<?php
/**
 * Paal 13 — Losse dag-beschikbaarheid.
 *
 * Vult het gat in het bestaande weken-model: één concrete kalenderdag die iemand
 * niet kan of liever vrij wil. Dekt beide gevallen uit de praktijk:
 *   - "12 augustus kan ik niet" (losse vrije dag)
 *   - "die week werk ik 5 dagen, maar niet die woensdag" (uitzondering op een week)
 *
 * Dit is een AANVULLING op beschikbaarheid2.php — het bestaande model blijft intact.
 *
 * De rooster-API wil per datum weten of iemand beschikbaar is. Daarvoor levert
 * beschikbaarheid_per_dag() een schone lijst: datum => beschikbaar (bool) + reden.
 */

if (!function_exists('beschikbaarheid_dag_auto_setup')) {

function beschikbaarheid_dag_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `beschikbaarheid_dag` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `datum` DATE NOT NULL,
            `soort` ENUM('niet_beschikbaar','voorkeur_vrij') NOT NULL DEFAULT 'niet_beschikbaar',
            `reden` VARCHAR(300) DEFAULT NULL,
            `bron` ENUM('sollicitant','bestuur') NOT NULL DEFAULT 'sollicitant',
            `status` ENUM('open','goedgekeurd','afgewezen') NOT NULL DEFAULT 'goedgekeurd',
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniek_dag` (`sollicitatie_id`, `datum`, `soort`),
            KEY `idx_sol_datum` (`sollicitatie_id`, `datum`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('beschikbaarheid_dag_auto_setup: ' . $e->getMessage()); }
}

/** Alle losse dagen van een sollicitant ophalen (optioneel binnen een datumbereik). */
function beschikbaarheid_dagen(int $sol_id, ?string $van = null, ?string $tot = null): array {
    beschikbaarheid_dag_auto_setup();
    try {
        $sql = "SELECT * FROM beschikbaarheid_dag WHERE sollicitatie_id=?";
        $args = [$sol_id];
        if ($van) { $sql .= " AND datum >= ?"; $args[] = $van; }
        if ($tot) { $sql .= " AND datum <= ?"; $args[] = $tot; }
        $sql .= " ORDER BY datum";
        $q = db()->prepare($sql); $q->execute($args);
        return $q->fetchAll();
    } catch (\Exception $e) { return []; }
}

/**
 * Losse dagen opslaan. $dagen = [['datum'=>'2026-08-12','soort'=>'niet_beschikbaar','reden'=>'..'], ...]
 * Vervangt de bestaande losse dagen van deze bron voor deze sollicitant.
 */
function beschikbaarheid_dagen_opslaan(int $sol_id, array $dagen, string $bron = 'sollicitant'): void {
    beschikbaarheid_dag_auto_setup();
    $db = db();
    try {
        // Verwijder eerst de bestaande dagen van déze bron (sollicitant overschrijft eigen invoer,
        // bestuur overschrijft eigen correcties — ze zitten elkaar niet in de weg).
        $db->prepare("DELETE FROM beschikbaarheid_dag WHERE sollicitatie_id=? AND bron=?")->execute([$sol_id, $bron]);
        $ins = $db->prepare("INSERT INTO beschikbaarheid_dag (sollicitatie_id, datum, soort, reden, bron, status)
                             VALUES (?,?,?,?,?,?)
                             ON DUPLICATE KEY UPDATE reden=VALUES(reden), status=VALUES(status)");
        foreach ($dagen as $d) {
            $datum = trim($d['datum'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) continue;   // alleen geldige datums
            $soort = in_array($d['soort'] ?? '', ['niet_beschikbaar','voorkeur_vrij'], true) ? $d['soort'] : 'niet_beschikbaar';
            $reden = trim($d['reden'] ?? '') ?: null;
            $status = in_array($d['status'] ?? '', ['open','goedgekeurd','afgewezen'], true) ? $d['status'] : 'goedgekeurd';
            $ins->execute([$sol_id, $datum, $soort, $reden, $bron, $status]);
        }
    } catch (\Exception $e) { error_log('beschikbaarheid_dagen_opslaan: ' . $e->getMessage()); }
}

/** Eén losse dag verwijderen (op id). */
function beschikbaarheid_dag_verwijderen(int $sol_id, int $dag_id): void {
    beschikbaarheid_dag_auto_setup();
    try {
        db()->prepare("DELETE FROM beschikbaarheid_dag WHERE id=? AND sollicitatie_id=?")->execute([$dag_id, $sol_id]);
    } catch (\Exception $e) {}
}

/**
 * DE KERN-API-FUNCTIE voor het rooster.
 * Geeft voor een sollicitant en een datumbereik terug: per dag beschikbaar ja/nee + waarom.
 * Voegt ALLE bronnen samen: vaste vrije dagen, afwezige periodes, en losse dagen.
 *
 * @return array  datum('Y-m-d') => [
 *                   'beschikbaar' => bool,
 *                   'reden'       => string,   // waarom niet (leeg als beschikbaar)
 *                   'hardheid'    => 'hard'|'zacht'|null,  // hard=kan echt niet, zacht=liever niet
 *                 ]
 */
function beschikbaarheid_per_dag(int $sol_id, string $van, string $tot): array {
    beschikbaarheid_dag_auto_setup();
    $uit = [];

    try {
        $start = new DateTime($van);
        $eind  = new DateTime($tot);
    } catch (\Exception $e) {
        return [];
    }
    if ($eind < $start) return [];

    // 1. Basis: begin met ALLES niet-beschikbaar. Een dag wordt pas beschikbaar
    //    als hij binnen een werkperiode valt én bij het regime past (stap 1b).
    //    Zo staat iemand met "alleen weekenden" doordeweeks correct op niet-beschikbaar,
    //    en dagen buiten alle werkperiodes ook.
    $cursor = clone $start;
    while ($cursor <= $eind) {
        $uit[$cursor->format('Y-m-d')] = ['beschikbaar' => false, 'reden' => 'buiten werkperiode', 'hardheid' => 'hard'];
        $cursor->modify('+1 day');
    }

    // 1b. Werkregime toepassen: welke dagen kan iemand volgens zijn periode-blokken?
    //     regime 'vijf'   = hele week werkbaar (ma t/m zo; vaste vrije dagen gaan er later af)
    //     regime 'weekend'= alleen de gekozen weekenddagen (default vr/za/zo)
    //     Per week telt het EERSTE blok dat die week dekt (net als in beschikbaarheid2).
    $regime_per_week = [];  // ISO-weeknr => ['kandidaat'=>[weekdagen], ] — voor stap 5 (verzoeken)
    try {
        $b = db()->prepare("SELECT van_week, tot_week, regime, weekend_dagen
                            FROM beschikbaarheid_blok WHERE sollicitatie_id=? ORDER BY van_week");
        $b->execute([$sol_id]);
        $blokken = $b->fetchAll();
        foreach ($blokken as $blok) {
            $regime = ($blok['regime'] ?? 'vijf') === 'weekend' ? 'weekend' : 'vijf';
            $weekend_dagen = json_decode($blok['weekend_dagen'] ?? 'null', true) ?: [5,6,7];
            $weekend_dagen = array_values(array_intersect(array_map('intval', $weekend_dagen), [5,6,7]));
            if ($regime === 'weekend' && !$weekend_dagen) $weekend_dagen = [5,6,7];
            $kandidaat = $regime === 'weekend' ? $weekend_dagen : [1,2,3,4,5,6,7];

            for ($wk = (int)$blok['van_week']; $wk <= (int)$blok['tot_week']; $wk++) {
                if (isset($regime_per_week[$wk])) continue;  // eerste blok wint bij overlap
                $regime_per_week[$wk] = ['kandidaat' => $kandidaat];
            }
        }
        // Nu per dag: beschikbaar als de week gedekt is én de weekdag kandidaat is
        foreach ($uit as $datum => &$info) {
            $dt = new DateTime($datum);
            $wk = (int)$dt->format('W');
            $weekdag = (int)$dt->format('N');
            if (isset($regime_per_week[$wk]) && in_array($weekdag, $regime_per_week[$wk]['kandidaat'], true)) {
                $info['beschikbaar'] = true;
                $info['reden'] = '';
                $info['hardheid'] = null;
            }
        }
        unset($info);
    } catch (\Exception $e) {}

    // 2. Vaste vrije weekdagen (bijv. elke woensdag school) — uit het profiel.
    try {
        $p = db()->prepare("SELECT vaste_dagen_uit, vaste_is_voorkeur, vaste_toelichting FROM beschikbaarheid_profiel WHERE sollicitatie_id=?");
        $p->execute([$sol_id]);
        if ($prof = $p->fetch()) {
            $vaste = json_decode($prof['vaste_dagen_uit'] ?? '[]', true) ?: [];
            $vaste = array_map('intval', $vaste);
            $is_voorkeur = !empty($prof['vaste_is_voorkeur']);
            if ($vaste) {
                foreach ($uit as $datum => &$info) {
                    $weekdag = (int)(new DateTime($datum))->format('N'); // 1=ma .. 7=zo
                    if (in_array($weekdag, $vaste, true)) {
                        $info['beschikbaar'] = false;
                        $info['reden'] = trim($prof['vaste_toelichting'] ?? '') ?: 'vaste vrije dag';
                        $info['hardheid'] = $is_voorkeur ? 'zacht' : 'hard';
                    }
                }
                unset($info);
            }
        }
    } catch (\Exception $e) {}

    // 3. Afwezige periodes (vakanties) — harde blokkade.
    try {
        $a = db()->prepare("SELECT van_datum, tot_datum, label FROM beschikbaarheid_afwezig WHERE sollicitatie_id=?");
        $a->execute([$sol_id]);
        foreach ($a->fetchAll() as $afw) {
            foreach ($uit as $datum => &$info) {
                if ($datum >= $afw['van_datum'] && $datum <= $afw['tot_datum']) {
                    $info['beschikbaar'] = false;
                    $info['reden'] = trim($afw['label'] ?? '') ?: 'afwezig';
                    $info['hardheid'] = 'hard';
                }
            }
            unset($info);
        }
    } catch (\Exception $e) {}

    // 4. Losse dagen (het nieuwe stuk) — de meest specifieke, dus deze wint.
    //    Alleen goedgekeurde (of direct-genoteerde) dagen tellen mee voor het rooster.
    foreach (beschikbaarheid_dagen($sol_id, $van, $tot) as $dag) {
        if (($dag['status'] ?? 'goedgekeurd') !== 'goedgekeurd') continue;
        $datum = $dag['datum'];
        if (!isset($uit[$datum])) continue;
        $uit[$datum]['beschikbaar'] = false;
        $uit[$datum]['reden'] = trim($dag['reden'] ?? '') ?: ($dag['soort'] === 'voorkeur_vrij' ? 'liever vrij' : 'niet beschikbaar');
        $uit[$datum]['hardheid'] = ($dag['soort'] === 'voorkeur_vrij') ? 'zacht' : 'hard';
    }

    // 5. Verzoeken "minder dagen per week" (goedgekeurd) — met voorkeursdagen (pool).
    //    Als iemand in bepaalde weken max N dagen wil, en aangeeft welke dagen wél (pool),
    //    dan zijn de dagen BUITEN de pool die week niet beschikbaar. Zo krijgt de rooster-API
    //    door: deze week alleen op deze specifieke dagen, en hooguit N.
    try {
        $v = db()->prepare("SELECT van_week, tot_week, dagen_aantal, dagen_pool, toelichting
                            FROM beschikbaarheid_verzoek WHERE sollicitatie_id=? AND status='goedgekeurd'");
        $v->execute([$sol_id]);
        foreach ($v->fetchAll() as $vz) {
            $pool = json_decode($vz['dagen_pool'] ?? 'null', true);
            $pool = is_array($pool) ? array_map('intval', $pool) : null;
            $reden = trim($vz['toelichting'] ?? '') ?: 'minder dagen (verzoek)';
            // Alleen zinvol als er voorkeursdagen zijn opgegeven; anders laat de rooster-API
            // zelf kiezen welke N dagen — dan blokkeren we hier niets, maar geven het wel door.
            if (!$pool) continue;
            for ($wk = (int)$vz['van_week']; $wk <= (int)$vz['tot_week']; $wk++) {
                foreach ($uit as $datum => &$info) {
                    $dt = new DateTime($datum);
                    if ((int)$dt->format('W') !== $wk) continue;
                    if (!$info['beschikbaar']) continue;              // al geblokkeerd, laat staan
                    $weekdag = (int)$dt->format('N');
                    if (!in_array($weekdag, $pool, true)) {
                        // Deze dag valt buiten de voorkeursdagen van het verzoek → niet beschikbaar.
                        // Hard: als iemand max N dagen opgeeft, kan hij de andere dagen echt niet.
                        $info['beschikbaar'] = false;
                        $info['reden'] = $reden;
                        $info['hardheid'] = 'hard';
                    }
                }
                unset($info);
            }
        }
    } catch (\Exception $e) {}

    return $uit;
}

}

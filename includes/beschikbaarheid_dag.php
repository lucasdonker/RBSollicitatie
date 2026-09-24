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
    static $done = false; if ($done || schema_actueel()) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `beschikbaarheid_dag` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `datum` DATE NOT NULL,
            `soort` ENUM('niet_beschikbaar','voorkeur_vrij','beschikbaar') NOT NULL DEFAULT 'niet_beschikbaar',
            `reden` VARCHAR(300) DEFAULT NULL,
            `bron` ENUM('sollicitant','bestuur') NOT NULL DEFAULT 'sollicitant',
            `status` ENUM('open','goedgekeurd','afgewezen') NOT NULL DEFAULT 'goedgekeurd',
            `gewijzigd_door` INT DEFAULT NULL COMMENT 'beheerder_id bij bron=bestuur',
            `gewijzigd_op` DATETIME DEFAULT NULL,
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
 * Bestuurs-override voor één dag. Het bestuur zet een specifieke datum expliciet op
 * beschikbaar of niet-beschikbaar; dat wordt de eindwaarheid (bron='bestuur' wint in de
 * merge). Legt vast wie het deed en wanneer, zodat alles herleidbaar is.
 *
 * @param string $soort 'beschikbaar' | 'niet_beschikbaar' | 'voorkeur_vrij'
 */
function beschikbaarheid_dag_bestuur_zet(int $sol_id, string $datum, string $soort, ?string $reden, int $beheerder_id): void {
    beschikbaarheid_dag_auto_setup();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) return;
    if (!in_array($soort, ['beschikbaar','niet_beschikbaar','voorkeur_vrij'], true)) return;
    try {
        $db = db();
        // Precies één bestuurs-waarheid per dag: verwijder eerst een eventuele eerdere.
        $db->prepare("DELETE FROM beschikbaarheid_dag WHERE sollicitatie_id=? AND datum=? AND bron='bestuur'")
           ->execute([$sol_id, $datum]);
        $db->prepare("INSERT INTO beschikbaarheid_dag
                        (sollicitatie_id, datum, soort, reden, bron, status, gewijzigd_door, gewijzigd_op)
                      VALUES (?,?,?,?,'bestuur','goedgekeurd',?,NOW())")
           ->execute([$sol_id, $datum, $soort, (($reden !== null && trim($reden) !== '') ? trim($reden) : null), $beheerder_id]);
        if (function_exists('audit_log')) {
            audit_log('gewijzigd', 'beschikbaarheid', $sol_id, ['datum' => $datum, 'soort' => $soort, 'reden' => $reden, 'via' => 'bestuur_per_dag']);
        }
    } catch (\Exception $e) { error_log('beschikbaarheid_dag_bestuur_zet: ' . $e->getMessage()); }
}

/** Een bestuurs-override voor één dag terugdraaien (dan geldt weer de sollicitant-invoer). */
function beschikbaarheid_dag_bestuur_verwijder(int $sol_id, string $datum): void {
    beschikbaarheid_dag_auto_setup();
    try {
        db()->prepare("DELETE FROM beschikbaarheid_dag WHERE sollicitatie_id=? AND datum=? AND bron='bestuur'")
            ->execute([$sol_id, $datum]);
        if (function_exists('audit_log')) {
            audit_log('gewijzigd', 'beschikbaarheid', $sol_id, ['datum' => $datum, 'via' => 'bestuur_teruggedraaid']);
        }
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
        $uit[$cursor->format('Y-m-d')] = ['beschikbaar' => false, 'reden' => 'week niet opgegeven', 'hardheid' => 'hard'];
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
            } elseif (isset($regime_per_week[$wk])) {
                $info['reden'] = 'alleen weekend opgegeven';
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

    // 4. Losse dagen — de meest specifieke laag. Sollicitant-dagen eerst, dan bestuur:
    //    een bestuurs-override wint altijd (dat is de afgesproken eindwaarheid).
    $losse = beschikbaarheid_dagen($sol_id, $van, $tot);
    // Sorteer zodat 'sollicitant' vóór 'bestuur' komt (bestuur overschrijft daarna).
    usort($losse, function ($a, $b) {
        $rang = ['sollicitant' => 0, 'bestuur' => 1];
        return ($rang[$a['bron'] ?? 'sollicitant'] ?? 0) <=> ($rang[$b['bron'] ?? 'sollicitant'] ?? 0);
    });
    foreach ($losse as $dag) {
        if (($dag['status'] ?? 'goedgekeurd') !== 'goedgekeurd') continue;
        $datum = $dag['datum'];
        if (!isset($uit[$datum])) continue;
        $soort = $dag['soort'] ?? 'niet_beschikbaar';
        $is_bestuur = ($dag['bron'] ?? 'sollicitant') === 'bestuur';

        if ($soort === 'beschikbaar') {
            // Expliciet beschikbaar (alleen zinvol als bestuurs-override van een blokkade).
            $uit[$datum]['beschikbaar'] = true;
            $uit[$datum]['reden'] = '';
            $uit[$datum]['hardheid'] = null;
        } else {
            $uit[$datum]['beschikbaar'] = false;
            $uit[$datum]['reden'] = trim($dag['reden'] ?? '') ?: ($soort === 'voorkeur_vrij' ? 'liever vrij' : 'niet beschikbaar');
            $uit[$datum]['hardheid'] = ($soort === 'voorkeur_vrij') ? 'zacht' : 'hard';
        }
        if ($is_bestuur) $uit[$datum]['door_bestuur'] = true;   // markering voor herleidbaarheid
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

    // 6. Weekcorrecties van het bestuur (beheer › beschikbaarheid, cel aanklikken). Die stonden
    //    wél in het weekraster maar niet hier, waardoor het rooster iemand kon inplannen op een
    //    week die het bestuur had geschrapt. 'niet' = hele week vervalt; 'minder' = alleen deze dagen.
    try {
        $c = db()->prepare("SELECT week, soort, dagen, reden FROM beschikbaarheid_correctie WHERE sollicitatie_id=?");
        $c->execute([$sol_id]);
        foreach ($c->fetchAll() as $corr) {
            $wk = (int)$corr['week'];
            $rest = json_decode($corr['dagen'] ?? 'null', true);
            $rest = is_array($rest) ? array_map('intval', $rest) : null;
            $reden = 'bestuur: ' . (trim((string)($corr['reden'] ?? '')) ?: ($corr['soort'] === 'niet' ? 'week vervalt' : 'minder dagen'));
            foreach ($uit as $datum => &$info) {
                $dt = new DateTime($datum);
                if ((int)$dt->format('W') !== $wk || !$info['beschikbaar']) continue;
                if ($corr['soort'] === 'niet' || ($rest !== null && !in_array((int)$dt->format('N'), $rest, true))) {
                    $info = ['beschikbaar' => false, 'reden' => $reden, 'hardheid' => 'hard', 'door_bestuur' => true];
                }
            }
            unset($info);
        }
    } catch (\Exception $e) {}

    return $uit;
}

}

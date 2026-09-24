<?php
/**
 * Paal 13 — Loonplanning (fase 3)
 *
 * Interactief planbord op WEEKNIVEAU (zoals het loon werkt):
 * per persoon per week een aantal dagen + functie.
 *
 * Het systeem rekent en signaleert; de mens beslist.
 * Geen optimizer die de planning invult.
 *
 * Kern:
 *  - Scenario's: meerdere planningen naast elkaar, één is actief
 *  - Kosten live tegen budget
 *  - Dekking-tellers op basis van wie je ECHT inplant (niet wie beschikbaar is)
 *  - Zachte signalen: >5 dagen/week, te weinig rust over de weekgrens,
 *    meer ingepland dan iemand beschikbaar is
 */

function loonplan_auto_setup(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = db();
        $db->exec("CREATE TABLE IF NOT EXISTS `loon_scenario` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `naam` VARCHAR(80) NOT NULL,
            `actief` TINYINT(1) NOT NULL DEFAULT 0,
            `notitie` VARCHAR(255) DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`seizoen_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // scenario_id op loon_planning (bestaande rijen → scenario 0 = 'basis')
        $dbn = $db->query("SELECT DATABASE()")->fetchColumn();
        $c = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='loon_planning' AND COLUMN_NAME='scenario_id'");
        $c->execute([$dbn]);
        if (!$c->fetchColumn()) {
            try { $db->exec("ALTER TABLE `loon_planning` ADD COLUMN `scenario_id` INT NOT NULL DEFAULT 0"); }
            catch (\Exception $e) {}
        }
        // De unieke sleutel moet het scenario meenemen. De oude sleutel heet `sol_week`
        // (instellingen.php); zolang die bestaat overschrijven scenario's elkaars cellen en
        // faalt dupliceren. Eerst de nieuwe erbij, dan pas de oude weg: er is altijd één uniek.
        if (!db_index_bestaat($db, 'loon_planning', 'uniek_scen')) {
            try { $db->exec("ALTER TABLE `loon_planning` ADD UNIQUE KEY `uniek_scen` (`sollicitatie_id`,`week_nr`,`jaar`,`scenario_id`)"); }
            catch (\Exception $e) { error_log('loonplan uniek_scen: ' . $e->getMessage()); }
        }
        foreach (['sol_week', 'uniek'] as $oud) {
            if (db_index_bestaat($db, 'loon_planning', $oud)) {
                try { $db->exec("ALTER TABLE `loon_planning` DROP INDEX `$oud`"); }
                catch (\Exception $e) { error_log("loonplan drop $oud: " . $e->getMessage()); }
            }
        }
    } catch (\Exception $e) { error_log('loonplan_auto_setup: ' . $e->getMessage()); }
}

/** Scenario's van een seizoen. */
function loonplan_scenarios(int $seizoen_id): array {
    loonplan_auto_setup();
    try {
        $q = db()->prepare("SELECT * FROM loon_scenario WHERE seizoen_id=? ORDER BY actief DESC, id");
        $q->execute([$seizoen_id]);
        $rijen = $q->fetchAll();
    } catch (\Exception $e) { return []; }

    // Zorg dat er altijd minstens één scenario is (de bestaande planning = scenario 0)
    if (!$rijen) {
        try {
            db()->prepare("INSERT INTO loon_scenario (seizoen_id, naam, actief) VALUES (?, 'Basisplanning', 1)")
               ->execute([$seizoen_id]);
            $id = (int)db()->lastInsertId();
            // Bestaande planning (scenario_id=0) koppelen aan dit scenario
            db()->prepare("UPDATE loon_planning SET scenario_id=? WHERE scenario_id=0")->execute([$id]);
            $q = db()->prepare("SELECT * FROM loon_scenario WHERE seizoen_id=? ORDER BY id");
            $q->execute([$seizoen_id]);
            $rijen = $q->fetchAll();
        } catch (\Exception $e) { return []; }
    }
    return $rijen;
}

/** Het actieve scenario (of het eerste). */
function loonplan_actief_scenario(int $seizoen_id): ?array {
    $s = loonplan_scenarios($seizoen_id);
    if (!$s) return null;
    foreach ($s as $sc) if (!empty($sc['actief'])) return $sc;
    return $s[0];
}

function loonplan_scenario_activeer(int $seizoen_id, int $scenario_id): void {
    loonplan_auto_setup();
    try {
        db()->prepare("UPDATE loon_scenario SET actief=0 WHERE seizoen_id=?")->execute([$seizoen_id]);
        db()->prepare("UPDATE loon_scenario SET actief=1 WHERE id=? AND seizoen_id=?")->execute([$scenario_id, $seizoen_id]);
    } catch (\Exception $e) {}
}

/** Scenario dupliceren, inclusief alle planningsregels. */
function loonplan_scenario_dupliceer(int $seizoen_id, int $bron_id, string $naam): ?int {
    loonplan_auto_setup();
    try {
        $db = db();
        $db->prepare("INSERT INTO loon_scenario (seizoen_id, naam, actief) VALUES (?,?,0)")
           ->execute([$seizoen_id, mb_substr(trim($naam), 0, 80) ?: 'Kopie']);
        $nieuw = (int)$db->lastInsertId();
        $db->prepare("
            INSERT INTO loon_planning (sollicitatie_id, week_nr, jaar, dagen, functie_sleutel, opgenomen, loon_override, notitie, scenario_id)
            SELECT sollicitatie_id, week_nr, jaar, dagen, functie_sleutel, opgenomen, loon_override, notitie, ?
            FROM loon_planning WHERE scenario_id = ?
        ")->execute([$nieuw, $bron_id]);
        return $nieuw;
    } catch (\Exception $e) { error_log('scenario dupliceren: ' . $e->getMessage()); return null; }
}

function loonplan_scenario_verwijder(int $seizoen_id, int $scenario_id): bool {
    loonplan_auto_setup();
    try {
        $s = loonplan_scenarios($seizoen_id);
        if (count($s) <= 1) return false; // laatste scenario niet verwijderen
        db()->prepare("DELETE FROM loon_planning WHERE scenario_id=?")->execute([$scenario_id]);
        db()->prepare("DELETE FROM loon_scenario WHERE id=? AND seizoen_id=?")->execute([$scenario_id, $seizoen_id]);
        // Als het actieve is verwijderd: eerste weer actief maken
        $rest = loonplan_scenarios($seizoen_id);
        $heeft_actief = false;
        foreach ($rest as $sc) if (!empty($sc['actief'])) $heeft_actief = true;
        if (!$heeft_actief && $rest) loonplan_scenario_activeer($seizoen_id, (int)$rest[0]['id']);
        return true;
    } catch (\Exception $e) { return false; }
}

/** Budget van het seizoen (instelling). */
function loonplan_budget(): float {
    return function_exists('instelling_float') ? (float)instelling_float('loon.budget_seizoen', 0) : 0.0;
}

/**
 * Volledige planningsanalyse voor één scenario.
 *
 * Retourneert:
 *  'mensen'  => [sol_id => ['naam','leeftijd','functie','comp','beschikbaar'=>[wk=>max], 'weken'=>[wk=>['dagen','opgenomen','dagloon','kosten']], 'totaal_kosten','totaal_dagen']]
 *  'per_week'=> [wk => ['kosten','dagen','mensen']]
 *  'totaal'  => ['kosten','dagen','budget','over_budget']
 *  'dekking' => [wk => dekkingsresultaat op basis van INGEPLANDE mensen]
 *  'signalen'=> [ ['soort','sol_id','naam','week','tekst'], ... ]
 */
function loonplan_analyse(int $seizoen_id, int $scenario_id, ?array $seizoen = null): array {
    loonplan_auto_setup();
    if ($seizoen === null && function_exists('actief_seizoen')) $seizoen = actief_seizoen();
    $jaar = $seizoen && !empty($seizoen['datum_werk_start']) ? (int)date('Y', strtotime($seizoen['datum_werk_start'])) : (int)date('Y');

    // Mensen van dit seizoen
    try {
        $q = db()->prepare("
            SELECT s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum
            FROM sollicitaties s
            JOIN personen p ON p.id = s.persoon_id
            WHERE s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt')
            ORDER BY p.achternaam, p.voornaam
        ");
        $q->execute([$seizoen_id]);
        $rijen = $q->fetchAll();
    } catch (\Exception $e) { $rijen = []; }

    // Bestaande planningsregels
    $plan = [];
    try {
        $q = db()->prepare("SELECT * FROM loon_planning WHERE jaar=? AND scenario_id=?");
        $q->execute([$jaar, $scenario_id]);
        foreach ($q->fetchAll() as $r) $plan[(int)$r['sollicitatie_id']][(int)$r['week_nr']] = $r;
    } catch (\Exception $e) {}

    $mensen   = [];
    $per_week = [];
    $pool     = [];   // voor de dekkingscheck: wie is er INGEPLAND per week
    $signalen = [];
    $tot_kosten = 0.0; $tot_dagen = 0;

    foreach ($rijen as $r) {
        $sol_id = (int)$r['sol_id'];
        $naam = trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam']);
        $lft  = $r['geboortedatum'] ? (int)(new DateTime($r['geboortedatum']))->diff(new DateTime())->y : 18;

        $comp = function_exists('bezetting_comp_van') ? bezetting_comp_van($sol_id) : [];
        $eff  = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol_id, $seizoen) : [];

        $beschikbaar = [];
        foreach ($eff as $wk => $w) $beschikbaar[$wk] = (int)$w['dagen_max'];

        $weken = [];
        $p_kosten = 0.0; $p_dagen = 0;

        foreach ($beschikbaar as $wk => $max) {
            $pl    = $plan[$sol_id][$wk] ?? null;
            $dagen = $pl ? (int)$pl['dagen'] : 0;
            $opgen = $pl ? (int)$pl['opgenomen'] : 0;
            $fn    = $pl['functie_sleutel'] ?? 'lifeguard';
            $periode = loonplan_periode($wk, $seizoen);
            $dagloon = function_exists('bereken_dagloon')
                     ? bereken_dagloon($lft, $fn, $periode, isset($pl['loon_override']) && $pl['loon_override'] !== null ? (float)$pl['loon_override'] : null)
                     : 0.0;
            $kosten = $opgen ? round($dagloon * $dagen, 2) : 0.0;

            $weken[$wk] = [
                'dagen'     => $dagen,
                'opgenomen' => $opgen,
                'functie'   => $fn,
                'dagloon'   => $dagloon,
                'kosten'    => $kosten,
                'max'       => $max,
            ];

            if ($opgen && $dagen > 0) {
                $p_kosten += $kosten; $p_dagen += $dagen;
                $per_week[$wk]['kosten'] = ($per_week[$wk]['kosten'] ?? 0) + $kosten;
                $per_week[$wk]['dagen']  = ($per_week[$wk]['dagen'] ?? 0) + $dagen;
                $per_week[$wk]['mensen'] = ($per_week[$wk]['mensen'] ?? 0) + 1;

                // Deze persoon telt mee voor de dekking van die week
                $pool[$wk][] = ['sol_id'=>$sol_id, 'naam'=>$naam, 'comp'=>$comp, 'dagen'=>$dagen];

                // ── Signalen ──
                if ($dagen > $max) {
                    $signalen[] = ['soort'=>'boven_beschikbaar', 'sol_id'=>$sol_id, 'naam'=>$naam, 'week'=>$wk,
                        'tekst'=>"$naam is week $wk voor $dagen dagen ingepland, maar is er maar $max beschikbaar."];
                }
                if ($dagen > 5) {
                    $signalen[] = ['soort'=>'veel_dagen', 'sol_id'=>$sol_id, 'naam'=>$naam, 'week'=>$wk,
                        'tekst'=>"$naam werkt week $wk $dagen dagen (meer dan 5)."];
                }
            }
        }

        // Rust over de weekgrens: twee opeenvolgende weken samen > 10 dagen
        // (4+6 = 10 is legitiem; 6+6 = 12 betekent te lang aaneen zonder rust)
        $wk_lijst = array_keys($weken);
        sort($wk_lijst);
        foreach ($wk_lijst as $wk) {
            $vlg = $wk + 1;
            if (!isset($weken[$vlg])) continue;
            $a = ($weken[$wk]['opgenomen']  ? $weken[$wk]['dagen']  : 0);
            $b = ($weken[$vlg]['opgenomen'] ? $weken[$vlg]['dagen'] : 0);
            if ($a + $b > 10) {
                $signalen[] = ['soort'=>'weinig_rust', 'sol_id'=>$sol_id, 'naam'=>$naam, 'week'=>$wk,
                    'tekst'=>"$naam werkt week $wk en $vlg samen " . ($a+$b) . " dagen — weinig rust over de weekgrens."];
            }
        }

        $mensen[$sol_id] = [
            'naam'          => $naam,
            'leeftijd'      => $lft,
            'comp'          => $comp,
            'beschikbaar'   => $beschikbaar,
            'weken'         => $weken,
            'totaal_kosten' => round($p_kosten, 2),
            'totaal_dagen'  => $p_dagen,
        ];
        $tot_kosten += $p_kosten;
        $tot_dagen  += $p_dagen;
    }

    ksort($per_week);
    $budget = loonplan_budget();

    return [
        'mensen'   => $mensen,
        'per_week' => $per_week,
        'totaal'   => [
            'kosten'      => round($tot_kosten, 2),
            'dagen'       => $tot_dagen,
            'budget'      => $budget,
            'over_budget' => $budget > 0 && $tot_kosten > $budget,
            'restant'     => $budget > 0 ? round($budget - $tot_kosten, 2) : null,
        ],
        'dekking'  => function_exists('bezetting_dekking_van_pool') ? bezetting_dekking_van_pool($pool) : [],
        'signalen' => $signalen,
    ];
}

/** Loonperiode van een week: juni = 'jun', juli t/m september = 'jul_sep'. */
function loonplan_periode(int $week, ?array $seizoen = null): string {
    $wd = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen) : [];
    if (isset($wd[$week])) {
        $maand = (int)$wd[$week]['start']->format('n');
        return $maand <= 6 ? 'jun' : 'jul_sep';
    }
    return 'jul_sep';
}

/** Eén planningscel opslaan. */
function loonplan_cel_opslaan(int $scenario_id, int $sol_id, int $week, int $jaar, int $dagen, int $opgenomen, ?string $functie): void {
    loonplan_auto_setup();
    $dagen = max(0, min(7, $dagen));
    try {
        db()->prepare("
            INSERT INTO loon_planning (sollicitatie_id, week_nr, jaar, dagen, functie_sleutel, opgenomen, scenario_id)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE dagen=VALUES(dagen), functie_sleutel=VALUES(functie_sleutel),
                                    opgenomen=VALUES(opgenomen), bijgewerkt_op=NOW()
        ")->execute([$sol_id, $week, $jaar, $dagen, $functie ?: null, $opgenomen ? 1 : 0, $scenario_id]);
    } catch (\Exception $e) { error_log('loonplan_cel_opslaan: ' . $e->getMessage()); }
}

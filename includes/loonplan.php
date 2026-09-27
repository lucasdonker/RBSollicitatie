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
    if ($done || schema_actueel()) return;
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
        // 2.106.0: betaald/onbetaald per week ("8 weken waarvan 5 betaald") en optioneel welke
        // weekdagen. Bestaande regels waren allemaal betaald.
        db_kolom_verzeker($db, 'loon_planning', 'betaald', 'TINYINT(1) NOT NULL DEFAULT 1');
        db_kolom_verzeker($db, 'loon_planning', 'weekdagen', 'VARCHAR(30) DEFAULT NULL');
        // De functie hoort bij de persoon, niet bij een week. Per scenario, want puzzelen mag.
        $db->exec("CREATE TABLE IF NOT EXISTS `loon_scenario_persoon` (
            `scenario_id` INT NOT NULL,
            `sollicitatie_id` INT NOT NULL,
            `functie_sleutel` VARCHAR(50) NOT NULL,
            PRIMARY KEY (`scenario_id`, `sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
            INSERT INTO loon_planning (sollicitatie_id, week_nr, jaar, dagen, functie_sleutel, opgenomen, betaald, weekdagen, loon_override, notitie, scenario_id)
            SELECT sollicitatie_id, week_nr, jaar, dagen, functie_sleutel, opgenomen, betaald, weekdagen, loon_override, notitie, ?
            FROM loon_planning WHERE scenario_id = ?
        ")->execute([$nieuw, $bron_id]);
        $db->prepare("INSERT INTO loon_scenario_persoon (scenario_id, sollicitatie_id, functie_sleutel)
                      SELECT ?, sollicitatie_id, functie_sleutel FROM loon_scenario_persoon WHERE scenario_id = ?")
           ->execute([$nieuw, $bron_id]);
        return $nieuw;
    } catch (\Exception $e) { error_log('scenario dupliceren: ' . $e->getMessage()); return null; }
}

function loonplan_scenario_verwijder(int $seizoen_id, int $scenario_id): bool {
    loonplan_auto_setup();
    try {
        $s = loonplan_scenarios($seizoen_id);
        if (count($s) <= 1) return false; // laatste scenario niet verwijderen
        db()->prepare("DELETE FROM loon_planning WHERE scenario_id=?")->execute([$scenario_id]);
        db()->prepare("DELETE FROM loon_scenario_persoon WHERE scenario_id=?")->execute([$scenario_id]);
        db()->prepare("DELETE FROM loon_scenario WHERE id=? AND seizoen_id=?")->execute([$scenario_id, $seizoen_id]);
        // Als het actieve is verwijderd: eerste weer actief maken
        $rest = loonplan_scenarios($seizoen_id);
        $heeft_actief = false;
        foreach ($rest as $sc) if (!empty($sc['actief'])) $heeft_actief = true;
        if (!$heeft_actief && $rest) loonplan_scenario_activeer($seizoen_id, (int)$rest[0]['id']);
        return true;
    } catch (\Exception $e) { return false; }
}

/** Budget van het jaar van dit seizoen (includes/loon_tarieven.php; 0 = geen budget). */
function loonplan_budget(?array $seizoen = null): float {
    return function_exists('loon_tarieven_voor_seizoen') ? (float)loon_tarieven_voor_seizoen($seizoen)['budget'] : 0.0;
}

/**
 * Welk tariefjaar de planning van dit seizoen gebruikt, en de zin als dat een ander jaar is.
 * @return array{jaar:int, bron_jaar:?int, melding:string}
 */
function loonplan_tarief_info(?array $seizoen): array {
    if (!function_exists('loon_tarieven_voor_seizoen')) return ['jaar' => loonplan_jaar($seizoen), 'bron_jaar' => null, 'melding' => ''];
    $t = loon_tarieven_voor_seizoen($seizoen);
    return ['jaar' => $t['jaar'], 'bron_jaar' => $t['bron_jaar'], 'melding' => loon_tarieven_melding($t)];
}

/**
 * Dagloon voor één persoon in één week: leeftijd op de maandag van die week, periode (juni of
 * juli–september), functie en de tarieven van het jaar van het seizoen. Eén plek, zodat de planning
 * en de export altijd hetzelfde rekenen.
 * Retourneert ['dagloon' => float, 'leeftijd' => int].
 */
function loonplan_week_dagloon(string $geboortedatum, string $functie, int $week, array $weken_datums, ?array $seizoen, ?float $override = null): array {
    $maandag = isset($weken_datums[$week]) ? $weken_datums[$week]['start']->format('Y-m-d') : (string)($seizoen['datum_werk_start'] ?? date('Y-m-d'));
    $lft = $geboortedatum !== '' ? leeftijd_op_datum($geboortedatum, $maandag) : 18;
    $dagloon = function_exists('bereken_dagloon') ? bereken_dagloon($lft, $functie, loonplan_periode($week, $seizoen), $override, loonplan_jaar($seizoen)) : 0.0;
    return ['dagloon' => $dagloon, 'leeftijd' => $lft];
}

/** Seizoensjaar (voor loon_planning.jaar en de tarieven): zie loon_seizoen_jaar(). */
function loonplan_jaar(?array $seizoen): int {
    if (function_exists('loon_seizoen_jaar')) return loon_seizoen_jaar($seizoen);
    return $seizoen && !empty($seizoen['datum_werk_start']) ? (int)date('Y', strtotime($seizoen['datum_werk_start'])) : (int)date('Y');
}

/**
 * Volledige planningsanalyse voor één scenario.
 *
 * Elke week van het seizoen is planbaar, ook een week die iemand niet opgaf: op het
 * trainingsweekend wordt soms meer (of minder) afgesproken dan opgegeven. Dat is een
 * signaal, geen blokkade. Alleen betaalde dagen kosten geld.
 *
 * Retourneert:
 *  'mensen'  => [sol_id => ['naam','leeftijd','functie','functie_bron' (scenario|voorstel),'comp',
 *                'beschikbaar'=>[wk=>max], 'weken'=>[wk=>['dagen','opgenomen','betaald','weekdagen',
 *                'dagloon','kosten','max','leeftijd']], 'totaal_kosten','dagen_betaald','dagen_onbetaald',
 *                'totaal_dagen','afspraak'=>null|['vastgesteld_op','wijkt_af']]]
 *  'per_week'=> [wk => ['kosten','dagen','dagen_onbetaald','mensen']]
 *  'totaal'  => ['kosten','dagen','dagen_onbetaald','budget','over_budget','restant']
 *  'dekking' => [wk => dekkingsresultaat op basis van INGEPLANDE mensen]
 *  'signalen'=> [ ['soort','sol_id','naam','week','tekst'], ... ]
 */
function loonplan_analyse(int $seizoen_id, int $scenario_id, ?array $seizoen = null, ?int $alleen_sol = null): array {
    loonplan_auto_setup();
    if ($seizoen === null && function_exists('actief_seizoen')) $seizoen = actief_seizoen();
    $jaar = loonplan_jaar($seizoen);
    $weken_datums = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen) : [];
    // $alleen_sol: alleen deze persoon doorrekenen (afspraak-editor, weekpaneel). Totalen en dekking
    // gaan dan alleen over die persoon — gebruik alleen ['mensen'][$alleen_sol].
    $een = $alleen_sol !== null && $alleen_sol > 0;

    // Mensen van dit seizoen
    try {
        $q = db()->prepare("
            SELECT s.id AS sol_id, s.fase, s.terugkerend, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum
            FROM sollicitaties s
            JOIN personen p ON p.id = s.persoon_id
            WHERE s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt','verwijderd_door_sollicitant')" . ($een ? " AND s.id = ?" : "") . "
            ORDER BY p.achternaam, p.voornaam
        ");
        $q->execute($een ? [$seizoen_id, $alleen_sol] : [$seizoen_id]);
        $rijen = $q->fetchAll();
    } catch (\Exception $e) { $rijen = []; }

    // Bestaande planningsregels en functies van dit scenario
    $plan = [];
    try {
        $q = db()->prepare("SELECT * FROM loon_planning WHERE jaar=? AND scenario_id=?" . ($een ? " AND sollicitatie_id=?" : ""));
        $q->execute($een ? [$jaar, $scenario_id, $alleen_sol] : [$jaar, $scenario_id]);
        foreach ($q->fetchAll() as $r) $plan[(int)$r['sollicitatie_id']][(int)$r['week_nr']] = $r;
    } catch (\Exception $e) {}
    $functies_sc = loonplan_functies_scenario($scenario_id);
    $afspraken   = function_exists('inzet_afspraken_seizoen') ? inzet_afspraken_seizoen($seizoen_id) : [];

    $mensen   = [];
    $per_week = [];
    $pool     = [];   // voor de dekkingscheck: wie is er INGEPLAND per week
    $signalen = [];
    $tot_kosten = 0.0; $tot_dagen = 0; $tot_onbetaald = 0;

    foreach ($rijen as $r) {
        $sol_id = (int)$r['sol_id'];
        $naam = trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam']);
        $geb  = (string)($r['geboortedatum'] ?? '');
        $start_seizoen = $seizoen['datum_werk_start'] ?? date('Y-m-d');
        $lft  = $geb !== '' ? leeftijd_op_datum($geb, $start_seizoen) : 18;

        $comp = function_exists('bezetting_comp_van') ? bezetting_comp_van($sol_id) : [];
        $eff  = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol_id, $seizoen) : [];

        $beschikbaar = [];
        foreach ($eff as $wk => $w) $beschikbaar[$wk] = (int)$w['dagen_max'];

        // Wie niets opgaf en nergens is ingepland, hoort niet in het raster (nog geen beschikbaarheid).
        if (!$beschikbaar && empty($plan[$sol_id]) && !isset($afspraken[$sol_id])) continue;

        // Functie: wat in dit scenario is gekozen, anders de oude per-week-waarde, anders het voorstel.
        $functie_bron = 'scenario';
        $fn = $functies_sc[$sol_id] ?? null;
        if ($fn === null) foreach ($plan[$sol_id] ?? [] as $pl) { if (!empty($pl['functie_sleutel'])) { $fn = $pl['functie_sleutel']; break; } }
        if ($fn === null) { $fn = function_exists('inzet_rol_voorstel') ? inzet_rol_voorstel($sol_id, $comp) : 'aspirant'; $functie_bron = 'voorstel'; }

        $weken = [];
        $p_kosten = 0.0; $p_dagen = 0; $p_onbetaald = 0;

        foreach ($weken_datums as $wk => $wd) {
            $max   = $beschikbaar[$wk] ?? 0;
            $pl    = $plan[$sol_id][$wk] ?? null;
            $dagen = $pl ? (int)$pl['dagen'] : 0;
            $opgen = $pl ? (int)$pl['opgenomen'] : 0;
            $betaald = $pl ? (int)($pl['betaald'] ?? 1) : 1;
            $weekdagen = $pl && !empty($pl['weekdagen']) ? (json_decode($pl['weekdagen'], true) ?: []) : [];
            // Leeftijd op de maandag van die week: wie in juli jarig is, krijgt vanaf dan het hogere tarief.
            $dl = loonplan_week_dagloon($geb, $fn, $wk, $weken_datums, $seizoen,
                      isset($pl['loon_override']) && $pl['loon_override'] !== null ? (float)$pl['loon_override'] : null);
            $lft_wk  = $dl['leeftijd'];
            $dagloon = $dl['dagloon'];
            $kosten = ($opgen && $betaald) ? round($dagloon * $dagen, 2) : 0.0;

            $weken[$wk] = [
                'dagen'     => $dagen,
                'opgenomen' => $opgen,
                'betaald'   => $betaald,
                'weekdagen' => $weekdagen,
                'functie'   => $fn,
                'dagloon'   => $dagloon,
                'kosten'    => $kosten,
                'max'       => $max,
                'opgave_dagen' => array_map('intval', $eff[$wk]['dagen'] ?? []),   // weekdagen 1–7 die iemand opgaf
                'leeftijd'  => $lft_wk,
            ];

            if ($opgen && $dagen > 0) {
                $p_dagen += $dagen;
                if ($betaald) $p_kosten += $kosten; else $p_onbetaald += $dagen;
                $per_week[$wk]['kosten'] = ($per_week[$wk]['kosten'] ?? 0) + $kosten;
                $per_week[$wk]['dagen']  = ($per_week[$wk]['dagen'] ?? 0) + $dagen;
                $per_week[$wk]['dagen_onbetaald'] = ($per_week[$wk]['dagen_onbetaald'] ?? 0) + ($betaald ? 0 : $dagen);
                $per_week[$wk]['mensen'] = ($per_week[$wk]['mensen'] ?? 0) + 1;

                // Deze persoon telt mee voor de dekking van die week
                $pool[$wk][] = ['sol_id'=>$sol_id, 'naam'=>$naam, 'comp'=>$comp, 'dagen'=>$dagen];

                // ── Signalen ──
                if ($max === 0) {
                    $signalen[] = ['soort'=>'niet_opgegeven', 'sol_id'=>$sol_id, 'naam'=>$naam, 'week'=>$wk,
                        'tekst'=>"$naam is week $wk ingepland, maar gaf die week niet op als beschikbaar."];
                } elseif ($dagen > $max) {
                    $signalen[] = ['soort'=>'boven_beschikbaar', 'sol_id'=>$sol_id, 'naam'=>$naam, 'week'=>$wk,
                        'tekst'=>"$naam is week $wk voor $dagen dagen ingepland, maar gaf er $max op."];
                }
                if ($dagen > 5) {
                    $signalen[] = ['soort'=>'veel_dagen', 'sol_id'=>$sol_id, 'naam'=>$naam, 'week'=>$wk,
                        'tekst'=>"$naam werkt week $wk $dagen dagen (meer dan 5)."];
                }
            }
        }

        // Rust over de weekgrens: twee opeenvolgende weken samen > 10 dagen
        // (4+6 = 10 is legitiem; 6+6 = 12 betekent te lang aaneen zonder rust)
        foreach ($weken as $wk => $w) {
            $vlg = $wk + 1;
            if (!isset($weken[$vlg])) continue;
            $a = ($w['opgenomen']           ? $w['dagen']           : 0);
            $b = ($weken[$vlg]['opgenomen'] ? $weken[$vlg]['dagen'] : 0);
            if ($a + $b > 10) {
                $signalen[] = ['soort'=>'weinig_rust', 'sol_id'=>$sol_id, 'naam'=>$naam, 'week'=>$wk,
                    'tekst'=>"$naam werkt week $wk en $vlg samen " . ($a+$b) . " dagen — weinig rust over de weekgrens."];
            }
        }

        // Klopt de vastgestelde afspraak nog met dit scenario?
        $afspraak = null;
        if (isset($afspraken[$sol_id])) {
            $afspraak = ['vastgesteld_op' => $afspraken[$sol_id]['vastgesteld_op'],
                         'wijkt_af' => function_exists('inzet_afspraak_wijkt_af') && inzet_afspraak_wijkt_af($afspraken[$sol_id], $fn, $weken)];
        }

        $mensen[$sol_id] = [
            'naam'            => $naam,
            'fase'            => (string)$r['fase'],
            'leeftijd'        => $lft,
            'functie'         => $fn,
            'functie_bron'    => $functie_bron,
            'comp'            => $comp,
            'beschikbaar'     => $beschikbaar,
            'weken'           => $weken,
            'totaal_kosten'   => round($p_kosten, 2),
            'totaal_dagen'    => $p_dagen,
            'dagen_betaald'   => $p_dagen - $p_onbetaald,
            'dagen_onbetaald' => $p_onbetaald,
            'afspraak'        => $afspraak,
        ];
        $tot_kosten += $p_kosten;
        $tot_dagen  += $p_dagen;
        $tot_onbetaald += $p_onbetaald;
    }

    ksort($per_week);
    $budget = loonplan_budget($seizoen);

    return [
        'mensen'   => $mensen,
        'per_week' => $per_week,
        'totaal'   => [
            'kosten'          => round($tot_kosten, 2),
            'dagen'           => $tot_dagen,
            'dagen_onbetaald' => $tot_onbetaald,
            'budget'          => $budget,
            'over_budget'     => $budget > 0 && $tot_kosten > $budget,
            'restant'         => $budget > 0 ? round($budget - $tot_kosten, 2) : null,
        ],
        'dekking'  => (!$een && function_exists('bezetting_dekking_van_pool')) ? bezetting_dekking_van_pool($pool) : [],
        'signalen' => $signalen,
        'tarieven' => loonplan_tarief_info($seizoen),
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

/**
 * Eén planningscel opslaan: ingepland (opgenomen), aantal dagen, betaald ja/nee en optioneel
 * welke weekdagen (1=ma … 7=zo). De functie staat per persoon (loonplan_functie_zetten).
 */
function loonplan_cel_opslaan(int $scenario_id, int $sol_id, int $week, int $jaar, int $dagen, int $opgenomen,
                              int $betaald = 1, ?array $weekdagen = null): bool {
    loonplan_auto_setup();
    // Na het TW-gesprek staat iemand op slot in alle scenario's (includes/loonplan_slot.php).
    if (function_exists('loonplan_slot_in_scenario') && loonplan_slot_in_scenario($scenario_id, $sol_id)) return false;
    $dagen = max(0, min(7, $dagen));
    $wd = $weekdagen ? array_values(array_unique(array_intersect(array_map('intval', $weekdagen), [1,2,3,4,5,6,7]))) : [];
    sort($wd);
    try {
        db()->prepare("
            INSERT INTO loon_planning (sollicitatie_id, week_nr, jaar, dagen, opgenomen, betaald, weekdagen, scenario_id)
            VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE dagen=VALUES(dagen), opgenomen=VALUES(opgenomen), betaald=VALUES(betaald),
                                    weekdagen=VALUES(weekdagen), bijgewerkt_op=NOW()
        ")->execute([$sol_id, $week, $jaar, $dagen, $opgenomen ? 1 : 0, $betaald ? 1 : 0, $wd ? json_encode($wd) : null, $scenario_id]);
        return true;
    } catch (\Exception $e) { error_log('loonplan_cel_opslaan: ' . $e->getMessage()); return false; }
}

/**
 * De opgave van de sollicitant overnemen in een scenario: per week zoveel dagen als hij of zij
 * opgaf (beschikbaarheid2_effectief, dagen_max); weken zonder opgave gaan eruit. Betaald blijft
 * zoals het per week stond (standaard betaald). Wie op slot staat (na het TW-gesprek) wordt niet
 * aangeraakt.
 *
 * @param bool $alleen_leeg  true = alleen als deze persoon in dit scenario nog niets gepland heeft
 * @return ?int  aantal ingeplande weken; null = niet gedaan (slot, of al gepland bij $alleen_leeg)
 */
function loonplan_opgave_overnemen(int $scenario_id, int $sol_id, ?array $seizoen, bool $alleen_leeg = false): ?int {
    loonplan_auto_setup();
    if (function_exists('loonplan_slot_in_scenario') && loonplan_slot_in_scenario($scenario_id, $sol_id)) return null;
    $jaar = loonplan_jaar($seizoen);
    $betaald = [];
    try {
        $q = db()->prepare("SELECT week_nr, betaald, opgenomen, dagen FROM loon_planning WHERE scenario_id=? AND sollicitatie_id=?");
        $q->execute([$scenario_id, $sol_id]);
        foreach ($q->fetchAll() as $r) {
            if ($alleen_leeg && !empty($r['opgenomen']) && (int)$r['dagen'] > 0) return null;
            $betaald[(int)$r['week_nr']] = (int)$r['betaald'];
        }
    } catch (\Exception $e) { return null; }
    $opgave = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol_id, $seizoen) : [];
    $n = 0;
    foreach (seizoen_weken_datums($seizoen) as $wk => $_) {
        $dagen = (int)($opgave[$wk]['dagen_max'] ?? 0);
        if ($dagen <= 0 && !isset($betaald[$wk])) continue;   // niets opgegeven en niets gepland: niets te doen
        loonplan_cel_opslaan($scenario_id, $sol_id, (int)$wk, $jaar, $dagen, $dagen > 0 ? 1 : 0, $betaald[$wk] ?? 1);
        if ($dagen > 0) $n++;
    }
    if (function_exists('audit_log')) audit_log('loonplan_opgave_overgenomen', 'sollicitatie', $sol_id, ['scenario' => $scenario_id, 'weken' => $n]);
    return $n;
}

/** De functie van één persoon in één scenario vastleggen (niet als die persoon op slot staat). */
function loonplan_functie_zetten(int $scenario_id, int $sol_id, string $functie): bool {
    loonplan_auto_setup();
    if (function_exists('loonplan_slot_in_scenario') && loonplan_slot_in_scenario($scenario_id, $sol_id)) return false;
    try {
        db()->prepare("INSERT INTO loon_scenario_persoon (scenario_id, sollicitatie_id, functie_sleutel) VALUES (?,?,?)
                       ON DUPLICATE KEY UPDATE functie_sleutel=VALUES(functie_sleutel)")
           ->execute([$scenario_id, $sol_id, mb_substr($functie, 0, 50)]);
        return true;
    } catch (\Exception $e) { error_log('loonplan_functie_zetten: ' . $e->getMessage()); return false; }
}

/**
 * Eén persoon in alle scenario's van het seizoen, voor het dossier (tab Inzet).
 * @return array [ ['id','naam','actief','functie','weken' => [wk => ['dagen','betaald']]] ]  actief scenario eerst
 */
function loonplan_persoon_scenarios(int $seizoen_id, int $sol_id): array {
    loonplan_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT id, naam, actief FROM loon_scenario WHERE seizoen_id=? ORDER BY actief DESC, id");
        $q->execute([$seizoen_id]);
        foreach ($q->fetchAll() as $s) $uit[(int)$s['id']] = ['id' => (int)$s['id'], 'naam' => (string)$s['naam'], 'actief' => !empty($s['actief']), 'functie' => null, 'weken' => []];
        if (!$uit) return [];
        $ph = implode(',', array_fill(0, count($uit), '?'));
        $q = db()->prepare("SELECT scenario_id, week_nr, dagen, betaald FROM loon_planning
                            WHERE sollicitatie_id=? AND opgenomen=1 AND dagen>0 AND scenario_id IN ($ph) ORDER BY week_nr");
        $q->execute(array_merge([$sol_id], array_keys($uit)));
        foreach ($q->fetchAll() as $r) {
            $s = (int)($r['scenario_id'] ?? 0);
            if (isset($uit[$s])) $uit[$s]['weken'][(int)$r['week_nr']] = ['dagen' => (int)$r['dagen'], 'betaald' => (int)$r['betaald']];
        }
        $q = db()->prepare("SELECT scenario_id, functie_sleutel FROM loon_scenario_persoon WHERE sollicitatie_id=? AND scenario_id IN ($ph)");
        $q->execute(array_merge([$sol_id], array_keys($uit)));
        foreach ($q->fetchAll() as $r) { $s = (int)($r['scenario_id'] ?? 0); if (isset($uit[$s])) $uit[$s]['functie'] = (string)$r['functie_sleutel']; }
    } catch (\Exception $e) {}
    return array_values($uit);
}

/** [sol_id => functie] zoals in dit scenario vastgelegd. */
function loonplan_functies_scenario(int $scenario_id): array {
    try {
        $q = db()->prepare("SELECT sollicitatie_id, functie_sleutel FROM loon_scenario_persoon WHERE scenario_id=?");
        $q->execute([$scenario_id]);
        $uit = [];
        foreach ($q->fetchAll() as $r) $uit[(int)$r['sollicitatie_id']] = (string)$r['functie_sleutel'];
        return $uit;
    } catch (\Exception $e) { return []; }
}

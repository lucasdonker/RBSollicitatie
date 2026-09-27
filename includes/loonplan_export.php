<?php
/**
 * Paal 13 — Export van de loonplanning voor de loonadministratie (module Loonplanning). GEEN HTML.
 *
 * Vier tabbladen: Per persoon, Weekmatrix, Opgave vs afspraak, Tarieven en budget.
 * Bron: de vastgestelde afspraken (standaard) of één scenario — dat laatste krijgt op elk
 * tabblad bovenaan "CONCEPT", zodat niemand per ongeluk een puzzelversie verloont.
 *
 * Bewust NIET in dit bestand: BSN, IBAN, adres. Dataminimalisatie (AVG art. 5 lid 1 sub c): die
 * gegevens staan versleuteld in het loondossier (module documenten), waar de penningmeester ze
 * al heeft. Een Excel reist per mail en USB-stick; wat er niet in staat, kan ook niet uitlekken.
 */

if (!function_exists('loonplan_export_personen')) {

/**
 * Eén lijst personen, ongeacht de bron:
 * [sol_id => ['naam','geboortedatum','leeftijd','functie','status','weken' => [wk => ['dagen','betaald','kosten','dagloon']],
 *             'opgave' => [wk => max], 'kosten','dagen_betaald','dagen_onbetaald']]
 */
function loonplan_export_personen(int $seizoen_id, string $bron, int $scenario_id): array {
    $seizoen = seizoen_op_id($seizoen_id);
    $weken_datums = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen) : [];
    $uit = [];

    if ($bron === 'scenario') {
        $an = loonplan_analyse($seizoen_id, $scenario_id, $seizoen);
        foreach ($an['mensen'] as $sol_id => $m) {
            if ($m['totaal_dagen'] <= 0) continue;
            $weken = [];
            foreach ($m['weken'] as $wk => $w) {
                if (!$w['opgenomen'] || $w['dagen'] <= 0) continue;
                $weken[$wk] = ['dagen' => $w['dagen'], 'betaald' => (int)$w['betaald'], 'kosten' => $w['kosten'], 'dagloon' => $w['dagloon']];
            }
            $uit[$sol_id] = ['naam' => $m['naam'], 'geboortedatum' => null, 'leeftijd' => $m['leeftijd'], 'functie' => $m['functie'],
                             'status' => $m['afspraak'] ? ($m['afspraak']['wijkt_af'] ? 'Wijkt af van afspraak' : 'Vastgesteld') : 'Concept',
                             'weken' => $weken, 'opgave' => $m['beschikbaar'], 'kosten' => $m['totaal_kosten'],
                             'dagen_betaald' => $m['dagen_betaald'], 'dagen_onbetaald' => $m['dagen_onbetaald']];
        }
    } else {
        foreach (inzet_afspraken_seizoen($seizoen_id) as $sol_id => $a) {
            $uit[$sol_id] = ['naam' => '', 'geboortedatum' => null, 'leeftijd' => null, 'functie' => $a['functie_sleutel'],
                             'status' => 'Vastgesteld ' . date('d-m-Y', strtotime($a['vastgesteld_op'])),
                             'weken' => $a['weken'], 'opgave' => [], 'kosten' => 0.0, 'dagen_betaald' => 0, 'dagen_onbetaald' => 0];
        }
    }
    if (!$uit) return [];

    // Naam en geboortedatum in één query (niet per persoon).
    try {
        $ids = array_keys($uit);
        $q = db()->prepare("SELECT s.id, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum
                            FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id
                            WHERE s.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
        $q->execute($ids);
        foreach ($q->fetchAll() as $r) {
            $id = (int)$r['id'];
            $uit[$id]['naam'] = trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam']);
            $uit[$id]['geboortedatum'] = $r['geboortedatum'] ?: null;
        }
    } catch (\Exception $e) { error_log('loonplan_export_personen: ' . $e->getMessage()); }

    foreach ($uit as $sol_id => &$p) {
        $geb = (string)($p['geboortedatum'] ?? '');
        if ($p['leeftijd'] === null) $p['leeftijd'] = $geb !== '' ? leeftijd_op_datum($geb, (string)($seizoen['datum_werk_start'] ?? date('Y-m-d'))) : null;
        if ($bron !== 'scenario') {
            // Afspraak: kosten opnieuw rekenen met dezelfde regel als de planning.
            foreach ($p['weken'] as $wk => &$w) {
                $dl = loonplan_week_dagloon($geb, $p['functie'], (int)$wk, $weken_datums, $seizoen);
                $w['dagloon'] = $dl['dagloon'];
                $w['kosten']  = $w['betaald'] ? round($dl['dagloon'] * $w['dagen'], 2) : 0.0;
                $p['kosten'] += $w['kosten'];
                if ($w['betaald']) $p['dagen_betaald'] += $w['dagen']; else $p['dagen_onbetaald'] += $w['dagen'];
            }
            unset($w);
            $eff = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol_id, $seizoen) : [];
            foreach ($eff as $wk => $e) $p['opgave'][$wk] = (int)$e['dagen_max'];
        }
    }
    unset($p);
    uasort($uit, fn($a, $b) => strcmp($a['naam'], $b['naam']));
    return $uit;
}

/** Het werkboek (voor xlsx_maak) en de bestandsnaam. */
function loonplan_export_boek(int $seizoen_id, string $bron, int $scenario_id): array {
    $seizoen  = seizoen_op_id($seizoen_id);
    $personen = loonplan_export_personen($seizoen_id, $bron, $scenario_id);
    $weken_datums = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen) : [];
    $functies = function_exists('functies_ophalen') ? functies_ophalen() : [];
    $fnaam    = array_column($functies, 'naam', 'sleutel');
    $boven    = $bron === 'scenario' ? 'CONCEPT — scenario, nog niet vastgesteld. Niet gebruiken voor de loonadministratie.' : null;
    $seizoen_naam = (string)($seizoen['naam'] ?? '');

    // 1. Per persoon
    $rijen = []; $tot = ['kosten' => 0.0, 'betaald' => 0, 'onbetaald' => 0];
    foreach ($personen as $p) {
        $gem = $p['dagen_betaald'] > 0 ? round($p['kosten'] / $p['dagen_betaald'], 2) : 0.0;
        $rijen[] = [$p['naam'], ['v' => $p['geboortedatum'], 's' => 'datum'], $p['leeftijd'], $fnaam[$p['functie']] ?? $p['functie'],
                    $p['status'], count($p['weken']), $p['dagen_betaald'], $p['dagen_onbetaald'],
                    ['v' => $gem, 's' => 'geld'], ['v' => round($p['kosten'], 2), 's' => 'geld']];
        $tot['kosten'] += $p['kosten']; $tot['betaald'] += $p['dagen_betaald']; $tot['onbetaald'] += $p['dagen_onbetaald'];
    }
    $rijen[] = [['v' => 'Totaal', 's' => 'vet'], null, null, null, null, null, ['v' => $tot['betaald'], 's' => 'vet'],
                ['v' => $tot['onbetaald'], 's' => 'vet'], null, ['v' => round($tot['kosten'], 2), 's' => 'geld']];
    $blad1 = ['naam' => 'Per persoon', 'boven' => $boven, 'vaste_kolom' => true,
              'kop' => ['Naam', 'Geboortedatum', 'Leeftijd (start)', 'Functie', 'Status', 'Weken', 'Dagen betaald', 'Dagen onbetaald', 'Gem. dagloon', 'Bruto totaal'],
              'breedte' => [28, 14, 16, 20, 24, 8, 14, 16, 14, 14], 'rijen' => $rijen];

    // 2. Weekmatrix: getal = betaalde dagen; "3 onb." = onbetaald (tekst, zodat het nooit wordt opgeteld als loon)
    $kop = ['Naam', 'Functie'];
    foreach ($weken_datums as $wk => $wd) $kop[] = 'W' . $wk . ' (' . $wd['start']->format('d-m') . ')';
    $kop[] = 'Dagen betaald'; $kop[] = 'Dagen onbetaald';
    $rijen = [];
    foreach ($personen as $p) {
        $rij = [$p['naam'], $fnaam[$p['functie']] ?? $p['functie']];
        foreach ($weken_datums as $wk => $wd) {
            $w = $p['weken'][$wk] ?? null;
            $rij[] = !$w ? null : ($w['betaald'] ? $w['dagen'] : ['v' => $w['dagen'] . ' onb.', 's' => 'let']);
        }
        $rij[] = $p['dagen_betaald']; $rij[] = $p['dagen_onbetaald'];
        $rijen[] = $rij;
    }
    $blad2 = ['naam' => 'Weekmatrix', 'boven' => $boven, 'vaste_kolom' => true, 'kop' => $kop,
              'breedte' => array_merge([28, 18], array_fill(0, count($weken_datums), 11), [13, 15]), 'rijen' => $rijen];

    // 3. Opgave vs afspraak: elke week waarin iets is opgegeven óf afgesproken
    $rijen = [];
    foreach ($personen as $p) {
        $wk_lijst = array_unique(array_merge(array_keys($p['opgave']), array_keys($p['weken'])));
        sort($wk_lijst);
        foreach ($wk_lijst as $wk) {
            $op = (int)($p['opgave'][$wk] ?? 0);
            $af = (int)($p['weken'][$wk]['dagen'] ?? 0);
            $bet = isset($p['weken'][$wk]) ? ($p['weken'][$wk]['betaald'] ? 'ja' : 'nee') : '';
            $opm = $af > 0 && $op === 0 ? 'niet opgegeven' : ($af > $op ? 'meer dan opgegeven' : ($af < $op ? ($af === 0 ? 'niet ingezet' : 'minder dan opgegeven') : ''));
            $rijen[] = [$p['naam'], $wk, ['v' => isset($weken_datums[$wk]) ? $weken_datums[$wk]['start']->format('Y-m-d') : null, 's' => 'datum'],
                        $op, $af, $bet, $af - $op, $opm !== '' ? ['v' => $opm, 's' => $af > $op ? 'let' : 'zacht'] : null];
        }
    }
    $blad3 = ['naam' => 'Opgave vs afspraak', 'boven' => $boven,
              'kop' => ['Naam', 'Week', 'Maandag', 'Opgegeven (max dagen)', ($bron === 'scenario' ? 'Ingepland' : 'Afgesproken'), 'Betaald', 'Verschil', 'Opmerking'],
              'breedte' => [28, 7, 12, 20, 13, 9, 9, 22], 'rijen' => $rijen];

    // 4. Tarieven en budget: van het jaar van dit seizoen (includes/loon_tarieven.php), dezelfde bron als de berekening.
    $t = loon_tarieven_voor_seizoen($seizoen);
    $melding = loon_tarieven_melding($t);
    $rijen = [[['v' => 'Tarieven ' . ($t['bron_jaar'] ?? $t['jaar']), 's' => 'vet']]];
    if ($melding !== '') $rijen[] = [['v' => $melding, 's' => 'let']];
    array_push($rijen,
              ['Uren per dag', $t['uren_per_dag']],
              ['Vakantietoeslag (%)', $t['vt_percentage']],
              ['Postcommandant vast per week, juni', ['v' => $t['pc_vast_juni'], 's' => 'geld']],
              ['Postcommandant vast per week, jul–sep', ['v' => $t['pc_vast_jul_sep'], 's' => 'geld']],
              [], [['v' => 'Functies', 's' => 'vet'], ['v' => 'Factor', 's' => 'vet']]);
    foreach ($functies as $f) $rijen[] = [$f['naam'], (float)$f['factor']];
    $rijen[] = [];
    $rijen[] = [['v' => 'Minimumuurloon', 's' => 'vet'], ['v' => 'Juni', 's' => 'vet'], ['v' => 'Juli–september', 's' => 'vet']];
    $leeftijden = array_unique(array_merge(array_keys($t['tarieven']['jun']), array_keys($t['tarieven']['jul_sep'])));
    sort($leeftijden);
    foreach ($leeftijden as $lft) {
        $rijen[] = [$lft . ' jaar', ['v' => $t['tarieven']['jun'][$lft] ?? null, 's' => 'geld'], ['v' => $t['tarieven']['jul_sep'][$lft] ?? null, 's' => 'geld']];
    }
    $budget = (float)$t['budget'];
    $rijen[] = [];
    $rijen[] = [['v' => 'Budget', 's' => 'vet']];
    $rijen[] = ['Bruto loonkosten (betaalde dagen)', ['v' => round($tot['kosten'], 2), 's' => 'geld']];
    if ($budget > 0) {
        $rijen[] = ['Budget ' . $t['jaar'], ['v' => $budget, 's' => 'geld']];
        $rijen[] = ['Restant', ['v' => round($budget - $tot['kosten'], 2), 's' => $budget - $tot['kosten'] < 0 ? 'let' : 'geld']];
    } else {
        $rijen[] = ['Budget ' . $t['jaar'], 'Niet ingesteld (Instellingen › Lonen & budget)'];
    }
    $rijen[] = [];
    $rijen[] = [['v' => 'Dagloon = minimumuurloon (leeftijd op de maandag van de week, periode juni of juli–september) × uren per dag × factor × (1 + vakantietoeslag). Onbetaalde dagen kosten niets.', 's' => 'zacht']];
    $blad4 = ['naam' => 'Tarieven en budget', 'boven' => $boven, 'kop' => ['Onderdeel', 'Waarde', ''], 'breedte' => [38, 16, 16], 'rijen' => $rijen];

    $bestand = 'loonplanning_' . preg_replace('/[^A-Za-z0-9]+/', '_', $seizoen_naam ?: (string)$seizoen_id)
             . ($bron === 'scenario' ? '_CONCEPT' : '_vastgesteld') . '_' . date('Y-m-d') . '.xlsx';
    return ['bladen' => [$blad1, $blad2, $blad3, $blad4], 'bestand' => $bestand, 'personen' => count($personen)];
}

}

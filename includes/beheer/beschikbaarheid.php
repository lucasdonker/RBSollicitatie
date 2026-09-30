<?php
/**
 * Paal 13 — het beschikbaarheidsraster: wie, welke weken, met filters. GEEN HTML.
 *
 * Eén bron voor het scherm (admin/beschikbaarheid.php) en de export
 * (admin/export_beschikbaarheid.php), zodat de Excel precies toont wat je op het scherm
 * gefilterd hebt.
 */

if (!function_exists('bh_besch_filter')) {

/**
 * De filters uit de URL, genormaliseerd.
 *   q · groep (''|proces|aangenomen) · type (''|nieuw|terug) · toon (''|verzoek|voorlopig)
 *   van, tot  weeknummers (leeg = hele werkperiode) · min  minimaal zoveel weken beschikbaar
 *   lv        'ja' = alleen wie ergens liever vrij is · sort (zie bh_besch_sorteringen)
 *   weergave  'week' | 'dag' (per dag: kan / liever niet / niet)
 */
function bh_besch_filter(array $get): array {
    $f = ['q' => trim((string)($get['q'] ?? '')), 'groep' => (string)($get['groep'] ?? ''),
          'type' => (string)($get['type'] ?? ''), 'toon' => (string)($get['toon'] ?? ''),
          'van' => (string)($get['van'] ?? ''), 'tot' => (string)($get['tot'] ?? ''), 'min' => (string)($get['min'] ?? ''),
          'lv' => (string)($get['lv'] ?? ''), 'sort' => (string)($get['sort'] ?? ''), 'weergave' => (string)($get['weergave'] ?? '')];
    if (!in_array($f['groep'], ['', 'proces', 'aangenomen'], true)) $f['groep'] = '';
    if (!in_array($f['type'], ['', 'nieuw', 'terug'], true)) $f['type'] = '';
    if (!in_array($f['toon'], ['', 'verzoek', 'voorlopig'], true)) $f['toon'] = '';
    foreach (['van', 'tot'] as $k) if ($f[$k] !== '' && !(ctype_digit($f[$k]) && (int)$f[$k] >= 1 && (int)$f[$k] <= 53)) $f[$k] = '';
    if ($f['min'] !== '' && !(ctype_digit($f['min']) && (int)$f['min'] <= 60)) $f['min'] = '';
    if ($f['lv'] !== 'ja') $f['lv'] = '';
    if (!isset(bh_besch_sorteringen()[$f['sort']])) $f['sort'] = '';
    if ($f['weergave'] !== 'dag') $f['weergave'] = '';
    return $f;
}

/** Sorteringen van het raster: sleutel => label. */
function bh_besch_sorteringen(): array {
    return ['' => 'Op naam', 'weken' => 'Meeste weken eerst', 'dagen' => 'Meeste dagen eerst',
            'minst' => 'Minste weken eerst', 'aangemeld' => 'Laatst aangemeld eerst'];
}

/** Staat er een filter aan (sorteren en weergave tellen niet)? */
function bh_besch_gefilterd(array $f): bool {
    foreach (['q', 'groep', 'type', 'toon', 'van', 'tot', 'min', 'lv'] as $k) if (($f[$k] ?? '') !== '') return true;
    return false;
}

/**
 * Het weekbereik toepassen. In de dagweergave zonder bereik: twee weken, vanaf deze week als die in
 * de werkperiode valt, anders vanaf het begin — een dagraster over het hele seizoen is onleesbaar.
 */
function bh_besch_weken_bereik(array $weken, array $f): array {
    $van = $f['van'] !== '' ? (int)$f['van'] : null;
    $tot = $f['tot'] !== '' ? (int)$f['tot'] : null;
    if (($f['weergave'] ?? '') === 'dag' && $van === null && $tot === null && $weken) {
        $nu = (int)date('W');
        $van = isset($weken[$nu]) ? $nu : (int)array_key_first($weken);
        $sleutels = array_keys($weken);
        $i = array_search($van, $sleutels, true);
        $tot = (int)($sleutels[min(count($sleutels) - 1, $i + 1)]);
    }
    if ($van === null && $tot === null) return $weken;
    // Volgorde van de werkperiode aanhouden (een seizoen over de jaargrens heeft weeknummers die terugspringen).
    $uit = []; $binnen = $van === null;
    foreach ($weken as $wn => $w) {
        if ($wn === $van) $binnen = true;
        if ($binnen) $uit[$wn] = $w;
        if ($tot !== null && $wn === $tot) break;
    }
    return $uit ?: $weken;
}

/**
 * De twee filtervelden van het weekbereik (voor admin/_filterbalk.php), gedeeld door Beschikbaarheid
 * en Loonplanning. $weken = wn => ['kort', …]; $f heeft 'van' en 'tot'. Staan onder "Meer filters".
 */
function bh_weekbereik_velden(array $weken, array $f): array {
    $opt = [];
    foreach ($weken as $wn => $w) $opt[(string)$wn] = 'W' . $wn . ' (' . $w['kort'] . ')';
    return [
        ['soort' => 'keuze', 'naam' => 'van', 'label' => 'Vanaf week', 'waarde' => $f['van'], 'meer' => true,
         'opties' => ['' => 'Vanaf: begin'] + array_map(fn($l) => 'Vanaf ' . $l, $opt)],
        ['soort' => 'keuze', 'naam' => 'tot', 'label' => 'Tot en met week', 'waarde' => $f['tot'], 'meer' => true,
         'opties' => ['' => 'Tot: eind'] + array_map(fn($l) => 't/m ' . $l, $opt)],
    ];
}

/**
 * "Liever vrij" per persoon per week, uit de losse dagen (beschikbaarheid_dag, soort voorkeur_vrij)
 * in één query voor iedereen: [sol_id][wn] => [Y-m-d, …]. Vaste voorkeur-vrije weekdagen staan
 * apart (bh_besch_raster 'vaste').
 */
function bh_besch_liever_vrij(array $sol_ids, array $weken): array {
    if (!$sol_ids || !$weken) return [];
    $van = reset($weken)['start'];
    $tot = (new DateTime(end($weken)['start']))->modify('+6 days')->format('Y-m-d');
    $uit = [];
    try {
        $q = db()->prepare("SELECT sollicitatie_id, datum FROM beschikbaarheid_dag
                            WHERE soort='voorkeur_vrij' AND status='goedgekeurd' AND datum BETWEEN ? AND ?
                              AND sollicitatie_id IN (" . implode(',', array_fill(0, count($sol_ids), '?')) . ")
                            ORDER BY datum");
        $q->execute(array_merge([$van, $tot], array_values($sol_ids)));
        foreach ($q->fetchAll() as $r) {
            $wn = (int)(new DateTime((string)$r['datum']))->format('W');
            if (isset($weken[$wn])) $uit[(int)$r['sollicitatie_id']][$wn][] = (string)$r['datum'];
        }
    } catch (\Throwable $e) {}
    return $uit;
}

/**
 * "Liever vrij" van één persoon in woorden: vaste weekdagen en losse datums ("wo · 12 aug, 19 aug").
 * @param mixed $vaste  JSON of array met weekdagen (1–7), zoals in beschikbaarheid_profiel
 */
function bh_besch_liever_tekst($vaste, array $per_week): string {
    $dagen = is_array($vaste) ? $vaste : (json_decode((string)$vaste, true) ?: []);
    $delen = [];
    if ($dagen) $delen[] = implode(' ', array_map(fn($d) => dag_kort((int)$d), $dagen));
    $datums = [];
    foreach ($per_week as $lijst) foreach ($lijst as $d) $datums[] = datum_kort_nl($d);
    if ($datums) $delen[] = implode(', ', $datums);
    return implode(' · ', $delen);
}

/**
 * De dagweergave: per persoon per dag 'ja' (kan), 'liever' (liever niet), 'nee' (kan niet) met de reden.
 * Uit beschikbaarheid_per_dag() — dezelfde bron als het rooster. Alleen voor het gekozen weekbereik.
 * @return array [sol_id][Y-m-d] => ['stand' => ja|liever|nee, 'reden' => string]
 */
function bh_besch_dagen(array $lijst, array $weken): array {
    if (!$weken || !function_exists('beschikbaarheid_per_dag')) return [];
    $van = reset($weken)['start'];
    $tot = (new DateTime(end($weken)['start']))->modify('+6 days')->format('Y-m-d');
    $uit = [];
    foreach ($lijst as $p) {
        foreach (beschikbaarheid_per_dag((int)$p['sol_id'], $van, $tot) as $d => $info) {
            $uit[(int)$p['sol_id']][$d] = [
                'stand' => !empty($info['beschikbaar']) ? 'ja' : (($info['hardheid'] ?? '') === 'zacht' ? 'liever' : 'nee'),
                'reden' => (string)($info['reden'] ?? ''),
            ];
        }
    }
    return $uit;
}

/** De weken van de werkperiode: [wn => ['wn','kort','eind','weektype','start' => Y-m-d]]. */
function bh_besch_weken(?array $seizoen): array {
    $werk_start = $seizoen['datum_werk_start'] ?? null;
    $werk_eind  = $seizoen['datum_werk_eind']  ?? null;
    if (!$werk_start) {
        $jaar = (int)date('Y');
        $werk_start = (new DateTime())->setISODate($jaar, 22, 1)->format('Y-m-d');
        $werk_eind  = (new DateTime())->setISODate($jaar, 35, 7)->format('Y-m-d');
    }
    $weken = [];
    $dag = new DateTime($werk_start);
    $eind_dag = new DateTime($werk_eind);
    while ($dag->format('N') != 1) $dag->modify('-1 day');
    while ($dag <= $eind_dag) {
        $wn = (int)$dag->format('W');
        $zo = (clone $dag)->modify('+6 days');
        $weken[$wn] = [
            'wn' => $wn, 'kort' => datum_kort_nl($dag), 'eind' => datum_kort_nl($zo), 'start' => $dag->format('Y-m-d'),
            'weektype' => function_exists('seizoen_weektype') ? seizoen_weektype($seizoen ?? [], $wn) : 'hoofd',
        ];
        $dag->modify('+7 days');
    }
    return $weken;
}

/**
 * Het raster: personen (gefilterd) met per week de effectieve opgave.
 *
 * @return array ['weken','lijst','eff' => [sol][wn] => [dagen,weekend,soort,welke,verzoek],
 *                'weken_count','dagen_totaal','vaste','liever' => [sol][wn] => [datums],'tot_per_week',
 *                'totaal_zonder_filter','alle_weken']
 */
function bh_besch_raster(?array $seizoen, array $f): array {
    $f    += ['van' => '', 'tot' => '', 'min' => '', 'lv' => '', 'sort' => '', 'weergave' => ''];
    $sid   = $seizoen ? (int)$seizoen['id'] : 0;
    $alle_weken = bh_besch_weken($seizoen);
    $weken = bh_besch_weken_bereik($alle_weken, $f);
    $uit = ['weken' => $weken, 'alle_weken' => $alle_weken, 'lijst' => [], 'eff' => [], 'weken_count' => [], 'dagen_totaal' => [],
            'vaste' => [], 'liever' => [], 'tot_per_week' => [], 'totaal_zonder_filter' => 0];
    foreach ($weken as $wn => $_) $uit['tot_per_week'][$wn] = ['aantal' => 0, 'weekend' => 0];
    if (!$sid) return $uit;

    if (function_exists('beschikbaarheid2_auto_setup')) beschikbaarheid2_auto_setup(); // beschikbaarheid_blok moet bestaan
    // Wie een formulier of een beschikbaarheid heeft. Zelfde uitsluiting als het dekkingsscherm.
    $q = db()->prepare("
        SELECT s.id sol_id, s.fase, s.terugkerend, s.aangemeld_op,
               p.voornaam, p.tussenvoegsel, p.achternaam,
               bsch.weken_json,
               prof.norm_dagen
        FROM sollicitaties s
        JOIN personen p ON p.id = s.persoon_id
        LEFT JOIN beschikbaarheid bsch ON bsch.sollicitatie_id = s.id
        LEFT JOIN beschikbaarheid_profiel prof ON prof.sollicitatie_id = s.id
        WHERE s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt','verwijderd_door_sollicitant')
          AND (bsch.sollicitatie_id IS NOT NULL
               OR EXISTS (SELECT 1 FROM blk_formulieren bl WHERE bl.sollicitatie_id = s.id)
               OR EXISTS (SELECT 1 FROM beschikbaarheid_blok bb WHERE bb.sollicitatie_id = s.id))
        ORDER BY p.achternaam, p.voornaam
    ");
    $q->execute([$sid]);
    $alle = $q->fetchAll();
    $uit['totaal_zonder_filter'] = count($alle);
    $voorlopig = [];
    if ($f['toon'] === 'voorlopig' && function_exists('beschikbaarheid2_voorlopigen')) {
        foreach (beschikbaarheid2_voorlopigen($sid) as $v) $voorlopig[(int)$v['sol_id']] = true;
    }

    $liever = bh_besch_liever_vrij(array_map(fn($p) => (int)$p['sol_id'], $alle), $weken);

    foreach ($alle as $p) {
        $sol_id = (int)$p['sol_id'];
        $naam = trim($p['voornaam'] . ' ' . ($p['tussenvoegsel'] ? $p['tussenvoegsel'] . ' ' : '') . $p['achternaam']);
        if ($f['q'] !== '' && !str_contains(mb_strtolower($naam), mb_strtolower($f['q']))) continue;
        if ($f['groep'] === 'aangenomen' && $p['fase'] !== 'aangenomen') continue;
        if ($f['groep'] === 'proces' && $p['fase'] === 'aangenomen') continue;
        if ($f['type'] === 'terug' && empty($p['terugkerend'])) continue;
        if ($f['type'] === 'nieuw' && !empty($p['terugkerend'])) continue;
        if ($f['toon'] === 'voorlopig' && empty($voorlopig[$sol_id])) continue;

        $pw = bh_besch_persoon_weken($sol_id, $weken, $seizoen, $p['weken_json'] ?? null);
        if ($f['toon'] === 'verzoek' && !$pw['open_verzoek']) continue;
        if ($f['min'] !== '' && count($pw['weken']) < (int)$f['min']) continue;
        if ($f['lv'] === 'ja' && empty($liever[$sol_id]) && !$pw['vaste']) continue;
        if ($pw['vaste']) $uit['vaste'][$sol_id] = $pw['vaste'];
        if (!empty($liever[$sol_id])) $uit['liever'][$sol_id] = $liever[$sol_id];
        $dagen = 0;
        foreach ($pw['weken'] as $wn => $c) {
            $uit['eff'][$sol_id][$wn] = $c;
            $uit['tot_per_week'][$wn]['aantal']++;
            if ($c['weekend']) $uit['tot_per_week'][$wn]['weekend']++;
            $dagen += (int)$c['dagen'];
        }
        $uit['weken_count'][$sol_id] = count($pw['weken']);
        $uit['dagen_totaal'][$sol_id] = $dagen;
        $p['naam'] = $naam;
        $uit['lijst'][] = $p;
    }

    // Sorteren (naam is de volgorde van de query).
    $wk = $uit['weken_count']; $dg = $uit['dagen_totaal'];
    $cmp = [
        'weken'     => fn($a, $b) => ($wk[(int)$b['sol_id']] ?? 0) <=> ($wk[(int)$a['sol_id']] ?? 0),
        'dagen'     => fn($a, $b) => ($dg[(int)$b['sol_id']] ?? 0) <=> ($dg[(int)$a['sol_id']] ?? 0),
        'minst'     => fn($a, $b) => ($wk[(int)$a['sol_id']] ?? 0) <=> ($wk[(int)$b['sol_id']] ?? 0),
        'aangemeld' => fn($a, $b) => strcmp((string)$b['aangemeld_op'], (string)$a['aangemeld_op']),
    ][$f['sort']] ?? null;
    if ($cmp) usort($uit['lijst'], fn($a, $b) => $cmp($a, $b) ?: strcasecmp($a['naam'], $b['naam']));
    return $uit;
}

/**
 * De opgave van één persoon per week, als rastercellen. Eén bron voor het raster
 * (bh_besch_raster) en het dossier (tab Beschikbaarheid & inzet), zodat beide hetzelfde tonen.
 *
 * @param array   $weken       bh_besch_weken()
 * @param ?array  $seizoen     het seizoen van déze sollicitatie (niet per se het actieve)
 * @param ?string $weken_json  de oude beschikbaarheid (van vóór beschikbaarheid2), als terugval
 * @return array ['weken' => [wn => [dagen,weekend,soort,welke,verzoek]], 'vaste' => ?json, 'open_verzoek' => bool]
 */
function bh_besch_persoon_weken(int $sol_id, array $weken, ?array $seizoen, ?string $weken_json = null): array {
    $b2 = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol_id, $seizoen) : [];
    $heeft_b2 = !empty($b2);
    $uit = ['weken' => [], 'vaste' => null, 'open_verzoek' => false];
    // Voorkeur-vrije dagen voor de naamkolom, en open verzoeken per week (anders stond de week als "vol 5").
    $open_vz = [];
    if (function_exists('beschikbaarheid2_van')) {
        $b2_data = beschikbaarheid2_van($sol_id);
        $prof = $b2_data['profiel'] ?? null;
        if ($prof && !empty($prof['vaste_dagen_uit'])) $uit['vaste'] = $prof['vaste_dagen_uit'];
        foreach ($b2_data['verzoeken'] ?? [] as $vz) {
            if (($vz['status'] ?? '') !== 'open') continue;
            for ($w = (int)$vz['van_week']; $w <= (int)$vz['tot_week']; $w++) $open_vz[$w] = $vz;
        }
    }
    $uit['open_verzoek'] = (bool)$open_vz;

    $oude_weken = [];
    if (!$heeft_b2 && $weken_json) {
        $d = json_decode($weken_json, true);
        if (is_array($d)) $oude_weken = array_map('intval', $d);
    }

    // Soort per week, expliciet: "minder dagen" en "hele week" horen niet dezelfde cel te krijgen.
    // Een week die door een correctie helemaal vervalt komt niet uit beschikbaarheid2_effectief().
    foreach ($weken as $wn => $_) {
        if ($heeft_b2) {
            if (!isset($b2[$wn])) continue;
            $w = $b2[$wn];
            $dagen   = (int)$w['dagen_max'];
            $weekend = count(array_intersect($w['dagen'], [5, 6, 7])) > 0;
            $welke   = array_map('intval', $w['dagen']);
            if (!empty($w['correctie']))                    $soort = 'correctie';
            elseif (isset($open_vz[$wn]))                   $soort = 'verzoek';
            elseif (($w['regime'] ?? 'vijf') === 'weekend') $soort = 'weekend';
            elseif ($dagen < 5)                             $soort = 'deels';
            else                                            $soort = 'vol';
        } else {
            if (!in_array($wn, $oude_weken, true)) continue;
            $dagen = 5; $weekend = true; $soort = 'vol'; $welke = [];
        }
        $uit['weken'][$wn] = ['dagen' => $dagen, 'weekend' => $weekend, 'soort' => $soort, 'welke' => $welke,
                              'verzoek' => $open_vz[$wn] ?? null];
    }
    return $uit;
}

/** Wat een weekcel zegt: [tekst, uitleg]. Eén plek voor scherm en export. */
function bh_besch_cel_tekst(?array $c): array {
    $soort = is_array($c) ? (string)$c['soort'] : 'leeg';
    $dagen = is_array($c) ? (int)$c['dagen'] : 0;
    $vz    = is_array($c) ? $c['verzoek'] : null;
    $vz_n  = $vz ? (int)$vz['dagen_aantal'] : 0;
    $vz_op = $vz && !empty($vz['dagen_pool']) ? ' op ' . implode('/', array_map('dag_kort', $vz['dagen_pool'])) : '';
    $wat = [
        'leeg'      => ['—',    'niet beschikbaar'],
        'weekend'   => ['wk',   'alleen het weekend (' . $dagen . ' dagen)'],
        'deels'     => [$dagen, $dagen . ' van de 5 dagen'],
        'correctie' => [$dagen, $dagen . ' dagen — gecorrigeerd door het bestuur'],
        'verzoek'   => [$vz_n . '?', 'vraagt max. ' . $vz_n . ' dagen' . $vz_op . ' — verzoek nog niet beoordeeld'],
        'vol'       => [$dagen, 'hele week (' . $dagen . ' dagen)'],
    ][$soort] ?? ['—', 'niet beschikbaar'];
    if (is_array($c) && $c['welke'] && count($c['welke']) < 7 && $soort !== 'verzoek') {
        $wat[1] .= ', kan op ' . implode(' ', array_map('dag_kort', $c['welke']));
    }
    return [(string)$wat[0], $wat[1], $soort];
}

} // function_exists

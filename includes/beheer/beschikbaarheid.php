<?php
/**
 * Paal 13 — het beschikbaarheidsraster: wie, welke weken, met filters. GEEN HTML.
 *
 * Eén bron voor het scherm (admin/beschikbaarheid.php) en de export
 * (admin/export_beschikbaarheid.php), zodat de Excel precies toont wat je op het scherm
 * gefilterd hebt.
 */

if (!function_exists('bh_besch_filter')) {

/** De filters uit de URL, genormaliseerd. q, groep (''|proces|aangenomen), type (''|nieuw|terug), toon (''|verzoek|voorlopig). */
function bh_besch_filter(array $get): array {
    $f = ['q' => trim((string)($get['q'] ?? '')), 'groep' => (string)($get['groep'] ?? ''),
          'type' => (string)($get['type'] ?? ''), 'toon' => (string)($get['toon'] ?? '')];
    if (!in_array($f['groep'], ['', 'proces', 'aangenomen'], true)) $f['groep'] = '';
    if (!in_array($f['type'], ['', 'nieuw', 'terug'], true)) $f['type'] = '';
    if (!in_array($f['toon'], ['', 'verzoek', 'voorlopig'], true)) $f['toon'] = '';
    return $f;
}

/** Staat er een filter aan? */
function bh_besch_gefilterd(array $f): bool {
    return $f['q'] !== '' || $f['groep'] !== '' || $f['type'] !== '' || $f['toon'] !== '';
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
 *                'weken_count','vaste','tot_per_week','totaal_zonder_filter']
 */
function bh_besch_raster(?array $seizoen, array $f): array {
    $sid   = $seizoen ? (int)$seizoen['id'] : 0;
    $weken = bh_besch_weken($seizoen);
    $uit = ['weken' => $weken, 'lijst' => [], 'eff' => [], 'weken_count' => [], 'vaste' => [], 'tot_per_week' => [], 'totaal_zonder_filter' => 0];
    foreach ($weken as $wn => $_) $uit['tot_per_week'][$wn] = ['aantal' => 0, 'weekend' => 0];
    if (!$sid) return $uit;

    if (function_exists('beschikbaarheid2_auto_setup')) beschikbaarheid2_auto_setup(); // beschikbaarheid_blok moet bestaan
    // Wie een formulier of een beschikbaarheid heeft. Zelfde uitsluiting als het dekkingsscherm.
    $q = db()->prepare("
        SELECT s.id sol_id, s.fase, s.terugkerend,
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
        if ($pw['vaste']) $uit['vaste'][$sol_id] = $pw['vaste'];
        if ($f['toon'] === 'verzoek' && !$pw['open_verzoek']) continue;
        foreach ($pw['weken'] as $wn => $c) {
            $uit['eff'][$sol_id][$wn] = $c;
            $uit['tot_per_week'][$wn]['aantal']++;
            if ($c['weekend']) $uit['tot_per_week'][$wn]['weekend']++;
        }
        $uit['weken_count'][$sol_id] = count($pw['weken']);
        $p['naam'] = $naam;
        $uit['lijst'][] = $p;
    }
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

<?php
/**
 * Paal 13 — Excel-export van de beschikbaarheid (opgave van de sollicitanten). GEEN HTML.
 *
 *   per week: per persoon per week hoeveel dagen (of "weekend", "verzoek open", "—")
 *   per dag:  per persoon per datum beschikbaar ja / nee / liever niet, plus een tabblad met
 *             de redenen waarom iemand niet kan — dezelfde bron als de rooster-API
 *             (beschikbaarheid_per_dag()).
 *
 * Volgt de filters van het scherm (bh_besch_raster). Bewust alleen naam, fase en opgave: geen
 * contactgegevens, geen geboortedatum (dataminimalisatie, AVG art. 5 lid 1 sub c).
 */

if (!function_exists('besch_export_boek')) {

/** @return array ['bladen' => [...], 'bestand' => string, 'personen' => int] */
function besch_export_boek(?array $seizoen, array $raster, array $filter, string $soort): array {
    $weken = $raster['weken'];
    $fase  = fn($f) => function_exists('fase_label') ? fase_label((string)$f) : (string)$f;
    $boven = 'Beschikbaarheid ' . ($seizoen['naam'] ?? '') . ' · opgave van de sollicitanten · ' . date('d-m-Y H:i')
           . (bh_besch_gefilterd($filter) ? ' · GEFILTERD' : '');
    $bladen = [];

    if ($soort === 'dag') {
        $eerste = reset($weken); $laatste = end($weken);
        $van = $eerste['start'] ?? date('Y-m-d');
        $tot = $laatste ? date('Y-m-d', strtotime($laatste['start'] . ' +6 days')) : $van;
        $datums = [];
        for ($d = strtotime($van); $d <= strtotime($tot); $d += 86400) $datums[] = date('Y-m-d', $d);
        $kop = ['Naam', 'Fase'];
        foreach ($datums as $dt) $kop[] = dag_kort((int)date('N', strtotime($dt))) . ' ' . date('d-m', strtotime($dt));
        $rijen = []; $redenen = [];
        foreach ($raster['lijst'] as $p) {
            $dagen = beschikbaarheid_per_dag((int)$p['sol_id'], $van, $tot);
            $rij = [$p['naam'], $fase($p['fase'])];
            foreach ($datums as $dt) {
                $info = $dagen[$dt] ?? ['beschikbaar' => false, 'hardheid' => 'hard', 'reden' => ''];
                if ($info['beschikbaar'])                   $rij[] = 'ja';
                elseif (($info['hardheid'] ?? '') === 'zacht') $rij[] = ['v' => 'liever niet', 's' => 'zacht'];
                else                                        $rij[] = ['v' => 'nee', 's' => 'let'];
                // Hele weken die niet zijn opgegeven staan al in het raster; hier alleen de echte redenen.
                if (!$info['beschikbaar'] && !in_array((string)($info['reden'] ?? ''), ['', 'week niet opgegeven', 'alleen weekend opgegeven'], true)) {
                    $redenen[] = [$p['naam'], ['v' => $dt, 's' => 'datum'], ($info['hardheid'] ?? '') === 'zacht' ? 'liever niet' : 'kan niet',
                                  (string)$info['reden'] . (!empty($info['door_bestuur']) ? ' (bestuur)' : '')];
                }
            }
            $rijen[] = $rij;
        }
        $bladen[] = ['naam' => 'Per dag', 'kop' => $kop, 'breedte' => array_merge([26, 20], array_fill(0, count($datums), 10)), 'rijen' => $rijen, 'boven' => $boven];
        $bladen[] = ['naam' => 'Redenen', 'kop' => ['Naam', 'Datum', 'Soort', 'Reden'], 'breedte' => [26, 12, 12, 50], 'rijen' => $redenen, 'boven' => $boven];
    } else {
        $kop = ['Naam', 'Fase', 'Nieuw of terug', 'Weken', 'Liever vrij'];
        foreach ($weken as $wn => $w) $kop[] = 'W' . $wn . ' (' . $w['kort'] . ')';
        $rijen = [];
        foreach ($raster['lijst'] as $p) {
            $sol = (int)$p['sol_id'];
            $rij = [$p['naam'], $fase($p['fase']), !empty($p['terugkerend']) ? 'terug' : 'nieuw', (int)($raster['weken_count'][$sol] ?? 0),
                    bh_besch_liever_tekst($raster['vaste'][$sol] ?? null, $raster['liever'][$sol] ?? [])];
            foreach ($weken as $wn => $_) {
                $c = $raster['eff'][$sol][$wn] ?? null;
                $t = bh_besch_cel_tekst($c);
                if (!$c)                                           $rij[] = ['v' => '—', 's' => 'zacht'];
                elseif (in_array($t[2], ['vol', 'deels'], true))   $rij[] = (int)$c['dagen'];
                elseif ($t[2] === 'weekend')                       $rij[] = 'weekend ' . (int)$c['dagen'];
                elseif ($t[2] === 'correctie')                     $rij[] = ['v' => (int)$c['dagen'] . ' (bestuur)', 's' => 'let'];
                else                                               $rij[] = ['v' => $t[0] . ' verzoek', 's' => 'let'];
            }
            $rijen[] = $rij;
        }
        $tot = ['Totaal beschikbaar', '', '', '', ''];
        foreach ($weken as $wn => $_) $tot[] = ['v' => (int)$raster['tot_per_week'][$wn]['aantal'], 's' => 'vet'];
        $rijen[] = $tot;
        $bladen[] = ['naam' => 'Per week', 'kop' => $kop, 'breedte' => array_merge([26, 20, 12, 8, 26], array_fill(0, count($weken), 12)), 'rijen' => $rijen, 'boven' => $boven];
    }

    $uitleg = [
        ['Wat', 'Opgave van de sollicitanten (niet de loonplanning of de afspraak).'],
        ['Per week', 'Getal = zoveel dagen die week. "weekend N" = alleen het weekend. "N (bestuur)" = gecorrigeerd door het bestuur. "N? verzoek" = vraagt om minder dagen, nog niet beoordeeld. — = niet beschikbaar.'],
        ['Per dag', 'ja = kan; nee = kan niet (reden op het tabblad Redenen); liever niet = voorkeur vrij, de planner beslist.'],
        ['Filters', bh_besch_gefilterd($filter) ? implode(', ', array_filter([
            $filter['q'] !== '' ? 'naam bevat "' . $filter['q'] . '"' : '',
            ['proces' => 'nog in de procedure', 'aangenomen' => 'aangenomen'][$filter['groep']] ?? '',
            ['nieuw' => 'alleen nieuw', 'terug' => 'alleen terugkerend'][$filter['type']] ?? '',
            ['verzoek' => 'met open verzoek', 'voorlopig' => 'nog voorlopig'][$filter['toon']] ?? '',
            ($filter['van'] ?? '') !== '' || ($filter['tot'] ?? '') !== '' ? 'weken ' . ($filter['van'] ?: 'begin') . ' t/m ' . ($filter['tot'] ?: 'eind') : '',
            ($filter['min'] ?? '') !== '' ? 'minimaal ' . $filter['min'] . ' weken' : '',
            ($filter['lv'] ?? '') === 'ja' ? 'alleen wie ergens liever vrij is' : '',
        ])) : 'geen: iedereen met een opgave'],
        ['Liever vrij', 'Vaste voorkeur-vrije weekdagen ("wo") en losse datums waarop iemand liever vrij is. Een voorkeur, geen blokkade: de planner beslist.'],
        ['Gemaakt', date('d-m-Y H:i')],
    ];
    $bladen[] = ['naam' => 'Uitleg', 'kop' => ['', ''], 'breedte' => [14, 110], 'rijen' => $uitleg];

    return ['bladen' => $bladen, 'personen' => count($raster['lijst']),
            'bestand' => 'beschikbaarheid_' . ($soort === 'dag' ? 'per_dag' : 'per_week') . '_' . date('Y-m-d') . '.xlsx'];
}

} // function_exists

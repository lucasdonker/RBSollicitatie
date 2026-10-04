<?php
/**
 * Paal 13 — het TW-gesprek: wat moet er besproken worden? GEEN HTML.
 *
 * Het gespreksscherm (admin/tw/gesprek.php) opent met "Gesprekspunten": wat je in het gesprek
 * moet aankaarten, opgehaald uit de opgave (vakantie, verzoeken, losse dagen), het weekend
 * (observaties, eerste indruk), de feedback van strandwachten, de selectiedag en het portaal.
 * Elk punt zegt naar welk tabblad het wijst. Het systeem wijst aan; het gesprek bepaalt.
 */

if (!function_exists('bh_tw_gesprekspunten')) {

/** Een tekst ingekort tot een fragment voor een punt. */
function bh_gesprek_fragment(string $t, int $max = 70): string {
    $t = trim(preg_replace('/\s+/', ' ', $t));
    return mb_strlen($t) > $max ? rtrim(mb_substr($t, 0, $max - 1)) . '…' : $t;
}

/**
 * @param array $opg  bh_opgave_overzicht()
 * @param array $ctx  tw_obs (rijen), lgf (rijen), sd_nb (bh_nb_analyse() of null), sd_besluit (?string),
 *                    evaluatie (?string), evaluatie_bericht (?string), open (string[] titels), niet_gekomen (bool)
 * @return array<int,array{soort:string,titel:string,tekst:string,tab:string}>
 *         soort: planning | aandacht | goed | info   ·   tab: opgave | hoe | open
 */
function bh_tw_gesprekspunten(array $opg, array $ctx): array {
    $p = [];
    $zet = function (string $soort, string $titel, string $tekst, string $tab) use (&$p) {
        $p[] = ['soort' => $soort, 'titel' => $titel, 'tekst' => $tekst, 'tab' => $tab];
    };

    // Opgave
    if (!empty($opg['leeg']))          $zet('planning', 'Nog geen beschikbaarheid', 'Vraag welke weken er gewerkt kan worden.', 'opgave');
    elseif (!empty($opg['voorlopig'])) $zet('planning', 'Beschikbaarheid nog voorlopig', 'Vraag of de opgave klopt en of hij definitief kan.', 'opgave');
    foreach ($opg['afwezig'] ?? [] as $a) $zet('planning', 'Vakantie', $a['tekst'] . ($a['label'] !== '' ? ' (' . $a['label'] . ')' : '') . '.', 'opgave');
    foreach ($opg['verzoeken'] ?? [] as $v) {
        if ($v['status'] === 'afgewezen') continue;
        $zet($v['status'] === 'open' ? 'planning' : 'info', 'Minder dagen',
             $v['tekst'] . ($v['status'] === 'open' ? ', nog niet beoordeeld.' : ', goedgekeurd.'), 'opgave');
    }
    $n_vrij = count($opg['vrij'] ?? []);
    if ($n_vrij) $zet('info', $n_vrij === 1 ? 'Eén losse vrije dag' : $n_vrij . ' losse vrije dagen',
                      implode(', ', array_slice(array_column($opg['vrij'], 'tekst'), 0, 4)) . ($n_vrij > 4 ? ', …' : '') . '.', 'opgave');
    if (($opg['liever'] ?? '') !== '') $zet('info', 'Liever vrij', (string)$opg['liever'] . '.', 'opgave');

    // Het weekend
    if (!empty($ctx['niet_gekomen'])) $zet('aandacht', 'Niet op het weekend', 'Er is geen gesprek geweest; leg wel een besluit vast.', 'hoe');
    if (($ctx['evaluatie'] ?? '') === 'twijfel') {
        $zet('aandacht', 'Eerste indruk: twijfel', !empty($ctx['evaluatie_bericht']) ? bh_gesprek_fragment((string)$ctx['evaluatie_bericht']) : 'Het bestuur twijfelde tijdens het weekend.', 'hoe');
    }
    $tw = ['positief' => [], 'aandacht' => []];
    foreach ($ctx['tw_obs'] ?? [] as $o) if (isset($tw[$o['soort'] ?? ''])) $tw[$o['soort']][] = (string)$o['tekst'];
    if ($tw['aandacht']) $zet('aandacht', 'Weekend: ' . count($tw['aandacht']) . '× aandacht', bh_gesprek_fragment($tw['aandacht'][0]), 'hoe');
    if (count($tw['positief']) >= 2) $zet('goed', 'Weekend: ' . count($tw['positief']) . '× positief', bh_gesprek_fragment($tw['positief'][0]), 'hoe');
    foreach ($ctx['lgf'] ?? [] as $f) {
        if (($f['soort'] ?? '') === 'aandacht') { $zet('aandacht', 'Strandwacht: aandachtspunt', bh_gesprek_fragment((string)$f['tekst']), 'hoe'); break; }
    }

    // De selectiedag
    $nb = $ctx['sd_nb'] ?? null;
    if (($ctx['sd_besluit'] ?? '') === 'twijfel') {
        $zet('aandacht', 'Selectiedag: twijfel', $nb && $nb['gem'] !== null ? 'Gemiddeld ' . cijfer_nl((float)$nb['gem']) . '.' : 'Na de selectiedag stond er twijfel.', 'hoe');
    } elseif ($nb && $nb['gem'] !== null) {
        $zet(($nb['advies']['stand'] ?? '') === 'door' ? 'goed' : 'info', 'Selectiedag ' . cijfer_nl((float)$nb['gem']),
             $nb['laagste'] && $nb['laagste']['gem'] <= 2.5 ? 'Laagst op ' . mb_strtolower($nb['laagste']['label']) . ' (' . cijfer_nl((float)$nb['laagste']['gem']) . ').' : 'Geen onderdeel onder de 2,5.', 'hoe');
    }

    // Het portaal
    $open = array_values(array_filter((array)($ctx['open'] ?? [])));
    if ($open) $zet('info', 'Nog open in het portaal', bh_gesprek_fragment(implode(', ', $open), 90) . '.', 'open');

    // Wat aandacht vraagt eerst, dan de planning, dan de rest.
    $volgorde = ['aandacht' => 0, 'planning' => 1, 'goed' => 2, 'info' => 3];
    usort($p, fn($a, $b) => $volgorde[$a['soort']] <=> $volgorde[$b['soort']]);
    return $p;
}

} // function_exists

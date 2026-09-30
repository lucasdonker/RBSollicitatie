<?php
/**
 * Paal 13 — nabespreking van de selectiedag: wie springt eruit? Geen HTML.
 *
 * Aan tafel wil je niet dertig kaarten van boven naar beneden lezen, maar eerst de mensen over
 * wie je moet praten. Daarom rekent deze laag per sollicitant de signalen uit — dicht bij de
 * drempel, een laag cijfer, veel aandachtspunten, beoordelaars die het oneens zijn, cijfers die
 * ontbreken — en kun je daarop filteren en sorteren. Een signaal is altijd een zin, nooit alleen
 * een kleur. Het systeem adviseert; het gesprek bepaalt (ontwerpfilosofie §3).
 *
 * Alles werkt op data die het scherm al in één keer ophaalt (selectie_scores_alle,
 * selectie_observaties_alle, lg_feedback_alle, selectie_besluiten): geen query per kaart.
 */

/** Signaalsoorten met hun korte naam (filter) — de enige plek voor die woorden. */
function bh_nb_signaal_soorten(): array {
    return [
        'drempel'  => 'Dicht bij de drempel',
        'laag'     => 'Laag cijfer op een onderdeel',
        'aandacht' => 'Meerdere aandachtspunten',
        'oneens'   => 'Beoordelaars oneens',
        'mist'     => 'Cijfers ontbreken',
        'lgf'      => 'Aandachtspunt van een strandwacht',
        'afwezig'  => 'Niet aanwezig',
    ];
}

/** De sorteringen van de nabespreking: sleutel => label (de filterbalk zet er een sorteer-icoon voor). */
function bh_nb_sorteringen(): array {
    return [
        'naam'      => 'Op naam',
        'rand'      => 'Meeste signalen eerst',
        'laag'      => 'Laagste cijfer eerst',
        'score'     => 'Hoogste cijfer eerst',
        'aandacht'  => 'Meeste aandacht eerst',
        'oneens'    => 'Meest oneens eerst',
        'drempel'   => 'Dichtst bij de drempel',
    ];
}

/**
 * Alles wat de nabespreking over één sollicitant wil weten.
 *
 * @param array $k         rij uit selectie_kandidaten()
 * @param array $sc        scores van deze persoon: [test][criterium] => ['allen' => [wie => cijfer], 'gem' => x]
 * @param array $obs       observaties van deze persoon
 * @param array $lgf       feedback van strandwachten over deze persoon
 * @param array $criteria  selectie_criteria()
 * @return array{gem:?float,advies:?array,pos:int,aandacht:int,spreiding:int,laagste:?array,ontbreekt:int,
 *               signalen:array<int,array{soort:string,tekst:string}>,rand:bool}
 */
function bh_nb_analyse(array $k, array $sc, array $obs, array $lgf, array $criteria, float $drempel): array {
    $gem = selectie_gemiddelde($sc);

    // Laagste gemiddelde op een onderdeel, grootste verschil tussen beoordelaars, wat er ontbreekt.
    $laagste = null; $spreiding = 0; $spreiding_op = ''; $ontbreekt = 0; $totaal = 0;
    foreach ($criteria as $ts => $test) {
        foreach ($test['criteria'] as $c) {
            $totaal++;
            $d = $sc[$ts][$c['sleutel']] ?? null;
            if (!$d || empty($d['allen'])) { $ontbreekt++; continue; }
            $g = (float)$d['gem'];
            if ($laagste === null || $g < $laagste['gem']) $laagste = ['gem' => $g, 'label' => $c['label'], 'test' => (string)($test['naam'] ?? $ts)];
            $s = max($d['allen']) - min($d['allen']);
            if ($s > $spreiding) { $spreiding = $s; $spreiding_op = $c['label']; }
        }
    }

    $pos = $aandacht = 0;
    foreach ($obs as $o) {
        if (($o['soort'] ?? '') === 'positief') $pos++;
        if (($o['soort'] ?? '') === 'aandacht') $aandacht++;
    }
    $lgf_aandacht = count(array_filter($lgf, fn($r) => ($r['soort'] ?? '') === 'aandacht'));
    $aanwezig = !empty($k['aanwezig']);

    $sig = [];
    if (!$aanwezig) $sig[] = ['soort' => 'afwezig', 'tekst' => 'was niet aanwezig'];
    if ($gem !== null && abs($gem - $drempel) <= 0.5) {
        $sig[] = ['soort' => 'drempel', 'tekst' => cijfer_nl($gem) . ' ligt dicht bij de drempel van ' . cijfer_nl($drempel)];
    }
    if ($laagste !== null && $laagste['gem'] <= 2) {
        $sig[] = ['soort' => 'laag', 'tekst' => 'laag op ' . mb_strtolower($laagste['label']) . ' (' . cijfer_nl($laagste['gem']) . ')'];
    }
    if ($aandacht >= 2) $sig[] = ['soort' => 'aandacht', 'tekst' => $aandacht . '× aandacht'];
    if ($spreiding >= 2) $sig[] = ['soort' => 'oneens', 'tekst' => 'beoordelaars oneens over ' . mb_strtolower($spreiding_op)];
    if ($aanwezig && $totaal > 0 && $ontbreekt === $totaal) {
        $sig[] = ['soort' => 'mist', 'tekst' => 'nog niet beoordeeld'];
    } elseif ($aanwezig && $ontbreekt > 0) {
        $sig[] = ['soort' => 'mist', 'tekst' => $ontbreekt . ' ' . ($ontbreekt === 1 ? 'cijfer ontbreekt' : 'cijfers ontbreken')];
    }
    if ($lgf_aandacht > 0) $sig[] = ['soort' => 'lgf', 'tekst' => 'strandwacht: aandachtspunt'];

    // Een randgeval is iemand over wie je moet praten; alleen "niet aanwezig" telt daar niet voor.
    $rand = (bool)array_filter($sig, fn($s) => $s['soort'] !== 'afwezig');

    return [
        'gem' => $gem, 'advies' => bh_advies_bij_score($gem), 'pos' => $pos, 'aandacht' => $aandacht,
        'spreiding' => $spreiding, 'laagste' => $laagste, 'ontbreekt' => $ontbreekt,
        'signalen' => $sig, 'rand' => $rand,
    ];
}

/**
 * De lijst van de nabespreking, gefilterd en gesorteerd. Elke rij krijgt 'nb' = bh_nb_analyse().
 *
 * @param array $f  q (naamdeel) · besluit (''|open|door|twijfel|niet) · advies (''|door|twijfel|niet|geen)
 *                  · groep (''|geen|id) · aanwezig (''|ja|nee) · signaal (''|rand|<soort>) · sort (zie bh_nb_sorteringen)
 */
function bh_nb_lijst(array $kandidaten, array $besluiten, array $scores, array $obs, array $lgf, array $criteria, array $f): array {
    $drempel = bh_advies_drempel();
    $q = mb_strtolower(trim((string)($f['q'] ?? '')));
    $uit = [];
    foreach ($kandidaten as $k) {
        $id = (int)$k['sol_id'];
        $k['nb'] = bh_nb_analyse($k, $scores[$id] ?? [], $obs[$id] ?? [], $lgf[$id] ?? [], $criteria, $drempel);
        $b = (string)($besluiten[$id]['besluit'] ?? '');

        if ($q !== '' && strpos(mb_strtolower((string)$k['naam']), $q) === false) continue;
        $fb = (string)($f['besluit'] ?? '');
        if ($fb === 'open' && $b !== '') continue;
        if (in_array($fb, ['door', 'twijfel', 'niet'], true) && $b !== $fb) continue;
        $fa = (string)($f['advies'] ?? '');
        if ($fa === 'geen' && $k['nb']['advies'] !== null) continue;
        if (in_array($fa, ['door', 'twijfel', 'niet'], true) && ($k['nb']['advies']['stand'] ?? '') !== $fa) continue;
        $fg = (string)($f['groep'] ?? '');
        if ($fg === 'geen' && !empty($k['groep_id'])) continue;
        if ($fg !== '' && $fg !== 'geen' && (string)($k['groep_id'] ?? '') !== $fg) continue;
        $fw = (string)($f['aanwezig'] ?? '');
        if ($fw === 'ja' && empty($k['aanwezig'])) continue;
        if ($fw === 'nee' && !empty($k['aanwezig'])) continue;
        $fs = (string)($f['signaal'] ?? '');
        if ($fs === 'rand' && !$k['nb']['rand']) continue;
        if ($fs !== '' && $fs !== 'rand' && !in_array($fs, array_column($k['nb']['signalen'], 'soort'), true)) continue;

        $uit[] = $k;
    }

    // Zonder cijfer onderaan bij sorteren op cijfer: daar valt niets aan te vergelijken.
    $naam = fn($a, $b) => strcasecmp((string)$a['naam'], (string)$b['naam']);
    $cmp = [
        'score'    => fn($a, $b) => ($b['nb']['gem'] ?? -1) <=> ($a['nb']['gem'] ?? -1),
        'laag'     => fn($a, $b) => ($a['nb']['gem'] ?? 99) <=> ($b['nb']['gem'] ?? 99),
        'aandacht' => fn($a, $b) => $b['nb']['aandacht'] <=> $a['nb']['aandacht'],
        'oneens'   => fn($a, $b) => $b['nb']['spreiding'] <=> $a['nb']['spreiding'],
        'rand'     => fn($a, $b) => count(array_filter($b['nb']['signalen'], fn($s) => $s['soort'] !== 'afwezig'))
                                <=> count(array_filter($a['nb']['signalen'], fn($s) => $s['soort'] !== 'afwezig')),
        'drempel'  => fn($a, $b) => ($a['nb']['gem'] === null ? 99 : abs($a['nb']['gem'] - $drempel))
                                <=> ($b['nb']['gem'] === null ? 99 : abs($b['nb']['gem'] - $drempel)),
    ][(string)($f['sort'] ?? 'naam')] ?? null;
    usort($uit, fn($a, $b) => ($cmp ? $cmp($a, $b) : 0) ?: $naam($a, $b));
    return $uit;
}

/**
 * Tellers bovenaan: hoeveel nog open, hoeveel randgevallen (van wie nog geen besluit heeft) en
 * per advies. Werkt op de ongefilterde lijst.
 */
function bh_nb_tellers(array $lijst, array $besluiten): array {
    $t = ['totaal' => count($lijst), 'open' => 0, 'rand_open' => 0, 'advies' => ['door' => 0, 'twijfel' => 0, 'niet' => 0, 'geen' => 0]];
    foreach ($lijst as $k) {
        $open = empty($besluiten[(int)$k['sol_id']]['besluit']);
        if ($open) $t['open']++;
        if ($open && $k['nb']['rand']) $t['rand_open']++;
        $t['advies'][$k['nb']['advies']['stand'] ?? 'geen']++;
    }
    return $t;
}

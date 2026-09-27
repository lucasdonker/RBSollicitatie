<?php
/**
 * Paal 13 — het loonplanningsraster: filters, cellen, tellingen. GEEN HTML.
 *
 * Eén bron voor het scherm (admin/loonplanning.php) en de AJAX-antwoorden, zodat een cel na
 * opslaan precies zo terugkomt als bij het laden van de pagina.
 */

if (!function_exists('bh_lp_filter')) {

/** Filters uit de URL: q, functie, groep (''|proces|aangenomen), toon (''|let|niet_ingepland|vast|open). */
function bh_lp_filter(array $get): array {
    $f = ['q' => trim((string)($get['q'] ?? '')), 'functie' => (string)($get['functie'] ?? ''),
          'groep' => (string)($get['groep'] ?? ''), 'toon' => (string)($get['toon'] ?? '')];
    if (!in_array($f['groep'], ['', 'proces', 'aangenomen'], true)) $f['groep'] = '';
    if (!in_array($f['toon'], ['', 'let', 'niet_ingepland', 'vast', 'open'], true)) $f['toon'] = '';
    return $f;
}

function bh_lp_gefilterd(array $f): bool {
    return $f['q'] !== '' || $f['functie'] !== '' || $f['groep'] !== '' || $f['toon'] !== '';
}

/** Is deze week een afwijking? Gepland in een week die niet is opgegeven, of meer dagen dan opgegeven. */
function bh_lp_cel_let(array $w): bool {
    return !empty($w['opgenomen']) && (int)$w['dagen'] > 0 && ((int)$w['max'] === 0 || (int)$w['dagen'] > (int)$w['max']);
}

/**
 * Wat een planningscel toont.
 * @return array ['tekst','sub','soort','tip']
 */
function bh_lp_cel(array $w, string $naam, int $wk): array {
    $max = (int)$w['max'];
    $sub = $max > 0 ? 'kan ' . $max : 'niet opg.';
    $opg = $max > 0 ? 'opgegeven: ' . $max . ' ' . ($max === 1 ? 'dag' : 'dagen') : 'week niet opgegeven';
    if (empty($w['opgenomen']) || (int)$w['dagen'] <= 0) {
        return ['tekst' => '–', 'sub' => $max > 0 ? $sub : '', 'soort' => $max > 0 ? 'open' : 'leeg',
                'tip' => $naam . ' — week ' . $wk . ': niet gepland, ' . $opg];
    }
    $d = (int)$w['dagen'];
    $betaald = !empty($w['betaald']);
    $let = bh_lp_cel_let($w);
    return ['tekst' => $d . ($betaald ? '' : ' onb.'), 'sub' => $sub,
            'soort' => $let ? 'let' : ($betaald ? 'gepland' : 'onbetaald'),
            'tip' => $naam . ' — week ' . $wk . ': ' . $d . ' ' . ($d === 1 ? 'dag' : 'dagen') . ' gepland'
                   . ($betaald ? '' : ' (onbetaald)') . ', ' . $opg . ($let ? ' — meer dan opgegeven' : '')];
}

/**
 * De rijen van het raster, gefilterd. Voegt per persoon 'let' (bool: een week of signaal met
 * een afwijking), 'signalen' (de teksten daarvan) en 'vast' (op slot na het TW-gesprek) toe.
 */
function bh_lp_rijen(array $analyse, array $f, array $sloten): array {
    $signaal_sol = [];
    foreach ($analyse['signalen'] as $s) $signaal_sol[(int)$s['sol_id']][] = (string)$s['tekst'];
    $uit = [];
    foreach ($analyse['mensen'] as $sol => $m) {
        $m['sol_id'] = (int)$sol;
        $m['vast'] = isset($sloten[$sol]) ? $sloten[$sol] : null;
        $m['signalen'] = $signaal_sol[$sol] ?? [];   // de redenen, voor de chip "let op" in het raster
        $m['let'] = (bool)$m['signalen'];
        if ($f['q'] !== '' && !str_contains(mb_strtolower($m['naam']), mb_strtolower($f['q']))) continue;
        if ($f['functie'] !== '' && $m['functie'] !== $f['functie']) continue;
        if ($f['groep'] === 'aangenomen' && $m['fase'] !== 'aangenomen') continue;
        if ($f['groep'] === 'proces' && $m['fase'] === 'aangenomen') continue;
        if ($f['toon'] === 'let' && !$m['let']) continue;
        if ($f['toon'] === 'niet_ingepland' && $m['totaal_dagen'] > 0) continue;
        if ($f['toon'] === 'vast' && !$m['vast']) continue;
        if ($f['toon'] === 'open' && $m['vast']) continue;
        $uit[$sol] = $m;
    }
    return $uit;
}

/**
 * Tellingen per week voor de dekkinggrafiek: wie is ingepland, en hoeveel daarvan dekken het
 * weekend (volgens hun opgave). Zelfde vorm als bij de beschikbaarheid: [wn => ['aantal','weekend']].
 */
function bh_lp_tot_per_week(array $mensen, array $weken): array {
    $uit = [];
    foreach ($weken as $wn => $_) $uit[$wn] = ['aantal' => 0, 'weekend' => 0];
    foreach ($mensen as $m) {
        foreach ($m['weken'] as $wn => $w) {
            if (!isset($uit[$wn]) || empty($w['opgenomen']) || (int)$w['dagen'] <= 0) continue;
            $uit[$wn]['aantal']++;
            // Zonder opgave die week (bewust toch gepland) tellen we het weekend mee: dat is de afspraak.
            if (!$w['opgave_dagen'] || array_intersect($w['opgave_dagen'], [5, 6, 7])) $uit[$wn]['weekend']++;
        }
    }
    return $uit;
}

} // function_exists

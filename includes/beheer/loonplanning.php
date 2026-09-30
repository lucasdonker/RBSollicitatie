<?php
/**
 * Paal 13 — het loonplanningsraster: filters, cellen, tellingen, waar het geld zit. GEEN HTML.
 *
 * Eén bron voor het scherm (admin/loonplanning.php) en de AJAX-antwoorden, zodat een cel na
 * opslaan precies zo terugkomt als bij het laden van de pagina.
 *
 * Extra personen (includes/loonplan_extra.php, bijv. een postcommandant) zitten in dezelfde
 * analyse met een negatieve sleutel en 'extra' => true. Ze hebben geen opgave: een geplande week
 * is dan nooit "meer dan opgegeven".
 */

if (!function_exists('bh_lp_filter')) {

/**
 * Filters uit de URL:
 *   q · functie · groep (''|proces|aangenomen|extra) · toon (''|let|niet_ingepland|vast|open)
 *   sort (zie bh_lp_sorteringen) · van, tot  weeknummers · hoofd '1' = alleen het hoofdseizoen
 */
function bh_lp_filter(array $get): array {
    $f = ['q' => trim((string)($get['q'] ?? '')), 'functie' => (string)($get['functie'] ?? ''),
          'groep' => (string)($get['groep'] ?? ''), 'toon' => (string)($get['toon'] ?? ''),
          'sort' => (string)($get['sort'] ?? ''), 'van' => (string)($get['van'] ?? ''), 'tot' => (string)($get['tot'] ?? ''),
          'hoofd' => (string)($get['hoofd'] ?? '')];
    if (!in_array($f['groep'], ['', 'proces', 'aangenomen', 'extra'], true)) $f['groep'] = '';
    if (!in_array($f['toon'], ['', 'let', 'niet_ingepland', 'vast', 'open'], true)) $f['toon'] = '';
    if (!isset(bh_lp_sorteringen()[$f['sort']])) $f['sort'] = '';
    foreach (['van', 'tot'] as $k) if ($f[$k] !== '' && !(ctype_digit($f[$k]) && (int)$f[$k] >= 1 && (int)$f[$k] <= 53)) $f[$k] = '';
    if ($f['hoofd'] !== '1') $f['hoofd'] = '';
    return $f;
}

/** Sorteringen van het raster: sleutel => label. */
function bh_lp_sorteringen(): array {
    return ['' => 'Op naam', 'kosten' => 'Duurste eerst', 'dagen' => 'Meeste dagen eerst',
            'dagloon' => 'Hoogste dagloon eerst', 'oudste' => 'Oudste eerst', 'jongste' => 'Jongste eerst'];
}

/** Staat er een filter aan (sorteren telt niet)? */
function bh_lp_gefilterd(array $f): bool {
    foreach (['q', 'functie', 'groep', 'toon', 'van', 'tot', 'hoofd'] as $k) if (($f[$k] ?? '') !== '') return true;
    return false;
}

/** De weken die het raster toont: het weekbereik, en eventueel alleen het hoofdseizoen. */
function bh_lp_weken(array $weken, array $f): array {
    $uit = function_exists('bh_besch_weken_bereik') ? bh_besch_weken_bereik($weken, $f) : $weken;
    if ($f['hoofd'] === '1') {
        $hoofd = array_filter($uit, fn($w) => ($w['weektype'] ?? 'hoofd') === 'hoofd');
        if ($hoofd) $uit = $hoofd;
    }
    return $uit;
}

/** Is deze week een afwijking? Gepland in een week die niet is opgegeven, of meer dagen dan opgegeven. */
function bh_lp_cel_let(array $w): bool {
    if (!empty($w['extra'])) return false;
    return !empty($w['opgenomen']) && (int)$w['dagen'] > 0 && ((int)$w['max'] === 0 || (int)$w['dagen'] > (int)$w['max']);
}

/**
 * Wat een planningscel toont.
 * @return array ['tekst','sub','soort','tip']
 */
function bh_lp_cel(array $w, string $naam, int $wk): array {
    $extra = !empty($w['extra']);
    $max = (int)$w['max'];
    $sub = $extra ? '' : ($max > 0 ? 'kan ' . $max : 'niet opg.');
    $opg = $extra ? 'extra persoon, geen opgave' : ($max > 0 ? 'opgegeven: ' . $max . ' ' . ($max === 1 ? 'dag' : 'dagen') : 'week niet opgegeven');
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

/** Het dagloon van een persoon om op te sorteren: gemiddeld over de betaalde dagen, anders de eerste week. */
function bh_lp_dagloon(array $m): float {
    if ((int)$m['dagen_betaald'] > 0) return (float)$m['totaal_kosten'] / (int)$m['dagen_betaald'];
    $eerste = reset($m['weken']);
    return $eerste ? (float)$eerste['dagloon'] : 0.0;
}

/**
 * De rijen van het raster, gefilterd en gesorteerd. Voegt per persoon 'let' (bool: een week of
 * signaal met een afwijking), 'signalen' (de teksten daarvan) en 'vast' (op slot na het TW-gesprek) toe.
 */
function bh_lp_rijen(array $analyse, array $f, array $sloten): array {
    $signaal_sol = [];
    foreach ($analyse['signalen'] as $s) $signaal_sol[(int)$s['sol_id']][] = (string)$s['tekst'];
    $uit = [];
    foreach ($analyse['mensen'] as $sol => $m) {
        $extra = !empty($m['extra']);
        $m['sol_id'] = (int)$sol;
        $m['vast'] = !$extra && isset($sloten[$sol]) ? $sloten[$sol] : null;
        $m['signalen'] = $signaal_sol[$sol] ?? [];   // de redenen, voor de chip "let op" in het raster
        $m['let'] = (bool)$m['signalen'];
        if ($f['q'] !== '' && !str_contains(mb_strtolower($m['naam']), mb_strtolower($f['q']))) continue;
        if ($f['functie'] !== '' && $m['functie'] !== $f['functie']) continue;
        if ($f['groep'] === 'aangenomen' && $m['fase'] !== 'aangenomen') continue;
        if ($f['groep'] === 'proces' && ($extra || $m['fase'] === 'aangenomen')) continue;
        if ($f['groep'] === 'extra' && !$extra) continue;
        if ($f['toon'] === 'let' && !$m['let']) continue;
        if ($f['toon'] === 'niet_ingepland' && $m['totaal_dagen'] > 0) continue;
        if ($f['toon'] === 'vast' && !$m['vast']) continue;
        if ($f['toon'] === 'open' && $m['vast']) continue;
        $uit[$sol] = $m;
    }
    $sort = $f['sort'] ?? '';
    if ($sort !== '') {
        $sleutel = [
            'kosten'  => fn($m) => -(float)$m['totaal_kosten'],
            'dagen'   => fn($m) => -(int)$m['totaal_dagen'],
            'dagloon' => fn($m) => -bh_lp_dagloon($m),
            'oudste'  => fn($m) => -(int)$m['leeftijd'],
            'jongste' => fn($m) => (int)$m['leeftijd'],
        ][$sort];
        // Gelijke waarde: de bestaande volgorde (op achternaam) aanhouden.
        $volg = array_flip(array_keys($uit));
        uksort($uit, fn($a, $b) => ($sleutel($uit[$a]) <=> $sleutel($uit[$b])) ?: ($volg[$a] <=> $volg[$b]));
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
            // Zonder opgave die week (bewust toch gepland, of een extra persoon) tellen we het weekend mee: dat is de afspraak.
            if (!$w['opgave_dagen'] || array_intersect($w['opgave_dagen'], [5, 6, 7])) $uit[$wn]['weekend']++;
        }
    }
    return $uit;
}

/**
 * Waar zit het geld? Voor het blok onder het budget: waar je kunt schuiven als het krap is.
 *
 * @param array $analyse loonplan_analyse()
 * @param array $dekking bh_dekking_weken() (met norm)
 * @return array [
 *   'personen'   => top 5 duurste: [['sol_id','naam','kosten','dagen','aandeel'(%)]],
 *   'weken'      => top 3 duurste weken: [['week','kosten','mensen']],
 *   'boven_norm' => weken met meer mensen dan de norm: [['week','teveel','kosten']] (leeg zonder norm),
 *   'norm'       => de bezettingsnorm (0 = niet ingesteld),
 *   'open'       => ['dagen','kosten']  opgegeven maar (nog) niet gepland — wat erbij kan komen,
 *   'onbetaald'  => aantal onbetaalde dagen ]
 */
function bh_lp_geld(array $analyse, array $dekking): array {
    $totaal = (float)$analyse['totaal']['kosten'];

    $personen = [];
    foreach ($analyse['mensen'] as $sol => $m) {
        if ((float)$m['totaal_kosten'] <= 0) continue;
        $personen[] = ['sol_id' => (int)$sol, 'naam' => (string)$m['naam'], 'kosten' => (float)$m['totaal_kosten'],
                       'dagen' => (int)$m['totaal_dagen'], 'aandeel' => $totaal > 0 ? (int)round($m['totaal_kosten'] / $totaal * 100) : 0];
    }
    usort($personen, fn($a, $b) => $b['kosten'] <=> $a['kosten']);

    $weken = [];
    foreach ($analyse['per_week'] as $wk => $w) {
        if ((float)($w['kosten'] ?? 0) <= 0) continue;
        $weken[] = ['week' => (int)$wk, 'kosten' => (float)$w['kosten'], 'mensen' => (int)($w['mensen'] ?? 0)];
    }
    usort($weken, fn($a, $b) => $b['kosten'] <=> $a['kosten']);

    $boven = [];
    foreach ($dekking as $wk => $d) {
        if ((int)$d['norm'] <= 0 || (int)$d['waarde'] <= (int)$d['norm']) continue;
        $boven[] = ['week' => (int)$wk, 'teveel' => (int)$d['waarde'] - (int)$d['norm'],
                    'kosten' => (float)($analyse['per_week'][$wk]['kosten'] ?? 0)];
    }

    $open = ['dagen' => 0, 'kosten' => 0.0];
    foreach ($analyse['mensen'] as $m) {
        if (!empty($m['extra'])) continue;
        foreach ($m['weken'] as $w) {
            if (!empty($w['opgenomen']) || (int)$w['max'] <= 0) continue;
            $open['dagen']  += (int)$w['max'];
            $open['kosten'] += (int)$w['max'] * (float)$w['dagloon'];
        }
    }
    $open['kosten'] = round($open['kosten'], 2);

    return ['personen' => array_slice($personen, 0, 5), 'weken' => array_slice($weken, 0, 3), 'boven_norm' => $boven,
            'norm' => $dekking ? (int)reset($dekking)['norm'] : 0,
            'open' => $open, 'onbetaald' => (int)$analyse['totaal']['dagen_onbetaald']];
}

} // function_exists

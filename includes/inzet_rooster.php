<?php
/**
 * Paal 13 — wat het rooster nodig heeft (module Loonplanning). GEEN HTML.
 *
 * Het rooster (later, via een koppeling) moet per persoon weten:
 *   1. per week: hoeveel dagen iemand werkt (de afspraak), betaald of niet, en de functie;
 *   2. per dag:  of iemand kan (ja), liever niet (voorkeur vrij) of niet (met reden).
 * Het rooster kiest zelf welke van de beschikbare dagen; wij leggen geen vaste dagen vast.
 *
 * Onbetaalde weken zijn échte werkdagen (afgesproken met de sollicitant), geen vrijwilligerswerk:
 * ze staan er gewoon in, met betaald=false. Alleen het loon verschilt.
 *
 * Bron: de vastgestelde afspraak zodra de loonplanning definitief is; daarvóór het actieve
 * scenario, gemarkeerd als concept. Per dag komt altijd uit beschikbaarheid_per_dag() (de opgave,
 * met vakanties, verzoeken en correcties van het bestuur).
 */

if (!function_exists('inzet_rooster_week')) {

/**
 * Eén week van één persoon samenvoegen. Puur (geen database), zodat het te testen is.
 *
 * @param int   $dagen       afgesproken dagen die week (0 = niet ingepland)
 * @param bool  $betaald
 * @param array $dag_info    datum => ['beschikbaar','reden','hardheid'] voor de 7 dagen van die week
 * @param int   $opgave_max  wat iemand die week maximaal opgaf (0 = niet opgegeven)
 * @return array ['dagen','betaald','beschikbaar' => [datums], 'liever_niet' => [datums],
 *                'niet' => [['datum','reden']], 'opgave_max', 'signalen' => [...]]
 */
function inzet_rooster_week(int $dagen, bool $betaald, array $dag_info, int $opgave_max): array {
    $ja = []; $liever = []; $niet = [];
    ksort($dag_info);
    foreach ($dag_info as $datum => $i) {
        if (!empty($i['beschikbaar']))                  $ja[] = $datum;
        elseif (($i['hardheid'] ?? '') === 'zacht')     $liever[] = $datum;
        else                                            $niet[] = ['datum' => $datum, 'reden' => (string)($i['reden'] ?? '')];
    }
    $signalen = [];
    if ($dagen > 0) {
        if ($opgave_max === 0)                 $signalen[] = 'niet_opgegeven';        // ingepland in een week die niet is opgegeven
        elseif ($dagen > $opgave_max)          $signalen[] = 'meer_dan_opgegeven';    // meer dagen dan de sollicitant opgaf
        if ($dagen > count($ja) + count($liever)) $signalen[] = 'te_weinig_dagen';    // het rooster kan dit niet vullen
        elseif ($dagen > count($ja))           $signalen[] = 'krap';                  // alleen met "liever niet"-dagen
    }
    return ['dagen' => $dagen, 'betaald' => $betaald, 'beschikbaar' => $ja, 'liever_niet' => $liever, 'niet' => $niet,
            'opgave_max' => $opgave_max, 'signalen' => $signalen];
}

/** Uitleg bij een signaal, voor scherm en export. */
function inzet_rooster_signaal_tekst(string $s): string {
    return [
        'niet_opgegeven'     => 'Ingepland in een week die niet is opgegeven',
        'meer_dan_opgegeven' => 'Meer dagen dan opgegeven',
        'te_weinig_dagen'    => 'Minder beschikbare dagen dan afgesproken: het rooster kan dit niet vullen',
        'krap'               => 'Alleen te vullen met dagen die iemand liever vrij heeft',
    ][$s] ?? $s;
}

/**
 * Het rooster-overzicht van een seizoen.
 *
 * @param string $bron 'auto' (afspraak als definitief, anders actief scenario), 'afspraak' of 'scenario'
 * @return array ['bron' => 'afspraak'|'concept', 'scenario_id' => ?int, 'gemaakt' => ISO-8601,
 *                'personen' => [ ['sollicitatie_id','naam','functie','weken' => [wk => inzet_rooster_week() + 'week','maandag']] ]]
 */
function inzet_rooster(int $seizoen_id, string $bron = 'auto', int $scenario_id = 0): array {
    $seizoen = seizoen_op_id($seizoen_id);
    $wd = seizoen_weken_datums($seizoen);
    if ($bron === 'auto') $bron = loonplan_definitief_op($seizoen_id) ? 'afspraak' : 'scenario';
    if ($bron === 'scenario' && !$scenario_id) $scenario_id = (int)(loonplan_actief_scenario($seizoen_id)['id'] ?? 0);

    // Per persoon: functie + [wk => [dagen, betaald]] en de opgave per week.
    $plan = [];
    if ($bron === 'afspraak') {
        foreach (inzet_afspraken_seizoen($seizoen_id) as $sol => $a) {
            $plan[$sol] = ['functie' => $a['functie_sleutel'], 'weken' => [], 'opgave' => []];
            foreach ($a['weken'] as $wk => $w) $plan[$sol]['weken'][$wk] = [(int)$w['dagen'], (bool)$w['betaald']];
        }
        foreach (array_keys($plan) as $sol) {
            foreach ((function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol, $seizoen) : []) as $wk => $w) {
                $plan[$sol]['opgave'][$wk] = (int)$w['dagen_max'];
            }
        }
    } elseif ($scenario_id) {
        foreach (loonplan_analyse($seizoen_id, $scenario_id, $seizoen)['mensen'] as $sol => $m) {
            $weken = [];
            foreach ($m['weken'] as $wk => $w) if ($w['opgenomen'] && $w['dagen'] > 0) $weken[$wk] = [(int)$w['dagen'], (bool)$w['betaald']];
            if (!$weken) continue;
            $plan[$sol] = ['functie' => $m['functie'], 'weken' => $weken, 'opgave' => $m['beschikbaar']];
        }
    }

    $namen = [];
    if ($plan) {
        try {
            $ids = array_keys($plan);
            $q = db()->prepare("SELECT s.id, p.voornaam, p.tussenvoegsel, p.achternaam FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id
                                WHERE s.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
            $q->execute($ids);
            foreach ($q->fetchAll() as $r) $namen[(int)$r['id']] = trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam']);
        } catch (\Exception $e) {}
    }

    $personen = [];
    foreach ($plan as $sol => $p) {
        $weken = [];
        foreach ($p['weken'] as $wk => $w) {
            if (!isset($wd[$wk])) continue;
            $ma = $wd[$wk]['start']->format('Y-m-d');
            $zo = date('Y-m-d', strtotime($ma . ' +6 days'));
            $dag_info = function_exists('beschikbaarheid_per_dag') ? beschikbaarheid_per_dag($sol, $ma, $zo) : [];
            $weken[$wk] = ['week' => $wk, 'maandag' => $ma] + inzet_rooster_week($w[0], $w[1], $dag_info, (int)($p['opgave'][$wk] ?? 0));
        }
        ksort($weken);
        $personen[] = ['sollicitatie_id' => $sol, 'naam' => $namen[$sol] ?? '', 'functie' => $p['functie'], 'weken' => $weken];
    }
    usort($personen, fn($a, $b) => strcmp($a['naam'], $b['naam']));
    return ['bron' => $bron === 'afspraak' ? 'afspraak' : 'concept', 'scenario_id' => $bron === 'afspraak' ? null : $scenario_id,
            'seizoen_id' => $seizoen_id, 'gemaakt' => date('c'), 'personen' => $personen];
}

} // function_exists

<?php
/**
 * Paal 13 — Meldingen: de velden waarop een bericht kan beslissen (fase, leeftijd, formulieren …).
 *
 * Eén register (voorwaarde_feiten()) voor drie plekken: de logica in sjablonen ({{als …}}), de
 * voorwaarde van een gepland bericht en "selecteren met een voorwaarde" bij Berichten. Een veld
 * toevoegen = één regel in het register + (zo nodig) een tak in voorwaarde_feit(). De keuzelijst en
 * de controle bij het opslaan volgen dan vanzelf.
 *
 * Formulieren, documenten en taken komen uit portaal_taken(), dezelfde bron als het portaal, met
 * $vangnet = false: een bericht opmaken mag nooit documenten klaarzetten of een ouder mailen.
 * "Dagen open" telt vanaf open_sinds van de taak, anders vanaf de laatste fasewissel.
 * Geen HTML hier.
 */

if (!function_exists('voorwaarde_feiten')) {

/**
 * naam => ['label', 'groep', 'soort' (getal|keuze|janee), 'waarden' => [waarde => uitleg], 'voorbeeld'].
 * 'voorbeeld' is de waarde in een voorbeeld zonder echte sollicitant (Sjablonen › Voorbeeld).
 */
function voorwaarde_feiten(): array {
    static $reg = null;
    if ($reg !== null) return $reg;
    $taak = ['nvt' => 'niet van toepassing', 'open' => 'nog niet gedaan', 'af' => 'gedaan'];
    $doc  = ['nvt' => 'niet van toepassing', 'open' => 'nog niet ondertekend', 'wacht_ouder' => 'wacht op ouder/verzorger', 'af' => 'ondertekend'];
    $kom  = ['nvt' => 'niet van toepassing', 'open' => 'nog niet laten weten', 'komt' => 'komt', 'komt_niet' => 'kan niet'];
    $fasen = (function_exists('fasen') ? fasen() : []) + ['afgehaakt' => 'Afgehaakt'];

    $reg = [
        'fase'                   => ['label' => 'Fase', 'groep' => 'Sollicitant', 'soort' => 'keuze', 'waarden' => $fasen, 'voorbeeld' => 'uitgenodigd_selectie'],
        'dagen_in_fase'          => ['label' => 'Dagen in deze fase', 'groep' => 'Sollicitant', 'soort' => 'getal', 'voorbeeld' => 6],
        'dagen_sinds_aanmelding' => ['label' => 'Dagen sinds de aanmelding', 'groep' => 'Sollicitant', 'soort' => 'getal', 'voorbeeld' => 21],
        'leeftijd'               => ['label' => 'Leeftijd in jaren', 'groep' => 'Sollicitant', 'soort' => 'getal', 'voorbeeld' => 17],
        'ouder_bekend'           => ['label' => 'E-mailadres ouder/verzorger bekend', 'groep' => 'Sollicitant', 'soort' => 'janee', 'voorbeeld' => true],
        'dagen_tot_selectiedag'  => ['label' => 'Dagen tot de selectiedag (negatief = geweest)', 'groep' => 'Data', 'soort' => 'getal', 'voorbeeld' => 10],
        'dagen_tot_trainingsweekend' => ['label' => 'Dagen tot het trainingsweekend (negatief = geweest)', 'groep' => 'Data', 'soort' => 'getal', 'voorbeeld' => 40],
        'taken_open'             => ['label' => 'Aantal open taken in het portaal', 'groep' => 'Formulieren en taken', 'soort' => 'getal', 'voorbeeld' => 2],
        'taken_open_dagen'       => ['label' => 'Dagen dat de oudste open taak openstaat', 'groep' => 'Formulieren en taken', 'soort' => 'getal', 'voorbeeld' => 9],
    ];
    $met_dagen = [
        'kennismakingsformulier'    => ['Kennismakingsformulier', $taak, 'Formulieren en taken', 'open'],
        'beschikbaarheid'           => ['Beschikbaarheid', ['nvt' => 'niet van toepassing', 'open' => 'nog niet doorgegeven', 'voorlopig' => 'voorlopig', 'af' => 'definitief'], 'Formulieren en taken', 'voorlopig'],
        'ouder_gegevens'            => ['Gegevens ouder/verzorger', $taak, 'Formulieren en taken', 'af'],
        'deelname_selectiedag'      => ['Komt op de selectiedag', $kom, 'Formulieren en taken', 'open'],
        'deelname_trainingsweekend' => ['Komt op het trainingsweekend', $kom, 'Formulieren en taken', 'nvt'],
        'ondertekenen'              => ['Alle ondertekeningen samen', $doc, 'Ondertekenen', 'open'],
        'gezondheidsverklaring'     => ['Gezondheidsverklaring', $doc, 'Ondertekenen', 'open'],
        'intakeformulier'           => ['Intakeformulier selectiedag', $doc, 'Ondertekenen', 'wacht_ouder'],
        'loonheffing'               => ['Opgaaf loonheffingen', $doc, 'Ondertekenen', 'nvt'],
        'lidmaatschap'              => ['Inschrijving lidmaatschap', $doc, 'Ondertekenen', 'nvt'],
    ];
    if (function_exists('kennismakingspakket_taken_definities')) {
        foreach (kennismakingspakket_taken_definities() as $slug => $d) $met_dagen['taak.' . $slug] = [$d['titel'], $taak, 'Takenpakket (aangenomen)', 'nvt'];
    }
    foreach ($met_dagen as $naam => $d) {
        $reg[$naam] = ['label' => $d[0], 'groep' => $d[2], 'soort' => 'keuze', 'waarden' => $d[1], 'voorbeeld' => $d[3]];
        $reg[$naam . '.dagen_open'] = ['label' => $d[0] . ': dagen open', 'groep' => $d[2], 'soort' => 'getal',
                                       'voorbeeld' => in_array($d[3], ['open', 'voorlopig', 'wacht_ouder'], true) ? 9 : null];
    }
    return $reg;
}

/**
 * Alle namen die een voorwaarde mag gebruiken, voor de controle bij het opslaan:
 * naam => toegestane waarden (keuzeveld) of null. Velden + variabelen (een variabele telt als tekst).
 */
function voorwaarde_bekend(array $extra_vars = []): array {
    $uit = [];
    foreach (voorwaarde_feiten() as $n => $f) $uit[$n] = !empty($f['waarden']) ? array_keys($f['waarden']) : null;
    if (function_exists('melding_variabelen_lijst')) foreach (melding_variabelen_lijst() as $groep) foreach ($groep as $n => $_) $uit[$n] = $uit[$n] ?? null;
    foreach ($extra_vars as $n) $uit[(string)$n] = $uit[(string)$n] ?? null;
    return $uit;
}

/**
 * De waarde-callback voor logica.php.
 *   $sol_id null → voorbeeldwaarden (voorbeeld en testbericht), 0 → geen sollicitant (velden leeg),
 *   > 0 → de echte gegevens, pas opgehaald als een voorwaarde ze nodig heeft.
 * Een naam die geen veld is, wordt opgezocht in de variabelen van het bericht ({{als gesprek_bericht}}).
 */
function voorwaarde_resolver(?int $sol_id, array $vars = []): callable {
    $memo = [];
    return function (string $naam) use ($sol_id, $vars, &$memo) {
        $feiten = voorwaarde_feiten();
        if (isset($feiten[$naam])) {
            if ($sol_id === null) return $feiten[$naam]['voorbeeld'] ?? null;
            if ($sol_id <= 0) return null;
            if (!array_key_exists('f:' . $naam, $memo)) $memo['f:' . $naam] = voorwaarde_feit($naam, $sol_id, $memo);
            return $memo['f:' . $naam];
        }
        return array_key_exists($naam, $vars) && !is_array($vars[$naam]) ? (string)$vars[$naam] : null;
    };
}

/** Voldoet deze sollicitant aan de voorwaarde? Een fout (oud, nooit gecontroleerd) telt als nee. */
function voorwaarde_klopt(string $expr, int $sol_id): bool {
    if (trim($expr) === '') return true;
    try { return voorwaarde_toets($expr, voorwaarde_resolver($sol_id)); }
    catch (\Throwable $e) { error_log('voorwaarde_klopt: ' . $e->getMessage()); return false; }
}

/** Hele dagen tussen een datum en vandaag (positief = in het verleden). */
function voorwaarde_dagen_sinds(?string $datum): ?int {
    $ts = $datum ? strtotime(substr($datum, 0, 10)) : false;
    return $ts ? (int)floor((strtotime('today') - $ts) / 86400) : null;
}

/** Eén veld uitrekenen. $memo deelt het opgehaalde dossier en de taken tussen velden van één bericht. */
function voorwaarde_feit(string $naam, int $sol_id, array &$memo) {
    if (!array_key_exists('_sol', $memo)) $memo['_sol'] = function_exists('portaal_sollicitant_laden') ? (portaal_sollicitant_laden($sol_id) ?: []) : [];
    $sol = $memo['_sol'];
    if (!$sol) return null;
    $fase_start = ($sol['gewijzigd_op'] ?? null) ?: ($sol['aangemeld_op'] ?? null);
    switch ($naam) {
        case 'fase':                   return (string)$sol['fase'];
        case 'dagen_in_fase':          return voorwaarde_dagen_sinds($fase_start);
        case 'dagen_sinds_aanmelding': return voorwaarde_dagen_sinds($sol['aangemeld_op'] ?? null);
        case 'leeftijd':               return function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($sol['geboortedatum'] ?? null) : null;
        case 'ouder_bekend':           return function_exists('portaal_ouder_gegevens') && trim((string)(portaal_ouder_gegevens($sol_id)['ouder_email'] ?? '')) !== '';
        case 'dagen_tot_selectiedag':  $d = voorwaarde_dagen_sinds($sol['datum_selectiedag'] ?? null); return $d === null ? null : -$d;
        case 'dagen_tot_trainingsweekend': $d = voorwaarde_dagen_sinds($sol['datum_tw_start'] ?? null); return $d === null ? null : -$d;
    }
    if (!array_key_exists('_taken', $memo)) $memo['_taken'] = voorwaarde_taak_standen($sol, $sol_id);
    $st = $memo['_taken'];
    if ($naam === 'taken_open' || $naam === 'taken_open_dagen') return $st[$naam] ?? 0;
    if (substr($naam, -11) === '.dagen_open') return $st[substr($naam, 0, -11)]['dagen'] ?? null;
    return $st[$naam]['stand'] ?? 'nvt';
}

/** Bij welke velden hoort deze taakrij uit portaal_taken()? */
function voorwaarde_taak_namen(array $t): array {
    $vast = ['blk' => 'kennismakingsformulier', 'besch' => 'beschikbaarheid', 'ouder' => 'ouder_gegevens',
             'deelname_selectiedag' => 'deelname_selectiedag', 'deelname_tw' => 'deelname_trainingsweekend'];
    if (isset($vast[$t['id']])) return [$vast[$t['id']]];
    if (!empty($t['doc_type'])) {
        $type = (string)$t['doc_type'];
        $groep = str_starts_with($type, 'gezondheid') ? 'gezondheidsverklaring' : (str_starts_with($type, 'intake') ? 'intakeformulier' : $type);
        return ['ondertekenen', $groep];
    }
    if (!empty($t['slug'])) return ['taak.' . $t['slug']];
    return [];
}

/**
 * Stand en dagen open per veld, uit één keer portaal_taken():
 *   [veld => ['stand' => …, 'dagen' => ?int], 'taken_open' => n, 'taken_open_dagen' => n]
 * Wat er niet in de lijst staat (andere fase, afgelopen sollicitatie) is 'nvt'.
 */
function voorwaarde_taak_standen(array $sol, int $sol_id): array {
    $taken = [];
    try {
        $blk = function_exists('blk_van') ? blk_van($sol_id) : null;
        if (function_exists('portaal_taken')) $taken = portaal_taken($sol, $blk, false);
    } catch (\Throwable $e) { error_log('voorwaarde_taak_standen: ' . $e->getMessage()); }
    $fase_start = ($sol['gewijzigd_op'] ?? null) ?: ($sol['aangemeld_op'] ?? null);
    $dagen = fn(array $t) => voorwaarde_dagen_sinds(($t['open_sinds'] ?? null) ?: $fase_start) ?? 0;

    $per = [];
    foreach ($taken as $t) foreach (voorwaarde_taak_namen($t) as $n) $per[$n][] = $t;
    $uit = [];
    foreach ($per as $n => $rijen) {
        $eigen = array_filter($rijen, fn($t) => empty($t['done']) && empty($t['wacht']));
        $wacht = array_filter($rijen, fn($t) => empty($t['done']) && !empty($t['wacht']));
        $open  = array_merge($eigen, $wacht);
        if ($eigen)     $stand = 'open';
        elseif ($wacht) $stand = str_starts_with($n, 'taak.') ? 'open' : 'wacht_ouder';   // pakkettaak wacht op het bestuur
        else            $stand = 'af';
        if (count($rijen) === 1 && $n === 'beschikbaarheid' && !empty($rijen[0]['stand'])) $stand = $rijen[0]['stand'];
        if (count($rijen) === 1 && str_starts_with($n, 'deelname_')) {
            $b = (string)($rijen[0]['inline_data']['stand'] ?? 'onbekend');
            $stand = in_array($b, ['komt', 'komt_niet'], true) ? $b : 'open';
            if ($stand !== 'open') $open = [];
        }
        $uit[$n] = ['stand' => $stand, 'dagen' => $open ? max(array_map($dagen, $open)) : null];
    }
    $eigen_open = function_exists('portaal_taken_open') ? portaal_taken_open($taken) : [];
    $uit['taken_open'] = count($eigen_open);
    $uit['taken_open_dagen'] = $eigen_open ? max(array_map($dagen, $eigen_open)) : 0;
    return $uit;
}

/**
 * Controleer de teksten van een bericht vóór het opslaan of versturen.
 * $velden: label => [tekst, is_html]. Retourneert foutzinnen met het label ervoor; leeg = in orde.
 */
function sjabloon_controleer(array $velden, array $extra_vars = []): array {
    $bekend = voorwaarde_bekend($extra_vars);
    $uit = [];
    foreach ($velden as $label => $v) {
        foreach (sjabloon_logica_fouten((string)$v[0], (bool)$v[1], $bekend) as $f) $uit[] = $label . ': ' . $f;
    }
    return $uit;
}

}

<?php
/**
 * Paal 13 — dekking per week: de rekenkant.
 *
 * Geen HTML. Dit bestand beantwoordt één vraag: zijn er die week genoeg mensen?
 * Het beschikbaarheidsscherm tekent er een grafiek van, het dekkingsscherm gebruikt
 * dezelfde norm, en later kan een cron er een waarschuwing op bouwen.
 *
 * De norm is NIET verzonnen: het is de optelsom van wat de posten volgens
 * Instellingen › Posten & bezetting nodig hebben (bezetting_sjablonen()). Verandert
 * daar een post, dan schuift deze norm mee. Daarom staat er ook nergens een getal
 * in dit bestand.
 *
 * Eén nuance die je niet moet wegpoetsen: in voor- en naseizoen worden alleen de
 * weekenden bemand. Dan telt het weekend-getal tegen de norm, niet het weektotaal.
 * Anders lijkt een week met tien doordeweekse mensen gedekt terwijl er zaterdag
 * niemand op het strand staat.
 */

if (!function_exists('bh_dekking_norm')) {

/**
 * Hoeveel mensen zijn er per dienst nodig? Som van alle rollen over alle posten.
 * Zonder bezettingsmodule vallen we terug op 0 — dan tekent het scherm geen norm
 * in plaats van een verkeerde.
 */
function bh_dekking_norm(): int {
    if (!function_exists('bezetting_sjablonen')) return 0;
    $n = 0;
    foreach (bezetting_sjablonen() as $post) {
        foreach (($post['rollen'] ?? []) as $rol) $n += max(0, (int)($rol['aantal'] ?? 0));
    }
    return $n;
}

/**
 * Rijg de weken aan de tellingen en bepaal per week de stand.
 *
 * @param array $weken         wn => ['wn','kort','eind','weektype']
 * @param array $tot_per_week  wn => ['aantal'=>int, 'weekend'=>int]
 * @param int   $norm          uit bh_dekking_norm(); 0 = geen norm bekend
 * @return array wn => aantal, weekend, weektype, norm, telt ('week'|'weekend'),
 *                     waarde (het getal dat telt), stand ('goed'|'krap'|'slecht'|'onbekend')
 */
function bh_dekking_weken(array $weken, array $tot_per_week, int $norm): array {
    $uit = [];
    foreach ($weken as $wn => $w) {
        $aantal  = (int)($tot_per_week[$wn]['aantal'] ?? 0);
        $weekend = (int)($tot_per_week[$wn]['weekend'] ?? 0);
        $type    = (string)($w['weektype'] ?? 'hoofd');
        $telt    = in_array($type, ['voor', 'na'], true) ? 'weekend' : 'week';
        $waarde  = $telt === 'weekend' ? $weekend : $aantal;

        if ($norm <= 0)          $stand = 'onbekend';
        elseif ($waarde < $norm) $stand = 'slecht';
        elseif ($waarde === $norm) $stand = 'krap';
        else                     $stand = 'goed';

        $uit[$wn] = [
            'wn'       => (int)$wn,
            'aantal'   => $aantal,
            'weekend'  => $weekend,
            'weektype' => $type,
            'norm'     => $norm,
            'telt'     => $telt,
            'waarde'   => $waarde,
            'stand'    => $stand,
        ];
    }
    return $uit;
}

/** De weeknummers die onder de norm zitten, op volgorde. */
function bh_dekking_knelpunten(array $dekking): array {
    $uit = [];
    foreach ($dekking as $d) if ($d['stand'] === 'slecht') $uit[] = (int)$d['wn'];
    return $uit;
}

/**
 * Eén zin over de knelpunten, of null als er niets aan de hand is.
 * Bewust een zin en geen getal: "2" bovenaan een scherm laat je zoeken welke twee.
 */
function bh_dekking_zin(array $knelpunten): ?string {
    if (!$knelpunten) return null;
    $lbl = array_map(fn($w) => 'W' . $w, $knelpunten);
    if (count($lbl) > 4) {
        return count($lbl) . ' weken onder de norm, vanaf ' . $lbl[0];
    }
    if (count($lbl) === 1) return $lbl[0] . ' onder de norm';
    $laatste = array_pop($lbl);
    return implode(', ', $lbl) . ' en ' . $laatste . ' onder de norm';
}

/** Hoogte van een staaf in procenten, met de norm op 80% zodat "boven de norm" zichtbaar blijft. */
function bh_dekking_hoogte(int $waarde, int $norm, int $max): int {
    if ($max <= 0) return 0;
    $ref = $norm > 0 ? max($max, (int)ceil($norm * 1.25)) : $max;
    return max(6, (int)round($waarde / $ref * 100));
}

/** De hoogste waarde in de reeks — nodig om de staven op schaal te zetten. */
function bh_dekking_max(array $dekking): int {
    $max = 0;
    foreach ($dekking as $d) $max = max($max, (int)$d['waarde']);
    return $max;
}

/* ── De postendekking (bezetting_dekking) ──────────────────────
   Dat is een ándere vraag dan hierboven: niet "zijn er genoeg mensen" maar
   "passen de mensen die er zijn op de rollen". Dezelfde drie standen, dus
   dezelfde vertaling naar het stoplicht. */

/** Vertaal de status van de bezettingsmodule naar de stand van bh_stoplicht(). */
function bh_dek_stand(string $status): string {
    return ['groen' => 'goed', 'oranje' => 'krap', 'rood' => 'slecht'][$status] ?? 'goed';
}

/** Het woord bij die stand. Kleur alleen is geen status, dus dit staat er altijd bij. */
function bh_dek_woord(string $status): string {
    return ['groen' => 'Gedekt', 'oranje' => 'Krap', 'rood' => 'Tekort'][$status] ?? 'Onbekend';
}

/**
 * Een opgeslagen icoon-waarde omzetten naar een naam uit includes/iconen.php.
 *
 * Op drie plekken bewaart het systeem een icoon in een instelling: de posten
 * (bezetting), de tests van de selectiedag, en de competentiecategorieën. Dat was
 * overal een emoji. Nieuwe waarden zijn een naam; bestaande installaties hebben nog
 * een emoji in hun instellingen staan, en die vertalen we hier in plaats van ze te
 * negeren — dan is er geen migratie nodig en verdwijnt er niets uit beeld.
 *
 * @param string $terugval  wat het wordt als er niets bruikbaars in staat
 */
function bh_icoonnaam(string $waarde, string $terugval = 'punt'): string {
    $waarde = trim($waarde);
    $vertaling = [
        '🏠' => 'huis',     '🏡' => 'huis',        '🏖️' => 'strand',   '🏖' => 'strand',
        '⛵' => 'golf',     '🚤' => 'golf',        '🌊' => 'golf',      '🏊' => 'golf',
        '🚑' => 'reddingsboei', '🛟' => 'reddingsboei', '🏃' => 'bliksem', '⚡' => 'bliksem',
        '🚗' => 'wissel',   '🚩' => 'locatie',     '📍' => 'locatie',   '⭐' => 'ster',
        '★'  => 'ster',     '🎯' => 'schild',      '🛡️' => 'schild',    '🛡' => 'schild',
        '•'  => $terugval,  ''   => $terugval,
    ];
    if (isset($vertaling[$waarde])) return $vertaling[$waarde];
    // Al een naam? Dan doorgeven: icoon() valt zelf terug op een stip bij een onbekende naam.
    return preg_match('/^[a-z_]+$/', $waarde) ? $waarde : $terugval;
}

/** De posten van de bezettingsmodule; terugval op een locatiespeld. */
function bh_post_icoon(string $waarde): string {
    return bh_icoonnaam($waarde, 'locatie');
}

/** De weken met een tekort, op volgorde. */
function bh_dek_probleemweken(array $dekking): array {
    $uit = [];
    foreach ($dekking as $wk => $d) if (($d['status'] ?? '') === 'rood') $uit[] = (int)$wk;
    return $uit;
}

/** De weken die precies genoeg zijn (geen reserve). */
function bh_dek_krappeweken(array $dekking): array {
    $uit = [];
    foreach ($dekking as $wk => $d) if (($d['status'] ?? '') === 'oranje') $uit[] = (int)$wk;
    return $uit;
}

/**
 * Eén zin over de stand van de hele reeks. Dit is de eerste regel van het scherm:
 * de vraag "kunnen we de zomer bemannen" moet beantwoord zijn vóór je gaat kijken.
 */
function bh_dek_zin(array $dekking): array {
    if (!$dekking) return ['goed', ''];
    $rood  = bh_dek_probleemweken($dekking);
    $oranje = bh_dek_krappeweken($dekking);
    if ($rood) {
        $lbl = array_map(fn($w) => 'W' . $w, $rood);
        $zin = count($lbl) > 4
            ? count($lbl) . ' weken met een tekort, vanaf ' . $lbl[0]
            : 'Tekort in ' . implode(', ', $lbl);
        return ['slecht', $zin];
    }
    if ($oranje) {
        return ['krap', count($oranje) . ' ' . (count($oranje) === 1 ? 'week is' : 'weken zijn')
                       . ' precies genoeg, zonder reserve'];
    }
    return ['goed', 'Alle ' . count($dekking) . ' weken gedekt, met reserve'];
}

} // function_exists

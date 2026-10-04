<?php
/**
 * Paal 13 — Moduleregister. GEEN HTML.
 *
 * Het systeem bestaat uit een kern (sollicitanten, portaal, berichten, beschikbaarheid-opgave,
 * ondertekenen, loondossiers, instellingen) plus losse modules die aan en uit kunnen:
 * selectiedag, trainingsweekend, loonplanning, dekking en wervingspagina's.
 *
 * Elke module beschrijft zich in één bestand, includes/modules/<sleutel>.php, dat een array
 * teruggeeft (zie modules_alle() voor de velden). Wie een module toevoegt, maakt alleen dat
 * bestand; menu, rechtenmatrix en het tabblad Modules lezen het register.
 *
 * Aan/uit werkt op één punt: mag() geeft false voor een recht dat bij een uitgezette module
 * hoort. Daardoor verdwijnt het menu-item, weigert elk scherm met vereis_recht() de toegang
 * (ook voor een admin) en valt het recht uit de matrix. Uitzetten wist niets: de tabellen en
 * gegevens blijven staan, en de AVG-opschoning blijft ze behandelen.
 */

if (!function_exists('modules_alle')) {

/**
 * Alle modules, aan of uit. Per module:
 *   sleutel, label, omschrijving
 *   rechten   [recht => ['label','groep','acties']]   (gaat mee in rechten_modules())
 *   nav       [['groep','positie','id','label','url','icoon','module'], …]  (url relatief aan /admin/)
 *   nav_alias [actieve_nav => nav-id]
 */
function modules_alle(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    foreach (glob(__DIR__ . '/modules/*.php') ?: [] as $bestand) {
        $def = require $bestand;
        if (!is_array($def) || empty($def['sleutel'])) continue;
        $cache[$def['sleutel']] = $def + ['label' => $def['sleutel'], 'omschrijving' => '', 'rechten' => [], 'nav' => [], 'nav_alias' => []];
    }
    uasort($cache, fn($a, $b) => strcmp($a['label'], $b['label']));
    return $cache;
}

/** Staat de module aan? Onbekend = kern = altijd aan. Standaard staat elke module aan. */
function module_aan(string $sleutel): bool {
    if (!isset(modules_alle()[$sleutel])) return true;
    return !function_exists('instelling') || instelling('module.' . $sleutel . '.aan', '1') !== '0';
}

/** Bij welke module hoort dit recht? null = kern. */
function module_van_recht(string $recht): ?string {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (modules_alle() as $s => $m) foreach (array_keys($m['rechten']) as $r) $map[$r] = $s;
    }
    return $map[$recht] ?? null;
}

/** Is dit recht bruikbaar (kern, of een module die aanstaat)? */
function module_recht_actief(string $recht): bool {
    $m = module_van_recht($recht);
    return $m === null || module_aan($m);
}

/** Rechten van alle modules die aanstaan (voor rechten_modules()). */
function modules_rechten(): array {
    $uit = [];
    foreach (modules_alle() as $s => $m) if (module_aan($s)) $uit += $m['rechten'];
    return $uit;
}

/** Menu-items van alle modules (aan én uit; mag() filtert de uitgezette weg). */
function modules_nav(): array {
    $uit = [];
    foreach (modules_alle() as $m) foreach ($m['nav'] as $item) $uit[] = $item;
    return $uit;
}

/** Menu-aliassen van alle modules. */
function modules_nav_alias(): array {
    $uit = [];
    foreach (modules_alle() as $m) $uit += $m['nav_alias'];
    return $uit;
}

/**
 * Voor schermen buiten vereis_recht() om (portaal, publieke pagina's): staat de module uit,
 * dan bestaat de pagina niet. 404 en stoppen.
 */
function module_vereist(string $sleutel): void {
    if (module_aan($sleutel)) return;
    http_response_code(404);
    exit('Deze pagina bestaat niet.');
}

}

<?php
/**
 * Paal 13 — Rondleidingen door het beheer (beslissingen, geen HTML).
 *
 * Een rondleiding wijst op het échte scherm aan wat ergens zit en wat je ermee doet.
 * Twee soorten, uit dezelfde stappen:
 *   - per scherm: "Rondleiding van dit scherm" in het vraagteken-paneel;
 *   - per rol: een route langs meerdere schermen (Instellingen › Handleidingen), voor
 *     wie nieuw is als bestuurslid, begeleider, penningmeester of admin.
 *
 * De inhoud (welke stap, welke tekst, welk element) staat in rondleiding_stappen.php;
 * hier zit alleen wat daarmee gebeurt: rechten filteren, adressen bouwen, en de ene
 * JSON-structuur die assets/js/rondleiding.js leest. Weergave: admin/_rondleiding.php.
 *
 * Twee regels die je niet moet versoepelen:
 *   1. Een stap waarvan het element ontbreekt, valt terug op een venster in het
 *      midden (dat doet de JS). Een rondleiding breekt dus nooit op een lege lijst
 *      of een scherm dat is omgebouwd — maar kijk na een ombouw wel even of de
 *      stappen nog op de goede plek landen (harnas: docs/ai/harnas/rondleiding.php).
 *   2. Rechten gaan vóór uitleg: een scherm of stap die deze rol niet mag zien, komt
 *      niet in de JSON. Een rondleiding mag nooit een onderdeel aanwijzen dat er voor
 *      jou niet is.
 */

if (!function_exists('rl_mag')) {

require_once __DIR__ . '/rondleiding_stappen.php';

/**
 * Mag de ingelogde beheerder dit onderdeel zien? null = iedereen. "module:actie" voor
 * schermen die meer dan lezen vragen (seizoen.php eist bewerken): dan moet de rondleiding
 * hetzelfde eisen, anders stuurt hij iemand naar een scherm dat hem wegstuurt.
 */
function rl_mag(?string $module): bool {
    if ($module === null || $module === '') return true;
    $d = explode(':', $module);
    return !function_exists('mag') || mag($d[0], $d[1] ?? 'lezen');
}

/**
 * Een voorbeeld-dossier om de dossierpagina te laten zien: de nieuwste sollicitatie
 * van het huidige seizoen. Geen dossier = het scherm valt uit de route (liever dan
 * een 404 midden in een rondleiding).
 */
function rl_voorbeeld_sollicitant(): int {
    static $id = null;
    if ($id !== null) return $id;
    $id = 0;
    try {
        $s = function_exists('actief_seizoen') ? actief_seizoen() : null;
        if ($s) {
            $st = db()->prepare('SELECT id FROM sollicitaties WHERE seizoen_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([(int)$s['id']]);
            $id = (int)($st->fetchColumn() ?: 0);
        }
    } catch (\Throwable $e) { $id = 0; }
    return $id;
}

/**
 * Eén scherm, klaar voor de JS: rechten toegepast, adres compleet. null = niet
 * zichtbaar voor deze rol of (dossier) er is geen voorbeeld.
 *
 * @param bool $hier  voor het scherm waar je nu bent: dan is er geen voorbeeld nodig
 *                    (je staat al op een dossier) en telt het adres niet.
 */
function rl_scherm(string $sleutel, bool $hier = false): ?array {
    $s = rl_schermen()[$sleutel] ?? null;
    if (!$s || !rl_mag($s['module'] ?? null)) return null;

    $url = (defined('APP_URL') ? APP_URL : '') . '/admin/' . ($s['bestand'] === 'index.php' ? '' : $s['bestand']);
    if (!$hier && ($s['vereist'] ?? '') === 'sollicitant') {
        $id = rl_voorbeeld_sollicitant();
        if (!$id) return null;
        $url .= '?id=' . $id;
    }

    $stappen = [];
    foreach ($s['stappen'] as $st) {
        if (!rl_mag($st['module'] ?? null)) continue;
        $stappen[] = [
            'doel'  => $st['doel'] ?? null,
            'titel' => (string)$st['titel'],
            'tekst' => (string)$st['tekst'],
        ];
    }
    if (!$stappen) return null;

    $hulp = (!empty($s['hulp']) && function_exists('hulp_tekst')) ? hulp_tekst($s['hulp']) : null;
    return [
        'sleutel' => $sleutel,
        'titel'   => (string)$s['titel'],
        'bestand' => (string)$s['bestand'],
        'url'     => $url,
        'lees'    => $hulp['url'] ?? null,
        'stappen' => $stappen,
    ];
}

/**
 * Een route voor deze rol: de zichtbare schermen op volgorde, of null.
 * `scherm-<sleutel>` is de rondleiding van één scherm, zodat de handleiding er via het
 * adres naartoe kan ("Laat zien op het scherm").
 */
function rl_route(string $id): ?array {
    if (str_starts_with($id, 'scherm-')) {
        $s = rl_scherm(substr($id, 7));
        return $s ? ['id' => $id, 'titel' => $s['titel'], 'afsluiting' => '', 'enkel' => true, 'schermen' => [$s]] : null;
    }
    $r = rl_routes()[$id] ?? null;
    if (!$r || !rl_mag($r['module'] ?? null)) return null;
    $schermen = [];
    foreach ($r['schermen'] as $k) {
        $s = rl_scherm($k);
        if ($s) $schermen[] = $s;
    }
    if (!$schermen) return null;
    return ['id' => $id, 'titel' => (string)$r['titel'], 'afsluiting' => (string)($r['afsluiting'] ?? ''),
            'schermen' => $schermen];
}

/** De routes die deze beheerder kan volgen, voor het overzicht. */
function rl_routes_zichtbaar(): array {
    $uit = [];
    foreach (rl_routes() as $id => $r) {
        $route = rl_route($id);
        if (!$route) continue;
        $uit[$id] = [
            'titel'    => (string)$r['titel'],
            'voor'     => (string)$r['voor'],
            'schermen' => count($route['schermen']),
            'stappen'  => array_sum(array_map(fn($s) => count($s['stappen']), $route['schermen'])),
            'url'      => rl_start_url($id),
        ];
    }
    return $uit;
}

/** Waar een route begint: het eerste scherm, met de route in het adres. */
function rl_start_url(string $id): string {
    $route = rl_route($id);
    if (!$route) return (defined('APP_URL') ? APP_URL : '') . '/admin/handleiding.php';
    $url = $route['schermen'][0]['url'];
    return $url . (str_contains($url, '?') ? '&' : '?') . 'rondleiding=' . rawurlencode($id) . '&rl=0';
}

/**
 * De schermen die bij een hoofdstuk van een handleiding horen, met het adres waarop hun
 * rondleiding start. De koppeling loopt via includes/hulp.php: een scherm hoort bij het
 * hoofdstuk waar zijn vraagteken naar verwijst. Zo staat die koppeling op één plek.
 *
 * @param string[] $ankers  het anker van het hoofdstuk plus die van zijn subkoppen
 *                          (het vraagteken van Dekking wijst naar een subkop)
 * @return array<int, array{titel:string, url:string}>
 */
function rl_schermen_bij_hoofdstuk(string $handleiding, array $ankers): array {
    if (!function_exists('hulp_registry')) return [];
    $reg = hulp_registry();
    $uit = [];
    foreach (rl_schermen() as $k => $s) {
        if (!empty($s['alleen_route']) || empty($s['hulp'])) continue;
        $h = $reg[$s['hulp']] ?? null;
        if (!$h || $h[2] !== $handleiding || !in_array($h[3], $ankers, true)) continue;
        $sc = rl_scherm($k);
        if (!$sc) continue;
        $uit[] = ['titel' => $sc['titel'], 'url' => $sc['url'] . (str_contains($sc['url'], '?') ? '&' : '?')
                  . 'rondleiding=' . rawurlencode('scherm-' . $k) . '&rl=0'];
    }
    return $uit;
}

/** Welke route past bij deze rol? Voor het welkomstblok op Vandaag. */
function rl_aanbevolen(string $rol): ?string {
    $keuze = ['begeleider' => 'begeleider', 'penningmeester' => 'penningmeester', 'planner' => 'planner',
              'bestuur' => 'bestuur', 'admin' => 'bestuur'][$rol] ?? 'welkom';
    if (rl_route($keuze)) return $keuze;
    return rl_route('welkom') ? 'welkom' : null;
}

/**
 * Het scherm dat bij dit bestand hoort (voor "Rondleiding van dit scherm").
 * Schermen met `alleen_route` (de algemene uitleg van menu en topbalk) tellen niet:
 * op elk scherm "rondleiding van dit scherm" krijgen en dan over het menu horen, klopt niet.
 */
function rl_scherm_voor_bestand(string $bestand): ?array {
    foreach (rl_schermen() as $k => $s) {
        if ($s['bestand'] !== $bestand || !empty($s['alleen_route'])) continue;
        $sc = rl_scherm($k, true);
        if ($sc) return $sc;
    }
    return null;
}

/**
 * Alles wat de JS op deze pagina nodig heeft, of null als er niets te tonen valt.
 *
 * @param string $bestand  basename van het script (sollicitanten.php)
 * @param string $route    ?rondleiding= uit het adres ('' = geen route bezig)
 * @param string $rol      rol van de beheerder, voor het welkomstblok
 */
function rl_pagina_data(string $bestand, string $route, string $rol): ?array {
    $data = [
        'scherm'     => rl_scherm_voor_bestand($bestand),
        'route'      => $route !== '' ? rl_route($route) : null,
        'overzicht'  => (defined('APP_URL') ? APP_URL : '') . '/admin/handleiding.php',
        'welkom'     => null,
    ];
    if ($bestand === 'index.php') {
        $a = rl_aanbevolen($rol);
        if ($a) $data['welkom'] = ['route' => $a, 'titel' => rl_routes()[$a]['titel'], 'url' => rl_start_url($a)];
    }
    if (!$data['scherm'] && !$data['route'] && !$data['welkom']) return null;
    return $data;
}

}

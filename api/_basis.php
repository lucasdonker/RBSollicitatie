<?php
/**
 * Paal 13 — Publieke API basis.
 *
 * Gedeelde laag voor alle publieke API-endpoints (gebruikt door de WordPress-plugin).
 * Regelt: JSON-uitvoer, CORS, en beveiliging met een geheime API-sleutel.
 *
 * De sleutel staat in de instellingen (api.sleutel) en moet meegestuurd worden in de
 * header 'X-Paal13-Key'. Zo kan alleen de eigen website posten, niemand anders.
 */

require_once __DIR__ . '/../includes/functions.php';

/** Stuur een JSON-respons en stop. */
function api_json($data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    // CORS: alleen de eigen hoofdsite mag deze API vanuit de browser aanroepen.
    $toegestaan = api_toegestane_origins();
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin && in_array($origin, $toegestaan, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Paal13-Key, X-Paal13-Bezoeker');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/** Welke origins mogen de API vanuit de browser aanroepen (CORS). Eén bron: site_toegestane_origins(). */
function api_toegestane_origins(): array {
    return site_toegestane_origins();
}

/** Controleer de API-sleutel. Stopt met 401 als hij ontbreekt of niet klopt. */
function api_vereis_sleutel(): void {
    $verwacht = function_exists('instelling') ? (string)instelling('api.sleutel', '') : '';
    if ($verwacht === '') {
        api_json(['ok' => false, 'fout' => 'API is nog niet geconfigureerd (geen sleutel ingesteld).'], 503);
    }
    // Alleen uit de header. Een sleutel in de querystring (?key=) belandt in access-logs,
    // Referer-headers en browsergeschiedenis; sinds 2.94.0 wordt die niet meer geaccepteerd.
    $gegeven = $_SERVER['HTTP_X_PAAL13_KEY'] ?? '';
    if (!hash_equals($verwacht, (string)$gegeven)) {
        api_json(['ok' => false, 'fout' => 'Ongeldige of ontbrekende API-sleutel.'], 401);
    }
}

/** Lees de JSON- of form-body van een POST-verzoek. */
function api_body(): array {
    $ruw = file_get_contents('php://input');
    if ($ruw) {
        $j = json_decode($ruw, true);
        if (is_array($j)) return $j;
    }
    return $_POST ?: [];
}

/** Handel een preflight OPTIONS-verzoek af. */
function api_preflight(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        api_json(['ok' => true], 204);
    }
}

// De snelheidsbegrenzing staat in includes/ratelimit.php (rate_limit), zodat de API en het
// aanmeldformulier op landingspagina's dezelfde limiter delen.

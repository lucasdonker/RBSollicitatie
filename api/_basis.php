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
    header('Access-Control-Allow-Headers: Content-Type, X-Paal13-Key');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/** Welke origins mogen de API vanuit de browser aanroepen (CORS). */
function api_toegestane_origins(): array {
    $ruw = function_exists('instelling') ? (string)instelling('api.origins', '') : '';
    $lijst = array_filter(array_map('trim', explode("\n", str_replace(',', "\n", $ruw))));
    // Standaard de hoofdsite toestaan
    $lijst[] = 'https://paal13.com';
    $lijst[] = 'https://www.paal13.com';
    return array_values(array_unique($lijst));
}

/** Controleer de API-sleutel. Stopt met 401 als hij ontbreekt of niet klopt. */
function api_vereis_sleutel(): void {
    $verwacht = function_exists('instelling') ? (string)instelling('api.sleutel', '') : '';
    if ($verwacht === '') {
        api_json(['ok' => false, 'fout' => 'API is nog niet geconfigureerd (geen sleutel ingesteld).'], 503);
    }
    // Sleutel uit header of (voor GET) uit query
    $gegeven = $_SERVER['HTTP_X_PAAL13_KEY'] ?? ($_GET['key'] ?? '');
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

/**
 * Eenvoudige snelheidsbegrenzing: max $max acties per $venster seconden, per sleutel.
 * Gebruikt een lichte tabel; faalt "open" (laat door) als er iets misgaat, zodat een
 * storing in de limiter nooit legitieme aanmeldingen blokkeert.
 */
function api_rate_limit(string $actie, string $identiteit, int $max, int $venster): bool {
    try {
        $db = db();
        $db->exec("CREATE TABLE IF NOT EXISTS `api_rate` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `sleutel` VARCHAR(160) NOT NULL,
            `moment` INT NOT NULL,
            KEY `idx_sleutel` (`sleutel`, `moment`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $sleutel = substr($actie . ':' . $identiteit, 0, 160);
        $nu = time();
        $grens = $nu - $venster;
        // Oude regels opruimen (klein)
        $db->prepare("DELETE FROM api_rate WHERE moment < ?")->execute([$grens]);
        // Tellen binnen het venster
        $q = $db->prepare("SELECT COUNT(*) FROM api_rate WHERE sleutel=? AND moment >= ?");
        $q->execute([$sleutel, $grens]);
        if ((int)$q->fetchColumn() >= $max) return false;
        $db->prepare("INSERT INTO api_rate (sleutel, moment) VALUES (?,?)")->execute([$sleutel, $nu]);
        return true;
    } catch (\Throwable $e) {
        error_log('api_rate_limit: ' . $e->getMessage());
        return true;  // faalt open
    }
}

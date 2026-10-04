<?php
/**
 * De snelzoeker (Ctrl+K). Geeft mensen en schermen terug bij een zoekterm.
 *
 * GET, want zoeken verandert niets — daarom ook geen csrf_check(); die hoort bij
 * schrijfacties. De begrenzing zit elders: je moet ingelogd zijn, bh_zoek() filtert op
 * je rechten, en de rate limit houdt het aftasten van namen tegen.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
vereist_login();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$term = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($term) < 2) { echo json_encode(['ok' => true, 'mensen' => [], 'plekken' => []]); exit; }
if (mb_strlen($term) > 80) $term = mb_substr($term, 0, 80);

// Zoeken op naam is het aftasten van een personenbestand. Een beheerder mag dat, maar
// niet ongelimiteerd en niet geautomatiseerd: 60 zoekopdrachten per minuut is ruim voor
// typen en te weinig om een lijst uit te lezen.
$wie = huidige_beheerder();
if (function_exists('rate_limit') && !rate_limit('adminzoek', (string)(int)($wie['id'] ?? 0), 60, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'fout' => 'Te veel zoekopdrachten achter elkaar. Wacht even.']);
    exit;
}

try {
    echo json_encode(['ok' => true] + bh_zoek($term), JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('api_zoek: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'fout' => 'Zoeken lukte niet.']);
}

<?php
/**
 * Live bijwerken (includes/live.php, assets/js/live.js): de tellers van de scopes waar een open
 * scherm naar kijkt. GET ?s=sd:12,sol:345 → {ok, v: {scope: {v, door_id, door}}}.
 *
 * Wordt elke 10 seconden per open tabblad gevraagd, dus zo licht mogelijk: na de inlogcontrole
 * meteen de sessie vrijgeven (anders wacht elk ander verzoek van dezelfde gebruiker op deze), en
 * één query op de primaire sleutel. Alleen lezen. Een scope geeft alleen een teller en de naam van
 * wie iets veranderde, en alleen voor wie dat onderdeel mag lezen.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// Telt niet als activiteit: anders logt een open tabblad nooit uit na inactiviteit (NIS2).
if (!is_ingelogd(false)) { http_response_code(401); echo json_encode(['ok' => false, 'uitgelogd' => true]); exit; }
if (function_exists('session_write_close')) session_write_close();

$mag_lezen = function (string $m): bool { return function_exists('mag') && mag($m, 'lezen'); };
$recht = [
    'sd'   => fn() => $mag_lezen('selectiedag'),
    'tw'   => fn() => $mag_lezen('trainingsweekend'),
    'loon' => fn() => $mag_lezen('loonplanning') || $mag_lezen('beschikbaarheid'),
    'sol'  => fn() => $mag_lezen('sollicitanten') || $mag_lezen('selectiedag') || $mag_lezen('trainingsweekend'),
    'sz'   => fn() => true,   // alleen een teller; Vandaag ziet iedereen die inlogt
];

$gevraagd = array_slice(array_filter(array_map('trim', explode(',', (string)($_GET['s'] ?? '')))), 0, 10);
$scopes = [];
foreach ($gevraagd as $s) {
    if (!live_scope_geldig($s)) continue;
    $soort = strtok($s, ':');
    if (isset($recht[$soort]) && $recht[$soort]()) $scopes[] = $s;
}

$uit = [];
foreach (live_versies($scopes) as $s => $r) {
    $uit[$s] = ['v' => $r['v'], 'door_id' => $r['door_id'], 'door' => $r['door']];
}
echo json_encode(['ok' => true, 'v' => $uit]);

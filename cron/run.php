<?php
/**
 * Paal 13 — Cron runner
 * Aangeroepen door de Plesk-cron (elke 5 min). Bij voorkeur met het token in een header,
 * zodat het niet in de access-logs van de webserver belandt:
 *   curl -s -H "X-Cron-Token: ..." "https://sollicitatie.paal13.com/cron/run.php"
 * De oude vorm met ?token=... werkt nog voor bestaande cronjobs, maar komt wel in de logs.
 * Token staat in Instellingen → Bewaren & opschonen → Cronjob.
 *
 * Alle taken zitten in includes/cron_taken.php (cron_taken_uitvoeren): berichtenwachtrij,
 * AI-wachtrij, herinneringen, automatisch uitnodigen, Authentik-sync en de AVG-opschoning
 * (1x per dag). Een lock voorkomt dubbel draaien met de lazy-cron uit het beheer.
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Header gaat voor; de querystring blijft voor bestaande cronjobs. Geen string = geen token
// (een ?token[]= zou hash_equals anders een TypeError geven).
$token = (string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
if ($token === '') $token = $_GET['token'] ?? '';
if (!is_string($token)) $token = '';
$geheim = instelling('cron.token', '');
if ($geheim === '' || $token === '' || !hash_equals($geheim, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'fout' => 'Ongeldige token']);
    exit;
}

$start = microtime(true);
$out = function_exists('cron_taken_uitvoeren')
    ? cron_taken_uitvoeren('cron', ['queue' => 8, 'ai' => 3])
    : ['ok' => false, 'fout' => 'cron_taken ontbreekt'];
$out['uitgevoerd_op'] = date('c');
$out['duur_ms'] = round((microtime(true) - $start) * 1000);
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

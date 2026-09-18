<?php
/**
 * Paal 13 — Cron runner
 * Aangeroepen door de Plesk/cPanel-cron (elke 5 min):
 *   curl -s "https://sollicitatie.paal13.com/cron/run.php?token=..."
 * Token staat in Instellingen → Mail & notificaties → Cronjob.
 *
 * Alle taken zitten in includes/cron_taken.php (cron_taken_uitvoeren): berichtenwachtrij,
 * AI-wachtrij, herinneringen, automatisch uitnodigen, Authentik-sync en de AVG-opschoning
 * (1x per dag). Een lock voorkomt dubbel draaien met de lazy-cron uit het beheer.
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$token  = $_GET['token'] ?? '';
$geheim = instelling('cron.token', '');
if ($geheim === '' || !hash_equals($geheim, $token)) {
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

<?php
/**
 * Paal 13 — Cron runner. De enige plek waar periodieke taken draaien (geen lazy cron meer).
 *
 * Aanbevolen (Plesk › Geplande taken › "PHP-script uitvoeren"), elke minuut:
 *   php cron/run.php
 * Zonder groep draait hij elke groep die aan de beurt is (snel/kwartier/dag, zie
 * includes/cron_taken.php). Aparte taken per groep mogen ook: php cron/run.php dag
 *
 * Via HTTP kan het ook (bijv. curl), dan is het token verplicht, bij voorkeur als header zodat
 * het niet in de toegangslogs belandt:
 *   curl -s -H "X-Cron-Token: ..." "https://sollicitatie.paal13.com/cron/run.php?groep=snel"
 * Vanaf de opdrachtregel is geen token nodig: wie een shell op de server heeft, kan dit toch al.
 */
require_once __DIR__ . '/../includes/functions.php';

$cli = PHP_SAPI === 'cli';
if ($cli) {
    $groep = (string)($argv[1] ?? 'auto');
} else {
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
    $groep = is_string($_GET['groep'] ?? null) ? $_GET['groep'] : 'auto';
    // Een HTTP-run mag niet afbreken als de aanroeper (curl) de verbinding sluit.
    ignore_user_abort(true);
}
@set_time_limit(300);

$start = microtime(true);
$out = cron_taken_uitvoeren($groep);
$out['uitgevoerd_op'] = date('c');
$out['duur_ms'] = round((microtime(true) - $start) * 1000);
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ($cli ? "\n" : '');
if ($cli) exit($out['ok'] ? 0 : 1);

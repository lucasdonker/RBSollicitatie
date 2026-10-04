<?php
/**
 * GET /api/status.php — hartslag voor een externe uptime-monitor.
 *
 * Een stilstaande cron zie je anders pas als iemand het beheer opent. Een monitor (UptimeRobot,
 * Uptime Kuma, Better Stack, …) vraagt dit elke paar minuten op en alarmeert bij een 503.
 *
 * Toegang: het cron-token (Instellingen › Bewaren & opschonen), bij voorkeur als header
 *   X-Cron-Token: <token>
 * of, als de monitor geen headers kan zetten, als ?token=<token> (belandt dan wel in de toegangslog).
 * Vergelijking in constante tijd; per IP maximaal 30 verzoeken per minuut.
 *
 * Antwoord: alleen tellingen en tijden, nooit persoonsgegevens.
 *   200 {ok:true,  ...}  alles loopt
 *   503 {ok:false, ...}  taakronde langer dan 10 minuten stil, of berichten hangen langer dan een uur
 *   403 ongeldig token · 429 te veel verzoeken · 503 zonder token ingesteld
 */
require_once __DIR__ . '/_basis.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (!rate_limit('api_status', rate_identiteit(), 30, 60)) {
    api_json(['ok' => false, 'fout' => 'Te veel verzoeken.'], 429);
}

// Zelfde token en volgorde als cron/run.php: header gaat voor; geen string (?token[]=) = geen token.
$token = (string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
if ($token === '') $token = $_GET['token'] ?? '';
if (!is_string($token)) $token = '';
$geheim = (string)instelling('cron.token', '');
if ($geheim === '') {
    api_json(['ok' => false, 'fout' => 'Statuscontrole is nog niet ingesteld (geen cron-token).'], 503);
}
if ($token === '' || !hash_equals($geheim, $token)) {
    api_json(['ok' => false, 'fout' => 'Ongeldige token.'], 403);
}

$hartslag = cron_hartslag(600);
$wachtrij = function_exists('health_wachtrij_stand') ? health_wachtrij_stand() : null;
$groepen  = [];
foreach (cron_stand() as $g => $s) $groepen[$g] = $s['status'];

$problemen = [];
if (!$hartslag['ok']) $problemen[] = $hartslag['laatst'] === null ? 'taakronde heeft nog nooit gedraaid' : 'taakronde loopt niet (langer dan 10 minuten stil)';
if ($wachtrij !== null && $wachtrij['vast'] > 0) $problemen[] = 'berichten wachten langer dan een uur';

api_json([
    'ok'              => !$problemen,
    'problemen'       => $problemen,
    'cron_laatst'     => $hartslag['laatst'],
    'cron_seconden'   => $hartslag['seconden'],
    'cron_groepen'    => $groepen,
    'wachtrij_wacht'  => $wachtrij['wacht'] ?? null,
    'wachtrij_vast'   => $wachtrij['vast'] ?? null,
    'wachtrij_fout'   => $wachtrij['fout_24u'] ?? null,
    'testmodus'       => function_exists('mail_omleiden') && mail_omleiden() !== '',
    'versie'          => defined('APP_VERSIE') ? APP_VERSIE : null,
    'gecontroleerd_op'=> date('c'),
], $problemen ? 503 : 200);

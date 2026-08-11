<?php
/**
 * Paal 13 — Cron runner
 * Aangeroepen door cPanel cron (elke 5 min):
 *   curl -s "https://sollicitatie.paal13.com/cron/run.php?token=..."
 * Token staat in Instellingen → Mail & notificaties → Cronjob.
 *
 * Draait per aanroep:
 *  - mail_queue_verwerk(8)   → gespreide mailverzending
 *  - ai_queue_verwerk(3)     → wachtende AI-analyses
 *  - reminder_run()          → BLK / SD-vooraf / SD-morgen / ondertekening-herinneringen
 *  - auto_invite_run()       → automatische uitnodigingen (indien modus=auto)
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
$out = ['ok' => true, 'uitgevoerd_op' => date('c'), 'taken' => []];

if (function_exists('mail_queue_verwerk')) $out['taken']['mail_queue'] = mail_queue_verwerk(8);
if (function_exists('ai_queue_verwerk')) { try { $out['taken']['ai_queue'] = ai_queue_verwerk(3); } catch (\Exception $e) { $out['taken']['ai_queue'] = ['fout' => $e->getMessage()]; } }
if (function_exists('reminder_run'))     $out['taken']['reminders']  = reminder_run();
if (function_exists('auto_invite_run'))  $out['taken']['auto_invite'] = auto_invite_run();
if (function_exists('competenties_cron_sync')) $out['taken']['authentik_sync'] = competenties_cron_sync();

// Opschoning volgens bewaartermijnen — hoogstens 1x per dag draaien
if (function_exists('opschoon_run')) {
    $laatste = instelling('bewaar.laatste_run', '');
    $vandaag = date('Y-m-d');
    if (substr($laatste, 0, 10) !== $vandaag) {
        try { $out['taken']['opschonen'] = opschoon_run(false, 'cron'); }
        catch (\Exception $e) { $out['taken']['opschonen'] = ['fout' => $e->getMessage()]; }
    } else {
        $out['taken']['opschonen'] = ['overgeslagen' => 'al gedraaid vandaag'];
    }
}

instelling_opslaan('cron.laatste_run', date('Y-m-d H:i:s'));
instelling_opslaan('cron.laatste_bron', 'cron');

$out['duur_ms'] = round((microtime(true) - $start) * 1000);
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

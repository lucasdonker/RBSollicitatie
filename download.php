<?php
/**
 * Paal 13 — Download van een bijlage via een getekende, verlopende link
 * (download.php?b=<id>&e=<verloopt>&t=<handtekening>). Zie includes/meldingen/bijlagen.php.
 */
require_once __DIR__ . '/includes/functions.php';

$id  = (int)($_GET['b'] ?? 0);
$exp = (int)($_GET['e'] ?? 0);
$tok = (string)($_GET['t'] ?? '');
// Korte vorm: /d/<id>-<verloopt>-<token> (via .htaccess) of download.php?d=<id>-<verloopt>-<token>
if (!$id && preg_match('/^(\d+)-(\d+)-([a-f0-9]{16,64})$/', (string)($_GET['d'] ?? ''), $m)) { $id = (int)$m[1]; $exp = (int)$m[2]; $tok = $m[3]; }

$weiger = function (int $code, string $titel, string $tekst): void {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>' . htmlspecialchars($titel) . '</title>'
       . '<link rel="stylesheet" href="' . APP_URL . '/assets/css/stijl.css"></head>'
       . '<body style="padding:40px;text-align:center;font-family:sans-serif"><h1>' . htmlspecialchars($titel) . '</h1><p>' . htmlspecialchars($tekst) . '</p>'
       . '<p><a href="https://paal13.com">paal13.com</a></p></body></html>';
    exit;
};

if ($id <= 0 || $exp <= 0 || $tok === '' || !function_exists('bijlage_token')) $weiger(404, 'Link ongeldig', 'Deze downloadlink klopt niet.');
if (!hash_equals(bijlage_token($id, $exp), $tok)) $weiger(403, 'Link ongeldig', 'Deze downloadlink is niet geldig.');
if ($exp < time()) $weiger(410, 'Link verlopen', 'Deze downloadlink is verlopen. Vraag Paal 13 om een nieuwe.');

$r = bijlage_rij($id);
$pad = $r ? bijlage_pad($r) : null;
if (!$r || !$pad) $weiger(404, 'Bestand niet gevonden', 'Dit bestand is niet (meer) beschikbaar.');

if (function_exists('audit_log')) { try { audit_log('bijlage_gedownload', 'bijlage', $id, null); } catch (\Throwable $e) {} }

header('Content-Type: ' . $r['mime']);
header('Content-Length: ' . filesize($pad));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $r['bestandsnaam']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($pad);
exit;

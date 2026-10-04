<?php
/**
 * Storingspagina in huisstijl, zelfde opbouw als 404.php. Wordt ge-include door
 * includes/error_handler.php (met $foutcode_html). Laadt zelf niets: bij een storing kan de
 * database of de configuratie juist het probleem zijn. Wat er al geladen is (icoon(), het
 * contactadres) gebruiken we; anders een vaste terugval.
 */
// Ook bruikbaar als Apache-ErrorDocument (500/503, root .htaccess): dan zet niemand anders de status.
if (!headers_sent() && http_response_code() < 500) http_response_code(500);
$foutcode_html = isset($foutcode_html) ? htmlspecialchars($foutcode_html) : 'P13-S001';
$app_url_500   = defined('APP_URL') ? APP_URL : '';
$esc  = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$ico  = fn(string $naam, int $g): string => function_exists('icoon') ? icoon($naam, $g)
      : '<svg width="' . $g . '" height="' . $g . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
$contact = 'sollicitatie@paal13.com';
if (function_exists('portaal_contact_email')) { try { $contact = portaal_contact_email() ?: $contact; } catch (\Throwable $e) {} }
$is_admin    = str_starts_with((string)($_SERVER['REQUEST_URI'] ?? ''), '/admin');
$terug_url   = $app_url_500 . ($is_admin ? '/admin/' : '/portaal/');
$terug_label = $is_admin ? 'Naar het dashboard' : 'Naar het portaal';
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Storing — Paal 13</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= $esc($app_url_500 . '/assets/css/stijl.css') ?>">
</head>
<body class="pt-body">
  <header class="pt-topbar">
    <span class="pt-merk-logo"><?= $ico('golf', 16) ?></span>
    <div class="pt-merk-tekst">
      <span class="pt-merk-naam">Paal 13</span>
      <span class="pt-merk-sub"><?= $is_admin ? 'Beheer' : 'Sollicitatie' ?></span>
    </div>
  </header>
  <main class="pt-main">
    <div class="pt-leeg groot" role="alert">
      <span class="pt-leeg-icoon navy"><?= $ico('let-op', 26) ?></span>
      <h1 class="pt-leeg-titel">Er ging aan onze kant iets mis</h1>
      <p class="pt-leeg-tekst">Dit ligt niet aan jou. Probeer het over een paar minuten opnieuw; wat je al had opgeslagen, staat er nog.
        Blijft het misgaan? Mail <a href="mailto:<?= $esc($contact) ?>"><?= $esc($contact) ?></a> en noem de foutcode hieronder.</p>
      <a href="<?= $esc($terug_url) ?>" class="ds-btn ds-btn-primair"><?= $esc($terug_label) ?></a>
      <span class="pt-leeg-tekst">Foutcode <?= $foutcode_html ?></span>
    </div>
  </main>
</body>
</html>

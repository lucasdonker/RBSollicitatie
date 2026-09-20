<?php
/**
 * Eigen 404-pagina in huisstijl. Komt op twee manieren in beeld:
 *  - Apache: ErrorDocument 404 /404.php (root .htaccess) voor adressen die niet bestaan;
 *  - PHP:    pagina_404() (includes/functions.php) vanuit code, bijv. een partial die direct wordt opgevraagd.
 * Werkt ook zonder database/config: alles wat uit functions.php komt is optioneel.
 */
http_response_code(404);
$pad      = (string)($_SERVER['REQUEST_URI'] ?? '');
$is_admin = str_starts_with($pad, '/admin');
$app_url  = '';
if (file_exists(__DIR__ . '/includes/functions.php')) {
    if (!defined('P13_NO_ERROR_HANDLER')) define('P13_NO_ERROR_HANDLER', 1);
    @require_once __DIR__ . '/includes/functions.php';
    $app_url = defined('APP_URL') ? APP_URL : '';
}
$esc  = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$ico  = fn(string $naam, int $g): string => function_exists('icoon') ? icoon($naam, $g)
      : '<svg width="' . $g . '" height="' . $g . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg>';
$css  = $app_url . '/assets/css/stijl.css';
$terug_url   = $app_url . ($is_admin ? '/admin/' : '/portaal/');
$terug_label = $is_admin ? 'Naar het dashboard' : 'Naar het portaal';
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pagina niet gevonden — Paal 13</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= $esc($css) ?>">
<style>
  .p404-pad { font: var(--text-meta); color: var(--ink-muted); text-align: center; margin: 14px 0 0; word-break: break-all; }
</style>
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
    <div class="pt-leeg groot" role="main">
      <span class="pt-leeg-icoon navy"><?= $ico('zoek', 26) ?></span>
      <span class="pt-leeg-titel">Pagina niet gevonden</span>
      <span class="pt-leeg-tekst">Deze pagina bestaat niet of is verplaatst. Controleer het adres, of ga terug naar de startpagina.</span>
      <a href="<?= $esc($terug_url) ?>" class="ds-btn ds-btn-primair"><?= $esc($terug_label) ?></a>
    </div>
    <p class="p404-pad">Foutcode P13-S003 · <?= $esc($pad) ?></p>
  </main>
</body>
</html>

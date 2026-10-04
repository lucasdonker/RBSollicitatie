<?php
/**
 * Het kader van elk beheerscherm: <head>, zijbalk, topbalk, en het begin van de
 * pagina-inhoud. Sluiten gebeurt in layout_bottom.php.
 *
 * Een scherm zet vóór het requiren:
 *   $pagina_titel  string  wat er in de topbalk staat
 *   $actieve_nav   string  welk menu-item oplicht (zie bh_nav_actief())
 *   $pagina_breed  bool    optioneel: volle breedte i.p.v. de leesbreedte
 *
 * LET OP: rechtencontrole en POST-verwerking horen vóór dit bestand. vereis_recht()
 * breekt af met die() en redirect() stuurt een header; nadat hier HTML is uitgestuurd
 * kan dat allebei niet meer.
 */
vereist_login();
require_once __DIR__ . '/../includes/beheer/laden.php';

$_bhd = huidige_beheerder();
$_seizoen = actief_seizoen();

// Tellers in het menu: wat vraagt om aandacht. Stil falen — een menu hoort nooit een
// scherm te breken.
$_tellers = [];
try {
    if (function_exists('bh_nav_tellers')) $_tellers = bh_nav_tellers($_seizoen);
} catch (\Throwable $e) { $_tellers = []; }

$_nav        = bh_nav_groepen($_tellers);
$_nav_actief = bh_nav_actief((string)($actieve_nav ?? ''));

// Rondleiding: de stappen van dit scherm, een lopende route (?rondleiding=) en het
// welkomstblok op Vandaag. Stil falen, net als de tellers.
$_rl = null;
try {
    if (function_exists('rl_pagina_data')) {
        $_rl = rl_pagina_data(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')),
                              preg_replace('/[^a-z0-9_-]/', '', (string)($_GET['rondleiding'] ?? '')),
                              (string)($_bhd['rol'] ?? ''));
    }
} catch (\Throwable $e) { $_rl = null; }

$_kan_portaal = false;
if (function_exists('toegang_bepaal') && !empty($_bhd['email'])) {
    $_t = toegang_bepaal($_bhd['email']);
    $_kan_portaal = !empty($_t['portaal']);
}
?>
<!DOCTYPE html>
<html lang="nl" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($pagina_titel ?? 'Beheer') ?> — Paal 13</title>
<?php $_fav = site_favicon_url(); if ($_fav): ?>
<link rel="icon" href="<?= h($_fav) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css?v=<?= @filemtime(__DIR__.'/../assets/css/stijl.css') ?>">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/beheer.css?v=<?= @filemtime(__DIR__.'/../assets/css/beheer.css') ?>">
<meta name="robots" content="noindex">
<?php // Vroeg: data-klik/data-terugval (vervangt inline onclick; CSP zonder 'unsafe-inline'). ?>
<script src="<?= APP_URL ?>/assets/js/klik.js?v=<?= @filemtime(__DIR__.'/../assets/js/klik.js') ?>"></script>
<script nonce="<?= h(csp_nonce()) ?>">
// Thema vóór de eerste verf, anders flitst de pagina wit op bij donkere modus.
(function(){try{
  var t = localStorage.getItem('paal13-theme');
  if (t !== 'dark' && t !== 'light') t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : '');
}catch(e){}})();
</script>
</head>
<body class="bh-body">

<div class="bh-kader">
  <div class="bh-overlay" id="navoverlay" hidden></div>

  <?php
    $nav         = $_nav;
    $nav_actief  = $_nav_actief;
    $nav_bhd     = $_bhd;
    $nav_portaal = $_kan_portaal;
    require __DIR__ . '/_nav.php';
  ?>

  <div class="bh-hoofd">
    <?php
      $top_titel   = $pagina_titel ?? 'Beheer';
      $top_seizoen = $_seizoen;
      $top_hulp    = function_exists('hulp_tekst') ? hulp_tekst((string)($actieve_nav ?? '')) : null;
      $top_rondleiding = !empty($_rl['scherm']);
      require __DIR__ . '/_topbar.php';
    ?>

    <?php // Live bijwerken (includes/live.php): een pagina zet $live_scopes, bijv. ['sd:' . $sid]. ?>
    <main class="bh-inhoud<?= !empty($pagina_breed) ? ' breed' : '' ?>" id="inhoud"<?= !empty($live_scopes) && function_exists('live_attr') ? live_attr((array)$live_scopes) : '' ?>>
      <?php toon_flash(); ?>
      <?php if (!empty($_rl['welkom'])) { $rlw = $_rl['welkom']; require __DIR__ . '/_rl_welkom.php'; } ?>

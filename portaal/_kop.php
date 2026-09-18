<?php
/**
 * Gedeelde portaal-kop. Opent de pagina met head + topbar.
 * Elke portaal-pagina zet vooraf (optioneel):
 *   $portaal_titel      = 'Mijn sollicitatie';   // <title> + desktop-paginakop
 *   $portaal_nav        = 'start';               // welke tab actief is: start | taken | gegevens
 *   $portaal_terug      = APP_URL.'/portaal/';   // als gezet: toon een terug-pijl i.p.v. het topbar-icoon
 *   $portaal_geen_nav   = true;                  // geen navigatiebalk/zijbalk (bijv. inlogscherm)
 *   $portaal_geen_topbar= true;                  // geen navy topbar (inlogscherm heeft eigen kop)
 *   $portaal_merk_naam  = 'Reddingsbrigade Paal 13'; // afwijkende merkregels in de topbar (bijv. de
 *   $portaal_merk_sub   = 'Ondertekenen voor Lars';  // ouder-ondertekenlink, ontwerp 09)
 * en sluit af met require _voet.php.
 *
 * De body krijgt 'heeft-nav' zodra de sollicitant is ingelogd en de nav niet is uitgezet;
 * de CSS (stijl.css, .pt-*) gebruikt dat voor de desktop-zijbalk-marge.
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$portaal_titel       = $portaal_titel ?? 'Mijn sollicitatie';
$portaal_nav         = $portaal_nav ?? '';
$portaal_terug       = $portaal_terug ?? '';
$portaal_geen_nav    = $portaal_geen_nav ?? false;
$portaal_geen_topbar = $portaal_geen_topbar ?? false;
$portaal_merk_naam   = $portaal_merk_naam ?? 'Paal 13';
$portaal_merk_sub    = $portaal_merk_sub ?? '';
$_fav = function_exists('site_favicon_url') ? site_favicon_url() : '';
$_heeft_nav = !$portaal_geen_nav && function_exists('portaal_ingelogd') && portaal_ingelogd();
// Status-chip in de desktop-paginakop: fase van de ingelogde sollicitant (pagina mag hem zelf zetten)
if ($_heeft_nav && empty($portaal_status_fase) && function_exists('portaal_sollicitant_laden')) {
    $_ps = portaal_sollicitant_laden((int)$_SESSION['portaal_sol_id']);
    $portaal_status_fase = $_ps['fase'] ?? '';
}
$portaal_status_fase = $portaal_status_fase ?? '';
$_chip_kleur = match ($portaal_status_fase) { 'aangenomen' => 'ok', 'afgewezen' => 'error', 'aangemeld' => 'navy', default => 'accent' };
// Belletje: ongelezen berichten (pagina mag het aantal zelf zetten, bijv. berichten.php → 0)
$_ongelezen = 0;
if ($_heeft_nav) {
    $_ongelezen = isset($portaal_berichten_ongelezen) ? (int)$portaal_berichten_ongelezen
                : (function_exists('portaal_berichten_ongelezen') ? portaal_berichten_ongelezen((int)$_SESSION['portaal_sol_id']) : 0);
}
$_bel = '<a href="' . APP_URL . '/portaal/berichten.php" class="pt-topbar-actie" aria-label="Berichten' . ($_ongelezen ? ' (' . $_ongelezen . ' ongelezen)' : '') . '">'
      . icoon('bel', 20) . ($_ongelezen ? '<span class="pt-topbar-stip"></span>' : '') . '</a>';
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($portaal_titel) ?> — Paal 13</title>
<?php if ($_fav): ?><link rel="icon" href="<?= h($_fav) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css?v=<?= @filemtime(__DIR__.'/../assets/css/stijl.css') ?>">
<meta name="robots" content="noindex">
</head>
<body class="pt-body<?= $_heeft_nav ? ' heeft-nav' : '' ?>">

<?php if (!$portaal_geen_topbar): ?>
  <!-- Topbar (exact volgens Portaal.dc: navy, logo-vierkantje, merk-kolom, icoon rechts) -->
  <?php $sz = function_exists('actief_seizoen') ? actief_seizoen() : null; ?>
  <header class="pt-topbar">
    <span class="pt-merk-logo"><?= icoon('golf', 16) ?></span>
    <div class="pt-merk-tekst">
      <span class="pt-merk-naam"><?= h($portaal_merk_naam) ?></span>
      <span class="pt-merk-sub"><?= $portaal_merk_sub !== '' ? h($portaal_merk_sub) : ($sz ? h($sz['naam']) : 'Sollicitatie 2026') ?></span>
    </div>
    <?php if ($portaal_terug): ?>
      <a href="<?= h($portaal_terug) ?>" class="pt-topbar-actie" aria-label="Terug"><?= icoon('terug', 20) ?></a>
    <?php elseif ($_heeft_nav): ?>
      <?= $_bel ?>
    <?php elseif (function_exists('portaal_ingelogd') && portaal_ingelogd()): ?>
      <a href="<?= APP_URL ?>/portaal/?uitloggen=1" class="pt-topbar-actie" aria-label="Uitloggen"><?= icoon('uitloggen', 20) ?></a>
    <?php endif; ?>
  </header>
<?php endif; ?>

<?php if ($_heeft_nav): ?>
  <!-- Desktop-paginakop (ontwerp: witte balk met titel, status-chip en icoonknop; op mobiel verborgen) -->
  <div class="pt-paginakop">
    <?php if ($portaal_terug): ?><a href="<?= h($portaal_terug) ?>" class="pt-paginakop-knop" aria-label="Terug"><?= icoon('terug', 18) ?></a><?php endif; ?>
    <span class="pt-paginakop-titel"><?= h($portaal_titel) ?></span>
    <div class="pt-paginakop-acties">
      <?php if ($portaal_status_fase !== ''): ?>
        <span class="pt-paginakop-chip <?= $_chip_kleur ?>"><span class="pt-paginakop-stip"></span><?= h(fase_label($portaal_status_fase)) ?></span>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/portaal/berichten.php" class="pt-paginakop-knop" aria-label="Berichten<?= $_ongelezen ? ' (' . $_ongelezen . ' ongelezen)' : '' ?>"><?= icoon('bel', 18) ?><?php if ($_ongelezen): ?><span class="pt-topbar-stip"></span><?php endif; ?></a>
    </div>
  </div>
<?php endif; ?>

  <!-- Pagina-inhoud -->
  <main class="pt-main">

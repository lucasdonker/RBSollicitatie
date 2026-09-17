<?php
/**
 * Gedeelde portaal-kop. Opent de pagina met head + topbar.
 * Elke portaal-pagina zet vooraf (optioneel):
 *   $portaal_titel      = 'Mijn sollicitatie';   // <title> + desktop-paginakop
 *   $portaal_nav        = 'start';               // welke tab actief is: start | taken | gegevens
 *   $portaal_terug      = APP_URL.'/portaal/';   // als gezet: toon een terug-pijl i.p.v. het topbar-icoon
 *   $portaal_geen_nav   = true;                  // geen navigatiebalk/zijbalk (bijv. inlogscherm)
 *   $portaal_geen_topbar= true;                  // geen navy topbar (inlogscherm heeft eigen kop)
 * en sluit af met require _voet.php.
 *
 * De body krijgt 'heeft-nav' zodra de sollicitant is ingelogd en de nav niet is uitgezet;
 * de CSS (stijl.css, .pt-*) gebruikt dat voor de desktop-zijbalk-marge.
 */
if (!defined('APP_URL')) { require_once __DIR__ . '/../includes/functions.php'; }
$portaal_titel       = $portaal_titel ?? 'Mijn sollicitatie';
$portaal_nav         = $portaal_nav ?? '';
$portaal_terug       = $portaal_terug ?? '';
$portaal_geen_nav    = $portaal_geen_nav ?? false;
$portaal_geen_topbar = $portaal_geen_topbar ?? false;
$_fav = function_exists('site_favicon_url') ? site_favicon_url() : '';
$_heeft_nav = !$portaal_geen_nav && function_exists('portaal_ingelogd') && portaal_ingelogd();
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
      <span class="pt-merk-naam">Paal 13</span>
      <span class="pt-merk-sub"><?= $sz ? h($sz['naam']) : 'Sollicitatie 2026' ?></span>
    </div>
    <?php if ($portaal_terug): ?>
      <a href="<?= h($portaal_terug) ?>" class="pt-topbar-actie" aria-label="Terug"><?= icoon('terug', 20) ?></a>
    <?php else: ?>
      <a href="<?= APP_URL ?>/portaal/?uitloggen=1" class="pt-topbar-actie" aria-label="Uitloggen"><?= icoon('uitloggen', 20) ?></a>
    <?php endif; ?>
  </header>
<?php endif; ?>

  <!-- Pagina-inhoud -->
  <main class="pt-main">

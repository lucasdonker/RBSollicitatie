<?php
/**
 * Gedeelde portaal-kop. Opent de pagina met head + topbar.
 * Elke portaal-pagina zet vooraf (optioneel):
 *   $portaal_titel = 'Mijn sollicitatie';   // <title>
 *   $portaal_nav   = 'start';               // welke tab actief is: start | taken | gegevens
 *   $portaal_terug = APP_URL.'/portaal/';   // als gezet: toon een terug-pijl i.p.v. de tabs-topbar
 * en sluit af met require _voet.php.
 */
if (!defined('APP_URL')) { require_once __DIR__ . '/../includes/functions.php'; }
$portaal_titel = $portaal_titel ?? 'Mijn sollicitatie';
$portaal_nav   = $portaal_nav ?? '';
$portaal_terug = $portaal_terug ?? '';
$_fav = function_exists('site_favicon_url') ? site_favicon_url() : '';
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
<body class="pt-body">

  <!-- Topbar -->
  <header class="pt-topbar">
    <?php if ($portaal_terug): ?>
      <a href="<?= h($portaal_terug) ?>" class="pt-topbar-terug" aria-label="Terug"><?= icoon('terug', 22) ?></a>
      <span class="pt-topbar-titel"><?= h($portaal_titel) ?></span>
      <span style="width:38px"></span>
    <?php else: ?>
      <div class="pt-merk">
        <span class="pt-merk-logo"><?= icoon('golf', 20) ?></span>
        <span>Paal 13</span>
      </div>
      <?php $sz = function_exists('actief_seizoen') ? actief_seizoen() : null; ?>
      <span class="pt-merk-sub"><?= $sz ? h($sz['naam']) : 'Sollicitatie' ?></span>
    <?php endif; ?>
  </header>

  <!-- Pagina-inhoud -->
  <main class="pt-main">

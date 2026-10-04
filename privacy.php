<?php
/**
 * De privacyverklaring voor sollicitanten: https://sollicitatie.paal13.com/privacy.php
 *
 * Het aanmeldformulier linkt hierheen (ook op een landingspagina en via de WordPress-plugin).
 * De tekst staat in includes/privacy_verklaring.php; hier alleen de omlijsting, dezelfde als
 * die van aanmelden.php. Geen sessie: dit is een openbare pagina om te lezen.
 */
require_once __DIR__ . '/includes/functions.php';
security_headers();

$v = static fn(string $p): string => APP_URL . $p . '?v=' . (@filemtime(__DIR__ . $p) ?: '1');
$contact = instelling('mail.bestuur_email', 'bestuur@paal13.com');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Privacyverklaring sollicitanten — Reddingsbrigade Paal 13</title>
<meta name="description" content="Welke gegevens Reddingsbrigade Paal 13 van sollicitanten gebruikt, waarom, hoe lang, en wat je rechten zijn.">
<link rel="stylesheet" href="<?= h($v('/assets/css/aanmeldformulier.css')) ?>">
</head>
<body class="af-pagina">
<header class="af-hero">
  <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Reddingsbrigade Paal 13" width="120" height="46">
  <h1>Privacyverklaring</h1>
  <p>Voor iedereen die solliciteert als strandwacht</p>
</header>

<main>
  <article class="af-kaart af-zelfstandig af-pv">
    <?= privacy_verklaring_html() ?>
  </article>
</main>

<footer class="af-voet">
  Vragen over je gegevens? Mail <a href="mailto:<?= h($contact) ?>"><?= h($contact) ?></a>.
  · <a href="<?= APP_URL ?>/aanmelden.php">Naar het aanmeldformulier</a>
</footer>
</body>
</html>

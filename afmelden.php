<?php
/**
 * Afmeldpagina voor mailings: /afmelden.php?t=<token>
 * Toont een bevestiging, met de mogelijkheid je toch weer aan te melden.
 */
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['t'] ?? '';
$actie = $_GET['actie'] ?? '';
$naam = null; $heraangemeld = false;

if ($actie === 'heraanmelden' && function_exists('optout_heraanmelden')) {
    $naam = optout_heraanmelden($token);
    $heraangemeld = ($naam !== null);
} elseif (function_exists('optout_afmelden')) {
    $naam = optout_afmelden($token);
}
$gelukt = ($naam !== null);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Afmelden — Paal 13</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css">
<style>
  body{background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
  .kaart{max-width:440px;width:100%;background:#fff;border-radius:14px;padding:32px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.06)}
  .kaart h1{font-size:20px;color:#054d8a;margin:0 0 10px}
  .kaart p{color:#64748b;font-size:14px;line-height:1.6}
  .icoon{font-size:44px;margin-bottom:12px}
  a.knop{display:inline-block;margin-top:16px;font-size:13px;color:#64748b;text-decoration:underline}
</style>
</head>
<body>
  <div class="kaart">
    <?php if ($heraangemeld): ?>
      <div class="icoon">✓</div>
      <h1>Je bent weer aangemeld</h1>
      <p>Je ontvangt weer mailings van Reddingsbrigade Paal 13.</p>
    <?php elseif ($gelukt): ?>
      <div class="icoon">👋</div>
      <h1>Je bent afgemeld</h1>
      <p>Je ontvangt geen mailings meer van Reddingsbrigade Paal 13. Belangrijke berichten over
         je sollicitatie (zoals uitnodigingen) blijf je wel ontvangen.</p>
      <a class="knop" href="<?= APP_URL ?>/afmelden.php?actie=heraanmelden&t=<?= htmlspecialchars($token) ?>">Toch weer aanmelden</a>
    <?php else: ?>
      <div class="icoon">🤔</div>
      <h1>Link niet herkend</h1>
      <p>Deze afmeldlink is ongeldig of verlopen. Neem gerust contact met ons op als je geen
         mailings meer wilt ontvangen.</p>
    <?php endif; ?>
  </div>
</body>
</html>

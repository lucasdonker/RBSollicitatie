<?php
// Wordt geinclude door error_handler.php
$foutcode_html = isset($foutcode_html) ? htmlspecialchars($foutcode_html) : 'P13-S001';
$app_url_500   = defined('APP_URL') ? APP_URL : '';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Serverfout — Paal 13</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;background:#001530;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .k{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:40px;max-width:420px;width:100%;text-align:center}
  h1{font-size:20px;font-weight:700;color:#fff;margin-bottom:8px}
  p{font-size:13px;color:rgba(255,255,255,.5);line-height:1.7;margin-bottom:16px}
  .code{font-size:11px;color:rgba(255,255,255,.25);margin-bottom:18px;font-family:monospace}
  a{display:inline-block;padding:9px 20px;border-radius:6px;font-size:13px;font-weight:500;text-decoration:none;background:#FF6B35;color:#fff}
</style>
</head>
<body>
<div class="k">
  <div style="font-size:56px;margin-bottom:16px">⚠️</div>
  <h1>Er ging iets fout</h1>
  <p>Er is een technische fout opgetreden. Het beheerteam is automatisch op de hoogte gesteld. Probeer het over een moment opnieuw.</p>
  <div class="code">Foutcode: <?= $foutcode_html ?></div>
  <a href="<?= htmlspecialchars($app_url_500) ?>/">Terug naar startpagina</a>
</div>
</body>
</html>

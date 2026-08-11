<?php
http_response_code(404);
$is_admin = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin');
$app_url = '';
if (file_exists(__DIR__ . '/includes/functions.php')) {
    define('P13_NO_ERROR_HANDLER', 1);
    @require_once __DIR__ . '/includes/functions.php';
    $app_url = defined('APP_URL') ? APP_URL : '';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pagina niet gevonden — Paal 13</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
         background: #001530; color: #e2e8f0; min-height: 100vh;
         display: flex; align-items: center; justify-content: center; padding: 20px; }
  .kaart { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
           border-radius: 16px; padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; }
  .golf { font-size: 64px; margin-bottom: 20px; display: block; }
  h1 { font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 8px; }
  p { font-size: 14px; color: rgba(255,255,255,.55); line-height: 1.7; margin-bottom: 24px; }
  .code { font-size: 11px; color: rgba(255,255,255,.25); margin-bottom: 20px; font-family: monospace; }
  .knoppen { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
  a.btn { display: inline-block; padding: 10px 22px; border-radius: 6px; font-size: 13px; font-weight: 500; text-decoration: none; }
  .btn-primary { background: #FF6B35; color: #fff; }
  .btn-outline { background: rgba(255,255,255,.08); color: rgba(255,255,255,.7); border: 1px solid rgba(255,255,255,.15); }
</style>
</head>
<body>
<div class="kaart">
  <span class="golf">🌊</span>
  <h1>Pagina niet gevonden</h1>
  <p>De pagina die je zoekt bestaat niet of is verplaatst. Misschien is de link verlopen of heb je een typfout gemaakt.</p>
  <div class="code">Foutcode: P13-S003 · <?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?></div>
  <div class="knoppen">
    <?php if ($is_admin): ?>
    <a href="<?= $app_url ?>/admin/" class="btn btn-primary">← Naar dashboard</a>
    <?php else: ?>
    <a href="<?= $app_url ?>/portaal/" class="btn btn-primary">← Naar portaal</a>
    <a href="<?= $app_url ?>/" class="btn btn-outline">Startpagina</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

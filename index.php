<?php
require_once __DIR__ . '/includes/functions.php';

// Als niet geïnstalleerd, redirect naar setup
$config_file = __DIR__ . '/includes/config.php';
if (!file_exists($config_file)) {
    header('Location: /setup/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Paal 13 — Sollicitatie & Selectie</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css">
<style>
body { background: #001530; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
.home-wrap { max-width: 500px; width: 100%; text-align: center; }
.home-logo img { height: 64px; margin-bottom: 24px; }
h1 { color: var(--oranje); font-size: 26px; margin-bottom: 8px; }
.sub { color: rgba(255,255,255,.55); font-size: 15px; margin-bottom: 40px; line-height: 1.6; }
.kaarten { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 24px; }
.kaart {
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 12px;
  padding: 24px 20px;
  text-decoration: none;
  color: #fff;
  transition: all .2s;
  display: block;
}
.kaart:hover { background: rgba(255,255,255,.12); transform: translateY(-2px); }
.kaart .icon { font-size: 32px; margin-bottom: 12px; display: block; }
.kaart .label { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
.kaart .desc { font-size: 12px; color: rgba(255,255,255,.5); line-height: 1.5; }
.kaart.primair { background: var(--oranje); border-color: var(--oranje); }
.kaart.primair:hover { background: var(--oranje-dim); }
.kaart.primair .desc { color: rgba(255,255,255,.75); }
.footer-link { font-size: 12px; color: rgba(255,255,255,.3); text-decoration: none; }
.footer-link:hover { color: rgba(255,255,255,.5); }
</style>
</head>
<body>
<div class="home-wrap">
  <div class="home-logo">
    <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Reddingsbrigade Paal 13">
  </div>
  <h1>Reddingsbrigade Paal 13</h1>
  <p class="sub">Sollicitatie- &amp; selectiesysteem voor lifeguards<br>Callantsoog &amp; Groote Keeten</p>

  <div class="kaarten">
    <a href="<?= APP_URL ?>/aanmelden.php" class="kaart primair">
      <span class="icon">🌊</span>
      <div class="label">Aanmelden als lifeguard</div>
      <div class="desc">Solliciteer voor het komende seizoen</div>
    </a>
    <a href="<?= APP_URL ?>/admin/" class="kaart">
      <span class="icon">⚙️</span>
      <div class="label">Beheerders</div>
      <div class="desc">Dashboard voor bestuur &amp; begeleiders</div>
    </a>
  </div>

  <a href="https://paal13.com" class="footer-link">← Terug naar paal13.com</a>
</div>
</body>
</html>

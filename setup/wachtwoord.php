<?php
// Hulppagina: genereer een bcrypt hash voor een wachtwoord
// Gebruik: /setup/wachtwoord.php
// Beveiliging: uitschakelen zodra het systeem is geïnstalleerd.
if (file_exists(__DIR__ . '/../includes/config.php')) {
    http_response_code(403);
    die('Niet beschikbaar. Verwijder de setup/-map van de server.');
}
$hash = '';
if (!empty($_POST['ww'])) {
    $hash = password_hash($_POST['ww'], PASSWORD_DEFAULT);
}
?><!DOCTYPE html>
<html lang="nl">
<head><meta charset="UTF-8"><title>Wachtwoord hash generator</title>
<style>body{font-family:sans-serif;background:#001530;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}.c{background:#002147;border-radius:12px;padding:28px;max-width:500px;width:100%}h1{color:#FF6B35;font-size:18px;margin-bottom:20px}input{width:100%;padding:9px 12px;border-radius:7px;border:1px solid #334;background:#001030;color:#fff;font-size:14px;margin-bottom:12px}button{background:#FF6B35;color:#fff;border:none;padding:10px 20px;border-radius:7px;cursor:pointer;font-size:14px}.hash{background:#001030;border-radius:7px;padding:12px;font-family:monospace;font-size:12px;word-break:break-all;margin-top:14px;border:1px solid #334}.note{font-size:12px;color:#8899aa;margin-top:10px}</style>
</head>
<body>
<div class="c">
  <h1>🔑 Wachtwoord hash generator</h1>
  <p style="font-size:13px;color:#8899aa;margin-bottom:16px">Gebruik dit om een wachtwoord hash te genereren voor een beheerder account.</p>
  <form method="POST">
    <input type="password" name="ww" placeholder="Vul een wachtwoord in" required minlength="8">
    <button type="submit">Genereer hash</button>
  </form>
  <?php if ($hash): ?>
  <div class="hash"><?= htmlspecialchars($hash) ?></div>
  <div class="note">Kopieer deze hash naar de kolom <code>wachtwoord</code> in phpMyAdmin (tabel: beheerders).</div>
  <?php endif; ?>
</div>
</body></html>

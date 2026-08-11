<?php
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
if (is_ingelogd()) redirect(APP_URL . '/admin/');

$fout  = '';
$terug = $_GET['terug'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $ww    = $_POST['wachtwoord'] ?? '';

    if (!$email || !$ww) {
        $fout = 'Vul je e-mailadres en wachtwoord in.';
    } else {
        $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        // Brute-force bescherming: te veel mislukte pogingen → tijdelijk blokkeren
        $wacht = login_geblokkeerd($email, $ip);
        if ($wacht > 0) {
            $minuten = ceil($wacht / 60);
            $fout = "Te veel mislukte inlogpogingen. Probeer het over {$minuten} minuut" . ($minuten === 1 ? '' : 'en') . ' opnieuw.';
        } else {
            $stmt = db()->prepare("SELECT * FROM beheerders WHERE email = ? AND actief = 1 LIMIT 1");
            $stmt->execute([$email]);
            $b = $stmt->fetch();
            if ($b && $b['wachtwoord'] && password_verify($ww, $b['wachtwoord'])) {
                login_pogingen_wissen($email, $ip);
                login_beheerder($b, 'wachtwoord');
                redirect($terug ?: APP_URL . '/admin/');
            } else {
                login_poging_registreren($email, $ip);
                sleep(1); // brute force vertraging
                $fout = 'Onjuist e-mailadres of wachtwoord.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Inloggen — Paal 13 Beheer</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css">
<meta name="robots" content="noindex">
<style>
.sso-btn { display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;border-radius:8px;border:1.5px solid var(--rand);background:#fff;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;color:var(--tekst);transition:all .15s; }
.sso-btn:hover { border-color:var(--oranje);background:var(--oranje-bg); }
.sso-icoon { width:24px;height:24px;border-radius:5px;background:#fd4b2d;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;flex-shrink:0; }
.scheiding { display:flex;align-items:center;gap:10px;margin:16px 0;color:var(--grijs);font-size:12px; }
.scheiding::before,.scheiding::after { content:'';flex:1;height:1px;background:var(--rand); }
</style>
</head>
<body>
<div class="login-wrapper">
  <div class="login-kaart">
    <div class="login-logo">
      <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Paal 13"
           onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/logo.svg'">
      <h1>Beheerders login</h1>
      <p>Reddingsbrigade Paal 13</p>
    </div>

    <?php if ($fout): ?>
    <div class="flash flash-fout"><?= h($fout) ?></div>
    <?php endif; ?>
    <?php toon_flash(); ?>

    <?php if (authentik_actief()): ?>
    <a href="<?= APP_URL ?>/admin/auth_sso.php<?= $terug ? '?terug=' . urlencode($terug) : '' ?>" class="sso-btn">
      <div class="sso-icoon">A</div>
      Inloggen via Authentik (SSO)
    </a>
    <div class="scheiding">of met wachtwoord</div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_input() ?>
      <?php if ($terug): ?><input type="hidden" name="terug" value="<?= h($terug) ?>"><?php endif; ?>
      <div class="form-groep">
        <label for="email">E-mailadres</label>
        <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus autocomplete="email" placeholder="jou@paal13.com">
      </div>
      <div class="form-groep">
        <label for="wachtwoord">Wachtwoord</label>
        <input type="password" id="wachtwoord" name="wachtwoord" required autocomplete="current-password" placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center">
        Inloggen
      </button>
    </form>

    <p style="text-align:center;font-size:12px;color:var(--grijs);margin-top:16px">
      Geen account? Vraag een admin om toegang.
    </p>
  </div>
</div>
</body>
</html>

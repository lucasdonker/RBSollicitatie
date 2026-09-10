<?php
/**
 * Keuzescherm na login — voor mensen die zowel beheer- als portaaltoegang hebben.
 *
 * Flow:
 *  - Komt hier terecht via de SSO-callback (of handmatig via de switch-knop).
 *  - Toont de twee ingangen; onthoudt desgewenst de voorkeur.
 *  - Bij een ingestelde voorkeur en directe login gaat het automatisch door (tenzij ?kies=1).
 *
 * De e-mail van de zojuist geverifieerde persoon staat in $_SESSION['toegang_email'].
 */
require_once __DIR__ . '/includes/functions.php';
sessie_start();

$email = strtolower(trim($_SESSION['toegang_email'] ?? ''));
if ($email === '') {
    // Niets om te kiezen — terug naar de login.
    redirect(APP_URL . '/portaal/');
}

$toegang = toegang_bepaal($email);

// Geen enkele toegang → terug naar login met melding
if (!$toegang['beheer'] && !$toegang['portaal']) {
    unset($_SESSION['toegang_email']);
    $_SESSION['portaal_flash'] = 'Je account is herkend, maar er is geen sollicitatie of beheertoegang gevonden.';
    redirect(APP_URL . '/portaal/');
}

// Maar één ingang → meteen daarheen (geen keuze nodig)
if ($toegang['beheer'] && !$toegang['portaal']) {
    toegang_login_als_beheerder($email, 'sso');
    unset($_SESSION['toegang_email']);
    redirect(APP_URL . '/admin/');
}
if ($toegang['portaal'] && !$toegang['beheer']) {
    toegang_login_als_sollicitant($email);
    unset($_SESSION['toegang_email']);
    redirect(APP_URL . '/portaal/');
}

// Beide ingangen. Een keuze verwerken?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ingang = $_POST['ingang'] ?? '';
    $onthoud = !empty($_POST['onthoud']);
    if (in_array($ingang, ['beheer', 'portaal'], true)) {
        $bid = (int)($toegang['beheerder']['id'] ?? 0);
        if ($onthoud && $bid) toegang_voorkeur_opslaan($bid, $ingang);
        unset($_SESSION['toegang_email']);
        if ($ingang === 'beheer') {
            toegang_login_als_beheerder($email, 'sso');
            redirect(APP_URL . '/admin/');
        } else {
            toegang_login_als_sollicitant($email);
            redirect(APP_URL . '/portaal/');
        }
    }
}

// Voorkeur ingesteld en niet expliciet om de keuze gevraagd → automatisch door
$bid = (int)($toegang['beheerder']['id'] ?? 0);
$voorkeur = toegang_voorkeur($bid);
if (empty($_GET['kies']) && ($voorkeur === 'beheer' || $voorkeur === 'portaal')) {
    // Alleen automatisch doorgaan als er echt een voorkeur is opgeslagen
    $heeft_voorkeur = false;
    try {
        $q = db()->prepare("SELECT standaard_ingang FROM beheerders WHERE id=?");
        $q->execute([$bid]);
        $heeft_voorkeur = in_array($q->fetchColumn(), ['beheer','portaal'], true);
    } catch (\Exception $e) {}
    if ($heeft_voorkeur) {
        unset($_SESSION['toegang_email']);
        if ($voorkeur === 'beheer') { toegang_login_als_beheerder($email, 'sso'); redirect(APP_URL . '/admin/'); }
        else { toegang_login_als_sollicitant($email); redirect(APP_URL . '/portaal/'); }
    }
}

$naam = trim(($toegang['beheerder']['naam'] ?? '') ?: $email);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Waar wil je heen? — Paal 13</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css">
<style>
  body { background: var(--navy, #001530); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  .kies-wrap { max-width: 520px; width: 100%; text-align: center; }
  .kies-wrap h1 { color: #fff; font-size: 22px; margin-bottom: 4px; }
  .kies-wrap .sub { color: rgba(255,255,255,.6); font-size: 14px; margin-bottom: 28px; }
  .kaarten { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px; }
  @media (max-width: 460px) { .kaarten { grid-template-columns: 1fr; } }
  .kaart { background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.14); border-radius: 14px; padding: 26px 20px; cursor: pointer; transition: all .15s; text-align: center; }
  .kaart:hover { background: rgba(255,255,255,.13); border-color: var(--oranje, #D43D00); transform: translateY(-2px); }
  .kaart .icoon { font-size: 34px; margin-bottom: 10px; }
  .kaart .titel { color: #fff; font-weight: 600; font-size: 16px; margin-bottom: 4px; }
  .kaart .uitleg { color: rgba(255,255,255,.55); font-size: 12.5px; line-height: 1.4; }
  .onthoud { color: rgba(255,255,255,.7); font-size: 13px; display: flex; align-items: center; gap: 8px; justify-content: center; margin-top: 8px; cursor: pointer; }
  button.kaart { border: 1px solid rgba(255,255,255,.14); font: inherit; }
</style>
</head>
<body>
  <div class="kies-wrap">
    <h1>Hoi <?= htmlspecialchars($naam) ?></h1>
    <p class="sub">Je hebt toegang tot twee delen. Waar wil je nu heen?</p>

    <form method="post">
      <?= csrf_input() ?>
      <label class="onthoud" style="margin-bottom:16px">
        <input type="checkbox" name="onthoud" value="1"> Onthoud mijn keuze (je kunt altijd wisselen)
      </label>
      <div class="kaarten">
        <button class="kaart" type="submit" name="ingang" value="beheer">
          <div class="icoon">🛠️</div>
          <div class="titel">Beheer</div>
          <div class="uitleg">Sollicitanten, planning en instellingen</div>
        </button>
        <button class="kaart" type="submit" name="ingang" value="portaal">
          <div class="icoon">📋</div>
          <div class="titel">Mijn sollicitatie</div>
          <div class="uitleg">Je eigen sollicitantenportaal</div>
        </button>
      </div>
    </form>
  </div>
</body>
</html>

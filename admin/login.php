<?php
/**
 * Beheer — inloggen. Normaal via Authentik (SSO, met eigen MFA); het wachtwoord is de noodingang.
 *
 * De noodingang is tweestaps (NIS2): een juist wachtwoord logt nog niet in, maar zet een
 * tussenstand in de sessie (includes/tweestaps.php, max 5 minuten). Daarna een code uit de
 * authenticator-app (of een herstelcode), of — zonder app — een eenmalige code per e-mail.
 * Mislukte codes tellen mee in login_pogingen (dezelfde blokkade als het wachtwoord) en staan
 * in het logboek; daarbovenop een snelheidsbegrenzing per account en per IP.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/toegang_terug.php';
require_once __DIR__ . '/../includes/tweestaps.php';
sessie_start();
if (is_ingelogd()) redirect(APP_URL . '/admin/');

$fout      = '';
$code_fout = '';
$herstel_fout = '';
// Alleen een pad binnen het beheer (geen open redirect); leeg blijft leeg voor de SSO-link.
$terug = isset($_GET['terug']) && $_GET['terug'] !== '' ? toegang_veilige_terug((string)$_GET['terug']) : '';
$zelf  = APP_URL . '/admin/login.php' . ($terug ? '?terug=' . urlencode($terug) : '');
$ip    = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);

/** Blokkademelding, gedeeld door de wachtwoord- en de codestap. */
function login_blokkade_tekst(int $wacht): string {
    $minuten = (int)ceil($wacht / 60);
    return "Te veel mislukte inlogpogingen. Probeer het over {$minuten} minuut" . ($minuten === 1 ? '' : 'en') . ' opnieuw.';
}

// Wacht er een tweede stap? Alleen voor een account dat nog actief is en een wachtwoord heeft.
$wacht_b = null;
if (($wacht_id = tweestaps_wacht()) > 0) {
    $wacht_b = tweestaps_rij($wacht_id);
    if (!$wacht_b || empty($wacht_b['actief']) || empty($wacht_b['wachtwoord'])) { tweestaps_wacht_stop(); $wacht_b = null; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = (string)($_POST['actie'] ?? 'wachtwoord');

    if ($actie === 'annuleren') {
        tweestaps_wacht_stop();
        redirect($zelf);
    }

    if ($actie === 'wachtwoord') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $ww    = $_POST['wachtwoord'] ?? '';
        tweestaps_wacht_stop();
        $wacht_b = null;

        if (!$email || !$ww) {
            $fout = 'Vul je e-mailadres en wachtwoord in.';
        } else {
            // Brute-force bescherming: te veel mislukte pogingen → tijdelijk blokkeren
            $wacht = login_geblokkeerd($email, $ip);
            if ($wacht > 0) {
                $fout = login_blokkade_tekst($wacht);
            } else {
                tweestaps_setup();
                $stmt = db()->prepare("SELECT * FROM beheerders WHERE email = ? AND actief = 1 LIMIT 1");
                $stmt->execute([$email]);
                $b = $stmt->fetch();
                if ($b && $b['wachtwoord'] && password_verify($ww, $b['wachtwoord'])) {
                    // Nog niet inloggen en de pogingen nog niet wissen: dat gebeurt pas na de
                    // tweede stap. Anders geeft elk juist wachtwoord weer verse codepogingen.
                    tweestaps_wacht_start((int)$b['id']);
                    if (tweestaps_methode($b) === 'mail') tweestaps_wacht_mail(tweestaps_mailcode_sturen($b));
                    redirect($zelf);
                } else {
                    login_poging_registreren($email, $ip);
                    sleep(1); // brute force vertraging
                    $fout = 'Onjuist e-mailadres of wachtwoord.';
                }
            }
        }
    }

    if (in_array($actie, ['code', 'herstel', 'nieuwe_mail'], true)) {
        if (!$wacht_b) {
            $fout = 'Je inlogpoging is verlopen: de code moet binnen 5 minuten na je wachtwoord. Log opnieuw in.';
        } else {
            $email   = strtolower(trim((string)$wacht_b['email']));
            $methode = tweestaps_methode($wacht_b);
            $wacht   = login_geblokkeerd($email, $ip);
            if ($wacht > 0) {
                tweestaps_wacht_stop(); $wacht_b = null;
                $fout = login_blokkade_tekst($wacht);
            } elseif ($actie === 'nieuwe_mail') {
                if ($methode === 'mail') tweestaps_wacht_mail(tweestaps_mailcode_sturen($wacht_b));
                redirect($zelf);
            } elseif (!tweestaps_poging_toegestaan((int)$wacht_b['id'], $ip)) {
                tweestaps_wacht_stop(); $wacht_b = null;
                $fout = 'Te veel codepogingen. Wacht een kwartier en log dan opnieuw in.';
            } else {
                $code = (string)($_POST['code'] ?? '');
                if ($actie === 'herstel')   $ok = tweestaps_herstel_gebruiken($wacht_b, $code);
                elseif ($methode === 'app') $ok = tweestaps_totp_gebruiken($wacht_b, $code);
                else                        $ok = tweestaps_mailcode_gebruiken($wacht_b, $code);

                if ($ok) {
                    if ($actie === 'herstel') {
                        $over = tweestaps_herstel_over(tweestaps_rij((int)$wacht_b['id']) ?? []);
                        audit_log('tweestaps_herstelcode', 'beheerder', (int)$wacht_b['id'], ['over' => $over]);
                        flash($over <= 2 ? 'waarschuwing' : 'info', "Je hebt een herstelcode gebruikt; er zijn er nog {$over}. Stel in Mijn profiel de tweestapsverificatie opnieuw in voor nieuwe codes.");
                    }
                    tweestaps_wacht_stop();
                    login_pogingen_wissen($email, $ip);
                    login_beheerder($wacht_b, 'wachtwoord');
                    redirect(toegang_veilige_terug($terug));
                }
                login_poging_registreren($email, $ip);
                audit_log('tweestaps_mislukt', 'beheerder', (int)$wacht_b['id'], ['methode' => $actie === 'herstel' ? 'herstelcode' : $methode]);
                sleep(1);
                if ($actie === 'herstel') $herstel_fout = 'Deze herstelcode klopt niet of is al gebruikt.';
                else $code_fout = 'Deze code klopt niet of is verlopen.';
            }
        }
    }
}

$methode = $wacht_b ? tweestaps_methode($wacht_b) : '';
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
.sso-icoon { width:24px;height:24px;border-radius:5px;background:var(--accent);display:flex;align-items:center;justify-content:center;color:var(--op-vol);font-size:13px;font-weight:700;flex-shrink:0; }
.scheiding { display:flex;align-items:center;gap:10px;margin:16px 0;color:var(--grijs);font-size:12px; }
.scheiding::before,.scheiding::after { content:'';flex:1;height:1px;background:var(--rand); }
.login-knop { width:100%;justify-content:center; }
.login-voet { text-align:center;font-size:12px;color:var(--grijs);margin-top:16px; }
.login-stap { display:flex;flex-direction:column;gap:12px;margin-top:12px; }
.login-stap details { font-size:13px; }
.login-stap summary { cursor:pointer;color:var(--navy); }
.login-stap details form { margin-top:10px; }
</style>
</head>
<body>
<div class="login-wrapper">
  <div class="login-kaart">
    <div class="login-logo">
      <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Paal 13"
           onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/logo.svg'">
      <h1><?= $wacht_b ? 'Tweede stap' : 'Beheerders login' ?></h1>
      <p>Reddingsbrigade Paal 13</p>
    </div>

    <?php if ($fout): ?>
    <div class="ds-melding ds-melding-error" role="alert"><?= h($fout) ?></div>
    <?php endif; ?>
    <?php toon_flash(); ?>

    <?php if ($wacht_b): ?>
      <?php $_mail = tweestaps_wacht_mail(); $_adres = tweestaps_email_maskeer((string)$wacht_b['email']); ?>
      <?php if ($methode === 'mail'): ?>
        <?php if ($_mail === 'mislukt'): ?>
          <div class="ds-melding ds-melding-error" role="alert">De code kon niet worden verstuurd. Probeer het zo opnieuw of vraag een beheerder.</div>
        <?php elseif ($_mail === 'limiet'): ?>
          <div class="ds-melding ds-melding-warn">Er zijn dit uur al te veel codes gestuurd naar <?= h($_adres) ?>. Gebruik de laatste die je kreeg, of probeer het later opnieuw.</div>
        <?php elseif ($_mail === 'te_snel'): ?>
          <div class="ds-melding ds-melding-info">Er ging net al een code naar <?= h($_adres) ?>. Gebruik die; hij is <?= TWEESTAPS_MAIL_MIN ?> minuten geldig.</div>
        <?php else: ?>
          <div class="ds-melding ds-melding-info">We hebben een code gestuurd naar <?= h($_adres) ?>. Hij is <?= TWEESTAPS_MAIL_MIN ?> minuten geldig en werkt één keer.</div>
        <?php endif; ?>
      <?php else: ?>
        <p class="ds-veld-hint">Open je authenticator-app en vul de code van 6 cijfers in die bij Paal13 Beheer staat.</p>
      <?php endif; ?>

      <div class="login-stap">
        <form method="POST" novalidate>
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="code">
          <?php $tsv = [
              'id' => 'code', 'label' => $methode === 'app' ? 'Code uit je app' : 'Code uit de e-mail',
              'autofocus' => true, 'fout' => $code_fout,
          ]; require __DIR__ . '/_tweestaps_codeveld.php'; ?>
          <button type="submit" class="btn btn-primary btn-lg login-knop">Inloggen</button>
        </form>

        <?php if ($methode === 'mail'): ?>
          <form method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="actie" value="nieuwe_mail">
            <button type="submit" class="btn btn-outline login-knop">Stuur een nieuwe code</button>
          </form>
          <p class="ds-veld-hint">
            Sneller en veiliger: stel na het inloggen een authenticator-app in via
            Mijn profiel &rsaquo; Tweestapsverificatie. Dan heb je deze e-mail niet meer nodig.
          </p>
        <?php else: ?>
          <details<?= $herstel_fout !== '' ? ' open' : '' ?>>
            <summary>Telefoon kwijt? Gebruik een herstelcode</summary>
            <form method="POST" novalidate>
              <?= csrf_input() ?>
              <input type="hidden" name="actie" value="herstel">
              <?php $tsv = [
                  'id' => 'herstelcode', 'label' => 'Herstelcode', 'herstel' => true, 'fout' => $herstel_fout,
                  'hint' => 'Een van de codes die je bij het instellen kreeg, bijvoorbeeld ABCDE-FGH23. Elke code werkt één keer.',
              ]; require __DIR__ . '/_tweestaps_codeveld.php'; ?>
              <button type="submit" class="btn btn-outline login-knop">Inloggen met herstelcode</button>
            </form>
          </details>
        <?php endif; ?>

        <form method="POST">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="annuleren">
          <button type="submit" class="btn btn-outline login-knop">Annuleren</button>
        </form>
      </div>
    <?php else: ?>

    <?php if (authentik_actief()): ?>
    <a href="<?= APP_URL ?>/admin/auth_sso.php<?= $terug ? '?terug=' . urlencode($terug) : '' ?>" class="sso-btn">
      <div class="sso-icoon">A</div>
      Inloggen via Authentik (SSO)
    </a>
    <div class="scheiding">of met wachtwoord</div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="wachtwoord">
      <?php if ($terug): ?><input type="hidden" name="terug" value="<?= h($terug) ?>"><?php endif; ?>
      <div class="ds-veld">
        <label for="email">E-mailadres</label>
        <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus autocomplete="email" placeholder="jou@paal13.com">
      </div>
      <div class="ds-veld">
        <label for="wachtwoord">Wachtwoord</label>
        <input type="password" id="wachtwoord" name="wachtwoord" required autocomplete="current-password" placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-primary btn-lg login-knop">
        Inloggen
      </button>
    </form>

    <p class="login-voet">
      Geen account? Vraag een admin om toegang.
    </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

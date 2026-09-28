<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/blk.php';
sessie_start();

$stap = $_GET['stap'] ?? 'login';
$fout = '';
$fout_kop = '';   // korte kop boven een codefout ("Code onjuist." / "Code niet compleet.")

/* "Blijf ingelogd": is er een geldig apparaat-token, dan hoeft er niets gevraagd te
   worden. Moet vóór de ingelogd-check, anders staat de sollicitant alsnog op het
   inlogscherm terwijl zijn telefoon hem kent. */
if (!portaal_ingelogd() && function_exists('portaal_onthoud_controleren')) {
    portaal_onthoud_controleren();
}
if (portaal_ingelogd()) $stap = 'dossier';

/* ── Inloglink uit de mail of WhatsApp ────────────────────────
   De GET verbruikt het token bewust NIET: mailscanners bezoeken links vooraf, en dan
   zou de link op zijn voordat de sollicitant klikt. Hier wordt alleen gekeken of hij
   geldig is; de knop op dat scherm post en verzilvert hem dan. */
if (!portaal_ingelogd() && isset($_GET['inlog'])) {
    $link_email = portaal_inloglink_controleren((string)$_GET['inlog']);
    if ($link_email) {
        $stap = 'inloglink';
        $link_token = (string)$_GET['inlog'];
        $lv = db()->prepare("SELECT voornaam FROM personen WHERE email=? LIMIT 1");
        $lv->execute([$link_email]);
        $link_voornaam = (string)($lv->fetchColumn() ?: '');
    } else {
        $stap = 'login';
        $fout = 'Deze inloglink werkt niet meer. Vraag hieronder een nieuwe aan — dat kost een paar seconden.';
    }
}

// De knop op dat scherm: nu pas verzilveren en inloggen.
if (!portaal_ingelogd() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inloglink_submit'])) {
    csrf_check();
    $link_email = portaal_inloglink_controleren((string)($_POST['inlog_token'] ?? ''), true);
    if (!$link_email) {
        $stap = 'login';
        $fout = 'Deze inloglink werkt niet meer. Vraag hieronder een nieuwe aan.';
    } else {
        $st = db()->prepare("SELECT s.id FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE p.email=? ORDER BY s.aangemeld_op DESC LIMIT 1");
        $st->execute([$link_email]);
        $sol_link = $st->fetch();
        if ($sol_link) {
            login_portaal((int)$sol_link['id'], $link_email);
            if (!empty($_POST['onthoud'])) portaal_onthoud_zetten((int)$sol_link['id'], $link_email);
            unset($_SESSION['otp_email']);
            redirect(APP_URL . '/portaal/');
        }
        $stap = 'login';
        $fout = 'Geen sollicitatie gevonden bij dit adres.';
    }
}

// ── OTP/SSO stap 1: email-submit ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_submit'])) {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fout = 'Vul een geldig e-mailadres in.';
    } else {
        // Probeer Authentik SSO: als email daar bestaat, toon een tussenscherm
        // ("je hebt al een account, we sturen je door") met een uitweg naar de code.
        $authentik_user = authentik_email_bestaat($email);
        if ($authentik_user) {
            $_SESSION['otp_email'] = $email; // wordt door auth_sso.php gebruikt
            $_SESSION['doorsturen_email'] = $email;
            redirect(APP_URL . '/portaal/?stap=doorsturen');
        }

        // Fallback: OTP code
        $p = db()->prepare("SELECT p.voornaam, p.telefoon FROM personen p JOIN sollicitaties s ON s.persoon_id=p.id WHERE p.email=? LIMIT 1");
        $p->execute([$email]); $persoon = $p->fetch();
        // Per adres: maximaal 10 code-aanvragen per uur, ongeacht het e-mailadres. De throttle
        // hieronder is per e-mailadres en houdt dus niemand tegen die veel adressen afgaat.
        $ip_ok = !function_exists('rate_limit') || rate_limit('otp_aanvraag', rate_identiteit(), 10, 3600);
        if ($persoon && $ip_ok) {
            // Atomische throttle: is er afgelopen 60s al een actieve OTP gegenereerd?
            $rl = db()->prepare("SELECT 1 FROM otp_tokens WHERE email=? AND type='portaal' AND gebruikt=0 AND aangemaakt_op > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
            $rl->execute([$email]);
            $recent_otp = (bool)$rl->fetchColumn();

            if (!$recent_otp) {
                try {
                    $code = otp_genereer($email, 'portaal');
                    // De link gaat mee in hetzelfde bericht als de code: één tik in
                    // plaats van zes cijfers overtypen, met de code eronder voor wie
                    // liever typt of de link niet kan openen.
                    $link = portaal_inloglink_maken($email);
                    portaal_code_versturen($email, $persoon['voornaam'], $code, $persoon['telefoon'] ?? null, $link);
                } catch (\Throwable $e) { error_log('portaal login otp: ' . $e->getMessage()); }
            }
            // Als wel recent: bestaande code blijft geldig, geen nieuwe mail
        }
        $_SESSION['otp_email'] = $email;
        redirect(APP_URL . '/portaal/?stap=code');
    }
}

// ── Vanaf het doorstuur-scherm: toch een code i.p.v. het account ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toch_code'])) {
    csrf_check();
    $email = strtolower(trim($_SESSION['doorsturen_email'] ?? ''));
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $p = db()->prepare("SELECT p.voornaam, p.telefoon FROM personen p JOIN sollicitaties s ON s.persoon_id=p.id WHERE p.email=? LIMIT 1");
        $p->execute([$email]); $persoon = $p->fetch();
        if ($persoon) {
            $rl = db()->prepare("SELECT 1 FROM otp_tokens WHERE email=? AND type='portaal' AND gebruikt=0 AND aangemaakt_op > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
            $rl->execute([$email]); $recent_otp = (bool)$rl->fetchColumn();
            if (!$recent_otp) {
                try {
                    $code = otp_genereer($email, 'portaal');
                    $link = portaal_inloglink_maken($email);
                    portaal_code_versturen($email, $persoon['voornaam'], $code, $persoon['telefoon'] ?? null, $link);
                } catch (\Throwable $e) { error_log('portaal login otp: ' . $e->getMessage()); }
            }
        }
        $_SESSION['otp_email'] = $email;
        unset($_SESSION['doorsturen_email']);
        redirect(APP_URL . '/portaal/?stap=code');
    }
    redirect(APP_URL . '/portaal/');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code_submit'])) {
    csrf_check();
    $email = $_SESSION['otp_email'] ?? '';
    // 'code' vult JS in; zonder JavaScript komen de zes losse vakjes als code_cijfer[] binnen.
    $code_ruw = (string)($_POST['code'] ?? '');
    if ($code_ruw === '') $code_ruw = implode('', array_map('strval', (array)($_POST['code_cijfer'] ?? [])));
    $code  = preg_replace('/\D/', '', $code_ruw);
    if (!$email) redirect(APP_URL . '/portaal/');
    if (strlen($code) !== 6) { $fout_kop = 'Code niet compleet.'; $fout = 'Vul alle zes cijfers van de code in.'; $stap = 'code'; }
    elseif (!otp_verifieer($email, $code, 'portaal')) { $fout_kop = 'Code onjuist.'; $fout = 'Deze code klopt niet of is verlopen. Controleer de cijfers, of laat hieronder een nieuwe sturen.'; $stap = 'code'; }
    else {
        $st = db()->prepare("SELECT s.id FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE p.email=? ORDER BY s.aangemeld_op DESC LIMIT 1");
        $st->execute([$email]); $sol = $st->fetch();
        if ($sol) {
            login_portaal((int)$sol['id'], $email);
            if (!empty($_POST['onthoud'])) portaal_onthoud_zetten((int)$sol['id'], $email);
            unset($_SESSION['otp_email']);
            redirect(APP_URL . '/portaal/');
        }
        else { $fout = 'Geen sollicitatie gevonden.'; $stap = 'login'; }
    }
}

if (isset($_GET['uitloggen'])) {
    // Uitloggen moet ook het apparaat-token intrekken; anders ben je bij de volgende
    // pagina gewoon weer ingelogd en lijkt uitloggen stuk.
    if (function_exists('portaal_onthoud_intrekken_dit')) portaal_onthoud_intrekken_dit();
    unset($_SESSION['portaal_sol_id'], $_SESSION['portaal_email'], $_SESSION['portaal_via_apparaat']);
    redirect(APP_URL . '/portaal/');
}

// Oude links naar het Taken-tabblad (?tab=taken) → eigen pagina
if ($stap === 'dossier' && ($_GET['tab'] ?? '') === 'taken') redirect(APP_URL . '/portaal/taken.php');

// ── Dossier data ──────────────────────────────────────────────
$sol = $blk = null;
if ($stap === 'dossier') {
    $sol_id = (int)$_SESSION['portaal_sol_id'];
    $sol = portaal_sollicitant_laden($sol_id);
    if (!$sol) { unset($_SESSION['portaal_sol_id']); redirect(APP_URL . '/portaal/'); }
    // BLK-rij (voor de taakstatus; het formulier zelf staat op blk_invullen.php)
    $blk = blk_van($sol_id);
}

// ── AI-beschikbaarheid: vrije tekst → formulierdata (experimenteel) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_besch_submit']) && portaal_ingelogd()) {
    header('Content-Type: application/json');
    csrf_check();
    if (!function_exists('ai_besch_actief') || !ai_besch_actief()) {
        echo json_encode(['status' => 'uit']); exit;
    }
    // Rate-limit: max 8 verzoeken per 10 minuten per sessie
    $nu = time();
    $_SESSION['ai_besch_hits'] = array_filter($_SESSION['ai_besch_hits'] ?? [], fn($t) => $t > $nu - 600);
    if (count($_SESSION['ai_besch_hits']) >= 8) {
        echo json_encode(['status' => 'fout', 'bericht' => 'Even rustig aan — probeer het over een paar minuten weer.']); exit;
    }
    $_SESSION['ai_besch_hits'][] = $nu;

    $seizoen_ai = actief_seizoen();
    $weken_geldig = array_keys(function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen_ai) : []);
    $res = ai_besch_verwerk($_POST['tekst'] ?? '', $weken_geldig, $seizoen_ai);
    // Log de interactie voor latere referentie (originele tekst + resultaat)
    if (function_exists('ai_besch_log')) {
        ai_besch_log((int)($_SESSION['portaal_sol_id'] ?? 0), $_POST['tekst'] ?? '', $res);
    }
    echo json_encode($res);
    exit;
}

// ── Taak-acties (oudergegevens, afvinken) — vóór alle HTML ──
if ($stap === 'dossier') portaal_taken_post_afhandelen((int)$_SESSION['portaal_sol_id']);

// ── Gedeelde kop (topbar + head). Inlogschermen: kaal (geen topbar, geen nav). ──
$portaal_titel = 'Start';
$portaal_nav   = 'start';
if ($stap !== 'dossier') { $portaal_geen_nav = true; $portaal_geen_topbar = true; }
else { $portaal_status_fase = $sol['fase']; }
require __DIR__ . '/_kop.php';
?>

  <?php // De melding van de vorige actie toont _kop.php (toon_flash). ?>
  <?php if ($fout && !in_array($stap, ['code', 'login'], true)): // inlog- en codescherm tonen de fout zelf, bij het veld ?>
  <div class="ds-melding ds-melding-error flash" role="alert"><?= icoon('let-op', 18) ?><span><?= h($fout) ?></span></div><?php endif; ?>

<?php if ($stap === 'inloglink'): ?>
  <?php /* De link uit de mail of WhatsApp. Eén knop en je bent binnen — maar wel een
           knop: een mailscanner die de link vooraf opent mag hem niet opmaken, en
           inloggen hoort een handeling te zijn, geen bijwerking van het bezoeken van
           een URL. */ ?>
  <div class="ds-login">
    <div class="ds-login-kop">
      <div class="ds-login-logo"><?= icoon('golf', 26) ?></div>
      <h1><?= $link_voornaam !== '' ? 'Hoi ' . h($link_voornaam) . '!' : 'Welkom terug' ?></h1>
      <p>Je bent één tik verwijderd van je portaal.</p>
    </div>
    <form method="POST" class="ds-login-form">
      <?= csrf_input() ?>
      <input type="hidden" name="inloglink_submit" value="1">
      <input type="hidden" name="inlog_token" value="<?= h($link_token) ?>">
      <label class="pt-onthoud">
        <input type="checkbox" name="onthoud" value="1">
        <span>
          <strong>Blijf ingelogd op dit apparaat</strong>
          <span class="pt-onthoud-uitleg">Dan hoef je dit <?= (int)PORTAAL_ONTHOUD_DAGEN ?> dagen niet meer te doen.
          Doe dit niet op een gedeelde of geleende telefoon.</span>
        </span>
      </label>
      <button type="submit" class="ds-btn ds-btn-primair ds-btn-vol">Open mijn portaal</button>
    </form>
    <div class="ds-login-voet">
      Niet jij? <a href="<?= APP_URL ?>/portaal/">Log in met een ander adres</a>
    </div>
  </div>

<?php elseif ($stap === 'login'): ?>
  <div class="ds-login">
    <div class="ds-login-kop">
      <div class="ds-login-logo"><?= icoon('golf', 26) ?></div>
      <h1>Welkom terug</h1>
      <p>Vul je e-mailadres in. <?= authentik_actief()
        ? 'Heb je al een Paal 13-account, dan sturen we je automatisch door. Anders krijg je een inloglink.'
        : 'Je krijgt een inloglink — één tik en je bent binnen.' ?></p>
    </div>
    <?php
      // Fout over het ingevulde adres → bij het veld (aria-invalid); andere fouten (verlopen link) alleen als melding.
      $email_fout = !empty($fout) && isset($_POST['email_submit']);
      // Terug via "adres opnieuw invoeren": het vorige adres staat er al, zodat je alleen een typfout hoeft te verbeteren.
      $email_waarde = (string)($_POST['email'] ?? ($_SESSION['otp_email'] ?? ''));
    ?>
    <?php if (!empty($fout)): ?>
    <div class="ds-melding ds-melding-error pt-login-fout" id="loginFout" role="alert"><?= icoon('let-op', 18) ?> <div><?= h($fout) ?></div></div>
    <?php endif; ?>
    <form method="POST" class="ds-login-form">
      <?= csrf_input() ?>
      <input type="hidden" name="email_submit" value="1">
      <div class="ds-veld">
        <label class="ds-label" for="loginEmail">E-mailadres</label>
        <input type="email" id="loginEmail" name="email" required autofocus autocomplete="email" class="ds-input"
               value="<?= h($email_waarde) ?>" placeholder="jou@voorbeeld.nl"
               aria-describedby="loginEmailHint<?= $email_fout ? ' loginFout' : '' ?>"<?= $email_fout ? ' aria-invalid="true"' : '' ?>>
        <div class="ds-veld-hint" id="loginEmailHint">Waarmee je je hebt aangemeld bij Paal 13</div>
      </div>
      <button type="submit" class="ds-btn ds-btn-primair ds-btn-vol">Doorgaan</button>
    </form>
    <div class="ds-login-voet">
      Nog niet aangemeld? <a href="<?= APP_URL ?>/aanmelden.php">Meld je aan</a>
    </div>
  </div>

<?php elseif ($stap === 'code'): ?>
  <div class="ds-login">
    <div class="ds-login-kop">
      <div class="ds-login-logo"><?= icoon('mail', 26) ?></div>
      <h1>Kijk in je berichten</h1>
      <p>We stuurden een <strong>inloglink</strong> naar <?= h($_SESSION['otp_email'] ?? '') ?>.
         Eén tik en je bent binnen.</p>
      <?php /* Niets verklappen: deze tekst staat er altijd, of het adres nu bekend is of niet. */ ?>
      <p class="pt-code-hint">Niets ontvangen? Controleer of je het adres goed hebt getypt en kijk in je spam.
         <a href="<?= APP_URL ?>/portaal/">Adres opnieuw invoeren</a></p>
      <p class="pt-code-uitleg" id="codeLbl">Liever typen? De code uit datzelfde bericht kan hieronder.</p>
    </div>
    <?php /* De foutplek staat er altijd (leeg en verborgen), zodat de groep er met aria-describedby
             naar kan wijzen en JS er dezelfde plek voor gebruikt. */ ?>
    <div class="ds-melding ds-melding-error pt-login-fout" id="codeFout" role="alert"<?= empty($fout) ? ' hidden' : '' ?>><?php if (!empty($fout)): ?><?= icoon('let-op', 18) ?> <div><?php if ($fout_kop !== ''): ?><strong><?= h($fout_kop) ?></strong> <?php endif; ?><?= h($fout) ?></div><?php endif; ?></div>
    <form method="POST" id="codeForm" class="ds-login-form">
      <?= csrf_input() ?>
      <input type="hidden" name="code_submit" value="1">
      <input type="hidden" name="code" id="codeHidden">
      <?php /* Zes vakjes = één veld: één groep met een naam, elk vakje zegt welk cijfer het is.
               Zonder JavaScript gaan de vakjes als code_cijfer[] mee; de server plakt ze aan elkaar. */ ?>
      <div class="ds-code-inputs" role="group" aria-labelledby="codeLbl" aria-describedby="codeFout">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <?php /* Vakje 1 zonder maxlength: de telefoon vult daar de hele code in (one-time-code); het script verdeelt hem. */ ?>
        <input type="text" inputmode="numeric" pattern="[0-9]*"<?= $i === 0 ? '' : ' maxlength="1"' ?> class="code-cijfer ds-code-cijfer"
               name="code_cijfer[]" aria-label="Cijfer <?= $i + 1 ?> van 6"<?= !empty($fout) ? ' aria-invalid="true"' : '' ?>
               autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>">
        <?php endfor; ?>
      </div>
      <label class="pt-onthoud">
        <input type="checkbox" name="onthoud" value="1">
        <span>
          <strong>Blijf ingelogd op dit apparaat</strong>
          <span class="pt-onthoud-uitleg">Dan hoef je dit <?= (int)PORTAAL_ONTHOUD_DAGEN ?> dagen niet meer te doen.
          Doe dit niet op een gedeelde of geleende telefoon.</span>
        </span>
      </label>
      <?php /* Nooit disabled (CLAUDE.md, actie.js): de knop werkt altijd; een onvolledige code
               meldt de controle hieronder in de foutplek. */ ?>
      <button type="submit" class="ds-btn ds-btn-primair ds-btn-vol" id="submitBtn">Inloggen</button>
    </form>
    <?php /* Opnieuw sturen: een gewoon formulier, dus ook zonder JavaScript bruikbaar. De server
             stuurt binnen 60 seconden geen tweede code; de teller maakt dat alleen zichtbaar. */ ?>
    <form method="POST" class="ds-login-voet" id="opnieuwForm">
      <?= csrf_input() ?>
      <input type="hidden" name="email_submit" value="1">
      <input type="hidden" name="email" value="<?= h($_SESSION['otp_email'] ?? '') ?>">
      Geen mail ontvangen? <button type="submit" class="ds-link-knop" id="opnieuwBtn">Opnieuw sturen<span id="teller"></span></button>
      <br><a href="<?= APP_URL ?>/portaal/"><?= icoon('terug', 14) ?> Ander e-mailadres</a>
    </form>
  </div>
  <script>
  (function () {
    var inp = document.querySelectorAll('.code-cijfer'), verborgen = document.getElementById('codeHidden');
    var form = document.getElementById('codeForm'), knop = document.getElementById('submitBtn'), foutPlek = document.getElementById('codeFout');
    function code() { return Array.prototype.map.call(inp, function (i) { return i.value; }).join(''); }
    function upd() { var c = code(); verborgen.value = c; if (c.length === 6) knop.focus(); }
    // Meer cijfers tegelijk (plakken, of de telefoon die de code uit de sms invult): over de vakjes verdelen.
    function verdeel(v, vanaf) {
      v.split('').slice(0, 6 - vanaf).forEach(function (c, j) { inp[vanaf + j].value = c; });
      inp[Math.min(vanaf + v.length, 5)].focus(); upd();
    }
    inp.forEach(function (el, i) {
      el.addEventListener('input', function () {
        var v = el.value.replace(/\D/g, '');
        if (v.length > 1) { verdeel(v, i); return; }
        el.value = v; if (v && i < 5) inp[i + 1].focus(); upd();
      });
      el.addEventListener('keydown', function (e) { if (e.key === 'Backspace' && !el.value && i > 0) inp[i - 1].focus(); });
      el.addEventListener('paste', function (e) {
        e.preventDefault();
        verdeel((e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6), 0);
      });
    });
    form.addEventListener('submit', function (e) {
      upd();
      if (code().length === 6) return;
      e.preventDefault();
      var leeg = Array.prototype.filter.call(inp, function (i) { return !i.value; })[0] || inp[0];
      var tekst = 'Code niet compleet. Vul alle zes cijfers van de code in.';
      if (window.P13Melding) P13Melding.fout(foutPlek, tekst, leeg);
      else { foutPlek.textContent = tekst; foutPlek.hidden = false; leeg.focus(); }
    });
    inp[0].focus();

    // Opnieuw sturen: 60 seconden aftellen. aria-disabled i.p.v. disabled, zodat de knop
    // vindbaar blijft voor een schermlezer en het formulier niet stil vastzit.
    var t = 60, teller = document.getElementById('teller'), opnieuw = document.getElementById('opnieuwBtn');
    opnieuw.setAttribute('aria-disabled', 'true');
    var mmss = function (s) { return ' (' + Math.floor(s / 60) + ':' + (s % 60 < 10 ? '0' : '') + (s % 60) + ')'; };
    teller.textContent = mmss(t);
    document.getElementById('opnieuwForm').addEventListener('submit', function (e) {
      if (opnieuw.getAttribute('aria-disabled') === 'true') e.preventDefault();
    });
    var iv = setInterval(function () {
      t--;
      if (t <= 0) { clearInterval(iv); opnieuw.removeAttribute('aria-disabled'); teller.textContent = ''; }
      else teller.textContent = mmss(t);
    }, 1000);
  })();
  </script>

<?php elseif ($stap === 'doorsturen'): ?>
  <?php $dse = $_SESSION['doorsturen_email'] ?? ''; ?>
  <div class="ds-login">
    <div class="ds-login-kop">
      <div class="ds-login-logo"><?= icoon('slot', 26) ?></div>
      <h1>Je hebt al een account</h1>
      <p>Je logt in met het Paal 13-account van <strong><?= h($dse) ?></strong>, zoals je gewend bent.</p>
    </div>
    <a href="<?= APP_URL ?>/portaal/auth_sso.php" class="ds-btn ds-btn-primair ds-btn-vol" id="dsNu">Inloggen met je Paal 13-account</a>
    <form method="POST" class="pt-login-los">
      <?= csrf_input() ?>
      <input type="hidden" name="toch_code" value="1">
      <div class="ds-login-voet">
        Geen account meer? <button type="submit" class="ds-link-knop">Stuur mij toch een code</button>
      </div>
    </form>
  </div>

<?php endif; ?>

<?php if ($stap === 'dossier' && $sol):
  // ── Startpagina: status + info bovenaan, daaronder beperkt de open taken (ontwerp 02) ──
  $fase       = $sol['fase'];
  $taken      = portaal_taken($sol, $blk);
  $open_taken = portaal_taken_open($taken);
  $wacht_taken= portaal_taken_wachtend($taken);
  $n_open     = count($open_taken);
  $vg         = portaal_voortgang($fase);
  $begroet    = portaal_begroeting($sol, $n_open);
  $eindfase   = portaal_is_eindfase($fase);   // afgewezen, gestopt, zelf verwijderd: _start_info toont de eindstand
  $aangenomen = ($fase === 'aangenomen');
  $wachtend   = in_array($fase, ['selectiedag_geweest', 'tw_geweest'], true);
  $start_max  = 3;   // meer open taken → "Alle (N) taken tonen" naar het Taken-tabblad
  $taak_terug = 'start';
  $portaal_open_taken = $n_open;
?>
<div class="pt-stapel">

  <?php if (!$aangenomen): ?>
  <div class="pt-begroet">
    <h1><?= h($begroet['titel']) ?></h1>
    <?php if ($begroet['tekst'] !== ''): ?><p><?= h($begroet['tekst']) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($aangenomen): ?>
  <div class="pt-melding ok">
    <?= icoon('vink-cirkel', 20) ?>
    <div class="pt-melding-tekst">
      <span class="pt-melding-titel"><?= h($begroet['titel']) ?></span>
      <span class="pt-melding-body"><?= h($begroet['tekst']) ?></span>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($vg):
    $vg_toelichting = portaal_voortgang_toelichting($fase);
    $vg_open = count($open_taken);   // "Stap 2 van 5 — nu even niets te doen"
    require __DIR__ . '/_voortgang.php';
  endif; ?>

  <div class="pt-start-grid">
    <?php require __DIR__ . '/_start_info.php'; ?>

    <?php if (!$eindfase): ?>
    <div class="pt-stapel pt-start-taken">
      <?php if ($aangenomen && $taken): $n_klaar = count($taken) - $n_open; ?>
        <div class="pt-sectiekop"><span>Takenpakket</span><span class="pt-sectiekop-teller"><?= $n_klaar ?> van <?= count($taken) ?> gedaan</span></div>
        <?php $lijst = $taken; $taak_knop = true; require __DIR__ . '/_takenlijst.php'; ?>

      <?php elseif ($n_open > 0): ?>
        <div class="pt-sectiekop"><span>Nu te doen</span><span class="pt-sectiekop-teller open"><?= $n_open ?> open</span></div>
        <?php $lijst = array_slice($open_taken, 0, $start_max); $taak_knop = true; require __DIR__ . '/_takenlijst.php'; ?>
        <?php if ($n_open > $start_max): ?>
          <a href="<?= APP_URL ?>/portaal/taken.php" class="pt-meer">Alle (<?= $n_open ?>) taken tonen</a>
        <?php endif; ?>
        <?php // Hoofdactie: de eerste open taak met een link (het ouderformulier staat inline)
          $cta_taak = null;
          foreach ($open_taken as $t) { if (!empty($t['url'])) { $cta_taak = $t; break; } }
          if ($cta_taak): $cta = $cta_taak['id'] === 'blk' ? 'Formulier invullen' : $cta_taak['titel']; ?>
          <a href="<?= h($cta_taak['url']) ?>" class="ds-btn ds-btn-primair ds-btn-vol pt-cta"><?= h($cta) ?> <?= icoon('pijl-rechts', 18) ?></a>
        <?php endif; ?>

      <?php else: ?>
        <?php
          $leeg_groot = $wachtend;
          $_lt        = portaal_leeg_tekst($sol);
          $leeg_titel = $_lt['titel'];
          $leeg_tekst = $_lt['tekst'];
          $leeg_knop  = null;
          foreach ($taken as $t) { if ($t['id'] === 'besch' && $t['done']) { $leeg_knop = ['url' => APP_URL . '/portaal/beschikbaarheid.php', 'label' => 'Mijn beschikbaarheid bekijken']; } }
          require __DIR__ . '/_leeg.php';
        ?>
        <?php if ($wachtend): ?>
          <span class="pt-hulp">Vragen? Mail <a href="mailto:<?= h(portaal_contact_email()) ?>"><?= h(portaal_contact_email()) ?></a></span>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($wacht_taken && !$aangenomen): ?>
        <div class="pt-sectiekop"><span><?= h(portaal_taken_wacht_titel($wacht_taken)) ?></span></div>
        <?php $lijst = $wacht_taken; require __DIR__ . '/_takenlijst.php'; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

</div>
<?php endif; ?>

<?php require __DIR__ . '/_voet.php'; ?>

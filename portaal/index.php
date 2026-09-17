<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/blk.php';
sessie_start();

$stap = $_GET['stap'] ?? 'login';
$fout = '';
if (portaal_ingelogd()) $stap = 'dossier';

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
        if ($persoon) {
            // Atomische throttle: is er afgelopen 60s al een actieve OTP gegenereerd?
            $rl = db()->prepare("SELECT 1 FROM otp_tokens WHERE email=? AND type='portaal' AND gebruikt=0 AND aangemaakt_op > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
            $rl->execute([$email]);
            $recent_otp = (bool)$rl->fetchColumn();

            if (!$recent_otp) {
                $code = otp_genereer($email, 'portaal');
                portaal_code_versturen($email, $persoon['voornaam'], $code, $persoon['telefoon'] ?? null);
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
                $code = otp_genereer($email, 'portaal');
                portaal_code_versturen($email, $persoon['voornaam'], $code, $persoon['telefoon'] ?? null);
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
    $code  = preg_replace('/\D/', '', $_POST['code'] ?? '');
    if (!$email) redirect(APP_URL . '/portaal/');
    if (strlen($code) !== 6) { $fout = 'Voer een 6-cijferige code in.'; $stap = 'code'; }
    elseif (!otp_verifieer($email, $code, 'portaal')) { $fout = 'Onjuiste of verlopen code.'; $stap = 'code'; }
    else {
        $st = db()->prepare("SELECT s.id FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE p.email=? ORDER BY s.aangemeld_op DESC LIMIT 1");
        $st->execute([$email]); $sol = $st->fetch();
        if ($sol) { login_portaal((int)$sol['id'], $email); unset($_SESSION['otp_email']); redirect(APP_URL . '/portaal/'); }
        else { $fout = 'Geen sollicitatie gevonden.'; $stap = 'login'; }
    }
}

if (isset($_GET['uitloggen'])) { unset($_SESSION['portaal_sol_id'], $_SESSION['portaal_email']); redirect(APP_URL . '/portaal/'); }

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

  <?php toon_flash(); ?>
  <?php if ($fout && $stap !== 'code'): // op het code-scherm toont dat scherm de fout zelf ?>
  <div class="flash flash-fout" style="margin-bottom:12px"><?= h($fout) ?></div><?php endif; ?>
  <?php if (!empty($_SESSION['portaal_flash'])): ?>
    <div class="flash flash-info" style="margin-bottom:12px"><?= h($_SESSION['portaal_flash']) ?></div>
    <?php unset($_SESSION['portaal_flash']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_portaal'])): ?>
    <div class="flash flash-succes" style="margin-bottom:12px"><?= h($_SESSION['flash_portaal']) ?></div>
    <?php unset($_SESSION['flash_portaal']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_portaal_fout'])): ?>
    <div class="flash flash-fout" style="margin-bottom:12px"><?= h($_SESSION['flash_portaal_fout']) ?></div>
    <?php unset($_SESSION['flash_portaal_fout']); ?>
  <?php endif; ?>

<?php if ($stap === 'login'): ?>
  <div class="ds-login">
    <div class="ds-login-kop">
      <div class="ds-login-logo"><?= icoon('golf', 26) ?></div>
      <h1>Welkom terug</h1>
      <p>Vul je e-mailadres in. <?= authentik_actief()
        ? 'Heb je al een Paal 13-account, dan sturen we je automatisch door. Anders krijg je een code van zes cijfers.'
        : 'Je krijgt een code van zes cijfers om in te loggen.' ?></p>
    </div>
    <form method="POST" class="ds-login-form" onsubmit="var b=this.querySelector('button[type=submit]'); b.disabled=true; b.innerHTML='Versturen…'; this.querySelector('input[name=email]').readOnly=true;">
      <?= csrf_input() ?>
      <input type="hidden" name="email_submit" value="1">
      <div class="ds-veld">
        <label class="ds-label">E-mailadres</label>
        <input type="email" name="email" required autofocus autocomplete="email" class="ds-input"
               value="<?= h($_POST['email'] ?? '') ?>" placeholder="jou@voorbeeld.nl">
        <div class="ds-veld-hint">Waarmee je je hebt aangemeld bij Paal 13</div>
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
      <h1>Code invoeren</h1>
      <p>We hebben zes cijfers gestuurd naar <strong><?= h($_SESSION['otp_email'] ?? '') ?></strong>. Check ook je spam-map.</p>
    </div>
    <?php if (!empty($fout)): ?>
    <div class="ds-melding ds-melding-error" style="margin-bottom:14px"><?= icoon('let-op', 18) ?> <div><strong>Code onjuist.</strong> <?= h($fout) ?></div></div>
    <?php endif; ?>
    <form method="POST" id="codeForm" class="ds-login-form">
      <?= csrf_input() ?>
      <input type="hidden" name="code_submit" value="1">
      <input type="hidden" name="code" id="codeHidden">
      <div class="ds-code-inputs">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="code-cijfer ds-code-cijfer"
               autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>">
        <?php endfor; ?>
      </div>
      <button type="submit" class="ds-btn ds-btn-primair ds-btn-vol" id="submitBtn" disabled>Inloggen</button>
    </form>
    <div class="ds-login-voet">
      Geen mail ontvangen? <button type="button" class="ds-link-knop" id="opnieuwBtn" disabled>Opnieuw sturen (<span id="teller">0:60</span>)</button>
      <br><a href="<?= APP_URL ?>/portaal/">← Ander e-mailadres</a>
    </div>
  </div>
  <form method="POST" id="opnieuwForm" style="display:none">
    <?= csrf_input() ?>
    <input type="hidden" name="email_submit" value="1">
    <input type="hidden" name="email" value="<?= h($_SESSION['otp_email'] ?? '') ?>">
  </form>
  <script>
  const inp = document.querySelectorAll('.code-cijfer'), h = document.getElementById('codeHidden'), b = document.getElementById('submitBtn');
  inp.forEach((el,i) => {
    el.addEventListener('input', () => { el.value=el.value.replace(/\D/g,'').slice(-1); if(el.value&&i<5) inp[i+1].focus(); upd(); });
    el.addEventListener('keydown', e => { if(e.key==='Backspace'&&!el.value&&i>0) inp[i-1].focus(); });
    el.addEventListener('paste', e => { e.preventDefault(); const v=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6); v.split('').forEach((c,j)=>{if(inp[j])inp[j].value=c;}); inp[Math.min(v.length,5)].focus(); upd(); });
  });
  function upd() { const c=Array.from(inp).map(i=>i.value).join(''); h.value=c; b.disabled=c.length!==6; if(c.length===6)b.focus(); }
  inp[0].focus();
  // Opnieuw-sturen teller: 60s aftellen, daarna knop actief
  (function(){
    var t = 60, el = document.getElementById('teller'), knop = document.getElementById('opnieuwBtn');
    var iv = setInterval(function(){
      t--;
      if (t <= 0) {
        clearInterval(iv);
        knop.disabled = false;
        knop.innerHTML = 'Opnieuw sturen';
        knop.addEventListener('click', function(){ document.getElementById('opnieuwForm').submit(); });
      } else {
        el.textContent = '0:' + (t < 10 ? '0' : '') + t;
      }
    }, 1000);
  })();
  </script>

<?php elseif ($stap === 'doorsturen'): ?>
  <?php $dse = $_SESSION['doorsturen_email'] ?? ''; ?>
  <div class="ds-login">
    <div class="ds-login-kop">
      <div class="ds-login-logo"><?= icoon('slot', 26) ?></div>
      <h1>Je hebt al een account</h1>
      <p>We sturen je door naar het Paal 13-account van <strong><?= h($dse) ?></strong>. Daar log je in zoals je gewend bent.</p>
    </div>
    <div class="ds-melding ds-melding-info" style="margin-bottom:16px">
      <?= icoon('klok', 18) ?> <div>Even geduld — je wordt doorgestuurd…</div>
    </div>
    <a href="<?= APP_URL ?>/portaal/auth_sso.php" class="ds-btn ds-btn-primair ds-btn-vol" id="dsNu">Nu doorgaan</a>
    <form method="POST" style="margin-top:14px">
      <?= csrf_input() ?>
      <input type="hidden" name="toch_code" value="1">
      <div class="ds-login-voet" style="margin-top:0">
        Geen account meer? <button type="submit" class="ds-link-knop">Stuur mij toch een code</button>
      </div>
    </form>
  </div>
  <script>
    setTimeout(function(){ window.location.href = document.getElementById('dsNu').href; }, 2500);
  </script>

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
  $afgewezen  = ($fase === 'afgewezen');
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

  <?php if ($vg): $vg_toelichting = portaal_voortgang_toelichting($fase); require __DIR__ . '/_voortgang.php'; endif; ?>

  <div class="pt-start-grid">
    <?php require __DIR__ . '/_start_info.php'; ?>

    <?php if (!$afgewezen): ?>
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
          $leeg_titel = 'Je bent helemaal bij';
          $leeg_tekst = $wachtend
            ? 'Niets te doen. Zodra er iets van je nodig is, verschijnt het hier en krijg je een bericht.'
            : 'Er staat niets open. Zodra er iets van je nodig is, verschijnt het hier.';
          $leeg_knop  = null;
          foreach ($taken as $t) { if ($t['id'] === 'besch' && $t['done']) { $leeg_knop = ['url' => APP_URL . '/portaal/beschikbaarheid.php', 'label' => 'Mijn beschikbaarheid bekijken']; } }
          require __DIR__ . '/_leeg.php';
        ?>
        <?php if ($wachtend): ?>
          <span class="pt-hulp">Vragen? Mail <a href="mailto:<?= h(portaal_contact_email()) ?>"><?= h(portaal_contact_email()) ?></a></span>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($wacht_taken && !$aangenomen): ?>
        <div class="pt-sectiekop"><span>Wacht op je ouder/verzorger</span></div>
        <?php $lijst = $wacht_taken; require __DIR__ . '/_takenlijst.php'; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

</div>
<?php endif; ?>

<?php require __DIR__ . '/_voet.php'; ?>

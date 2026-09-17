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

// ── Dossier data ──────────────────────────────────────────────
$sol = $blk = $besch = null;
$huidig_weken = [];
$alle_weken = [];
$maanden_labels = [];

if ($stap === 'dossier') {
    $sol_id = (int)$_SESSION['portaal_sol_id'];

    // Haal seizoen op, met graceful fallback als datum_werk kolommen ontbreken
    try {
        $stmt = db()->prepare("
            SELECT s.*, p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon, p.geboortedatum,
                   sz.naam sz_naam, sz.datum_selectiedag, sz.locatie_selectiedag,
                   sz.datum_tw_start, sz.datum_tw_eind, sz.locatie_tw,
                   sz.datum_werk_start, sz.datum_werk_eind
            FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id
            JOIN seizoenen sz ON sz.id=s.seizoen_id WHERE s.id=?
        ");
        $stmt->execute([$sol_id]); $sol = $stmt->fetch();
    } catch (Exception $e) {
        $stmt = db()->prepare("
            SELECT s.*, p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon, p.geboortedatum,
                   sz.naam sz_naam, sz.datum_selectiedag, sz.locatie_selectiedag,
                   sz.datum_tw_start, sz.datum_tw_eind, sz.locatie_tw,
                   NULL as datum_werk_start, NULL as datum_werk_eind
            FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id
            JOIN seizoenen sz ON sz.id=s.seizoen_id WHERE s.id=?
        ");
        $stmt->execute([$sol_id]); $sol = $stmt->fetch();
    }
    if (!$sol) { unset($_SESSION['portaal_sol_id']); redirect(APP_URL . '/portaal/'); }

    // BLK-rij (voor de taakstatus; het formulier zelf staat op blk_invullen.php)
    $blk = blk_van($sol_id);

    $bd = db()->prepare("SELECT * FROM beschikbaarheid WHERE sollicitatie_id=?");
    $bd->execute([$sol_id]); $besch = $bd->fetch();
    $huidig_weken = $besch ? (json_decode($besch['weken_json'] ?? '[]', true) ?: []) : [];

    // Weken genereren (seizoen-datums of fallback zomerweken NL)
    $week_start = $sol['datum_werk_start'] ?? null;
    $week_eind  = $sol['datum_werk_eind']  ?? null;
    if (!$week_start || !$week_eind) {
        // Fallback: week 22 t/m 35 (eind mei – begin sept)
        $jaar = (int)date('Y');
        $week_start = (new DateTime())->setISODate($jaar, 22, 1)->format('Y-m-d');
        $week_eind  = (new DateTime())->setISODate($jaar, 35, 7)->format('Y-m-d');
    }
    $dag = new DateTime($week_start);
    $eind = new DateTime($week_eind);
    while ($dag->format('N') != 1) $dag->modify('+1 day');
    while ($dag <= $eind) {
        $wn = (int)$dag->format('W');
        $m  = (int)$dag->format('n');
        $alle_weken[] = ['week' => $wn, 'maand' => $m,
            'label'   => 'Week ' . $wn,
            'periode' => $dag->format('j M') . '–' . (clone $dag)->modify('+6 days')->format('j M')];
        $maanden_labels[$m] = $dag->format('F Y');
        $dag->modify('+7 days');
    }
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

// ── Oudergegevens opslaan (minderjarigen) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ouder_submit']) && !isset($_POST['ond_id']) && portaal_ingelogd()) {
    csrf_check();
    $sol_id = (int)$_SESSION['portaal_sol_id'];
    $o_naam  = trim($_POST['ouder_naam'] ?? '');
    $o_email = trim($_POST['ouder_email'] ?? '');
    $o_tel   = trim($_POST['ouder_telefoon'] ?? '');
    // Telefoon: cijfers, spaties, +, -, () toegestaan; minstens 8 cijfers
    $tel_ok = preg_match_all('/\d/', $o_tel) >= 8;
    if ($o_naam && filter_var($o_email, FILTER_VALIDATE_EMAIL) && $tel_ok) {
        $opgeslagen = false;
        // Stap 1: gegevens opslaan (dit is de kern — moet lukken)
        try {
            if (function_exists('personen_ouder_auto_setup')) personen_ouder_auto_setup();
            // Zeker stellen dat de ouder-kolommen bestaan (auto-setup kan elders al gedraaid zijn of falen)
            try {
                $dbn = db()->query("SELECT DATABASE()")->fetchColumn();
                $kc = db()->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='personen' AND COLUMN_NAME IN ('ouder_naam','ouder_email','ouder_telefoon')");
                $kc->execute([$dbn]);
                $aanwezig = $kc->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('ouder_naam', $aanwezig))     db()->exec("ALTER TABLE `personen` ADD COLUMN `ouder_naam` VARCHAR(120) DEFAULT NULL");
                if (!in_array('ouder_email', $aanwezig))    db()->exec("ALTER TABLE `personen` ADD COLUMN `ouder_email` VARCHAR(255) DEFAULT NULL");
                if (!in_array('ouder_telefoon', $aanwezig)) db()->exec("ALTER TABLE `personen` ADD COLUMN `ouder_telefoon` VARCHAR(30) DEFAULT NULL");
            } catch (Exception $e) { error_log('ouder-kolommen aanmaken: ' . $e->getMessage()); }

            // Eerst het persoon_id ophalen, dan updaten (betrouwbaarder dan een subquery in de WHERE)
            $pid_q = db()->prepare("SELECT persoon_id FROM sollicitaties WHERE id=?");
            $pid_q->execute([$sol_id]);
            $persoon_id = (int)$pid_q->fetchColumn();
            if ($persoon_id > 0) {
                $upd = db()->prepare("UPDATE personen SET ouder_naam=?, ouder_email=?, ouder_telefoon=? WHERE id=?");
                $upd->execute([$o_naam, $o_email, $o_tel, $persoon_id]);
                $opgeslagen = true;
            } else {
                error_log('ouder_submit: geen persoon_id voor sollicitatie ' . $sol_id);
                $_SESSION['flash_portaal_fout'] = 'Er ging iets mis bij het opslaan (kon je gegevens niet koppelen). Neem contact op.';
            }
        } catch (Exception $e) {
            error_log('ouder_submit opslaan: ' . $e->getMessage());
            $_SESSION['flash_portaal_fout'] = 'Er ging iets mis bij het opslaan van de gegevens. Probeer het later opnieuw of neem contact op via website@paal13.com.';
        }
        // Stap 2: vervolgstappen — mogen falen zonder de bevestiging te blokkeren
        if ($opgeslagen) {
            $docs_ok = true;
            try { if (function_exists('ondertekeningen_klaarzetten_sd')) ondertekeningen_klaarzetten_sd($sol_id); }
            catch (Exception $e) { $docs_ok = false; error_log('ondertekeningen_klaarzetten_sd: ' . $e->getMessage()); }
            try { if (function_exists('mail_taken_klaar')) mail_taken_klaar($sol_id); }
            catch (Exception $e) { error_log('mail_taken_klaar: ' . $e->getMessage()); }
            $_SESSION['flash_portaal'] = $docs_ok
                ? 'Bedankt! Je ouder/verzorger ontvangt nu een mail om de formulieren te ondertekenen.'
                : 'De gegevens zijn opgeslagen. Het klaarzetten van de formulieren lukte nog niet — we kijken ernaar; probeer het zo nodig later opnieuw.';
        }
    } else {
        $_SESSION['flash_portaal_fout'] = 'Vul een geldige naam, e-mailadres én telefoonnummer in.';
    }
    redirect(APP_URL . '/portaal/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['taak_toggle']) && portaal_ingelogd()) {
    csrf_check();
    $taak_id = (int)($_POST['taak_id'] ?? 0);
    $sol_id  = (int)$_SESSION['portaal_sol_id'];
    try {
        $q = db()->prepare("SELECT status FROM sollicitant_taken WHERE id=? AND sollicitatie_id=?");
        $q->execute([$taak_id, $sol_id]);
        $t = $q->fetch();
        if ($t) {
            $nieuw   = $t['status'] === 'voltooid' ? 'open' : 'voltooid';
            $voltooid = $nieuw === 'voltooid' ? date('Y-m-d H:i:s') : null;
            db()->prepare("UPDATE sollicitant_taken SET status=?, voltooid_op=? WHERE id=?")
                ->execute([$nieuw, $voltooid, $taak_id]);
        }
    } catch (Exception $e) {}
    redirect(APP_URL . '/portaal/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ouder_submit']) && portaal_ingelogd()) {
    csrf_check();
    $ond_id      = (int)($_POST['ond_id'] ?? 0);
    $ouder_naam  = trim($_POST['ouder_naam'] ?? '');
    $ouder_email = trim($_POST['ouder_email'] ?? '');
    $sol_id      = (int)$_SESSION['portaal_sol_id'];
    if ($ond_id && $ouder_naam && filter_var($ouder_email, FILTER_VALIDATE_EMAIL)) {
        try {
            // Alleen eigen records zonder ondertekening bijwerken
            $upd = db()->prepare("UPDATE ondertekeningen SET ondertekenaar_naam=?, ondertekenaar_email=? WHERE id=? AND sollicitatie_id=? AND ondertekend_op IS NULL");
            $upd->execute([$ouder_naam, $ouder_email, $ond_id, $sol_id]);
            if ($upd->rowCount() > 0 && function_exists('ouder_mail_versturen')) {
                ouder_mail_versturen($ond_id);
            }
        } catch (Exception $e) {}
    }
    redirect(APP_URL . '/portaal/#taken');
}

$fases_volgorde = ['aangemeld','uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest','uitgenodigd_tw','tw_geweest','aangenomen'];

// Weken-datums voor de AI-beschikbaarheid JS (weeknr → start/eind datum)
$weken_datums_ai = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen ?? null) : [];

// ── Gedeelde kop (topbar + head). Inlogschermen: kaal (geen topbar, geen nav). ──
$portaal_titel = 'Mijn sollicitatie';
$portaal_nav   = 'start';
if ($stap !== 'dossier') { $portaal_geen_nav = true; $portaal_geen_topbar = true; }
require __DIR__ . '/_kop.php';
?>
<?php if ($stap === 'dossier'): ?>
<style>
/* TIJDELIJK — oude banner-stijl van de startpagina; verdwijnt bij de herbouw van de start-staten */
.banner { border-radius:10px; padding:14px 16px; margin-bottom:12px; }
.banner-wacht { background:var(--bg-subtle); border:1px solid var(--border); }
.banner-actie  { background:var(--accent-soft); border:1px solid var(--accent); }
.banner-ok     { background:var(--ok-soft); border:1px solid var(--ok); }
.banner-rood   { background:var(--error-soft); border:1px solid var(--error); }
.banner h2 { font-size:15px; font-weight:700; color:var(--ink); margin-bottom:4px; }
.banner p { font-size:13px; color:var(--ink-soft); margin:0; }
.banner-actie h2 { color:var(--accent); }
.banner-ok h2 { color:var(--ok); }
.banner-rood h2 { color:var(--error); }
</style>
<?php endif; ?>

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

<?php if ($stap === 'dossier' && $sol): ?>

  <?php
    $vnaam = $sol['voornaam'] ?? '';
    $begroetingen = [
      'aangemeld'            => 'Hoi ' . $vnaam . '|Je aanmelding is binnen. We nemen contact op zodra we je uitnodigen.',
      'uitgenodigd_selectie' => 'Je bent uitgenodigd|Er staan een paar dingen voor je klaar.',
      'aangemeld_selectie'   => 'Bijna klaar, ' . $vnaam . '|Je formulieren zijn ontvangen. Tot de selectiedag!',
      'selectiedag_geweest'  => 'Goed gedaan, ' . $vnaam . '|Je was op de selectiedag. We laten snel weten hoe het verder gaat.',
      'uitgenodigd_tw'       => 'Door naar het trainingsweekend|Er staat iets voor je klaar.',
      'tw_geweest'           => 'Bedankt, ' . $vnaam . '|Het trainingsweekend zit erop. We nemen contact op.',
      'aangenomen'           => 'Welkom bij Paal 13|Je bent aangenomen als strandwacht.',
      'afgewezen'            => 'Bedankt voor je sollicitatie|Dit seizoen gaan we niet verder, maar we waarderen je inzet.',
    ];
    $begroet = explode('|', $begroetingen[$sol['fase']] ?? ('Hoi ' . $vnaam . '|'));
  ?>
  <div class="pt-begroet">
    <h1><?= h($begroet[0]) ?></h1>
    <?php if (!empty($begroet[1])): ?><p><?= h($begroet[1]) ?></p><?php endif; ?>
  </div>

  <!-- Voortgang (niet meer tonen als de reis klaar is: aangenomen of afgewezen) -->
  <?php if (!in_array($sol['fase'], ['afgewezen', 'aangenomen'], true)):
    $vg_stappen = [['aangemeld','Aangemeld'],['uitgenodigd_selectie','Selectiedag'],['selectiedag_geweest','Na de selectie'],['uitgenodigd_tw','Trainingsweekend'],['aangenomen','Aangenomen']];
    $fi = array_search($sol['fase'], $fases_volgorde);
    $actief_idx = 0;
    foreach ($vg_stappen as $vi => $vs) {
      $vsi = array_search($vs[0], $fases_volgorde);
      $in_this = in_array($sol['fase'], [$vs[0], $vs[0] === 'uitgenodigd_selectie' ? 'aangemeld_selectie' : ''], true);
      if ($in_this) $actief_idx = $vi;
      elseif ($fi !== false && $vsi !== false && $vsi < $fi) $actief_idx = max($actief_idx, $vi);
    }
    $vg_totaal = count($vg_stappen);
    $vg_pct = round($actief_idx / ($vg_totaal - 1) * 100);
  ?>
  <div class="ds-kaart ds-kaart-p vg3">
    <div class="vg3-top">
      <div class="vg3-nu">
        <span class="vg3-stapnr">Stap <?= $actief_idx + 1 ?> van <?= $vg_totaal ?></span>
        <span class="vg3-naam"><?= h($vg_stappen[$actief_idx][1]) ?></span>
      </div>
      <div class="vg3-bollen">
        <?php foreach ($vg_stappen as $vi => $vs): $c = $vi < $actief_idx ? 'gedaan' : ($vi == $actief_idx ? 'nu' : ''); ?>
          <span class="vg3-bol <?= $c ?>"></span>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="vg3-balk"><div class="vg3-vul" style="width:<?= $vg_pct ?>%"></div></div>
    <?php if ($actief_idx + 1 < $vg_totaal): ?>
    <div class="vg3-volgende">Hierna: <?= h($vg_stappen[$actief_idx + 1][1]) ?></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Status banners -->
  <?php
  $banner_map=[
    'afgewezen'=>['rood','kruis-cirkel','Niet geselecteerd','Na zorgvuldige beoordeling nemen we je dit seizoen niet verder mee.'],
    'aangenomen'=>['ok','feest','Welkom bij Paal 13!','Je bent aangenomen! Je ontvangt binnenkort meer informatie.'],
    'aangemeld'=>['wacht','vink-cirkel','Aanmelding ontvangen','We bekijken alle aanmeldingen en nemen contact op als je wordt uitgenodigd.'.($sol['terugkerend']?' Welkom terug!':'')],
    'selectiedag_geweest'=>['wacht','wachten','Beslissing volgt','Je was aanwezig op de selectiedag. Het bestuur bespreekt de resultaten.'],
    'tw_geweest'=>['wacht','wachten','Na het trainingsweekend','Het bestuur bespreekt de resultaten. Je ontvangt bericht.'],
  ];
  if(isset($banner_map[$sol['fase']])):[$type,$ic,$titel,$tekst]=$banner_map[$sol['fase']]; ?>
  <div class="banner banner-<?=$type?>"><h2><?= icoon($ic, 20) ?> <?=h($titel)?></h2><p><?=h($tekst)?></p></div>
  <?php endif; ?>

  <?php
  // Taken tonen (kennismakingspakket) bij aangenomen sollicitant
  if ($sol['fase'] === 'aangenomen'):
      $tq = db()->prepare("SELECT id, taak_slug, titel, omschrijving, link_url, status, verplicht, voltooid_op FROM sollicitant_taken WHERE sollicitatie_id=? ORDER BY status DESC, verplicht DESC, id");
      try { $tq->execute([$sol['id']]); $taken = $tq->fetchAll(); } catch (Exception $e) { $taken = []; }
      if ($taken):
          $open = count(array_filter($taken, fn($t) => $t['status'] === 'open'));
  ?>
  <div class="banner banner-actie" style="margin-top:14px">

    <p style="margin:6px 0 12px">Voltooi deze stappen om je lidmaatschap af te ronden.</p>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($taken as $t):
        $done = ($t['status'] === 'voltooid');
      ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;background:var(--grijs-bg);border-radius:6px;border:1px solid var(--rand);<?= $done ? 'opacity:.6' : '' ?>">
          <form method="POST" style="margin:0;flex-shrink:0">
            <?= csrf_input() ?>
            <input type="hidden" name="taak_id" value="<?= (int)$t['id'] ?>">
            <button type="submit" name="taak_toggle" value="1" title="<?= $done ? 'Ongedaan maken' : 'Markeer als voltooid' ?>"
                    style="background:<?= $done ? 'var(--groen)' : 'transparent' ?>;color:<?= $done ? '#fff' : 'inherit' ?>;border:2px solid <?= $done ? 'var(--groen)' : 'currentColor' ?>;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:14px;padding:0;line-height:1"><?= $done ? '✓' : '' ?></button>
          </form>
          <div style="flex:1;min-width:0">
            <div style="font-weight:500;<?= $done ? 'text-decoration:line-through' : '' ?>">
              <?= h($t['titel']) ?>
              <?php if (!$t['verplicht']): ?><span style="font-size:11px;opacity:.6;font-weight:400"> (optioneel)</span><?php endif; ?>
            </div>
            <?php if ($t['omschrijving']): ?>
              <div style="font-size:12.5px;opacity:.75;margin-top:2px"><?= nl2br(h($t['omschrijving'])) ?></div>
            <?php endif; ?>
            <?php if ($t['link_url'] && !$done): ?>
              <a href="<?= h($t['link_url']) ?>" style="display:inline-block;margin-top:6px;font-size:12px;color:inherit;text-decoration:underline">Openen &rarr;</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; endif; ?>

  <?php if(in_array($sol['fase'],['uitgenodigd_selectie','aangemeld_selectie'])): ?>
  <div class="banner banner-actie">
    <h2><?= icoon('strand', 20) ?> Uitgenodigd voor de selectiedag</h2>
    <div class="info-grid" style="margin-top:12px">
      <?php if($sol['datum_selectiedag']): ?><div class="info-item"><span class="ii-ic"><?= icoon('kalender', 16) ?></span><div><span class="ii-l">Datum</span><span class="ii-v"><?=datum_nl($sol['datum_selectiedag'])?></span></div></div><?php endif; ?>
      <?php $v = $sol['selectiedag_verzameltijd'] ?? null; if($v): ?><div class="info-item"><span class="ii-ic"><?= icoon('klok', 16) ?></span><div><span class="ii-l">Verzamelen</span><span class="ii-v"><?=h($v)?> uur</span></div></div><?php endif; ?>
      <?php if($sol['locatie_selectiedag']): ?><div class="info-item"><span class="ii-ic"><?= icoon('reddingsboei', 16) ?></span><div><span class="ii-l">Locatie</span><span class="ii-v"><?=h($sol['locatie_selectiedag'])?></span></div></div><?php endif; ?>
      <?php $s = $sol['selectiedag_starttijd'] ?? null; if($s): ?><div class="info-item"><span class="ii-ic"><?= icoon('klok', 16) ?></span><div><span class="ii-l">Start</span><span class="ii-v"><?=h($s)?> uur</span></div></div><?php endif; ?>
    </div>
    <?php if($sol['fase']==='uitgenodigd_selectie'): ?>
    <div class="info-melding info-melding-actie" style="margin-top:12px"><?= icoon('info', 15) ?> Rond de taken hieronder af vóór de selectiedag.</div>
    <?php elseif($sol['fase']==='aangemeld_selectie'): ?>
    <div class="info-melding info-melding-ok" style="margin-top:12px"><?= icoon('vink', 15) ?> Jouw formulier is ontvangen. Tot de selectiedag!</div>
    <?php endif; ?>

  <?php
  // ── Universele taken-sectie: openstaande ondertekeningen + fase-taken ──
  $alle_taken = [];

  // 1) Openstaande ondertekeningen voor sollicitant zelf
  try {
      $oq = db()->prepare("SELECT id, type, token, ondertekend_op FROM ondertekeningen WHERE sollicitatie_id=? AND ondertekenaar_rol='sollicitant' ORDER BY aangemaakt_op");
      $oq->execute([$sol['id']]);
      foreach ($oq->fetchAll() as $o) {
          $lbl = match($o['type']) {
              'gezondheid_18plus' => 'Onderteken de gezondheidsverklaring',
              'intake'            => 'Vul het intakeformulier voor de selectiedag in',
              default             => 'Onderteken document',
          };
          $alle_taken[] = [
              'titel'   => $lbl,
              'done'    => !empty($o['ondertekend_op']),
              'url'     => APP_URL . '/portaal/ondertekenen.php?token=' . $o['token'],
              'urgent'  => empty($o['ondertekend_op']),
          ];
      }
  } catch (Exception $e) {}

  // 2) Ouder-ondertekeningen: zonder email → vraag sollicitant om gegevens; met email → status
  $ouder_invullen = [];  // records die nog oudergegevens nodig hebben
  $ouder_docs = [];      // voor het status-overzicht (punt 3)
  try {
      $oq2 = db()->prepare("SELECT id, type, ondertekenaar_naam, ondertekenaar_email, ondertekend_op, aangemaakt_op FROM ondertekeningen WHERE sollicitatie_id=? AND ondertekenaar_rol='ouder' ORDER BY aangemaakt_op");
      $oq2->execute([$sol['id']]);
      foreach ($oq2->fetchAll() as $o) {
          $lbl = match($o['type']) {
              'gezondheid_ouder' => 'Gezondheidsverklaring (door ouder/verzorger)',
              'intake_ouder'     => 'Intakeformulier (door ouder/verzorger)',
              default            => 'Document (ouder)',
          };
          if (empty($o['ondertekenaar_email']) && empty($o['ondertekend_op'])) {
              // Sollicitant moet ouder-gegevens invullen
              $ouder_invullen[] = ['id' => (int)$o['id'], 'label' => $lbl];
          } else {
              $is_klaar = !empty($o['ondertekend_op']);
              // Voor het status-overzicht (punt 3)
              $ouder_docs[] = [
                  'label'    => $lbl,
                  'email'    => $o['ondertekenaar_email'] ?? '',
                  'klaar'    => $is_klaar,
                  'klaar_op' => $o['ondertekend_op'] ?? null,
              ];
          }
      }
  } catch (Exception $e) {}

  if ($ouder_invullen) {
      $alle_taken[] = [
          'titel'   => 'Vul de gegevens van je ouder/verzorger in',
          'done'    => false,
          'url'     => '#oudergegevens',
          'urgent'  => true,
          'hint'    => 'Voor de gezondheidsverklaring en het intakeformulier hebben we een handtekening van je ouder/verzorger nodig.',
      ];
  }

  // 3) BLK en beschikbaarheid als eigen taken (bij uitgenodigd én aangemeld voor selectiedag)
  if (in_array($sol['fase'], blk_fases_zichtbaar(), true)) {
      // Beter Leren Kennen: eigen pagina (blk_invullen.php). De taak is pas 'gedaan' als het
      // formulier DEFINITIEF is ingediend — niet zodra er een (autosave-)rij bestaat.
      $blk_klaar = blk_is_ingediend($sol, $blk);
      $blk_op    = $blk['definitief_op'] ?? ($blk['ingevuld_op'] ?? null);
      $alle_taken[] = [
          'titel'  => 'Beter Leren Kennen invullen',
          'done'   => $blk_klaar,
          'url'    => APP_URL . '/portaal/blk_invullen.php',
          'urgent' => !$blk_klaar,
          'hint'   => $blk_klaar
              ? 'Ingeleverd' . ($blk_op ? ' ' . datum_nl(substr($blk_op, 0, 10)) : '')
              : '± 8 minuten' . (!empty($sol['datum_selectiedag']) ? ' · vóór ' . datum_nl($sol['datum_selectiedag']) : ''),
          'klikbaar_gedaan' => true, // ook na inleveren te openen (Bekijken / bijwerken)
      ];

      // Beschikbaarheid als eigen taak (het ontwerp maakt dit een losse taak, los van het BLK)
      $besch_ingevuld = false;
      try {
          if (function_exists('beschikbaarheid2_van')) {
              $_bv = beschikbaarheid2_van($sol['id']);
              $besch_ingevuld = !empty($_bv['blokken']);
          }
      } catch (\Exception $e) {}
      $alle_taken[] = [
          'titel'  => 'Geef je beschikbaarheid door',
          'done'   => $besch_ingevuld,
          'url'    => APP_URL . '/portaal/beschikbaarheid_invullen.php',
          'urgent' => !$besch_ingevuld,
          'hint'   => 'Welke weken kun je werken dit seizoen?',
      ];
  }

  // 4) Ouder-gegevens taak voor minderjarigen bij SD-uitnodiging (vóór ouder-docs kunnen bestaan)
  $toon_ouder_form = false;
  if (in_array($sol['fase'], ['uitgenodigd_selectie','aangemeld_selectie'])) {
      $geb = $sol['geboortedatum'] ?? null;
      $lft = function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($geb)
           : ($geb ? (new DateTime($geb))->diff(new DateTime())->y : null);
      if ($lft !== null && $lft < 18) {
          $ouder_bekend = false;
          $ouder_gegevens = null;
          try {
              if (function_exists('personen_ouder_auto_setup')) personen_ouder_auto_setup();
              $pq = db()->prepare("SELECT p.ouder_naam, p.ouder_email, p.ouder_telefoon FROM personen p JOIN sollicitaties s ON s.persoon_id=p.id WHERE s.id=?");
              $pq->execute([$sol['id']]);
              $ouder_gegevens = $pq->fetch() ?: null;
              $ouder_bekend = $ouder_gegevens && !empty($ouder_gegevens['ouder_email']);
          } catch (Exception $e) {}
          if (!$ouder_bekend) {
              array_unshift($alle_taken, [
                  'titel'       => 'Vul de gegevens van je ouder/verzorger in',
                  'done'        => false,
                  'url'         => null,
                  'urgent'      => true,
                  'hint'        => 'Je ouder of verzorger moet twee formulieren ondertekenen omdat je nog geen 18 bent. Na het invullen krijgen ze automatisch een mail.',
                  'inline_form' => 'ouder',
              ]);
          } else {
              // Al ingevuld: toon als afgeronde taak, met mogelijkheid tot wijzigen
              array_unshift($alle_taken, [
                  'titel'       => 'Gegevens ouder/verzorger ingevuld',
                  'done'        => true,
                  'url'         => null,
                  'status'      => 'Ingevuld',
                  'hint'        => 'Ouder/verzorger: ' . trim(($ouder_gegevens['ouder_naam'] ?? '') . ' — ' . ($ouder_gegevens['ouder_email'] ?? '')),
                  'inline_form' => 'ouder',
                  'ouder_data'  => $ouder_gegevens,
              ]);
          }
      }
  }

  // Aftel-banner: beschikbaarheid nog voorlopig + deadline nadert
  $besch_voorlopig = false; $besch_deadline = null; $besch_dgn = null;
  if (function_exists('beschikbaarheid2_van') && function_exists('beschikbaarheid2_deadline')) {
      $_bp = beschikbaarheid2_van($sol['id'])['profiel'] ?? null;
      if ($_bp && !empty($_bp['voorlopig'])) {
          $besch_voorlopig = true;
          $besch_deadline = beschikbaarheid2_deadline($seizoen ?? null);
          if ($besch_deadline) $besch_dgn = (int)floor(($besch_deadline->getTimestamp() - strtotime('today')) / 86400);
      }
  }
  if ($besch_voorlopig):
    $_over = $besch_dgn;
    if ($_over === null)       { $_kleur='var(--oranje)'; $_bg='var(--oranje-bg)'; $_tekst='Je beschikbaarheid staat nog op <strong>voorlopig</strong>. Maak deze op tijd definitief.'; }
    elseif ($_over < 0)        { $_kleur='var(--rood)';   $_bg='var(--rood-bg)';   $_tekst='De deadline voor je beschikbaarheid is <strong>verstreken</strong>. Maak deze zo snel mogelijk definitief, anders kunnen we je niet inplannen.'; }
    elseif ($_over === 0)      { $_kleur='var(--rood)';   $_bg='var(--rood-bg)';   $_tekst='<strong>Vandaag</strong> is de deadline voor je beschikbaarheid! Maak deze nu definitief.'; }
    elseif ($_over <= 7)       { $_kleur='var(--rood)';   $_bg='var(--rood-bg)';   $_tekst='Nog <strong>' . $_over . ' ' . ($_over===1?'dag':'dagen') . '</strong> tot de deadline. Maak je beschikbaarheid definitief.'; }
    else                       { $_kleur='var(--oranje)'; $_bg='var(--oranje-bg)'; $_tekst='Nog <strong>' . $_over . ' dagen</strong> om je beschikbaarheid definitief te maken.'; }
  ?>
  <div class="card" style="margin-top:14px;border-left:4px solid <?= $_kleur ?>;background:<?= $_bg ?>">
    <div style="display:flex;align-items:center;gap:12px;padding:4px 2px">
      <span style="color:var(--warn)"><?= icoon('wachten', 24) ?></span>
      <div style="flex:1">
        <div style="font-weight:600;font-size:14px;margin-bottom:2px">Beschikbaarheid nog niet definitief</div>
        <div style="font-size:13px;color:var(--tekst-zacht);line-height:1.5"><?= $_tekst ?><?= $besch_deadline ? ' <span style="white-space:nowrap">(deadline ' . h(datum_nl($besch_deadline->format('Y-m-d'))) . ')</span>' : '' ?></div>
      </div>
      <a href="<?= APP_URL ?>/portaal/beschikbaarheid.php" class="ds-btn ds-btn-primair ds-btn-sm" style="white-space:nowrap">Bekijken</a>
    </div>
  </div>
  <?php endif; ?>

  <?php
  $open_taken = array_filter($alle_taken, fn($t) => !$t['done']);
  if ($alle_taken):
  ?>
  <div class="ds-kaart" style="margin-top:16px;padding:6px;overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 12px 10px">
      <span style="font:var(--text-subhead);color:var(--ink)"><?= $open_taken ? 'Nu te doen' : 'Jouw acties' ?></span>
      <?php if ($open_taken): ?>
        <span class="ds-badge ds-badge-accent"><?= count($open_taken) ?> open</span>
      <?php else: ?>
        <span class="ds-badge ds-badge-ok"><?= icoon('vink', 13) ?> Alles gedaan</span>
      <?php endif; ?>
    </div>
    <?php foreach ($alle_taken as $ti => $t):
        // Lijn-icoon + accentkleur per taak (ontwerp: geen emoji, geen gekleurde blokranden)
        if ($t['done'])                                     { $ico='vink-cirkel'; $icocls='ok'; }
        elseif (($t['status'] ?? '')==='Wachten op ouder')  { $ico='mail';        $icocls='navy'; }
        elseif (!empty($t['urgent']))                       { $ico='wachten';     $icocls='accent'; }
        else                                                { $ico='mail';        $icocls=''; }
        $heeft_form = (!empty($t['inline_form']) && $t['inline_form'] === 'ouder');
        $klikbaar = (!$t['done'] || !empty($t['klikbaar_gedaan'])) && !empty($t['url']) && !$heeft_form;
    ?>
        <?php if ($ti > 0): ?><div style="height:1px;background:var(--border);margin:0 12px"></div><?php endif; ?>
        <?php if ($klikbaar): ?><a href="<?= h($t['url']) ?>" style="text-decoration:none;color:inherit;display:block"><?php endif; ?>
        <div style="padding:13px 12px;display:flex;align-items:flex-start;gap:13px;<?= $t['done'] ? 'opacity:.6' : '' ?>">
          <span class="ds-taak-icoon <?= $icocls ?>" style="width:40px;height:40px;border-radius:10px;flex-shrink:0"><?= icoon($ico, 18) ?></span>
          <div style="flex:1;min-width:0">
            <div style="font:var(--text-label);font-size:15px;<?= $t['done'] ? 'text-decoration:line-through;color:var(--ink-muted)' : 'color:var(--ink)' ?>"><?= h($t['titel']) ?></div>
            <?php if (!empty($t['hint'])): ?>
              <div style="font:var(--text-meta);color:var(--ink-muted);margin-top:2px"><?= h($t['hint']) ?></div>
            <?php endif; ?>

            <?php if ($heeft_form): ?>
              <?php $od = $t['ouder_data'] ?? null; $is_ingevuld = !empty($t['done']); ?>
              <?php if ($is_ingevuld): ?>
                <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" style="margin-top:10px" onclick="var f=this.nextElementSibling; f.style.display = f.style.display==='none'?'block':'none';">Wijzigen</button>
              <?php endif; ?>
              <form method="POST" style="margin-top:10px<?= $is_ingevuld ? ';display:none' : '' ?>" onsubmit="return ouderValideer(this)">
                <?= csrf_input() ?>
                <input type="hidden" name="ouder_submit" value="1">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px" class="ouder-grid">
                  <input type="text" name="ouder_naam" placeholder="Naam ouder/verzorger" value="<?= h($od['ouder_naam'] ?? '') ?>" class="ds-input" style="height:42px">
                  <input type="email" name="ouder_email" placeholder="E-mail ouder/verzorger" value="<?= h($od['ouder_email'] ?? '') ?>" class="ds-input" style="height:42px">
                </div>
                <input type="tel" name="ouder_telefoon" placeholder="Telefoonnummer ouder/verzorger" value="<?= h($od['ouder_telefoon'] ?? '') ?>" class="ds-input" style="height:42px;margin-bottom:8px">
                <div class="ouder-fout" style="display:none;color:var(--error);font-size:12px;margin-bottom:8px"></div>
                <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">
                  <span class="btn-tekst"><?= $is_ingevuld ? 'Opnieuw versturen' : 'Versturen naar ouder' ?></span>
                  <span class="btn-spinner" style="display:none">Bezig…</span>
                </button>
              </form>
            <?php endif; ?>
          </div>
          <?php if ($klikbaar && $t['done']): ?><span style="font:var(--text-label);color:var(--navy);flex-shrink:0;align-self:center">Bekijken</span>
          <?php elseif ($klikbaar): ?><span style="color:var(--ink-muted);flex-shrink:0;align-self:center"><?= icoon('chevron', 18) ?></span><?php endif; ?>
        </div>
        <?php if ($klikbaar): ?></a><?php endif; ?>
      <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php // ── Ouder-status overzicht (punt 3): duidelijke status per document ──
  if (!empty($ouder_docs)):
      $totaal = count($ouder_docs);
      $getekend = count(array_filter($ouder_docs, fn($d) => $d['klaar']));
      $alles_klaar = $getekend === $totaal;
  ?>
  <div class="card" style="margin-top:14px">
    <div class="card-header">
      <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;width:100%;gap:10px">
        <span>Formulieren van je ouder/verzorger</span>
        <span class="badge <?= $alles_klaar ? 'badge-groen' : 'badge-blauw' ?>" style="font-size:11px"><?= $getekend ?>/<?= $totaal ?> ondertekend</span>
      </div>
    </div>
    <div class="card-body">
      <?php if ($alles_klaar): ?>
        <div style="background:var(--groen-bg);border:1px solid var(--groen);border-radius:8px;padding:12px 14px;font-size:13px;color:var(--groen)">
          Alle formulieren zijn ondertekend door je ouder/verzorger. Je hoeft verder niets te doen.
        </div>
      <?php else: ?>
        <p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 12px">
          Je ouder/verzorger heeft per e-mail een link gekregen om deze formulieren te ondertekenen. Zodra dat gebeurd is, zie je dat hier. Nog niets gezien? Een herinnering sturen helpt vaak.
        </p>
      <?php endif; ?>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($ouder_docs as $doc): ?>
          <div style="display:flex;align-items:flex-start;gap:10px;padding:11px 13px;border:1px solid var(--rand);border-radius:8px;background:var(--bg-card)">

            <div style="flex:1;min-width:0">
              <div style="font-weight:600;font-size:13px"><?= h($doc['label']) ?></div>
              <div style="font-size:11.5px;color:var(--tekst-zacht);margin-top:2px">
                <?php if ($doc['klaar']): ?>
                  Ondertekend<?= $doc['klaar_op'] ? ' op ' . datum_nl(substr($doc['klaar_op'],0,10)) : '' ?>
                <?php else: ?>
                  Verstuurd naar <?= h($doc['email']) ?> · wacht op ondertekening
                <?php endif; ?>
              </div>
            </div>
            <span class="badge <?= $doc['klaar'] ? 'badge-groen' : 'badge-blauw' ?>" style="font-size:10px;flex-shrink:0"><?= $doc['klaar'] ? 'Getekend' : 'Wacht' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  </div>
  <?php endif; ?>
  <?php if(in_array($sol['fase'],['uitgenodigd_tw','tw_geweest'])): ?>
  <div class="ds-kaart ds-kaart-navy" style="padding:16px;margin-bottom:16px">
    <div style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:16px;margin-bottom:12px"><?= icoon('reddingsboei', 20) ?> Uitgenodigd voor het trainingsweekend</div>
    <div class="info-grid">
      <?php if($sol['datum_tw_start']): ?><div class="info-item"><span class="ii-ic"><?= icoon('kalender', 16) ?></span><div><span class="ii-l">Datum</span><span class="ii-v"><?=datum_nl($sol['datum_tw_start'])?> t/m <?=datum_nl($sol['datum_tw_eind'])?></span></div></div><?php endif; ?>
      <?php $vtw = $sol['tw_verzameltijd'] ?? null; if($vtw): ?><div class="info-item"><span class="ii-ic"><?= icoon('klok', 16) ?></span><div><span class="ii-l">Verzamelen</span><span class="ii-v"><?=h($vtw)?> uur</span></div></div><?php endif; ?>
      <?php if($sol['locatie_tw']): ?><div class="info-item"><span class="ii-ic"><?= icoon('reddingsboei', 16) ?></span><div><span class="ii-l">Locatie</span><span class="ii-v"><?=h($sol['locatie_tw'])?></span></div></div><?php endif; ?>
    </div>
    <div class="info-melding info-melding-actie" style="margin-top:12px"><?= icoon('info', 15) ?> Hieronder zie je je beschikbaarheid. Klopt er iets niet meer? Geef dat daar aan.</div>
  </div>
  <?php endif; ?>

  <?php // ── Beschikbaarheid tonen (definitief uit BLK) ──────
  if (in_array($sol['fase'], ['uitgenodigd_tw','tw_geweest','aangenomen'])):
    $b2tw = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($sol['id']) : ['profiel'=>null,'blokken'=>[],'afwezig'=>[],'verzoeken'=>[]];
    $b2tw_eff = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol['id']) : [];
    $b2tw_wkd = function_exists('seizoen_weken_datums') ? seizoen_weken_datums() : [];
    $b2tw_lbl = function($wn) use ($b2tw_wkd) { $wn=(int)$wn; return isset($b2tw_wkd[$wn]) ? 'week ' . $wn . ' (' . $b2tw_wkd[$wn]['start']->format('j M') . ')' : 'week ' . $wn; };
    if ($b2tw['profiel'] && $b2tw['blokken']):
    $b2tw_voorlopig = !empty($b2tw['profiel']['voorlopig']);
  ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title"><?= icoon("kalender", 18) ?> Jouw beschikbaarheid</div>
    </div>
    <div class="card-body">
      <?php if ($b2tw_voorlopig): ?>
      <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--oranje-bg);border:1px solid var(--oranje);border-radius:8px;margin-bottom:12px">
        <span style="color:var(--ink-muted)"><?= icoon("wachten", 17) ?></span>
        <div style="font-size:12.5px;color:var(--oranje-dim)">
          <strong>Nog voorlopig.</strong> Werk het op tijd bij en zet het definitief — anders gebruiken we je huidige invoer automatisch vanaf 2 weken vóór het trainingsweekend.
        </div>
      </div>
      <?php else: ?>
      <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--groen-bg);border:1px solid var(--groen);border-radius:8px;margin-bottom:12px;font-size:12.5px;color:var(--groen)">
        ✓ Je beschikbaarheid is definitief.
      </div>
      <?php endif; ?>

      <!-- Compacte samenvatting -->
      <div style="font-size:13px;line-height:1.7;margin-bottom:14px">
        <?php foreach ($b2tw['blokken'] as $blok): ?>
        <div>• <strong><?= h($b2tw_lbl($blok['van_week'])) ?> t/m <?= h($b2tw_lbl($blok['tot_week'])) ?></strong> —
          <?php if ($blok['regime']==='weekend'):
            $wd = $blok['weekend_dagen'] ?: [5,6,7];
            echo 'alleen ' . implode('/', array_map('dag_kort', $wd));
          else: echo 'hele weken (5 dagen)'; endif; ?>
        </div>
        <?php endforeach; ?>
        <div style="color:var(--grijs);margin-top:4px"><?= count($b2tw_eff) ?> <?= count($b2tw_eff) === 1 ? 'week' : 'weken' ?> beschikbaar<?php if (!empty($b2tw['afwezig'])): ?> · <?= count($b2tw['afwezig']) ?> afwezigheidsperiode<?= count($b2tw['afwezig'])>1?'s':'' ?><?php endif; ?></div>
      </div>

      <a href="<?= APP_URL ?>/portaal/beschikbaarheid.php" class="ds-btn ds-btn-secundair ds-btn-sm">Bekijken & aanpassen →</a>
    </div>
  </div>
  <?php endif; endif; ?>

<?php endif; ?>

<script>

/* ── Loading states ──────────────────────────────────────── */
// Alle form-submits: knop → spinner, voorkom dubbel klikken
function portaalSubmit(form) {
  const btn = form.querySelector('button[type="submit"]');
  if (btn) {
    btn.disabled = true;
    const t = btn.querySelector('.btn-tekst'), s = btn.querySelector('.btn-spinner');
    if (t && s) { t.style.display = 'none'; s.style.display = ''; }
    else { btn.dataset.oud = btn.innerHTML; btn.innerHTML = 'Bezig…'; }
  }
  return true;
}

// Ouder-formulier: JS-validatie (velden hebben geen 'required' zodat verborgen staat de submit niet blokkeert)
function ouderValideer(form) {
  const naam = (form.querySelector('[name="ouder_naam"]')||{}).value?.trim() || '';
  const email = (form.querySelector('[name="ouder_email"]')||{}).value?.trim() || '';
  const tel = (form.querySelector('[name="ouder_telefoon"]')||{}).value?.trim() || '';
  const foutBox = form.querySelector('.ouder-fout');
  const cijfers = (tel.match(/\d/g) || []).length;
  let fout = '';
  if (!naam) fout = 'Vul de naam van je ouder/verzorger in.';
  else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) fout = 'Vul een geldig e-mailadres in.';
  else if (cijfers < 8) fout = 'Vul een geldig telefoonnummer in (minstens 8 cijfers).';
  if (fout) {
    if (foutBox) { foutBox.textContent = fout; foutBox.style.display = 'block'; foutBox.scrollIntoView({behavior:'smooth', block:'center'}); }
    else { alert(fout); }
    return false;
  }
  // Spinner (als portaalSubmit bestaat), maar laat de submit altijd doorgaan
  if (typeof portaalSubmit === 'function') { try { portaalSubmit(form); } catch(e){} }
  return true;
}

// Automatisch op alle forms zonder expliciete handler
document.querySelectorAll('form').forEach(f => {
  if (!f.hasAttribute('onsubmit')) {
    f.addEventListener('submit', () => portaalSubmit(f));
  }
});
// Links naar ondertekenen/documenten: visuele feedback
document.querySelectorAll('a.btn').forEach(a => {
  a.addEventListener('click', () => {
    if (a.href && !a.href.startsWith('#')) {
      a.style.opacity = '.6';
      a.textContent = 'Openen…';
    }
  });
});
</script>
<?php
// Gedeelde voet (nav + script). Telbolletje op "Taken" = aantal open taken.
$portaal_nav = (($_GET['tab'] ?? '') === 'taken') ? 'taken' : 'start';
$portaal_open_taken = isset($open_taken) ? count($open_taken) : 0;
require __DIR__ . '/_voet.php';

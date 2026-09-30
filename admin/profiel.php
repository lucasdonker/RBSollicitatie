<?php
/**
 * Beheer — Mijn profiel: je eigen naam, wachtwoord en voorkeuren.
 *
 * Dit scherm hangt niet in de navigatie maar onder je naam in de zijbalk, want het
 * gaat over jou en niet over het werk. Daarom is $actieve_nav 'profiel': bh_nav_actief()
 * maakt daar '' van (geen enkel nav-item licht op), en hulp_tekst('profiel') geeft het
 * vraagteken zijn eigen uitleg.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
require_once __DIR__ . '/../includes/tweestaps.php';
$pagina_titel = 'Mijn profiel';
$actieve_nav  = 'profiel';
vereist_login();

$b    = huidige_beheerder();
$fout = '';
$ts_fout      = ['bevestig' => '', 'wijzig' => ''];   // foutmelding bij het codeveld
$herstel_toon = null;                                 // herstelcodes: alleen in dít antwoord, nooit bewaard

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = (string)($_POST['actie'] ?? '');

    if ($actie === 'naam') {
        $naam = trim((string)($_POST['naam'] ?? ''));
        if ($naam === '') {
            $fout = 'Vul een naam in.';
        } else {
            db()->prepare("UPDATE beheerders SET naam=? WHERE id=?")->execute([$naam, $b['id']]);
            $_SESSION['bhd_naam'] = $naam;
            flash('succes', 'Naam bijgewerkt.');
            redirect(APP_URL . '/admin/profiel.php');
        }
    }

    if ($actie === 'wachtwoord') {
        $oud     = (string)($_POST['oud_wachtwoord'] ?? '');
        $nieuw   = (string)($_POST['nieuw_wachtwoord'] ?? '');
        $herhaal = (string)($_POST['herhaal_wachtwoord'] ?? '');

        $q = db()->prepare("SELECT * FROM beheerders WHERE id=?");
        $q->execute([$b['id']]);
        $rec = $q->fetch();

        if (empty($rec['wachtwoord'])) {
            $fout = 'Je logt in via Authentik en hebt hier geen wachtwoord. Wil je er toch een, vraag dan een beheerder.';
        } elseif (!password_verify($oud, $rec['wachtwoord'])) {
            $fout = 'Het huidige wachtwoord klopt niet.';
        } elseif (strlen($nieuw) < 8) {
            $fout = 'Het nieuwe wachtwoord moet minstens 8 tekens hebben.';
        } elseif ($nieuw !== $herhaal) {
            $fout = 'De twee nieuwe wachtwoorden zijn niet hetzelfde.';
        } else {
            db()->prepare("UPDATE beheerders SET wachtwoord=? WHERE id=?")
                ->execute([password_hash($nieuw, PASSWORD_DEFAULT), $b['id']]);
            // Wel dát het gebeurde, nooit het wachtwoord zelf.
            if (function_exists('audit_log')) {
                audit_log('beheerder_gewijzigd', 'beheerder', (int)$b['id'], ['wachtwoord' => 'zelf gewijzigd']);
            }
            flash('succes', 'Je wachtwoord is gewijzigd.');
            redirect(APP_URL . '/admin/profiel.php');
        }
    }

    // ── Tweestapsverificatie (includes/tweestaps.php) ─────────
    if (str_starts_with($actie, 'tweestaps_')) {
        $bid = (int)$b['id'];
        $rec = tweestaps_rij($bid) ?? [];
        $ip  = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        $zelf = APP_URL . '/admin/profiel.php#tweestaps';

        if (!tweestaps_van_toepassing($rec)) {
            $fout = 'Tweestaps hoort bij inloggen met een wachtwoord. Jij logt in via Authentik, en dat regelt de tweede stap zelf.';
        } elseif ($actie === 'tweestaps_start') {
            if (tweestaps_aan($rec)) {
                $fout = 'Tweestaps staat al aan. Wil je een nieuwe telefoon koppelen, kies dan "Opnieuw instellen".';
            } elseif (!tweestaps_voorlopig_start($bid)) {
                $fout = 'Instellen kan nu niet: de kluis voor geheimen werkt niet. Meld dit bij de systeembeheerder.';
            } else {
                redirect($zelf);
            }
        } elseif ($actie === 'tweestaps_annuleer') {
            tweestaps_voorlopig_stop();
            redirect($zelf);
        } elseif ($actie === 'tweestaps_bevestig') {
            $b32 = tweestaps_voorlopig($bid);
            if ($b32 === null) {
                $fout = 'Het instellen is verlopen (na 15 minuten). Begin opnieuw.';
            } elseif (!tweestaps_poging_toegestaan($bid, $ip)) {
                $ts_fout['bevestig'] = 'Te veel pogingen. Wacht een kwartier en probeer het dan opnieuw.';
            } elseif (($stap = tweestaps_totp_controleer($b32, (string)($_POST['code'] ?? ''))) === null) {
                audit_log('tweestaps_mislukt', 'beheerder', $bid, ['methode' => 'instellen']);
                $ts_fout['bevestig'] = 'Deze code klopt niet. Controleer of de tijd op je telefoon automatisch wordt ingesteld en probeer de volgende code.';
            } elseif (($codes = tweestaps_inschakelen($bid, $b32, $stap)) === null) {
                $fout = 'Opslaan lukte niet: de kluis voor geheimen werkt niet. Meld dit bij de systeembeheerder.';
            } else {
                tweestaps_voorlopig_stop();
                audit_log('tweestaps_aangezet', 'beheerder', $bid, ['opnieuw' => tweestaps_aan($rec) ? 'ja' : 'nee']);
                $herstel_toon = $codes;   // geen redirect: de codes bestaan alleen in dit antwoord
            }
        } elseif ($actie === 'tweestaps_uit' || $actie === 'tweestaps_opnieuw') {
            if (!tweestaps_aan($rec)) {
                $fout = 'Tweestaps staat niet aan.';
            } elseif (!tweestaps_poging_toegestaan($bid, $ip)) {
                $ts_fout['wijzig'] = 'Te veel pogingen. Wacht een kwartier en probeer het dan opnieuw.';
            } elseif (!password_verify((string)($_POST['huidig_wachtwoord'] ?? ''), (string)$rec['wachtwoord'])) {
                audit_log('tweestaps_mislukt', 'beheerder', $bid, ['methode' => 'wachtwoord bij ' . ($actie === 'tweestaps_uit' ? 'uitzetten' : 'opnieuw instellen')]);
                $ts_fout['wijzig'] = 'Het wachtwoord klopt niet.';
            } elseif (!tweestaps_code_of_herstel($rec, (string)($_POST['code'] ?? ''))) {
                audit_log('tweestaps_mislukt', 'beheerder', $bid, ['methode' => $actie === 'tweestaps_uit' ? 'uitzetten' : 'opnieuw instellen']);
                $ts_fout['wijzig'] = 'Deze code klopt niet, is al gebruikt of is verlopen.';
            } elseif ($actie === 'tweestaps_uit') {
                tweestaps_voorlopig_stop();
                tweestaps_uitschakelen($bid);
                audit_log('tweestaps_uitgezet', 'beheerder', $bid, ['door' => 'zelf']);
                flash('waarschuwing', 'Tweestaps staat uit. Inloggen met je wachtwoord vraagt nu een code per e-mail.');
                redirect($zelf);
            } elseif (!tweestaps_voorlopig_start($bid)) {
                $fout = 'Instellen kan nu niet: de kluis voor geheimen werkt niet. Meld dit bij de systeembeheerder.';
            } else {
                redirect($zelf);
            }
        }
    }
}

$rec = tweestaps_rij((int)$b['id']) ?? [];   // SELECT * en zorgt dat de tweestaps-kolommen bestaan

require __DIR__ . '/layout_top.php';
?>

<?php if ($fout !== ''): ?>
  <div class="ds-melding ds-melding-error bh-blokkade" role="alert"><?= icoon('waarschuwing', 18) ?><span><?= h($fout) ?></span></div>
<?php endif; ?>

<div class="bh-profiel">
  <section class="ds-kaart ds-kaart-p">
    <div class="bh-kaartkop"><h2>Mijn gegevens</h2></div>

    <div class="bh-gegevensraster twee">
      <div>
        <span class="bh-veldlabel">E-mailadres</span>
        <span class="bh-veldwaarde"><?= h((string)($rec['email'] ?? '')) ?></span>
      </div>
      <div>
        <span class="bh-veldlabel">Rol</span>
        <?php /* Zelfde woord als in de zijbalk (_nav.php): de rol admin heet daar "Beheerder". */
              $_rol = (string)($rec['rol'] ?? ''); ?>
        <span class="bh-veldwaarde"><?= h($_rol === 'admin' ? 'Beheerder' : ucfirst($_rol)) ?></span>
      </div>
      <div>
        <span class="bh-veldlabel">Hoe je inlogt</span>
        <span class="bh-veldwaarde">
          <?php if (!empty($rec['authentik_uid'])): ?>
            <span class="bh-bhrij-via"><?= icoon('schild', 14) ?> Authentik</span>
          <?php endif; ?>
          <?php if (!empty($rec['wachtwoord'])): ?>
            <span class="bh-bhrij-via"><?= icoon('sleutel', 14) ?> Wachtwoord</span>
          <?php endif; ?>
          <?php if (empty($rec['authentik_uid']) && empty($rec['wachtwoord'])): ?>
            <span class="bh-bhrij-via let-op"><?= icoon('waarschuwing', 14) ?> Geen inlogmethode</span>
          <?php endif; ?>
        </span>
      </div>
      <div>
        <span class="bh-veldlabel">Laatst ingelogd</span>
        <span class="bh-veldwaarde">
          <?= !empty($rec['laatste_login']) ? h(datum_nl(substr((string)$rec['laatste_login'], 0, 10))) : 'onbekend' ?>
        </span>
      </div>
    </div>

    <form method="POST" class="bh-profielform">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="naam">
      <div class="ds-veld">
        <label class="ds-label" for="pf_naam">Weergavenaam</label>
        <input class="ds-input" type="text" id="pf_naam" name="naam" value="<?= h((string)($rec['naam'] ?? '')) ?>" required>
        <p class="ds-veld-hint">Deze naam staat bij je observaties en besluiten, zodat anderen zien wie wat zei.</p>
      </div>
      <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Naam opslaan</button>
    </form>
  </section>

  <section class="ds-kaart ds-kaart-p">
    <div class="bh-kaartkop"><h2>Wachtwoord</h2></div>
    <?php if (empty($rec['wachtwoord'])): ?>
      <p class="bh-dossier-tekst">
        Je logt in via Authentik, dus je hebt hier geen wachtwoord — je verandert het in Authentik zelf.
        Dat is ook veiliger: er staat er hier dan geen.
      </p>
    <?php else: ?>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="wachtwoord">
        <div class="ds-veld">
          <label class="ds-label" for="pf_oud">Huidig wachtwoord</label>
          <input class="ds-input" type="password" id="pf_oud" name="oud_wachtwoord" required autocomplete="current-password">
        </div>
        <div class="ds-veld">
          <label class="ds-label" for="pf_nieuw">Nieuw wachtwoord</label>
          <input class="ds-input" type="password" id="pf_nieuw" name="nieuw_wachtwoord" required minlength="8" autocomplete="new-password">
          <p class="ds-veld-hint">Minstens 8 tekens. Een zin is makkelijker te onthouden en moeilijker te raden dan een kort wachtwoord met tekens erin.</p>
        </div>
        <div class="ds-veld">
          <label class="ds-label" for="pf_herhaal">Nieuw wachtwoord nog een keer</label>
          <input class="ds-input" type="password" id="pf_herhaal" name="herhaal_wachtwoord" required minlength="8" autocomplete="new-password">
        </div>
        <div class="bh-knoprij">
          <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Wachtwoord wijzigen</button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <?php if (tweestaps_van_toepassing($rec)) require __DIR__ . '/_tweestaps_kaart.php'; ?>

  <section class="ds-kaart ds-kaart-p bh-profiel-breed">
    <div class="bh-kaartkop"><h2>Voorkeuren</h2></div>
    <div class="bh-voorkeur">
      <span class="bh-voorkeur-tekst">
        <strong>Donker thema</strong>
        <span>Een donker kleurenpalet. Dit wordt per apparaat onthouden, niet op je account.</span>
      </span>
      <button type="button" class="bh-schakelaar" id="themeSwitch" role="switch" aria-checked="false"
              aria-label="Donker thema" data-klik="toggleTheme()">
        <span class="bh-schakelaar-bol" id="themeDot"></span>
      </button>
    </div>
  </section>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>

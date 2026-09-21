<?php
/**
 * Beheer — Mijn profiel: je eigen naam, wachtwoord en voorkeuren.
 *
 * Dit scherm hangt niet in de navigatie maar onder je naam in de zijbalk, want het
 * gaat over jou en niet over het werk. Daarom staat $actieve_nav hier leeg: geen
 * enkel nav-item hoort op te lichten.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
$pagina_titel = 'Mijn profiel';
$actieve_nav  = '';
vereist_login();

$b    = huidige_beheerder();
$fout = '';

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
}

$q = db()->prepare("SELECT * FROM beheerders WHERE id=?");
$q->execute([$b['id']]);
$rec = $q->fetch() ?: [];

require __DIR__ . '/layout_top.php';
?>

<?php if ($fout !== ''): ?>
  <div class="ds-melding ds-melding-error bh-blokkade"><?= icoon('waarschuwing', 18) ?><span><?= h($fout) ?></span></div>
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
        <span class="bh-veldwaarde"><?= h(ucfirst((string)($rec['rol'] ?? ''))) ?></span>
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

  <section class="ds-kaart ds-kaart-p bh-profiel-breed">
    <div class="bh-kaartkop"><h2>Voorkeuren</h2></div>
    <div class="bh-voorkeur">
      <span class="bh-voorkeur-tekst">
        <strong>Donker thema</strong>
        <span>Een donker kleurenpalet. Dit wordt per apparaat onthouden, niet op je account.</span>
      </span>
      <button type="button" class="bh-schakelaar" id="themeSwitch" role="switch" aria-checked="false"
              aria-label="Donker thema" onclick="toggleTheme()">
        <span class="bh-schakelaar-bol" id="themeDot"></span>
      </button>
    </div>
  </section>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>

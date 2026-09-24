<?php
/**
 * Inchecken — draait op een tablet bij de ingang.
 *
 * Dit scherm wordt staand bediend, met één hand, soms met natte handen
 * (ontwerpfilosofie §6). Daarom: één kolom, grote raakvlakken (48px), hoog contrast,
 * geen horizontaal scrollen. Een tik ís opslaan; er is geen bewaarknop.
 *
 * Inchecken zet meteen de fase op "selectiedag geweest": niemand werkt achteraf een
 * lijst bij.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

vereis_recht('selectiedag', 'lezen');

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;

// Zelfde niveaus als het trainingsweekend (admin/trainingsweekend.php):
//   meedraaien — aanwezig/ongedaan tikken: begeleiderswerk, dus 'lezen' (zie rechten.php).
//   bewerken   — de tablet-code aanmaken of vernieuwen: bestuur.
$mag_meedraaien = mag('selectiedag', 'lezen');
$mag_bewerken   = mag('selectiedag', 'bewerken');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    // Aanwezig/niet/ongedaan en afsluiten gaan via admin/api_incheck.php (gedeeld met het TW).
    $actie = (string)($_POST['actie'] ?? '');
    csrf_check();

    if ($actie === 'pin_zetten') {
        // De pincode waarmee iemand van de organisatie op de tablet zelf kan afsluiten.
        vereis_recht('selectiedag', 'bewerken');
        $pin = preg_replace('/\D/', '', (string)($_POST['pin'] ?? ''));
        instelling_opslaan('selectie.afsluit_pin', strlen($pin) >= 4 ? password_hash($pin, PASSWORD_DEFAULT) : '');
        flash('succes', strlen($pin) >= 4 ? 'Pincode voor afsluiten op de tablet opgeslagen.' : 'Pincode gewist: afsluiten kan nu alleen in het beheer.');
        redirect(APP_URL . '/admin/selectie_inchecken.php');
    }
    if ($actie === 'code_genereren') {
        vereis_recht('selectiedag', 'bewerken');
        // Leesbare code: geen tekens die je kunt verwarren (0/O, 1/I/L).
        $alfabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alfabet[random_int(0, strlen($alfabet) - 1)];
        instelling_opslaan('selectie.incheck_code', $code);
        flash('succes', 'Nieuwe toegangscode aangemaakt. De oude tablet-links werken niet meer.');
        redirect(APP_URL . '/admin/selectie_inchecken.php');
    }
}

$code        = function_exists('instelling') ? instelling('selectie.incheck_code', '') : '';
$incheck_url = APP_URL . '/portaal/incheck.php?ev=selectiedag&code=' . urlencode($code);
$heeft_pin   = function_exists('instelling') && instelling('selectie.afsluit_pin', '') !== '';

$pagina_titel = 'Inchecken';
$actieve_nav  = 'selectiedag';
require __DIR__ . '/layout_top.php';
?>

<?php
  $subnav_actief = 'inchecken';
  $subnav_datum  = $seizoen && !empty($seizoen['datum_selectiedag']) ? datum_nl($seizoen['datum_selectiedag']) : null;
  require __DIR__ . '/_selectie_subnav.php';
?>

<?php if (!$sid): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php
      $leeg_titel = 'Geen actief seizoen';
      $leeg_tekst = 'Maak eerst een seizoen aan en zet de datum van de selectiedag.';
      $leeg_icoon = 'kalender';
      require __DIR__ . '/_leeg.php';
    ?>
  </div>
<?php else: ?>

<div class="bh-incheck">

  <section class="ds-kaart ds-kaart-p bh-incheck-tablet">
    <div class="bh-kaartkop">
      <h2>Tablet bij de ingang</h2>
      <span class="bh-meta">sollicitanten melden zichzelf aan</span>
    </div>
    <?php if ($code): ?>
      <div class="bh-incheck-coderij">
        <div class="bh-incheck-codeblok">
          <span class="bh-veldlabel">Toegangscode</span>
          <span class="bh-incheck-code"><?= h($code) ?></span>
        </div>
        <div class="bh-incheck-link">
          <span class="bh-veldlabel">Tablet-link</span>
          <div class="bh-incheck-linkrij">
            <input type="text" class="ds-input" readonly value="<?= h($incheck_url) ?>" id="incheckUrl">
            <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
                    onclick="kopieerIncheckLink()">Kopiëren</button>
            <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($incheck_url) ?>" target="_blank" rel="noopener">Openen</a>
          </div>
        </div>
      </div>
      <?php if ($mag_bewerken): ?>
        <form method="post" class="bh-incheck-nieuw" id="codeForm">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="code_genereren">
          <button type="button" class="bh-doc-actie" onclick="nieuweCode()">Nieuwe code aanmaken</button>
        </form>
        <form method="post" class="bh-incheck-pin">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="pin_zetten">
          <label for="afsluitPin" class="bh-veldlabel">Pincode om op de tablet af te sluiten <?= $heeft_pin ? '(ingesteld)' : '(nog niet ingesteld)' ?></label>
          <div class="bh-incheck-linkrij">
            <input type="password" inputmode="numeric" autocomplete="new-password" id="afsluitPin" name="pin" class="ds-input" placeholder="minstens 4 cijfers; leeg = wissen">
            <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Opslaan</button>
          </div>
        </form>
      <?php endif; ?>
    <?php elseif ($mag_bewerken): ?>
      <p class="bh-dossier-tekst">Er is nog geen code. Zonder code kunnen sollicitanten zich niet zelf aanmelden.</p>
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="code_genereren">
        <button type="submit" class="ds-btn ds-btn-primair">Toegangscode aanmaken</button>
      </form>
    <?php else: ?>
      <p class="bh-dossier-tekst zacht">Nog geen code ingesteld. Vraag een bestuurslid er een aan te maken.</p>
    <?php endif; ?>
  </section>

  <?php
    $ick_ev          = 'selectiedag';
    $ick_titel       = 'Inchecken selectiedag';
    $ick_sub         = trim(($seizoen && !empty($seizoen['datum_selectiedag']) ? datum_nl($seizoen['datum_selectiedag']) : '')
                     . (!empty($seizoen['locatie_selectiedag']) ? ' · ' . $seizoen['locatie_selectiedag'] : ''), ' ·');
    $ick_lijst       = evenement_lijst('selectiedag', $sid);
    $ick_mag         = $mag_meedraaien;
    $ick_mag_sluiten = $mag_bewerken;
    $ick_verder      = ['url' => APP_URL . '/admin/selectiedag.php', 'label' => 'Naar beoordelen'];
    require __DIR__ . '/_incheck_lijst.php';
  ?>
</div>

<script>
// window.CSRF, geen const: layout_bottom.php declareert CSRF ook, en een tweede const
// brak dat hele script af (SyntaxError) — thema, meldingen en fase wijzigen deden niets.
window.CSRF = window.CSRF || '<?= csrf_token() ?>';
function kopieerIncheckLink() {
  const v = document.getElementById('incheckUrl').value;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(v).then(function () { bhToast('Link gekopieerd.', 'succes'); });
  }
}

function nieuweCode() {
  bhBevestig({
    titel: 'Nieuwe toegangscode aanmaken?',
    tekst: 'De tablets die nu open staan werken daarna niet meer; die moet je opnieuw openen met de nieuwe link.',
    knop: 'Ja, nieuwe code'
  }).then(function (ja) { if (ja) document.getElementById('codeForm').submit(); });
}
</script>

<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>

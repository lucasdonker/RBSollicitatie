<?php
/**
 * Vandaag — het startscherm.
 *
 * Opent op een vraag, niet op data: bovenaan waar het seizoen staat, daaronder de ene
 * taak die nu telt, en pas daarna de cijfers. Iemand die eens per week inlogt moet
 * binnen tien seconden zien wat er te doen is (ontwerpfilosofie §1 en §2).
 *
 * Wat hier staat, staat er voor iedereen — behalve de blokken met persoonsgegevens.
 * Die vragen om het leesrecht op sollicitanten.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

$db  = db();
$sz  = actief_seizoen();
$sid = $sz ? (int)$sz['id'] : 0;

$fase     = bh_seizoen_fase($sz);
$taak     = bh_vandaag_hoofdtaak($sz, $fase);
$werkbak  = bh_vandaag_werkbak($sz, $taak);
$sinds_op = beheerder_vandaag_sinds((int)($_SESSION['bhd_id'] ?? 0));
$sinds    = bh_vandaag_sinds($sz, $sinds_op);
$agenda   = bh_vandaag_agenda($sz);
$systeem  = (mag('systeem', 'lezen') || mag('logboek', 'lezen') || mag('instellingen', 'bewerken')) ? bh_systeemstatus() : null;
$tellingen = $sid ? bh_fase_tellingen($sid) : [];
$mag_soll = mag('sollicitanten', 'lezen');

$pagina_titel = 'Vandaag';
$actieve_nav  = 'dashboard';
$live_scopes = $sid ? ['sz:' . $sid] : [];   // alleen een melding: Vandaag zelf herladen verschuift "sinds je vorige bezoek"
require __DIR__ . '/layout_top.php';
?>

<?php if (!$sz): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php
      // De knop alleen voor wie een seizoen mag aanmaken; anders eindigt hij op "Geen toegang".
      $_sz_mag    = mag('seizoen', 'bewerken');
      $leeg_titel = 'Er is nog geen seizoen';
      $leeg_tekst = $_sz_mag
          ? 'Maak een seizoen aan en zet de datums; daarna komt hier te staan wat er te doen is.'
          : 'Zodra het bestuur een seizoen heeft aangemaakt, komt hier te staan wat er te doen is.';
      $leeg_icoon = 'kalender';
      $leeg_knop  = $_sz_mag ? ['tekst' => 'Seizoen aanmaken', 'url' => APP_URL . '/admin/seizoen.php'] : null;
      require __DIR__ . '/_leeg.php';
    ?>
  </div>
<?php else: ?>

  <?php $vandaag_fase = $fase; require __DIR__ . '/_seizoenbalk.php'; ?>

  <?php $kengetallen = bh_vandaag_kengetallen($sz); require __DIR__ . '/_kengetallen.php'; ?>

  <div class="bh-vandaag-kolommen">
    <?php // Links: wat je moet doen. Rechts: wat je moet weten. ?>
    <div class="bh-vandaag-hoofd">
      <?php $vandaag_taak = $taak; require __DIR__ . '/_hoofdtaak.php'; ?>

      <?php require __DIR__ . '/_werkbak.php'; ?>

      <?php if ($mag_soll && function_exists('evenement_afmeldingen_open')) { $afmeldingen = evenement_afmeldingen_open($sid); require __DIR__ . '/_afmeldingen.php'; } ?>

      <?php if ($mag_soll && function_exists('intrek_open_lijst')) { $intrekverzoeken = intrek_open_lijst($sid); require __DIR__ . '/_intrekverzoeken.php'; } ?>

    <?php if ($mag_soll): ?>
    <section class="ds-kaart ds-kaart-p bh-stand">
      <div class="bh-kaartkop">
        <h2>Waar staat iedereen</h2>
        <span class="bh-meta">klik om te filteren</span>
      </div>
      <?php
        $_totaal = array_sum($tellingen);
        $_max    = $_totaal > 0 ? max($tellingen) : 0;
      ?>
      <?php if ($_totaal === 0): ?>
        <?php
          $leeg_titel = 'Nog niemand aangemeld';
          $leeg_tekst = 'Zodra de eerste aanmelding binnenkomt, zie je hier hoe de lichting ervoor staat.';
          require __DIR__ . '/_leeg.php';
        ?>
      <?php else: ?>
        <div class="bh-stand-balken">
          <?php foreach (fasen() as $_f => $_lbl): ?>
            <?php $_n = (int)($tellingen[$_f] ?? 0); if ($_n === 0) continue; ?>
            <a class="bh-stand-balk ds-fase-<?= h(fase_kleur($_f)) ?>"
               href="<?= APP_URL ?>/admin/sollicitanten.php?fase=<?= h($_f) ?>">
              <span class="bh-stand-getal"><?= $_n ?></span>
              <span class="bh-stand-staaf" style="--h: <?= max(8, (int)round($_n / max(1, $_max) * 56)) ?>px"></span>
              <span class="bh-stand-label"><?= h(fase_label_kort($_f)) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    </div>

    <div class="bh-vandaag-zij">
      <?php $sinds_regels = $sinds; require __DIR__ . '/_sinds.php'; ?>
      <?php require __DIR__ . '/_agenda.php'; ?>

      <?php // Techniek (wachtrij, mailfouten, taakronde) is voor wie er iets aan kan doen; voor een begeleider is het ruis.
        if (mag('systeem', 'lezen') || mag('logboek', 'lezen') || mag('instellingen', 'bewerken')) {
            $vandaag_systeem = $systeem; require __DIR__ . '/_systeemstatus.php';
        } ?>
    </div>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

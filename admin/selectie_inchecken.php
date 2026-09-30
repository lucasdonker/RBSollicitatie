<?php
/**
 * Inchecken — draait op een tablet bij de ingang.
 *
 * Dit scherm wordt staand bediend, met één hand, soms met natte handen
 * (ontwerpfilosofie §6). Daarom: één kolom, grote raakvlakken (48px), hoog contrast,
 * geen horizontaal scrollen. Een tik ís opslaan; er is geen bewaarknop.
 *
 * Inchecken zet meteen de fase op "selectiedag geweest": niemand werkt achteraf een
 * lijst bij. Aanwezig/niet/ongedaan, afsluiten en de tablet-code lopen via
 * admin/api_incheck.php — gedeeld met het trainingsweekend.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

vereis_recht('selectiedag', 'lezen');

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;

// Taken (includes/modules/selectiedag.php):
//   inchecken    — aanwezig/ongedaan tikken: begeleiderswerk.
//   voorbereiden — de tablet-code en pin, en afsluiten: bestuur.
$mag_meedraaien = mag('selectiedag', 'inchecken');
$mag_bewerken   = mag('selectiedag', 'voorbereiden');

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
  <?php
    $tc_ev = 'selectiedag';
    $tc_mag_bewerken = $mag_bewerken;
    require __DIR__ . '/_tablet_code.php';

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

<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>

<?php
/**
 * TW-stap Inchecken: de tablet-code en de gedeelde incheck-lijst (handmatige backup van de
 * tablet) — zelfde opbouw als bij de selectiedag. Inchecken zet de fase op tw_geweest.
 * Onderdeel van admin/trainingsweekend.php.
 */
?>
<div class="bh-incheck">
  <?php
    $tc_ev = 'tw'; $tc_mag_bewerken = $mag_beslissen;   // begeleiders zien de code, het bestuur wijzigt hem
    require __DIR__ . '/../_tablet_code.php';

    $ick_ev          = 'tw';
    $ick_titel       = 'Inchecken trainingsweekend';
    $ick_sub         = $tw_periode;
    $ick_lijst       = evenement_lijst('tw', $sid);
    $ick_mag         = $mag_meedraaien;
    $ick_mag_sluiten = $mag_beslissen;
    $ick_verder      = ['url' => tw_url('beoordelen'), 'label' => 'Naar beoordelen'];
    require __DIR__ . '/../_incheck_lijst.php';
  ?>
</div>

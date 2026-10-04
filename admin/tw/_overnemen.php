<?php
/**
 * Knop "Overnemen" voor een TW-gesprek dat een ander open heeft (bezig door X, 30 minuten).
 * Bevestigen via data-bevestig (beheer.js) met wie het nu open heeft en sinds wanneer; de
 * router (actie gesprek_overnemen) neemt alleen over als de claim nog bij die persoon ligt.
 * Alleen tonen bij bewerkrecht; de router controleert dat opnieuw.
 *
 * Contract:
 *   $ov_sol_id  int     de sollicitatie
 *   $ov_naam    string  naam van de sollicitant (voor de schermlezer)
 *   $ov_door    int     beheerder-id van wie het gesprek nu open heeft
 *   $ov_sinds   ?string sinds wanneer (DATETIME)
 */
$_ov_wie   = evenement_claim_naam($ov_door);
$_ov_tijd  = $ov_sinds ? date('H:i', strtotime((string)$ov_sinds)) : '';
$_ov_zin   = 'Gesprek overnemen? ' . $_ov_wie . ' heeft het open' . ($_ov_tijd !== '' ? ' sinds ' . $_ov_tijd : '') . '. '
           . 'Wat daar nog niet is vastgelegd, kan daarna niet meer worden opgeslagen.';
?>
<form method="post" class="bh-twovernemen" data-bevestig="<?= h($_ov_zin) ?>">
  <?= csrf_input() ?>
  <input type="hidden" name="actie" value="gesprek_overnemen">
  <input type="hidden" name="sol_id" value="<?= (int)$ov_sol_id ?>">
  <input type="hidden" name="van" value="<?= (int)$ov_door ?>">
  <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Overnemen<span class="bh-vw"> (gesprek met <?= h((string)$ov_naam) ?>)</span></button>
</form>

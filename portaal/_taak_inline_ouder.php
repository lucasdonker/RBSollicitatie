<?php
/**
 * Partial: inline formulier "gegevens ouder/verzorger" binnen een taakrij.
 * Verwacht $_t (de taak, met inline_data: ouder_naam/ouder_email/ouder_telefoon) en $taak_terug.
 * Veldnamen en de POST-afhandeling (ouder_submit) zijn ongewijzigd: includes/portaal_taken.php.
 */
$_od  = $_t['inline_data'] ?? [];
$_af  = !empty($_t['done']);
?>
<form method="POST" class="pt-taakrij-inline" onsubmit="return ouderValideer(this)"<?= $_af ? ' hidden' : '' ?>>
  <?= csrf_input() ?>
  <input type="hidden" name="ouder_submit" value="1">
  <input type="hidden" name="terug" value="<?= h($taak_terug ?? 'start') ?>">
  <div class="pt-inline-grid">
    <input type="text" name="ouder_naam" placeholder="Naam ouder/verzorger" autocomplete="off" value="<?= h($_od['ouder_naam'] ?? '') ?>" class="ds-input" aria-label="Naam ouder/verzorger">
    <input type="email" name="ouder_email" placeholder="E-mail ouder/verzorger" autocomplete="off" value="<?= h($_od['ouder_email'] ?? '') ?>" class="ds-input" aria-label="E-mail ouder/verzorger">
  </div>
  <input type="tel" name="ouder_telefoon" placeholder="Telefoonnummer ouder/verzorger" autocomplete="off" value="<?= h($_od['ouder_telefoon'] ?? '') ?>" class="ds-input" aria-label="Telefoonnummer ouder/verzorger">
  <div class="ouder-fout pt-inline-fout" hidden role="alert"></div>
  <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">
    <span class="btn-tekst"><?= $_af ? 'Opnieuw versturen' : 'Versturen naar ouder' ?></span>
    <span class="btn-spinner" hidden>Bezig…</span>
  </button>
</form>

<?php
/**
 * Partial: "Kom je op de selectiedag / het trainingsweekend?" binnen een taakrij.
 * Verwacht $_t (de taak, inline_data: ev/stand/reden) en $taak_terug.
 * Werkt zonder JavaScript: een keuze, een reden en één knop. De reden bij "ik kan niet" is
 * verplicht; dat controleert de server (includes/evenement.php, evenement_bevestigen()).
 * POST-afhandeling: deelname_submit in includes/portaal_taken.php.
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$_dd = $_t['inline_data'] ?? [];
$_af = !empty($_t['done']);
$_id = 'dn-' . h((string)($_dd['ev'] ?? ''));
?>
<form method="POST" class="pt-taakrij-inline"<?= $_af ? ' hidden' : '' ?>>
  <?= csrf_input() ?>
  <input type="hidden" name="deelname_submit" value="1">
  <input type="hidden" name="ev" value="<?= h((string)($_dd['ev'] ?? '')) ?>">
  <input type="hidden" name="terug" value="<?= h($taak_terug ?? 'start') ?>">
  <fieldset class="pt-deelname-keuze">
    <legend class="pt-inline-legend">Laat het ons weten</legend>
    <label class="pt-deelname-optie"><input type="radio" name="stand" value="komt" <?= ($_dd['stand'] ?? '') === 'komt' ? 'checked' : '' ?> required> Ik kom</label>
    <label class="pt-deelname-optie"><input type="radio" name="stand" value="komt_niet" <?= ($_dd['stand'] ?? '') === 'komt_niet' ? 'checked' : '' ?>> Ik kan niet</label>
  </fieldset>
  <label for="<?= $_id ?>-reden" class="pt-inline-legend">Kun je niet? Vertel kort waarom (verplicht)</label>
  <textarea id="<?= $_id ?>-reden" name="reden" rows="2" maxlength="300" class="ds-input"><?= h((string)($_dd['reden'] ?? '')) ?></textarea>
  <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">
    <span class="btn-tekst">Doorgeven</span>
    <span class="btn-spinner" hidden>Bezig…</span>
  </button>
</form>

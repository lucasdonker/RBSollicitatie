<?php
/**
 * Partial: "Kom je op de selectiedag / het trainingsweekend?" binnen een taakrij.
 * Verwacht $_t (de taak, inline_data: ev/stand/reden) en $taak_terug.
 * Werkt zonder JavaScript: een keuze, een opmerking en één knop. De opmerking wordt altijd
 * bewaard en is verplicht bij "ik kan niet"; dat controleert de server (includes/evenement.php,
 * evenement_bevestigen()).
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
  <?php // Trainingsweekend: eerder weg als eigen veld (dag + tijd), zodat het bestuur je gesprek op tijd plant.
    $_dagen = (array)($_dd['dagen'] ?? []);
    $_weg   = (string)($_dd['eerder_weg'] ?? '');
    if (($_dd['ev'] ?? '') === 'tw' && $_dagen): ?>
  <fieldset class="pt-deelname-weg">
    <legend class="pt-inline-legend">Moet je eerder weg? Dan plannen we je gesprek op tijd.</legend>
    <label class="pt-deelname-optie"><input type="checkbox" name="eerder_weg_aan" value="1"<?= $_weg !== '' ? ' checked' : '' ?>> Ja, ik moet eerder weg</label>
    <div class="pt-deelname-wegtijd">
      <label class="pt-inline-legend" for="<?= $_id ?>-wegdag">Welke dag</label>
      <select id="<?= $_id ?>-wegdag" name="eerder_weg_dag" class="ds-input">
        <?php foreach ($_dagen as $_d): ?><option value="<?= h($_d) ?>"<?= substr($_weg, 0, 10) === $_d ? ' selected' : '' ?>><?= h(datum_dag_nl($_d)) ?></option><?php endforeach; ?>
      </select>
      <label class="pt-inline-legend" for="<?= $_id ?>-wegtijd">Hoe laat</label>
      <input type="time" id="<?= $_id ?>-wegtijd" name="eerder_weg_tijd" class="ds-input" value="<?= h(substr($_weg, 11, 5)) ?>">
    </div>
  </fieldset>
  <?php endif; ?>
  <label for="<?= $_id ?>-reden" class="pt-inline-legend">Opmerking</label>
  <textarea id="<?= $_id ?>-reden" name="reden" rows="2" maxlength="300" class="ds-input" aria-describedby="<?= $_id ?>-hint"><?= h((string)($_dd['reden'] ?? '')) ?></textarea>
  <span class="ds-veld-hint" id="<?= $_id ?>-hint">Kun je niet? Vertel kort waarom (verplicht). Kom je later? Zet het hier ook.</span>
  <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">
    <span class="btn-tekst">Doorgeven</span>
    <span class="btn-spinner" hidden>Bezig…</span>
  </button>
</form>

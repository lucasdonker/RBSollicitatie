<?php
/**
 * Partial: één taakrij (ontwerp: icoon 40px, titel, sub, rechts chevron / "Bekijken" / knop).
 * Verwacht $taak (contract in includes/portaal_taken.php), $taak_terug, optioneel $taak_eerste
 * (eerste open rij → oranje knop op desktop). Rijen met inline_form tonen het formulier eronder;
 * rijen met afvink tonen rechts "Gedaan" (open) of "Ongedaan maken" (afgerond) en, als er een url is,
 * een externe link in de subregel.
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$_t      = $taak;
$_inline = $_t['inline_form'] ?? null;
$_afvink = !empty($_t['afvink']) && !empty($_t['db_id']);
$_klik   = !empty($_t['url']) && !$_inline && !$_afvink;
$_url    = $_klik ? portaal_url_met_terug($_t['url'], $taak_terug ?? 'start') : '';
$_tag    = $_klik ? 'a' : 'div';
$_cls    = 'pt-taakrij' . (!empty($_t['done']) ? ' gedaan' : '') . ($_inline ? ' inline' : '') . ($_afvink ? ' afvink' : '');
$_eerste = !empty($taak_eerste);
$_extern = !empty($_t['extern']);
?>
<<?= $_tag ?><?= $_klik ? ' href="' . h($_url) . '"' . ($_extern ? ' target="_blank" rel="noopener"' : '') : '' ?> class="<?= $_cls ?>" data-taak="<?= h($_t['id']) ?>">
  <span class="pt-taakrij-icoon <?= h($_t['kleur'] ?? 'accent') ?>"><?= icoon(!empty($_t['done']) ? 'vink' : ($_t['icoon'] ?? 'document'), 20) ?></span>
  <span class="pt-taakrij-tekst">
    <span class="pt-taakrij-titel"><?= h($_t['titel']) ?></span>
    <?php if (($_t['sub'] ?? '') !== ''): ?><span class="pt-taakrij-sub"><?= h($_t['sub']) ?></span><?php endif; ?>
    <?php if ($_afvink && !empty($_t['url'])): ?>
      <a href="<?= h($_t['url']) ?>" class="pt-taakrij-link" target="_blank" rel="noopener"><?= icoon('koppeling', 14) ?>Openen</a>
    <?php endif; ?>
    <?php if ($_inline === 'ouder') require __DIR__ . '/_taak_inline_ouder.php'; ?>
    <?php if ($_inline === 'deelname') require __DIR__ . '/_taak_inline_deelname.php'; ?>
  </span>
  <?php if ($_afvink): ?>
    <form method="POST" class="pt-taakrij-afvink">
      <?= csrf_input() ?>
      <input type="hidden" name="taak_toggle" value="1">
      <input type="hidden" name="taak_id" value="<?= (int)$_t['db_id'] ?>">
      <input type="hidden" name="terug" value="<?= h($taak_terug ?? 'start') ?>">
      <?php if (!empty($_t['done'])): ?>
        <button type="submit" class="pt-taakrij-actie">Ongedaan maken</button>
      <?php else: ?>
        <button type="submit" class="pt-taakrij-gedaan"><?= icoon('vink', 16) ?>Gedaan</button>
      <?php endif; ?>
    </form>
  <?php elseif (!empty($_t['herinner_ond'])): ?>
    <?php /* De ouder is aan zet en heeft de mail niet (meer). Dit stuurt de link
             opnieuw, zodat de sollicitant daarvoor niet hoeft te bellen. */ ?>
    <form method="POST" class="pt-taakrij-afvink">
      <?= csrf_input() ?>
      <input type="hidden" name="ouder_herinner" value="1">
      <input type="hidden" name="ond_id" value="<?= (int)$_t['herinner_ond'] ?>">
      <input type="hidden" name="terug" value="<?= h($taak_terug ?? 'start') ?>">
      <button type="submit" class="pt-taakrij-actie"><?= icoon('mail', 15) ?> Link opnieuw sturen</button>
    </form>
  <?php elseif ($_inline && !empty($_t['done'])): ?>
    <button type="button" class="pt-taakrij-actie" data-toggle-inline>Wijzigen</button>
  <?php elseif ($_klik && !empty($_t['done']) && !empty($_t['actie_label'])): ?>
    <span class="pt-taakrij-actie"><?= h($_t['actie_label']) ?></span>
  <?php elseif ($_klik): ?>
    <span class="pt-taakrij-knop<?= $_eerste ? ' primair' : '' ?>"><?= h($_t['knop_label'] ?? ($_eerste ? 'Invullen' : 'Openen')) ?></span>
    <span class="pt-taakrij-chevron"><?= icoon('chevron', 18) ?></span>
  <?php endif; ?>
</<?= $_tag ?>>

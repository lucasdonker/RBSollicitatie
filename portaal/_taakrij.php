<?php
/**
 * Partial: één taakrij (ontwerp: icoon 40px, titel, sub, rechts chevron / "Bekijken" / knop).
 * Verwacht $taak (contract in includes/portaal_taken.php), $taak_terug, optioneel $taak_eerste
 * (eerste open rij → oranje knop op desktop). Rijen met inline_form tonen het formulier eronder.
 */
$_t      = $taak;
$_inline = $_t['inline_form'] ?? null;
$_klik   = !empty($_t['url']) && !$_inline;
$_tag    = $_klik ? 'a' : 'div';
$_cls    = 'pt-taakrij' . (!empty($_t['done']) ? ' gedaan' : '') . ($_inline ? ' inline' : '');
$_eerste = !empty($taak_eerste);
?>
<<?= $_tag ?><?= $_klik ? ' href="' . h($_t['url']) . '"' : '' ?> class="<?= $_cls ?>" data-taak="<?= h($_t['id']) ?>">
  <span class="pt-taakrij-icoon <?= h($_t['kleur'] ?? 'accent') ?>"><?= icoon(!empty($_t['done']) ? 'vink' : ($_t['icoon'] ?? 'document'), 20) ?></span>
  <span class="pt-taakrij-tekst">
    <span class="pt-taakrij-titel"><?= h($_t['titel']) ?></span>
    <?php if (($_t['sub'] ?? '') !== ''): ?><span class="pt-taakrij-sub"><?= h($_t['sub']) ?></span><?php endif; ?>
    <?php if ($_inline === 'ouder') require __DIR__ . '/_taak_inline_ouder.php'; ?>
  </span>
  <?php if ($_inline && !empty($_t['done'])): ?>
    <button type="button" class="pt-taakrij-actie" data-toggle-inline>Wijzigen</button>
  <?php elseif ($_klik && !empty($_t['done']) && !empty($_t['actie_label'])): ?>
    <span class="pt-taakrij-actie"><?= h($_t['actie_label']) ?></span>
  <?php elseif ($_klik): ?>
    <span class="pt-taakrij-knop<?= $_eerste ? ' primair' : '' ?>"><?= $_eerste ? 'Invullen' : 'Openen' ?></span>
    <span class="pt-taakrij-chevron"><?= icoon('chevron', 18) ?></span>
  <?php endif; ?>
</<?= $_tag ?>>

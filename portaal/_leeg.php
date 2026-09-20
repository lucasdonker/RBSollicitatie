<?php
/**
 * Partial: lege staat "Je bent helemaal bij" (ontwerp 02/08).
 * Verwacht $leeg_titel, $leeg_tekst; optioneel $leeg_groot (bool) en $leeg_knop = ['url','label'].
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$leeg_groot = $leeg_groot ?? false;
$leeg_knop  = $leeg_knop ?? null;
?>
<div class="pt-leeg<?= $leeg_groot ? ' groot' : '' ?>">
  <span class="pt-leeg-icoon"><?= icoon('vink', $leeg_groot ? 26 : 24) ?></span>
  <span class="pt-leeg-titel"><?= h($leeg_titel) ?></span>
  <span class="pt-leeg-tekst"><?= h($leeg_tekst) ?></span>
  <?php if ($leeg_knop): ?><a href="<?= h($leeg_knop['url']) ?>" class="ds-btn ds-btn-secundair"><?= h($leeg_knop['label']) ?></a><?php endif; ?>
</div>
<?php $leeg_groot = false; $leeg_knop = null; ?>

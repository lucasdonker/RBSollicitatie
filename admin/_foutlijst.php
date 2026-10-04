<?php
/**
 * Partial: wat er mis is aan een formulier, als lijst boven het formulier. Niets opgeslagen of
 * verstuurd; de invoer staat er nog. Toont niets als de lijst leeg is.
 *
 * Verwacht:
 *   $fouten        string[]  de fouten in woorden
 *   $fouten_titel  ?string   kop (standaard: "Niet opgeslagen")
 */
$fouten = array_values(array_filter((array)($fouten ?? []), 'strlen'));
if ($fouten): ?>
<div class="ds-melding ds-melding-error bh-blokkade" role="alert">
  <?= icoon('waarschuwing', 18) ?>
  <div>
    <strong><?= h($fouten_titel ?? 'Niet opgeslagen') ?></strong>
    <ul class="bh-foutlijst">
      <?php foreach ($fouten as $_f): ?><li><?= h($_f) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif;
$fouten = []; $fouten_titel = null;

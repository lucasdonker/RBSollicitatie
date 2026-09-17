<?php
/**
 * Partial: takenlijst-kaart (ontwerp 02/08): één witte kaart, padding 6, rijen met scheidingslijn.
 * Verwacht:
 *   $lijst      array   taken (contract: includes/portaal_taken.php)
 *   $taak_terug string  'start' | 'taken' (waar POST-acties naar terugkeren)
 *   $taak_knop  bool    optioneel: op desktop knoppen i.p.v. chevrons (startpagina)
 * Lege lijst → niets (de pagina toont zelf een lege staat).
 */
$taak_terug = $taak_terug ?? 'start';
$taak_knop  = $taak_knop ?? false;
if (!empty($lijst)): ?>
<div class="pt-takenlijst<?= $taak_knop ? ' knoppen' : '' ?>">
  <?php foreach (array_values($lijst) as $_i => $taak): ?>
    <?php if ($_i > 0): ?><div class="pt-taakrij-scheiding"></div><?php endif; ?>
    <?php $taak_eerste = ($_i === 0 && empty($taak['done'])); require __DIR__ . '/_taakrij.php'; ?>
  <?php endforeach; ?>
</div>
<?php endif;
$taak_knop = false;

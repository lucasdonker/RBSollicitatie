<?php
/**
 * Partial: lege staat.
 *
 * "Leeg is vaak goed nieuws" (ontwerpfilosofie §8): een lege bellijst betekent dat
 * iedereen een besluit heeft, geen grijze streep. Daarom is de standaardtoon neutraal
 * en kun je hem op 'groen' zetten als leeg juist gunstig is.
 *
 * Verwacht:
 *   $leeg_titel  string   korte kop, bijv. "Bellijst is leeg"
 *   $leeg_tekst  string   optioneel: één zin die zegt waaróm het leeg is
 *   $leeg_toon   string   optioneel: fasekleur voor het icoonvlak (standaard grijs)
 *   $leeg_icoon  string   optioneel: naam uit includes/iconen.php (standaard 'lijst')
 *   $leeg_knop   array    optioneel: ['tekst' => …, 'url' => …]
 *
 * Ruimt zijn eigen vlaggen op, zodat een tweede aanroep op dezelfde pagina niet erft.
 */
$leeg_titel = $leeg_titel ?? '';
$leeg_tekst = $leeg_tekst ?? '';
$leeg_toon  = $leeg_toon ?? 'grijs';
$leeg_icoon = $leeg_icoon ?? 'lijst';
$leeg_knop  = $leeg_knop ?? null;
?>
<div class="bh-leeg ds-fase-<?= h($leeg_toon) ?>">
  <span class="bh-leeg-icoon"><?= icoon($leeg_icoon, 19) ?></span>
  <?php if ($leeg_titel !== ''): ?>
    <span class="bh-leeg-titel"><?= h($leeg_titel) ?></span>
  <?php endif; ?>
  <?php if ($leeg_tekst !== ''): ?>
    <span class="bh-leeg-tekst"><?= h($leeg_tekst) ?></span>
  <?php endif; ?>
  <?php if ($leeg_knop && !empty($leeg_knop['url'])): ?>
    <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($leeg_knop['url']) ?>"><?= h($leeg_knop['tekst'] ?? 'Openen') ?></a>
  <?php endif; ?>
</div>
<?php
$leeg_titel = ''; $leeg_tekst = ''; $leeg_toon = 'grijs'; $leeg_icoon = 'lijst'; $leeg_knop = null;

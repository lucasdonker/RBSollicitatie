<?php
/**
 * Partial: de selectiedag-cijfers per test en criterium (gemiddelde van alle beoordelaars),
 * met per beoordelaar het cijfer als er meer dan één was. Dossier en TW-gesprek.
 *
 * Verwacht: $scs_dossier  array  uitkomst van bh_dossier()
 *           $scs_kaarten  bool   optioneel: elke test een eigen kaart (dossier); standaard één blok
 */
$_scores = $scs_dossier['scores'] ?? [];
if (!$_scores) return;
$_per_test = bh_dossier_score_per_test($scs_dossier);
?>
<?php foreach ($_scores as $_test => $_criteria): ?>
<div class="<?= !empty($scs_kaarten) ? 'ds-kaart ds-kaart-p' : 'bh-sdscores-test' ?>">
  <div class="bh-kaartkop">
    <h2><?= h(ucfirst((string)$_test)) ?></h2>
    <?php if (isset($_per_test[$_test])): ?>
      <span class="bh-meta bh-num"><?= h(number_format($_per_test[$_test], 1, ',', '')) ?> gemiddeld</span>
    <?php endif; ?>
  </div>
  <div class="bh-scorebalken bh-scorebalken-los">
    <?php foreach ($_criteria as $_crit => $_sd): ?>
      <div class="bh-scorebalk">
        <span class="bh-scorebalk-naam"><?= h(ucfirst(str_replace('_', ' ', (string)$_crit))) ?></span>
        <span class="bh-scorebalk-spoor">
          <span class="bh-scorebalk-vul" style="--w: <?= (int)round(((float)$_sd['gem']) / 5 * 100) ?>%"></span>
        </span>
        <span class="bh-scorebalk-getal bh-num"><?= h(number_format((float)$_sd['gem'], 1, ',', '')) ?></span>
      </div>
      <?php if (count($_sd['allen']) > 1): ?>
        <div class="bh-scorebalk-wie">
          <?php foreach ($_sd['allen'] as $_door => $_sc): ?>
            <span><?= h($_door) ?> <strong class="bh-num"><?= (int)$_sc ?></strong></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
<?php $scs_kaarten = false;

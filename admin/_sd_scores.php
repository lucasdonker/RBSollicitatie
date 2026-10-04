<?php
/**
 * Partial: de selectiedag-cijfers per test en criterium (gemiddelde van alle beoordelaars),
 * met per beoordelaar het cijfer als er meer dan één was. Dossier, TW-gesprek en nabespreking.
 * Namen van test en criterium komen uit selectie_criteria() (niet de sleutel: "warmup").
 *
 * Verwacht: $scs_dossier  array  uitkomst van bh_dossier()                       — óf —
 *           $scs_scores   array  de scores zelf: [test][criterium] => ['allen' => [wie => cijfer], 'gem' => x]
 *           $scs_kaarten  bool   optioneel: elke test een eigen kaart (dossier); standaard één blok
 */
$_scores = $scs_scores ?? ($scs_dossier['scores'] ?? []);
$scs_scores = null;
if (!$_scores) { $scs_kaarten = false; return; }
$_per_test = bh_dossier_score_per_test(['scores' => $_scores]);
$_def = selectie_criteria();
$_crit_label = function (string $test, string $crit) use ($_def): string {
    foreach ($_def[$test]['criteria'] ?? [] as $c) if ($c['sleutel'] === $crit) return (string)$c['label'];
    return ucfirst(str_replace('_', ' ', $crit));
};
?>
<?php foreach ($_scores as $_test => $_criteria): ?>
<div class="<?= !empty($scs_kaarten) ? 'ds-kaart ds-kaart-p' : 'bh-sdscores-test' ?>">
  <div class="bh-kaartkop">
    <h2><?= h((string)($_def[$_test]['naam'] ?? ucfirst((string)$_test))) ?></h2>
    <?php if (isset($_per_test[$_test])): ?>
      <span class="bh-meta bh-num"><?= h(cijfer_nl((float)$_per_test[$_test])) ?> gemiddeld</span>
    <?php endif; ?>
  </div>
  <div class="bh-scorebalken bh-scorebalken-los">
    <?php foreach ($_criteria as $_crit => $_sd): $_laag = (float)$_sd['gem'] <= 2; ?>
      <div class="bh-scorebalk<?= $_laag ? ' laag' : '' ?>">
        <span class="bh-scorebalk-naam"><?= h($_crit_label((string)$_test, (string)$_crit)) ?></span>
        <span class="bh-scorebalk-spoor">
          <span class="bh-scorebalk-vul" style="--w: <?= (int)round(((float)$_sd['gem']) / 5 * 100) ?>%"></span>
        </span>
        <span class="bh-scorebalk-getal bh-num"><?= h(cijfer_nl((float)$_sd['gem'])) ?></span>
      </div>
      <?php if (count($_sd['allen']) > 1): ?>
        <div class="bh-scorebalk-wie">
          <?php foreach ($_sd['allen'] as $_door => $_sc): ?>
            <span><?= h((string)$_door) ?> <strong class="bh-num"><?= (int)$_sc ?></strong></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
<?php $scs_kaarten = false;

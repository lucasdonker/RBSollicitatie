<?php
/**
 * Partial: de selectiedag-cijfers in één oogopslag — het totaal en het gemiddelde per test als
 * chips, met de cijfers per onderdeel inklapbaar (admin/_sd_scores.php). Voor plekken waar de
 * cijfers ondersteunen maar niet de hoofdzaak zijn: bellijst, TW-beoordelen, samenvatting.
 *
 * Verwacht:
 *   $sck_scores  array  [test][criterium] => ['allen' => [wie => cijfer], 'gem' => x]
 *                       (selectie_scores_alle()[$id], of bh_dossier()['scores'])
 *   $sck_open    bool   optioneel: de cijfers per onderdeel staan open (standaard dicht)
 * Toont niets zonder cijfers.
 */
$_sck = $sck_scores ?? [];
$_sck_open = !empty($sck_open);
$sck_scores = null; $sck_open = false;
if (!$_sck) return;
$_sck_def  = selectie_criteria();
$_sck_tot  = selectie_gemiddelde($_sck);
$_sck_test = bh_dossier_score_per_test(['scores' => $_sck]);
?>
<div class="bh-sdkort">
  <p class="bh-sdkort-rij">
    <?php if ($_sck_tot !== null): ?>
      <span class="bh-sdkort-totaal"><strong class="bh-num"><?= h(cijfer_nl((float)$_sck_tot)) ?></strong> gemiddeld</span>
    <?php endif; ?>
    <?php foreach ($_sck_test as $_t => $_g): ?>
      <?= bh_chip(($_sck_def[$_t]['naam'] ?? ucfirst((string)$_t)) . ' ' . cijfer_nl((float)$_g), (float)$_g <= 2 ? 'rood' : 'grijs') ?>
    <?php endforeach; ?>
  </p>
  <details class="bh-klap bh-sdkort-meer"<?= $_sck_open ? ' open' : '' ?>>
    <summary><span class="bh-klap-titel">Cijfers per onderdeel</span></summary>
    <div class="bh-klap-inhoud">
      <?php $scs_scores = $_sck; require __DIR__ . '/_sd_scores.php'; ?>
    </div>
  </details>
</div>

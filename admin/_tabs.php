<?php
/**
 * Partial: fase-tabs met aantallen.
 *
 * Een tab verdwijnt als er niemand in zit (bh_fase_tabs()). Staat er iemand in een
 * fase die in deze seizoensfase niet aan de beurt is, dan komt daar onder de tabs één
 * regel over — die mensen mogen niet stil uit beeld vallen.
 *
 * Verwacht:
 *   $tabs_data   array     uitkomst van bh_fase_tabs()
 *   $tabs_actief string    huidige sleutel ('alle' of een fase)
 *   $tabs_url    callable  fn(string $sleutel): string
 *   $tabs_totaal int       aantal achter "Alle"
 *   $tabs_voet   ?string   optioneel: extra regel onder de tabs
 */
$tabs_data   = $tabs_data ?? ['tabs' => [], 'vreemd' => [], 'vreemd_aantal' => 0];
$tabs_actief = $tabs_actief ?? 'alle';
$tabs_url    = $tabs_url ?? fn($s) => '?fase=' . urlencode($s);
$tabs_totaal = $tabs_totaal ?? 0;
$tabs_voet   = $tabs_voet ?? null;
?>
<div class="bh-tabs" role="tablist" aria-label="Filter op fase">
  <a class="bh-tab<?= $tabs_actief === 'alle' ? ' actief' : '' ?>"
     href="<?= h($tabs_url('alle')) ?>" role="tab"
     aria-selected="<?= $tabs_actief === 'alle' ? 'true' : 'false' ?>">
    Alle <span class="bh-tab-getal"><?= (int)$tabs_totaal ?></span>
  </a>
  <?php foreach ($tabs_data['tabs'] as $_t): ?>
    <a class="bh-tab ds-fase-<?= h($_t['kleur']) ?><?= $tabs_actief === $_t['sleutel'] ? ' actief' : '' ?><?= $_t['actueel'] ? '' : ' bh-tab-buiten' ?>"
       href="<?= h($tabs_url($_t['sleutel'])) ?>" role="tab"
       aria-selected="<?= $tabs_actief === $_t['sleutel'] ? 'true' : 'false' ?>">
      <?= h($_t['label']) ?> <span class="bh-tab-getal"><?= (int)$_t['aantal'] ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php if ($tabs_data['vreemd_aantal'] > 0): ?>
  <?php $_n = (int)$tabs_data['vreemd_aantal']; ?>
  <div class="bh-tabs-voet">
    <?= icoon('info', 14) ?>
    <?= $_n === 1 ? '1 persoon staat' : h((string)$_n) . ' mensen staan' ?>
    in een fase die nu niet aan de beurt is
    <a href="<?= h($tabs_url($tabs_data['vreemd'][0]['sleutel'])) ?>">bekijken</a>
  </div>
<?php elseif ($tabs_voet): ?>
  <div class="bh-tabs-voet"><?= h($tabs_voet) ?></div>
<?php endif; ?>
<?php
$tabs_data = null; $tabs_actief = 'alle'; $tabs_totaal = 0; $tabs_voet = null;

<?php
/**
 * Partial: filtertabs met aantallen.
 *
 * Meestal gevuld door bh_fase_tabs(): een tab verdwijnt als er niemand in zit, en staat
 * er iemand in een fase die in deze seizoensfase niet aan de beurt is, dan komt daar
 * onder de tabs één regel over — die mensen mogen niet stil uit beeld vallen.
 *
 * Elke lijst met dezelfde vorm werkt: het ondertekeningenscherm zet er zijn eigen
 * twee tabs in. Bouw dus geen tweede tabbalk, vul deze.
 *
 * Verwacht:
 *   $tabs_data   array     ['tabs' => [ ['sleutel','label','aantal','kleur','actueel'], … ],
 *                           'vreemd' => […], 'vreemd_aantal' => int] — bijv. uit bh_fase_tabs()
 *   $tabs_actief string    huidige sleutel ('alle' of een fase)
 *   $tabs_url    callable  fn(string $sleutel): string
 *   $tabs_totaal int       aantal achter "Alle"
 *   $tabs_voet   ?string   optioneel: extra regel onder de tabs
 *   $tabs_label  ?string   optioneel: toegankelijke naam van de balk (standaard "Filter op fase")
 */
$tabs_data   = $tabs_data ?? ['tabs' => [], 'vreemd' => [], 'vreemd_aantal' => 0];
$tabs_actief = $tabs_actief ?? 'alle';
$tabs_url    = $tabs_url ?? fn($s) => '?fase=' . urlencode($s);
$tabs_totaal = $tabs_totaal ?? 0;
$tabs_voet   = $tabs_voet ?? null;
$tabs_label  = $tabs_label ?? 'Filter op fase';
?>
<?php // Elke tab is een link (nieuwe pagina), dus nav + aria-current — geen role="tab". ?>
<nav class="bh-tabs" aria-label="<?= h($tabs_label) ?>">
  <a class="bh-tab<?= $tabs_actief === 'alle' ? ' actief' : '' ?>"
     href="<?= h($tabs_url('alle')) ?>"<?= $tabs_actief === 'alle' ? ' aria-current="page"' : '' ?>>
    Alle <span class="bh-tab-getal"><?= (int)$tabs_totaal ?></span>
  </a>
  <?php foreach ($tabs_data['tabs'] as $_t): ?>
    <a class="bh-tab ds-fase-<?= h($_t['kleur']) ?><?= $tabs_actief === $_t['sleutel'] ? ' actief' : '' ?><?= $_t['actueel'] ? '' : ' bh-tab-buiten' ?>"
       href="<?= h($tabs_url($_t['sleutel'])) ?>"<?= $tabs_actief === $_t['sleutel'] ? ' aria-current="page"' : '' ?>>
      <?= h($_t['label']) ?> <span class="bh-tab-getal"><?= (int)$_t['aantal'] ?></span>
    </a>
  <?php endforeach; ?>
</nav>
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
$tabs_data = null; $tabs_actief = 'alle'; $tabs_totaal = 0; $tabs_voet = null; $tabs_label = null;

<?php
/**
 * Partial: voortgangskaart "Stap N van 5 — Naam" + percentage + balk + vijf bolletjes (ontwerp 02).
 * Verwacht $vg (portaal_voortgang()) en optioneel $vg_toelichting (alleen zichtbaar op desktop).
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$vg_toelichting = $vg_toelichting ?? '';
?>
<div class="pt-voortgang<?= !empty($vg['klaar']) ? ' klaar' : '' ?>" role="group" aria-label="Voortgang van je sollicitatie">
  <div class="pt-voortgang-kern">
    <div class="pt-voortgang-kop">
      <span class="pt-voortgang-titel">Stap <?= (int)$vg['stap'] ?> van <?= (int)$vg['totaal'] ?> — <?= h($vg['naam']) ?></span>
      <span class="pt-voortgang-pct"><?= (int)$vg['pct'] ?>%</span>
    </div>
    <div class="pt-voortgang-balk" aria-hidden="true"><div class="pt-voortgang-vul" style="width:<?= (int)$vg['pct'] ?>%"></div></div>
    <div class="pt-voortgang-bollen" aria-hidden="true">
      <?php for ($i = 0; $i < (int)$vg['totaal']; $i++): ?>
        <span class="pt-voortgang-bol<?= $i <= (int)$vg['index'] ? ' gedaan' : '' ?>"></span>
      <?php endfor; ?>
    </div>
  </div>
  <?php if ($vg_toelichting !== ''): ?><span class="pt-voortgang-toelichting"><?= h($vg_toelichting) ?></span><?php endif; ?>
</div>

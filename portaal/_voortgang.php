<?php
/**
 * Partial: voortgangskaart "Stap N van 5 — Naam" + percentage + balk + vijf bolletjes (ontwerp 02).
 *
 * Verwacht:
 *   $vg             array   portaal_voortgang()
 *   $vg_toelichting string  optioneel, alleen zichtbaar op desktop
 *   $vg_open        ?int    aantal openstaande taken; bepaalt of er "nu niets te doen" bij staat
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$vg_toelichting = $vg_toelichting ?? '';
$vg_open = isset($vg_open) ? (int)$vg_open : null;

/* "Stap 2 van 5" zegt niets over of jíj nog iets moet doen. Dat is precies wat je wilt
   weten als je het portaal opent, dus staat het erachter. */
$vg_stand = '';
if (!empty($vg['klaar']))      $vg_stand = 'je bent erdoor';
elseif ($vg_open === 0)        $vg_stand = 'nu even niets te doen';
elseif ($vg_open === 1)        $vg_stand = 'één ding open';
elseif ($vg_open !== null)     $vg_stand = $vg_open . ' dingen open';
?>
<div class="pt-voortgang<?= !empty($vg['klaar']) ? ' klaar' : '' ?>" role="group" aria-label="Voortgang van je sollicitatie">
  <div class="pt-voortgang-kern">
    <div class="pt-voortgang-kop">
      <span class="pt-voortgang-titel">Stap <?= (int)$vg['stap'] ?> van <?= (int)$vg['totaal'] ?> — <?= h($vg['naam']) ?><?php
        if ($vg_stand !== ''): ?><span class="pt-voortgang-stand"><?= h($vg_stand) ?></span><?php endif; ?></span>
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

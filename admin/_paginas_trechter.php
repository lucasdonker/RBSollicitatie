<?php
/**
 * Partial — de trechter van één landingspagina: waar haken mensen af?
 *
 * Verwacht:
 *   $id    int    het id van de pagina
 *
 * Toont per trede hoeveel mensen er kwamen en hoeveel er daarna afvielen. De balkjes zijn
 * geen opsmuk: een cijferrij van zes getallen leest niemand, een aflopende balk wel. De
 * percentages staan er altijd bij, want kleur en lengte alleen zijn geen informatie.
 *
 * "Halverwege weggegaan" staat er los onder en telt bewust niet mee in de trechter: dat
 * signaal komt van een tabblad dat naar de achtergrond gaat, en dat is een aanwijzing, geen
 * bewijs — iemand kan ook even iets opzoeken en terugkomen.
 */

if (!isset($id)) return;

$trechter = pag_trechter((int)$id);
$treden   = $trechter['treden'];
$top      = 0;
foreach ($treden as $t) $top = max($top, (int)$t['aantal']);
$verlaten = $trechter['verlaten_per_stap'];
$stap_labels = aanmeld_stappen_labels();
?>
<section class="ds-kaart ds-kaart-p pg-trechter">
  <h2 class="pg-h2">Waar haken mensen af?</h2>
  <p class="pg-sub">
    Van de bezoekers tot de verstuurde aanmelding. De percentages zijn ten opzichte van
    iedereen die aan het formulier begon.
  </p>

  <?php if ($top === 0): ?>
    <p class="ds-leeg">Er is nog niets gemeten. Zodra de pagina gepubliceerd is en er bezoekers komen, staat het hier.</p>
  <?php else: ?>
  <ol class="pg-tr-lijst">
    <?php foreach ($treden as $i => $t):
        $breedte = $top > 0 ? max(2, (int)round($t['aantal'] / $top * 100)) : 0;
        $laatste = $i === count($treden) - 1; ?>
    <li class="pg-tr-trede">
      <div class="pg-tr-kop">
        <span class="pg-tr-label"><?= h((string)$t['label']) ?></span>
        <span class="pg-tr-cijfer">
          <strong><?= (int)$t['aantal'] ?></strong>
          <?php if ($t['sleutel'] !== 'bezoek'): ?>
          <span class="pg-tr-pct"><?= (int)$t['pct_van_start'] ?>%</span>
          <?php endif; ?>
        </span>
      </div>
      <div class="pg-tr-balk">
        <span class="pg-tr-vul<?= $laatste ? ' pg-tr-vul-eind' : '' ?>" style="width:<?= $breedte ?>%"></span>
      </div>
      <?php if (!empty($t['verloop'])): ?>
      <p class="pg-tr-verloop"><?= (int)$t['verloop'] ?> gingen hier niet verder</p>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ol>

  <?php if (array_filter($verlaten)): ?>
  <div class="pg-tr-weg">
    <h3 class="pg-tr-h3">Halverwege weggegaan</h3>
    <p class="pg-sub">
      Het tabblad ging naar de achtergrond terwijl het formulier openstond. Een aanwijzing,
      geen zekerheid: sommigen komen daarna gewoon terug.
    </p>
    <ul class="pg-tr-weglijst">
      <?php ksort($verlaten); foreach ($verlaten as $stap => $aantal):
          if (!$aantal) continue; ?>
      <li>
        <span>Bij stap <?= (int)$stap ?><?= isset($stap_labels[$stap]) ? ' · ' . h($stap_labels[$stap]) : '' ?></span>
        <strong><?= (int)$aantal ?></strong>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</section>

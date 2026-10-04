<?php
/**
 * Partial — de trechter van één landingspagina: waar haken mensen af?
 *
 * Verwacht (een van beide):
 *   $id           int    het id van de pagina, of
 *   $pt_trechter  array  een kant-en-klare trechter (pag_bronnen_overzicht()['trechter']) voor het totaal
 * Optioneel: $pt_titel, $pt_sub (kop en uitleg), $pt_leeg (tekst zonder metingen).
 *
 * De trede met de relatief grootste uitval staat er met tekst bij ("Hier haken de meesten af").
 *
 * Toont per trede hoeveel mensen er kwamen en hoeveel er daarna afvielen. De balkjes zijn
 * geen opsmuk: een cijferrij van zes getallen leest niemand, een aflopende balk wel. De
 * percentages staan er altijd bij, want kleur en lengte alleen zijn geen informatie.
 *
 * "Halverwege weggegaan" staat er los onder en telt bewust niet mee in de trechter: dat
 * signaal komt van een tabblad dat naar de achtergrond gaat, en dat is een aanwijzing, geen
 * bewijs — iemand kan ook even iets opzoeken en terugkomen.
 */

if (!isset($id) && !isset($pt_trechter)) return;

$trechter = isset($pt_trechter) ? ['treden' => $pt_trechter, 'verlaten_per_stap' => []] : pag_trechter((int)$id);
$pt_titel = $pt_titel ?? 'Waar haken mensen af?';
$pt_leeg  = $pt_leeg ?? 'Er is nog niets gemeten. Zodra de pagina gepubliceerd is en er bezoekers komen, staat het hier.';
$pt_sub   = $pt_sub ?? 'Van de bezoekers tot de verstuurde aanmelding. De percentages zijn ten opzichte van iedereen die aan het formulier begon.';
$treden   = $trechter['treden'];
$top      = 0;
foreach ($treden as $t) $top = max($top, (int)$t['aantal']);
$verlaten = $trechter['verlaten_per_stap'];
$stap_labels = aanmeld_stappen_labels();
?>
<section class="ds-kaart ds-kaart-p pg-trechter">
  <h2 class="pg-h2"><?= h($pt_titel) ?></h2>
  <p class="pg-sub"><?= h($pt_sub) ?></p>

  <?php if ($top === 0): ?>
    <p class="ds-leeg"><?= h($pt_leeg) ?></p>
  <?php else: ?>
  <ol class="pg-tr-lijst">
    <?php foreach ($treden as $i => $t):
        $breedte = $top > 0 ? max(2, (int)round($t['aantal'] / $top * 100)) : 0;
        $laatste = $i === count($treden) - 1; ?>
    <li class="pg-tr-trede<?= !empty($t['grootste']) ? ' pg-tr-grootste' : '' ?>">
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
        <span class="pg-tr-vul<?= $laatste ? ' pg-tr-vul-eind' : '' ?>" style="--w: <?= $breedte ?>%"></span>
      </div>
      <?php if (!empty($t['verloop'])): ?>
      <p class="pg-tr-verloop"><?= (int)$t['verloop'] ?> gingen hier niet verder<?php if (!empty($t['grootste'])): ?> <strong>· hier haken de meesten af</strong><?php endif; ?></p>
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
<?php $pt_trechter = $pt_titel = $pt_sub = $pt_leeg = null;

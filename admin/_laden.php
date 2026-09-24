<?php
/**
 * Partial: laadstaat (skelet).
 *
 * Toont de vórm van wat komt in grijstinten, zodat de pagina niet verspringt zodra de
 * gegevens er zijn (ontwerpfilosofie §8). Geen spinner: een spinner zegt "wacht",
 * een skelet zegt "dit krijg je".
 *
 * Verwacht:
 *   $laden_rijen  int      optioneel: aantal skeletrijen (standaard 3)
 *   $laden_tegel  bool     optioneel: vierkantje vooraan, voor lijsten met avatar (standaard true)
 *
 * aria-hidden: voor een schermlezer is dit decoratie; de status staat in aria-busy
 * op de container eromheen.
 */
$laden_rijen = max(1, (int)($laden_rijen ?? 3));
$laden_tegel = $laden_tegel ?? true;
$_breedtes   = [['45%', '70%'], ['38%', '60%'], ['52%', '64%'], ['42%', '66%'], ['48%', '58%']];
?>
<div aria-busy="true" aria-label="Bezig met laden">
  <?php for ($_i = 0; $_i < $laden_rijen; $_i++): $_b = $_breedtes[$_i % count($_breedtes)]; ?>
  <div class="bh-skelet-rij" aria-hidden="true">
    <?php if ($laden_tegel): ?><span class="bh-skelet-tegel"></span><?php endif; ?>
    <span class="bh-skelet-regels">
      <span class="bh-skelet-regel" style="--w: <?= h($_b[0]) ?>"></span>
      <span class="bh-skelet-regel kort" style="--w: <?= h($_b[1]) ?>"></span>
    </span>
  </div>
  <?php endfor; ?>
</div>
<?php
$laden_rijen = 3; $laden_tegel = true;

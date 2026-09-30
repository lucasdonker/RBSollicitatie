<?php
/**
 * Loonplanning — "Waar zit het geld?": onder het budget, standaard ingeklapt. Waar je kunt schuiven
 * als het krap is: de duurste mensen en weken, weken met meer mensen dan de norm, en wat er nog
 * bij kan komen (opgegeven maar niet gepland).
 * Onderdeel van admin/lp/_kop.php. Verwacht $geld (bh_lp_geld()) en $scenario_id.
 * Stand bij het laden van de pagina (de kop erboven werkt wel direct bij).
 */
$_duur  = $geld['personen'][0] ?? null;
$_wk    = $geld['weken'][0] ?? null;
$_sam   = [];
if ($_duur) $_sam[] = 'Duurste: ' . $_duur['naam'] . ' (' . euro($_duur['kosten']) . ')';
if ($_wk)   $_sam[] = 'duurste week W' . $_wk['week'] . ' (' . euro($_wk['kosten']) . ')';
if ($geld['boven_norm']) $_sam[] = count($geld['boven_norm']) . ' ' . (count($geld['boven_norm']) === 1 ? 'week' : 'weken') . ' boven de norm';
?>
<details class="bh-klap bh-lp-geld" data-klap-id="lp-geld">
  <summary>
    <span class="bh-klap-titel">Waar zit het geld?</span>
    <span class="bh-klap-sam"><?= $_sam ? h(implode(' · ', $_sam)) : 'Nog niets gepland' ?></span>
  </summary>
  <div class="bh-klap-inhoud bh-lp-geldraster">
    <section>
      <h3>Duurste personen</h3>
      <?php if ($geld['personen']): ?>
        <ol class="bh-lp-geldlijst">
          <?php foreach ($geld['personen'] as $_p): ?>
            <li><span><?= h($_p['naam']) ?></span><strong><?= h(euro($_p['kosten'])) ?></strong>
              <span class="bh-meta"><?= $_p['dagen'] ?> d · <?= $_p['aandeel'] ?>% van het totaal</span></li>
          <?php endforeach; ?>
        </ol>
        <a class="bh-doc-actie" href="?scenario=<?= (int)$scenario_id ?>&amp;sort=kosten">Raster sorteren op kosten</a>
      <?php else: ?>
        <p class="bh-meta">Nog niemand betaald ingepland.</p>
      <?php endif; ?>
    </section>
    <section>
      <h3>Duurste weken</h3>
      <?php if ($geld['weken']): ?>
        <ol class="bh-lp-geldlijst">
          <?php foreach ($geld['weken'] as $_w): ?>
            <li><span>Week <?= $_w['week'] ?></span><strong><?= h(euro($_w['kosten'])) ?></strong>
              <span class="bh-meta"><?= $_w['mensen'] ?> <?= $_w['mensen'] === 1 ? 'persoon' : 'mensen' ?></span></li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <p class="bh-meta">Nog geen kosten.</p>
      <?php endif; ?>
    </section>
    <section>
      <h3>Ruimte om te schuiven</h3>
      <ul class="bh-lp-geldlijst">
        <?php foreach ($geld['boven_norm'] as $_b): ?>
          <li><span>Week <?= $_b['week'] ?></span><strong><?= $_b['teveel'] ?> boven de norm</strong>
            <span class="bh-meta"><?= h(euro($_b['kosten'])) ?> die week</span></li>
        <?php endforeach; ?>
        <?php if (!$geld['boven_norm']): ?>
          <li><span class="bh-meta"><?= $geld['norm'] > 0 ? 'Geen week met meer mensen dan de norm.' : 'Geen bezettingsnorm ingesteld (Instellingen › Bezetting).' ?></span></li>
        <?php endif; ?>
        <?php if ($geld['open']['dagen'] > 0): ?>
          <li><span>Opgegeven, niet gepland</span><strong><?= h(euro($geld['open']['kosten'])) ?></strong>
            <span class="bh-meta"><?= $geld['open']['dagen'] ?> dagen: dit komt erbij als je alles inplant</span></li>
        <?php endif; ?>
        <?php if ($geld['onbetaald'] > 0): ?>
          <li><span>Onbetaalde dagen</span><strong><?= $geld['onbetaald'] ?></strong>
            <span class="bh-meta">tellen mee in de dekking, niet in de kosten</span></li>
        <?php endif; ?>
      </ul>
    </section>
    <p class="bh-meta bh-lp-geldvoet">Stand bij het openen van de pagina. Na plannen: pagina vernieuwen.</p>
  </div>
</details>

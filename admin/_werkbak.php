<?php
/**
 * Partial: de werkbak op Vandaag — alles wat op jou wacht, uit alle modules, met een knop
 * naar de plek waar je het afhandelt. Getal én zin, nooit alleen kleur.
 *
 * Verwacht: $werkbak  array  uit bh_vandaag_werkbak()
 */
$werkbak = $werkbak ?? [];
?>
<section class="ds-kaart ds-kaart-p bh-werkbak" aria-labelledby="bh-werkbak-kop">
  <div class="bh-kaartkop">
    <h2 id="bh-werkbak-kop">Wacht ook op jou</h2>
    <?php if ($werkbak): ?><span class="bh-meta"><?= count($werkbak) ?> <?= count($werkbak) === 1 ? 'punt' : 'punten' ?></span><?php endif; ?>
  </div>
  <?php if (!$werkbak): ?>
    <p class="bh-meta bh-werkbak-leeg"><?= icoon('vink-cirkel', 16) ?> Verder ligt er niets op je te wachten.</p>
  <?php else: ?>
  <ul class="bh-werkbak-lijst">
    <?php foreach ($werkbak as $_w): ?>
      <li class="bh-werkbak-rij ds-fase-<?= h($_w['toon']) ?>">
        <span class="bh-werkbak-icoon" aria-hidden="true"><?= icoon($_w['icoon'], 18) ?></span>
        <span class="bh-werkbak-tekst"><strong class="bh-num"><?= (int)$_w['aantal'] ?></strong> <?= h($_w['tekst']) ?></span>
        <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($_w['url']) ?>"><?= h($_w['knop']) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php
$werkbak = null;

<?php
/**
 * Partial: wat iemand over de zomer opgaf, als lijst — periodes, vakanties, losse vrije dagen,
 * verzoeken om minder dagen (met status), liever vrij, de norm van het bestuur, de opmerking en wat
 * er in het kennismakingsformulier stond. Voor het TW-gesprek (naast de inzet) en het dossier.
 *
 * Verwacht:
 *   $opo       array   bh_opgave_overzicht()
 *   $opo_naam  string  voornaam, voor de kop ("Wat Lieke opgaf")
 * Zonder opgave: een korte zin, en wat er in het formulier stond als dat er is.
 */
$_o = $opo ?? [];
$_n = (string)($opo_naam ?? '');
$opo = null; $opo_naam = null;
$_status = ['open' => ['open verzoek', 'oranje'], 'goedgekeurd' => ['goedgekeurd', 'groen'], 'afgewezen' => ['afgewezen', 'grijs']];
?>
<section class="ds-kaart ds-kaart-p bh-opgave" aria-labelledby="opgave-kop">
  <div class="bh-kaartkop">
    <h2 id="opgave-kop"><?= $_n !== '' ? 'Wat ' . h($_n) . ' opgaf' : 'Opgave' ?></h2>
    <span class="bh-meta">
      <?php if (!empty($_o['leeg'])): ?>nog niets opgegeven
      <?php else: ?><?= !empty($_o['voorlopig']) ? 'voorlopig' : 'definitief' ?><?= !empty($_o['bijgewerkt']) ? ' · bijgewerkt ' . h(datum_kort_nl(substr((string)$_o['bijgewerkt'], 0, 10))) : '' ?><?php endif; ?>
    </span>
  </div>
  <dl class="bh-opgave-lijst">
    <?php if (!empty($_o['periodes'])): ?>
      <div><dt>Beschikbaar</dt><dd><?php foreach ($_o['periodes'] as $_r): ?><span class="bh-opgave-regel"><?= h($_r['tekst']) ?></span><?php endforeach; ?></dd></div>
    <?php elseif (!empty($_o['leeg'])): ?>
      <div><dt>Beschikbaar</dt><dd class="zacht">Nog niet doorgegeven in het portaal.</dd></div>
    <?php endif; ?>
    <?php if (!empty($_o['norm'])): ?><div><dt>Afgesproken norm</dt><dd><?= h((string)$_o['norm']) ?></dd></div><?php endif; ?>
    <div><dt>Vakantie</dt><dd>
      <?php if (empty($_o['afwezig'])): ?><span class="zacht">Geen</span><?php endif; ?>
      <?php foreach ($_o['afwezig'] ?? [] as $_r): ?><span class="bh-opgave-regel"><?= h($_r['tekst']) ?><?php if ($_r['label'] !== ''): ?> <span class="bh-meta"><?= h($_r['label']) ?></span><?php endif; ?></span><?php endforeach; ?>
    </dd></div>
    <?php if (!empty($_o['vrij'])): ?>
      <div><dt>Losse vrije dagen</dt><dd><?php foreach ($_o['vrij'] as $_r): ?><span class="bh-opgave-regel"><?= h($_r['tekst']) ?><?php if ($_r['reden'] !== ''): ?> <span class="bh-meta"><?= h($_r['reden']) ?></span><?php endif; ?><?php if ($_r['bestuur']): ?> <span class="bh-meta">(door het bestuur)</span><?php endif; ?></span><?php endforeach; ?></dd></div>
    <?php endif; ?>
    <?php if (!empty($_o['verzoeken'])): ?>
      <div><dt>Minder dagen</dt><dd><?php foreach ($_o['verzoeken'] as $_r): $_st = $_status[$_r['status']] ?? [$_r['status'], 'grijs']; ?>
        <span class="bh-opgave-regel"><?= h($_r['tekst']) ?> <?= bh_badge($_st[0], $_st[1]) ?><?php if ($_r['toelichting'] !== ''): ?><br><span class="bh-meta">"<?= h($_r['toelichting']) ?>"</span><?php endif; ?></span>
      <?php endforeach; ?></dd></div>
    <?php endif; ?>
    <?php if (($_o['liever'] ?? '') !== ''): ?><div><dt>Liever vrij</dt><dd><?= h((string)$_o['liever']) ?></dd></div><?php endif; ?>
    <?php if (!empty($_o['opmerking'])): ?><div><dt>Opmerking</dt><dd><p class="bh-opgave-citaat"><?= nl2br(h((string)$_o['opmerking'])) ?></p></dd></div><?php endif; ?>
    <?php if (!empty($_o['formulier']) || !empty($_o['uitzonderingen'])): ?>
      <div><dt>In het formulier</dt><dd>
        <?php if (!empty($_o['formulier'])): ?><span class="bh-opgave-regel"><?= nl2br(h((string)$_o['formulier'])) ?></span><?php endif; ?>
        <?php if (!empty($_o['uitzonderingen'])): ?><span class="bh-opgave-regel"><span class="bh-meta">Uitzonderingen:</span> <?= nl2br(h((string)$_o['uitzonderingen'])) ?></span><?php endif; ?>
      </dd></div>
    <?php endif; ?>
  </dl>
</section>

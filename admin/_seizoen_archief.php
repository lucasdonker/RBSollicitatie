<?php
/**
 * Partial — Seizoen › Archief: alle seizoenen met kerncijfers, en één seizoen terugkijken
 * (alleen lezen). Niets hier wijzigt iets; openen, sluiten en verwijderen staan op Instellen.
 *
 * Verwacht:
 *   $ar_seizoenen  array  alle seizoensrijen, nieuwste eerst (met 'n')
 *   $ar_id         int    het seizoen om te bekijken, of 0 voor de lijst
 *   $ar_actief_id  int    het seizoen waar het beheer nu mee werkt
 * Logica: bh_seizoen_kerncijfers(), bh_seizoen_archief() in includes/beheer/seizoen.php.
 *
 * Wat je ziet, volgt de rechten: namen en dossiers alleen met Sollicitanten, loonkosten alleen met
 * Loonplanning (en de module aan). Oude dossiers kunnen al geanonimiseerd zijn (bewaartermijnen).
 */
$_kern = bh_seizoen_kerncijfers();
$_sz   = null;
foreach ($ar_seizoenen as $_r) if ((int)$_r['id'] === $ar_id) $_sz = $_r;
$_url  = APP_URL . '/admin/seizoen.php?tab=archief';
?>
<?php if (!$_sz): ?>
<section class="ds-kaart ds-kaart-p bh-archief">
  <div class="bh-kaartkop"><h2>Alle seizoenen</h2></div>
  <p class="bh-meta">Kies een seizoen om terug te kijken: de cijfers per fase, waar de aanmeldingen vandaan kwamen,
    de loonkosten en de dossiers.</p>
  <?php if (!$ar_seizoenen): ?>
    <p class="bh-meta">Er is nog geen seizoen aangemaakt.</p>
  <?php else: ?>
  <div class="tabel-scroll">
    <table class="bh-tabel bh-archieftabel">
      <caption class="sr-only">Seizoenen met het aantal aanmeldingen en aangenomen</caption>
      <thead><tr><th scope="col">Seizoen</th><th scope="col" class="bh-num">Aanmeldingen</th><th scope="col" class="bh-num">Aangenomen</th>
        <th scope="col" class="bh-num">Terugkerend</th><th scope="col">Selectiedag</th><th scope="col"><span class="sr-only">Actie</span></th></tr></thead>
      <tbody>
        <?php foreach ($ar_seizoenen as $_r): $_k = $_kern[(int)$_r['id']] ?? ['aanmeldingen' => 0, 'aangenomen' => 0, 'terugkerend' => 0]; ?>
        <tr>
          <th scope="row"><?= h((string)$_r['naam']) ?><?php if ((int)$_r['id'] === $ar_actief_id): ?> <?= bh_chip('huidig', 'blauw') ?><?php endif; ?></th>
          <td class="bh-num"><?= (int)$_k['aanmeldingen'] ?></td>
          <td class="bh-num"><?= (int)$_k['aangenomen'] ?></td>
          <td class="bh-num"><?= (int)$_k['terugkerend'] ?></td>
          <td><?= !empty($_r['datum_selectiedag']) ? h(datum_kort_nl((string)$_r['datum_selectiedag'])) : '–' ?></td>
          <td><a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($_url . '&id=' . (int)$_r['id']) ?>">Bekijken<span class="bh-vw"> — <?= h((string)$_r['naam']) ?></span></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<?php else:
  $_mag_personen = mag('sollicitanten', 'lezen');
  $_mag_loon     = mag('loonplanning', 'lezen');
  $_a  = bh_seizoen_archief((int)$_sz['id'], $_mag_personen, $_mag_loon);
  $_k  = $_a['kern'];
  $_max = $_k['per_fase'] ? max($_k['per_fase']) : 0;
?>
<p class="bh-terug"><a href="<?= h($_url) ?>"><?= icoon('terug', 14) ?> Alle seizoenen</a></p>

<section class="ds-kaart ds-kaart-p bh-archief">
  <div class="bh-kaartkop">
    <h2><?= h((string)$_sz['naam']) ?></h2>
    <span class="bh-meta">alleen lezen<?= (int)$_sz['id'] !== $ar_actief_id ? '' : ' · dit is het huidige seizoen' ?></span>
  </div>
  <dl class="pg-cijfers">
    <div><dt>Aanmeldingen</dt><dd><?= (int)$_k['aanmeldingen'] ?></dd></div>
    <div><dt>Aangenomen</dt><dd><?= (int)$_k['aangenomen'] ?></dd></div>
    <div><dt>Terugkerend</dt><dd><?= (int)$_k['terugkerend'] ?></dd></div>
    <?php if ($_a['loon']): ?>
      <div><dt>Loonkosten (afspraken)</dt><dd><?= h(euro($_a['loon']['kosten'])) ?></dd></div>
    <?php endif; ?>
  </dl>
  <?php if ($_a['loon'] && $_a['loon']['budget'] > 0): ?>
    <p class="bh-meta"><?= h(euro($_a['loon']['kosten'])) ?> van het budget van <?= h(euro($_a['loon']['budget'])) ?>
      (<?= (int)round($_a['loon']['kosten'] / $_a['loon']['budget'] * 100) ?>%), <?= (int)$_a['loon']['dagen'] ?> betaalde dagen,
      <?= (int)$_a['loon']['personen'] ?> personen.</p>
  <?php endif; ?>
</section>

<div class="bh-archief-kolommen">
  <section class="ds-kaart ds-kaart-p">
    <div class="bh-kaartkop"><h2>Waar iedereen eindigde</h2></div>
    <?php if (!$_k['per_fase']): ?>
      <p class="bh-meta">Geen sollicitaties in dit seizoen.</p>
    <?php else: ?>
    <ul class="bh-archief-fases">
      <?php foreach ($_k['per_fase'] as $_f => $_n): ?>
        <li><span><?= h(fase_label((string)$_f)) ?></span><strong><?= (int)$_n ?></strong>
          <span class="bh-archief-balk" aria-hidden="true"><span style="--w: <?= $_max ? max(2, (int)round($_n / $_max * 100)) : 0 ?>%"></span></span></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="ds-kaart ds-kaart-p">
    <div class="bh-kaartkop"><h2>Waar aanmeldingen vandaan kwamen</h2></div>
    <?php if (!$_a['bronnen']): ?>
      <p class="bh-meta">Geen bron bekend.</p>
    <?php else: ?>
    <ul class="bh-archief-fases">
      <?php foreach ($_a['bronnen'] as $_b): ?>
        <li><span><?= h((string)$_b['bron']) ?></span><strong><?= (int)$_b['aanmeldingen'] ?></strong>
          <span class="bh-meta"><?= (int)$_b['aangenomen'] ?> aangenomen</span></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>

<section class="ds-kaart ds-kaart-p bh-archief">
  <div class="bh-kaartkop"><h2>Exporteren</h2></div>
  <div class="bh-lijstkop-acties">
    <?php if (mag('sollicitanten', 'exporteren')): ?>
      <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/export_blk.php?formaat=excel&amp;seizoen=<?= (int)$_sz['id'] ?>" data-geen-bezig><?= icoon('export', 14) ?> Sollicitanten (Excel)</a>
    <?php endif; ?>
    <?php if ($_a['loon']): ?>
      <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/export_loonplanning.php?seizoen=<?= (int)$_sz['id'] ?>" data-geen-bezig><?= icoon('export', 14) ?> Loonadministratie (Excel)</a>
    <?php endif; ?>
    <?php if (!mag('sollicitanten', 'exporteren') && !$_a['loon']): ?><span class="bh-meta">Er is voor jouw rol niets te exporteren.</span><?php endif; ?>
  </div>
  <p class="bh-meta">Elke export komt in het logboek.</p>
</section>

<?php if ($_mag_personen): ?>
<details class="ds-kaart bh-klap bh-archief" data-klap-id="sz-archief-personen">
  <summary><span class="bh-klap-titel">Sollicitanten</span><span class="bh-klap-sam"><?= count($_a['personen']) ?> in dit seizoen</span></summary>
  <div class="bh-klap-inhoud">
    <?php if (!$_a['personen']): ?>
      <p class="bh-meta">Geen sollicitaties.</p>
    <?php else: ?>
    <div class="tabel-scroll">
      <table class="bh-tabel">
        <caption class="sr-only">Sollicitanten van <?= h((string)$_sz['naam']) ?></caption>
        <thead><tr><th scope="col">Naam</th><th scope="col">Fase</th><th scope="col">Aangemeld</th><th scope="col">Bron</th></tr></thead>
        <tbody>
          <?php foreach ($_a['personen'] as $_p): ?>
          <tr>
            <th scope="row"><a href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= (int)$_p['id'] ?>"><?= h($_p['naam']) ?></a></th>
            <td><?= h(fase_label($_p['fase'])) ?></td>
            <td><?= h(datum_kort_nl($_p['aangemeld_op'])) ?></td>
            <td><?= h($_p['bron'] !== '' ? $_p['bron'] : '–') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <p class="bh-meta">Afgewezen en afgehaakte dossiers worden na de bewaartermijn geanonimiseerd; daarna staat hier geen naam meer.</p>
  </div>
</details>
<?php endif; ?>
<?php endif; ?>

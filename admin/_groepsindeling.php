<?php
/**
 * Partial: de groepsindeling — bestuur, op een laptop. Voor de selectiedag
 * (admin/selectie_indeling.php) én het trainingsweekend (admin/tw/groepen.php).
 *
 * Het systeem doet een voorstel, de mens beslist (ontwerpfilosofie §3): een knop verdeelt
 * eerlijk op leeftijd en geslacht en spreidt de terugkerende lifeguards; daarna verplaats je
 * met de hand (slepen, of de keuzelijst op het kaartje). Wie niet bij elkaar mag, geef je per
 * persoon aan — één set per seizoen, dus wat op de selectiedag gold, geldt op het weekend ook.
 *
 * Verwacht:
 *   $gi_ev            'selectiedag' | 'tw'
 *   $gi_kandidaten    rijen met sol_id, naam, groep_id, leeftijd, geslacht, is_lifeguard
 *   $gi_groepen       evenement_groepen()
 *   $gi_relaties      evenement_relaties()
 *   $gi_mag_bewerken  bool
 *   $gi_endpoint      URL waar de POST's heen gaan (bh_groepsindeling_post() in dat scherm)
 * Handelingen: includes/beheer/groepsindeling.php. Gedrag: assets/js/groepsindeling.js.
 */
$groepen      = $gi_groepen;           // _kandidaatkaart.php verwacht deze twee namen
$mag_bewerken = $gi_mag_bewerken;
$_gi_per = ['0' => []];                // '0' = nog niet ingedeeld
foreach ($gi_groepen as $_g) $_gi_per[(string)$_g['id']] = [];
foreach ($gi_kandidaten as $_k) {
    $_gid = !empty($_k['groep_id']) ? (string)$_k['groep_id'] : '0';
    $_gi_per[isset($_gi_per[$_gid]) ? $_gid : '0'][] = $_k;
}
$_gi_botst = evenement_groep_botsingen($gi_kandidaten, $gi_relaties);
$_gi_open  = count($_gi_per['0']);
$_gi_in    = count($gi_kandidaten) - $_gi_open;
$_gi_lg    = count(array_filter($gi_kandidaten, fn($k) => !empty($k['is_lifeguard'])));
?>
<section class="ds-kaart ds-kaart-p bh-indeel-balk">
  <?php if ($gi_mag_bewerken): ?>
    <?php /* Eén formulier: het aantal hierboven geldt ook voor het voorstel (vroeger nam het voorstel
             het oude aantal uit een verborgen veld). Alleen "Voorstel maken" vraagt bevestiging. */ ?>
    <form method="post" action="<?= h($gi_endpoint) ?>" class="bh-indeel-aantal"
          data-bevestig="Een nieuw voorstel maken? Dit overschrijft de indeling die er nu staat, ook de kaartjes die je zelf hebt versleept.">
      <?= csrf_input() ?>
      <label class="bh-veldlabel" for="gi-aantal">Aantal groepen</label>
      <div class="bh-indeel-aantalrij">
        <input type="number" class="ds-input" id="gi-aantal" name="aantal" min="1" max="20" value="<?= count($gi_groepen) ?: 3 ?>">
        <button type="submit" name="actie" value="aantal_groepen" class="ds-btn ds-btn-secundair ds-btn-sm" data-zonder-bevestig>Alleen aantal aanpassen</button>
        <button type="submit" name="actie" value="voorstel" class="ds-btn ds-btn-primair">Voorstel maken</button>
      </div>
    </form>
  <?php endif; ?>

  <div class="bh-indeel-stand">
    <span><span class="bh-num"><?= $_gi_in ?></span> van <span class="bh-num"><?= count($gi_kandidaten) ?></span> ingedeeld</span>
    <?php if ($_gi_open): ?><span class="bh-indeel-open"><?= $_gi_open ?> nog niet</span><?php endif; ?>
  </div>

  <p class="bh-indeel-uitleg">
    Het voorstel maakt gelijke groepen, gemengd op leeftijd en geslacht<?= $_gi_lg > 1 ? ', met de ' . $_gi_lg . ' terugkerende lifeguards verspreid' : '' ?>,
    en houdt uit elkaar wie niet samen mag. Daarna verplaats je zelf: slepen, of per kaartje met de keuzelijst "Naar groep".
    <?php if ($gi_mag_bewerken): ?>Klik op een naam om aan te geven wie níét bij elkaar mogen<?= $gi_ev === 'tw' ? ' — dat is dezelfde lijst als op de selectiedag' : '' ?>.<?php endif; ?>
  </p>
</section>

<div class="bh-groepgrid" data-groepsindeling data-endpoint="<?= h($gi_endpoint) ?>" data-csrf="<?= h(csrf_token()) ?>"
     data-mag="<?= $gi_mag_bewerken ? '1' : '0' ?>">
  <?php if ($_gi_open): ?>
    <div class="bh-groepkolom open">
      <div class="bh-groepkolom-kop"><span>Nog niet ingedeeld</span><span class="bh-num" data-telling="0"><?= $_gi_open ?></span></div>
      <div class="bh-groepkolom-body droppable" data-groep="">
        <?php foreach ($_gi_per['0'] as $_k) { $_kand = $_k; $_conflict = false; require __DIR__ . '/_kandidaatkaart.php'; } ?>
      </div>
    </div>
  <?php endif; ?>

  <?php foreach ($gi_groepen as $_g): ?>
    <div class="bh-groepkolom">
      <div class="bh-groepkolom-kop">
        <span><?= h(evenement_groep_label($_g)) ?></span>
        <span class="bh-num" data-telling="<?= (int)$_g['id'] ?>"><?= count($_gi_per[(string)$_g['id']] ?? []) ?></span>
      </div>
      <div class="bh-groepkolom-body droppable" data-groep="<?= (int)$_g['id'] ?>">
        <?php foreach ($_gi_per[(string)$_g['id']] ?? [] as $_k) {
            $_kand = $_k; $_conflict = !empty($_gi_botst[(int)$_k['sol_id']]);
            require __DIR__ . '/_kandidaatkaart.php';
        } ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($gi_mag_bewerken): ?>
<dialog class="bh-dialoog" id="relModal">
  <h2>Wie mogen niet bij elkaar?</h2>
  <p>Vink aan wie <strong data-rel-naam></strong> kent en dus liever niet in dezelfde groep zit.</p>
  <div data-rel-lijst class="bh-rel-lijst"></div>
  <div class="bh-dialoog-knoppen">
    <button type="button" class="ds-btn ds-btn-primair" data-rel-sluit>Klaar</button>
  </div>
</dialog>
<?php endif; ?>

<script type="application/json" id="gi-data"><?= json_encode([
    'kandidaten' => array_map(fn($k) => ['id' => (int)$k['sol_id'], 'naam' => (string)$k['naam']], $gi_kandidaten),
    'relaties'   => (object)array_map(fn($l) => array_values(array_unique($l)), $gi_relaties),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= APP_URL ?>/assets/js/groepsindeling.js?v=<?= @filemtime(__DIR__ . '/../assets/js/groepsindeling.js') ?>" defer></script>
<?php
$gi_kandidaten = $gi_groepen = $gi_relaties = null;

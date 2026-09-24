<?php
/**
 * Partial: de staafgrafiek "Dekking per week".
 *
 * Verwacht:
 *   $_dek      array   uit bh_dekking_weken()  — wn => [waarde, norm, stand, weektype, …]
 *   $_weken    array   wn => ['kort' => '22 jun', …]  (voor de tooltip)
 *   $_titel    ?string kop boven de grafiek       (standaard "Dekking per week")
 *   $_klik     ?string JS-sjabloon met %d voor het weeknummer; maakt de staven klikbaar
 *
 * De grafiek staat bovenaan het scherm omdat de eerste vraag "is er een probleem?"
 * is en niet "wie is er beschikbaar?" (ontwerpfilosofie §1). De knelpunten staan
 * daarom ook als zin rechtsboven en niet alleen als rode staaf: kleur alleen is
 * geen mededeling.
 */
$_dek   = $_dek   ?? [];
$_weken = $_weken ?? [];
$_titel = $_titel ?? 'Dekking per week';
$_klik  = $_klik  ?? null;
if (!$_dek) return;

$_norm  = (int)(reset($_dek)['norm'] ?? 0);
$_max   = bh_dekking_max($_dek);
$_knel  = bh_dekking_knelpunten($_dek);
$_zin   = bh_dekking_zin($_knel);
?>
<section class="bh-kaart bh-dekgrafiek">
  <header class="bh-dekgrafiek-kop">
    <h2><?= h($_titel) ?></h2>
    <?php if ($_norm > 0): ?>
      <span class="bh-dekgrafiek-norm">norm <?= $_norm ?> <?= $_norm === 1 ? 'persoon' : 'mensen' ?> per dienst</span>
      <span class="bh-dekgrafiek-stand">
        <?= $_zin !== null ? bh_stoplicht('slecht', $_zin) : bh_stoplicht('goed', 'Alle weken gedekt') ?>
      </span>
    <?php else: ?>
      <?php /* Zonder norm weten we niet of het genoeg is. Dan géén groen vinkje:
               "Alle weken gedekt" zou hier een bewering zijn die nergens op rust. */ ?>
      <span class="bh-dekgrafiek-norm">
        Er is nog geen norm ingesteld, dus de staven tonen alleen de onderlinge verhouding.
        Stel de posten in bij <a href="<?= APP_URL ?>/admin/instellingen.php#bezetting">Posten &amp; bezetting</a>.
      </span>
    <?php endif; ?>
  </header>

  <?php
  // De normlijn is een waarde, geen opmaak — vandaar als variabele op het element,
  // net als --bh-tracks bij de tabel. Zonder norm tekenen we geen lijn.
  $_normpct = $_norm > 0 ? bh_dekking_hoogte($_norm, $_norm, $_max) : 0;
  ?>
  <div class="bh-dekstaven<?= $_normpct > 0 ? ' met-norm' : '' ?>"
       <?= $_normpct > 0 ? 'style="--bh-norm:' . $_normpct . '%"' : '' ?>>
    <?php foreach ($_dek as $_wn => $_d):
      $_h    = bh_dekking_hoogte((int)$_d['waarde'], (int)$_d['norm'], $_max);
      $_laag = $_d['stand'] === 'slecht';
      $_tip  = 'Week ' . $_wn
             . (isset($_weken[$_wn]['kort']) ? ' (' . $_weken[$_wn]['kort'] . ')' : '')
             . ' — ' . $_d['aantal'] . ' beschikbaar, ' . $_d['weekend'] . ' in het weekend'
             . ($_d['telt'] === 'weekend' ? ' · alleen weekend bemand' : '');
      $_cel  = '<span class="bh-dekstaaf-getal">' . (int)$_d['waarde'] . '</span>'
             . '<span class="bh-dekstaaf-bak"><span class="bh-dekstaaf-vul" style="--h: ' . $_h . '%"></span></span>'
             . '<span class="bh-dekstaaf-wk">W' . (int)$_wn . '</span>';
      $_cls  = 'bh-dekstaaf bh-dekstaaf-' . $_d['stand'];
    ?>
      <?php if ($_klik): ?>
        <button type="button" class="<?= $_cls ?>" title="<?= h($_tip) ?>" aria-label="<?= h($_tip) ?>"
                onclick="<?= h(sprintf($_klik, (int)$_wn)) ?>"><?= $_cel ?></button>
      <?php else: ?>
        <span class="<?= $_cls ?>" title="<?= h($_tip) ?>" aria-label="<?= h($_tip) ?>"><?= $_cel ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <p class="bh-dekgrafiek-voet">
    De staaf toont het getal dat die week telt: in het hoofdseizoen het weektotaal, in
    voor- en naseizoen het aantal dat het <strong>weekend</strong> dekt — dan staan de posten
    alleen in het weekend bemand.
  </p>
</section>
<?php
$_klik = null; $_titel = 'Dekking per week';

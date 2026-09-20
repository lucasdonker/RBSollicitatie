<?php
/**
 * Partial: de seizoensbalk — waar staat het seizoen.
 *
 * Zes fases naast elkaar; wat geweest is groen, waar we nu zijn oranje, wat komt grijs.
 * De fase stuurt de rest van het scherm (ontwerpfilosofie §2), dus die hoort bovenaan
 * te staan en niet ergens in een hoek.
 *
 * Verwacht:
 *   $vandaag_fase  string  uitkomst van bh_seizoen_fase()
 *   $vandaag_datums ?array optioneel: [fase => 'datumtekst'] achter het label
 */
$vandaag_fase   = $vandaag_fase ?? '';
$vandaag_datums = $vandaag_datums ?? [];

$_fases  = bh_seizoen_fases();
$_sleutels = array_keys($_fases);
$_nu     = array_search($vandaag_fase, $_sleutels, true);
if ($_nu === false) $_nu = 0;

// Korte labels: de volledige omschrijving uit bh_seizoen_fases() is voor een keuzelijst.
$_kort = ['werving' => 'Werving', 'formulieren' => 'Formulieren', 'selectiedag' => 'Selectiedag',
          'bellijst' => 'Bellijst', 'trainingsweekend' => 'Trainingsweekend', 'aanname' => 'Aanname'];
?>
<div class="ds-kaart bh-seizoenbalk" role="img"
     aria-label="Het seizoen staat in de fase <?= h($_kort[$vandaag_fase] ?? $vandaag_fase) ?>">
  <?php foreach ($_sleutels as $_i => $_s):
    $_stand = $_i < $_nu ? 'gehad' : ($_i === $_nu ? 'nu' : 'komt');
  ?>
    <div class="bh-sb-stap <?= $_stand ?>">
      <span class="bh-sb-lijn"></span>
      <span class="bh-sb-label">
        <?= h($_kort[$_s] ?? $_s) ?>
        <?php if (!empty($vandaag_datums[$_s])): ?>
          <span class="bh-sb-datum"><?= h($vandaag_datums[$_s]) ?></span>
        <?php endif; ?>
      </span>
    </div>
  <?php endforeach; ?>
</div>
<?php
$vandaag_fase = ''; $vandaag_datums = [];

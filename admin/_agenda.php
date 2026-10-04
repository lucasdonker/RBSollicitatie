<?php
/**
 * Partial: de agenda op Vandaag — datums van het seizoen en geplande berichten, de komende
 * weken, met "over N dagen". Lopend (het weekend) staat er ook bij.
 *
 * Verwacht: $agenda  array  uit bh_vandaag_agenda()
 */
$agenda = $agenda ?? [];
$_over = function (array $a): string {
    if ($a['over'] < 0)   return 'nu bezig';
    if ($a['over'] === 0) return 'vandaag';
    if ($a['over'] === 1) return 'morgen';
    return 'over ' . $a['over'] . ' dagen';
};
?>
<section class="ds-kaart ds-kaart-p bh-agenda" aria-labelledby="bh-agenda-kop">
  <div class="bh-kaartkop"><h2 id="bh-agenda-kop">Agenda</h2><span class="bh-meta">komende 60 dagen</span></div>
  <?php if (!$agenda): ?>
    <p class="bh-meta">Geen datums in de komende weken.
      <?php if (mag('seizoen', 'bewerken')): ?><a href="<?= APP_URL ?>/admin/seizoen.php">Datums van het seizoen</a><?php endif; ?></p>
  <?php else: ?>
  <ul class="bh-agenda-lijst">
    <?php foreach ($agenda as $_a): ?>
      <li class="bh-agenda-rij <?= h($_a['soort']) ?>">
        <span class="bh-agenda-datum">
          <span class="bh-agenda-dag bh-num"><?= (int)date('j', strtotime($_a['datum'])) ?></span>
          <span class="bh-agenda-maand"><?= h(maanden_kort_nl()[(int)date('n', strtotime($_a['datum']))]) ?></span>
        </span>
        <span class="bh-agenda-tekst">
          <?= h($_a['tekst']) ?>
          <span class="bh-meta"><?= h($_over($_a)) ?><?= $_a['tot'] ? ' · t/m ' . h(datum_kort_nl($_a['tot'])) : '' ?></span>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php
$agenda = null;

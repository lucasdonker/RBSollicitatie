<?php
/**
 * Partial: de kop met stappen van een evenement — selectiedag (admin/_selectie_subnav.php) en
 * trainingsweekend (admin/trainingsweekend.php). Eén vorm, zodat beide modules hetzelfde ogen.
 *
 * Verwacht:
 *   $sb_titel    string  "Selectiedag" | "Trainingsweekend"
 *   $sb_datum    ?string de datum(s) in de kop, of null
 *   $sb_stappen  array   [sleutel => ['label' => …, 'url' => …]] — alleen de stappen die deze gebruiker mag zien
 *   $sb_actief   string  sleutel van de huidige stap
 */
?>
<div class="bh-selectiekop">
  <div class="bh-selectiekop-titel">
    <h2><?= h($sb_titel) ?></h2>
    <?php if (!empty($sb_datum)): ?><span class="bh-meta"><?= h($sb_datum) ?></span><?php endif; ?>
  </div>
  <nav class="bh-substappen" aria-label="Stappen van <?= h(mb_strtolower($sb_titel)) ?>">
    <?php foreach ($sb_stappen as $_sk => $_ss): $_nu = $sb_actief === $_sk; ?>
      <a class="bh-substap<?= $_nu ? ' actief' : '' ?>" href="<?= h($_ss['url']) ?>"<?= $_nu ? ' aria-current="page"' : '' ?>><?= h($_ss['label']) ?></a>
    <?php endforeach; ?>
  </nav>
</div>
<?php
$sb_titel = $sb_datum = $sb_stappen = $sb_actief = null;

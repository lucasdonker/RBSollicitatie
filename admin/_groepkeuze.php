<?php
/**
 * Partial: groepchips boven een beoordeellijst ("Alle 24 · Groep 1 8 · Groep 2 8 …").
 * Voor de selectiedag en het trainingsweekend. Groepen zonder leden vallen weg; zijn er geen
 * groepen, dan toont hij niets.
 *
 * Verwacht:
 *   $gk_groepen     evenement_groepen()
 *   $gk_kandidaten  rijen met groep_id
 *   $gk_gekozen     string  '' = alle, anders het groep-id
 *   $gk_url         callable fn(string $groep): string — '' = alle
 */
if (!$gk_groepen) { $gk_groepen = $gk_kandidaten = $gk_url = null; return; }
$_gk_tel = [];
foreach ($gk_kandidaten as $_k) if (!empty($_k['groep_id'])) $_gk_tel[(string)$_k['groep_id']] = ($_gk_tel[(string)$_k['groep_id']] ?? 0) + 1;
?>
<nav class="bh-groepkeuze" aria-label="Kies een groep">
  <a class="bh-groepchip<?= $gk_gekozen === '' ? ' actief' : '' ?>" href="<?= h($gk_url('')) ?>"<?= $gk_gekozen === '' ? ' aria-current="true"' : '' ?>>
    Alle <span><?= count($gk_kandidaten) ?></span>
  </a>
  <?php foreach ($gk_groepen as $_g): $_n = $_gk_tel[(string)$_g['id']] ?? 0; if (!$_n) continue; $_nu = $gk_gekozen === (string)$_g['id']; ?>
    <a class="bh-groepchip<?= $_nu ? ' actief' : '' ?>" href="<?= h($gk_url((string)$_g['id'])) ?>"<?= $_nu ? ' aria-current="true"' : '' ?>>
      <?= h(evenement_groep_label($_g)) ?> <span><?= $_n ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php
$gk_groepen = $gk_kandidaten = $gk_url = null;

<?php
/**
 * Partial — een instellingentabblad uit de lijst (includes/instellingen_velden.php): secties met
 * velden in een raster, of een maatwerkblok (admin/inst/_<naam>.php). Eén Opslaan onderaan; de
 * whitelist van het opslaan komt uit dezelfde lijst.
 *
 * Verwacht: $if_tab (sleutel van het tabblad). De maatwerkblokken zien de variabelen van
 * admin/instellingen.php ($mag_sjablonen, $mag_systeem).
 */
$_if = instellingen_velden()[$if_tab] ?? null;
if (!$_if) return;
?>
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2><?= h($_if['titel']) ?></h2></div>
  <div class="bh-kaartbody">
    <form method="POST">
      <?= csrf_input() ?><input type="hidden" name="actie" value="instellingen">
      <?php foreach ($_if['secties'] as $_is):
        if (isset($_is['als']) && !($_is['als'])()) continue; ?>
      <div class="bh-instsectie">
        <h3><?= h($_is['titel']) ?></h3>
        <?php if (!empty($_is['uitleg'])): ?><p class="ds-veld-hint bh-mb"><?= h($_is['uitleg']) ?></p><?php endif; ?>
        <?php if (!empty($_is['partial'])): ?>
          <?php require __DIR__ . '/inst/_' . basename((string)$_is['partial']) . '.php'; ?>
        <?php else: ?>
          <div class="bh-instvelden">
            <?php foreach ($_is['velden'] as $iv) require __DIR__ . '/_inst_veld.php'; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
    </form>
  </div>
</div>
<?php
$if_tab = null;

<?php
/**
 * Partial: een knop met een klein menu eronder, voor wat je zelden doet (importeren, exporteren).
 * Een <details>: werkt zonder JavaScript; beheer.js sluit hem bij een klik ernaast of Escape.
 *
 * Verwacht:
 *   $am_label  string  tekst op de knop (bijv. "Meer")
 *   $am_icoon  ?string icoon op de knop
 *   $am_items  array   elk ['label', 'url', 'icoon'?, 'titel'?]
 */
if (empty($am_items)) { $am_label = $am_icoon = null; $am_items = []; return; }
?>
<details class="bh-filter-menu" data-actiemenu>
  <summary class="ds-btn ds-btn-secundair ds-btn-sm"><?php if (!empty($am_icoon)) echo icoon($am_icoon, 15); ?><?= h((string)$am_label) ?></summary>
  <ul class="bh-filter-menu-lijst">
    <?php foreach ($am_items as $_m): ?>
      <li><a href="<?= h($_m['url']) ?>"<?= !empty($_m['titel']) ? ' title="' . h($_m['titel']) . '"' : '' ?>>
        <?php if (!empty($_m['icoon'])) echo icoon($_m['icoon'], 15); ?><?= h($_m['label']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</details>
<?php
$am_label = $am_icoon = null; $am_items = [];

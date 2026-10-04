<?php
/**
 * Partial: uitklapbare lijst "wat betekent wat" voor een scherm met eigen begrippen.
 *
 * Dicht als standaard: wie het kent, heeft er geen last van; wie twijfelt, vindt het op de
 * plek waar het woord staat in plaats van in de handleiding.
 *
 * Verwacht:
 *   $begrippen        array   [begrip => uitleg in één of twee zinnen] — platte tekst
 *   $begrippen_titel  string  optioneel: tekst van de klapregel (standaard "Wat betekenen de begrippen?")
 */
$begrippen       = $begrippen ?? [];
$begrippen_titel = $begrippen_titel ?? 'Wat betekenen de begrippen?';
if ($begrippen):
?>
<details class="bh-begrippen">
  <summary><?= icoon('info', 14) ?> <?= h($begrippen_titel) ?></summary>
  <dl>
    <?php foreach ($begrippen as $_bg => $_bu): ?>
      <dt><?= h((string)$_bg) ?></dt>
      <dd><?= h((string)$_bu) ?></dd>
    <?php endforeach; ?>
  </dl>
</details>
<?php
endif;
$begrippen = []; $begrippen_titel = null;

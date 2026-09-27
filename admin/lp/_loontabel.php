<?php
/**
 * Loonplanning — ingeklapt onderaan: het dagloon per leeftijd en functie (juni en juli–september),
 * met de tarieven van het jaar van het seizoen (includes/loon_tarieven.php).
 * Onderdeel van admin/loonplanning.php (zet $seizoen, $functies, $analyse).
 */
$_lt_info = $analyse['tarieven'] ?? loonplan_tarief_info($seizoen);
$_lt_jaar = (int)$_lt_info['jaar'];
?>
<details class="ds-kaart bh-lp-loontabel">
  <summary>Loontabel <?= $_lt_info['bron_jaar'] ? (int)$_lt_info['bron_jaar'] : $_lt_jaar ?>: dagloon per leeftijd en functie</summary>
  <?php if ($_lt_info['melding'] !== ''): ?>
    <p class="bh-meta"><?= h($_lt_info['melding']) ?></p>
  <?php endif; ?>
  <div class="bh-rasterrol">
    <table class="bh-lp-loontab">
      <thead>
        <tr><th scope="col">Leeftijd</th><?php foreach ($functies as $_f): ?><th scope="col" colspan="2"><?= h($_f['naam']) ?></th><?php endforeach; ?></tr>
        <tr><th></th><?php foreach ($functies as $_f): ?><th scope="col">juni</th><th scope="col">jul–sep</th><?php endforeach; ?></tr>
      </thead>
      <tbody>
        <?php for ($_l = 15; $_l <= 22; $_l++): ?>
        <tr>
          <th scope="row"><?= $_l ?> jr</th>
          <?php foreach ($functies as $_f): ?>
            <td>€ <?= number_format(bereken_dagloon($_l, $_f['sleutel'], 'jun', null, $_lt_jaar), 2, ',', '.') ?></td>
            <td>€ <?= number_format(bereken_dagloon($_l, $_f['sleutel'], 'jul_sep', null, $_lt_jaar), 2, ',', '.') ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</details>

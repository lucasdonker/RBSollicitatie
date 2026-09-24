<?php
/**
 * Loonplanning — ingeklapt onderaan: het dagloon per leeftijd en functie (juni en juli–september).
 * Onderdeel van admin/loonplanning.php.
 */
?>
<details class="ds-kaart bh-lp-loontabel">
  <summary>Loontabel: dagloon per leeftijd en functie</summary>
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
            <td>€ <?= number_format(bereken_dagloon($_l, $_f['sleutel'], 'jun'), 2, ',', '.') ?></td>
            <td>€ <?= number_format(bereken_dagloon($_l, $_f['sleutel'], 'jul_sep'), 2, ',', '.') ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</details>

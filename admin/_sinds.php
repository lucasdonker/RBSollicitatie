<?php
/**
 * Partial: "Sinds je vorige bezoek" op Vandaag — wat er nieuw is sinds je hier het laatst
 * keek (beheerder_vandaag_sinds()). Alleen wat je mag zien.
 *
 * Verwacht:
 *   $sinds_regels  array   uit bh_vandaag_sinds()
 *   $sinds_op      string  het ijkpunt ('Y-m-d H:i:s')
 */
$sinds_regels = $sinds_regels ?? [];
$_so = strtotime((string)$sinds_op);
$_so_tekst = date('Y-m-d', $_so) === date('Y-m-d') ? 'vandaag ' . date('H:i', $_so)
           : (date('Y-m-d', $_so) === date('Y-m-d', strtotime('-1 day')) ? 'gisteren ' . date('H:i', $_so)
           : datum_dag_nl(date('Y-m-d', $_so)) . ' ' . date('H:i', $_so));
?>
<section class="ds-kaart ds-kaart-p bh-sinds" aria-labelledby="bh-sinds-kop">
  <div class="bh-kaartkop">
    <h2 id="bh-sinds-kop">Sinds je vorige bezoek</h2>
    <span class="bh-meta"><?= h($_so_tekst) ?></span>
  </div>
  <?php if (!$sinds_regels): ?>
    <p class="bh-meta">Niets nieuws.</p>
  <?php else: ?>
  <ul class="bh-sinds-lijst">
    <?php foreach ($sinds_regels as $_r): ?>
      <li>
        <span class="bh-sinds-getal bh-num"><?= (int)$_r['aantal'] ?></span>
        <?php if (!empty($_r['url'])): ?><a href="<?= h($_r['url']) ?>"><?= h($_r['tekst']) ?></a><?php else: ?><span><?= h($_r['tekst']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php
$sinds_regels = null;

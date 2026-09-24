<?php
/**
 * TW-stap Gesprekken (bestuur): iedereen krijgt een gesprek, in de volgorde die ter plekke
 * uitkomt. "Bezig door X" voorkomt dat twee bestuursleden dezelfde persoon openen (30 min).
 * Onderdeel van admin/trainingsweekend.php; het gesprek zelf staat in admin/tw/gesprek.php.
 */
$_st  = ['nog' => [], 'bezig' => [], 'gehad' => []];
foreach ($kandidaten as $_k) $_st[tw_gesprek_stand($_k, $ik_id)][] = $_k;
$_twijfel = count(array_filter($_st['gehad'], fn($k) => ($k['besluit'] ?? '') === 'twijfel' && empty($k['doorgevoerd_op'])));
$_besluit = ['aannemen' => ['Aangenomen', 'groen'], 'twijfel' => ['Twijfel', 'oranje'], 'afwijzen' => ['Afgewezen', 'rood']];
?>
<section class="ds-kaart bh-dekkop">
  <div class="bh-dekkop-zin">
    <?= bh_stoplicht(count($_st['nog']) + count($_st['bezig']) > 0 ? 'krap' : 'goed',
                     count($_st['gehad']) . ' van ' . count($kandidaten) . ' gesprekken gehad') ?>
  </div>
  <p class="bh-dekkop-uitleg">
    Kies wie er nu aan tafel zit. Aannemen of afwijzen gaat meteen door: de fase verandert en
    het bericht gaat eruit, met wat je in het gesprek voor de sollicitant opschrijft.
    <?php if ($_twijfel > 0): ?><strong><?= $_twijfel ?> op twijfel</strong> — die blijven staan tot na het weekend.<?php endif; ?>
  </p>
</section>

<?php foreach (['bezig' => 'Nu bezig', 'nog' => 'Nog te spreken', 'gehad' => 'Gesproken'] as $_groep => $_titel):
  if (!$_st[$_groep]) continue; ?>
<section class="ds-kaart bh-twgesprekken">
  <h3 class="bh-kaartkop"><?= h($_titel) ?> <span class="bh-meta"><?= count($_st[$_groep]) ?></span></h3>
  <ul class="bh-twgesprek-lijst">
    <?php foreach ($_st[$_groep] as $_k):
      $_id = (int)$_k['sol_id'];
      $_b  = $_besluit[$_k['besluit'] ?? ''] ?? null;
    ?>
    <li class="bh-twgesprek-rij">
      <?= bh_avatar($_k, 44) ?>
      <span class="bh-twgesprek-wie">
        <span class="bh-twgesprek-naam"><?= h((string)$_k['naam']) ?></span>
        <?= bh_meta([$_k['leeftijd'] !== null ? $_k['leeftijd'] . ' jaar' : '',
                     !empty($_k['aanwezig']) ? 'aanwezig' : (($_k['afgemeld_reden'] ?? null) !== null ? 'niet gekomen' : ''),
                     $_groep === 'bezig' ? 'bezig door ' . evenement_claim_naam((int)$_k['gesprek_door']) : '']) ?>
      </span>
      <?php if ($_groep === 'gehad' && $_b): ?><?= bh_badge($_b[0] . (!empty($_k['doorgevoerd_op']) ? ' · doorgevoerd' : ''), $_b[1]) ?><?php endif; ?>
      <?php if ($_groep === 'bezig'): ?>
        <?= bh_badge('Bezig', 'oranje') ?>
      <?php else: ?>
        <a class="ds-btn <?= $_groep === 'nog' ? 'ds-btn-primair' : 'ds-btn-secundair' ?> ds-btn-sm" href="<?= h(tw_url('gesprekken', $_id)) ?>">
          <?= $_groep === 'nog' ? 'Gesprek starten' : (empty($_k['doorgevoerd_op']) ? 'Verder' : 'Bekijken') ?>
        </a>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>

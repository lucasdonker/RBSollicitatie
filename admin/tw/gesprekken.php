<?php
/**
 * TW-stap Gesprekken (bestuur): iedereen krijgt een gesprek, in de volgorde die ter plekke
 * uitkomt. "Bezig door X" voorkomt dat twee bestuursleden dezelfde persoon openen (30 min).
 * Zoeken en filteren via de gedeelde filterbalk (werkt zonder JavaScript).
 * Onderdeel van admin/trainingsweekend.php; het gesprek zelf staat in admin/tw/gesprek.php.
 */
$_f = ['q' => (string)($_GET['q'] ?? ''), 'stand' => (string)($_GET['stand'] ?? ''),
       'besluit' => (string)($_GET['besluit'] ?? ''), 'sort' => (string)($_GET['sort'] ?? 'naam')];
$_sd  = (module_aan('selectiedag') && function_exists('selectie_scores_alle')) ? selectie_scores_alle($sid) : [];
$_alle   = tw_gesprekken_filter($kandidaten, [], $ik_id, $_sd);
$_lijst  = tw_gesprekken_filter($kandidaten, $_f, $ik_id, $_sd);
$_tel = ['nog' => 0, 'bezig' => 0, 'gehad' => 0];
foreach ($_alle as $_k) $_tel[$_k['_stand']]++;
$_st  = ['bezig' => [], 'nog' => [], 'gehad' => []];
foreach ($_lijst as $_k) $_st[$_k['_stand']][] = $_k;
$_twijfel = count(array_filter($_alle, fn($k) => ($k['besluit'] ?? '') === 'twijfel' && empty($k['doorgevoerd_op'])));
$_besluit = ['aannemen' => ['Aangenomen', 'groen'], 'twijfel' => ['Twijfel', 'oranje'], 'afwijzen' => ['Afgewezen', 'rood']];
$_gefilterd = $_f['q'] !== '' || $_f['stand'] !== '' || $_f['besluit'] !== '';
?>
<section class="ds-kaart bh-dekkop">
  <div class="bh-dekkop-zin">
    <?= bh_stoplicht($_tel['nog'] + $_tel['bezig'] > 0 ? 'krap' : 'goed',
                     $_tel['gehad'] . ' van ' . count($_alle) . ' gesprekken gehad') ?>
  </div>
  <p class="bh-dekkop-uitleg">
    Kies wie er nu aan tafel zit. Aannemen of afwijzen gaat meteen door: de fase verandert en
    het bericht gaat eruit, met wat je in het gesprek voor de sollicitant opschrijft.
    <?php if ($_twijfel > 0): ?><strong><?= $_twijfel ?> op twijfel</strong> — die blijven staan tot na het weekend.<?php endif; ?>
  </p>
</section>

<?php
  $filter_verborgen = ['stap' => 'gesprekken'];
  $filter_velden = [
    ['soort' => 'zoek', 'naam' => 'q', 'label' => 'Zoek op naam', 'waarde' => $_f['q'], 'breed' => true],
    ['soort' => 'keuze', 'naam' => 'stand', 'label' => 'Gesprek', 'waarde' => $_f['stand'],
     'opties' => ['' => 'Alle gesprekken', 'nog' => 'Nog te spreken (' . $_tel['nog'] . ')', 'bezig' => 'Nu bezig (' . $_tel['bezig'] . ')', 'gehad' => 'Gesproken (' . $_tel['gehad'] . ')']],
    ['soort' => 'keuze', 'naam' => 'besluit', 'label' => 'Besluit', 'waarde' => $_f['besluit'],
     'opties' => ['' => 'Elk besluit', 'geen' => 'Nog geen besluit', 'aannemen' => 'Aangenomen', 'twijfel' => 'Twijfel', 'afwijzen' => 'Afgewezen']],
    ['soort' => 'keuze', 'naam' => 'sort', 'label' => 'Sorteren', 'waarde' => $_f['sort'],
     'opties' => ['naam' => 'Op naam', 'sd' => 'Op selectiedag-cijfer']],
  ];
  $filter_wissen = $_gefilterd ? tw_url('gesprekken') : null;
  require __DIR__ . '/../_filterbalk.php';
?>

<?php if (!$_lijst): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php $leeg_titel = 'Niemand gevonden'; $leeg_tekst = 'Pas de zoekterm of de filters aan.'; $leeg_icoon = 'zoek'; require __DIR__ . '/../_leeg.php'; ?>
  </div>
<?php endif; ?>

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
                     $_k['_sd'] !== null ? 'selectiedag ' . number_format($_k['_sd'], 1, ',', '') : '',
                     !empty($_k['aanwezig']) ? 'aanwezig' : (($_k['afgemeld_reden'] ?? null) !== null ? 'niet gekomen' : ''),
                     $_groep !== 'gehad' && !empty($_k['evaluatie']) ? 'indruk: ' . mb_strtolower(tw_evaluatie_standen()[$_k['evaluatie']] ?? '') : '',
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

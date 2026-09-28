<?php
/**
 * TW-stap Gesprekken (bestuur): iedereen krijgt een gesprek, in de volgorde die ter plekke
 * uitkomt. "Bezig door X" voorkomt dat twee bestuursleden dezelfde persoon openen (30 min);
 * wie het bewerkrecht heeft kan zo'n gesprek bewust overnemen (admin/tw/_overnemen.php).
 * Wie niet kwam staat apart ("Niet gekomen"): geen gesprek, wel een besluit.
 * Zoeken en filteren via de gedeelde filterbalk (werkt zonder JavaScript).
 * Onderdeel van admin/trainingsweekend.php; het gesprek zelf staat in admin/tw/gesprek.php.
 */
// Terug in het overzicht = in geen gesprek meer: je open gesprek komt vrij voor je collega's.
if (!empty($ik_id)) evenement_claim_los_overige('tw_deelname', 'gesprek', $sid, $ik_id);
$_f = ['q' => (string)($_GET['q'] ?? ''), 'stand' => (string)($_GET['stand'] ?? ''),
       'besluit' => (string)($_GET['besluit'] ?? ''), 'sort' => (string)($_GET['sort'] ?? 'naam')];
$_sd  = (module_aan('selectiedag') && function_exists('selectie_scores_alle')) ? selectie_scores_alle($sid) : [];
$_alle   = tw_gesprekken_filter($kandidaten, [], $ik_id, $_sd);
$_lijst  = tw_gesprekken_filter($kandidaten, $_f, $ik_id, $_sd);
$_tel = ['nog' => 0, 'bezig' => 0, 'gehad' => 0, 'niet_gekomen' => 0];
foreach ($_alle as $_k) $_tel[$_k['_stand']]++;
$_st  = ['bezig' => [], 'nog' => [], 'niet_gekomen' => [], 'gehad' => []];
foreach ($_lijst as $_k) $_st[$_k['_stand']][] = $_k;
$_twijfel = count(array_filter($_alle, fn($k) => ($k['besluit'] ?? '') === 'twijfel' && empty($k['doorgevoerd_op'])));
$_besluit = ['aannemen' => ['Aangenomen', 'groen'], 'twijfel' => ['Twijfel', 'oranje'], 'afwijzen' => ['Afgewezen', 'rood']];
$_gefilterd = $_f['q'] !== '' || $_f['stand'] !== '' || $_f['besluit'] !== '';
$_te_spreken = count($_alle) - $_tel['niet_gekomen'];
?>
<section class="ds-kaart bh-dekkop">
  <div class="bh-dekkop-zin">
    <?= bh_stoplicht($_tel['nog'] + $_tel['bezig'] + $_tel['niet_gekomen'] > 0 ? 'krap' : 'goed',
                     $_tel['gehad'] . ' van ' . $_te_spreken . ' gesprekken gehad'
                     . ($_tel['niet_gekomen'] > 0 ? ' · ' . $_tel['niet_gekomen'] . ' niet gekomen, nog zonder besluit' : '')) ?>
  </div>
  <p class="bh-dekkop-uitleg">
    Kies wie er nu aan tafel zit. Aannemen of afwijzen gaat meteen door: de fase verandert en
    het bericht gaat eruit, met wat je in het gesprek voor de sollicitant opschrijft.
    <?php if ($_twijfel > 0): ?><strong><?= $_twijfel ?> op twijfel</strong> — die blijven staan tot na het weekend.<?php endif; ?>
    <?php if ($_tel['niet_gekomen'] > 0): ?>Wie niet kwam, heeft geen gesprek maar wel een besluit nodig; die staan apart.<?php endif; ?>
  </p>
</section>

<?php
  $filter_verborgen = ['stap' => 'gesprekken'];
  $filter_velden = [
    ['soort' => 'zoek', 'naam' => 'q', 'label' => 'Zoek op naam', 'waarde' => $_f['q'], 'breed' => true],
    ['soort' => 'keuze', 'naam' => 'stand', 'label' => 'Gesprek', 'waarde' => $_f['stand'],
     'opties' => ['' => 'Alle gesprekken', 'nog' => 'Nog te spreken (' . $_tel['nog'] . ')', 'bezig' => 'Nu bezig (' . $_tel['bezig'] . ')', 'gehad' => 'Gesproken (' . $_tel['gehad'] . ')',
                  'niet_gekomen' => 'Niet gekomen, zonder besluit (' . $_tel['niet_gekomen'] . ')']],
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

<?php foreach (['bezig' => 'Nu bezig', 'nog' => 'Nog te spreken', 'niet_gekomen' => 'Niet gekomen', 'gehad' => 'Gesproken'] as $_groep => $_titel):
  if (!$_st[$_groep]) continue; ?>
<section class="ds-kaart bh-twgesprekken">
  <h3 class="bh-kaartkop"><?= h($_titel) ?> <span class="bh-meta"><?= count($_st[$_groep]) ?></span></h3>
  <?php if ($_groep === 'niet_gekomen'): ?>
    <p class="bh-meta">Zij waren er niet bij. Er is geen gesprek, wel een besluit nodig; bij afwijzen krijgen ze een eigen standaardtekst.</p>
  <?php endif; ?>
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
                     tw_aanwezigheid_tekst($_k),
                     $_groep !== 'gehad' && !empty($_k['evaluatie']) ? 'indruk: ' . mb_strtolower(tw_evaluatie_standen()[$_k['evaluatie']] ?? '') : '',
                     $_groep === 'bezig' ? 'bezig door ' . evenement_claim_naam((int)$_k['gesprek_door'])
                                           . (!empty($_k['gesprek_sinds']) ? ' sinds ' . date('H:i', strtotime((string)$_k['gesprek_sinds'])) : '') : '']) ?>
      </span>
      <?php if ($_groep === 'gehad' && $_b): ?><?= bh_badge($_b[0] . (!empty($_k['doorgevoerd_op']) ? ' · doorgevoerd' : ''), $_b[1]) ?><?php endif; ?>
      <?php if ($_groep === 'bezig'): ?>
        <?= bh_badge('Bezig', 'oranje') ?>
        <?php if ($mag_beslissen) { $ov_sol_id = $_id; $ov_naam = (string)$_k['naam']; $ov_door = (int)$_k['gesprek_door'];
                                   $ov_sinds = $_k['gesprek_sinds'] ?? null; require __DIR__ . '/_overnemen.php'; } ?>
      <?php else: ?>
        <a class="ds-btn <?= $_groep === 'gehad' ? 'ds-btn-secundair' : 'ds-btn-primair' ?> ds-btn-sm" href="<?= h(tw_url('gesprekken', $_id)) ?>">
          <?= ['nog' => 'Gesprek starten', 'niet_gekomen' => 'Besluit vastleggen'][$_groep] ?? (empty($_k['doorgevoerd_op']) ? 'Verder' : 'Bekijken') ?>
        </a>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>

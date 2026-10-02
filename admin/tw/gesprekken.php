<?php
/**
 * TW-stap Gesprekken (bestuur): iedereen krijgt een gesprek. "Bezig door X" voorkomt dat twee
 * bestuursleden dezelfde persoon openen (30 min); wie het bewerkrecht heeft kan zo'n gesprek bewust
 * overnemen (admin/tw/_overnemen.php). Wie niet kwam staat apart ("Niet gekomen"): geen gesprek,
 * wel een besluit. Zoeken en filteren via de gedeelde filterbalk (werkt zonder JavaScript).
 *
 * "Nog te spreken" staat in gespreksvolgorde (tw_gesprek_volgorde(), 2.153.0): eerst wie eerder weg
 * moet, dan op naam; terugkerende strandwachten ingeklapt apart, zodat ze de anderen niet voorgaan.
 * De volgorde is aan te passen met omhoog/omlaag (zonder JavaScript) of door te slepen
 * (assets/js/volgorde.js), en een strandwacht zet je met "Naar de lijst" tussen de anderen.
 * Onderdeel van admin/trainingsweekend.php; het gesprek zelf staat in admin/tw/gesprek.php.
 */
// Terug in het overzicht = in geen gesprek meer: je open gesprek komt vrij voor je collega's.
if (!empty($ik_id)) evenement_claim_los_overige('tw_deelname', 'gesprek', $sid, $ik_id);
$_sorts = ['volgorde' => 'Gespreksvolgorde', 'naam' => 'Op naam', 'sd' => 'Op selectiedag-cijfer'];
$_f = ['q' => (string)($_GET['q'] ?? ''), 'stand' => (string)($_GET['stand'] ?? ''),
       'besluit' => (string)($_GET['besluit'] ?? ''), 'sort' => (string)($_GET['sort'] ?? 'volgorde')];
if (!isset($_sorts[$_f['sort']])) $_f['sort'] = 'volgorde';
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

// Gespreksvolgorde: alleen ordenen als de lijst compleet is (niet gefilterd/gezocht), anders klopt "omhoog" niet.
$_ordenen = $_f['sort'] === 'volgorde';
$_kan_schuiven = $_ordenen && !$_gefilterd && $mag_beslissen;
$_volg = $_ordenen ? tw_gesprek_volgorde($_st['nog']) : ['hoofd' => $_st['nog'], 'apart' => []];
$_handmatig = (bool)array_filter($_alle, fn($k) => ($k['gesprek_positie'] ?? null) !== null && $k['_stand'] === 'nog');
$_dagen = tw_dagen($seizoen);

/** Eén rij in de lijst. $plek: 'hoofd' (schuifbaar), 'apart' (strandwacht) of '' (andere groepen). */
$_rij = function (array $k, string $groep, string $plek = '', int $nr = 0, int $aantal = 0)
        use ($_besluit, $mag_beslissen, $_kan_schuiven, $_dagen): void {
    $id = (int)$k['sol_id'];
    $b  = $_besluit[$k['besluit'] ?? ''] ?? null;
    $weg = tw_eerder_weg_tekst($k['eerder_weg'] ?? null);
    ?>
    <li class="bh-twgesprek-rij" id="gs-<?= $id ?>" data-id="<?= $id ?>"<?= $plek === 'hoofd' && $_kan_schuiven ? ' draggable="true"' : '' ?>>
      <?php if ($plek === 'hoofd' && $_kan_schuiven): ?><span class="bh-volgorde-nr bh-num" aria-hidden="true"><?= $nr ?></span><?php endif; ?>
      <?= bh_avatar($k, 44) ?>
      <span class="bh-twgesprek-wie">
        <span class="bh-twgesprek-naam"><?= h((string)$k['naam']) ?></span>
        <?= bh_meta([$k['leeftijd'] !== null ? $k['leeftijd'] . ' jaar' : '',
                     $k['_sd'] !== null ? 'selectiedag ' . cijfer_nl((float)$k['_sd']) : '',
                     tw_aanwezigheid_tekst($k),
                     !empty($k['is_lifeguard']) ? 'terugkerende strandwacht' : '',
                     $groep !== 'gehad' && !empty($k['evaluatie']) ? 'indruk: ' . mb_strtolower(tw_evaluatie_standen()[$k['evaluatie']] ?? '') : '',
                     $groep === 'bezig' ? 'bezig door ' . evenement_claim_naam((int)$k['gesprek_door'])
                                          . (!empty($k['gesprek_sinds']) ? ' sinds ' . date('H:i', strtotime((string)$k['gesprek_sinds'])) : '') : '']) ?>
        <?php if ($weg !== '' && $groep !== 'gehad'): ?>
          <span class="bh-twgesprek-weg"><?= icoon('klok', 14) ?> Moet weg: <?= h($weg) ?></span>
        <?php endif; ?>
      </span>
      <?php if ($groep === 'gehad' && $b): ?><?= bh_badge($b[0] . (!empty($k['doorgevoerd_op']) ? ' · doorgevoerd' : ''), $b[1]) ?><?php endif; ?>
      <?php if ($groep === 'bezig'): ?>
        <?= bh_badge('Bezig', 'oranje') ?>
        <?php if ($mag_beslissen) { $ov_sol_id = $id; $ov_naam = (string)$k['naam']; $ov_door = (int)$k['gesprek_door'];
                                   $ov_sinds = $k['gesprek_sinds'] ?? null; require __DIR__ . '/_overnemen.php'; } ?>
      <?php else: ?>
        <span class="bh-twgesprek-acties">
          <?php if ($plek === 'hoofd' && $_kan_schuiven): ?>
            <form method="post" class="bh-volgorde-knoppen">
              <?= csrf_input() ?>
              <input type="hidden" name="actie" value="gesprek_volgorde">
              <input type="hidden" name="sol_id" value="<?= $id ?>">
              <button type="submit" name="richting" value="op" class="bh-icoonknop" aria-label="<?= h((string)$k['naam']) ?> eerder"<?= $nr <= 1 ? ' disabled' : '' ?>><?= icoon('chevron-op', 16) ?></button>
              <button type="submit" name="richting" value="neer" class="bh-icoonknop" aria-label="<?= h((string)$k['naam']) ?> later"<?= $nr >= $aantal ? ' disabled' : '' ?>><?= icoon('chevron-neer', 16) ?></button>
              <?php if (!empty($k['is_lifeguard'])): ?>
                <button type="submit" name="richting" value="apart" class="bh-doc-actie">Terug naar strandwachten</button>
              <?php endif; ?>
            </form>
          <?php elseif ($plek === 'apart' && $mag_beslissen): ?>
            <form method="post">
              <?= csrf_input() ?>
              <input type="hidden" name="actie" value="gesprek_volgorde">
              <input type="hidden" name="sol_id" value="<?= $id ?>">
              <button type="submit" name="richting" value="lijst" class="ds-btn ds-btn-zacht ds-btn-sm">Naar de lijst</button>
            </form>
          <?php endif; ?>
          <a class="ds-btn <?= $groep === 'gehad' ? 'ds-btn-secundair' : 'ds-btn-primair' ?> ds-btn-sm" href="<?= h(tw_url('gesprekken', $id)) ?>">
            <?= ['nog' => 'Gesprek starten', 'niet_gekomen' => 'Besluit vastleggen'][$groep] ?? (empty($k['doorgevoerd_op']) ? 'Verder' : 'Bekijken') ?>
          </a>
        </span>
      <?php endif; ?>
      <?php if ($groep === 'nog' && $mag_beslissen && $_dagen): $_wd = substr((string)($k['eerder_weg'] ?? ''), 0, 10); ?>
        <details class="bh-twgesprek-wegform">
          <summary><?= $weg !== '' ? 'Tijd aanpassen' : 'Moet eerder weg?' ?></summary>
          <?php // Gewoon opslaan en herladen: de tijd bepaalt de plek in de lijst. ?>
          <form method="post" class="bh-knoprij">
            <?= csrf_input() ?>
            <input type="hidden" name="actie" value="eerder_weg">
            <input type="hidden" name="sol_id" value="<?= $id ?>">
            <label class="bh-vw" for="wd-<?= $id ?>">Dag</label>
            <select class="ds-select" id="wd-<?= $id ?>" name="dag">
              <option value="">Niet eerder weg</option>
              <?php foreach ($_dagen as $_d): ?><option value="<?= h($_d) ?>"<?= $_d === $_wd ? ' selected' : '' ?>><?= h(datum_dag_nl($_d)) ?></option><?php endforeach; ?>
            </select>
            <label class="bh-vw" for="wt-<?= $id ?>">Tijd</label>
            <input class="ds-input" type="time" id="wt-<?= $id ?>" name="tijd" value="<?= h(substr((string)($k['eerder_weg'] ?? ''), 11, 5)) ?>">
            <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Opslaan</button>
          </form>
        </details>
      <?php endif; ?>
    </li>
    <?php
};
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
    ['soort' => 'sorteer', 'naam' => 'sort', 'label' => 'Sorteren', 'waarde' => $_f['sort'], 'opties' => $_sorts, 'standaard' => 'volgorde'],
  ];
  $filter_wissen = ($_gefilterd || $_f['sort'] !== 'volgorde') ? tw_url('gesprekken') : null;
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
  <div class="bh-kaartkop">
    <h3><?= h($_titel) ?> <span class="bh-meta"><?= count($_st[$_groep]) ?></span></h3>
    <?php if ($_groep === 'nog' && $_kan_schuiven && $_handmatig): ?>
      <form method="post" data-bevestig="De volgorde gaat weer vanzelf: eerst wie eerder weg moet, dan op naam. Strandwachten gaan terug naar hun eigen blok.">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="gesprek_volgorde_herstel">
        <button type="submit" class="bh-doc-actie">Volgorde herstellen</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if ($_groep === 'niet_gekomen'): ?>
    <p class="bh-meta">Zij waren er niet bij. Er is geen gesprek, wel een besluit nodig; bij afwijzen krijgen ze een eigen standaardtekst.</p>
  <?php elseif ($_groep === 'nog' && $_ordenen): ?>
    <p class="bh-meta">
      Wie eerder weg moet, staat bovenaan.
      <?= $_kan_schuiven ? 'Pas de volgorde aan met de pijltjes of door een rij te slepen.' : ($_gefilterd ? 'Wis de filters om de volgorde aan te passen.' : '') ?>
    </p>
  <?php endif; ?>

  <?php if ($_groep === 'nog'): $_n = count($_volg['hoofd']); ?>
    <?php if ($_volg['hoofd']): ?>
    <ul class="bh-twgesprek-lijst"<?= $_kan_schuiven ? ' data-volgorde data-csrf="' . h(csrf_token()) . '" data-url="' . h(tw_url('gesprekken')) . '"' : '' ?>>
      <?php foreach ($_volg['hoofd'] as $_i => $_k) $_rij($_k, 'nog', 'hoofd', $_i + 1, $_n); ?>
    </ul>
    <?php endif; ?>
    <?php if ($_volg['apart']): ?>
    <details class="bh-klap bh-twgesprek-apart" data-klap-id="tw-gesprek-lifeguards">
      <summary>
        <span class="bh-klap-titel">Terugkerende strandwachten</span>
        <span class="bh-klap-sam"><?= count($_volg['apart']) ?> · gaan de anderen niet voor; zet er één met "Naar de lijst" tussen</span>
      </summary>
      <div class="bh-klap-inhoud">
        <ul class="bh-twgesprek-lijst">
          <?php foreach ($_volg['apart'] as $_k) $_rij($_k, 'nog', 'apart'); ?>
        </ul>
      </div>
    </details>
    <?php endif; ?>
  <?php else: ?>
  <ul class="bh-twgesprek-lijst">
    <?php foreach ($_st[$_groep] as $_k) $_rij($_k, $_groep); ?>
  </ul>
  <?php endif; ?>
</section>
<?php endforeach; ?>
<?php if ($_kan_schuiven): ?>
<script src="<?= APP_URL ?>/assets/js/volgorde.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/volgorde.js') ?>" defer></script>
<?php endif; ?>

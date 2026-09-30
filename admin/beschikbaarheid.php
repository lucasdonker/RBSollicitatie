<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
$pagina_titel = 'Beschikbaarheid';
$actieve_nav  = 'beschikbaarheid';
// Een raster met een kolom per week is geen tekst: de leesbreedte van 1400px zou het
// laten schuiven terwijl er rechts ruimte over is.
$pagina_breed = true;
vereis_recht('beschikbaarheid', 'lezen');

$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;

// Raster + filters: één bron voor scherm en export (includes/beheer/beschikbaarheid.php).
$filter  = bh_besch_filter($_GET);
$raster  = bh_besch_raster($seizoen, $filter);
$weken        = $raster['weken'];
$lijst        = $raster['lijst'];
$eff          = $raster['eff'];
$weken_count  = $raster['weken_count'];
$vaste_map    = $raster['vaste'];
$tot_per_week = $raster['tot_per_week'];
// ── Wat er nog op een besluit wacht ──────────────────────────
$voorlopigen    = function_exists('beschikbaarheid2_voorlopigen') && $sid ? beschikbaarheid2_voorlopigen($sid) : [];
$besch_deadline = function_exists('beschikbaarheid2_deadline') ? beschikbaarheid2_deadline($seizoen) : null;
$open_verzoeken = function_exists('beschikbaarheid2_open_verzoeken') && $sid ? beschikbaarheid2_open_verzoeken($sid) : [];

$vz_wkd = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen) : [];
$vz_lbl = function ($wn) use ($vz_wkd) {
    $wn = (int)$wn;
    return isset($vz_wkd[$wn]) ? 'W' . $wn . ' (' . datum_kort_nl($vz_wkd[$wn]['start']) . ')' : 'W' . $wn;
};

// Dekking: dezelfde norm als het dekkingsscherm, uit Posten & bezetting
$dek_norm = bh_dekking_norm();
$dekking  = bh_dekking_weken($weken, $tot_per_week, $dek_norm);

$mag_bewerken = mag('beschikbaarheid', 'bewerken');
// De planner ziet wie wanneer kan, maar geen dossier en geen e-mailadres (dataminimalisatie).
$mag_dossier  = mag('sollicitanten', 'lezen');
$naam_html    = fn(int $sol_id, string $naam): string => $mag_dossier
    ? '<a href="' . APP_URL . '/admin/sollicitant.php?id=' . $sol_id . '&amp;tab=beschikbaarheid">' . h($naam) . '</a>'
    : h($naam);
$geen_grens   = !$seizoen || (empty($seizoen['hoofdseizoen_start_week']) && empty($seizoen['hoofdseizoen_eind_week']));

require __DIR__ . '/layout_top.php';
?>

<?php if ($open_verzoeken): ?>
<section class="ds-kaart bh-actiekaart">
  <header class="bh-actiekaart-kop">
    <?= icoon('bel', 18) ?>
    <h2>Openstaande verzoeken: <?= count($open_verzoeken) ?></h2>
  </header>
  <p class="bh-actiekaart-uitleg">
    Verzoeken om minder dagen per week te werken.<?= $mag_bewerken ? ' Je beoordeelt ze hier.' : ' Het bestuur beoordeelt ze.' ?>
    <?= $mag_dossier ? 'De naam opent het dossier van de sollicitant.' : '' ?>
  </p>
  <ul class="bh-verzoeklijst">
    <?php foreach ($open_verzoeken as $vz):
      $naam = trim($vz['voornaam'] . ' ' . ($vz['tussenvoegsel'] ? $vz['tussenvoegsel'] . ' ' : '') . $vz['achternaam']);
    ?>
    <li id="cvz-<?= (int)$vz['id'] ?>" class="bh-verzoek">
      <div class="bh-verzoek-kop">
        <?php /* Naar het tabblad dat bij dít scherm hoort: je komt van de beschikbaarheid,
                 dus daar wil je ook landen. sollicitant.php valt terug op 'dossier' als
                 de persoon dat tabblad niet heeft. */ ?>
        <span class="bh-verzoek-naam"><?= $naam_html((int)$vz['sol_id'], $naam) ?></span>
        <span class="bh-verzoek-wat">
          <?= h($vz_lbl($vz['van_week'])) ?> t/m <?= h($vz_lbl($vz['tot_week'])) ?>:
          <strong><?= (int)$vz['dagen_aantal'] ?> dagen per week</strong>
        </span>
        <?php if ($mag_bewerken): ?>
        <span class="bh-verzoek-knoppen">
          <button type="button" class="ds-btn ds-btn-primair ds-btn-sm"
                  data-klik="verzoekBeslis(<?= (int)$vz['id'] ?>,'goedkeuren',<?= (int)$vz['sol_id'] ?>)">Goedkeuren</button>
          <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
                  data-klik="verzoekBeslis(<?= (int)$vz['id'] ?>,'afwijzen',<?= (int)$vz['sol_id'] ?>)">Afwijzen</button>
        </span>
        <?php endif; ?>
      </div>
      <?php if (!empty($vz['dagen_pool'])): ?>
        <p class="bh-verzoek-regel">Kan die week: <strong><?= h(implode(', ', array_map('dag_kort', $vz['dagen_pool']))) ?></strong></p>
      <?php endif; ?>
      <?php if (!empty($vz['toelichting'])): ?>
        <p class="bh-verzoek-regel">Reden: <?= h($vz['toelichting']) ?></p>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($voorlopigen):
  $dgn_tot = $besch_deadline ? (int)floor(($besch_deadline->getTimestamp() - strtotime('today')) / 86400) : null;
?>
<?php // Informatief (de herinneringen gaan vanzelf): ingeklapt, met de stand in de kop. ?>
<details class="bh-klap ds-kaart bh-actiekaart bh-actiekaart-zacht" data-klap-id="besch-voorlopig">
  <summary>
    <span class="bh-klap-titel">Nog voorlopig: <?= count($voorlopigen) ?> <?= count($voorlopigen) === 1 ? 'persoon' : 'personen' ?></span>
    <?php if ($besch_deadline): ?>
      <span class="bh-klap-sam"><?= bh_stoplicht(
            $dgn_tot === null ? 'goed' : ($dgn_tot < 0 ? 'slecht' : ($dgn_tot <= 7 ? 'krap' : 'goed')),
            'deadline ' . datum_nl($besch_deadline->format('Y-m-d'))
            . ($dgn_tot === null ? '' : ' · ' . ($dgn_tot < 0
                ? 'verstreken'
                : 'nog ' . $dgn_tot . ' ' . ($dgn_tot === 1 ? 'dag' : 'dagen')))
          ) ?></span>
    <?php endif; ?>
  </summary>
  <div class="bh-klap-inhoud">
    <p class="bh-actiekaart-uitleg">
      Deze mensen hebben hun beschikbaarheid nog niet definitief gemaakt. Ze krijgen vanaf twee weken
      vóór de deadline automatisch een herinnering — je hoeft ze niet zelf na te bellen.
    </p>
    <ul class="bh-persoonchips">
      <?php foreach ($voorlopigen as $v):
        $naam = trim($v['voornaam'] . ' ' . ($v['tussenvoegsel'] ? $v['tussenvoegsel'] . ' ' : '') . $v['achternaam']);
      ?>
      <li class="bh-persoonchip">
        <?= h($naam) ?>
        <?php if (!empty($v['email']) && $mag_dossier): ?>
          <a href="mailto:<?= h($v['email']) ?>" title="Mail <?= h($naam) ?>" aria-label="Mail <?= h($naam) ?>"><?= icoon('mail', 14) ?></a>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</details>
<?php endif; ?>

<?php
  // Filteren, sorteren en de weergave. De export volgt precies wat het scherm toont.
  $_exp  = http_build_query(array_filter($filter, fn($v) => $v !== ''));
  $_wkop = ['' => ''];
  foreach ($raster['alle_weken'] as $_wn => $_w) $_wkop[(string)$_wn] = 'W' . $_wn . ' (' . $_w['kort'] . ')';
  $filter_velden = [
    ['soort' => 'zoek', 'naam' => 'q', 'label' => 'Zoek op naam', 'waarde' => $filter['q']],
    ['soort' => 'keuze', 'naam' => 'groep', 'label' => 'Wie', 'waarde' => $filter['groep'],
     'opties' => ['' => 'Iedereen', 'proces' => 'Nog in de procedure', 'aangenomen' => 'Aangenomen']],
    ['soort' => 'keuze', 'naam' => 'weergave', 'label' => 'Weergave', 'waarde' => $filter['weergave'],
     'opties' => ['' => 'Per week', 'dag' => 'Per dag (kan, liever niet, niet)']],
    ['soort' => 'sorteer', 'naam' => 'sort', 'label' => 'Sorteren', 'waarde' => $filter['sort'], 'opties' => bh_besch_sorteringen()],
    ['soort' => 'keuze', 'naam' => 'van', 'label' => 'Vanaf week', 'waarde' => $filter['van'], 'meer' => true,
     'opties' => ['' => 'Vanaf: begin'] + array_map(fn($l) => 'Vanaf ' . $l, array_slice($_wkop, 1, null, true))],
    ['soort' => 'keuze', 'naam' => 'tot', 'label' => 'Tot en met week', 'waarde' => $filter['tot'], 'meer' => true,
     'opties' => ['' => 'Tot: eind'] + array_map(fn($l) => 't/m ' . $l, array_slice($_wkop, 1, null, true))],
    ['soort' => 'keuze', 'naam' => 'min', 'label' => 'Minimaal weken', 'waarde' => $filter['min'], 'meer' => true,
     'opties' => ['' => 'Weken: alle'] + array_combine(array_map('strval', [2, 4, 6, 8, 10]), array_map(fn($n) => 'Minimaal ' . $n . ' weken', [2, 4, 6, 8, 10]))],
    ['soort' => 'keuze', 'naam' => 'type', 'label' => 'Nieuw of terug', 'waarde' => $filter['type'], 'meer' => true,
     'opties' => ['' => 'Nieuw en terugkerend', 'nieuw' => 'Alleen nieuw', 'terug' => 'Alleen terugkerend']],
    ['soort' => 'keuze', 'naam' => 'toon', 'label' => 'Toon', 'waarde' => $filter['toon'], 'meer' => true,
     'opties' => ['' => 'Alle opgaven', 'verzoek' => 'Met open verzoek', 'voorlopig' => 'Nog voorlopig']],
    ['soort' => 'keuze', 'naam' => 'lv', 'label' => 'Liever vrij', 'waarde' => $filter['lv'], 'meer' => true,
     'opties' => ['' => 'Liever vrij: iedereen', 'ja' => 'Alleen wie ergens liever vrij is']],
  ];
  $filter_wissen = bh_besch_gefilterd($filter) ? APP_URL . '/admin/beschikbaarheid.php' . ($filter['weergave'] ? '?weergave=dag' : '') : null;
  // Exporteren staat in één menu: dezelfde filters en hetzelfde weekbereik als het scherm.
  $_exporten = [
    ['label' => 'Excel per week', 'url' => APP_URL . '/admin/export_beschikbaarheid.php?soort=week' . ($_exp ? '&' . $_exp : ''), 'icoon' => 'download',
     'titel' => 'Per persoon per week het aantal dagen, met liever vrij'],
    ['label' => 'Excel per dag', 'url' => APP_URL . '/admin/export_beschikbaarheid.php?soort=dag' . ($_exp ? '&' . $_exp : ''), 'icoon' => 'download',
     'titel' => 'Per persoon per datum: ja, liever niet of nee, met de redenen'],
  ];
  // Wat het rooster nodig heeft (afgesproken dagen + beschikbaarheid); hier voor de planner, die geen loonplanning ziet.
  if (module_aan('loonplanning')) {
      $_exporten[] = ['label' => 'Export voor het rooster', 'url' => APP_URL . '/admin/export_rooster.php', 'icoon' => 'download',
                      'titel' => 'Per persoon per week de afgesproken dagen en wanneer iemand kan'];
  }
  $filter_acties = [['label' => 'Exporteren', 'icoon' => 'export', 'toon' => 'secundair', 'menu' => $_exporten]];
  if ($raster['totaal_zonder_filter'] > 0) require __DIR__ . '/_filterbalk.php';
?>

<?php if (empty($lijst)): ?>
  <?php
  if (bh_besch_gefilterd($filter) && $raster['totaal_zonder_filter'] > 0) {
      $leeg_titel = 'Niemand gevonden';
      $leeg_tekst = 'Met deze filters blijft er niemand over. Pas ze aan of wis ze.';
      $leeg_icoon = 'zoek';
  } else {
      $leeg_titel = 'Nog niemand met een ingevuld formulier';
      $leeg_tekst = 'Zodra een sollicitant het kennismakingsformulier invult, verschijnt de beschikbaarheid hier.';
      $leeg_icoon = 'kalender';
  }
  require __DIR__ . '/_leeg.php';
  ?>
<?php else: ?>

<?php
$_dek   = $dekking;
$_weken = $weken;
$_klap  = 'besch-dekking';   // standaard dicht; de stand staat in de kop
require __DIR__ . '/_dekkinggrafiek.php';
?>

<?php if ($geen_grens): ?>
<p class="bh-tiptekst">
  <?= icoon('info', 15) ?>
  Het hoofdseizoen is nog niet ingesteld, dus elke week telt nu als hoofdseizoen. Stel het in bij
  <a href="<?= APP_URL ?>/admin/seizoen.php">Seizoen</a> — dan rekenen voor- en naseizoen op het weekend.
</p>
<?php endif; ?>

<?php $_per_dag = $filter['weergave'] === 'dag'; ?>
<section class="ds-kaart bh-raster">
  <header class="bh-raster-kop">
    <h2><?= $_per_dag ? 'Per dag' : 'Per week' ?></h2>
    <span class="bh-raster-uitleg">
      <?php if ($_per_dag): ?>
        <?= h('W' . array_key_first($weken) . ' t/m W' . array_key_last($weken)) ?> · kies andere weken onder Meer filters
      <?php else: ?>
        <?= $mag_bewerken ? 'klik een cel om die week van die persoon te corrigeren' : 'alleen lezen' ?>
      <?php endif; ?>
    </span>
    <span class="bh-raster-legenda">
      <?php if ($_per_dag): ?>
        <span><i class="bh-legenda-blok bh-weekcel-vol"></i>ja = kan</span>
        <span><i class="bh-legenda-blok bh-weekcel-deels"></i>lv = liever niet</span>
        <span><i class="bh-legenda-blok bh-weekcel-leeg"></i>— = kan niet</span>
      <?php else: ?>
        <span><i class="bh-legenda-blok bh-weekcel-vol"></i>hele week</span>
        <span><i class="bh-legenda-blok bh-weekcel-weekend"></i>weekend</span>
        <span><i class="bh-legenda-blok bh-weekcel-deels"></i>minder dagen</span>
        <span><i class="bh-legenda-blok bh-weekcel-correctie"></i>correctie bestuur</span>
        <span><i class="bh-legenda-blok bh-weekcel-verzoek"></i>verzoek open (3? = vraagt 3 dagen)</span>
        <span>lv = dagen liever vrij</span>
      <?php endif; ?>
    </span>
  </header>

  <?php if ($_per_dag):
    $_dagen = bh_besch_dagen($lijst, $weken);
    $_datums = [];
    foreach ($weken as $_w) for ($_i = 0; $_i < 7; $_i++) $_datums[] = date('Y-m-d', strtotime($_w['start'] . ' +' . $_i . ' days'));
  ?>
  <div class="bh-rasterrol">
    <table class="bh-rastertabel bh-dagraster" style="--bh-tracks:<?= 'minmax(190px,1.3fr) repeat(' . count($_datums) . ',minmax(38px,1fr))' ?>">
      <caption class="sr-only">Beschikbaarheid per sollicitant per dag</caption>
      <thead>
        <tr>
          <th scope="col" class="bh-raster-naam">Sollicitant (<?= count($lijst) ?>)</th>
          <?php foreach ($_datums as $_d): $_n = (int)date('N', strtotime($_d)); ?>
            <th scope="col" class="bh-raster-wk<?= $_n >= 6 ? ' bh-dag-weekend' : '' ?><?= $_n === 1 ? ' bh-dag-maandag' : '' ?>" title="<?= h(datum_nl($_d)) ?>">
              <span class="bh-raster-wknr"><?= h(dag_kort($_n)) ?></span>
              <span class="bh-raster-wkdat"><?= (int)date('j', strtotime($_d)) ?></span>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lijst as $p): $sol_id = (int)$p['sol_id']; ?>
        <tr>
          <th scope="row" class="bh-raster-naam"><?= $naam_html($sol_id, (string)$p['naam']) ?></th>
          <?php foreach ($_datums as $_d):
            $_i = $_dagen[$sol_id][$_d] ?? ['stand' => 'nee', 'reden' => ''];
            $_t = ['ja' => ['ja', 'vol', 'kan'], 'liever' => ['lv', 'deels', 'liever niet'], 'nee' => ['—', 'leeg', 'kan niet']][$_i['stand']];
            $_tip = $p['naam'] . ', ' . datum_nl($_d) . ': ' . $_t[2] . ($_i['reden'] !== '' ? ' (' . $_i['reden'] . ')' : '');
          ?>
            <td class="bh-raster-cel<?= (int)date('N', strtotime($_d)) === 1 ? ' bh-dag-maandag' : '' ?>"><?= bh_weekcel($_t[0], $_t[1], ['titel' => $_tip]) ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="bh-raster-voet">
    Per dag uit dezelfde bron als het rooster: werkperiodes, vaste vrije dagen, afwezigheid, losse dagen en
    goedgekeurde verzoeken. Wijs een cel aan voor de reden. Corrigeren doe je in de weekweergave.
  </p>

  <?php else: ?>
  <div class="bh-rasterrol">
    <table class="bh-rastertabel" style="--bh-tracks:<?= 'minmax(205px,1.3fr) 46px minmax(90px,.8fr) repeat(' . count($weken) . ',minmax(44px,1fr))' ?>">
      <caption class="sr-only">Beschikbaarheid per sollicitant per week</caption>
      <thead>
        <tr>
          <th scope="col" class="bh-raster-naam">Sollicitant (<?= count($lijst) ?><?= bh_besch_gefilterd($filter) ? ' van ' . (int)$raster['totaal_zonder_filter'] : '' ?>)</th>
          <th scope="col" class="bh-raster-wkn" title="Aantal weken beschikbaar">wkn</th>
          <th scope="col" class="bh-raster-lv" title="Vaste voorkeur-vrije weekdagen en losse datums liever vrij">Liever vrij</th>
          <?php foreach ($weken as $wn => $w):
            $_type = (string)$w['weektype'];
            $_tt   = $_type === 'hoofd' ? 'Hoofdseizoen'
                   : ($_type === 'voor' ? 'Voorseizoen — alleen weekend bemand'
                                        : 'Naseizoen — alleen weekend bemand');
          ?>
          <th scope="col" class="bh-raster-wk bh-wk-<?= h($_type) ?><?= ($dekking[$wn]['stand'] ?? '') === 'slecht' ? ' bh-wk-laag' : '' ?>"
              title="<?= h($_tt) ?>">
            <span class="bh-raster-wknr">W<?= (int)$wn ?></span>
            <span class="bh-raster-wkdat"><?= h($w['kort']) ?></span>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lijst as $p):
          $sol_id = (int)$p['sol_id'];
          $naam   = (string)$p['naam'];
          /* Alleen JS-escapen, niet HTML-escapen: bh_weekcel() haalt de hele onclick
             door h(). Stond hier htmlspecialchars() omheen, dan werd " eerst &quot; en
             daarna &amp;quot;, en kreeg de browser "Unexpected token &" — het paneel
             ging dan bij geen enkele cel open. JSON_HEX_QUOT/APOS houden de string
             zonder aanhalingstekens, zodat h() er niets meer aan kan bederven. */
          $naam_js = json_encode($naam, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP);
          $_lv_tekst = bh_besch_liever_tekst($vaste_map[$sol_id] ?? null, $raster['liever'][$sol_id] ?? []);
          $_vast = $vaste_map[$sol_id] ?? null;
          $_vast = is_array($_vast) ? $_vast : (json_decode((string)$_vast, true) ?: []);
          $_n_los = array_sum(array_map('count', $raster['liever'][$sol_id] ?? []));
        ?>
        <tr>
          <th scope="row" class="bh-raster-naam">
            <?= $naam_html($sol_id, $naam) ?>
            <?php if (!empty($p['terugkerend'])): ?><?= bh_chip('terug', 'blauw') ?><?php endif; ?>
            <?php if (!empty($p['norm_dagen'])): ?>
              <span title="Afwijkende norm: <?= (int)$p['norm_dagen'] ?> dagen per week"><?= bh_chip((int)$p['norm_dagen'] . 'd', 'oranje') ?></span>
            <?php endif; ?>
          </th>
          <td class="bh-raster-wkn bh-num" title="<?= (int)($raster['dagen_totaal'][$sol_id] ?? 0) ?> dagen in totaal"><?= (int)($weken_count[$sol_id] ?? 0) ?></td>
          <td class="bh-raster-lv"<?= $_lv_tekst !== '' ? ' title="Liever vrij: ' . h($_lv_tekst) . ' — een voorkeur, de planner beslist"' : '' ?>>
            <?php if ($_vast): ?><?= bh_chip(implode(' ', array_map(fn($d) => dag_kort((int)$d), $_vast)), 'paars') ?><?php endif; ?>
            <?php if ($_n_los): ?><span class="bh-meta"><?= $_n_los ?> <?= $_n_los === 1 ? 'dag' : 'dagen' ?></span><?php endif; ?>
            <?php if (!$_vast && !$_n_los): ?><span class="bh-meta">—</span><?php endif; ?>
          </td>
          <?php foreach ($weken as $wn => $w):
            $c = $eff[$sol_id][$wn] ?? null;
            $klik = $mag_bewerken ? 'correctieOpen(' . $sol_id . ',' . (int)$wn . ',' . $naam_js . ')' : null;
            $_ct   = bh_besch_cel_tekst($c);
            $_lv   = $raster['liever'][$sol_id][$wn] ?? [];
            $tip   = $naam . ' — week ' . $wn . ': ' . $_ct[1]
                   . ($_lv ? '; liever vrij op ' . implode(', ', array_map('datum_kort_nl', $_lv)) : '');
          ?>
          <td class="bh-raster-cel"><?= bh_weekcel($_ct[0], $_ct[2], ['titel' => $tip, 'klik' => $klik, 'sub' => $c && $_lv ? count($_lv) . ' lv' : '']) ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th scope="row" class="bh-raster-naam">Totaal <span class="bh-raster-totsub">· weekend</span></th>
          <td class="bh-raster-wkn"></td>
          <td class="bh-raster-lv"></td>
          <?php foreach ($weken as $wn => $w): $d = $dekking[$wn]; ?>
          <td class="bh-raster-tot bh-num<?= $d['stand'] === 'slecht' ? ' bh-raster-tot-laag' : '' ?>"
              title="Week <?= (int)$wn ?>: <?= $d['aantal'] ?> beschikbaar, <?= $d['weekend'] ?> dekken het weekend<?= $dek_norm > 0 ? ' (minimaal ' . $dek_norm . ' nodig)' : '' ?>">
            <?= $d['aantal'] ?><span class="bh-raster-totwk"> / <?= $d['weekend'] ?></span>
          </td>
          <?php endforeach; ?>
        </tr>
      </tfoot>
    </table>
  </div>

  <p class="bh-raster-voet">
    Onderaan per week: hoeveel <?= bh_besch_gefilterd($filter) ? 'van de getoonde ' : '' ?>mensen er beschikbaar zijn en hoeveel daarvan het <strong>weekend</strong>
    dekken. Rood betekent onder de norm van <?= $dek_norm > 0 ? $dek_norm . ' mensen' : 'de posten' ?>.
    "Liever vrij" is een voorkeur, geen blokkade: de planner beslist. Kies Per dag om te zien op welke dagen.
  </p>
  <?php endif; ?>

  <?= bh_versheid(bh_laatst(
        "SELECT MAX(GREATEST(COALESCE(b.definitief_op,'1000-01-01'), COALESCE(b.ingevuld_op,'1000-01-01')))
         FROM beschikbaarheid b JOIN sollicitaties s ON s.id = b.sollicitatie_id
         WHERE s.seizoen_id = ?", [$sid]), 'Beschikbaarheid') ?>
</section>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

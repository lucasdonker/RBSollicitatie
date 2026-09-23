<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
$pagina_titel = 'Beschikbaarheid';
$actieve_nav  = 'beschikbaarheid';
// Een raster met een kolom per week is geen tekst: de leesbreedte van 1400px zou het
// laten schuiven terwijl er rechts ruimte over is.
$pagina_breed = true;
vereis_recht('beschikbaarheid', 'lezen');

$db      = db();
$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;

// ── Seizoen-werkperiode -> weken met datums ──────────────────
$werk_start = $seizoen['datum_werk_start'] ?? null;
$werk_eind  = $seizoen['datum_werk_eind']  ?? null;
if (!$werk_start) {
    $jaar = (int)date('Y');
    $werk_start = (new DateTime())->setISODate($jaar, 22, 1)->format('Y-m-d');
    $werk_eind  = (new DateTime())->setISODate($jaar, 35, 7)->format('Y-m-d');
}
$weken = [];
$dag = new DateTime($werk_start);
$eind_dag = new DateTime($werk_eind);
while ($dag->format('N') != 1) $dag->modify('-1 day');
while ($dag <= $eind_dag) {
    $wn = (int)$dag->format('W');
    $zo = (clone $dag)->modify('+6 days');
    $weken[$wn] = [
        'wn'       => $wn,
        'kort'     => datum_kort_nl($dag),
        'eind'     => datum_kort_nl($zo),
        'weektype' => function_exists('seizoen_weektype') ? seizoen_weektype($seizoen ?? [], $wn) : 'hoofd',
    ];
    $dag->modify('+7 days');
}

// ── Sollicitanten met een formulier of een beschikbaarheid ───
// Eerder alleen wie een BLK-rij had: wie eerst zijn beschikbaarheid doorgaf, stond
// hier niet, terwijl het dekkingsscherm (bezetting.php) hem wel meetelde. Zelfde
// uitsluiting als daar: afgewezen en afgehaakt.
$lijst = [];
if ($sid) {
    if (function_exists('beschikbaarheid2_auto_setup')) beschikbaarheid2_auto_setup(); // beschikbaarheid_blok moet bestaan
    $q = $db->prepare("
        SELECT s.id sol_id, s.fase, s.terugkerend,
               p.voornaam, p.tussenvoegsel, p.achternaam,
               bsch.weken_json,
               prof.norm_dagen
        FROM sollicitaties s
        JOIN personen p ON p.id = s.persoon_id
        LEFT JOIN beschikbaarheid bsch ON bsch.sollicitatie_id = s.id
        LEFT JOIN beschikbaarheid_profiel prof ON prof.sollicitatie_id = s.id
        WHERE s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt')
          AND (bsch.sollicitatie_id IS NOT NULL
               OR EXISTS (SELECT 1 FROM blk_formulieren bl WHERE bl.sollicitatie_id = s.id)
               OR EXISTS (SELECT 1 FROM beschikbaarheid_blok bb WHERE bb.sollicitatie_id = s.id))
        ORDER BY p.achternaam, p.voornaam
    ");
    $q->execute([$sid]);
    $lijst = $q->fetchAll();
}

// ── Per persoon effectieve beschikbaarheid ───────────────────
$eff = [];
$weken_count = [];   // sol_id => aantal beschikbare weken
$vaste_map = [];     // sol_id => [voorkeur vrije dagen]
$tot_per_week = [];
foreach ($weken as $wn => $_) { $tot_per_week[$wn] = ['aantal'=>0, 'weekend'=>0]; }

foreach ($lijst as $p) {
    $sol_id = (int)$p['sol_id'];
    $b2 = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol_id) : [];
    $heeft_b2 = !empty($b2);

    // Voorkeur-vrije dagen ophalen voor de naam-kolom
    if (function_exists('beschikbaarheid2_van')) {
        $prof = beschikbaarheid2_van($sol_id)['profiel'] ?? null;
        if ($prof && !empty($prof['vaste_dagen_uit'])) $vaste_map[$sol_id] = $prof['vaste_dagen_uit'];
    }

    $oude_weken = [];
    if (!$heeft_b2 && !empty($p['weken_json'])) {
        $d = json_decode($p['weken_json'], true);
        if (is_array($d)) $oude_weken = array_map('intval', $d);
    }

    // Soort per week, expliciet. Eerder zat dit verstopt in een tekstveld ('weekend',
    // '3d', 'correctie'), waardoor "minder dagen" en "hele week" in het raster dezelfde
    // groene cel kregen: je zag niet dat iemand er maar drie dagen was. Een week die
    // door een correctie hélemaal vervalt komt niet terug uit beschikbaarheid2_effectief()
    // (die doet daar `continue`), dus die is hier vanzelf 'leeg'.
    $telling = 0;
    foreach ($weken as $wn => $_) {
        if ($heeft_b2) {
            if (!isset($b2[$wn])) continue;
            $w = $b2[$wn];
            $dagen   = (int)$w['dagen_max'];
            $weekend = count(array_intersect($w['dagen'], [5, 6, 7])) > 0;
            if (!empty($w['correctie']))                    $soort = 'correctie';
            elseif (($w['regime'] ?? 'vijf') === 'weekend') $soort = 'weekend';
            elseif ($dagen < 5)                             $soort = 'deels';
            else                                            $soort = 'vol';
        } else {
            if (!in_array($wn, $oude_weken, true)) continue;
            $dagen = 5; $weekend = true; $soort = 'vol';
        }
        $eff[$sol_id][$wn] = ['dagen' => $dagen, 'weekend' => $weekend, 'soort' => $soort];
        $tot_per_week[$wn]['aantal']++;
        if ($weekend) $tot_per_week[$wn]['weekend']++;
        $telling++;
    }
    $weken_count[$sol_id] = $telling;
}

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
    Verzoeken om minder dagen per week te werken. Beoordeel ze hier, of in het dossier van de sollicitant.
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
        <a class="bh-verzoek-naam" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= (int)$vz['sol_id'] ?>&amp;tab=beschikbaarheid"><?= h($naam) ?></a>
        <span class="bh-verzoek-wat">
          <?= h($vz_lbl($vz['van_week'])) ?> t/m <?= h($vz_lbl($vz['tot_week'])) ?>:
          <strong><?= (int)$vz['dagen_aantal'] ?> dagen per week</strong>
        </span>
        <?php if ($mag_bewerken): ?>
        <span class="bh-verzoek-knoppen">
          <button type="button" class="ds-btn ds-btn-primair ds-btn-sm"
                  onclick="verzoekBeslis(<?= (int)$vz['id'] ?>,'goedkeuren',<?= (int)$vz['sol_id'] ?>)">Goedkeuren</button>
          <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
                  onclick="verzoekBeslis(<?= (int)$vz['id'] ?>,'afwijzen',<?= (int)$vz['sol_id'] ?>)">Afwijzen</button>
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
<section class="ds-kaart bh-actiekaart bh-actiekaart-zacht">
  <header class="bh-actiekaart-kop">
    <?= icoon('klok', 18) ?>
    <h2>Nog voorlopig: <?= count($voorlopigen) ?> <?= count($voorlopigen) === 1 ? 'persoon' : 'personen' ?></h2>
    <?php if ($besch_deadline): ?>
      <?= bh_stoplicht(
            $dgn_tot === null ? 'goed' : ($dgn_tot < 0 ? 'slecht' : ($dgn_tot <= 7 ? 'krap' : 'goed')),
            'deadline ' . datum_nl($besch_deadline->format('Y-m-d'))
            . ($dgn_tot === null ? '' : ' · ' . ($dgn_tot < 0
                ? 'verstreken'
                : 'nog ' . $dgn_tot . ' ' . ($dgn_tot === 1 ? 'dag' : 'dagen')))
          ) ?>
    <?php endif; ?>
  </header>
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
      <?php if (!empty($v['email'])): ?>
        <a href="mailto:<?= h($v['email']) ?>" title="Mail <?= h($naam) ?>" aria-label="Mail <?= h($naam) ?>"><?= icoon('mail', 14) ?></a>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (empty($lijst)): ?>
  <?php
  $leeg_titel = 'Nog niemand met een ingevuld formulier';
  $leeg_tekst = 'Zodra een sollicitant het "Beter leren kennen"-formulier invult, verschijnt de beschikbaarheid hier.';
  $leeg_icoon = 'kalender';
  require __DIR__ . '/_leeg.php';
  ?>
<?php else: ?>

<?php
$_dek   = $dekking;
$_weken = $weken;
require __DIR__ . '/_dekkinggrafiek.php';
?>

<?php if ($geen_grens): ?>
<p class="bh-tip">
  <?= icoon('info', 15) ?>
  Het hoofdseizoen is nog niet ingesteld, dus elke week telt nu als hoofdseizoen. Stel het in bij
  <a href="<?= APP_URL ?>/admin/seizoen.php">Seizoen</a> — dan rekenen voor- en naseizoen op het weekend.
</p>
<?php endif; ?>

<section class="ds-kaart bh-raster">
  <header class="bh-raster-kop">
    <h2>Raster</h2>
    <span class="bh-raster-uitleg">
      <?= $mag_bewerken ? 'klik een cel om die week van die persoon te corrigeren' : 'alleen lezen' ?>
    </span>
    <span class="bh-raster-legenda">
      <span><i class="bh-legenda-blok bh-weekcel-vol"></i>hele week</span>
      <span><i class="bh-legenda-blok bh-weekcel-weekend"></i>weekend</span>
      <span><i class="bh-legenda-blok bh-weekcel-deels"></i>minder dagen</span>
      <span><i class="bh-legenda-blok bh-weekcel-correctie"></i>correctie bestuur</span>
    </span>
  </header>

  <div class="bh-rasterrol">
    <table class="bh-rastertabel" style="--bh-tracks:<?= 'minmax(205px,1.3fr) 46px repeat(' . count($weken) . ',minmax(44px,1fr))' ?>">
      <caption class="sr-only">Beschikbaarheid per sollicitant per week</caption>
      <thead>
        <tr>
          <th scope="col" class="bh-raster-naam">Sollicitant (<?= count($lijst) ?>)</th>
          <th scope="col" class="bh-raster-wkn" title="Aantal weken beschikbaar">wkn</th>
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
          $naam   = trim($p['voornaam'] . ' ' . ($p['tussenvoegsel'] ? $p['tussenvoegsel'] . ' ' : '') . $p['achternaam']);
          /* Alleen JS-escapen, niet HTML-escapen: bh_weekcel() haalt de hele onclick
             door h(). Stond hier htmlspecialchars() omheen, dan werd " eerst &quot; en
             daarna &amp;quot;, en kreeg de browser "Unexpected token &" — het paneel
             ging dan bij geen enkele cel open. JSON_HEX_QUOT/APOS houden de string
             zonder aanhalingstekens, zodat h() er niets meer aan kan bederven. */
          $naam_js = json_encode($naam, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP);
        ?>
        <tr>
          <th scope="row" class="bh-raster-naam">
            <a href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $sol_id ?>&amp;tab=beschikbaarheid"><?= h($naam) ?></a>
            <?php if (!empty($p['terugkerend'])): ?><?= bh_chip('terug', 'blauw') ?><?php endif; ?>
            <?php if (!empty($p['norm_dagen'])): ?>
              <span title="Afwijkende norm: <?= (int)$p['norm_dagen'] ?> dagen per week"><?= bh_chip((int)$p['norm_dagen'] . 'd', 'oranje') ?></span>
            <?php endif; ?>
            <?php if (!empty($vaste_map[$sol_id])): ?>
              <span title="Voorkeur vrij (de planner beslist): <?= h(implode(', ', array_map('dag_kort', $vaste_map[$sol_id]))) ?>">
                <?= bh_chip('vrij ' . implode(' ', array_map('dag_kort', $vaste_map[$sol_id])), 'paars') ?>
              </span>
            <?php endif; ?>
          </th>
          <td class="bh-raster-wkn bh-num"><?= (int)($weken_count[$sol_id] ?? 0) ?></td>
          <?php foreach ($weken as $wn => $w):
            $c = $eff[$sol_id][$wn] ?? null;
            $klik = $mag_bewerken ? 'correctieOpen(' . $sol_id . ',' . (int)$wn . ',' . $naam_js . ')' : null;

            $soort = is_array($c) ? (string)$c['soort'] : 'leeg';
            $dagen = is_array($c) ? (int)$c['dagen'] : 0;
            $wat   = [
                'leeg'      => ['—',    'niet beschikbaar'],
                'weekend'   => ['wk',   'alleen het weekend (' . $dagen . ' dagen)'],
                'deels'     => [$dagen, $dagen . ' van de 5 dagen'],
                'correctie' => [$dagen, $dagen . ' dagen — gecorrigeerd door het bestuur'],
                'vol'       => [$dagen, 'hele week (' . $dagen . ' dagen)'],
            ][$soort] ?? ['—', 'niet beschikbaar'];
            $tekst = (string)$wat[0];
            $tip   = $naam . ' — week ' . $wn . ': ' . $wat[1];
          ?>
          <td class="bh-raster-cel"><?= bh_weekcel($tekst, $soort, ['titel' => $tip, 'klik' => $klik]) ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th scope="row" class="bh-raster-naam">Totaal <span class="bh-raster-totsub">· weekend</span></th>
          <td class="bh-raster-wkn"></td>
          <?php foreach ($weken as $wn => $w): $d = $dekking[$wn]; ?>
          <td class="bh-raster-tot bh-num<?= $d['stand'] === 'slecht' ? ' bh-raster-tot-laag' : '' ?>"
              title="Week <?= (int)$wn ?>: <?= $d['aantal'] ?> beschikbaar, <?= $d['weekend'] ?> dekken het weekend<?= $dek_norm > 0 ? ' (norm ' . $dek_norm . ')' : '' ?>">
            <?= $d['aantal'] ?><span class="bh-raster-totwk"> / <?= $d['weekend'] ?></span>
          </td>
          <?php endforeach; ?>
        </tr>
      </tfoot>
    </table>
  </div>

  <p class="bh-raster-voet">
    Onderaan per week: hoeveel mensen er beschikbaar zijn en hoeveel daarvan het <strong>weekend</strong>
    dekken. Rood betekent onder de norm van <?= $dek_norm > 0 ? $dek_norm . ' mensen' : 'de posten' ?>.
  </p>

  <?= bh_versheid(bh_laatst(
        "SELECT MAX(GREATEST(COALESCE(b.definitief_op,'1000-01-01'), COALESCE(b.ingevuld_op,'1000-01-01')))
         FROM beschikbaarheid b JOIN sollicitaties s ON s.id = b.sollicitatie_id
         WHERE s.seizoen_id = ?", [$sid]), 'Beschikbaarheid') ?>
</section>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

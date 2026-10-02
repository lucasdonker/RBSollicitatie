<?php
/**
 * Nabespreking en bellijst — eerst besluiten met z'n allen, dan bellen, dan pas mailen.
 *
 * Twee tabbladen omdat het twee momenten zijn: aan tafel het besluit nemen, en daarna
 * de mensen bellen. De scores zijn een advies, het gesprek bepaalt (ontwerpfilosofie §3).
 *
 * Dit is bestuurswerk: taak Nabespreken, en voor de bellijst taak Bellen (telefoonnummers, fase
 * doorvoeren; includes/modules/selectiedag.php).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

$_bel_acties = ['gebeld', 'bel_los', 'gebeld_wissen', 'doorvoeren_een', 'gebelde_doorvoeren'];
$_is_bel = ($_GET['tab'] ?? '') === 'bellijst'
        || ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)($_POST['actie'] ?? ''), $_bel_acties, true));
vereis_recht('selectiedag', $_is_bel ? 'bellen' : 'nabespreken');

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;
$ik  = $_SESSION['bhd_naam'] ?? 'onbekend';

/** Na doorvoeren: zeggen wat er gebeurde, als melding op de herladen pagina (fase + bericht). */
function nb_doorvoer_melding(bool $ok, ?array $res): void {
    if (!$ok) { flash('fout', 'Doorvoeren lukte niet' . (!empty($res['fout']) ? ': ' . $res['fout'] : '') . '.'); return; }
    $bericht = function_exists('fase_melding_tekst') ? fase_melding_tekst($res ?? []) : '';
    flash('succes', 'Doorgevoerd: ' . fase_label((string)($res['nieuw'] ?? '')) . '. ' . ($bericht !== '' ? $bericht : 'Er is geen bericht verstuurd.'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    csrf_check();
    $actie = (string)($_POST['actie'] ?? '');

    // Een verouderd tabblad op een tweede tablet mag niet over het werk van een collega heen:
    // weigeren als iemand anders deze persoon aan het bellen is, of als hij niet (meer) op de
    // selectiedag-lijst staat (bijv. al doorgevoerd).
    if (in_array($actie, ['besluit', 'gebeld', 'gebeld_wissen', 'doorvoeren_een'], true)) {
        $_sol  = (int)($_POST['sol_id'] ?? 0);
        $_rij  = selectie_besluit_van($sid, $_sol) ?? [];
        $_fout = !selectie_is_kandidaat($sid, $_sol)
            ? 'Deze persoon staat niet meer op de lijst van de selectiedag. Ververs de pagina.'
            : (evenement_claim_actief($_rij['bel_door'] ?? null, $_rij['bel_sinds'] ?? null, (int)($_SESSION['bhd_id'] ?? 0))
                ? evenement_claim_naam((int)$_rij['bel_door']) . ' is deze persoon aan het bellen. Ververs de pagina.'
                : '');
        if ($_fout !== '') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'fout' => $_fout]);
            exit;
        }
    }

    if ($actie === 'besluit') {
        header('Content-Type: application/json');
        selectie_besluit_zetten($sid, (int)$_POST['sol_id'], (string)($_POST['besluit'] ?? ''),
                                (string)($_POST['notitie'] ?? ''), $ik);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($actie === 'gebeld') {
        header('Content-Type: application/json');
        $sol = (int)$_POST['sol_id'];
        $nieuw = (string)($_POST['nieuw_besluit'] ?? '');
        // Bij "door" meteen vastleggen of iemand op het trainingsweekend komt; dan krijgt de
        // sollicitant die vraag niet ook nog in het portaal.
        $tw = (string)($_POST['tw_bevestiging'] ?? '');
        if (in_array($tw, ['komt', 'komt_niet'], true)) {
            if (!evenement_bevestigen('tw', $sid, $sol, $tw, (string)($_POST['tw_reden'] ?? ''), 'bellijst')) {
                echo json_encode(['ok' => false, 'fout' => 'Vul de reden in als iemand niet op het trainingsweekend kan komen.']);
                exit;
            }
        }
        $res = selectie_gebeld_zetten($sid, $sol, (string)($_POST['gebeld_notitie'] ?? ''), $ik,
                                      !empty($_POST['meteen_doorvoeren']), in_array($nieuw, ['door', 'niet'], true) ? $nieuw : null);
        if (!empty($_POST['meteen_doorvoeren'])) nb_doorvoer_melding((bool)$res['doorgevoerd'], $res['fase_res']);
        echo json_encode(['ok' => true, 'gebeld' => true, 'doorgevoerd' => $res['doorgevoerd']]);
        exit;
    }
    if ($actie === 'bel_los') {
        header('Content-Type: application/json');
        evenement_claim_los('selectie_besluit', 'bel', $sid, (int)$_POST['sol_id'], (int)($_SESSION['bhd_id'] ?? 0));
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($actie === 'gebeld_wissen') {
        header('Content-Type: application/json');
        selectie_gebeld_wissen($sid, (int)$_POST['sol_id']);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($actie === 'doorvoeren_een') {
        header('Content-Type: application/json');
        $_res = null;
        $_ok  = selectie_besluit_doorvoeren_een($sid, (int)$_POST['sol_id'], $_res);
        nb_doorvoer_melding($_ok, $_res);
        echo json_encode(['ok' => $_ok]);
        exit;
    }
    // Alles in één keer doorvoeren (actie 'doorvoeren') is bewust weg: dan gaat er bericht
    // uit vóórdat er gebeld is. Doorvoeren gaat via de bellijst, per persoon of per gebelde.
    if ($actie === 'gebelde_doorvoeren') {
        $n = selectie_gebelde_doorvoeren($sid);
        flash('succes', $n . ' gebelde ' . ($n === 1 ? 'sollicitant' : 'sollicitanten') . ' doorgevoerd naar de nieuwe fase. Wat er aan berichten uitging, staat in het berichtenlog.');
        redirect(APP_URL . '/admin/selectie_nabespreking.php?tab=bellijst');
    }
}

$criteria   = selectie_criteria();
$kandidaten = $sid ? selectie_kandidaten($sid) : [];
$groepen    = $sid ? selectie_groepen($sid) : [];
$besluiten  = $sid ? selectie_besluiten($sid) : [];
// Eén query voor alle scores en één voor alle observaties (niet per kaart).
$scores_alle = $sid ? selectie_scores_alle($sid) : [];
$obs_alle    = $sid ? selectie_observaties_alle($sid) : [];
$lgf_alle    = $sid ? lg_feedback_alle($sid) : [];   // feedback van lifeguards, ook één query

$groep_naam = [];
foreach ($groepen as $g) $groep_naam[$g['id']] = $g['naam'] ?: 'Groep ' . $g['nummer'];

$tab = (string)($_GET['tab'] ?? 'nabespreken');
// Filters en sortering (includes/beheer/nabespreking.php). 'filter' is de oude naam van het besluitfilter.
$nb_f = [
    'q'        => (string)($_GET['q'] ?? ''),
    'besluit'  => (string)($_GET['filter'] ?? ''),
    'signaal'  => (string)($_GET['signaal'] ?? ''),
    'advies'   => (string)($_GET['advies'] ?? ''),
    'groep'    => (string)($_GET['groep'] ?? ''),
    'aanwezig' => (string)($_GET['aanwezig'] ?? ''),
    'sort'     => (string)($_GET['sort'] ?? 'naam'),
];
if ($nb_f['besluit'] === 'alle') $nb_f['besluit'] = '';
if (!isset(bh_nb_sorteringen()[$nb_f['sort']])) $nb_f['sort'] = 'naam';

$tel = ['door' => 0, 'twijfel' => 0, 'niet' => 0, 'open' => 0];
foreach ($kandidaten as $k) {
    $b = $besluiten[(int)$k['sol_id']]['besluit'] ?? '';
    $tel[$b ?: 'open']++;
}

// Eén keer ongefilterd (voor de tellers en de aantallen in de keuzelijsten), één keer zoals gevraagd.
$nb_alle   = $sid ? bh_nb_lijst($kandidaten, $besluiten, $scores_alle, $obs_alle, $lgf_alle, $criteria, []) : [];
$nb_tel    = bh_nb_tellers($nb_alle, $besluiten);
$zichtbaar = $sid ? bh_nb_lijst($kandidaten, $besluiten, $scores_alle, $obs_alle, $lgf_alle, $criteria, $nb_f) : [];
$nb_sig_tel = [];
foreach ($nb_alle as $_k) foreach (array_unique(array_column($_k['nb']['signalen'], 'soort')) as $_s) $nb_sig_tel[$_s] = ($nb_sig_tel[$_s] ?? 0) + 1;
$nb_rand_tel = count(array_filter($nb_alle, fn($k) => $k['nb']['rand']));

// Bellijst: wie een besluit heeft maar nog niet is doorgevoerd — ook twijfel: die bel je
// ook, en na het gesprek wordt het door of niet.
$bel_kandidaten = array_values(array_filter($kandidaten, function ($k) use ($besluiten) {
    $b = $besluiten[(int)$k['sol_id']] ?? null;
    return $b && in_array($b['besluit'] ?? '', ['door', 'twijfel', 'niet'], true) && empty($b['doorgevoerd_op']);
}));
$tel_gebeld = $tel_ongebeld = 0;
foreach ($bel_kandidaten as $k) {
    if (!empty($besluiten[(int)$k['sol_id']]['gebeld_op'])) $tel_gebeld++; else $tel_ongebeld++;
}

$pagina_titel = $tab === 'bellijst' ? 'Bellijst' : 'Nabespreking';
$actieve_nav  = 'selectiedag';
require __DIR__ . '/layout_top.php';
?>

<?php
  $subnav_actief = $tab === 'bellijst' ? 'bellijst' : 'nabespreking';
  $subnav_datum  = $seizoen && !empty($seizoen['datum_selectiedag']) ? datum_nl($seizoen['datum_selectiedag']) : null;
  require __DIR__ . '/_selectie_subnav.php';
?>

<?php if (!$sid || !$kandidaten): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php
      $leeg_titel = $sid ? 'Nog geen sollicitanten' : 'Geen actief seizoen';
      $leeg_tekst = $sid ? 'Zodra er sollicitanten op de selectiedag zijn geweest, bespreek je ze hier na.' : '';
      $leeg_icoon = 'mensen';
      require __DIR__ . '/_leeg.php';
    ?>
  </div>

<?php elseif ($tab === 'bellijst'): ?>
  <?php
    $bel_sid       = $sid;
    $bel_lijst     = $bel_kandidaten;
    $bel_besluiten = $besluiten;
    $bel_gebeld    = $tel_gebeld;
    $bel_ongebeld  = $tel_ongebeld;
    $bel_scores    = $scores_alle;
    $bel_obs       = $obs_alle;
    $bel_lgf       = $lgf_alle;
    $bel_nb        = array_column($nb_alle, 'nb', 'sol_id');
    $bel_groepen   = $groep_naam;
    require __DIR__ . '/_bellijst.php';
  ?>

<?php else: ?>

  <?php $_hier = APP_URL . '/admin/selectie_nabespreking.php'; ?>
  <section class="ds-kaart bh-dekkop bh-nb-stand-kaart">
    <div class="bh-dekkop-zin">
      <?= bh_stoplicht($nb_tel['open'] > 0 ? 'krap' : 'goed',
                       $nb_tel['open'] . ' van ' . $nb_tel['totaal'] . ' nog zonder besluit') ?>
      <?php if ($nb_tel['rand_open'] > 0): ?>
        <a class="bh-nb-snel" href="<?= h($_hier . '?' . http_build_query(['signaal' => 'rand', 'filter' => 'open', 'sort' => 'rand'])) ?>">
          <?= $nb_tel['rand_open'] ?> <?= $nb_tel['rand_open'] === 1 ? 'randgeval' : 'randgevallen' ?> om eerst te bespreken</a>
      <?php endif; ?>
    </div>
    <p class="bh-dekkop-uitleg">
      Advies op cijfer: <?= $nb_tel['advies']['door'] ?> door · <?= $nb_tel['advies']['twijfel'] ?> twijfel ·
      <?= $nb_tel['advies']['niet'] ?> niet<?= $nb_tel['advies']['geen'] ? ' · ' . $nb_tel['advies']['geen'] . ' zonder cijfer' : '' ?>.
      Het cijfer is een advies; jullie gesprek bepaalt.<?php if (mag('selectiedag', 'bellen')): ?> Daarna bel je iedereen via de <a href="?tab=bellijst">bellijst</a>.<?php endif; ?>
    </p>
  </section>

  <?php
    $_besl = ['' => 'Besluit: iedereen (' . count($kandidaten) . ')', 'open' => 'Besluit: nog open (' . $tel['open'] . ')'];
    foreach (selectie_besluit_labels() as $_bk => $_bd) $_besl[$_bk] = 'Besluit: ' . mb_strtolower($_bd[0]) . ' (' . $tel[$_bk] . ')';
    $_sig = ['' => 'Signalen: alle', 'rand' => 'Alleen randgevallen (' . $nb_rand_tel . ')'];
    foreach (bh_nb_signaal_soorten() as $_sk => $_sl) if (!empty($nb_sig_tel[$_sk])) $_sig[$_sk] = $_sl . ' (' . $nb_sig_tel[$_sk] . ')';
    $_adv = ['' => 'Advies: alle', 'door' => 'Advies: door (' . $nb_tel['advies']['door'] . ')',
             'twijfel' => 'Advies: twijfel (' . $nb_tel['advies']['twijfel'] . ')', 'niet' => 'Advies: niet (' . $nb_tel['advies']['niet'] . ')',
             'geen' => 'Advies: nog geen cijfer (' . $nb_tel['advies']['geen'] . ')'];
    $_grp = ['' => 'Groep: alle'];
    foreach ($groep_naam as $_gid => $_gn) $_grp[(string)$_gid] = 'Groep: ' . $_gn;
    $_grp['geen'] = 'Groep: zonder groep';
    $filter_velden = [
        ['soort' => 'zoek',    'naam' => 'q',        'label' => 'Zoek op naam', 'waarde' => $nb_f['q']],
        ['soort' => 'keuze',   'naam' => 'signaal',  'label' => 'Signalen', 'waarde' => $nb_f['signaal'], 'opties' => $_sig],
        ['soort' => 'keuze',   'naam' => 'filter',   'label' => 'Besluit', 'waarde' => $nb_f['besluit'], 'opties' => $_besl],
        ['soort' => 'sorteer', 'naam' => 'sort',     'label' => 'Sorteren', 'waarde' => $nb_f['sort'], 'opties' => bh_nb_sorteringen()],
        ['soort' => 'keuze',   'naam' => 'advies',   'label' => 'Advies', 'waarde' => $nb_f['advies'], 'opties' => $_adv, 'meer' => true],
        ['soort' => 'keuze',   'naam' => 'groep',    'label' => 'Groep', 'waarde' => $nb_f['groep'], 'opties' => $_grp, 'meer' => true],
        ['soort' => 'keuze',   'naam' => 'aanwezig', 'label' => 'Aanwezig', 'waarde' => $nb_f['aanwezig'], 'meer' => true,
         'opties' => ['' => 'Aanwezig: iedereen', 'ja' => 'Was aanwezig', 'nee' => 'Was niet aanwezig']],
    ];
    $_gefilterd = array_filter(array_diff_key($nb_f, ['sort' => 1])) || $nb_f['sort'] !== 'naam';
    $filter_wissen  = $_gefilterd ? $_hier : null;
    require __DIR__ . '/_filterbalk.php';
  ?>

  <?php if ($zichtbaar): ?>
  <div class="bh-lijstkop">
    <span class="bh-meta"><?= count($zichtbaar) ?> van <?= count($kandidaten) ?> getoond</span>
    <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" data-klap-alles="details.bh-nb-meer" aria-pressed="false">Alles uitklappen</button>
  </div>
  <?php endif; ?>

  <?php if (!$zichtbaar): ?>
    <div class="ds-kaart ds-kaart-p">
      <?php
        $leeg_titel = 'Niemand gevonden';
        $leeg_tekst = 'Pas de zoekterm of de filters aan.';
        $leeg_icoon = 'zoek';
        require __DIR__ . '/_leeg.php';
      ?>
    </div>
  <?php endif; ?>

  <?php
    // Na feedback vastleggen terug naar dezelfde lijst (filters in de URL-namen van de filterbalk).
    $_url_f = ['q' => $nb_f['q'], 'filter' => $nb_f['besluit'], 'signaal' => $nb_f['signaal'], 'advies' => $nb_f['advies'],
               'groep' => $nb_f['groep'], 'aanwezig' => $nb_f['aanwezig'], 'sort' => $nb_f['sort'] !== 'naam' ? $nb_f['sort'] : ''];
    $_lgf_terug = $_hier . (array_filter($_url_f) ? '?' . http_build_query(array_filter($_url_f)) : '');
  ?>
  <div class="bh-nb-lijst" data-nb data-endpoint="<?= h($_hier) ?>" data-csrf="<?= h(csrf_token()) ?>">
  <?php foreach ($zichtbaar as $k):
    $_id  = (int)$k['sol_id'];
    $_nb  = $k['nb'];
    $_obs = $obs_alle[$_id] ?? [];
    $_lgf = $lgf_alle[$_id] ?? [];
    $_b   = $besluiten[$_id]['besluit'] ?? '';
    $_not = $besluiten[$_id]['notitie'] ?? '';
    $_kleur = ['door' => 'groen', 'twijfel' => 'oranje', 'niet' => 'rood'][$_b] ?? 'grijs';
    $_sam = array_filter([
        count($_obs) ? count($_obs) . ' ' . (count($_obs) === 1 ? 'observatie' : 'observaties') : 'geen observaties',
        $_nb['pos'] ? $_nb['pos'] . ' positief' : '',
        $_nb['aandacht'] ? $_nb['aandacht'] . ' aandacht' : '',
        $_lgf ? count($_lgf) . ' feedback van strandwachten' : '',
    ]);
  ?>
  <section class="bh-nb-kaart ds-fase-<?= h($_kleur) ?><?= $_b ? ' besloten' : '' ?>" id="nb-<?= $_id ?>" data-sol="<?= $_id ?>">
    <header class="bh-nb-kop">
      <?= bh_avatar($k, 46, ['dim' => empty($k['aanwezig']), 'vergroot' => true]) ?>
      <span class="bh-nb-wie">
        <a class="bh-nb-naam" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $_id ?>&amp;tab=selectiedag"><?= h((string)$k['naam']) ?></a>
        <?= bh_meta([
            $k['leeftijd'] ? $k['leeftijd'] . ' jaar' : '',
            $k['groep_id'] ? ($groep_naam[$k['groep_id']] ?? '') : '',
        ]) ?>
      </span>
      <?php if ($_nb['advies']): ?>
        <?= bh_badge('Advies: ' . mb_strtolower($_nb['advies']['label']), $_nb['advies']['kleur'], ['titel' => $_nb['advies']['reden']]) ?>
      <?php endif; ?>
      <span class="bh-nb-score">
        <span class="bh-num"><?= $_nb['gem'] !== null ? h(cijfer_nl((float)$_nb['gem'])) : '–' ?></span>
        <span class="bh-meta"><?= $_nb['gem'] !== null ? 'gemiddeld' : 'geen cijfer' ?></span>
      </span>
    </header>

    <?php if ($_nb['signalen']): ?>
      <div class="bh-nb-signalen" aria-label="Signalen">
        <?php foreach ($_nb['signalen'] as $_s) echo bh_chip($_s['tekst'], bh_nb_signaal_kleur($_s['soort'])); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($k['bijzonderheden'])): ?>
      <div class="ds-melding ds-melding-warn">
        <?= icoon('waarschuwing', 16) ?>
        <?php if (mag('blk', 'lezen')): ?>
          <span><strong>Bijzonderheid:</strong> <?= nl2br(h((string)$k['bijzonderheden'])) ?></span>
        <?php else: ?>
          <span><strong>Let op:</strong> er is een bijzonderheid bekend; die staat in het formulier.</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <details class="bh-klap bh-nb-meer" data-klap-id="nb-<?= $_id ?>">
      <summary>
        <span class="bh-klap-titel">Observaties, cijfers en feedback</span>
        <span class="bh-klap-sam"><?= h(implode(' · ', $_sam)) ?></span>
      </summary>
      <div class="bh-klap-inhoud bh-nb-kolommen">
        <div>
          <h3>Observaties</h3>
          <?php if (!$_obs): ?>
            <p class="bh-dossier-tekst zacht">Niemand heeft iets genoteerd.</p>
          <?php else: $obs_lijst = $_obs; $obs_eigen = null; require __DIR__ . '/_observaties.php'; endif; ?>
        </div>

        <div>
          <h3>Cijfers per onderdeel</h3>
          <?php if (empty($scores_alle[$_id])): ?>
            <p class="bh-dossier-tekst zacht">Niet beoordeeld.</p>
          <?php else: $scs_scores = $scores_alle[$_id]; require __DIR__ . '/_sd_scores.php'; endif; ?>
        </div>

        <?php
          // Wat strandwachten het bestuur over deze sollicitant vertelden; hier vastleggen.
          $lgf_sol_id = $_id; $lgf_seizoen_id = $sid; $lgf_moment = 'selectiedag'; $lgf_mag_bewerken = true;
          $lgf_terug = $_lgf_terug . '#nb-' . $_id;
          $lgf_rijen = $_lgf; $lgf_kaart = false; $lgf_kop = 'h3';
          require __DIR__ . '/_lg_feedback.php';
        ?>
      </div>
    </details>

    <footer class="bh-nb-besluit">
      <div class="bh-nb-knoppen" role="group" aria-label="Besluit over <?= h((string)$k['naam']) ?>">
        <?php foreach (selectie_besluit_labels() as $_bk => $_bd): ?>
          <button type="button" class="bh-besluitknop <?= $_bk ?><?= $_b === $_bk ? ' aan' : '' ?>"
                  aria-pressed="<?= $_b === $_bk ? 'true' : 'false' ?>" data-besluit="<?= $_bk ?>"><?= h($_bd[0]) ?></button>
        <?php endforeach; ?>
      </div>
      <input type="text" class="ds-input bh-nb-notitie" data-notitie
             aria-label="Reden of aantekening bij <?= h((string)$k['naam']) ?>"
             value="<?= h((string)$_not) ?>" placeholder="Korte reden of aantekening…">
      <span class="bh-nb-stand" data-stand aria-live="polite"></span>
    </footer>
  </section>
  <?php endforeach; ?>
  </div>

  <?php if ($tel['door'] + $tel['niet'] + $tel['twijfel'] > 0): ?>
  <div class="bh-nb-afsluiten">
    <div>
      <strong><?= $tel['door'] + $tel['niet'] + $tel['twijfel'] ?> sollicitanten hebben een besluit.</strong>
      <span class="bh-meta">
        Bellen doe je op de bellijst, ook de twijfelgevallen: daar wordt het na het gesprek door of niet.
        Je voert per persoon door, zodat er pas bericht uitgaat nadat je hebt gebeld.
      </span>
    </div>
    <?php if (mag('selectiedag', 'bellen')): ?><a class="ds-btn ds-btn-primair" href="?tab=bellijst">Naar de bellijst</a><?php endif; ?>
  </div>
  <?php endif; ?>

<?php endif; ?>

<?php if ($tab !== 'bellijst'): ?>
<script src="<?= APP_URL ?>/assets/js/nabespreking.js?v=<?= @filemtime(__DIR__ . '/../assets/js/nabespreking.js') ?>" defer></script>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

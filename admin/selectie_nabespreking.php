<?php
/**
 * Nabespreking en bellijst — eerst besluiten met z'n allen, dan bellen, dan pas mailen.
 *
 * Twee tabbladen omdat het twee momenten zijn: aan tafel het besluit nemen, en daarna
 * de mensen bellen. De scores zijn een advies, het gesprek bepaalt (ontwerpfilosofie §3).
 *
 * Dit is bestuurswerk: een begeleider komt hier niet.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

vereis_recht('selectiedag', 'bewerken');

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

$filter = (string)($_GET['filter'] ?? 'alle');
$tab    = (string)($_GET['tab'] ?? 'nabespreken');

$tel = ['door' => 0, 'twijfel' => 0, 'niet' => 0, 'open' => 0];
foreach ($kandidaten as $k) {
    $b = $besluiten[(int)$k['sol_id']]['besluit'] ?? '';
    $tel[$b ?: 'open']++;
}

$zichtbaar = array_values(array_filter($kandidaten, function ($k) use ($filter, $besluiten) {
    $b = $besluiten[(int)$k['sol_id']]['besluit'] ?? '';
    return match ($filter) {
        'door' => $b === 'door', 'twijfel' => $b === 'twijfel',
        'niet' => $b === 'niet', 'open' => $b === '', default => true,
    };
}));

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
      $leeg_titel = $sid ? 'Nog geen kandidaten' : 'Geen actief seizoen';
      $leeg_tekst = $sid ? 'Zodra er kandidaten op de selectiedag zijn geweest, bespreek je ze hier na.' : '';
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
    require __DIR__ . '/_bellijst.php';
  ?>

<?php else: ?>

  <div class="bh-nb-uitleg">
    <?= icoon('info', 16) ?>
    <span>
      Alles per kandidaat bij elkaar. De scores zijn een advies; jullie gesprek bepaalt het besluit.
      Als iedereen een besluit heeft, ga je naar de <a href="?tab=bellijst">bellijst</a> om te bellen
      en per persoon door te voeren.
    </span>
  </div>

  <div class="bh-groepkeuze bh-nb-filter">
    <?php
      $_f = [
        'alle'    => ['Iedereen', count($kandidaten), 'grijs'],
        'open'    => ['Nog open', $tel['open'], 'grijs'],
        'door'    => ['Door', $tel['door'], 'groen'],
        'twijfel' => ['Twijfel', $tel['twijfel'], 'oranje'],
        'niet'    => ['Niet', $tel['niet'], 'rood'],
      ];
      foreach ($_f as $_k => $_d):
        if ($_k !== 'alle' && $_d[1] === 0) continue;   // een filter zonder mensen is ruis
    ?>
      <a class="bh-groepchip ds-fase-<?= h($_d[2]) ?><?= $filter === $_k ? ' actief' : '' ?>"
         href="?filter=<?= h($_k) ?>"><?= h($_d[0]) ?> <span><?= (int)$_d[1] ?></span></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$zichtbaar): ?>
    <div class="ds-kaart ds-kaart-p">
      <?php
        $leeg_titel = 'Niemand in deze selectie';
        $leeg_tekst = 'Kies een ander filter om de rest te zien.';
        require __DIR__ . '/_leeg.php';
      ?>
    </div>
  <?php endif; ?>

  <?php foreach ($zichtbaar as $k):
    $_id  = (int)$k['sol_id'];
    $_sc  = $scores_alle[$_id] ?? [];
    $_obs = $obs_alle[$_id] ?? [];
    $_b   = $besluiten[$_id]['besluit'] ?? '';
    $_not = $besluiten[$_id]['notitie'] ?? '';
    $_gem = selectie_gemiddelde($_sc);
    $_kleur = ['door' => 'groen', 'twijfel' => 'oranje', 'niet' => 'rood'][$_b] ?? 'grijs';
  ?>
  <section class="bh-nb-kaart ds-fase-<?= h($_kleur) ?><?= $_b ? ' besloten' : '' ?>" id="nb-<?= $_id ?>">
    <header class="bh-nb-kop">
      <?= bh_avatar($k, 46, ['dim' => empty($k['aanwezig']), 'vergroot' => true]) ?>
      <span class="bh-nb-wie">
        <a class="bh-nb-naam" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $_id ?>&amp;tab=selectiedag"><?= h((string)$k['naam']) ?></a>
        <?= bh_meta([
            $k['leeftijd'] ? $k['leeftijd'] . ' jaar' : '',
            $k['groep_id'] ? ($groep_naam[$k['groep_id']] ?? '') : '',
            empty($k['aanwezig']) ? 'was niet aanwezig' : '',
        ]) ?>
      </span>
      <?php if ($_gem !== null): ?>
        <span class="bh-nb-score">
          <span class="bh-num"><?= h(number_format($_gem, 1, ',', '')) ?></span>
          <span class="bh-meta">gemiddeld</span>
        </span>
      <?php endif; ?>
    </header>

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

    <div class="bh-nb-kolommen">
      <div>
        <h3>Observaties</h3>
        <?php if (!$_obs): ?>
          <p class="bh-dossier-tekst zacht">Niemand heeft iets genoteerd.</p>
        <?php else: $obs_lijst = $_obs; $obs_eigen = null; require __DIR__ . '/_observaties.php'; endif; ?>
      </div>

      <div>
        <h3>Scores</h3>
        <?php if (!$_sc): ?>
          <p class="bh-dossier-tekst zacht">Niet beoordeeld.</p>
        <?php else: ?>
          <div class="bh-scorebalken bh-scorebalken-los">
            <?php foreach ($_sc as $_test => $_crits):
              $_w = [];
              foreach ($_crits as $_d) if (!empty($_d['gem'])) $_w[] = (float)$_d['gem'];
              if (!$_w) continue;
              $_tg = round(array_sum($_w) / count($_w), 1);
            ?>
              <div class="bh-scorebalk">
                <span class="bh-scorebalk-naam"><?= h(ucfirst((string)$_test)) ?></span>
                <span class="bh-scorebalk-spoor">
                  <span class="bh-scorebalk-vul" style="--w: <?= (int)round($_tg / 5 * 100) ?>%"></span>
                </span>
                <span class="bh-scorebalk-getal bh-num"><?= h(number_format($_tg, 1, ',', '')) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php
        // Wat lifeguards het bestuur over deze kandidaat vertelden; hier vastleggen.
        $lgf_sol_id = $_id; $lgf_seizoen_id = $sid; $lgf_moment = 'selectiedag'; $lgf_mag_bewerken = true;
        $lgf_terug = APP_URL . '/admin/selectie_nabespreking.php?filter=' . rawurlencode($filter);
        $lgf_rijen = $lgf_alle[$_id] ?? []; $lgf_kaart = false; $lgf_kop = 'h3';
        require __DIR__ . '/_lg_feedback.php';
      ?>
    </div>

    <footer class="bh-nb-besluit">
      <div class="bh-nb-knoppen" id="besluit-<?= $_id ?>">
        <?php foreach (['door' => 'Door', 'twijfel' => 'Twijfel', 'niet' => 'Niet'] as $_bk => $_bl): ?>
          <button type="button" class="bh-besluitknop <?= $_bk ?><?= $_b === $_bk ? ' aan' : '' ?>"
                  aria-pressed="<?= $_b === $_bk ? 'true' : 'false' ?>"
                  onclick="besluitZet(<?= $_id ?>, '<?= $_bk ?>')"><?= h($_bl) ?></button>
        <?php endforeach; ?>
      </div>
      <input type="text" class="ds-input bh-nb-notitie" id="notitie-<?= $_id ?>"
             value="<?= h((string)$_not) ?>" placeholder="Korte reden of aantekening…"
             onchange="besluitZet(<?= $_id ?>, null)">
      <span class="bh-nb-stand" id="stand-<?= $_id ?>" aria-live="polite"></span>
    </footer>
  </section>
  <?php endforeach; ?>

  <?php if ($tel['door'] + $tel['niet'] + $tel['twijfel'] > 0): ?>
  <div class="bh-nb-afsluiten">
    <div>
      <strong><?= $tel['door'] + $tel['niet'] + $tel['twijfel'] ?> kandidaten hebben een besluit.</strong>
      <span class="bh-meta">
        Bellen doe je op de bellijst, ook de twijfelgevallen: daar wordt het na het gesprek door of niet.
        Je voert per persoon door, zodat er pas bericht uitgaat nadat je hebt gebeld.
      </span>
    </div>
    <a class="ds-btn ds-btn-primair" href="?tab=bellijst">Naar de bellijst</a>
  </div>
  <?php endif; ?>

<?php endif; ?>

<script>
// window.CSRF, geen const: layout_bottom.php declareert CSRF ook, en een tweede const
// brak dat hele script af (SyntaxError) — thema, meldingen en fase wijzigen deden niets.
window.CSRF = window.CSRF || '<?= csrf_token() ?>';

/* Besluit en notitie gaan samen de deur uit: de notitie hoort bij het besluit, en
   los opslaan zou een notitie zonder besluit kunnen achterlaten.
   Nogmaals klikken op het actieve besluit wist het (besluit '' — de server verwijdert
   dan de rij, notitie inbegrepen). */
function besluitZet(solId, besluit) {
  const rij = document.getElementById('besluit-' + solId);
  const aan = rij.querySelector('.aan');
  const huidig = !aan ? '' : aan.classList.contains('door') ? 'door'
               : (aan.classList.contains('twijfel') ? 'twijfel' : 'niet');
  if (besluit === null) {
    if (!huidig) {                          // nog geen besluit: de notitie gaat mee zodra je er een kiest
      const st = document.getElementById('stand-' + solId);
      if (st) st.textContent = 'nog niet opgeslagen: kies een besluit, dan gaat de aantekening mee';
      return;
    }
    besluit = huidig;
  } else if (besluit === huidig) {
    besluit = '';                           // zelfde knop nogmaals: wissen
  }
  const notitie = document.getElementById('notitie-' + solId).value;
  const stand = document.getElementById('stand-' + solId);
  if (stand) stand.textContent = 'opslaan…';

  fetch(location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ actie: 'besluit', sol_id: solId, besluit: besluit, notitie: notitie, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(d => {
    if (!d.ok) throw new Error(d.fout || '');
    rij.querySelectorAll('.bh-besluitknop').forEach(function (b) {
      const aan = besluit !== '' && b.classList.contains(besluit);
      b.classList.toggle('aan', aan);
      b.setAttribute('aria-pressed', aan ? 'true' : 'false');
    });
    const kaart = document.getElementById('nb-' + solId);
    kaart.className = kaart.className.replace(/ds-fase-\w+/, 'ds-fase-' +
      (besluit === 'door' ? 'groen' : besluit === 'twijfel' ? 'oranje' : besluit === 'niet' ? 'rood' : 'grijs'));
    kaart.classList.toggle('besloten', besluit !== '');
    if (besluit === '') document.getElementById('notitie-' + solId).value = '';
    if (stand) stand.textContent = besluit === '' ? 'besluit gewist' : 'opgeslagen';
  })
  .catch((e) => { if (stand) stand.textContent = 'niet opgeslagen'; bhToast((e && e.message) || 'Opslaan mislukt.', 'fout'); });
}
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>

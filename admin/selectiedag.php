<?php
/**
 * Beoordelen — op de telefoon van de begeleider, buiten, in de zon.
 *
 * Dit scherm wordt met één duim bediend, met natte handen (ontwerpfilosofie §6).
 * Daarom: één kandidaat tegelijk, cijfers als knoppen van 48 pixels in plaats van
 * sterretjes, en tikken ís opslaan — een vinkje "opgeslagen" verschijnt naast het
 * criterium. Geen bewaarknop, geen verplichte volgorde: weglopen mag altijd.
 *
 * Links (op een groot scherm) de groep met wie er klaar is, zodat je weet waar je
 * bent. Op een telefoon staat die lijst boven de kandidaat.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

vereis_recht('selectiedag', 'lezen');

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;
$ik  = $_SESSION['bhd_naam'] ?? 'onbekend';

// Zelfde niveaus als het trainingsweekend (admin/trainingsweekend.php):
//   beoordelen — cijfers geven/wissen en observaties plaatsen, en je eígen observaties
//                verwijderen. Dat is het werk van de begeleider op de dag, dus 'lezen'.
//   bewerken   — observaties van anderen verwijderen: bestuur.
// Scores en observaties zijn per beoordelaar ($ik): niemand overschrijft een ander.
$mag_beoordelen = mag('selectiedag', 'lezen');
$mag_alles      = mag('selectiedag', 'bewerken');
// Dataminimalisatie (AVG art. 5 lid 1c): de BLK-bijzonderheden (vaak medisch) alleen met
// BLK-recht; de begeleider ziet dat er iets is, niet wat. Het dossier alleen met dossierrecht.
$mag_blk     = mag('blk', 'lezen');
$mag_dossier = mag('sollicitanten', 'lezen');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    header('Content-Type: application/json');
    csrf_check();
    if (!$mag_beoordelen) { echo json_encode(['ok' => false, 'fout' => 'geen_rechten']); exit; }
    $actie = (string)($_POST['actie'] ?? '');
    if (in_array($actie, ['score', 'observatie'], true) && !selectie_is_kandidaat($sid, (int)($_POST['sol_id'] ?? 0))) {
        echo json_encode(['ok' => false, 'fout' => 'geen_kandidaat']); exit;
    }

    if ($actie === 'score') {
        if (!selectie_criterium_bestaat((string)($_POST['test'] ?? ''), (string)($_POST['criterium'] ?? ''))) {
            echo json_encode(['ok' => false, 'fout' => 'onbekend_criterium']); exit;
        }
        selectie_score_opslaan($sid, (int)$_POST['sol_id'], (string)$_POST['test'],
                               (string)$_POST['criterium'], (int)$_POST['score'], $ik, (int)($_SESSION['bhd_id'] ?? 0) ?: null);
        $sc = selectie_scores_van($sid, (int)$_POST['sol_id'], $ik);
        $d  = $sc[(string)$_POST['test']][(string)$_POST['criterium']] ?? ['gem' => 0, 'allen' => []];
        echo json_encode(['ok' => true, 'gem' => $d['gem'], 'aantal' => count($d['allen'])]);
        exit;
    }
    if ($actie === 'observatie') {
        $id = selectie_observatie_toevoegen($sid, (int)$_POST['sol_id'], (string)($_POST['tekst'] ?? ''),
                                            (string)($_POST['soort'] ?? 'neutraal'), $ik, (int)($_SESSION['bhd_id'] ?? 0) ?: null);
        // De nieuwe lijst gaat als HTML mee: het scherm vervangt hem zonder te herladen (assets/js/beoordelen.js).
        $html = '';
        if ($id !== null) {
            $obs_lijst = selectie_observaties_van($sid, (int)$_POST['sol_id']);
            $obs_eigen = fn($o) => $mag_alles || ($o['beoordelaar'] ?? '') === $ik;
            ob_start();
            require __DIR__ . '/_observatie_lijst.php';
            $html = (string)ob_get_clean();
        }
        echo json_encode(['ok' => $id !== null, 'id' => $id, 'html' => $html]);
        exit;
    }
    if ($actie === 'observatie_wis') {
        echo json_encode(['ok' => selectie_observatie_verwijderen((int)$_POST['id'], $ik, $mag_alles)]);
        exit;
    }
    echo json_encode(['ok' => false, 'fout' => 'onbekende actie']);
    exit;
}

$criteria   = selectie_criteria();
$groepen    = $sid ? selectie_groepen($sid) : [];
$kandidaten = $sid ? selectie_kandidaten($sid) : [];
$obs_tel    = $sid ? selectie_observatie_tellingen($sid) : [];

$groep_naam = [];
foreach ($groepen as $g) $groep_naam[$g['id']] = $g['naam'] ?: 'Groep ' . $g['nummer'];

$gekozen_groep = (string)($_GET['groep'] ?? '');
$lijst = array_values(array_filter($kandidaten, function ($k) use ($gekozen_groep) {
    if ($gekozen_groep === '')     return true;
    if ($gekozen_groep === 'geen') return !$k['groep_id'];
    return (string)$k['groep_id'] === $gekozen_groep;
}));
// Terugkerende lifeguards onderaan in een eigen blok; vorige/volgende volgt dezelfde volgorde.
$secties = evenement_beoordeel_secties($lijst);
$lijst   = evenement_secties_plat($secties);

/** Hoeveel criteria heeft déze beoordelaar al ingevuld? */
$totaal_criteria = 0;
foreach ($criteria as $t) $totaal_criteria += count($t['criteria']);
$voortgang = [];
$_alle_scores = $sid ? selectie_scores_alle($sid) : [];   // één query in plaats van één per kandidaat
foreach ($lijst as $k) {
    $n = 0;
    foreach ($_alle_scores[(int)$k['sol_id']] ?? [] as $crits) foreach ($crits as $d) if (isset($d['allen'][$ik])) $n++;
    $voortgang[(int)$k['sol_id']] = $n;
}

// Welke kandidaat staat open? Standaard de eerste die nog niet klaar is.
$huidig_id = (int)($_GET['k'] ?? 0);
if (!$huidig_id) {
    foreach ($lijst as $k) {
        if (($voortgang[(int)$k['sol_id']] ?? 0) < $totaal_criteria) { $huidig_id = (int)$k['sol_id']; break; }
    }
    if (!$huidig_id && $lijst) $huidig_id = (int)$lijst[0]['sol_id'];
}
$posities = array_map(fn($k) => (int)$k['sol_id'], $lijst);
$pos      = array_search($huidig_id, $posities, true);
$huidig   = $pos !== false ? $lijst[$pos] : null;

/** URL naar dit scherm met een andere kandidaat of groep. Met een kandidaat springt hij naar de
 *  kaart (#kandidaat): op een telefoon sta je anders na elke "Volgende" weer bovenaan. */
function bd_url(?int $k = null, ?string $groep = null): string {
    $p = [];
    $g = $groep ?? (string)($_GET['groep'] ?? '');
    if ($g !== '') $p['groep'] = $g;
    if ($k)        $p['k'] = $k;
    return APP_URL . '/admin/selectiedag.php' . ($p ? '?' . http_build_query($p) : '') . ($k ? '#kandidaat' : '');
}

$pagina_titel = 'Beoordelen';
$actieve_nav  = 'selectiedag';
require __DIR__ . '/layout_top.php';
?>

<?php
  $subnav_actief = 'beoordelen';
  $subnav_datum  = $seizoen && !empty($seizoen['datum_selectiedag']) ? datum_nl($seizoen['datum_selectiedag']) : null;
  require __DIR__ . '/_selectie_subnav.php';
?>

<?php if (!$sid || !$kandidaten): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php
      $leeg_titel = $sid ? 'Nog niemand op de selectiedag' : 'Geen actief seizoen';
      $leeg_tekst = $sid ? 'Zodra er sollicitanten zijn uitgenodigd, kun je ze hier beoordelen.'
                         : 'Maak eerst een seizoen aan.';
      $leeg_icoon = 'mensen';
      require __DIR__ . '/_leeg.php';
    ?>
  </div>
<?php else: ?>

<div class="bh-beoordeel" data-beoordeel data-endpoint="<?= APP_URL ?>/admin/selectiedag.php" data-csrf="<?= h(csrf_token()) ?>">

  <aside class="bh-beoordeel-lijst">
    <button type="button" class="bh-beoordeel-lijstknop" data-lijstknop aria-expanded="true">
      Groep en alle sollicitanten
    </button>
    <?php
      $gk_groepen = $groepen; $gk_kandidaten = $kandidaten; $gk_gekozen = $gekozen_groep;
      $gk_url = fn(string $g) => bd_url(null, $g);
      require __DIR__ . '/_groepkeuze.php';
    ?>

    <?php
      $_klaar = count(array_filter($lijst, fn($k) => ($voortgang[(int)$k['sol_id']] ?? 0) >= $totaal_criteria && $totaal_criteria > 0));
    ?>
    <div class="bh-beoordeel-stand">
      <span><?= count($lijst) ?> sollicitanten</span>
      <span class="bh-beoordeel-klaar"><?= $_klaar ?> klaar</span>
    </div>
    <?= bh_voortgang($_klaar, max(1, count($lijst)), ['label' => '']) ?>

    <?php foreach ($secties as $_sec): ?>
    <?php if ($_sec['titel']): ?>
      <h3 class="bh-kandidaatlijst-kop"><?= h($_sec['titel']) ?> <span class="bh-num"><?= count($_sec['rijen']) ?></span></h3>
      <?php if ($_sec['uitleg'] !== ''): ?><p class="bh-meta bh-kandidaatlijst-uitleg"><?= h($_sec['uitleg']) ?></p><?php endif; ?>
    <?php endif; ?>
    <ul class="bh-kandidaatlijst">
      <?php foreach ($_sec['rijen'] as $_k):
        $_id = (int)$_k['sol_id'];
        $_v  = $voortgang[$_id] ?? 0;
        $_af = empty($_k['aanwezig']);
        $_nu = $_id === $huidig_id;
      ?>
        <li>
          <a class="bh-kandidaat<?= $_nu ? ' nu' : '' ?><?= $_af ? ' afwezig' : '' ?>"
             href="<?= h(bd_url($_id)) ?>"<?= $_nu ? ' aria-current="true"' : '' ?>>
            <?= bh_avatar($_k, 38, ['dim' => $_af]) ?>
            <span class="bh-kandidaat-tekst">
              <span class="bh-kandidaat-naam">
                <?= h((string)$_k['naam']) ?>
                <?php if (!empty($_k['bijzonderheden'])): ?>
                  <span class="bh-kandidaat-letop" title="Bijzonderheid gemeld"><?= icoon('waarschuwing', 14) ?><span class="bh-vw">bijzonderheid gemeld</span></span>
                <?php endif; ?>
              </span>
              <span class="bh-kandidaat-sub">
                <?php if ($_af): ?>niet verschenen
                <?php elseif ($totaal_criteria > 0 && $_v >= $totaal_criteria): ?>klaar
                <?php elseif ($_v > 0): ?><?= $_v ?> van <?= $totaal_criteria ?> criteria
                <?php else: ?>nog niet begonnen<?php endif; ?>
                <?php $_no = $obs_tel[$_id] ?? 0; if ($_no): ?> · <?= (int)$_no ?> observaties<?php endif; ?>
              </span>
            </span>
            <?php if ($_nu): ?>
              <span class="bh-kandidaat-nu">nu</span>
            <?php elseif ($totaal_criteria > 0 && $_v >= $totaal_criteria): ?>
              <span class="bh-kandidaat-vink"><?= icoon('vink', 16) ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endforeach; ?>
  </aside>

  <?php if ($huidig): $hid = (int)$huidig['sol_id'];
    $scores = selectie_scores_van($sid, $hid, $ik);
    $observaties = selectie_observaties_van($sid, $hid);
  ?>
  <section class="bh-beoordeel-kaart" id="kandidaat" data-sol="<?= $hid ?>">
    <header class="bh-beoordeel-kop">
      <?= bh_avatar($huidig, 56, ['vergroot' => true]) ?>
      <span class="bh-beoordeel-wie">
        <span class="bh-beoordeel-naam"><?= h((string)$huidig['naam']) ?></span>
        <span class="bh-meta">
          <?= $huidig['groep_id'] ? h($groep_naam[$huidig['groep_id']] ?? '') . ' · ' : '' ?>
          <?= !empty($huidig['is_lifeguard']) ? 'terugkerende strandwacht · ' : '' ?>
          <?= (int)($voortgang[$hid] ?? 0) ?> van <?= (int)$totaal_criteria ?> criteria
        </span>
      </span>
      <?php if ($mag_dossier): ?>
        <a class="ds-btn ds-btn-secundair ds-btn-sm"
           href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $hid ?>&amp;tab=selectiedag">Dossier</a>
      <?php endif; ?>
    </header>

    <?php if (!empty($huidig['bijzonderheden'])): ?>
      <div class="ds-melding ds-melding-warn bh-beoordeel-bijz">
        <?= icoon('waarschuwing', 16) ?>
        <?php if ($mag_blk): ?>
          <span><strong>Bijzonderheid gemeld:</strong> <?= nl2br(h((string)$huidig['bijzonderheden'])) ?></span>
        <?php else: ?>
          <span><strong>Let op:</strong> er is een bijzonderheid bekend bij het bestuur. Vraag vóór de
            zwem- of sporttest bij een bestuurslid na waar je op moet letten.</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($huidig['aanwezig'])): ?>
      <div class="ds-melding ds-melding-info">
        <?= icoon('info', 16) ?>
        <span>Deze sollicitant is niet ingecheckt. Beoordelen kan, maar controleer eerst of de sollicitant er is.</span>
      </div>
    <?php endif; ?>

    <?php if ($mag_beoordelen): ?>
      <p class="bh-beoordeel-hint"><?= icoon('info', 16) ?>
        <span>Een cijfer tikken is meteen opslaan. Nog een keer tikken wist het.</span></p>
    <?php endif; ?>

    <?php foreach ($criteria as $_ts => $_test):
      // Standaard dicht, zodat je in één oogopslag beide testen én de observaties ziet. Wat je
      // openklapt blijft open bij de volgende kandidaat (inklap.js, per sessie): wie bij de
      // zwemtest staat, hoeft niet steeds opnieuw te openen.
      $_tot = count($_test['criteria']);
      $_klaar = count(array_filter($_test['criteria'], fn($c) => (int)($scores[$_ts][$c['sleutel']]['eigen'] ?? 0) > 0));
    ?>
      <details class="bh-klap bh-testblok" data-test="<?= h($_ts) ?>" data-klap-id="sd-test-<?= h($_ts) ?>">
        <summary><h3><?= h($_test['naam']) ?></h3>
          <span class="bh-meta<?= $_klaar < $_tot ? ' nog' : '' ?>" data-test-stand><?= $_klaar ?> van <?= $_tot ?> gescoord</span>
          <span class="bh-testblok-tik" aria-hidden="true">Openen</span></summary>
        <div class="bh-klap-inhoud">
        <?php foreach ($_test['criteria'] as $_crit):
          $_sc = $scores[$_ts][$_crit['sleutel']] ?? ['eigen' => 0, 'allen' => [], 'gem' => 0];
          $_eigen = (int)$_sc['eigen'];
        ?>
          <div class="bh-criterium<?= $_eigen ? ' gedaan' : '' ?>">
            <div class="bh-criterium-kop">
              <span class="bh-criterium-naam"><?= h($_crit['label']) ?></span>
              <span class="bh-criterium-stand" aria-live="polite">
                <?= $_eigen ? icoon('vink', 12) . 'opgeslagen' : 'nog niet' ?>
              </span>
            </div>
            <div class="bh-cijfers" role="group" aria-label="<?= h($_crit['label']) ?>"
                 data-test="<?= h($_ts) ?>" data-crit="<?= h($_crit['sleutel']) ?>">
              <?php for ($_s = 1; $_s <= 5; $_s++): ?>
                <button type="button" class="bh-cijfer<?= $_s === $_eigen ? ' gekozen' : '' ?>"
                        <?= $mag_beoordelen ? '' : 'disabled' ?> data-cijfer="<?= $_s ?>"
                        aria-pressed="<?= $_s === $_eigen ? 'true' : 'false' ?>"><?= $_s ?></button>
              <?php endfor; ?>
            </div>
            <span class="bh-criterium-gem">
              <?php if (count($_sc['allen']) > 1): ?>
                gemiddelde van <?= count($_sc['allen']) ?> beoordelaars: <?= h((string)$_sc['gem']) ?>
              <?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>

    <?php
      $oi_sol = $hid; $oi_naam = (string)$huidig['naam']; $oi_mag = $mag_beoordelen; $oi_titel = 'Observatie plaatsen';
      $obs_lijst = $observaties;
      $obs_eigen = fn($o) => $mag_alles || ($o['beoordelaar'] ?? '') === $ik;
      require __DIR__ . '/_observatie_invoer.php';
    ?>

    <footer class="bh-beoordeel-voet">
      <?php $_vorige = $pos > 0 ? $lijst[$pos - 1] : null; $_volgende = isset($lijst[$pos + 1]) ? $lijst[$pos + 1] : null; ?>
      <?php if ($_vorige): ?>
        <a class="ds-btn ds-btn-secundair" href="<?= h(bd_url((int)$_vorige['sol_id'])) ?>" rel="prev"><?= icoon('terug', 14) ?> Vorige</a>
      <?php else: ?>
        <span class="ds-btn ds-btn-zacht" aria-disabled="true"><?= icoon('terug', 14) ?> Vorige</span>
      <?php endif; ?>
      <?php if ($_volgende): ?>
        <a class="ds-btn ds-btn-primair bh-beoordeel-volgende" href="<?= h(bd_url((int)$_volgende['sol_id'])) ?>" rel="next">
          Volgende: <?= h((string)$_volgende['voornaam']) ?> <?= icoon('chevron', 14) ?>
        </a>
      <?php else: ?>
        <span class="bh-meta bh-beoordeel-volgende">Dit was de laatste van deze groep.</span>
      <?php endif; ?>
    </footer>
  </section>
  <?php endif; ?>
</div>

<script src="<?= APP_URL ?>/assets/js/beoordelen.js?v=<?= @filemtime(__DIR__ . '/../assets/js/beoordelen.js') ?>" defer></script>

<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>

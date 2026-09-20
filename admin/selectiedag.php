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
$mag_alles    = function_exists('huidige_gebruiker_rol_minstens') && huidige_gebruiker_rol_minstens('bestuur');
$mag_bewerken = mag('selectiedag', 'bewerken');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    header('Content-Type: application/json');
    csrf_check();
    if (!$mag_bewerken) { echo json_encode(['ok' => false, 'fout' => 'geen_rechten']); exit; }
    $actie = (string)($_POST['actie'] ?? '');

    if ($actie === 'score') {
        selectie_score_opslaan($sid, (int)$_POST['sol_id'], (string)$_POST['test'],
                               (string)$_POST['criterium'], (int)$_POST['score'], $ik);
        $sc = selectie_scores_van($sid, (int)$_POST['sol_id'], $ik);
        $d  = $sc[(string)$_POST['test']][(string)$_POST['criterium']] ?? ['gem' => 0, 'allen' => []];
        echo json_encode(['ok' => true, 'gem' => $d['gem'], 'aantal' => count($d['allen'])]);
        exit;
    }
    if ($actie === 'observatie') {
        $id = selectie_observatie_toevoegen($sid, (int)$_POST['sol_id'], (string)($_POST['tekst'] ?? ''),
                                            (string)($_POST['soort'] ?? 'neutraal'), $ik);
        echo json_encode(['ok' => $id !== null, 'id' => $id, 'door' => $ik]);
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

/** Hoeveel criteria heeft déze beoordelaar al ingevuld? */
$totaal_criteria = 0;
foreach ($criteria as $t) $totaal_criteria += count($t['criteria']);
$voortgang = [];
foreach ($lijst as $k) {
    $sc = selectie_scores_van($sid, (int)$k['sol_id'], $ik);
    $n = 0;
    foreach ($sc as $crits) foreach ($crits as $d) if (!empty($d['eigen'])) $n++;
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

/** URL naar dit scherm met een andere kandidaat of groep. */
function bd_url(?int $k = null, ?string $groep = null): string {
    $p = [];
    $g = $groep ?? (string)($_GET['groep'] ?? '');
    if ($g !== '') $p['groep'] = $g;
    if ($k)        $p['k'] = $k;
    return APP_URL . '/admin/selectiedag.php' . ($p ? '?' . http_build_query($p) : '');
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
  <div class="ds-kaart" style="padding:16px">
    <?php
      $leeg_titel = $sid ? 'Nog niemand op de selectiedag' : 'Geen actief seizoen';
      $leeg_tekst = $sid ? 'Zodra er kandidaten zijn uitgenodigd, kun je ze hier beoordelen.'
                         : 'Maak eerst een seizoen aan.';
      $leeg_icoon = 'mensen';
      require __DIR__ . '/_leeg.php';
    ?>
  </div>
<?php else: ?>

<div class="bh-beoordeel">

  <aside class="bh-beoordeel-lijst">
    <div class="bh-groepkeuze">
      <a class="bh-groepchip<?= $gekozen_groep === '' ? ' actief' : '' ?>" href="<?= h(bd_url(null, '')) ?>">
        Alle <span><?= count($kandidaten) ?></span>
      </a>
      <?php foreach ($groepen as $_g):
        $_n = count(array_filter($kandidaten, fn($k) => (string)$k['groep_id'] === (string)$_g['id']));
        if (!$_n) continue;
      ?>
        <a class="bh-groepchip<?= $gekozen_groep === (string)$_g['id'] ? ' actief' : '' ?>"
           href="<?= h(bd_url(null, (string)$_g['id'])) ?>">
          <?= h($groep_naam[$_g['id']]) ?> <span><?= $_n ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <?php
      $_klaar = count(array_filter($lijst, fn($k) => ($voortgang[(int)$k['sol_id']] ?? 0) >= $totaal_criteria && $totaal_criteria > 0));
    ?>
    <div class="bh-beoordeel-stand">
      <span><?= count($lijst) ?> kandidaten</span>
      <span class="bh-beoordeel-klaar"><?= $_klaar ?> klaar</span>
    </div>
    <?= bh_voortgang($_klaar, max(1, count($lijst)), ['label' => '']) ?>

    <ul class="bh-kandidaatlijst">
      <?php foreach ($lijst as $_k):
        $_id = (int)$_k['sol_id'];
        $_v  = $voortgang[$_id] ?? 0;
        $_af = empty($_k['aanwezig']);
        $_nu = $_id === $huidig_id;
      ?>
        <li>
          <a class="bh-kandidaat<?= $_nu ? ' nu' : '' ?><?= $_af ? ' afwezig' : '' ?>"
             href="<?= h(bd_url($_id)) ?>"<?= $_nu ? ' aria-current="true"' : '' ?>>
            <?= bh_avatar(['voornaam' => $_k['voornaam'], 'achternaam' => $_k['achternaam']], 38, ['dim' => $_af]) ?>
            <span class="bh-kandidaat-tekst">
              <span class="bh-kandidaat-naam">
                <?= h((string)$_k['naam']) ?>
                <?php if (!empty($_k['bijzonderheden'])): ?>
                  <span class="bh-kandidaat-letop" title="Bijzonderheid gemeld"><?= icoon('waarschuwing', 14) ?></span>
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
  </aside>

  <?php if ($huidig): $hid = (int)$huidig['sol_id'];
    $scores = selectie_scores_van($sid, $hid, $ik);
    $observaties = selectie_observaties_van($sid, $hid);
  ?>
  <section class="bh-beoordeel-kaart" id="kandidaat">
    <header class="bh-beoordeel-kop">
      <?= bh_avatar(['voornaam' => $huidig['voornaam'], 'achternaam' => $huidig['achternaam']], 44) ?>
      <span class="bh-beoordeel-wie">
        <span class="bh-beoordeel-naam"><?= h((string)$huidig['naam']) ?></span>
        <span class="bh-meta">
          <?= $huidig['groep_id'] ? h($groep_naam[$huidig['groep_id']] ?? '') . ' · ' : '' ?>
          <?= (int)($voortgang[$hid] ?? 0) ?> van <?= (int)$totaal_criteria ?> criteria
        </span>
      </span>
      <a class="ds-btn ds-btn-secundair ds-btn-sm"
         href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $hid ?>">Dossier</a>
    </header>

    <?php if (!empty($huidig['bijzonderheden'])): ?>
      <div class="ds-melding ds-melding-warn bh-beoordeel-bijz">
        <?= icoon('waarschuwing', 16) ?>
        <span><strong>Bijzonderheid gemeld:</strong> <?= nl2br(h((string)$huidig['bijzonderheden'])) ?></span>
      </div>
    <?php endif; ?>

    <?php if (empty($huidig['aanwezig'])): ?>
      <div class="ds-melding ds-melding-info">
        <?= icoon('info', 16) ?>
        <span>Deze kandidaat is niet ingecheckt. Beoordelen kan, maar controleer eerst of hij er is.</span>
      </div>
    <?php endif; ?>

    <?php foreach ($criteria as $_ts => $_test): ?>
      <div class="bh-testblok">
        <h3><?= h($_test['naam']) ?></h3>
        <?php foreach ($_test['criteria'] as $_crit):
          $_sc = $scores[$_ts][$_crit['sleutel']] ?? ['eigen' => 0, 'allen' => [], 'gem' => 0];
          $_eigen = (int)$_sc['eigen'];
          $_key = $hid . '-' . $_ts . '-' . $_crit['sleutel'];
        ?>
          <div class="bh-criterium<?= $_eigen ? ' gedaan' : '' ?>" id="crit-<?= h($_key) ?>">
            <div class="bh-criterium-kop">
              <span class="bh-criterium-naam"><?= h($_crit['label']) ?></span>
              <span class="bh-criterium-stand" id="stand-<?= h($_key) ?>">
                <?= $_eigen ? icoon('vink', 12) . 'opgeslagen' : 'nog niet' ?>
              </span>
            </div>
            <div class="bh-cijfers" role="group" aria-label="<?= h($_crit['label']) ?>">
              <?php for ($_s = 1; $_s <= 5; $_s++): ?>
                <button type="button" class="bh-cijfer<?= $_s === $_eigen ? ' gekozen' : '' ?>"
                        <?= $mag_bewerken ? '' : 'disabled' ?>
                        aria-pressed="<?= $_s === $_eigen ? 'true' : 'false' ?>"
                        onclick="zetScore(this, <?= $hid ?>, '<?= h($_ts) ?>', '<?= h($_crit['sleutel']) ?>', <?= $_s ?>)"><?= $_s ?></button>
              <?php endfor; ?>
            </div>
            <span class="bh-criterium-gem" id="gem-<?= h($_key) ?>">
              <?php if (count($_sc['allen']) > 1): ?>
                gemiddelde van <?= count($_sc['allen']) ?> beoordelaars: <?= h((string)$_sc['gem']) ?>
              <?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <div class="bh-observatieblok">
      <h3>Observatie plaatsen</h3>
      <?php if ($mag_bewerken): ?>
        <div class="bh-soortkeuze" id="soort-<?= $hid ?>">
          <button type="button" class="bh-soort positief" data-soort="positief"
                  onclick="kiesSoort(<?= $hid ?>, 'positief', this)">Positief</button>
          <button type="button" class="bh-soort neutraal gekozen" data-soort="neutraal"
                  onclick="kiesSoort(<?= $hid ?>, 'neutraal', this)">Neutraal</button>
          <button type="button" class="bh-soort aandacht" data-soort="aandacht"
                  onclick="kiesSoort(<?= $hid ?>, 'aandacht', this)">Aandacht</button>
        </div>
        <textarea class="ds-textarea" id="obs-<?= $hid ?>" rows="2" placeholder="Wat viel je op…"></textarea>
        <button type="button" class="ds-btn ds-btn-primair ds-btn-vol" onclick="obsToevoegen(<?= $hid ?>)">
          Observatie opslaan
        </button>
      <?php endif; ?>

      <ul class="bh-observaties" id="obslijst-<?= $hid ?>">
        <?php foreach ($observaties as $_o):
          $_kleur = ['positief' => 'groen', 'aandacht' => 'rood'][$_o['soort'] ?? ''] ?? 'grijs';
          $_eigen_obs = ($_o['beoordelaar'] ?? '') === $ik;
        ?>
          <li class="ds-fase-<?= h($_kleur) ?>" id="obs-<?= (int)$_o['id'] ?>">
            <span class="bh-obs-stip"></span>
            <span class="bh-obs-tekst">
              <?= nl2br(h((string)$_o['tekst'])) ?>
              <span class="bh-obs-wie"><?= h((string)($_o['beoordelaar'] ?? '')) ?></span>
            </span>
            <?php if ($_eigen_obs || $mag_alles): ?>
              <button type="button" class="bh-obs-wis" aria-label="Observatie verwijderen"
                      onclick="obsWis(<?= (int)$_o['id'] ?>)"><?= icoon('kruis', 14) ?></button>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <footer class="bh-beoordeel-voet">
      <?php $_vorige = $pos > 0 ? $lijst[$pos - 1] : null; $_volgende = isset($lijst[$pos + 1]) ? $lijst[$pos + 1] : null; ?>
      <?php if ($_vorige): ?>
        <a class="ds-btn ds-btn-secundair" href="<?= h(bd_url((int)$_vorige['sol_id'])) ?>" rel="prev">‹ Vorige</a>
      <?php else: ?>
        <span class="ds-btn ds-btn-zacht" aria-disabled="true">‹ Vorige</span>
      <?php endif; ?>
      <?php if ($_volgende): ?>
        <a class="ds-btn ds-btn-primair bh-beoordeel-volgende" href="<?= h(bd_url((int)$_volgende['sol_id'])) ?>" rel="next">
          Volgende: <?= h((string)$_volgende['voornaam']) ?> ›
        </a>
      <?php else: ?>
        <span class="bh-meta bh-beoordeel-volgende">Dit was de laatste van deze groep.</span>
      <?php endif; ?>
    </footer>
  </section>
  <?php endif; ?>
</div>

<script>
const CSRF = '<?= csrf_token() ?>';
const soortKeuze = {};

/* Tikken ís opslaan. De knop springt meteen aan en het vinkje verschijnt; gaat het
   opslaan mis, dan valt hij terug en zegt een toast waarom. */
function zetScore(knop, solId, test, crit, score) {
  const rij = knop.closest('.bh-cijfers');
  const vorige = rij.querySelector('.gekozen');
  const key = solId + '-' + test + '-' + crit;
  const stand = document.getElementById('stand-' + key);
  const kaart = document.getElementById('crit-' + key);

  rij.querySelectorAll('.bh-cijfer').forEach(function (b) {
    b.classList.remove('gekozen'); b.setAttribute('aria-pressed', 'false');
  });
  knop.classList.add('gekozen'); knop.setAttribute('aria-pressed', 'true');
  if (kaart) kaart.classList.add('gedaan');
  if (stand) stand.textContent = 'opslaan…';

  fetch(location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ actie: 'score', sol_id: solId, test: test, criterium: crit, score: score, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(d => {
    if (!d.ok) throw new Error(d.fout || 'mislukt');
    if (stand) stand.textContent = 'opgeslagen';
    const gem = document.getElementById('gem-' + key);
    if (gem && d.aantal > 1) gem.textContent = 'gemiddelde van ' + d.aantal + ' beoordelaars: ' + d.gem;
  })
  .catch(() => {
    knop.classList.remove('gekozen'); knop.setAttribute('aria-pressed', 'false');
    if (vorige) { vorige.classList.add('gekozen'); vorige.setAttribute('aria-pressed', 'true'); }
    if (!vorige && kaart) kaart.classList.remove('gedaan');
    if (stand) stand.textContent = 'niet opgeslagen';
    bhToast('Score niet opgeslagen — probeer opnieuw.', 'fout');
  });
}

function kiesSoort(solId, soort, knop) {
  soortKeuze[solId] = soort;
  document.getElementById('soort-' + solId).querySelectorAll('.bh-soort')
    .forEach(function (b) { b.classList.remove('gekozen'); });
  knop.classList.add('gekozen');
}

function obsToevoegen(solId) {
  const veld = document.getElementById('obs-' + solId);
  const tekst = veld.value.trim();
  if (!tekst) { bhToast('Schrijf eerst iets op.', 'fout'); return; }
  fetch(location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      actie: 'observatie', sol_id: solId, tekst: tekst,
      soort: soortKeuze[solId] || 'neutraal', _csrf: CSRF
    })
  })
  .then(r => r.json())
  .then(d => {
    if (!d.ok) { bhToast('Opslaan mislukt.', 'fout'); return; }
    veld.value = '';
    bhToast('Observatie opgeslagen.', 'succes');
    setTimeout(function () { location.reload(); }, 600);
  })
  .catch(() => bhToast('Geen verbinding — probeer opnieuw.', 'fout'));
}

function obsWis(id) {
  bhBevestig({ titel: 'Observatie verwijderen?', knop: 'Ja, verwijderen', gevaar: true })
    .then(function (ja) {
      if (!ja) return;
      fetch(location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ actie: 'observatie_wis', id: id, _csrf: CSRF })
      })
      .then(r => r.json())
      .then(d => {
        if (!d.ok) { bhToast('Verwijderen mislukt.', 'fout'); return; }
        const el = document.getElementById('obs-' + id);
        if (el) el.remove();
      })
      .catch(() => bhToast('Geen verbinding — probeer opnieuw.', 'fout'));
    });
}
</script>

<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>

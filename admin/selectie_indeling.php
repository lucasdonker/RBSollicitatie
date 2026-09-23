<?php
/**
 * Groepsindeling — bestuur, op een laptop.
 *
 * Het systeem doet een voorstel, de mens beslist (ontwerpfilosofie §3): een knop
 * genereert een eerlijke verdeling op leeftijd en geslacht, daarna verplaats je met de
 * hand (slepen, of de keuzelijst op het kaartje). Wie niet bij elkaar mag, geef je per persoon aan; zit zo'n koppel toch samen,
 * dan zegt de kaart dat.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

vereis_recht('selectiedag', 'lezen');

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;
$mag_bewerken = mag('selectiedag', 'bewerken');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    csrf_check();
    if (!$mag_bewerken) { http_response_code(403); exit; }
    $actie = (string)($_POST['actie'] ?? '');

    if ($actie === 'aantal_groepen') {
        selectie_groepen_zetten($sid, (int)$_POST['aantal']);
        flash('succes', 'Aantal groepen bijgewerkt.');
        redirect(APP_URL . '/admin/selectie_indeling.php');
    }
    if ($actie === 'voorstel') {
        $kandidaten = selectie_kandidaten($sid);
        $groepen = selectie_groepen($sid);
        if (!$groepen) { selectie_groepen_zetten($sid, max(1, (int)$_POST['aantal'])); $groepen = selectie_groepen($sid); }
        $voorstel = selectie_verdeel_voorstel($kandidaten, count($groepen), selectie_relaties($sid));
        $map = [];
        foreach ($voorstel as $sol_id => $gi) $map[$sol_id] = $groepen[$gi]['id'] ?? null;
        selectie_indeling_opslaan($sid, $map);
        flash('succes', 'Voorstel gemaakt. Je kunt het hieronder nog aanpassen.');
        redirect(APP_URL . '/admin/selectie_indeling.php');
    }
    if ($actie === 'verplaats') {
        // Eén endpoint voor slepen (fetch, JSON) en de keuzelijst "Naar groep" op het
        // kaartje (gewoon formulier met terug=1, redirect terug naar dat kaartje).
        $formulier = !empty($_POST['terug']);
        $sol_id    = (int)($_POST['sol_id'] ?? 0);
        $groep_raw = (string)($_POST['groep_id'] ?? '');
        $groep_id  = $groep_raw !== '' ? (int)$groep_raw : null;
        $geldig    = selectie_is_kandidaat($sid, $sol_id)
                  && ($groep_id === null || in_array($groep_id, array_map(fn($g) => (int)$g['id'], selectie_groepen($sid)), true));
        if ($geldig) selectie_indeel($sid, $sol_id, $groep_id);
        if ($formulier) {
            flash($geldig ? 'succes' : 'fout', $geldig ? 'Verplaatst.' : 'Verplaatsen niet gelukt: onbekende kandidaat of groep.');
            redirect(APP_URL . '/admin/selectie_indeling.php#kaart-' . $sol_id);
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => $geldig]);
        exit;
    }
    if ($actie === 'relatie') {
        header('Content-Type: application/json');
        selectie_relatie_zet($sid, (int)$_POST['a'], (int)$_POST['b'], ($_POST['toevoegen'] ?? '') === '1');
        echo json_encode(['ok' => true]);
        exit;
    }
}

$kandidaten = $sid ? selectie_kandidaten($sid) : [];
$groepen    = $sid ? selectie_groepen($sid) : [];
$relaties   = $sid ? selectie_relaties($sid) : [];

$per_groep = ['0' => []];                         // '0' = nog niet ingedeeld
foreach ($groepen as $g) $per_groep[$g['id']] = [];
foreach ($kandidaten as $k) {
    $gid = $k['groep_id'] ? (string)$k['groep_id'] : '0';
    if (!isset($per_groep[$gid])) $gid = '0';
    $per_groep[$gid][] = $k;
}
$n_ingedeeld = count(array_filter($kandidaten, fn($k) => $k['groep_id']));
$n_open      = count($per_groep['0']);

$pagina_titel = 'Groepsindeling';
$actieve_nav  = 'selectiedag';
require __DIR__ . '/layout_top.php';
?>

<?php
  $subnav_actief = 'groepen';
  $subnav_datum  = $seizoen && !empty($seizoen['datum_selectiedag']) ? datum_nl($seizoen['datum_selectiedag']) : null;
  require __DIR__ . '/_selectie_subnav.php';
?>

<?php if (!$sid || !$kandidaten): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php
      $leeg_titel = $sid ? 'Nog niemand uitgenodigd' : 'Geen actief seizoen';
      $leeg_tekst = $sid ? 'Zodra er kandidaten voor de selectiedag zijn uitgenodigd, verdeel je ze hier in groepen.' : '';
      $leeg_icoon = 'mensen';
      $leeg_knop  = $sid && mag('sollicitanten', 'lezen') ? ['tekst' => 'Naar sollicitanten', 'url' => APP_URL . '/admin/sollicitanten.php'] : null;
      require __DIR__ . '/_leeg.php';
    ?>
  </div>
<?php else: ?>

<section class="ds-kaart ds-kaart-p bh-indeel-balk">
  <?php if ($mag_bewerken): ?>
    <form method="post" class="bh-indeel-aantal">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="aantal_groepen">
      <label class="bh-veldlabel" for="aantal">Aantal groepen</label>
      <div class="bh-indeel-aantalrij">
        <input type="number" class="ds-input" id="aantal" name="aantal" min="1" max="20"
               value="<?= count($groepen) ?: 3 ?>">
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Instellen</button>
      </div>
    </form>

    <form method="post" id="voorstelForm">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="voorstel">
      <input type="hidden" name="aantal" value="<?= count($groepen) ?: 3 ?>">
      <button type="button" class="ds-btn ds-btn-primair" onclick="voorstelMaken()">Voorstel maken</button>
    </form>
  <?php endif; ?>

  <div class="bh-indeel-stand">
    <span><span class="bh-num"><?= $n_ingedeeld ?></span> van <span class="bh-num"><?= count($kandidaten) ?></span> ingedeeld</span>
    <?php if ($n_open): ?>
      <span class="bh-indeel-open"><?= $n_open ?> nog niet</span>
    <?php endif; ?>
  </div>

  <p class="bh-indeel-uitleg">
    Het voorstel maakt gelijke groepen, gemengd op leeftijd en geslacht, en houdt koppels
    uit elkaar die je hebt aangegeven. Daarna verplaats je zelf: slepen, of per kaartje met
    de keuzelijst "Naar groep".
    <?php if ($mag_bewerken): ?>Klik op een naam om aan te geven wie níét bij elkaar mogen.<?php endif; ?>
  </p>
</section>

<div class="bh-groepgrid">
  <?php if ($n_open): ?>
    <div class="bh-groepkolom open">
      <div class="bh-groepkolom-kop">
        <span>Nog niet ingedeeld</span>
        <span class="bh-num" data-telling="0"><?= $n_open ?></span>
      </div>
      <div class="bh-groepkolom-body droppable" data-groep="">
        <?php foreach ($per_groep['0'] as $_k): ?>
          <?php $_kand = $_k; $_conflict = false; require __DIR__ . '/_kandidaatkaart.php'; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php foreach ($groepen as $_g): ?>
    <div class="bh-groepkolom">
      <div class="bh-groepkolom-kop">
        <span><?= h($_g['naam'] ?: 'Groep ' . $_g['nummer']) ?></span>
        <span class="bh-num" data-telling="<?= (int)$_g['id'] ?>"><?= count($per_groep[$_g['id']] ?? []) ?></span>
      </div>
      <div class="bh-groepkolom-body droppable" data-groep="<?= (int)$_g['id'] ?>">
        <?php foreach ($per_groep[$_g['id']] ?? [] as $_k):
          // Zit hier iemand die met deze kandidaat uit elkaar moet?
          $_conflict = false;
          foreach ($relaties[(int)$_k['sol_id']] ?? [] as $_ander) {
              foreach ($per_groep[$_g['id']] as $_mede) {
                  if ((int)$_mede['sol_id'] === $_ander) { $_conflict = true; break 2; }
              }
          }
          $_kand = $_k;
          require __DIR__ . '/_kandidaatkaart.php';
        endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($mag_bewerken): ?>
<dialog class="bh-dialoog" id="relModal">
  <h2>Wie mogen niet bij elkaar?</h2>
  <p>Vink aan wie <strong id="relNaam"></strong> kent en dus liever niet in dezelfde groep zit.</p>
  <div id="relLijst" class="bh-rel-lijst"></div>
  <div class="bh-dialoog-knoppen">
    <button type="button" class="ds-btn ds-btn-primair" onclick="relSluit()">Klaar</button>
  </div>
</dialog>
<?php endif; ?>

<script>
// window.CSRF, geen const: layout_bottom.php declareert CSRF ook, en een tweede const
// brak dat hele script af (SyntaxError) — thema, meldingen en fase wijzigen deden niets.
window.CSRF = window.CSRF || '<?= csrf_token() ?>';
const MAG_BEWERKEN = <?= $mag_bewerken ? 'true' : 'false' ?>;
const KANDIDATEN = <?= json_encode(array_map(fn($k) => ['id' => (int)$k['sol_id'], 'naam' => $k['naam']], $kandidaten)) ?>;
let RELATIES = <?= json_encode(array_map(fn($l) => array_values(array_unique($l)), $relaties)) ?: '{}' ?>;

/* Slepen: het kaartje verhuist meteen, de server volgt. Daarna één herlaadactie,
   omdat de conflictmarkering van de hele kolom afhangt en niet van dit ene kaartje. */
let sleep = null;
if (MAG_BEWERKEN) {
  document.querySelectorAll('.bh-kandkaart').forEach(function (kaart) {
    kaart.addEventListener('dragstart', function () { sleep = kaart; kaart.classList.add('sleept'); });
    kaart.addEventListener('dragend',   function () { kaart.classList.remove('sleept'); sleep = null; });
  });
  document.querySelectorAll('.droppable').forEach(function (zone) {
    zone.addEventListener('dragover',  function (e) { e.preventDefault(); zone.classList.add('doel'); });
    zone.addEventListener('dragleave', function () { zone.classList.remove('doel'); });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      zone.classList.remove('doel');
      if (!sleep) return;
      const solId = sleep.dataset.sol;
      zone.appendChild(sleep);
      hertel();
      fetch(location.pathname, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ actie: 'verplaats', sol_id: solId, groep_id: zone.dataset.groep, _csrf: CSRF })
      })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) bhToast('Verplaatsen niet opgeslagen.', 'fout');
        // Bij een fout wat langer wachten, zodat de melding te lezen is vóór het kaartje terugspringt.
        setTimeout(function () { location.reload(); }, d.ok ? 120 : 1800);
      })
      .catch(function () { bhToast('Verplaatsen niet opgeslagen.', 'fout'); });
    });
  });
}

function hertel() {
  document.querySelectorAll('.bh-groepkolom-body').forEach(function (body) {
    const gid = body.dataset.groep || '0';
    const teller = document.querySelector('[data-telling="' + gid + '"]');
    if (teller) teller.textContent = body.querySelectorAll('.bh-kandkaart').length;
  });
}

function voorstelMaken() {
  bhBevestig({
    titel: 'Een nieuw voorstel maken?',
    tekst: 'Dit overschrijft de indeling die er nu staat, ook de kaartjes die je zelf hebt versleept.',
    knop: 'Ja, opnieuw verdelen'
  }).then(function (ja) { if (ja) document.getElementById('voorstelForm').submit(); });
}

function relKies(solId, naam) {
  if (!MAG_BEWERKEN) return;
  document.getElementById('relNaam').textContent = naam;
  const lijst = document.getElementById('relLijst');
  lijst.innerHTML = '';
  const mijn = RELATIES[solId] || [];
  KANDIDATEN.filter(function (k) { return k.id !== solId; }).forEach(function (k) {
    const rij = document.createElement('label');
    rij.className = 'bh-rel-rij';
    const vak = document.createElement('input');
    vak.type = 'checkbox';
    vak.checked = mijn.indexOf(k.id) !== -1;
    vak.addEventListener('change', function (e) { relZet(solId, k.id, e.target.checked); });
    rij.appendChild(vak);
    rij.appendChild(document.createTextNode(' ' + k.naam));
    lijst.appendChild(rij);
  });
  document.getElementById('relModal').showModal();
}

function relZet(a, b, aan) {
  if (!RELATIES[a]) RELATIES[a] = [];
  if (!RELATIES[b]) RELATIES[b] = [];
  if (aan) {
    if (RELATIES[a].indexOf(b) === -1) RELATIES[a].push(b);
    if (RELATIES[b].indexOf(a) === -1) RELATIES[b].push(a);
  } else {
    RELATIES[a] = RELATIES[a].filter(function (x) { return x !== b; });
    RELATIES[b] = RELATIES[b].filter(function (x) { return x !== a; });
  }
  fetch(location.pathname, {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ actie: 'relatie', a: a, b: b, toevoegen: aan ? '1' : '0', _csrf: CSRF })
  }).catch(function () { bhToast('Niet opgeslagen.', 'fout'); });
}

function relSluit() {
  document.getElementById('relModal').close();
  location.reload();
}
</script>

<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>

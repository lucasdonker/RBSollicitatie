<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Beoordelen';
$actieve_nav  = 'selectiedag';
vereist_rol('begeleider');

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;
$ik = $_SESSION['bhd_naam'] ?? 'onbekend';
$mag_alles = huidige_gebruiker_rol_minstens('bestuur');

// ── AJAX-acties ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    header('Content-Type: application/json');
    csrf_check();
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'score') {
        selectie_score_opslaan($sid, (int)$_POST['sol_id'], $_POST['test'], $_POST['criterium'], (int)$_POST['score'], $ik);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($actie === 'observatie') {
        $id = selectie_observatie_toevoegen($sid, (int)$_POST['sol_id'], $_POST['tekst'] ?? '', $_POST['soort'] ?? 'neutraal', $ik);
        echo json_encode(['ok' => $id !== null, 'id' => $id, 'door' => $ik, 'wanneer' => 'zojuist']);
        exit;
    }
    if ($actie === 'observatie_wis') {
        $ok = selectie_observatie_verwijderen((int)$_POST['id'], $ik, $mag_alles);
        echo json_encode(['ok' => $ok]);
        exit;
    }
    echo json_encode(['ok' => false]);
    exit;
}

$criteria = selectie_criteria();
$groepen  = $sid ? selectie_groepen($sid) : [];
$kandidaten = $sid ? selectie_kandidaten($sid) : [];
$obs_tel = $sid ? selectie_observatie_tellingen($sid) : [];

// Gekozen groep (uit URL); default: alle
$gekozen = isset($_GET['groep']) ? $_GET['groep'] : '';

// Filter kandidaten op groep
$groep_naam = [];
foreach ($groepen as $g) $groep_naam[$g['id']] = $g['naam'] ?: 'Groep ' . $g['nummer'];

$zichtbaar = array_filter($kandidaten, function($k) use ($gekozen) {
    if ($gekozen === '') return true;
    if ($gekozen === 'geen') return !$k['groep_id'];
    return (string)$k['groep_id'] === (string)$gekozen;
});

require __DIR__ . '/layout_top.php';
?>

<style>
.bd-groepkeuze { display:flex; gap:6px; margin-bottom:14px; flex-wrap:wrap; }
.bd-groepkeuze a { padding:8px 16px; border-radius:20px; font-size:13px; text-decoration:none; color:var(--tekst); background:var(--bg-card); border:1px solid var(--rand); }
.bd-groepkeuze a.actief { background:var(--navy); color:#fff; border-color:var(--navy); }
.bd-kaart { background:var(--bg-card); border:1px solid var(--rand); border-radius:12px; margin-bottom:12px; overflow:hidden; }
.bd-kop { display:flex; align-items:center; gap:10px; padding:12px 16px; cursor:pointer; }
.bd-naam { flex:1; font-weight:600; font-size:15px; }
.bd-foto { width:40px; height:40px; border-radius:50%; object-fit:cover; border:1.5px solid var(--rand); flex-shrink:0; }
.bd-foto-leeg { width:40px; height:40px; border-radius:50%; background:var(--grijs-bg); display:flex; align-items:center; justify-content:center; font-weight:600; color:var(--grijs); font-size:14px; flex-shrink:0; }
.bd-bijz { margin:10px 0 4px; padding:10px 12px; background:#fff5f5; border:1px solid #feb2b2; border-radius:8px; font-size:13px; color:#9b2c2c; }
.bd-foto-groot { width:90px; height:90px; border-radius:10px; object-fit:cover; border:2px solid var(--rand); float:right; margin:0 0 8px 12px; }
.bd-body { display:none; padding:0 16px 16px; border-top:1px solid var(--rand-licht); }
.bd-kaart.open .bd-body { display:block; }
.bd-obs-veld { display:flex; gap:8px; margin:14px 0 10px; flex-wrap:wrap; }
.bd-obs-veld textarea { flex:1; min-width:200px; padding:10px 12px; border:1px solid var(--rand); border-radius:8px; font-size:14px; font-family:inherit; resize:vertical; min-height:44px; }
.bd-obs-lijst { display:flex; flex-direction:column; gap:6px; }
.bd-obs { display:flex; gap:8px; align-items:flex-start; padding:8px 10px; border-radius:8px; font-size:13px; background:var(--grijs-bg); }
.bd-obs.positief { background:var(--groen-bg); }
.bd-obs.aandacht { background:var(--rood-bg); }
.bd-obs-tekst { flex:1; }
.bd-obs-meta { font-size:11px; color:var(--grijs); }
.ster { font-size:26px; cursor:pointer; color:var(--rand); line-height:1; user-select:none; }
.ster.aan { color:#f5a623; }
.crit-rij { display:flex; align-items:center; gap:6px; padding:6px 0; }
.crit-label { flex:1; font-size:13.5px; }
.crit-gem { font-size:11px; color:var(--grijs); min-width:64px; text-align:right; }
.test-blok { margin-top:12px; }
.test-titel { font-size:13px; font-weight:600; color:var(--tekst-zacht); margin-bottom:4px; }
.soort-kies { display:flex; gap:4px; }
.soort-kies button { padding:8px 12px; border:1px solid var(--rand); border-radius:8px; background:var(--bg-card); cursor:pointer; font-size:13px; }
.soort-kies button.aan { border-width:2px; font-weight:600; }
</style>

<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
  <a href="<?= APP_URL ?>/admin/selectie_indeling.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--tekst);background:var(--bg-card);border:1px solid var(--rand)">1 · Groepsindeling</a>
  <a href="<?= APP_URL ?>/admin/selectie_inchecken.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--tekst);background:var(--bg-card);border:1px solid var(--rand)">2 · Inchecken</a>
  <a href="<?= APP_URL ?>/admin/selectiedag.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:#fff;background:var(--navy);border:1px solid var(--navy)">3 · Beoordelen</a>
  <?php if ($mag_alles): ?>
  <a href="<?= APP_URL ?>/admin/selectie_nabespreking.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--tekst);background:var(--bg-card);border:1px solid var(--rand)">4 · Nabespreking</a>
  <?php endif; ?>
</div>

<?php if (!$sid): ?>
  <div class="flash flash-fout">Geen actief seizoen.</div>
<?php elseif (!$kandidaten): ?>
  <div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--tekst-zacht)">
    Nog geen sollicitanten voor de selectiedag.
  </div></div>
<?php else: ?>

<p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 12px">
  Je beoordeelt als <strong><?= h($ik) ?></strong>. Tik een naam open om een observatie te noteren of sterren te geven.
  Observaties mogen op iedereen — ook buiten je eigen groep.
</p>

<!-- Groepskeuze -->
<div class="bd-groepkeuze">
  <a href="?groep=" class="<?= $gekozen===''?'actief':'' ?>">Alle (<?= count($kandidaten) ?>)</a>
  <?php foreach ($groepen as $g):
    $n = count(array_filter($kandidaten, fn($k) => (string)$k['groep_id'] === (string)$g['id']));
  ?>
  <a href="?groep=<?= (int)$g['id'] ?>" class="<?= (string)$gekozen===(string)$g['id']?'actief':'' ?>"><?= h($groep_naam[$g['id']]) ?> (<?= $n ?>)</a>
  <?php endforeach; ?>
  <?php $n_geen = count(array_filter($kandidaten, fn($k) => !$k['groep_id'])); if ($n_geen): ?>
  <a href="?groep=geen" class="<?= $gekozen==='geen'?'actief':'' ?>">Geen groep (<?= $n_geen ?>)</a>
  <?php endif; ?>
</div>

<!-- Kandidaten -->
<?php foreach ($zichtbaar as $k):
  $sol_id = (int)$k['sol_id'];
  $scores = selectie_scores_van($sid, $sol_id, $ik);
  $observaties = selectie_observaties_van($sid, $sol_id);
  $n_obs = $obs_tel[$sol_id] ?? 0;
?>
<div class="bd-kaart" id="kaart-<?= $sol_id ?>">
  <div class="bd-kop" onclick="document.getElementById('kaart-<?= $sol_id ?>').classList.toggle('open')">
    <?php $foto = !empty($k['foto_url']) ? foto_url($k['foto_url']) : null; ?>
    <?php if ($foto): ?>
      <img class="bd-foto" src="<?= h($foto) ?>" alt="" loading="lazy">
    <?php else: ?>
      <span class="bd-foto-leeg"><?= h(mb_strtoupper(mb_substr($k['voornaam'], 0, 1) . mb_substr($k['achternaam'], 0, 1))) ?></span>
    <?php endif; ?>
    <span class="bd-naam"><?= h($k['naam']) ?></span>
    <?php if (!empty($k['bijzonderheden'])): ?><span title="Heeft bijzonderheden">⚠️</span><?php endif; ?>
    <?php if (!$k['aanwezig']): ?><span class="badge badge-grijs" style="font-size:10px">afwezig</span><?php endif; ?>
    <?php if ($n_obs): ?><span class="badge badge-blauw" style="font-size:10px">💬 <?= $n_obs ?></span><?php endif; ?>
    <span style="color:var(--grijs)">▾</span>
  </div>
  <div class="bd-body">

    <?php if ($foto || !empty($k['bijzonderheden'])): ?>
    <div style="padding-top:12px">
      <?php if ($foto): ?><img class="bd-foto-groot" src="<?= h($foto) ?>" alt="Foto van <?= h($k['naam']) ?>" loading="lazy"><?php endif; ?>
      <?php if (!empty($k['bijzonderheden'])): ?>
      <div class="bd-bijz">⚠️ <strong>Bijzonderheden:</strong> <?= nl2br(h($k['bijzonderheden'])) ?></div>
      <?php endif; ?>
      <div style="clear:both"></div>
    </div>
    <?php endif; ?>

    <!-- Observatie toevoegen (centraal) -->
    <div class="bd-obs-veld">
      <textarea id="obs-<?= $sol_id ?>" placeholder="Wat viel op? (observatie)" rows="1"></textarea>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
      <div class="soort-kies" id="soort-<?= $sol_id ?>">
        <button type="button" data-soort="positief" onclick="kiesSoort(<?= $sol_id ?>,'positief',this)">👍 Positief</button>
        <button type="button" data-soort="neutraal" class="aan" onclick="kiesSoort(<?= $sol_id ?>,'neutraal',this)">📝 Neutraal</button>
        <button type="button" data-soort="aandacht" onclick="kiesSoort(<?= $sol_id ?>,'aandacht',this)">⚠️ Aandacht</button>
      </div>
      <span style="flex:1"></span>
      <button type="button" class="btn btn-primary btn-sm" onclick="obsToevoegen(<?= $sol_id ?>)">Opslaan</button>
    </div>

    <!-- Bestaande observaties -->
    <div class="bd-obs-lijst" id="obslijst-<?= $sol_id ?>">
      <?php foreach ($observaties as $o):
        $eigen = $o['beoordelaar'] === $ik;
      ?>
      <div class="bd-obs <?= h($o['soort']) ?>" id="obs-rij-<?= (int)$o['id'] ?>">
        <span class="bd-obs-tekst">
          <?= nl2br(h($o['tekst'])) ?>
          <span class="bd-obs-meta">— <?= h($o['beoordelaar']) ?></span>
        </span>
        <?php if ($eigen || $mag_alles): ?>
        <button type="button" onclick="obsWis(<?= (int)$o['id'] ?>)" title="Verwijderen"
                style="border:none;background:none;cursor:pointer;color:var(--grijs);font-size:14px">✕</button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Scores per test (optioneel, uitklapbaar) -->
    <?php foreach ($criteria as $test_sleutel => $test): ?>
    <div class="test-blok">
      <div class="test-titel"><?= h($test['icoon'] ?? '') ?> <?= h($test['naam']) ?></div>
      <?php foreach ($test['criteria'] as $crit):
        $sc = $scores[$test_sleutel][$crit['sleutel']] ?? ['eigen'=>0,'allen'=>[],'gem'=>0];
      ?>
      <div class="crit-rij">
        <span class="crit-label"><?= h($crit['label']) ?></span>
        <span class="sterren" data-sol="<?= $sol_id ?>" data-test="<?= h($test_sleutel) ?>" data-crit="<?= h($crit['sleutel']) ?>">
          <?php for ($s = 1; $s <= 5; $s++): ?>
          <span class="ster <?= $s <= $sc['eigen'] ? 'aan' : '' ?>" data-score="<?= $s ?>" onclick="zetScore(this)">★</span>
          <?php endfor; ?>
        </span>
        <span class="crit-gem" id="gem-<?= $sol_id ?>-<?= h($test_sleutel) ?>-<?= h($crit['sleutel']) ?>">
          <?= count($sc['allen']) > 1 ? 'gem ' . $sc['gem'] . ' (' . count($sc['allen']) . ')' : '' ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

  </div>
</div>
<?php endforeach; ?>

<script>
const CSRF = '<?= csrf_token() ?>';
const IK = <?= json_encode($ik) ?>;
const soortKeuze = {};

function kiesSoort(solId, soort, btn) {
  soortKeuze[solId] = soort;
  btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('aan'));
  btn.classList.add('aan');
}

function obsToevoegen(solId) {
  const veld = document.getElementById('obs-' + solId);
  const tekst = veld.value.trim();
  if (!tekst) return;
  const soort = soortKeuze[solId] || 'neutraal';
  fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ actie:'observatie', sol_id:solId, tekst, soort, _csrf:CSRF }) })
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return;
      const lijst = document.getElementById('obslijst-' + solId);
      const div = document.createElement('div');
      div.className = 'bd-obs ' + soort;
      div.id = 'obs-rij-' + d.id;
      div.innerHTML = '<span class="bd-obs-tekst">' + esc(tekst).replace(/\n/g,'<br>') +
        ' <span class="bd-obs-meta">— ' + esc(IK) + '</span></span>' +
        '<button type="button" onclick="obsWis(' + d.id + ')" style="border:none;background:none;cursor:pointer;color:var(--grijs);font-size:14px">✕</button>';
      lijst.insertBefore(div, lijst.firstChild);
      veld.value = '';
      // Badge bijwerken
      const kop = document.querySelector('#kaart-' + solId + ' .bd-kop');
    });
}

function obsWis(id) {
  if (!confirm('Observatie verwijderen?')) return;
  fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ actie:'observatie_wis', id, _csrf:CSRF }) })
    .then(r => r.json())
    .then(d => { if (d.ok) document.getElementById('obs-rij-' + id)?.remove(); });
}

function zetScore(ster) {
  const wrap = ster.closest('.sterren');
  const nieuw = parseInt(ster.dataset.score);
  const huidig = wrap.querySelectorAll('.ster.aan').length;
  // Zelfde ster nogmaals = wissen (score 0)
  const score = (nieuw === huidig) ? 0 : nieuw;
  wrap.querySelectorAll('.ster').forEach(s => {
    s.classList.toggle('aan', parseInt(s.dataset.score) <= score);
  });
  fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ actie:'score', sol_id:wrap.dataset.sol, test:wrap.dataset.test,
      criterium:wrap.dataset.crit, score, _csrf:CSRF }) });
}

// Textarea groeit mee
document.querySelectorAll('.bd-obs-veld textarea').forEach(t => {
  t.addEventListener('input', () => { t.style.height='auto'; t.style.height=t.scrollHeight+'px'; });
});

function esc(s){ const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
</script>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

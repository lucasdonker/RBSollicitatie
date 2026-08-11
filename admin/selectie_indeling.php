<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Groepsindeling';
$actieve_nav  = 'selectiedag';
vereist_rol('bestuur');

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;

// ── Acties ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    csrf_check();
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'aantal_groepen') {
        selectie_groepen_zetten($sid, (int)$_POST['aantal']);
        flash('succes', 'Aantal groepen bijgewerkt.');
        redirect(APP_URL . '/admin/selectie_indeling.php');
    }

    if ($actie === 'voorstel') {
        // Genereer een voorstel en sla het meteen op
        $kandidaten = selectie_kandidaten($sid);
        $groepen = selectie_groepen($sid);
        if (!$groepen) { selectie_groepen_zetten($sid, max(1, (int)$_POST['aantal'])); $groepen = selectie_groepen($sid); }
        $relaties = selectie_relaties($sid);
        $voorstel = selectie_verdeel_voorstel($kandidaten, count($groepen), $relaties);
        // groep_index → groep_id
        $map = [];
        foreach ($voorstel as $sol_id => $gi) $map[$sol_id] = $groepen[$gi]['id'] ?? null;
        selectie_indeling_opslaan($sid, $map);
        flash('succes', 'Voorstel gegenereerd — je kunt het hieronder nog aanpassen.');
        redirect(APP_URL . '/admin/selectie_indeling.php');
    }

    if ($actie === 'verplaats') {
        // AJAX: één sollicitant naar een groep
        header('Content-Type: application/json');
        selectie_indeel($sid, (int)$_POST['sol_id'], $_POST['groep_id'] !== '' ? (int)$_POST['groep_id'] : null);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($actie === 'relatie') {
        header('Content-Type: application/json');
        selectie_relatie_zet($sid, (int)$_POST['a'], (int)$_POST['b'], $_POST['toevoegen'] === '1');
        echo json_encode(['ok' => true]);
        exit;
    }
}

$kandidaten = $sid ? selectie_kandidaten($sid) : [];
$groepen    = $sid ? selectie_groepen($sid) : [];
$relaties   = $sid ? selectie_relaties($sid) : [];

// Indeling groeperen
$per_groep = ['0' => []]; // '0' = nog niet ingedeeld
foreach ($groepen as $g) $per_groep[$g['id']] = [];
foreach ($kandidaten as $k) {
    $gid = $k['groep_id'] ? (string)$k['groep_id'] : '0';
    if (!isset($per_groep[$gid])) $gid = '0';
    $per_groep[$gid][] = $k;
}
$n_ingedeeld = count(array_filter($kandidaten, fn($k) => $k['groep_id']));

require __DIR__ . '/layout_top.php';
?>

<style>
.si-subnav { display:flex; gap:6px; margin-bottom:16px; flex-wrap:wrap; }
.si-subnav a { padding:7px 14px; border-radius:8px; font-size:13px; text-decoration:none; color:var(--tekst); background:var(--bg-card); border:1px solid var(--rand); }
.si-subnav a.actief { background:var(--navy); color:#fff; border-color:var(--navy); }
.groep-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:12px; }
.groep-kol { background:var(--bg-card); border:1px solid var(--rand); border-radius:10px; overflow:hidden; }
.groep-kop { padding:10px 12px; font-weight:600; font-size:13.5px; background:var(--grijs-bg); border-bottom:1px solid var(--rand); display:flex; justify-content:space-between; align-items:center; }
.groep-body { padding:8px; min-height:60px; display:flex; flex-direction:column; gap:5px; }
.kand-kaart { background:var(--bg); border:1px solid var(--rand); border-radius:7px; padding:7px 9px; font-size:12.5px; cursor:grab; display:flex; align-items:center; gap:6px; }
.kand-kaart:active { cursor:grabbing; }
.kand-kaart.sleep { opacity:.4; }
.groep-body.droptarget { background:var(--blauw-bg); outline:2px dashed var(--blauw); outline-offset:-4px; }
.mini { font-size:10px; color:var(--grijs); }
.rel-waarschuw { color:var(--rood); font-weight:600; }
</style>

<div class="si-subnav">
  <a href="<?= APP_URL ?>/admin/selectie_indeling.php" class="actief">1 · Groepsindeling</a>
  <a href="<?= APP_URL ?>/admin/selectie_inchecken.php">2 · Inchecken</a>
  <a href="<?= APP_URL ?>/admin/selectiedag.php">3 · Beoordelen</a>
  <a href="<?= APP_URL ?>/admin/selectie_nabespreking.php">4 · Nabespreking</a>
</div>

<?php if (!$sid): ?>
  <div class="flash flash-fout">Geen actief seizoen.</div>
<?php elseif (!$kandidaten): ?>
  <div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--tekst-zacht)">
    Nog geen sollicitanten uitgenodigd voor de selectiedag.
    <br><a href="<?= APP_URL ?>/admin/sollicitanten.php" class="btn btn-primary" style="margin-top:12px">Naar sollicitanten</a>
  </div></div>
<?php else: ?>

<!-- Instelbalk -->
<div class="card" style="margin-bottom:14px">
  <div class="card-body" style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap">
    <form method="POST" style="display:flex;gap:8px;align-items:flex-end;margin:0">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="aantal_groepen">
      <div class="form-groep" style="margin:0">
        <label style="font-size:12px" for="aantal">Aantal groepen</label>
        <input type="number" id="aantal" name="aantal" min="1" max="20" value="<?= count($groepen) ?: 3 ?>" style="width:80px">
      </div>
      <button type="submit" class="btn btn-outline btn-sm">Instellen</button>
    </form>

    <form method="POST" style="margin:0" onsubmit="return confirm('Dit maakt een nieuwe indeling en overschrijft de huidige. Doorgaan?')">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="voorstel">
      <input type="hidden" name="aantal" value="<?= count($groepen) ?: 3 ?>">
      <button type="submit" class="btn btn-primary btn-sm">✨ Voorstel genereren</button>
    </form>

    <div style="flex:1;min-width:150px;text-align:right;font-size:12.5px;color:var(--tekst-zacht)">
      <?= $n_ingedeeld ?> van <?= count($kandidaten) ?> ingedeeld
      <?php if (count($per_groep['0'] ?? [])): ?>
        · <span style="color:var(--oranje)"><?= count($per_groep['0']) ?> nog niet</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body" style="padding-top:0">
    <p style="font-size:12px;color:var(--grijs);margin:0">
      Het voorstel verdeelt eerlijk (gelijke groepen, gemengd op leeftijd en geslacht) en houdt bekende koppels uit elkaar.
      Sleep daarna kaartjes om te schuiven. Klik op een naam om aan te geven wie níét bij elkaar mogen.
    </p>
  </div>
</div>

<!-- Groepen -->
<div class="groep-grid">
  <!-- Nog niet ingedeeld -->
  <?php if (count($per_groep['0'] ?? [])): ?>
  <div class="groep-kol" style="border-color:var(--oranje)">
    <div class="groep-kop" style="background:var(--oranje-bg);color:#c05a1a">
      <span>Nog niet ingedeeld</span>
      <span class="mini" data-telling="0"><?= count($per_groep['0']) ?></span>
    </div>
    <div class="groep-body droppable" data-groep="">
      <?php foreach ($per_groep['0'] as $k): ?>
      <div class="kand-kaart" draggable="true" data-sol="<?= (int)$k['sol_id'] ?>" onclick="relKies(<?= (int)$k['sol_id'] ?>, '<?= h(addslashes($k['naam'])) ?>')">
        <span style="flex:1"><?= h($k['naam']) ?></span>
        <span class="mini"><?= $k['leeftijd'] ? $k['leeftijd'] . 'j' : '' ?> <?= $k['geslacht'] ? strtoupper(substr($k['geslacht'],0,1)) : '' ?></span>
        <?php if (!empty($relaties[(int)$k['sol_id']])): ?><span class="rel-waarschuw" title="Heeft houd-uit-elkaar koppels">⚠</span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Echte groepen -->
  <?php foreach ($groepen as $gi => $g): ?>
  <div class="groep-kol">
    <div class="groep-kop">
      <span><?= h($g['naam'] ?: 'Groep ' . $g['nummer']) ?></span>
      <span class="mini" data-telling="<?= (int)$g['id'] ?>"><?= count($per_groep[$g['id']] ?? []) ?></span>
    </div>
    <div class="groep-body droppable" data-groep="<?= (int)$g['id'] ?>">
      <?php foreach ($per_groep[$g['id']] ?? [] as $k):
        // Check conflict: zit een houd-uit-elkaar partner in dezelfde groep?
        $conflict = false;
        foreach ($relaties[(int)$k['sol_id']] ?? [] as $ander) {
          foreach ($per_groep[$g['id']] as $mede) if ((int)$mede['sol_id'] === $ander) { $conflict = true; break 2; }
        }
      ?>
      <div class="kand-kaart" draggable="true" data-sol="<?= (int)$k['sol_id'] ?>" onclick="relKies(<?= (int)$k['sol_id'] ?>, '<?= h(addslashes($k['naam'])) ?>')">
        <span style="flex:1"><?= h($k['naam']) ?></span>
        <span class="mini"><?= $k['leeftijd'] ? $k['leeftijd'] . 'j' : '' ?> <?= $k['geslacht'] ? strtoupper(substr($k['geslacht'],0,1)) : '' ?></span>
        <?php if ($conflict): ?><span class="rel-waarschuw" title="Zit bij iemand die uit elkaar moet!">⚠</span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Relatie-kiezer (modal) -->
<div id="relModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;padding:16px">
  <div style="background:var(--bg-card);border-radius:12px;padding:20px;max-width:420px;width:100%;max-height:80vh;overflow:auto">
    <h3 style="margin:0 0 4px;font-size:16px" id="relTitel">Houd uit elkaar</h3>
    <p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 12px">Vink aan wie <strong id="relNaam"></strong> kent en dus liever niet in dezelfde groep zit.</p>
    <div id="relLijst" style="display:flex;flex-direction:column;gap:4px;margin-bottom:14px"></div>
    <button type="button" class="btn btn-primary btn-sm" onclick="relSluit()">Klaar</button>
  </div>
</div>

<script>
const CSRF = '<?= csrf_token() ?>';
const KANDIDATEN = <?= json_encode(array_map(fn($k) => ['id'=>(int)$k['sol_id'], 'naam'=>$k['naam']], $kandidaten)) ?>;
let RELATIES = <?= json_encode(array_map(fn($lijst) => array_values(array_unique($lijst)), $relaties)) ?: '{}' ?>;
let relHuidige = null;

// ── Drag & drop ──
let sleep = null;
document.querySelectorAll('.kand-kaart').forEach(kaart => {
  kaart.addEventListener('dragstart', e => { sleep = kaart; kaart.classList.add('sleep'); });
  kaart.addEventListener('dragend', e => { kaart.classList.remove('sleep'); sleep = null; });
});
document.querySelectorAll('.droppable').forEach(zone => {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('droptarget'); });
  zone.addEventListener('dragleave', e => zone.classList.remove('droptarget'));
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('droptarget');
    if (!sleep) return;
    const solId = sleep.dataset.sol;
    const groepId = zone.dataset.groep;
    zone.appendChild(sleep);
    hertelGroepen();
    fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ actie:'verplaats', sol_id:solId, groep_id:groepId, _csrf:CSRF }) })
      .then(() => setTimeout(() => location.reload(), 100)); // herlaad voor conflict-markering
  });
});

function hertelGroepen() {
  document.querySelectorAll('.groep-body').forEach(body => {
    const gid = body.dataset.groep || '0';
    const teller = document.querySelector('[data-telling="' + gid + '"]');
    if (teller) teller.textContent = body.querySelectorAll('.kand-kaart').length;
  });
}

// ── Houd-uit-elkaar relaties ──
function relKies(solId, naam) {
  relHuidige = solId;
  document.getElementById('relNaam').textContent = naam;
  const lijst = document.getElementById('relLijst');
  lijst.innerHTML = '';
  const mijnRel = RELATIES[solId] || [];
  KANDIDATEN.filter(k => k.id !== solId).forEach(k => {
    const aan = mijnRel.includes(k.id);
    const rij = document.createElement('label');
    rij.style.cssText = 'display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:6px;cursor:pointer;font-size:13px;border:1px solid var(--rand)';
    rij.innerHTML = '<input type="checkbox" ' + (aan?'checked':'') + '> ' + k.naam;
    rij.querySelector('input').addEventListener('change', e => relToggle(solId, k.id, e.target.checked));
    lijst.appendChild(rij);
  });
  document.getElementById('relModal').style.display = 'flex';
}
function relToggle(a, b, toevoegen) {
  if (!RELATIES[a]) RELATIES[a] = [];
  if (!RELATIES[b]) RELATIES[b] = [];
  if (toevoegen) { if(!RELATIES[a].includes(b))RELATIES[a].push(b); if(!RELATIES[b].includes(a))RELATIES[b].push(a); }
  else { RELATIES[a] = RELATIES[a].filter(x=>x!==b); RELATIES[b] = RELATIES[b].filter(x=>x!==a); }
  fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ actie:'relatie', a:a, b:b, toevoegen:toevoegen?'1':'0', _csrf:CSRF }) });
}
function relSluit() { document.getElementById('relModal').style.display='none'; location.reload(); }
document.getElementById('relModal').addEventListener('click', e => { if(e.target.id==='relModal') relSluit(); });
</script>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

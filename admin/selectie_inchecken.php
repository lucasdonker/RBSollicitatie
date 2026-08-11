<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Inchecken';
$actieve_nav  = 'selectiedag';
vereist_rol('begeleider'); // begeleiders mogen ook afvinken

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;

// ── Acties ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    csrf_check();
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'code_genereren') {
        vereist_rol('bestuur');
        // Korte, leesbare code (geen verwarrende tekens)
        $alfabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alfabet[random_int(0, strlen($alfabet) - 1)];
        instelling_opslaan('selectie.incheck_code', $code);
        flash('succes', 'Nieuwe toegangscode gegenereerd.');
        redirect(APP_URL . '/admin/selectie_inchecken.php');
    }

    if ($actie === 'aanwezig') {
        header('Content-Type: application/json');
        $res = selectie_aanwezig_zetten($sid, (int)$_POST['sol_id'], $_POST['aanwezig'] === '1');
        echo json_encode(['ok' => true] + $res);
        exit;
    }
}

$code = function_exists('instelling') ? instelling('selectie.incheck_code', '') : '';
$incheck_url = APP_URL . '/portaal/selectie_incheck.php?code=' . urlencode($code);

// Kandidaten met groep + aanwezigheid
$kandidaten = $sid ? selectie_kandidaten($sid) : [];
$groepen = $sid ? selectie_groepen($sid) : [];
$groep_naam = [];
foreach ($groepen as $g) $groep_naam[$g['id']] = $g['naam'] ?: 'Groep ' . $g['nummer'];

$n_aanwezig = count(array_filter($kandidaten, fn($k) => $k['aanwezig']));
$n_totaal = count($kandidaten);

// Groeperen op groep voor de lijst
$per_groep = [];
foreach ($kandidaten as $k) {
    $key = $k['groep_id'] ? $groep_naam[$k['groep_id']] ?? 'Onbekend' : 'Nog geen groep';
    $per_groep[$key][] = $k;
}
ksort($per_groep);

require __DIR__ . '/layout_top.php';
?>

<style>
.inchk-rij { display:flex; align-items:center; gap:12px; padding:11px 14px; border-bottom:1px solid var(--rand-licht); }
.inchk-rij:last-child { border-bottom:none; }
.inchk-naam { flex:1; font-size:14px; }
.aanw-knop { min-width:110px; padding:8px 14px; border-radius:8px; font-size:13px; font-weight:600; border:1.5px solid var(--rand); background:var(--bg-card); color:var(--tekst-zacht); cursor:pointer; transition:.15s; }
.aanw-knop.ja { background:var(--groen); border-color:var(--groen); color:#fff; }
</style>

<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
  <a href="<?= APP_URL ?>/admin/selectie_indeling.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--tekst);background:var(--bg-card);border:1px solid var(--rand)">1 · Groepsindeling</a>
  <a href="<?= APP_URL ?>/admin/selectie_inchecken.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:#fff;background:var(--navy);border:1px solid var(--navy)">2 · Inchecken</a>
  <a href="<?= APP_URL ?>/admin/selectiedag.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--tekst);background:var(--bg-card);border:1px solid var(--rand)">3 · Beoordelen</a>
  <?php if (huidige_gebruiker_rol_minstens('bestuur')): ?>
  <a href="<?= APP_URL ?>/admin/selectie_nabespreking.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--tekst);background:var(--bg-card);border:1px solid var(--rand)">4 · Nabespreking</a>
  <?php endif; ?>
</div>

<?php if (!$sid): ?>
  <div class="flash flash-fout">Geen actief seizoen.</div>
<?php else: ?>

<!-- Tablet-code -->
<div class="card" style="margin-bottom:14px">
  <div class="card-header"><div class="card-title">📱 Tablet-inchecken</div></div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--tekst-zacht);margin:0 0 12px">
      Zet deze pagina klaar op de tablets bij de ingang. Sollicitanten typen hun naam en melden zichzelf aan.
    </p>
    <?php if ($code): ?>
      <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <div>
          <div style="font-size:11px;color:var(--tekst-zacht)">Toegangscode</div>
          <div style="font-size:24px;font-weight:700;letter-spacing:3px;color:var(--navy);font-family:monospace"><?= h($code) ?></div>
        </div>
        <div style="flex:1;min-width:200px">
          <div style="font-size:11px;color:var(--tekst-zacht)">Tablet-link</div>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="text" readonly value="<?= h($incheck_url) ?>" id="url" style="flex:1;font-size:12px;padding:7px 10px;border:1px solid var(--rand);border-radius:6px;background:var(--grijs-bg)">
            <button type="button" class="btn btn-outline btn-sm" onclick="kopieer()">📋</button>
            <a href="<?= h($incheck_url) ?>" target="_blank" class="btn btn-outline btn-sm">Openen</a>
          </div>
        </div>
      </div>
      <?php if (huidige_gebruiker_rol_minstens('bestuur')): ?>
      <form method="POST" style="margin-top:12px" onsubmit="return confirm('Nieuwe code genereren? De oude tablet-links werken dan niet meer.')">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="code_genereren">
        <button type="submit" class="btn btn-outline btn-sm">🔄 Nieuwe code</button>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <?php if (huidige_gebruiker_rol_minstens('bestuur')): ?>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="code_genereren">
        <button type="submit" class="btn btn-primary">Toegangscode aanmaken</button>
      </form>
      <?php else: ?>
        <p style="color:var(--tekst-zacht)">Nog geen code ingesteld. Vraag een bestuurslid om er een aan te maken.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Aanwezigheidslijst -->
<div class="card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <div class="card-title">Aanwezigheid</div>
    <div style="display:flex;gap:8px;align-items:center">
      <input type="text" id="filter" placeholder="Zoek naam…" oninput="filterLijst(this.value)"
             style="font-size:13px;padding:7px 12px;border:1px solid var(--rand);border-radius:8px">
      <span class="badge badge-groen" id="tellerBadge"><?= $n_aanwezig ?>/<?= $n_totaal ?> aanwezig</span>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (!$kandidaten): ?>
      <p style="text-align:center;color:var(--tekst-zacht);padding:32px">Nog geen sollicitanten uitgenodigd voor de selectiedag.</p>
    <?php else: ?>
      <?php foreach ($per_groep as $groepnaam => $leden): ?>
      <div style="padding:8px 14px;background:var(--grijs-bg);font-size:12px;font-weight:600;color:var(--tekst-zacht)"><?= h($groepnaam) ?> (<?= count($leden) ?>)</div>
      <?php foreach ($leden as $k): ?>
      <div class="inchk-rij" data-naam="<?= h(mb_strtolower($k['naam'])) ?>">
        <span class="inchk-naam"><?= h($k['naam']) ?></span>
        <button class="aanw-knop <?= $k['aanwezig'] ? 'ja' : '' ?>" id="ak-<?= (int)$k['sol_id'] ?>"
                onclick="toggleAanwezig(<?= (int)$k['sol_id'] ?>)">
          <?= $k['aanwezig'] ? '✓ Aanwezig' : 'Afwezig' ?>
        </button>
      </div>
      <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
const CSRF = '<?= csrf_token() ?>';
let nAanwezig = <?= $n_aanwezig ?>;
const nTotaal = <?= $n_totaal ?>;

function toggleAanwezig(solId) {
  const knop = document.getElementById('ak-' + solId);
  const wordtAanwezig = !knop.classList.contains('ja');
  fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ actie:'aanwezig', sol_id:solId, aanwezig:wordtAanwezig?'1':'0', _csrf:CSRF }) })
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return;
      if (wordtAanwezig) { knop.classList.add('ja'); knop.textContent = '✓ Aanwezig'; nAanwezig++; }
      else { knop.classList.remove('ja'); knop.textContent = 'Afwezig'; nAanwezig--; }
      document.getElementById('tellerBadge').textContent = nAanwezig + '/' + nTotaal + ' aanwezig';
    });
}

function filterLijst(term) {
  term = term.trim().toLowerCase();
  document.querySelectorAll('.inchk-rij').forEach(rij => {
    rij.style.display = rij.dataset.naam.includes(term) ? '' : 'none';
  });
}

function kopieer() {
  const url = document.getElementById('url');
  navigator.clipboard.writeText(url.value);
}
</script>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

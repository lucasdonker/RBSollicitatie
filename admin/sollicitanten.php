<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Sollicitanten';
$actieve_nav  = 'sollicitanten';
require __DIR__ . '/layout_top.php';

$db      = db();
$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;

// ── Bulk actie verwerken ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_actie'])) {
    csrf_check();
    vereis_recht('sollicitanten', 'lezen');
    $ids    = array_map('intval', $_POST['ids'] ?? []);
    $actie  = $_POST['bulk_actie'];
    if (!empty($ids) && function_exists('fase_zetten_bulk')) {
        // Alle fase-overgangen via de centrale fase-functie (documenten, pakket en melding inbegrepen).
        // Bij meer dan 10 personen gaan de berichten via de wachtrij (cron).
        $bulk = [
            'uitnodigen_selectie' => ['uitgenodigd_selectie', ['aangemeld'],           'uitnodiging(en)'],
            'uitnodigen_tw'       => ['uitgenodigd_tw',       ['selectiedag_geweest'], 'TW-uitnodiging(en)'],
            'aangenomen'          => ['aangenomen',           null,                    'welkomstbericht(en)'],
            'afgewezen'           => ['afgewezen',            null,                    'bericht(en)'],
        ];
        if (isset($bulk[$actie])) {
            $opties = ['seizoen_id' => $sid];
            if ($bulk[$actie][1] !== null) $opties['alleen_van'] = $bulk[$actie][1];
            $t = fase_zetten_bulk($ids, $bulk[$actie][0], 'bulk', $opties);
            flash(($t['mislukt'] || $t['fout']) ? 'waarschuwing' : 'succes', fase_label($bulk[$actie][0]) . ': ' . fase_bulk_tekst($t, $bulk[$actie][2]));
        }
    }
    redirect(APP_URL . '/admin/sollicitanten.php');
}

// ── Individuele fase wisselen (formulier-terugval zonder JavaScript; POST + CSRF) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fase_wissel'], $_POST['id'], $_POST['naar'])) {
    csrf_check();
    vereis_recht('sollicitanten', 'bewerken');
    $id   = (int)$_POST['id'];
    $naar = (string)$_POST['naar'];
    if (function_exists('fase_zetten') && isset(fasen()[$naar])) {
        $res   = fase_zetten($id, $naar, 'lijst', ['seizoen_id' => $sid]);
        $label = fase_label($naar);
        if (!$res['ok'])           flash('fout', 'Fase wijzigen mislukt: ' . ($res['fout'] ?? 'onbekend'));
        elseif ($res['ongewijzigd']) flash('succes', "Fase was al '$label'.");
        else {
            $mt = fase_melding_tekst($res);
            $slecht = in_array($res['melding']['status'] ?? '', ['mislukt'], true);
            flash($slecht ? 'waarschuwing' : 'succes', "Fase bijgewerkt naar '$label'." . ($mt ? ' ' . $mt : ''));
        }
    } else {
        flash('fout', 'Ongeldige fase.');
    }
    redirect(APP_URL . '/admin/sollicitanten.php');
}

// ── Filters & query ───────────────────────────────────────────
$fase_filter  = $_GET['fase']  ?? 'alle';
$filter_terug = $_GET['terug'] ?? '';
$filter_blk   = $_GET['blk']   ?? '';
$filter_comp  = $_GET['comp']  ?? '';
$zoek         = trim($_GET['q'] ?? '');

$ant  = [];
$stmt = $db->prepare("SELECT fase, COUNT(*) n FROM sollicitaties WHERE seizoen_id=? GROUP BY fase");
$stmt->execute([$sid]);
foreach ($stmt->fetchAll() as $r) $ant[$r['fase']] = (int)$r['n'];
$ant['alle'] = array_sum($ant);

$where  = "s.seizoen_id = ?";
$params = [$sid];
if ($fase_filter !== 'alle') { $where .= " AND s.fase = ?";   $params[] = $fase_filter; }
if ($filter_terug === '1')   { $where .= " AND s.terugkerend = 1"; }
if ($filter_blk === 'ja')    { $where .= " AND EXISTS (SELECT 1 FROM blk_formulieren bf_ja  WHERE bf_ja.sollicitatie_id=s.id)"; }
if ($filter_blk === 'nee')   { $where .= " AND NOT EXISTS (SELECT 1 FROM blk_formulieren bf_nee WHERE bf_nee.sollicitatie_id=s.id)"; }
if ($filter_comp !== '')     { $where .= " AND EXISTS (SELECT 1 FROM competenties cmp WHERE cmp.sollicitatie_id=s.id AND cmp.categorie=?)"; $params[] = $filter_comp; }
if ($zoek) {
    $where .= " AND (p.voornaam LIKE ? OR p.achternaam LIKE ? OR p.email LIKE ?)";
    $params[] = "%$zoek%"; $params[] = "%$zoek%"; $params[] = "%$zoek%";
}

// Paginatie
$per_pagina = (int)($_GET['per_pagina'] ?? 25);
if (!in_array($per_pagina, [15, 25, 50, 100], true)) $per_pagina = 25;
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

// Totaal voor paginatie
$totaal_stmt = $db->prepare("SELECT COUNT(*) FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE $where");
$totaal_stmt->execute($params);
$totaal_sol = (int)$totaal_stmt->fetchColumn();
$totaal_pag = max(1, (int)ceil($totaal_sol / $per_pagina));
$pagina     = min($pagina, $totaal_pag);
$offset     = ($pagina - 1) * $per_pagina;

$stmt = $db->prepare("
    SELECT s.id, s.fase, s.terugkerend, s.aangemeld_op, s.persoon_id,
           p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.geboortedatum,
           b.foto_url
    FROM sollicitaties s
    JOIN personen p ON p.id=s.persoon_id
    LEFT JOIN blk_formulieren b ON b.sollicitatie_id=s.id
    WHERE $where ORDER BY s.aangemeld_op DESC
    LIMIT $per_pagina OFFSET $offset
");
$stmt->execute($params);
$soll = $stmt->fetchAll();

$tabs = ['alle'=>'Alle','aangemeld'=>'Aangemeld','uitgenodigd_selectie'=>'Uitgenodigd SD','aangemeld_selectie'=>'Aangemeld SD','selectiedag_geweest'=>'SD geweest','uitgenodigd_tw'=>'Uitgenodigd TW','tw_geweest'=>'TW geweest','aangenomen'=>'Aangenomen','afgewezen'=>'Afgewezen'];
$akleuren = ['#002147','#003570','#1D9E75','#805ad5','#e55a25','#2c7a7b'];

function filter_url(string $fase, string $zoek, string $terug, string $blk, string $comp = ''): string {
    $p = ['fase' => $fase];
    if ($zoek)  $p['q']     = $zoek;
    if ($terug) $p['terug'] = $terug;
    if ($blk)   $p['blk']   = $blk;
    if ($comp)  $p['comp']  = $comp;
    return '?' . http_build_query($p);
}
?>

<div class="fase-tabs">
  <?php foreach ($tabs as $f => $l): ?>
  <a href="<?= h(filter_url($f, $zoek, $filter_terug, $filter_blk)) ?>"
     class="fase-tab <?= $fase_filter === $f ? 'actief' : '' ?>">
    <?= h($l) ?> <span class="count"><?= $ant[$f] ?? 0 ?></span>
  </a>
  <?php endforeach; ?>
</div>

<div class="bulk-balk" id="bulkBalk">
  <div class="info"><strong id="bulkAantal">0</strong> geselecteerd</div>
  <div id="bulkKnoppen"></div>
  <button type="button" class="btn btn-outline btn-sm"
          style="color:#fff;border-color:rgba(255,255,255,.3)" onclick="wisSelectie()">✕</button>
</div>

<form method="POST" id="bulkForm">
<?= csrf_input() ?>
<input type="hidden" name="bulk_actie" id="bulkActieVeld">

<div class="card" style="margin-bottom:14px">
  <div class="card-body" style="padding:12px 16px">
    <div class="filter-balk">
      <a href="<?= APP_URL ?>/admin/sollicitant_nieuw.php" class="btn btn-groen btn-sm">+ Toevoegen</a>
      <?php if (!empty($sid)): ?>
      <a href="<?= APP_URL ?>/admin/export_blk.php?formaat=excel&seizoen=<?= (int)$sid ?>" class="btn btn-outline btn-sm">📊 Export alle (Excel)</a>
      <?php endif; ?>
      <div class="zoek-input">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="zoekInput" placeholder="Zoek naam of e-mail…" value="<?= h($zoek) ?>">
      </div>
      <select id="filterTerug" onchange="pasFilterAan()"
              style="width:auto;font-size:12px;padding:5px 9px;border-radius:6px">
        <option value=""  <?= !$filter_terug       ? 'selected' : '' ?>>Alle kandidaten</option>
        <option value="1" <?= $filter_terug === '1' ? 'selected' : '' ?>>↩ Alleen terugkerend</option>
      </select>
      <select id="filterBlk" onchange="pasFilterAan()"
              style="width:auto;font-size:12px;padding:5px 9px;border-radius:6px">
        <option value=""    <?= !$filter_blk          ? 'selected' : '' ?>>BLK: alle</option>
        <option value="ja"  <?= $filter_blk === 'ja'  ? 'selected' : '' ?>>BLK: ingevuld</option>
        <option value="nee" <?= $filter_blk === 'nee' ? 'selected' : '' ?>>BLK: niet ingevuld</option>
      </select>
      <?php if (function_exists('competentie_sync_klaar') && competentie_sync_klaar()): ?>
      <select id="filterComp" onchange="pasFilterAan()" style="width:auto;font-size:12px;padding:5px 9px;border-radius:6px">
        <option value="">Competentie: alle</option>
        <?php foreach (competentie_mapping() as $cat => $def): ?>
        <option value="<?= h($cat) ?>" <?= $filter_comp === $cat ? 'selected' : '' ?>><?= h($def['icoon'] ?? '') ?> <?= h($def['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <?php if ($fase_filter !== 'alle' || $filter_terug || $filter_blk || $filter_comp): ?>
      <a href="?" style="font-size:12px;color:var(--oranje)">✕ Wis filters</a>
      <?php endif; ?>
    </div>
    <div style="font-size:12px;color:var(--grijs);margin-top:7px">
      <?= count($soll) ?> sollicitant(en)
    </div>
  </div>
</div>

<div class="card">
  <div style="padding:8px 14px;border-bottom:1px solid var(--rand);display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:var(--grijs-bg)">
    <div style="position:relative">
      <button type="button" id="kolomKnop" onclick="document.getElementById('kolomMenu').classList.toggle('open')"
              class="btn btn-outline btn-sm" style="display:inline-flex;align-items:center;gap:4px" title="Kolommen kiezen">
        ⚙️ Kolommen
      </button>
      <div id="kolomMenu" class="kolom-dropdown" style="display:none;position:absolute;top:100%;left:0;margin-top:4px;background:var(--bg-card);border:1px solid var(--rand);border-radius:8px;padding:10px;min-width:180px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:50">
        <div style="font-size:11px;font-weight:600;color:var(--tekst-zacht);margin-bottom:6px;text-transform:uppercase">Toon kolommen</div>
        <?php
        $extra_cols = ['telefoon'=>'Telefoon','geboortedatum'=>'Leeftijd','blk_status'=>'BLK','terugkerend_col'=>'Terugkerend','foto_col'=>'Foto'];
        foreach ($extra_cols as $kol => $lbl): ?>
        <label style="font-size:13px;display:flex;align-items:center;gap:7px;cursor:pointer;user-select:none;padding:4px 0">
          <input type="checkbox" class="kol-toggle" data-kol="<?= h($kol) ?>" style="width:auto"> <?= h($lbl) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <span id="kolomLabel" style="font-size:11px;color:var(--tekst-zacht)"></span>
  </div>
  <div class="tabel-wrap">
    <table class="data-tabel soll-tabel">
      <thead>
        <tr>
          <th style="width:36px">
            <input type="checkbox" id="selecteerAlle" onchange="toggleAlle(this.checked)">
          </th>
          <th>Naam</th>
          <th class="col-sm-hide">Aangemeld</th>
          <th class="kol-telefoon" style="display:none">Telefoon</th>
          <th class="kol-geboortedatum" style="display:none">Leeftijd</th>
          <th class="kol-blk_status" style="display:none">BLK</th>
          <th class="kol-terugkerend_col" style="display:none">Terug</th>
          <th>Status</th>
          <th style="width:180px">Acties</th>
        </tr>
      </thead>
      <tbody id="sollBody">
      <?php if (empty($soll)): ?>
        <tr>
          <td colspan="5">
            <div class="leeg-scherm">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
              </svg>
              <p>Geen sollicitanten gevonden<?= $zoek || $fase_filter !== 'alle' || $filter_terug || $filter_blk ? ' voor deze filters' : '' ?>.</p>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php
      // Pre-fetch: welke sollicitaties hebben BLK ingevuld
      $met_blk = [];
      if (!empty($soll)) {
          $sol_ids = implode(',', array_column($soll, 'id'));
          $blk_ids = db()->query("SELECT sollicitatie_id FROM blk_formulieren WHERE sollicitatie_id IN($sol_ids)")->fetchAll(\PDO::FETCH_COLUMN);
          $met_blk = $blk_ids;
      }
      ?>
      <?php foreach ($soll as $s):
          $naam = volledige_naam($s);
          $ini  = strtoupper(substr($s['voornaam'], 0, 1) . substr($s['achternaam'], 0, 1));
          $akl  = $akleuren[$s['persoon_id'] % count($akleuren)];
        ?>
        <tr data-id="<?= $s['id'] ?>" data-fase="<?= h($s['fase']) ?>"
            class="soll-rij" style="cursor:pointer" onclick="openPaneel(<?= $s['id'] ?>)">

          <td onclick="event.stopPropagation()">
            <input type="checkbox" name="ids[]" value="<?= $s['id'] ?>"
                   class="soll-cb" onchange="updateBulk()">
          </td>

          <td>
            <div class="naam-cel">
              <?php if (!empty($s['foto_url'])): ?>
              <img src="<?= h(foto_url($s['foto_url'])) ?>" alt="" class="avatar avatar-foto"
                   onclick="event.stopPropagation();fotoVergroten('<?= h(foto_url($s['foto_url'])) ?>','<?= h($naam) ?>')"
                   style="width:36px;height:36px;border-radius:50%;object-fit:cover;cursor:zoom-in;border:2px solid var(--rand)">
              <?php else: ?>
              <div class="avatar" style="background:<?= $akl ?>"><?= h($ini) ?></div>
              <?php endif; ?>
              <div>
                <div class="naam"><?= h($naam) ?>
                <?php
                // Auto-invite suggestie badge
                if (function_exists('invite_aanbeveling') && in_array($s['fase'], ['aangemeld'])) {
                  $lft_s = leeftijd_op_datum($s['geboortedatum'], $seizoen['datum_werk_start'] ?? date('Y').'-06-01');
                  $inv = invite_aanbeveling($lft_s);
                  if ($inv['voldoet'] && $inv['modus'] !== 'uit') {
                    echo '<span class="badge badge-groen" style="font-size:9px;margin-left:4px" title="' . h($inv['label']) . '">✓ Uitnodigen</span>';
                  }
                }
                ?>
              </div>
                <div class="email">
                  <?= h($s['email']) ?>
                  <?php if ($s['terugkerend']): ?>
                  <span class="badge badge-terugkerend" style="font-size:10px">↩</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </td>

          <td class="col-sm-hide" style="white-space:nowrap;font-size:13px"><?= datum_nl($s['aangemeld_op']) ?></td>
          <td class="kol-telefoon" style="display:none;font-size:12px"><?= h($s['telefoon'] ?? '—') ?></td>
          <td class="kol-geboortedatum" style="display:none;font-size:12px"><?= leeftijd($s['geboortedatum']) ?></td>
          <td class="kol-blk_status" style="display:none;font-size:12px">
            <?= in_array($s['id'], $met_blk) ? '<span style="color:var(--groen)">✓</span>' : '<span style="color:var(--grijs)">—</span>' ?>
          </td>
          <td class="kol-terugkerend_col" style="display:none;font-size:12px">
            <?= $s['terugkerend'] ? '<span class="badge badge-terugkerend">↩</span>' : '—' ?>
          </td>

          <td>
            <span class="badge badge-<?= h(fase_kleur($s['fase'])) ?>">
              <?= h(fase_label($s['fase'])) ?>
            </span>
          </td>

          <!-- Actie-kolom — altijd gevuld, bijgewerkt via JS na AJAX -->
          <td class="actie-kolom" onclick="event.stopPropagation()"
              style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;padding:8px 12px">
            <?php if ($s['fase'] === 'aangemeld'): ?>
            <button type="button" class="btn btn-groen btn-sm"
                    onclick="enkeleActie(<?= $s['id'] ?>, 'uitnodigen_selectie')">
              Uitnodigen SD
            </button>
            <?php elseif ($s['fase'] === 'selectiedag_geweest'): ?>
            <button type="button" class="btn btn-primary btn-sm"
                    onclick="enkeleActie(<?= $s['id'] ?>, 'uitnodigen_tw')">
              Uitnodigen TW
            </button>
            <?php elseif ($s['fase'] === 'aangenomen'): ?>
            <span style="font-size:11px;color:var(--groen);font-weight:600">✓ Aangenomen</span>
            <?php elseif ($s['fase'] === 'afgewezen'): ?>
            <span style="font-size:11px;color:var(--rood)">✕ Afgewezen</span>
            <?php endif; ?>
            <button type="button" class="btn btn-outline btn-sm"
                    onclick="openPaneel(<?= $s['id'] ?>)" title="Open dossier">
              ···
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginatie + aantal per pagina -->
  <?php if ($totaal_sol > 0): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 18px;border-top:1px solid var(--rand);flex-wrap:wrap">
    <div style="font-size:13px;color:var(--tekst-zacht)">
      <?= ($offset + 1) ?>–<?= min($offset + $per_pagina, $totaal_sol) ?> van <?= $totaal_sol ?>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <label style="font-size:12px;color:var(--tekst-zacht)">Per pagina:
        <select onchange="window.location='?per_pagina='+this.value+'<?= $_GET ? '&'.http_build_query(array_diff_key($_GET, ['per_pagina'=>1,'pagina'=>1])) : '' ?>'"
                style="font-size:12px;padding:3px 8px;border:1px solid var(--rand);border-radius:4px;background:var(--bg-card);color:var(--tekst);margin-left:4px">
          <?php foreach ([15,25,50,100] as $n): ?>
          <option value="<?= $n ?>" <?= $per_pagina===$n?'selected':'' ?>><?= $n ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php if ($totaal_pag > 1):
        $base_q = array_diff_key($_GET, ['pagina'=>1]);
        $base   = '?' . http_build_query($base_q) . ($base_q ? '&' : '') . 'pagina=';
      ?>
      <div style="display:flex;gap:4px">
        <?php
        if ($pagina > 1) echo '<a href="'.$base.($pagina-1).'" class="btn btn-outline btn-sm">←</a>';
        echo '<span style="padding:5px 10px;font-size:13px;color:var(--tekst-zacht)">'.$pagina.' / '.$totaal_pag.'</span>';
        if ($pagina < $totaal_pag) echo '<a href="'.$base.($pagina+1).'" class="btn btn-outline btn-sm">→</a>';
        ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
</form>

<!-- Foto vergroten modaal -->
<div id="fotoModal" onclick="this.style.display='none'"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center;padding:40px;cursor:zoom-out">
  <div style="max-width:90vw;max-height:90vh;text-align:center">
    <img id="fotoModalImg" src="" alt=""
         style="max-width:100%;max-height:80vh;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.5)">
    <div id="fotoModalCaption" style="color:#fff;margin-top:12px;font-size:14px"></div>
    <div style="color:rgba(255,255,255,.5);font-size:11px;margin-top:4px">Klik om te sluiten</div>
  </div>
</div>

<script>
// Zoeken (client-side)
document.getElementById('zoekInput').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.soll-rij').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// Kolom toggle
function updateKolomLabel() {
  const aan = document.querySelectorAll('.kol-toggle:checked').length;
  const lbl = document.getElementById('kolomLabel');
  if (lbl) lbl.textContent = aan === 0 ? '(standaard)' : aan + ' extra';
}
document.querySelectorAll('.kol-toggle').forEach(cb => {
  const kol = cb.dataset.kol;
  // Herstel opgeslagen staat
  if (localStorage.getItem('kol_' + kol) === '1') {
    cb.checked = true;
    document.querySelectorAll('.kol-' + kol).forEach(el => el.style.display = '');
  }
  cb.addEventListener('change', function() {
    document.querySelectorAll('.kol-' + kol).forEach(el => el.style.display = this.checked ? '' : 'none');
    localStorage.setItem('kol_' + kol, this.checked ? '1' : '0');
    updateKolomLabel();
  });
});
updateKolomLabel();

// Sluit dropdown bij klik buiten
document.addEventListener('click', function(e) {
  const menu = document.getElementById('kolomMenu');
  const knop = document.getElementById('kolomKnop');
  if (menu && menu.classList.contains('open') && !menu.contains(e.target) && e.target !== knop) {
    menu.classList.remove('open');
  }
});

// CSS toggle voor open state
document.addEventListener('DOMContentLoaded', function(){
  const menu = document.getElementById('kolomMenu');
  if (menu) {
    const obs = new MutationObserver(() => menu.style.display = menu.classList.contains('open') ? '' : 'none');
    obs.observe(menu, {attributes:true, attributeFilter:['class']});
  }
});

// Filter dropdowns
function pasFilterAan() {
  const fase  = new URLSearchParams(window.location.search).get('fase') || 'alle';
  const terug = document.getElementById('filterTerug').value;
  const blk   = document.getElementById('filterBlk').value;
  const comp  = document.getElementById('filterComp') ? document.getElementById('filterComp').value : '';
  const zoek  = document.getElementById('zoekInput').value;
  const p     = new URLSearchParams({ fase });
  if (terug) p.set('terug', terug);
  if (blk)   p.set('blk', blk);
  if (comp)  p.set('comp', comp);
  if (zoek)  p.set('q', zoek);
  window.location.href = '?' + p.toString();
}

// Alles selecteren
function toggleAlle(c) {
  document.querySelectorAll('.soll-rij:not([style*="display: none"]) .soll-cb').forEach(cb => { cb.checked = c; });
  updateBulk();
}

// Bulk balk bijwerken
function updateBulk() {
  const sel = [...document.querySelectorAll('.soll-cb:checked')];
  document.getElementById('bulkAantal').textContent = sel.length;
  const balk = document.getElementById('bulkBalk');
  if (sel.length > 0) {
    balk.classList.add('zichtbaar');
    const fases = sel.map(cb => cb.closest('tr').dataset.fase);
    let html = '';
    if (fases.some(f => f === 'aangemeld'))
      html += `<button type="button" class="btn btn-groen btn-sm" onclick="bulkActie('uitnodigen_selectie')">📧 Uitnodigen SD</button> `;
    if (fases.some(f => f === 'selectiedag_geweest'))
      html += `<button type="button" class="btn btn-primary btn-sm" onclick="bulkActie('uitnodigen_tw')">📧 Uitnodigen TW</button> `;
    html += `<button type="button" class="btn btn-groen btn-sm" onclick="bulkActie('aangenomen')">✓ Aannemen</button> `;
    html += `<button type="button" class="btn btn-rood btn-sm" onclick="bulkActie('afgewezen')">✕ Afwijzen</button> `;
    html += `<button type="button" class="btn btn-outline btn-sm" style="color:#fff;border-color:rgba(255,255,255,.3)" onclick="bulkExport()">📊 Export Excel</button>`;
    document.getElementById('bulkKnoppen').innerHTML = html;
  } else {
    balk.classList.remove('zichtbaar');
  }
}

function wisSelectie() {
  document.querySelectorAll('.soll-cb').forEach(cb => { cb.checked = false; });
  document.getElementById('selecteerAlle').checked = false;
  updateBulk();
}

function bulkExport() {
  const ids = [...document.querySelectorAll('.soll-cb:checked')].map(cb => cb.value).filter(Boolean);
  if (!ids.length) return;
  // Open de Excel-export met de geselecteerde sollicitanten
  window.location.href = '<?= APP_URL ?>/admin/export_blk.php?formaat=excel&ids=' + ids.join(',');
}

function bulkActie(actie) {
  const labels = {
    uitnodigen_selectie: { tekst: 'uitnodigen voor selectiedag', mail: true },
    uitnodigen_tw:       { tekst: 'uitnodigen voor trainingsweekend', mail: true },
    aangenomen:          { tekst: 'aannemen', mail: false },
    afgewezen:           { tekst: 'afwijzen', mail: false }
  };
  const cfg = labels[actie] || { tekst: actie, mail: false };
  const n = document.querySelectorAll('.soll-cb:checked').length;
  let bevestiging = n + ' persoon/personen ' + cfg.tekst + '?';
  if (cfg.mail) bevestiging += '\n\n⚠ Er wordt automatisch een uitnodigingsmail verstuurd.';
  if (!confirm(bevestiging)) return;
  document.getElementById('bulkActieVeld').value = actie;
  document.getElementById('bulkForm').submit();
}

function enkeleActie(id, actie) {
  const cb = document.querySelector('.soll-rij[data-id="' + id + '"] .soll-cb');
  document.querySelectorAll('.soll-cb').forEach(c => { c.checked = false; });
  if (cb) cb.checked = true;
  bulkActie(actie);
}

<?php if (isset($_GET['panel'])): ?>
// Auto-open vanuit redirect
openPaneel(<?= (int)$_GET['panel'] ?>);
<?php endif; ?>
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>
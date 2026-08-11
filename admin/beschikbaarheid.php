<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Beschikbaarheid';
$actieve_nav  = 'beschikbaarheid';

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
$maand_kort = [1=>'jan',2=>'feb',3=>'mrt',4=>'apr',5=>'mei',6=>'jun',7=>'jul',8=>'aug',9=>'sep',10=>'okt',11=>'nov',12=>'dec'];

$weken = [];
$dag = new DateTime($werk_start);
$eind_dag = new DateTime($werk_eind);
while ($dag->format('N') != 1) $dag->modify('-1 day');
while ($dag <= $eind_dag) {
    $wn = (int)$dag->format('W');
    $zo = (clone $dag)->modify('+6 days');
    $weken[$wn] = [
        'wn'       => $wn,
        'kort'     => (int)$dag->format('j') . ' ' . $maand_kort[(int)$dag->format('n')],
        'eind'     => (int)$zo->format('j') . ' ' . $maand_kort[(int)$zo->format('n')],
        'weektype' => function_exists('seizoen_weektype') ? seizoen_weektype($seizoen ?? [], $wn) : 'hoofd',
    ];
    $dag->modify('+7 days');
}

// ── Sollicitanten met BLK (niet afgewezen) ───────────────────
$lijst = [];
if ($sid) {
    $q = $db->prepare("
        SELECT s.id sol_id, s.fase, s.terugkerend,
               p.voornaam, p.tussenvoegsel, p.achternaam,
               bsch.weken_json,
               prof.norm_dagen
        FROM sollicitaties s
        JOIN personen p ON p.id = s.persoon_id
        JOIN blk_formulieren bl ON bl.sollicitatie_id = s.id
        LEFT JOIN beschikbaarheid bsch ON bsch.sollicitatie_id = s.id
        LEFT JOIN beschikbaarheid_profiel prof ON prof.sollicitatie_id = s.id
        WHERE s.seizoen_id = ? AND s.fase NOT IN ('afgewezen')
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

    $telling = 0;
    foreach ($weken as $wn => $_) {
        if ($heeft_b2) {
            if (!isset($b2[$wn])) continue;
            $w = $b2[$wn];
            $dagen = $w['dagen_max'];
            $weekend = count(array_intersect($w['dagen'], [5,6,7])) > 0;
            $bijz = ($w['regime'] ?? 'vijf') === 'weekend' ? 'weekend' : ($dagen < 5 ? $dagen . 'd' : null);
            if (!empty($w['correctie'])) $bijz = $w['correctie']['soort']==='niet' ? 'correctie' : ($dagen . 'd');
        } else {
            if (!in_array($wn, $oude_weken, true)) continue;
            $dagen = 5; $weekend = true; $bijz = null;
        }
        $eff[$sol_id][$wn] = ['dagen'=>$dagen, 'weekend'=>$weekend, 'bijz'=>$bijz];
        $tot_per_week[$wn]['aantal']++;
        if ($weekend) $tot_per_week[$wn]['weekend']++;
        $telling++;
    }
    $weken_count[$sol_id] = $telling;
}

require __DIR__ . '/layout_top.php';
?>

<style>
.besch-wrap { overflow-x:auto; border:1px solid var(--rand); border-radius:10px; background:var(--bg-card); }
.besch-matrix { border-collapse:separate; border-spacing:0; font-size:12px; min-width:100%; }
.besch-matrix th, .besch-matrix td { padding:0; text-align:center; }
.besch-matrix thead th { position:sticky; top:0; z-index:2; background:var(--bg-card); border-bottom:2px solid var(--rand); padding:6px 4px; font-weight:600; }
.besch-matrix .naam-kol { position:sticky; left:0; z-index:1; background:var(--bg-card); text-align:left; padding:7px 12px; min-width:170px; border-right:2px solid var(--rand); border-bottom:1px solid var(--rand-licht); font-weight:500; white-space:nowrap; }
.besch-matrix thead .naam-kol { z-index:3; }
.besch-matrix .wk-kop { min-width:52px; font-size:11px; line-height:1.3; }
.besch-matrix .wk-kop .wk-nr { font-weight:700; }
.besch-matrix .wk-kop .wk-dat { font-weight:400; color:var(--grijs); font-size:10px; }
.besch-matrix .wk-kop.voor, .besch-matrix .wk-kop.na { background:var(--blauw-bg); }
.besch-matrix tbody td.cel { border-bottom:1px solid var(--rand-licht); border-right:1px solid var(--rand-licht); cursor:pointer; height:34px; }
.cel-vol    { background:var(--groen-bg); color:var(--groen); font-weight:600; }
.cel-week   { background:#fff7ed; color:#c05a1a; font-weight:600; }
.cel-anders { background:#fef9c3; color:#854d0e; font-weight:600; }
.cel-leeg   { background:transparent; }
.cel:hover  { outline:2px solid var(--oranje); outline-offset:-2px; }
.tot-rij td { border-top:2px solid var(--rand); font-weight:600; padding:6px 4px; background:var(--grijs-bg); }
.tot-rij .wknd-laag { color:var(--rood); }
.tot-rij .wknd-ok { color:var(--groen); }
.besch-legenda { display:flex; gap:16px; flex-wrap:wrap; font-size:12px; margin:12px 2px; color:var(--tekst-zacht); align-items:center; }
.besch-legenda span { display:inline-flex; align-items:center; gap:5px; }
.besch-legenda i { width:14px; height:14px; border-radius:3px; display:inline-block; }
</style>

<div style="margin-bottom:14px">
  <p style="font-size:13px;color:var(--tekst-zacht);margin:0">
    De beschikbaarheid die sollicitanten bij hun aanmelding (BLK) hebben opgegeven. Klik op een cel om de dagen van die week te bekijken of een verhindering te markeren.
    <?php if (!$seizoen || (empty($seizoen['hoofdseizoen_start_week']) && empty($seizoen['hoofdseizoen_eind_week']))): ?>
      <br><span style="color:var(--oranje)">Tip: stel bij <a href="<?= APP_URL ?>/admin/seizoen.php" style="color:var(--oranje)">Seizoen</a> het hoofdseizoen in — dan tonen we voor-/naseizoen (weekend-focus) apart.</span>
    <?php endif; ?>
  </p>
</div>

<?php
// Bestuursoverzicht: wie staat nog op voorlopig?
$voorlopigen = function_exists('beschikbaarheid2_voorlopigen') && $sid ? beschikbaarheid2_voorlopigen($sid) : [];
$besch_deadline = function_exists('beschikbaarheid2_deadline') ? beschikbaarheid2_deadline($seizoen) : null;
if ($voorlopigen):
  $dgn_tot = $besch_deadline ? (int)floor(($besch_deadline->getTimestamp() - strtotime('today')) / 86400) : null;
?>
<div class="card" style="margin-bottom:14px;border-left:4px solid var(--oranje)">
  <div class="card-body" style="padding:12px 16px">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">
      <span style="font-size:15px;font-weight:600">⏳ Nog voorlopig: <?= count($voorlopigen) ?> <?= count($voorlopigen)===1?'persoon':'personen' ?></span>
      <?php if ($besch_deadline): ?>
        <span style="font-size:12px;color:<?= ($dgn_tot!==null && $dgn_tot<=7)?'var(--rood)':'var(--tekst-zacht)' ?>">
          · deadline <?= h(datum_nl($besch_deadline->format('Y-m-d'))) ?>
          <?php if ($dgn_tot !== null): ?>(<?= $dgn_tot < 0 ? 'verstreken' : 'nog ' . $dgn_tot . ' ' . ($dgn_tot===1?'dag':'dagen') ?>)<?php endif; ?>
        </span>
      <?php endif; ?>
    </div>
    <div style="font-size:12px;color:var(--tekst-zacht);margin-bottom:8px">Deze mensen krijgen automatisch herinneringen vanaf 2 weken vóór de deadline.</div>
    <div style="display:flex;flex-wrap:wrap;gap:6px">
      <?php foreach ($voorlopigen as $v): $naam = trim($v['voornaam'] . ' ' . ($v['tussenvoegsel'] ? $v['tussenvoegsel'] . ' ' : '') . $v['achternaam']); ?>
      <span style="display:inline-flex;align-items:center;gap:6px;background:var(--oranje-bg);border:1px solid var(--oranje);border-radius:20px;padding:4px 12px;font-size:12.5px">
        <?= h($naam) ?>
        <?php if (!empty($v['email'])): ?><a href="mailto:<?= h($v['email']) ?>" style="color:var(--oranje);text-decoration:none" title="Mail sturen">✉️</a><?php endif; ?>
      </span>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
// Centraal overzicht: alle openstaande verzoeken (goedkeuren/afwijzen op één plek)
$open_verzoeken = function_exists('beschikbaarheid2_open_verzoeken') && $sid ? beschikbaarheid2_open_verzoeken($sid) : [];
$vz_wkd = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen) : [];
$vz_lbl = function($wn) use ($vz_wkd) { $wn=(int)$wn; return isset($vz_wkd[$wn]) ? 'W'.$wn.' ('.$vz_wkd[$wn]['start']->format('j M').')' : 'W'.$wn; };
if ($open_verzoeken):
?>
<div class="card" style="margin-bottom:14px;border-left:4px solid var(--blauw)">
  <div class="card-body" style="padding:12px 16px">
    <div style="font-size:15px;font-weight:600;margin-bottom:4px">⚖️ Openstaande verzoeken: <?= count($open_verzoeken) ?></div>
    <div style="font-size:12px;color:var(--tekst-zacht);margin-bottom:10px">Verzoeken om minder dagen te werken. Beoordeel ze hier of via het dossier van de sollicitant.</div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($open_verzoeken as $vz): $naam = trim($vz['voornaam'] . ' ' . ($vz['tussenvoegsel'] ? $vz['tussenvoegsel'] . ' ' : '') . $vz['achternaam']); ?>
      <div id="cvz-<?= (int)$vz['id'] ?>" style="padding:10px 12px;background:var(--bg);border:1px solid var(--rand);border-radius:8px">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
          <a href="<?= APP_URL ?>/admin/sollicitanten.php?panel=<?= (int)$vz['sol_id'] ?>" style="font-weight:600;color:var(--navy);text-decoration:none"><?= h($naam) ?></a>
          <span style="font-size:12.5px">— <strong><?= h($vz_lbl($vz['van_week'])) ?> t/m <?= h($vz_lbl($vz['tot_week'])) ?></strong>: <?= (int)$vz['dagen_aantal'] ?> dagen/week</span>
          <span style="flex:1"></span>
          <button type="button" class="btn btn-primary btn-sm" style="font-size:11px" onclick="verzoekBeslisCentraal(<?= (int)$vz['id'] ?>,'goedkeuren')">✓ Goedkeuren</button>
          <button type="button" class="btn btn-outline btn-sm" style="font-size:11px" onclick="verzoekBeslisCentraal(<?= (int)$vz['id'] ?>,'afwijzen')">✕ Afwijzen</button>
        </div>
        <?php if (!empty($vz['dagen_pool'])): ?>
          <div style="font-size:12px;color:var(--tekst-zacht)">Kan die week: <strong><?= implode(', ', array_map('dag_kort', $vz['dagen_pool'])) ?></strong></div>
        <?php endif; ?>
        <?php if (!empty($vz['toelichting'])): ?>
          <div style="font-size:12px;color:var(--tekst-zacht)">Reden: <?= h($vz['toelichting']) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
function verzoekBeslisCentraal(id, besluit) {
  if (besluit === 'afwijzen' && !confirm('Verzoek afwijzen?')) return;
  fetch(APP_URL + '/admin/api_verzoek.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ verzoek_id: id, besluit, _csrf: CSRF })
  })
  .then(r => r.json())
  .then(data => { if (!data.ok) { alert(data.fout || 'Fout'); return; }
    const el = document.getElementById('cvz-' + id);
    if (el) { el.style.transition='opacity .3s'; el.style.opacity='.4'; el.innerHTML = '<div style="font-size:12.5px;color:var(--tekst-zacht);padding:4px">' + (besluit==='goedkeuren'?'✓ goedgekeurd':'✕ afgewezen') + '</div>'; }
  })
  .catch(() => alert('Verbindingsfout'));
}
</script>
<?php endif; ?>

<?php if (empty($lijst)): ?>
  <div class="card"><div class="card-body" style="text-align:center;color:var(--tekst-zacht);padding:40px">
    Nog geen sollicitanten met een ingevuld BLK-formulier dit seizoen.
  </div></div>
<?php else: ?>

<div class="besch-legenda">
  <span><i style="background:var(--groen-bg);border:1px solid var(--groen)"></i> Volledige week</span>
  <span><i style="background:#fff7ed;border:1px solid #c05a1a"></i> Alleen weekend</span>
  <span><i style="background:#fef9c3;border:1px solid #854d0e"></i> Anders / in overleg</span>
  <span><i style="background:transparent;border:1px solid var(--rand)"></i> Niet beschikbaar</span>
  <span style="margin-left:auto">Cijfer = aantal inzetbare dagen</span>
</div>

<div class="besch-wrap">
  <table class="besch-matrix">
    <thead>
      <tr>
        <th class="naam-kol">Sollicitant (<?= count($lijst) ?>)</th>
        <th class="wkn-kol" title="Aantal weken beschikbaar" style="position:sticky;left:0;z-index:2;background:var(--bg-card);border-bottom:2px solid var(--rand);padding:6px 8px;font-weight:600;font-size:11px">wkn</th>
        <?php foreach ($weken as $wn => $w): ?>
        <th class="wk-kop <?= $w['weektype'] ?>" title="<?= $w['weektype']==='hoofd'?'Hoofdseizoen':($w['weektype']==='voor'?'Voorseizoen (weekend-focus)':'Naseizoen (weekend-focus)') ?>">
          <div class="wk-nr">W<?= $wn ?></div>
          <div class="wk-dat"><?= h($w['kort']) ?></div>
        </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lijst as $p):
        $sol_id = (int)$p['sol_id'];
        $naam = trim($p['voornaam'] . ' ' . ($p['tussenvoegsel'] ? $p['tussenvoegsel'] . ' ' : '') . $p['achternaam']);
      ?>
      <tr>
        <td class="naam-kol">
          <a href="<?= APP_URL ?>/admin/sollicitanten.php?panel=<?= $sol_id ?>" style="color:var(--navy);text-decoration:none"><?= h($naam) ?></a>
          <?php if ($p['terugkerend']): ?><span title="Terugkerend" style="font-size:10px">🔁</span><?php endif; ?>
          <?php if (!empty($p['norm_dagen'])): ?><span title="Afwijkende norm: <?= (int)$p['norm_dagen'] ?> dagen/week" style="font-size:9px;background:var(--blauw-bg);color:var(--blauw);padding:1px 5px;border-radius:4px;font-weight:600"><?= (int)$p['norm_dagen'] ?>d</span><?php endif; ?>
          <?php if (!empty($vaste_map[$sol_id])): ?><span title="Voorkeur vrij (planner beslist): <?= implode(', ', array_map('dag_kort', $vaste_map[$sol_id])) ?>" style="font-size:9px;background:#fef9c3;color:#854d0e;padding:1px 5px;border-radius:4px;font-weight:600">⭐<?= implode('', array_map('dag_kort', $vaste_map[$sol_id])) ?></span><?php endif; ?>
        </td>
        <td class="wkn-kol" style="position:sticky;left:0;z-index:1;background:var(--bg-card);text-align:center;font-weight:600;font-size:12px;color:var(--navy);border-right:1px solid var(--rand-licht)"><?= $weken_count[$sol_id] ?? 0 ?></td>
        <?php foreach ($weken as $wn => $w):
          $c = $eff[$sol_id][$wn] ?? null;
          $naam_js = h(addslashes($naam));
          if (!$c) { echo '<td class="cel cel-leeg" onclick="celDetail(' . $sol_id . ',' . $wn . ',\'' . $naam_js . '\')"></td>'; continue; }
          $klasse = $c['bijz'] === 'weekend' ? 'cel-week' : (str_starts_with((string)$c['bijz'], 'anders') ? 'cel-anders' : 'cel-vol');
          $tip = $c['bijz'] ? h($c['bijz']) : ($c['dagen'] . ' dagen');
        ?>
        <td class="cel <?= $klasse ?>" title="W<?= $wn ?> — <?= $tip ?>" onclick="celDetail(<?= $sol_id ?>,<?= $wn ?>,'<?= $naam_js ?>')">
          <?= $c['bijz'] === 'weekend' ? 'wk' : (int)$c['dagen'] ?>
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>

      <tr class="tot-rij">
        <td class="naam-kol" style="background:var(--grijs-bg)">Beschikbaar totaal</td>
        <td class="wkn-kol" style="background:var(--grijs-bg)"></td>
        <?php foreach ($weken as $wn => $w):
          $t = $tot_per_week[$wn];
          $weekend_kritisch = in_array($w['weektype'], ['voor','na'], true);
        ?>
        <td>
          <div><?= $t['aantal'] ?></div>
          <div style="font-size:10px;font-weight:500" class="<?= $weekend_kritisch && $t['weekend'] < 3 ? 'wknd-laag' : 'wknd-ok' ?>" title="Dekt weekend (vr/za/zo)">wk:<?= $t['weekend'] ?></div>
        </td>
        <?php endforeach; ?>
      </tr>
    </tbody>
  </table>
</div>

<div style="font-size:12px;color:var(--tekst-zacht);margin-top:10px">
  Onderste rij: aantal beschikbare mensen per week en hoeveel daarvan het <strong>weekend</strong> dekken.
  In voor- en naseizoen (blauw) kleurt het weekend-getal <span style="color:var(--rood)">rood</span> bij minder dan 3.
</div>

<div id="celModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000" onclick="if(event.target===this)this.style.display='none'">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--bg-card);border-radius:12px;padding:20px;min-width:300px;max-width:90vw;box-shadow:var(--shadow-lg)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <strong id="celModalTitel" style="font-size:15px;color:var(--navy)"></strong>
      <button type="button" onclick="document.getElementById('celModal').style.display='none'" style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--grijs)">✕</button>
    </div>
    <div style="font-size:12px;color:var(--tekst-zacht);margin-bottom:10px">Klik op een dag om een verhindering aan/uit te zetten (rood = kan niet).</div>
    <div id="celModalDagen" style="display:flex;gap:4px;justify-content:center"></div>
    <div style="font-size:11px;color:var(--grijs);margin-top:12px;text-align:center">Wijzigingen worden direct opgeslagen.</div>
  </div>
</div>

<script>
const DAG_NL = ['','ma','di','wo','do','vr','za','zo'];

// Cel-klik opent de bestuurs-correctie modal (gedeeld met het dossier-paneel)
function celDetail(solId, week, naam) {
  if (typeof correctieOpen === 'function') correctieOpen(solId, week);
}
</script>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

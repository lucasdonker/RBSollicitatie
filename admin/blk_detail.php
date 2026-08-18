<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'BLK Formulier';
$actieve_nav  = 'sollicitanten';
vereist_login();

$sol_id = (int)($_GET['id'] ?? 0);
if (!$sol_id) redirect(APP_URL . '/admin/sollicitanten.php');

$db = db();

try {
    $stmt = $db->prepare("
        SELECT s.*, p.voornaam, p.tussenvoegsel, p.achternaam, p.email,
               p.telefoon, p.geboortedatum, p.geslacht, sz.naam seizoen_naam
        FROM sollicitaties s
        JOIN personen p ON p.id = s.persoon_id
        JOIN seizoenen sz ON sz.id = s.seizoen_id
        WHERE s.id = ?
    ");
    $stmt->execute([$sol_id]);
    $sol = $stmt->fetch();
} catch (Exception $e) {
    die('Database fout: ' . h($e->getMessage()));
}

if (!$sol) redirect(APP_URL . '/admin/sollicitanten.php');

$blk = $db->prepare("SELECT * FROM blk_formulieren WHERE sollicitatie_id=?")->execute([$sol_id]) ? null : null;
$bq = $db->prepare("SELECT * FROM blk_formulieren WHERE sollicitatie_id=?");
$bq->execute([$sol_id]); $blk = $bq->fetch();

$besch_q = $db->prepare("SELECT * FROM beschikbaarheid WHERE sollicitatie_id=?");
$besch_q->execute([$sol_id]); $besch = $besch_q->fetch();

// AI analyse ophalen
$ai_tekst = null;
$ai_datum = null;
try {
    $ai_q = $db->prepare("SELECT ai_analyse, ai_analyse_op FROM blk_formulieren WHERE sollicitatie_id=?");
    $ai_q->execute([$sol_id]);
    $ai_row = $ai_q->fetch();
    if ($ai_row) { $ai_tekst = $ai_row['ai_analyse'] ?: null; $ai_datum = $ai_row['ai_analyse_op']; }
} catch (Exception $e) { /* kolom bestaat nog niet */ }

$weken_sol = $besch ? (json_decode($besch['weken_json'] ?? '[]', true) ?: []) : [];
$weken_def = $besch ? (json_decode($besch['definitief_json'] ?? '[]', true) ?: []) : [];

// AI-invoer van deze sollicitant (om terug te kunnen kijken wat er is ingevuld)
$ai_besch_logs = [];
try {
    $alq = $db->prepare("SELECT invoer, status, aangemaakt_op FROM ai_beschikbaarheid_log WHERE sollicitatie_id=? ORDER BY aangemaakt_op DESC LIMIT 10");
    $alq->execute([$sol_id]);
    $ai_besch_logs = $alq->fetchAll();
} catch (\Exception $e) { /* tabel bestaat nog niet of leeg */ }

// BLK beschikbaarheid weken (uit stap 4 BLK)
$blk_weken = [];
if ($blk) {
    $d = json_decode($blk['beschikbaarheid_intentie'] ?? '', true);
    if (is_array($d)) $blk_weken = $d;
}

// Wijzigingsgeschiedenis — veilig: table bestaat mogelijk nog niet
$wijzigingen = [];
try {
    $wq = $db->prepare("SELECT * FROM blk_wijzigingen WHERE sollicitatie_id=? ORDER BY gewijzigd_op DESC");
    $wq->execute([$sol_id]); $wijzigingen = $wq->fetchAll();
} catch (Exception $e) { /* tabel bestaat nog niet */ }

$naam = volledige_naam($sol);
$pagina_titel = 'BLK: ' . $naam;
require __DIR__ . '/layout_top.php';
?>
<style>
@media print { .sidebar,.topbar,.btn,.flash,.paneel-overlay,.detail-paneel{display:none!important} .main-content{margin:0!important} .page-body{padding:0!important} .card{box-shadow:none!important;break-inside:avoid} }
.blk-veld { margin-bottom:14px; }
.blk-label { font-size:11px;font-weight:600;color:var(--grijs);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px; }
.blk-waarde { font-size:14px;line-height:1.7;background:var(--grijs-bg);border-radius:6px;padding:10px 12px;white-space:pre-wrap; }
.blk-leeg { font-size:13px;color:var(--grijs);font-style:italic; }
</style>

<div style="max-width:860px">
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:14px">
  <div style="display:flex;gap:14px;align-items:flex-start;flex:1;min-width:0">
    <?php
      $ini   = strtoupper(substr($sol['voornaam'], 0, 1) . substr($sol['achternaam'] ?? '', 0, 1));
      $fotoU = (!empty($blk['foto_url'])) ? foto_url($blk['foto_url']) : '';
    ?>
    <?php if ($fotoU): ?>
      <img src="<?= h($fotoU) ?>" alt="<?= h($naam) ?>"
           data-foto="<?= h($fotoU) ?>" data-naam="<?= h($naam) ?>"
           onclick="fotoVergroten(this.dataset.foto, this.dataset.naam)"
           style="width:64px;height:64px;border-radius:50%;object-fit:cover;flex-shrink:0;cursor:zoom-in;border:2px solid var(--rand);box-shadow:var(--shadow)">
    <?php else: ?>
      <div style="width:64px;height:64px;border-radius:50%;background:var(--oranje);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:20px;flex-shrink:0">
        <?= h($ini) ?>
      </div>
    <?php endif; ?>
    <div style="flex:1;min-width:0">
      <a href="<?= APP_URL ?>/admin/sollicitanten.php" class="btn btn-outline btn-sm" style="margin-bottom:8px">← Terug naar sollicitanten</a>
      <h2 style="font-size:18px;color:var(--navy);margin-top:4px;word-break:break-word"><?= h($naam) ?></h2>
      <div style="font-size:13px;color:var(--tekst-zacht);word-break:break-word"><?= h($sol['email']) ?> — <?= h($sol['seizoen_naam']) ?></div>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span class="badge badge-<?= fase_kleur($sol['fase']) ?>"><?= fase_label($sol['fase']) ?></span>
    <?php if ($sol['terugkerend']): ?><span class="badge badge-terugkerend">↩ Terugkerend</span><?php endif; ?>
    <button onclick="window.print()" class="btn btn-outline btn-sm">🖨️ Afdrukken</button>
    <a href="<?= APP_URL ?>/admin/sollicitanten.php?panel=<?= $sol_id ?>" class="btn btn-outline btn-sm">Open in paneel</a>
  </div>
</div>

<?php if (!$blk): ?>
<div class="flash flash-info">Dit formulier is nog niet ingevuld door de sollicitant.</div>
<?php else: ?>

<!-- NAW + gegevens -->
<div class="card" style="margin-bottom:14px">
  <div class="card-header"><div class="card-title">👤 Persoonsgegevens</div></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
      <?php
      // AVG: begeleiders zien alleen woonplaats, niet volledig adres
      $bhd_rol_d = huidige_beheerder()['rol'] ?? 'sol-begeleider';
      $mag_adres = in_array($bhd_rol_d, ['admin','bestuur','sol-admin'], true);
      $adres_volledig = trim(($blk['straat'] ?? '') . ' ' . ($blk['huisnummer'] ?? '') . ', ' . ($blk['postcode'] ?? '') . ' ' . ($blk['woonplaats'] ?? ''), ' ,');
      if (trim($adres_volledig, ' ,') === '') $adres_volledig = $blk['adres'] ?? '';
      $adres_toon = $mag_adres ? ($adres_volledig ?: '—') : ($blk['woonplaats'] ?? '—');
      $adres_label = $mag_adres ? 'Woonadres' : 'Woonplaats';
      ?>
      <?php foreach ([
        'Naam'          => h($naam),
        'E-mail'        => '<a href="mailto:'.h($sol['email']).'">'.h($sol['email']).'</a>',
        'Telefoon'      => h($sol['telefoon'] ?: '—'),
        'Geboortedatum' => datum_nl($sol['geboortedatum']) . ($sol['geboortedatum'] ? ' ('.leeftijd($sol['geboortedatum']).')' : ''),
        $adres_label    => h($adres_toon ?: '—'),
        'Social media'  => h($blk['social_media'] ?: '—'),
      ] as $l => $v): ?>
      <div><div class="blk-label"><?= $l ?></div><div style="font-size:13px"><?= $v ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Achtergrond -->
<div class="card" style="margin-bottom:14px">
  <div class="card-header"><div class="card-title">🎓 Achtergrond</div></div>
  <div class="card-body">
    <?php foreach (['Opleiding'=>'opleiding',"Diploma's"=>'diplomas','Werkervaring'=>'werkervaring'] as $l=>$k): ?>
    <div class="blk-veld">
      <div class="blk-label"><?= $l ?></div>
      <?php if ($blk[$k]): ?><div class="blk-waarde"><?= h($blk[$k]) ?></div>
      <?php else: ?><div class="blk-leeg">Niet ingevuld</div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Motivatie -->
<div class="card" style="margin-bottom:14px">
  <div class="card-header"><div class="card-title">💬 Motivatie</div></div>
  <div class="card-body">
    <?php foreach (['Motivatie lifeguard'=>'motivatie_lifeguard','Strand motivatie'=>'motivatie_strand'] as $l=>$k): ?>
    <div class="blk-veld">
      <div class="blk-label"><?= $l ?></div>
      <?php if ($blk[$k]): ?><div class="blk-waarde"><?= h($blk[$k]) ?></div>
      <?php else: ?><div class="blk-leeg">Niet ingevuld</div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Beschikbaarheid -->
<div class="card" style="margin-bottom:14px">
  <div class="card-header"><div class="card-title">📅 Beschikbaarheid</div></div>
  <div class="card-body">
    <?php if (!empty($blk_weken)): ?>
    <div class="blk-veld">
      <div class="blk-label">Voorkeur weken (uit BLK — <?= count($blk_weken) ?> weken)</div>
      <div style="display:flex;flex-wrap:wrap;gap:5px">
        <?php foreach ($blk_weken as $w): ?><span class="badge badge-blauw" style="font-size:12px">Week <?= (int)$w ?></span><?php endforeach; ?>
      </div>
    </div>
    <?php elseif ($blk['beschikbaarheid_intentie']): ?>
    <div class="blk-veld">
      <div class="blk-label">Beschikbaarheid (vrije tekst)</div>
      <div class="blk-waarde"><?= h($blk['beschikbaarheid_intentie']) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($blk['beschikbaarheid_uitzonderingen']): ?>
    <div class="blk-veld">
      <div class="blk-label">Kan niet op</div>
      <div class="blk-waarde" style="background:#fffbeb;border:1px solid #fde68a">⚠️ <?= h($blk['beschikbaarheid_uitzonderingen']) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($weken_sol) || !empty($weken_def)): ?>
    <hr style="border:none;border-top:1px solid var(--rand);margin:12px 0">
    <?php if (!empty($weken_sol)): ?>
    <div class="blk-veld">
      <div class="blk-label">Definitief opgegeven door sollicitant (<?= count($weken_sol) ?> weken)</div>
      <div style="display:flex;flex-wrap:wrap;gap:4px">
        <?php foreach ($weken_sol as $w): ?><span class="badge badge-blauw">W<?= $w ?></span><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($weken_def)): ?>
    <div class="blk-veld">
      <div class="blk-label">Definitief ingepland door beheerder (<?= count($weken_def) ?> weken · <?= count($weken_def)*5 ?> werkdagen)</div>
      <div style="display:flex;flex-wrap:wrap;gap:4px">
        <?php foreach ($weken_def as $w): ?><span class="badge badge-oranje">W<?= $w ?></span><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($ai_besch_logs)): ?>
    <hr style="border:none;border-top:1px solid var(--rand);margin:12px 0">
    <div class="blk-veld">
      <div class="blk-label">AI-invoer beschikbaarheid <span style="font-weight:400;color:var(--tekst-zacht)">(wat de sollicitant zelf typte)</span></div>
      <?php foreach ($ai_besch_logs as $log): ?>
      <div style="background:var(--grijs-bg);border:1px solid var(--rand);border-radius:8px;padding:8px 10px;margin-bottom:6px">
        <div style="font-size:11px;color:var(--tekst-zacht);margin-bottom:3px">
          <?= h(date('j-n-Y H:i', strtotime($log['aangemaakt_op']))) ?>
          · <?= $log['status']==='ok' ? 'verwerkt' : h($log['status']) ?>
        </div>
        <div style="font-size:13px;white-space:pre-wrap"><?= h($log['invoer']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Bijzonderheden + foto -->
<div class="card" style="margin-bottom:14px">
  <div class="card-header"><div class="card-title">📎 Overig</div></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:start">
      <div>
        <?php if ($blk['bijzonderheden']): ?>
        <div class="blk-veld">
          <div class="blk-label">Bijzonderheden / medicatie</div>
          <div class="blk-waarde" style="background:#fff5f5;border:1px solid #feb2b2">⚠️ <?= h($blk['bijzonderheden']) ?></div>
        </div>
        <?php endif; ?>
        <div style="font-size:11px;color:var(--grijs);margin-top:8px">
          Ingevuld: <?= datum_nl(substr($blk['ingevuld_op'] ?? '', 0, 10)) ?>
          <?php if ($blk['bijgewerkt_op'] && substr($blk['bijgewerkt_op'],0,10) !== substr($blk['ingevuld_op']??'',0,10)): ?>
          · Bijgewerkt: <?= datum_nl(substr($blk['bijgewerkt_op'],0,10)) ?>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($blk['foto_url']): ?>
      <img src="<?= h(foto_url($blk['foto_url'])) ?>" alt="Foto"
           data-foto="<?= h(foto_url($blk['foto_url'])) ?>" data-naam="<?= h(volledige_naam($blk)) ?>"
           onclick="fotoVergroten(this.dataset.foto, this.dataset.naam)"
           style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:2px solid var(--rand);cursor:zoom-in"
           title="Klik voor groter">
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Wijzigingslog -->
<!-- AI Analyse sectie -->
<?php if ($ai_tekst || instelling('ai.api_key')): ?>
<div class="card" style="margin-bottom:14px">
  <div class="card-header">
    <div style="display:flex;align-items:center;gap:10px">
      <div class="card-title">🤖 AI Geschiktheidsanalyse</div>
      <span style="font-size:11px;color:var(--grijs);background:var(--grijs-bg);padding:2px 7px;border-radius:10px">Geanonimiseerd · AVG-conform</span>
    </div>
    <?php if ($blk): ?>
    <button type="button" class="btn btn-outline btn-sm"
            onclick="genereerAnalyse(<?= $sol_id ?>)">
      <?= $ai_tekst ? '↺ Heranalyse' : '⚡ Analyseer nu' ?>
    </button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php if ($ai_tekst): ?>
    <div style="background:linear-gradient(135deg,rgba(99,102,241,.06),rgba(168,85,247,.04));border:1px solid rgba(99,102,241,.15);border-radius:8px;padding:16px;line-height:1.8;font-size:14px;color:var(--tekst)">
      <?= function_exists('ai_render') ? ai_render($ai_tekst) : nl2br(h($ai_tekst)) ?>
    </div>
    <?php if ($ai_datum): ?>
    <div style="font-size:11px;color:var(--grijs);margin-top:8px">
      Analyse gegenereerd op <?= datum_nl(substr($ai_datum,0,10)) ?> <?= substr($ai_datum,11,5) ?>
      · Gebaseerd op BLK-formulier, geen persoonsgegevens verstuurd
    </div>
    <?php endif; ?>
    <?php elseif (!instelling('ai.api_key')): ?>
    <div class="flash flash-info">
      Stel een Gemini API key in via <a href="<?= APP_URL ?>/admin/instellingen.php?tab=mail" style="color:var(--oranje)">Instellingen → Mail & notificaties</a> om AI analyse te activeren.
    </div>
    <?php elseif (!$blk): ?>
    <p style="color:var(--grijs);font-size:13px">Analyse beschikbaar zodra het BLK-formulier is ingevuld.</p>
    <?php else: ?>
    <div style="text-align:center;padding:20px">
      <p style="color:var(--grijs);font-size:13px;margin-bottom:12px">Nog geen analyse gegenereerd.</p>
      <div id="ai-loading-<?= $sol_id ?>" style="display:none;color:var(--grijs);font-size:13px;margin-top:8px">
        ⏳ Analyse wordt gegenereerd via Gemini AI...
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($wijzigingen)): ?>
<div class="card" style="margin-bottom:14px">
  <div class="card-header"><div class="card-title">🕓 Wijzigingsgeschiedenis</div></div>
  <div style="padding:0">
    <?php foreach ($wijzigingen as $wij):
      $ch = json_decode($wij['wijzigingen_json'] ?? '[]', true) ?: []; ?>
    <div style="padding:11px 16px;border-bottom:1px solid var(--rand)">
      <div style="display:flex;justify-content:space-between;margin-bottom:5px">
        <strong style="font-size:13px">Reden: <?= h($wij['reden']) ?></strong>
        <span style="font-size:12px;color:var(--grijs)"><?= datum_nl(substr($wij['gewijzigd_op'],0,10)) ?> <?= substr($wij['gewijzigd_op'],11,5) ?></span>
      </div>
      <?php foreach ($ch as $c): ?>
      <div style="font-size:12px;background:var(--grijs-bg);border-radius:5px;padding:5px 9px;margin-bottom:3px">
        <strong><?= h($c['label'] ?? $c['veld'] ?? '') ?>:</strong>
        <span style="color:var(--rood);text-decoration:line-through;margin:0 4px"><?= h(mb_substr($c['oud'] ?? '—', 0, 60)) ?></span>
        → <span style="color:var(--groen);margin-left:4px"><?= h(mb_substr($c['nieuw'] ?? '—', 0, 60)) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</div>
<script>
// Op blk_detail.php herlaad na analyse de pagina zelf (geen paneel)
function genereerAnalyse(solId) {
  const btn = event?.target;
  const loadEl = document.getElementById('ai-loading-' + solId);
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Bezig...'; }
  if (loadEl) loadEl.style.display = '';

  fetch('<?= APP_URL ?>/admin/api_ai_analyse.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ sol_id: solId, _csrf: '<?= csrf_token() ?>' })
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      window.location.reload();
    } else {
      if (btn) { btn.disabled = false; btn.textContent = '⚡ Analyseer nu'; }
      if (loadEl) loadEl.style.display = 'none';
      alert(data.fout || 'Analyse mislukt. Controleer de API key in Instellingen.');
    }
  })
  .catch(() => {
    if (btn) { btn.disabled = false; }
    if (loadEl) loadEl.style.display = 'none';
    alert('Verbindingsfout.');
  });
}
</script>
<?php require __DIR__ . '/layout_bottom.php'; ?>
<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'BLK Formulier';
$actieve_nav  = 'sollicitanten';
vereis_recht('blk', 'lezen');

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
    p13_fout('P13-D002', 'BLK-detail van sollicitatie ' . $sol_id, $e, true);
}

if (!$sol) redirect(APP_URL . '/admin/sollicitanten.php');

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
<div class="bh-blk">
<header class="bh-blkkop">
  <div class="bh-blkkop-wie">
    <?php
      $fotoU = !empty($blk['foto_url']) ? foto_url($blk['foto_url']) : '';
    ?>
    <?php if ($fotoU): ?>
      <?php /* Knop en geen klikbare <img>: zo is de foto ook met het toetsenbord te
               openen en heeft hij een naam. De klik wordt opgevangen door de gedeelde
               vergroter in assets/js/app.js (data-foto). */ ?>
      <button type="button" class="bh-blkfoto" data-foto="<?= h($fotoU) ?>" data-naam="<?= h($naam) ?>"
              aria-label="Foto van <?= h($naam) ?> vergroten">
        <img src="<?= h($fotoU) ?>" alt="">
      </button>
    <?php else: ?>
      <?= bh_avatar(['voornaam' => $sol['voornaam'], 'achternaam' => $sol['achternaam'] ?? ''], 64) ?>
    <?php endif; ?>
    <div class="bh-blkkop-tekst">
      <h2><?= h($naam) ?></h2>
      <span class="bh-meta"><?= h((string)$sol['email']) ?> · <?= h((string)($sol['seizoen_naam'] ?? '')) ?></span>
      <span class="bh-blkkop-badges">
        <?= bh_fase_badge((string)$sol['fase']) ?>
        <?php if (!empty($sol['terugkerend'])): ?><?= bh_chip('terugkerend', 'paars') ?><?php endif; ?>
      </span>
    </div>
  </div>
  <div class="bh-blkkop-acties">
    <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $sol_id ?>">
      Naar het dossier
    </a>
    <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" onclick="window.print()">
      <?= icoon('printer', 14) ?> Afdrukken
    </button>
    <a class="ds-btn ds-btn-zacht ds-btn-sm" target="_blank" rel="noopener"
       href="<?= APP_URL ?>/admin/export_blk.php?formaat=pdf&amp;id=<?= $sol_id ?>">
      <?= icoon('document', 14) ?> PDF
    </a>
    <a class="ds-btn ds-btn-zacht ds-btn-sm"
       href="<?= APP_URL ?>/admin/export_blk.php?formaat=excel&amp;ids=<?= $sol_id ?>">
      <?= icoon('export', 14) ?> Excel
    </a>
  </div>
</header>

<?php if (!$blk): ?>
<div class="ds-melding ds-melding-info">Dit formulier is nog niet ingevuld door de sollicitant.</div>
<?php else: ?>

<!-- NAW + gegevens -->
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Persoonsgegevens</h2></div>
  <div class="bh-kaartbody">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
      <?php
      // AVG: alleen wie de BLK-module mag lezen ziet het volledige adres; anders alleen woonplaats
      $mag_adres = function_exists('mag') ? mag('blk', 'lezen') : true;
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
      <div><div class="bh-veldlabel"><?= $l ?></div><div style="font-size:13px"><?= $v ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Achtergrond -->
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Achtergrond</h2></div>
  <div class="bh-kaartbody">
    <?php foreach (['Opleiding'=>'opleiding',"Diploma's"=>'diplomas','Werkervaring'=>'werkervaring'] as $l=>$k): ?>
    <div class="bh-blkveld">
      <div class="bh-veldlabel"><?= $l ?></div>
      <?php if ($blk[$k]): ?><div class="bh-blkwaarde"><?= h($blk[$k]) ?></div>
      <?php else: ?><div class="bh-blkleeg">Niet ingevuld</div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Motivatie -->
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Motivatie</h2></div>
  <div class="bh-kaartbody">
    <?php foreach (['Motivatie lifeguard'=>'motivatie_lifeguard','Strand motivatie'=>'motivatie_strand'] as $l=>$k): ?>
    <div class="bh-blkveld">
      <div class="bh-veldlabel"><?= $l ?></div>
      <?php if ($blk[$k]): ?><div class="bh-blkwaarde"><?= h($blk[$k]) ?></div>
      <?php else: ?><div class="bh-blkleeg">Niet ingevuld</div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Beschikbaarheid -->
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Beschikbaarheid</h2></div>
  <div class="bh-kaartbody">
    <?php if (!empty($blk_weken)): ?>
    <div class="bh-blkveld">
      <div class="bh-veldlabel">Voorkeur weken (uit BLK — <?= count($blk_weken) ?> weken)</div>
      <div style="display:flex;flex-wrap:wrap;gap:5px">
        <?php foreach ($blk_weken as $w): ?><span class="badge badge-blauw" style="font-size:12px">Week <?= (int)$w ?></span><?php endforeach; ?>
      </div>
    </div>
    <?php elseif ($blk['beschikbaarheid_intentie']): ?>
    <div class="bh-blkveld">
      <div class="bh-veldlabel">Beschikbaarheid (vrije tekst)</div>
      <div class="bh-blkwaarde"><?= h($blk['beschikbaarheid_intentie']) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($blk['beschikbaarheid_uitzonderingen']): ?>
    <div class="bh-blkveld">
      <div class="bh-veldlabel">Kan niet op</div>
      <div class="bh-blkwaarde" class="bh-blkwaarde ds-fase-oranje bh-blk-let-op"><?= h($blk['beschikbaarheid_uitzonderingen']) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($weken_sol) || !empty($weken_def)): ?>
    <hr style="border:none;border-top:1px solid var(--rand);margin:12px 0">
    <?php if (!empty($weken_sol)): ?>
    <div class="bh-blkveld">
      <div class="bh-veldlabel">Definitief opgegeven door sollicitant (<?= count($weken_sol) ?> weken)</div>
      <div style="display:flex;flex-wrap:wrap;gap:4px">
        <?php foreach ($weken_sol as $w): ?><span class="badge badge-blauw">W<?= $w ?></span><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($weken_def)): ?>
    <div class="bh-blkveld">
      <div class="bh-veldlabel">Definitief ingepland door beheerder (<?= count($weken_def) ?> weken · <?= count($weken_def)*5 ?> werkdagen)</div>
      <div style="display:flex;flex-wrap:wrap;gap:4px">
        <?php foreach ($weken_def as $w): ?><span class="badge badge-oranje">W<?= $w ?></span><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($ai_besch_logs)): ?>
    <hr style="border:none;border-top:1px solid var(--rand);margin:12px 0">
    <div class="bh-blkveld">
      <div class="bh-veldlabel">AI-invoer beschikbaarheid <span style="font-weight:400;color:var(--tekst-zacht)">(wat de sollicitant zelf typte)</span></div>
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
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Overig</h2></div>
  <div class="bh-kaartbody">
    <div style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:start">
      <div>
        <?php if ($blk['bijzonderheden']): ?>
        <div class="bh-blkveld">
          <div class="bh-veldlabel">Bijzonderheden / medicatie</div>
          <div class="bh-blkwaarde" class="bh-blkwaarde ds-fase-rood bh-blk-let-op"><?= h($blk['bijzonderheden']) ?></div>
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
      <?php /* $naam en niet volledige_naam($blk): de blk-rij heeft geen naamvelden, dus
               dat gaf een leeg bijschrift en een aria-label "Foto van  vergroten". */ ?>
      <button type="button" class="bh-blkfoto-groot" data-foto="<?= h(foto_url($blk['foto_url'])) ?>"
              data-naam="<?= h($naam) ?>"
              aria-label="Foto van <?= h($naam) ?> vergroten" title="Klik voor groter">
        <img src="<?= h(foto_url($blk['foto_url'])) ?>" alt="">
      </button>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Wijzigingslog -->
<!-- AI Analyse sectie -->
<?php if ($ai_tekst || instelling('ai.api_key')): ?>
<div class="ds-kaart">
  <div class="bh-kaartkop">
    <div style="display:flex;align-items:center;gap:10px">
      <h2>AI Geschiktheidsanalyse</h2>
      <span style="font-size:11px;color:var(--grijs);background:var(--grijs-bg);padding:2px 7px;border-radius:10px">Geanonimiseerd · AVG-conform</span>
    </div>
    <?php if ($blk): ?>
    <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
            onclick="genereerAnalyse(<?= $sol_id ?>)">
      <?= $ai_tekst ? 'Opnieuw analyseren' : 'Analyseer nu' ?>
    </button>
    <?php endif; ?>
  </div>
  <div class="bh-kaartbody">
    <?php if ($ai_tekst): ?>
    <div class="bh-aikader" style=";border-radius:8px;padding:16px;line-height:1.8;font-size:14px;color:var(--tekst)">
      <?= function_exists('ai_render') ? ai_render($ai_tekst) : nl2br(h($ai_tekst)) ?>
    </div>
    <?php if ($ai_datum): ?>
    <div style="font-size:11px;color:var(--grijs);margin-top:8px">
      Analyse gegenereerd op <?= datum_nl(substr($ai_datum,0,10)) ?> <?= substr($ai_datum,11,5) ?>
      · Gebaseerd op BLK-formulier, geen persoonsgegevens verstuurd
    </div>
    <?php endif; ?>
    <?php elseif (!instelling('ai.api_key')): ?>
    <div class="ds-melding ds-melding-info">
      Stel een Gemini API key in via <a href="<?= APP_URL ?>/admin/instellingen.php?tab=mail" style="color:var(--oranje)">Instellingen → Mail & notificaties</a> om AI analyse te activeren.
    </div>
    <?php elseif (!$blk): ?>
    <p style="color:var(--grijs);font-size:13px">Analyse beschikbaar zodra het BLK-formulier is ingevuld.</p>
    <?php else: ?>
    <div style="text-align:center;padding:20px">
      <p style="color:var(--grijs);font-size:13px;margin-bottom:12px">Nog geen analyse gegenereerd.</p>
      <div id="ai-loading-<?= $sol_id ?>" style="display:none;color:var(--grijs);font-size:13px;margin-top:8px">
        Analyse wordt gemaakt via Gemini AI...
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($wijzigingen)): ?>
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Wijzigingsgeschiedenis</h2></div>
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
  if (btn) { btn.disabled = true; btn.textContent = 'Bezig…'; }
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
      if (btn) { btn.disabled = false; btn.textContent = 'Analyseer nu'; }
      if (loadEl) loadEl.style.display = 'none';
      bhToast(data.fout || 'De analyse lukte niet. Controleer de API-sleutel bij Instellingen › AI-analyse.', 'fout');
    }
  })
  .catch(() => {
    if (btn) { btn.disabled = false; }
    if (loadEl) loadEl.style.display = 'none';
    bhToast('Verbindingsfout — probeer het opnieuw.', 'fout');
  });
}
</script>
<?php require __DIR__ . '/layout_bottom.php'; ?>
<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Kennismakingsformulier';
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
$pagina_titel = 'Kennismakingsformulier: ' . $naam;
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
    <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" data-klik="window.print()">
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
    <div class="bh-blk-gegevens">
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
      <div><div class="bh-veldlabel"><?= $l ?></div><div class="bh-blk-gegeven"><?= $v ?></div></div>
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
    <?php foreach (['Motivatie strandwacht'=>'motivatie_lifeguard','Strand motivatie'=>'motivatie_strand'] as $l=>$k): ?>
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
      <div class="bh-veldlabel">Voorkeur weken (uit het kennismakingsformulier, <?= count($blk_weken) ?> weken)</div>
      <div class="bh-blk-weken bh-blk-weken-ruim">
        <?php foreach ($blk_weken as $w): ?><?= bh_badge('Week ' . (int)$w, 'blauw') ?><?php endforeach; ?>
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
      <div class="bh-blkwaarde ds-fase-oranje bh-blk-let-op"><?= h($blk['beschikbaarheid_uitzonderingen']) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($weken_sol) || !empty($weken_def)): ?>
    <hr class="bh-blk-scheiding">
    <?php if (!empty($weken_sol)): ?>
    <div class="bh-blkveld">
      <div class="bh-veldlabel">Definitief opgegeven door sollicitant (<?= count($weken_sol) ?> weken)</div>
      <div class="bh-blk-weken">
        <?php foreach ($weken_sol as $w): ?><?= bh_badge('Week ' . (int)$w, 'blauw') ?><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($weken_def)): ?>
    <div class="bh-blkveld">
      <div class="bh-veldlabel">Definitief ingepland door beheerder (<?= count($weken_def) ?> weken · <?= count($weken_def)*5 ?> werkdagen)</div>
      <div class="bh-blk-weken">
        <?php foreach ($weken_def as $w): ?><?= bh_badge('Week ' . (int)$w, 'oranje') ?><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($ai_besch_logs)): ?>
    <hr class="bh-blk-scheiding">
    <div class="bh-blkveld">
      <div class="bh-veldlabel">AI-invoer beschikbaarheid <span class="bh-blk-labelnoot">(wat de sollicitant zelf typte)</span></div>
      <?php foreach ($ai_besch_logs as $log): ?>
      <div class="bh-blk-aiinvoer">
        <div class="bh-blk-aiinvoer-meta">
          <?= h(date('j-n-Y H:i', strtotime($log['aangemaakt_op']))) ?>
          · <?= $log['status']==='ok' ? 'verwerkt' : h($log['status']) ?>
        </div>
        <div class="bh-blk-aiinvoer-tekst"><?= h($log['invoer']) ?></div>
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
    <div class="bh-blk-overig">
      <div>
        <?php if ($blk['bijzonderheden']): ?>
        <div class="bh-blkveld">
          <div class="bh-veldlabel">Bijzonderheden / medicatie</div>
          <div class="bh-blkwaarde ds-fase-rood bh-blk-let-op"><?= h($blk['bijzonderheden']) ?></div>
        </div>
        <?php endif; ?>
        <div class="bh-blk-voetnoot">
          <?php $_blk_op = blk_ingediend_op($sol, $blk); ?>
          <?= $_blk_op ? 'Ingediend: ' . h(datum_nl(substr($_blk_op, 0, 10))) : 'Concept, nog niet ingediend' ?>
          <?php if ($blk['bijgewerkt_op'] && substr($blk['bijgewerkt_op'],0,10) !== substr((string)$_blk_op,0,10)): ?>
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
<?php
// Bezwaar (AVG art. 21): de sollicitant wil geen automatische beoordeling. Dan geen knop meer
// om te analyseren, alleen de vaststelling. Vastleggen doet het bestuur op verzoek (vraag in Berichten).
$ai_bezwaar  = function_exists('ai_bezwaar') && ai_bezwaar($sol_id);
$ai_mag      = !function_exists('mag') || mag('sollicitanten', 'bewerken');
?>
<?php if ($ai_tekst || $ai_bezwaar || instelling('ai.api_key')): ?>
<div class="ds-kaart">
  <div class="bh-kaartkop">
    <div class="bh-blk-aikop">
      <h2>AI Geschiktheidsanalyse</h2>
      <span class="bh-blk-aipil">Zonder naam en contactgegevens, zonder bijzonderheden</span>
    </div>
    <?php if ($blk && !$ai_bezwaar && $ai_mag): ?>
    <span class="bh-knoprij">
      <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
              data-klik="genereerAnalyse(<?= $sol_id ?>)">
        <?= $ai_tekst ? 'Opnieuw analyseren' : 'Analyseer nu' ?>
      </button>
      <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" data-klik="aiBezwaar(<?= $sol_id ?>)">
        Bezwaar vastleggen…
      </button>
    </span>
    <?php endif; ?>
  </div>
  <div class="bh-kaartbody">
    <?php if ($ai_bezwaar): ?>
    <div class="ds-melding ds-melding-info">
      De sollicitant heeft bezwaar gemaakt tegen de AI-analyse. Er is geen analyse (meer) en er wordt er ook geen gemaakt.
    </div>
    <?php elseif ($ai_tekst): ?>
    <div class="bh-aikader bh-blk-aitekst">
      <?= function_exists('ai_render') ? ai_render($ai_tekst) : nl2br(h($ai_tekst)) ?>
    </div>
    <?php if ($ai_datum): ?>
    <div class="bh-blk-voetnoot">
      Analyse gegenereerd op <?= datum_nl(substr($ai_datum,0,10)) ?> <?= substr($ai_datum,11,5) ?>
      · Gebaseerd op het kennismakingsformulier, zonder naam en contactgegevens verstuurd
    </div>
    <?php endif; ?>
    <?php elseif (!instelling('ai.api_key')): ?>
    <div class="ds-melding ds-melding-info">
      Stel een Gemini API-sleutel in bij <a href="<?= APP_URL ?>/admin/instellingen.php?tab=ai" class="bh-blk-oranjelink">Instellingen › AI-analyse</a> om de analyse te gebruiken.
    </div>
    <?php elseif (!$blk): ?>
    <p class="bh-leegregel">Analyse beschikbaar zodra het kennismakingsformulier is ingevuld.</p>
    <?php else: ?>
    <div class="bh-blk-geenanalyse">
      <p class="bh-leegregel bh-blk-geenanalyse-tekst">Nog geen analyse gegenereerd.</p>
      <div id="ai-loading-<?= $sol_id ?>" class="bh-blk-bezig" hidden>
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
  <div>
    <?php foreach ($wijzigingen as $wij):
      $ch = json_decode($wij['wijzigingen_json'] ?? '[]', true) ?: []; ?>
    <div class="bh-blk-wijziging">
      <div class="bh-blk-wijziging-kop">
        <strong class="bh-blk-wijziging-reden">Reden: <?= h($wij['reden']) ?></strong>
        <span class="bh-blk-wijziging-wanneer"><?= datum_nl(substr($wij['gewijzigd_op'],0,10)) ?> <?= substr($wij['gewijzigd_op'],11,5) ?></span>
      </div>
      <?php foreach ($ch as $c): ?>
      <div class="bh-blk-wijziging-veld">
        <strong><?= h($c['label'] ?? $c['veld'] ?? '') ?>:</strong>
        <span class="bh-blk-oud"><?= h(mb_substr($c['oud'] ?? '—', 0, 60)) ?></span>
        → <span class="bh-blk-nieuw"><?= h(mb_substr($c['nieuw'] ?? '—', 0, 60)) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</div>
<script nonce="<?= h(csp_nonce()) ?>">
// Op blk_detail.php herlaad na analyse de pagina zelf (geen paneel)
function genereerAnalyse(solId) {
  const btn = event?.target;
  const loadEl = document.getElementById('ai-loading-' + solId);
  if (btn) { btn.disabled = true; btn.textContent = 'Bezig…'; }
  if (loadEl) loadEl.hidden = false;

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
      if (loadEl) loadEl.hidden = true;
      bhToast(data.fout || 'De analyse lukte niet. Controleer de API-sleutel bij Instellingen › AI-analyse.', 'fout');
    }
  })
  .catch(() => {
    if (btn) { btn.disabled = false; }
    if (loadEl) loadEl.hidden = true;
    bhToast('Verbindingsfout — probeer het opnieuw.', 'fout');
  });
}

/* Bezwaar tegen de AI-analyse (AVG art. 21), op verzoek van de sollicitant. Wist de analyse
   en zorgt dat er geen nieuwe komt (includes/ai_analyse.php, ai_bezwaar_zetten()). */
function aiBezwaar(solId) {
  bhBevestig({
    titel: 'Bezwaar tegen de AI-analyse vastleggen?',
    tekst: 'Doe dit als de sollicitant erom vraagt. De bestaande analyse wordt gewist en er wordt geen nieuwe gemaakt. Dit kun je niet terugdraaien.',
    knop: 'Ja, vastleggen'
  }).then(function (ja) {
    if (!ja) return;
    var klaar = window.P13Actie ? window.P13Actie.bezig(null, null) : function () {};
    fetch('<?= APP_URL ?>/admin/api_ai_analyse.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ sol_id: solId, actie: 'bezwaar', _csrf: '<?= csrf_token() ?>' })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.ok) { location.reload(); return; }
      klaar(); bhToast(data.fout || 'Vastleggen mislukt.', 'fout');
    })
    .catch(function () { klaar(); bhToast('Verbindingsfout — probeer het opnieuw.', 'fout'); });
  });
}
</script>
<?php require __DIR__ . '/layout_bottom.php'; ?>
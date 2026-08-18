<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Nabespreking';
$actieve_nav  = 'selectiedag';
vereist_rol('bestuur');

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;
$ik = $_SESSION['bhd_naam'] ?? 'onbekend';

// ── Acties ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    csrf_check();
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'besluit') {
        header('Content-Type: application/json');
        selectie_besluit_zetten($sid, (int)$_POST['sol_id'], $_POST['besluit'] ?? '', $_POST['notitie'] ?? '', $ik);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($actie === 'doorvoeren') {
        $n = selectie_besluit_doorvoeren($sid);
        flash('succes', "$n sollicitant(en) doorgezet: 'door' → uitgenodigd voor het TW, 'niet' → afgewezen. Twijfelgevallen bleven staan.");
        redirect(APP_URL . '/admin/selectie_nabespreking.php');
    }

    if ($actie === 'gebeld') {
        header('Content-Type: application/json');
        $meteen = !empty($_POST['meteen_doorvoeren']);
        $res = selectie_gebeld_zetten($sid, (int)$_POST['sol_id'], $_POST['gebeld_notitie'] ?? '', $ik, $meteen);
        echo json_encode(['ok' => true] + $res);
        exit;
    }
    if ($actie === 'gebeld_wissen') {
        header('Content-Type: application/json');
        selectie_gebeld_wissen($sid, (int)$_POST['sol_id']);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($actie === 'doorvoeren_een') {
        header('Content-Type: application/json');
        $ok = selectie_besluit_doorvoeren_een($sid, (int)$_POST['sol_id']);
        echo json_encode(['ok' => $ok]);
        exit;
    }
    if ($actie === 'gebelde_doorvoeren') {
        $n = selectie_gebelde_doorvoeren($sid);
        flash('succes', "$n gebelde sollicitant(en) doorgezet naar hun nieuwe fase.");
        redirect(APP_URL . '/admin/selectie_nabespreking.php?tab=bellijst');
    }
}

$criteria   = selectie_criteria();
$kandidaten = $sid ? selectie_kandidaten($sid) : [];
$groepen    = $sid ? selectie_groepen($sid) : [];
$besluiten  = $sid ? selectie_besluiten($sid) : [];

$groep_naam = [];
foreach ($groepen as $g) $groep_naam[$g['id']] = $g['naam'] ?: 'Groep ' . $g['nummer'];

// Filter
$filter = $_GET['filter'] ?? 'alle';
$zichtbaar = array_filter($kandidaten, function($k) use ($filter, $besluiten) {
    $b = $besluiten[(int)$k['sol_id']]['besluit'] ?? '';
    return match ($filter) {
        'door'    => $b === 'door',
        'twijfel' => $b === 'twijfel',
        'niet'    => $b === 'niet',
        'open'    => $b === '',
        default   => true,
    };
});

// Tellingen
$tel = ['door'=>0, 'twijfel'=>0, 'niet'=>0, 'open'=>0];
foreach ($kandidaten as $k) {
    $b = $besluiten[(int)$k['sol_id']]['besluit'] ?? '';
    $tel[$b ?: 'open']++;
}

require __DIR__ . '/layout_top.php';
?>

<style>
.nb-kaart { background:var(--bg-card); border:1px solid var(--rand); border-radius:12px; margin-bottom:12px; overflow:hidden; border-left:4px solid var(--rand); }
.nb-kaart.b-door { border-left-color:var(--groen); }
.nb-kaart.b-twijfel { border-left-color:var(--oranje); }
.nb-kaart.b-niet { border-left-color:var(--rood); }
.nb-kop { display:flex; align-items:center; gap:12px; padding:14px 16px; }
.nb-foto { width:52px; height:52px; border-radius:50%; object-fit:cover; border:2px solid var(--rand); flex-shrink:0; }
.nb-foto-leeg { width:52px; height:52px; border-radius:50%; background:var(--grijs-bg); display:flex; align-items:center; justify-content:center; font-weight:600; color:var(--grijs); flex-shrink:0; }
.nb-naam { font-weight:600; font-size:16px; }
.nb-body { padding:0 16px 16px; }
.nb-kolommen { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media(max-width:640px){ .nb-kolommen { grid-template-columns:1fr; } }
.nb-obs { padding:8px 10px; border-radius:8px; font-size:13px; background:var(--grijs-bg); margin-bottom:5px; }
.nb-obs.positief { background:var(--groen-bg); }
.nb-obs.aandacht { background:var(--rood-bg); }
.nb-score-rij { display:flex; align-items:center; gap:8px; font-size:13px; padding:3px 0; }
.nb-score-balk { flex:1; height:6px; background:var(--grijs-bg); border-radius:3px; overflow:hidden; }
.nb-score-vul { height:100%; background:var(--navy); }
.nb-besluit-knop { padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; border:1.5px solid var(--rand); background:var(--bg-card); cursor:pointer; }
.nb-besluit-knop.door.aan { background:var(--groen); border-color:var(--groen); color:#fff; }
.nb-besluit-knop.twijfel.aan { background:var(--oranje); border-color:var(--oranje); color:#fff; }
.nb-besluit-knop.niet.aan { background:var(--rood); border-color:var(--rood); color:#fff; }
.nb-filter { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px; }
.nb-filter a { padding:7px 14px; border-radius:20px; font-size:13px; text-decoration:none; color:var(--tekst); background:var(--bg-card); border:1px solid var(--rand); }
.nb-filter a.actief { background:var(--navy); color:#fff; border-color:var(--navy); }
</style>

<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
  <a href="<?= APP_URL ?>/admin/selectie_indeling.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--tekst);background:var(--bg-card);border:1px solid var(--rand)">1 · Groepsindeling</a>
  <a href="<?= APP_URL ?>/admin/selectie_inchecken.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--tekst);background:var(--bg-card);border:1px solid var(--rand)">2 · Inchecken</a>
  <a href="<?= APP_URL ?>/admin/selectiedag.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:var(--tekst);background:var(--bg-card);border:1px solid var(--rand)">3 · Beoordelen</a>
  <a href="<?= APP_URL ?>/admin/selectie_nabespreking.php" style="padding:7px 14px;border-radius:8px;font-size:13px;text-decoration:none;color:#fff;background:var(--navy);border:1px solid var(--navy)">4 · Nabespreking</a>
</div>

<?php if (!$sid): ?>
  <div class="flash flash-fout">Geen actief seizoen.</div>
<?php elseif (!$kandidaten): ?>
  <div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--tekst-zacht)">Nog geen kandidaten.</div></div>
<?php else: ?>

<?php
  $tab = $_GET['tab'] ?? 'nabespreken';
  // Bellijst: kandidaten met een definitief besluit (door/niet), nog niet doorgevoerd
  $bel_kandidaten = array_filter($kandidaten, function($k) use ($besluiten) {
      $b = $besluiten[(int)$k['sol_id']] ?? null;
      return $b && in_array($b['besluit'] ?? '', ['door','niet'], true) && empty($b['doorgevoerd_op']);
  });
  $tel_gebeld = 0; $tel_ongebeld = 0;
  foreach ($bel_kandidaten as $k) {
      $b = $besluiten[(int)$k['sol_id']] ?? [];
      if (!empty($b['gebeld_op'])) $tel_gebeld++; else $tel_ongebeld++;
  }
?>

<!-- Tab-toggle -->
<div style="display:flex;gap:6px;margin-bottom:16px;border-bottom:1px solid var(--rand);padding-bottom:0">
  <a href="?tab=nabespreken" style="padding:9px 18px;font-size:14px;font-weight:600;text-decoration:none;border-bottom:3px solid <?= $tab==='nabespreken'?'var(--navy)':'transparent' ?>;color:<?= $tab==='nabespreken'?'var(--navy)':'var(--tekst-zacht)' ?>">Nabespreken</a>
  <a href="?tab=bellijst" style="padding:9px 18px;font-size:14px;font-weight:600;text-decoration:none;border-bottom:3px solid <?= $tab==='bellijst'?'var(--navy)':'transparent' ?>;color:<?= $tab==='bellijst'?'var(--navy)':'var(--tekst-zacht)' ?>">📞 Bellijst<?php if ($tel_ongebeld): ?> <span style="background:var(--oranje);color:#fff;border-radius:10px;padding:1px 7px;font-size:11px"><?= $tel_ongebeld ?></span><?php endif; ?></a>
</div>

<?php if ($tab === 'bellijst'): ?>
  <?php require __DIR__ . '/selectie_bellijst_partial.php'; ?>
<?php else: ?>

<p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 12px">
  Alles per kandidaat bij elkaar. De scores zijn indicatief — de observaties en jullie gesprek bepalen het besluit.
  Zet per persoon een voorlopig besluit. Als je klaar bent, ga je naar de <a href="?tab=bellijst" style="color:var(--navy)">Bellijst</a> om iedereen te bellen en per persoon door te voeren.
</p>

<!-- Filter -->
<div class="nb-filter">
  <a href="?filter=alle" class="<?= $filter==='alle'?'actief':'' ?>">Iedereen (<?= count($kandidaten) ?>)</a>
  <a href="?filter=open" class="<?= $filter==='open'?'actief':'' ?>">○ Nog open (<?= $tel['open'] ?>)</a>
  <a href="?filter=door" class="<?= $filter==='door'?'actief':'' ?>" style="color:<?= $filter==='door'?'#fff':'var(--groen)' ?>">✓ Door (<?= $tel['door'] ?>)</a>
  <a href="?filter=twijfel" class="<?= $filter==='twijfel'?'actief':'' ?>" style="color:<?= $filter==='twijfel'?'#fff':'var(--oranje)' ?>">? Twijfel (<?= $tel['twijfel'] ?>)</a>
  <a href="?filter=niet" class="<?= $filter==='niet'?'actief':'' ?>" style="color:<?= $filter==='niet'?'#fff':'var(--rood)' ?>">✕ Niet (<?= $tel['niet'] ?>)</a>
</div>

<!-- Kandidaten -->
<?php foreach ($zichtbaar as $k):
  $sol_id = (int)$k['sol_id'];
  $scores = selectie_scores_van($sid, $sol_id);
  $observaties = selectie_observaties_van($sid, $sol_id);
  $besluit = $besluiten[$sol_id]['besluit'] ?? '';
  $notitie = $besluiten[$sol_id]['notitie'] ?? '';
  $foto = !empty($k['foto_url']) ? foto_url($k['foto_url']) : null;
?>
<div class="nb-kaart <?= $besluit ? 'b-' . $besluit : '' ?>" id="nb-<?= $sol_id ?>">
  <div class="nb-kop">
    <?php if ($foto): ?>
      <img class="nb-foto" src="<?= h($foto) ?>" alt="" loading="lazy"
           style="cursor:pointer"
           data-foto="<?= h($foto) ?>" data-naam="<?= h(trim($k['voornaam'].' '.$k['achternaam'])) ?>"
           onclick="fotoVergroten(this.dataset.foto, this.dataset.naam)">
    <?php else: ?>
      <span class="nb-foto-leeg"><?= h(mb_strtoupper(mb_substr($k['voornaam'],0,1) . mb_substr($k['achternaam'],0,1))) ?></span>
    <?php endif; ?>
    <div style="flex:1">
      <div class="nb-naam"><?= h($k['naam']) ?></div>
      <div style="font-size:12px;color:var(--tekst-zacht)">
        <?= $k['leeftijd'] ? $k['leeftijd'] . ' jaar' : '' ?>
        <?= $k['groep_id'] ? '· ' . h($groep_naam[$k['groep_id']] ?? '') : '' ?>
        <?php if (!$k['aanwezig']): ?>· <span style="color:var(--rood)">was afwezig</span><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="nb-body">

    <?php if (!empty($k['bijzonderheden'])): ?>
    <div style="padding:9px 12px;background:#fff5f5;border:1px solid #feb2b2;border-radius:8px;font-size:13px;color:#9b2c2c;margin-bottom:12px">
      ⚠️ <strong>Bijzonderheden:</strong> <?= nl2br(h($k['bijzonderheden'])) ?>
    </div>
    <?php endif; ?>

    <div class="nb-kolommen">
      <!-- Observaties -->
      <div>
        <div style="font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:6px">💬 Observaties (<?= count($observaties) ?>)</div>
        <?php if (!$observaties): ?>
          <div style="font-size:13px;color:var(--grijs)">Geen observaties genoteerd.</div>
        <?php else: foreach ($observaties as $o): ?>
          <div class="nb-obs <?= h($o['soort']) ?>">
            <?= nl2br(h($o['tekst'])) ?>
            <span style="font-size:11px;color:var(--grijs)">— <?= h($o['beoordelaar']) ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Scores -->
      <div>
        <div style="font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:6px">⭐ Scores (gemiddeld)</div>
        <?php
        $heeft_scores = false;
        foreach ($criteria as $tk => $test):
          if (empty($scores[$tk])) continue;
          $heeft_scores = true;
        ?>
        <div style="font-size:11px;color:var(--tekst-zacht);margin-top:6px"><?= h($test['icoon'] ?? '') ?> <?= h($test['naam']) ?></div>
        <?php foreach ($test['criteria'] as $crit):
          $sc = $scores[$tk][$crit['sleutel']] ?? null;
          if (!$sc || !$sc['allen']) continue;
        ?>
        <div class="nb-score-rij">
          <span style="min-width:90px"><?= h($crit['label']) ?></span>
          <span class="nb-score-balk"><span class="nb-score-vul" style="width:<?= ($sc['gem']/5)*100 ?>%"></span></span>
          <span style="min-width:56px;text-align:right;color:var(--tekst-zacht)"><?= $sc['gem'] ?> <span style="font-size:10px">(<?= count($sc['allen']) ?>)</span></span>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if (!$heeft_scores): ?><div style="font-size:13px;color:var(--grijs)">Geen scores ingevuld.</div><?php endif; ?>
      </div>
    </div>

    <!-- Besluit -->
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--rand-licht)">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span style="font-size:13px;font-weight:600;color:var(--tekst-zacht)">Besluit:</span>
        <div class="nb-besluit" data-sol="<?= $sol_id ?>">
          <button type="button" class="nb-besluit-knop door <?= $besluit==='door'?'aan':'' ?>" onclick="zetBesluit(<?= $sol_id ?>,'door')">✓ Door naar TW</button>
          <button type="button" class="nb-besluit-knop twijfel <?= $besluit==='twijfel'?'aan':'' ?>" onclick="zetBesluit(<?= $sol_id ?>,'twijfel')">? Twijfel</button>
          <button type="button" class="nb-besluit-knop niet <?= $besluit==='niet'?'aan':'' ?>" onclick="zetBesluit(<?= $sol_id ?>,'niet')">✕ Niet</button>
        </div>
      </div>
      <input type="text" id="notitie-<?= $sol_id ?>" value="<?= h($notitie) ?>" placeholder="Notitie bij het besluit (optioneel)"
             onchange="zetBesluit(<?= $sol_id ?>, null)"
             style="width:100%;margin-top:8px;padding:8px 10px;border:1px solid var(--rand);border-radius:8px;font-size:13px;box-sizing:border-box">
    </div>

  </div>
</div>
<?php endforeach; ?>

<!-- Doorvoeren -->
<div class="card" style="margin-top:16px;border:2px solid var(--navy)">
  <div class="card-body" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <div style="flex:1;min-width:200px">
      <div style="font-weight:600;margin-bottom:2px">Besluiten doorvoeren</div>
      <div style="font-size:12.5px;color:var(--tekst-zacht)">
        Zet <strong><?= $tel['door'] ?></strong> kandidaten op "uitgenodigd voor TW" en wijs <strong><?= $tel['niet'] ?></strong> af.
        Twijfelgevallen (<?= $tel['twijfel'] ?>) en open (<?= $tel['open'] ?>) blijven staan.
      </div>
    </div>
    <div style="margin-top:20px">
      <button type="button" onclick="document.getElementById('geavanceerd-doorvoeren').style.display='block';this.style.display='none'" style="background:none;border:none;color:var(--tekst-zacht);font-size:12.5px;cursor:pointer;text-decoration:underline">⚙ Geavanceerd: alles in één keer doorvoeren</button>
      <div id="geavanceerd-doorvoeren" style="display:none;margin-top:12px;padding:14px;background:var(--grijs-bg);border-radius:10px">
        <div style="font-size:12.5px;color:var(--tekst-zacht);margin-bottom:10px">
          Zet <strong><?= $tel['door'] ?></strong> kandidaten ineens op "uitgenodigd voor TW" en wijs <strong><?= $tel['niet'] ?></strong> af, zónder de bellijst te gebruiken.
          Twijfelgevallen (<?= $tel['twijfel'] ?>) en open (<?= $tel['open'] ?>) blijven staan. Handig bij heel veel sollicitanten.
        </div>
        <form method="POST" onsubmit="return confirm('Weet je het zeker? Dit past de fase van alle beoordeelde sollicitanten in één keer aan.\n\n<?= $tel['door'] ?> → uitgenodigd TW\n<?= $tel['niet'] ?> → afgewezen')">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="doorvoeren">
          <button type="submit" class="btn btn-primary" <?= ($tel['door']+$tel['niet'])===0?'disabled':'' ?>>Alles doorvoeren →</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php endif; // einde tab nabespreken ?>

<script>
const CSRF = '<?= csrf_token() ?>';

function zetBesluit(solId, besluit) {
  const wrap = document.querySelector('.nb-besluit[data-sol="' + solId + '"]');
  // Als besluit null: alleen notitie opslaan, huidige besluit-knop-status behouden
  if (besluit === null) {
    const aan = wrap.querySelector('.nb-besluit-knop.aan');
    besluit = aan ? (aan.classList.contains('door') ? 'door' : aan.classList.contains('twijfel') ? 'twijfel' : 'niet') : '';
  } else {
    // Toggle: nogmaals klikken = wissen
    const huidig = wrap.querySelector('.nb-besluit-knop.' + besluit + '.aan');
    wrap.querySelectorAll('.nb-besluit-knop').forEach(b => b.classList.remove('aan'));
    if (!huidig) wrap.querySelector('.nb-besluit-knop.' + besluit).classList.add('aan');
    else besluit = '';
    // Kaart-rand kleuren
    const kaart = document.getElementById('nb-' + solId);
    kaart.className = 'nb-kaart' + (besluit ? ' b-' + besluit : '');
  }
  const notitie = document.getElementById('notitie-' + solId).value;
  fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ actie:'besluit', sol_id:solId, besluit, notitie, _csrf:CSRF }) });
}
</script>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

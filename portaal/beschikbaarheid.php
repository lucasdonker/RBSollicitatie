<?php
/**
 * Portaal — beschikbaarheid-overzicht voor de sollicitant.
 * Toont in mensentaal wat wij begrepen hebben, met verzoeken + status,
 * en knoppen om aan te passen of definitief te maken.
 */
require_once __DIR__ . '/../includes/functions.php';
sessie_start();

if (empty($_SESSION['portaal_sol_id'])) {
    redirect(APP_URL . '/portaal/');
}
$sol_id = (int)$_SESSION['portaal_sol_id'];
$db = db();

// Sollicitant + seizoen
$q = $db->prepare("
    SELECT s.id, s.fase, sz.naam AS seizoen_naam, sz.datum_werk_start, sz.datum_werk_eind, sz.datum_tw_start,
           sz.id AS seizoen_id, p.voornaam
    FROM sollicitaties s
    JOIN personen p ON p.id = s.persoon_id
    JOIN seizoenen sz ON sz.id = s.seizoen_id
    WHERE s.id = ?
");
$q->execute([$sol_id]);
$mij = $q->fetch();
if (!$mij) redirect(APP_URL . '/portaal/');

$seizoen = ['datum_werk_start'=>$mij['datum_werk_start'], 'datum_werk_eind'=>$mij['datum_werk_eind'], 'datum_tw_start'=>$mij['datum_tw_start'], 'id'=>$mij['seizoen_id']];

// Actie: definitief maken
$flash_ok = null; $flash_fout = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'definitief') {
    csrf_check();
    if (function_exists('beschikbaarheid2_definitief_maken') && beschikbaarheid2_definitief_maken($sol_id)) {
        $flash_ok = 'Je beschikbaarheid staat nu definitief. Bedankt!';
    } else {
        $flash_fout = 'Er staat nog geen beschikbaarheid om definitief te maken. Vul die eerst in.';
    }
}

$b2 = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($sol_id) : ['profiel'=>null,'blokken'=>[],'afwezig'=>[],'verzoeken'=>[],'correcties'=>[]];
$b2p = $b2['profiel'];
$eff = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol_id, $seizoen) : [];
$wkd = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen) : [];
$wk_label = function($wn) use ($wkd) {
    $wn = (int)$wn;
    return isset($wkd[$wn]) ? 'week ' . $wn . ' (' . $wkd[$wn]['start']->format('j M') . ')' : 'week ' . $wn;
};
$heeft_besch = $b2p && $b2['blokken'];
$voorlopig = $heeft_besch && !empty($b2p['voorlopig']);

// Deadline
$deadline = function_exists('beschikbaarheid2_deadline') ? beschikbaarheid2_deadline($seizoen) : null;
$dgn_tot = $deadline ? (int)floor(($deadline->getTimestamp() - strtotime('today')) / 86400) : null;

$logo = function_exists('site_logo_url') ? (site_logo_url() ?: '') : '';
$dagnamen = ['',' maandag','dinsdag','woensdag','donderdag','vrijdag','zaterdag','zondag'];
$portaal_titel = 'Mijn beschikbaarheid';
$portaal_nav = 'start';
require __DIR__ . '/_kop.php';
?>
<style>
.bk-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;margin-bottom:16px;box-shadow:var(--shadow-card)}
.bk-card h2{margin-top:0;font:var(--text-subhead)}
.bk-rij{padding:8px 0;border-bottom:1px solid var(--rand-2);font-size:14px;line-height:1.6}
.bk-rij:last-child{border-bottom:none}
.badge{display:inline-block;font-size:11px;font-weight:700;color:#fff;padding:3px 10px;border-radius:var(--radius-sm)}
</style>

  <h1 style="font:var(--text-title);margin-bottom:6px">Mijn beschikbaarheid</h1>
  <p style="color:var(--ink-soft);font-size:14px;margin-bottom:20px">Dit is wat wij van jou begrepen hebben voor seizoen <?= h($mij['seizoen_naam']) ?>. Zo gebruiken we het voor de planning.</p>

  <?php if ($flash_ok): ?>
    <div class="ds-melding ds-melding-ok" style="margin-bottom:16px"><?= icoon('vink', 18) ?> <?= h($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_fout): ?>
    <div style="margin-bottom:16px;padding:12px 16px;background:var(--rood-bg);border-left:3px solid var(--rood);border-radius:6px;font-size:13.5px;color:var(--rood)">⚠️ <?= h($flash_fout) ?></div>
  <?php endif; ?>

  <?php if (!$heeft_besch): ?>
    <div class="bk-card" style="text-align:center;padding:32px 20px">
      <div style="margin-bottom:8px;color:var(--navy)"><?= icoon("lijst", 30) ?></div>
      <p style="font-size:14px;margin-bottom:16px">Je hebt nog geen beschikbaarheid doorgegeven.</p>
      <a href="<?= APP_URL ?>/portaal/#blk" class="ds-btn ds-btn-primair">Beschikbaarheid invullen</a>
    </div>
  <?php else: ?>

    <!-- Status -->
    <div class="bk-card">
      <?php if ($voorlopig): ?>
        <span class="ds-badge ds-badge-warn">Voorlopig</span>
        <p style="font-size:13.5px;margin:10px 0 0;line-height:1.6">
          Je beschikbaarheid staat nog op voorlopig. Klopt alles? Maak het dan definitief.
          <?php if ($deadline): ?>
            <br><span style="color:<?= ($dgn_tot!==null && $dgn_tot<=7)?'var(--rood)':'var(--tekst-zacht)' ?>">
              Deadline: <strong><?= h(datum_nl($deadline->format('Y-m-d'))) ?></strong><?php if ($dgn_tot !== null): ?> — <?= $dgn_tot < 0 ? 'verstreken' : 'nog ' . $dgn_tot . ' ' . ($dgn_tot===1?'dag':'dagen') ?><?php endif; ?>.
            </span>
          <?php endif; ?>
          <br><span style="color:var(--tekst-zacht);font-size:12.5px">Werk je het niet bij, dan gebruiken we je huidige invoer automatisch vanaf 2 weken vóór het trainingsweekend.</span>
        </p>
      <?php else: ?>
        <span class="ds-badge ds-badge-ok"><?= icoon("vink", 13) ?> Definitief</span>
        <p style="font-size:13.5px;margin:10px 0 0;color:var(--tekst-zacht)">Je beschikbaarheid is definitief<?= !empty($b2p['definitief_op']) ? ' sinds ' . h(datum_nl($b2p['definitief_op'])) : '' ?>. Je kunt hem nog steeds aanpassen als er iets wijzigt.</p>
      <?php endif; ?>
    </div>

    <!-- Samenvatting in mensentaal -->
    <div class="bk-card">
      <h2><?= icoon("kalender", 18) ?> Wanneer je kunt werken</h2>
      <?php foreach ($b2['blokken'] as $blok): ?>
        <div class="bk-rij">
          <strong><?= h($wk_label($blok['van_week'])) ?> t/m <?= h($wk_label($blok['tot_week'])) ?></strong> —
          <?php if ($blok['regime'] === 'weekend'):
            $wd = $blok['weekend_dagen'] ?: [5,6,7];
            echo 'alleen ' . implode('/', array_map(fn($d) => trim($dagnamen[$d]), $wd));
          else: ?>
            hele weken (5 dagen)
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="bk-rij" style="color:var(--tekst-zacht)"><?= count($eff) ?> <?= count($eff)===1?'week':'weken' ?> beschikbaar in totaal.</div>
    </div>

    <!-- Afwezigheid + voorkeuren -->
    <?php if ($b2['afwezig'] || !empty($b2p['vaste_dagen_uit'])): ?>
    <div class="bk-card">
      <h2>ℹ️ Verder doorgegeven</h2>
      <?php if ($b2['afwezig']): ?>
        <div class="bk-rij">
          <strong>Afwezig:</strong>
          <?= implode(', ', array_map(fn($a) => h(datum_nl($a['van_datum'])) . ' t/m ' . h(datum_nl($a['tot_datum'])) . ($a['label'] ? ' (' . h($a['label']) . ')' : ''), $b2['afwezig'])) ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($b2p['vaste_dagen_uit'])): ?>
        <div class="bk-rij">
          <strong>Voorkeur vrij:</strong>
          <?= implode(', ', array_map(fn($d) => trim($dagnamen[$d]), $b2p['vaste_dagen_uit'])) ?>
          <span style="color:var(--tekst-zacht)">(voorkeur — de planner beslist)</span>
          <?php if (!empty($b2p['vaste_toelichting'])): ?><br><span style="color:var(--tekst-zacht);font-size:12.5px"><?= h($b2p['vaste_toelichting']) ?></span><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Verzoeken minder dagen + status -->
    <?php $zichtbare_vz = array_filter($b2['verzoeken'], fn($v) => $v['status'] !== 'afgewezen' || true); ?>
    <?php if ($b2['verzoeken']): ?>
    <div class="bk-card">
      <h2>⚖️ Verzoeken (minder dagen)</h2>
      <?php foreach ($b2['verzoeken'] as $vz):
        $st = $vz['status'];
        $st_kleur = $st === 'goedgekeurd' ? 'var(--groen)' : ($st === 'afgewezen' ? 'var(--rood)' : 'var(--oranje)');
        $st_tekst = $st === 'goedgekeurd' ? 'goedgekeurd' : ($st === 'afgewezen' ? 'afgewezen' : 'in behandeling');
      ?>
        <div class="bk-rij">
          <strong><?= h($wk_label($vz['van_week'])) ?> t/m <?= h($wk_label($vz['tot_week'])) ?></strong>: <?= (int)$vz['dagen_aantal'] ?> dagen/week
          <span style="color:<?= $st_kleur ?>;font-weight:600"> · <?= $st_tekst ?></span>
          <?php if (!empty($vz['dagen_pool'])): ?>
            <br><span style="color:var(--tekst-zacht);font-size:12.5px">Beschikbare dagen die week: <?= implode(', ', array_map(fn($d) => trim($dagnamen[$d]), $vz['dagen_pool'])) ?></span>
          <?php endif; ?>
          <?php if (!empty($vz['toelichting'])): ?>
            <br><span style="color:var(--tekst-zacht);font-size:12.5px"><?= h($vz['toelichting']) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Bestuurs-correcties (als die er zijn, transparant tonen) -->
    <?php if (!empty($b2['correcties'])): ?>
    <div class="bk-card">
      <h2><?= icoon("potlood", 18) ?> Aangepast in overleg</h2>
      <?php foreach ($b2['correcties'] as $c): ?>
        <div class="bk-rij">
          <strong><?= h($wk_label($c['week'])) ?></strong>:
          <?= $c['soort'] === 'niet' ? 'deze week niet ingedeeld' : 'aangepaste dagen' ?>
          <?php if (!empty($c['reden'])): ?><span style="color:var(--tekst-zacht)"> — <?= h($c['reden']) ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Acties -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
      <a href="<?= APP_URL ?>/portaal/#blk" class="ds-btn ds-btn-secundair" style="flex:1;min-width:140px"><?= icoon("potlood", 16) ?> Aanpassen</a>
      <?php if ($voorlopig): ?>
      <form method="POST" style="flex:1;min-width:140px;margin:0">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="definitief">

      </form>
      <?php endif; ?>
    </div>

  <?php endif; ?>

<?php require __DIR__ . '/_voet.php'; ?>

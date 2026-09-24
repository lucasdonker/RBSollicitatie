<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Loonplanning';
$actieve_nav  = 'loonplanning';
// Weken naast elkaar: geen tekstbreedte, maar de ruimte die er is.
$pagina_breed = true;
vereis_recht('loonplanning', 'lezen');
// Lezen mag de planning zien; alles wat schrijft (scenario's, cellen) vraagt bewerkrecht.
$mag_bewerken = mag('loonplanning', 'bewerken');

$db = db();
try { instellingen_laden(); } catch (Exception $e) {}

$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;
$jaar    = $seizoen && !empty($seizoen['datum_werk_start']) ? (int)date('Y', strtotime($seizoen['datum_werk_start'])) : (int)date('Y');

// ── Scenario bepalen ─────────────────────────────────────────
$scenarios = $sid ? loonplan_scenarios($sid) : [];
$actief_sc = $sid ? loonplan_actief_scenario($sid) : null;
$scenario_id = isset($_GET['scenario']) ? (int)$_GET['scenario'] : (int)($actief_sc['id'] ?? 0);
$geldig = false;
foreach ($scenarios as $sc) if ((int)$sc['id'] === $scenario_id) $geldig = true;
if (!$geldig && $scenarios) $scenario_id = (int)$scenarios[0]['id'];

// ── Scenario-acties ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_actie'])) {
    csrf_check();
    if (!$mag_bewerken) {
        flash('fout', 'Je hebt geen bewerkrecht op de loonplanning.');
        redirect(APP_URL . '/admin/loonplanning.php?scenario=' . $scenario_id);
    }
    $a = $_POST['sc_actie'];
    if ($a === 'dupliceer') {
        $nieuw = loonplan_scenario_dupliceer($sid, (int)$_POST['bron_id'], trim($_POST['naam'] ?? 'Kopie'));
        if ($nieuw) { flash('succes', 'Scenario gedupliceerd.'); redirect(APP_URL . '/admin/loonplanning.php?scenario=' . $nieuw); }
        flash('fout', 'Dupliceren mislukt.');
    } elseif ($a === 'activeer') {
        loonplan_scenario_activeer($sid, (int)$_POST['scenario_id']);
        flash('succes', 'Scenario is nu de actieve planning.');
        redirect(APP_URL . '/admin/loonplanning.php?scenario=' . (int)$_POST['scenario_id']);
    } elseif ($a === 'verwijder') {
        if (loonplan_scenario_verwijder($sid, (int)$_POST['scenario_id'])) flash('succes', 'Scenario verwijderd.');
        else flash('fout', 'Het laatste scenario kan niet worden verwijderd.');
        redirect(APP_URL . '/admin/loonplanning.php');
    }
    redirect(APP_URL . '/admin/loonplanning.php?scenario=' . $scenario_id);
}

// ── AJAX: cel opslaan + herberekening ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_loon'])) {
    header('Content-Type: application/json');
    try {
        csrf_check();
        if (!$mag_bewerken) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'fout' => 'Je hebt geen bewerkrecht op de loonplanning.']);
            exit;
        }
        loonplan_cel_opslaan(
            (int)$_POST['scenario_id'], (int)$_POST['sol_id'], (int)$_POST['week_nr'], $jaar,
            (int)$_POST['dagen'], ($_POST['opgenomen'] ?? '0') === '1' ? 1 : 0,
            trim($_POST['functie'] ?? '') ?: null
        );
        $an = loonplan_analyse($sid, (int)$_POST['scenario_id'], $seizoen);
        echo json_encode([
            'ok'       => true,
            'totaal'   => $an['totaal'],
            'per_week' => $an['per_week'],
            'dekking'  => array_map(fn($d) => ['status'=>$d['status'], 'ingezet'=>$d['ingezet']], $an['dekking']),
            'signalen' => $an['signalen'],
            'persoon'  => [
                'sol_id' => (int)$_POST['sol_id'],
                'kosten' => $an['mensen'][(int)$_POST['sol_id']]['totaal_kosten'] ?? 0,
                'dagen'  => $an['mensen'][(int)$_POST['sol_id']]['totaal_dagen'] ?? 0,
            ],
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false, 'fout'=>$e->getMessage()]);
    }
    exit;
}

// ── Data ─────────────────────────────────────────────────────
$analyse = ($sid && $scenario_id)
    ? loonplan_analyse($sid, $scenario_id, $seizoen)
    : ['mensen'=>[], 'per_week'=>[], 'totaal'=>['kosten'=>0,'dagen'=>0,'budget'=>0,'over_budget'=>false,'restant'=>null], 'dekking'=>[], 'signalen'=>[]];
$weken_datums = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen) : [];
$functies = function_exists('functies_ophalen') ? functies_ophalen() : [];
$budget = (float)$analyse['totaal']['budget'];
// Van de status van de bezettingsmodule naar de stand van bh_stoplicht(). Het woord
// staat er altijd bij: een gekleurd bolletje alleen zegt niets tegen wie kleur niet ziet.
$dek_stand = ['groen' => 'goed', 'oranje' => 'krap', 'rood' => 'slecht'];
$dek_woord = ['groen' => 'Gedekt', 'oranje' => 'Krap', 'rood' => 'Tekort'];

require __DIR__ . '/layout_top.php';
?>

<style>
.lt { font-size:12px; border-collapse:collapse; min-width:100%; }
.lt th, .lt td { padding:5px 7px; border:1px solid var(--rand); white-space:nowrap; vertical-align:middle; }
.lt thead th { background:var(--navy); color:var(--op-vol); text-align:center; font-size:11px; }
.lt thead th.sth { position:sticky; left:0; z-index:3; background:var(--navy); color:var(--op-vol); text-align:left; min-width:170px; }
.lt thead th.fth { position:sticky; left:170px; z-index:3; background:var(--navy); color:var(--op-vol); min-width:110px; }
.lt td.scol { position:sticky; left:0; background:var(--bg-card); z-index:1; }
.lt td.fcol { position:sticky; left:170px; background:var(--bg-card); z-index:1; }
.lt tfoot td { background:var(--navy); color:var(--op-vol); font-weight:700; text-align:center; }
.lt tfoot td.scol, .lt tfoot td.fcol { background:var(--navy); color:var(--op-vol); text-align:left; }
.wcel { text-align:center; min-width:66px; }
.wcel.op  { background:var(--ok-soft); }
.wcel.nb  { background:var(--grijs-bg); }
.di { width:34px; text-align:center; border:1px solid var(--rand); border-radius:4px; padding:3px; font-size:11px; font-family:monospace; background:var(--bg-card); color:var(--tekst); }
.di:disabled { background:var(--grijs-bg); color:var(--grijs); }
.di.waarschuw { border-color:var(--accent); background:var(--accent-soft); }
.kl { font-size:10px; color:var(--grijs); display:block; margin-top:1px; }
.fs { font-size:11px; padding:3px 5px; width:100%; border:1px solid var(--rand); border-radius:4px; background:var(--bg-card); color:var(--tekst); }
.totb { position:sticky; bottom:0; background:var(--bg-card); border-top:3px solid var(--oranje); padding:12px 20px; display:flex; gap:20px; align-items:center; flex-wrap:wrap; box-shadow:var(--shadow-float); z-index:5; }
.budget-balk { flex:1; min-width:180px; height:10px; background:var(--grijs-bg); border-radius:5px; overflow:hidden; }
.budget-vul { height:100%; transition:width .3s, background .3s; }
.sc-tab { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border:1px solid var(--rand); border-radius:20px; font-size:12.5px; text-decoration:none; color:var(--tekst); background:var(--bg-card); }
.sc-tab.actief { background:var(--navy); color:var(--op-vol); border-color:var(--navy); }
.jun-brd { border-left:3px solid var(--fase-blauw) !important; }
.jul-brd { border-left:3px solid var(--fase-oranje) !important; }
</style>

<?php if (!$sid): ?>
  <div class="ds-melding ds-melding-error">Geen actief seizoen. <a href="<?= APP_URL ?>/admin/seizoen.php">Stel een seizoen in ↗</a></div>
<?php else: ?>

<!-- Scenario's -->
<div class="ds-kaart">
  <div class="bh-kaartbody bh-kaartbody-compact">
    <div class="bh-lp-scenariorij">
      <span class="bh-lp-scenariolabel">Scenario:</span>
      <?php foreach ($scenarios as $sc): ?>
        <a href="?scenario=<?= (int)$sc['id'] ?>" class="sc-tab <?= (int)$sc['id']===$scenario_id?'actief':'' ?>">
          <?= h($sc['naam']) ?><?php if (!empty($sc['actief'])): ?> <?= icoon('ster', 13) ?> <span class="bh-meta">Actief</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <span class="bh-vulling"></span>
      <a href="<?= APP_URL ?>/admin/export_beschikbaarheid.php?scenario=<?= $scenario_id ?>"
         class="ds-btn ds-btn-secundair ds-btn-sm" title="Exporteer dit scenario als CSV">CSV</a>
      <?php if ($mag_bewerken): ?>
      <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="document.getElementById('dupForm').hidden=false">+ Dupliceren</button>
      <?php $huidig_actief = false; foreach ($scenarios as $sc) if ((int)$sc['id']===$scenario_id && !empty($sc['actief'])) $huidig_actief = true; ?>
      <?php if (!$huidig_actief && $scenario_id): ?>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="sc_actie" value="activeer">
        <input type="hidden" name="scenario_id" value="<?= $scenario_id ?>">
        <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm"><?= icoon('ster', 14) ?> Maak actief</button>
      </form>
      <form method="POST" data-bevestig-titel="Scenario verwijderen?"
            data-bevestig="Alle ingeplande dagen in dit scenario verdwijnen mee. De beschikbaarheid van de sollicitanten blijft ongemoeid."
            data-bevestig-knop="Ja, verwijderen" data-bevestig-gevaar="1">
        <?= csrf_input() ?>
        <input type="hidden" name="sc_actie" value="verwijder">
        <input type="hidden" name="scenario_id" value="<?= $scenario_id ?>">
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm" title="Scenario verwijderen">Verwijderen</button>
      </form>
      <?php endif; ?>
      <?php endif; /* $mag_bewerken */ ?>
    </div>
    <?php if ($mag_bewerken): ?>
    <form method="POST" id="dupForm" class="bh-lp-dupliceer" hidden>
      <?= csrf_input() ?>
      <input type="hidden" name="sc_actie" value="dupliceer">
      <input type="hidden" name="bron_id" value="<?= $scenario_id ?>">
      <input type="text" name="naam" placeholder="Naam van het nieuwe scenario" required
             class="bh-lp-dupnaam">
      <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Dupliceren</button>
      <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.closest('form').hidden=true">Annuleren</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($analyse['mensen'])): ?>
  <div class="ds-kaart"><div class="bh-kaartbody bh-lp-leeg">
    Nog niemand met beschikbaarheid dit seizoen — er valt nog niets te plannen.
  </div></div>
<?php else: ?>

<div id="signaalBox">
<?php if ($analyse['signalen']): ?>
  <div class="ds-kaart bh-signalen">
    <div class="bh-kaartbody bh-kaartbody-compact">
      <div class="bh-signalen-kop">Signalen (<?= count($analyse['signalen']) ?>)</div>
      <div class="bh-signalen-uitleg">Aandachtspunten, geen blokkades — jij beslist.</div>
      <ul class="bh-signalen-lijst">
        <?php foreach (array_slice($analyse['signalen'], 0, 12) as $s): ?>
        <li><?= h($s['tekst']) ?></li>
        <?php endforeach; ?>
        <?php if (count($analyse['signalen']) > 12): ?>
        <li class="bh-tekst-grijs">… en <?= count($analyse['signalen']) - 12 ?> meer</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>
</div>

<?php $alleen_lezen = $mag_bewerken ? '' : ' disabled'; ?>
<p class="bh-lp-uitleg">
  <?php if (!$mag_bewerken): ?>
  Je bekijkt deze planning alleen. Dagen invullen of scenario's wijzigen vraagt bewerkrecht op Loonplanning.
  <?php else: ?>
  Vul per week het aantal dagen in en vink aan wie je <strong>opneemt</strong>. De dekkingsrij onderaan rekent mee met
  wie je daadwerkelijk inplant — plan je de schipper weg, dan zie je die week meteen rood worden.
  <?php endif; ?>
</p>

<div class="bh-lp-tabelrol">
<table class="lt">
  <thead>
    <tr>
      <th class="sth">Naam</th>
      <th class="fth">Functie</th>
      <?php foreach ($weken_datums as $wn => $wd): $per = loonplan_periode($wn, $seizoen); ?>
      <th class="<?= $per==='jun'?'jun-brd':'jul-brd' ?>">
        W<?= $wn ?><br><span class="bh-lp-weekdatum"><?= h(datum_kort_nl($wd['start'])) ?></span>
      </th>
      <?php endforeach; ?>
      <th class="bh-lp-totaalkop">Totaal</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($analyse['mensen'] as $sol_id => $m): ?>
    <tr>
      <td class="scol">
        <a href="<?= APP_URL ?>/admin/sollicitanten.php?panel=<?= $sol_id ?>" class="bh-link-navy"><?= h($m['naam']) ?></a>
        <span class="kl"><?= (int)$m['leeftijd'] ?> jr</span>
      </td>
      <td class="fcol">
        <?php $std_fn = 'lifeguard'; foreach ($m['weken'] as $w) { if (!empty($w['functie'])) { $std_fn = $w['functie']; break; } } ?>
        <select class="fs" id="fn-<?= $sol_id ?>" onchange="functieWijzig(<?= $sol_id ?>)"<?= $alleen_lezen ?>>
          <?php foreach ($functies as $f): ?>
            <option value="<?= h($f['sleutel']) ?>" <?= $std_fn===$f['sleutel']?'selected':'' ?>><?= h($f['naam']) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <?php foreach ($weken_datums as $wn => $wd):
        $w = $m['weken'][$wn] ?? null;
        $beschikbaar = $w !== null;
        $opgen = $beschikbaar ? (int)$w['opgenomen'] : 0;
        $dagen = $beschikbaar ? (int)$w['dagen'] : 0;
        $max   = $beschikbaar ? (int)$w['max'] : 0;
        $teveel = $beschikbaar && $opgen && ($dagen > $max || $dagen > 5);
      ?>
      <td class="wcel <?= !$beschikbaar ? 'nb' : ($opgen ? 'op' : '') ?>"
          title="<?= $beschikbaar ? 'Max ' . $max . ' dagen beschikbaar · €' . number_format($w['dagloon'],2,',','.') . '/dag' : 'Niet beschikbaar deze week' ?>">
        <?php if ($beschikbaar): ?>
          <input type="checkbox" <?= $opgen ? 'checked' : '' ?> id="c-<?= $sol_id ?>-<?= $wn ?>"
                 onchange="celWijzig(<?= $sol_id ?>, <?= $wn ?>)" title="Opnemen in de planning" class="bh-lp-opnemen"<?= $alleen_lezen ?>>
          <br>
          <input type="number" class="di <?= $teveel?'waarschuw':'' ?>" min="0" max="7" value="<?= $dagen ?>"
                 id="d-<?= $sol_id ?>-<?= $wn ?>" onchange="celWijzig(<?= $sol_id ?>, <?= $wn ?>)" <?= ($opgen && $mag_bewerken) ? '' : 'disabled' ?>>
          <span class="kl" id="k-<?= $sol_id ?>-<?= $wn ?>"><?= $opgen && $w['kosten'] > 0 ? '€' . number_format($w['kosten'],0,',','.') : '' ?></span>
        <?php else: ?>
          <span class="bh-lp-nb">–</span>
        <?php endif; ?>
      </td>
      <?php endforeach; ?>
      <td class="bh-lp-persoontotaal">
        <span id="pk-<?= $sol_id ?>">&euro;<?= number_format($m['totaal_kosten'],0,',','.') ?></span>
        <span class="kl" id="pd-<?= $sol_id ?>"><?= (int)$m['totaal_dagen'] ?> dgn</span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td class="scol">Dekking</td>
      <td class="fcol bh-lp-dekuitleg">o.b.v. ingeplande mensen</td>
      <?php foreach ($weken_datums as $wn => $wd): $dk = $analyse['dekking'][$wn] ?? null; ?>
      <td id="dek-<?= $wn ?>" title="<?= $dk ? h(ucfirst($dk['status'])) . ' — ' . (int)$dk['ingezet'] . ' ingezet' : 'Niemand ingepland' ?>">
        <?= $dk
              ? bh_stoplicht($dek_stand[$dk['status']] ?? 'goed', $dek_woord[$dk['status']] ?? '—')
              : '<span class="bh-meta">—</span>' ?>
      </td>
      <?php endforeach; ?>
      <td></td>
    </tr>
    <tr>
      <td class="scol">Kosten p/w</td>
      <td class="fcol"></td>
      <?php foreach ($weken_datums as $wn => $wd): $k = $analyse['per_week'][$wn]['kosten'] ?? 0; ?>
      <td id="wk-<?= $wn ?>" class="bh-lp-weekkosten"><?= $k > 0 ? '&euro;' . number_format($k,0,',','.') : '–' ?></td>
      <?php endforeach; ?>
      <td id="totKostenCel">&euro;<?= number_format($analyse['totaal']['kosten'],0,',','.') ?></td>
    </tr>
  </tfoot>
</table>
</div>

<details class="bh-lp-loontabel">
  <summary>Loontabel (dagloon per leeftijd &amp; functie)</summary>
  <div class="ds-kaart">
    <table>
      <thead>
        <tr>
          <th class="bh-lp-loontabel-leeftijd">Leeftijd</th>
          <?php foreach ($functies as $f): ?><th colspan="2"><?= h($f['naam']) ?></th><?php endforeach; ?>
        </tr>
        <tr class="bh-lp-loontabel-periodes">
          <th></th>
          <?php foreach ($functies as $f): ?><th>juni</th><th>jul–sep</th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php for ($l = 15; $l <= 22; $l++): ?>
        <tr>
          <td class="bh-lp-loontabel-jr"><?= $l ?> jr</td>
          <?php foreach ($functies as $f):
            $dj = bereken_dagloon($l, $f['sleutel'], 'jun');
            $dz = bereken_dagloon($l, $f['sleutel'], 'jul_sep');
          ?>
          <td>&euro;<?= number_format($dj,2,',','.') ?></td>
          <td>&euro;<?= number_format($dz,2,',','.') ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</details>

<?php endif; ?>

<div class="totb">
  <div>
    <div class="bh-lp-totlabel">Totale loonkosten</div>
    <div class="bh-lp-totgetal" id="totKosten">&euro;<?= number_format($analyse['totaal']['kosten'],2,',','.') ?></div>
  </div>
  <div>
    <div class="bh-lp-totlabel">Mandagen</div>
    <div class="bh-lp-totgetal" id="totDagen"><?= (int)$analyse['totaal']['dagen'] ?></div>
  </div>
  <?php if ($budget > 0):
    $pct = min(100, ($analyse['totaal']['kosten'] / $budget) * 100);
    $over = !empty($analyse['totaal']['over_budget']);
  ?>
  <div class="bh-lp-budget">
    <div class="bh-lp-budgetregel">
      <span>Budget &euro;<?= number_format($budget,0,',','.') ?></span>
      <span id="restantLabel" class="bh-lp-restant <?= $over ? 'bh-tekst-fout' : 'bh-tekst-ok' ?>">
        <?= $over
            ? 'Over budget: &euro;' . number_format(abs((float)$analyse['totaal']['restant']),0,',','.')
            : 'Restant: &euro;' . number_format((float)$analyse['totaal']['restant'],0,',','.') ?>
      </span>
    </div>
    <div class="budget-balk">
      <div class="budget-vul <?= $over ? 'bh-budget-over' : ($pct > 85 ? 'bh-budget-krap' : 'bh-budget-ok') ?>" id="budgetVul"
           style="--w: <?= $pct ?>%"></div>
    </div>
  </div>
  <?php else: ?>
  <div class="bh-lp-geenbudget">
    Geen budget ingesteld — <a href="<?= APP_URL ?>/admin/instellingen.php?tab=loon" class="bh-link-navy">stel er een in</a> om de voortgang te volgen.
  </div>
  <?php endif; ?>
</div>

<script>
const SCENARIO_ID = <?= (int)$scenario_id ?>;
const BUDGET = <?= (float)$budget ?>;
/* Alleen UI: de server weigert zelf ook elke opslag zonder bewerkrecht. */
const MAG_BEWERKEN = <?= $mag_bewerken ? 'true' : 'false' ?>;
/* Zelfde stoplicht als de server rendert: stand voor de kleur, woord voor de tekst. */
const DEK_STAND = {groen:'goed', oranje:'krap', rood:'slecht'};
const DEK_WOORD = {groen:'Gedekt', oranje:'Krap', rood:'Tekort'};
function dekStoplicht(status) {
  const stand = DEK_STAND[status] || 'goed';
  const kleur = stand === 'goed' ? 'groen' : (stand === 'krap' ? 'oranje' : 'rood');
  return '<span class="bh-stoplicht ds-fase-' + kleur + '"><span class="bh-stoplicht-bol"></span>'
       + (DEK_WOORD[status] || '') + '</span>';
}

function functieWijzig(solId) {
  // Functie geldt voor alle weken van deze persoon: elke ingevulde week opnieuw opslaan
  document.querySelectorAll('[id^="d-' + solId + '-"]').forEach(inp => {
    const wk = parseInt(inp.id.split('-')[2]);
    celWijzig(solId, wk);
  });
}

function celWijzig(solId, week) {
  const chk  = document.getElementById('c-' + solId + '-' + week);
  const dagI = document.getElementById('d-' + solId + '-' + week);
  const fnS  = document.getElementById('fn-' + solId);
  if (!MAG_BEWERKEN || !chk || !dagI) return;

  dagI.disabled = !chk.checked;
  if (chk.checked && (!dagI.value || parseInt(dagI.value) === 0)) dagI.value = 5;

  const body = new URLSearchParams({
    ajax_loon: '1', scenario_id: SCENARIO_ID, sol_id: solId, week_nr: week,
    dagen: dagI.value || 0, opgenomen: chk.checked ? '1' : '0',
    functie: fnS ? fnS.value : '', _csrf: CSRF
  });

  fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r => r.json())
    .then(d => { if (d.ok) werkBij(d); })
    .catch(() => {});
}

function werkBij(d) {
  document.getElementById('totKosten').textContent = '\u20ac' + fmt(d.totaal.kosten, 2);
  document.getElementById('totDagen').textContent = d.totaal.dagen;
  const tkc = document.getElementById('totKostenCel');
  if (tkc) tkc.textContent = '\u20ac' + fmt(d.totaal.kosten, 0);

  if (BUDGET > 0) {
    const pct = Math.min(100, (d.totaal.kosten / BUDGET) * 100);
    const vul = document.getElementById('budgetVul');
    if (vul) {
      vul.style.setProperty('--w', pct + '%');
      vul.classList.toggle('bh-budget-over', !!d.totaal.over_budget);
      vul.classList.toggle('bh-budget-krap', !d.totaal.over_budget && pct > 85);
      vul.classList.toggle('bh-budget-ok', !d.totaal.over_budget && pct <= 85);
    }
    const lbl = document.getElementById('restantLabel');
    if (lbl) {
      lbl.textContent = d.totaal.over_budget
        ? 'Over budget: \u20ac' + fmt(Math.abs(d.totaal.restant), 0)
        : 'Restant: \u20ac' + fmt(d.totaal.restant, 0);
      lbl.classList.toggle('bh-tekst-fout', !!d.totaal.over_budget);
      lbl.classList.toggle('bh-tekst-ok', !d.totaal.over_budget);
    }
  }

  if (d.persoon) {
    const pk = document.getElementById('pk-' + d.persoon.sol_id);
    const pd = document.getElementById('pd-' + d.persoon.sol_id);
    if (pk) pk.textContent = '\u20ac' + fmt(d.persoon.kosten, 0);
    if (pd) pd.textContent = d.persoon.dagen + ' dgn';
  }

  document.querySelectorAll('[id^="wk-"]').forEach(td => {
    const wk = td.id.split('-')[1];
    const k = d.per_week[wk] ? d.per_week[wk].kosten : 0;
    td.textContent = k > 0 ? '\u20ac' + fmt(k, 0) : '\u2013';
  });

  document.querySelectorAll('[id^="dek-"]').forEach(td => {
    const wk = td.id.split('-')[1];
    const dk = d.dekking[wk];
    if (dk) {
      td.innerHTML = dekStoplicht(dk.status);
      td.title = (DEK_WOORD[dk.status] || dk.status) + ' \u2014 ' + dk.ingezet + ' ingezet';
    } else {
      td.innerHTML = '<span class="bh-meta">\u2014</span>';
      td.title = 'Niemand ingepland';
    }
  });

  const box = document.getElementById('signaalBox');
  if (box) {
    if (d.signalen && d.signalen.length) {
      const items = d.signalen.slice(0, 12).map(s => '<li>' + esc(s.tekst) + '</li>').join('');
      const meer = d.signalen.length > 12 ? '<li class="bh-tekst-grijs">\u2026 en ' + (d.signalen.length - 12) + ' meer</li>' : '';
      box.innerHTML =
        '<div class="ds-kaart bh-signalen"><div class="bh-kaartbody bh-kaartbody-compact">' +
        '<div class="bh-signalen-kop">Signalen (' + d.signalen.length + ')</div>' +
        '<div class="bh-signalen-uitleg">Aandachtspunten, geen blokkades \u2014 jij beslist.</div>' +
        '<ul class="bh-signalen-lijst">' + items + meer + '</ul></div></div>';
    } else {
      box.innerHTML = '';
    }
  }
}

function fmt(n, dec) {
  return Number(n).toLocaleString('nl-NL', {minimumFractionDigits:dec, maximumFractionDigits:dec});
}
function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
</script>

<?= bh_versheid(bh_laatst(
      "SELECT MAX(lp.bijgewerkt_op) FROM loon_planning lp
       JOIN sollicitaties s ON s.id = lp.sollicitatie_id WHERE s.seizoen_id = ?", [$sid]), 'Planning') ?>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

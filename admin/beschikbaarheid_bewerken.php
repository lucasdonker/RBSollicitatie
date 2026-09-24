<?php
/**
 * Geavanceerd beheer-scherm: beschikbaarheid van één sollicitant aanpassen.
 * Twee manieren:
 *   1. Per dag — kalender waarin het bestuur per datum de status omzet (beschikbaar/niet).
 *   2. Formulier — dezelfde stijl als het portaal (periodes + uitzonderingen).
 *
 * Bestuurs-aanpassingen worden de eindwaarheid (ook in het portaal van de sollicitant,
 * met melding) en zijn herleidbaar: wie + wanneer wordt vastgelegd.
 */
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Beschikbaarheid aanpassen';
$actieve_nav  = 'beschikbaarheid';
vereis_recht('beschikbaarheid', 'bewerken');

$db  = db();
$id  = (int)($_GET['id'] ?? 0);
$bhd = huidige_beheerder();
$beheerder_id = (int)($bhd['id'] ?? 0);

if (!$id) { flash('fout', 'Geen sollicitant opgegeven.'); redirect(APP_URL . '/admin/beschikbaarheid.php'); }

// Sollicitant + seizoen ophalen
$q = $db->prepare("SELECT s.*, p.voornaam, p.tussenvoegsel, p.achternaam, sz.naam AS seizoen_naam,
                          sz.datum_werk_start, sz.datum_werk_eind
                   FROM sollicitaties s
                   JOIN personen p ON p.id = s.persoon_id
                   JOIN seizoenen sz ON sz.id = s.seizoen_id
                   WHERE s.id = ?");
$q->execute([$id]);
$sol = $q->fetch();
if (!$sol) { flash('fout', 'Sollicitant niet gevonden.'); redirect(APP_URL . '/admin/beschikbaarheid.php'); }

$naam = trim($sol['voornaam'] . ' ' . ($sol['tussenvoegsel'] ? $sol['tussenvoegsel'] . ' ' : '') . $sol['achternaam']);
$van = $sol['datum_werk_start'] ?? null;
$tot = $sol['datum_werk_eind'] ?? null;

// ── POST-verwerking ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'dag_zet') {
        // Eén dag omzetten (per-dag tab)
        $datum = $_POST['datum'] ?? '';
        $soort = $_POST['soort'] ?? '';   // 'beschikbaar' | 'niet_beschikbaar'
        $reden = $_POST['reden'] ?? null;
        if (function_exists('beschikbaarheid_dag_bestuur_zet')) {
            beschikbaarheid_dag_bestuur_zet($id, $datum, $soort, $reden, $beheerder_id);
        }
        flash('succes', 'Dag bijgewerkt: ' . h($datum) . '.');
        redirect(APP_URL . '/admin/beschikbaarheid_bewerken.php?id=' . $id . '#perdag');
    }

    if ($actie === 'dag_herstel') {
        // Bestuurs-override voor één dag terugdraaien
        $datum = $_POST['datum'] ?? '';
        if (function_exists('beschikbaarheid_dag_bestuur_verwijder')) {
            beschikbaarheid_dag_bestuur_verwijder($id, $datum);
        }
        flash('succes', 'Aanpassing teruggedraaid voor ' . h($datum) . '. De eigen invoer van de sollicitant geldt weer.');
        redirect(APP_URL . '/admin/beschikbaarheid_bewerken.php?id=' . $id . '#perdag');
    }

    if ($actie === 'formulier_opslaan') {
        // Werkperiodes namens de sollicitant opslaan (portaal-stijl tab)
        $blokken = [];
        $vans = $_POST['bb_van'] ?? []; $tots = $_POST['bb_tot'] ?? []; $regs = $_POST['bb_regime'] ?? [];
        $wds  = $_POST['bb_wd'] ?? [];
        foreach ($vans as $i => $v) {
            $vw = (int)$v; $tw = (int)($tots[$i] ?? 0);
            if ($vw < 1 || $tw < 1) continue;
            $wd = json_decode((string)($wds[$i] ?? ''), true);
            $blokken[] = [
                'van_week'      => $vw,
                'tot_week'      => $tw,
                'regime'        => (($regs[$i] ?? 'vijf') === 'weekend') ? 'weekend' : 'vijf',
                'weekend_dagen' => is_array($wd) && $wd ? $wd : [5,6,7],
            ];
        }
        // Behoud alles van de sollicitant (vakanties, verzoeken, vaste dagen); vervang alleen de blokken.
        $invoer = beschikbaarheid2_data_naar_invoer(beschikbaarheid2_van($id));
        $invoer['blokken'] = $blokken;
        beschikbaarheid2_opslaan($id, $invoer, 'beheer');
        flash('succes', 'Werkperiodes opgeslagen namens ' . h($naam) . '.');
        if (function_exists('audit_log')) audit_log('gewijzigd', 'beschikbaarheid', $id, ['via' => 'bestuur_formulier', 'blokken' => count($blokken)]);
        redirect(APP_URL . '/admin/beschikbaarheid_bewerken.php?id=' . $id . '#formulier');
    }
}

// ── Data voor de weergave ────────────────────────────────────────────
$heeft_periode = ($van && $tot);
$per_dag = ($heeft_periode && function_exists('beschikbaarheid_per_dag'))
    ? beschikbaarheid_per_dag($id, $van, $tot) : [];

// Welke dagen zijn door het bestuur aangepast? (voor markering + herstel-knop)
$bestuur_dagen = [];
if (function_exists('beschikbaarheid_dagen')) {
    foreach (beschikbaarheid_dagen($id) as $d) {
        if (($d['bron'] ?? '') === 'bestuur') $bestuur_dagen[$d['datum']] = $d;
    }
}

$dagen_nl   = [1=>'ma',2=>'di',3=>'wo',4=>'do',5=>'vr',6=>'za',7=>'zo'];
$maanden_nl = [1=>'januari',2=>'februari',3=>'maart',4=>'april',5=>'mei',6=>'juni',
               7=>'juli',8=>'augustus',9=>'september',10=>'oktober',11=>'november',12=>'december'];

require __DIR__ . '/layout_top.php';
?>

<div class="bh-bb">
  <div class="bh-bb-kop">
    <h2 class="bh-bb-naam"><?= h($naam) ?></h2>
    <span class="bh-bb-sub">beschikbaarheid<?php
      // Het seizoen staat er alleen bij als de query het meegaf; de naam is een extraatje,
      // geen reden om het scherm te laten struikelen.
      if (!empty($sol['seizoen_naam'])) echo ' · seizoen ' . h((string)$sol['seizoen_naam']);
    ?></span>
  </div>
  <p class="bh-bb-intro">
    Aanpassingen die je hier maakt worden de nieuwe waarheid — ook in het eigen overzicht van de sollicitant (met melding).
    Elke wijziging wordt vastgelegd (wie en wanneer).
  </p>

  <?php if (!$heeft_periode): ?>
    <div class="bh-bb-waarschuwing">
      Voor dit seizoen zijn nog geen werk-start/eind-datums ingesteld. Stel die eerst in bij
      <a href="<?= APP_URL ?>/admin/seizoen.php">Seizoen</a>, dan kun je hier per dag werken.
    </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="bh-bb-tabs" role="tablist">
    <button type="button" class="bb-tab bh-bb-tab bb-tab-actief" data-tab="perdag" onclick="bbTab('perdag')">
      Per dag
    </button>
    <button type="button" class="bb-tab bh-bb-tab" data-tab="formulier" onclick="bbTab('formulier')">
      Formulier (portaal-stijl)
    </button>
  </div>

  <!-- ══ TAB 1: PER DAG ══ -->
  <div id="bb-perdag" class="bb-paneel">
    <?php if ($heeft_periode): ?>
    <p class="bh-bb-uitleg">
      Klik op een dag om de status om te zetten. <span class="bh-tekst-ok">Groen = beschikbaar</span>,
      <span class="bh-tekst-fout">rood = niet</span>. Een <strong>blauw randje</strong> betekent: door het bestuur aangepast.
    </p>

    <?php
    // Groepeer per maand voor een kalender-achtige weergave
    $huidige_maand = null;
    foreach ($per_dag as $datum => $info):
        $ts = strtotime($datum);
        $maand = (int)date('n', $ts);
        $weekdag = (int)date('N', $ts);
        $is_bestuur = isset($bestuur_dagen[$datum]);
        if ($maand !== $huidige_maand):
            if ($huidige_maand !== null) echo '</div>';
            $huidige_maand = $maand; ?>
            <div class="bh-bb-maand"><?= h(ucfirst($maanden_nl[$maand])) ?> <?= h(date('Y', $ts)) ?></div>
            <div class="bh-bb-dagen">
        <?php endif; ?>
        <div class="bb-dag bh-bb-dag <?= $info['beschikbaar'] ? 'bh-bb-dag-ja' : 'bh-bb-dag-nee' ?><?= $is_bestuur ? ' bh-bb-dag-bestuur' : '' ?>" data-datum="<?= h($datum) ?>" data-beschikbaar="<?= $info['beschikbaar'] ? '1' : '0' ?>"
             data-bestuur="<?= $is_bestuur ? '1' : '0' ?>"
             onclick="bbDagKlik(this)">
          <div class="bh-bb-dag-wd"><?= h($dagen_nl[$weekdag]) ?></div>
          <div class="bh-bb-dag-datum"><?= h(date('d-m', $ts)) ?></div>
          <div class="bh-bb-dag-icoon"><?= icoon($info['beschikbaar'] ? 'vink' : 'kruis', 14) ?></div>
        </div>
    <?php endforeach; ?>
    <?php if ($huidige_maand !== null) echo '</div>'; ?>

    <!-- Verborgen formulieren voor de per-dag acties -->
    <form method="post" id="bbDagForm" hidden>
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="dag_zet">
      <input type="hidden" name="datum" id="bbDagDatum">
      <input type="hidden" name="soort" id="bbDagSoort">
      <input type="hidden" name="reden" id="bbDagReden">
    </form>
    <form method="post" id="bbHerstelForm" hidden>
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="dag_herstel">
      <input type="hidden" name="datum" id="bbHerstelDatum">
    </form>

    <!-- Popover voor een dag (verschijnt bij klik) -->
    <div id="bbDagPopover" class="bh-bb-pop" hidden>
      <div id="bbPopDatum" class="bh-bb-pop-datum"></div>
      <div class="bh-bb-pop-acties">
        <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm bh-bb-pop-knop" onclick="bbZet('beschikbaar')"><?= icoon('vink', 14) ?> Beschikbaar maken</button>
        <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm bh-bb-pop-knop" onclick="bbZet('niet_beschikbaar')"><?= icoon('kruis', 14) ?> Niet beschikbaar maken</button>
        <input type="text" id="bbPopReden" placeholder="reden (optioneel)" class="bh-bb-pop-reden">
        <button type="button" class="btn btn-sm bh-bb-herstel" id="bbHerstelKnop" onclick="bbHerstel()" hidden>↩ Aanpassing terugdraaien</button>
      </div>
      <button type="button" onclick="bbSluitPop()" class="bh-bb-pop-sluit"><?= icoon('kruis', 14) ?></button>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══ TAB 2: FORMULIER (portaal-stijl) ══ -->
  <div id="bb-formulier" class="bb-paneel" hidden>
    <p class="bh-bb-uitleg">
      Vul de werkperiodes in namens de sollicitant, in dezelfde vorm als het portaal. Dit overschrijft
      de periode-invoer van de sollicitant. Voor losse dag-correcties gebruik je de tab "Per dag".
    </p>
    <?php
      // Bestaande blokken ophalen voor voorinvulling
      $bb_blokken = [];
      if (function_exists('beschikbaarheid2_van')) {
          $bb_data = beschikbaarheid2_van($id);
          $bb_blokken = $bb_data['blokken'] ?? [];
      }
      // Weekopties uit de seizoensperiode
      $wk_start = $heeft_periode ? (int)(new DateTime($van))->format('W') : 22;
      $wk_eind  = $heeft_periode ? (int)(new DateTime($tot))->format('W') : 35;
    ?>
    <form method="post" action="<?= APP_URL ?>/admin/beschikbaarheid_bewerken.php?id=<?= $id ?>">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="formulier_opslaan">
      <div class="bh-bb-blokken">
        <div class="bh-bb-blokkop">Werkperiodes</div>
        <div id="bbBlokLijst">
          <?php if ($bb_blokken): foreach ($bb_blokken as $bi => $blok):
            $is_wknd = ($blok['regime'] ?? '') === 'weekend'; ?>
          <div class="bb-blok-rij bh-bb-blokrij">
            <input type="hidden" name="bb_wd[]" value="<?= h(json_encode($blok['weekend_dagen'] ?: [5,6,7])) ?>">
            <span class="bh-bb-wk">week</span>
            <input type="number" name="bb_van[]" value="<?= (int)$blok['van_week'] ?>" min="1" max="53" class="bh-bb-week" aria-label="Van week">
            <span class="bh-bb-wk">t/m</span>
            <input type="number" name="bb_tot[]" value="<?= (int)$blok['tot_week'] ?>" min="1" max="53" class="bh-bb-week" aria-label="Tot week">
            <select name="bb_regime[]" class="bh-bb-regime" aria-label="Regime">
              <option value="vijf" <?= !$is_wknd?'selected':'' ?>>hele weken</option>
              <option value="weekend" <?= $is_wknd?'selected':'' ?>>alleen weekenden</option>
            </select>
            <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.parentElement.remove()" aria-label="Verwijderen"><?= icoon('kruis', 14) ?></button>
          </div>
          <?php endforeach; else: ?>
          <div class="bb-blok-rij bh-bb-blokrij">
            <input type="hidden" name="bb_wd[]" value="">
            <span class="bh-bb-wk">week</span>
            <input type="number" name="bb_van[]" value="<?= $wk_start ?>" min="1" max="53" class="bh-bb-week" aria-label="Van week">
            <span class="bh-bb-wk">t/m</span>
            <input type="number" name="bb_tot[]" value="<?= $wk_eind ?>" min="1" max="53" class="bh-bb-week" aria-label="Tot week">
            <select name="bb_regime[]" class="bh-bb-regime" aria-label="Regime">
              <option value="vijf">hele weken</option>
              <option value="weekend">alleen weekenden</option>
            </select>
            <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.parentElement.remove()" aria-label="Verwijderen"><?= icoon('kruis', 14) ?></button>
          </div>
          <?php endif; ?>
        </div>
        <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="bbBlokToevoegen()">+ Periode toevoegen</button>
      </div>
      <button type="submit" class="ds-btn ds-btn-primair">Opslaan namens sollicitant</button>
    </form>
  </div>

  <div class="bh-bb-export">
    <a href="<?= APP_URL ?>/admin/export_dagbeschikbaarheid.php?id=<?= $id ?>" target="_blank" class="ds-btn ds-btn-secundair ds-btn-sm">
      Bekijk de dag-export (API-check)
    </a>
  </div>
</div>

<script>
function bbTab(naam) {
  document.querySelectorAll('.bb-paneel').forEach(p => p.hidden = true);
  document.getElementById('bb-' + naam).hidden = false;
  document.querySelectorAll('.bb-tab').forEach(t => {
    t.classList.toggle('bb-tab-actief', t.dataset.tab === naam);
  });
}

let bbHuidigeDatum = null;
function bbDagKlik(el) {
  bbHuidigeDatum = el.dataset.datum;
  const pop = document.getElementById('bbDagPopover');
  document.getElementById('bbPopDatum').textContent = bbHuidigeDatum;
  document.getElementById('bbPopReden').value = '';
  // Toon herstel-knop alleen als deze dag door bestuur is aangepast (blauw randje)
  const isBestuur = el.style.borderColor && el.style.borderColor !== 'transparent' && getComputedStyle(el).borderColor !== 'rgba(0, 0, 0, 0)';
  document.getElementById('bbHerstelKnop').hidden = el.getAttribute('data-bestuur') !== '1';
  // Positioneer de popover bij de dag
  const r = el.getBoundingClientRect();
  pop.hidden = false;
  let top = r.bottom + 8, left = r.left;
  if (left + 260 > window.innerWidth) left = window.innerWidth - 270;
  if (top + 200 > window.innerHeight) top = r.top - 200;
  pop.style.top = top + 'px';
  pop.style.left = left + 'px';
}
function bbSluitPop() { document.getElementById('bbDagPopover').hidden = true; }
function bbZet(soort) {
  if (!bbHuidigeDatum) return;
  document.getElementById('bbDagDatum').value = bbHuidigeDatum;
  document.getElementById('bbDagSoort').value = soort;
  document.getElementById('bbDagReden').value = document.getElementById('bbPopReden').value;
  document.getElementById('bbDagForm').submit();
}
function bbHerstel() {
  if (!bbHuidigeDatum) return;
  document.getElementById('bbHerstelDatum').value = bbHuidigeDatum;
  document.getElementById('bbHerstelForm').submit();
}
// Klik buiten de popover sluit 'm
document.addEventListener('click', function(e) {
  const pop = document.getElementById('bbDagPopover');
  if (pop && !pop.hidden && !pop.contains(e.target) && !e.target.closest('.bb-dag')) {
    bbSluitPop();
  }
});
// Open direct de juiste tab bij #formulier of #perdag in de URL
if (location.hash === '#formulier') bbTab('formulier');

function bbBlokToevoegen() {
  const div = document.createElement('div');
  div.className = 'bb-blok-rij bh-bb-blokrij';
  div.innerHTML = '<input type="hidden" name="bb_wd[]" value="">' +
    '<span class="bh-bb-wk">week</span>' +
    '<input type="number" name="bb_van[]" min="1" max="53" class="bh-bb-week" aria-label="Van week">' +
    '<span class="bh-bb-wk">t/m</span>' +
    '<input type="number" name="bb_tot[]" min="1" max="53" class="bh-bb-week" aria-label="Tot week">' +
    '<select name="bb_regime[]" class="bh-bb-regime" aria-label="Regime"><option value="vijf">hele weken</option><option value="weekend">alleen weekenden</option></select>' +
    '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.parentElement.remove()" aria-label="Verwijderen"><?= icoon('kruis', 14) ?></button>';
  document.getElementById('bbBlokLijst').appendChild(div);
}
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>

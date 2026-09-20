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
        foreach ($vans as $i => $v) {
            $vw = (int)$v; $tw = (int)($tots[$i] ?? 0);
            if ($vw < 1 || $tw < 1) continue;
            $blokken[] = [
                'van_week' => $vw,
                'tot_week' => $tw,
                'regime'   => (($regs[$i] ?? 'vijf') === 'weekend') ? 'weekend' : 'vijf',
            ];
        }
        if (function_exists('beschikbaarheid2_opslaan')) {
            // Behoud bestaande uitzonderingen; overschrijf alleen de blokken.
            $bestaand = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($id) : [];
            beschikbaarheid2_opslaan($id, [
                'blokken'           => $blokken,
                'afwezig'           => $bestaand['afwezig'] ?? [],
                'verzoeken'         => $bestaand['verzoeken'] ?? [],
                'vaste_dagen_uit'   => $bestaand['profiel']['vaste_dagen_uit'] ?? [],
                'vaste_toelichting' => $bestaand['profiel']['vaste_toelichting'] ?? null,
                'voorlopig'         => $bestaand['profiel']['voorlopig'] ?? 0,
                'opmerking'         => $bestaand['profiel']['opmerking'] ?? null,
            ], 'beheer');
        }
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

<div style="max-width:820px;margin:0 auto">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px;flex-wrap:wrap">
    <h1 style="margin:0;font-size:22px"><?= h($naam) ?></h1>
    <span style="font-size:13px;color:var(--tekst-zacht)">beschikbaarheid · seizoen <?= h($sol['seizoen_naam']) ?></span>
  </div>
  <p style="color:var(--tekst-zacht);font-size:13.5px;margin:0 0 18px">
    Aanpassingen die je hier maakt worden de nieuwe waarheid — ook in het eigen overzicht van de sollicitant (met melding).
    Elke wijziging wordt vastgelegd (wie en wanneer).
  </p>

  <?php if (!$heeft_periode): ?>
    <div style="padding:14px 16px;background:var(--oranje-bg);border:1px solid var(--oranje);border-radius:10px;font-size:13.5px">
      Voor dit seizoen zijn nog geen werk-start/eind-datums ingesteld. Stel die eerst in bij
      <a href="<?= APP_URL ?>/admin/seizoen.php">Seizoen</a>, dan kun je hier per dag werken.
    </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div style="display:flex;gap:4px;border-bottom:2px solid var(--rand);margin-bottom:18px" role="tablist">
    <button type="button" class="bb-tab bb-tab-actief" data-tab="perdag" onclick="bbTab('perdag')"
            style="padding:10px 18px;border:none;background:none;font-size:14px;font-weight:600;cursor:pointer;border-bottom:2px solid var(--navy);margin-bottom:-2px;color:var(--navy)">
      📅 Per dag
    </button>
    <button type="button" class="bb-tab" data-tab="formulier" onclick="bbTab('formulier')"
            style="padding:10px 18px;border:none;background:none;font-size:14px;font-weight:500;cursor:pointer;color:var(--tekst-zacht)">
      📝 Formulier (portaal-stijl)
    </button>
  </div>

  <!-- ══ TAB 1: PER DAG ══ -->
  <div id="bb-perdag" class="bb-paneel">
    <?php if ($heeft_periode): ?>
    <p style="font-size:13px;color:var(--tekst-zacht);margin:0 0 14px">
      Klik op een dag om de status om te zetten. <span style="color:var(--groen)">Groen = beschikbaar</span>,
      <span style="color:var(--rood)">rood = niet</span>. Een <strong>blauw randje</strong> betekent: door het bestuur aangepast.
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
            <div style="font-weight:600;font-size:14px;margin:16px 0 8px;color:var(--navy)"><?= h(ucfirst($maanden_nl[$maand])) ?> <?= h(date('Y', $ts)) ?></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php endif; ?>
        <div class="bb-dag" data-datum="<?= h($datum) ?>" data-beschikbaar="<?= $info['beschikbaar'] ? '1' : '0' ?>"
             data-bestuur="<?= $is_bestuur ? '1' : '0' ?>"
             onclick="bbDagKlik(this)"
             style="width:64px;padding:6px 4px;border-radius:8px;text-align:center;cursor:pointer;user-select:none;
                    background:<?= $info['beschikbaar'] ? 'var(--groen-bg)' : 'var(--rood-bg)' ?>;
                    border:2px solid <?= $is_bestuur ? 'var(--navy)' : 'transparent' ?>">
          <div style="font-size:10px;color:var(--tekst-zacht)"><?= h($dagen_nl[$weekdag]) ?></div>
          <div style="font-size:15px;font-weight:600"><?= h(date('d-m', $ts)) ?></div>
          <div style="font-size:13px"><?= $info['beschikbaar'] ? '✓' : '✗' ?></div>
        </div>
    <?php endforeach; ?>
    <?php if ($huidige_maand !== null) echo '</div>'; ?>

    <!-- Verborgen formulieren voor de per-dag acties -->
    <form method="post" id="bbDagForm" style="display:none">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="dag_zet">
      <input type="hidden" name="datum" id="bbDagDatum">
      <input type="hidden" name="soort" id="bbDagSoort">
      <input type="hidden" name="reden" id="bbDagReden">
    </form>
    <form method="post" id="bbHerstelForm" style="display:none">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="dag_herstel">
      <input type="hidden" name="datum" id="bbHerstelDatum">
    </form>

    <!-- Popover voor een dag (verschijnt bij klik) -->
    <div id="bbDagPopover" style="display:none;position:fixed;z-index:100;background:#fff;border:1px solid var(--rand);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.15);padding:16px;width:260px">
      <div id="bbPopDatum" style="font-weight:600;margin-bottom:10px"></div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <button type="button" class="btn btn-outline btn-sm" onclick="bbZet('beschikbaar')" style="justify-content:flex-start">✓ Beschikbaar maken</button>
        <button type="button" class="btn btn-outline btn-sm" onclick="bbZet('niet_beschikbaar')" style="justify-content:flex-start">✗ Niet beschikbaar maken</button>
        <input type="text" id="bbPopReden" placeholder="reden (optioneel)" style="width:100%;font-size:13px">
        <button type="button" class="btn btn-sm" id="bbHerstelKnop" onclick="bbHerstel()" style="display:none;background:none;color:var(--tekst-zacht);border:1px solid var(--rand)">↩ Aanpassing terugdraaien</button>
      </div>
      <button type="button" onclick="bbSluitPop()" style="position:absolute;top:8px;right:10px;border:none;background:none;font-size:16px;cursor:pointer;color:var(--grijs)">✕</button>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══ TAB 2: FORMULIER (portaal-stijl) ══ -->
  <div id="bb-formulier" class="bb-paneel" style="display:none">
    <p style="font-size:13px;color:var(--tekst-zacht);margin:0 0 14px">
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
      <div style="background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:12px;padding:16px;margin-bottom:14px">
        <div style="font-weight:600;font-size:14px;margin-bottom:10px">Werkperiodes</div>
        <div id="bbBlokLijst">
          <?php if ($bb_blokken): foreach ($bb_blokken as $bi => $blok):
            $is_wknd = ($blok['regime'] ?? '') === 'weekend'; ?>
          <div class="bb-blok-rij" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
            <span style="font-size:12px;color:var(--tekst-zacht)">week</span>
            <input type="number" name="bb_van[]" value="<?= (int)$blok['van_week'] ?>" min="1" max="53" style="width:70px" aria-label="Van week">
            <span style="font-size:12px;color:var(--tekst-zacht)">t/m</span>
            <input type="number" name="bb_tot[]" value="<?= (int)$blok['tot_week'] ?>" min="1" max="53" style="width:70px" aria-label="Tot week">
            <select name="bb_regime[]" style="min-width:150px" aria-label="Regime">
              <option value="vijf" <?= !$is_wknd?'selected':'' ?>>hele weken</option>
              <option value="weekend" <?= $is_wknd?'selected':'' ?>>alleen weekenden</option>
            </select>
            <button type="button" class="btn btn-outline btn-sm" onclick="this.parentElement.remove()" aria-label="Verwijderen">✕</button>
          </div>
          <?php endforeach; else: ?>
          <div class="bb-blok-rij" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
            <span style="font-size:12px;color:var(--tekst-zacht)">week</span>
            <input type="number" name="bb_van[]" value="<?= $wk_start ?>" min="1" max="53" style="width:70px" aria-label="Van week">
            <span style="font-size:12px;color:var(--tekst-zacht)">t/m</span>
            <input type="number" name="bb_tot[]" value="<?= $wk_eind ?>" min="1" max="53" style="width:70px" aria-label="Tot week">
            <select name="bb_regime[]" style="min-width:150px" aria-label="Regime">
              <option value="vijf">hele weken</option>
              <option value="weekend">alleen weekenden</option>
            </select>
            <button type="button" class="btn btn-outline btn-sm" onclick="this.parentElement.remove()" aria-label="Verwijderen">✕</button>
          </div>
          <?php endif; ?>
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="bbBlokToevoegen()">+ Periode toevoegen</button>
      </div>
      <button type="submit" class="btn btn-primary">Opslaan namens sollicitant</button>
    </form>
  </div>

  <div style="margin-top:24px">
    <a href="<?= APP_URL ?>/admin/export_dagbeschikbaarheid.php?id=<?= $id ?>" target="_blank" class="btn btn-outline btn-sm">
      📆 Bekijk de dag-export (API-check)
    </a>
  </div>
</div>

<script>
function bbTab(naam) {
  document.querySelectorAll('.bb-paneel').forEach(p => p.style.display = 'none');
  document.getElementById('bb-' + naam).style.display = '';
  document.querySelectorAll('.bb-tab').forEach(t => {
    const actief = t.dataset.tab === naam;
    t.style.borderBottom = actief ? '2px solid var(--navy)' : 'none';
    t.style.color = actief ? 'var(--navy)' : 'var(--tekst-zacht)';
    t.style.fontWeight = actief ? '600' : '500';
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
  document.getElementById('bbHerstelKnop').style.display = el.getAttribute('data-bestuur') === '1' ? '' : 'none';
  // Positioneer de popover bij de dag
  const r = el.getBoundingClientRect();
  pop.style.display = 'block';
  let top = r.bottom + 8, left = r.left;
  if (left + 260 > window.innerWidth) left = window.innerWidth - 270;
  if (top + 200 > window.innerHeight) top = r.top - 200;
  pop.style.top = top + 'px';
  pop.style.left = left + 'px';
}
function bbSluitPop() { document.getElementById('bbDagPopover').style.display = 'none'; }
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
  if (pop && pop.style.display === 'block' && !pop.contains(e.target) && !e.target.closest('.bb-dag')) {
    bbSluitPop();
  }
});
// Open direct de juiste tab bij #formulier of #perdag in de URL
if (location.hash === '#formulier') bbTab('formulier');

function bbBlokToevoegen() {
  const div = document.createElement('div');
  div.className = 'bb-blok-rij';
  div.style.cssText = 'display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:8px';
  div.innerHTML = '<span style="font-size:12px;color:var(--tekst-zacht)">week</span>' +
    '<input type="number" name="bb_van[]" min="1" max="53" style="width:70px" aria-label="Van week">' +
    '<span style="font-size:12px;color:var(--tekst-zacht)">t/m</span>' +
    '<input type="number" name="bb_tot[]" min="1" max="53" style="width:70px" aria-label="Tot week">' +
    '<select name="bb_regime[]" style="min-width:150px" aria-label="Regime"><option value="vijf">hele weken</option><option value="weekend">alleen weekenden</option></select>' +
    '<button type="button" class="btn btn-outline btn-sm" onclick="this.parentElement.remove()" aria-label="Verwijderen">✕</button>';
  document.getElementById('bbBlokLijst').appendChild(div);
}
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>

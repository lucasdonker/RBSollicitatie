<?php
/**
 * Beheer — Loonplanning: wie werkt welke week hoeveel dagen, en wat kost het.
 *
 * Opbouw, van boven naar beneden (zelfde taal als Beschikbaarheid):
 *   1. scenario's + stand (concept/definitief, budget)      admin/lp/_kop.php
 *   2. dekking per week (wie is ingepland tegen de norm)     admin/_dekkinggrafiek.php
 *   3. filters + raster: per cel wat je plant, daaronder wat iemand opgaf   admin/lp/_raster.php
 *   4. zijpaneel: één week van één persoon, of de persoon zelf               admin/lp/_paneel.php
 * Logica: includes/loonplan*.php (planning, afspraak, slot) en includes/beheer/loonplanning.php
 * (filters en cellen). Gedrag: assets/js/loonplanning.js.
 *
 * Lezen mag de planning zien; alles wat schrijft vraagt bewerkrecht. Wie na het TW-gesprek
 * vaststaat (includes/loonplan_slot.php) is in geen enkel scenario te wijzigen zonder te ontgrendelen.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
$pagina_titel = 'Loonplanning';
$actieve_nav  = 'loonplanning';
$pagina_breed = true;
vereis_recht('loonplanning', 'lezen');
$mag_bewerken = mag('loonplanning', 'bewerken');
try { instellingen_laden(); } catch (Exception $e) {}

$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;
$jaar    = loonplan_jaar($seizoen);
$ik      = (int)($_SESSION['bhd_id'] ?? 0);

// ── Scenario bepalen ─────────────────────────────────────────
$scenarios = $sid ? loonplan_scenarios($sid) : [];
$actief_sc = $sid ? loonplan_actief_scenario($sid) : null;
$scenario_id = isset($_GET['scenario']) ? (int)$_GET['scenario'] : (int)($actief_sc['id'] ?? 0);
if (!in_array($scenario_id, array_map('intval', array_column($scenarios, 'id')), true) && $scenarios) $scenario_id = (int)$scenarios[0]['id'];
$terug = APP_URL . '/admin/loonplanning.php?scenario=' . $scenario_id;

// ── Scenario- en persoonsacties (gewone formulieren) ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_actie'])) {
    csrf_check();
    if (!$mag_bewerken) { flash('fout', 'Je hebt geen bewerkrecht op de loonplanning.'); redirect($terug); }
    $a   = (string)$_POST['sc_actie'];
    $sol = (int)($_POST['sol_id'] ?? 0);
    if ($a === 'dupliceer') {
        $nieuw = loonplan_scenario_dupliceer($sid, $scenario_id, trim((string)($_POST['naam'] ?? 'Kopie')));
        if ($nieuw) { flash('succes', 'Scenario gedupliceerd. Je werkt nu in de kopie.'); redirect(APP_URL . '/admin/loonplanning.php?scenario=' . $nieuw); }
        flash('fout', 'Dupliceren mislukt.');
    } elseif ($a === 'activeer') {
        loonplan_scenario_activeer($sid, $scenario_id);
        flash('succes', 'Dit scenario is nu de actieve planning (die zie je ook in het TW-gesprek).');
    } elseif ($a === 'verwijder') {
        if (loonplan_scenario_verwijder($sid, $scenario_id)) { flash('succes', 'Scenario verwijderd.'); redirect(APP_URL . '/admin/loonplanning.php'); }
        flash('fout', 'Het laatste scenario kan niet worden verwijderd.');
    } elseif ($a === 'definitief') {
        // Het getoonde scenario wordt de actieve planning én de afspraak van iedereen die is aangenomen.
        loonplan_scenario_activeer($sid, $scenario_id);
        $res = loonplan_definitief_maken($sid, $scenario_id, $ik);
        $tekst = 'Loonplanning definitief: ' . $res['aantal'] . ' ' . ($res['aantal'] === 1 ? 'afspraak' : 'afspraken') . ' vastgesteld.';
        if ($res['zonder_planning']) $tekst .= ' Aangenomen maar nergens ingepland (geen afspraak gemaakt): ' . implode(', ', $res['zonder_planning']) . '.';
        flash($res['zonder_planning'] ? 'fout' : 'succes', $tekst);
    } elseif ($a === 'afspraak' && $sol) {
        // Eén persoon opnieuw vaststellen vanuit dit scenario (na een wijziging, of later aangenomen).
        $n = inzet_vaststellen($sid, $scenario_id, [$sol], $ik);
        flash($n ? 'succes' : 'fout', $n ? 'Afspraak bijgewerkt.' : 'Afspraak bijwerken is niet gelukt.');
    } elseif ($a === 'vastzetten' && $sol) {
        flash(loonplan_vastzetten($sid, $sol, $scenario_id, $ik) ? 'succes' : 'fout',
              'Vastgezet: de weken uit dit scenario staan nu in alle scenario\'s en zijn op slot.');
    } elseif ($a === 'ontgrendel' && $sol) {
        $ok = loonplan_ontgrendelen($sid, $sol, (string)($_POST['reden'] ?? ''), $ik);
        flash($ok ? 'succes' : 'fout', $ok ? 'Ontgrendeld. Zet het na je aanpassing weer vast.' : 'Geef een reden op (die komt in het logboek).');
    }
    redirect($terug);
}

/** Het AJAX-antwoord na een wijziging: totalen, per week, en de cellen van één persoon. */
function lp_antwoord(int $sid, int $sc, ?array $seizoen, int $sol): array {
    $an = loonplan_analyse($sid, $sc, $seizoen);
    $m  = $an['mensen'][$sol] ?? null;
    $cellen = [];
    foreach (($m['weken'] ?? []) as $wk => $w) {
        $c = bh_lp_cel($w, (string)$m['naam'], (int)$wk);
        $cellen[$wk] = $c + ['opgenomen' => (int)$w['opgenomen'], 'dagen' => (int)$w['dagen'], 'betaald' => (int)$w['betaald'], 'max' => (int)$w['max']];
    }
    $weken = bh_besch_weken($seizoen);
    return ['ok' => true, 'totaal' => $an['totaal'], 'per_week' => $an['per_week'],
            'tot_per_week' => bh_lp_tot_per_week($an['mensen'], $weken),
            'persoon' => $m ? ['sol_id' => $sol, 'kosten' => $m['totaal_kosten'], 'dagen' => $m['totaal_dagen'], 'onbetaald' => $m['dagen_onbetaald'],
                               'functie' => $m['functie'], 'wijkt_af' => !empty($m['afspraak']['wijkt_af']), 'cellen' => $cellen] : null];
}

// ── AJAX: de dagen van één week (paneel) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['dagen'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sol = (int)($_GET['sol_id'] ?? 0); $wk = (int)($_GET['week'] ?? 0);
    $wd = seizoen_weken_datums($seizoen);
    $m = $sid ? (loonplan_analyse($sid, $scenario_id, $seizoen)['mensen'][$sol] ?? null) : null;
    if (!$m || !isset($wd[$wk])) { echo json_encode(['ok' => false]); exit; }
    $ma = $wd[$wk]['start']->format('Y-m-d');
    $w  = $m['weken'][$wk];
    $r  = inzet_rooster_week((int)$w['opgenomen'] ? (int)$w['dagen'] : 0, (bool)$w['betaald'],
                             beschikbaarheid_per_dag($sol, $ma, date('Y-m-d', strtotime($ma . ' +6 days'))), (int)$w['max']);
    $r['signalen_tekst'] = array_map('inzet_rooster_signaal_tekst', $r['signalen']);
    echo json_encode(['ok' => true, 'periode' => datum_kort_nl($wd[$wk]['start']) . ' – ' . datum_kort_nl($wd[$wk]['eind'])] + $r);
    exit;
}

// ── AJAX: cel, functie of kopiëren ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_loon'])) {
    header('Content-Type: application/json; charset=utf-8');
    csrf_check();
    if (!$mag_bewerken) { http_response_code(403); echo json_encode(['ok' => false, 'fout' => 'Je hebt geen bewerkrecht op de loonplanning.']); exit; }
    $sol = (int)($_POST['sol_id'] ?? 0);
    if (!$sid || !$scenario_id) { echo json_encode(['ok' => false, 'fout' => 'Geen scenario.']); exit; }
    if (loonplan_slot_in_scenario($scenario_id, $sol)) {
        echo json_encode(['ok' => false, 'fout' => 'Staat vast na het TW-gesprek. Ontgrendel eerst (met een reden).']); exit;
    }
    $wat = (string)($_POST['wat'] ?? 'cel');
    $gelukt = true;
    if ($wat === 'functie') {
        $fn = (string)($_POST['functie'] ?? '');
        $gelukt = in_array($fn, array_column(functies_ophalen(), 'sleutel'), true) && loonplan_functie_zetten($scenario_id, $sol, $fn);
    } elseif ($wat === 'kopieer') {
        // Zelfde plan voor de volgende weken die iemand opgaf, hooguit het opgegeven aantal dagen.
        $van = (int)($_POST['week_nr'] ?? 0); $dagen = (int)($_POST['dagen'] ?? 0); $betaald = ($_POST['betaald'] ?? '1') === '1' ? 1 : 0;
        $m = loonplan_analyse($sid, $scenario_id, $seizoen)['mensen'][$sol] ?? null;
        foreach (($m['weken'] ?? []) as $wk => $w) {
            if ($wk <= $van || (int)$w['max'] <= 0) continue;
            $gelukt = loonplan_cel_opslaan($scenario_id, $sol, (int)$wk, $jaar, min($dagen, (int)$w['max']), $dagen > 0 ? 1 : 0, $betaald) && $gelukt;
        }
    } else {
        $gelukt = loonplan_cel_opslaan($scenario_id, $sol, (int)($_POST['week_nr'] ?? 0), $jaar, (int)($_POST['dagen'] ?? 0),
                                       ($_POST['opgenomen'] ?? '0') === '1' ? 1 : 0, ($_POST['betaald'] ?? '1') === '1' ? 1 : 0);
    }
    echo json_encode($gelukt ? lp_antwoord($sid, $scenario_id, $seizoen, $sol) : ['ok' => false, 'fout' => 'Opslaan lukte niet.']);
    exit;
}

// ── Data ─────────────────────────────────────────────────────
$filter   = bh_lp_filter($_GET);
$analyse  = ($sid && $scenario_id) ? loonplan_analyse($sid, $scenario_id, $seizoen)
          : ['mensen' => [], 'per_week' => [], 'totaal' => ['kosten' => 0, 'dagen' => 0, 'dagen_onbetaald' => 0, 'budget' => 0, 'over_budget' => false, 'restant' => null], 'dekking' => [], 'signalen' => []];
$sloten   = $sid ? loonplan_sloten($sid) : [];
$rijen    = bh_lp_rijen($analyse, $filter, $sloten);
$weken    = bh_besch_weken($seizoen);
$functies = functies_ophalen();
$definitief_op = $sid ? loonplan_definitief_op($sid) : null;
$dekking  = bh_dekking_weken($weken, bh_lp_tot_per_week($analyse['mensen'], $weken), bh_dekking_norm());

require __DIR__ . '/layout_top.php';
?>

<?php if (!$sid): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php $leeg_titel = 'Geen actief seizoen'; $leeg_tekst = 'Stel eerst een seizoen in bij Seizoen.'; $leeg_icoon = 'kalender'; require __DIR__ . '/_leeg.php'; ?>
  </div>
<?php else: ?>

  <?php require __DIR__ . '/lp/_kop.php'; ?>

  <?php if (!$analyse['mensen']): ?>
    <div class="ds-kaart ds-kaart-p">
      <?php $leeg_titel = 'Nog niets te plannen'; $leeg_tekst = 'Zodra sollicitanten hun beschikbaarheid opgeven, staan ze hier.'; $leeg_icoon = 'kalender'; require __DIR__ . '/_leeg.php'; ?>
    </div>
  <?php else: ?>
    <?php $_dek = $dekking; $_weken = $weken; $_titel = 'Ingepland per week'; $_woord = 'ingepland'; require __DIR__ . '/_dekkinggrafiek.php'; ?>
    <?php require __DIR__ . '/lp/_raster.php'; ?>
    <?php require __DIR__ . '/lp/_paneel.php'; ?>
    <?php require __DIR__ . '/lp/_loontabel.php'; ?>
  <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

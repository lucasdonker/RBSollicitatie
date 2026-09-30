<?php
/**
 * De afspraak-editor in het TW-gesprek (admin/_afspraak_editor.php). Altijd het ACTIEVE scenario
 * van de loonplanning: dat blijft concept tot "Loonplanning definitief maken".
 *
 * GET  sol_id                          → de stand van die persoon (live bijwerken; lezen)
 * POST wat=cel     sol_id, week_nr, dagen, opgenomen (0/1), betaald (0/1)   (taak plannen)
 * POST wat=functie sol_id, functie                                            (taak plannen)
 * POST wat=opgave  sol_id              → planning = eigen opgave (taak plannen)
 * POST wat=vastzetten sol_id           → naar alle scenario's kopiëren + op slot (taak definitief)
 * POST wat=ontgrendelen sol_id, reden  → slot eraf, reden verplicht (taak definitief)
 * Staat iemand op slot, dan weigeren cel en functie (includes/loonplan_slot.php).
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_login();
header('Content-Type: application/json; charset=utf-8');
vereis_recht('loonplanning', 'lezen');

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;
$sc  = $sid ? loonplan_actief_scenario($sid) : null;
$sol = (int)($_REQUEST['sol_id'] ?? 0);
$ik  = (int)($_SESSION['bhd_id'] ?? 0);
if (!$sc || !$sol) { echo json_encode(['ok' => false, 'fout' => 'Geen actief scenario.']); exit; }

// Alleen sollicitaties van dit seizoen.
$q = db()->prepare("SELECT 1 FROM sollicitaties WHERE id=? AND seizoen_id=?");
$q->execute([$sol, $sid]);
if (!$q->fetchColumn()) { echo json_encode(['ok' => false, 'fout' => 'Onbekende sollicitatie.']); exit; }

/** De stand van één persoon in het actieve scenario, zoals de editor hem toont. */
function afspraak_stand(int $sid, array $sc, int $sol, array $seizoen): array {
    $m = loonplan_analyse($sid, (int)$sc['id'], $seizoen, $sol)['mensen'][$sol] ?? null;   // alleen deze persoon (elke 15 s)
    $slot = loonplan_sloten($sid)[$sol] ?? null;
    $weken = [];
    foreach (($m['weken'] ?? []) as $wk => $w) $weken[$wk] = ['opgenomen' => (int)$w['opgenomen'], 'dagen' => (int)$w['dagen'], 'betaald' => (int)$w['betaald']];
    return ['ok' => true, 'functie' => $m['functie'] ?? null, 'weken' => $weken,
            'dagen' => $m['totaal_dagen'] ?? 0, 'onbetaald' => $m['dagen_onbetaald'] ?? 0, 'kosten' => $m['totaal_kosten'] ?? 0,
            'slot' => $slot ? ['op' => date('d-m H:i', strtotime((string)$slot['vergrendeld_op'])),
                               'door' => evenement_claim_naam(isset($slot['vergrendeld_door']) ? (int)$slot['vergrendeld_door'] : null)] : null];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(afspraak_stand($sid, $sc, $sol, $seizoen)); exit; }

csrf_check();
$wat = (string)($_POST['wat'] ?? 'cel');
// Taken (includes/modules/loonplanning.php): vastzetten en ontgrendelen = definitief, de rest = plannen.
$_taak = in_array($wat, ['vastzetten', 'ontgrendelen'], true) ? 'definitief' : 'plannen';
if (!mag('loonplanning', $_taak)) { http_response_code(403); echo json_encode(['ok' => false, 'fout' => 'Dit hoort niet bij jouw taken in de loonplanning.']); exit; }

if ($wat === 'vastzetten') {
    $ok = loonplan_vastzetten($sid, $sol, (int)$sc['id'], $ik);
    echo json_encode($ok ? afspraak_stand($sid, $sc, $sol, $seizoen) : ['ok' => false, 'fout' => 'Vastzetten lukte niet.']);
    exit;
}
if ($wat === 'ontgrendelen') {
    $ok = loonplan_ontgrendelen($sid, $sol, (string)($_POST['reden'] ?? ''), $ik);
    echo json_encode($ok ? afspraak_stand($sid, $sc, $sol, $seizoen) : ['ok' => false, 'fout' => 'Geef een reden op.']);
    exit;
}

if ($wat === 'opgave') {
    // Planning van deze persoon = wat hij of zij zelf opgaf (includes/loonplan.php).
    $n = loonplan_opgave_overnemen((int)$sc['id'], $sol, $seizoen);
    echo json_encode($n !== null ? afspraak_stand($sid, $sc, $sol, $seizoen) : ['ok' => false, 'fout' => 'Staat vast na het gesprek. Klik op "Toch aanpassen" om te ontgrendelen.']);
    exit;
}
if ($wat === 'functie') {
    $fn = (string)($_POST['functie'] ?? '');
    if (!in_array($fn, array_column(functies_ophalen(), 'sleutel'), true)) { echo json_encode(['ok' => false, 'fout' => 'Onbekende functie.']); exit; }
    $gelukt = loonplan_functie_zetten((int)$sc['id'], $sol, $fn);
} else {
    $gelukt = loonplan_cel_opslaan((int)$sc['id'], $sol, (int)($_POST['week_nr'] ?? 0), loonplan_jaar($seizoen),
                                   (int)($_POST['dagen'] ?? 0), ($_POST['opgenomen'] ?? '0') === '1' ? 1 : 0, ($_POST['betaald'] ?? '1') === '1' ? 1 : 0);
}
if (!$gelukt) {
    echo json_encode(['ok' => false, 'fout' => loonplan_slot_in_scenario((int)$sc['id'], $sol)
        ? 'Staat vast na het gesprek. Klik op "Toch aanpassen" om te ontgrendelen.' : 'Opslaan lukte niet.']);
    exit;
}
echo json_encode(afspraak_stand($sid, $sc, $sol, $seizoen));

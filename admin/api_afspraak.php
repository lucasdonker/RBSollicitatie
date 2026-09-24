<?php
/**
 * De afspraak-editor in het TW-gesprek (admin/_afspraak_editor.php) schrijft hierheen.
 * Altijd in het ACTIEVE scenario van de loonplanning: dat blijft concept tot "Loonplanning
 * definitief maken". Recht: loonplanning bewerken (het bestuur heeft dat standaard).
 *
 * POST wat=cel     sol_id, week_nr, dagen, opgenomen (0/1), betaald (0/1)
 * POST wat=functie sol_id, functie
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_login();
header('Content-Type: application/json; charset=utf-8');
vereis_recht('loonplanning', 'bewerken');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }
csrf_check();

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;
$sc  = $sid ? loonplan_actief_scenario($sid) : null;
$sol = (int)($_POST['sol_id'] ?? 0);
if (!$sc || !$sol) { echo json_encode(['ok' => false, 'fout' => 'Geen actief scenario.']); exit; }

// Alleen sollicitaties van dit seizoen.
$q = db()->prepare("SELECT 1 FROM sollicitaties WHERE id=? AND seizoen_id=?");
$q->execute([$sol, $sid]);
if (!$q->fetchColumn()) { echo json_encode(['ok' => false, 'fout' => 'Onbekende sollicitatie.']); exit; }

if (($_POST['wat'] ?? '') === 'functie') {
    $fn = (string)($_POST['functie'] ?? '');
    if (!in_array($fn, array_column(functies_ophalen(), 'sleutel'), true)) { echo json_encode(['ok' => false, 'fout' => 'Onbekende functie.']); exit; }
    loonplan_functie_zetten((int)$sc['id'], $sol, $fn);
} else {
    loonplan_cel_opslaan((int)$sc['id'], $sol, (int)($_POST['week_nr'] ?? 0), loonplan_jaar($seizoen),
                         (int)($_POST['dagen'] ?? 0), ($_POST['opgenomen'] ?? '0') === '1' ? 1 : 0, ($_POST['betaald'] ?? '1') === '1' ? 1 : 0);
}
$m = loonplan_analyse($sid, (int)$sc['id'], $seizoen)['mensen'][$sol] ?? null;
echo json_encode(['ok' => true, 'kosten' => $m['totaal_kosten'] ?? 0, 'dagen' => $m['totaal_dagen'] ?? 0, 'onbetaald' => $m['dagen_onbetaald'] ?? 0]);

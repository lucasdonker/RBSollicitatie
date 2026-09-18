<?php
/**
 * Autosave-concept voor het beschikbaarheidsformulier (tegenhanger van api_blk_concept.php).
 * Bewaart een JSON-snapshot van het formulier zodat een afgebroken sessie later verdergaat waar
 * die was. Wijzigt NIET de echte beschikbaarheid en telt niet als "ingevuld".
 *
 * POST _csrf + concept=<json>  → {ok, tijd}
 * POST _csrf + wis=1           → {ok}
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

if (!function_exists('portaal_ingelogd') || !portaal_ingelogd() || empty($_SESSION['portaal_sol_id'])) {
    echo json_encode(['ok' => false, 'fout' => 'Niet ingelogd']); exit;
}
$sol_id = (int)$_SESSION['portaal_sol_id'];
csrf_check();

if (!empty($_POST['wis'])) {
    if (function_exists('beschikbaarheid2_concept_wissen')) beschikbaarheid2_concept_wissen($sol_id);
    echo json_encode(['ok' => true]); exit;
}

$ruw = (string)($_POST['concept'] ?? '');
if ($ruw === '' || strlen($ruw) > 20000) { echo json_encode(['ok' => false, 'fout' => 'Ongeldig concept']); exit; }
$concept = json_decode($ruw, true);
if (!is_array($concept)) { echo json_encode(['ok' => false, 'fout' => 'Ongeldig concept']); exit; }

// Alleen bekende sleutels bewaren; de inhoud wordt bij het echte opslaan opnieuw gevalideerd.
$toegestaan = ['stap', 'ai_tekst', 'periodes', 'vaste', 'vaste_toelichting', 'vakanties', 'losse', 'verzoeken', 'voorlopig'];
$schoon = array_intersect_key($concept, array_flip($toegestaan));
$schoon['stap'] = ($schoon['stap'] ?? '') === 'wizard' ? 'wizard' : 'formulier';
if (isset($schoon['ai_tekst'])) $schoon['ai_tekst'] = substr((string)$schoon['ai_tekst'], 0, 2000);

if (function_exists('beschikbaarheid2_concept_opslaan') && beschikbaarheid2_concept_opslaan($sol_id, $schoon)) {
    echo json_encode(['ok' => true, 'tijd' => date('H:i')]);
} else {
    echo json_encode(['ok' => false, 'fout' => 'Opslaan mislukt']);
}

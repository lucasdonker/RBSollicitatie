<?php
/**
 * CSV-export van de loonplanning (actief of gekozen scenario).
 * Bedoeld voor de penningmeester: wie werkt hoeveel weken/dagen en wat kost dat.
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_login();
vereis_recht('beschikbaarheid', 'lezen');

$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;
if (!$sid) { http_response_code(400); exit('Geen actief seizoen.'); }

// Scenario: expliciet meegegeven of het actieve
$scenario_id = isset($_GET['scenario']) ? (int)$_GET['scenario'] : 0;
if (!$scenario_id) {
    $sc = function_exists('loonplan_actief_scenario') ? loonplan_actief_scenario($sid) : null;
    $scenario_id = (int)($sc['id'] ?? 0);
}
$scenario_naam = 'planning';
foreach ((function_exists('loonplan_scenarios') ? loonplan_scenarios($sid) : []) as $sc) {
    if ((int)$sc['id'] === $scenario_id) $scenario_naam = $sc['naam'];
}

$analyse = function_exists('loonplan_analyse')
         ? loonplan_analyse($sid, $scenario_id, $seizoen)
         : ['mensen' => [], 'totaal' => ['kosten'=>0,'dagen'=>0,'budget'=>0]];

// Functielabels
$functie_label = [];
foreach ((function_exists('functies_ophalen') ? functies_ophalen() : []) as $f) {
    $functie_label[$f['sleutel']] = $f['naam'];
}

$fn = 'paal13_loonplanning_' . ($seizoen['naam'] ?? 'seizoen') . '_' . $scenario_naam . '_' . date('Y-m-d') . '.csv';
$fn = preg_replace('/[^a-z0-9_\-\.]/i', '_', $fn);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fn . '"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // BOM voor Excel

fputcsv($out, [
    'Naam', 'Leeftijd', 'Functie',
    'Beschikbare weken', 'Ingeplande weken', 'Ingeplande dagen',
    'Totale kosten (EUR)',
], ';');

foreach ($analyse['mensen'] as $m) {
    // Alleen weken die zijn opgenomen in de planning
    $ingepland = 0;
    foreach ($m['weken'] as $w) {
        if (!empty($w['opgenomen']) && (int)$w['dagen'] > 0) $ingepland++;
    }
    // Functie = die van de eerste geplande week
    $fn_sleutel = 'lifeguard';
    foreach ($m['weken'] as $w) { if (!empty($w['functie'])) { $fn_sleutel = $w['functie']; break; } }

    fputcsv($out, [
        $m['naam'],
        (int)$m['leeftijd'],
        $functie_label[$fn_sleutel] ?? $fn_sleutel,
        count($m['beschikbaar']),
        $ingepland,
        (int)$m['totaal_dagen'],
        number_format((float)$m['totaal_kosten'], 2, ',', ''),
    ], ';');
}

// Totaalregel
fputcsv($out, [], ';');
fputcsv($out, [
    'TOTAAL', '', '', '', '',
    (int)$analyse['totaal']['dagen'],
    number_format((float)$analyse['totaal']['kosten'], 2, ',', ''),
], ';');

if (!empty($analyse['totaal']['budget'])) {
    fputcsv($out, ['Budget', '', '', '', '', '', number_format((float)$analyse['totaal']['budget'], 2, ',', '')], ';');
    fputcsv($out, ['Restant', '', '', '', '', '', number_format((float)$analyse['totaal']['restant'], 2, ',', '')], ';');
}

fclose($out);

<?php
/**
 * Wat het rooster nodig heeft: per persoon per week de afgesproken dagen (betaald of niet) en per
 * dag of iemand kan. Opbouw: includes/inzet_rooster.php — dezelfde vorm die de latere
 * roosterkoppeling krijgt.
 *
 *   ?formaat=json|xlsx   (standaard xlsx)
 *   ?bron=auto|afspraak|scenario[&scenario=N]   auto = de afspraak als die definitief is, anders
 *                                               het actieve scenario (concept)
 * Recht: loonplanning lezen, of beschikbaarheid lezen (de planner). Er staan geen bedragen in, alleen
 * dagen en betaald ja/nee. Zonder loonplanning-recht alleen bron=auto: geen scenario's kiezen.
 * Elke export in het auditlog.
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_login();
$mag_loonplanning = mag('loonplanning', 'lezen');
if (!$mag_loonplanning) vereis_recht('beschikbaarheid', 'lezen');
module_vereist('loonplanning');

$seizoen = actief_seizoen();
$sid = $seizoen ? (int)$seizoen['id'] : 0;
if (!$sid) { flash('fout', 'Geen actief seizoen.'); redirect(APP_URL . '/admin/' . ($mag_loonplanning ? 'loonplanning.php' : 'beschikbaarheid.php')); }

$bron = (string)($_GET['bron'] ?? 'auto');
if (!in_array($bron, ['auto', 'afspraak', 'scenario'], true) || !$mag_loonplanning) $bron = 'auto';
$sc = $mag_loonplanning ? (int)($_GET['scenario'] ?? 0) : 0;
if ($sc && !in_array($sc, array_map('intval', array_column(loonplan_scenarios($sid), 'id')), true)) $sc = 0;

$r = inzet_rooster($sid, $bron, $sc);
if (function_exists('audit_log')) audit_log('export', 'rooster', null, ['bron' => $r['bron'], 'formaat' => $_GET['formaat'] ?? 'xlsx', 'personen' => count($r['personen'])]);

if (($_GET['formaat'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="rooster_' . date('Y-m-d') . '.json"');
    echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Excel: één regel per persoon per week.
$functies = array_column(functies_ophalen(), 'naam', 'sleutel');
$rijen = [];
foreach ($r['personen'] as $p) {
    foreach ($p['weken'] as $w) {
        $rijen[] = [$p['naam'], $functies[$p['functie']] ?? $p['functie'], 'W' . $w['week'], ['v' => $w['maandag'], 's' => 'datum'],
                    $w['dagen'], $w['betaald'] ? 'ja' : ['v' => 'nee', 's' => 'let'], $w['opgave_max'] ?: '—',
                    implode(' ', array_map(fn($d) => dag_kort((int)date('N', strtotime($d))), $w['beschikbaar'])),
                    implode(' ', array_map(fn($d) => dag_kort((int)date('N', strtotime($d))), $w['liever_niet'])),
                    $w['signalen'] ? ['v' => implode('; ', array_map('inzet_rooster_signaal_tekst', $w['signalen'])), 's' => 'let'] : ''];
    }
}
$boven = 'Rooster · ' . ($r['bron'] === 'afspraak' ? 'vastgestelde afspraken' : 'CONCEPT (actief scenario)') . ' · ' . date('d-m-Y H:i');
xlsx_download([[
    'naam' => 'Per week', 'boven' => $boven,
    'kop' => ['Naam', 'Functie', 'Week', 'Maandag', 'Dagen', 'Betaald', 'Opgegeven', 'Kan op', 'Liever niet op', 'Let op'],
    'breedte' => [26, 18, 7, 12, 7, 9, 10, 22, 16, 50], 'rijen' => $rijen,
]], 'rooster_' . date('Y-m-d') . '.xlsx');

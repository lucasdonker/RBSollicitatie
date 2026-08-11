<?php
require_once __DIR__ . '/../includes/functions.php';
vereist_rol('bestuur');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$sol_id = (int)($_POST['sol_id'] ?? 0);
$week   = (int)($_POST['week'] ?? 0);
$soort  = $_POST['soort'] ?? '';          // 'niet' | 'minder' | '' (wissen)
$reden  = trim($_POST['reden'] ?? '') ?: null;
$dagen  = isset($_POST['dagen']) ? (array)$_POST['dagen'] : [];

if (!$sol_id || !$week) { echo json_encode(['ok' => false, 'fout' => 'Ongeldig']); exit; }

// Wissen als soort leeg is
$soort_param = in_array($soort, ['niet','minder'], true) ? $soort : null;

// Reden verplicht bij een echte correctie
if ($soort_param && !$reden) { echo json_encode(['ok' => false, 'fout' => 'Geef een reden op.']); exit; }
if ($soort_param === 'minder' && !$dagen) { echo json_encode(['ok' => false, 'fout' => 'Kies minstens één dag.']); exit; }

$door = $_SESSION['bhd_naam'] ?? null;

try {
    beschikbaarheid2_correctie_zetten($sol_id, $week, $soort_param, $dagen, $reden, $door);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'fout' => $e->getMessage()]);
}

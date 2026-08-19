<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('beschikbaarheid', 'bewerken');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$sol_id = (int)($_POST['sol_id'] ?? 0);
$week   = (int)($_POST['week'] ?? 0);
$dag    = (int)($_POST['dag'] ?? 0);

if (!$sol_id || $week < 1 || $week > 53 || $dag < 1 || $dag > 7) {
    echo json_encode(['ok' => false, 'fout' => 'Ongeldige parameters']);
    exit;
}

try {
    $verhinderd = verhindering_toggle($sol_id, $week, $dag, 'bestuur');
    echo json_encode(['ok' => true, 'verhinderd' => $verhinderd]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'fout' => $e->getMessage()]);
}

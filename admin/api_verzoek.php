<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('beschikbaarheid', 'bewerken');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$verzoek_id = (int)($_POST['verzoek_id'] ?? 0);
$besluit    = $_POST['besluit'] ?? '';

if (!$verzoek_id || !in_array($besluit, ['goedkeuren', 'afwijzen'], true)) {
    echo json_encode(['ok' => false, 'fout' => 'Ongeldig']); exit;
}

try {
    beschikbaarheid2_verzoek_beslis($verzoek_id, $besluit);
    echo json_encode(['ok' => true, 'status' => $besluit === 'goedkeuren' ? 'goedgekeurd' : 'afgewezen']);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'fout' => $e->getMessage()]);
}

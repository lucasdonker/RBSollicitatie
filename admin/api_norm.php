<?php
require_once __DIR__ . '/../includes/functions.php';
// De norm (dagen per week) hoort bij de beschikbaarheid, niet bij de selectiedag.
vereis_recht('beschikbaarheid', 'bewerken');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$sol_id  = (int)($_POST['sol_id'] ?? 0);
$dagen   = ($_POST['norm_dagen'] ?? '') === '' ? null : (int)$_POST['norm_dagen'];
$notitie = trim($_POST['norm_notitie'] ?? '') ?: null;

if (!$sol_id) { echo json_encode(['ok' => false, 'fout' => 'Ongeldig']); exit; }

try {
    beschikbaarheid2_norm_zetten($sol_id, $dagen, $notitie);
    echo json_encode(['ok' => true, 'norm_dagen' => $dagen, 'norm_notitie' => $notitie]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'fout' => $e->getMessage()]);
}

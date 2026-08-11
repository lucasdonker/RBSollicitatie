<?php
require_once __DIR__ . '/../includes/functions.php';
vereist_rol('bestuur');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$actie  = $_POST['actie'] ?? '';
$sol_id = (int)($_POST['sol_id'] ?? 0);
if (!$sol_id) { echo json_encode(['ok' => false, 'fout' => 'Ongeldig']); exit; }

if (!competentie_sync_klaar()) {
    echo json_encode(['ok' => false, 'fout' => 'Authentik is nog niet geconfigureerd (zie Instellingen).']);
    exit;
}

try {
    // E-mail van de sollicitant ophalen
    $q = db()->prepare("SELECT p.email FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id WHERE s.id=?");
    $q->execute([$sol_id]);
    $email = $q->fetchColumn();
    if (!$email) { echo json_encode(['ok' => false, 'fout' => 'Geen e-mailadres bekend']); exit; }

    if ($actie === 'koppel') {
        $username = trim($_POST['username'] ?? '');
        if ($username === '') { echo json_encode(['ok' => false, 'fout' => 'Geef een gebruikersnaam op']); exit; }
        competentie_koppel_handmatig($sol_id, $username);
    }

    // (Her)synchroniseer
    $res = competenties_sync($sol_id, $email);
    echo json_encode([
        'ok'      => $res['ok'],
        'melding' => $res['melding'],
        'aantal'  => $res['aantal'] ?? 0,
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'fout' => $e->getMessage()]);
}

<?php
/** Beheer — kanaalvoorkeur (WhatsApp/sms/e-mail) van een persoon wijzigen (JSON). */
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('sollicitanten', 'bewerken');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }
csrf_check();

$pid   = (int)($_POST['persoon_id'] ?? 0);
$keuze = (string)($_POST['kanaal_voorkeur'] ?? '');
if (!$pid || !in_array($keuze, ['email', 'whatsapp', 'sms'], true)) { echo json_encode(['ok' => false, 'fout' => 'Ongeldige invoer']); exit; }
try {
    if (function_exists('personen_kanaal_auto_setup')) personen_kanaal_auto_setup();
    db()->prepare("UPDATE personen SET kanaal_voorkeur=?, kanaal_gewijzigd_op=NOW(), kanaal_bron='beheer' WHERE id=?")->execute([$keuze, $pid]);
    if (function_exists('audit_log')) audit_log('kanaal_voorkeur', 'persoon', $pid, ['naar' => $keuze, 'via' => 'beheer']);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'fout' => 'Opslaan mislukt']);
}

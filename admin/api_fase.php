<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('sollicitanten', 'bewerken');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'fout' => 'Methode niet toegestaan']); exit;
}

csrf_check();

$sol_id = (int)($_POST['sol_id'] ?? 0);
$naar   = $_POST['naar'] ?? '';

if (!$sol_id || !function_exists('fase_zetten') || !isset(fasen()[$naar])) {
    echo json_encode(['ok' => false, 'fout' => 'Ongeldige invoer']); exit;
}

try {
    // Eén centrale fase-functie: zet de fase, zet documenten/kennismakingspakket klaar en
    // verstuurt de bijbehorende melding via de meldingen-kern.
    $res = fase_zetten($sol_id, $naar, 'paneel');
    if (!$res['ok']) { echo json_encode(['ok' => false, 'fout' => $res['fout'] ?? 'Fase wijzigen mislukt']); exit; }

    $mail_status  = $res['melding']['status'] ?? null;
    $mail_melding = fase_melding_tekst($res) ?: null;
    if ($res['ongewijzigd']) $mail_melding = 'Fase was al ' . fase_label($naar) . '.';

    // Bepaal de volgende snelle actie voor in de tabel-rij
    $snelle_actie = match($naar) {
        'aangemeld'          => ['label' => 'Uitnodigen SD', 'bulk' => 'uitnodigen_selectie', 'kleur' => 'groen'],
        'selectiedag_geweest'=> ['label' => 'Uitnodigen TW', 'bulk' => 'uitnodigen_tw',       'kleur' => 'primary'],
        default              => null,
    };

    echo json_encode([
        'ok'           => true,
        'fase'         => $naar,
        'label'        => fase_label($naar),
        'kleur'        => fase_kleur($naar),
        'snelle_actie' => $snelle_actie,
        'mail_status'  => $mail_status,
        'mail_melding' => $mail_melding,
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'fout' => $e->getMessage()]);
}

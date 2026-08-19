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
$geldig = ['aangemeld','uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest','uitgenodigd_tw','tw_geweest','aangenomen','afgewezen'];

if (!$sol_id || !in_array($naar, $geldig, true)) {
    echo json_encode(['ok' => false, 'fout' => 'Ongeldige invoer']); exit;
}

try {
    db()->prepare("UPDATE sollicitaties SET fase = ?, gewijzigd_op = NOW() WHERE id = ?")->execute([$naar, $sol_id]);

    // Actief seizoen ophalen voor mail variabelen
    $seizoen = function_exists('actief_seizoen') ? (actief_seizoen() ?? []) : [];

    // Stuur automatisch uitnodigingsmail bij overgang naar uitgenodigd-fase
    $mail_status = null;
    if ($naar === 'uitgenodigd_selectie' && function_exists('verstuur_fase_mail')) {
        if (function_exists('ondertekeningen_klaarzetten_sd')) ondertekeningen_klaarzetten_sd($sol_id);
        $mail_status = verstuur_fase_mail($sol_id, 'uitnodiging_selectie', '%selectiedag%', $seizoen, 60);
    } elseif ($naar === 'uitgenodigd_tw' && function_exists('verstuur_fase_mail')) {
        $mail_status = verstuur_fase_mail($sol_id, 'uitnodiging_tw', '%trainingsweekend%', $seizoen, 60);
    } elseif ($naar === 'aangenomen' && function_exists('kennismakingspakket_activeren')) {
        // Seed welkom template en activeer kennismakingspakket
        if (function_exists('welkom_template_seed')) welkom_template_seed();
        $kmp = kennismakingspakket_activeren($sol_id);
        $mail_status = $kmp['mail_verstuurd'] ? 'verstuurd' : 'fout';
    }

    $mail_melding = null;
    if     ($mail_status === 'verstuurd')    $mail_melding = '✉ Uitnodigingsmail verstuurd';
    elseif ($mail_status === 'al_verstuurd') $mail_melding = '⏱ Mail al verstuurd in afgelopen uur (geen dubbele)';
    elseif ($mail_status === 'fout')         $mail_melding = '⚠ Mail mislukt — zie mail_log';
    elseif ($mail_status === 'geen_email')   $mail_melding = '⚠ Geen e-mailadres bekend';

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
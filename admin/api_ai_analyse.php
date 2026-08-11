<?php
/**
 * AI analyse aanvragen — voegt toe aan wachtrij. Geen directe API call.
 * Cron verwerkt elke 5 minuten.
 */
ob_start();
define('P13_NO_ERROR_HANDLER', 1);

try {
    require_once __DIR__ . '/../includes/functions.php';
} catch (Throwable $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false, 'fout'=>'Laad fout: '.$e->getMessage()]); exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean(); echo json_encode(['ok'=>false,'fout'=>'POST vereist']); exit;
}

try { csrf_check(); } catch (Throwable $e) {
    ob_end_clean(); echo json_encode(['ok'=>false,'fout'=>'CSRF fout']); exit;
}

vereist_login();

$sol_id = (int)($_POST['sol_id'] ?? 0);
if (!$sol_id) { ob_end_clean(); echo json_encode(['ok'=>false,'fout'=>'Geen ID']); exit; }

if (!instelling('ai.api_key')) {
    ob_end_clean();
    echo json_encode(['ok'=>false,'fout'=>'Geen API key — stel in via Instellingen → Mail']); exit;
}

if (!function_exists('ai_queue_toevoegen')) {
    ob_end_clean();
    echo json_encode(['ok'=>false,'fout'=>'Queue functie ontbreekt']); exit;
}

try {
    $resultaat = ai_queue_toevoegen($sol_id, 'blk_analyse');
    ob_end_clean();

    $berichten = [
        'al_klaar'       => ['ok'=>true, 'status'=>'al_klaar',       'bericht'=>'Analyse is al beschikbaar.'],
        'al_in_wachtrij' => ['ok'=>true, 'status'=>'al_in_wachtrij', 'bericht'=>'Al in wachtrij — wordt binnen 5 min verwerkt.'],
        'toegevoegd'     => ['ok'=>true, 'status'=>'toegevoegd',     'bericht'=>'In wachtrij geplaatst. Wordt binnen 5 min verwerkt.'],
        'fout'           => ['ok'=>false,'status'=>'fout',           'fout'=>'Kon niet toevoegen aan wachtrij.'],
    ];
    echo json_encode($berichten[$resultaat] ?? ['ok'=>false,'fout'=>"Onbekend resultaat: $resultaat"]);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['ok'=>false,'fout'=>'Serverfout: '.$e->getMessage()]);
}

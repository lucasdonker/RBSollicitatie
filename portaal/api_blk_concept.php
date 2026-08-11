<?php
/**
 * Autosave-concept voor het BLK-formulier.
 * Slaat tekstvelden tussentijds op zodat een time-out geen invoer wist.
 * Wijzigt NIET de fase en logt geen wijzigingen — puur een concept-buffer.
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

// Sollicitant moet ingelogd zijn via portaal-sessie
if (!function_exists('portaal_ingelogd') || !portaal_ingelogd() || empty($_SESSION['portaal_sol_id'])) {
    echo json_encode(['ok' => false, 'fout' => 'Niet ingelogd']); exit;
}
$sol_id = (int)$_SESSION['portaal_sol_id'];

csrf_check();

// Alle bekende tekstvelden accepteren (geen fase-wijziging, puur concept-buffer)
$toegestaan = ['postcode', 'huisnummer', 'straat', 'woonplaats', 'adres',
               'opleiding', 'werkervaring', 'diplomas', 'motivatie_lifeguard',
               'motivatie_strand', 'beschikbaarheid_uitzonderingen', 'social_media',
               'bijzonderheden'];

try {
    $kolommen = db()->query("SHOW COLUMNS FROM blk_formulieren")->fetchAll(PDO::FETCH_COLUMN);
} catch (\Exception $e) { $kolommen = []; }
$kolommen_set = array_flip($kolommen);

$velden = [];
foreach ($toegestaan as $veld) {
    if (isset($_POST[$veld]) && isset($kolommen_set[$veld])) {
        $velden[$veld] = trim($_POST[$veld]) ?: null;
    }
}

if (!$velden) { echo json_encode(['ok' => true, 'opgeslagen' => 0]); exit; }

try {
    // Bestaat er al een (concept)rij?
    $q = db()->prepare("SELECT 1 FROM blk_formulieren WHERE sollicitatie_id=?");
    $q->execute([$sol_id]);
    if ($q->fetchColumn()) {
        $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($velden)));
        db()->prepare("UPDATE blk_formulieren SET $sets, bijgewerkt_op=NOW() WHERE sollicitatie_id=?")
           ->execute([...array_values($velden), $sol_id]);
    } else {
        $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($velden)));
        $ph   = implode(',', array_fill(0, count($velden), '?'));
        db()->prepare("INSERT INTO blk_formulieren (sollicitatie_id,$cols) VALUES(?,$ph)")
           ->execute([$sol_id, ...array_values($velden)]);
    }
    echo json_encode(['ok' => true, 'opgeslagen' => count($velden), 'tijd' => date('H:i')]);
} catch (\Exception $e) {
    error_log('blk concept autosave: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'fout' => 'Opslaan mislukt']);
}

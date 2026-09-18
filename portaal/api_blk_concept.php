<?php
/**
 * Autosave-concept voor het BLK-formulier (zelfde contract als api_besch_concept.php, zodat
 * beide formulieren de gedeelde helper assets/js/portaal_concept.js gebruiken).
 * Slaat tekstvelden tussentijds op in blk_formulieren zodat een time-out geen invoer wist.
 * Wijzigt NIET de fase en logt geen wijzigingen — puur een concept-buffer.
 *
 * POST _csrf + concept=<json {veld: waarde}>  → {ok, opgeslagen, tijd}
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/blk.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

if (!portaal_ingelogd()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'fout' => 'Niet ingelogd']); exit;
}
$sol_id = (int)$_SESSION['portaal_sol_id'];

csrf_check();

$ruw = (string)($_POST['concept'] ?? '');
if ($ruw === '' || strlen($ruw) > 30000) { echo json_encode(['ok' => false, 'fout' => 'Ongeldig concept']); exit; }
$concept = json_decode($ruw, true);
if (!is_array($concept)) { echo json_encode(['ok' => false, 'fout' => 'Ongeldig concept']); exit; }

// Alleen bekende tekstvelden accepteren (geen fase-wijziging, puur concept-buffer)
$toegestaan = ['postcode', 'huisnummer', 'straat', 'woonplaats', 'adres',
               'opleiding', 'werkervaring', 'diplomas', 'motivatie_lifeguard',
               'motivatie_strand', 'beschikbaarheid_uitzonderingen', 'social_media',
               'bijzonderheden'];

blk_kolommen_verzeker(); // adreskolommen e.d. moeten bestaan, anders valt het concept stil weg
try {
    $kolommen = db()->query("SHOW COLUMNS FROM blk_formulieren")->fetchAll(PDO::FETCH_COLUMN);
} catch (\Exception $e) { $kolommen = []; }
$kolommen_set = array_flip($kolommen);

$velden = [];
foreach ($toegestaan as $veld) {
    if (array_key_exists($veld, $concept) && isset($kolommen_set[$veld]) && is_scalar($concept[$veld])) {
        $velden[$veld] = trim((string)$concept[$veld]) ?: null;
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

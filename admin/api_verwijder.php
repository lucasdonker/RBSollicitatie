<?php
/**
 * Verwijderen via JSON. Het dossier gebruikt dit niet meer: dat gaat via het
 * tussenscherm in admin/sollicitant.php (werkt zonder JavaScript). Dit endpoint blijft
 * voor scripts en roept voor een hele sollicitatie dezelfde functie aan.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
vereis_recht('sollicitanten', 'verwijderen');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'fout' => 'Methode niet toegestaan']); exit;
}

csrf_check();

$actie  = $_POST['actie'] ?? '';
$sol_id = (int)($_POST['sol_id'] ?? 0);

if (!$sol_id) {
    echo json_encode(['ok' => false, 'fout' => 'Geen ID opgegeven']); exit;
}

try {
    switch ($actie) {

        case 'verwijder_blk':
            if (function_exists('foto_van_sollicitatie_verwijderen')) foto_van_sollicitatie_verwijderen($sol_id);
            db()->prepare("DELETE FROM blk_formulieren WHERE sollicitatie_id = ?")->execute([$sol_id]);
            echo json_encode(['ok' => true, 'msg' => 'BLK formulier verwijderd.']);
            break;

        case 'verwijder_sollicitatie':   // alleen deze sollicitatie; de persoon blijft
        case 'verwijder_persoon':        // AVG-verwijderverzoek: alles van deze persoon
            /* Dezelfde functie als het tussenscherm in het dossier (sollicitant.php), zodat
               er één lijst is van wat er verdwijnt (includes/avg_wissen.php).
               Loondossier: loonheffing + kopie ID vallen onder de wettelijke bewaarplicht.
               Die gaan alleen mee met ook_loondocs=1 (het dienstverband is niet
               doorgegaan) — en alleen voor wie loondossiers mag verwijderen. */
            $ook_loon = !empty($_POST['ook_loondocs']) && mag('documenten', 'verwijderen');
            $r = bh_dossier_verwijderen($sol_id, $actie === 'verwijder_persoon' ? 'persoon' : 'sollicitatie', $ook_loon);
            if (!$r['ok']) {
                echo json_encode(['ok' => false, 'fout' => $r['fout'], 'loondossier' => $r['loondossier']]);
                break;
            }
            echo json_encode(['ok' => true, 'msg' => $r['tekst'], 'verwijderd' => true]);
            break;

        case 'verwijder_beschikbaarheid':
            db()->prepare("DELETE FROM beschikbaarheid WHERE sollicitatie_id = ?")->execute([$sol_id]);
            echo json_encode(['ok' => true, 'msg' => 'Beschikbaarheid verwijderd.']);
            break;

        case 'verwijder_beoordeling':
            vereis_recht('sollicitanten', 'verwijderen');
            db()->prepare("DELETE FROM beoordelingen WHERE sollicitatie_id = ?")->execute([$sol_id]);
            echo json_encode(['ok' => true, 'msg' => 'Beoordeling verwijderd.']);
            break;

        default:
            echo json_encode(['ok' => false, 'fout' => 'Onbekende actie']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'fout' => $e->getMessage()]);
}
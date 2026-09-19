<?php
require_once __DIR__ . '/../includes/functions.php';
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
            db()->prepare("DELETE FROM blk_formulieren WHERE sollicitatie_id = ?")->execute([$sol_id]);
            echo json_encode(['ok' => true, 'msg' => 'BLK formulier verwijderd.']);
            break;

        case 'verwijder_sollicitatie':
            // Verwijder de sollicitatie (cascade verwijdert BLK, beoordeling, beschikbaarheid)
            // De persoon zelf blijft bewaard (kan opnieuw solliciteren)
            $naam_stmt = db()->prepare("
                SELECT CONCAT(p.voornaam, ' ', p.achternaam) naam
                FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id
                WHERE s.id = ?
            ");
            $naam_stmt->execute([$sol_id]);
            $naam = $naam_stmt->fetchColumn() ?: 'Onbekend';

            // Loondossier: loonheffing + kopie ID vallen onder de wettelijke bewaarplicht (5 jaar). Die gaan alleen
            // mee als het beheer expliciet aangeeft dat het dienstverband niet is doorgegaan (ook_loondocs=1).
            // Bankpas, pasfoto en overige documenten worden altijd vernietigd; taken gaan weg.
            if (function_exists('documenten_loondossier')) {
                $ld = documenten_loondossier($sol_id);
                $ook_loon = !empty($_POST['ook_loondocs']);
                if ($ld['verwerkt'] && !$ook_loon) {
                    echo json_encode(['ok' => false, 'fout' => "$naam heeft een verwerkt loondossier (wettelijke bewaarplicht). Verwijderen kan alleen met de bevestiging dat het dienstverband niet is doorgegaan.", 'loondossier' => true]);
                    break;
                }
                documenten_bij_dossier_verwijderen($sol_id, $ook_loon || !$ld['verwerkt']);
            }
            try { db()->prepare("DELETE FROM sollicitant_taken WHERE sollicitatie_id = ?")->execute([$sol_id]); } catch (\Exception $e) {}

            db()->prepare("DELETE FROM sollicitaties WHERE id = ?")->execute([$sol_id]);
            if (function_exists('audit_log')) audit_log('sollicitatie_verwijderd', 'sollicitatie', $sol_id, ['naam' => $naam, 'ook_loondocs' => !empty($_POST['ook_loondocs'])]);
            echo json_encode(['ok' => true, 'msg' => "$naam verwijderd uit dit seizoen.", 'verwijderd' => true]);
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
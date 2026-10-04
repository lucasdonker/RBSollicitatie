<?php
require_once __DIR__ . '/../includes/functions.php';
 if (function_exists('extra_tabellen_auto_setup')) extra_tabellen_auto_setup();

$act = $_POST['actie'] ?? $_GET['actie'] ?? 'lijst';
// Lezen is lezen: wie het dossier mag zien, ziet ook de notities (de penningmeester
// kreeg anders "Notities konden niet geladen worden"). Plaatsen, bewerken en
// verwijderen vragen bewerkrecht.
vereis_recht('sollicitanten', $act === 'lijst' ? 'lezen' : 'bewerken');
header('Content-Type: application/json');

$db  = db();
$bhd = huidige_beheerder();

try {
    if ($act === 'lijst') {
        $sol_id = (int)($_GET['sol_id'] ?? 0);
        if (!$sol_id) throw new Exception('sol_id vereist');
        $q = $db->prepare("
            SELECT n.id, n.tekst, n.aangemaakt_op, n.gewijzigd_op, n.beheerder_id, b.naam AS beheerder_naam
            FROM sollicitant_notities n
            LEFT JOIN beheerders b ON b.id = n.beheerder_id
            WHERE n.sollicitatie_id = ?
            ORDER BY n.aangemaakt_op DESC
        ");
        $q->execute([$sol_id]);
        echo json_encode(['ok' => true, 'notities' => $q->fetchAll(), 'huidige_id' => $bhd['id']]);
        exit;
    }

    csrf_check();

    if ($act === 'toevoegen') {
        $sol_id = (int)($_POST['sol_id'] ?? 0);
        $tekst  = trim($_POST['tekst'] ?? '');
        if (!$sol_id || $tekst === '') throw new Exception('sol_id en tekst vereist');
        if (mb_strlen($tekst) > 5000) throw new Exception('Maximum 5000 tekens');
        $db->prepare("INSERT INTO sollicitant_notities (sollicitatie_id, beheerder_id, tekst) VALUES (?,?,?)")
           ->execute([$sol_id, $bhd['id'], $tekst]);
        echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId()]);
        exit;
    }

    if ($act === 'bewerken') {
        $id    = (int)($_POST['id'] ?? 0);
        $tekst = trim($_POST['tekst'] ?? '');
        if (!$id || $tekst === '') throw new Exception('id en tekst vereist');

        // Alleen aanmaker of admin mag bewerken
        $eig = $db->prepare("SELECT beheerder_id FROM sollicitant_notities WHERE id=?");
        $eig->execute([$id]);
        $r = $eig->fetch();
        if (!$r) throw new Exception('Notitie niet gevonden');
        if ((int)$r['beheerder_id'] !== (int)$bhd['id'] && ($bhd['rol'] ?? '') !== 'admin') {
            throw new Exception('Geen rechten om deze notitie te bewerken');
        }

        $db->prepare("UPDATE sollicitant_notities SET tekst=? WHERE id=?")
           ->execute([$tekst, $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($act === 'verwijderen') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) throw new Exception('id vereist');
        $eig = $db->prepare("SELECT beheerder_id FROM sollicitant_notities WHERE id=?");
        $eig->execute([$id]);
        $r = $eig->fetch();
        if (!$r) throw new Exception('Notitie niet gevonden');
        if ((int)$r['beheerder_id'] !== (int)$bhd['id'] && ($bhd['rol'] ?? '') !== 'admin') {
            throw new Exception('Geen rechten om deze notitie te verwijderen');
        }
        $db->prepare("DELETE FROM sollicitant_notities WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    throw new Exception('Onbekende actie');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fout' => $e->getMessage()]);
}

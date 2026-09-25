<?php
/**
 * Feedback van lifeguards vastleggen of wissen (admin/_lg_feedback.php). Gewone formulieren,
 * werkt zonder JavaScript: na opslaan terug naar het dossier. Logica in includes/lg_feedback.php.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
vereis_recht('sollicitanten', 'bewerken');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$ik    = huidige_beheerder();
$actie = (string)($_POST['actie'] ?? '');
$sol   = (int)($_POST['sol_id'] ?? 0);

if ($actie === 'lg_feedback') {
    $sz = null;
    try {
        $q = db()->prepare("SELECT seizoen_id FROM sollicitaties WHERE id=?");
        $q->execute([$sol]);
        $sz = $q->fetchColumn();
    } catch (\Exception $e) {}
    $ok = $sz !== false && $sz !== null && lg_feedback_toevoegen($sol, (int)$sz, (string)($_POST['van'] ?? ''), (string)($_POST['moment'] ?? ''),
                                              (string)($_POST['soort'] ?? ''), (string)($_POST['tekst'] ?? ''), (int)$ik['id'], (string)$ik['naam']);
    flash($ok ? 'succes' : 'fout', $ok ? 'Feedback vastgelegd.' : 'Niet opgeslagen: vul in van wie het komt en wat er gezegd is.');
} elseif ($actie === 'lg_feedback_wis') {
    $gewist = lg_feedback_wissen((int)($_POST['id'] ?? 0), (int)$ik['id'], ($ik['rol'] ?? '') === 'admin');
    flash($gewist ? 'succes' : 'fout', $gewist ? 'Feedback verwijderd.' : 'Niet verwijderd: alleen wie het vastlegde (of een admin) kan dat.');
    if ($gewist) $sol = $gewist;
} else {
    http_response_code(400); exit('Onbekende actie.');
}
redirect(APP_URL . '/admin/sollicitant.php?id=' . $sol . '#lg-feedback');

<?php
/**
 * Feedback van lifeguards vastleggen of wissen (admin/_lg_feedback.php). Gewone formulieren,
 * werkt zonder JavaScript: na opslaan terug naar de pagina waar je was (SD-nabespreking of
 * TW-gesprek). Recht: bewerken op de module van het moment (lg_feedback_module()).
 * Logica in includes/lg_feedback.php.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
vereist_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$ik    = huidige_beheerder();
$actie = (string)($_POST['actie'] ?? '');
// Alleen terug naar een pagina binnen het beheer (geen open redirect).
$terug = (string)($_POST['terug'] ?? '');
if (!str_starts_with($terug, rtrim(APP_URL, '/') . '/admin/')) $terug = APP_URL . '/admin/';

if ($actie === 'lg_feedback') {
    $sol    = (int)($_POST['sol_id'] ?? 0);
    $moment = (string)($_POST['moment'] ?? '');
    vereis_recht(lg_feedback_module($moment), 'bewerken');
    $sz = false;
    try {
        $q = db()->prepare("SELECT seizoen_id FROM sollicitaties WHERE id=?");
        $q->execute([$sol]);
        $sz = $q->fetchColumn();
    } catch (\Exception $e) {}
    $ok = $sz !== false && lg_feedback_toevoegen($sol, (int)$sz, (string)($_POST['van'] ?? ''), $moment,
                                                 (string)($_POST['soort'] ?? ''), (string)($_POST['tekst'] ?? ''), (int)$ik['id'], (string)$ik['naam']);
    flash($ok ? 'succes' : 'fout', $ok ? 'Feedback vastgelegd.' : 'Niet opgeslagen: vul in van wie het komt en wat er gezegd is.');
    redirect($terug . '#lg-feedback-' . $sol);
}
if ($actie === 'lg_feedback_wis') {
    $rij = lg_feedback_rij((int)($_POST['id'] ?? 0));
    if (!$rij) { flash('fout', 'Die feedback bestaat niet (meer).'); redirect($terug); }
    vereis_recht(lg_feedback_module((string)$rij['moment']), 'bewerken');
    $gewist = lg_feedback_wissen((int)$rij['id'], (int)$ik['id'], ($ik['rol'] ?? '') === 'admin');
    flash($gewist ? 'succes' : 'fout', $gewist ? 'Feedback verwijderd.' : 'Niet verwijderd: alleen wie het vastlegde (of een admin) kan dat.');
    redirect($terug . '#lg-feedback-' . (int)$rij['sollicitatie_id']);
}
http_response_code(400); exit('Onbekende actie.');

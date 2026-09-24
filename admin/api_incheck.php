<?php
/**
 * Inchecken vanuit het beheer, voor de selectiedag én het trainingsweekend (admin/_incheck_lijst.php).
 *
 * POST ev=selectiedag|tw, actie=zet, sol_id, stand=aanwezig|ongedaan|niet
 *      → lezen op de module (begeleiders draaien mee)
 * POST ev, actie=afsluiten → bewerken (bestuur); geen JSON maar een redirect terug
 *
 * Alles loopt via includes/evenement.php, dezelfde laag als de tablet.
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$ev  = (string)($_POST['ev'] ?? '');
$def = evenement_def($ev);
$actie = (string)($_POST['actie'] ?? '');
$sz  = actief_seizoen();
$sid = $sz ? (int)$sz['id'] : 0;

if ($actie === 'afsluiten') {
    csrf_check();
    if (!$def) redirect(APP_URL . '/admin/');
    vereis_recht($def['module'], 'bewerken');
    $n = $sid ? evenement_afsluiten($ev, $sid, (int)($_SESSION['bhd_id'] ?? 0)) : 0;
    flash('succes', 'Inchecken afgesloten: ' . $n . ' ' . ($n === 1 ? 'persoon staat' : 'personen staan') . ' op niet verschenen. Laatkomers kun je nog inchecken.');
    $terug = (string)($_SERVER['HTTP_REFERER'] ?? '');
    redirect(str_starts_with($terug, rtrim(APP_URL, '/') . '/admin/') ? $terug : APP_URL . '/admin/');
}

header('Content-Type: application/json; charset=utf-8');
csrf_check();
if (!$def) { echo json_encode(['ok' => false, 'fout' => 'onbekend']); exit; }
vereis_recht($def['module'], 'lezen');

$sol   = (int)($_POST['sol_id'] ?? 0);
$stand = (string)($_POST['stand'] ?? '');
if ($actie !== 'zet' || !in_array($stand, ['aanwezig', 'ongedaan', 'niet'], true) || !evenement_is_kandidaat($ev, $sid, $sol)) {
    echo json_encode(['ok' => false, 'fout' => 'geen_kandidaat']);
    exit;
}
if ($stand === 'niet') {
    evenement_inchecken($ev, $sid, $sol, false, 'beheer');
    evenement_afmelden($ev, $sid, $sol, 'Niet gekomen');
    echo json_encode(['ok' => true, 'stand' => 'niet']);
} else {
    $r = evenement_inchecken($ev, $sid, $sol, $stand === 'aanwezig', 'beheer');
    echo json_encode(['ok' => true, 'stand' => $stand === 'aanwezig' ? 'aanwezig' : 'verwacht', 'tijd' => date('H:i')] + $r);
}

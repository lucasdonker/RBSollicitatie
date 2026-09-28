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

// De tablet instellen (admin/_tablet_code.php): bestuurswerk, terug naar het scherm.
if ($actie === 'code_genereren' || $actie === 'pin_zetten' || $actie === 'pin_wissen') {
    csrf_check();
    if (!$def) redirect(APP_URL . '/admin/');
    vereis_recht($def['module'], 'bewerken');
    if ($actie === 'code_genereren') {
        // Leesbare code: geen tekens die je kunt verwarren (0/O, 1/I/L).
        $alfabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alfabet[random_int(0, strlen($alfabet) - 1)];
        instelling_opslaan($def['code'], $code);
        flash('succes', 'Nieuwe toegangscode aangemaakt. Oude tablet-links werken niet meer.');
    } elseif ($actie === 'pin_wissen') {
        // Wissen is een eigen, bevestigde knop: eerder wiste een leeg of te kort veld de pincode stil.
        instelling_opslaan($def['pin'], '');
        flash('succes', 'Pincode gewist: afsluiten kan nu alleen in het beheer.');
    } else {
        $pin = preg_replace('/\D/', '', (string)($_POST['pin'] ?? ''));
        if (strlen($pin) < 4) {
            flash('fout', 'De pincode is niet gewijzigd: gebruik minstens 4 cijfers.');
        } else {
            instelling_opslaan($def['pin'], password_hash($pin, PASSWORD_DEFAULT));
            flash('succes', 'Pincode voor afsluiten op de tablet opgeslagen.');
        }
    }
    if (function_exists('audit_log')) audit_log('tablet_' . $actie, 'seizoen', $sid, ['evenement' => $ev]);
    $terug = (string)($_SERVER['HTTP_REFERER'] ?? '');
    redirect(str_starts_with($terug, rtrim(APP_URL, '/') . '/admin/') ? $terug : APP_URL . '/admin/');
}

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

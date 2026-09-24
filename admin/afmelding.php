<?php
/**
 * Besluit over een "kan niet komen" (admin/_afmeldingen.php): afmelden of uitzondering.
 * Alleen POST, met bewerkrecht op de module van het evenement. Terug naar waar je vandaan kwam
 * (alleen binnen het beheer).
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(APP_URL . '/admin/');
csrf_check();

$ev    = (string)($_POST['ev'] ?? '');
$sol   = (int)($_POST['sol_id'] ?? 0);
$keuze = (string)($_POST['keuze'] ?? '');
$def   = evenement_def($ev);
if (!$def) redirect(APP_URL . '/admin/');
vereis_recht($def['module'], 'bewerken');

$sz = actief_seizoen();
$ok = $sz && evenement_afmelding_besluiten($ev, (int)$sz['id'], $sol, $keuze);
flash($ok ? 'succes' : 'fout', $ok
    ? ($keuze === 'afmelden' ? 'Afgemeld (fase Afgehaakt).' : 'Uitzondering vastgelegd: blijft in het proces.')
    : 'Dat lukte niet. Staat deze persoon nog uitgenodigd?');

$terug = (string)($_SERVER['HTTP_REFERER'] ?? '');
redirect(str_starts_with($terug, rtrim(APP_URL, '/') . '/admin/') ? $terug : APP_URL . '/admin/');

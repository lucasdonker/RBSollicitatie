<?php
/**
 * Besluit over een verzoek om in te trekken (admin/_intrekverzoeken.php): doorvoeren of niet.
 * Alleen POST, met sollicitanten › bewerken. Terug naar waar je vandaan kwam (alleen binnen het beheer).
 */
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('sollicitanten', 'bewerken');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(APP_URL . '/admin/');
csrf_check();

$keuze = (string)($_POST['keuze'] ?? '');
$ok = intrek_besluiten((int)($_POST['id'] ?? 0), $keuze, (string)($_POST['notitie'] ?? ''), (string)($_SESSION['bhd_naam'] ?? 'onbekend'));
flash($ok ? 'succes' : 'fout', $ok
    ? ($keuze === 'doorvoeren' ? 'Ingetrokken (fase Afgehaakt).' : 'Niet doorgevoerd: de sollicitatie loopt door.')
    : 'Dat lukte niet. Is het verzoek al afgehandeld?');

$terug = (string)($_SERVER['HTTP_REFERER'] ?? '');
redirect(str_starts_with($terug, rtrim(APP_URL, '/') . '/admin/') ? $terug : APP_URL . '/admin/');

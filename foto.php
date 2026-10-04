<?php
/**
 * GET /foto.php?f=<naam> — de foto van een sollicitant, alleen voor wie hem mag zien.
 *
 * Zie includes/foto.php. Geen toegang en geen bestand geven allebei 404: of een foto
 * bestaat, is voor een buitenstaander ook al een gegeven.
 */
require_once __DIR__ . '/includes/functions.php';

sessie_start();
// Alleen lezen uit de sessie. Meteen vrijgeven, anders wachten twintig avatars in een
// lijst op elkaar.
session_write_close();

$naam = foto_naam('foto:' . (string)($_GET['f'] ?? ''));
$pad  = ($naam !== null && foto_mag_zien($naam)) ? foto_pad($naam) : null;
if ($pad === null) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

$typen = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . ($typen[pathinfo($naam, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($pad));
header('Content-Disposition: inline');
// De naam is willekeurig en verandert bij een nieuwe foto; de browser mag hem dus bewaren,
// maar alleen voor zichzelf (private), nooit in een gedeelde cache.
header('Cache-Control: private, max-age=604800, immutable');
readfile($pad);

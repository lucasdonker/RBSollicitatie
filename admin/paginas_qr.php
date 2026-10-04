<?php
/**
 * Beheer — QR-code van een wervingspagina downloaden (SVG voor drukwerk, of PNG).
 *
 * GET ?id=<pagina>&campagne=<code>&formaat=svg|png
 * Alleen voor een bestaande pagina, en de code bevat altijd APP_URL/p/<slug> — nooit een adres
 * uit het verzoek. De campagne wordt geschoond zoals op de pagina zelf (pag_campagne_schoon).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paginas/qr.php';
vereis_recht('paginas', 'lezen');

$pagina = pag_via_id((int)($_GET['id'] ?? 0));
if (!$pagina) { http_response_code(404); exit('Die pagina bestaat niet (meer).'); }

$campagne = pag_campagne_schoon((string)($_GET['campagne'] ?? ''));
$formaat  = ($_GET['formaat'] ?? 'svg') === 'png' ? 'png' : 'svg';
$url      = pag_deel_url($pagina, $campagne);
$naam     = 'qr-' . $pagina['slug'] . ($campagne !== '' ? '-' . $campagne : '') . '.' . $formaat;

$inhoud = $formaat === 'png' ? pag_qr_png($url) : pag_qr_svg($url, 'QR-code naar ' . $url);
if ($inhoud === null || $inhoud === '') {
    http_response_code(500);
    exit($formaat === 'png' ? 'PNG maken lukt niet op deze server (GD ontbreekt). Download de SVG.' : 'Het adres is te lang voor een QR-code.');
}

header('Content-Type: ' . ($formaat === 'png' ? 'image/png' : 'image/svg+xml; charset=utf-8'));
header('Content-Disposition: attachment; filename="' . $naam . '"');   // $naam: alleen a-z0-9- en een punt
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . strlen($inhoud));
echo $inhoud;

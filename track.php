<?php
/**
 * POST /track.php — anonieme gebeurtenissen van het aanmeldformulier.
 *
 * Het formulier meldt hiermee dat iemand begonnen is, welke stap hij bereikte en of hij
 * halverwege wegging. Dat kan alleen vanuit de browser: de server ziet een bezoeker pas
 * weer bij het verzenden, en juist het stuk daartussen wil je kunnen zien.
 *
 * Bewust klein gehouden:
 *  - geen sessie, geen cookie, geen invoer van de bezoeker — alleen het soort gebeurtenis,
 *    de bron en een stapnummer
 *  - geen API-sleutel: dit is publiek en anoniem. De sleutel zou op een publieke pagina
 *    tóch meegestuurd moeten worden en daarmee geen sleutel meer zijn
 *  - alleen de drie gebeurtenissen die echt uit een browser kunnen komen. "Aanmelding
 *    verstuurd" en "geweigerd" schrijft de server zelf, anders is de conversie te vervalsen
 *    door dit adres een paar keer aan te roepen
 *  - antwoord is 204 zonder inhoud, en gaat de deur uit vóórdat we naar de database
 *    schrijven; de bezoeker wacht nergens op
 *
 * De landingspagina's staan dit adres expliciet toe in hun Content-Security-Policy
 * (connect-src, zie includes/paginas/render.php). Dat is bewust één pad en niet 'self':
 * script uit een geüpload ontwerp mag hierheen, maar nergens anders.
 */
require_once __DIR__ . '/includes/functions.php';

// ── Antwoorden en pas daarna werken ──────────────────────────────────────
function track_klaar(int $status = 204): void {
    http_response_code($status);
    header('Content-Length: 0');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    // CORS: de WordPress-plugin op paal13.com stuurt dit vanaf een ander adres.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, site_toegestane_origins(), true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');

    antwoord_afsluiten();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { track_klaar(204); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST')     { track_klaar(405); exit; }

// Wat er binnenkomt, vóór het antwoord uitgaat (daarna is $_POST nog steeds beschikbaar,
// maar we lezen het hier zodat de rest van dit bestand niets meer hoeft te wachten).
$type   = substr(trim((string)($_POST['t'] ?? '')), 0, 30);
$bron   = substr(trim((string)($_POST['b'] ?? '')), 0, 80);
$detail = substr(trim((string)($_POST['d'] ?? '')), 0, 40);

track_klaar(204);

// ── Vanaf hier praat niemand meer met de browser ─────────────────────────

/** Alleen deze drie kunnen uit een browser komen. De rest schrijft de server zelf. */
$toegestaan = ['formulier_start', 'formulier_stap', 'formulier_verlaten'];
if (!in_array($type, $toegestaan, true)) exit;

// Het stapnummer is het enige detail dat we accepteren — nooit vrije tekst.
if ($detail !== '' && !ctype_digit($detail)) $detail = '';
if ($detail !== '' && ((int)$detail < 1 || (int)$detail > 20)) $detail = '';

// Ruim genoeg voor iemand die het formulier een paar keer doorloopt, krap genoeg om de
// statistiek niet te laten volschrijven vanaf één adres.
if (function_exists('rate_limit') && !rate_limit('track', rate_identiteit(), 120, 3600)) exit;

if (!function_exists('pag_track')) exit;

$pagina = ($bron !== '' && function_exists('pag_via_bron')) ? pag_via_bron($bron) : null;
pag_track($type, isset($pagina['id']) ? (int)$pagina['id'] : null, $bron !== '' ? $bron : null, $detail !== '' ? $detail : null);

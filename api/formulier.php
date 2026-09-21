<?php
/**
 * GET /api/formulier.php — de HTML van het aanmeldformulier, voor de WordPress-plugin.
 *
 * Waarom dit bestaat: anders zou de plugin een eigen kopie van het formulier moeten
 * meedragen. Twee kopieën lopen uit elkaar — een veld dat hier bijkomt, staat dan niet op
 * paal13.com, en een tekst die hier verandert blijft daar het oude zeggen. Nu is er één
 * formulier; de plugin haalt het op en bewaart het een paar minuten.
 *
 * Wat er bewust NIET in zit:
 *  - het anti-bot-token van de landingspagina's: dat verloopt, en deze HTML wordt gecachet.
 *    De plugin zet er zijn eigen WordPress-nonce en tijdstempel in
 *  - een formulier-action: het formulier post naar de pagina waar het staat, dus naar
 *    WordPress zelf. Zou dit adres een meegegeven URL in de HTML zetten, dan had je een
 *    open doorstuurgat
 *
 * Vereist de API-sleutel in de header X-Paal13-Key.
 */
require_once __DIR__ . '/_basis.php';
api_preflight();
api_vereis_sleutel();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    api_json(['ok' => false, 'fout' => 'Gebruik GET.'], 405);
}

// De plugin cachet dit; een limiet vangt alleen een plugin die op hol slaat.
if (function_exists('rate_limit') && !rate_limit('api_formulier', rate_identiteit(), 120, 3600)) {
    api_json(['ok' => false, 'fout' => 'Te veel verzoeken. Probeer het later opnieuw.'], 429);
}

if (!function_exists('aanmeld_formulier_html')) {
    api_json(['ok' => false, 'fout' => 'Het formulier is op dit moment niet beschikbaar.'], 503);
}

// Alleen deze drie zijn instelbaar vanaf de website, en alle drie gaan door h() heen
// in de opbouw van de HTML.
$bron  = substr(preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_GET['bron'] ?? ''))), 0, 80);
$titel = substr(trim((string)($_GET['titel'] ?? '')), 0, 120);
$knop  = substr(trim((string)($_GET['knop'] ?? '')), 0, 40);

$seizoen = actief_seizoen();
$open    = $seizoen && ($seizoen['status'] ?? '') === 'open';

$opties = ['prefix' => 'wp', 'bron_code' => $bron, 'markeer' => true];
if ($titel !== '') $opties['titel'] = $titel;
if ($knop  !== '') $opties['knop']  = $knop;

api_json([
    'ok'             => true,
    'aanmelden_open' => $open,
    'html'           => aanmeld_formulier_html($opties),
    'succes_html'    => aanmeld_succes_html(),
    'css'            => APP_URL . '/assets/css/aanmeldformulier.css',
    'js'             => APP_URL . '/assets/js/aanmeldformulier.js',
    'versie'         => defined('APP_VERSIE') ? APP_VERSIE : '',
    'opgehaald_op'   => date('c'),
]);

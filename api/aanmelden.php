<?php
/**
 * POST /api/aanmelden.php
 *
 * Ontvangt een aanmelding van de WordPress-plugin en verwerkt die via de gedeelde
 * bron-van-waarheid (sollicitatie_aanmaken). Het platform blijft dus de baas: alle
 * validatie, opslag en mails gebeuren hier.
 *
 * Vereist de API-sleutel. Body: JSON of form met de aanmeldvelden.
 */
require_once __DIR__ . '/_basis.php';
require_once __DIR__ . '/../includes/aanmelden_kern.php';
api_preflight();
api_vereis_sleutel();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json(['ok' => false, 'fout' => 'Gebruik POST om aan te melden.'], 405);
}

$body = api_body();

// Eenvoudige snelheidsbegrenzing per IP (tegen misbruik).
if (!rate_limit('api_aanmelden', rate_identiteit(), 10, 3600)) {
    api_json(['ok' => false, 'fout' => 'Te veel aanmeldingen in korte tijd. Probeer het later opnieuw.'], 429);
}

$resultaat = sollicitatie_aanmaken($body, [
    'ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
    'via' => 'wordpress_plugin',
]);

if (!$resultaat['ok']) {
    // Nette foutcode zodat de plugin gericht kan reageren
    $status = ($resultaat['code'] ?? '') === 'al_aangemeld' ? 409
            : (($resultaat['code'] ?? '') === 'geen_open_seizoen' ? 403 : 422);
    api_json([
        'ok'   => false,
        'fout' => $resultaat['fout'],
        'code' => $resultaat['code'] ?? 'onbekend',
    ], $status);
}

// Succes — geef het portaal-token terug zodat de plugin desgewenst kan doorverwijzen.
api_json([
    'ok'              => true,
    'sollicitatie_id' => $resultaat['sollicitatie_id'],
    'portaal_token'   => $resultaat['portaal_token'],
    'portaal_url'     => $resultaat['portaal_url'],
    'terugkerend'     => $resultaat['terugkerend'],
    'bericht'         => 'Aanmelding ontvangen.',
]);

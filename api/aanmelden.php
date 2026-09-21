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

/**
 * Van wie is deze aanmelding? Niet van de webserver die hem doorgeeft.
 *
 * Alles wat via de plugin binnenkomt, komt van hetzelfde adres: de webserver van
 * paal13.com. Zonder deze regel zou de snelheidsbegrenzing hieronder na tien aanmeldingen
 * per uur iederéén tegenhouden, en zou bij de privacytoestemming het adres van de
 * webserver worden vastgelegd in plaats van dat van de sollicitant — precies het gegeven
 * dat dat bewijs zijn waarde geeft.
 *
 * De plugin stuurt het echte adres mee in X-Paal13-Bezoeker. Dat mogen we vertrouwen: dit
 * verzoek is hierboven al met de geheime sleutel bewezen. Zonder geldige sleutel komt er
 * niets voorbij api_vereis_sleutel(), dus niemand anders kan hier een adres verzinnen.
 */
$bezoeker = trim((string)($_SERVER['HTTP_X_PAAL13_BEZOEKER'] ?? ''));
if ($bezoeker === '' || !filter_var($bezoeker, FILTER_VALIDATE_IP)) {
    $bezoeker = $_SERVER['REMOTE_ADDR'] ?? '';
}

// Snelheidsbegrenzing per bezoeker (tegen misbruik).
if (!rate_limit('api_aanmelden', $bezoeker, 10, 3600)) {
    api_json(['ok' => false, 'fout' => 'Te veel aanmeldingen in korte tijd. Probeer het later opnieuw.'], 429);
}

$resultaat = sollicitatie_aanmaken($body, [
    'ip'  => $bezoeker,
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

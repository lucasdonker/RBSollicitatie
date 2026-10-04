<?php
/**
 * Afmelden voor wervingsberichten: /afmelden.php?t=<token>
 *
 *   GET  ?t=<token>      toont de stand en één knop. Alleen bekijken verandert niets:
 *                        mailscanners (bijv. Outlook Safe Links) openen links vooraf.
 *   POST actie + t       afmelden of weer aanmelden, daarna terug naar de GET (303).
 *
 * Geen sessie en geen cookie: het token uit de mail is het bewijs, net als bij seintje.php.
 */
require_once __DIR__ . '/includes/functions.php';
security_headers();
header('Cache-Control: no-store');

$token = (string)($_POST['t'] ?? $_GET['t'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $actie = (string)($_POST['actie'] ?? '');
    if ($actie === 'afmelden')     optout_afmelden($token);
    if ($actie === 'heraanmelden') optout_heraanmelden($token);
    header('Location: ' . APP_URL . '/afmelden.php?' . http_build_query(['t' => $token, 'klaar' => $actie]), true, 303);
    exit;
}

$p     = optout_persoon($token);
$klaar = (string)($_GET['klaar'] ?? '');
$contact = '<a href="mailto:' . h(portaal_contact_email()) . '">' . h(portaal_contact_email()) . '</a>';

$kaart = static fn(string $titel, string $tekst, string $klasse = '', ?array $knop = null, string $klein = ''): string =>
    aanmeld_kaart_html($titel, $tekst, ['klasse' => $klasse, 'knop' => $knop, 'klein' => $klein,
                                        'post_naar' => APP_URL . '/afmelden.php', 'token' => $token]);

$blijft = 'Berichten over een lopende sollicitatie, zoals een uitnodiging, krijg je wel gewoon.';

if (!$p) {
    http_response_code(404);
    $inhoud = $kaart('Deze link werkt niet',
        'We herkennen deze afmeldlink niet. Wil je geen wervingsberichten meer? Mail ons op ' . $contact . '.', 'af-gesloten');
} elseif ($p['afgemeld']) {
    $inhoud = $kaart($klaar === 'afmelden' ? 'Je bent afgemeld' : 'Je bent al afgemeld',
        'Je krijgt geen wervingsberichten meer van Reddingsbrigade Paal 13. ' . $blijft,
        'af-succes', ['actie' => 'heraanmelden', 'tekst' => 'Toch weer berichten ontvangen', 'klasse' => 'af-terug']);
} else {
    $inhoud = $klaar === 'heraanmelden'
        ? $kaart('Je krijgt weer berichten', 'Je ontvangt weer wervingsberichten van Reddingsbrigade Paal 13.', 'af-succes',
                 ['actie' => 'afmelden', 'tekst' => 'Toch afmelden', 'klasse' => 'af-terug'])
        : $kaart('Afmelden voor wervingsberichten',
                 'Hoi ' . h($p['voornaam']) . ', wil je geen berichten meer over werving en nieuwe seizoenen van Reddingsbrigade Paal 13? ' . $blijft,
                 '', ['actie' => 'afmelden', 'tekst' => 'Ja, meld me af'],
                 'Heb je per ongeluk op de link geklikt? Sluit deze pagina dan gewoon; er verandert niets.');
}

echo aanmeld_pagina_html($inhoud, [
    'titel' => 'Afmelden — Reddingsbrigade Paal 13',
    'h1'    => 'Afmelden voor wervingsberichten',
]);

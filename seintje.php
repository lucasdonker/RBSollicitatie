<?php
/**
 * Seintje buiten het seizoen: https://sollicitatie.paal13.com/seintje.php
 *
 * Drie dingen op één adres. Wat er gebeurt beslist seintje_werving_scherm() in
 * includes/seintje_werving.php; hier staat alleen hoe elke stand eruitziet.
 *   POST p13_seintje=1   een aanvraag vanaf een wervingspagina of paal13.com. Die hebben geen
 *                        sessie, dus het anti-bot-token in plaats van csrf. (Op /aanmelden.php
 *                        zelf post het formulier naar die pagina, mét csrf.)
 *   GET  ?t=<token>      de link uit de bevestigingsmail of de afmeldlink: toont een knop.
 *                        Alleen bekijken verandert niets; mailscanners openen links vooraf.
 *   POST actie + t       bevestigen of intrekken.
 *
 * Geen sessie en geen cookie. Pagina's met een token worden niet gecachet.
 */
require_once __DIR__ . '/includes/functions.php';
security_headers();
header('Cache-Control: no-store');

$seizoen = actief_seizoen();
$open    = $seizoen && ($seizoen['status'] ?? '') === 'open';
$s = seintje_werving_scherm($_SERVER['REQUEST_METHOD'] ?? 'GET', $_GET, $_POST, $open);

if ($s['redirect'] !== null) {
    header('Location: ' . APP_URL . '/seintje.php?' . http_build_query($s['redirect']), true, 303);
    exit;
}
http_response_code($s['http']);

$rij   = $s['rij'];
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$aanmeld_url = APP_URL . '/aanmelden.php';
$opnieuw = '<a href="' . h($aanmeld_url) . '">Vraag het opnieuw aan</a>';

/** Eén meldingskaart, optioneel met een knop die post (bevestigen of intrekken). */
$kaart = static fn(string $titel, string $tekst, string $klasse = '', ?array $knop = null, string $klein = ''): string =>
    aanmeld_kaart_html($titel, $tekst, ['klasse' => $klasse, 'knop' => $knop, 'klein' => $klein,
                                        'post_naar' => APP_URL . '/seintje.php', 'token' => $token]);

switch ($s['stand']) {
    case 'te_vaak':
        $inhoud = $kaart('Even geduld', 'Er zijn in korte tijd te veel verzoeken vanaf dit adres gedaan. Probeer het over een uur opnieuw.', 'af-gesloten');
        break;
    case 'ingetrokken':
        $inhoud = $kaart('Geen seintje', 'We hebben je voornaam en e-mailadres verwijderd. Je krijgt van ons geen bericht.');
        break;
    case 'aangevraagd':
        $inhoud = aanmeld_seintje_succes_html(['extra_class' => 'af-zelfstandig']);
        break;
    case 'onbekend':
        $inhoud = $kaart('Deze link werkt niet meer',
            'Misschien heb je je seintje al ingetrokken, of zijn je gegevens al verwijderd. Wil je alsnog een seintje? ' . $opnieuw . '.', 'af-gesloten');
        break;
    case 'verlopen':
        $inhoud = $kaart('Deze link is verlopen',
            'Je hebt je seintje niet binnen ' . (int)seintje_werving_bevestig_dagen() . ' dagen bevestigd. ' . $opnieuw . ', dan krijg je een nieuwe mail.', 'af-gesloten');
        break;
    case 'onbevestigd':
        $inhoud = $kaart('Bevestig je seintje',
            'Hoi ' . h((string)$rij['voornaam']) . ', klik op de knop om te bevestigen dat je één bericht wilt als de aanmelding opengaat.',
            '', ['actie' => 'bevestigen', 'tekst' => 'Ja, stuur me een seintje'],
            'Heb je dit niet zelf aangevraagd? Sluit deze pagina dan gewoon. Zonder bevestiging verwijderen we de aanvraag vanzelf.');
        break;
    case 'actief':
        $inhoud = $kaart($s['klaar'] === 'bevestigd' ? 'Top, we laten van ons horen' : 'Je krijgt een seintje',
            'Zodra je je kunt aanmelden, sturen we je één bericht. Daarna verwijderen we je gegevens, en anders uiterlijk na ' . (int)seintje_termijn_mnd() . ' maanden.',
            'af-succes', ['actie' => 'intrekken', 'tekst' => 'Toch geen seintje', 'klasse' => 'af-terug'],
            'Met die knop verwijderen we je voornaam en e-mailadres meteen.');
        break;
    case 'verstuurd':
        $inhoud = $kaart('Je hebt je seintje gekregen',
            'We hebben je het bericht gestuurd dat de aanmelding open is. Je gegevens verwijderen we binnenkort vanzelf. '
            . '<a href="' . h($aanmeld_url) . '">Naar het aanmeldformulier</a>.',
            '', ['actie' => 'intrekken', 'tekst' => 'Verwijder mijn gegevens nu', 'klasse' => 'af-terug']);
        break;
    case 'open':
        $inhoud = $kaart('Goed nieuws: de aanmelding is open',
            'Je hoeft niet meer op een seintje te wachten. <a href="' . h($aanmeld_url) . '">Meld je nu aan</a>.', 'af-succes');
        break;
    default:   // 'formulier', eventueel met de fout van een mislukte aanvraag
        $opties = ['prefix' => 'af', 'extra_class' => 'af-zelfstandig',
                   'bron_code' => seintje_werving_bron_schoon((string)($_POST['bron_code'] ?? $_GET['bron'] ?? ''))];
        if ($s['aanvraag']) {
            $opties += ['seintje_waarden' => $_POST, 'seintje_fout' => $s['aanvraag']['fout'], 'seintje_code' => $s['aanvraag']['code']];
        }
        $inhoud = aanmeld_gesloten_html($opties);
}

echo aanmeld_pagina_html($inhoud, [
    'titel' => 'Seintje bij de volgende werving — Reddingsbrigade Paal 13',
    'h1'    => 'Seintje bij de volgende werving',
]);

<?php
/**
 * Paal 13 — Contextuele hulp in het beheer (het vraagteken rechtsboven).
 *
 * Eén korte uitleg per scherm plus een link naar het juiste hoofdstuk van de handleiding.
 * De handleidingen staan als Markdown in docs/handleiding/ en worden met
 * docs/handleiding/maak_export.ps1 omgezet naar HTML (voor deze links) en Word.
 *
 * Gebruik: hulp_tekst($actieve_nav) → ['titel', 'tekst', 'url'] of null.
 * Nieuw scherm? Voeg hier één regel toe en een hoofdstuk in handleiding_beheer.md met
 * hetzelfde anker (kop in kleine letters, spaties als koppeltekens, leestekens weg).
 */

if (!function_exists('hulp_tekst')) {

function hulp_registry(): array {
    // nav-id => [titel, tekst, handleiding, anker]
    return [
        'dashboard'       => ['Dashboard', 'Hier zie je in één oogopslag hoe het seizoen loopt. Klik op een funnel-tegel om die sollicitanten te bekijken. De systeemstatus waarschuwt als berichten vastlopen of ondertekeningen te lang openstaan.', 'beheer', 'dashboard'],
        'sollicitanten'   => ['Sollicitanten', 'Het hart van het systeem. Klik op een naam voor het detailpaneel met alle gegevens, notities en acties. Selecteer meerdere rijen voor bulkacties (uitnodigen, aannemen, bericht sturen). Een fase wijzigen zet automatisch de bijbehorende taken klaar en verstuurt het bericht.', 'beheer', 'sollicitanten'],
        'paginas'         => ['Landingspagina\'s', 'Eigen wervingspagina\'s op /p/<naam>: upload een compleet HTML-ontwerp, bekijk het scanrapport en zet de pagina op Gepubliceerd. Het aanmeldformulier staat in de pagina; de herkomst van elke aanmelding komt in het dossier.', 'landingspaginas', ''],
        'selectiedag'     => ['Selectiedag', 'Vier stappen: groepsindeling, inchecken (ook zelf op een tablet), beoordelen per persoon en de nabespreking met de bellijst. Vul per persoon scores en aantekeningen in; de uitslag zet je door naar de volgende fase.', 'beheer', 'selectiedag'],
        'ondertekeningen' => ['Ondertekeningen', 'Overzicht van alle digitale handtekeningen (gezondheidsverklaring, intake, loonheffing, lidmaatschap). Bij 18+ tekent de sollicitant zelf in het portaal; bij minderjarigen tekent de ouder via een link in de mail. "Bekijken" opent de verzegelde PDF.', 'beheer', 'ondertekeningen'],
        'documenten'      => ['Loondossiers', 'Per aangenomen strandwacht: loonheffingsformulier (verzegelde PDF), kopie identiteitsbewijs en kopie bankpas. Alles staat versleuteld in de kluis; elke download wordt vastgelegd en krijgt een watermerk. Markeer een dossier als "verwerkt" zodra de loonadministratie het heeft overgenomen.', 'penningmeester', 'het-scherm-loondossiers'],
        'beschikbaarheid' => ['Beschikbaarheid', 'Wie kan wanneer werken. Per sollicitant zie je de werkperiodes, vakanties en verzoeken om minder dagen; verzoeken keur je hier goed of af (de sollicitant krijgt bericht). Exporteren kan als overzicht per week of per dag.', 'beheer', 'beschikbaarheid-dekking-en-loonplanning'],
        'dekking'         => ['Dekking', 'Per dag en post: hoeveel mensen er beschikbaar zijn tegenover wat er nodig is. Rood is onderbezet. De posten en de norm per post stel je in bij Instellingen › Posten & bezetting.', 'beheer', 'beschikbaarheid-dekking-en-loonplanning'],
        'loonplanning'    => ['Loonplanning', 'Een schatting van de loonkosten van het seizoen op basis van de beschikbaarheid, de functies en de tarieven uit Instellingen › Vakantietoeslag & lonen. Voor de begroting, niet voor de salarisadministratie.', 'beheer', 'beschikbaarheid-dekking-en-loonplanning'],
        'instellingen'    => ['Instellingen', 'Alles instelbaar per tabblad: organisatie, functies, uitnodigen en automatisering, beoordelingscriteria, posten, lonen, mail, e-mailserver, AI, Authentik, website-koppeling, berichten per kanaal en de bewaartermijnen (AVG).', 'beheer', 'instellingen'],
        'seizoen'         => ['Seizoenbeheer', 'Maak een seizoen aan en zet het op Open zodra de aanmelding mag beginnen. De datums, tijden, adressen en "wat neem je mee" van de selectiedag en het trainingsweekend staan hier; de sollicitant ziet ze op zijn startpagina en in de mails.', 'beheer', 'seizoen-opzetten'],
        'berichten'       => ['Berichten', 'Stuur een bericht aan één persoon, een selectie of een hele fase, per e-mail, WhatsApp/sms of beide, met voorbeeld en testmail. Onder Log zie je elk verstuurd bericht met kanaal en status; bij een mislukt WhatsApp- of sms-bericht gaat het automatisch per e-mail.', 'beheer', 'berichten-sturen'],
        'mail_templates'  => ['Sjablonen', 'Per meldingstype bewerk je de e-mail, de korte tekst voor WhatsApp/sms en de samenvatting in het portaal. Gebruik variabelen zoals {{voornaam}} en {{selectiedag_datum}}; het voorbeeld toont hoe het eruitziet. Eigen sjablonen gebruik je bij Berichten en Geplande berichten.', 'beheer', 'sjablonen'],
        'geplande_berichten' => ['Geplande berichten', 'Regels als "10 dagen na het inleveren van het formulier: stuur het magazine", ook vóór of na de selectiedag of het trainingsweekend. Proefdraaien laat zien wie wat zou krijgen zonder te versturen.', 'beheer', 'geplande-berichten-en-herinneringen'],
        'systeem'         => ['Systeem & Updates', 'De versie en changelog, de systeemcontrole (database, cron, mailwachtrij, opschoning, mappen, configuratie, kluis, zegel), migraties uitvoeren na een nieuwe versie, herinneringen proefdraaien en het foutenlog.', 'beheer', 'systeem-en-foutenlog'],
        'logboek'         => ['Logboek', 'Alle sporen van het systeem in één tijdlijn: wie wat wanneer deed, verstuurde berichten, technische fouten, het bewijsspoor van ondertekeningen, wat de bewaartermijnen hebben opgeruimd en mislukte inlogpogingen. Klik op een object om alles te zien wat daarmee gebeurd is.', 'beheer', 'logboek'],
        'beheerders'      => ['Beheerders', 'Wie toegang heeft tot het beheer en met welke rol. Nieuwe beheerders komen via Authentik: zet iemand daar in de juiste groep en laat hem één keer inloggen. Een wachtwoord zet je alleen als noodingang.', 'beheer', 'beheerders-en-rechten'],
        'rollen'          => ['Rollen & rechten', 'Per rol (begeleider, bestuur, penningmeester, admin) welke onderdelen zichtbaar en bewerkbaar zijn. Admin heeft altijd alles. Wijzigingen worden vastgelegd in het logboek.', 'beheer', 'beheerders-en-rechten'],
        'profiel'         => ['Mijn profiel', 'Je eigen naam, e-mailadres en wachtwoord voor de noodingang. Inloggen gaat normaal via Authentik.', 'beheer', 'inloggen-en-rollen'],
    ];
}

/** Basisadres van de HTML-handleidingen (gegenereerd uit de Markdown, zie maak_export.ps1). */
function hulp_url(string $handleiding, string $anker = ''): string {
    $bestand = ['beheer' => 'handleiding_beheer', 'penningmeester' => 'handleiding_penningmeester',
                'sollicitant' => 'handleiding_sollicitant', 'landingspaginas' => 'landingspaginas'][$handleiding] ?? 'handleiding_beheer';
    $base = defined('APP_URL') ? APP_URL : '';
    return $base . '/docs/handleiding/' . $bestand . '.html' . ($anker !== '' ? '#' . $anker : '');
}

/** Hulptekst voor een scherm: ['titel','tekst','url'] of null als er niets voor is. */
function hulp_tekst(string $nav_id): ?array {
    $r = hulp_registry()[$nav_id] ?? null;
    if (!$r) return null;
    return ['titel' => $r[0], 'tekst' => $r[1], 'url' => hulp_url($r[2], $r[3])];
}

}

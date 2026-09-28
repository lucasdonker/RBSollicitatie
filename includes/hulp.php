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
 * hetzelfde anker (kop in kleine letters, spaties als koppeltekens, leestekens weg), en
 * de stappen van de rondleiding in includes/beheer/rondleiding_stappen.php.
 */

if (!function_exists('hulp_tekst')) {

function hulp_registry(): array {
    // nav-id => [titel, tekst, handleiding, anker]
    return [
        'dashboard'          => ['Vandaag', 'Bovenaan zie je waar het seizoen staat en de kengetallen (klik op een tegel voor de lijst). Links wat je moet doen: de taak die nu telt en daaronder alles wat verder op je wacht, elk met een knop. Rechts wat er nieuw is sinds je vorige bezoek en de agenda van de komende weken. Klik op een staaf bij Waar staat iedereen om die sollicitanten te zien. Staat de systeemstatus op Aandacht nodig, dan lopen berichten vast of draait de taakronde niet.', 'beheer', 'vandaag'],
        'sollicitanten'      => ['Sollicitanten', 'Kies een fase-tab, zoek of filter, en klik op een naam om het dossier te openen. De knop in de kolom Actie zet de volgende stap; met vinkjes doe je dat voor meerdere mensen tegelijk. Je ziet altijd eerst wat er gebeurt en kunt kiezen om zonder bericht door te voeren.', 'beheer', 'sollicitanten'],
        'sollicitant'        => ['Het dossier', 'Alles over één sollicitant: het tabblad Dossier geeft gegevens, advies, fase, taken en notities; de andere tabbladen het formulier, beschikbaarheid & inzet (opgave en afspraak per week naast elkaar), de selectiedag, het weekend, de ondertekeningen en de tijdlijn. Met Vorige en Volgende loop je door de lijst waar je vandaan kwam. Een fase zetten via de fasekaart vraagt eerst bevestiging.', 'beheer', 'het-dossier'],
        'selectiedag'        => ['Selectiedag', 'Zes stappen: voorbereiden (wie komt er, wat moet nog getekend worden), groepen indelen, inchecken (ook zelf op een tablet), beoordelen met cijfers en observaties, nabespreken en de bellijst. Een besluit in de nabespreking verandert nog niets. Pas als je na het bellen doorvoert, verandert de fase en gaat het bericht uit. Twijfel staat ook op de bellijst: na het gesprek wordt het door of niet, en bij door leg je meteen vast of iemand op het trainingsweekend komt. Wie een sollicitant opent, heeft die sollicitant 30 minuten op naam om te bellen; een ander ziet dat.', 'beheer', 'selectiedag'],
        'trainingsweekend'   => ['Trainingsweekend', 'Zes stappen. Voorbereiden: wie komt er (met hun opmerking), wie mist nog beschikbaarheid. Groepen: indelen zoals op de selectiedag; "niet samen met" is dezelfde lijst. Inchecken: de link en code voor de tablet, en hier als backup. Beoordelen: op je telefoon observaties per persoon, per dag; wie het bestuur extra aandacht vraagt staat bovenaan, terugkerende strandwachten onderaan. Evaluatie (bestuur): per persoon alvast sowieso aannemen, twijfel of sowieso afwijzen; alleen twijfel zien de beoordelaars, met jullie vraag. Gesprekken (bestuur): iedereen, in de volgorde die uitkomt, met alle informatie en de inzet erbij. Aannemen of afwijzen gaat meteen door, met jullie bericht in de mail; twijfel blijft staan.', 'beheer', 'trainingsweekend'],
        'ondertekeningen'    => ['Ondertekeningen', 'Wie moet nog tekenen en wat is al binnen: gezondheidsverklaring, intake, loonheffing en lidmaatschap. Vanaf 18 tekent de sollicitant zelf in het portaal, jonger tekent een ouder via een link in de mail. Komt een mail niet aan, gebruik dan Link kopiëren en stuur de link zelf.', 'beheer', 'ondertekeningen'],
        'seizoen'            => ['Seizoen', 'Maak een seizoen aan, vul de werkperiode en de datums van de selectiedag en het trainingsweekend in, en klik op Aanmelding openen zodra de aanmelding mag beginnen (met Aanmelding sluiten gaat die weer dicht). Deze gegevens staan in de mails en op de startpagina van de sollicitant. Er staat maar één aanmelding tegelijk open: openen sluit de vorige vanzelf.', 'beheer', 'seizoen-opzetten'],
        'paginas'            => ['Landingspagina\'s', 'Eigen landingspagina\'s op /p/ gevolgd door het adres: upload een HTML-ontwerp, bekijk het scanrapport en zet de pagina op Gepubliceerd. Per pagina zie je bezoekers, aanmeldingen en waar mensen in het formulier afhaken. De volledige uitleg staat in de handleiding Landingspagina\'s.', 'beheer', 'landingspaginas'],
        'beschikbaarheid'    => ['Beschikbaarheid', 'Per week wie er kan werken. Beoordeel hier de verzoeken om minder dagen (de sollicitant krijgt bericht) en klik op een cel om een week te corrigeren, altijd met een reden. Wie nog voorlopig is krijgt vanzelf herinneringen, zolang de herinnering "Beschikbaarheid nog voorlopig" aanstaat (Instellingen › Uitnodigen & automatisch).', 'beheer', 'het-beschikbaarheidsraster'],
        'dekking'            => ['Dekking', 'Per week of de posten te bemannen zijn met de juiste competenties uit Authentik: Gedekt, Krap of Tekort. Klik op een week voor de rollen en het bellijstje. De rollen per post stel je in bij Instellingen › Posten & bezetting.', 'beheer', 'het-dekkingsscherm'],
        'loonplanning'       => ['Loonplanning', 'Per persoon per week: groot wat je plant, klein wat iemand opgaf. Klik een week om te plannen (je ziet per dag of iemand kan), klik een naam voor functie, afspraak en slot. Werk in scenario\'s; de ster is de actieve planning. Na het trainingsweekend maak je de planning definitief: dat wordt de afspraak voor de sollicitant, de loonadministratie en het rooster. Lonen en budget: Instellingen › Lonen & budget.', 'beheer', 'de-loonplanning'],
        'documenten'         => ['Loondossiers', 'Per aangenomen strandwacht het loonheffingsformulier, de ID-kopie, de bankpas en de inschrijving als lid, versleuteld in de kluis. Elke download wordt vastgelegd. Markeer een dossier als verwerkt zodra het in de loonadministratie staat.', 'penningmeester', 'het-scherm-loondossiers'],
        'berichten'          => ['Berichten', 'Stuur een bericht aan één persoon, een fase of een groep uit vorige seizoenen, per e-mail, WhatsApp/SMS of allebei; eerst een voorbeeld of testmail. Onder Verzonden zie je per bericht of het aankwam en waarom niet. Gepland bevat de automatische regels.', 'beheer', 'berichten-sturen'],
        'geplande_berichten' => ['Geplande berichten', 'Regels als "10 dagen na het inleveren van het formulier: stuur het magazine", ook vóór of na de selectiedag of het trainingsweekend. Maak eerst een eigen sjabloon. Proefdraaien laat zien wie wat zou krijgen zonder te versturen.', 'beheer', 'geplande-berichten-en-herinneringen'],
        'mail_templates'     => ['Sjablonen', 'Per soort bericht de e-mail, de korte tekst voor WhatsApp/SMS, de samenvatting in het portaal en het kanaal. Gebruik variabelen zoals {{voornaam}} en controleer met het voorbeeld. Terugzetten naar standaard kan altijd.', 'beheer', 'sjablonen'],
        'instellingen'       => ['Instellingen', 'Alles per onderwerp in zes groepen: organisatie, selectie en seizoen, inzet en loon, berichten, koppelingen, en privacy en onderhoud. Elk tabblad heeft een eigen knop Opslaan. Zet onder Bewaren & opschonen het automatisch opschonen aan.', 'beheer', 'instellingen'],
        'beheerders'         => ['Beheerders & rechten','Wie toegang heeft tot het beheer en met welke rol. Nieuwe beheerders komen via Authentik: zet de persoon in de juiste groep, controleer de toegestane admin-groepen en laat die één keer inloggen. Een wachtwoord zet je alleen als noodingang.', 'beheer', 'beheerders-en-rechten'],
        'rollen'             => ['Rollen & rechten', 'Per rol de Authentik-groep en per onderdeel of de rol mag lezen, bewerken of verwijderen. Admin heeft altijd alles. Elke wijziging komt in het logboek.', 'beheer', 'het-tabblad-rollen--rechten'],
        'logboek'            => ['Logboek', 'Alle sporen in één tijdlijn: wie wat wanneer deed, verstuurde berichten, technische fouten, het bewijsspoor van ondertekeningen, de opschoning en mislukte logins. Klik op een object om alles te zien wat daarmee gebeurde.', 'beheer', 'logboek'],
        'systeem'            => ['Systeem & Updates', 'De versie en changelog, de systeemcontrole (database, cron, mailwachtrij, opschoning, mappen, configuratie, kluis, zegel), migraties na een update en het proefdraaien van herinneringen. Fouten staan in het Logboek onder Fouten.', 'beheer', 'systeem-en-foutenlog'],
        'foutenlog'          => ['Fouten', 'Technische fouten staan in het Logboek onder Fouten, met een code en een referentienummer. Zoek het ref-nummer van de foutpagina op in "Zoek in details". Komt dezelfde fout vaker terug, meld hem dan bij de technisch beheerder.', 'beheer', 'fouten-opzoeken'],
        'profiel'            => ['Mijn profiel', 'Je weergavenaam, je wachtwoord voor de noodingang en het donkere thema. Wie via Authentik inlogt, wijzigt het wachtwoord in Authentik.', 'beheer', 'inloggen-en-rollen'],
        'import'             => ['Sollicitanten importeren', 'Eenmalig een bestaande lijst inlezen uit een CSV-bestand: kies het seizoen en de fase en lees in. Dubbele e-mailadressen worden overgeslagen, dus opnieuw inlezen kan veilig.', 'beheer', 'sollicitanten'],
        'handleiding'        => ['Hulp en handleidingen', 'Rondleidingen per rol die je op de echte schermen laten zien wat waar staat, en de handleidingen als naslag met een zoekveld. Het vraagteken rechtsboven op elk scherm brengt je naar de uitleg van dat scherm.', 'beheer', 'aan-de-slag'],
    ];
}

/**
 * De handleidingen: sleutel => titel, voor wie, het bestand in docs/handleiding/ en eventueel de
 * module die je moet mogen lezen. Dit is meteen de whitelist van admin/handleiding.php
 * (de map docs/ is afgeschermd met docs/.htaccess).
 *
 * Alleen het runbook is beperkt: dat gaat over de server, net als Systeem & Updates. De andere
 * handleidingen mag iedereen met toegang tot het beheer lezen; ze bevatten geen persoonsgegevens.
 */
function hulp_handleidingen(): array {
    return [
        'beheer'         => ['titel' => 'Handleiding beheer',            'voor' => 'Bestuur: van seizoen opzetten tot aangenomen, scherm voor scherm', 'bestand' => 'handleiding_beheer',         'module' => null],
        'landingspaginas'=> ['titel' => "Handleiding landingspagina's",  'voor' => 'Bestuur: eigen landingspagina\'s maken en publiceren',             'bestand' => 'landingspaginas',             'module' => null],
        'penningmeester' => ['titel' => 'Handleiding penningmeester',    'voor' => 'Penningmeester: van beschikbaarheid en loonplanning tot het gesprek en het loondossier',       'bestand' => 'handleiding_penningmeester',  'module' => null],
        'sollicitant'    => ['titel' => 'Jouw sollicitatie bij Paal 13', 'voor' => 'Sollicitanten en ouders: uitleg over het portaal',                'bestand' => 'handleiding_sollicitant',     'module' => null],
        'verwerkingsregister' => ['titel' => 'Verwerkingsregister (AVG)', 'voor' => 'Bestuur en admin: welke persoonsgegevens we verwerken, waarom, hoe lang en met wie', 'bestand' => 'verwerkingsregister', 'module' => 'instellingen'],
        'systeembeheer'  => ['titel' => 'Runbook systeembeheer',         'voor' => 'Technisch beheer: installeren, uitrollen, back-up en herstel',    'bestand' => 'runbook_systeembeheer',       'module' => 'systeem'],
    ];
}

/** Mag de ingelogde beheerder deze handleiding lezen? */
function hulp_handleiding_mag(string $sleutel): bool {
    $h = hulp_handleidingen()[$sleutel] ?? null;
    if (!$h) return false;
    if ($h['module'] === null) return true;
    return !function_exists('mag') || mag($h['module'], 'lezen');
}

/** De handleidingen die de ingelogde beheerder mag lezen. */
function hulp_handleidingen_zichtbaar(): array {
    return array_filter(hulp_handleidingen(), fn($s) => hulp_handleiding_mag($s), ARRAY_FILTER_USE_KEY);
}

/** Bestandsnaam (zonder extensie) van een handleiding, of null als hij niet bestaat of niet mag. */
function hulp_handleiding_bestand(string $sleutel): ?string {
    if (!hulp_handleiding_mag($sleutel)) return null;
    return hulp_handleidingen()[$sleutel]['bestand'] ?? null;
}

/**
 * Adres van een handleiding. De HTML staat in docs/handleiding/ (gegenereerd uit de Markdown met
 * maak_export.ps1), maar die map is afgeschermd; lezen gaat via admin/handleiding.php.
 */
function hulp_url(string $handleiding, string $anker = ''): string {
    if (!isset(hulp_handleidingen()[$handleiding])) $handleiding = 'beheer';
    $base = defined('APP_URL') ? APP_URL : '';
    return $base . '/admin/handleiding.php?doc=' . rawurlencode($handleiding) . ($anker !== '' ? '#' . $anker : '');
}

/**
 * Een gegenereerde handleiding (pandoc-HTML) in stukken, om in het beheer te tonen met
 * een eigen inhoudsopgave en zoekveld.
 *
 * Pandoc maakt met --shift-heading-level-by=-1 van elk `##`-hoofdstuk een <h1 id="anker">.
 * In het beheer staat de paginatitel al in een <h1>, dus schuift alles hier één niveau
 * op: het hoofdstuk krijgt zijn kop van admin/handleiding.php (h2), subkoppen worden h3/h4.
 * Het hoofdstuk "Inhoud" valt weg — daar is de inhoudsopgave naast de tekst voor in de
 * plaats gekomen. De Word-versie heeft hem nog wel.
 *
 * @return array{intro:string, hoofdstukken: array<int, array{id:string, titel:string, html:string}>}
 */
function hulp_handleiding_delen(string $html): array {
    $b = stripos($html, '<body');
    if ($b !== false) {
        $b = strpos($html, '>', $b);
        $e = strripos($html, '</body>');
        $html = substr($html, $b + 1, ($e !== false ? $e : strlen($html)) - $b - 1);
    }
    $html = preg_replace('~<header id="title-block-header">.*?</header>~s', '', $html);

    $delen = preg_split('~(?=<h1\b)~i', $html);
    $intro = trim((string)array_shift($delen));
    $hoofdstukken = [];
    foreach ($delen as $d) {
        if (!preg_match('~^<h1[^>]*\bid="([^"]+)"[^>]*>(.*?)</h1>~s', $d, $m)) continue;
        if ($m[1] === 'inhoud') continue;
        $rest = substr($d, strlen($m[0]));
        $rest = str_ireplace(['<h3', '</h3>'], ['<h4', '</h4>'], $rest);
        $rest = str_ireplace(['<h2', '</h2>'], ['<h3', '</h3>'], $rest);
        $hoofdstukken[] = [
            'id'    => $m[1],
            'titel' => html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'html'  => trim($rest),
        ];
    }
    return ['intro' => $intro, 'hoofdstukken' => $hoofdstukken];
}

/** Pad naar een bestand van een handleiding (html of docx), of null. */
function hulp_handleiding_pad(string $sleutel, string $formaat = 'html'): ?string {
    $bestand = hulp_handleiding_bestand($sleutel);
    if ($bestand === null) return null;
    $map = dirname(__DIR__) . '/docs/handleiding';
    $pad = $formaat === 'docx' ? "$map/word/$bestand.docx" : "$map/$bestand.html";
    return is_file($pad) ? $pad : null;
}

/** Hulptekst voor een scherm: ['titel','tekst','url'] of null als er niets voor is. */
function hulp_tekst(string $nav_id): ?array {
    $r = hulp_registry()[$nav_id] ?? null;
    if (!$r) return null;
    return ['titel' => $r[0], 'tekst' => $r[1], 'url' => hulp_url($r[2], $r[3])];
}

}

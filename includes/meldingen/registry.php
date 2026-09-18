<?php
/**
 * Paal 13 — Meldingen: het register van meldingstypen.
 *
 * Eén plek die vastlegt WELKE meldingen het systeem kent: sleutel (= template-slug), naam,
 * categorie, ontvanger, standaard-kanaalmodus en de standaard-inhoud (seed) voor mail én
 * korte tekst (WhatsApp/SMS). De inhoud zelf staat in de tabel `mail_templates` en is in het
 * beheer te bewerken; dit register levert de definitie en de standaardteksten.
 *
 * Kanaalmodus per type (instelling `notif.<type>`):
 *   email  = alleen e-mail
 *   kort   = kort bericht (WhatsApp of SMS, afhankelijk van de voorkeur van de sollicitant);
 *            valt terug op e-mail als het korte kanaal niet kan of mislukt
 *   beide  = e-mail én kort bericht
 *   uit    = niet versturen
 */

if (!function_exists('meldingen_register')) {

/** Categorieën (sleutel => label) in de volgorde voor het beheer. */
function meldingen_categorieen(): array {
    return [
        'fase'        => 'Fase-overgangen',
        'taak'        => 'Taken en documenten',
        'herinnering' => 'Herinneringen',
        'bestuur'     => 'Meldingen aan het bestuur',
        'systeem'     => 'Systeem',
        'handmatig'   => 'Handmatig',
        'gepland'     => 'Gepland',
    ];
}

/** Kanaalmodi (sleutel => label). */
function meldingen_kanaalmodi(): array {
    return ['email' => 'E-mail', 'kort' => 'WhatsApp/SMS', 'beide' => 'E-mail + WhatsApp/SMS', 'uit' => 'Uit'];
}

/**
 * Alle meldingstypen. Sleutel = type = template-slug.
 * Velden per type:
 *   naam, beschrijving, categorie, ontvanger (sollicitant|ouder|ondertekenaar|bestuur|direct),
 *   kanaal_standaard, kort_toegestaan, dedup_min (0 = geen dubbelcheck), body_loggen,
 *   vars (extra variabelen naast de standaard mail_variabelen), seed (onderwerp, hero_titel,
 *   hero_subtitel, hero_kleur, body_html, tekst_kort, samenvatting).
 */
function meldingen_register(): array {
    static $reg = null;
    if ($reg !== null) return $reg;

    $portaal = '{{portaal_url}}';
    $std = [
        'kort_toegestaan' => true, 'dedup_min' => 60, 'body_loggen' => true, 'vars' => [],
        'hero_kleur' => '#054d8a', 'ontvanger' => 'sollicitant',
    ];

    $reg = [
        // ── Fase-overgangen ───────────────────────────────────────────
        'aanmelding_bevestiging' => array_merge($std, [
            'naam' => 'Bevestiging van aanmelding', 'categorie' => 'fase', 'kanaal_standaard' => 'email',
            'beschrijving' => 'Direct na het aanmelden (via de site of handmatig door het beheer).',
            'seed' => [
                'onderwerp' => 'Je aanmelding voor {{seizoen_naam}} is ontvangen',
                'hero_titel' => 'Aanmelding ontvangen', 'hero_subtitel' => 'Welkom, {{voornaam}}',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Bedankt voor je aanmelding bij <strong>Reddingsbrigade Paal 13</strong> voor {{seizoen_naam}}!</p>\n<p>We hebben je aanmelding ontvangen en beoordelen deze. Word je uitgenodigd voor de selectiedag, dan krijg je bericht van ons.</p>\n<p>In de tussentijd kun je je gegevens bekijken in het portaal:</p>\n[knop href=\"$portaal\"]Naar het portaal[/knop]\n<p>Vragen? Mail naar <a href=\"mailto:{{bestuur_email}}\">{{bestuur_email}}</a>.</p>",
                'tekst_kort' => "Hoi {{voornaam}}, we hebben je aanmelding bij Paal 13 ontvangen. Je hoort van ons over de selectiedag. Volg je status: $portaal",
                'samenvatting' => 'Welkom. We hebben je aanmelding binnen.',
            ],
        ]),
        'uitnodiging_selectie' => array_merge($std, [
            'naam' => 'Uitnodiging selectiedag', 'categorie' => 'fase', 'kanaal_standaard' => 'beide',
            'beschrijving' => 'Als een sollicitant wordt uitgenodigd voor de selectiedag (fase "uitgenodigd selectiedag").',
            'seed' => [
                'onderwerp' => 'Je bent uitgenodigd voor de selectiedag — Paal 13',
                'hero_titel' => 'Je bent uitgenodigd!', 'hero_subtitel' => 'We zien je graag op de selectiedag',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Goed nieuws: we nodigen je van harte uit voor de <strong>selectiedag</strong> van Reddingsbrigade Paal 13! Dit is jouw kans om te ontdekken hoe het is om strandwacht te zijn, en onze kans om jou te leren kennen.</p>\n[info]<strong>De selectiedag op een rij</strong><br>\nWanneer: {{selectiedag_datum}}{{selectiedag_tijd}}<br>\nWaar: {{selectiedag_locatie}}[/info]\n<p><strong>Bevestig je deelname</strong><br>\nLog in op je portaal en vul het \"Beter leren kennen\"-formulier in. Daarmee bevestig je meteen je deelname aan de selectiedag. Er staan nog een of meer taken voor je klaar; je ziet ze zodra je inlogt.</p>\n[knop href=\"$portaal\"]Naar mijn portaal[/knop]\n<p><strong>Wat kun je verwachten?</strong><br>\nTijdens de selectiedag doe je een conditietest en een zwemtest, en hebben we een persoonlijk gesprek. Geen zorgen als je nog niet alles vloeiend kunt: we kijken vooral naar je inzet en enthousiasme.</p>\n<p>Tot op de selectiedag!<br><strong>Team Sollicitaties</strong></p>",
                'tekst_kort' => "Hoi {{voornaam}}, je bent uitgenodigd voor de selectiedag bij Paal 13 op {{selectiedag_datum}}. Bevestig je deelname in je portaal: $portaal",
                'samenvatting' => 'Je bent welkom op {{selectiedag_datum}}. Bevestig je deelname in het portaal.',
            ],
        ]),
        'selectie_aangemeld_bevestiging' => array_merge($std, [
            'naam' => 'Bevestiging aanmelding selectiedag', 'categorie' => 'fase', 'kanaal_standaard' => 'email',
            'beschrijving' => 'Zodra de sollicitant het "Beter leren kennen"-formulier heeft ingeleverd (= aangemeld voor de selectiedag).',
            'seed' => [
                'onderwerp' => 'Je deelname aan de selectiedag is bevestigd — Paal 13',
                'hero_titel' => 'Tot op de selectiedag!', 'hero_subtitel' => '{{selectiedag_datum}}',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Bedankt voor het invullen van je formulier. Je deelname aan de selectiedag op <strong>{{selectiedag_datum}}</strong> staat genoteerd.</p>\n[info]Wanneer: {{selectiedag_datum}}{{selectiedag_tijd}}<br>Waar: {{selectiedag_locatie}}[/info]\n<p>Staan er nog taken voor je open? Dan zie je die in je portaal.</p>\n[knop href=\"$portaal\"]Naar mijn portaal[/knop]",
                'tekst_kort' => "Hoi {{voornaam}}, je deelname aan de selectiedag op {{selectiedag_datum}} is bevestigd. Details in je portaal: $portaal",
                'samenvatting' => 'Je deelname op {{selectiedag_datum}} staat genoteerd.',
            ],
        ]),
        'uitnodiging_tw' => array_merge($std, [
            'naam' => 'Uitnodiging trainingsweekend', 'categorie' => 'fase', 'kanaal_standaard' => 'beide',
            'beschrijving' => 'Als een sollicitant wordt uitgenodigd voor het trainingsweekend.',
            'seed' => [
                'onderwerp' => 'Je bent uitgenodigd voor het trainingsweekend — Paal 13',
                'hero_titel' => 'Trainingsweekend', 'hero_subtitel' => 'Je bent door de selectie!',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Geweldig nieuws: je bent door de selectiedag en we nodigen je uit voor het <strong>trainingsweekend</strong>.</p>\n[info]Wanneer: {{tw_datum_start}} t/m {{tw_datum_eind}}<br>Waar: {{tw_locatie}}<br>Verzamelen: {{tw_verzameltijd}}[/info]\n<p>Geef in je portaal je beschikbaarheid voor de zomer door; die hebben we nodig voor de planning.</p>\n[knop href=\"$portaal\"]Beschikbaarheid doorgeven[/knop]\n<p>We zien je graag op het trainingsweekend!</p>",
                'tekst_kort' => "Hoi {{voornaam}}, je bent uitgenodigd voor het trainingsweekend van Paal 13 ({{tw_datum_start}}). Geef je beschikbaarheid door in je portaal: $portaal",
                'samenvatting' => 'Je bent door! Trainingsweekend {{tw_datum_start}} t/m {{tw_datum_eind}}.',
            ],
        ]),
        'aangenomen' => array_merge($std, [
            'naam' => 'Welkom: aangenomen', 'categorie' => 'fase', 'kanaal_standaard' => 'email',
            'beschrijving' => 'Als een sollicitant is aangenomen. Het kennismakingspakket (taken) wordt tegelijk klaargezet.',
            'seed' => [
                'onderwerp' => 'Welkom bij Paal 13, {{voornaam}}!',
                'hero_titel' => 'Welkom bij Paal 13!', 'hero_subtitel' => 'Aangenomen voor {{seizoen_naam}}',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p><strong>Van harte welkom bij Reddingsbrigade Paal 13!</strong> We zijn blij dat je aan boord komt voor {{seizoen_naam}}.</p>\n<h3>Wat gebeurt er nu?</h3>\n<p>In het portaal staan een paar taken die je nog afrondt:</p>\n<ul>\n  <li>Bevestig je lidmaatschap</li>\n  <li>Vul het loonheffingsformulier in</li>\n  <li>Neem de huisregels door</li>\n  <li>Wacht op de uitnodiging voor je Paal 13-account</li>\n</ul>\n[knop href=\"$portaal\"]Open het portaal[/knop]\n<p>Vragen? Mail naar <a href=\"mailto:{{bestuur_email}}\">{{bestuur_email}}</a>.</p>\n<p>Tot snel op het strand!<br><strong>Team Sollicitaties</strong></p>",
                'tekst_kort' => "Hoi {{voornaam}}, welkom bij Paal 13! Je bent aangenomen. In je portaal staan een paar taken klaar: $portaal",
                'samenvatting' => 'Je bent aangenomen. Welkom bij Paal 13!',
            ],
        ]),
        'afgewezen' => array_merge($std, [
            'naam' => 'Niet geselecteerd', 'categorie' => 'fase', 'kanaal_standaard' => 'uit', 'kort_toegestaan' => false,
            'beschrijving' => 'Als een sollicitant niet verder gaat. Staat standaard uit: het bestuur belt of mailt persoonlijk.',
            'seed' => [
                'onderwerp' => 'Terugkoppeling selectieprocedure {{seizoen_naam}}',
                'hero_titel' => 'Terugkoppeling', 'hero_subtitel' => '',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Na zorgvuldige beoordeling hebben we helaas besloten je niet verder mee te nemen in de selectieprocedure voor {{seizoen_naam}}.</p>\n<p>We hebben dit jaar veel aanmeldingen ontvangen en de keuze was moeilijk. Bedankt voor je enthousiasme en de moeite die je hebt gedaan.</p>\n<p>Vragen over onze beslissing? Neem gerust contact op via <a href=\"mailto:{{bestuur_email}}\">{{bestuur_email}}</a>.</p>\n<p>We hopen je misschien in een volgend seizoen weer te zien.</p>",
                'tekst_kort' => '',
                'samenvatting' => 'Terugkoppeling over je sollicitatie.',
            ],
        ]),

        // ── Taken en documenten ───────────────────────────────────────
        'taak_afgerond' => array_merge($std, [
            'naam' => 'Bevestiging taak afgerond', 'categorie' => 'taak', 'kanaal_standaard' => 'email', 'dedup_min' => 5,
            'beschrijving' => 'Nadat de sollicitant een taak afrondt (formulier ingeleverd, beschikbaarheid definitief, document getekend).',
            'vars' => ['taak_naam'],
            'seed' => [
                'onderwerp' => 'Taak afgerond: {{taak_naam}} — Paal 13',
                'hero_titel' => 'Taak afgerond', 'hero_subtitel' => '{{taak_naam}}',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>De taak <strong>{{taak_naam}}</strong> is afgerond. In je portaal zie je of er nog andere acties openstaan.</p>\n[knop href=\"$portaal\"]Naar mijn portaal[/knop]",
                'tekst_kort' => "Hoi {{voornaam}}, je taak \"{{taak_naam}}\" is afgerond. Kijk in je portaal of er nog iets openstaat: $portaal",
                'samenvatting' => '{{taak_naam}} is afgerond.',
            ],
        ]),
        'ouder_gegevens_ontvangen' => array_merge($std, [
            'naam' => 'Taken klaargezet na oudergegevens', 'categorie' => 'taak', 'kanaal_standaard' => 'email', 'dedup_min' => 720,
            'beschrijving' => 'Nadat een minderjarige sollicitant de gegevens van de ouder/verzorger heeft ingevuld en de formulieren zijn klaargezet.',
            'seed' => [
                'onderwerp' => 'Er staan taken voor je klaar — Paal 13',
                'hero_titel' => 'Taken klaargezet', 'hero_subtitel' => 'Nog een paar stappen',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Bedankt voor de gegevens van je ouder/verzorger. Die krijgt nu een e-mail met de formulieren om te ondertekenen.</p>\n<p>In je portaal zie je welke acties nog voor jou openstaan: het \"Beter leren kennen\"-formulier en je beschikbaarheid.</p>\n[knop href=\"$portaal\"]Naar mijn portaal[/knop]",
                'tekst_kort' => "Hoi {{voornaam}}, je ouder/verzorger krijgt nu de formulieren per e-mail. Jouw open taken staan in je portaal: $portaal",
                'samenvatting' => 'Je ouder/verzorger heeft de formulieren ontvangen.',
            ],
        ]),
        'ondertekening_verzoek_ouder' => array_merge($std, [
            'naam' => 'Ondertekenverzoek aan ouder/verzorger', 'categorie' => 'taak', 'ontvanger' => 'ouder',
            'kanaal_standaard' => 'email', 'kort_toegestaan' => false, 'dedup_min' => 0,
            'beschrijving' => 'Naar de ouder/verzorger van een minderjarige sollicitant met de te ondertekenen formulieren.',
            'vars' => ['ouder_naam', 'formulieren_blok', 'document_naam', 'onderteken_url'],
            'seed' => [
                'onderwerp' => 'Handtekening nodig: formulieren voor {{voornaam}} — Reddingsbrigade Paal 13',
                'hero_titel' => 'Handtekening gevraagd', 'hero_subtitel' => 'Voor de selectiedag van {{voornaam}}',
                'body_html' => "<p>Beste {{ouder_naam}},</p>\n<p><strong>{{voornaam}}</strong> heeft gesolliciteerd als strandwacht bij Reddingsbrigade Paal 13 in Callantsoog en is uitgenodigd voor onze selectiedag. Daar maken we kennis en testen we de zwemvaardigheid in het zwembad.</p>\n<p>Omdat {{voornaam}} nog geen 18 jaar is, hebben wij uw toestemming en handtekening nodig op onderstaande documenten. Het ondertekenen gebeurt volledig digitaal en duurt ongeveer 3 minuten per formulier.</p>\n{{formulieren_blok}}\n<p><strong>Goed om te weten:</strong> uw gegevens worden alleen gebruikt voor deze verklaringen en vertrouwelijk behandeld. De ondertekening is 1 jaar geldig. Zonder deze formulieren kan {{voornaam}} helaas niet deelnemen aan de selectiedag.</p>\n<p>Heeft u vragen? Mail ons gerust.</p>\n<p>Met vriendelijke groet,<br><strong>Reddingsbrigade Paal 13</strong><br>Callantsoog</p>",
                'tekst_kort' => '',
                'samenvatting' => 'Ondertekenverzoek verstuurd aan je ouder/verzorger.',
            ],
        ]),
        'ondertekening_verzoek_zelf' => array_merge($std, [
            'naam' => 'Ondertekenverzoek aan sollicitant (18+)', 'categorie' => 'taak', 'kanaal_standaard' => 'email', 'dedup_min' => 0,
            'beschrijving' => 'Als het bestuur handmatig een document klaarzet dat de sollicitant zelf ondertekent.',
            'vars' => ['document_naam', 'onderteken_url'],
            'seed' => [
                'onderwerp' => 'Onderteken: {{document_naam}} — Paal 13',
                'hero_titel' => 'Handtekening gevraagd', 'hero_subtitel' => '{{document_naam}}',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Voor je sollicitatie bij Reddingsbrigade Paal 13 vragen we je <strong>{{document_naam}}</strong> digitaal te ondertekenen. Dit duurt ongeveer 3 minuten.</p>\n[knop href=\"{{onderteken_url}}\"]Formulier openen[/knop]\n<p>Vragen? Mail naar <a href=\"mailto:{{bestuur_email}}\">{{bestuur_email}}</a>.</p>",
                'tekst_kort' => "Hoi {{voornaam}}, wil je {{document_naam}} digitaal ondertekenen voor Paal 13? Dat kan hier: {{onderteken_url}}",
                'samenvatting' => 'Onderteken {{document_naam}}.',
            ],
        ]),
        'ondertekening_bevestiging' => array_merge($std, [
            'naam' => 'Bevestiging ondertekening (aan sollicitant)', 'categorie' => 'taak', 'kanaal_standaard' => 'email', 'dedup_min' => 0,
            'beschrijving' => 'Naar de sollicitant zodra een document is ondertekend (door de ouder/verzorger of door de sollicitant zelf; dan met PDF-kopie).',
            'vars' => ['document_naam', 'door'],
            'seed' => [
                'onderwerp' => '{{document_naam}} is ondertekend — Paal 13',
                'hero_titel' => 'Ondertekend', 'hero_subtitel' => '{{document_naam}}', 'hero_kleur' => '#1d9e75',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Goed nieuws: <strong>{{document_naam}}</strong> is ondertekend door {{door}}. Je hoeft hier niets meer voor te doen.</p>\n<p>In je portaal zie je de actuele status van al je taken.</p>\n[knop href=\"$portaal\"]Naar mijn portaal[/knop]",
                'tekst_kort' => "Hoi {{voornaam}}, {{document_naam}} is ondertekend door {{door}}. Status van je taken: $portaal",
                'samenvatting' => '{{document_naam}} is ondertekend door {{door}}.',
            ],
        ]),
        'ondertekening_kopie' => array_merge($std, [
            'naam' => 'Kopie ondertekend document (met PDF)', 'categorie' => 'taak', 'ontvanger' => 'ouder',
            'kanaal_standaard' => 'email', 'kort_toegestaan' => false, 'dedup_min' => 0, 'body_loggen' => false,
            'beschrijving' => 'Naar de ondertekenaar (ouder/verzorger) met het ondertekende document als PDF-bijlage.',
            'vars' => ['document_naam', 'ontvanger_naam'],
            'seed' => [
                'onderwerp' => 'Kopie ondertekend: {{document_naam}} — Paal 13',
                'hero_titel' => 'Kopie van je ondertekening', 'hero_subtitel' => 'Voor je administratie',
                'body_html' => "<p>Beste {{ontvanger_naam}},</p>\n<p>Bedankt voor het ondertekenen van <strong>{{document_naam}}</strong> voor {{volledige_naam}}.</p>\n<p>In de bijlage vind je een kopie van het ondertekende document, zodat je kunt terugzien waar je je handtekening onder hebt gezet.</p>\n<p>Met vriendelijke groet,<br><strong>Team Sollicitaties</strong><br>Reddingsbrigade Paal 13</p>",
                'tekst_kort' => '', 'samenvatting' => '',
            ],
        ]),
        'verzoek_beslist' => array_merge($std, [
            'naam' => 'Besluit verzoek minder dagen werken', 'categorie' => 'taak', 'kanaal_standaard' => 'beide', 'dedup_min' => 0,
            'beschrijving' => 'Als het bestuur een verzoek om minder dagen te werken goed- of afkeurt.',
            'vars' => ['besluit', 'besluit_html', 'verzoek_weken', 'verzoek_dagen', 'besluit_toelichting'],
            'seed' => [
                'onderwerp' => 'Je verzoek om minder dagen te werken is {{besluit}} — Paal 13',
                'hero_titel' => 'Verzoek {{besluit}}', 'hero_subtitel' => '{{verzoek_weken}}',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Het bestuur heeft je verzoek om in <strong>{{verzoek_weken}}</strong> maximaal <strong>{{verzoek_dagen}} dagen</strong> te werken beoordeeld: het is <strong>{{besluit}}</strong>.</p>\n{{besluit_html}}\n[knop href=\"{{portaal_url}}beschikbaarheid.php\"]Bekijk je beschikbaarheid[/knop]",
                'tekst_kort' => "Hoi {{voornaam}}, het bestuur heeft je verzoek om minder dagen te werken ({{verzoek_weken}}) beoordeeld: {{besluit}}. Details: {{portaal_url}}beschikbaarheid.php",
                'samenvatting' => 'Je verzoek voor {{verzoek_weken}} is {{besluit}}.',
            ],
        ]),

        // ── Herinneringen ─────────────────────────────────────────────
        'herinnering_taken_open' => array_merge($std, [
            'naam' => 'Herinnering: openstaande taken', 'categorie' => 'herinnering', 'kanaal_standaard' => 'email', 'dedup_min' => 0,
            'beschrijving' => 'Als er nog taken openstaan in het portaal (formulier, beschikbaarheid, documenten).',
            'vars' => ['taken_aantal', 'taken_blok'],
            'seed' => [
                'onderwerp' => 'Er staan nog taken voor je open — Paal 13',
                'hero_titel' => 'Nog even doen', 'hero_subtitel' => '{{taken_aantal}} open',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>In je portaal staan nog een of meer taken open. Rond ze af zodat we verder kunnen met je sollicitatie:</p>\n{{taken_blok}}\n[knop href=\"$portaal\"]Naar mijn taken[/knop]",
                'tekst_kort' => "Hoi {{voornaam}}, er staan nog {{taken_aantal}} taken voor je open in je Paal 13-portaal: $portaal",
                'samenvatting' => 'Je hebt nog {{taken_aantal}} open taken.',
            ],
        ]),
        'herinnering_selectie_aanmelden' => array_merge($std, [
            'naam' => 'Herinnering: nog niet aangemeld voor de selectiedag', 'categorie' => 'herinnering', 'kanaal_standaard' => 'beide', 'dedup_min' => 0,
            'beschrijving' => 'Uitgenodigd, maar het "Beter leren kennen"-formulier is nog niet ingeleverd terwijl de selectiedag nadert.',
            'seed' => [
                'onderwerp' => 'Bevestig je deelname aan de selectiedag — Paal 13',
                'hero_titel' => 'Kom je ook?', 'hero_subtitel' => 'Selectiedag {{selectiedag_datum}}', 'hero_kleur' => '#D43D00',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>De selectiedag op <strong>{{selectiedag_datum}}</strong> komt eraan, maar je hebt je deelname nog niet bevestigd. Dat doe je door het \"Beter leren kennen\"-formulier in je portaal in te vullen.</p>\n[knop href=\"$portaal\"]Formulier invullen[/knop]\n<p>Kun je niet? Laat het ons even weten via <a href=\"mailto:{{bestuur_email}}\">{{bestuur_email}}</a>.</p>",
                'tekst_kort' => "Hoi {{voornaam}}, de selectiedag op {{selectiedag_datum}} komt eraan. Bevestig je deelname via het formulier in je portaal: $portaal",
                'samenvatting' => 'Je hebt je deelname aan de selectiedag nog niet bevestigd.',
            ],
        ]),
        'herinnering_ouder_gegevens' => array_merge($std, [
            'naam' => 'Herinnering: gegevens ouder/verzorger', 'categorie' => 'herinnering', 'kanaal_standaard' => 'beide', 'dedup_min' => 0,
            'beschrijving' => 'Minderjarige sollicitant heeft de gegevens van de ouder/verzorger nog niet ingevuld.',
            'seed' => [
                'onderwerp' => 'We missen nog de gegevens van je ouder/verzorger — Paal 13',
                'hero_titel' => 'Nog één stap', 'hero_subtitel' => 'Gegevens ouder/verzorger',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Omdat je nog geen 18 bent, moet een ouder/verzorger een paar formulieren ondertekenen. Vul daarvoor in je portaal de naam en het e-mailadres van je ouder/verzorger in; wij sturen de formulieren dan naar hem of haar.</p>\n[knop href=\"$portaal\"]Gegevens invullen[/knop]",
                'tekst_kort' => "Hoi {{voornaam}}, vul in je Paal 13-portaal nog de gegevens van je ouder/verzorger in, dan kunnen de formulieren getekend worden: $portaal",
                'samenvatting' => 'Vul de gegevens van je ouder/verzorger in.',
            ],
        ]),
        'selectiedag_nadert' => array_merge($std, [
            'naam' => 'Selectiedag nadert', 'categorie' => 'herinnering', 'kanaal_standaard' => 'email', 'dedup_min' => 0,
            'beschrijving' => 'Een paar dagen vóór de selectiedag, naar iedereen die is uitgenodigd of aangemeld.',
            'seed' => [
                'onderwerp' => 'Bijna zover: de selectiedag — Paal 13',
                'hero_titel' => 'Bijna zover', 'hero_subtitel' => 'Selectiedag {{selectiedag_datum}}',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Het is bijna zover! De selectiedag is op <strong>{{selectiedag_datum}}</strong>{{selectiedag_tijd}} bij {{selectiedag_locatie}}.</p>\n<p>Bekijk in je portaal alle praktische info voor de dag.</p>\n[knop href=\"$portaal\"]Naar mijn portaal[/knop]\n<p>Tot dan!<br>Reddingsbrigade Paal 13</p>",
                'tekst_kort' => "Hoi {{voornaam}}, denk je aan de selectiedag op {{selectiedag_datum}}? Alle info in je portaal: $portaal",
                'samenvatting' => 'De selectiedag op {{selectiedag_datum}} komt eraan.',
            ],
        ]),
        'selectiedag_morgen' => array_merge($std, [
            'naam' => 'Selectiedag morgen', 'categorie' => 'herinnering', 'kanaal_standaard' => 'beide', 'dedup_min' => 0,
            'beschrijving' => 'De dag vóór de selectiedag: laatste praktische info.',
            'vars' => ['laatste_info'],
            'seed' => [
                'onderwerp' => 'Morgen al: de selectiedag — Paal 13',
                'hero_titel' => 'Tot morgen op het strand!', 'hero_subtitel' => '{{selectiedag_datum}}', 'hero_kleur' => '#D43D00',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Morgen is het zover: de selectiedag bij Paal 13!</p>\n[info]Wanneer: {{selectiedag_datum}}{{selectiedag_tijd}}<br>Waar: {{selectiedag_locatie}}<br>Adres: {{selectiedag_adres}}<br>Meenemen: {{selectiedag_meenemen}}[/info]\n<p>{{laatste_info}}</p>\n<p>Veel succes en tot morgen!<br>Reddingsbrigade Paal 13</p>",
                'tekst_kort' => "Hoi {{voornaam}}, morgen is de selectiedag! Verzamelen {{selectiedag_tijd}} bij {{selectiedag_locatie}}. Alle info: $portaal",
                'samenvatting' => 'Morgen is de selectiedag. Praktische info in dit bericht.',
            ],
        ]),
        'trainingsweekend_nadert' => array_merge($std, [
            'naam' => 'Trainingsweekend nadert', 'categorie' => 'herinnering', 'kanaal_standaard' => 'beide', 'dedup_min' => 0,
            'beschrijving' => 'Een paar dagen vóór het trainingsweekend, naar iedereen die is uitgenodigd.',
            'seed' => [
                'onderwerp' => 'Bijna zover: het trainingsweekend — Paal 13',
                'hero_titel' => 'Trainingsweekend', 'hero_subtitel' => '{{tw_datum_start}} t/m {{tw_datum_eind}}',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Het trainingsweekend komt eraan!</p>\n[info]Wanneer: {{tw_datum_start}} t/m {{tw_datum_eind}}<br>Waar: {{tw_locatie}}<br>Adres: {{tw_adres}}<br>Verzamelen: {{tw_verzameltijd}}<br>Meenemen: {{tw_meenemen}}[/info]\n[knop href=\"$portaal\"]Naar mijn portaal[/knop]\n<p>Tot dan!<br>Reddingsbrigade Paal 13</p>",
                'tekst_kort' => "Hoi {{voornaam}}, het trainingsweekend start {{tw_datum_start}}. Verzamelen {{tw_verzameltijd}} bij {{tw_locatie}}. Alle info: $portaal",
                'samenvatting' => 'Het trainingsweekend start {{tw_datum_start}}.',
            ],
        ]),
        'ondertekening_herinnering' => array_merge($std, [
            'naam' => 'Herinnering: document nog niet ondertekend', 'categorie' => 'herinnering', 'ontvanger' => 'ondertekenaar',
            'kanaal_standaard' => 'email', 'kort_toegestaan' => false, 'dedup_min' => 0,
            'beschrijving' => 'Naar de ondertekenaar (ouder of sollicitant) als een document na een paar dagen nog openstaat.',
            'vars' => ['ontvanger_naam', 'document_naam', 'onderteken_url', 'is_ouder'],
            'seed' => [
                'onderwerp' => 'Herinnering: {{document_naam}} — Paal 13',
                'hero_titel' => 'Herinnering', 'hero_subtitel' => '{{document_naam}}', 'hero_kleur' => '#D43D00',
                'body_html' => "<p>Beste {{ontvanger_naam}},</p>\n<p>Een vriendelijke herinnering: <strong>{{document_naam}}</strong> staat nog open. Zonder deze handtekening kan {{voornaam}} helaas niet deelnemen aan de selectiedag van Reddingsbrigade Paal 13.</p>\n[knop href=\"{{onderteken_url}}\"]Nu ondertekenen[/knop]\n<p>Met vriendelijke groet,<br><strong>Reddingsbrigade Paal 13</strong></p>",
                'tekst_kort' => "Hoi {{voornaam}}, er staat nog een document klaar om te ondertekenen: {{onderteken_url}}",
                'samenvatting' => '{{document_naam}} wacht nog op een handtekening.',
            ],
        ]),
        'beschikbaarheid_voorlopig' => array_merge($std, [
            'naam' => 'Herinnering: beschikbaarheid definitief maken', 'categorie' => 'herinnering', 'kanaal_standaard' => 'email', 'dedup_min' => 0,
            'beschrijving' => 'Beschikbaarheid staat nog op voorlopig terwijl de deadline nadert.',
            'vars' => ['deadline_datum', 'dagen_resterend', 'deadline_voorbij'],
            'seed' => [
                'onderwerp' => 'Maak je beschikbaarheid definitief — Paal 13',
                'hero_titel' => 'Nog even je beschikbaarheid definitief maken', 'hero_subtitel' => 'Deadline {{deadline_datum}}', 'hero_kleur' => '#D43D00',
                'body_html' => "<p>Hoi {{voornaam}},</p>\n<p>Je hebt je beschikbaarheid doorgegeven, maar die staat nog op <strong>voorlopig</strong>. We hebben je definitieve beschikbaarheid nodig om je in te plannen.</p>\n[info]<strong>Deadline: {{deadline_datum}}</strong>[/info]\n<p>Het duurt maar een minuutje: open het portaal, controleer je gegevens en maak je beschikbaarheid definitief.</p>\n[knop href=\"{{portaal_url}}beschikbaarheid.php\"]Beschikbaarheid definitief maken[/knop]",
                'tekst_kort' => "Hoi {{voornaam}}, je beschikbaarheid staat nog op voorlopig. Maak hem vóór {{deadline_datum}} definitief: {{portaal_url}}beschikbaarheid.php",
                'samenvatting' => 'Maak je beschikbaarheid vóór {{deadline_datum}} definitief.',
            ],
        ]),

        // ── Bestuur ───────────────────────────────────────────────────
        'bestuur_nieuwe_aanmelding' => array_merge($std, [
            'naam' => 'Nieuwe aanmelding', 'categorie' => 'bestuur', 'ontvanger' => 'bestuur',
            'kanaal_standaard' => 'email', 'kort_toegestaan' => false, 'dedup_min' => 0, 'body_loggen' => false,
            'beschrijving' => 'Naar het bestuur zodra iemand zich aanmeldt.',
            'vars' => ['terugkerend_html', 'beheer_url'],
            'seed' => [
                'onderwerp' => 'Nieuwe aanmelding: {{volledige_naam}}',
                'hero_titel' => 'Nieuwe aanmelding', 'hero_subtitel' => '{{seizoen_naam}}',
                'body_html' => "<p><strong>{{volledige_naam}}</strong><br>{{sollicitant_email}} · {{sollicitant_telefoon}}</p>\n{{terugkerend_html}}\n[knop href=\"{{beheer_url}}\"]Bekijk in het beheer[/knop]",
                'tekst_kort' => '', 'samenvatting' => '',
            ],
        ]),
        'bestuur_blk_ingediend' => array_merge($std, [
            'naam' => 'Formulier "Beter leren kennen" ingeleverd', 'categorie' => 'bestuur', 'ontvanger' => 'bestuur',
            'kanaal_standaard' => 'uit', 'kort_toegestaan' => false, 'dedup_min' => 0, 'body_loggen' => false,
            'beschrijving' => 'Naar het bestuur als een sollicitant het formulier inlevert (= aangemeld voor de selectiedag).',
            'vars' => ['beheer_url'],
            'seed' => [
                'onderwerp' => 'Formulier ingeleverd: {{volledige_naam}}',
                'hero_titel' => 'Formulier ingeleverd', 'hero_subtitel' => '{{seizoen_naam}}',
                'body_html' => "<p><strong>{{volledige_naam}}</strong> heeft het \"Beter leren kennen\"-formulier ingeleverd en is daarmee aangemeld voor de selectiedag.</p>\n[knop href=\"{{beheer_url}}\"]Bekijk in het beheer[/knop]",
                'tekst_kort' => '', 'samenvatting' => '',
            ],
        ]),
        'bestuur_beschikbaarheid_definitief' => array_merge($std, [
            'naam' => 'Beschikbaarheid definitief gemaakt', 'categorie' => 'bestuur', 'ontvanger' => 'bestuur',
            'kanaal_standaard' => 'uit', 'kort_toegestaan' => false, 'dedup_min' => 0, 'body_loggen' => false,
            'beschrijving' => 'Naar het bestuur als een sollicitant de beschikbaarheid definitief maakt.',
            'vars' => ['beheer_url'],
            'seed' => [
                'onderwerp' => 'Beschikbaarheid definitief: {{volledige_naam}}',
                'hero_titel' => 'Beschikbaarheid definitief', 'hero_subtitel' => '{{seizoen_naam}}',
                'body_html' => "<p><strong>{{volledige_naam}}</strong> heeft de beschikbaarheid definitief gemaakt.</p>\n[knop href=\"{{beheer_url}}\"]Bekijk in het beheer[/knop]",
                'tekst_kort' => '', 'samenvatting' => '',
            ],
        ]),
        'bestuur_verzoek_minder_dagen' => array_merge($std, [
            'naam' => 'Nieuw verzoek minder dagen werken', 'categorie' => 'bestuur', 'ontvanger' => 'bestuur',
            'kanaal_standaard' => 'email', 'kort_toegestaan' => false, 'dedup_min' => 0, 'body_loggen' => false,
            'beschrijving' => 'Naar het bestuur als een sollicitant een verzoek indient om minder dagen te werken.',
            'vars' => ['verzoek_weken', 'verzoek_dagen', 'verzoek_toelichting', 'beheer_url'],
            'seed' => [
                'onderwerp' => 'Verzoek minder dagen: {{volledige_naam}}',
                'hero_titel' => 'Verzoek te beoordelen', 'hero_subtitel' => '{{verzoek_weken}}',
                'body_html' => "<p><strong>{{volledige_naam}}</strong> vraagt om in <strong>{{verzoek_weken}}</strong> maximaal <strong>{{verzoek_dagen}} dagen</strong> te werken.</p>\n<p>Toelichting: {{verzoek_toelichting}}</p>\n[knop href=\"{{beheer_url}}\"]Beoordelen in het beheer[/knop]",
                'tekst_kort' => '', 'samenvatting' => '',
            ],
        ]),
        'bestuur_zelf_verwijderd' => array_merge($std, [
            'naam' => 'Sollicitant heeft zich teruggetrokken', 'categorie' => 'bestuur', 'ontvanger' => 'bestuur',
            'kanaal_standaard' => 'email', 'kort_toegestaan' => false, 'dedup_min' => 0, 'body_loggen' => false,
            'beschrijving' => 'Naar het bestuur als een sollicitant zelf de gegevens laat verwijderen (AVG).',
            'seed' => [
                'onderwerp' => 'Teruggetrokken: {{volledige_naam}}',
                'hero_titel' => 'Sollicitant teruggetrokken', 'hero_subtitel' => '{{seizoen_naam}}',
                'body_html' => "<p><strong>{{volledige_naam}}</strong> heeft via het portaal gevraagd de gegevens te verwijderen en is daarmee uit de procedure.</p>",
                'tekst_kort' => '', 'samenvatting' => '',
            ],
        ]),
        'bestuur_ondertekening_afgerond' => array_merge($std, [
            'naam' => 'Document ondertekend', 'categorie' => 'bestuur', 'ontvanger' => 'bestuur',
            'kanaal_standaard' => 'uit', 'kort_toegestaan' => false, 'dedup_min' => 0, 'body_loggen' => false,
            'beschrijving' => 'Naar het bestuur als een document (intake, gezondheidsverklaring) is ondertekend.',
            'vars' => ['document_naam', 'ontvanger_naam', 'beheer_url'],
            'seed' => [
                'onderwerp' => 'Ondertekend: {{document_naam}} voor {{volledige_naam}}',
                'hero_titel' => 'Document ondertekend', 'hero_subtitel' => '{{document_naam}}',
                'body_html' => "<p><strong>{{document_naam}}</strong> voor {{volledige_naam}} is ondertekend door {{ontvanger_naam}}.</p>\n[knop href=\"{{beheer_url}}\"]Bekijk in het beheer[/knop]",
                'tekst_kort' => '', 'samenvatting' => '',
            ],
        ]),

        // ── Systeem ───────────────────────────────────────────────────
        'portaal_code' => array_merge($std, [
            'naam' => 'Portaal-inlogcode', 'categorie' => 'systeem', 'ontvanger' => 'direct',
            'kanaal_standaard' => 'email', 'dedup_min' => 0, 'body_loggen' => false,
            'beschrijving' => 'De eenmalige inlogcode voor het portaal. De inhoud wordt nooit gelogd.',
            'vars' => ['otp_code'],
            'seed' => [
                'onderwerp' => 'Jouw inlogcode voor het Paal 13 portaal',
                'hero_titel' => 'Inlogcode', 'hero_subtitel' => 'Geldig 15 minuten',
                'body_html' => "<p>Je hebt een inlogcode aangevraagd voor het sollicitantenportaal van Reddingsbrigade Paal 13.</p>\n[code]{{otp_code}}[/code]\n<p>Deze code is <strong>15 minuten</strong> geldig. Vul hem in op de inlogpagina.</p>\n[info]Heb jij deze code niet aangevraagd? Dan kun je dit bericht negeren.[/info]",
                'tekst_kort' => 'Je inlogcode voor het Paal 13 portaal is: {{otp_code}} (geldig 15 minuten).',
                'samenvatting' => '',
            ],
        ]),

        // ── Handmatig en gepland ──────────────────────────────────────
        'gepland' => array_merge($std, [
            'naam' => 'Gepland bericht', 'categorie' => 'gepland', 'kanaal_standaard' => 'email', 'dedup_min' => 0,
            'beschrijving' => 'Een bericht uit een geplande regel ("X dagen na gebeurtenis Y"); inhoud komt uit het gekozen eigen sjabloon.',
            'vars' => ['onderwerp', 'body_html', 'tekst_kort'],
            'seed' => [
                'onderwerp' => '{{onderwerp}}', 'hero_titel' => '{{onderwerp}}', 'hero_subtitel' => '',
                'body_html' => '{{body_html}}', 'tekst_kort' => '{{tekst_kort}}', 'samenvatting' => '',
            ],
        ]),
        'handmatig' => array_merge($std, [
            'naam' => 'Handmatig bericht', 'categorie' => 'handmatig', 'kanaal_standaard' => 'email', 'dedup_min' => 0,
            'beschrijving' => 'Een bericht dat het bestuur zelf schrijft en verstuurt (onderwerp en inhoud per verzending).',
            'vars' => ['onderwerp', 'body_html', 'tekst_kort'],
            'seed' => [
                'onderwerp' => '{{onderwerp}}', 'hero_titel' => '{{onderwerp}}', 'hero_subtitel' => '',
                'body_html' => '{{body_html}}', 'tekst_kort' => '{{tekst_kort}}', 'samenvatting' => '',
            ],
        ]),
    ];
    return $reg;
}

/** Definitie van één type, of null als het type niet bestaat. */
function melding_definitie(string $type): ?array {
    return meldingen_register()[$type] ?? null;
}

/** Typen per categorie (voor het beheer). */
function meldingen_per_categorie(): array {
    $uit = [];
    foreach (meldingen_register() as $slug => $def) $uit[$def['categorie']][$slug] = $def;
    return $uit;
}

/**
 * De ingestelde kanaalmodus voor een type: 'email' | 'kort' | 'beide' | 'uit'.
 * Leest `notif.<type>` en begrijpt ook de oude CSV-vorm ('email,sms,wa').
 * Typen die geen kort kanaal mogen (ouder/bestuur) worden altijd 'email' (of 'uit').
 */
function melding_kanaalmodus(string $type): string {
    $def = melding_definitie($type);
    if (!$def) return 'email';
    $ruw = function_exists('instelling') ? trim(instelling('notif.' . $type, '')) : '';
    $modus = $def['kanaal_standaard'];
    if ($ruw !== '') {
        if (in_array($ruw, ['email', 'kort', 'beide', 'uit'], true)) {
            $modus = $ruw;
        } else {
            // Oude CSV-vorm
            $delen = array_filter(array_map('trim', explode(',', $ruw)));
            $mail  = in_array('email', $delen, true);
            $kort  = in_array('sms', $delen, true) || in_array('wa', $delen, true);
            $modus = $mail && $kort ? 'beide' : ($kort ? 'kort' : ($mail ? 'email' : 'uit'));
        }
    }
    if (!$def['kort_toegestaan'] && $modus !== 'uit') $modus = 'email';
    return $modus;
}

/**
 * Compat: de oude sleutels uit notificatie_typen() vertaald naar de nieuwe typen.
 * Wordt gebruikt bij de migratie van instellingen en oude aanroepen.
 */
function melding_type_alias(string $oud): string {
    $map = [
        'aanmeldbevestiging'        => 'aanmelding_bevestiging',
        'welkomstmail'              => 'aanmelding_bevestiging',
        'welkom_aangenomen'         => 'aangenomen',
        'otp_portaal'               => 'portaal_code',
        'herinnering_selectie'      => 'selectiedag_nadert',
        'reminder_sd_aankomst'      => 'selectiedag_nadert',
        'reminder_sd_morgen'        => 'selectiedag_morgen',
        'reminder_ondertekening'    => 'ondertekening_herinnering',
        'reminder_beschikbaarheid'  => 'beschikbaarheid_voorlopig',
        'reminder_blk'              => 'herinnering_taken_open',
        'ondertekening_ouder'       => 'ondertekening_verzoek_ouder',
        'status_update'             => 'handmatig',
        'algemeen'                  => 'handmatig',
    ];
    return $map[$oud] ?? $oud;
}

}

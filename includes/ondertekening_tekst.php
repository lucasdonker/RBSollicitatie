<?php
/**
 * Paal 13 — Ondertekeningen: één bron voor de naam en de tekst van elk documenttype.
 *
 * Gebruikt door portaal/ondertekenen.php (het scherm), de meldingen (documentnaam in mails en
 * herinneringen), de takenlijst en de PDF-bestandsnaam. Geen HTML hier; alleen data en teksten.
 *
 * Typen: gezondheid_18plus, gezondheid_ouder (ouder tekent), intake, intake_ouder (ouder tekent),
 * loonheffing (altijd zelf), lidmaatschap (18+ zelf; onder 18 hetzelfde type met ondertekenaar_rol 'ouder').
 */

if (!function_exists('ondertekening_label')) {

/** Leesbare naam van een documenttype (mails, herinneringen, beheer). */
function ondertekening_label(string $type): string {
    return match ($type) {
        'gezondheid_ouder'  => 'Gezondheidsverklaring (t/m 17)',
        'gezondheid_18plus' => 'Gezondheidsverklaring 18+',
        'intake'            => 'Intakeformulier selectiedag',
        'intake_ouder'      => 'Intakeformulier selectiedag (ouder)',
        'loonheffing'       => 'Opgaaf gegevens voor de loonheffingen',
        'lidmaatschap'      => 'Inschrijving lidmaatschap vereniging',
        default             => 'Formulier',
    };
}

/**
 * Typen van het aangenomen-pakket met vertrouwelijke gegevens (BSN, IBAN → kluis verplicht).
 * Loonheffing tekent de werknemer altijd zelf, ook onder 18. Lidmaatschap onder 18 tekent de
 * ouder/verzorger (zie ondertekening_ouder_tekent()).
 */
function ondertekening_is_werknemer_type(string $type): bool {
    return in_array($type, ['loonheffing', 'lidmaatschap'], true);
}

/**
 * Tekent de ouder/verzorger dit document, gegeven de leeftijd van de sollicitant?
 * Oudertypen altijd; lidmaatschap onder 18 (wettelijk vertegenwoordiger: lidmaatschap met contributieplicht
 * en SEPA-machtiging gaat een minderjarige niet zelf aan, art. 1:234 BW). Grens 18 = de ondertekeningsgrens,
 * bewust los van de aanmeld-/AVG-grens van 16.
 */
function ondertekening_ouder_tekent(string $type, ?int $leeftijd): bool {
    if (ondertekening_is_ouder_type($type)) return true;
    return $type === 'lidmaatschap' && $leeftijd !== null && $leeftijd < 18;
}

/** Introtekst bovenaan het ondertekenscherm van de ouder/verzorger. */
function ondertekening_ouder_intro(string $type, string $voornaam, ?int $leeftijd): string {
    $kind = $voornaam . ($leeftijd !== null ? ' (' . $leeftijd . ')' : '');
    if ($type === 'lidmaatschap') {
        return 'Uw kind ' . $kind . ' is aangenomen als strandwacht bij Reddingsbrigade Paal 13 en wordt lid van de vereniging. '
             . 'Omdat ' . $voornaam . ' jonger is dan 18, schrijft u als ouder/verzorger ' . $voornaam . ' in en tekent u namens ' . $voornaam . '. '
             . 'Kiest u voor automatische incasso, dan geeft u ook de machtiging als rekeninghouder.';
    }
    return 'Uw kind ' . $kind . ' meldt zich aan als strandwacht bij Reddingsbrigade Paal 13. Voor deelname is uw handtekening nodig.';
}

/** Tekst bij het verplichte akkoordvinkje naast de handtekening. */
function ondertekening_akkoord_tekst(string $type, bool $ouder, string $voornaam = ''): string {
    if ($ouder && $type === 'lidmaatschap') {
        return 'Ik heb het document gelezen en geef als ouder/verzorger (wettelijk vertegenwoordiger) toestemming voor het lidmaatschap van '
             . ($voornaam !== '' ? $voornaam : 'mijn kind') . '.';
    }
    return $ouder
        ? 'Ik heb het document gelezen en geef toestemming voor deelname.'
        : 'Ik heb het document gelezen en verklaar naar waarheid te hebben ondertekend.';
}

/** Uitleg onder "Ondertekenaar" (desktop) als de sollicitant zelf tekent. */
function ondertekening_zelf_uitleg(string $type): string {
    return match ($type) {
        'loonheffing'  => 'Dit formulier teken je altijd zelf, ook als je jonger bent dan 18: het is een verklaring van jou als werknemer.',
        'lidmaatschap' => 'Je bent 18 of ouder, dus je schrijft je zelf in. Onder 18 tekent je ouder of verzorger via een eigen link.',
        default        => '18 of ouder, dus je tekent zelf. Onder 18 vervalt dit scherm en tekent alleen de ouder via een eigen link.',
    };
}

/** Korte titel voor in de kop van het ondertekenscherm en de bestandsnaam ("Gezondheidsverklaring"). */
function ondertekening_titel_kort(string $type): string {
    return match ($type) {
        'gezondheid_ouder', 'gezondheid_18plus' => 'Gezondheidsverklaring',
        'intake', 'intake_ouder'                => 'Intakeformulier',
        'loonheffing'                           => 'Loonheffingsformulier',
        'lidmaatschap'                          => 'Lid worden van de vereniging',
        default                                 => 'Document',
    };
}

/**
 * Titel en subregel van de taakrij in het portaal (open en afgerond), één bron voor start en Taken-tab.
 * Retour: ['titel' => ..., 'sub' => ...]; $klaar_op = 'Y-m-d H:i:s' van de ondertekening.
 */
function ondertekening_taak_titel(string $type, bool $klaar, ?string $klaar_op = null): array {
    $datum = $klaar && $klaar_op ? datum_nl(substr($klaar_op, 0, 10)) : '';
    return match ($type) {
        'gezondheid_18plus' => ['titel' => $klaar ? 'Gezondheidsverklaring' : 'Gezondheidsverklaring ondertekenen', 'sub' => $klaar ? 'Ondertekend ' . $datum : 'Nodig voor de selectiedag'],
        'intake'            => ['titel' => $klaar ? 'Intakeformulier' : 'Intakeformulier invullen', 'sub' => $klaar ? 'Ondertekend ' . $datum : 'Nodig voor de selectiedag'],
        'loonheffing'       => ['titel' => $klaar ? 'Loonheffingsformulier' : 'Loonheffingsformulier invullen', 'sub' => $klaar ? 'Ondertekend ' . $datum : 'Nodig voor je salaris · ± 5 minuten'],
        'lidmaatschap'      => ['titel' => $klaar ? 'Lid van de vereniging' : 'Lid worden van de vereniging', 'sub' => $klaar ? 'Ingeschreven ' . $datum : 'Inschrijven als lid · ± 5 minuten'],
        default             => ['titel' => $klaar ? 'Document' : 'Document ondertekenen', 'sub' => $klaar ? 'Ondertekend ' . $datum : ''],
    };
}

/** Tekent een ouder/verzorger dit type (in plaats van de sollicitant zelf)? */
function ondertekening_is_ouder_type(string $type): bool {
    return in_array($type, ['gezondheid_ouder', 'intake_ouder'], true);
}

/** Is dit een gezondheidsverklaring (anders: intakeformulier)? */
function ondertekening_is_gezondheid(string $type): bool {
    return in_array($type, ['gezondheid_ouder', 'gezondheid_18plus'], true);
}

/** Bestandsnaam voor de PDF-kopie, bijv. "Gezondheidsverklaring_Paal13.pdf". */
function ondertekening_pdf_bestandsnaam(string $type): string {
    return preg_replace('/[^A-Za-z0-9]+/', '_', ondertekening_titel_kort($type)) . '_Paal13.pdf';
}

/**
 * De documenttekst zoals die in het scherm staat: ['titel' => ..., 'alineas' => [...]].
 * Voor de oudertypen in de u-vorm ("uw kind"), voor de sollicitant zelf in de je-vorm.
 * Dit is de tekst waar de handtekening onder komt; de vragen (aandoeningen, verklaringen) staan
 * apart in het formulier en worden mee opgeslagen in formulier_data.
 */
function ondertekening_document(string $type, string $voornaam = '', ?bool $ouder = null): array {
    $ouder = $ouder ?? ondertekening_is_ouder_type($type);
    $kind  = $voornaam !== '' ? $voornaam : 'uw kind';
    if ($type === 'loonheffing') {
        return [
            'titel'   => 'Opgaaf gegevens voor de loonheffingen',
            'alineas' => [
                'Als strandwacht krijg je salaris van Reddingsbrigade Paal 13. De Belastingdienst verplicht elke werkgever om vóór de eerste werkdag je gegevens vast te leggen op het formulier "Model Opgaaf gegevens voor de loonheffingen". Dit formulier is dat officiële model; je vult het hier digitaal in en ondertekent het.',
                'Je geeft je naam, burgerservicenummer (BSN), adres en geboortedatum door, en je kiest of Paal 13 de loonheffingskorting mag toepassen. Die korting mag maar bij één werkgever of uitkeringsinstantie tegelijk. Heb je nog een andere baan of uitkering waar hij al wordt toegepast, kies dan "Nee".',
                'Geef je je gegevens niet of niet juist door, dan moet de werkgever het hoge tarief van 52% loonheffing inhouden en mag de loonheffingskorting niet worden toegepast. Verandert er later iets in je gegevens, geef dat dan schriftelijk aan de penningmeester door.',
                'Je BSN wordt versleuteld bewaard en is alleen zichtbaar voor de penningmeester (loonadministratie). Het ondertekende formulier wordt digitaal verzegeld en wettelijk bewaard tot vijf jaar na het einde van het jaar waarin je dienstverband eindigt.',
            ],
        ];
    }
    if ($type === 'lidmaatschap' && $ouder) {
        return [
            'titel'   => 'Inschrijving als lid van Reddingsbrigade Paal 13',
            'alineas' => [
                'Als strandwacht wordt ' . $kind . ' lid van de vereniging Reddingsbrigade Paal 13. Omdat ' . $kind . ' jonger is dan 18, kan ' . $kind . ' dat niet zelf aangaan: als ouder/verzorger (wettelijk vertegenwoordiger) geeft u toestemming voor het lidmaatschap en tekent u namens ' . $kind . '. Daarmee gaat u ook de verplichtingen van het lidmaatschap aan, zoals de contributie.',
                'De gegevens van ' . $kind . ' zijn grotendeels al ingevuld vanuit de sollicitatie; controleer ze en vul aan wat ontbreekt. De ledenservice verwerkt de inschrijving.',
                'De contributie, zwemgelden en eventuele opleidingskosten worden betaald per overschrijving of via automatische incasso. Kiest u voor incasso, dan machtigt u als rekeninghouder de vereniging om die bedragen jaarlijks van de rekening af te schrijven; u ontvangt minimaal 14 dagen van tevoren een factuur en kunt een afschrijving binnen 56 dagen laten terugboeken.',
            ],
        ];
    }
    if ($type === 'lidmaatschap') {
        return [
            'titel'   => 'Inschrijving als lid van Reddingsbrigade Paal 13',
            'alineas' => [
                'Als strandwacht word je lid van de vereniging Reddingsbrigade Paal 13. Met dit formulier schrijf je je in; de ledenservice verwerkt je inschrijving en je krijgt daarvan bericht.',
                'Je gegevens zijn grotendeels al ingevuld vanuit je sollicitatie; controleer ze en vul aan wat ontbreekt. (Ben je jonger dan 18, dan schrijft je ouder of verzorger je in via een eigen link.)',
                'De contributie, zwemgelden en eventuele opleidingskosten betaal je per overschrijving of via automatische incasso. Kies je voor incasso, dan machtig je de vereniging om die bedragen jaarlijks van je rekening af te schrijven; je ontvangt minimaal 14 dagen van tevoren een factuur en kunt een afschrijving binnen 56 dagen laten terugboeken.',
            ],
        ];
    }
    if (ondertekening_is_gezondheid($type)) {
        return [
            'titel'   => 'Gezondheidsverklaring strandwacht',
            'alineas' => [
                $ouder
                    ? 'Ondergetekende verklaart dat bovengenoemde minderjarige mag deelnemen aan de opleidingen en het examen voor zwemmend en/of varend redden en/of wedstrijden waarbij de bepalingen van het geldige Examen Reglement worden gehandhaafd.'
                    : 'Ondergetekende verklaart deel te mogen nemen aan de opleidingen en het examen voor zwemmend en/of varend redden en/of wedstrijden waarbij de bepalingen van het geldige Examen Reglement worden gehandhaafd.',
                'Ondergetekende verklaart bekend te zijn met het feit dat het bovenstaande verhoogde eisen stelt aan de fysieke toestand van de deelnemer en verklaart kennis te hebben genomen van de factoren die in het formulier zijn opgenomen.',
                'Ondergetekende verklaart jegens de organisatie die de opleiding en het examen verzorgt, alsmede de Koninklijke Nederlandse Bond tot het Redden van Drenkelingen, het risico van deelname te aanvaarden en beide instanties uitdrukkelijk van iedere aansprakelijkheid te vrijwaren, voor zover deze aansprakelijkheid betrekking heeft op gebeurtenissen die voortvloeien uit de vermelde gezondheidsrisico\'s.',
                'Vraag bij enige twijfel advies aan de huisarts of behandelend specialist. Doe dit ook na iedere belangrijke ziekte, in het bijzonder infectieziekten en operaties.',
                'Deze gezondheidsverklaring is één jaar geldig vanaf de datum van ondertekening. Zonder ondertekening is deze verklaring niet geldig.',
            ],
        ];
    }
    return [
        'titel'   => 'Intakeformulier selectiedag',
        'alineas' => $ouder
            ? [
                'Tijdens de selectiedag doet ' . $kind . ' mee aan sportieve onderdelen in en om het water. Om de veiligheid te kunnen waarborgen, vragen wij of er medische of fysieke aandachtspunten zijn waarmee we rekening moeten houden. U bent niet verplicht iets te delen; wat u invult wordt vertrouwelijk behandeld.',
                'Tijdens de dag worden foto\'s gemaakt voor interne evaluatie, promotie en sociale media. We gaan hier zorgvuldig mee om: niemand wordt ongepast of negatief in beeld gebracht, bijvoorbeeld in het zwembad. Toestemming hiervoor is optioneel.',
                'Deelname is op eigen risico. De organisatie is niet aansprakelijk voor letsel dat voortkomt uit verzwegen of onbekende omstandigheden.',
            ]
            : [
                'Tijdens de selectiedag doe je mee aan sportieve onderdelen in en om het water. Om jouw veiligheid te kunnen waarborgen, vragen wij of er medische of fysieke aandachtspunten zijn waarmee we rekening moeten houden. Je bent niet verplicht iets te delen; wat je invult wordt vertrouwelijk behandeld.',
                'Tijdens de dag worden foto\'s gemaakt voor interne evaluatie, promotie en sociale media. We gaan hier zorgvuldig mee om: niemand wordt ongepast of negatief in beeld gebracht, bijvoorbeeld in het zwembad. Toestemming hiervoor is optioneel.',
                'Deelname is op eigen risico. De organisatie is niet aansprakelijk voor letsel dat voortkomt uit verzwegen of onbekende omstandigheden.',
            ],
    ];
}

} // function_exists

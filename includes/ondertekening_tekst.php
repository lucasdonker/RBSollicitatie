<?php
/**
 * Paal 13 — Ondertekeningen: één bron voor de naam en de tekst van elk documenttype.
 *
 * Gebruikt door portaal/ondertekenen.php (het scherm), de meldingen (documentnaam in mails en
 * herinneringen), de takenlijst en de PDF-bestandsnaam. Geen HTML hier; alleen data en teksten.
 *
 * Typen: gezondheid_18plus, gezondheid_ouder (ouder tekent), intake, intake_ouder (ouder tekent).
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

/** Typen waarbij de sollicitant altijd zelf tekent (ook onder 18): geen ouderflow. */
function ondertekening_is_werknemer_type(string $type): bool {
    return in_array($type, ['loonheffing', 'lidmaatschap'], true);
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
function ondertekening_document(string $type, string $voornaam = ''): array {
    $ouder = ondertekening_is_ouder_type($type);
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
    if ($type === 'lidmaatschap') {
        return [
            'titel'   => 'Inschrijving als lid van Reddingsbrigade Paal 13',
            'alineas' => [
                'Als strandwacht word je lid van de vereniging Reddingsbrigade Paal 13. Met dit formulier schrijf je je in; de ledenservice verwerkt je inschrijving en je krijgt daarvan bericht.',
                'Je gegevens zijn grotendeels al ingevuld vanuit je sollicitatie; controleer ze en vul aan wat ontbreekt. Ben je jonger dan 18, dan vragen we ook de gegevens van je ouder of verzorger.',
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

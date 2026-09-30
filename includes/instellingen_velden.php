<?php
/**
 * Paal 13 — Instellingen als lijst (2.144.0). GEEN HTML.
 *
 * Eén definitie per tabblad: secties met velden. Uit dezelfde lijst komen
 *   - het formulier (admin/_inst_formulier.php + admin/_inst_veld.php), en
 *   - de whitelist van wat dat tabblad mag opslaan (instellingen_formulier_sleutels()).
 * Een veld toevoegen is dus één regel hier; het kan niet meer gebeuren dat een veld wel op het
 * scherm staat maar stil niet wordt opgeslagen.
 *
 * Tabbladen met eigen gedrag (uploads, geheimen, tarieven per jaar, posten, criteria, modules) staan
 * nog als eigen code in admin/instellingen.php; die gaan er tab voor tab bij.
 *
 * Tab:     ['titel', 'intro'?, 'secties' => [...]]
 * Sectie:  ['titel', 'uitleg'?, 'als'?: callable, 'velden' => [...]]  of
 *          ['titel', 'partial' => naam (admin/inst/_<naam>.php), 'sleutels' => [...]]  voor maatwerk
 * Veld:    sleutel, label, type (text|email|number|textarea|select|checkbox), standaard, hint?, tip?,
 *          opties? (array of callable), min?/max?/step?, rijen?, placeholder? (string of callable),
 *          breed? (hele regel), toon_als? ([sleutel, waarde]: alleen zichtbaar bij die waarde)
 */

if (!function_exists('instellingen_velden')) {

function instellingen_velden(): array {
    return [
        'fase_advies' => [
            'titel'   => 'Seizoensfase & advies',
            'secties' => [
                ['titel' => 'Seizoen en selectie', 'velden' => [
                    ['sleutel' => 'seizoen.fase', 'label' => 'Seizoensfase', 'type' => 'select', 'standaard' => '',
                     'opties' => fn() => ['' => 'Automatisch (uit de datums van het seizoen)'] + (function_exists('bh_seizoen_fases') ? bh_seizoen_fases() : []),
                     'hint' => 'Bepaalt de seizoensbalk en de hoofdtaak op Vandaag, en wat de sollicitantenlijst als afwijkend aanmerkt. Kies alleen een vaste fase als de datums niet kloppen, bijvoorbeeld als de selectiedag verschoven is of de belronde uitloopt. Zet hem daarna terug op automatisch.'],
                    ['sleutel' => 'selectie.drempel', 'label' => 'Advies-drempel selectiedag', 'type' => 'number', 'standaard' => '3.5',
                     'min' => 1, 'max' => 5, 'step' => 0.1,
                     'hint' => 'Gemiddelde score (1 tot 5) vanaf waar het dossier "Door" adviseert. Tot een halve punt eronder is het "Twijfel", daaronder "Niet". Het is een advies; het besluit neem je zelf.'],
                ]],
                ['titel' => 'Na het gesprek op het trainingsweekend',
                 'uitleg' => 'In het gesprek schrijft het bestuur op wat er besproken is; dat gaat mee in de mail als {{gesprek_bericht}}. Blijft dat veld leeg, dan komt deze standaardtekst erin. Leeg laten = de ingebouwde tekst (staat grijs in het veld).',
                 'als' => fn() => !function_exists('module_aan') || module_aan('trainingsweekend'),
                 'velden' => [
                    ['sleutel' => 'tw.bericht_aangenomen', 'label' => 'Standaardtekst bij aangenomen', 'type' => 'textarea', 'standaard' => '', 'breed' => true, 'rijen' => 3, 'max' => 2000,
                     'placeholder' => fn() => function_exists('tw_standaard_bericht') ? tw_standaard_bericht('aannemen', false) : ''],
                    ['sleutel' => 'tw.bericht_afgewezen', 'label' => 'Standaardtekst bij afgewezen', 'type' => 'textarea', 'standaard' => '', 'breed' => true, 'rijen' => 3, 'max' => 2000,
                     'placeholder' => fn() => function_exists('tw_standaard_bericht') ? tw_standaard_bericht('afwijzen', false) : ''],
                    ['sleutel' => 'tw.bericht_afgewezen_niet_gekomen', 'label' => 'Standaardbericht bij afwijzen: niet op het trainingsweekend geweest', 'type' => 'textarea', 'standaard' => '', 'breed' => true, 'rijen' => 3, 'max' => 2000,
                     'placeholder' => fn() => function_exists('tw_standaard_bericht') ? tw_standaard_bericht('afwijzen', true) : ''],
                ]],
            ],
        ],

        'uitnodiging' => [
            'titel'   => 'Uitnodigen & herinneren',
            'secties' => [
                ['titel' => 'Automatisch uitnodigen voor de selectiedag',
                 'uitleg' => 'Sollicitanten binnen de leeftijdsgrenzen worden automatisch uitgenodigd, of je krijgt ze als voorstel te zien.',
                 'velden' => [
                    ['sleutel' => 'invite.modus', 'label' => 'Modus', 'type' => 'select', 'standaard' => 'uit', 'breed' => true,
                     'opties' => ['uit' => 'Uit — geen automatisering', 'suggestie' => 'Suggestie — markeer in de lijst, admin handelt', 'auto' => 'Automatisch — nodig uit na ingestelde vertraging']],
                    ['sleutel' => 'invite.min_leeftijd', 'label' => 'Minimumleeftijd', 'type' => 'number', 'standaard' => '16', 'min' => 14, 'max' => 40],
                    ['sleutel' => 'invite.max_leeftijd', 'label' => 'Maximumleeftijd', 'type' => 'number', 'standaard' => '30', 'min' => 14, 'max' => 65],
                    ['sleutel' => 'invite.delay_uur', 'label' => 'Vertraging in uren', 'type' => 'number', 'standaard' => '24', 'min' => 0, 'max' => 168,
                     'toon_als' => ['invite.modus', 'auto'], 'hint' => '0 = direct uitnodigen. 24 = na 24 uur automatisch uitnodigen.'],
                ]],
                ['titel' => 'Automatisering bij fase-wijziging', 'uitleg' => 'Wat gebeurt er automatisch als een sollicitant van fase wisselt.', 'velden' => [
                    ['sleutel' => 'auto.ondertekening_sd', 'label' => 'Ondertekeningen bij uitnodiging selectiedag', 'type' => 'checkbox', 'standaard' => '1',
                     'hint' => '18+: gezondheid + intake als portaal-taak. Minderjarig: ouder krijgt automatisch mail zodra sollicitant ouder-gegevens invult.'],
                    ['sleutel' => 'auto.kennismakingspakket', 'label' => 'Kennismakingspakket bij aangenomen', 'type' => 'checkbox', 'standaard' => '1',
                     'hint' => 'Welkomstmail + portaal-taken (lidmaatschap, loonheffing, huisregels).'],
                ]],
                ['titel' => 'Herinneringen', 'partial' => 'herinneringen', 'sleutels' => instellingen_herinnering_sleutels()],
                ['titel' => 'Grenzen', 'uitleg' => 'Zodat niemand te veel berichten krijgt en de mailserver niets weigert.', 'velden' => [
                    ['sleutel' => 'herinnering.rust_dagen', 'label' => 'Rustperiode (dagen)', 'type' => 'number', 'standaard' => '3', 'min' => 0, 'max' => 30,
                     'hint' => 'Maximaal één "zachte" herinnering per sollicitant per zoveel dagen.'],
                    ['sleutel' => 'melding.plafond_dag', 'label' => 'Hoe vaak hetzelfde bericht per dag', 'type' => 'number', 'standaard' => '8', 'min' => 1, 'max' => 50,
                     'hint' => 'Vaker hetzelfde automatische bericht naar dezelfde ontvanger in 24 uur wordt overgeslagen (zie het logboek). Andere berichtsoorten, bestuursmeldingen en wat je zelf in gang zet (fase omzetten, bulk, handmatig) tellen niet mee.'],
                    ['sleutel' => 'bijlage.mail_max_mb', 'label' => 'Bijlage als e-mailbijlage tot (MB)', 'type' => 'number', 'standaard' => '15', 'min' => 1, 'max' => 25,
                     'hint' => 'Grotere bestanden gaan als downloadknop in de mail (link 14 dagen geldig). Weigert de mailserver een bijlage toch, dan gaat de mail automatisch opnieuw met een downloadknop.'],
                    ['sleutel' => 'wa.max_per_uur', 'label' => 'WhatsApp per uur (max)', 'type' => 'number', 'standaard' => '30', 'min' => 1, 'max' => 500,
                     'hint' => 'Boven dit aantal valt de kern terug op SMS/e-mail.'],
                ]],
            ],
        ],

        'mail' => [
            'titel'   => 'Afzender & adressen',
            'secties' => [
                ['titel' => 'Waar berichten heen gaan', 'velden' => [
                    ['sleutel' => 'mail.bestuur_email', 'label' => 'Bestuur e-mail', 'type' => 'email', 'standaard' => 'bestuur@paal13.com',
                     'hint' => 'Ontvangt notificaties bij nieuwe aanmeldingen.'],
                    ['sleutel' => 'mail.contact_email', 'label' => 'Contact voor sollicitanten', 'type' => 'email', 'standaard' => 'sollicitatie@paal13.com',
                     'hint' => 'Staat in het portaal onder Contact en onderaan elke pagina; vragen uit het portaal komen hier binnen. Leeg = bestuursadres.'],
                    ['sleutel' => 'mail.penningmeester_email', 'label' => 'Penningmeester e-mail', 'type' => 'email', 'standaard' => '',
                     'hint' => 'Ontvangt de melding "loondossier compleet" (loonheffing, kopie ID, bankpas). Leeg = bestuursadres.'],
                    ['sleutel' => 'mail.ledenservice_email', 'label' => 'Ledenservice e-mail', 'type' => 'email', 'standaard' => '',
                     'hint' => 'Ontvangt de ondertekende inschrijving als lid of donateur (met pdf). Leeg = bestuursadres.'],
                    ['sleutel' => 'mail.fout_email', 'label' => 'Foutmeldingen sturen naar', 'type' => 'email', 'standaard' => 'website@paal13.com',
                     'hint' => 'Ontvangt automatisch een mail bij technische fouten (foutcode en details).'],
                ]],
                ['titel' => 'Loondossier', 'velden' => [
                    ['sleutel' => 'documenten.penningmeester_bijlagen', 'label' => 'Loondossier-stukken als bijlage meesturen in de mail aan de penningmeester',
                     'type' => 'checkbox', 'standaard' => '0', 'breed' => true,
                     'hint' => 'Standaard uit: de stukken (BSN, kopie identiteitsbewijs, bankpas) gaan dan alleen via de beveiligde inzage in het beheer. Aanzetten stuurt ze onversleuteld per e-mail; doe dat alleen als het mailpad vertrouwd is.'],
                ]],
            ],
        ],
    ];
}

/** De sleutels van de ingebouwde herinneringen (per soort aan, na, herhaal, max, en de info bij "morgen"). */
function instellingen_herinnering_sleutels(): array {
    $uit = [];
    if (function_exists('herinnering_regels')) {
        foreach (array_keys(herinnering_regels()) as $slug) {
            foreach (['aan', 'na', 'herhaal', 'max'] as $veld) $uit[] = 'herinnering.' . $slug . '.' . $veld;
        }
        $uit[] = 'herinnering.selectiedag_morgen.info';
    }
    return $uit;
}

/** Alle sleutels die dit tabblad volgens de lijst mag opslaan (leeg = tabblad staat niet in de lijst). */
function instellingen_velden_sleutels(string $tab): array {
    $def = instellingen_velden()[$tab] ?? null;
    if (!$def) return [];
    $uit = [];
    foreach ($def['secties'] as $s) {
        if (isset($s['sleutels'])) $uit = array_merge($uit, $s['sleutels']);
        foreach ($s['velden'] ?? [] as $v) $uit[] = $v['sleutel'];
    }
    return array_values(array_unique($uit));
}

} // function_exists

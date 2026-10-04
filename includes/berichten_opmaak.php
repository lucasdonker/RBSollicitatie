<?php
/**
 * Paal 13 — opmaak van de korte tekst (WhatsApp/sms) en de lijst met variabelen voor berichten.
 * Geen verzendlogica; gebruikt door admin/berichten.php, admin/_variabelen.php en de verzendkern
 * (includes/meldingen/verzend.php strip de opmaak voor sms).
 *
 * We schrijven in de notatie van WhatsApp zelf:
 *   *vet*   _cursief_   ~doorgehaald~   ```vaste breedte```   regels die met "- " beginnen = opsomming
 * WhatsApp toont dat opgemaakt; voor sms halen we de tekens weg (anders staan er sterretjes); voor
 * de e-mail die gaat als iemand geen telefoon heeft, wordt het gewone HTML.
 */

if (!function_exists('kort_opmaak_strip')) {

/** De opmaaktekens weghalen, voor sms: "*vet*" wordt "vet". */
function kort_opmaak_strip(string $t): string {
    $t = (string)preg_replace('/```(.+?)```/s', '$1', $t);
    foreach (['\*', '_', '~'] as $teken) {
        // Alleen een paar om een woord of zin heen, niet een losse * of een _ in een URL.
        $t = (string)preg_replace('/(?<![\w' . $teken . '])' . $teken . '(?=\S)(.+?)(?<=\S)' . $teken . '(?![\w' . $teken . '])/u', '$1', $t);
    }
    return $t;
}

/**
 * De korte tekst als eenvoudige e-mail-HTML: escapen, opmaak omzetten, links klikbaar, alinea's.
 * Veilig: eerst alles escapen, daarna pas onze eigen tags.
 */
function kort_naar_html(string $t): string {
    $e = htmlspecialchars(str_replace("\r\n", "\n", trim($t)), ENT_QUOTES, 'UTF-8');
    $e = (string)preg_replace('/```(.+?)```/s', '<code>$1</code>', $e);
    $map = ['\*' => 'strong', '_' => 'em', '~' => 's'];
    foreach ($map as $teken => $tag) {
        $e = (string)preg_replace('/(?<![\w' . $teken . '])' . $teken . '(?=\S)(.+?)(?<=\S)' . $teken . '(?![\w' . $teken . '])/u', "<$tag>\$1</$tag>", $e);
    }
    // Links (ook {{portaal_url}} blijft staan: de variabele wordt later ingevuld en mail_vervang escapet).
    $e = (string)preg_replace('#(?<!["=])\bhttps?://[^\s<]+#', '<a href="$0">$0</a>', $e);
    $uit = ''; $lijst = [];
    $alinea = function (array $regels) { return $regels ? '<p>' . implode('<br>', $regels) . "</p>\n" : ''; };
    $regels = [];
    foreach (explode("\n", $e) as $r) {
        if (preg_match('/^\s*[-•]\s+(.*)$/u', $r, $m)) { $uit .= $alinea($regels); $regels = []; $lijst[] = '<li>' . $m[1] . '</li>'; continue; }
        if ($lijst) { $uit .= '<ul>' . implode('', $lijst) . "</ul>\n"; $lijst = []; }
        if (trim($r) === '') { $uit .= $alinea($regels); $regels = []; continue; }
        $regels[] = $r;
    }
    if ($lijst) $uit .= '<ul>' . implode('', $lijst) . "</ul>\n";
    return $uit . $alinea($regels);
}

/**
 * Alle variabelen die in elk bericht werken, gegroepeerd en met een uitleg: groep => [naam => uitleg].
 * Eén lijst voor Berichten en Sjablonen; de waarden komen uit melding_vars().
 */
function melding_variabelen_lijst(): array {
    return [
        'Sollicitant' => [
            'voornaam' => 'Voornaam', 'achternaam' => 'Achternaam', 'volledige_naam' => 'Voor- en achternaam',
            'sollicitant_email' => 'E-mailadres', 'sollicitant_telefoon' => 'Telefoonnummer', 'ouder_naam' => 'Naam ouder/verzorger',
        ],
        'Seizoen' => ['seizoen_naam' => 'Naam van het seizoen'],
        'Selectiedag' => [
            'selectiedag_datum' => 'Datum', 'selectiedag_tijd' => 'Verzameltijd (" om 9:00")', 'selectiedag_eindtijd' => 'Eindtijd',
            'selectiedag_locatie' => 'Locatie', 'selectiedag_adres' => 'Adres', 'selectiedag_meenemen' => 'Wat meenemen',
        ],
        'Trainingsweekend' => [
            'tw_periode' => 'Periode ("3 – 5 mei")', 'tw_datum_start' => 'Begindatum', 'tw_datum_eind' => 'Einddatum',
            'tw_verzameltijd' => 'Verzameltijd', 'tw_eindtijd' => 'Eindtijd', 'tw_locatie' => 'Locatie', 'tw_adres' => 'Adres',
            'tw_meenemen' => 'Wat meenemen',
        ],
        'Links en contact' => [
            'portaal_url' => 'Link naar het portaal', 'aanmeld_url' => 'Link om je aan te melden',
            'contact_email' => 'Ons contactadres', 'bestuur_email' => 'Adres van het bestuur',
            'afmeld_url' => 'Link om je af te melden voor berichten', 'seintje_url' => 'Link voor een seintje volgend seizoen',
        ],
    ];
}

}

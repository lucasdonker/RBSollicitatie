<?php
/**
 * Paal 13 — seizoenbeheer: wat staat er klaar, en wat gebeurt er als je iets doet?
 *
 * Geen HTML. Twee dingen:
 *   1. bh_seizoen_stappen()  — het stappenplan als stand, niet als uitleg. Een statische
 *      lijst "1. aanmaken 2. datums 3. openen" lees je één keer; een lijst die zegt wat
 *      al af is, blijft nuttig. Dit is een jaarlijkse taak: niemand onthoudt de volgorde.
 *   2. bh_seizoen_gevolgen() — de tekst van de bevestiging. Die hoort hier en niet in
 *      het scherm, want de knop op de kaart en het tussenscherm zonder JavaScript
 *      moeten hetzelfde beloven.
 */

if (!function_exists('bh_seizoen_stappen')) {

/**
 * De vijf stappen van een seizoen, met per stap of hij af is.
 *
 * @return array [ ['sleutel','titel','tekst','af'=>bool,'nu'=>bool], … ]
 *               'nu' staat op de eerste stap die nog niet af is: dat is wat je moet doen.
 */
function bh_seizoen_stappen(?array $sz): array {
    $sz = $sz ?: [];
    $heeft = fn(string $k) => trim((string)($sz[$k] ?? '')) !== '';

    $stappen = [
        ['sleutel' => 'aanmaken', 'titel' => 'Seizoen aangemaakt',
         'tekst' => 'Een naam, bijvoorbeeld "Zomer 2027".',
         'af' => $heeft('naam')],
        ['sleutel' => 'datums', 'titel' => 'Datums ingevuld',
         'tekst' => 'Selectiedag, trainingsweekend en de werkperiode. De werkperiode bepaalt welke weken de sollicitant in het portaal ziet.',
         'af' => $heeft('datum_selectiedag') && $heeft('datum_tw_start') && $heeft('datum_werk_start')],
        ['sleutel' => 'openen', 'titel' => 'Seizoen geopend',
         'tekst' => 'Vanaf dat moment kan er worden aangemeld.',
         'af' => ($sz['status'] ?? '') === 'open'],
        // Deze twee zijn pas af als het seizoen dicht is. Een open seizoen met tien
        // sollicitanten is niet "doorlopen" — daar zit je middenin, en dat moet de
        // lijst zeggen, anders wijst hij je naar sluiten terwijl je nog aan het werk bent.
        ['sleutel' => 'doorlopen', 'titel' => 'Procedure doorlopen',
         'tekst' => 'Uitnodigen, beoordelen en aannemen doe je bij Sollicitanten.',
         'af' => ($sz['status'] ?? '') === 'gesloten' && (int)($sz['n'] ?? 0) > 0],
        ['sleutel' => 'sluiten', 'titel' => 'Seizoen gesloten',
         'tekst' => 'Aan het eind van de zomer. Alle gegevens blijven bewaard.',
         'af' => ($sz['status'] ?? '') === 'gesloten'],
    ];

    $gezet = false;
    foreach ($stappen as $i => $s) {
        $stappen[$i]['nu'] = (!$s['af'] && !$gezet);
        if ($stappen[$i]['nu']) $gezet = true;
    }
    return $stappen;
}

/**
 * Wat gebeurt er als je deze seizoensactie uitvoert?
 * Zelfde vorm als bh_gevolgen(), zodat admin/_bevestig.php hem zonder aanpassing rendert.
 *
 * @param array $sz  de seizoensrij, inclusief 'n' (aantal sollicitaties)
 */
function bh_seizoen_gevolgen(string $actie, array $sz): array {
    $naam = (string)($sz['naam'] ?? 'dit seizoen');
    $n    = (int)($sz['n'] ?? 0);

    if ($actie === 'open') {
        return [
            'titel'  => 'Seizoen ' . $naam . ' openen?',
            'regels' => [
                'Het aanmeldformulier op de website wordt meteen actief.',
                'Wie zich aanmeldt komt in dit seizoen terecht.',
            ],
            'bericht'      => 'Controleer eerst of de datums van de selectiedag en het trainingsweekend kloppen — die staan in de bevestigingsmail die elke aanmelder krijgt.',
            'onomkeerbaar' => false,
            'knop'         => 'Ja, openen',
            'toon'         => 'primair',
        ];
    }

    if ($actie === 'sluit') {
        return [
            'titel'  => 'Seizoen ' . $naam . ' sluiten?',
            'regels' => [
                'Het aanmeldformulier gaat uit; nieuwe aanmeldingen zijn niet meer mogelijk.',
                'Alle gegevens blijven bewaard en je kunt het seizoen later weer openen.',
            ],
            'bericht'      => null,
            'onomkeerbaar' => false,
            'knop'         => 'Ja, sluiten',
            'toon'         => 'primair',
        ];
    }

    // Verwijderen. Dit is het enige echt onomkeerbare op dit scherm, en het raakt
    // persoonsgegevens: benoem precies wát er weggaat, niet alleen "weet je het zeker".
    $regels = $n > 0
        ? [
            $n . ' ' . ($n === 1 ? 'sollicitatie verdwijnt' : 'sollicitaties verdwijnen') . ' uit dit seizoen.',
            'Daarmee verdwijnen ook de formulieren, de beoordelingen van de selectiedag, de beschikbaarheid en de ondertekeningen van die mensen.',
            'Dit is niet terug te draaien en er is geen prullenbak.',
          ]
        : ['Dit seizoen heeft geen sollicitaties; er gaan geen persoonsgegevens verloren.'];

    return [
        'titel'        => 'Seizoen ' . $naam . ' definitief verwijderen?',
        'regels'       => $regels,
        'bericht'      => $n > 0 ? 'Wil je de gegevens bewaren maar het seizoen niet meer gebruiken? Sluit het dan in plaats van verwijderen.' : null,
        'onomkeerbaar' => true,
        'knop'         => $n > 0 ? 'Ja, verwijder alles' : 'Ja, verwijderen',
        'toon'         => 'gevaar',
    ];
}

} // function_exists

<?php
/**
 * Paal 13 — wat gebeurt er als ik dit doe? GEEN HTML.
 *
 * "Onomkeerbaar vraagt bevestiging, en zegt wat er uitgaat" (ontwerpfilosofie §8).
 * Deze functie beschrijft de gevolgen van een actie vóórdat hij wordt uitgevoerd.
 *
 * LET OP — wat hier bewust NIET gebeurt: het exacte aantal berichten voorspellen.
 * Of een melding daadwerkelijk vertrekt hangt af van regels die in de verzendkern
 * staan (dubbelcheck, dagplafond, kanaalvoorkeur, opt-out, ontbrekend adres). Die
 * hier naprogrammeren geeft twee implementaties van dezelfde regels, en dan bevestigt
 * het bestuur op een dag iets anders dan er gebeurt. Wat deze functie wél zeker weet,
 * en dus zegt: hoeveel mensen het betreft, of er automatisch bericht uitgaat voor deze
 * overgang, en of de actie terug te draaien is.
 *
 * Een exacte telling kan pas als melding_verstuur() een droogloop-stand krijgt die
 * dezelfde code doorloopt zonder te versturen. Tot die er is: geen getal beloven.
 */

if (!function_exists('bh_gevolgen')) {

/**
 * @param string $actie   sleutel uit de bulkbalk of een rij-actie
 * @param int[]  $ids     de betrokken sollicitaties
 * @return array{titel:string,regels:string[],bericht:?string,onomkeerbaar:bool,knop:string,toon:string}
 */
function bh_gevolgen(string $actie, array $ids): array {
    $n = count($ids);
    $mensen = $n === 1 ? 'één persoon' : $n . ' mensen';
    // Aan het begin van een zin; ucfirst() kent geen multibyte-é en liet "één" klein.
    $Mensen = $n === 1 ? 'Eén persoon' : $n . ' mensen';

    $naar = [
        'uitnodigen_selectie' => 'uitgenodigd_selectie',
        'uitnodigen_tw'       => 'uitgenodigd_tw',
        'aannemen'            => 'aangenomen',
        'aangenomen'          => 'aangenomen',
        'afgewezen'           => 'afgewezen',
    ][$actie] ?? null;

    if ($naar === null) {
        // Geen faseovergang: bericht sturen, exporteren. Die zijn niet onomkeerbaar.
        $titels = ['bericht_sturen' => 'Bericht opstellen voor ' . $mensen,
                   'exporteren'     => $Mensen . ' exporteren'];
        return [
            'titel' => $titels[$actie] ?? ($Mensen . ': ' . $actie),
            'regels' => [], 'bericht' => null, 'onomkeerbaar' => false,
            'knop' => 'Doorgaan', 'toon' => 'primair',
        ];
    }

    $regels = ['Dit zet ' . $mensen . ' op "' . fase_label($naar) . '".'];

    // Gaat er bericht uit? Dat weten we zeker: het staat per meldingstype in de
    // instellingen. De afwijsmail staat daar standaard uit — bewuste keuze, het
    // bestuur belt afwijzingen zelf.
    $bericht = null;
    $typen = function_exists('fase_meldingstypen') ? fase_meldingstypen($naar) : [];
    $aan = [];
    foreach ($typen as $t) {
        $modus = function_exists('melding_kanaalmodus') ? melding_kanaalmodus($t) : 'email';
        if ($modus !== 'uit') $aan[] = $t;
    }
    if ($aan) {
        $bericht = 'Er gaat automatisch bericht uit naar ' . $mensen
                 . '. Wie geen adres heeft of het bericht al kreeg, krijgt niets; dat staat daarna in het berichtenlog.';
    } elseif ($typen) {
        $bericht = 'Er gaat géén bericht uit: die melding staat uit in de instellingen.';
        if ($naar === 'afgewezen') {
            $bericht .= ' Het bestuur belt afwijzingen zelf.';
        }
    }

    $onomkeerbaar = in_array($naar, ['afgewezen', 'aangenomen'], true) || $aan !== [];
    if ($onomkeerbaar) {
        $regels[] = 'Een verstuurd bericht kun je niet terugnemen. De fase kun je wel terugzetten in het '
                  . 'dossier; een reden die je daarbij opgeeft komt in de tijdlijn.';
    }

    $knoppen = ['uitnodigen_selectie' => 'Ja, uitnodigen', 'uitnodigen_tw' => 'Ja, uitnodigen',
                'aannemen' => 'Ja, aannemen', 'aangenomen' => 'Ja, aannemen',
                'afgewezen' => 'Ja, afwijzen'];

    return [
        'titel'        => $Mensen . ' ' . [
                              'uitnodigen_selectie' => 'uitnodigen voor de selectiedag',
                              'uitnodigen_tw'       => 'uitnodigen voor het trainingsweekend',
                              'aannemen'            => 'aannemen',
                              'aangenomen'          => 'aannemen',
                              'afgewezen'           => 'afwijzen',
                          ][$actie] . '?',
        'regels'       => $regels,
        'bericht'      => $bericht,
        'onomkeerbaar' => $onomkeerbaar,
        'knop'         => $knoppen[$actie] ?? 'Doorgaan',
        'toon'         => $actie === 'afgewezen' ? 'gevaar' : 'primair',
    ];
}

/**
 * Het vinkje "zonder bericht" op het tussenscherm van een fasestap. Eén definitie voor
 * de lijst, de bulkbalk en de hoofdactie in de dossierkop, zodat het overal hetzelfde
 * heet en hetzelfde belooft.
 */
function bh_bevestig_stil_vink(): array {
    return ['naam' => 'stil', 'label' => 'Zonder bericht doorvoeren',
            'hulp' => 'De fase verandert wel, maar er gaat niets de deur uit. '
                    . 'Gebruik dit als je zelf belt of het al besproken is.'];
}

/**
 * De gevolgen van een dossier verwijderen, voor het tussenscherm (admin/_bevestig.php).
 *
 * Twee soorten, bewust gescheiden:
 *   sollicitatie  alleen deze sollicitatie weg; de persoon (naam, e-mail, telefoon)
 *                 blijft voor andere seizoenen.
 *   persoon       een AVG-verwijderverzoek (art. 17): alle sollicitaties van deze
 *                 persoon worden leeggemaakt en de persoon geanonimiseerd, precies zoals
 *                 bij "Gegevens laten verwijderen" in het portaal (includes/avg_wissen.php).
 *
 * @param int  $aantal_sol     hoeveel sollicitaties deze persoon heeft (bij 'persoon')
 * @param bool $loon_verwerkt  er ligt een verwerkt loondossier (bewaarplicht)
 */
function bh_gevolgen_verwijderen(string $omvang, int $aantal_sol, bool $loon_verwerkt): array {
    if ($omvang === 'persoon') {
        $regels = [
            $aantal_sol > 1
                ? 'Alle ' . $aantal_sol . ' sollicitaties van deze persoon worden leeggemaakt: formulier, foto, beschikbaarheid, notities, beoordelingen, taken en documenten.'
                : 'De sollicitatie wordt leeggemaakt: formulier, foto, beschikbaarheid, notities, beoordelingen, taken en documenten.',
            'Naam, e-mail, telefoon, geboortedatum en adres worden vervangen door "Verwijderd (verzoek AVG)". De lege sollicitatie blijft alleen voor de telling staan.',
            'In het berichtenlog verdwijnen het adres en de inhoud van de berichten.',
            'Ingelogde apparaten in het portaal worden uitgelogd.',
        ];
        $titel = 'Alles van deze persoon wissen?';
        $knop  = 'Ja, alles wissen';
    } else {
        $regels = [
            'Het formulier, de foto, de beschikbaarheid, de notities, de beoordelingen, de taken en de documenten van deze sollicitatie verdwijnen.',
            'In het berichtenlog verdwijnen de inhoud en het adres van de berichten van deze sollicitatie.',
            'Naam, e-mail en telefoon blijven bewaard, voor andere seizoenen. Vraagt iemand om verwijdering van alles, kies dan "Alles van deze persoon wissen".',
        ];
        $titel = 'Deze sollicitatie verwijderen?';
        $knop  = 'Ja, verwijderen';
    }
    $bericht = 'Dit kun je niet terugdraaien.';
    if ($loon_verwerkt) {
        $bericht = 'Er ligt een verwerkt loondossier. Het loonheffingsformulier en de kopie van het '
                 . 'identiteitsbewijs moeten vijf jaar bewaard blijven na het einde van het jaar waarin het '
                 . 'dienstverband eindigde. '
                 . 'Alleen als het dienstverband nooit is doorgegaan mogen ze mee. Dit kun je niet terugdraaien.';
    }
    return ['titel' => $titel, 'regels' => $regels, 'bericht' => $bericht,
            'onomkeerbaar' => true, 'knop' => $knop, 'toon' => 'gevaar'];
}

/** Het vinkje voor het loondossier op het verwijder-tussenscherm. */
function bh_bevestig_loon_vink(): array {
    return ['naam' => 'ook_loondocs', 'label' => 'Het dienstverband is niet doorgegaan',
            'hulp' => 'Vink dit alleen aan als deze persoon nooit heeft gewerkt. Het loonheffingsformulier '
                    . 'en de kopie van het identiteitsbewijs worden dan ook vernietigd. Zonder vinkje wordt niets verwijderd.'];
}

} // function_exists

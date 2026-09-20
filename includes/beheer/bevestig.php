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
                   'exporteren'     => $mensen . ' exporteren'];
        return [
            'titel' => $titels[$actie] ?? ($mensen . ': ' . $actie),
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
        $regels[] = 'Een verstuurd bericht kun je niet terugnemen. De fase kun je wel terugzetten, '
                  . 'met een reden die in de tijdlijn komt.';
    }

    $knoppen = ['uitnodigen_selectie' => 'Ja, uitnodigen', 'uitnodigen_tw' => 'Ja, uitnodigen',
                'aannemen' => 'Ja, aannemen', 'aangenomen' => 'Ja, aannemen',
                'afgewezen' => 'Ja, afwijzen'];

    return [
        'titel'        => ucfirst($mensen) . ' ' . [
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

} // function_exists

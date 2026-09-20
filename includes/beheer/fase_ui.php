<?php
/**
 * Paal 13 — wat mag je met een kandidaat in deze fase? GEEN HTML.
 *
 * Eén bron voor de knop in een lijstrij, de knop in de dossierkop, de knoppen in de
 * bulkbalk en de hertekening na een AJAX-wissel. De ontwerpfilosofie eist: "de
 * actieknop in een rij is per fase voor iedereen hetzelfde". Dat is alleen af te
 * dwingen als er één plek is die hem bepaalt.
 *
 * LET OP — twee soorten knop, bewust gescheiden:
 *   bh_fase_actie()   de stap vooruit in de trechter. Precies nul of één per fase.
 *   bh_rij_attentie() een probleem dat aandacht vraagt (mail mislukt, geen adres).
 *                     Staat náást de fase-actie en vervangt hem nooit.
 * Het referentie-ontwerp haalt die twee op één plek door elkaar (bij dezelfde fase
 * staat er eenmaal "Uitnodigen TW" en eenmaal "Adres corrigeren"). Laat je dat via
 * één functie lopen, dan is "één actie per fase" binnen een week weer weg.
 */

if (!function_exists('bh_fase_actie')) {

/**
 * De hoofdactie voor een kandidaat in deze fase.
 *
 * @param string $fase  huidige fase van de sollicitatie
 * @param array  $ctx   'mag_bewerken' => bool (standaard: het recht wordt zelf bepaald)
 * @return array  leeg als er geen stap is, anders:
 *                ['actie','label','naar','toon','mail'=>bool,'bevestig'=>bool]
 */
function bh_fase_actie(string $fase, array $ctx = []): array {
    $mag = $ctx['mag_bewerken'] ?? (function_exists('mag') ? mag('sollicitanten', 'bewerken') : false);
    if (!$mag) return [];

    // Nul of één stap per fase. Een fase die hier niet staat heeft bewust geen knop:
    // "uitgenodigd, wacht op antwoord" is geen handeling voor het bestuur.
    $stappen = [
        'aangemeld'           => ['uitnodigen_selectie', 'Uitnodigen selectiedag', 'uitgenodigd_selectie'],
        'selectiedag_geweest' => ['uitnodigen_tw',       'Uitnodigen trainingsweekend', 'uitgenodigd_tw'],
        'tw_geweest'          => ['aannemen',            'Aannemen',                    'aangenomen'],
    ];
    if (!isset($stappen[$fase])) return [];

    $s = $stappen[$fase];
    return [
        'actie'    => $s[0],
        'label'    => $s[1],
        'naar'     => $s[2],
        'toon'     => 'primair',
        'mail'     => true,          // elke faseovergang stuurt standaard bericht
        'bevestig' => true,          // en vraagt dus bevestiging met dat aantal erbij
    ];
}

/** Hetzelfde label, maar kort genoeg voor een tabelcel ("Uitnodigen TW"). */
function bh_fase_actie_kort(array $actie): string {
    return [
        'uitnodigen_selectie' => 'Uitnodigen SD',
        'uitnodigen_tw'       => 'Uitnodigen TW',
        'aannemen'            => 'Aannemen',
    ][$actie['actie'] ?? ''] ?? ($actie['label'] ?? '');
}

/**
 * Een probleem bij deze kandidaat dat om een handeling vraagt. Staat vóór de
 * fase-actie in de rij en is nooit een vervanging ervan.
 *
 * @return array  leeg, of ['label','url','toon','uitleg']
 */
function bh_rij_attentie(array $rij): array {
    // 'kort' is wat er in een tabelcel past, 'uitleg' de volle reden (tooltip, dossier).
    if (!empty($rij['mail_mislukt'])) {
        return ['label' => 'Adres corrigeren', 'toon' => 'secundair', 'kort' => 'mail mislukt',
                'uitleg' => (string)($rij['mail_mislukt_reden'] ?? 'Bericht kon niet bezorgd worden'),
                'url' => null];
    }
    if (empty($rij['email']) && empty($rij['telefoon'])) {
        return ['label' => 'Adres invullen', 'toon' => 'secundair', 'kort' => 'geen adres',
                'uitleg' => 'Geen e-mail en geen telefoonnummer', 'url' => null];
    }
    return [];
}

/**
 * De fase-tabs met hun aantallen.
 *
 * Een tab verdwijnt als er niemand in zit — niet op grond van de seizoensfase. Dat
 * laatste is bewust zachter dan de filosofie: staat iemand na een handmatige
 * correctie of een import in een fase die "nu niet aan de beurt is", dan valt hij
 * anders uit élke tab en is hij onvindbaar. De seizoensfase bepaalt hier alleen de
 * volgorde en welke tab standaard openstaat; wie buiten de ronde valt komt in
 * 'vreemd' en krijgt onder de tabs één regel met een link.
 *
 * @param array<string,int> $aantallen  fase => aantal (uit één GROUP BY)
 * @param string $seizoen_fase          werving|formulieren|selectiedag|bellijst|trainingsweekend|aanname
 * @return array{tabs:array,vreemd:array,vreemd_aantal:int}
 */
function bh_fase_tabs(array $aantallen, string $seizoen_fase = ''): array {
    $hoort_bij = bh_fases_in_seizoensfase($seizoen_fase);

    $tabs = [];
    foreach (fasen() as $fase => $_label) {
        $n = (int)($aantallen[$fase] ?? 0);
        if ($n === 0) continue;                       // niemand erin → geen tab
        $tabs[] = [
            'sleutel' => $fase,
            'label'   => fase_label_kort($fase),
            'aantal'  => $n,
            'kleur'   => fase_kleur($fase),
            'actueel' => $hoort_bij === null || in_array($fase, $hoort_bij, true),
        ];
    }
    // Fases die in deze seizoensfase niet aan de beurt zijn, achteraan.
    usort($tabs, fn($a, $b) => ($b['actueel'] <=> $a['actueel']));

    $vreemd = array_values(array_filter($tabs, fn($t) => !$t['actueel']));
    return [
        'tabs'          => $tabs,
        'vreemd'        => $vreemd,
        'vreemd_aantal' => array_sum(array_column($vreemd, 'aantal')),
    ];
}

/** Welke kandidaatfases horen bij een seizoensfase? null = onbekend, toon alles. */
function bh_fases_in_seizoensfase(string $seizoen_fase): ?array {
    $kaart = [
        'werving'           => ['aangemeld'],
        'formulieren'       => ['aangemeld', 'uitgenodigd_selectie', 'aangemeld_selectie', 'afgewezen'],
        'selectiedag'       => ['aangemeld_selectie', 'selectiedag_geweest', 'afgewezen'],
        'bellijst'          => ['selectiedag_geweest', 'uitgenodigd_tw', 'afgewezen'],
        'trainingsweekend'  => ['uitgenodigd_tw', 'tw_geweest', 'afgewezen'],
        'aanname'           => ['tw_geweest', 'aangenomen', 'afgewezen'],
    ];
    return $kaart[$seizoen_fase] ?? null;
}

} // function_exists

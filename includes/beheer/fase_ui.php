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
    // De url wijst naar de kaart Gegevens corrigeren in het dossier: daar pas je het aan.
    $url = !empty($rij['id']) ? bh_gegevens_url((int)$rij['id']) : null;
    if (!empty($rij['mail_mislukt'])) {
        return ['label' => 'Adres corrigeren', 'toon' => 'secundair', 'kort' => 'mail mislukt',
                'uitleg' => (string)($rij['mail_mislukt_reden'] ?? 'Bericht kon niet bezorgd worden'),
                'url' => $url];
    }
    if (empty($rij['email']) && empty($rij['telefoon'])) {
        return ['label' => 'Adres invullen', 'toon' => 'secundair', 'kort' => 'geen adres',
                'uitleg' => 'Geen e-mail en geen telefoonnummer', 'url' => $url];
    }
    return [];
}

/** Het dossier met de kaart Gegevens corrigeren open. Eén plek voor dit adres. */
function bh_gegevens_url(int $sol_id): string {
    return APP_URL . '/admin/sollicitant.php?id=' . $sol_id . '&corrigeer=1#gegevens';
}

/**
 * Wijkt deze faseovergang af van de gewone volgorde van de trechter?
 *
 * Gewoon is: één stap vooruit, of afwijzen (kan altijd). Al het andere — terug, een
 * stap overslaan, een afwijzing terugdraaien — is een correctie, en daar hoort een reden
 * bij die later terug te lezen is.
 */
function bh_fase_afwijkend(string $van, string $naar): bool {
    if ($naar === 'afgewezen' || $van === $naar) return false;
    $trechter = array_values(array_filter(array_keys(fasen()), fn($f) => $f !== 'afgewezen'));
    $i = array_search($van, $trechter, true);
    return $i === false || ($trechter[$i + 1] ?? null) !== $naar;
}

/**
 * Suggestie "Uitnodigen?" in de lijst, als de uitnodig-modus op Suggestie staat.
 *
 * Alleen bij aangemeld, en alleen als de leeftijd bij de start van het werkseizoen binnen
 * de grenzen van Instellingen › Uitnodigen & automatisch valt — dezelfde regel als de
 * automatische uitnodiging (invite_aanbeveling), zodat Suggestie en Automatisch nooit
 * verschillend oordelen. Een suggestie, geen besluit: de knop blijft een mensenklik.
 */
function bh_uitnodig_suggestie(array $rij): bool {
    if (($rij['fase'] ?? '') !== 'aangemeld' || empty($rij['geboortedatum'])) return false;
    if (!function_exists('instelling') || instelling('invite.modus', 'suggestie') !== 'suggestie') return false;
    if (!function_exists('invite_aanbeveling') || !function_exists('leeftijd_op_datum')) return false;
    $s = function_exists('actief_seizoen') ? actief_seizoen() : null;
    try {
        $lft = leeftijd_op_datum((string)$rij['geboortedatum'], (string)($s['datum_werk_start'] ?? (date('Y') . '-06-01')));
    } catch (\Throwable $e) { return false; }
    return !empty(invite_aanbeveling($lft)['voldoet']);
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

    // Vaste volgorde: de trechter van boven naar beneden. Hersorteren op "actueel"
    // maakt het scherm onvoorspelbaar voor wie eens per week inlogt.
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

    $vreemd = array_values(array_filter($tabs, fn($t) => !$t['actueel']));
    return [
        'tabs'          => $tabs,
        'vreemd'        => $vreemd,
        'vreemd_aantal' => array_sum(array_column($vreemd, 'aantal')),
    ];
}

/**
 * In welke fase zit het seizoen? Afgeleid uit de datums die al in het seizoen staan,
 * met een handmatige uitweg: de instelling seizoen.fase overrulet de afleiding. Die
 * uitweg is er omdat een selectiedag verschoven kan worden of een belronde uitloopt —
 * en dan klopt elk fase-gestuurd scherm niet meer (ontwerpfilosofie §3).
 *
 * @return string werving|formulieren|selectiedag|bellijst|trainingsweekend|aanname
 */
function bh_seizoen_fase(?array $seizoen = null): string {
    $handmatig = function_exists('instelling') ? trim(instelling('seizoen.fase', '')) : '';
    if ($handmatig !== '' && bh_fases_in_seizoensfase($handmatig) !== null) return $handmatig;

    $s = $seizoen ?? (function_exists('actief_seizoen') ? actief_seizoen() : null);
    if (!$s) return 'werving';

    $vandaag = new DateTimeImmutable('today');
    $datum = function (?string $d): ?DateTimeImmutable {
        if (!$d) return null;
        try { return new DateTimeImmutable($d); } catch (\Exception $e) { return null; }
    };
    $sd = $datum($s['datum_selectiedag'] ?? null);
    $tw = $datum($s['datum_tw_start'] ?? null);
    $we = $datum($s['datum_tw_eind'] ?? null) ?? $tw;

    if ($we && $vandaag > $we)                          return 'aanname';
    if ($tw && $vandaag >= $tw)                         return 'trainingsweekend';
    // Tussen de selectiedag en het trainingsweekend wordt gebeld en besloten.
    if ($sd && $vandaag > $sd)                          return 'bellijst';
    if ($sd && $vandaag->format('Y-m-d') === $sd->format('Y-m-d')) return 'selectiedag';
    if (($s['status'] ?? '') === 'open')                return 'werving';
    return 'formulieren';
}

/** De zes seizoensfases met hun label, voor de keuzelijst in Instellingen. */
function bh_seizoen_fases(): array {
    return [
        'werving'          => 'Werving — aanmeldingen komen binnen',
        'formulieren'      => 'Formulieren — wachten op de kennismakingsformulieren',
        'selectiedag'      => 'Selectiedag',
        'bellijst'         => 'Bellijst — besluiten en bellen',
        'trainingsweekend' => 'Trainingsweekend',
        'aanname'          => 'Aanname — contracten en loondossiers',
    ];
}

/**
 * Welke kandidaatfases kunnen in deze seizoensfase bestaan? null = onbekend, toon alles.
 *
 * Cumulatief, en dat is met opzet: wie in de bellijst-fase nog in 'aangemeld' staat is
 * blijven hangen, maar hij hoort wél bij dit seizoen. Alleen wie vóórloopt op het
 * seizoen is echt vreemd — iemand die al is aangenomen terwijl de selectiedag nog moet
 * komen wijst op een handmatige correctie of een import, en dat wil je zien.
 * Afgewezen kan altijd.
 */
function bh_fases_in_seizoensfase(string $seizoen_fase): ?array {
    // De trechter op volgorde; de seizoensfase bepaalt hoe ver je mag zijn.
    $tot = [
        'werving'          => 'uitgenodigd_selectie',
        'formulieren'      => 'aangemeld_selectie',
        'selectiedag'      => 'selectiedag_geweest',
        'bellijst'         => 'uitgenodigd_tw',
        'trainingsweekend' => 'tw_geweest',
        'aanname'          => 'aangenomen',
    ];
    if (!isset($tot[$seizoen_fase])) return null;

    $uit = [];
    foreach (array_keys(fasen()) as $fase) {
        if ($fase === 'afgewezen') continue;          // staat buiten de trechter, zie hieronder
        $uit[] = $fase;
        if ($fase === $tot[$seizoen_fase]) break;
    }
    $uit[] = 'afgewezen';                             // kan in elke seizoensfase
    return $uit;
}

} // function_exists

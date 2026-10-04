<?php
/**
 * Paal 13 — het bewerken van één week van één persoon. GEEN HTML.
 *
 * Het ontwerp (Beheer ontwerp.dc.html r.896-945) vraagt meer van dit paneel dan alleen
 * "welke dagen": de datums van die week, de status van de beschikbaarheid, een reden
 * uit een lijst, de historie, en het doorzetten naar de volgende weken. Dat is te veel
 * om in de JavaScript van layout_bottom.php te bouwen — vandaar deze laag, die de
 * API bedient en straks ook een server-side terugval kan voeden.
 *
 * Eén rij per (sollicitant, week) in `beschikbaarheid_correctie`; dat model blijft
 * ongemoeid. Wat hier bij komt is wat je eromheen moet weten om een goed besluit te
 * nemen.
 */

if (!function_exists('bh_corr_redenen')) {

/**
 * De redenen die je kunt kiezen. Vrije tekst blijft mogelijk ("anders"), maar een
 * lijst zorgt dat dezelfde reden niet op vijf manieren wordt opgeschreven — anders is
 * het logboek later niet te doorzoeken.
 *
 * De sleutel wordt opgeslagen als onderdeel van de redentekst, niet apart: de tabel
 * heeft één `reden`-kolom en die hoeft daar niet voor te veranderen.
 */
function bh_corr_redenen(): array {
    return [
        'vakantie'   => 'Vakantie',
        'school'     => 'School of studie',
        'werk'       => 'Ander werk',
        'ziekte'     => 'Ziekte of herstel',
        'overleg'    => 'In overleg aangepast',
        'gebeld'     => 'Telefonisch doorgegeven',
        'correctie'  => 'Correctie van een invoerfout',
        'anders'     => 'Anders…',
    ];
}

/**
 * Alles wat het bewerkpaneel voor één week moet weten.
 *
 * @return array{ok:bool,fout?:string,naam:string,initialen:string,week:int,periode:string,
 *               soort:?string,dagen:array,reden:string,redensleutel:string,
 *               voorlopig:bool,laatste_week:int,historie:array}
 */
function bh_corr_week(int $sol_id, int $week): array {
    $leeg = ['ok' => false, 'fout' => 'Niet gevonden'];
    if ($sol_id <= 0 || $week <= 0) return $leeg;

    try {
        $q = db()->prepare("SELECT s.id, p.voornaam, p.tussenvoegsel, p.achternaam
                            FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id
                            WHERE s.id = ?");
        $q->execute([$sol_id]);
        $rij = $q->fetch();
    } catch (\Throwable $e) { return $leeg; }
    if (!$rij) return $leeg;

    $naam   = volledige_naam($rij);
    $weken  = seizoen_weken_datums();
    $wk     = $weken[$week] ?? null;
    // "6 t/m 12 juli" — de maand hoeft er maar één keer bij als de week niet over de
    // maandgrens loopt. Dat is hoe je het zelf zou opschrijven.
    $periode = '';
    if ($wk) {
        $zelfde = $wk['start']->format('n') === $wk['eind']->format('n');
        $periode = $zelfde
            ? $wk['start']->format('j') . ' t/m ' . datum_kort_nl($wk['eind'])
            : datum_kort_nl($wk['start']) . ' t/m ' . datum_kort_nl($wk['eind']);
    }

    $data  = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($sol_id) : [];
    $corr  = null;
    foreach (($data['correcties'] ?? []) as $c) {
        if ((int)$c['week'] === $week) { $corr = $c; break; }
    }

    /* Wat geldt er nu voor deze week? Niet alleen de correctie — die is er meestal
       niet. Klik je op een cel waar "4" staat, dan moet je die vier dagen aangevinkt
       zien staan; anders begin je bij niets en weet je niet wat je verandert.
       beschikbaarheid2_effectief() rekent alles mee: het regime (hele week of alleen
       weekend), afwezigheden, goedgekeurde verzoeken én een eventuele correctie. */
    $eff = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol_id) : [];
    $nu  = $eff[$week] ?? null;

    if ($corr && (string)$corr['soort'] === 'niet') {
        $dagen_nu = [];                       // hele week geschrapt door het bestuur
    } elseif ($corr && is_array($corr['dagen']) && $corr['dagen']) {
        $dagen_nu = array_map('intval', $corr['dagen']);
    } else {
        $dagen_nu = array_map('intval', $nu['dagen'] ?? []);
    }

    /* Het getal in de cel is dagen_max: de norm kan lager liggen dan het aantal dagen
       dat iemand kán. Dan staan er meer vinkjes dan het getal in de cel, en dat moet
       het paneel uitleggen in plaats van het te verbergen. */
    $norm = (int)($nu['dagen_max'] ?? count($dagen_nu));

    // Reden terugvertalen naar de keuzelijst: staat er een bekende reden voor de
    // liggende streep, dan was die gekozen; anders is het vrije tekst.
    $reden = (string)($corr['reden'] ?? '');
    $sleutel = '';
    foreach (bh_corr_redenen() as $k => $label) {
        if ($k === 'anders') continue;
        if ($reden === $label || str_starts_with($reden, $label . ' — ')) {
            $sleutel = $k;
            $reden   = trim(mb_substr($reden, mb_strlen($label)), " —\t\n");
            break;
        }
    }
    if ($sleutel === '' && $reden !== '') $sleutel = 'anders';

    return [
        'ok'             => true,
        'naam'           => $naam,
        'initialen'      => bh_corr_initialen($rij),
        'week'           => $week,
        'periode'        => $periode,
        'soort'          => $corr ? (string)$corr['soort'] : null,
        'dagen'          => $dagen_nu,
        'norm'           => $norm,
        'regime'         => (string)($nu['regime'] ?? ''),
        'voorkeur_vrij'  => array_map('intval', $nu['voorkeur_vrij'] ?? []),
        'heeft_correctie'=> $corr !== null,
        'ingevuld'       => $nu !== null || $corr !== null,
        'reden'          => $reden,
        'redensleutel'   => $sleutel,
        'voorlopig'      => !empty($data['profiel']['voorlopig']),
        'laatste_week'   => $weken ? (int)array_key_last($weken) : $week,
        'historie'       => bh_corr_historie($sol_id, $week, $corr, $data),
    ];
}

/** Twee letters voor het rondje in de kop. */
function bh_corr_initialen(array $persoon): string {
    $v = mb_substr(trim((string)($persoon['voornaam'] ?? '')), 0, 1);
    $a = mb_substr(trim((string)($persoon['achternaam'] ?? '')), 0, 1);
    return mb_strtoupper($v . $a);
}

/**
 * Wat er met deze week is gebeurd, nieuwste eerst.
 *
 * `beschikbaarheid_correctie` houdt één rij per week bij, dus er is geen volledige
 * geschiedenis — wel de laatste correctie, en het moment waarop de sollicitant zelf
 * heeft ingediend. Dat zijn precies de twee regels die het ontwerp laat zien. Meer
 * beloven dan er is zou erger zijn dan een korte lijst.
 */
function bh_corr_historie(int $sol_id, int $week, ?array $corr, array $data): array {
    $uit = [];
    if ($corr && !empty($corr['aangemaakt_op'])) {
        $wat = (string)$corr['soort'] === 'niet'
            ? 'W' . $week . ' niet beschikbaar'
            : 'W' . $week . ' aangepast naar ' . count((array)$corr['dagen']) . ' dagen';
        if (!empty($corr['reden'])) $wat .= ' — ' . $corr['reden'];
        $uit[] = [
            'wanneer' => datum_kort_nl(new DateTime((string)$corr['aangemaakt_op'])),
            'wat'     => ($corr['door'] ? $corr['door'] : 'Bestuur') . ': ' . $wat,
        ];
    }
    $ing = $data['profiel']['ingevuld_op'] ?? null;
    if ($ing) {
        $uit[] = [
            'wanneer' => datum_kort_nl(new DateTime((string)$ing)),
            'wat'     => 'Sollicitant: ingediend via het portaal',
        ];
    }
    return $uit;
}

/**
 * Een correctie opslaan, eventueel voor meerdere weken achter elkaar.
 *
 * Doorzetten naar latere weken is waarom dit hier staat en niet in de API: wie op
 * vakantie is, is dat zelden één week. Zonder dit klik je acht keer dezelfde correctie.
 *
 * @param int $tot_week  0 = alleen deze week; anders t/m die week (inclusief)
 * @return array{ok:bool,fout?:string,weken:array<int>}
 */
function bh_corr_opslaan(int $sol_id, int $week, ?string $soort, array $dagen,
                         string $reden, ?string $door, int $tot_week = 0): array {
    if ($sol_id <= 0 || $week <= 0) return ['ok' => false, 'fout' => 'Ongeldig', 'weken' => []];

    $weken_seizoen = array_keys(seizoen_weken_datums());
    $doel = [$week];
    if ($tot_week > $week) {
        foreach ($weken_seizoen as $w) {
            if ($w > $week && $w <= $tot_week) $doel[] = $w;
        }
    }

    foreach ($doel as $w) {
        beschikbaarheid2_correctie_zetten($sol_id, $w, $soort, $dagen, $reden ?: null, $door);
    }
    return ['ok' => true, 'weken' => $doel];
}

/**
 * De reden uit keuzelijst + toelichting tot één regel.
 *
 * Bewust één string en geen extra kolom: de tabel heeft `reden VARCHAR(300)` en die
 * regel komt zo in de tijdlijn en het logboek terecht. "Vakantie — drie weken Frankrijk"
 * leest daar beter dan een code met een los veld ernaast.
 */
function bh_corr_reden_tekst(string $sleutel, string $vrij): string {
    $vrij = trim($vrij);
    $lijst = bh_corr_redenen();
    if ($sleutel === '' || $sleutel === 'anders' || !isset($lijst[$sleutel])) return mb_substr($vrij, 0, 300);
    $tekst = $lijst[$sleutel] . ($vrij !== '' ? ' — ' . $vrij : '');
    return mb_substr($tekst, 0, 300);
}

} // function_exists

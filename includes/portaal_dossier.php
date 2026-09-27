<?php
/**
 * Paal 13 — Portaal: dossier van de ingelogde sollicitant + voortgang + teksten.
 *
 * Eén plek voor "wie is dit, in welke fase zit hij, wat zijn de seizoensgegevens" zodat
 * index.php, taken.php, berichten.php én _kop.php/_voet.php dezelfde bron gebruiken.
 * Geen HTML hier; alleen data en teksten.
 */

if (!function_exists('portaal_sollicitant_laden')) {

/**
 * Extra kolommen op `seizoenen` (naast de basiskolommen uit schema.sql). Eén lijst, gedeeld
 * met admin/seizoen.php, zodat kolom + formulier + portaal altijd gelijk lopen.
 */
function seizoen_kolom_definities(): array {
    return [
        'selectiedag_starttijd'    => 'TIME DEFAULT NULL',
        'selectiedag_verzameltijd' => 'TIME DEFAULT NULL',
        'selectiedag_eindtijd'     => 'TIME DEFAULT NULL',
        'adres_selectiedag'        => 'VARCHAR(255) DEFAULT NULL',
        'selectiedag_meenemen'     => 'TEXT DEFAULT NULL',
        'datum_tw_start'           => 'DATE DEFAULT NULL',
        'datum_tw_eind'            => 'DATE DEFAULT NULL',
        'locatie_tw'               => 'VARCHAR(255) DEFAULT NULL',
        'tw_verzameltijd'          => 'TIME DEFAULT NULL',
        'tw_begintijd'             => 'TIME DEFAULT NULL',
        'tw_eindtijd'              => 'TIME DEFAULT NULL',
        'adres_tw'                 => 'VARCHAR(255) DEFAULT NULL',
        'tw_meenemen'              => 'TEXT DEFAULT NULL',
        'datum_werk_start'         => 'DATE DEFAULT NULL',
        'datum_werk_eind'          => 'DATE DEFAULT NULL',
        'hoofdseizoen_start_week'  => 'INT DEFAULT NULL',
        'hoofdseizoen_eind_week'   => 'INT DEFAULT NULL',
    ];
}

/** Zorgt (failsafe) dat alle seizoen-kolommen bestaan. */
function seizoen_kolommen_verzeker(): void {
    if (!function_exists('db_kolom_verzeker') || schema_actueel()) return;
    try {
        $db = db();
        foreach (seizoen_kolom_definities() as $kolom => $def) db_kolom_verzeker($db, 'seizoenen', $kolom, $def);
    } catch (\Throwable $e) { error_log('seizoen_kolommen_verzeker: ' . $e->getMessage()); }
}

/**
 * Sollicitatie + persoon + seizoen in één rij (kolommen van het seizoen komen als losse velden
 * op de rij: datum_selectiedag, locatie_selectiedag, selectiedag_eindtijd, ...).
 * Per request gecachet, zodat kop/voet/pagina dezelfde rij delen. Null als niet gevonden.
 */
function portaal_sollicitant_laden(int $sol_id): ?array {
    static $cache = [];
    if (!empty($GLOBALS['_portaal_sol_cache_wis'])) {
        foreach ($GLOBALS['_portaal_sol_cache_wis'] as $wis) unset($cache[$wis]);
        $GLOBALS['_portaal_sol_cache_wis'] = [];
    }
    if (array_key_exists($sol_id, $cache)) return $cache[$sol_id];

    $basis = "s.*, p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon, p.geboortedatum,
              sz.naam sz_naam, sz.naam seizoen_naam, sz.datum_selectiedag, sz.locatie_selectiedag,
              sz.datum_tw_start, sz.datum_tw_eind, sz.locatie_tw";
    $from  = " FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id
               JOIN seizoenen sz ON sz.id=s.seizoen_id WHERE s.id=?";
    $extra = [];
    foreach (array_keys(seizoen_kolom_definities()) as $k) {
        if (in_array($k, ['datum_tw_start', 'datum_tw_eind', 'locatie_tw'], true)) continue;
        $extra[] = "sz.`$k`";
    }
    $rij = null;
    try {
        $st = db()->prepare("SELECT $basis, " . implode(', ', $extra) . $from);
        $st->execute([$sol_id]); $rij = $st->fetch() ?: null;
    } catch (\Throwable $e) {
        // Oudere DB zonder de extra kolommen: basisvelden, rest leeg.
        try {
            $st = db()->prepare("SELECT $basis" . $from);
            $st->execute([$sol_id]); $rij = $st->fetch() ?: null;
            if ($rij) foreach (array_keys(seizoen_kolom_definities()) as $k) { if (!array_key_exists($k, $rij)) $rij[$k] = null; }
        } catch (\Throwable $e2) { $rij = null; }
    }
    return $cache[$sol_id] = $rij;
}

/** Vergeet de gecachte rij (na een fase-wissel of andere wijziging in dezelfde request). */
function portaal_sollicitant_cache_wissen(int $sol_id): void {
    $GLOBALS['_portaal_sol_cache_wis'][] = $sol_id;
}

/** Volledige naam van de sollicitant. */
function portaal_naam(array $sol): string {
    return trim(($sol['voornaam'] ?? '') . ' ' . trim(($sol['tussenvoegsel'] ?? '') . ' ' . ($sol['achternaam'] ?? '')));
}

/** Initialen (max 2) voor het gebruikersblok in de zijbalk. */
function portaal_initialen(array $sol): string {
    $eerste = function (string $s): string { return preg_match('/^\s*(\S)/u', $s, $m) ? $m[1] : ''; };
    $v = $eerste($sol['voornaam'] ?? '');
    $a = $eerste($sol['achternaam'] ?? '');
    $ini = function_exists('mb_strtoupper') ? mb_strtoupper($v . $a, 'UTF-8') : strtoupper($v . $a);
    return $ini !== '' ? $ini : '?';
}

/**
 * Voortgang in vijf stappen (ontwerp: "Stap N van 5 — Naam", percentage, bolletjes).
 * Null in de eindfases (portaal_eindfases(): geen reis meer te tonen).
 */
function portaal_voortgang(string $fase): ?array {
    $stappen = ['Aangemeld', 'Selectiedag', 'Na de selectie', 'Trainingsweekend', 'Aangenomen'];
    $index = [
        'aangemeld'            => 0,
        'uitgenodigd_selectie' => 1,
        'aangemeld_selectie'   => 1,
        'selectiedag_geweest'  => 2,
        'uitgenodigd_tw'       => 3,
        'tw_geweest'           => 3,
        'aangenomen'           => 4,
    ];
    if (!isset($index[$fase])) return null;
    $i = $index[$fase];
    return [
        'stap'   => $i + 1,
        'totaal' => count($stappen),
        'naam'   => $stappen[$i],
        'pct'    => (int)round(($i + 1) / count($stappen) * 100),
        'klaar'  => $fase === 'aangenomen',
        'stappen'=> $stappen,
        'index'  => $i,
    ];
}

/** Korte toelichting bij de voortgang (desktop-kaart), per fase. */
function portaal_voortgang_toelichting(string $fase): string {
    return match ($fase) {
        'aangemeld'            => 'Word je uitgenodigd, dan krijg je bericht en verschijnen hier je taken.',
        'uitgenodigd_selectie',
        'aangemeld_selectie'   => 'Na de selectiedag hoor je of je door mag naar het trainingsweekend.',
        'selectiedag_geweest'  => 'Het bestuur bespreekt de selectiedag. Je hoort of je door mag naar het trainingsweekend.',
        'uitgenodigd_tw'       => 'Na het trainingsweekend hoor je of je bent aangenomen.',
        'tw_geweest'           => 'Het bestuur bespreekt het trainingsweekend. Je hoort snel of je bent aangenomen.',
        'aangenomen'           => 'Je hebt alle stappen doorlopen. Welkom bij Paal 13.',
        default                => '',
    };
}

/**
 * Begroeting (titel + tekst) per fase, afhankelijk van het aantal open taken.
 */
function portaal_begroeting(array $sol, int $open): array {
    $naam = $sol['voornaam'] ?? '';
    $sd   = !empty($sol['datum_selectiedag']) ? portaal_datum_lang($sol['datum_selectiedag']) : '';
    $tw   = !empty($sol['datum_tw_start'])    ? portaal_datum_lang($sol['datum_tw_start'])    : '';
    $n    = ['', 'Eén ding', 'Twee dingen', 'Drie dingen', 'Vier dingen', 'Vijf dingen'][$open] ?? ($open . ' dingen');
    switch ($sol['fase'] ?? '') {
        case 'aangemeld':
            return ['titel' => 'Hoi ' . $naam, 'tekst' => 'Je aanmelding is binnen. Wij nemen contact op zodra we je uitnodigen.'];
        case 'uitgenodigd_selectie':
        case 'aangemeld_selectie':
            if ($open > 0) return ['titel' => 'Je bent uitgenodigd', 'tekst' => $n . ' te doen' . ($sd ? ' vóór ' . lcfirst($sd) : '') . '.'];
            return ['titel' => 'Je bent uitgenodigd', 'tekst' => 'Alles is binnen.' . ($sd ? ' Tot ' . lcfirst($sd) . '!' : ' Tot op de selectiedag!')];
        case 'selectiedag_geweest':
            return ['titel' => 'Hoi ' . $naam, 'tekst' => 'Je hebt de selectiedag gedaan. We laten snel weten hoe het verder gaat.'];
        case 'uitgenodigd_tw':
            if ($open > 0) return ['titel' => 'Door naar het trainingsweekend', 'tekst' => $n . ' te doen' . ($tw ? ' vóór ' . lcfirst($tw) : '') . '.'];
            return ['titel' => 'Door naar het trainingsweekend', 'tekst' => 'Je bent erbij.' . ($tw ? ' Tot ' . lcfirst($tw) . '!' : '')];
        case 'tw_geweest':
            return ['titel' => 'Hoi ' . $naam, 'tekst' => 'Het trainingsweekend zit erop. We laten snel weten hoe het verder gaat.'];
        case 'aangenomen':
            return ['titel' => 'Welkom bij Paal 13', 'tekst' => 'Je bent aangenomen als strandwacht voor ' . ($sol['sz_naam'] ?? 'dit seizoen') . '.'];
        case 'afgewezen':
            return ['titel' => 'Hoi ' . $naam, 'tekst' => match (portaal_afwijs_stadium((int)($sol['id'] ?? 0))) {
                'tw'          => 'Bedankt voor je inzet tijdens het trainingsweekend.',
                'selectiedag' => 'Bedankt voor je inzet op de selectiedag.',
                default       => 'Bedankt voor je aanmelding en je interesse in Paal 13.',
            }];
        case 'afgehaakt':
            return ['titel' => 'Hoi ' . $naam, 'tekst' => 'Bedankt voor je interesse in Paal 13.'];
        case 'verwijderd_door_sollicitant':
            return ['titel' => 'Tot ziens', 'tekst' => 'Je gegevens zijn verwijderd.'];
    }
    return ['titel' => 'Hoi ' . $naam, 'tekst' => ''];
}

/** Fases waarin de sollicitatie is afgelopen zonder aanname: geen taken, geen voortgang meer. */
function portaal_eindfases(): array {
    return ['afgewezen', 'afgehaakt', 'verwijderd_door_sollicitant'];
}

function portaal_is_eindfase(string $fase): bool {
    return in_array($fase, portaal_eindfases(), true);
}

/**
 * De status zoals de sollicitant hem ziet: zonder jargon ("TW geweest") en zonder
 * beheertaal ("Afgehaakt"). fase_label() blijft voor het beheer.
 */
function portaal_fase_label(string $fase): string {
    return [
        'aangemeld'                   => 'Aanmelding ontvangen',
        'uitgenodigd_selectie'        => 'Uitgenodigd voor de selectiedag',
        'aangemeld_selectie'          => 'Aangemeld voor de selectiedag',
        'selectiedag_geweest'         => 'Selectiedag gedaan',
        'uitgenodigd_tw'              => 'Uitgenodigd voor het trainingsweekend',
        'tw_geweest'                  => 'Trainingsweekend gedaan',
        'aangenomen'                  => 'Aangenomen',
        'afgewezen'                   => 'Dit seizoen niet verder',
        'afgehaakt'                   => 'Sollicitatie gestopt',
        'verwijderd_door_sollicitant' => 'Gegevens verwijderd',
    ][$fase] ?? 'In behandeling';
}

/**
 * Hoe ver kwam een afgewezen sollicitant? 'tw' | 'selectiedag' | 'aanmelding'.
 *
 * De afwijstekst mag geen selectie noemen die er niet was. Bronnen, van hard naar zacht:
 *  1. aanwezigheid bij het evenement (tw_deelname / selectie_indeling, aanwezig = 1): ingecheckt is een feit;
 *  2. de fase van vóór de afwijzing uit het audit-log (fase_zetten schrijft oud → nieuw);
 *  3. niets gevonden = afgewezen op de aanmelding.
 */
function portaal_afwijs_stadium(int $sol_id): string {
    static $cache = [];
    if (isset($cache[$sol_id])) return $cache[$sol_id];
    if ($sol_id <= 0) return 'aanmelding';
    foreach (['tw' => 'tw_deelname', 'selectiedag' => 'selectie_indeling'] as $stadium => $tabel) {
        try {
            $q = db()->prepare("SELECT 1 FROM `$tabel` WHERE sollicitatie_id=? AND aanwezig=1 LIMIT 1");
            $q->execute([$sol_id]);
            if ($q->fetchColumn()) return $cache[$sol_id] = $stadium;
        } catch (\Throwable $e) {}
    }
    try {
        $q = db()->prepare("SELECT details FROM audit_log WHERE objecttype='sollicitatie' AND object_id=? AND actie='fase_gewijzigd'
                              AND details LIKE ? ORDER BY id DESC LIMIT 1");
        $q->execute([$sol_id, '%"nieuw":"afgewezen"%']);
        $d = json_decode((string)$q->fetchColumn(), true);
        $oud = is_array($d) ? (string)($d['oud'] ?? '') : '';
        if (in_array($oud, ['tw_geweest', 'aangenomen'], true))           return $cache[$sol_id] = 'tw';
        if (in_array($oud, ['selectiedag_geweest', 'uitgenodigd_tw'], true)) return $cache[$sol_id] = 'selectiedag';
    } catch (\Throwable $e) {}
    return $cache[$sol_id] = 'aanmelding';
}

/**
 * De eindstand op de startpagina (afgewezen, gestopt, zelf verwijderd): titel, tekst en of de
 * AVG-acties (seintje, downloaden, verwijderen) erbij horen. Null buiten de eindfases.
 * Geen HTML; portaal/_start_info.php rendert.
 *
 * @return ?array ['titel','tekst','icoon','kleur','avg' => bool]
 */
function portaal_eindstand(array $sol): ?array {
    $volgend = 'Dat zegt niets over volgend jaar — je bent dan opnieuw welkom.';
    switch ($sol['fase'] ?? '') {
        case 'afgewezen':
            $tekst = match (portaal_afwijs_stadium((int)($sol['id'] ?? 0))) {
                'tw'          => 'Na het trainingsweekend hebben we besloten je dit seizoen niet aan te nemen.',
                'selectiedag' => 'Na de selectiedag hebben we besloten je dit seizoen niet aan te nemen.',
                default       => 'We hebben besloten je dit seizoen niet verder mee te nemen in de sollicitatie.',
            };
            return ['titel' => 'Dit seizoen niet verder', 'tekst' => $tekst . ' ' . $volgend, 'icoon' => 'kruis-cirkel', 'kleur' => 'error', 'avg' => true];
        case 'afgehaakt':
            return ['titel' => 'Je sollicitatie is gestopt',
                    'tekst' => 'Je sollicitatie voor ' . ($sol['sz_naam'] ?? 'dit seizoen') . ' loopt niet meer door. Je hoeft niets meer te doen. '
                             . 'Volgend seizoen kun je je gewoon opnieuw aanmelden.',
                    'icoon' => 'info', 'kleur' => 'navy', 'avg' => true];
        case 'verwijderd_door_sollicitant':
            return ['titel' => 'Je gegevens zijn verwijderd',
                    'tekst' => 'Je hebt gevraagd je gegevens te verwijderen. Je sollicitatie is daarmee gestopt. '
                             . 'Volgend seizoen kun je je gewoon opnieuw aanmelden.',
                    'icoon' => 'info', 'kleur' => 'navy', 'avg' => false];
    }
    return null;
}

/** Lege staat van de takenlijst: in een eindfase komt er niets meer bij, dus ook niets beloven. */
function portaal_leeg_tekst(array $sol): array {
    if (portaal_is_eindfase((string)($sol['fase'] ?? ''))) {
        return ['titel' => 'Geen taken meer', 'tekst' => 'Je sollicitatie loopt niet meer, dus hier komt niets meer bij.'];
    }
    return ['titel' => 'Je bent helemaal bij', 'tekst' => 'Er staat niets open. Zodra er iets van je nodig is, verschijnt het hier en krijg je een bericht.'];
}

/**
 * Wat er gebeurt nu je dit hebt ingeleverd — de zin die na "gelukt" hoort te volgen.
 *
 * Zonder dit eindigt elke taak in stilte: je vult iets in, ziet een vinkje, en weet
 * niet of er nog iets van je wordt verwacht of wanneer je iets hoort. Wachten is prima
 * zolang je weet waarop en hoelang.
 *
 * Eén plek, want dezelfde zin hoort op de bevestigingspagina van het formulier, in de
 * taakrij én in de mail te staan.
 *
 * @param int $open aantal taken dat hierna nog openstaat
 */
function portaal_hierna(array $sol, int $open = 0): string {
    $sd = !empty($sol['datum_selectiedag']) ? portaal_datum_lang($sol['datum_selectiedag']) : '';
    $tw = !empty($sol['datum_tw_start'])    ? portaal_datum_lang($sol['datum_tw_start'])    : '';

    if ($open > 0) {
        return $open === 1
            ? 'Er staat hierna nog één ding open.'
            : 'Er staan hierna nog ' . $open . ' dingen open.';
    }
    return match ($sol['fase'] ?? '') {
        'aangemeld'            => 'Dat was het voor nu. Word je uitgenodigd, dan krijg je bericht en verschijnen hier je taken.',
        'uitgenodigd_selectie',
        'aangemeld_selectie'   => $sd !== ''
            ? 'Dat was het voor nu. We zien je op ' . lcfirst($sd) . ' — je krijgt daarvoor nog een herinnering.'
            : 'Dat was het voor nu. Je krijgt bericht met de details van de selectiedag.',
        'selectiedag_geweest'  => 'Dat was het voor nu. Het bestuur bespreekt de selectiedag; je hoort binnen twee weken of je door mag.',
        'uitgenodigd_tw'       => $tw !== ''
            ? 'Dat was het voor nu. We zien je op ' . lcfirst($tw) . '.'
            : 'Dat was het voor nu. Je krijgt bericht met de details van het trainingsweekend.',
        'tw_geweest'           => 'Dat was het voor nu. Je hoort binnen twee weken of je bent aangenomen.',
        'aangenomen'           => 'Dat was het — je bent helemaal rond. Tot op het strand.',
        default                => 'Dat was het voor nu. Je hoort van ons zodra er nieuws is.',
    };
}

/**
 * Waar een taakpagina (beschikbaarheid, BLK, overzicht) na afloop naar terugkeert: de Taken-tab als
 * de sollicitant daar vandaan kwam (?terug=taken in de link, of het hidden veld 'terug' in het
 * formulier), anders de startpagina. Whitelist, dus nooit een open redirect.
 */
function portaal_terug_url(): string {
    return portaal_terug_param() === 'taken' ? APP_URL . '/portaal/taken.php' : APP_URL . '/portaal/';
}

/** 'taken' of '' — de waarde om door te geven in een hidden veld of een vervolg-link. */
function portaal_terug_param(): string {
    $t = $_POST['terug'] ?? ($_GET['terug'] ?? '');
    return $t === 'taken' ? 'taken' : '';
}

/** Hangt ?terug=taken aan een portaal-eigen url als de herkomst de Taken-tab is. */
function portaal_url_met_terug(string $url, string $terug): string {
    if ($terug !== 'taken' || !str_starts_with($url, APP_URL . '/portaal/')) return $url;
    return $url . (str_contains($url, '?') ? '&' : '?') . 'terug=taken';
}

/** E-mailadres voor "Vragen? Mail ..." (instelling, met fallback). */
function portaal_contact_email(): string {
    // Eén adres voor sollicitanten (Instellingen › Mail › Contact voor sollicitanten); leeg = het bestuursadres.
    $e = function_exists('instelling') ? trim((string)instelling('mail.contact_email', '')) : '';
    if ($e === '' && function_exists('instelling')) $e = trim((string)instelling('mail.bestuur_email', ''));
    return $e !== '' ? $e : 'sollicitatie@paal13.com';
}

/** "2026-04-11" → "Zaterdag 11 april" (zonder jaar; ontwerp-notatie). */
function portaal_datum_lang(?string $d): string {
    if (!$d) return '';
    try { $dt = new DateTime($d); } catch (\Exception $e) { return $d; }
    $dagen   = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];
    $maanden = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
    return $dagen[(int)$dt->format('N') - 1] . ' ' . $dt->format('j') . ' ' . $maanden[(int)$dt->format('n') - 1];
}

/** "2026-04-11" → "11 april" (kort, zonder jaar). */
function portaal_datum_kort(?string $d): string {
    if (!$d) return '';
    try { $dt = new DateTime($d); } catch (\Exception $e) { return $d; }
    $maanden = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
    return $dt->format('j') . ' ' . $maanden[(int)$dt->format('n') - 1];
}

/** "08:45:00" → "08:45". Leeg blijft leeg. */
function portaal_tijd(?string $t): string {
    if (!$t) return '';
    return substr($t, 0, 5);
}

/**
 * Tijdregel voor de infokaart: "Verzamelen 08:45, start 09:00 — tot ± 16:00" (alleen wat er is).
 */
function portaal_tijdregel(?string $verzamel, ?string $start, ?string $eind): string {
    $delen = [];
    if ($verzamel) $delen[] = 'Verzamelen ' . portaal_tijd($verzamel);
    if ($start)    $delen[] = ($delen ? 'start ' : 'Start ') . portaal_tijd($start);
    $regel = implode(', ', $delen);
    if ($eind) $regel .= ($regel ? ' — ' : '') . 'tot ± ' . portaal_tijd($eind);
    return $regel;
}

} // function_exists

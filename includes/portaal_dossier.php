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
    if (!function_exists('db_kolom_verzeker')) return;
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
 * Null bij afgewezen (geen reis meer te tonen).
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
            return ['titel' => 'Hoi ' . $naam, 'tekst' => 'Bedankt voor je inzet tijdens de selectie.'];
    }
    return ['titel' => 'Hoi ' . $naam, 'tekst' => ''];
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
    $e = function_exists('instelling') ? instelling('mail.bestuur_email', '') : '';
    return $e ?: 'bestuur@paal13.com';
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

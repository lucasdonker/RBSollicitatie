<?php
/**
 * Paal 13 — Wat de selectiedag en het trainingsweekend gemeen hebben. GEEN HTML.
 *
 * Twee "evenementen" met dezelfde handelingen: inchecken (tablet of beheer), inchecken
 * afsluiten, "ik kom / ik kan niet", en de vergrendeling "bezig door X" op de bellijst en bij
 * de gesprekken. Eén laag, zodat beide modules precies hetzelfde doen.
 *
 * Evenement-sleutels: 'selectiedag' en 'tw'. De tabellen blijven gescheiden
 * (selectie_indeling / tw_deelname): twee momenten, twee sets, zie includes/trainingsweekend.php.
 *
 * De fase zet alleen fase_zetten(): inchecken → selectiedag_geweest / tw_geweest (met audit),
 * ongedaan maken → stil terug (geen dubbele uitnodiging).
 */

if (!function_exists('evenement_def')) {

/** De vaste gegevens per evenement, of null bij een onbekende sleutel. */
function evenement_def(string $ev): ?array {
    return [
        'selectiedag' => [
            'label' => 'selectiedag', 'module' => 'selectiedag', 'tabel' => 'selectie_indeling', 'groep_tabel' => 'selectie_groep',
            'kandidaat_fases' => ['uitgenodigd_selectie', 'aangemeld_selectie', 'selectiedag_geweest'],
            'voor_fases' => ['uitgenodigd_selectie', 'aangemeld_selectie'], 'na_fase' => 'selectiedag_geweest',
            'code' => 'selectie.incheck_code', 'pin' => 'selectie.afsluit_pin',
        ],
        'tw' => [
            'label' => 'trainingsweekend', 'module' => 'trainingsweekend', 'tabel' => 'tw_deelname', 'groep_tabel' => 'tw_groep',
            'kandidaat_fases' => ['uitgenodigd_tw', 'tw_geweest'],
            'voor_fases' => ['uitgenodigd_tw'], 'na_fase' => 'tw_geweest',
            'code' => 'tw.incheck_code', 'pin' => 'tw.afsluit_pin',
        ],
    ][$ev] ?? null;
}

/** Tabellen van het evenement bestaan (via de setup van de module). */
function evenement_setup(string $ev): void {
    if ($ev === 'tw') { if (function_exists('tw_auto_setup')) tw_auto_setup(); }
    elseif (function_exists('selectiedag_auto_setup')) selectiedag_auto_setup();
}

/** Hoort deze sollicitatie bij dit evenement van dit seizoen? (Server-side, niet vertrouwen op de knop.) */
function evenement_is_kandidaat(string $ev, int $seizoen_id, int $sol_id): bool {
    $d = evenement_def($ev);
    if (!$d || $seizoen_id <= 0 || $sol_id <= 0) return false;
    try {
        $q = db()->prepare("SELECT 1 FROM sollicitaties WHERE id=? AND seizoen_id=?
                            AND fase IN (" . implode(',', array_fill(0, count($d['kandidaat_fases']), '?')) . ")");
        $q->execute(array_merge([$sol_id, $seizoen_id], $d['kandidaat_fases']));
        return (bool)$q->fetchColumn();
    } catch (\Exception $e) { return false; }
}

function evenement_rij_verzeker(string $ev, int $seizoen_id, int $sol_id): void {
    $d = evenement_def($ev);
    if (!$d) return;
    evenement_setup($ev);
    db()->prepare("INSERT IGNORE INTO `{$d['tabel']}` (seizoen_id, sollicitatie_id) VALUES (?,?)")->execute([$seizoen_id, $sol_id]);
}

/**
 * "Dit ben ik niet" op de tablet: wie per ongeluk op de verkeerde naam tikte, mag die ene
 * check-in binnen een minuut terugdraaien. Het token bindt evenement, seizoen, persoon en tijd
 * (HMAC met de kluissleutel), zodat niemand bij de ingang willekeurig anderen kan uitchecken.
 */
function evenement_ongedaan_token(string $ev, int $seizoen_id, int $sol_id, int $ts): string {
    $sleutel = defined('KLUIS_SLEUTEL') ? (string)KLUIS_SLEUTEL : '';
    return $ts . '.' . substr(hash_hmac('sha256', "kiosk-ongedaan|$ev|$seizoen_id|$sol_id|$ts", $sleutel), 0, 32);
}

function evenement_ongedaan_token_ok(string $ev, int $seizoen_id, int $sol_id, string $token): bool {
    $delen = explode('.', $token, 2);
    $ts = (int)$delen[0];
    if ($ts <= 0 || time() - $ts > 60 || $ts > time() + 5) return false;
    return hash_equals(evenement_ongedaan_token($ev, $seizoen_id, $sol_id, $ts), $token);
}

/** Staat deze persoon al als aanwezig? (Voor de tablet: alleen een nieuwe check-in mag terug.) */
function evenement_is_aanwezig(string $ev, int $seizoen_id, int $sol_id): bool {
    $d = evenement_def($ev);
    if (!$d) return false;
    try {
        $q = db()->prepare("SELECT aanwezig FROM `{$d['tabel']}` WHERE seizoen_id=? AND sollicitatie_id=?");
        $q->execute([$seizoen_id, $sol_id]);
        return (int)$q->fetchColumn() === 1;
    } catch (\Exception $e) { return false; }
}

/**
 * Inchecken of ongedaan maken. Tablet en beheer gebruiken allebei deze functie.
 * Een laatkomer na het afsluiten checkt gewoon in: de afmelding ("niet verschenen") vervalt dan.
 * Retourneert ['aanwezig' => bool, 'groep_nummer' => ?int, 'groep_naam' => ?string].
 */
function evenement_inchecken(string $ev, int $seizoen_id, int $sol_id, bool $aanwezig, string $bron = 'beheer'): array {
    $d = evenement_def($ev);
    $uit = ['aanwezig' => $aanwezig, 'groep_nummer' => null, 'groep_naam' => null];
    if (!$d) return $uit;
    evenement_rij_verzeker($ev, $seizoen_id, $sol_id);
    $db = db();
    $db->prepare("UPDATE `{$d['tabel']}` SET aanwezig=?, ingecheckt_op=" . ($aanwezig ? "IFNULL(ingecheckt_op, NOW())" : "NULL") . ",
                  afgemeld_reden=NULL WHERE seizoen_id=? AND sollicitatie_id=?")
       ->execute([$aanwezig ? 1 : 0, $seizoen_id, $sol_id]);

    if (function_exists('fase_zetten')) {
        if ($aanwezig) {
            fase_zetten($sol_id, $d['na_fase'], 'inchecken', ['seizoen_id' => $seizoen_id, 'alleen_van' => $d['voor_fases']]);
        } else {
            // Terug naar de fase van vóór het inchecken, zonder bericht (de uitnodiging ging al).
            fase_zetten($sol_id, evenement_terugfase($ev, $sol_id), 'inchecken', ['seizoen_id' => $seizoen_id, 'alleen_van' => [$d['na_fase']], 'stil' => true]);
        }
    }
    if (function_exists('audit_log')) audit_log($aanwezig ? 'ingecheckt' : 'inchecken_ongedaan', 'sollicitatie', $sol_id, ['evenement' => $ev, 'via' => $bron]);

    // "Je zit in groep 2" — beide evenementen hebben groepen (includes/evenement_groepen.php).
    try {
        $q = $db->prepare("SELECT g.nummer, g.naam FROM `{$d['tabel']}` t LEFT JOIN `{$d['groep_tabel']}` g ON g.id = t.groep_id
                           WHERE t.seizoen_id=? AND t.sollicitatie_id=?");
        $q->execute([$seizoen_id, $sol_id]);
        $r = $q->fetch() ?: [];
        $uit['groep_nummer'] = isset($r['nummer']) ? (int)$r['nummer'] : null;
        $uit['groep_naam']   = $r['naam'] ?? null;
    } catch (\Exception $e) {}
    return $uit;
}

/** FROM + joins voor een lijst van een evenement, met de groep erbij (alias t = deelname, g = groep). */
function evenement_from_met_groep(array $d): string {
    return ", g.nummer AS groep_nummer, g.naam AS groep_naam FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id
             LEFT JOIN `{$d['tabel']}` t ON t.sollicitatie_id = s.id AND t.seizoen_id = s.seizoen_id
             LEFT JOIN `{$d['groep_tabel']}` g ON g.id = t.groep_id";
}

/**
 * De incheck-lijst (beheer): iedereen die voor dit evenement is uitgenodigd, met foto,
 * leeftijd, groep (selectiedag), aanwezigheid, afmelding en bevestiging. Eén query.
 */
function evenement_lijst(string $ev, int $seizoen_id): array {
    $d = evenement_def($ev);
    if (!$d || $seizoen_id <= 0) return [];
    evenement_setup($ev);
    $groep = evenement_from_met_groep($d);
    try {
        $q = db()->prepare("SELECT s.id AS sol_id, s.fase, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum,
                                   t.aanwezig, t.ingecheckt_op, t.afgemeld_reden, t.bevestiging, t.bevestiging_reden, blk.foto_url $groep
                            LEFT JOIN blk_formulieren blk ON blk.sollicitatie_id = s.id
                            WHERE s.seizoen_id = ? AND s.fase IN (" . implode(',', array_fill(0, count($d['kandidaat_fases']), '?')) . ")
                            ORDER BY p.voornaam, p.achternaam");
        $q->execute(array_merge([$seizoen_id], $d['kandidaat_fases']));
        return array_map(function ($r) {
            $r += ['aanwezig' => 0, 'ingecheckt_op' => null, 'afgemeld_reden' => null, 'bevestiging' => 'onbekend',
                   'bevestiging_reden' => null, 'groep_nummer' => null, 'groep_naam' => null, 'foto_url' => null];
            $r['sol_id']   = (int)$r['sol_id'];
            $r['naam']     = trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam']);
            $r['leeftijd'] = !empty($r['geboortedatum']) ? leeftijd_uit_datum($r['geboortedatum']) : null;
            $r['groep']    = ($r['groep_nummer'] ?? null) !== null ? (($r['groep_naam'] ?? '') ?: 'groep ' . $r['groep_nummer']) : null;
            $r['aanwezig'] = (int)$r['aanwezig'] === 1;
            $r['stand']    = $r['aanwezig'] ? 'aanwezig' : (($r['afgemeld_reden'] ?? null) !== null ? 'niet' : 'verwacht');
            return $r;
        }, $q->fetchAll());
    } catch (\Exception $e) { return []; }
}

/**
 * De beoordeellijst in secties: eerst de nieuwe sollicitanten, onderaan de terugkerende
 * lifeguards (Authentik-account, is_lifeguard). Die worden wel ingedeeld en kunnen beoordeeld
 * worden, maar zijn daarvoor minder interessant. De volgorde binnen een sectie blijft staan.
 *
 * @param array $voorop  optioneel: sol_ids die in een eigen sectie bovenaan komen (TW: twijfel
 *                       van het bestuur), met $voorop_titel en $voorop_uitleg
 * @return array [ ['sleutel','titel','uitleg','rijen'] ] — lege secties vallen weg; titel null = geen kop
 */
function evenement_beoordeel_secties(array $lijst, array $voorop = [], string $voorop_titel = '', string $voorop_uitleg = ''): array {
    $s = ['voorop' => [], 'nieuw' => [], 'lifeguard' => []];
    $voor = array_flip(array_map('intval', $voorop));
    foreach ($lijst as $k) {
        $sleutel = isset($voor[(int)$k['sol_id']]) ? 'voorop' : (!empty($k['is_lifeguard']) ? 'lifeguard' : 'nieuw');
        $s[$sleutel][] = $k;
    }
    $uit = [];
    if ($s['voorop'])    $uit[] = ['sleutel' => 'voorop', 'titel' => $voorop_titel, 'uitleg' => $voorop_uitleg, 'rijen' => $s['voorop']];
    if ($s['nieuw'])     $uit[] = ['sleutel' => 'nieuw', 'titel' => $s['voorop'] ? 'Overige sollicitanten' : null, 'uitleg' => '', 'rijen' => $s['nieuw']];
    if ($s['lifeguard']) $uit[] = ['sleutel' => 'lifeguard', 'titel' => 'Terugkerende strandwachten', 'uitleg' => 'Al strandwacht bij de brigade; ze doen mee voor de groepen.', 'rijen' => $s['lifeguard']];
    return $uit;
}

/** De secties weer als één lijst, in dezelfde volgorde (voor vorige/volgende). */
function evenement_secties_plat(array $secties): array {
    $uit = [];
    foreach ($secties as $sec) foreach ($sec['rijen'] as $r) $uit[] = $r;
    return $uit;
}

/** Welke fase had iemand vóór het inchecken? Selectiedag: met ingeleverd BLK "aangemeld", anders "uitgenodigd". */
function evenement_terugfase(string $ev, int $sol_id): string {
    if ($ev === 'tw') return 'uitgenodigd_tw';
    try {
        $q = db()->prepare("SELECT definitief_op FROM blk_formulieren WHERE sollicitatie_id=?");
        $q->execute([$sol_id]);
        return $q->fetchColumn() ? 'aangemeld_selectie' : 'uitgenodigd_selectie';
    } catch (\Exception $e) { return 'uitgenodigd_selectie'; }
}

/** Niet gekomen (met reden). Aanwezig vervalt; de fase blijft staan. */
function evenement_afmelden(string $ev, int $seizoen_id, int $sol_id, string $reden): void {
    $d = evenement_def($ev);
    if (!$d) return;
    evenement_rij_verzeker($ev, $seizoen_id, $sol_id);
    db()->prepare("UPDATE `{$d['tabel']}` SET aanwezig=0, ingecheckt_op=NULL, afgemeld_reden=? WHERE seizoen_id=? AND sollicitatie_id=?")
       ->execute([mb_substr(trim($reden), 0, 200) ?: 'Geen reden opgegeven', $seizoen_id, $sol_id]);
}

/**
 * "Inchecken afsluiten": wie dan nog niet is ingecheckt (en niet al is afgemeld) staat op
 * "niet gekomen" (reden: niet ingecheckt). Een laatkomer kan daarna nog steeds inchecken. Retourneert het aantal.
 */
function evenement_afsluiten(string $ev, int $seizoen_id, int $door): int {
    $d = evenement_def($ev);
    if (!$d) return 0;
    evenement_setup($ev);
    $db = db();
    $ph = implode(',', array_fill(0, count($d['kandidaat_fases']), '?'));
    // Iedereen een rij, zodat ook wie nooit is ingedeeld meetelt.
    $db->prepare("INSERT IGNORE INTO `{$d['tabel']}` (seizoen_id, sollicitatie_id)
                  SELECT seizoen_id, id FROM sollicitaties WHERE seizoen_id=? AND fase IN ($ph)")
       ->execute(array_merge([$seizoen_id], $d['kandidaat_fases']));
    $u = $db->prepare("UPDATE `{$d['tabel']}` t JOIN sollicitaties s ON s.id = t.sollicitatie_id
                       SET t.afgemeld_reden='Niet ingecheckt'
                       WHERE t.seizoen_id=? AND t.ingecheckt_op IS NULL AND t.aanwezig=0 AND t.afgemeld_reden IS NULL
                         AND s.fase IN ($ph)");
    $u->execute(array_merge([$seizoen_id], $d['kandidaat_fases']));
    $n = $u->rowCount();
    if (function_exists('instelling_opslaan')) instelling_opslaan($ev . '.afgesloten.' . $seizoen_id, date('Y-m-d H:i:s'));
    if (function_exists('audit_log')) audit_log('inchecken_afgesloten', 'seizoen', $seizoen_id, ['evenement' => $ev, 'niet_verschenen' => $n, 'door' => $door]);
    return $n;
}

/** Wanneer is het inchecken afgesloten? null = nog open. */
function evenement_afgesloten_op(string $ev, int $seizoen_id): ?string {
    $v = function_exists('instelling') ? (string)instelling($ev . '.afgesloten.' . $seizoen_id, '') : '';
    return $v !== '' ? $v : null;
}

/**
 * Klopt de pincode om op de tablet af te sluiten? De instelling bewaart een hash, nooit de pin
 * zelf. Lege instelling = afsluiten op de tablet kan niet (dan alleen in het beheer).
 */
function evenement_pin_ok(string $ev, string $pin): bool {
    $d = evenement_def($ev);
    $hash = $d && function_exists('instelling') ? (string)instelling($d['pin'], '') : '';
    return $hash !== '' && $pin !== '' && password_verify($pin, $hash);
}

/**
 * Zoeken op de tablet: minimaal 3 tekens, hooguit 3 treffers, mét miniatuur. Nooit een lijst
 * van iedereen: de tablet staat bij de ingang, voor iedereen zichtbaar.
 */
function evenement_zoek(string $ev, int $seizoen_id, string $term): array {
    $d = evenement_def($ev);
    $term = trim($term);
    if (!$d || mb_strlen($term) < 3) return [];
    evenement_setup($ev);
    $groep = evenement_from_met_groep($d);
    try {
        $q = db()->prepare("SELECT s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam, t.aanwezig, blk.foto_url $groep
                            LEFT JOIN blk_formulieren blk ON blk.sollicitatie_id = s.id
                            WHERE s.seizoen_id = ? AND s.fase IN (" . implode(',', array_fill(0, count($d['kandidaat_fases']), '?')) . ")
                              AND CONCAT_WS(' ', p.voornaam, p.tussenvoegsel, p.achternaam) LIKE ?
                            ORDER BY p.voornaam, p.achternaam LIMIT 3");
        $q->execute(array_merge([$seizoen_id], $d['kandidaat_fases'], ['%' . $term . '%']));
        return array_map(function ($r) {
            return [
                'sol_id' => (int)$r['sol_id'],
                'naam' => trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam']),
                'initialen' => mb_strtoupper(mb_substr((string)$r['voornaam'], 0, 1) . mb_substr((string)$r['achternaam'], 0, 1)),
                'aanwezig' => (int)$r['aanwezig'] === 1,
                'groep_nummer' => ($r['groep_nummer'] ?? null) !== null ? (int)$r['groep_nummer'] : null,
                'groep_naam' => $r['groep_naam'] ?? null,
                'foto' => function_exists('foto_miniatuur_data_url') ? foto_miniatuur_data_url($r['foto_url'] ?? null) : null,
            ];
        }, $q->fetchAll());
    } catch (\Exception $e) { return []; }
}

// ─────────────────────────────────────────────────────────────
//  "Ik kom / ik kan niet"
// ─────────────────────────────────────────────────────────────

function evenement_bevestig_standen(): array {
    return ['onbekend' => 'Nog niet bekend', 'komt' => 'Komt', 'komt_niet' => 'Kan niet'];
}

/**
 * Zoeken en filteren in een deelnemerslijst (Voorbereiden, beoordelen): op naam en op bevestiging.
 * Dezelfde regel voor selectiedag en trainingsweekend. Geen HTML.
 * @param string $q      deel van de naam (hoofdletterongevoelig); leeg = iedereen
 * @param string $stand  een sleutel uit evenement_bevestig_standen(); leeg = alle
 */
function evenement_lijst_filter(array $lijst, string $q = '', string $stand = ''): array {
    $q = function_exists('mb_strtolower') ? mb_strtolower(trim($q)) : strtolower(trim($q));
    return array_values(array_filter($lijst, function ($k) use ($q, $stand) {
        if ($q !== '') {
            $naam = (string)($k['naam'] ?? '');
            $naam = function_exists('mb_strtolower') ? mb_strtolower($naam) : strtolower($naam);
            if (strpos($naam, $q) === false) return false;
        }
        if ($stand !== '') {
            $st = (string)($k['bevestiging'] ?? 'onbekend');
            if (!isset(evenement_bevestig_standen()[$st])) $st = 'onbekend';
            if ($st !== $stand) return false;
        }
        return true;
    }));
}

/**
 * Bevestiging vastleggen, met een opmerking (kolom bevestiging_reden). Bij "kan niet" is die
 * verplicht; bij "ik kom" mag hij er ook staan ("ik ben er pas om 14 uur") en wordt hij bewaard —
 * tot 2.117.0 viel hij dan stil weg. De fase verandert niet: het bestuur beslist op Vandaag
 * (afmelden of uitzondering). Komt de afmelding uit het portaal, dan krijgt het bestuur bericht.
 *
 * @param string $bron  portaal | bellijst | beheer
 * @return bool  false bij een ongeldige stand of een ontbrekende reden
 */
function evenement_bevestigen(string $ev, int $seizoen_id, int $sol_id, string $stand, string $reden, string $bron): bool {
    $d = evenement_def($ev);
    if (!$d || !isset(evenement_bevestig_standen()[$stand])) return false;
    $reden = mb_substr(trim($reden), 0, 300);
    if ($stand === 'komt_niet' && $reden === '') return false;
    evenement_rij_verzeker($ev, $seizoen_id, $sol_id);
    db()->prepare("UPDATE `{$d['tabel']}` SET bevestiging=?, bevestiging_reden=?, bevestiging_bron=?, bevestigd_op=NOW()
                   WHERE seizoen_id=? AND sollicitatie_id=?")
       ->execute([$stand, $reden !== '' ? $reden : null, $bron, $seizoen_id, $sol_id]);
    if (function_exists('audit_log')) audit_log('deelname_bevestigd', 'sollicitatie', $sol_id, ['evenement' => $ev, 'stand' => $stand, 'bron' => $bron]);
    if ($stand === 'komt_niet' && $bron === 'portaal' && function_exists('melding_verstuur')) {
        melding_verstuur('bestuur_deelname_afgemeld', $sol_id, [
            'evenement' => $d['label'], 'reden' => $reden,
            'beheer_url' => rtrim(APP_URL, '/') . '/admin/sollicitant.php?id=' . $sol_id,
        ], ['prioriteit' => 'na_antwoord']);
    }
    return true;
}

/** De bevestiging van één persoon: ['stand','reden','bron','op'] (stand 'onbekend' als er niets is). */
function evenement_bevestiging_van(string $ev, int $seizoen_id, int $sol_id): array {
    $d = evenement_def($ev);
    $leeg = ['stand' => 'onbekend', 'reden' => null, 'bron' => null, 'op' => null, 'uitzondering' => false];
    if (!$d) return $leeg;
    try {
        $q = db()->prepare("SELECT bevestiging, bevestiging_reden, bevestiging_bron, bevestigd_op FROM `{$d['tabel']}` WHERE seizoen_id=? AND sollicitatie_id=?");
        $q->execute([$seizoen_id, $sol_id]);
        $r = $q->fetch();
        if (!$r) return $leeg;
        return ['stand' => (string)($r['bevestiging'] ?: 'onbekend'), 'reden' => $r['bevestiging_reden'],
                'bron' => $r['bevestiging_bron'], 'op' => $r['bevestigd_op'], 'uitzondering' => $r['bevestiging_bron'] === 'uitzondering'];
    } catch (\Exception $e) { return $leeg; }
}

/**
 * Wie gaf "kan niet" door en wacht op een besluit van het bestuur (afmelden of uitzondering)?
 * Voor Vandaag › actie nodig. [ ['sol_id','naam','evenement','reden'] ]
 */
function evenement_afmeldingen_open(int $seizoen_id): array {
    $uit = [];
    foreach (['selectiedag', 'tw'] as $ev) {
        $d = evenement_def($ev);
        if (function_exists('module_aan') && !module_aan($d['module'])) continue;
        try {
            evenement_setup($ev);
            $q = db()->prepare("SELECT s.id, p.voornaam, p.tussenvoegsel, p.achternaam, t.bevestiging_reden
                                FROM `{$d['tabel']}` t JOIN sollicitaties s ON s.id = t.sollicitatie_id JOIN personen p ON p.id = s.persoon_id
                                WHERE t.seizoen_id=? AND t.bevestiging='komt_niet'
                                  AND s.fase IN (" . implode(',', array_fill(0, count($d['voor_fases']), '?')) . ")");
            $q->execute(array_merge([$seizoen_id], $d['voor_fases']));
            foreach ($q->fetchAll() as $r) {
                $uit[] = ['sol_id' => (int)$r['id'], 'evenement' => $ev, 'label' => $d['label'], 'reden' => $r['bevestiging_reden'],
                          'naam' => trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam'])];
            }
        } catch (\Exception $e) {}
    }
    return $uit;
}

/**
 * Het bestuur beslist over een afmelding: 'afmelden' (fase afgehaakt, via fase_zetten) of
 * 'uitzondering' (blijft in het proces; de bevestiging gaat op "komt" met bron uitzondering).
 * In beide gevallen krijgt de sollicitant bericht (deelname_afgemeld / deelname_uitzondering):
 * het portaal belooft na "ik kan niet" dat het bestuur laat weten wat het besluit is.
 */
function evenement_afmelding_besluiten(string $ev, int $seizoen_id, int $sol_id, string $keuze): bool {
    if (!evenement_is_kandidaat($ev, $seizoen_id, $sol_id)) return false;
    $d = evenement_def($ev);
    $meld = function (string $type) use ($d, $sol_id) {
        if (function_exists('melding_verstuur')) melding_verstuur($type, $sol_id, ['evenement' => $d['label']], ['prioriteit' => 'na_antwoord']);
    };
    if ($keuze === 'afmelden') {
        // De fase-overgang zelf blijft stil (afgehaakt heeft geen fase-melding); het besluit gaat apart.
        $r = function_exists('fase_zetten') ? fase_zetten($sol_id, 'afgehaakt', 'paneel', ['seizoen_id' => $seizoen_id, 'alleen_van' => $d['voor_fases'], 'stil' => true]) : ['ok' => false];
        if (!empty($r['ok']) && empty($r['ongewijzigd'])) $meld('deelname_afgemeld');
        return !empty($r['ok']);
    }
    if ($keuze === 'uitzondering') {
        db()->prepare("UPDATE `{$d['tabel']}` SET bevestiging='komt', bevestiging_bron='uitzondering', bevestigd_op=NOW() WHERE seizoen_id=? AND sollicitatie_id=?")
           ->execute([$seizoen_id, $sol_id]);
        if (function_exists('audit_log')) audit_log('afmelding_uitzondering', 'sollicitatie', $sol_id, ['evenement' => $ev]);
        $meld('deelname_uitzondering');
        return true;
    }
    return false;
}

// ─────────────────────────────────────────────────────────────
//  Vergrendeling "bezig door X" (bellijst en gesprekken), 30 minuten
// ─────────────────────────────────────────────────────────────

/**
 * Probeer een persoon te claimen. Lukt als niemand anders hem in de laatste 30 minuten claimde
 * (of als jij het zelf al was). Atomair: één UPDATE met de voorwaarde erin, dus twee mensen
 * die tegelijk klikken kunnen niet allebei winnen.
 *
 * @param string $soort 'bel' (kolommen bel_door/bel_sinds) of 'gesprek' (gesprek_door/gesprek_sinds)
 * @return array ['ok' => bool, 'door' => ?int (wie hem heeft), 'sinds' => ?string]
 */
function evenement_claim(string $tabel, string $soort, int $seizoen_id, int $sol_id, int $beheerder_id): array {
    if (!in_array($tabel, ['selectie_besluit', 'tw_deelname'], true) || !in_array($soort, ['bel', 'gesprek'], true)) return ['ok' => false, 'door' => null, 'sinds' => null];
    $door = $soort . '_door'; $sinds = $soort . '_sinds';
    $db = db();
    $u = $db->prepare("UPDATE `$tabel` SET `$door`=?, `$sinds`=NOW()
                       WHERE seizoen_id=? AND sollicitatie_id=?
                         AND (`$door` IS NULL OR `$door`=? OR `$sinds` < NOW() - INTERVAL 30 MINUTE)");
    $u->execute([$beheerder_id, $seizoen_id, $sol_id, $beheerder_id]);
    $q = $db->prepare("SELECT `$door` AS door, `$sinds` AS sinds FROM `$tabel` WHERE seizoen_id=? AND sollicitatie_id=?");
    $q->execute([$seizoen_id, $sol_id]);
    $r = $q->fetch() ?: [];
    $wie = isset($r['door']) ? (int)$r['door'] : null;
    // Niemand houdt hem vast (bijv. geen rij) = vrij; alleen een ánder die hem heeft blokkeert.
    return ['ok' => $wie === null || $wie === $beheerder_id, 'door' => $wie, 'sinds' => $r['sinds'] ?? null];
}

/** Vergrendeling loslaten (klaar, of per ongeluk geclaimd). Alleen je eigen claim. */
function evenement_claim_los(string $tabel, string $soort, int $seizoen_id, int $sol_id, int $beheerder_id): void {
    if (!in_array($tabel, ['selectie_besluit', 'tw_deelname'], true) || !in_array($soort, ['bel', 'gesprek'], true)) return;
    db()->prepare("UPDATE `$tabel` SET `{$soort}_door`=NULL, `{$soort}_sinds`=NULL WHERE seizoen_id=? AND sollicitatie_id=? AND `{$soort}_door`=?")
       ->execute([$seizoen_id, $sol_id, $beheerder_id]);
}

/**
 * Al je andere claims van deze soort in dit seizoen loslaten. Je belt of spreekt één persoon
 * tegelijk: wie naar een ander gaat (of terug naar het overzicht, $behalve = 0), houdt de vorige
 * niet nog 30 minuten vast voor collega's.
 */
function evenement_claim_los_overige(string $tabel, string $soort, int $seizoen_id, int $beheerder_id, int $behalve = 0): void {
    if (!in_array($tabel, ['selectie_besluit', 'tw_deelname'], true) || !in_array($soort, ['bel', 'gesprek'], true)) return;
    db()->prepare("UPDATE `$tabel` SET `{$soort}_door`=NULL, `{$soort}_sinds`=NULL WHERE seizoen_id=? AND `{$soort}_door`=? AND sollicitatie_id<>?")
       ->execute([$seizoen_id, $beheerder_id, $behalve]);
}

/**
 * Een claim van een ander bewust overnemen (bijv. iemand liep weg met het gesprek open).
 * Alleen als de claim nog bij $van ligt — de persoon die in de bevestiging werd genoemd. Heeft
 * intussen een derde hem, dan gebeurt er niets (je bevestigde iets anders). Geaudit.
 *
 * @return array ['ok' => bool, 'door' => ?int (wie hem nu heeft), 'sinds' => ?string]
 */
function evenement_claim_overnemen(string $tabel, string $soort, int $seizoen_id, int $sol_id, int $beheerder_id, int $van): array {
    if (!in_array($tabel, ['selectie_besluit', 'tw_deelname'], true) || !in_array($soort, ['bel', 'gesprek'], true) || $van <= 0) {
        return ['ok' => false, 'door' => null, 'sinds' => null];
    }
    $door = $soort . '_door'; $sinds = $soort . '_sinds';
    $db = db();
    $u = $db->prepare("UPDATE `$tabel` SET `$door`=?, `$sinds`=NOW() WHERE seizoen_id=? AND sollicitatie_id=? AND `$door`=?");
    $u->execute([$beheerder_id, $seizoen_id, $sol_id, $van]);
    $gelukt = $u->rowCount() > 0;
    $q = $db->prepare("SELECT `$door` AS door, `$sinds` AS sinds FROM `$tabel` WHERE seizoen_id=? AND sollicitatie_id=?");
    $q->execute([$seizoen_id, $sol_id]);
    $r = $q->fetch() ?: [];
    $wie = isset($r['door']) ? (int)$r['door'] : null;
    if ($gelukt && function_exists('audit_log')) {
        audit_log('claim_overgenomen', 'sollicitatie', $sol_id, ['tabel' => $tabel, 'soort' => $soort, 'van' => $van, 'door' => $beheerder_id]);
    }
    return ['ok' => $wie === $beheerder_id, 'door' => $wie, 'sinds' => $r['sinds'] ?? null];
}

/** De naam van wie een claim heeft, voor "bezig door X". */
function evenement_claim_naam(?int $beheerder_id): string {
    if (!$beheerder_id) return '';
    static $cache = [];
    if (isset($cache[$beheerder_id])) return $cache[$beheerder_id];
    try {
        $q = db()->prepare("SELECT naam FROM beheerders WHERE id=?");
        $q->execute([$beheerder_id]);
        return $cache[$beheerder_id] = (string)($q->fetchColumn() ?: 'een ander bestuurslid');
    } catch (\Exception $e) { return 'een ander bestuurslid'; }
}

/** Is de claim nog geldig voor iemand anders? Voor de weergave "bezig door X". */
function evenement_claim_actief(?string $door, ?string $sinds, int $ik): bool {
    return $door !== null && (int)$door !== $ik && $sinds !== null && strtotime($sinds) > time() - 1800;
}

}

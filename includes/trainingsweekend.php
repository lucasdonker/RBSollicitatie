<?php
/**
 * Paal 13 — Trainingsweekend.
 *
 * Het trainingsweekend is de laatste stap vóór aannemen: een weekend meedraaien,
 * waarna het bestuur per kandidaat besluit. Dit bestand is het datamodel; geen HTML.
 *
 * Waarom eigen tabellen en niet die van de selectiedag: de selectiedag heeft per
 * kandidaat al een rij in selectie_indeling, selectie_observatie en selectie_besluit.
 * Diezelfde rijen hergebruiken zou de twee momenten over elkaar heen schrijven — je
 * zou de observatie van de selectiedag overschrijven met die van het weekend, en het
 * dossier kan dan niet meer laten zien wat wanneer is gezegd. Twee momenten, twee sets.
 *
 * De vorm is bewust gelijk aan die van de selectiedag, zodat de schermen op elkaar
 * lijken en er later één gedeelde laag onder kan.
 *
 * Wat hier NIET zit: het versturen van berichten en het zetten van de fase. Dat doet
 * fase_zetten() in includes/fase.php — één plek die weet wie er bericht krijgt.
 */

if (!function_exists('tw_auto_setup')) {

function tw_auto_setup(): void {
    static $done = false;
    if ($done || schema_actueel()) return;
    $done = true;
    try {
        $db = db();

        // Aanwezigheid én besluit: beide zijn precies één waarde per kandidaat per
        // seizoen, dus één rij. Een aparte besluittabel zou alleen maar een join kosten.
        $db->exec("CREATE TABLE IF NOT EXISTS `tw_deelname` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `sollicitatie_id` INT NOT NULL,
            `aanwezig` TINYINT(1) NOT NULL DEFAULT 0,
            `ingecheckt_op` DATETIME DEFAULT NULL,
            `afgemeld_reden` VARCHAR(200) DEFAULT NULL,
            `besluit` ENUM('aannemen','twijfel','afwijzen') DEFAULT NULL,
            `besluit_notitie` VARCHAR(300) DEFAULT NULL,
            `besluit_door` VARCHAR(100) DEFAULT NULL,
            `besluit_op` DATETIME DEFAULT NULL,
            `doorgevoerd_op` DATETIME DEFAULT NULL,
            `bijgewerkt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniek_sol` (`seizoen_id`,`sollicitatie_id`),
            INDEX (`seizoen_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Observaties: vrije notities, meerdere per kandidaat, van iedereen die
        // meedraait. Zelfde soorten als op de selectiedag.
        $db->exec("CREATE TABLE IF NOT EXISTS `tw_observatie` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `sollicitatie_id` INT NOT NULL,
            `tekst` TEXT NOT NULL,
            `soort` ENUM('neutraal','positief','aandacht') NOT NULL DEFAULT 'neutraal',
            `beoordelaar` VARCHAR(100) NOT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 2.108.0: bevestiging, het gesprek met het bestuur (vergrendeling + bericht aan de
        // sollicitant, los van de interne besluit_notitie), bellijst-vergrendeling, beoordelaar-id.
        foreach (['bevestiging' => "VARCHAR(12) NOT NULL DEFAULT 'onbekend'", 'bevestiging_reden' => 'VARCHAR(300) DEFAULT NULL',
                  'bevestiging_bron' => 'VARCHAR(12) DEFAULT NULL', 'bevestigd_op' => 'DATETIME DEFAULT NULL',
                  'gesprek_status' => 'VARCHAR(10) DEFAULT NULL', 'gesprek_door' => 'INT DEFAULT NULL',
                  'gesprek_sinds' => 'DATETIME DEFAULT NULL', 'gesprek_bericht' => 'TEXT DEFAULT NULL',
                  'bel_door' => 'INT DEFAULT NULL', 'bel_sinds' => 'DATETIME DEFAULT NULL'] as $kol => $def) {
            db_kolom_verzeker($db, 'tw_deelname', $kol, $def);
        }
        db_kolom_verzeker($db, 'tw_observatie', 'beoordelaar_id', 'INT DEFAULT NULL');

        // 2.117.0: groepen, net als op de selectiedag (includes/evenement_groepen.php).
        $db->exec("CREATE TABLE IF NOT EXISTS `tw_groep` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `nummer` SMALLINT NOT NULL,
            `naam` VARCHAR(60) DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniek_nr` (`seizoen_id`,`nummer`),
            INDEX (`seizoen_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db_kolom_verzeker($db, 'tw_deelname', 'groep_id', 'INT DEFAULT NULL');

        // 2.118.0: de evaluatie van het bestuur tijdens het weekend (tw_evaluatie_zetten()).
        // VARCHAR, geen ENUM: een onbekende waarde wordt dan geen stille lege string.
        foreach (['evaluatie' => 'VARCHAR(10) DEFAULT NULL', 'evaluatie_bericht' => 'TEXT DEFAULT NULL',
                  'evaluatie_door' => 'VARCHAR(100) DEFAULT NULL', 'evaluatie_op' => 'DATETIME DEFAULT NULL'] as $kol => $def) {
            db_kolom_verzeker($db, 'tw_deelname', $kol, $def);
        }
    } catch (\Exception $e) {
        error_log('tw_auto_setup: ' . $e->getMessage());
    }
}

/**
 * De kandidaten voor het trainingsweekend.
 *
 * Wie al op 'aangenomen' of 'afgewezen' staat is klaar en hoort er niet meer bij —
 * behalve als het besluit hier vandaan kwam, want dan wil je het nog kunnen terugzien.
 */
function tw_kandidaten(int $seizoen_id): array {
    tw_auto_setup();
    $lg = function_exists('authentik_lifeguard_sql') ? authentik_lifeguard_sql('s.id') : '0';
    try {
        $q = db()->prepare("
            SELECT s.id AS sol_id, s.fase, p.voornaam, p.tussenvoegsel, p.achternaam,
                   p.geboortedatum, p.geslacht, $lg AS is_lifeguard,
                   d.aanwezig, d.ingecheckt_op, d.afgemeld_reden, d.groep_id,
                   d.besluit, d.besluit_notitie, d.besluit_door, d.besluit_op, d.doorgevoerd_op,
                   d.bevestiging, d.bevestiging_reden, d.gesprek_status, d.gesprek_door, d.gesprek_sinds, d.gesprek_bericht,
                   d.evaluatie, d.evaluatie_bericht, d.evaluatie_door, d.evaluatie_op,
                   blk.foto_url, blk.bijzonderheden
            FROM sollicitaties s
            JOIN personen p ON p.id = s.persoon_id
            LEFT JOIN tw_deelname d ON d.sollicitatie_id = s.id AND d.seizoen_id = s.seizoen_id
            LEFT JOIN blk_formulieren blk ON blk.sollicitatie_id = s.id
            WHERE s.seizoen_id = ?
              AND (s.fase IN ('uitgenodigd_tw','tw_geweest') OR d.doorgevoerd_op IS NOT NULL)
            ORDER BY p.achternaam, p.voornaam
        ");
        $q->execute([$seizoen_id]);
        return array_map(function ($r) {
            $r['naam'] = trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam']);
            $r['leeftijd'] = $r['geboortedatum']
                ? (int)(new DateTime($r['geboortedatum']))->diff(new DateTime())->y
                : null;
            return $r;
        }, $q->fetchAll());
    } catch (\Exception $e) {
        return [];
    }
}

/** De deelnamerij van één sollicitatie (of null), voor het dossier. */
function tw_deelname_van(int $seizoen_id, int $sol_id): ?array {
    tw_auto_setup();
    try {
        $q = db()->prepare("SELECT * FROM tw_deelname WHERE seizoen_id=? AND sollicitatie_id=?");
        $q->execute([$seizoen_id, $sol_id]);
        return $q->fetch() ?: null;
    } catch (\Exception $e) { return null; }
}

/** Zorgt dat er een deelnamerij is, en geeft hem terug. */
function tw_rij_verzeker(int $seizoen_id, int $sol_id): void {
    tw_auto_setup();
    db()->prepare("INSERT IGNORE INTO tw_deelname (seizoen_id, sollicitatie_id) VALUES (?,?)")
        ->execute([$seizoen_id, $sol_id]);
}

/**
 * Aanwezig of niet. Bij afwezig mag een reden mee — dat is precies wat je in de
 * nabespreking wilt weten ("ziek" is iets anders dan "niet komen opdagen").
 */
function tw_aanwezig_zetten(int $seizoen_id, int $sol_id, bool $aanwezig, ?string $reden = null, string $bron = 'beheer'): void {
    // Inchecken zet nu ook de fase tw_geweest (includes/evenement.php); die werd nergens gezet.
    if ($aanwezig)                        evenement_inchecken('tw', $seizoen_id, $sol_id, true, $bron);
    elseif (trim((string)$reden) !== '')  { evenement_inchecken('tw', $seizoen_id, $sol_id, false, $bron); evenement_afmelden('tw', $seizoen_id, $sol_id, (string)$reden); }
    else                                  evenement_inchecken('tw', $seizoen_id, $sol_id, false, $bron);
}

/** Observaties van één kandidaat, nieuwste eerst. */
function tw_observaties_van(int $seizoen_id, int $sol_id): array {
    tw_auto_setup();
    try {
        $q = db()->prepare("SELECT * FROM tw_observatie
                            WHERE seizoen_id=? AND sollicitatie_id=? ORDER BY aangemaakt_op DESC, id DESC");
        $q->execute([$seizoen_id, $sol_id]);
        return $q->fetchAll();
    } catch (\Exception $e) { return []; }
}

/** Alle observaties van het seizoen, gegroepeerd per kandidaat. */
function tw_observaties_alle(int $seizoen_id): array {
    tw_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT * FROM tw_observatie WHERE seizoen_id=? ORDER BY aangemaakt_op DESC, id DESC");
        $q->execute([$seizoen_id]);
        foreach ($q->fetchAll() as $o) $uit[(int)$o['sollicitatie_id']][] = $o;
    } catch (\Exception $e) {}
    return $uit;
}

function tw_observatie_toevoegen(int $seizoen_id, int $sol_id, string $tekst, string $soort, string $door, int $beoordelaar_id = 0): bool {
    $tekst = trim($tekst);
    if ($tekst === '') return false;
    if (!in_array($soort, ['neutraal', 'positief', 'aandacht'], true)) $soort = 'neutraal';
    tw_auto_setup();
    try {
        db()->prepare("INSERT INTO tw_observatie (seizoen_id, sollicitatie_id, tekst, soort, beoordelaar, beoordelaar_id)
                       VALUES (?,?,?,?,?,?)")
            ->execute([$seizoen_id, $sol_id, mb_substr($tekst, 0, 2000), $soort, mb_substr($door, 0, 100), $beoordelaar_id ?: null]);
        return true;
    } catch (\Exception $e) { return false; }
}

/**
 * Een observatie wissen. Alleen de eigen observatie; wat een ander zag blijft staan.
 * Eigen = zelfde beoordelaar-id; oude rijen zonder id vallen terug op de naam.
 */
function tw_observatie_wissen(int $obs_id, string $door, int $beoordelaar_id = 0): bool {
    tw_auto_setup();
    try {
        $q = db()->prepare("DELETE FROM tw_observatie WHERE id=?
                            AND (beoordelaar_id=? OR (beoordelaar_id IS NULL AND beoordelaar=?))");
        $q->execute([$obs_id, $beoordelaar_id, $door]);
        return $q->rowCount() > 0;
    } catch (\Exception $e) { return false; }
}

/** Is dit mijn observatie? (Voor de wisknop; de server controleert bij het wissen opnieuw.) */
function tw_observatie_is_eigen(array $o, string $door, int $beoordelaar_id): bool {
    return !empty($o['beoordelaar_id']) ? (int)$o['beoordelaar_id'] === $beoordelaar_id : ($o['beoordelaar'] ?? '') === $door;
}

/** [sol_id => true] voor wie van dit seizoen al beschikbaarheid opgaf. Eén query, voor de stap Voorbereiden. */
function tw_met_beschikbaarheid(int $seizoen_id): array {
    try {
        $q = db()->prepare("SELECT DISTINCT b.sollicitatie_id FROM beschikbaarheid_blok b
                            JOIN sollicitaties s ON s.id = b.sollicitatie_id WHERE s.seizoen_id=?");
        $q->execute([$seizoen_id]);
        $uit = [];
        foreach ($q->fetchAll() as $r) $uit[(int)$r['sollicitatie_id']] = true;
        return $uit;
    } catch (\Exception $e) { return []; }
}

/** Het besluit vastleggen. Nog niet doorvoeren — dat is een aparte, bewuste stap. */
function tw_besluit_zetten(int $seizoen_id, int $sol_id, ?string $besluit, ?string $notitie, string $door): void {
    if ($besluit !== null && !in_array($besluit, ['aannemen', 'twijfel', 'afwijzen'], true)) $besluit = null;
    tw_rij_verzeker($seizoen_id, $sol_id);
    db()->prepare("UPDATE tw_deelname
                   SET besluit=?, besluit_notitie=?, besluit_door=?, besluit_op=?, bijgewerkt_op=NOW()
                   WHERE seizoen_id=? AND sollicitatie_id=?")
        ->execute([
            $besluit,
            $besluit === null ? null : (mb_substr(trim((string)$notitie), 0, 300) ?: null),
            $besluit === null ? null : mb_substr($door, 0, 100),
            $besluit === null ? null : date('Y-m-d H:i:s'),
            $seizoen_id, $sol_id,
        ]);
}

/** Markeer dat het besluit is doorgevoerd (de fase is gezet en het bericht is eruit). */
function tw_doorgevoerd_markeren(int $seizoen_id, int $sol_id): void {
    tw_rij_verzeker($seizoen_id, $sol_id);
    db()->prepare("UPDATE tw_deelname SET doorgevoerd_op=NOW() WHERE seizoen_id=? AND sollicitatie_id=?")
        ->execute([$seizoen_id, $sol_id]);
}

/**
 * De stand van het weekend in getallen.
 *
 * 'te_beslissen'            — nog geen besluit, ongeacht de aanwezigheid. Ook wie niet is
 *                             afgevinkt of niet kwam heeft een besluit nodig; telden alleen
 *                             aanwezigen, dan zei het stoplicht "alles besloten" terwijl er
 *                             nog kandidaten zonder besluit in uitgenodigd_tw stonden.
 * 'klaar_om_door_te_voeren' — besluiten die ergens toe leiden en nog niet zijn uitgevoerd.
 * 'twijfel_open'            — vastgelegd als twijfel, dus wachtend op een telefoontje.
 *
 * LET OP: twijfel telt níét mee in 'klaar_om_door_te_voeren'. tw_besluit_fase() geeft
 * daar null terug, dus doorvoeren doet er niets mee. Zou twijfel wél meetellen, dan
 * zegt de knop "3 besluiten doorvoeren" en gebeurt er daarna niets — het ergste soort
 * knop: een die belooft en zwijgt.
 */
function tw_stand(array $kandidaten): array {
    $s = ['totaal' => count($kandidaten), 'aanwezig' => 0, 'afwezig' => 0, 'onbekend' => 0,
          'besloten' => 0, 'te_beslissen' => 0, 'klaar_om_door_te_voeren' => 0, 'twijfel_open' => 0,
          'aannemen' => 0, 'twijfel' => 0, 'afwijzen' => 0, 'doorgevoerd' => 0];
    foreach ($kandidaten as $k) {
        $ingecheckt = $k['ingecheckt_op'] !== null || $k['afgemeld_reden'] !== null;
        if (!empty($k['aanwezig']))      $s['aanwezig']++;
        elseif ($ingecheckt)             $s['afwezig']++;
        else                             $s['onbekend']++;

        $besluit = (string)($k['besluit'] ?? '');
        if ($besluit !== '') {
            $s[$besluit]++;
            $s['besloten']++;
            if (empty($k['doorgevoerd_op'])) {
                if (tw_besluit_fase($besluit) !== null) $s['klaar_om_door_te_voeren']++;
                else                                    $s['twijfel_open']++;
            }
        } else {
            $s['te_beslissen']++;
        }
        if (!empty($k['doorgevoerd_op'])) $s['doorgevoerd']++;
    }
    return $s;
}

/** Naar welke fase leidt een besluit? Twijfel leidt nergens heen: daar moet iemand over bellen. */
function tw_besluit_fase(string $besluit): ?string {
    return ['aannemen' => 'aangenomen', 'afwijzen' => 'afgewezen'][$besluit] ?? null;
}

// ─────────────────────────────────────────────────────────────
//  De evaluatie van het bestuur (stap Evaluatie), tijdens het weekend
// ─────────────────────────────────────────────────────────────
//
// Na een dag beoordelen zegt het bestuur alvast wat het vindt: sowieso aannemen, sowieso
// afwijzen, of twijfel. Dat is geen besluit (dat valt in het gesprek) en gaat nergens heen.
// Het doel: de beoordelaars kijken de rest van het weekend vooral naar de twijfelgevallen.
// Beoordelaars zien daarom ALLEEN wie op twijfel staat ("extra aandacht gevraagd", met de
// vraag van het bestuur); aannemen of afwijzen zien ze nooit — dat zou hun blik kleuren.

/** De keuzes, in de volgorde van het scherm. */
function tw_evaluatie_standen(): array {
    return ['aannemen' => 'Sowieso aannemen', 'twijfel' => 'Twijfel', 'afwijzen' => 'Sowieso afwijzen'];
}

/**
 * De evaluatie van één persoon vastleggen of wissen ($stand leeg).
 * Het bericht is een vraag aan de beoordelaars en telt alleen bij twijfel; bij de andere
 * keuzes wordt het niet bewaard (anders zou het bij een beoordelaar kunnen opduiken).
 */
function tw_evaluatie_zetten(int $seizoen_id, int $sol_id, string $stand, string $bericht, string $door): bool {
    if ($stand !== '' && !isset(tw_evaluatie_standen()[$stand])) return false;
    tw_rij_verzeker($seizoen_id, $sol_id);
    $bericht = $stand === 'twijfel' ? mb_substr(trim($bericht), 0, 500) : '';
    db()->prepare("UPDATE tw_deelname SET evaluatie=?, evaluatie_bericht=?, evaluatie_door=?, evaluatie_op=?
                   WHERE seizoen_id=? AND sollicitatie_id=?")
       ->execute([$stand !== '' ? $stand : null, $bericht !== '' ? $bericht : null,
                  $stand !== '' ? mb_substr($door, 0, 100) : null, $stand !== '' ? date('Y-m-d H:i:s') : null,
                  $seizoen_id, $sol_id]);
    if (function_exists('audit_log')) audit_log('tw_evaluatie', 'sollicitatie', $sol_id, ['stand' => $stand !== '' ? $stand : 'gewist']);
    return true;
}

/** Wie staat op twijfel en is nog niet doorgevoerd? [sol_id, …] — voor de beoordeellijst. */
function tw_twijfel_ids(array $kandidaten): array {
    return array_values(array_map(fn($k) => (int)$k['sol_id'],
        array_filter($kandidaten, fn($k) => ($k['evaluatie'] ?? '') === 'twijfel' && empty($k['doorgevoerd_op']))));
}

// ─────────────────────────────────────────────────────────────
//  Het gesprek met het bestuur (stap Gesprekken)
// ─────────────────────────────────────────────────────────────

/** De standaardtekst als het bestuur in het gesprek geen eigen bericht invult (Instellingen › Beoordelingscriteria). */
function tw_standaard_bericht(string $besluit): string {
    $standaard = [
        'aannemen' => 'Fijn dat je erbij bent! In je portaal staan een paar formulieren klaar; vul die zo snel mogelijk in.',
        'afwijzen' => 'We hebben je inzet dit weekend gezien en waarderen die. Dank je wel, en misschien tot een volgend seizoen.',
    ];
    $sleutel = $besluit === 'aannemen' ? 'tw.bericht_aangenomen' : 'tw.bericht_afgewezen';
    $v = function_exists('instelling') ? trim((string)instelling($sleutel, '')) : '';
    return $v !== '' ? $v : ($standaard[$besluit] ?? '');
}

/**
 * Het gesprek afronden: besluit vastleggen en — bij aannemen of afwijzen — meteen doorvoeren.
 * Aangenomen krijgt daardoor direct het kennismakingspakket (de formuliertaken) in het portaal.
 * Het bericht aan de sollicitant gaat als {{gesprek_bericht}} mee in de mail; leeg = de
 * standaardtekst. De interne notitie blijft intern. Twijfel: geen fase, geen mail — die komt
 * op de bellijst voor na het weekend.
 *
 * @return array ['ok' => bool, 'fase' => ?string, 'melding' => string]
 */
function tw_gesprek_afronden(int $seizoen_id, int $sol_id, string $besluit, string $notitie, string $bericht, string $door, int $beheerder_id): array {
    if (!in_array($besluit, ['aannemen', 'twijfel', 'afwijzen'], true)) return ['ok' => false, 'fase' => null, 'melding' => 'Kies een besluit.'];
    tw_besluit_zetten($seizoen_id, $sol_id, $besluit, $notitie, $door);
    $bericht = mb_substr(trim($bericht), 0, 2000);
    db()->prepare("UPDATE tw_deelname SET gesprek_status='gehad', gesprek_bericht=?, gesprek_door=NULL, gesprek_sinds=NULL
                   WHERE seizoen_id=? AND sollicitatie_id=?")
       ->execute([$bericht !== '' ? $bericht : null, $seizoen_id, $sol_id]);
    if (function_exists('audit_log')) audit_log('tw_gesprek', 'sollicitatie', $sol_id, ['besluit' => $besluit, 'door' => $beheerder_id]);

    // Wat in het gesprek over de inzet is besproken, staat daarna vast in alle scenario's
    // (includes/loonplan_slot.php). Bij afwijzen valt de persoon uit de planning; niets vast te zetten.
    if ($besluit !== 'afwijzen' && function_exists('loonplan_vastzetten') && (!function_exists('module_aan') || module_aan('loonplanning'))) {
        $sc = loonplan_actief_scenario($seizoen_id);
        if ($sc) loonplan_vastzetten($seizoen_id, $sol_id, (int)$sc['id'], $beheerder_id);
    }

    $naar = tw_besluit_fase($besluit);
    if ($naar === null) return ['ok' => true, 'fase' => null, 'melding' => 'Vastgelegd als twijfel: bel na het weekend.'];
    $res = fase_zetten($sol_id, $naar, 'gesprek', [
        'seizoen_id'    => $seizoen_id,
        'alleen_van'    => ['uitgenodigd_tw', 'tw_geweest'],
        'melding_vars'  => ['gesprek_bericht' => $bericht !== '' ? $bericht : tw_standaard_bericht($besluit)],
        'meldingstypen' => $besluit === 'afwijzen' ? ['tw_afgewezen'] : fase_meldingstypen($naar),
    ]);
    if (empty($res['ok'])) return ['ok' => false, 'fase' => null, 'melding' => 'Het besluit staat vast, maar doorvoeren lukte niet: ' . ($res['fout'] ?? 'onbekend')];
    tw_doorgevoerd_markeren($seizoen_id, $sol_id);
    $tekst = $besluit === 'aannemen' ? 'Aangenomen. De formulieren staan nu in het portaal klaar.' : 'Afgewezen.';
    $m = function_exists('fase_melding_tekst') ? fase_melding_tekst($res) : '';
    return ['ok' => true, 'fase' => $naar, 'melding' => trim($tekst . ' ' . $m)];
}

/**
 * De gesprekkenlijst gefilterd en gesorteerd (30 gesprekken op een tablet: je moet snel vinden).
 * Voegt aan elke kandidaat '_stand' (nog|bezig|gehad) en '_sd' (selectiedag-gemiddelde of null) toe.
 *
 * @param array $f  q (naam), stand (''|nog|bezig|gehad), besluit (''|aannemen|twijfel|afwijzen|geen),
 *                  sort ('naam'|'sd')
 */
function tw_gesprekken_filter(array $kandidaten, array $f, int $ik, array $sd_scores): array {
    $q = mb_strtolower(trim((string)($f['q'] ?? '')));
    $uit = [];
    foreach ($kandidaten as $k) {
        $k['_stand'] = tw_gesprek_stand($k, $ik);
        $k['_sd'] = function_exists('selectie_gemiddelde') ? selectie_gemiddelde($sd_scores[(int)$k['sol_id']] ?? []) : null;
        if ($q !== '' && !str_contains(mb_strtolower((string)$k['naam']), $q)) continue;
        if (!empty($f['stand']) && $k['_stand'] !== $f['stand']) continue;
        $b = (string)($k['besluit'] ?? '');
        if (!empty($f['besluit']) && ($f['besluit'] === 'geen' ? $b !== '' : $b !== $f['besluit'])) continue;
        $uit[] = $k;
    }
    if (($f['sort'] ?? '') === 'sd') {
        usort($uit, fn($a, $b) => ($b['_sd'] ?? -1) <=> ($a['_sd'] ?? -1) ?: strcmp((string)$a['naam'], (string)$b['naam']));
    }
    return $uit;
}

/** Stand van de gesprekken voor de lijst: nog / bezig / gehad (twijfel telt als gehad, met twijfel). */
function tw_gesprek_stand(array $k, int $ik): string {
    if (!empty($k['doorgevoerd_op']) || ($k['gesprek_status'] ?? '') === 'gehad') return 'gehad';
    if (function_exists('evenement_claim_actief') && evenement_claim_actief($k['gesprek_door'] ?? null, $k['gesprek_sinds'] ?? null, $ik)) return 'bezig';
    return 'nog';
}

} // function_exists

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
    try {
        $q = db()->prepare("
            SELECT s.id AS sol_id, s.fase, p.voornaam, p.tussenvoegsel, p.achternaam,
                   p.geboortedatum, p.geslacht, p.telefoon,
                   d.aanwezig, d.ingecheckt_op, d.afgemeld_reden,
                   d.besluit, d.besluit_notitie, d.besluit_door, d.besluit_op, d.doorgevoerd_op,
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

function tw_observatie_toevoegen(int $seizoen_id, int $sol_id, string $tekst, string $soort, string $door): bool {
    $tekst = trim($tekst);
    if ($tekst === '') return false;
    if (!in_array($soort, ['neutraal', 'positief', 'aandacht'], true)) $soort = 'neutraal';
    tw_auto_setup();
    try {
        db()->prepare("INSERT INTO tw_observatie (seizoen_id, sollicitatie_id, tekst, soort, beoordelaar)
                       VALUES (?,?,?,?,?)")
            ->execute([$seizoen_id, $sol_id, mb_substr($tekst, 0, 2000), $soort, mb_substr($door, 0, 100)]);
        return true;
    } catch (\Exception $e) { return false; }
}

/** Een observatie wissen. Alleen de eigen observatie; wat een ander zag blijft staan. */
function tw_observatie_wissen(int $obs_id, string $door): bool {
    tw_auto_setup();
    try {
        $q = db()->prepare("DELETE FROM tw_observatie WHERE id=? AND beoordelaar=?");
        $q->execute([$obs_id, $door]);
        return $q->rowCount() > 0;
    } catch (\Exception $e) { return false; }
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

} // function_exists

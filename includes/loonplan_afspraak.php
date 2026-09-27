<?php
/**
 * Paal 13 — De vastgestelde inzet-afspraak (module Loonplanning). GEEN HTML.
 *
 * Drie lagen, die elkaar nooit overschrijven:
 *   1. de opgave      — wat de sollicitant zelf invulde (beschikbaarheid_*; blijft altijd staan);
 *   2. de scenario's  — het puzzelen in de loonplanning (loon_planning, per scenario);
 *   3. de afspraak    — wat er echt is afgesproken (inzet_afspraak + inzet_afspraak_week).
 *
 * De afspraak ontstaat pas bij "Loonplanning definitief maken" (na het trainingsweekend): het
 * actieve scenario wordt dan per aangenomen persoon gekopieerd. Tot dat moment ziet niemand
 * buiten de loonplanning en het TW-gesprek iets van de planning — ook de sollicitant niet.
 * Daarna is de afspraak de bron voor portaal, dossier, export, contract en loonadministratie.
 * Later wijzigen kan per persoon ("afspraak bijwerken"), altijd met een regel in het auditlog.
 */

if (!function_exists('inzet_afspraak_auto_setup')) {

function inzet_afspraak_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
    try {
        $db = db();
        $db->exec("CREATE TABLE IF NOT EXISTS `inzet_afspraak` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `sollicitatie_id` INT NOT NULL,
            `functie_sleutel` VARCHAR(50) NOT NULL,
            `bron_scenario_id` INT DEFAULT NULL,
            `notitie` VARCHAR(500) DEFAULT NULL,
            `vastgesteld_op` DATETIME NOT NULL,
            `vastgesteld_door` INT DEFAULT NULL,
            UNIQUE KEY `seizoen_sol` (`seizoen_id`, `sollicitatie_id`),
            INDEX (`sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS `inzet_afspraak_week` (
            `afspraak_id` INT NOT NULL,
            `week_nr` INT NOT NULL,
            `jaar` INT NOT NULL,
            `dagen` TINYINT NOT NULL,
            `betaald` TINYINT(1) NOT NULL DEFAULT 1,
            `weekdagen` VARCHAR(30) DEFAULT NULL,
            PRIMARY KEY (`afspraak_id`, `jaar`, `week_nr`),
            FOREIGN KEY (`afspraak_id`) REFERENCES `inzet_afspraak`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('inzet_afspraak_auto_setup: ' . $e->getMessage()); }
}

/**
 * Welke functie ligt voor de hand? Nieuwe sollicitanten zijn Aspirant; wie in Authentik een
 * Lifeguard-rang heeft, krijgt die (rang 1 Aspirant, 2 Junior, 3 Lifeguard, 4 Senior).
 * Postcommandant is nooit een voorstel: dat kies je bewust.
 *
 * @param array|null $comp  [categorie => hoogste rang] als je hem al hebt (scheelt een query)
 */
function inzet_rol_voorstel(int $sol_id, ?array $comp = null): string {
    if ($comp === null) $comp = function_exists('bezetting_comp_van') ? bezetting_comp_van($sol_id) : [];
    $rang = (int)($comp['lifeguard'] ?? 0);
    $sleutel = [1 => 'aspirant', 2 => 'junior', 3 => 'lifeguard', 4 => 'senior'][$rang] ?? 'aspirant';
    // Bestaat die functie niet (hernoemd in Instellingen › Functies), dan de eerste die er is.
    static $bestaand = null;
    if ($bestaand === null) $bestaand = function_exists('functies_ophalen') ? array_column(functies_ophalen(), 'sleutel') : [];
    if ($bestaand && !in_array($sleutel, $bestaand, true)) $sleutel = (string)$bestaand[0];
    return $sleutel;
}

/**
 * Alle afspraken van een seizoen: [sol_id => afspraak + 'weken' => [wk => ['dagen','betaald','weekdagen']]].
 * Standaard zonder wie inmiddels gestopt is (afgewezen, afgehaakt, zelf verwijderd), net als de
 * scenario's (loonplan.php): anders blijft zo iemand in de loonexport, het rooster en de dekking staan.
 * $ook_gestopt = true voor de historie in het dossier.
 */
function inzet_afspraken_seizoen(int $seizoen_id, bool $ook_gestopt = false): array {
    inzet_afspraak_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT a.* FROM inzet_afspraak a JOIN sollicitaties s ON s.id = a.sollicitatie_id
                            WHERE a.seizoen_id=?"
                           . ($ook_gestopt ? '' : " AND s.fase NOT IN ('afgewezen','afgehaakt','verwijderd_door_sollicitant')"));
        $q->execute([$seizoen_id]);
        foreach ($q->fetchAll() as $a) { $a['weken'] = []; $uit[(int)$a['sollicitatie_id']] = $a; }
        if (!$uit) return [];
        $ids = array_column($uit, 'id', 'sollicitatie_id');
        $per_id = array_flip(array_map('intval', $ids));
        $q = db()->prepare("SELECT * FROM inzet_afspraak_week WHERE afspraak_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") ORDER BY jaar, week_nr");
        $q->execute(array_values(array_map('intval', $ids)));
        foreach ($q->fetchAll() as $w) {
            $sol = $per_id[(int)$w['afspraak_id']] ?? null;
            if ($sol === null) continue;
            $uit[$sol]['weken'][(int)$w['week_nr']] = [
                'dagen' => (int)$w['dagen'], 'betaald' => (int)$w['betaald'],
                'weekdagen' => $w['weekdagen'] ? (json_decode($w['weekdagen'], true) ?: []) : [],
            ];
        }
    } catch (\Exception $e) { error_log('inzet_afspraken_seizoen: ' . $e->getMessage()); }
    return $uit;
}

/** De afspraak van één sollicitatie (of null: nog niets vastgesteld). */
function inzet_afspraak_van(int $sol_id): ?array {
    try {
        $q = db()->prepare("SELECT seizoen_id FROM sollicitaties WHERE id=?");
        $q->execute([$sol_id]);
        $sid = (int)$q->fetchColumn();
    } catch (\Exception $e) { return null; }
    return $sid ? (inzet_afspraken_seizoen($sid, true)[$sol_id] ?? null) : null;
}

/**
 * Wijkt het scenario af van de vastgestelde afspraak? (Functie, of een week met ander aantal
 * dagen, andere betaald-stand of andere weekdagen.) $weken komt uit loonplan_analyse().
 */
function inzet_afspraak_wijkt_af(array $afspraak, string $functie, array $weken): bool {
    if ($afspraak['functie_sleutel'] !== $functie) return true;
    $scenario = [];
    foreach ($weken as $wk => $w) if ($w['opgenomen'] && $w['dagen'] > 0) $scenario[$wk] = [$w['dagen'], (int)$w['betaald'], $w['weekdagen']];
    $afgesproken = [];
    foreach ($afspraak['weken'] as $wk => $w) $afgesproken[$wk] = [$w['dagen'], $w['betaald'], $w['weekdagen']];
    ksort($scenario); ksort($afgesproken);
    return $scenario !== $afgesproken;
}

/**
 * Stel de afspraak vast voor deze sollicitaties, vanuit een scenario. Vervangt een eerdere
 * afspraak van dezelfde persoon helemaal. Retourneert het aantal vastgestelde personen.
 */
function inzet_vaststellen(int $seizoen_id, int $scenario_id, array $sol_ids, int $door): int {
    inzet_afspraak_auto_setup();
    if (!$sol_ids || !function_exists('loonplan_analyse')) return 0;
    $seizoen = seizoen_op_id($seizoen_id);
    $an   = loonplan_analyse($seizoen_id, $scenario_id, $seizoen);
    $jaar = loonplan_jaar($seizoen);
    $db   = db();
    $n = 0;
    foreach (array_unique(array_map('intval', $sol_ids)) as $sol_id) {
        $m = $an['mensen'][$sol_id] ?? null;
        if (!$m) continue;
        try {
            $db->beginTransaction();
            $db->prepare("INSERT INTO inzet_afspraak (seizoen_id, sollicitatie_id, functie_sleutel, bron_scenario_id, vastgesteld_op, vastgesteld_door)
                          VALUES (?,?,?,?,NOW(),?)
                          ON DUPLICATE KEY UPDATE functie_sleutel=VALUES(functie_sleutel), bron_scenario_id=VALUES(bron_scenario_id),
                                                  vastgesteld_op=NOW(), vastgesteld_door=VALUES(vastgesteld_door)")
               ->execute([$seizoen_id, $sol_id, $m['functie'], $scenario_id, $door ?: null]);
            $q = $db->prepare("SELECT id FROM inzet_afspraak WHERE seizoen_id=? AND sollicitatie_id=?");
            $q->execute([$seizoen_id, $sol_id]);
            $aid = (int)$q->fetchColumn();
            $db->prepare("DELETE FROM inzet_afspraak_week WHERE afspraak_id=?")->execute([$aid]);
            $ins = $db->prepare("INSERT INTO inzet_afspraak_week (afspraak_id, week_nr, jaar, dagen, betaald, weekdagen) VALUES (?,?,?,?,?,?)");
            $weken = 0;
            foreach ($m['weken'] as $wk => $w) {
                if (!$w['opgenomen'] || $w['dagen'] <= 0) continue;
                $ins->execute([$aid, $wk, $jaar, $w['dagen'], $w['betaald'] ? 1 : 0, $w['weekdagen'] ? json_encode($w['weekdagen']) : null]);
                $weken++;
            }
            $db->commit();
            $n++;
            if (function_exists('audit_log')) {
                audit_log('afspraak_vastgesteld', 'sollicitatie', $sol_id, ['functie' => $m['functie'], 'weken' => $weken,
                    'dagen_betaald' => $m['dagen_betaald'], 'dagen_onbetaald' => $m['dagen_onbetaald'], 'scenario' => $scenario_id]);
            }
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('inzet_vaststellen ' . $sol_id . ': ' . $e->getMessage());
        }
    }
    return $n;
}

/**
 * "Loonplanning definitief maken": het actieve scenario wordt de afspraak van iedereen die is
 * aangenomen. Retourneert ['aantal' => n, 'zonder_planning' => [namen]] — wie is aangenomen
 * maar in het scenario nergens staat ingepland, krijgt géén lege afspraak maar een melding.
 */
function loonplan_definitief_maken(int $seizoen_id, int $scenario_id, int $door): array {
    $seizoen = seizoen_op_id($seizoen_id);
    $an = loonplan_analyse($seizoen_id, $scenario_id, $seizoen);
    $wel = []; $zonder = [];
    foreach ($an['mensen'] as $sol_id => $m) {
        if ($m['fase'] !== 'aangenomen') continue;
        if ($m['totaal_dagen'] > 0) $wel[] = $sol_id; else $zonder[] = $m['naam'];
    }
    $n = inzet_vaststellen($seizoen_id, $scenario_id, $wel, $door);
    if (function_exists('instelling_opslaan')) instelling_opslaan('loonplan.definitief.' . $seizoen_id, date('Y-m-d H:i:s'));
    if (function_exists('audit_log')) audit_log('loonplanning_definitief', 'seizoen', $seizoen_id, ['scenario' => $scenario_id, 'personen' => $n, 'zonder_planning' => count($zonder)]);
    return ['aantal' => $n, 'zonder_planning' => $zonder];
}

/** Wanneer is de loonplanning van dit seizoen definitief gemaakt? null = nog niet. */
function loonplan_definitief_op(int $seizoen_id): ?string {
    $v = function_exists('instelling') ? (string)instelling('loonplan.definitief.' . $seizoen_id, '') : '';
    return $v !== '' ? $v : null;
}

/**
 * Wat geldt voor dekking en inzet: de afspraak als die er is, anders null (dan de opgave).
 * [wk => dagen] — alleen weken met dagen > 0.
 */
function inzet_effectief(int $sol_id): ?array {
    $a = inzet_afspraak_van($sol_id);
    if (!$a) return null;
    $uit = [];
    foreach ($a['weken'] as $wk => $w) if ($w['dagen'] > 0) $uit[$wk] = $w['dagen'];
    return $uit;
}

}

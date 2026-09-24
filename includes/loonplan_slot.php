<?php
/**
 * Paal 13 — Loonplanning: vastzetten na het gesprek (module Loonplanning). GEEN HTML.
 *
 * Wat in het TW-gesprek met de sollicitant is besproken, mag daarna niet stilletjes veranderen
 * doordat iemand met een ander scenario speelt. Daarom: bij het vastleggen van het besluit worden
 * de weken en de functie van die persoon uit het actieve scenario naar ÁLLE scenario's van het
 * seizoen gekopieerd, en staat die persoon daar op slot. Met de rest kun je blijven puzzelen.
 *
 * Toch iets aanpassen (bijzondere situatie) = ontgrendelen met een verplichte reden (auditlog),
 * aanpassen, en opnieuw vastzetten — dat kopieert opnieuw naar alle scenario's.
 *
 * Het slot is geen afspraak: die ontstaat nog steeds pas bij "Loonplanning definitief maken".
 */

if (!function_exists('loonplan_slot_auto_setup')) {

function loonplan_slot_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `loon_vergrendeling` (
            `seizoen_id` INT NOT NULL,
            `sollicitatie_id` INT NOT NULL,
            `bron_scenario_id` INT DEFAULT NULL,
            `vergrendeld_op` DATETIME NOT NULL,
            `vergrendeld_door` INT DEFAULT NULL,
            PRIMARY KEY (`seizoen_id`, `sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('loonplan_slot_auto_setup: ' . $e->getMessage()); }
}

/** Alle vergrendelingen van een seizoen: [sol_id => rij]. */
function loonplan_sloten(int $seizoen_id): array {
    loonplan_slot_auto_setup();
    try {
        $q = db()->prepare("SELECT * FROM loon_vergrendeling WHERE seizoen_id=?");
        $q->execute([$seizoen_id]);
        $uit = [];
        foreach ($q->fetchAll() as $r) $uit[(int)$r['sollicitatie_id']] = $r;
        return $uit;
    } catch (\Exception $e) { return []; }
}

/** Staat deze persoon op slot in (het seizoen van) dit scenario? */
function loonplan_slot_in_scenario(int $scenario_id, int $sol_id): bool {
    loonplan_slot_auto_setup();
    try {
        $q = db()->prepare("SELECT 1 FROM loon_vergrendeling v JOIN loon_scenario s ON s.seizoen_id = v.seizoen_id
                            WHERE s.id=? AND v.sollicitatie_id=?");
        $q->execute([$scenario_id, $sol_id]);
        return (bool)$q->fetchColumn();
    } catch (\Exception $e) { return false; }
}

/**
 * Vastzetten: de weken en de functie van $sol_id uit $bron_scenario naar alle scenario's van het
 * seizoen kopiëren en de persoon op slot zetten. Eén transactie.
 */
function loonplan_vastzetten(int $seizoen_id, int $sol_id, int $bron_scenario_id, int $door): bool {
    loonplan_slot_auto_setup();
    $db = db();
    try {
        $db->beginTransaction();
        $q = $db->prepare("SELECT id FROM loon_scenario WHERE seizoen_id=? AND id<>?");
        $q->execute([$seizoen_id, $bron_scenario_id]);
        foreach ($q->fetchAll() as $s) {
            $sc = (int)$s['id'];
            $db->prepare("DELETE FROM loon_planning WHERE scenario_id=? AND sollicitatie_id=?")->execute([$sc, $sol_id]);
            $db->prepare("INSERT INTO loon_planning (sollicitatie_id, week_nr, jaar, dagen, functie_sleutel, opgenomen, betaald, weekdagen, loon_override, notitie, scenario_id)
                          SELECT sollicitatie_id, week_nr, jaar, dagen, functie_sleutel, opgenomen, betaald, weekdagen, loon_override, notitie, ?
                          FROM loon_planning WHERE scenario_id=? AND sollicitatie_id=?")->execute([$sc, $bron_scenario_id, $sol_id]);
            $db->prepare("DELETE FROM loon_scenario_persoon WHERE scenario_id=? AND sollicitatie_id=?")->execute([$sc, $sol_id]);
            $db->prepare("INSERT INTO loon_scenario_persoon (scenario_id, sollicitatie_id, functie_sleutel)
                          SELECT ?, sollicitatie_id, functie_sleutel FROM loon_scenario_persoon WHERE scenario_id=? AND sollicitatie_id=?")
               ->execute([$sc, $bron_scenario_id, $sol_id]);
        }
        $db->prepare("INSERT INTO loon_vergrendeling (seizoen_id, sollicitatie_id, bron_scenario_id, vergrendeld_op, vergrendeld_door)
                      VALUES (?,?,?,NOW(),?)
                      ON DUPLICATE KEY UPDATE bron_scenario_id=VALUES(bron_scenario_id), vergrendeld_op=NOW(), vergrendeld_door=VALUES(vergrendeld_door)")
           ->execute([$seizoen_id, $sol_id, $bron_scenario_id, $door ?: null]);
        $db->commit();
    } catch (\Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('loonplan_vastzetten: ' . $e->getMessage());
        return false;
    }
    if (function_exists('audit_log')) audit_log('loonplanning_vastgezet', 'sollicitatie', $sol_id, ['scenario' => $bron_scenario_id]);
    return true;
}

/** Ontgrendelen voor een bijzondere situatie. De reden is verplicht en komt in het logboek. */
function loonplan_ontgrendelen(int $seizoen_id, int $sol_id, string $reden, int $door): bool {
    $reden = mb_substr(trim($reden), 0, 300);
    if ($reden === '') return false;
    loonplan_slot_auto_setup();
    try {
        db()->prepare("DELETE FROM loon_vergrendeling WHERE seizoen_id=? AND sollicitatie_id=?")->execute([$seizoen_id, $sol_id]);
    } catch (\Exception $e) { return false; }
    if (function_exists('audit_log')) audit_log('loonplanning_ontgrendeld', 'sollicitatie', $sol_id, ['reden' => $reden, 'door' => $door]);
    return true;
}

} // function_exists

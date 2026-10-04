<?php
/**
 * Paal 13 — extra personen in de loonplanning (module Loonplanning). GEEN HTML.
 *
 * Iemand die niet via het sollicitatiesysteem komt maar wel meetelt in de kosten, het budget, de
 * dekking en het rooster: bijvoorbeeld een postcommandant. Besluit Lucas (30 sep 2026): een eigen
 * lijst, geen nep-sollicitatie. Zo staan ze nooit in Sollicitanten, Berichten, Vandaag of de
 * AVG-wislaag van sollicitanten, en hebben ze geen portaal of afspraak.
 *
 * In loonplan_analyse() zijn ze een persoon met een negatieve sleutel (-id); de opslagfuncties van
 * de planning (loonplan_cel_opslaan, loonplan_functie_zetten) sturen een negatieve sleutel hierheen.
 * De planning staat per scenario in een eigen tabel: loon_planning heeft een foreign key naar
 * sollicitaties en kan deze mensen niet bevatten.
 *
 * Persoonsgegevens: naam en (optioneel) geboortedatum, voor het dagloon naar leeftijd. Zie het
 * verwerkingsregister (docs/handleiding/verwerkingsregister.md). Ze verdwijnen met het seizoen.
 */

if (!function_exists('loonplan_extra_auto_setup')) {

function loonplan_extra_auto_setup(): void {
    static $done = false;
    if ($done || schema_actueel()) return;
    $done = true;
    try {
        $db = db();
        $db->exec("CREATE TABLE IF NOT EXISTS `loon_extra_persoon` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `naam` VARCHAR(100) NOT NULL,
            `functie_sleutel` VARCHAR(50) NOT NULL DEFAULT 'postcommandant',
            `geboortedatum` DATE DEFAULT NULL,
            `notitie` VARCHAR(255) DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`seizoen_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS `loon_extra_planning` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `extra_id` INT NOT NULL,
            `scenario_id` INT NOT NULL,
            `week_nr` TINYINT NOT NULL,
            `jaar` SMALLINT NOT NULL,
            `dagen` TINYINT NOT NULL DEFAULT 0,
            `opgenomen` TINYINT(1) NOT NULL DEFAULT 0,
            `betaald` TINYINT(1) NOT NULL DEFAULT 1,
            `bijgewerkt_op` DATETIME DEFAULT NULL,
            UNIQUE KEY `uniek_extra` (`extra_id`, `week_nr`, `jaar`, `scenario_id`),
            INDEX (`scenario_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('loonplan_extra_auto_setup: ' . $e->getMessage()); }
}

/** De extra personen van een seizoen, op naam: [id => rij]. */
function loonplan_extra_personen(int $seizoen_id): array {
    loonplan_extra_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT * FROM loon_extra_persoon WHERE seizoen_id=? ORDER BY naam");
        $q->execute([$seizoen_id]);
        foreach ($q->fetchAll() as $r) $uit[(int)$r['id']] = $r;
    } catch (\Exception $e) {}
    return $uit;
}

/**
 * Toevoegen ($id null) of wijzigen. Geeft het id terug, of null bij een lege naam of fout.
 * Een geboortedatum is optioneel: zonder rekent het dagloon met 18 jaar (postcommandant rekent
 * met een vast bedrag, zonder leeftijd).
 */
function loonplan_extra_opslaan(int $seizoen_id, ?int $id, string $naam, string $functie, ?string $geboortedatum, string $notitie = ''): ?int {
    loonplan_extra_auto_setup();
    $naam = trim($naam);
    if ($naam === '' || $seizoen_id <= 0) return null;
    $geb = $geboortedatum && preg_match('/^\d{4}-\d{2}-\d{2}$/', $geboortedatum) ? $geboortedatum : null;
    $waarden = [mb_substr($naam, 0, 100), mb_substr($functie ?: 'postcommandant', 0, 50), $geb, mb_substr(trim($notitie), 0, 255) ?: null];
    try {
        if ($id) {
            db()->prepare("UPDATE loon_extra_persoon SET naam=?, functie_sleutel=?, geboortedatum=?, notitie=? WHERE id=? AND seizoen_id=?")
                ->execute(array_merge($waarden, [$id, $seizoen_id]));
        } else {
            db()->prepare("INSERT INTO loon_extra_persoon (naam, functie_sleutel, geboortedatum, notitie, seizoen_id) VALUES (?,?,?,?,?)")
                ->execute(array_merge($waarden, [$seizoen_id]));
            $id = (int)db()->lastInsertId();
        }
        if (function_exists('audit_log')) audit_log('loonplan_extra_opgeslagen', 'loon_extra', $id, ['naam' => $waarden[0], 'functie' => $waarden[1]]);
        if (function_exists('live_loon')) live_loon(0, $seizoen_id);
        return $id;
    } catch (\Exception $e) { error_log('loonplan_extra_opslaan: ' . $e->getMessage()); return null; }
}

/** Verwijderen, met de planning in alle scenario's. */
function loonplan_extra_verwijderen(int $seizoen_id, int $id): bool {
    loonplan_extra_auto_setup();
    try {
        $q = db()->prepare("SELECT naam FROM loon_extra_persoon WHERE id=? AND seizoen_id=?");
        $q->execute([$id, $seizoen_id]);
        $naam = $q->fetchColumn();
        if ($naam === false) return false;
        db()->prepare("DELETE FROM loon_extra_planning WHERE extra_id=?")->execute([$id]);
        db()->prepare("DELETE FROM loon_extra_persoon WHERE id=?")->execute([$id]);
        if (function_exists('audit_log')) audit_log('loonplan_extra_verwijderd', 'loon_extra', $id, ['naam' => (string)$naam]);
        if (function_exists('live_loon')) live_loon(0, $seizoen_id);
        return true;
    } catch (\Exception $e) { error_log('loonplan_extra_verwijderen: ' . $e->getMessage()); return false; }
}

/** De planning van één scenario: [extra_id][week] => rij (zelfde velden als loon_planning). */
function loonplan_extra_planning(int $scenario_id, int $jaar, ?int $alleen_id = null): array {
    loonplan_extra_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT * FROM loon_extra_planning WHERE scenario_id=? AND jaar=?" . ($alleen_id ? " AND extra_id=?" : ""));
        $q->execute($alleen_id ? [$scenario_id, $jaar, $alleen_id] : [$scenario_id, $jaar]);
        foreach ($q->fetchAll() as $r) $uit[(int)$r['extra_id']][(int)$r['week_nr']] = $r;
    } catch (\Exception $e) {}
    return $uit;
}

function loonplan_extra_cel_opslaan(int $scenario_id, int $extra_id, int $week, int $jaar, int $dagen, int $opgenomen, int $betaald = 1): bool {
    loonplan_extra_auto_setup();
    try {
        db()->prepare("INSERT INTO loon_extra_planning (extra_id, scenario_id, week_nr, jaar, dagen, opgenomen, betaald, bijgewerkt_op)
                       VALUES (?,?,?,?,?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE dagen=VALUES(dagen), opgenomen=VALUES(opgenomen), betaald=VALUES(betaald), bijgewerkt_op=NOW()")
            ->execute([$extra_id, $scenario_id, $week, $jaar, max(0, min(7, $dagen)), $opgenomen ? 1 : 0, $betaald ? 1 : 0]);
        if (function_exists('live_loon')) live_loon($scenario_id);
        return true;
    } catch (\Exception $e) { error_log('loonplan_extra_cel_opslaan: ' . $e->getMessage()); return false; }
}

/** De functie van een extra persoon (geldt in alle scenario's: het is geen sollicitant om mee te puzzelen). */
function loonplan_extra_functie_zetten(int $extra_id, string $functie): bool {
    loonplan_extra_auto_setup();
    try {
        db()->prepare("UPDATE loon_extra_persoon SET functie_sleutel=? WHERE id=?")->execute([mb_substr($functie, 0, 50), $extra_id]);
        return true;
    } catch (\Exception $e) { return false; }
}

/** Bij het dupliceren of verwijderen van een scenario. */
function loonplan_extra_scenario_dupliceer(int $bron_id, int $nieuw_id): void {
    loonplan_extra_auto_setup();
    try {
        db()->prepare("INSERT INTO loon_extra_planning (extra_id, scenario_id, week_nr, jaar, dagen, opgenomen, betaald, bijgewerkt_op)
                       SELECT extra_id, ?, week_nr, jaar, dagen, opgenomen, betaald, bijgewerkt_op FROM loon_extra_planning WHERE scenario_id=?")
            ->execute([$nieuw_id, $bron_id]);
    } catch (\Exception $e) { error_log('loonplan_extra_scenario_dupliceer: ' . $e->getMessage()); }
}

function loonplan_extra_scenario_wissen(int $scenario_id): void {
    loonplan_extra_auto_setup();
    try { db()->prepare("DELETE FROM loon_extra_planning WHERE scenario_id=?")->execute([$scenario_id]); } catch (\Exception $e) {}
}

} // function_exists

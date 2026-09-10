<?php
/**
 * Paal 13 — Database failsafe / zelfherstel.
 *
 * Eén centrale plek die garandeert dat het databaseschema klopt, ongeacht of losse
 * SQL-migraties gedraaid zijn. Draait één keer per request (lichte guard) en:
 *   1. roept alle bekende auto_setup-functies aan (tabellen maken zichzelf aan);
 *   2. controleert kritieke kolommen en voegt ontbrekende toe.
 *
 * Zo herstelt het systeem zichzelf: een verse of achtergebleven database wordt bij de
 * eerste pagina-load compleet gemaakt, zonder handmatige migraties.
 *
 * Ontworpen om NOOIT een pagina te breken: elke stap zit in een eigen try/catch en
 * faalt stil (met een log-regel), zodat een probleem met één tabel de rest niet raakt.
 */

if (!function_exists('db_failsafe')) {

/**
 * Controleert of een kolom bestaat en voegt hem toe als hij ontbreekt.
 * Werkt op elke MariaDB/MySQL-versie (gebruikt INFORMATION_SCHEMA, niet IF NOT EXISTS).
 */
function db_kolom_verzeker(PDO $db, string $tabel, string $kolom, string $definitie): void {
    try {
        $q = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $q->execute([$tabel, $kolom]);
        if (!$q->fetchColumn()) {
            $db->exec("ALTER TABLE `$tabel` ADD COLUMN `$kolom` $definitie");
            error_log("db_failsafe: kolom $tabel.$kolom toegevoegd");
        }
    } catch (\Exception $e) {
        error_log("db_failsafe: kon $tabel.$kolom niet verzekeren: " . $e->getMessage());
    }
}

/**
 * Controleert of een tabel bestaat (los van de auto_setup-functies).
 * Retourneert true/false; breekt nooit af.
 */
function db_tabel_bestaat(PDO $db, string $tabel): bool {
    try {
        $q = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $q->execute([$tabel]);
        return (bool)$q->fetchColumn();
    } catch (\Exception $e) { return false; }
}

/**
 * Het verwachte kolom-schema per tabel: kolommen die via losse migraties zijn toegevoegd
 * en op een achtergebleven database kunnen ontbreken. Alleen kolommen die stille bugs
 * veroorzaken als ze missen — niet het volledige schema (dat maken de auto_setups).
 */
function db_verwachte_kolommen(): array {
    return [
        'seizoenen' => [
            'selectiedag_starttijd'    => 'TIME DEFAULT NULL',
            'selectiedag_verzameltijd' => 'TIME DEFAULT NULL',
            'datum_tw_start'           => 'DATE DEFAULT NULL',
            'datum_tw_eind'            => 'DATE DEFAULT NULL',
            'locatie_tw'               => 'VARCHAR(255) DEFAULT NULL',
            'tw_verzameltijd'          => 'TIME DEFAULT NULL',
            'tw_begintijd'             => 'TIME DEFAULT NULL',
            'datum_werk_start'         => 'DATE DEFAULT NULL',
            'datum_werk_eind'          => 'DATE DEFAULT NULL',
            'hoofdseizoen_start_week'  => 'INT DEFAULT NULL',
            'hoofdseizoen_eind_week'   => 'INT DEFAULT NULL',
        ],
        'ondertekeningen' => [
            'herinnerd_op' => 'DATETIME DEFAULT NULL',
        ],
        'mail_templates' => [
            'hero_titel'   => 'VARCHAR(200) DEFAULT NULL',
        ],
        'beschikbaarheid_dag' => [
            'gewijzigd_door' => 'INT DEFAULT NULL',
            'gewijzigd_op'   => 'DATETIME DEFAULT NULL',
        ],
    ];
}

/**
 * De centrale failsafe. Draait één keer per request.
 * Roept alle auto_setup-functies aan en verzekert de verwachte kolommen.
 */
function db_failsafe(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    // 1. Alle bekende auto_setup-functies aanroepen (elk heeft zijn eigen guard + try/catch).
    $setups = [
        'instellingen_auto_setup',
        'mail_templates_auto_setup',
        'mail_queue_auto_setup',
        'ai_queue_auto_setup',
        'login_pogingen_auto_setup',
        'competenties_auto_setup',
        'selectiedag_auto_setup',
        'personen_ouder_auto_setup',
        'ondertekeningen_auto_setup',
        'ondertekening_herinnerd_auto_setup',
        'extra_tabellen_auto_setup',
        'consent_auto_setup',
        'opschoon_auto_setup',
        'loonplan_auto_setup',
        'rechten_auto_setup',
        'beschikbaarheid_dag_auto_setup',
    ];
    foreach ($setups as $fn) {
        if (function_exists($fn)) {
            try { $fn(); }
            catch (\Exception $e) { error_log("db_failsafe: $fn faalde: " . $e->getMessage()); }
            catch (\Error $e)     { error_log("db_failsafe: $fn error: " . $e->getMessage()); }
        }
    }

    // 2. Ontbrekende kolommen verzekeren (alleen voor tabellen die bestaan).
    try {
        $db = db();
        foreach (db_verwachte_kolommen() as $tabel => $kolommen) {
            if (!db_tabel_bestaat($db, $tabel)) continue;
            foreach ($kolommen as $kolom => $definitie) {
                db_kolom_verzeker($db, $tabel, $kolom, $definitie);
            }
        }
        // Speciaal: soort-enum van beschikbaarheid_dag moet 'beschikbaar' bevatten
        // (voor bestuurs-overrides die een dag expliciet beschikbaar maken).
        if (db_tabel_bestaat($db, 'beschikbaarheid_dag')) {
            try {
                $q = $db->query("SHOW COLUMNS FROM beschikbaarheid_dag LIKE 'soort'");
                $col = $q->fetch();
                if ($col && stripos($col['Type'] ?? '', "'beschikbaar'") === false) {
                    $db->exec("ALTER TABLE beschikbaarheid_dag MODIFY COLUMN soort
                               ENUM('niet_beschikbaar','voorkeur_vrij','beschikbaar') NOT NULL DEFAULT 'niet_beschikbaar'");
                    error_log('db_failsafe: soort-enum uitgebreid met beschikbaar');
                }
            } catch (\Exception $e) { error_log('db_failsafe soort-enum: ' . $e->getMessage()); }
        }
    } catch (\Exception $e) {
        error_log('db_failsafe kolom-check: ' . $e->getMessage());
    }
}

/**
 * Diagnose: geeft een overzicht van de schema-gezondheid terug (voor een beheerpagina).
 * Breekt nooit af; retourneert wat het kan vaststellen.
 */
function db_gezondheid(): array {
    $uit = ['tabellen' => [], 'ontbrekende_kolommen' => [], 'ok' => true];
    try {
        $db = db();
        // Verwachte kern-tabellen
        $verwachte_tabellen = [
            'seizoenen', 'personen', 'sollicitaties', 'beheerders', 'instellingen',
            'mail_templates', 'mail_queue', 'ondertekeningen', 'rollen', 'rol_rechten',
            'beschikbaarheid_profiel', 'beschikbaarheid_dag', 'competenties',
        ];
        foreach ($verwachte_tabellen as $t) {
            $bestaat = db_tabel_bestaat($db, $t);
            $uit['tabellen'][$t] = $bestaat;
            if (!$bestaat) $uit['ok'] = false;
        }
        // Verwachte kolommen
        foreach (db_verwachte_kolommen() as $tabel => $kolommen) {
            if (!db_tabel_bestaat($db, $tabel)) continue;
            foreach ($kolommen as $kolom => $_def) {
                $q = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
                $q->execute([$tabel, $kolom]);
                if (!$q->fetchColumn()) {
                    $uit['ontbrekende_kolommen'][] = "$tabel.$kolom";
                    $uit['ok'] = false;
                }
            }
        }
    } catch (\Exception $e) {
        $uit['fout'] = $e->getMessage();
        $uit['ok'] = false;
    }
    return $uit;
}

}

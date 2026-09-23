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
        'sollicitant_taken' => [
            'soort'          => "VARCHAR(20) NOT NULL DEFAULT 'afvinken'",
            'document_soort' => 'VARCHAR(40) DEFAULT NULL',
            'volgorde'       => 'INT NOT NULL DEFAULT 50',
        ],
        'mail_templates' => [
            'hero_titel'   => 'VARCHAR(200) DEFAULT NULL',
        ],
        'beschikbaarheid_dag' => [
            'gewijzigd_door' => 'INT DEFAULT NULL',
            'gewijzigd_op'   => 'DATETIME DEFAULT NULL',
        ],
        'beheerders' => [
            'standaard_ingang' => "VARCHAR(10) DEFAULT NULL",
        ],
        'sollicitaties' => [
            'bron_code'  => 'VARCHAR(80) DEFAULT NULL',
            'bron_label' => 'VARCHAR(150) DEFAULT NULL',
        ],
    ];
}

/**
 * De centrale failsafe. Draait één keer per versie (en verder nooit meer, tot de volgende release).
 * Roept alle auto_setup-functies aan en verzekert de verwachte kolommen.
 *
 * Vóór 2.94.0 draaide dit bij élk verzoek: ~50-80 queries, waarvan een flink deel tegen
 * INFORMATION_SCHEMA. Nu bewaart een geslaagde run de versie in de instelling
 * `db.failsafe_versie`; staat die gelijk aan APP_VERSIE, dan wordt alles overgeslagen.
 * Systeem & Updates en de health-check forceren met db_failsafe(true).
 */
function db_failsafe(bool $forceer = false): void {
    static $done = false;
    if ($done && !$forceer) return;
    $done = true;

    $versie = defined('APP_VERSIE') ? APP_VERSIE : '0';
    if (!$forceer) {
        try {
            $q = db()->prepare("SELECT waarde FROM instellingen WHERE sleutel = 'db.failsafe_versie' LIMIT 1");
            $q->execute();
            if ((string)$q->fetchColumn() === $versie) return;
        } catch (\Throwable $e) { /* tabel bestaat nog niet: gewoon de volledige run doen */ }
    }

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
        'extra_tabellen_auto_setup',
        'consent_auto_setup',
        'opschoon_auto_setup',
        'loonplan_auto_setup',
        'rechten_auto_setup',
        'beschikbaarheid_dag_auto_setup',
        'audit_dag_auto_setup',
        'pag_auto_setup',
        'optout_auto_setup',
        'seintje_auto_setup',
        'sjablonen_auto_setup',
        'meldingen_log_auto_setup',
        'personen_kanaal_auto_setup',
        'bijlagen_auto_setup',
        'gepland_auto_setup',
        'documenten_auto_setup',
        'ondertekening_bewijs_auto_setup',
        'portaal_login_setup',
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
        // Speciaal: otp_tokens.type was ENUM('portaal','beheerder'), maar is nu een vrije sleutel (ook
        // 'ond<id>' voor de 2FA bij ondertekenen). Op een ENUM-kolom wordt zo'n waarde stil als lege string
        // opgeslagen, waardoor de verstuurde code nooit te verifiëren is. Omzetten naar VARCHAR en de
        // onbruikbare rijen (type='') opruimen.
        if (db_tabel_bestaat($db, 'otp_tokens')) {
            try {
                $col = $db->query("SHOW COLUMNS FROM otp_tokens LIKE 'type'")->fetch();
                if ($col && stripos((string)($col['Type'] ?? ''), 'enum') !== false) {
                    $db->exec("ALTER TABLE otp_tokens MODIFY COLUMN `type` VARCHAR(30) NOT NULL DEFAULT 'portaal'");
                    $db->exec("DELETE FROM otp_tokens WHERE `type` = ''");
                    error_log('db_failsafe: otp_tokens.type omgezet van ENUM naar VARCHAR(30)');
                }
            } catch (\Exception $e) { error_log('db_failsafe otp_tokens.type: ' . $e->getMessage()); }
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

        // Speciaal: het oude installatie-account uit schema.sql/migratie 0002 had een wachtwoord dat
        // in Git stond ("password"). Bestaat dat account nog met exact die hash, dan gaat het op
        // non-actief. Inloggen kan daarna alleen nog via Authentik of een echt gezet wachtwoord.
        if (db_tabel_bestaat($db, 'beheerders')) {
            try {
                $seed_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
                $u = $db->prepare("UPDATE beheerders SET actief = 0 WHERE email = 'admin@paal13.com' AND wachtwoord = ? AND actief = 1");
                $u->execute([$seed_hash]);
                if ($u->rowCount() > 0) {
                    error_log('db_failsafe: installatie-account admin@paal13.com met standaardwachtwoord op non-actief gezet');
                    if (function_exists('audit_log')) audit_log('seed_account_uitgeschakeld', 'beheerder', null, ['email' => 'admin@paal13.com']);
                }
            } catch (\Exception $e) { error_log('db_failsafe seed-account: ' . $e->getMessage()); }
        }

        // Klaar: deze versie hoeft pas weer te draaien na de volgende release.
        try {
            $db->prepare("INSERT INTO instellingen (sleutel, waarde) VALUES ('db.failsafe_versie', ?) ON DUPLICATE KEY UPDATE waarde = ?")
               ->execute([$versie, $versie]);
        } catch (\Exception $e) { error_log('db_failsafe versievlag: ' . $e->getMessage()); }
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

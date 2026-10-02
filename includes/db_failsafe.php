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

// Back-up vóór elke schema-update (backup_maak), zie includes/backup.php.
if (file_exists(__DIR__ . '/backup.php')) require_once __DIR__ . '/backup.php';

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
 * Klopt het schema al voor deze versie? Dan hoeft geen enkele *_auto_setup() iets te doen.
 *
 * Elke setup-functie begint met `if ($done || schema_actueel()) return;`. Zonder die regel
 * draaiden ze bij élk verzoek opnieuw (tientallen INFORMATION_SCHEMA-queries en UPDATEs per
 * aanmelding) — de hoofdoorzaak van trage formulieren. db() start de failsafe bij de eerste
 * verbinding, dus na de aanroep hieronder is de vlag gezet. Tijdens de volledige run staat
 * hij uit, zodat de setups dan wél hun werk doen.
 * Gevolg: een nieuwe tabel of kolom in een setup = APP_VERSIE ophogen, anders draait hij niet.
 */
function schema_actueel(): bool {
    if (function_exists('db')) db();
    return !empty($GLOBALS['_p13_schema_actueel']);
}

/** Bestaat deze index (op naam) op de tabel? Breekt nooit af. */
function db_index_bestaat(PDO $db, string $tabel, string $index): bool {
    try {
        $q = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1");
        $q->execute([$tabel, $index]);
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
            // Tweestaps op de wachtwoord-noodingang (includes/tweestaps.php). TEXT: de kluis-blob
            // en de lijst met hashes passen niet gegarandeerd in een VARCHAR (stil afkappen).
            'tweestaps_geheim'  => 'TEXT DEFAULT NULL',
            'tweestaps_aan_op'  => 'DATETIME DEFAULT NULL',
            'tweestaps_stap'    => 'BIGINT DEFAULT NULL',
            'tweestaps_herstel' => 'TEXT DEFAULT NULL',
        ],
        'sollicitaties' => [
            'bron_code'  => 'VARCHAR(80) DEFAULT NULL',
            'bron_label' => 'VARCHAR(150) DEFAULT NULL',
            // 2.145.0: UTM van de aanmelding (pag_utm_velden()), geschoond tot a-z0-9-.
            'utm_source'   => 'VARCHAR(40) DEFAULT NULL',
            'utm_medium'   => 'VARCHAR(40) DEFAULT NULL',
            'utm_campaign' => 'VARCHAR(40) DEFAULT NULL',
            'utm_content'  => 'VARCHAR(40) DEFAULT NULL',
            // 2.145.0: sessiegeneratie, "overal uitloggen" (includes/portaal_login.php).
            'portaal_sessie_gen' => 'INT NOT NULL DEFAULT 0',
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
    $GLOBALS['_p13_schema_actueel'] = false;
    $vorige = '';
    try {
        $q = db()->prepare("SELECT waarde FROM instellingen WHERE sleutel = 'db.failsafe_versie' LIMIT 1");
        $q->execute();
        $vorige = (string)$q->fetchColumn();
    } catch (\Throwable $e) { /* tabel bestaat nog niet: gewoon de volledige run doen */ }
    if (!$forceer && $vorige === $versie) { $GLOBALS['_p13_schema_actueel'] = true; return; }

    // Eén tegelijk: komen er twee eerste verzoeken na een uitrol tegelijk binnen, dan doet de één
    // de back-up en de update; de ander slaat hem over (en ziet het schema bij het volgende verzoek).
    $db_lock = null;
    try { $db_lock = db(); $slot = (int)$db_lock->query("SELECT GET_LOCK('p13_db_failsafe', 0)")->fetchColumn(); }
    catch (\Throwable $e) { $slot = 1; $db_lock = null; }   // geen GET_LOCK (bijv. SQLite in tests): gewoon door
    if ($slot !== 1) { $GLOBALS['_p13_schema_actueel'] = true; return; }
    $los = function () use ($db_lock): void {
        if ($db_lock) { try { $db_lock->query("SELECT RELEASE_LOCK('p13_db_failsafe')"); } catch (\Throwable $e) {} }
    };

    // Een echte versiewissel op een bestaande database? Dan eerst een volledige back-up, vóór er
    // ook maar één tabel of kolom verandert. Lukt die niet, dan gaat de update tóch door (anders
    // draait nieuwe code op een oud schema): de systeemcontrole wordt oranje en het staat in het log.
    if ($vorige !== $versie && $vorige !== '' && function_exists('backup_maak')) {
        $res = backup_maak(db(), 'v' . $vorige . '-naar-v' . $versie);
        if ($res['ok']) {
            try {
                db()->prepare("INSERT INTO instellingen (sleutel, waarde) VALUES ('db.laatste_backup', ?) ON DUPLICATE KEY UPDATE waarde = VALUES(waarde)")
                    ->execute([json_encode(['bestand' => basename((string)$res['bestand']), 'op' => date('Y-m-d H:i:s'),
                                            'van' => $vorige, 'naar' => $versie, 'grootte' => $res['grootte']])]);
                db()->prepare("DELETE FROM instellingen WHERE sleutel = 'db.backup_fout'")->execute();
            } catch (\Throwable $e) {}
        } else {
            $melding = 'Back-up vóór de update van ' . $vorige . ' naar ' . $versie . ' mislukt: ' . $res['fout'];
            error_log('db_failsafe: ' . $melding);
            try {
                db()->prepare("INSERT INTO instellingen (sleutel, waarde) VALUES ('db.backup_fout', ?) ON DUPLICATE KEY UPDATE waarde = VALUES(waarde)")
                    ->execute([json_encode(['melding' => $melding, 'op' => date('Y-m-d H:i:s')])]);
            } catch (\Throwable $e) {}
        }
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
        'inzet_afspraak_auto_setup',     // na loonplan: de vastgestelde afspraak
        'loonplan_slot_auto_setup',      // vastzetten na het TW-gesprek
        'loonplan_extra_auto_setup',     // extra personen in de loonplanning (2.140.0)
        'rechten_auto_setup',
        'beschikbaarheid_dag_auto_setup',
        'audit_dag_auto_setup',
        'pag_auto_setup',
        'optout_auto_setup',
        'seintje_auto_setup',
        'werving_seintje_auto_setup',    // seintje buiten het seizoen (includes/seintje_werving.php)
        'sjablonen_auto_setup',
        'meldingen_log_auto_setup',
        'personen_kanaal_auto_setup',
        'bijlagen_auto_setup',
        'gepland_auto_setup',
        'documenten_auto_setup',
        'foto_auto_setup',         // na documenten: verhuist foto's naar de afgeschermde map
        'ondertekening_bewijs_auto_setup',
        'portaal_login_setup',
        'beschikbaarheid2_auto_setup',
        'tw_auto_setup',
        'lg_feedback_auto_setup',
        'intrek_auto_setup',
        'beheerder_bezoek_auto_setup',
        'ratelimit_auto_setup',
        'portaal_berichten_kolom_verzeker',
        'blk_kolommen_verzeker',
        'seizoen_kolommen_verzeker',
        'loon_tarieven_auto_setup',      // na de seizoenkolommen: het jaar komt uit datum_werk_start
        'live_auto_setup',               // live bijwerken (2.155.0)
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

        // beheerders.rol was ENUM('begeleider','bestuur','admin'): de rol penningmeester (en elke
        // rol die je bij Rollen & rechten toevoegt) paste er niet in. MariaDB zette er dan stil ''
        // van, en zo'n account had geen enkel recht. De rollen staan in de tabel `rollen`; de kolom
        // hoeft ze niet te kennen.
        if (db_tabel_bestaat($db, 'beheerders')) {
            try {
                $col = $db->query("SHOW COLUMNS FROM beheerders LIKE 'rol'")->fetch();
                if ($col && stripos((string)($col['Type'] ?? ''), 'enum') !== false) {
                    $db->exec("ALTER TABLE beheerders MODIFY COLUMN `rol` VARCHAR(40) NOT NULL DEFAULT 'begeleider'");
                    $db->exec("UPDATE beheerders SET `rol` = 'begeleider' WHERE `rol` = ''");
                    error_log('db_failsafe: beheerders.rol omgezet van ENUM naar VARCHAR(40)');
                }
            } catch (\Exception $e) { error_log('db_failsafe beheerders.rol: ' . $e->getMessage()); }
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

        // Speciaal: sollicitaties.fase kende 'verwijderd_door_sollicitant' en 'afgehaakt' niet,
        // terwijl portaal/gegevens.php de eerste zet en de selecties op beide filteren. In
        // strict mode gooide die UPDATE een fout, de transactie rolde terug, en kon een
        // sollicitant zijn eigen account nooit verwijderen (AVG art. 17). Alleen achteraan
        // toevoegen: bestaande waarden en hun volgorde blijven gelijk.
        if (db_tabel_bestaat($db, 'sollicitaties')) {
            try {
                $col = $db->query("SHOW COLUMNS FROM sollicitaties LIKE 'fase'")->fetch();
                $type = (string)($col['Type'] ?? '');
                if ($col && stripos($type, 'enum') !== false && stripos($type, "'verwijderd_door_sollicitant'") === false) {
                    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $m);
                    $waarden = $m[1];
                    foreach (['afgehaakt', 'verwijderd_door_sollicitant'] as $nieuw) {
                        if (!in_array($nieuw, $waarden, true)) $waarden[] = $nieuw;
                    }
                    $lijst = implode(',', array_map(fn($w) => $db->quote($w), $waarden));
                    $db->exec("ALTER TABLE sollicitaties MODIFY COLUMN `fase` ENUM($lijst) NOT NULL DEFAULT 'aangemeld'");
                    error_log('db_failsafe: sollicitaties.fase uitgebreid met afgehaakt en verwijderd_door_sollicitant');
                }
            } catch (\Exception $e) { error_log('db_failsafe fase-enum: ' . $e->getMessage()); }
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
            $GLOBALS['_p13_schema_actueel'] = true;
        } catch (\Exception $e) { error_log('db_failsafe versievlag: ' . $e->getMessage()); }
    } catch (\Exception $e) {
        error_log('db_failsafe kolom-check: ' . $e->getMessage());
    }
    $los();
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

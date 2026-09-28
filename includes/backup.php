<?php
/**
 * Paal 13 — Automatische databaseback-up vóór een schema-update.
 *
 * Een nieuwe versie past bij het eerste verzoek het schema aan (db_failsafe). Vóór dat gebeurt,
 * maakt dit bestand een volledige dump van de database. Op productie is dat een harde voorwaarde:
 * lukt de back-up niet, dan voert db_failsafe() niets door (en probeert het bij het volgende
 * verzoek opnieuw). Zo kan een release nooit echte data wijzigen zonder dat er een terugweg is.
 *
 * Opslag: BACKUP_MAP uit config.php, anders <DOCUMENTEN_MAP>/db-backups (buiten de webroot).
 * Bestanden: db-<datum>-<tijd>-v<van>-naar-v<naar>.sql.gz, rechten 0600, met een .htaccess die
 * alles weigert voor het geval de map toch in de webroot staat. Bewaard: altijd de laatste 3,
 * de rest hooguit 30 dagen (AVG: back-ups niet langer dan nodig; zie het runbook).
 *
 * Terugzetten: Plesk › Databases › Importeren (accepteert .sql.gz), of
 *   gunzip -c bestand.sql.gz | mysql -u … -p databasenaam
 * Het bestand begint met DROP TABLE IF EXISTS per tabel: importeren zet de database terug naar
 * precies het moment van de back-up.
 */

if (!function_exists('backup_maak')) {

/** Hoeveel back-ups altijd blijven staan, en hoe oud de rest hooguit mag worden. */
function backup_bewaar_minimaal(): int { return 3; }
function backup_bewaar_dagen(): int { return 30; }

/**
 * De map voor back-ups, aangemaakt en afgeschermd; null als er niet te schrijven valt.
 * Een back-up is de hele database: hij mag nooit binnen de webroot staan (een .htaccess helpt
 * niet als nginx de bestanden serveert). Alleen op de testomgeving mag dat als noodoplossing.
 */
function backup_map(): ?string {
    $dir = '';
    if (defined('BACKUP_MAP') && trim((string)BACKUP_MAP) !== '' && !str_contains((string)BACKUP_MAP, '{{')) {
        $dir = rtrim((string)BACKUP_MAP, '/\\');
    } elseif (defined('DOCUMENTEN_MAP') && trim((string)DOCUMENTEN_MAP) !== '' && !str_contains((string)DOCUMENTEN_MAP, '{{')) {
        $dir = rtrim((string)DOCUMENTEN_MAP, '/\\') . '/db-backups';
    } elseif (function_exists('is_test_omgeving') && is_test_omgeving() && function_exists('documenten_map') && ($doc = documenten_map())) {
        $dir = $doc . '/db-backups';
    }
    if ($dir === '') return null;
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (!is_dir($dir) || !is_writable($dir)) return null;
    $web = realpath(dirname(__DIR__));
    $echt = realpath($dir);
    if ($web && $echt && str_starts_with($echt . DIRECTORY_SEPARATOR, $web . DIRECTORY_SEPARATOR)
        && !(function_exists('is_test_omgeving') && is_test_omgeving())) {
        error_log('backup_map: ' . $echt . ' staat binnen de webroot; geen back-up daar');
        return null;
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    return $dir;
}

/** SQL-literal voor één waarde; binaire kolommen als hex, zodat de tekenset niets kan verminken. */
function backup_waarde(PDO $db, $v, bool $binair): string {
    if ($v === null) return 'NULL';
    if ($binair) return $v === '' ? "''" : '0x' . bin2hex((string)$v);
    return $db->quote((string)$v);
}

/**
 * Volledige dump van de huidige database naar een .sql.gz.
 * @return array ['ok' => bool, 'bestand' => ?string, 'grootte' => int, 'tabellen' => int, 'fout' => ?string]
 */
function backup_maak(PDO $db, string $label = 'handmatig'): array {
    $uit = ['ok' => false, 'bestand' => null, 'grootte' => 0, 'tabellen' => 0, 'fout' => null];
    if (!function_exists('gzopen')) { $uit['fout'] = 'zlib ontbreekt (gzopen)'; return $uit; }
    $dir = backup_map();
    if (!$dir) { $uit['fout'] = 'geen schrijfbare back-upmap (BACKUP_MAP of DOCUMENTEN_MAP)'; return $uit; }

    $label = trim(preg_replace('/[^a-z0-9.\-]+/', '-', strtolower($label)), '-') ?: 'backup';
    $naam  = 'db-' . date('Ymd-His') . '-' . substr($label, 0, 60) . '.sql.gz';
    $pad   = $dir . '/' . $naam;
    $tmp   = $pad . '.part';

    $gz = @gzopen($tmp, 'wb6');
    if (!$gz) { $uit['fout'] = 'kan ' . $tmp . ' niet openen'; return $uit; }
    $schrijf = function (string $s) use ($gz): void {
        if (gzwrite($gz, $s) === false) throw new \RuntimeException('schrijven naar de back-up mislukt');
    };

    try {
        $dbnaam = (string)$db->query('SELECT DATABASE()')->fetchColumn();
        $schrijf("-- Paal 13 databaseback-up\n-- Database: {$dbnaam}\n-- Gemaakt: " . date('c') . "\n-- Reden: {$label}\n"
               . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tabellen = [];
        foreach ($db->query("SHOW FULL TABLES")->fetchAll(PDO::FETCH_NUM) as $r) {
            if (strtoupper((string)($r[1] ?? '')) === 'BASE TABLE') $tabellen[] = (string)$r[0];
        }
        foreach ($tabellen as $t) {
            $tq = '`' . str_replace('`', '``', $t) . '`';
            $create = $db->query("SHOW CREATE TABLE $tq")->fetch(PDO::FETCH_NUM);
            $schrijf("DROP TABLE IF EXISTS $tq;\n" . $create[1] . ";\n\n");

            $binair = [];
            foreach ($db->query("SHOW COLUMNS FROM $tq")->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $binair[(string)$c['Field']] = (bool)preg_match('/blob|binary/i', (string)$c['Type']);
            }
            $kolommen = array_keys($binair);
            if (!$kolommen) continue;
            $kop = "INSERT INTO $tq (`" . implode('`,`', array_map(fn($k) => str_replace('`', '``', $k), $kolommen)) . "`) VALUES\n";

            $st = $db->query("SELECT * FROM $tq");
            $blok = [];
            while (($rij = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
                $vals = [];
                foreach ($kolommen as $k) $vals[] = backup_waarde($db, $rij[$k] ?? null, $binair[$k]);
                $blok[] = '(' . implode(',', $vals) . ')';
                if (count($blok) >= 100) { $schrijf($kop . implode(",\n", $blok) . ";\n"); $blok = []; }
            }
            if ($blok) $schrijf($kop . implode(",\n", $blok) . ";\n");
            $schrijf("\n");
        }
        $schrijf("SET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n-- Einde back-up (" . count($tabellen) . " tabellen)\n");
        if (!gzclose($gz)) throw new \RuntimeException('afsluiten van de back-up mislukt');
        $gz = null;
        if (!@rename($tmp, $pad)) throw new \RuntimeException('hernoemen van de back-up mislukt');
        @chmod($pad, 0600);
        $uit = ['ok' => true, 'bestand' => $pad, 'grootte' => (int)@filesize($pad), 'tabellen' => count($tabellen), 'fout' => null];
    } catch (\Throwable $e) {
        if ($gz) @gzclose($gz);
        @unlink($tmp);
        $uit['fout'] = $e->getMessage();
        return $uit;
    }
    backup_opruimen($dir);
    return $uit;
}

/** Oude back-ups weg: de nieuwste backup_bewaar_minimaal() altijd houden, de rest na backup_bewaar_dagen(). */
function backup_opruimen(?string $dir = null): int {
    $dir ??= backup_map();
    if (!$dir) return 0;
    $bestanden = glob($dir . '/db-*.sql.gz') ?: [];
    usort($bestanden, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $grens = time() - backup_bewaar_dagen() * 86400;
    $weg = 0;
    foreach (array_slice($bestanden, backup_bewaar_minimaal()) as $f) {
        if (filemtime($f) < $grens && @unlink($f)) $weg++;
    }
    // Afgebroken pogingen (.part) van meer dan een dag oud
    foreach (glob($dir . '/db-*.part') ?: [] as $f) if (filemtime($f) < time() - 86400) @unlink($f);
    return $weg;
}

/** De bestaande back-ups, nieuwste eerst: [['naam','grootte','op'], …]. */
function backup_lijst(): array {
    $dir = backup_map();
    if (!$dir) return [];
    $uit = [];
    foreach (glob($dir . '/db-*.sql.gz') ?: [] as $f) {
        $uit[] = ['naam' => basename($f), 'grootte' => (int)filesize($f), 'op' => date('Y-m-d H:i:s', (int)filemtime($f))];
    }
    usort($uit, fn($a, $b) => strcmp($b['op'], $a['op']));
    return $uit;
}

}

<?php
/**
 * Paal 13 — Installatie Wizard (volledig zelfstandig, geen dependencies)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Fouten via eigen systeem weergeven

session_name('p13_setup');
session_start();

$config_path = __DIR__ . '/../includes/config.php';
$al_klaar    = file_exists($config_path);

// Beveiliging: als het systeem al geïnstalleerd is, is de wizard niet meer nodig.
// Volledig blokkeren voorkomt dat iemand de installatie opnieuw kan starten.
if ($al_klaar && empty($_GET['force_toon'])) {
    http_response_code(403);
    die('<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Al geïnstalleerd</title></head><body style="font-family:sans-serif;max-width:520px;margin:60px auto;padding:0 20px;line-height:1.6;color:#1a1a1a"><h2 style="color:#054d8a">Systeem is al geïnstalleerd</h2><p>De installatiewizard is uitgeschakeld omdat het systeem al draait. Verwijder de <code>setup/</code>-map van de server voor extra veiligheid.</p></body></html>');
}

$stap        = (int)($_GET['stap'] ?? 1);
$actie       = $_POST['actie'] ?? '';
$resultaat   = null; // ['ok' => bool, 'msg' => string, 'detail' => string]

// ══════════════════════════════════════════════════════════════
// INLINE HELPERS (geen require_once nodig)
// ══════════════════════════════════════════════════════════════

function setup_pdo(string $host, string $db, string $user, string $pass): PDO {
    return new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function setup_smtp_test(string $host, int $port, string $user, string $pass, string $encrypt, string $naar): array {
    try {
        if ($encrypt === 'ssl') {
            $sock = @fsockopen("ssl://$host", $port, $en, $es, 8);
        } else {
            $sock = @fsockopen($host, $port, $en, $es, 8);
        }
        if (!$sock) return ['ok' => false, 'msg' => "Kan $host:$port niet bereiken: $es"];
        stream_set_timeout($sock, 8);

        $lees = function() use ($sock) {
            $r = '';
            while ($l = fgets($sock, 512)) { $r .= $l; if ($l[3] === ' ') break; }
            return $r;
        };
        $stuur = function($c) use ($sock, $lees) { fwrite($sock, $c . "\r\n"); return $lees(); };

        $welkom = $lees();
        if (!str_starts_with($welkom, '2')) { fclose($sock); return ['ok' => false, 'msg' => "Server weigert verbinding: $welkom"]; }

        $stuur("EHLO localhost");
        if ($encrypt === 'tls') {
            $r = $stuur("STARTTLS");
            if (!str_starts_with($r, '2')) { fclose($sock); return ['ok' => false, 'msg' => "STARTTLS geweigerd: $r"]; }
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($sock); return ['ok' => false, 'msg' => 'TLS handshake mislukt']; }
            $stuur("EHLO localhost");
        }
        if ($user) {
            $r = $stuur("AUTH LOGIN");
            if (str_starts_with($r, '334')) {
                $stuur(base64_encode($user));
                $r = $stuur(base64_encode($pass));
                if (!str_starts_with($r, '235')) { fclose($sock); return ['ok' => false, 'msg' => "Authenticatie mislukt: $r"]; }
            }
        }
        $stuur("QUIT"); fclose($sock);
        return ['ok' => true, 'msg' => "SMTP verbinding geslaagd met $host:$port"];
    } catch (Throwable $e) {
        return ['ok' => false, 'msg' => 'Fout: ' . $e->getMessage()];
    }
}

function setup_oidc_test(string $issuer, string $slug): array {
    if (!function_exists('curl_init')) return ['ok' => false, 'msg' => 'cURL niet beschikbaar'];
    $url = rtrim($issuer, '/') . '/application/o/' . trim($slug, '/') . '/.well-known/openid-configuration';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_FOLLOWLOCATION => true]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return ['ok' => false, 'msg' => "Verbindingsfout: $err"];
    if ($code !== 200) return ['ok' => false, 'msg' => "HTTP $code — URL: $url"];
    $data = json_decode($body, true);
    if (!$data || !isset($data['authorization_endpoint'])) return ['ok' => false, 'msg' => "Geen geldig OIDC endpoint. Response: " . substr($body, 0, 100)];
    return ['ok' => true, 'msg' => "✅ Authentik OIDC endpoint gevonden", 'detail' => "Authorization: " . $data['authorization_endpoint']];
}

// Volledig SQL schema
function setup_schema(): string {
    return <<<SQL
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS `seizoenen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `naam` VARCHAR(50) NOT NULL,
  `status` ENUM('gesloten','open') NOT NULL DEFAULT 'gesloten',
  `datum_selectiedag` DATE DEFAULT NULL,
  `locatie_selectiedag` VARCHAR(200) DEFAULT NULL,
  `datum_tw_start` DATE DEFAULT NULL,
  `datum_tw_eind` DATE DEFAULT NULL,
  `locatie_tw` VARCHAR(200) DEFAULT NULL,
  `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `personen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `voornaam` VARCHAR(100) NOT NULL,
  `tussenvoegsel` VARCHAR(30) DEFAULT NULL,
  `achternaam` VARCHAR(100) NOT NULL,
  `email` VARCHAR(200) NOT NULL UNIQUE,
  `telefoon` VARCHAR(30) DEFAULT NULL,
  `geboortedatum` DATE DEFAULT NULL,
  `geslacht` ENUM('man','vrouw','anders','onbekend') DEFAULT 'onbekend',
  `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sollicitaties` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `persoon_id` INT NOT NULL,
  `seizoen_id` INT NOT NULL,
  `fase` ENUM('aangemeld','uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest','uitgenodigd_tw','tw_geweest','aangenomen','afgewezen') NOT NULL DEFAULT 'aangemeld',
  `terugkerend` TINYINT(1) NOT NULL DEFAULT 0,
  `notities` TEXT DEFAULT NULL,
  `portaal_token` VARCHAR(64) DEFAULT NULL UNIQUE,
  `aangemeld_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gewijzigd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniek_per_seizoen` (`persoon_id`, `seizoen_id`),
  FOREIGN KEY (`persoon_id`) REFERENCES `personen`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`seizoen_id`) REFERENCES `seizoenen`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `blk_formulieren` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sollicitatie_id` INT NOT NULL UNIQUE,
  `adres` VARCHAR(300) DEFAULT NULL,
  `opleiding` TEXT DEFAULT NULL,
  `werkervaring` TEXT DEFAULT NULL,
  `diplomas` TEXT DEFAULT NULL,
  `motivatie_lifeguard` TEXT DEFAULT NULL,
  `motivatie_strand` TEXT DEFAULT NULL,
  `beschikbaarheid_intentie` TEXT DEFAULT NULL,
  `beschikbaarheid_uitzonderingen` TEXT DEFAULT NULL,
  `social_media` VARCHAR(300) DEFAULT NULL,
  `foto_url` VARCHAR(500) DEFAULT NULL,
  `bijzonderheden` TEXT DEFAULT NULL,
  `ingevuld_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `bijgewerkt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`sollicitatie_id`) REFERENCES `sollicitaties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `beoordelingen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sollicitatie_id` INT NOT NULL UNIQUE,
  `groep` VARCHAR(50) DEFAULT NULL,
  `aanwezig` TINYINT(1) DEFAULT NULL,
  `score_communicatie` TINYINT DEFAULT NULL,
  `score_teamwork` TINYINT DEFAULT NULL,
  `score_conditie` TINYINT DEFAULT NULL,
  `score_water` TINYINT DEFAULT NULL,
  `score_attitude` TINYINT DEFAULT NULL,
  `aantekeningen` TEXT DEFAULT NULL,
  `beoordelaar` VARCHAR(100) DEFAULT NULL,
  `beoordeeld_op` DATETIME DEFAULT NULL,
  FOREIGN KEY (`sollicitatie_id`) REFERENCES `sollicitaties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `beschikbaarheid` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sollicitatie_id` INT NOT NULL UNIQUE,
  `weken_json` JSON DEFAULT NULL,
  `uitzonderingen` TEXT DEFAULT NULL,
  `definitief_json` JSON DEFAULT NULL,
  `dagloon` DECIMAL(8,2) DEFAULT NULL,
  `functie` ENUM('aspirant','lifeguard','senior_lifeguard') DEFAULT 'aspirant',
  `ingevuld_op` DATETIME DEFAULT NULL,
  `definitief_op` DATETIME DEFAULT NULL,
  FOREIGN KEY (`sollicitatie_id`) REFERENCES `sollicitaties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `beheerders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `naam` VARCHAR(150) NOT NULL,
  `email` VARCHAR(200) NOT NULL UNIQUE,
  `wachtwoord` VARCHAR(255) DEFAULT NULL,
  `rol` ENUM('begeleider','bestuur','admin') NOT NULL DEFAULT 'bestuur',
  `actief` TINYINT(1) NOT NULL DEFAULT 1,
  `authentik_uid` VARCHAR(200) DEFAULT NULL,
  `laatste_login` DATETIME DEFAULT NULL,
  `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `otp_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(200) NOT NULL,
  `code_hash` VARCHAR(64) NOT NULL,
  `type` VARCHAR(30) NOT NULL DEFAULT 'portaal',
  `pogingen` TINYINT NOT NULL DEFAULT 0,
  `gebruikt` TINYINT(1) NOT NULL DEFAULT 0,
  `vervalt_op` DATETIME NOT NULL,
  `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email_type` (`email`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mail_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sollicitatie_id` INT DEFAULT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'mail',
  `ontvanger` VARCHAR(200) NOT NULL,
  `onderwerp` VARCHAR(300) NOT NULL,
  `verstuurd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('verstuurd','mislukt') NOT NULL DEFAULT 'verstuurd',
  `foutmelding` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `migraties` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `versie` VARCHAR(20) NOT NULL UNIQUE,
  `beschrijving` VARCHAR(200) NOT NULL,
  `uitgevoerd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;
SQL;
}

// ══════════════════════════════════════════════════════════════
// POST VERWERKING
// ══════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Stap 1: Test database ─────────────────────────────────
    if ($actie === 'test_db') {
        $host = trim($_POST['db_host'] ?? 'localhost');
        $naam = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = trim($_POST['db_pass'] ?? '');
        try {
            // Verbinding zonder database om eerst te checken
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
            $resultaat = ['ok' => true, 'msg' => "✅ Verbinding geslaagd! MySQL versie: $ver", 'detail' => "Host: $host | Gebruiker: $user"];
        } catch (PDOException $e) {
            $resultaat = ['ok' => false, 'msg' => '❌ Verbinding mislukt: ' . $e->getMessage()];
        }
        $stap = 1;
    }

    // ── Stap 1: Opslaan & aanmaken ───────────────────────────
    if ($actie === 'sla_db') {
        $host    = trim($_POST['db_host']);
        $db_naam = trim($_POST['db_name']);
        $user    = trim($_POST['db_user']);
        $pass    = trim($_POST['db_pass']);
        $app_url = rtrim(trim($_POST['app_url']), '/');
        $ww      = $_POST['admin_wachtwoord'];

        if (!$host || !$db_naam || !$user || !$app_url) {
            $resultaat = ['ok' => false, 'msg' => '❌ Vul alle verplichte velden in.'];
            $stap = 1;
        } elseif (strlen($ww) < 6) {
            $resultaat = ['ok' => false, 'msg' => '❌ Admin wachtwoord minimaal 6 tekens.'];
            $stap = 1;
        } else {
            try {
                // Verbinding maken (zonder database eerst)
                $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                // Database aanmaken als die niet bestaat
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_naam` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `$db_naam`");

                // ── Alles leegmaken ─────────────────────────────────
                // Foreign key checks uit zodat we in elke volgorde kunnen droppen
                $pdo->exec("SET foreign_key_checks = 0");

                // Alle bestaande tabellen droppen
                $bestaande = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($bestaande as $tabel) {
                    $pdo->exec("DROP TABLE IF EXISTS `$tabel`");
                }

                $pdo->exec("SET foreign_key_checks = 1");

                // ── Alle tabellen opnieuw aanmaken ──────────────────
                $tabellen_sql = [
                    "CREATE TABLE `seizoenen` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `naam` VARCHAR(50) NOT NULL,
                        `status` ENUM('gesloten','open') NOT NULL DEFAULT 'gesloten',
                        `datum_selectiedag` DATE DEFAULT NULL,
                        `locatie_selectiedag` VARCHAR(200) DEFAULT NULL,
                        `datum_tw_start` DATE DEFAULT NULL,
                        `datum_tw_eind` DATE DEFAULT NULL,
                        `locatie_tw` VARCHAR(200) DEFAULT NULL,
                        `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                    "CREATE TABLE `personen` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `voornaam` VARCHAR(100) NOT NULL,
                        `tussenvoegsel` VARCHAR(30) DEFAULT NULL,
                        `achternaam` VARCHAR(100) NOT NULL,
                        `email` VARCHAR(200) NOT NULL UNIQUE,
                        `telefoon` VARCHAR(30) DEFAULT NULL,
                        `geboortedatum` DATE DEFAULT NULL,
                        `geslacht` ENUM('man','vrouw','anders','onbekend') DEFAULT 'onbekend',
                        `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                    "CREATE TABLE `sollicitaties` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `persoon_id` INT NOT NULL,
                        `seizoen_id` INT NOT NULL,
                        `fase` ENUM('aangemeld','uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest','uitgenodigd_tw','tw_geweest','aangenomen','afgewezen') NOT NULL DEFAULT 'aangemeld',
                        `terugkerend` TINYINT(1) NOT NULL DEFAULT 0,
                        `notities` TEXT DEFAULT NULL,
                        `portaal_token` VARCHAR(64) DEFAULT NULL UNIQUE,
                        `aangemeld_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `gewijzigd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY `uniek_per_seizoen` (`persoon_id`, `seizoen_id`),
                        FOREIGN KEY (`persoon_id`) REFERENCES `personen`(`id`) ON DELETE CASCADE,
                        FOREIGN KEY (`seizoen_id`) REFERENCES `seizoenen`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                    "CREATE TABLE `blk_formulieren` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `sollicitatie_id` INT NOT NULL UNIQUE,
                        `adres` VARCHAR(300) DEFAULT NULL,
                        `opleiding` TEXT DEFAULT NULL,
                        `werkervaring` TEXT DEFAULT NULL,
                        `diplomas` TEXT DEFAULT NULL,
                        `motivatie_lifeguard` TEXT DEFAULT NULL,
                        `motivatie_strand` TEXT DEFAULT NULL,
                        `beschikbaarheid_intentie` TEXT DEFAULT NULL,
                        `beschikbaarheid_uitzonderingen` TEXT DEFAULT NULL,
                        `social_media` VARCHAR(300) DEFAULT NULL,
                        `foto_url` VARCHAR(500) DEFAULT NULL,
                        `bijzonderheden` TEXT DEFAULT NULL,
                        `ingevuld_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `bijgewerkt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (`sollicitatie_id`) REFERENCES `sollicitaties`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                    "CREATE TABLE `beoordelingen` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `sollicitatie_id` INT NOT NULL UNIQUE,
                        `groep` VARCHAR(50) DEFAULT NULL,
                        `aanwezig` TINYINT(1) DEFAULT NULL,
                        `score_communicatie` TINYINT DEFAULT NULL,
                        `score_teamwork` TINYINT DEFAULT NULL,
                        `score_conditie` TINYINT DEFAULT NULL,
                        `score_water` TINYINT DEFAULT NULL,
                        `score_attitude` TINYINT DEFAULT NULL,
                        `aantekeningen` TEXT DEFAULT NULL,
                        `beoordelaar` VARCHAR(100) DEFAULT NULL,
                        `beoordeeld_op` DATETIME DEFAULT NULL,
                        FOREIGN KEY (`sollicitatie_id`) REFERENCES `sollicitaties`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                    "CREATE TABLE `beschikbaarheid` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `sollicitatie_id` INT NOT NULL UNIQUE,
                        `weken_json` JSON DEFAULT NULL,
                        `uitzonderingen` TEXT DEFAULT NULL,
                        `definitief_json` JSON DEFAULT NULL,
                        `dagloon` DECIMAL(8,2) DEFAULT NULL,
                        `functie` ENUM('aspirant','lifeguard','senior_lifeguard') DEFAULT 'aspirant',
                        `ingevuld_op` DATETIME DEFAULT NULL,
                        `definitief_op` DATETIME DEFAULT NULL,
                        FOREIGN KEY (`sollicitatie_id`) REFERENCES `sollicitaties`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                    "CREATE TABLE `beheerders` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `naam` VARCHAR(150) NOT NULL,
                        `email` VARCHAR(200) NOT NULL UNIQUE,
                        `wachtwoord` VARCHAR(255) DEFAULT NULL,
                        `rol` ENUM('begeleider','bestuur','admin') NOT NULL DEFAULT 'bestuur',
                        `actief` TINYINT(1) NOT NULL DEFAULT 1,
                        `authentik_uid` VARCHAR(200) DEFAULT NULL,
                        `laatste_login` DATETIME DEFAULT NULL,
                        `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                    "CREATE TABLE `otp_tokens` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `email` VARCHAR(200) NOT NULL,
                        `code_hash` VARCHAR(64) NOT NULL,
                        `type` VARCHAR(30) NOT NULL DEFAULT 'portaal',
                        `pogingen` TINYINT NOT NULL DEFAULT 0,
                        `gebruikt` TINYINT(1) NOT NULL DEFAULT 0,
                        `vervalt_op` DATETIME NOT NULL,
                        `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_email_type` (`email`, `type`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                    "CREATE TABLE `mail_log` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `sollicitatie_id` INT DEFAULT NULL,
                        `type` VARCHAR(50) NOT NULL DEFAULT 'mail',
                        `ontvanger` VARCHAR(200) NOT NULL,
                        `onderwerp` VARCHAR(300) NOT NULL,
                        `verstuurd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `status` ENUM('verstuurd','mislukt') NOT NULL DEFAULT 'verstuurd',
                        `foutmelding` TEXT DEFAULT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                    "CREATE TABLE `migraties` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `versie` VARCHAR(20) NOT NULL UNIQUE,
                        `beschrijving` VARCHAR(200) NOT NULL,
                        `uitgevoerd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                ];

                foreach ($tabellen_sql as $sql) {
                    $pdo->exec($sql);
                }

                // ── Standaard data invoegen ─────────────────────────
                $pdo->exec("INSERT INTO `seizoenen` (`naam`, `status`) VALUES ('Zomer 2026', 'gesloten')");
                $pdo->exec("INSERT INTO `migraties` (`versie`, `beschrijving`) VALUES ('0001', 'Basisschema installatie'), ('0002', 'Beheerders en OTP')");

                // Admin account aanmaken met het ingevulde wachtwoord
                $hash = password_hash($ww, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO `beheerders` (`naam`, `email`, `wachtwoord`, `rol`) VALUES ('Beheerder', 'admin@paal13.com', ?, 'admin')")
                    ->execute([$hash]);

                $tabellen = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

                $_SESSION['setup'] = compact('host', 'db_naam', 'user', 'pass', 'app_url');
                $_SESSION['setup_stap1_ok'] = true;

                $resultaat = [
                    'ok'     => true,
                    'msg'    => "✅ Database volledig ingericht! " . count($tabellen) . " tabellen aangemaakt.",
                    'detail' => "Aangemaakt: " . implode(', ', $tabellen) . " | Admin: admin@paal13.com",
                ];
                $stap = 2;
            } catch (PDOException $e) {
                $resultaat = ['ok' => false, 'msg' => '❌ Database fout: ' . $e->getMessage()];
                $stap = 1;
            }
        }
    }

    // ── Stap 2: Test e-mail ───────────────────────────────────
    if ($actie === 'test_mail') {
        $smtp_host    = trim($_POST['smtp_host'] ?? '');
        $smtp_port    = (int)($_POST['smtp_port'] ?? 587);
        $smtp_user    = trim($_POST['smtp_user'] ?? '');
        $smtp_pass    = $_POST['smtp_pass'] ?? '';
        $smtp_encrypt = $_POST['smtp_encrypt'] ?? 'tls';
        $mail_bestuur = trim($_POST['mail_bestuur'] ?? '');

        if (!$smtp_host) {
            $resultaat = ['ok' => false, 'msg' => '❌ Vul een SMTP host in om te testen.'];
        } elseif (!$mail_bestuur) {
            $resultaat = ['ok' => false, 'msg' => '❌ Vul het bestuur e-mailadres in om de testmail naartoe te sturen.'];
        } else {
            $resultaat = setup_smtp_test($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_encrypt, $mail_bestuur);
            if ($resultaat['ok']) {
                $resultaat['msg'] = "✅ SMTP verbinding geslaagd! ({$smtp_host}:{$smtp_port})";
            }
        }
        $stap = 2;
    }

    // ── Stap 2: Opslaan mail ──────────────────────────────────
    if ($actie === 'sla_mail') {
        $_SESSION['setup']['mail_from']    = trim($_POST['mail_from']);
        $_SESSION['setup']['mail_bestuur'] = trim($_POST['mail_bestuur']);
        $_SESSION['setup']['smtp_host']    = trim($_POST['smtp_host']);
        $_SESSION['setup']['smtp_port']    = (int)($_POST['smtp_port'] ?? 587);
        $_SESSION['setup']['smtp_user']    = trim($_POST['smtp_user']);
        $_SESSION['setup']['smtp_pass']    = $_POST['smtp_pass'] ?? '';
        $_SESSION['setup']['smtp_encrypt'] = $_POST['smtp_encrypt'] ?? 'tls';
        $_SESSION['setup_stap2_ok']        = true;
        $resultaat = ['ok' => true, 'msg' => '✅ E-mailinstellingen opgeslagen.'];
        $stap = 3;
    }

    // ── Stap 3: Test Authentik ────────────────────────────────
    if ($actie === 'test_oidc') {
        $issuer = rtrim(trim($_POST['oidc_issuer'] ?? ''), '/');
        $slug   = trim($_POST['oidc_slug'] ?? '', '/');
        if (!$issuer || !$slug) {
            $resultaat = ['ok' => false, 'msg' => '❌ Vul Authentik URL en slug in.'];
        } else {
            $resultaat = setup_oidc_test($issuer, $slug);
        }
        $stap = 3;
    }

    // ── Stap 3: Opslaan & config schrijven ────────────────────
    if ($actie === 'sla_oidc' || $actie === 'overslaan_oidc') {
        $setup = $_SESSION['setup'] ?? [];
        if (empty($setup)) {
            $resultaat = ['ok' => false, 'msg' => '❌ Sessiedata verloren. Begin opnieuw bij stap 1.'];
            $stap = 1;
        } else {
            $oidc_issuer = $actie === 'overslaan_oidc' ? '' : rtrim(trim($_POST['oidc_issuer'] ?? ''), '/');
            $oidc_id     = $actie === 'overslaan_oidc' ? '' : trim($_POST['oidc_client_id'] ?? '');
            $oidc_secret = $actie === 'overslaan_oidc' ? '' : trim($_POST['oidc_client_secret'] ?? '');
            $oidc_slug   = $actie === 'overslaan_oidc' ? '' : trim($_POST['oidc_slug'] ?? '', '/');

            $smtp_port = (int)($setup['smtp_port'] ?? 587);
            $app_url   = $setup['app_url'] ?? '';

            $config = '<?php' . "\n" .
                "// Paal 13 Sollicitatie Systeem — Configuratie\n" .
                "// Aangemaakt door de installatie wizard op " . date('Y-m-d H:i:s') . "\n\n" .
                "define('DB_HOST',            '" . addslashes($setup['host'] ?? '') . "');\n" .
                "define('DB_NAME',            '" . addslashes($setup['db_naam'] ?? '') . "');\n" .
                "define('DB_USER',            '" . addslashes($setup['user'] ?? '') . "');\n" .
                "define('DB_PASS',            '" . addslashes($setup['pass'] ?? '') . "');\n" .
                "define('DB_CHARSET',         'utf8mb4');\n\n" .
                "define('APP_URL',            '" . addslashes($app_url) . "');\n\n" .
                "// E-mail\n" .
                "define('MAIL_FROM',          '" . addslashes($setup['mail_from'] ?? 'noreply@paal13.com') . "');\n" .
                "define('MAIL_FROM_NAME',     'Reddingsbrigade Paal 13');\n" .
                "define('MAIL_BESTUUR',       '" . addslashes($setup['mail_bestuur'] ?? '') . "');\n\n" .
                "// SMTP (leeg = PHP mail() als fallback)\n" .
                "define('SMTP_HOST',          '" . addslashes($setup['smtp_host'] ?? '') . "');\n" .
                "define('SMTP_PORT',          $smtp_port);\n" .
                "define('SMTP_USER',          '" . addslashes($setup['smtp_user'] ?? '') . "');\n" .
                "define('SMTP_PASS',          '" . addslashes($setup['smtp_pass'] ?? '') . "');\n" .
                "define('SMTP_ENCRYPT',       '" . addslashes($setup['smtp_encrypt'] ?? 'tls') . "');\n\n" .
                "// Authentik SSO (leeg = uitgeschakeld, wachtwoord login werkt altijd)\n" .
                "define('OIDC_CLIENT_ID',     '" . addslashes($oidc_id) . "');\n" .
                "define('OIDC_CLIENT_SECRET', '" . addslashes($oidc_secret) . "');\n" .
                "define('OIDC_ISSUER',        '" . addslashes($oidc_issuer) . "');\n" .
                "define('OIDC_SLUG',          '" . addslashes($oidc_slug) . "');\n" .
                "define('OIDC_REDIRECT_URI',  '" . addslashes($app_url) . "/admin/auth_callback.php');\n\n" .
                "define('SESSION_NAME',       'p13sess');\n" .
                "define('SESSION_LIFETIME',   0);\n\n" .
                "date_default_timezone_set('Europe/Amsterdam');\n" .
                "error_reporting(0);\n" .
                "ini_set('display_errors', 0);\n";

            if (file_put_contents($config_path, $config) !== false) {
                session_destroy();
                $stap = 4;
                $resultaat = ['ok' => true, 'msg' => '✅ Configuratie opgeslagen!'];
            } else {
                $resultaat = ['ok' => false, 'msg' => '❌ Kan includes/config.php niet schrijven. Controleer de rechten: chmod 755 includes/'];
                $stap = 3;
            }
        }
    }
}

// Herlees setup data als we al verder zijn
$setup    = $_SESSION['setup'] ?? [];
$app_url  = $setup['app_url'] ?? ((isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST']);
$app_url  = rtrim(preg_replace('/\/setup.*$/', '', $app_url), '/');
$redirect = $app_url . '/admin/auth_callback.php';

// Al geïnstalleerd?
if ($al_klaar && $stap < 4) {
    require_once $config_path;
    $app_url_cfg = defined('APP_URL') ? APP_URL : $app_url;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installatie — Paal 13</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#001530;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:32px 20px}
.wrap{width:100%;max-width:600px}
.logo{text-align:center;margin-bottom:24px}
.logo h1{color:#FF6B35;font-size:22px;margin-top:10px;font-weight:700}
.logo p{color:rgba(255,255,255,.5);font-size:13px;margin-top:3px}

/* Stappen indicator */
.stappen{display:flex;margin-bottom:22px;background:rgba(255,255,255,.06);border-radius:10px;overflow:hidden}
.stap-item{flex:1;padding:12px 8px;text-align:center;font-size:12px;color:rgba(255,255,255,.35);border-right:1px solid rgba(255,255,255,.08);cursor:default;transition:all .2s}
.stap-item:last-child{border-right:none}
.stap-item.actief{background:rgba(255,107,53,.2);color:#FF6B35;font-weight:600}
.stap-item.klaar{background:rgba(29,158,117,.15);color:#9FE1CB}
.stap-item .nr{display:block;font-size:18px;font-weight:700;margin-bottom:2px}

/* Kaart */
.card{background:#fff;border-radius:12px;overflow:hidden;margin-bottom:16px;box-shadow:0 4px 24px rgba(0,0,0,.2)}
.card-head{background:#002147;padding:14px 22px;color:#fff;font-weight:600;font-size:15px;display:flex;align-items:center;justify-content:space-between}
.card-body{padding:22px}

/* Resultaat banner */
.res{border-radius:8px;padding:12px 15px;font-size:13px;margin-bottom:16px;line-height:1.5}
.res.ok{background:#e1f5ee;border:1px solid #9fe1cb;color:#085041}
.res.er{background:#fff5f5;border:1px solid #feb2b2;color:#c53030}
.res.info{background:#ebf8ff;border:1px solid #90cdf4;color:#1a56a0}
.res .detail{font-size:12px;opacity:.8;margin-top:4px}

/* Formulier */
.fg{margin-bottom:14px}
.fg label{display:block;font-size:13px;font-weight:500;color:#1a2332;margin-bottom:4px}
.fg .sub{font-size:11px;color:#718096;margin-top:3px}
.fg .vereist{color:#e55a25}
input,select{width:100%;padding:9px 12px;border:1.5px solid #d8e2ee;border-radius:7px;font-size:13px;color:#1a2332;font-family:inherit;transition:border-color .15s}
input:focus,select:focus{outline:none;border-color:#FF6B35;box-shadow:0 0 0 3px rgba(255,107,53,.1)}
.rij{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.rij3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px}
hr{border:none;border-top:1px solid #e8edf4;margin:16px 0}

/* Knoppen */
.knoppen{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px solid #e8edf4}
.btn{display:inline-flex;align-items:center;gap:5px;padding:10px 18px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;border:none;color:#fff;font-family:inherit;transition:all .15s}
.btn-o{background:#FF6B35}.btn-o:hover{background:#e55a25}
.btn-n{background:#002147}.btn-n:hover{background:#003570}
.btn-g{background:#718096}
.btn-gr{background:#1D9E75}.btn-gr:hover{background:#0f6e56}
.btn-outline{background:#fff;color:#1a2332;border:1.5px solid #d8e2ee}.btn-outline:hover{border-color:#FF6B35;color:#FF6B35}
.btn-sm{padding:7px 13px;font-size:12px}
.badge{display:inline-block;font-size:11px;padding:2px 7px;border-radius:4px;font-weight:600;margin-left:6px}
.b-opt{background:#f0fdf4;color:#166534;border:1px solid #86efac}

/* Info box */
.info-box{background:#f0f8ff;border:1px solid #bce0fd;border-radius:8px;padding:13px 15px;font-size:13px;color:#1a4971;margin-bottom:14px;line-height:1.7}
.info-box strong{display:block;margin-bottom:4px;font-size:13px}
.uri-box{background:#dbeafe;border:1px solid #93c5fd;border-radius:6px;padding:8px 11px;font-family:monospace;font-size:12px;color:#1e40af;word-break:break-all;margin:6px 0;user-select:all;cursor:copy}
code{background:rgba(0,0,0,.07);padding:2px 5px;border-radius:3px;font-family:monospace;font-size:12px}

/* Loading state */
.btn.loading{opacity:.7;cursor:wait}
</style>
</head>
<body>
<div class="wrap">

<div class="logo">
  <h1>🌊 Paal 13 — Installatie</h1>
  <p>Sollicitatie &amp; selectiesysteem voor Reddingsbrigade Paal 13</p>
</div>

<?php if ($al_klaar && $stap < 4): ?>
<!-- Al geïnstalleerd -->
<div class="card">
  <div class="card-head">✅ Systeem is al geïnstalleerd</div>
  <div class="card-body">
    <div class="res ok">
      Het systeem is geconfigureerd en klaar voor gebruik.<br>
      <span class="detail">config.php aanwezig op de server</span>
    </div>
    <div class="knoppen" style="padding-top:0;border:none;margin-top:0">
      <a href="<?= htmlspecialchars(defined('APP_URL') ? APP_URL : $app_url) ?>/admin/" class="btn btn-o">→ Naar het dashboard</a>
      <a href="update.php" class="btn btn-n">Database updaten</a>
      <a href="import.php" class="btn btn-outline">Data importeren</a>
    </div>
    <hr>
    <p style="font-size:12px;color:#718096">Wil je opnieuw installeren? Verwijder dan <code>includes/config.php</code> via FTP en herlaad deze pagina.</p>
  </div>
</div>

<?php else: ?>

<!-- Stappen indicator -->
<div class="stappen">
  <?php
  $stap_labels = ['1' => ['Database','💾'], '2' => ['E-mail','📧'], '3' => ['Authentik','🔐'], '4' => ['Klaar!','✅']];
  foreach ($stap_labels as $nr => [$label, $icon]):
    $cls = $nr == $stap ? 'actief' : ($nr < $stap ? 'klaar' : '');
  ?>
  <div class="stap-item <?= $cls ?>">
    <span class="nr"><?= $nr < $stap ? '✓' : $icon ?></span>
    <?= $label ?>
  </div>
  <?php endforeach; ?>
</div>

<?php // ── Resultaat tonen ──────────────────────────────── ?>
<?php if ($resultaat): ?>
<div class="res <?= $resultaat['ok'] ? 'ok' : 'er' ?>">
  <?= htmlspecialchars($resultaat['msg']) ?>
  <?php if (!empty($resultaat['detail'])): ?>
  <div class="detail"><?= htmlspecialchars($resultaat['detail']) ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php // ═══════════════════════════════════════════════════════ ?>
<?php if ($stap === 1): ?>
<div class="card">
  <div class="card-head">
    Stap 1 — Database & Admin account
  </div>
  <div class="card-body">
    <div class="info-box">
      <strong>Wat er in deze stap gebeurt:</strong>
      1. Database verbinding testen<br>
      2. Database aanmaken (als die nog niet bestaat)<br>
      3. Alle bestaande tabellen verwijderen en opnieuw aanmaken (schone installatie)<br>
      4. Admin account aanmaken (admin@paal13.com)
    </div>

    <div class="res info" style="margin-bottom:14px">
      ⚠️ <strong>Let op:</strong> Bij installatie worden alle bestaande tabellen in de database verwijderd en opnieuw aangemaakt.
      Bestaande data gaat verloren. Gebruik daarna <a href="import.php" style="color:#1a56a0">setup/import.php</a> om data te importeren.
    </div>

    <form method="POST" id="form1">
      <div class="rij">
        <div class="fg">
          <label>Database host <span class="vereist">*</span></label>
          <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
        </div>
        <div class="fg">
          <label>Databasenaam <span class="vereist">*</span></label>
          <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" placeholder="paal13_sollicitatie" required>
        </div>
      </div>
      <div class="rij">
        <div class="fg">
          <label>Gebruikersnaam <span class="vereist">*</span></label>
          <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
        </div>
        <div class="fg">
          <label>Wachtwoord <span class="vereist">*</span></label>
          <input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" required>
        </div>
      </div>
      <div class="fg">
        <label>URL van het systeem <span class="vereist">*</span></label>
        <input type="url" name="app_url" value="<?= htmlspecialchars($_POST['app_url'] ?? 'https://sollicitatie.paal13.com') ?>" required>
        <div class="sub">Het exacte adres waarop dit systeem bereikbaar is (zonder trailing slash)</div>
      </div>
      <hr>
      <div class="fg">
        <label>Admin wachtwoord <span class="vereist">*</span></label>
        <input type="password" name="admin_wachtwoord" placeholder="Kies een veilig wachtwoord (min. 6 tekens)" required>
        <div class="sub">Voor het account admin@paal13.com — bewaar dit goed!</div>
      </div>

      <div class="knoppen">
        <button type="submit" name="actie" value="test_db" class="btn btn-outline btn-sm">
          🔍 Test verbinding
        </button>
        <button type="submit" name="actie" value="sla_db" class="btn btn-o"
                onclick="return confirm('Alle bestaande tabellen worden verwijderd en opnieuw aangemaakt. Weet je het zeker?')">
          ▶ Database installeren & doorgaan →
        </button>
      </div>
    </form>
  </div>
</div>

<?php elseif ($stap === 2): ?>
<div class="card">
  <div class="card-head">Stap 2 — E-mail & SMTP</div>
  <div class="card-body">
    <div class="info-box">
      <strong>SMTP (sterk aanbevolen):</strong>
      Vul de gegevens van je e-mailprovider in zodat mails betrouwbaar aankomen.<br>
      Voorbeelden: Gmail (<code>smtp.gmail.com:587</code>), Brevo (<code>smtp-relay.brevo.com:587</code>), Mailgun.<br>
      Laat leeg om PHP's ingebouwde mail() te gebruiken (minder betrouwbaar).
    </div>

    <form method="POST">
      <div class="rij">
        <div class="fg">
          <label>Afzender e-mail <span class="vereist">*</span></label>
          <input type="email" name="mail_from" value="<?= htmlspecialchars($setup['mail_from'] ?? 'noreply@paal13.com') ?>" required>
        </div>
        <div class="fg">
          <label>Bestuur e-mail <span class="vereist">*</span></label>
          <input type="email" name="mail_bestuur" value="<?= htmlspecialchars($setup['mail_bestuur'] ?? '') ?>" placeholder="bestuur@paal13.com" required>
          <div class="sub">Testmail wordt hierheen gestuurd</div>
        </div>
      </div>
      <hr>
      <div class="fg" style="font-weight:600;font-size:13px;color:#002147;margin-bottom:10px">
        SMTP instellingen <span class="badge b-opt">Optioneel</span>
      </div>
      <div class="rij3">
        <div class="fg">
          <label>SMTP host</label>
          <input type="text" name="smtp_host" value="<?= htmlspecialchars($setup['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
        </div>
        <div class="fg">
          <label>Port</label>
          <input type="number" name="smtp_port" value="<?= htmlspecialchars((string)($setup['smtp_port'] ?? 587)) ?>">
        </div>
        <div class="fg">
          <label>Versleuteling</label>
          <select name="smtp_encrypt">
            <option value="tls" <?= ($setup['smtp_encrypt'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
            <option value="ssl" <?= ($setup['smtp_encrypt'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
            <option value="" <?= ($setup['smtp_encrypt'] ?? '') === '' ? 'selected' : '' ?>>Geen</option>
          </select>
        </div>
      </div>
      <div class="rij">
        <div class="fg">
          <label>SMTP gebruikersnaam</label>
          <input type="text" name="smtp_user" value="<?= htmlspecialchars($setup['smtp_user'] ?? '') ?>" placeholder="je@email.com">
        </div>
        <div class="fg">
          <label>SMTP wachtwoord / app key</label>
          <input type="password" name="smtp_pass" value="<?= htmlspecialchars($setup['smtp_pass'] ?? '') ?>">
        </div>
      </div>

      <div class="knoppen">
        <button type="submit" name="actie" value="test_mail" class="btn btn-outline btn-sm">
          📧 Test SMTP verbinding
        </button>
        <button type="submit" name="actie" value="sla_mail" class="btn btn-o">
          ▶ Opslaan & doorgaan →
        </button>
        <a href="?stap=1" class="btn btn-outline btn-sm">← Terug</a>
      </div>
    </form>
  </div>
</div>

<?php elseif ($stap === 3): ?>
<div class="card">
  <div class="card-head">
    Stap 3 — Authentik SSO <span class="badge b-opt">Optioneel</span>
  </div>
  <div class="card-body">
    <div class="info-box">
      <strong>Je kunt deze stap overslaan — inloggen met wachtwoord werkt altijd.</strong>
      SSO toevoegen doe je zo:<br>
      1. Ga naar <strong>auth.paal13.com</strong> → Applications → Providers → Nieuw → OAuth2/OpenID Provider<br>
      2. Redirect URI — kopieer dit exact:
      <div class="uri-box" onclick="navigator.clipboard.writeText(this.textContent).then(()=>this.style.background='#bbf7d0')" title="Klik om te kopiëren"><?= htmlspecialchars($redirect) ?></div>
      3. Maak een Application aan met een slug en noteer Client ID + Secret
    </div>

    <form method="POST">
      <div class="fg">
        <label>Authentik URL</label>
        <input type="url" name="oidc_issuer" value="https://auth.paal13.com" placeholder="https://auth.paal13.com">
      </div>
      <div class="fg">
        <label>Application slug</label>
        <input type="text" name="oidc_slug" value="" placeholder="paal13-sollicitatie">
      </div>
      <div class="rij">
        <div class="fg">
          <label>Client ID</label>
          <input type="text" name="oidc_client_id" placeholder="Uit de provider instellingen">
        </div>
        <div class="fg">
          <label>Client Secret</label>
          <input type="password" name="oidc_client_secret" placeholder="••••••••">
        </div>
      </div>

      <div class="knoppen">
        <button type="submit" name="actie" value="test_oidc" class="btn btn-outline btn-sm">
          🔍 Test Authentik endpoint
        </button>
        <button type="submit" name="actie" value="sla_oidc" class="btn btn-o">
          ▶ SSO instellen & afronden →
        </button>
        <button type="submit" name="actie" value="overslaan_oidc" class="btn btn-g">
          Overslaan — gebruik alleen wachtwoorden →
        </button>
      </div>
    </form>
  </div>
</div>

<?php elseif ($stap === 4): ?>
<div class="card">
  <div class="card-head" style="background:#1D9E75">✅ Installatie voltooid!</div>
  <div class="card-body">
    <div class="res ok" style="font-size:14px">
      <strong>Het systeem is klaar voor gebruik.</strong><br>
      <span class="detail">config.php is aangemaakt — alle instellingen zijn opgeslagen</span>
    </div>

    <div class="info-box">
      <strong>Eerste login:</strong>
      E-mail: <code>admin@paal13.com</code><br>
      Wachtwoord: het wachtwoord dat je in stap 1 hebt ingevuld
    </div>

    <div class="info-box" style="background:#fffbeb;border-color:#fde68a;color:#92400e">
      <strong>Volgende stappen:</strong>
      1. Log in en controleer de instellingen<br>
      2. Importeer bestaande data via <a href="import.php" style="color:#92400e">setup/import.php</a><br>
      3. Maak een seizoen aan en stel datums in<br>
      4. Open de aanmeldingen voor sollicitanten<br>
      5. Upload het Paal 13 logo als <code>assets/img/logo.png</code>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="../admin/login.php" class="btn btn-o">→ Inloggen in het dashboard</a>
      <a href="import.php" class="btn btn-n">📥 Data importeren</a>
      <a href="update.php" class="btn btn-outline">🔄 Updates controleren</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; // einde al_klaar check ?>

</div>
</body>
</html>

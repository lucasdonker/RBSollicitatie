SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- Seizoenen
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

-- Personen (uniek op email, over seizoenen heen)
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

-- Sollicitaties (per persoon per seizoen)
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

-- BLK formulier (beter leren kennen)
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

-- Selectiedag beoordelingen
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

-- Beschikbaarheid
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

-- Beheerders (eigen accounts, los van Authentik)
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

-- OTP tokens (voor sollicitant login en beheerder 2FA)
CREATE TABLE IF NOT EXISTS `otp_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(200) NOT NULL,
  `code_hash` VARCHAR(64) NOT NULL,
  `type` ENUM('portaal','beheerder') NOT NULL DEFAULT 'portaal',
  `pogingen` TINYINT NOT NULL DEFAULT 0,
  `gebruikt` TINYINT(1) NOT NULL DEFAULT 0,
  `vervalt_op` DATETIME NOT NULL,
  `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email_type` (`email`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mail log
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

-- Database migraties tracking
CREATE TABLE IF NOT EXISTS `migraties` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `versie` VARCHAR(20) NOT NULL UNIQUE,
  `beschrijving` VARCHAR(200) NOT NULL,
  `uitgevoerd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;

-- Standaard data
INSERT IGNORE INTO `seizoenen` (`naam`, `status`) VALUES ('Zomer 2026', 'gesloten');
INSERT IGNORE INTO `migraties` (`versie`, `beschrijving`) VALUES ('0001', 'Basisschema installatie');

-- Standaard admin: wachtwoord = "Paal13Admin!" (wijzig direct na installatie)
INSERT IGNORE INTO `beheerders` (`naam`, `email`, `wachtwoord`, `rol`)
VALUES ('Beheerder', 'admin@paal13.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

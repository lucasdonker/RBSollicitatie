-- Migratie 0002: Beheerders tabel en OTP tokens toevoegen aan bestaande installaties
-- Veilig om meerdere keren te draaien (IF NOT EXISTS)

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
  `type` ENUM('portaal','beheerder') NOT NULL DEFAULT 'portaal',
  `pogingen` TINYINT NOT NULL DEFAULT 0,
  `gebruikt` TINYINT(1) NOT NULL DEFAULT 0,
  `vervalt_op` DATETIME NOT NULL,
  `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email_type` (`email`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Voeg foutmelding kolom toe aan mail_log als die ontbreekt
ALTER TABLE `mail_log` ADD COLUMN IF NOT EXISTS `foutmelding` TEXT DEFAULT NULL;

-- Standaard admin account (wachtwoord: password — direct wijzigen!)
INSERT IGNORE INTO `beheerders` (`naam`, `email`, `wachtwoord`, `rol`)
VALUES ('Beheerder', 'admin@paal13.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

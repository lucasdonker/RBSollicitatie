-- Migratie 0016: Digitale ondertekeningen (gezondheidsverklaring, intake, aansprakelijkheid)

CREATE TABLE IF NOT EXISTS `ondertekeningen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sollicitatie_id` INT NOT NULL,
  `type` VARCHAR(30) NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `ondertekenaar_rol` VARCHAR(20) NOT NULL DEFAULT 'sollicitant',
  `ondertekenaar_naam` VARCHAR(150) DEFAULT NULL,
  `ondertekenaar_email` VARCHAR(200) DEFAULT NULL,
  `ondertekenaar_relatie` VARCHAR(30) DEFAULT NULL,
  `ondertekenaar_geboortedatum` DATE DEFAULT NULL,
  `ondertekenaar_geboorteplaats` VARCHAR(150) DEFAULT NULL,
  `formulier_data` MEDIUMTEXT DEFAULT NULL,
  `plaats` VARCHAR(150) DEFAULT NULL,
  `ondertekend_op` DATETIME DEFAULT NULL,
  `ondertekend_ip` VARCHAR(45) DEFAULT NULL,
  `ondertekend_user_agent` VARCHAR(500) DEFAULT NULL,
  `geldig_tot` DATE DEFAULT NULL,
  `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`sollicitatie_id`),
  INDEX (`type`),
  INDEX (`token`),
  INDEX (`ondertekend_op`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

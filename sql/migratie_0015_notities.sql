-- Migratie 0015: Sollicitant notities voor interne bestuurs-communicatie
CREATE TABLE IF NOT EXISTS `sollicitant_notities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sollicitatie_id` INT NOT NULL,
  `beheerder_id` INT NOT NULL,
  `tekst` TEXT NOT NULL,
  `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gewijzigd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`sollicitatie_id`),
  INDEX (`aangemaakt_op`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

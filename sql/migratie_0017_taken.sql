-- Migratie 0017: Portaal-taken voor aangenomen sollicitanten (kennismakingspakket)

CREATE TABLE IF NOT EXISTS `sollicitant_taken` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sollicitatie_id` INT NOT NULL,
  `taak_slug` VARCHAR(50) NOT NULL,
  `titel` VARCHAR(150) NOT NULL,
  `omschrijving` TEXT DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `verplicht` TINYINT(1) NOT NULL DEFAULT 1,
  `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `voltooid_op` DATETIME DEFAULT NULL,
  INDEX (`sollicitatie_id`),
  INDEX (`status`),
  UNIQUE KEY `uniek_taak_per_sol` (`sollicitatie_id`, `taak_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

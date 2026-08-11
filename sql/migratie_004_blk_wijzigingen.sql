-- Migratie 0004: Wijzigingslog voor BLK formulieren
CREATE TABLE IF NOT EXISTS `blk_wijzigingen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sollicitatie_id` INT NOT NULL,
  `reden` TEXT NOT NULL,
  `wijzigingen_json` JSON DEFAULT NULL COMMENT 'Array van {veld, label, oud, nieuw}',
  `gewijzigd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sollicitatie_id`) REFERENCES `sollicitaties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
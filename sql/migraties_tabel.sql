-- Migration tracking tabel
-- Wordt automatisch aangemaakt door het update systeem
CREATE TABLE IF NOT EXISTS `migraties` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `versie` VARCHAR(20) NOT NULL UNIQUE,
  `beschrijving` VARCHAR(200) NOT NULL,
  `uitgevoerd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migratie 0011: Notities per sollicitant
CREATE TABLE IF NOT EXISTS `sollicitant_notities` (
  `id`             INT          AUTO_INCREMENT PRIMARY KEY,
  `sol_id`         INT          NOT NULL,
  `beheerder_id`   INT          DEFAULT NULL,
  `tekst`          TEXT         NOT NULL,
  `aangemaakt_op`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_sol` (`sol_id`),
  CONSTRAINT `fk_notitie_sol` FOREIGN KEY (`sol_id`) REFERENCES `sollicitaties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO instellingen (sleutel, waarde, beschrijving, type) VALUES
  ('reminder.blk_aan',    '1', 'BLK-herinnering automatisch versturen', 'boolean'),
  ('reminder.blk_dagen',  '7', 'Aantal dagen wachten voor BLK reminder', 'getal'),
  ('reminder.sd_aan',     '1', 'Selectiedag-herinnering automatisch versturen', 'boolean'),
  ('reminder.sd_dagen',   '3', 'Aantal dagen wachten voor SD reminder', 'getal');

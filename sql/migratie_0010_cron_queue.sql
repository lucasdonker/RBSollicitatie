-- Migratie 0010: Centrale cron + mail queue

-- Mail queue voor grote batches
CREATE TABLE IF NOT EXISTS `mail_queue` (
  `id`             INT          AUTO_INCREMENT PRIMARY KEY,
  `naar_email`     VARCHAR(255) NOT NULL,
  `naar_naam`      VARCHAR(255) DEFAULT NULL,
  `onderwerp`      VARCHAR(255) NOT NULL,
  `body`           LONGTEXT     NOT NULL,
  `sol_id`         INT          DEFAULT NULL,
  `status`         ENUM('wacht','bezig','klaar','fout') NOT NULL DEFAULT 'wacht',
  `pogingen`       TINYINT      NOT NULL DEFAULT 0,
  `fout`           TEXT         DEFAULT NULL,
  `aangemaakt_op`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verwerkt_op`    DATETIME     DEFAULT NULL,
  INDEX `idx_wacht` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cron settings
INSERT IGNORE INTO instellingen (sleutel, waarde, beschrijving, type) VALUES
  ('cron.token',         '', 'Token voor cron endpoint (automatisch gegenereerd)', 'geheim'),
  ('invite.delay_dagen', '1', 'Aantal dagen wachten na aanmelding voor auto-invite check', 'getal');

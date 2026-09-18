-- Migratie 0018: centrale meldingen (2.87.0)
-- Documentatie van wat de failsafe (includes/meldingen/*.php) automatisch aanmaakt.
-- Handmatig draaien is niet nodig; de app verzekert deze kolommen zelf bij de eerste request.

-- mail_log: één log voor alle kanalen (e-mail, WhatsApp, SMS)
ALTER TABLE `mail_log`
  ADD COLUMN IF NOT EXISTS `kanaal`        VARCHAR(10)  NOT NULL DEFAULT 'email',
  ADD COLUMN IF NOT EXISTS `meldingstype`  VARCHAR(50)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `categorie`     VARCHAR(20)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ontvanger_rol` VARCHAR(15)  NOT NULL DEFAULT 'sollicitant',
  ADD COLUMN IF NOT EXISTS `persoon_id`    INT          DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `samenvatting`  VARCHAR(300) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `body`          MEDIUMTEXT   DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `fallback_van`  VARCHAR(10)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `referentie`    VARCHAR(60)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `queue_id`      INT          DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `pogingen`      TINYINT      NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `bijgewerkt_op` DATETIME     DEFAULT NULL,
  MODIFY COLUMN `status` ENUM('wacht','bezig','verstuurd','mislukt','overgeslagen') NOT NULL DEFAULT 'verstuurd';
ALTER TABLE `mail_log` ADD INDEX IF NOT EXISTS `idx_ml_sol_type` (`sollicitatie_id`, `meldingstype`, `verstuurd_op`);
ALTER TABLE `mail_log` ADD INDEX IF NOT EXISTS `idx_ml_kanaal`   (`kanaal`, `verstuurd_op`);
ALTER TABLE `mail_log` ADD INDEX IF NOT EXISTS `idx_ml_ontv`     (`ontvanger`, `verstuurd_op`);

-- mail_queue: wachtrij voor alle kanalen
ALTER TABLE `mail_queue`
  ADD COLUMN IF NOT EXISTS `log_id`        INT         DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `kanaal_plan`   VARCHAR(40) NOT NULL DEFAULT 'email',
  ADD COLUMN IF NOT EXISTS `telefoon`      VARCHAR(30) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `tekst_kort`    TEXT        DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `meldingstype`  VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `prioriteit`    VARCHAR(12) NOT NULL DEFAULT 'queue',
  ADD COLUMN IF NOT EXISTS `bijlagen_json` TEXT        DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ontvanger_rol` VARCHAR(15) NOT NULL DEFAULT 'sollicitant';

-- mail_templates: korte tekst (WhatsApp/SMS), categorie, samenvatting (portaal), vaste bijlagen
ALTER TABLE `mail_templates`
  ADD COLUMN IF NOT EXISTS `tekst_kort`    TEXT         DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `categorie`     VARCHAR(20)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `samenvatting`  VARCHAR(200) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `bijlagen_json` TEXT         DEFAULT NULL;

-- personen: kanaalvoorkeur (standaard WhatsApp; afmelden via portaal of beheer)
ALTER TABLE `personen`
  ADD COLUMN IF NOT EXISTS `kanaal_voorkeur`     ENUM('email','whatsapp','sms') NOT NULL DEFAULT 'whatsapp',
  ADD COLUMN IF NOT EXISTS `kanaal_gewijzigd_op` DATETIME    DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `kanaal_bron`         VARCHAR(20) DEFAULT NULL;

-- Slug-migratie mail_templates (gebeurt in code, alleen als de nieuwe slug nog niet bestaat):
--   welkomstmail → aanmelding_bevestiging, welkom_aangenomen → aangenomen, otp_portaal → portaal_code,
--   reminder_sd_aankomst → selectiedag_nadert, reminder_sd_morgen → selectiedag_morgen,
--   reminder_ondertekening → ondertekening_herinnering, reminder_beschikbaarheid → beschikbaarheid_voorlopig,
--   ondertekening_ouder → ondertekening_verzoek_ouder, reminder_blk → herinnering_taken_open

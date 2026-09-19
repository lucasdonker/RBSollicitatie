-- Migratie 0018 — Aangenomen-takenpakket (2.90.x): documenten in de kluis, bewijslog ondertekeningen, taken-soorten.
-- REFERENTIE: het systeem maakt dit zelf aan (db_failsafe → documenten_auto_setup, ondertekening_bewijs_auto_setup,
-- extra_tabellen_auto_setup + db_kolom_verzeker). Handmatig draaien is niet nodig.

CREATE TABLE IF NOT EXISTS `sollicitant_documenten` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `sollicitatie_id` INT NOT NULL,
  `soort`           VARCHAR(30) NOT NULL,          -- id_voor | id_achter | bankpas | pasfoto | ondertekening_<type>
  `bestandsnaam`    VARCHAR(200) NOT NULL,
  `opslagnaam`      VARCHAR(80) NOT NULL,          -- willekeurige naam .enc in DOCUMENTEN_MAP (kluis)
  `mime`            VARCHAR(100) NOT NULL,
  `grootte`         INT NOT NULL DEFAULT 0,
  `sha256`          CHAR(64) DEFAULT NULL,         -- hash van de ontsleutelde inhoud
  `kluis_versie`    TINYINT NOT NULL DEFAULT 1,
  `extra`           VARCHAR(100) DEFAULT NULL,     -- id_voor: paspoort | id_kaart
  `status`          VARCHAR(20) NOT NULL DEFAULT 'ingediend', -- ingediend | verwerkt | vervangen | vernietigd
  `geupload_op`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geupload_ip`     VARCHAR(45) DEFAULT NULL,
  `verwerkt_op`     DATETIME DEFAULT NULL,
  `verwerkt_door`   INT DEFAULT NULL,
  `vernietigd_op`   DATETIME DEFAULT NULL,
  `laatst_gedownload_op`   DATETIME DEFAULT NULL,
  `laatst_gedownload_door` INT DEFAULT NULL,
  INDEX (`sollicitatie_id`), INDEX (`status`), INDEX (`soort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ondertekening_gebeurtenissen` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `ondertekening_id` INT NOT NULL,
  `gebeurtenis`      VARCHAR(30) NOT NULL,         -- aangemaakt, geopend, code_verzonden, code_fout, code_ok, ondertekend, pdf_verzegeld, pdf_gedownload, verwerkt, ...
  `tijdstip`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip`               VARCHAR(45) DEFAULT NULL,
  `browser`          VARCHAR(255) DEFAULT NULL,
  `details`          VARCHAR(500) DEFAULT NULL,    -- JSON zonder gevoelige waarden
  INDEX (`ondertekening_id`), INDEX (`gebeurtenis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `ondertekeningen`
  ADD COLUMN `vertrouwelijk`   TEXT DEFAULT NULL,        -- kluis-blob (base64): BSN / IBAN+tenaamstelling
  ADD COLUMN `pdf_document_id` INT DEFAULT NULL,         -- verzegelde PDF in sollicitant_documenten
  ADD COLUMN `pdf_sha256`      CHAR(64) DEFAULT NULL,
  ADD COLUMN `zekerheid`       VARCHAR(20) DEFAULT NULL, -- link | sms | whatsapp | email
  ADD COLUMN `bewijs_hash`     CHAR(64) DEFAULT NULL,
  ADD COLUMN `verwerkt_op`     DATETIME DEFAULT NULL,
  ADD COLUMN `verwerkt_door`   INT DEFAULT NULL;

ALTER TABLE `sollicitant_taken`
  ADD COLUMN `soort`          VARCHAR(20) NOT NULL DEFAULT 'afvinken', -- upload | link | afvinken
  ADD COLUMN `document_soort` VARCHAR(40) DEFAULT NULL,
  ADD COLUMN `volgorde`       INT NOT NULL DEFAULT 50;

-- Nieuwe instellingen (INSERT IGNORE via instellingen_auto_setup): mail.penningmeester_email, mail.ledenservice_email,
-- documenten.penningmeester_bijlagen, documenten.download_melding, taken.huisregels_bijlage_id, ondertekenen.2fa_typen,
-- bewaar.loondocumenten_jaar, bewaar.bankpas_na_verwerking_dagen, kluis.zout (automatisch).
-- Nieuwe rol: penningmeester (rechten_auto_setup) met module documenten.

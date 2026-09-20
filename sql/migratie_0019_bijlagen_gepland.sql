-- Migratie 0019: bijlagen + geplande berichten (2.87.5)
-- Documentatie; de app maakt deze tabellen zelf aan (bijlagen_auto_setup, gepland_auto_setup).

CREATE TABLE IF NOT EXISTS `bijlagen` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `bestandsnaam`  VARCHAR(200) NOT NULL,
  `opslagnaam`    VARCHAR(80)  NOT NULL,
  `mime`          VARCHAR(100) NOT NULL,
  `grootte`       INT NOT NULL DEFAULT 0,
  `omschrijving`  VARCHAR(200) DEFAULT NULL,
  `geupload_door` INT DEFAULT NULL,
  `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geplande_berichten` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `naam`           VARCHAR(120) NOT NULL,
  `aan`            TINYINT(1) NOT NULL DEFAULT 1,
  `gebeurtenis`    VARCHAR(40) NOT NULL,
  `offset_dagen`   INT NOT NULL DEFAULT 0,
  `sjabloon_slug`  VARCHAR(50) NOT NULL,
  `kanaalmodus`    VARCHAR(10) NOT NULL DEFAULT 'email',
  `fases`          VARCHAR(255) DEFAULT NULL,
  `max_per_sollicitatie` TINYINT NOT NULL DEFAULT 1,
  `venster_dagen`  TINYINT NOT NULL DEFAULT 7,
  `aangemaakt_door` INT DEFAULT NULL,
  `aangemaakt_op`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gewijzigd_op`   DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bestanden staan in uploads/bijlagen/ (met .htaccess deny); download via download.php met getekende link.
-- Instelling bijlage.geheim (HMAC-sleutel) wordt eenmalig gegenereerd; bewaar.bijlagen_mnd = opschoning.

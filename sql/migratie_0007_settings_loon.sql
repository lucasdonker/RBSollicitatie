-- Migratie 0007: Instellingen + Loonplanning systeem

-- ── Instellingen (key-value) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `instellingen` (
  `sleutel`     VARCHAR(100)  NOT NULL PRIMARY KEY,
  `waarde`      TEXT          DEFAULT NULL,
  `beschrijving`VARCHAR(300)  DEFAULT NULL,
  `type`        VARCHAR(20)   DEFAULT 'tekst',
  `bijgewerkt_op` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Standaardwaarden
INSERT IGNORE INTO `instellingen` (`sleutel`, `waarde`, `beschrijving`, `type`) VALUES
('site.naam',             'Reddingsbrigade Paal 13',      'Naam van de organisatie',            'tekst'),
('site.logo_url',         '',                              'URL van het logo (leeg = standaard)', 'url'),
('site.favicon_url',      '',                              'URL van het favicon',                 'url'),
('site.kleur_primary',    '#FF6B35',                       'Primaire accentkleur (oranje)',        'kleur'),
('site.kleur_navy',       '#002147',                       'Marineblauwe kleur',                  'kleur'),
('mail.fout_email',       'website@paal13.com',            'E-mail voor foutmeldingen',           'email'),
('mail.bestuur_email',    'bestuur@paal13.com',            'E-mail bestuur (notificaties)',        'email'),
('aanmeld.min_leeftijd',  '15',                            'Minimumleeftijd om te solliciteren',  'getal'),
('aanmeld.max_leeftijd',  '35',                            'Maximumleeftijd om te solliciteren',  'getal'),
('invite.modus',          'suggestie',                     'Auto-invite: auto | suggestie | uit', 'keuze'),
('invite.min_leeftijd',   '16',                            'Min leeftijd voor auto-uitnodiging',  'getal'),
('invite.max_leeftijd',   '30',                            'Max leeftijd voor auto-uitnodiging',  'getal'),
('invite.delay_uur',      '24',                            'Uren wachten voor auto-uitnodiging',  'getal'),
('loon.vt_percentage',    '8',                             'Vakantietoeslag % (standaard 8)',      'getal'),
('loon.uren_per_dag',     '8',                             'Werkuren per dag',                    'getal'),
('ai.provider',           'gemini',                        'AI provider: gemini | uit',           'keuze'),
('ai.api_key',            '',                              'Gemini API key (gratis tier)',         'geheim'),
('portaal.welkomst_tekst','Welkom op het sollicitantenportaal van Paal 13. Log in met je e-mailadres om je aanmelding te bekijken.', 'Tekst op portaal login', 'textarea'),
('app.versie',            '1.0.0',                         'Huidige applicatieversie',            'tekst');

-- ── Functies & factoren ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `functies` (
  `id`         INT          AUTO_INCREMENT PRIMARY KEY,
  `sleutel`    VARCHAR(50)  NOT NULL UNIQUE,
  `naam`       VARCHAR(100) NOT NULL,
  `factor`     DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  `min_leeftijd` INT        DEFAULT NULL,
  `max_leeftijd` INT        DEFAULT NULL,
  `vast_loon`  DECIMAL(8,2) DEFAULT NULL COMMENT 'Vast weekbedrag ipv berekend loon (bijv. postcommandant)',
  `volgorde`   INT          DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `functies` (`sleutel`, `naam`, `factor`, `min_leeftijd`, `max_leeftijd`, `volgorde`) VALUES
('aspirant',     'Aspirant Lifeguard',  1.00, 15, 17, 1),
('junior',       'Junior Lifeguard',    1.10, 16, 20, 2),
('lifeguard',    'Lifeguard',           1.20, 16, 99, 3),
('senior',       'Senior Lifeguard',    1.30, 18, 99, 4),
('postcommandant','Postcommandant',     0.00, 18, 99, 5);

-- Postcommandant vast loon apart (per seizoensperiode)
INSERT IGNORE INTO `instellingen` (`sleutel`, `waarde`, `beschrijving`, `type`) VALUES
('loon.pc_vast_juni',    '850', 'Vast weekbedrag postcommandant (juni)',       'getal'),
('loon.pc_vast_jul_sep', '750', 'Vast weekbedrag postcommandant (jul-sep)',    'getal');

-- ── Minimumlonen per leeftijd per periode ─────────────────────
CREATE TABLE IF NOT EXISTS `loon_tarieven` (
  `id`         INT          AUTO_INCREMENT PRIMARY KEY,
  `leeftijd`   INT          NOT NULL,
  `periode`    VARCHAR(10)  NOT NULL COMMENT 'jun | jul_sep',
  `min_uurloon`DECIMAL(6,4) NOT NULL,
  UNIQUE KEY `leeftijd_periode` (`leeftijd`, `periode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Minlonen uit het Excel (periode jun 2026)
INSERT IGNORE INTO `loon_tarieven` (`leeftijd`, `periode`, `min_uurloon`) VALUES
(15,'jun',4.41),(16,'jun',5.07),(17,'jun',5.81),(18,'jun',7.36),(19,'jun',8.83),
(20,'jun',11.77),(21,'jun',14.71),(22,'jun',14.71),(23,'jun',14.71),(24,'jun',11.16),
(25,'jun',11.16),(26,'jun',11.16),(27,'jun',11.16),(28,'jun',11.16),(29,'jun',11.16),
(30,'jun',11.16),(31,'jun',11.16),(32,'jun',11.16),
(15,'jul_sep',4.32),(16,'jul_sep',4.97),(17,'jul_sep',5.69),(18,'jul_sep',7.20),
(19,'jul_sep',8.64),(20,'jul_sep',11.52),(21,'jul_sep',14.40),(22,'jul_sep',14.40),
(23,'jul_sep',14.40),(24,'jul_sep',11.16),(25,'jul_sep',11.16),(26,'jul_sep',11.16),
(27,'jul_sep',11.16),(28,'jul_sep',11.16),(29,'jul_sep',11.16),(30,'jul_sep',11.16),
(31,'jul_sep',11.16),(32,'jul_sep',11.16);

-- ── Loon planning (per persoon per week) ──────────────────────
CREATE TABLE IF NOT EXISTS `loon_planning` (
  `id`              INT         AUTO_INCREMENT PRIMARY KEY,
  `sollicitatie_id` INT         NOT NULL,
  `week_nr`         INT         NOT NULL,
  `jaar`            INT         NOT NULL DEFAULT 2026,
  `dagen`           INT         NOT NULL DEFAULT 5 COMMENT 'Werkdagen die week (1-7)',
  `functie_sleutel` VARCHAR(50) DEFAULT NULL COMMENT 'Override functie voor deze week',
  `opgenomen`       TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'Admin heeft dit ingepland',
  `loon_override`   DECIMAL(8,2) DEFAULT NULL COMMENT 'Handmatig dagloon overschrijven',
  `notitie`         VARCHAR(255) DEFAULT NULL,
  `bijgewerkt_op`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `sol_week` (`sollicitatie_id`, `week_nr`, `jaar`),
  FOREIGN KEY (`sollicitatie_id`) REFERENCES `sollicitaties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Error log ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `error_log` (
  `id`          INT           AUTO_INCREMENT PRIMARY KEY,
  `code`        VARCHAR(20)   NOT NULL,
  `bericht`     TEXT          NOT NULL,
  `bestand`     VARCHAR(300)  DEFAULT NULL,
  `regel`       INT           DEFAULT NULL,
  `gebruiker`   VARCHAR(100)  DEFAULT NULL,
  `url`         VARCHAR(500)  DEFAULT NULL,
  `mail_verstuurd` TINYINT(1) DEFAULT 0,
  `aangemaakt_op`  DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

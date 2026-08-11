-- Migratie 0003: Seizoen werkperiode datums voor automatische week-generator
ALTER TABLE `seizoenen`
  ADD COLUMN IF NOT EXISTS `datum_werk_start` DATE DEFAULT NULL
    COMMENT 'Eerste werkdag van het seizoen (voor week-selector)' AFTER `locatie_tw`,
  ADD COLUMN IF NOT EXISTS `datum_werk_eind` DATE DEFAULT NULL
    COMMENT 'Laatste werkdag van het seizoen (voor week-selector)' AFTER `datum_werk_start`;
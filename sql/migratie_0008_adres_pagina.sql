-- Migratie 0007: BLK adres uitsplitsen in postcode/huisnummer/straat/woonplaats
ALTER TABLE blk_formulieren ADD COLUMN IF NOT EXISTS postcode    VARCHAR(10)  DEFAULT NULL AFTER adres;
ALTER TABLE blk_formulieren ADD COLUMN IF NOT EXISTS huisnummer  VARCHAR(15)  DEFAULT NULL AFTER postcode;
ALTER TABLE blk_formulieren ADD COLUMN IF NOT EXISTS straat      VARCHAR(150) DEFAULT NULL AFTER huisnummer;
ALTER TABLE blk_formulieren ADD COLUMN IF NOT EXISTS woonplaats  VARCHAR(100) DEFAULT NULL AFTER straat;

-- Selectiedag- en TW-locatie velden in seizoenen (volledig adres + vrije omschrijving)
ALTER TABLE seizoenen ADD COLUMN IF NOT EXISTS tw_straat      VARCHAR(150) DEFAULT NULL;
ALTER TABLE seizoenen ADD COLUMN IF NOT EXISTS tw_huisnummer  VARCHAR(15)  DEFAULT NULL;
ALTER TABLE seizoenen ADD COLUMN IF NOT EXISTS tw_postcode    VARCHAR(10)  DEFAULT NULL;
ALTER TABLE seizoenen ADD COLUMN IF NOT EXISTS tw_woonplaats  VARCHAR(100) DEFAULT NULL;
ALTER TABLE seizoenen ADD COLUMN IF NOT EXISTS tw_extra       TEXT         DEFAULT NULL;

ALTER TABLE seizoenen ADD COLUMN IF NOT EXISTS sd_straat      VARCHAR(150) DEFAULT NULL;
ALTER TABLE seizoenen ADD COLUMN IF NOT EXISTS sd_huisnummer  VARCHAR(15)  DEFAULT NULL;
ALTER TABLE seizoenen ADD COLUMN IF NOT EXISTS sd_postcode    VARCHAR(10)  DEFAULT NULL;
ALTER TABLE seizoenen ADD COLUMN IF NOT EXISTS sd_woonplaats  VARCHAR(100) DEFAULT NULL;
ALTER TABLE seizoenen ADD COLUMN IF NOT EXISTS sd_extra       TEXT         DEFAULT NULL;

-- Paginatie voorkeur per beheerder
ALTER TABLE beheerders ADD COLUMN IF NOT EXISTS per_pagina TINYINT DEFAULT 25;

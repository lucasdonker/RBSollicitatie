-- Migratie 0019 — twee te beperkte kolommen die stille fouten gaven (2.90.8).
-- REFERENTIE: het systeem doet dit zelf (db_failsafe → otp_tokens.type, documenten_auto_setup → soort).
-- Handmatig draaien is niet nodig; wel handig om te zien wat er verandert.

-- 1. otp_tokens.type was ENUM('portaal','beheerder'), maar is een vrije sleutel: naast 'portaal' en
--    'beheerder' ook 'ond<id>' voor de verificatiecode bij het ondertekenen. Op een ENUM-kolom werd die
--    waarde stil als lege string opgeslagen, waardoor de verstuurde code altijd "ongeldig" was.
ALTER TABLE `otp_tokens` MODIFY COLUMN `type` VARCHAR(30) NOT NULL DEFAULT 'portaal';
DELETE FROM `otp_tokens` WHERE `type` = '';   -- onbruikbare codes van vóór deze fix

-- 2. sollicitant_documenten.soort was VARCHAR(30); de langste waarde is
--    'ondertekening_gezondheid_18plus' (31 tekens) en werd dus afgekapt.
ALTER TABLE `sollicitant_documenten` MODIFY COLUMN `soort` VARCHAR(40) NOT NULL;

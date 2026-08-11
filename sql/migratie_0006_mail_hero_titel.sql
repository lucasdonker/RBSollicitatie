-- Migratie 0006: Aparte hero titel voor mail templates
ALTER TABLE `mail_templates`
  ADD COLUMN IF NOT EXISTS `hero_titel` VARCHAR(300) DEFAULT NULL
    COMMENT 'Grote tekst in de hero afbeelding (los van het e-mail onderwerp)'
    AFTER `onderwerp`;

-- Vul bestaande templates: hero_titel = onderwerp als startwaarde
UPDATE `mail_templates` SET `hero_titel` = `onderwerp` WHERE `hero_titel` IS NULL AND `slug` != '_hero_image';

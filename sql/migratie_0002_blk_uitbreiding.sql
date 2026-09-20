-- Migratie 0002: BLK formulier uitbreiden met extra velden uit FluentForms
-- Voeg toe aan blk_formulieren: diploma's, werkervaring, strand_motivatie, social_media, bijzonderheden
-- Uitvoeren via: Beheer › Instellingen › Systeem & Updates › Migraties uitvoeren

ALTER TABLE `blk_formulieren`
  ADD COLUMN IF NOT EXISTS `diplomas` TEXT DEFAULT NULL AFTER `zwemvaardigheid`,
  ADD COLUMN IF NOT EXISTS `werkervaring` TEXT DEFAULT NULL AFTER `diplomas`,
  ADD COLUMN IF NOT EXISTS `strand_motivatie` TEXT DEFAULT NULL AFTER `werkervaring`,
  ADD COLUMN IF NOT EXISTS `social_media` VARCHAR(200) DEFAULT NULL AFTER `strand_motivatie`,
  ADD COLUMN IF NOT EXISTS `bijzonderheden` TEXT DEFAULT NULL AFTER `social_media`,
  ADD COLUMN IF NOT EXISTS `foto_url` VARCHAR(500) DEFAULT NULL AFTER `bijzonderheden`;

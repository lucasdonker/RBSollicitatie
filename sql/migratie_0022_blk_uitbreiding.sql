-- Migratie 0022: BLK formulier uitbreiden met extra velden uit FluentForms
-- Voeg toe aan blk_formulieren: diploma's, werkervaring, strand_motivatie, social_media, bijzonderheden, foto_url
-- Uitvoeren via: Beheer › Systeem & Updates › Database-migraties (admin/update.php)
--
-- Heette eerder migratie_0002_blk_uitbreiding.sql en deelde zo nummer 0002 met
-- migratie_0002_beheerders_en_otp.sql. admin/update.php slaat een nummer over dat al in
-- `migraties` staat, dus na de eerste 0002 draaide deze nooit. Hernummerd; veilig om
-- opnieuw te draaien (IF NOT EXISTS), ook waar de oude 0002 wel is uitgevoerd.
-- Geen AFTER-clausules: `zwemvaardigheid` bestaat niet in sql/schema.sql, en de
-- kolomvolgorde doet er niet toe.

ALTER TABLE `blk_formulieren`
  ADD COLUMN IF NOT EXISTS `diplomas` TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `werkervaring` TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `strand_motivatie` TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `social_media` VARCHAR(200) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `bijzonderheden` TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `foto_url` VARCHAR(500) DEFAULT NULL;

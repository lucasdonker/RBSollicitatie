-- Migratie 0013: Per-template hero velden (placeholder)
-- De daadwerkelijke ALTER gebeurt via mail_templates_auto_setup() in PHP,
-- die INFORMATION_SCHEMA gebruikt voor MySQL/MariaDB compatibiliteit.
-- Deze migratie wordt als 'gedaan' gemarkeerd zodra de PHP setup is gedraaid.
SELECT 1;

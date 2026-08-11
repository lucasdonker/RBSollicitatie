-- Migratie 0009: Authentik portaal SSO + rolverdeling
-- Beheerders krijgen extra rollen voor AVG-rolverdeling

-- Seed Authentik instellingen (gebruik INSERT IGNORE voor compatibiliteit)
INSERT IGNORE INTO instellingen (sleutel, waarde, beschrijving, type) VALUES
  ('authentik.api_token',    '', 'Long-lived API token voor user lookup', 'geheim'),
  ('authentik.admin_groepen','Bestuur,Sol-Admin,Sol-Begeleider', 'Komma-gescheiden Authentik group namen die admin-toegang krijgen', 'tekst');

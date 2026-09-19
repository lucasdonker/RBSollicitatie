# Paal 13 Sollicitatiesysteem

Sollicitatiesysteem van Reddingsbrigade Paal 13 (Callantsoog en Groote Keeten): van aanmelding tot
aangenomen strandwacht. Portaal voor sollicitanten (`/portaal/`), beheer voor het bestuur
(`/admin/`), landingspagina's (`/p/<naam>`) en een API voor de website (`/api/`).

PHP 8.3, MariaDB, Apache/PHP-FPM op Plesk (Cloud86). Authentik voor SSO. Geen framework, geen
Composer.

## Documentatie

| Voor wie | Document |
|---|---|
| Bestuur / beheerders | [Handleiding beheer](docs/handleiding/handleiding_beheer.md) |
| Bestuur: landingspagina's | [Handleiding landingspagina's](docs/handleiding/landingspaginas.md) |
| Penningmeester | [Handleiding penningmeester](docs/handleiding/handleiding_penningmeester.md) |
| Sollicitanten en ouders | [Jouw sollicitatie bij Paal 13](docs/handleiding/handleiding_sollicitant.md) |
| Systeembeheer (installatie, uitrol, back-up, herstel) | [Runbook systeembeheer](docs/handleiding/runbook_systeembeheer.md) |

Van elke handleiding staat een Word-versie in `docs/handleiding/word/` en een HTML-versie ernaast;
die maak je opnieuw met `docs/handleiding/maak_export.ps1` (pandoc) na elke tekstwijziging.
Het vraagteken rechtsboven in het beheer linkt naar het juiste hoofdstuk.

## Omgevingen

| Omgeving | Adres | Branch | Uitrol |
|---|---|---|---|
| Productie | sollicitatie.paal13.com | `main` | handmatig via Plesk › Git › Deploy |
| Test | test.sollicitatie.paal13.com | `Development` | automatisch bij elke push |

Werkwijze: bouwen op `Development` → testen op de testomgeving → merge naar `main` → uitrollen
volgens de checklist in het runbook. `includes/config.php` bevat de geheimen en staat nooit in Git;
`includes/config.template.php` is het voorbeeld.

## Voor ontwikkelaars

- `CLAUDE.md` (lokaal, niet in Git) en `docs/ai/` bevatten de werkafspraken, de projectcontext en
  de testharnassen.
- De changelog staat in `includes/versie.php` en is in het beheer te lezen onder
  Instellingen › Systeem & Updates.
- Nieuwe databasekolommen: via `db_kolom_verzeker()` in `includes/db_failsafe.php` (draait
  automatisch één keer per versie). Grotere schemawijzigingen als `sql/migratie_NNNN_*.sql`,
  uit te voeren via Systeem & Updates › Migraties uitvoeren.
- Aanmeldformulier op WordPress: gebruik bij voorkeur een landingspagina. Een iframe op
  `https://sollicitatie.paal13.com/aanmelden.php` blijft werken vanaf paal13.com (en de origins
  die bij Instellingen › Website & API staan).

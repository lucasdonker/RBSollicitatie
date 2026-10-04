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

In het beheer staan ze onder Instellingen › Handleidingen, en het vraagteken rechtsboven op elk
scherm linkt naar het juiste hoofdstuk. De map `docs/` is op de server afgeschermd; lezen gaat via
`/admin/handleiding.php` (achter de login).

De Markdown is de bron. Na elke tekstwijziging draai je `docs/handleiding/maak_export.ps1` (pandoc)
om de HTML-versie (voor in het beheer) en de Word-versie in `docs/handleiding/word/` bij te werken;
allebei gaan mee in Git. `docs/ai/harnas/handleiding_check.php` controleert of elk anker waar
`includes/hulp.php` naar linkt echt bestaat.

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
- Het aanmeldformulier bestaat één keer: `includes/aanmeldformulier.php`. `/aanmelden.php` en de
  landingspagina's roepen die functie aan, de WordPress-plugin haalt de HTML op via
  `GET /api/formulier.php`. Een veld toevoegen doe je daar én in `includes/aanmelden_kern.php`.
- Aanmeldformulier op WordPress: installeer de plugin uit `wordpress-plugin/` (zie de README
  daar) en zet `[paal13_aanmeldformulier]` op een pagina. Een landingspagina (`/p/<naam>`) of
  een iframe op `https://sollicitatie.paal13.com/aanmelden.php?ingebed=1` blijft ook werken,
  vanaf paal13.com en de origins bij Instellingen › Website & API.

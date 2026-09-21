# Runbook systeembeheer — Sollicitatiesysteem Paal 13

Versie 2.94.0 · september 2026

Dit document is voor wie de servers beheert: installeren, uitrollen naar productie, configuratie,
back-ups, herstel en het lezen van de logs. Het is de enige plek waar de technische stappen staan.
Wat het bestuur in het beheer doet, staat in de [handleiding beheer](handleiding_beheer.md).

De handleidingen staan als Markdown in `docs/handleiding/`. De map `docs/` is op de server
afgeschermd (`docs/.htaccess`); in het beheer lees je ze onder Instellingen › Handleidingen, wat
de meegeleverde HTML-versie achter de login serveert. Na een tekstwijziging draai je
`docs/handleiding/maak_export.ps1` (pandoc) om de HTML en de Word-versies bij te werken en commit
je die mee.

## Inhoud

1. [Omgevingen en branches](#omgevingen-en-branches)
2. [Hoe het systeem in elkaar zit](#hoe-het-systeem-in-elkaar-zit)
3. [Uitrollen naar productie (checklist)](#uitrollen-naar-productie-checklist)
4. [Nieuwe server installeren](#nieuwe-server-installeren)
5. [config.php: alle instellingen](#configphp-alle-instellingen)
6. [Geheimen die in de database staan](#geheimen-die-in-de-database-staan)
7. [Cron](#cron)
8. [Authentik (SSO)](#authentik-sso)
9. [Systeemcontrole (health check)](#systeemcontrole-health-check)
10. [Back-up en herstel](#back-up-en-herstel)
11. [Terugdraaien (rollback)](#terugdraaien-rollback)
12. [Logs lezen](#logs-lezen)
13. [Beveiligingsmaatregelen in het kort](#beveiligingsmaatregelen-in-het-kort)
14. [Bekende beperkingen](#bekende-beperkingen)

---

## Omgevingen en branches

| Omgeving | Adres | Branch | Uitrol |
|---|---|---|---|
| Test | test.sollicitatie.paal13.com | `Development` | automatisch bij elke push (Plesk Git) |
| Productie | sollicitatie.paal13.com | `main` | handmatig: merge naar `main`, dan in Plesk "Deploy" |

Elke omgeving heeft een **eigen database**, een **eigen `includes/config.php`** en een **eigen
kluissleutel**. Niets daarvan staat in Git. Het systeem herkent de omgeving aan `APP_URL`: op test
staat een oranje label TESTOMGEVING in het beheer.

Werkwijze: bouwen op `Development` → testen op de testomgeving → merge naar `main` → uitrollen
volgens de checklist hieronder.

## Hoe het systeem in elkaar zit

- **PHP 8.3, MariaDB**, Apache met PHP-FPM achter de Plesk-proxy (Cloud86). Geen framework, geen
  Composer: alles wat nodig is zit in de repo (`includes/lib/` bevat TCPDF en FPDI voor de PDF's).
- **Twee kanten:** het portaal voor sollicitanten (`/portaal/`) en het beheer (`/admin/`).
  Daarnaast `/aanmelden.php` (het openbare aanmeldformulier), `/p/<naam>` (landingspagina's),
  `/api/` (voor de WordPress-plugin op paal13.com), `/track.php` (anonieme tellingen van het
  aanmeldformulier) en `/cron/run.php`.
- **Het aanmeldformulier staat op drie plekken maar bestaat één keer:** de HTML komt uit
  `includes/aanmeldformulier.php`. `/aanmelden.php` en de landingspagina's roepen die functie
  aan; de WordPress-plugin haalt hem op via `GET /api/formulier.php` en bewaart hem een paar
  minuten. Een veld toevoegen gebeurt daar én in `includes/aanmelden_kern.php`, nergens anders.
- **Inloggen:** beheerders via Authentik (OIDC); sollicitanten met een code per e-mail, WhatsApp
  of sms, of via Authentik als ze al lid zijn.
- **Meldingen:** één verzendkern voor e-mail (SMTP), WhatsApp (WhatsReach) en sms. Alles gaat via
  een wachtrij die door de cron wordt geleegd.
- **Gevoelige gegevens** (kopie ID, bankpas, BSN, IBAN, verzegelde PDF's) staan versleuteld in de
  **kluis**: bestanden in `DOCUMENTEN_MAP` buiten de webroot, sleutel in `config.php` plus een zout
  in de database. Zonder sleutel zijn ze voor niemand leesbaar.
- **Zelfherstel:** bij de eerste pagina na een nieuwe versie maakt het systeem ontbrekende tabellen
  en kolommen zelf aan (`db_failsafe`). Grotere stappen staan als SQL-bestanden in `sql/` en
  draai je via Beheer › Instellingen › Systeem & Updates › Migraties uitvoeren.

## Uitrollen naar productie (checklist)

Doorloop dit bij **elke** release. De stappen met ★ zijn eenmalig bij de overgang naar 2.94.0
(de eerste productie-uitrol sinds augustus 2026).

**Vooraf**

1. Alles is op de testomgeving getest volgens de checklist in `docs/ai/Toekomstige plannen/Nog te doen.md`.
2. Maak een back-up van de productiedatabase (Plesk › Databases › Exporteren) en van `DOCUMENTEN_MAP`.
3. Merge `Development` naar `main` (GitHub Desktop of een pull request). Push.

**Uitrollen**

4. Plesk › Git › productie-repository › **Deploy**. Wacht tot de deploy klaar is.
5. ★ Vul `includes/config.php` op productie aan met de sleutels die sinds augustus zijn bijgekomen
   (zie [config.php: alle instellingen](#configphp-alle-instellingen)):
   - `KLUIS_SLEUTEL` — genereer een nieuwe: `php -r "echo base64_encode(random_bytes(32));"`.
     **Bewaar een kopie in de wachtwoordkluis van het bestuur.** Kwijt = alle documenten kwijt.
   - `KLUIS_SLEUTEL_VERSIE` = `1`.
   - `DOCUMENTEN_MAP` = een map **buiten** `httpdocs`, bijvoorbeeld
     `/var/www/vhosts/paal13.com/private/sollicitatie-documenten`. Maak de map aan; de
     PHP-gebruiker van de site moet er kunnen schrijven (rechten 700).
   - Controleer dat de laatste twee regels `error_reporting(0);` en `ini_set('display_errors', 0);`
     erin staan. De `.htaccess` regelt dit niet onder PHP-FPM.
6. ★ Verwijder op de productieserver de mappen `setup/` en `_backups/` als die er nog staan
   (`_backups/` bevat kopieën van oude configuraties met wachtwoorden).
7. Open één willekeurige pagina, bijvoorbeeld `https://sollicitatie.paal13.com/portaal/`.
   Daarmee draait de zelfherstelronde voor deze versie.
8. Log in op het beheer en ga naar **Instellingen › Systeem & Updates**:
   - Klik **Migraties uitvoeren** (de SQL-bestanden in `sql/` die nog niet gedraaid zijn).
   - Klik **Systeemcontrole** en kijk of alle acht checks groen zijn. Kluis en zegel horen groen
     te zijn; het zegelcertificaat wordt bij de eerste keer automatisch aangemaakt.
   - ★ Kijk in **Instellingen › Foutenlog** (of het serverlog) of de regel
     `otp_tokens.type omgezet van ENUM naar VARCHAR(30)` er **één keer** staat. Zonder die
     omzetting wordt elke verificatiecode bij het ondertekenen afgekeurd.
9. ★ Ga naar **Beheerders**. Staat er nog een account `admin@paal13.com` op non-actief, laat dat
   zo (of verwijder het). Dat was het installatie-account met een openbaar wachtwoord; 2.94.0 zet
   het automatisch uit.
10. Open **Instellingen** een keer (daarmee wordt de cron-token aangemaakt als die ontbreekt) en
    controleer de cron in Plesk (zie [Cron](#cron)).
11. ★ Authentik: controleer de redirect-URI's en de groep `Sol-Penningmeester` (zie
    [Authentik](#authentik-sso)).
12. ★ Instellingen › **Algemeen**: vul het penningmeester-adres en het ledenservice-adres in en
    plaats de huisregels-pdf onder "Taken na aannemen". Laat "loondossier-stukken als bijlage
    meesturen" uit.
13. Stuur vanuit **Instellingen › Berichten** een testbericht per e-mail en, als WhatsApp aan
    staat, via WhatsApp. Controleer de regel in Berichten › log.
14. Test de inlog van een sollicitant (code per e-mail) en de inlog van een beheerder (Authentik).
15. ★ WordPress-plugin op paal13.com: bouw de ZIP met `wordpress-plugin\bouw-zip.ps1`, upload hem
    bij Plugins › Nieuwe plugin, en vul bij **Instellingen › Paal 13 Aanmelden** het adres en de
    API-sleutel in. Klik op **Verbinding testen** — er hoort te staan welk seizoen actief is.
    Stond er een oudere Paal 13-plugin op de site, verwijder die dan eerst.
    De sleutel gaat mee in de header `X-Paal13-Key`; de variant in het webadres (`?key=`) werkt
    sinds 2.94.0 niet meer. De pagina met het formulier mag niet in een paginacache staan: de
    plugin geeft dat zelf aan met `DONOTCACHEPAGE`, controleer het als je een exotische cache
    gebruikt.

**Na afloop**

16. Noteer de versie en datum in het logboek van het bestuur. De changelog staat in
    Systeem & Updates.

## Nieuwe server installeren

Alleen nodig bij een verhuizing of een extra omgeving. De installatiewizard bestaat niet meer.

1. Maak in Plesk een database en een databasegebruiker met alle rechten op die database.
2. Koppel de Git-repository aan het (sub)domein, branch `main` (productie) of `Development` (test),
   documentroot = de repo-root.
3. Kopieer `includes/config.template.php` naar `includes/config.php` en vul alle waarden in
   (tabel hieronder). Elke omgeving krijgt een **eigen** kluissleutel.
4. Importeer `sql/schema.sql` in de database (phpMyAdmin of `mysql < sql/schema.sql`).
5. Open een pagina; de zelfherstelronde maakt de rest van de tabellen aan. Draai daarna in
   Systeem & Updates de migraties.
6. Log in via Authentik met een account in de groep `Sol-Admin`; dat account wordt automatisch
   beheerder met rol admin. Er is geen standaardaccount met wachtwoord meer.
7. Stel de cron in en doorloop de rest van de checklist hierboven vanaf stap 10.

Maprechten: `uploads/` schrijfbaar voor PHP; `DOCUMENTEN_MAP` schrijfbaar en buiten de webroot.
Alle andere mappen alleen-lezen is prima.

## config.php: alle instellingen

| Constante | Betekenis | Let op |
|---|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET` | Databaseverbinding | charset altijd `utf8mb4` |
| `APP_URL` | Volledig webadres van deze omgeving, zonder slash aan het eind | Bepaalt test/productie en alle links in mails |
| `MAIL_FROM`, `MAIL_FROM_NAME` | Afzender van alle e-mail | Moet een adres op een domein zijn met geldige SPF/DKIM |
| `MAIL_BESTUUR` | Standaard bestuursadres (meldingen, terugval) | Kan in het beheer per meldingstype worden overschreven |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_ENCRYPT` | Mailserver | Leeg = PHP `mail()`, alleen als noodoplossing |
| `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`, `OIDC_ISSUER`, `OIDC_SLUG` | Authentik-provider | Leeg = SSO uit; alleen wachtwoordlogin voor beheerders |
| `OIDC_REDIRECT_URI` | Callback voor het beheer | `<APP_URL>/admin/auth_callback.php`; het portaal gebruikt vast `<APP_URL>/portaal/auth_callback.php` |
| `KLUIS_SLEUTEL` | Hoofdsleutel van de kluis, base64 van 32 bytes | Uniek per omgeving, kopie in de wachtwoordkluis |
| `KLUIS_SLEUTEL_VERSIE` | Nummer van de sleutel | Alleen ophogen bij een sleutelwissel |
| `DOCUMENTEN_MAP` | Map voor versleutelde documenten | Buiten de webroot; leeg = `uploads/documenten` (health waarschuwt) |
| `ZEGEL_MAP`, `ZEGEL_NAAM`, `ZEGEL_ORGANISATIE` | Documentzegel (optioneel) | Standaard `<DOCUMENTEN_MAP>/zegel`, automatisch aangemaakt |
| `SESSION_NAME`, `SESSION_LIFETIME` | Sessiecookie | Laten staan |
| `error_reporting(0)`, `display_errors=0` | Geen foutmeldingen op het scherm | Verplicht op productie |

## Geheimen die in de database staan

Deze staan niet in `config.php` maar in de tabel `instellingen`, en vul je in het beheer in:

| Sleutel | Waar in het beheer | Wat |
|---|---|---|
| `cron.token` | Instellingen › Mail & herinneringen | Token voor `/cron/run.php`; wordt automatisch aangemaakt |
| `api.sleutel`, `api.origins` | Instellingen › Website & API | Sleutel en toegestane websites voor de WordPress-plugin |
| `ai.api_key` | Instellingen › AI | Sleutel voor de AI-hulp (Gemini) |
| `authentik.api_token`, `authentik.admin_groepen` | Instellingen › Authentik & competenties | Voor de competentie-sync en de groepen die beheerder mogen zijn |
| `sms.*`, `wa.*` | Instellingen › SMS, WhatsApp & meldingen | Sleutels van de sms-gateway en WhatsReach |
| `kluis.zout` | (automatisch) | Zout van de kluis; samen met `KLUIS_SLEUTEL` nodig om documenten te lezen |

Een database-export bevat deze waarden. Behandel exports dus als geheim.

## Cron

Plesk › Geplande taken, **elke 5 minuten**, als de gebruiker van de site:

```
curl -s "https://sollicitatie.paal13.com/cron/run.php?token=<cron.token>" > /dev/null
```

Wat de cron doet: berichtenwachtrij legen (max 8 per run), AI-wachtrij, herinneringen en
geplande berichten, automatisch uitnodigen, Authentik-sync en één keer per dag de AVG-opschoning.
Een lock van vier minuten voorkomt dubbel draaien.

Zonder cron blijft het systeem werken via een vangnet: een bezoek aan het beheer of het portaal
zet de wachtrij maximaal één keer per uur in gang. Dat vangnet doet **geen** AVG-opschoning en geen
Authentik-sync. De systeemcontrole waarschuwt na 30 minuten zonder cron en meldt een fout na 2 uur.

## Authentik (SSO)

In Authentik (`auth.paal13.com`) hoort één OAuth2/OpenID-provider met:

- Redirect-URI's (allebei, per omgeving):
  `https://sollicitatie.paal13.com/admin/auth_callback.php` en
  `https://sollicitatie.paal13.com/portaal/auth_callback.php`. Ontbreekt de tweede, dan krijgen
  sollicitanten die via Authentik inloggen "Redirect URI Error".
- Scopes `openid profile email`, en de groepen in het token (claim `groups`).
- Groepen: `Sol-Admin` (rol admin, alles), `Bestuur` (rol bestuur), `Sol-Penningmeester`
  (rol penningmeester: alleen loondossiers en sollicitanten lezen), `Sol-Begeleider`
  (rol begeleider: selectiedag en dekking lezen). De hoogste rang wint als iemand in meer groepen zit.
  Rechten per rol pas je aan in Beheer › Rollen & rechten.

Nieuwe beheerders maak je dus niet in het beheer aan: zet de persoon in Authentik in de juiste groep
en laat hem één keer inloggen. Wachtwoordlogin (`/admin/login.php`) blijft bestaan als noodingang
voor accounts waarvoor in het beheer een wachtwoord is gezet.

## Systeemcontrole (health check)

Beheer › Instellingen › Systeem & Updates › Systeemcontrole. Acht checks:

| Check | Groen | Waarschuwing / fout | Wat te doen |
|---|---|---|---|
| Database | Alle tabellen en kolommen aanwezig | Ontbrekend onderdeel | Klik Repareren; anders migraties uitvoeren |
| Cron | Laatste run < 30 min geleden | > 30 min waarschuwing, > 2 uur fout | Cron in Plesk controleren, token vergelijken |
| Mailwachtrij | Niets ouder dan 1 uur in "wacht" | Berichten blijven hangen | Cron en SMTP-instellingen; Berichten › log |
| Opschoning | AVG-opschoning < 3 dagen geleden | Langer geleden | Cron draait niet als echte cron |
| Mappen | `uploads/` schrijfbaar | Niet schrijfbaar | Maprechten |
| Configuratie | cron-token en bestuursadres gezet | Iets ontbreekt | Instellingen openen, `MAIL_BESTUUR` in config |
| Kluis | Werkt, documentenmap buiten webroot | Sleutel ontbreekt/ongeldig, map in webroot | `KLUIS_SLEUTEL`, `DOCUMENTEN_MAP` in config |
| Zegel | Certificaat geldig > 60 dagen | Ontbreekt of verloopt | Wordt automatisch aangemaakt; bij verlopen: zegelmap leegmaken en pagina openen |

## Back-up en herstel

Wat je nodig hebt om alles terug te zetten:

1. **Database** (Plesk-back-up of `mysqldump`), dagelijks.
2. **`DOCUMENTEN_MAP`** (de versleutelde documenten en de zegelsleutel), dagelijks.
3. **`includes/config.php`** van elke omgeving, met daarin de `KLUIS_SLEUTEL`. Bewaar die sleutel
   óók los in de wachtwoordkluis van het bestuur.
4. **`uploads/`** (BLK-foto's, bijlagen, landingspagina-ontwerpen).

De code zelf staat in Git en hoeft niet in de back-up.

Herstel: code uitrollen via Plesk, `config.php` terugzetten, database importeren,
`DOCUMENTEN_MAP` en `uploads/` terugzetten, pagina openen, systeemcontrole draaien.
**Zonder de originele `KLUIS_SLEUTEL` én het zout uit de database zijn de documenten definitief
onleesbaar.** Dat is bewust: een gelekte back-up zonder sleutel is waardeloos voor een aanvaller.

Bewaartermijnen van back-ups: hanteer dezelfde termijn als de langste wettelijke bewaartermijn in het
systeem (5 jaar voor loondocumenten) en vernietig back-ups die ouder zijn.

## Terugdraaien (rollback)

Blijkt een release kapot op productie:

1. Plesk › Git › kies de vorige commit op `main` (of zet in Git `main` terug met een revert en push).
2. Deploy.
3. Open een pagina. Nieuwe kolommen die de nieuwe versie heeft aangemaakt blijven staan; dat is
   onschadelijk, oudere code negeert ze.
4. Meld het in `docs/ai/Toekomstige plannen/Nog te doen.md` zodat het in de volgende release wordt opgelost.

Zet nooit een oudere database-back-up terug alleen om code terug te draaien: dan verlies je
aanmeldingen en ondertekeningen van na de back-up.

## Logs lezen

| Waar | Wat | Voor wie |
|---|---|---|
| Beheer › Logboek | Alles in één tijdlijn: acties van beheerders, verstuurde berichten, fouten, ondertekenbewijs, opschoning, mislukte logins | admin (bestuur na toekennen recht) |
| Beheer › Instellingen › Foutenlog | Technische fouten met code en referentie | admin |
| Plesk › Logs (PHP error_log) | Regels die het systeem zelf schrijft (`db_failsafe: …`, `cron …`, `rate_limit: …`) en PHP-fouten | systeembeheerder |
| Foutmail | Bij ernstige fouten gaat een mail naar `mail.fout_email` (standaard website@paal13.com), maximaal één per code per vijf minuten | systeembeheerder |

Een foutmelding op het scherm toont een code en een referentie (`P13-S000 · ref 1a2b3c4d`); zoek die
referentie in het Foutenlog.

## Beveiligingsmaatregelen in het kort

Voor de verantwoording (AVG, NIS2-zorgplicht) staat hier wat het systeem doet:

- **Toegang:** SSO via Authentik met groepen; rollen en rechten per module; sessiecookie alleen via
  https, HttpOnly, SameSite; sessie-id vernieuwt na inloggen en elk half uur.
- **Brute force:** 5 mislukte logins per 15 minuten per e-mail én per IP-adres; inlogcode voor
  sollicitanten maximaal 5 pogingen per code en 10 aanvragen per uur per IP; verificatiecode bij
  ondertekenen 5 per uur per document; aanmeldformulier 5 per uur per IP met honeypot;
  downloads van loondocumenten 30 per uur per sessie.
- **Headers:** Content-Security-Policy (alleen eigen scripts en verbindingen), HSTS,
  X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy. Landingspagina's hebben een eigen,
  strengere CSP.
- **CSRF:** elk formulier en elke beheer-API met token.
- **Uploads:** typecontrole op inhoud, geen PHP-uitvoering in `uploads/`, gevoelige bestanden
  alleen versleuteld in de kluis buiten de webroot.
- **Versleuteling:** XChaCha20-Poly1305 of AES-256-GCM per bestand, sleutel per bestand,
  hoofdsleutel uit config plus zout uit de database, hash-controle bij lezen.
- **Ondertekenen:** bewijslog (IP, browser, tijd, kanaal van de verificatiecode), verzegelde PDF
  met eigen certificaat, PDF eenmalig gemaakt en versleuteld bewaard.
- **Logging:** audit van alle beheeracties, downloads en rechtenwijzigingen; exports worden zelf
  gelogd; berichtinhoud komt nooit in het logboek.
- **Bewaartermijnen:** automatische opschoning per categorie (instelbaar), verwerkingsregister in
  `docs/ai/PROJECT.md` §8.
- **Foutafhandeling:** geen technische details op het scherm, wel een referentie; foutmail naar
  de beheerder.

## Bekende beperkingen

- Het zegelcertificaat is zelf uitgegeven: Acrobat toont "geldigheid onbekend" in plaats van een
  groen vinkje. Het bewijst wél dat de PDF na ondertekening niet is gewijzigd. Een AATL-certificaat
  kost geld en is bewust niet aangeschaft.
- WhatsApp loopt via WhatsReach (geen officiële Meta-koppeling). Het bestuur kan het per
  meldingstype uitzetten; bij storing valt alles terug op e-mail.
- "Halverwege weggegaan" in de trechter bij Landingspagina's is een aanwijzing, geen telling:
  het signaal komt van een tabblad dat naar de achtergrond gaat, en sommigen komen terug.
- Het formulier bewaart tijdens het invullen wat iemand intypt in `sessionStorage` van de eigen
  browser, zodat de terugknop niet alles wist. Dat verdwijnt zodra het tabblad sluit en bij het
  verzenden; toestemmingsvinkjes worden er bewust niet in bewaard.
- Er is geen automatische test-suite op de server; de harnassen in `docs/ai/harnas/` draaien
  lokaal en staan niet in Git.

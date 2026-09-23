# Runbook systeembeheer — Sollicitatiesysteem Paal 13

Versie 2.99 · september 2026

Dit document is voor wie de servers beheert: installeren, uitrollen naar productie, configuratie,
back-ups, herstel, toegangsbeheer, incidenten en het lezen van de logs. Het is de enige plek waar
de technische stappen staan. Wat het bestuur in het beheer doet, staat in de
[handleiding beheer](handleiding_beheer.md).

De handleidingen staan als Markdown in `docs/handleiding/`. De map `docs/` is op de server
afgeschermd (`docs/.htaccess`); in het beheer lees je ze via het vraagteken rechtsboven of
`/admin/handleiding.php`, dat de meegeleverde HTML-versie achter de login serveert. Dit runbook
is alleen zichtbaar voor wie de module Systeem mag lezen. Na een tekstwijziging draai je
`docs/handleiding/maak_export.ps1` (pandoc) om de HTML en de Word-versies bij te werken en commit
je die mee.

**Inhoud**

1. [Aan de slag](#aan-de-slag)
2. [Omgevingen en branches](#omgevingen-en-branches)
3. [Hoe het systeem in elkaar zit](#hoe-het-systeem-in-elkaar-zit)
4. [Uitrollen naar productie (checklist)](#uitrollen-naar-productie-checklist)
5. [Nieuwe server installeren](#nieuwe-server-installeren)
6. [config.php: alle instellingen](#configphp-alle-instellingen)
7. [Geheimen die in de database staan](#geheimen-die-in-de-database-staan)
8. [Cron](#cron)
9. [Authentik (SSO)](#authentik-sso)
10. [Toegangsbeheer](#toegangsbeheer)
11. [WordPress-plugin](#wordpress-plugin)
12. [Bewaartermijnen en privacy](#bewaartermijnen-en-privacy)
13. [Systeemcontrole (health check)](#systeemcontrole-health-check)
14. [Back-up en herstel](#back-up-en-herstel)
15. [Terugdraaien (rollback)](#terugdraaien-rollback)
16. [Logs lezen](#logs-lezen)
17. [Datalek of beveiligingsincident](#datalek-of-beveiligingsincident)
18. [Beveiligingsmaatregelen in het kort](#beveiligingsmaatregelen-in-het-kort)
19. [Bekende beperkingen](#bekende-beperkingen)

---

## Aan de slag

> **In het kort:** Nieuw als technisch beheerder? Regel in je eerste uur je toegang, leer de
> vijf plekken waar alles staat en draai één keer de systeemcontrole op test en productie.
> Daarna houdt de onderhoudskalender hieronder het systeem gezond.

**Je eerste uur**

- [ ] Toegang tot **Plesk** (Cloud86) voor het domein `sollicitatie.paal13.com`, inclusief de
  subdomeinen voor test.
- [ ] Een **Authentik-account** in de groep `Sol-Admin`. Log één keer in op
  `https://test.sollicitatie.paal13.com/admin/` en op productie.
- [ ] Toegang tot de **Git-repository** (branches `Development` en `main`).
- [ ] Toegang tot de **wachtwoordkluis van het bestuur**. Daar horen per omgeving te staan: de
  `KLUIS_SLEUTEL`, het databasewachtwoord, het SMTP-wachtwoord en het OIDC client secret.
  Ontbreekt de kluissleutel van productie daar, meld dat dezelfde dag: zonder die sleutel is een
  back-up van de documenten waardeloos.
- [ ] Open op beide omgevingen **Beheer › Systeem & Updates**: noteer de versie en controleer of
  alle acht checks van de [systeemcontrole](#systeemcontrole-health-check) groen zijn. Kijk bij
  Cron of er "via cron" staat en niet "via lazy".
- [ ] Open **Instellingen › Bewaren & opschonen** op productie en controleer dat
  "Automatisch opschonen" aan staat (zie [Bewaartermijnen en privacy](#bewaartermijnen-en-privacy)).
- [ ] Open **Logboek**, filter op bron Fouten, en kijk wat er de afgelopen week gebeurde.
- [ ] Zorg dat je de foutmails ontvangt: het adres staat bij **Instellingen › Afzender &
  adressen** (`mail.fout_email`, standaard website@paal13.com).

**Waar staat wat**

| Wat | Waar | In Git? |
|---|---|---|
| Code | Git-repository; test volgt `Development`, productie `main` | ja |
| Configuratie per omgeving | `includes/config.php` op de server | nee, nooit |
| Geheimen van koppelingen (API, AI, WhatsApp, sms, SMTP) | tabel `instellingen` in de database | nee |
| Versleutelde documenten, foto's, zegelcertificaat | `DOCUMENTEN_MAP` op de server, buiten de webroot | nee |
| Overige uploads (logo, landingspagina's, bijlagen) | `uploads/` in de webroot | nee |
| Geplande taak | Plesk › Geplande taken | nee |
| Versie en changelog | Beheer › Systeem & Updates (bron: `includes/versie.php`) | ja |
| Logboek (acties, berichten, fouten) | Beheer › Logboek | nee (database) |
| Serverlog (PHP-fouten, `db_failsafe`, `cron`) | Plesk › Logs | nee |
| WordPress-plugin | `wordpress-plugin/` in de repo; geïnstalleerd op paal13.com | ja |
| Ontwikkelnotities, testharnassen, verwerkingsregister | `docs/ai/` op de machine van de ontwikkelaar | nee (staat in `.gitignore`) |

**Onderhoudskalender**

Dagelijks, in het seizoen (maart tot en met september):

- [ ] Foutmails lezen. Eén foutmail is een signaal, een reeks dezelfde is een storing.
- [ ] Het scherm Vandaag in het beheer toont de berichtenwachtrij en mislukte berichten; staan
  daar mislukte berichten, kijk in Logboek › Berichten waarom.

Wekelijks:

- [ ] Systeemcontrole op productie: alle acht groen, Cron "via cron".
- [ ] Logboek, bron Fouten: nieuwe foutcodes? Zoek de referentie op (zie [Logs lezen](#logs-lezen)).
- [ ] Logboek, bron Mislukte logins: een piek van mislukte logins op één adres of één e-mailadres
  wijst op raden van wachtwoorden of codes.
- [ ] Plesk › Back-up: is de nachtelijke back-up van database én `DOCUMENTEN_MAP` gelukt?
- [ ] Plesk: schijfruimte en of de PHP-versie van het domein nog 8.3 of hoger is.

Maandelijks:

- [ ] Plesk-updates en PHP-updates door Cloud86 bijgehouden? PHP 8.3 krijgt beveiligingsupdates
  tot eind 2027; plan de overstap op tijd en test eerst op de testomgeving.
- [ ] TLS-certificaten (Let's Encrypt in Plesk) verlengen automatisch; controleer de vervaldatum.
- [ ] Beheer › Beheerders: staat er iemand op actief die geen rol meer heeft in de brigade?

Per seizoen, vóór de aanmelding opengaat en na afloop:

- [ ] **Hersteltest** van een back-up (zie [Back-up en herstel](#back-up-en-herstel)).
- [ ] **Toegangsreview**: Authentik-groepen, Beheerders en Rollen & rechten naast elkaar leggen
  (zie [Toegangsbeheer](#toegangsbeheer)).
- [ ] **Privacyverklaring** (`/privacy.php`) lezen: kloppen de verwerkers en de termijnen nog?
  Zie [Bewaartermijnen en privacy](#bewaartermijnen-en-privacy).
- [ ] Verwerkersovereenkomsten aanwezig voor Cloud86, de e-mailprovider en, als ze aanstaan,
  WhatsReach, de sms-dienst en Google (Gemini). Het bestuur bewaart ze; jij controleert dat de
  lijst klopt met wat er in Instellingen aanstaat.
- [ ] Opschoon-logboek (Instellingen › Bewaren & opschonen): draait de opschoning dagelijks en
  verdwijnen afgewezen sollicitanten na de ingestelde termijn? (Ook zichtbaar in Logboek, bron
  AVG-opschoning.)
- [ ] WordPress-plugin: **Verbinding testen** op paal13.com en één proefaanmelding op test.
- [ ] Overweeg de API-sleutel te vernieuwen (zie [WordPress-plugin](#wordpress-plugin)).
- [ ] Testomgeving: staan er echte persoonsgegevens op? Verwijder die.

Per release: de [checklist](#uitrollen-naar-productie-checklist).

Jaarlijks: de [incidentprocedure](#datalek-of-beveiligingsincident) met het bestuur doorlopen en de
contactgegevens daarin bijwerken.

## Omgevingen en branches

> **In het kort:** Er is een test- en een productieomgeving, elk met een eigen database,
> `config.php` en kluissleutel. Test volgt `Development` automatisch; productie gaat handmatig
> vanaf `main`.

| Omgeving | Adres | Branch | Uitrol |
|---|---|---|---|
| Test | test.sollicitatie.paal13.com | `Development` | automatisch bij elke push (Plesk Git) |
| Productie | sollicitatie.paal13.com | `main` | handmatig: merge naar `main`, dan in Plesk "Deploy" |

Elke omgeving heeft een **eigen database**, een **eigen `includes/config.php`** en een **eigen
kluissleutel**. Niets daarvan staat in Git. Het systeem herkent de omgeving aan `APP_URL`
(`omgeving()` in `includes/functions.php`): een host met `test`, `staging`, `dev` of `acc` erin,
of `localhost`, is test. Op test staat een oranje label TESTOMGEVING in het beheer.

Werkwijze: bouwen op `Development` → testen op de testomgeving → merge naar `main` → uitrollen
volgens de checklist.

Zet geen echte persoonsgegevens op de testomgeving. Test met verzonnen sollicitanten en je eigen
e-mailadres.

## Hoe het systeem in elkaar zit

> **In het kort:** Eén PHP-applicatie zonder framework met twee kanten (portaal en beheer), een
> publieke API voor de WordPress-plugin en een cron. Gevoelige stukken staan versleuteld in de
> kluis; het schema herstelt zichzelf na elke nieuwe versie.

- **PHP 8.3, MariaDB**, Apache met **PHP-FPM** achter de Plesk-proxy (Cloud86). Geen framework,
  geen Composer: alles zit in de repo (`includes/lib/` bevat TCPDF en FPDI voor de PDF's).
  Benodigde PHP-extensies: `pdo_mysql`, `openssl`, `sodium`, `curl`, `mbstring`, `gd`, `fileinfo`.
- **PHP-FPM is een eis, geen voorkeur.** Mails, WhatsApp, het auditlog en de lazy-cron draaien
  ná het antwoord aan de bezoeker (`includes/na_antwoord.php`, via `fastcgi_finish_request`).
  Zet de PHP-handler in Plesk dus op een FPM-variant. Onder een andere handler werkt alles nog,
  maar wacht de bezoeker tot het werk klaar is.
- **Twee kanten:** het portaal voor sollicitanten (`/portaal/`) en het beheer (`/admin/`).
  Daarnaast de openbare adressen `/aanmelden.php` (aanmeldformulier), `/privacy.php`
  (privacyverklaring), `/p/<naam>` (landingspagina's), `/foto.php` (foto van een sollicitant,
  alleen met toegang), `/d/<code>` (tijdelijke downloadlink van een bijlage), `/track.php`
  (anonieme tellingen van het aanmeldformulier), `/api/` (voor de WordPress-plugin) en
  `/cron/run.php`.
- **Het aanmeldformulier bestaat één keer:** de HTML komt uit `includes/aanmeldformulier.php`,
  de verwerking uit `includes/aanmelden_kern.php`. `/aanmelden.php` en de landingspagina's roepen
  die aan; de WordPress-plugin haalt het formulier op via `GET /api/formulier.php` en post via
  `POST /api/aanmelden.php`.
- **Inloggen:** beheerders via Authentik (OIDC). Sollicitanten met een code of een eenmalige
  inloglink per e-mail, WhatsApp of sms, of via Authentik als ze al lid zijn. "Blijf ingelogd" op
  het portaal is een apart token (zie [Toegangsbeheer](#toegangsbeheer)).
- **Meldingen:** één verzendkern voor e-mail (SMTP), WhatsApp (WhatsReach) en sms. Berichten gaan
  meteen na het antwoord de deur uit of via de wachtrij (`mail_queue`) die de cron leegt.
- **Gevoelige gegevens** (kopie ID, bankpas, BSN, IBAN, verzegelde PDF's) staan versleuteld in de
  **kluis**: bestanden in `DOCUMENTEN_MAP` buiten de webroot, hoofdsleutel in `config.php` plus
  een zout in de database. **Foto's van sollicitanten** staan sinds 2.99.3 ook in
  `DOCUMENTEN_MAP` (submap `fotos/`), met een willekeurige naam en alleen op te vragen via
  `/foto.php`; ze zijn afgeschermd maar niet versleuteld.
- **Zelfherstel:** bij het eerste verzoek na een nieuwe versie maakt `db_failsafe()` ontbrekende
  tabellen en kolommen aan en doet het eenmalige verhuizingen (bijvoorbeeld de foto's uit
  `uploads/fotos/`). Daarna slaat hij zichzelf over tot de volgende versie (instelling
  `db.failsafe_versie`).
- **Beheerschermen** zijn opgebouwd uit gedeelde bouwstenen (`includes/ui_beheer.php`,
  `admin/_*.php`, `assets/css/beheer.css`). Voor jou betekent dat: een weergavefout zit bijna
  altijd op één plek en geldt dan voor alle schermen tegelijk.

## Uitrollen naar productie (checklist)

> **In het kort:** Doorloop dit bij elke release, van back-up tot rooktest. Stappen met
> "(eenmalig)" horen bij de eerste productie-uitrol sinds augustus 2026 en kun je daarna
> overslaan.

**Vooraf**

1. Alles is op de testomgeving getest. Minimaal deze rooktest: aanmelden via `/aanmelden.php`
   en via de WordPress-plugin, inloggen op het portaal met code en met inloglink, een formulier
   invullen, een fase wijzigen in het beheer, een bericht versturen, en de systeemcontrole
   groen. De uitgebreide testlijst houdt de ontwikkelaar bij in `docs/ai/` (niet in Git).
2. Maak een back-up van de productiedatabase (Plesk › Databases › Exporteren) en van
   `DOCUMENTEN_MAP`.
3. Merge `Development` naar `main` (GitHub Desktop of een pull request). Push.

**Uitrollen**

4. Plesk › Git › productie-repository › **Deploy**. Wacht tot de deploy klaar is.
5. (eenmalig) Vul `includes/config.php` op productie aan met de sleutels die sinds augustus
   zijn bijgekomen (zie [config.php: alle instellingen](#configphp-alle-instellingen)):
   - `KLUIS_SLEUTEL` — genereer een nieuwe: `php -r "echo base64_encode(random_bytes(32));"`.
     **Bewaar een kopie in de wachtwoordkluis van het bestuur.** Kwijt = alle documenten kwijt.
   - `KLUIS_SLEUTEL_VERSIE` = `1`.
   - `DOCUMENTEN_MAP` = een map **buiten** `httpdocs`, bijvoorbeeld
     `/var/www/vhosts/paal13.com/private/sollicitatie-documenten`. Maak de map aan; de
     PHP-gebruiker van de site moet er kunnen schrijven (rechten 700).
   - Controleer dat de laatste regels `error_reporting(0);` en `ini_set('display_errors', 0);`
     erin staan. De `.htaccess` regelt dit niet onder PHP-FPM.
6. (eenmalig) Verwijder op de productieserver de mappen `setup/` en `_backups/` als die er nog
   staan (`_backups/` bevat kopieën van oude configuraties met wachtwoorden).
7. Open één willekeurige pagina, bijvoorbeeld `https://sollicitatie.paal13.com/portaal/`.
   Daarmee draait de zelfherstelronde voor deze versie.
8. Log in op het beheer en ga naar **Systeem & Updates**:
   - Klik **Migraties uitvoeren**. Dat forceert de zelfherstelronde nog eens (`db_failsafe`) en
     is veilig om vaker te doen.
   - Open `/admin/update.php` (niet in het menu, alleen admin) en kijk of er losse
     SQL-migraties uit `sql/` op "pending" staan. Normaal is dat nul; de laatste is
     `migratie_0021`.
   - Kijk bij **Systeemgezondheid** of alle acht checks groen zijn. Kluis en zegel horen groen te
     zijn; het zegelcertificaat wordt bij de eerste keer automatisch aangemaakt.
   - (eenmalig) Zoek in het serverlog (Plesk › Logs) of Logboek › Fouten de regel
     `otp_tokens.type omgezet van ENUM naar VARCHAR(30)`. Die hoort er **één keer** te staan.
     Zonder die omzetting wordt elke verificatiecode bij het ondertekenen afgekeurd.
   - (eenmalig, 2.99.3) Controleer via Plesk › Bestandsbeheer dat `uploads/fotos/` leeg is (op
     een `.htaccess` na) en dat `<DOCUMENTEN_MAP>/fotos/` de foto's bevat. Blijft er iets
     staan, dan kon de verhuizing niet schrijven: controleer de rechten van `DOCUMENTEN_MAP`
     en klik nog eens Migraties uitvoeren.
9. (eenmalig) Ga naar **Beheerders**. Staat er nog een account `admin@paal13.com` op non-actief,
   laat dat zo (of verwijder het). Dat was het installatie-account met een openbaar wachtwoord;
   sinds 2.94.0 zet het systeem het automatisch uit.
10. Controleer de cron in Plesk (zie [Cron](#cron)) en kijk na tien minuten of de systeemcontrole
    bij Cron "via cron" meldt.
11. (eenmalig) Authentik: controleer de redirect-URI's en de groep `Sol-Penningmeester`, en zet
    die groep bij **Instellingen › Authentik** in de lijst met toegestane groepen (zie
    [Authentik](#authentik-sso)).
12. (eenmalig) **Instellingen › Algemeen**: vul het penningmeester-adres en het
    ledenservice-adres in en plaats de huisregels-pdf onder "Taken na aannemen". Laat
    "loondossier-stukken als bijlage meesturen" uit.
13. (eenmalig) **Instellingen › Bewaren & opschonen**: zet "Automatisch opschonen" aan en
    controleer de termijnen (zie [Bewaartermijnen en privacy](#bewaartermijnen-en-privacy)).
14. Stuur vanuit **Instellingen › Kanalen & meldingen** een testbericht per e-mail en, als
    WhatsApp aan staat, via WhatsApp. Controleer de regel in Logboek › Berichten.
15. Test de inlog van een sollicitant (code én inloglink per e-mail) en de inlog van een
    beheerder (Authentik).
16. Open `https://sollicitatie.paal13.com/privacy.php`: de pagina laadt en de termijnen kloppen
    met wat je bij stap 13 zag.
17. WordPress-plugin op paal13.com: alleen als de plugin in deze release is veranderd, of
    (eenmalig) bij de eerste installatie. Zie [WordPress-plugin](#wordpress-plugin). Klik daarna
    in WordPress op **Verbinding testen** — er hoort te staan welk seizoen actief is.

**Na afloop**

18. Noteer de versie en datum in het logboek van het bestuur. De changelog staat in
    Systeem & Updates.
19. Kijk de volgende ochtend in Logboek › Fouten of er sinds de uitrol iets nieuws bij is.

## Nieuwe server installeren

> **In het kort:** Alleen bij een verhuizing of een extra omgeving. Database aanmaken, repo
> koppelen, `config.php` invullen, `schema.sql` importeren, één pagina openen en inloggen via
> Authentik. Een installatiewizard is er niet meer.

1. Maak in Plesk een database en een databasegebruiker met alle rechten op die database.
2. Koppel de Git-repository aan het (sub)domein, branch `main` (productie) of `Development` (test),
   documentroot = de repo-root. Zet de PHP-handler op PHP 8.3 FPM.
3. Kopieer `includes/config.template.php` naar `includes/config.php` en vul alle waarden in
   (tabel hieronder). Elke omgeving krijgt een **eigen** kluissleutel.
4. Importeer `sql/schema.sql` in de database (phpMyAdmin of `mysql < sql/schema.sql`).
5. Maak `DOCUMENTEN_MAP` aan buiten de webroot (rechten 700, eigenaar de PHP-gebruiker).
6. **Zet `uploads/.htaccess` terug.** De map `uploads/` staat niet in Git, en daarmee ook dit
   bestand niet. Het blokkeert het uitvoeren van scripts in de uploadmap. Neem het uit de back-up,
   of maak het aan met minimaal:

   ```
   <FilesMatch "\.(php|php3|php4|php5|php7|php8|phtml|phar|pht|cgi|pl|py|asp|aspx|sh|shtml)$">
       Require all denied
   </FilesMatch>
   RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar
   RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phar
   ```

7. Open een pagina; de zelfherstelronde maakt de rest van de tabellen aan.
8. Log in via Authentik met een account in de groep `Sol-Admin`; dat account wordt automatisch
   beheerder met rol admin. Er is geen standaardaccount met wachtwoord meer.
9. Stel de cron in en doorloop de rest van de checklist vanaf stap 8.

Maprechten: `uploads/` schrijfbaar voor PHP; `DOCUMENTEN_MAP` schrijfbaar en buiten de webroot.
Alle andere mappen alleen-lezen is prima.

## config.php: alle instellingen

> **In het kort:** `includes/config.php` bevat de databasegegevens, het webadres, de mailserver,
> Authentik en de kluis. Het staat nooit in Git; `includes/config.template.php` is het voorbeeld.

| Constante | Betekenis | Let op |
|---|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET` | Databaseverbinding | charset altijd `utf8mb4` |
| `APP_URL` | Volledig webadres van deze omgeving, zonder slash aan het eind | Bepaalt test/productie, https-detectie en alle links in mails |
| `MAIL_FROM`, `MAIL_FROM_NAME` | Afzender van alle e-mail | Moet een adres zijn op een domein met geldige SPF, DKIM en DMARC |
| `MAIL_BESTUUR` | Terugval voor het bestuursadres | Het adres in Instellingen › Afzender & adressen gaat voor; de systeemcontrole kijkt wel naar deze constante |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_ENCRYPT` | Mailserver | Worden overschreven door Instellingen › E-mailserver als daar een host staat. Allebei leeg = PHP `mail()`, alleen als noodoplossing |
| `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`, `OIDC_ISSUER`, `OIDC_SLUG` | Authentik-provider | Leeg = SSO uit; alleen wachtwoordlogin voor beheerders |
| `OIDC_REDIRECT_URI` | Callback voor het beheer | `<APP_URL>/admin/auth_callback.php`; het portaal gebruikt vast `<APP_URL>/portaal/auth_callback.php` |
| `KLUIS_SLEUTEL` | Hoofdsleutel van de kluis, base64 van 32 bytes | Uniek per omgeving, kopie in de wachtwoordkluis. Nooit vervangen zolang er documenten zijn |
| `KLUIS_SLEUTEL_VERSIE` | Nummer van de sleutel | Laat op `1`. Er is geen herversleutel-functie: ophogen maakt alle bestaande documenten onleesbaar |
| `DOCUMENTEN_MAP` | Map voor versleutelde documenten, foto's en het zegel | Buiten de webroot. Leeg = `uploads/documenten` (afgeschermd, maar de systeemcontrole waarschuwt) |
| `ZEGEL_MAP`, `ZEGEL_NAAM`, `ZEGEL_ORGANISATIE` | Documentzegel (optioneel) | Standaard `<DOCUMENTEN_MAP>/zegel`, automatisch aangemaakt |
| `ZEGEL_OPENSSL_CNF` | Pad naar `openssl.cnf` (optioneel) | Alleen nodig als openssl zijn configuratie niet zelf vindt; op Cloud86 niet |
| `SESSION_NAME` | Naam van de sessiecookie | Laten staan (`p13sess`) |
| `SESSION_LIFETIME` | Staat nog in het voorbeeld | Wordt niet meer gebruikt: de sessiecookie leeft vast acht uur (`sessie_start()`) |
| `date_default_timezone_set('Europe/Amsterdam')` | Tijdzone | Laten staan; bewaartermijnen en de cron rekenen ermee |
| `error_reporting(0)`, `display_errors=0` | Geen foutmeldingen op het scherm | Verplicht op productie |

Ontbreekt `config.php`, dan toont elke pagina "Systeem is nog niet ingesteld" (HTTP 503).

## Geheimen die in de database staan

> **In het kort:** Sleutels van koppelingen staan in de tabel `instellingen` en vul je in het
> beheer in. Een database-export bevat ze dus: behandel exports als geheim.

| Sleutel | Waar in het beheer | Wat |
|---|---|---|
| `cron.token` | Instellingen › Bewaren & opschonen (onderaan, "Cronjob") | Token voor `/cron/run.php`; wordt aangemaakt zodra dat tabblad opent en hij ontbreekt |
| `api.sleutel`, `api.origins` | Instellingen › Website & API | Sleutel en toegestane websites voor de WordPress-plugin. `api.origins` bepaalt ook wie het aanmeldformulier in een iframe mag tonen |
| `smtp.*` | Instellingen › E-mailserver | Mailserver en wachtwoord; gaat voor op `SMTP_*` in config |
| `ai.api_key` | Instellingen › AI-analyse | Sleutel voor de AI-hulp (Gemini) |
| `authentik.url`, `authentik.api_token`, `authentik.admin_groepen` | Instellingen › Authentik | Competentie-sync en de groepen die het beheer in mogen |
| `sms.*`, `wa.*` | Instellingen › Kanalen & meldingen | Sleutels van de sms-gateway en WhatsReach |
| `kluis.zout` | (automatisch, niet wijzigen) | Zout van de kluis; samen met `KLUIS_SLEUTEL` nodig om documenten te lezen |

Een sleutel vervangen: API-sleutel met de knop in Website & API (en meteen in de plugin
invullen); AI-, sms- en WhatsApp-sleutels bij de leverancier vernieuwen en in het beheer
plakken. Het cron-token heeft geen knop: maak de waarde leeg in phpMyAdmin
(`UPDATE instellingen SET waarde = '' WHERE sleutel = 'cron.token'`), open het tabblad Bewaren &
opschonen voor een nieuw token en zet dat in de Plesk-taak.

## Cron

> **In het kort:** Eén geplande taak in Plesk, elke 5 minuten, roept `/cron/run.php` aan met het
> cron-token. Zonder die taak blijven berichten hangen en draait de AVG-opschoning niet.

Plesk › Geplande taken, **elke 5 minuten**, type "URL ophalen" of een opdracht als de gebruiker
van de site:

```
curl -s "https://sollicitatie.paal13.com/cron/run.php?token=<cron.token>" > /dev/null
```

De volledige regel met het token staat in het beheer bij Instellingen › Bewaren & opschonen ›
Cronjob, met een knop "Handmatig uitvoeren". Het antwoord is JSON met per taak wat er gebeurde.

Wat de cron doet (`includes/cron_taken.php`):

- elke run: berichtenwachtrij legen (max 8 per run), AI-wachtrij (max 3), herinneringen en
  geplande berichten, automatisch uitnodigen;
- alleen vanuit de echte cron: Authentik-sync, verlopen "blijf ingelogd"-tokens opruimen,
  fotobestanden zonder dossier opruimen, en één keer per dag de AVG-opschoning.

Een lock van vier minuten (`cron.lock_tot`) voorkomt dubbel draaien.

Zonder cron blijft het systeem werken via een vangnet: een bezoek aan het beheer of het portaal
zet de wachtrij maximaal één keer per uur in gang, ná het antwoord aan de bezoeker. Dat vangnet
doet **geen** AVG-opschoning, geen Authentik-sync en geen opruiming van tokens en foto's. Omdat
het vangnet ook als "laatste run" telt, kan de systeemcontrole groen zijn terwijl de echte cron
stilligt: kijk of er "via cron" staat. Na 30 minuten zonder run volgt een waarschuwing, na 2 uur
een fout.

## Authentik (SSO)

> **In het kort:** Eén OAuth2/OpenID-provider in `auth.paal13.com` met twee redirect-URI's per
> omgeving. De groep in Authentik bepaalt of iemand het beheer in mag en met welke rol.

In Authentik hoort per omgeving één OAuth2/OpenID-provider met:

- Redirect-URI's (allebei):
  `https://sollicitatie.paal13.com/admin/auth_callback.php` en
  `https://sollicitatie.paal13.com/portaal/auth_callback.php` (op test met het testadres).
  Ontbreekt de tweede, dan krijgen sollicitanten die via Authentik inloggen "Redirect URI Error".
- Scopes `openid profile email`, en de groepen in het token (claim `groups`).
- Groepen en rollen: `Sol-Admin` (rol admin, alles), `Bestuur` (rol bestuur),
  `Sol-Penningmeester` (rol penningmeester: loondossiers en sollicitanten lezen),
  `Sol-Begeleider` (rol begeleider: selectiedag en dekking lezen). Welke groep bij welke rol hoort
  en wat een rol mag, pas je aan in Beheer › Rollen & rechten.
- **Toegestane groepen:** Instellingen › Authentik, veld met groepen die het beheer in mogen
  (`authentik.admin_groepen`, standaard `Bestuur,Sol-Admin,Sol-Begeleider`). Voeg
  `Sol-Penningmeester` toe, anders wordt een nieuwe penningmeester bij de eerste inlog geweigerd.
- Zet in Authentik **tweestapsverificatie verplicht** voor iedereen in deze groepen. Het
  sollicitatiesysteem zelf vraagt bij een Authentik-inlog geen tweede factor.

Nieuwe beheerders maak je niet in het beheer aan: zet de persoon in Authentik in de juiste groep
en laat hem één keer inloggen. Wachtwoordlogin (`/admin/login.php`) bestaat nog als noodingang
voor accounts waarvoor in het beheer een wachtwoord is gezet (zie [Toegangsbeheer](#toegangsbeheer)).

## Toegangsbeheer

> **In het kort:** Toegang loopt via Authentik-groepen, maar iemand weghalen vraagt twee stappen:
> uit de groep én op non-actief in Beheerders. Controleer de toegang elk seizoen en houd de
> noodingang met wachtwoord tot één account.

**Iemand erbij**

1. Authentik: account aanmaken, tweestapsverificatie aan, in de juiste groep zetten.
2. Laat de persoon inloggen op `/admin/`. Controleer in Beheer › Beheerders dat de rol klopt.

**Iemand eraf** (vertrek uit het bestuur, einde functie)

1. Authentik: uit de groep halen of het account uitschakelen.
2. **Beheer › Beheerders: het account op non-actief zetten.** Dit is nodig: een bestaand account
   dat bij het inloggen geen passende groep meer heeft, houdt de rol die het had.
3. Staat er een wachtwoord op het account, verwijder dat.
4. Zijn er sleutels die de persoon kende (API-sleutel, SMTP-wachtwoord, wachtwoordkluis),
   vervang die.

**Seizoensreview**

- [ ] Leg Authentik-groepen `Sol-*` en `Bestuur`, Beheer › Beheerders en Beheer › Rollen &
  rechten naast elkaar. Iedereen die actief is heeft een reden.
- [ ] Hoogstens één account met wachtwoordlogin (noodingang), met een lang wachtwoord dat in de
  wachtwoordkluis staat. Die inlog heeft geen tweede factor.
- [ ] Wie mag Instellingen bewerken? Dat recht (standaard bestuur en admin) geeft ook zicht op
  de API-sleutel en het cron-token.
- [ ] Wie heeft de module Documenten (BSN, IBAN, ID)? Standaard alleen penningmeester en admin.

**Sollicitanten op het portaal**

- Inlogcode en inloglink zijn 15 minuten geldig en eenmalig. De inloglink logt niet in bij het
  openen maar pas na een klik op een knop, omdat mailscanners links vooraf bezoeken.
- "Blijf ingelogd" (60 dagen, standaard uit) staat in de tabel `portaal_apparaten` en in de
  cookie `p13_apparaat`. Het token wisselt bij elk gebruik; wordt een oud token opnieuw
  aangeboden, dan trekt het systeem alle apparaten van die sollicitant in. Uitloggen, "vergeet
  alle apparaten" in Mijn gegevens en het verwijderen van een dossier trekken ze ook in; de cron
  ruimt verlopen tokens op.
- Moet je bij een vermoed misbruik alle apparaten van iedereen uitloggen: leeg de tabel
  `portaal_apparaten` in phpMyAdmin. Sollicitanten loggen daarna gewoon opnieuw in met een code.

## WordPress-plugin

> **In het kort:** De plugin "Paal 13 Aanmelden" zet het aanmeldformulier en seizoensdata op
> paal13.com. Hij praat met `/api/` met een geheime sleutel in de header `X-Paal13-Key`; de
> sleutel staat alleen op de WordPress-server, nooit in de pagina.

**Installeren of bijwerken**

1. Zet in het sollicitatiesysteem een API-sleutel klaar: **Instellingen › Website & API**.
   Controleer daar ook de toegestane websites (`https://paal13.com` en
   `https://www.paal13.com` staan er altijd in).
2. Bouw de ZIP op een Windows-machine met de repo: `wordpress-plugin\bouw-zip.ps1`. Er komt
   `paal13-aanmelden-<versie>.zip` naast te staan (ZIP's staan niet in Git).
3. WordPress › Plugins › Nieuwe plugin › Plugin uploaden, ZIP kiezen, activeren. Stond er een
   oudere Paal 13-plugin op de site, deactiveer en verwijder die eerst.
4. WordPress › Instellingen › Paal 13 Aanmelden: adres (`https://sollicitatie.paal13.com`) en
   sleutel invullen, **Verbinding testen**.
5. Zet `[paal13_aanmeldformulier]` op de aanmeldpagina. De overige shortcodes staan in
   `wordpress-plugin/README.md` en op de instellingenpagina van de plugin.

**Goed om te weten**

- De sleutel gaat alleen in de header `X-Paal13-Key` mee; `?key=` in het webadres werkt sinds
  2.94.0 niet meer.
- De plugin bewaart het formulier en de seizoensdata een paar minuten (instelbaar) en valt bij
  een storing terug op het laatste goede antwoord.
- De pagina met het formulier mag niet in een paginacache staan. De plugin zet zelf
  `DONOTCACHEPAGE`; controleer het als je een ongebruikelijke cache gebruikt.
- De plugin geeft het IP-adres van de bezoeker door in `X-Paal13-Bezoeker`. Dat adres wordt
  vastgelegd bij de aanmelding en telt voor de limiet van 10 aanmeldingen per uur. De plugin leest
  het uit proxyheaders (Cloudflare, `X-Forwarded-For`); staat paal13.com niet achter zo'n proxy,
  dan kan een bezoeker die headers zelf invullen.
- Een nieuwe sleutel werkt meteen en de oude niet meer. Genereer hem dus pas als je de
  WordPress-instellingen open hebt, en test direct.

## Bewaartermijnen en privacy

> **In het kort:** De AVG-opschoning doet niets tot "Automatisch opschonen" aan staat. Zet dat
> aan op productie en houd de termijnen gelijk aan wat de privacyverklaring belooft; die leest
> dezelfde instellingen.

**Aanzetten en controleren**

- Instellingen › Bewaren & opschonen › "Automatisch opschonen inschakelen" (`bewaar.actief`).
  Standaard staat dit **uit**; zolang het uit staat wordt er niets verwijderd of geanonimiseerd
  en meldt de systeemcontrole "opschoning heeft nog niet gedraaid".
- De opschoning draait één keer per dag via de echte cron (`includes/opschonen.php`). Wat er is
  opgeschoond staat in het Opschoon-logboek onderaan hetzelfde tabblad en in Logboek, bron AVG-opschoning.
- Er is geen proefdraaistand in het beheer. Wil je een nieuwe termijn eerst zien werken, zet
  hem dan eerst op de testomgeving.

**Standaardtermijnen**

| Wat | Termijn | Instelling |
|---|---|---|
| Afgewezen of afgehaakte sollicitanten (anonimiseren) | 4 weken na het besluit | `bewaar.afgewezen_weken` |
| Seintje bij de volgende werving | max 12 maanden | `bewaar.seintje_mnd` |
| Oud-lifeguards | 2 jaar na het laatste seizoen | `bewaar.aangenomen_jaar` |
| Gezondheidsgegevens | einde seizoen, direct bij afwijzing | `bewaar.gezondheid_mnd` (0) |
| AI-invoertekst | 3 maanden | `bewaar.ai_invoer_mnd` |
| Niet-voltooide aanmeldingen | 3 maanden | `bewaar.onvoltooid_mnd` |
| Berichtenlog / inhoud van berichten | 12 / 3 maanden | `bewaar.maillog_mnd`, `bewaar.maillog_body_mnd` |
| Losse bijlagen | 12 maanden | `bewaar.bijlagen_mnd` |
| Auditlog (acties van beheerders) | 12 maanden | `bewaar.audit_maanden` (niet in het beheer; alleen in de database te wijzigen) |
| Loonheffing en kopie ID | 5 jaar na het dienstjaar (wettelijk) | `bewaar.loondocumenten_jaar` |
| Kopie bankpas | 30 dagen na verwerking | `bewaar.bankpas_na_verwerking_dagen` |
| Mislukte inlogpogingen | 24 uur | vast |
| "Blijf ingelogd"-tokens | 60 dagen | vast |

**Privacyverklaring**

- De tekst staat in `includes/privacy_verklaring.php` en is openbaar op `/privacy.php`. De
  termijnen in de tabel komen uit de instellingen hierboven; de alinea's over AI, WhatsApp en
  sms verschijnen alleen als die dienst aanstaat. Een termijn wijzigen = de verklaring verandert
  mee.
- Verandert de tekst zelf (in code), dan hoort `PRIVACY_VERSIE` in `includes/functions.php`
  omhoog. Bij elke aanmelding wordt vastgelegd welke versie iemand las.
- Schakel je een nieuwe dienst in (bijvoorbeeld sms of een andere mailprovider), controleer dan
  of die als verwerker in de verklaring staat en of er een verwerkersovereenkomst is.
- De verklaring belooft dat back-ups na verloop van tijd automatisch verdwijnen. Houd de
  back-uptermijn daarom kort (zie [Back-up en herstel](#back-up-en-herstel)).

## Systeemcontrole (health check)

> **In het kort:** Beheer › Systeem & Updates › Systeemgezondheid voert acht checks uit en kan
> een deel zelf herstellen. Groen betekent niet altijd dat de echte cron draait: lees de uitleg
> bij Cron.

Knop **Controleren & repareren** draait de checks opnieuw en herstelt waar dat kan (vooral de
database). De knop "Diagnose draaien" op hetzelfde scherm verwijst naar een pagina die niet meer
bestaat; gebruik hem niet.

| Check | Groen | Waarschuwing / fout | Wat te doen |
|---|---|---|---|
| Database | Alle tabellen en kolommen aanwezig | Ontbrekend onderdeel | Controleren & repareren; anders Migraties uitvoeren en het serverlog lezen |
| Cron | Laatste run < 30 min geleden | > 30 min waarschuwing, > 2 uur fout | Plesk-taak en token controleren. Staat er "via lazy", dan draait de echte cron niet |
| Mailwachtrij | Niets ouder dan 1 uur in "wacht" | Berichten blijven hangen | Cron en E-mailserver-instellingen; Logboek › Berichten |
| Opschoning | AVG-opschoning < 3 dagen geleden | Nog nooit of langer geleden | "Automatisch opschonen" aan? Draait de echte cron? |
| Mappen | `uploads/` schrijfbaar | Niet schrijfbaar | Maprechten in Plesk |
| Configuratie | Cron-token en `MAIL_BESTUUR` gezet | Iets ontbreekt | Tabblad Bewaren & opschonen openen; `MAIL_BESTUUR` in config |
| Kluis | Werkt, documentenmap buiten webroot | Sleutel ontbreekt/ongeldig, map niet schrijfbaar of in webroot | `KLUIS_SLEUTEL`, `DOCUMENTEN_MAP` in config; rechten van de map |
| Zegel | Certificaat geldig > 60 dagen | Ontbreekt, verloopt of verlopen | Wordt automatisch aangemaakt (10 jaar geldig); bij verlopen: `cert.pem` en `key.enc` in de zegelmap weghalen en een pagina openen |

Let op bij het zegel: een nieuw certificaat heeft een andere vingerafdruk. Eerder verzegelde PDF's
blijven geldig; noteer de nieuwe vingerafdruk (staat in het Logboek bij
`zegel_certificaat_aangemaakt`).

## Back-up en herstel

> **In het kort:** Database, `DOCUMENTEN_MAP`, `config.php` en `uploads/` dagelijks, met een
> korte bewaartermijn. De kluissleutel staat los in de wachtwoordkluis. Test het herstel elk
> seizoen, want een back-up die nooit is teruggezet is een aanname.

**Wat je nodig hebt om alles terug te zetten**

1. **Database** (Plesk-back-up of `mysqldump`), dagelijks. Bevat ook het kluis-zout en de
   sleutels van koppelingen.
2. **`DOCUMENTEN_MAP`**, dagelijks: de versleutelde documenten, de foto's (`fotos/`) en het
   zegel (`zegel/`).
3. **`includes/config.php`** van elke omgeving, met daarin de `KLUIS_SLEUTEL`. Bewaar die sleutel
   óók los in de wachtwoordkluis van het bestuur, niet in dezelfde back-up.
4. **`uploads/`**: logo, landingspagina-ontwerpen, bijlagen van sjablonen, en `uploads/.htaccess`
   (die staat niet in Git).

De code zelf staat in Git en hoeft niet in de back-up.

**Bewaartermijn van back-ups:** houd een rollend venster van ongeveer 30 dagen aan en laat oudere
back-ups automatisch vervallen (Plesk › Back-up › Instellingen). Bewaar back-ups niet jaren: dan
blijven gegevens van afgewezen sollicitanten veel langer bestaan dan de privacyverklaring
belooft. De wettelijke bewaarplicht van loondocumenten wordt gedekt door het systeem zelf, niet
door oude back-ups.

**Herstel**

Code uitrollen via Plesk, `config.php` terugzetten, database importeren, `DOCUMENTEN_MAP` en
`uploads/` terugzetten, pagina openen, systeemcontrole draaien. **Zonder de originele
`KLUIS_SLEUTEL` én het zout uit de database zijn de documenten definitief onleesbaar.** Dat is
bewust: een gelekte back-up zonder sleutel is waardeloos voor een aanvaller.

**Hersteltest, elk seizoen**

1. Maak in Plesk een tijdelijke database en zet de productie-back-up daarin terug.
2. Koppel die aan een tijdelijk subdomein met een kopie van de productie-`config.php`, met eigen
   `DB_NAME` en `APP_URL`, en een kopie van `DOCUMENTEN_MAP` op een eigen pad.
3. **Zet vóór je een pagina opent de mail uit:** laat `SMTP_HOST` leeg, en stel géén cron in.
   Het vangnet verstuurt anders wachtende berichten aan echte sollicitanten.
4. Log in, draai de systeemcontrole (kluis moet groen zijn) en open als admin één document in
   een loondossier. Lukt dat, dan kloppen sleutel, zout en bestanden samen.
5. Verwijder daarna database, subdomein en kopieën. Noteer datum en uitkomst voor het bestuur.

## Terugdraaien (rollback)

> **In het kort:** Code terugzetten via Plesk en een pagina openen. Nooit een oude
> database-back-up terugzetten alleen om code terug te draaien.

Blijkt een release kapot op productie:

1. Plesk › Git › kies de vorige commit op `main` (of zet in Git `main` terug met een revert en push).
2. Deploy.
3. Open een pagina. De zelfherstelronde draait opnieuw voor de oudere versie; nieuwe kolommen die
   de nieuwe versie heeft aangemaakt blijven staan, en dat is onschadelijk.
4. Meld het aan de ontwikkelaar, zodat het in de volgende release wordt opgelost.

Zet nooit een oudere database-back-up terug alleen om code terug te draaien: dan verlies je
aanmeldingen en ondertekeningen van na de back-up.

## Logs lezen

> **In het kort:** Het Logboek in het beheer toont alle logbronnen als één tijdlijn. Technische
> details die daar niet in passen staan in het serverlog in Plesk.

| Waar | Wat | Voor wie |
|---|---|---|
| Beheer › Logboek | Eén tijdlijn uit zes bronnen: acties van beheerders (`audit_log`), verstuurde berichten (`mail_log`), fouten (`error_log`), ondertekenbewijs, AVG-opschoning en mislukte logins. Filteren, exporteren (de export wordt zelf gelogd) | module Logboek (standaard alleen admin) |
| Logboek, bron Fouten | Technische fouten met code, referentie, bestand en regel. Het oude scherm Foutenlog verwijst hierheen | idem |
| Plesk › Logs | PHP-fouten en regels die het systeem zelf schrijft: `db_failsafe: …`, `cron <taak>: …`, `rate_limit: …`, `… (na antwoord): …` | systeembeheerder |
| Foutmail | Bij fouten via `p13_fout()` gaat een mail naar `mail.fout_email` (standaard website@paal13.com) | systeembeheerder |

Een foutmelding op het scherm toont een code en een referentie (`P13-S000 · ref 1a2b3c4d`); zoek
die referentie in Logboek › Fouten. De inhoud van berichten komt nooit in het Logboek.

Veelvoorkomende codes: `P13-D001` database niet bereikbaar (config of MariaDB), `P13-M001/M002`
mail (SMTP), `P13-M005` WhatsApp tijdelijk gepauzeerd na herhaalde fouten (valt terug op
e-mail, gaat vanzelf weer aan), `P13-P004` verwijderverzoek van een sollicitant mislukt en
teruggedraaid.

Werk dat ná het antwoord draait (mails, audit, lazy-cron) kan de bezoeker niet meer laten zien
dat het misging; dat vind je alleen in het serverlog, met `(na antwoord)` in de regel.

De foutmail begrenst zichzelf alleen binnen één verzoek. Gaat een veelbezochte pagina stuk, dan
kun je veel mails tegelijk krijgen; kijk dan eerst in Logboek › Fouten welke code het is.

## Datalek of beveiligingsincident

> **In het kort:** Beperk de schade, leg vast wat je ziet, en laat het bestuur binnen 72 uur na
> ontdekking beslissen over een melding bij de Autoriteit Persoonsgegevens. Registreer elk
> datalek, ook als je het niet meldt.

Een datalek is elke inbreuk waarbij persoonsgegevens verloren gaan of bij iemand terechtkomen die
ze niet mag hebben: een gestolen laptop met een export, een mail aan de verkeerde ontvanger, een
gelekte back-up, een beheerder die meer zag dan mocht.

**Wie doet wat**

| Rol | Taak |
|---|---|
| Wie het ontdekt | Direct melden bij de technisch beheerder en het bestuur. Niets wissen |
| Technisch beheerder | Beperken, vastleggen, feiten aanleveren |
| Bestuur (verwerkingsverantwoordelijke) | Beslist over melden, meldt bij de AP, informeert betrokkenen, houdt het datalekregister bij |
| Cloud86 en andere verwerkers | Moeten een lek bij hen zonder onnodige vertraging aan ons melden |

**Stappen**

1. **Tijdstip noteren** van ontdekking. De termijn van 72 uur loopt vanaf dat moment.
2. **Beperken**, wat van toepassing is:
   - account op non-actief in Beheerders en uit de Authentik-groep;
   - API-sleutel vernieuwen (Website & API) en in de plugin zetten;
   - cron-token, SMTP-, AI-, sms- en WhatsApp-sleutels vervangen (zie
     [Geheimen die in de database staan](#geheimen-die-in-de-database-staan));
   - databasewachtwoord en OIDC client secret vernieuwen in Plesk/Authentik en in `config.php`;
   - bij misbruik van het portaal: tabel `portaal_apparaten` leegmaken;
   - site tijdelijk uit (Plesk: domein uitschakelen) als het lek nog doorloopt.
   Vervang `KLUIS_SLEUTEL` niet: dat maakt alle documenten onleesbaar en lost niets op. Is de
   sleutel samen met de database gelekt, overleg dan met de ontwikkelaar.
3. **Vastleggen**: Logboek-export van de periode, Plesk-logs (access en error) downloaden,
   screenshots, een tijdlijn van wat er gebeurde. Doe dit voor de logs roteren.
4. **Beoordelen** met het bestuur: welke gegevens, hoeveel mensen, welk risico. BSN, kopie ID,
   gezondheidsgegevens en gegevens van minderjarigen wegen zwaar.
5. **Melden**: tenzij het lek waarschijnlijk geen risico oplevert, meldt het bestuur binnen 72
   uur na ontdekking bij de Autoriteit Persoonsgegevens (meldloket datalekken op
   autoriteitpersoonsgegevens.nl). Bij een hoog risico worden ook de betrokkenen geïnformeerd.
   Nog niet alles bekend? Meld binnen de termijn en vul later aan.
6. **Registreren**: elk datalek komt in het datalekregister van het bestuur, ook als het niet
   gemeld is, met de reden waarom niet.
7. **Evalueren** en de oorzaak laten oplossen; noteer de maatregel.

NIS2 (in Nederland de Cyberbeveiligingswet) geldt naar verwachting niet voor de brigade als
organisatie. Valt ze er toch onder, dan komt daar een vroege waarschuwing binnen 24 uur en een
melding binnen 72 uur bij het CSIRT bij. De maatregelen in dit runbook (toegangsbeheer, logging,
back-up met hersteltest, incidentprocedure) volgen de zorgplicht als goede praktijk.

## Beveiligingsmaatregelen in het kort

> **In het kort:** Een overzicht van wat het systeem technisch doet, voor de verantwoording naar
> bestuur en toezichthouder (AVG, NIS2-zorgplicht).

- **Toegang:** SSO via Authentik met groepen; rollen en rechten per module; sessiecookie alleen
  via https, HttpOnly, SameSite=Lax, acht uur geldig; sessie-id vernieuwt na inloggen en elk half
  uur. "Blijf ingelogd" op het portaal met selector en gehashte verifier die bij elk gebruik
  wisselt.
- **Brute force en misbruik:** beheerderslogin 5 mislukte pogingen per 15 minuten per e-mail én
  per IP-adres; inlogcode voor sollicitanten maximaal 5 pogingen per code en 10 aanvragen per uur
  per IP; verificatiecode bij ondertekenen 5 per uur per document; aanmeldformulier 5 per uur per
  IP met honeypot, via de API 10 per uur per bezoeker; vragen via het portaal 3 per uur; uploads
  20 per uur per dossier; downloads van loondocumenten 30 per uur per sessie, met watermerk op
  ID- en bankpasfoto's.
- **Headers:** Content-Security-Policy (scripts en verbindingen alleen van de eigen host), HSTS,
  X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, gezet in PHP
  (`security_headers()`), niet in `.htaccess`. Landingspagina's hebben een eigen, strengere CSP met
  `connect-src 'none'`.
- **CSRF:** elk formulier en elke beheer-API met token.
- **API:** sleutel alleen in de header `X-Paal13-Key`, vergeleken in constante tijd; CORS alleen
  voor paal13.com en de ingestelde websites.
- **Uploads:** typecontrole op inhoud, geen PHP-uitvoering in `uploads/`, gevoelige bestanden
  alleen versleuteld in de kluis buiten de webroot, foto's buiten de webroot met willekeurige
  naam en toegangscontrole.
- **Versleuteling:** XChaCha20-Poly1305 of AES-256-GCM per bestand, sleutel per bestand,
  hoofdsleutel uit config plus zout uit de database, gebonden aan het dossier (AAD).
- **Ondertekenen:** bewijslog (IP, browser, tijd, kanaal van de verificatiecode), verzegelde PDF
  met eigen certificaat, PDF eenmalig gemaakt en versleuteld bewaard.
- **Logging:** audit van alle beheeracties, downloads en rechtenwijzigingen; exports worden zelf
  gelogd; berichtinhoud komt nooit in het logboek.
- **Bewaartermijnen:** automatische opschoning per categorie (instelbaar, moet aan staan),
  privacyverklaring die dezelfde termijnen toont. Het verwerkingsregister houdt de ontwikkelaar
  bij in `docs/ai/PROJECT.md` §8 (niet in Git); het bestuur hoort een actuele kopie te hebben.
- **Foutafhandeling:** geen technische details op het scherm, wel een referentie; foutmail naar
  de beheerder.

## Bekende beperkingen

> **In het kort:** Bewuste keuzes en bekende gaten waar je als beheerder mee rekening houdt.

- Het zegelcertificaat is zelf uitgegeven: Acrobat toont "geldigheid onbekend" in plaats van een
  groen vinkje. Het bewijst wél dat de PDF na ondertekening niet is gewijzigd. Een AATL-certificaat
  kost geld en is bewust niet aangeschaft.
- De kluissleutel kan niet worden gewisseld: er is geen functie die bestaande documenten opnieuw
  versleutelt.
- Foto's van sollicitanten zijn afgeschermd, maar niet versleuteld opgeslagen.
- Het cron-token staat in het webadres van de cron en dus in de access-log van de server. Houd die
  logs afgeschermd; vervangen kan alleen via de database.
- De AVG-opschoning heeft geen proefdraaistand in het beheer.
- WhatsApp loopt via WhatsReach (geen officiële Meta-koppeling). Het bestuur kan het per
  meldingstype uitzetten; bij storing valt alles terug op e-mail.
- "Halverwege weggegaan" in de trechter bij Landingspagina's is een aanwijzing, geen telling:
  het signaal komt van een tabblad dat naar de achtergrond gaat, en sommigen komen terug.
- Het formulier bewaart tijdens het invullen wat iemand intypt in `sessionStorage` van de eigen
  browser, zodat de terugknop niet alles wist. Dat verdwijnt zodra het tabblad sluit en bij het
  verzenden; toestemmingsvinkjes worden er bewust niet in bewaard.
- Er is geen automatische test-suite op de server; de harnassen in `docs/ai/harnas/` draaien
  lokaal bij de ontwikkelaar en staan niet in Git.

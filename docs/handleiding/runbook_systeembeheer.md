# Runbook systeembeheer — Sollicitatiesysteem Paal 13

Versie 2.102 · september 2026

Dit document is voor wie de servers beheert: installeren, uitrollen naar productie, configuratie,
back-ups, herstel, toegangsbeheer, incidenten en het lezen van de logs. Het is de enige plek waar
de technische stappen staan. Wat het bestuur in het beheer doet, staat in de handleiding beheer
(`docs/handleiding/handleiding_beheer.md`, in het beheer onder Hulp en handleidingen).

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
   - [Generale repetitie op test (geanonimiseerde productiekopie)](#generale-repetitie-op-test-geanonimiseerde-productiekopie)
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
   - [Regels voor database-wijzigingen](#regels-voor-database-wijzigingen)
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
- [ ] Een **Authentik-account** in de groep `app-sol-admin`. Log één keer in op
  `https://test.sollicitatie.paal13.com/admin/` en op productie.
- [ ] Toegang tot de **Git-repository** (branches `Development` en `main`).
- [ ] Toegang tot de **wachtwoordkluis van het bestuur**. Daar horen per omgeving te staan: de
  `KLUIS_SLEUTEL`, het databasewachtwoord, het SMTP-wachtwoord en het OIDC client secret.
  Ontbreekt de kluissleutel van productie daar, meld dat dezelfde dag: zonder die sleutel is een
  back-up van de documenten waardeloos.
- [ ] Open op beide omgevingen **Beheer › Systeem & Updates**: noteer de versie en controleer of
  alle checks van de [systeemcontrole](#systeemcontrole-health-check) groen zijn, ook Cron
  (alle drie de groepen op tijd).
- [ ] Open **Instellingen › Bewaren & opschonen** op productie en controleer dat
  "Automatisch opschonen" aan staat; staat het uit, dan staat daar een rode waarschuwing (zie
  [Bewaartermijnen en privacy](#bewaartermijnen-en-privacy)).
- [ ] Open **Logboek**, filter op bron Fouten, en kijk wat er de afgelopen week gebeurde.
- [ ] Zorg dat je de foutmails ontvangt: het adres staat bij **Instellingen › Afzender &
  adressen** (`mail.fout_email`, standaard website@paal13.com).
- [ ] Controleer dat er een **uptime-monitor** op `api/status.php` van productie staat en dat
  jij de alarmen krijgt (zie [Uptime-monitor](#uptime-monitor)).
- [ ] Controleer op de **testomgeving** dat bovenaan elk beheerscherm de balk "Testmodus" staat
  (`MAIL_OMLEIDEN` in `config.php`), en op **productie** dat die balk er níét staat.

**Waar staat wat**

| Wat | Waar | In Git? |
|---|---|---|
| Code | Git-repository; test volgt `Development`, productie `main` | ja |
| Configuratie per omgeving | `includes/config.php` op de server | nee, nooit |
| Geheimen van koppelingen (API, AI, WhatsApp, sms, SMTP) | tabel `instellingen` in de database | nee |
| Versleutelde documenten, foto's, zegelcertificaat | `DOCUMENTEN_MAP` op de server, buiten de webroot | nee |
| Overige uploads (logo, landingspagina's, bijlagen) | `uploads/` in de webroot | nee, behalve `uploads/.htaccess` en `uploads/branding/.htaccess` |
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

- [ ] Systeemcontrole op productie: alles groen, Cron met alle drie de groepen op tijd.
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
- [ ] Logboek, bron Audit: pogingen "Inloggen via Authentik geweigerd" van iemand die er nog in
  hoort? Dan klopt de groep in Authentik niet.

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
- **PHP-FPM is een eis, geen voorkeur.** Mails, WhatsApp en het auditlog draaien
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

> **In het kort:** Werkt het op test, dan gaat het naar productie: back-up, mergen, uitrollen,
> controleren. Stappen met "(eenmalig)" horen bij de eerste productie-uitrol sinds augustus 2026
> en kun je daarna overslaan.

**Het uitgangspunt:** test en productie draaien exact dezelfde code. Ze verschillen alleen in
`config.php` (adres, database, sleutels) en in de data. Zorg daarnaast dat de serverinstellingen
gelijk zijn: dezelfde PHP-versie en extensies, PHP-FPM, een cron, en dezelfde instellingen in
Plesk. Bewust anders op test: het oranje label TESTOMGEVING en dat Google test nooit indexeert.

**Vooraf**

1. De nieuwe versie werkt op test. Minimaal deze rooktest: aanmelden via `/aanmelden.php`
   en via de WordPress-plugin, inloggen op het portaal met code en met inloglink, een formulier
   invullen, een fase wijzigen in het beheer, een bericht versturen, en de systeemcontrole
   groen. De uitgebreide testlijst houdt de ontwikkelaar bij in `docs/ai/` (niet in Git).
2. Maak een back-up van de productiedatabase (Plesk › Databases › Exporteren) en van
   `DOCUMENTEN_MAP`. Het systeem maakt vóór een database-update zelf ook nog een back-up, als extra.
3. Merge `Development` naar `main` (GitHub Desktop of een pull request). Push. GitHub zet daarna
   zelf een versielabel (`v<versie>`) op die commit; daar hoef je niets voor te doen. Het helpt
   bij [Terugdraaien](#terugdraaien-rollback).

**Uitrollen**

4. (eenmalig) Vul `includes/config.php` op productie aan met de sleutels die sinds augustus zijn
   bijgekomen (zie [config.php: alle instellingen](#configphp-alle-instellingen)). Doe dit vóór de
   deploy, dan is alles in één keer goed:
   - `KLUIS_SLEUTEL` — genereer een nieuwe: `php -r "echo base64_encode(random_bytes(32));"`.
     **Bewaar een kopie in de wachtwoordkluis van het bestuur.** Kwijt = alle documenten kwijt.
   - `KLUIS_SLEUTEL_VERSIE` = `1`.
   - `DOCUMENTEN_MAP` = een map **buiten** `httpdocs`, bijvoorbeeld
     `/var/www/vhosts/paal13.com/private/sollicitatie-documenten`. Maak de map aan; de
     PHP-gebruiker van de site moet er kunnen schrijven (rechten 700). De automatische back-ups
     komen in `<DOCUMENTEN_MAP>/db-backups`, of in `BACKUP_MAP` als je die apart instelt.
   - Controleer dat de laatste regels `error_reporting(0);` en `ini_set('display_errors', 0);`
     erin staan. De `.htaccess` regelt dit niet onder PHP-FPM.
   - `MAIL_OMLEIDEN` staat op productie **leeg**.
5. Plesk › Git › productie-repository › **Deploy**. Wacht tot de deploy klaar is.
6. (eenmalig) Verwijder op de productieserver de mappen `setup/` en `_backups/` als die er nog
   staan (`_backups/` bevat kopieën van oude configuraties met wachtwoorden).
7. Open één willekeurige pagina, bijvoorbeeld `https://sollicitatie.paal13.com/portaal/`.
   Daarmee draait de zelfherstelronde voor deze versie (eerst een back-up, dan de updates).
8. Log in op het beheer en ga naar **Systeem & Updates**:
   - Klik **Migraties uitvoeren**. Dat forceert de zelfherstelronde nog eens (`db_failsafe`) en
     is veilig om vaker te doen.
   - Klik **SQL-migraties** (opent `/admin/update.php`; vraagt het recht Systeem bewerken) en
     kijk of er losse SQL-migraties uit `sql/` op "pending" staan. Normaal is dat nul; de laatste
     is `migratie_0022_blk_uitbreiding` (vroeger dubbel genummerd als `0002`; die naam bestaat
     niet meer).
   - Kijk bij **Systeemgezondheid** of alle twaalf checks groen zijn. Kluis en zegel horen groen te
     zijn; het zegelcertificaat wordt bij de eerste keer automatisch aangemaakt. Staat er bij een
     check een lijstje met knoppen (SMTP, Authentik, adressen, huisregels), werk dat dan af; op
     Vandaag staat hetzelfde als **Nog in te stellen**. Op productie hoort "E-mail en testmodus"
     groen te zijn: oranje met "Testmodus" betekent dat `MAIL_OMLEIDEN` nog gevuld is.
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
10. Controleer de cron in Plesk (zie [Cron](#cron)) en kijk na een kwartier of de systeemcontrole
    bij Cron alle groepen op tijd meldt.
11. (eenmalig) Authentik: controleer de redirect-URI's en onder **Rollen & rechten** welke groep bij
    elke rol hoort (sinds 2.132.0 standaard `app-sol-*`, en de enige bron voor toegang). Meldt de
    systeemcontrole groepen die "bij geen rol horen", koppel die dan eerst: wie alleen daarin zit,
    komt anders niet meer binnen. Zie [Authentik](#authentik-sso) en
    [authentik_groepen.md](authentik_groepen.md).
12. (eenmalig) **Instellingen › Afzender & adressen**: vul het penningmeester-adres en het
    ledenservice-adres in en laat "loondossier-stukken als bijlage meesturen" uit. Plaats onder
    **Instellingen › Algemeen** de huisregels-pdf.
13. (eenmalig) **Instellingen › Bewaren & opschonen**: controleer eerst de termijnen en zet daarna
    "Automatisch opschonen" aan. De eerste dagelijkse run wist alles wat al over de termijn is,
    onomkeerbaar (zie [Bewaartermijnen en privacy](#bewaartermijnen-en-privacy)). Vervang de
    cronjob-regel in Plesk door de nieuwe met de header `X-Cron-Token` (zie [Cron](#cron)).
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

## Generale repetitie op test (geanonimiseerde productiekopie)

> **In het kort:** optioneel, geen vaste stap. Wil je een versie met grote database-wijzigingen
> eens testen tegen de echte opbouw en aantallen van productie, dan kun je een kopie van de
> productiedatabase op test zetten en die meteen anoniem maken met `tools/anonimiseer_testkopie.php`.
> Het exportbestand gooi je direct weg.

**Waarom.** Op test staan verzonnen sollicitanten in een database die met de code is meegegroeid.
Productie heeft oudere rijen, meer data en andere randgevallen. Een nieuwe versie voert bij het
eerste verzoek zijn schema-updates uit (`db_failsafe()`); de generale repetitie laat zien of dat op
een database met de vorm van productie goed gaat, vóórdat het op productie gebeurt.

**Wat het script doet.** Het vervangt namen, e-mailadressen, telefoonnummers, adressen, vrije
teksten (motivatie, notities, observaties, redenen), berichtinhoud, IP-adressen en browsers door
nepwaarden in dezelfde vorm. Geboortedatums schuiven hooguit een half jaar, zodat de leeftijdslogica
blijft werken. Handtekeningen, versleutelde BSN/IBAN-velden, formulierantwoorden en foto's gaan weg.
De wachtrijen (berichten, AI) en alle inlogcodes, "blijf ingelogd"-tokens en tellers worden geleegd.
In de instellingen worden alle sleutels en wachtwoorden leeggemaakt of vernieuwd, en WhatsApp en
sms gaan uit; de rest van de instellingen blijft, want die wil je juist testen. `db.failsafe_versie`
blijft staan, zodat de nieuwe versie zijn updates echt uitvoert. Alles gebeurt in één transactie:
bij een fout wordt niets gewijzigd. Twee keer draaien kan geen kwaad. Per tabel en kolom staat in
`anon_regels()` bovenin het script wat er gebeurt.

**Wat het script bewust niet doet.** De kluis repareren. `DOCUMENTEN_MAP` en `KLUIS_SLEUTEL` van
productie komen niet mee naar test. Documenten, verzegelde PDF's en foto's uit de kopie zijn op test
dus niet te openen, en de systeemcontrole kan de kluis rood kleuren. Dat is de bedoeling. Ook de
ontwerpen van landingspagina's (in `uploads/`) komen niet mee.

**Vooraf controleren op test** (`includes/config.php` van test):

- `APP_URL` is het test-adres. Het script weigert als het systeem de omgeving niet als test herkent.
- `MAIL_OMLEIDEN` is gevuld met jouw adres. Het script weigert als dat leeg is.
- Test heeft een eigen `KLUIS_SLEUTEL` en een eigen `DOCUMENTEN_MAP`, nooit die van productie.

**Stappen**

1. **Zet de cron van test uit:** Plesk › test-domein › Geplande taken › de taak met
   `cron/run.php` › uitschakelen. Tussen importeren en anonimiseren staan de echte gegevens en de
   sleutels van productie op test. Een cron zou dan bijvoorbeeld de AI-wachtrij met de
   productiesleutel naar Google sturen of met Authentik synchroniseren. Open in die tijd ook geen
   pagina van test.
2. **Exporteer productie:** Plesk › Databases › de productiedatabase › Dump exporteren. Sla de
   dump op in een map op de server buiten `httpdocs` en zet "automatisch downloaden" uit, als test
   en productie in hetzelfde Plesk-abonnement staan. Moet hij toch via je laptop: bewaar hem niet in
   een map die naar de cloud synchroniseert (OneDrive, Dropbox).
3. **Importeer op test:** Plesk › Databases › de **test**database (controleer de naam) › Dump
   importeren, en kies het bestand van stap 2. Tabellen uit de dump vervangen die van test.
4. **Anonimiseer meteen.** Kies één van twee manieren:
   - **Met SSH** (als je abonnement dat heeft): ga naar de map van de testsite en draai
     ```
     /opt/plesk/php/8.3/bin/php tools/anonimiseer_testkopie.php --ja --email=jij@domein.nl --behoud-beheerder=jij@domein.nl
     ```
   - **Zonder SSH, via een geplande taak:** Plesk › test-domein › Geplande taken › Taak toevoegen ›
     **PHP-script uitvoeren**. Script: het volledige pad naar `tools/anonimiseer_testkopie.php` van de
     testsite (de documentroot staat bij Hosting-instellingen). Argumenten:
     `--ja --email=jij@domein.nl --behoud-beheerder=jij@domein.nl`. PHP-versie 8.3. Kies een
     schema dat niet vanzelf binnenkort draait (bijvoorbeeld jaarlijks op 1 januari) en klik na
     opslaan op **Nu uitvoeren**. Plesk toont de uitvoer. Verwijder de taak daarna meteen.

   De opties: `--ja` is verplicht. `--email=basis@domein` maakt van elk nepadres een plus-adres
   op jouw adres (`basis+p123@domein`): zo kun je als elke sollicitant inloggen en komt alle
   test-mail bij jou binnen. Zonder deze optie worden het onbestelbare adressen
   (`test+p123@example.invalid`). `--behoud-beheerder` (kommagescheiden) houdt naam, e-mailadres en
   Authentik-koppeling van die beheerders, zodat je via Authentik op test kunt inloggen. Wachtwoord
   en tweestaps gaan ook bij hen weg; de noodingang met wachtwoord werkt op de kopie dus niet.
5. **Lees de controle-uitvoer.** Staat er `GEWEIGERD` of `FOUT, alles teruggedraaid`, dan is er
   niets veranderd; los de oorzaak op en draai opnieuw. Onderaan staat de controle:
   - "tabel X staat niet in de regellijst": een nieuwe tabel die het script nog niet kent. Kijk wat
     erin staat. Persoonsgegevens? Dan eerst het script aanvullen (`anon_regels()`) en opnieuw draaien.
   - "mogelijk nog persoonsgegevens in tabel.kolom": in die kolom staat iets dat op een
     e-mailadres, telefoonnummer of IBAN lijkt. Kijk het na in phpMyAdmin. Is het van een persoon,
     vul dan het script aan en draai opnieuw. Is het van de vereniging (bijvoorbeeld het nummer van de
     post bij een seizoen), noteer het dan en ga door.
   - Een vrije tekst zonder e-mailadres of telefoonnummer ziet de controle niet. Daarom staat elke
     bekende tekstkolom in de regellijst; bij een nieuwe tabel is de melding dus het signaal.
6. **Gooi het exportbestand weg**, nu meteen: op de server, in je downloads en in de prullenbak.
   Het is een volledige kopie van alle persoonsgegevens (AVG: niet langer bewaren dan nodig). Zet
   geen kopie in e-mail, chat of een gedeelde map.
7. **Zet de cron van test weer aan.** Het script maakt een nieuw `cron.token`. Draait de taak als
   PHP-script (`cron/run.php`), dan hoef je niets te doen; gebruikt hij de HTTP-regel met het token,
   neem dan de nieuwe regel over uit Instellingen › Bewaren & opschonen › Cronjob.
8. **Zet de nieuwe versie op test:** push naar `Development` (of Plesk › Git › Deploy als hij er al
   staat) en open één pagina. De zelfherstelronde van de nieuwe versie draait nu op de kopie. Kijk
   in Systeem & Updates of de migraties en de systeemcontrole goed zijn. Verwacht: "E-mail en
   testmodus" oranje (testmodus) en mogelijk de kluis rood (zie hierboven). Al het andere hoort groen.
9. **Doorloop de testlijst** met de kopie: de rooktest uit de
   [checklist](#uitrollen-naar-productie-checklist), plus de schermen die van aantallen afhangen
   (Vandaag, de lijst sollicitanten, een dossier, selectiedag, trainingsweekend, loonplanning,
   beschikbaarheid en de exports). Inloggen als sollicitant: gebruik het plus-adres van die persoon
   (`basis+p<persoon-id>@domein`); de code komt via `MAIL_OMLEIDEN` bij jou.
10. **Noteer** datum, versie en eventuele waarschuwingen uit stap 5. Daarna kan de uitrol verder
    met de [checklist](#uitrollen-naar-productie-checklist).

De geanonimiseerde kopie mag op test blijven staan tot de volgende generale repetitie. Houd de
toegang tot het beheer van test even beperkt als die van productie: de gegevens zijn
geanonimiseerd, maar aanmelddata, fases en aantallen zijn nog echt.

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
6. **Controleer de `.htaccess`-bestanden in `uploads/`.** De inhoud van `uploads/` staat niet in
   Git, maar `uploads/.htaccess` (blokkeert het uitvoeren van scripts in de uploadmap) en
   `uploads/branding/.htaccess` (logo en favicon: alleen afbeeldingen, eigen CSP met `sandbox`)
   wel. Ze komen dus mee met de deploy; controleer dat ze er staan. Serveert nginx in Plesk
   statische bestanden rechtstreeks, dan negeert het `.htaccess`: zet in dat geval de headers voor
   `uploads/branding/` (`X-Content-Type-Options: nosniff` en
   `Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox`)
   in de nginx-instellingen van het domein, of zet het statisch serveren door nginx uit.

7. Open een pagina; de zelfherstelronde maakt de rest van de tabellen aan.
8. Log in via Authentik met een account in de groep `app-sol-admin`; dat account wordt automatisch
   beheerder met rol admin. Er is geen standaardaccount met wachtwoord meer.
9. Stel de cron in en doorloop de rest van de checklist vanaf stap 8.
10. Werk op Vandaag de lijst **Nog in te stellen** af (SMTP, adressen, huisregels, opschonen)
    en zet een [uptime-monitor](#uptime-monitor) op `api/status.php`. Een testomgeving krijgt
    `MAIL_OMLEIDEN` in `config.php`.

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
| `MAIL_BESTUUR` | Terugval voor het bestuursadres | Het adres in Instellingen › Afzender & adressen gaat voor; de systeemcontrole kijkt in dezelfde volgorde |
| `MAIL_OMLEIDEN` | **Testmodus**: één e-mailadres waar álle e-mail naartoe gaat | Alleen op test en bij een hersteltest (vooral als daar een kopie van echte gegevens staat); op productie altijd leeg. Onderwerp krijgt `[TEST, voor <echte ontvanger>]`; WhatsApp en sms gaan niet de deur uit (de tekst komt als mail op dit adres, het logboek zegt "testmodus"). Bewust een constante en geen instelling: een teruggezette productiedatabase zet hem niet uit. Het beheer toont dan bovenaan een balk |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_ENCRYPT` | Mailserver | Worden overschreven door Instellingen › E-mailserver als daar een host staat. Allebei leeg (of nog `{{SMTP_HOST}}`) = PHP `mail()`, alleen als noodoplossing. Let op: ook dan gaat er gewoon mail de deur uit; tegenhouden doe je met `MAIL_OMLEIDEN` |
| `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`, `OIDC_ISSUER`, `OIDC_SLUG` | Authentik-provider | Leeg (of nog een `{{…}}`-plaatshouder) = SSO uit; alleen wachtwoordlogin voor beheerders. De systeemcontrole waarschuwt |
| `OIDC_REDIRECT_URI` | Callback voor het beheer | `<APP_URL>/admin/auth_callback.php`; het portaal gebruikt vast `<APP_URL>/portaal/auth_callback.php` |
| `KLUIS_SLEUTEL` | Hoofdsleutel van de kluis, base64 van 32 bytes | Uniek per omgeving, kopie in de wachtwoordkluis. Alleen vervangen volgens [Kluissleutel wisselen](#kluissleutel-wisselen) |
| `KLUIS_SLEUTEL_VERSIE` | Nummer van de sleutel | Begint op `1`; gaat alleen omhoog bij het wisselen van de sleutel, samen met `KLUIS_SLEUTEL_OUD` |
| `KLUIS_SLEUTEL_OUD`, `KLUIS_SLEUTEL_OUD_VERSIE` | Vorige sleutel + versie, alleen tijdens het wisselen | Normaal afwezig. Weghalen zodra Systeem › Kluissleutel wisselen 0 over toont; de systeemcontrole herinnert eraan |
| `DOCUMENTEN_MAP` | Map voor versleutelde documenten, foto's en het zegel | Buiten de webroot. Leeg = `uploads/documenten` (afgeschermd, maar de systeemcontrole waarschuwt) |
| `BACKUP_MAP` | Map voor de automatische back-up vóór elke update | Optioneel; standaard `<DOCUMENTEN_MAP>/db-backups`. Moet buiten de webroot staan en schrijfbaar zijn: op productie gaat een update anders niet door (zie [Back-up en herstel](#back-up-en-herstel)) |
| `ZEGEL_MAP`, `ZEGEL_NAAM`, `ZEGEL_ORGANISATIE` | Documentzegel (optioneel) | Standaard `<DOCUMENTEN_MAP>/zegel`, automatisch aangemaakt |
| `ZEGEL_OPENSSL_CNF` | Pad naar `openssl.cnf` (optioneel) | Alleen nodig als openssl zijn configuratie niet zelf vindt; op Cloud86 niet |
| `SESSION_NAME` | Naam van de sessiecookie | Laten staan (`p13sess`). De sessie leeft vast acht uur (`sessie_start()`); daar is geen constante voor. Staat er in een oude `config.php` nog `SESSION_LIFETIME`, dan mag die regel weg |
| `date_default_timezone_set('Europe/Amsterdam')` | Tijdzone | Laten staan; bewaartermijnen en de cron rekenen ermee |
| `error_reporting(0)`, `display_errors=0` | Geen foutmeldingen op het scherm | Verplicht op productie |

Ontbreekt `config.php`, dan toont elke pagina "Systeem is nog niet ingesteld" (HTTP 503).

### Kluissleutel wisselen

**Wanneer:** bij een vermoeden dat de sleutel (of `config.php`) is gelekt, als iemand vertrekt die
bij `config.php` of de wachtwoordkluis kon, en verder periodiek (eens per jaar).

Wisselen pakt per gegeven alleen de datasleutel opnieuw in met de nieuwe hoofdsleutel; de
versleutelde inhoud zelf blijft staan. Het gaat in batches van 200 en is hervatbaar: wat al op de
nieuwe sleutel staat, wordt overgeslagen. Het raakt de BSN/IBAN-velden bij ondertekeningen, alle
bestanden in `DOCUMENTEN_MAP` en de privésleutel van het documentzegel.

1. **Nieuwe sleutel maken:** `php -r "echo base64_encode(random_bytes(32));"`. Zet hem meteen in
   de wachtwoordkluis van het bestuur, naast de oude.
2. **`config.php` aanpassen:** de huidige `KLUIS_SLEUTEL` en `KLUIS_SLEUTEL_VERSIE` gaan naar
   `KLUIS_SLEUTEL_OUD` en `KLUIS_SLEUTEL_OUD_VERSIE`; de nieuwe sleutel wordt `KLUIS_SLEUTEL` met
   versie +1. Vanaf nu wordt alles nieuw met de nieuwe sleutel versleuteld; het oude blijft leesbaar.
3. **Herversleutelen:** Systeem › Kluissleutel wisselen (`/admin/kluis.php`, recht systeem ›
   bewerken). Klik op Herversleutelen, bevestig, en klik op "Volgende batch" tot er 0 over staat.
   Mislukte gegevens staan met bron en id op het scherm; de rest gaat door.
4. **Back-up controleren:** maak een verse back-up van database en documentenmap en open als admin
   één document in het beheer.
5. **Oude sleutel weghalen:** haal `KLUIS_SLEUTEL_OUD` en `KLUIS_SLEUTEL_OUD_VERSIE` uit
   `config.php` en de oude sleutel uit de wachtwoordkluis. De systeemcontrole waarschuwt zolang de
   oude sleutel nog staat terwijl hij niet meer nodig is.

Let op:

- Haal de oude sleutel nooit weg voordat er 0 over staat: wat dan nog op de oude versie staat, is
  onleesbaar (de systeemcontrole kleurt dan rood). Zet de sleutel in dat geval terug als
  `KLUIS_SLEUTEL_OUD` met de juiste versie.
- Oude back-ups blijven op de oude sleutel. Bewaar die sleutel zo lang als die back-ups bestaan,
  gescheiden van de nieuwe.
- De interne bewijs-hash van een ondertekening (`ondertekeningen.bewijs_hash`) is berekend over het
  versleutelde BSN/IBAN-veld. Na een wissel klopt die hash dus niet meer met wat in de database
  staat: het veld is opnieuw ingepakt, de inhoud is gelijk. Dat is geen probleem voor het bewijs.
  Het juridische bewijs is de verzegelde PDF; die verandert niet en het zegel blijft geldig. De
  rotatie legt per ondertekening de sha256 van het oude en het nieuwe veld vast in het logboek
  (`kluis_blob_herwrapt`), zodat je de keten van de oude hash naar het huidige veld kunt herleiden.
  Controleer je later een bewijs-hash, neem die logboekregels dan mee.
- Het tweestaps-geheim van beheerders (`beheerders.tweestaps_geheim`) zit **niet** in de
  herversleuteling. Zolang `KLUIS_SLEUTEL_OUD` staat, blijft het leesbaar. Haal je de oude sleutel
  weg, dan valt de noodingang voor die accounts terug op de code per e-mail (zie
  [Toegangsbeheer](#toegangsbeheer)); laat ze de app daarna opnieuw instellen in Mijn profiel, of
  reset hun tweestaps bij Beheerders.
- Het logboek bevat per batch `kluis_herversleuteld` met aantallen; nooit een sleutel.
- Formulieren op landingspagina's die op het moment van wisselen open staan, kunnen één keer
  worden geweigerd als hun beveiliging op de sleutel terugvalt; opnieuw versturen werkt.

## Geheimen die in de database staan

> **In het kort:** Sleutels van koppelingen staan in de tabel `instellingen` en vul je in het
> beheer in. Het beheer toont ze nooit terug. Wachtwoorden en API-sleutels staan sinds 2.130.0
> versleuteld in de database (`kluis:…`, met `KLUIS_SLEUTEL`); een export bevat ze dus niet
> leesbaar. Behandel exports toch als geheim: ze bevatten persoonsgegevens.

Welke sleutels versleuteld worden: `smtp.pass`, `ai.api_key`, `authentik.api_token`,
`authentik.token`, `sms.http_token`, `sms.easysendsms_key`, `sms.messagebird_key` en
`wa.api_sleutel` (`instellingen_geheime_sleutels()`). Een oude, nog leesbare waarde wordt bij de eerste
keer lezen vanzelf versleuteld. Bij het wisselen van de kluissleutel gaan ze mee (bron "Wachtwoorden en
sleutels van koppelingen" op het kluisscherm). Werkt de kluis niet, dan blijven de koppelingen werken
met een leesbare waarde en meldt de systeemcontrole het.

In het beheer staat bij een geheim alleen **Ingesteld** of **Nog niet ingesteld**. Een leeg veld
laat de waarde staan; met **Huidige waarde verwijderen** maak je hem leeg. Opslaan vraagt
bewerkrecht op Instellingen en neemt alleen de velden van het open tabblad aan; andere sleutels
worden geweigerd en gelogd. De API-sleutel en het cron-token zijn alleen zichtbaar, kopieerbaar en
te vernieuwen voor wie Systeem mag bewerken.

| Sleutel | Waar in het beheer | Wat |
|---|---|---|
| `cron.token` | Instellingen › Bewaren & opschonen (onderaan, "Cronjob") | Token voor `/cron/run.php`; wordt aangemaakt zodra dat tabblad opent en hij ontbreekt |
| `api.sleutel`, `api.origins` | Instellingen › Website & API | Sleutel en toegestane websites voor de WordPress-plugin. `api.origins` bepaalt ook wie het aanmeldformulier in een iframe mag tonen |
| `smtp.*` | Instellingen › E-mailserver | Mailserver en wachtwoord; gaat voor op `SMTP_*` in config |
| `ai.api_key` | Instellingen › AI-analyse | Sleutel voor de AI-hulp (Gemini) |
| `authentik.url`, `authentik.api_token`, `authentik.token` | Instellingen › Authentik | Portaal-SSO en competentie-sync (wie het beheer in mag: Rollen & rechten) |
| `sms.*`, `wa.*` | Instellingen › Kanalen & meldingen | Sleutels van de sms-gateway en WhatsReach |
| `kluis.zout` | (automatisch, niet wijzigen) | Zout van de kluis; samen met `KLUIS_SLEUTEL` nodig om documenten te lezen |

Een sleutel vervangen: API-sleutel met de knop in Website & API (en meteen in de plugin
invullen); AI-, sms- en WhatsApp-sleutels bij de leverancier vernieuwen en in het beheer
plakken. Het cron-token vernieuw je met **Nieuw token maken** onder Instellingen › Bewaren &
opschonen › Cronjob; de oude regel werkt daarna niet meer, dus zet meteen de nieuwe regel in de
Plesk-taak. Doe dat ook als iemand met toegang tot Plesk vertrekt.

## Cron

> **In het kort:** Eén geplande taak in Plesk, **elke minuut**: `php cron/run.php`. Er is sinds
> 2.104.0 geen vangnet meer bij een paginabezoek (lazy cron): staat de taak stil, dan gaan er
> geen berichten, herinneringen of opschoningen meer uit.

Plesk › Geplande taken › **PHP-script uitvoeren**, elke minuut (`* * * * *`), met als script het
volledige pad naar `cron/run.php` (staat in het beheer bij Instellingen › Bewaren & opschonen ›
Cronjob). Vanaf de opdrachtregel is geen token nodig.

Kan de server geen PHP-script plannen, gebruik dan een opdracht (ook elke minuut):

```
curl -s -H "X-Cron-Token: <cron.token>" "https://sollicitatie.paal13.com/cron/run.php" > /dev/null 2>&1
```

Het token gaat dan als header mee, zodat het niet in de access-log belandt (`?token=` werkt nog
voor oude regels). "Nieuw token maken" staat bij de cronjob in Instellingen, net als **Nu
uitvoeren** (draait alle groepen meteen, met bevestiging).

De taken staan in drie groepen (`includes/cron_taken.php`), elk met een eigen interval, lock
(`cron.lock_<groep>`) en laatste run (`cron.laatste_<groep>`). `run.php` zonder argument draait
elke groep die aan de beurt is; `php cron/run.php dag` draait één groep, voor wie liever aparte
taken maakt (een lange dagrun houdt de berichten dan zeker niet op).

| Groep | Interval | Taken |
|---|---|---|
| `snel` | elke minuut | berichtenwachtrij (max 20), AI-wachtrij (max 3) |
| `kwartier` | 15 min | herinneringen en geplande berichten, automatisch uitnodigen, verlopen inlogcodes en rate-limit-tellers, verlopen "blijf ingelogd"-tokens |
| `dag` | 1× per kalenderdag | Authentik-sync, foto's zonder dossier, AVG-opschoning |

De systeemcontrole, Vandaag en het cronblok in Instellingen tonen de stand per groep. Een groep
die langer dan twee keer zijn interval stilligt (minimaal een kwartier) geeft een waarschuwing;
langer dan zes keer (minimaal een half uur, dag: zes dagen) een fout.

### Uptime-monitor

Die meldingen zie je alleen als iemand het beheer opent. Zet daarom een externe uptime-monitor
(UptimeRobot, Uptime Kuma, Better Stack of vergelijkbaar) op productie, elke 5 minuten:

```
GET https://sollicitatie.paal13.com/api/status.php
Header: X-Cron-Token: <cron.token>
```

Kan de monitor geen header meesturen, gebruik dan `?token=<cron.token>` in het adres (dat komt
wel in de access-log). Het is hetzelfde token als voor de cron; vernieuw je het, pas dan ook de
monitor aan.

| Antwoord | Betekenis |
|---|---|
| `200` + `"ok": true` | Taakronde liep de laatste 10 minuten en er hangt niets in de berichtenwachtrij |
| `503` + `"ok": false` | Taakronde langer dan 10 minuten stil of nog nooit gedraaid, of berichten wachten langer dan een uur. `problemen` zegt welke |
| `403` | Token ontbreekt of klopt niet |
| `429` | Meer dan 30 verzoeken per minuut vanaf hetzelfde adres |
| `503` + "niet ingesteld" | Er is nog geen cron-token (open Instellingen › Bewaren & opschonen) |

Het antwoord bevat alleen tijden en tellingen (`cron_laatst`, `cron_groepen`, `wachtrij_wacht`,
`wachtrij_vast`, `wachtrij_fout`, `testmodus`, `versie`), nooit persoonsgegevens. Mislukte
berichten (`wachtrij_fout`, afgelopen 24 uur) geven bewust geen 503: één verkeerd adres is geen
storing. Laat de monitor alarmeren naar ten minste twee mensen.

## Authentik (SSO)

> **In het kort:** Eén OAuth2/OpenID-provider in `auth.paal13.com` met twee redirect-URI's per
> omgeving. De groep in Authentik bepaalt of iemand het beheer in mag en met welke rol.

In Authentik hoort per omgeving één OAuth2/OpenID-provider met:

- Redirect-URI's (allebei):
  `https://sollicitatie.paal13.com/admin/auth_callback.php` en
  `https://sollicitatie.paal13.com/portaal/auth_callback.php` (op test met het testadres).
  Ontbreekt de tweede, dan krijgen sollicitanten die via Authentik inloggen "Redirect URI Error".
- Scopes `openid profile email`, en de groepen in het token (claim `groups`).
- Groepen en rollen: `app-sol-admin` (rol admin, alles), `app-sol-bestuur` (rol bestuur),
  `app-sol-penningmeester` (rol penningmeester: loondossiers, loonplanning en sollicitanten lezen),
  `app-sol-begeleider` (rol begeleider: selectiedag en dekking lezen). Welke groep bij welke rol
  hoort en wat een rol mag, pas je aan in Beheer › Rollen & rechten. Het groepenmodel van de
  vereniging, de testgroepen (`app-soltest-*`) en de afvinklijst voor Authentik staan in
  [authentik_groepen.md](authentik_groepen.md).
- **Die koppeling is de enige bron voor toegang** (sinds 2.132.0; de oude lijst
  `authentik.admin_groepen` wordt niet meer gebruikt). Geen gekoppelde groep = geen toegang via
  SSO, ook voor een bestaand account. De poging komt in het logboek als "Inloggen via Authentik
  geweigerd". Met meerdere gekoppelde groepen tellen de rechten op. Groepsnamen moeten exact kloppen
  (hoofdletters maken niet uit). Stonden rollen nog op de oude standaardnamen (`Sol-Admin`,
  `Bestuur`, `Sol-Penningmeester`, `Sol-Begeleider`), dan zet de update die om naar `app-sol-*`;
  een zelf gekozen groep blijft staan. Groepen die in de oude lijst stonden maar bij geen rol horen,
  meldt de systeemcontrole.
- **Terug na het inloggen** gaat alleen naar een pad binnen `/admin/`; een extern adres in `terug=`
  wordt genegeerd.
- Zet in Authentik **tweestapsverificatie verplicht** voor iedereen in deze groepen. Het
  sollicitatiesysteem zelf vraagt bij een Authentik-inlog geen tweede factor.

Nieuwe beheerders maak je niet in het beheer aan: zet de persoon in Authentik in de juiste groep
en laat hem één keer inloggen. Wachtwoordlogin (`/admin/login.php`) bestaat nog als noodingang
voor accounts waarvoor in het beheer een wachtwoord is gezet. Die noodingang heeft een eigen
tweede stap (zie [Toegangsbeheer](#toegangsbeheer)).

## Toegangsbeheer

> **In het kort:** Toegang loopt via Authentik-groepen: uit alle aan een rol gekoppelde groepen halen trekt de
> SSO-toegang in. Heeft het account ook een wachtwoord, zet het dan daarnaast op non-actief in
> Beheerders. Controleer de toegang elk seizoen en houd de noodingang met wachtwoord tot één
> account. De noodingang vraagt altijd een tweede stap (NIS2): een code uit een authenticator-app
> of, zonder app, een code per e-mail.

**Iemand erbij**

1. Authentik: account aanmaken, tweestapsverificatie aan, in de juiste groep zetten.
2. Laat de persoon inloggen op `/admin/`. Controleer in Beheer › Beheerders dat de rol klopt.

**Iemand eraf** (vertrek uit het bestuur, einde functie)

1. Authentik: uit de groep halen of het account uitschakelen. Daarmee is inloggen via SSO
   meteen voorbij, ook voor het bestaande account.
2. Staat er een wachtwoord op het account (noodingang), zet het account dan in **Beheer ›
   Beheerders** op non-actief; de wachtwoordlogin kijkt niet naar Authentik. Voor een account
   zonder wachtwoord is dit niet nodig, maar het kan geen kwaad.
3. Zijn er sleutels die de persoon kende (API-sleutel, cron-token, SMTP-wachtwoord,
   wachtwoordkluis), vervang die.

**Seizoensreview**

- [ ] Leg Authentik-groepen `Sol-*` en `Bestuur`, Beheer › Beheerders en Beheer › Rollen &
  rechten naast elkaar. Iedereen die actief is heeft een reden.
- [ ] Hoogstens één account met wachtwoordlogin (noodingang), met een lang wachtwoord dat in de
  wachtwoordkluis staat. In Beheer › Beheerders staat bij dat account **Tweestaps aan**; staat er
  **Tweestaps uit**, laat de eigenaar dan een authenticator-app instellen.
- [ ] Wie mag Systeem bewerken? Dat recht (standaard alleen admin) geeft zicht op de API-sleutel
  en het cron-token, en toegang tot de SQL-migraties. Instellingen bewerken (standaard bestuur en
  admin) toont geen geheimen, maar kan ze wel vervangen of wissen.
- [ ] Wie heeft de module Documenten (BSN, IBAN, ID)? Standaard alleen penningmeester en admin.
- [ ] Staat de penningmeester (of iemand met een eigen rol) in Beheerders als Begeleider? Dat is
  een restant van vóór 2.101; zet de rol goed.
- [ ] Sinds 2.102 heeft de penningmeester lees- en bewerkrecht op Loonplanning. Een bestaande
  installatie kreeg dat één keer automatisch (vlag `rechten.toegevoegd.penningmeester.loonplanning`
  in `instellingen`, logboekregel `rol_gewijzigd` "door systeem"). Haalt een admin het weg in
  Rollen & rechten, dan blijft het weg.

**Noodingang met tweestaps (NIS2)**

Een Authentik-inlog krijgt zijn tweede factor van Authentik. De wachtwoordlogin op
`/admin/login.php` heeft daarom een eigen tweede stap (`includes/tweestaps.php`), zodat ook de
noodingang MFA heeft, zoals de zorgplicht van NIS2 vraagt.

- Een juist wachtwoord logt nog niet in. Het zet een tussenstand in de sessie (sessie-id wordt
  vernieuwd) die 5 minuten geldig is. Daarbinnen moet de tweede stap slagen.
- **Met app:** een TOTP-code (RFC 6238, 6 cijfers, 30 seconden, één stap klokverschil, uitgever
  "Paal13 Beheer"). Een gebruikte code werkt geen tweede keer. Het geheim staat als kluis-blob in
  `beheerders.tweestaps_geheim`. Instellen, opnieuw instellen en uitzetten doet de beheerder zelf
  in Mijn profiel; uitzetten en opnieuw instellen vragen wachtwoord plus code.
- **Herstelcodes:** acht per keer, alleen als `password_hash` bewaard (`beheerders.tweestaps_herstel`),
  elk één keer bruikbaar. De beheerder ziet ze alleen direct na het instellen.
- **Zonder app (terugval):** een eenmalige code per e-mail, 10 minuten geldig, als sha256 in
  `otp_tokens` (type `beheer_2fa`), hooguit één per minuut en vijf per uur per account. Dit
  vereist **werkende mail**: ligt de mailserver plat (of zit de mailbox achter dezelfde Authentik),
  dan komt iemand zonder app niet binnen. Zorg dus dat minstens de noodingang een app heeft.
- **Meldingstype:** de mail is het type "Inlogcode beheer (noodingang)" (`beheer_code`), alleen
  e-mail, wordt altijd direct verstuurd en de inhoud wordt nooit gelogd. Het sjabloon is aan te
  passen, maar laat `{{otp_code}}` erin staan.
- **Kapotte of gewisselde `KLUIS_SLEUTEL`:** is het geheim niet te ontsleutelen, dan valt de
  tweede stap terug op de e-mailcode (regel `tweestaps: geheim van beheerder #… niet te
  ontsleutelen` in het serverlog). Een app instellen kan pas weer als de kluis werkt.
- **Blokkade:** foute codes tellen mee in `login_pogingen`, net als een fout wachtwoord (5 per 15
  minuten per e-mail en per IP). Daarbovenop een rate limit van 5 codepogingen per 15 minuten per
  account en 20 per IP.
- **Logboek:** `tweestaps_aangezet`, `tweestaps_uitgezet`, `tweestaps_gereset`,
  `tweestaps_mislukt`, `tweestaps_herstelcode` en `tweestaps_mailcode`.

**Tweestaps resetten** (telefoon kwijt én geen herstelcodes):

1. Controleer de identiteit buiten het systeem om: bel de persoon op een bekend nummer of spreek
   elkaar. Een mail- of chatverzoek alleen is niet genoeg.
2. Beheer › Beheerders › **Tweestaps resetten** bij dat account, bevestigen met **Ja, tweestaps
   resetten**. Alleen de rol admin ziet die knop.
3. App-koppeling en herstelcodes vervallen; de volgende wachtwoordlogin vraagt een e-mailcode.
4. Laat de persoon direct daarna een nieuwe app instellen in Mijn profiel en controleer bij
   Beheerders dat er weer **Tweestaps aan** staat.

Komt het resetverzoek onverwacht, of kent iemand anders het wachtwoord mogelijk: wijzig dan ook het
wachtwoord (Beheerders › **Wachtwoord**) en kijk in het Logboek naar `tweestaps_mislukt` en mislukte
logins voor dat account.

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
  vastgelegd bij de aanmelding en telt voor de limiet van 10 aanmeldingen per uur. De plugin
  gebruikt alleen het echte verbindingsadres (`REMOTE_ADDR`); proxyheaders zoals
  `X-Forwarded-For` of die van Cloudflare negeert hij, want die kan een bezoeker zelf invullen.
  Staat paal13.com wel achter een proxy of CDN, zet dan in `wp-config.php` de adressen van die
  proxy als lijst van IP-adressen of CIDR-blokken, bijvoorbeeld
  `define('P13_VERTROUWDE_PROXY', '203.0.113.10, 173.245.48.0/20');`. Alleen als het
  verbindingsadres in die lijst valt, leest de plugin de proxyheader.
- De map `wordpress-plugin/` in de repo heeft een eigen `.htaccess` (`Require all denied`), zodat
  de broncode op de sollicitatieserver niet via het web op te vragen is.
- Een nieuwe sleutel werkt meteen en de oude niet meer. Genereer hem dus pas als je de
  WordPress-instellingen open hebt, en test direct.

## Bewaartermijnen en privacy

> **In het kort:** De AVG-opschoning doet niets tot "Automatisch opschonen" aan staat. Zet dat
> aan op productie en houd de termijnen gelijk aan wat de privacyverklaring belooft; die leest
> dezelfde instellingen.

**Aanzetten en controleren**

- Instellingen › Bewaren & opschonen › "Automatisch opschonen inschakelen" (`bewaar.actief`).
  Standaard staat dit **uit**, bewust: aanzetten wist bij de eerste dagelijkse run alles wat al
  over de termijn is, en dat is onomkeerbaar. Zolang het uit staat wordt er niets verwijderd of
  geanonimiseerd, en staat er een rode waarschuwing op het tabblad, op Vandaag en als fout in de
  systeemcontrole ("Automatisch opschonen staat uit").
- **Loondocumenten** (loonheffing en kopie ID) worden nooit automatisch gewist: de bewaarplicht
  loopt tot 5 jaar na het einde van het jaar waarin het dienstverband eindigde, en dat weet het
  systeem niet. De penningmeester vernietigt ze op Loondossiers via de kaart "Te beoordelen voor
  vernietiging" (`documenten_te_vernietigen()`, `documenten_loondossier_vernietigen()`); in het
  logboek staat dan `loondossier_vernietigd`. Een oud-strandwacht wordt pas geanonimiseerd als die
  stukken weg zijn.
- De opschoning draait één keer per dag via de echte cron (`includes/opschonen.php`). Wat er is
  opgeschoond staat in het Opschoon-logboek onderaan hetzelfde tabblad en in Logboek, bron AVG-opschoning.
- Er is geen proefdraaistand in het beheer. Wil je een nieuwe termijn eerst zien werken, zet
  hem dan eerst op de testomgeving.

**Standaardtermijnen**

| Wat | Termijn | Instelling |
|---|---|---|
| Afgewezen of afgehaakte sollicitanten (anonimiseren) | 4 weken na het besluit | `bewaar.afgewezen_weken` |
| Seintje bij de volgende werving | max 12 maanden | `bewaar.seintje_mnd` |
| Oud-strandwachten | 2 jaar na het laatste seizoen | `bewaar.aangenomen_jaar` |
| Gezondheidsgegevens | einde seizoen, direct bij afwijzing | `bewaar.gezondheid_mnd` (0) |
| AI-invoertekst | 3 maanden | `bewaar.ai_invoer_mnd` |
| Niet-voltooide aanmeldingen | 3 maanden | `bewaar.onvoltooid_mnd` |
| Berichtenlog / inhoud van berichten | 12 / 3 maanden | `bewaar.maillog_mnd`, `bewaar.maillog_body_mnd` |
| Losse bijlagen | 12 maanden | `bewaar.bijlagen_mnd` |
| Auditlog (acties van beheerders) | 12 maanden | `bewaar.audit_maanden` |
| Foutenlog (`error_log`) | 90 dagen | `bewaar.foutenlog_dagen` |
| Berichtenwachtrij (`mail_queue`, afgehandeld of mislukt) | 30 dagen | vast |
| Loonheffing en kopie ID | niet automatisch; de penningmeester vernietigt na 5 jaar na het einde van het jaar waarin het dienstverband eindigde | `bewaar.loondocumenten_jaar` (5, nooit lager) bepaalt alleen wanneer iemand op de lijst "Te beoordelen voor vernietiging" komt |
| Kopie bankpas | 30 dagen na verwerking | `bewaar.bankpas_na_verwerking_dagen` |
| Mislukte inlogpogingen | 24 uur | vast |
| Gebruikte en verlopen inlogcodes (`otp_tokens`), rate-limit-tellers (`api_rate`) | 1 uur na verlopen, tellers na 24 uur | vast; ook als opschonen uit staat |
| "Blijf ingelogd"-tokens | 60 dagen | vast |

Alles in deze tabel staat nu als veld op het tabblad Bewaren & opschonen, behalve wat "vast" is en
`bewaar.loondocumenten_jaar`. Daar staan ook de schakelaars `documenten.download_melding` (mail
aan het bestuur bij elke download van een loondocument, standaard aan) en `paginas.tracking`
(bezoekersstatistiek van landingspagina's en aanmeldformulier, standaard aan). Een AVG-verzoek
(`includes/avg_wissen.php`) neemt ook de rijen van die persoon in `mail_queue` mee, net als
selectiedag, trainingsweekend, beschikbaarheid, `portaal_apparaten` en het IP-adres in het
bewijsspoor van ondertekeningen. Wat er gewist wordt (`avg_sollicitatie_leegmaken()`) en wat de
sollicitant bij inzage downloadt (`avg_inzage()`) staat op één plek; een nieuwe tabel met
persoonsgegevens hoort in allebei en in het verwerkingsregister.

**Privacyverklaring**

- De tekst staat in `includes/privacy_verklaring.php` en is openbaar op `/privacy.php`. De
  termijnen in de tabel komen uit de instellingen hierboven; de alinea's over AI, WhatsApp en
  sms verschijnen alleen als die dienst aanstaat. Een termijn wijzigen = de verklaring verandert
  mee.
- Verandert de tekst zelf (in code), dan hoort `PRIVACY_VERSIE` in `includes/functions.php`
  omhoog (nu `2026-09c`). Bij elke aanmelding wordt vastgelegd welke versie iemand las.
- Schakel je een nieuwe dienst in (bijvoorbeeld sms of een andere mailprovider), controleer dan
  of die als verwerker in de verklaring staat en of er een verwerkersovereenkomst is.
- De verklaring belooft dat back-ups na verloop van tijd automatisch verdwijnen. Houd de
  back-uptermijn daarom kort (zie [Back-up en herstel](#back-up-en-herstel)).

## Systeemcontrole (health check)

> **In het kort:** Beheer › Systeem & Updates › Systeemgezondheid voert twaalf checks uit en kan
> een deel zelf herstellen. Ligt een van de drie cron-groepen stil, dan waarschuwt de check Cron
> daarvoor. Wat bij de eerste keer instellen nog openstaat, staat op Vandaag als **Nog in te
> stellen**, elk punt met een knop naar het juiste tabblad.

Knop **Controleren & repareren** draait de checks opnieuw en herstelt waar dat kan (vooral de
database). Bovenaan het scherm staan daarnaast **Migraties uitvoeren** (de zelfherstelronde) en
**SQL-migraties** (`/admin/update.php`, losse migraties uit `sql/`; vraagt het recht Systeem
bewerken).

| Check | Groen | Waarschuwing / fout | Wat te doen |
|---|---|---|---|
| Database | Alle tabellen en kolommen aanwezig | Ontbrekend onderdeel | Controleren & repareren; anders Migraties uitvoeren en het serverlog lezen |
| Cron | Elke groep (snel, kwartier, dag) draaide op tijd | Waarschuwing als een groep langer dan 2× zijn interval stilligt (min. 15 min), fout na 6× (min. 30 min) of als hij nog nooit draaide | Plesk-taak `php cron/run.php` (elke minuut); bij curl de header `X-Cron-Token` en het token |
| Snel antwoorden | PHP draait als FPM (of LiteSpeed) | Waarschuwing: elke bezoeker wacht op de verzending van berichten | PHP-handler in Plesk op een FPM-variant zetten |
| Mailwachtrij | Niets ouder dan 1 uur in "wacht", niets definitief mislukt in 24 uur | Berichten blijven hangen, of berichten staan na drie pogingen op "fout" (de ontvanger heeft ze nooit gehad) | Cron en E-mailserver-instellingen; Logboek › Berichten voor de reden |
| Opschoning | AVG-opschoning < 3 dagen geleden | Fout als "Automatisch opschonen" uit staat; waarschuwing als hij nog nooit of langer geleden draaide | Opschonen aanzetten; draait de echte cron? |
| Mappen | `uploads/` schrijfbaar | Niet schrijfbaar | Maprechten in Plesk |
| Back-up vóór updates | Veilige back-upmap buiten de webroot; laatste automatische back-up zichtbaar | Waarschuwing: geen back-upmap, of de laatste back-up vóór een update mislukte (de update zelf ging wel door) | `DOCUMENTEN_MAP` of `BACKUP_MAP` in config (buiten `httpdocs`, schrijfbaar) |
| E-mail en testmodus | SMTP ingesteld, testmodus uit | Geen SMTP (mail gaat via PHP `mail()`, komt vaak niet aan); of `MAIL_OMLEIDEN` staat aan | Instellingen › E-mailserver; op productie `MAIL_OMLEIDEN` leegmaken |
| Inloggen (Authentik) | `OIDC_CLIENT_ID` en `OIDC_ISSUER` ingevuld | Leeg of nog een `{{…}}`-plaatshouder: alleen de wachtwoord-noodingang werkt | `config.php`, zie [Authentik (SSO)](#authentik-sso) |
| Configuratie | Cron-token, bestuursadres, adres penningmeester en ledenservice, huisregels-PDF | Iets ontbreekt. Zonder adres voor penningmeester of ledenservice gaan loondocumenten en lidmaatschapsinschrijvingen (met IBAN) naar het bestuursadres. Geen open seizoen staat er alleen ter informatie bij | Knop bij het punt; adressen in Instellingen › Afzender & adressen, huisregels in Instellingen › Algemeen |
| Kluis | Werkt, documentenmap buiten webroot | Sleutel ontbreekt/ongeldig, map niet schrijfbaar of in webroot; gegevens nog op een oude sleutel, of `KLUIS_SLEUTEL_OUD` staat er nog terwijl niets hem meer nodig heeft | `KLUIS_SLEUTEL`, `DOCUMENTEN_MAP` in config; rechten van de map; [Kluissleutel wisselen](#kluissleutel-wisselen) |
| Zegel | Certificaat geldig > 60 dagen | Ontbreekt, verloopt of verlopen | Wordt automatisch aangemaakt (10 jaar geldig); bij verlopen: `cert.pem` en `key.enc` in de zegelmap weghalen en een pagina openen |

Het bestuursadres telt als ingesteld zodra het niet leeg en geen plaatshouder is; de standaard
`bestuur@paal13.com` geeft bewust geen waarschuwing, want dat is het echte adres van de brigade.

**Nog in te stellen** (Vandaag, alleen voor wie Systeem mag bewerken) verzamelt de punten uit
Back-up, Configuratie, E-mail, Inloggen en Opschoning: rood eerst, dan oranje, dan informatief. Een
nieuwe installatie werk je van boven naar beneden af tot de lijst leeg is. De punten komen uit
`health_nog_in_te_stellen()` in `includes/health.php`; een nieuw punt is één `health_punt()` in
de check waar het bij hoort.

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
4. **`uploads/`**: logo, ontwerpen van landingspagina's en bijlagen van sjablonen. De
   `.htaccess`-bestanden in `uploads/` en `uploads/branding/` staan in Git.

De code zelf staat in Git en hoeft niet in de back-up.

**Automatische back-up vóór elke update (sinds 2.128).** Komt er een nieuwe versie binnen, dan
maakt het systeem bij het eerste verzoek eerst een volledige dump van de database, en pas daarna
past het de database aan. Lukt de back-up niet (bijvoorbeeld geen schrijfbare map), dan gaat de
update gewoon door en meldt de check "Back-up vóór updates" het in het oranje.

- Waar: `BACKUP_MAP` uit `config.php`, anders `<DOCUMENTEN_MAP>/db-backups`. Nooit binnen de
  webroot; staat de map daar toch, dan weigert het systeem hem.
- Naam: `db-<datum>-<tijd>-v<oud>-naar-v<nieuw>.sql.gz`, rechten 600.
- Bewaard: altijd de laatste 3; de rest wordt na 30 dagen opgeruimd.
- Dit vervangt de dagelijkse Plesk-back-up niet: die blijft nodig, ook voor `DOCUMENTEN_MAP`.

**Een automatische back-up terugzetten** (alleen als een update echt data heeft beschadigd):
zet eerst de code terug op de vorige versie (zie [Terugdraaien](#terugdraaien-rollback)), download
het bestand via Plesk › Bestandsbeheer, en importeer het via Plesk › Databases › Importeren (het
`.sql.gz`-bestand kan direct). Het bestand begint per tabel met `DROP TABLE IF EXISTS`: de
database staat daarna precies zoals vlak vóór de update. Alles wat daarna is ingevoerd
(aanmeldingen, ondertekeningen) ben je dan kwijt; doe dit dus alleen als de schade groter is.

**Bewaartermijn van back-ups:** houd een rollend venster van ongeveer 30 dagen aan en laat oudere
back-ups automatisch vervallen (Plesk › Back-up › Instellingen). Bewaar back-ups niet jaren: dan
blijven gegevens van afgewezen sollicitanten veel langer bestaan dan de privacyverklaring
belooft. De wettelijke bewaarplicht van loondocumenten wordt gedekt door het systeem zelf (ze
blijven staan tot de penningmeester ze vernietigt), niet door oude back-ups.

**Herstel**

Code uitrollen via Plesk, `config.php` terugzetten, database importeren, `DOCUMENTEN_MAP` en
`uploads/` terugzetten, pagina openen, systeemcontrole draaien. **Zonder de originele
`KLUIS_SLEUTEL` én het zout uit de database zijn de documenten definitief onleesbaar.** Dat is
bewust: een gelekte back-up zonder sleutel is waardeloos voor een aanvaller.

**Hersteltest, elk seizoen**

1. Maak in Plesk een tijdelijke database en zet de productie-back-up daarin terug.
2. Koppel die aan een tijdelijk subdomein met een kopie van de productie-`config.php`, met eigen
   `DB_NAME` en `APP_URL`, en een kopie van `DOCUMENTEN_MAP` op een eigen pad.
3. **Zet vóór je een pagina opent de testmodus aan:** vul in de gekopieerde `config.php`
   `MAIL_OMLEIDEN` met je eigen adres, en stel géén cron in. `SMTP_HOST` leeglaten helpt níét:
   de SMTP-instelling uit de teruggezette database gaat voor, en zonder SMTP valt het systeem
   terug op PHP `mail()` — dan gaan wachtende berichten gewoon naar echte sollicitanten. Met
   `MAIL_OMLEIDEN` gaat alle e-mail naar jou en worden WhatsApp en sms niet verstuurd. Controleer
   bij het openen dat bovenaan het beheer de balk "Testmodus" staat voordat je iets anders doet.
4. Log in, draai de systeemcontrole (kluis moet groen zijn) en open als admin één document in
   een loondossier. Lukt dat, dan kloppen sleutel, zout en bestanden samen.
5. Verwijder daarna database, subdomein en kopieën. Noteer datum en uitkomst voor het bestuur.

## Terugdraaien (rollback)

> **In het kort:** Code terugzetten via Plesk en een pagina openen. Nooit een oude
> database-back-up terugzetten alleen om code terug te draaien.

Elke productie-uitrol heeft een **versielabel** in Git (`v2.128.0` enz.), dat GitHub automatisch
zet bij de merge naar `main` (workflow `.github/workflows/versielabel.yml`). Een label blijft
altijd op dezelfde commit staan; zo weet je precies waar je naar terug kunt.

Blijkt een release kapot op productie:

1. Zoek op GitHub bij **Tags** het label van de vorige, goede versie (bijvoorbeeld `v2.127.1`).
2. Zet `main` terug op die versie. Het eenvoudigst in GitHub Desktop: kies de branch `main`, open
   History, klik met rechts op de merge-commit van de kapotte versie en kies **Revert changes in
   commit**; push. (Wie de opdrachtregel gebruikt: `git revert -m 1 <merge-commit>` en push.)
   Je maakt zo een nieuwe commit die de wijzigingen ongedaan maakt; de geschiedenis blijft heel.
3. Plesk › Git › productie-repository › **Deploy**.
4. Open een pagina. De zelfherstelronde draait opnieuw voor de oudere versie (met eerst weer een
   automatische back-up); nieuwe kolommen die de nieuwe versie heeft aangemaakt blijven staan, en
   dat is onschadelijk: de regels voor database-wijzigingen zorgen dat een oudere versie ermee kan
   werken.
5. Meld het aan de ontwikkelaar, zodat het in de volgende release wordt opgelost.

Zet nooit een oudere database-back-up terug alleen om code terug te draaien: dan verlies je
aanmeldingen en ondertekeningen van na de back-up. De database terugzetten is alleen voor het
geval dat een update echt data heeft beschadigd (zie [Back-up en herstel](#back-up-en-herstel)).

## Regels voor database-wijzigingen

> **In het kort:** Een release voegt alleen toe. Weghalen gebeurt in twee releases, en een
> controle bewaakt dat vóór elke merge naar `main`.

Zodat een update nooit data kan kwijtmaken, en terugdraaien van code altijd veilig is:

1. **Alleen toevoegen:** nieuwe tabellen, kolommen, indexen en ENUM-waarden (achteraan). Een oudere
   versie van de code negeert wat hij niet kent.
2. **Weghalen of verkleinen in twee releases:** een kolom of tabel weghalen, een kolom inkorten,
   een ENUM-waarde schrappen, een type versmallen of iets hernoemen. Release 1: de code gebruikt
   het niet meer. Release 2 (minstens één uitrol later): het wordt opgeruimd — met de automatische
   back-up van die release als vangnet.
3. **Een ENUM omzetten naar VARCHAR mag** (alle waarden blijven), en een oude index weghalen nádat
   de vervanger bestaat.
4. **Controle vóór elke merge naar `main`:** `php docs/ai/harnas/migratie_check.php` zoekt in de
   code alle plekken die iets aan het schema weghalen of wijzigen en vergelijkt die met een lijst
   van bekende, beoordeelde plekken (met reden). Een nieuwe plek geeft een fout totdat iemand hem
   bewust beoordeelt.
5. **Testen op test:** elke release draait eerst op test. Wie een grote database-wijziging extra
   wil controleren, kan dat optioneel doen tegen een geanonimiseerde kopie van productie (zie
   [Generale repetitie op test](#generale-repetitie-op-test-geanonimiseerde-productiekopie)).

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

Werk dat ná het antwoord draait (mails, audit) kan de bezoeker niet meer laten zien
dat het misging; dat vind je alleen in het serverlog, met `(na antwoord)` in de regel.

De foutmail gaat hooguit één keer per foutcode per vijf minuten uit, ook over verzoeken heen (de
logregel krijgt `mail_verstuurd = 1`; werkt de database niet, dan houdt een bestand in de
tijdelijke map het bij). Krijg je een foutmail, kijk dan in Logboek › Fouten hoe vaak dezelfde code
voorkomt.

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
   Is `KLUIS_SLEUTEL` (of `config.php`) mogelijk gelekt, wissel hem dan volgens
   [Kluissleutel wisselen](#kluissleutel-wisselen). Vervang hem nooit zonder die procedure: dan
   worden alle documenten onleesbaar. Wisselen beschermt niet wat al buitgemaakt is als de
   database of documenten zelf ook gelekt zijn; overleg dan met de ontwikkelaar.
3. **Vastleggen**: Logboek-export van de periode, Plesk-logs (access en error) downloaden,
   screenshots, een tijdlijn van wat er gebeurde. Doe dit voor de logs roteren.
4. **Beoordelen** met het bestuur: welke gegevens, hoeveel mensen, welk risico. BSN, kopie ID,
   gezondheidsgegevens en gegevens van minderjarigen wegen zwaar. Het verwerkingsregister
   (`docs/handleiding/verwerkingsregister.md`, in het beheer onder Hulp en handleidingen) is de
   bron voor "welke gegevens": zoek het
   getroffen onderdeel (tabel, scherm of verwerker) op en neem de categorieën persoonsgegevens,
   betrokkenen, ontvangers en bewaartermijn daaruit over in de beoordeling en de melding.
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

**Kwetsbaarheid gemeld?** Het systeem publiceert `/.well-known/security.txt` (RFC 9116) met het adres
voor foutmeldingen (Instellingen › Mail). Een melding van buiten behandel je als incident: bevestig de
ontvangst, zoek het uit met de ontwikkelaar, los het op, en laat de melder weten dat het klaar is.
Test nooit met echte persoonsgegevens.

### Wat buiten de code geregeld moet worden (NIS2-zorgplicht als goede praktijk)

| Maatregel | Wie | Wanneer |
|---|---|---|
| Plesk › Hosting › **Permanente 301 van http naar https** aan (test én productie) | systeembeheer | eenmalig, bij inrichten |
| Authentik: **MFA verplicht** in de login-flow voor de groepen die het beheer in mogen | Authentik-beheer | eenmalig; jaarlijks nalopen |
| Verwerkersovereenkomsten met Cloud86, WhatsReach, sms-dienst en Google (Gemini) afgesloten en bewaard | bestuur | vóór productie; bij elke nieuwe leverancier |
| TCPDF en FPDI (`includes/lib/`) naast de laatste versie leggen; beveiligingsupdates meenemen | ontwikkelaar | elk seizoen vóór de werving |
| Toegang nalopen: wie heeft een beheerdersaccount, welke Authentik-groepen, wie heeft Plesk | bestuur + systeembeheer | elk seizoen (zie [Toegangsbeheer](#toegangsbeheer)) |
| Hersteltest van de back-up | systeembeheer | elk seizoen (zie [Back-up en herstel](#back-up-en-herstel)) |
| Korte uitleg "veilig werken" aan nieuwe beheerders (zie handleiding beheer) | bestuur | bij elke nieuwe beheerder |

## Beveiligingsmaatregelen in het kort

> **In het kort:** Een overzicht van wat het systeem technisch doet, voor de verantwoording naar
> bestuur en toezichthouder (AVG, NIS2-zorgplicht).

- **Toegang:** SSO via Authentik met groepen; rollen en rechten per module; sessiecookie alleen
  via https, HttpOnly, SameSite=Lax, acht uur geldig; sessie-id vernieuwt na inloggen en elk half
  uur. "Blijf ingelogd" op het portaal met selector en gehashte verifier die bij elk gebruik
  wisselt. De wachtwoordlogin van het beheer (noodingang) vraagt een tweede stap: TOTP uit een
  authenticator-app, een herstelcode of een e-mailcode (zie [Toegangsbeheer](#toegangsbeheer)).
- **Brute force en misbruik:** beheerderslogin 5 mislukte pogingen per 15 minuten per e-mail én
  per IP-adres, foute tweestapscodes tellen mee, plus 5 codepogingen per 15 minuten per account en
  20 per IP; inlogcode voor sollicitanten maximaal 5 pogingen per code en 10 aanvragen per uur
  per IP; verificatiecode bij ondertekenen 5 per uur per document; aanmeldformulier 5 per uur per
  IP met honeypot, via de API 10 per uur per bezoeker; vragen via het portaal 3 per uur; uploads
  20 per uur per dossier; downloads van loondocumenten 30 per uur per sessie, met watermerk op
  ID- en bankpasfoto's.
- **Headers:** Content-Security-Policy (scripts en verbindingen alleen van de eigen host), HSTS,
  X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, gezet in PHP
  (`security_headers()`), niet in `.htaccess`. Sinds 2.130.0 staat de CSP **geen inline JavaScript**
  meer toe: `script-src 'self' 'nonce-…'`, met een nieuwe nonce per verzoek (`csp_nonce()`).
  Knoppen gebruiken `data-klik="functie(…)"` in plaats van `onclick` (`assets/js/klik.js`); een
  inline `<script>` heeft `nonce="<?= h(csp_nonce()) ?>"` nodig, anders draait hij niet. Zo kan
  geïnjecteerde HTML geen script uitvoeren. HSTS staat ook op de API en de landingspagina's.
- **Sessie beheer:** na twee uur zonder activiteit uitgelogd (`BEHEER_INACTIEF_SECONDEN`), ook al
  leeft de cookie acht uur.
- **Direct opvragen:** partials (`admin/_*.php`) en de mappen `admin/dossier`, `admin/tw`, `admin/lp`
  zijn via `.htaccess` afgeschermd; ze draaien alleen als include.
- **OIDC:** state met `hash_equals`, PKCE (S256) naar Authentik, TLS-controle bij elke uitgaande
  verbinding. De Gemini-sleutel gaat als header mee, niet in de URL.
- **Wachtwoorden:** `password_hash` met automatische rehash bij inloggen; mislukte pogingen staan
  12 maanden in de audit (de tellers voor de blokkade 24 uur). Stijlen en lettertypen komen alleen van de eigen
  host: `style-src 'self' 'unsafe-inline'` en `font-src 'self' data:`, zonder externe domeinen.
- **Lettertype:** Instrument Sans staat zelf gehost in `assets/fonts/` (woff2, met de licentie
  `OFL.txt`, SIL Open Font License). Er gaat geen verzoek en dus geen IP-adres meer naar Google
  Fonts. Voeg geen externe lettertypen of stylesheets toe: de CSP blokkeert ze, en het zou een
  nieuwe doorgifte zijn die in het verwerkingsregister en de privacyverklaring moet. Landingspagina's hebben een eigen, strengere CSP met
  `connect-src 'none'`. Logo en favicon in `uploads/branding/` krijgen via hun eigen `.htaccess`
  een CSP met `sandbox`.
- **CSRF:** elk formulier en elke beheer-API met token.
- **API:** sleutel alleen in de header `X-Paal13-Key`, vergeleken in constante tijd; CORS alleen
  voor paal13.com en de ingestelde websites.
- **Toegang na inloggen:** terugsturen alleen naar een pad binnen `/admin/` (geen open redirect);
  SSO weigert wie in geen aan een rol gekoppelde groep meer zit.
- **Instellingen:** opslaan vraagt bewerkrecht en neemt alleen de velden van het open tabblad
  aan; geheimen gaan nooit terug naar het scherm.
- **Uploads:** typecontrole op inhoud, geen SVG als logo of favicon, geen PHP-uitvoering in `uploads/`, gevoelige bestanden
  alleen versleuteld in de kluis buiten de webroot, foto's buiten de webroot met willekeurige
  naam en toegangscontrole.
- **Versleuteling:** XChaCha20-Poly1305 of AES-256-GCM per bestand, sleutel per bestand,
  hoofdsleutel uit config plus zout uit de database, gebonden aan het dossier (AAD).
- **Ondertekenen:** bewijslog (IP, browser, tijd, kanaal van de verificatiecode), verzegelde PDF
  met eigen certificaat, PDF eenmalig gemaakt en versleuteld bewaard.
- **Logging:** audit van alle beheeracties, downloads en rechtenwijzigingen; exports worden zelf
  gelogd; berichtinhoud komt nooit in het logboek.
- **Bewaartermijnen:** automatische opschoning per categorie (instelbaar, moet aan staan; rode
  waarschuwing zolang het uit staat), handmatige vernietiging van loondocumenten na de
  bewaarplicht, privacyverklaring die dezelfde termijnen toont.
- **Verwerkingsregister:** het register van verwerkingsactiviteiten (AVG art. 30) staat in Git als
  `docs/handleiding/verwerkingsregister.md` en in het beheer onder Hulp en handleidingen.
  Werk het bij bij elke nieuwe tabel, verwerker of termijn.
- **Foutafhandeling:** geen technische details op het scherm, wel een referentie; foutmail naar
  de beheerder.

## Bekende beperkingen

> **In het kort:** Bewuste keuzes en bekende gaten waar je als beheerder mee rekening houdt.

- Het zegelcertificaat is zelf uitgegeven: Acrobat toont "geldigheid onbekend" in plaats van een
  groen vinkje. Het bewijst wél dat de PDF na ondertekening niet is gewijzigd. Een AATL-certificaat
  kost geld en is bewust niet aangeschaft.
- Foto's van sollicitanten zijn afgeschermd, maar niet versleuteld opgeslagen.
- Een oude cronjob-regel met `?token=` in het adres werkt nog, en zet het token dan in de
  access-log van de server. Vervang hem door de regel met de header `X-Cron-Token`, en maak een
  nieuw token als het oude in logs heeft gestaan.
- Het systeem weet niet wanneer een dienstverband eindigde. De lijst "Te beoordelen voor
  vernietiging" rekent vanaf het laatste seizoen; de penningmeester controleert in de
  loonadministratie voordat er iets wordt vernietigd.
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

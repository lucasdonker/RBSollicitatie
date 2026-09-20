# Handleiding beheer — Sollicitatiesysteem Paal 13

Versie 2.94.0 · september 2026

Het sollicitatiesysteem regelt de hele werving van strandwachten voor Reddingsbrigade Paal 13:
van de aanmelding op de website, via de selectiedag en het trainingsweekend, tot de aangenomen
strandwacht met een compleet loondossier. Sollicitanten volgen hun eigen voortgang in het
portaal; het bestuur overziet de hele lichting in het beheer. Deze handleiding beschrijft het
beheer (het gedeelte onder `/admin/`), scherm voor scherm, in de volgorde van het menu. Waar het
systeem iets automatisch doet (een mail sturen, taken klaarzetten, gegevens opruimen), staat dat
er expliciet bij.

Je vindt deze handleiding terug onder Beheer › Instellingen › Handleidingen, samen met de uitleg
voor de penningmeester, voor sollicitanten en voor het technisch beheer. Het vraagteken rechtsboven
op elk scherm geeft de korte uitleg van dát scherm, met een link naar het bijbehorende hoofdstuk.

## Inhoud

1. [Inloggen en rollen](#inloggen-en-rollen)
2. [Dashboard](#dashboard)
3. [Seizoen opzetten](#seizoen-opzetten)
4. [Landingspagina's](#landingspaginas)
5. [Sollicitanten](#sollicitanten)
6. [De fases en wat er automatisch gebeurt](#de-fases-en-wat-er-automatisch-gebeurt)
7. [Selectiedag](#selectiedag)
8. [Ondertekeningen](#ondertekeningen)
9. [Beschikbaarheid, dekking en loonplanning](#beschikbaarheid-dekking-en-loonplanning)
10. [Berichten sturen](#berichten-sturen)
11. [Sjablonen](#sjablonen)
12. [Geplande berichten en herinneringen](#geplande-berichten-en-herinneringen)
13. [Instellingen](#instellingen)
14. [Loondossiers](#loondossiers)
15. [Logboek](#logboek)
16. [Beheerders en rechten](#beheerders-en-rechten)
17. [Privacy en AVG-verzoeken](#privacy-en-avg-verzoeken)
18. [Systeem en foutenlog](#systeem-en-foutenlog)
19. [Veelgestelde vragen](#veelgestelde-vragen)

---

## Inloggen en rollen

**Waar:** het inlogscherm van het beheer (`/admin/`), daarna het menu links.  
**Wie:** iedereen met een beheerdersaccount.

### Inloggen via Authentik

Het beheer gebruikt de centrale login van de brigade (Authentik). Op het inlogscherm "Beheerders
login" staat de knop **Inloggen via Authentik (SSO)**. Klik daarop, log in bij Authentik en je
komt terug in het beheer.

Welke rol je krijgt, hangt af van de groep waar je in Authentik in zit. Standaard:

| Authentik-groep | Rol in het beheer |
|---|---|
| Sol-Admin | Admin |
| Bestuur | Bestuur |
| Sol-Penningmeester | Penningmeester |
| Sol-Begeleider | Begeleider |

Zit je in meerdere groepen, dan wint de hoogste rol. Zit je in geen van deze groepen en heb je
nog nooit een account gehad, dan krijg je de melding "Je Authentik-account heeft geen
admin-toegang". Vraag dan een admin om je aan de juiste groep toe te voegen. Welke groepen
toegang geven staat onder Beheer › Instellingen › Authentik SSO-login; welke groep welke rol
krijgt staat onder Beheer › Rollen & rechten.

### Wachtwoordlogin als noodingang

Onder de SSO-knop staat "of met wachtwoord": e-mailadres en wachtwoord. Dit is bedoeld voor als
Authentik niet bereikbaar is. Alleen accounts die een admin een wachtwoord heeft gegeven (zie
[Beheerders en rechten](#beheerders-en-rechten)) kunnen hiermee inloggen. Na te veel mislukte
pogingen wordt het adres tijdelijk geblokkeerd; je ziet dan hoeveel minuten je moet wachten.
Mislukte pogingen komen in het logboek.

### Wat elke rol ziet

Het menu laat alleen de onderdelen zien waar je rol leesrecht op heeft. Standaard:

| Onderdeel | Begeleider | Bestuur | Penningmeester | Admin |
|---|---|---|---|---|
| Dashboard | ja | ja | ja | ja |
| Sollicitanten | – | bekijken, bewerken, verwijderen | alleen bekijken | alles |
| BLK-formulieren en woonadres | – | ja | – | ja |
| Landingspagina's | – | ja | – | ja |
| Selectiedag | inchecken en beoordelen | alles, ook nabespreking | – | alles |
| Ondertekeningen | – | ja | – | ja |
| Loondossiers | – | – | ja | ja |
| Beschikbaarheid, Loonplanning | – | ja | – | ja |
| Dekking | bekijken | bekijken | – | ja |
| Instellingen, Seizoenbeheer, Berichten, Sjablonen, Geplande berichten | – | ja | – | ja |
| Logboek | – | – | – | ja |
| Beheerders, Rollen & rechten, Systeem & Updates, Foutenlog | – | – | – | ja |

Admin heeft altijd alles en kan zichzelf nooit buitensluiten. Rechten zijn per rol aan te passen
(zie [Beheerders en rechten](#beheerders-en-rechten)).

### Je eigen profiel

Klik linksonder op je naam voor **Mijn profiel**. Daar wijzig je je weergavenaam en, als je
account een wachtwoord heeft, je wachtwoord. Ook staat daar de schakelaar **Donker thema**
(wordt per apparaat onthouden). Heb je zelf ook een sollicitatie lopen, dan staat linksonder de
link **Naar mijn sollicitatie** om naar het portaal te wisselen.

Op elke pagina met een vraagteken-knop rechtsboven staat een korte uitleg van dat scherm.

---

## Dashboard

**Waar:** Beheer › Dashboard  
**Wie:** iedereen die is ingelogd.

Het dashboard geeft in één oogopslag de stand van het seizoen:

- **Seizoenkaart** bovenaan: naam en status van het huidige seizoen (Open voor aanmeldingen of
  Gesloten), met de knoppen **Sluiten** of **Openen** en **Instellingen**. Daaronder de datum
  van de selectiedag en het trainingsweekend, of "Nog niet ingesteld".
- **Actie vereist** (oranje kaart): hoeveel aanmeldingen wachten op een beslissing over de
  selectiedag en hoeveel kandidaten na de selectiedag wachten op een beslissing over het
  trainingsweekend. Klik op **Bekijk** om die groep te openen.
- **Tegels**: Aanmeldingen, Uitgenodigd selectiedag, Trainingsweekend, Aangenomen, Afgewezen en
  Terugkerend. Elke tegel is klikbaar en opent de sollicitantenlijst met dat filter.
- **Systeem**: mails in de wachtrij, mailfouten van de laatste 24 uur, ondertekeningen die
  langer dan 3 dagen openstaan en het tijdstip van de laatste automatische run (cron). Wordt de
  kaart rood, dan lopen mails vast of draaien de automatische taken niet; zie [Systeem en
  foutenlog](#systeem-en-foutenlog).
- **Recente aanmeldingen**: de laatste acht, met **Bekijk** om het dossier te openen, plus de
  knoppen **+ Toevoegen** en **Alle bekijken**.

---

## Seizoen opzetten

**Waar:** Beheer › Instellingen › Seizoenbeheer  
**Wie:** bestuur en admin. Verwijderen en heropenen van seizoenen: alleen admin.

Alles in het beheer draait om het **huidige seizoen**: het seizoen dat je het laatst hebt
aangemaakt. De lijst "Alle seizoenen" rechts toont welk seizoen "Huidig" is. Let op: zodra je
een nieuw seizoen aanmaakt, is dát meteen het huidige seizoen, ook al staat het nog gesloten.
Maak een nieuw seizoen dus pas aan als het vorige echt klaar is.

### Stap voor stap

1. **Nieuw seizoen aanmaken.** Vul bij "Nieuw seizoen aanmaken" een naam in (bijvoorbeeld "Zomer
   2027") en klik op **Aanmaken**. Het seizoen begint gesloten. Bestaande gegevens van vorige
   seizoenen blijven bewaard; sollicitanten die al eerder meededen worden automatisch als
   terugkerend herkend.
2. **Datums instellen.** Vul de kaart "… instellen" in en klik op **Opslaan**:
   - **Werkperiode**: eerste en laatste werkdag. Sollicitanten zien in het portaal alle weken
     tussen deze datums als selecteerbare tegels bij hun beschikbaarheid. Zonder werkperiode
     werkt de beschikbaarheid niet goed.
   - **Hoofdseizoen vanaf week / t/m week**: weken daarbuiten tellen als voor- of naseizoen
     (weekend-focus in de bezettingsplanning). Leeg = alles is hoofdseizoen.
   - **Selectiedag**: datum, locatie, verzameltijd (wanneer kandidaten aanwezig moeten zijn),
     starttijd programma, eindtijd (het portaal toont "tot ± 16:00"), adres en "Wat meenemen".
   - **Trainingsweekend**: start- en einddatum, locatie, verzameltijd, begintijd programma,
     eindtijd, adres en "Wat meenemen".
3. **Openen.** Klik op **Openen** in de groene balk bovenaan (of op het dashboard). Vanaf dat
   moment is het aanmeldformulier actief en kunnen sollicitanten zich aanmelden. Met
   **Aanmeldformulier** open je het formulier zelf in een nieuw tabblad om te controleren.
4. **Procedure doorlopen**: uitnodigen, beoordelen, aannemen via Sollicitanten.
5. **Sluiten.** Aan het einde van het seizoen klik je op **Sluiten**. Het aanmeldformulier gaat
   dan dicht; alle gegevens blijven bewaard.

### Wat de sollicitant hiervan ziet

In het portaal krijgt de sollicitant, zodra hij is uitgenodigd, een kaart met de datum, de
verzameltijd, de starttijd, "tot ± eindtijd", de locatie met het adres en de tekst "Wat
meenemen". Hetzelfde geldt voor het trainingsweekend. Dezelfde gegevens zijn als variabelen in
mails te gebruiken (zie [Sjablonen](#sjablonen)). Zolang een datum leeg is, ziet de sollicitant
"Nog niet ingesteld" of geen kaart.

### Verwijderen

Alleen admin ziet in de lijst "Alle seizoenen" de prullenbak. Heeft een seizoen nog
sollicitaties, dan vraagt het systeem een extra bevestiging: alle sollicitaties van dat seizoen
worden dan definitief verwijderd, inclusief formulieren, beoordelingen en beschikbaarheid. Dit
kan niet ongedaan worden gemaakt.

---

## Landingspagina's

**Waar:** Beheer › Landingspagina's  
**Wie:** bestuur en admin.

Een landingspagina is een eigen wervingspagina voor één doelgroep (bijvoorbeeld een
Instagram-campagne of een school), met het aanmeldformulier erin. Je maakt het ontwerp in een
ontwerptool en uploadt het als HTML-bestand; na een controlerapport bevestig je en zet je de
status op **Gepubliceerd**. De pagina staat dan op `/p/<adres>`. Elke pagina heeft een
**bron-code** die meereist tot in het dossier van de sollicitant, zodat je ziet welke pagina
welke aanmeldingen (en aangenomen strandwachten) opleverde. De pagina zet geen cookies; bezoeken
worden anoniem geteld.

De volledige uitleg (ontwerp maken, markeringen zoals `[[formulier]]`, afbeeldingen, het
rapport, versies, statussen en problemen oplossen) staat in de aparte handleiding
**Landingspagina's** in dezelfde map als dit document.

---

## Sollicitanten

**Waar:** Beheer › Sollicitanten  
**Wie:** bestuur en admin (bekijken, bewerken, verwijderen); penningmeester alleen bekijken.

Dit is het hart van het systeem. De lijst toont alle sollicitanten van het huidige seizoen.

### De lijst

- **Tabbladen per fase** bovenaan (Alle, Aangemeld, Uitgenodigd SD, Aangemeld SD, SD geweest,
  Uitgenodigd TW, TW geweest, Aangenomen, Afgewezen) met het aantal per fase.
- **Filters**: zoeken op naam of e-mail, "Alle kandidaten" of "Alleen terugkerend", "BLK: alle /
  ingevuld / niet ingevuld" en, als Authentik gekoppeld is, "Competentie". Met **Wis filters**
  ga je terug naar de volledige lijst.
- **Kolommen**: standaard zie je naam, e-mail, aanmelddatum, status en acties. Via **Kolommen**
  zet je Telefoon, Leeftijd, BLK, Terugkerend en Foto erbij; die keuze wordt op je apparaat
  onthouden.
- **Per pagina**: 15, 25, 50 of 100.
- **+ Toevoegen** opent het scherm om handmatig een sollicitant in te voeren; **Export alle
  (Excel)** downloadt het hele seizoen.

In de kolom Acties staat bij een sollicitant in fase Aangemeld de knop **Uitnodigen SD** en bij
fase Selectiedag geweest **Uitnodigen TW**. Met **···** open je het dossier. Een groen label
"Uitnodigen" naast de naam betekent dat de sollicitant binnen de ingestelde leeftijdsgrenzen
valt (zie Instellingen › Uitnodiging & automatisering).

### Het detailpaneel

Klik op een rij en het dossier schuift rechts in beeld. Van boven naar beneden:

- **Gegevens**: telefoon, geboortedatum en leeftijd (met een oranje label als iemand binnenkort
  16 wordt of bij aanmelding nog 15 was), aanmelddatum, de vastgelegde privacy-toestemming en
  eventuele ouderlijke toestemming, woonadres (uit het BLK-formulier) en de huidige status.
- **Berichten via**: de kanaalvoorkeur van de sollicitant: WhatsApp, Sms of Alleen e-mail. Je
  kunt dit hier wijzigen, bijvoorbeeld als iemand aangeeft geen WhatsApp te willen. De wijziging
  wordt gelogd.
- **Uitnodigen aanbevolen**: bij fase Aangemeld, op basis van de leeftijdsgrenzen.
- **Competenties** (alleen met Authentik-koppeling): de vaardigheden van terugkerende
  sollicitanten uit Authentik. **Ververs** haalt ze opnieuw op; is er geen automatische match,
  dan koppel je handmatig op Authentik-gebruikersnaam met **Koppel**.
- **Fase wijzigen**: een knop per fase. De huidige fase is gemarkeerd. Je krijgt altijd een
  bevestigingsvraag; bij Uitgenodigd SD en Uitgenodigd TW staat erbij dat er automatisch een
  uitnodiging wordt verstuurd. Wat elke fase in gang zet, staat in [De fases en wat er
  automatisch gebeurt](#de-fases-en-wat-er-automatisch-gebeurt).
- **Voortgang**: de tijdlijn van fases.
- **AI Analyse**: als een AI-sleutel is ingesteld en het BLK-formulier is ingevuld, kun je met
  **Analyseer nu** een geanonimiseerde geschiktheidsanalyse laten maken. Die komt in een
  wachtrij en is binnen circa vijf minuten klaar. Er gaan geen naam, adres of contactgegevens
  naar de AI-dienst.
- **Notities**: interne opmerkingen van het bestuur, zichtbaar voor iedereen met toegang tot het
  dossier. Alleen je eigen notities kun je bewerken of verwijderen. Typ een tekst en klik op **+
  Toevoegen**.
- **Tijdlijn**: alles wat er met dit dossier gebeurde (fasewijzigingen, berichten, notities),
  nieuwste bovenaan.
- **Ondertekening**: de documenten die voor deze persoon klaarstaan of getekend zijn, met
  **Bekijk** (getekend) of **Link** (nog open; kopieert de ondertekenlink). Bij een minderjarige
  zie je de gegevens van de ouder/verzorger, of de waarschuwing dat die nog ontbreken. Met de
  knoppen **+ Gezondheid (ouder)**, **+ Intake (ouder)** of, bij 18+, **+ Gezondheid 18+** en
  **+ Intake selectiedag** zet je handmatig een document klaar en verstuur je meteen de mail.
  Zie [Ondertekeningen](#ondertekeningen).
- **Beter leren kennen**: een samenvatting van het BLK-formulier (opleiding, diploma's,
  werkervaring, motivatie, bijzonderheden) en de foto. **Volledig** opent het hele formulier;
  met de prullenbak verwijder je het formulier (de sollicitant kan het dan opnieuw invullen).
- **Beschikbaarheid**: voorlopig of definitief, de werkperiodes per week, afwezigheid, losse
  dagen, voorkeur vrije dagen en opmerkingen. Daaronder: **Beschikbaarheid per dag bekijken**,
  **Beschikbaarheid aanpassen**, openstaande **verzoeken om minder dagen** (Goedkeuren /
  Afwijzen; de sollicitant krijgt automatisch bericht), de **Norm** (standaard 5 dagen per week;
  afwijkend alleen na een bestuursbesluit, met reden) en de **bestuurs-correctie per week**
  (bijvoorbeeld na een telefoontje: "kan deze week niet" of "alleen bepaalde dagen", altijd met
  reden). Zie [Beschikbaarheid, dekking en
  loonplanning](#beschikbaarheid-dekking-en-loonplanning).
- **Selectiedag**: aanwezigheid, groep en scores als die er zijn.
- **Eerdere seizoenen**: eerdere sollicitaties van dezelfde persoon met de fase waarin ze
  eindigden.
- **Acties**: Uitnodigen selectiedag / Uitnodigen trainingsweekend (afhankelijk van de fase),
  **Bericht sturen** (opent Berichten met deze persoon voorgeselecteerd), **E-mail** en
  **Bellen**.
- **Verwijderen**: verwijdert de sollicitatie van dit seizoen inclusief BLK, beoordeling en
  beschikbaarheid. Het persoonsaccount blijft bestaan, zodat iemand later opnieuw kan
  solliciteren. Zie [Privacy en AVG-verzoeken](#privacy-en-avg-verzoeken).

### Bulkacties

Vink meerdere rijen aan (of gebruik het vinkje in de kop voor alle zichtbare rijen). Onderaan
verschijnt een balk met:

- **Uitnodigen SD**: alleen voor de aangevinkte personen die in fase Aangemeld staan; anderen
  worden overgeslagen.
- **Uitnodigen TW**: alleen voor wie in fase Selectiedag geweest staat.
- **Aannemen** en **Afwijzen**: voor alle aangevinkte personen, ongeacht hun fase.
- **Bericht sturen**: opent Berichten met deze personen als ontvangers.
- **Export Excel**: alleen de aangevinkte personen.

Bij meer dan tien personen gaan de berichten via de wachtrij en worden ze de komende minuten
verstuurd. Na afloop zie je een samenvatting: hoeveel bijgewerkt, hoeveel al in die fase,
hoeveel berichten verstuurd, overgeslagen of mislukt.

### Handmatig toevoegen

Via **+ Toevoegen**. Verplicht zijn voornaam, achternaam en e-mailadres; verder telefoonnummer,
geboortedatum, geslacht, het seizoen en de beginfase. Bestaat het e-mailadres al, dan wordt het
profiel bijgewerkt en de sollicitatie eraan gekoppeld (terugkerend). Bij "Interne notities" komt
de tekst als notitie in het dossier. Het vinkje **Stuur welkomstmail naar sollicitant**
(standaard aan) verstuurt de bevestiging van aanmelding met de link naar het portaal.

### Importeren

Alleen admin. Het importscherm staat niet in het menu; je bereikt het via het adres van het
beheer gevolgd door `import.php`. Het leest een CSV-bestand zoals geëxporteerd uit het oude
Google Sheets-systeem: naam, e-mail, telefoon, geboortedatum, geslacht, beschikbaarheid per week
(23 t/m 35), motivatie, ervaring en diploma's. Je kiest het seizoen en de beginfase. Dubbele
e-mailadressen worden overgeslagen, dus je kunt de import veilig meerdere keren draaien. Na
afloop zie je hoeveel personen zijn aangemaakt, bijgewerkt en overgeslagen, plus eventuele
fouten per rij.

### BLK bekijken

**Volledig** in het paneel opent het complete formulier "Beter leren kennen": persoonsgegevens
(woonadres alleen voor wie het BLK-recht heeft; anderen zien alleen de woonplaats), achtergrond,
motivatie, beschikbaarheid, bijzonderheden of medicatie, de foto, de AI-analyse en de
wijzigingsgeschiedenis als de sollicitant het formulier later aanpaste. Bovenaan staan
**Afdrukken**, **Dossier (PDF)**, **Excel** en **Open in paneel**.

---

## De fases en wat er automatisch gebeurt

Elke fase-overgang gaat via dezelfde route, of je hem nu vanuit het paneel, de lijst, een
bulkactie of de bellijst zet. Daardoor doet een overgang altijd hetzelfde. Welke melding er gaat
en via welk kanaal, stel je in onder Instellingen › SMS, WhatsApp & meldingen en bij de
sjablonen.

| Fase | Wat het bestuur doet | Wat het systeem doet |
|---|---|---|
| **Aangemeld** | Niets; de aanmelding komt binnen via de website, een landingspagina of handmatig. | Stuurt de "Bevestiging van aanmelding" (e-mail) en de bestuursmelding "Nieuwe aanmelding". Toont in de lijst de suggestie "Uitnodigen" bij wie binnen de leeftijdsgrenzen valt; bij modus Automatisch nodigt het systeem na de ingestelde vertraging zelf uit. |
| **Uitgenodigd selectiedag** | Klikt op Uitnodigen SD (lijst, paneel of bulk). | Zet de ondertekeningen klaar: bij 18+ de gezondheidsverklaring en het intakeformulier als taak in het portaal; bij minderjarigen wacht het systeem tot de sollicitant de gegevens van de ouder/verzorger invult en mailt dan de ouder. Stuurt de "Uitnodiging selectiedag" (standaard e-mail én WhatsApp/SMS). |
| **Aangemeld selectiedag** | Niets; de sollicitant levert het formulier "Beter leren kennen" in. | Zet de fase, stuurt de "Bevestiging aanmelding selectiedag" (e-mail) en de bestuursmelding "Formulier ingeleverd" (standaard uit). Start de AI-analyse als die op automatisch staat. |
| **Selectiedag geweest** | Zet de kandidaat op Aanwezig bij Inchecken (of de kandidaat checkt zelf in op de tablet). | Zet de fase. Geen melding. |
| **Uitgenodigd trainingsweekend** | Voert het besluit "Door" door via de bellijst, of klikt op Uitnodigen TW. | Stuurt de "Uitnodiging trainingsweekend" (standaard e-mail én WhatsApp/SMS) met het verzoek de beschikbaarheid door te geven. |
| **TW geweest** | Zet de fase handmatig in het paneel. | Zet de fase. Geen melding. |
| **Aangenomen** | Klikt op Aannemen (paneel of bulk). | Activeert het kennismakingspakket: taken in het portaal voor de upload van identiteitsbewijs en bankpas, het loonheffingsformulier, de inschrijving als lid, de huisregels en het activeren van het verenigingsaccount. Stuurt "Welkom: aangenomen" (e-mail). Zodra loonheffing, ID en bankpas binnen zijn, gaat de melding "Loondossier compleet" naar de penningmeester. |
| **Afgewezen** | Voert het besluit "Niet" door via de bellijst, of klikt op Afwijzen. | Zet de fase. De melding "Niet geselecteerd" staat standaard **uit**: het bestuur belt of mailt persoonlijk. |

Goed om te weten:

- **Terugzetten** naar Aangemeld, Selectiedag geweest of TW geweest verstuurt nooit een bericht.
  Alleen de overgangen naar Uitgenodigd SD, Aangemeld SD, Uitgenodigd TW, Aangenomen en
  Afgewezen kunnen een melding sturen.
- **Geen dubbele uitnodiging**: dezelfde melding gaat niet twee keer binnen een uur naar
  dezelfde persoon. Zet je iemand per ongeluk terug en meteen weer vooruit, dan zie je "Bericht
  al eerder verstuurd (geen dubbele)".
- **Een fase "stil" zetten** (zonder mail) kan niet met een knop. Wil je bijvoorbeeld iemand
  opnieuw op Uitgenodigd SD zetten zonder nieuwe uitnodiging, zet dan eerst onder Instellingen ›
  SMS, WhatsApp & meldingen het kanaal van dat meldingstype op **Uit**, doe de wijziging, en zet
  het kanaal daarna terug. Vergeet die laatste stap niet.
- **Automatiseringen uitzetten**: onder Instellingen › Uitnodiging & automatisering kun je het
  klaarzetten van ondertekeningen bij de SD-uitnodiging en het kennismakingspakket bij aannemen
  uitschakelen.
- Na elke overgang laat het scherm zien wat er met het bericht gebeurde: verstuurd (via welk
  kanaal), in de wachtrij, mislukt (kijk in het berichtenlog), of overgeslagen omdat er geen
  adres of nummer bekend is.

---

## Selectiedag

**Waar:** Beheer › Selectiedag (vier stappen: Groepsindeling, Inchecken, Beoordelen,
Nabespreking)
**Wie:** begeleider (inchecken en beoordelen), bestuur en admin (alles, inclusief nabespreking
en de bellijst).

De selectiedag werkt met iedereen in de fases Uitgenodigd SD, Aangemeld SD en Selectiedag
geweest. Staat er nog niemand in die fases, dan zie je "Nog geen sollicitanten uitgenodigd voor
de selectiedag".

### 1 · Groepsindeling

1. Vul het **Aantal groepen** in en klik op **Instellen**.
2. Klik op **Voorstel genereren**. Het systeem verdeelt eerlijk (gelijke groepen, gemengd op
   leeftijd en geslacht) en houdt bekende koppels uit elkaar. Let op: dit overschrijft een
   bestaande indeling.
3. **Sleep** kaartjes tussen de groepen om te schuiven. De kolom "Nog niet ingedeeld" toont wie
   nog geen groep heeft.
4. Klik op een naam om aan te vinken wie deze persoon kent en dus liever **niet in dezelfde
   groep** zit. Een waarschuwingsteken op een kaartje betekent dat zo'n koppel toch samen staat.

### 2 · Inchecken

Twee manieren, die door elkaar te gebruiken zijn:

- **Aanwezigheidslijst**: per groep een rij per kandidaat met de knop **Afwezig / Aanwezig**.
  Klik om om te zetten; de teller "x/y aanwezig" loopt mee. Zoeken op naam kan bovenaan.
- **Tablet-inchecken** (zelfservice): een bestuurslid klikt op **Toegangscode aanmaken** (of
  **Nieuwe code**, waarna de oude tablet-links niet meer werken). Kopieer of open de
  **Tablet-link** en zet die pagina klaar op de tablets bij de ingang. De kandidaat typt zijn
  naam, tikt zichzelf aan en ziet meteen in welke groep hij zit ("Ga naar de tafel van je
  groep"). Met **Volgende persoon** is de tablet klaar voor de volgende. Wie al is aangemeld,
  ziet "al aangemeld". Staat iemand niet in de lijst, dan staat er "Vraag het organisatieteam om
  hulp".

Zodra iemand op Aanwezig staat, zet het systeem de fase op **Selectiedag geweest**.

### 3 · Beoordelen

Elke beoordelaar werkt onder zijn eigen naam (die staat bovenaan). Kies eventueel een groep en
tik een naam open. Per kandidaat zie je de foto en eventuele bijzonderheden uit het
BLK-formulier. Dan:

- **Observatie** noteren: typ wat opviel, kies **Positief**, **Neutraal** of **Aandacht** en
  klik op **Opslaan**. Observaties mogen over iedereen gaan, ook buiten je eigen groep. Je eigen
  observaties kun je verwijderen; bestuur kan alle observaties verwijderen.
- **Sterren** geven per criterium, gegroepeerd per test (standaard Sporttest en Zwemtest; de
  criteria stel je in onder Instellingen › Beoordelingscriteria). Klik op een ster voor 1 t/m 5;
  dezelfde ster nogmaals wist je score. Je ziet alleen je eigen sterren; rechts staat het
  gemiddelde van alle beoordelaars als er meer zijn.

### 4 · Nabespreking

Alleen bestuur en admin. Per kandidaat staan alle observaties en de gemiddelde scores bij
elkaar. Filter op Iedereen, Nog open, Door, Twijfel of Niet.

1. Zet per persoon een voorlopig **besluit**: **Door naar TW**, **Twijfel** of **Niet**,
   eventueel met een notitie. Nogmaals klikken wist het besluit. Dit verandert nog niets aan de
   fase.
2. Ga naar het tabblad **Bellijst**. Daar staat iedereen met een besluit Door of Niet die nog
   niet is doorgevoerd, met telefoonnummer. Bel iedereen persoonlijk met de uitslag en klik
   daarna op **Gebeld** (met notitie, bijvoorbeeld "wil bedenktijd") of meteen op **Gebeld +
   doorvoeren**. Een vergissing herstel je met **Ongedaan**.
3. **Doorvoeren**, per persoon of in één keer met **Alle gebelde doorvoeren**. Door wordt
   Uitgenodigd trainingsweekend (met de uitnodigingsmail); Niet wordt Afgewezen (zonder mail,
   tenzij je dat meldingstype hebt aangezet). Twijfelgevallen blijven staan.

Wil je de bellijst overslaan, dan staat onderaan de nabespreking onder **Geavanceerd** de knop
**Alles doorvoeren**. Gebruik die alleen bij heel veel sollicitanten en na een bewuste keuze,
want dan gaan de uitnodigingen direct de deur uit.

---

## Ondertekeningen

**Waar:** Beheer › Ondertekeningen (en per persoon in het detailpaneel)  
**Wie:** bestuur en admin. Het loonheffingsformulier en de inschrijving als lid kunnen alleen
penningmeester en admin openen.

### Welke documenten

| Document | Wie tekent | Wanneer klaargezet |
|---|---|---|
| Gezondheidsverklaring 18+ | Sollicitant zelf, in het portaal | Bij de uitnodiging voor de selectiedag |
| Gezondheidsverklaring (t/m 17) | Ouder/verzorger, via een link per mail | Zodra de minderjarige de gegevens van de ouder heeft ingevuld |
| Intakeformulier selectiedag | Sollicitant zelf | Bij de uitnodiging voor de selectiedag |
| Intakeformulier selectiedag (ouder) | Ouder/verzorger | Zodra de oudergegevens bekend zijn |
| Opgaaf gegevens voor de loonheffingen | Sollicitant zelf, ook onder de 18 | Bij aannemen (kennismakingspakket) |
| Inschrijving lidmaatschap vereniging | Sollicitant zelf, ook onder de 18 | Bij aannemen, als er nog geen verenigingsaccount is |

De grens van 18 jaar geldt voor het tekenen: jonger dan 18 tekent via een ouder of verzorger,
behalve voor het loonheffingsformulier en het lidmaatschap, die de sollicitant altijd zelf
tekent. De gezondheidsverklaring is één jaar geldig vanaf de ondertekening.

### Hoe het loopt

- **18+**: de documenten verschijnen als taken in het portaal. De sollicitant tekent daar. Hij
  krijgt daarna een bevestiging met de getekende PDF als bijlage.
- **Minderjarig**: de sollicitant vult in het portaal naam, e-mailadres en telefoonnummer van de
  ouder/verzorger in. De ouder ontvangt één mail met de links naar beide formulieren en tekent
  digitaal. De sollicitant krijgt bericht dat de ouder heeft getekend; de kopie-PDF gaat naar de
  ouder.
- **Verificatiecode**: alleen bij het loonheffingsformulier moet de ondertekenaar eerst een code
  invoeren die per SMS (of WhatsApp of e-mail als SMS niet kan) wordt gestuurd. Dat staat als
  zekerheidsniveau op de certificaatpagina van de PDF. Voor de andere documenten is geen code
  nodig.
- **Herinneringen**: staat een document na een paar dagen nog open, dan krijgt de ondertekenaar
  automatisch een herinnering (instelbaar, zie [Geplande berichten en
  herinneringen](#geplande-berichten-en-herinneringen)).

### Het overzicht

Per persoon een kaart met een voortgangsbalk ("2/2") en het label Compleet of "x open". Filter
op **Iedereen**, **Nog open** of **Compleet**. Klik op de kaart om de documenten uit te klappen.
Per document:

- Getekend: datum en naam van de ondertekenaar, en **Bekijk** om de PDF te openen (in een nieuw
  tabblad) en te printen voor de administratie. Bij loonheffing en lidmaatschap zie je het label
  "Loondossier" als je daar geen recht op hebt.
- Nog open: **Kopieer link**. Plak die link in een mail of app als de automatische mail niet is
  aangekomen.

Klik op de naam om het dossier te openen.

### Een verkeerd ouder-mailadres

Heeft de sollicitant een fout e-mailadres van de ouder ingevuld, dan komt de mail niet aan.
Belangrijk: als de sollicitant in het portaal het adres corrigeert en op "Opnieuw versturen"
klikt, worden de gegevens wel opgeslagen maar gaan de al klaargezette formulieren **niet**
opnieuw de deur uit. Doe daarom het volgende:

1. Open het dossier, sectie Ondertekening.
2. Klik op **+ Gezondheid (ouder)** en daarna op **+ Intake (ouder)**. Je wordt gevraagd om het
   (juiste) e-mailadres en de naam van de ouder. Het systeem maakt nieuwe documenten aan en
   mailt de ouder meteen.
3. Of: klik bij het openstaande document op **Link** / **Kopieer link** en stuur de link zelf
   naar het goede adres.

De oude, niet-getekende documenten blijven in de lijst staan als "wacht". Automatische
herinneringen voor die oude documenten kunnen nog naar het foute adres gaan; wil je dat niet,
zet dan de herinnering "Document nog niet ondertekend" tijdelijk uit of vraag de technisch
beheerder de oude regels op te ruimen.

---

## Beschikbaarheid, dekking en loonplanning

**Waar:** Beheer › Beschikbaarheid, Beheer › Dekking, Beheer › Loonplanning  
**Wie:** Beschikbaarheid en Loonplanning: bestuur en admin. Dekking: begeleider, bestuur en
admin (alleen bekijken).

### Beschikbaarheid

Het overzicht toont per sollicitant met een ingevuld BLK-formulier (behalve afgewezen) een
matrix van de weken uit de werkperiode van het seizoen. Per cel zie je het aantal inzetbare
dagen; de kleur betekent volledige week, alleen weekend, anders/in overleg of niet beschikbaar.
De kolom "wkn" telt het aantal beschikbare weken per persoon. De onderste rij telt per week
hoeveel mensen beschikbaar zijn en hoeveel daarvan het weekend dekken; in voor- en naseizoen
kleurt dat getal rood bij minder dan drie. Heb je het hoofdseizoen nog niet ingesteld, dan staat
daar een tip.

Bovenaan staan twee kaarten als ze van toepassing zijn:

- **Nog voorlopig**: wie de beschikbaarheid nog niet definitief heeft gemaakt, met de deadline
  (twee weken vóór het trainingsweekend). Deze mensen krijgen vanaf twee weken vóór de deadline
  automatisch herinneringen.
- **Openstaande verzoeken**: verzoeken om in bepaalde weken minder dagen te werken.
  **Goedkeuren** of **Afwijzen** kan hier of in het dossier; de sollicitant krijgt automatisch
  bericht van het besluit.

Klik op een cel om voor die week een **bestuurs-correctie** vast te leggen: "Kan deze week niet"
of "Alleen bepaalde dagen", altijd met reden. Dat overschrijft wat de sollicitant opgaf en is
herleidbaar. Herstellen kan in het dossier met **Herstel**.

**Beschikbaarheid aanpassen** (knop in het dossier) opent een apart scherm met twee tabbladen:

- **Per dag**: een kalender van de hele werkperiode. Klik op een dag en kies **Beschikbaar
  maken** of **Niet beschikbaar maken**, eventueel met reden. Een blauw randje betekent "door
  het bestuur aangepast"; met **Aanpassing terugdraaien** geldt de eigen invoer van de
  sollicitant weer.
- **Formulier (portaal-stijl)**: werkperiodes (week t/m week, hele weken of alleen weekenden)
  namens de sollicitant invullen. Dit overschrijft de periode-invoer van de sollicitant;
  vakanties en verzoeken blijven staan.

Aanpassingen worden de nieuwe waarheid, ook in het portaal van de sollicitant, en worden
vastgelegd met wie en wanneer.

Exports: **Beschikbaarheid per dag bekijken** (in het dossier of het aanpasscherm) toont per dag
of iemand beschikbaar is, precies zoals het aan het rooster wordt doorgegeven. De Excel-export
van de sollicitantenlijst bevat de beschikbaarheid als leesbare tekst.

### Dekking

Dekking rekent uit of de posten per week te bemannen zijn met wie beschikbaar is. Dit werkt
alleen als Authentik gekoppeld is (competenties) en de posten zijn ingesteld onder Instellingen
› Posten & bezetting. Per week zie je een tegel met Gedekt (groen), Krap (oranje, precies
genoeg, geen reserve) of Tekort (rood). Klik op een week voor de details: per post en per rol
wie erop kan staan, hoeveel reserve er is, en bij een tekort waarom. Onder "Wel gekwalificeerd,
niet beschikbaar deze week" staat een bellijstje met telefoon en e-mail. Eén persoon telt maar
één keer: een schipper kan niet tegelijk op een andere post staan. De postcommandant valt buiten
deze berekening.

### Loonplanning

Hier plan je wie welke week hoeveel dagen werkt en wat dat kost.

- **Scenario's**: je werkt altijd in een scenario. Met **+ Dupliceren** maak je een kopie om een
  alternatief door te rekenen; met **Maak actief** wordt een scenario de actieve planning
  (ster). Een niet-actief scenario kun je verwijderen; het laatste scenario niet. **CSV**
  exporteert het scenario voor de penningmeester.
- **De tabel**: per persoon de functie (keuzelijst; geldt voor alle weken) en per week een
  vinkje **opnemen** en het aantal dagen. Grijze cellen: niet beschikbaar die week. Een oranje
  rand op het dagenveld betekent meer dagen dan de persoon beschikbaar is. Rechts de totale
  kosten en dagen per persoon.
- **Dekking** (onderste rij): rekent mee met wie je daadwerkelijk inplant. Plan je de schipper
  weg, dan wordt die week meteen rood.
- **Signalen**: aandachtspunten boven de tabel (geen blokkades; jij beslist).
- **Loontabel**: uitklapbaar, het dagloon per leeftijd en functie voor juni en juli–september.
- **Onderaan**: totale loonkosten, mandagen en, als er een seizoensbudget is ingesteld
  (Instellingen › Vakantietoeslag & lonen), de voortgangsbalk met het restant of "Over budget".

Alle wijzigingen worden direct opgeslagen.

---

## Berichten sturen

**Waar:** Beheer › Instellingen › Berichten  
**Wie:** bestuur en admin (bekijken: leesrecht op Berichten & sjablonen; versturen:
bewerkrecht).

### Nieuw bericht

1. **Kanaal**: **E-mail** (volledige opmaak), **WhatsApp/SMS** (korte tekst; e-mail als
   terugval) of **E-mail + WhatsApp/SMS**. WhatsApp of SMS volgt de voorkeur van de sollicitant;
   zonder telefoonnummer of bij een mislukking gaat het bericht per e-mail. Wie zich heeft
   afgemeld voor berichten wordt overgeslagen. Elk handmatig bericht krijgt een afmeldvoet.
2. **Inhoud**: kies een eigen sjabloon uit de keuzelijst, of schrijf zelf: onderwerp, optioneel
   een hero-titel (grote tekst bovenin), de e-mailtekst in de editor, en de **korte tekst** voor
   WhatsApp/SMS (platte tekst, maximaal 400 tekens; verplicht als je een kort kanaal kiest).
   Onder **Bijlagen** voeg je bestanden toe (pdf, jpg, png, webp, docx, xlsx). Grote bestanden
   gaan als downloadknop mee; bij WhatsApp/SMS krijgt de ontvanger een downloadlink die 14 dagen
   geldig is en wordt er ook een e-mail gestuurd.
3. **Ontvangers**: rechts staan alle sollicitanten van het seizoen, gegroepeerd per fase. Vink
   personen of hele fases aan; **Alles** / **Geen** voor de hele lijst. Bij elke naam staat het
   e-mailadres en het kanaal (WhatsApp, sms, alleen e-mail of "geen telefoon"). Kom je vanuit
   het dossier of een bulkactie, dan staan die personen al aangevinkt.
4. **Variabelen**: klik op een chip (bijvoorbeeld voornaam, selectiedag_datum, portaal_url) om
   die in de tekst te zetten. Ze werken ook in het onderwerp en de korte tekst.
5. **Voorbeeld** toont de e-mail en het korte bericht zoals de eerste ontvanger ze krijgt.
   **Testmail naar mijzelf** stuurt de e-mail naar je eigen adres. **Verzenden** vraagt om
   bevestiging en verstuurt.

Bij meer dan tien ontvangers gaat het bericht via de wachtrij en wordt het de komende minuten
verstuurd. Na het verzenden zie je per persoon wat er gebeurde: verstuurd (via welk kanaal), in
de wachtrij, overgeslagen (met reden) of mislukt.

### Berichtenlog

Het tabblad **Berichtenlog** toont de laatste 200 berichten van alle kanalen, ook de
automatische: datum, ontvanger, onderwerp, kanaal en status (verstuurd, wacht, mislukt,
overgeslagen). Filter op naam of onderwerp, kanaal, status en type. Een sterretje achter het
kanaal betekent dat het bericht is teruggevallen op dat kanaal nadat het eerste kanaal mislukte.
Klik op een regel voor de details en, zolang de bewaartermijn niet is verstreken, de inhoud. Van
inlogcodes en bestuursmeldingen wordt de inhoud nooit bewaard.

---

## Sjablonen

**Waar:** Beheer › Instellingen › Sjablonen  
**Wie:** bestuur en admin.

Elk automatisch bericht heeft een sjabloon. Links staan ze per categorie: Fase-overgangen, Taken
en documenten, Herinneringen, Meldingen aan het bestuur, Systeem, Gepland en Eigen sjablonen.
Klik op een sjabloon om het te bewerken.

Per sjabloon bewerk je:

- **Onderwerpregel** (verplicht), **Hero-titel** (leeg = onderwerp), **Hero-subtitel**,
  **Hero-kleur** (leeg = huisstijl-navy) en optioneel een **Hero-afbeelding** (upload of URL).
- **E-mailtekst** in de editor. Opmaakblokken via de knoppen: Knop, Info-blok, Let op.
- **Korte tekst (WhatsApp/SMS)**: platte tekst, maximaal 400 tekens, geen opmaak. Bij typen die
  alleen per e-mail gaan (naar ouders en het bestuur) staat hier "Dit type gaat alleen per
  e-mail".
- **Samenvatting (portaal)**: één regel onder de titel in het berichtenoverzicht van de
  sollicitant.
- **Bijlagen**: gaan mee met elke e-mail van dit type; uitvinken koppelt een bijlage los.
- **Kanaal** (rechtsboven): E-mail, WhatsApp/SMS, E-mail + WhatsApp/SMS of Uit. Dit is dezelfde
  instelling als onder Instellingen › SMS, WhatsApp & meldingen.

Rechts staan de **variabelen**: eerst die speciaal voor dit type gelden (bijvoorbeeld
document_naam of taken_aantal), daaronder de altijd beschikbare (voornaam, achternaam,
volledige_naam, seizoen_naam, selectiedag_datum, selectiedag_tijd, selectiedag_locatie,
selectiedag_adres, selectiedag_meenemen, tw_datum_start, tw_datum_eind, tw_locatie,
tw_verzameltijd, tw_meenemen, portaal_url, bestuur_email, afmeld_url en meer). Klik op een chip
om hem in de e-mailtekst te zetten.

Knoppen: **Opslaan**, **Voorbeeld e-mail** en **Voorbeeld kort bericht** (openen in een nieuw
tabblad met voorbeeldgegevens), en **Terugzetten naar standaard** (zet de ingebouwde tekst
terug; je eigen wijzigingen gaan verloren).

**Eigen sjablonen** maak je links onder "Nieuw eigen sjabloon" met een naam. Die zijn te kiezen
bij Berichten (handmatig) en bij Geplande berichten. Een eigen sjabloon kun je verwijderen; de
ingebouwde typen niet. Onder "Standaard hero-afbeelding" stel je de foto in die bovenaan elke
mail staat als een sjabloon geen eigen afbeelding heeft.

---

## Geplande berichten en herinneringen

**Waar:** Beheer › Instellingen › Geplande berichten (regels) en Beheer › Instellingen ›
Uitnodiging & automatisering (herinneringen)
**Wie:** bestuur en admin.

### Herinneringen (ingebouwd)

Het systeem stuurt automatisch herinneringen, gecontroleerd op wat er echt verstuurd is, zodat
niemand twee keer hetzelfde krijgt. Per regel stel je in of hij aanstaat, na hoeveel dagen, hoe
vaak hij herhaalt en het maximum:

| Herinnering | Standaard |
|---|---|
| Nog niet aangemeld voor de selectiedag | aan; 3 dagen na de uitnodiging, herhaal elke 4 dagen, max 3; alleen als de selectiedag binnen 14 dagen is |
| Gegevens ouder/verzorger ontbreken | aan; na 2 dagen, herhaal elke 4 dagen, max 3 |
| Document nog niet ondertekend | aan; na 3 dagen, herhaal elke 5 dagen, max 2 per document; naar de ondertekenaar |
| Beschikbaarheid nog voorlopig | aan; vanaf 14 dagen vóór de deadline, herhaal elke 4 dagen, max 5; na de deadline elke 2 dagen |
| Openstaande taken | aan; 5 dagen na de laatste fasewijziging, herhaal elke 7 dagen, max 3 |
| Selectiedag nadert | aan; 3 dagen vooraf, één keer |
| Selectiedag morgen | aan; de dag ervoor, één keer, met een vrij in te vullen "laatste info" |
| Trainingsweekend nadert | aan; 3 dagen vooraf, één keer |

Daarnaast geldt een **rustperiode** (standaard 3 dagen): per sollicitant maximaal één "zachte"
herinnering per zoveel dagen. De herinneringen draaien mee met de automatische taken (elke vijf
minuten). Onder Beheer › Instellingen › Systeem & Updates › Herinneringen kan een admin
**Proefdraaien (niets versturen)** om te zien wie er een herinnering zou krijgen, of **Nu
draaien**.

### Geplande berichten (eigen regels)

Een regel is: "X dagen na (of vóór) gebeurtenis Y stuur sjabloon Z". Bijvoorbeeld: tien dagen na
het inleveren van het formulier het magazine sturen.

1. Maak eerst een **eigen sjabloon** onder Sjablonen (inhoud, eventueel met bijlage).
2. Klik op **Nieuwe regel** en vul in: naam (intern), **Actief**, de **gebeurtenis**, **Dagen
   erna** (negatief = ervoor, bijvoorbeeld -3 = drie dagen vóór de selectiedag), het
   **sjabloon**, het **kanaal**, eventueel **alleen voor deze fases**, **Max per persoon** en
   het **venster** in dagen (alleen gevallen waarvan het moment hoogstens zoveel dagen geleden
   was, zodat je niet na-zendt aan oude gevallen).
3. **Opslaan**. Met **Proefdraaien** zie je per regel wie het bericht zou krijgen, zonder iets
   te versturen. **Bewerken** en **Verwijderen** spreken voor zich.

---

## Instellingen

**Waar:** Beheer › Instellingen (tabbladen links)  
**Wie:** bestuur en admin.

| Tabblad | Wat je hier instelt |
|---|---|
| **Algemeen** | Naam van de organisatie, logo en favicon (upload of URL), de welkomsttekst op de portaal-login, de huisregels-pdf voor de taak "Huisregels lezen" van aangenomen strandwachten (zonder document blijft de taak staan als "volgt binnenkort"), en de minimum- en maximumleeftijd voor het aanmeldformulier. |
| **Functies & factoren** | Per functie (bijvoorbeeld lifeguard, postcommandant) de naam, de factor waarmee het dagloon wordt berekend, minimum- en maximumleeftijd en de volgorde. Daaronder het vakantietoeslagpercentage (standaard 8%), de werkuren per dag (standaard 8) en de vaste weekbedragen voor de postcommandant. Bij elke functie staat een voorbeeldberekening. |
| **Uitnodiging & automatisering** | Auto-invite: modus Uit, Suggestie (markeert in de lijst) of Automatisch (nodigt uit na de ingestelde vertraging in uren), met de leeftijdsgrenzen. De automatiseringen bij een fasewijziging: ondertekeningen klaarzetten bij de SD-uitnodiging en het kennismakingspakket bij aannemen. Alle herinneringsregels (zie hierboven), de rustperiode, het anti-loop-plafond (hetzelfde automatische bericht maximaal zoveel keer per dag naar dezelfde ontvanger), tot welke grootte een bijlage als e-mailbijlage meegaat, en het maximum aantal WhatsApp-berichten per uur. |
| **Beoordelingscriteria** | De naam, het icoon en de criteria (één per regel) van de Sporttest en de Zwemtest waarop beoordelaars op de selectiedag sterren geven. **Standaard uit draaiboek herstellen** zet de ingebouwde lijst terug. |
| **Posten & bezetting** | Welke posten er zijn en welke rollen per post bemand moeten zijn (rol, competentiecategorie, minimale rang uit Authentik, aantal personen). Per post het vinkje "nooit 2 aspiranten samen". Dekking rekent hiermee. De postcommandant valt hier buiten. |
| **Vakantietoeslag & lonen** | Het totaalbudget voor loonkosten dit seizoen (voor de voortgangsbalk in de loonplanning; leeg = zonder budget) en de minimumuurlonen per leeftijd (15 t/m 32) voor juni en voor juli–september, met het berekende dagloon ernaast. |
| **Mail & herinneringen** | De e-mailadressen: foutmeldingen, bestuur (ontvangt de bestuursmeldingen), penningmeester (ontvangt "loondossier compleet"; leeg = bestuursadres) en ledenservice (ontvangt de ondertekende inschrijving als lid; leeg = bestuursadres). Het vinkje om loondossier-stukken als bijlage mee te sturen aan de penningmeester staat standaard uit; laat dat zo tenzij het mailpad vertrouwd is. Onderaan de cronjob-regel voor de hoster, een knop **Handmatig uitvoeren** en de stand van de AI- en mailwachtrij. |
| **E-mailserver (SMTP)** | De mailserver waarmee het systeem mailt: host, poort, beveiliging (STARTTLS, SSL/TLS of geen), gebruikersnaam, wachtwoord, afzenderadres en afzendernaam. Met **Verstuur testmail** controleer je of het werkt; sla eerst op. |
| **AI-analyse** | De Gemini API-sleutel en het model, de experimentele AI-hulp bij beschikbaarheid (sollicitanten typen hun beschikbaarheid in gewone taal), de prompt-instructie voor de BLK-analyse (met variabelen; **Standaard terugzetten** herstelt de ingebouwde tekst) en of er automatisch wordt geanalyseerd zodra een BLK-formulier binnenkomt. |
| **Authentik & competenties** | De basis-URL en API-token van Authentik om competenties van terugkerende sollicitanten op te halen, **Test verbinding**, **Nu alle sollicitanten verversen**, en de groep-indeling: welke Authentik-groepen als competentie tellen en hun rangorde (alleen te bekijken). |
| **Authentik SSO-login** | Status van de SSO-koppeling, de API-token waarmee terugkerende sollicitanten via Authentik in het portaal kunnen inloggen (leeg = alleen inlogcode per mail) en de **toegestane admin-groepen**: de Authentik-groepen die toegang geven tot het beheer. |
| **Website & API** | De geheime sleutel voor de WordPress-plugin op de website (**Kopieer**; **Nieuwe sleutel genereren** maakt de oude ongeldig), de toegestane websites (CORS) en de adressen die de plugin nodig heeft. |
| **SMS, WhatsApp & meldingen** | SMS inschakelen met de gateway en bijbehorende gegevens; WhatsApp inschakelen via WhatsReach (staat standaard uit; lees de waarschuwing over blokkering van het nummer). Daaronder per meldingstype het kanaal: E-mail, WhatsApp/SMS, Beide of Uit. Ouders en het bestuur krijgen altijd e-mail. Onderaan **Testbericht sturen** naar een telefoonnummer of e-mailadres via het gekozen kanaal; het resultaat vertelt ook of er is teruggevallen op een ander kanaal. |
| **Bewaartermijnen (AVG)** | Automatisch opschonen inschakelen en de termijnen per categorie (zie [Privacy en AVG-verzoeken](#privacy-en-avg-verzoeken)), plus het opschoon-logboek met de laatste runs. |

---

## Loondossiers

**Waar:** Beheer › Loondossiers  
**Wie:** penningmeester en admin. Bestuur ziet dit scherm niet.

Per aangenomen strandwacht staat hier het loondossier: het ondertekende loonheffingsformulier
(digitaal verzegelde PDF), de kopie van het identiteitsbewijs (voor- en achterkant; bij een
paspoort alleen de voorkant), de kopie bankpas en de inschrijving als lid. Elke download wordt
vastgelegd (wie, wanneer) en op ID-kopieën komt een watermerk. Is een dossier compleet, dan
markeer je het met **Verwerkt in de loonadministratie**; de sollicitant kan de documenten daarna
niet meer wijzigen en de bankpasfoto wordt na de ingestelde termijn automatisch vernietigd (of
direct met **Vernietigen**). Met **Melding opnieuw naar de penningmeester** stuur je de
compleet-melding nog eens. Download alleen op een vertrouwd apparaat en verwijder de bestanden
zodra ze in de loonadministratie staan. Er komt een aparte handleiding voor de penningmeester.

---

## Logboek

**Waar:** Beheer › Logboek  
**Wie:** alleen admin.

Het logboek toont alle sporen van het systeem in één tijdlijn: wie wat wanneer deed (inclusief
inzage in persoonsgegevens en downloads uit het loondossier), verstuurde berichten, technische
fouten, het bewijsspoor van ondertekeningen, wat de AVG-opschoning heeft opgeruimd en mislukte
inlogpogingen. Gebruik het om een melding te herleiden of verantwoording af te leggen, niet om
mensen te volgen.

- **Tabbladen per bron**: Alles, of één van de zes bronnen, met het aantal regels.
- **Filters**: vrij zoeken in de details, **Wie** (naam, e-mail of nummer), **Actie**,
  **Ernst**, en een periode van/tot. **Wis filters** haalt alles weg.
- **Kolommen**: Wanneer, Wie (met het soort: beheerder, sollicitant, systeem, en het IP-adres),
  Wat (de actie met eventueel een ernst-label), Waar (het object, bijvoorbeeld een sollicitatie)
  en Details.
- Klik in de kolom **Waar** op een object om alles te zien wat daarmee gebeurd is.
- **Export (CSV)** downloadt de huidige selectie; de export zelf wordt ook gelogd.

Regels worden na de ingestelde termijn (standaard 12 maanden) automatisch opgeruimd.

---

## Beheerders en rechten

**Waar:** Beheer › Beheerders en Beheer › Rollen & rechten  
**Wie:** alleen admin.

### Beheerders

De lijst toont per account naam, e-mailadres, rol (keuzelijst; wijzigen slaat direct op), hoe
iemand inlogt (Wachtwoord, Authentik of beide) en de laatste login. Per account:

- **Sleutel-knop**: een nieuw wachtwoord instellen (minimaal 8 tekens). Gebruik dit om iemand
  een noodingang te geven naast SSO.
- **Deactiveren** / **Activeren**: een gedeactiveerd account kan niet meer inloggen; jezelf
  deactiveren kan niet.

Rechts maak je een nieuw account aan met naam, e-mailadres, een tijdelijk wachtwoord (geef dit
persoonlijk door; de ontvanger kan het onder Mijn profiel wijzigen) en een rol. Wie via
Authentik inlogt, krijgt automatisch een account; de rol volgt dan uit de Authentik-groep. Alle
wijzigingen aan accounts komen in het logboek, wachtwoorden zelf nooit.

### Rollen & rechten

Boven staan de rollen met hun Authentik-groep, rang en aantal gebruikers. Systeemrollen
(Begeleider, Bestuur, Penningmeester, Admin) kun je niet verwijderen of hernoemen; de
Authentik-groep wel aanpassen. Met **+ Nieuwe rol toevoegen** maak je een extra rol (naam,
Authentik-groep, rang 1 of 2); die begint met alle rechten uit. Een rol verwijderen kan alleen
als er geen beheerders meer aan gekoppeld zijn.

De **rechtenmatrix** toont per onderdeel en per rol de vinkjes Lezen, Bewerken en Verwijderen.
Bewerken of verwijderen zet automatisch ook lezen aan. Admin staat vast op alles. Klik op
**Rechten opslaan** om de hele matrix in één keer op te slaan. Let op bij het onderdeel
"Loondossiers (ID, bankpas, loonheffing)": dat bevat BSN en identiteitsbewijzen en hoort alleen
bij de penningmeester en admin. Hetzelfde geldt voor "Logboek": daarin staat wie welke
persoonsgegevens heeft ingezien.

---

## Privacy en AVG-verzoeken

**Waar:** het detailpaneel van de sollicitant, Beheer › Instellingen › Bewaartermijnen (AVG)  
**Wie:** bestuur en admin; instellingen alleen wie Instellingen mag bewerken.

### Inzage en export

Vraagt een sollicitant (of ouder) welke gegevens er zijn vastgelegd, open dan het dossier, klik
bij Beter leren kennen op **Volledig** en gebruik **Dossier (PDF)** of **Excel**. De export
bevat de persoonsgegevens, het BLK-formulier, de beschikbaarheid in gewone taal, welke
documenten getekend zijn (datum, door wie, hoe geverifieerd) en de status van het loondossier.
BSN, IBAN, kopieën van het identiteitsbewijs en de inhoud van de gezondheidsverklaring zitten er
nooit in. Getekende documenten geef je via **Bekijk** bij Ondertekeningen; de sollicitant en de
ouder hebben die ook zelf per mail ontvangen.

### Verwijderen

Op verzoek van de sollicitant: open het dossier en klik onderaan op **Verwijder sollicitatie**.
Dat verwijdert de sollicitatie van dit seizoen met het BLK-formulier, de beoordeling, de
beschikbaarheid, de taken en de geüploade documenten. Het persoonsaccount (naam, e-mail) blijft
bestaan, zodat iemand later opnieuw kan solliciteren; wil de persoon ook dat weg hebben, overleg
dan met de technisch beheerder. De verwijdering komt in het logboek.

Heeft de persoon een **verwerkt loondossier**, dan wordt het verwijderen geweigerd: het
loonheffingsformulier en de ID-kopie vallen onder de wettelijke bewaarplicht van vijf jaar na
het einde van het dienstjaar. Verwijderen kan dan alleen met de bevestiging dat het
dienstverband niet is doorgegaan; daarvoor is op dit moment geen knop in het scherm, dus schakel
de technisch beheerder in.

Een sollicitant kan zichzelf ook terugtrekken via het portaal. Het bestuur krijgt dan de melding
"Sollicitant heeft zich teruggetrokken".

### Bewaartermijnen

Onder Instellingen › Bewaartermijnen (AVG) zet je **Automatisch opschonen inschakelen** aan;
zolang dat uitstaat wordt er niets automatisch verwijderd. De opschoning draait dagelijks.
Aangenomen kandidaten worden nooit automatisch verwijderd of geanonimiseerd. Een termijn op 0
betekent: die categorie niet opschonen (behalve bij gezondheidsgegevens, zie tabel).

| Categorie | Standaard | Wat er gebeurt |
|---|---|---|
| Afgewezen sollicitanten | 6 maanden | Persoonsgegevens worden geanonimiseerd; de statistiek blijft. |
| Gezondheidsgegevens | 0 | 0 = verwijderen zodra de sollicitatie is afgewezen; anders zoveel maanden na ondertekening. Het feit dát er getekend is blijft. |
| AI-invoertekst | 3 maanden | De vrije beschikbaarheidstekst die naar de AI-dienst ging wordt gewist. |
| Niet-voltooide aanmeldingen | 3 maanden | Aanmeldingen die in Aangemeld bleven staan zonder formulier of beschikbaarheid worden verwijderd. |
| Berichtenlog | 12 maanden | Logregels verwijderen. |
| Inhoud van berichten | 3 maanden | Alleen de tekst wordt leeggemaakt; onderwerp en samenvatting blijven. |
| Losse bijlagen | 12 maanden | Bestanden die aan geen sjabloon meer hangen. |
| Bezoekersstatistiek landingspagina's | 13 maanden | Aantallen verwijderen. |
| Herkenning unieke bezoekers | 30 dagen | Het dag-pseudoniem wissen; aantallen blijven. |
| Logboek | 12 maanden | Auditregels verwijderen. |
| Loondocumenten (loonheffing en ID-kopie) | 5 jaar na einde dienstjaar | Wettelijke bewaarplicht; daarna vernietigd. |
| Kopie bankpas | 30 dagen na verwerking | Vernietigd nadat de penningmeester het dossier op verwerkt heeft gezet. |

Het opschoon-logboek onderaan toont per run wat er is opgeruimd.

### Wat je nooit per mail doorstuurt

Stuur nooit een BSN, een kopie identiteitsbewijs, een bankpas of de inhoud van een
gezondheidsverklaring per e-mail door, ook niet intern. Verwijs de penningmeester naar
Loondossiers en laat het vinkje "loondossier-stukken als bijlage meesturen" uit.
Gezondheidsverklaringen bekijk je alleen in het beheer (bestuur en admin). Print alleen wat de
administratie echt nodig heeft en bewaar het afgeschermd.

---

## Systeem en foutenlog

**Waar:** Beheer › Instellingen › Systeem & Updates en Beheer › Instellingen › Foutenlog  
**Wie:** alleen admin.

### Systeem & Updates

Bovenaan de geïnstalleerde versie, **Diagnose draaien** (opent een technische controlepagina) en
**Migraties uitvoeren**. Het systeem controleert en herstelt de database normaal gesproken zelf
bij elk bezoek; klopt er na een update toch iets niet, klik dan op deze knop.

**Systeemgezondheid** toont acht controles met een groen vinkje, een oranje waarschuwing of een
rood kruis:

| Controle | Betekenis |
|---|---|
| Database | Zijn alle tabellen en kolommen aanwezig? Ontbreekt er iets, dan maakt **Controleren & repareren** het aan. |
| Cron (automatische taken) | Wanneer draaiden de automatische taken (mailwachtrij, herinneringen, opschoning) voor het laatst. Hoort elke vijf minuten; langer dan 30 minuten geleden is een waarschuwing, langer dan 2 uur een fout. Controleer dan de cronjob bij de hoster (de regel staat onder Instellingen › Mail & herinneringen). |
| Mailwachtrij | Blijven er mails langer dan een uur hangen? Dan draait de cron niet of klopt de mailserver niet. |
| AVG-opschoning | Is de opschoning de laatste drie dagen gedraaid? |
| Mappen & rechten | Kan het systeem bestanden wegschrijven (uploads)? |
| Configuratie | Zijn de basiszaken ingesteld (cron-token, bestuurs-e-mailadres)? |
| Kluis | Kan het systeem gevoelige documenten versleuteld opslaan? Zonder werkende kluis zijn uploads van ID en bankpas en het loonheffingsformulier geblokkeerd. Een waarschuwing over de documentenmap is voor de technisch beheerder. |
| Zegel | Kan het systeem PDF's digitaal verzegelen, en hoe lang is het certificaat nog geldig? Bij een fout worden getekende PDF's zonder zegel gemaakt. |

**Herinneringen**: de laatste automatische run, **Proefdraaien (niets versturen)** en **Nu
draaien** (verstuurt via de wachtrij). Daaronder de **Database**-tabelstatus per groep, een link
naar het **Logboek** en de **Changelog** met per versie wat er veranderde.

### Foutenlog

De laatste 200 technische fouten, nieuwste bovenaan, met foutcode, tijdstip, het bericht, het
bestand en de regel, het adres en de gebruiker. Elke fout heeft een **ref**-nummer dat ook op de
foutpagina aan de gebruiker wordt getoond: vraag dat nummer als iemand een foutmelding meldt,
dan vind je hier de oorzaak. Fouten worden ook gemaild naar het adres onder Instellingen › Mail
& herinneringen. Met **Log legen** wis je het hele foutenlog.

### Testomgeving

Op de testomgeving staat linksboven in de zijbalk het oranje label **TESTOMGEVING**. Alles wat
je daar doet is oefenen; mails gaan wel echt naar de ingevulde adressen, dus gebruik daar geen
adressen van echte sollicitanten. Zie je dat label niet, dan werk je in productie.

---

## Veelgestelde vragen

**Een sollicitant zegt dat hij geen inlogcode krijgt.**  
De code gaat per e-mail (type "Portaal-inlogcode") en is 15 minuten geldig. Controleer eerst het
e-mailadres in het dossier: een tikfout is de meest voorkomende oorzaak. Kijk daarna in
Berichten › Berichtenlog, filter op type "Portaal-inlogcode": staat de regel op verstuurd, dan
zit de mail waarschijnlijk in de spam. Staat hij op mislukt of wacht, dan is er iets mis met de
mailserver of de cron (zie Systeem & Updates). Per adres kan maar één code per minuut worden
aangevraagd en per bezoeker een beperkt aantal per uur; laat de sollicitant even wachten en niet
blijven klikken. Heeft de sollicitant een verenigingsaccount, dan logt hij in via Authentik en
krijgt hij geen code.

**De ouder-mail is naar een verkeerd adres gegaan.**  
Zie [Een verkeerd ouder-mailadres](#een-verkeerd-ouder-mailadres): zet in het dossier met **+
Gezondheid (ouder)** en **+ Intake (ouder)** nieuwe documenten klaar op het juiste adres, of
kopieer de link van het openstaande document en stuur die zelf.

**WhatsApp komt niet aan.**  
Controleer onder Instellingen › SMS, WhatsApp & meldingen of WhatsApp is ingeschakeld en stuur
een testbericht naar je eigen nummer. Kijk in het berichtenlog naar de status en de foutmelding;
een sterretje betekent dat het bericht is teruggevallen op sms of e-mail. Controleer in het
dossier of de kanaalvoorkeur van de sollicitant op WhatsApp staat en of er een geldig mobiel
nummer is. Boven het ingestelde maximum per uur valt het systeem terug op sms of e-mail. Bedenk
dat WhatsReach buiten de officiële WhatsApp-koppeling om werkt; een geblokkeerd nummer is een
reëel risico.

**Hoe zet ik iemand terug naar een eerdere fase zonder mail?**  
Terugzetten naar Aangemeld, Selectiedag geweest of TW geweest stuurt nooit een mail: gewoon de
fase-knop in het dossier gebruiken. Wil je iemand opnieuw op Uitgenodigd SD, Uitgenodigd TW,
Aangenomen of Afgewezen zetten zonder bericht, zet dan eerst het kanaal van dat meldingstype op
Uit (Instellingen › SMS, WhatsApp & meldingen), wijzig de fase en zet het kanaal terug. Binnen
een uur na een eerdere verzending gaat dezelfde melding sowieso niet nog een keer.

**Ik heb per ongeluk iemand uitgenodigd.**  
Zet de fase in het dossier terug naar Aangemeld (dat stuurt geen mail) en bel of mail de persoon
persoonlijk. De uitnodiging zelf kun je niet terughalen. De klaargezette documenten blijven
staan; dat is niet erg.

**De sollicitant is 17 en wordt tijdens het seizoen 18. Wie tekent?**  
De leeftijd op het moment van tekenen bepaalt het. Is de sollicitant bij de uitnodiging nog 17,
dan tekent de ouder de gezondheidsverklaring en het intakeformulier. Het loonheffingsformulier
en het lidmaatschap tekent de sollicitant altijd zelf. Het dossier toont een oranje label als
iemand rond een leeftijdsgrens zit.

**Iemand is 15 en wil meedoen.**  
De minimumleeftijd voor het aanmeldformulier staat onder Instellingen › Algemeen (standaard 15);
de leeftijdsgrenzen voor de uitnodigingssuggestie staan onder Uitnodiging & automatisering
(standaard 16). Het dossier laat zien wanneer iemand 16 wordt. Dit zijn bewust twee aparte
grenzen: de aanmeldleeftijd en de tekengrens van 18 zijn juridisch verschillende dingen.

**Een mail is "mislukt". Wat nu?**  
Open de regel in het berichtenlog; de foutmelding staat erbij. Meestal is het een verkeerd adres
of een probleem met de mailserver. Controleer Instellingen › E-mailserver (SMTP) met een
testmail en kijk in Systeem & Updates of de cron en de mailwachtrij in orde zijn. Is het adres
fout, verbeter het dan in het dossier en stuur het bericht opnieuw via Berichten.

**Kan ik een bericht aan iedereen in een fase sturen?**  
Ja. Ga naar Berichten, vink bij Ontvangers de hele fase aan (het vinkje bij de fasekop), schrijf
het bericht of kies een eigen sjabloon en klik op Verzenden. Bij meer dan tien ontvangers gaat
het via de wachtrij.

**Waar zie ik wie een document heeft gedownload of een dossier heeft bekeken?**  
In het Logboek (alleen admin). Filter op de persoon in de kolom Wie, of klik in een regel op het
object om alles van dat dossier te zien. Downloads uit het loondossier staan ook bij het
document zelf ("Laatst gedownload").

**De selectiedag-tablet zegt "Geen geldige toegangscode".**  
De link op de tablet hoort bij een oude code. Kopieer onder Selectiedag › Inchecken de huidige
tablet-link opnieuw naar de tablet, of maak een nieuwe code en verspreid die link.

**Een sollicitant zegt dat hij niet in de groepsindeling staat.**  
Alleen sollicitanten in de fases Uitgenodigd SD, Aangemeld SD en Selectiedag geweest doen mee.
Controleer de fase in het dossier. Staat hij wel goed maar zonder groep, dan staat hij in de
kolom "Nog niet ingedeeld": sleep hem naar een groep.

**De dekking is rood terwijl er genoeg mensen zijn.**  
Dekking kijkt naar competenties uit Authentik en de rollen onder Instellingen › Posten &
bezetting. Controleer of Authentik gekoppeld is, of de sollicitant in het dossier de juiste
competenties heeft (Ververs of handmatig koppelen) en of de rol niet een te hoge minimale rang
vraagt. Klik op de week: bij "Waarom tekort" staat precies welke rol niet te vervullen is en wie
wel gekwalificeerd is maar niet beschikbaar.

**Het seizoen staat open, maar het formulier op de website werkt niet.**  
Controleer onder Instellingen › Website & API of er een API-sleutel is en of die in de
WordPress-plugin staat. Op een landingspagina moet bovendien de markering voor het formulier in
het ontwerp staan en de status op Gepubliceerd; zie de handleiding Landingspagina's.

**Ik zie een onderdeel niet in het menu.**  
Dan heeft je rol daar geen leesrecht op. Vraag een admin om je rol te controleren onder
Beheerders, of het recht aan te passen onder Rollen & rechten. Loondossiers is bewust alleen
voor penningmeester en admin; Logboek, Beheerders en Systeem alleen voor admin.

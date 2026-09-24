# Handleiding beheer — Sollicitatiesysteem Paal 13

Versie 2.102 · september 2026

Het sollicitatiesysteem regelt de hele werving van strandwachten voor Reddingsbrigade Paal 13:
van de aanmelding op de website, via de selectiedag en het trainingsweekend, tot de aangenomen
strandwacht met een compleet loondossier. Sollicitanten volgen hun eigen voortgang in het
portaal; het bestuur overziet de hele lichting in het beheer (`/admin/`).

Deze handleiding is twee dingen tegelijk:

- **Een inwerkgids.** Nieuw als bestuurslid, begeleider of beheerder? Begin bij
  [Aan de slag](#aan-de-slag) en lees daarna [Het seizoen in stappen](#het-seizoen-in-stappen):
  wat je wanneer moet doen. In het beheer lopen de **rondleidingen** met je langs de echte
  schermen (vraagteken rechtsboven › Alle rondleidingen).
- **Naslag.** Elk hoofdstuk begint met een blok **In het kort**: wat je op dat scherm doet, in een
  paar zinnen. Daaronder de details. Waar het systeem iets automatisch doet (een bericht sturen,
  taken klaarzetten, gegevens opruimen), staat dat er expliciet bij.

In het beheer lees je deze handleiding met een zoekveld en een inhoudsopgave die meeloopt; het
vraagteken op elk scherm brengt je naar het juiste hoofdstuk, en bij veel hoofdstukken staat de
knop **Laat zien op het scherm**. Voor de penningmeester, de sollicitant, de wervingspagina's en
het technisch beheer zijn er aparte handleidingen.

## Inhoud
1. [Aan de slag](#aan-de-slag)
2. [Het seizoen in stappen](#het-seizoen-in-stappen)
3. [Inloggen en rollen](#inloggen-en-rollen)
4. [Vandaag](#vandaag)
5. [Seizoen opzetten](#seizoen-opzetten)
6. [Wervingspagina's](#wervingspaginas)
7. [Sollicitanten](#sollicitanten)
8. [Het dossier](#het-dossier)
9. [De fases en wat er automatisch gebeurt](#de-fases-en-wat-er-automatisch-gebeurt)
10. [Selectiedag](#selectiedag)
11. [Trainingsweekend](#trainingsweekend)
12. [Ondertekeningen](#ondertekeningen)
13. [Beschikbaarheid, dekking en loonplanning](#beschikbaarheid-dekking-en-loonplanning)
14. [Loondossiers](#loondossiers)
15. [Berichten sturen](#berichten-sturen)
16. [Sjablonen](#sjablonen)
17. [Geplande berichten en herinneringen](#geplande-berichten-en-herinneringen)
18. [Seintje bij de volgende werving](#seintje-bij-de-volgende-werving)
19. [Instellingen](#instellingen)
20. [Beheerders en rechten](#beheerders-en-rechten)
21. [Logboek](#logboek)
22. [Privacy en AVG-verzoeken](#privacy-en-avg-verzoeken)
23. [Bewaartermijnen](#bewaartermijnen)
24. [Systeem en foutenlog](#systeem-en-foutenlog)
25. [Veelgestelde vragen](#veelgestelde-vragen)
26. [Woordenlijst](#woordenlijst)

---

## Aan de slag

**Waar:** het beheer op `/admin/`, na het inloggen kom je op Vandaag.  
**Wie:** iedereen die net begint als bestuurslid, begeleider, penningmeester of beheerder.

> **In het kort:** het sollicitatiesysteem regelt de hele werving van strandwachten, van
> aanmelding tot aangenomen. Jij werkt in het beheer; de sollicitant werkt in het portaal. Begin
> op Vandaag: daar staat altijd de ene taak die nu telt. Hulp zit rechtsboven onder het vraagteken.

### Het systeem in vijf zinnen

1. Iemand meldt zich aan via de website, een wervingspagina of wordt door het bestuur handmatig
   toegevoegd; daarmee staat die in fase Aangemeld van het huidige seizoen.
2. Het bestuur nodigt uit voor de selectiedag; het systeem zet dan de formulieren klaar, stuurt de
   uitnodiging en herinnert automatisch wie nog iets moet doen.
3. Op de selectiedag checken kandidaten in, worden ze beoordeeld, en daarna besluit het bestuur
   wie door mag naar het trainingsweekend en belt iedereen persoonlijk.
4. Na het trainingsweekend neemt het bestuur aan of wijst af; wie is aangenomen krijgt in het
   portaal de taken voor het loondossier en het lidmaatschap.
5. Aan het eind van het seizoen gaat het aanmeldformulier dicht en ruimt het systeem gegevens
   op volgens de bewaartermijnen. Alleen loondocumenten vernietigt de penningmeester zelf, na de
   wettelijke bewaarplicht.

Elke stap vooruit heet een **fase**. Welke fases er zijn en wat er bij elke overgang automatisch
gebeurt, staat in [De fases en wat er automatisch gebeurt](#de-fases-en-wat-er-automatisch-gebeurt).
De volgorde van het jaar staat in [Het seizoen in stappen](#het-seizoen-in-stappen).

### De rollen

Je rol hangt af van de groep waar je in Authentik in zit. Het menu toont alleen wat jouw rol mag
lezen: een onderdeel zonder recht is weg, niet uitgegrijsd. Dit zijn de standaardrechten; een
beheerder kan ze per rol aanpassen (zie [Beheerders en rechten](#beheerders-en-rechten)).

| Rol (zo staat hij onderin de zijbalk) | Authentik-groep | Wat je doet | Wat je in het menu ziet |
|---|---|---|---|
| **Begeleider** | Sol-Begeleider | Op de selectiedag inchecken en beoordelen (cijfers en observaties), in het trainingsweekend aanwezigheid afvinken en observaties noteren. Geen toegang tot persoonsgegevens of dossiers. | Vandaag, Selectiedag, Trainingsweekend, Dekking |
| **Bestuur** | Bestuur | De hele werving: uitnodigen, selectiedag indelen en beoordelen, nabespreken en bellen, aannemen, berichten, seizoen en instellingen. | Alles behalve Loondossiers, Beheerders & rechten en Logboek |
| **Penningmeester** | Sol-Penningmeester | Het loondossier: loonheffing, kopie ID en bankpas inzien, downloaden, als verwerkt markeren en na de bewaarplicht vernietigen. De loonplanning bekijken en bewerken (kosten, scenario's, CSV). Sollicitanten alleen bekijken. | Vandaag, Sollicitanten, Loonplanning, Loondossiers |
| **Beheerder** (technisch: admin) | Sol-Admin | Alles, ook beheerders, rechten, logboek en systeem. Kan zichzelf nooit buitensluiten. | Alles |

Goed om te weten over de begeleider: met de standaardrechten (Selectiedag: alleen lezen) kan een
begeleider op de selectiedag de groepen bekijken, kandidaten inchecken (**Aanwezig** en
**Ongedaan**), cijfers geven en wissen, observaties plaatsen en de eigen observaties verwijderen.
In het trainingsweekend vinkt een begeleider af en observeert. De toegangscode voor de tablet, de
groepsindeling, de nabespreking, de bellijst en het besluit na het trainingsweekend blijven bij het
bestuur. Bijzonderheden uit het kennismakingsformulier (vaak medisch) ziet een begeleider niet; wel
de melding "Let op: er is een bijzonderheid bekend bij het bestuur" met het verzoek het na te
vragen. De knop naar het dossier en telefoonnummers ziet alleen wie Sollicitanten mag lezen.

### Je eerste keer inloggen

1. Vraag een beheerder om je in Authentik in de juiste groep te zetten (zie de tabel hierboven).
2. Ga naar het adres van het beheer (`/admin/`) en klik op **Inloggen via Authentik (SSO)**.
3. Log in bij Authentik. Je komt terug op **Vandaag**.
4. Kijk linksonder in de zijbalk: daar staan je naam, je rol en hoe je bent ingelogd (bijvoorbeeld
   "Bestuur · SSO"). Klopt de rol niet, meld het dan bij een beheerder.
5. Klik op je naam en controleer op **Mijn profiel** je **Weergavenaam**. Die naam staat bij je
   observaties en besluiten, zodat anderen zien wie wat zei.
6. Zet daar desgewenst **Donker thema** aan (per apparaat onthouden).

Krijg je de melding "Je Authentik-account heeft geen admin-toegang", dan zit je nog niet in een
groep die toegang geeft. Zie [Inloggen en rollen](#inloggen-en-rollen).

### Hulp vinden

- **Het vraagteken** rechtsboven op een scherm opent een korte uitleg van dat scherm, met
  **Lees meer in de handleiding** (het juiste hoofdstuk hiervan) en **Alle rondleidingen**.
- **Rondleidingen.** In het beheer zitten interactieve rondleidingen die op het echte scherm
  aanwijzen wat waar staat en wat je ermee doet. Op een scherm met een rondleiding staat in het
  vraagteken-paneel **Rondleiding van dit scherm**. Een route langs meerdere schermen voor jouw rol
  start je vanuit **Hulp en handleidingen**, onderin het menu (je komt er ook via **Alle
  rondleidingen** in het vraagteken-paneel). De eerste keer staat op Vandaag ook een blok
  **Nieuw in het beheer?** met de rondleiding die bij jouw rol past.
  In een rondleiding blader je met **Volgende** en **Vorige** of met de pijltjestoetsen, en stop je
  met **Stoppen** of **Esc**.
- **De zoeker** in de topbalk (knop **Zoeken**, of **Ctrl K**): typ minstens twee letters. Je
  vindt mensen op naam of e-mailadres (over alle seizoenen, het huidige seizoen eerst) en
  schermen, ook instellingen-tabbladen. Met de pijltjes kies je, met Enter open je.
- **Deze handleiding** staat onder **Hulp en handleidingen**, samen met die voor de penningmeester,
  voor sollicitanten, voor de wervingspagina's en (alleen voor beheerders) het runbook voor het
  technisch beheer. Je leest ze daar met een zoekveld; van elke handleiding is er ook een
  Word-versie om af te drukken of uit te delen. Afvinklijsten zoals hieronder kun je in het beheer
  echt aanvinken; dat wordt op je eigen apparaat onthouden.
- **Het verwerkingsregister** (AVG artikel 30) staat ook onder **Hulp en handleidingen**, als
  **Verwerkingsregister (AVG)**. Daarin staat per onderdeel welke persoonsgegevens we verwerken,
  waarom, met wie en hoe lang. Kijk daar als iemand vraagt "wat doen jullie met mijn gegevens?" of
  als je een datalek moet beoordelen.

### Checklist: je eerste week als bestuurslid

- [ ] Log in en controleer je rol en weergavenaam ([Je eerste keer inloggen](#je-eerste-keer-inloggen)).
- [ ] Lees [Het seizoen in stappen](#het-seizoen-in-stappen), zodat je weet wat er wanneer van je
   verwacht wordt.
- [ ] Open [Vandaag](#vandaag) en lees de seizoensbalk en de taak die er nu staat.
- [ ] Open [Seizoen](#seizoen-opzetten) en controleer of de datums van de selectiedag, het
   trainingsweekend en de werkperiode kloppen.
- [ ] Open [Sollicitanten](#sollicitanten), klik de fase-tabs langs en open één dossier
   ([Het dossier](#het-dossier)). Kijk vooral naar de kaart Taken: zo ziet de sollicitant het ook.
- [ ] Bekijk bij [Sjablonen](#sjablonen) de uitnodiging voor de selectiedag, zodat je weet wat
   kandidaten van ons krijgen.
- [ ] Lees [De fases en wat er automatisch gebeurt](#de-fases-en-wat-er-automatisch-gebeurt) voordat
   je voor het eerst op een uitnodigknop drukt.
- [ ] Kijk op [Selectiedag](#selectiedag) en [Trainingsweekend](#trainingsweekend) hoe de schermen er
   op de dag zelf uitzien; ze zijn gemaakt voor tablet en telefoon.

### Checklist: je eerste week als beheerder

Alles van het bestuurslid, plus:

- [ ] Controleer onder [Instellingen](#instellingen) › Authentik welke groepen toegang geven.
   Standaard staat daar "Bestuur,Sol-Admin,Sol-Begeleider,Sol-Penningmeester". Controleer dat
   **Sol-Penningmeester** in de toegestane groepen staat; heeft iemand de lijst eerder zelf
   aangepast, dan kan die groep ontbreken.
- [ ] Loop bij [Beheerders en rechten](#beheerders-en-rechten) na wie er toegang heeft en welke rol
   bij welke Authentik-groep hoort. Staat de penningmeester (of iemand met een eigen rol) als
   Begeleider in de lijst, zet de rol dan eenmalig goed.
- [ ] Heb je een wachtwoord als noodingang? Stel dan in **Mijn profiel** › **Tweestapsverificatie**
   een authenticator-app in en bewaar de herstelcodes offline, bijvoorbeeld op papier in de kluis
   van het bestuur, niet in je mail of op dezelfde telefoon. Zie
   [Wachtwoord als noodingang](#wachtwoord-als-noodingang).
- [ ] Kijk op [Vandaag](#vandaag) of de systeemstatus op "Systeem in orde" staat en de taakronde
   recent heeft gedraaid; zo niet, zie [Systeem en foutenlog](#systeem-en-foutenlog).
- [ ] Zet bij [Bewaartermijnen](#bewaartermijnen) het automatisch opschonen aan. Het staat
   standaard **uit**, en dan klopt de belofte in de afwijzing ("zonder seintje verwijderen we je
   gegevens binnen vier weken") niet. Zolang het uit staat, is de systeemstatus op Vandaag rood.
   Controleer eerst de termijnen: bij de eerste run verdwijnt alles wat al over de termijn is, en
   dat is niet terug te draaien.
- [ ] Bekijk het [Logboek](#logboek) één keer, zodat je weet waar je moet zoeken als iemand vraagt
   wat er met een dossier is gebeurd.
- [ ] Lees [Privacy en AVG-verzoeken](#privacy-en-avg-verzoeken): een verzoek om inzage of
   verwijdering komt meestal bij jou terecht.

---

## Het seizoen in stappen

**Waar:** Vandaag (de seizoensbalk bovenaan), en per stap het genoemde scherm.  
**Wie:** bestuur en beheerder; de penningmeester vanaf het aannemen.

> **In het kort:** een seizoen loopt in vaste momenten, van voorbereiden tot opruimen. Per moment
> staat hieronder wat jij moet doen, wat het systeem zelf doet en op welk scherm. De seizoensbalk
> op Vandaag toont zes fases (Werving, Formulieren, Selectiedag, Bellijst, Trainingsweekend,
> Aanname) en rekent zelf uit waar je bent aan de hand van de datums en of het seizoen open is.

Hoe Vandaag de seizoensfase bepaalt:

| Seizoensfase op Vandaag | Wanneer |
|---|---|
| Werving | Het seizoen staat open en de selectiedag is nog niet geweest |
| Formulieren | Het seizoen is gesloten en de selectiedag moet nog komen |
| Selectiedag | Op de datum van de selectiedag zelf |
| Bellijst | Na de selectiedag, tot de eerste dag van het trainingsweekend |
| Trainingsweekend | Vanaf de eerste dag van het trainingsweekend tot en met de laatste dag |
| Aanname | Na de laatste dag van het trainingsweekend |

### Moment 1: voorbereiden, vóór de opening

| Wat jij doet | Wat het systeem doet | Scherm |
|---|---|---|
| Het vorige seizoen sluiten als dat nog openstaat, daarna een nieuw seizoen aanmaken. | Het nieuwe seizoen begint gesloten. Wie eerder solliciteerde, wordt bij aanmelding herkend als terugkerend. | [Seizoen](#seizoen-opzetten) |
| Werkperiode, hoofdseizoen, selectiedag en trainingsweekend invullen (datum, tijden, adres, wat meenemen). | De datums komen in de mails en in het portaal; herinneringen rekenen ermee. | [Seizoen](#seizoen-opzetten) |
| De sjablonen nalezen, vooral de uitnodigingen. Beslissen of de afwijzingsmail aan moet (staat standaard uit). | | [Sjablonen](#sjablonen), [Instellingen](#instellingen) |
| De herinneringen en de uitnodig-modus controleren (Uit, Suggestie of Automatisch). | | [Geplande berichten en herinneringen](#geplande-berichten-en-herinneringen), [Instellingen](#instellingen) |
| Een wervingspagina maken of bijwerken. | Telt bezoekers anoniem en meet per stap van het formulier waar mensen afhaken. | [Wervingspagina's](#wervingspaginas) |
| (Beheerder) Bewaartermijnen aanzetten en controleren. | | [Bewaartermijnen](#bewaartermijnen) |

### Moment 2: aanmelding open

| Wat jij doet | Wat het systeem doet | Scherm |
|---|---|---|
| Op **Seizoen openen** klikken en bevestigen met **Ja, openen**. | Het aanmeldformulier (website, wervingspagina's, WordPress-plugin) gaat aan. Het formulier heeft drie korte stappen: naam, een paar gegevens, contact. | [Seizoen](#seizoen-opzetten) |
| Aanmeldingen bekijken en uitnodigen (zie moment 3). | Elke aanmelder krijgt de mail "Bevestiging van aanmelding"; het bestuur krijgt "Nieuwe aanmelding". Vandaag toont "... aanmeldingen uitnodigen". | [Vandaag](#vandaag), [Sollicitanten](#sollicitanten) |
| | Staat de uitnodig-modus op Automatisch, dan nodigt het systeem zelf uit wie binnen de leeftijdsgrenzen valt (standaard 16 tot en met 30), na de ingestelde vertraging (standaard 24 uur). | [Instellingen](#instellingen) |
| | Staan er in het portaal taken open, dan gaat 5 dagen na de laatste fasewijziging de herinnering "Openstaande taken", daarna elke 7 dagen, maximaal 3 keer. | |

### Moment 3: uitnodigen voor de selectiedag

| Wat jij doet | Wat het systeem doet | Scherm |
|---|---|---|
| Kandidaten uitnodigen: per rij **Uitnodigen SD**, in het dossier **Uitnodigen selectiedag**, of meerdere tegelijk via de bulkbalk. | Zet de ondertekeningen klaar (18+: gezondheidsverklaring en intake als taak; jonger dan 18: wacht op de oudergegevens en mailt dan de ouder). Stuurt "Uitnodiging selectiedag" per e-mail en WhatsApp/sms. | [Sollicitanten](#sollicitanten) |
| De aanmelding sluiten zodra je genoeg kandidaten hebt: **Seizoen sluiten**. | Vandaag springt naar "Formulieren" en telt wie het kennismakingsformulier nog niet inleverde. | [Seizoen](#seizoen-opzetten) |
| | Wie het formulier "Beter leren kennen" inlevert, gaat vanzelf naar Aangemeld selectiedag en krijgt een bevestiging. | |
| | Herinnering "Nog niet aangemeld voor de selectiedag": alleen als de selectiedag binnen 14 dagen is, 3 dagen na de uitnodiging, daarna elke 4 dagen, maximaal 3 keer. | |
| | Herinnering aan minderjarigen die de oudergegevens nog niet invulden: na 2 dagen, elke 4 dagen, maximaal 3 keer. | |
| | Herinnering aan wie een document nog niet tekende: na 3 dagen, elke 5 dagen, maximaal 2 keer. | [Ondertekeningen](#ondertekeningen) |
| | Een sollicitant krijgt nooit meer dan één van deze herinneringen per 3 dagen. | |
| Een paar dagen vooraf: groepen indelen en de toegangscode voor de tablet aanmaken. | 3 dagen vóór de selectiedag gaat "Selectiedag nadert", de dag ervoor "Selectiedag morgen". | [Selectiedag](#selectiedag) |

### Moment 4: de selectiedag zelf

| Wat jij doet | Wat het systeem doet | Scherm |
|---|---|---|
| De tablet bij de ingang klaarzetten met de tablet-link. | Vandaag toont "Vandaag is de selectiedag" met de knop **Naar inchecken**. | [Selectiedag](#selectiedag) |
| Inchecken (of kandidaten checken zelf in op de tablet). | Wie wordt ingecheckt, gaat meteen naar fase Selectiedag geweest. Geen bericht. | [Selectiedag](#selectiedag) |
| Beoordelen: cijfers per criterium en observaties. | Tikken is opslaan; het systeem berekent per criterium het gemiddelde van alle beoordelaars. | [Selectiedag](#selectiedag) |

### Moment 5: nabespreking en bellen

| Wat jij doet | Wat het systeem doet | Scherm |
|---|---|---|
| Per kandidaat een besluit nemen: Door, Twijfel of Niet. | Een besluit verandert nog niets aan de fase en stuurt niets. | [Selectiedag](#selectiedag) |
| Iedereen met Door of Niet persoonlijk bellen en daarna doorvoeren. | Door wordt Uitgenodigd trainingsweekend met de uitnodiging (e-mail en WhatsApp/sms). Niet wordt Afgewezen; de afwijzingsmail staat standaard uit. De afgewezen sollicitant ziet in het portaal de knop voor een seintje bij de volgende werving. | [Selectiedag](#selectiedag) |
| Twijfelgevallen later zelf afhandelen. | Twijfel blijft staan; die gaat nooit vanzelf de deur uit. | |
| | De uitnodiging voor het trainingsweekend vraagt de beschikbaarheid in te vullen. De deadline daarvoor is 14 dagen vóór het trainingsweekend. Vanaf 14 dagen vóór die deadline gaat de herinnering "Beschikbaarheid nog voorlopig" (elke 4 dagen, na de deadline elke 2 dagen, maximaal 5 keer). | [Beschikbaarheid, dekking en loonplanning](#beschikbaarheid-dekking-en-loonplanning) |
| | 3 dagen vóór het trainingsweekend gaat "Trainingsweekend nadert". | |

### Moment 6: het trainingsweekend

| Wat jij doet | Wat het systeem doet | Scherm |
|---|---|---|
| Tablet klaarzetten (toegangscode en pincode) en nalopen wie er komt. | Sollicitanten checken zelf in; dat zet de fase op TW geweest. | [Trainingsweekend](#trainingsweekend) |
| Observaties noteren op je telefoon (bestuur en begeleiders). | Tikken is opslaan; er gaat geen bericht uit. | [Trainingsweekend](#trainingsweekend) |

### Moment 7: aannemen

| Wat jij doet | Wat het systeem doet | Scherm |
|---|---|---|
| In het gesprek met het bestuur per kandidaat de inzet afspreken en een besluit vastleggen (Aannemen, Twijfel, Afwijzen), met een bericht aan de sollicitant. Het gaat meteen door. | Aangenomen: het kennismakingspakket gaat klaar in het portaal (upload identiteitsbewijs en bankpas, loonheffingsformulier, inschrijving als lid als er nog geen verenigingsaccount is, huisregels, verenigingsaccount) en de mail "Welkom: aangenomen" gaat uit. Afgewezen: zoals bij moment 5. | [Trainingsweekend](#trainingsweekend) |
| Twijfelgevallen bellen en daarna het gesprek opnieuw openen en het besluit vastleggen. | | [Trainingsweekend](#trainingsweekend) |
| Na het weekend de loonplanning definitief maken. | Pas dan ziet de sollicitant de afgesproken inzet. | [Beschikbaarheid, dekking en loonplanning](#beschikbaarheid-dekking-en-loonplanning) |

### Moment 8: loondossiers

| Wat jij doet | Wat het systeem doet | Scherm |
|---|---|---|
| (Penningmeester) Complete dossiers verwerken en als verwerkt markeren. | Zodra loonheffing, kopie ID en kopie bankpas binnen zijn, krijgt de penningmeester "Loondossier compleet", ongeacht wat het laatst binnenkwam. De kopie bankpas wordt 30 dagen na verwerking vernietigd (als automatisch opschonen aanstaat). Loonheffing en ID blijven staan tot de penningmeester ze na de bewaarplicht vernietigt. | [Loondossiers](#loondossiers) |
| (Bestuur) Openstaande handtekeningen nalopen. | De inschrijving als lid gaat na ondertekening naar de ledenservice. | [Ondertekeningen](#ondertekeningen) |

### Moment 9: seizoen sluiten en opruimen

| Wat jij doet | Wat het systeem doet | Scherm |
|---|---|---|
| Het seizoen sluiten als dat nog niet gebeurd is. Alle gegevens blijven staan. | | [Seizoen](#seizoen-opzetten) |
| (Beheerder) Controleren dat automatisch opschonen aanstaat. | Eén keer per dag ruimt de taakronde op volgens de ingestelde termijnen, onder meer: afgewezen sollicitanten zonder seintje 4 weken na de afwijzing; een seintje vervalt na maximaal 12 maanden; het dossier van oud-strandwachten 2 jaar na hun laatste seizoen. Loondocumenten ruimt het systeem niet zelf op. | [Bewaartermijnen](#bewaartermijnen) |
| (Penningmeester, jaarlijks) Op Loondossiers de kaart **Te beoordelen voor vernietiging** nalopen. | Toont wie hier minstens vijf volle kalenderjaren niet meer voorkwam. | [Loondossiers](#loondossiers) |
| Volgend jaar: wie om een seintje vroeg en oud-strandwachten benaderen. | | [Seintje bij de volgende werving](#seintje-bij-de-volgende-werving), [Berichten sturen](#berichten-sturen) |

---

## Inloggen en rollen

**Waar:** het inlogscherm "Beheerders login" op `/admin/`, daarna de zijbalk links.  
**Wie:** iedereen met een beheerdersaccount.

> **In het kort:** je logt in via Authentik; je rol komt uit je Authentik-groep. Linksonder in
> de zijbalk zie je wie je bent, je rol en hoe je bent ingelogd. Daar vind je ook Mijn profiel
> (weergavenaam, wachtwoord, tweestapsverificatie, donker thema), Mijn sollicitatie en Uitloggen.
> Inloggen met een wachtwoord vraagt altijd een tweede stap.

### Inloggen via Authentik

Klik op **Inloggen via Authentik (SSO)**, log in bij Authentik en je komt terug op het scherm
binnen het beheer waar je heen wilde, of op Vandaag. Een adres buiten het beheer volgt het systeem
na het inloggen nooit.

Twee instellingen bepalen of en hoe je binnenkomt:

- **Welke groepen toegang geven** staat onder Instellingen › Authentik (standaard
  "Bestuur,Sol-Admin,Sol-Begeleider,Sol-Penningmeester"). Zit je in geen van die groepen, dan zie
  je "Je Authentik-account heeft geen admin-toegang". Dat geldt ook als je eerder al een account
  had: wie in Authentik uit alle toegestane groepen wordt gehaald, komt niet meer binnen via SSO.
  Zo'n geweigerde poging staat in het logboek als "Inloggen via Authentik geweigerd".
- **Welke groep welke rol krijgt** staat bij Beheerders & rechten (standaard Sol-Admin =
  Beheerder, Bestuur = Bestuur, Sol-Penningmeester = Penningmeester, Sol-Begeleider = Begeleider).
  Zit je in meerdere groepen, dan wint de hoogste rol. Bij elke login wordt je rol opnieuw uit
  Authentik overgenomen. Zit je wel in een toegestane groep, maar is die aan geen rol gekoppeld,
  dan blijft de rol staan die een beheerder voor je koos; een nieuw account krijgt dan de rol
  Begeleider.

### Wachtwoord als noodingang

Onder de SSO-knop staat "of met wachtwoord": **E-mailadres**, **Wachtwoord** en **Inloggen**. Dit
is voor als Authentik niet bereikbaar is en werkt alleen voor accounts waar een beheerder een
wachtwoord voor heeft gezet (zie [Beheerders en rechten](#beheerders-en-rechten)).

Een juist wachtwoord is nog niet genoeg. Je komt op het scherm **Tweede stap**:

- **Heb je een authenticator-app ingesteld** (in Mijn profiel, zie hieronder), vul dan bij **Code
  uit je app** de code van 6 cijfers in die de app bij "Paal13 Beheer" toont, en klik op
  **Inloggen**. Telefoon kwijt? Klap **Telefoon kwijt? Gebruik een herstelcode** open, vul een van
  je herstelcodes in en klik op **Inloggen met herstelcode**. Elke herstelcode werkt één keer; na
  gebruik zie je hoeveel er nog over zijn.
- **Heb je geen app ingesteld**, dan krijg je een code per e-mail ("Je inlogcode voor het Paal 13
  beheer"). Vul die in bij **Code uit de e-mail** en klik op **Inloggen**. De code is 10 minuten
  geldig en werkt één keer. Geen mail gekregen? Klik op **Stuur een nieuwe code** (hooguit één per
  minuut en vijf per uur).
- Met **Annuleren** ga je terug naar het gewone inlogscherm.

De tweede stap moet binnen 5 minuten na je wachtwoord. Duurt het langer, dan zie je "Je
inlogpoging is verlopen" en log je opnieuw in. Na te veel mislukte pogingen, met het wachtwoord of
met een code, wordt inloggen tijdelijk geblokkeerd; je ziet hoeveel minuten je moet wachten. Bij
te veel foute codes zie je "Te veel codepogingen. Wacht een kwartier en log dan opnieuw in."
Mislukte pogingen en foute codes komen in het logboek.

Een code per e-mail is minder veilig dan een app: wie je wachtwoord én je mailbox heeft, komt
binnen. Stel daarom een app in als je een wachtwoord hebt. Log je alleen via Authentik in, dan
regelt Authentik de tweede stap en hoef je hier niets te doen.

### De zijbalk

Bovenaan staat **Vandaag**, daaronder vier groepen:

| Groep | Onderdelen |
|---|---|
| Kandidaten | Sollicitanten, Selectiedag, Trainingsweekend, Ondertekeningen |
| Inzet & loon | Beschikbaarheid, Dekking, Loonplanning, Loondossiers |
| Communicatie | Berichten, Wervingspagina's |
| Systeem | Seizoen, Instellingen, Beheerders & rechten, Logboek |

Een groep waar je niets in mag, verdwijnt helemaal. Een getal achter een menu-item betekent dat
er iets op je wacht: bij Vandaag het aantal openstaande stappen van de huidige taak, bij
Ondertekeningen het aantal documenten dat langer dan 3 dagen openstaat. Op de testomgeving staat
bovenin de zijbalk het label **Testomgeving**. Op een telefoon open je het menu met de knop met
drie streepjes linksboven.

In de topbalk staan de titel van het scherm, de zoeker, het huidige seizoen met **Open** of
**Gesloten**, en het vraagteken.

### Onderin: jij

Linksonder staan je naam, je rol en hoe je bent ingelogd, bijvoorbeeld "Beheerder · SSO" of
"Bestuur · wachtwoord". De rolnaam admin heet hier **Beheerder**. Daaronder:

- **Mijn sollicitatie**: alleen als je zelf ook een sollicitatie hebt. Hiermee wissel je naar
  het portaal.
- **Uitloggen**.

Klik op je naam voor **Mijn profiel**:

- **Mijn gegevens**: e-mailadres, rol, hoe je inlogt (Authentik en/of Wachtwoord) en wanneer je
  het laatst inlogde. Met **Naam opslaan** wijzig je je **Weergavenaam**; die staat bij je
  observaties en besluiten.
- **Wachtwoord**: alleen als je account een wachtwoord heeft. Minstens 8 tekens; **Wachtwoord
  wijzigen**. Log je alleen via Authentik in, dan wijzig je je wachtwoord in Authentik.
- **Tweestapsverificatie**: alleen als je account een wachtwoord heeft. Het label **Tweestaps
  aan** of **Tweestaps uit** zegt hoe het nu staat. Instellen gaat zo:
  1. Klik op **Instellen**.
  2. Open een authenticator-app op je telefoon (bijvoorbeeld Microsoft Authenticator, Google
     Authenticator, 2FAS of je wachtwoordmanager), kies "account toevoegen" en neem de sleutel
     over die op het scherm staat. Op je telefoon zelf kan het ook met de link **open in je
     authenticator-app**. Deel die sleutel met niemand.
  3. Vul bij **Code uit je app** de code in die de app nu toont en klik op **Bevestigen en
     aanzetten**. Klopt de code niet, controleer dan of de tijd op je telefoon automatisch wordt
     ingesteld.
  4. Je ziet nu acht **herstelcodes**. Dit is de enige keer dat je ze ziet. Schrijf ze op of print
     ze en bewaar ze offline, niet in je mail of op dezelfde telefoon. Klik daarna op **Ik heb ze
     bewaard**.

  Staat tweestaps aan, dan zie je sinds wanneer en hoeveel herstelcodes je nog hebt; bij twee of
  minder krijg je een waarschuwing. Nieuwe telefoon of nieuwe herstelcodes nodig? Vul je **Huidig
  wachtwoord** en een code uit je app (of een herstelcode) in en klik op **Opnieuw instellen**.
  Met **Uitzetten** zet je de app uit; inloggen met je wachtwoord vraagt dan weer een code per
  e-mail. Werkt de kluis niet, dan kun je de app niet instellen; meld dat bij de technisch
  beheerder.
- **Voorkeuren**: de schakelaar **Donker thema**. Dit wordt per apparaat onthouden, niet op je
  account. Zonder keuze volgt het beheer de instelling van je apparaat.

---

## Vandaag

**Waar:** Vandaag, bovenaan in de zijbalk (het startscherm na het inloggen).  
**Wie:** iedereen. Het blok "Waar staat iedereen" alleen met leesrecht op Sollicitanten.

> **In het kort:** Vandaag opent op een vraag: wat moet er nu gebeuren? Bovenaan zie je waar het
> seizoen staat, daaronder de ene taak die telt met een knop ernaartoe, en pas dan de cijfers en
> de systeemstatus. Kom hier elke keer dat je inlogt eerst.

### De seizoensbalk

Zes fases naast elkaar: Werving, Formulieren, Selectiedag, Bellijst, Trainingsweekend, Aanname.
Wat geweest is, is groen; waar het seizoen nu staat, is oranje; wat nog komt, is grijs. Hoe de
fase wordt bepaald, staat in [Het seizoen in stappen](#het-seizoen-in-stappen). Is er nog geen
seizoen, dan staat hier "Er is nog geen seizoen" met de knop **Seizoen aanmaken**.

### De taak van nu

Een groot blok met "Te doen" of "Niets te doen". Welke taak er staat, hangt af van de
seizoensfase:

| Seizoensfase | De taak | Knop |
|---|---|---|
| Werving | "... aanmeldingen uitnodigen" (fase Aangemeld) | **Naar de aanmeldingen** |
| Formulieren | "... wachten nog op hun formulier" (fase Uitgenodigd selectiedag), met hoe de automatische herinnering nu is ingesteld | **Bekijk wie** |
| Selectiedag | "Vandaag is de selectiedag" | **Naar inchecken** |
| Bellijst | "... kandidaten zonder besluit" zolang er nog niemand op de bellijst staat, daarna "De bellijst afwerken" met hoeveel er nog niet gebeld, gebeld maar niet doorgevoerd of op twijfel staan | **Naar de nabespreking** of **Bellijst openen** |
| Trainingsweekend | "Het trainingsweekend", met hoeveel kandidaten nog niet zijn afgevinkt | **Naar het trainingsweekend** |
| Aanname | "... wachten op een besluit", daarna "... besluiten nog doorvoeren" en "... op twijfel" (uit het trainingsweekend) | **Naar het besluit** |

Naast de taak staan blokjes en "x gedaan · y open". Is er niets te doen, dan zegt het blok dat
ook, bijvoorbeeld "De bellijst is leeg" of "De lichting is rond". "De lichting is rond" verschijnt
pas als op het trainingsweekend iedereen een besluit heeft en alles is doorgevoerd. Het getal bij
Vandaag in het menu is het aantal open stappen van deze taak. Een knop staat er alleen als je dat
scherm ook mag openen.

### Waar staat iedereen

Een staaf per fase met het aantal kandidaten in dit seizoen. Klik op een staaf om de
sollicitantenlijst met die fase te openen. Zolang er niemand is, staat er "Nog niemand
aangemeld".

### Loopt ondertussen

Dingen die je moet weten maar nu niet hoeft te doen, elk met een link:

- "... ondertekeningen staan langer dan 3 dagen open" (naar [Ondertekeningen](#ondertekeningen)).
- "... beschikbaarheden zijn nog voorlopig" (naar Beschikbaarheid).

Het blok verschijnt alleen als er iets loopt.

### De systeemstatus

Een balk met twee standen:

- **Systeem in orde** (groen): het aantal berichten in de wachtrij, mailfouten in 24 uur,
  ondertekeningen die langer dan 3 dagen openstaan en het tijdstip van de laatste taakronde.
- **Aandacht nodig** (rood, met een waarschuwingsteken): er zijn mailfouten in de laatste 24 uur,
  er staan meer dan 20 berichten in de wachtrij, de taakronde heeft meer dan twee uur niet
  gedraaid ("taakronde vastgelopen" of "taakronde nooit gedraaid"), of automatisch opschonen staat
  uit ("Automatisch opschonen staat uit — er wordt niets verwijderd"). Met **Bekijk het logboek**
  ga je naar de fouten; die knop zie je alleen als je het logboek mag lezen (standaard alleen een
  beheerder). Staat opschonen uit en mag je Instellingen bewerken, dan staat er ook de knop
  **Opschonen aanzetten**. Zie [Systeem en foutenlog](#systeem-en-foutenlog) en
  [Bewaartermijnen](#bewaartermijnen).

---

## Seizoen opzetten

**Waar:** Systeem › Seizoen (titel van het scherm: Seizoenen)  
**Wie:** bestuur en beheerder. Een ander seizoen heropenen of sluiten en een seizoen verwijderen:
alleen de beheerder.

> **In het kort:** hier maak je één keer per jaar een seizoen aan, vul je de datums in en zet je
> het open zodra aanmelden mag. Het blok "Waar sta je?" rechts laat zien welke stap nu aan de
> beurt is. Openen, sluiten en verwijderen gaan altijd via een tussenscherm dat zegt wat er gebeurt.

### Welk seizoen is het huidige?

Het hele beheer werkt met één seizoen tegelijk: het seizoen dat **open** staat. Staat er geen
seizoen open, dan is het nieuwste seizoen het huidige. De topbalk toont altijd welk seizoen dat
is, en dit scherm opent ook op dat seizoen, met het label **huidig**. Een ander seizoen bewerk je
via **Bewerken** in de lijst "Alle seizoenen"; bovenaan staat dan "Je bewerkt ..." met welk
seizoen de rest van het beheer gebruikt.

Sluit het oude seizoen bij voorkeur voordat je een nieuw seizoen aanmaakt. Staat er nog een
seizoen open, dan waarschuwt het formulier "Nieuw seizoen aanmaken" daarvoor: je kunt het nieuwe
seizoen alvast aanmaken en de datums invullen, maar het beheer en het aanmeldformulier blijven
met het open seizoen werken tot je dat sluit en het nieuwe opent. Open je een tweede seizoen
terwijl het oude nog open staat, dan zegt het tussenscherm dat het beheer met het nieuwste open
seizoen gaat werken.

### Stap voor stap

1. **Nieuw seizoen aanmaken.** Vul bij "Nieuw seizoen aanmaken" een **Naam** in (bijvoorbeeld
   "Zomer 2027") en klik op **Aanmaken**. Het seizoen begint gesloten; gegevens van vorige
   seizoenen blijven staan. Je komt meteen in het nieuwe seizoen terecht om de datums in te vullen.
2. **Datums invullen** in de kaart "... instellen" en klik op **Opslaan**:
   - **Werkperiode**: **Eerste werkdag** en **Laatste werkdag**. Sollicitanten zien in het portaal
     elke week daartussen als tegel. Zonder werkperiode kunnen ze hun beschikbaarheid niet invullen.
   - **Hoofdseizoen vanaf week** en **Hoofdseizoen tot en met week**. Daarbuiten zijn de posten
     alleen in het weekend bemand en rekent de dekking daarmee. Leeg is: elke week hoofdseizoen.
   - **Selectiedag**: **Datum**, **Locatie**, **Verzameltijd** (hoe laat kandidaten er moeten
     zijn), **Start programma**, **Eindtijd (ongeveer)** (het portaal toont "tot ongeveer ..."),
     **Adres** en **Wat neem je mee**.
   - **Trainingsweekend**: **Start**, **Eind**, **Locatie**, **Verzameltijd**, **Start programma**,
     **Eindtijd (ongeveer)**, **Adres** en **Wat neem je mee**.
3. **Openen.** Klik in de kaart bovenaan op **Seizoen openen**. Het tussenscherm zegt dat het
   aanmeldformulier meteen actief wordt en vraagt je de datums te controleren (die staan in de
   bevestigingsmail van elke aanmelder). Klik op **Ja, openen**. Met **Aanmeldformulier bekijken**
   open je het formulier in een nieuw tabblad.
4. **Procedure doorlopen** bij [Sollicitanten](#sollicitanten), [Selectiedag](#selectiedag) en
   [Trainingsweekend](#trainingsweekend).
5. **Sluiten.** Klik op **Seizoen sluiten** en bevestig met **Ja, sluiten**. Het aanmeldformulier
   gaat uit; alle gegevens blijven bewaard en je kunt later weer openen.

### Waar sta je?

Rechts staan vijf stappen: Seizoen aangemaakt, Datums ingevuld, Seizoen geopend, Procedure
doorlopen, Seizoen gesloten. Een vinkje betekent af; de eerste stap die nog niet af is, krijgt het
label **nu**. "Datums ingevuld" is pas af als selectiedag, begin trainingsweekend en eerste
werkdag zijn ingevuld.

### Wat de sollicitant ervan ziet

Zodra de sollicitant is uitgenodigd, staat in het portaal een kaart met datum, verzameltijd,
starttijd, "tot ongeveer" de eindtijd, locatie met adres en "Wat neem je mee"; hetzelfde voor het
trainingsweekend. Dezelfde gegevens zijn als variabelen in mails te gebruiken (zie
[Sjablonen](#sjablonen)). Zolang een datum leeg is, ziet de sollicitant "Nog niet ingesteld" of
geen kaart.

### Alle seizoenen en verwijderen

De lijst "Alle seizoenen" toont per seizoen het aantal sollicitaties, de datum van de selectiedag
en de werkperiode, en het label **huidig** bij het seizoen waarmee het beheer werkt. Bij elk
seizoen dat je nu niet bewerkt staat **Bewerken**; bij het seizoen dat open staat op dit scherm
"je bewerkt dit seizoen". Een beheerder ziet daarnaast **Sluiten** of **Heropenen** (niet bij het
seizoen dat je bewerkt) en **Verwijderen**. Anderen zien "Een seizoen verwijderen of heropenen kan
alleen een beheerder."

Verwijderen is onomkeerbaar. Het tussenscherm noemt precies wat er verdwijnt: alle sollicitaties
van dat seizoen met hun formulieren, beoordelingen van de selectiedag, beschikbaarheid en
ondertekeningen. Er is geen prullenbak. Wil je de gegevens bewaren, sluit het seizoen dan in
plaats van het te verwijderen. De knop heet **Ja, verwijder alles** (of **Ja, verwijderen** als
het seizoen leeg is).

---

## Wervingspagina's

**Waar:** Communicatie › Wervingspagina's  
**Wie:** bestuur en beheerder.

> **In het kort:** een wervingspagina is een eigen pagina voor één doelgroep met het
> aanmeldformulier erin, op `/p/<adres>`. Je uploadt een HTML-ontwerp, controleert het rapport en
> zet de pagina op Gepubliceerd. Per pagina zie je bezoekers, aanmeldingen en waar mensen afhaken.
> De volledige uitleg staat in de aparte handleiding voor wervingspagina's.

### Het overzicht van de pagina's

- **Nieuwe pagina**: vul **Titel** en **Adres** in (kleine letters, cijfers en koppeltekens) en
  klik op **Aanmaken**. Je gaat door naar het beheerscherm van de pagina.
- Per pagina: de status (**Concept**, **Alleen beheerders** of **Gepubliceerd**), het label
  **Geen ontwerp** zolang er nog niets is geüpload, het adres, en de cijfers **Bezoekers**,
  **Bezoeken**, **Aanmeldingen** en **Omzetting**. Met **Beheren** open je de pagina, met
  **Bekijken** de publieke versie.
- **Waar komen aanmeldingen vandaan?**: per bron het aantal aanmeldingen, hoeveel daarvan zijn
  aangenomen, en het aandeel.

In het beheerscherm van een pagina staat onder meer "Waar haken mensen af?": per stap van het
aanmeldformulier hoeveel mensen verder gingen, en los daarvan "Halverwege weggegaan". Elke pagina
heeft een bron-code die meereist tot in het dossier. De pagina zet geen cookies; bezoeken worden
anoniem geteld. Die telling zet je uit onder Instellingen › Bewaren & opschonen.

Het ontwerp maken, de markeringen zoals `[[formulier]]`, afbeeldingen, het scanrapport, versies en
problemen oplossen staan in de **Handleiding wervingspagina's** (overzicht Handleidingen).

---

## Sollicitanten

**Waar:** Kandidaten › Sollicitanten  
**Wie:** bestuur en beheerder (bekijken, bewerken, verwijderen); penningmeester alleen bekijken.
Begeleiders hebben hier geen toegang.

> **In het kort:** de lijst van alle kandidaten in het huidige seizoen. Kies een fase-tab, filter
> of zoek, en klik op een naam om het dossier te openen. De knop in de kolom Actie zet de volgende
> stap; met vinkjes doe je hetzelfde voor meerdere mensen tegelijk. Elke stap die bericht stuurt,
> laat eerst zien wat er gaat gebeuren.

### Fase-tabs

Bovenaan staat **Alle** met het totaal, daarna een tab per fase met het aantal: Aangemeld,
Uitgenodigd SD, Aangemeld SD, SD geweest, Uitgenodigd TW, TW geweest, Aangenomen, Afgewezen. Een
tab zonder mensen verdwijnt. Staat iemand in een fase die in deze periode van het seizoen nog niet
aan de beurt is (bijvoorbeeld al aangenomen terwijl de selectiedag nog moet komen, na een import of
correctie), dan staat onder de tabs "... in een fase die nu niet aan de beurt is" met de link
**bekijken**.

### Filterbalk

- **Zoek naam of e-mail**: filtert terwijl je typt.
- **Alle kandidaten** of **Alleen terugkerend**.
- **Formulier: alle**, **Formulier ingediend** of **Formulier open** (het kennismakingsformulier;
  een concept telt als open).
- **Competentie** (alleen als de koppeling met Authentik is ingericht).
- **Wis filters** verschijnt zodra er een filter aanstaat.
- Rechts: **Toevoegen** (handmatig een sollicitant invoeren) en **Exporteren** (het hele seizoen
  als Excel-bestand). Zonder het recht op de kennismakingsformulieren heet de knop **Exporteren
  (basis)**: dan staan adres en formuliervelden er niet in.

### Kolommen kiezen en sorteren

Standaard zie je Naam (met e-mailadres eronder), Lft, Woonplaats, Status, Aangemeld en Actie. In
de kolom Status staat de fase, het label **terug** bij een terugkerende kandidaat, en een
waarschuwing als er iets mis is ("mail mislukt" of "geen adres"; houd je muis erop voor de reden).
Staat de uitnodig-modus op Suggestie, dan krijgt een aanmelding die binnen de leeftijdsgrenzen valt
het groene label **Uitnodigen?**. Dat is een voorstel; uitnodigen doe je zelf. Afgewezen
kandidaten staan gedempt.

Met **Kolommen** zet je extra kolommen aan of uit: Telefoon en de velden uit het
kennismakingsformulier (Straat, Huisnr., Postcode, Opleiding, Werkervaring, Diploma's, Motivatie,
Waarom strand, Beschikbaar, Uitzonderingen, Social media, Bijzonderheden en Formulier, de datum
van indienen). Adresvelden en Bijzonderheden zie je alleen met het recht op de
kennismakingsformulieren. Een kolom die je aanzet, wordt opgehaald zodra je het lijstje sluit. Je
keuze blijft staan tot het einde van het seizoen.

Klik op een kolomkop om te sorteren. Onderaan kies je 15, 25, 50 of 100 regels per pagina en blader
je met **Vorige** en **Volgende**. Open je vanuit de lijst een dossier, dan volgen **← Vorige** en
**Volgende →** in het dossier dezelfde sortering.

### De kolom Actie

Per fase is er hooguit één knop, voor iedereen dezelfde:

| Fase | Knop |
|---|---|
| Aangemeld | **Uitnodigen SD** |
| SD geweest | **Uitnodigen TW** |
| TW geweest | **Aannemen** |

In de andere fases wacht het systeem op de sollicitant of op de selectiedag, en is er dus niets
te doen. Staat er een probleem bij de kandidaat, dan staat ervoor **Adres corrigeren** of **Adres
invullen** (houd je muis erop voor de reden). Die knop opent het dossier met de kaart
[Gegevens corrigeren](#gegevens-corrigeren) open. De kolom Actie ontbreekt als je geen bewerkrecht
hebt.

Een knop in de kolom Actie opent eerst het tussenscherm (zie hieronder).

### Meerdere tegelijk: de bulkbalk

1. Vink de rijen aan, of gebruik het vinkje in de kop voor alle rijen op deze pagina.
2. Onderaan verschijnt een donkere balk met "... geselecteerd" en de knoppen **Uitnodigen
   selectiedag**, **Uitnodigen trainingsweekend**, **Aannemen**, **Afwijzen**, **Bericht** en
   **Exporteren**. Een uitnodigknop verschijnt alleen als iedereen in je selectie in de juiste fase
   staat (Aangemeld voor de selectiedag, SD geweest voor het trainingsweekend).
3. Klik op een knop. Bij een fasestap zie je eerst het tussenscherm; **Bericht** opent Berichten
   met deze mensen als ontvangers; **Exporteren** downloadt alleen de gekozen mensen.

### Het tussenscherm

Voordat een fase verandert, laat het beheer een scherm zien met:

- de vraag, bijvoorbeeld "3 mensen uitnodigen voor de selectiedag?";
- of er automatisch bericht uitgaat, of dat die melding uitstaat (bij afwijzen: "Het bestuur belt
  afwijzingen zelf");
- dat een verstuurd bericht niet terug te nemen is;
- de namen van wie het betreft (de eerste acht);
- het vinkje **Zonder bericht doorvoeren**: de fase verandert wel, maar er gaat niets de deur uit.
  Gebruik dit als je ze zelf belt of het al besproken is.

Klik op **Ja, uitnodigen**, **Ja, aannemen** of **Ja, afwijzen**, of op **Annuleren**. Daarna zie
je een samenvatting: hoeveel zijn bijgewerkt, hoeveel stonden al in die fase, hoeveel berichten zijn
verstuurd, overgeslagen (al verstuurd of geen adres) of mislukt. Bij meer dan tien mensen gaan de
berichten via de wachtrij en worden ze de komende minuten verstuurd.

Een melding dat iets gelukt is, verdwijnt na vijf seconden. Een fout of waarschuwing blijft staan
tot je verder gaat, zodat je hem kunt lezen.

### Handmatig toevoegen

Via **Toevoegen**. Verplicht zijn **Voornaam**, **Achternaam**, **E-mailadres** en **Seizoen**
(standaard het huidige seizoen); verder **Tussenvoegsel**, **Telefoonnummer**, **Geboortedatum**,
**Geslacht** en de **Beginfase**. Tekst bij **Interne notities** komt als notitie in het dossier.
Het vinkje **Stuur welkomstmail naar de sollicitant (met portaallink)** staat standaard aan en
stuurt de bevestiging van aanmelding. Klik op **Toevoegen**; daarna opent het dossier van de nieuwe
sollicitant.

Goed om te weten:

- Een latere beginfase dan Aangemeld doet hetzelfde als een fasewijziging in het dossier: het
  systeem zet de formulieren of het kennismakingspakket klaar en stuurt het bijbehorende bericht.
  Wil je dat bericht niet, vink dan **Geen bericht bij de beginfase** aan.
- Bestaat het e-mailadres al, dan komt de sollicitatie bij die persoon en telt die als
  terugkerend. Heeft die persoon in dit seizoen al een sollicitatie, dan zie je een melding en wordt
  er niets gewijzigd. Een leeg veld wist geen bekend telefoonnummer of geboortedatum.
- Wie handmatig is toegevoegd, heeft zelf nog geen privacytoestemming gegeven; in het dossier
  staat dan "Nog niet gegeven".

### Importeren

Alleen voor de beheerder. Het scherm "Sollicitanten importeren" (zoek op "importeren", of ga naar
`/admin/import.php`) leest een CSV-bestand in, zoals geëxporteerd uit het oude systeem: naam,
e-mail, telefoon, geboortedatum, geslacht, beschikbaarheid per week, motivatie, ervaring en
diploma's. Kies **Seizoen**, **Beginfase** en het **CSV bestand** en klik op **Import starten**.
Dubbele e-mailadressen worden overgeslagen, dus opnieuw inlezen is veilig. Na afloop zie je hoeveel
personen zijn aangemaakt, bijgewerkt en overgeslagen, plus eventuele fouten per rij.

---

## Het dossier

**Waar:** klik in Sollicitanten (of in de zoeker, of op een ander scherm) op een naam.  
**Wie:** bestuur en beheerder; de penningmeester alleen bekijken.

> **In het kort:** alles over één sollicitant op een eigen pagina met een eigen adres (te delen en
> als bladwijzer op te slaan). Bovenaan wie het is en de volgende stap; daaronder tabbladen.
> Het eerste tabblad, Dossier, geeft alles wat je nodig hebt om te beslissen. Met Vorige en
> Volgende loop je door de lijst waar je vandaan kwam.

### Bovenaan: kruimelpad en de kop

- Het **kruimelpad** "Sollicitanten / naam" brengt je terug naar de lijst, met je filters nog aan.
  Kwam je uit een lijst met meer mensen, dan staat er "4 van 12 in ..." met **← Vorige** en
  **Volgende →**. Die volgen de filters en de sortering van de lijst.
- De **kop** toont foto of initialen, naam, fase, het label **Eerder gesolliciteerd** bij een
  terugkerende kandidaat, en leeftijd, woonplaats, e-mail en telefoon. Een oranje melding
  waarschuwt rond de leeftijdsgrens van 16: "Wordt pas 16 op ..." of "Was 15 bij de start van het
  seizoen, inmiddels 16".
- De **knoppen** in de kop (alleen met bewerkrecht): de volgende stap (**Uitnodigen
  selectiedag**, **Uitnodigen trainingsweekend** of **Aannemen**, afhankelijk van de fase),
  **Bericht** (opent Berichten met deze persoon) en **Bellen** (als er een telefoonnummer is).

De knop voor de volgende stap opent eerst hetzelfde tussenscherm als in de lijst: je ziet wat er
gebeurt en of er bericht uitgaat, en je kunt **Zonder bericht doorvoeren** aanvinken. Pas na
**Ja, uitnodigen** of **Ja, aannemen** gebeurt het.

### De tabbladen

Een tabblad dat niets te tonen heeft, verschijnt niet.

| Tabblad | Wat erin staat |
|---|---|
| Dossier | Het complete beeld (zie hieronder). Altijd aanwezig. |
| Kennismakingsformulier | Het formulier "Beter leren kennen" voluit. Alleen met het recht op de kennismakingsformulieren en als er een formulier is. |
| Beschikbaarheid | De weken die de sollicitant kan werken. |
| Selectiedag | Cijfers en observaties van de selectiedag, met het aantal observaties. |
| Ondertekeningen | De documenten, met "x/y" getekend. |
| Tijdlijn | Alles wat er met dit dossier is gebeurd. Altijd aanwezig. |

### Tabblad Dossier, links: lezen

- **Gegevens**: Geboortedatum, Aangemeld, **Berichten via** (WhatsApp, Sms of Alleen e-mail; met
  bewerkrecht kies je hier een ander kanaal, bijvoorbeeld als iemand geen WhatsApp wil; de wijziging
  wordt opgeslagen en gelogd), Adres, **Toestemming privacy** ("Gegeven" met datum, of "Nog niet
  gegeven") en **Ouderlijke toestemming** ("Nodig — tekent via een ouder" onder de 18, anders
  "Niet nodig — 18 of ouder"). Het volledige adres zie je alleen met het recht op de
  kennismakingsformulieren; zonder dat recht staat er alleen de **Woonplaats**.
- **Gegevens corrigeren** (met bewerkrecht): een uitklapper om naam, e-mail, telefoon of
  geboortedatum te verbeteren. Zie [Gegevens corrigeren](#gegevens-corrigeren).
- **Advies**: zodra er cijfers zijn, een advies Door, Twijfel of Niet met de reden ("Score ... ligt
  boven de drempel van 3,5"), en een balk per test. Het label zegt het al: advies, geen besluit.
  Is er een besluit uit de nabespreking, dan staat dat eronder.
- **Competenties** (alleen met de Authentik-koppeling): de vaardigheden uit Authentik, met
  **Synchroniseren** om ze opnieuw op te halen. Is er geen match, dan koppel je handmatig op
  Authentik-gebruikersnaam met **Koppelen**.
- **Uit het kennismakingsformulier**: opleiding, werkervaring, diploma's, bijzonderheden
  (opvallend als er iets staat) en de motivatie, met **Volledig formulier →**. Het label
  **Concept** betekent dat de sollicitant het formulier nog niet heeft ingediend.
- **AI-analyse**: als er een analyse is gemaakt (zonder naam, contactgegevens en bijzonderheden).
- **Sollicitant verwijderen**: zie [Verwijderen](#verwijderen-uit-het-dossier).

### Tabblad Dossier, rechts: doen

- **Fase**: een knop per fase; de huidige heeft een vinkje. Klik op een andere fase om die te
  zetten: je krijgt de vraag "Fase wijzigen naar ...?" met het vinkje **Zonder bericht versturen**
  en de knop **Ja, wijzigen**. Zo zet je ook terug of corrigeer je een fase. Wijkt de stap af van de
  gewone volgorde (terugzetten of een stap overslaan), dan heet de knop **Verder** en volgt de vraag
  "Waarom wijk je af? (optioneel)". Wat je daar typt, komt als notitie in de tijdlijn; leeg laten
  mag. Wat een overgang in gang zet, staat in
  [De fases en wat er automatisch gebeurt](#de-fases-en-wat-er-automatisch-gebeurt).
- **Taken**: de taken precies zoals de sollicitant ze in het portaal ziet, met "x / y afgerond"
  en per taak Afgerond, Open of Wacht op ouder. Bij een open ondertekening staat **Link kopiëren**:
  plak die link in een mail of app als de automatische mail niet aankwam. Onderaan de link naar het
  tabblad Ondertekeningen.
- **Notities**: interne opmerkingen, alleen zichtbaar in het beheer, voor iedereen die het dossier
  mag lezen (ook de penningmeester). Plaatsen vraagt bewerkrecht: typ en klik op **Plaatsen**. Je
  eigen notities kun je bewerken en verwijderen; een beheerder kan dat bij alle notities.
- **Tijdlijn**: de laatste gebeurtenissen.
- **Eerdere seizoenen**: eerdere sollicitaties van dezelfde persoon, met de fase waarin die eindigden.

### Tabblad Kennismakingsformulier

Persoonsgegevens, Achtergrond (met diploma's als labels), Motivatie ("Waarom strandwacht?",
"Waarom juist dit strand?") en Bijzonderheden (rood gemarkeerd als er iets staat), plus social
media. Een leeg veld staat er als "Niet ingevuld". Onderaan opent **Afdrukweergave en
wijzigingsgeschiedenis** het formulier om te printen, met **Naar het dossier**, **Afdrukken**,
**PDF**, **Excel** en, als er een AI-sleutel is ingesteld, het blok **AI Geschiktheidsanalyse** met
**Analyseer nu** (of **Opnieuw analyseren**) en **Bezwaar vastleggen…**; die knoppen vragen
bewerkrecht op Sollicitanten. Een AI-analyse komt in de wachtrij en is binnen enkele minuten
klaar. Naar de AI-dienst gaan geen naam, adres, contactgegevens of foto, en ook niet de
bijzonderheden (die kunnen medisch zijn). Het advies is een hulpmiddel; het bestuur beslist.
Heeft de sollicitant bezwaar gemaakt, dan staat daar dat er geen analyse is en er ook geen komt;
zie [Bezwaar tegen de AI-analyse](#bezwaar-tegen-de-ai-analyse).

### Tabblad Beschikbaarheid

Een tegel per week (W23, W24 ...) met het aantal dagen, en het label **Voorlopig** of
**Definitief**. Groen is de hele week, oranje deels, blauw een correctie van het bestuur, grijs niet
beschikbaar. Klik op een week om die te corrigeren; **Volledig bewerken** opent alle weken. Rechts
staat wat de sollicitant bij de aanmelding als voorkeur opgaf.

In de kaart **Norm** staat hoeveel dagen per week deze persoon werkt: "5 dagen per week
(standaard)" of een afwijkende norm. Met bewerkrecht op Beschikbaarheid kies je bij **Norm (dagen
per week)** een ander aantal (1 tot en met 6), vul je een **Reden** in en klik je op **Norm
opslaan**. Zonder dat recht zijn de weektegels en de norm alleen te bekijken. Zie
[Beschikbaarheid, dekking en loonplanning](#beschikbaarheid-dekking-en-loonplanning).

### Tabblad Selectiedag

Per test de cijfers per criterium met het gemiddelde; bij meerdere beoordelaars staat eronder wie
wat gaf. Daaronder de observaties met beoordelaar, datum en soort. Rechts het totaalgemiddelde en
het besluit van de nabespreking, met wie het nam en wanneer er is gebeld.

### Tabblad Ondertekeningen

Bij een minderjarige een melding met de gegevens van de ouder of verzorger, of de waarschuwing dat
die nog ontbreken ("De ouderformulieren kunnen pas uitgaan zodra er een naam en e-mailadres van een
ouder of verzorger is ingevuld"). Daaronder elk document met **Ondertekend**, **Wacht** of **Wacht
op ouder**. Getekend: datum, naam van de ondertekenaar en **PDF**. Open: **Link kopiëren**. Een
document dat je rol niet mag openen, toont een slotje met wie het wel mag (**Alleen
loondossier** voor loonheffing en lidmaatschap). Zie [Ondertekeningen](#ondertekeningen).

### Tabblad Tijdlijn

Alles wat er met dit dossier is gebeurd, nieuwste bovenaan: fasewijzigingen, berichten, notities.
Rechts de notities en, voor wie het logboek mag lezen, **Alles over deze sollicitant** (het
logboek gefilterd op deze persoon, inclusief wie welk document opende).

### Verwijderen uit het dossier

Onderaan het tabblad Dossier staat het rode blok **Sollicitant verwijderen** (alleen met
verwijderrecht op Sollicitanten). Er zijn twee knoppen:

- **Alleen deze sollicitatie…**: het formulier, de foto, de beschikbaarheid, notities,
  beoordelingen, taken en documenten van deze sollicitatie verdwijnen. Naam, e-mail en telefoon
  blijven bewaard voor andere seizoenen.
- **Alles van deze persoon…**: voor een verwijderverzoek (AVG). Alle sollicitaties van deze persoon
  worden leeggemaakt en naam, e-mail, telefoon, geboortedatum en adres worden vervangen door
  "Verwijderd (verzoek AVG)". Ingelogde apparaten in het portaal worden uitgelogd.

Beide knoppen openen eerst een tussenscherm dat opsomt wat er verdwijnt. Bevestig met **Ja,
verwijderen** of **Ja, alles wissen**. In het berichtenlog verdwijnen de inhoud en het adres van de
berichten. Dit is niet terug te draaien.

Ligt er een verwerkt loondossier, dan staat op het tussenscherm het vinkje **Het dienstverband is
niet doorgegaan**. Vink dat alleen aan als deze persoon nooit heeft gewerkt; dan gaan ook het
loonheffingsformulier en de kopie ID mee. Zonder vinkje wordt er niets verwijderd, want die stukken
vallen onder de wettelijke bewaarplicht. Dit kan alleen wie ook het recht Loondossiers verwijderen
heeft (standaard alleen de beheerder); anderen krijgen een melding en geen tussenscherm. Zie
[Privacy en AVG-verzoeken](#privacy-en-avg-verzoeken).

### Gegevens corrigeren

Klopt de naam, het e-mailadres, het telefoonnummer of de geboortedatum niet, dan pas je dat aan in
de uitklapper **Gegevens corrigeren** op het tabblad Dossier (bewerkrecht op Sollicitanten). Je
komt er ook direct via **Adres corrigeren** of **Adres invullen** in de lijst, en via de link
**Gegevens corrigeren** bij een mislukt bericht.

1. Klap **Gegevens corrigeren** open.
2. Pas **Voornaam**, **Tussenvoegsel**, **Achternaam**, **E-mailadres**, **Telefoon** of
   **Geboortedatum** aan.
3. Klik op **Gegevens opslaan**.

Goed om te weten:

- Een e-mailadres hoort bij één persoon. Staat het adres al bij een ander dossier, dan wordt er
  niets opgeslagen. De sollicitant logt daarna met het nieuwe adres in op het portaal.
- De correctie geldt voor de persoon, dus ook voor eerdere seizoenen.
- Gaat iemand door een nieuwe geboortedatum over de grens van 18 jaar (ondertekenen via een ouder
  of zelf) of onder de 16 jaar (aanmeldleeftijd en AVG-grens), dan zie je na het opslaan een
  waarschuwing. Documenten die al klaarstaan veranderen niet vanzelf; kijk bij Ondertekeningen of
  er iets opnieuw moet.
- In het logboek komt "Gegevens gecorrigeerd" met welke velden zijn gewijzigd, niet het nieuwe
  e-mailadres of nummer.

Een sollicitant kan dit niet zelf aanpassen, maar vraagt het aan via een vraag in Berichten in het
portaal.

---

## De fases en wat er automatisch gebeurt

**Waar:** elke plek waar je een fase zet: de lijst, de bulkbalk, het dossier, de bellijst en het
trainingsweekend.  
**Wie:** bestuur en beheerder.

> **In het kort:** elke fasewijziging loopt via dezelfde route en doet dus altijd hetzelfde. Bij
> uitnodigen, aanmelden voor de selectiedag, aannemen en afwijzen kan er een bericht uitgaan; de
> afwijzingsmail staat standaard uit. Wil je geen bericht, zet dan het vinkje "zonder bericht".
> Welke melding via welk kanaal gaat, stel je in bij Instellingen en Sjablonen.

### De fases

| Fase | Wat het bestuur doet | Wat het systeem doet |
|---|---|---|
| **Aangemeld** | Niets; de aanmelding komt binnen via de website, een wervingspagina of handmatig. | Stuurt "Bevestiging van aanmelding" (e-mail) en aan het bestuur "Nieuwe aanmelding". Bij uitnodig-modus Automatisch nodigt het zelf uit wie binnen de leeftijdsgrenzen valt, na de ingestelde vertraging. |
| **Uitgenodigd selectiedag** | **Uitnodigen SD** (lijst), **Uitnodigen selectiedag** (dossier of bulk). | Zet de ondertekeningen klaar: 18+ gezondheidsverklaring en intake als taak in het portaal; onder de 18 wacht het tot de sollicitant de oudergegevens invult en mailt dan de ouder. Stuurt "Uitnodiging selectiedag" (standaard e-mail én WhatsApp/sms). |
| **Aangemeld selectiedag** | Niets; de sollicitant levert het formulier "Beter leren kennen" in. | Stuurt "Bevestiging aanmelding selectiedag" (e-mail) en aan het bestuur "Formulier ingeleverd" (standaard uit). |
| **Selectiedag geweest** | Inchecken op de selectiedag, of de kandidaat checkt zelf in op de tablet. | Zet de fase bij het inchecken. Geen bericht. |
| **Uitgenodigd trainingsweekend** | Doorvoeren van Door op de bellijst, of **Uitnodigen TW** / **Uitnodigen trainingsweekend**. | Stuurt "Uitnodiging trainingsweekend" (standaard e-mail én WhatsApp/sms) met het verzoek de beschikbaarheid door te geven. |
| **TW geweest** | Met de hand zetten in de fasekaart van het dossier. | Zet de fase. Geen bericht. Het afvinken in het trainingsweekend zet deze fase niet. |
| **Aangenomen** | Doorvoeren bij Trainingsweekend, **Aannemen** in de lijst (vanaf TW geweest), in het dossier of in de bulkbalk. | Zet het kennismakingspakket klaar in het portaal en stuurt "Welkom: aangenomen" (e-mail). Zodra loonheffing, kopie ID en kopie bankpas binnen zijn, krijgt de penningmeester "Loondossier compleet". |
| **Afgewezen** | Doorvoeren van Niet op de bellijst of van Afwijzen bij Trainingsweekend, of **Afwijzen** in de bulkbalk. | Stuurt "Niet geselecteerd", maar die staat standaard **uit**: het bestuur belt of mailt persoonlijk. Onder die mail staat, als hij aan staat, altijd de knop **Stuur me een seintje**. |

### Het seintje bij afwijzing

Een afgewezen sollicitant mag je niet zomaar benaderen bij de volgende werving; daarvoor is los
gegeven toestemming nodig. Die vraagt het systeem op het moment dat het logisch is:

- In het portaal ziet een afgewezen sollicitant de taak "Seintje bij de volgende werving?" met een
  knop, ook als er geen afwijzingsmail is verstuurd. Wie op het seintje klikt, kan het daar ook weer
  intrekken.
- Staat de melding "Niet geselecteerd" aan, dan komt onder de mail een vast blok "Volgend jaar
  weer?" met de knop **Stuur me een seintje**. Dat blok staat los van het sjabloon, dus je raakt
  het niet kwijt als je de tekst aanpast.
- Zonder seintje worden de gegevens van een afgewezen sollicitant (standaard) 4 weken na de
  afwijzing opgeschoond; met seintje blijven naam en contactgegevens maximaal een jaar bewaard.
  Dit werkt alleen als het automatisch opschonen aanstaat (zie [Bewaartermijnen](#bewaartermijnen)).

Hoe je die mensen later benadert, staat in
[Seintje bij de volgende werving](#seintje-bij-de-volgende-werving).

### Zonder bericht doorvoeren

Soms heb je al gebeld of corrigeer je een fase die verkeerd stond. Dan wil je geen standaardbericht.

- In de lijst, de bulkbalk en met de knop in de kop van het dossier: vink op het tussenscherm
  **Zonder bericht doorvoeren** aan.
- In de fasekaart van het dossier: vink in de vraag "Fase wijzigen naar ...?" **Zonder bericht
  versturen** aan.
- Bij handmatig toevoegen: vink **Geen bericht bij de beginfase** aan.

De fase verandert wel, en de neveneffecten (formulieren klaarzetten, kennismakingspakket) gebeuren
ook; alleen het bericht aan de sollicitant blijft achterwege. In het logboek blijft zichtbaar dát
het stil ging. Op de bellijst en bij het trainingsweekend kan dit niet: daar gaat bij doorvoeren
altijd het ingestelde bericht uit.

### Goed om te weten

- **Terugzetten** naar Aangemeld, Selectiedag geweest of TW geweest stuurt nooit een bericht. Alleen
  de overgangen naar Uitgenodigd selectiedag, Aangemeld selectiedag, Uitgenodigd trainingsweekend,
  Aangenomen en Afgewezen kunnen een melding sturen. Bij terugzetten of een stap overslaan in de
  fasekaart kun je een reden opgeven die in de tijdlijn komt.
- **Geen dubbele berichten**: dezelfde melding gaat niet twee keer binnen een uur naar dezelfde
  persoon. Zet je iemand per ongeluk terug en meteen weer vooruit, dan zie je "Bericht al eerder
  verstuurd (geen dubbele)".
- **Na elke overgang** zie je bovenaan wat er met het bericht gebeurde: "Bericht verstuurd via ...",
  "Bericht gaat nu de deur uit via ...", "Bericht staat in de wachtrij.", "Bericht mislukt — zie het
  berichtenlog." of "Geen e-mailadres of telefoonnummer bekend."
- **Automatiseringen uitzetten**: bij Instellingen › Uitnodigen & automatisch kun je het klaarzetten
  van ondertekeningen bij de uitnodiging voor de selectiedag en het kennismakingspakket bij aannemen
  uitzetten.
- **Kanalen**: een melding kan per type op E-mail, WhatsApp/SMS, E-mail + WhatsApp/SMS of Uit staan.
  Mislukt WhatsApp of sms, dan valt het systeem terug op e-mail. De sollicitant kiest zelf WhatsApp,
  Sms of Alleen e-mail; dat zie en wijzig je in het dossier.

---

## Selectiedag

**Waar:** Kandidaten › Selectiedag, met bovenaan de stappen Groepen, Inchecken, Beoordelen,
Nabespreking en Bellijst.  
**Wie:** bestuur en beheerder alles. Een begeleider bekijkt de groepen, checkt in en beoordeelt;
de tabletcode, de groepsindeling, Nabespreking en Bellijst blijven bij het bestuur.

> **In het kort:** vijf stappen voor één dag. Vooraf deel je groepen in en maak je de
> toegangscode voor de tablet. Op de dag check je in (of kandidaten doen dat zelf) en beoordeel je
> per kandidaat. Daarna neem je aan tafel een besluit, bel je iedereen en voer je pas dan door.

De selectiedag werkt met iedereen in de fases Uitgenodigd selectiedag, Aangemeld selectiedag en
Selectiedag geweest. Staat er nog niemand in die fases, dan staat er "Nog niemand uitgenodigd".
Naast de titel staat de datum van de selectiedag.

### Stap 1: groepen indelen

Het beste op een laptop.

1. Vul het **Aantal groepen** in en klik op **Instellen**.
2. Klik op **Voorstel maken** en bevestig met **Ja, opnieuw verdelen**. Het systeem maakt gelijke
   groepen, gemengd op leeftijd en geslacht, en houdt koppels uit elkaar die je hebt aangegeven.
   Let op: dit overschrijft de bestaande indeling, ook wat je zelf verplaatste.
3. **Verplaats** kaartjes tussen de groepen: sleep ze met de muis, of kies op het kaartje bij
   "Naar groep" een groep (of "Nog niet ingedeeld") en klik op **Verplaats**. Die keuzelijst werkt
   ook op een tablet en met alleen het toetsenbord. De kolom "Nog niet ingedeeld" toont wie nog
   geen groep heeft; bovenaan staat "x van y ingedeeld".
4. Klik op een naam om in "Wie mogen niet bij elkaar?" aan te vinken wie deze persoon kent en dus
   liever niet in dezelfde groep zit. Klik op **Klaar**. Zit zo'n koppel toch samen, dan staat op het
   kaartje **botst**.

### Stap 2: inchecken

Gemaakt voor een tablet bij de ingang. Twee manieren, door elkaar te gebruiken:

- **Tablet bij de ingang** (kandidaten melden zichzelf aan):
  1. Een bestuurslid klikt op **Toegangscode aanmaken**. Er verschijnen een **Toegangscode** en een
     **Tablet-link**.
  2. Klik op **Kopiëren** of **Openen** en zet die pagina klaar op de tablets.
  3. De kandidaat typt de eigen naam, tikt zichzelf aan en ziet meteen in welke groep die zit ("Ga
     naar de tafel van je groep"). Met **Volgende persoon** is de tablet klaar voor de volgende. Wie
     al is aangemeld ziet "al aangemeld"; staat iemand er niet in, dan staat er "Vraag het
     organisatieteam om hulp".
  4. **Nieuwe code aanmaken** maakt een nieuwe code; de tablets die openstaan werken daarna niet
     meer en moet je opnieuw openen met de nieuwe link.
- **Aanwezigheidslijst**: per groep een rij per kandidaat met de knop **Aanwezig** (na aantikken
  wordt het **Ongedaan**). De teller "x/y aanwezig" loopt mee; met **Zoek op naam** vind je iemand
  snel. Tikken is opslaan. Ook begeleiders kunnen hier inchecken; de toegangscode aanmaken kan
  alleen het bestuur.

Zodra iemand is ingecheckt, zet het systeem de fase op **Selectiedag geweest**. Wie na afloop niet
is ingecheckt, telt als niet verschenen. Onderaan brengt **Naar beoordelen** je naar stap 3.

### Stap 3: beoordelen

Gemaakt voor een telefoon, met één kandidaat tegelijk. Je werkt onder je eigen weergavenaam.
Bestuur, beheerder en begeleiders kunnen beoordelen; niemand overschrijft de cijfers van een ander.

1. Kies bovenaan eventueel een groep (**Alle** of een groepsnaam). Links (op een telefoon erboven)
   staat de lijst met per kandidaat "nog niet begonnen", "x van y criteria", "klaar" of "niet
   verschenen", en het aantal observaties.
2. Het systeem opent de eerste kandidaat die jij nog niet af hebt. Een waarschuwing toont
   bijzonderheden uit het kennismakingsformulier; een melding zegt het als de kandidaat niet is
   ingecheckt. Zonder het recht op de kennismakingsformulieren (zoals een begeleider) staat daar
   alleen "Let op: er is een bijzonderheid bekend bij het bestuur" met het verzoek bij een
   bestuurslid na te vragen waar je op moet letten.
3. Geef per criterium een cijfer van 1 tot en met 5 door op het cijfer te tikken; er verschijnt
   "opgeslagen". Tik je nog een keer op hetzelfde cijfer, dan wordt het gewist. De criteria staan
   per test (standaard Sporttest en Zwemtest) en stel je in bij Instellingen ›
   Beoordelingscriteria. Je ziet je eigen cijfers; bij meer beoordelaars staat eronder het
   gemiddelde.
4. Een **observatie** plaats je door **Positief**, **Neutraal** of **Aandacht** te kiezen, te typen
   wat opviel en op **Observatie opslaan** te tikken. Observaties mogen over iedereen gaan, ook
   buiten je eigen groep. Je eigen observaties kun je verwijderen; bestuur en beheerder kunnen alle
   observaties verwijderen.
5. Ga verder met **Volgende: naam** of terug met **Vorige**. **Dossier** opent het dossier; die
   knop zie je alleen als je Sollicitanten mag lezen.

### Stap 4: nabespreken

Alleen bestuur en beheerder. Per kandidaat staan observaties en scores bij elkaar, met
bijzonderheden, groep en of de kandidaat aanwezig was. De scores zijn een advies; jullie gesprek
bepaalt het besluit. Filter op **Iedereen**, **Nog open**, **Door**, **Twijfel** of **Niet**.

1. Klik per kandidaat op **Door**, **Twijfel** of **Niet**, en typ eventueel een korte reden in het
   veld ernaast. Dit wordt direct opgeslagen ("opgeslagen") en verandert nog niets aan de fase.
2. Een ander besluit kies je door op een andere knop te klikken. Klik je nog een keer op het
   besluit dat al gekozen is, dan wordt het gewist, samen met de reden ("besluit gewist").
3. Heeft iemand een besluit Door of Niet, dan staat onderaan **Naar de bellijst**.

### Stap 5: de bellijst

Links wie er gebeld moet worden (iedereen met Door of Niet die nog niet is doorgevoerd), met
telefoonnummer, score, besluit en of er al gebeld is. Bovenaan "x van y gebeld". Rechts de
kandidaat die aan de beurt is, met de reden bij het besluit en de observaties.

1. Klik op **Bellen** (op een telefoon belt dat direct) en vertel de uitslag.
2. Schrijf bij "Wat heb je verteld?" een korte aantekening.
3. Kies:
   - **Gebeld — doorzetten** of **Gebeld — afwijzen**: markeert als gebeld en voert meteen door
     (na bevestiging met **Ja, doorvoeren**).
   - **Gebeld, later doorvoeren**: markeert alleen als gebeld.
   - **Overslaan**: naar de volgende zonder iets vast te leggen.
4. Bij iemand die al gebeld is: **Doorzetten naar trainingsweekend** of **Afwijzen doorvoeren**, of
   **Toch niet gebeld** als je je vergiste.
5. **Alle gebelde doorvoeren** (bovenaan de lijst) voert iedereen door die als gebeld staat.

Doorvoeren van Door zet de fase op Uitgenodigd trainingsweekend en stuurt de uitnodiging.
Doorvoeren van Niet zet de fase op Afgewezen; de afwijzingsmail gaat alleen als je die hebt
aangezet (zie [De fases en wat er automatisch gebeurt](#de-fases-en-wat-er-automatisch-gebeurt)).
Een verstuurd bericht kun je niet terugnemen. Twijfelgevallen staan niet op de bellijst; die
handel je af via de nabespreking of het dossier. Is iedereen doorgevoerd, dan staat er "De
bellijst is leeg".

---

## Trainingsweekend

**Waar:** Kandidaten › Trainingsweekend, met vier stappen: Voorbereiden, Inchecken, Beoordelen en
Gesprekken.  
**Wie:** de eerste drie: bestuur, beheerder en begeleiders. Gesprekken: alleen bestuur en beheerder.

> **In het kort:** vooraf zie je wie er komt. Op het weekend checken sollicitanten zelf in op een
> tablet, en schrijven begeleiders op hun telefoon op wat opvalt. Daarna heeft iedereen een gesprek
> met het bestuur, met alle informatie bij elkaar. Aannemen of afwijzen gaat in dat gesprek meteen
> door, met jullie eigen bericht in de mail. Twijfel blijft staan.

Op dit scherm staat iedereen in de fases Uitgenodigd trainingsweekend en TW geweest, plus wie hier
al is doorgevoerd. Naast de titel staat de periode van het weekend. Vóór het weekend opent het
scherm op Voorbereiden, daarna op Beoordelen.

### Stap 1: voorbereiden

Bovenaan hoeveel mensen komen, hoeveel niet kunnen en van hoeveel het nog niet bekend is, en hoeveel
er nog geen beschikbaarheid hebben opgegeven. Die heb je nodig voor het gesprek.

- **Komt?** Dit vult de sollicitant zelf in het portaal in ("Kom je?"). Heb je iemand gebeld (ook
  na de selectiedag vanuit de bellijst), vul het dan hier in. Bij "Kan niet" is een reden verplicht.
  Kan iemand niet, dan verandert de fase niet: het bestuur beslist op Vandaag of die persoon afhaakt
  of een uitzondering krijgt.
- **Tablet bij de ingang** (bestuur). Maak een toegangscode aan, kopieer de tablet-link en open die
  op de tablet. Stel ook een pincode in: daarmee kan iemand van de organisatie op de tablet zelf het
  inchecken afsluiten. Een nieuwe code maakt de oude links ongeldig.

### Stap 2: inchecken

Op de tablet typt de sollicitant minstens drie letters van de naam en tikt op zichzelf (met foto).
Er staat nooit een volledige lijst op de tablet. Inchecken zet de fase op TW geweest.

Is de gezondheidsverklaring of het intakeformulier nog niet getekend (selectiedag en weekend),
dan zegt de tablet dat en gaat de link meteen uit: per mail en WhatsApp naar wie 18+ is, per mail
naar de ouder als die tekent, en zonder bekend ouder-adres een verzoek om dat in te vullen. In de
incheck-lijst van het beheer staat bij die persoon een rode markering "Niet getekend: …". Laat
niemand zonder getekende gezondheidsverklaring het water in.

Lukt de tablet niet, dan vink je hier af: **Aanwezig**, **Niet** of **Ongedaan**. Met **Inchecken
afsluiten** (of de pincode op de tablet) komt iedereen die nog niet is ingecheckt op "niet
verschenen". Er sluit niets vanzelf af, en een laatkomer check je daarna gewoon nog in.

### Stap 3: beoordelen

Op je telefoon. Tik een naam aan (met foto). Kies onder "Wat valt op?" **Positief**, **Neutraal**
of **Aandacht**, typ wat je zag en tik op **Observatie opslaan**. De sollicitant ziet observaties
niet. Je eigen observaties kun je verwijderen; die van anderen blijven staan.

Onderaan staat ingeklapt **Selectiedag**: het advies en gemiddelde, het besluit en de observaties
van toen. Het is er als je het nodig hebt, maar het staat bewust niet vooraan.

Wie de kennismakingsformulieren niet mag lezen, ziet geen bijzonderheden, alleen dat er iets is om
na te vragen; de knop **Dossier** en het telefoonnummer ziet alleen wie Sollicitanten mag lezen.

### Stap 4: gesprekken

Iedereen krijgt een gesprek met het bestuur. De lijst heeft drie groepen: Nu bezig, Nog te spreken
en Gesproken. De volgorde kies je ter plekke. Zoek op naam, filter op gesprek of besluit, of sorteer
op het selectiedag-cijfer.

1. Klik bij wie er aan tafel zit op **Gesprek starten**. Het gesprek staat dan op jouw naam; een
   ander bestuurslid ziet "bezig door ..." en kan meelezen, maar niet tegelijk een besluit
   vastleggen. Na 30 minuten vervalt dat vanzelf.
2. Links staat alles bij elkaar: gegevens, de opgegeven beschikbaarheid, **Inzet afspreken** (de
   loonplanning als concept: functie, en per week plannen, aantal dagen en betaald ja/nee; elke
   wijziging wordt meteen opgeslagen, en wat iemand in de loonplanning verandert zie je hier binnen
   15 seconden), wat er dit weekend is opgevallen, de selectiedag (cijfers per onderdeel, advies,
   besluit en opmerkingen), en wat er nog openstaat in het portaal.
3. Rechts kies je **Aannemen**, **Twijfel** of **Afwijzen**. Bij **Bericht aan de sollicitant**
   schrijf je in je eigen woorden wat jullie besproken hebben. Dat komt in de mail als
   `{{gesprek_bericht}}`. Laat je het leeg, dan komt de standaardtekst erin (instelbaar bij
   Instellingen › Uitnodigen & automatisch). De **Interne notitie** is alleen voor het bestuur.
4. Klik op **Besluit vastleggen** en bevestig. Aannemen en afwijzen gaan **meteen door**: de fase
   verandert en het bericht gaat uit. Aangenomen heeft direct het kennismakingspakket (de
   formulieren) in het portaal. Twijfel blijft staan: bel de kandidaat na het weekend, open het
   gesprek opnieuw en leg dan het besluit vast.
5. **Terug zonder besluit** geeft het gesprek weer vrij.

Bij aannemen of twijfel wordt de inzet van die persoon uit het actieve scenario naar **alle**
scenario's gekopieerd en daar vastgezet: wat met de sollicitant besproken is, verandert niet meer
doordat iemand met een ander scenario speelt. Met de anderen kun je gewoon verder puzzelen. Moet
het toch anders (bijzondere situatie), klik dan op **Toch aanpassen**, geef een reden (die komt in
het logboek), pas het aan en klik op **Nu vastzetten**.

De inzet blijft een concept tot je na het weekend bij Loonplanning **Loonplanning definitief maakt**.
Pas daarna ziet de sollicitant de afspraak.

In het dossier staat alles terug op het tabblad **Trainingsweekend**.

Liever per persoon, of met de keuze zonder bericht? Dat kan ook bij
[Sollicitanten](#sollicitanten): gebruik de fasekaart in het dossier of de bulkbalk.

---
## Ondertekeningen

**Waar:** Kandidaten › Ondertekeningen, en per persoon het tabblad Ondertekeningen in het dossier.  
**Wie:** bestuur en beheerder. Het loonheffingsformulier en de inschrijving als lid openen alleen
penningmeester en beheerder.

> **In het kort:** hier zie je wie er nog moet tekenen en wat al binnen is. Het scherm opent op
> Nog open. Bij 18+ tekent de sollicitant zelf in het portaal, onder de 18 tekent een ouder via
> een link in de mail. Komt een mail niet aan, kopieer dan de link en stuur hem zelf.

### Welke documenten

| Document | Wie tekent | Wanneer klaargezet |
|---|---|---|
| Gezondheidsverklaring (18+, tekent zelf) | Sollicitant, in het portaal | Bij de uitnodiging voor de selectiedag |
| Intake sollicitant | Sollicitant | Bij de uitnodiging voor de selectiedag |
| Gezondheidsverklaring (ouder tekent) | Ouder of verzorger, via een link per mail | Zodra de minderjarige de oudergegevens heeft ingevuld |
| Intake ouder | Ouder of verzorger | Zodra de oudergegevens bekend zijn |
| Loonheffingsformulier | Sollicitant zelf, ook onder de 18 | Bij aannemen (kennismakingspakket) |
| Inschrijving als lid | Sollicitant zelf, ook onder de 18 | Bij aannemen, als er nog geen verenigingsaccount is |

De grens van 18 jaar geldt voor het tekenen; dat is een andere grens dan de minimumleeftijd van 16
voor de aanmelding. De gezondheidsverklaring is één jaar geldig vanaf ondertekening. Elk getekend
document wordt verzegeld opgeslagen en is daarna niet meer te wijzigen.

### Hoe het loopt

- **18+**: de documenten staan als taken in het portaal. Na het tekenen krijgt de sollicitant een
  bevestiging met de getekende PDF als bijlage.
- **Minderjarig**: de sollicitant vult in het portaal naam, e-mailadres en telefoonnummer van de
  ouder of verzorger in. De ouder krijgt één mail met de links naar beide formulieren en tekent
  digitaal. De sollicitant krijgt bericht dat de ouder heeft getekend; de kopie-PDF gaat naar de
  ouder.
- **Verificatiecode**: alleen bij het loonheffingsformulier voert de ondertekenaar eerst een code
  in die per sms (of WhatsApp of e-mail als sms niet kan) wordt gestuurd.
- **Herinneringen**: staat een document na 3 dagen nog open, dan krijgt de ondertekenaar een
  herinnering, daarna nog één na 5 dagen (instelbaar, zie
  [Geplande berichten en herinneringen](#geplande-berichten-en-herinneringen)).

### Het overzicht van de handtekeningen

Bovenaan de tabs **Alle**, **Nog open** en **Compleet** met aantallen. Het scherm opent op Nog
open; is iedereen rond, dan op Alle. Per persoon een kaart met de naam (klik om het tabblad
Ondertekeningen in het dossier te openen), het label **ouder tekent mee** bij een minderjarige, een
voortgangsbalk en **Compleet** of "x open". Per document:

- **Ondertekend**: datum, naam van de ondertekenaar en **Bekijken** (opent de PDF in een nieuw
  tabblad). Mag je rol het document niet openen, dan zie je een slotje met **Alleen loondossier**.
- **Wacht**: **Link kopiëren**. Plak die link in een mail of app als de automatische mail niet is
  aangekomen.

Het getal achter Ondertekeningen in het menu en de melding op Vandaag tellen documenten die langer
dan 3 dagen openstaan (afgewezen kandidaten tellen niet mee).

### Een verkeerd e-mailadres van de ouder

Heeft de sollicitant een fout adres van de ouder ingevuld, dan komt de mail niet aan.

1. Vraag de sollicitant het adres in het portaal te verbeteren en op **Opnieuw versturen** te
   klikken. De formulieren die nog niet getekend zijn, krijgen dan het nieuwe adres en de ouder
   krijgt de mail opnieuw; ook de herinneringen gaan daarna naar het goede adres.
2. Lukt dat niet, open dan het dossier, tabblad Ondertekeningen, klik bij het openstaande document
   op **Link kopiëren** en stuur de link zelf naar het goede adres.

---

## Beschikbaarheid, dekking en loonplanning

**Waar:** zijbalk › Inzet & loon › Beschikbaarheid, Dekking en Loonplanning  
**Wie:** Beschikbaarheid: bestuur en admin. Loonplanning: bestuur, penningmeester en admin (bekijken met leesrecht, aanpassen met bewerkrecht). Dekking: begeleider, bestuur en admin, alleen bekijken.

> **In het kort:** Beschikbaarheid laat per week zien wie er kan werken. Daar beoordeel je ook
> de verzoeken om minder dagen, en je corrigeert een week als iemand iets anders doorgeeft.
> Dekking rekent uit of de posten per week te bemannen zijn met de juiste competenties.
> Loonplanning is de planning van wie je werkelijk inzet en wat dat kost, in scenario's.
> Wat je moet doen: openstaande verzoeken beoordelen zodra ze binnenkomen, en vóór de deadline
> kijken wie zijn of haar beschikbaarheid nog niet definitief heeft gemaakt.

### Het beschikbaarheidsraster

In het raster staat iedereen uit het huidige seizoen die een formulier heeft ingevuld of een
beschikbaarheid heeft doorgegeven. Afgewezen sollicitanten staan er niet in. Van boven naar
beneden zie je:

- **Openstaande verzoeken: N** (alleen als die er zijn): verzoeken om in bepaalde weken minder
  dagen te werken. Zie [Een verzoek om minder dagen beoordelen](#een-verzoek-om-minder-dagen-beoordelen).
- **Nog voorlopig: N personen** (alleen als die er zijn): wie de beschikbaarheid nog niet
  definitief heeft gemaakt. Er staat de deadline bij, met het aantal dagen dat nog rest of
  "verstreken". Deze mensen krijgen vanaf twee weken vóór de deadline vanzelf herinneringen. Je
  hoeft ze niet zelf na te bellen. Met het envelopje bij een naam mail je iemand direct.
- **Dekking per week**: een staafgrafiek met per week het aantal mensen dat telt. In het
  hoofdseizoen is dat het weektotaal. In het voor- en naseizoen zijn de posten alleen in het
  weekend bemand, dus telt dan het aantal dat het weekend dekt. De norm is de optelsom van alle
  rollen onder Instellingen › Posten & bezetting. Rechtsboven staat in woorden welke weken onder
  de norm zitten, of "Alle weken gedekt". Is er nog geen post ingesteld, dan staat er dat er geen
  norm is.
- Een tip als het **hoofdseizoen** nog niet is ingesteld. Elke week telt dan als hoofdseizoen.
  Je stelt het hoofdseizoen in bij [Seizoen](#seizoen-opzetten).
- **Raster**: een rij per sollicitant en een kolom per week van de werkperiode. De kolom "wkn"
  telt het aantal beschikbare weken.

Een cel in het raster betekent het volgende:

| In de cel | Betekenis |
|---|---|
| een getal (groen) | hele week, het aantal dagen |
| wk | alleen het weekend |
| een getal (minder dagen) | minder dan vijf dagen die week |
| een getal (correctie) | aangepast door het bestuur |
| — | niet beschikbaar |

Achter een naam kunnen labels staan: **terug** (terugkerende sollicitant), een oranje label
zoals **3d** (afwijkende norm van dat aantal dagen per week) en een paars label **vrij ma di**
(de dagen die iemand liever vrij heeft; de planner beslist). De onderste rij toont per week
"aantal beschikbaar / aantal dat het weekend dekt". Een rood getal zit onder de norm. Onderaan
staat wanneer de gegevens voor het laatst veranderden.

Met een klik op een naam open je het dossier op het tabblad Beschikbaarheid.

### Een verzoek om minder dagen beoordelen

Een sollicitant kan in het portaal vragen om in bepaalde weken minder dagen te werken. Het
bestuur krijgt dan de melding "Nieuw verzoek minder dagen werken".

1. Open **Beschikbaarheid**. In de kaart **Openstaande verzoeken** staat per verzoek de naam, de
   weken, het gevraagde aantal dagen per week en, als de sollicitant dat invulde, "Kan die
   week:" en "Reden:".
2. Klik op **Goedkeuren**, of op **Afwijzen** en bevestig met **Ja, afwijzen**. Bij afwijzen
   houdt de sollicitant de beschikbaarheid die er nu staat.
3. De sollicitant krijgt automatisch bericht van het besluit (bericht "Besluit verzoek minder
   dagen werken", standaard per e-mail en WhatsApp/SMS).

Beoordelen kan alleen op dit scherm, ook al zegt de uitleg op de kaart dat het ook in het
dossier kan.

### Een week corrigeren

Belt iemand dat een week niet gaat, of alleen een paar dagen? Leg dat vast als correctie van het
bestuur. Die overschrijft wat de sollicitant opgaf, en in de tijdlijn staat waarom.

1. Klik in het raster op de cel van die persoon en die week. In het dossier (tabblad
   Beschikbaarheid) werkt een klik op een weektegel hetzelfde.
2. Rechts opent een paneel. Onder **Dagen deze week** zet je de dagen aan of uit (ma t/m zo), of
   je kiest **Hele week**, **Alleen weekend** of **Deze week niet**. Staat er geen enkele dag aan,
   dan is die persoon die week niet beschikbaar.
3. Geldt het voor meer weken, vink dan **Ook W.. t/m W..** aan.
4. Kies een **Reden**. Dat is verplicht. Bij "Anders…" beschrijf je de reden in het tekstveld;
   bij de andere redenen is dat veld een extra toelichting.
5. Wil je dat de sollicitant bericht krijgt, vink dan **(voornaam) hierover berichten** aan.
6. Klik op **Opslaan**.

Onder **Status** zie je of de hele beschikbaarheid van deze persoon nog voorlopig of al definitief
is. Onder **Historie** staan eerdere aanpassingen van die week. Een bestaande correctie draai je
terug met **Correctie ongedaan maken**: dan geldt weer wat de sollicitant zelf opgaf.

### Beschikbaarheid volledig bewerken

In het dossier, tabblad Beschikbaarheid, staat in de kaart **Aanpassen** de knop **Volledig
bewerken**. Die opent het scherm "Beschikbaarheid aanpassen" met twee tabbladen:

- **Per dag**: een kalender van de hele werkperiode. Groen is beschikbaar, rood niet. Klik op een
  dag en kies **Beschikbaar maken** of **Niet beschikbaar maken**, eventueel met een reden. Een
  blauw randje betekent: aangepast door het bestuur. Met **Aanpassing terugdraaien** geldt voor
  die dag weer de eigen invoer van de sollicitant. Dit tabblad werkt pas als de werkperiode van
  het seizoen is ingesteld.
- **Formulier (portaal-stijl)**: de werkperiodes namens de sollicitant invullen, per periode
  "week … t/m …" met **hele weken** of **alleen weekenden**. **+ Periode toevoegen** geeft een
  extra regel. **Opslaan namens sollicitant** overschrijft de periodes van de sollicitant.
  Vakanties, verzoeken en de voorkeur voor vrije dagen blijven staan.

Onderaan staat **Bekijk de dag-export (API-check)**: per dag of iemand beschikbaar is, precies zoals
het naar het rooster gaat. Een afwijkende norm (bijvoorbeeld drie dagen per week na een
bestuursbesluit) zie je als oranje label in het raster. Je stelt die in het dossier in, tabblad
Beschikbaarheid, kaart **Norm**, met een reden (bewerkrecht op Beschikbaarheid).

### Het dekkingsscherm

Dekking beantwoordt een andere vraag dan het raster: niet "zijn er genoeg mensen?", maar
"passen de mensen die er zijn op de rollen?". Tien mensen die niet mogen varen leveren samen nog
steeds geen schipper op. Daarvoor zijn twee dingen nodig:

- de koppeling met Authentik, want daar komen de competenties vandaan. Zonder koppeling staat
  bovenaan "Authentik is nog niet gekoppeld." met een link naar Instellingen › Authentik;
- de posten en rollen onder Instellingen › Posten & bezetting.

Bovenaan staat in één zin hoe de zomer ervoor staat, bijvoorbeeld "Tekort in W28, W30", "2 weken
zijn precies genoeg, zonder reserve" of "Alle 14 weken gedekt, met reserve". Daaronder staat een
tegel per week met **Gedekt**, **Krap** of **Tekort**, de periode, het aantal beschikbare mensen
en de posten.

Klik op een week voor de details:

- de kop "x ingezet van y beschikbaar";
- per post elke rol met de namen die erop kunnen, en "+N reserve" of "geen reserve";
- bij een tekort: "Waarom tekort:" met de rol, en "Nodig · gevonden · vereist (competentie) vanaf
  rang". Daaronder staat "Wel gekwalificeerd, deze week niet beschikbaar:" met een bel- en
  mailknop per persoon. Staat daar "Niemand met deze competentie gevonden", dan lost bellen het
  niet op.

**Sluiten** klapt de week weer dicht. Eén persoon telt maar één keer: wie op de hoofdpost staat,
staat niet tegelijk op een andere post. De postcommandant valt buiten deze berekening.

### De loonplanning

In de loonplanning puzzel je per persoon en per week wie je inzet, en zie je meteen wat dat kost.
Elke wijziging wordt direct opgeslagen. Met alleen leesrecht op Loonplanning kun je niets wijzigen.

**Drie lagen, die elkaar niet overschrijven:**

1. **De opgave**: wat de sollicitant zelf invulde (Beschikbaarheid). Die blijft altijd staan.
2. **Scenario's**: het puzzelen hier. Zoveel varianten als je wilt; één is de actieve planning.
3. **De afspraak**: wat er echt is afgesproken. Die ontstaat pas als je de loonplanning
   **definitief maakt** (na het trainingsweekend) en is daarna de bron voor het portaal van de
   sollicitant, de dekking en de Excel voor de loonadministratie.

- **Scenario's**: bovenaan. **+ Dupliceren** maakt een kopie (met betaald/onbetaald en functies).
  Bij een scenario dat niet actief is staan **Maak actief** en **Verwijderen**.
- **Concept / Definitief**: de balk onder de scenario's. **Loonplanning definitief maken** maakt het
  getoonde scenario de actieve planning én de afspraak van iedereen die is aangenomen. Wie is
  aangenomen maar nergens staat ingepland, krijgt geen afspraak; die naam staat in de melding.
- **De tabel**: per persoon de **Functie** en per week een vinkje (inplannen), het aantal dagen en
  **betaald** (uitzetten voor een onbetaalde week, bijvoorbeeld "8 weken waarvan 5 betaald").
  Alle weken van het seizoen zijn te plannen, ook weken die iemand niet opgaf (stippelrand; dat
  komt ook bij de signalen). Een oranje rand betekent meer dagen dan opgegeven, of meer dan vijf.
- **Functie**: staat per persoon. "voorstel" betekent dat het systeem hem koos: een nieuwe
  sollicitant is Aspirant, wie in Authentik een Lifeguard-rang heeft krijgt die rang (Junior,
  Lifeguard, Senior). Kies je zelf, dan verdwijnt "voorstel". Postcommandant kies je altijd zelf.
- **Afspraak per persoon**: na definitief maken staat bij de naam "Afspraak dd-mm". Wijzig je daarna
  iets in het scenario, dan staat er **Wijkt af van afspraak** met de knop **Afspraak bijwerken**.
  Wordt iemand later aangenomen, dan staat er **Afspraak vaststellen**. Elke vaststelling komt in
  het logboek.
- **Signalen (N)**: aandachtspunten, geen blokkades: meer dagen dan opgegeven, een week die niet is
  opgegeven, meer dan vijf dagen, of weinig rust over de weekgrens.
- **Dekking** (onderste rijen): rekent met wie je werkelijk inplant. Daaronder de kosten per week.
- **Onderaan**: totale loonkosten (alleen betaalde dagen), mandagen met het aantal onbetaalde
  dagen, en de budgetbalk.

Het dagloon rekent met de leeftijd op de maandag van elke week: wie halverwege het seizoen jarig
is, krijgt vanaf die week het hogere tarief.

**Excel voor de loonadministratie**: **Excel (vastgesteld)** (pas na definitief maken) en **Excel
(dit scenario)** (met CONCEPT bovenaan elk tabblad). Vier tabbladen: Per persoon (geboortedatum,
functie, dagen betaald en onbetaald, bruto totaal), Weekmatrix, Opgave vs afspraak en Tarieven en
budget. BSN, IBAN en adres staan er bewust niet in: die heeft de penningmeester in het loondossier.
Elke download komt in het logboek.

De penningmeester werkt ook in de loonplanning (standaard lees- en bewerkrecht). Het budget en de
lonen staan onder Instellingen › Lonen & budget; dat blijft bij het bestuur.

---

## Loondossiers

**Waar:** zijbalk › Inzet & loon › Loondossiers  
**Wie:** penningmeester en admin. Het bestuur ziet dit scherm standaard niet.

> **In het kort:** Hier staat per aangenomen strandwacht het loondossier: het loonheffingsformulier,
> de kopie van het identiteitsbewijs, de kopie van de bankpas en de inschrijving als lid. Het
> dagelijkse werk doet de penningmeester; dat staat in de Handleiding penningmeester (hoofdstuk
> "Het scherm Loondossiers"). Als admin zorg je dat de penningmeester erbij kan en dat de kluis
> en het zegel werken.

### Wat je als admin regelt

1. Geef de penningmeester toegang: de rol Penningmeester, via de Authentik-groep of met de hand
   (zie [Beheerders en rechten](#beheerders-en-rechten)).
2. Vul onder Instellingen › Afzender & adressen het **Penningmeester e-mail** in. Leeg betekent:
   de melding gaat naar het bestuursadres. Vul ook het **Ledenservice e-mail** in voor de
   inschrijvingen als lid.
3. Laat het vinkje **Loondossier-stukken als bijlage meesturen in de mail aan de penningmeester**
   uit. Aanzetten stuurt BSN, ID-kopie en bankpas onversleuteld per mail.
4. Controleer onder [Systeem & Updates](#systeem-en-foutenlog) dat **Kluis** en **Documentzegel**
   groen zijn. Werkt de kluis niet, dan staat bovenaan Loondossiers "De kluis is niet
   bereikbaar." en zijn uploads en loonheffingsformulieren geblokkeerd.

### Wat er op het scherm staat

- Een vast kader "Hier staan een BSN en kopieën van identiteitsbewijzen." met de regels, wat er
  met de bankpasfoto gebeurt (automatisch na het ingestelde aantal dagen als opschonen aanstaat,
  anders zelf vernietigen) en de bewaarplicht van de loondocumenten.
- Een regel met de stand, bijvoorbeeld "2 dossiers wachten op verwerking", en de telling
  "aangenomen · compleet · verwerkt".
- Een kaart per persoon met het label **Verwerkt …**, **Compleet — klaar om te verwerken** of
  **Ontbreekt nog: …**. Een compleet, nog niet verwerkt dossier staat al open. Wie Sollicitanten
  mag lezen, ziet de link **Naar het dossier van …**.
- Per stuk de stand en de knoppen **PDF** of **Download**, en bij de bankpas **Vernietigen**
  (alleen met verwijderrecht).
- Onderaan de kaart **Verwerkt in de loonadministratie** (met een tussenscherm en **Ja,
  verwerkt**), **Melding opnieuw naar de penningmeester** en **Tijdlijn van de ondertekening**.
- Onderaan het scherm, zodra er iemand voor in aanmerking komt, de kaart **Te beoordelen voor
  vernietiging** (zie hieronder).

### Te beoordelen voor vernietiging

Het loonheffingsformulier en de kopie ID moeten bewaard blijven tot vijf jaar na het einde van het
kalenderjaar waarin het dienstverband eindigde. Het systeem weet alleen tot welk seizoen iemand
hier voorkwam, niet wanneer het dienstverband eindigde. Daarom wist het deze stukken niet zelf,
maar zet het ze klaar.

De kaart toont iedereen van wie het laatste seizoen als aangenomen (of met loonstukken) minstens
vijf volle kalenderjaren terug ligt. Wie in 2020 voor het laatst werkte, verschijnt dus op
1 januari 2026. Per persoon staan "Laatste seizoen hier" en "Nog aanwezig" (welke stukken er nog
zijn). De kaart ziet alleen wie Loondossiers mag bewerken.

1. Controleer in de loonadministratie of het dienstverband toen echt eindigde. Werkte iemand daarna
   nog, vernietig dan niets.
2. Klik op **Vernietigen…** (alleen met het recht Loondossiers verwijderen).
3. Het tussenscherm noemt wat er onherroepelijk weggaat. Klik op **Ja, loondossier vernietigen**.

De stukken worden uit de kluis gewist, en van het loonheffingsformulier verdwijnen ook het BSN, de
handtekening en de formuliergegevens. Als bewijs blijft staan dát er getekend en geüpload is. De
rest van het dossier (gegevens, sollicitaties, andere ondertekeningen) blijft staan. In het logboek
komt "Loondossier vernietigd (na de bewaarplicht)". Zijn de loondocumenten van een oud-strandwacht
weg, dan anonimiseert de dagelijkse opschoning daarna ook de persoon (als automatisch opschonen
aanstaat).

### Wat automatisch gebeurt

- Zodra loonheffing, ID en bankpas binnen zijn, gaat de melding "Loondossier compleet
  (penningmeester)" uit. Dat gebeurt ook als het laatste stuk een upload is, niet alleen na het
  ondertekenen.
- Elke download komt in het logboek als "Document gedownload", met wie en wanneer. Bij het stuk
  staat "Laatst ingezien". Op een foto (JPG of PNG) van de ID-kopie of de bankpas komt een
  watermerk; op een PDF niet.
- Bij elke download van een ID-kopie, bankpas of loonheffingsformulier krijgt het bestuursadres
  een mail "Loondocument gedownload: …" met de naam van het document. Verwacht je die niet, kijk
  dan in het [Logboek](#logboek). Die mail staat standaard aan en zet je uit onder Instellingen ›
  Bewaren & opschonen (raden we af).
- De kopie van de bankpas wordt na het ingestelde aantal dagen (standaard 30) na verwerking
  vernietigd, alleen als automatisch opschonen aanstaat (zie [Bewaartermijnen](#bewaartermijnen)).
  Staat het uit, dan vernietigt de penningmeester de bankpasfoto zelf.
- Loonheffing en ID-kopie worden nooit automatisch vernietigd; zie hierboven.

---

## Berichten sturen

**Waar:** zijbalk › Communicatie › Berichten, met de tabbladen **Nieuw bericht**, **Verzonden** en **Gepland**  
**Wie:** met leesrecht op Berichten & sjablonen zie je het scherm. Versturen en het tabblad Gepland vragen bewerkrecht. Standaard hebben bestuur en admin allebei.

> **In het kort:** Stuur een bericht aan één persoon, een selectie, een hele fase of een groep uit
> vorige seizoenen, per e-mail, WhatsApp/SMS of allebei. Bekijk eerst het voorbeeld of stuur een
> testmail naar jezelf. Onder Verzonden zie je van elk bericht, ook de automatische, of het is
> aangekomen en zo niet, waarom. Gepland bevat de regels voor berichten die op een vast moment
> uitgaan.

### Een bericht versturen

1. **Kanaal**: kies **E-mail** (volledige opmaak), **WhatsApp/SMS** (korte tekst, e-mail als
   terugval) of **E-mail + WhatsApp/SMS**. WhatsApp of SMS volgt de voorkeur die de sollicitant
   zelf instelde. Zonder telefoonnummer, of als het niet lukt, gaat het bericht per e-mail. Wie
   zich heeft afgemeld wordt overgeslagen, en elk bericht krijgt een afmeldvoet.
2. **Inhoud**: kies in de keuzelijst een eigen sjabloon, of laat **Zelf schrijven** staan en vul in:
   - **Onderwerp (e-mail)**;
   - **Hero-titel** (optioneel, de grote tekst bovenin);
   - **Bericht (e-mail)** in de editor;
   - **Korte tekst (WhatsApp/sms)**: platte tekst, maximaal 400 tekens, verplicht als je een kort
     kanaal kiest;
   - **Bijlagen** (optioneel): pdf, jpg, png, webp, docx of xlsx. Boven de ingestelde grens
     (standaard 15 MB) wordt een bijlage een downloadknop. Bij WhatsApp en SMS krijgt de
     ontvanger een downloadlink die veertien dagen geldig is, en dan gaat er ook een e-mail uit.
   Kies je een sjabloon, dan komt de inhoud daaruit; met **Sjabloon bewerken** pas je het aan.
3. **Aan**: typ een naam in het zoekveld, of klik op een snelknop zoals **+ Aangemeld (12)** of
   **+ Seintje gevraagd (4)**. **Alles** en **Geen** zetten de hele lijst aan of uit. Onder **Of
   kies uit de volledige lijst** staan alle sollicitanten van het seizoen per fase, met een vinkje
   voor de hele fase. Bij elke naam staan het e-mailadres en het kanaal ("WhatsApp", "sms",
   "alleen e-mail" of "geen telefoon"), en eventueel het label "afgemeld". Kom je via **Bericht**
   in een dossier of via een bulkactie, dan staan die mensen al aangevinkt.
4. **Variabelen**: klik op een chip om hem in de e-mailtekst te zetten: voornaam, achternaam,
   volledige_naam, seizoen_naam, selectiedag_datum, selectiedag_locatie, tw_datum_start,
   tw_locatie, portaal_url, aanmeld_url en bestuur_email. In het onderwerp en de korte tekst typ
   je ze zelf, bijvoorbeeld {{voornaam}}. {{aanmeld_url}} is de link naar het aanmeldformulier.
5. Klik op **Voorbeeld bekijken**. Je ziet de e-mail en de korte tekst zoals de eerste aangevinkte
   ontvanger ze krijgt (zonder ontvanger met voorbeeldnaam Lieke de Vries).
6. Klik eventueel op **Testmail naar mijzelf**. Die gaat altijd per e-mail naar je eigen adres.
7. Klik op **Versturen naar N personen** en bevestig met **Ja, versturen**.

Daarna zie je de uitslag. Wat misging staat vooraan, bijvoorbeeld "1 mislukt, 11 berichten
verstuurd". Per persoon zie je de stand met de reden in woorden. Bij meer dan tien ontvangers gaat
het via de wachtrij en wordt het de komende minuten verstuurd; je hoeft niets te doen. Met **Naar
het log** ga je naar Verzonden.

### Vorige seizoenen: oud-strandwachten en seintje gevraagd

Onder de fases van het huidige seizoen staan twee groepen uit vorige seizoenen:

- **Oud-strandwachten**: iedereen die ooit is aangenomen. Die mag je als vereniging benaderen,
  met de afmeldlink eronder.
- **Seintje gevraagd**: afgewezen sollicitanten die zelf om een bericht bij de volgende werving
  vroegen, minder dan twaalf maanden geleden (zie
  [Seintje bij de volgende werving](#seintje-bij-de-volgende-werving)).

Bij elke naam staat "laatst (seizoen)". Wie zich heeft afgemeld, is geanonimiseerd of zich dit
seizoen al heeft aangemeld, staat er niet in. Afgewezen mensen zonder seintje staan er bewust niet
in: daarvoor ontbreekt de toestemming, dus stuur hun geen wervingsbericht. In een bericht aan deze
groepen is {{seizoen_naam}} het huidige seizoen.

### Het tabblad Verzonden

Hier staan de laatste 200 berichten van alle kanalen, ook de automatische.

- **Filters**: zoek op naam of onderwerp, en kies een kanaal, een stand (Verstuurd, In de
  wachtrij, Mislukt, Overgeslagen) en een soort bericht. Klik op **Filteren**.
- **Kolommen**: Wanneer, Ontvanger (met de rol als het niet de sollicitant is, bijvoorbeeld een
  ouder), Onderwerp, Kanaal, Stand en Reden.
- **Teruggevallen**: het eerste kanaal lukte niet, het tweede wel. Bij het kanaal staat dan een
  pijl, bijvoorbeeld "WhatsApp → e-mail".
- De **reden** staat in woorden, zoals "Adres bestaat niet", "Postbus vol", "Geen WhatsApp op dit
  nummer" of "Sollicitant heeft zich afgemeld". Herkent het systeem de fout niet, dan staat de
  technische tekst er letterlijk.
- Klik op een regel voor de details en, zolang de bewaartermijn loopt, de inhoud. Van
  systeemberichten zoals de inlogcode wordt de inhoud nooit bewaard; dan staat er "Inhoud niet
  bewaard". **Sluiten** brengt je terug naar de lijst.

### Het tabblad Gepland

Hier staan de regels voor berichten die automatisch op een vast moment uitgaan. Zie
[Geplande berichten en herinneringen](#geplande-berichten-en-herinneringen).

---

## Sjablonen

**Waar:** Instellingen › **Sjablonen voor mails** (onderaan het tabbladmenu), of via **Sjabloon bewerken** bij Berichten  
**Wie:** bewerkrecht op Berichten & sjablonen (standaard bestuur en admin).

> **In het kort:** Elk automatisch bericht heeft een sjabloon: de e-mail, de korte tekst voor
> WhatsApp/SMS en de samenvatting in het portaal. Hier pas je de tekst aan, stel je per soort het
> kanaal in en maak je eigen sjablonen voor Berichten en Gepland. Terugzetten naar de standaardtekst
> kan altijd.

### Het sjablonenscherm

Links staan de sjablonen per categorie: Fase-overgangen, Taken en documenten, Herinneringen,
Meldingen aan het bestuur, Systeem, Handmatig, Gepland en Eigen sjablonen. Daaronder staan twee
kleine formulieren:

- **Nieuw eigen sjabloon**: een naam en **Aanmaken**. Eigen sjablonen kies je bij Berichten en bij
  Gepland.
- **Standaard hero-afbeelding (URL)**: de foto bovenaan elke mail als een sjabloon geen eigen foto
  heeft. Leeg betekent de standaard strandfoto.

In het midden bewerk je het gekozen sjabloon. Bovenaan staan de naam, de beschrijving, het type en
de ontvanger. Rechtsboven staat **Kanaal**: E-mail, WhatsApp/SMS, E-mail + WhatsApp/SMS of Uit.
Een wijziging daar wordt meteen opgeslagen; het is dezelfde instelling als onder Instellingen ›
Kanalen & meldingen. Berichten naar ouders en het bestuur kennen alleen E-mail of Uit.

Rechts staan de **Variabelen**: eerst die alleen voor dit type gelden, daaronder **Altijd
beschikbaar** (onder meer voornaam, seizoen_naam, selectiedag_datum, selectiedag_adres,
selectiedag_meenemen, tw_datum_start, tw_verzameltijd, portaal_url, bestuur_email en afmeld_url).
Klik op een chip om hem in de e-mailtekst te zetten.

### Een sjabloon aanpassen

1. Klik links op het sjabloon.
2. Pas de velden aan:
   - **Onderwerpregel (e-mail)**, verplicht;
   - **Hero-titel** (leeg is het onderwerp), **Hero-subtitel** en **Hero-kleur** (leeg is huisstijl-navy);
   - **Hero-afbeelding (optioneel)**: upload een jpg, png of webp, of vul een URL in;
   - **E-mailtekst** in de editor. Opmaakblokken maak je met de knoppen Knop, Info-blok en Let
     op, of als tekst: `[knop href="URL"]Tekst[/knop]`, `[info]…[/info]`, `[let op]…[/let op]` en
     `[code]123456[/code]`;
   - **Korte tekst (WhatsApp/SMS)**: platte tekst, maximaal 400 tekens. Bij een type dat alleen
     per e-mail gaat staat hier "Dit type gaat alleen per e-mail.";
   - **Samenvatting (portaal)**: één regel onder de titel in het berichtenoverzicht van de sollicitant;
   - **Bijlagen**: gaan mee met elke e-mail van dit type. Uitvinken koppelt een bijlage los.
3. Klik op **Opslaan**.
4. Controleer met **Voorbeeld e-mail** en **Voorbeeld kort bericht**. Die openen in een nieuw
   tabblad met voorbeeldgegevens.

**Terugzetten naar standaard** zet de ingebouwde tekst terug; je eigen tekst gaat verloren. Een
eigen sjabloon heeft in plaats daarvan **Verwijderen**. Verstuurde berichten blijven in het log
staan.

Let op bij **Niet geselecteerd** (de afwijzingsmail): die staat standaard **Uit**, omdat het
bestuur persoonlijk belt of mailt. Zet je hem aan, dan komt onder je eigen tekst altijd het blok
"Volgend jaar weer?" met de knop **Stuur me een seintje**. Dat blok staat niet in het sjabloon en
kun je er dus niet per ongeluk uit halen.

---

## Geplande berichten en herinneringen

**Waar:** Berichten › tabblad **Gepland** (eigen regels); Instellingen › Uitnodigen & automatisch, sectie Herinneringen (ingebouwde herinneringen); Instellingen › Systeem & updates › Herinneringen (proefdraaien)  
**Wie:** Gepland: bewerkrecht op Berichten & sjablonen. Herinneringen instellen: Instellingen. Proefdraaien: alleen wie Systeem mag bewerken, standaard alleen admin.

> **In het kort:** Het systeem stuurt uit zichzelf herinneringen, bijvoorbeeld als een document
> openstaat of de selectiedag nadert. Het kijkt daarbij naar wat er echt verstuurd is, zodat
> niemand hetzelfde twee keer krijgt. Daarnaast maak je zelf regels als "tien dagen na het
> inleveren van het formulier: stuur het magazine". Alles draait mee met de automatische taken,
> elk kwartier.

### De ingebouwde herinneringen

Onder Instellingen › Uitnodigen & automatisch staat bij **Herinneringen** een regel per soort. Per
regel zet je hem aan of uit en stel je in na hoeveel dagen (of hoeveel dagen vooraf), hoe vaak hij
herhaalt en het maximum. Klik daarna op **Opslaan** onderaan.

| Herinnering | Standaard |
|---|---|
| Nog niet aangemeld voor de selectiedag | aan; 3 dagen na de uitnodiging, herhaal elke 4 dagen, max 3; alleen als de selectiedag binnen 14 dagen is |
| Gegevens ouder/verzorger ontbreken | aan; na 2 dagen, herhaal elke 4 dagen, max 3 |
| Document nog niet ondertekend | aan; na 3 dagen, herhaal elke 5 dagen, max 2; per document naar de ondertekenaar |
| Beschikbaarheid nog voorlopig | aan; vanaf 14 dagen vóór de deadline (2 weken vóór het trainingsweekend), herhaal elke 4 dagen, max 5; na de deadline elke 2 dagen |
| Openstaande taken | aan; 5 dagen na de laatste fasewijziging, herhaal elke 7 dagen, max 3 |
| Selectiedag nadert | aan; 3 dagen vooraf, één keer |
| Selectiedag morgen | aan; de dag ervoor, één keer, met een vrij in te vullen "laatste info" |
| Trainingsweekend nadert | aan; 3 dagen vooraf, één keer |

Onder de regels staan nog vier instellingen:

- **Rustperiode (dagen)**, standaard 3: per sollicitant hooguit één "zachte" herinnering per zoveel dagen.
- **Anti-loop: zelfde bericht per dag**, standaard 8: vaker hetzelfde automatische bericht naar
  dezelfde ontvanger binnen 24 uur wordt overgeslagen. Wat je zelf in gang zet (fase omzetten,
  bulk, handmatig) telt niet mee.
- **Bijlage als e-mailbijlage tot (MB)**, standaard 15.
- **WhatsApp per uur (max)**, standaard 30. Daarboven valt het systeem terug op SMS of e-mail.

De inhoud van elke herinnering pas je aan bij [Sjablonen](#sjablonen), het kanaal onder
Instellingen › Kanalen & meldingen.

### Herinneringen proefdraaien

Wil je weten wie er een herinnering zou krijgen? Een admin opent Instellingen › **Systeem &
updates** en gaat naar de kaart **Herinneringen**.

1. Klik op **Proefdraaien (niets versturen)**. Per regel zie je hoeveel kandidaten er zijn, hoeveel
   berichten er uit zouden gaan en wie wordt overgeslagen, met de naam en de reden.
2. Klopt het en wil je niet op de volgende automatische ronde wachten, klik dan op **Nu draaien** en
   bevestig met **Ja, versturen**. De berichten gaan via de wachtrij.

Op die kaart staat ook wanneer de automatische taken voor het laatst draaiden.

### Een eigen regel maken

1. Maak eerst een eigen sjabloon aan onder [Sjablonen](#sjablonen): dat is de inhoud van het
   bericht, eventueel met bijlage. Zonder eigen sjabloon kun je geen regel opslaan.
2. Ga naar Berichten › **Gepland** en klik op **Nieuwe regel**.
3. Vul in:
   - **Naam (intern)**, bijvoorbeeld "Magazine na formulier";
   - bij **Wanneer**: de **Gebeurtenis**, **Dagen erna** (10 is tien dagen erna, -3 is drie
     dagen ervóór) en het **Venster (dagen)**, standaard 7. Een regel verstuurt alleen als het
     moment hooguit zoveel dagen geleden was, zodat oude gevallen het bericht niet alsnog krijgen;
   - bij **Wat**: het **Sjabloon (inhoud)**, het **Kanaal** en **Hoe vaak per persoon (max)**;
   - bij **Voor wie**: de fases. Niets aangevinkt betekent alle fases. Het vinkje **Regel staat
     aan** zet de regel aan.
4. Klik op **Opslaan**.

Je kunt kiezen uit deze gebeurtenissen: Aanmelding (datum van aanmelden), Formulier "Beter leren
kennen" ingeleverd, Beschikbaarheid definitief gemaakt, Selectiedag (datum uit het seizoen),
Trainingsweekend (startdatum uit het seizoen), en per fase "Fase gewijzigd naar …".

In de lijst **Regels** staat per regel **Aan** of **Uit**, met een samenvatting zoals "10 dagen
na aanmelding · sjabloon "Magazine" · E-mail · max 1× per persoon". Per regel heb je:

- **Proefdraaien**: toont bovenaan een **Proefrun** met het label "Niets verstuurd", het aantal
  kandidaten, hoeveel er een bericht zouden krijgen en wie wordt overgeslagen;
- **Bewerken**;
- **Verwijderen**, met bevestiging. Berichten die al zijn verstuurd blijven in het log staan.

---

## Seintje bij de volgende werving

**Waar:** de afwijzingsmail, de startpagina van het portaal (bij fase Afgewezen), en Berichten › Aan › groep **Seintje gevraagd**  
**Wie:** de sollicitant geeft of trekt de toestemming zelf in; het bestuur gebruikt de groep bij de volgende werving.

> **In het kort:** Een afgewezen sollicitant mag je niet zomaar een bericht sturen als de werving
> weer opent. De gegevens zijn voor die ene sollicitatie gegeven, en voor zo'n bericht is vooraf
> toestemming nodig (AVG en Telecommunicatiewet). Een zin in de voorwaarden is daarvoor niet
> genoeg. Daarom vraagt het systeem het zelf bij de afwijzing, met één knop. Wie ja zegt, staat
> bij Berichten in de groep Seintje gevraagd. De toestemming geldt maximaal twaalf maanden.

### Wat de afgewezen sollicitant ziet

**In het portaal.** Staat iemand op Afgewezen, dan toont de startpagina de kaart "Dit seizoen
niet verder" met een korte uitleg en het aanbod om persoonlijk te horen waarop is gelet. Daaronder
staan drie regels:

- **Seintje bij de volgende werving?** met "Dan bewaren we je naam en contactgegevens maximaal een
  jaar." en de knop **Ja, graag**. Na de klik staat er **Je krijgt een seintje**, met de knop
  **Toch niet**;
- **Mijn gegevens downloaden**;
- **Gegevens laten verwijderen**.

Onderaan staat hoe lang de gegevens blijven: zonder seintje "Zonder seintje verwijderen we je
gegevens vier weken na de afwijzing." (het getal volgt de bewaartermijn), met seintje "maximaal een
jaar". Dit werkt dus ook als het bestuur persoonlijk belt en de afwijzingsmail uit laat.

**In de afwijzingsmail.** Staat de mail "Niet geselecteerd" aan (standaard uit), dan komt onder de
tekst het blok "Volgend jaar weer?" met de knop **Stuur me een seintje**. Die knop opent een pagina
waarvoor de sollicitant niet hoeft in te loggen. Daar staat nog een knop **Stuur me een seintje**.
Alleen het openen van de link geeft dus geen toestemming, want mailscanners openen links vaak
vooraf. Daarna staat er "Top, we laten van ons horen", en op dezelfde pagina kan de sollicitant
het terugdraaien met **Toch geen seintje**.

### Wat je als bestuur doet

1. **Bij de afwijzing**: bel of mail persoonlijk en noem de mogelijkheid van een seintje. Zet
   eventueel de mail "Niet geselecteerd" aan onder [Sjablonen](#sjablonen).
2. **Als de werving weer opent**: ga naar Berichten › Nieuw bericht en klik bij **Aan** op **+
   Seintje gevraagd (n)**. Voeg eventueel **+ Oud-strandwachten (n)** toe.
3. Schrijf het wervingsbericht met de variabele {{aanmeld_url}}, bekijk het voorbeeld en verstuur.
4. Benader afgewezen mensen buiten deze groep niet, ook niet als je hun adres nog ergens hebt.

### Hoe lang het geldt en hoe het vervalt

- De toestemming geldt het aantal maanden onder Instellingen › Bewaren & opschonen, **Seintje bij
  de volgende werving** (standaard en maximaal 12, want dat beloven de mail en het portaal). Daarna
  valt de persoon uit de groep. Als automatisch opschonen aanstaat, worden de gegevens dan
  geanonimiseerd.
- Intrekken kan altijd: met **Toch niet** in het portaal, met **Toch geen seintje** op de pagina
  achter de mailknop, en met de afmeldlink onder elk bericht.
- Wie om een seintje vraagt, heeft zich daarmee ook weer aangemeld voor berichten: een eerdere
  afmelding vervalt.
- Geven en intrekken komen in het [Logboek](#logboek) als "Seintje gevraagd" en "Seintje ingetrokken".

Oud-strandwachten hebben geen seintje nodig. Die mag je als vereniging benaderen, met de afmeldlink
eronder, en die zet het systeem er zelf onder.

---

## Instellingen

**Waar:** zijbalk › Systeem › Instellingen (tabbladen links, in groepen)  
**Wie:** bestuur en admin. Opslaan vraagt bewerkrecht op Instellingen.

> **In het kort:** Links staan de tabbladen in zes groepen, met een filterveld "Filter
> instellingen…". Elk tabblad heeft een eigen knop **Opslaan**; die bewaart alleen de velden van
> dat tabblad. Wachtwoorden en sleutels worden nooit getoond. Onder de tabbladen staat de link
> **Sjablonen voor mails**, en in de kaart **Versie** de link **Systeem & updates** (voor wie
> Systeem mag bewerken). Zoeken door het hele beheer kan met Ctrl K.

### Wachtwoorden en sleutels

Een geheim veld (de AI-sleutel, de Authentik-tokens, het wachtwoord van de mailserver, de sleutels
voor sms en WhatsApp) toont nooit de waarde zelf, alleen het label **Ingesteld** of **Nog niet
ingesteld**. Laat je het veld leeg, dan blijft de huidige waarde staan. Plak een nieuwe waarde om
hem te vervangen, of vink **Huidige waarde verwijderen** aan om hem te wissen. In het logboek komt
alleen welk veld is gewijzigd, nooit de waarde.

### De tabbladen

| Groep | Tabblad | Wat je hier instelt |
|---|---|---|
| Organisatie | **Algemeen** | Naam van de organisatie; logo (PNG, JPG of WebP) en favicon (PNG of ICO), als upload of pad/URL; SVG wordt geweigerd, want daar kan script in zitten; de welkomsttekst op de portaal-login; de huisregels (pdf) voor de taak "Huisregels lezen" van aangenomen strandwachten (zonder document blijft de taak staan als "volgt binnenkort"; **Losmaken van de taak** koppelt hem los). Bij Aanmeldformulier staat de vaste tekst "De minimumleeftijd is 15 jaar"; die is niet in te stellen. |
| Selectie & seizoen | **Uitnodigen & automatisch** | **Seizoen en selectie**: de **Seizoensfase** (standaard "Automatisch (uit de datums van het seizoen)"; kies alleen een vaste fase als de datums niet kloppen, en zet hem daarna terug) en de **Advies-drempel selectiedag** (1 tot 5, standaard 3,5), vanaf waar het dossier "Door" adviseert. Auto-invite: modus Uit, Suggestie (markeert in de lijst met **Uitnodigen?**) of Automatisch (nodigt uit na de vertraging in uren), met de leeftijdsgrenzen (standaard 16 tot 30). De automatisering bij een fasewijziging: **Ondertekeningen bij SD-uitnodiging** en **Kennismakingspakket bij aangenomen**. De ingebouwde herinneringen, de rustperiode, het anti-loop-plafond, de grens voor e-mailbijlagen en het maximum aantal WhatsApp-berichten per uur (zie [Geplande berichten en herinneringen](#geplande-berichten-en-herinneringen)). |
| Selectie & seizoen | **Beoordelingscriteria** | Per test (Sporttest en Zwemtest) het icoon, de naam en de criteria, één per regel. Beoordelaars scoren elk criterium van 1 tot 5. **Standaard uit het draaiboek herstellen** zet de ingebouwde lijst terug. |
| Selectie & seizoen | **Posten & bezetting** | De posten en per post de rollen: naam, competentie, minimale rang uit Authentik en aantal personen. Per post het vinkje "nooit 2 aspiranten samen". **+ Post toevoegen**, **+ Rol**, **Opslaan**, **Standaard herstellen**. Dekking en de norm in het beschikbaarheidsraster rekenen hiermee. De postcommandant valt erbuiten. |
| Inzet & loon | **Functies** | Per functie de naam, de factor voor het dagloon, de minimum- en maximumleeftijd en de volgorde, met **Sla op** en een voorbeeldberekening. |
| Inzet & loon | **Lonen & budget** | Het seizoensbudget voor loonkosten (voor de balk in de loonplanning; leeg is zonder budget); het vakantietoeslagpercentage (standaard 8), de werkuren per dag (standaard 8) en de vaste weekbedragen van de postcommandant voor juni en juli–september; de minimumuurlonen per leeftijd (15 tot en met 32) voor juni en voor juli/augustus/september, elk met een eigen **Opslaan**. |
| Berichten | **Kanalen & meldingen** | SMS inschakelen met de gateway en de gegevens daarvan; WhatsApp inschakelen via WhatsReach (staat standaard uit; lees de waarschuwing over blokkering van het nummer); per meldingstype het kanaal (E-mail, WhatsApp/SMS, E-mail + WhatsApp/SMS of Uit). Ouders en het bestuur krijgen altijd e-mail. Onderaan **Testbericht sturen** met een telefoonnummer, een e-mailadres als terugval en het kanaal, en **Test versturen**. |
| Berichten | **Afzender & adressen** | De adressen voor foutmeldingen, het bestuur (ontvangt de bestuursmeldingen), de penningmeester ("loondossier compleet"; leeg is het bestuursadres) en de ledenservice (de ondertekende inschrijving als lid; leeg is het bestuursadres). Het vinkje voor loondossier-stukken als bijlage: laat dat uit. |
| Berichten | **E-mailserver** | Host, poort, beveiliging (STARTTLS, SSL/TLS of Geen), gebruikersnaam, wachtwoord, afzenderadres en afzendernaam. Daaronder **Testmail versturen**: sla eerst op, vul het adres in en klik op **Verstuur testmail**. |
| Koppelingen | **Authentik** | De status van de SSO-koppeling, het **Authentik API-token** voor het inloggen van terugkerende sollicitanten via Authentik (verwijderd is alleen inloggen per mail), de **Toegestane admin-groepen** (standaard "Bestuur,Sol-Admin,Sol-Begeleider,Sol-Penningmeester"), de koppeling voor competenties (basis-URL en **API-token (service-account)**, **Test verbinding**, **Nu alle sollicitanten verversen**) en de groep-indeling van de competenties (alleen te bekijken). De tokens zijn geheime velden. |
| Koppelingen | **Website & API** | De geheime sleutel voor de WordPress-plugin (**Kopieer**; **Nieuwe sleutel genereren** maakt de oude meteen ongeldig), de toegestane websites (CORS, één per regel) en de adressen die de plugin nodig heeft. De sleutel zelf zien, kopiëren en vernieuwen kan alleen wie Systeem mag bewerken (standaard admin); anderen zien **Ingesteld**. |
| Koppelingen | **AI-analyse** | De **Gemini API-sleutel** (geheim veld) en het **Model**: Gemini 2.5 Flash (standaard), Gemini 2.5 Flash-Lite (sneller, goedkoper) of Gemini 2.5 Pro (grondiger, trager); een oud model dat Google heeft uitgezet valt vanzelf terug op de standaard. Het vinkje **AI-hulp bij beschikbaarheid** (experimenteel). Het blok **Wat er naar Google gaat**: de instructie voor de analyse staat vast in de code en is niet te bewerken; per sollicitant gaan alleen leeftijdscategorie, opleiding, diploma's en certificaten, werkervaring, de twee motivaties en het aantal beschikbare weken mee, niet de naam, contactgegevens, het adres, de foto of de bijzonderheden. **Automatisch analyseren na ontvangst BLK** (standaard aan): uit betekent alleen handmatig, met **Analyseer nu** bij het volledige formulier. |
| Privacy & onderhoud | **Bewaren & opschonen** | Een rode waarschuwing zolang automatisch opschonen uit staat. **Automatisch opschonen inschakelen**, de termijnen per soort gegeven (ook het logboek, het foutenlog en de kopie bankpas), de schakelaars voor de downloadmail aan het bestuur en de bezoekersstatistiek, en het **Opschoon-logboek**. Daaronder de cronjob-regel voor Plesk met **Handmatig uitvoeren** en **Nieuw token maken** (alleen voor wie Systeem mag bewerken) en de stand van de AI- en mailwachtrij. Zie [Bewaartermijnen](#bewaartermijnen). |

Onder de tabbladen:

- **Sjablonen voor mails** opent [Sjablonen](#sjablonen).
- De kaart **Versie** toont de geïnstalleerde versie, met de link **Systeem & updates** voor wie
  Systeem mag bewerken (standaard admin; zie [Systeem en foutenlog](#systeem-en-foutenlog)). Ook
  de link naar het proefdraaien van herinneringen staat er alleen voor hen.

Het seizoen zelf (datums, openen en sluiten) staat niet hier maar onder het eigen menu-item
Seizoen; zie [Seizoen opzetten](#seizoen-opzetten).

### Handleidingen

De handleidingen en rondleidingen hebben geen tabblad in Instellingen. Je komt er op drie manieren:

- via **Hulp en handleidingen** onderin het menu: het overzicht met de rondleidingen per rol en de
  handleiding beheer, wervingspagina's, penningmeester, sollicitant en, voor wie Systeem mag
  lezen, het runbook systeembeheer;
- via het vraagteken rechtsboven op een scherm: **Lees meer in de handleiding** opent het juiste
  hoofdstuk, **Rondleiding van dit scherm** loopt het scherm met je door;
- via de zoeker (Ctrl K): typ "handleiding".

### Wat je bij de start instelt

Een nieuwe installatie werkt pas goed als je deze tabbladen hebt nagelopen:

1. **Afzender & adressen**: het bestuursadres, en als je die hebt het adres van de penningmeester
   en de ledenservice.
2. **E-mailserver**: vul de gegevens in, klik op **Opslaan** en daarna op **Verstuur testmail**.
3. **Website & API** (admin): genereer de sleutel en zet hem in de WordPress-plugin.
4. **Kanalen & meldingen**: zet SMS en eventueel WhatsApp aan, kies per melding het kanaal en
   stuur een testbericht naar je eigen nummer.
5. **Bewaren & opschonen**: zet **Automatisch opschonen inschakelen** aan. Zonder dit vinkje wordt
   er nooit iets opgeruimd; zie [Bewaartermijnen](#bewaartermijnen).
6. **Posten & bezetting** en **Lonen & budget**: vóór je gaat plannen.
7. **Bewaren & opschonen**, onderaan (admin): zet de geplande taak in Plesk
   (PHP-script cron/run.php, elke minuut), of laat de technisch beheerder dat doen.

---

## Beheerders en rechten

**Waar:** zijbalk › Systeem › Beheerders & rechten, met de tabbladen **Beheerders** en **Rollen & rechten**  
**Wie:** bewerkrecht op Beheerders & rollen (standaard alleen admin).

> **In het kort:** Iemand komt normaal binnen via Authentik: de groep in Authentik bepaalt de rol,
> en het account ontstaat bij de eerste keer inloggen. Een wachtwoord geef je alleen als
> noodingang. Onder Rollen & rechten koppel je een rol aan een Authentik-groep en stel je per
> onderdeel in wat een rol mag. Admin heeft altijd alles.

### Iemand toegang geven

1. Zet de persoon in Authentik in de groep die bij de rol hoort. Standaard is dat **Sol-Admin**
   voor Admin, **Bestuur** voor Bestuur, **Sol-Penningmeester** voor Penningmeester en
   **Sol-Begeleider** voor Begeleider (te controleren onder Rollen & rechten).
2. Controleer onder Instellingen › Authentik het veld **Toegestane admin-groepen**. Standaard staat
   daar "Bestuur,Sol-Admin,Sol-Begeleider,Sol-Penningmeester". Heeft iemand die lijst eerder zelf
   aangepast, dan blijft die eigen lijst staan; controleer dan of de groep van de nieuwe persoon
   erin staat.
3. Laat de persoon één keer inloggen met **Inloggen via Authentik (SSO)**. Het account verschijnt
   dan vanzelf in de lijst.

Goed om te weten:

- Bij elke inlog via Authentik zet het systeem de rol opnieuw op basis van de groepen. Zit iemand
  in een groep die aan een rol gekoppeld is, dan overschrijft de volgende inlog een rol die je met
  de hand hebt gekozen. Zit iemand in meer groepen, dan wint de hoogste rang. Wil je de rol
  blijvend wijzigen, doe dat dan in Authentik.
- Zit iemand wel in een toegestane groep, maar is die groep aan geen rol gekoppeld, dan blijft de
  rol staan die je met de hand koos. Een nieuw account krijgt dan de rol Begeleider.
- In eerdere versies werd de rol Penningmeester (en elke eigen rol) stil leeg opgeslagen. Zo'n
  account staat nu op Begeleider. Zie je dat bij de penningmeester, zet de rol dan eenmalig op
  Penningmeester.

### Iemand de toegang afnemen

Haal de persoon in Authentik uit de groep (of uit alle toegestane groepen). Daarna kan die niet
meer via Authentik inloggen, ook niet met een bestaand account. Heeft het account ook een
wachtwoord als noodingang, zet het account dan hier ook uit met **Uitzetten**.

### Het tabblad Beheerders

Per account zie je de naam (met "jij" bij jezelf en "Uitgezet" bij een uitgezet account), het
e-mailadres, hoe iemand inlogt (**Authentik**, **Wachtwoord**, of de waarschuwing **Geen
inlogmethode**) en "laatst …" of "nog nooit ingelogd". Per account:

- de **rol** in de keuzelijst, opgeslagen met **Rol opslaan**;
- **Wachtwoord**: klapt het formulier "Nieuw wachtwoord voor …" open (minimaal 8 tekens). Het
  systeem verstuurt het wachtwoord niet: geef het zelf door;
- **Uitzetten** of **Aanzetten**, via een tussenscherm met **Ja, uitzetten**. Een uitgezet account
  kan niet meer inloggen, ook niet via Authentik. Wat die persoon deed blijft in het logboek.
  Je eigen account kun je niet uitzetten.

Bij een account met een wachtwoord staat ook het label **Tweestaps aan** (groen: inloggen met het
wachtwoord vraagt een code uit een authenticator-app) of **Tweestaps uit** (oranje: nog geen app,
dus een code per e-mail). Zie [Wachtwoord als noodingang](#wachtwoord-als-noodingang).

**Tweestaps resetten** staat alleen bij een account met tweestaps aan, en alleen voor wie de rol
Beheerder (admin) heeft; het recht op Beheerders & rollen is niet genoeg. Gebruik het alleen als
iemand de telefoon met de app kwijt is én geen herstelcodes meer heeft:

1. Controleer eerst dat het verzoek echt van die persoon komt: bel of spreek elkaar. Een mail of
   chatbericht is niet genoeg, want juist dat kan een aanvaller sturen.
2. Klik op **Tweestaps resetten** en bevestig op het tussenscherm met **Ja, tweestaps resetten**.
3. De app-koppeling en alle herstelcodes vervallen. Inloggen met het wachtwoord vraagt daarna een
   code per e-mail. Laat de persoon na het inloggen de app opnieuw instellen in Mijn profiel.

De reset komt in het logboek als "Tweestapsverificatie gereset door een beheerder".

Rechts staat **Account aanmaken**: naam, e-mailadres, een tijdelijk wachtwoord (minimaal 8 tekens)
en de rol, met de knop **Account aanmaken**. Dat heb je alleen nodig voor iemand die niet via
Authentik binnenkomt, of als noodingang. Alle wijzigingen komen in het logboek; wachtwoorden zelf
nooit.

### Een noodingang maken

Ligt Authentik plat, dan kan alleen iemand met een wachtwoord in dit systeem nog inloggen.

1. Klik bij je eigen account (of dat van een andere admin) op **Wachtwoord**.
2. Vul een sterk wachtwoord in en klik op **Opslaan**.
3. Bewaar het veilig, bijvoorbeeld in de wachtwoordkluis van het bestuur. Na te veel mislukte
   pogingen wordt het adres tijdelijk geblokkeerd.
4. Log één keer in met het wachtwoord (met de code per e-mail) en stel in **Mijn profiel** ›
   **Tweestapsverificatie** een authenticator-app in. Bewaar de herstelcodes offline. Zonder app
   hangt de noodingang af van werkende mail, en juist die kan tegelijk met Authentik uitvallen.

Je wachtwoord zelf wijzigen doe je onder **Mijn profiel** (klik linksonder op je naam).

### Het tabblad Rollen & rechten

Bovenaan staan de rollen met het label **systeemrol**, de **rang** en het aantal gebruikers. Per rol:

- **Bewerken**: de naam (bij een systeemrol vast) en de **Authentik-groep**, met **Opslaan**;
- **Verwijderen** (alleen bij een zelfgemaakte rol), via een tussenscherm. Dat lukt pas als geen
  enkele beheerder die rol nog heeft.

Onder **Nieuwe rol toevoegen** maak je een extra rol: naam, Authentik-groep en rang (**1 — basis**
of **2 — uitgebreid**), met **Rol toevoegen**. Een nieuwe rol begint met alle rechten uit. De rang
bepaalt alleen welke rol wint bij iemand in meer groepen.

De matrix **Wat mag welke rol?** toont per onderdeel en per rol de vinkjes Lezen, Bewerken en
Verwijderen. Een streepje betekent dat die actie voor dat onderdeel niet bestaat. Bewerken of
verwijderen zet lezen vanzelf aan. Admin staat vast op alles. **Rechten opslaan** bewaart de hele
matrix in één keer; elke wijziging komt in het logboek.

Zo staat het op een nieuwe installatie (l = lezen, b = bewerken, v = verwijderen):

| Onderdeel | Begeleider | Bestuur | Penningmeester | Admin |
|---|---|---|---|---|
| Sollicitanten | – | l b v | l | alles |
| Wervingspagina's | – | l b v | – | alles |
| BLK-formulieren & details | – | l b | – | alles |
| Selectiedag | l | l b | – | alles |
| Ondertekeningen | – | l b | – | alles |
| Loondossiers (ID, bankpas, loonheffing) | – | – | l b v | alles |
| Beschikbaarheid | – | l b | – | alles |
| Dekking | l | l | – | alles |
| Loonplanning | – | l b | l b | alles |
| Berichten & sjablonen | – | l b | – | alles |
| Seizoenbeheer | – | l b | – | alles |
| Instellingen | – | l b | – | alles |
| Beheerders & rollen | – | – | – | alles |
| Systeem, updates & logs | – | – | – | alles |
| Logboek (alle logs) | – | – | – | alles |

Het recht Loonplanning voor de penningmeester is er sinds versie 2.102. Een bestaande installatie
krijgt het één keer automatisch (in het logboek als rolwijziging "door systeem"); bestaande
rechten worden daarbij nooit verlaagd. Wil je het niet, haal het dan weg onder Rollen & rechten
en klik op **Rechten opslaan**: het systeem zet het daarna niet opnieuw aan.

Wat Selectiedag lezen voor de begeleider betekent: groepen bekijken, inchecken, cijfers en
observaties geven en de eigen observaties verwijderen. Bewerken (bestuur) voegt de tabletcode, de
groepsindeling, de nabespreking, de bellijst en het besluit na het trainingsweekend toe.

Twee onderdelen vragen extra zorg. **Loondossiers** bevat BSN en identiteitsbewijzen en hoort
alleen bij de penningmeester en admin. **Logboek** laat zien wie welke persoonsgegevens heeft
ingezien. Geef die niet zomaar aan een andere rol.

---

## Logboek

**Waar:** zijbalk › Systeem › Logboek  
**Wie:** leesrecht op Logboek (standaard alleen admin).

> **In het kort:** Alle sporen van het systeem in één tijdlijn: wie wat wanneer deed (ook inzage in
> en downloads van persoonsgegevens), verstuurde berichten, technische fouten, het bewijsspoor van
> ondertekeningen, wat de bewaartermijnen opruimden en mislukte inlogpogingen. Gebruik het om een
> melding te herleiden of verantwoording af te leggen, niet om mensen te volgen. Het oude foutenlog
> zit hier nu in, als tabblad Fouten.

### Het logboekscherm

- **Tabbladen per bron**, elk met het aantal regels: **Alles**, **Audit** (wie wat deed),
  **Fouten** (technische fouten met hun referentienummer), **Berichten** (alleen het onderwerp,
  nooit de inhoud), **Ondertekenen** (geopend, code verzonden, ondertekend, verzegeld),
  **AVG-opschoning** en **Mislukte logins**. Onder een gekozen bron staat een uitleg.
- **Filters**: "Zoek in details…", **Wie** (naam, e-mail of id), **Alle acties**, de ernst
  (Alles, Info, Aandacht, Fout) en een periode **Van** … **tot**. Klik op **Filter**. **Wis
  filters** haalt ze weg.
- **Export (CSV)** downloadt de huidige selectie. Ook de export zelf wordt gelogd.
- **Kolommen**: Wanneer (bij Alles ook de bron), Wie (met Beheerder, Sollicitant of Onbekend, en
  het IP-adres), Wat (met een label Aandacht of Fout), Waar en Details.
- Klik in de kolom **Waar** op een object om alles te zien wat daarmee gebeurde. Het object staat
  dan als label naast de filters.
- Per pagina 50 regels, met **← Vorige** en **Volgende →**.

### Een melding herleiden

Voorbeeld: "wie heeft de ID-kopie van deze strandwacht gedownload?"

1. Open het tabblad **Audit**.
2. Kies bij de acties **Document gedownload**, of zoek in de details op de naam.
3. Klik in de kolom **Waar** op het document of de sollicitatie om de hele geschiedenis te zien.
4. Leg een export vast met **Export (CSV)** als je het moet verantwoorden.

Een foutmelding op het scherm van een gebruiker heeft een referentienummer. Zoek dat op onder
**Fouten**; zie [Systeem en foutenlog](#systeem-en-foutenlog).

### Hoe lang regels blijven

De auditregels blijven standaard 12 maanden (de regel bovenaan noemt de termijn) en de fouten 90
dagen; je stelt beide in onder Bewaren & opschonen, net als de termijn van het berichtenlog.
Mislukte inlogpogingen worden na 24 uur opgeruimd. Voor het bewijsspoor van ondertekeningen en het
opschoon-logboek geldt geen automatische termijn. Zie [Bewaartermijnen](#bewaartermijnen).

---

## Privacy en AVG-verzoeken

**Waar:** het dossier van de sollicitant (Sollicitanten › naam), het portaal (Mijn gegevens), en de privacyverklaring op `/privacy.php`  
**Wie:** bestuur en admin. Een dossier verwijderen vraagt verwijderrecht op Sollicitanten, gegevens corrigeren bewerkrecht.

> **In het kort:** Een sollicitant kan veel zelf in het portaal: alle gegevens downloaden, het account
> verwijderen, het berichtkanaal kiezen, onthouden apparaten vergeten en een seintje geven of
> intrekken. Komt een verzoek bij het bestuur binnen, antwoord dan binnen een maand en controleer
> eerst of het echt de betrokkene is. Bezwaar tegen de AI-analyse leg je vast bij het volledige
> formulier. Stuur nooit een BSN, ID-kopie, bankpas of gezondheidsverklaring per mail. Welke
> gegevens we waarvoor verwerken, staat in het verwerkingsregister.

Het **verwerkingsregister** (AVG artikel 30) staat onder **Hulp en handleidingen** als
**Verwerkingsregister (AVG)**. Het beschrijft per onderdeel welke persoonsgegevens we verwerken,
op welke grond, wie ze krijgt en hoe lang we ze bewaren. Gebruik het bij een verzoek om inzage
("wat hebben jullie van mij?"), bij vragen over de privacyverklaring en bij een datalek. Verandert
er iets in wat we verwerken, dan hoort het register mee te veranderen.

### Wat de sollicitant zelf kan

Onder **Mijn gegevens** in het portaal:

- **Wat we van je weten**: naam, e-mail, telefoon, geboortedatum, aanmelddatum en status. Klopt
  iets niet, dan stelt de sollicitant een vraag via Berichten en past het bestuur het aan (zie
  [Een verzoek om correctie](#een-verzoek-om-correctie)).
- **Hoe wil je berichten ontvangen?**: WhatsApp, Sms of Alleen e-mail, met **Voorkeur opslaan**.
- **Al mijn gegevens downloaden** met **Download als JSON**: alles wat over de sollicitatie is
  vastgelegd. Dat zijn de persoonsgegevens en de stand van de sollicitatie, het
  kennismakingsformulier met de wijzigingsgeschiedenis, de AI-analyse (als die er is), de hele
  beschikbaarheid (ook verzoeken, correcties van het bestuur en de tekst voor de AI-hulp), de
  selectiedag (groep, inchecken, cijfers, observaties en besluit), het trainingsweekend
  (aanwezigheid, observaties en besluit), de notities van het bestuur, van ondertekeningen en
  documenten de gegevens erover, de verstuurde berichten (datum, onderwerp, kanaal en stand) en de
  onthouden apparaten. Er staan geen BSN, IBAN of handtekening in (die staan in de ondertekende
  PDF's) en geen bestanden. Bij cijfers en observaties staat "beoordelaar" in plaats van de naam van
  het bestuurslid of de begeleider. Houd daar rekening mee als je een notitie of observatie schrijft:
  de sollicitant kan die lezen.
- **Mijn account verwijderen**: de sollicitant klapt **Definitief verwijderen** open, leest wat er
  gebeurt, typt VERWIJDER en klikt op **Ja, verwijder mijn account**. Naam, contactgegevens,
  formulier, beschikbaarheid, beoordelingen, taken en documenten worden meteen gewist of
  geanonimiseerd, net als bij [Alles van deze persoon](#een-dossier-verwijderen). Het bestuur krijgt
  de melding "Sollicitant heeft zich teruggetrokken". Dat kan ook als het loondossier al verwerkt
  is. Dan blijft alleen wat de wet eist: het ondertekende loonheffingsformulier, de kopie van het
  identiteitsbewijs, en de naam en geboortedatum om het dossier terug te vinden. Het IP-adres in
  het bewijsspoor van dat formulier wordt ingekort. De rest gaat meteen weg. Vernietigt de
  penningmeester het loondossier na de bewaarplicht, dan verdwijnen ook naam en geboortedatum.
- **Ingelogd blijven**: als er apparaten onthouden worden, staat hier **Vergeet alle apparaten**.

Onder elk bericht staat een afmeldlink. Een afgewezen sollicitant ziet op de startpagina ook de
seintje-vraag (zie [Seintje bij de volgende werving](#seintje-bij-de-volgende-werving)).

### De privacyverklaring

De privacyverklaring staat op `/privacy.php`. Het aanmeldformulier, de wervingspagina's en de
WordPress-plugin linken ernaar. De bewaartermijnen in de tekst komen uit dezelfde instellingen als
het opschonen, en een alinea over AI, WhatsApp of SMS verschijnt alleen als die dienst aanstaat.
Bij elke aanmelding wordt vastgelegd welke versie iemand las (nu 2026-09c). Daarin staan de
gezondheidsverklaring en de intake bij de uitnodiging voor de selectiedag, en voor de loondocumenten
"5 jaar na het einde van het jaar waarin je voor het laatst bij ons werkte; de penningmeester
vernietigt ze daarna". Het vinkje op het formulier betekent
"ik heb dit gelezen", geen toestemming: een sollicitatie verwerken we omdat dat nodig is om een
overeenkomst te kunnen sluiten.

### Een verzoek om inzage

1. Controleer of het verzoek van de sollicitant zelf komt, of bij een minderjarige van de ouder.
2. Wijs eerst op **Mijn gegevens** › **Download als JSON** in het portaal: dat is meteen compleet
   (zie hierboven wat erin zit). Wil de persoon ook weten waarvoor en hoe lang, verwijs dan naar de
   privacyverklaring; het verwerkingsregister is de bron als je iets moet nazoeken.
3. Wil de persoon het anders, open dan het dossier, tabblad **Kennismakingsformulier**, en klik op
   **Afdrukweergave en wijzigingsgeschiedenis**. Daar staan **Afdrukken**, **PDF** (een printbare
   pagina; kies "Opslaan als PDF") en **Excel**. Die export bevat de persoonsgegevens, het formulier,
   de beschikbaarheid in gewone taal, welke documenten getekend zijn en de stand van het
   loondossier. BSN en IBAN staan er nooit in.
4. Getekende documenten open je in het dossier op het tabblad Ondertekeningen. De sollicitant en de
   ouder kregen die ook zelf per mail.
5. Antwoord binnen een maand.

### Een verzoek om correctie

Gegevens uit het kennismakingsformulier kan de sollicitant zelf aanpassen in het portaal; de
wijzigingen zie je in de wijzigingsgeschiedenis. De kanaalvoorkeur zet je in het dossier bij
**Berichten via**, of de sollicitant doet het zelf. Een verkeerde naam, een verkeerd e-mailadres,
een verkeerd telefoonnummer of een verkeerde geboortedatum corrigeer je in het dossier met de kaart
[Gegevens corrigeren](#gegevens-corrigeren). De sollicitant vraagt dat aan via een vraag in
Berichten. Controleer eerst of het verzoek echt van de sollicitant komt. De correctie komt in het
logboek als "Gegevens gecorrigeerd". Antwoord binnen een maand.

### Een dossier verwijderen

Wijs eerst op de knop in het portaal: dan regelt de sollicitant het zelf en wordt alles meteen
geanonimiseerd. Moet het bestuur het doen:

1. Open het dossier, tabblad **Dossier**. Onderaan staat het rode blok **Sollicitant verwijderen**
   met wat er meegaat.
2. Kies **Alles van deze persoon…** bij een verwijderverzoek (AVG), of **Alleen deze
   sollicitatie…** als alleen dit seizoen weg moet.
3. Lees op het tussenscherm wat er verdwijnt en bevestig met **Ja, alles wissen** of **Ja,
   verwijderen**.
4. Heeft de persoon een verwerkt loondossier, dan staat op het tussenscherm het vinkje **Het
   dienstverband is niet doorgegaan**. Vink dat alleen aan als deze persoon nooit heeft gewerkt: dan
   gaan ook het loonheffingsformulier en de ID-kopie weg. Zonder vinkje wordt er niets verwijderd,
   want die stukken vallen onder de wettelijke bewaarplicht. Het vinkje zie je alleen met het recht
   Loondossiers verwijderen (standaard alleen de beheerder); zonder dat recht krijg je een melding.
   Heeft de persoon wel gewerkt en wil die toch weg, wijs dan op de knop in het portaal: die wist
   alles behalve het loondossier (zie [Wat de sollicitant zelf kan](#wat-de-sollicitant-zelf-kan)).

**Alles van deze persoon** maakt alle sollicitaties leeg en anonimiseert de persoon, precies zoals
de knop in het portaal; in het logboek staat "Alle gegevens gewist (AVG-verzoek)", zonder naam.
**Alleen deze sollicitatie** haalt de sollicitatie van dit seizoen weg; naam en e-mail blijven
bestaan voor andere seizoenen. Dat komt in het logboek als "Sollicitatie verwijderd".

In beide gevallen gaat alles van die sollicitatie mee: het kennismakingsformulier met foto en
wijzigingen, de AI-analyse, de hele beschikbaarheid (ook verzoeken, correcties en de tekst voor
de AI-hulp), de selectiedag (indeling, cijfers, observaties, besluit), het trainingsweekend
(aanwezigheid, observaties, besluit), de notities, de taken en documenten, de onthouden apparaten
("blijf ingelogd") en de berichten in de wachtrij. Van ondertekeningen blijft alleen staan dát er
getekend is (soort en moment); naam, e-mail, IP-adres en browser in het bewijsspoor gaan weg. In
de loonplanning blijven alleen de ingeplande dagen staan voor het kostenoverzicht; de notitie
verdwijnt.

### Bezwaar tegen de AI-analyse

Een sollicitant mag bezwaar maken tegen de automatische beoordeling van het kennismakingsformulier
door de AI-dienst. De privacyverklaring zegt dat dit kan via een vraag in Berichten of per mail.
Komt zo'n verzoek binnen:

1. Open het dossier, tabblad **Kennismakingsformulier**, en klik op **Afdrukweergave en
   wijzigingsgeschiedenis** (de pagina met het volledige formulier).
2. Klik in het blok **AI Geschiktheidsanalyse** op **Bezwaar vastleggen…** en bevestig met **Ja,
   vastleggen**. Dit vraagt bewerkrecht op Sollicitanten.
3. Een bestaande analyse wordt gewist en de sollicitatie gaat uit de wachtrij. Er wordt daarna
   nooit meer een analyse gemaakt, ook niet automatisch of met een knop. Op de pagina staat dan dat
   de sollicitant bezwaar heeft gemaakt.
4. Laat de sollicitant weten dat het is geregeld. Het bezwaar staat in het logboek.

Dit is niet terug te draaien. Het bezwaar heeft geen gevolgen voor de sollicitatie zelf: het
bestuur beoordeelt het formulier dan gewoon zonder AI-advies.

### Wat je nooit per mail doorstuurt

Stuur nooit een BSN, een kopie identiteitsbewijs, een bankpas of de inhoud van een
gezondheidsverklaring per mail door, ook niet intern. Verwijs de penningmeester naar Loondossiers en
laat het vinkje voor loondossier-stukken als bijlage uit. Print alleen wat de administratie echt
nodig heeft en bewaar het afgeschermd.

---

## Bewaartermijnen

**Waar:** Instellingen › Bewaren & opschonen  
**Wie:** bestuur en admin; het opschoon-logboek in het Logboek alleen admin.

> **In het kort:** Het systeem ruimt persoonsgegevens automatisch op zodra de bewaartermijn
> verstreken is, maar **alleen als Automatisch opschonen inschakelen aanstaat**. Op een nieuwe
> installatie staat dat vinkje uit, en dan wordt er niets opgeruimd, ook niet wat de
> privacyverklaring belooft. Zet het dus aan. De opschoning draait één keer per dag mee met de
> automatische taken. Loondocumenten zijn de uitzondering: die vernietigt de penningmeester met de
> hand. Wat er is opgeruimd zie je in het opschoon-logboek en in het Logboek onder AVG-opschoning.

### Opschonen aanzetten

Zolang automatisch opschonen uit staat, zie je een rode waarschuwing: bovenaan dit tabblad, in de
systeemstatus op Vandaag (met de knop **Opschonen aanzetten** voor wie Instellingen mag bewerken) en
als fout in de systeemcontrole. Het staat bewust standaard uit, want aanzetten is niet terug te
draaien: bij de eerstvolgende dagelijkse run verdwijnt alles wat al over de termijn is.

1. Open Instellingen › **Bewaren & opschonen**.
2. Controleer eerst de termijnen.
3. Vink **Automatisch opschonen inschakelen** aan en klik op **Opslaan**.
4. Controleer de volgende dag onder **Opschoon-logboek** of er "Laatst uitgevoerd" staat, en onder
   [Systeem & Updates](#systeem-en-foutenlog) of de controle **AVG-opschoning** groen is.

Een termijn op 0 betekent: die soort niet automatisch opschonen. Gezondheidsgegevens zijn de
uitzondering: daar heeft 0 een eigen betekenis (zie de tabel).

### De termijnen

| Soort gegeven | Instelling (standaard) | Wat er gebeurt |
|---|---|---|
| Afgewezen sollicitanten | **Afgewezen sollicitanten, in weken** (4) | Gerekend vanaf de laatste wijziging van de sollicitatie, bij een afwijzing het moment van afwijzen. De persoon wordt geanonimiseerd: formulier, foto, beschikbaarheid, notities, beoordelingen, taken en documenten weg, adressen en inhoud in het berichtenlog weg. De statistiek blijft. Alleen als élke sollicitatie van die persoon is afgewezen; wie om een seintje vroeg, blijft tot dat verloopt. |
| Seintje bij de volgende werving | **Seintje bij de volgende werving** (12 maanden, maximaal 12) | Zo lang geldt de toestemming. Daarna wordt de persoon geanonimiseerd zoals hierboven. |
| Oud-strandwachten | **Oud-lifeguards, in jaren** (2) | Gerekend vanaf het einde van het laatste seizoen waarin iemand werkte. Het dossier wordt leeggemaakt; loonheffing en ID-kopie blijven staan tot de penningmeester ze vernietigt, en pas daarna wordt ook de naam geanonimiseerd. Wie een nieuwe sollicitatie heeft lopen, valt erbuiten. |
| Gezondheidsgegevens | **Gezondheidsgegevens** (0) | 0 betekent: na afloop van het seizoen, en meteen bij afwijzen. Een ander getal betekent zoveel maanden na ondertekening. Het gaat om de gezondheidsverklaring en de gezondheidsvragen in de intake, ook in de verzegelde PDF. Het feit dát er getekend is blijft. |
| AI-invoertekst | **AI-invoertekst** (3 maanden) | De vrije beschikbaarheidstekst die naar de AI-dienst ging wordt gewist. |
| Niet-voltooide aanmeldingen | **Niet-voltooide aanmeldingen** (3 maanden) | Aanmeldingen die op Aangemeld bleven staan zonder formulier of beschikbaarheid worden verwijderd. |
| Berichtenlog | **Berichtenlog (alle kanalen)** (12 maanden) | De logregels worden verwijderd. |
| Inhoud van berichten | **Inhoud van berichten** (3 maanden) | Alleen de tekst wordt leeggemaakt; onderwerp en samenvatting blijven. |
| Losse bijlagen | **Losse bijlagen** (12 maanden) | Bestanden die aan geen sjabloon meer hangen worden verwijderd. |
| Bezoekersstatistiek | **Bezoekersstatistiek wervingspagina's** (13 maanden) | De aantallen worden verwijderd. |
| Herkenning unieke bezoekers | **Herkenning unieke bezoekers** (30 dagen) | Het dag-pseudoniem wordt gewist; de aantallen blijven. |
| Auditregels (logboek) | **Auditlog (wie deed wat)** (12 maanden) | De regels worden verwijderd. |
| Foutenlog | **Foutenlog** (90 dagen) | Technische foutmeldingen, waarin een e-mailadres of een webadres kan staan, worden verwijderd. |
| Berichtenwachtrij | vast (30 dagen) | Afgehandelde en mislukte berichten in de wachtrij worden verwijderd; het overzicht staat in het berichtenlog. |
| Kopie bankpas | **Kopie bankpas** (30 dagen na verwerking) | Vernietigd nadat de penningmeester het dossier op verwerkt heeft gezet. Handmatig kan eerder met **Vernietigen** bij Loondossiers. Staat opschonen uit, dan moet de penningmeester dit zelf doen. |
| Loonheffingsformulier en ID-kopie | geen automatische termijn | Wettelijke bewaarplicht: 5 jaar na het einde van het kalenderjaar waarin het dienstverband eindigde. Het systeem weet niet wanneer dat was en wist ze daarom niet zelf. De penningmeester vernietigt ze met de hand via de kaart **Te beoordelen voor vernietiging** bij [Loondossiers](#loondossiers). |

Onder de termijnen staan nog twee schakelaars, allebei standaard aan:

- **Mail naar het bestuur bij elke download van een loondocument**: zo valt onverwachte inzage op.
  De mail bevat geen document.
- **Bezoekersstatistiek op de wervingspagina's en het aanmeldformulier**: telt bezoeken en bij
  welke stap mensen afhaken, zonder cookies en persoonsgegevens. Uit is: er wordt niets meer
  geteld.

Los van het vinkje ruimt het systeem ook dit op:

- mislukte inlogpogingen na 24 uur;
- gebruikte of verlopen inlog- en verificatiecodes, en oude tellers van de snelheidsbegrenzing;
- verlopen "blijf ingelogd"-tokens van het portaal (60 dagen);
- fotobestanden van dossiers die al verwijderd zijn.

Een AVG-verzoek ("Alles van deze persoon" in het dossier, of de knop in het portaal) neemt ook de
berichten van die persoon in de wachtrij mee.

De privacyverklaring noemt dezelfde getallen, want die komen uit deze instellingen. Pas je een
termijn aan, lees de verklaring dan even na.

### Waar je het terugziet

- **Opschoon-logboek** (op hetzelfde tabblad): de laatste 20 opruimacties met datum, categorie en
  aantal, en wanneer de opschoning voor het laatst draaide. Er komt alleen een regel bij als er iets
  is opgeruimd.
- **Logboek › AVG-opschoning**: dezelfde acties in de tijdlijn, bijvoorbeeld "Afgewezen
  sollicitaties geanonimiseerd", "Gezondheidsgegevens verwijderd", "Berichtinhoud geleegd" of "Kopie
  bankpas vernietigd".
- **Systeem & Updates › AVG-opschoning**: oranje als de opschoning meer dan drie dagen niet draaide.
  Staat automatisch opschonen uit, dan is deze controle rood met "Automatisch opschonen staat uit —
  er wordt niets verwijderd".

---

## Systeem en foutenlog

**Waar:** Instellingen › kaart Versie › **Systeem & updates**; fouten staan in Logboek › **Fouten**  
**Wie:** Systeem & Updates: bewerkrecht op Systeem, updates & logs (standaard alleen admin). Fouten: leesrecht op Logboek (standaard alleen admin).

> **In het kort:** Systeem & Updates toont de versie, een systeemcontrole met acht onderdelen, de
> herinneringen (proefdraaien) en de changelog. Wordt iets rood of oranje, dan lees je hier wat er
> mis is. Via de controle Kluis wissel je de kluissleutel. Een technische fout heeft een referentienummer; dat zoek je op in het Logboek onder Fouten.
> Een los foutenlog met een knop om het te legen bestaat niet meer.

### Systeem & Updates

Bovenaan staat de **geïnstalleerde versie** met **Migraties uitvoeren**. Het systeem controleert en
herstelt de database normaal zelf bij elk bezoek. Klopt er na een update toch iets niet, klik dan
op deze knop. Daarnaast opent **SQL-migraties** de losse SQL-migraties uit de map `sql/`, om te
bekijken en uit te voeren; dat is werk voor de technisch beheerder.

**Systeemgezondheid** geeft een totaaloordeel (Alles in orde, Aandachtspunten of Er is een probleem)
en per onderdeel een vinkje, een waarschuwing of een kruis met uitleg:

| Controle | Wat er wordt gecontroleerd |
|---|---|
| Database | Zijn alle tabellen en kolommen aanwezig? |
| Cron (automatische taken) | Draaien de drie groepen automatische taken op tijd? Berichten elke minuut, herinneringen en uitnodigen elk kwartier, Authentik en opschonen dagelijks. Ligt een groep stil, dan volgt een waarschuwing en daarna een fout: controleer de geplande taak in Plesk (staat onder Instellingen › Bewaren & opschonen). Er is geen vangnet meer bij een paginabezoek. |
| Mailwachtrij | Wachten er mails langer dan een uur? Dan draait de cron niet of klopt de mailserver niet. |
| AVG-opschoning | Is de opschoning de laatste drie dagen gedraaid? Staat automatisch opschonen uit, dan is dit een fout. |
| Mappen & rechten | Kan het systeem uploads wegschrijven? |
| Configuratie | Zijn het cron-token en het bestuursadres (Instellingen › Afzender & adressen, of de terugval in de configuratie) ingesteld? |
| Kluis (versleutelde documenten) | Kan het systeem gevoelige documenten versleuteld opslaan? Zonder kluis zijn uploads van ID en bankpas en het loonheffingsformulier geblokkeerd, en kan niemand een authenticator-app instellen. Na het wisselen van de kluissleutel komen er twee waarschuwingen bij: "... versleutelde gegevens staan nog op de oude sleutel" (rond het wisselen af) en "Niets staat meer op de oude sleutel: verwijder KLUIS_SLEUTEL_OUD ..." (laat de technisch beheerder de oude sleutel weghalen). Rood wordt het als gegevens op een sleutel staan die niet meer in de configuratie staat: die zijn onleesbaar tot de sleutel terug is. Bij de controle staat de knop **Kluissleutel wisselen**. |
| Documentzegel (PDF) | Kan het systeem PDF's verzegelen, en hoe lang is het certificaat nog geldig? Onder 60 dagen komt er een waarschuwing. |

**Controleren & repareren** draait de controle opnieuw en herstelt wat automatisch te herstellen is.

Daaronder staan:

- **Herinneringen**: de laatste automatische run, **Proefdraaien (niets versturen)** en **Nu draaien**
  (zie [Geplande berichten en herinneringen](#geplande-berichten-en-herinneringen));
- **Database**: per groep welke tabellen aanwezig zijn;
- **Logboek**: een knop **Logboek openen**;
- **Changelog**: per versie wat er veranderde, met de huidige versie open.

### Kluissleutel wisselen

Gevoelige gegevens (BSN en IBAN bij ondertekeningen, kopieën van ID en bankpas, de ondertekende
PDF's en de privésleutel van het documentzegel) staan versleuteld in de kluis. De hoofdsleutel daarvan staat in de
configuratie op de server. Die sleutel wissel je bij een vermoeden van een lek, als iemand vertrekt
die bij de configuratie kon, en verder eens per jaar.

**Waar:** Systeem & Updates › bij de controle Kluis de knop **Kluissleutel wisselen**. Dit vraagt
bewerkrecht op Systeem, updates & logs (standaard alleen admin).

Het wisselen gaat in twee delen. De technisch beheerder maakt een nieuwe sleutel en zet die in de
configuratie; die stappen staan in het runbook systeembeheer, hoofdstuk config.php, onder
"Kluissleutel wisselen". Daarna pak jij op dit scherm alle gegevens opnieuw in met de nieuwe
sleutel. Het scherm toont nooit een sleutel, en in het logboek komen alleen aantallen.

Wat je op het scherm ziet:

- **Stand van de kluis**: de huidige sleutelversie, en of er nog een oude sleutel in de
  configuratie staat (**nee**, grijs, of **ja, versie …**, oranje).
- Een tabel per soort gegeven met **Totaal**, **Huidige versie** (al op de nieuwe sleutel) en
  **Nog te doen**. Groen **niets** betekent klaar; oranje "… over" betekent dat er nog iets op de
  oude sleutel staat; rood betekent dat gegevens op een sleutel staan die niet meer in de
  configuratie staat en dus onleesbaar zijn. Dan moet de technisch beheerder de oude sleutel
  terugzetten.
- **Zo wissel je de sleutel**: de vijf stappen, met een vinkje bij wat klaar is en de stap die nu
  aan de beurt is gemarkeerd.

Opnieuw inpakken:

1. Laat de technisch beheerder eerst een back-up maken en de nieuwe sleutel in de configuratie
   zetten (stap 1 en 2).
2. Klik op **Herversleutelen** en bevestig met **Ja, herversleutelen**.
3. Het gaat in batches van 200. Na elke batch zie je de stand; klik op **Volgende batch** tot er 0
   over staat. Met **Stoppen, later verder** onderbreek je; wat klaar is wordt de volgende keer
   overgeslagen. Mislukt er iets, dan staat dat op het scherm en gaat de rest gewoon door.
4. Staat alles op de nieuwe versie, dan zie je dat in een groene melding. Laat de technisch
   beheerder daarna de back-up controleren en de oude sleutel uit de configuratie halen. Tot dat
   gebeurt, geeft de systeemcontrole een waarschuwing.

De koppeling met een authenticator-app (tweestaps) staat niet in deze tabel en wordt niet opnieuw
ingepakt. Is de oude sleutel weg, dan vraagt inloggen met een wachtwoord een code per e-mail in
plaats van een code uit de app. Laat wie een wachtwoord heeft de app daarna opnieuw instellen in
Mijn profiel (**Opnieuw instellen** met een herstelcode, of anders eerst **Tweestaps resetten**
bij Beheerders).

### Fouten opzoeken

1. Iemand meldt een foutmelding: vraag naar het **ref**-nummer dat op de foutpagina staat.
2. Open Logboek › **Fouten** en zoek op dat nummer in "Zoek in details…".
3. Je ziet de foutcode, het bericht, het bestand, de regel, het adres en de gebruiker.
4. Komt dezelfde fout vaker terug, meld hem dan bij de technisch beheerder.

Fouten worden ook gemaild naar het adres voor foutmeldingen onder Instellingen › Afzender & adressen,
hooguit één mail per foutcode per vijf minuten. Oude links naar het foutenlog komen vanzelf uit bij
Logboek › Fouten.

### Testomgeving

Op de testomgeving staat linksboven in de zijbalk het oranje label **Testomgeving**. Alles wat je
daar doet is oefenen, maar mails gaan wel echt naar de ingevulde adressen. Gebruik daar dus geen
adressen van echte sollicitanten. Zie je het label niet, dan werk je in productie.

---

## Veelgestelde vragen

**Een sollicitant zegt dat de inlogmail niet aankomt.**  
De inlogmail ("Portaal-inlogcode") bevat een knop en een code, allebei 15 minuten geldig en één
keer te gebruiken. Standaard gaat hij per e-mail én via WhatsApp/SMS. Controleer eerst het
e-mailadres in het dossier; een tikfout is de meest voorkomende oorzaak. Kijk daarna in Berichten ›
Verzonden en kies de soort "Portaal-inlogcode". Staat de regel op Verstuurd, dan zit de mail
waarschijnlijk in de spam. Staat hij op Mislukt of In de wachtrij, kijk dan in Systeem & Updates
naar de cron en de mailwachtrij. Laat de sollicitant niet blijven klikken: een nieuwe code kan pas
na een minuut. Heeft de sollicitant een verenigingsaccount, dan kan die ook via Authentik inloggen.

**De inlogknop in de mail logt niet meteen in.**  
Dat klopt. De knop opent een pagina met de knop **Open mijn portaal**. Die tussenstap is er omdat
mailscanners links vooraf openen en de link anders verbruiken. Wie **Blijf ingelogd op dit
apparaat** aanvinkt, hoeft daar 60 dagen niet opnieuw in te loggen.

**De ouder-mail is naar een verkeerd adres gegaan.**  
Zie [Ondertekeningen](#ondertekeningen): zet in het dossier nieuwe documenten klaar op het juiste
adres, of kopieer de link van het openstaande document en stuur die zelf.

**WhatsApp komt niet aan.**  
Controleer onder Instellingen › Kanalen & meldingen of WhatsApp aanstaat en stuur een testbericht
naar je eigen nummer. Kijk in Berichten › Verzonden naar de stand en de reden. "Teruggevallen" met
een pijl betekent dat het bericht per SMS of e-mail wel aankwam. Controleer in het dossier bij
**Berichten via** de voorkeur, en of er een mobiel nummer is. Boven het maximum per uur valt het
systeem terug op SMS of e-mail. WhatsReach werkt buiten de officiële WhatsApp-koppeling om; een
geblokkeerd nummer is een reëel risico.

**Hoe zet ik iemand in een andere fase zonder dat er een bericht uitgaat?**  
Wijzig de fase in het dossier en vink in het bevestigingsvenster **Zonder bericht versturen** aan.
De fase verandert, maar er gaat geen automatisch bericht naar die persoon. Zie
[De fases en wat er automatisch gebeurt](#de-fases-en-wat-er-automatisch-gebeurt).

**Een mail is "Mislukt". Wat nu?**  
Kijk in Berichten › Verzonden naar de reden. Meestal is het een verkeerd adres of een probleem met
de mailserver. Controleer Instellingen › E-mailserver met **Verstuur testmail**, en in Systeem &
Updates of de cron en de mailwachtrij in orde zijn. Is het adres fout, laat het dan corrigeren (zie
[Een verzoek om correctie](#een-verzoek-om-correctie)) en stuur het bericht opnieuw via Berichten.

**Kan ik een bericht aan iedereen in een fase sturen?**  
Ja. Ga naar Berichten › Nieuw bericht, klik bij **Aan** op de snelknop van die fase (bijvoorbeeld
**+ Aangemeld (12)**) of vink de fase aan in de volledige lijst, schrijf het bericht en verstuur.
Bij meer dan tien ontvangers gaat het via de wachtrij.

**Mag ik vorig jaar afgewezen sollicitanten uitnodigen voor de nieuwe werving?**  
Alleen wie om een seintje vroeg: die staan in de groep **Seintje gevraagd**. Anderen mag je niet
benaderen, daarvoor ontbreekt de toestemming. Oud-strandwachten mag je wel benaderen. Zie
[Seintje bij de volgende werving](#seintje-bij-de-volgende-werving).

**Worden oude gegevens echt opgeruimd?**  
Alleen als onder Instellingen › Bewaren & opschonen het vinkje **Automatisch opschonen
inschakelen** aanstaat. Op een nieuwe installatie staat het uit, en dan is de systeemstatus op
Vandaag rood. Loondocumenten worden nooit automatisch opgeruimd; die vernietigt de penningmeester.
Zie [Bewaartermijnen](#bewaartermijnen).

**Een sollicitant wil al zijn of haar gegevens zien, of weg hebben.**  
Wijs op **Mijn gegevens** in het portaal: daar staan **Download als JSON** en **Mijn account
verwijderen**. Lukt dat niet, zie [Privacy en AVG-verzoeken](#privacy-en-avg-verzoeken).

**Waar zie ik wie een document heeft gedownload of een dossier heeft bekeken?**  
In het [Logboek](#logboek) (standaard alleen admin), tabblad Audit. Klik in de kolom Waar op het
object voor de hele geschiedenis. Bij Loondossiers staat bij het stuk ook "Laatst ingezien", en het
bestuur krijgt bij elke download van een ID-kopie, bankpas of loonheffingsformulier een mail.

**De dekking is rood terwijl er genoeg mensen zijn.**  
Dekking kijkt naar competenties uit Authentik en de rollen onder Instellingen › Posten & bezetting.
Controleer of Authentik gekoppeld is, of de sollicitant in het dossier de juiste competenties heeft
(**Synchroniseren**) en of een rol geen te hoge minimale rang vraagt. Klik op de week: bij "Waarom
tekort" staat welke rol niet te vullen is en wie wel gekwalificeerd is maar niet beschikbaar.

**Een nieuwe penningmeester komt niet binnen via Authentik.**  
Controleer onder Instellingen › Authentik of **Toegestane admin-groepen** de groep Sol-Penningmeester
bevat. Die staat er standaard in, maar een eerder zelf aangepaste lijst blijft staan zoals die was.
Controleer ook in Authentik of de persoon echt in die groep zit. Komt de penningmeester wel binnen
maar als Begeleider, zet de rol dan onder Beheerders op Penningmeester.

**Ik heb iemands rol gewijzigd, maar na het inloggen staat de oude rol er weer.**  
Bij elke inlog via Authentik volgt de rol de groep in Authentik. Wijzig de groep in Authentik, of
koppel de rol onder Rollen & rechten aan een andere groep.

**Ik zie een onderdeel niet in het menu.**  
Dan heeft je rol daar geen leesrecht op. Vraag een admin om je rol te controleren onder Beheerders
& rechten. Loondossiers is bewust alleen voor penningmeester en admin; Logboek, Beheerders &
rechten en Systeem & Updates alleen voor admin.

**Waar vind ik de handleidingen?**  
Onderin het menu onder **Hulp en handleidingen**. Ook via het vraagteken rechtsboven op elk scherm
(**Lees meer in de handleiding**), of met Ctrl K en het woord "handleiding".

---

## Woordenlijst

**Anti-loop-plafond** — het maximum aantal keer dat hetzelfde automatische bericht binnen 24 uur naar dezelfde ontvanger gaat (standaard 8). Wat erboven komt, wordt overgeslagen.

**Authentik** — de centrale login van de brigade. Beheerders loggen ermee in, de groep in Authentik bepaalt de rol, en de competenties voor de dekking komen eruit.

**Bellijst** — de lijst bij de nabespreking van de selectiedag: iedereen met het besluit Door of Niet die nog persoonlijk gebeld moet worden, voordat het besluit wordt doorgevoerd.

**BLK** — "Beter leren kennen": het kennismakingsformulier dat de sollicitant na de uitnodiging invult (opleiding, diploma's, motivatie, adres, foto). In het dossier heet het tabblad Kennismakingsformulier.

**Correctie** — een aanpassing van de beschikbaarheid door het bestuur, altijd met reden. Die overschrijft wat de sollicitant opgaf en staat in de tijdlijn.

**Cron** — de automatische taken, via één geplande taak in Plesk die elke minuut draait: berichten en AI elke minuut, herinneringen, geplande berichten en automatisch uitnodigen elk kwartier, en één keer per dag de Authentik-synchronisatie en het opschonen. Draait de geplande taak niet, dan gebeurt er niets van dit alles: een bezoek aan het beheer of portaal vangt het niet meer op.

**Dossier** — de pagina van één sollicitant, met de tabbladen Dossier, Kennismakingsformulier, Beschikbaarheid, Selectiedag, Ondertekeningen en Tijdlijn.

**Fase** — de stap waarin een sollicitant staat: Aangemeld, Uitgenodigd selectiedag, Aangemeld selectiedag, Selectiedag geweest, Uitgenodigd trainingsweekend, TW geweest, Aangenomen of Afgewezen. Een fasewijziging kan automatisch een bericht en taken in gang zetten.

**Kluis** — de versleutelde opslag voor gevoelige stukken: BSN, IBAN, kopie identiteitsbewijs en bankpas. Zonder werkende kluis zijn die uploads geblokkeerd.

**Loondossier** — per aangenomen strandwacht het loonheffingsformulier, de ID-kopie, de bankpas en de inschrijving als lid. Alleen voor penningmeester en admin. Na de bewaarplicht vernietigt de penningmeester het met de hand.

**Noodingang** — inloggen met een wachtwoord in dit systeem in plaats van via Authentik, voor als Authentik niet bereikbaar is. Alleen accounts met een wachtwoord kunnen dat.

**Norm** — het aantal mensen dat per dienst nodig is, de optelsom van alle rollen onder Posten & bezetting. Daarnaast bestaat de norm per persoon: standaard vijf dagen per week, aan te passen in het dossier (tabblad Beschikbaarheid).

**Oud-strandwacht** — iemand die in een eerder seizoen is aangenomen. Die mag je bij een nieuwe werving benaderen, met een afmeldlink.

**Rustperiode** — per sollicitant hooguit één "zachte" herinnering per zoveel dagen (standaard 3).

**Scenario** — een versie van de loonplanning. Eén scenario is actief (de ster); de andere gebruik je om alternatieven door te rekenen.

**SD** — selectiedag: de dag waarop kandidaten worden getest en beoordeeld.

**Seintje** — de toestemming van een afgewezen sollicitant om bij de volgende werving een bericht te krijgen. Geldt maximaal twaalf maanden en is altijd in te trekken.

**Seizoen** — één wervingsronde met eigen datums voor werkperiode, selectiedag en trainingsweekend. Alles in het beheer draait om het huidige seizoen.

**Sjabloon** — de vaste tekst van een bericht: e-mail, korte tekst en samenvatting. Elk automatisch bericht heeft er een; eigen sjablonen gebruik je bij Berichten en Gepland.

**TW** — trainingsweekend: het weekend na de selectiedag, waarna het besluit over aannemen valt.

**Wachtrij** — berichten die niet meteen worden verstuurd maar door de cron in de komende minuten, bijvoorbeeld bij meer dan tien ontvangers tegelijk.

**Zegel** — het digitale zegel op ondertekende PDF's. Daarmee is te controleren dat het document na het tekenen niet is veranderd.

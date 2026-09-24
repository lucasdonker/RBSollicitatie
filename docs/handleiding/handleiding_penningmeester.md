# Handleiding penningmeester — Loondossiers

Versie 2.102 · september 2026

Deze handleiding is voor de penningmeester. Je werkt vooral op twee schermen in het beheer:
**Loondossiers** en **Loonplanning**. In Loondossiers staat per aangenomen strandwacht alles wat
de loonadministratie nodig heeft. Alleen de penningmeester en de admin kunnen deze stukken
inzien; het bestuur ziet dit scherm niet. In de Loonplanning zie je wie er wordt ingezet en wat
dat kost; die deel je met het bestuur.

## Inhoud

1. [Aan de slag](#aan-de-slag)
2. [Inloggen](#inloggen)
3. [Wat er in een loondossier zit](#wat-er-in-een-loondossier-zit)
4. [Wanneer je een melding krijgt](#wanneer-je-een-melding-krijgt)
5. [Het scherm Loondossiers](#het-scherm-loondossiers)
6. [Stukken downloaden](#stukken-downloaden)
7. [Een dossier verwerken](#een-dossier-verwerken)
8. [Bankpas vernietigen](#bankpas-vernietigen)
9. [Bewaartermijnen en verwijderen](#bewaartermijnen-en-verwijderen)
10. [Loonplanning](#loonplanning)
11. [Wat je nooit doet](#wat-je-nooit-doet)
12. [Veelgestelde vragen](#veelgestelde-vragen)

---

## Aan de slag

> **In het kort:** jij zet de gegevens van aangenomen strandwachten in de loonadministratie. Je
> downloadt de stukken uit Loondossiers, verwerkt ze en markeert het dossier als verwerkt. Na de
> wettelijke bewaarplicht vernietig je de loondocumenten zelf; het systeem zet ze voor je klaar.
> In de Loonplanning houd je de loonkosten tegen het budget in de gaten.

**Jouw rol.** Na het trainingsweekend neemt het bestuur strandwachten aan. Vanaf dat moment
leveren zij in het portaal hun loonheffingsformulier, een kopie van hun identiteitsbewijs en een
kopie van hun bankpas in. Jij haalt die stukken op, zet ze in de loonadministratie en markeert
het dossier daarna als verwerkt. Je beslist niet over aannemen of afwijzen; dat doet het bestuur.
Daarnaast kijk je mee in de Loonplanning: wie wordt ingezet, wat kost dat en past het in het
budget.

**Wat je doet, en wanneer:**

- [ ] **Vóór het seizoen:** log één keer in (zie [Inloggen](#inloggen)) en controleer dat je
  de menu-items Loonplanning en Loondossiers ziet. Vraag het bestuur om jouw e-mailadres in te
  vullen als penningmeester-adres (Instellingen › Afzender & adressen). Anders gaan de meldingen
  naar het bestuursadres.
- [ ] **Bij de begroting:** geef het bestuur het seizoensbudget voor loonkosten door. Het bestuur
  vult dat in bij Instellingen › Lonen & budget, samen met de minimumlonen. Daarna zie je in de
  Loonplanning of de planning erbinnen past (zie [Loonplanning](#loonplanning)).
- [ ] **Na het trainingsweekend (de aannames):** open Loondossiers. Elke aangenomen strandwacht
  staat er, ook als er nog niets is ingeleverd.
- [ ] **Zodra er een mail "Loondossier compleet" komt:** klik op de knop in de mail. Je komt direct
  bij het juiste dossier. Download de stukken en zet ze in de loonadministratie.
- [ ] **Direct daarna:** markeer het dossier als verwerkt en verwijder de gedownloade bestanden
  van je computer. Staat automatisch opschonen uit (dat zegt het kader bovenaan Loondossiers),
  vernietig dan ook de bankpasfoto zelf (zie [Bankpas vernietigen](#bankpas-vernietigen)).
- [ ] **Elke week, tot alles verwerkt is:** kijk in Loondossiers. De balk bovenaan zegt hoeveel
  dossiers nog op verwerking wachten. Staat een dossier lang op oranje, laat het bestuur de
  strandwacht dan herinneren.
- [ ] **Tijdens het seizoen, bijvoorbeeld elke maand:** open de Loonplanning en kijk onderaan naar
  de totale loonkosten en het restant van het budget. Download zo nodig een CSV als tussenstand
  voor het bestuur of de loonadministratie.
- [ ] **Elk jaar, bijvoorbeeld in januari:** kijk onderaan Loondossiers of de kaart **Te beoordelen
  voor vernietiging** er staat. Controleer die namen in de loonadministratie en vernietig wat over
  de bewaarplicht is (zie
  [Loondossiers vernietigen na de bewaarplicht](#loondossiers-vernietigen-na-de-bewaarplicht)).

**Rondleiding.** In het beheer staat een interactieve rondleiding "Penningmeester: loondossiers en
loonplanning". Die loopt met je langs de echte schermen en wijst aan wat waar staat en wat jij moet doen. De
eerste keer staat hij op Vandaag klaar in het blok **Nieuw in het beheer?**; daarna vind je hem
onder **Hulp en handleidingen** onderin het menu (of via het vraagteken rechtsboven › **Alle
rondleidingen**). Op het scherm Loondossiers kun je in het vraagteken ook **Rondleiding van dit
scherm** kiezen. Stoppen kan altijd met **Stoppen** of Esc.

## Inloggen

> **In het kort:** log in via Authentik op het beheer. Je ziet vier menu-items: Vandaag,
> Sollicitanten, Loonplanning en Loondossiers.

**Waar:** `https://sollicitatie.paal13.com/admin/`  
**Wie:** je Authentik-account zit in de groep `Sol-Penningmeester`.  

Kies "Inloggen via Authentik". In het menu zie je:

- **Vandaag**: het startscherm met de stand van het seizoen;
- **Sollicitanten**: alleen lezen, zodat je bij een dossier kunt kijken wie iemand is;
- **Loonplanning** (in de groep Inzet & loon): wie er wordt ingezet en wat dat kost; zie
  [Loonplanning](#loonplanning);
- **Loondossiers** (ook in Inzet & loon): jouw werkscherm voor de loonadministratie.

Zie je meer of minder, of krijg je de melding "Je Authentik-account heeft geen admin-toegang", dan
staat je account in Authentik in de verkeerde groep. Meld dat bij de admin.

## Wat er in een loondossier zit

> **In het kort:** het loonheffingsformulier, een kopie van het identiteitsbewijs en een kopie van
> de bankpas. Het dossier is compleet als die drie binnen zijn. De inschrijving als lid staat er ter
> informatie bij.

Per aangenomen strandwacht verzamelt het portaal:

| Stuk | Hoe het binnenkomt | Bijzonderheden |
|---|---|---|
| Loonheffingsformulier | De strandwacht vult het officiële formulier van de Belastingdienst in het portaal in en ondertekent digitaal met een verificatiecode | Verzegelde PDF; het BSN staat alleen in de PDF en versleuteld in de kluis |
| Kopie identiteitsbewijs | Foto of scan (JPG, PNG of PDF). Bij een identiteitskaart voor- én achterkant, bij een paspoort alleen de fotopagina | Wettelijk verplicht, volledig en niet afgeschermd |
| Kopie bankpas | Foto van de voorkant (naam en rekeningnummer) | Alleen om het rekeningnummer te controleren; wordt na verwerking vernietigd (automatisch, of door jou als opschonen uit staat) |
| Inschrijving als lid | Alleen voor wie nog geen Paal 13-account heeft; gaat na het ondertekenen als PDF naar de ledenservice | Ter informatie. Telt niet mee voor "compleet" |

Alles staat versleuteld in de kluis. Een dossier is **compleet** als het loonheffingsformulier is
ondertekend en de kopie van het identiteitsbewijs en de kopie van de bankpas binnen zijn.

## Wanneer je een melding krijgt

> **In het kort:** je krijgt één mail "Loondossier compleet" als alle stukken binnen zijn. De
> stukken zitten niet in die mail. Komt er geen mail, kijk dan zelf in Loondossiers.

De mail gaat naar het penningmeester-adres uit Instellingen › Afzender & adressen (is dat leeg,
dan naar het bestuursadres). In de mail staat welke stukken er zijn, met een knop "Openen in het
beheer" die direct het juiste dossier openklapt.

De stukken zitten standaard **niet** in de mail; je haalt ze op via het beheer. Het bestuur kan
instellen dat ze wél meegaan, maar dan gaan een BSN en een kopie identiteitsbewijs onversleuteld
per e-mail. Dat raden we af.

De mail komt zodra het laatste stuk binnen is, of dat nu het ondertekende loonheffingsformulier is
of een upload van de kopie ID of de bankpas. Per loonheffingsformulier krijg je hem één keer.

Mail kwijt of niet gekregen? Klik in het dossier op **Melding opnieuw naar de penningmeester**.
Die knop staat er zodra het dossier compleet is.

## Het scherm Loondossiers

> **In het kort:** bovenaan de regels voor deze stukken en de stand, daaronder per strandwacht een
> kaart die je openklapt. Oranje is nog niet compleet, groen wacht op jou, grijs is verwerkt.
> Onderaan staat, als dat nodig is, wie in aanmerking komt voor vernietiging.

**Waar:** Beheer › Loondossiers (in de menugroep Inzet & loon).  

Het scherm toont de aangenomen strandwachten van het actieve seizoen.

**Bovenaan** staat een vast kader met de regels: hier staan een BSN en kopieën van
identiteitsbewijzen, elke download wordt vastgelegd, wat er met de bankpasfoto gebeurt en hoe lang
het loonheffingsformulier en de kopie ID blijven. Komen er dossiers in aanmerking voor
vernietiging, dan staat daar ook een link naar **Te beoordelen voor vernietiging**. Dat kader staat
er altijd; het is geen waarschuwing.

Let op de zin over de bankpasfoto. Staat daar "De bankpasfoto wordt ... dagen na "verwerkt"
automatisch vernietigd", dan hoef je niets te doen. Staat daar "Automatisch opschonen staat uit",
dan vernietig je de bankpasfoto zelf na het verwerken.

Daaronder de stand, bijvoorbeeld "2 dossiers wachten op verwerking", met de tellers aangenomen,
compleet en verwerkt.

**Per strandwacht** een kaart. Klik op de naam om hem open of dicht te klappen. Rechts van de naam
staat de status:

- **Oranje, "Ontbreekt nog: …":** er ontbreken stukken; je ziet welke.
- **Groen, "Compleet — klaar om te verwerken":** wacht op jou. Deze kaarten staan standaard open.
- **Grijs, "Verwerkt" met datum:** klaar.

In een opengeklapte kaart zie je:

- een link **Naar het dossier van …**, als je meer over de persoon wilt weten;
- het **loonheffingsformulier**: wanneer het is ondertekend, hoe (verificatiecode, naar welk
  nummer) en een vingerafdruk van het bestand (sha256), met de knop **PDF**;
- **identiteitsbewijs voorkant** (met paspoort of identiteitskaart), **achterkant** ("Niet nodig
  bij een paspoort") en **kopie bankpas** ("Vernietigd ... dagen na verwerking" of "Na verwerking
  zelf vernietigen"): per stuk de bestandsnaam, de uploaddatum, de grootte en wanneer het voor het
  laatst is ingezien, met de knop **Download**;
- **lid van de vereniging**: wanneer de inschrijving naar de ledenservice is gemaild, met de knop
  **PDF**, of "Niet van toepassing" als iemand al een verenigingsaccount heeft;
- onderaan de knoppen **Verwerkt in de loonadministratie** en **Melding opnieuw naar de
  penningmeester**, en de uitklapbare **Tijdlijn van de ondertekening**. Daarin staat het
  bewijsspoor: klaargezet, geopend, verificatiecode verstuurd, code geaccepteerd, ondertekend,
  PDF verzegeld, gedownload en verwerkt, met tijdstip en IP-adres.

Staat er bovenaan een **rode melding** "De kluis is niet bereikbaar", dan kunnen strandwachten
niets uploaden en geen loonheffingsformulier ondertekenen. Waarschuw de admin. Een **oranje
melding** "Het documentzegel werkt niet" betekent dat nieuwe PDF's geen digitaal zegel krijgen;
bestaande documenten blijven geldig. Ook dan de admin waarschuwen.

Staat er nog niemand op "Aangenomen", dan zie je "Nog geen loondossiers".

**Onderaan** verschijnt de kaart **Te beoordelen voor vernietiging** zodra er iemand is van wie
de bewaarplicht mogelijk voorbij is. Zie
[Loondossiers vernietigen na de bewaarplicht](#loondossiers-vernietigen-na-de-bewaarplicht).

## Stukken downloaden

> **In het kort:** download via de knoppen in de kaart, alleen op een vertrouwd apparaat. Elke
> download wordt vastgelegd en foto's krijgen een watermerk met jouw naam.

1. Klik op **Download** of **PDF** achter het stuk.
2. Op foto's (JPG of PNG) van het identiteitsbewijs en de bankpas staat een watermerk: "Kopie voor
   loonadministratie Reddingsbrigade Paal 13", de datum en jouw naam. Het origineel in de kluis
   blijft ongewijzigd. Een kopie die als PDF is geüpload krijgt geen watermerk.
3. Elke download wordt vastgelegd (wie, wanneer, welk stuk). Bij het identiteitsbewijs, de bankpas
   en het loonheffingsformulier krijgt het bestuur standaard een mail dat jij iets hebt gedownload,
   met de naam van het document. Dat is normaal en hoort zo.
4. Je kunt maximaal 30 stukken per uur downloaden.
5. Een downloadlink werkt alleen vanuit het beheer. Een link doorsturen heeft geen zin.

Zet de stukken direct in de loonadministratie. Verwijder daarna de gedownloade bestanden van je
computer, ook uit je downloadmap en de prullenbak.

## Een dossier verwerken

> **In het kort:** staan de gegevens in de loonadministratie, klik dan op "Verwerkt in de
> loonadministratie" en bevestig. Dat is niet terug te draaien.

1. Klik in de kaart op **Verwerkt in de loonadministratie**. Die knop staat er alleen bij een
   compleet dossier.
2. Je krijgt een apart scherm dat uitlegt wat er gebeurt. Klik op **Ja, verwerkt**.

Wat er dan gebeurt:

- De strandwacht kan de stukken niet meer vervangen of verwijderen.
- De kopie van de bankpas wordt na de ingestelde termijn (standaard 30 dagen) automatisch
  vernietigd, als automatisch opschonen aanstaat. Het tussenscherm zegt welke van de twee geldt.
  Staat opschonen uit, vernietig de bankpasfoto dan zelf (zie [Bankpas vernietigen](#bankpas-vernietigen)).
- Het loonheffingsformulier en de kopie van het identiteitsbewijs blijven staan tot jij ze na de
  bewaarplicht vernietigt (zie [Bewaartermijnen en verwijderen](#bewaartermijnen-en-verwijderen)).
- Verwijdert de strandwacht daarna zelf het account, dan blijft het loondossier staan omdat de
  wettelijke bewaarplicht voorgaat: het loonheffingsformulier, de kopie ID, de naam en de
  geboortedatum. Al het andere verdwijnt. Het portaal legt dat vooraf uit. In het overzicht van het
  seizoen staat de persoon dan niet meer, maar na de bewaarplicht verschijnt die gewoon in **Te
  beoordelen voor vernietiging**. Vernietig je het loondossier, dan verdwijnen ook naam en
  geboortedatum.

Markeer een dossier pas als verwerkt als het echt verwerkt is. Zelf terugdraaien kan niet; vraag
dan de admin.

## Bankpas vernietigen

> **In het kort:** de bankpasfoto verdwijnt vanzelf als automatisch opschonen aanstaat. Staat dat
> uit, of moet hij eerder weg, klik dan op "Vernietigen" en bevestig.

Klik op **Vernietigen** achter de kopie van de bankpas. Op het volgende scherm lees je wat er
gebeurt en klik je op **Ja, vernietigen**. Het bestand wordt uit de kluis gewist en is niet terug
te halen. Dat het er was en wie het vernietigde, blijft in het logboek staan. De rest van het
dossier blijft ongemoeid.

Dit kan per stuk alleen bij de bankpas. De andere stukken vallen onder de bewaarplicht; die
vernietig je pas daarna, in één keer per persoon (zie
[Loondossiers vernietigen na de bewaarplicht](#loondossiers-vernietigen-na-de-bewaarplicht)).

## Bewaartermijnen en verwijderen

> **In het kort:** loonheffing en kopie ID moeten blijven tot 5 jaar na het einde van het jaar
> waarin het dienstverband eindigde. Het systeem wist ze niet zelf: jij vernietigt ze daarna, vanuit
> een lijst die het systeem klaarzet. De bankpasfoto verdwijnt 30 dagen na verwerking vanzelf, maar
> alleen als het bestuur het automatisch opschonen heeft aangezet.

| Stuk | Termijn | Wie ruimt op |
|---|---|---|
| Loonheffingsformulier en kopie identiteitsbewijs | 5 jaar na het einde van het kalenderjaar waarin het dienstverband eindigde (wettelijke bewaarplicht loonadministratie) | Jij, met de hand (zie hieronder) |
| Kopie bankpas | 30 dagen nadat jij het dossier als verwerkt hebt gemarkeerd (instelbaar) | Het systeem, als automatisch opschonen aanstaat; anders jij |
| Inschrijving als lid | Volgt het dossier: 2 jaar na het laatste seizoen | Het systeem, als automatisch opschonen aanstaat |

Het automatische opruimen gebeurt één keer per dag, **maar alleen als "Automatisch opschonen
inschakelen" aan staat** bij Instellingen › Bewaren & opschonen. Dat staat standaard uit. Je ziet
op Loondossiers in het kader bovenaan welke van de twee geldt. De termijn voor de bankpas stelt het
bestuur in op hetzelfde tabblad (**Kopie bankpas**). Wat er is vernietigd, ziet de admin in het
Logboek.

### Loondossiers vernietigen na de bewaarplicht

Het systeem weet tot welk seizoen iemand hier voorkwam, maar niet wanneer het dienstverband
eindigde. Iemand kan na een pauze terugkomen. Daarom vernietigt het systeem het
loonheffingsformulier en de kopie ID nooit zelf. Het zet wel klaar wie in aanmerking komt, onderaan
Loondossiers in de kaart **Te beoordelen voor vernietiging**.

Daarin staat iedereen van wie het laatste seizoen als aangenomen (of met loonstukken) minstens vijf
volle kalenderjaren terug ligt. Wie in 2020 voor het laatst werkte, verschijnt op 1 januari 2026.
Per persoon zie je "Laatste seizoen hier" en "Nog aanwezig" met de stukken die er nog zijn. Staat
er niemand in aanmerking, dan is de kaart er niet.

1. Controleer in de loonadministratie of het dienstverband toen echt eindigde. Werkte iemand daarna
   nog, vernietig dan niets.
2. Klik op **Vernietigen…** bij de naam.
3. Het tussenscherm noemt precies wat onherroepelijk weggaat. Klik op **Ja, loondossier
   vernietigen**.

Wat er dan gebeurt:

- De stukken worden uit de kluis gewist en zijn niet meer terug te halen, ook niet door een
  beheerder.
- Van het loonheffingsformulier verdwijnen ook het BSN, de handtekening en de ingevulde gegevens.
  Als bewijs blijft staan dát er getekend en geüpload is.
- De rest van het dossier blijft staan: gegevens, sollicitaties en andere ondertekeningen.
- In het logboek komt "Loondossier vernietigd (na de bewaarplicht)", met wie het deed, zonder de
  inhoud.

Doe deze controle één keer per jaar, bijvoorbeeld in januari. Zie de afvinklijst in
[Aan de slag](#aan-de-slag).

### Terugtrekken

Trekt een strandwacht zich terug vóórdat het dossier verwerkt is, dan kan die de gegevens zelf
verwijderen in het portaal; de stukken worden dan meteen vernietigd. Na het verwerken kan dat ook,
maar dan blijft het loondossier staan (zie [Een dossier verwerken](#een-dossier-verwerken)). Is
het dossier al verwerkt en gaat het dienstverband toch niet door, dan kan alleen iemand met zowel het recht om sollicitanten
te verwijderen als het recht om loondossiers te verwijderen het dossier verwijderen (standaard
alleen de admin). Het systeem vraagt dan met een apart vinkje om te bevestigen dat het
dienstverband niet is doorgegaan.

## Loonplanning

**Waar:** Beheer › Loonplanning (in de menugroep Inzet & loon)  
**Wie:** penningmeester, bestuur en admin. Jij mag de planning bekijken en bewerken.

> **In het kort:** de loonplanning laat per persoon en per week zien wie er wordt ingezet en wat
> dat kost, in één of meer scenario's. Jij gebruikt hem om de loonkosten tegen het budget te
> houden en als tussenstand voor de begroting. Het budget en de lonen zelf stelt het bestuur in.

Het bestuur maakt meestal de planning; jij kijkt vooral naar de kosten. Je kunt ook zelf dagen
aanpassen of een scenario maken. Elke wijziging wordt meteen opgeslagen en het bestuur ziet hem
ook. Stem dus af voordat je iets in de actieve planning verandert.

**Scenario's.** Bovenaan staan de scenario's. De ster met het woord **Actief** wijst de planning
aan die geldt. Wil je een alternatief doorrekenen, bijvoorbeeld "wat als we in juni twee mensen
minder inzetten?", klik dan op **+ Dupliceren**, geef het scenario een naam en klik op
**Dupliceren**. Je werkt dan in een kopie; de actieve planning blijft zoals hij was. Bij een
scenario dat niet actief is staan **Maak actief** en **Verwijderen** (met bevestiging). Maak een
scenario alleen actief als het bestuur dat heeft besloten.

**Kosten per persoon en per week.** In de tabel staat per persoon een rij en per week een kolom:

- met een vinkje neem je iemand die week op; het systeem vult dan vijf dagen in, en dat aantal
  kun je aanpassen. Een "–" betekent dat de persoon die week niet beschikbaar is;
- in elke cel staan de kosten van die week, rechts in de rij de totale kosten en dagen van die
  persoon;
- de **Functie** per persoon (keuzelijst) bepaalt mee het dagloon;
- de onderste rijen tonen de dekking per week en de regel **Kosten p/w**: de loonkosten van die
  week voor iedereen samen.

Het dagloon hangt af van de leeftijd, de functie en de maand (juni heeft andere lonen dan juli
tot en met september). Klap **Loontabel (dagloon per leeftijd & functie)** open om te zien welk
dagloon het systeem gebruikt. Boven de tabel staan eventueel **Signalen**, bijvoorbeeld iemand
die meer dagen staat ingepland dan die kan. Dat zijn aandachtspunten, geen blokkades.

**Budget.** Onderaan staan de **Totale loonkosten** en de mandagen. Is er een budget ingesteld, dan
zie je een balk met **Restant** of **Over budget**. Staat er "Geen budget ingesteld", dan moet het
bestuur het budget nog invullen bij Instellingen › Lonen & budget. Daar staan ook de minimumuurlonen
per leeftijd, het vakantietoeslagpercentage en de werkuren per dag. Die instellingen kun jij niet
wijzigen; klopt er iets niet, bijvoorbeeld na een nieuwe verhoging van het wettelijk minimumloon,
geef het dan door aan het bestuur.

**CSV-export.** De knop **CSV** bij de scenario's downloadt het getoonde scenario als bestand voor
Excel: per persoon de naam, de leeftijd, de functie, de beschikbare en ingeplande weken, de
ingeplande dagen en de totale kosten. Gebruik het als tussenstand voor de begroting of als
controle bij de loonadministratie. Er staan geen BSN of rekeningnummers in, maar wel namen:
bewaar het bestand niet langer dan nodig.

## Wat je nooit doet

> **In het kort:** stukken blijven in het beheer en in de loonadministratie. Niet mailen, niet
> kopiëren, niet delen.

- Stukken per e-mail of WhatsApp doorsturen, ook niet naar jezelf. Gebruik het beheer.
- Een BSN of rekeningnummer in een spreadsheet, notitie of chat zetten buiten de
  loonadministratie.
- Je inlog delen. Elke download staat op jouw naam.
- Screenshots maken van kopieën van identiteitsbewijzen.
- Gedownloade bestanden laten staan op je computer.

## Veelgestelde vragen

> **In het kort:** de meeste vragen gaan over een dossier dat oranje blijft, een verificatiecode
> die niet aankomt of het watermerk.

**Een dossier blijft oranje terwijl de strandwacht zegt dat alles is geüpload.**  
Kijk achter "Ontbreekt nog" welk stuk mist. Bij een identiteitskaart moet ook de achterkant erbij;
bij een paspoort niet. Het loonheffingsformulier telt pas mee na het ondertekenen met de
verificatiecode.

**Het dossier is groen, maar ik heb geen mail gekregen.**  
De mail gaat uit zodra het laatste stuk binnen is. Kijk eerst in je ongewenste mail en of het
penningmeester-adres in Instellingen › Afzender & adressen klopt (leeg is: het bestuursadres).
Verwerk het dossier gewoon, of klik op "Melding opnieuw naar de penningmeester" als je de mail toch
wilt hebben.

**De strandwacht krijgt geen verificatiecode.**  
De code gaat per sms naar het telefoonnummer uit het dossier; staat sms uit, dan via WhatsApp,
en anders per e-mail. Klopt het nummer niet, laat het bestuur het dan aanpassen in het dossier.
Blijft het misgaan, dan is het iets voor de admin (foutenlog).

**Ik zie een watermerk op de ID-kopie. Kan dat weg?**  
Nee. Het watermerk is bewust en beschermt tegen misbruik van de kopie. Voor de loonadministratie
is een kopie mét watermerk gewoon geldig.

**Het formulier van vorig jaar staat er niet meer.**  
Het scherm toont alleen het actieve seizoen. Stukken van eerdere seizoenen blijven in de kluis tot
jij ze na de bewaarplicht vernietigt; vraag de admin als je ze nodig hebt.

**Is de PDF echt door de strandwacht ondertekend?**  
De laatste pagina van de PDF is het certificaat van ondertekening: wie, wanneer, vanaf welk
IP-adres, met welke code naar welk nummer, en een controlegetal. Acrobat toont bij het zegel dat
het document sinds de ondertekening niet is gewijzigd, met een melding dat de uitgever niet in de
lijst van Adobe staat. Dat is normaal voor een eigen zegel.

**Waarom krijgt het bestuur een mail als ik iets download?**  
Zo ziet iemand anders mee dat deze stukken worden ingezien. Dat beschermt de strandwacht en
jou. Het bestuur kan die melding uitzetten onder Instellingen › Bewaren & opschonen, maar dat
raden we af.

**Ik zie een naam bij "Te beoordelen voor vernietiging" die nog bij ons werkt.**  
Vernietig dan niets. Het systeem kijkt alleen naar het laatste seizoen waarin iemand hier als
aangenomen voorkwam. Werkte die persoon later nog, bijvoorbeeld zonder nieuwe sollicitatie, dan
loopt de bewaarplicht langer. De naam blijft in de lijst staan; dat is geen probleem.

**Ik zie Loonplanning niet in het menu.**  
De penningmeester heeft het recht Loonplanning standaard. Een admin kan het bij Rollen & rechten
hebben weggehaald; vraag de admin om het terug te zetten als je het nodig hebt.

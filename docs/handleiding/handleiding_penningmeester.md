# Handleiding penningmeester — Van beschikbaarheid tot loondossier

Versie 2.126 · september 2026

Deze handleiding is voor de penningmeester van Reddingsbrigade Paal 13. Je hoeft geen kenner van
het systeem te zijn: hier staat hoe de hele keten werkt, van de beschikbaarheid die een sollicitant
opgeeft tot het loon dat je uitbetaalt, en wat jij op welk moment doet.

Je werkt vooral op twee schermen in het beheer: **Loonplanning** (wie wordt ingezet en wat dat
kost) en **Loondossiers** (de stukken voor de loonadministratie). De rest van de procedure doet het
bestuur; wat daarvan voor geld en loon telt, leggen we hier ook uit.

Elk hoofdstuk begint met een blok **In het kort**. De afvinklijsten kun je in het beheer echt
aanvinken; dat wordt op je eigen apparaat onthouden.

## Inhoud

1. [Aan de slag](#aan-de-slag)
2. [Het grote plaatje](#het-grote-plaatje)
3. [Wat je per seizoen doet](#wat-je-per-seizoen-doet)
4. [Inloggen en je menu](#inloggen-en-je-menu)
5. [Beschikbaarheid: wat sollicitanten opgeven](#beschikbaarheid-wat-sollicitanten-opgeven)
6. [Loonplanning](#loonplanning)
7. [Trainingsweekend en het gesprek](#trainingsweekend-en-het-gesprek)
8. [Na het aannemen: het takenpakket](#na-het-aannemen-het-takenpakket)
9. [Wanneer je een melding krijgt](#wanneer-je-een-melding-krijgt)
10. [Het scherm Loondossiers](#het-scherm-loondossiers)
11. [Stukken downloaden](#stukken-downloaden)
12. [Een dossier verwerken](#een-dossier-verwerken)
13. [Bankpas vernietigen](#bankpas-vernietigen)
14. [Bewaartermijnen en verwijderen](#bewaartermijnen-en-verwijderen)
15. [Inzet en rooster](#inzet-en-rooster)
16. [Wat je nooit doet](#wat-je-nooit-doet)
17. [Veelgestelde vragen](#veelgestelde-vragen)
18. [Woordenlijst](#woordenlijst)

---

## Aan de slag

**Waar:** het beheer, `https://sollicitatie.paal13.com/admin/`  
**Wie:** de penningmeester (Authentik-groep `app-sol-penningmeester`).

> **In het kort:** jij bewaakt de loonkosten en zet de gegevens van aangenomen strandwachten in de
> loonadministratie. In de Loonplanning zie je wie er wordt ingezet, wat dat kost en of het binnen
> het budget past. In Loondossiers download je de stukken, verwerk je ze en markeer je het dossier
> als verwerkt. Na de wettelijke bewaarplicht vernietig je de loondocumenten zelf; het systeem zet
> ze voor je klaar.

**Jouw rol.** Het bestuur werft, selecteert en neemt aan. Jij beslist niet over aannemen of
afwijzen. Wel bepaalt de loonplanning wat een strandwacht verdient: hoeveel dagen per week, betaald
of onbetaald, en in welke functie. Daar werk je samen met het bestuur aan. Na het aannemen leveren
de nieuwe strandwachten in het portaal hun loonheffingsformulier, een kopie van hun
identiteitsbewijs en een kopie van hun bankpas in. Jij haalt die stukken op en verwerkt ze.

**Waar begin je?** Lees eerst [Het grote plaatje](#het-grote-plaatje) en
[Wat je per seizoen doet](#wat-je-per-seizoen-doet). Daarna gebruik je de rest als naslag.

**Rondleiding.** In het beheer staat een interactieve rondleiding "Penningmeester: loondossiers en
loonplanning". Die loopt met je langs de echte schermen en wijst aan wat waar staat. De eerste keer
staat hij op Vandaag klaar in het blok **Nieuw in het beheer?**; daarna vind je hem onder **Hulp en
handleidingen** onderin het menu (of via het vraagteken rechtsboven › **Alle rondleidingen**). Op
Loondossiers en Loonplanning kun je in het vraagteken ook **Rondleiding van dit scherm** kiezen.
Stoppen kan altijd met **Stoppen** of Esc.

## Het grote plaatje

> **In het kort:** een sollicitant doorloopt vaste stappen: aanmelden, selectiedag,
> trainingsweekend, gesprek, aangenomen. Vanaf de uitnodiging voor de selectiedag geeft die op
> wanneer die kan werken. Daarmee puzzelt het bestuur (en jij) in de loonplanning. In het gesprek op
> het trainingsweekend wordt de inzet afgesproken en vastgezet. Na het weekend wordt de planning
> definitief: dat is de afspraak waarop het loon rust. Daarna volgen het loondossier en de
> uitbetaling.

### De reis van een sollicitant

Elke stap heet in het systeem een **fase**. Zo loopt het, met wat er voor geld en loon gebeurt:

| Fase (zoals in het beheer) | Wat er gebeurt | Wat dit voor jou betekent |
|---|---|---|
| **Aangemeld** | Iemand meldt zich aan via de website. | Nog niets. |
| **Uitgenodigd selectiedag**, **Aangemeld selectiedag** | Het bestuur nodigt uit. De sollicitant vult het kennismakingsformulier in en kan vanaf nu de beschikbaarheid opgeven. | Wie beschikbaarheid opgeeft, verschijnt in de Loonplanning. |
| **Selectiedag geweest** | Een dag met testen en beoordelingen. Het bestuur besluit wie door mag. | Wie afvalt, verdwijnt uit de Loonplanning. |
| **Uitgenodigd trainingsweekend** | De uitnodiging vraagt de beschikbaarheid in te vullen en definitief te maken, uiterlijk 14 dagen vóór het weekend. | Nu vult de Loonplanning zich echt. Tijd om een eerste planning te maken. |
| **Trainingsweekend geweest** | De sollicitant was op het weekend. Daar is een gesprek met het bestuur. | In het gesprek wordt de inzet afgesproken; die staat daarna vast. |
| **Aangenomen** | Het bestuur neemt aan in het gesprek. De sollicitant krijgt het takenpakket in het portaal. | Er verschijnt een loondossier. Na het weekend wordt de loonplanning definitief. |
| **Afgewezen**, **Afgehaakt** | Niet door, of zelf gestopt. | Verdwijnt uit de Loonplanning; kost niets. |

### Van beschikbaarheid naar loon

Het geld volgt drie lagen die elkaar nooit overschrijven:

1. **De opgave**: wat de sollicitant zelf invulde (welke weken, hoeveel dagen). Die blijft altijd
   bewaard.
2. **De scenario's**: de planning in de Loonplanning. Hier wordt gepuzzeld: wie zet je welke week
   hoeveel dagen in, betaald of onbetaald, in welke functie. Er kunnen meerdere varianten naast
   elkaar bestaan; één is de **actieve** planning.
3. **De afspraak**: wat er echt is afgesproken. Die ontstaat pas als de loonplanning na het
   trainingsweekend **definitief** wordt gemaakt. Vanaf dan ziet de strandwacht de afspraak in het
   portaal, en is die de basis voor de Excel voor de loonadministratie en voor het rooster.

Het loon zelf rekent het systeem uit met de minimumlonen van dat jaar, de leeftijd, de functie en de
maand. Hoe precies staat in [Hoe het dagloon wordt berekend](#hoe-het-dagloon-wordt-berekend).

### Wanneer jij iets ziet of doet

- **Vanaf de uitnodigingen:** in de Loonplanning zie je wie beschikbaar is en wat een planning zou
  kosten.
- **Vóór het trainingsweekend:** de actieve planning moet kloppen, want die ziet het bestuur in het
  gesprek.
- **Na het trainingsweekend:** de loonplanning wordt definitief. Op Vandaag zie je
  "... aangenomen zonder vastgestelde afspraak" zolang dat nog niet is gebeurd.
- **Na het aannemen:** je krijgt per strandwacht de mail "Loondossier compleet" zodra alle stukken
  binnen zijn. Op Vandaag staat dan "... loondossiers te verwerken".
- **Tijdens het seizoen:** je houdt de kosten tegen het budget in de gaten en verwerkt
  wijzigingen.
- **Elk jaar in januari:** je loopt de lijst met loondossiers langs die over de bewaarplicht zijn.

## Wat je per seizoen doet

> **In het kort:** een afvinklijst per moment in het jaar. De datums van de selectiedag, het
> trainingsweekend en de werkperiode staan bij het seizoen; vraag ze na bij het bestuur als je ze
> niet weet.

**Bij de begroting (vóór het seizoen)**

- [ ] Log één keer in (zie [Inloggen en je menu](#inloggen-en-je-menu)) en controleer dat je
  **Loonplanning** en **Loondossiers** in het menu ziet.
- [ ] Vraag het bestuur jouw e-mailadres in te vullen als penningmeester-adres (Instellingen ›
  Afzender & adressen). Anders gaan jouw meldingen naar het bestuursadres.
- [ ] Geef het bestuur het **budget voor loonkosten** van dit kalenderjaar door. Het bestuur vult
  dat in bij Instellingen › Lonen & budget, bij het juiste jaar. Een nieuw jaar begint altijd zonder
  budget.
- [ ] Controleer met het bestuur of de **minimumlonen** van dit jaar zijn ingevuld (per leeftijd,
  voor juni en voor juli tot en met september), plus de vakantietoeslag en de werkuren per dag. Zie
  [Budget en loontarieven per jaar](#budget-en-loontarieven-per-jaar). Staat er in de Loonplanning
  "Tarieven … nog niet ingevuld, we rekenen met …", dan is dat nog niet gebeurd.

**Na de selectiedag, vóór het trainingsweekend**

- [ ] Open de Loonplanning. Wie beschikbaarheid heeft opgegeven, staat erin.
- [ ] Maak samen met het bestuur een eerste planning in het actieve scenario (de ster), of laat
  **Opgave overnemen (wie nog leeg is)** eerst alles vullen met wat mensen opgaven. Kijk of de
  loonkosten binnen het budget blijven.
- [ ] Wil je een alternatief doorrekenen? Maak een kopie van het scenario (zie
  [Scenario's gebruiken](#scenarios-gebruiken)). De actieve planning blijft dan ongemoeid.
- [ ] Controleer vlak vóór het weekend dat de **actieve** planning de juiste is: dat is wat het
  bestuur in de gesprekken ziet en aanpast.

**Direct na het trainingsweekend**

- [ ] Het bestuur heeft in de gesprekken aangenomen en de inzet vastgezet. Kijk in de Loonplanning
  of alle aangenomen strandwachten een planning hebben (filter **Wie** › **Aangenomen**).
- [ ] Laat het bestuur de loonplanning **definitief maken**, of doe het zelf in overleg (zie
  [Definitief maken: de afspraak](#definitief-maken-de-afspraak)). Noemt de melding namen onder
  "Aangenomen maar nergens ingepland", los dat dan op.
- [ ] Download onder **Exporteren** › **Loonadministratie (vastgesteld, Excel)** het overzicht
  voor de loonadministratie.

**Zodra de loondossiers binnenkomen**

- [ ] Komt er een mail "Loondossier compleet", klik dan op **Openen in het beheer**. Je komt
  direct bij het juiste dossier. Download de stukken en zet ze in de loonadministratie.
- [ ] Markeer het dossier daarna als verwerkt en verwijder de gedownloade bestanden van je
  computer. Staat automatisch opschonen uit (dat zegt het kader bovenaan Loondossiers), vernietig
  dan ook de bankpasfoto zelf (zie [Bankpas vernietigen](#bankpas-vernietigen)).
- [ ] Kijk elke week in Loondossiers tot alles verwerkt is. Staat een dossier lang op oranje, laat
  het bestuur de strandwacht dan herinneren.

**Tijdens het seizoen (bijvoorbeeld elke maand)**

- [ ] Open de Loonplanning en kijk naar de loonkosten en wat er van het budget over is.
- [ ] Verandert er iets aan iemands inzet, dan past het bestuur (of jij) de planning aan en werk je
  de afspraak van die persoon bij (zie [Een persoon: functie, afspraak en slot](#een-persoon-functie-afspraak-en-slot)).
  Een oranje label **wijkt af** achter een naam betekent dat planning en afspraak niet meer gelijk
  zijn.

**Elk jaar in januari**

- [ ] Kijk onderaan Loondossiers of de kaart **Te beoordelen voor vernietiging** er staat.
  Controleer die namen in de loonadministratie en vernietig wat over de bewaarplicht is (zie
  [Loondossiers vernietigen na de bewaarplicht](#loondossiers-vernietigen-na-de-bewaarplicht)).
- [ ] Zorg dat de loontarieven en het budget voor het nieuwe jaar klaarstaan (zie hierboven).

## Inloggen en je menu

> **In het kort:** log in via Authentik op het beheer. Je ziet vier menu-items: Vandaag,
> Sollicitanten, Loonplanning en Loondossiers.

**Waar:** `https://sollicitatie.paal13.com/admin/`  
**Wie:** je Authentik-account zit in de groep `app-sol-penningmeester`.

Kies **Inloggen via Authentik**. In het menu zie je:

- **Vandaag**: het startscherm. Voor jou staat daar bovenaan het werk dat op je wacht, bijvoorbeeld
  "2 loondossiers te verwerken" met de knop **Naar de loondossiers**, of "aangenomen zonder
  vastgestelde afspraak" met **Naar de loonplanning**. Ligt er niets, dan zie je wat het bestuur op
  dat moment doet.
- **Sollicitanten** (in de groep Sollicitanten): alleen lezen. Zo kun je een dossier openen en zien wie
  iemand is, wat die opgaf en wat er is afgesproken.
- **Loonplanning** (in de groep Inzet & loon): wie er wordt ingezet en wat dat kost. Zie
  [Loonplanning](#loonplanning).
- **Loondossiers** (ook in Inzet & loon): jouw werkscherm voor de loonadministratie.
- Onderin: **Hulp en handleidingen**, met deze handleiding en de rondleidingen.

Het scherm **Beschikbaarheid**, het **Trainingsweekend** en de **Instellingen** zie je bewust niet;
dat is werk van het bestuur. Wat je daarvan nodig hebt, zie je in de Loonplanning en in het dossier.

Zie je meer of minder, of krijg je de melding "Je Authentik-account heeft geen admin-toegang", dan
staat je account in Authentik in de verkeerde groep. Meld dat bij de beheerder.

## Beschikbaarheid: wat sollicitanten opgeven

**Waar:** jij ziet de opgave in de Loonplanning (klein in elke cel, en per dag als je een week
aanklikt) en in het dossier, tabblad **Beschikbaarheid & inzet**.  
**Wie:** de sollicitant vult in; het bestuur beoordeelt en corrigeert.

> **In het kort:** de sollicitant geeft in het portaal op welke weken die kan werken en hoeveel
> dagen, met vakanties en losse dagen. Wie minder dagen wil werken, doet een verzoek; het bestuur
> beslist. Het bestuur kan ook zelf corrigeren. Het resultaat per week (hooguit zoveel dagen) is
> wat je in de Loonplanning als "kan 4" terugziet. Het is een bovengrens, geen afspraak.

### Wat de sollicitant invult

Vanaf de uitnodiging voor de selectiedag staat in het portaal de taak om de beschikbaarheid door te
geven. De sollicitant geeft op:

- **Periodes**: van week … tot en met week …, met **hele weken** (tot vijf dagen per week) of
  **alleen weekenden** (vrijdag, zaterdag en zondag, of een deel daarvan).
- **Vakanties** en **losse dagen** waarop die niet kan. Die dagen vallen er echt af.
- **Een vaste dag die die liever vrij heeft.** Dat is een voorkeur; de planner beslist. Zo'n dag telt
  gewoon mee als beschikbaar.
- **Een verzoek om minder dagen**: bijvoorbeeld drie dagen per week in plaats van vijf, eventueel met
  de dagen waarop die wel kan. Het bestuur keurt dat goed of af. Pas na goedkeuring telt het mee.

**Voorlopig of definitief.** Zolang de sollicitant niet op **Definitief maken** heeft geklikt, staat
de opgave op voorlopig. De deadline is 14 dagen vóór het trainingsweekend; het systeem stuurt vanzelf
herinneringen. Ook daarna kan de sollicitant de opgave nog aanpassen. In het dossier zie je bovenaan
het label **Opgave voorlopig** of **Opgave definitief**.

### Wat het bestuur ermee doet

- **Verzoeken om minder dagen** beoordelen op het scherm Beschikbaarheid. De sollicitant krijgt
  bericht van het besluit.
- **Een week corrigeren**, bijvoorbeeld als iemand belt dat een week niet gaat. Zo'n correctie gaat
  boven wat de sollicitant opgaf, altijd met een reden.
- **Een afwijkende norm** instellen, bijvoorbeeld drie dagen per week voor iemand, na een besluit van
  het bestuur.
- Het raster met iedereen per week bekijken en exporteren (per week of per dag).

Jij hebt standaard geen toegang tot het scherm Beschikbaarheid en de exports daarvan, en je kunt de
opgave niet corrigeren. Klopt er iets niet, geef het dan door aan het bestuur.

### Hoe de opgave doorwerkt in de loonplanning

Het systeem maakt van al die gegevens per week één getal: het **aantal dagen dat iemand hooguit
kan**. Dat staat in de Loonplanning klein in elke cel ("kan 4"), of "niet opg." als de week niet is
opgegeven.

- Plan je minder dagen dan opgegeven, dan is dat gewoon goed.
- Plan je meer dagen dan opgegeven, of in een week die niet is opgegeven, dan kan dat, maar wordt
  de cel rood met een stippelrand. Dat is een signaal om het met de sollicitant te bespreken, geen
  blokkade.
- Wie nog niets heeft opgegeven en nergens is ingepland, staat nog niet in de Loonplanning.

In het dossier, tabblad **Beschikbaarheid & inzet**, zie je per week drie regels onder elkaar:
**Opgave**, **Afspraak** (als die er is) en **Planning** (het actieve scenario). Onder **Andere
scenario's** staan de varianten. Daaronder staat wat de sollicitant bij de aanmelding schreef over
beschikbaarheid en uitzonderingen.

## Loonplanning

**Waar:** menu › Inzet & loon › **Loonplanning**  
**Wie:** penningmeester, bestuur en beheerder. Jij mag standaard bekijken én bewerken.

> **In het kort:** per persoon per week zet je hoeveel dagen iemand werkt, betaald of onbetaald, en
> in welke functie. Het systeem rekent meteen uit wat dat kost en hoeveel er van het budget over is.
> Je werkt in scenario's; de ster is de actieve planning. Na het trainingsweekend wordt de planning
> definitief: dan is het de afspraak met de strandwacht en de basis voor de loonadministratie. Elke
> wijziging wordt direct opgeslagen, en het bestuur ziet hem ook: stem af voordat je in de actieve
> planning iets verandert.

De planning heeft ruimte nodig. Op een telefoon zie je de melding "De planning heeft ruimte nodig:
open hem op een groter scherm om te plannen."

### Het scherm van boven naar beneden

**1. Scenario en stand**

- **Scenario**: de knoppen met alle scenario's. De ster met "(actief)" is de actieve planning.
  Klik op een scenario om het te bekijken. Met **Kopie maken** maak je een variant (zie
  [Scenario's gebruiken](#scenarios-gebruiken)).
- **Loonkosten**: de bruto loonkosten van alle betaalde dagen in dit scenario.
- **Werkdagen**: het totaal aantal ingeplande dagen, met "waarvan … onbetaald".
- **Budget**: een balk met het budget van dit jaar en "Nog € … over" (groen, of oranje boven 85%)
  of "Over budget: € …" (rood). Is er geen budget, dan staat er "Geen budget ingesteld voor …".
- Staat er een waarschuwing "Tarieven … nog niet ingevuld, we rekenen met …", dan rekent het
  systeem met de minimumlonen van een eerder jaar. Zie
  [Budget en loontarieven per jaar](#budget-en-loontarieven-per-jaar).
- **Concept** of **Definitief sinds …**: of de afspraken al zijn vastgesteld. Bij Concept staat
  erbij hoeveel mensen er zijn aangenomen en hoeveel er al vaststaan na het gesprek.
- De knoppen: **Maak actief** en **Verwijderen** (alleen bij een scenario dat niet actief is),
  **Opgave overnemen (wie nog leeg is)**, **Definitief maken** (later: **Opnieuw definitief maken**)
  en **Exporteren**.

**2. Ingepland per week**

Een staafgrafiek met per week hoeveel mensen je hebt ingepland, tegen de norm die het bestuur voor de
posten heeft ingesteld. Rood is te weinig. Dit gaat over bezetting, niet over geld, maar het helpt om
te zien waar je niet kunt bezuinigen.

**3. Filters**

Zoek op naam, of kies **Functie**, **Wie** (Iedereen, Nog in de procedure, Aangenomen) en **Toon**
(Alles, Met een afwijking, Nog niet ingepland, Vast na het gesprek, Nog niet vast). "Met een
afwijking" toont wie ergens meer is ingepland dan opgegeven, in een week die niet is opgegeven, meer
dan vijf dagen in een week, of twee weken achter elkaar samen meer dan tien dagen.

**4. De planning**

Een rij per persoon en een kolom per week van de werkperiode (W27, W28, …, met de datum). In de
naamkolom staan de functie, de leeftijd en of iemand is aangenomen. Een slotje betekent **vast na het
gesprek**; een groen label **afspraak** of een oranje label **wijkt af** zegt hoe de planning zich
verhoudt tot de vastgestelde afspraak. De kolom **Totaal** geeft per persoon de dagen en de kosten.

In elke cel staat groot wat je plant en klein wat iemand opgaf:

| In de cel | Betekenis |
|---|---|
| een getal (blauw) | zoveel dagen gepland, betaald |
| een getal met "onb." (oranje) | zoveel dagen gepland, onbetaald |
| rood met een stippelrand | meer gepland dan opgegeven, of in een week die niet is opgegeven |
| een streepje met "kan 4" | opgegeven, maar niet gepland |
| "niet opg." | die week niet opgegeven |

Onderaan staan per week het aantal ingeplande mensen (en hoeveel daarvan in het weekend kunnen) en
de **Kosten** van die week in duizenden euro's (bijvoorbeeld "€ 1,2k").

**5. Loontabel**

Helemaal onderaan, ingeklapt: **Loontabel …: dagloon per leeftijd en functie**. Daar zie je per
leeftijd (15 tot en met 22 jaar) en per functie welk dagloon het systeem gebruikt, voor juni en voor
juli tot en met september.

### Een week plannen

Klik op een cel. Rechts opent een paneel.

1. Onder **Wat kan … deze week?** zie je per dag of iemand **kan**, **liever niet** of **kan niet**,
   met de redenen (vakantie, bijbaan en dergelijke) en "Opgegeven: hooguit … dagen deze week."
2. Vink **Deze week inplannen** aan. Het systeem zet dan vijf dagen, of minder als iemand minder
   opgaf.
3. Zet het aantal dagen met **−** en **+**.
4. Zet **Betaald** uit voor een onbetaalde week. Onbetaalde dagen zijn échte werkdagen die je met de
   sollicitant afspreekt; alleen het loon valt weg. Ze tellen mee voor de bezetting en gaan gewoon
   naar het rooster.
5. Onderaan staan eventuele signalen, bijvoorbeeld "Meer dagen dan opgegeven" of "Alleen te vullen
   met dagen die iemand liever vrij heeft".
6. **Zelfde voor de volgende opgegeven weken** plant elke volgende opgegeven week met hetzelfde
   aantal dagen, nooit meer dan iemand die week opgaf.

Elke wijziging slaat meteen op. De kosten bovenaan en onderaan lopen direct mee.

### Een persoon: functie, afspraak en slot

Klik op een naam. In het paneel staan:

- **Functie**: de keuzelijst. Het voorstel is Aspirant Lifeguard voor wie nieuw is, en anders de
  Lifeguard-rang uit Authentik. Postcommandant kies je altijd zelf. De functie bepaalt mee het
  dagloon en geldt per scenario.
- de totalen van deze persoon in dit scenario, en **Dossier openen**;
- **Opgave overnemen** met **Neem de opgave over**: vervangt de planning van deze persoon in dit
  scenario door wat die zelf opgaf. Daarna kun je per week bijsturen;
- **Afspraak** (pas nadat de loonplanning definitief is gemaakt): "Vastgesteld; komt overeen met dit
  scenario", "Vastgesteld, maar dit scenario wijkt ervan af" of "Nog geen afspraak vastgesteld". Met
  **Afspraak bijwerken uit dit scenario** (of **Afspraak vastleggen uit dit scenario** voor wie er
  nog geen had) wordt de planning van nu de afspraak van deze persoon. De strandwacht ziet de nieuwe
  afspraak in het portaal; er gaat geen bericht uit, dus laat het bestuur het vertellen;
- **Vast in alle scenario's**: zie [Vastzetten na het gesprek](#vastzetten-na-het-gesprek).

### Scenario's gebruiken

Een scenario is een complete planning. Je kunt er zoveel naast elkaar hebben als je wilt, om
bijvoorbeeld te zien "wat als we in juni twee mensen minder inzetten?".

- **Kopie maken**: typ bij **Naam van de kopie** een naam (bijvoorbeeld "Krap budget") en klik op
  **Maak kopie**. Je werkt dan in de kopie; het origineel blijft zoals het was.
- **Maak actief**: dit scenario wordt de actieve planning (de ster). Dat is de planning die het
  bestuur in de gesprekken op het trainingsweekend ziet en aanpast. Maak een scenario alleen actief
  als het bestuur dat heeft besloten.
- **Verwijderen** (met bevestiging): de ingeplande dagen van dat scenario verdwijnen; de
  beschikbaarheid van de sollicitanten blijft staan. Het laatste scenario kan niet weg.
- **Opgave overnemen (wie nog leeg is)**: vult voor iedereen die in dit scenario nog niets gepland
  heeft de eigen opgave in. Wie al gepland is of vaststaat, blijft zoals die is.

### Vastzetten na het gesprek

In het gesprek op het trainingsweekend spreekt het bestuur de inzet af met de sollicitant. Leggen ze
**Aannemen** vast, dan zet het systeem de weken en de functie van die persoon uit het
actieve scenario over naar **alle** scenario's, en zet ze daar op slot. Zo verandert wat met de
sollicitant besproken is niet meer doordat iemand met een ander scenario speelt. Met de anderen kun
je gewoon verder puzzelen. Bij **Afwijzen** wordt niets vastgezet.

In de Loonplanning zie je dat aan het slotje en "vast na het gesprek". Klik je een week aan, dan staat
er "Vast na het gesprek op het trainingsweekend: dit is met de sollicitant besproken." Aanpassen kan dan niet.

Moet het toch anders, in een bijzondere situatie:

1. Klik op de naam. Vul bij **Waarom pas je het aan? (komt in het logboek)** een reden in en klik
   op **Ontgrendelen**.
2. Pas de planning aan.
3. Klik op **Vastzetten vanuit dit scenario**. Dat kopieert de planning opnieuw naar alle scenario's.

Doe dit alleen in overleg met het bestuur: het gaat om wat met de sollicitant is afgesproken.
Vastzetten is nog geen afspraak; die ontstaat pas bij het definitief maken.

### Definitief maken: de afspraak

Na het trainingsweekend, als iedereen is aangenomen of afgewezen, wordt de loonplanning definitief
gemaakt. Meestal doet het bestuur dat; met je bewerkrecht kun jij het ook, in overleg.

1. Kies het scenario dat moet gelden (normaal het actieve).
2. Klik op **Definitief maken** en bevestig.

Wat er dan gebeurt:

- Het getoonde scenario wordt de actieve planning.
- Voor iedereen die op **Aangenomen** staat, wordt de planning van dat moment de **afspraak**:
  functie, en per week het aantal dagen en betaald of onbetaald. Een eerdere afspraak van dezelfde
  persoon wordt vervangen.
- Wie is aangenomen maar in het scenario nergens staat ingepland, krijgt géén afspraak. Die namen
  staan in de melding: "Aangenomen maar nergens ingepland (geen afspraak gemaakt): …".
- De strandwacht ziet in het portaal "Wat we hebben afgesproken": de functie en per week het aantal
  dagen, met "onbetaald" waar dat geldt. Loonbedragen ziet de strandwacht niet.
- Bovenaan staat voortaan **Definitief sinds …**, en onder **Exporteren** verschijnt
  **Loonadministratie (vastgesteld, Excel)**.

Verandert er daarna iets (iemand wordt later aangenomen, of een week gaat niet door), pas dan de
planning aan en werk de afspraak van die ene persoon bij (zie
[Een persoon: functie, afspraak en slot](#een-persoon-functie-afspraak-en-slot)). **Opnieuw
definitief maken** kan ook, maar dat vervangt de afspraak van iedereen die is aangenomen.

### Hoe het dagloon wordt berekend

Per persoon en per week:

**dagloon = minimumuurloon × werkuren per dag × factor van de functie × (1 + vakantietoeslag)**

- **Minimumuurloon**: uit de tabel van dat kalenderjaar, per leeftijd en per periode. Er zijn twee
  periodes, omdat het wettelijk minimumloon halverwege het jaar kan veranderen: **juni** en **juli
  tot en met september**. Een week die in juni (of eerder) begint, rekent met het junitarief; vanaf
  juli met het tarief van juli tot en met september.
- **Leeftijd**: op de maandag van die week. Wie halverwege het seizoen jarig is, krijgt vanaf die
  week het hogere tarief. Staat een leeftijd niet in de tabel, dan rekent het systeem met de
  dichtstbijzijnde leeftijd; boven de hoogste leeftijd geldt het tarief van de hoogste.
- **Werkuren per dag** en **vakantietoeslag**: per jaar ingesteld, standaard 8 uur en 8%.
- **Factor van de functie**: standaard Aspirant Lifeguard 1,00, Junior Lifeguard 1,10, Lifeguard
  1,20, Senior Lifeguard 1,30. Het bestuur kan dat aanpassen bij Instellingen › Functies; de factor
  geldt voor elk jaar.
- **Postcommandant**: geen uurloon maar een vast weekbedrag (apart voor juni en voor juli tot en met
  september). Het dagloon is dat weekbedrag gedeeld door vijf. Leeftijd en vakantietoeslag tellen
  daar niet mee.
- **Onbetaalde dagen** kosten niets.

Wat de Loonplanning laat zien is **bruto loon inclusief vakantietoeslag**. Werkgeverslasten zitten
er niet in. Gebruik het dus als raming, niet als loonstrook.

### Budget en loontarieven per jaar

**Waar:** Instellingen › **Lonen & budget** (bestuur)

Alles wat met geld te maken heeft, hoort bij een **kalenderjaar**: de minimumlonen, de
vakantietoeslag, de werkuren per dag, het vaste weekbedrag van de postcommandant en het budget. Een
seizoen rekent met het jaar waarin de werkperiode begint. Zo verandert een nieuw tarief nooit de
cijfers van een vorig seizoen.

Op dat tabblad kiest het bestuur bovenaan het **Jaar**. Daaronder:

- **Budget …**: het totaalbudget voor loonkosten van dat jaar. Leeg laten mag; de Loonplanning toont
  dan alleen de kosten. Het budget hoort bij één jaar: een volgend jaar begint zonder budget.
- **Vakantietoeslag & werkuren …** en het vaste weekbedrag van de postcommandant.
- **Minimumlonen per leeftijd …**: bedragen per uur, zonder vakantietoeslag, voor juni en voor
  juli tot en met september. Ernaast staat als voorbeeld het dagloon van de functie Lifeguard.

**Een nieuw jaar.** Met **Nieuw jaar … op basis van …** kopieert het bestuur de tarieven,
vakantietoeslag, werkuren en postcommandant van het laatste jaar; het budget niet. Daarna vult het
bestuur de nieuwe minimumlonen in. Kies je een jaar zonder tarieven, dan staat er "Tarieven … nog niet
ingevuld, we rekenen met …" met de knop **Tarieven … maken op basis van …**.

**Als een jaar ontbreekt.** Zijn de tarieven van het seizoensjaar nog niet ingevuld, dan rekent de
Loonplanning met het dichtstbijzijnde eerdere jaar en zegt dat hardop: "Tarieven 2027 nog niet
ingevuld, we rekenen met 2026." Die zin staat ook in de Excel. Het budget valt nooit terug op een
vorig jaar; dan staat er "Geen budget ingesteld voor 2027".

Jij kunt de Instellingen standaard niet openen. Klopt er iets niet, bijvoorbeeld na een verhoging
van het wettelijk minimumloon per 1 januari of 1 juli, geef het dan door aan het bestuur. Een wijziging
geldt voor alle berekeningen van dat jaar, ook voor vastgestelde afspraken en de export.

### Exporteren naar Excel

Onder **Exporteren** staan vier downloads:

| Download | Wat erin staat | Wanneer gebruiken |
|---|---|---|
| **Loonadministratie (vastgesteld, Excel)** | De vastgestelde afspraken. Alleen na definitief maken. | Voor de loonadministratie. |
| **Dit scenario (Excel, CONCEPT)** | Het scenario dat je bekijkt, met op elk tabblad "CONCEPT — scenario, nog niet vastgesteld. Niet gebruiken voor de loonadministratie." | Voor de begroting of een tussenstand. |
| **Voor het rooster (Excel)** en **(JSON)** | Per persoon per week de dagen, betaald of niet en per dag of iemand kan. Zie [Inzet en rooster](#inzet-en-rooster). | Voor wie het rooster maakt. |

De Excel voor de loonadministratie heeft vier tabbladen:

1. **Per persoon**: naam, geboortedatum, leeftijd bij de start, functie, status, aantal weken, dagen
   betaald, dagen onbetaald, gemiddeld dagloon en bruto totaal, met een totaalregel.
2. **Weekmatrix**: per persoon per week het aantal betaalde dagen. Een onbetaalde week staat er als
   tekst ("3 onb."), zodat die nooit per ongeluk als loon wordt opgeteld.
3. **Opgave vs afspraak**: per week wat iemand opgaf en wat is afgesproken, met het verschil en een
   opmerking zoals "meer dan opgegeven" of "niet ingezet".
4. **Tarieven en budget**: de tarieven van het jaar waarmee is gerekend, de factoren van de functies,
   het budget, de bruto loonkosten, het restant en de rekenregel.

BSN, rekeningnummer en adres staan er bewust niet in; die heb je in het loondossier. Er staan wel
namen en geboortedatums in: bewaar het bestand niet langer dan nodig en stuur het niet rond. Elke
download komt in het logboek.

### Wat jij mag wijzigen

Met de standaardrechten van de penningmeester:

| Mag wel | Mag niet (doet het bestuur) |
|---|---|
| Planning per week aanpassen, betaald of onbetaald | De beschikbaarheid van een sollicitant corrigeren |
| Functie per persoon kiezen | Verzoeken om minder dagen beoordelen |
| Scenario's maken, actief maken en verwijderen | Minimumlonen, vakantietoeslag, werkuren en budget instellen |
| Definitief maken en afspraken bijwerken | Functies en factoren wijzigen |
| Ontgrendelen en opnieuw vastzetten | Aannemen of afwijzen, het gesprek op het trainingsweekend |
| Alle exports downloaden | Sollicitanten wijzigen (jij kunt ze alleen lezen) |

Dat je iets technisch mag, wil niet zeggen dat je het alleen moet doen. Wijzigingen in de actieve
planning, het definitief maken en het ontgrendelen gaan over afspraken met mensen: stem die af met
het bestuur. Een beheerder kan de rechten per rol aanpassen.

## Trainingsweekend en het gesprek

**Waar:** menu › Selectie › **Trainingsweekend** (alleen bestuur, beheerder en begeleiders)  
**Wie:** het bestuur voert de gesprekken. Jij hebt standaard geen toegang tot dit scherm; het
resultaat zie je in de Loonplanning, in het dossier en in Loondossiers.

> **In het kort:** op het trainingsweekend heeft elke sollicitant een gesprek met het bestuur. Daar
> wordt de inzet afgesproken (weken, dagen, betaald, functie) in het actieve scenario, en daarna
> meteen vastgezet. Aannemen en afwijzen gaan in het gesprek direct door. Na het weekend wordt de
> loonplanning definitief.

### Hoe het weekend verloopt

Het scherm Trainingsweekend heeft zes stappen:

1. **Voorbereiden**: wie komt er ("Kom je?" vult de sollicitant zelf in), wie mist nog
   beschikbaarheid, en de tablet voor het inchecken.
2. **Groepen**: de groepsindeling.
3. **Inchecken**: sollicitanten checken zelf in op een tablet. Wie is ingecheckt, gaat naar fase
   **Trainingsweekend geweest**.
4. **Beoordelen**: begeleiders en bestuur noteren op hun telefoon wat hen opvalt.
5. **Evaluatie**: het bestuur geeft per persoon een eerste indruk (sowieso aannemen, twijfel, sowieso
   afwijzen). Dat is nog geen besluit en er gaat niets de deur uit.
6. **Gesprekken**: het bestuur spreekt iedereen en beslist.

### Het gesprek en de inzet

In het gesprek ziet het bestuur alles over één persoon bij elkaar, waaronder **Inzet afspreken**:
per week wat de sollicitant opgaf, en wat ze nu afspreken (plannen ja of nee, aantal dagen,
betaald), plus de functie en het totaal aan dagen en bruto kosten. Dit is dezelfde planning als in
de Loonplanning: wijzigingen gaan meteen in het **actieve scenario**, en wat iemand in de
Loonplanning verandert, ziet het bestuur daar binnen 15 seconden. Daarom moet de actieve planning
vóór het weekend kloppen.

Daarna legt het bestuur het besluit vast:

- **Aannemen**: gaat meteen door. De fase wordt Aangenomen, de sollicitant krijgt een mail met het
  bericht van het bestuur, en het takenpakket staat direct in het portaal. De inzet wordt vastgezet in
  alle scenario's.
- **Twijfel**: er gebeurt nog niets met de fase, en de inzet staat nog niet vast (twijfel is nog geen
  afspraak). Het bestuur belt na het weekend en legt dan alsnog het besluit vast.
- **Afwijzen**: gaat meteen door. De sollicitant verdwijnt uit de Loonplanning en kost niets.

**Niet gekomen.** Was iemand er niet (afgemeld, of niet verschenen toen het inchecken werd
afgesloten), dan is er geen gesprek, maar legt het bestuur wel een besluit vast. Meestal is dat
afwijzen, met een tekst die past bij iemand die er niet was. Tot dat besluit er is, staat die persoon
nog gewoon in de Loonplanning. Kijk dus niet vreemd op van een planning voor iemand die niet kwam;
die verdwijnt na het afwijzen vanzelf.

### Wat jij ervan ziet

- **In de Loonplanning**: het slotje "vast na het gesprek" bij wie is aangenomen,
  en "aangenomen" onder de naam. Met **Toon** › **Vast na het gesprek** of **Nog niet vast** zie je
  hoe ver het bestuur is.
- **In het dossier** (via Sollicitanten): het tabblad **Trainingsweekend** met de deelname (kwam
  wel of niet) en het besluit, en het tabblad **Beschikbaarheid & inzet** met de opgave, de planning
  en later de afspraak.
- **In Loondossiers**: een kaart per aangenomen strandwacht, direct na het aannemen.
- **Op Vandaag**: "... aangenomen zonder vastgestelde afspraak" tot de loonplanning definitief is.

## Na het aannemen: het takenpakket

> **In het kort:** wie is aangenomen, krijgt in het portaal een takenpakket. Drie onderdelen vormen
> samen het loondossier: het loonheffingsformulier, een kopie van het identiteitsbewijs en een kopie
> van de bankpas. Het dossier is compleet als die drie binnen zijn. De inschrijving als lid staat er
> ter informatie bij.

### Wat de strandwacht doet

In het portaal staan na het aannemen deze taken:

| Taak | Wat en hoe | Bijzonderheden |
|---|---|---|
| **Loonheffingsformulier** | Het officiële formulier van de Belastingdienst, deels ingevuld. De strandwacht kiest of de loonheffingskorting wordt toegepast en ondertekent digitaal met een verificatiecode. | De code gaat per sms, anders via WhatsApp, anders per e-mail. De strandwacht tekent altijd zelf, ook onder de 18. Het BSN staat alleen in de verzegelde PDF en versleuteld in de kluis. |
| **Kopie ID uploaden** | Foto of scan (JPG, PNG of PDF, maximaal 10 MB). Bij een identiteitskaart voor- én achterkant, bij een paspoort alleen de fotopagina. Een rijbewijs telt niet. | Wettelijk verplicht, volledig en niet afgeschermd. |
| **Kopie bankpas uploaden** | Foto van de voorkant (naam en rekeningnummer). | Alleen om het rekeningnummer te controleren. Wordt na verwerking vernietigd. |
| **Lid worden** | De inschrijving als lid van de vereniging, alleen voor wie nog geen Paal 13-account heeft. Gaat na het ondertekenen als PDF naar de ledenservice. | Onder de 18 vult de ouder of verzorger dit in en tekent die, via een link in de mail. Kiest die voor automatische incasso, dan geeft die de machtiging als rekeninghouder. |
| Huisregels lezen, Paal 13-account activeren | Geen loonstukken. | Tellen niet mee voor het loondossier. |

De strandwacht kan een foto vervangen of verwijderen tot jij het dossier als verwerkt hebt
gemarkeerd. Alles staat versleuteld in de kluis; alleen jij en de beheerder kunnen het openen.

### Wanneer is een loondossier compleet?

Een dossier is **compleet** als:

- het loonheffingsformulier is ondertekend (met de verificatiecode);
- de kopie van het identiteitsbewijs binnen is (bij een identiteitskaart ook de achterkant);
- de kopie van de bankpas binnen is.

De inschrijving als lid telt niet mee voor "compleet".

## Wanneer je een melding krijgt

> **In het kort:** je krijgt één mail "Loondossier compleet" als alle stukken binnen zijn. De
> stukken zitten niet in die mail. Komt er geen mail, kijk dan zelf in Loondossiers.

De mail gaat naar het penningmeester-adres uit Instellingen › Afzender & adressen (is dat leeg,
dan naar het bestuursadres). In de mail staat welke stukken er zijn, met een knop **Openen in het
beheer** die direct het juiste dossier openklapt.

De stukken zitten standaard **niet** in de mail; je haalt ze op via het beheer. Het bestuur kan
instellen dat ze wél meegaan, maar dan gaan een BSN en een kopie identiteitsbewijs onversleuteld
per e-mail. Dat raden we af.

De mail komt zodra het laatste stuk binnen is, of dat nu het ondertekende loonheffingsformulier is
of een upload van de kopie ID of de bankpas. Per loonheffingsformulier krijg je hem één keer.

Mail kwijt of niet gekregen? Klik in het dossier op **Melding opnieuw naar de penningmeester**.
Die knop staat er zodra het dossier compleet is.

## Het scherm Loondossiers

**Waar:** menu › Inzet & loon › **Loondossiers**  
**Wie:** penningmeester en beheerder. Het bestuur ziet dit scherm niet.

> **In het kort:** bovenaan de regels voor deze stukken en de stand, daaronder per strandwacht een
> kaart die je openklapt. Oranje is nog niet compleet, groen wacht op jou, grijs is verwerkt.
> Onderaan staat, als dat nodig is, wie in aanmerking komt voor vernietiging.

**Bovenaan** staat een vast kader met de regels: hier staan een BSN en kopieën van
identiteitsbewijzen, elke download wordt vastgelegd, wat er met de bankpasfoto gebeurt en hoe lang
het loonheffingsformulier en de kopie ID blijven. Komen er dossiers in aanmerking voor
vernietiging, dan staat daar ook een link naar **Te beoordelen voor vernietiging**. Dat kader staat
er altijd; het is geen waarschuwing.

Let op de zin over de bankpasfoto. Staat daar "De bankpasfoto wordt … dagen na "verwerkt"
automatisch vernietigd", dan hoef je niets te doen. Staat daar "Automatisch opschonen staat uit",
dan vernietig je de bankpasfoto zelf na het verwerken.

**Seizoen.** Zijn er meerdere seizoenen, dan kies je bovenaan het **Seizoen**. Standaard zie je het
actieve seizoen; per seizoen staat erbij hoeveel mensen er zijn aangenomen. Zo vind je de dossiers van
vorig jaar terug, ook als er al een nieuw seizoen loopt.

Daaronder de stand, bijvoorbeeld "2 dossiers wachten op verwerking", met de naam van het seizoen en
de tellers aangenomen, compleet en verwerkt.

**Per strandwacht** een kaart. Klik op de naam om hem open of dicht te klappen. Rechts van de naam
staat de status:

- **Oranje, "Ontbreekt nog: …":** er ontbreken stukken; je ziet welke.
- **Groen, "Compleet — klaar om te verwerken":** wacht op jou. Deze kaarten staan standaard open.
- **Grijs, "Verwerkt" met datum:** klaar.

In een opengeklapte kaart zie je:

- een link **Naar het dossier van …**, als je meer over de persoon wilt weten;
- het **loonheffingsformulier**: wanneer het is ondertekend, hoe (verificatiecode, naar welk
  nummer) en een vingerafdruk van het bestand, met de knop **PDF**;
- **identiteitsbewijs voorkant** (met paspoort of identiteitskaart), **achterkant** ("Niet nodig
  bij een paspoort") en **kopie bankpas** ("Vernietigd … dagen na verwerking" of "Na verwerking
  zelf vernietigen"): per stuk de bestandsnaam, de uploaddatum, de grootte en wanneer het voor het
  laatst is ingezien, met de knop **Download**;
- **lid van de vereniging**: wanneer de inschrijving naar de ledenservice is gemaild, met de knop
  **PDF**, of "Niet van toepassing — heeft al een verenigingsaccount";
- onderaan de knoppen **Verwerkt in de loonadministratie** en **Melding opnieuw naar de
  penningmeester**, en de uitklapbare **Tijdlijn van de ondertekening**. Daarin staat het
  bewijsspoor: klaargezet, geopend, verificatiecode verstuurd, code geaccepteerd, ondertekend,
  PDF verzegeld, gedownload en verwerkt, met tijdstip en IP-adres.

Staat er bovenaan een **rode melding** "De kluis is niet bereikbaar", dan kunnen strandwachten
niets uploaden en geen loonheffingsformulier ondertekenen. Waarschuw de beheerder. Een **oranje
melding** "Het documentzegel werkt niet" betekent dat nieuwe PDF's geen digitaal zegel krijgen;
bestaande documenten blijven geldig. Ook dan de beheerder waarschuwen.

Staat er nog niemand op Aangenomen, dan zie je "Nog geen loondossiers".

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
dan de beheerder.

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
bestuur in op hetzelfde tabblad (**Kopie bankpas**). Daar staat ook de bewaartermijn van de
loondocumenten; die kan langer worden gezet, nooit korter dan 5 jaar. Wat er is vernietigd, ziet de
beheerder in het logboek.

### Loondossiers vernietigen na de bewaarplicht

Het systeem weet tot welk seizoen iemand hier voorkwam, maar niet wanneer het dienstverband
eindigde. Iemand kan na een pauze terugkomen. Daarom vernietigt het systeem het
loonheffingsformulier en de kopie ID nooit zelf. Het zet wel klaar wie in aanmerking komt, onderaan
Loondossiers in de kaart **Te beoordelen voor vernietiging**. Die kaart kijkt over alle seizoenen,
welk seizoen je bovenaan ook kiest.

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
[Wat je per seizoen doet](#wat-je-per-seizoen-doet).

### Terugtrekken

Trekt een strandwacht zich terug vóórdat het dossier verwerkt is, dan kan die de gegevens zelf
verwijderen in het portaal; de stukken worden dan meteen vernietigd. Na het verwerken kan dat ook,
maar dan blijft het loondossier staan (zie [Een dossier verwerken](#een-dossier-verwerken)). Is
het dossier al verwerkt en gaat het dienstverband toch niet door, dan kan alleen iemand met zowel
het recht om sollicitanten te verwijderen als het recht om loondossiers te verwijderen het dossier
verwijderen (standaard alleen de beheerder). Het systeem vraagt dan met een apart vinkje om te
bevestigen dat het dienstverband niet is doorgegaan.

Stopt een aangenomen strandwacht, controleer dan ook de Loonplanning en de vastgestelde afspraak
met het bestuur, zodat de export voor de loonadministratie klopt.

## Inzet en rooster

> **In het kort:** het rooster (wie welke dag op welke post staat) wordt buiten dit systeem
> gemaakt. Dit systeem levert per persoon per week het afgesproken aantal dagen, betaald of niet,
> de functie, en per dag of iemand kan. Het rooster kiest zelf welke dagen.

Onder **Exporteren** in de Loonplanning staan **Voor het rooster (Excel)** en **Voor het rooster
(JSON)**. Zolang de loonplanning niet definitief is, komt daar het actieve scenario in (als concept);
daarna de vastgestelde afspraak. Plan het echte rooster alleen op de afspraak.

Per week staat erin:

- het afgesproken aantal dagen en of die week betaald is;
- de dagen waarop iemand kan, liever niet (een voorkeur; het rooster mag ze gebruiken als het niet
  anders kan) en niet (met de reden);
- wat iemand die week hooguit opgaf;
- signalen als het rooster dat niet kan vullen, bijvoorbeeld "Minder beschikbare dagen dan
  afgesproken".

Voor jou telt vooral: er worden **geen vaste weekdagen** afgesproken, alleen een aantal per week. Het
loon rekent met dat aantal, niet met welke dagen het rooster kiest. Onbetaalde weken staan er ook
in; dat zijn echte werkdagen zonder loon.

## Wat je nooit doet

> **In het kort:** stukken blijven in het beheer en in de loonadministratie. Niet mailen, niet
> kopiëren, niet delen.

- Stukken per e-mail of WhatsApp doorsturen, ook niet naar jezelf. Gebruik het beheer.
- Een BSN of rekeningnummer in een spreadsheet, notitie of chat zetten buiten de
  loonadministratie.
- Een BSN, rekeningnummer of kopie ID lokaal bewaren nadat het in de loonadministratie staat.
  Verwijder gedownloade bestanden meteen, ook uit de downloadmap en de prullenbak.
- Screenshots maken van kopieën van identiteitsbewijzen.
- Je inlog delen. Elke download staat op jouw naam.
- De concept-Excel ("CONCEPT") gebruiken voor de loonadministratie. Gebruik de vastgestelde versie.
- Een Excel met namen en geboortedatums rondsturen of langer bewaren dan nodig.
- Iemand ontgrendelen of een afspraak wijzigen zonder overleg met het bestuur: dat gaat over wat met
  de strandwacht is afgesproken.

## Veelgestelde vragen

> **In het kort:** de meeste vragen gaan over een dossier dat oranje blijft, een waarschuwing over
> tarieven of budget, of iemand die niet te wijzigen is in de Loonplanning.

**In de Loonplanning staat "Tarieven 2027 nog niet ingevuld, we rekenen met 2026."**  
De minimumlonen van het jaar van dit seizoen zijn nog niet ingevuld. De kosten zijn dan een schatting
met de tarieven van het vorige jaar. Vraag het bestuur bij Instellingen › Lonen & budget het nieuwe
jaar te maken en de nieuwe minimumlonen in te vullen.

**Er staat "Geen budget ingesteld voor …".**  
Het budget hoort bij één kalenderjaar en gaat niet mee naar het volgende jaar. Geef het budget door
aan het bestuur; die vult het in bij Instellingen › Lonen & budget, bij het juiste jaar.

**Waarom zijn de kosten in de Loonplanning anders dan in de loonadministratie?**  
De Loonplanning rekent bruto loon inclusief vakantietoeslag, zonder werkgeverslasten, met de
leeftijd op de maandag van elke week en het tarief van juni of van juli tot en met september.
Onbetaalde dagen kosten niets. Kijk in de Excel op het tabblad **Tarieven en budget** welke getallen
er zijn gebruikt.

**Ik kan iemand in de Loonplanning niet aanpassen.**  
Die persoon staat vast na het gesprek op het trainingsweekend (slotje). Overleg met het bestuur; moet
het echt anders, ontgrendel dan met een reden en zet daarna weer vast (zie
[Vastzetten na het gesprek](#vastzetten-na-het-gesprek)). Zie je helemaal geen knoppen en staat er
"alleen lezen", dan heb je geen bewerkrecht; vraag de beheerder.

**Iemand is aangenomen, maar heeft geen afspraak.**  
Dat gebeurt als iemand na het definitief maken is aangenomen, of bij het definitief maken nergens was
ingepland. Plan die persoon in, klik op de naam en kies **Afspraak vastleggen uit dit scenario**.

**Achter een naam staat "wijkt af".**  
De planning in dit scenario is anders dan de vastgestelde afspraak. Hoort de wijziging erbij, werk dan
de afspraak bij met **Afspraak bijwerken uit dit scenario**. Hoort hij er niet bij, zet de planning
dan terug.

**Een cel is rood met een stippelrand.**  
Er is meer gepland dan de sollicitant opgaf, of in een week die niet is opgegeven. Dat mag, maar
bespreek het met de sollicitant (via het bestuur).

**Ik zie Beschikbaarheid of Trainingsweekend niet in het menu.**  
Dat is zo bedoeld: die schermen zijn van het bestuur. De opgave zie je in de Loonplanning en in het
dossier, het besluit in het dossier op het tabblad Trainingsweekend.

**Een dossier blijft oranje terwijl de strandwacht zegt dat alles is geüpload.**  
Kijk achter "Ontbreekt nog" welk stuk mist. Bij een identiteitskaart moet ook de achterkant erbij;
bij een paspoort niet. Het loonheffingsformulier telt pas mee na het ondertekenen met de
verificatiecode.

**Het dossier is groen, maar ik heb geen mail gekregen.**  
De mail gaat uit zodra het laatste stuk binnen is. Kijk eerst in je ongewenste mail en of het
penningmeester-adres in Instellingen › Afzender & adressen klopt (leeg is: het bestuursadres).
Verwerk het dossier gewoon, of klik op **Melding opnieuw naar de penningmeester** als je de mail
toch wilt hebben.

**De strandwacht krijgt geen verificatiecode.**  
De code gaat per sms naar het telefoonnummer uit het dossier; lukt dat niet, dan via WhatsApp, en
anders per e-mail. Klopt het nummer niet, laat het bestuur het dan aanpassen in het dossier. Blijft
het misgaan, dan is het iets voor de beheerder.

**Ik zie een watermerk op de ID-kopie. Kan dat weg?**  
Nee. Het watermerk is bewust en beschermt tegen misbruik van de kopie. Voor de loonadministratie
is een kopie mét watermerk gewoon geldig.

**Ik zoek het loondossier van vorig jaar.**  
Kies bovenaan Loondossiers het **Seizoen** van vorig jaar. Die keuze staat er zodra er meer dan één
seizoen is. Stukken blijven in de kluis tot jij ze na de bewaarplicht vernietigt.

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
De penningmeester heeft het recht Loonplanning standaard. Een beheerder kan het bij Rollen & rechten
hebben weggehaald, of de module Loonplanning staat uit. Vraag de beheerder.

## Woordenlijst

| Woord | Betekenis |
|---|---|
| **Actief scenario** | Het scenario met de ster: de planning die geldt en die het bestuur in de gesprekken ziet. |
| **Afspraak** | De vastgestelde inzet van een aangenomen strandwacht: functie, en per week dagen en betaald of niet. Ontstaat bij definitief maken. De basis voor loon en rooster. |
| **Dagloon** | Wat één betaalde werkdag bruto kost, inclusief vakantietoeslag. |
| **Definitief maken** | De planning omzetten in afspraken voor iedereen die is aangenomen. |
| **Factor** | Vermenigvuldiger per functie op het minimumloon (bijvoorbeeld 1,20 voor de functie Lifeguard). |
| **Fase** | De stap waarin een sollicitant zit, van Aangemeld tot Aangenomen of Afgewezen. |
| **Loondossier** | Loonheffingsformulier, kopie identiteitsbewijs en kopie bankpas van één strandwacht. |
| **Onbetaald** | Echte, afgesproken werkdagen zonder loon. Tellen mee voor bezetting en rooster. |
| **Opgave** | Wat de sollicitant zelf opgaf over beschikbaarheid; per week hooguit zoveel dagen. |
| **Scenario** | Een complete planning in de Loonplanning. Er kunnen er meerdere naast elkaar bestaan. |
| **SD** | Oude afkorting voor selectiedag; op de schermen staat nu het hele woord. |
| **Tariefjaar** | Het kalenderjaar waarvan de minimumlonen en het budget komen: het jaar waarin de werkperiode begint. |
| **TW** | Oude afkorting voor trainingsweekend; op de schermen staat nu het hele woord (of kort "weekend", zoals in "Weekend geweest"). |
| **Vastzetten** | Na het gesprek op het trainingsweekend de inzet van één persoon in alle scenario's gelijk maken en op slot zetten. |
| **Verzoek om minder dagen** | Vraag van een sollicitant om in bepaalde weken minder te werken; telt pas mee na goedkeuring door het bestuur. |
| **Voorlopig / definitief** (beschikbaarheid) | Of de sollicitant de opgave al als zeker heeft bevestigd. Los van het definitief maken van de loonplanning. |

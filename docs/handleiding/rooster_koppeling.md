# Rooster-koppeling: wat het sollicitatiesysteem levert

> **In het kort:** per persoon per week hoeveel dagen iemand werkt (de afspraak uit de
> loonplanning), betaald of niet, de functie, en per dag of iemand kan. Het rooster kiest zelf
> welke van de beschikbare dagen. Bron in de code: `includes/inzet_rooster.php`
> (`inzet_rooster()`); export: Loonplanning › **Export voor het rooster** (Excel of JSON), of
> Beschikbaarheid › **Export voor het rooster** (Excel; ook voor de planner, zonder scenariokeuze).

## Wat erin staat

Per persoon: `sollicitatie_id` (of `extra_id`, zie onder), `naam`, `functie` (sleutel uit Instellingen › Functies) en per
week:

| Veld | Betekenis |
|---|---|
| `week`, `maandag` | ISO-weeknummer en de maandag van die week |
| `dagen` | afgesproken aantal werkdagen die week |
| `betaald` | `true` of `false`. Onbetaalde weken zijn échte werkdagen, afgesproken met de sollicitant; alleen het loon verschilt |
| `beschikbaar` | datums waarop iemand kan |
| `liever_niet` | datums die iemand liever vrij heeft (voorkeur); het rooster mag ze gebruiken als het niet anders kan |
| `niet` | datums waarop iemand niet kan, met reden (vakantie, bijbaan, tandarts, "bestuur: …") |
| `opgave_max` | wat de sollicitant die week maximaal opgaf (0 = week niet opgegeven) |
| `signalen` | `niet_opgegeven`, `meer_dan_opgegeven`, `te_weinig_dagen` (het rooster kan dit niet vullen), `krap` (alleen met "liever niet"-dagen) |

Bron: de **vastgestelde afspraak** zodra de loonplanning definitief is gemaakt (`bron: "afspraak"`);
daarvóór het actieve scenario (`bron: "concept"`). Plan het echte rooster alleen op de afspraak.

**Extra personen** uit de loonplanning (bijvoorbeeld de postcommandant; geen sollicitant) staan er
ook in, met `sollicitatie_id: null` en een `extra_id`. Zij hebben geen opgave en geen afspraak: hun
weken komen altijd uit het actieve scenario, elke dag staat in `beschikbaar`, `opgave_max` is `null`
en `signalen` is leeg. Een koppeling die op `sollicitatie_id` matcht, moet deze rijen apart herkennen.

De dagen komen uit `beschikbaarheid_per_dag()`, in lagen: de opgegeven periodes en het regime
(hele week of alleen weekend), vaste vrije dagen (voorkeur of hard), vakanties, losse dagen
(sollicitant, bestuur wint), goedgekeurde verzoeken om minder dagen (alleen de gekozen dagen) en
de weekcorrecties van het bestuur.

## Hoe het systeem reageert (getest 24 sep 2026)

Allemaal week 29 (13–19 juli 2026), met de echte code op een testdatabase
(`docs/ai/harnas/rooster_scenarios.php`).

| Scenario | Afgesproken | Kan op | Liever niet | Kan niet | Signaal |
|---|---|---|---|---|---|
| A. 4 opgegeven (verzoek, ma–do), 3 gepland | 3, betaald | ma di wo do | — | vr za zo (bijbaan) | geen: het rooster kiest 3 van de 4 |
| B. Zelfde opgave, 5 gepland | 5, betaald | ma di wo do | — | vr za zo (bijbaan) | meer dan opgegeven; te weinig dagen |
| C. Onbetaalde week, 3 gepland | 3, **niet betaald** | ma t/m zo | — | — | geen: gewoon inroosteren |
| D. Vakantie wo–vr, 4 gepland | 4, betaald | ma di za zo | — | wo do vr (vakantie) | geen |
| E. Woensdag liever vrij, 7 gepland | 7, betaald | ma di do vr za zo | wo | — | krap |
| F. Alleen weekend (vr–zo), 3 gepland | 3, betaald | vr za zo | — | ma–do (alleen weekend opgegeven) | geen |
| G. Alleen weekend, 4 gepland | 4, betaald | vr za zo | — | ma–do | meer dan opgegeven; te weinig dagen |
| H. Bestuur: alleen za/zo, 2 gepland | 2, betaald | za zo | — | ma–vr (bestuur: …) | geen |
| I. Week niet opgegeven, 3 gepland | 3, betaald | — | — | hele week (week niet opgegeven) | niet opgegeven; te weinig dagen |
| J. Do 16 juli tandarts, 5 gepland | 5, betaald | ma di wo vr za zo | — | do (tandarts) | geen |

Voorbeeld van één week in JSON (scenario A):

```json
{"week":29,"maandag":"2026-07-13","dagen":3,"betaald":true,
 "beschikbaar":["2026-07-13","2026-07-14","2026-07-15","2026-07-16"],
 "liever_niet":[],
 "niet":[{"datum":"2026-07-17","reden":"bijbaan"},{"datum":"2026-07-18","reden":"bijbaan"},{"datum":"2026-07-19","reden":"bijbaan"}],
 "opgave_max":4,"signalen":[]}
```

## Nog te doen voor de echte koppeling

Een publiek eindpunt (in `api/`, met een eigen sleutel en snelheidsbegrenzing, alleen lezen) dat
`inzet_rooster()` teruggeeft. Tot dan is er de export in het beheer (achter inloggen, in het
logboek).

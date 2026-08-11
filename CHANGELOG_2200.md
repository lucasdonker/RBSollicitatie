# Bundel 2.20.0 — Loonplanning planbord (fase 3)

Het planbord is nu een echt beslisinstrument: je ziet live wat je keuzes kosten
én of de posten gedekt blijven. Werkt op **weekniveau**, zoals het loon werkt.
De dagplanning komt later als apart onderdeel.

## Wat is er nieuw

### 1. Dekking rekent mee met wie je INPLANT
Dit is de kern. De dekkingsrij onder de matrix rekent niet met wie er *beschikbaar*
is, maar met wie je daadwerkelijk hebt **opgenomen** in de planning. Plan je de
schipper weg, dan wordt die week meteen 🔴. Plan je hem terug, dan is het hersteld.
Live, zonder de pagina te herladen.

### 2. Budget met voortgangsbalk
Nieuw: **Instellingen → Minimumlonen → Seizoensbudget**. Vul een totaalbudget in en
de balk onderin loopt live mee — groen, oranje boven 85%, rood als je eroverheen
gaat, met het restant of het tekort in euro's. Laat het leeg om zonder budget te
werken; dan zie je alleen de kosten.

### 3. Signalen — geen blokkades
Het systeem rekent en signaleert, jij beslist. Drie signalen:
- **Meer ingepland dan beschikbaar** — iemand staat voor 5 dagen ingepland maar is
  er maar 3 beschikbaar.
- **Meer dan 5 dagen in een week.**
- **Weinig rust over de weekgrens** — twee opeenvolgende weken samen meer dan 10
  dagen. Let op: **4+6 = 10 is legitiem** en geeft géén signaal; 6+6 = 12 wel.

Niets blokkeert. Alles is een aandachtspunt dat je bewust kunt negeren.

### 4. Scenario's
Dupliceer een planning om varianten naast elkaar te zetten ("wat als Bram niet kan?").
Eén scenario is de **actieve** planning (★). Wisselen, activeren en verwijderen kan
met één klik. Het laatste scenario kan niet worden verwijderd.

## Nieuwe/gewijzigde bestanden
- `includes/loonplan.php` — **NIEUW** planningsmodel: scenario's, kosten, budget,
  dekking-op-basis-van-planning, signalen
- `includes/bezetting.php` — `bezetting_dekking_van_pool()` afgesplitst, zodat het
  planbord met een eigen pool kan rekenen. Ook `bezetting_comp_van()` toegevoegd.
- `admin/loonplanning.php` — volledig herbouwd rond het nieuwe model
- `admin/instellingen.php` — Seizoensbudget op het loon-tabblad
- `includes/functions.php` — laadt loonplan.php
- `includes/versie.php` — 2.20.0

## Databasemigratie (automatisch bij eerste laden)
- **Nieuw:** tabel `loon_scenario` (id, seizoen_id, naam, actief, notitie)
- `loon_planning.scenario_id` toegevoegd; unieke sleutel wordt
  `(sollicitatie_id, week_nr, jaar, scenario_id)`
- Je bestaande planningsregels worden automatisch aan een scenario
  **"Basisplanning"** gekoppeld — er gaat niets verloren.

## Testchecklist
1. **Budget** → Instellingen → Minimumlonen → vul een seizoensbudget in. Loopt de
   balk mee als je dagen invult? Kleurt hij rood boven budget?
2. **Dekking live** → vink de enige schipper uit in een week. Wordt die week 🔴?
   Vink hem terug → weer 🟢/🟠?
3. **Signalen** → plan iemand 6 dagen in twee opeenvolgende weken (6+6). Verschijnt
   "weinig rust"? Doe daarna 4+6 → signaal weg?
4. **Meer dan beschikbaar** → plan iemand meer dagen dan hij beschikbaar is. Kleurt
   het invulveld oranje en verschijnt er een signaal?
5. **Scenario's** → dupliceer de planning, wijzig iets in de kopie, wissel terug.
   Blijft het origineel intact? Werkt "★ Maak actief"?
6. **Bestaande data** → staat je oude planning er nog, onder "Basisplanning"?

## Wat hierna nog komt
- **Dagplanning** — wie werkt welke dag (apart onderdeel, zoals afgesproken)

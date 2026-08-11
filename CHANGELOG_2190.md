# Bundel 2.19.0 — Dekkingmatrix (fase 2b)

Nieuw: het systeem rekent nu uit of de posten élke week te bemannen zijn met de
mensen die beschikbaar zijn, op basis van hun competenties uit Authentik.

## Nieuwe pagina: Dekking (`/admin/dekking.php`)

**Week-overzicht** — per week een stoplicht per post:
- 🟢 **Gedekt** — alle rollen vervuld én er is reserve
- 🟠 **Krap** — alle rollen vervuld, maar niemand achter de hand
- 🔴 **Tekort** — minstens één rol is onvervulbaar

**Dag-/weekdetail** — klik een week aan en je ziet per post welke rol door wie
vervuld wordt, hoeveel reserve er is, en bij een tekort:
- **"Waarom tekort"** — welke rol, hoeveel nodig, wat er gevonden is
- **📞 Bellijstje** — wie de competentie wél heeft maar die week niet beschikbaar
  is, met bel- en mailknop

## Hoe het rekent
- **Eén pool**: beide posten concurreren om dezelfde mensen. Een schipper kan niet
  tegelijk op GK staan.
- **Schaarste eerst**: rollen met de minste kandidaten worden als eerste vervuld.
  Wie zowel schipper als chauffeur kan, gaat naar de schaarse rol — zodat de andere
  met een gewone chauffeur wordt gevuld.
- **Beschikbaar = inzetbaar** (optimistisch op weekniveau). Wie-welke-dag komt in fase 3.
- **De postcommandant valt hier buiten** — dat regelt het bestuur apart.

## Standaard posten
**Hoofdpost** — chauffeur (Chauffeur), opstapper auto (Opstapper Auto+),
derde man/zwemmer (Lifeguard-rang), schipper (Schipper), opstapper boot (Opstapper Boot+).

**Tweede post (GK)** — 2× lifeguard van minstens Junior. De regel "nooit 2 aspiranten
samen" wordt bewaakt.

## Instelbaar: Instellingen → Posten & bezetting
Posten en rollen zijn volledig aanpasbaar zonder code: rol-naam, competentie-categorie,
minimum-rang en aantal personen. Posten toevoegen/verwijderen kan ook. Met een
"standaard herstellen"-knop.

## Nieuwe/gewijzigde bestanden
- `includes/bezetting.php` — **NIEUW** dekkingsmodel (sjablonen, greedy toewijzing,
  stoplichten, bellijst)
- `includes/functions.php` — laadt bezetting.php
- `admin/dekking.php` — **NIEUW** dekkingspagina
- `admin/instellingen.php` — nieuw tabblad "Posten & bezetting"
- `admin/layout_top.php` — menu-item "Dekking"
- `includes/versie.php` — 2.19.0

## Geen databasemigratie nodig
De sjablonen worden opgeslagen als instelling (`bezetting.sjablonen`). Er komen
geen nieuwe tabellen bij.

## Let op
De dekking werkt alleen als **Authentik gekoppeld** is (competenties zijn de basis
van de rol-matching). Staat dat nog niet aan, dan toont de pagina een waarschuwing
met een link naar de instellingen.

## Testchecklist
1. **Menu** → staat "Dekking" tussen Beschikbaarheid en Loonplanning?
2. **Week-overzicht** → zie je stoplichten per week met beide posten?
3. **Klik een week** → zie je per rol wie ingedeeld is + reserve-aantal?
4. **Rode week** → klopt de "waarom tekort"-uitleg? Staat het bellijstje er met
   mensen die de competentie hebben maar niet beschikbaar zijn?
5. **Eén pool** → wordt een schipper niet ook op GK ingedeeld?
6. **Instellingen → Posten & bezetting** → kun je een rol toevoegen/wijzigen en
   opslaan? Rekent de dekkingspagina daarna met de nieuwe indeling?

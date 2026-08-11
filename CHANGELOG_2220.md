# Bundel 2.22.0 — Selectiedag fase 1: groepsindeling

Eerste van vier fases voor het opnieuw opgezette selectiedag-systeem. Deze fase
legt het fundament: groepen en een eerlijke indeling.

## Wat is er nieuw

### Indelingsscherm (Beheer → Selectiedag → 1 · Groepsindeling)
- **Aantal groepen instellen** — hangt af van hoeveel sollicitanten en begeleiders
  je hebt.
- **Voorstel genereren** — het systeem verdeelt de uitgenodigde sollicitanten
  eerlijk: gelijke groepsgrootte, en gemengd op leeftijd en geslacht zodat de
  groepen onderling vergelijkbaar zijn.
- **Handmatig schuiven** — sleep kandidaten tussen groepen. De tellingen lopen mee.
- **Houd uit elkaar** — klik op een naam en vink aan wie die persoon kent
  (vrienden). Het voorstel probeert die koppels in verschillende groepen te zetten.
  Zet je ze tóch samen, dan verschijnt een waarschuwingsdriehoek.

## Hoe het indeel-algoritme werkt
Slangsgewijs verdelen na sortering op geslacht en leeftijd geeft automatisch
gelijkmatige, gemengde groepen. Daarna worden houd-uit-elkaar-conflicten opgelost
door leden te ruilen. Bij een onmogelijke situatie (te veel koppels, te weinig
groepen) faalt het netjes zonder vast te lopen — je ziet dan gewoon een
waarschuwing en kunt het met de hand oplossen.

## Nieuwe/gewijzigde bestanden
- `includes/selectiedag.php` — NIEUW datamodel: groepen, indeling, relaties, het
  verdeel-algoritme
- `admin/selectie_indeling.php` — NIEUW indelingsscherm
- `admin/selectiedag.php` — subnav toegevoegd (fase 1 ↔ fase 2)
- `admin/layout_top.php` — menu wijst nu naar de indelingspagina
- `includes/functions.php` — laadt selectiedag.php
- `includes/versie.php` — 2.22.0 + changelog

## Databasemigratie (automatisch bij eerste laden)
Drie nieuwe tabellen: `selectie_groep`, `selectie_indeling`, `selectie_relatie`.
De oude `beoordelingen`-tabel blijft volledig ongemoeid — de bestaande beoordeel-
functionaliteit (fase 2 knop) werkt gewoon door tot we die in fase 3 vervangen.

## Testchecklist
1. **Menu** → Selectiedag opent nu op "Groepsindeling"?
2. **Aantal groepen** → op 3 zetten en instellen → verschijnen er 3 lege groepen?
3. **Voorstel** → "Voorstel genereren" → worden de kandidaten eerlijk verdeeld?
4. **Slepen** → sleep iemand naar een andere groep → blijft dat na verversen staan?
5. **Houd uit elkaar** → klik een naam, vink iemand aan, genereer opnieuw → komen ze
   in verschillende groepen? Zet ze met de hand samen → verschijnt de waarschuwing?
6. **Fase 2 knop** → werkt de bestaande "Inchecken & beoordelen" nog gewoon?

## Wat hierna komt
- Fase 2: inchecken op tablet (zelfservice) + aanwezigheid
- Fase 3: beoordelen op de dag (scores + observaties, meerdere beoordelaars)
- Fase 4: nabespreking met alles per kandidaat + eindbesluit

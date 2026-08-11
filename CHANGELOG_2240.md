# Bundel 2.24.0 — Selectiedag fase 3: beoordelen

Derde van vier fases. De kern van de selectiedag: beoordelaars noteren op hun eigen
telefoon wat hen opvalt, met scores als hulpmiddel.

## Wat is er nieuw

### Beoordeel-scherm (Selectiedag → 3 · Beoordelen)
Ontworpen voor de telefoon, tijdens de dag. Elke beoordelaar (rol begeleider of
hoger) ziet de kandidaten, kan filteren op groep, en tikt een naam open.

**Observaties staan centraal** — een groot tekstveld met een soort-keuze:
👍 positief / 📝 neutraal / ⚠️ aandacht. Dit is het belangrijkste, want jullie
bespreken achteraf per persoon wie door mag naar het TW. Observaties mogen op
**iedereen**, ook buiten je eigen groep ("viel negatief op bij een andere groep").

**Sterren als bijzaak** — per criterium 1–5 sterren, tik-tik-klaar. Meerdere
beoordelaars naast elkaar: je ziet het gemiddelde en hoeveel mensen scoorden
("gem 4.5 (2)"). Je eigen score overschrijft die van een ander niet. Tik dezelfde
ster nogmaals om je score te wissen. Alles slaat direct op.

### Instelbare criteria (Instellingen → Beoordelingscriteria)
De onderdelen waarop gescoord wordt, per test, één criterium per regel. Standaard
gevuld met de twee lijsten uit het draaiboek (Sporttest: warming-up, hardlopen,
sprint, teamspeler, sociaal, gedrag — Zwemtest: schoolslag, polocrawl, onderwater,
vervoeren, teamspeler, sociaal, gedrag). Een herstelknop zet ze terug naar het
draaiboek.

## Toegang
Beoordelaars gebruiken hun bestaande beheerdersaccount (rol begeleider of hoger).
Wie observaties/scores van een ander wil verwijderen, moet bestuur zijn — je eigen
notities mag je altijd weghalen.

## Nieuwe/gewijzigde bestanden
- `admin/selectiedag.php` — volledig herbouwd tot het nieuwe beoordeel-scherm
  (verving de oude vaste-kolommen-tabel)
- `includes/selectiedag.php` — model uitgebreid: criteria, scores (per beoordelaar),
  observaties
- `admin/instellingen.php` — tab "Beoordelingscriteria"
- `includes/versie.php` — 2.24.0 + changelog

## Databasemigratie (automatisch bij eerste laden)
Vier nieuwe tabellen: `selectie_score`, `selectie_observatie`, `selectie_besluit`
(die laatste is voor fase 4, staat alvast klaar). De oude `beoordelingen`-tabel
blijft ongemoeid — daar gaat geen data verloren, hij wordt alleen niet meer
gebruikt.

## Testchecklist
1. **Criteria** → Instellingen → Beoordelingscriteria. Staan de draaiboek-lijsten
   er? Pas er een aan, sla op, herstel weer.
2. **Observatie** → Selectiedag → 3 · Beoordelen, tik een naam open, typ een
   observatie, kies een soort, opslaan. Verschijnt hij in de lijst?
3. **Buiten je groep** → filter op een andere groep, noteer daar een observatie —
   mag dat?
4. **Sterren** → geef sterren op een criterium. Blijft het staan na verversen? Tik
   dezelfde ster nogmaals → wist hij?
5. **Meerdere beoordelaars** → laat iemand anders dezelfde persoon scoren →
   verschijnt "gem X (2)"?
6. **Verwijderen** → kun je je eigen observatie wissen? Die van een ander alleen als
   bestuur?

## Wat hierna komt
- Fase 4: nabespreking — alles per kandidaat bij elkaar (scores + observaties) met
  het eindbesluit (door naar TW / twijfel / niet) dat doorwerkt in de fase van de
  sollicitant.

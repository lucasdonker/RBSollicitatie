# Bundel 2.21.0 — Opruimronde

Geen nieuwe features, maar een grote schoonmaak na de drie planning-uitbreidingen.
Dode code weg, kapotte links gerepareerd, alles consistent gemaakt.

## Wat is opgeruimd

### Dode code verwijderd
- **`admin/api_verhindering.php`** — dit endpoint werd nergens meer aangeroepen
  sinds het verhindering-model is vervangen door bestuurs-correcties. Verwijderd.
- **`includes/beschikbaarheid_dagen.php`** teruggebracht van 110 naar 27 regels.
  De vier dode functies van het oude dag-precieze verhindering-model
  (`verhinderingen_auto_setup`, `verhinderingen_van`, `verhindering_toggle`,
  `beschikbaarheid_dagen`) zijn weg. De twee helpers die nog gebruikt worden
  (`seizoen_weektype`, `dag_kort`) blijven.
  > De databasetabel `beschikbaarheid_verhinderingen` blijft bestaan — er gaat geen
  > historische data verloren, de code raakt hem alleen niet meer aan.

### Kapotte links gerepareerd
- **Menu-link "Systeem & Updates"** verwees naar een pagina die niet bestond (404
  voor admins). Er is nu een echte `admin/systeem.php`: geïnstalleerde versie,
  volledige changelog, en de status van alle databasetabellen — met een knop om de
  automatische migraties handmatig uit te voeren. Handig direct na een upload.
- **Ondertekeningen-pagina** miste de layout-variabelen, waardoor het menu-item niet
  oplichtte, en toonde een dubbele titel. Beide opgelost.

### CSV-export herbouwd
- **`admin/export_beschikbaarheid.php`** draaide nog op de verlaten `beschikbaarheid`-
  tabel en leverde daardoor lege regels. Bovendien werd hij nergens aangeroepen.
  Nu exporteert hij de **loonplanning** van het gekozen scenario (naam, leeftijd,
  functie, beschikbare/ingeplande weken, dagen, kosten, budget, restant) — met een
  **⬇ CSV-knop** op de loonplanning-pagina zodat de penningmeester erbij kan.

### Diagnose bijgewerkt
- De diagnose-pagina controleerde nog verouderde tabellen. Nu checkt hij de juiste
  set (blok, verzoek, correctie, loon_scenario) en draait de juiste setup-routines.

### Changelog aangevuld
- De in-app changelog liep vier versies achter. 2.17 t/m 2.21 zijn toegevoegd, te
  bekijken op de nieuwe Systeem-pagina.

## Upload-instructies

1. **Verwijder op de server:** `admin/api_verhindering.php`
   (dit bestand zit bewust NIET in de zip — het moet weg, niet overschreven).
2. Upload de rest van de bundel zoals gebruikelijk (overschrijft de bestaande bestanden).
3. Open **Beheer → Instellingen → Systeem & Updates** (alleen admins) en controleer:
   - staat de versie op 2.21.0?
   - zijn alle tabellen groen? Zo niet: klik "Migraties uitvoeren".

## Gewijzigde/nieuwe bestanden in de zip
- `admin/systeem.php` — NIEUW
- `admin/export_beschikbaarheid.php` — herbouwd op het nieuwe model
- `includes/beschikbaarheid_dagen.php` — opgeschoond (110 → 27 regels)
- `admin/ondertekeningen.php` — layout-fix
- `admin/loonplanning.php` — CSV-exportknop toegevoegd
- `admin/seizoen.php` — dode aanroep verwijderd
- `diagnose.php` — tabellen/modellen bijgewerkt
- `includes/versie.php` — changelog aangevuld + versie 2.21.0

## Te verwijderen op de server (niet in de zip)
- `admin/api_verhindering.php`

## Testchecklist
1. **Systeem-pagina** → Instellingen → Systeem & Updates opent zonder 404? Versie
   2.21.0? Tabellen groen? Changelog zichtbaar?
2. **Ondertekeningen** → licht het menu-item nu op? Geen dubbele titel meer?
3. **CSV-export** → knop "⬇ CSV" op de loonplanning geeft een gevuld bestand?
4. **Niets kapot** → beschikbaarheid, dekking en loonplanning werken nog normaal?

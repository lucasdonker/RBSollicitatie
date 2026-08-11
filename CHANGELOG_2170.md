# Bundel 2.17.0 — Beschikbaarheid 3.0 (periode-blokken)

Grote herbouw van het beschikbaarheidssysteem naar het **periode-blokken model**.
Vervangt het datum-model uit 2.13.

## Wat er verandert voor de sollicitant (portaal, stap 4)
- **Periode-blokken** i.p.v. één begin/einddatum: "van week X tot week Y" met een
  regime — *hele weken (5 dagen)* of *alleen weekenden*. Standaard staat er één blok
  klaar (hele seizoen, hele weken), dus wie niks bijzonders heeft klikt gewoon door.
- **"+ Nog een periode"** voor gemengde profielen (bijv. weekenden in het voorseizoen
  + hele weken in het hoofdseizoen).
- **Afwezigheid** blijft als datumperiodes (vakantie), nu dag-precies verwerkt.
- **Vaste vrije dag**: bij een weekenddag (vr/za/zo) is een toelichting nu **verplicht**
  (client + server afgedwongen).
- **Live preview in mensentaal** ("Van week 23 tot week 26: alleen weekenden. Afwezig: …").
- **Minder dagen werken** = een **verzoek** aan het bestuur (weggestopt in een uitklap),
  telt pas mee ná goedkeuring.
- **Voorlopig-vinkje** met geruststelling + waarschuwing over de deadline.

## Wat het bestuur ziet
- **Dossier-paneel**: blokken, regimes, afwezigheid, vaste vrije dagen mét toelichting,
  opmerking, en een **✓ Definitief / ⏳ Voorlopig** badge. Openstaande verzoeken hebben
  **Goedkeuren / Afwijzen**-knoppen. Alle data nu expliciet zichtbaar.
- **Matrix-pagina**: bovenaan een overzicht **"Nog voorlopig: N personen"** met
  namen + mail-knop en de deadline-status.

## Deadline-push (automatisch)
- Deadline = **2 weken vóór het trainingsweekend**.
- Herinneringsmails starten **2 weken vóór de deadline**, herhalen elke 4 dagen,
  en na de deadline elke 2 dagen (dringender toon).
- Nieuwe mailtemplate **`reminder_beschikbaarheid`** wordt automatisch aangemaakt
  (aanpasbaar via Instellingen → Mailtemplates → categorie "Herinneringen").
- Portaal toont een **aftel-banner** bovenaan zolang iemand voorlopig is
  (oranje → rood naarmate de deadline nadert).

## Nieuwe/gewijzigde bestanden
- `includes/beschikbaarheid2.php` — volledig herschreven (blokken-model)
- `includes/functions.php` — 4e reminder in `reminder_run()` (deadline-push)
- `includes/mail_render.php` — seed van `reminder_beschikbaarheid` template
- `includes/versie.php` — 2.17.0
- `portaal/index.php` — nieuw stap-4 formulier, opslag-handler, read-only weergave, aftel-banner
- `admin/api_paneel.php` — nieuwe beschikbaarheid-weergave + verzoek-knoppen
- `admin/beschikbaarheid.php` — matrix leest `regime`; voorlopigen-overzicht
- `admin/api_verzoek.php` — **NIEUW** endpoint voor goedkeuren/afwijzen
- `admin/layout_bottom.php` — `verzoekBeslis()` JS

## Databasemigratie
Automatisch bij eerste laden. Nieuwe/gewijzigde tabellen (via `beschikbaarheid2_auto_setup`):
- `beschikbaarheid_blok` (van_week, tot_week, regime)
- `beschikbaarheid_profiel` — nieuwe kolommen: vaste_toelichting, voorlopig, definitief_op
- `beschikbaarheid_verzoek` (van_week, tot_week, dagen_aantal, toelichting, status)
- `beschikbaarheid_afwezig` blijft ongewijzigd

> **Let op:** oude weekpatroon-data (`beschikbaarheid_weekpatroon`) wordt niet
> gemigreerd. Als er al testinvoer stond, laat sollicitanten die opnieuw invullen —
> het nieuwe formulier start met één standaardblok, dus dat is een kwestie van
> doorklikken + opslaan.

## Testchecklist (op device)
1. **Portaal → aanmelding → stap 4**: staat er één blok klaar? Doorklikken zonder
   iets te wijzigen → opslaan → verschijnt het bij het bestuur als "hele weken"?
2. **"+ Nog een periode"** toevoegen (weekend-blok) → preview klopt in mensentaal?
3. **Vaste vrije dag = zaterdag** → dwingt het een toelichting af bij "Volgende"?
4. **Minder dagen (verzoek)** invullen → bij bestuur zichtbaar met Goedkeuren-knop?
   Na goedkeuren: zakt de norm in de matrix?
5. **Voorlopig aanvinken** → verschijnt de aftel-banner in het portaal + de persoon
   in het "Nog voorlopig"-overzicht op de matrix-pagina?
6. **Matrix**: kloppen de weken/dagen/weekend-signalen nog?

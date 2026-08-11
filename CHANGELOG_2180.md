# Bundel 2.18.0 — Beschikbaarheid 3.1 (feedback-ronde)

Verwerkt alle 15 feedbackpunten op Beschikbaarheid 3.0. Dit is een grote
inhoudelijke verbetering van zowel de sollicitant- als de beheerderskant.

## Sollicitantenkant (portaal)

1. **Overlap-melding** — als weken in meerdere periodes zitten, verschijnt live
   een gele waarschuwing ("Deze week zit in meerdere periodes — we gebruiken de
   eerste. Klopt dat?"). Loopt niet vast; puur een seintje.
2. **Weekend-dagen kiesbaar** — bij "alleen weekenden" verschijnt per periode
   vr/za/zo met vinkjes (standaard alle drie). Wie alleen za+zo kan, vinkt vrijdag uit.
3. **Vaste vrije dag → voorkeur** — omgevormd van harde blokkade naar een
   *voorkeur* die het bestuur meeneemt. Duidelijke melding: "Dit is een voorkeur,
   geen vaste afspraak — de planning maken we later samen." Achter een "Ja, ik heb
   een vaste dag"-drempel, met verplichte toelichting.
4. **Verzoek minder dagen** — nu met week-dropdowns (consistent met stap 1) i.p.v.
   nummervelden, plus optioneel de dagen aankruisen die je die week wél kunt (de
   planner kiest daar het afgesproken aantal uit).
6. **Nieuw overzicht-scherm** (`portaal/beschikbaarheid.php`) — mensentaal-
   samenvatting van wat wij begrepen hebben, verzoeken mét status (in behandeling
   / goedgekeurd / afgewezen), en werkende knoppen **Aanpassen** en
   **Definitief maken**. Werkt los van de BLK-fase, het hele seizoen door.
7. **Auto-definitief uitgelegd** — overal duidelijk: "werk je het niet bij, dan
   gebruiken we je huidige invoer automatisch vanaf 2 weken vóór het TW."
13. **Gidsje** — uitklapbaar "Hoe vul ik dit in?" met een concreet voorbeeld
   bovenaan stap 4 (doelgroep 15–25, vaak eerste bijbaan).
14. **Autosave** — tekstvelden (opleiding, motivatie, etc.) worden tussentijds
   opgeslagen via `api_blk_concept.php`. Geen verlies meer na een time-out.
15. **Harde validatie** — lege verplichte velden blokkeren nu écht, met een
   zichtbare tekstmelding bovenaan de stap (niet alleen een rode rand → WCAG).

## Beheerderskant

8. **Verzoek-goedkeuring op één plek** — nieuw centraal "Openstaande verzoeken"-
   overzicht op de matrix-pagina, met goedkeuren/afwijzen-knoppen. Ook per dossier
   beschikbaar. De door de sollicitant aangegeven beschikbare dagen worden getoond.
9. **Weekend-dagen in overzicht** — "alleen za/zo" zichtbaar in dossier + matrix.
10. **Weken-kolom** — aantal beschikbare weken naast de naam in de matrix.
11. **Verhindering herontworpen** — de cryptische dag-toggle is vervangen door een
   **bestuurs-correctie per week** met duidelijke uitleg en verplichte reden:
   "Kan deze week niet" of "Alleen bepaalde dagen". Bijvoorbeeld na een telefoontje.
12. **Voorkeur-vrije dagen in matrix** — zichtbaar als ⭐-badge naast de naam.

## Toegankelijkheid (WCAG)
- Foutmeldingen in tekst, niet alleen kleur (`role="alert"`, `aria-live`).
- `aria-label` op alle losse invoervelden; `aria-live="polite"` op de live-preview.
- Touch-targets ≥38–44px voor mobiel/PWA-gebruik.
- Logische focus-volgorde; duidelijke knoplabels.

## Nieuwe/gewijzigde bestanden
- `includes/beschikbaarheid2.php` — model uitgebreid: weekend_dagen, verzoek-pool,
  vaste-als-voorkeur (telt niet meer af), bestuurs-correcties, `_definitief_maken`,
  `_open_verzoeken`, `_correctie_zetten`
- `includes/functions.php` — (ongewijzigd t.o.v. 2.17 reminder, meegebundeld)
- `includes/mail_render.php` — (reminder-template, meegebundeld)
- `portaal/index.php` — stap-4 volledig herbouwd, autosave, WCAG-validatie, compacte read-only card
- `portaal/beschikbaarheid.php` — **NIEUW** overzicht-scherm
- `portaal/api_blk_concept.php` — **NIEUW** autosave-endpoint
- `admin/api_paneel.php` — weekend-dagen, voorkeur, verzoek-pool, week-correcties
- `admin/beschikbaarheid.php` — weken-kolom, voorkeur-badge, centraal verzoeken-overzicht, cel-klik → correctie
- `admin/api_verzoek.php` — (beslist_op, meegebundeld)
- `admin/api_correctie.php` — **NIEUW** endpoint voor week-correcties
- `admin/layout_bottom.php` — correctie-modal JS (vervangt dag-toggle)

## Databasemigratie (automatisch bij eerste laden)
Nieuwe kolommen/tabellen via `beschikbaarheid2_auto_setup`:
- `beschikbaarheid_blok.weekend_dagen`
- `beschikbaarheid_verzoek.dagen_pool`, `.beslist_op`
- `beschikbaarheid_profiel.vaste_is_voorkeur`
- **nieuw:** `beschikbaarheid_correctie` (week-correcties met reden)

> De oude `beschikbaarheid_verhinderingen`-tabel wordt niet meer gebruikt maar
> blijft staan (geen dataverlies). `api_verhindering.php` is nergens meer gekoppeld.

## Testchecklist (op device)
1. **Weekend-dagen:** kies "alleen weekenden", vink vrijdag uit → preview zegt
   "alleen za/zo"? Bij bestuur ook zo zichtbaar?
2. **Vaste vrije dag:** staat de melding "dit is een voorkeur, niks staat vast"?
   Dwingt hij een toelichting af?
3. **Verzoek:** week-dropdowns i.p.v. nummers? Kun je de dagen-pool aankruisen?
   Bij bestuur zichtbaar mét pool?
4. **Overzicht-scherm:** werkt "Definitief maken"? Zie je de verzoek-status?
5. **Autosave:** vul stap 2/3 in, wacht, ververs de pagina → staat je tekst er nog?
6. **Validatie:** probeer met een leeg verplicht veld door te klikken → blokkeert het
   met een tekstmelding?
7. **Beheer matrix:** zie je de weken-kolom + ⭐ voorkeur-badge + het centrale
   verzoeken-overzicht? Werkt cel-klik → week-correctie met reden?
8. **Overlap:** maak twee overlappende periodes → verschijnt de gele waarschuwing?

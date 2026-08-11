# Bundel 2.25.0 — Selectiedag fase 4: nabespreking (+ foto & bijzonderheden)

De laatste van vier fases. Hiermee is het selectiedag-systeem compleet:
**indeling → inchecken → beoordelen → nabespreking.**

## Wat is er nieuw

### Nabespreking (Selectiedag → 4 · Nabespreking, bestuur-only)
Alles per kandidaat op één plek, klaar voor het gesprek:
- **Foto** en **bijzonderheden** (allergieën e.d.) bovenaan.
- **Alle observaties** naast **alle scores** (gemiddeld over de beoordelaars, met
  tussen haakjes hoeveel mensen scoorden).
- **Eindbesluit** per kandidaat: ✓ door naar TW / ? twijfel / ✕ niet, met een
  optionele notitie. De kaartrand kleurt mee (groen/oranje/rood).
- **Filter** op besluit-status — zo pak je snel de twijfelgevallen.

**Doorvoeren in één klik** (onderaan): "door" → uitgenodigd voor het TW,
"niet" → afgewezen. Twijfelgevallen en nog-open blijven bewust staan. Met een
bevestiging vooraf, want het past de fase van de sollicitanten aan. Nogmaals
doorvoeren doet niets dubbel — al verwerkte kandidaten worden overgeslagen.

De scores blijven indicatief: het besluit is een bewuste keuze van het bestuur, niet
een automatische uitkomst van de cijfers.

### Verbeteringen aan fase 3 (beoordelen)
- **Foto** van de kandidaat in de beoordeelkaart — klein in de kop voor snelle
  herkenning, groot bij het openklappen.
- **Bijzonderheden** (allergieën, medicatie uit het BLK-formulier) worden getoond
  met een ⚠️-waarschuwing, zowel in de kop als uitgeklapt.

## Nieuwe/gewijzigde bestanden
- `admin/selectie_nabespreking.php` — NIEUW nabespreking-scherm
- `includes/selectiedag.php` — besluit-functies (opslaan, ophalen, doorvoeren) +
  foto/bijzonderheden toegevoegd aan de kandidaten-query
- `admin/selectiedag.php` — foto + bijzonderheden in de beoordeelkaart, nabespreking-
  link in de subnav
- `admin/selectie_inchecken.php` + `admin/selectie_indeling.php` — 4e stap in de
  subnav
- `includes/versie.php` — 2.25.0 + changelog

## Geen nieuwe tabellen
Gebruikt `selectie_besluit` die in fase 3 al was aangemaakt. Foto en bijzonderheden
komen uit de bestaande `blk_formulieren`-tabel.

## Testchecklist
1. **Foto bij beoordelen** → Selectiedag → 3 · Beoordelen. Zie je de foto's van
   kandidaten die een BLK hebben ingevuld? Een initialen-cirkel bij wie geen foto
   heeft?
2. **Bijzonderheden** → heeft iemand allergieën in het BLK? Zie je de ⚠️-melding?
3. **Nabespreking openen** → Selectiedag → 4 · Nabespreking. Staat per kandidaat
   alles bij elkaar (observaties + scores + foto)?
4. **Besluit** → klik "door naar TW" bij iemand. Kleurt de kaartrand groen? Blijft
   het staan na verversen?
5. **Filter** → filter op "twijfel" — zie je alleen de twijfelgevallen?
6. **Doorvoeren** → zet een testkandidaat op "door", klik Doorvoeren. Staat die daarna
   op "uitgenodigd voor TW" (check bij de sollicitant)? Blijft een twijfelgeval staan?

## Het selectiedag-systeem is nu compleet
1. **Groepsindeling** — eerlijk verdelen, houd-uit-elkaar, slepen
2. **Inchecken** — tablet-zelfservice + aanwezigheid per telefoon
3. **Beoordelen** — observaties + scores, meerdere beoordelaars, foto & bijzonderheden
4. **Nabespreking** — alles per kandidaat + eindbesluit dat doorwerkt in de fase

## Nog openstaand (van eerder, geen haast)
- Aparte Authentik-rol voor beoordelaars + backup-link (login-flow, apart klusje)
- Dagplanning (dag-precies rooster)
- UX/contrast-doorloop oudere adminpagina's

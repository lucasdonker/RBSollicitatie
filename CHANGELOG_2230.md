# Bundel 2.23.0 — Selectiedag fase 2: inchecken

Tweede van vier fases. Sollicitanten kunnen zichzelf aanmelden op een tablet, en
beheerders kunnen aanwezigheid afvinken vanaf hun eigen telefoon.

## Wat is er nieuw

### Tablet-inchecken (zelfservice)
Een kale, grote pagina voor op de iPad bij de ingang — geen login nodig.
- Sollicitant typt zijn naam, tikt zichzelf aan uit de lijst.
- Ziet meteen groot in beeld: "Je bent aangemeld! Je zit in **Groep 2**".
- "Volgende persoon" zet de tablet klaar voor de volgende.
- Al aangemelde mensen worden groen getoond → geen dubbele aanmeldingen.

Beveiligd met een **toegangscode** die in de link zit
(`/portaal/selectie_incheck.php?code=XXXXXX`). Zonder geldige code komt niemand
op de pagina. Dit is meteen de backup-toegang die geen account vereist.

### Beheer: inchecken (Selectiedag → 2 · Inchecken)
- **Tablet-code & -link** klaarzetten (bestuur genereert/vernieuwt de code).
- **Aanwezigheidslijst** per groep — vink mensen af vanaf je eigen telefoon.
  Begeleiders mogen dit ook (niet alleen bestuur).
- Teller "12/40 aanwezig" en een zoekveld om snel iemand te vinden.

Inchecken (op de tablet én via de lijst) zet de sollicitant automatisch op de fase
"selectiedag geweest".

## Nieuwe/gewijzigde bestanden
- `portaal/selectie_incheck.php` — NIEUW tablet-zelfservicepagina
- `admin/selectie_inchecken.php` — NIEUW beheer-incheckpagina
- `includes/selectiedag.php` — check-in functies: zoeken op naam, aanwezig zetten
- `includes/functions.php` — helper `huidige_gebruiker_rol_minstens()`
- `admin/selectie_indeling.php` + `admin/selectiedag.php` — subnav uitgebreid naar
  3 stappen
- `includes/versie.php` — 2.23.0 + changelog

## Geen nieuwe tabellen
Gebruikt de `aanwezig`- en `ingecheckt_op`-velden die al in `selectie_indeling`
zaten (fase 1). De toegangscode wordt opgeslagen als instelling
(`selectie.incheck_code`).

## Testchecklist
1. **Code aanmaken** → Selectiedag → 2 · Inchecken → "Toegangscode aanmaken"
   (als bestuur). Verschijnt er een code + link?
2. **Tablet openen** → open de link op een tweede apparaat/tab. Kun je een naam
   typen en jezelf aanmelden? Zie je de groep groot in beeld?
3. **Groen bij dubbel** → zoek dezelfde persoon nogmaals → staat die op "✓ al
   aangemeld"?
4. **Beheer afvinken** → vink iemand af in de aanwezigheidslijst. Loopt de teller
   mee? Blijft het staan na verversen?
5. **Verkeerde code** → open de tablet-link met een fout code → krijg je "geen
   geldige toegangscode"?
6. **Fase** → een ingecheckte sollicitant → staat die nu op "selectiedag geweest"?

## Wat hierna komt
- Fase 3: beoordelen op de dag (scores + observaties, meerdere beoordelaars,
  Authentik-rol + backup-link)
- Fase 4: nabespreking met alles per kandidaat + eindbesluit

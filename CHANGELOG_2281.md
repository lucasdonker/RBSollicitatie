# Bundel 2.28.1 — Portaal-fixes (3 punten)

Drie meldingen uit het portaal opgelost.

## 1. Ouder-gegevens waren verdwenen
**Oorzaak:** er waren twee formulier-handlers die allebei op `ouder_submit`
reageerden (de nieuwe met telefoonnummer uit 2.26, en een oudere voor het bijwerken
van een specifieke ondertekening). Ze botsten, waardoor de ouder-invoer niet meer
goed werkte.

**Fix:**
- De handlers zijn uit elkaar getrokken (de nieuwe reageert alleen als er géén
  `ond_id` wordt meegestuurd).
- De ouder-taak verschijnt weer voor minderjarigen. Als de gegevens nog niet zijn
  ingevuld: als **open taak** met het formulier. Als ze al ingevuld zijn: als
  **afgeronde taak** met een **"Wijzigen"-knop** en de bestaande waarden
  voor-ingevuld.

## 2. BLK-formulier stond altijd zichtbaar
Het "Vertel ons meer over jezelf"-blok stond als losse kaart onder de taken, ook
voordat je de taak opende.

**Fix:** de kaart is nu standaard verborgen en verschijnt pas als je op de taak
**"Openen →"** klikt. Als het BLK al is ingevuld, blijft de kaart zichtbaar zodat je
'm kunt bekijken of bewerken.

## 3. AI-beschikbaarheid: input niet geblokkeerd tijdens verwerken
Bij het klikken op "Vul formulier in" bleef het tekstvak bewerkbaar terwijl de AR
bezig was.

**Fix:** tijdens het verwerken worden nu zowel de knop als het tekstvak geblokkeerd
(readonly + gedimd), met een duidelijke status "De AI leest je tekst…". Na afloop
komt alles weer vrij.

## Gewijzigde bestanden
- `portaal/index.php` — alle drie de fixes
- `includes/versie.php` — 2.28.1 + changelog

## Geen databasewijzigingen

## Testchecklist
1. **Ouder-gegevens (nog niet ingevuld)** → minderjarige sollicitant zonder
   ouder-gegevens: verschijnt de taak met het formulier (naam, e-mail, telefoon)?
2. **Ouder-gegevens (al ingevuld)** → verschijnt de taak als afgerond, met een
   "Wijzigen"-knop die het formulier met bestaande waarden opent?
3. **BLK verborgen** → is "Vertel ons meer over jezelf" niet meer zichtbaar tot je op
   "Openen →" klikt bij de taak? (Tenzij al ingevuld.)
4. **BLK openen** → opent en scrollt de kaart netjes bij een klik op "Openen →"?
5. **AI-invoer blokkeren** → klik "Vul formulier in": wordt het tekstvak grijs/
   readonly tijdens het verwerken en komt het daarna weer vrij?

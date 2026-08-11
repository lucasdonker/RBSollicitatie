# Bundel 2.29.0 — BLK autosave (UX-punt 1 van 4)

Eerste van vier UX-verbeteringen aan het portaal. Dit punt maakt het
"Beter leren kennen"-formulier robuust tegen dataverlies.

## Wat is er nieuw

### Automatisch opslaan van je voortgang
Het BLK-formulier bewaart de invoer nu automatisch:
- **Tijdens het typen** — na een korte pauze (2,5 sec) wordt het concept opgeslagen.
- **Bij het wisselen van stap** — elke keer dat je op "Volgende" klikt.
- **Bij het verlaten van de pagina** — als laatste redmiddel (via sendBeacon).

Voorheen werkte dit maar voor een deel van de velden; nu worden alle tekstvelden
meegenomen (adres, opleiding, werkervaring, diploma's, motivatie, beschikbaarheid-
uitzonderingen, social media, bijzonderheden).

### Zichtbare opslag-status
Bovenaan het formulier staat nu een statusregel die laat zien wanneer je concept
voor het laatst is opgeslagen: "✓ Concept opgeslagen om 14:32". Dat geeft de
sollicitant vertrouwen dat er niets verloren gaat.

## Hoe het werkt (techniek)
Het bestaande endpoint `portaal/api_blk_concept.php` slaat een concept op in de
`blk_formulieren`-tabel **zonder** de fase te wijzigen of mails te sturen — puur een
buffer. De definitieve verzending (met validatie, wijzigingslog en eventuele mails)
blijft ongewijzigd via de normale "Verzenden"-knop.

## Gewijzigde bestanden
- `portaal/api_blk_concept.php` — accepteert nu alle tekstvelden + geeft tijdstip terug
- `portaal/index.php` — autosave voor alle velden, statusindicator
- `includes/versie.php` — 2.29.0 + changelog

## Geen databasewijzigingen
Gebruikt de bestaande `blk_formulieren`-tabel.

## Testchecklist
1. **Typen** → begin een veld in te vullen, wacht ~3 sec → verschijnt "✓ Concept
   opgeslagen om HH:MM"?
2. **Herladen** → vul iets in, wacht op de bevestiging, herlaad de pagina → staat je
   invoer er nog?
3. **Stapwissel** → vul stap 1 in, klik "Volgende" → wordt er opgeslagen?
4. **Definitief verzenden** → vul alles in en verzend op stap 5 → werkt de normale
   opslag nog (met de mails/logging)?

## Volgende punten (nog te doen)
- Punt 2: BLK integreren met de takenlijst
- Punt 3: Ouder-flow met duidelijke status per document
- Punt 4: Consistente foutafhandeling

# Bundel 2.28.2 — Bugfix: formulieren sloegen niet op + AI-invoer bewaren

## De hoofdbug: formulieren deden "niks"
Zowel "Versturen naar ouder" als het opslaan van het BLK-formulier leek soms niets
te doen — geen melding, geen opslag.

**Oorzaak:** het BLK is één groot formulier met 5 stappen die met CSS worden
verborgen. Verplichte velden (`required`) op stappen die op dat moment verborgen
zijn, zorgen ervoor dat de browser de verzending stil afbreekt — hij wil een
validatiefout tonen op een veld dat hij niet kan focussen, en doet dan gewoon niks.
Bij het ouder-formulier speelde hetzelfde zodra het verborgen stond.

**Fix:** de `required`-attributen zijn vervangen door een eigen JavaScript-validatie.
- Bij het BLK springt de validatie naar de eerste stap waar nog iets ontbreekt en
  toont daar de melding.
- Het ouder-formulier valideert naam, e-mail en telefoon in JavaScript met een
  duidelijke foutmelding.
- De server-side validatie blijft ongewijzigd als vangnet.

Hierdoor slaan de beschikbaarheid én de BLK-voortgang weer betrouwbaar op.

## AI-invoer wordt nu bewaard
De AI-beschikbaarheid bewaart voortaan de originele tekst die de sollicitant typte,
samen met de status en het gevalideerde resultaat, in een nieuwe tabel
`ai_beschikbaarheid_log`. Handig voor latere referentie en om te zien hoe de AI
bepaalde invoer heeft geïnterpreteerd.

## Gewijzigde bestanden
- `portaal/index.php` — validatie-fix (ouder + BLK), AI-invoer loggen
- `includes/ai_beschikbaarheid.php` — nieuwe functie `ai_besch_log()`
- `includes/versie.php` — 2.28.2 + changelog

## Databasemigratie (automatisch)
Nieuwe tabel `ai_beschikbaarheid_log` wordt automatisch aangemaakt bij het eerste
AI-verzoek.

## Testchecklist
1. **Ouder versturen** → vul naam, e-mail, telefoon in → klik "Versturen naar
   ouder". Komt de bevestiging + gaat de mail eruit?
2. **Ouder ongeldig** → laat een veld leeg → verschijnt de foutmelding (i.p.v. niks)?
3. **BLK opslaan** → vul alle stappen → verzend op stap 5. Wordt alles opgeslagen?
4. **BLK incompleet** → laat op stap 2 de opleiding leeg, ga naar stap 5, verzend →
   springt het formulier terug naar stap 2 met een melding?
5. **AI-log** → gebruik de AI-invoer → wordt er een rij toegevoegd in
   `ai_beschikbaarheid_log`?

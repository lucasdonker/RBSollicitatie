# Bundel 2.29.4 — Fix: ouder-gegevens versturen

## Het probleem
"Versturen naar ouder" leek niks te doen: het formulier leegde (pagina herlaadde),
maar er verscheen geen bevestiging en er gebeurde niets.

## De oorzaak
De handler deed drie dingen in één `try`-blok: (1) gegevens opslaan, (2) de
ouder-documenten klaarzetten via `ondertekeningen_klaarzetten_sd()`, en (3) de
bevestigingsmelding zetten. Als stap 2 een fout gooide (die functie zit op de
server, buiten deze codebase), sprong de code naar de `catch` — die leeg was — en
werd de bevestiging nooit gezet. De pagina redirectte alsnog, dus het oogde alsof er
niets gebeurde. Mogelijk werd zelfs de opslag teruggedraaid.

## De fix
- **Opslag ontkoppeld van de vervolgstappen.** De gegevens worden nu eerst
  opgeslagen en bevestigd; het klaarzetten van de documenten en de mail gebeuren
  daarna, elk in hun eigen `try` — een fout daar blokkeert de opslag/bevestiging
  niet meer.
- **Concrete meldingen.** Lukt de opslag niet, dan zie je nu een duidelijke fout in
  plaats van niets. Lukt de opslag wél maar het klaarzetten niet, dan krijg je een
  passende melding (en het wordt gelogd).
- **Betrouwbaardere query.** De `UPDATE ... WHERE id=(SELECT ...)` subquery is
  vervangen door twee losse stappen: eerst het persoon-id opzoeken, dan bijwerken.

## Gewijzigde bestanden
- `portaal/index.php`
- `includes/versie.php`

## Geen databasewijzigingen

## Testinstructie
1. Log in als minderjarige testsollicitant, vul naam/e-mail/telefoon van de ouder in,
   klik "Versturen naar ouder".
2. Verwacht: bevestiging bovenaan ("Bedankt! Je ouder/verzorger ontvangt nu een
   mail…") en de gegevens blijven bewaard (bij herladen zie je de afgeronde taak met
   "Wijzigen").
3. Krijg je in plaats daarvan de melding "De gegevens zijn opgeslagen. Het klaarzetten
   van de formulieren lukte nog niet…"? Dan is de opslag gelukt maar zit er een
   probleem in `ondertekeningen_klaarzetten_sd()` op de server — laat het me weten,
   dan kijken we daar gericht naar (en check dan ook even de PHP error-log).

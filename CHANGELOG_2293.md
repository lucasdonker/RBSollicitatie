# Bundel 2.29.3 — Fix: BLK-taak + ouder-verzending

Twee regressies uit de vorige bundel opgelost.

## 1. BLK-taak verscheen niet bij "aangemeld"
De taak "Vul het Beter leren kennen formulier in" stond alleen in de takenlijst zolang
je fase `uitgenodigd_selectie` was. Zodra je je had aangemeld voor de selectiedag
(fase `aangemeld_selectie`) verdween de taak — terwijl het formulier eronder wél
zichtbaar bleef. Dat gaf een mismatch.

**Fix:** de BLK-taak toont nu bij zowel `uitgenodigd_selectie` als
`aangemeld_selectie`, gelijk aan de conditie van het formulier zelf.

## 2. "Versturen naar ouder" deed soms niets
Het ouder-formulier is robuuster gemaakt:
- Bij ongeldige invoer verschijnt nu altijd een zichtbare foutmelding (met een
  fallback als de foutbox onverhoopt ontbreekt), en er wordt naartoe gescrolld.
- Bij geldige invoer verzendt het formulier betrouwbaar, ook als de
  spinner-hulpfunctie om wat voor reden dan ook niet beschikbaar is.

## Gewijzigde bestanden
- `portaal/index.php`
- `includes/versie.php`

## Geen databasewijzigingen

## Testinstructie (belangrijk)
Voor bug 2 wil ik je vragen dit gericht te testen, want ik kon het in de
ontwikkelomgeving niet één-op-één reproduceren (de container bereikt de echte
database/browser niet):

1. Log in als een **minderjarige** testsollicitant in fase `uitgenodigd_selectie`
   of `aangemeld_selectie`.
2. Zie je nu de taak "Vul de gegevens van je ouder/verzorger in" met het formulier?
3. Vul naam, e-mail en telefoon in, klik "Versturen naar ouder".
   - Werkt het nu (bevestiging bovenaan + de ouder krijgt een mail)?
   - Zo niet: laat een veld leeg en kijk of er nu wél een rode foutmelding onder het
     formulier verschijnt. Als die verschijnt, weten we dat de JS draait en zit het
     probleem elders (dan hoor ik graag wat je ziet).
4. Controleer ook of de BLK-taak nu in de lijst staat na aanmelding.

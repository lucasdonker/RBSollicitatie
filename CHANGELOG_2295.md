# Bundel 2.29.5 — Fix: ouder-gegevens opslaan (ontbrekende kolommen)

## De oorzaak (nu bevestigd)
De foutmelding "Er ging iets mis bij het opslaan" kwam doordat de opslag-query
faalde met "no such column: ouder_naam". De kolommen `ouder_naam`, `ouder_email` en
`ouder_telefoon` bestaan nog niet in de `personen`-tabel op de server, en de
functie die ze automatisch zou aanmaken had dat nooit (goed) gedaan — waarschijnlijk
omdat een oudere versie van die functie op de server draait, of omdat de aanmaak
eerder stil faalde.

## De fix
De handler maakt de drie ouder-kolommen nu **direct aan vlak vóór het opslaan**, met
een controle of ze al bestaan. Zo hangt het opslaan niet meer af van een aparte
setup-stap die maar één keer draait.

Daarnaast: mocht er tóch nog een databasefout optreden, dan toont de melding nu de
**concrete oorzaak** in plaats van een algemene tekst. Dat is tijdelijk voor
diagnose — zodra bevestigd is dat het werkt, kan die volledige fouttekst weer
ingekort worden.

## Gewijzigde bestanden
- `portaal/index.php`
- `includes/versie.php`

## Databasewijziging (automatisch)
Bij de eerste keer "Versturen naar ouder" worden de kolommen `ouder_naam`,
`ouder_email`, `ouder_telefoon` toegevoegd aan de `personen`-tabel (als ze nog niet
bestaan). Dit vereist ALTER-rechten op de database — die heb je normaal voor je
eigen Cloud86-database.

## Testinstructie
1. Upload deze bundel.
2. Log in als minderjarige testsollicitant, vul de ouder-gegevens in, klik
   "Versturen naar ouder".
3. Verwacht nu: de bevestiging bovenaan, en de gegevens blijven bewaard.
4. **Als je nóg een fout ziet:** de melding bevat nu de exacte databasefout. Stuur
   die letterlijk door, dan weet ik meteen wat er speelt. (Bijvoorbeeld: als er
   "ALTER command denied" staat, dan geeft de hosting geen rechten om kolommen toe te
   voegen en pakken we het via een aparte tabel aan.)

## Opmerking
Zodra bevestigd is dat het werkt, lever ik een kleine opschoonbundel die de
volledige technische foutmelding weer vervangt door een nette gebruikerstekst.

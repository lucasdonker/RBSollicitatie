# Bundel 2.29.2 — UX-verbeteringen portaal (punt 1 t/m 4 compleet)

Deze bundel bevat alle vier de UX-verbeteringen samen, zodat je in één keer kunt
testen. (Het is de opeenstapeling van 2.29.0 → 2.29.1 → 2.29.2.)

## Punt 1 — BLK automatisch opslaan
Het "Beter leren kennen"-formulier bewaart je invoer nu automatisch: tijdens het
typen (na ~2,5 sec pauze), bij elke stapwissel, en bij het verlaten van de pagina.
Alle tekstvelden worden meegenomen. Bovenaan het formulier staat een statusregel:
"✓ Concept opgeslagen om 14:32". Zo raak je nooit voortgang kwijt.

## Punt 2 — BLK gekoppeld aan de takenlijst
De verwarrende dubbeling tussen de taak en de losse "Vertel ons meer"-kaart is
weggenomen. De taak is nu de duidelijke ingang ("Openen →"), en de misleidende
badge "Bij je acties hierboven" is verwijderd. Klikken op de taak opent het
formulier en scrollt er soepel naartoe.

## Punt 3 — Duidelijke ouder-status
Nieuw overzicht "Formulieren van je ouder/verzorger": per document zie je in één
oogopslag de status — verstuurd en wachtend op ondertekening (📨), of al getekend
met datum (✅) — plus een teller "X/Y ondertekend". Vervangt de losse
ouder-documenttaken die eerder tussen andere taken zweefden.

## Punt 4 — Consistente meldingen
Alle meldingen (succes én fout) verschijnen nu op één vaste plek bovenaan het
portaal, in plaats van verspreid over de pagina. Twee bijna-identieke interne
meldingsvariabelen (`flash_portaal` vs `portaal_flash`) zijn opgeruimd — die konden
verwarring en gemiste meldingen veroorzaken.

## Gewijzigde bestanden
- `portaal/index.php` — alle vier de punten
- `portaal/api_blk_concept.php` — autosave voor alle velden (punt 1)
- `includes/versie.php` — 2.29.2 + changelog

## Geen databasewijzigingen
Alles gebruikt bestaande tabellen (`blk_formulieren`, `ondertekeningen`).

## Testchecklist (alle punten)
**Punt 1 — autosave**
1. Vul iets in het BLK, wacht ~3 sec → verschijnt "✓ Concept opgeslagen om HH:MM"?
2. Herlaad de pagina → staat je invoer er nog?
3. Verzend definitief op stap 5 → werkt de normale opslag (met mails) nog?

**Punt 2 — koppeling**
4. Is de losse kaart weg / niet meer verwarrend als het BLK nog niet is ingevuld?
5. Opent "Openen →" bij de taak het formulier en scrollt het ernaartoe?

**Punt 3 — ouder-status** (test met een minderjarige sollicitant met verstuurde
ouder-documenten)
6. Zie je het blok "Formulieren van je ouder/verzorger" met de juiste status per
   document en de teller "X/Y ondertekend"?

**Punt 4 — meldingen**
7. Verschijnen meldingen (bijv. na "Versturen naar ouder") bovenaan het portaal,
   consistent gestyled?

## Losse bundels (indien je per punt wilt uitrollen)
- `bundel_2290.zip` — alleen punt 1
- `bundel_2291.zip` — alleen punt 2
- `bundel_2292.zip` — punt 3 + 4
Deze gecombineerde bundel bevat alles; gebruik óf deze, óf de losse in volgorde.

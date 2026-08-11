# Bundel 2.27.0 — Taak-meldingen per mail (functioneel)

Twee nieuwe mails, functioneel opgezet. Het hele mails/meldingen-stuk wordt later
herzien — dit is de werkende basis.

## Wat is er nieuw
- **"Er staan taken voor je klaar"** — gaat de deur uit wanneer de sollicitant de
  oudergegevens invult (moment dat de ondertekentaken bijkomen).
- **"Taak afgerond"** — bevestiging nadat het Beter leren kennen-formulier is
  ingevuld.
- **Dubbel-mail bescherming** — voorkomt dat dezelfde melding bij elke
  paginaverversing opnieuw wordt verstuurd (via mail_log, venster van 12 uur voor
  "taken klaar", kort voor "taak afgerond").

## Nieuwe/gewijzigde bestanden
- `includes/functions.php` — helpers `stuur_taak_mail()`, `mail_taken_klaar()`,
  `mail_taak_afgerond()`
- `portaal/index.php` — triggers: taken-klaar bij oudergegevens, taak-afgerond bij
  BLK-invulling
- `includes/versie.php` — 2.27.0 + changelog

## Testchecklist
1. Vul als minderjarige de oudergegevens in → krijgt de sollicitant een
   "taken klaar"-mail?
2. Vul het BLK-formulier voor het eerst in → komt er een "taak afgerond"-mail?
3. Ververs de pagina een paar keer → komen er GEEN dubbele mails?

## Nog op de lijst
- Het complete mails- en meldingen-systeem herzien (templates, momenten, beheer)
- AI-ondersteuning bij de beschikbaarheid (experimenteel, instelbaar)

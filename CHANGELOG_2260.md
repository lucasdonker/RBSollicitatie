# Bundel 2.26.0 — Portaal-verbeteringen (ronde 1)

Eerste ronde van de portaal-feedback: telefoonnummer ouder, overzichtelijkere taken,
en een paar fixes.

## Wat is er nieuw / gefixt

### Telefoonnummer ouder/verzorger (verplicht)
Bij het invullen van de oudergegevens is telefoon nu een verplicht veld, naast naam
en e-mail. Minstens 8 cijfers. Het nummer is zichtbaar voor het bestuur in het
sollicitant-paneel bij de ouder-info (📞).

### Taken als kaartjes
Het "Jouw acties"-blok toont taken nu als losse kaartjes met een gekleurde rand en
een statusbadge:
- **Actie nodig** (oranje) — jij moet iets doen
- **Wachten op ouder** (blauw) — verstuurd, wachtend op handtekening
- **Ondertekend** (groen) — klaar

Veel overzichtelijker dan de vorige platte lijst, vooral als er meerdere ouder-
formulieren openstaan.

### BLK-formulier duidelijker gekoppeld
"Vertel ons meer over jezelf" krijgt nu een badge ("Bij je acties hierboven" of
"✓ Ingevuld") zodat het niet meer als losse, zwevende sectie voelt die al open
lijkt te staan voordat je eraan toe bent.

### Bug: onterechte "reden verplicht" bij BLK insturen
Bij het insturen van het BLK-formulier werd soms gevraagd om "een reden voor de
wijziging", ook als je niets aan het formulier had veranderd (bijvoorbeeld als je
alleen je beschikbaarheid bijwerkte). De reden wordt nu alleen gevraagd als er
daadwerkelijk iets aan het BLK is gewijzigd.

## Nieuwe/gewijzigde bestanden
- `portaal/index.php` — telefoonveld, taak-kaartjes, BLK-badge, reden-fix
- `includes/functions.php` — `personen_ouder_auto_setup()` maakt nu de ouder-velden
  aan (naam, e-mail, telefoon) — was eerder alleen een aanroep zonder definitie
- `admin/api_paneel.php` — toont ouder-telefoonnummer in het paneel
- `includes/versie.php` — 2.26.0 + changelog

## Databasemigratie (automatisch)
`personen.ouder_telefoon` wordt toegevoegd (naast de bestaande ouder_naam /
ouder_email, die nu ook gegarandeerd worden aangemaakt).

## Testchecklist
1. **Telefoon** → minderjarige sollicitant, ouder-gegevens invullen. Is telefoon nu
   verplicht? Krijg je een foutmelding zonder geldig nummer?
2. **Zichtbaar voor bestuur** → open de sollicitant in het admin-paneel. Zie je het
   telefoonnummer bij de ouder-info?
3. **Kaartjes** → staan de taken nu als kaartjes met statusbadges? Zie je "Wachten op
   ouder" bij de verstuurde formulieren?
4. **BLK-badge** → is het "Vertel ons meer"-blok nu voorzien van een badge?
5. **BLK-bug** → vul het BLK in en stuur het in. Werkt dat zonder onterechte
   reden-melding? En als je het opnieuw opent en écht iets wijzigt, vraagt hij dan
   wél om een reden?

## Nog op de lijst (volgende rondes)
- Mail "er staan taken voor je klaar" + mail "taak afgerond"
- AI-ondersteuning bij de beschikbaarheid (experimenteel, instelbaar)

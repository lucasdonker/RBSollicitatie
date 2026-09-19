# Landingspagina's — handleiding

Een landingspagina is een eigen wervingspagina voor één doelgroep, met het aanmeldformulier
erin. Je maakt het ontwerp waar je wilt (bijvoorbeeld in Claude Design) en uploadt het hier
als HTML-bestand.

Te vinden onder **Beheer › Landingspagina's**. Je hebt het recht `paginas` nodig.

---

## In het kort

1. Maak een pagina aan (titel + adres).
2. Maak een ontwerp en zet er `[[formulier]]` in waar het aanmeldformulier moet komen.
3. Upload het `.html`-bestand.
4. Lees het rapport en bevestig. Het ontwerp staat dan live.
5. Zet de status op **Gepubliceerd**.

De pagina is dan bereikbaar op `https://sollicitatie.paal13.com/p/<adres>`.

---

## Het ontwerp maken

Eén `.html`-bestand, met de opmaak en eventuele scripts erin. Dat is precies wat een export
uit Claude Design oplevert — die kun je rechtstreeks uploaden.

**Zet `[[formulier]]` in het ontwerp** op de plek waar het aanmeldformulier moet komen.
Zonder die markering staat er geen formulier op de pagina; het wordt er niet vanzelf
onderaan bij geplakt, want dat zou het ontwerp verpesten.

Andere markeringen die je kunt gebruiken:

| Markering | Wordt |
|---|---|
| `[[formulier]]` | Het aanmeldformulier |
| `[[seizoen]]` | De naam van het huidige seizoen, bijvoorbeeld "zomer 2027" |
| `[[aanmeld_url]]` | Het adres van het formulier, handig voor een eigen knop |
| `[[bron]]` | De bron-code van deze pagina |

Een markering die je verkeerd typt, blijft gewoon zichtbaar op de pagina staan. Zie je
`[[formulir]]` staan, dan weet je meteen wat eraan scheelt.

### Afbeeldingen

Upload afbeeldingen los bij de pagina (JPG, PNG, GIF of WebP) en verwijs ernaar met alleen
de bestandsnaam:

```html
<img src="strand.jpg" alt="Het strand bij Paal 13">
```

Geef elke afbeelding een `alt`-tekst: een korte omschrijving als hij iets vertelt, of
`alt=""` als hij alleen versiering is.

---

## Het rapport

Het ontwerp wordt niet aangepast bij het uploaden — animaties en interactie blijven dus
werken. Maar daardoor kan er ook van alles in zitten wat je niet wilt. Daarom krijg je na
het inlezen een rapport te zien, en gaat het ontwerp pas live als je dat bevestigt.

Waar het rapport op let:

- **Let op** — scripts van andere websites, formulieren die naar een andere partij sturen,
  en bekende trackers zoals Google Analytics.
- **Aandacht** — scripts in het bestand zelf, externe lettertypen (Google Fonts stuurt het
  IP-adres van de bezoeker naar de VS), code die netwerkverkeer opzet, en cookies of
  browseropslag.
- **Klein punt** — afbeeldingen van andere websites, `onclick`-acties, `javascript:`-links.
- **Tip** — een ontbrekend `[[formulier]]`, taal, titel, hoofdkop of alt-teksten.

Een ontwerp dat je zelf hebt gemaakt en dat alles in het bestand zelf heeft staan, geeft
meestal helemaal geen bevindingen.

### Externe bronnen toestaan

Verwijst het ontwerp naar een andere website — bijvoorbeeld `cdn.tailwindcss.com` voor de
opmaak — dan wordt die **standaard geblokkeerd** door de browser. De pagina ziet er dan
anders uit dan bedoeld.

Bij het bevestigen van het rapport staat per host een vinkje om hem toe te staan. Vink
alleen aan wat je vertrouwt en wat het ontwerp echt nodig heeft. Dat wordt vastgelegd in
het logboek.

Beter dan toestaan is meestal: het lettertype of de opmaak in het bestand zelf zetten. Dan
werkt de pagina ook als die andere website eruit ligt, en gaan er geen gegevens van
bezoekers naar derden.

---

## Versies

Elke upload is een nieuwe versie. Je ziet ze allemaal terug, kunt elke versie bekijken en
met één klik terugzetten. De laatste tien versies blijven bewaard.

Een net geüploade versie staat nog niet live: eerst het rapport bevestigen.

---

## Status

| Status | Wat bezoekers zien |
|---|---|
| Concept | Een 404, behalve voor ingelogde beheerders |
| Alleen beheerders | Hetzelfde — handig om iets voor te leggen aan het bestuur |
| Gepubliceerd | De pagina, voor iedereen |

Pas bij **Gepubliceerd** tellen bezoeken mee in de statistiek. Kijk je zelf als beheerder
naar een pagina die nog niet openbaar is, dan zie je bovenaan een balk en telt je bezoek
niet mee.

Standaard staat een pagina op "niet vindbaar via Google". Dat kun je per pagina aanzetten.

---

## Herkomst bijhouden

Elke pagina heeft een **bron-code**. Die reist mee tot in het dossier van de sollicitant,
dus je ziet later precies welke pagina welke aanmeldingen heeft opgeleverd — en hoeveel
daarvan uiteindelijk zijn aangenomen. Dat overzicht staat op het dashboard en onderaan het
overzicht van landingspagina's.

Gebruik je dezelfde pagina voor meerdere advertenties, dan kun je de bron ook in het adres
meegeven: `.../aanmelden.php?bron=instagram-mei`.

---

## Wat we van bezoekers bijhouden

Zo min mogelijk. De pagina zet **geen cookies** en start geen sessie, dus er is geen
cookiemelding nodig.

Om unieke bezoekers te kunnen tellen wordt een versleutelde code bewaard die elke dag
verandert en niet naar een persoon te herleiden is. Die code wordt na 30 dagen gewist; de
aantallen blijven. De cijfers zelf verdwijnen na 13 maanden. Beide termijnen staan bij
**Instellingen › Bewaartermijnen**.

Let op: zet je ontwerp zelf een cookie of een tracker, dan gelden de gewone regels wél en is
de brigade daarvoor verantwoordelijk. Het rapport waarschuwt daarvoor, en de browser
blokkeert het tot je het toestaat.

---

## Het adres wijzigen

Wijzig je het adres van een pagina, dan verhuizen de afbeeldingen automatisch mee — maar
**bestaande links, QR-codes en advertenties werken dan niet meer**. Kies het adres dus
meteen goed.

Oude adressen van vóór deze versie (`/l/<naam>`) sturen automatisch door naar `/p/<naam>`.

---

## Als de pagina niet laadt

- **404 terwijl de pagina bestaat** — staat de status op Gepubliceerd? En is er een
  bevestigde versie? Werkt `/p/<naam>` niet maar `/p.php?s=<naam>` wel, dan ontbreekt de
  rewrite-regel in `.htaccess`; het overzichtsscherm laat dan zien welke regels erin moeten.
- **De pagina ziet er kaal uit** — waarschijnlijk wordt een externe bron geblokkeerd. Kijk
  in het rapport van de versie welke hosts erin zitten en sta ze toe, of zet de opmaak in
  het bestand zelf.
- **Het formulier staat er niet** — `[[formulier]]` ontbreekt in het ontwerp, of het seizoen
  staat niet op "open". In dat laatste geval verschijnt er een melding dat de aanmelding
  gesloten is.

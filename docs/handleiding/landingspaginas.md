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

Je kunt er meerdere tegelijk kiezen (maximaal 20 per keer); per bestand hoor je of het
gelukt is.

**Staat er een map voor de bestandsnaam?** Dat mag. Een ontwerp uit een ontwerptool
verwijst meestal naar zijn eigen mappen (`assets/strand.jpg`, `images/duinen.jpg`). Bij ons
staan alle afbeeldingen van een pagina bij elkaar in één map, dus telt alleen de
bestandsnaam. Bij het inlezen worden die verwijzingen automatisch rechtgezet — ook namen
met hoofdletters of spaties, want die worden bij het uploaden omgezet ("Strand Foto.JPG"
wordt `strand-foto.jpg`). In het rapport zie je precies wat er is aangepast, en in de lijst
onder het uploadveld staan de namen zoals ze werkelijk zijn opgeslagen.

Verwijst het ontwerp naar een afbeelding die er nog niet is, dan zegt het rapport welke
namen hij verwacht. Upload ze daarna onder die naam; opnieuw inlezen hoeft dan niet.

De extensie blijft altijd die van het bestand dat je uploadt. Het systeem kijkt wél naar wat
er écht in zit: een foto die `duinen.jpg` heet maar in werkelijkheid een PNG is (dat gebeurt
makkelijk na bewerken of hernoemen) wordt gewoon opgeslagen als `duinen.jpg`, zodat je
ontwerp blijft kloppen. Zit er iets in wat helemaal geen afbeelding is, dan wordt het
geweigerd en staat erbij wat het dan wél is.

Geef elke afbeelding een `alt`-tekst: een korte omschrijving als hij iets vertelt, of
`alt=""` als hij alleen versiering is.

**Zitten de foto's in het HTML-bestand verwerkt?** Dat gebeurt vanzelf als je een ontwerp
uit een ontwerptool downloadt: de foto's staan er dan als een lange reeks tekens in
(`data:image/jpeg;base64,…`) en het bestand wordt al snel tien keer zo groot. Laat het
vinkje **"Ingesloten afbeeldingen losmaken"** dan aan staan. Bij het inlezen worden ze
eruit gehaald en als losse afbeelding bij de pagina gezet (`ontwerp-1.jpg`, `ontwerp-2.jpg`
…); in het rapport zie je precies wat er is losgemaakt. Dat scheelt bezoekers veel
laadtijd, want de browser bewaart losse afbeeldingen en hoeft ze bij een volgend bezoek
niet opnieuw op te halen.

Wat niet als afbeelding herkend wordt (bijvoorbeeld een SVG-logo), blijft gewoon in het
bestand staan. Het ontwerp blijft dus altijd werken.

Wil je het liever zelf regelen, vraag dan in je ontwerptool om een export zonder
ingesloten afbeeldingen — met `<img src="strand.jpg">` en `background-image: url(duinen.jpg)`,
zonder `data:`-URI's en zonder verwijzingen naar andere websites. Die laatste worden
namelijk geblokkeerd (zie "Externe bronnen").

---

## Het rapport

Het ontwerp wordt niet aangepast bij het uploaden — animaties en interactie blijven dus
werken. (De enige uitzondering is het losmaken van ingesloten afbeeldingen hierboven, en
dat staat achter een vinkje dat je kunt uitzetten.) Maar daardoor kan er ook van alles in zitten wat je niet wilt. Daarom krijg je na
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

Dezelfde bezoeker telt hooguit **eens per 30 minuten** als bezoek op dezelfde pagina.
Vernieuwen, de terug-knop en een tabblad dat door de browser in de slaapstand is gezet en
daarna opnieuw laadt, tellen dus niet opnieuw mee. (Instelling `paginas.bezoek_venster_min`.)

Naast het bezoek houden we bij hoever iemand in het formulier kwam: begonnen met invullen,
stap 2 bereikt, stap 3 bereikt, verstuurd. Daarvan staat alleen het stapnummer geregistreerd
— nooit iets wat iemand heeft ingetypt. Dat is te zien bij een pagina onder **Waar haken
mensen af?**.

Eén regel daar leest anders dan de rest: **Halverwege weggegaan**. Dat is geen telling maar
een aanwijzing. Het signaal komt van een tabblad dat naar de achtergrond gaat, en dat gebeurt
ook als iemand even iets opzoekt en daarna gewoon doorgaat. Gebruik het om te zien bij welke
stap het vaakst gebeurt, niet als aantal afhakers.

Wil je helemaal niets meten, dan zet je **Instellingen › tracking** uit; dan schrijft het
systeem geen enkele gebeurtenis meer weg en verdwijnt ook het meetadres uit de pagina.

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

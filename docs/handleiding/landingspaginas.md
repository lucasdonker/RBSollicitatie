# Wervingspagina's — handleiding

Versie 2.101 · september 2026

Een wervingspagina (ook wel landingspagina) is een eigen pagina voor één doelgroep, met het
aanmeldformulier erin. Je maakt het ontwerp waar je wilt (bijvoorbeeld in Claude Design) en
uploadt het hier als HTML-bestand.

Te vinden onder **Beheer › Wervingspagina's** (menugroep Communicatie). Je hebt het recht
Wervingspagina's (`paginas`) nodig; dat hebben standaard het bestuur en de admin.

## Inhoud

1. [Aan de slag](#aan-de-slag)
2. [Het ontwerp maken](#het-ontwerp-maken)
3. [Het aanmeldformulier op de pagina](#het-aanmeldformulier-op-de-pagina)
4. [Het rapport](#het-rapport)
5. [Versies](#versies)
6. [Status en vindbaarheid](#status-en-vindbaarheid)
7. [Cijfers: waar haken mensen af?](#cijfers-waar-haken-mensen-af)
8. [Herkomst bijhouden](#herkomst-bijhouden)
9. [Wat we van bezoekers bijhouden](#wat-we-van-bezoekers-bijhouden)
10. [Het adres wijzigen](#het-adres-wijzigen)
11. [Een pagina verwijderen](#een-pagina-verwijderen)
12. [Als de pagina niet laadt](#als-de-pagina-niet-laadt)

---

## Aan de slag

> **In het kort:** pagina aanmaken, ontwerp met `[[formulier]]` uploaden, rapport bevestigen,
> status op Gepubliceerd. Dan staat de pagina op `https://sollicitatie.paal13.com/p/<adres>`.
> Datums en loon komen via markeringen uit het seizoen, dus de pagina veroudert niet.

**Je eerste wervingspagina in 6 stappen:**

1. **Pagina aanmaken.** Ga naar Beheer › Wervingspagina's en klik op **Nieuwe pagina**. Vul een
   titel in (bijvoorbeeld "Word lifeguard — zomer 2027") en een adres (bijvoorbeeld `zomer-2027`;
   kleine letters, cijfers en koppeltekens). Klik op **Aanmaken**. Kies het adres meteen goed:
   links en QR-codes hangen eraan.
2. **Ontwerp maken.** Maak één `.html`-bestand en zet `[[formulier]]` op de plek waar het
   aanmeldformulier moet komen (zie [Het ontwerp maken](#het-ontwerp-maken)).
3. **Ontwerp uploaden.** Kies bij "Ontwerp" het HTML-bestand. Laat het vinkje **Ingesloten
   afbeeldingen losmaken** aan staan en klik op **Inlezen**.
4. **Afbeeldingen toevoegen.** Zegt het rapport dat er afbeeldingen ontbreken, upload ze dan bij
   "Afbeeldingen" onder de naam die het rapport noemt.
5. **Rapport bevestigen.** Lees het rapport. Klik eventueel op **Eerst bekijken**. Vink alleen de
   externe bronnen aan die je vertrouwt, zet het vinkje **Ik heb het rapport gelezen en wil dit
   ontwerp live zetten** en klik op **Live zetten**.
6. **Publiceren.** Zet bij "Gegevens" de status op **Gepubliceerd** en klik op **Opslaan**. Vul
   eventueel een bron-code in en vink "Mag gevonden worden via Google" aan als dat de bedoeling
   is. Open de pagina via de link bovenaan en meld je voor de test niet echt aan.

---

## Het ontwerp maken

> **In het kort:** één HTML-bestand, met `[[formulier]]` op de plek van het formulier.
> Afbeeldingen upload je los en je verwijst ernaar met alleen de bestandsnaam.

Eén `.html`-bestand, met de opmaak en eventuele scripts erin. Dat is precies wat een export
uit Claude Design oplevert; die kun je rechtstreeks uploaden. Het scherm noemt de maximale
grootte (standaard 16 MB, of minder als de server lager staat).

**Zet `[[formulier]]` in het ontwerp** op de plek waar het aanmeldformulier moet komen.
Zonder die markering staat er geen formulier op de pagina; het wordt er niet vanzelf
onderaan bij geplakt, want dat zou het ontwerp verpesten.

Andere markeringen die je kunt gebruiken:

| Markering | Wordt |
|---|---|
| `[[formulier]]` | Het aanmeldformulier |
| `[[seizoen]]` | De naam van het seizoen, bijvoorbeeld "zomer 2027" |
| `[[selectiedag_datum]]` | De selectiedag, bijvoorbeeld "zaterdag 10 april 2027" |
| `[[trainingsweekend_datum]]` | Het trainingsweekend, bijvoorbeeld "vrijdag 30 april t/m zondag 2 mei 2027" (of één dag) |
| `[[werkperiode]]` | De werkperiode, bijvoorbeeld "1 juni t/m 31 augustus 2027" |
| `[[aanmelden_tot]]` | De sollicitatiedeadline, bijvoorbeeld "15 maart 2027" |
| `[[uurloon_vanaf]]` | Het laagste uurloon vanaf 16 jaar, bijvoorbeeld "€ 4,97" (zonder vakantietoeslag) |
| `[[salaristabel]]` | Een tabel met het uurloon per leeftijd (juni en juli–september), met een regel over de vakantietoeslag eronder |
| `[[vacature_schema]]` | Onzichtbaar: de vacature voor Google (zie [De vacature in Google](#de-vacature-in-google)). Zet hem in de `<head>` |
| `[[aanmeld_url]]` | Het adres van het formulier op deze pagina, handig voor een eigen knop ("Meld je aan") |
| `[[bron]]` | De bron-code van deze pagina |
| `[[pagina_url]]` | Het volledige adres van deze pagina, bijvoorbeeld `https://sollicitatie.paal13.com/p/zomer-2027`. Nodig voor `og:url`, `og:image` (`[[pagina_url]]/deel-foto.jpg`) en structured data: daar werkt een los bestandsnaam niet |

Een markering die je verkeerd typt, blijft gewoon zichtbaar op de pagina staan. Zie je
`[[formulir]]` staan, dan weet je meteen wat eraan scheelt.

### Datums en loon uit het seizoen

Zet datums en bedragen **nooit letterlijk** in het ontwerp, maar gebruik de markeringen hierboven.
Dan veroudert de pagina niet: volgend jaar staan er vanzelf de nieuwe datums en de nieuwe lonen.

- De datums komen uit **Seizoen** in het beheer: het seizoen waarvan de aanmelding open staat, of
  anders het nieuwste (het komende seizoen, zodra je dat hebt aangemaakt). De deadline vul je daar in
  bij **Aanmelden kan tot**. Dat veld sluit het formulier niet: dat blijft "Aanmelding sluiten".
- Het loon komt uit **Instellingen › Lonen & budget**, van het kalenderjaar van het seizoen. Het is
  het wettelijke minimum(jeugd)loon per uur, zonder vakantietoeslag.
- Is iets nog niet ingevuld, dan staat er "wordt nog bekendgemaakt".

De salaristabel begint bij 16 jaar. De hoogste leeftijden met hetzelfde loon worden één regel,
bijvoorbeeld "21 jaar en ouder". Voor de opmaak in het ontwerp (bijvoorbeeld in Claude Design):
de tabel heeft de klasse `p13-salaris`, met een `<caption>`, kolomkoppen en per rij de leeftijd
als rijkop; de regel eronder heeft de klasse `p13-salaris-noot`.

### Afbeeldingen

Upload afbeeldingen los bij de pagina (JPG, PNG, GIF of WebP, standaard maximaal 5 MB per stuk) en
verwijs ernaar met alleen de bestandsnaam:

```html
<img src="strand.jpg" alt="Het strand bij Paal 13">
```

Je kunt er meerdere tegelijk kiezen (maximaal 20 per keer); per bestand hoor je of het
gelukt is. Een afbeelding verwijderen kan met **Verwijderen** in de lijst. Verwijst de pagina
er nog naar, dan is dat daarna een kapotte afbeelding.

**Staat er een map voor de bestandsnaam?** Dat mag. Een ontwerp uit een ontwerptool
verwijst meestal naar zijn eigen mappen (`assets/strand.jpg`, `images/duinen.jpg`). Bij ons
staan alle afbeeldingen van een pagina bij elkaar in één map, dus telt alleen de
bestandsnaam. Bij het inlezen worden die verwijzingen automatisch rechtgezet, ook namen
met hoofdletters of spaties, want die worden bij het uploaden omgezet ("Strand Foto.JPG"
wordt `strand-foto.jpg`). In het rapport zie je precies wat er is aangepast, en in de lijst
onder het uploadveld staan de namen zoals ze werkelijk zijn opgeslagen.

Verwijst het ontwerp naar een afbeelding die er nog niet is, dan zegt het rapport welke
namen hij verwacht. Upload ze daarna onder die naam; opnieuw inlezen hoeft dan niet.

De extensie blijft altijd die van het bestand dat je uploadt. Het systeem kijkt wél naar wat
er echt in zit: een foto die `duinen.jpg` heet maar in werkelijkheid een PNG is (dat gebeurt
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
ingesloten afbeeldingen, met `<img src="strand.jpg">` en `background-image: url(duinen.jpg)`,
zonder `data:`-URI's en zonder verwijzingen naar andere websites. Die laatste worden
namelijk geblokkeerd (zie [Externe bronnen toestaan](#externe-bronnen-toestaan)).

---

## Het aanmeldformulier op de pagina

> **In het kort:** op de plek van `[[formulier]]` komt hetzelfde aanmeldformulier als op
> `aanmelden.php` en paal13.com, in drie stappen. Is het seizoen niet open, dan staat er dat de
> aanmelding gesloten is.

Het formulier heeft drie stappen: **Naam**, **Jij** (geboortedatum en, als iemand dat wil,
geslacht) en **Contact** (e-mailadres, telefoonnummer en het vinkje voor de privacyverklaring).
Wie 15 is, krijgt in stap 2 een blok voor de toestemming van een ouder of verzorger. Het formulier
is overal hetzelfde; je kunt het niet per pagina aanpassen.

Na het versturen ziet de bezoeker op dezelfde pagina de bevestiging "Je aanmelding is binnen".
Daarna volgt een mail met een link naar het portaal. De aanmelding komt in het beheer binnen
met de bron-code van de pagina (zie [Herkomst bijhouden](#herkomst-bijhouden)).

Staat het actieve seizoen niet op "open", dan staat er op de plek van het formulier "De
aanmelding is gesloten".

---

## Het rapport

> **In het kort:** na het inlezen krijg je een rapport over wat er in het ontwerp zit. Het ontwerp
> gaat pas live als je dat rapport bevestigt.

Het ontwerp wordt niet aangepast bij het uploaden; animaties en interactie blijven dus
werken. (De enige uitzondering is het losmaken van ingesloten afbeeldingen hierboven, en
dat staat achter een vinkje dat je kunt uitzetten.) Maar daardoor kan er ook van alles in
zitten wat je niet wilt. Daarom krijg je na het inlezen een rapport te zien, en gaat het ontwerp
pas live als je dat bevestigt.

Waar het rapport op let:

- **Let op**: scripts van andere websites, formulieren die naar een andere partij sturen,
  en bekende trackers zoals Google Analytics.
- **Aandacht**: scripts in het bestand zelf, externe lettertypen (Google Fonts stuurt het
  IP-adres van de bezoeker naar de VS), code die netwerkverkeer opzet, en cookies of
  browseropslag.
- **Klein punt**: afbeeldingen van andere websites, `onclick`-acties, `javascript:`-links.
- **Tip**: een ontbrekend `[[formulier]]`, taal, titel, hoofdkop of alt-teksten.

Een ontwerp dat je zelf hebt gemaakt en dat alles in het bestand zelf heeft staan, geeft
meestal helemaal geen bevindingen ("Er is niets bijzonders gevonden in dit ontwerp").

Onder het rapport staan **Eerst bekijken** (de nieuwe versie in een eigen tabblad, alleen voor
jou) en **Live zetten**. Live zetten kan pas als je het vinkje "Ik heb het rapport gelezen en wil
dit ontwerp live zetten" hebt gezet.

### Externe bronnen toestaan

Verwijst het ontwerp naar een andere website, bijvoorbeeld `cdn.tailwindcss.com` voor de
opmaak, dan wordt die **standaard geblokkeerd** door de browser. De pagina ziet er dan
anders uit dan bedoeld.

Bij het bevestigen van het rapport staat per host een vinkje om hem toe te staan. Vink
alleen aan wat je vertrouwt en wat het ontwerp echt nodig heeft. Dat wordt vastgelegd in
het logboek. Later aanpassen kan bij "Gegevens" in het veld **Toegestane externe bronnen**: één
hostnaam per regel.

Beter dan toestaan is meestal: het lettertype of de opmaak in het bestand zelf zetten. Dan
werkt de pagina ook als die andere website eruit ligt, en gaan er geen gegevens van
bezoekers naar derden.

Eén ding kun je nooit toestaan: script uit het ontwerp mag geen verzoeken doen naar andere
adressen dan het meetpunt van de pagina zelf. Dat beschermt de gegevens in het beheer.

---

## Versies

> **In het kort:** elke upload is een nieuwe versie. Je kunt elke versie bekijken en een oudere
> met één klik terugzetten. De laatste tien blijven bewaard.

Onder "Ontwerp" staat de lijst met versies. Per versie zie je de datum, de bestandsnaam, de
grootte en of er aandachtspunten in het rapport stonden.

- **Live** staat bij de versie die bezoekers zien.
- **Niet bevestigd** staat bij een versie waarvan het rapport nog niet is bevestigd. Met
  **Rapport** open je dat rapport.
- **Bekijken** opent een versie in een nieuw tabblad.
- **Terugzetten** zet een eerder bevestigde versie weer live, na een bevestiging. De huidige
  versie blijft bewaard.

De laatste tien versies blijven bewaard; de versie die live staat altijd. Een net geüploade
versie staat nog niet live: eerst het rapport bevestigen.

Rechts op het scherm staat een **Voorbeeld** van de versie die live staat.

---

## Status en vindbaarheid

> **In het kort:** alleen "Gepubliceerd" is voor iedereen te zien. Standaard is een pagina niet
> vindbaar via Google.

| Status | Wat bezoekers zien |
|---|---|
| Concept | Een 404 (pagina niet gevonden), behalve voor ingelogde beheerders met het recht `paginas` |
| Alleen beheerders | Hetzelfde; handig om iets voor te leggen aan het bestuur |
| Gepubliceerd | De pagina, voor iedereen |

Pas bij **Gepubliceerd** tellen bezoeken mee in de cijfers. Kijk je zelf als beheerder naar een
pagina die nog niet openbaar is, of naar een versie die nog niet is bevestigd, dan zie je bovenaan
een balk en telt je bezoek niet mee.

Standaard staat een pagina op "niet vindbaar via Google". Wil je dat wel, vink dan bij "Gegevens"
**Mag gevonden worden via Google** aan. Dan gebeurt er automatisch nog meer:

- de pagina krijgt een **canonical**-adres (`/p/<adres>`, zonder `?bron=` of andere toevoegingen),
  tenzij het ontwerp er zelf een heeft;
- de pagina komt in **`/sitemap.xml`**, waar Google hem vindt, en `/robots.txt` verwijst daarnaar.
  Meld de sitemap één keer aan in Google Search Console.

**Startpagina van het domein.** Vink bij "Gegevens" **Startpagina van sollicitatie.paal13.com** aan
bij de belangrijkste wervingspagina. Wie dan naar `https://sollicitatie.paal13.com/` gaat en niet is
ingelogd, komt op die pagina uit; ingelogde sollicitanten en beheerders gaan naar hun eigen
omgeving. Dat is goed voor Google (de voorpagina is het sterkste adres) en voor bezoekers die het
adres intypen. Zet op die pagina wel een link "Al gesolliciteerd? Log in" naar `/portaal/`. Er is
maar één startpagina; staat de pagina niet op Gepubliceerd, dan gaat de voorpagina gewoon naar het
inloggen.

Op de testomgeving is een pagina nooit vindbaar, ook niet met het vinkje aan: anders concurreert
test met de echte site in de zoekresultaten.

### De vacature in Google

Zet `[[vacature_schema]]` in de `<head>` van het ontwerp. Daar komt dan een vacature voor Google
Jobs ("Lifeguard (strandwacht)" in Callantsoog), met de werkperiode, de selectiedag, het
trainingsweekend en het loon per leeftijd uit het seizoen. Je ziet er op de pagina zelf niets van.

De vacature staat er alleen als:

- de pagina **Gepubliceerd** is en **Mag gevonden worden via Google** aan staat (dus nooit op de
  testomgeving);
- de aanmelding van het seizoen **open** staat;
- de deadline (**Aanmelden kan tot**) nog niet voorbij is. Zonder deadline geldt de selectiedag.

Daarna is de markering leeg en **verloopt de vacature vanzelf** in Google; je hoeft niets weg te
halen. Opent de aanmelding volgend jaar weer, dan staat hij er vanzelf opnieuw, met de nieuwe
gegevens. Controleer een gepubliceerde pagina één keer met de test voor rich results van Google.

In het overzicht staat **Geen ontwerp** bij een pagina waarop nog geen versie live staat.

---

## Cijfers: waar haken mensen af?

> **In het kort:** per pagina zie je bezoekers, bezoeken, aanmeldingen en omzetting, en een
> trechter die laat zien bij welke stap van het formulier mensen stoppen.

Bovenaan een pagina, en per pagina in het overzicht, staan vier cijfers:

- **Unieke bezoekers** en **Bezoeken** (dezelfde bezoeker telt hooguit eens per 30 minuten als
  bezoek);
- **Aanmeldingen**: verstuurde formulieren;
- **Omzetting**: aanmeldingen gedeeld door unieke bezoekers.

Daaronder staat **Waar haken mensen af?**. Die trechter heeft deze treden:

1. Pagina bekeken
2. Stap 1 · Naam (begonnen met invullen)
3. Stap 2 · Jij
4. Stap 3 · Contact
5. Aanmelding verstuurd

Bij elke trede staat het aantal en het percentage van iedereen die aan het formulier begon. Waar
mensen afvallen, staat "… gingen hier niet verder".

Los daaronder staat **Halverwege weggegaan**, per stap. Dat is geen telling maar een aanwijzing.
Het signaal komt van een tabblad dat naar de achtergrond gaat, en dat gebeurt ook als iemand even
iets opzoekt en daarna gewoon doorgaat. Gebruik het om te zien bij welke stap het vaakst gebeurt,
niet als aantal afhakers.

Is er nog niets gemeten, dan staat er dat de cijfers verschijnen zodra de pagina gepubliceerd is
en er bezoekers komen.

---

## Herkomst bijhouden

> **In het kort:** elke pagina heeft een bron-code die meereist tot in het dossier van de
> sollicitant. Onderaan het overzicht zie je per bron hoeveel aanmeldingen er kwamen en hoeveel
> daarvan zijn aangenomen.

Elke pagina heeft een **bron-code**. Je stelt hem in bij "Gegevens"; laat je het veld leeg, dan
wordt het adres van de pagina gebruikt. De code reist mee tot in het dossier van de sollicitant,
dus je ziet later precies welke pagina welke aanmeldingen heeft opgeleverd, en hoeveel daarvan
uiteindelijk zijn aangenomen.

Dat overzicht staat onderaan het overzicht van wervingspagina's, onder **Waar komen aanmeldingen
vandaan?**: per bron het aantal aanmeldingen, het aantal aangenomen en het aandeel. Kwamen er
aanmeldingen binnen via een campagnelink, dan staan de campagnes eronder, elk met hun eigen
aantallen, plus een regel "zonder campagne" voor de rest.

### Campagnes en QR-codes

Wil je weten welke poster, flyer of advertentie iets oplevert, gebruik dan **dezelfde pagina** met
een campagne erachter: `https://sollicitatie.paal13.com/p/zomer-2027?bron=poster-csg-2027`.
Een aanmelding via die link telt gewoon mee voor de pagina (de cijfers en de trechter van de
pagina blijven kloppen), en in het overzicht zie je hem terug onder die campagne. Werkt een
advertentieplatform met `utm_campaign` of `utm_source`, dan wordt die gebruikt als er geen
`?bron=` is.

Een campagnenaam bestaat uit kleine letters, cijfers en koppeltekens, maximaal 40 tekens. Andere
tekens worden vanzelf een koppelteken ("Poster CSG 2027" wordt `poster-csg-2027`).

Je hoeft de link niet zelf te maken. Op het scherm van een pagina staat het blok **Delen en
meten**: vul een campagne in en klik op **Link maken**. Je krijgt de volledige link (met
**Kopiëren**) en een QR-code om te downloaden: **SVG** voor drukwerk (blijft scherp op elk formaat)
en, als de server het kan, een **PNG**. De QR-code wordt op onze eigen server gemaakt; er gaat niets
naar een andere dienst. Scan hem altijd even met je telefoon voordat je iets laat drukken, en wijzig
daarna het adres van de pagina niet meer.

Het canonical-adres voor Google blijft altijd zonder `?bron=`, dus campagnelinks leveren geen
dubbele pagina's op in de zoekresultaten.

Gebruik je het gewone aanmeldformulier voor meerdere advertenties, dan kun je de bron ook daar in
het adres meegeven: `https://sollicitatie.paal13.com/aanmelden.php?bron=instagram-mei`.

Het veld **Doelgroep** bij "Gegevens" is alleen voor jezelf; het staat in het overzicht achter het
adres.

---

## Wat we van bezoekers bijhouden

> **In het kort:** zo min mogelijk. Geen cookies, geen sessie, geen cookiemelding nodig. Alleen
> tellingen, met een code die elke dag verandert.

De pagina zet **geen cookies** en start geen sessie, dus er is geen cookiemelding nodig.

Om unieke bezoekers te kunnen tellen wordt een versleutelde code bewaard die elke dag
verandert en niet naar een persoon te herleiden is. Die code wordt na 30 dagen gewist; de
aantallen blijven. De cijfers zelf verdwijnen na 13 maanden. Beide termijnen staan bij
**Instellingen › Bewaren & opschonen** ("Herkenning unieke bezoekers" en "Bezoekersstatistiek
wervingspagina's"). Het wissen gebeurt alleen als daar "Automatisch opschonen inschakelen" aan
staat.

Dezelfde bezoeker telt hooguit **eens per 30 minuten** als bezoek op dezelfde pagina.
Vernieuwen, de terug-knop en een tabblad dat door de browser in de slaapstand is gezet en
daarna opnieuw laadt, tellen dus niet opnieuw mee. Bekende zoekmachines en andere robots tellen
niet mee.

Naast het bezoek houden we bij hoever iemand in het formulier kwam: begonnen met invullen,
stap 2 bereikt, stap 3 bereikt, verstuurd. Daarvan staat alleen het stapnummer geregistreerd,
nooit iets wat iemand heeft ingetypt.

Wil je helemaal niets meten, zet dan onder **Instellingen › Bewaren & opschonen** het vinkje
**Bezoekersstatistiek op de wervingspagina's en het aanmeldformulier** uit (standaard staat het
aan). Dan schrijft het systeem geen enkele gebeurtenis meer weg en verdwijnt ook het meetadres uit
de pagina. Wat al geteld is, blijft staan volgens de termijnen hierboven.

Let op: zet je ontwerp zelf een cookie of een tracker, dan gelden de gewone regels wél en is
de brigade daarvoor verantwoordelijk. Het rapport waarschuwt daarvoor, en de browser
blokkeert het tot je het toestaat.

---

## Het adres wijzigen

> **In het kort:** kan, maar bestaande links, QR-codes en advertenties werken daarna niet meer.

Wijzig je bij "Gegevens" het adres van een pagina, dan verhuizen de afbeeldingen automatisch mee,
maar **bestaande links, QR-codes en advertenties werken dan niet meer**. Kies het adres dus
meteen goed.

Oude adressen uit de eerste versie van deze module (`/l/<naam>`) sturen automatisch door naar
`/p/<naam>`.

---

## Een pagina verwijderen

> **In het kort:** onderaan een pagina staat "Pagina verwijderen". De pagina, alle versies en de
> afbeeldingen verdwijnen; aanmeldingen en de anonieme cijfers blijven.

Onderaan het scherm van een pagina staat het blok **Verwijderen** (alleen als je het recht hebt om
pagina's te verwijderen). Na een bevestiging verdwijnt de pagina van het web, samen met alle
versies en geüploade bestanden. Aanmeldingen die er ooit via binnenkwamen blijven staan, en de
statistiek blijft anoniem bewaard. Dit is niet terug te draaien.

Wil je een pagina alleen tijdelijk offline halen, zet de status dan terug op **Concept**.

---

## Als de pagina niet laadt

> **In het kort:** controleer de status, of er een bevestigde versie live staat, en of er externe
> bronnen worden geblokkeerd.

- **404 terwijl de pagina bestaat**: staat de status op Gepubliceerd? En staat er een
  bevestigde versie live (niet "Geen ontwerp")? Werkt `/p/<naam>` niet maar `/p.php?s=<naam>`
  wel, dan ontbreekt de rewrite-regel in `.htaccess`; het overzichtsscherm laat dan zien welke
  regels erin moeten.
- **De pagina ziet er kaal uit**: waarschijnlijk wordt een externe bron geblokkeerd. Kijk
  in het rapport van de versie welke hosts erin zitten en sta ze toe, of zet de opmaak in
  het bestand zelf.
- **Het formulier staat er niet**: `[[formulier]]` ontbreekt in het ontwerp, of het seizoen
  staat niet op "open". In dat laatste geval verschijnt er een melding dat de aanmelding
  gesloten is.
- **Het bestand is te groot**: het scherm zegt hoeveel de server per upload aanneemt. Laat de
  afbeeldingen losmaken, of maak het bestand kleiner.

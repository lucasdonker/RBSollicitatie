=== Paal 13 — Aanmelden ===
Contributors: reddingsbrigadepaal13
Tags: aanmelden, formulier, reddingsbrigade
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 2.98.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Het aanmeldformulier en de seizoensgegevens van Reddingsbrigade Paal 13 op je eigen website.

== Description ==

Deze plugin haalt het aanmeldformulier en de seizoensgegevens op bij het sollicitatiesysteem
van Reddingsbrigade Paal 13. Het formulier staat gewoon in je pagina — geen venster-in-een-
venster — en werkt met toetsenbord en voorleessoftware.

Verandert het bestuur een datum of een locatie in het beheer, dan verandert de website mee.
Niemand hoeft datums met de hand over te typen.

Het formulier vraagt in drie korte stappen om naam, geboortedatum en contactgegevens. Is
iemand nog geen 16, dan verschijnt vanzelf het blok voor toestemming van een ouder — dat is
de leeftijdsgrens uit de AVG voor het verwerken van gegevens.

**Shortcodes**

* `[paal13_aanmeldformulier]` — het formulier
* `[paal13_seizoen veld="datum_selectiedag"]` — één seizoenswaarde
* `[paal13_open]…[/paal13_open]` — alleen tonen als de aanmelding openstaat
* `[paal13_gesloten]…[/paal13_gesloten]` — alleen tonen als hij dicht is
* `[paal13_aanmeldknop url="/aanmelden/"]` — een knop naar de aanmeldpagina

De volledige lijst met seizoensvelden staat bij Instellingen › Paal 13 Aanmelden, met de
waarde die er op dit moment uit komt.

== Installation ==

1. Haal in het sollicitatiesysteem een API-sleutel op bij Instellingen › Website & API.
2. Upload de plugin bij Plugins › Nieuwe plugin › Plugin uploaden en activeer hem.
3. Vul bij Instellingen › Paal 13 Aanmelden het adres en de sleutel in.
4. Klik op "Verbinding testen".
5. Zet `[paal13_aanmeldformulier]` op een pagina.

== Frequently Asked Questions ==

= Waar komen de aanmeldingen terecht? =

In het sollicitatiesysteem op sollicitatie.paal13.com. In WordPress worden geen gegevens van
sollicitanten opgeslagen — alleen de instellingen van deze plugin.

= Het formulier zegt dat de pagina te lang openstond =

Dan wordt de pagina waarschijnlijk door een cache-plugin bewaard. De plugin geeft zelf al
aan dat dat niet mag (DONOTCACHEPAGE); sluit de pagina anders met de hand uit van de cache.

= De aanmelding is gesloten, maar ik wil de pagina alvast klaarzetten =

Dat kan. Zolang het seizoen in het beheer op "gesloten" staat, laat het formulier een klein
seintje-formulier zien: bezoekers laten voornaam en e-mailadres achter en krijgen één bericht
als de aanmelding opengaat. Dat formulier post rechtstreeks naar het sollicitatiesysteem
(/seintje.php), niet via WordPress; de plugin hoeft er niets voor te doen.

= Kan ik het formulier zelf opmaken? =

Ja. Vink bij de instellingen "Onze opmaak niet laden" aan en style de klassen die met `af-`
beginnen zelf. Let dan wel op de zichtbare toetsenbordrand en op invoervelden van minstens
44 bij 44 pixels.

== Changelog ==

= 2.98.0 =
* Eerste versie van de herbouwde plugin.
* Het formulier komt uit het sollicitatiesysteem zelf, dus er is nog maar één versie van.
* Alle seizoenswaarden zijn als shortcode beschikbaar, met Nederlandse datumopmaak.
* De aanmelding gaat via de server, zodat de API-sleutel niet in de pagina terechtkomt.
* Het adres van de bezoeker gaat mee naar het sollicitatiesysteem, zodat de
  privacytoestemming het juiste adres vastlegt en de limiet per bezoeker telt.
* Ligt de API er even uit, dan blijven de seizoensgegevens staan die het laatst goed waren.

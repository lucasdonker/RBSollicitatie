# WordPress-plugin — Paal 13 Aanmelden

Written for: wie de website paal13.com beheert.

Deze plugin zet het aanmeldformulier en de seizoensgegevens van het sollicitatiesysteem op
de WordPress-site. De plugin bevat zelf geen aanmeldlogica en geen eigen kopie van het
formulier: alles komt van `sollicitatie.paal13.com`. Verandert daar een veld of een datum,
dan staat dat hier binnen een paar minuten ook — zonder dat er iets bijgewerkt hoeft te worden.

## Installeren

1. Zet in het sollicitatiesysteem een API-sleutel klaar: **Instellingen › Website & API**.
2. Maak de ZIP:
   ```powershell
   cd wordpress-plugin
   .\bouw-zip.ps1
   ```
   Er komt een bestand `paal13-aanmelden-<versie>.zip` naast te staan.
3. In WordPress: **Plugins › Nieuwe plugin › Plugin uploaden**, kies de ZIP, activeren.
4. Ga naar **Instellingen › Paal 13 Aanmelden**, vul het adres en de sleutel in en klik op
   **Verbinding testen**. Staat er "De verbinding werkt", dan ben je klaar.
5. Maak een pagina en zet daar `[paal13_aanmeldformulier]` op.

Staat er al een oudere Paal 13-plugin op de site? Deactiveer en verwijder die eerst; twee
plugins die hetzelfde formulier plaatsen gaan elkaar in de weg zitten.

## Shortcodes

| Shortcode | Wat het doet |
|---|---|
| `[paal13_aanmeldformulier]` | Het aanmeldformulier. Extra: `bron="flyer-2027"`, `titel="…"`, `knop="…"` |
| `[paal13_seizoen veld="datum_selectiedag"]` | Eén seizoenswaarde. Extra: `formaat="lang\|kort\|dag\|dagnaam\|maand\|jaar\|ruw"` en `leeg="nog niet bekend"` |
| `[paal13_open]…[/paal13_open]` | Alleen tonen als de aanmelding openstaat |
| `[paal13_gesloten]…[/paal13_gesloten]` | Alleen tonen als de aanmelding dicht is |
| `[paal13_aanmeldknop url="/aanmelden/"]` | Een knop naar de pagina met het formulier |

In gewone tekst (een kop, een knoplabel) werkt ook `{{seizoen:datum_selectiedag}}` en
`{{seizoen:datum_selectiedag|dag}}`.

De volledige lijst met velden staat op de instellingenpagina, mét de waarde die er op dit
moment uit komt. Dat is de plek om te kijken, niet dit bestand: de lijst groeit mee met wat
het sollicitatiesysteem over een seizoen weet.

## Waar `bron` voor is

Alles wat via de plugin binnenkomt krijgt een herkomst mee. In het beheer van het
sollicitatiesysteem staat bij **Landingspagina's** een overzicht per bron: hoeveel
aanmeldingen, en hoeveel daarvan uiteindelijk zijn aangenomen. Zet je op een flyer een
andere pagina met `bron="flyer-2027"`, dan zie je precies wat die flyer opleverde.

## Twee dingen om te weten

**De pagina met het formulier mag niet gecachet worden.** In het formulier zit een nonce en
een tijdstempel die per bezoeker verschillen. De plugin zet `DONOTCACHEPAGE`, en alle bekende
cache-plugins luisteren daarnaar. Gebruik je iets exotisch, sluit die pagina dan met de hand
uit van de cache.

**De sleutel staat op de server, niet in de pagina.** De bezoeker post naar WordPress, en
WordPress praat met de API. Kom je ooit in de verleiding het formulier rechtstreeks naar
`sollicitatie.paal13.com` te laten posten: dan moet de sleutel in de broncode van de pagina
staan, en is het geen sleutel meer.

## Bestanden

```
paal13-aanmelden.php          plugin-header, laadt de rest
includes/class-p13-api.php    praten met de API: adres, sleutel, cache, foutafhandeling
includes/class-p13-seizoen.php de seizoenswaarden en de Nederlandse opmaak ervan
includes/class-p13-formulier.php het formulier tonen en een inzending verwerken
includes/class-p13-shortcodes.php de shortcodes
includes/class-p13-admin.php  de instellingenpagina
assets/                       leeg in Git; bouw-zip.ps1 vult hem (zie LEESMIJ.txt daar)
```

## Testen zonder WordPress

```powershell
cd docs\ai\harnas
php wpplugin.php
```

Dat zet de WordPress-functies na die de plugin gebruikt en controleert de datumopmaak, de
shortcodes, de botcontrole en wat er gebeurt als de API eruit ligt.

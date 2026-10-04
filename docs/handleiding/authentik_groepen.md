# Authentik-groepen: wie is wie, wie mag wat

> **In het kort:** Authentik bevat alleen de *huidige staat* die een app nodig heeft om te beslissen.
> Het Verenigingsportaal is de ledenadministratie en zet de groepen. Er zijn vijf soorten groepen,
> elk met één betekenis. Een app leest alleen zijn eigen `app-`-groepen (plus wat hij als gegeven
> nodig heeft). Besluit Lucas, 30 sep 2026.
>
> Voor: de bouwer van het Verenigingsportaal, wie Authentik beheert, en wie aan deze app werkt.

## Uitgangspunten

1. **Authentik is geen ledenadministratie.** Lidsoort (jeugd, senior, slapend, erelid, donateur),
   contributie, geboortedatum en historie per seizoen staan in het Verenigingsportaal. In Authentik
   komt alleen wat een app nodig heeft om toegang of een beslissing op te baseren.
2. **Elke groep betekent één ding.** Status, functie, kwalificatie en toegang lopen niet door elkaar.
3. **Toegang is expliciet.** Een app kijkt naar `app-<app>-<rol>`, nooit naar een functie of diploma.
   Het Verenigingsportaal leidt de `app-`-groepen af uit de functie (koppelregels hieronder).
4. **Geen overerving via parent-groepen.** De `groups`-claim bevat standaard alleen directe
   groepen; wie op overerving leunt, gaat stil onderuit.
5. **Alles met een functie of inzet heeft een einddatum.** Offboarding gebeurt dan vanzelf: de app
   weigert iemand die niet meer in een gekoppelde groep zit.
6. **Dataminimalisatie (AVG art. 5 lid 1 sub c).** Elke app krijgt via een eigen scope mapping
   alleen de groepen die hij nodig heeft.

## De vijf soorten

| Prefix | Betekenis | Wie zet het | Looptijd |
|---|---|---|---|
| `lid-` | Status in de vereniging | Verenigingsportaal | Zolang de status geldt |
| `fn-` | Functie / aanstelling: wat je nú doet | Verenigingsportaal | Altijd met einddatum |
| `kw-` | Kwalificatie: wat je kúnt (diploma, bevoegdheid) | Verenigingsportaal | Tot het diploma verloopt |
| `app-<app>-<rol>` | Toegang tot een app, met de rol daarin | Verenigingsportaal (afgeleid) of handmatig | Zolang de functie loopt |
| (ingebouwd) | Beheer van Authentik zelf | Handmatig | — |

Namen: kleine letters, woorden gescheiden door `-`. Een weergavenaam hoort in het
Verenigingsportaal, niet in de groepsnaam.

### `lid-` — status (drie assen, meerdere tegelijk mogelijk)

| Groep | Betekenis |
|---|---|
| `lid-vereniging` | Lid van de vereniging, actief of slapend |
| `lid-actief` | Doet nu iets voor de vereniging; krijgt de verenigingsapps (Nextcloud, ELO). Zonder deze groep = slapend |
| `lid-strandploeg` | Betaalde ploeg van het lopende zomerseizoen. Einddatum = einde seizoen |
| `lid-alarmploeg` | 24/7 inzetbaar voor de vereniging |
| `lid-oud-strandploeg` | Zat in de strandploeg in een van de **afgelopen 3 seizoenen** (niet dit seizoen). Het Verenigingsportaal houdt dit bij uit de historie |

Voorbeelden: een slapend lid heeft alleen `lid-vereniging`. Een oud-strandploeglid dat nog actief is:
`lid-vereniging` + `lid-actief` + `lid-oud-strandploeg`.

**Jeugdlid staat niet in Authentik.** Deze app rekent de leeftijd zelf uit (grenzen 15/16 en 18).
Heeft een app het later echt nodig, dan komt er `lid-jeugd`, en die gaat alleen naar díe app.

**Opgezegd:** het Verenigingsportaal zet het account op inactief (niet direct verwijderen, zodat
logboeken kloppen) en verwijdert het na de bewaartermijn.

**Onder de 16:** een Authentik-account voor een jeugdlid vraagt toestemming van een ouder of
verzorger (AVG art. 8, UAVG art. 5). Dat hoort in het inschrijfproces van het Verenigingsportaal.

### `fn-` — functie

| Groep | Was |
|---|---|
| `fn-bestuur` (alle bestuursleden) | `sys-bestuurslid` |
| `fn-voorzitter` | `sys-voorzitter` |
| `fn-secretaris` | `sys-secretaris` |
| `fn-penningmeester` | `sys-penningmeester` |
| `fn-systeembeheer` | `sys-systeembeheerder` |
| `fn-postcommandant` (dit seizoen aangesteld) | `Huidige Postcommandant` |
| `fn-planner` | `Planner` |

### `kw-` — kwalificatie

Naam: `kw-<categorie>-<niveau>`.

| Groep | Was |
|---|---|
| `kw-lifeguard-aspirant` | `Aspirant Lifeguard` |
| `kw-lifeguard-junior` | `Junior Lifeguard` |
| `kw-lifeguard` | `Lifeguard` |
| `kw-lifeguard-senior` | `Senior Lifeguard` |
| `kw-boot-opstapper` | `Opstapper Boot` |
| `kw-boot-schipper` | `Schipper` |
| `kw-auto-opstapper` | `Opstapper Auto` |
| `kw-auto-chauffeur` | `Chauffeur` |
| `kw-postcommandant` (bevoegd) | `Postcommandant` |
| `kw-bevelvoerder` | `sys-bevelvoerder` |

Een kwalificatie zegt niets over of iemand (recent) heeft gewerkt; zie "Terugkerende strandwacht".

### `app-` — toegang

Dit sollicitatiesysteem:

| Groep | Rol in de app | Automatisch voor |
|---|---|---|
| `app-sol-admin` | Beheerder: overal bij, behalve de *inhoud* van loondossiers | `fn-systeembeheer` |
| `app-sol-bestuur` | Werving, selectie, trainingsweekend, berichten, seizoen, instellingen | `fn-bestuur` |
| `app-sol-penningmeester` | Loondossiers (inhoud), loonplanning, sollicitanten lezen | `fn-penningmeester` |
| `app-sol-planner` | Beschikbaarheid en dekking, alleen lezen; geen lonen | `fn-planner` |
| `app-sol-begeleider` | Selectiedag en trainingsweekend meedraaien, geen persoonsgegevens | Handmatig per seizoen, einddatum na het trainingsweekend |

Iemand kan meerdere `app-sol-`-groepen hebben; de rechten tellen op. `app-sol-bekijker` vervalt.

De overige apps (`app-elo-*`, `app-nextcloud-media`, `app-website-*`) volgen hetzelfde patroon en
blijven zoals ze zijn.

### Ingebouwd

`authentik Admins` (noodingang voor Authentik zelf, **maximaal 2 personen**, met MFA) en
`authentik Read-only` blijven. `Superadmin`, `paal13-beheerder` en alle `sys-`-groepen vervallen.

## Koppelregels in het Verenigingsportaal

Eén tabel, functie → app-groepen. Het portaal zet en haalt de `app-`-groepen weg als de functie
begint of eindigt. Handmatige extra's (bijvoorbeeld een begeleider die geen bestuur is) mogen,
altijd met een einddatum.

| Als iemand heeft | Dan ook |
|---|---|
| `fn-bestuur` | `app-sol-bestuur` |
| `fn-penningmeester` | `app-sol-penningmeester` |
| `fn-systeembeheer` | `app-sol-admin` |
| `fn-planner` | `app-sol-planner` |

Einde seizoen: iedereen uit `lid-strandploeg` naar `lid-oud-strandploeg`; wie langer dan 3 seizoenen
niet in de strandploeg zat, uit `lid-oud-strandploeg`.

## Wat deze app met de groepen doet

- **Inloggen in het beheer:** je moet in een groep zitten die in Rollen & rechten aan een rol
  gekoppeld is. Alle gekoppelde rollen tellen op. Wie geen gekoppelde groep meer heeft, komt er niet
  in; de wachtwoord-noodingang blijft werken.
- **Loondossiers:** de beheerder ziet dat stukken er zijn, de status, en kan vernietigen. De
  *inhoud* (ID-kopie, bankpas, BSN, IBAN, loonheffingsformulier) opent alleen wie een rol heeft die
  het loondossier-recht echt heeft, dus de penningmeester. Noodgeval: de beheerder zet zichzelf in
  `app-sol-penningmeester`; dat staat dan in het logboek van Authentik én van deze app.
- **Terugkerende strandwacht:** `lid-strandploeg` of `lid-oud-strandploeg`. Een `kw-`-groep telt
  hiervoor bewust **niet** mee: er zijn mensen met een diploma die nooit of meer dan tien jaar
  geleden hebben gewerkt.
- **Competenties op de kaart van een sollicitant:** uit de `kw-`-groepen.
- **Portaal:** strandwachten loggen via een eigen Application in (zie onder). Daarvoor is geen
  `app-`-groep nodig.
- Deze app **schrijft niet** in Authentik. Groepen zetten is werk van het Verenigingsportaal; twee
  bronnen zouden elkaar overschrijven.

## Testomgeving

Eigen Applications en providers (ander redirect-adres, eigen secret). De rollen worden in de
testdatabase via Rollen & rechten gekoppeld aan eigen testgroepen:

`app-soltest-admin`, `app-soltest-bestuur`, `app-soltest-penningmeester`, `app-soltest-planner`,
`app-soltest-begeleider`

De code is gelijk aan productie; alleen de koppeling verschilt. Deze groepen zet je met de hand,
het Verenigingsportaal kent ze niet. Staan er op test echte (niet-geanonimiseerde) gegevens, dan
gelden daar dezelfde eisen als op productie: MFA en een kleine groep mensen.

## Afvinklijst Authentik

Per omgeving (productie, test):

- [ ] Groepen aangemaakt of hernoemd volgens de lijsten hierboven; vervallen groepen leeg en weg.
- [ ] Application **Sollicitatie – Beheer** met eigen OAuth2-provider (redirect
      `…/admin/auth_callback.php`). Bindings: de `app-sol-*`-groepen (op test `app-soltest-*`),
      policy engine mode **any**.
- [ ] Application **Sollicitatie – Portaal** met eigen provider (redirect
      `…/portaal/auth_callback.php`). Binding: `lid-vereniging`.
- [ ] In `config.php`: `OIDC_CLIENT_ID`/`OIDC_CLIENT_SECRET` van Beheer,
      `OIDC_PORTAAL_CLIENT_ID`/`OIDC_PORTAAL_CLIENT_SECRET` van Portaal.
- [ ] Per provider een eigen scope mapping voor `profile` in plaats van de standaard, die alleen de
      nodige groepen doorgeeft (voorbeeld hieronder).
- [ ] MFA verplicht in de login-flow voor iedereen met een `app-sol-*`-groep (authenticator
      validation stage, gebonden aan die groepen).
- [ ] In het beheer › Rollen & rechten: elke rol aan de juiste groep gekoppeld.
- [ ] Eén keer per seizoen: export van de `app-sol-*`-leden, gecontroleerd en afgetekend door de
      secretaris.

Voorbeeld scope mapping (scope `profile`) voor Sollicitatie – Beheer:

```python
nodig = ("app-sol-", "app-soltest-", "kw-", "lid-strandploeg", "lid-oud-strandploeg")
return {
    "name": request.user.name,
    "given_name": request.user.name,
    "preferred_username": request.user.username,
    "nickname": request.user.username,
    "groups": [g.name for g in request.user.ak_groups.all() if g.name.startswith(nodig)],
}
```

Controleer bij een Authentik-update of `request.user.ak_groups` nog zo heet. De app-kant leest
alleen `groups`, `email`, `given_name`, `family_name`, `preferred_username` en `sub`.

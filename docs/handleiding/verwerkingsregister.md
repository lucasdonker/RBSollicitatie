# Verwerkingsregister — Sollicitatiesysteem Paal 13

Versie 2.125 · september 2026

Dit is het register van verwerkingsactiviteiten (AVG artikel 30) voor het sollicitatiesysteem van
Reddingsbrigade Paal 13: het aanmeldformulier, het sollicitantenportaal en het beheer. Per
verwerking staat hier waarom we persoonsgegevens gebruiken, op welke grond, van wie, welke, wie ze
krijgt, hoe lang we ze bewaren en hoe we ze beveiligen. Het register staat in Git naast de code,
zodat het bestuur en een opvolger altijd de actuele versie hebben.

## Inhoud

1. [Over dit register](#over-dit-register)
2. [Overzicht](#overzicht)
3. [Hosting en vaste verwerkers](#hosting-en-vaste-verwerkers)
4. [Aanmelding en sollicitatie](#aanmelding-en-sollicitatie)
5. [Kennismakingsformulier en foto](#kennismakingsformulier-en-foto)
6. [Selectiedag en trainingsweekend](#selectiedag-en-trainingsweekend)
7. [Gezondheidsverklaring en intake](#gezondheidsverklaring-en-intake)
8. [Ondertekening en bewijslog](#ondertekening-en-bewijslog)
9. [Beschikbaarheid en loonplanning](#beschikbaarheid-en-loonplanning)
10. [Loondossier](#loondossier)
11. [Lidmaatschap](#lidmaatschap)
12. [Berichten](#berichten)
13. [AI-analyse](#ai-analyse)
14. [Landingspagina's en bezoekersstatistiek](#landingspaginas-en-bezoekersstatistiek)
15. [Beheerders en logboek](#beheerders-en-logboek)
16. [Productiekopie op de testomgeving](#productiekopie-op-de-testomgeving)
17. [Seintje bij de volgende werving](#seintje-bij-de-volgende-werving)
18. [Bijzonderheden](#bijzonderheden)
19. [Rechten van betrokkenen](#rechten-van-betrokkenen)
20. [Datalekken](#datalekken)
21. [Nog vast te leggen](#nog-vast-te-leggen)
22. [Wijzigingen](#wijzigingen)

---

## Over dit register

> **In het kort:** de vereniging is verwerkingsverantwoordelijke, het bestuur houdt dit register
> bij en de technisch beheerder controleert het tegen de code. Bijwerken bij elke nieuwe
> verwerking, nieuwe dienst of gewijzigde bewaartermijn. Alle termijnen hieronder gelden alleen als
> automatisch opschonen aan staat (`bewaar.actief`).

**Verwerkingsverantwoordelijke.** Reddingsbrigade Paal 13 (vereniging, Callantsoog en Groote
Keeten). Aanspreekpunt voor privacy is het bestuur, via het adres in de instelling
`mail.bestuur_email` (standaard `bestuur@paal13.com`). Er is geen functionaris
gegevensbescherming aangesteld; dat is voor de vereniging niet verplicht (AVG artikel 37).

**Wie het bijhoudt.**

| Rol | Taak |
|---|---|
| Bestuur | Eigenaar van dit register. Beslist over nieuwe verwerkingen en termijnen, sluit verwerkersovereenkomsten. |
| Technisch beheerder | Controleert bij elke release of het register nog klopt met de code en de instellingen, en past het aan in dezelfde commit als de wijziging. |
| Penningmeester | Leest mee voor het loondossier en vernietigt de loondocumenten na de bewaarplicht. |

**Wanneer bijwerken.** Bij elke nieuwe verwerking (een nieuw formulier, een nieuw soort gegeven),
bij elke nieuwe of andere externe dienst (een andere mail-, sms- of AI-dienst) en bij elke
gewijzigde bewaartermijn. Pas dan ook de privacyverklaring aan (`includes/privacy_verklaring.php`)
en verhoog `PRIVACY_VERSIE` in `includes/functions.php`. Noteer de wijziging onderaan in
[Wijzigingen](#wijzigingen).

**Bewaartermijnen en opschonen.** De termijnen staan in het beheer onder Instellingen › Bewaren &
opschonen. Per termijn staat hieronder de instellingssleutel en de standaardwaarde. De dagelijkse
opschoning (`includes/opschonen.php`) doet **niets** zolang `bewaar.actief` op 0 staat, en dat is
de standaard. Op productie moet deze schakelaar aan; anders blijven gegevens onbeperkt staan en
klopt de privacyverklaring niet.

**Verhouding tot de privacyverklaring.** De openbare verklaring (`/privacy.php`) is de versie voor
sollicitanten. Dit register is de interne, volledige versie. Ze horen hetzelfde te zeggen; de
verklaring leest dezelfde instellingen voor de termijnen.

## Overzicht

> **In het kort:** één regel per verwerking. De details staan in de hoofdstukken erna.

| Verwerking | Grondslag (AVG) | Bewaartermijn (standaard) |
|---|---|---|
| Aanmelding en sollicitatie | art. 6 lid 1 sub b | afgewezen of afgehaakt: 4 weken; onvoltooid: 3 maanden; aangenomen: 2 jaar na laatste seizoen |
| Kennismakingsformulier en foto | art. 6 lid 1 sub b | volgt het dossier |
| Selectiedag en trainingsweekend | art. 6 lid 1 sub f (beoordelen), sub b (organiseren) | volgt het dossier |
| Gezondheidsverklaring en intake | art. 9 lid 2 sub a; foto's: art. 6 lid 1 sub a | einde seizoen; bij afwijzing direct |
| Ondertekening en bewijslog | art. 6 lid 1 sub f en art. 32 | zolang het document bestaat |
| Beschikbaarheid en loonplanning | art. 6 lid 1 sub b | volgt het dossier; AI-invoer 3 maanden |
| Loondossier | art. 6 lid 1 sub c; bankpas sub b | 5 jaar na einde jaar van uitdienst (handmatig); bankpas 30 dagen na verwerking |
| Lidmaatschap | art. 6 lid 1 sub b | volgt het dossier; ledenadministratie daarbuiten |
| Berichten | art. 6 lid 1 sub b | overzicht 12 maanden, inhoud 3 maanden |
| AI-analyse | art. 6 lid 1 sub f; beschikbaarheidshulp sub b | analyse volgt het dossier; invoertekst 3 maanden |
| Landingspagina's en statistiek | art. 6 lid 1 sub f | pseudoniem 30 dagen, telling 13 maanden |
| Beheerders en logboek | art. 6 lid 1 sub f en art. 32 | logboek 12 maanden, fouten 90 dagen |
| Productiekopie op de testomgeving | art. 6 lid 1 sub f en art. 32 | echte gegevens alleen de minuten tussen importeren en anonimiseren; exportbestand direct weg |
| Seintje bij de volgende werving | art. 6 lid 1 sub a; oud-strandwachten sub f | maximaal 12 maanden |
| Seintje buiten het seizoen (nog niet gesolliciteerd) | art. 6 lid 1 sub a, met dubbele bevestiging | onbevestigd 7 dagen; na het bericht weg; maximaal 12 maanden |

## Hosting en vaste verwerkers

> **In het kort:** alles draait bij Cloud86 in Nederland. Een paar diensten zijn altijd betrokken
> (hosting, e-mail, inloggen via Authentik); andere alleen als ze aan staan. Voor elke externe
> verwerker hoort een verwerkersovereenkomst.

| Partij | Wat | Wanneer | Buiten de EER |
|---|---|---|---|
| Cloud86 (Plesk) | Webserver, database (MariaDB), opslag van documenten en back-ups | altijd | nee (Nederland) |
| Authentik | Inloggen voor beheerders (SSO, OIDC) met tweestapsverificatie | altijd | nee; eigen installatie van de vereniging |
| E-mailprovider | Verzenden van e-mail via de SMTP-server uit `smtp.host` | altijd | vastleggen welke partij en waar |
| WhatsReach en WhatsApp (Meta) | Berichten via WhatsApp | alleen als `wa.actief` = 1 | ja (Meta, VS); vestiging WhatsReach vastleggen |
| Sms-dienst | Berichten en verificatiecodes per sms, via `sms.gateway`: `messagebird` (Bird, Nederland), `easysendsms` (EasySendSMS), `eigen_telefoon` of `http_gateway` | alleen als `sms.actief` = 1 | afhankelijk van de gekozen gateway |
| Google (Gemini API) | AI-analyse van het kennismakingsformulier en de beschikbaarheidshulp | alleen als `ai.api_key` gevuld is | ja (VS) |
| Kadaster (PDOK) | Straat en woonplaats aanvullen uit postcode en huisnummer; alleen die twee gaan erheen | bij invullen adres, alleen voor ingelogde sollicitanten | nee |
| Website paal13.com (WordPress-plugin) | Toont het aanmeldformulier en stuurt de inzending door naar het systeem; bewaart zelf alleen een gehashte IP-teller van een uur tegen misbruik | als het formulier daar staat | nee, mits de WordPress-hosting in de EER staat |

Doorgifte naar de VS (Google, Meta) steunt op het EU-VS Data Privacy Framework, voor zover het
bedrijf daarbij is aangesloten, of op standaardcontractbepalingen.

## Aanmelding en sollicitatie

> **In het kort:** iemand meldt zich aan via het formulier (op deze site, een landingspagina of
> paal13.com) en volgt daarna de sollicitatie in het portaal. We verwerken dat om een
> arbeidsovereenkomst te kunnen sluiten, niet op basis van toestemming.

| Onderdeel | Inhoud |
|---|---|
| Doel | De sollicitatie behandelen: aanmelden, contact houden, uitnodigen, besluiten, en de sollicitant toegang geven tot het portaal. |
| Grondslag | Art. 6 lid 1 sub b AVG: nodig om op verzoek van de sollicitant een arbeidsovereenkomst te kunnen sluiten. Het vinkje bij de privacyverklaring betekent "gelezen", geen toestemming. De ouder van een 15-jarige geeft bij het aanmelden toestemming (zie [Bijzonderheden](#bijzonderheden)). |
| Betrokkenen | Sollicitanten (vanaf 15 jaar); ouders of verzorgers van sollicitanten jonger dan 18. |
| Persoonsgegevens | Naam, geboortedatum, e-mailadres, telefoonnummer, geslacht (optioneel, alleen voor de slaapindeling in het trainingsweekend), bron van de aanmelding (`bron_code`, `bron_label`) en sinds 2.145 de UTM-herkomst van de advertentielink waarmee iemand binnenkwam (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`: bron, medium, campagne en variant; geschoond tot a-z, 0-9 en koppeltekens, maximaal 40 tekens; geen cookie, geen IP-adres), moment, versie en IP-adres van het lezen van de privacyverklaring (`privacy_ip`), bij een 15-jarige naam en moment van ouderlijke toestemming, bij jonger dan 18 naam, e-mail en telefoon van de ouder. Fase en geschiedenis van de sollicitatie, notities van het bestuur (`sollicitant_notities`), taken in het portaal. Portaalgebruik: inlogmomenten, "blijf ingelogd" met browser en IP per apparaat (`portaal_apparaten`). |
| Verzoek om in te trekken (2.120) | Vanuit Mijn gegevens: reden (optioneel), of iemand een seintje wil, status en wie besliste (`intrek_verzoek`). Er verandert niets vanzelf; het bestuur belt en beslist. Het seintje-vinkje staat niet vooraf aan (toestemming, zie [Seintje](#seintje-bij-de-volgende-werving), bron `intrekken`). Gaat mee in inzage en wissen. |
| Ontvangers en verwerkers | Bestuur (module `sollicitanten`), penningmeester (alleen lezen). Cloud86, e-mailprovider, eventueel WhatsReach of sms-dienst, WordPress-site paal13.com als doorgeefluik. |
| Doorgifte buiten de EER | Nee, behalve bij berichten via WhatsApp (zie [Berichten](#berichten)). |
| Bewaartermijn | Afgewezen of afgehaakt: anonimiseren 4 weken na het besluit (`bewaar.afgewezen_weken`, standaard 4); met een seintje maximaal 12 maanden (zie [Seintje bij de volgende werving](#seintje-bij-de-volgende-werving)). Aanmelding niet afgemaakt (fase aangemeld, geen formulier, geen beschikbaarheid): verwijderen na 3 maanden (`bewaar.onvoltooid_mnd`, standaard 3). Aangenomen: zolang iemand bij ons werkt en daarna 2 jaar na het laatste seizoen (`bewaar.aangenomen_jaar`, standaard 2); dan wordt het dossier leeggemaakt en de persoon geanonimiseerd zodra er geen loondocumenten meer zijn. "Blijf ingelogd": 60 dagen (vast), en weg bij wissen of anonimiseren. Wat er bij anonimiseren verdwijnt, staat op één plek: `avg_sollicitatie_leegmaken()` in `includes/avg_wissen.php` (formulieren, beschikbaarheid, selectiedag, trainingsweekend, notities, AI-gegevens, apparaten, bewijsspoor van ondertekeningen). De sollicitatierij zelf blijft zonder persoonsgegevens staan voor de statistiek, net als de loonplanning (zonder notitie). De bron en de UTM-herkomst volgen dezelfde termijn: ze staan bij de sollicitatie en blijven na anonimiseren alleen bij die anonieme rij, voor de statistiek per wervingskanaal. |
| Beveiliging | Https, CSRF-bescherming en snelheidsbegrenzing op formulieren en API, inloggen in het portaal met een eenmalige code of inloglink (bevestigingsklik, geen inloggen op een GET), roulerend "blijf ingelogd"-token dat bij hergebruik alle apparaten intrekt, rechten per module voor beheerders, logboek van handelingen. |

## Kennismakingsformulier en foto

> **In het kort:** het kennismakingsformulier met adres, opleiding, ervaring, motivatie en
> een foto. De foto staat buiten het webbereik en is alleen te zien met de juiste rechten.

| Onderdeel | Inhoud |
|---|---|
| Doel | De sollicitant leren kennen om te beslissen over een uitnodiging voor de selectiedag, en de foto om sollicitanten op de selectiedag te herkennen. |
| Grondslag | Art. 6 lid 1 sub b AVG. |
| Betrokkenen | Sollicitanten. |
| Persoonsgegevens | Adres (straat, huisnummer, postcode, woonplaats), opleiding, werkervaring, diploma's en certificaten, motivatie, beschikbaarheidsintentie, bijzonderheden (vrije tekst; kan gezondheidsinformatie bevatten), foto, wijzigingen met reden (`blk_wijzigingen`). Tabel `blk_formulieren`. |
| Ontvangers en verwerkers | Bestuur (module `blk`). Begeleiders zien de bijzonderheden niet, alleen een neutrale "let op"-vlag. Foto: iedereen met leesrecht op `sollicitanten`, `blk` of `selectiedag`. Kadaster (PDOK) voor het aanvullen van het adres. Google (Gemini) voor de AI-analyse, zie [AI-analyse](#ai-analyse); de bijzonderheden gaan daar niet heen. |
| Doorgifte buiten de EER | Alleen de antwoorden die naar de AI-dienst gaan (zonder naam, adres, contactgegevens, foto en bijzonderheden). |
| Bewaartermijn | Volgt het dossier (zie [Aanmelding en sollicitatie](#aanmelding-en-sollicitatie)). Bij anonimiseren of een verwijderverzoek gaan het formulier, de wijzigingen (`blk_wijzigingen`) en het fotobestand weg. |
| Beveiliging | Foto in een map buiten het webbereik (`documenten_map()/fotos`), alleen op te vragen via `foto.php` met een rechtencontrole; willekeurige bestandsnaam; alleen jpg, png of webp tot 5 MB, gecontroleerd op inhoud. |

## Selectiedag en trainingsweekend

> **In het kort:** groepsindeling, aanwezigheid, cijfers, observaties en besluiten. Beoordelen doen
> we op grond van gerechtvaardigd belang: goede strandwachten kiezen voor de veiligheid op het strand.

| Onderdeel | Inhoud |
|---|---|
| Doel | De selectiedag en het trainingsweekend organiseren en sollicitanten beoordelen om te besluiten over aannemen. |
| Grondslag | Organiseren: art. 6 lid 1 sub b AVG. Beoordelen, observaties en besluit: art. 6 lid 1 sub f (gerechtvaardigd belang: veilige bezetting van het strand). Een mens beslist altijd. |
| Betrokkenen | Sollicitanten die zijn uitgenodigd; beoordelaars (hun naam staat bij hun cijfers en observaties); strandwachten van wie het bestuur feedback vastlegt (hun naam staat bij die feedback, niet in de inzage voor de sollicitant). |
| Persoonsgegevens | Groepsindeling en "houd uit elkaar"-relaties (`selectie_indeling`, `selectie_relatie`), aanwezigheid en incheckmoment, cijfers per test en criterium per beoordelaar (`selectie_score`), vrije observaties (`selectie_observatie`, `tw_observatie`), besluit met notitie en belnotitie (`selectie_besluit`), deelname, afmeldreden en besluit trainingsweekend (`tw_deelname`). Per evenement de bevestiging "ik kom / ik kan niet" met reden en bron (portaal, bellijst of bestuur). Van het gesprek op het trainingsweekend: het besluit, een interne notitie en het bericht aan de sollicitant (`tw_deelname.gesprek_bericht`, gaat mee in de mail). Wie een bellijst-regel of gesprek open heeft staat maximaal 30 minuten bij de rij (`bel_door`, `gesprek_door`). Sinds 2.117: groepsindeling van het weekend (`tw_groep`, `tw_deelname.groep_id`); "houd uit elkaar" geldt voor beide evenementen. Of iemand een terugkerende strandwacht is, wordt afgeleid uit de Authentik-groepen `lid-strandploeg` en `lid-oud-strandploeg` (afgelopen 3 seizoenen), die de competentie-sync al ophaalt (sinds 2.132.2; niets nieuws opgeslagen). Sinds 2.118: de eerste indruk van het bestuur tijdens het weekend (sowieso aannemen / twijfel / sowieso afwijzen) met bij twijfel een vraag aan de beoordelaars (`tw_deelname.evaluatie*`). Sinds 2.119: feedback die een strandwacht het bestuur gaf, door het bestuur vastgelegd: van wie, wanneer gezien, soort en tekst (`lifeguard_feedback`). |
| Ontvangers en verwerkers | Bestuur (modules `selectiedag` en `trainingsweekend`, bewerken). Begeleiders (rol `begeleider`): alleen lezen, inchecken, eigen cijfers en observaties geven, geen dossier en geen telefoonnummers. Van de evaluatie zien begeleiders alleen wie op twijfel staat ("extra aandacht gevraagd") met de vraag van het bestuur; sowieso aannemen of afwijzen zien ze niet. Feedback van strandwachten is alleen zichtbaar met dossierrecht (bestuur, penningmeester). Op de incheck-tablet ziet iemand na minstens 3 getypte letters hooguit 3 namen met foto — nooit een volledige lijst. Cloud86. |
| Doorgifte buiten de EER | Nee. |
| Bewaartermijn | Volgt het dossier: bij afwijzen 4 weken, bij aannemen 2 jaar na het laatste seizoen. Bij wissen gaan indeling, relaties (in beide richtingen), cijfers, observaties, besluiten, deelname (met evaluatie) en feedback van strandwachten weg. |
| Beveiliging | Rechten per module; begeleiders kunnen alleen hun eigen observaties verwijderen; incheck-tablet met een eigen code per evenement, snelheidsbegrenzing en een gehashte pincode om af te sluiten; logboek. |

## Gezondheidsverklaring en intake

> **In het kort:** vóór de selectiedag vragen we een gezondheidsverklaring en een intake, omdat
> sollicitanten het water in gaan. Dat zijn bijzondere persoonsgegevens (art. 9). Na het seizoen of bij
> afwijzing gaan de gezondheidsgegevens weg; het feit dát er is getekend blijft.

| Onderdeel | Inhoud |
|---|---|
| Doel | Veilig kunnen werken in het water en bij een noodgeval weten wat nodig is. De intake vraagt daarnaast toestemming voor het gebruik van foto's. |
| Grondslag | Gezondheidsgegevens: art. 9 lid 2 sub a AVG, uitdrukkelijke toestemming, gegeven door de ondertekening. Onder de 16 geeft de ouder of verzorger die toestemming (art. 5 UAVG). Foto's gebruiken: art. 6 lid 1 sub a, toestemming, altijd in te trekken. |
| Betrokkenen | Sollicitanten die zijn uitgenodigd; ouders of verzorgers die voor een minderjarige tekenen. |
| Persoonsgegevens | Ondertekeningstypen `gezondheid_18plus`, `gezondheid_ouder`, `intake`, `intake_ouder`: de antwoorden op de gezondheidsvragen (`gezondheid_keuze`, `gezondheid_toelichting`), overige intake-antwoorden, toestemming foto's, handtekening, verzegelde PDF. |
| Ontvangers en verwerkers | Bestuur (module `ondertekeningen`). Niet de begeleiders. Cloud86, e-mailprovider (kopie aan de ondertekenaar). |
| Doorgifte buiten de EER | Nee. |
| Bewaartermijn | `bewaar.gezondheid_mnd`, standaard 0: de gezondheidsgegevens (in de database en de verzegelde PDF) gaan weg na het einde van het seizoen (`datum_werk_eind`), en direct bij afwijzen of afhaken (bij de eerstvolgende dagelijkse run). Zonder einddatum van het seizoen: 12 maanden na ondertekening. Een waarde groter dan 0 betekent: zoveel maanden na ondertekening. De rest van de intake (verklaringen, toestemming foto's) en het feit dát er is getekend blijven bij het dossier. |
| Beveiliging | Verzegelde PDF versleuteld in de kluis; inzage alleen met module `ondertekeningen`; bewijslog van de ondertekening. De antwoorden zelf staan tot de opschoning in `ondertekeningen.formulier_data` in de database (niet in de kluis). |

## Ondertekening en bewijslog

> **In het kort:** elke digitale handtekening krijgt een bewijsspoor en een verzegelde PDF, zodat we
> kunnen aantonen wie wanneer tekende.

| Onderdeel | Inhoud |
|---|---|
| Doel | Aantonen dat de juiste persoon een document heeft ondertekend (bewijs, eIDAS), en de integriteit van het document waarborgen. |
| Grondslag | Art. 6 lid 1 sub f AVG (gerechtvaardigd belang: bewijs) en art. 32 (beveiliging). |
| Betrokkenen | Sollicitanten, aangenomen strandwachten, ouders of verzorgers. |
| Persoonsgegevens | Gebeurtenissen per ondertekening (`ondertekening_gebeurtenissen`: geopend, code verzonden, ondertekend, verzegeld, gedownload, verstuurd) met tijdstip, IP-adres en browser; handtekeningafbeelding; kanaal van de verificatiecode; een certificaat van ondertekening in de PDF. Verificatiecodes (`otp_tokens`). |
| Ontvangers en verwerkers | Bestuur (module `ondertekeningen`); voor loonheffing en lidmaatschap alleen de penningmeester (een rol met module `documenten`; de admin-rol telt hier niet vanzelf, `mag_kluis()`). Sms-dienst of e-mailprovider voor de verificatiecode. |
| Doorgifte buiten de EER | Alleen als de verificatiecode via een sms-gateway buiten de EER gaat. |
| Bewaartermijn | Zolang het bijbehorende document bestaat. Bij het wissen van het dossier gaan handtekening, vertrouwelijke gegevens, naam, e-mail, geboortedatum en -plaats van de ondertekenaar, IP-adres, browser en het adres van de verificatiecode weg; type en moment blijven. Bij een bewaard loondossier blijft het bewijs van de loonheffing staan, met het IP-adres gemaskeerd (laatste blok op 0), zonder e-mailadres en met alleen de laatste vier tekens van het adres van de verificatiecode. De verzegelde PDF zelf wordt niet aangepast (dan klopt het zegel niet meer). Gebruikte of verlopen verificatiecodes: 1 uur na verlopen (vast). |
| Beveiliging | PDF digitaal verzegeld (PKCS#7, eigen certificaat) en versleuteld in de kluis; verificatiecode verplicht voor de typen in `ondertekenen.2fa_typen` (standaard `loonheffing`); maximum aantal codes per uur. |

## Beschikbaarheid en loonplanning

> **In het kort:** wanneer iemand kan werken, verzoeken om minder dagen, correcties van het bestuur,
> de planning met loonkosten en (na het trainingsweekend) de vastgestelde inzet-afspraak.

| Onderdeel | Inhoud |
|---|---|
| Doel | Een bezetting van de posten maken, de loonkosten plannen en vastleggen wat er per persoon is afgesproken (weken, dagen, betaald of onbetaald, functie) als basis voor contract en loonadministratie. |
| Grondslag | Art. 6 lid 1 sub b AVG. |
| Betrokkenen | Sollicitanten en aangenomen strandwachten. Daarnaast extra personen in de loonplanning die geen sollicitant zijn, zoals de postcommandant (2.140). |
| Persoonsgegevens | Weken en dagen, vaste vrije dagen met reden, afwezigheid met omschrijving, verzoeken om minder dagen met toelichting (en welke dagen iemand wél kan), correcties door het bestuur met reden, losse dagen (tabellen `beschikbaarheid*`). Optioneel de vrije tekst voor de AI-hulp (`ai_beschikbaarheid_log`). Loonplanning: weken, dagen, betaald/onbetaald, functie, loon, notitie (`loon_planning`, `loon_scenario`, `loon_scenario_persoon`). Vastgestelde afspraak: functie, weken, dagen, betaald/onbetaald, wie en wanneer vastgesteld (`inzet_afspraak`, `inzet_afspraak_week`). De functie wordt voorgesteld uit de Lifeguard-rang in Authentik (zie Authentik). Extra personen: naam, functie, optioneel geboortedatum (alleen voor een dagloon naar leeftijd) en een korte notitie, plus hun planning per scenario (`loon_extra_persoon`, `loon_extra_planning`); ze krijgen geen portaal, berichten of afspraak en staan niet bij Sollicitanten. |
| Ontvangers en verwerkers | Bestuur (modules `beschikbaarheid`, `loonplanning`), penningmeester (`loonplanning`), begeleiders (`dekking`, alleen lezen), planner (`beschikbaarheid` en `dekking`, alleen lezen, plus de roosterexport zonder bedragen; geen dossier of contactgegevens). De sollicitant ziet de eigen afspraak in het portaal zodra die is vastgesteld. Google (Gemini) alleen voor de AI-hulp. |
| Doorgifte buiten de EER | Alleen de vrije tekst van de AI-hulp (zie [AI-analyse](#ai-analyse)). |
| Bewaartermijn | Volgt het dossier: bij wissen gaan alle tabellen `beschikbaarheid*` en `ai_beschikbaarheid_log` weg. AI-invoertekst: 3 maanden (`bewaar.ai_invoer_mnd`, standaard 3). Loonplanning en vastgestelde afspraak blijven zonder notitie staan en zijn na anonimiseren niet meer herleidbaar. Extra personen: tot het bestuur ze verwijdert (Loonplanning, paneel van de persoon), uiterlijk met het seizoen (Seizoen › verwijderen). Gaan mee in de export voor de loonadministratie en het rooster. |
| Beveiliging | Rechten per module; correcties alleen met reden en naam van wie corrigeert; de AI-uitvoer wordt op de server gecontroleerd voordat er iets wordt ingevuld. |

## Loondossier

> **In het kort:** BSN, kopie identiteitsbewijs, kopie bankpas en de opgaaf loonheffing van
> aangenomen strandwachten. Alles in de kluis; openen kan alleen de penningmeester. Loondocumenten
> bewaren we 5 jaar na het einde van het jaar waarin het dienstverband eindigde; de penningmeester
> vernietigt ze met de hand.

| Onderdeel | Inhoud |
|---|---|
| Doel | Loon uitbetalen, loonheffing inhouden en afdragen, en de identiteit van de werknemer vastleggen. |
| Grondslag | Opgaaf loonheffingen, BSN en kopie identiteitsbewijs: art. 6 lid 1 sub c AVG, wettelijke plicht (Wet op de loonbelasting 1964, art. 28 en 29; BSN: art. 46 UAVG). Kopie bankpas: art. 6 lid 1 sub b, uitvoering van de arbeidsovereenkomst. |
| Betrokkenen | Aangenomen strandwachten. |
| Persoonsgegevens | Naam, voorletters, BSN, adres, geboortedatum, keuze loonheffingskorting, handtekening, IP-adres, browser en tijdstip, kanaal van de verificatiecode; kopie identiteitsbewijs voor- en achterkant (volledig, niet afgeschermd, zoals de wet eist) met documenttype; kopie voorkant bankpas (naam en IBAN). |
| Ontvangers en verwerkers | Penningmeester (module `documenten`). De admin ziet dat de stukken er zijn en kan ze vernietigen, maar opent ze niet (sinds 2.132.0, `mag_kluis()`); in een noodgeval zet de admin zichzelf in Authentik in `app-sol-penningmeester`, wat in beide logboeken komt. Een geweigerde poging staat in het logboek (`document_geweigerd`). Het bestuur ziet deze stukken niet. De Belastingdienst (via de loonaangifte). Cloud86. Mail met bijlagen aan de penningmeester alleen als `documenten.penningmeester_bijlagen` aan staat (standaard uit). |
| Doorgifte buiten de EER | Nee. |
| Bewaartermijn | Opgaaf loonheffing en kopie identiteitsbewijs: 5 jaar na het einde van het kalenderjaar waarin het dienstverband eindigde (fiscale bewaarplicht). Het systeem vernietigt ze **niet** automatisch, omdat het einde van het dienstverband niet vastligt; `bewaar.loondocumenten_jaar` (standaard 5) bepaalt alleen wanneer een dossier op de lijst "Te beoordelen voor vernietiging" komt. De penningmeester vernietigt ze op het scherm Loondossiers. Kopie bankpas: 30 dagen nadat de penningmeester het dossier als verwerkt heeft gemarkeerd (`bewaar.bankpas_na_verwerking_dagen`, standaard 30). |
| Beveiliging | Kluis (`includes/kluis.php`): envelope-encryptie met XChaCha20-Poly1305 (terugval AES-256-GCM), een eigen sleutel per bestand, hoofdsleutel afgeleid uit `KLUIS_SLEUTEL` in `config.php` én een zout in de database, geen onversleutelde terugval. Bestanden buiten het webbereik (`DOCUMENTEN_MAP`). Download met watermerk, elke inzage in het logboek, melding aan het bestuur bij elke download (`documenten.download_melding`, standaard aan). Verificatiecode verplicht bij de opgaaf loonheffing. Uploadlimieten per uur. |

Het recht op verwijderen wijkt alleen voor wat de wet eist (AVG art. 17 lid 3 sub b). Verwijdert
een sollicitant met een verwerkt loondossier zichzelf, dan gaat al het andere weg en blijven alleen
de loonheffing-ondertekening (met BSN), de kopie van het identiteitsbewijs, naam en geboortedatum
(`avg_behoud_loondossier()`). Het portaal legt in eenvoudige taal uit wat blijft, waarom en tot
wanneer. Het dossier blijft op de lijst "Te beoordelen voor vernietiging" komen (de naam staat er
nog, de loonheffing-ondertekening bestaat nog). Vernietigt de penningmeester dat loondossier, dan
gaan meteen ook naam en geboortedatum weg: `documenten_loondossier_vernietigen()` roept daarvoor
`avg_na_loondossier_vernietigd()` aan (`includes/documenten.php`, `includes/avg_wissen.php`).

## Lidmaatschap

> **In het kort:** aangenomen strandwachten worden lid van de vereniging. De inschrijving wordt in
> het portaal getekend en gaat per e-mail naar de ledenservice. Bij een minderjarige tekent de
> ouder of verzorger, en de SEPA-machtiging tekent de rekeninghouder.

| Onderdeel | Inhoud |
|---|---|
| Doel | Inschrijving als lid of donateur van de vereniging, en de contributie innen. |
| Grondslag | Art. 6 lid 1 sub b AVG (lidmaatschapsovereenkomst); bij incasso de SEPA-machtiging. Een minderjarige is handelingsonbekwaam (grens 18, zie [Bijzonderheden](#bijzonderheden)), dus de lidmaatschapsovereenkomst van iemand onder de 18 wordt aangegaan en getekend door de ouder of verzorger. Een SEPA-machtiging kan alleen de rekeninghouder geven; die tekent de machtiging. |
| Betrokkenen | Aangenomen strandwachten; bij een minderjarige de ouder of verzorger die tekent; bij incasso de rekeninghouder (dat kan een ander zijn dan het lid, bijvoorbeeld de ouder). |
| Persoonsgegevens | Naam, geslacht, geboortedatum en -plaats, adres, telefoon, e-mail, gegevens van de ouder, lid of donateur, diploma's, betaalwijze, bij incasso IBAN en tenaamstelling, SEPA-machtiging, handtekening, verzegelde PDF. Bij een minderjarige: naam, e-mailadres en telefoonnummer van de ouder of verzorger die tekent, diens handtekening en het bewijsspoor van die ondertekening (tijdstip, IP-adres, browser; zie [Ondertekening en bewijslog](#ondertekening-en-bewijslog)); bij incasso naam en IBAN van de rekeninghouder en diens handtekening onder de machtiging. |
| Status (september 2026) | Het tekenen door de ouder of verzorger en de machtiging door de rekeninghouder worden op dit moment gebouwd. Tot dat in gebruik is, tekent de sollicitant zelf in het portaal en staan de oudergegevens alleen als invulvelden op het formulier. Na oplevering dit hoofdstuk en de privacyverklaring nalopen tegen de code (zie [Nog vast te leggen](#nog-vast-te-leggen)). |
| Ontvangers en verwerkers | Ledenservice: e-mail met de gegevens en de verzegelde PDF naar `mail.ledenservice_email` (leeg = bestuursadres). Penningmeester voor de PDF en het IBAN (module `documenten`, `mag_kluis()`). E-mailprovider. |
| Doorgifte buiten de EER | Nee. |
| Bewaartermijn | In dit systeem: volgt het dossier. De ledenadministratie bij de ledenservice valt buiten dit systeem en heeft een eigen termijn (vastleggen bij de ledenadministratie). |
| Beveiliging | IBAN en tenaamstelling versleuteld in de kluis (`ondertekeningen.vertrouwelijk`), niet in `formulier_data`; verzegelde PDF in de kluis. In de mailtekst aan de ledenservice staan alleen de laatste vier tekens van het IBAN; het volledige IBAN staat in de bijgevoegde verzegelde PDF (lukt de PDF niet, dan staat het volledig in de mail). De inhoud van de mail komt niet in het berichtenlog (`body_loggen` uit). |

## Berichten

> **In het kort:** e-mail, en naar keuze WhatsApp of sms. We houden bij wat we stuurden en of het
> aankwam; de tekst zelf bewaren we korter dan het overzicht.

| Onderdeel | Inhoud |
|---|---|
| Doel | De sollicitant op de hoogte houden, taken en herinneringen sturen, inlogcodes versturen, het bestuur en de penningmeester informeren, en vragen van de sollicitant aan het bestuur doorgeven (portaal, blok "Een vraag?"). |
| Grondslag | Art. 6 lid 1 sub b AVG. Inlogcodes en veiligheidsberichten: ook art. 32. |
| Betrokkenen | Sollicitanten, ouders of verzorgers, aangenomen strandwachten, bestuursleden en penningmeester als ontvanger. |
| Persoonsgegevens | Ontvanger (e-mailadres of telefoonnummer), onderwerp, samenvatting, inhoud, kanaal, status, tijdstip (`mail_log`); wachtrij met adres, telefoon en volledige tekst (`mail_queue`); vragen via het portaal; wanneer de sollicitant de berichten voor het laatst bekeek. Bij inlogcodes, bestuursmeldingen, de ledenservice en de penningmeester wordt de inhoud nooit gelogd. |
| Ontvangers en verwerkers | Bestuur (module `mailing`). E-mailprovider (`smtp.host`); WhatsReach en Meta als `wa.actief` aan staat; de sms-dienst uit `sms.gateway` als `sms.actief` aan staat (Bird/MessageBird, EasySendSMS, een eigen telefoon of een HTTP-gateway). |
| Doorgifte buiten de EER | WhatsApp: ja (Meta, VS). Sms: bij `easysendsms` ja (EasySendSMS, buiten de EER; de privacyverklaring noemt het dan); andere gateways vastleggen. E-mail: afhankelijk van de provider; vastleggen. |
| Bewaartermijn | Overzicht van berichten: 12 maanden (`bewaar.maillog_mnd`, standaard 12). Inhoud van berichten: 3 maanden (`bewaar.maillog_body_mnd`, standaard 3). Wachtrij: 30 dagen na verzending of fout (vast); bij berichten waarvan de inhoud nooit gelogd wordt (inlogcodes, bestuursmeldingen, ledenservice, penningmeester) worden de tekst en de korte tekst in de wachtrij direct na een geslaagde verzending leeggemaakt (`includes/meldingen/verzend.php`). Losse bijlagen die aan geen sjabloon meer hangen: 12 maanden (`bewaar.bijlagen_mnd`, standaard 12). |
| Beveiliging | Dagplafond en uurlimiet (WhatsApp), pauze na herhaalde fouten, afmeldlink, kanaalkeuze in het portaal; geheimen (API-sleutels, SMTP-wachtwoord) worden in het beheer nooit teruggetoond. |

## AI-analyse

> **In het kort:** twee toepassingen, allebei via de Gemini API van Google: een beoordeling van
> het kennismakingsformulier voor het bestuur (model uit `ai.model`, standaard `gemini-2.5-flash`),
> en een hulp die vrije tekst over beschikbaarheid omzet naar het rooster (`gemini-2.5-flash`).
> Naam, contactgegevens, adres, foto en bijzonderheden gaan niet mee. Een mens beslist altijd.

| Onderdeel | Inhoud |
|---|---|
| Doel | Analyse kennismakingsformulier: het bestuur een korte beoordeling met advies geven als hulpmiddel. Beschikbaarheidshulp: de sollicitant helpen het rooster in te vullen. |
| Grondslag | Analyse kennismakingsformulier: art. 6 lid 1 sub f AVG (gerechtvaardigd belang: zorgvuldig en efficiënt selecteren); de sollicitant kan bezwaar maken via een vraag in Berichten of per mail. Het bestuur legt dat vast met de knop "Bezwaar vastleggen" bij de AI-analyse op het kennismakingsformulier in het beheer (`admin/blk_detail.php`, via `admin/api_ai_analyse.php` naar `ai_bezwaar_zetten()`; recht `sollicitanten` bewerken): de vlag `sollicitaties.ai_bezwaar` wordt gezet, de analyse gewist, en de wachtrij slaat de sollicitant daarna over. Beschikbaarheidshulp: art. 6 lid 1 sub b; het gebruik is vrijwillig, handmatig invullen kan altijd. Geen geautomatiseerd besluit in de zin van art. 22. Geen bijzondere persoonsgegevens: het veld bijzonderheden (medicatie, allergieën) gaat niet naar de AI, want daarvoor is geen grondslag onder art. 9. |
| Betrokkenen | Sollicitanten die het kennismakingsformulier indienen of de beschikbaarheidshulp gebruiken. |
| Persoonsgegevens | Analyse kennismakingsformulier, wat naar Google gaat: leeftijdscategorie (geen geboortedatum), opleiding, diploma's, werkervaring, motivatie, aantal weken beschikbaar. Niet: naam, adres, e-mail, telefoon, foto, bijzonderheden. De uitkomst staat in `blk_formulieren.ai_analyse`. Beschikbaarheidshulp: de vrije tekst (maximaal 1500 tekens) en de weken van het seizoen; de tekst wordt bewaard in `ai_beschikbaarheid_log`. Let op: dit is pseudonimisering, geen anonimisering. Vrije tekst kan zelf herleidbare of gezondheidsinformatie bevatten. |
| Ontvangers en verwerkers | Google (Gemini API), alleen als `ai.api_key` gevuld is. De analyse is zichtbaar voor het bestuur (module `blk`) en voor de sollicitant in de download (inzage). Automatisch starten na indienen alleen als `ai.auto_analyse` aan staat (standaard aan); anders alleen met de knop in het beheer. Beschikbaarheidshulp alleen als `ai.beschikbaarheid_hulp` aan staat (standaard uit). |
| Doorgifte buiten de EER | Ja, Google LLC (VS), op grond van het EU-VS Data Privacy Framework of standaardcontractbepalingen. Gebruik een betaald API-account met verwerkersvoorwaarden: bij het gratis niveau mag Google de invoer gebruiken om zijn diensten te verbeteren. |
| Bewaartermijn | Analyse: volgt het kennismakingsformulier (en daarmee het dossier); bij bezwaar direct weg. Invoertekst beschikbaarheidshulp: 3 maanden (`bewaar.ai_invoer_mnd`, standaard 3). Wachtrij en invoerlog gaan mee met het wissen van het dossier. |
| Beveiliging | Alleen de cron roept de API aan (wachtrij `ai_queue`), met backoff en een maximum aantal pogingen; de cron controleert vlak voor het versturen opnieuw op bezwaar; de API-sleutel wordt in het beheer nooit teruggetoond; strikte prompt en validatie op de server. |

AI die sollicitanten beoordeelt valt onder de AI-verordening in de categorie hoog risico (bijlage
III, punt 4). Voor de vereniging als gebruiker betekent dat onder meer: menselijk toezicht, de
sollicitant informeren en de werking kunnen verantwoorden. Het bestuur toetst dit voordat de
analyse van het kennismakingsformulier op productie aan gaat.

## Landingspagina's en bezoekersstatistiek

> **In het kort:** we tellen bezoeken en afhakers op de landingspagina's en het aanmeldformulier,
> zonder cookies en zonder IP-adres op te slaan. Een dagpseudoniem maakt "uniek" tellen mogelijk en
> wordt na 30 dagen gewist.

| Onderdeel | Inhoud |
|---|---|
| Doel | Zien welke wervingskanalen werken en waar mensen in het formulier afhaken, om de werving en het formulier te verbeteren. |
| Grondslag | Art. 6 lid 1 sub f AVG (gerechtvaardigd belang). Geen cookies of volgtechnieken, dus geen toestemming nodig onder de Telecommunicatiewet. |
| Betrokkenen | Bezoekers van landingspagina's (`/p/<slug>`), het aanmeldformulier en (sinds 2.142) de pagina met het formulier op paal13.com via de WordPress-plugin; sollicitanten (bron van hun aanmelding). |
| Persoonsgegevens | Per gebeurtenis (`pagina_events`): type (bezoek, formulier gestart, stap, verlaten, klaar, geweigerd), pagina, bron, stapnummer, tijdstip en een dagpseudoniem: een HMAC-SHA-256 van IP-adres en browser met een dagsleutel, die is afgeleid van de datum en een geheim zout (`paginas.pseudoniem_zout`, 32 willekeurige bytes, eenmalig aangemaakt). Zonder het zout niet terug te rekenen, en elke dag anders, dus niet over dagen te volgen. Sinds 2.145 ook de UTM-velden uit het adres (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`), geschoond tot a-z, 0-9 en koppeltekens, maximaal 40 tekens. Geen IP-adres, geen browser, niets wat is ingevuld. Bij de sollicitatie: `bron_code` ("<bron>" of "<bron>/<campagne>", de campagne uit `?bron=`, `utm_campaign` of `utm_source` van het adres waarmee iemand binnenkwam) en `bron_label`, plus dezelfde vier UTM-velden (zie [Aanmelding en sollicitatie](#aanmelding-en-sollicitatie)). Het bezoek op paal13.com meldt het formulierscript rechtstreeks aan `/track.php` (anoniem, geen cookie, zelfde pseudoniem); WordPress zelf slaat niets op. |
| Ontvangers en verwerkers | Bestuur (module `paginas`). Cloud86. |
| Doorgifte buiten de EER | Nee. |
| Bewaartermijn | Dagpseudoniem gewist na 30 dagen (`bewaar.pagina_hash_dagen`, standaard 30); gebeurtenis verwijderd na 13 maanden (`bewaar.pagina_events_mnd`, standaard 13). Bron bij de sollicitatie: volgt het dossier. Aan- en uitzetten: `paginas.tracking` (standaard aan). |
| Beveiliging | Bots worden niet geteld; herhaalde weergaven binnen 30 minuten tellen niet opnieuw; de landingspagina's draaien onder een strikte Content-Security-Policy (`connect-src 'none'`). |

## Beheerders en logboek

> **In het kort:** wie in het beheer werkt, logt in via Authentik met tweestapsverificatie. Wat
> beheerders en sollicitanten doen, komt in het logboek, voor de beveiliging en om fouten na te
> lopen.

| Onderdeel | Inhoud |
|---|---|
| Doel | Toegang tot het beheer regelen, handelingen herleidbaar maken (wie deed wat), misbruik en inbraakpogingen herkennen en fouten oplossen. |
| Grondslag | Art. 6 lid 1 sub f AVG (gerechtvaardigd belang) en art. 32 (plicht tot beveiliging). |
| Betrokkenen | Beheerders (bestuur, penningmeester, planner, begeleiders, admin); sollicitanten wier handelingen worden gelogd. |
| Persoonsgegevens | Beheerders: naam, e-mailadres, Authentik-koppeling en groepen, rol, inlogmomenten. Logboek (`audit_log`): wie, actie, object, details, IP-adres, browser, tijdstip. Foutenlog (`error_log`): foutmelding, URL, e-mailadres van de ingelogde gebruiker. Mislukte inlogpogingen (`login_pogingen`): e-mailadres, IP-adres. Opschoonlog (`opschoon_log`): alleen aantallen. Tellingen van de snelheidsbegrenzing (`api_rate`). |
| Ontvangers en verwerkers | Admin; module `logboek` (standaard alleen admin). Authentik (eigen installatie). Cloud86. |
| Doorgifte buiten de EER | Nee. |
| Bewaartermijn | Logboek: 12 maanden (`bewaar.audit_maanden`, standaard 12). Foutenlog: 90 dagen (`bewaar.foutenlog_dagen`, standaard 90). Mislukte inlogpogingen: 24 uur (vast). Tellingen snelheidsbegrenzing: na verlopen (vast). Beheerdersaccount: zolang iemand toegang nodig heeft; bij vertrek uit de Authentik-groep halen. |
| Beveiliging | Inloggen via Authentik (OIDC) met tweestapsverificatie, rollen met rechten per module, de berichtinhoud (`mail_log.body`) komt in geen enkele logboekweergave voor. |

## Productiekopie op de testomgeving

> **In het kort:** vóór elke productie-uitrol gaat een kopie van de productiedatabase naar de
> testomgeving (zelfde hoster) en wordt die meteen geanonimiseerd. De echte gegevens staan daar
> alleen de paar minuten tussen importeren en anonimiseren; het exportbestand wordt direct
> weggegooid.

| Onderdeel | Inhoud |
|---|---|
| Doel | Een nieuwe versie testen tegen de echte opbouw en aantallen van productie ("generale repetitie"), zodat fouten niet pas op productie en bij echte sollicitanten optreden. |
| Grondslag | Art. 6 lid 1 sub f AVG (gerechtvaardigd belang: een betrouwbaar systeem) en art. 32 (testen hoort bij passende beveiliging). Het gebruik is verenigbaar met het oorspronkelijke doel (art. 6 lid 4): zelfde verantwoordelijke, zelfde hoster, kort, en direct geanonimiseerd. |
| Betrokkenen | Alle betrokkenen van wie gegevens in de productiedatabase staan. |
| Persoonsgegevens | Tijdelijk de hele database. Daarna vervangt `tools/anonimiseer_testkopie.php` namen, e-mailadressen, telefoonnummers, adressen, vrije teksten, berichtinhoud, IP-adressen en browsers door nepwaarden; geboortedatums schuiven hooguit een half jaar; handtekeningen, versleutelde BSN/IBAN-velden, formulierantwoorden en foto's gaan weg; inlogcodes, tokens en wachtrijen worden geleegd; sleutels van koppelingen worden leeggemaakt of vernieuwd. De bestanden in de kluis (`DOCUMENTEN_MAP`) en de kluissleutel van productie gaan niet mee, dus documenten zijn op test onleesbaar. Wat blijft: fases, seizoenen, data van aanmelden en ondertekenen, aantallen en instellingen. Strikt genomen is dat pseudonimisering, geen volledige anonimisering: met kennis van buiten (wie wanneer solliciteerde) is een rij soms nog te herleiden. |
| Ontvangers en verwerkers | Alleen de technisch beheerder en wie toegang heeft tot het beheer van test. Cloud86 (zelfde hoster als productie). Geen externe diensten: WhatsApp en sms gaan uit, de AI-sleutel wordt leeggemaakt en `MAIL_OMLEIDEN` leidt alle e-mail naar de tester (het script weigert zonder). |
| Doorgifte buiten de EER | Nee. |
| Bewaartermijn | Echte gegevens op test: alleen tussen importeren en het script (minuten). Het exportbestand: direct na het script verwijderen, van de server en van de laptop. De geanonimiseerde kopie blijft tot de volgende generale repetitie. |
| Beveiliging | De cron van test staat uit tijdens importeren en anonimiseren; het script draait alleen vanaf de opdrachtregel, alleen op test, alleen met `--ja`, in één transactie, en controleert na afloop alle tekstkolommen op overgebleven e-mailadressen, telefoonnummers en IBAN's. Stappen in het Runbook systeembeheer, hoofdstuk "Generale repetitie op test". |

## Seintje bij de volgende werving

> **In het kort:** een afgewezen sollicitant kan met één knop vragen om bericht als we weer mensen
> zoeken. Dat is toestemming, apart gegeven en net zo makkelijk in te trekken.

| Onderdeel | Inhoud |
|---|---|
| Doel | Afgewezen sollicitanten die dat willen, benaderen bij een volgende werving. |
| Grondslag | Art. 6 lid 1 sub a AVG, toestemming, los gegeven (art. 7) en vereist voor ongevraagde berichten (Telecommunicatiewet art. 11.7). Oud-strandwachten (ooit aangenomen) benaderen we op grond van gerechtvaardigd belang (art. 6 lid 1 sub f), met een afmeldlink als bezwaarmogelijkheid; dat staat in de privacyverklaring. |
| Betrokkenen | Afgewezen sollicitanten; oud-strandwachten. |
| Persoonsgegevens | Moment en bron van de toestemming (`personen.seintje_op`, `seintje_bron`), afmeldtoken, en de gegevens van het dossier die zolang blijven staan. |
| Ontvangers en verwerkers | Bestuur (module `mailing`). E-mailprovider, eventueel WhatsReach of sms-dienst. |
| Doorgifte buiten de EER | Alleen via WhatsApp (zie [Berichten](#berichten)). |
| Bewaartermijn | Maximaal 12 maanden na de toestemming (`bewaar.seintje_mnd`, standaard 12). Na intrekken: anonimiseren volgens `bewaar.afgewezen_weken`. |
| Beveiliging | Toestemming geven nooit op een GET (mailscanners), alleen na een klik op een knop; intrekken via de afmeldlink of in het portaal. |

**Seintje buiten het seizoen.** Staat de aanmelding dicht, dan tonen het aanmeldformulier, de
landingspagina's en het formulier op paal13.com een klein formulier: "laat je e-mailadres achter,
dan sturen we je één bericht zodra je je kunt aanmelden" (`includes/seintje_werving.php`).

| Onderdeel | Inhoud |
|---|---|
| Doel | Mensen die nog niet solliciteerden één bericht sturen als de aanmelding opengaat. |
| Grondslag | Art. 6 lid 1 sub a AVG, toestemming: een apart, niet vooraf aangevinkt vakje "Ja, stuur me één bericht als de werving opent", met de privacyverklaring erbij. Dubbele bevestiging (double opt-in): pas na de klik in de bevestigingsmail staat het seintje aan, want iedereen kan elk adres invullen (AVG art. 7 lid 1 aantoonbaarheid, Telecommunicatiewet art. 11.7). |
| Betrokkenen | Bezoekers van het aanmeldformulier, een landingspagina of paal13.com buiten de aanmeldperiode. |
| Persoonsgegevens | Voornaam, e-mailadres, bron (pagina of campagne, voor de statistiek), moment van aanvragen, bevestigen en versturen, en een token (alleen als sha256-hash). Tabel `werving_seintje`. Het IP-adres komt alleen in het logboek (bevestigen, intrekken) en in de snelheidsbegrenzing, niet in de tabel. |
| Ontvangers en verwerkers | Bestuur (module `mailing`, Berichten › groep Seintje gevraagd). E-mailprovider. Alleen e-mail; geen WhatsApp of sms (geen telefoonnummer). |
| Doorgifte buiten de EER | Nee. |
| Bewaartermijn | Onbevestigd: 7 dagen, daarna weg (altijd, ook met `bewaar.actief` uit: zonder bevestiging is er geen toestemming). Bevestigd: tot het openingsbericht is verstuurd (een dag later weg), en uiterlijk `bewaar.seintje_mnd` maanden (standaard en maximaal 12) na de bevestiging. Intrekken: meteen weg. |
| Intrekken | De link in de bevestigingsmail en de afmeldlink onder het openingsbericht leiden naar `/seintje.php` met de knop "Toch geen seintje"; die verwijdert de rij direct (art. 7 lid 3). |
| Beveiliging | Anti-bot-token (landingspagina, paal13.com) of csrf (`/aanmelden.php`), honeypot, maximaal 10 aanvragen per uur per IP-adres en maximaal 3 bevestigingsmails per adres per dag. Bevestigen en intrekken alleen met een knop die post, niet op een GET (mailscanners). Het scherm verklapt nooit of een adres al bekend is. De bevestigingsmail wordt niet gelogd (`body_loggen` uit). |

## Bijzonderheden

> **In het kort:** twee leeftijdsgrenzen die juridisch los van elkaar staan: 15/16 voor aanmelden
> en toestemming, 18 voor ondertekenen. Niet samenvoegen.

**Minderjarigen: twee aparte grenzen.**

| Grens | Waarvoor | Hoe in het systeem |
|---|---|---|
| 15/16 | Minimumleeftijd voor aanmelden, en de AVG-grens voor toestemming (art. 8 AVG, art. 5 UAVG: onder de 16 geeft de ouder of verzorger toestemming) | Aanmelden kan vanaf 15 (vast; een geboortedatum onder de 15 wordt geweigerd). Bij een 15-jarige geeft de ouder bij het aanmelden toestemming; naam en moment worden vastgelegd. |
| 18 | Ondertekeningsgrens: minderjarigen zijn handelingsonbekwaam | Onder de 18 vragen we naam, e-mail en telefoon van de ouder; de ouder tekent de gezondheidsverklaring en de intake via een link in de mail, en (in aanbouw, zie [Lidmaatschap](#lidmaatschap)) ook het lidmaatschap; een SEPA-machtiging tekent altijd de rekeninghouder. De opgaaf loonheffing tekent de minderjarige zelf. |

Dit zijn twee juridisch verschillende grenzen. Voeg ze nooit samen tot één grens, ook niet bij het
opschonen van code. De instelling `invite.min_leeftijd` (standaard 16) is iets anders: die bepaalt
alleen wie automatisch voor de selectiedag wordt uitgenodigd.

**Geautomatiseerde besluiten.** Er zijn er twee: een geboortedatum onder de 15 blokkeert het
formulier, en het systeem kan automatisch uitnodigen voor de selectiedag (`invite.modus`). Dat
laatste is nooit een afwijzing: wie niet automatisch wordt uitgenodigd, wordt door een mens
bekeken. Over afwijzen en aannemen beslissen altijd mensen.

**Bijzondere persoonsgegevens.** Alleen de gezondheidsgegevens in de gezondheidsverklaring en de
intake (zie [Gezondheidsverklaring en intake](#gezondheidsverklaring-en-intake)). Het vrije veld
"bijzonderheden" in het kennismakingsformulier (medicatie, allergieën) kan ze ook bevatten;
begeleiders zien dat veld daarom niet, en het gaat niet naar de AI-dienst.

**BSN.** Alleen voor de loonheffing (art. 46 UAVG), altijd in de kluis, nooit in
`formulier_data`, logs of berichten.

## Rechten van betrokkenen

> **In het kort:** inzage en verwijderen kan de sollicitant zelf in het portaal onder Mijn
> gegevens. Verbeteren gaat via een vraag aan het bestuur. Al het andere via het bestuursadres;
> antwoord binnen een maand.

| Recht | Hoe het werkt |
|---|---|
| Inzage (art. 15) | Portaal › Mijn gegevens › Al mijn gegevens downloaden: een JSON-bestand (`avg_inzage()`) met persoon, sollicitatie, kennismakingsformulier met wijzigingen, AI-analyse, beschikbaarheid (alle huidige tabellen en de AI-invoertekst), selectiedag en trainingsweekend (indeling, cijfers, observaties, besluiten), notities van het bestuur, ondertekeningen (type, datum), documenten (soort, datum, status), berichten (datum, onderwerp, kanaal), ingelogde apparaten en de herkomst van de aanmelding (sleutel `herkomst`: via welke pagina of site, en de UTM-bron, -medium, -campagne en -variant). Niet: BSN, IBAN en handtekening (die staan in de verzegelde PDF's onder de taken), de bestanden zelf, en namen van beoordelaars en bestuursleden (rechten van anderen, art. 15 lid 4; daar staat "beoordelaar"). |
| Verbeteren (art. 16) | De sollicitant stelt een vraag via het blok "Een vraag?" in het portaal (komt bij het bestuur binnen met naam en fase). Het bestuur corrigeert in het dossier; een wijziging aan het kennismakingsformulier wordt met reden vastgelegd. |
| Verwijderen (art. 17) | Portaal › Mijn gegevens › Mijn account verwijderen (bevestigen met VERWIJDER): het dossier wordt leeggemaakt en de persoon geanonimiseerd; het bestuur krijgt bericht. Is het loondossier verwerkt, dan blijven alleen het loondossier, naam en geboortedatum (art. 17 lid 3 sub b, zie [Loondossier](#loondossier)); het scherm legt uit wat blijft en tot wanneer. Een verzoek per mail handelt het bestuur af in het dossier: Verwijderen, voor één sollicitatie of de hele persoon. Een verwerkt loondossier gaat daar alleen mee met de verklaring dat het dienstverband niet is doorgegaan. |
| Bezwaar (art. 21) | Tegen verwerking op grond van gerechtvaardigd belang. AI-analyse: via een vraag in Berichten of per mail; het bestuur zet het bezwaar met de knop "Bezwaar vastleggen" op het kennismakingsformulier (`ai_bezwaar_zetten()`), de analyse wordt gewist en niet opnieuw gemaakt. Wervingsberichten aan oud-strandwachten: de afmeldlink. |
| Toestemming intrekken (art. 7 lid 3) | Seintje: afmeldlink of de knop in het portaal. Seintje buiten het seizoen: de knop "Toch geen seintje" achter de link in de mail (gegevens meteen weg). Foto's: per mail. Wat al gebeurde blijft rechtmatig. |
| Beperking en overdraagbaarheid (art. 18, 20) | Per mail aan het bestuur; de JSON-download dient als overdraagbaar bestand. |
| Apparaten | Mijn gegevens › "vergeet alle apparaten" trekt alle "blijf ingelogd"-tokens in en logt open sessies op andere apparaten meteen uit (sinds 2.145; de sessie waarin je het doet blijft). |

Bij een verzoek per mail mag het bestuur vragen de identiteit aan te tonen, zodat gegevens niet bij
de verkeerde persoon terechtkomen. Klachten kunnen bij de Autoriteit Persoonsgegevens.

## Datalekken

> **In het kort:** de procedure staat in het Runbook systeembeheer, hoofdstuk "Datalek of
> beveiligingsincident". Melden bij de Autoriteit Persoonsgegevens binnen 72 uur; elk lek komt in
> het datalekregister van het bestuur.

De stappen (vaststellen, indammen, beoordelen, melden bij de AP binnen 72 uur, betrokkenen
informeren bij een hoog risico, registreren) en de rolverdeling staan in het Runbook systeembeheer,
hoofdstuk "Datalek of beveiligingsincident". Het runbook is in het beheer alleen zichtbaar met de
module `systeem`; het bestuur kan het opvragen bij de technisch beheerder of de Word-versie
gebruiken. Het datalekregister zelf houdt het bestuur buiten dit systeem bij.

## Nog vast te leggen

> **In het kort:** punten waarvan dit register zegt hoe het hoort, maar die in de praktijk of in de
> code nog niet rond zijn. Afvinken en dan uit deze lijst halen.

- [ ] `bewaar.actief` op productie aanzetten; zonder dat gelden geen van de termijnen hierboven.
- [ ] Verwerkersovereenkomsten vastleggen met Cloud86, de e-mailprovider, en (als ze aan staan)
  WhatsReach, de sms-dienst en Google (Gemini API, betaald account).
- [ ] Vastleggen welke e-mailprovider en welke sms-gateway op productie in gebruik zijn, en waar ze
  de gegevens verwerken.
- [ ] De bewaartermijn van de ledenadministratie bij de ledenservice vastleggen.
- [ ] AI-analyse: de AI-verordening toetsen (zie [AI-analyse](#ai-analyse)).
- [ ] Lidmaatschap van minderjarigen (ouder of verzorger tekent, SEPA-machtiging door de
  rekeninghouder): na de bouw controleren of dit register en de privacyverklaring kloppen met de
  code (zie [Lidmaatschap](#lidmaatschap)).
- [ ] Het bewijs van ondertekening met gemaskeerd IP-adres laten toetsen: is een IP-adres met het
  laatste blok op 0 nog nodig als bewijs, of kan het helemaal weg?
- [ ] Gezondheidsverklaring vóór de selectiedag: laten toetsen aan de Wet op de medische keuringen
  (een aanstellingskeuring mag alleen bij bijzondere functie-eisen en in de laatste fase van de
  selectie).

## Wijzigingen

> **In het kort:** elke wijziging van dit register, met datum.

| Datum | Wat |
|---|---|
| 18 september 2026 | Eerste register voor het aangenomen-takenpakket (loonheffing, kopie ID, bankpas, lidmaatschap, bewijs van ondertekening), bijgehouden in de lokale projectnotities. |
| 24 september 2026 | Verhuisd naar `docs/handleiding/verwerkingsregister.md` (in Git en in het beheer) en uitgebreid tot alle verwerkingen van het sollicitatiesysteem, gecontroleerd tegen de code van versie 2.101. |
| 24 september 2026 | AVG-gaten gedicht: wissen neemt selectiedag, trainingsweekend, alle beschikbaarheidstabellen, wijzigingen aan het kennismakingsformulier, apparaten, AI-wachtrij en het bewijsspoor mee; bijzonderheden niet meer naar de AI; `ai.model` en `ai.auto_analyse` worden gevolgd; bezwaar tegen AI-analyse (`ai_bezwaar`); volledige inzage; zelf verwijderen met een verwerkt loondossier; geheim, dagelijks wisselend bezoekerspseudoniem; IBAN gemaskeerd in de mail aan de ledenservice; privacyverklaring bijgewerkt (EasySendSMS, oud-strandwachten). |
| 24 september 2026 | Het lettertype (Instrument Sans) staat nu op de eigen server; er gaat geen IP-adres meer naar Google Fonts. Google Fonts uit de lijst met verwerkers gehaald. |
| 24 september 2026 | Beschikbaarheid exporteren naar Excel (per week of per dag, met redenen van afwezigheid): alleen naam, fase en opgave, geen contactgegevens of geboortedatum; elke export in het logboek. Loonplanning: vastzetten na het gesprek op het trainingsweekend (`loon_vergrendeling`: wie en wanneer; ontgrendelen met reden in het logboek). |
| 24 september 2026 | Selectiedag en trainingsweekend: bevestiging "ik kom / ik kan niet" met reden, het gesprek met het bestuur (besluit, interne notitie, bericht aan de sollicitant), vergrendeling "bezig door" en de incheck-tablet met foto. Alles zit in de inzage en gaat mee bij wissen. |
| 27 september 2026 | Seintje buiten het seizoen: wie nog niet solliciteerde kan bij een gesloten aanmelding om één bericht vragen (voornaam, e-mail, bron; dubbele bevestiging; onbevestigd 7 dagen, daarna tot het bericht en maximaal 12 maanden). Tabel `werving_seintje`; privacyverklaring bijgewerkt. |
| 27 september 2026 | Drie punten uit "Nog vast te leggen" zijn gebouwd en naar hun hoofdstuk verplaatst: de knop "Bezwaar vastleggen" voor de AI-analyse, naam en geboortedatum weg na het vernietigen van een bewaard loondossier, en de wachtrijtekst van niet-gelogde berichten leeg na verzending. Lidmaatschap van minderjarigen: de ouder of verzorger tekent, de SEPA-machtiging tekent de rekeninghouder (in aanbouw). Privacyverklaring: de termijn van de gezondheidsgegevens volgt nu `bewaar.gezondheid_mnd`, en het tekenen van het lidmaatschap door de ouder staat erin. |
| 28 september 2026 | Nieuwe verwerking: productiekopie op de testomgeving (generale repetitie vóór elke uitrol), direct geanonimiseerd met `tools/anonimiseer_testkopie.php`; exportbestand direct weg. Interne verwerking bij dezelfde hoster, geen wijziging in de privacyverklaring nodig. |
| 1 oktober 2026 | Versie 2.145: de UTM-herkomst (bron, medium, campagne, variant van de advertentielink) wordt los bewaard bij elk bezoek en elke formulierstap (`pagina_events`) en bij de aanmelding (`sollicitaties`). Geen cookie, geen IP-adres; zelfde bewaartermijn als de rest (statistiek 13 maanden, bij de sollicitatie: volgt het dossier); zichtbaar in de inzage (`avg_inzage()`, sleutel `herkomst`). "Vergeet alle apparaten" logt nu ook open sessies elders uit. |

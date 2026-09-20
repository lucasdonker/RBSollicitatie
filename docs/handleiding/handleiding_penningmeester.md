# Handleiding penningmeester — Loondossiers

Versie 2.94.0 · september 2026

Deze handleiding is voor de penningmeester. Je gebruikt één scherm in het beheer: **Loondossiers**.
Daar staat per aangenomen strandwacht alles wat de loonadministratie nodig heeft. De rest van het
beheer zie je niet, en dat is de bedoeling: alleen jij en de admin kunnen deze stukken inzien.

## Inhoud

1. [Inloggen](#inloggen)
2. [Wat er in een loondossier zit](#wat-er-in-een-loondossier-zit)
3. [Wanneer je een melding krijgt](#wanneer-je-een-melding-krijgt)
4. [Het scherm Loondossiers](#het-scherm-loondossiers)
5. [Stukken downloaden](#stukken-downloaden)
6. [Een dossier verwerken](#een-dossier-verwerken)
7. [Bankpas vernietigen](#bankpas-vernietigen)
8. [Bewaartermijnen en verwijderen](#bewaartermijnen-en-verwijderen)
9. [Wat je nooit doet](#wat-je-nooit-doet)
10. [Veelgestelde vragen](#veelgestelde-vragen)

---

## Inloggen

**Waar:** `https://sollicitatie.paal13.com/admin/`  
**Wie:** je Authentik-account zit in de groep `Sol-Penningmeester`.

Kies "Inloggen via Authentik". Je komt in het beheer met alleen het menu-item **Loondossiers** en
(alleen lezen) **Sollicitanten**. Zie je meer of minder, dan klopt je groep in Authentik niet; meld
dat bij de admin.

## Wat er in een loondossier zit

Per aangenomen strandwacht verzamelt het portaal:

| Stuk | Hoe het binnenkomt | Bijzonderheden |
|---|---|---|
| Loonheffingsformulier | De strandwacht vult het officiële Belastingdienst-formulier in het portaal in en ondertekent digitaal met een verificatiecode (sms/WhatsApp/e-mail) | Verzegelde PDF; het BSN staat alleen in de PDF en in de kluis, nergens anders |
| Kopie identiteitsbewijs | Foto of scan; bij een ID-kaart voor- én achterkant, bij een paspoort alleen de fotopagina | Wettelijk verplicht, volledig en onafgeschermd |
| Kopie bankpas | Foto van de voorkant (naam en IBAN) | Alleen om het rekeningnummer te controleren; wordt na verwerking vernietigd |
| Inschrijving lidmaatschap | Alleen voor wie nog geen verenigingsaccount heeft; gaat als PDF naar de ledenservice | Staat ter info in het dossier |

Alles staat versleuteld in de kluis. Een dossier is **compleet** als loonheffingsformulier, kopie ID
en kopie bankpas binnen zijn.

## Wanneer je een melding krijgt

Zodra een dossier compleet is, krijg je één e-mail "Loondossier compleet" op het adres dat bij
Instellingen › Algemeen als penningmeester-adres staat. De stukken zitten **niet** in die mail; je
haalt ze op via het beheer. (Het bestuur kan instellen dat ze wél meegaan, maar dat raden we af.)

Krijg je de melding niet, dan kan een beheerder hem opnieuw sturen met de knop
"Melding opnieuw naar de penningmeester".

## Het scherm Loondossiers

**Waar:** Beheer › Loondossiers.

Bovenaan drie tellers: aangenomen, compleet, verwerkt. Daaronder per strandwacht een kaart:

- **Oranje rand:** dossier nog niet compleet; er ontbreken stukken.
- **Groene rand:** compleet, wacht op verwerking. Deze kaarten staan standaard opengeklapt.
- **Grijze rand:** verwerkt.

In een kaart zie je per stuk de bestandsnaam, de uploaddatum, de grootte en wanneer het voor het
laatst is gedownload. Onder "Tijdlijn loonheffing" staat het bewijsspoor van de ondertekening:
geopend, code verstuurd, code geaccepteerd, ondertekend, verzegeld, met tijdstip en kanaal.

Staat er bovenaan een rode melding over de kluis of het zegel, dan werkt het systeem niet goed;
waarschuw de admin. Zolang die melding staat kunnen strandwachten niets uploaden.

## Stukken downloaden

1. Klik op **Download** achter het stuk.
2. Op ID-kopieën en de bankpas staat een watermerk met je naam en de datum. Dat is bedoeld om
   doorsturen te ontmoedigen; verwijder het niet.
3. Elke download wordt vastgelegd (wie, wanneer, welk stuk) en het bestuur krijgt daar standaard
   een melding van. Dat is normaal en hoort zo.
4. Er geldt een grens van 30 downloads per uur.

Download alleen op een vertrouwd apparaat. Zet de stukken direct in de loonadministratie en
verwijder de gedownloade bestanden daarna van je computer en uit je downloadmap.

## Een dossier verwerken

Zodra de gegevens in de loonadministratie staan:

1. Klik in de kaart op **Verwerkt in de loonadministratie**.
2. Bevestig.

Wat er dan gebeurt:

- De strandwacht kan de stukken niet meer wijzigen of verwijderen.
- De bankpasfoto wordt na de ingestelde termijn (standaard 30 dagen) automatisch vernietigd.
- Het loonheffingsformulier en de ID-kopie blijven bewaard tot de wettelijke termijn is verstreken
  (standaard 5 jaar na het einde van het dienstjaar) en worden dan automatisch vernietigd.
- De strandwacht kan zijn account niet meer zelf verwijderen (wettelijke bewaarplicht gaat voor);
  het portaal legt dat uit.

Markeer een dossier pas als verwerkt als het écht verwerkt is. Terugdraaien kan niet zelf; vraag
dan de admin.

## Bankpas vernietigen

Wil je de bankpasfoto eerder weg dan de automatische termijn, klik dan op **Vernietigen** achter de
bankpas en bevestig. Dit kan alleen voor de bankpas; de andere stukken vallen onder de bewaarplicht.

## Bewaartermijnen en verwijderen

| Stuk | Termijn | Wie stelt in |
|---|---|---|
| Loonheffingsformulier en kopie ID | 5 jaar na einde van het dienstjaar | Bestuur, Instellingen › Bewaartermijnen |
| Kopie bankpas | 30 dagen na "verwerkt" | Bestuur, Instellingen › Bewaartermijnen |

De opschoning draait automatisch. Wat is vernietigd staat in het Logboek onder "opschoning".

Trekt een strandwacht zich terug vóórdat het dossier verwerkt is, dan kan hij zijn gegevens zelf
verwijderen en verdwijnen de stukken mee. Gaat het dienstverband niet door nádat je het dossier
verwerkt hebt, dan kan alleen een beheerder het dossier verwijderen, met een aparte bevestiging.

## Wat je nooit doet

- Stukken per e-mail of WhatsApp doorsturen, ook niet naar jezelf. Gebruik het beheer.
- Een BSN of IBAN in een spreadsheet, notitie of chat zetten buiten de loonadministratie.
- Je inlog delen. Elke download staat op jouw naam.
- Screenshots maken van ID-kopieën.

## Veelgestelde vragen

**Een dossier blijft oranje terwijl de strandwacht zegt dat alles is geüpload.**  
Kijk welk stuk ontbreekt. Bij een ID-kaart moet ook de achterkant erbij; bij een paspoort niet. Het
loonheffingsformulier telt pas mee na de ondertekening met verificatiecode.

**De strandwacht krijgt geen verificatiecode.**  
De code gaat naar het telefoonnummer uit het dossier (sms, anders WhatsApp, anders e-mail). Laat
het nummer controleren bij "Mijn gegevens" in het portaal. Blijft het misgaan, dan is dat iets
voor de admin (foutenlog).

**Ik zie een watermerk op de ID-kopie. Kan dat weg?**  
Nee. Het watermerk is bewust en beschermt tegen misbruik van de kopie. Voor de loonadministratie
is een kopie mét watermerk gewoon geldig.

**Het formulier van vorig jaar staat er niet meer.**  
Het scherm toont het actieve seizoen. Stukken van eerdere seizoenen blijven in de kluis tot de
bewaartermijn is verstreken; vraag de admin als je ze nodig hebt.

**Is de PDF echt door de strandwacht ondertekend?**  
De laatste pagina van de PDF is het certificaat van ondertekening: wie, wanneer, vanaf welk
IP-adres, met welke code naar welk nummer, en een controlegetal. Acrobat toont bij het zegel dat het
document sinds de ondertekening niet is gewijzigd (met een melding dat de uitgever niet in de lijst
van Adobe staat; dat is normaal voor een eigen zegel).

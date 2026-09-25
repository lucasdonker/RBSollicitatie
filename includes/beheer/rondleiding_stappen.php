<?php
/**
 * Paal 13 — de inhoud van de rondleidingen: welke stap, welke tekst, welk element.
 *
 * Alleen gegevens; wat ermee gebeurt staat in rondleiding.php, het tonen in
 * assets/js/rondleiding.js. Per scherm:
 *   titel     wat er in de stapkaart staat
 *   bestand   het script in admin/ — daarop kiest "Rondleiding van dit scherm" zijn stappen
 *   module    recht dat nodig is om het scherm te zien ("module" of "module:actie", gelijk
 *             aan de vereis_recht() bovenin dat scherm)
 *   hulp      de sleutel in hulp_registry(): zo hoort het scherm bij een hoofdstuk van de
 *             handleiding ("Lees meer", "Laat zien op het scherm")
 *   stappen   doel = CSS-selector op het echte scherm, of null voor een venster in het
 *             midden. Ontbreekt het element (lege lijst), dan staat de stap ook in het midden.
 *
 * Een scherm omgebouwd? Draai docs/ai/harnas/rondleiding.ps1: die rendert elk scherm en
 * meldt welke selectors niets meer vinden.
 */

if (!function_exists('rl_schermen')) {

function rl_schermen(): array {
    return [
        'algemeen' => [
            'titel' => 'Het beheer in het kort', 'bestand' => 'index.php', 'module' => null, 'hulp' => 'dashboard',
            'alleen_route' => true,
            'stappen' => [
                ['doel' => null, 'titel' => 'Welkom in het beheer',
                 'tekst' => 'Links het menu, bovenin de topbalk met zoeken, het seizoen en hulp. Deze rondleiding laat in een minuut zien waar alles zit.'],
                ['doel' => '#zijbalk', 'titel' => 'Het menu',
                 'tekst' => 'Hier staan alle onderdelen die jouw rol mag zien; wat je niet mag, staat er ook niet. Een getal achter een item betekent dat er iets op je wacht. Op een telefoon open je het menu met de knop met drie streepjes.'],
                ['doel' => '.bh-nav-groepkop', 'titel' => 'Vier groepen',
                 'tekst' => 'Vandaag staat los bovenaan. Daaronder Kandidaten, Inzet & loon, Communicatie en Systeem. Een groep zonder onderdelen voor jouw rol verdwijnt.'],
                ['doel' => '#zoekKnop', 'titel' => 'Zoeken',
                 'tekst' => 'Zoek een persoon op naam of e-mailadres, of een scherm of instelling. Sneltoets: Ctrl K. Typ minstens twee letters, kies met de pijltjes en open met Enter.'],
                ['doel' => '.bh-seizoen', 'titel' => 'Het huidige seizoen',
                 'tekst' => 'Met welk seizoen het beheer nu werkt, en of de aanmelding open of gesloten is. Openen en sluiten doe je bij Systeem › Seizoen.'],
                ['doel' => '#hulpknop', 'titel' => 'Het vraagteken',
                 'tekst' => 'Korte uitleg van het scherm waar je bent, met een link naar de handleiding, de rondleiding van dit scherm en alle rondleidingen.'],
                ['doel' => '.bh-zijbalk-voet', 'titel' => 'Jij, onderin',
                 'tekst' => 'Je naam, je rol en hoe je bent ingelogd. Klik op je naam voor Mijn profiel (weergavenaam, wachtwoord, donker thema). Hier staan ook Mijn sollicitatie (als je zelf solliciteert) en Uitloggen.'],
            ],
        ],

        'vandaag' => [
            'titel' => 'Vandaag', 'bestand' => 'index.php', 'module' => null, 'hulp' => 'dashboard',
            'stappen' => [
                ['doel' => null, 'titel' => 'Vandaag',
                 'tekst' => 'Het startscherm opent op de vraag wat er nu moet gebeuren. Kom hier elke keer eerst.'],
                ['doel' => '.bh-seizoenbalk', 'titel' => 'Waar staat het seizoen',
                 'tekst' => 'Zes fases van werving tot aanname. Groen is geweest, oranje is nu, grijs komt nog. Het systeem rekent de fase uit met de datums van het seizoen.'],
                ['doel' => '.bh-hoofdtaak', 'titel' => 'De taak van nu',
                 'tekst' => 'De ene taak die in deze fase telt, met hoeveel er al gedaan is en een knop ernaartoe. Staat er Niets te doen, dan is dat goed nieuws.'],
                ['doel' => '.bh-stand', 'titel' => 'Waar staat iedereen',
                 'tekst' => 'Het aantal kandidaten per fase. Klik op een staaf om die mensen in de sollicitantenlijst te zien.'],
                ['doel' => '.bh-looptlijst', 'titel' => 'Loopt ondertussen',
                 'tekst' => 'Wat je moet weten maar nu niet hoeft te doen, zoals ondertekeningen die langer dan 3 dagen openstaan.'],
                ['doel' => '.bh-systeem', 'titel' => 'Systeemstatus',
                 'tekst' => 'Groen is in orde. Rood met Aandacht nodig betekent mailfouten, een volle wachtrij of een taakronde die niet draait; meld dat bij de beheerder.'],
            ],
        ],

        'seizoen' => [
            'titel' => 'Seizoen', 'bestand' => 'seizoen.php', 'module' => 'seizoen:bewerken', 'hulp' => 'seizoen',
            'stappen' => [
                ['doel' => null, 'titel' => 'Het seizoen',
                 'tekst' => 'Eén keer per jaar: aanmaken, datums invullen, openen en aan het eind sluiten.'],
                ['doel' => '.bh-seizoenkaart', 'titel' => 'Open of gesloten',
                 'tekst' => 'Hier zet je de aanmelding aan of uit. Je ziet eerst een scherm dat zegt wat er gebeurt.'],
                ['doel' => '#sz_werk_start', 'titel' => 'Werkperiode',
                 'tekst' => 'Zonder eerste en laatste werkdag kunnen sollicitanten hun beschikbaarheid niet invullen.'],
                ['doel' => '#sz_sd_datum', 'titel' => 'Selectiedag en trainingsweekend',
                 'tekst' => 'Datum, tijden, adres en wat meenemen. Deze gegevens staan in de mails en in het portaal; herinneringen rekenen ermee.'],
                ['doel' => '.bh-stappen', 'titel' => 'Waar sta je?',
                 'tekst' => 'Wat al af is en wat nu aan de beurt is. Handig, want dit doe je maar één keer per jaar.'],
                ['doel' => '#sz_nieuw', 'titel' => 'Nieuw seizoen',
                 'tekst' => 'Sluit eerst het oude seizoen. Een nieuw seizoen begint gesloten; wie eerder solliciteerde wordt herkend als terugkerend.'],
            ],
        ],

        'sollicitanten' => [
            'titel' => 'Sollicitanten', 'bestand' => 'sollicitanten.php', 'module' => 'sollicitanten', 'hulp' => 'sollicitanten',
            'stappen' => [
                ['doel' => null, 'titel' => 'De sollicitantenlijst',
                 'tekst' => 'Alle kandidaten van het huidige seizoen. Klik op een naam om het dossier te openen.'],
                ['doel' => '.bh-tabs', 'titel' => 'Fase-tabs',
                 'tekst' => 'Kies een fase om alleen die mensen te zien. Een tab zonder mensen verdwijnt; Alle toont iedereen.'],
                ['doel' => '.bh-filterbalk', 'titel' => 'Zoeken en filteren',
                 'tekst' => 'Zoek op naam of e-mail, of filter op terugkerend en op het kennismakingsformulier. Wis filters brengt je terug naar de hele lijst.'],
                ['doel' => '.bh-filter-acties', 'titel' => 'Toevoegen en exporteren',
                 'tekst' => 'Toevoegen voert handmatig een sollicitant in. Exporteren downloadt het hele seizoen als Excel-bestand.'],
                ['doel' => '.bh-kolomkiezer', 'titel' => 'Kolommen',
                 'tekst' => 'Zet extra kolommen aan, zoals telefoon of velden uit het kennismakingsformulier. Je keuze blijft staan tot het einde van het seizoen.'],
                ['doel' => '#tabel-sollicitanten', 'titel' => 'Actie en vinkjes',
                 'tekst' => 'De knop in de kolom Actie zet de volgende stap, zoals Uitnodigen SD. Vink meerdere rijen aan voor de bulkbalk onderaan. Eerst zie je altijd wat er gebeurt en of er bericht uitgaat.'],
                ['doel' => '.bh-paginering', 'titel' => 'Bladeren',
                 'tekst' => 'Kies hoeveel regels je per pagina ziet en blader met Vorige en Volgende.'],
            ],
        ],

        'sollicitant' => [
            'titel' => 'Het dossier', 'bestand' => 'sollicitant.php', 'module' => 'sollicitanten', 'hulp' => 'sollicitant',
            'vereist' => 'sollicitant',
            'stappen' => [
                ['doel' => null, 'titel' => 'Het dossier',
                 'tekst' => 'Alles over één sollicitant op een eigen pagina. Het eerste tabblad geeft alles wat je nodig hebt om te beslissen.'],
                ['doel' => '.bh-kruimels', 'titel' => 'Terug en verder',
                 'tekst' => 'Terug naar de lijst met je filters nog aan. Met Vorige en Volgende loop je door dezelfde selectie.'],
                ['doel' => '.bh-dossierkop', 'titel' => 'Wie is dit',
                 'tekst' => 'Naam, fase, leeftijd en contactgegevens. Een oranje melding waarschuwt rond de leeftijdsgrens van 16.'],
                ['doel' => '.bh-dossierkop-acties', 'titel' => 'De volgende stap',
                 'tekst' => 'De knop zet de volgende stap in de procedure. Je ziet eerst wat er gebeurt en of er bericht uitgaat, en kunt kiezen om zonder bericht door te voeren. Daarnaast Bericht en Bellen.'],
                ['doel' => '.bh-dossiertabs', 'titel' => 'Tabbladen',
                 'tekst' => 'Formulier, beschikbaarheid, selectiedag, ondertekeningen en tijdlijn. Een tabblad zonder inhoud verschijnt niet.'],
                ['doel' => '.bh-fasekeuze', 'titel' => 'Fase zetten of corrigeren',
                 'tekst' => 'Klik op een fase om die te zetten. Je krijgt eerst een vraag, met de keuze om zonder bericht door te voeren.'],
                ['doel' => '[id^="notities-lijst-"]', 'titel' => 'Notities',
                 'tekst' => 'Interne opmerkingen van het bestuur, zichtbaar voor iedereen met toegang tot het dossier. De sollicitant ziet ze niet.'],
            ],
        ],

        'selectiedag' => [
            'titel' => 'Selectiedag', 'bestand' => 'selectie_indeling.php', 'module' => 'selectiedag', 'hulp' => 'selectiedag',
            'stappen' => [
                ['doel' => null, 'titel' => 'De selectiedag',
                 'tekst' => 'Vijf stappen voor één dag: groepen, inchecken, beoordelen, nabespreken en bellen.'],
                ['doel' => '.bh-substappen', 'titel' => 'De stappen',
                 'tekst' => 'Wissel hier tussen de stappen. Nabespreking en Bellijst zijn bestuurswerk en zie je alleen met bewerkrecht.'],
                ['doel' => '.bh-indeel-balk', 'titel' => 'Groepen maken',
                 'tekst' => 'Stel het aantal groepen in en laat het systeem een voorstel maken, gemengd op leeftijd en geslacht, met de terugkerende lifeguards verspreid. Een nieuw voorstel overschrijft de bestaande indeling.'],
                ['doel' => '.bh-groepgrid', 'titel' => 'Slepen en koppels',
                 'tekst' => 'Sleep kaartjes tussen de groepen, of kies op het kaartje een groep bij Naar groep (werkt ook met toetsenbord en op een tablet). Klik op een naam om aan te geven wie niet bij elkaar mogen; botst er een koppel, dan staat dat op het kaartje.'],
                ['doel' => '.bh-substappen a[href$="selectie_inchecken.php"]', 'titel' => 'Inchecken',
                 'tekst' => 'Maak hier de toegangscode en tablet-link voor de ingang. Wie is ingecheckt, gaat meteen naar Selectiedag geweest.'],
                ['doel' => '.bh-substappen a[href$="/selectiedag.php"]', 'titel' => 'Beoordelen',
                 'tekst' => 'Op je telefoon: per kandidaat een cijfer van 1 tot 5 per criterium en observaties. Tikken is opslaan; nog eens op hetzelfde cijfer tikken wist het. Begeleiders kunnen hier ook inchecken en beoordelen.'],
                ['doel' => '.bh-substappen a[href$="selectie_nabespreking.php"]', 'titel' => 'Nabespreken en bellen',
                 'tekst' => 'Besluit per kandidaat Door, Twijfel of Niet. Bel daarna iedereen via de bellijst; pas bij doorvoeren gaat het bericht uit.'],
            ],
        ],

        'trainingsweekend' => [
            'titel' => 'Trainingsweekend', 'bestand' => 'trainingsweekend.php', 'module' => 'trainingsweekend', 'hulp' => 'trainingsweekend',
            'stappen' => [
                ['doel' => null, 'titel' => 'Het trainingsweekend',
                 'tekst' => 'Zes stappen: voorbereiden, groepen, inchecken, beoordelen, en voor het bestuur de evaluatie en de gesprekken.'],
                ['doel' => '.bh-substappen a[href$="stap=voorbereiden"]', 'titel' => 'Voorbereiden',
                 'tekst' => 'Wie komt er, wie kan niet (met hun opmerking), en wie mist nog beschikbaarheid.'],
                ['doel' => '.bh-substappen a[href$="stap=groepen"]', 'titel' => 'Groepen',
                 'tekst' => 'Net als op de selectiedag: laat een voorstel maken en schuif daarna zelf. Terugkerende lifeguards worden verspreid. Wie niet samen mag, is dezelfde lijst als op de selectiedag.'],
                ['doel' => '.bh-substappen a[href$="stap=inchecken"]', 'titel' => 'Inchecken',
                 'tekst' => 'Hier staat de link en code voor de tablet bij de ingang. Sollicitanten melden zich daar en zien hun groep. Lukt dat niet, dan vink je ze hier af. Laatkomers kun je altijd nog inchecken.'],
                ['doel' => '.bh-substappen a[href$="stap=beoordelen"]', 'titel' => 'Beoordelen',
                 'tekst' => 'Op je telefoon: kies iemand, schrijf op wat je zag (positief, neutraal of aandacht). Bovenaan staat wie het bestuur extra aandacht vraagt, onderaan de terugkerende lifeguards. Observaties staan per dag.'],
                ['doel' => '.bh-substappen a[href$="stap=evaluatie"]', 'titel' => 'Evaluatie',
                 'tekst' => 'Voor het bestuur: per persoon alvast sowieso aannemen, twijfel of sowieso afwijzen. Alleen twijfel zien de beoordelaars, met jullie vraag erbij.'],
                ['doel' => '.bh-substappen a[href$="stap=gesprekken"]', 'titel' => 'Gesprekken',
                 'tekst' => 'Voor het bestuur: in willekeurige volgorde, met alles over de persoon bij elkaar. Aannemen of afwijzen gaat meteen door, met jullie bericht in de mail. Twijfel blijft staan.'],
            ],
        ],

        'ondertekeningen' => [
            'titel' => 'Ondertekeningen', 'bestand' => 'ondertekeningen.php', 'module' => 'ondertekeningen', 'hulp' => 'ondertekeningen',
            'stappen' => [
                ['doel' => null, 'titel' => 'Ondertekeningen',
                 'tekst' => 'Wie moet nog tekenen en wat is al binnen. Het scherm opent op Nog open, want dat is het werk.'],
                ['doel' => '.bh-tabs', 'titel' => 'Open of compleet',
                 'tekst' => 'Wissel tussen Alle, Nog open en Compleet.'],
                ['doel' => '.bh-ondkaart', 'titel' => 'Per persoon',
                 'tekst' => 'Een kaart per sollicitant. Klik op de naam voor het dossier; ouder tekent mee betekent dat de sollicitant nog geen 18 is.'],
                ['doel' => '.bh-ondkaart-voortgang', 'titel' => 'Hoe ver',
                 'tekst' => 'Hoeveel documenten van deze persoon al getekend zijn.'],
                ['doel' => '.bh-ond-acties', 'titel' => 'Bekijken of link kopiëren',
                 'tekst' => 'Getekend: Bekijken opent de verzegelde PDF. Open: Link kopiëren, om zelf te sturen als de mail niet aankwam.'],
            ],
        ],

        'beschikbaarheid' => [
            'titel' => 'Beschikbaarheid', 'bestand' => 'beschikbaarheid.php', 'module' => 'beschikbaarheid', 'hulp' => 'beschikbaarheid',
            'stappen' => [
                ['doel' => null, 'titel' => 'Wie kan wanneer werken',
                 'tekst' => 'Dit scherm laat per week zien wie er beschikbaar is. Je beoordeelt hier verzoeken om minder dagen en corrigeert een week als iemand iets anders doorgeeft.'],
                ['doel' => '.bh-verzoeklijst', 'titel' => 'Openstaande verzoeken',
                 'tekst' => 'Verzoeken om in bepaalde weken minder dagen te werken. Klik op Goedkeuren of Afwijzen; de sollicitant krijgt automatisch bericht.', 'module' => 'beschikbaarheid'],
                ['doel' => '.bh-actiekaart-zacht', 'titel' => 'Nog voorlopig',
                 'tekst' => 'Wie de beschikbaarheid nog niet definitief heeft gemaakt, met de deadline. Herinneringen gaan vanzelf; je hoeft niemand na te bellen.'],
                ['doel' => '.bh-dekgrafiek', 'titel' => 'Dekking per week',
                 'tekst' => 'Per week het aantal mensen tegenover de norm uit Posten & bezetting. In voor- en naseizoen telt alleen het weekend.'],
                ['doel' => '.bh-raster', 'titel' => 'Het raster',
                 'tekst' => 'Een rij per sollicitant, een kolom per week. Klik op een cel om die week te corrigeren, altijd met een reden.'],
                ['doel' => '.bh-raster-legenda', 'titel' => 'Wat de kleuren betekenen',
                 'tekst' => 'Hele week, alleen weekend, minder dagen of een correctie van het bestuur. Onderaan per week het totaal en hoeveel het weekend dekken.'],
                ['doel' => '#hulpknop', 'titel' => 'Meer uitleg',
                 'tekst' => 'Het vraagteken geeft de korte uitleg van dit scherm en een link naar de handleiding.'],
            ],
        ],

        'dekking' => [
            'titel' => 'Dekking', 'bestand' => 'dekking.php', 'module' => 'dekking', 'hulp' => 'dekking',
            'stappen' => [
                ['doel' => null, 'titel' => 'Zijn de posten te bemannen?',
                 'tekst' => 'Dekking kijkt niet naar aantallen maar naar competenties: passen de beschikbare mensen op de rollen van elke post? Daarvoor zijn Authentik en Posten & bezetting nodig.'],
                ['doel' => '.bh-blokkade', 'titel' => 'Authentik niet gekoppeld',
                 'tekst' => 'Zonder competenties uit Authentik kan de dekking niet worden berekend. Koppel het via de link in deze melding.'],
                ['doel' => '.bh-dekkop', 'titel' => 'De stand in één zin',
                 'tekst' => 'Bovenaan staat hoe de zomer ervoor staat, bijvoorbeeld welke weken een tekort hebben.'],
                ['doel' => '.bh-dektegels', 'titel' => 'Een tegel per week',
                 'tekst' => 'Gedekt, Krap of Tekort, met de posten. Klik op een week voor de rollen, de reserve en het bellijstje bij een tekort.'],
                ['doel' => '#hulpknop', 'titel' => 'Meer uitleg',
                 'tekst' => 'Het vraagteken geeft de korte uitleg van dit scherm en een link naar de handleiding.'],
            ],
        ],

        'loonplanning' => [
            'titel' => 'Loonplanning', 'bestand' => 'loonplanning.php', 'module' => 'loonplanning', 'hulp' => 'loonplanning',
            'stappen' => [
                ['doel' => null, 'titel' => 'Plannen en rekenen',
                 'tekst' => 'Hier zet je per persoon en per week hoeveel dagen iemand werkt, en zie je meteen wat dat kost en of er genoeg mensen zijn. Alles wordt direct opgeslagen.'],
                ['doel' => '.bh-lp-sclijst', 'titel' => 'Scenario\'s',
                 'tekst' => 'Je werkt altijd in een scenario. De ster is de actieve planning (die zie je ook in het TW-gesprek); met Kopie maken reken je een alternatief door.'],
                ['doel' => '.bh-lp-stand', 'titel' => 'Kosten en budget',
                 'tekst' => 'De loonkosten, het aantal werkdagen (en hoeveel onbetaald) en het budget.'],
                ['doel' => '.bh-dekgrafiek', 'titel' => 'Ingepland per week',
                 'tekst' => 'Hoeveel mensen je per week hebt ingepland, tegen de norm. Rood is te weinig.'],
                ['doel' => '.bh-lp-tabel', 'titel' => 'De planning',
                 'tekst' => 'Groot wat je plant, klein wat iemand opgaf ("kan 4"). Rood met een stippelrand: meer dan opgegeven. Klik een week om te plannen; je ziet dan per dag of iemand kan. Klik een naam voor de functie, de afspraak en het slot na het gesprek.'],
            ],
        ],

        'documenten' => [
            'titel' => 'Loondossiers', 'bestand' => 'documenten.php', 'module' => 'documenten', 'hulp' => 'documenten',
            'stappen' => [
                ['doel' => null, 'titel' => 'Welkom, penningmeester',
                 'tekst' => 'Dit is jouw werkscherm. Hier staat per aangenomen strandwacht wat de loonadministratie nodig heeft: het loonheffingsformulier, een kopie van het identiteitsbewijs en een kopie van de bankpas. Alleen jij en de admin kunnen dit zien.'],
                ['doel' => '#zijbalk .bh-nav-link.actief', 'titel' => 'Jouw menu',
                 'tekst' => 'Je ziet vier onderdelen: Vandaag, Sollicitanten (alleen lezen), Loonplanning en Loondossiers. Die laatste twee staan in de groep Inzet & loon.'],
                ['doel' => '.bh-avgkader', 'titel' => 'De vaste regels',
                 'tekst' => 'Dit kader staat er altijd. Elke download wordt vastgelegd, foto\'s van ID en bankpas krijgen een watermerk met jouw naam, en hier staan de bewaartermijnen. Download alleen op een apparaat dat je vertrouwt.'],
                ['doel' => '.bh-dosstand', 'titel' => 'Hoeveel wacht er op jou',
                 'tekst' => 'Deze regel zegt hoeveel dossiers compleet zijn en nog op verwerking wachten, met de tellers aangenomen, compleet en verwerkt.'],
                ['doel' => '.bh-doskaart', 'titel' => 'Eén kaart per strandwacht',
                 'tekst' => 'Klik op de naam om de kaart open te klappen. Oranje betekent dat er nog stukken ontbreken, groen dat het dossier klaar is om te verwerken, grijs dat het verwerkt is.'],
                ['doel' => '.bh-doskaart-voet', 'titel' => 'Downloaden en verwerken',
                 'tekst' => 'Download de stukken met de knoppen Download en PDF in de kaart. Staan de gegevens in de loonadministratie, klik dan op Verwerkt in de loonadministratie en bevestig. Dat is niet terug te draaien.'],
                ['doel' => '#hulpknop', 'titel' => 'Hulp nodig?',
                 'tekst' => 'Via dit vraagteken lees je een korte uitleg van dit scherm, met een link naar de volledige handleiding voor de penningmeester.'],
            ],
        ],

        'berichten' => [
            'titel' => 'Berichten', 'bestand' => 'berichten.php', 'module' => 'mailing', 'hulp' => 'berichten',
            'stappen' => [
                ['doel' => null, 'titel' => 'Berichten sturen',
                 'tekst' => 'Stuur een bericht aan één persoon, een selectie, een fase of een groep uit vorige seizoenen, per e-mail, WhatsApp/SMS of allebei.'],
                ['doel' => '.bh-tabs', 'titel' => 'Drie tabbladen',
                 'tekst' => 'Nieuw bericht om te schrijven, Verzonden voor wat er met elk bericht gebeurde, en Gepland voor berichten die automatisch op een vast moment uitgaan.'],
                ['doel' => '.bh-kanaalkiezer', 'titel' => 'Kanaal',
                 'tekst' => 'E-mail, WhatsApp/SMS of allebei. WhatsApp of sms volgt de voorkeur van de sollicitant; lukt het niet, dan gaat het per e-mail.'],
                ['doel' => '#eigenInhoud', 'titel' => 'Inhoud',
                 'tekst' => 'Schrijf het onderwerp, de e-mail en de korte tekst, of kies bovenaan een eigen sjabloon.'],
                ['doel' => '.bh-bericht-zij', 'titel' => 'Aan',
                 'tekst' => 'Zoek op naam of klik op een snelknop voor een hele fase, Oud-strandwachten of Seintje gevraagd. Rechts staan ook de variabelen.'],
                ['doel' => '#verzendKnop', 'titel' => 'Versturen',
                 'tekst' => 'Bekijk eerst het voorbeeld of stuur een testmail naar jezelf. Versturen vraagt altijd om bevestiging.', 'module' => 'mailing'],
            ],
        ],

        'geplande_berichten' => [
            'titel' => 'Berichten › Gepland', 'bestand' => 'geplande_berichten.php', 'module' => 'mailing:bewerken', 'hulp' => 'geplande_berichten',
            'stappen' => [
                ['doel' => null, 'titel' => 'Geplande berichten',
                 'tekst' => 'Regels als "tien dagen na het inleveren van het formulier: stuur het magazine". Ze draaien mee met de automatische taken.'],
                ['doel' => '.bh-tabs', 'titel' => 'Onder Berichten',
                 'tekst' => 'Gepland is het derde tabblad van Berichten. Je ziet het alleen met bewerkrecht op Berichten & sjablonen.', 'module' => 'mailing'],
                ['doel' => 'a[href="?nieuw=1"]', 'titel' => 'Nieuwe regel',
                 'tekst' => 'Kies een gebeurtenis, het aantal dagen erna of ervóór, een eigen sjabloon en voor welke fases. Maak het sjabloon eerst aan onder Sjablonen.'],
                ['doel' => '.bh-tabel', 'titel' => 'De regels',
                 'tekst' => 'Per regel of hij aanstaat en wanneer hij verstuurt. Met Proefdraaien zie je wie het bericht zou krijgen, zonder iets te versturen.'],
            ],
        ],

        'mail_templates' => [
            'titel' => 'Sjablonen', 'bestand' => 'mail_templates.php', 'module' => 'mailing:bewerken', 'hulp' => 'mail_templates',
            'stappen' => [
                ['doel' => null, 'titel' => 'Sjablonen',
                 'tekst' => 'Elk automatisch bericht heeft hier een sjabloon: de e-mail, de korte tekst voor WhatsApp/SMS en de samenvatting in het portaal.'],
                ['doel' => '.tmpl-lijst', 'titel' => 'Per categorie',
                 'tekst' => 'Kies links een sjabloon: fase-overgangen, taken, herinneringen, meldingen aan het bestuur, systeem en je eigen sjablonen.'],
                ['doel' => 'input[placeholder="Naam, bv. Magazine seizoen"]', 'titel' => 'Eigen sjabloon',
                 'tekst' => 'Maak een eigen sjabloon voor handmatige of geplande berichten.'],
                ['doel' => 'select[name="kanaalmodus"]', 'titel' => 'Kanaal',
                 'tekst' => 'Via welk kanaal dit bericht uitgaat, of Uit. Een wijziging wordt meteen opgeslagen.'],
                ['doel' => '#tmplForm', 'titel' => 'De tekst',
                 'tekst' => 'Onderwerp, hero, e-mailtekst, korte tekst en bijlagen. Sla op en controleer met Voorbeeld e-mail en Voorbeeld kort bericht.'],
                ['doel' => '.var-chip', 'titel' => 'Variabelen',
                 'tekst' => 'Klik op een variabele om hem in de e-mailtekst te zetten, zoals {{voornaam}} of {{selectiedag_datum}}.'],
            ],
        ],

        'paginas' => [
            'titel' => 'Wervingspagina\'s', 'bestand' => 'paginas.php', 'module' => 'paginas', 'hulp' => 'paginas',
            'stappen' => [
                ['doel' => null, 'titel' => 'Wervingspagina\'s',
                 'tekst' => 'Eigen pagina\'s per doelgroep met het aanmeldformulier erin, op /p/ gevolgd door het adres.'],
                ['doel' => '[data-open="pg-nieuw"]', 'titel' => 'Nieuwe pagina',
                 'tekst' => 'Geef een titel en een adres; daarna upload je het ontwerp als HTML-bestand en bekijk je het rapport.'],
                ['doel' => '.pg-lijst', 'titel' => 'Je pagina\'s',
                 'tekst' => 'Per pagina de status: Concept, Alleen beheerders of Gepubliceerd. Beheren opent de pagina, Bekijken de publieke versie.'],
                ['doel' => '.pg-cijfers', 'titel' => 'Cijfers',
                 'tekst' => 'Bezoekers, bezoeken, aanmeldingen en omzetting, anoniem geteld zonder cookies.'],
                ['doel' => '.pg-werving', 'titel' => 'Waar komen aanmeldingen vandaan',
                 'tekst' => 'Per bron hoeveel mensen zich aanmeldden en hoeveel er zijn aangenomen.'],
            ],
        ],

        'instellingen' => [
            'titel' => 'Instellingen', 'bestand' => 'instellingen.php', 'module' => 'instellingen', 'hulp' => 'instellingen',
            'stappen' => [
                ['doel' => null, 'titel' => 'Instellingen',
                 'tekst' => 'Alles wat je voor het hele systeem instelt, per onderwerp. Elk tabblad heeft een eigen knop Opslaan.'],
                ['doel' => '#instZoek', 'titel' => 'Filteren',
                 'tekst' => 'Typ een woord zoals "vakantietoeslag" of "sso" om het juiste tabblad te vinden.'],
                ['doel' => '.bh-instnav', 'titel' => 'De tabbladen',
                 'tekst' => 'Zes groepen: Organisatie, Selectie & seizoen, Inzet & loon, Berichten, Koppelingen en Privacy & onderhoud.'],
                ['doel' => '.bh-instnav-extra', 'titel' => 'Sjablonen voor mails',
                 'tekst' => 'Hier bewerk je de teksten van alle berichten.'],
                ['doel' => '.bh-instversie', 'titel' => 'Versie en systeem',
                 'tekst' => 'De geïnstalleerde versie, met de link naar Systeem & updates (alleen voor admin).'],
                ['doel' => '.bh-instinhoud', 'titel' => 'Het gekozen tabblad',
                 'tekst' => 'Rechts staat de inhoud van het tabblad. Vergeet niet op Opslaan te klikken.'],
            ],
        ],

        'beheerders' => [
            'titel' => 'Beheerders & rechten', 'bestand' => 'beheerders.php', 'module' => 'beheerders:bewerken', 'hulp' => 'beheerders',
            'stappen' => [
                ['doel' => null, 'titel' => 'Wie kan er in het beheer',
                 'tekst' => 'Iemand komt normaal binnen via Authentik; de groep bepaalt de rol. Een wachtwoord geef je alleen als noodingang.'],
                ['doel' => '.bh-tabs', 'titel' => 'Twee tabbladen',
                 'tekst' => 'Beheerders voor de accounts, Rollen & rechten voor wat elke rol mag.'],
                ['doel' => '.bh-bhlijst', 'titel' => 'De accounts',
                 'tekst' => 'Per account hoe iemand inlogt en wanneer voor het laatst. Hier stel je een wachtwoord in of zet je een account uit.'],
                ['doel' => '.bh-bhrij-rol', 'titel' => 'De rol',
                 'tekst' => 'Kies een rol en klik op Rol opslaan. Let op: bij een inlog via Authentik volgt de rol weer de groep.'],
                ['doel' => '.bh-beheerders-zij', 'titel' => 'Account aanmaken',
                 'tekst' => 'Alleen nodig voor iemand die niet via Authentik binnenkomt. Geef het tijdelijke wachtwoord zelf door.'],
            ],
        ],

        'rollen' => [
            'titel' => 'Rollen & rechten', 'bestand' => 'rollen.php', 'module' => 'beheerders:bewerken', 'hulp' => 'rollen',
            'stappen' => [
                ['doel' => null, 'titel' => 'Wat mag welke rol',
                 'tekst' => 'Per rol de Authentik-groep en per onderdeel of de rol mag lezen, bewerken of verwijderen. Admin heeft altijd alles.'],
                ['doel' => '.bh-rollijstje', 'titel' => 'De rollen',
                 'tekst' => 'Met Bewerken koppel je een rol aan een Authentik-groep. Systeemrollen kun je niet verwijderen.'],
                ['doel' => '.bh-nieuwerol', 'titel' => 'Nieuwe rol',
                 'tekst' => 'Een nieuwe rol begint met alle rechten uit. Zet daarna in de matrix aan wat nodig is.'],
                ['doel' => '.bh-matrix', 'titel' => 'De matrix',
                 'tekst' => 'Bewerken of verwijderen zet lezen vanzelf aan. Wees zuinig met Loondossiers en Logboek.'],
                ['doel' => '.bh-matrix-voet', 'titel' => 'Opslaan',
                 'tekst' => 'Rechten opslaan bewaart de hele matrix in één keer. Elke wijziging komt in het logboek.'],
            ],
        ],

        'logboek' => [
            'titel' => 'Logboek', 'bestand' => 'logboek.php', 'module' => 'logboek', 'hulp' => 'logboek',
            'stappen' => [
                ['doel' => null, 'titel' => 'Alle sporen in één tijdlijn',
                 'tekst' => 'Wie wat wanneer deed, verstuurde berichten, technische fouten, ondertekeningen, opschoning en mislukte logins. Gebruik het om iets te herleiden, niet om mensen te volgen.'],
                ['doel' => '.fase-tabs', 'titel' => 'Bronnen',
                 'tekst' => 'Alles, of één bron: Audit, Fouten, Berichten, Ondertekenen, AVG-opschoning of Mislukte logins.'],
                ['doel' => '#lbZoek', 'titel' => 'Zoeken',
                 'tekst' => 'Zoek in de details, bijvoorbeeld op het referentienummer van een fout.'],
                ['doel' => '#lbWie', 'titel' => 'Filters',
                 'tekst' => 'Filter op wie, de actie, de ernst en een periode. Klik in een regel op het object voor alles wat daarmee gebeurde.'],
                ['doel' => 'a[href*="logboek_export.php"]', 'titel' => 'Exporteren',
                 'tekst' => 'Download de huidige selectie als CSV. Ook de export wordt gelogd.'],
            ],
        ],

        'systeem' => [
            'titel' => 'Systeem & Updates', 'bestand' => 'systeem.php', 'module' => 'systeem:bewerken', 'hulp' => 'systeem',
            'stappen' => [
                ['doel' => null, 'titel' => 'Systeem & Updates',
                 'tekst' => 'De versie, de systeemcontrole, de herinneringen en de changelog. Kijk hier als iets vastloopt.'],
                ['doel' => 'button[title="Voer alle automatische database-migraties uit"]', 'titel' => 'Migraties uitvoeren',
                 'tekst' => 'Het systeem herstelt de database normaal zelf. Klopt er na een update toch iets niet, klik dan hier.'],
                ['doel' => '.ds-kaart:has(.bh-healthicoon)', 'titel' => 'Systeemgezondheid',
                 'tekst' => 'Acht controles, van cron en mailwachtrij tot kluis en zegel. Met Controleren & repareren wordt hersteld wat kan.'],
                ['doel' => '#herinneringen', 'titel' => 'Herinneringen',
                 'tekst' => 'Proefdraaien laat zien wie een herinnering zou krijgen, zonder iets te versturen.'],
            ],
        ],
    ];
}

/**
 * De routes per rol, op volgorde. Een scherm dat de rol niet mag zien valt er vanzelf
 * uit; een route zonder zichtbaar scherm verschijnt niet in het overzicht.
 */
function rl_routes(): array {
    return [
        'welkom' => [
            'titel' => 'Welkom in het beheer',
            'voor'  => 'Voor iedereen die net begint: het menu, zoeken, de hulp en het startscherm Vandaag.',
            'module' => null,
            'schermen' => ['algemeen', 'vandaag'],
            'afsluiting' => 'Dit was de basis. Volg nu de rondleiding voor jouw rol, of lees in de handleiding het hoofdstuk Aan de slag.',
        ],
        'bestuur' => [
            'titel' => 'Bestuur: het seizoen rond',
            'voor'  => 'Voor een nieuw bestuurslid: van seizoen opzetten via selectiedag en trainingsweekend tot aangenomen en getekend.',
            'module' => 'sollicitanten:bewerken',
            'schermen' => ['algemeen', 'vandaag', 'seizoen', 'sollicitanten', 'sollicitant', 'selectiedag',
                           'trainingsweekend', 'ondertekeningen'],
            'afsluiting' => 'Je hebt de hele procedure gezien. Wat je wanneer moet doen staat in de handleiding onder Het seizoen in stappen. Volg daarna de rondleiding Bestuur: inzet, berichten en werving.',
        ],
        'bestuur_inzet' => [
            'titel' => 'Bestuur: inzet, berichten en werving',
            'voor'  => 'Het tweede deel: beschikbaarheid, dekking en loonplanning, berichten en sjablonen, en de wervingspagina\'s.',
            'module' => 'mailing',
            'schermen' => ['beschikbaarheid', 'dekking', 'loonplanning', 'berichten', 'geplande_berichten',
                           'mail_templates', 'paginas'],
            'afsluiting' => 'Klaar. Twijfel je later over een scherm, klik dan rechtsboven op het vraagteken: daar staat de korte uitleg en de rondleiding van dat scherm.',
        ],
        'begeleider' => [
            'titel' => 'Begeleider: de selectiedag',
            'voor'  => 'Voor wie op de selectiedag of het trainingsweekend meedraait: waar je kijkt, afvinkt en observeert.',
            'module' => 'selectiedag',
            'schermen' => ['algemeen', 'selectiedag', 'trainingsweekend', 'dekking'],
            'afsluiting' => 'Meer hoef je niet te weten. Staat iets anders dan je verwacht, vraag het dan aan het bestuur.',
        ],
        'penningmeester' => [
            'titel' => 'Penningmeester: loondossiers en loonplanning',
            'voor'  => 'Voor de penningmeester: waar de loondossiers staan, hoe je ze downloadt en verwerkt, en de loonplanning met kosten en budget.',
            'module' => 'documenten',
            'schermen' => ['algemeen', 'documenten', 'loonplanning'],
            'afsluiting' => 'De volledige werkwijze, inclusief bewaartermijnen, staat in de Handleiding penningmeester.',
        ],
        'admin' => [
            'titel' => 'Beheerder: instellingen, toegang en systeem',
            'voor'  => 'Voor de admin: instellingen, beheerders en rollen, het logboek en de systeemcontrole.',
            'module' => 'beheerders:bewerken',
            'schermen' => ['instellingen', 'beheerders', 'rollen', 'logboek', 'systeem'],
            'afsluiting' => 'Voor alles rond de server (uitrollen, back-up, cron, incidenten) is er het Runbook systeembeheer.',
        ],
    ];
}

}

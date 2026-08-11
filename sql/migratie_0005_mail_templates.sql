-- Migratie 0005: Mail templates + extra seizoen tijdvelden

CREATE TABLE IF NOT EXISTS `mail_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `naam` VARCHAR(100) NOT NULL,
  `beschrijving` VARCHAR(300) DEFAULT NULL,
  `onderwerp` VARCHAR(300) NOT NULL,
  `body_html` TEXT NOT NULL,
  `gewijzigd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `seizoenen`
  ADD COLUMN IF NOT EXISTS `selectiedag_starttijd`     TIME DEFAULT NULL AFTER `locatie_selectiedag`,
  ADD COLUMN IF NOT EXISTS `selectiedag_verzameltijd`  TIME DEFAULT NULL AFTER `selectiedag_starttijd`,
  ADD COLUMN IF NOT EXISTS `tw_verzameltijd`           TIME DEFAULT NULL AFTER `locatie_tw`,
  ADD COLUMN IF NOT EXISTS `tw_begintijd`              TIME DEFAULT NULL AFTER `tw_verzameltijd`;

-- Standaard mail templates invoegen
INSERT IGNORE INTO `mail_templates` (`slug`, `naam`, `beschrijving`, `onderwerp`, `body_html`) VALUES

('uitnodiging_selectie', 'Uitnodiging selectiedag', 'Verstuurd wanneer een sollicitant uitgenodigd wordt voor de selectiedag.',
'Uitnodiging selectiedag – {{seizoen.naam}}',
'<p>Gefeliciteerd! We hebben je aanmelding beoordeeld en nodigen je graag uit voor de <strong>selectiedag</strong> van {{seizoen.naam}}.</p>

<p><strong>📅 Datum:</strong> {{selectiedag.datum}}<br>
<strong>📍 Locatie:</strong> {{selectiedag.locatie}}<br>
<strong>🕗 Verzamelen:</strong> {{selectiedag.verzameltijd}}<br>
<strong>🕗 Start:</strong> {{selectiedag.starttijd}}</p>

<p>Via het portaal kun je het "Beter leren kennen" formulier invullen. Zorg dat je dit vóór de selectiedag doet — we gebruiken het om je beter te leren kennen!</p>

[knop href="{{portaal.url}}"]Naar het portaal →[/knop]

<p>Heb je vragen? Stuur een mail naar <a href="mailto:{{bestuur.email}}">{{bestuur.email}}</a>.</p>
<p>Tot {{selectiedag.datum}}!</p>'),

('uitnodiging_tw', 'Uitnodiging trainingsweekend', 'Verstuurd wanneer een sollicitant uitgenodigd wordt voor het trainingsweekend.',
'Uitnodiging trainingsweekend – {{seizoen.naam}}',
'<p>Geweldig nieuws, {{sollicitant.voornaam}}! Je bent geslaagd voor de selectiedag en nodigen je uit voor het <strong>trainingsweekend</strong>.</p>

<p><strong>📅 Datum:</strong> {{tw.datum_start}} t/m {{tw.datum_eind}}<br>
<strong>📍 Locatie:</strong> {{tw.locatie}}<br>
<strong>🕗 Verzamelen:</strong> {{tw.verzameltijd}}</p>

<p>Log in op het portaal om je beschikbaarheid voor de rest van de zomer door te geven. We hebben dit nodig voor de planning.</p>

[knop href="{{portaal.url}}"]Beschikbaarheid doorgeven →[/knop]

<p>We zien je graag tijdens het trainingsweekend!</p>'),

('aangenomen', 'Aangenomen als lifeguard', 'Verstuurd wanneer een sollicitant officieel is aangenomen.',
'Welkom bij Paal 13! 🎉',
'<p>Fantastisch nieuws, {{sollicitant.voornaam}}! Je bent officieel aangenomen als lifeguard bij <strong>Reddingsbrigade Paal 13</strong> voor {{seizoen.naam}}.</p>

<p>We zijn blij dat je deel uitmaakt van ons team. Je ontvangt binnenkort meer informatie over de indeling, het rooster en wat je kunt verwachten.</p>

[info]Bewaar het portaal in je favorieten. Via {{portaal.url}} kun je altijd je informatie terugzien.[/info]

<p>Welkom aan boord! 🌊</p>'),

('afgewezen', 'Niet geselecteerd', 'Verstuurd wanneer een sollicitant niet verder gaat in de procedure.',
'Terugkoppeling selectieprocedure {{seizoen.naam}}',
'<p>Na zorgvuldige beoordeling hebben we helaas besloten je niet verder mee te nemen in de selectieprocedure voor {{seizoen.naam}}.</p>

<p>We hebben dit jaar veel aanmeldingen ontvangen en de keuze was moeilijk. Bedankt voor je enthousiasme en de moeite die je hebt gedaan.</p>

<p>Mocht je vragen hebben over onze beslissing, neem dan gerust contact op via <a href="mailto:{{bestuur.email}}">{{bestuur.email}}</a>.</p>

<p>We hopen je misschien in een volgend seizoen weer te zien!</p>'),

('otp_portaal', 'Inlogcode portaal', 'OTP-code mail voor het sollicitantenportaal.',
'Jouw inlogcode voor het Paal 13 portaal',
'<p>Je hebt een inlogcode aangevraagd voor het sollicitantenportaal van Reddingsbrigade Paal 13.</p>

[code]{{otp.code}}[/code]

<p>Deze code is <strong>15 minuten</strong> geldig. Vul hem in op de inlogpagina.</p>

[info]Heb jij deze code niet aangevraagd? Dan kun je deze e-mail veilig negeren.[/info]'),

('welkomstmail', 'Bevestiging aanmelding', 'Verstuurd bij het handmatig toevoegen van een sollicitant (optioneel).',
'Je aanmelding voor {{seizoen.naam}} is ontvangen',
'<p>Bedankt voor je aanmelding bij <strong>Reddingsbrigade Paal 13</strong> voor {{seizoen.naam}}!</p>

<p>We hebben je aanmelding ontvangen en zullen deze beoordelen. Als je wordt uitgenodigd voor de selectiedag, ontvang je van ons een e-mail.</p>

<p>In het tussentijd kun je je gegevens bekijken via het portaal:</p>

[knop href="{{portaal.url}}"]Naar het portaal →[/knop]

<p>Heb je vragen? Stuur een mail naar <a href="mailto:{{bestuur.email}}">{{bestuur.email}}</a>.</p>');

-- Extra: opmerkingen kolom voor beschikbaarheid
ALTER TABLE `beschikbaarheid` ADD COLUMN IF NOT EXISTS `opmerkingen` TEXT DEFAULT NULL AFTER `uitzonderingen`;

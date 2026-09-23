<?php
/**
 * Paal 13 — De privacyverklaring voor sollicitanten.
 *
 * Eén bron voor de tekst waarmee iemand bij het aanmelden akkoord gaat. De publieke pagina
 * (privacy.php) toont hem; het aanmeldformulier, de landingspagina's en de WordPress-plugin
 * linken ernaar via privacy_url().
 *
 * Twee regels:
 *  1. Wijzig je de inhoud, verhoog dan PRIVACY_VERSIE (includes/functions.php). Bij elke
 *     aanmelding wordt vastgelegd met welke versie iemand akkoord ging.
 *  2. Wat hier staat moet kloppen met wat het systeem doet. Daarom komen de bewaartermijnen
 *     uit dezelfde instellingen die includes/opschonen.php gebruikt, en verschijnt een
 *     alinea over een dienst (AI, WhatsApp, sms) alleen als die dienst aanstaat.
 *
 * De grondslagen volgen de lijn van de Autoriteit Persoonsgegevens voor werving en selectie:
 * een sollicitatie verwerken we niet op basis van toestemming (in een sollicitatie is
 * toestemming zelden vrij), maar omdat het nodig is om een overeenkomst te kunnen sluiten.
 * Het vinkje op het formulier betekent dus "ik heb dit gelezen", niet "ik geef toestemming".
 */

if (!function_exists('privacy_verklaring_html')) {

/** Waar de privacyverklaring staat. Instelbaar voor als hij ooit naar paal13.com verhuist. */
function privacy_url(): string {
    $standaard = (defined('APP_URL') ? APP_URL : '') . '/privacy.php';
    return function_exists('instelling') ? (instelling('privacy.url', '') ?: $standaard) : $standaard;
}

/** De verklaring als HTML (zonder omlijsting). */
function privacy_verklaring_html(): string {
    $in  = fn(string $k, string $d = '') => function_exists('instelling') ? (string)instelling($k, $d) : $d;
    $int = fn(string $k, int $d) => function_exists('instelling_int') ? instelling_int($k, $d) : $d;

    $org      = $in('site.naam', 'Reddingsbrigade Paal 13');
    $contact  = $in('mail.bestuur_email', 'bestuur@paal13.com');
    $weken    = $int('bewaar.afgewezen_weken', 4);
    $seintje  = $int('bewaar.seintje_mnd', 12);
    $onvolt   = $int('bewaar.onvoltooid_mnd', 3);
    $maillog  = $int('bewaar.maillog_mnd', 12);
    $loonjr   = $int('bewaar.loondocumenten_jaar', 5);
    $bankpas  = $int('bewaar.bankpas_na_verwerking_dagen', 30);
    $werkjr   = $int('bewaar.aangenomen_jaar', 2);
    $ai_blk   = $in('ai.api_key') !== '';
    $ai_besch = $ai_blk && $in('ai.beschikbaarheid_hulp', '0') === '1';
    $whatsapp = $in('wa.actief', '0') === '1';
    $sms      = $in('sms.actief', '0') === '1';
    $versie   = defined('PRIVACY_VERSIE') ? PRIVACY_VERSIE : '';

    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $mail = '<a href="mailto:' . $h($contact) . '">' . $h($contact) . '</a>';

    ob_start();
?>
<p class="af-pv-intro">Als je solliciteert als lifeguard bij <?= $h($org) ?>, hebben we gegevens van je nodig.
Hier lees je welke, waarvoor, wie ze ziet en hoe lang we ze bewaren. Ook lees je wat je zelf kunt
doen: je gegevens bekijken, laten verbeteren of laten verwijderen.</p>

<nav class="af-pv-inhoud" aria-label="Inhoud">
  <ol>
    <li><a href="#wie">Wie wij zijn</a></li>
    <li><a href="#welke">Welke gegevens we gebruiken</a></li>
    <li><a href="#waarom">Waarom we dat mogen</a></li>
    <li><a href="#jong">Als je jonger bent dan 18</a></li>
    <li><a href="#beslissen">Wie beslist over je sollicitatie</a></li>
    <li><a href="#delen">Wie je gegevens ziet</a></li>
    <li><a href="#bewaren">Hoe lang we ze bewaren</a></li>
    <li><a href="#beveiliging">Hoe we ze beveiligen</a></li>
    <li><a href="#cookies">Cookies</a></li>
    <li><a href="#rechten">Jouw rechten</a></li>
    <li><a href="#wijzigingen">Wijzigingen</a></li>
  </ol>
</nav>

<h2 id="wie">1. Wie wij zijn</h2>
<p><?= $h($org) ?> (Callantsoog en Groote Keeten) is verantwoordelijk voor je gegevens.
Vragen over privacy? Mail het bestuur: <?= $mail ?>.</p>
<p>Deze verklaring gaat alleen over solliciteren: het aanmeldformulier (hier of op paal13.com),
het sollicitantenportaal, de selectiedag, het trainingsweekend en wat je regelt als je wordt
aangenomen.</p>

<h2 id="welke">2. Welke gegevens we gebruiken</h2>
<h3>Als je je aanmeldt</h3>
<ul>
  <li>Je naam, geboortedatum, e-mailadres en telefoonnummer.</li>
  <li>Je geslacht, als je dat wilt invullen. We gebruiken dit alleen voor de slaapindeling in het trainingsweekend.</li>
  <li>Via welke pagina of advertentie je bij het formulier kwam.</li>
  <li>Wanneer je deze verklaring hebt gelezen, welke versie het was en vanaf welk IP-adres.
      Zo kunnen we laten zien dat je deze informatie hebt gekregen.</li>
  <li>Ben je 15? Dan ook de naam van je ouder of verzorger en het moment waarop die toestemming gaf.</li>
</ul>
<h3>Tijdens de sollicitatie</h3>
<ul>
  <li>Het kennismakingsformulier: je adres, opleiding, werkervaring, diploma's, motivatie en een foto.
      Wat je bij "bijzonderheden" invult, bepaal je zelf.</li>
  <li>Je beschikbaarheid: welke weken en dagen je kunt, en eventueel waarom je een dag niet kunt.</li>
  <li>Selectiedag en trainingsweekend: of je er was, in welke groep je zat, de beoordeling van de
      selectiecommissie en hun aantekeningen.</li>
  <li>De berichten die we je sturen en de vragen die je ons stelt in het portaal.</li>
</ul>
<h3>Als je wordt aangenomen</h3>
<ul>
  <li>Een gezondheidsverklaring en een intakeformulier. Daarin staan gezondheidsgegevens: dat zijn
      bijzondere persoonsgegevens, die extra bescherming krijgen.</li>
  <li>Voor je loon: je burgerservicenummer (BSN), rekeningnummer (IBAN), een kopie van je
      identiteitsbewijs, een kopie van je bankpas en de opgaaf voor de loonheffing.</li>
  <li>Voor het lidmaatschap van de vereniging: je adres, geboorteplaats, diploma's en
      rekeningnummer voor de contributie.</li>
  <li>Je handtekening onder deze documenten, met het moment, je IP-adres en je browser als bewijs
      dat jij het was.</li>
  <li>Of we foto's van je mogen gebruiken. Dat bepaal je zelf; je kunt het altijd weer intrekken.</li>
</ul>
<h3>Als je het portaal gebruikt</h3>
<ul>
  <li>Wanneer je inlogt, en als je "blijf ingelogd" aanzet: je browser en IP-adres bij dat apparaat.</li>
  <li>Een logboek van belangrijke handelingen (bijvoorbeeld: je hebt een formulier ingediend), met
      IP-adres en browser. Dat houden we bij voor de beveiliging.</li>
</ul>

<h2 id="waarom">3. Waarom we dat mogen</h2>
<p>We gebruiken je gegevens alleen als de wet (de AVG) daar een reden voor geeft. Per doel:</p>
<div class="af-pv-tabel">
<table>
  <thead><tr><th scope="col">Waarvoor</th><th scope="col">Waarom dat mag</th></tr></thead>
  <tbody>
    <tr><td>Je sollicitatie behandelen, contact met je houden, de selectiedag en het trainingsweekend</td>
        <td>Het is nodig om met je een arbeidsovereenkomst te kunnen sluiten. Jij vroeg daarom door te solliciteren (art. 6 lid 1 sub b AVG).</td></tr>
    <tr><td>Je beoordelen en de aantekeningen van de selectiecommissie</td>
        <td>Ons gerechtvaardigd belang: goede lifeguards kiezen, omdat zij verantwoordelijk zijn voor de veiligheid op het strand (art. 6 lid 1 sub f).</td></tr>
    <tr><td>Je loon uitbetalen en je arbeidsovereenkomst uitvoeren</td>
        <td>Nodig voor de overeenkomst (art. 6 lid 1 sub b).</td></tr>
    <tr><td>BSN, kopie identiteitsbewijs en loonheffing</td>
        <td>De wet verplicht ons hiertoe (art. 6 lid 1 sub c; Wet op de loonbelasting 1964).</td></tr>
    <tr><td>De gezondheidsverklaring en de gezondheidsvragen in de intake</td>
        <td>Je uitdrukkelijke toestemming (art. 9 lid 2 sub a). We vragen dit alleen om in het water veilig te kunnen werken en bij een noodgeval te weten wat er nodig is.</td></tr>
    <tr><td>Foto's van je gebruiken</td>
        <td>Je toestemming (art. 6 lid 1 sub a). Die kun je altijd intrekken.</td></tr>
    <tr><td>Een seintje sturen als we weer mensen zoeken, nadat je bent afgewezen</td>
        <td>Je toestemming. Die geef je met een aparte knop; je kunt hem altijd intrekken.</td></tr>
    <tr><td>Bewijs van je handtekening, het logboek en de beveiliging van het portaal</td>
        <td>Ons gerechtvaardigd belang, en onze plicht om je gegevens te beveiligen (art. 6 lid 1 sub f en art. 32).</td></tr>
    <tr><td>Anoniem tellen hoeveel mensen het formulier starten en afmaken</td>
        <td>Ons gerechtvaardigd belang: het formulier verbeteren. We gebruiken hier geen cookies en slaan niets op waarmee we jou herkennen.</td></tr>
  </tbody>
</table>
</div>
<p>Het vinkje bij het aanmelden betekent dat je deze verklaring hebt gelezen. Het is geen toestemming
voor alles wat hier staat: waar we je toestemming nodig hebben, vragen we die apart.</p>

<h2 id="jong">4. Als je jonger bent dan 18</h2>
<ul>
  <li>Je kunt je aanmelden vanaf 15 jaar.</li>
  <li>Ben je 15, dan moet je ouder of verzorger bij het aanmelden toestemming geven.</li>
  <li>Ben je jonger dan 18, dan vragen we de naam, het e-mailadres en het telefoonnummer van je
      ouder of verzorger. Je ouder of verzorger ondertekent dan de gezondheidsverklaring en de
      intake. De opgaaf voor de loonheffing onderteken je zelf.</li>
</ul>

<h2 id="beslissen">5. Wie beslist over je sollicitatie</h2>
<p>Over je sollicitatie beslissen altijd mensen: het bestuur en de selectiecommissie. Een computer
beslist nooit zelf of je wordt afgewezen.</p>
<p>Twee dingen gebeuren wel automatisch: bij een geboortedatum onder de 15 kun je het formulier
niet versturen, en het systeem kan je automatisch uitnodigen voor de selectiedag. Dat laatste
is nooit een afwijzing: wie niet automatisch wordt uitgenodigd, wordt door een mens bekeken.</p>
<?php if ($ai_blk): ?>
<p>Als je het kennismakingsformulier indient, laten we het lezen door een AI-dienst (Gemini, van
Google). Die schrijft voor het bestuur een korte beoordeling met een advies. Wat de AI krijgt:
je leeftijdsgroep en je antwoorden over opleiding, diploma's, werkervaring, motivatie,
beschikbaarheid en bijzonderheden. Je naam, adres, e-mailadres, telefoonnummer en foto krijgt de AI niet. Het advies is
een hulpmiddel. Een mens beslist altijd, en kan het advies negeren. Wil je niet dat een AI je
antwoorden leest? Mail dan <?= $mail ?> voordat je het formulier indient. Dan zetten we het
voor jou uit.</p>
<?php endif; ?>
<?php if ($ai_besch): ?>
<p>Bij je beschikbaarheid kun je in gewone woorden typen wanneer je kunt. Wil je dat, dan maakt
dezelfde AI-dienst er een voorstel van, en dat controleer je zelf. Die tekst bewaren we
<?= (int)$int('bewaar.ai_invoer_mnd', 3) ?> maanden. Je kunt je beschikbaarheid ook
zonder AI invullen.</p>
<?php endif; ?>

<h2 id="delen">6. Wie je gegevens ziet</h2>
<p>Binnen de vereniging ziet iedereen alleen wat nodig is voor zijn taak:</p>
<ul>
  <li><strong>Het bestuur en de selectiecommissie</strong> zien je sollicitatie, je beschikbaarheid en de beoordelingen.</li>
  <li><strong>De penningmeester</strong> ziet je loongegevens (BSN, IBAN, identiteitsbewijs, loonheffing). Alleen de penningmeester en de beheerder van het systeem kunnen die openen.</li>
  <li><strong>De ledenadministratie</strong> krijgt je inschrijving als lid.</li>
</ul>
<p>We verkopen je gegevens nooit. Buiten de vereniging werken we met deze partijen. Ze mogen je
gegevens alleen voor ons gebruiken:</p>
<ul>
  <li><strong>Cloud86</strong> (Nederland): de server waarop het systeem en de database draaien.</li>
  <li><strong>Onze e-mailprovider</strong>: voor de e-mails die we je sturen.</li>
<?php if ($whatsapp): ?>
  <li><strong>WhatsReach</strong> en <strong>WhatsApp</strong> (Meta): als je berichten via WhatsApp wilt krijgen. Wil je dat niet, kies dan in het portaal voor e-mail.</li>
<?php endif; ?>
<?php if ($sms): ?>
  <li><strong>Een sms-dienst</strong>: voor berichten en inlogcodes per sms.</li>
<?php endif; ?>
<?php if ($ai_blk): ?>
  <li><strong>Google</strong> (Gemini, zie hierboven).</li>
<?php endif; ?>
  <li><strong>Het Kadaster</strong> (PDOK): als je je postcode en huisnummer invult, vullen we je straat en woonplaats aan. Alleen je postcode en huisnummer gaan daarheen.</li>
  <li><strong>Google Fonts</strong>: het lettertype van het portaal komt van Google. Je browser maakt daarvoor contact met Google en geeft daarbij je IP-adres door.</li>
  <li><strong>De Belastingdienst</strong>: de loonheffing geven we door, omdat de wet dat verplicht.</li>
</ul>
<?php if ($ai_blk || $whatsapp): ?>
<p>Sommige van deze diensten gebruiken servers buiten de Europese Unie, vooral in de Verenigde
Staten. Dat mag alleen als je gegevens daar even goed beschermd zijn, bijvoorbeeld omdat het bedrijf
is aangesloten bij het EU-VS Data Privacy Framework, of met afspraken die de Europese Commissie
heeft goedgekeurd.</p>
<?php endif; ?>

<h2 id="bewaren">7. Hoe lang we ze bewaren</h2>
<p>We bewaren je gegevens niet langer dan nodig is. Daarna verwijderen we ze, of maken we ze zo
dat ze niet meer naar jou te herleiden zijn.</p>
<div class="af-pv-tabel">
<table>
  <thead><tr><th scope="col">Situatie</th><th scope="col">Hoe lang</th></tr></thead>
  <tbody>
    <tr><td>Je wordt niet aangenomen, of je stopt zelf met solliciteren</td>
        <td><?= $weken ?> weken na het besluit</td></tr>
    <tr><td>Je wordt afgewezen, maar vraagt om een seintje als we weer mensen zoeken</td>
        <td>Maximaal <?= $seintje ?> maanden. Je kunt het seintje eerder intrekken; dan verwijderen we je gegevens binnen <?= $weken ?> weken.</td></tr>
    <tr><td>Je begint met aanmelden, maar maakt het niet af</td>
        <td><?= $onvolt ?> maanden</td></tr>
    <tr><td>Je wordt aangenomen</td>
        <td>Zolang je bij ons werkt, en daarna nog <?= $werkjr ?> jaar na je laatste seizoen</td></tr>
    <tr><td>Gezondheidsverklaring</td>
        <td>Eén seizoen. Voor een nieuw seizoen vragen we een nieuwe verklaring. Word je afgewezen, dan verwijderen we hem direct.</td></tr>
    <tr><td>Opgaaf loonheffing en kopie identiteitsbewijs</td>
        <td><?= $loonjr ?> jaar na het jaar waarin je voor het laatst bij ons werkte (dat is de wettelijke termijn)</td></tr>
    <tr><td>Kopie van je bankpas</td>
        <td><?= $bankpas ?> dagen nadat de penningmeester je rekeningnummer heeft verwerkt</td></tr>
    <tr><td>Logboek en overzicht van verstuurde berichten</td>
        <td><?= $maillog ?> maanden. De tekst van de berichten zelf 3 maanden.</td></tr>
    <tr><td>Mislukte inlogpogingen</td><td>24 uur</td></tr>
    <tr><td>"Blijf ingelogd" op een apparaat</td><td>60 dagen, of korter als je uitlogt</td></tr>
  </tbody>
</table>
</div>
<p>We maken dagelijks een beveiligde back-up. Oude back-ups worden automatisch vervangen, dus
daaruit verdwijnen je gegevens ook, alleen iets later.</p>

<h2 id="beveiliging">8. Hoe we ze beveiligen</h2>
<ul>
  <li>Alle verbindingen zijn versleuteld (https).</li>
  <li>Je BSN, IBAN, identiteitsbewijs en bankpas bewaren we versleuteld, in een apart deel van het systeem.</li>
  <li>Voor documenten zoals de opgaaf voor de loonheffing moet je een code invullen die we naar je telefoon of e-mailadres sturen.</li>
  <li>Iedere beheerder heeft een eigen account en ziet alleen wat bij zijn taak hoort. Wie gevoelige gegevens bekijkt, wordt vastgelegd.</li>
  <li>Gaat er toch iets mis met je gegevens (een datalek)? Dan melden we dat binnen 72 uur bij de
      Autoriteit Persoonsgegevens. Is er een groot risico voor jou, dan laten we het jou ook weten.</li>
</ul>

<h2 id="cookies">9. Cookies</h2>
<p>We gebruiken alleen cookies die nodig zijn om het portaal te laten werken:</p>
<ul>
  <li>een cookie dat je ingelogd houdt terwijl je het portaal gebruikt. Na 8 uur vervalt het;</li>
  <li>een cookie voor "blijf ingelogd", maar alleen als je dat zelf aanvinkt (60 dagen).</li>
</ul>
<p>Wat je in het aanmeldformulier typt, bewaart je browser tijdelijk op je eigen apparaat. Zo ben
je niets kwijt als je per ongeluk terug gaat. Wij zien dat niet. Het verdwijnt als je het tabblad
sluit, of als je het formulier verstuurt. We gebruiken geen cookies om je te volgen of voor
advertenties.</p>

<h2 id="rechten">10. Jouw rechten</h2>
<p>Het zijn jouw gegevens. Je hebt het recht om:</p>
<ul>
  <li><strong>ze te bekijken</strong>: in het portaal kun je onder "Mijn gegevens" alles downloaden wat we van je hebben;</li>
  <li><strong>ze te laten verbeteren</strong> als er iets niet klopt;</li>
  <li><strong>ze te laten verwijderen</strong>: dat kan met een knop in het portaal. Gegevens die we van de wet moeten bewaren (zoals voor de loonheffing) blijven staan tot de wettelijke termijn voorbij is;</li>
  <li><strong>bezwaar te maken</strong> als we je gegevens gebruiken vanwege ons gerechtvaardigd belang, bijvoorbeeld tegen de AI-beoordeling;</li>
  <li><strong>je toestemming in te trekken</strong>, bijvoorbeeld voor foto's of het seintje. Wat we al deden voordat je de toestemming introk, blijft geldig;</li>
  <li><strong>te vragen dat we het gebruik tijdelijk stoppen</strong>, of <strong>je gegevens mee te nemen</strong> in een bestand dat je elders kunt gebruiken.</li>
</ul>
<p>Wil je iets wat niet in het portaal kan? Mail <?= $mail ?>. Binnen een maand krijg je een
antwoord. We kunnen je vragen te laten zien wie je bent, zodat we je gegevens niet aan iemand
anders geven.</p>
<p>Ben je het niet eens met hoe we met je gegevens omgaan? Zeg het ons eerst, dan zoeken we samen
een oplossing. Je kunt ook een klacht indienen bij de
<a href="https://autoriteitpersoonsgegevens.nl" rel="noopener">Autoriteit Persoonsgegevens</a>.</p>

<h2 id="wijzigingen">11. Wijzigingen</h2>
<p>Verandert er iets belangrijks aan deze verklaring, dan laten we het je weten. Bij je aanmelding
leggen we vast welke versie je hebt gelezen.</p>
<p class="af-pv-versie">Versie <?= $h($versie) ?></p>
<?php
    return (string)ob_get_clean();
}

}

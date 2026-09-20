<?php
/**
 * Partial: velden van de "Opgaaf gegevens voor de loonheffingen" (type loonheffing) — de vragen van het
 * officiële Belastingdienst-formulier in gewone taal. Veldnamen lh_*; de POST-tak in ondertekenen.php
 * zet ze op de juiste plek in het PDF-sjabloon (includes/loonheffing_pdf.php).
 * Verwacht: $v/$aan (waarden na fout of vooringevuld), $leeftijd, $sol.
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
?>
<!-- 1. Jouw gegevens (vraag 1a t/m 1f) -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('persoon', 18) ?>1. Jouw gegevens</span>
  <span class="ond-kaart-tekst">Grotendeels al ingevuld vanuit je sollicitatie. Controleer of alles klopt met je identiteitsbewijs.</span>
  <div class="ond-rij">
    <label class="ds-veld ond-veld" style="flex:2">
      <span class="ond-lbl">Achternaam <span class="ster">*</span></span>
      <input type="text" name="lh_achternaam" class="ds-input" value="<?= $v('lh_achternaam') ?>" autocomplete="family-name" required>
    </label>
    <label class="ds-veld ond-veld">
      <span class="ond-lbl">Voorletters <span class="ster">*</span></span>
      <input type="text" name="lh_voorletters" class="ds-input" value="<?= $v('lh_voorletters') ?>" placeholder="L.A." maxlength="12" required>
    </label>
  </div>
  <label class="ds-veld ond-veld">
    <span class="ond-lbl">Burgerservicenummer (BSN) <span class="ster">*</span></span>
    <input type="text" name="lh_bsn" class="ds-input ond-bsn" value="<?= $v('lh_bsn') ?>" inputmode="numeric" pattern="[0-9 ]*" maxlength="11" autocomplete="off" placeholder="9 cijfers" required>
    <span class="ds-veld-hint">Staat op je paspoort, identiteitskaart of rijbewijs. Wordt versleuteld bewaard en alleen gebruikt voor de loonadministratie.</span>
  </label>
  <label class="ds-veld ond-veld">
    <span class="ond-lbl">Straat en huisnummer <span class="ster">*</span></span>
    <input type="text" name="lh_adres" class="ds-input" value="<?= $v('lh_adres') ?>" autocomplete="street-address" required>
  </label>
  <div class="ond-rij">
    <label class="ds-veld ond-veld">
      <span class="ond-lbl">Postcode <span class="ster">*</span></span>
      <input type="text" name="lh_postcode" class="ds-input" value="<?= $v('lh_postcode') ?>" placeholder="1234 AB" maxlength="7" autocomplete="postal-code" required>
    </label>
    <label class="ds-veld ond-veld" style="flex:1.6">
      <span class="ond-lbl">Woonplaats <span class="ster">*</span></span>
      <input type="text" name="lh_woonplaats" class="ds-input" value="<?= $v('lh_woonplaats') ?>" autocomplete="address-level2" required>
    </label>
  </div>
  <label class="ds-veld ond-veld">
    <span class="ond-lbl">Geboortedatum</span>
    <input type="date" name="lh_geboortedatum" class="ds-input" value="<?= $v('lh_geboortedatum') ?>" required>
    <span class="ds-veld-hint">Klopt dit niet? Pas het aan bij Mijn gegevens of mail het bestuur.</span>
  </label>
</div>

<!-- 2. Loonheffingskorting (vraag 2a t/m 2e) -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('reddingsboei', 18) ?>2. Loonheffingskorting</span>
  <span class="ond-kaart-tekst">Met de loonheffingskorting houd je netto meer over. Je mag hem maar bij <strong>één</strong> werkgever of uitkeringsinstantie tegelijk laten toepassen. Heb je nog een andere baan of uitkering waar hij al wordt toegepast? Kies dan "Nee" (of zet hem daar stop).</span>
  <div class="ond-lijst">
    <label class="ond-radio"><input type="radio" name="lh_korting" value="ja"<?= $aan('lh_korting', 'ja') ?> required><span class="ond-radio-box"></span><span class="ond-radio-tekst"><strong>Ja</strong>, Paal 13 mag de loonheffingskorting toepassen (meestal de juiste keuze als dit je enige baan is).</span></label>
    <label class="ond-radio"><input type="radio" name="lh_korting" value="nee"<?= $aan('lh_korting', 'nee') ?>><span class="ond-radio-box"></span><span class="ond-radio-tekst"><strong>Nee</strong>, niet toepassen (een andere werkgever of uitkering past hem al toe).</span></label>
  </div>
  <label class="ds-veld ond-veld">
    <span class="ond-lbl">Vanaf welke datum <span class="ster">*</span></span>
    <input type="date" name="lh_vanaf" class="ds-input" value="<?= $v('lh_vanaf') ?>" required>
    <span class="ds-veld-hint">Meestal je eerste werkdag; die is alvast ingevuld.</span>
  </label>
  <label class="ond-check">
    <input type="checkbox" name="lh_begrip" value="1"<?= $aan('lh_begrip') ?> required>
    <span class="ond-check-box"><?= icoon('vink', 14) ?></span>
    <span class="ond-check-tekst">Ik begrijp dat ik de loonheffingskorting maar bij 1 werkgever of uitkeringsinstantie mag laten toepassen. Ik heb ook de toelichting gelezen.</span>
  </label>
  <span class="ond-kaart-tekst">Vraag 2d op het formulier (alleenstaande-ouderenkorting) geldt alleen bij een AOW-uitkering en blijft leeg.<?= ($leeftijd !== null && $leeftijd < 18) ? ' Ben je jonger dan 18? Vraag zo nodig je ouder of verzorger om mee te kijken; je ondertekent zelf.' : '' ?></span>
</div>

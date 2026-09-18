<?php
/**
 * Partial: velden van de inschrijving als lid (type lidmaatschap) — dezelfde vragen als het formulier op
 * paal13.com/vereniging/lid-worden, vooringevuld. Veldnamen lid_*; validatie in includes/lidmaatschap.php.
 * Verwacht: $v/$aan (waarden), $leeftijd, $std (voorinvulling, o.a. lid_diplomas), $na_fout.
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$_minderjarig = $leeftijd !== null && $leeftijd < 18;
$_dip_aan = $na_fout ? array_map('strval', (array)($_POST['lid_diplomas'] ?? [])) : (array)($std['lid_diplomas'] ?? []);
$_radio = function (string $naam, string $waarde, string $tekst, bool $req = false) use ($aan, $std, $na_fout): string {
    $checked = $na_fout ? $aan($naam, $waarde) : ((($std[$naam] ?? '') === $waarde) ? ' checked' : '');
    return '<label class="ond-radio"><input type="radio" name="' . h($naam) . '" value="' . h($waarde) . '"' . $checked . ($req ? ' required' : '') . '><span class="ond-radio-box"></span><span class="ond-radio-tekst">' . $tekst . '</span></label>';
};
$_check = function (string $naam, string $tekst, bool $req = false) use ($aan): string {
    return '<label class="ond-check"><input type="checkbox" name="' . h($naam) . '" value="1"' . $aan($naam) . ($req ? ' required' : '') . '><span class="ond-check-box">' . icoon('vink', 14) . '</span><span class="ond-check-tekst">' . $tekst . '</span></label>';
};
?>
<!-- 1. Persoonlijke gegevens -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('persoon', 18) ?>1. Persoonlijke gegevens</span>
  <span class="ond-kaart-tekst">Grotendeels al ingevuld vanuit je sollicitatie. Controleer en vul aan.</span>
  <div class="ond-rij">
    <label class="ds-veld ond-veld" style="flex:1.4"><span class="ond-lbl">Voornaam <span class="ster">*</span></span><input type="text" name="lid_voornaam" class="ds-input" value="<?= $v('lid_voornaam') ?>" autocomplete="given-name" required></label>
    <label class="ds-veld ond-veld" style="flex:.8"><span class="ond-lbl">Tussenvoegsel</span><input type="text" name="lid_tussenvoegsel" class="ds-input" value="<?= $v('lid_tussenvoegsel') ?>"></label>
  </div>
  <label class="ds-veld ond-veld"><span class="ond-lbl">Achternaam <span class="ster">*</span></span><input type="text" name="lid_achternaam" class="ds-input" value="<?= $v('lid_achternaam') ?>" autocomplete="family-name" required></label>
  <div class="ond-veld ds-veld">
    <span class="ond-lbl">Geslacht <span class="ster">*</span></span>
    <div class="ond-lijst">
      <?= $_radio('lid_geslacht', 'man', 'Man', true) ?>
      <?= $_radio('lid_geslacht', 'vrouw', 'Vrouw') ?>
      <?= $_radio('lid_geslacht', 'geen', 'Wil ik niet specificeren') ?>
    </div>
  </div>
  <div class="ond-rij">
    <label class="ds-veld ond-veld"><span class="ond-lbl">Geboortedatum <span class="ster">*</span></span><input type="date" name="lid_geboortedatum" class="ds-input" value="<?= $v('lid_geboortedatum') ?>" required></label>
    <label class="ds-veld ond-veld" style="flex:1.3"><span class="ond-lbl">Geboorteplaats <span class="ster">*</span></span><input type="text" name="lid_geboorteplaats" class="ds-input" value="<?= $v('lid_geboorteplaats') ?>" required></label>
  </div>
  <div class="ond-rij">
    <label class="ds-veld ond-veld" style="flex:2"><span class="ond-lbl">Straatnaam <span class="ster">*</span></span><input type="text" name="lid_straat" class="ds-input" value="<?= $v('lid_straat') ?>" autocomplete="address-line1" required></label>
    <label class="ds-veld ond-veld" style="flex:.8"><span class="ond-lbl">Huisnr. <span class="ster">*</span></span><input type="text" name="lid_huisnummer" class="ds-input" value="<?= $v('lid_huisnummer') ?>" required></label>
  </div>
  <div class="ond-rij">
    <label class="ds-veld ond-veld"><span class="ond-lbl">Postcode <span class="ster">*</span></span><input type="text" name="lid_postcode" class="ds-input" value="<?= $v('lid_postcode') ?>" placeholder="1234 AB" maxlength="7" autocomplete="postal-code" required></label>
    <label class="ds-veld ond-veld" style="flex:1.6"><span class="ond-lbl">Woonplaats <span class="ster">*</span></span><input type="text" name="lid_woonplaats" class="ds-input" value="<?= $v('lid_woonplaats') ?>" autocomplete="address-level2" required></label>
  </div>
  <div class="ond-rij">
    <label class="ds-veld ond-veld"><span class="ond-lbl">Telefoonnummer <span class="ster">*</span></span><input type="tel" name="lid_telefoon" class="ds-input" value="<?= $v('lid_telefoon') ?>" autocomplete="tel" required></label>
    <label class="ds-veld ond-veld" style="flex:1.4"><span class="ond-lbl">E-mail <span class="ster">*</span></span><input type="email" name="lid_email" class="ds-input" value="<?= $v('lid_email') ?>" autocomplete="email" required></label>
  </div>
</div>

<?php if ($_minderjarig): ?>
<!-- 2. Ouder / verzorger (alleen minderjarig) -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('ouders', 18) ?>2. Ouder / verzorger</span>
  <span class="ond-kaart-tekst">Omdat je jonger bent dan 18 vraagt de vereniging ook de gegevens van je ouder of verzorger.</span>
  <div class="ond-rij">
    <label class="ds-veld ond-veld"><span class="ond-lbl">Voornaam <span class="ster">*</span></span><input type="text" name="lid_ouder_voornaam" class="ds-input" value="<?= $v('lid_ouder_voornaam') ?>" required></label>
    <label class="ds-veld ond-veld"><span class="ond-lbl">Achternaam <span class="ster">*</span></span><input type="text" name="lid_ouder_achternaam" class="ds-input" value="<?= $v('lid_ouder_achternaam') ?>" required></label>
  </div>
  <div class="ond-rij">
    <label class="ds-veld ond-veld" style="flex:1.4"><span class="ond-lbl">E-mail <span class="ster">*</span></span><input type="email" name="lid_ouder_email" class="ds-input" value="<?= $v('lid_ouder_email') ?>" required></label>
    <label class="ds-veld ond-veld"><span class="ond-lbl">Telefoonnummer</span><input type="tel" name="lid_ouder_telefoon" class="ds-input" value="<?= $v('lid_ouder_telefoon') ?>"></label>
  </div>
</div>
<?php endif; ?>

<!-- 3. Lidmaatschap + diploma's -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('reddingsboei', 18) ?><?= $_minderjarig ? '3' : '2' ?>. Lidmaatschap en diploma's</span>
  <div class="ond-lijst">
    <?= $_radio('lid_soort', 'lid', 'Ik wil <strong>lid</strong> worden van de vereniging', true) ?>
    <?= $_radio('lid_soort', 'donateur', 'Ik wil <strong>donateur</strong> worden van de vereniging') ?>
  </div>
  <span class="ond-lbl">Diploma's en licenties <span class="optioneel">(vink aan wat je hebt)</span></span>
  <?php foreach (lidmaatschap_diplomas() as $groep => $items): ?>
    <div class="ond-veld ds-veld">
      <span class="ond-kaart-tekst" style="font-weight:600;color:var(--ink)"><?= h($groep) ?></span>
      <div class="ond-dip">
        <?php foreach ($items as $k => $label): ?>
          <label class="ond-check ond-dip-item"><input type="checkbox" name="lid_diplomas[]" value="<?= h($k) ?>"<?= in_array($k, $_dip_aan, true) ? ' checked' : '' ?>><span class="ond-check-box"><?= icoon('vink', 14) ?></span><span class="ond-check-tekst"><?= h($label) ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- 4. Betaling -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('bankpas', 18) ?><?= $_minderjarig ? '4' : '3' ?>. Betaling</span>
  <span class="ond-kaart-tekst">Contributie, zwemgelden en eventuele opleidingskosten.</span>
  <div class="ond-lijst">
    <?= $_radio('lid_betaling', 'overschrijving', 'Ik wil betalen per <strong>overschrijving</strong>', true) ?>
    <?= $_radio('lid_betaling', 'incasso', 'Ik wil betalen per <strong>automatische incasso</strong>') ?>
  </div>
  <div class="ond-toon" data-toon-bij="lid_betaling=incasso" hidden>
    <label class="ds-veld ond-veld"><span class="ond-lbl">IBAN-rekeningnummer <span class="ster">*</span></span><input type="text" name="lid_iban" class="ds-input" value="<?= $v('lid_iban') ?>" placeholder="NL00 BANK 0123 4567 89" autocomplete="off"><span class="ds-veld-hint">Wordt versleuteld bewaard en alleen gedeeld met de ledenservice.</span></label>
    <label class="ds-veld ond-veld"><span class="ond-lbl">Ten name van <span class="ster">*</span></span><input type="text" name="lid_tenaamstelling" class="ds-input" value="<?= $v('lid_tenaamstelling') ?>"></label>
    <?= $_check('lid_machtiging', h(lidmaatschap_machtigingstekst())) ?>
  </div>
</div>

<!-- 5. Akkoord -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('schild', 18) ?><?= $_minderjarig ? '5' : '4' ?>. Akkoord</span>
  <div class="ond-lijst">
    <?= $_check('lid_akkoord_opslag', 'Ik ga ermee akkoord dat de vereniging mijn ingediende informatie opslaat.', true) ?>
    <?= $_check('lid_akkoord_juist', 'Ik verklaar dat de bovenstaande gegevens volledig zijn en kloppen.', true) ?>
  </div>
</div>

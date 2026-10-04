<?php
/**
 * Partial: velden van de inschrijving als lid (type lidmaatschap) — dezelfde vragen als het formulier op
 * paal13.com/vereniging/lid-worden, vooringevuld. Veldnamen lid_*; validatie in includes/lidmaatschap.php.
 * Verwacht: $v/$aan (waarden), $leeftijd, $std (voorinvulling, o.a. lid_diplomas), $na_fout, $is_ouder,
 * $voornaam, $fout + $fout_veld (fout bij het veld: melding met role=alert, aria-invalid, aria-describedby).
 *
 * Twee vormen: de sollicitant zelf (18+, je-vorm) of de ouder/verzorger (lid < 18, u-vorm): die vult de
 * gegevens van het lid aan, geeft bij incasso de machtiging als rekeninghouder en tekent als wettelijk
 * vertegenwoordiger (kaart 2: eigen gegevens + relatie tot het lid).
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$_ouder       = !empty($is_ouder);
$_minderjarig = $_ouder || ($leeftijd !== null && $leeftijd < 18);
$_fv          = (string)($fout_veld ?? '');
$_ft          = (string)($fout ?? '');
$_lid_naam    = trim((string)($std['lid_voornaam'] ?? $voornaam ?? ''));
$_dip_aan = $na_fout ? array_map('strval', (array)($_POST['lid_diplomas'] ?? [])) : (array)($std['lid_diplomas'] ?? []);
$_n = 1; // kaartnummer
// Tekstveld: label, input (met foutkoppeling) en de foutmelding bij het veld
$_tekst = function (string $naam, string $label, bool $req = true, string $type = 'text', string $extra = '', string $klasse = '') use ($v, $_fv, $_ft): string {
    return '<label class="ds-veld ond-veld' . ($klasse !== '' ? ' ' . $klasse : '') . ($_fv === $naam ? ' fout' : '') . '">'
         . '<span class="ond-lbl">' . h($label) . ($req ? ' <span class="ster">*</span>' : '') . '</span>'
         . '<input type="' . h($type) . '" name="' . h($naam) . '" class="ds-input" value="' . $v($naam) . '"' . $extra . ($req ? ' required' : '') . ondertekening_veldfout_attr($naam, $_fv) . '>'
         . ondertekening_veldfout_html($naam, $_fv, $_ft) . '</label>';
};
$_radio = function (string $naam, string $waarde, string $tekst, bool $req = false) use ($aan, $std, $na_fout): string {
    $checked = $na_fout ? $aan($naam, $waarde) : ((($std[$naam] ?? '') === $waarde) ? ' checked' : '');
    return '<label class="ond-radio"><input type="radio" name="' . h($naam) . '" value="' . h($waarde) . '"' . $checked . ($req ? ' required' : '') . '><span class="ond-radio-box"></span><span class="ond-radio-tekst">' . $tekst . '</span></label>';
};
// Keuzegroep (radio's) met foutkoppeling op de groep; $zichtbaar = false: label alleen voor schermlezers (ontwerp)
$_groep_open = fn(string $naam, string $label, bool $zichtbaar = true): string => '<div class="ond-veld ds-veld' . ($_fv === $naam ? ' fout' : '') . '">'
    . '<span class="' . ($zichtbaar ? 'ond-lbl' : 'sr-only') . '" id="ondLbl-' . h($naam) . '">' . h($label) . ($zichtbaar ? ' <span class="ster">*</span>' : '') . '</span>'
    . '<div class="ond-lijst" role="radiogroup" aria-labelledby="ondLbl-' . h($naam) . '"' . ondertekening_veldfout_attr($naam, $_fv) . '>';
$_groep_dicht = fn(string $naam): string => '</div>' . ondertekening_veldfout_html($naam, $_fv, $_ft) . '</div>';
$_check = function (string $naam, string $tekst, bool $req = false) use ($aan, $_fv, $_ft): string {
    return '<label class="ond-check' . ($_fv === $naam ? ' fout' : '') . '"><input type="checkbox" name="' . h($naam) . '" value="1"' . $aan($naam) . ($req ? ' required' : '') . ondertekening_veldfout_attr($naam, $_fv) . '><span class="ond-check-box">' . icoon('vink', 14) . '</span><span class="ond-check-tekst">' . $tekst . '</span></label>'
         . ondertekening_veldfout_html($naam, $_fv, $_ft);
};
$_akkoord = lidmaatschap_akkoordteksten($_ouder, $_lid_naam);
?>
<!-- 1. Gegevens van het lid -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('persoon', 18) ?><?= $_n++ ?>. <?= $_ouder ? 'Gegevens van ' . h($_lid_naam !== '' ? $_lid_naam : 'het lid') : 'Persoonlijke gegevens' ?></span>
  <span class="ond-kaart-tekst"><?= $_ouder ? 'Grotendeels al ingevuld vanuit de sollicitatie. Controleer en vul aan.' : 'Grotendeels al ingevuld vanuit je sollicitatie. Controleer en vul aan.' ?></span>
  <div class="ond-rij">
    <?= $_tekst('lid_voornaam', 'Voornaam', true, 'text', $_ouder ? '' : ' autocomplete="given-name"', 'lid-w-14') ?>
    <?= $_tekst('lid_tussenvoegsel', 'Tussenvoegsel', false, 'text', '', 'lid-w-08') ?>
  </div>
  <?= $_tekst('lid_achternaam', 'Achternaam', true, 'text', $_ouder ? '' : ' autocomplete="family-name"') ?>
  <?= $_groep_open('lid_geslacht', 'Geslacht') ?>
    <?= $_radio('lid_geslacht', 'man', 'Man', true) ?>
    <?= $_radio('lid_geslacht', 'vrouw', 'Vrouw') ?>
    <?= $_radio('lid_geslacht', 'geen', 'Wil ik niet specificeren') ?>
  <?= $_groep_dicht('lid_geslacht') ?>
  <div class="ond-rij">
    <?= $_tekst('lid_geboortedatum', 'Geboortedatum', true, 'date') ?>
    <?= $_tekst('lid_geboorteplaats', 'Geboorteplaats', true, 'text', '', 'lid-w-13') ?>
  </div>
  <div class="ond-rij">
    <?= $_tekst('lid_straat', 'Straatnaam', true, 'text', $_ouder ? '' : ' autocomplete="address-line1"', 'lid-w-2') ?>
    <?= $_tekst('lid_huisnummer', 'Huisnr.', true, 'text', '', 'lid-w-08') ?>
  </div>
  <div class="ond-rij">
    <?= $_tekst('lid_postcode', 'Postcode', true, 'text', ' placeholder="1234 AB" maxlength="7"' . ($_ouder ? '' : ' autocomplete="postal-code"')) ?>
    <?= $_tekst('lid_woonplaats', 'Woonplaats', true, 'text', $_ouder ? '' : ' autocomplete="address-level2"', 'lid-w-16') ?>
  </div>
  <div class="ond-rij">
    <?= $_tekst('lid_telefoon', 'Telefoonnummer', true, 'tel', $_ouder ? '' : ' autocomplete="tel"') ?>
    <?= $_tekst('lid_email', 'E-mail', true, 'email', $_ouder ? '' : ' autocomplete="email"', 'lid-w-14') ?>
  </div>
  <?php if ($_ouder): ?>
    <span class="ds-veld-hint">Telefoon en e-mail van <?= h($_lid_naam !== '' ? $_lid_naam : 'het lid') ?> zelf; uw eigen gegevens vult u hieronder in.</span>
  <?php endif; ?>
</div>

<?php if ($_minderjarig): ?>
<!-- 2. Ouder / verzorger (lid jonger dan 18; bij de ouderroute is dit de ondertekenaar) -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('ouders', 18) ?><?= $_n++ ?>. <?= $_ouder ? 'Uw gegevens (ouder / verzorger)' : 'Ouder / verzorger' ?></span>
  <span class="ond-kaart-tekst"><?= $_ouder
    ? 'U tekent als wettelijk vertegenwoordiger van ' . h($_lid_naam !== '' ? $_lid_naam : 'het lid') . '. De vereniging legt uw gegevens vast als contactpersoon.'
    : 'Omdat je jonger bent dan 18 vraagt de vereniging ook de gegevens van je ouder of verzorger.' ?></span>
  <div class="ond-rij">
    <?= $_tekst('lid_ouder_voornaam', 'Voornaam', true, 'text', $_ouder ? ' autocomplete="given-name"' : '') ?>
    <?= $_tekst('lid_ouder_achternaam', 'Achternaam', true, 'text', $_ouder ? ' autocomplete="family-name"' : '') ?>
  </div>
  <div class="ond-rij">
    <?= $_tekst('lid_ouder_email', 'E-mail', true, 'email', $_ouder ? ' autocomplete="email"' : '', 'lid-w-14') ?>
    <?= $_tekst('lid_ouder_telefoon', 'Telefoonnummer', false, 'tel', $_ouder ? ' autocomplete="tel"' : '') ?>
  </div>
  <?php if ($_ouder): ?>
    <?= $_groep_open('lid_relatie', 'Uw relatie tot ' . ($_lid_naam !== '' ? $_lid_naam : 'het lid')) ?>
      <?php $_eerste = true; foreach (lidmaatschap_relaties() as $_k => $_l): ?>
        <?= $_radio('lid_relatie', $_k, h($_l), $_eerste) ?>
      <?php $_eerste = false; endforeach; ?>
    <?= $_groep_dicht('lid_relatie') ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Lidmaatschap + diploma's -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('reddingsboei', 18) ?><?= $_n++ ?>. Lidmaatschap en diploma's</span>
  <?= $_groep_open('lid_soort', $_ouder ? 'Wat wordt ' . ($_lid_naam !== '' ? $_lid_naam : 'het lid') . '?' : 'Wat wil je worden?', false) ?>
    <?= $_radio('lid_soort', 'lid', $_ouder ? h($_lid_naam !== '' ? $_lid_naam : 'Mijn kind') . ' wordt <strong>lid</strong> van de vereniging' : 'Ik wil <strong>lid</strong> worden van de vereniging', true) ?>
    <?= $_radio('lid_soort', 'donateur', $_ouder ? h($_lid_naam !== '' ? $_lid_naam : 'Mijn kind') . ' wordt <strong>donateur</strong> van de vereniging' : 'Ik wil <strong>donateur</strong> worden van de vereniging') ?>
  <?= $_groep_dicht('lid_soort') ?>
  <span class="ond-lbl">Diploma's en licenties <span class="optioneel">(<?= $_ouder ? 'vink aan wat ' . h($_lid_naam !== '' ? $_lid_naam : 'het lid') . ' heeft' : 'vink aan wat je hebt' ?>)</span></span>
  <?php foreach (lidmaatschap_diplomas() as $groep => $items): ?>
    <div class="ond-veld ds-veld">
      <span class="ond-kaart-tekst lid-dip-groep"><?= h($groep) ?></span>
      <div class="ond-dip">
        <?php foreach ($items as $k => $label): ?>
          <label class="ond-check ond-dip-item"><input type="checkbox" name="lid_diplomas[]" value="<?= h($k) ?>"<?= in_array($k, $_dip_aan, true) ? ' checked' : '' ?>><span class="ond-check-box"><?= icoon('vink', 14) ?></span><span class="ond-check-tekst"><?= h($label) ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Betaling -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('bankpas', 18) ?><?= $_n++ ?>. Betaling</span>
  <span class="ond-kaart-tekst">Contributie, zwemgelden en eventuele opleidingskosten.</span>
  <?= $_groep_open('lid_betaling', 'Betaalwijze', false) ?>
    <?= $_radio('lid_betaling', 'overschrijving', 'Ik wil betalen per <strong>overschrijving</strong>', true) ?>
    <?= $_radio('lid_betaling', 'incasso', 'Ik wil betalen per <strong>automatische incasso</strong>') ?>
  <?= $_groep_dicht('lid_betaling') ?>
  <div class="ond-toon" data-toon-bij="lid_betaling=incasso" hidden>
    <?php if ($_ouder): ?>
      <span class="ond-kaart-tekst">Een incassomachtiging geeft de rekeninghouder. Staat de rekening op naam van <?= h($_lid_naam !== '' ? $_lid_naam : 'uw kind') ?>, dan tekent u als wettelijk vertegenwoordiger namens <?= h($_lid_naam !== '' ? $_lid_naam : 'uw kind') ?>.</span>
    <?php endif; ?>
    <label class="ds-veld ond-veld<?= $_fv === 'lid_iban' ? ' fout' : '' ?>"><span class="ond-lbl">IBAN-rekeningnummer <span class="ster">*</span></span><input type="text" name="lid_iban" class="ds-input" value="<?= $v('lid_iban') ?>" placeholder="NL00 BANK 0123 4567 89" autocomplete="off"<?= ondertekening_veldfout_attr('lid_iban', $_fv) ?>><span class="ds-veld-hint">Wordt versleuteld bewaard en alleen gedeeld met de ledenservice.</span><?= ondertekening_veldfout_html('lid_iban', $_fv, $_ft) ?></label>
    <?= $_tekst('lid_tenaamstelling', $_ouder ? 'Ten name van (rekeninghouder)' : 'Ten name van') ?>
    <?= $_check('lid_machtiging', h(lidmaatschap_machtigingstekst($_ouder, $_lid_naam))) ?>
  </div>
</div>

<!-- Akkoord -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon('schild', 18) ?><?= $_n++ ?>. Akkoord</span>
  <div class="ond-lijst">
    <?= $_check('lid_akkoord_opslag', h($_akkoord['opslag']), true) ?>
    <?= $_check('lid_akkoord_juist', h($_akkoord['juist']), true) ?>
  </div>
</div>

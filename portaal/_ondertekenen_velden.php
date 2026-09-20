<?php
/**
 * Partial: de gegevens- en vragenkaarten van het ondertekenscherm, per documenttype.
 * Verwacht van ondertekenen.php: $type, $is_ouder, $is_gezondheid, $u (u-vorm), $v/$aan/$sel
 * (waarden na een serverfout). Veldnamen zijn ongewijzigd t.o.v. de opslag in formulier_data:
 *   relatie, geslacht, geboorteplaats, geboortedatum, a_* + a_overig (gezondheid),
 *   gezondheid_keuze + gezondheid_toelichting, foto_toestemming, v_* (intake).
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
// Eigen veldenset per type (loonheffing = officieel Belastingdienst-formulier; lidmaatschap = inschrijving vereniging)
if ($type === 'loonheffing')  { require __DIR__ . '/_ondertekenen_velden_loonheffing.php'; return; }
if ($type === 'lidmaatschap' && is_file(__DIR__ . '/_ondertekenen_velden_lidmaatschap.php')) { require __DIR__ . '/_ondertekenen_velden_lidmaatschap.php'; return; }
$_check = function (string $naam, string $tekst, bool $verplicht = false) use ($aan): string {
    return '<label class="ond-check"><input type="checkbox" name="' . h($naam) . '" value="1"' . $aan($naam) . ($verplicht ? ' required' : '') . '>'
         . '<span class="ond-check-box">' . icoon('vink', 14) . '</span><span class="ond-check-tekst">' . h($tekst) . '</span></label>';
};
?>
<!-- Gegevens van de ondertekenaar -->
<div class="ond-kaart">
  <span class="ond-kaart-lbl"><?= icoon($is_ouder ? 'ouders' : 'persoon', 18) ?><?= $is_ouder ? 'Ouder / verzorger' : 'Jouw gegevens' ?></span>
  <div class="ond-rij">
    <?php if ($is_ouder): ?>
      <label class="ds-veld ond-veld">
        <span class="ond-lbl">Relatie <span class="ster">*</span></span>
        <select name="relatie" class="ds-select" required>
          <option value="">Kies…</option>
          <option value="vader"<?= $sel('relatie', 'vader') ?>>Vader</option>
          <option value="moeder"<?= $sel('relatie', 'moeder') ?>>Moeder</option>
          <option value="voogd"<?= $sel('relatie', 'voogd') ?>>Voogd / voogdes</option>
        </select>
      </label>
    <?php endif; ?>
    <label class="ds-veld ond-veld">
      <span class="ond-lbl">Geslacht <span class="ster">*</span></span>
      <select name="geslacht" class="ds-select" required>
        <option value="">Kies…</option>
        <option value="man"<?= $sel('geslacht', 'man') ?>>Man</option>
        <option value="vrouw"<?= $sel('geslacht', 'vrouw') ?>>Vrouw</option>
        <option value="anders"<?= $sel('geslacht', 'anders') ?>>Anders</option>
      </select>
    </label>
  </div>
  <div class="ond-rij">
    <?php if ($is_gezondheid): ?>
      <label class="ds-veld ond-veld">
        <span class="ond-lbl">Geboorteplaats <span class="ster">*</span></span>
        <input type="text" name="geboorteplaats" class="ds-input" value="<?= $v('geboorteplaats') ?>" required>
      </label>
    <?php endif; ?>
    <label class="ds-veld ond-veld">
      <span class="ond-lbl">Geboortedatum <span class="ster">*</span></span>
      <input type="date" name="geboortedatum" class="ds-input" value="<?= $v('geboortedatum') ?>" required>
    </label>
  </div>
</div>

<?php if ($is_gezondheid): ?>
  <!-- Gezondheid: van toepassing? -->
  <div class="ond-kaart">
    <span class="ond-kaart-lbl"><?= icoon('reddingsboei', 18) ?>Gezondheid</span>
    <span class="ond-kaart-tekst">Vink aan wat <?= $u ? 'voor uw kind' : 'voor jou' ?> geldt. Niets van toepassing? Laat alles leeg.</span>
    <div class="ond-lijst">
      <?= $_check('a_diabetes', 'Diabetes (suikerziekte) of breuken') ?>
      <?= $_check('a_hartvaat', 'Hart- en vaataandoeningen (benauwdheid, snelle vermoeidheid, hartkloppingen)') ?>
      <?= $_check('a_ademhaling', 'Astma / bronchitis') ?>
      <?= $_check('a_zenuwstelsel', 'Duizeligheid, flauwtes, wegrakingen, epilepsie') ?>
      <?= $_check('a_gehoor', 'Middenoorontstekingen, loop-oor, gat in trommelvlies') ?>
    </div>
    <label class="ds-veld ond-veld">
      <span class="ond-lbl">Overige aandoeningen of medicatie <span class="optioneel">(optioneel)</span></span>
      <textarea name="a_overig" class="ds-textarea" rows="2" placeholder="Bijv. medicatie, allergieën, andere aandoeningen…"><?= $v('a_overig') ?></textarea>
    </label>
  </div>

<?php else: ?>
  <!-- Intake 1: gezondheid & veiligheid -->
  <div class="ond-kaart">
    <span class="ond-kaart-lbl"><?= icoon('reddingsboei', 18) ?>1. Gezondheid &amp; veiligheid</span>
    <span class="ond-kaart-tekst">Zijn er medische of fysieke aandachtspunten waarmee we tijdens de selectiedag rekening moeten houden? <?= $u ? 'U bent' : 'Je bent' ?> niet verplicht iets te delen; wat <?= $u ? 'u invult' : 'je invult' ?> wordt vertrouwelijk behandeld.</span>
    <div class="ond-lijst">
      <label class="ond-radio"><input type="radio" name="gezondheid_keuze" value="geen"<?= $aan('gezondheid_keuze', 'geen') ?> required><span class="ond-radio-box"></span><span class="ond-radio-tekst">Geen aandachtspunten om te melden.</span></label>
      <label class="ond-radio"><input type="radio" name="gezondheid_keuze" value="ja"<?= $aan('gezondheid_keuze', 'ja') ?>><span class="ond-radio-box"></span><span class="ond-radio-tekst">Ja, namelijk:</span></label>
    </div>
    <label class="ds-veld ond-veld">
      <textarea name="gezondheid_toelichting" class="ds-textarea" rows="3" placeholder="Alleen nodig als <?= $u ? 'u' : 'je' ?> hierboven 'Ja' koos" aria-label="Toelichting aandachtspunten"><?= $v('gezondheid_toelichting') ?></textarea>
    </label>
  </div>

  <!-- Intake 2: foto's -->
  <div class="ond-kaart">
    <span class="ond-kaart-lbl"><?= icoon('camera', 18) ?>2. Foto's</span>
    <span class="ond-kaart-tekst">Tijdens de dag worden foto's gemaakt voor interne evaluatie, promotie en sociale media. Niemand wordt ongepast of negatief in beeld gebracht. Toestemming is optioneel.</span>
    <?= $_check('foto_toestemming', 'Ik geef toestemming voor het gebruik van foto\'s zoals hierboven omschreven.') ?>
  </div>

  <!-- Intake 3: verklaring -->
  <div class="ond-kaart">
    <span class="ond-kaart-lbl"><?= icoon('schild', 18) ?>3. Verklaring &amp; aansprakelijkheid</span>
    <div class="ond-lijst">
      <?= $_check('v_waarheid', 'Ik verklaar dat ik dit formulier naar waarheid heb ingevuld.', true) ?>
      <?= $_check('v_gezondheid', $u ? 'Ik verklaar dat mijn kind in goede gezondheid verkeert om deel te nemen aan sportieve activiteiten.'
                                     : 'Ik verklaar dat ik in goede gezondheid verkeer om deel te nemen aan sportieve activiteiten.', true) ?>
      <?= $_check('v_eigenrisico', 'Deelname is op eigen risico. De organisatie is niet aansprakelijk voor letsel dat voortkomt uit verzwegen of onbekende omstandigheden.', true) ?>
      <?php if ($type === 'intake'): ?>
        <?= $_check('v_leeftijd', 'Ik ben 16 jaar of ouder en bevoegd om dit formulier zelfstandig te ondertekenen.', true) ?>
      <?php endif; ?>
    </div>
    <span class="ond-kaart-tekst">De verklaringen zijn nodig om te kunnen ondertekenen. Het fotobeleid is optioneel.</span>
  </div>
<?php endif; ?>

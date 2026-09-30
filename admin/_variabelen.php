<?php
/**
 * Partial: variabele invoegen — alle variabelen uit melding_variabelen_lijst() (één bron voor
 * Berichten en Sjablonen), gegroepeerd en doorzoekbaar. Een klik zet {{naam}} in het veld waar je
 * het laatst typte: een tekstveld met data-var-veld, of de e-maileditor (assets/js/variabelen.js).
 *
 * Verwacht:
 *   $var_extra  ?array  optioneel: variabelen die alleen bij dit bericht horen [naam => uitleg]
 */
$var_extra = $var_extra ?? [];
$_groepen  = ($var_extra ? ['Voor dit bericht' => $var_extra] : []) + melding_variabelen_lijst();
?>
<details class="bh-klap bh-varkiezer" data-varkiezer data-klap-id="varkiezer">
  <summary>
    <span class="bh-klap-titel">Variabele invoegen</span>
    <span class="bh-klap-sam">wordt per ontvanger ingevuld, bijvoorbeeld {{voornaam}}</span>
  </summary>
  <div class="bh-klap-inhoud">
    <label class="bh-zoek">
      <span class="bh-zoek-icoon"><?= icoon('zoek', 15) ?></span>
      <span class="bh-vw">Zoek een variabele</span>
      <input type="search" data-var-zoek placeholder="Zoek, bijvoorbeeld datum of link">
    </label>
    <?php foreach ($_groepen as $_g => $_vars): ?>
      <div class="bh-vargroep" data-vargroep>
        <span class="bh-veldlabel"><?= h($_g) ?></span>
        <div class="bh-varchips">
          <?php foreach ($_vars as $_n => $_u): ?>
            <button type="button" class="bh-varchip" data-var="{{<?= h($_n) ?>}}"
                    data-var-tekst="<?= h(mb_strtolower($_n . ' ' . $_u)) ?>">
              <?= h($_u) ?> <code>{{<?= h($_n) ?>}}</code>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <p class="ds-veld-hint">De variabele komt in het veld waar je het laatst typte. Is een waarde onbekend
      (bijvoorbeeld nog geen datum), dan blijft die plek leeg.</p>
  </div>
</details>
<?php
$var_extra = [];

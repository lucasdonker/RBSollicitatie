<?php
/**
 * Partial: kies welke kolommen je wilt zien.
 *
 * De hele machinerie bestond al — bh_kolommen() leest de keuze uit een cookie,
 * kolomZetten()/kolomkeuzeBewaren() in beheer.js verbergen de kolom en onthouden het —
 * alleen deze knop ontbrak, waardoor je er niet bij kon.
 *
 * De keuze staat in een cookie mét het seizoen-id, zodat de server hem al bij de
 * eerste render kent: geen kolommen die na een fractie van een seconde verspringen.
 *
 * Zonder JavaScript is dit een gewoon formulier dat de keuze als parameter meestuurt.
 *
 * Verwacht:
 *   $kk_tabel  array   uitkomst van bh_kolommen() (bevat 'id' en 'kolommen')
 */
$kk_tabel = $kk_tabel ?? null;
if (!$kk_tabel) return;

$_opties = array_filter($kk_tabel['kolommen'] ?? [], fn($k) => !empty($k['optioneel']));
if (!$_opties) return;                      // niets te kiezen: dan ook geen knop
$_aan = count(array_filter($_opties, fn($k) => $k['aan']));
?>
<!-- data-voor is het id van het <table>-element, dus mét het "tabel-"-voorvoegsel dat
     _tabel.php eraan geeft. Zonder dat vindt getElementById() niets en doet de kiezer
     zichtbaar niets. -->
<details class="bh-kolomkiezer" data-kolomkiezer data-voor="tabel-<?= h((string)$kk_tabel['id']) ?>">
  <summary class="ds-btn ds-btn-secundair ds-btn-sm">
    <?= icoon('lijst', 15) ?>
    Kolommen <span class="bh-kolomkiezer-getal"><?= $_aan ?>/<?= count($_opties) ?></span>
  </summary>
  <div class="bh-kolomkiezer-paneel">
    <p class="bh-kolomkiezer-hulp">Wat je hier kiest blijft staan tot het einde van het seizoen.</p>
    <?php /* Een kolom die uitstaat wordt niet meegestuurd — de gegevens staan dan ook niet
             onzichtbaar in de pagina. Aanzetten betekent dus even ophalen; dat gebeurt in
             één keer zodra je dit lijstje sluit, en deze regel zegt dat vooraf. */ ?>
    <p class="bh-kolomkiezer-wacht" data-kolomwacht role="status" hidden></p>
    <?php foreach ($_opties as $_k): ?>
      <label class="bh-kolomkiezer-optie">
        <input type="checkbox" data-kolom="<?= h((string)$_k['sleutel']) ?>" <?= $_k['aan'] ? 'checked' : '' ?>>
        <span><?= h((string)$_k['kop']) ?></span>
      </label>
    <?php endforeach; ?>
  </div>
</details>
<?php
$kk_tabel = null;

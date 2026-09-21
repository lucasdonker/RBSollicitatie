<?php
/**
 * Partial: de snelzoeker — de knop in de topbalk en het venster dat hij opent.
 *
 * Eén veld voor twee vragen die in de praktijk door elkaar lopen: "waar staat die ene
 * persoon" en "waar stond die instelling ook alweer". Ze staan onder elkaar in twee
 * groepen, want mensen wil je bijna altijd — een scherm zoek je één keer.
 *
 * Wordt vanuit _topbar.php geladen en heeft geen parameters: wát je mag vinden bepaalt
 * bh_zoek() aan de serverkant, niet dit bestand.
 */
?>
<button type="button" class="bh-zoekknop" id="zoekKnop" aria-haspopup="dialog"
        aria-label="Zoek een persoon of scherm (Ctrl K)">
  <?= icoon('zoek', 16) ?>
  <span class="bh-zoekknop-tekst">Zoeken</span>
  <kbd class="bh-zoekknop-toets">Ctrl K</kbd>
</button>

<dialog class="bh-dialoog bh-zoekvenster" id="zoekVenster" aria-label="Zoeken">
  <div class="bh-zoekvak">
    <?= icoon('zoek', 18) ?>
    <input type="text" id="zoekVeld" class="bh-zoekveld" autocomplete="off"
           placeholder="Zoek een naam, e-mailadres of scherm&hellip;"
           role="combobox" aria-expanded="false" aria-controls="zoekUitslag"
           aria-autocomplete="list" aria-label="Zoekterm">
    <button type="button" class="bh-zoeksluit" data-zoeksluit aria-label="Sluiten"><?= icoon('kruis', 16) ?></button>
  </div>
  <div class="bh-zoekuitslag" id="zoekUitslag" role="listbox" aria-label="Zoekresultaten">
    <p class="bh-zoekhint">Typ minstens twee letters. <kbd>&uarr;</kbd><kbd>&darr;</kbd> om te kiezen, <kbd>Enter</kbd> om te openen.</p>
  </div>
</dialog>

<?php
/**
 * Partial: observatie plaatsen + de lijst eronder. Gedeeld door Beoordelen op de selectiedag
 * (admin/selectiedag.php) en op het trainingsweekend (admin/tw/beoordelen.php), zodat een
 * begeleider op beide dagen precies hetzelfde doet.
 *
 * Gedrag in assets/js/beoordelen.js: soort kiezen, opslaan zonder herladen (de server geeft de
 * nieuwe lijst als HTML terug), Ctrl+Enter, een concept dat een herlaadactie overleeft, en
 * automatisch opslaan als je met tekst in het veld naar een ander persoon tikt.
 *
 * Verwacht (binnen een element met data-beoordeel, dat data-endpoint en data-csrf draagt):
 *   $oi_sol       int      sollicitatie-id
 *   $oi_naam      string   naam, voor het (verborgen) label
 *   $oi_mag       bool     mag plaatsen; anders alleen de lijst
 *   $oi_titel     string   kop boven het blok
 *   $oi_melding   ?string  korte zin onder de kop (bijv. "staat op niet gekomen"), platte tekst
 *   $oi_voorbeeld ?string  placeholder van het tekstvak
 *   $obs_lijst, $obs_eigen  voor admin/_observaties.php
 */
$oi_melding   = $oi_melding ?? null;
$oi_voorbeeld = $oi_voorbeeld ?? 'Wat viel je op…';
$_oi_soorten  = ['positief' => ['Positief', 'vink-cirkel'], 'neutraal' => ['Neutraal', 'punt'], 'aandacht' => ['Aandacht', 'let-op']];
?>
<div class="bh-observatieblok" id="observaties" data-obs-blok data-sol="<?= (int)$oi_sol ?>">
  <h3><?= h($oi_titel) ?></h3>
  <?php if ($oi_melding): ?><p class="bh-meta"><?= h($oi_melding) ?></p><?php endif; ?>

  <?php if ($oi_mag): ?>
    <div class="bh-obsinvoer" data-soort="neutraal">
      <div class="bh-soortkeuze" role="group" aria-label="Soort observatie">
        <?php foreach ($_oi_soorten as $_ok => $_os): ?>
          <button type="button" class="bh-soort <?= $_ok ?><?= $_ok === 'neutraal' ? ' gekozen' : '' ?>" data-soort="<?= $_ok ?>"
                  aria-pressed="<?= $_ok === 'neutraal' ? 'true' : 'false' ?>"><?= icoon($_os[1], 16) ?><?= h($_os[0]) ?></button>
        <?php endforeach; ?>
      </div>
      <label class="bh-vw" for="obs-<?= (int)$oi_sol ?>">Observatie over <?= h($oi_naam) ?></label>
      <?php // Op een telefoon een chatbalk onderin (veld + ronde verzendknop), op de desktop de knop eronder. ?>
      <div class="bh-obsinvoer-rij">
        <textarea class="ds-textarea" id="obs-<?= (int)$oi_sol ?>" data-obs-tekst rows="3" maxlength="2000"
                  placeholder="<?= h($oi_voorbeeld) ?>"></textarea>
        <button type="button" class="ds-btn ds-btn-primair bh-obsinvoer-knop" data-obs-opslaan aria-label="Observatie opslaan">
          <?= icoon('verstuur', 18) ?><span class="bh-obsinvoer-knoptekst">Observatie opslaan</span>
        </button>
      </div>
      <div class="bh-obsinvoer-voet">
        <span class="bh-obsinvoer-stand" data-obs-stand role="status" aria-live="polite">
          <span class="bh-obsinvoer-toets">Ctrl + Enter slaat op.</span>
        </span>
        <span class="ds-veld-hint">Voor het bestuur. De sollicitant ziet dit niet.</span>
      </div>
    </div>
  <?php endif; ?>

  <?php // data-live-blok: assets/js/live.js ververst de lijst als een collega iets toevoegt (id per persoon). ?>
  <div data-obs-lijst data-live-blok id="obs-lijst-<?= (int)$oi_sol ?>"><?php require __DIR__ . '/_observatie_lijst.php'; ?></div>
</div>
<?php
$oi_sol = $oi_naam = $oi_mag = $oi_titel = $oi_melding = $oi_voorbeeld = null;

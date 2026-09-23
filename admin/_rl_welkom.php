<?php
/**
 * Partial: welkomstblok op Vandaag — "nieuw hier? volg de rondleiding".
 *
 * Staat standaard op `hidden`; assets/js/rondleiding.js haalt dat weg zolang iemand het
 * op dit apparaat niet heeft weggeklikt of een rondleiding heeft gevolgd. Zonder
 * JavaScript blijft het dus weg — de rondleiding werkt dan ook niet, en een knop die
 * niets doet is erger dan geen knop. Het overzicht (Instellingen › Handleidingen) blijft
 * altijd bereikbaar.
 *
 * Verwacht:
 *   $rlw  array  ['route','titel','url'] uit rl_pagina_data()['welkom']
 */
$rlw = $rlw ?? null;
if ($rlw):
?>
<section class="ds-kaart bh-rl-welkom" data-rl-welkom hidden aria-labelledby="rl-welkom-kop">
  <span class="bh-rl-welkom-icoon"><?= icoon('rondleiding', 20) ?></span>
  <div class="bh-rl-welkom-tekst">
    <h2 id="rl-welkom-kop">Nieuw in het beheer?</h2>
    <p>Een rondleiding laat je op de echte schermen zien wat waar staat en wat jij er moet doen. Duurt een paar minuten; je kunt altijd stoppen.</p>
  </div>
  <div class="bh-rl-welkom-knoppen">
    <a class="ds-btn ds-btn-primair ds-btn-sm" href="<?= h($rlw['url']) ?>">Start: <?= h($rlw['titel']) ?></a>
    <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/handleiding.php">Alle rondleidingen</a>
    <button type="button" class="bh-rl-welkom-weg" data-rl-welkom-weg aria-label="Niet meer tonen"><?= icoon('kruis', 16) ?></button>
  </div>
</section>
<?php
endif;
$rlw = null;

<?php
/**
 * Loonplanning — het zijpaneel (zelfde vorm als het correctiepaneel van Beschikbaarheid).
 * Twee standen, gevuld door assets/js/loonplanning.js:
 *   week    — één week van één persoon: welke dagen iemand kan (uit de opgave, met redenen),
 *             plannen ja/nee, aantal dagen, betaald, en kopiëren naar de volgende weken.
 *             Elke wijziging slaat meteen op.
 *   persoon — functie, totalen, de afspraak en het slot na het TW-gesprek (gewone formulieren).
 * Onderdeel van admin/loonplanning.php.
 */
$_functies_opt = array_column($functies, 'naam', 'sleutel');
?>
<div class="bh-zijpaneel-overlay bh-lp-paneel" data-lp-paneel hidden>
  <div class="bh-zijpaneel" role="dialog" aria-modal="true" aria-labelledby="lpTitel">
    <header class="bh-zijpaneel-kop">
      <span class="bh-zijpaneel-avatar" data-lp-initialen aria-hidden="true"></span>
      <div class="bh-zijpaneel-wie">
        <strong id="lpTitel" data-lp-naam></strong>
        <span data-lp-sub></span>
      </div>
      <button type="button" class="bh-zijpaneel-sluit" data-lp-sluit aria-label="Sluiten">&times;</button>
    </header>

    <div class="bh-zijpaneel-body">
      <p class="bh-lp-vastmelding" data-lp-vastmelding hidden><?= icoon('slot', 15) ?>
        <span>Vast na het TW-gesprek: dit is met de sollicitant besproken. Aanpassen kan via de persoon (naam aanklikken) → Ontgrendelen.</span></p>

      <!-- Stand: week -->
      <div data-lp-stand="week" hidden>
        <section class="bh-zijpaneel-blok">
          <h3>Wat kan <span data-lp-voornaam></span> deze week?</h3>
          <ul class="bh-lp-dagen" data-lp-dagen aria-live="polite"></ul>
          <p class="bh-zijpaneel-hint" data-lp-opgave></p>
          <ul class="bh-lp-redenen" data-lp-redenen></ul>
        </section>

        <section class="bh-zijpaneel-blok">
          <h3>Plannen</h3>
          <label class="bh-zijpaneel-vink"><input type="checkbox" data-lp-veld="opgen"> <span>Deze week inplannen</span></label>
          <div class="bh-lp-stepper" data-lp-stepper>
            <span class="bh-veldlabel" id="lpDagenLabel">Dagen</span>
            <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" data-lp-min aria-label="Een dag minder">&minus;</button>
            <output data-lp-dagenwaarde aria-labelledby="lpDagenLabel">0</output>
            <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" data-lp-plus aria-label="Een dag meer">+</button>
          </div>
          <label class="bh-zijpaneel-vink"><input type="checkbox" data-lp-veld="betaald"> <span>Betaald</span></label>
          <p class="bh-zijpaneel-hint">Onbetaald zijn échte werkdagen die je met de sollicitant afspreekt; alleen het loon valt weg.
            Het rooster plant ze gewoon in.</p>
          <p class="bh-lp-signalen" data-lp-signalen role="status"></p>
        </section>

        <section class="bh-zijpaneel-blok" data-lp-alleenbewerken>
          <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" data-lp-kopieer>Zelfde voor de volgende opgegeven weken</button>
          <p class="bh-zijpaneel-hint">Plant elke volgende week die is opgegeven met hetzelfde aantal dagen (nooit meer dan opgegeven).</p>
        </section>
      </div>

      <!-- Stand: persoon -->
      <div data-lp-stand="persoon" hidden>
        <section class="bh-zijpaneel-blok">
          <h3><label for="lpFunctie">Functie</label></h3>
          <select id="lpFunctie" class="ds-select" data-lp-functie>
            <?php foreach ($_functies_opt as $_k => $_n): ?><option value="<?= h($_k) ?>"><?= h($_n) ?></option><?php endforeach; ?>
          </select>
          <p class="bh-zijpaneel-hint">Voorstel: nieuw = Aspirant, anders de Lifeguard-rang uit Authentik. Postcommandant kies je zelf.</p>
          <p class="bh-zijpaneel-status" data-lp-totaal></p>
          <a class="bh-doc-actie" data-lp-dossier href="#">Dossier openen</a>
        </section>

        <?php if ($mag_bewerken): ?>
        <section class="bh-zijpaneel-blok">
          <h3>Opgave overnemen</h3>
          <form method="post" data-bevestig="De planning van deze persoon in dit scenario vervangen door wat hij of zij zelf opgaf?">
            <?= csrf_input() ?><input type="hidden" name="sc_actie" value="opgave"><input type="hidden" name="sol_id" value="">
            <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Neem de opgave over</button>
          </form>
          <p class="bh-zijpaneel-hint">Plant elke week zoveel dagen als de sollicitant opgaf. Daarna kun je per week bijsturen.</p>
        </section>

        <section class="bh-zijpaneel-blok" data-lp-afspraakblok>
          <h3>Afspraak</h3>
          <p class="bh-zijpaneel-status" data-lp-afspraaktekst></p>
          <form method="post" data-lp-afspraakform data-bevestig="De afspraak van deze persoon vervangen door wat nu in dit scenario staat?">
            <?= csrf_input() ?><input type="hidden" name="sc_actie" value="afspraak"><input type="hidden" name="sol_id" value="">
            <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm" data-lp-afspraakknop>Afspraak vaststellen</button>
          </form>
        </section>

        <section class="bh-zijpaneel-blok">
          <h3>Vast na het gesprek</h3>
          <p class="bh-zijpaneel-status" data-lp-slottekst></p>
          <form method="post" data-lp-vastzetform>
            <?= csrf_input() ?><input type="hidden" name="sc_actie" value="vastzetten"><input type="hidden" name="sol_id" value="">
            <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Vastzetten vanuit dit scenario</button>
          </form>
          <form method="post" data-lp-ontgrendelform>
            <?= csrf_input() ?><input type="hidden" name="sc_actie" value="ontgrendel"><input type="hidden" name="sol_id" value="">
            <label for="lpReden" class="bh-veldlabel">Waarom pas je het aan? (komt in het logboek)</label>
            <input type="text" id="lpReden" name="reden" class="ds-input" maxlength="300" required>
            <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Ontgrendelen</button>
          </form>
        </section>
        <?php endif; ?>
      </div>
    </div>

    <footer class="bh-zijpaneel-voet">
      <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" data-lp-sluit>Sluiten</button>
    </footer>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/loonplanning.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/loonplanning.js') ?>" defer></script>

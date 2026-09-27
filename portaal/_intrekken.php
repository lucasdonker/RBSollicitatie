<?php
/**
 * Partial: "Toch niet meer?" — verzoek om de sollicitatie in te trekken (Mijn gegevens).
 * Bewust ingetogen: een kaart met een uitklapper. Het is een verzoek; het bestuur belt en
 * beslist (includes/intrekken.php). Het vakje "op de hoogte blijven" staat NIET vooraf aan:
 * dat is toestemming, en die moet iemand zelf geven.
 * POST-afhandeling: portaal/gegevens.php (intrek_actie). Werkt zonder JavaScript.
 * Kaartopmaak: .pt-kaart, net als de andere kaarten op Mijn gegevens.
 *
 * Verwacht:
 *   $ik_sol_id    int
 *   $ik_persoon   int
 *   $ik_fase      string
 *   $ik_flash     ?string  uitkomst van de vorige POST
 *   $ik_fout      ?string
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
if (!function_exists('intrek_open_van') || !in_array($ik_fase, intrek_fases(), true)) return;
$_iv      = intrek_open_van($ik_sol_id);
$_seintje = function_exists('seintje_heeft') && seintje_heeft($ik_persoon);
?>
<section class="pt-kaart" id="intrekken" aria-labelledby="ggIntrekken">
  <h2 class="pt-kaart-titel" id="ggIntrekken"><?= icoon('info', 18) ?> Toch niet meer?</h2>
  <?php if ($ik_flash): ?>
    <div class="ds-melding ds-melding-ok"><?= icoon('vink-cirkel', 18) ?> <div><?= h($ik_flash) ?></div></div>
  <?php endif; ?>
  <?php if ($ik_fout): ?>
    <div class="ds-melding ds-melding-error"><?= icoon('let-op', 18) ?> <div><?= h($ik_fout) ?></div></div>
  <?php endif; ?>

  <?php if ($_iv): ?>
    <p class="pt-kaart-tekst">
      Je hebt op <?= h(portaal_datum_kort($_iv['aangevraagd_op'])) ?> gevraagd je sollicitatie in te trekken.
      We nemen contact met je op. Tot die tijd blijft alles gewoon staan.
    </p>
    <details class="pt-bevestig">
      <summary>Toch niet intrekken</summary>
      <p>Dan loopt je sollicitatie gewoon door.</p>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="intrek_actie" value="annuleren">
        <button type="submit" class="ds-btn ds-btn-secundair">Verzoek annuleren</button>
      </form>
    </details>
  <?php else: ?>
    <p class="pt-kaart-tekst">
      Wil je stoppen met je sollicitatie? Laat het ons weten. We nemen dan contact met je op voordat er iets verandert.
    </p>
    <details class="pt-bevestig"<?= $ik_fout ? ' open' : '' ?>>
      <summary>Sollicitatie intrekken</summary>
      <form method="POST" class="pt-intrek-form">
        <?= csrf_input() ?>
        <input type="hidden" name="intrek_actie" value="aanvragen">
        <div class="ds-veld">
          <label class="ds-label" for="intrekReden">Waarom? (mag je leeg laten)</label>
          <textarea id="intrekReden" name="reden" rows="3" maxlength="1000" class="ds-textarea"><?= h((string)($_POST['reden'] ?? '')) ?></textarea>
        </div>
        <?php if (!$_seintje): ?>
          <label class="pt-deelname-optie">
            <input type="checkbox" name="seintje" value="1">
            Stuur me een seintje als de werving voor een volgend seizoen opent
          </label>
          <span class="ds-veld-hint">Dan bewaren we je naam en contactgegevens daarvoor maximaal een jaar. Zonder vinkje niet.</span>
        <?php endif; ?>
        <button type="submit" class="ds-btn ds-btn-secundair">Verzoek versturen</button>
      </form>
    </details>
  <?php endif; ?>
</section>

<?php
/**
 * Partial: "Stel een vraag" — de uitweg uit het portaal.
 *
 * Zonder dit is het portaal een doodlopend eind: je krijgt een bericht, leest het, en
 * kunt niet antwoorden. Elke vraag gaat dan via een kanaal waar hij kwijtraakt.
 * Hiermee komt hij bij het bestuur binnen mét naam en fase erbij.
 *
 * De afhandeling staat in portaal_vraag_verwerk() (includes/portaal_berichten.php),
 * zodat elke pagina die dit blok toont hem op dezelfde manier verwerkt.
 *
 * Verwacht:
 *   $vraag_sol    array   de sollicitant (portaal_sollicitant_laden())
 *   $vraag_flash  ?string uitkomst van de vorige POST (gezet door portaal_vraag_verwerk)
 *   $vraag_fout   ?string foutmelding
 */
$vraag_sol   = $vraag_sol ?? null;
$vraag_flash = $vraag_flash ?? null;
$vraag_fout  = $vraag_fout ?? null;
if (!$vraag_sol) return;
$_contact = function_exists('portaal_contact_email') ? portaal_contact_email() : '';
?>
<div class="pt-kaart pt-vraag">
  <h2 class="pt-kaart-titel"><?= icoon('chat', 18) ?> Een vraag?</h2>

  <?php if ($vraag_flash): ?>
    <div class="ds-melding ds-melding-ok">
      <?= icoon('vink-cirkel', 18) ?>
      <div><?= h($vraag_flash) ?></div>
    </div>
  <?php else: ?>
    <p class="pt-kaart-tekst">
      Stel hem hier — hij komt bij het bestuur terecht met je naam erbij, dus je hoeft niet uit te
      leggen wie je bent. Je krijgt antwoord per e-mail.
    </p>
    <?php if ($vraag_fout): ?>
      <div class="ds-melding ds-melding-error"><?= icoon('let-op', 18) ?> <div><?= h($vraag_fout) ?></div></div>
    <?php endif; ?>
    <form method="POST" class="pt-vraag-form">
      <?= csrf_input() ?>
      <input type="hidden" name="vraag_versturen" value="1">
      <div class="ds-veld">
        <label class="ds-label" for="vraagTekst">Je vraag</label>
        <textarea id="vraagTekst" name="vraag" rows="3" maxlength="1500" required
                  class="ds-textarea" placeholder="Bijvoorbeeld: ik kan op de selectiedag pas om 10 uur, kan dat?"><?= h((string)($_POST['vraag'] ?? '')) ?></textarea>
        <div class="ds-veld-hint">Een paar zinnen is genoeg.</div>
      </div>
      <button type="submit" class="ds-btn ds-btn-secundair">Versturen</button>
    </form>
  <?php endif; ?>

  <?php if ($_contact !== ''): ?>
    <p class="pt-vraag-voet">Liever zelf mailen of bellen? <a href="mailto:<?= h($_contact) ?>"><?= h($_contact) ?></a></p>
  <?php endif; ?>
</div>
<?php
$vraag_sol = null; $vraag_flash = null; $vraag_fout = null;

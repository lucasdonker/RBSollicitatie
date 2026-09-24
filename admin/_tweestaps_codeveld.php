<?php
/**
 * Partial: het invoerveld voor een tweestapscode.
 * Gebruikt op de inlogpagina (tweede stap) en in Mijn profiel (bevestigen, uitzetten, opnieuw instellen).
 *
 * Verwacht $tsv (array):
 *   id        string   id van het veld (uniek op de pagina)
 *   naam      string   name van het veld (standaard 'code')
 *   label     string   zichtbaar label
 *   hint      ?string  uitleg onder het veld
 *   herstel   bool     ook een herstelcode toegestaan (dan geen numeriek toetsenbord)
 *   autofocus bool
 *   fout      ?string  foutmelding bij dit veld (role="alert")
 * Geen JavaScript nodig. Na afloop wordt $tsv leeggemaakt.
 */
$tsv = $tsv ?? [];
$_tid   = (string)($tsv['id'] ?? 'code');
$_hint  = (string)($tsv['hint'] ?? '');
$_fout  = (string)($tsv['fout'] ?? '');
$_herst = !empty($tsv['herstel']);
$_besch = trim(($_hint !== '' ? $_tid . '-hint ' : '') . ($_fout !== '' ? $_tid . '-fout' : ''));
?>
<div class="ds-veld">
  <label class="ds-label" for="<?= h($_tid) ?>"><?= h((string)($tsv['label'] ?? 'Code')) ?></label>
  <input class="ds-input" type="text" id="<?= h($_tid) ?>" name="<?= h((string)($tsv['naam'] ?? 'code')) ?>"
         autocomplete="one-time-code" required spellcheck="false" autocapitalize="off"
         <?php if ($_herst): ?>maxlength="16"<?php else: ?>inputmode="numeric" pattern="[0-9 ]*" maxlength="7"<?php endif; ?>
         <?= !empty($tsv['autofocus']) ? 'autofocus' : '' ?>
         <?= $_besch !== '' ? 'aria-describedby="' . h($_besch) . '"' : '' ?>
         <?= $_fout !== '' ? 'aria-invalid="true"' : '' ?>>
  <?php if ($_hint !== ''): ?>
    <p class="ds-veld-hint" id="<?= h($_tid) ?>-hint"><?= h($_hint) ?></p>
  <?php endif; ?>
  <?php if ($_fout !== ''): ?>
    <p class="ds-melding ds-melding-error" id="<?= h($_tid) ?>-fout" role="alert"><?= h($_fout) ?></p>
  <?php endif; ?>
</div>
<?php
$tsv = null;

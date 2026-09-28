<?php
/**
 * Partial: de tablet bij de ingang — toegangscode, tablet-link en de pincode om op de tablet
 * zelf af te sluiten. Voor de selectiedag én het trainingsweekend.
 *
 * Verwacht:
 *   $tc_ev           'selectiedag' | 'tw'
 *   $tc_mag_bewerken bool  code en pin aanmaken/wijzigen (bewerken op de module)
 * Opslaan: admin/api_incheck.php (actie code_genereren / pin_zetten). Bevestigen via
 * data-bevestig (beheer.js); geen eigen script.
 */
$_def  = evenement_def($tc_ev);
$_code = (string)instelling($_def['code'], '');
$_pin  = (string)instelling($_def['pin'], '') !== '';
$_url  = APP_URL . '/portaal/incheck.php?ev=' . urlencode($tc_ev) . '&code=' . urlencode($_code);
?>
<section class="ds-kaart ds-kaart-p bh-incheck-tablet">
  <div class="bh-kaartkop">
    <h2>Tablet bij de ingang</h2>
    <span class="bh-meta">sollicitanten melden zichzelf aan</span>
  </div>
  <?php if ($_code !== ''): ?>
    <div class="bh-incheck-coderij">
      <div class="bh-incheck-codeblok">
        <span class="bh-veldlabel">Toegangscode</span>
        <span class="bh-incheck-code"><?= h($_code) ?></span>
      </div>
      <div class="bh-incheck-link">
        <label class="bh-veldlabel" for="tabletUrl-<?= h($tc_ev) ?>">Tablet-link</label>
        <div class="bh-incheck-linkrij">
          <input type="text" class="ds-input" readonly value="<?= h($_url) ?>" id="tabletUrl-<?= h($tc_ev) ?>">
          <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" data-kopieer="#tabletUrl-<?= h($tc_ev) ?>">Kopiëren</button>
          <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($_url) ?>" target="_blank" rel="noopener">Openen</a>
        </div>
      </div>
    </div>
  <?php elseif ($tc_mag_bewerken): ?>
    <p class="bh-dossier-tekst">Er is nog geen code. Zonder code kunnen sollicitanten zich niet zelf aanmelden.</p>
  <?php else: ?>
    <p class="bh-dossier-tekst zacht">Nog geen code ingesteld. Vraag een bestuurslid er een aan te maken.</p>
  <?php endif; ?>

  <?php if ($tc_mag_bewerken): ?>
    <form method="post" action="<?= APP_URL ?>/admin/api_incheck.php" class="bh-incheck-nieuw"
          <?= $_code !== '' ? 'data-bevestig="Nieuwe toegangscode aanmaken? De tablets die nu open staan werken daarna niet meer; open ze opnieuw met de nieuwe link."' : '' ?>>
      <?= csrf_input() ?>
      <input type="hidden" name="ev" value="<?= h($tc_ev) ?>">
      <input type="hidden" name="actie" value="code_genereren">
      <button type="submit" class="ds-btn <?= $_code !== '' ? 'ds-btn-secundair ds-btn-sm' : 'ds-btn-primair' ?>"><?= $_code !== '' ? 'Nieuwe code aanmaken' : 'Toegangscode aanmaken' ?></button>
    </form>
    <form method="post" action="<?= APP_URL ?>/admin/api_incheck.php" class="bh-incheck-pin">
      <?= csrf_input() ?>
      <input type="hidden" name="ev" value="<?= h($tc_ev) ?>">
      <input type="hidden" name="actie" value="pin_zetten">
      <label for="afsluitPin-<?= h($tc_ev) ?>" class="bh-veldlabel">Pincode om op de tablet af te sluiten <?= $_pin ? '(ingesteld)' : '(nog niet ingesteld)' ?></label>
      <div class="bh-incheck-linkrij">
        <input type="password" inputmode="numeric" autocomplete="new-password" id="afsluitPin-<?= h($tc_ev) ?>" name="pin" class="ds-input"
               placeholder="minstens 4 cijfers" minlength="4" pattern="[0-9]{4,}" required>
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Opslaan</button>
      </div>
    </form>
    <?php if ($_pin): ?>
    <form method="post" action="<?= APP_URL ?>/admin/api_incheck.php" class="bh-incheck-nieuw"
          data-bevestig="Pincode wissen? Dan kan het inchecken alleen nog in het beheer worden afgesloten, niet op de tablet.">
      <?= csrf_input() ?>
      <input type="hidden" name="ev" value="<?= h($tc_ev) ?>">
      <input type="hidden" name="actie" value="pin_wissen">
      <button type="submit" class="ds-btn ds-btn-zacht ds-btn-sm">Pincode wissen</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</section>

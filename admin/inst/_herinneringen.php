<?php
/**
 * Maatwerkblok — Instellingen › Uitnodigen & herinneren › Herinneringen: per ingebouwde herinnering
 * aan/uit, na hoeveel dagen, herhalen en maximaal. De regels komen uit herinnering_regels(); de
 * sleutels staan in instellingen_herinnering_sleutels() (de whitelist van dit tabblad).
 * Onderdeel van admin/_inst_formulier.php; ziet $mag_sjablonen en $mag_systeem van admin/instellingen.php.
 */
?>
<p class="ds-veld-hint bh-mb">
      Automatische herinneringen, gecontroleerd op wat er echt verstuurd is (niet dubbel). Inhoud en kanaal per type:
      <?php if ($mag_sjablonen): ?><a href="<?= APP_URL ?>/admin/mail_templates.php" class="bh-let-op">Sjablonen</a> en<?php endif; ?>
      <a href="<?= APP_URL ?>/admin/instellingen.php?tab=berichten" class="bh-let-op">Kanalen &amp; meldingen</a>.
      <?php if ($mag_systeem): ?>Proefdraaien zonder te versturen: <a href="<?= APP_URL ?>/admin/systeem.php#herinneringen" class="bh-let-op">Systeem › Herinneringen</a>.<?php endif; ?>
    </p>

    <?php if (function_exists('herinnering_regels')): foreach (herinnering_regels() as $hslug => $hregel):
      $h_aan = herinnering_instelling($hslug, 'aan') === '1'; ?>
    <div class="bh-veldrij bh-inst-herinnering">
      <div class="ds-veld bh-inst-herinnering-wat">
        <label>
          <input type="hidden" name="inst[herinnering.<?= $hslug ?>.aan]" value="0">
          <input type="checkbox" name="inst[herinnering.<?= $hslug ?>.aan]" value="1" <?= $h_aan ? 'checked' : '' ?>>
          <?= h($hregel['naam']) ?>
        </label>
        <div class="ds-veld-hint"><?= h($hregel['uitleg']) ?></div>
        <?php if ($hslug === 'selectiedag_morgen'): ?>
        <input type="text" name="inst[herinnering.selectiedag_morgen.info]" value="<?= h(instelling('herinnering.selectiedag_morgen.info', '') ?: instelling('reminder.sd_morgen_info', '')) ?>" placeholder="Laatste info, bv: het wordt warm, neem water mee" class="bh-inst-herinnering-info">
        <?php endif; ?>
      </div>
      <?php if (empty($hregel['vast_na'])): ?>
      <div class="ds-veld bh-inst-getalveld">
        <label><?= in_array($hslug, ['selectiedag_nadert', 'trainingsweekend_nadert'], true) ? 'Dagen vooraf' : 'Na (dagen)' ?></label>
        <input type="number" name="inst[herinnering.<?= $hslug ?>.na]" value="<?= (int)herinnering_instelling($hslug, 'na') ?>" min="0" max="60">
      </div>
      <?php endif; ?>
      <?php if ((int)$hregel['herhaal'] > 0): ?>
      <div class="ds-veld bh-inst-getalveld">
        <label>Herhaal elke</label>
        <input type="number" name="inst[herinnering.<?= $hslug ?>.herhaal]" value="<?= (int)herinnering_instelling($hslug, 'herhaal') ?>" min="1" max="60">
      </div>
      <div class="ds-veld bh-inst-getalveld-smal">
        <label>Max</label>
        <input type="number" name="inst[herinnering.<?= $hslug ?>.max]" value="<?= (int)herinnering_instelling($hslug, 'max') ?>" min="1" max="20">
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>

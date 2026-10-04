<?php
/**
 * Partial: de kaart "Nu" bovenaan het dossier — wat er aan de hand is, wie aan zet is, de stand
 * met datums en de volgende stap (met een knop naar het scherm waar je hem zet).
 * Logica in bh_dossier_nu() (includes/beheer/dossier.php). De fase-knop staat in de kop van het dossier.
 *
 * Verwacht:
 *   $nu_dossier  array  uitkomst van bh_dossier()
 *   $nu_taken    array  uitkomst van bh_dossier_taken()
 */
$_nu = bh_dossier_nu($nu_dossier, $nu_taken);
$_id = (int)$nu_dossier['sol']['id'];
?>
<section class="ds-kaart ds-kaart-p bh-nu" aria-labelledby="nu-titel">
  <div class="bh-nu-kop">
    <span class="bh-nu-label">Nu</span>
    <h2 class="bh-nu-titel" id="nu-titel"><?= h($_nu['titel']) ?></h2>
    <?= bh_chip($_nu['aan_zet']['tekst'], $_nu['aan_zet']['kleur']) ?>
  </div>
  <p class="bh-nu-zin"><?= h($_nu['zin']) ?></p>
  <?php if ($_nu['volgende']): ?>
    <div class="bh-nu-volgende">
      <span class="bh-nu-volgende-tekst"><span class="bh-veldlabel">Volgende stap</span> <?= h($_nu['volgende']['tekst']) ?></span>
      <?php if (!empty($_nu['volgende']['url'])): ?>
        <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($_nu['volgende']['url']) ?>"><?= h((string)$_nu['volgende']['knop']) ?></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($_nu['wacht']): ?>
    <div class="bh-nu-wacht">
      <span class="bh-veldlabel">Nog open</span>
      <div class="bh-chiprij">
        <?php foreach ($_nu['wacht'] as $_w) echo bh_chip($_w['tekst'], $_w['kleur']); ?>
        <?php if ($_nu['meer']): ?><a class="bh-kaartkop-link" href="#taken">en nog <?= (int)$_nu['meer'] ?></a><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($_nu['herinner'] && mag('sollicitanten', 'bewerken')): ?>
    <form method="post" class="bh-nu-acties"
          data-bevestig="Herinnering sturen? Wie taken open heeft krijgt &quot;Openstaande taken&quot;; een ouder die nog moet tekenen krijgt de ondertekenherinnering.">
      <?= csrf_input() ?>
      <input type="hidden" name="id" value="<?= $_id ?>">
      <input type="hidden" name="herinneren" value="1">
      <button type="submit" class="ds-btn ds-btn-zacht ds-btn-sm"><?= icoon('bel', 14) ?> Herinnering sturen</button>
    </form>
  <?php endif; ?>
</section>
<?php
$nu_dossier = $nu_taken = null;

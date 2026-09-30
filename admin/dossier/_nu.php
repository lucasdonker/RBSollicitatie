<?php
/**
 * Partial: de kaart "Nu" bovenaan het dossier — waar staat deze sollicitant en waar wacht het op.
 * Logica in bh_dossier_nu() (includes/beheer/dossier.php). De stap vooruit staat in de kop.
 *
 * Verwacht:
 *   $nu_dossier  array  uitkomst van bh_dossier()
 *   $nu_taken    array  uitkomst van bh_dossier_taken()
 */
$_nu = bh_dossier_nu($nu_dossier, $nu_taken);
$_id = (int)$nu_dossier['sol']['id'];
?>
<section class="ds-kaart ds-kaart-p bh-nu">
  <div class="bh-kaartkop"><h2>Nu</h2></div>
  <p class="bh-nu-zin"><?= h($_nu['zin']) ?></p>
  <?php if ($_nu['wacht']): ?>
    <div class="bh-nu-wacht">
      <span class="bh-veldlabel">Wacht op</span>
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
      <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm"><?= icoon('bel', 14) ?> Herinnering sturen</button>
    </form>
  <?php endif; ?>
</section>
<?php
$nu_dossier = $nu_taken = null;

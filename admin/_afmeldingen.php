<?php
/**
 * Partial: "Kan niet komen — wacht op jouw besluit" (Vandaag).
 *
 * Wie in het portaal (of via de bellijst) doorgeeft niet te kunnen komen, blijft in zijn
 * fase staan tot het bestuur beslist: afmelden (afgehaakt) of een uitzondering maken.
 * Niets gaat vanzelf — dat is een keuze van Lucas, geen gat.
 *
 * Verwacht:
 *   $afmeldingen  array  uit evenement_afmeldingen_open(): [['sol_id','naam','evenement','label','reden'], …]
 * Knoppen alleen met bewerkrecht op de module van dat evenement. Wie bij geen enkele rij
 * een knop heeft, krijgt het blok als informatie: dan wacht er niets op jóúw besluit.
 */
$afmeldingen = $afmeldingen ?? [];
if (!$afmeldingen) return;
$_afm_mag = false;
foreach ($afmeldingen as $_a) {
    $_def = evenement_def($_a['evenement']);
    if ($_def && mag($_def['module'], 'voorbereiden')) { $_afm_mag = true; break; }
}
?>
<section class="ds-kaart ds-kaart-p bh-afmeldingen" aria-labelledby="bh-afm-kop">
  <div class="bh-kaartkop"><h2 id="bh-afm-kop"><?= $_afm_mag ? 'Kan niet komen — wacht op jouw besluit' : 'Kan niet komen — het bestuur beslist' ?></h2></div>
  <ul class="bh-afm-lijst">
    <?php foreach ($afmeldingen as $_a): $_def = evenement_def($_a['evenement']); ?>
      <li class="bh-afm-rij">
        <div class="bh-afm-tekst">
          <a href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= (int)$_a['sol_id'] ?>" class="bh-link-navy"><?= h($_a['naam']) ?></a>
          <span class="bh-meta"><?= h($_a['label']) ?><?= $_a['reden'] ? ' · ' . h($_a['reden']) : '' ?></span>
        </div>
        <?php if ($_def && mag($_def['module'], 'voorbereiden')): ?>
        <div class="bh-knoprij">
          <form method="POST" action="<?= APP_URL ?>/admin/afmelding.php"
                data-bevestig="<?= h($_a['naam']) ?> afmelden? De fase wordt Afgehaakt; er gaat geen bericht uit.">
            <?= csrf_input() ?>
            <input type="hidden" name="sol_id" value="<?= (int)$_a['sol_id'] ?>">
            <input type="hidden" name="ev" value="<?= h($_a['evenement']) ?>">
            <input type="hidden" name="keuze" value="afmelden">
            <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Afmelden</button>
          </form>
          <form method="POST" action="<?= APP_URL ?>/admin/afmelding.php"
                data-bevestig="<?= h($_a['naam']) ?> blijft in het proces, ook al gaf die door niet te kunnen. Uitzondering maken?">
            <?= csrf_input() ?>
            <input type="hidden" name="sol_id" value="<?= (int)$_a['sol_id'] ?>">
            <input type="hidden" name="ev" value="<?= h($_a['evenement']) ?>">
            <input type="hidden" name="keuze" value="uitzondering">
            <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Uitzondering, blijft in proces</button>
          </form>
        </div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

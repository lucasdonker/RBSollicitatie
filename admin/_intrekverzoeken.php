<?php
/**
 * Partial: "Wil intrekken — bel eerst" (Vandaag, en bovenaan het dossier van die persoon).
 *
 * Een sollicitant vroeg in het portaal de sollicitatie in te trekken. Er verandert niets
 * vanzelf: het bestuur neemt contact op en kiest daarna doorvoeren (fase Afgehaakt, zonder
 * bericht) of niet (de sollicitatie loopt door). Logica: includes/intrekken.php;
 * opslaan: admin/intrekken.php. Werkt zonder JavaScript.
 *
 * Verwacht:
 *   $intrekverzoeken  array  uit intrek_open_lijst() (of één rij daarvan)
 * Knoppen alleen met sollicitanten › bewerken.
 */
$intrekverzoeken = $intrekverzoeken ?? [];
if (!$intrekverzoeken) return;
$_iv_mag = mag('sollicitanten', 'bewerken');
?>
<section class="ds-kaart ds-kaart-p bh-afmeldingen" aria-labelledby="bh-iv-kop">
  <div class="bh-kaartkop"><h2 id="bh-iv-kop">Wil intrekken — neem eerst contact op</h2></div>
  <ul class="bh-afm-lijst">
    <?php foreach ($intrekverzoeken as $_iv): ?>
      <li class="bh-afm-rij">
        <div class="bh-afm-tekst">
          <a href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= (int)$_iv['sol_id'] ?>" class="bh-link-navy"><?= h($_iv['naam']) ?></a>
          <span class="bh-meta"><?= h(implode(' · ', array_filter([fase_label((string)$_iv['fase']), 'gevraagd ' . datum_kort_nl($_iv['aangevraagd_op']),
                                    (string)($_iv['reden'] ?? ''), !empty($_iv['seintje']) ? 'wil een seintje voor volgend seizoen' : '']))) ?></span>
        </div>
        <?php if ($_iv_mag): ?>
        <div class="bh-knoprij">
          <form method="POST" action="<?= APP_URL ?>/admin/intrekken.php"
                data-bevestig="Intrekken doorvoeren voor <?= h($_iv['naam']) ?>? De fase wordt Afgehaakt; er gaat geen bericht uit.">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= (int)$_iv['id'] ?>">
            <input type="hidden" name="keuze" value="doorvoeren">
            <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Doorvoeren</button>
          </form>
          <form method="POST" action="<?= APP_URL ?>/admin/intrekken.php">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= (int)$_iv['id'] ?>">
            <input type="hidden" name="keuze" value="afwijzen">
            <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Niet doorvoeren, loopt door</button>
          </form>
        </div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php
$intrekverzoeken = null;

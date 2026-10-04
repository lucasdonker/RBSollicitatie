<?php
/**
 * Partial: kaart "Te beoordelen voor vernietiging" op Loondossiers.
 *
 * Het systeem wist loonstukken niet zelf: het weet alleen tot welk seizoen iemand hier voorkwam,
 * niet wanneer het dienstverband eindigde. Deze kaart zet de kandidaten klaar; de penningmeester
 * controleert in de loonadministratie en vernietigt via het tussenscherm (_bevestig.php).
 *
 * Verwacht:
 *   $tv_lijst       array  uitkomst van documenten_te_vernietigen()
 *   $tv_jaren       int    documenten_loon_bewaarjaren()
 *   $tv_mag_wissen  bool   mag('documenten', 'verwijderen') — anders geen knop
 * Toont niets als de lijst leeg is.
 */
$tv_lijst      = $tv_lijst ?? [];
$tv_jaren      = (int)($tv_jaren ?? 5);
$tv_mag_wissen = (bool)($tv_mag_wissen ?? false);
if (!$tv_lijst) return;
?>
<section class="ds-kaart ds-kaart-p bh-loonvernietig" id="vernietigen" aria-labelledby="loonvernietig-titel">
  <h2 id="loonvernietig-titel" class="bh-loonvernietig-titel">
    <?= icoon('prullenbak', 18) ?> Te beoordelen voor vernietiging
  </h2>
  <p class="bh-loonvernietig-uitleg">
    Hieronder staat wie hier voor het laatst als aangenomen voorkwam in een seizoen dat minstens
    <?= $tv_jaren ?> volle kalenderjaren terug ligt. De fiscale
    bewaarplicht is <?= $tv_jaren ?> jaar na het einde van het kalenderjaar waarin het dienstverband eindigde.
    Het systeem weet alleen tot welk seizoen iemand hier voorkwam — controleer in de loonadministratie of het
    dienstverband echt toen eindigde. Werkte iemand daarna nog, vernietig dan niets.
  </p>
  <ul class="bh-dosstukken">
    <?php foreach ($tv_lijst as $_tv): ?>
    <li>
      <span class="bh-dosstuk-wat">
        <strong><?= h((string)$_tv['naam']) ?></strong>
        <span>Laatste seizoen hier: <?= (int)$_tv['laatste_jaar'] ?></span>
      </span>
      <span class="bh-dosstuk-stand">
        Nog aanwezig: <?= h(implode(' · ', $_tv['stukken'])) ?>
      </span>
      <span class="bh-dosstuk-actie">
        <?php if ($tv_mag_wissen): ?>
          <a class="ds-btn ds-btn-gevaar ds-btn-sm" href="?bevestig=loondossier&amp;persoon=<?= (int)$_tv['persoon_id'] ?>">
            <?= icoon('prullenbak', 14) ?> Vernietigen…
          </a>
        <?php endif; ?>
      </span>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php
$tv_lijst = []; $tv_mag_wissen = false;

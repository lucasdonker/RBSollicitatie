<?php
/**
 * Partial: de ene taak die nu telt.
 *
 * Er is één hoofdtaak tegelijk (ontwerpfilosofie §2) en die staat groot. Is er niets
 * te doen, dan zegt het blok dat ook — leeg is vaak goed nieuws (§8), geen grijze streep.
 *
 * Verwacht:
 *   $vandaag_taak  array  uitkomst van bh_vandaag_hoofdtaak()
 */
$vandaag_taak = $vandaag_taak ?? null;
if (!$vandaag_taak || $vandaag_taak['titel'] === '') return;

$_klaar  = !empty($vandaag_taak['klaar']);
$_totaal = (int)($vandaag_taak['totaal'] ?? 0);
$_gedaan = (int)($vandaag_taak['gedaan'] ?? 0);
$_open   = max(0, $_totaal - $_gedaan);
?>
<section class="bh-hoofdtaak<?= $_klaar ? ' klaar' : '' ?>">
  <div class="bh-hoofdtaak-tekst">
    <span class="bh-hoofdtaak-kop">
      <?= $_klaar ? 'Niets te doen' : 'Te doen' ?>
    </span>
    <h2><?= h($vandaag_taak['titel']) ?></h2>
    <?php if (!empty($vandaag_taak['tekst'])): ?>
      <p><?= h($vandaag_taak['tekst']) ?></p>
    <?php endif; ?>
  </div>

  <?php if (!$_klaar): ?>
  <div class="bh-hoofdtaak-rechts">
    <?php if ($_totaal > 0): ?>
      <div class="bh-hoofdtaak-voortgang">
        <div class="bh-blokjes" role="img"
             aria-label="<?= h($_gedaan . ' van ' . $_totaal . ' gedaan') ?>">
          <?php for ($_i = 0; $_i < min($_totaal, 20); $_i++): ?>
            <span class="bh-blokje<?= $_i < $_gedaan ? ' gedaan' : '' ?>"></span>
          <?php endfor; ?>
        </div>
        <span class="bh-meta"><?= (int)$_gedaan ?> gedaan · <?= (int)$_open ?> open</span>
      </div>
    <?php endif; ?>
    <?php if (!empty($vandaag_taak['knop']) && !empty($vandaag_taak['url'])): ?>
      <a class="ds-btn ds-btn-primair" href="<?= h($vandaag_taak['url']) ?>">
        <?= h($vandaag_taak['knop']) ?>
      </a>
    <?php endif; ?>
  </div>
  <?php else: ?>
    <span class="bh-hoofdtaak-vink"><?= icoon('vink-cirkel', 26) ?></span>
  <?php endif; ?>
</section>
<?php
$vandaag_taak = null;

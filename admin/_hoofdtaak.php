<?php
/**
 * Partial: de ene taak die nu telt.
 *
 * Er is één hoofdtaak tegelijk (ontwerpfilosofie §2) en die staat groot. Is er niets
 * te doen, dan zegt het blok dat ook — leeg is vaak goed nieuws (§8), geen grijze streep.
 *
 * Is de stap klaar maar is er een vervolgstap, dan staat de knop er ook bij een klare
 * taak ("Volgende stap"). In de fase afronden komt er een lijst stappen met vinkjes bij.
 *
 * Verwacht:
 *   $vandaag_taak  array  uitkomst van bh_vandaag_hoofdtaak()
 *                         optioneel 'taken': [['tekst','uitleg','knop','url'|null,'af'=>bool], …]
 */
$vandaag_taak = $vandaag_taak ?? null;
if (!$vandaag_taak || $vandaag_taak['titel'] === '') return;

$_klaar  = !empty($vandaag_taak['klaar']);
$_totaal = (int)($vandaag_taak['totaal'] ?? 0);
$_gedaan = (int)($vandaag_taak['gedaan'] ?? 0);
$_open   = max(0, $_totaal - $_gedaan);
$_knop   = !empty($vandaag_taak['knop']) && !empty($vandaag_taak['url']);
$_taken  = $vandaag_taak['taken'] ?? [];
?>
<section class="bh-hoofdtaak<?= $_klaar ? ' klaar' : '' ?>">
  <div class="bh-hoofdtaak-tekst">
    <span class="bh-hoofdtaak-kop">
      <?= !$_klaar ? 'Te doen' : ($_knop ? 'Volgende stap' : 'Niets te doen') ?>
    </span>
    <h2><?= h($vandaag_taak['titel']) ?></h2>
    <?php if (!empty($vandaag_taak['tekst'])): ?>
      <p><?= h($vandaag_taak['tekst']) ?></p>
    <?php endif; ?>
    <?php if ($_taken): ?>
      <ul class="bh-hoofdtaak-taken">
        <?php foreach ($_taken as $_tk): ?>
          <li class="bh-hoofdtaak-taak<?= $_tk['af'] ? ' af' : '' ?>">
            <span class="bh-hoofdtaak-taakicoon" aria-hidden="true"><?= icoon($_tk['af'] ? 'vink-cirkel' : 'punt', 16) ?></span>
            <span class="bh-hoofdtaak-taaktekst">
              <span class="bh-vw"><?= $_tk['af'] ? 'Klaar:' : 'Nog te doen:' ?></span>
              <?php if (!empty($_tk['url'])): ?>
                <a href="<?= h($_tk['url']) ?>" class="bh-link-navy"><?= h($_tk['tekst']) ?></a>
              <?php else: ?>
                <strong><?= h($_tk['tekst']) ?></strong>
              <?php endif; ?>
              <?php if (!empty($_tk['uitleg'])): ?><span class="bh-meta"><?= h($_tk['uitleg']) ?></span><?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
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
    <?php if ($_knop): ?>
      <a class="ds-btn ds-btn-primair" href="<?= h($vandaag_taak['url']) ?>">
        <?= h($vandaag_taak['knop']) ?>
      </a>
    <?php endif; ?>
  </div>
  <?php elseif ($_knop): ?>
  <div class="bh-hoofdtaak-rechts">
    <span class="bh-hoofdtaak-vink"><?= icoon('vink-cirkel', 26) ?></span>
    <a class="ds-btn ds-btn-secundair" href="<?= h($vandaag_taak['url']) ?>">
      <?= h($vandaag_taak['knop']) ?>
    </a>
  </div>
  <?php else: ?>
    <span class="bh-hoofdtaak-vink"><?= icoon('vink-cirkel', 26) ?></span>
  <?php endif; ?>
</section>
<?php
$vandaag_taak = null;

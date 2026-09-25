<?php
/**
 * Partial: de systeemstatusbalk — twee standen.
 *
 * Automatisering die je niet ziet wordt eng zodra ze hapert (ontwerpfilosofie §3),
 * dus de taakronde staat altijd in beeld. De alarmstand is nooit alleen kleur: er
 * staat een driehoek bij, het woord "Aandacht nodig", en een knop die ergens heen
 * gaat (§8).
 *
 * Verwacht:
 *   $vandaag_systeem  array  uitkomst van bh_systeemstatus()
 */
$vandaag_systeem = $vandaag_systeem ?? null;
if (!$vandaag_systeem) return;

// Opschonen uit is een AVG-probleem, geen storing, maar wel alarm: de privacyverklaring en de
// afwijzingsmail beloven termijnen die dan niet worden gehaald. Tekst komt uit de health-check.
$_opschonen_uit = function_exists('instelling_bool') && !instelling_bool('bewaar.actief', false);
$_ok  = !empty($vandaag_systeem['ok']) && !$_opschonen_uit;
$_tijd = !empty($vandaag_systeem['ronde']) ? substr((string)$vandaag_systeem['ronde'], 11, 5) : null;
?>
<section class="bh-systeem <?= $_ok ? 'ds-fase-groen' : 'ds-fase-rood' ?><?= $_ok ? '' : ' alarm' ?>">
  <span class="bh-systeem-kop">
    <?php if ($_ok): ?>
      <span class="bh-systeem-stip"></span>Systeem in orde
    <?php else: ?>
      <?= icoon('waarschuwing', 15) ?>Aandacht nodig
    <?php endif; ?>
  </span>

  <span class="bh-systeem-cijfers">
    <?php /* Getal vóór het woord, net als de twee regels hieronder: "wachtrij 0" leest
             als een naam met een nummer, "0 berichten in de wachtrij" als een stand. */ ?>
    <span<?= $vandaag_systeem['wachtrij'] > 20 ? ' class="let-op"' : '' ?>>
      <strong class="bh-num"><?= (int)$vandaag_systeem['wachtrij'] ?></strong>
      <?= (int)$vandaag_systeem['wachtrij'] === 1 ? 'bericht' : 'berichten' ?> in de wachtrij
    </span>
    <span<?= $vandaag_systeem['fouten'] > 0 ? ' class="slecht"' : '' ?>>
      <?= (int)$vandaag_systeem['fouten'] ?> mailfouten in 24 uur
    </span>
    <?php // Open ondertekeningen staan al onder "Loopt ondertussen"; hier alleen techniek. ?>
    <?php if ($_opschonen_uit && function_exists('health_opschonen_uit_tekst')): ?>
      <span class="slecht"><?= h(health_opschonen_uit_tekst()) ?></span>
    <?php endif; ?>
  </span>

  <span class="bh-systeem-ronde">
    <?php if ($_tijd && !empty($vandaag_systeem['ronde_ok'])): ?>
      taakronde <?= h($_tijd) ?>
    <?php elseif ($_tijd): ?>
      <strong>taakronde vastgelopen <?= h($_tijd) ?></strong>
    <?php else: ?>
      <strong>taakronde nooit gedraaid</strong>
    <?php endif; ?>
  </span>

  <?php /* Alleen knoppen naar schermen die deze gebruiker ook mag openen. */ ?>
  <?php if ($_opschonen_uit && mag('instellingen', 'bewerken')): ?>
    <a class="ds-btn ds-btn-sm bh-systeem-knop" href="<?= APP_URL ?>/admin/instellingen.php?tab=bewaren">
      Opschonen aanzetten
    </a>
  <?php endif; ?>
  <?php if (empty($vandaag_systeem['ok']) && mag('logboek', 'lezen')): ?>
    <a class="ds-btn ds-btn-sm bh-systeem-knop" href="<?= APP_URL ?>/admin/logboek.php?bron=fout">
      Bekijk het logboek
    </a>
  <?php endif; ?>
</section>
<?php
$vandaag_systeem = null;

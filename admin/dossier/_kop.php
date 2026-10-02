<?php
/**
 * Partial: de kop van het dossier — wie is dit, en wat kun je nu doen.
 *
 * De hoofdactie is de stap vooruit in de trechter en komt uit bh_fase_actie(), zodat
 * die hier hetzelfde is als in de lijstrij en in de bulkbalk. De knop post naar
 * admin/sollicitant.php, dat eerst het tussenscherm toont (met "Zonder bericht
 * doorvoeren") — net als de lijst. Pas na die bevestiging gaat er iets de deur uit.
 *
 * Verwacht:
 *   $kop_dossier  array  uitkomst van bh_dossier()
 */
$kop_dossier = $kop_dossier ?? null;
if (!$kop_dossier) return;

$_s    = $kop_dossier['sol'];
$_naam = volledige_naam($_s);
$_act  = bh_fase_actie((string)$_s['fase'], ['id' => (int)$_s['id']]);
$_mag  = mag('sollicitanten', 'bewerken');
$_link = !empty($_act['url']);   // na de selectiedag: naar de bellijst, geen fasewissel
?>
<header class="bh-dossierkop">
  <?= bh_avatar($_s, 58, ['vergroot' => true]) ?>

  <div class="bh-dossierkop-wie">
    <div class="bh-dossierkop-naam">
      <h2><?= h($_naam) ?></h2>
      <?= bh_fase_badge((string)$_s['fase'], ['stip' => true]) ?>
      <?php if (!empty($_s['terugkerend'])): ?>
        <?= bh_badge('Eerder gesolliciteerd', 'paars') ?>
      <?php endif; ?>
    </div>
    <?= bh_meta([
        $kop_dossier['leeftijd'] !== null ? $kop_dossier['leeftijd'] . ' jaar' : '',
        $kop_dossier['blk']['woonplaats'] ?? '',
        $_s['email'] ?? '',
        $_s['telefoon'] ?? '',
    ]) ?>
    <?php if (!empty($kop_dossier['leeftijd_melding'])): ?>
      <div class="ds-melding ds-melding-warn bh-dossierkop-let-op">
        <?= icoon('waarschuwing', 15) ?><span><?= h($kop_dossier['leeftijd_melding']) ?></span>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($_mag || $_link): ?>
  <form method="post" class="bh-dossierkop-acties">
    <?= csrf_input() ?>
    <input type="hidden" name="id" value="<?= (int)$_s['id'] ?>">
    <?php if ($_link): ?>
      <a class="ds-btn ds-btn-primair" href="<?= h($_act['url']) ?>"><?= h($_act['label']) ?></a>
    <?php elseif ($_act && $_mag): ?>
      <button type="submit" name="fase_actie" value="<?= h($_act['actie']) ?>"
              class="ds-btn ds-btn-primair"><?= h($_act['label']) ?></button>
    <?php endif; ?>
    <?php if ($_mag && mag('mailing', 'versturen')): ?>
      <a class="ds-btn ds-btn-secundair" href="<?= APP_URL ?>/admin/berichten.php?ids=<?= (int)$_s['id'] ?>">Bericht</a>
    <?php endif; ?>
    <?php if ($_mag && !empty($_s['telefoon'])): ?>
      <a class="ds-btn ds-btn-secundair" href="tel:<?= h(preg_replace('/\s+/', '', $_s['telefoon'])) ?>">Bellen</a>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</header>
<?php
$kop_dossier = null;

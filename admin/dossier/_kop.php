<?php
/**
 * Partial: de kop van het dossier — wie is dit, en wat kun je nu met hem.
 *
 * De hoofdactie is de stap vooruit in de trechter en komt uit bh_fase_actie(), zodat
 * hij hier hetzelfde is als in de lijstrij en in de bulkbalk.
 *
 * Verwacht:
 *   $kop_dossier  array  uitkomst van bh_dossier()
 */
$kop_dossier = $kop_dossier ?? null;
if (!$kop_dossier) return;

$_s    = $kop_dossier['sol'];
$_naam = volledige_naam($_s);
$_act  = bh_fase_actie((string)$_s['fase']);
$_mag  = mag('sollicitanten', 'bewerken');
?>
<header class="bh-dossierkop">
  <?= bh_avatar($_s, 58) ?>

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

  <?php if ($_mag): ?>
  <form method="post" class="bh-dossierkop-acties">
    <?= csrf_input() ?>
    <input type="hidden" name="id" value="<?= (int)$_s['id'] ?>">
    <?php if ($_act): ?>
      <button type="submit" name="fase_actie" value="<?= h($_act['actie']) ?>"
              class="ds-btn ds-btn-primair"><?= h($_act['label']) ?></button>
    <?php endif; ?>
    <?php if (mag('mailing', 'lezen')): ?>
      <a class="ds-btn ds-btn-secundair" href="<?= APP_URL ?>/admin/berichten.php?ids=<?= (int)$_s['id'] ?>">Bericht</a>
    <?php endif; ?>
    <?php if (!empty($_s['telefoon'])): ?>
      <a class="ds-btn ds-btn-secundair" href="tel:<?= h(preg_replace('/\s+/', '', $_s['telefoon'])) ?>">Bellen</a>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</header>
<?php
$kop_dossier = null;

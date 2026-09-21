<?php
/**
 * Portaal — Berichten (achter het belletje, ontwerp 08). Overzicht van alle berichten aan deze
 * sollicitant (e-mail, WhatsApp, sms) met samenvatting en ongelezen-markering. Tik op een
 * bericht om het volledig te lezen. Bij openen wordt alles als gelezen gezet.
 */
require_once __DIR__ . '/../includes/functions.php';
$sol_id = portaal_vereis_login();
$sol = portaal_sollicitant_laden($sol_id);
if (!$sol) { unset($_SESSION['portaal_sol_id']); redirect(APP_URL . '/portaal/'); }

// "Stel een vraag" staat onder de berichtenlijst: dit is de plek waar iemand zit die
// net iets heeft gelezen en wil reageren.
$vr = portaal_vraag_verwerk($sol);

$gezien_op = portaal_berichten_gezien_op($sol_id);
$berichten = portaal_berichten($sol_id, $gezien_op);
$ongelezen = count(array_filter($berichten, fn($b) => $b['ongelezen']));
portaal_berichten_gezien($sol_id);   // vanaf nu is alles gelezen; deze weergave toont nog de stippen

$portaal_titel = 'Berichten';
$portaal_nav   = 'start';
$portaal_terug = APP_URL . '/portaal/';
$portaal_berichten_ongelezen = 0;    // de bel in de kop hoeft hier geen stip meer te tonen
require __DIR__ . '/_kop.php';
?>
<div class="pt-stapel pt-berichten">
  <div class="pt-paginatitel">
    <h1>Berichten</h1>
    <span><?= $ongelezen > 0 ? $ongelezen . ' ongelezen' : 'Alles gelezen' ?></span>
  </div>

  <?php if (!$berichten): ?>
    <div class="pt-leeg">
      <span class="pt-leeg-icoon navy"><?= icoon('mail', 24) ?></span>
      <span class="pt-leeg-titel">Nog geen berichten</span>
      <span class="pt-leeg-tekst">Berichten die we je sturen, zoals een uitnodiging of herinnering, verschijnen hier.</span>
    </div>
  <?php else: ?>
    <?php foreach ($berichten as $b): ?>
    <a class="pt-bericht<?= $b['ongelezen'] ? ' ongelezen' : '' ?>" href="<?= APP_URL ?>/portaal/bericht.php?id=<?= (int)$b['id'] ?>">
      <span class="pt-bericht-icoon"><?= icoon($b['icoon'], 20) ?></span>
      <div class="pt-bericht-tekst">
        <div class="pt-bericht-kop">
          <span class="pt-bericht-titel"><?= h($b['titel']) ?></span>
          <?php if ($b['ongelezen']): ?><span class="pt-bericht-stip" aria-label="Ongelezen"></span><?php endif; ?>
        </div>
        <?php if ($b['samenvatting'] !== ''): ?><span class="pt-bericht-sam"><?= h($b['samenvatting']) ?></span><?php endif; ?>
        <span class="pt-bericht-meta"><?= h($b['meta']) ?></span>
      </div>
    </a>
    <?php endforeach; ?>
    <span class="pt-hulp">Berichten blijven een seizoen bewaard.</span>
  <?php endif; ?>

  <?php
    $vraag_sol   = $sol;
    $vraag_flash = $vr['flash'];
    $vraag_fout  = $vr['fout'];
    require __DIR__ . '/_vraag.php';
  ?>
</div>

<?php require __DIR__ . '/_voet.php'; ?>

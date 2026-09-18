<?php
/**
 * Portaal — Taken-tabblad (ontwerp 08): "Nu te doen" (open) en "Afgerond", met teller.
 * Zelfde takenbron als de startpagina: includes/portaal_taken.php.
 */
require_once __DIR__ . '/../includes/functions.php';
$sol_id = portaal_vereis_login();
$sol = portaal_sollicitant_laden($sol_id);
if (!$sol) { unset($_SESSION['portaal_sol_id']); redirect(APP_URL . '/portaal/'); }
$blk = blk_van($sol_id);

// Taak-acties (oudergegevens, afvinken) — vóór alle HTML; keert terug naar deze pagina
if ($_SERVER['REQUEST_METHOD'] === 'POST') { $_POST['terug'] = 'taken'; portaal_taken_post_afhandelen($sol_id); }

$taken    = portaal_taken($sol, $blk);
$open     = portaal_taken_open($taken);
$wacht    = portaal_taken_wachtend($taken);
$afgerond = portaal_taken_afgerond($taken);
$n_open   = count($open);
$totaal   = count($taken);
$teller   = $totaal === 0 ? 'Nog geen taken' : ($n_open === 0 ? 'Alles gedaan' : count($afgerond) . ' van ' . $totaal . ' gedaan');
$taak_terug = 'taken';

$portaal_titel = 'Taken';
$portaal_nav   = 'taken';
$portaal_status_fase = $sol['fase'];
$portaal_open_taken  = $n_open;
require __DIR__ . '/_kop.php';
?>
  <?php toon_flash(); ?>
  <?php if (!empty($_SESSION['flash_portaal'])): ?>
    <div class="flash flash-succes" style="margin-bottom:12px"><?= h($_SESSION['flash_portaal']) ?></div>
    <?php unset($_SESSION['flash_portaal']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_portaal_fout'])): ?>
    <div class="flash flash-fout" style="margin-bottom:12px"><?= h($_SESSION['flash_portaal_fout']) ?></div>
    <?php unset($_SESSION['flash_portaal_fout']); ?>
  <?php endif; ?>

<div class="pt-stapel">
  <div class="pt-paginatitel">
    <h1>Taken</h1>
    <span><?= h($teller) ?></span>
  </div>

  <?php if ($n_open > 0): ?>
    <div class="pt-sectiekop"><span>Nu te doen</span><span class="pt-sectiekop-teller open"><?= $n_open ?> open</span></div>
    <?php $lijst = $open; require __DIR__ . '/_takenlijst.php'; ?>
  <?php else: ?>
    <?php
      $leeg_groot = true;
      $leeg_titel = 'Je bent helemaal bij';
      $leeg_tekst = 'Er staat niets open. Zodra er iets van je nodig is, verschijnt het hier en krijg je een bericht.';
      require __DIR__ . '/_leeg.php';
    ?>
  <?php endif; ?>

  <?php if ($wacht): ?>
    <div class="pt-sectiekop" style="padding-top:8px"><span>Wacht op je ouder/verzorger</span></div>
    <?php $lijst = $wacht; require __DIR__ . '/_takenlijst.php'; ?>
  <?php endif; ?>

  <?php if ($afgerond): ?>
    <div class="pt-sectiekop" style="padding-top:8px"><span>Afgerond</span></div>
    <?php $lijst = $afgerond; require __DIR__ . '/_takenlijst.php'; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_voet.php'; ?>

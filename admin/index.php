<?php
/**
 * Vandaag — het startscherm.
 *
 * Opent op een vraag, niet op data: bovenaan waar het seizoen staat, daaronder de ene
 * taak die nu telt, en pas daarna de cijfers. Iemand die eens per week inlogt moet
 * binnen tien seconden zien wat er te doen is (ontwerpfilosofie §1 en §2).
 *
 * Wat hier staat, staat er voor iedereen — behalve de blokken met persoonsgegevens.
 * Die vragen om het leesrecht op sollicitanten.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

$db  = db();
$sz  = actief_seizoen();
$sid = $sz ? (int)$sz['id'] : 0;

$fase     = bh_seizoen_fase($sz);
$taak     = bh_vandaag_hoofdtaak($sz, $fase);
$loopt    = bh_vandaag_loopt($sz);
$systeem  = bh_systeemstatus();
$tellingen = $sid ? bh_fase_tellingen($sid) : [];
$mag_soll = mag('sollicitanten', 'lezen');

$pagina_titel = 'Vandaag';
$actieve_nav  = 'dashboard';
require __DIR__ . '/layout_top.php';
?>

<?php if (!$sz): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php
      $leeg_titel = 'Er is nog geen seizoen';
      $leeg_tekst = 'Maak een seizoen aan en zet de datums; daarna komt hier te staan wat er te doen is.';
      $leeg_icoon = 'kalender';
      $leeg_knop  = ['tekst' => 'Seizoen aanmaken', 'url' => APP_URL . '/admin/seizoen.php'];
      require __DIR__ . '/_leeg.php';
    ?>
  </div>
<?php else: ?>

  <?php $vandaag_fase = $fase; require __DIR__ . '/_seizoenbalk.php'; ?>

  <?php $vandaag_taak = $taak; require __DIR__ . '/_hoofdtaak.php'; ?>

  <div class="bh-vandaag-kolommen">

    <?php if ($mag_soll): ?>
    <section class="ds-kaart ds-kaart-p bh-stand">
      <div class="bh-kaartkop">
        <h2>Waar staat iedereen</h2>
        <span class="bh-meta">klik om te filteren</span>
      </div>
      <?php
        $_totaal = array_sum($tellingen);
        $_max    = $_totaal > 0 ? max($tellingen) : 0;
      ?>
      <?php if ($_totaal === 0): ?>
        <?php
          $leeg_titel = 'Nog niemand aangemeld';
          $leeg_tekst = 'Zodra de eerste aanmelding binnenkomt, zie je hier hoe de lichting ervoor staat.';
          require __DIR__ . '/_leeg.php';
        ?>
      <?php else: ?>
        <div class="bh-stand-balken">
          <?php foreach (fasen() as $_f => $_lbl): ?>
            <?php $_n = (int)($tellingen[$_f] ?? 0); if ($_n === 0) continue; ?>
            <a class="bh-stand-balk ds-fase-<?= h(fase_kleur($_f)) ?>"
               href="<?= APP_URL ?>/admin/sollicitanten.php?fase=<?= h($_f) ?>">
              <span class="bh-stand-getal"><?= $_n ?></span>
              <span class="bh-stand-staaf" style="height:<?= max(8, (int)round($_n / max(1, $_max) * 56)) ?>px"></span>
              <span class="bh-stand-label"><?= h(fase_label_kort($_f)) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="bh-vandaag-zij">
      <?php if ($loopt): ?>
      <section class="ds-kaart ds-kaart-p">
        <div class="bh-kaartkop"><h2>Loopt ondertussen</h2></div>
        <ul class="bh-looptlijst">
          <?php foreach ($loopt as $_l): ?>
            <li class="ds-fase-<?= h($_l['toon']) ?>">
              <span class="bh-loopt-stip"></span>
              <?php if (!empty($_l['url'])): ?>
                <a href="<?= h($_l['url']) ?>"><?= h($_l['tekst']) ?></a>
              <?php else: ?>
                <span><?= h($_l['tekst']) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <?php $vandaag_systeem = $systeem; require __DIR__ . '/_systeemstatus.php'; ?>
    </div>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

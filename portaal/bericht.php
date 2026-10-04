<?php
/**
 * Portaal — Eén bericht volledig lezen (vanuit Berichten). Alleen berichten aan de ingelogde
 * sollicitant zelf; de inhoud wordt opgeschoond getoond (geen scripts/formulieren).
 */
require_once __DIR__ . '/../includes/functions.php';
$sol_id = portaal_vereis_login();
$sol = portaal_sollicitant_laden($sol_id);
if (!$sol) { unset($_SESSION['portaal_sol_id']); redirect(APP_URL . '/portaal/'); }

$id = (int)($_GET['id'] ?? 0);
$b  = $id > 0 ? portaal_bericht_laden($sol_id, $id) : null;
if (!$b) redirect(APP_URL . '/portaal/berichten.php');

$portaal_titel = $b['titel'];
$portaal_nav   = 'start';
$portaal_terug = APP_URL . '/portaal/berichten.php';
// Zelfde bericht via een ander kanaal (portaal_berichten_bundel): één keer tonen, met beide iconen en een voetnoot.
$_ook = portaal_bericht_ook($sol_id, $b);
require __DIR__ . '/_kop.php';
?>
<div class="pt-stapel pt-berichten">
  <div class="pt-bericht-detail">
    <div class="pt-bericht-detail-kop">
      <?= portaal_kanaal_iconen(array_merge([(string)$b['kanaal']], array_column($_ook, 'kanaal'))) ?>
      <div class="pt-bericht-tekst">
        <span class="pt-bericht-titel"><?= h($b['titel']) ?></span>
        <span class="pt-bericht-meta"><?= h($b['meta']) ?></span>
      </div>
    </div>
    <?php if ($b['body'] === null || $b['body'] === ''): ?>
      <p class="pt-bericht-inhoud-leeg">De inhoud van dit bericht is niet meer beschikbaar in het portaal. Kijk in je e-mail of berichten-app<?= $b['samenvatting'] !== '' ? ', of lees de samenvatting: ' . h($b['samenvatting']) : '' ?>.</p>
    <?php elseif ($b['kanaal'] === 'email'): ?>
      <div class="pt-bericht-inhoud pt-mailtekst"><?= portaal_bericht_html_schoon($b['body']) ?></div>
    <?php else: ?>
      <div class="pt-bericht-inhoud pt-korttekst"><?= nl2br(h($b['body'])) ?></div>
    <?php endif; ?>
    <?php if ($_ook): ?>
      <p class="pt-bericht-ook">Ook verstuurd <?= h(implode(' en ', array_map(fn($o) => portaal_bericht_kanaal($o['kanaal'])['label'], $_ook))) ?>.</p>
    <?php endif; ?>
  </div>
  <a class="pt-hulp" href="<?= APP_URL ?>/portaal/berichten.php"><?= icoon('terug', 14) ?> Alle berichten</a>
</div>

<?php require __DIR__ . '/_voet.php'; ?>

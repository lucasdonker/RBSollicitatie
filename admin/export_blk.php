<?php
/**
 * Export van BLK-dossiers voor het bestuur.
 *
 *  - ?formaat=excel&ids=1,2,3   → één Excel/CSV-bestand met alle geselecteerde sollicitanten
 *                                  (inclusief uitgewerkte beschikbaarheid), rij per persoon.
 *  - ?formaat=excel&seizoen=1   → alle sollicitanten van een seizoen.
 *  - ?formaat=pdf&id=1          → één printbaar dossier (PDF) voor die sollicitant.
 *
 * Toegang: wie sollicitantgegevens mag inzien. Het adres en de velden uit het
 * kennismakingsformulier alleen met het recht blk-lezen (AVG, dataminimalisatie);
 * zonder dat recht bevat de export de basisgegevens en de woonplaats.
 */
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('sollicitanten', 'exporteren');   // taak (includes/rechten.php)

$db = db();
$formaat = $_GET['formaat'] ?? 'excel';
$met_formulier = mag('blk', 'lezen');

// Welke sollicitanten?
$ids = [];
if (!empty($_GET['ids'])) {
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
} elseif (!empty($_GET['id'])) {
    $ids = [(int)$_GET['id']];
} elseif (!empty($_GET['seizoen'])) {
    $sq = $db->prepare("SELECT id FROM sollicitaties WHERE seizoen_id = ? ORDER BY id");
    $sq->execute([(int)$_GET['seizoen']]);
    $ids = array_map('intval', array_column($sq->fetchAll(), 'id'));
}

if (!$ids) { http_response_code(400); exit('Geen sollicitanten geselecteerd.'); }

// Audit: wie exporteert welke dossiers?
if (function_exists('audit_log')) {
    audit_log('geexporteerd', 'blk_dossier', null, ['aantal' => count($ids), 'formaat' => $formaat,
                                                    'met_formulier' => $met_formulier]);
}

// ══ EXCEL / CSV (bulk) ══
if ($formaat === 'excel' || $formaat === 'csv') {
    $bestandsnaam = 'blk_dossiers_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $bestandsnaam . '"');
    // BOM zodat Excel UTF-8 (accenten) goed toont
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');

    $eerste = true;
    foreach ($ids as $sol_id) {
        $dossier = export_dossier_van($sol_id, $met_formulier);
        if (!$dossier) continue;
        if ($eerste) {
            fputcsv($out, array_keys($dossier), ';');
            $eerste = false;
        }
        // Nieuwe regels in tekstvelden platslaan zodat de CSV netjes blijft
        $rij = array_map(fn($v) => trim(preg_replace('/\s*\R\s*/', ' / ', (string)$v)), array_values($dossier));
        fputcsv($out, $rij, ';');
    }
    fclose($out);
    exit;
}

// ══ PDF (per persoon, printbaar) ══
if ($formaat === 'pdf') {
    $sol_id = $ids[0];
    $dossier = export_dossier_van($sol_id, $met_formulier);
    if (!$dossier) { http_response_code(404); exit('Sollicitant niet gevonden.'); }
    $naam = $dossier['Naam'] ?? 'sollicitant';

    // We renderen een nette HTML die de browser als PDF kan afdrukken (Ctrl/Cmd+P → Opslaan
    // als PDF). Geen externe PDF-library nodig; de opmaak is print-geoptimaliseerd.
    ?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title>Dossier — <?= h($naam) ?></title>
<style>
  @media print { .geen-print { display:none } @page { margin: 20mm } }
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1a1a1a;max-width:800px;margin:0 auto;padding:30px;line-height:1.5}
  h1{font-size:22px;color:#054d8a;margin:0 0 4px;border-bottom:3px solid #054d8a;padding-bottom:8px}
  .sub{color:#666;font-size:13px;margin-bottom:24px}
  .veld{margin-bottom:14px;page-break-inside:avoid}
  .label{font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px}
  .waarde{font-size:14px;white-space:pre-wrap}
  .waarde.leeg{color:#bbb;font-style:italic}
  .besch{background:#f0f6fc;border-left:3px solid #054d8a;padding:10px 14px;border-radius:6px}
  .knop{display:inline-block;background:#054d8a;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-size:14px;margin-bottom:20px;border:none;cursor:pointer;font-family:inherit}
</style>
<script src="<?= APP_URL ?>/assets/js/klik.js?v=<?= @filemtime(__DIR__.'/../assets/js/klik.js') ?>"></script>
</head>
<body>
  <button type="button" data-klik="window.print()" class="knop geen-print">Afdrukken / opslaan als PDF</button>
  <h1><?= h($dossier['Naam']) ?></h1>
  <div class="sub"><?= h($dossier['Seizoen']) ?> · <?= h($dossier['Fase']) ?></div>

  <?php foreach ($dossier as $label => $waarde):
      if ($label === 'Naam') continue; // al in de titel
      $leeg = trim((string)$waarde) === '';
      $isBesch = ($label === 'Beschikbaarheid');
  ?>
  <div class="veld">
    <div class="label"><?= h($label) ?></div>
    <div class="waarde <?= $leeg ? 'leeg' : '' ?> <?= $isBesch && !$leeg ? 'besch' : '' ?>"><?= $leeg ? '—' : h($waarde) ?></div>
  </div>
  <?php endforeach; ?>

  <div class="sub geen-print" style="margin-top:30px">Geëxporteerd op <?= date('d-m-Y H:i') ?> · Paal 13 sollicitatiesysteem</div>
</body>
</html>
    <?php
    exit;
}

http_response_code(400);
exit('Onbekend formaat.');

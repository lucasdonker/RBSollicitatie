<?php
/**
 * Snelle export van de beschikbaarheid per dag voor één sollicitant.
 * Toont — over de hele seizoensperiode — per dag of iemand beschikbaar is,
 * precies zoals het aan de rooster-API zou worden doorgegeven.
 *
 * Gebruik:
 *   ?id=<sollicitatie_id>              → leesbare controle-weergave (tabel)
 *   ?id=<sollicitatie_id>&formaat=json → ruwe JSON (zoals de API het krijgt)
 */
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('beschikbaarheid', 'lezen');

$id = (int)($_GET['id'] ?? 0);
$formaat = $_GET['formaat'] ?? 'html';
if (!$id) { http_response_code(400); exit('Ongeldig verzoek: geen id.'); }

// Sollicitant + seizoen ophalen
try {
    $db = db();
    $q = $db->prepare("SELECT s.*, p.voornaam, p.tussenvoegsel, p.achternaam, sz.naam AS seizoen_naam,
                              sz.datum_werk_start, sz.datum_werk_eind
                       FROM sollicitaties s
                       JOIN personen p ON p.id = s.persoon_id
                       JOIN seizoenen sz ON sz.id = s.seizoen_id
                       WHERE s.id = ?");
    $q->execute([$id]);
    $sol = $q->fetch();
} catch (Exception $e) { http_response_code(500); exit('Databasefout.'); }

if (!$sol) { http_response_code(404); exit('Sollicitant niet gevonden.'); }

$van = $sol['datum_werk_start'] ?? null;
$tot = $sol['datum_werk_eind'] ?? null;
if (!$van || !$tot) {
    http_response_code(422);
    exit('Voor dit seizoen zijn nog geen werk-start/eind-datums ingesteld (Instellingen → Selectie & seizoen).');
}

$naam = trim($sol['voornaam'] . ' ' . ($sol['tussenvoegsel'] ? $sol['tussenvoegsel'] . ' ' : '') . $sol['achternaam']);

// De kern: dag-voor-dag beschikbaarheid (zoals de rooster-API het zou krijgen)
$per_dag = function_exists('beschikbaarheid_per_dag') ? beschikbaarheid_per_dag($id, $van, $tot) : [];

if (function_exists('audit_log')) {
    audit_log('geexporteerd', 'beschikbaarheid', $id, ['formaat' => $formaat]);
}

// ── JSON-uitvoer (het formaat dat de API zou ontvangen) ──────────────
if ($formaat === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    $dagen_json = [];
    foreach ($per_dag as $datum => $info) {
        $dagen_json[] = [
            'datum'       => $datum,
            'beschikbaar' => $info['beschikbaar'],
            'reden'       => $info['reden'] ?: null,
            'hardheid'    => $info['hardheid'],   // 'hard' | 'zacht' | null
        ];
    }
    echo json_encode([
        'sollicitatie_id' => $id,
        'naam'            => $naam,
        'seizoen'         => $sol['seizoen_naam'],
        'periode'         => ['van' => $van, 'tot' => $tot],
        'dagen'           => $dagen_json,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Leesbare controle-weergave ───────────────────────────────────────
$dagen_nl = [1=>'ma',2=>'di',3=>'wo',4=>'do',5=>'vr',6=>'za',7=>'zo'];
$maanden_nl = [1=>'januari',2=>'februari',3=>'maart',4=>'april',5=>'mei',6=>'juni',
               7=>'juli',8=>'augustus',9=>'september',10=>'oktober',11=>'november',12=>'december'];
$aantal_beschikbaar = 0; $aantal_niet = 0;
foreach ($per_dag as $info) { $info['beschikbaar'] ? $aantal_beschikbaar++ : $aantal_niet++; }
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Beschikbaarheid per dag — <?= h($naam) ?></title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f1f5f9;color:#1e293b;margin:0;padding:24px;line-height:1.5}
  .wrap{max-width:760px;margin:0 auto}
  h1{font-size:20px;margin:0 0 4px}
  .meta{color:#64748b;font-size:14px;margin:0 0 4px}
  .knoppen{margin:16px 0}
  .knop{display:inline-block;background:#054d8a;color:#fff;text-decoration:none;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:500;margin-right:8px}
  .knop.licht{background:#fff;color:#054d8a;border:1px solid #cbd5e1}
  .telling{display:flex;gap:12px;margin:16px 0}
  .kaart{flex:1;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;text-align:center}
  .kaart .n{font-size:24px;font-weight:700}
  .kaart.ja .n{color:#0f9d58} .kaart.nee .n{color:#dc2626}
  .kaart .l{font-size:12px;color:#64748b}
  table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;font-size:13px}
  th,td{padding:7px 12px;text-align:left;border-bottom:1px solid #eef2f7}
  th{background:#f8fafc;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
  tr.maand-kop td{background:#f1f5f9;font-weight:600;color:#475569}
  .status.ja{color:#0f9d58;font-weight:600}
  .status.nee{color:#dc2626;font-weight:600}
  .hard{font-size:11px;background:#fef2f2;color:#dc2626;padding:1px 7px;border-radius:9px}
  .zacht{font-size:11px;background:#fffbeb;color:#d97706;padding:1px 7px;border-radius:9px}
  .weekend td{background:#fcfcfd}
</style>
</head>
<body>
<div class="wrap">
  <h1>Beschikbaarheid per dag</h1>
  <p class="meta"><strong><?= h($naam) ?></strong> · seizoen <?= h($sol['seizoen_naam']) ?></p>
  <p class="meta">Periode: <?= h(date('d-m-Y', strtotime($van))) ?> t/m <?= h(date('d-m-Y', strtotime($tot))) ?></p>

  <div class="knoppen">
    <a class="knop" href="?id=<?= $id ?>&formaat=json" target="_blank">Bekijk als JSON (API-formaat)</a>
    <a class="knop licht" href="<?= APP_URL ?>/admin/beschikbaarheid.php">← Terug naar beschikbaarheid</a>
  </div>

  <div class="telling">
    <div class="kaart ja"><div class="n"><?= $aantal_beschikbaar ?></div><div class="l">dagen beschikbaar</div></div>
    <div class="kaart nee"><div class="n"><?= $aantal_niet ?></div><div class="l">dagen niet beschikbaar</div></div>
  </div>

  <table>
    <thead><tr><th>Datum</th><th>Dag</th><th>Status</th><th>Reden</th></tr></thead>
    <tbody>
      <?php
      $huidige_maand = null;
      foreach ($per_dag as $datum => $info):
          $ts = strtotime($datum);
          $maand = (int)date('n', $ts);
          $weekdag = (int)date('N', $ts);
          if ($maand !== $huidige_maand):
              $huidige_maand = $maand; ?>
              <tr class="maand-kop"><td colspan="4"><?= h(ucfirst($maanden_nl[$maand])) ?> <?= h(date('Y', $ts)) ?></td></tr>
          <?php endif; ?>
          <tr class="<?= $weekdag >= 6 ? 'weekend' : '' ?>">
            <td><?= h(date('d-m', $ts)) ?></td>
            <td><?= h($dagen_nl[$weekdag]) ?></td>
            <td>
              <?php if ($info['beschikbaar']): ?>
                <span class="status ja">✓ beschikbaar</span>
              <?php else: ?>
                <span class="status nee">✗ niet</span>
                <?php if ($info['hardheid'] === 'hard'): ?><span class="hard">hard</span>
                <?php elseif ($info['hardheid'] === 'zacht'): ?><span class="zacht">zacht</span><?php endif; ?>
              <?php endif; ?>
            </td>
            <td><?= h($info['reden'] ?: '—') ?></td>
          </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body>
</html>

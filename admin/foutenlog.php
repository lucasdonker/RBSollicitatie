<?php
/**
 * Admin — Foutenlog
 * Toont de laatste fouten uit de error_log-tabel, zodat problemen herleidbaar zijn.
 * Elke fout heeft een referentienummer dat ook op de foutpagina aan de gebruiker wordt getoond.
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_rol('admin');

$db = db();

// Oud loggen opruimen op verzoek
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leegmaken'])) {
    csrf_check();
    try { $db->exec("DELETE FROM error_log"); flash('succes', 'Foutenlog geleegd.'); }
    catch (\Exception $e) { flash('fout', 'Kon het log niet legen.'); }
    redirect(APP_URL . '/admin/foutenlog.php');
}

// Fouten ophalen (laatste 200)
$fouten = [];
try {
    $fouten = $db->query("SELECT * FROM error_log ORDER BY id DESC LIMIT 200")->fetchAll();
} catch (\Exception $e) {
    // tabel bestaat mogelijk nog niet
}

$pagina_titel = 'Foutenlog';
$actieve_nav  = 'systeem';
require __DIR__ . '/layout_top.php';
?>
<div class="page-body">

  <div class="card" style="margin-bottom:14px">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
      <div style="font-size:13px;color:var(--tekst-zacht)">
        De laatste <?= count($fouten) ?> fouten. Elke fout heeft een <strong>ref</strong>-nummer dat ook op de foutpagina aan de gebruiker wordt getoond — zo koppel je een melding aan de echte oorzaak.
      </div>
      <?php if (!empty($fouten)): ?>
      <form method="POST" style="margin:0" onsubmit="return confirm('Het volledige foutenlog wissen?')">
        <?= csrf_input() ?>
        <button type="submit" name="leegmaken" value="1" class="btn btn-outline btn-sm">🗑 Log legen</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($fouten)): ?>
  <div class="card"><div class="card-body">
    <p style="font-size:14px;color:var(--tekst-zacht);margin:0">✓ Geen fouten geregistreerd. Mooi zo.</p>
  </div></div>
  <?php else: ?>
  <div class="card">
    <div class="card-body" style="padding:0">
      <?php foreach ($fouten as $f):
        // Ref-nummer uit het bericht halen (staat vooraan als [ref xxxx])
        $ref = '';
        if (preg_match('/^\[ref ([a-f0-9]+)\]\s*(.*)$/s', $f['bericht'], $m)) {
            $ref = $m[1];
            $bericht_schoon = $m[2];
        } else {
            $bericht_schoon = $f['bericht'];
        }
      ?>
      <div style="padding:12px 16px;border-bottom:1px solid var(--rand)">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
          <span style="font-family:monospace;font-size:12px;font-weight:700;color:var(--rood)"><?= h($f['code']) ?></span>
          <?php if ($ref): ?><span style="font-family:monospace;font-size:11px;background:var(--grijs-bg);padding:1px 7px;border-radius:4px;color:var(--tekst)">ref <?= h($ref) ?></span><?php endif; ?>
          <span style="font-size:11px;color:var(--grijs);margin-left:auto"><?= h($f['aangemaakt_op']) ?></span>
        </div>
        <div style="font-size:13px;color:var(--tekst);margin-bottom:4px"><?= h($bericht_schoon) ?></div>
        <div style="font-size:11px;color:var(--tekst-zacht);display:flex;gap:12px;flex-wrap:wrap">
          <?php if ($f['bestand']): ?><span>📄 <?= h(basename($f['bestand'])) ?><?= $f['regel'] ? ':' . (int)$f['regel'] : '' ?></span><?php endif; ?>
          <?php if ($f['url']): ?><span>🔗 <?= h($f['url']) ?></span><?php endif; ?>
          <?php if (!empty($f['gebruiker'])): ?><span>👤 <?= h($f['gebruiker']) ?></span><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

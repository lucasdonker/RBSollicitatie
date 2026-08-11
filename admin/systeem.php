<?php
/**
 * Admin — Systeem & Updates
 * Toont de geïnstalleerde versie, de changelog en of alle tabellen aanwezig zijn.
 * Handig direct na een upload: draaien de automatische migraties?
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_rol('admin');

$pagina_titel = 'Systeem & Updates';
$actieve_nav  = 'systeem';

// Verwachte tabellen (worden automatisch aangemaakt bij gebruik)
$verwacht = [
    'Kern' => ['personen', 'sollicitaties', 'seizoenen', 'beheerders', 'instellingen'],
    'Beschikbaarheid' => ['beschikbaarheid_profiel', 'beschikbaarheid_blok', 'beschikbaarheid_afwezig',
                          'beschikbaarheid_verzoek', 'beschikbaarheid_correctie'],
    'Competenties'    => ['competenties', 'authentik_koppeling'],
    'Planning'        => ['loon_planning', 'loon_scenario'],
];

$aanwezig = [];
$db_fout = null;
try {
    $aanwezig = db()->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $db_fout = $e->getMessage();
}

// Migraties forceren (handig na een upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'migreer') {
    csrf_check();
    $gedraaid = [];
    foreach (['instellingen_auto_setup', 'beschikbaarheid2_auto_setup', 'competenties_auto_setup', 'loonplan_auto_setup'] as $fn) {
        if (function_exists($fn)) { try { $fn(); $gedraaid[] = $fn; } catch (Exception $e) {} }
    }
    flash('succes', 'Migraties uitgevoerd: ' . (count($gedraaid) ?: 0) . ' setup-routines.');
    redirect(APP_URL . '/admin/systeem.php');
}

$changelog = function_exists('app_changelog') ? app_changelog() : [];

require __DIR__ . '/layout_top.php';
?>

<div class="card" style="margin-bottom:14px">
  <div class="card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div>
      <div style="font-size:12px;color:var(--tekst-zacht)">Geïnstalleerde versie</div>
      <div style="font-size:24px;font-weight:700;color:var(--navy)"><?= h(APP_VERSIE) ?></div>
    </div>
    <div style="flex:1"></div>
    <a href="<?= APP_URL ?>/diagnose.php" class="btn btn-outline btn-sm" target="_blank">🔍 Diagnose draaien</a>
    <form method="POST" style="margin:0">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="migreer">
      <button type="submit" class="btn btn-outline btn-sm" title="Voer alle automatische database-migraties uit">⚙️ Migraties uitvoeren</button>
    </form>
  </div>
</div>

<!-- Tabelstatus -->
<div class="card" style="margin-bottom:14px">
  <div class="card-header"><div class="card-title">Database</div></div>
  <div class="card-body">
    <?php if ($db_fout): ?>
      <div style="color:var(--rood)">Kan de database niet lezen: <?= h($db_fout) ?></div>
    <?php else: ?>
      <p style="font-size:12.5px;color:var(--tekst-zacht);margin-bottom:12px">
        Ontbrekende tabellen worden automatisch aangemaakt zodra de bijbehorende functie voor het eerst gebruikt wordt.
        Klopt er iets niet na een upload? Klik dan op "Migraties uitvoeren".
      </p>
      <?php foreach ($verwacht as $groep => $tabellen): ?>
      <div style="margin-bottom:10px">
        <div style="font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:4px"><?= h($groep) ?></div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach ($tabellen as $t): $ok = in_array($t, $aanwezig, true); ?>
          <span style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;padding:4px 10px;border-radius:20px;
                       background:<?= $ok ? 'var(--groen-bg)' : 'var(--grijs-bg)' ?>;
                       color:<?= $ok ? 'var(--groen)' : 'var(--grijs)' ?>;
                       border:1px solid <?= $ok ? 'var(--groen)' : 'var(--rand)' ?>">
            <?= $ok ? '✓' : '○' ?> <?= h($t) ?>
          </span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Changelog -->
<div class="card">
  <div class="card-header"><div class="card-title">Changelog</div></div>
  <div class="card-body">
    <?php if (!$changelog): ?>
      <div style="color:var(--tekst-zacht)">Geen changelog beschikbaar.</div>
    <?php else: ?>
      <?php foreach ($changelog as $versie => $c): ?>
      <details <?= $versie === APP_VERSIE ? 'open' : '' ?> style="border-bottom:1px solid var(--rand-licht);padding:10px 0">
        <summary style="cursor:pointer;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span style="font-weight:700;color:var(--navy);font-size:14px"><?= h($versie) ?></span>
          <?php if ($versie === APP_VERSIE): ?>
            <span style="font-size:10px;font-weight:700;color:#fff;background:var(--groen);padding:2px 8px;border-radius:4px">HUIDIG</span>
          <?php endif; ?>
          <span style="font-size:13px;color:var(--tekst)"><?= h($c['titel'] ?? '') ?></span>
          <span style="flex:1"></span>
          <span style="font-size:11px;color:var(--grijs)"><?= h($c['datum'] ?? '') ?></span>
        </summary>
        <ul style="margin:8px 0 0;padding-left:20px;font-size:12.5px;line-height:1.8;color:var(--tekst-zacht)">
          <?php foreach ($c['punten'] ?? [] as $p): ?>
          <li><?= h($p) ?></li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>

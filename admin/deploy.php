<?php
/**
 * Paal 13 — Deploy & Versiebeheer
 *
 * Upload ZIP met nieuwe bestanden. Maakt automatisch backup, plaatst bestanden,
 * draait migraties, werkt versie bij. Rollback zet een eerdere backup terug.
 */

// Geen output buffering issues
@ini_set('memory_limit', '256M');
@set_time_limit(120);

$config_path = __DIR__ . '/../includes/config.php';
if (!file_exists($config_path)) {
    die('<p style="font-family:sans-serif;padding:40px">Niet geïnstalleerd.</p>');
}
require_once __DIR__ . '/../includes/functions.php';

// Alleen administrators
vereist_rol('admin');
$bhd = huidige_beheerder();

$root        = realpath(__DIR__ . '/..');
$backup_dir  = $root . '/_backups';
if (!is_dir($backup_dir)) @mkdir($backup_dir, 0755, true);

// Beschermde paden — NOOIT overschrijven of in backup zetten
// Relatief vanaf project root, met forward slashes
$beschermd_paden = [
    'includes/config.php',  // Database config blijft altijd staan
    '_backups',             // Backups overschrijven we niet
    'uploads',              // Geüploade BLK-fotos blijven
    '.htaccess',            // Server config blijft
];

$melding = '';
$fout = '';
$log = [];

// ── Helper: pad-relatief tot root ─────────────────────────────
function deploy_relatief(string $abs, string $root): string {
    $abs  = str_replace('\\', '/', $abs);
    $root = str_replace('\\', '/', $root);
    if (str_starts_with($abs, $root)) {
        return ltrim(substr($abs, strlen($root)), '/');
    }
    return $abs;
}

// ── Helper: moet dit pad beschermd worden? ────────────────────
function deploy_is_beschermd(string $rel_pad, array $beschermd): bool {
    $rel_pad = trim(str_replace('\\', '/', $rel_pad), '/');
    foreach ($beschermd as $b) {
        $b = trim($b, '/');
        if ($rel_pad === $b || str_starts_with($rel_pad . '/', $b . '/')) {
            return true;
        }
    }
    return false;
}

// ── Recursief verwijderen met bescherming ─────────────────────
// Verwijdert alles in $dir behalve beschermde paden. Gebruikt voor rollback-cleanup.
function deploy_verwijder_met_bescherming(string $dir, string $root, array $beschermd): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $pad = $dir . '/' . $item;
        $rel = deploy_relatief($pad, $root);

        if (deploy_is_beschermd($rel, $beschermd)) continue;

        if (is_dir($pad)) {
            deploy_verwijder_met_bescherming($pad, $root, $beschermd);
            // Verwijder lege map (als hij echt leeg is)
            @rmdir($pad);
        } else {
            @unlink($pad);
        }
    }
}

// ── Recursief kopiëren met bescherming op BRON-pad ────────────
// Belangrijk: we checken of het BRON-relatieve pad beschermd is, NIET het doel.
// Dat is essentieel voor backups (die zelf in _backups/ landen).
function deploy_kopieer_dir(string $src, string $dst, string $src_root, array $beschermd, array &$log): int {
    if (!is_dir($src)) return 0;
    if (!is_dir($dst)) @mkdir($dst, 0755, true);
    $teller = 0;

    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') continue;
        $src_pad = $src . '/' . $item;
        $dst_pad = $dst . '/' . $item;
        $rel_src = deploy_relatief($src_pad, $src_root);

        if (deploy_is_beschermd($rel_src, $beschermd)) {
            continue;
        }

        if (is_dir($src_pad)) {
            $teller += deploy_kopieer_dir($src_pad, $dst_pad, $src_root, $beschermd, $log);
        } else {
            if (@copy($src_pad, $dst_pad)) {
                $teller++;
            } else {
                $log[] = "⚠ Kon niet kopiëren: $rel_src";
            }
        }
    }
    return $teller;
}

// ── Backup maken van huidige installatie ──────────────────────
function deploy_maak_backup(string $root, string $backup_dir, array $beschermd): string {
    $versie = defined('APP_VERSIE') ? APP_VERSIE : 'onbekend';
    $naam = 'backup_' . date('Ymd_His') . '_v' . $versie;
    $pad  = $backup_dir . '/' . $naam;
    @mkdir($pad, 0755, true);

    $dummy = [];
    deploy_kopieer_dir($root, $pad, $root, ['_backups'], $dummy);
    return $naam;
}

// ── Migraties draaien ─────────────────────────────────────────
function deploy_draai_migraties(string $root): array {
    $log = [];
    try {
        $db = db();
        $db->exec("CREATE TABLE IF NOT EXISTS `migraties` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `versie` VARCHAR(20) NOT NULL UNIQUE,
            `beschrijving` VARCHAR(200) NOT NULL,
            `uitgevoerd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $bestanden = glob($root . '/sql/migratie_*.sql') ?: [];
        sort($bestanden);
        $gedaan = array_column($db->query("SELECT versie FROM migraties")->fetchAll(), 'versie');

        foreach ($bestanden as $f) {
            if (!preg_match('/migratie_(\d+)_(.+)\.sql$/', basename($f), $m)) continue;
            if (in_array($m[1], $gedaan, true)) continue;

            $sql = file_get_contents($f);
            // Quote-aware splitter: respecteert ' " en -- comments tot regeleind
            $statements = [];
            $buf = '';
            $in_single = false;
            $in_double = false;
            $in_comment = false;
            $len = strlen($sql);
            for ($i = 0; $i < $len; $i++) {
                $c = $sql[$i];
                $nx = $sql[$i+1] ?? '';
                if ($in_comment) {
                    if ($c === "\n") $in_comment = false;
                    $buf .= $c; continue;
                }
                if (!$in_single && !$in_double && $c === '-' && $nx === '-') {
                    $in_comment = true; $buf .= $c; continue;
                }
                if (!$in_double && $c === "'") {
                    if ($in_single && $nx === "'") { $buf .= "''"; $i++; continue; }
                    $in_single = !$in_single;
                }
                if (!$in_single && $c === '"') $in_double = !$in_double;

                if ($c === ';' && !$in_single && !$in_double) {
                    $s = trim($buf);
                    if ($s !== '') $statements[] = $s;
                    $buf = '';
                    continue;
                }
                $buf .= $c;
            }
            $s = trim($buf);
            if ($s !== '') $statements[] = $s;

            $fout_in_migratie = null;
            foreach ($statements as $s) {
                $cleaned = preg_replace('/^--[^\n]*$/m', '', $s);
                if (trim($cleaned) === '') continue;
                try { $db->exec($s); }
                catch (\Exception $e) { $fout_in_migratie = $e->getMessage(); break; }
            }
            if ($fout_in_migratie) {
                $log[] = "✗ Migratie {$m[1]} fout: $fout_in_migratie";
            } else {
                $db->prepare("INSERT IGNORE INTO migraties (versie, beschrijving) VALUES (?, ?)")
                   ->execute([$m[1], str_replace('_', ' ', $m[2])]);
                $log[] = "✓ Migratie {$m[1]}: " . str_replace('_', ' ', $m[2]);
            }
        }

        if (empty($log)) $log[] = '— Geen nieuwe migraties';
    } catch (\Exception $e) {
        $log[] = '✗ Migratie-systeem fout: ' . $e->getMessage();
    }
    return $log;
}

// ── POST: deploy ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deploy'])) {
    csrf_check();
    if (!isset($_FILES['pakket']) || $_FILES['pakket']['error'] !== UPLOAD_ERR_OK) {
        $fout = 'Geen geldig ZIP-bestand geüpload.';
    } elseif (!class_exists('ZipArchive')) {
        $fout = 'ZipArchive niet beschikbaar op deze server.';
    } else {
        // 1. Backup
        $backup_naam = deploy_maak_backup($root, $backup_dir, $beschermd_paden);
        $log[] = "📦 Backup gemaakt: $backup_naam";

        // 2. Uitpakken naar temp
        $tmp = sys_get_temp_dir() . '/deploy_' . uniqid();
        @mkdir($tmp, 0755, true);
        $zip = new ZipArchive();
        if ($zip->open($_FILES['pakket']['tmp_name']) !== true) {
            $fout = 'Kon ZIP niet openen.';
        } else {
            $zip->extractTo($tmp);
            $zip->close();
            $log[] = "📂 ZIP uitgepakt";

            // Detecteer of inhoud in een submap zit
            $items = array_values(array_diff(scandir($tmp), ['.', '..']));
            $bron = $tmp;
            if (count($items) === 1 && is_dir("$tmp/{$items[0]}")) {
                $bron = "$tmp/{$items[0]}";
            }

            // 3. Kopiëren met bescherming
            // Bron-root = $bron (uitgepakte ZIP) zodat paden relatief daaraan worden gecheckt
            $aantal = deploy_kopieer_dir($bron, $root, $bron, $beschermd_paden, $log);
            $log[] = "✓ $aantal bestanden geplaatst (config.php, uploads, backups beschermd)";

            // 4. Migraties
            $log = array_merge($log, deploy_draai_migraties($root));

            // 5. Versie / setup
            if (function_exists('instellingen_auto_setup')) {
                try {
                    instellingen_auto_setup(true);
                    $log[] = "✓ Database bijgewerkt naar versie " . (defined('APP_VERSIE') ? APP_VERSIE : '?');
                } catch (\Exception $e) {
                    $log[] = "⚠ Setup fout: " . $e->getMessage();
                }
            }

            // 6. Tijdelijke map opruimen
            deploy_verwijder_dir($tmp);

            $melding = '✅ Deploy voltooid.';
        }
    }
}

// ── Verwijder map recursief ───────────────────────────────────
function deploy_verwijder_dir(string $pad): void {
    if (!is_dir($pad)) { @unlink($pad); return; }
    foreach (scandir($pad) as $item) {
        if ($item === '.' || $item === '..') continue;
        $sub = $pad . '/' . $item;
        is_dir($sub) ? deploy_verwijder_dir($sub) : @unlink($sub);
    }
    @rmdir($pad);
}

// ── POST: rollback ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rollback'])) {
    csrf_check();
    $naam = basename($_POST['backup'] ?? '');
    $pad  = $backup_dir . '/' . $naam;

    if (!$naam || !is_dir($pad)) {
        $fout = 'Backup niet gevonden: ' . htmlspecialchars($naam);
    } else {
        // 1. Veiligheidsbackup van huidige staat (zodat we deze rollback ook kunnen terugdraaien)
        $veilig = deploy_maak_backup($root, $backup_dir, $beschermd_paden);
        $log[] = "🛡 Huidige staat veiliggesteld als: $veilig";

        // 2. Ruim huidige installatie op (behalve beschermde paden zoals config.php en uploads)
        //    Dit voorkomt dat nieuwe bestanden uit v2 blijven hangen na rollback
        deploy_verwijder_met_bescherming($root, $root, $beschermd_paden);
        $log[] = "🧹 Huidige bestanden verwijderd (config.php en uploads beschermd)";

        // 3. Terugzetten — bron-root = backup-pad, zodat config.php in de backup
        //    (op pad "includes/config.php") herkend wordt als beschermd en NIET wordt teruggezet
        //    (we behouden de huidige werkende config.php)
        $aantal = deploy_kopieer_dir($pad, $root, $pad, $beschermd_paden, $log);
        $log[] = "↺ $aantal bestanden teruggezet vanuit $naam";

        // 3. Forceer setup zodat DB-versie weer overeenkomt met code-versie
        if (function_exists('instellingen_auto_setup')) {
            try {
                instellingen_auto_setup(true);
                $log[] = "✓ Database synchronisatie";
            } catch (\Exception $e) {
                $log[] = "⚠ Setup fout: " . $e->getMessage();
            }
        }

        $melding = '✅ Rollback voltooid.';
    }
}

// ── POST: backup verwijderen ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verwijder_backup'])) {
    csrf_check();
    $naam = basename($_POST['backup'] ?? '');
    $pad  = $backup_dir . '/' . $naam;
    if ($naam && is_dir($pad) && str_starts_with($naam, 'backup_')) {
        deploy_verwijder_dir($pad);
        $melding = "Backup verwijderd: $naam";
    }
}

// Lijst backups
$backups = [];
foreach (glob($backup_dir . '/backup_*') ?: [] as $b) {
    if (is_dir($b)) {
        $backups[] = [
            'naam'  => basename($b),
            'tijd'  => date('Y-m-d H:i', filemtime($b)),
            'bytes' => null, // dirsize kan langzaam zijn, sla over
        ];
    }
}
usort($backups, fn($a,$b) => strcmp($b['naam'], $a['naam']));

$huidige_versie = defined('APP_VERSIE') ? APP_VERSIE : '?';

$pagina_titel = 'Deploy & Versiebeheer';
$actieve_nav  = 'deploy';
require __DIR__ . '/layout_top.php';
?>
<style>
  .log-blok { font-family:'SF Mono',Menlo,monospace;font-size:12px;line-height:1.9; }
  .log-blok > div { padding:2px 0; }
  .backup-rij { display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--rand); }
  .backup-rij:last-child { border-bottom: none; }
  .backup-naam { flex:1;font-family:monospace;font-size:12px;color:var(--tekst); }
  .backup-tijd { font-size:11px;color:var(--grijs); }
</style>
<div class="page-body">

  <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap">
    <span class="badge badge-oranje">Versie <?= htmlspecialchars($huidige_versie) ?></span>
  </div>

  <?php if ($melding): ?>
  <div class="flash flash-succes"><?= htmlspecialchars($melding) ?></div>
  <?php endif; ?>
  <?php if ($fout): ?>
  <div class="flash flash-fout"><?= htmlspecialchars($fout) ?></div>
  <?php endif; ?>

  <?php if (!empty($log)): ?>
  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><div class="card-title">Resultaat</div></div>
    <div class="card-body log-blok">
      <?php foreach ($log as $r): ?>
      <div><?= htmlspecialchars($r) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Upload -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><div class="card-title">Nieuwe versie uploaden</div></div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:14px">
        Upload een ZIP. Voor het deployen wordt automatisch backup gemaakt.
        <code>config.php</code>, <code>uploads/</code> en backups blijven ongemoeid.
      </p>
      <form method="POST" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="form-groep">
          <label>ZIP-bestand</label>
          <input type="file" name="pakket" accept=".zip" required>
        </div>
        <button type="submit" name="deploy" value="1" class="btn btn-primary"
                onclick="return confirm('Nieuwe versie deployen? Er wordt eerst een backup gemaakt.')">
          🚀 Deploy nu
        </button>
      </form>
    </div>
  </div>

  <!-- Backups & Rollback -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Backups (<?= count($backups) ?>)</div>
    </div>
    <div class="card-body">
      <?php if (empty($backups)): ?>
      <p style="font-size:13px;color:var(--grijs)">Nog geen backups.</p>
      <?php else: ?>
      <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:12px">
        Zet een eerdere versie terug. De huidige staat wordt eerst veiliggesteld.
      </p>
      <?php foreach ($backups as $b): ?>
      <div class="backup-rij">
        <div style="flex:1">
          <div class="backup-naam"><?= htmlspecialchars($b['naam']) ?></div>
          <div class="backup-tijd"><?= htmlspecialchars($b['tijd']) ?></div>
        </div>
        <form method="POST" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="backup" value="<?= htmlspecialchars($b['naam']) ?>">
          <button type="submit" name="rollback" value="1" class="btn btn-outline btn-sm"
                  onclick="return confirm('Terugzetten naar <?= htmlspecialchars($b['naam']) ?>?\nDe huidige staat wordt eerst veiliggesteld.')">
            ↺ Terugzetten
          </button>
        </form>
        <form method="POST" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="backup" value="<?= htmlspecialchars($b['naam']) ?>">
          <button type="submit" name="verwijder_backup" value="1" class="btn btn-rood btn-sm"
                  onclick="return confirm('Backup <?= htmlspecialchars($b['naam']) ?> definitief verwijderen?')">
            🗑
          </button>
        </form>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

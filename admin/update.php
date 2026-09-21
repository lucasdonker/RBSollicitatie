<?php
/**
 * Database update systeem — zelfstandig
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

$config_path = __DIR__ . '/../includes/config.php';
if (!file_exists($config_path)) {
    die("Niet geïnstalleerd.");
}
require_once $config_path;

// Beveiliging: alleen een ingelogde admin mag database-updates draaien.
require_once __DIR__ . '/../includes/functions.php';
vereist_rol('admin');

// Inline DB connectie
function upd_db(): PDO {
    static $p = null;
    if (!$p) $p = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    return $p;
}

// Zorg dat migraties tabel bestaat
upd_db()->exec("CREATE TABLE IF NOT EXISTS `migraties` (`id` INT AUTO_INCREMENT PRIMARY KEY, `versie` VARCHAR(20) NOT NULL UNIQUE, `beschrijving` VARCHAR(200) NOT NULL, `uitgevoerd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Vind migratiebestanden
$bestanden = glob(__DIR__ . '/../sql/migratie_*.sql');
sort($bestanden);
$gedaan = array_column(upd_db()->query("SELECT versie FROM migraties")->fetchAll(), 'versie');

$pending = [];
foreach ($bestanden as $f) {
    if (preg_match('/migratie_(\d+)_(.+)\.sql$/', basename($f), $m) && !in_array($m[1], $gedaan)) {
        $pending[] = ['v' => $m[1], 'oms' => str_replace('_',' ',$m[2]), 'file' => $f];
    }
}

$uitgevoerd = []; $fouten = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uitvoer'])) {
    csrf_check();
    foreach ($pending as $mg) {
        try {
            $sql = file_get_contents($mg['file']);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $s) {
                if ($s && !str_starts_with(ltrim($s),'--')) upd_db()->exec($s);
            }
            upd_db()->prepare("INSERT IGNORE INTO migraties (versie,beschrijving) VALUES(?,?)")->execute([$mg['v'],$mg['oms']]);
            $uitgevoerd[] = $mg['v'] . ': ' . $mg['oms'];
        } catch (Exception $e) {
            $fouten[] = $mg['v'] . ': ' . $e->getMessage();
        }
    }
    $pending = [];
}

// System info
$info = ['PHP' => PHP_VERSION, 'MySQL' => upd_db()->query('SELECT VERSION()')->fetchColumn()];
try { $info['Seizoen'] = upd_db()->query("SELECT naam FROM seizoenen ORDER BY id DESC LIMIT 1")->fetchColumn() ?: '—'; } catch(Exception $e){}
try { $info['Sollicitaties'] = upd_db()->query("SELECT COUNT(*) FROM sollicitaties")->fetchColumn(); } catch(Exception $e){ $info['Sollicitaties'] = 'N/A'; }
$pagina_titel = 'Database updates';
$actieve_nav  = 'systeem';
require __DIR__ . '/layout_top.php';
?>
<style>
.upd-stat-rij{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.upd-stat-box{background:var(--grijs-bg);border-radius:8px;padding:10px 12px}
.upd-stat-box .n{font-size:15px;font-weight:700;color:var(--navy)}
.upd-stat-box .l{font-size:11px;color:var(--tekst-zacht)}
.upd-mig-rij{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--rand);font-size:13px}
.upd-mig-rij:last-child{border:none}
.upd-nr{font-size:11px;font-weight:700;padding:2px 7px;border-radius:4px}
</style>
<div class="page-body">

<?php if(!empty($uitgevoerd)): ?><div class="ds-melding ds-melding-ok">✅ Uitgevoerd: <?=implode(', ', $uitgevoerd)?></div><?php endif; ?>
<?php foreach($fouten as $f): ?><div class="ds-melding ds-melding-error">❌ <?=htmlspecialchars($f)?></div><?php endforeach; ?>

<div class="ds-kaart" style="margin-bottom:14px">
  <div class="bh-kaartkop"><h2>Systeeminfo</h2></div>
  <div class="bh-kaartbody">
    <div class="upd-stat-rij"><?php foreach($info as $k=>$v): ?><div class="upd-stat-box"><div class="l"><?=$k?></div><div class="n"><?=htmlspecialchars((string)$v)?></div></div><?php endforeach; ?></div>
  </div>
</div>

<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Database migraties</h2><span style="font-size:12px;color:var(--tekst-zacht)"><?=count($pending)?> pending</span></div>
  <div class="bh-kaartbody">
<?php foreach($bestanden as $bf): preg_match('/migratie_(\d+)_(.+)\.sql$/',basename($bf),$mx); $v=$mx[1]??'?'; $ok=in_array($v,$gedaan)||in_array($v.':', array_map(fn($s)=>explode(':',$s)[0].':',$uitgevoerd)); ?>
    <div class="upd-mig-rij">
      <span class="upd-nr" style="background:<?=$ok?'var(--groen-bg)':'var(--oranje-bg)'?>;color:<?=$ok?'#085041':'#c05a1a'?>"><?=$v?></span>
      <span style="flex:1"><?=htmlspecialchars(str_replace('_',' ',$mx[2]??''))?></span>
      <span style="font-size:11px;color:<?=$ok?'#085041':'#c05a1a'?>"><?=$ok?'✓ Klaar':'⏳ Pending'?></span>
    </div>
<?php endforeach; if(empty($bestanden)): ?><p style="font-size:13px;color:var(--tekst-zacht)">Geen migratiebestanden gevonden in sql/</p><?php endif; ?>
  </div>
</div>

<?php if(!empty($pending)): ?>
<form method="POST" style="margin-top:14px"><?= csrf_input() ?><button type="submit" name="uitvoer" value="1" class="ds-btn ds-btn-primair" onclick="return confirm('<?=count($pending)?> migratie(s) uitvoeren?')">▶ Voer <?=count($pending)?> migratie(s) uit</button></form>
<?php else: ?>
<div style="margin-top:14px;color:var(--tekst-zacht);font-size:13px">✓ Alles up-to-date</div>
<?php endif; ?>

</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>


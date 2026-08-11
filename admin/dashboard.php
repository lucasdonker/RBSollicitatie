<?php
require_once __DIR__ . '/../includes/functions.php';
require __DIR__ . '/layout_top.php';

$db      = db();
$seizoen = actief_seizoen();
$sid     = $seizoen['id'] ?? 0;

/* Funnel per fase */
$funnel_fases = [
    'aangemeld'           => ['label' => 'Aangemeld',            'kleur' => 'blauw',   'icoon' => '🎯'],
    'blk_ingevuld'        => ['label' => 'BLK ingevuld',         'kleur' => 'blauw',   'icoon' => '📝'],
    'uitgenodigd_selectie'=> ['label' => 'Uitgenodigd SD',       'kleur' => 'oranje',  'icoon' => '📧'],
    'selectiedag_geweest' => ['label' => 'Selectiedag geweest',  'kleur' => 'oranje',  'icoon' => '☀️'],
    'uitgenodigd_tw'      => ['label' => 'Uitgenodigd TW',       'kleur' => 'oranje',  'icoon' => '🏕️'],
    'aangenomen'          => ['label' => 'Aangenomen',           'kleur' => 'groen',   'icoon' => '✅'],
    'afgewezen'           => ['label' => 'Afgewezen',            'kleur' => 'rood',    'icoon' => '❌'],
    'verwijderd_door_sollicitant' => ['label' => 'Zelf verwijderd', 'kleur' => 'grijs', 'icoon' => '🗑'],
];

$funnel_data = [];
try {
    $q = $db->prepare("SELECT fase, COUNT(*) AS aantal FROM sollicitaties WHERE seizoen_id=? GROUP BY fase");
    $q->execute([$sid]);
    foreach ($q->fetchAll() as $r) $funnel_data[$r['fase']] = (int)$r['aantal'];
} catch (Exception $e) {}

// BLK ingevuld = subset van 'aangemeld' die BLK heeft
try {
    $q = $db->prepare("SELECT COUNT(DISTINCT s.id) FROM sollicitaties s JOIN blk_formulieren b ON b.sollicitatie_id=s.id WHERE s.seizoen_id=?");
    $q->execute([$sid]);
    $funnel_data['blk_ingevuld'] = (int)$q->fetchColumn();
} catch (Exception $e) {}

$totaal = $funnel_data['aangemeld'] ?? 0;
foreach ($funnel_fases as $k => &$f) {
    $f['aantal'] = $funnel_data[$k] ?? 0;
    $f['perc']   = $totaal > 0 ? round($f['aantal'] / max(1, $totaal) * 100) : 0;
}
unset($f);

/* Trend: aanmeldingen per week (laatste 12 weken) */
$trend = [];
try {
    $q = $db->prepare("
        SELECT YEARWEEK(aangemeld_op, 3) AS wk,
               DATE_FORMAT(MIN(aangemeld_op), '%d %b') AS label,
               COUNT(*) AS aantal
        FROM sollicitaties
        WHERE seizoen_id=? AND aangemeld_op >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
        GROUP BY wk ORDER BY wk
    ");
    $q->execute([$sid]);
    $trend = $q->fetchAll();
} catch (Exception $e) {}

$max_trend = max(1, ...array_map(fn($r) => (int)$r['aantal'], $trend ?: [['aantal'=>1]]));

/* System health */
$meldingen = dashboard_meldingen($sid);
$health = [];
try {
    $q = $db->query("SELECT COUNT(*) FROM mail_log WHERE status='wachten' AND verstuurd_op < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $health['mail_stuck'] = (int)$q->fetchColumn();
} catch (Exception $e) { $health['mail_stuck'] = 0; }

try {
    $q = $db->query("SELECT COUNT(*) FROM mail_log WHERE status='fout' AND verstuurd_op > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $health['mail_fout_24h'] = (int)$q->fetchColumn();
} catch (Exception $e) { $health['mail_fout_24h'] = 0; }

try {
    $q = $db->query("SELECT MAX(verstuurd_op) FROM mail_log");
    $health['mail_laatste'] = $q->fetchColumn();
} catch (Exception $e) { $health['mail_laatste'] = null; }

try {
    $q = $db->query("SELECT COUNT(*) FROM ondertekeningen WHERE ondertekend_op IS NULL AND aangemaakt_op < DATE_SUB(NOW(), INTERVAL 3 DAY)");
    $health['ond_open_3d'] = (int)$q->fetchColumn();
} catch (Exception $e) { $health['ond_open_3d'] = 0; }

$health_ok = ($health['mail_stuck'] === 0 && $health['mail_fout_24h'] < 3);
?>

<div class="container">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
      <h1 style="margin-bottom:4px">📊 Dashboard</h1>
      <div style="font-size:13px;color:var(--tekst-zacht)">Seizoen: <?= h($seizoen['naam'] ?? '—') ?></div>
    </div>
    <span class="badge <?= $health_ok ? 'badge-groen' : 'badge-rood' ?>"><?= $health_ok ? '✓ Systeem gezond' : '⚠ Aandacht nodig' ?></span>
  </div>

  <?php if (!empty($meldingen)): ?>
  <!-- Meldingen: dingen die aandacht vragen -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-header"><h2>🔔 Vraagt je aandacht</h2></div>
    <div class="card-body" style="padding:0">
      <?php foreach ($meldingen as $mld):
        $kleur = ['info'=>'var(--blauw)', 'waarschuwing'=>'var(--oranje)', 'urgent'=>'var(--rood)'][$mld['type']] ?? 'var(--tekst)';
        $icoon = ['info'=>'ℹ️', 'waarschuwing'=>'⚠️', 'urgent'=>'🚨'][$mld['type']] ?? '•';
      ?>
      <a href="<?= h($mld['link']) ?>" style="display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid var(--rand);text-decoration:none;color:var(--tekst)">
        <span style="flex-shrink:0;font-size:16px"><?= $icoon ?></span>
        <span style="flex:1;font-size:13.5px"><?= h($mld['tekst']) ?></span>
        <span style="flex-shrink:0;min-width:26px;height:26px;padding:0 8px;border-radius:13px;background:<?= $kleur ?>;color:#fff;font-size:12px;font-weight:600;display:flex;align-items:center;justify-content:center"><?= (int)$mld['aantal'] ?></span>
        <span style="flex-shrink:0;color:var(--grijs)">→</span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Kerncijfers -->
  <div class="card">
    <div class="card-header"><h2>Sollicitatie-funnel</h2></div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
        <?php foreach ($funnel_fases as $k => $f):
          $bg_kleur = "var(--{$f['kleur']}-bg)";
          $tekst_kleur = "var(--{$f['kleur']})";
          if ($f['kleur'] === 'grijs') { $bg_kleur = 'var(--grijs-bg)'; $tekst_kleur = 'var(--tekst-zacht)'; }
          // Doorkliklink: blk_ingevuld heeft eigen filter, rest filtert op fase
          $link = $k === 'blk_ingevuld'
              ? APP_URL . '/admin/sollicitanten.php?blk=ja'
              : APP_URL . '/admin/sollicitanten.php?fase=' . urlencode($k);
        ?>
          <a href="<?= $link ?>" style="display:block;background:<?= $bg_kleur ?>;padding:14px 16px;border-radius:8px;border-left:4px solid <?= $tekst_kleur ?>;text-decoration:none;transition:transform .1s,box-shadow .1s"
             onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='var(--shadow-md)'"
             onmouseout="this.style.transform='';this.style.boxShadow=''"
             title="Bekijk deze sollicitanten">
            <div style="font-size:11px;color:<?= $tekst_kleur ?>;font-weight:600;text-transform:uppercase;letter-spacing:.04em"><?= $f['icoon'] ?> <?= h($f['label']) ?></div>
            <div style="font-size:28px;font-weight:600;color:<?= $tekst_kleur ?>;line-height:1.1;margin-top:6px"><?= $f['aantal'] ?></div>
            <?php if ($k !== 'aangemeld' && $totaal > 0): ?>
              <div style="font-size:11px;color:<?= $tekst_kleur ?>;opacity:.7;margin-top:2px"><?= $f['perc'] ?>% van aanmeldingen</div>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Trend chart -->
  <?php if ($trend): ?>
  <div class="card">
    <div class="card-header"><h2>Aanmeldingen per week (laatste 12 weken)</h2></div>
    <div class="card-body">
      <div style="display:flex;align-items:end;gap:6px;height:150px;padding:0 4px">
        <?php foreach ($trend as $t):
          $h = max(4, round($t['aantal'] / $max_trend * 130));
        ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px" title="<?= h($t['label']) ?>: <?= (int)$t['aantal'] ?>">
            <div style="font-size:10px;color:var(--tekst-zacht);font-weight:600"><?= (int)$t['aantal'] ?></div>
            <div style="width:100%;height:<?= $h ?>px;background:linear-gradient(180deg,var(--oranje),var(--oranje-dim));border-radius:4px 4px 0 0"></div>
            <div style="font-size:10px;color:var(--tekst-zacht);text-align:center;line-height:1.2"><?= h($t['label']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- System health -->
  <div class="card">
    <div class="card-header"><h2>Systeemstatus</h2></div>
    <div class="card-body" style="padding:0">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
        <div style="padding:16px 18px;border-right:1px solid var(--rand);border-bottom:1px solid var(--rand)">
          <div style="font-size:11px;color:var(--tekst-zacht);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Mail wachtrij</div>
          <div style="font-size:22px;font-weight:600;color:<?= $health['mail_stuck'] > 0 ? 'var(--rood)' : 'var(--groen)' ?>">
            <?= $health['mail_stuck'] > 0 ? '⚠ ' . $health['mail_stuck'] . ' vast' : '✓ Alles verzonden' ?>
          </div>
        </div>
        <div style="padding:16px 18px;border-bottom:1px solid var(--rand)">
          <div style="font-size:11px;color:var(--tekst-zacht);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Mail-fouten (24u)</div>
          <div style="font-size:22px;font-weight:600;color:<?= $health['mail_fout_24h'] > 0 ? 'var(--rood)' : 'var(--groen)' ?>">
            <?= $health['mail_fout_24h'] > 0 ? '⚠ ' . $health['mail_fout_24h'] : '✓ 0' ?>
          </div>
        </div>
        <div style="padding:16px 18px;border-right:1px solid var(--rand);cursor:pointer" onclick="location.href='<?= APP_URL ?>/admin/ondertekeningen.php'" title="Naar ondertekeningen">
          <div style="font-size:11px;color:var(--tekst-zacht);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Ondertekeningen open >3 dagen →</div>
          <div style="font-size:22px;font-weight:600;color:<?= $health['ond_open_3d'] > 0 ? 'var(--oranje)' : 'var(--tekst)' ?>">
            <?= $health['ond_open_3d'] > 0 ? '⚠ ' . $health['ond_open_3d'] : '✓ 0' ?>
          </div>
        </div>
        <div style="padding:16px 18px">
          <div style="font-size:11px;color:var(--tekst-zacht);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Laatste mail verstuurd</div>
          <div style="font-size:13px;font-weight:500;color:var(--tekst);margin-top:4px">
            <?= $health['mail_laatste'] ? h(datum_nl(substr($health['mail_laatste'],0,10))) . ' ' . h(substr($health['mail_laatste'],11,5)) : 'Nog geen' ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Snelle acties -->
  <div class="card">
    <div class="card-header"><h2>Snelle acties</h2></div>
    <div class="card-body">
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= APP_URL ?>/admin/sollicitanten.php" class="btn btn-primary">👥 Sollicitanten beheren</a>
        <a href="<?= APP_URL ?>/admin/ondertekeningen.php" class="btn btn-outline">📄 Ondertekeningen</a>
        <a href="<?= APP_URL ?>/admin/selectiedag.php" class="btn btn-outline">📅 Selectiedag</a>
        <a href="<?= APP_URL ?>/admin/mail_templates.php" class="btn btn-outline">✉️ Mail templates</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>

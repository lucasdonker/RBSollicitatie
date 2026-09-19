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
    $q = $db->query("SELECT COUNT(*) FROM mail_log WHERE status IN ('wacht','bezig') AND verstuurd_op < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $health['mail_stuck'] = (int)$q->fetchColumn();
} catch (Exception $e) { $health['mail_stuck'] = 0; }

try {
    $q = $db->query("SELECT COUNT(*) FROM mail_log WHERE status='mislukt' AND verstuurd_op > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
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

/* Werving per bron/doelgroep (samengebracht vanuit de landingspagina-statistiek) */
$werving = [];
if (function_exists('pag_werving_overzicht')) {
    try { $werving = pag_werving_overzicht($seizoen['id'] ?? null); } catch (Exception $e) { $werving = []; }
}

/* Gemiddelde doorlooptijd: van aanmelding tot aangenomen (in dagen) */
$doorlooptijd = null;
try {
    $q = $db->prepare("
        SELECT AVG(DATEDIFF(aangenomen_op, aangemeld_op)) AS gem, COUNT(*) AS n
        FROM (
            SELECT aangemeld_op,
                   COALESCE(
                     (SELECT MAX(gewijzigd_op) FROM audit_log a
                       WHERE a.objecttype='sollicitatie' AND a.object_id=s.id AND a.details LIKE '%aangenomen%'),
                     bijgewerkt_op
                   ) AS aangenomen_op
            FROM sollicitaties s
            WHERE seizoen_id=? AND fase='aangenomen' AND aangemeld_op IS NOT NULL
        ) t
        WHERE aangenomen_op IS NOT NULL AND aangenomen_op >= aangemeld_op
    ");
    $q->execute([$seizoen['id'] ?? 0]);
    $rij = $q->fetch();
    if ($rij && $rij['n'] > 0 && $rij['gem'] !== null) $doorlooptijd = round((float)$rij['gem']);
} catch (Exception $e) { $doorlooptijd = null; }

/* Kerncijfers voor de bovenste rij */
$kpi_aanmeldingen = $funnel_data['aangemeld'] ?? 0;
$kpi_aangenomen   = $funnel_data['aangenomen'] ?? 0;
$kpi_conversie    = $kpi_aanmeldingen > 0 ? round($kpi_aangenomen / $kpi_aanmeldingen * 100) : 0;
?>

<div class="container">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
      <h1 style="margin-bottom:4px">📊 Dashboard</h1>
      <div style="font-size:13px;color:var(--tekst-zacht)">Seizoen: <?= h($seizoen['naam'] ?? '—') ?></div>
    </div>
    <span class="badge <?= $health_ok ? 'badge-groen' : 'badge-rood' ?>"><?= $health_ok ? '✓ Systeem gezond' : '⚠ Aandacht nodig' ?></span>
  </div>

  <!-- Kerncijfers -->
  <div class="stat-rij" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
    <div class="stat-box">
      <div class="stat-n"><?= (int)$kpi_aanmeldingen ?></div>
      <div class="stat-l">Aanmeldingen</div>
    </div>
    <div class="stat-box">
      <div class="stat-n" style="color:var(--oranje)"><?= (int)$kpi_aangenomen ?></div>
      <div class="stat-l">Aangenomen</div>
    </div>
    <div class="stat-box">
      <div class="stat-n"><?= (int)$kpi_conversie ?>%</div>
      <div class="stat-l">Conversie</div>
    </div>
    <div class="stat-box">
      <div class="stat-n"><?= $doorlooptijd !== null ? (int)$doorlooptijd : '–' ?><?= $doorlooptijd !== null ? '<span style="font-size:14px;font-weight:500"> dg</span>' : '' ?></div>
      <div class="stat-l">Gem. doorlooptijd</div>
    </div>
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

  <!-- Werving: waar komen de aanmeldingen vandaan? -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-header"><h2>Werving — waar komen aanmeldingen vandaan?</h2></div>
    <div class="card-body">
      <?php if (empty($werving)): ?>
        <p style="color:var(--tekst-zacht);font-size:14px;margin:0">
          Nog geen herkomst-gegevens dit seizoen. Zodra sollicitanten via een landingspagina of
          campagne binnenkomen (met een bron-code), zie je hier per bron hoeveel aanmeldingen en
          hoeveel daarvan zijn aangenomen.
        </p>
      <?php else: ?>
        <div class="tabel-scroll">
          <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
              <tr style="border-bottom:1px solid var(--rand);text-align:left">
                <th style="padding:8px 10px">Bron / doelgroep</th>
                <th style="padding:8px 10px;text-align:right">Aanmeldingen</th>
                <th style="padding:8px 10px;text-align:right">Aangenomen</th>
                <th style="padding:8px 10px;text-align:right">Conversie</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($werving as $w):
                $conv = $w['aanmeldingen'] > 0 ? round($w['aangenomen'] / $w['aanmeldingen'] * 100) : 0; ?>
              <tr style="border-bottom:1px solid var(--rand-licht,#eef1f5)">
                <td style="padding:9px 10px;font-weight:600"><?= h($w['bron']) ?></td>
                <td style="padding:9px 10px;text-align:right"><?= (int)$w['aanmeldingen'] ?></td>
                <td style="padding:9px 10px;text-align:right"><?= (int)$w['aangenomen'] ?></td>
                <td style="padding:9px 10px;text-align:right;color:var(--tekst-zacht)"><?= $conv ?>%</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:12px;font-size:12px;color:var(--tekst-zacht)">
          Tip: geef elke landingspagina een eigen bron-code, dan zie je precies welke doelgroep het
          beste werft.
        </div>
      <?php endif; ?>
    </div>
  </div>

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

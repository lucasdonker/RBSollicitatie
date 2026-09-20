<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Dashboard';
$actieve_nav  = 'dashboard';
// vereist_login() wordt aangeroepen in layout_top.php
require __DIR__ . '/layout_top.php';

$db  = db();
$sz  = actief_seizoen();
$sid = $sz ? (int)$sz['id'] : 0;

// Statistieken
$stats = ['t' => 0, 'nm' => 0, 'sel' => 0, 'tw' => 0, 'aan' => 0, 'af' => 0, 'ter' => 0];
if ($sid) {
    $st = $db->prepare("
        SELECT
            COUNT(*) AS t,
            SUM(fase = 'aangemeld') AS nm,
            SUM(fase IN('uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest')) AS sel,
            SUM(fase IN('uitgenodigd_tw','tw_geweest')) AS tw,
            SUM(fase = 'aangenomen') AS aan,
            SUM(fase = 'afgewezen') AS af,
            SUM(terugkerend = 1) AS ter
        FROM sollicitaties WHERE seizoen_id = ?
    ");
    $st->execute([$sid]);
    $stats = $st->fetch() ?: $stats;
}

// Recente aanmeldingen — niet ophalen wat deze rol niet mag zien
$recent = [];
if ($sid && mag('sollicitanten', 'lezen')) {
    $stmt = $db->prepare("
        SELECT s.id, s.fase, s.terugkerend, s.aangemeld_op,
               p.voornaam, p.tussenvoegsel, p.achternaam, p.email
        FROM sollicitaties s
        JOIN personen p ON p.id = s.persoon_id
        WHERE s.seizoen_id = ?
        ORDER BY s.aangemeld_op DESC LIMIT 8
    ");
    $stmt->execute([$sid]);
    $recent = $stmt->fetchAll();
}

// Acties vereist
$acties = [];
if ($sid) {
    $act = $db->prepare("SELECT fase, COUNT(*) n FROM sollicitaties WHERE seizoen_id = ? AND fase IN('aangemeld','selectiedag_geweest') GROUP BY fase");
    $act->execute([$sid]);
    foreach ($act->fetchAll() as $r) $acties[$r['fase']] = (int)$r['n'];
}
?>

<?php if ($sz): ?>
<!-- Seizoen status -->
<div class="seizoen-kaart <?= $sz['status'] === 'open' ? 'open-status' : '' ?>">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <div>
      <div class="seizoen-naam"><?= h($sz['naam']) ?></div>
      <div style="font-size:13px;color:var(--tekst-zacht)">
        Status: <strong><?= $sz['status'] === 'open' ? '✅ Open voor aanmeldingen' : '🔒 Gesloten' ?></strong>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ($sz['status'] === 'open'): ?>
      <form method="POST" action="<?= APP_URL ?>/admin/seizoen.php" style="display:inline">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="sluit">
        <input type="hidden" name="seizoen_id" value="<?= $sz['id'] ?>">
        <button type="submit" class="btn btn-rood btn-sm" onclick="return confirm('Seizoen sluiten?')">🔒 Sluiten</button>
      </form>
      <?php else: ?>
      <a href="<?= APP_URL ?>/admin/seizoen.php" class="btn btn-groen btn-sm">✅ Openen</a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/admin/seizoen.php" class="btn btn-outline btn-sm">Instellingen</a>
    </div>
  </div>
  <?php if ($sz['datum_selectiedag'] || $sz['datum_tw_start']): ?>
  <div class="seizoen-info-rij" style="margin-top:10px">
    <div class="item">
      <label style="font-size:11px;color:var(--tekst-zacht)">Selectiedag</label>
      <strong><?= $sz['datum_selectiedag'] ? datum_nl($sz['datum_selectiedag']) : 'Nog niet ingesteld' ?></strong>
    </div>
    <div class="item">
      <label style="font-size:11px;color:var(--tekst-zacht)">Trainingsweekend</label>
      <strong><?= $sz['datum_tw_start'] ? datum_nl($sz['datum_tw_start']) . ' t/m ' . datum_nl($sz['datum_tw_eind']) : 'Nog niet ingesteld' ?></strong>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Actie vereist -->
<?php if (!empty($acties)): ?>
<div class="card" style="margin-bottom:16px;border-left:4px solid var(--oranje)">
  <div class="card-body" style="padding:14px 18px">
    <div style="font-weight:600;color:var(--navy);margin-bottom:8px">⚡ Actie vereist</div>
    <?php if (!empty($acties['aangemeld'])): ?>
    <p style="font-size:13px;margin-bottom:6px">
      <strong><?= $acties['aangemeld'] ?></strong> aanmelding(en) wacht op beslissing selectiedag.
      <a href="<?= APP_URL ?>/admin/sollicitanten.php?fase=aangemeld" style="color:var(--oranje)">Bekijk →</a>
    </p>
    <?php endif; ?>
    <?php if (!empty($acties['selectiedag_geweest'])): ?>
    <p style="font-size:13px;margin-bottom:0">
      <strong><?= $acties['selectiedag_geweest'] ?></strong> kandidaat/kandidaten wachten op beslissing TW.
      <a href="<?= APP_URL ?>/admin/sollicitanten.php?fase=selectiedag_geweest" style="color:var(--oranje)">Bekijk →</a>
    </p>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Statistieken -->
<div class="stats-grid">
  <div class="stat-kaart" style="cursor:pointer" onclick="location.href='<?= APP_URL ?>/admin/sollicitanten.php'">
    <div class="label">Aanmeldingen</div>
    <div class="getal"><?= (int)$stats['t'] ?></div>
    <div class="sub">dit seizoen</div>
  </div>
  <div class="stat-kaart" style="cursor:pointer" onclick="location.href='<?= APP_URL ?>/admin/sollicitanten.php?fase=uitgenodigd_selectie'">
    <div class="label">Uitgenodigd selectiedag</div>
    <div class="getal"><?= (int)$stats['sel'] ?></div>
    <div class="sub">van <?= (int)$stats['t'] ?></div>
  </div>
  <div class="stat-kaart" style="cursor:pointer" onclick="location.href='<?= APP_URL ?>/admin/sollicitanten.php?fase=uitgenodigd_tw'">
    <div class="label">Trainingsweekend</div>
    <div class="getal"><?= (int)$stats['tw'] ?></div>
    <div class="sub">uitgenodigd</div>
  </div>
  <div class="stat-kaart" style="cursor:pointer" onclick="location.href='<?= APP_URL ?>/admin/sollicitanten.php?fase=aangenomen'">
    <div class="label">Aangenomen</div>
    <div class="getal" style="color:var(--groen)"><?= (int)$stats['aan'] ?></div>
    <div class="sub">nieuwe leden</div>
  </div>
  <div class="stat-kaart" style="cursor:pointer" onclick="location.href='<?= APP_URL ?>/admin/sollicitanten.php?fase=afgewezen'">
    <div class="label">Afgewezen</div>
    <div class="getal" style="color:var(--rood)"><?= (int)$stats['af'] ?></div>
  </div>
  <div class="stat-kaart" style="cursor:pointer" onclick="location.href='<?= APP_URL ?>/admin/sollicitanten.php?terug=1'">
    <div class="label">Terugkerend</div>
    <div class="getal" style="color:var(--paars)"><?= (int)$stats['ter'] ?></div>
  </div>
</div>

<!-- Systeemstatus -->
<?php
$sys = [];
try { $sys['mail_wacht'] = (int)$db->query("SELECT COUNT(*) FROM mail_queue WHERE status='wacht'")->fetchColumn(); } catch (\Exception $e) { $sys['mail_wacht'] = 0; }
try { $sys['mail_fout'] = (int)$db->query("SELECT COUNT(*) FROM mail_queue WHERE status='fout' AND aangemaakt_op > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(); } catch (\Exception $e) { $sys['mail_fout'] = 0; }
try { $sys['ond_open'] = (int)$db->query("SELECT COUNT(*) FROM ondertekeningen WHERE ondertekend_op IS NULL AND aangemaakt_op < DATE_SUB(NOW(), INTERVAL 3 DAY)")->fetchColumn(); } catch (\Exception $e) { $sys['ond_open'] = 0; }
$sys['cron_laatste'] = instelling('cron.laatste_run', '');
$sys['cron_ok'] = $sys['cron_laatste'] && strtotime($sys['cron_laatste']) > time() - 7200;
$sys_issues = ($sys['mail_fout'] > 0) || !$sys['cron_ok'] || ($sys['mail_wacht'] > 20);
?>
<div class="card" style="margin-bottom:16px<?= $sys_issues ? ';border-left:4px solid var(--rood)' : '' ?>">
  <div class="card-body" style="padding:12px 18px;display:flex;gap:24px;flex-wrap:wrap;align-items:center;font-size:12.5px">
    <span style="font-weight:600;color:var(--tekst-zacht)">Systeem:</span>
    <span title="Mails in wachtrij">📬 wachtrij: <strong style="color:<?= $sys['mail_wacht'] > 20 ? 'var(--oranje)' : 'var(--tekst)' ?>"><?= $sys['mail_wacht'] ?></strong></span>
    <span title="Mail-fouten laatste 24u">⚠️ fouten 24u: <strong style="color:<?= $sys['mail_fout'] > 0 ? 'var(--rood)' : 'var(--groen)' ?>"><?= $sys['mail_fout'] ?></strong></span>
    <a href="<?= APP_URL ?>/admin/ondertekeningen.php" style="color:inherit;text-decoration:none" title="Ondertekeningen langer dan 3 dagen open">✍️ open &gt;3d: <strong style="color:<?= $sys['ond_open'] > 0 ? 'var(--oranje)' : 'var(--groen)' ?>"><?= $sys['ond_open'] ?></strong></a>
    <span title="Laatste keer dat de cron/automatische taken draaiden">⏰ cron: 
      <?php if ($sys['cron_laatste']): ?>
        <strong style="color:<?= $sys['cron_ok'] ? 'var(--groen)' : 'var(--rood)' ?>"><?= $sys['cron_ok'] ? '✓' : '⚠' ?> <?= h(substr($sys['cron_laatste'], 11, 5)) ?></strong>
      <?php else: ?>
        <strong style="color:var(--rood)">⚠ nooit gedraaid</strong>
      <?php endif; ?>
    </span>
  </div>
</div>

<!-- Recente aanmeldingen — alleen voor wie de sollicitanten mag lezen. Een begeleider hoort
     geen persoonsgegevens te zien; wat je niet mag zien, staat er niet (ook niet leeg). -->
<?php if (mag('sollicitanten', 'lezen')): ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Recente aanmeldingen</div>
    <div style="display:flex;gap:8px">
      <a href="<?= APP_URL ?>/admin/sollicitant_nieuw.php" class="btn btn-groen btn-sm">+ Toevoegen</a>
      <a href="<?= APP_URL ?>/admin/sollicitanten.php" class="btn btn-outline btn-sm">Alle bekijken</a>
    </div>
  </div>
  <div class="tabel-wrap">
    <table class="data-tabel">
      <thead>
        <tr><th>Naam</th><th>Aangemeld</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (empty($recent)): ?>
        <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--grijs)">
          Nog geen aanmeldingen dit seizoen.
        </td></tr>
      <?php else: ?>
        <?php foreach ($recent as $r):
          $naam = volledige_naam($r);
          $init = strtoupper(substr($r['voornaam'], 0, 1) . substr($r['achternaam'], 0, 1));
        ?>
        <tr>
          <td>
            <div class="naam-cel">
              <div class="avatar" style="background:var(--navy)"><?= h($init) ?></div>
              <div>
                <div class="naam"><?= h($naam) ?></div>
                <div class="email"><?= h($r['email']) ?></div>
              </div>
            </div>
          </td>
          <td><?= datum_nl($r['aangemeld_op']) ?></td>
          <td>
            <span class="badge badge-<?= h(fase_kleur($r['fase'])) ?>"><?= h(fase_label($r['fase'])) ?></span>
            <?php if ($r['terugkerend']): ?>
            <span class="badge badge-terugkerend">↩</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-outline btn-sm" onclick="openPaneel(<?= (int)$r['id'] ?>)">Bekijk</button>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

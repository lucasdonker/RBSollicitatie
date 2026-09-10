<?php
/**
 * Auditlog — inzage-scherm. Doorzoekbaar overzicht van wie wat wanneer deed/zag.
 * Valt onder het 'systeem'-recht (Systeem, updates & logs).
 */
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Auditlogboek';
$actieve_nav  = 'systeem';
vereis_recht('systeem', 'lezen');

$db = db();
if (function_exists('audit_dag_auto_setup')) audit_dag_auto_setup();

// Filters
$f_wie    = trim($_GET['wie'] ?? '');
$f_actie  = trim($_GET['actie'] ?? '');
$f_object = trim($_GET['object'] ?? '');
$f_van    = trim($_GET['van'] ?? '');
$f_tot    = trim($_GET['tot'] ?? '');
$pagina   = max(1, (int)($_GET['p'] ?? 1));
$per_pagina = 50;

$where = []; $args = [];
if ($f_wie !== '')   { $where[] = "(wie_naam LIKE ? OR wie_id = ?)"; $args[] = "%$f_wie%"; $args[] = (int)$f_wie; }
if ($f_actie !== '') { $where[] = "actie = ?"; $args[] = $f_actie; }
if ($f_object !== ''){ $where[] = "objecttype = ?"; $args[] = $f_object; }
if ($f_van !== '')   { $where[] = "moment >= ?"; $args[] = $f_van . ' 00:00:00'; }
if ($f_tot !== '')   { $where[] = "moment <= ?"; $args[] = $f_tot . ' 23:59:59'; }
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totaal = 0; $regels = [];
try {
    $q = $db->prepare("SELECT COUNT(*) FROM audit_log $where_sql");
    $q->execute($args);
    $totaal = (int)$q->fetchColumn();

    $offset = ($pagina - 1) * $per_pagina;
    $q2 = $db->prepare("SELECT * FROM audit_log $where_sql ORDER BY moment DESC, id DESC LIMIT $per_pagina OFFSET $offset");
    $q2->execute($args);
    $regels = $q2->fetchAll();
} catch (Exception $e) { /* tabel bestaat mogelijk nog niet; leeg tonen */ }

$max_pagina = max(1, (int)ceil($totaal / $per_pagina));

// Labels
$actie_labels = [
    'bekeken' => '👁 bekeken', 'aangemaakt' => '➕ aangemaakt', 'gewijzigd' => '✏️ gewijzigd',
    'verwijderd' => '🗑 verwijderd', 'geexporteerd' => '📤 geëxporteerd', 'ingelogd' => '🔑 ingelogd',
    'uitgelogd' => '🚪 uitgelogd', 'mail_verstuurd' => '✉️ mail verstuurd',
];
function audit_wie_label(array $r): string {
    $naam = $r['wie_naam'] ?: ('#' . ($r['wie_id'] ?? '?'));
    $type = $r['wie_type'] === 'beheerder' ? '' : ' (' . h($r['wie_type']) . ')';
    return h($naam) . $type;
}

require __DIR__ . '/layout_top.php';
?>

<div style="max-width:1000px;margin:0 auto">
  <h1 style="font-size:22px;margin:0 0 4px">Auditlogboek</h1>
  <p style="color:var(--tekst-zacht);font-size:13.5px;margin:0 0 18px">
    Overzicht van wie wat wanneer deed. <?= number_format($totaal, 0, ',', '.') ?> gebeurtenissen.
  </p>

  <!-- Filters -->
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:18px;padding:14px;background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:10px">
    <div>
      <label style="display:block;font-size:11px;color:var(--tekst-zacht);margin-bottom:3px">Wie (naam of id)</label>
      <input type="text" name="wie" value="<?= h($f_wie) ?>" style="width:150px">
    </div>
    <div>
      <label style="display:block;font-size:11px;color:var(--tekst-zacht);margin-bottom:3px">Actie</label>
      <select name="actie" style="width:150px">
        <option value="">alle</option>
        <?php foreach (['bekeken','aangemaakt','gewijzigd','verwijderd','geexporteerd','ingelogd','mail_verstuurd'] as $a): ?>
        <option value="<?= $a ?>" <?= $f_actie===$a?'selected':'' ?>><?= h($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:11px;color:var(--tekst-zacht);margin-bottom:3px">Objecttype</label>
      <input type="text" name="object" value="<?= h($f_object) ?>" placeholder="bijv. sollicitant" style="width:140px">
    </div>
    <div>
      <label style="display:block;font-size:11px;color:var(--tekst-zacht);margin-bottom:3px">Van</label>
      <input type="date" name="van" value="<?= h($f_van) ?>">
    </div>
    <div>
      <label style="display:block;font-size:11px;color:var(--tekst-zacht);margin-bottom:3px">Tot</label>
      <input type="date" name="tot" value="<?= h($f_tot) ?>">
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <?php if ($where): ?><a href="<?= APP_URL ?>/admin/audit.php" class="btn btn-outline btn-sm">Wissen</a><?php endif; ?>
  </form>

  <!-- Tabel -->
  <?php if (!$regels): ?>
    <div style="padding:30px;text-align:center;color:var(--tekst-zacht);background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:10px">
      Geen gebeurtenissen gevonden<?= $where ? ' met deze filters' : ' — het logboek is nog leeg' ?>.
    </div>
  <?php else: ?>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:10px;overflow:hidden;font-size:13px">
      <thead>
        <tr style="background:var(--bg)">
          <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:var(--tekst-zacht)">Wanneer</th>
          <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:var(--tekst-zacht)">Wie</th>
          <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:var(--tekst-zacht)">Actie</th>
          <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:var(--tekst-zacht)">Object</th>
          <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;color:var(--tekst-zacht)">Details</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($regels as $r): ?>
        <tr style="border-top:1px solid var(--rand)">
          <td style="padding:7px 12px;white-space:nowrap;color:var(--tekst-zacht)"><?= h(date('d-m-Y H:i', strtotime($r['moment']))) ?></td>
          <td style="padding:7px 12px"><?= audit_wie_label($r) ?></td>
          <td style="padding:7px 12px;white-space:nowrap"><?= $actie_labels[$r['actie']] ?? h($r['actie']) ?></td>
          <td style="padding:7px 12px"><?= $r['objecttype'] ? h($r['objecttype']) . ($r['object_id'] ? ' #' . (int)$r['object_id'] : '') : '—' ?></td>
          <td style="padding:7px 12px;color:var(--tekst-zacht);font-size:12px;max-width:280px">
            <?php
              if ($r['details']) {
                  $d = json_decode($r['details'], true);
                  if (is_array($d)) {
                      $stukken = [];
                      foreach ($d as $k => $v) {
                          if (is_array($v) && isset($v['oud'], $v['nieuw'])) {
                              $stukken[] = h($k) . ': ' . h((string)$v['oud']) . ' → ' . h((string)$v['nieuw']);
                          } else {
                              $stukken[] = h($k) . ': ' . h(is_scalar($v) ? (string)$v : json_encode($v));
                          }
                      }
                      echo implode('<br>', $stukken);
                  } else { echo h($r['details']); }
              } else { echo '—'; }
            ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <!-- Paginering -->
    <?php if ($max_pagina > 1): ?>
    <div style="display:flex;gap:8px;align-items:center;justify-content:center;margin-top:16px;font-size:13px">
      <?php
        $basis = function($p) use ($f_wie,$f_actie,$f_object,$f_van,$f_tot) {
            return APP_URL . '/admin/audit.php?' . http_build_query(array_filter([
                'wie'=>$f_wie,'actie'=>$f_actie,'object'=>$f_object,'van'=>$f_van,'tot'=>$f_tot,'p'=>$p
            ]));
        };
      ?>
      <?php if ($pagina > 1): ?><a href="<?= h($basis($pagina-1)) ?>" class="btn btn-outline btn-sm">← Vorige</a><?php endif; ?>
      <span style="color:var(--tekst-zacht)">Pagina <?= $pagina ?> van <?= $max_pagina ?></span>
      <?php if ($pagina < $max_pagina): ?><a href="<?= h($basis($pagina+1)) ?>" class="btn btn-outline btn-sm">Volgende →</a><?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>

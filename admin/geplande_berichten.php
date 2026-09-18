<?php
/**
 * Beheer — Geplande berichten: "X dagen na/vóór gebeurtenis Y stuur sjabloon Z".
 * Regels staan in geplande_berichten; ze draaien mee met de herinneringen (cron) en zijn hier
 * proef te draaien (niets versturen).
 */
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Geplande berichten';
$actieve_nav  = 'geplande_berichten';
vereis_recht('mailing', 'bewerken');
$db = db();

$gebeurtenissen = function_exists('gepland_gebeurtenissen') ? gepland_gebeurtenissen() : [];
$fases = function_exists('fasen') ? fasen() : [];
$sjablonen = [];
try { $sjablonen = $db->query("SELECT slug, naam FROM mail_templates WHERE slug LIKE 'eigen\\_%' OR categorie='handmatig' ORDER BY naam")->fetchAll(); } catch (Exception $e) {}
$sjablonen = array_values(array_filter($sjablonen, fn($t) => $t['slug'] !== 'handmatig'));

$proef = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = $_POST['actie'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($actie === 'opslaan') {
        if (empty($_POST['sjabloon_slug'])) { flash('fout', 'Kies een sjabloon (maak er eerst een aan onder Sjablonen).'); redirect(APP_URL . '/admin/geplande_berichten.php' . ($id ? '?id=' . $id : '')); }
        $nieuw_id = gepland_opslaan($_POST, $id ?: null, (int)(huidige_beheerder()['id'] ?? 0));
        if ($nieuw_id) { if (function_exists('audit_log')) audit_log($id ? 'gewijzigd' : 'aangemaakt', 'gepland_bericht', $nieuw_id, ['naam' => $_POST['naam'] ?? '']); flash('succes', 'Regel opgeslagen.'); }
        else flash('fout', 'Opslaan mislukt.');
        redirect(APP_URL . '/admin/geplande_berichten.php');
    }
    if ($actie === 'verwijder' && $id) {
        gepland_verwijderen($id);
        if (function_exists('audit_log')) audit_log('verwijderd', 'gepland_bericht', $id, null);
        flash('succes', 'Regel verwijderd.');
        redirect(APP_URL . '/admin/geplande_berichten.php');
    }
    if ($actie === 'proef' && $id) {
        $proef = function_exists('gepland_run') ? gepland_run($id, true) : [];
    }
}

$regels = function_exists('gepland_regels') ? gepland_regels(false) : [];
$bewerk = !empty($_GET['id']) ? gepland_regel((int)$_GET['id']) : null;
$nieuw  = isset($_GET['nieuw']);
$form   = $bewerk ?: ['id' => 0, 'naam' => '', 'aan' => 1, 'gebeurtenis' => 'aangemeld', 'offset_dagen' => 10, 'sjabloon_slug' => '', 'kanaalmodus' => 'email', 'fases' => '', 'max_per_sollicitatie' => 1, 'venster_dagen' => 7];
$form_fases = array_filter(array_map('trim', explode(',', (string)($form['fases'] ?? ''))));
$modi = ['email' => 'E-mail', 'kort' => 'WhatsApp/SMS', 'beide' => 'E-mail + WhatsApp/SMS'];
$sjabloon_naam = []; foreach ($sjablonen as $t) $sjabloon_naam[$t['slug']] = $t['naam'];

require __DIR__ . '/layout_top.php';
?>
<div class="card" style="margin-bottom:14px">
  <div class="card-header"><div class="card-title">Regels</div><a href="?nieuw=1" class="btn btn-primary btn-sm">Nieuwe regel</a></div>
  <div class="card-body" style="padding:0">
    <?php if (!$regels): ?><p style="padding:16px;color:var(--grijs);font-size:13px">Nog geen geplande berichten. Voorbeeld: "10 dagen na het inleveren van het formulier: stuur het magazine".</p>
    <?php else: ?>
    <?php foreach ($regels as $r): ?>
    <div style="display:flex;gap:12px;align-items:center;padding:10px 16px;border-bottom:1px solid var(--rand);font-size:13px;flex-wrap:wrap">
      <span class="badge <?= (int)$r['aan'] ? 'badge-groen' : '' ?>" style="flex:none"><?= (int)$r['aan'] ? 'aan' : 'uit' ?></span>
      <div style="flex:1;min-width:220px">
        <div style="font-weight:600"><?= h($r['naam']) ?></div>
        <div style="color:var(--tekst-zacht)">
          <?= (int)$r['offset_dagen'] === 0 ? 'Op de dag van' : abs((int)$r['offset_dagen']) . ' dagen ' . ((int)$r['offset_dagen'] < 0 ? 'vóór' : 'na') ?>
          <?= h(lcfirst($gebeurtenissen[$r['gebeurtenis']] ?? $r['gebeurtenis'])) ?> · sjabloon "<?= h($sjabloon_naam[$r['sjabloon_slug']] ?? $r['sjabloon_slug']) ?>" · <?= h($modi[$r['kanaalmodus']] ?? $r['kanaalmodus']) ?>
          <?= $r['fases'] ? ' · alleen fase ' . h(implode(', ', array_map(fn($f) => $fases[$f] ?? $f, explode(',', $r['fases'])))) : '' ?>
          · max <?= (int)$r['max_per_sollicitatie'] ?>x per persoon
        </div>
      </div>
      <form method="post" style="margin:0"><?= csrf_input() ?><input type="hidden" name="actie" value="proef"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-outline btn-sm">Proefdraaien</button></form>
      <a href="?id=<?= (int)$r['id'] ?>" class="btn btn-outline btn-sm">Bewerken</a>
      <form method="post" style="margin:0" onsubmit="return confirm('Deze regel verwijderen?')"><?= csrf_input() ?><input type="hidden" name="actie" value="verwijder"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-rood btn-sm">Verwijderen</button></form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($proef !== null): ?>
<div class="card" style="margin-bottom:14px;border-left:4px solid var(--navy)">
  <div class="card-header"><div class="card-title">Proefrun (niets verstuurd)</div></div>
  <div class="card-body" style="font-size:13px">
    <?php foreach ($proef as $pr): ?>
    <div><strong><?= h($pr['naam']) ?></strong>: <?= (int)$pr['kandidaten'] ?> kandidaat/-aten, <?= (int)$pr['verstuurd'] ?> zou versturen, <?= (int)$pr['overgeslagen'] ?> overgeslagen<?= !empty($pr['fout']) ? ' — ' . h($pr['fout']) : '' ?></div>
    <div style="color:var(--tekst-zacht);margin-top:4px"><?php foreach ($pr['lijst'] as $hl): ?><span style="display:inline-block;margin:2px 8px 2px 0"><?= h($hl['naam']) ?>: <em><?= h($hl['actie']) ?></em></span><?php endforeach; ?></div>
    <?php endforeach; ?>
    <?php if (!$proef): ?><div style="color:var(--grijs)">Geen actief seizoen of regel niet gevonden.</div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($bewerk || $nieuw): ?>
<div class="card">
  <div class="card-header"><div class="card-title"><?= $bewerk ? 'Regel bewerken' : 'Nieuwe regel' ?></div></div>
  <div class="card-body">
    <?php if (!$sjablonen): ?>
    <div class="flash flash-waarschuwing">Maak eerst een eigen sjabloon aan onder <a href="<?= APP_URL ?>/admin/mail_templates.php">Sjablonen</a> (inhoud van het bericht, eventueel met bijlage).</div>
    <?php endif; ?>
    <form method="post">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="opslaan">
      <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
      <div class="form-rij">
        <div class="form-groep" style="flex:2"><label>Naam (intern)</label><input type="text" name="naam" value="<?= h($form['naam']) ?>" required placeholder="Bijv. Magazine na formulier"></div>
        <div class="form-groep"><label><input type="checkbox" name="aan" value="1" <?= (int)$form['aan'] ? 'checked' : '' ?>> Actief</label></div>
      </div>
      <div class="form-rij">
        <div class="form-groep" style="flex:2"><label>Gebeurtenis</label>
          <select name="gebeurtenis"><?php foreach ($gebeurtenissen as $gk => $gl): ?><option value="<?= h($gk) ?>" <?= $form['gebeurtenis'] === $gk ? 'selected' : '' ?>><?= h($gl) ?></option><?php endforeach; ?></select></div>
        <div class="form-groep"><label>Dagen erna (negatief = ervoor)</label><input type="number" name="offset_dagen" value="<?= (int)$form['offset_dagen'] ?>" min="-60" max="365">
          <div class="form-hulp">Bijv. 10 = tien dagen na de gebeurtenis; -3 = drie dagen vóór de selectiedag.</div></div>
      </div>
      <div class="form-rij">
        <div class="form-groep" style="flex:2"><label>Sjabloon (inhoud)</label>
          <select name="sjabloon_slug" required><option value="">— kies —</option><?php foreach ($sjablonen as $t): ?><option value="<?= h($t['slug']) ?>" <?= $form['sjabloon_slug'] === $t['slug'] ? 'selected' : '' ?>><?= h($t['naam']) ?></option><?php endforeach; ?></select></div>
        <div class="form-groep"><label>Kanaal</label>
          <select name="kanaalmodus"><?php foreach ($modi as $mk => $ml): ?><option value="<?= $mk ?>" <?= $form['kanaalmodus'] === $mk ? 'selected' : '' ?>><?= h($ml) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="form-groep"><label>Alleen voor deze fases <span style="font-weight:400;color:var(--grijs)">(leeg = alle fases)</span></label>
        <div style="display:flex;flex-wrap:wrap;gap:8px 14px;font-size:13px">
          <?php foreach ($fases as $fk => $fl): ?><label style="display:inline-flex;gap:5px;align-items:center;font-weight:400"><input type="checkbox" name="fases[]" value="<?= h($fk) ?>" <?= in_array($fk, $form_fases, true) ? 'checked' : '' ?>> <?= h($fl) ?></label><?php endforeach; ?>
        </div></div>
      <div class="form-rij">
        <div class="form-groep"><label>Max per persoon</label><input type="number" name="max_per_sollicitatie" value="<?= (int)$form['max_per_sollicitatie'] ?>" min="1" max="10"></div>
        <div class="form-groep"><label>Venster (dagen)</label><input type="number" name="venster_dagen" value="<?= (int)$form['venster_dagen'] ?>" min="1" max="60">
          <div class="form-hulp">Alleen gevallen waarvan het moment hoogstens zoveel dagen geleden was (voorkomt na-zenden aan oude gevallen).</div></div>
      </div>
      <div style="display:flex;gap:8px"><button type="submit" class="btn btn-primary">Opslaan</button><a href="<?= APP_URL ?>/admin/geplande_berichten.php" class="btn btn-outline">Annuleren</a></div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

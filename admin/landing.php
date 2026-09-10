<?php
/**
 * Beheer van landingspagina's: aanmaken, bewerken (HTML/CSS), status, en statistiek.
 */
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Landingspagina\'s';
$actieve_nav  = 'landing';
vereis_recht('sollicitanten', 'bewerken');

$db = db();
if (function_exists('landing_auto_setup')) landing_auto_setup();

$actie = $_GET['actie'] ?? 'lijst';
$id = (int)($_GET['id'] ?? 0);

// ── Opslaan ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') !== 'verwijder') {
    csrf_check();
    $slug = strtolower(trim($_POST['slug'] ?? ''));
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = trim(preg_replace('/-+/', '-', $slug), '-');
    $titel = trim($_POST['titel'] ?? '');
    $doelgroep = trim($_POST['doelgroep'] ?? '');
    $html = $_POST['html'] ?? '';
    $css = $_POST['css'] ?? '';
    $status = in_array($_POST['status'] ?? '', ['concept','alleen_admin','gepubliceerd'], true) ? $_POST['status'] : 'concept';
    $bron_code = strtolower(trim($_POST['bron_code'] ?? '')) ?: $slug;
    $bewerk_id = (int)($_POST['id'] ?? 0);

    if ($slug === '' || $titel === '') {
        flash('fout', 'Slug en titel zijn verplicht.');
    } else {
        try {
            if ($bewerk_id) {
                $db->prepare("UPDATE landingspaginas SET slug=?, titel=?, doelgroep=?, html=?, css=?, status=?, bron_code=?, gewijzigd_op=NOW() WHERE id=?")
                   ->execute([$slug, $titel, $doelgroep, $html, $css, $status, $bron_code, $bewerk_id]);
                if (function_exists('audit_log')) audit_log('gewijzigd', 'landingspagina', $bewerk_id, ['slug'=>$slug, 'status'=>$status]);
                flash('succes', 'Landingspagina bijgewerkt.');
            } else {
                $db->prepare("INSERT INTO landingspaginas (slug, titel, doelgroep, html, css, status, bron_code) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$slug, $titel, $doelgroep, $html, $css, $status, $bron_code]);
                $bewerk_id = (int)$db->lastInsertId();
                if (function_exists('audit_log')) audit_log('aangemaakt', 'landingspagina', $bewerk_id, ['slug'=>$slug]);
                flash('succes', 'Landingspagina aangemaakt.');
            }
            redirect(APP_URL . '/admin/landing.php?actie=bewerk&id=' . $bewerk_id);
        } catch (\Exception $e) {
            flash('fout', 'Opslaan mislukt: mogelijk bestaat de slug al.');
        }
    }
}

// ── Verwijderen (via POST, veilig) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'verwijder') {
    csrf_check();
    $del_id = (int)($_POST['id'] ?? 0);
    if ($del_id) {
        try {
            $db->prepare("DELETE FROM landingspaginas WHERE id=?")->execute([$del_id]);
            if (function_exists('audit_log')) audit_log('verwijderd', 'landingspagina', $del_id);
            flash('succes', 'Landingspagina verwijderd.');
        } catch (\Exception $e) {}
    }
    redirect(APP_URL . '/admin/landing.php');
}

require __DIR__ . '/layout_top.php';

$status_label = ['concept' => 'Concept', 'alleen_admin' => 'Alleen admins', 'gepubliceerd' => 'Gepubliceerd'];
$status_kleur = ['concept' => 'var(--grijs)', 'alleen_admin' => 'var(--oranje)', 'gepubliceerd' => 'var(--groen)'];

// ══ BEWERKEN / NIEUW ══
if ($actie === 'bewerk' || $actie === 'nieuw'):
    $lp = $id ? landing_via_slug('') : null;
    if ($id) {
        $q = $db->prepare("SELECT * FROM landingspaginas WHERE id=?");
        $q->execute([$id]);
        $lp = $q->fetch();
    }
    $stats = ($lp && $id) ? landing_stats($id) : null;
?>
  <div style="max-width:1100px;margin:0 auto">
    <a href="<?= APP_URL ?>/admin/landing.php" style="font-size:13px;color:var(--tekst-zacht);text-decoration:none">← Alle landingspagina's</a>
    <h1 style="font-size:22px;margin:6px 0 16px"><?= $lp ? h($lp['titel']) : 'Nieuwe landingspagina' ?></h1>

    <?php if ($stats): ?>
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px">
      <?php
        $kpis = [
          ['Unieke bezoekers', $stats['uniek']],
          ['Bezoeken', $stats['bezoeken']],
          ['Formulier gestart', $stats['starts'] . ' (' . $stats['start_pct'] . '%)'],
          ['Afgerond', $stats['klaar'] . ' (' . $stats['klaar_pct'] . '%)'],
        ];
        foreach ($kpis as $k):
      ?>
      <div style="flex:1;min-width:140px;background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:10px;padding:12px 16px">
        <div style="font-size:22px;font-weight:700;color:var(--navy)"><?= h($k[1]) ?></div>
        <div style="font-size:12px;color:var(--tekst-zacht)"><?= h($k[0]) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" id="lpForm">
      <?= csrf_input() ?>
      <input type="hidden" name="id" value="<?= (int)($lp['id'] ?? 0) ?>">

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:12px">
        <div>
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Titel *</label>
          <input type="text" name="titel" value="<?= h($lp['titel'] ?? '') ?>" required style="width:100%">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Doelgroep</label>
          <input type="text" name="doelgroep" value="<?= h($lp['doelgroep'] ?? '') ?>" placeholder="bijv. medische studenten" style="width:100%">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:12px">
        <div>
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">URL-naam (slug) *</label>
          <input type="text" name="slug" value="<?= h($lp['slug'] ?? '') ?>" required placeholder="medisch" style="width:100%">
          <div style="font-size:11px;color:var(--tekst-zacht);margin-top:2px"><?= APP_URL ?>/l/<span id="slugPreview"><?= h($lp['slug'] ?? '...') ?></span></div>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Bron-code</label>
          <input type="text" name="bron_code" value="<?= h($lp['bron_code'] ?? '') ?>" placeholder="= slug" style="width:100%">
          <div style="font-size:11px;color:var(--tekst-zacht);margin-top:2px">Voor herkomst-attributie</div>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Status</label>
          <select name="status" style="width:100%">
            <?php foreach ($status_label as $sk => $sl): ?>
            <option value="<?= $sk ?>" <?= ($lp['status'] ?? 'concept')===$sk?'selected':'' ?>><?= $sl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="margin-bottom:12px">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">HTML</label>
        <div style="font-size:11px;color:var(--tekst-zacht);margin-bottom:4px">Gebruik <code>[[formulier]]</code> op de plek waar het aanmeldformulier moet komen.</div>
        <textarea name="html" id="lpHtml" rows="12" style="width:100%;font-family:monospace;font-size:12px" spellcheck="false"><?= h($lp['html'] ?? "<section class=\"hero\">\n  <h1>Word lifeguard bij Paal 13</h1>\n  <p>Voor medische studenten die iets willen met hun kennis.</p>\n</section>\n\n[[formulier]]") ?></textarea>
      </div>

      <div style="margin-bottom:12px">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">CSS</label>
        <textarea name="css" id="lpCss" rows="8" style="width:100%;font-family:monospace;font-size:12px" spellcheck="false"><?= h($lp['css'] ?? ".hero{background:#054d8a;color:#fff;padding:40px;text-align:center;border-radius:12px}\n.hero h1{font-size:28px;margin:0 0 8px}") ?></textarea>
      </div>

      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <button type="submit" class="btn btn-primary">Opslaan</button>
        <button type="button" class="btn btn-outline" onclick="lpPreview()">Voorbeeld tonen</button>
        <?php if ($lp): ?>
          <a href="<?= APP_URL ?>/l/<?= h($lp['slug']) ?>" target="_blank" class="btn btn-outline">Openen in nieuw tabblad ↗</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Live preview -->
    <div id="lpPreviewWrap" style="display:none;margin-top:20px">
      <div style="font-size:13px;font-weight:600;margin-bottom:6px">Voorbeeld (zonder formulier):</div>
      <iframe id="lpPreviewFrame" style="width:100%;height:500px;border:1px solid var(--rand);border-radius:10px;background:#fff"></iframe>
    </div>
  </div>

  <script>
    // Slug live tonen
    document.querySelector('[name=slug]').addEventListener('input', function(){
      document.getElementById('slugPreview').textContent = this.value.toLowerCase().replace(/[^a-z0-9\-]/g,'-') || '...';
    });
    // Live preview van HTML+CSS (formulier vervangen door een placeholder)
    function lpPreview() {
      var html = document.getElementById('lpHtml').value.replace(/\[\[formulier\]\]/g,
        '<div style="padding:20px;border:2px dashed #999;text-align:center;color:#666;border-radius:10px">[ hier komt het aanmeldformulier ]</div>');
      var css = document.getElementById('lpCss').value;
      var doc = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'+css+'</style></head><body>'+html+'</body></html>';
      document.getElementById('lpPreviewWrap').style.display = 'block';
      var f = document.getElementById('lpPreviewFrame');
      f.srcdoc = doc;
      document.getElementById('lpPreviewWrap').scrollIntoView({behavior:'smooth'});
    }
  </script>

<?php else: // ══ LIJST ══ ?>
  <div style="max-width:1000px;margin:0 auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
      <h1 style="font-size:22px;margin:0">Landingspagina's</h1>
      <a href="<?= APP_URL ?>/admin/landing.php?actie=nieuw" class="btn btn-primary btn-sm">+ Nieuwe pagina</a>
    </div>

    <?php $paginas = landing_alle(); ?>

    <?php
      // Wervingsoverzicht: per bron hoeveel aanmeldingen en aangenomen
      $werving = function_exists('landing_werving_overzicht') ? landing_werving_overzicht() : [];
      if ($werving):
    ?>
    <div style="background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:10px;padding:16px;margin-bottom:18px">
      <div style="font-weight:600;font-size:15px;margin-bottom:4px">Wervingsoverzicht — waar komen aanmeldingen vandaan?</div>
      <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 12px">Per bron het aantal aanmeldingen en hoeveel daarvan zijn aangenomen.</p>
      <div class="tabel-scroll">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="border-bottom:1px solid var(--rand)">
              <th style="text-align:left;padding:6px 8px">Bron</th>
              <th style="text-align:right;padding:6px 8px">Aanmeldingen</th>
              <th style="text-align:right;padding:6px 8px">Aangenomen</th>
              <th style="text-align:right;padding:6px 8px">Conversie</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($werving as $w):
              $conv = $w['aanmeldingen'] > 0 ? round($w['aangenomen'] / $w['aanmeldingen'] * 100) : 0;
            ?>
            <tr style="border-bottom:1px solid var(--rand)">
              <td style="padding:7px 8px"><?= h($w['bron']) ?></td>
              <td style="text-align:right;padding:7px 8px"><?= (int)$w['aanmeldingen'] ?></td>
              <td style="text-align:right;padding:7px 8px"><?= (int)$w['aangenomen'] ?></td>
              <td style="text-align:right;padding:7px 8px;color:var(--tekst-zacht)"><?= $conv ?>%</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
    <?php if (!$paginas): ?>
      <div style="padding:30px;text-align:center;color:var(--tekst-zacht);background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:10px">
        Nog geen landingspagina's. Maak er een aan voor je eerste doelgroep.
      </div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach ($paginas as $p): $st = landing_stats((int)$p['id']); ?>
        <div style="background:var(--bg-card,#fff);border:1px solid var(--rand);border-radius:10px;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
          <div style="flex:1;min-width:200px">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <a href="<?= APP_URL ?>/admin/landing.php?actie=bewerk&id=<?= (int)$p['id'] ?>" style="font-weight:600;font-size:15px;color:var(--navy);text-decoration:none"><?= h($p['titel']) ?></a>
              <span style="font-size:11px;padding:2px 8px;border-radius:9px;background:<?= $status_kleur[$p['status']] ?>22;color:<?= $status_kleur[$p['status']] ?>"><?= $status_label[$p['status']] ?></span>
            </div>
            <div style="font-size:12px;color:var(--tekst-zacht);margin-top:2px">
              /l/<?= h($p['slug']) ?><?= $p['doelgroep'] ? ' · ' . h($p['doelgroep']) : '' ?>
            </div>
          </div>
          <div style="display:flex;gap:16px;text-align:center;font-size:12px">
            <div><div style="font-weight:700;font-size:16px"><?= $st['uniek'] ?></div><div style="color:var(--tekst-zacht)">bezoekers</div></div>
            <div><div style="font-weight:700;font-size:16px"><?= $st['starts'] ?></div><div style="color:var(--tekst-zacht)">gestart</div></div>
            <div><div style="font-weight:700;font-size:16px"><?= $st['klaar'] ?></div><div style="color:var(--tekst-zacht)">afgerond</div></div>
          </div>
          <div style="display:flex;gap:6px">
            <a href="<?= APP_URL ?>/admin/landing.php?actie=bewerk&id=<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm">Bewerken</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

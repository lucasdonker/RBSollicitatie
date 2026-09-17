<?php
// ── Preview: vóór alles, geen layout nodig ────────────────────
if (isset($_GET['preview'])) {
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/mail_render.php';
    vereist_login();
    $slug = $_GET['preview'];
    try {
        $stmt = db()->prepare("SELECT * FROM mail_templates WHERE slug=?");
        $stmt->execute([$slug]); $tmpl = $stmt->fetch();
    } catch (Exception $e) { die('DB fout: ' . htmlspecialchars($e->getMessage())); }
    if ($tmpl) {
        $dummy_sol = ['voornaam'=>'Lucas','tussenvoegsel'=>'de','achternaam'=>'Vries','email'=>'lucas@example.com','telefoon'=>'06-12345678'];
        $dummy_sz  = ['naam'=>'Zomer 2026','datum_selectiedag'=>date('Y-m-d',strtotime('+14 days')),'locatie_selectiedag'=>'Paal 13, Callantsoog','selectiedag_starttijd'=>'10:00','selectiedag_verzameltijd'=>'09:30','datum_tw_start'=>date('Y-m-d',strtotime('+60 days')),'datum_tw_eind'=>date('Y-m-d',strtotime('+62 days')),'locatie_tw'=>'Strandpost Callantsoog','tw_verzameltijd'=>'14:00'];
        $vars       = array_merge(mail_variabelen($dummy_sol, $dummy_sz), ['otp.code'=>'483921']);
        $body       = mail_shortcodes(mail_vervang($tmpl['body_html'], $vars));
        $onderwerp  = mail_vervang($tmpl['onderwerp'], $vars);
        $hero_titel = !empty($tmpl['hero_titel']) ? mail_vervang($tmpl['hero_titel'], $vars) : $onderwerp;
        $hero_subtitel = !empty($tmpl['hero_subtitel']) ? mail_vervang($tmpl['hero_subtitel'], $vars) : '';
        $hero_image    = $tmpl['hero_image_url'] ?? null;
        $hero_image_url = $hero_image ? (function_exists('foto_url') ? foto_url($hero_image) : $hero_image) : null;
        $hero_kleur    = $tmpl['hero_kleur'] ?: '#1A2540';
        header('Content-Type: text/html; charset=utf-8');
        echo mail_html_wrapper($body, $onderwerp, $hero_titel, $hero_subtitel, $hero_image_url, $hero_kleur);
    } else {
        die('Template niet gevonden');
    }
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail_render.php';
$pagina_titel = 'Mail templates';
$actieve_nav  = 'mail_templates';
vereis_recht('mailing', 'bewerken');
$db = db();

// Auto-setup: tabel + kolommen aanmaken als nodig
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `mail_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `slug` VARCHAR(50) NOT NULL UNIQUE,
        `naam` VARCHAR(100) NOT NULL,
        `beschrijving` VARCHAR(300) DEFAULT NULL,
        `onderwerp` VARCHAR(300) NOT NULL,
        `hero_titel` VARCHAR(300) DEFAULT NULL,
        `body_html` TEXT NOT NULL,
        `gewijzigd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Voeg hero_titel toe als die ontbreekt
try { $db->exec("ALTER TABLE mail_templates ADD COLUMN IF NOT EXISTS hero_titel VARCHAR(300) DEFAULT NULL AFTER onderwerp"); } catch (Exception $e) {}
try { $db->exec("UPDATE mail_templates SET hero_titel = onderwerp WHERE hero_titel IS NULL AND slug != '_hero_image'"); } catch (Exception $e) {}

// Hero image config aanmaken als die ontbreekt
try { $db->exec("INSERT IGNORE INTO mail_templates (slug,naam,beschrijving,onderwerp,body_html) VALUES('_hero_image','Hero afbeelding','URL van de hero foto','Hero afbeelding','')"); } catch (Exception $e) {}

// Auto-fill standaard templates als tabel leeg
$count = 0;
try { $count = (int)$db->query("SELECT COUNT(*) FROM mail_templates WHERE slug != '_hero_image'")->fetchColumn(); } catch (Exception $e) {}
if ($count === 0) {
    $sql = file_get_contents(__DIR__ . '/../sql/migratie_0005_mail_templates.sql');
    preg_match_all('/INSERT IGNORE INTO `mail_templates`[^;]+;/s', $sql, $m);
    foreach ($m[0] as $s) { try { $db->exec($s); } catch (Exception $e) {} }
    try { $db->exec("UPDATE mail_templates SET hero_titel = onderwerp WHERE hero_titel IS NULL AND slug != '_hero_image'"); } catch (Exception $e) {}
}

// ── Opslaan ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opslaan'])) {
    csrf_check();
    $slug       = trim($_POST['slug'] ?? '');
    $onderwerp  = trim($_POST['onderwerp'] ?? '');
    $hero_titel    = trim($_POST['hero_titel'] ?? '') ?: $onderwerp;
    $hero_subtitel = trim($_POST['hero_subtitel'] ?? '');
    $hero_kleur    = trim($_POST['hero_kleur'] ?? '#1A2540');
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $hero_kleur)) $hero_kleur = '#1A2540';
    $hero_image_url = trim($_POST['hero_image_url'] ?? '');
    $body       = $_POST['body_html'] ?? '';

    // Image upload heeft voorrang op URL
    if (!empty($_FILES['hero_image_upload']['tmp_name']) && $_FILES['hero_image_upload']['error'] === 0) {
        $bron = $_FILES['hero_image_upload'];
        if ($bron['size'] < 5*1024*1024) {
            $ext = strtolower(pathinfo($bron['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
                $dir = __DIR__ . '/../uploads/mail_hero/';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $fn = 'hero_' . $slug . '_' . time() . '.' . $ext;
                if (move_uploaded_file($bron['tmp_name'], $dir . $fn)) {
                    $hero_image_url = '/uploads/mail_hero/' . $fn;
                }
            }
        }
    }

    if ($slug && $onderwerp) {
        // Auto-setup zorgt dat hero kolommen bestaan
        if (function_exists('mail_templates_auto_setup')) mail_templates_auto_setup();
        try {
            $db->prepare("UPDATE mail_templates SET onderwerp=?, hero_titel=?, hero_subtitel=?, hero_image_url=?, hero_kleur=?, body_html=?, gewijzigd_op=NOW() WHERE slug=?")
               ->execute([$onderwerp, $hero_titel, $hero_subtitel ?: null, $hero_image_url ?: null, $hero_kleur, $body, $slug]);
        } catch (Exception $e) {
            // Fallback: alleen basis-velden
            $db->prepare("UPDATE mail_templates SET onderwerp=?, hero_titel=?, body_html=?, gewijzigd_op=NOW() WHERE slug=?")
               ->execute([$onderwerp, $hero_titel, $body, $slug]);
        }
        flash('succes', 'Template opgeslagen.');
        redirect(APP_URL . '/admin/mail_templates.php?slug=' . urlencode($slug));
    }
}

// ── Reset naar standaard ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_slug'])) {
    csrf_check();
    $slug = trim($_POST['reset_slug']);
    // Slug valideren: alleen de tekens die in een template-slug voorkomen (letters, cijfers, _ en -).
    // Voorkomt SQL-injectie en beperkt de invoer tot een veilig formaat.
    if ($slug === '' || !preg_match('/^[a-z0-9_-]{1,64}$/i', $slug)) {
        flash('fout', 'Ongeldige template.');
        redirect(APP_URL . '/admin/mail_templates.php');
    }
    $sql  = file_get_contents(__DIR__ . '/../sql/migratie_0005_mail_templates.sql');
    preg_match_all('/INSERT IGNORE INTO `mail_templates`[^;]+;/s', $sql, $m);
    foreach ($m[0] as $insert) {
        // De juiste INSERT-regel voor deze slug herkennen (regel komt uit een vast bestand op de server, niet uit invoer)
        if (strpos($insert, "'" . $slug . "'") !== false) {
            try { $db->exec(str_replace('INSERT IGNORE INTO', 'REPLACE INTO', $insert)); } catch (Exception $e) { error_log('template reset: ' . $e->getMessage()); }
        }
    }
    try {
        $db->prepare("UPDATE mail_templates SET hero_titel = onderwerp WHERE slug = ? AND hero_titel IS NULL")->execute([$slug]);
    } catch (Exception $e) { error_log('template reset hero_titel: ' . $e->getMessage()); }
    flash('succes', 'Template teruggezet naar standaard.');
    redirect(APP_URL . '/admin/mail_templates.php?slug=' . urlencode($slug));
}

if (function_exists('ondertekening_templates_seed')) ondertekening_templates_seed();
if (function_exists('welkom_template_seed')) welkom_template_seed();
$templates   = $db->query("SELECT * FROM mail_templates ORDER BY id")->fetchAll();
$actief_slug = $_GET['slug'] ?? '';
if (!$actief_slug) foreach ($templates as $t) { if ($t['slug'] !== '_hero_image') { $actief_slug = $t['slug']; break; } }
$actief = null;
foreach ($templates as $t) { if ($t['slug'] === $actief_slug) { $actief = $t; break; } }

// Hero image rij
$hero_row = null;
foreach ($templates as $t) { if ($t['slug'] === '_hero_image') { $hero_row = $t; break; } }

$vars_ref = [
    'Sollicitant'    => ['sollicitant.voornaam','sollicitant.achternaam','sollicitant.volledige_naam','sollicitant.email'],
    'Seizoen'        => ['seizoen.naam'],
    'Selectiedag'    => ['selectiedag.datum','selectiedag.locatie','selectiedag.adres','selectiedag.starttijd','selectiedag.verzameltijd','selectiedag.eindtijd','selectiedag.meenemen'],
    'Trainingsweekend' => ['tw.datum_start','tw.datum_eind','tw.locatie','tw.adres','tw.verzameltijd','tw.eindtijd','tw.meenemen'],
    'Overig'         => ['portaal.url','bestuur.email','otp.code'],
];
$shortcodes = [
    '[knop href="URL"]Tekst[/knop]' => '🔶 Oranje knop',
    '[info]Tekst[/info]'            => '🔵 Info blok',
    '[let op]Tekst[/let op]'        => '🟡 Waarschuwing',
    '[code]123456[/code]'           => '🔢 OTP code weergave',
];

require __DIR__ . '/layout_top.php';
?>
<style>
.tmpl-grid{display:grid;grid-template-columns:210px 1fr 310px;gap:14px;align-items:start}
.tmpl-lijst{background:#fff;border-radius:var(--radius);border:1px solid var(--rand);overflow:hidden}
.tmpl-item{display:block;padding:11px 14px;border-bottom:1px solid var(--rand);text-decoration:none;color:var(--tekst);transition:background .1s}
.tmpl-item:last-child{border:none}
.tmpl-item:hover{background:var(--grijs-bg)}
.tmpl-item.actief{background:var(--navy);color:#fff}
.tmpl-item.actief .ts{color:rgba(255,255,255,.5)}
.tn{font-weight:600;font-size:13px}.ts{font-size:11px;color:var(--grijs);margin-top:1px}
.editor-tabs{display:flex;border-bottom:2px solid var(--rand)}
.etab{padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;border:none;background:none;color:var(--grijs);border-bottom:2px solid transparent;margin-bottom:-2px}
.etab.actief{color:var(--navy);border-bottom-color:var(--oranje)}
.wysiwyg-toolbar{display:flex;gap:3px;flex-wrap:wrap;padding:8px;background:#f7f8fa;border:1px solid var(--rand);border-bottom:none;border-radius:7px 7px 0 0}
.wb{padding:5px 9px;border:1px solid var(--rand);background:#fff;border-radius:4px;cursor:pointer;font-size:13px;color:var(--tekst)}
.wb:hover{background:var(--oranje-bg);border-color:var(--oranje)}
.wysiwyg-editor{min-height:300px;padding:14px;border:1px solid var(--rand);border-radius:0 0 7px 7px;outline:none;font-family:Arial,sans-serif;font-size:14px;line-height:1.7;overflow-y:auto}
.wysiwyg-editor:focus{border-color:var(--oranje)}
#htmlEditor{font-family:'Courier New',monospace;font-size:12px;line-height:1.6;min-height:300px;resize:vertical}
.var-chip{display:inline-block;background:var(--blauw-bg);color:#1a56a0;font-size:11px;font-family:monospace;padding:2px 7px;border-radius:4px;margin:2px;cursor:pointer;border:1px solid #bee3f8}
.var-chip:hover{background:#bee3f8}
.sc-chip{background:#f7f8fa;border:1px solid var(--rand);font-size:11px;font-family:monospace;padding:3px 8px;border-radius:4px;cursor:pointer}
.sc-chip:hover{background:var(--oranje-bg);border-color:var(--oranje)}
.veld-label{font-size:11px;font-weight:600;color:var(--grijs);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;display:block}
</style>

<div class="tmpl-grid">

<!-- 1: Lijst -->
<div>
  <div class="tmpl-lijst">
    <?php
    // Sorteer templates op categorie voor overzicht
    $cat_van = function(string $slug): string {
        if (str_starts_with($slug, 'uitnodiging'))   return 'Uitnodigingen';
        if (str_starts_with($slug, 'reminder'))      return 'Herinneringen';
        if (str_starts_with($slug, 'ondertekening')) return 'Ondertekeningen';
        if (str_starts_with($slug, 'welkom'))        return 'Welkom & aanname';
        if (str_starts_with($slug, 'afwijzing') || str_starts_with($slug, 'bedank')) return 'Afronding';
        if (str_starts_with($slug, 'otp') || str_starts_with($slug, 'login'))        return 'Inloggen';
        return 'Overig';
    };
    $cat_volgorde = ['Uitnodigingen','Herinneringen','Ondertekeningen','Welkom & aanname','Afronding','Inloggen','Overig'];
    $per_cat = [];
    foreach ($templates as $t) {
        if ($t['slug'] === '_hero_image') continue;
        $per_cat[$cat_van($t['slug'])][] = $t;
    }
    ?>
    <?php foreach ($cat_volgorde as $cat): if (empty($per_cat[$cat])) continue; ?>
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--grijs);padding:10px 12px 4px"><?= h($cat) ?></div>
      <?php foreach ($per_cat[$cat] as $t): ?>
      <a href="?slug=<?= h($t['slug']) ?>" class="tmpl-item <?= $t['slug']===$actief_slug?'actief':'' ?>">
        <div class="tn"><?= h($t['naam']) ?></div>
        <div class="ts"><?= h($t['slug']) ?></div>
      </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <!-- Hero image instelling — aparte form, NIET genest -->
  <div style="margin-top:10px;background:#fff;border-radius:var(--radius);border:1px solid var(--rand);padding:12px">
    <div style="font-size:11px;font-weight:600;color:var(--grijs);text-transform:uppercase;margin-bottom:6px">🖼 Hero afbeelding URL</div>
    <form method="POST" action="">
      <?= csrf_input() ?>
      <input type="hidden" name="opslaan" value="1">
      <input type="hidden" name="slug" value="_hero_image">
      <input type="hidden" name="onderwerp" value="Hero afbeelding">
      <input type="hidden" name="hero_titel" value="">
      <input type="text" name="body_html" value="<?= h($hero_row['body_html'] ?? '') ?>"
             placeholder="https://jouwsite.nl/foto.jpg"
             style="width:100%;font-size:11px;padding:6px 8px;margin-bottom:5px">
      <div style="font-size:10px;color:var(--grijs);margin-bottom:6px">Laat leeg voor de standaard strandfoto</div>
      <button type="submit" class="btn btn-primary btn-sm" style="width:100%;justify-content:center">Opslaan</button>
    </form>
  </div>

  <div style="margin-top:8px">
    <a href="?preview=<?= h($actief_slug) ?>" target="_blank"
       class="btn btn-outline btn-sm" style="width:100%;justify-content:center">
      👁 Preview mail
    </a>
  </div>
</div>

<!-- 2: Editor -->
<?php if ($actief && $actief['slug'] !== '_hero_image'): ?>
<div>
<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title"><?= h($actief['naam']) ?></div>
      <?php if ($actief['beschrijving']): ?>
      <div style="font-size:12px;color:var(--tekst-zacht);margin-top:2px"><?= h($actief['beschrijving']) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <!-- Hoofd form — geen geneste forms hierin -->
    <form method="POST" enctype="multipart/form-data" id="tmplForm" onsubmit="syncEditor()">
      <?= csrf_input() ?>
      <input type="hidden" name="opslaan" value="1">
      <input type="hidden" name="slug" value="<?= h($actief['slug']) ?>">

      <!-- Onderwerp + hero velden -->
      <div style="padding:14px 16px;border-bottom:1px solid var(--rand);display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div>
          <label class="veld-label">📧 Onderwerpregel <span style="font-weight:400;opacity:.6">(inbox)</span></label>
          <input type="text" name="onderwerp" value="<?= h($actief['onderwerp']) ?>" required
                 style="width:100%;font-size:13px;padding:8px 11px">
          <div style="font-size:11px;color:var(--tekst-zacht);margin-top:3px">Zichtbaar in de inbox</div>
        </div>
        <div>
          <label class="veld-label">🖼 Hero titel <span style="font-weight:400;opacity:.6">(grote tekst)</span></label>
          <input type="text" name="hero_titel"
                 value="<?= h($actief['hero_titel'] ?? $actief['onderwerp']) ?>"
                 placeholder="Leeg = zelfde als onderwerp"
                 style="width:100%;font-size:13px;padding:8px 11px">
          <div style="font-size:11px;color:var(--tekst-zacht);margin-top:3px">Grote witte tekst in de header</div>
        </div>
        <div>
          <label class="veld-label">💬 Hero subtitel <span style="font-weight:400;opacity:.6">(optioneel)</span></label>
          <input type="text" name="hero_subtitel"
                 value="<?= h($actief['hero_subtitel'] ?? '') ?>"
                 placeholder="Bijv: Trainingsweekend staat voor de deur"
                 style="width:100%;font-size:13px;padding:8px 11px">
        </div>
        <div>
          <label class="veld-label">🎨 Hero kleur (achtergrond)</label>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="color" name="hero_kleur" value="<?= h($actief['hero_kleur'] ?? '#1A2540') ?>"
                   style="width:42px;height:36px;border:1px solid var(--rand);border-radius:6px;padding:2px;cursor:pointer">
            <input type="text" name="hero_kleur_tekst" value="<?= h($actief['hero_kleur'] ?? '#1A2540') ?>"
                   placeholder="#1A2540" oninput="this.previousElementSibling.value=this.value"
                   onchange="document.querySelector('[name=hero_kleur]').value=this.value"
                   style="flex:1;font-size:13px;padding:8px 11px;font-family:monospace">
          </div>
        </div>
        <div style="grid-column:1/-1">
          <label class="veld-label">📷 Hero afbeelding <span style="font-weight:400;opacity:.6">(optioneel)</span></label>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="file" name="hero_image_upload" accept="image/jpeg,image/png,image/webp"
                   style="font-size:12px;flex:1;min-width:200px">
            <input type="text" name="hero_image_url" value="<?= h($actief['hero_image_url'] ?? '') ?>"
                   placeholder="of URL: https://..."
                   style="flex:2;min-width:200px;font-size:13px;padding:8px 11px">
            <?php if (!empty($actief['hero_image_url'])): ?>
            <img src="<?= h(foto_url($actief['hero_image_url'])) ?>" alt="Preview"
                 style="height:36px;border-radius:4px;border:1px solid var(--rand)">
            <?php endif; ?>
          </div>
          <div style="font-size:11px;color:var(--tekst-zacht);margin-top:3px">
            Achtergrondfoto voor de header met donker overlay. Aanbevolen: 1200×400px.
          </div>
        </div>
      </div>

      <!-- Editor tabs -->
      <div style="padding:14px 16px 0">
        <div class="editor-tabs">
          <button type="button" class="etab actief" id="tabVisueel" onclick="wisseltab('visueel')">✏️ Visueel</button>
          <button type="button" class="etab" id="tabHtml" onclick="wisseltab('html')">&lt;/&gt; HTML</button>
        </div>
        <div class="wysiwyg-toolbar" id="toolbar">
          <button type="button" class="wb" onclick="fmt('bold')"><strong>B</strong></button>
          <button type="button" class="wb" onclick="fmt('italic')"><em>I</em></button>
          <button type="button" class="wb" onclick="fmt('underline')"><u>U</u></button>
          <span style="width:1px;background:var(--rand);margin:2px 4px"></span>
          <button type="button" class="wb" onclick="insertLink()">🔗 Link</button>
          <button type="button" class="wb" onclick="fmt('insertUnorderedList')">• Lijst</button>
          <button type="button" class="wb" onclick="insertRaw('<hr style=&quot;border:none;border-top:1px solid #e5e7eb;margin:20px 0&quot;>')">— Lijn</button>
        </div>
        <div id="editorVisueel" class="wysiwyg-editor" contenteditable="true" lang="nl"></div>
        <textarea name="body_html" id="htmlEditor"
                  style="display:none;width:100%;font-family:monospace;font-size:12px;line-height:1.6;min-height:300px;resize:vertical;border:1px solid var(--rand);border-radius:0 0 7px 7px;padding:12px"><?= h($actief['body_html']) ?></textarea>
      </div>

      <div style="padding:12px 16px;display:flex;gap:8px;align-items:center">
        <button type="submit" class="btn btn-primary">💾 Opslaan</button>
        <a href="?preview=<?= h($actief['slug']) ?>" target="_blank" class="btn btn-outline btn-sm">👁 Preview</a>
        <div style="flex:1"></div>
        <span style="font-size:11px;color:var(--grijs)">Bijgewerkt: <?= datum_nl(substr($actief['gewijzigd_op'],0,10)) ?></span>
      </div>
    </form>

    <!-- Reset: aparte form, BUITEN de hoofd form -->
    <div style="padding:0 16px 14px">
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="reset_slug" value="<?= h($actief['slug']) ?>">
        <button type="submit" class="btn btn-outline btn-sm"
                onclick="return confirm('Terug naar standaard tekst? Jouw wijzigingen gaan verloren.')">
          ↺ Reset naar standaard
        </button>
      </form>
    </div>
  </div>
</div>
</div>

<!-- 3: Variabelen referentie -->
<div>
<div class="card">
  <div class="card-header"><div class="card-title">📎 Variabelen</div></div>
  <div class="card-body" style="padding:12px 14px">
    <p style="font-size:12px;color:var(--tekst-zacht);margin-bottom:10px">Klik om in te voegen op cursorpositie:</p>
    <?php foreach ($vars_ref as $cat => $vars): ?>
    <div style="margin-bottom:10px">
      <div style="font-size:11px;font-weight:600;color:var(--grijs);text-transform:uppercase;margin-bottom:4px"><?= $cat ?></div>
      <?php foreach ($vars as $v): ?>
      <span class="var-chip" onclick="insertAtCursor('{{<?= $v ?>}}')">{{<?= $v ?>}}</span>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<div class="card" style="margin-top:12px">
  <div class="card-header"><div class="card-title">⚡ Shortcodes</div></div>
  <div class="card-body" style="padding:12px 14px">
    <?php foreach ($shortcodes as $code => $label): ?>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
      <span class="sc-chip" onclick="insertAtCursor('<?= h(addslashes($code)) ?>')"><?= h($code) ?></span>
      <span style="font-size:11px;color:var(--tekst-zacht)"><?= $label ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</div>
<?php endif; ?>
</div>

<script>
const initHtml = <?= json_encode($actief['body_html'] ?? '') ?>;
const editor   = document.getElementById('editorVisueel');
const htmlTA   = document.getElementById('htmlEditor');

if (editor && initHtml !== undefined) editor.innerHTML = initHtml;

function wisseltab(tab) {
    if (tab === 'visueel') {
        editor.innerHTML = htmlTA.value;
        document.getElementById('toolbar').style.display = '';
        editor.style.display = '';
        htmlTA.style.display = 'none';
        document.getElementById('tabVisueel').classList.add('actief');
        document.getElementById('tabHtml').classList.remove('actief');
    } else {
        htmlTA.value = editor.innerHTML;
        htmlTA.style.display = '';
        document.getElementById('toolbar').style.display = 'none';
        editor.style.display = 'none';
        document.getElementById('tabHtml').classList.add('actief');
        document.getElementById('tabVisueel').classList.remove('actief');
    }
}

function syncEditor() {
    // Altijd de visuele editor inhoud naar de textarea kopiëren voor submit
    if (editor && editor.style.display !== 'none') {
        htmlTA.value = editor.innerHTML;
    }
    htmlTA.style.display = ''; // Zorg dat de textarea zichtbaar/actief is voor submit
}

function fmt(cmd) { editor.focus(); document.execCommand(cmd, false, null); }

function insertLink() {
    const url = prompt('URL:'); if (!url) return;
    const tekst = prompt('Linktekst:', 'Klik hier'); if (!tekst) return;
    editor.focus();
    document.execCommand('insertHTML', false, '<a href="'+url+'">'+tekst+'</a>');
}

function insertRaw(html) {
    if (editor.style.display !== 'none') {
        editor.focus(); document.execCommand('insertHTML', false, html);
    } else {
        const ta = htmlTA, p = ta.selectionStart;
        ta.value = ta.value.slice(0,p) + html + ta.value.slice(ta.selectionEnd);
        ta.selectionStart = ta.selectionEnd = p + html.length; ta.focus();
    }
}

function insertAtCursor(tekst) {
    if (editor && editor.style.display !== 'none') {
        editor.focus();
        document.execCommand('insertText', false, tekst);
    } else if (htmlTA) {
        const p = htmlTA.selectionStart;
        htmlTA.value = htmlTA.value.slice(0,p) + tekst + htmlTA.value.slice(htmlTA.selectionEnd);
        htmlTA.selectionStart = htmlTA.selectionEnd = p + tekst.length; htmlTA.focus();
    }
}

editor?.addEventListener('paste', e => {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, text);
});
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>
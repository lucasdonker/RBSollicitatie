<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail_render.php';
$pagina_titel = 'Mailings';
$actieve_nav  = 'mailing';
vereis_recht('mailing', 'bewerken');

$db      = db();
$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;

// Templates
$templates = [];
try {
    $templates = $db->query("SELECT slug, naam, onderwerp, hero_titel FROM mail_templates WHERE slug NOT LIKE '\\_%' ORDER BY naam")->fetchAll();
} catch (Exception $e) {}

// Sollicitanten
$alle_soll = [];
if ($sid) {
    $stmt = $db->prepare("
        SELECT s.id, s.fase, p.voornaam, p.tussenvoegsel, p.achternaam, p.email
        FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id
        WHERE s.seizoen_id = ? ORDER BY p.achternaam, p.voornaam
    ");
    $stmt->execute([$sid]);
    $alle_soll = $stmt->fetchAll();
}
$per_fase = [];
foreach ($alle_soll as $s) $per_fase[$s['fase']][] = $s;

// ── Verzenden ────────────────────────────────────────────────
$verzend_resultaten = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verzend'])) {
    csrf_check();

    $template_slug  = $_POST['template_slug'] ?? '';
    $gebruik_tmpl   = ($template_slug && $template_slug !== '_custom');
    $mail_stijl     = $_POST['mail_stijl'] ?? 'volledig';  // volledig | eenvoudig | eigen
    $eigen_img      = trim($_POST['eigen_afbeelding'] ?? '');
    $custom_subject = trim($_POST['custom_onderwerp'] ?? '');
    $custom_hero    = trim($_POST['custom_hero_titel'] ?? '') ?: $custom_subject;
    $custom_body    = $_POST['custom_body'] ?? '';
    $ids            = array_map('intval', $_POST['ontvangers'] ?? []);

    if (empty($ids)) { flash('fout','Selecteer minimaal één ontvanger.'); redirect(APP_URL.'/admin/mailing.php'); }

    $verzend_lijst = array_filter($alle_soll, fn($s) => in_array((int)$s['id'], $ids, true));
    $ok = 0; $fail = 0; $details = [];

    // Drempel: bij meer dan 10 ontvangers via queue (cron verstuurt dan)
    $via_queue = count($verzend_lijst) > 10 && function_exists('mail_queue_toevoegen');

    foreach ($verzend_lijst as $s) {
        $sol = ['voornaam'=>$s['voornaam'],'tussenvoegsel'=>$s['tussenvoegsel']??'','achternaam'=>$s['achternaam'],'email'=>$s['email'],'telefoon'=>''];
        try {
            // Bouw onderwerp + html (zelfde logica voor queue of direct)
            if ($gebruik_tmpl) {
                if ($via_queue) {
                    // Render template-html zonder direct te versturen
                    $vars     = mail_variabelen($sol, $seizoen ?? []);
                    $rendered = mail_template_render($template_slug, $vars);
                    if (!$rendered) throw new Exception('Template niet gevonden of leeg');
                    $onderwerp = $rendered['onderwerp'];
                    $html      = mail_html_wrapper($rendered['body_html'], $onderwerp, $rendered['hero_titel']);
                    if (!mail_queue_toevoegen($s['email'], $s['voornaam'], $onderwerp, $html, (int)$s['id'])) throw new Exception('Queue fout');
                } else {
                    $verstuurd = stuur_template_mail($template_slug, $s['email'], $sol, $seizoen ?? [], [], (int)$s['id']);
                    if (!$verstuurd) throw new Exception('Verzenden mislukt');
                }
            } else {
                if (!$custom_subject) throw new Exception('Geen onderwerp opgegeven');
                $vars      = mail_variabelen($sol, $seizoen ?? []);
                $onderwerp = mail_vervang($custom_subject, $vars);
                $hero      = mail_vervang($custom_hero, $vars);
                $body      = mail_shortcodes(mail_vervang($custom_body, $vars));
                $html = match($mail_stijl) {
                    'volledig' => mail_html_wrapper($body, $onderwerp, $hero),
                    'eigen'    => mail_simple_wrapper($body, $onderwerp, $eigen_img),
                    default    => mail_simple_wrapper($body, $onderwerp),
                };
                if ($via_queue) {
                    if (!mail_queue_toevoegen($s['email'], $s['voornaam'], $onderwerp, $html, (int)$s['id'])) throw new Exception('Queue fout');
                } else {
                    $verstuurd = Mailer::verstuur($s['email'], $s['voornaam'], $onderwerp, $html, (int)$s['id']);
                    if (!$verstuurd) throw new Exception('SMTP fout');
                }
            }
            $ok++; $details[] = ['naam'=>volledige_naam($s),'email'=>$s['email'],'status'=>'ok'];
        } catch (Exception $e) {
            $fail++; $details[] = ['naam'=>volledige_naam($s),'email'=>$s['email'],'status'=>'fout','fout'=>$e->getMessage()];
        }
        if (!$via_queue && count($verzend_lijst) > 20) usleep(100000);
    }
    $verzend_resultaten = compact('ok','fail','details','via_queue');
}

// Mail log
$mail_log = [];
try {
    $mail_log = $db->query("SELECT ml.*, p.voornaam, p.achternaam FROM mail_log ml LEFT JOIN sollicitaties s ON s.id=ml.sollicitatie_id LEFT JOIN personen p ON p.id=s.persoon_id ORDER BY ml.verstuurd_op DESC LIMIT 100")->fetchAll();
} catch (Exception $e) {}

$tab = $_GET['tab'] ?? 'nieuw';
require __DIR__ . '/layout_top.php';
?>
<style>
.mail-grid { display:grid; grid-template-columns:1fr 340px; gap:16px; align-items:start; }
.refs-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

/* WYSIWYG */
.editor-wrap { border:1px solid var(--rand); border-radius:8px; overflow:hidden; }
.editor-toolbar { background:#f7f8fa; padding:6px 8px; display:flex; flex-wrap:wrap; gap:3px; border-bottom:1px solid var(--rand); }
.etool { padding:5px 8px; border:1px solid transparent; background:none; border-radius:4px; cursor:pointer; font-size:13px; color:var(--tekst); display:inline-flex; align-items:center; gap:3px; white-space:nowrap; }
.etool:hover { background:#fff; border-color:var(--rand); }
.etool.actief { background:#fff; border-color:var(--oranje); color:var(--oranje); }
.etool-sep { width:1px; background:var(--rand); margin:3px 2px; align-self:stretch; }
.editor-body { min-height:260px; padding:14px 16px; outline:none; font-family:Arial,sans-serif; font-size:14px; line-height:1.75; color:#374151; overflow-y:auto; }
.editor-body:focus { box-shadow:inset 0 0 0 2px rgba(255,107,53,.15); }
.editor-body a { color:#FF6B35; }
.editor-body p { margin:0 0 14px; }
.editor-html { display:none; width:100%; min-height:260px; padding:12px 14px; font-family:monospace; font-size:12px; line-height:1.6; border:none; resize:vertical; color:var(--tekst); background:#fafafa; }
.editor-tabs { display:flex; border-bottom:1px solid var(--rand); background:#f7f8fa; }
.etab { padding:7px 14px; font-size:12px; font-weight:500; cursor:pointer; border:none; background:none; color:var(--grijs); border-bottom:2px solid transparent; }
.etab.actief { color:var(--navy); border-bottom-color:var(--oranje); background:#fff; }

/* Variabelen */
.var-chip { display:inline-block; background:#eff8ff; color:#1a56a0; font-size:11px; font-family:monospace; padding:2px 6px; border-radius:4px; margin:2px; cursor:pointer; border:1px solid #bee3f8; }
.var-chip:hover { background:#bee3f8; }
.sc-item { display:flex; align-items:flex-start; gap:6px; margin-bottom:6px; }
.sc-chip { background:#f7f8fa; border:1px solid var(--rand); font-size:11px; font-family:monospace; padding:3px 7px; border-radius:4px; cursor:pointer; white-space:nowrap; flex-shrink:0; }
.sc-chip:hover { background:var(--oranje-bg); border-color:var(--oranje); }

/* Ontvangers */
.og-header { display:flex; align-items:center; gap:8px; padding:7px 10px; background:var(--grijs-bg); border-radius:6px; cursor:pointer; }
.og-header:hover { background:#e8edf4; }
.ontvanger-rij { display:flex; align-items:center; gap:8px; padding:5px 6px; border-radius:5px; cursor:pointer; font-size:13px; }
.ontvanger-rij:hover { background:var(--grijs-bg); }

/* Mail stijl kiezer */
.stijl-kiezer { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:14px; }
.stijl-opt { position:relative; }
.stijl-opt input { position:absolute; opacity:0; }
.stijl-opt label { display:flex; flex-direction:column; align-items:center; gap:6px; padding:12px 8px; border:2px solid var(--rand); border-radius:8px; cursor:pointer; font-size:12px; font-weight:500; text-align:center; transition:all .15s; }
.stijl-opt input:checked + label { border-color:var(--oranje); background:var(--oranje-bg); color:var(--navy); }
.stijl-opt label:hover { border-color:var(--oranje); }
.stijl-preview { font-size:20px; }
</style>

<div class="fase-tabs" style="margin-bottom:16px">
  <a href="?tab=nieuw" class="fase-tab <?=$tab==='nieuw'?'actief':''?>">✉️ Nieuwe mailing</a>
  <a href="?tab=log"   class="fase-tab <?=$tab==='log'?'actief':''?>">📋 Mail log <span class="count"><?=count($mail_log)?></span></a>
</div>

<?php if ($tab === 'log'): ?>
<div class="card">
  <div class="card-header"><div class="card-title">Mail log</div></div>
  <?php if (empty($mail_log)): ?>
  <div class="card-body"><p style="color:var(--grijs)">Nog geen mails verstuurd.</p></div>
  <?php else: ?>
  <div style="padding:0">
    <?php foreach ($mail_log as $ml): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid var(--rand);font-size:12px">
      <span style="width:10px;height:10px;border-radius:50%;background:<?=$ml['status']==='verstuurd'?'var(--groen)':'var(--rood)'?>;flex-shrink:0"></span>
      <span style="width:120px;flex-shrink:0;font-weight:500"><?=h(trim(($ml['voornaam']??'').' '.($ml['achternaam']??'')))?:h($ml['ontvanger'])?></span>
      <span style="flex:1;color:var(--tekst-zacht);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=h($ml['onderwerp'])?></span>
      <span style="color:var(--grijs);flex-shrink:0"><?=datum_nl(substr($ml['verstuurd_op'],0,10))?> <?=substr($ml['verstuurd_op'],11,5)?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php else: /* ── TAB: NIEUWE MAILING ── */ ?>

<?php if ($verzend_resultaten): ?>
<div class="card" style="margin-bottom:16px;border-left:4px solid <?=$verzend_resultaten['fail']===0?'var(--groen)':'var(--oranje)'?>">
  <div class="card-body">
    <div style="font-size:16px;font-weight:700;color:var(--navy);margin-bottom:10px">
      <?=$verzend_resultaten['fail']===0?'✅':'⚠️'?>
      <?php if (!empty($verzend_resultaten['via_queue'])): ?>
      <?=$verzend_resultaten['ok']?> in wachtrij geplaatst<?=$verzend_resultaten['fail']?' · '.$verzend_resultaten['fail'].' mislukt':''?>
      <span style="font-size:12px;color:var(--tekst-zacht);font-weight:400">— wordt verstuurd via cron (~20 per 5 min)</span>
      <?php else: ?>
      <?=$verzend_resultaten['ok']?> verstuurd<?=$verzend_resultaten['fail']?' · '.$verzend_resultaten['fail'].' mislukt':''?>
      <?php endif; ?>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:5px">
      <?php foreach ($verzend_resultaten['details'] as $d): ?>
      <div style="font-size:12px;padding:5px 8px;background:<?=$d['status']==='ok'?'#e1f5ee':'#fff5f5'?>;border-radius:5px">
        <?=$d['status']==='ok'?'✓':'✗'?> <strong><?=h($d['naam'])?></strong>
        <?=isset($d['fout'])?'<br><span style="color:var(--rood)">'.h($d['fout']).'</span>':''?>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:12px;display:flex;gap:8px">
      <a href="<?=APP_URL?>/admin/mailing.php" class="btn btn-outline btn-sm">Nieuwe mailing</a>
      <a href="?tab=log" class="btn btn-outline btn-sm">Mail log bekijken</a>
    </div>
  </div>
</div>
<?php endif; ?>

<form method="POST" id="mailingForm" onsubmit="syncEditor()">
  <?= csrf_input() ?>
  <input type="hidden" name="verzend" value="1">

  <div class="mail-grid">

    <!-- ═══ LINKS: Inhoud ═══════════════════════════════════════ -->
    <div>

      <!-- Template kiezen -->
      <div class="card" style="margin-bottom:14px">
        <div class="card-header"><div class="card-title">📨 Template</div></div>
        <div class="card-body">
          <select name="template_slug" id="templateSelect" onchange="wisselTemplate(this.value)"
                  style="width:100%;font-size:14px;padding:9px 12px">
            <option value="">— Kies een template —</option>
            <?php foreach ($templates as $t): ?>
            <option value="<?=h($t['slug'])?>"><?=h($t['naam'])?></option>
            <?php endforeach; ?>
            <option value="_custom">✏️ Zelf schrijven</option>
          </select>

          <div id="tmplPreviewRij" style="display:none;margin-top:10px;display:none">
            <a id="tmplPreviewLink" href="#" target="_blank" class="btn btn-outline btn-sm">
              👁 Preview template
            </a>
            <a href="<?=APP_URL?>/admin/mail_templates.php" target="_blank"
               class="btn btn-outline btn-sm" style="margin-left:6px">
              ✏️ Template bewerken
            </a>
          </div>
        </div>
      </div>

      <!-- Custom mail velden (verborgen tenzij _custom) -->
      <div id="customVelden" style="display:none">

        <!-- Mail stijl -->
        <div class="card" style="margin-bottom:14px">
          <div class="card-header"><div class="card-title">🎨 Opmaakstijl</div></div>
          <div class="card-body">
            <div class="stijl-kiezer">
              <div class="stijl-opt">
                <input type="radio" name="mail_stijl" id="stijl-volledig" value="volledig" checked onchange="wisselStijl('volledig')">
                <label for="stijl-volledig">
                  <div class="stijl-preview">🌊</div>
                  <strong>Volledig</strong>
                  Hero afbeelding + Paal 13 stijl
                </label>
              </div>
              <div class="stijl-opt">
                <input type="radio" name="mail_stijl" id="stijl-eenvoudig" value="eenvoudig" onchange="wisselStijl('eenvoudig')">
                <label for="stijl-eenvoudig">
                  <div class="stijl-preview">📄</div>
                  <strong>Eenvoudig</strong>
                  Logo + titel, geen foto
                </label>
              </div>
              <div class="stijl-opt">
                <input type="radio" name="mail_stijl" id="stijl-eigen" value="eigen" onchange="wisselStijl('eigen')">
                <label for="stijl-eigen">
                  <div class="stijl-preview">🖼️</div>
                  <strong>Eigen afbeelding</strong>
                  Jouw foto als header
                </label>
              </div>
            </div>

            <!-- Eigen afbeelding URL (verborgen tenzij 'eigen') -->
            <div id="eigenAfbeeldingVeld" style="display:none">
              <div class="form-groep">
                <label>URL van jouw afbeelding</label>
                <input type="url" name="eigen_afbeelding" placeholder="https://paal13.com/fotos/strand.jpg">
                <div class="form-hulp">Afbeelding wordt bovenaan de mail geplaatst. Breedte wordt 560px.</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Onderwerp + hero titel -->
        <div class="card" style="margin-bottom:14px">
          <div class="card-header"><div class="card-title">📝 Onderwerp & titel</div></div>
          <div class="card-body">
            <div class="form-groep">
              <label>📧 Onderwerpregel <span style="font-weight:400;color:var(--grijs)">(inbox)</span></label>
              <input type="text" name="custom_onderwerp" id="customOnderwerp"
                     placeholder="Bijv: Informatie over het aankomende seizoen"
                     style="font-size:14px">
            </div>
            <div class="form-groep" id="heroTitelVeld">
              <label>🖼 Hero titel <span style="font-weight:400;color:var(--grijs)">(grote tekst over de afbeelding)</span></label>
              <input type="text" name="custom_hero_titel"
                     placeholder="Laat leeg voor zelfde als onderwerp"
                     style="font-size:14px">
              <div class="form-hulp">Alleen zichtbaar bij "Volledig" stijl</div>
            </div>
          </div>
        </div>

        <!-- WYSIWYG editor -->
        <div class="card" style="margin-bottom:14px">
          <div class="card-header">
            <div class="card-title">✉️ Bericht</div>
            <div class="editor-tabs" style="border:none;background:none">
              <button type="button" class="etab actief" id="tabVis" onclick="wisseltab('vis')">Visueel</button>
              <button type="button" class="etab" id="tabHtml" onclick="wisseltab('html')">HTML</button>
            </div>
          </div>
          <div class="card-body" style="padding:0">
            <div class="editor-wrap">
              <!-- Toolbar -->
              <div class="editor-toolbar" id="editorToolbar">
                <!-- Opmaak -->
                <select onchange="fmtBlock(this.value);this.value=''" style="font-size:12px;padding:4px 6px;border:1px solid var(--rand);border-radius:4px;height:30px">
                  <option value="">Alinea</option>
                  <option value="h2">Kop 2</option>
                  <option value="h3">Kop 3</option>
                  <option value="p">Normaal</option>
                </select>
                <div class="etool-sep"></div>
                <button type="button" class="etool" onclick="fmt('bold')" title="Vet (Ctrl+B)"><strong>B</strong></button>
                <button type="button" class="etool" onclick="fmt('italic')" title="Schuin (Ctrl+I)"><em>I</em></button>
                <button type="button" class="etool" onclick="fmt('underline')" title="Onderstrepen"><u>U</u></button>
                <button type="button" class="etool" onclick="fmt('strikeThrough')" title="Doorhalen"><s>S</s></button>
                <div class="etool-sep"></div>
                <button type="button" class="etool" onclick="fmt('justifyLeft')" title="Links">⬅</button>
                <button type="button" class="etool" onclick="fmt('justifyCenter')" title="Gecentreerd">☰</button>
                <button type="button" class="etool" onclick="fmt('justifyRight')" title="Rechts">➡</button>
                <div class="etool-sep"></div>
                <button type="button" class="etool" onclick="fmt('insertUnorderedList')" title="Lijst">• Lijst</button>
                <button type="button" class="etool" onclick="fmt('insertOrderedList')" title="Nummering">1. Lijst</button>
                <div class="etool-sep"></div>
                <button type="button" class="etool" onclick="insertLink()" title="Link invoegen">🔗 Link</button>
                <button type="button" class="etool" onclick="fmt('unlink')" title="Link verwijderen">🔗✕</button>
                <div class="etool-sep"></div>
                <button type="button" class="etool" onclick="insertRaw('<hr style=&quot;border:none;border-top:1px solid #e5e7eb;margin:18px 0&quot;>')" title="Horizontale lijn">─</button>
                <div class="etool-sep"></div>
                <button type="button" class="etool" onclick="fmt('undo')" title="Ongedaan maken">↩</button>
                <button type="button" class="etool" onclick="fmt('redo')" title="Opnieuw">↪</button>
                <button type="button" class="etool" onclick="fmt('removeFormat')" title="Opmaak verwijderen">✕ Stijl</button>
              </div>
              <div id="editor" class="editor-body" contenteditable="true" spellcheck="true" lang="nl">
                <p>Hoi {{sollicitant.voornaam}},</p>
                <p></p>
              </div>
              <textarea name="custom_body" id="htmlEditor" class="editor-html"></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Verzendknop -->
      <div class="card">
        <div class="card-body" style="padding:14px 16px">
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <button type="submit" class="btn btn-primary btn-lg"
                    id="verzendKnop" disabled
                    onclick="return bevestig()">
              ✉️ Verzenden
            </button>
            <div>
              <div id="verzendTeller" style="font-size:13px;font-weight:600;color:var(--navy)">0 ontvangers geselecteerd</div>
              <div id="verzendHint" style="font-size:12px;color:var(--grijs)">Selecteer template en ontvangers</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ RECHTS: Ontvangers + variabelen ═════════════════════ -->
    <div>

      <!-- Ontvangers -->
      <div class="card" style="margin-bottom:14px">
        <div class="card-header">
          <div class="card-title">👥 Ontvangers</div>
          <div style="display:flex;gap:5px">
            <button type="button" class="btn btn-outline btn-sm" onclick="selAlles(true)">Alles</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="selAlles(false)">Geen</button>
          </div>
        </div>
        <div style="max-height:420px;overflow-y:auto;padding:10px 14px">
          <?php if (empty($alle_soll)): ?>
          <p style="font-size:13px;color:var(--grijs)">Geen sollicitanten in dit seizoen.</p>
          <?php else: ?>
          <?php $fase_volgorde = ['aangemeld','uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest','uitgenodigd_tw','tw_geweest','aangenomen','afgewezen'];
          foreach ($fase_volgorde as $f):
            if (empty($per_fase[$f])) continue; ?>
          <div style="margin-bottom:10px">
            <div class="og-header" onclick="toggleGroep('<?=$f?>')">
              <input type="checkbox" class="groep-cb" data-fase="<?=$f?>"
                     onclick="event.stopPropagation();selGroep('<?=$f?>',this.checked)"
                     style="width:auto;flex-shrink:0">
              <span class="badge badge-<?=fase_kleur($f)?>" style="font-size:11px"><?=fase_label($f)?></span>
              <span style="font-size:11px;color:var(--grijs);margin-left:auto"><?=count($per_fase[$f])?></span>
            </div>
            <div id="groep-<?=$f?>" style="padding:4px 0">
              <?php foreach ($per_fase[$f] as $s): ?>
              <label class="ontvanger-rij" data-fase="<?=$f?>">
                <input type="checkbox" name="ontvangers[]" value="<?=$s['id']?>"
                       class="ontvanger-cb fase-<?=$f?>" onchange="updateTeller()">
                <div>
                  <div style="font-weight:500;font-size:13px"><?=h(volledige_naam($s))?></div>
                  <div style="font-size:11px;color:var(--grijs)"><?=h($s['email'])?></div>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Variabelen referentie -->
      <div class="card" id="variabelenPanel" style="display:none">
        <div class="card-header"><div class="card-title">📎 Variabelen & shortcodes</div></div>
        <div class="card-body" style="padding:12px 14px">
          <?php
          $vref = ['Sollicitant'=>['sollicitant.voornaam','sollicitant.achternaam','sollicitant.volledige_naam','sollicitant.email'],'Seizoen'=>['seizoen.naam'],'Selectiedag'=>['selectiedag.datum','selectiedag.locatie','selectiedag.starttijd','selectiedag.verzameltijd'],'Trainingsweekend'=>['tw.datum_start','tw.datum_eind','tw.locatie','tw.verzameltijd'],'Links'=>['portaal.url','bestuur.email']];
          foreach ($vref as $cat => $vars): ?>
          <div style="margin-bottom:8px">
            <div style="font-size:10px;font-weight:600;color:var(--grijs);text-transform:uppercase;margin-bottom:3px"><?=$cat?></div>
            <?php foreach ($vars as $v): ?><span class="var-chip" onclick="insertVar('{{<?=$v?>>}}')">{{<?=$v?>}}</span><?php endforeach; ?>
          </div>
          <?php endforeach; ?>
          <div style="margin-top:10px;border-top:1px solid var(--rand);padding-top:10px">
            <div style="font-size:10px;font-weight:600;color:var(--grijs);text-transform:uppercase;margin-bottom:6px">Shortcodes</div>
            <?php foreach (['[knop href="URL"]Tekst[/knop]'=>'🔶 Knop','[info]Tekst[/info]'=>'ℹ️ Info blok','[let op]Tekst[/let op]'=>'⚠️ Waarschuwing'] as $sc=>$lbl): ?>
            <div class="sc-item"><span class="sc-chip" onclick="insertVar('<?=h(addslashes($sc))?>')"><?=h($sc)?></span><span style="font-size:11px;color:var(--tekst-zacht)"><?=$lbl?></span></div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</form>
<?php endif; ?>

<script>
const APP_URL = '<?= APP_URL ?>';
const editor  = document.getElementById('editor');
const htmlTA  = document.getElementById('htmlEditor');

// ── Template wisselen ──────────────────────────────────────────
function wisselTemplate(slug) {
    const custom = document.getElementById('customVelden');
    const plink  = document.getElementById('tmplPreviewRij');
    const vPanel = document.getElementById('variabelenPanel');
    if (slug === '_custom') {
        custom.style.display = '';
        if (plink) plink.style.display = 'none';
        vPanel.style.display = '';
    } else if (slug) {
        custom.style.display = 'none';
        if (plink) { plink.style.display = ''; document.getElementById('tmplPreviewLink').href = APP_URL + '/admin/mail_templates.php?preview=' + slug; }
        vPanel.style.display = 'none';
    } else {
        custom.style.display = 'none';
        if (plink) plink.style.display = 'none';
        vPanel.style.display = 'none';
    }
    updateKnop();
}

// ── Mail stijl wisselen ────────────────────────────────────────
function wisselStijl(s) {
    document.getElementById('eigenAfbeeldingVeld').style.display = s === 'eigen' ? '' : 'none';
    document.getElementById('heroTitelVeld').style.display       = s === 'volledig' ? '' : 'none';
}

// ── WYSIWYG tabs ───────────────────────────────────────────────
function wisseltab(tab) {
    if (tab === 'vis') {
        editor.innerHTML = htmlTA.value;
        editor.style.display  = '';
        htmlTA.style.display  = 'none';
        document.getElementById('editorToolbar').style.display = '';
        document.getElementById('tabVis').classList.add('actief');
        document.getElementById('tabHtml').classList.remove('actief');
    } else {
        htmlTA.value = editor.innerHTML;
        htmlTA.style.display  = '';
        editor.style.display  = 'none';
        document.getElementById('editorToolbar').style.display = 'none';
        document.getElementById('tabHtml').classList.add('actief');
        document.getElementById('tabVis').classList.remove('actief');
    }
}

function syncEditor() {
    if (editor.style.display !== 'none') htmlTA.value = editor.innerHTML;
    htmlTA.style.display = '';
}

// ── Editor commando's ──────────────────────────────────────────
function fmt(cmd) { editor.focus(); document.execCommand(cmd, false, null); }

function fmtBlock(tag) {
    if (!tag) return;
    editor.focus();
    document.execCommand('formatBlock', false, '<' + tag + '>');
}

function insertLink() {
    const url = prompt('URL:'); if (!url) return;
    const txt = prompt('Linktekst:', 'Klik hier'); if (!txt) return;
    editor.focus();
    document.execCommand('insertHTML', false, '<a href="' + url + '">' + txt + '</a>');
}

function insertRaw(html) {
    if (editor.style.display !== 'none') {
        editor.focus(); document.execCommand('insertHTML', false, html);
    } else {
        const p = htmlTA.selectionStart;
        htmlTA.value = htmlTA.value.slice(0,p) + html + htmlTA.value.slice(htmlTA.selectionEnd);
        htmlTA.selectionStart = htmlTA.selectionEnd = p + html.length; htmlTA.focus();
    }
}

function insertVar(tekst) {
    if (editor.style.display !== 'none') {
        editor.focus(); document.execCommand('insertText', false, tekst);
    } else {
        const p = htmlTA.selectionStart;
        htmlTA.value = htmlTA.value.slice(0,p) + tekst + htmlTA.value.slice(htmlTA.selectionEnd);
        htmlTA.selectionStart = htmlTA.selectionEnd = p + tekst.length; htmlTA.focus();
    }
}

editor?.addEventListener('paste', e => {
    e.preventDefault();
    const t = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, t);
});

// ── Ontvangers ─────────────────────────────────────────────────
function updateTeller() {
    const n  = document.querySelectorAll('.ontvanger-cb:checked').length;
    const el = document.getElementById('verzendTeller');
    const hi = document.getElementById('verzendHint');
    if (el) el.textContent = n + ' ontvanger' + (n !== 1 ? 's' : '') + ' geselecteerd';
    if (hi) hi.textContent = n > 20 ? '⏱ Kleine pauze tussen mails bij grote aantallen' : '';
    updateKnop();
}

function toggleGroep(f) {
    const el = document.getElementById('groep-' + f);
    el.style.display = el.style.display === 'none' ? '' : 'none';
}

function selGroep(fase, aan) {
    document.querySelectorAll('.fase-' + fase).forEach(c => c.checked = aan);
    updateTeller();
}

function selAlles(aan) {
    document.querySelectorAll('.ontvanger-cb, .groep-cb').forEach(c => c.checked = aan);
    updateTeller();
}

function updateKnop() {
    const n    = document.querySelectorAll('.ontvanger-cb:checked').length;
    const slug = document.getElementById('templateSelect').value;
    const knop = document.getElementById('verzendKnop');
    const ok   = n > 0 && !!slug;
    knop.disabled = !ok;
    knop.textContent = ok ? '✉️ Verzend naar ' + n + ' persoon' + (n !== 1 ? 'en' : '') : '✉️ Verzenden';
}

function bevestig() {
    const n    = document.querySelectorAll('.ontvanger-cb:checked').length;
    const sel  = document.getElementById('templateSelect');
    const naam = sel.options[sel.selectedIndex]?.text || '';
    return confirm('Mail "' + naam + '" verzenden naar ' + n + ' persoon' + (n !== 1 ? 'en' : '') + '?\nDit kan niet ongedaan worden.');
}

updateTeller();
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>
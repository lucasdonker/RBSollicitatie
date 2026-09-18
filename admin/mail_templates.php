<?php
/**
 * Beheer — Sjablonen per meldingstype (e-mail + korte WhatsApp/SMS-tekst + samenvatting).
 * Bron: includes/meldingen/registry.php (typen + standaardteksten) en tabel mail_templates (inhoud).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_editor.php';

/** Voorbeeldvariabelen voor preview/test (geen echte persoon). */
function sjabloon_voorbeeld_vars(): array {
    $vars = function_exists('melding_vars') ? melding_vars(0) : [];
    $extra = [
        'voornaam' => 'Lieke', 'achternaam' => 'de Vries', 'volledige_naam' => 'Lieke de Vries', 'tussenvoegsel' => 'de',
        'sollicitant.voornaam' => 'Lieke', 'sollicitant.achternaam' => 'de Vries', 'sollicitant.volledige_naam' => 'Lieke de Vries', 'sollicitant.email' => 'lieke@voorbeeld.nl',
        'sollicitant_email' => 'lieke@voorbeeld.nl', 'sollicitant_telefoon' => '06 12345678', 'ouder_naam' => 'Marieke de Vries', 'ontvanger_naam' => 'Marieke de Vries',
        'taak_naam' => 'Beter leren kennen-formulier', 'otp_code' => '483921', 'otp.code' => '483921', 'document_naam' => 'Intakeformulier selectiedag',
        'onderteken_url' => APP_URL . '/portaal/ondertekenen.php?token=voorbeeld', 'is_ouder' => '1', 'door' => 'je ouder/verzorger',
        'besluit' => 'goedgekeurd', 'besluit_html' => '<p>We houden daar rekening mee in de planning.</p>', 'verzoek_weken' => 'week 31 (27 jul)', 'verzoek_dagen' => '3', 'verzoek_toelichting' => 'Tentamenweek',
        'taken_aantal' => '2', 'taken_blok' => '<ul><li>Beter leren kennen-formulier</li><li>Beschikbaarheid doorgeven</li></ul>', 'laatste_info' => 'Neem water mee, het wordt warm.',
        'deadline_datum' => '1 mei 2026', 'dagen_resterend' => '5', 'deadline_voorbij' => '0', 'terugkerend_html' => '',
        'onderwerp' => 'Voorbeeldonderwerp', 'body_html' => '<p>Voorbeeldtekst.</p>', 'tekst_kort' => 'Voorbeeld korte tekst.',
        'formulieren_blok' => function_exists('ondertekening_formulieren_blok') ? ondertekening_formulieren_blok(['Intakeformulier selectiedag' => APP_URL . '/portaal/ondertekenen.php?token=voorbeeld']) : '',
    ];
    foreach ($extra as $k => $v) $vars[$k] = $v;
    if (empty($vars['seizoen_naam'])) { $vars['seizoen_naam'] = 'Sollicitatie 2026'; $vars['seizoen.naam'] = 'Sollicitatie 2026'; }
    return $vars;
}

// ── Preview: vóór alles, geen layout nodig ────────────────────
if (isset($_GET['preview'])) {
    vereist_login();
    $slug   = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['preview']);
    $kanaal = ($_GET['kanaal'] ?? 'email') === 'kort' ? 'kort' : 'email';
    $r = function_exists('melding_render') ? melding_render($slug, sjabloon_voorbeeld_vars()) : null;
    if (!$r) { http_response_code(404); die('Sjabloon niet gevonden'); }
    header('Content-Type: text/html; charset=utf-8');
    if ($kanaal === 'email') { echo $r['html']; exit; }
    ?><!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Voorbeeld kort bericht</title>
    <style>body{margin:0;background:#e5ddd5;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;display:flex;justify-content:center;padding:40px 16px}
    .b{max-width:340px;background:#fff;border-radius:12px;padding:12px 14px;font-size:15px;line-height:1.45;color:#111;box-shadow:0 1px 1px rgba(0,0,0,.1);white-space:pre-wrap;word-break:break-word}
    .m{font-size:11px;color:#667;text-align:right;margin-top:6px}</style></head>
    <body><div><div class="b"><?= h($r['tekst_kort'] ?: '(geen korte tekst)') ?><div class="m"><?= strlen($r['tekst_kort']) ?> tekens</div></div></div></body></html><?php
    exit;
}

$pagina_titel = 'Sjablonen';
$actieve_nav  = 'mail_templates';
vereis_recht('mailing', 'bewerken');
$db = db();
if (function_exists('sjablonen_auto_setup')) sjablonen_auto_setup();

$register = function_exists('meldingen_register') ? meldingen_register() : [];
$cats     = function_exists('meldingen_categorieen') ? meldingen_categorieen() : [];
$modi     = function_exists('meldingen_kanaalmodi') ? meldingen_kanaalmodi() : [];

// ── POST-acties ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = $_POST['actie'] ?? '';
    $slug  = preg_replace('/[^a-z0-9_-]/i', '', (string)($_POST['slug'] ?? ''));

    if ($actie === 'opslaan' && $slug !== '') {
        $onderwerp     = trim($_POST['onderwerp'] ?? '');
        $hero_titel    = trim($_POST['hero_titel'] ?? '');
        $hero_subtitel = trim($_POST['hero_subtitel'] ?? '');
        $hero_kleur    = trim($_POST['hero_kleur'] ?? '');
        if ($hero_kleur !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $hero_kleur)) $hero_kleur = '';
        $hero_image_url = trim($_POST['hero_image_url'] ?? '');
        $body          = (string)($_POST['body_html'] ?? '');
        $tekst_kort    = trim((string)($_POST['tekst_kort'] ?? ''));
        $samenvatting  = trim((string)($_POST['samenvatting'] ?? ''));
        $naam          = trim($_POST['naam'] ?? '');
        if (!empty($_FILES['hero_image_upload']['tmp_name']) && $_FILES['hero_image_upload']['error'] === 0) {
            $bron = $_FILES['hero_image_upload'];
            $ext  = strtolower(pathinfo($bron['name'], PATHINFO_EXTENSION));
            if ($bron['size'] < 5 * 1024 * 1024 && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && @getimagesize($bron['tmp_name'])) {
                $dir = __DIR__ . '/../uploads/mail_hero/';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $fn = 'hero_' . $slug . '_' . time() . '.' . $ext;
                if (move_uploaded_file($bron['tmp_name'], $dir . $fn)) $hero_image_url = '/uploads/mail_hero/' . $fn;
            }
        }
        if ($onderwerp === '') { flash('fout', 'Onderwerp is verplicht.'); redirect(APP_URL . '/admin/mail_templates.php?slug=' . urlencode($slug)); }
        // Bijlagen: bestaande ids behouden (minus verwijderde) + nieuwe upload
        $bijl_ids = array_values(array_filter(array_map('intval', $_POST['bijlage_ids'] ?? [])));
        if (!empty($_FILES['bijlage_upload']['tmp_name']) && function_exists('bijlage_opslaan')) {
            $bu = bijlage_opslaan($_FILES['bijlage_upload'], (int)(huidige_beheerder()['id'] ?? 0), 'Sjabloon ' . $slug);
            if ($bu['ok']) $bijl_ids[] = (int)$bu['id']; else flash('waarschuwing', 'Bijlage niet opgeslagen: ' . $bu['fout']);
        }
        try {
            $sql = "UPDATE mail_templates SET onderwerp=?, hero_titel=?, hero_subtitel=?, hero_image_url=?, hero_kleur=?, body_html=?, tekst_kort=?, samenvatting=?, bijlagen_json=?, gewijzigd_op=NOW()" . ($naam !== '' ? ", naam=?" : '') . " WHERE slug=?";
            $p = [$onderwerp, $hero_titel ?: null, $hero_subtitel ?: null, $hero_image_url ?: null, $hero_kleur ?: null, $body, $tekst_kort, $samenvatting, $bijl_ids ? json_encode(array_values(array_unique($bijl_ids))) : null];
            if ($naam !== '') $p[] = $naam;
            $p[] = $slug;
            if (!$in_db_post = (int)$db->query("SELECT COUNT(*) FROM mail_templates WHERE slug=" . $db->quote($slug))->fetchColumn()) {
                $rdef = $register[$slug] ?? null;
                $db->prepare("INSERT INTO mail_templates (slug, naam, beschrijving, onderwerp, body_html, categorie) VALUES (?,?,?,?,?,?)")
                   ->execute([$slug, $rdef['naam'] ?? ($naam ?: $slug), $rdef['beschrijving'] ?? '', $onderwerp, $body, $rdef['categorie'] ?? 'handmatig']);
            }
            $db->prepare($sql)->execute($p);
            if (function_exists('audit_log')) audit_log('gewijzigd', 'sjabloon', null, ['slug' => $slug]);
            flash('succes', 'Sjabloon opgeslagen.');
        } catch (Exception $e) { flash('fout', 'Opslaan mislukt: ' . $e->getMessage()); }
        redirect(APP_URL . '/admin/mail_templates.php?slug=' . urlencode($slug));
    }

    if ($actie === 'reset' && isset($register[$slug])) {
        $s = $register[$slug]['seed'];
        try {
            $db->prepare("UPDATE mail_templates SET onderwerp=?, hero_titel=?, hero_subtitel=?, hero_kleur=?, hero_image_url=NULL, body_html=?, tekst_kort=?, samenvatting=?, naam=?, gewijzigd_op=NOW() WHERE slug=?")
               ->execute([$s['onderwerp'], $s['hero_titel'] ?? '', $s['hero_subtitel'] ?? '', $s['hero_kleur'] ?? $register[$slug]['hero_kleur'], $s['body_html'], $s['tekst_kort'] ?? '', $s['samenvatting'] ?? '', $register[$slug]['naam'], $slug]);
            flash('succes', 'Sjabloon teruggezet naar de standaardtekst.');
        } catch (Exception $e) { flash('fout', 'Terugzetten mislukt: ' . $e->getMessage()); }
        redirect(APP_URL . '/admin/mail_templates.php?slug=' . urlencode($slug));
    }

    if ($actie === 'kanaal' && isset($register[$slug])) {
        $modus = $_POST['kanaalmodus'] ?? 'email';
        if (isset($modi[$modus])) { instelling_opslaan('notif.' . $slug, $modus); flash('succes', 'Kanaal voor dit type opgeslagen.'); }
        redirect(APP_URL . '/admin/mail_templates.php?slug=' . urlencode($slug));
    }

    if ($actie === 'nieuw') {
        $naam = trim($_POST['naam'] ?? '');
        $ascii = function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT', $naam) ?: $naam) : $naam;
        $basis = preg_replace('/[^a-z0-9]+/', '_', strtolower($ascii));
        $basis = trim($basis, '_');
        if ($naam === '' || $basis === '') { flash('fout', 'Geef een naam op.'); redirect(APP_URL . '/admin/mail_templates.php'); }
        $nieuw = 'eigen_' . substr($basis, 0, 40);
        try {
            $i = 1; $kandidaat = $nieuw;
            while ((int)$db->query("SELECT COUNT(*) FROM mail_templates WHERE slug=" . $db->quote($kandidaat))->fetchColumn() > 0) { $kandidaat = $nieuw . '_' . (++$i); }
            $db->prepare("INSERT INTO mail_templates (slug, naam, beschrijving, onderwerp, hero_titel, hero_subtitel, hero_kleur, body_html, tekst_kort, categorie, samenvatting) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$kandidaat, $naam, 'Eigen sjabloon voor handmatige of geplande berichten.', $naam, $naam, '', '#054d8a', "<p>Hoi {{voornaam}},</p>\n<p>...</p>", "Hoi {{voornaam}}, ... Meer info: {{portaal_url}}", 'handmatig', '']);
            flash('succes', 'Nieuw sjabloon aangemaakt.');
            redirect(APP_URL . '/admin/mail_templates.php?slug=' . urlencode($kandidaat));
        } catch (Exception $e) { flash('fout', 'Aanmaken mislukt: ' . $e->getMessage()); redirect(APP_URL . '/admin/mail_templates.php'); }
    }

    if ($actie === 'verwijder' && $slug !== '' && !isset($register[$slug]) && $slug !== '_hero_image') {
        try { $db->prepare("DELETE FROM mail_templates WHERE slug=?")->execute([$slug]); flash('succes', 'Sjabloon verwijderd.'); }
        catch (Exception $e) { flash('fout', 'Verwijderen mislukt.'); }
        redirect(APP_URL . '/admin/mail_templates.php');
    }

    if ($actie === 'hero_image') {
        $url = trim($_POST['hero_url'] ?? '');
        try {
            $db->exec("INSERT IGNORE INTO mail_templates (slug,naam,beschrijving,onderwerp,body_html) VALUES('_hero_image','Hero afbeelding','URL van de hero foto','Hero afbeelding','')");
            $db->prepare("UPDATE mail_templates SET body_html=? WHERE slug='_hero_image'")->execute([$url]);
            flash('succes', 'Hero-afbeelding opgeslagen.');
        } catch (Exception $e) { flash('fout', 'Opslaan mislukt.'); }
        redirect(APP_URL . '/admin/mail_templates.php');
    }
    redirect(APP_URL . '/admin/mail_templates.php');
}

// ── Data voor het scherm ───────────────────────────────────────
$rijen = [];
try { foreach ($db->query("SELECT * FROM mail_templates ORDER BY id")->fetchAll() as $r) $rijen[$r['slug']] = $r; } catch (Exception $e) {}
$hero_row = $rijen['_hero_image'] ?? null;

$groepen = [];
foreach ($cats as $ck => $cl) $groepen[$ck] = ['label' => $cl, 'items' => []];
$groepen['eigen'] = ['label' => 'Eigen sjablonen', 'items' => []];
$groepen['oud']   = ['label' => 'Niet gekoppeld (oud)', 'items' => []];
foreach ($register as $slug => $def) $groepen[$def['categorie']]['items'][$slug] = $def['naam'];
foreach ($rijen as $slug => $r) {
    if ($slug === '_hero_image' || isset($register[$slug])) continue;
    $groepen[str_starts_with($slug, 'eigen_') || ($r['categorie'] ?? '') === 'handmatig' ? 'eigen' : 'oud']['items'][$slug] = $r['naam'];
}

$actief_slug = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['slug'] ?? ''));
if ($actief_slug === '' || $actief_slug === '_hero_image') $actief_slug = array_key_first($register) ?: '';
$actief = function_exists('sjabloon_laden') ? sjabloon_laden($actief_slug) : ($rijen[$actief_slug] ?? null);
$def    = $register[$actief_slug] ?? null;
$in_db  = isset($rijen[$actief_slug]);
$modus  = $def && function_exists('melding_kanaalmodus') ? melding_kanaalmodus($actief_slug) : null;

$vars_basis = ['voornaam', 'achternaam', 'volledige_naam', 'sollicitant_email', 'sollicitant_telefoon', 'seizoen_naam',
               'selectiedag_datum', 'selectiedag_tijd', 'selectiedag_locatie', 'selectiedag_adres', 'selectiedag_eindtijd', 'selectiedag_meenemen',
               'tw_datum_start', 'tw_datum_eind', 'tw_locatie', 'tw_adres', 'tw_verzameltijd', 'tw_eindtijd', 'tw_meenemen',
               'portaal_url', 'bestuur_email', 'afmeld_url'];
$vars_type  = $def['vars'] ?? [];

require __DIR__ . '/layout_top.php';
?>
<style>
.tmpl-grid{display:grid;grid-template-columns:230px 1fr 300px;gap:14px;align-items:start}
@media (max-width:1100px){.tmpl-grid{grid-template-columns:1fr}}
.tmpl-lijst{background:#fff;border-radius:var(--radius);border:1px solid var(--rand);overflow:hidden}
.tmpl-item{display:block;padding:9px 14px;border-bottom:1px solid var(--rand);text-decoration:none;color:var(--tekst)}
.tmpl-item:hover{background:var(--grijs-bg)}
.tmpl-item.actief{background:var(--navy);color:#fff}
.tmpl-item .tn{font-weight:600;font-size:13px}
.tmpl-kop{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--grijs);padding:10px 12px 4px}
.var-chip{display:inline-block;background:var(--blauw-bg,#eff8ff);color:#1a56a0;font-size:11px;font-family:monospace;padding:2px 7px;border-radius:4px;margin:2px;cursor:pointer;border:1px solid #bee3f8}
.var-chip:hover{background:#bee3f8}
.veld-label{font-size:11px;font-weight:600;color:var(--grijs);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;display:block}
.tmpl-velden{padding:14px 16px;border-bottom:1px solid var(--rand);display:grid;grid-template-columns:1fr 1fr;gap:10px}
.tmpl-velden input[type=text]{width:100%;font-size:13px;padding:8px 11px}
</style>

<div class="tmpl-grid">

<!-- 1: Lijst -->
<div>
  <div class="tmpl-lijst">
    <?php foreach ($groepen as $gk => $g): if (empty($g['items'])) continue; ?>
      <div class="tmpl-kop"><?= h($g['label']) ?></div>
      <?php foreach ($g['items'] as $slug => $naam): ?>
      <a href="?slug=<?= h($slug) ?>" class="tmpl-item <?= $slug === $actief_slug ? 'actief' : '' ?>">
        <div class="tn"><?= h($naam) ?></div>
      </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <form method="POST" style="margin-top:10px;background:#fff;border-radius:var(--radius);border:1px solid var(--rand);padding:12px">
    <?= csrf_input() ?><input type="hidden" name="actie" value="nieuw">
    <div class="veld-label">Nieuw eigen sjabloon</div>
    <input type="text" name="naam" placeholder="Naam, bv. Magazine seizoen" required style="width:100%;font-size:12px;padding:6px 8px;margin-bottom:6px">
    <button type="submit" class="btn btn-outline btn-sm" style="width:100%;justify-content:center">Aanmaken</button>
    <div style="font-size:10px;color:var(--grijs);margin-top:6px">Voor handmatige en geplande berichten.</div>
  </form>

  <form method="POST" style="margin-top:10px;background:#fff;border-radius:var(--radius);border:1px solid var(--rand);padding:12px">
    <?= csrf_input() ?><input type="hidden" name="actie" value="hero_image">
    <div class="veld-label">Standaard hero-afbeelding (URL)</div>
    <input type="text" name="hero_url" value="<?= h($hero_row['body_html'] ?? '') ?>" placeholder="https://.../foto.jpg" style="width:100%;font-size:11px;padding:6px 8px;margin-bottom:5px">
    <div style="font-size:10px;color:var(--grijs);margin-bottom:6px">Leeg = standaard strandfoto</div>
    <button type="submit" class="btn btn-outline btn-sm" style="width:100%;justify-content:center">Opslaan</button>
  </form>
</div>

<!-- 2: Editor -->
<?php if ($actief): ?>
<div>
<div class="card">
  <div class="card-header" style="align-items:flex-start">
    <div>
      <div class="card-title"><?= h($actief['naam'] ?? $actief_slug) ?></div>
      <div style="font-size:12px;color:var(--tekst-zacht);margin-top:2px"><?= h($def['beschrijving'] ?? ($actief['beschrijving'] ?? '')) ?></div>
      <div style="font-size:11px;color:var(--grijs);margin-top:4px">
        Type: <code><?= h($actief_slug) ?></code>
        <?php if ($def): ?> · Ontvanger: <?= h($def['ontvanger']) ?><?php endif; ?>
        <?php if (!$in_db): ?> · <span style="color:var(--oranje)">nog niet in de database (standaardtekst); wordt bij opslaan aangemaakt</span><?php endif; ?>
      </div>
    </div>
    <?php if ($def): ?>
    <form method="POST" style="display:flex;gap:6px;align-items:center;margin:0">
      <?= csrf_input() ?><input type="hidden" name="actie" value="kanaal"><input type="hidden" name="slug" value="<?= h($actief_slug) ?>">
      <label style="font-size:12px;color:var(--tekst-zacht)">Kanaal</label>
      <select name="kanaalmodus" onchange="this.form.submit()" style="font-size:12px;padding:5px 8px">
        <?php foreach ($modi as $mk => $ml): if ($mk !== 'email' && $mk !== 'uit' && !$def['kort_toegestaan']) continue; ?>
        <option value="<?= $mk ?>" <?= $modus === $mk ? 'selected' : '' ?>><?= h($ml) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding:0">
    <form method="POST" enctype="multipart/form-data" id="tmplForm">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="opslaan">
      <input type="hidden" name="slug" value="<?= h($actief_slug) ?>">

      <div class="tmpl-velden">
        <?php if (!$def): ?>
        <div style="grid-column:1/-1">
          <label class="veld-label">Naam</label>
          <input type="text" name="naam" value="<?= h($actief['naam'] ?? '') ?>">
        </div>
        <?php endif; ?>
        <div>
          <label class="veld-label">Onderwerpregel (e-mail)</label>
          <input type="text" name="onderwerp" value="<?= h($actief['onderwerp'] ?? '') ?>" required>
        </div>
        <div>
          <label class="veld-label">Hero-titel</label>
          <input type="text" name="hero_titel" value="<?= h($actief['hero_titel'] ?? '') ?>" placeholder="Leeg = onderwerp">
        </div>
        <div>
          <label class="veld-label">Hero-subtitel</label>
          <input type="text" name="hero_subtitel" value="<?= h($actief['hero_subtitel'] ?? '') ?>">
        </div>
        <div>
          <label class="veld-label">Hero-kleur</label>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="color" value="<?= h($actief['hero_kleur'] ?: '#054d8a') ?>" oninput="this.nextElementSibling.value=this.value" style="width:42px;height:36px;border:1px solid var(--rand);border-radius:6px;padding:2px">
            <input type="text" name="hero_kleur" value="<?= h($actief['hero_kleur'] ?? '') ?>" placeholder="leeg = huisstijl-navy" style="flex:1;font-family:monospace">
          </div>
        </div>
        <div style="grid-column:1/-1">
          <label class="veld-label">Hero-afbeelding (optioneel)</label>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="file" name="hero_image_upload" accept="image/jpeg,image/png,image/webp" style="font-size:12px;flex:1;min-width:200px">
            <input type="text" name="hero_image_url" value="<?= h($actief['hero_image_url'] ?? '') ?>" placeholder="of URL" style="flex:2;min-width:200px">
          </div>
        </div>
      </div>

      <div style="padding:14px 16px 0">
        <label class="veld-label">E-mailtekst</label>
        <?= editor_html('tmpl', 'body_html', (string)($actief['body_html'] ?? ''), ['hoogte' => 320]) ?>
      </div>

      <div style="padding:14px 16px 0;display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <label class="veld-label">Korte tekst (WhatsApp/SMS) <span id="kortTeller" style="font-weight:400;text-transform:none"></span></label>
          <textarea name="tekst_kort" id="tekstKort" rows="5" maxlength="400" style="width:100%;font-size:13px;padding:8px 11px" oninput="kortTeller()" <?= ($def && !$def['kort_toegestaan']) ? 'placeholder="Dit type gaat alleen per e-mail."' : '' ?>><?= h($actief['tekst_kort'] ?? '') ?></textarea>
          <div style="font-size:11px;color:var(--tekst-zacht);margin-top:3px">Platte tekst, max 400 tekens, geen opmaak. Zelfde variabelen als de e-mail.</div>
        </div>
        <div>
          <label class="veld-label">Samenvatting (portaal)</label>
          <textarea name="samenvatting" rows="5" maxlength="200" style="width:100%;font-size:13px;padding:8px 11px"><?= h($actief['samenvatting'] ?? '') ?></textarea>
          <div style="font-size:11px;color:var(--tekst-zacht);margin-top:3px">Eén regel onder de titel in het berichtenoverzicht van de sollicitant.</div>
        </div>
      </div>

      <div style="padding:14px 16px 0">
        <label class="veld-label">Bijlagen (gaan mee met elke e-mail; WhatsApp/SMS krijgt een downloadlink)</label>
        <?php $bijl_huidig = []; if (!empty($actief['bijlagen_json']) && function_exists('bijlagen_van_ids')) { $j = json_decode((string)$actief['bijlagen_json'], true); if (is_array($j)) $bijl_huidig = bijlagen_van_ids($j); } ?>
        <?php foreach ($bijl_huidig as $bh): ?>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin:4px 0"><input type="checkbox" name="bijlage_ids[]" value="<?= (int)$bh['id'] ?>" checked> <?= h($bh['naam']) ?> <span style="color:var(--grijs);font-size:11px">(uitvinken = loskoppelen)</span></label>
        <?php endforeach; ?>
        <input type="file" name="bijlage_upload" accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx" style="font-size:12px">
        <div style="font-size:11px;color:var(--tekst-zacht);margin-top:3px">pdf, jpg, png, webp, docx, xlsx · max <?= function_exists('bijlagen_max_bytes') ? round(bijlagen_max_bytes() / 1048576) : 25 ?> MB<?= function_exists('bijlagen_max_bytes') && bijlagen_max_bytes() < 25 * 1048576 ? ' (beperkt door de serverinstelling in Plesk)' : '' ?>.</div>
      </div>

      <div style="padding:12px 16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <button type="submit" class="btn btn-primary">Opslaan</button>
        <a href="?preview=<?= h($actief_slug) ?>" target="_blank" class="btn btn-outline btn-sm">Voorbeeld e-mail</a>
        <a href="?preview=<?= h($actief_slug) ?>&kanaal=kort" target="_blank" class="btn btn-outline btn-sm">Voorbeeld kort bericht</a>
        <div style="flex:1"></div>
        <?php if (!empty($actief['gewijzigd_op'])): ?><span style="font-size:11px;color:var(--grijs)">Bijgewerkt: <?= datum_nl(substr($actief['gewijzigd_op'], 0, 10)) ?></span><?php endif; ?>
      </div>
    </form>

    <div style="padding:0 16px 14px;display:flex;gap:8px">
      <?php if ($def): ?>
      <form method="POST"><?= csrf_input() ?><input type="hidden" name="actie" value="reset"><input type="hidden" name="slug" value="<?= h($actief_slug) ?>">
        <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Terug naar de standaardtekst? Je wijzigingen gaan verloren.')">Terugzetten naar standaard</button></form>
      <?php else: ?>
      <form method="POST"><?= csrf_input() ?><input type="hidden" name="actie" value="verwijder"><input type="hidden" name="slug" value="<?= h($actief_slug) ?>">
        <button type="submit" class="btn btn-rood btn-sm" onclick="return confirm('Dit sjabloon verwijderen?')">Verwijderen</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>

<!-- 3: Variabelen -->
<div>
<div class="card">
  <div class="card-header"><div class="card-title">Variabelen</div></div>
  <div class="card-body" style="padding:12px 14px">
    <p style="font-size:12px;color:var(--tekst-zacht);margin-bottom:8px">Klik om in te voegen in de e-mailtekst (of typ ze in de korte tekst).</p>
    <?php if ($vars_type): ?>
    <div style="margin-bottom:10px">
      <div class="veld-label">Voor dit type</div>
      <?php foreach ($vars_type as $v): ?><span class="var-chip" onclick="P13Editor.insert('tmpl','{{<?= h($v) ?>}}')">{{<?= h($v) ?>}}</span><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="veld-label">Altijd beschikbaar</div>
    <?php foreach ($vars_basis as $v): ?><span class="var-chip" onclick="P13Editor.insert('tmpl','{{<?= h($v) ?>}}')">{{<?= h($v) ?>}}</span><?php endforeach; ?>
    <div style="margin-top:12px;border-top:1px solid var(--rand);padding-top:10px;font-size:11px;color:var(--tekst-zacht)">
      Opmaakblokken via de knoppen in de editor: Knop, Info-blok, Let op. Als tekst: <code>[knop href="URL"]Tekst[/knop]</code>, <code>[info]...[/info]</code>, <code>[let op]...[/let op]</code>, <code>[code]123456[/code]</code>.
    </div>
  </div>
</div>
</div>
<?php endif; ?>
</div>

<script>
function kortTeller(){var t=document.getElementById('tekstKort');var e=document.getElementById('kortTeller');if(t&&e)e.textContent='('+t.value.length+'/400)';}
kortTeller();
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>

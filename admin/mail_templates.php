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
        'taak_naam' => 'Kennismakingsformulier', 'otp_code' => '483921', 'otp.code' => '483921', 'document_naam' => 'Intakeformulier selectiedag',
        'onderteken_url' => APP_URL . '/portaal/ondertekenen.php?token=voorbeeld', 'is_ouder' => '1', 'door' => 'je ouder/verzorger',
        'besluit' => 'goedgekeurd', 'besluit_html' => '<p>We houden daar rekening mee in de planning.</p>', 'verzoek_weken' => 'week 31 (27 jul)', 'verzoek_dagen' => '3', 'verzoek_toelichting' => 'Tentamenweek',
        'taken_aantal' => '2', 'taken_blok' => '<ul><li>Kennismakingsformulier</li><li>Beschikbaarheid doorgeven</li></ul>', 'laatste_info' => 'Neem water mee, het wordt warm.',
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
    // Voorbeeldgegevens, maar wel sjablooninhoud: alleen wie berichten mag zien.
    vereis_recht('mailing', 'lezen');
    $slug   = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['preview']);
    $kanaal = ($_GET['kanaal'] ?? 'email') === 'kort' ? 'kort' : 'email';
    $r = function_exists('melding_render') ? melding_render($slug, sjabloon_voorbeeld_vars()) : null;
    if (!$r) { http_response_code(404); die('Sjabloon niet gevonden'); }
    header('Content-Type: text/html; charset=utf-8');
    if (!empty($r['fout'])) { echo '<!DOCTYPE html><html lang="nl"><meta charset="UTF-8"><title>Fout in het sjabloon</title><p>Fout in de logica van dit sjabloon: ' . h($r['fout']) . '</p></html>'; exit; }
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
vereis_recht('mailing', 'sjablonen');
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

    // Logica in de teksten eerst controleren. Klopt die niet, dan slaan we niets op en blijft je
    // invoer op het scherm staan (geen redirect): een fout sjabloon zou anders bij verzenden mislukken.
    $logica_fouten = [];
    if ($actie === 'opslaan' && $slug !== '' && function_exists('sjabloon_controleer')) {
        $logica_fouten = sjabloon_controleer([
            'Onderwerp' => [(string)($_POST['onderwerp'] ?? ''), false], 'Hero-titel' => [(string)($_POST['hero_titel'] ?? ''), false],
            'Hero-subtitel' => [(string)($_POST['hero_subtitel'] ?? ''), false], 'E-mailtekst' => [(string)($_POST['body_html'] ?? ''), true],
            'Korte tekst' => [(string)($_POST['tekst_kort'] ?? ''), false], 'Samenvatting' => [(string)($_POST['samenvatting'] ?? ''), false],
        ], $register[$slug]['vars'] ?? []);
        // De grens van de korte tekst geldt voor de langste variant, niet voor de logica eromheen.
        $logica_fouten = array_merge($logica_fouten, sjabloon_kort_controle((string)($_POST['tekst_kort'] ?? ''), (int)($register[$slug]['kort_max'] ?? 400)));
    }

    if ($actie === 'opslaan' && $slug !== '' && !$logica_fouten) {
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
    if (!$logica_fouten) redirect(APP_URL . '/admin/mail_templates.php');
    $_GET['slug'] = $slug;   // fout in de logica: hetzelfde sjabloon opnieuw tonen, met je invoer
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
if (!empty($logica_fouten) && $actief) {
    foreach (['onderwerp', 'hero_titel', 'hero_subtitel', 'hero_kleur', 'hero_image_url', 'body_html', 'tekst_kort', 'samenvatting', 'naam'] as $_k) {
        if (isset($_POST[$_k])) $actief[$_k] = (string)$_POST[$_k];
    }
}
$def    = $register[$actief_slug] ?? null;
$in_db  = isset($rijen[$actief_slug]);
$modus  = $def && function_exists('melding_kanaalmodus') ? melding_kanaalmodus($actief_slug) : null;

$vars_type  = $def['vars'] ?? [];   // extra voor dit type; de vaste lijst staat in melding_variabelen_lijst()

require __DIR__ . '/layout_top.php';
?>
<style>
.tmpl-grid{display:grid;grid-template-columns:230px 1fr 300px;gap:14px;align-items:start}
@media (max-width:1100px){.tmpl-grid{grid-template-columns:1fr}}
.tmpl-lijst{background:var(--surface);border-radius:var(--radius);border:1px solid var(--rand);overflow:hidden}
.tmpl-item{display:block;padding:9px 14px;border-bottom:1px solid var(--rand);text-decoration:none;color:var(--tekst)}
.tmpl-item:hover{background:var(--grijs-bg)}
.tmpl-item.actief{background:var(--navy);color:var(--op-vol)}
.tmpl-item .tn{font-weight:600;font-size:13px}
.tmpl-kop{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--grijs);padding:10px 12px 4px}
.var-chip{display:inline-block;background:var(--fase-blauw-vlak);color:var(--fase-blauw);font-size:11px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;padding:2px 7px;border-radius:4px;margin:2px;cursor:pointer;border:1px solid var(--fase-blauw-rand)}
.var-chip:hover{border-color:var(--fase-blauw)}
.veld-label{font-size:11px;font-weight:600;color:var(--grijs);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;display:block}
.tmpl-velden{padding:14px 16px;border-bottom:1px solid var(--rand);display:grid;grid-template-columns:1fr 1fr;gap:10px}
.tmpl-velden input[type=text]{width:100%;font-size:13px;padding:8px 11px}
</style>

<?php $bt_actief = 'sjablonen'; require __DIR__ . '/_berichten_tabs.php'; ?>

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

  <form method="POST" class="bh-mt-zijform">
    <?= csrf_input() ?><input type="hidden" name="actie" value="nieuw">
    <label for="mtNieuwNaam" class="veld-label">Nieuw eigen sjabloon</label>
    <input type="text" id="mtNieuwNaam" name="naam" placeholder="Naam, bv. Magazine seizoen" required class="bh-mt-zijveld">
    <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm bh-mt-zijknop">Aanmaken</button>
    <div class="bh-mt-zijhint">Voor handmatige en geplande berichten.</div>
  </form>

  <form method="POST" class="bh-mt-zijform">
    <?= csrf_input() ?><input type="hidden" name="actie" value="hero_image">
    <label for="mtHeroUrl" class="veld-label">Standaard hero-afbeelding (URL)</label>
    <input type="text" id="mtHeroUrl" name="hero_url" value="<?= h($hero_row['body_html'] ?? '') ?>" placeholder="https://.../foto.jpg" class="bh-mt-zijveld bh-mt-zijveld-url">
    <div class="bh-mt-zijhint bh-mt-zijhint-boven">Leeg = standaard strandfoto</div>
    <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm bh-mt-zijknop">Opslaan</button>
  </form>
</div>

<!-- 2: Editor -->
<?php if ($actief): ?>
<div>
<div class="ds-kaart">
  <div class="bh-kaartkop bh-mt-kop">
    <div>
      <h2><?= h($actief['naam'] ?? $actief_slug) ?></h2>
      <div class="bh-mt-beschrijving"><?= h($def['beschrijving'] ?? ($actief['beschrijving'] ?? '')) ?></div>
      <div class="bh-mt-typeregel">
        Type: <code><?= h($actief_slug) ?></code>
        <?php if ($def): ?> · Ontvanger: <?= h($def['ontvanger']) ?><?php endif; ?>
        <?php if (!$in_db): ?> · <span class="bh-mt-nietindb">nog niet in de database (standaardtekst); wordt bij opslaan aangemaakt</span><?php endif; ?>
      </div>
    </div>
    <?php if ($def): ?>
    <form method="POST" class="bh-mt-kanaal">
      <?= csrf_input() ?><input type="hidden" name="actie" value="kanaal"><input type="hidden" name="slug" value="<?= h($actief_slug) ?>">
      <label for="veld-kanaalmodus" class="bh-mt-kanaal-label">Kanaal</label>
      <select id="veld-kanaalmodus" name="kanaalmodus" data-wijzig="P13Klik.verstuur(this)" class="bh-mt-kanaal-keuze">
        <?php foreach ($modi as $mk => $ml): if ($mk !== 'email' && $mk !== 'uit' && !$def['kort_toegestaan']) continue; ?>
        <option value="<?= $mk ?>" <?= $modus === $mk ? 'selected' : '' ?>><?= h($ml) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
  </div>
  <div class="bh-kaartbody bh-mt-body">
    <?php $fouten = $logica_fouten ?? []; $fouten_titel = 'Niet opgeslagen: de logica klopt nog niet'; require __DIR__ . '/_foutlijst.php'; ?>
    <form method="POST" enctype="multipart/form-data" id="tmplForm">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="opslaan">
      <input type="hidden" name="slug" value="<?= h($actief_slug) ?>">

      <div class="tmpl-velden">
        <?php if (!$def): ?>
        <div class="bh-mt-breed">
          <label for="veld-naam" class="veld-label">Naam</label>
          <input id="veld-naam" type="text" name="naam" value="<?= h($actief['naam'] ?? '') ?>">
        </div>
        <?php endif; ?>
        <div>
          <label for="veld-onderwerp" class="veld-label">Onderwerpregel (e-mail)</label>
          <input id="veld-onderwerp" type="text" name="onderwerp" data-var-veld value="<?= h($actief['onderwerp'] ?? '') ?>" required>
        </div>
        <div>
          <label for="veld-hero-titel" class="veld-label">Hero-titel</label>
          <input id="veld-hero-titel" type="text" name="hero_titel" data-var-veld value="<?= h($actief['hero_titel'] ?? '') ?>" placeholder="Leeg = onderwerp">
        </div>
        <div>
          <label for="veld-hero-subtitel" class="veld-label">Hero-subtitel</label>
          <input id="veld-hero-subtitel" type="text" name="hero_subtitel" data-var-veld value="<?= h($actief['hero_subtitel'] ?? '') ?>">
        </div>
        <div>
          <label class="veld-label">Hero-kleur</label>
          <div class="bh-mt-kleurrij">
            <input type="color" value="<?= h($actief['hero_kleur'] ?: '#054d8a') ?>" data-invoer="P13Klik.spiegel(this)" class="bh-mt-kleurkiezer">
            <input type="text" name="hero_kleur" value="<?= h($actief['hero_kleur'] ?? '') ?>" placeholder="leeg = huisstijl-navy" class="bh-mt-kleurcode">
          </div>
        </div>
        <div class="bh-mt-breed">
          <label class="veld-label">Hero-afbeelding (optioneel)</label>
          <div class="bh-mt-heroregel">
            <input type="file" name="hero_image_upload" accept="image/jpeg,image/png,image/webp" class="bh-mt-herobestand">
            <input type="text" name="hero_image_url" value="<?= h($actief['hero_image_url'] ?? '') ?>" placeholder="of URL" class="bh-mt-herourl">
          </div>
        </div>
      </div>

      <div class="bh-mt-sectie">
        <label class="veld-label">E-mailtekst</label>
        <?= editor_html('tmpl', 'body_html', (string)($actief['body_html'] ?? ''), ['hoogte' => 320]) ?>
      </div>

      <div class="bh-mt-sectie bh-mt-tweekolom">
        <div>
          <label class="veld-label">Korte tekst (WhatsApp/SMS) <span id="kortTeller" class="bh-mt-teller"></span></label>
          <?php $kort_max = (int)($def['kort_max'] ?? 400); ?>
          <textarea name="tekst_kort" id="tekstKort" data-var-veld rows="5" maxlength="<?= $kort_max * 5 ?>" data-kort-max="<?= $kort_max ?>" class="bh-mt-tekstvak" data-invoer="kortTeller()" <?= ($def && !$def['kort_toegestaan']) ? 'placeholder="Dit type gaat alleen per e-mail."' : '' ?>><?= h($actief['tekst_kort'] ?? '') ?></textarea>
          <div class="bh-mt-hulp">Max <?= $kort_max ?> tekens voor wat de ontvanger krijgt; met logica geldt dat voor de langste variant (gecontroleerd bij opslaan). Opmaak zoals in WhatsApp: *vet*, _cursief_, ~doorgehaald~ (sms toont die tekens niet). Zelfde variabelen en logica als de e-mail.</div>
        </div>
        <div>
          <label for="veld-samenvatting" class="veld-label">Samenvatting (portaal)</label>
          <textarea id="veld-samenvatting" name="samenvatting" data-var-veld rows="5" maxlength="200" class="bh-mt-tekstvak"><?= h($actief['samenvatting'] ?? '') ?></textarea>
          <div class="bh-mt-hulp">Eén regel onder de titel in het berichtenoverzicht van de sollicitant.</div>
        </div>
      </div>

      <div class="bh-mt-sectie">
        <label class="veld-label">Bijlagen (mee met elke e-mail, grote bestanden als downloadknop; WhatsApp/SMS krijgt een downloadlink)</label>
        <?php $bijl_huidig = []; if (!empty($actief['bijlagen_json']) && function_exists('bijlagen_van_ids')) { $j = json_decode((string)$actief['bijlagen_json'], true); if (is_array($j)) $bijl_huidig = bijlagen_van_ids($j); } ?>
        <?php foreach ($bijl_huidig as $bh): ?>
        <label class="bh-mt-bijlage"><input type="checkbox" name="bijlage_ids[]" value="<?= (int)$bh['id'] ?>" checked> <?= h($bh['naam']) ?> <span class="bh-mt-bijlage-hint">(uitvinken = loskoppelen)</span></label>
        <?php endforeach; ?>
        <input type="file" name="bijlage_upload" accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx">
        <div class="bh-mt-hulp">pdf, jpg, png, webp, docx, xlsx · max <?= function_exists('bijlagen_max_bytes') ? round(bijlagen_max_bytes() / 1048576) : 25 ?> MB<?= function_exists('bijlagen_max_bytes') && bijlagen_max_bytes() < 25 * 1048576 ? ' (beperkt door de serverinstelling in Plesk)' : '' ?>.</div>
      </div>

      <div class="bh-mt-voet">
        <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
        <a href="?preview=<?= h($actief_slug) ?>" target="_blank" class="ds-btn ds-btn-secundair ds-btn-sm">Voorbeeld e-mail</a>
        <a href="?preview=<?= h($actief_slug) ?>&kanaal=kort" target="_blank" class="ds-btn ds-btn-secundair ds-btn-sm">Voorbeeld kort bericht</a>
        <div class="bh-vulling"></div>
        <?php if (!empty($actief['gewijzigd_op'])): ?><span class="bh-mt-bijgewerkt">Bijgewerkt: <?= datum_nl(substr($actief['gewijzigd_op'], 0, 10)) ?></span><?php endif; ?>
      </div>
    </form>

    <div class="bh-mt-gevaarrij">
      <?php if ($def): ?>
      <form method="POST"
            data-bevestig-titel="Terug naar de standaardtekst?"
            data-bevestig="Je eigen tekst voor dit sjabloon gaat verloren en het bericht gaat er weer uitzien zoals het systeem het bedoeld heeft."
            data-bevestig-knop="Ja, terugzetten">
        <?= csrf_input() ?><input type="hidden" name="actie" value="reset"><input type="hidden" name="slug" value="<?= h($actief_slug) ?>">
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Terugzetten naar standaard</button></form>
      <?php else: ?>
      <form method="POST"
            data-bevestig-titel="Dit sjabloon verwijderen?"
            data-bevestig="Berichten die je hiermee hebt verstuurd blijven in het log staan; alleen het sjabloon zelf verdwijnt."
            data-bevestig-knop="Ja, verwijderen" data-bevestig-gevaar="1">
        <?= csrf_input() ?><input type="hidden" name="actie" value="verwijder"><input type="hidden" name="slug" value="<?= h($actief_slug) ?>">
        <button type="submit" class="ds-btn ds-btn-gevaar ds-btn-sm">Verwijderen</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>

<!-- 3: Variabelen -->
<div>
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Variabelen</h2></div>
  <div class="bh-kaartbody bh-mt-varbody">
    <p class="bh-mt-varuitleg">Klik om in te voegen in het veld waar je het laatst typte: de e-mailtekst, het onderwerp of de korte tekst.</p>
    <?php
      // Eén lijst met variabelen voor Berichten en Sjablonen (melding_variabelen_lijst()).
      $var_extra = array_combine($vars_type, array_map(fn($v) => ucfirst(str_replace('_', ' ', $v)), $vars_type)) ?: [];
      require __DIR__ . '/_variabelen.php';
      $vw_soort = 'sjabloon'; require __DIR__ . '/_voorwaarden.php';
    ?>
    <div class="bh-mt-opmaak">
      Opmaakblokken via de knoppen in de editor: Knop, Info-blok, Let op. Als tekst: <code>[knop href="URL"]Tekst[/knop]</code>, <code>[info]...[/info]</code>, <code>[let op]...[/let op]</code>, <code>[code]123456[/code]</code>.
    </div>
  </div>
</div>
</div>
<?php endif; ?>
</div>

<script nonce="<?= h(csp_nonce()) ?>">
function kortTeller(){var t=document.getElementById('tekstKort');var e=document.getElementById('kortTeller');if(!t||!e)return;
  var logica=/\{\{\s*\/?\s*(als|if|kies|switch)\b/i.test(t.value);
  e.textContent=logica?'('+t.value.length+' tekens met logica; max '+t.dataset.kortMax+' per variant)':'('+t.value.length+'/'+t.dataset.kortMax+')';}
kortTeller();
</script>
<script src="<?= APP_URL ?>/assets/js/variabelen.js?v=<?= @filemtime(__DIR__ . '/../assets/js/variabelen.js') ?>" defer></script>

<?php require __DIR__ . '/layout_bottom.php'; ?>

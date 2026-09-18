<?php
/**
 * Beheer — Berichten: handmatig een bericht sturen (individueel, geselecteerd, per fase) via
 * e-mail en/of WhatsApp/SMS, plus het berichtenlog van alle kanalen.
 * Alles loopt via de centrale meldingen-kern (melding_verstuur, type 'handmatig').
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_editor.php';
$pagina_titel = 'Berichten';
$actieve_nav  = 'berichten';
vereis_recht('mailing', 'lezen');

$db      = db();
$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;
$tab     = in_array($_GET['tab'] ?? 'nieuw', ['nieuw', 'log'], true) ? $_GET['tab'] : 'nieuw';
$modi    = function_exists('meldingen_kanaalmodi') ? meldingen_kanaalmodi() : ['email' => 'E-mail'];
unset($modi['uit']);
$fases   = function_exists('fasen') ? fasen() : [];
$mag_bewerken = function_exists('mag') ? mag('mailing', 'bewerken') : true;

// ── Sollicitanten (voor de ontvangerskiezer) ──────────────────
$alle = [];
if ($sid) {
    if (function_exists('personen_kanaal_auto_setup')) personen_kanaal_auto_setup();
    try {
        $q = $db->prepare("SELECT s.id, s.fase, s.persoon_id, p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon,
                                  COALESCE(p.mailing_afgemeld,0) AS afgemeld, COALESCE(p.kanaal_voorkeur,'whatsapp') AS kanaal_voorkeur
                           FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id
                           WHERE s.seizoen_id=? AND s.fase NOT IN ('verwijderd_door_sollicitant') ORDER BY p.achternaam, p.voornaam");
        $q->execute([$sid]);
        $alle = $q->fetchAll();
    } catch (Exception $e) {}
}
$per_fase = [];
foreach ($alle as $s) $per_fase[$s['fase']][] = $s;
$voorsel = array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? ''))));
if (!empty($_GET['fase']) && isset($per_fase[$_GET['fase']])) foreach ($per_fase[$_GET['fase']] as $s) $voorsel[] = (int)$s['id'];

// ── Eigen sjablonen ────────────────────────────────────────────
$sjablonen = [];
try { $sjablonen = $db->query("SELECT slug, naam, onderwerp FROM mail_templates WHERE slug LIKE 'eigen\\_%' OR categorie='handmatig' ORDER BY naam")->fetchAll(); } catch (Exception $e) {}
$sjablonen = array_values(array_filter($sjablonen, fn($t) => $t['slug'] !== 'handmatig'));

/** Inhoud + override uit het formulier (of uit een eigen sjabloon). */
function berichten_inhoud(array $post, PDO $db): array {
    $slug = preg_replace('/[^a-z0-9_-]/i', '', (string)($post['sjabloon'] ?? ''));
    $inhoud = ['onderwerp' => trim($post['onderwerp'] ?? ''), 'body_html' => (string)($post['body_html'] ?? ''), 'tekst_kort' => trim((string)($post['tekst_kort'] ?? ''))];
    $override = ['hero_titel' => trim($post['hero_titel'] ?? '')];
    if ($slug !== '' && function_exists('sjabloon_laden')) {
        $t = sjabloon_laden($slug);
        if ($t) {
            $inhoud = ['onderwerp' => (string)$t['onderwerp'], 'body_html' => (string)$t['body_html'], 'tekst_kort' => (string)($t['tekst_kort'] ?? '')];
            $override = ['hero_titel' => (string)($t['hero_titel'] ?? ''), 'hero_subtitel' => (string)($t['hero_subtitel'] ?? ''), 'hero_kleur' => (string)($t['hero_kleur'] ?? ''), 'hero_image_url' => (string)($t['hero_image_url'] ?? '')];
            if (!empty($t['bijlagen_json'])) $override['bijlagen_json'] = $t['bijlagen_json'];
        }
    }
    return ['inhoud' => $inhoud, 'override' => array_filter($override, fn($v) => $v !== '')];
}

// ── POST: voorbeeld / test / verzenden ────────────────────────
$voorbeeld = null; $resultaat = null; $fout = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'nieuw') {
    csrf_check();
    if (!$mag_bewerken) { http_response_code(403); die('Geen recht om berichten te versturen.'); }
    $actie  = $_POST['actie'] ?? '';
    $modus  = isset($modi[$_POST['kanaalmodus'] ?? '']) ? $_POST['kanaalmodus'] : 'email';
    $ids    = array_values(array_unique(array_filter(array_map('intval', $_POST['ontvangers'] ?? []))));
    $bi     = berichten_inhoud($_POST, $db);
    $inhoud = $bi['inhoud']; $override = $bi['override'];
    $voorsel = $ids;
    // Bijlagen: geüploade bestanden naar de bibliotheek (alleen bij echt verzenden/testen)
    $bijlagen_opties = [];
    if (in_array($actie, ['verzend', 'test'], true) && !empty($_FILES['bijlagen']['name']) && function_exists('bijlage_opslaan')) {
        foreach ((array)$_FILES['bijlagen']['name'] as $i => $bn) {
            if ($bn === '' || ($_FILES['bijlagen']['error'][$i] ?? 4) !== UPLOAD_ERR_OK) continue;
            $een = ['name' => $bn, 'tmp_name' => $_FILES['bijlagen']['tmp_name'][$i], 'size' => $_FILES['bijlagen']['size'][$i], 'error' => $_FILES['bijlagen']['error'][$i]];
            $bu = bijlage_opslaan($een, (int)(huidige_beheerder()['id'] ?? 0), 'Handmatig bericht');
            if ($bu['ok']) $bijlagen_opties[] = (int)$bu['id']; else $fout = 'Bijlage "' . h($bn) . '" niet opgeslagen: ' . $bu['fout'];
        }
    }

    if ($fout) {
        // bijlage-fout: niets versturen
    } elseif ($inhoud['onderwerp'] === '' || trim(strip_tags($inhoud['body_html'])) === '') {
        $fout = 'Vul een onderwerp en een berichttekst in (of kies een sjabloon).';
    } elseif ($modus !== 'email' && $inhoud['tekst_kort'] === '') {
        $fout = 'Voor WhatsApp/SMS is een korte tekst nodig.';
    } elseif ($actie === 'voorbeeld') {
        $vars = function_exists('melding_vars') ? melding_vars($ids[0] ?? 0, $inhoud) : $inhoud;
        if (!$ids) { $vars['voornaam'] = 'Lieke'; $vars['achternaam'] = 'de Vries'; $vars['volledige_naam'] = 'Lieke de Vries'; }
        $voorbeeld = melding_render('handmatig', $vars, $override);
    } elseif ($actie === 'test') {
        $bhd = huidige_beheerder();
        $r = melding_verstuur('handmatig', 0, $inhoud, [
            'override' => $override, 'kanaalmodus' => 'email', 'prioriteit' => 'direct', 'negeer_plafond' => true, 'bijlagen' => $bijlagen_opties,
            'ontvanger' => ['email' => (string)($bhd['email'] ?? ''), 'naam' => (string)($bhd['naam'] ?? 'Test'), 'telefoon' => '', 'rol' => 'direct'],
        ]);
        $fout = $r['status'] === 'verstuurd' ? null : 'Testmail mislukt: ' . ($r['reden'] ?? 'onbekend');
        if (!$fout) flash('succes', 'Testmail verstuurd naar ' . h((string)($bhd['email'] ?? '')) . '.');
    } elseif ($actie === 'verzend') {
        if (!$ids) $fout = 'Selecteer minimaal één ontvanger.';
        else {
            $prio = count($ids) > 10 ? 'queue' : 'direct';
            $resultaat = ['ok' => 0, 'wacht' => 0, 'overgeslagen' => 0, 'mislukt' => 0, 'details' => [], 'via_queue' => $prio === 'queue'];
            $namen = []; foreach ($alle as $s) $namen[(int)$s['id']] = volledige_naam($s);
            foreach ($ids as $id) {
                if (!isset($namen[$id])) continue;
                $r = melding_verstuur('handmatig', $id, $inhoud, ['override' => $override, 'kanaalmodus' => $modus, 'prioriteit' => $prio, 'referentie' => 'handmatig:' . date('YmdHi'), 'bijlagen' => $bijlagen_opties]);
                $st = $r['status'];
                if ($st === 'verstuurd') $resultaat['ok']++; elseif ($st === 'wacht') $resultaat['wacht']++; elseif ($st === 'overgeslagen') $resultaat['overgeslagen']++; else $resultaat['mislukt']++;
                $resultaat['details'][] = ['naam' => $namen[$id], 'status' => $st, 'kanaal' => $r['kanaal'] ?? null, 'reden' => $r['reden'] ?? null];
            }
            if (function_exists('audit_log')) audit_log('bericht_verstuurd', 'sollicitatie', null, ['aantal' => count($ids), 'kanaal' => $modus, 'onderwerp' => mb_substr($inhoud['onderwerp'], 0, 80)]);
        }
    }
}

// ── Log ────────────────────────────────────────────────────────
$log = []; $log_detail = null; $f_kanaal = ''; $f_status = ''; $f_type = ''; $f_q = '';
if ($tab === 'log') {
    $f_kanaal = in_array($_GET['kanaal'] ?? '', ['email', 'whatsapp', 'sms'], true) ? $_GET['kanaal'] : '';
    $f_status = in_array($_GET['status'] ?? '', ['wacht', 'verstuurd', 'mislukt', 'overgeslagen'], true) ? $_GET['status'] : '';
    $f_type   = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['type'] ?? ''));
    $f_q      = trim((string)($_GET['q'] ?? ''));
    $waar = []; $p = [];
    if ($f_kanaal) { $waar[] = 'ml.kanaal=?'; $p[] = $f_kanaal; }
    if ($f_status) { $waar[] = 'ml.status=?'; $p[] = $f_status; }
    if ($f_type)   { $waar[] = 'ml.meldingstype=?'; $p[] = $f_type; }
    if ($f_q !== '') { $waar[] = '(ml.onderwerp LIKE ? OR ml.ontvanger LIKE ? OR p.voornaam LIKE ? OR p.achternaam LIKE ?)'; array_push($p, "%$f_q%", "%$f_q%", "%$f_q%", "%$f_q%"); }
    try {
        $q = $db->prepare("SELECT ml.id, ml.sollicitatie_id, ml.kanaal, ml.meldingstype, ml.categorie, ml.ontvanger_rol, ml.ontvanger, ml.onderwerp, ml.status, ml.foutmelding, ml.fallback_van, ml.verstuurd_op, p.voornaam, p.achternaam
                           FROM mail_log ml LEFT JOIN sollicitaties s ON s.id=ml.sollicitatie_id LEFT JOIN personen p ON p.id=s.persoon_id
                           " . ($waar ? 'WHERE ' . implode(' AND ', $waar) : '') . " ORDER BY ml.id DESC LIMIT 200");
        $q->execute($p);
        $log = $q->fetchAll();
    } catch (Exception $e) { $log = []; }
    if (!empty($_GET['log'])) {
        try { $q = $db->prepare("SELECT ml.*, p.voornaam, p.achternaam FROM mail_log ml LEFT JOIN sollicitaties s ON s.id=ml.sollicitatie_id LEFT JOIN personen p ON p.id=s.persoon_id WHERE ml.id=?"); $q->execute([(int)$_GET['log']]); $log_detail = $q->fetch() ?: null; } catch (Exception $e) {}
    }
}
$type_namen = [];
if (function_exists('meldingen_register')) foreach (meldingen_register() as $k => $d) $type_namen[$k] = $d['naam'];
$kanaal_label = ['email' => 'e-mail', 'whatsapp' => 'WhatsApp', 'sms' => 'sms'];
$status_kleur = ['verstuurd' => 'var(--groen)', 'wacht' => '#f59e0b', 'bezig' => '#f59e0b', 'mislukt' => 'var(--rood)', 'overgeslagen' => 'var(--grijs)'];

require __DIR__ . '/layout_top.php';
?>
<style>
.ber-grid{display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start}
@media (max-width:1000px){.ber-grid{grid-template-columns:1fr}}
.og-header{display:flex;align-items:center;gap:8px;padding:7px 10px;background:var(--grijs-bg);border-radius:6px;cursor:pointer}
.ontv-rij{display:flex;align-items:center;gap:8px;padding:5px 6px;border-radius:5px;cursor:pointer;font-size:13px}
.ontv-rij:hover{background:var(--grijs-bg)}
.ontv-rij .sub{font-size:11px;color:var(--grijs)}
.kanaal-kiezer{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.kanaal-kiezer label{display:flex;flex-direction:column;gap:4px;padding:10px;border:2px solid var(--rand);border-radius:8px;cursor:pointer;font-size:12px}
.kanaal-kiezer input{position:absolute;opacity:0}
.kanaal-kiezer input:checked+label{border-color:var(--oranje);background:var(--oranje-bg);color:var(--navy)}
.var-chip{display:inline-block;background:#eff8ff;color:#1a56a0;font-size:11px;font-family:monospace;padding:2px 6px;border-radius:4px;margin:2px;cursor:pointer;border:1px solid #bee3f8}
.log-rij{display:grid;grid-template-columns:110px 150px 1fr 90px 80px;gap:10px;align-items:center;padding:8px 14px;border-bottom:1px solid var(--rand);font-size:12px}
.log-rij:hover{background:var(--grijs-bg)}
@media (max-width:800px){.log-rij{grid-template-columns:1fr 1fr}}
</style>

<div class="fase-tabs" style="margin-bottom:16px">
  <a href="?tab=nieuw" class="fase-tab <?= $tab === 'nieuw' ? 'actief' : '' ?>">Nieuw bericht</a>
  <a href="?tab=log" class="fase-tab <?= $tab === 'log' ? 'actief' : '' ?>">Berichtenlog</a>
</div>

<?php if ($tab === 'log'): ?>
<?php if ($log_detail): ?>
<div class="card" style="margin-bottom:14px;border-left:4px solid var(--navy)">
  <div class="card-header"><div class="card-title"><?= h($log_detail['onderwerp']) ?></div><a href="?tab=log" class="btn btn-outline btn-sm">Sluiten</a></div>
  <div class="card-body" style="font-size:13px">
    <div style="color:var(--tekst-zacht);margin-bottom:10px">
      <?= h($kanaal_label[$log_detail['kanaal']] ?? $log_detail['kanaal']) ?> · <?= h($log_detail['status']) ?><?= $log_detail['fallback_van'] ? ' (terugval van ' . h($kanaal_label[$log_detail['fallback_van']] ?? $log_detail['fallback_van']) . ')' : '' ?>
      · <?= h(trim(($log_detail['voornaam'] ?? '') . ' ' . ($log_detail['achternaam'] ?? '')) ?: $log_detail['ontvanger']) ?> (<?= h($log_detail['ontvanger_rol']) ?>)
      · <?= h($log_detail['verstuurd_op']) ?> · type <?= h($type_namen[$log_detail['meldingstype']] ?? $log_detail['meldingstype'] ?? '-') ?>
      <?= $log_detail['foutmelding'] ? '<br><span style="color:var(--rood)">' . h($log_detail['foutmelding']) . '</span>' : '' ?>
    </div>
    <?php if ($log_detail['body'] !== null && $log_detail['body'] !== ''): ?>
      <?php if ($log_detail['kanaal'] === 'email'): ?>
      <div style="border:1px solid var(--rand);border-radius:8px;padding:14px;background:#fff"><?= strip_tags((string)$log_detail['body'], '<p><br><strong><em><b><i><u><a><ul><ol><li><h2><h3><h4><table><tr><td><span><div><img><hr><blockquote>') ?></div>
      <?php else: ?>
      <div style="border:1px solid var(--rand);border-radius:8px;padding:14px;background:#fff;white-space:pre-wrap"><?= h((string)$log_detail['body']) ?></div>
      <?php endif; ?>
    <?php else: ?>
    <div style="color:var(--grijs)">Inhoud niet bewaard (systeembericht, of opgeschoond volgens de bewaartermijn).</div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Berichtenlog</div>
    <form method="get" style="display:flex;gap:6px;flex-wrap:wrap;margin:0">
      <input type="hidden" name="tab" value="log">
      <input type="text" name="q" value="<?= h($f_q) ?>" placeholder="Zoek naam/onderwerp" style="font-size:12px;padding:5px 8px;width:170px">
      <select name="kanaal" style="font-size:12px;padding:5px 8px"><option value="">Alle kanalen</option><?php foreach ($kanaal_label as $k => $l): ?><option value="<?= $k ?>" <?= $f_kanaal === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select>
      <select name="status" style="font-size:12px;padding:5px 8px"><option value="">Alle statussen</option><?php foreach (['verstuurd', 'wacht', 'mislukt', 'overgeslagen'] as $st): ?><option value="<?= $st ?>" <?= $f_status === $st ? 'selected' : '' ?>><?= $st ?></option><?php endforeach; ?></select>
      <select name="type" style="font-size:12px;padding:5px 8px;max-width:200px"><option value="">Alle typen</option><?php foreach ($type_namen as $k => $l): ?><option value="<?= h($k) ?>" <?= $f_type === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select>
      <button type="submit" class="btn btn-outline btn-sm">Filter</button>
    </form>
  </div>
  <?php if (!$log): ?><div class="card-body"><p style="color:var(--grijs)">Geen berichten gevonden.</p></div>
  <?php else: ?>
  <div>
    <?php foreach ($log as $ml): ?>
    <a class="log-rij" href="?tab=log&log=<?= (int)$ml['id'] ?>&kanaal=<?= h($f_kanaal) ?>&status=<?= h($f_status) ?>&type=<?= h($f_type) ?>&q=<?= h($f_q) ?>" style="text-decoration:none;color:inherit">
      <span style="color:var(--grijs)"><?= datum_nl(substr($ml['verstuurd_op'], 0, 10)) ?> <?= substr($ml['verstuurd_op'], 11, 5) ?></span>
      <span style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h(trim(($ml['voornaam'] ?? '') . ' ' . ($ml['achternaam'] ?? '')) ?: $ml['ontvanger']) ?><?= $ml['ontvanger_rol'] !== 'sollicitant' ? ' <span style="color:var(--grijs)">(' . h($ml['ontvanger_rol']) . ')</span>' : '' ?></span>
      <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--tekst-zacht)" title="<?= h($type_namen[$ml['meldingstype']] ?? $ml['meldingstype'] ?? '') ?>"><?= h($ml['onderwerp']) ?></span>
      <span><?= h($kanaal_label[$ml['kanaal']] ?? $ml['kanaal']) ?><?= $ml['fallback_van'] ? ' <span title="terugval van ' . h($ml['fallback_van']) . '" style="color:var(--oranje)">*</span>' : '' ?></span>
      <span style="color:<?= $status_kleur[$ml['status']] ?? 'inherit' ?>;font-weight:600" title="<?= h((string)$ml['foutmelding']) ?>"><?= h($ml['status']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <div style="padding:8px 14px;font-size:11px;color:var(--grijs)">Laatste 200. * = teruggevallen op dit kanaal nadat het eerste kanaal mislukte.</div>
  <?php endif; ?>
</div>

<?php else: ?>

<?php if ($fout): ?><div class="flash flash-fout"><?= h($fout) ?></div><?php endif; ?>

<?php if ($resultaat): ?>
<div class="card" style="margin-bottom:16px;border-left:4px solid <?= $resultaat['mislukt'] ? 'var(--oranje)' : 'var(--groen)' ?>">
  <div class="card-body">
    <div style="font-size:15px;font-weight:700;color:var(--navy);margin-bottom:8px">
      <?= $resultaat['via_queue'] ? ($resultaat['wacht'] + $resultaat['ok']) . ' in de wachtrij (wordt de komende minuten verstuurd)' : $resultaat['ok'] . ' verstuurd' ?><?= $resultaat['overgeslagen'] ? ' · ' . $resultaat['overgeslagen'] . ' overgeslagen' : '' ?><?= $resultaat['mislukt'] ? ' · ' . $resultaat['mislukt'] . ' mislukt' : '' ?>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:5px">
      <?php foreach ($resultaat['details'] as $d): ?>
      <div style="font-size:12px;padding:5px 8px;background:<?= in_array($d['status'], ['verstuurd', 'wacht'], true) ? '#e1f5ee' : ($d['status'] === 'overgeslagen' ? '#f3f4f6' : '#fff5f5') ?>;border-radius:5px">
        <strong><?= h($d['naam']) ?></strong> — <?= h($d['status']) ?><?= $d['kanaal'] ? ' via ' . h($kanaal_label[$d['kanaal']] ?? $d['kanaal']) : '' ?><?= $d['reden'] ? ' (' . h($d['reden']) . ')' : '' ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:12px;display:flex;gap:8px"><a href="?tab=nieuw" class="btn btn-outline btn-sm">Nieuw bericht</a><a href="?tab=log" class="btn btn-outline btn-sm">Berichtenlog</a></div>
  </div>
</div>
<?php endif; ?>

<?php if ($voorbeeld): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-header"><div class="card-title">Voorbeeld: <?= h($voorbeeld['onderwerp']) ?></div></div>
  <div class="card-body" style="display:grid;grid-template-columns:1fr 300px;gap:14px">
    <iframe srcdoc="<?= h($voorbeeld['html']) ?>" style="width:100%;height:520px;border:1px solid var(--rand);border-radius:8px;background:#fff"></iframe>
    <div><div style="font-size:11px;font-weight:600;color:var(--grijs);text-transform:uppercase;margin-bottom:6px">Kort bericht</div>
      <div style="background:#e5ddd5;padding:14px;border-radius:10px"><div style="background:#fff;border-radius:10px;padding:10px 12px;font-size:14px;white-space:pre-wrap"><?= h($voorbeeld['tekst_kort'] ?: '(geen korte tekst)') ?></div></div></div>
  </div>
</div>
<?php endif; ?>

<form method="POST" id="berichtForm" enctype="multipart/form-data">
  <?= csrf_input() ?>
  <input type="hidden" name="actie" id="actieVeld" value="verzend">
  <div class="ber-grid">
    <div>
      <div class="card" style="margin-bottom:14px">
        <div class="card-header"><div class="card-title">Kanaal</div></div>
        <div class="card-body">
          <div class="kanaal-kiezer">
            <?php $gekozen = $_POST['kanaalmodus'] ?? 'email'; foreach ($modi as $mk => $ml): ?>
            <div style="position:relative"><input type="radio" name="kanaalmodus" id="km_<?= $mk ?>" value="<?= $mk ?>" <?= $gekozen === $mk ? 'checked' : '' ?>><label for="km_<?= $mk ?>"><strong><?= h($ml) ?></strong><span style="color:var(--tekst-zacht)"><?= $mk === 'email' ? 'Volledige opmaak' : ($mk === 'kort' ? 'Korte tekst; e-mail als terugval' : 'E-mail én korte tekst') ?></span></label></div>
            <?php endforeach; ?>
          </div>
          <div style="font-size:11px;color:var(--tekst-zacht);margin-top:8px">WhatsApp of SMS volgt de voorkeur van de sollicitant (Mijn gegevens). Zonder telefoonnummer of bij een mislukking gaat het bericht per e-mail. Afgemelde personen worden overgeslagen; elk bericht krijgt een afmeldvoet.</div>
        </div>
      </div>

      <div class="card" style="margin-bottom:14px">
        <div class="card-header"><div class="card-title">Inhoud</div>
          <?php if ($sjablonen): ?>
          <select name="sjabloon" id="sjabloonSel" style="font-size:12px;padding:5px 8px;max-width:260px" onchange="sjabloonWissel()">
            <option value="">Zelf schrijven</option>
            <?php foreach ($sjablonen as $t): ?><option value="<?= h($t['slug']) ?>" <?= ($_POST['sjabloon'] ?? '') === $t['slug'] ? 'selected' : '' ?>><?= h($t['naam']) ?></option><?php endforeach; ?>
          </select>
          <?php endif; ?>
        </div>
        <div class="card-body" id="eigenInhoud">
          <div class="form-groep"><label>Onderwerp (e-mail)</label><input type="text" name="onderwerp" value="<?= h($_POST['onderwerp'] ?? '') ?>" placeholder="Bijv. Informatie over het aankomende seizoen"></div>
          <div class="form-groep"><label>Hero-titel <span style="font-weight:400;color:var(--grijs)">(optioneel, grote tekst bovenin)</span></label><input type="text" name="hero_titel" value="<?= h($_POST['hero_titel'] ?? '') ?>"></div>
          <div class="form-groep"><label>Bericht (e-mail)</label><?= editor_html('bericht', 'body_html', (string)($_POST['body_html'] ?? "<p>Hoi {{voornaam}},</p>\n<p></p>"), ['hoogte' => 280]) ?></div>
          <div class="form-groep"><label>Korte tekst (WhatsApp/SMS) <span id="kortTeller" style="font-weight:400;color:var(--grijs)"></span></label>
            <textarea name="tekst_kort" id="tekstKort" rows="4" maxlength="400" oninput="kortTeller()" placeholder="Hoi {{voornaam}}, ... Meer info: {{portaal_url}}"><?= h($_POST['tekst_kort'] ?? '') ?></textarea>
            <div class="form-hulp">Alleen nodig bij WhatsApp/SMS. Platte tekst, max 400 tekens.</div></div>
          <div class="form-groep"><label>Bijlagen <span style="font-weight:400;color:var(--grijs)">(optioneel)</span></label>
            <input type="file" name="bijlagen[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx">
            <div class="form-hulp">Gaan als bijlage mee in de e-mail; WhatsApp/SMS krijgt een downloadlink (14 dagen geldig). Bij "WhatsApp/SMS" wordt dan ook een e-mail gestuurd. Max 10 MB per bestand.</div></div>
        </div>
        <div class="card-body" id="sjabloonInfo" style="display:none;font-size:13px;color:var(--tekst-zacht)">De inhoud komt uit het gekozen sjabloon. Bewerken: <a href="<?= APP_URL ?>/admin/mail_templates.php" style="color:var(--oranje)">Sjablonen</a>.</div>
      </div>

      <div class="card">
        <div class="card-body" style="padding:14px 16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <button type="submit" class="btn btn-primary" id="verzendKnop" onclick="return verzendBevestig()" <?= $mag_bewerken ? '' : 'disabled' ?>>Verzenden</button>
          <button type="submit" class="btn btn-outline" onclick="document.getElementById('actieVeld').value='voorbeeld'">Voorbeeld</button>
          <button type="submit" class="btn btn-outline" onclick="document.getElementById('actieVeld').value='test'" <?= $mag_bewerken ? '' : 'disabled' ?>>Testmail naar mijzelf</button>
          <div id="verzendTeller" style="font-size:13px;font-weight:600;color:var(--navy)">0 ontvangers</div>
        </div>
      </div>
    </div>

    <div>
      <div class="card" style="margin-bottom:14px">
        <div class="card-header"><div class="card-title">Ontvangers</div>
          <div style="display:flex;gap:5px"><button type="button" class="btn btn-outline btn-sm" onclick="selAlles(true)">Alles</button><button type="button" class="btn btn-outline btn-sm" onclick="selAlles(false)">Geen</button></div></div>
        <div style="max-height:480px;overflow-y:auto;padding:10px 14px">
          <?php if (!$alle): ?><p style="font-size:13px;color:var(--grijs)">Geen sollicitanten in dit seizoen.</p>
          <?php else: foreach (array_keys($fases) as $f): if (empty($per_fase[$f])) continue; ?>
          <div style="margin-bottom:10px">
            <div class="og-header">
              <input type="checkbox" class="groep-cb" onclick="selGroep('<?= $f ?>',this.checked)" style="width:auto">
              <span class="badge badge-<?= fase_kleur($f) ?>" style="font-size:11px"><?= fase_label($f) ?></span>
              <span style="font-size:11px;color:var(--grijs);margin-left:auto"><?= count($per_fase[$f]) ?></span>
            </div>
            <?php foreach ($per_fase[$f] as $s): ?>
            <label class="ontv-rij">
              <input type="checkbox" name="ontvangers[]" value="<?= (int)$s['id'] ?>" class="ontv-cb fase-<?= $f ?>" onchange="updateTeller()" <?= in_array((int)$s['id'], $voorsel, true) ? 'checked' : '' ?>>
              <div style="min-width:0"><div style="font-weight:500"><?= h(volledige_naam($s)) ?><?= $s['afgemeld'] ? ' <span class="badge" style="font-size:10px">afgemeld</span>' : '' ?></div>
                <div class="sub"><?= h($s['email']) ?><?= $s['telefoon'] ? ' · ' . h($s['kanaal_voorkeur'] === 'sms' ? 'sms' : ($s['kanaal_voorkeur'] === 'email' ? 'alleen e-mail' : 'WhatsApp')) : ' · geen telefoon' ?></div></div>
            </label>
            <?php endforeach; ?>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Variabelen</div></div>
        <div class="card-body" style="padding:10px 14px">
          <?php foreach (['voornaam', 'achternaam', 'volledige_naam', 'seizoen_naam', 'selectiedag_datum', 'selectiedag_locatie', 'tw_datum_start', 'tw_locatie', 'portaal_url', 'bestuur_email'] as $v): ?>
          <span class="var-chip" onclick="P13Editor.insert('bericht','{{<?= $v ?>}}')">{{<?= $v ?>}}</span>
          <?php endforeach; ?>
          <div style="font-size:11px;color:var(--tekst-zacht);margin-top:8px">Werkt ook in het onderwerp en de korte tekst (typen).</div>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
function updateTeller(){var n=document.querySelectorAll('.ontv-cb:checked').length;document.getElementById('verzendTeller').textContent=n+' ontvanger'+(n!==1?'s':'');}
function selGroep(f,aan){document.querySelectorAll('.fase-'+f).forEach(function(c){c.checked=aan;});updateTeller();}
function selAlles(aan){document.querySelectorAll('.ontv-cb,.groep-cb').forEach(function(c){c.checked=aan;});updateTeller();}
function kortTeller(){var t=document.getElementById('tekstKort');var e=document.getElementById('kortTeller');if(t&&e)e.textContent='('+t.value.length+'/400)';}
function sjabloonWissel(){var s=document.getElementById('sjabloonSel');var eigen=!s||!s.value;document.getElementById('eigenInhoud').style.display=eigen?'':'none';document.getElementById('sjabloonInfo').style.display=eigen?'none':'';}
function verzendBevestig(){document.getElementById('actieVeld').value='verzend';var n=document.querySelectorAll('.ontv-cb:checked').length;if(!n){alert('Selecteer minimaal één ontvanger.');return false;}var k=document.querySelector('input[name=kanaalmodus]:checked');return confirm('Bericht verzenden naar '+n+' persoon'+(n!==1?'en':'')+' via '+(k?k.parentNode.querySelector('strong').textContent:'e-mail')+'?');}
updateTeller();kortTeller();sjabloonWissel();
</script>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

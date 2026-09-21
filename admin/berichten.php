<?php
/**
 * Beheer — Berichten: handmatig een bericht sturen (individueel, geselecteerd, per fase) via
 * e-mail en/of WhatsApp/SMS, plus het berichtenlog van alle kanalen.
 * Alles loopt via de centrale meldingen-kern (melding_verstuur, type 'handmatig').
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
require_once __DIR__ . '/_editor.php';
$pagina_titel = 'Berichten';
$actieve_nav  = 'berichten';
vereis_recht('mailing', 'lezen');

$db      = db();
$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;
$tab     = (string)($_GET['tab'] ?? 'nieuw');
if (!in_array($tab, ['nieuw', 'log'], true)) $tab = 'nieuw';
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
function berichten_inhoud(array $post, $db): array {
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
            if (function_exists('audit_log')) audit_log('bericht_verstuurd', 'sollicitatie', null, ['aantal' => count($ids), 'kanaal' => $modus, 'onderwerp' => substr($inhoud['onderwerp'], 0, 80)]);
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
        $q = $db->prepare("SELECT ml.id, ml.sollicitatie_id, ml.kanaal, ml.meldingstype, ml.categorie, ml.ontvanger_rol, ml.ontvanger, ml.onderwerp, ml.status, ml.foutmelding, ml.fallback_van, ml.verstuurd_op, p.voornaam, p.tussenvoegsel, p.achternaam
                           FROM mail_log ml LEFT JOIN sollicitaties s ON s.id=ml.sollicitatie_id LEFT JOIN personen p ON p.id=s.persoon_id
                           " . ($waar ? 'WHERE ' . implode(' AND ', $waar) : '') . " ORDER BY ml.id DESC LIMIT 200");
        $q->execute($p);
        $log = $q->fetchAll();
    } catch (Exception $e) { $log = []; }
    if (!empty($_GET['log'])) {
        try { $q = $db->prepare("SELECT ml.*, p.voornaam, p.tussenvoegsel, p.achternaam FROM mail_log ml LEFT JOIN sollicitaties s ON s.id=ml.sollicitatie_id LEFT JOIN personen p ON p.id=s.persoon_id WHERE ml.id=?"); $q->execute([(int)$_GET['log']]); $log_detail = $q->fetch() ?: null; } catch (Exception $e) {}
    }
}
$type_namen = [];
if (function_exists('meldingen_register')) foreach (meldingen_register() as $k => $d) $type_namen[$k] = $d['naam'];

require __DIR__ . '/layout_top.php';
?>

<div class="bh-tabs" role="tablist" aria-label="Berichten">
  <a class="bh-tab<?= $tab === 'nieuw' ? ' actief' : '' ?>" href="?tab=nieuw" role="tab"
     aria-selected="<?= $tab === 'nieuw' ? 'true' : 'false' ?>">Nieuw bericht</a>
  <a class="bh-tab<?= $tab === 'log' ? ' actief' : '' ?>" href="?tab=log" role="tab"
     aria-selected="<?= $tab === 'log' ? 'true' : 'false' ?>">Verzonden</a>
</div>

<?php if ($tab === 'log'): ?>

<?php if ($log_detail): $_d = bh_bericht_stand($log_detail); ?>
<section class="ds-kaart bh-logdetail">
  <header class="bh-logdetail-kop">
    <h2><?= h((string)$log_detail['onderwerp']) ?></h2>
    <a class="ds-btn ds-btn-zacht ds-btn-sm" href="?tab=log">Sluiten</a>
  </header>
  <p class="bh-logdetail-meta">
    <?= bh_badge($_d['woord'], $_d['kleur']) ?>
    <?= h(bh_bericht_kanaal($log_detail)) ?>
    · aan <?= h(volledige_naam($log_detail) ?: (string)$log_detail['ontvanger']) ?>
    (<?= h((string)$log_detail['ontvanger_rol']) ?>)
    · <?= h(datum_nl(substr((string)$log_detail['verstuurd_op'], 0, 10))) ?>
      <?= h(substr((string)$log_detail['verstuurd_op'], 11, 5)) ?>
    · <?= h($type_namen[$log_detail['meldingstype']] ?? (string)($log_detail['meldingstype'] ?? '—')) ?>
  </p>
  <?php if ($_d['reden'] !== ''): ?>
    <p class="bh-logdetail-reden"><?= h($_d['reden']) ?></p>
  <?php endif; ?>

  <?php if (($log_detail['body'] ?? '') !== null && ($log_detail['body'] ?? '') !== ''): ?>
    <?php if ($log_detail['kanaal'] === 'email'): ?>
      <div class="bh-logbody"><?= strip_tags((string)$log_detail['body'], '<p><br><strong><em><b><i><u><a><ul><ol><li><h2><h3><h4><table><tr><td><span><div><img><hr><blockquote>') ?></div>
    <?php else: ?>
      <div class="bh-logbody kort"><?= h((string)$log_detail['body']) ?></div>
    <?php endif; ?>
  <?php else: ?>
    <p class="bh-meta">Inhoud niet bewaard — dit was een systeembericht, of de bewaartermijn is verstreken.</p>
  <?php endif; ?>
</section>
<?php endif; ?>

<form class="bh-filterbalk" method="get">
  <input type="hidden" name="tab" value="log">
  <label class="sr-only" for="fq">Zoek op naam of onderwerp</label>
  <input class="ds-input bh-veld-smal" type="search" id="fq" name="q" value="<?= h($f_q) ?>" placeholder="Zoek naam of onderwerp">
  <label class="sr-only" for="fkanaal">Kanaal</label>
  <select class="ds-select bh-veld-smal" id="fkanaal" name="kanaal">
    <option value="">Alle kanalen</option>
    <?php foreach (['email', 'whatsapp', 'sms'] as $k): ?>
      <option value="<?= $k ?>" <?= $f_kanaal === $k ? 'selected' : '' ?>><?= h(bh_kanaal_naam($k)) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="sr-only" for="fstatus">Status</label>
  <select class="ds-select bh-veld-smal" id="fstatus" name="status">
    <option value="">Alle standen</option>
    <?php foreach (['verstuurd' => 'Verstuurd', 'wacht' => 'In de wachtrij', 'mislukt' => 'Mislukt', 'overgeslagen' => 'Overgeslagen'] as $k => $l): ?>
      <option value="<?= $k ?>" <?= $f_status === $k ? 'selected' : '' ?>><?= h($l) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="sr-only" for="ftype">Soort bericht</label>
  <select class="ds-select bh-veld-smal" id="ftype" name="type">
    <option value="">Alle soorten</option>
    <?php foreach ($type_namen as $k => $l): ?>
      <option value="<?= h($k) ?>" <?= $f_type === $k ? 'selected' : '' ?>><?= h($l) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Filteren</button>
</form>

<?php if (!$log): ?>
  <?php
  $leeg_titel = 'Geen berichten gevonden';
  $leeg_tekst = ($f_q !== '' || $f_kanaal || $f_status || $f_type)
      ? 'Met deze filters staat er niets in het log. Ruim het filter op om alles te zien.'
      : 'Er is dit seizoen nog niets verstuurd.';
  $leeg_icoon = 'mail';
  require __DIR__ . '/_leeg.php';
  ?>
<?php else: ?>
<section class="ds-kaart bh-loglijst">
  <header class="bh-loglijst-kop">
    <h2>Verzonden</h2>
    <span class="bh-meta">de reden staat er altijd in woorden bij</span>
  </header>
  <table class="bh-rastertabel bh-logtabel" style="--bh-tracks:112px minmax(150px,1.3fr) minmax(0,1.6fr) 130px 130px minmax(0,1.7fr)">
    <thead>
      <tr>
        <th scope="col">Wanneer</th>
        <th scope="col">Ontvanger</th>
        <th scope="col">Onderwerp</th>
        <th scope="col">Kanaal</th>
        <th scope="col">Stand</th>
        <th scope="col">Reden</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($log as $ml):
        $st    = bh_bericht_stand($ml);
        $actie = bh_bericht_actie($ml);
        $naam  = volledige_naam($ml) ?: (string)$ml['ontvanger'];
        $url   = '?tab=log&log=' . (int)$ml['id'] . '&kanaal=' . urlencode($f_kanaal)
               . '&status=' . urlencode($f_status) . '&type=' . urlencode($f_type) . '&q=' . urlencode($f_q);
      ?>
      <tr>
        <td class="bh-logcel bh-log-wanneer">
          <a href="<?= h($url) ?>">
            <?= h(datum_kort_nl(substr((string)$ml['verstuurd_op'], 0, 10))) ?>
            <?= h(substr((string)$ml['verstuurd_op'], 11, 5)) ?>
          </a>
        </td>
        <td class="bh-logcel bh-log-naam">
          <a href="<?= h($url) ?>"><?= h($naam) ?></a>
          <?php if (($ml['ontvanger_rol'] ?? 'sollicitant') !== 'sollicitant'): ?>
            <span class="bh-meta">(<?= h((string)$ml['ontvanger_rol']) ?>)</span>
          <?php endif; ?>
        </td>
        <td class="bh-logcel bh-log-onderwerp">
          <a href="<?= h($url) ?>" title="<?= h($type_namen[$ml['meldingstype']] ?? (string)($ml['meldingstype'] ?? '')) ?>"><?= h((string)$ml['onderwerp']) ?></a>
        </td>
        <td class="bh-logcel bh-log-kanaal"><?= h(bh_bericht_kanaal($ml)) ?></td>
        <td class="bh-logcel bh-log-stand ds-fase-<?= h($st['kleur']) ?>">
          <?= icoon($st['icoon'], 13) ?><?= h($st['woord']) ?>
        </td>
        <td class="bh-logcel bh-log-reden">
          <?= $st['reden'] !== '' ? h($st['reden']) : '<span class="bh-meta">—</span>' ?>
          <?php if ($actie): ?>
            <a class="bh-log-actie" href="<?= h($actie['url']) ?>"><?= h($actie['tekst']) ?></a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="bh-loglijst-voet">
    De laatste 200 berichten. Een pijl bij het kanaal betekent dat het eerste kanaal niet lukte
    en het bericht via het tweede alsnog is aangekomen.
  </p>
</section>
<?php endif; ?>

<?php else: ?>

<?php if ($fout): ?>
  <div class="ds-melding ds-melding-error bh-blokkade"><?= icoon('waarschuwing', 18) ?><span><?= h($fout) ?></span></div>
<?php endif; ?>

<?php if ($resultaat): $_uitslag = bh_verzend_zin($resultaat); ?>
<section class="ds-kaart bh-uitslag">
  <header class="bh-uitslag-kop">
    <?= bh_stoplicht($_uitslag[0], $_uitslag[1]) ?>
    <span class="bh-uitslag-knoppen">
      <a class="ds-btn ds-btn-zacht ds-btn-sm" href="?tab=nieuw">Nieuw bericht</a>
      <a class="ds-btn ds-btn-secundair ds-btn-sm" href="?tab=log">Naar het log</a>
    </span>
  </header>
  <?php if (!empty($resultaat['via_queue'])): ?>
    <p class="bh-uitslag-uitleg">
      Meer dan tien ontvangers, dus de berichten gaan via de wachtrij de deur uit. Dat duurt een
      paar minuten; je hoeft niets te doen. In het log zie je per persoon wanneer het gelukt is.
    </p>
  <?php endif; ?>
  <ul class="bh-uitslaglijst">
    <?php foreach ($resultaat['details'] as $d):
      $_st = bh_bericht_stand(['status' => $d['status'], 'kanaal' => $d['kanaal'], 'foutmelding' => $d['reden']]);
    ?>
    <li class="ds-fase-<?= h($_st['kleur']) ?>">
      <span class="bh-uitslag-naam"><?= h((string)$d['naam']) ?></span>
      <span class="bh-uitslag-stand"><?= icoon($_st['icoon'], 13) ?><?= h($_st['woord']) ?></span>
      <span class="bh-uitslag-reden">
        <?= $d['kanaal'] ? h(bh_kanaal_naam((string)$d['kanaal'])) : '' ?>
        <?= $_st['reden'] !== '' ? ' · ' . h($_st['reden']) : '' ?>
      </span>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($voorbeeld): ?>
<section class="ds-kaart bh-voorbeeld">
  <header class="bh-voorbeeld-kop">
    <h2>Voorbeeld</h2>
    <span class="bh-meta"><?= h((string)$voorbeeld['onderwerp']) ?></span>
  </header>
  <div class="bh-voorbeeld-kolommen">
    <iframe class="bh-voorbeeld-mail" title="Voorbeeld van de e-mail" srcdoc="<?= h((string)$voorbeeld['html']) ?>"></iframe>
    <div class="bh-voorbeeld-kort">
      <h3>Korte tekst</h3>
      <div class="bh-voorbeeld-bubbel"><?= h($voorbeeld['tekst_kort'] ?: '(geen korte tekst)') ?></div>
    </div>
  </div>
</section>
<?php endif; ?>

<form method="POST" id="berichtForm" enctype="multipart/form-data" class="bh-berichtform">
  <?= csrf_input() ?>
  <input type="hidden" name="actie" id="actieVeld" value="verzend">

  <div class="bh-bericht-kolommen">
    <div class="bh-bericht-hoofd">

      <section class="ds-kaart ds-kaart-p">
        <div class="bh-kaartkop"><h2>Kanaal</h2></div>
        <div class="bh-kanaalkiezer">
          <?php $gekozen = $_POST['kanaalmodus'] ?? 'email'; foreach ($modi as $mk => $ml): ?>
          <div class="bh-kanaalkeuze">
            <input type="radio" name="kanaalmodus" id="km_<?= h($mk) ?>" value="<?= h($mk) ?>" <?= $gekozen === $mk ? 'checked' : '' ?>>
            <label for="km_<?= h($mk) ?>">
              <strong><?= h($ml) ?></strong>
              <span><?= $mk === 'email' ? 'Volledige opmaak' : ($mk === 'kort' ? 'Korte tekst, e-mail als terugval' : 'E-mail én korte tekst') ?></span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="ds-veld-hint">
          WhatsApp of sms volgt de voorkeur die de sollicitant zelf heeft ingesteld. Zonder
          telefoonnummer, of als het niet lukt, gaat het bericht per e-mail. Wie zich heeft
          afgemeld wordt overgeslagen; elk bericht krijgt een afmeldvoet.
        </p>
      </section>

      <section class="ds-kaart ds-kaart-p">
        <div class="bh-kaartkop">
          <h2>Inhoud</h2>
          <?php if ($sjablonen): ?>
          <select class="ds-select bh-veld-smal bh-kaartkop-veld" name="sjabloon" id="sjabloonSel" onchange="sjabloonWissel()"
                  aria-label="Sjabloon kiezen">
            <option value="">Zelf schrijven</option>
            <?php foreach ($sjablonen as $t): ?>
              <option value="<?= h($t['slug']) ?>" <?= ($_POST['sjabloon'] ?? '') === $t['slug'] ? 'selected' : '' ?>><?= h($t['naam']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php endif; ?>
        </div>

        <div id="eigenInhoud">
          <div class="ds-veld">
            <label class="ds-label" for="ber_onderwerp">Onderwerp (e-mail)</label>
            <input class="ds-input" type="text" id="ber_onderwerp" name="onderwerp"
                   value="<?= h($_POST['onderwerp'] ?? '') ?>" placeholder="Bijv. Informatie over het aankomende seizoen">
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="ber_hero">Hero-titel <span>optioneel, de grote tekst bovenin</span></label>
            <input class="ds-input" type="text" id="ber_hero" name="hero_titel" value="<?= h($_POST['hero_titel'] ?? '') ?>">
          </div>
          <div class="ds-veld">
            <label class="ds-label">Bericht (e-mail)</label>
            <?= editor_html('bericht', 'body_html', (string)($_POST['body_html'] ?? "<p>Hoi {{voornaam}},</p>\n<p></p>"), ['hoogte' => 280]) ?>
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="tekstKort">Korte tekst (WhatsApp/sms) <span id="kortTeller"></span></label>
            <textarea class="ds-textarea" name="tekst_kort" id="tekstKort" rows="4" maxlength="400" oninput="kortTeller()"
                      placeholder="Hoi {{voornaam}}, … Meer info: {{portaal_url}}"><?= h($_POST['tekst_kort'] ?? '') ?></textarea>
            <p class="ds-veld-hint">Alleen nodig bij WhatsApp of sms. Platte tekst, maximaal 400 tekens.</p>
          </div>
          <div class="ds-veld">
            <label class="ds-label" for="ber_bijlagen">Bijlagen <span>optioneel</span></label>
            <input class="ds-input bh-veld-bestand" type="file" id="ber_bijlagen" name="bijlagen[]" multiple
                   accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx">
            <p class="ds-veld-hint">
              Gaan mee in de e-mail; boven <?= function_exists('bijlagen_mail_max_bytes') ? round(bijlagen_mail_max_bytes() / 1048576) : 15 ?> MB
              wordt het een downloadknop. WhatsApp en sms krijgen een downloadlink die veertien dagen
              geldig is, en dan gaat er ook een e-mail uit. Maximaal
              <?= function_exists('bijlagen_max_bytes') ? round(bijlagen_max_bytes() / 1048576) : 25 ?> MB per bestand<?= function_exists('bijlagen_max_bytes') && bijlagen_max_bytes() < 25 * 1048576 ? ' — de server staat op dit moment niet meer toe' : '' ?>.
            </p>
          </div>
        </div>

        <p id="sjabloonInfo" class="ds-veld-hint" hidden>
          De inhoud komt uit het gekozen sjabloon.
          <a href="<?= APP_URL ?>/admin/mail_templates.php">Sjabloon bewerken</a>
        </p>
      </section>

      <section class="ds-kaart bh-verzendbalk">
        <button type="submit" class="ds-btn ds-btn-primair" id="verzendKnop"
                onclick="return verzendBevestig(event)" <?= $mag_bewerken ? '' : 'disabled' ?>>
          Versturen naar <span id="verzendTeller">0</span>
        </button>
        <button type="submit" class="ds-btn ds-btn-secundair"
                onclick="document.getElementById('actieVeld').value='voorbeeld'">Voorbeeld bekijken</button>
        <button type="submit" class="ds-btn ds-btn-zacht"
                onclick="document.getElementById('actieVeld').value='test'" <?= $mag_bewerken ? '' : 'disabled' ?>>
          Testmail naar mijzelf
        </button>
        <?php if (!$mag_bewerken): ?>
          <span class="bh-meta">Je mag berichten lezen, niet versturen.</span>
        <?php endif; ?>
      </section>
    </div>

    <aside class="bh-bericht-zij">
      <section class="ds-kaart ds-kaart-p">
        <div class="bh-kaartkop">
          <h2>Aan</h2>
          <span class="bh-kaartkop-knoppen">
            <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" onclick="selAlles(true)">Alles</button>
            <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" onclick="selAlles(false)">Geen</button>
          </span>
        </div>

        <?php /* Het zoekveld met chips. Staat verborgen tot de JS het aanzet: zonder
                 JavaScript blijft de lijst eronder het werkende formulier. De vinkjes
                 in die lijst blijven in beide gevallen de bron van waarheid — de chips
                 zijn er een weergave van, zodat er maar één stand bestaat. */ ?>
        <div class="bh-aanveld" id="aanVeld" hidden>
          <div class="bh-aanveld-vak" id="aanVak" onclick="aanFocus(event)">
            <input type="text" id="aanZoek" class="bh-aanveld-zoek" placeholder="naam typen…"
                   autocomplete="off" role="combobox" aria-expanded="false"
                   aria-controls="aanLijst" aria-autocomplete="list"
                   aria-label="Zoek een ontvanger op naam of e-mailadres"
                   oninput="aanZoeken()" onkeydown="aanToets(event)" onfocus="aanZoeken()">
          </div>
          <ul class="bh-aanveld-lijst" id="aanLijst" role="listbox" aria-label="Gevonden mensen" hidden></ul>
          <p class="bh-aanveld-leeg" id="aanLeeg" hidden>Niemand gevonden met die naam.</p>
          <?php if ($alle): ?>
          <div class="bh-aanveld-snel">
            <?php foreach (array_keys($fases) as $f): if (empty($per_fase[$f])) continue; ?>
              <button type="button" class="bh-aanchip-snel" onclick="selGroep('<?= h($f) ?>', true)">
                + <?= h(fase_label_kort($f)) ?> (<?= count($per_fase[$f]) ?>)
              </button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <details class="bh-ontvlijst" id="aanAlles" open>
          <summary class="bh-ontvlijst-kop">Of kies uit de volledige lijst</summary>
        <div class="bh-ontvangers">
          <?php if (!$alle): ?>
            <p class="bh-meta">Geen sollicitanten in dit seizoen.</p>
          <?php else: foreach (array_keys($fases) as $f): if (empty($per_fase[$f])) continue; ?>
          <div class="bh-ontvgroep">
            <label class="bh-ontvgroep-kop">
              <input type="checkbox" class="groep-cb" onclick="selGroep('<?= h($f) ?>',this.checked)">
              <?= bh_fase_badge($f, ['lengte' => 'kort']) ?>
              <span class="bh-ontvgroep-aantal"><?= count($per_fase[$f]) ?></span>
            </label>
            <?php foreach ($per_fase[$f] as $s): ?>
            <label class="bh-ontvrij">
              <input type="checkbox" name="ontvangers[]" value="<?= (int)$s['id'] ?>"
                     class="ontv-cb fase-<?= h($f) ?>" onchange="updateTeller()"
                     data-naam="<?= h(volledige_naam($s)) ?>"
                     data-zoek="<?= h(mb_strtolower(volledige_naam($s) . ' ' . (string)$s['email'])) ?>"
                     data-fase="<?= h(fase_label_kort($f)) ?>"
                     <?= in_array((int)$s['id'], $voorsel, true) ? 'checked' : '' ?>>
              <span class="bh-ontvrij-tekst">
                <span class="bh-ontvrij-naam">
                  <?= h(volledige_naam($s)) ?>
                  <?php if (!empty($s['afgemeld'])): ?><?= bh_chip('afgemeld', 'rood') ?><?php endif; ?>
                </span>
                <span class="bh-ontvrij-sub">
                  <?= h((string)$s['email']) ?>
                  <?php if (!empty($s['telefoon'])): ?>
                    · <?= h($s['kanaal_voorkeur'] === 'sms' ? 'sms' : ($s['kanaal_voorkeur'] === 'email' ? 'alleen e-mail' : 'WhatsApp')) ?>
                  <?php else: ?>
                    · geen telefoon
                  <?php endif; ?>
                </span>
              </span>
            </label>
            <?php endforeach; ?>
          </div>
          <?php endforeach; endif; ?>
        </div>
        </details>
      </section>

      <section class="ds-kaart ds-kaart-p">
        <div class="bh-kaartkop"><h2>Variabelen</h2></div>
        <div class="bh-varchips">
          <?php foreach (['voornaam', 'achternaam', 'volledige_naam', 'seizoen_naam', 'selectiedag_datum', 'selectiedag_locatie', 'tw_datum_start', 'tw_locatie', 'portaal_url', 'bestuur_email'] as $v): ?>
          <button type="button" class="bh-varchip" onclick="P13Editor.insert('bericht','{{<?= h($v) ?>}}')">{{<?= h($v) ?>}}</button>
          <?php endforeach; ?>
        </div>
        <p class="ds-veld-hint">Werken ook in het onderwerp en de korte tekst — daar typ je ze zelf.</p>
      </section>
    </aside>
  </div>
</form>

<script>
function berAantal() { return document.querySelectorAll('.ontv-cb:checked').length; }

function updateTeller() {
  var n = berAantal();
  var el = document.getElementById('verzendTeller');
  if (el) el.textContent = n === 0 ? 'niemand' : (n + ' ' + (n === 1 ? 'persoon' : 'personen'));
  aanTeken();
}
function selGroep(f, aan) {
  document.querySelectorAll('.fase-' + f).forEach(function (c) { c.checked = aan; });
  updateTeller();
}
function selAlles(aan) {
  document.querySelectorAll('.ontv-cb,.groep-cb').forEach(function (c) { c.checked = aan; });
  updateTeller();
}

/* ── Het "Aan"-veld ───────────────────────────────────────────────────────────
   De vinkjes in de lijst zijn de stand; dit tekent die stand als chips en laat
   je erin zoeken. Er is dus niets dat uit de pas kan lopen: elke handeling hier
   zet een checkbox om en vraagt daarna opnieuw te tekenen. Zonder JavaScript
   draait dit blok niet en blijft de lijst gewoon een lijst. */
var aanMarkeer = -1;                        // welke suggestie met de pijltjes is aangewezen

function aanAan() {
  var veld = document.getElementById('aanVeld');
  if (!veld || !document.querySelector('.ontv-cb')) return;
  veld.hidden = false;
  document.getElementById('aanAlles').open = false;
  aanTeken();
}

function aanTeken() {
  var vak = document.getElementById('aanVak');
  if (!vak || document.getElementById('aanVeld').hidden) return;
  vak.querySelectorAll('.bh-aanchip').forEach(function (c) { c.remove(); });
  var zoek = document.getElementById('aanZoek');
  document.querySelectorAll('.ontv-cb:checked').forEach(function (cb) {
    var chip = document.createElement('span');
    chip.className = 'bh-aanchip';
    chip.textContent = cb.dataset.naam || '';
    var weg = document.createElement('button');
    weg.type = 'button';
    weg.className = 'bh-aanchip-weg';
    weg.setAttribute('aria-label', 'Haal ' + (cb.dataset.naam || 'deze persoon') + ' weg');
    weg.textContent = '×';
    weg.onclick = function (e) { e.stopPropagation(); cb.checked = false; updateTeller(); };
    chip.appendChild(weg);
    vak.insertBefore(chip, zoek);
  });
  zoek.placeholder = vak.querySelector('.bh-aanchip') ? '' : 'naam typen…';
}

function aanFocus(e) {
  if (e.target.closest('.bh-aanchip')) return;
  document.getElementById('aanZoek').focus();
}

function aanZoeken() {
  var zoek = document.getElementById('aanZoek');
  var lijst = document.getElementById('aanLijst');
  var leeg = document.getElementById('aanLeeg');
  var term = zoek.value.trim().toLowerCase();
  lijst.innerHTML = '';
  aanMarkeer = -1;

  if (!term) { aanSluit(); return; }

  var treffers = [];
  document.querySelectorAll('.ontv-cb').forEach(function (cb) {
    if (cb.checked || treffers.length >= 8) return;
    if ((cb.dataset.zoek || '').indexOf(term) !== -1) treffers.push(cb);
  });

  treffers.forEach(function (cb, i) {
    var li = document.createElement('li');
    li.className = 'bh-aansug';
    li.id = 'aansug-' + i;
    li.setAttribute('role', 'option');
    li.setAttribute('aria-selected', 'false');
    li.tabIndex = -1;
    var naam = document.createElement('span');
    naam.className = 'bh-aansug-naam';
    naam.textContent = cb.dataset.naam || '';
    var fase = document.createElement('span');
    fase.className = 'bh-aansug-fase';
    fase.textContent = cb.dataset.fase || '';
    li.appendChild(naam); li.appendChild(fase);
    li.onclick = function () { aanKies(cb); };
    lijst.appendChild(li);
  });

  lijst.hidden = treffers.length === 0;
  leeg.hidden = treffers.length !== 0;
  zoek.setAttribute('aria-expanded', treffers.length ? 'true' : 'false');
}

function aanKies(cb) {
  cb.checked = true;
  var zoek = document.getElementById('aanZoek');
  zoek.value = '';
  aanSluit();
  updateTeller();
  zoek.focus();
}

function aanSluit() {
  var lijst = document.getElementById('aanLijst');
  lijst.hidden = true; lijst.innerHTML = '';
  document.getElementById('aanLeeg').hidden = true;
  document.getElementById('aanZoek').setAttribute('aria-expanded', 'false');
  aanMarkeer = -1;
}

function aanToets(e) {
  var opties = document.querySelectorAll('#aanLijst .bh-aansug');
  var zoek = e.target;

  /* Backspace in een leeg zoekveld haalt de laatste chip weg — hetzelfde gebaar
     als in elke mailapp, en sneller dan het kruisje zoeken. */
  if (e.key === 'Backspace' && !zoek.value) {
    var gekozen = document.querySelectorAll('.ontv-cb:checked');
    if (gekozen.length) { gekozen[gekozen.length - 1].checked = false; updateTeller(); }
    return;
  }
  if (e.key === 'Escape') { zoek.value = ''; aanSluit(); return; }
  if (!opties.length) return;

  if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
    e.preventDefault();
    aanMarkeer += (e.key === 'ArrowDown' ? 1 : -1);
    if (aanMarkeer < 0) aanMarkeer = opties.length - 1;
    if (aanMarkeer >= opties.length) aanMarkeer = 0;
    opties.forEach(function (o, i) {
      var aan = i === aanMarkeer;
      o.classList.toggle('aangewezen', aan);
      o.setAttribute('aria-selected', aan ? 'true' : 'false');
    });
    zoek.setAttribute('aria-activedescendant', opties[aanMarkeer].id);
    return;
  }
  if (e.key === 'Enter') {
    e.preventDefault();                     // anders verstuurt Enter het formulier
    opties[aanMarkeer < 0 ? 0 : aanMarkeer].click();
  }
}

document.addEventListener('click', function (e) {
  if (!e.target.closest('.bh-aanveld')) aanSluit();
});
function kortTeller() {
  var t = document.getElementById('tekstKort'), e = document.getElementById('kortTeller');
  if (t && e) e.textContent = '(' + t.value.length + '/400)';
}
function sjabloonWissel() {
  var s = document.getElementById('sjabloonSel');
  var eigen = !s || !s.value;
  document.getElementById('eigenInhoud').hidden = !eigen;
  document.getElementById('sjabloonInfo').hidden = eigen;
}

/* Versturen is onomkeerbaar, dus er komt een bevestiging die zegt wát er gebeurt:
   hoeveel mensen en via welk kanaal. bhBevestig() is asynchroon, dus we houden de
   submit tegen en verzenden hem zelf zodra er ja is gezegd. */
function verzendBevestig(ev) {
  document.getElementById('actieVeld').value = 'verzend';
  var n = berAantal();
  if (!n) { bhToast('Kies eerst minstens één ontvanger.', 'fout'); return false; }
  if (ev && ev.target && ev.target.dataset.bevestigd === 'ja') return true;

  var keuze = document.querySelector('input[name=kanaalmodus]:checked');
  var label = keuze ? keuze.parentNode.querySelector('strong').textContent : 'e-mail';
  var knop  = ev && ev.target ? ev.target : document.getElementById('verzendKnop');

  bhBevestig({
    titel: 'Versturen naar ' + n + ' ' + (n === 1 ? 'persoon' : 'personen') + '?',
    tekst: 'Het bericht gaat via ' + label + ' de deur uit en is daarna niet meer terug te halen. '
         + 'Wie zich heeft afgemeld wordt overgeslagen.',
    knop: 'Ja, versturen'
  }).then(function (ja) {
    if (!ja) return;
    knop.dataset.bevestigd = 'ja';
    knop.click();
  });
  return false;
}

aanAan(); updateTeller(); kortTeller(); sjabloonWissel();
</script>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

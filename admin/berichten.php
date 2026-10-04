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
$mag_bewerken = function_exists('mag') ? mag('mailing', 'versturen') : true;   // taak (includes/rechten.php)
// Grens van de WhatsApp/sms-tekst zoals de ontvanger hem krijgt (register, type handmatig).
$kort_max = (int)((function_exists('melding_definitie') ? melding_definitie('handmatig') : null)['kort_max'] ?? 1000);

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

// Vorige seizoenen: oud-strandwachten en wie om een seintje vroeg (bh_vorige_seizoenen()).
$vorige_groepen = ['oud' => 'Oud-strandwachten', 'seintje' => 'Seintje gevraagd'];
$per_vorig = [];
foreach (bh_vorige_seizoenen($sid) as $v) $per_vorig[$v['groep']][] = $v;
$voorsel = array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? ''))));
if (!empty($_GET['fase']) && isset($per_fase[$_GET['fase']])) foreach ($per_fase[$_GET['fase']] as $s) $voorsel[] = (int)$s['id'];

// ── Eigen sjablonen ────────────────────────────────────────────
$sjablonen = [];
try { $sjablonen = $db->query("SELECT slug, naam, onderwerp FROM mail_templates WHERE slug LIKE 'eigen\\_%' OR categorie='handmatig' ORDER BY naam")->fetchAll(); } catch (Exception $e) {}
$sjablonen = array_values(array_filter($sjablonen, fn($t) => $t['slug'] !== 'handmatig'));

/**
 * Inhoud + override uit het formulier (of uit een eigen sjabloon).
 * Alleen WhatsApp/sms ($modus 'kort'): er is geen e-mailtekst, want wie geen telefoon heeft (of liever
 * e-mail wil) krijgt dezelfde korte tekst als eenvoudige e-mail (besluit Lucas). Die e-mail maken we
 * hier uit de korte tekst (kort_naar_html), met een vast onderwerp.
 */
function berichten_inhoud(array $post, $db, string $modus): array {
    $slug = preg_replace('/[^a-z0-9_-]/i', '', (string)($post['sjabloon'] ?? ''));
    $inhoud = ['onderwerp' => trim($post['onderwerp'] ?? ''), 'body_html' => (string)($post['body_html'] ?? ''), 'tekst_kort' => trim((string)($post['tekst_kort'] ?? ''))];
    if ($slug === '' && $modus === 'kort') {
        $inhoud['onderwerp'] = 'Bericht van ' . (function_exists('instelling') ? instelling('site.naam', 'Reddingsbrigade Paal 13') : 'Reddingsbrigade Paal 13');
        $inhoud['body_html'] = kort_naar_html($inhoud['tekst_kort']);
    }
    $override = ['hero_titel' => $modus === 'kort' ? '' : trim($post['hero_titel'] ?? '')];
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

// ── POST: selecteren / voorbeeld / test / verzenden ───────────
$voorbeeld = null; $resultaat = null; $fout = null; $logica_fouten = []; $selectie_uitslag = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'nieuw') {
    csrf_check();
    $actie  = $_POST['actie'] ?? '';
    // Een voorbeeld of selectie verstuurt niets: dat mag met leesrecht, versturen en testen niet.
    if (!$mag_bewerken && !in_array($actie, ['voorbeeld', 'selecteer'], true)) { http_response_code(403); die('Geen recht om berichten te versturen.'); }
    // Standaard WhatsApp/sms (besluit Lucas); e-mail is de terugval voor wie geen telefoon heeft.
    $modus  = isset($modi[$_POST['kanaalmodus'] ?? '']) ? $_POST['kanaalmodus'] : 'kort';
    $ids    = array_values(array_unique(array_filter(array_map('intval', $_POST['ontvangers'] ?? []))));
    $bi     = berichten_inhoud($_POST, $db, $modus);
    $inhoud = $bi['inhoud']; $override = $bi['override'];
    $voorsel = $ids;
    // Logica in de tekst ({{als …}}): eerst controleren, dan pas bijlagen, een voorbeeld of verzending.
    if ($actie !== 'selecteer' && function_exists('sjabloon_controleer')) {
        $logica_fouten = sjabloon_controleer(['Onderwerp' => [$inhoud['onderwerp'], false], 'E-mailtekst' => [$inhoud['body_html'], true],
                                              'Tekst voor WhatsApp en sms' => [$inhoud['tekst_kort'], false]]);
        // Grens voor wat de ontvanger krijgt: de langste variant, zonder de logica eromheen.
        if ($modus !== 'email') {
            $logica_fouten = array_merge($logica_fouten, sjabloon_kort_controle($inhoud['tekst_kort'], $kort_max, 'Tekst voor WhatsApp en sms'));
        }
    }
    // Bijlagen: geüploade bestanden naar de bibliotheek (alleen bij echt verzenden/testen)
    $bijlagen_opties = [];
    if (!$logica_fouten && in_array($actie, ['verzend', 'test'], true) && !empty($_FILES['bijlagen']['name']) && function_exists('bijlage_opslaan')) {
        foreach ((array)$_FILES['bijlagen']['name'] as $i => $bn) {
            if ($bn === '' || ($_FILES['bijlagen']['error'][$i] ?? 4) !== UPLOAD_ERR_OK) continue;
            $een = ['name' => $bn, 'tmp_name' => $_FILES['bijlagen']['tmp_name'][$i], 'size' => $_FILES['bijlagen']['size'][$i], 'error' => $_FILES['bijlagen']['error'][$i]];
            $bu = bijlage_opslaan($een, (int)(huidige_beheerder()['id'] ?? 0), 'Handmatig bericht');
            if ($bu['ok']) $bijlagen_opties[] = (int)$bu['id']; else $fout = 'Bijlage "' . h($bn) . '" niet opgeslagen: ' . $bu['fout'];
        }
    }

    if ($actie === 'selecteer') {
        // Selecteren met een voorwaarde: alleen de sollicitanten van dit seizoen; de selectie wordt vervangen.
        $vw = trim((string)($_POST['voorwaarde'] ?? ''));
        $logica_fouten = $vw === '' ? ['Schrijf eerst een voorwaarde.'] : array_map(fn($f) => 'Voorwaarde: ' . $f, voorwaarde_fouten($vw, voorwaarde_bekend()));
        if (!$logica_fouten) {
            $voorsel = array_values(array_map(fn($s) => (int)$s['id'], array_filter($alle, fn($s) => voorwaarde_klopt($vw, (int)$s['id']))));
            $selectie_uitslag = [count($voorsel), count($alle)];
        }
    } elseif ($fout || $logica_fouten) {
        // bijlage- of logicafout: niets versturen
    } elseif ($modus !== 'email' && $inhoud['tekst_kort'] === '') {
        $fout = 'Schrijf het bericht voor WhatsApp/sms.';
    } elseif ($modus !== 'kort' && ($inhoud['onderwerp'] === '' || trim(strip_tags($inhoud['body_html'])) === '')) {
        $fout = 'Vul een onderwerp en de e-mailtekst in (of kies een sjabloon).';
    } elseif ($actie === 'voorbeeld') {
        $vars = function_exists('melding_vars') ? melding_vars($ids[0] ?? 0, $inhoud) : $inhoud;
        if (!$ids) { $vars['voornaam'] = 'Lieke'; $vars['achternaam'] = 'de Vries'; $vars['volledige_naam'] = 'Lieke de Vries'; }
        // Logica: met de gegevens van de eerste ontvanger, zonder ontvanger met voorbeeldwaarden.
        $voorbeeld = melding_render('handmatig', $vars, $override, $ids[0] ?? null);
    } elseif ($actie === 'test') {
        $bhd = huidige_beheerder();
        $r = melding_verstuur('handmatig', 0, $inhoud, [
            'override' => $override, 'kanaalmodus' => 'email', 'prioriteit' => 'direct', 'negeer_plafond' => true, 'bijlagen' => $bijlagen_opties, 'voorbeeld' => true,
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
            // Iemand uit een vorig seizoen krijgt het bericht op zijn laatste sollicitatie; het
            // seizoen in de tekst hoort dan wel het huidige te zijn, niet dat van toen.
            $vorig_ids = [];
            foreach ($per_vorig as $_rijen) foreach ($_rijen as $v) { $namen[(int)$v['sol_id']] = volledige_naam($v); $vorig_ids[(int)$v['sol_id']] = true; }
            $nu_seizoen = ['seizoen_naam' => (string)($seizoen['naam'] ?? ''), 'seizoen.naam' => (string)($seizoen['naam'] ?? '')];
            foreach ($ids as $id) {
                if (!isset($namen[$id])) continue;
                $opt = ['override' => $override, 'kanaalmodus' => $modus, 'prioriteit' => $prio, 'referentie' => 'handmatig:' . date('YmdHi'), 'bijlagen' => $bijlagen_opties];
                // Negatief id = seintje via de website, zonder sollicitatie (includes/seintje_werving.php):
                // altijd e-mail, eigen afmeldlink, en daarna gemarkeerd als verstuurd.
                $r = $id < 0
                    ? seintje_werving_verstuur(-$id, $inhoud + $nu_seizoen, $opt)
                    : melding_verstuur('handmatig', $id, isset($vorig_ids[$id]) ? $inhoud + $nu_seizoen : $inhoud, $opt);
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

<?php $bt_actief = $tab; require __DIR__ . '/_berichten_tabs.php'; ?>

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
      : 'Er is nog niets verstuurd.';   // Verzonden toont alle seizoenen, dus geen "dit seizoen"
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
<?php $fouten = $logica_fouten; $fouten_titel = ($_POST['actie'] ?? '') === 'selecteer' ? 'Niets geselecteerd' : 'Niets verstuurd: de logica klopt nog niet';
      require __DIR__ . '/_foutlijst.php'; ?>

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

<?php
  // Kanaal: standaard WhatsApp/sms. Welke velden je ziet, volgt de keuze (data-kanaal, berichten.js);
  // zonder JavaScript staan ze er allemaal.
  $gekozen = (string)($_POST['kanaalmodus'] ?? 'kort');
  $_uitleg = ['kort' => 'Via WhatsApp of sms, zoals de ontvanger zelf koos. Zonder telefoon: dezelfde tekst per e-mail.',
              'email' => 'Een e-mail met volledige opmaak, knoppen en bijlagen.',
              'beide' => 'Een e-mail én een korte tekst via WhatsApp of sms.'];
  $_volgorde = array_intersect_key(['kort' => 1, 'email' => 1, 'beide' => 1], $modi);
  if (!isset($_volgorde[$gekozen])) $gekozen = (string)array_key_first($_volgorde);
?>
<form method="POST" id="berichtForm" enctype="multipart/form-data" class="bh-berichtform" data-kanaal-form="<?= h($gekozen) ?>">
  <?= csrf_input() ?>
  <input type="hidden" name="actie" id="actieVeld" value="verzend">

  <section class="ds-kaart ds-kaart-p bh-berichtstap">
    <div class="bh-kaartkop">
      <h2><span class="bh-stapnr">1</span> Aan</h2>
      <span class="bh-kaartkop-knoppen">
        <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" data-klik="selAlles(true)">Iedereen</button>
        <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" data-klik="selAlles(false)">Niemand</button>
      </span>
    </div>

    <?php /* Het zoekveld met chips. Staat verborgen tot de JS het aanzet: zonder
             JavaScript blijft de lijst eronder het werkende formulier. De vinkjes
             in die lijst blijven in beide gevallen de bron van waarheid — de chips
             zijn er een weergave van, zodat er maar één stand bestaat. */ ?>
    <div class="bh-aanveld" id="aanVeld" hidden>
      <div class="bh-aanveld-vak" id="aanVak" data-klik="aanFocus(event)">
        <input type="text" id="aanZoek" class="bh-aanveld-zoek" placeholder="naam typen…"
               autocomplete="off" role="combobox" aria-expanded="false"
               aria-controls="aanLijst" aria-autocomplete="list"
               aria-label="Zoek een ontvanger op naam of e-mailadres"
               data-invoer="aanZoeken()" data-toets="aanToets(event)" data-focus="aanZoeken()">
      </div>
      <ul class="bh-aanveld-lijst" id="aanLijst" role="listbox" aria-label="Gevonden mensen" hidden></ul>
      <p class="bh-aanveld-leeg" id="aanLeeg" hidden>Niemand gevonden met die naam.</p>
      <?php if ($alle || $per_vorig): ?>
      <div class="bh-aanveld-snel">
        <?php foreach (array_keys($fases) as $f): if (empty($per_fase[$f])) continue; ?>
          <button type="button" class="bh-aanchip-snel" data-klik="selGroep('<?= h($f) ?>', true)">
            + <?= h(fase_label_kort($f)) ?> (<?= count($per_fase[$f]) ?>)
          </button>
        <?php endforeach; ?>
        <?php foreach ($vorige_groepen as $g => $gl): if (empty($per_vorig[$g])) continue; ?>
          <button type="button" class="bh-aanchip-snel" data-klik="selGroep('<?= h($g) ?>', true)">
            + <?= h($gl) ?> (<?= count($per_vorig[$g]) ?>)
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <details class="bh-klap bh-ontvlijst" id="aanAlles" open>
      <summary><span class="bh-klap-titel">Of kies uit de volledige lijst</span></summary>
    <div class="bh-ontvangers">
      <?php if (!$alle && !$per_vorig): ?>
        <p class="bh-meta">Geen sollicitanten in dit seizoen.</p>
      <?php endif; ?>
      <?php foreach (array_keys($fases) as $f): if (empty($per_fase[$f])) continue; ?>
      <div class="bh-ontvgroep">
        <label class="bh-ontvgroep-kop">
          <input type="checkbox" class="groep-cb" data-klik="selGroep('<?= h($f) ?>',this.checked)">
          <?= bh_fase_badge($f, ['lengte' => 'kort']) ?>
          <span class="bh-ontvgroep-aantal"><?= count($per_fase[$f]) ?></span>
        </label>
        <?php foreach ($per_fase[$f] as $s) {
          $ontv = $s; $ontv_id = (int)$s['id']; $ontv_groep = $f; $ontv_label = fase_label_kort($f);
          $ontv_aan = in_array((int)$s['id'], $voorsel, true);
          require __DIR__ . '/_ontvrij.php';
        } ?>
      </div>
      <?php endforeach; ?>

      <?php foreach ($vorige_groepen as $g => $gl): if (empty($per_vorig[$g])) continue; ?>
      <div class="bh-ontvgroep">
        <label class="bh-ontvgroep-kop">
          <input type="checkbox" class="groep-cb" data-klik="selGroep('<?= h($g) ?>',this.checked)">
          <?= bh_badge($gl, $g === 'oud' ? 'blauw' : 'paars') ?>
          <span class="bh-ontvgroep-aantal"><?= count($per_vorig[$g]) ?></span>
        </label>
        <?php foreach ($per_vorig[$g] as $v) {
          $ontv = $v; $ontv_id = (int)$v['sol_id']; $ontv_groep = $g; $ontv_label = $gl;
          $ontv_sub = $v['laatste_seizoen'] ? 'laatst ' . $v['laatste_seizoen']
                    : (!empty($v['werving_seintje_id']) ? 'via de website, nog niet gesolliciteerd' : '');
          $ontv_aan = in_array((int)$v['sol_id'], $voorsel, true);
          require __DIR__ . '/_ontvrij.php';
        } ?>
      </div>
      <?php endforeach; ?>
      <?php if ($per_vorig): ?>
        <p class="ds-veld-hint">
          Vorige seizoenen: alleen oud-strandwachten en wie zelf om een seintje vroeg (ook via
          de website toen de aanmelding dicht was; die krijgen het bericht één keer, per e-mail).
          Afgewezen zonder seintje staan hier bewust niet: daarvoor ontbreekt de toestemming.
        </p>
      <?php endif; ?>
    </div>
    </details>

    <?php if ($alle): $_vw_open = isset($_POST['voorwaarde']) && trim((string)$_POST['voorwaarde']) !== ''; ?>
    <details class="bh-klap" <?= $_vw_open ? 'open' : '' ?>>
      <summary><span class="bh-klap-titel">Selecteren met een voorwaarde</span><span class="bh-klap-sam">voor gevorderden</span></summary>
      <div class="bh-klap-inhoud">
        <?php if ($selectie_uitslag): ?>
          <p class="ds-melding ds-melding-info" role="status">
            <?= (int)$selectie_uitslag[0] ?> van de <?= (int)$selectie_uitslag[1] ?> sollicitanten van dit seizoen voldoen; die staan nu aangevinkt.
          </p>
        <?php endif; ?>
        <div class="ds-veld">
          <label class="ds-label" for="ber_voorwaarde">Voorwaarde</label>
          <textarea class="ds-textarea" id="ber_voorwaarde" name="voorwaarde" rows="2" data-var-veld aria-describedby="ber_voorwaarde_hint"
                    placeholder='kennismakingsformulier = "open" en leeftijd &lt; 18'><?= h($_POST['voorwaarde'] ?? '') ?></textarea>
          <p class="ds-veld-hint" id="ber_voorwaarde_hint">
            Vinkt iedereen van dit seizoen aan die voldoet; de rest gaat uit. Er wordt niets verstuurd.
          </p>
        </div>
        <?php $vw_soort = 'voorwaarde'; $vw_doel = 'ber_voorwaarde'; require __DIR__ . '/_voorwaarden.php'; ?>
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm"
                data-klik="P13Klik.zet('#actieVeld', 'selecteer')">Selecteren</button>
      </div>
    </details>
    <?php endif; ?>
  </section>

  <section class="ds-kaart ds-kaart-p bh-berichtstap">
    <div class="bh-kaartkop"><h2><span class="bh-stapnr">2</span> Hoe</h2></div>
    <div class="bh-kanaalkiezer">
      <?php foreach (array_keys($_volgorde) as $mk): ?>
      <div class="bh-kanaalkeuze">
        <input type="radio" name="kanaalmodus" id="km_<?= h($mk) ?>" value="<?= h($mk) ?>" <?= $gekozen === $mk ? 'checked' : '' ?>
               data-wijzig="kanaalWissel()">
        <label for="km_<?= h($mk) ?>">
          <strong><?= h($modi[$mk]) ?></strong>
          <span><?= h($_uitleg[$mk]) ?></span>
        </label>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="ds-veld-hint" id="terugvalTelling" aria-live="polite"></p>
    <p class="ds-veld-hint">
      Wie zich heeft afgemeld wordt overgeslagen; elk bericht krijgt een afmeldregel.
    </p>
  </section>

  <section class="ds-kaart ds-kaart-p bh-berichtstap">
    <div class="bh-kaartkop">
      <h2><span class="bh-stapnr">3</span> Bericht</h2>
      <?php if ($sjablonen): ?>
      <select class="ds-select bh-veld-smal bh-kaartkop-veld" name="sjabloon" id="sjabloonSel" data-wijzig="sjabloonWissel()"
              aria-label="Sjabloon kiezen">
        <option value="">Zelf schrijven</option>
        <?php foreach ($sjablonen as $t): ?>
          <option value="<?= h($t['slug']) ?>" <?= ($_POST['sjabloon'] ?? '') === $t['slug'] ? 'selected' : '' ?>><?= h($t['naam']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
    </div>

    <div id="eigenInhoud" class="bh-berichtvelden">
      <div class="ds-veld" data-kanaal="kort beide">
        <label class="ds-label" for="tekstKort">Tekst voor WhatsApp en sms</label>
        <div class="bh-kortopmaak" role="toolbar" aria-label="Opmaak" aria-controls="tekstKort">
          <button type="button" class="p13-ek" data-opmaak="*" title="Vet: *tekst*"><strong>B</strong><span class="bh-vw">Vet</span></button>
          <button type="button" class="p13-ek" data-opmaak="_" title="Cursief: _tekst_"><em>I</em><span class="bh-vw">Cursief</span></button>
          <button type="button" class="p13-ek" data-opmaak="~" title="Doorhalen: ~tekst~"><s>S</s><span class="bh-vw">Doorhalen</span></button>
          <button type="button" class="p13-ek" data-opmaak="lijst" title="Opsomming: regels met - ervoor">Lijst</button>
          <button type="button" class="p13-ek" data-opmaak="link" title="Link naar het portaal">Link portaal</button>
        </div>
        <textarea class="ds-textarea" name="tekst_kort" id="tekstKort" rows="6" maxlength="<?= $kort_max * 5 ?>" data-kort-max="<?= $kort_max ?>" data-var-veld
                  placeholder="Hoi {{voornaam}}, … Meer info: {{portaal_url}}"><?= h($_POST['tekst_kort'] ?? '') ?></textarea>
        <div class="bh-kortvoet">
          <span class="bh-meta" id="kortTeller" aria-live="polite"></span>
          <span class="bh-meta">*vet* _cursief_ ~doorgehaald~ · sms toont geen opmaak</span>
        </div>
        <div class="bh-kortvoorbeeld" aria-label="Zo ziet het eruit in WhatsApp">
          <div class="bh-voorbeeld-bubbel" id="kortBubbel"></div>
        </div>
      </div>

      <div class="ds-veld" data-kanaal="email beide">
        <label class="ds-label" for="ber_onderwerp">Onderwerp van de e-mail</label>
        <input class="ds-input" type="text" id="ber_onderwerp" name="onderwerp" data-var-veld
               value="<?= h($_POST['onderwerp'] ?? '') ?>" placeholder="Bijv. Informatie over het aankomende seizoen">
      </div>
      <div class="ds-veld" data-kanaal="email beide">
        <span class="ds-label">E-mailtekst</span>
        <?= editor_html('bericht', 'body_html', (string)($_POST['body_html'] ?? "<p>Hoi {{voornaam}},</p>\n<p></p>"), ['hoogte' => 280]) ?>
      </div>
      <details class="bh-klap" data-kanaal="email beide">
        <summary><span class="bh-klap-titel">Meer opties voor de e-mail</span><span class="bh-klap-sam">grote titel bovenin</span></summary>
        <div class="ds-veld">
          <label class="ds-label" for="ber_hero">Grote titel bovenin <span>optioneel; anders het onderwerp</span></label>
          <input class="ds-input" type="text" id="ber_hero" name="hero_titel" data-var-veld value="<?= h($_POST['hero_titel'] ?? '') ?>">
        </div>
      </details>

      <?php require __DIR__ . '/_variabelen.php'; ?>
      <?php $vw_soort = 'sjabloon'; require __DIR__ . '/_voorwaarden.php'; ?>

      <details class="bh-klap">
        <summary><span class="bh-klap-titel">Bijlagen</span><span class="bh-klap-sam">optioneel</span></summary>
        <div class="ds-veld">
          <label class="ds-label" for="ber_bijlagen">Bestanden</label>
          <input class="ds-input bh-veld-bestand" type="file" id="ber_bijlagen" name="bijlagen[]" multiple
                 accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx">
          <p class="ds-veld-hint">
            Gaan mee in de e-mail; boven <?= function_exists('bijlagen_mail_max_bytes') ? round(bijlagen_mail_max_bytes() / 1048576) : 15 ?> MB
            wordt het een downloadknop. WhatsApp en sms krijgen een downloadlink die veertien dagen
            geldig is, en dan gaat er ook een e-mail uit. Maximaal
            <?= function_exists('bijlagen_max_bytes') ? round(bijlagen_max_bytes() / 1048576) : 25 ?> MB per bestand<?= function_exists('bijlagen_max_bytes') && bijlagen_max_bytes() < 25 * 1048576 ? ' — de server staat op dit moment niet meer toe' : '' ?>.
          </p>
        </div>
      </details>
    </div>

    <p id="sjabloonInfo" class="ds-veld-hint" hidden>
      De inhoud komt uit het gekozen sjabloon.
      <?php if ($mag_bewerken): ?><a href="<?= APP_URL ?>/admin/mail_templates.php">Sjabloon bewerken</a><?php endif; ?>
    </p>
  </section>

  <section class="ds-kaart bh-verzendbalk">
    <?php if ($mag_bewerken): ?>
    <button type="submit" class="ds-btn ds-btn-primair" id="verzendKnop"
            data-klik="return verzendBevestig(event)">
      Versturen naar <span id="verzendTeller">niemand</span>
    </button>
    <?php endif; ?>
    <button type="submit" class="ds-btn ds-btn-secundair"
            data-klik="P13Klik.zet('#actieVeld', 'voorbeeld')">Voorbeeld</button>
    <?php if ($mag_bewerken): ?>
    <button type="submit" class="ds-btn ds-btn-zacht"
            data-klik="P13Klik.zet('#actieVeld', 'test')">
      Testmail naar mij
    </button>
    <?php else: ?>
      <span class="bh-meta">Je mag berichten lezen, niet versturen.</span>
    <?php endif; ?>
  </section>
</form>
<script src="<?= APP_URL ?>/assets/js/variabelen.js?v=<?= @filemtime(__DIR__ . '/../assets/js/variabelen.js') ?>" defer></script>
<script src="<?= APP_URL ?>/assets/js/berichten.js?v=<?= @filemtime(__DIR__ . '/../assets/js/berichten.js') ?>" defer></script>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

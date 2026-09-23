<?php
/**
 * Beheer — Geplande berichten: "X dagen na/vóór gebeurtenis Y stuur sjabloon Z".
 * Regels staan in geplande_berichten; ze draaien mee met de herinneringen (cron) en zijn hier
 * proef te draaien (niets versturen).
 *
 * Staat als tab "Gepland" onder Berichten (_berichten_tabs.php), dus titel en nav-item
 * zijn die van Berichten.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
$pagina_titel = 'Berichten';
$actieve_nav  = 'berichten';
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

/** "10 dagen na aangemeld" — het moment van een regel in één zin. */
$moment = function (array $r) use ($gebeurtenissen): string {
    $d = (int)$r['offset_dagen'];
    $wanneer = $d === 0 ? 'Op de dag van' : abs($d) . ' ' . (abs($d) === 1 ? 'dag' : 'dagen') . ' ' . ($d < 0 ? 'vóór' : 'na');
    return $wanneer . ' ' . lcfirst($gebeurtenissen[$r['gebeurtenis']] ?? $r['gebeurtenis']);
};
?>

<?php $bt_actief = 'gepland'; require __DIR__ . '/_berichten_tabs.php'; ?>

<?php if ($proef !== null): ?>
<section class="ds-kaart ds-kaart-p bh-mb" aria-live="polite">
  <div class="bh-kaartkop"><h2>Proefrun</h2><?= bh_badge('Niets verstuurd', 'grijs') ?></div>
  <?php if (!$proef): ?>
    <p class="bh-meta">Geen actief seizoen, of de regel is niet gevonden.</p>
  <?php endif; ?>
  <?php foreach ($proef as $pr): ?>
    <p>
      <strong><?= h($pr['naam']) ?></strong><br>
      <?= bh_meta([(int)$pr['kandidaten'] . ' ' . ((int)$pr['kandidaten'] === 1 ? 'kandidaat' : 'kandidaten'),
                   (int)$pr['verstuurd'] . ' zou versturen',
                   (int)$pr['overgeslagen'] . ' overgeslagen',
                   (string)($pr['fout'] ?? '')]) ?>
    </p>
    <?php if (!empty($pr['lijst'])): ?>
      <div class="bh-chiprij">
        <?php foreach ($pr['lijst'] as $hl): ?><?= bh_chip($hl['naam'] . ': ' . $hl['actie']) ?><?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($bewerk || $nieuw): ?>
<form method="post" class="ds-kaart ds-kaart-p bh-veldform">
  <?= csrf_input() ?>
  <input type="hidden" name="actie" value="opslaan">
  <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

  <div class="bh-kaartkop"><h2><?= $bewerk ? 'Regel bewerken' : 'Nieuwe regel' ?></h2></div>

  <?php if (!$sjablonen): ?>
    <div class="ds-melding ds-melding-warn">
      <span>Maak eerst een eigen sjabloon aan onder <a href="<?= APP_URL ?>/admin/mail_templates.php">Sjablonen</a>:
      dat is de inhoud van het bericht, eventueel met bijlage.</span>
    </div>
  <?php endif; ?>

  <div class="ds-veld">
    <label class="ds-label" for="gb_naam">Naam (intern)</label>
    <input class="ds-input" type="text" id="gb_naam" name="naam" value="<?= h($form['naam']) ?>" required
           placeholder="bijv. Magazine na formulier">
  </div>

  <fieldset class="bh-veldgroep">
    <legend>Wanneer</legend>
    <div class="bh-veldrij">
      <div class="ds-veld">
        <label class="ds-label" for="gb_gebeurtenis">Gebeurtenis</label>
        <select class="ds-select" id="gb_gebeurtenis" name="gebeurtenis">
          <?php foreach ($gebeurtenissen as $gk => $gl): ?>
            <option value="<?= h($gk) ?>" <?= $form['gebeurtenis'] === $gk ? 'selected' : '' ?>><?= h($gl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ds-veld">
        <label class="ds-label" for="gb_offset">Dagen erna</label>
        <input class="ds-input" type="number" id="gb_offset" name="offset_dagen" value="<?= (int)$form['offset_dagen'] ?>"
               min="-60" max="365" aria-describedby="gb_offset_hint">
        <p class="ds-veld-hint" id="gb_offset_hint">10 = tien dagen na de gebeurtenis; -3 = drie dagen ervóór.</p>
      </div>
    </div>
    <div class="ds-veld">
      <label class="ds-label" for="gb_venster">Venster (dagen)</label>
      <input class="ds-input" type="number" id="gb_venster" name="venster_dagen" value="<?= (int)$form['venster_dagen'] ?>"
             min="1" max="60" aria-describedby="gb_venster_hint">
      <p class="ds-veld-hint" id="gb_venster_hint">Alleen als het moment hooguit zoveel dagen geleden was. Zo krijgen oude gevallen het bericht niet alsnog.</p>
    </div>
  </fieldset>

  <fieldset class="bh-veldgroep">
    <legend>Wat</legend>
    <div class="bh-veldrij">
      <div class="ds-veld">
        <label class="ds-label" for="gb_sjabloon">Sjabloon (inhoud)</label>
        <select class="ds-select" id="gb_sjabloon" name="sjabloon_slug" required>
          <option value="">Kies een sjabloon</option>
          <?php foreach ($sjablonen as $t): ?>
            <option value="<?= h($t['slug']) ?>" <?= $form['sjabloon_slug'] === $t['slug'] ? 'selected' : '' ?>><?= h($t['naam']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ds-veld">
        <label class="ds-label" for="gb_kanaal">Kanaal</label>
        <select class="ds-select" id="gb_kanaal" name="kanaalmodus">
          <?php foreach ($modi as $mk => $ml): ?>
            <option value="<?= h($mk) ?>" <?= $form['kanaalmodus'] === $mk ? 'selected' : '' ?>><?= h($ml) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="ds-veld">
      <label class="ds-label" for="gb_max">Hoe vaak per persoon (max)</label>
      <input class="ds-input" type="number" id="gb_max" name="max_per_sollicitatie" value="<?= (int)$form['max_per_sollicitatie'] ?>" min="1" max="10">
    </div>
  </fieldset>

  <fieldset class="bh-veldgroep">
    <legend>Voor wie</legend>
    <p class="ds-veld-hint">Niets aangevinkt = alle fases.</p>
    <div class="bh-fasekeuze">
      <?php foreach ($fases as $fk => $fl): ?>
        <label class="bh-fasechip ds-fase-<?= h(fase_kleur($fk)) ?>">
          <input type="checkbox" name="fases[]" value="<?= h($fk) ?>" <?= in_array($fk, $form_fases, true) ? 'checked' : '' ?>>
          <?= h($fl) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <label class="bh-dialoog-vink">
      <input type="checkbox" name="aan" value="1" <?= (int)$form['aan'] ? 'checked' : '' ?>>
      <span>Regel staat aan</span>
    </label>
  </fieldset>

  <div class="bh-veldform-voet">
    <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
    <a href="<?= APP_URL ?>/admin/geplande_berichten.php" class="ds-btn ds-btn-zacht">Annuleren</a>
  </div>
</form>
<?php endif; ?>

<section class="ds-kaart ds-kaart-p">
  <div class="bh-kaartkop">
    <h2>Regels</h2>
    <?php if (!$bewerk && !$nieuw): ?>
      <div class="bh-kaartkop-knoppen"><a href="?nieuw=1" class="ds-btn ds-btn-primair ds-btn-sm">Nieuwe regel</a></div>
    <?php endif; ?>
  </div>

  <?php if (!$regels): ?>
    <?php
      $leeg_titel = 'Nog geen geplande berichten';
      $leeg_tekst = 'Bijvoorbeeld: tien dagen nadat iemand het formulier heeft ingeleverd het magazine sturen.';
      $leeg_icoon = 'klok';
      require __DIR__ . '/_leeg.php';
    ?>
  <?php else: ?>
  <div class="bh-tabel-wrap">
  <table class="bh-tabel" style="--bh-tracks:76px minmax(0,1fr) auto">
    <thead>
      <tr><th scope="col">Stand</th><th scope="col">Regel</th><th scope="col" class="bh-rechts">Acties</th></tr>
    </thead>
    <tbody>
      <?php foreach ($regels as $r):
        $_fasen = $r['fases'] ? implode(', ', array_map(fn($f) => $fases[$f] ?? $f, explode(',', $r['fases']))) : '';
      ?>
      <tr<?= (int)$r['aan'] ? '' : ' class="bh-rij-gedempt"' ?>>
        <td data-label="Stand"><?= (int)$r['aan'] ? bh_badge('Aan', 'groen') : bh_badge('Uit', 'grijs') ?></td>
        <td data-label="Regel">
          <span class="bh-naamcel-tekst">
            <span class="bh-naamcel-naam"><?= h($r['naam']) ?></span>
            <span class="bh-naamcel-sub"><?= bh_meta([
                $moment($r),
                'sjabloon "' . ($sjabloon_naam[$r['sjabloon_slug']] ?? $r['sjabloon_slug']) . '"',
                $modi[$r['kanaalmodus']] ?? $r['kanaalmodus'],
                $_fasen !== '' ? 'alleen ' . $_fasen : '',
                'max ' . (int)$r['max_per_sollicitatie'] . '× per persoon',
            ]) ?></span>
          </span>
        </td>
        <td class="bh-rechts">
          <div class="bh-acties">
            <form method="post">
              <?= csrf_input() ?>
              <input type="hidden" name="actie" value="proef">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="ds-btn ds-btn-zacht ds-btn-sm">Proefdraaien</button>
            </form>
            <a href="?id=<?= (int)$r['id'] ?>" class="ds-btn ds-btn-secundair ds-btn-sm">Bewerken</a>
            <form method="post" data-bevestig-titel="Regel verwijderen?"
                  data-bevestig="Deze automatische regel wordt gewist. Berichten die er eerder uit zijn gegaan blijven in het log staan."
                  data-bevestig-knop="Ja, verwijderen" data-bevestig-gevaar="1">
              <?= csrf_input() ?>
              <input type="hidden" name="actie" value="verwijder">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="ds-btn ds-btn-gevaar ds-btn-sm">Verwijderen</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/layout_bottom.php'; ?>

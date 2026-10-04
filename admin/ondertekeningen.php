<?php
/**
 * Beheer — Ondertekeningen: wie moet nog tekenen, en wat is al binnen?
 *
 * Het scherm opent op de openstaande kant, want dat is de vraag. Compleet is geen
 * werk meer en staat achter een tab. Met dertig mensen moet je snel vinden wie je zoekt:
 * zoeken, filteren op document en "ouder tekent mee", sorteren op wat het langst openstaat, en
 * per persoon de documenten inklapbaar. Een herinnering sturen kan per persoon of voor iedereen
 * die de filter toont — hetzelfde bericht als de automatische herinnering.
 * Logica (geen HTML): includes/beheer/ondertekeningen.php; versturen: ondertekening_herinneren().
 *
 * Twee leeftijdsgrenzen die hier zichtbaar moeten blijven: 18 is de ondertekengrens
 * (daaronder tekent een ouder mee), 15/16 is de aanmeld- en AVG-grens. Die twee zijn
 * juridisch verschillend; voeg ze nooit samen.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
vereis_recht('ondertekeningen', 'lezen');
$db = db();

// ── Eén ondertekening openen als PDF ──────────────────────────
// Vóór layout_top.php: dit pad stuurt een bestand of een redirect, geen pagina.
if (isset($_GET['export'])) {
    $id = (int)$_GET['export'];
    $q = $db->prepare("
        SELECT o.*, s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum, sz.naam AS seizoen_naam
        FROM ondertekeningen o
        JOIN sollicitaties s ON s.id = o.sollicitatie_id
        JOIN personen p ON p.id = s.persoon_id
        JOIN seizoenen sz ON sz.id = s.seizoen_id
        WHERE o.id = ?
    ");
    $q->execute([$id]);
    $rec = $q->fetch();
    if (!$rec) { http_response_code(404); exit('Niet gevonden'); }

    // Dezelfde regel als in de lijst hieronder en in het dossier: één bron.
    // Dat leesrecht op dit scherm bestaat, zegt nog niet dat élk document open mag.
    $toegang = bh_mag_document((string)$rec['type']);
    if (!$toegang[0]) {
        http_response_code(403);
        exit('Dit document is niet voor jouw rol (' . $toegang[1] . ').');
    }
    // BSN en IBAN gaan altijd via de gelogde download van de kluis.
    if (in_array($rec['type'], ['loonheffing', 'lidmaatschap'], true)) {
        redirect(APP_URL . '/admin/document_download.php?ond=' . $id . '&t=' . urlencode(csrf_token()));
    }

    // Exact dezelfde generator als de mail naar de ouders, zodat bekijken in het
    // beheer identiek is aan wat de ouder heeft ontvangen.
    $sol_voor_pdf = [
        'voornaam'      => $rec['voornaam'] ?? '',
        'achternaam'    => trim(($rec['tussenvoegsel'] ? $rec['tussenvoegsel'] . ' ' : '') . ($rec['achternaam'] ?? '')),
        'geboortedatum' => $rec['geboortedatum'] ?? '',
    ];
    if (function_exists('ondertekening_pdf_stream')) {
        ondertekening_pdf_stream($rec, $sol_voor_pdf, 'Ondertekend_' . preg_replace('/[^a-z0-9]/i', '_', $rec['type']) . '.pdf');
    }
    http_response_code(500);
    exit('Kon het PDF-document niet genereren.');
}

$pagina_titel = 'Ondertekeningen';
$actieve_nav  = 'ondertekeningen';

$seizoen      = actief_seizoen();
$sid          = (int)($seizoen['id'] ?? 0);
$mag_bewerken = mag('ondertekeningen', 'bewerken');
$hier         = APP_URL . '/admin/ondertekeningen.php';

// Filters uit de URL; de POST van "herinnering" draagt dezelfde mee, zodat je terugkomt waar je was.
$bron = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$f = [
    'status' => (string)($bron['filter'] ?? 'open'),
    'q'      => (string)($bron['q'] ?? ''),
    'soort'  => (string)($bron['soort'] ?? ''),
    'ouder'  => (string)($bron['ouder'] ?? ''),
    'sort'   => (string)($bron['sort'] ?? 'naam'),
];
if (!in_array($f['status'], ['alle', 'open', 'compleet'], true)) $f['status'] = 'open';
if (!isset(bh_ond_sorteringen()[$f['sort']])) $f['sort'] = 'naam';
/** URL met de huidige filters, eventueel met een andere waarde. */
$url_met = function (array $anders = []) use ($f, $hier): string {
    $p = ['filter' => $f['status'], 'q' => $f['q'], 'soort' => $f['soort'], 'ouder' => $f['ouder'],
          'sort' => $f['sort'] !== 'naam' ? $f['sort'] : ''];
    $p = array_filter(array_merge($p, $anders), fn($v) => $v !== '');
    return $hier . ($p ? '?' . http_build_query($p) : '');
};

$per_persoon = bh_ond_per_persoon($sid);

// ── Herinnering sturen (per persoon, of iedereen die de filter nu toont) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    csrf_check();
    if (!$mag_bewerken) { http_response_code(403); exit('Herinneringen sturen vraagt bewerkrecht op ondertekeningen.'); }
    $actie = (string)($_POST['actie'] ?? '');
    $ids = [];
    if ($actie === 'herinner') {
        $ids = $per_persoon[(int)($_POST['sol_id'] ?? 0)]['open_ids'] ?? [];
    } elseif ($actie === 'herinner_alle') {
        $ids = bh_ond_open_ids(bh_ond_filter($per_persoon, ['status' => 'open'] + $f));
    }
    $r = ondertekening_herinneren($ids);
    $tekst = $r['verstuurd'] === 0 ? 'Er is geen herinnering verstuurd.'
           : ($r['verstuurd'] === 1 ? '1 herinnering verstuurd.' : $r['verstuurd'] . ' herinneringen verstuurd.');
    if ($r['dubbel']) $tekst .= ' ' . $r['dubbel'] . ' niet opnieuw: het afgelopen uur al herinnerd.';
    if ($r['niet'])   $tekst .= ' ' . $r['niet'] . ' overgeslagen (al getekend, geen e-mailadres of niet meer in de procedure).';
    flash($r['verstuurd'] > 0 ? 'succes' : 'info', $tekst);
    redirect($url_met() . ($actie === 'herinner' ? '#ond-' . (int)($_POST['sol_id'] ?? 0) : ''));
}

$aantal_compleet = 0;
$aantal_open     = 0;
foreach ($per_persoon as $p) {
    if ($p['klaar'] === $p['totaal']) $aantal_compleet++; else $aantal_open++;
}
// Standaard "open", want dat is het werk; is er niets open, dan meteen alles.
if ($f['status'] === 'open' && $aantal_open === 0 && $aantal_compleet > 0 && !isset($_GET['filter'])) $f['status'] = 'alle';

$zichtbaar  = bh_ond_filter($per_persoon, $f);
$open_hier  = bh_ond_open_ids($zichtbaar);
$n_open_hier = count(array_filter($zichtbaar, fn($p) => $p['open_ids']));

// Documentsoorten die dit seizoen voorkomen, voor het filter.
$soorten = [];
foreach ($per_persoon as $p) foreach (array_keys($p['soorten']) as $_t) $soorten[$_t] = ondertekening_label($_t);
asort($soorten);

require __DIR__ . '/layout_top.php';
?>

<?php if (empty($per_persoon)): ?>
  <?php
  $leeg_titel = 'Nog geen ondertekeningen klaargezet';
  $leeg_tekst = 'Zodra iemand wordt uitgenodigd voor de selectiedag, zet het systeem de formulieren klaar en verschijnen ze hier.';
  $leeg_icoon = 'ondertekenen';
  require __DIR__ . '/_leeg.php';
  ?>
<?php else: ?>

<?php
$tabs_data = ['tabs' => [
    ['sleutel' => 'open',     'label' => 'Nog open', 'aantal' => $aantal_open,     'kleur' => 'oranje', 'actueel' => true],
    ['sleutel' => 'compleet', 'label' => 'Compleet', 'aantal' => $aantal_compleet, 'kleur' => 'groen',  'actueel' => true],
], 'vreemd' => [], 'vreemd_aantal' => 0];
$tabs_actief = $f['status'];
$tabs_totaal = count($per_persoon);
$tabs_url    = fn($s) => $url_met(['filter' => $s]);
$tabs_label  = 'Filter op stand';
require __DIR__ . '/_tabs.php';

$filter_verborgen = ['filter' => $f['status']];
$filter_velden = [
    ['soort' => 'zoek',    'naam' => 'q',     'label' => 'Zoek op naam', 'waarde' => $f['q']],
    ['soort' => 'keuze',   'naam' => 'soort', 'label' => 'Document', 'waarde' => $f['soort'],
     'opties' => ['' => 'Document: alle'] + array_map(fn($l) => 'Document: ' . $l, $soorten)],
    ['soort' => 'keuze',   'naam' => 'ouder', 'label' => 'Wie tekent', 'waarde' => $f['ouder'],
     'opties' => ['' => 'Wie tekent: iedereen', 'ja' => 'Ouder tekent mee (onder 18)', 'nee' => 'Tekent zelf']],
    ['soort' => 'sorteer', 'naam' => 'sort',  'label' => 'Sorteren', 'waarde' => $f['sort'], 'opties' => bh_ond_sorteringen()],
];
$filter_wissen = ($f['q'] !== '' || $f['soort'] !== '' || $f['ouder'] !== '' || $f['sort'] !== 'naam') ? $url_met(['q' => '', 'soort' => '', 'ouder' => '', 'sort' => '']) : null;
require __DIR__ . '/_filterbalk.php';
?>

<?php if (empty($zichtbaar)): ?>
  <?php
  $_gefilterd = $f['q'] !== '' || $f['soort'] !== '' || $f['ouder'] !== '';
  $leeg_titel = $_gefilterd ? 'Niemand gevonden' : ($f['status'] === 'compleet' ? 'Nog niemand is helemaal rond' : 'Iedereen heeft getekend');
  $leeg_tekst = $_gefilterd ? 'Pas de zoekterm of de filters aan.'
      : ($f['status'] === 'compleet'
          ? 'Zodra alle formulieren van iemand terug zijn, staat die persoon hier.'
          : 'Alle ' . $aantal_compleet . ' sollicitanten hebben hun formulieren terug. Er is hier niets meer te doen.');
  $leeg_toon  = $_gefilterd || $f['status'] === 'compleet' ? 'grijs' : 'groen';
  $leeg_icoon = $_gefilterd ? 'zoek' : ($f['status'] === 'compleet' ? 'lijst' : 'vink');
  require __DIR__ . '/_leeg.php';
  ?>
<?php else: ?>

<div class="bh-lijstkop">
  <span class="bh-meta"><?= count($zichtbaar) ?> van <?= count($per_persoon) ?> getoond</span>
  <span class="bh-lijstkop-acties">
    <button type="button" class="ds-btn ds-btn-zacht ds-btn-sm" data-klap-alles="details.bh-ond-docs" aria-pressed="false">Alles uitklappen</button>
    <?php if ($mag_bewerken && $open_hier): ?>
      <form method="post" action="<?= h($hier) ?>"
            data-bevestig="Herinnering sturen voor <?= count($open_hier) ?> open <?= count($open_hier) === 1 ? 'document' : 'documenten' ?> van <?= $n_open_hier ?> <?= $n_open_hier === 1 ? 'persoon' : 'mensen' ?>? Ze krijgen dezelfde mail als de automatische herinnering. Wie het afgelopen uur al een herinnering kreeg, slaan we over.">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="herinner_alle">
        <?php foreach (['filter' => $f['status'], 'q' => $f['q'], 'soort' => $f['soort'], 'ouder' => $f['ouder'], 'sort' => $f['sort']] as $_n => $_v): ?>
          <input type="hidden" name="<?= h($_n) ?>" value="<?= h($_v) ?>">
        <?php endforeach; ?>
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm"><?= icoon('bel', 14) ?> Herinnering aan <?= $n_open_hier === 1 ? '1 persoon' : 'alle ' . $n_open_hier ?></button>
      </form>
    <?php endif; ?>
  </span>
</div>

<div class="bh-ondkaarten">
  <?php foreach ($zichtbaar as $sol => $p):
    $compleet = $p['klaar'] === $p['totaal'];
    $open     = $p['totaal'] - $p['klaar'];
    $sam = $compleet ? 'alles getekend' : implode(' · ', array_filter([
        $open . ' open',
        $p['oudste_open'] ? 'oudste ' . bh_ond_dagen_open($p['oudste_open']) : '',
        $p['herinnerd'] ? 'herinnerd ' . datum_kort_nl(substr($p['herinnerd'], 0, 10)) : 'nog niet herinnerd',
    ]));
  ?>
  <section class="ds-kaart bh-ondkaart<?= $compleet ? ' compleet' : '' ?>" id="ond-<?= (int)$sol ?>">
    <header class="bh-ondkaart-kop">
      <a class="bh-ondkaart-naam" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= (int)$sol ?>&amp;tab=ondertekeningen">
        <?= h($p['naam']) ?>
      </a>
      <?php if ($p['ouder']): ?>
        <span title="Nog geen 18: een ouder of verzorger tekent mee"><?= bh_chip('ouder tekent mee', 'paars') ?></span>
      <?php endif; ?>
      <span class="bh-ondkaart-voortgang"><?= bh_voortgang($p['klaar'], $p['totaal']) ?></span>
      <?= $compleet ? bh_badge('Compleet', 'groen') : bh_badge($open . ' open', 'oranje') ?>
      <?php if ($mag_bewerken && $p['open_ids']): ?>
        <form method="post" action="<?= h($hier) ?>" class="bh-ondkaart-herinner">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="herinner">
          <input type="hidden" name="sol_id" value="<?= (int)$sol ?>">
          <?php foreach (['filter' => $f['status'], 'q' => $f['q'], 'soort' => $f['soort'], 'ouder' => $f['ouder'], 'sort' => $f['sort']] as $_n => $_v): ?>
            <input type="hidden" name="<?= h($_n) ?>" value="<?= h($_v) ?>">
          <?php endforeach; ?>
          <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm" aria-label="Herinnering sturen aan <?= h($p['naam']) ?>">
            <?= icoon('bel', 14) ?> Herinnering
          </button>
        </form>
      <?php endif; ?>
    </header>

    <details class="bh-klap bh-ond-docs" data-klap-id="ond-<?= (int)$sol ?>">
      <summary>
        <span class="bh-klap-titel"><?= $p['totaal'] === 1 ? '1 document' : $p['totaal'] . ' documenten' ?></span>
        <span class="bh-klap-sam"><?= h($sam) ?></span>
      </summary>
      <ul class="bh-ondlijst">
        <?php foreach ($p['docs'] as $o):
          $klaar   = !empty($o['ondertekend_op']);
          $toegang = bh_mag_document((string)$o['type']);
        ?>
        <li>
          <span class="bh-ond-status">
            <?= $klaar ? bh_badge('Ondertekend', 'groen') : bh_badge('Wacht', 'oranje') ?>
          </span>
          <span class="bh-ond-naam">
            <?= h(ondertekening_label((string)$o['type'])) ?>
            <span class="bh-ond-sub">
              <?php if ($klaar): ?>
                <?= h(datum_nl(substr((string)$o['ondertekend_op'], 0, 10))) ?>
                <?php if (!empty($o['ondertekenaar_naam'])): ?> · <?= h((string)$o['ondertekenaar_naam']) ?><?php endif; ?>
              <?php else: ?>
                <?= h(implode(' · ', array_filter([
                    !empty($o['aangemaakt_op']) ? 'open sinds ' . datum_kort_nl(substr((string)$o['aangemaakt_op'], 0, 10)) : '',
                    ($o['ondertekenaar_rol'] ?? '') === 'ouder' ? 'naar de ouder' . (!empty($o['ondertekenaar_email']) ? '' : ' (nog geen adres)') : '',
                    $o['herinnerd'] ? 'herinnerd ' . datum_kort_nl(substr((string)$o['herinnerd'], 0, 10)) : '',
                ]))) ?>
              <?php endif; ?>
            </span>
          </span>
          <span class="bh-ond-acties">
            <?php if ($klaar && !$toegang[0]): ?>
              <span class="bh-ond-slot" title="Dit document bevat gegevens die niet voor iedereen zijn">
                <?= icoon('slot', 14) ?> <?= h((string)$toegang[1]) ?>
              </span>
            <?php elseif ($klaar): ?>
              <a class="ds-btn ds-btn-secundair ds-btn-sm" href="?export=<?= (int)$o['id'] ?>" target="_blank" rel="noopener">
                <?= icoon('document', 14) ?> Bekijken
              </a>
            <?php elseif (!empty($o['token']) && $mag_bewerken): ?>
              <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
                      data-klik="kopieerOndertekenlink('<?= h((string)$o['token']) ?>', this)">
                Link kopiëren
              </button>
            <?php else: ?>
              <span class="bh-meta">nog niet klaargezet</span>
            <?php endif; ?>
          </span>
        </li>
        <?php endforeach; ?>
      </ul>
    </details>
  </section>
  <?php endforeach; ?>
</div>

<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

<?php
/**
 * Beheer — Ondertekeningen: wie moet nog tekenen, en wat is al binnen?
 *
 * Het scherm opent op de openstaande kant, want dat is de vraag. Compleet is geen
 * werk meer en staat achter een tab.
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

$seizoen = actief_seizoen();
$sid     = (int)($seizoen['id'] ?? 0);

$q = $db->prepare("
    SELECT o.*, s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam
    FROM ondertekeningen o
    JOIN sollicitaties s ON s.id = o.sollicitatie_id
    JOIN personen p ON p.id = s.persoon_id
    WHERE s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt')
    ORDER BY p.achternaam, p.voornaam, o.aangemaakt_op
");
$q->execute([$sid]);
$rijen = $q->fetchAll();

$type_label = fn($t) => match ($t) {
    'gezondheid_ouder'  => 'Gezondheidsverklaring (ouder tekent)',
    'gezondheid_18plus' => 'Gezondheidsverklaring (18+, tekent zelf)',
    'intake'            => 'Intake sollicitant',
    'intake_ouder'      => 'Intake ouder',
    'loonheffing'       => 'Loonheffingsformulier',
    'lidmaatschap'      => 'Inschrijving als lid',
    default             => $t,
};

// Per persoon bundelen: je wilt weten wie er klaar is, niet welk los formulier binnen is.
$per_persoon = [];
foreach ($rijen as $o) {
    $sol = (int)$o['sol_id'];
    if (!isset($per_persoon[$sol])) {
        $per_persoon[$sol] = [
            'naam'   => trim($o['voornaam'] . ' ' . ($o['tussenvoegsel'] ? $o['tussenvoegsel'] . ' ' : '') . $o['achternaam']),
            'docs'   => [], 'klaar' => 0, 'totaal' => 0,
            // Tekent er een ouder mee? Dan is deze sollicitant nog geen 18.
            'ouder'  => false,
        ];
    }
    $per_persoon[$sol]['docs'][] = $o;
    $per_persoon[$sol]['totaal']++;
    if (!empty($o['ondertekend_op'])) $per_persoon[$sol]['klaar']++;
    if (str_ends_with((string)$o['type'], '_ouder')) $per_persoon[$sol]['ouder'] = true;
}

$aantal_compleet = 0;
$aantal_open     = 0;
foreach ($per_persoon as $p) {
    if ($p['klaar'] === $p['totaal']) $aantal_compleet++; else $aantal_open++;
}

// Filter server-side: zonder JS werkt het scherm gewoon, en het harnas kan elke
// stand fotograferen. Standaard "open", want dat is het werk.
$filter = (string)($_GET['filter'] ?? 'open');
if (!in_array($filter, ['alle', 'open', 'compleet'], true)) $filter = 'open';
if ($filter === 'open' && $aantal_open === 0 && $aantal_compleet > 0) $filter = 'alle';

$zichtbaar = array_filter($per_persoon, function ($p) use ($filter) {
    if ($filter === 'alle') return true;
    return $filter === 'compleet' ? $p['klaar'] === $p['totaal'] : $p['klaar'] < $p['totaal'];
});

$mag_bewerken = mag('ondertekeningen', 'bewerken');

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
$tabs_actief = $filter;
$tabs_totaal = count($per_persoon);
$tabs_url    = fn($s) => '?filter=' . urlencode($s);
require __DIR__ . '/_tabs.php';
?>

<?php if (empty($zichtbaar)): ?>
  <?php
  $leeg_titel = $filter === 'compleet' ? 'Nog niemand is helemaal rond' : 'Iedereen heeft getekend';
  $leeg_tekst = $filter === 'compleet'
      ? 'Zodra alle formulieren van iemand terug zijn, staat die persoon hier.'
      : 'Alle ' . $aantal_compleet . ' sollicitanten hebben hun formulieren terug. Er is hier niets meer te doen.';
  $leeg_toon  = $filter === 'compleet' ? 'grijs' : 'groen';
  $leeg_icoon = $filter === 'compleet' ? 'lijst' : 'vink';
  require __DIR__ . '/_leeg.php';
  ?>
<?php else: ?>

<div class="bh-ondkaarten">
  <?php foreach ($zichtbaar as $sol => $p):
    $compleet = $p['klaar'] === $p['totaal'];
  ?>
  <section class="ds-kaart bh-ondkaart<?= $compleet ? ' compleet' : '' ?>">
    <header class="bh-ondkaart-kop">
      <a class="bh-ondkaart-naam" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= (int)$sol ?>&amp;tab=ondertekeningen">
        <?= h($p['naam']) ?>
      </a>
      <?php if ($p['ouder']): ?>
        <span title="Nog geen 18: een ouder of verzorger tekent mee"><?= bh_chip('ouder tekent mee', 'paars') ?></span>
      <?php endif; ?>
      <span class="bh-ondkaart-voortgang"><?= bh_voortgang($p['klaar'], $p['totaal']) ?></span>
      <?= $compleet ? bh_badge('Compleet', 'groen') : bh_badge(($p['totaal'] - $p['klaar']) . ' open', 'oranje') ?>
    </header>

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
          <?= h($type_label($o['type'])) ?>
          <?php if ($klaar): ?>
            <span class="bh-ond-sub">
              <?= h(datum_nl(substr((string)$o['ondertekend_op'], 0, 10))) ?>
              <?php if (!empty($o['ondertekenaar_naam'])): ?> · <?= h((string)$o['ondertekenaar_naam']) ?><?php endif; ?>
            </span>
          <?php endif; ?>
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
  </section>
  <?php endforeach; ?>
</div>

<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

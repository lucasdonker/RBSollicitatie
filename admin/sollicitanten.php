<?php
/**
 * Sollicitantenlijst — het drukste beheerscherm.
 *
 * Opbouw: eerst rechten, dan POST-verwerking, dan pas de layout. Die volgorde is geen
 * smaak: vereis_recht() breekt af met die() en redirect() stuurt een header, en allebei
 * mogen niet nadat de layout al HTML heeft uitgestuurd. Eerder leunde dit op
 * output_buffering in de php.ini.
 *
 * Alle presentatie loopt via de gedeelde bouwstenen (_tabs, _filterbalk, _tabel,
 * _bulkbalk, _paginering). Moet er iets anders: pas de bouwsteen aan, niet dit scherm.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

// Deze lijst toont naam, e-mail, telefoon en geboortedatum. Een begeleider heeft daar
// bewust geen recht op (includes/rechten.php).
vereis_recht('sollicitanten', 'lezen');

$db      = db();
$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;

/* ── POST: bulkacties en losse fase-stappen ──────────────────────
   Alles gaat via de centrale fase-functies, zodat een overgang vanaf de lijst
   precies hetzelfde doet als vanuit het dossier (documenten, pakket, melding). */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));

    // Eén rij: "uitnodigen_tw:42". Zelfde route als de bulk, maar met één id.
    if (!empty($_POST['rij_actie']) && str_contains((string)$_POST['rij_actie'], ':')) {
        $_delen = explode(':', (string)$_POST['rij_actie'], 2);
        $_POST['bulk_actie'] = $_delen[0];
        $ids = [(int)$_delen[1]];
    }

    $actie = (string)($_POST['bulk_actie'] ?? '');

    // Tussenscherm: eerst laten zien wat er gebeurt, dan pas doen. Dat werkt zonder
    // JavaScript en is daardoor ook te screenshotten in het harnas.
    if ($ids && $actie !== '' && empty($_POST['bevestigd'])
        && in_array($actie, ['uitnodigen_selectie', 'uitnodigen_tw', 'aannemen', 'aangenomen', 'afgewezen'], true)) {
        $toon_bevestiging = ['actie' => $actie, 'ids' => $ids];
    }

    if (empty($toon_bevestiging) && $ids && $actie === 'bericht_sturen') {
        redirect(APP_URL . '/admin/berichten.php?ids=' . implode(',', $ids));
    }
    if (empty($toon_bevestiging) && $ids && $actie === 'exporteren') {
        redirect(APP_URL . '/admin/export_blk.php?formaat=excel&ids=' . implode(',', $ids));
    }
    if (empty($toon_bevestiging) && $ids && $actie !== '' && function_exists('fase_zetten_bulk')) {
        vereis_recht('sollicitanten', 'bewerken');
        // Bij meer dan 10 personen gaan de berichten via de wachtrij (cron).
        $bulk = [
            'uitnodigen_selectie' => ['uitgenodigd_selectie', ['aangemeld'],           'uitnodiging(en)'],
            'uitnodigen_tw'       => ['uitgenodigd_tw',       ['selectiedag_geweest'], 'TW-uitnodiging(en)'],
            'aannemen'            => ['aangenomen',           null,                    'welkomstbericht(en)'],
            'aangenomen'          => ['aangenomen',           null,                    'welkomstbericht(en)'],
            'afgewezen'           => ['afgewezen',            null,                    'bericht(en)'],
        ];
        if (isset($bulk[$actie])) {
            $opties = ['seizoen_id' => $sid];
            if ($bulk[$actie][1] !== null) $opties['alleen_van'] = $bulk[$actie][1];
            // Zonder bericht: bij een afwijsronde van twintig man is dat precies het
            // moment waarop je ze liever zelf belt. Het vinkje komt uit _bevestig.php.
            $stil = !empty($_POST['stil']);
            if ($stil) $opties['stil'] = true;
            $t = fase_zetten_bulk($ids, $bulk[$actie][0], 'bulk', $opties);
            flash(($t['mislukt'] || $t['fout']) ? 'waarschuwing' : 'succes',
                  fase_label($bulk[$actie][0]) . ': ' . fase_bulk_tekst($t, $bulk[$actie][2])
                  . ($stil ? ' Er is geen bericht verstuurd.' : ''));
        }
    }
    if (empty($toon_bevestiging)) {
        redirect(APP_URL . '/admin/sollicitanten.php' . (empty($_GET) ? '' : '?' . http_build_query($_GET)));
    }
}

/* ── Filters ─────────────────────────────────────────────────── */
$fase_filter  = (string)($_GET['fase']  ?? 'alle');
$filter_terug = (string)($_GET['terug'] ?? '');
$filter_blk   = (string)($_GET['blk']   ?? '');
$filter_comp  = (string)($_GET['comp']  ?? '');
$zoek         = trim((string)($_GET['q'] ?? ''));

/** Bouwt een URL met de huidige filters, met één ding vervangen. */
function soll_url(array $vervang = []): string {
    $p = array_filter(array_merge([
        'fase'  => $_GET['fase']  ?? null, 'q'    => $_GET['q']    ?? null,
        'terug' => $_GET['terug'] ?? null, 'blk'  => $_GET['blk']  ?? null,
        'comp'  => $_GET['comp']  ?? null, 'sort' => $_GET['sort'] ?? null,
        'richting' => $_GET['richting'] ?? null, 'per' => $_GET['per'] ?? null,
        'pagina'   => $_GET['pagina']   ?? null,
    ], $vervang), fn($v) => $v !== null && $v !== '');
    return '?' . http_build_query($p);
}

// Tellingen per fase — één query, ook de basis voor de tabs.
$ant  = [];
$stmt = $db->prepare("SELECT fase, COUNT(*) n FROM sollicitaties WHERE seizoen_id=? GROUP BY fase");
$stmt->execute([$sid]);
foreach ($stmt->fetchAll() as $r) $ant[$r['fase']] = (int)$r['n'];
$totaal_alle = array_sum($ant);

// De WHERE komt uit includes/beheer/lijst.php, want de dossierpagina moet dezelfde
// selectie kennen voor de ← →-navigatie. Zou dit scherm zijn eigen filter bouwen, dan
// wijken die twee bij de eerste wijziging van elkaar af.
$_f     = bh_soll_filter($_GET, $sid);
$where  = $_f['where'];
$params = $_f['params'];

/* ── Kolommen ──────────────────────────────────────────────────
   De vaste kolommen hieronder, plus alles uit het kennismakingsformulier als
   optionele kolom. Die laatste komen uit blk_kolom_velden() — één bron, zodat de
   kiezer, de SELECT en de cel niet uit elkaar kunnen lopen. Ze staan standaard uit:
   twaalf extra kolommen aanzetten is een keuze, geen beginsituatie. */
$blk_velden = blk_kolom_velden();
$blk_kolommen = [];
foreach ($blk_velden as $_sleutel => $_v) {
    $blk_kolommen[$_sleutel] = [
        'kop'       => $_v['kop'],
        'track'     => $_v['track'],
        'mobiel'    => 'verberg',          // op telefoon is er geen ruimte voor
        'optioneel' => true,
        'standaard' => false,
        'sorteer'   => $_v['sorteer'] ?? null,
    ] + (isset($_v['recht']) ? ['recht' => $_v['recht']] : []);
}

$tabel = bh_kolommen('sollicitanten', [
    'naam'       => ['kop' => 'Naam',       'track' => 'minmax(165px,2.2fr)', 'sorteer' => 'p.achternaam',
                     'mobiel' => 'titel'],
    'leeftijd'   => ['kop' => 'Lft',        'track' => '52px',  'uitlijn' => 'rechts', 'mobiel' => 'verberg',
                     'optioneel' => true, 'standaard' => true, 'sorteer' => 'p.geboortedatum'],
    // Op telefoon blijft er weinig over: naam, status, aangemeld, actie. De rest zou
    // het kaartje een scherm lang maken zonder dat je er iets aan hebt.
    'woonplaats' => ['kop' => 'Woonplaats', 'track' => 'minmax(80px,1fr)', 'mobiel' => 'verberg',
                     'optioneel' => true, 'standaard' => true],
    'telefoon'   => ['kop' => 'Telefoon',   'track' => '125px', 'mobiel' => 'verberg',
                     'optioneel' => true, 'standaard' => false],
] + $blk_kolommen + [
    'status'     => ['kop' => 'Status',     'track' => 'minmax(185px,1.6fr)'],
    'aangemeld'  => ['kop' => 'Aangemeld',  'track' => '104px', 'sorteer' => 's.aangemeld_op'],
    // Zonder bewerkrecht valt de hele kolom weg in plaats van leeg te blijven staan.
    'actie'      => ['kop' => 'Actie',      'track' => '250px', 'uitlijn' => 'rechts',
                     'recht' => ['sollicitanten', 'bewerken']],
], ['sort' => $_GET['sort'] ?? null, 'richting' => $_GET['richting'] ?? null]);

/* ── Paginering ──────────────────────────────────────────────── */
$per = (int)($_GET['per'] ?? 25);
if (!in_array($per, [15, 25, 50, 100], true)) $per = 25;

$q_tot = $db->prepare("SELECT COUNT(*) FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE $where");
$q_tot->execute($params);
$totaal = (int)$q_tot->fetchColumn();

$paginas = max(1, (int)ceil($totaal / $per));
$pagina  = min(max(1, (int)($_GET['pagina'] ?? 1)), $paginas);
$offset  = ($pagina - 1) * $per;

// ORDER BY komt uit de whitelist in de kolomdefinitie, nooit rechtstreeks uit $_GET.
$order = bh_order_by($tabel, 's.aangemeld_op DESC');

/* Alleen de BLK-velden ophalen die ook echt aanstaan: twaalf TEXT-kolommen voor
   honderd rijen meeslepen terwijl er niet één wordt getoond is zonde. De sleutels
   komen uit blk_kolom_velden() en niet uit $_GET, dus er kan niets vreemds in de
   SELECT belanden. `b.adres` gaat altijd mee — dat is de terugval voor woonplaats. */
$extra_sql = '';
foreach ($tabel['kolommen'] as $_sleutel => $_k) {
    if (!$_k['aan'] || !isset($blk_velden[$_sleutel])) continue;
    $extra_sql .= ', ' . $blk_velden[$_sleutel]['sql'] . ' AS `' . $_sleutel . '`';
}

$stmt = $db->prepare("
    SELECT s.id, s.fase, s.terugkerend, s.aangemeld_op, s.persoon_id,
           p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon, p.geboortedatum,
           b.foto_url, b.woonplaats, b.adres, (b.id IS NOT NULL) AS heeft_blk
           $extra_sql
    FROM sollicitaties s
    JOIN personen p ON p.id=s.persoon_id
    LEFT JOIN blk_formulieren b ON b.sollicitatie_id=s.id
    WHERE $where ORDER BY $order
    LIMIT $per OFFSET $offset
");
$stmt->execute($params);
$soll = $stmt->fetchAll();

$seizoen_fase = bh_seizoen_fase($seizoen);
$heeft_filter = $_f['actief'];

$pagina_titel = 'Sollicitanten';
$actieve_nav  = 'sollicitanten';
/* De leesbreedte van 1400px is er voor schermen met tekst; een lijst is dat niet. Hij
   liet rechts een strook wit staan terwijl de kolommen werden samengeperst, en zodra
   je er formuliervelden bij aanzette ging de tabel binnen die 1400px schuiven terwijl
   er een half scherm leeg was. */
$pagina_breed = true;
require __DIR__ . '/layout_top.php';
?>

<?php if (!empty($toon_bevestiging)):
    // Tussenscherm in plaats van de lijst: eerst zien wat er gebeurt.
    $_bnamen = [];
    $_bq = $db->prepare("SELECT p.voornaam, p.tussenvoegsel, p.achternaam
                         FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id
                         WHERE s.id IN (" . implode(',', array_fill(0, count($toon_bevestiging['ids']), '?')) . ")");
    $_bq->execute($toon_bevestiging['ids']);
    foreach ($_bq->fetchAll() as $_r) $_bnamen[] = volledige_naam($_r);

    $bev       = bh_gevolgen($toon_bevestiging['actie'], $toon_bevestiging['ids']);
    $bev_form  = ['actie' => soll_url(),
                  'velden' => ['bulk_actie' => $toon_bevestiging['actie'],
                               'bevestigd'  => '1',
                               'ids'        => $toon_bevestiging['ids']]];
    $bev_terug = soll_url();
    $bev_namen = $_bnamen;
    // Hetzelfde vinkje als bij een losse fasewijziging in het dossier. Juist bij een
    // bulkactie telt het: dat is het moment waarop je twintig mensen tegelijk raakt.
    $bev_vink  = ['naam' => 'stil', 'label' => 'Zonder bericht doorvoeren',
                  'hulp' => 'De fase verandert wel, maar er gaat niets de deur uit. '
                          . 'Gebruik dit als je ze zelf belt of het al besproken is.'];
    require __DIR__ . '/_bevestig.php';
    require __DIR__ . '/layout_bottom.php';
    return;
endif; ?>

<?php
  $tabs_data   = bh_fase_tabs($ant, $seizoen_fase);
  $tabs_actief = $fase_filter;
  $tabs_totaal = $totaal_alle;
  $tabs_url    = fn(string $s) => soll_url(['fase' => $s === 'alle' ? null : $s, 'pagina' => null]);
  require __DIR__ . '/_tabs.php';
?>

<?php
  $filter_velden = [
      ['soort' => 'zoek', 'naam' => 'q', 'label' => 'Zoek naam of e-mail', 'waarde' => $zoek, 'breed' => true],
      ['soort' => 'keuze', 'naam' => 'terug', 'label' => 'Terugkerend', 'waarde' => $filter_terug,
       'opties' => ['' => 'Alle kandidaten', '1' => 'Alleen terugkerend']],
      ['soort' => 'keuze', 'naam' => 'blk', 'label' => 'Kennismakingsformulier', 'waarde' => $filter_blk,
       'opties' => ['' => 'Formulier: alle', 'ja' => 'Formulier ingevuld', 'nee' => 'Formulier open']],
  ];
  if (function_exists('competentie_sync_klaar') && competentie_sync_klaar()) {
      $_opties = ['' => 'Competentie: alle'];
      foreach (competentie_mapping() as $_cat => $_def) $_opties[$_cat] = $_def['label'];
      $filter_velden[] = ['soort' => 'keuze', 'naam' => 'comp', 'label' => 'Competentie',
                          'waarde' => $filter_comp, 'opties' => $_opties];
  }
  $filter_verborgen = array_filter(['fase' => $fase_filter === 'alle' ? null : $fase_filter,
                                    'sort' => $tabel['sort'], 'per' => $per !== 25 ? $per : null]);
  $filter_acties = [];
  if (mag('sollicitanten', 'bewerken')) {
      $filter_acties[] = ['label' => 'Toevoegen', 'icoon' => 'plus', 'toon' => 'primair',
                          'url' => APP_URL . '/admin/sollicitant_nieuw.php'];
  }
  if ($sid) {
      $filter_acties[] = ['label' => 'Exporteren', 'icoon' => 'export', 'toon' => 'secundair',
                          'url' => APP_URL . '/admin/export_blk.php?formaat=excel&seizoen=' . $sid];
  }
  $filter_wissen = $heeft_filter ? APP_URL . '/admin/sollicitanten.php' : null;
  require __DIR__ . '/_filterbalk.php';
?>

<div class="bh-tabelbalk">
  <?php $kk_tabel = $tabel; require __DIR__ . '/_kolomkiezer.php'; ?>
</div>

<form method="post" id="sollForm">
<?= csrf_input() ?>

<?php
  $tabel_rijen    = $soll;
  $tabel_rij      = __DIR__ . '/_rij_sollicitant.php';
  $tabel_selectie = mag('sollicitanten', 'bewerken');
  $tabel_rij_cls  = fn(array $r) => ($r['fase'] === 'afgewezen' ? 'bh-rij-gedempt' : '');
  $tabel_rij_data = fn(array $r) => ['fase' => $r['fase']];   // de bulkbalk leest dit
  $tabel_leeg     = $heeft_filter
      ? ['titel' => 'Niemand gevonden', 'tekst' => 'Geen kandidaat voldoet aan deze filters. Wis ze om iedereen te zien.']
      : ['titel' => 'Nog geen aanmeldingen', 'tekst' => 'Zodra iemand zich via een wervingspagina aanmeldt, staat hij hier.'];
  require __DIR__ . '/_tabel.php';
?>

<?php
  // Elke knop die bericht stuurt is gemarkeerd; de bevestiging zegt daarna naar
  // hoeveel mensen er iets uitgaat.
  $bulk_acties = [
      ['actie' => 'uitnodigen_selectie', 'label' => 'Uitnodigen selectiedag', 'toon' => 'primair',
       'mail' => true, 'alleen_fases' => ['aangemeld'], 'recht' => ['sollicitanten', 'bewerken']],
      ['actie' => 'uitnodigen_tw', 'label' => 'Uitnodigen trainingsweekend', 'toon' => 'primair',
       'mail' => true, 'alleen_fases' => ['selectiedag_geweest'], 'recht' => ['sollicitanten', 'bewerken']],
      ['actie' => 'aannemen', 'label' => 'Aannemen', 'mail' => true, 'recht' => ['sollicitanten', 'bewerken']],
      ['actie' => 'afgewezen', 'label' => 'Afwijzen', 'toon' => 'gevaar', 'mail' => false,
       'recht' => ['sollicitanten', 'bewerken']],
      ['actie' => 'bericht_sturen', 'label' => 'Bericht', 'mail' => false, 'recht' => ['mailing', 'lezen']],
      ['actie' => 'exporteren', 'label' => 'Exporteren', 'mail' => false],
  ];
  $bulk_voet = 'Elke actie vraagt bevestiging en meldt of er bericht uitgaat';
  require __DIR__ . '/_bulkbalk.php';
?>
</form>

<?php
  $pag_totaal  = $totaal;
  $pag_pagina  = $pagina;
  $pag_per     = $per;
  $pag_url     = fn(int $p) => soll_url(['pagina' => $p > 1 ? $p : null]);
  $pag_per_url = fn(int $n) => soll_url(['per' => $n !== 25 ? $n : null, 'pagina' => null]);
  require __DIR__ . '/_paginering.php';
?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

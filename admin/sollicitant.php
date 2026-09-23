<?php
/**
 * Het dossier van één sollicitant — een volledige pagina op een eigen adres.
 *
 * Bewust geen uitschuifpaneel (ontwerpfilosofie §5): deelbaar, bookmarkbaar, de
 * terugknop werkt, en je raakt de context niet kwijt achter een overlay. Binnen de
 * pagina loop je met ← → door de lijst waar je vandaan kwam, met de filtertekst erbij.
 *
 * Het eerste tabblad geeft het complete beeld dat je nodig hebt om te beslissen; de
 * zware onderdelen (formulier, beschikbaarheidsraster, beoordelingen) zitten achter
 * een tabblad.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

vereis_recht('sollicitanten', 'lezen');

$id = (int)($_GET['id'] ?? 0);

/* ── POST: de hoofdactie uit de dossierkop ───────────────────────
   Vóór de layout, want redirect() stuurt een header. Loopt via dezelfde
   fase-functie als de lijst en de bulkbalk. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['fase_actie'])) {
    csrf_check();
    vereis_recht('sollicitanten', 'bewerken');
    $_d0 = bh_dossier((int)($_POST['id'] ?? 0));
    if ($_d0) {
        $_a = bh_fase_actie((string)$_d0['sol']['fase']);
        if ($_a && $_a['actie'] === $_POST['fase_actie'] && function_exists('fase_zetten')) {
            $_r = fase_zetten((int)$_d0['sol']['id'], $_a['naar'], 'paneel',
                              ['seizoen_id' => (int)$_d0['sol']['seizoen_id']]);
            if (!$_r['ok']) {
                flash('fout', 'Fase wijzigen mislukt: ' . ($_r['fout'] ?? 'onbekend'));
            } else {
                $_mt = function_exists('fase_melding_tekst') ? fase_melding_tekst($_r) : '';
                flash('succes', 'Fase bijgewerkt naar ' . fase_label($_a['naar']) . '.' . ($_mt ? ' ' . $_mt : ''));
            }
        }
    }
    redirect(APP_URL . '/admin/sollicitant.php?' . http_build_query(
        array_merge(bh_soll_filter_params($_GET), ['id' => (int)($_POST['id'] ?? 0)])));
}

$d = bh_dossier($id);
if (!$d) {
    http_response_code(404);
    $pagina_titel = 'Niet gevonden';
    $actieve_nav  = 'sollicitanten';
    require __DIR__ . '/layout_top.php';
    echo '<div class="ds-kaart" style="max-width:520px;margin:48px auto;padding:8px">';
    $leeg_titel = 'Deze sollicitant bestaat niet';
    $leeg_tekst = 'De link klopt niet meer, of het dossier is verwijderd.';
    $leeg_icoon = 'zoek';
    $leeg_knop  = ['tekst' => 'Terug naar de lijst', 'url' => APP_URL . '/admin/sollicitanten.php'];
    require __DIR__ . '/_leeg.php';
    echo '</div>';
    require __DIR__ . '/layout_bottom.php';
    exit;
}

$sol     = $d['sol'];
$seizoen = actief_seizoen();
$sid     = (int)($sol['seizoen_id'] ?? 0);
$naam    = volledige_naam($sol);

$tab    = (string)($_GET['tab'] ?? 'dossier');
$tabs   = bh_dossier_tabs($d, $tab);
if (!in_array($tab, array_column($tabs, 'sleutel'), true)) $tab = 'dossier';

$buren  = bh_soll_buren($id, $_GET, $sid);
$filter = bh_soll_filter_params($_GET);
$terug  = APP_URL . '/admin/sollicitanten.php' . ($filter ? '?' . http_build_query($filter) : '');

/** URL naar hetzelfde dossier, met een ander tabblad of een andere persoon. */
function dossier_url(int $sol_id, string $tab = 'dossier'): string {
    $p = bh_soll_filter_params($_GET);
    $p['id'] = $sol_id;
    if ($tab !== 'dossier') $p['tab'] = $tab;
    return APP_URL . '/admin/sollicitant.php?' . http_build_query($p);
}

$pagina_titel = $naam;
$actieve_nav  = 'sollicitant';   // eigen hulptekst; bh_nav_actief() laat Sollicitanten oplichten
$pagina_breed = true;
require __DIR__ . '/layout_top.php';
?>

<nav class="bh-kruimels" aria-label="Kruimelpad">
  <a href="<?= h($terug) ?>">Sollicitanten</a>
  <span aria-hidden="true">/</span>
  <span class="bh-kruimel-nu"><?= h($naam) ?></span>

  <?php if ($buren['totaal'] > 1): ?>
    <span class="bh-kruimel-positie">
      <?= (int)$buren['positie'] ?> van <?= (int)$buren['totaal'] ?>
      <?php if ($buren['omschrijving'] !== 'alle kandidaten'): ?>
        in <?= h($buren['omschrijving']) ?>
      <?php endif; ?>
    </span>
    <span class="bh-kruimel-pijlen">
      <?php if ($buren['vorige']): ?>
        <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h(dossier_url((int)$buren['vorige'], $tab)) ?>"
           rel="prev" aria-label="Vorige kandidaat">← Vorige</a>
      <?php else: ?>
        <span class="ds-btn ds-btn-zacht ds-btn-sm" aria-disabled="true">← Vorige</span>
      <?php endif; ?>
      <?php if ($buren['volgende']): ?>
        <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h(dossier_url((int)$buren['volgende'], $tab)) ?>"
           rel="next" aria-label="Volgende kandidaat">Volgende →</a>
      <?php else: ?>
        <span class="ds-btn ds-btn-zacht ds-btn-sm" aria-disabled="true">Volgende →</span>
      <?php endif; ?>
    </span>
  <?php endif; ?>
</nav>

<?php $kop_dossier = $d; require __DIR__ . '/dossier/_kop.php'; ?>

<div class="bh-dossiertabs" role="tablist">
  <?php foreach ($tabs as $_t): ?>
    <a class="bh-dossiertab<?= $_t['actief'] ? ' actief' : '' ?>"
       href="<?= h(dossier_url($id, $_t['sleutel'])) ?>" role="tab"
       aria-selected="<?= $_t['actief'] ? 'true' : 'false' ?>">
      <?= h($_t['label']) ?>
      <?php if ($_t['aantal'] !== null): ?><span class="bh-dossiertab-getal"><?= h((string)$_t['aantal']) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php
$_bestand = __DIR__ . '/dossier/_tab_' . preg_replace('/[^a-z]/', '', $tab) . '.php';
if (is_file($_bestand)) {
    $tab_dossier = $d;
    require $_bestand;
} else {
    echo '<div class="ds-kaart ds-kaart-p">';
    $leeg_titel = 'Dit tabblad komt nog';
    $leeg_tekst = 'Deze sectie wordt in een volgende stap omgebouwd.';
    require __DIR__ . '/_leeg.php';
    echo '</div>';
}
?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

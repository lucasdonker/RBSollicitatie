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

/* ── POST: hoofdactie uit de kop en verwijderen ──────────────────
   Vóór de layout, want redirect() stuurt een header. Allebei eerst via het
   tussenscherm (admin/_bevestig.php), net als in de lijst en de bulkbalk: dat werkt
   zonder JavaScript en zegt vóóraf wat er gebeurt. Pas met bevestigd=1 gebeurt het. */
$toon_bevestiging = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $_pid = (int)($_POST['id'] ?? 0);
    $_d0  = bh_dossier($_pid);
    $_hier = APP_URL . '/admin/sollicitant.php?' . http_build_query(
        array_merge(bh_soll_filter_params($_GET), ['id' => $_pid]));

    if ($_d0 && !empty($_POST['fase_actie'])) {
        vereis_recht('sollicitanten', 'bewerken');
        // Dezelfde fase-functie als de lijst en de bulkbalk.
        $_a = bh_fase_actie((string)$_d0['sol']['fase']);
        if ($_a && $_a['actie'] === $_POST['fase_actie']) {
            if (empty($_POST['bevestigd'])) {
                $toon_bevestiging = ['soort' => 'fase', 'actie' => $_a['actie'], 'id' => $_pid];
            } elseif (function_exists('fase_zetten')) {
                $_stil = !empty($_POST['stil']);
                $_r = fase_zetten($_pid, $_a['naar'], 'paneel',
                                  ['seizoen_id' => (int)$_d0['sol']['seizoen_id'], 'stil' => $_stil]);
                if (!$_r['ok']) {
                    flash('fout', 'Fase wijzigen mislukt: ' . ($_r['fout'] ?? 'onbekend'));
                } else {
                    $_mt = $_stil ? 'Er is geen bericht verstuurd.'
                                  : (function_exists('fase_melding_tekst') ? fase_melding_tekst($_r) : '');
                    flash('succes', 'Fase bijgewerkt naar ' . fase_label($_a['naar']) . '.' . ($_mt ? ' ' . $_mt : ''));
                }
            }
        }
    } elseif ($_d0 && !empty($_POST['verwijder'])) {
        vereis_recht('sollicitanten', 'verwijderen');
        $_omvang = $_POST['verwijder'] === 'persoon' ? 'persoon' : 'sollicitatie';
        // Het loondossier mag alleen mee met het recht om loondossiers te verwijderen.
        $_mag_loon = mag('documenten', 'verwijderen');
        $_ids = bh_verwijder_ids($_pid, $_omvang);
        $_verwerkt = bh_loon_verwerkt($_ids);
        if (empty($_POST['bevestigd'])) {
            if ($_verwerkt && !$_mag_loon) {
                // Zonder dat recht kan het niet; dan ook geen tussenscherm met een knop die toch weigert.
                flash('fout', 'Er ligt een verwerkt loondossier (wettelijke bewaarplicht). Verwijderen kan alleen '
                            . 'iemand met het recht Loondossiers verwijderen, met de verklaring dat het dienstverband '
                            . 'niet is doorgegaan.');
            } else {
                $toon_bevestiging = ['soort' => 'verwijder', 'omvang' => $_omvang, 'id' => $_pid,
                                     'aantal' => count($_ids), 'verwerkt' => $_verwerkt];
            }
        } else {
            $_r = bh_dossier_verwijderen($_pid, $_omvang, $_mag_loon && !empty($_POST['ook_loondocs']));
            if ($_r['ok']) {
                flash('succes', $_r['tekst']);
                redirect(APP_URL . '/admin/sollicitanten.php');
            }
            flash('fout', (string)$_r['fout']);
        }
    }
    if (!$toon_bevestiging) redirect($_hier);
}

$d = bh_dossier($id);
if (!$d) {
    http_response_code(404);
    $pagina_titel = 'Niet gevonden';
    $actieve_nav  = 'sollicitanten';
    require __DIR__ . '/layout_top.php';
    echo '<div class="ds-kaart bh-losse-kaart">';
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
$tab    = ['inzet' => 'beschikbaarheid'][$tab] ?? $tab;   // 2.122.0: Inzet zit in Beschikbaarheid & inzet
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

/* Tussenscherm in plaats van het dossier: eerst zien wat er gebeurt. */
if ($toon_bevestiging):
    $_bid = (int)$toon_bevestiging['id'];
    $_bd  = bh_dossier($_bid);
    $bev_form = ['actie' => $_hier, 'velden' => ['id' => $_bid, 'bevestigd' => '1']];
    if ($toon_bevestiging['soort'] === 'fase') {
        $bev = bh_gevolgen($toon_bevestiging['actie'], [$_bid]);
        $bev_form['velden']['fase_actie'] = $toon_bevestiging['actie'];
        $bev_vink = bh_bevestig_stil_vink();
    } else {
        $bev = bh_gevolgen_verwijderen($toon_bevestiging['omvang'], (int)$toon_bevestiging['aantal'],
                                       (bool)$toon_bevestiging['verwerkt']);
        $bev_form['velden']['verwijder'] = $toon_bevestiging['omvang'];
        // Alleen bij een verwerkt loondossier is er iets te kiezen; het recht is al getoetst.
        $bev_vink = $toon_bevestiging['verwerkt'] ? bh_bevestig_loon_vink() : null;
    }
    $bev_terug = dossier_url($_bid);
    $bev_namen = $_bd ? [volledige_naam($_bd['sol'])] : [];
    require __DIR__ . '/_bevestig.php';
    require __DIR__ . '/layout_bottom.php';
    return;
endif;
?>

<nav class="bh-kruimels" aria-label="Kruimelpad">
  <a href="<?= h($terug) ?>">Sollicitanten</a>
  <span aria-hidden="true">/</span>
  <span class="bh-kruimel-nu"><?= h($naam) ?></span>

  <?php if ($buren['totaal'] > 1): ?>
    <span class="bh-kruimel-positie">
      <?= (int)$buren['positie'] ?> van <?= (int)$buren['totaal'] ?>
      <?php if (!in_array($buren['omschrijving'], ['alle sollicitanten', 'alle kandidaten'], true)): ?>
        in <?= h($buren['omschrijving']) ?>
      <?php endif; ?>
    </span>
    <span class="bh-kruimel-pijlen">
      <?php if ($buren['vorige']): ?>
        <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h(dossier_url((int)$buren['vorige'], $tab)) ?>"
           rel="prev" aria-label="Vorige sollicitant"><?= icoon('terug', 14) ?> Vorige</a>
      <?php else: ?>
        <span class="ds-btn ds-btn-zacht ds-btn-sm" aria-disabled="true"><?= icoon('terug', 14) ?> Vorige</span>
      <?php endif; ?>
      <?php if ($buren['volgende']): ?>
        <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h(dossier_url((int)$buren['volgende'], $tab)) ?>"
           rel="next" aria-label="Volgende sollicitant">Volgende <?= icoon('pijl-rechts', 14) ?></a>
      <?php else: ?>
        <span class="ds-btn ds-btn-zacht ds-btn-sm" aria-disabled="true">Volgende <?= icoon('pijl-rechts', 14) ?></span>
      <?php endif; ?>
    </span>
  <?php endif; ?>
</nav>

<?php $kop_dossier = $d; require __DIR__ . '/dossier/_kop.php'; ?>

<nav class="bh-dossiertabs" aria-label="Onderdelen van het dossier">
  <?php foreach ($tabs as $_t): ?>
    <a class="bh-dossiertab<?= $_t['actief'] ? ' actief' : '' ?>"
       href="<?= h(dossier_url($id, $_t['sleutel'])) ?>"<?= $_t['actief'] ? ' aria-current="page"' : '' ?>>
      <?= h($_t['label']) ?>
      <?php if ($_t['aantal'] !== null): ?><span class="bh-dossiertab-getal"><?= h((string)$_t['aantal']) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

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

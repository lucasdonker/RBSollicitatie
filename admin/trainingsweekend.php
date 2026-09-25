<?php
/**
 * Beheer — Trainingsweekend. Vier stappen, zelfde opbouw als de selectiedag:
 *
 *   voorbereiden — wie komt er (bevestiging), wie mist nog beschikbaarheid, de tablet     (lezen; wijzigen = bestuur)
 *   groepen      — groepsindeling, gedeeld met de selectiedag (admin/_groepsindeling.php) (lezen; wijzigen = bestuur)
 *   inchecken    — gedeelde incheck-lijst; de tablet doet hetzelfde                       (lezen)
 *   beoordelen   — vrijwilligers op hun telefoon: observaties per persoon, met foto;
 *                  twijfelgevallen van het bestuur bovenaan ("extra aandacht")             (lezen)
 *   evaluatie    — het bestuur zegt alvast sowieso aannemen / twijfel / sowieso afwijzen   (bewerken)
 *   gesprekken   — het bestuur spreekt iedereen, in vrije volgorde, en beslist ter plekke (bewerken)
 *
 * Dit bestand is alleen de router: rechten, POST-acties en welke stap. De stappen staan in
 * admin/tw/*.php; de logica in includes/trainingsweekend.php en includes/evenement.php.
 *
 * Aannemen of afwijzen in het gesprek gaat meteen door (fase + bericht): aangenomen heeft
 * daarna direct de formulieren in het portaal. Twijfel blijft staan voor na het weekend.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

$pagina_titel = 'Trainingsweekend';
$actieve_nav  = 'trainingsweekend';
vereis_recht('trainingsweekend', 'lezen');

$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;
$ik      = (string)($_SESSION['bhd_naam'] ?? 'onbekend');
$ik_id   = (int)($_SESSION['bhd_id'] ?? 0);

// lezen = meedraaien (inchecken, observeren: begeleiderswerk), bewerken = bestuur.
$mag_meedraaien = mag('trainingsweekend', 'lezen');
$mag_beslissen  = mag('trainingsweekend', 'bewerken');
// Dataminimalisatie: BLK-bijzonderheden (vaak medisch) alleen met BLK-recht,
// dossier en telefoonnummer alleen met dossierrecht.
$mag_blk     = mag('blk', 'lezen');
$mag_dossier = mag('sollicitanten', 'lezen');

/** URL naar dit scherm, met een stap, eventueel een persoon en extra parameters (bijv. groep). */
function tw_url(string $stap, ?int $k = null, array $extra = []): string {
    $p = ['stap' => $stap] + array_filter($extra, fn($v) => $v !== '' && $v !== null);
    if ($k) $p['k'] = $k;
    return APP_URL . '/admin/trainingsweekend.php?' . http_build_query($p);
}

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    $actie = (string)($_POST['actie'] ?? '');
    $sol   = (int)($_POST['sol_id'] ?? 0);
    $json  = in_array($actie, ['observatie', 'observatie_wis'], true);
    if ($json) header('Content-Type: application/json; charset=utf-8');
    csrf_check();

    if ($json) {
        if ($actie === 'observatie') {
            // Alleen wie nog in het weekend zit; na het besluit is het dossier de plek.
            echo json_encode(['ok' => evenement_is_kandidaat('tw', $sid, $sol) && tw_observatie_toevoegen($sid, $sol, (string)($_POST['tekst'] ?? ''),
                                                 (string)($_POST['soort'] ?? 'neutraal'), $ik, $ik_id)]);
        } else {
            echo json_encode(['ok' => tw_observatie_wissen((int)($_POST['id'] ?? 0), $ik, $ik_id)]);
        }
        exit;
    }

    if (!$mag_beslissen) { http_response_code(403); exit('Dit is bestuurswerk.'); }

    // Groepsindeling (admin/tw/groepen.php): zelfde handelingen als op de selectiedag.
    bh_groepsindeling_post('tw', $sid, tw_kandidaten($sid), tw_url('groepen'));

    if ($actie === 'bevestiging') {
        $ok = evenement_is_kandidaat('tw', $sid, $sol)
           && evenement_bevestigen('tw', $sid, $sol, (string)($_POST['stand'] ?? ''), (string)($_POST['reden'] ?? ''), 'beheer');
        flash($ok ? 'succes' : 'fout', $ok ? 'Opgeslagen.' : 'Niet opgeslagen: bij "kan niet" hoort een reden.');
        redirect(tw_url('voorbereiden'));
    }
    if ($actie === 'evaluatie') {
        $ok = evenement_is_kandidaat('tw', $sid, $sol)
           && tw_evaluatie_zetten($sid, $sol, (string)($_POST['evaluatie'] ?? ''), (string)($_POST['evaluatie_bericht'] ?? ''), $ik);
        flash($ok ? 'succes' : 'fout', $ok ? 'Evaluatie opgeslagen.' : 'Niet opgeslagen: deze persoon staat niet (meer) op de lijst.');
        redirect(tw_url('evaluatie', null, ['toon' => (string)($_POST['toon'] ?? '')]) . '#ev-' . $sol);
    }
    if ($actie === 'gesprek_los') {
        evenement_claim_los('tw_deelname', 'gesprek', $sid, $sol, $ik_id);
        redirect(tw_url('gesprekken'));
    }
    if ($actie === 'gesprek') {
        // Alleen wie nog in het weekend zit (uitgenodigd_tw / tw_geweest); na doorvoeren is het klaar.
        if (!evenement_is_kandidaat('tw', $sid, $sol)) {
            flash('fout', 'Deze persoon staat niet (meer) op de lijst van het trainingsweekend.');
            redirect(tw_url('gesprekken'));
        }
        $claim = evenement_claim('tw_deelname', 'gesprek', $sid, $sol, $ik_id);
        if (!$claim['ok']) {
            flash('fout', 'Niet opgeslagen: ' . evenement_claim_naam($claim['door']) . ' voert dit gesprek.');
            redirect(tw_url('gesprekken'));
        }
        $r = tw_gesprek_afronden($sid, $sol, (string)($_POST['besluit'] ?? ''), (string)($_POST['notitie'] ?? ''),
                                 (string)($_POST['bericht'] ?? ''), $ik, $ik_id);
        flash($r['ok'] ? 'succes' : 'fout', $r['melding']);
        redirect($r['ok'] ? tw_url('gesprekken') : tw_url('gesprekken', $sol));
    }
    http_response_code(400); exit('Onbekende actie.');
}

// ── Welke stap ──
$stappen = ['voorbereiden' => 'Voorbereiden', 'groepen' => 'Groepen', 'inchecken' => 'Inchecken', 'beoordelen' => 'Beoordelen'];
if ($mag_beslissen) $stappen += ['evaluatie' => 'Evaluatie', 'gesprekken' => 'Gesprekken'];
$stap = (string)($_GET['stap'] ?? '');
$stap = ['weekend' => 'beoordelen', 'besluit' => 'gesprekken'][$stap] ?? $stap;   // oude links
if (!isset($stappen[$stap])) {
    $begin = (string)($seizoen['datum_tw_start'] ?? '');
    $stap  = ($begin !== '' && date('Y-m-d') < $begin) ? 'voorbereiden' : 'beoordelen';
}

$kandidaten = $sid ? tw_kandidaten($sid) : [];

// "3 – 5 mei 2026" als het in dezelfde maand valt, anders "29 apr – 2 mei 2026".
$tw_periode = '';
if ($seizoen && !empty($seizoen['datum_tw_start'])) {
    $_van = (string)$seizoen['datum_tw_start'];
    $_tot = (string)($seizoen['datum_tw_eind'] ?? '');
    if ($_tot === '' || $_tot === $_van)                     $tw_periode = datum_nl($_van);
    elseif (substr($_van, 0, 7) === substr($_tot, 0, 7))    $tw_periode = (int)substr($_van, 8, 2) . ' – ' . datum_nl($_tot);
    else                                                     $tw_periode = datum_kort_nl($_van) . ' – ' . datum_nl($_tot);
}

require __DIR__ . '/layout_top.php';
?>

<div class="bh-selectiekop">
  <div class="bh-selectiekop-titel">
    <h2>Trainingsweekend</h2>
    <?php if ($tw_periode !== ''): ?><span class="bh-meta"><?= h($tw_periode) ?></span><?php endif; ?>
  </div>
  <nav class="bh-substappen" aria-label="Stappen van het trainingsweekend">
    <?php foreach ($stappen as $_s => $_l): ?>
      <a class="bh-substap<?= $stap === $_s ? ' actief' : '' ?>" href="<?= h(tw_url($_s)) ?>"<?= $stap === $_s ? ' aria-current="page"' : '' ?>><?= h($_l) ?></a>
    <?php endforeach; ?>
  </nav>
</div>

<?php if (!$sid || (!$kandidaten && $stap !== 'voorbereiden')): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php
    $leeg_titel = !$sid ? 'Geen actief seizoen' : 'Nog niemand uitgenodigd';
    $leeg_tekst = !$sid
        ? 'Maak eerst een seizoen aan bij Seizoenen.'
        : 'Zodra je na de selectiedag mensen uitnodigt voor het trainingsweekend, staan ze hier.';
    $leeg_icoon = 'reddingsboei';
    require __DIR__ . '/_leeg.php';
    ?>
  </div>
<?php elseif ($stap === 'gesprekken' && (int)($_GET['k'] ?? 0) > 0): ?>
  <?php require __DIR__ . '/tw/gesprek.php'; ?>
<?php else: ?>
  <?php require __DIR__ . '/tw/' . $stap . '.php'; ?>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

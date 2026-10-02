<?php
/**
 * Beheer — Trainingsweekend. Zeven stappen, zelfde opbouw als de selectiedag. Welke je ziet, hangt af
 * van je taken (includes/modules/trainingsweekend.php); een begeleider heeft standaard inchecken en
 * beoordelen.
 *
 *   voorbereiden — wie komt er (bevestiging), wie mist nog beschikbaarheid, de tablet     (voorbereiden)
 *   groepen      — groepsindeling, gedeeld met de selectiedag (admin/_groepsindeling.php) (voorbereiden)
 *   inchecken    — gedeelde incheck-lijst; de tablet doet hetzelfde                       (inchecken)
 *   beoordelen   — vrijwilligers op hun telefoon: observaties per persoon, met foto;
 *                  twijfelgevallen van het bestuur bovenaan ("extra aandacht")             (beoordelen; zonder: alleen lezen)
 *   feedback     — wat strandwachten vertellen vastleggen, zonder gesprek te openen         (gesprekken)
 *   evaluatie    — het bestuur zegt alvast sowieso aannemen / twijfel / sowieso afwijzen   (gesprekken)
 *   gesprekken   — het bestuur spreekt iedereen, in vrije volgorde, en beslist ter plekke (gesprekken)
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

// Taken (includes/modules/trainingsweekend.php).
$mag_inchecken    = mag('trainingsweekend', 'inchecken');
$mag_beoordelen   = mag('trainingsweekend', 'beoordelen');
$mag_voorbereiden = mag('trainingsweekend', 'voorbereiden');
$mag_beslissen    = mag('trainingsweekend', 'gesprekken');   // ook observaties van anderen weghalen
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
        if (!$mag_beoordelen && !$mag_beslissen) { echo json_encode(['ok' => false, 'fout' => 'geen_rechten']); exit; }
        if ($actie === 'observatie') {
            // Alleen wie nog in het weekend zit; na het besluit is het dossier de plek.
            $ok = evenement_is_kandidaat('tw', $sid, $sol) && tw_observatie_toevoegen($sid, $sol, (string)($_POST['tekst'] ?? ''),
                                                                                  (string)($_POST['soort'] ?? 'neutraal'), $ik, $ik_id);
            // De nieuwe lijst gaat als HTML mee (assets/js/beoordelen.js vervangt hem zonder herladen).
            $html = '';
            if ($ok) {
                $obs_lijst = tw_observaties_alle($sid)[$sol] ?? [];
                $obs_eigen = fn($o) => tw_observatie_is_eigen($o, $ik, $ik_id, $mag_beslissen);
                ob_start();
                require __DIR__ . '/_observatie_lijst.php';
                $html = (string)ob_get_clean();
            }
            echo json_encode(['ok' => $ok, 'html' => $html]);
        } else {
            echo json_encode(['ok' => tw_observatie_wissen((int)($_POST['id'] ?? 0), $ik, $ik_id, $mag_beslissen)]);
        }
        exit;
    }

    // Per actie de taak: voorbereiden (groepen, bevestiging) of gesprekken (evaluatie, gesprek).
    $_eis = function (bool $mag): void { if (!$mag) { http_response_code(403); exit('Dit hoort niet bij jouw taken.'); } };
    $_eis($mag_voorbereiden || $mag_beslissen);

    // Groepsindeling (admin/tw/groepen.php): zelfde handelingen als op de selectiedag.
    if ($mag_voorbereiden) bh_groepsindeling_post('tw', $sid, tw_kandidaten($sid), tw_url('groepen'));

    // Direct opslaan (assets/js/direct_opslaan.js stuurt ajax=1): JSON terug, geen herlaadactie.
    // Zonder JavaScript: gewoon formulier met Opslaan-knop, flash + redirect.
    $_ajax = !empty($_POST['ajax']);
    $_klaar = function (bool $ok, string $goed, string $fout, string $terug) use ($_ajax) {
        if ($_ajax) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => $ok, 'melding' => $ok ? $goed : $fout]); exit; }
        flash($ok ? 'succes' : 'fout', $ok ? $goed : $fout);
        redirect($terug);
    };
    if ($actie === 'bevestiging') {
        $_eis($mag_voorbereiden);
        $ok = evenement_is_kandidaat('tw', $sid, $sol)
           && evenement_bevestigen('tw', $sid, $sol, (string)($_POST['stand'] ?? ''), (string)($_POST['reden'] ?? ''), 'beheer');
        $_klaar($ok, 'Opgeslagen.', 'Niet opgeslagen: bij "kan niet" hoort een opmerking.', tw_url('voorbereiden'));
    }
    if (in_array($actie, ['evaluatie', 'gesprek_los', 'gesprek_overnemen', 'gesprek', 'gesprek_volgorde', 'gesprek_volgorde_lijst',
                          'gesprek_volgorde_herstel', 'eerder_weg'], true)) $_eis($mag_beslissen);

    // De volgorde van "Nog te spreken" (tw_gesprek_volgorde()): omhoog/omlaag/naar de lijst, slepen, of herstellen.
    if (in_array($actie, ['gesprek_volgorde', 'gesprek_volgorde_lijst'], true)) {
        $_nog = array_filter(tw_gesprekken_filter(tw_kandidaten($sid), [], $ik_id, []), fn($k) => $k['_stand'] === 'nog');
        $_ids = array_map(fn($k) => (int)$k['sol_id'], tw_gesprek_volgorde($_nog)['hoofd']);
        if ($actie === 'gesprek_volgorde') {
            $ok = evenement_is_kandidaat('tw', $sid, $sol) && tw_gesprek_verplaats($sid, $_ids, $sol, (string)($_POST['richting'] ?? ''));
        } else {
            // Alleen wie nu in "Nog te spreken" staat; de rest van wat de browser stuurt valt weg.
            $_toegestaan = array_flip(array_map(fn($k) => (int)$k['sol_id'], $_nog));
            $_nieuw = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['volgorde'] ?? ''))), fn($i) => isset($_toegestaan[$i])));
            $ok = $_nieuw && tw_gesprek_volgorde_opslaan($sid, $_nieuw);
        }
        $_klaar((bool)$ok, 'Volgorde opgeslagen.', 'Volgorde niet opgeslagen. Ververs de pagina.', tw_url('gesprekken') . '#gs-' . $sol);
    }
    if ($actie === 'gesprek_volgorde_herstel') {
        tw_gesprek_volgorde_herstellen($sid);
        $_klaar(true, 'De volgorde gaat weer vanzelf: eerst wie eerder weg moet, dan op naam.', '', tw_url('gesprekken'));
    }
    if ($actie === 'eerder_weg') {
        $ok = evenement_is_kandidaat('tw', $sid, $sol)
           && tw_eerder_weg_zetten($sid, $sol, (string)($_POST['dag'] ?? ''), (string)($_POST['tijd'] ?? ''), $seizoen);
        $_klaar($ok, 'Opgeslagen.', 'Niet opgeslagen: kies een dag van het weekend en een tijd.', tw_url('gesprekken') . '#gs-' . $sol);
    }
    if ($actie === 'evaluatie') {
        $ok = evenement_is_kandidaat('tw', $sid, $sol)
           && tw_evaluatie_zetten($sid, $sol, (string)($_POST['evaluatie'] ?? ''), (string)($_POST['evaluatie_bericht'] ?? ''), $ik);
        $_klaar($ok, 'Opgeslagen.', 'Niet opgeslagen: deze persoon staat niet (meer) op de lijst.',
                tw_url('evaluatie', null, ['toon' => (string)($_POST['toon'] ?? '')]) . '#ev-' . $sol);
    }
    if ($actie === 'gesprek_los') {
        evenement_claim_los('tw_deelname', 'gesprek', $sid, $sol, $ik_id);
        redirect(tw_url('gesprekken'));
    }
    if ($actie === 'gesprek_overnemen') {
        // Bewust overnemen van wie het gesprek open heeft (bevestigd met naam en tijd); geaudit.
        $van = (int)($_POST['van'] ?? 0);
        if (!evenement_is_kandidaat('tw', $sid, $sol)) {
            flash('fout', 'Deze persoon staat niet (meer) op de lijst van het trainingsweekend.');
            redirect(tw_url('gesprekken'));
        }
        $r = evenement_claim_overnemen('tw_deelname', 'gesprek', $sid, $sol, $ik_id, $van);
        if ($r['ok']) {
            flash('succes', 'Je hebt het gesprek overgenomen van ' . evenement_claim_naam($van) . '.');
            redirect(tw_url('gesprekken', $sol));
        }
        flash('fout', $r['door'] ? 'Niet overgenomen: het gesprek is intussen van ' . evenement_claim_naam((int)$r['door']) . '.'
                                 : 'Niet overgenomen: het gesprek is niet meer bezet. Open het gewoon.');
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
// De stappen volgen de taken; Beoordelen staat er altijd (zonder die taak: alleen lezen).
$stappen = [];
if ($mag_voorbereiden) $stappen += ['voorbereiden' => 'Voorbereiden', 'groepen' => 'Groepen'];
if ($mag_inchecken)    $stappen['inchecken'] = 'Inchecken';
$stappen['beoordelen'] = 'Beoordelen';
if ($mag_beslissen)    $stappen += ['feedback' => 'Feedback', 'evaluatie' => 'Evaluatie', 'gesprekken' => 'Gesprekken'];
$stap = (string)($_GET['stap'] ?? '');
$stap = ['weekend' => 'beoordelen', 'besluit' => 'gesprekken'][$stap] ?? $stap;   // oude links
if (!isset($stappen[$stap])) {
    $begin = (string)($seizoen['datum_tw_start'] ?? '');
    $stap  = ($mag_voorbereiden && $begin !== '' && date('Y-m-d') < $begin) ? 'voorbereiden' : 'beoordelen';
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

<?php
  // Zelfde kop als de selectiedag (admin/_stappenbalk.php).
  $sb_titel = 'Trainingsweekend'; $sb_datum = $tw_periode; $sb_actief = $stap; $sb_stappen = [];
  foreach ($stappen as $_s => $_l) $sb_stappen[$_s] = ['label' => $_l, 'url' => tw_url($_s)];
  require __DIR__ . '/_stappenbalk.php';
?>

<?php if (!$sid || (!$kandidaten && $stap !== 'voorbereiden')): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php
    $leeg_titel = !$sid ? 'Geen actief seizoen' : 'Nog niemand uitgenodigd';
    $leeg_tekst = !$sid
        ? 'Maak eerst een seizoen aan bij Seizoen.'
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

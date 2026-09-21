<?php
/**
 * Het bewerken van één week van één persoon: ophalen (GET) en opslaan (POST).
 *
 * De beslissingen staan in includes/beheer/correctie.php; dit bestand doet alleen de
 * afhandeling van het verzoek.
 *
 * Dit endpoint schrijft beschikbaarheid, niet de selectiedag. Stond eerder op
 * 'selectiedag' — dat sloot begeleiders toevallig óók uit, maar om de verkeerde reden.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* ── Ophalen: wat het paneel moet tonen ─────────────────────────
   Lezen mag met het leesrecht; het paneel toont wat er al is, en pas het opslaan
   vraagt om bewerkrecht. */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    vereis_recht('beschikbaarheid', 'lezen');
    $uit = bh_corr_week((int)($_GET['sol_id'] ?? 0), (int)($_GET['week'] ?? 0));
    $uit['redenen'] = bh_corr_redenen();
    $uit['mag_bewerken'] = mag('beschikbaarheid', 'bewerken');
    echo json_encode($uit, JSON_UNESCAPED_UNICODE);
    exit;
}

vereis_recht('beschikbaarheid', 'bewerken');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$sol_id   = (int)($_POST['sol_id'] ?? 0);
$week     = (int)($_POST['week'] ?? 0);
$soort    = $_POST['soort'] ?? '';          // 'niet' | 'minder' | '' (wissen)
$dagen    = isset($_POST['dagen']) ? (array)$_POST['dagen'] : [];
$tot_week = (int)($_POST['tot_week'] ?? 0); // doorzetten t/m deze week
$bericht  = !empty($_POST['bericht']);      // de sollicitant hierover berichten

// De reden komt als keuze + vrije toelichting binnen en wordt hier één regel.
$reden = bh_corr_reden_tekst((string)($_POST['redensleutel'] ?? ''), (string)($_POST['reden'] ?? ''));

if (!$sol_id || !$week) { echo json_encode(['ok' => false, 'fout' => 'Ongeldig']); exit; }

$soort_param = in_array($soort, ['niet', 'minder'], true) ? $soort : null;

// Reden verplicht bij een echte correctie: die regel komt in de tijdlijn van de
// sollicitant en moet later uit te leggen zijn.
if ($soort_param && $reden === '') { echo json_encode(['ok' => false, 'fout' => 'Geef een reden op.']); exit; }
if ($soort_param === 'minder' && !$dagen) { echo json_encode(['ok' => false, 'fout' => 'Kies minstens één dag.']); exit; }

$door = $_SESSION['bhd_naam'] ?? null;

try {
    $res = bh_corr_opslaan($sol_id, $week, $soort_param, $dagen, $reden, $door, $tot_week);
    if (!$res['ok']) { echo json_encode($res); exit; }

    $aantal = count($res['weken']);
    $melding = $aantal > 1
        ? 'Aangepast voor ' . $aantal . ' weken (W' . $res['weken'][0] . ' t/m W' . end($res['weken']) . ').'
        : 'Week ' . $week . ' aangepast.';

    /* De sollicitant hierover berichten. Bewust een keuze per correctie en niet
       automatisch: een invoerfout rechtzetten is geen nieuws, een vakantieweek
       schrappen wel. Faalt stil — een mislukte mail mag de correctie niet terugdraaien. */
    if ($bericht && function_exists('melding_verstuur')) {
        try {
            $weken_tekst = $aantal > 1
                ? 'week ' . $res['weken'][0] . ' t/m ' . end($res['weken'])
                : 'week ' . $week;
            melding_verstuur('beschikbaarheid_gecorrigeerd', $sol_id, [
                'weken'  => $weken_tekst,
                'reden'  => $reden,
            ], ['prioriteit' => 'na_antwoord', 'referentie' => 'corr:' . $sol_id . ':' . $week, 'dedup_min' => 0]);
            $melding .= ' De sollicitant krijgt er bericht over.';
        } catch (\Throwable $e) {
            error_log('correctie-melding: ' . $e->getMessage());
        }
    }

    if (function_exists('flash')) flash('succes', $melding);
    echo json_encode(['ok' => true, 'weken' => $res['weken'], 'melding' => $melding]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'fout' => $e->getMessage()]);
}

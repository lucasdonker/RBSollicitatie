<?php
/**
 * Selectiedag › Voorbereiden — wie komt er en welke formulieren staan nog open.
 * Zelfde stap als bij het trainingsweekend; de weergave is gedeeld (admin/_voorbereiden.php).
 *
 * Lezen = meekijken (begeleider), bevestiging wijzigen = bestuur (selectiedag › bewerken).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';

vereis_recht('selectiedag', 'lezen');

$seizoen      = actief_seizoen();
$sid          = $seizoen ? (int)$seizoen['id'] : 0;
$mag_bewerken = mag('selectiedag', 'bewerken');
$mag_dossier  = mag('sollicitanten', 'lezen');
$hier         = APP_URL . '/admin/selectie_voorbereiden.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sid) {
    csrf_check();
    if (!$mag_bewerken) { http_response_code(403); exit('Dit is bestuurswerk.'); }
    $sol = (int)($_POST['sol_id'] ?? 0);
    $ok  = ($_POST['actie'] ?? '') === 'bevestiging'
        && evenement_is_kandidaat('selectiedag', $sid, $sol)
        && evenement_bevestigen('selectiedag', $sid, $sol, (string)($_POST['stand'] ?? ''), (string)($_POST['reden'] ?? ''), 'beheer');
    $melding = $ok ? 'Opgeslagen.' : 'Niet opgeslagen: bij "kan niet" hoort een opmerking.';
    if (!empty($_POST['ajax'])) {           // assets/js/direct_opslaan.js
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'melding' => $melding]);
        exit;
    }
    flash($ok ? 'succes' : 'fout', $melding);
    redirect($hier);
}

$pagina_titel = 'Selectiedag voorbereiden';
$actieve_nav  = 'selectiedag';
require __DIR__ . '/layout_top.php';

$subnav_actief = 'voorbereiden';
$subnav_datum  = $seizoen && !empty($seizoen['datum_selectiedag']) ? datum_nl($seizoen['datum_selectiedag']) : null;
require __DIR__ . '/_selectie_subnav.php';

if (!$sid):
    echo '<div class="ds-kaart ds-kaart-p">';
    $leeg_titel = 'Geen actief seizoen'; $leeg_tekst = 'Maak eerst een seizoen aan en zet de datum van de selectiedag.'; $leeg_icoon = 'kalender';
    require __DIR__ . '/_leeg.php';
    echo '</div>';
else:
    $_open  = evenement_open_formulieren_seizoen($sid);
    $_lijst = evenement_lijst('selectiedag', $sid);
    $_n_open = count(array_filter($_lijst, fn($k) => !empty($_open[(int)$k['sol_id']])));

    $vb_lijst        = $_lijst;
    $vb_post_url     = $hier;
    $vb_filter_url   = $hier;
    $vb_verborgen    = [];
    $vb_mag_wijzigen = $mag_bewerken;
    $vb_mag_dossier  = $mag_dossier;
    $vb_klaar_kop    = 'Formulieren';
    $vb_klaar        = fn($k) => !empty($_open[(int)$k['sol_id']])
        ? [count($_open[(int)$k['sol_id']]) . ' open', 'oranje', implode(', ', $_open[(int)$k['sol_id']])]
        : ['Alles getekend', 'groen'];
    $vb_mist         = $_n_open > 0
        ? $_n_open . ' ' . ($_n_open === 1 ? 'persoon moet' : 'personen moeten') . ' nog een formulier tekenen — dat moet vóór het water. Bij het inchecken krijgen ze de link opnieuw.'
        : '';
    $vb_leeg         = ['titel' => 'Nog niemand uitgenodigd', 'tekst' => 'Zodra je sollicitanten uitnodigt voor de selectiedag, staan ze hier.', 'icoon' => 'kalender'];
    require __DIR__ . '/_voorbereiden.php';
endif;

require __DIR__ . '/layout_bottom.php';

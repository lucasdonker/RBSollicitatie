<?php
/**
 * Wisselen tussen beheer en portaal, voor wie beide toegangen heeft.
 *
 * Veiligheid: we wisselen alleen op basis van de e-mail die al in de huidige (geverifieerde)
 * sessie zit — nooit op basis van vrije invoer. Wie geen toegang tot het doeldeel heeft, wordt
 * geweigerd. Zo kan niemand zich naar een deel wisselen waar hij geen recht op heeft.
 */
require_once __DIR__ . '/includes/functions.php';
sessie_start();

$naar = $_GET['naar'] ?? '';

// Bepaal de e-mail van de huidige sessie (beheerder of sollicitant).
$email = '';
if (!empty($_SESSION['bhd_email'])) {
    $email = strtolower(trim($_SESSION['bhd_email']));
} elseif (!empty($_SESSION['portaal_email'])) {
    $email = strtolower(trim($_SESSION['portaal_email']));
}

if ($email === '') {
    // Niet ingelogd → naar de login.
    redirect(APP_URL . '/portaal/');
}

$toegang = toegang_bepaal($email);

if ($naar === 'beheer') {
    if (!$toegang['beheer']) {
        // Geen beheertoegang → terug naar portaal.
        redirect(APP_URL . '/portaal/');
    }
    // Portaal-sessie afsluiten, beheerder-sessie openen.
    unset($_SESSION['portaal_sol_id'], $_SESSION['portaal_email']);
    toegang_login_als_beheerder($email, $_SESSION['bhd_via'] ?? 'sso');
    if (function_exists('audit_log')) audit_log('gewisseld', 'toegang', null, ['naar' => 'beheer']);
    redirect(APP_URL . '/admin/');
}

if ($naar === 'portaal') {
    if (!$toegang['portaal']) {
        // Geen portaaltoegang → terug naar beheer.
        redirect(APP_URL . '/admin/');
    }
    // Beheerder-sessie afsluiten, portaal-sessie openen.
    unset($_SESSION['bhd_id'], $_SESSION['bhd_naam'], $_SESSION['bhd_email'], $_SESSION['bhd_rol'], $_SESSION['bhd_rollen'], $_SESSION['bhd_via']);
    toegang_login_als_sollicitant($email);
    if (function_exists('audit_log')) audit_log('gewisseld', 'toegang', null, ['naar' => 'portaal']);
    redirect(APP_URL . '/portaal/');
}

// Onbekende richting → terug naar de startpagina.
redirect(APP_URL . '/');

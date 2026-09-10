<?php
/**
 * Landingspagina — leidt meteen naar het inlogscherm.
 *
 * De oude keuzepagina (aanmelden / beheerders) is vervangen: aanmelden gaat voortaan via de
 * website (paal13.com, WordPress-plugin), en wie hierheen komt wil inloggen. Sollicitanten en
 * beheerders loggen in via hetzelfde portaal-scherm (e-mail); bekende mensen gaan via Authentik
 * en kunnen daarna kiezen tussen beheer en hun eigen portaal.
 *
 * Beheerders die rechtstreeks het klassieke beheerscherm willen, kunnen altijd naar /admin/.
 */
require_once __DIR__ . '/includes/functions.php';

// Nog niet geïnstalleerd? Naar setup.
$config_file = __DIR__ . '/includes/config.php';
if (!file_exists($config_file)) {
    header('Location: /setup/');
    exit;
}

// Al ingelogd? Stuur naar het juiste deel.
sessie_start();
if (!empty($_SESSION['bhd_id'])) { header('Location: ' . APP_URL . '/admin/'); exit; }
if (!empty($_SESSION['portaal_sol_id'])) { header('Location: ' . APP_URL . '/portaal/'); exit; }

// Anders: naar het inlogscherm (portaal-login is de gedeelde ingang).
header('Location: ' . APP_URL . '/portaal/');
exit;

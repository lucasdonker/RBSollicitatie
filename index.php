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
// Zonder config.php stopt functions.php zelf met een nette 503 (de installatiewizard bestaat niet meer).
require_once __DIR__ . '/includes/functions.php';

// Al ingelogd? Stuur naar het juiste deel. Alleen kijken als er al een sessiecookie is: een nieuwe
// bezoeker (of Google) krijgt zo geen cookie op de voorpagina (zelfde aanpak als p.php).
if (isset($_COOKIE[defined('SESSION_NAME') ? SESSION_NAME : 'p13sess'])) {
    sessie_start();
    if (!empty($_SESSION['bhd_id'])) { header('Location: ' . APP_URL . '/admin/'); exit; }
    if (!empty($_SESSION['portaal_sol_id'])) { header('Location: ' . APP_URL . '/portaal/'); exit; }
}

// Niet ingelogd en er is een wervingspagina als startpagina ingesteld (en gepubliceerd)? Dan daarheen:
// de voorpagina is voor zoekmachines het sterkste adres van het domein. Tijdelijke doorverwijzing
// (302), zodat een andere keuze meteen werkt en browsers niets vast onthouden.
if (function_exists('module_aan') && module_aan('paginas') && function_exists('pag_startpagina_slug')
    && ($start = pag_startpagina_slug()) !== '') {
    $p = function_exists('pag_via_slug') ? pag_via_slug($start) : null;
    if ($p && ($p['status'] ?? '') === 'gepubliceerd' && !empty($p['actieve_versie_id'])) {
        header('Location: ' . pag_publieke_url($p), true, 302);
        exit;
    }
}

// Anders: naar het inlogscherm (portaal-login is de gedeelde ingang).
header('Location: ' . APP_URL . '/portaal/');
exit;

<?php
/**
 * Paal 13 — één laadpunt voor de beheer-modules.
 *
 * Waarom apart en niet in includes/functions.php: dat bestand laadt al ~60 modules
 * eager bij élke request, ook die van de sollicitant. Het portaal hoeft geen
 * beheer-code te kennen. Wie beheer-bouwstenen nodig heeft (admin/layout_top.php en
 * de api_*.php-endpoints die geen layout laden) requiret dit bestand.
 *
 * Laadvolgorde is bewust: eerst de HTML-loze beslissingslaag, dan de string-helpers
 * die daarop leunen.
 */

if (!defined('BH_GELADEN')) {
    define('BH_GELADEN', true);

    $__bh_inc = dirname(__DIR__);

    // Beslissingen en tellingen — geen HTML
    foreach (['fase_ui.php', 'tabel.php', 'nav.php', 'vandaag.php', 'lijst.php',
              'dossier.php', 'bevestig.php', 'dekking.php', 'berichten.php',
              'seizoen.php', 'zoek.php', 'correctie.php', 'rondleiding.php', 'beschikbaarheid.php', 'loonplanning.php'] as $__bh_f) {
        if (is_file(__DIR__ . '/' . $__bh_f)) require_once __DIR__ . '/' . $__bh_f;
    }

    // Kleine HTML-bouwstenen (geven strings terug)
    if (is_file($__bh_inc . '/ui_beheer.php')) require_once $__bh_inc . '/ui_beheer.php';

    unset($__bh_inc, $__bh_f);
}

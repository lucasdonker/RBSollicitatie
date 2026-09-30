<?php
/**
 * Plugin Name:       Paal 13 — Aanmelden
 * Plugin URI:        https://sollicitatie.paal13.com/
 * Description:       Het aanmeldformulier en de seizoensgegevens van Reddingsbrigade Paal 13 op de website. Zet [paal13_aanmeldformulier] op een pagina, en gebruik [paal13_seizoen veld="datum_selectiedag"] in je teksten.
 * Version:           2.142.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Reddingsbrigade Paal 13
 * License:           GPL-2.0-or-later
 * Text Domain:       paal13-aanmelden
 *
 * ── Hoe dit samenhangt met het sollicitatiesysteem ───────────────────────────
 *
 * De plugin bevat zelf geen aanmeldlogica en geen eigen kopie van het formulier. Hij praat
 * met sollicitatie.paal13.com:
 *
 *   GET  /api/seizoen.php    de seizoensgegevens (datums, locaties, tijden)
 *   GET  /api/formulier.php  de HTML van het formulier
 *   POST /api/aanmelden.php  de aanmelding zelf
 *
 * Alle drie met de geheime sleutel in de header X-Paal13-Key. Die sleutel staat in de
 * WordPress-instellingen en verlaat de server nooit: de bezoeker post naar WordPress, en
 * WordPress praat met de API. Verandert er iets aan het formulier in het sollicitatie-
 * systeem, dan staat dat hier binnen een paar minuten ook — er is niets bij te werken.
 *
 * Snelheid: elk antwoord van de API wordt in een transient bewaard (standaard 5 minuten),
 * en het laatste goede antwoord blijft apart bewaard. Ligt de API eruit, dan blijft de
 * pagina dus gewoon werken met wat er het laatst stond.
 */

if (!defined('ABSPATH')) exit;

define('P13_VERSIE', '2.142.0');
define('P13_PAD', plugin_dir_path(__FILE__));
define('P13_URL', plugin_dir_url(__FILE__));

require_once P13_PAD . 'includes/class-p13-api.php';
require_once P13_PAD . 'includes/class-p13-seizoen.php';
require_once P13_PAD . 'includes/class-p13-formulier.php';
require_once P13_PAD . 'includes/class-p13-shortcodes.php';
require_once P13_PAD . 'includes/class-p13-admin.php';

add_action('plugins_loaded', function () {
    P13_Formulier::start();
    P13_Shortcodes::start();
    if (is_admin()) P13_Admin::start();
});

// Bij het uitschakelen alles wat we gecachet hebben weggooien, zodat een herinstallatie
// niet met oude seizoensdatums begint.
register_deactivation_hook(__FILE__, ['P13_Api', 'cache_leegmaken']);

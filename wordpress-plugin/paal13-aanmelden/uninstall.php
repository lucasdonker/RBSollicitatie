<?php
/**
 * Verwijderen van de plugin: alles opruimen wat we in de database hebben gezet.
 *
 * Er staan geen gegevens van sollicitanten in WordPress — die staan alleen in het
 * sollicitatiesysteem. Hier verdwijnen dus alleen de instellingen en de tijdelijk
 * bewaarde antwoorden van de API.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

delete_option('p13_instellingen');

$wpdb->query(
    "DELETE FROM {$wpdb->options}
      WHERE option_name LIKE '\_transient\_p13\_%'
         OR option_name LIKE '\_transient\_timeout\_p13\_%'
         OR option_name LIKE 'p13\_laatst\_goed\_%'"
);

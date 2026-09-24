<?php
/**
 * Paal 13 — Module "Pagina's" (landingspagina's).
 *
 * Zelfstandige module: alles zit in includes/paginas/. Recht en menu-item staan in het
 * moduleregister (includes/modules/paginas.php); verder raakt de kern hem via deze require
 * en pag_auto_setup in db_failsafe. Staat de module uit, dan geeft p.php een 404.
 *
 * Prefix: pag_   (pagina_ is bezet: functions.php heeft al pagina_404)
 * Publieke URL: /p/<slug>     Opslag: uploads/paginas/<slug>/
 */

foreach (['kern', 'opslag', 'verwijzingen', 'statistiek', 'token', 'import', 'formulier', 'render', 'migratie'] as $__pag_deel) {
    $__pag_pad = __DIR__ . '/paginas/' . $__pag_deel . '.php';
    if (file_exists($__pag_pad)) require_once $__pag_pad;
}
unset($__pag_deel, $__pag_pad);

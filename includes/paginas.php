<?php
/**
 * Paal 13 — Module "Pagina's" (landingspagina's).
 *
 * Zelfstandige module: alles zit in includes/paginas/. De kern raakt hem op maar vier
 * plekken aan (deze require, db_failsafe, het menu en de rechtenmatrix) — zie
 * pag_module_definitie() in kern.php, die precies die vier dingen als array teruggeeft.
 *
 * Prefix: pag_   (pagina_ is bezet: functions.php heeft al pagina_404)
 * Publieke URL: /p/<slug>     Opslag: uploads/paginas/<slug>/
 */

foreach (['kern', 'opslag', 'statistiek', 'token', 'import', 'formulier', 'render', 'migratie'] as $__pag_deel) {
    $__pag_pad = __DIR__ . '/paginas/' . $__pag_deel . '.php';
    if (file_exists($__pag_pad)) require_once $__pag_pad;
}
unset($__pag_deel, $__pag_pad);

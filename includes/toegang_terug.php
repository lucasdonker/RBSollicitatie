<?php
/**
 * Waar een beheerder na het inloggen heen gaat (?terug=…).
 *
 * vereist_login() stuurt het opgevraagde pad mee (REQUEST_URI), login.php en auth_sso.php geven het
 * door, auth_callback.php stuurt erheen. Zonder controle is dat een open redirect: een link als
 * login.php?terug=https://kwaad.example stuurt een beheerder na een échte login naar buiten.
 * Daarom alleen een pad binnen het eigen beheer; al het andere wordt het beheer-startscherm.
 */
if (!function_exists('toegang_veilige_terug')) {
function toegang_veilige_terug(string $t): string {
    $start = APP_URL . '/admin/';
    $t = trim($t);
    if ($t === '') return $start;

    // Het pad van het beheer, ook als de app in een submap draait (APP_URL met pad).
    $basis = rtrim((string)parse_url(APP_URL, PHP_URL_PATH), '/') . '/admin/';

    // Een volledige URL naar het eigen beheer mag, als pad.
    if (str_starts_with($t, $start)) $t = $basis . substr($t, strlen($start));

    // Geen schema, geen //host, geen backslash (browsers lezen \ als /), geen stuurtekens, geen ../
    if (!str_starts_with($t, $basis)
        || str_contains($t, '//')
        || str_contains($t, '\\')
        || str_contains($t, '/../')
        || preg_match('/[\x00-\x1F\x7F]/', $t)) {
        return $start;
    }
    return $t;
}
}

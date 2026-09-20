<?php
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
$via = $_SESSION['bhd_via'] ?? 'wachtwoord';
$slug = defined('OIDC_SLUG') ? OIDC_SLUG : '';
// Vóór session_destroy(): audit_log() leest de actor uit de sessie op het moment van aanroepen.
// Met inloggen erbij (functions.php) is zo te zien hoe lang iemand toegang had.
if (!empty($_SESSION['bhd_id'])) audit_log('uitgelogd', 'beheerder', (int)$_SESSION['bhd_id'], ['via' => $via]);
session_destroy();
if ($via === 'authentik' && authentik_actief() && $slug) {
    $url = rtrim(OIDC_ISSUER, '/') . '/application/o/' . $slug . '/end-session/?post_logout_redirect_uri=' . urlencode(APP_URL . '/admin/login.php');
    header('Location: ' . $url); exit;
}
header('Location: ' . APP_URL . '/admin/login.php'); exit;

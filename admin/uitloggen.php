<?php
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
$via = $_SESSION['bhd_via'] ?? 'wachtwoord';
$slug = defined('OIDC_SLUG') ? OIDC_SLUG : '';
session_destroy();
if ($via === 'authentik' && authentik_actief() && $slug) {
    $url = rtrim(OIDC_ISSUER, '/') . '/application/o/' . $slug . '/end-session/?post_logout_redirect_uri=' . urlencode(APP_URL . '/admin/login.php');
    header('Location: ' . $url); exit;
}
header('Location: ' . APP_URL . '/admin/login.php'); exit;

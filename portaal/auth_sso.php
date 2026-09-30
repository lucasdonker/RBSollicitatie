<?php
/**
 * Portaal SSO start — redirect naar Authentik OIDC.
 * Aparte sessie state dan admin om verwarring te voorkomen.
 */
require_once __DIR__ . '/../includes/functions.php';
sessie_start();

if (!authentik_actief()) {
    redirect(APP_URL . '/portaal/');
}

$email = strtolower(trim($_SESSION['otp_email'] ?? $_GET['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect(APP_URL . '/portaal/');
}

$state = bin2hex(random_bytes(16));
$_SESSION['portaal_sso_state'] = $state;
$_SESSION['portaal_sso_email'] = $email;

$redirect_uri = APP_URL . '/portaal/auth_callback.php';

$params = http_build_query([
    'client_id'     => OIDC_CLIENT_ID,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'openid profile email',
    'state'         => $state,
    'login_hint'    => $email, // Authentik pre-fills het email veld
] + oidc_pkce_start('portaal_sso_pkce'));

header('Location: ' . rtrim(OIDC_ISSUER, '/') . '/application/o/authorize/?' . $params);
exit;

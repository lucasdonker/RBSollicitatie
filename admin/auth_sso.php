<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/toegang_terug.php';
sessie_start();
if (is_ingelogd()) redirect(APP_URL . '/admin/');
if (!authentik_actief()) { flash('fout', 'Authentik SSO is niet geconfigureerd.'); redirect(APP_URL . '/admin/login.php'); }

$_SESSION['sso_terug'] = toegang_veilige_terug((string)($_GET['terug'] ?? ''));
$state = bin2hex(random_bytes(16));
$_SESSION['sso_state'] = $state;

$params = http_build_query(['client_id' => OIDC_CLIENT_ID, 'redirect_uri' => OIDC_REDIRECT_URI, 'response_type' => 'code', 'scope' => 'openid profile email', 'state' => $state]);
header('Location: ' . OIDC_ISSUER . '/application/o/authorize/?' . $params);
exit;

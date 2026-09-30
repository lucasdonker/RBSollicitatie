<?php
/**
 * Portaal SSO callback — verwerkt Authentik OIDC terugkomst.
 * - Verifieert state
 * - Wisselt code voor token
 * - Haalt userinfo op
 * - Logt in als sollicitant (gebruikt bestaande portaal sessie)
 */
require_once __DIR__ . '/../includes/functions.php';
sessie_start();

$fout = '';

// 1. State check
// hash_equals: vergelijken in constante tijd, net als in het beheer.
if (empty($_GET['state']) || !is_string($_GET['state']) || !hash_equals((string)($_SESSION['portaal_sso_state'] ?? ''), $_GET['state'])) {
    $fout = 'Ongeldige sessie-state.';
}

// 2. Code aanwezig?
if (!$fout && empty($_GET['code'])) {
    $fout = 'Geen autorisatiecode ontvangen.';
}

// 3. Token wisselen
$token = null;
if (!$fout) {
    $url = rtrim(OIDC_ISSUER, '/') . '/application/o/token/';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'authorization_code',
            'code'          => $_GET['code'],
            'redirect_uri'  => APP_URL . '/portaal/auth_callback.php',
            'client_id'     => OIDC_CLIENT_ID,
            'client_secret' => OIDC_CLIENT_SECRET,
        ] + array_filter(['code_verifier' => oidc_pkce_verifier('portaal_sso_pkce')])),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 200) {
        $fout = 'Kon token niet ophalen (HTTP ' . $http . ').';
    } else {
        $d = json_decode($body, true);
        $token = $d['access_token'] ?? null;
        if (!$token) $fout = 'Geen access token ontvangen.';
    }
}

// 4. Userinfo
$ui = null;
if (!$fout) {
    $ch = curl_init(rtrim(OIDC_ISSUER, '/') . '/application/o/userinfo/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 200) {
        $fout = 'Userinfo ophalen mislukt.';
    } else {
        $ui = json_decode($body, true);
        if (empty($ui['email'])) $fout = 'Geen e-mailadres in Authentik response.';
    }
}

// 5. Match op sollicitatie in DB
if (!$fout) {
    $email = strtolower(trim($ui['email']));
    $verwacht = strtolower(trim($_SESSION['portaal_sso_email'] ?? ''));
    if ($verwacht && $email !== $verwacht) {
        $fout = "Ander e-mailadres dan ingevuld ($email vs $verwacht).";
    }
}

if (!$fout) {
    $email = strtolower(trim($ui['email']));
    $q = db()->prepare("
        SELECT s.id sol_id, p.id pid, p.voornaam
        FROM personen p
        JOIN sollicitaties s ON s.persoon_id = p.id
        WHERE p.email = ?
        ORDER BY s.aangemeld_op DESC
        LIMIT 1
    ");
    $q->execute([$email]);
    $sol = $q->fetch();

    // Bepaal of deze persoon ook beheertoegang heeft. Zo ja → keuzescherm.
    $heeft_beheer = false;
    if (function_exists('toegang_bepaal')) {
        $t = toegang_bepaal($email);
        $heeft_beheer = $t['beheer'];
    }

    if ($heeft_beheer) {
        // Dubbele toegang (of alleen beheer): laat het keuzescherm beslissen/routeren.
        unset($_SESSION['portaal_sso_state'], $_SESSION['portaal_sso_email'], $_SESSION['otp_email']);
        $_SESSION['toegang_email'] = $email;
        redirect(APP_URL . '/kies.php');
    }

    if (!$sol) {
        // Geen bestaande sollicitatie én geen beheer — kan een nieuwe sollicitant zijn
        unset($_SESSION['portaal_sso_state'], $_SESSION['portaal_sso_email']);
        flash('info', 'Je Paal 13-account is herkend, maar er is nog geen sollicitatie. Vul eerst het aanmeldformulier in.');
        redirect(APP_URL . '/portaal/');
    }

    // Alleen sollicitant → gewoon inloggen zoals voorheen.
    login_portaal((int)$sol['sol_id'], $email);
    unset($_SESSION['portaal_sso_state'], $_SESSION['portaal_sso_email'], $_SESSION['otp_email']);
    redirect(APP_URL . '/portaal/');
}

// Foutweergave
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Inloggen mislukt — Paal 13</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css">
</head>
<body>
<main class="login-wrapper">
  <div class="login-kaart">
    <div class="login-logo">
      <h1>Inloggen mislukt</h1>
    </div>
    <div class="ds-melding ds-melding-error" role="alert"><?= icoon('let-op', 16) ?><span><?= h($fout) ?></span></div>
    <a href="<?= APP_URL ?>/portaal/" class="ds-btn ds-btn-primair ds-btn-vol">Opnieuw proberen</a>
  </div>
</main>
</body>
</html>

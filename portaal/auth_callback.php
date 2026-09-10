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
if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['portaal_sso_state'] ?? '')) {
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
        ]),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
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
        $_SESSION['portaal_flash'] = 'Je Authentik-account is herkend, maar er is nog geen sollicitatie. Vul eerst het aanmeldformulier in.';
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
<title>Inloggen mislukt</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css">
</head>
<body style="background:var(--navy);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
  <div style="background:#fff;border-radius:12px;padding:30px;max-width:420px;text-align:center">
    <h2 style="color:#e53e3e;margin-bottom:10px">Inloggen mislukt</h2>
    <p style="color:#4a5568;font-size:14px;margin-bottom:20px"><?= htmlspecialchars($fout) ?></p>
    <a href="<?= APP_URL ?>/portaal/" class="btn btn-primary">Opnieuw proberen</a>
  </div>
</body>
</html>

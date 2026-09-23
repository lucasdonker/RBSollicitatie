<?php
/**
 * Authentik OIDC Callback
 * Gebruikt cURL voor HTTPS requests (betrouwbaarder dan file_get_contents)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/toegang_terug.php';
sessie_start();

$fout  = '';
// Technische details (token- en userinfo-antwoorden van Authentik) zijn nuttig bij het inrichten,
// maar horen niet op een scherm dat iedereen zonder login kan opvragen. Op productie gaan ze
// alleen naar het foutenlog; op de test-omgeving blijven ze zichtbaar in het uitklapblok.
$debug = [];
$debug_tonen = function_exists('is_test_omgeving') && is_test_omgeving();

// ── 1. State validatie ────────────────────────────────────────
$state_ontvangen = $_GET['state'] ?? '';
$state_verwacht  = $_SESSION['sso_state'] ?? '';
$terug           = $_SESSION['sso_terug'] ?? '';

unset($_SESSION['sso_state'], $_SESSION['sso_terug']);

if (!$state_ontvangen || !hash_equals($state_verwacht, $state_ontvangen)) {
    $fout   = 'Ongeldige state parameter. Probeer opnieuw in te loggen.';
    $debug[] = "State check mislukt: ontvangen='" . substr($state_ontvangen, 0, 20) . "' verwacht='" . substr($state_verwacht, 0, 20) . "'";
}

// Authentik error?
if (!$fout && isset($_GET['error'])) {
    $fout   = 'Authentik fout: ' . htmlspecialchars($_GET['error']) . '. ' . htmlspecialchars($_GET['error_description'] ?? '');
    $debug[] = 'Authentik error: ' . ($_GET['error'] ?? '') . ' - ' . ($_GET['error_description'] ?? '');
}

// ── 2. Authorization code ─────────────────────────────────────
$code = $_GET['code'] ?? '';
if (!$fout && !$code) {
    $fout   = 'Geen autorisatiecode ontvangen van Authentik.';
    $debug[] = 'Geen code in GET parameters. Parameters: ' . implode(', ', array_keys($_GET));
}

// ── Helper: cURL request ──────────────────────────────────────
function curl_post(string $url, array $data, array $headers = []): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'body' => '', 'code' => 0, 'fout' => 'cURL niet beschikbaar op deze server'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'], $headers),
    ]);
    $body    = curl_exec($ch);
    $code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $fout    = curl_error($ch);
    curl_close($ch);
    return ['ok' => ($body !== false && $code >= 200 && $code < 300), 'body' => $body ?: '', 'code' => $code, 'fout' => $fout];
}

function curl_get(string $url, string $bearer): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'body' => '', 'code' => 0, 'fout' => 'cURL niet beschikbaar'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $bearer, 'Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $fout = curl_error($ch);
    curl_close($ch);
    return ['ok' => ($body !== false && $code >= 200 && $code < 300), 'body' => $body ?: '', 'code' => $code, 'fout' => $fout];
}

// ── 3. Token ophalen ─────────────────────────────────────────
$token_data = null;
if (!$fout) {
    $token_url = rtrim(OIDC_ISSUER, '/') . '/application/o/token/';
    $debug[]   = "Token URL: $token_url";

    $result = curl_post($token_url, [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => OIDC_REDIRECT_URI,
        'client_id'     => OIDC_CLIENT_ID,
        'client_secret' => OIDC_CLIENT_SECRET,
    ]);

    $debug[] = "Token response HTTP {$result['code']}: " . substr($result['body'], 0, 300);

    if (!$result['ok']) {
        if ($result['fout']) {
            $fout = "Kan Authentik niet bereiken: {$result['fout']}";
        } else {
            $resp = json_decode($result['body'], true);
            $fout = 'Token ophalen mislukt (HTTP ' . $result['code'] . '): ' . ($resp['error_description'] ?? $resp['error'] ?? 'onbekende fout');
        }
    } else {
        $token_data = json_decode($result['body'], true);
        if (!isset($token_data['access_token'])) {
            $fout    = 'Geen access token ontvangen van Authentik.';
            $debug[] = 'Token response: ' . $result['body'];
        }
    }
}

// ── 4. Userinfo ophalen ───────────────────────────────────────
$userinfo = null;
if (!$fout) {
    $userinfo_url = rtrim(OIDC_ISSUER, '/') . '/application/o/userinfo/';
    $debug[]      = "Userinfo URL: $userinfo_url";

    $result2 = curl_get($userinfo_url, $token_data['access_token']);
    $debug[] = "Userinfo response HTTP {$result2['code']}: " . substr($result2['body'], 0, 300);

    if (!$result2['ok']) {
        $fout = 'Userinfo ophalen mislukt (HTTP ' . $result2['code'] . ').' . ($result2['fout'] ? ' ' . $result2['fout'] : '');
    } else {
        $userinfo = json_decode($result2['body'], true);
        if (empty($userinfo['email'])) {
            $fout    = 'Geen e-mailadres ontvangen van Authentik. Zorg dat de "email" scope actief is in de provider.';
            $debug[] = 'Userinfo: ' . $result2['body'];
        }
    }
}

// ── 5. Beheerder koppelen / aanmaken ─────────────────────────
if (!$fout) {
    $email = strtolower(trim($userinfo['email']));
    $naam  = trim(($userinfo['given_name'] ?? '') . ' ' . ($userinfo['family_name'] ?? ''));
    if (!$naam || $naam === ' ') $naam = $userinfo['preferred_username'] ?? $email;
    $sub   = $userinfo['sub'] ?? null;
    $groups = (array)($userinfo['groups'] ?? []);

    // Toegang: alleen wie in Authentik in een van de toegestane groepen zit (exacte naam).
    $admin_groepen_str = instelling('authentik.admin_groepen',
        defined('AUTHENTIK_STANDAARD_GROEPEN') ? AUTHENTIK_STANDAARD_GROEPEN : 'Bestuur,Sol-Admin,Sol-Begeleider,Sol-Penningmeester');
    $admin_groepen    = array_filter(array_map('trim', explode(',', $admin_groepen_str)));
    $matched_group    = null;
    foreach ($admin_groepen as $ag) {
        foreach ($groups as $ug) {
            if (strcasecmp($ag, $ug) === 0) { $matched_group = $ag; break 2; }
        }
    }

    // Bepaal app-rol op basis van match
    $rol_uit_groups = authentik_groups_naar_rol($groups);

    $db = db();
    $stmt = $db->prepare("SELECT * FROM beheerders WHERE (authentik_uid = ? AND authentik_uid IS NOT NULL) OR email = ? LIMIT 1");
    $stmt->execute([$sub, $email]);
    $b = $stmt->fetch();

    // Niet (meer) in een toegestane groep → geen toegang via SSO, ook niet voor een bestaand account.
    // Zo trekt "uit de groep halen" in Authentik de toegang echt in (offboarding). Het account zelf
    // blijft ongemoeid: een ontbrekende groups-claim of hernoemde groep mag niet iedereen buitensluiten,
    // en een wachtwoord-noodingang die een admin heeft ingericht blijft werken via login.php.
    if (!$matched_group) {
        $fout = "Je Authentik-account heeft geen admin-toegang. Voor sollicitatieportaal: ga naar " . APP_URL . "/portaal/";
        if (function_exists('audit_log')) {
            audit_log('sso_geweigerd', 'beheerder', $b ? (int)$b['id'] : null, [
                'email'   => $email,
                'reden'   => $b ? 'niet_meer_in_toegestane_groep' : 'geen_toegestane_groep',
                'groepen' => array_slice(array_map('strval', $groups), 0, 20),
            ]);
        }
    } else {
        // De rol volgt de gekoppelde Authentik-groep, bij elke login. Zit iemand in een toegestane
        // groep die aan geen rol gekoppeld is, dan blijft de rol die een admin heeft gekozen: anders
        // verliest een beheerder in een eigen groep bij de volgende login zijn rechten. (Uit álle
        // toegestane groepen = hierboven al geweigerd.) Nieuwe accounts krijgen de laagste rol.
        if ($b) {
            $nieuwe_rol = $rol_uit_groups ?: ($b['rol'] !== '' ? $b['rol'] : 'begeleider');
            $db->prepare("UPDATE beheerders SET authentik_uid = ?, naam = ?, rol = ?, laatste_login = NOW() WHERE id = ?")
               ->execute([$sub, $naam, $nieuwe_rol, $b['id']]);
            $b['naam'] = $naam;
            $b['rol']  = $nieuwe_rol;
        } else {
            // Nieuw account
            $rol = $rol_uit_groups ?: 'begeleider';
            $db->prepare("INSERT INTO beheerders (naam, email, wachtwoord, rol, authentik_uid) VALUES (?, ?, NULL, ?, ?)")
               ->execute([$naam, $email, $rol, $sub]);
            $new_id = (int)$db->lastInsertId();
            $stmt_b = $db->prepare("SELECT * FROM beheerders WHERE id = ?");
            $stmt_b->execute([$new_id]);
            $b = $stmt_b->fetch();
        }
    } // einde else van matched_group check

    if (!$fout && (!$b || !$b['actief'])) {
        $fout = 'Account is niet actief of kon niet worden aangemaakt. Vraag een admin om toegang.';
    } elseif (!$fout) {
        login_beheerder($b, 'authentik');
        redirect(toegang_veilige_terug($terug));
    }
}

// ── Foutpagina ────────────────────────────────────────────────
// Op productie: details naar het foutenlog (zonder de token-body zelf), niet naar het scherm.
if ($fout && !$debug_tonen) {
    $veilig = array_values(array_filter($debug, fn($r) => !str_starts_with($r, 'Token response') && !str_starts_with($r, 'Userinfo')));
    if (function_exists('p13_fout')) p13_fout('P13-S002', 'SSO-login mislukt: ' . $fout . ($veilig ? ' | ' . implode(' | ', $veilig) : ''));
    $debug = [];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SSO Fout — Paal 13</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css">
</head>
<body>
<div class="login-wrapper">
  <div class="login-kaart" style="max-width:500px">
    <div class="login-logo">
      <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Paal 13"
           onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/logo.svg'">
      <h1>SSO Login mislukt</h1>
    </div>
    <div class="ds-melding ds-melding-error"><?= h($fout) ?></div>

    <?php if (!empty($debug)): ?>
    <details style="margin-bottom:14px">
      <summary style="cursor:pointer;font-size:12px;color:var(--grijs);padding:8px 0">🔍 Technische details (voor diagnose)</summary>
      <div style="background:#f7f8fa;border-radius:6px;padding:10px;margin-top:6px;font-size:11px;font-family:monospace;white-space:pre-wrap;word-break:break-all;border:1px solid var(--rand)"><?= h(implode("\n\n", $debug)) ?></div>
    </details>
    <?php endif; ?>

    <div style="display:flex;flex-direction:column;gap:8px">
      <a href="<?= APP_URL ?>/admin/auth_sso.php" class="ds-btn ds-btn-primair" style="justify-content:center">↺ Opnieuw proberen via SSO</a>
      <a href="<?= APP_URL ?>/admin/login.php" class="ds-btn ds-btn-secundair" style="justify-content:center">🔑 Inloggen met wachtwoord</a>
    </div>
  </div>
</div>
</body>
</html>

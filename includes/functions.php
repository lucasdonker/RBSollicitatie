<?php
// ── Config laden ──────────────────────────────────────────────
$_config = __DIR__ . '/config.php';
if (file_exists($_config)) {
    require_once $_config;
} else {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/setup/') === false) {
        $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        header('Location: ' . $base . '/setup/');
        exit;
    }
    return;
}

require_once __DIR__ . '/smtp.php';
if (file_exists(__DIR__ . '/versie.php'))        require_once __DIR__ . '/versie.php';
if (file_exists(__DIR__ . '/mail_render.php'))   require_once __DIR__ . '/mail_render.php';
if (file_exists(__DIR__ . '/error_handler.php')) require_once __DIR__ . '/error_handler.php';
if (file_exists(__DIR__ . '/instellingen.php'))  require_once __DIR__ . '/instellingen.php';
if (file_exists(__DIR__ . '/ai_analyse.php'))      require_once __DIR__ . '/ai_analyse.php';
if (file_exists(__DIR__ . '/kennismakingspakket.php')) require_once __DIR__ . '/kennismakingspakket.php';
if (file_exists(__DIR__ . '/ondertekeningen_auto.php')) require_once __DIR__ . '/ondertekeningen_auto.php';
if (file_exists(__DIR__ . '/ondertekening_mails.php')) require_once __DIR__ . '/ondertekening_mails.php';
if (file_exists(__DIR__ . '/ondertekening_pdf.php')) require_once __DIR__ . '/ondertekening_pdf.php';
if (file_exists(__DIR__ . '/rechten.php')) require_once __DIR__ . '/rechten.php';
if (file_exists(__DIR__ . '/beschikbaarheid_dagen.php')) require_once __DIR__ . '/beschikbaarheid_dagen.php';
if (file_exists(__DIR__ . '/beschikbaarheid2.php')) require_once __DIR__ . '/beschikbaarheid2.php';
if (file_exists(__DIR__ . '/beschikbaarheid_post.php')) require_once __DIR__ . '/beschikbaarheid_post.php';
if (file_exists(__DIR__ . '/beschikbaarheid_dag.php')) require_once __DIR__ . '/beschikbaarheid_dag.php';
if (file_exists(__DIR__ . '/competenties.php')) require_once __DIR__ . '/competenties.php';
if (file_exists(__DIR__ . '/bezetting.php')) require_once __DIR__ . '/bezetting.php';
if (file_exists(__DIR__ . '/loonplan.php')) require_once __DIR__ . '/loonplan.php';
if (file_exists(__DIR__ . '/selectiedag.php')) require_once __DIR__ . '/selectiedag.php';
if (file_exists(__DIR__ . '/ai_beschikbaarheid.php')) require_once __DIR__ . '/ai_beschikbaarheid.php';
if (file_exists(__DIR__ . '/opschonen.php')) require_once __DIR__ . '/opschonen.php';
if (file_exists(__DIR__ . '/db_failsafe.php')) require_once __DIR__ . '/db_failsafe.php';
if (file_exists(__DIR__ . '/audit.php')) require_once __DIR__ . '/audit.php';
if (file_exists(__DIR__ . '/toegang.php')) require_once __DIR__ . '/toegang.php';
if (file_exists(__DIR__ . '/health.php')) require_once __DIR__ . '/health.php';
if (file_exists(__DIR__ . '/landing.php')) require_once __DIR__ . '/landing.php';
if (file_exists(__DIR__ . '/kanalen.php')) require_once __DIR__ . '/kanalen.php';
if (file_exists(__DIR__ . '/notificatie.php')) require_once __DIR__ . '/notificatie.php';
if (file_exists(__DIR__ . '/optout.php')) require_once __DIR__ . '/optout.php';
if (file_exists(__DIR__ . '/export_dossier.php')) require_once __DIR__ . '/export_dossier.php';
if (file_exists(__DIR__ . '/iconen.php')) require_once __DIR__ . '/iconen.php';

// ── Database ──────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    static $failsafe_bezig = false;
    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<html><body style="font-family:sans-serif;padding:40px"><h2 style="color:#c53030">Database fout</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p><a href="/setup/">Controleer de configuratie</a></p></body></html>');
        }
        // Zelfherstel: zorg dat het schema klopt zodra de connectie er is.
        // $failsafe_bezig voorkomt oneindige recursie (db_failsafe roept db() aan).
        if (!$failsafe_bezig && function_exists('db_failsafe')) {
            $failsafe_bezig = true;
            try { db_failsafe(); } catch (\Throwable $e) { error_log('db_failsafe trigger: ' . $e->getMessage()); }
        }
    }
    return $pdo;
}

// ── Sessie ────────────────────────────────────────────────────
function sessie_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
        if (!isset($_SESSION['_t'])) { $_SESSION['_t'] = time(); }
        elseif (time() - $_SESSION['_t'] > 1800) { session_regenerate_id(true); $_SESSION['_t'] = time(); }
    }
}

// ── Beheerder auth ────────────────────────────────────────────
function is_ingelogd(): bool { sessie_start(); return !empty($_SESSION['bhd_id']); }

function vereist_login(): void {
    if (!is_ingelogd()) { header('Location: ' . APP_URL . '/admin/login.php?terug=' . urlencode($_SERVER['REQUEST_URI'] ?? '')); exit; }
}

/** Rang van een rol-sleutel; uit de database (nieuwe rollen), met terugval op de basis. */
function rol_rang(string $sleutel): int {
    static $cache = null;
    if ($cache === null) {
        $cache = ['begeleider' => 1, 'bestuur' => 2, 'admin' => 3]; // terugval
        if (function_exists('rechten_auto_setup')) {
            try {
                rechten_auto_setup();
                foreach (db()->query("SELECT sleutel, rang FROM rollen")->fetchAll() as $r) {
                    $cache[$r['sleutel']] = (int)$r['rang'];
                }
            } catch (\Exception $e) {}
        }
    }
    return $cache[$sleutel] ?? 0;
}

function vereist_rol(string $min): void {
    vereist_login();
    if (rol_rang($_SESSION['bhd_rol'] ?? 'begeleider') < rol_rang($min)) {
        http_response_code(403); die('<h2 style="font-family:sans-serif">Geen toegang</h2>');
    }
}

/** True als de ingelogde beheerder minstens de gevraagde rol heeft (zonder af te breken). */
function huidige_gebruiker_rol_minstens(string $min): bool {
    return rol_rang($_SESSION['bhd_rol'] ?? 'begeleider') >= rol_rang($min);
}

// ── Omgeving-detectie (test vs productie) ─────────────────────
if (!function_exists('omgeving')) {
/**
 * Bepaalt op welke omgeving we draaien, op basis van APP_URL.
 * Retourneert 'test' als de URL een test/staging/dev-subdomein is, anders 'productie'.
 * Handig om per ongeluk testen op productie te voorkomen.
 */
function omgeving(): string {
    $url = defined('APP_URL') ? strtolower(APP_URL) : '';
    // Haal de hostnaam eruit en check op test/staging/dev-aanduidingen (voor- of achteraan)
    $host = parse_url($url, PHP_URL_HOST) ?: $url;
    if (preg_match('#(^|[.\-])(test|staging|stage|dev|acc|ontwikkel)([.\-]|$)#', $host)) return 'test';
    if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) return 'test';
    return 'productie';
}
}

if (!function_exists('is_test_omgeving')) {
function is_test_omgeving(): bool { return omgeving() === 'test'; }
}

function login_beheerder(array $b, string $via = 'wachtwoord'): void {
    sessie_start();
    session_regenerate_id(true);
    $_SESSION['bhd_id'] = $b['id']; $_SESSION['bhd_naam'] = $b['naam'];
    $_SESSION['bhd_email'] = $b['email']; $_SESSION['bhd_rol'] = $b['rol'];
    $_SESSION['bhd_via'] = $via; $_SESSION['_t'] = time();
    try { db()->prepare("UPDATE beheerders SET laatste_login = NOW() WHERE id = ?")->execute([$b['id']]); } catch (Exception $e) {}
    if (function_exists('audit_log')) audit_log('ingelogd', 'beheerder', (int)$b['id'], ['via' => $via]);
}

// ── Brute-force bescherming (login) ───────────────────────────
if (!function_exists('login_pogingen_auto_setup')) {
function login_pogingen_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `login_pogingen` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `email`      VARCHAR(150) DEFAULT NULL,
            `ip`         VARCHAR(45)  DEFAULT NULL,
            `moment`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_moment (email, moment),
            INDEX idx_ip_moment (ip, moment)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('login_pogingen_auto_setup: ' . $e->getMessage()); }
}
}

if (!function_exists('login_geblokkeerd')) {
/**
 * Bepaalt of inloggen tijdelijk geblokkeerd is voor deze e-mail of dit IP.
 * Standaard: max 5 mislukte pogingen binnen 15 minuten.
 * Retourneert 0 als er niet geblokkeerd is, anders het aantal seconden tot ontgrendeling.
 */
function login_geblokkeerd(string $email, string $ip, int $max = 5, int $venster_min = 15): int {
    login_pogingen_auto_setup();
    try {
        $grens = date('Y-m-d H:i:s', strtotime("-{$venster_min} minutes"));
        // Tel recente mislukte pogingen voor deze e-mail OF dit IP
        $q = db()->prepare("SELECT COUNT(*) AS n, MAX(moment) AS laatste
                            FROM login_pogingen
                            WHERE moment > ? AND (email = ? OR ip = ?)");
        $q->execute([$grens, $email, $ip]);
        $r = $q->fetch();
        if (($r['n'] ?? 0) >= $max) {
            // Blokkade loopt tot venster_min na de laatste poging
            $tot = strtotime($r['laatste']) + $venster_min * 60;
            $resterend = $tot - time();
            return $resterend > 0 ? $resterend : 0;
        }
    } catch (\Exception $e) { error_log('login_geblokkeerd: ' . $e->getMessage()); }
    return 0;
}
}

if (!function_exists('login_poging_registreren')) {
/** Legt een mislukte inlogpoging vast. Ruimt tegelijk oude pogingen (>24u) op. */
function login_poging_registreren(string $email, string $ip): void {
    login_pogingen_auto_setup();
    try {
        db()->prepare("INSERT INTO login_pogingen (email, ip) VALUES (?, ?)")
            ->execute([substr($email, 0, 150), substr($ip, 0, 45)]);
        // Opruimen van pogingen ouder dan 24 uur (klein onderhoud)
        db()->exec("DELETE FROM login_pogingen WHERE moment < (NOW() - INTERVAL 24 HOUR)");
    } catch (\Exception $e) { error_log('login_poging_registreren: ' . $e->getMessage()); }
}
}

if (!function_exists('login_pogingen_wissen')) {
/** Wist de mislukte pogingen na een succesvolle login. */
function login_pogingen_wissen(string $email, string $ip): void {
    try {
        db()->prepare("DELETE FROM login_pogingen WHERE email = ? OR ip = ?")
            ->execute([$email, $ip]);
    } catch (\Exception $e) { /* stil */ }
}
}

// ── Dashboard-meldingen (dingen die aandacht vragen) ──────────
if (!function_exists('dashboard_meldingen')) {
/**
 * Verzamelt actiegerichte meldingen voor het dashboard.
 * Elke melding: ['type'=>'info|waarschuwing|urgent', 'tekst'=>..., 'link'=>..., 'aantal'=>int]
 */
function dashboard_meldingen(int $sid): array {
    $m = [];
    $db = db();
    if (!$sid) return $m;

    // 1. Nieuwe aanmeldingen die nog niet in behandeling zijn (fase 'aangemeld')
    try {
        $q = $db->prepare("SELECT COUNT(*) FROM sollicitaties WHERE seizoen_id=? AND fase='aangemeld'");
        $q->execute([$sid]);
        $n = (int)$q->fetchColumn();
        if ($n > 0) $m[] = ['type'=>'info', 'aantal'=>$n,
            'tekst'=> $n === 1 ? '1 nieuwe aanmelding wacht op behandeling' : "$n nieuwe aanmeldingen wachten op behandeling",
            'link'=> APP_URL.'/admin/sollicitanten.php?fase=aangemeld'];
    } catch (\Exception $e) {}

    // 2. Ondertekeningen die langer dan 3 dagen openstaan
    try {
        $n = (int)$db->query("SELECT COUNT(*) FROM ondertekeningen WHERE ondertekend_op IS NULL AND aangemaakt_op < DATE_SUB(NOW(), INTERVAL 3 DAY)")->fetchColumn();
        if ($n > 0) $m[] = ['type'=>'waarschuwing', 'aantal'=>$n,
            'tekst'=> $n === 1 ? '1 ondertekening staat al >3 dagen open' : "$n ondertekeningen staan al >3 dagen open",
            'link'=> APP_URL.'/admin/ondertekeningen.php'];
    } catch (\Exception $e) {}

    // 3. Vastgelopen mails
    try {
        $n = (int)$db->query("SELECT COUNT(*) FROM mail_queue WHERE status='wacht' AND pogingen >= 3")->fetchColumn();
        if ($n > 0) $m[] = ['type'=>'urgent', 'aantal'=>$n,
            'tekst'=> $n === 1 ? '1 e-mail is niet verstuurd (mislukt)' : "$n e-mails zijn niet verstuurd (mislukt)",
            'link'=> APP_URL.'/admin/instellingen.php'];
    } catch (\Exception $e) {}

    // 4. Kandidaten met ingevuld formulier die nog in 'aangemeld' staan (klaar om te beoordelen)
    try {
        $q = $db->prepare("SELECT COUNT(DISTINCT s.id) FROM sollicitaties s
                           JOIN blk_formulieren b ON b.sollicitatie_id=s.id
                           WHERE s.seizoen_id=? AND s.fase='aangemeld'
                             AND b.motivatie_lifeguard IS NOT NULL AND b.motivatie_lifeguard <> ''");
        $q->execute([$sid]);
        $n = (int)$q->fetchColumn();
        if ($n > 0) $m[] = ['type'=>'info', 'aantal'=>$n,
            'tekst'=> $n === 1 ? '1 kandidaat heeft het formulier ingevuld en wacht op beoordeling' : "$n kandidaten hebben hun formulier ingevuld en wachten op beoordeling",
            'link'=> APP_URL.'/admin/sollicitanten.php?fase=aangemeld'];
    } catch (\Exception $e) {}

    return $m;
}
}

function huidige_beheerder(): array {
    return ['id' => $_SESSION['bhd_id'] ?? 0, 'naam' => $_SESSION['bhd_naam'] ?? '', 'email' => $_SESSION['bhd_email'] ?? '', 'rol' => $_SESSION['bhd_rol'] ?? 'begeleider', 'via' => $_SESSION['bhd_via'] ?? 'wachtwoord'];
}

// ── Portaal auth (sollicitant) ────────────────────────────────
function portaal_ingelogd(): bool { sessie_start(); return !empty($_SESSION['portaal_sol_id']); }

function login_portaal(int $sol_id, string $email): void {
    sessie_start(); session_regenerate_id(true);
    $_SESSION['portaal_sol_id'] = $sol_id; $_SESSION['portaal_email'] = $email; $_SESSION['_t'] = time();
}

// ── OTP ───────────────────────────────────────────────────────
function otp_genereer(string $email, string $type = 'portaal'): string {
    db()->prepare("DELETE FROM otp_tokens WHERE email = ? AND type = ?")->execute([$email, $type]);
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db()->prepare("INSERT INTO otp_tokens (email, code_hash, type, vervalt_op) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
       ->execute([$email, hash('sha256', $code), $type]);
    return $code;
}

function otp_verifieer(string $email, string $code, string $type = 'portaal'): bool {
    $t = db()->prepare("SELECT * FROM otp_tokens WHERE email = ? AND type = ? AND gebruikt = 0 AND vervalt_op > NOW() ORDER BY aangemaakt_op DESC LIMIT 1");
    $t->execute([$email, $type]); $token = $t->fetch();
    if (!$token || $token['pogingen'] >= 5) return false;
    db()->prepare("UPDATE otp_tokens SET pogingen = pogingen + 1 WHERE id = ?")->execute([$token['id']]);
    if (!hash_equals($token['code_hash'], hash('sha256', trim($code)))) return false;
    db()->prepare("UPDATE otp_tokens SET gebruikt = 1 WHERE id = ?")->execute([$token['id']]);
    return true;
}

// ── CSRF ──────────────────────────────────────────────────────
function csrf_token(): string { sessie_start(); if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); } return $_SESSION['csrf']; }
function csrf_input(): string { return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    sessie_start();
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) { http_response_code(403); die('Sessie verlopen. Ga terug en probeer opnieuw.'); }
}

// ── Flash ──────────────────────────────────────────────────────
function flash(string $type, string $msg): void { sessie_start(); $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
function get_flash(): ?array { sessie_start(); if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; } return null; }
function toon_flash(): void { $f = get_flash(); if ($f) echo '<div class="flash flash-' . h($f['type']) . '">' . h($f['msg']) . '</div>'; }

// ── Seizoen ───────────────────────────────────────────────────
function actief_seizoen(): ?array {
    static $s = null;
    if ($s === null) {
        try {
            // Kies het GEOPENDE seizoen (het meest recente als er meerdere open zijn).
            // Pas als er geen enkel seizoen open is, terugvallen op het nieuwste seizoen.
            $s = db()->query("SELECT * FROM seizoenen ORDER BY (status='open') DESC, id DESC LIMIT 1")->fetch() ?: null;
        } catch (Exception $e) { $s = null; }
    }
    return $s;
}

// ── Helpers ───────────────────────────────────────────────────
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }

function datum_nl(?string $d): string {
    if (!$d) return '—';
    try { $dt = new DateTime($d); $m = ['jan','feb','mrt','apr','mei','jun','jul','aug','sep','okt','nov','dec']; return $dt->format('j') . ' ' . $m[(int)$dt->format('n') - 1] . ' ' . $dt->format('Y'); }
    catch (Exception $e) { return $d; }
}

function volledige_naam(array $p): string {
    return implode(' ', array_filter([$p['voornaam'] ?? '', $p['tussenvoegsel'] ?? '', $p['achternaam'] ?? '']));
}

function leeftijd(?string $dob): string {
    if (!$dob) return '—';
    try { return (new DateTime($dob))->diff(new DateTime())->y . ' jr'; } catch (Exception $e) { return '—'; }
}

function genereer_token(int $bytes = 32): string { return bin2hex(random_bytes($bytes)); }

function fase_label(string $f): string {
    return ['aangemeld' => 'Aangemeld', 'uitgenodigd_selectie' => 'Uitgenodigd selectiedag', 'aangemeld_selectie' => 'Aangemeld selectiedag', 'selectiedag_geweest' => 'Selectiedag geweest', 'uitgenodigd_tw' => 'Uitgenodigd trainingsweekend', 'tw_geweest' => 'TW geweest', 'aangenomen' => 'Aangenomen', 'afgewezen' => 'Afgewezen'][$f] ?? $f;
}

function fase_kleur(string $f): string {
    return ['aangemeld' => 'grijs', 'uitgenodigd_selectie' => 'blauw', 'aangemeld_selectie' => 'oranje', 'selectiedag_geweest' => 'teal', 'uitgenodigd_tw' => 'paars', 'tw_geweest' => 'paars', 'aangenomen' => 'groen', 'afgewezen' => 'rood'][$f] ?? 'grijs';
}

function authentik_actief(): bool {
    return defined('OIDC_CLIENT_ID') && OIDC_CLIENT_ID !== '' && defined('OIDC_ISSUER') && OIDC_ISSUER !== '';
}

// Alias voor backwards compat
function authentik_geconfigureerd(): bool { return authentik_actief(); }

/**
 * Check via de Authentik API of een email bestaat. Vereist instelling 'authentik.api_token'.
 * Returns: array met user-info bij hit, false bij geen treffer of fout/uitgeschakeld.
 */
function authentik_email_bestaat(string $email): array|false {
    if (!authentik_actief()) return false;
    $token = instelling('authentik.api_token');
    if (empty($token)) return false;
    if (!function_exists('curl_init')) return false;

    $url = rtrim(OIDC_ISSUER, '/') . '/api/v3/core/users/?email=' . urlencode($email);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200) return false;
    $data = json_decode($resp, true);
    if (empty($data['results']) || !is_array($data['results'])) return false;

    // Zoek exacte match op email (Authentik kan partial matches teruggeven)
    foreach ($data['results'] as $u) {
        if (strcasecmp($u['email'] ?? '', $email) === 0 && !empty($u['is_active'])) {
            return $u;
        }
    }
    return false;
}

/**
 * Map Authentik groups naar app-rol. Bepaalt admin-toegang.
 * Returns: 'admin' | 'bestuur' | 'sol-admin' | 'sol-begeleider' | null (geen admin)
 */
function authentik_groups_naar_rol(array $groups): ?string {
    // Nieuw: gebruik de rollen-tabel (door de gebruiker beheerd via de rechtenmatrix).
    if (function_exists('rol_uit_authentik_groepen')) {
        $r = rol_uit_authentik_groepen($groups);
        if ($r !== null) return $r;
    }
    // Terugval op de oorspronkelijke hardcoded mapping (voor als de tabel nog leeg is).
    $map = [
        'admin'           => 'admin',
        'bestuur'         => 'bestuur',
        'sol-admin'       => 'admin',
        'sol-begeleider'  => 'begeleider',
        'sol_begeleider'  => 'begeleider',
    ];
    foreach ($groups as $g) {
        $g_low = strtolower($g);
        foreach ($map as $needle => $rol) {
            if ($g_low === $needle || str_contains($g_low, $needle)) return $rol;
        }
    }
    return null;
}

// Foto URL builder — werkt voor zowel oude (volledige URL) als nieuwe (relatief /uploads/...) paden
function foto_url(?string $opgeslagen): ?string {
    if (empty($opgeslagen)) return null;
    if (preg_match('#^https?://#', $opgeslagen)) return $opgeslagen;
    return rtrim(APP_URL, '/') . '/' . ltrim($opgeslagen, '/');
}

// Site logo URL (instelling → URL of bestand)
function site_logo_url(): ?string {
    $v = instelling('site.logo_url');
    return $v ? foto_url($v) : null;
}

// Site favicon URL (instelling → URL of bestand)
function site_favicon_url(): ?string {
    $v = instelling('site.favicon_url');
    return $v ? foto_url($v) : null;
}

function stuur_mail(string $naar, string $naam, string $onderwerp, string $body, ?int $sol_id = null): bool {
    return Mailer::verstuur($naar, $naam, $onderwerp, $body, $sol_id);
}

/**
 * Stuur uitnodigingsmail VEILIG: dubbel-mail bescherming via mail_log.
 * Stuurt mail tenzij dezelfde sollicitant in afgelopen $minuten al een mail met
 * vergelijkbaar onderwerp heeft gekregen. Retourneert:
 *   - 'verstuurd' : mail is succesvol verstuurd
 *   - 'al_verstuurd' : binnen tijdvenster al verstuurd, skip
 *   - 'fout' : verzending mislukt (controleer mail_log voor details)
 *   - 'geen_email' : sollicitant heeft geen e-mailadres
 */
function verstuur_fase_mail(
    int $sol_id,
    string $template_slug,
    string $onderwerp_zoek_patroon,
    array $seizoen = [],
    int $window_minuten = 60
): string {
    try {
        $db = db();
        $r = $db->prepare("SELECT s.id,p.voornaam,p.email,p.tussenvoegsel,p.achternaam,p.telefoon FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE s.id=?");
        $r->execute([$sol_id]);
        $row = $r->fetch();
        if (!$row || empty($row['email'])) return 'geen_email';

        // Dubbel-mail check
        $recent = $db->prepare("SELECT 1 FROM mail_log WHERE sollicitatie_id=? AND onderwerp LIKE ? AND status='verstuurd' AND verstuurd_op > DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 1");
        $recent->execute([$sol_id, $onderwerp_zoek_patroon, $window_minuten]);
        if ($recent->fetch()) return 'al_verstuurd';

        $sol_arr = [
            'voornaam'      => $row['voornaam'],
            'tussenvoegsel' => $row['tussenvoegsel'] ?? '',
            'achternaam'    => $row['achternaam']    ?? '',
            'email'         => $row['email'],
            'telefoon'      => $row['telefoon'] ?? '',
        ];
        $ok = stuur_template_mail($template_slug, $row['email'], $sol_arr, $seizoen, [], $sol_id);

        // Ook via SMS/WhatsApp als dat voor dit meldingstype is ingesteld en de sollicitant
        // een telefoonnummer heeft. De e-mail is hierboven al verstuurd.
        if (!empty($row['telefoon']) && function_exists('notificatie_kanalen')) {
            $k = notificatie_kanalen($template_slug);
            if (($k['sms'] || $k['wa']) && function_exists('notificatie_verstuur')) {
                $naam = trim($row['voornaam'] . ' ' . ($row['achternaam'] ?? ''));
                $kort = notificatie_korte_tekst($template_slug, $row['voornaam'], $seizoen);
                try {
                    notificatie_verstuur($template_slug, ['telefoon' => $row['telefoon'], 'naam' => $naam], ['kort' => $kort], $sol_id);
                } catch (\Exception $e) { error_log('fase-notificatie sms/wa: ' . $e->getMessage()); }
            }
        }
        return $ok ? 'verstuurd' : 'fout';
    } catch (\Exception $e) {
        error_log("verstuur_fase_mail: " . $e->getMessage());
        return 'fout';
    }
}

// ─────────────────────────────────────────────────────────────
//  Taak-meldingen (functioneel, wordt later herzien in mail-overhaul)
// ─────────────────────────────────────────────────────────────

/**
 * Stuur een simpele mail met dubbel-bescherming: skip als dezelfde sollicitant
 * in het tijdvenster al een mail met dit onderwerp kreeg. Gebruikt mail_log.
 * Retourneert: 'verstuurd' | 'al_verstuurd' | 'geen_email' | 'fout'.
 */
if (!function_exists('stuur_taak_mail')) {
function stuur_taak_mail(int $sol_id, string $onderwerp, string $body, int $window_minuten = 720): string {
    try {
        $db = db();
        $r = $db->prepare("SELECT p.voornaam, p.tussenvoegsel, p.achternaam, p.email
                           FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE s.id=?");
        $r->execute([$sol_id]);
        $row = $r->fetch();
        if (!$row || empty($row['email'])) return 'geen_email';

        // Dubbel-mail check op onderwerp
        try {
            $recent = $db->prepare("SELECT 1 FROM mail_log WHERE sollicitatie_id=? AND onderwerp=? AND status='verstuurd'
                                    AND verstuurd_op > DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 1");
            $recent->execute([$sol_id, $onderwerp, $window_minuten]);
            if ($recent->fetch()) return 'al_verstuurd';
        } catch (\Exception $e) { /* mail_log kolomnaam kan afwijken — dan gewoon versturen */ }

        $naam = trim($row['voornaam'] . ' ' . ($row['tussenvoegsel'] ? $row['tussenvoegsel'] . ' ' : '') . $row['achternaam']);
        return stuur_mail($row['email'], $naam, $onderwerp, $body, $sol_id) ? 'verstuurd' : 'fout';
    } catch (\Exception $e) {
        error_log("stuur_taak_mail: " . $e->getMessage());
        return 'fout';
    }
}
}

/** "Er staan taken voor je klaar" — bij uitnodiging selectiedag. */
if (!function_exists('mail_taken_klaar')) {
function mail_taken_klaar(int $sol_id): string {
    $url = rtrim(APP_URL, '/') . '/portaal/';
    $body = "<p>Hoi,</p>
<p>Er staan een paar acties voor je klaar in je Paal 13 portaal. Log in om ze in te vullen — denk aan het \"Beter leren kennen\"-formulier en (als je nog geen 18 bent) de gegevens van je ouder/verzorger.</p>
<p><a href=\"" . h($url) . "\">Ga naar je portaal &rarr;</a></p>
<p>Groet,<br>Reddingsbrigade Paal 13</p>";
    return stuur_taak_mail($sol_id, 'Er staan taken voor je klaar — Paal 13', $body);
}
}

/** "Taak afgerond" — bevestiging na afronden van een taak (bijv. ondertekening). */
if (!function_exists('mail_taak_afgerond')) {
function mail_taak_afgerond(int $sol_id, string $taak_naam): string {
    $url = rtrim(APP_URL, '/') . '/portaal/';
    $body = "<p>Hoi,</p>
<p>Goed nieuws: de taak <strong>" . h($taak_naam) . "</strong> is afgerond. ✅</p>
<p>Je kunt in je portaal zien of er nog andere acties openstaan.</p>
<p><a href=\"" . h($url) . "\">Ga naar je portaal &rarr;</a></p>
<p>Groet,<br>Reddingsbrigade Paal 13</p>";
    // Kort venster: elke afgeronde taak mag een eigen bevestiging sturen
    return stuur_taak_mail($sol_id, 'Taak afgerond: ' . $taak_naam . ' — Paal 13', $body, 5);
}
}


/** Zorgt dat personen de ouder/verzorger-velden hebben (naam, e-mail, telefoon). */
if (!function_exists('personen_ouder_auto_setup')) {
function personen_ouder_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        $db = db();
        $dbn = $db->query("SELECT DATABASE()")->fetchColumn();
        $bestaand = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='personen'");
        $bestaand->execute([$dbn]);
        $kolommen = array_flip($bestaand->fetchAll(PDO::FETCH_COLUMN));
        if (!isset($kolommen['ouder_naam']))     $db->exec("ALTER TABLE `personen` ADD COLUMN `ouder_naam` VARCHAR(120) DEFAULT NULL");
        if (!isset($kolommen['ouder_email']))    $db->exec("ALTER TABLE `personen` ADD COLUMN `ouder_email` VARCHAR(255) DEFAULT NULL");
        if (!isset($kolommen['ouder_telefoon'])) $db->exec("ALTER TABLE `personen` ADD COLUMN `ouder_telefoon` VARCHAR(30) DEFAULT NULL");
    } catch (\Exception $e) { error_log('personen_ouder_auto_setup: ' . $e->getMessage()); }
}
}

/**
 * Versie van de privacyverklaring waarmee sollicitanten akkoord gaan.
 * Verhoog dit nummer zodra de inhoud van de privacyverklaring wijzigt,
 * zodat vastgelegd blijft op welke versie iemand akkoord is gegaan.
 */
if (!defined('PRIVACY_VERSIE')) define('PRIVACY_VERSIE', '2026-08');

if (!function_exists('consent_auto_setup')) {
/**
 * Zorgt dat de kolommen voor toestemming-vastlegging bestaan op de sollicitaties-tabel.
 * Draait automatisch, voegt alleen toe wat nog ontbreekt (veilig om vaak aan te roepen).
 */
function consent_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        $db = db();
        $dbn = $db->query("SELECT DATABASE()")->fetchColumn();
        $bestaand = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='sollicitaties'");
        $bestaand->execute([$dbn]);
        $kol = array_flip($bestaand->fetchAll(PDO::FETCH_COLUMN));
        // Eigen privacy-toestemming van de sollicitant
        if (!isset($kol['privacy_akkoord_op'])) $db->exec("ALTER TABLE `sollicitaties` ADD COLUMN `privacy_akkoord_op` DATETIME DEFAULT NULL");
        if (!isset($kol['privacy_versie']))     $db->exec("ALTER TABLE `sollicitaties` ADD COLUMN `privacy_versie` VARCHAR(20) DEFAULT NULL");
        if (!isset($kol['privacy_ip']))         $db->exec("ALTER TABLE `sollicitaties` ADD COLUMN `privacy_ip` VARCHAR(45) DEFAULT NULL");
        // Ouderlijke toestemming — alleen gebruikt voor de 15-jarige jeugdledenuitzondering
        if (!isset($kol['ouder_toestemming_op']))   $db->exec("ALTER TABLE `sollicitaties` ADD COLUMN `ouder_toestemming_op` DATETIME DEFAULT NULL");
        if (!isset($kol['ouder_toestemming_naam'])) $db->exec("ALTER TABLE `sollicitaties` ADD COLUMN `ouder_toestemming_naam` VARCHAR(150) DEFAULT NULL");
    } catch (\Exception $e) { error_log('consent_auto_setup: ' . $e->getMessage()); }
}
}

/**
 * Berekent de leeftijd (in hele jaren) op basis van een geboortedatum.
 * Retourneert null als de datum ontbreekt of ongeldig is.
 */
if (!function_exists('leeftijd_uit_datum')) {
function leeftijd_uit_datum(?string $geboortedatum): ?int {
    if (empty($geboortedatum)) return null;
    try {
        // Exacte leeftijd via echte kalenderdatums — de 365.25-benadering rekent
        // sommige leeftijden een jaar te hoog door schrikkeljaar-afronding.
        return (new DateTime($geboortedatum))->diff(new DateTime())->y;
    } catch (Exception $e) {
        return null;
    }
}
}

function mail_queue_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `mail_queue` (
            `id`             INT AUTO_INCREMENT PRIMARY KEY,
            `naar_email`     VARCHAR(255) NOT NULL,
            `naar_naam`      VARCHAR(255) DEFAULT NULL,
            `onderwerp`      VARCHAR(255) NOT NULL,
            `body`           LONGTEXT NOT NULL,
            `sol_id`         INT DEFAULT NULL,
            `status`         ENUM('wacht','bezig','klaar','fout') NOT NULL DEFAULT 'wacht',
            `pogingen`       TINYINT NOT NULL DEFAULT 0,
            `fout`           TEXT DEFAULT NULL,
            `aangemaakt_op`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `verwerkt_op`    DATETIME DEFAULT NULL,
            INDEX `idx_wacht` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) {}
}

function mail_queue_toevoegen(string $naar, string $naam, string $onderwerp, string $body, ?int $sol_id = null): bool {
    mail_queue_auto_setup();
    try {
        db()->prepare("INSERT INTO mail_queue (naar_email,naar_naam,onderwerp,body,sol_id) VALUES (?,?,?,?,?)")
           ->execute([$naar, $naam, $onderwerp, $body, $sol_id]);
        return true;
    } catch (\Exception $e) { error_log("mail_queue: " . $e->getMessage()); return false; }
}

function mail_queue_verwerk(int $max = 20): array {
    mail_queue_auto_setup();
    $res = ['verwerkt'=>0, 'fouten'=>0];
    try {
        $rows = db()->prepare("SELECT * FROM mail_queue WHERE status='wacht' AND pogingen < 3 ORDER BY id ASC LIMIT ?");
        $rows->execute([$max]);
        $items = $rows->fetchAll();
    } catch (\Exception $e) { return $res; }

    foreach ($items as $m) {
        db()->prepare("UPDATE mail_queue SET status='bezig', pogingen=pogingen+1 WHERE id=?")->execute([$m['id']]);
        try {
            $ok = Mailer::verstuur($m['naar_email'], $m['naar_naam'] ?? '', $m['onderwerp'], $m['body'], $m['sol_id'] ?? null);
            if ($ok) {
                db()->prepare("UPDATE mail_queue SET status='klaar', verwerkt_op=NOW(), fout=NULL WHERE id=?")->execute([$m['id']]);
                $res['verwerkt']++;
            } else {
                $status = ($m['pogingen'] >= 2) ? 'fout' : 'wacht';
                db()->prepare("UPDATE mail_queue SET status=?, fout='Mailer.verstuur gaf false' WHERE id=?")->execute([$status, $m['id']]);
                $res['fouten']++;
            }
        } catch (\Exception $e) {
            $status = ($m['pogingen'] >= 2) ? 'fout' : 'wacht';
            db()->prepare("UPDATE mail_queue SET status=?, fout=? WHERE id=?")->execute([$status, $e->getMessage(), $m['id']]);
            $res['fouten']++;
        }
        usleep(200000); // 0.2s tussen mails om SMTP niet te overstelpen
    }
    return $res;
}

// ── Reminders: sollicitanten die ergens vastzitten ────────────
function reminder_run(): array {
    $res = ['blk_gemaild'=>0, 'sd_gemaild'=>0, 'sd_morgen_gemaild'=>0];
    $seizoen = actief_seizoen();
    if (!$seizoen) return $res;

    // 1. Aangemeld + geen BLK na X dagen (herhaalt elke 14 dagen)
    if (instelling('reminder.blk_aan', '1') === '1') {
        $blk_dgn = max(1, instelling_int('reminder.blk_dagen', 7));
        try {
            $q = db()->prepare("
                SELECT s.id, p.email, p.voornaam, p.tussenvoegsel, p.achternaam, p.telefoon
                FROM sollicitaties s
                JOIN personen p ON p.id = s.persoon_id
                LEFT JOIN blk_formulieren b ON b.sollicitatie_id = s.id
                WHERE s.seizoen_id = ?
                  AND s.fase = 'aangemeld'
                  AND b.id IS NULL
                  AND s.aangemeld_op <= DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND NOT EXISTS (
                    SELECT 1 FROM mail_log ml
                    WHERE ml.sollicitatie_id = s.id AND ml.onderwerp LIKE '%BLK formulier%'
                      AND ml.verstuurd_op > DATE_SUB(NOW(), INTERVAL 14 DAY)
                  )
            ");
            $q->execute([$seizoen['id'], $blk_dgn]);
            foreach ($q->fetchAll() as $k) {
                $sol_arr = ['voornaam'=>$k['voornaam'], 'tussenvoegsel'=>$k['tussenvoegsel']??'', 'achternaam'=>$k['achternaam']??'', 'email'=>$k['email'], 'telefoon'=>$k['telefoon']??''];
                $vars    = mail_variabelen($sol_arr, $seizoen);
                $vars['portaal_url'] = APP_URL . '/portaal/';
                $rendered = mail_template_render('reminder_blk', $vars);
                if (!$rendered) continue;
                $html = mail_html_wrapper($rendered['body_html'], $rendered['onderwerp'], $rendered['hero_titel'] ?? '');
                if (mail_queue_toevoegen($k['email'], $k['voornaam'], $rendered['onderwerp'], $html, (int)$k['id'])) {
                    $res['blk_gemaild']++;
                }
            }
        } catch (\Exception $e) { error_log("reminder blk: " . $e->getMessage()); }
    }

    // 2. Selectiedag komt eraan ("bijna zover") — Y dagen vooraf
    if (instelling('reminder.sd_aan', '1') === '1' && !empty($seizoen['datum_selectiedag'])) {
        $sd_dgn_vooraf = max(1, instelling_int('reminder.sd_dagen', 3));
        $dgn_tot_sd = (int)floor((strtotime($seizoen['datum_selectiedag']) - time()) / 86400);
        // Verstuur als selectiedag nog komt EN binnen drempel valt (maar niet morgen = aparte mail)
        if ($dgn_tot_sd > 1 && $dgn_tot_sd <= $sd_dgn_vooraf) {
            try {
                $q = db()->prepare("
                    SELECT s.id, p.email, p.voornaam, p.tussenvoegsel, p.achternaam, p.telefoon
                    FROM sollicitaties s
                    JOIN personen p ON p.id = s.persoon_id
                    WHERE s.seizoen_id = ?
                      AND s.fase IN ('uitgenodigd_selectie','aangemeld_selectie')
                      AND NOT EXISTS (
                        SELECT 1 FROM mail_log ml
                        WHERE ml.sollicitatie_id = s.id AND ml.onderwerp LIKE '%Bijna zover%'
                          AND ml.verstuurd_op > DATE_SUB(NOW(), INTERVAL 10 DAY)
                      )
                ");
                $q->execute([$seizoen['id']]);
                foreach ($q->fetchAll() as $k) {
                    $sol_arr = ['voornaam'=>$k['voornaam'], 'tussenvoegsel'=>$k['tussenvoegsel']??'', 'achternaam'=>$k['achternaam']??'', 'email'=>$k['email'], 'telefoon'=>$k['telefoon']??''];
                    $vars    = mail_variabelen($sol_arr, $seizoen);
                    $vars['portaal_url'] = APP_URL . '/portaal/';
                    $rendered = mail_template_render('reminder_sd_aankomst', $vars);
                    if (!$rendered) continue;
                    $html = mail_html_wrapper($rendered['body_html'], $rendered['onderwerp'], $rendered['hero_titel'] ?? '');
                    if (mail_queue_toevoegen($k['email'], $k['voornaam'], $rendered['onderwerp'], $html, (int)$k['id'])) {
                        $res['sd_gemaild']++;
                    }
                }
            } catch (\Exception $e) { error_log("reminder sd aankomst: " . $e->getMessage()); }
        }

        // 3. Selectiedag morgen — laatste praktische info
        if ($dgn_tot_sd === 1 && instelling('reminder.sd_morgen_aan', '1') === '1') {
            try {
                $q = db()->prepare("
                    SELECT s.id, p.email, p.voornaam, p.tussenvoegsel, p.achternaam, p.telefoon
                    FROM sollicitaties s
                    JOIN personen p ON p.id = s.persoon_id
                    WHERE s.seizoen_id = ?
                      AND s.fase IN ('uitgenodigd_selectie','aangemeld_selectie')
                      AND NOT EXISTS (
                        SELECT 1 FROM mail_log ml
                        WHERE ml.sollicitatie_id = s.id AND ml.onderwerp LIKE '%Morgen al%'
                          AND ml.verstuurd_op > DATE_SUB(NOW(), INTERVAL 3 DAY)
                      )
                ");
                $q->execute([$seizoen['id']]);
                foreach ($q->fetchAll() as $k) {
                    $sol_arr = ['voornaam'=>$k['voornaam'], 'tussenvoegsel'=>$k['tussenvoegsel']??'', 'achternaam'=>$k['achternaam']??'', 'email'=>$k['email'], 'telefoon'=>$k['telefoon']??''];
                    $vars    = mail_variabelen($sol_arr, $seizoen);
                    $vars['portaal_url']  = APP_URL . '/portaal/';
                    $vars['laatste_info'] = instelling('reminder.sd_morgen_info', '');
                    $rendered = mail_template_render('reminder_sd_morgen', $vars);
                    if (!$rendered) continue;
                    $html = mail_html_wrapper($rendered['body_html'], $rendered['onderwerp'], $rendered['hero_titel'] ?? '');
                    if (mail_queue_toevoegen($k['email'], $k['voornaam'], $rendered['onderwerp'], $html, (int)$k['id'])) {
                        $res['sd_morgen_gemaild']++;
                    }
                }
            } catch (\Exception $e) { error_log("reminder sd morgen: " . $e->getMessage()); }
        }
    }

    // 4. Open ondertekeningen na X dagen (max 1 herinnering per document)
    if (instelling('reminder.ondertekening_aan', '1') === '1') {
        $ond_dgn = max(1, instelling_int('reminder.ondertekening_dagen', 3));
        try {
            ondertekening_herinnerd_auto_setup();
            $q = db()->prepare("
                SELECT o.id, o.type, o.token, o.ondertekenaar_rol, o.ondertekenaar_naam, o.ondertekenaar_email,
                       p.voornaam AS sol_voornaam
                FROM ondertekeningen o
                JOIN sollicitaties s ON s.id = o.sollicitatie_id
                JOIN personen p ON p.id = s.persoon_id
                WHERE o.ondertekend_op IS NULL
                  AND o.herinnerd_op IS NULL
                  AND o.ondertekenaar_email IS NOT NULL
                  AND o.aangemaakt_op < DATE_SUB(NOW(), INTERVAL ? DAY)
                  AND s.seizoen_id = ?
            ");
            $q->execute([$ond_dgn, $seizoen['id']]);
            foreach ($q->fetchAll() as $o) {
                $type_label = match($o['type']) {
                    'gezondheid_ouder'  => 'Gezondheidsverklaring',
                    'gezondheid_18plus' => 'Gezondheidsverklaring',
                    'intake'            => 'Intakeformulier selectiedag',
                    'intake_ouder'      => 'Intakeformulier selectiedag',
                    default             => 'Formulier',
                };
                $is_ouder = ($o['ondertekenaar_rol'] === 'ouder');
                $url = APP_URL . '/portaal/ondertekenen.php?token=' . urlencode($o['token']);
                $ontvanger_naam = $o['ondertekenaar_naam'] ?: ($is_ouder ? 'ouder/verzorger' : $o['sol_voornaam']);
                $vars_r = [
                    'ontvanger_naam' => $ontvanger_naam,
                    'voornaam'       => $o['sol_voornaam'],
                    'document_naam'  => $type_label,
                    'onderteken_url' => $url,
                    'is_ouder'       => $is_ouder ? '1' : '0',
                ];
                $rendered_r = function_exists('ondertekening_mail_render') ? ondertekening_mail_render('reminder_ondertekening', $vars_r) : null;
                if ($rendered_r) {
                    $onderwerp = $rendered_r['onderwerp'];
                    $html = $rendered_r['html'];
                } else {
                    $onderwerp = "Herinnering: $type_label — Paal 13";
                    $html = '<p>Beste ' . htmlspecialchars($ontvanger_naam) . ',</p><p>Herinnering: <strong>' . $type_label . '</strong> staat nog open.</p><p><a href="' . htmlspecialchars($url) . '">Nu ondertekenen</a></p>';
                    if (function_exists('mail_simple_wrapper')) $html = mail_simple_wrapper($html, $onderwerp);
                }
                if (stuur_mail_html($o['ondertekenaar_email'], $o['ondertekenaar_naam'] ?: '', $onderwerp, $html, null)) {
                    db()->prepare("UPDATE ondertekeningen SET herinnerd_op=NOW() WHERE id=?")->execute([$o['id']]);
                    $res['ondertekening_gemaild'] = ($res['ondertekening_gemaild'] ?? 0) + 1;
                }
            }
        } catch (\Exception $e) { error_log("reminder ondertekening: " . $e->getMessage()); }
    }

    // 4. Beschikbaarheid nog voorlopig — deadline = 2 weken vóór trainingsweekend.
    //    Herinneringen starten 2 weken VÓÓR de deadline (mensen zijn lastig), herhalen elke 4 dagen.
    if (instelling('reminder.besch_aan', '1') === '1' && function_exists('beschikbaarheid2_deadline')) {
        $deadline = beschikbaarheid2_deadline($seizoen);
        if ($deadline) {
            $nu = new DateTime('today');
            $dgn_tot_deadline = (int)floor(($deadline->getTimestamp() - $nu->getTimestamp()) / 86400);
            // Start 14 dagen vóór de deadline; blijf mailen tot en met de deadline-dag en erna
            if ($dgn_tot_deadline <= 14) {
                try {
                    // Herhaal elke 4 dagen (na deadline: elke 2 dagen dringend)
                    $herhaal_dagen = $dgn_tot_deadline < 0 ? 2 : 4;
                    $q = db()->prepare("
                        SELECT s.id, p.email, p.voornaam, p.tussenvoegsel, p.achternaam, p.telefoon
                        FROM beschikbaarheid_profiel bp
                        JOIN sollicitaties s ON s.id = bp.sollicitatie_id
                        JOIN personen p ON p.id = s.persoon_id
                        WHERE bp.voorlopig = 1
                          AND s.seizoen_id = ?
                          AND s.fase NOT IN ('afgewezen','afgehaakt')
                          AND NOT EXISTS (
                            SELECT 1 FROM mail_log ml
                            WHERE ml.sollicitatie_id = s.id AND ml.onderwerp LIKE '%beschikbaarheid%definitief%'
                              AND ml.verstuurd_op > DATE_SUB(NOW(), INTERVAL ? DAY)
                          )
                    ");
                    $q->execute([$seizoen['id'], $herhaal_dagen]);
                    foreach ($q->fetchAll() as $k) {
                        $sol_arr = ['voornaam'=>$k['voornaam'], 'tussenvoegsel'=>$k['tussenvoegsel']??'', 'achternaam'=>$k['achternaam']??'', 'email'=>$k['email'], 'telefoon'=>$k['telefoon']??''];
                        $vars = mail_variabelen($sol_arr, $seizoen);
                        $vars['portaal_url']    = APP_URL . '/portaal/';
                        $vars['deadline_datum'] = datum_nl($deadline->format('Y-m-d'));
                        $vars['dagen_resterend']= max(0, $dgn_tot_deadline);
                        $vars['deadline_voorbij']= $dgn_tot_deadline < 0 ? '1' : '0';
                        $rendered = mail_template_render('reminder_beschikbaarheid', $vars);
                        if (!$rendered) continue;
                        $html = mail_html_wrapper($rendered['body_html'], $rendered['onderwerp'], $rendered['hero_titel'] ?? '');
                        if (mail_queue_toevoegen($k['email'], $k['voornaam'], $rendered['onderwerp'], $html, (int)$k['id'])) {
                            $res['besch_gemaild'] = ($res['besch_gemaild'] ?? 0) + 1;
                        }
                    }
                } catch (\Exception $e) { error_log("reminder beschikbaarheid: " . $e->getMessage()); }
            }
        }
    }

    return $res;
}

// Auto-setup: herinnerd_op kolom op ondertekeningen
function ondertekening_herinnerd_auto_setup(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = db();
        // Zorg eerst dat de tabel zelf bestaat (op een verse database komt die anders alleen via migratie 0016)
        ondertekeningen_auto_setup();
        $dbn = $db->query("SELECT DATABASE()")->fetchColumn();
        $c = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='ondertekeningen' AND COLUMN_NAME='herinnerd_op'");
        $c->execute([$dbn]);
        if (!$c->fetchColumn()) $db->exec("ALTER TABLE `ondertekeningen` ADD COLUMN `herinnerd_op` DATETIME DEFAULT NULL");
    } catch (\Exception $e) {}
}

if (!function_exists('ondertekeningen_auto_setup')) {
/**
 * Maakt de ondertekeningen-tabel aan als die nog niet bestaat.
 * Voorheen kwam deze tabel alleen via migratie 0016 — op een verse database (bijv. test)
 * ontbrak hij daardoor. Nu maakt het systeem hem zelf aan, net als de andere tabellen.
 */
function ondertekeningen_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `ondertekeningen` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `type` VARCHAR(30) NOT NULL,
            `token` VARCHAR(64) NOT NULL UNIQUE,
            `ondertekenaar_rol` VARCHAR(20) NOT NULL DEFAULT 'sollicitant',
            `ondertekenaar_naam` VARCHAR(150) DEFAULT NULL,
            `ondertekenaar_email` VARCHAR(200) DEFAULT NULL,
            `ondertekenaar_relatie` VARCHAR(30) DEFAULT NULL,
            `ondertekenaar_geboortedatum` DATE DEFAULT NULL,
            `ondertekenaar_geboorteplaats` VARCHAR(150) DEFAULT NULL,
            `formulier_data` MEDIUMTEXT DEFAULT NULL,
            `plaats` VARCHAR(150) DEFAULT NULL,
            `ondertekend_op` DATETIME DEFAULT NULL,
            `ondertekend_ip` VARCHAR(45) DEFAULT NULL,
            `ondertekend_user_agent` VARCHAR(500) DEFAULT NULL,
            `geldig_tot` DATE DEFAULT NULL,
            `herinnerd_op` DATETIME DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`sollicitatie_id`),
            INDEX (`type`),
            INDEX (`token`),
            INDEX (`ondertekend_op`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Handtekening-afbeelding (base64 PNG data-URL) — nieuw in de ontwerp-herbouw
        if (function_exists('db_kolom_verzeker')) {
            db_kolom_verzeker(db(), 'ondertekeningen', 'handtekening_beeld', 'MEDIUMTEXT DEFAULT NULL');
        }
    } catch (\Exception $e) { error_log('ondertekeningen_auto_setup: ' . $e->getMessage()); }
}
}

// ── Overige tabellen die voorheen alleen via migraties kwamen ──
// Op een verse database (bijv. testomgeving) ontbraken deze; nu maken ze zichzelf aan.
if (!function_exists('extra_tabellen_auto_setup')) {
function extra_tabellen_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    $db = db();
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `sollicitant_notities` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `beheerder_id` INT NOT NULL,
            `tekst` TEXT NOT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `gewijzigd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`sollicitatie_id`), INDEX (`aangemaakt_op`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `sollicitant_taken` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `taak_slug` VARCHAR(50) NOT NULL,
            `titel` VARCHAR(150) NOT NULL,
            `omschrijving` TEXT DEFAULT NULL,
            `link_url` VARCHAR(500) DEFAULT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'open',
            `verplicht` TINYINT(1) NOT NULL DEFAULT 1,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `voltooid_op` DATETIME DEFAULT NULL,
            INDEX (`sollicitatie_id`), INDEX (`status`),
            UNIQUE KEY `uniek_taak_per_sol` (`sollicitatie_id`, `taak_slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `blk_wijzigingen` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `reden` TEXT NOT NULL,
            `wijzigingen_json` JSON DEFAULT NULL,
            `gewijzigd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `otp_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(200) NOT NULL,
            `code_hash` VARCHAR(64) NOT NULL,
            `type` ENUM('portaal','beheerder') NOT NULL DEFAULT 'portaal',
            `pogingen` TINYINT NOT NULL DEFAULT 0,
            `gebruikt` TINYINT(1) NOT NULL DEFAULT 0,
            `vervalt_op` DATETIME NOT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_email_type` (`email`, `type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('extra_tabellen_auto_setup: ' . $e->getMessage()); }
}
}

// ── Lazy-cron: draait max 1x per uur bij admin-bezoek ─────────
function lazy_cron_tick(): void {
    try {
        $laatste = instelling('cron.laatste_run', '');
        if ($laatste && strtotime($laatste) > time() - 3600) return;
        instelling_opslaan('cron.laatste_run', date('Y-m-d H:i:s'));
        instelling_opslaan('cron.laatste_bron', 'lazy');
        if (function_exists('reminder_run')) reminder_run();
        if (function_exists('auto_invite_run')) auto_invite_run();
        if (function_exists('mail_queue_verwerk')) mail_queue_verwerk(10);
        if (function_exists('ai_queue_verwerk')) { try { ai_queue_verwerk(3); } catch (\Exception $e) {} }
    } catch (\Exception $e) { error_log('lazy_cron: ' . $e->getMessage()); }
}

// ── Auto-invite: stuur uitnodigingen op basis van criteria ────
function auto_invite_run(): array {
    $res = ['gecheckt'=>0, 'uitgenodigd'=>0, 'overgeslagen'=>0];
    if (instelling('invite.modus', 'suggestie') !== 'auto') return $res;
    if (!function_exists('invite_aanbeveling')) return $res;

    $delay_uur = instelling_int('invite.delay_uur', 24);
    $seizoen = actief_seizoen();
    if (!$seizoen) return $res;

    try {
        // Sollicitanten met fase 'aangemeld', minimaal X uur geleden aangemeld
        // 0 uur = direct uitnodigen (bij eerstvolgende cron run)
        $q = db()->prepare("
            SELECT s.id, s.persoon_id, p.email, p.voornaam, p.geboortedatum
            FROM sollicitaties s
            JOIN personen p ON p.id = s.persoon_id
            WHERE s.seizoen_id = ?
              AND s.fase = 'aangemeld'
              AND (? = 0 OR s.aangemeld_op <= DATE_SUB(NOW(), INTERVAL ? HOUR))
        ");
        $q->execute([$seizoen['id'], $delay_uur, $delay_uur]);
        $kandidaten = $q->fetchAll();
    } catch (\Exception $e) { return $res; }

    foreach ($kandidaten as $k) {
        $res['gecheckt']++;
        $lft = leeftijd_op_datum($k['geboortedatum'], $seizoen['datum_werk_start'] ?? date('Y').'-06-01');
        $ok  = invite_aanbeveling($lft);

        if (!$ok['voldoet']) { $res['overgeslagen']++; continue; }

        // Zet eerst de fase, verstuur dan de mail via dezelfde weg als de handmatige route:
        // de bewerkbare template 'uitnodiging_selectie'. Zo krijgen auto-invite en handmatig
        // uitnodigen exact dezelfde (bewerkbare) mail — niets hardgecodeerd.
        db()->prepare("UPDATE sollicitaties SET fase='uitgenodigd_selectie' WHERE id=?")->execute([$k['id']]);
        if (function_exists('verstuur_fase_mail')) {
            try {
                verstuur_fase_mail((int)$k['id'], 'uitnodiging_selectie', '%selectiedag%', $seizoen, 60);
            } catch (\Exception $e) { error_log('auto-invite mail: ' . $e->getMessage()); }
        }
        $res['uitgenodigd']++;
    }
    return $res;
}
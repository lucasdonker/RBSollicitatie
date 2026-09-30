<?php
// ── Config laden ──────────────────────────────────────────────
$_config = __DIR__ . '/config.php';
if (file_exists($_config)) {
    require_once $_config;
} else {
    // Geen config: de installatiewizard bestaat niet meer (2.94.0). Installeren gaat via het
    // runbook: config.template.php kopiëren naar config.php en invullen.
    if (php_sapi_name() !== 'cli') {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Nog niet ingesteld</title></head>'
           . '<body style="font-family:sans-serif;max-width:520px;margin:60px auto;padding:0 20px;line-height:1.6;color:#1a2230">'
           . '<h2 style="color:#054d8a">Systeem is nog niet ingesteld</h2>'
           . '<p>Het configuratiebestand ontbreekt op de server. Zie het runbook voor systeembeheer '
           . '(<code>docs/handleiding/runbook_systeembeheer.md</code>).</p></body></html>';
    }
    exit;
}

require_once __DIR__ . '/na_antwoord.php';
require_once __DIR__ . '/smtp.php';
if (file_exists(__DIR__ . '/versie.php'))        require_once __DIR__ . '/versie.php';
if (file_exists(__DIR__ . '/mail_render.php'))   require_once __DIR__ . '/mail_render.php';
if (file_exists(__DIR__ . '/error_handler.php')) require_once __DIR__ . '/error_handler.php';
if (file_exists(__DIR__ . '/instellingen.php'))  require_once __DIR__ . '/instellingen.php';
if (file_exists(__DIR__ . '/validatie.php'))      require_once __DIR__ . '/validatie.php';
if (file_exists(__DIR__ . '/ratelimit.php'))      require_once __DIR__ . '/ratelimit.php';
if (file_exists(__DIR__ . '/kluis.php'))          require_once __DIR__ . '/kluis.php';
if (file_exists(__DIR__ . '/upload.php'))         require_once __DIR__ . '/upload.php';
if (file_exists(__DIR__ . '/documenten.php'))     require_once __DIR__ . '/documenten.php';
if (file_exists(__DIR__ . '/foto.php'))           require_once __DIR__ . '/foto.php';
if (file_exists(__DIR__ . '/ai_analyse.php'))      require_once __DIR__ . '/ai_analyse.php';
if (file_exists(__DIR__ . '/kennismakingspakket.php')) require_once __DIR__ . '/kennismakingspakket.php';
if (file_exists(__DIR__ . '/ondertekening_tekst.php')) require_once __DIR__ . '/ondertekening_tekst.php';
if (file_exists(__DIR__ . '/ondertekeningen_auto.php')) require_once __DIR__ . '/ondertekeningen_auto.php';
if (file_exists(__DIR__ . '/ondertekening_bewijs.php')) require_once __DIR__ . '/ondertekening_bewijs.php';
if (file_exists(__DIR__ . '/zegel.php')) require_once __DIR__ . '/zegel.php';
if (file_exists(__DIR__ . '/ondertekening_pdf.php')) require_once __DIR__ . '/ondertekening_pdf.php';
if (file_exists(__DIR__ . '/loonheffing_pdf.php')) require_once __DIR__ . '/loonheffing_pdf.php';
if (file_exists(__DIR__ . '/lidmaatschap.php')) require_once __DIR__ . '/lidmaatschap.php';
// Moduleregister vóór de rechten: rechten_modules() en mag() vragen het register wat aanstaat.
if (file_exists(__DIR__ . '/modules.php')) require_once __DIR__ . '/modules.php';
if (file_exists(__DIR__ . '/rechten.php')) require_once __DIR__ . '/rechten.php';
if (file_exists(__DIR__ . '/beschikbaarheid_dagen.php')) require_once __DIR__ . '/beschikbaarheid_dagen.php';
if (file_exists(__DIR__ . '/beschikbaarheid2.php')) require_once __DIR__ . '/beschikbaarheid2.php';
if (file_exists(__DIR__ . '/beschikbaarheid_post.php')) require_once __DIR__ . '/beschikbaarheid_post.php';
if (file_exists(__DIR__ . '/beschikbaarheid_dag.php')) require_once __DIR__ . '/beschikbaarheid_dag.php';
if (file_exists(__DIR__ . '/competenties.php')) require_once __DIR__ . '/competenties.php';
if (file_exists(__DIR__ . '/bezetting.php')) require_once __DIR__ . '/bezetting.php';
if (file_exists(__DIR__ . '/loonplan.php')) require_once __DIR__ . '/loonplan.php';
if (file_exists(__DIR__ . '/loonplan_afspraak.php')) require_once __DIR__ . '/loonplan_afspraak.php';
if (file_exists(__DIR__ . '/loonplan_slot.php')) require_once __DIR__ . '/loonplan_slot.php';
if (file_exists(__DIR__ . '/inzet_rooster.php')) require_once __DIR__ . '/inzet_rooster.php';
if (file_exists(__DIR__ . '/loonplan_export.php')) require_once __DIR__ . '/loonplan_export.php';
if (file_exists(__DIR__ . '/xlsx.php')) require_once __DIR__ . '/xlsx.php';
if (file_exists(__DIR__ . '/selectiedag.php')) require_once __DIR__ . '/selectiedag.php';
if (file_exists(__DIR__ . '/trainingsweekend.php')) require_once __DIR__ . '/trainingsweekend.php';
// Wat selectiedag en trainingsweekend delen: inchecken, afsluiten, ik kom/kan niet, vergrendeling.
if (file_exists(__DIR__ . '/evenement.php')) require_once __DIR__ . '/evenement.php';
if (file_exists(__DIR__ . '/evenement_groepen.php')) require_once __DIR__ . '/evenement_groepen.php';
if (file_exists(__DIR__ . '/lg_feedback.php')) require_once __DIR__ . '/lg_feedback.php';
if (file_exists(__DIR__ . '/intrekken.php')) require_once __DIR__ . '/intrekken.php';
if (file_exists(__DIR__ . '/beheerder_bezoek.php')) require_once __DIR__ . '/beheerder_bezoek.php';
if (file_exists(__DIR__ . '/evenement_formulieren.php')) require_once __DIR__ . '/evenement_formulieren.php';
if (file_exists(__DIR__ . '/ai_beschikbaarheid.php')) require_once __DIR__ . '/ai_beschikbaarheid.php';
if (file_exists(__DIR__ . '/opschonen.php')) require_once __DIR__ . '/opschonen.php';
if (file_exists(__DIR__ . '/db_failsafe.php')) require_once __DIR__ . '/db_failsafe.php';
if (file_exists(__DIR__ . '/audit.php')) require_once __DIR__ . '/audit.php';
// Logboek: leest de zes logbronnen uit als één tijdlijn (zie includes/logboek/bronnen.php).
foreach (['bronnen', 'query', 'weergave'] as $__l) {
    if (file_exists(__DIR__ . '/logboek/' . $__l . '.php')) require_once __DIR__ . '/logboek/' . $__l . '.php';
}
if (file_exists(__DIR__ . '/toegang.php')) require_once __DIR__ . '/toegang.php';
if (file_exists(__DIR__ . '/health.php')) require_once __DIR__ . '/health.php';
// Het aanmeldformulier (alleen de presentatie) vóór paginas.php: includes/paginas/formulier.php
// en render.php leunen erop. De verwerking (aanmelden_kern.php) blijft bewust los — die laadt
// alleen wie hem echt nodig heeft.
if (file_exists(__DIR__ . '/privacy_verklaring.php')) require_once __DIR__ . '/privacy_verklaring.php';
if (file_exists(__DIR__ . '/aanmeldformulier.php')) require_once __DIR__ . '/aanmeldformulier.php';
if (file_exists(__DIR__ . '/paginas.php')) require_once __DIR__ . '/paginas.php';
if (file_exists(__DIR__ . '/kanalen.php')) require_once __DIR__ . '/kanalen.php';
if (file_exists(__DIR__ . '/optout.php')) require_once __DIR__ . '/optout.php';
if (file_exists(__DIR__ . '/seintje.php')) require_once __DIR__ . '/seintje.php';
if (file_exists(__DIR__ . '/avg_wissen.php')) require_once __DIR__ . '/avg_wissen.php';
// Opmaak van de korte tekst (WhatsApp/sms) en de variabelenlijst; de verzendkern gebruikt kort_opmaak_strip().
if (file_exists(__DIR__ . '/berichten_opmaak.php')) require_once __DIR__ . '/berichten_opmaak.php';
// Centrale meldingen (register, sjablonen, verzendkern) — ná kanalen/optout/mail_render.
foreach (['registry', 'sjablonen', 'verzend', 'bijlagen', 'herinneringen', 'gepland'] as $__m) {
    if (file_exists(__DIR__ . '/meldingen/' . $__m . '.php')) require_once __DIR__ . '/meldingen/' . $__m . '.php';
}
if (file_exists(__DIR__ . '/fase.php')) require_once __DIR__ . '/fase.php';
if (file_exists(__DIR__ . '/cron_taken.php')) require_once __DIR__ . '/cron_taken.php';
if (file_exists(__DIR__ . '/export_dossier.php')) require_once __DIR__ . '/export_dossier.php';
if (file_exists(__DIR__ . '/iconen.php')) require_once __DIR__ . '/iconen.php';
if (file_exists(__DIR__ . '/ui.php')) require_once __DIR__ . '/ui.php';
if (file_exists(__DIR__ . '/hulp.php')) require_once __DIR__ . '/hulp.php';
if (file_exists(__DIR__ . '/blk.php')) require_once __DIR__ . '/blk.php';
if (file_exists(__DIR__ . '/portaal_dossier.php')) require_once __DIR__ . '/portaal_dossier.php';
if (file_exists(__DIR__ . '/portaal_taken.php')) require_once __DIR__ . '/portaal_taken.php';
if (file_exists(__DIR__ . '/portaal_berichten.php')) require_once __DIR__ . '/portaal_berichten.php';
if (file_exists(__DIR__ . '/portaal_login.php')) require_once __DIR__ . '/portaal_login.php';

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
            // De echte oorzaak (host, databasenaam, gebruiker) hoort in het log, niet op het scherm.
            error_log('P13-D001 databaseverbinding mislukt: ' . $e->getMessage());
            if (php_sapi_name() === 'cli') { throw $e; }
            if (!headers_sent()) { http_response_code(503); header('Retry-After: 60'); }
            $foutcode_html = 'P13-D001';
            include __DIR__ . '/../500.php';
            exit;
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

// ── HTTPS en beveiligingsheaders ──────────────────────────────
/**
 * Draait dit verzoek over https? Achter de Plesk-proxy (nginx vóór Apache/FPM) is $_SERVER['HTTPS']
 * niet altijd gezet; dan telt de X-Forwarded-Proto-header, en anders het schema van APP_URL.
 */
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
    return defined('APP_URL') && str_starts_with(strtolower(APP_URL), 'https://');
}

/**
 * Welke andere websites mogen dit systeem aanspreken of insluiten: de hoofdsite paal13.com plus
 * wat het bestuur bij Instellingen › Website & API onder "origins" heeft gezet. Eén bron voor de
 * API-CORS (api/_basis.php) en voor het insluiten van het aanmeldformulier in een iframe.
 */
function site_toegestane_origins(): array {
    $ruw = function_exists('instelling') ? (string)instelling('api.origins', '') : '';
    $lijst = array_filter(array_map('trim', explode("\n", str_replace(',', "\n", $ruw))));
    $lijst[] = 'https://paal13.com';
    $lijst[] = 'https://www.paal13.com';
    return array_values(array_unique($lijst));
}

/**
 * Eén willekeurige nonce per verzoek voor inline scriptblokken (CSP: script-src 'nonce-…').
 * Schrijf een inline script als: script-tag met nonce="<?= h(csp_nonce()) ?>". Zonder nonce draait
 * het niet — met opzet. Inline gebeurtenissen (onclick en dergelijke) bestaan niet meer: die lopen
 * via data-klik / data-wijzig / … (assets/js/klik.js).
 */
function csp_nonce(): string {
    static $nonce = null;
    if ($nonce === null) $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    return $nonce;
}

/**
 * Beveiligingsheaders voor het beheer en het portaal. Eén keer per verzoek, vóór de eerste uitvoer;
 * de eerste aanroep wint (sessie_start roept dit ook aan). Landingspagina's zetten hun eigen,
 * strengere CSP (includes/paginas/render.php) en hebben geen sessie, dus die komen hier niet.
 * Niet in .htaccess: onder PHP-FPM is niet zeker dat Apache-directieven doorkomen.
 *
 * @param array|null $frame_ancestors  Origins die deze pagina in een iframe mogen tonen (alleen het
 *                                     aanmeldformulier op paal13.com); standaard alleen de eigen host.
 */
function security_headers(?array $frame_ancestors = null): void {
    static $gedaan = false;
    if ($gedaan || headers_sent() || php_sapi_name() === 'cli') return;
    $gedaan = true;
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=()');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    if ($frame_ancestors) {
        $ancestors = "'self' " . implode(' ', array_map(fn($o) => preg_replace('/[^A-Za-z0-9:.\/\-*]/', '', $o), $frame_ancestors));
    } else {
        $ancestors = "'self'";
        header('X-Frame-Options: SAMEORIGIN');
    }
    // Scripts: alleen van de eigen host of een inline blok met de nonce van dit verzoek. Geen
    // 'unsafe-inline' meer — onclick="…" en dergelijke werken dus niet (NIS2: geïnjecteerde HTML kan geen
    // script uitvoeren); gebeurtenissen lopen via data-klik (assets/js/klik.js).
    // Stijlen houden 'unsafe-inline': style="--w: 42%" voor data-waarden (zie CLAUDE.md). CSS kan geen
    // code uitvoeren; het risico daarvan is klein en bewust geaccepteerd.
    // Camera-upload (blob:) en het handtekening-canvas (data:) hebben img-src nodig.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . csp_nonce() . "'; "
         // Lettertypen komen van de eigen host (assets/fonts): geen Google Fonts, dus geen IP naar Google.
         . "style-src 'self' 'unsafe-inline'; font-src 'self' data:; "
         . "img-src 'self' data: blob: https:; connect-src 'self'; frame-ancestors $ancestors; "
         . "form-action 'self' https:; base-uri 'self'; object-src 'none'");
}

// ── Sessie ────────────────────────────────────────────────────
function sessie_start(): void {
    security_headers();
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        /* lifetime stond op 0: de sessie verviel zodra de browser dichtging. Op een
           telefoon is dat zo'n beetje elk bezoek, dus moest een sollicitant telkens
           opnieuw de hele inlogprocedure door. Nu leeft de cookie acht uur, lang
           genoeg voor een dag portaalgebruik.
           Wie langer ingelogd wil blijven vinkt "blijf ingelogd" aan; dat gaat via een
           eigen token (includes/portaal_login.php) en niet via deze cookie — een
           sessiecookie van zestig dagen zou óók voor het beheer gelden. */
        session_set_cookie_params(['lifetime' => 8 * 3600, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        // De sessie op de server even lang laten leven als de cookie; de PHP-standaard is 24 minuten,
        // dus wie een formulier even liet liggen, verloor bij versturen alles.
        ini_set('session.gc_maxlifetime', (string)(8 * 3600));
        session_start();
        if (!isset($_SESSION['_t'])) { $_SESSION['_t'] = time(); }
        elseif (time() - $_SESSION['_t'] > 1800) { session_regenerate_id(true); $_SESSION['_t'] = time(); }
    }
}

// ── Beheerder auth ────────────────────────────────────────────
/**
 * Na zoveel seconden zonder verzoek is een beheersessie verlopen, ook al leeft de cookie acht
 * uur (NIS2: een onbeheerde laptop met het beheer open). Twee uur: een selectiedag of gesprekkenronde
 * met pauzes past erin; wie langer weg is, logt opnieuw in (met SSO is dat één klik).
 * Het portaal valt hier bewust buiten: dat gaat over de eigen gegevens van één sollicitant.
 */
const BEHEER_INACTIEF_SECONDEN = 7200;

function is_ingelogd(): bool {
    sessie_start();
    if (empty($_SESSION['bhd_id'])) return false;
    $laatst = (int)($_SESSION['bhd_actief'] ?? 0);
    if ($laatst > 0 && time() - $laatst > BEHEER_INACTIEF_SECONDEN) {
        if (function_exists('audit_log')) audit_log('uitgelogd', 'beheerder', (int)$_SESSION['bhd_id'], ['reden' => 'inactief']);
        unset($_SESSION['bhd_id'], $_SESSION['bhd_naam'], $_SESSION['bhd_email'], $_SESSION['bhd_rol'], $_SESSION['bhd_rollen'], $_SESSION['bhd_groepen'], $_SESSION['bhd_via'], $_SESSION['bhd_actief']);
        session_regenerate_id(true);
        flash('info', 'Je was twee uur niet actief in het beheer en bent daarom uitgelogd. Log opnieuw in om verder te gaan.');
        return false;
    }
    $_SESSION['bhd_actief'] = time();
    return true;
}

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

/**
 * Eist een minimale rolrang.
 *
 * LET OP — rang is een hiërarchie, en niet elke rol staat in die hiërarchie op de plek
 * die je zou verwachten: de penningmeester heeft dezelfde rang als het bestuur maar
 * mag heel andere dingen. Gebruik daarom bij voorkeur mag()/vereis_recht() met een
 * module; deze functie alleen waar het écht om "minstens deze rang" gaat.
 *
 * Wat er na de herbouw nog op deze functie staat, en waarom:
 *   bouwstenen.php  — gereedschap voor wie het systeem bouwt, geen beheerscherm
 *   update.php      — draait migraties met een eigen databaseverbinding, hoort bij deployen
 *   import.php      — massa-import van personen. Zou 'sollicitanten.bewerken' kunnen zijn,
 *                     maar dat geeft het bestuur er een bevoegdheid bij die het nu niet
 *                     heeft. Dat is een keuze voor Lucas, geen opruimklus.
 * De rest is omgezet naar vereis_recht() met een module.
 *
 * De weigering rendert dezelfde pagina als vereis_recht(): geen rechten is een keuze,
 * geen storing (ontwerpfilosofie §8). Eerder stond hier een kale die() met een <h2>.
 */
function vereist_rol(string $min): void {
    vereist_login();
    if (rol_rang($_SESSION['bhd_rol'] ?? 'begeleider') >= rol_rang($min)) return;

    http_response_code(403);

    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_starts_with($script, 'api_')) {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['ok' => false, 'fout' => 'geen_rechten', 'rol_minimaal' => $min]));
    }

    $layout  = __DIR__ . '/../admin/layout_top.php';
    $partial = __DIR__ . '/../admin/_geen_rechten.php';
    if (is_file($layout) && is_file($partial)) {
        $pagina_titel = 'Geen toegang';
        $actieve_nav  = '';
        require $layout;
        $geen_module = null;
        $geen_rol    = (string)($_SESSION['bhd_rol'] ?? '');
        require $partial;
        require __DIR__ . '/../admin/layout_bottom.php';
        exit;
    }

    $terug = defined('APP_URL') ? APP_URL . '/admin/' : '/admin/';
    die('<div style="font-family:sans-serif;max-width:520px;margin:80px auto;text-align:center">'
      . '<h2>Geen toegang</h2><p>Je rol heeft hier geen inzage. Dat is een bewuste keuze, geen storing.</p>'
      . '<p><a href="' . htmlspecialchars($terug) . '">Terug naar Vandaag</a></p></div>');
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

/**
 * @param array $groepen  de groepen die Authentik bij deze login stuurde (leeg bij wachtwoord of wisselen).
 *                        Alleen voor de uitleg op Mijn profiel en het logboek: welke rol kwam waarvandaan.
 */
function login_beheerder(array $b, string $via = 'wachtwoord', array $groepen = []): void {
    sessie_start();
    session_regenerate_id(true);
    $_SESSION['bhd_id'] = $b['id']; $_SESSION['bhd_naam'] = $b['naam'];
    $_SESSION['bhd_email'] = $b['email']; $_SESSION['bhd_rol'] = $b['rol'];
    $_SESSION['bhd_rollen'] = array_values(array_filter(explode(',', (string)($b['extra_rollen'] ?? ''))));
    $_SESSION['bhd_groepen'] = array_slice(array_values(array_map('strval', $groepen)), 0, 50);
    $_SESSION['bhd_via'] = $via; $_SESSION['_t'] = time(); $_SESSION['bhd_actief'] = time();
    try { db()->prepare("UPDATE beheerders SET laatste_login = NOW() WHERE id = ?")->execute([$b['id']]); } catch (Exception $e) {}
    if (function_exists('audit_log')) {
        $details = ['via' => $via];
        if (function_exists('huidige_rollen')) $details['rollen'] = huidige_rollen();
        if ($groepen) $details['groepen'] = $_SESSION['bhd_groepen'];
        audit_log('ingelogd', 'beheerder', (int)$b['id'], $details);
    }
}

// ── Brute-force bescherming (login) ───────────────────────────
if (!function_exists('login_pogingen_auto_setup')) {
function login_pogingen_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
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
                             AND " . blk_ingediend_sql('b', 's'));
        $q->execute([$sid]);
        $n = (int)$q->fetchColumn();
        if ($n > 0) $m[] = ['type'=>'info', 'aantal'=>$n,
            'tekst'=> $n === 1 ? '1 sollicitant heeft het formulier ingevuld en wacht op beoordeling' : "$n sollicitanten hebben hun formulier ingevuld en wachten op beoordeling",
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

/** Elke portaal-pagina (behalve login/token-pagina's) begint hiermee: niet ingelogd → inlogscherm. */
function portaal_vereis_login(): int {
    if (!portaal_ingelogd()) redirect(APP_URL . '/portaal/');
    return (int)$_SESSION['portaal_sol_id'];
}

/**
 * Voor partials (portaal/_kop.php enz.): alleen via require te gebruiken. Direct opgevraagd in de
 * browser → niet ingelogd: inlogscherm; ingelogd: de 404-pagina (een partial is geen pagina).
 */
function portaal_alleen_include(string $bestand): void {
    $script = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if (!$script || $script !== realpath($bestand)) return;
    portaal_vereis_login();
    pagina_404();
}

/** Eigen 404-pagina (404.php in de root, ook Apache's ErrorDocument) tonen en stoppen. */
function pagina_404(): never {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

function login_portaal(int $sol_id, string $email): void {
    sessie_start(); session_regenerate_id(true);
    $_SESSION['portaal_sol_id'] = $sol_id; $_SESSION['portaal_email'] = $email; $_SESSION['_t'] = time();
}

// ── OTP ───────────────────────────────────────────────────────
/**
 * Nieuwe eenmalige code voor een sleutel ($type): 'portaal', 'beheerder' of 'ond<id>' (ondertekenen-2FA).
 * De kolom otp_tokens.type was oorspronkelijk een ENUM; op zo'n database wordt een andere sleutel stil als
 * lege string opgeslagen en is de code daarna nooit te verifiëren. db_failsafe() zet de kolom om naar
 * VARCHAR; de controle hieronder zorgt dat het probleem anders meteen zichtbaar is in plaats van een code
 * die niet werkt.
 */
function otp_genereer(string $email, string $type = 'portaal'): string {
    db()->prepare("DELETE FROM otp_tokens WHERE email = ? AND type = ?")->execute([$email, $type]);
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db()->prepare("INSERT INTO otp_tokens (email, code_hash, type, vervalt_op) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
       ->execute([$email, hash('sha256', $code), $type]);
    try {
        $c = db()->prepare("SELECT `type` FROM otp_tokens WHERE id = ?");
        $c->execute([(int)db()->lastInsertId()]);
        $opgeslagen = $c->fetchColumn();
        if ($opgeslagen !== false && (string)$opgeslagen !== $type) {
            error_log("otp_genereer: type '$type' werd opgeslagen als '" . (string)$opgeslagen . "' — kolom otp_tokens.type te beperkt (ENUM?).");
            throw new RuntimeException('otp_type_niet_opgeslagen');
        }
    } catch (PDOException $e) { error_log('otp_genereer controle: ' . $e->getMessage()); }
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
/**
 * Klopt het formulier-token niet (meestal: de sessie is verlopen), dan geen kale 403 met platte
 * tekst maar iets waar de gebruiker mee verder kan. Een fetch krijgt JSON (de schermen tonen
 * 'fout'); een gewoon formulier gaat terug naar dezelfde pagina met een melding. Die pagina
 * stuurt wie niet meer is ingelogd vanzelf naar het inlogscherm, waar de melding ook staat.
 */
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    sessie_start();
    if (hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) return;
    $tekst = 'Je sessie was verlopen, daardoor is dit niet opgeslagen. Probeer het nog een keer.';
    $modus = (string)($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '');
    if (($modus !== '' && $modus !== 'navigate') || !empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'fout' => $tekst . ' Ververs de pagina.']);
        exit;
    }
    flash('waarschuwing', $tekst);
    // Terug naar de pagina met het formulier (sommige formulieren posten naar een los script),
    // maar alleen binnen dit domein: nooit een open redirect.
    $terug = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $ref   = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $pad   = (string)(parse_url($ref, PHP_URL_PATH) ?: '/');
    if ($ref !== '' && strcasecmp((string)parse_url($ref, PHP_URL_HOST), (string)parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) === 0
        && str_starts_with($pad, '/') && !str_starts_with($pad, '//') && !str_contains($pad, '\\')) {
        $terug = $pad . (($q = parse_url($ref, PHP_URL_QUERY)) ? '?' . $q : '');
    }
    header('Location: /' . ltrim($terug, '/'), true, 303);
    exit;
}

// ── Flash ──────────────────────────────────────────────────────
function flash(string $type, string $msg): void { sessie_start(); $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
function get_flash(): ?array { sessie_start(); if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; } return null; }
/* Een fout wordt meteen voorgelezen (role=alert), de rest als status. Alleen een
   succesmelding verdwijnt vanzelf (layout_bottom.php); een fout of waarschuwing blijft
   staan tot de volgende pagina — wegvagen voordat je hem gelezen hebt helpt niemand (WCAG 2.2.1). */
/* Eén melding voor beheer én portaal: de bouwsteen ds-melding (kleuren via tokens, ook donker).
   De portaalkop (portaal/_kop.php) en layout_top.php roepen dit aan; zet een melding dus altijd met
   flash($type, $tekst): succes | info | waarschuwing | fout. De klassen flash/flash-<type> blijven
   als haak (layout_bottom.php laat alleen succes vervagen). */
function toon_flash(): void {
    $f = get_flash();
    if (!$f) return;
    $type = (string)$f['type'];
    $rol  = in_array($type, ['fout', 'error', 'waarschuwing'], true) ? 'alert' : 'status';
    $stijl = ['succes' => ['ok', 'vink-cirkel'], 'fout' => ['error', 'let-op'], 'error' => ['error', 'let-op'],
              'waarschuwing' => ['warn', 'waarschuwing']][$type] ?? ['info', 'info'];
    $ico = function_exists('icoon') ? icoon($stijl[1], 18) : '';
    echo '<div class="ds-melding ds-melding-' . $stijl[0] . ' flash flash-' . h($type) . '" role="' . $rol . '">'
       . $ico . '<span>' . h((string)$f['msg']) . '</span></div>';
}

// ── Seizoen ───────────────────────────────────────────────────
function actief_seizoen(): ?array {
    static $s = null;
    if ($s === null) {
        try {
            // Kies het seizoen waarvan de AANMELDING open staat (het meest recente als er ooit
            // meerdere open zijn; openen sluit de andere). Staat er geen open, dan het nieuwste
            // seizoen met sollicitaties — de aanmelding sluiten betekent niet dat de procedure
            // klaar is, en een net aangemaakt, leeg seizoen mag die niet wegdrukken. Pas als geen
            // enkel seizoen sollicitaties heeft: het nieuwste.
            $s = db()->query("SELECT * FROM seizoenen ORDER BY (status='open') DESC,
                                  (EXISTS (SELECT 1 FROM sollicitaties so WHERE so.seizoen_id = seizoenen.id)) DESC, id DESC
                              LIMIT 1")->fetch() ?: null;
        } catch (Exception $e) {
            try { $s = db()->query("SELECT * FROM seizoenen ORDER BY (status='open') DESC, id DESC LIMIT 1")->fetch() ?: null; }
            catch (Exception $e2) { $s = null; }
        }
    }
    return $s;
}

/**
 * Het seizoen voor de buitenwereld (website-API, wervingspagina's): dat met de aanmelding open,
 * anders het nieuwste (het komende seizoen zodra het is aangemaakt). actief_seizoen() is voor het
 * beheer en blijft bij het lopende seizoen zolang daar nog sollicitaties in zitten — voor een
 * wervingstekst zijn dat oude datums.
 */
function seizoen_voor_werving(): ?array {
    static $s = false;
    if ($s !== false) return $s;
    $s = actief_seizoen();
    if (!$s || ($s['status'] ?? '') !== 'open') {
        try { $s = db()->query("SELECT * FROM seizoenen ORDER BY id DESC LIMIT 1")->fetch() ?: $s; }
        catch (\Throwable $e) { /* terugval: actief_seizoen() */ }
    }
    return $s;
}

/** Eén seizoen op id (null als het niet bestaat). */
function seizoen_op_id(int $id): ?array {
    $actief = actief_seizoen();
    if ($actief && (int)$actief['id'] === $id) return $actief;
    try {
        $q = db()->prepare("SELECT * FROM seizoenen WHERE id=?");
        $q->execute([$id]);
        return $q->fetch() ?: null;
    } catch (Exception $e) { return null; }
}

// ── Helpers ───────────────────────────────────────────────────
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }

// Datums, maand- en dagnamen, bedragen en cijfers: includes/opmaak.php (één bron).
require_once __DIR__ . '/opmaak.php';

function volledige_naam(array $p): string {
    return implode(' ', array_filter([$p['voornaam'] ?? '', $p['tussenvoegsel'] ?? '', $p['achternaam'] ?? '']));
}

function leeftijd(?string $dob): string {
    if (!$dob) return '—';
    try { return (new DateTime($dob))->diff(new DateTime())->y . ' jr'; } catch (Exception $e) { return '—'; }
}

function genereer_token(int $bytes = 32): string { return bin2hex(random_bytes($bytes)); }

/** De naam van een fase in het beheer. Eén bron: fasen() in includes/fase.php leest hieruit. */
function fase_label(string $f): string {
    return ['aangemeld' => 'Aangemeld', 'uitgenodigd_selectie' => 'Uitgenodigd selectiedag', 'aangemeld_selectie' => 'Aangemeld selectiedag', 'selectiedag_geweest' => 'Selectiedag geweest', 'uitgenodigd_tw' => 'Uitgenodigd trainingsweekend', 'tw_geweest' => 'Trainingsweekend geweest', 'aangenomen' => 'Aangenomen', 'afgewezen' => 'Afgewezen',
            // Eindstanden buiten de procedure: geen knop in fasen(), wel een leesbare naam.
            'afgehaakt' => 'Afgehaakt', 'verwijderd_door_sollicitant' => 'Zelf verwijderd'][$f] ?? $f;
}

function fase_kleur(string $f): string {
    return ['aangemeld' => 'grijs', 'uitgenodigd_selectie' => 'blauw', 'aangemeld_selectie' => 'oranje', 'selectiedag_geweest' => 'teal', 'uitgenodigd_tw' => 'paars', 'tw_geweest' => 'paars', 'aangenomen' => 'groen', 'afgewezen' => 'rood'][$f] ?? 'grijs';
}

/** Is Authentik (OIDC) ingesteld? Een {{PLAATSHOUDER}} uit config.template.php telt als leeg (config_const, smtp.php). */
function authentik_actief(): bool {
    return config_const('OIDC_CLIENT_ID') !== '' && config_const('OIDC_ISSUER') !== '';
}

// Alias voor backwards compat
function authentik_geconfigureerd(): bool { return authentik_actief(); }

/**
 * PKCE (RFC 7636) voor de OIDC-login bij Authentik, beheer en portaal. Een onderschepte
 * autorisatiecode is zonder de verifier in deze sessie waardeloos. Authentik ondersteunt S256;
 * voor een client met secret is het extra, maar het kost niets (NIS2: defense in depth).
 * @param string $sleutel  sessiesleutel, per ingang eigen ('sso_pkce' / 'portaal_sso_pkce')
 * @return array  extra parameters voor de authorize-URL
 */
function oidc_pkce_start(string $sleutel): array {
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $_SESSION[$sleutel] = $verifier;
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    return ['code_challenge' => $challenge, 'code_challenge_method' => 'S256'];
}

/** De verifier voor de tokenaanvraag (één keer bruikbaar). Leeg = geen PKCE gestart. */
function oidc_pkce_verifier(string $sleutel): string {
    $v = (string)($_SESSION[$sleutel] ?? '');
    unset($_SESSION[$sleutel]);
    return $v;
}

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

// Foto URL builder. Een foto van een sollicitant gaat altijd via foto.php (includes/foto.php);
// de rest (logo, favicon, afbeeldingen in mails) blijft een gewone URL.
function foto_url(?string $opgeslagen): ?string {
    if (empty($opgeslagen)) return null;
    if (function_exists('foto_naam') && ($naam = foto_naam($opgeslagen)) !== null) return foto_beveiligde_url($naam);
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

/** Zorgt dat personen de ouder/verzorger-velden hebben (naam, e-mail, telefoon). */
if (!function_exists('personen_ouder_auto_setup')) {
function personen_ouder_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
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
 * Versie van de privacyverklaring (includes/privacy_verklaring.php) die een sollicitant
 * bij het aanmelden heeft gelezen. Verhoog dit nummer zodra de inhoud wijzigt,
 * zodat vastgelegd blijft welke versie iemand te zien kreeg.
 */
// 2026-09b: gezondheid/intake bij de selectiedag, loondocumenten handmatig, termijnen uit de instellingen
// 2026-09c: AI-bezwaar, dagpseudoniem, oud-strandwachten, verwijderen met bewaard loondossier,
//           doorgifte (sms), lettertype zelf gehost
// 2026-09d: lidmaatschap onder 18 getekend door ouder/verzorger (SEPA door de rekeninghouder),
//           termijn gezondheidsverklaring volgt de instelling
// 2026-09e: seintje via de website buiten het seizoen (voornaam + e-mail, dubbele bevestiging)
// 2026-09g: loongegevens alleen door de penningmeester te openen (niet de beheerder); de planner ziet
//           beschikbaarheid en afgesproken dagen
if (!defined('PRIVACY_VERSIE')) define('PRIVACY_VERSIE', '2026-09g');

if (!function_exists('consent_auto_setup')) {
/**
 * Zorgt dat de kolommen voor toestemming-vastlegging bestaan op de sollicitaties-tabel.
 * Draait automatisch, voegt alleen toe wat nog ontbreekt (veilig om vaak aan te roepen).
 */
function consent_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
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
    static $done = false; if ($done || schema_actueel()) return; $done = true;
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
    // De centrale meldingen-kern verwerkt dezelfde wachtrij (alle kanalen); oude rijen zonder
    // kanaalplan gaan als e-mail. Dit is nu alleen nog een doorgeefluik.
    if (function_exists('melding_queue_verwerk')) return melding_queue_verwerk($max);
    return ['verwerkt' => 0, 'fouten' => 0];
}

if (!function_exists('ondertekeningen_auto_setup')) {
/**
 * Maakt de ondertekeningen-tabel aan als die nog niet bestaat.
 * Voorheen kwam deze tabel alleen via migratie 0016 — op een verse database (bijv. test)
 * ontbrak hij daardoor. Nu maakt het systeem hem zelf aan, net als de andere tabellen.
 */
function ondertekeningen_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
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
    static $done = false; if ($done || schema_actueel()) return; $done = true;
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
            `soort` VARCHAR(20) NOT NULL DEFAULT 'afvinken',
            `document_soort` VARCHAR(40) DEFAULT NULL,
            `volgorde` INT NOT NULL DEFAULT 50,
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

        // `type` is een vrije sleutel: 'portaal', 'beheerder' of 'ond<id>' (2FA bij ondertekenen). Geen ENUM!
        $db->exec("CREATE TABLE IF NOT EXISTS `otp_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(200) NOT NULL,
            `code_hash` VARCHAR(64) NOT NULL,
            `type` VARCHAR(30) NOT NULL DEFAULT 'portaal',
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

        // Dezelfde centrale fase-functie als de handmatige route: documenten klaarzetten én de
        // bewerkbare uitnodiging (via de meldingen-kern, in de wachtrij).
        if (function_exists('fase_zetten')) {
            try {
                $fr = fase_zetten((int)$k['id'], 'uitgenodigd_selectie', 'auto_invite', ['alleen_van' => ['aangemeld'], 'prioriteit' => 'queue']);
                if ($fr['ok'] && !$fr['ongewijzigd']) $res['uitgenodigd']++; else $res['overgeslagen']++;
            } catch (\Exception $e) { error_log('auto-invite: ' . $e->getMessage()); $res['overgeslagen']++; }
        }
    }
    return $res;
}
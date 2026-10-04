<?php
/**
 * Paal 13 — Tweestapsverificatie voor de wachtwoord-noodingang van het beheer.
 *
 * Normaal log je in via Authentik (dat heeft zijn eigen MFA). De wachtwoordlogin in
 * admin/login.php is de terugval; NIS2 vraagt ook daar een tweede factor. Twee soorten:
 *   - TOTP (RFC 6238): 30 s, 6 cijfers, SHA1, ±1 tijdstap. Geheim = 20 random bytes,
 *     base32 getoond, versleuteld opgeslagen via de kluis (AAD bindt het aan het account).
 *     Een code werkt één keer: de laatst gebruikte tijdstap staat in tweestaps_stap.
 *     Plus 8 eenmalige herstelcodes, alleen als password_hash bewaard.
 *   - Geen TOTP ingesteld: een eenmalige code per e-mail (otp_tokens, type 'beheer_2fa',
 *     10 minuten, sha256-hash, na gebruik weg).
 *
 * Hier staat alleen logica: geen regel HTML. De schermen zijn admin/login.php,
 * admin/profiel.php en admin/beheerders.php; het codeveld is admin/_tweestaps_codeveld.php.
 *
 * Kolommen op `beheerders` (bron: db_verwachte_kolommen() in db_failsafe.php):
 *   tweestaps_geheim TEXT      kluis-blob (base64) van het base32-geheim
 *   tweestaps_aan_op DATETIME  moment van aanzetten; NULL = uit
 *   tweestaps_stap   BIGINT    laatst gebruikte TOTP-tijdstap (tegen hergebruik)
 *   tweestaps_herstel TEXT     JSON-lijst met password_hash() van de ongebruikte herstelcodes
 */

if (!function_exists('tweestaps_totp')) {

define('TWEESTAPS_PERIODE', 30);
define('TWEESTAPS_CIJFERS', 6);
define('TWEESTAPS_VENSTER', 1);          // ± tijdstappen tolerantie (klokverschil)
define('TWEESTAPS_WACHT_SEC', 300);      // wachtwoord goed → max 5 min voor de tweede stap
define('TWEESTAPS_MAIL_MIN', 10);        // geldigheid van de code per e-mail
define('TWEESTAPS_MAIL_TYPE', 'beheer_2fa');
define('TWEESTAPS_UITGEVER', 'Paal13 Beheer');
define('TWEESTAPS_HERSTEL_AANTAL', 8);

// ── Kern: base32 + HOTP/TOTP (geen externe bibliotheek) ──────────

/** RFC 4648 base32, zonder opvulling. */
function tweestaps_base32_encode(string $bin): string {
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $uit = ''; $buf = 0; $bits = 0;
    for ($i = 0, $n = strlen($bin); $i < $n; $i++) {
        $buf = ($buf << 8) | ord($bin[$i]); $bits += 8;
        while ($bits >= 5) { $bits -= 5; $uit .= $abc[($buf >> $bits) & 31]; }
        $buf &= (1 << $bits) - 1;
    }
    if ($bits > 0) $uit .= $abc[($buf << (5 - $bits)) & 31];
    return $uit;
}

/** Base32 terug naar bytes; spaties, streepjes en '=' worden genegeerd. Null bij een ongeldig teken. */
function tweestaps_base32_decode(string $b32): ?string {
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper((string)preg_replace('/[\s=\-]/', '', $b32));
    $uit = ''; $buf = 0; $bits = 0;
    for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
        $w = strpos($abc, $b32[$i]);
        if ($w === false) return null;
        $buf = ($buf << 5) | $w; $bits += 5;
        if ($bits >= 8) { $bits -= 8; $uit .= chr(($buf >> $bits) & 0xFF); }
        $buf &= (1 << $bits) - 1;
    }
    return $uit;
}

/** HOTP (RFC 4226) met SHA1. */
function tweestaps_hotp(string $sleutel, int $teller, int $cijfers = TWEESTAPS_CIJFERS): string {
    $h = hash_hmac('sha1', pack('J', $teller), $sleutel, true);
    $o = ord($h[19]) & 0x0F;
    $n = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % (10 ** $cijfers)), $cijfers, '0', STR_PAD_LEFT);
}

/** TOTP (RFC 6238) op tijdstip $tijd (unix). */
function tweestaps_totp(string $sleutel, int $tijd, int $cijfers = TWEESTAPS_CIJFERS): string {
    return tweestaps_hotp($sleutel, intdiv($tijd, TWEESTAPS_PERIODE), $cijfers);
}

/**
 * Controleer een TOTP-code tegen een base32-geheim, met ±TWEESTAPS_VENSTER tijdstappen.
 * Retourneert de tijdstap die klopte (voor de hergebruik-controle), anders null.
 * $na_stap: alleen tijdstappen daarná tellen (de laatst gebruikte is al op).
 */
function tweestaps_totp_controleer(string $geheim_b32, string $code, ?int $tijd = null, ?int $na_stap = null): ?int {
    $code = (string)preg_replace('/\D/', '', $code);
    if (strlen($code) !== TWEESTAPS_CIJFERS) return null;
    $sleutel = tweestaps_base32_decode($geheim_b32);
    if ($sleutel === null || $sleutel === '') return null;
    $stap = intdiv($tijd ?? time(), TWEESTAPS_PERIODE);
    $raak = null;
    for ($d = -TWEESTAPS_VENSTER; $d <= TWEESTAPS_VENSTER; $d++) {
        $s = $stap + $d;
        if ($na_stap !== null && $s <= $na_stap) continue;
        if (hash_equals(tweestaps_hotp($sleutel, $s), $code) && $raak === null) $raak = $s;
    }
    return $raak;
}

/** Nieuw geheim: 20 random bytes, als base32 (32 tekens). */
function tweestaps_geheim_nieuw(): string {
    return tweestaps_base32_encode(random_bytes(20));
}

/** Het geheim in groepjes van 4, om over te typen. */
function tweestaps_geheim_groepen(string $b32): string {
    return trim(chunk_split($b32, 4, ' '));
}

/** otpauth://-link voor een authenticator-app. */
function tweestaps_otpauth_uri(string $email, string $b32): string {
    $uitgever = rawurlencode(TWEESTAPS_UITGEVER);
    return 'otpauth://totp/' . $uitgever . ':' . rawurlencode($email)
         . '?secret=' . $b32 . '&issuer=' . $uitgever
         . '&algorithm=SHA1&digits=' . TWEESTAPS_CIJFERS . '&period=' . TWEESTAPS_PERIODE;
}

// ── Opslag ───────────────────────────────────────────────────────

/** Zorg dat de tweestaps-kolommen bestaan (de failsafe draait maar één keer per versie). */
function tweestaps_setup(): void {
    static $klaar = false;
    if ($klaar) return;
    $klaar = true;
    if (!function_exists('db_verwachte_kolommen') || !function_exists('db_kolom_verzeker')) return;
    foreach ((db_verwachte_kolommen()['beheerders'] ?? []) as $kolom => $def) {
        if (str_starts_with($kolom, 'tweestaps_')) db_kolom_verzeker(db(), 'beheerders', $kolom, $def);
    }
}

/** AAD voor de kluis: het geheim hoort bij precies dit account. */
function tweestaps_aad(int $id): string {
    return 'beheerder|tweestaps|' . $id;
}

/** Eén beheerder (actief of niet), of null. */
function tweestaps_rij(int $id): ?array {
    tweestaps_setup();
    $q = db()->prepare("SELECT * FROM beheerders WHERE id = ? LIMIT 1");
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

/** Heeft dit account een wachtwoord (en dus een noodingang die tweestaps nodig heeft)? */
function tweestaps_van_toepassing(array $rij): bool {
    return !empty($rij['wachtwoord']);
}

/** Staat TOTP aan? */
function tweestaps_aan(array $rij): bool {
    return !empty($rij['tweestaps_aan_op']) && !empty($rij['tweestaps_geheim']);
}

/**
 * Welke tweede stap bij het inloggen: 'app' (TOTP) of 'mail' (code per e-mail).
 * Staat TOTP aan maar is het geheim niet te ontsleutelen (kluis stuk/sleutel gewisseld),
 * dan de e-mailcode — anders sluit een kluisprobleem iedereen buiten.
 */
function tweestaps_methode(array $rij): string {
    if (tweestaps_aan($rij) && tweestaps_geheim($rij) !== null) return 'app';
    if (tweestaps_aan($rij)) error_log('tweestaps: geheim van beheerder #' . (int)$rij['id'] . ' niet te ontsleutelen; terugval op e-mailcode');
    return 'mail';
}

/** Status voor een badge: ['tekst','kleur'], of null als het account geen wachtwoord heeft. */
function tweestaps_status(array $rij): ?array {
    if (!tweestaps_van_toepassing($rij)) return null;
    return tweestaps_aan($rij)
        ? ['tekst' => 'Tweestaps aan', 'kleur' => 'groen']
        : ['tekst' => 'Tweestaps uit', 'kleur' => 'oranje'];
}

/** Aantal ongebruikte herstelcodes. */
function tweestaps_herstel_over(array $rij): int {
    $l = json_decode((string)($rij['tweestaps_herstel'] ?? ''), true);
    return is_array($l) ? count($l) : 0;
}

/** Kan TOTP hier worden ingesteld? (De kluis moet werken; er is nooit een plaintext-terugval.) */
function tweestaps_kan_instellen(): bool {
    return function_exists('kluis_beschikbaar') && kluis_beschikbaar();
}

/** Het base32-geheim van een account; null als het er niet is of niet te ontsleutelen valt. */
function tweestaps_geheim(array $rij): ?string {
    if (empty($rij['tweestaps_geheim']) || !function_exists('kluis_ontsleutel')) return null;
    return kluis_ontsleutel((string)$rij['tweestaps_geheim'], tweestaps_aad((int)$rij['id']));
}

/**
 * Instellen gaat in twee stappen (geheim tonen → code bevestigen). Tot de bevestiging staat het
 * nieuwe geheim alleen in de sessie, versleuteld met de kluis, 15 minuten. Een bestaand geheim
 * blijft tot dat moment gewoon werken ("opnieuw instellen" sluit je dus niet buiten).
 */
function tweestaps_voorlopig_start(int $id): bool {
    if (!tweestaps_kan_instellen()) return false;
    $blob = kluis_versleutel(tweestaps_geheim_nieuw(), tweestaps_aad($id) . '|voorlopig');
    if ($blob === null) return false;
    sessie_start();
    $_SESSION['tweestaps_nieuw'] = ['id' => $id, 'blob' => $blob, 't' => time()];
    return true;
}

/** Het voorlopige base32-geheim, of null (geen, verlopen of van een ander account). */
function tweestaps_voorlopig(int $id): ?string {
    sessie_start();
    $v = $_SESSION['tweestaps_nieuw'] ?? null;
    if (!is_array($v) || (int)($v['id'] ?? 0) !== $id) return null;
    if (time() - (int)($v['t'] ?? 0) > 900) { tweestaps_voorlopig_stop(); return null; }
    return kluis_ontsleutel((string)($v['blob'] ?? ''), tweestaps_aad($id) . '|voorlopig');
}

function tweestaps_voorlopig_stop(): void {
    unset($_SESSION['tweestaps_nieuw']);
}

/** 8 nieuwe herstelcodes als tekst (XXXXX-XXXXX); alleen de hashes gaan de database in. */
function tweestaps_herstelcodes_maken(): array {
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // geen 0/O en 1/I: niet te verwarren
    $codes = [];
    for ($i = 0; $i < TWEESTAPS_HERSTEL_AANTAL; $i++) {
        $c = '';
        for ($j = 0; $j < 10; $j++) $c .= $abc[random_int(0, 31)];
        $codes[] = substr($c, 0, 5) . '-' . substr($c, 5);
    }
    return $codes;
}

/** Herstelcode normaliseren: hoofdletters, alleen letters en cijfers. */
function tweestaps_herstel_norm(string $code): string {
    return (string)preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
}

/**
 * Zet TOTP aan met een bevestigd geheim. $stap = de tijdstap van de bevestigingscode (die is
 * dan al op). Retourneert de herstelcodes (één keer te tonen), of null als het niet lukte.
 */
function tweestaps_inschakelen(int $id, string $b32, int $stap): ?array {
    tweestaps_setup();
    if (!tweestaps_kan_instellen()) return null;
    $blob = kluis_versleutel($b32, tweestaps_aad($id));
    if ($blob === null) return null;
    $codes = tweestaps_herstelcodes_maken();
    $hashes = array_map(fn($c) => password_hash(tweestaps_herstel_norm($c), PASSWORD_DEFAULT), $codes);
    db()->prepare("UPDATE beheerders SET tweestaps_geheim = ?, tweestaps_aan_op = NOW(), tweestaps_stap = ?, tweestaps_herstel = ? WHERE id = ?")
        ->execute([$blob, $stap, json_encode($hashes), $id]);
    return $codes;
}

/** Zet tweestaps uit (zelf, of een admin die reset). Het geheim en de herstelcodes verdwijnen. */
function tweestaps_uitschakelen(int $id): void {
    tweestaps_setup();
    db()->prepare("UPDATE beheerders SET tweestaps_geheim = NULL, tweestaps_aan_op = NULL, tweestaps_stap = NULL, tweestaps_herstel = NULL WHERE id = ?")
        ->execute([$id]);
}

// ── Codes controleren ────────────────────────────────────────────

/**
 * Snelheidsbegrenzing op codepogingen: per account én per IP. Telt elke poging.
 * Bovenop login_geblokkeerd() (dat telt de mislukte pogingen in login_pogingen).
 */
function tweestaps_poging_toegestaan(int $id, string $ip): bool {
    if (!function_exists('rate_limit')) return true;
    $acc = rate_limit('beheer_2fa_account', 'b' . $id, 5, 900);
    $adr = rate_limit('beheer_2fa_ip', $ip, 20, 900);
    return $acc && $adr;
}

/**
 * TOTP-code van een account controleren. Een geldige code wordt meteen "op" gezet
 * (atomair: twee gelijktijdige verzoeken met dezelfde code → maar één slaagt).
 */
function tweestaps_totp_gebruiken(array $rij, string $code): bool {
    if (!tweestaps_aan($rij)) return false;
    $geheim = tweestaps_geheim($rij);
    if ($geheim === null) return false;
    $vorige = isset($rij['tweestaps_stap']) && $rij['tweestaps_stap'] !== null ? (int)$rij['tweestaps_stap'] : null;
    $stap = tweestaps_totp_controleer($geheim, $code, null, $vorige);
    if ($stap === null) return false;
    $u = db()->prepare("UPDATE beheerders SET tweestaps_stap = ? WHERE id = ? AND (tweestaps_stap IS NULL OR tweestaps_stap < ?)");
    $u->execute([$stap, (int)$rij['id'], $stap]);
    return $u->rowCount() === 1;
}

/** Herstelcode gebruiken: klopt hij, dan is hij daarna weg. */
function tweestaps_herstel_gebruiken(array $rij, string $code): bool {
    $code = tweestaps_herstel_norm($code);
    if (strlen($code) !== 10 || !tweestaps_aan($rij)) return false;
    $oud = (string)($rij['tweestaps_herstel'] ?? '');
    $lijst = json_decode($oud, true);
    if (!is_array($lijst)) return false;
    foreach ($lijst as $i => $hash) {
        if (is_string($hash) && password_verify($code, $hash)) {
            unset($lijst[$i]);
            $u = db()->prepare("UPDATE beheerders SET tweestaps_herstel = ? WHERE id = ? AND tweestaps_herstel = ?");
            $u->execute([json_encode(array_values($lijst)), (int)$rij['id'], $oud]);
            return $u->rowCount() === 1;
        }
    }
    return false;
}

/** Code uit de app óf herstelcode (Mijn profiel accepteert allebei in één veld). */
function tweestaps_code_of_herstel(array $rij, string $code): bool {
    return preg_match('/^\s*\d[\d\s]*$/', $code)
        ? tweestaps_totp_gebruiken($rij, $code)
        : tweestaps_herstel_gebruiken($rij, $code);
}

// ── Code per e-mail (account zonder TOTP) ────────────────────────

/**
 * Stuur een eenmalige inlogcode naar het adres van het account.
 * Retourneert 'verstuurd', 'te_snel' (er ging er net al een), 'limiet' of 'mislukt'.
 */
function tweestaps_mailcode_sturen(array $rij): string {
    $email = strtolower(trim((string)($rij['email'] ?? '')));
    if ($email === '') return 'mislukt';
    try {
        $r = db()->prepare("SELECT 1 FROM otp_tokens WHERE email = ? AND type = ? AND gebruikt = 0
                            AND aangemaakt_op > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
        $r->execute([$email, TWEESTAPS_MAIL_TYPE]);
        if ($r->fetchColumn()) return 'te_snel';
        if (function_exists('rate_limit') && !rate_limit('beheer_2fa_mail', 'b' . (int)$rij['id'], 5, 3600)) return 'limiet';

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        db()->prepare("DELETE FROM otp_tokens WHERE email = ? AND type = ?")->execute([$email, TWEESTAPS_MAIL_TYPE]);
        db()->prepare("INSERT INTO otp_tokens (email, code_hash, type, vervalt_op)
                       VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL " . TWEESTAPS_MAIL_MIN . " MINUTE))")
            ->execute([$email, hash('sha256', $code), TWEESTAPS_MAIL_TYPE]);

        // Eigen meldingstype 'beheer_code' (includes/meldingen/registry.php): logt de inhoud
        // nooit, alleen e-mail, en meteen versturen — de beheerder staat op het scherm te wachten.
        $naam = (string)($rij['naam'] ?? '');
        $uit = melding_verstuur('beheer_code', 0, ['otp_code' => $code, 'voornaam' => $naam,
                                                   'geldig_min' => (string)TWEESTAPS_MAIL_MIN], [
            'ontvanger'   => ['email' => $email, 'naam' => $naam, 'rol' => 'direct', 'kanaal_voorkeur' => 'email'],
            'kanaalmodus' => 'email',
            'prioriteit'  => 'direct',
            'dedup_min'   => 0,
            'referentie'  => TWEESTAPS_MAIL_TYPE,
        ]);
        if (function_exists('audit_log')) audit_log('tweestaps_mailcode', 'beheerder', (int)$rij['id'], ['status' => $uit['status'] ?? '']);
        if (in_array($uit['status'] ?? '', ['verstuurd', 'wacht'], true)) return 'verstuurd';
        // Niet aangekomen: de code weer weg, anders houdt de 60-secondenrem een nieuwe poging tegen.
        db()->prepare("DELETE FROM otp_tokens WHERE email = ? AND type = ?")->execute([$email, TWEESTAPS_MAIL_TYPE]);
        return 'mislukt';
    } catch (\Throwable $e) {
        error_log('tweestaps_mailcode_sturen: ' . $e->getMessage());
        return 'mislukt';
    }
}

/** Code per e-mail controleren; bij succes verdwijnt het token. */
function tweestaps_mailcode_gebruiken(array $rij, string $code): bool {
    $email = strtolower(trim((string)($rij['email'] ?? '')));
    $code  = (string)preg_replace('/\D/', '', $code);
    if ($email === '' || strlen($code) !== 6) return false;
    if (!otp_verifieer($email, $code, TWEESTAPS_MAIL_TYPE)) return false;
    db()->prepare("DELETE FROM otp_tokens WHERE email = ? AND type = ?")->execute([$email, TWEESTAPS_MAIL_TYPE]);
    return true;
}

// ── Tussenstand in de sessie: wachtwoord goed, tweede stap nog niet ─

function tweestaps_wacht_start(int $id): void {
    sessie_start();
    session_regenerate_id(true);
    $_SESSION['bhd_2fa'] = ['id' => $id, 't' => time(), 'mail' => ''];
}

/** Het beheerder-id dat op de tweede stap wacht, of 0 (geen of verlopen). */
function tweestaps_wacht(): int {
    sessie_start();
    $w = $_SESSION['bhd_2fa'] ?? null;
    if (!is_array($w) || empty($w['id'])) return 0;
    if (time() - (int)($w['t'] ?? 0) > TWEESTAPS_WACHT_SEC) { unset($_SESSION['bhd_2fa']); return 0; }
    return (int)$w['id'];
}

/** Uitkomst van het versturen van de e-mailcode onthouden (voor de melding na de redirect). */
function tweestaps_wacht_mail(?string $status = null): string {
    if ($status !== null && isset($_SESSION['bhd_2fa'])) $_SESSION['bhd_2fa']['mail'] = $status;
    return (string)($_SESSION['bhd_2fa']['mail'] ?? '');
}

function tweestaps_wacht_stop(): void {
    unset($_SESSION['bhd_2fa']);
}

/** "l****@gmail.com": genoeg om het adres te herkennen, niet om het af te lezen. */
function tweestaps_email_maskeer(string $email): string {
    $d = explode('@', $email, 2);
    if (count($d) !== 2 || $d[0] === '') return $email;
    return mb_substr($d[0], 0, 1) . str_repeat('*', max(3, mb_strlen($d[0]) - 1)) . '@' . $d[1];
}

} // function_exists

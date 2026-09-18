<?php
/**
 * Paal 13 — Kluis: versleuteling van gevoelige gegevens (ID-kopieën, bankpas, BSN, IBAN, verzegelde PDF's).
 *
 * Envelope-encryptie: elk bestand/veld krijgt een eigen willekeurige datasleutel (DEK). De inhoud wordt
 * daarmee versleuteld (XChaCha20-Poly1305 via sodium; terugval AES-256-GCM via openssl). De DEK zelf
 * wordt versleuteld met de hoofdsleutel (KEK). De KEK is afgeleid uit TWEE delen: KLUIS_SLEUTEL in
 * config.php (buiten Git) én het zout `kluis.zout` in de database (eenmalig gegenereerd). Wie alleen de
 * database óf alleen de code/config buitmaakt, kan niets lezen.
 *
 * Elke versleuteling draagt "AAD" (bijv. "sollicitatie|soort|id"): ontsleutelen lukt alleen met dezelfde
 * AAD, zodat een blob niet naar een ander dossier te verplaatsen is.
 *
 * Blob-indeling (binair): "P13K" | versie(1) | alg(1) | kek-versie(1) | wrapped-DEK | data-nonce | data-ct(+tag)
 *   alg 1 = xchacha20poly1305 (nonce 24, tag 16), alg 2 = aes-256-gcm (nonce 12, tag 16).
 *   wrapped-DEK = nonce | ct(32+16), versleuteld met de KEK en AAD = de 7 headerbytes.
 * Velden in de database: base64 van dezelfde blob. Bestanden op schijf: de blob zelf.
 *
 * Zonder werkende kluis (geen sleutel, geen extensie) weigert het systeem gevoelige uploads en
 * ondertekeningen; er is nooit een plaintext-terugval.
 */

if (!function_exists('kluis_beschikbaar')) {

define('KLUIS_MAGIC', 'P13K');
define('KLUIS_VERSIE', 1);
define('KLUIS_ALG_SODIUM', 1);
define('KLUIS_ALG_OPENSSL', 2);

/** Welk algoritme kan deze server? 0 = geen. */
function kluis_algoritme(): int {
    if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) return KLUIS_ALG_SODIUM;
    if (function_exists('openssl_encrypt') && in_array('aes-256-gcm', array_map('strtolower', openssl_get_cipher_methods()), true)) return KLUIS_ALG_OPENSSL;
    return 0;
}

/** Status voor de health-check: ['ok'=>bool, 'reden'=>string, 'algoritme'=>string]. */
function kluis_status(): array {
    $alg = kluis_algoritme();
    $alg_naam = $alg === KLUIS_ALG_SODIUM ? 'XChaCha20-Poly1305 (sodium)' : ($alg === KLUIS_ALG_OPENSSL ? 'AES-256-GCM (openssl)' : 'geen');
    if ($alg === 0) return ['ok' => false, 'reden' => 'Geen sodium- of openssl-extensie met AES-GCM beschikbaar.', 'algoritme' => $alg_naam];
    if (!defined('KLUIS_SLEUTEL') || KLUIS_SLEUTEL === '' || str_contains((string)KLUIS_SLEUTEL, '{{')) {
        return ['ok' => false, 'reden' => 'KLUIS_SLEUTEL ontbreekt in config.php. Genereer: php -r "echo base64_encode(random_bytes(32));"', 'algoritme' => $alg_naam];
    }
    $sleutel = base64_decode((string)KLUIS_SLEUTEL, true);
    if ($sleutel === false || strlen($sleutel) < 32) return ['ok' => false, 'reden' => 'KLUIS_SLEUTEL is geen base64 van minstens 32 bytes.', 'algoritme' => $alg_naam];
    if (kluis_zout() === '') return ['ok' => false, 'reden' => 'Het kluis-zout kon niet in de instellingen worden opgeslagen.', 'algoritme' => $alg_naam];
    // Roundtrip
    try {
        $t = 'kluis-test-' . random_int(1000, 9999);
        $b = kluis_versleutel($t, 'health');
        if ($b === null || kluis_ontsleutel($b, 'health') !== $t) return ['ok' => false, 'reden' => 'Versleutel/ontsleutel-test mislukt.', 'algoritme' => $alg_naam];
        if (kluis_ontsleutel($b, 'anders') !== null) return ['ok' => false, 'reden' => 'AAD-controle werkt niet.', 'algoritme' => $alg_naam];
    } catch (\Throwable $e) { return ['ok' => false, 'reden' => 'Test gaf een fout: ' . $e->getMessage(), 'algoritme' => $alg_naam]; }
    return ['ok' => true, 'reden' => '', 'algoritme' => $alg_naam];
}

function kluis_beschikbaar(): bool {
    static $ok = null;
    if ($ok === null) $ok = kluis_status()['ok'];
    return $ok;
}

/** Zout in de database (tweede deel van de sleutel). Eenmalig 32 random bytes, hex. */
function kluis_zout(): string {
    if (!function_exists('instelling')) return '';
    $z = instelling('kluis.zout', '');
    if ($z === '' && function_exists('instelling_opslaan')) {
        $z = bin2hex(random_bytes(32));
        try { instelling_opslaan('kluis.zout', $z); } catch (\Throwable $e) { return ''; }
        // Cache van instellingen kan achterlopen; lees opnieuw
        if (function_exists('instellingen_cache_wissen')) instellingen_cache_wissen();
    }
    return $z;
}

/** Versienummer van de hoofdsleutel (voor rotatie; standaard 1). */
function kluis_kek_versie(): int {
    return defined('KLUIS_SLEUTEL_VERSIE') ? max(1, min(255, (int)KLUIS_SLEUTEL_VERSIE)) : 1;
}

/** De hoofdsleutel (32 bytes): HKDF over config-sleutel + database-zout. Null als niet beschikbaar. */
function kluis_kek(): ?string {
    static $kek = null;
    if ($kek !== null) return $kek;
    if (!defined('KLUIS_SLEUTEL')) return null;
    $ikm = base64_decode((string)KLUIS_SLEUTEL, true);
    $zout = kluis_zout();
    if ($ikm === false || strlen($ikm) < 32 || $zout === '') return null;
    $kek = hash_hkdf('sha256', $ikm, 32, 'paal13-kluis-v' . kluis_kek_versie(), hex2bin($zout) ?: $zout);
    return $kek;
}

/** Ruwe AEAD-versleuteling met een gegeven sleutel. Retourneert nonce|ct(+tag). */
function kluis_aead_encrypt(int $alg, string $sleutel, string $plain, string $aad): ?string {
    if ($alg === KLUIS_ALG_SODIUM) {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        return $nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $aad, $nonce, $sleutel);
    }
    if ($alg === KLUIS_ALG_OPENSSL) {
        $nonce = random_bytes(12); $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $sleutel, OPENSSL_RAW_DATA, $nonce, $tag, $aad, 16);
        if ($ct === false) return null;
        return $nonce . $ct . $tag;
    }
    return null;
}

/** Ruwe AEAD-ontsleuteling; null bij manipulatie of verkeerde AAD/sleutel. */
function kluis_aead_decrypt(int $alg, string $sleutel, string $blob, string $aad): ?string {
    try {
        if ($alg === KLUIS_ALG_SODIUM) {
            $n = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
            if (strlen($blob) < $n + 16) return null;
            $r = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($blob, $n), $aad, substr($blob, 0, $n), $sleutel);
            return $r === false ? null : $r;
        }
        if ($alg === KLUIS_ALG_OPENSSL) {
            if (strlen($blob) < 12 + 16) return null;
            $nonce = substr($blob, 0, 12); $tag = substr($blob, -16); $ct = substr($blob, 12, -16);
            $r = openssl_decrypt($ct, 'aes-256-gcm', $sleutel, OPENSSL_RAW_DATA, $nonce, $tag, $aad);
            return $r === false ? null : $r;
        }
    } catch (\Throwable $e) {}
    return null;
}

/**
 * Versleutel een string (envelope). $aad bindt de blob aan zijn context. Null als de kluis niet werkt.
 * Retourneert de binaire blob; gebruik kluis_versleutel_tekst() voor een databaseveld.
 */
function kluis_versleutel_blob(string $plain, string $aad = ''): ?string {
    $alg = kluis_algoritme(); $kek = kluis_kek();
    if ($alg === 0 || $kek === null) return null;
    $header = KLUIS_MAGIC . chr(KLUIS_VERSIE) . chr($alg) . chr(kluis_kek_versie());
    $dek = random_bytes(32);
    $wrapped = kluis_aead_encrypt($alg, $kek, $dek, $header);
    $data    = kluis_aead_encrypt($alg, $dek, $plain, $aad);
    if (function_exists('sodium_memzero')) { try { sodium_memzero($dek); } catch (\Throwable $e) {} }
    if ($wrapped === null || $data === null) return null;
    return $header . $wrapped . $data;
}

/** Ontsleutel een blob; null bij fout/manipulatie. */
function kluis_ontsleutel_blob(string $blob, string $aad = ''): ?string {
    if (strlen($blob) < 7 || substr($blob, 0, 4) !== KLUIS_MAGIC) return null;
    $versie = ord($blob[4]); $alg = ord($blob[5]); $kekv = ord($blob[6]);
    if ($versie !== KLUIS_VERSIE || !in_array($alg, [KLUIS_ALG_SODIUM, KLUIS_ALG_OPENSSL], true)) return null;
    if ($alg === KLUIS_ALG_SODIUM && !function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) return null;
    if ($kekv !== kluis_kek_versie()) return null; // andere sleutelversie: eerst herwrappen
    $kek = kluis_kek();
    if ($kek === null) return null;
    $header = substr($blob, 0, 7);
    $wlen = ($alg === KLUIS_ALG_SODIUM ? 24 : 12) + 32 + 16;
    if (strlen($blob) < 7 + $wlen) return null;
    $dek = kluis_aead_decrypt($alg, $kek, substr($blob, 7, $wlen), $header);
    if ($dek === null) return null;
    $plain = kluis_aead_decrypt($alg, $dek, substr($blob, 7 + $wlen), $aad);
    if (function_exists('sodium_memzero')) { try { sodium_memzero($dek); } catch (\Throwable $e) {} }
    return $plain;
}

/** Voor databasevelden: base64-tekst. */
function kluis_versleutel(string $plain, string $aad = ''): ?string {
    $b = kluis_versleutel_blob($plain, $aad);
    return $b === null ? null : base64_encode($b);
}
function kluis_ontsleutel(?string $tekst, string $aad = ''): ?string {
    if ($tekst === null || $tekst === '') return null;
    $b = base64_decode($tekst, true);
    return $b === false ? null : kluis_ontsleutel_blob($b, $aad);
}

/** Bestand versleuteld wegschrijven (atomair: eerst .tmp, dan hernoemen). */
function kluis_bestand_schrijven(string $plain, string $pad, string $aad): bool {
    $blob = kluis_versleutel_blob($plain, $aad);
    if ($blob === null) return false;
    $tmp = $pad . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $blob, LOCK_EX) !== strlen($blob)) { @unlink($tmp); return false; }
    @chmod($tmp, 0600);
    if (!@rename($tmp, $pad)) { @unlink($tmp); return false; }
    return true;
}

/** Bestand ontsleuteld in het geheugen lezen; null bij fout. */
function kluis_bestand_lezen(string $pad, string $aad): ?string {
    if (!is_file($pad)) return null;
    $blob = @file_get_contents($pad);
    return $blob === false ? null : kluis_ontsleutel_blob($blob, $aad);
}

/** Overschrijf gevoelige tekst in het geheugen (waar de runtime dat toelaat). */
function kluis_wis(string &$s): void {
    if (function_exists('sodium_memzero')) { try { sodium_memzero($s); return; } catch (\Throwable $e) {} }
    $s = str_repeat("\0", strlen($s));
}

/** Gemaskeerde weergave, bijv. BSN "*****123" of IBAN "NL** **** **** **12 34". */
function kluis_maskeer(string $waarde, int $zichtbaar = 3): string {
    $w = preg_replace('/\s+/', '', $waarde);
    $n = strlen($w);
    if ($n <= $zichtbaar) return str_repeat('*', $n);
    return str_repeat('*', $n - $zichtbaar) . substr($w, -$zichtbaar);
}

} // function_exists

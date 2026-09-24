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
 *
 * Sleutelrotatie: config.php kent naast KLUIS_SLEUTEL (+ KLUIS_SLEUTEL_VERSIE) optioneel de vorige
 * sleutel KLUIS_SLEUTEL_OUD (+ KLUIS_SLEUTEL_OUD_VERSIE). Ontsleutelen lukt met elke bekende versie,
 * versleutelen gebeurt altijd met de huidige. kluis_herversleutel_batch() pakt per blob alleen de DEK
 * opnieuw in met de huidige KEK (de data-ciphertext blijft ongemoeid); scherm: admin/kluis.php.
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

/** Versie van de vorige sleutel (KLUIS_SLEUTEL_OUD), of null als die er niet (bruikbaar) is. */
function kluis_oude_versie(): ?int {
    if (!defined('KLUIS_SLEUTEL_OUD') || (string)KLUIS_SLEUTEL_OUD === '' || str_contains((string)KLUIS_SLEUTEL_OUD, '{{')) return null;
    $v = defined('KLUIS_SLEUTEL_OUD_VERSIE') ? (int)KLUIS_SLEUTEL_OUD_VERSIE : 0;
    return ($v >= 1 && $v <= 255 && $v !== kluis_kek_versie()) ? $v : null;
}

/** Fout in de rotatie-instellingen (voor scherm en health-check), of '' als het klopt. Noemt nooit de sleutel zelf. */
function kluis_rotatie_configfout(): string {
    if (!defined('KLUIS_SLEUTEL_OUD') || (string)KLUIS_SLEUTEL_OUD === '' || str_contains((string)KLUIS_SLEUTEL_OUD, '{{')) return '';
    $v = defined('KLUIS_SLEUTEL_OUD_VERSIE') ? (int)KLUIS_SLEUTEL_OUD_VERSIE : 0;
    if ($v < 1 || $v > 255) return 'KLUIS_SLEUTEL_OUD staat in config.php, maar KLUIS_SLEUTEL_OUD_VERSIE ontbreekt of is geen getal van 1 tot 255.';
    if ($v === kluis_kek_versie()) return 'KLUIS_SLEUTEL_OUD_VERSIE is gelijk aan KLUIS_SLEUTEL_VERSIE; de nieuwe sleutel hoort een hoger versienummer te krijgen.';
    $b = base64_decode((string)KLUIS_SLEUTEL_OUD, true);
    if ($b === false || strlen($b) < 32) return 'KLUIS_SLEUTEL_OUD is geen base64 van minstens 32 bytes.';
    return '';
}

/**
 * Hoofdsleutel (32 bytes) voor een sleutelversie: HKDF over config-sleutel + database-zout.
 * Zonder argument de huidige versie. Null als die versie niet bekend of niet bruikbaar is.
 */
function kluis_kek(?int $versie = null): ?string {
    static $cache = [];
    $versie = $versie ?? kluis_kek_versie();
    if (array_key_exists($versie, $cache)) return $cache[$versie];
    if ($versie === kluis_kek_versie()) {
        $bron = defined('KLUIS_SLEUTEL') ? (string)KLUIS_SLEUTEL : '';
    } elseif ($versie === kluis_oude_versie()) {
        $bron = (string)KLUIS_SLEUTEL_OUD;
    } else {
        return null;
    }
    $ikm = base64_decode($bron, true);
    $zout = kluis_zout();
    if ($ikm === false || strlen($ikm) < 32 || $zout === '') return null;
    $cache[$versie] = hash_hkdf('sha256', $ikm, 32, 'paal13-kluis-v' . $versie, hex2bin($zout) ?: $zout);
    kluis_wis($ikm);
    return $cache[$versie];
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
    $kek = kluis_kek($kekv); // huidige of vorige sleutel; een onbekende versie geeft null
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

// ── Sleutelrotatie ────────────────────────────────────────────────────────────

/** Sleutelversie uit de eerste 7 bytes van een blob; null als het geen kluis-blob is. */
function kluis_kop_versie(?string $kop): ?int {
    if ($kop === null || strlen($kop) < 7 || substr($kop, 0, 4) !== KLUIS_MAGIC || ord($kop[4]) !== KLUIS_VERSIE) return null;
    return ord($kop[6]);
}

/**
 * Pak de DEK van een blob opnieuw in met de huidige KEK. De data-ciphertext blijft byte-voor-byte gelijk,
 * dus de AAD van de inhoud is hier niet nodig. Idempotent: een blob op de huidige versie blijft ongemoeid.
 * Retour: ['status' => 'gedaan'|'al_goed'|'mislukt', 'blob' => ?string (alleen bij gedaan), 'reden' => string].
 */
function kluis_herwrap_blob(string $blob): array {
    $mis = fn(string $r) => ['status' => 'mislukt', 'blob' => null, 'reden' => $r];
    $kekv = kluis_kop_versie($blob);
    if ($kekv === null) return $mis('geen kluis-blob');
    if ($kekv === kluis_kek_versie()) return ['status' => 'al_goed', 'blob' => null, 'reden' => ''];
    $alg = ord($blob[5]);
    if (!in_array($alg, [KLUIS_ALG_SODIUM, KLUIS_ALG_OPENSSL], true)) return $mis('onbekend algoritme');
    if ($alg === KLUIS_ALG_SODIUM && !function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) return $mis('sodium ontbreekt op deze server');
    $oud = kluis_kek($kekv);
    if ($oud === null) return $mis('sleutelversie ' . $kekv . ' is niet bekend (staat KLUIS_SLEUTEL_OUD in config.php?)');
    $nieuw = kluis_kek();
    if ($nieuw === null) return $mis('de huidige sleutel is niet bruikbaar');
    $wlen = ($alg === KLUIS_ALG_SODIUM ? 24 : 12) + 32 + 16;
    if (strlen($blob) < 7 + $wlen) return $mis('blob is te kort');
    $dek = kluis_aead_decrypt($alg, $oud, substr($blob, 7, $wlen), substr($blob, 0, 7));
    if ($dek === null) return $mis('datasleutel niet te openen met sleutelversie ' . $kekv);
    $kop = KLUIS_MAGIC . chr(KLUIS_VERSIE) . chr($alg) . chr(kluis_kek_versie());
    $wrapped = kluis_aead_encrypt($alg, $nieuw, $dek, $kop);
    // Controle vóór het wegschrijven: de nieuwe verpakking moet dezelfde DEK teruggeven.
    $terug = $wrapped === null ? null : kluis_aead_decrypt($alg, $nieuw, $wrapped, $kop);
    $goed = $terug !== null && hash_equals($dek, $terug);
    kluis_wis($dek);
    if ($terug !== null) kluis_wis($terug);
    if (!$goed || $wrapped === null || strlen($wrapped) !== $wlen) return $mis('opnieuw inpakken mislukt');
    return ['status' => 'gedaan', 'blob' => $kop . $wrapped . substr($blob, 7 + $wlen), 'reden' => ''];
}

/** Idem voor een databaseveld (base64). 'blob' is dan weer base64-tekst. */
function kluis_herwrap(string $tekst): array {
    $b = base64_decode($tekst, true);
    if ($b === false) return ['status' => 'mislukt', 'blob' => null, 'reden' => 'geen geldige base64'];
    $r = kluis_herwrap_blob($b);
    if ($r['blob'] !== null) $r['blob'] = base64_encode($r['blob']);
    return $r;
}

/** Eerste 7 bytes van een bestand (de kop), of null. */
function kluis_bestand_kop(string $pad): ?string {
    $f = @fopen($pad, 'rb');
    if ($f === false) return null;
    $kop = fread($f, 7);
    fclose($f);
    return ($kop === false || strlen($kop) < 7) ? null : $kop;
}

/** Herwrap een versleuteld bestand op zijn plek (atomair: .tmp + hernoemen). Zelfde retour als kluis_herwrap_blob, zonder 'blob'. */
function kluis_bestand_herwrappen(string $pad): array {
    if (!is_file($pad)) return ['status' => 'mislukt', 'blob' => null, 'reden' => 'bestand bestaat niet meer'];
    if (kluis_kop_versie(kluis_bestand_kop($pad)) === kluis_kek_versie()) return ['status' => 'al_goed', 'blob' => null, 'reden' => ''];
    $blob = @file_get_contents($pad);
    if ($blob === false) return ['status' => 'mislukt', 'blob' => null, 'reden' => 'bestand niet leesbaar'];
    $r = kluis_herwrap_blob($blob);
    if ($r['status'] !== 'gedaan') return $r;
    $tmp = $pad . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $r['blob'], LOCK_EX) !== strlen($r['blob'])) { @unlink($tmp); return ['status' => 'mislukt', 'blob' => null, 'reden' => 'tijdelijk bestand schrijven mislukt']; }
    @chmod($tmp, 0600);
    // Is het bestand intussen weggehaald (vervangen/vernietigd)? Dan niet terugzetten.
    if (!is_file($pad) || !@rename($tmp, $pad)) { @unlink($tmp); return ['status' => 'mislukt', 'blob' => null, 'reden' => 'bestand is tijdens het herwrappen verdwenen of niet te vervangen']; }
    return ['status' => 'gedaan', 'blob' => null, 'reden' => ''];
}

/** Waar kluis-blobs staan: bron => omschrijving. Een bron toevoegen = hier + kluis_bron_volgende() + kluis_bron_herwrap(). */
function kluis_bronnen(): array {
    return [
        'ondertekeningen' => 'BSN en IBAN bij ondertekeningen',
        'documenten'      => "Documenten (ID, bankpas, verzegelde PDF's)",
        'zegel'           => 'Privésleutel van het documentzegel',
    ];
}

/** Map van een bestandsbron, of null als die er niet is. */
function kluis_bron_map(string $bron): ?string {
    if ($bron === 'documenten') return function_exists('documenten_map') ? documenten_map() : null;
    if ($bron === 'zegel')      return function_exists('zegel_map') ? zegel_map() : null;
    return null;
}

/**
 * De volgende blobs van een bron ná cursor $na, oplopend. Retour: lijst van [cursor, kop (7+ bytes) of null].
 * Cursor = id (database) of bestandsnaam (map). Leest alleen de kop, niet de hele blob.
 */
function kluis_bron_volgende(string $bron, string $na, int $max): array {
    $uit = [];
    if ($max < 1) return $uit;
    if ($bron === 'ondertekeningen') {
        if (!function_exists('db')) return $uit;
        try {
            // LEFT(…,12): 12 base64-tekens zijn 9 bytes, genoeg voor de kop van 7.
            $q = db()->prepare("SELECT id, LEFT(vertrouwelijk, 12) AS kop FROM ondertekeningen
                                WHERE vertrouwelijk IS NOT NULL AND vertrouwelijk <> '' AND id > ? ORDER BY id LIMIT " . (int)$max);
            $q->execute([(int)$na]);
            foreach ($q->fetchAll() as $r) {
                $kop = base64_decode((string)$r['kop'], true);
                $uit[] = [(string)$r['id'], $kop === false ? null : $kop];
            }
        } catch (\Throwable $e) { error_log('kluis_bron_volgende: ' . $e->getMessage()); }
        return $uit;
    }
    $map = kluis_bron_map($bron);
    if ($map === null) return $uit;
    $namen = $bron === 'zegel' ? (is_file($map . '/key.enc') ? ['key.enc'] : []) : array_map('basename', glob($map . '/*.enc') ?: []);
    sort($namen, SORT_STRING);
    foreach ($namen as $n) {
        if ($na !== '' && strcmp($n, $na) <= 0) continue;
        $uit[] = [$n, kluis_bestand_kop($map . '/' . $n)];
        if (count($uit) >= $max) break;
    }
    return $uit;
}

/** Herwrap één blob van een bron. Retour ['status', 'reden']. Schrijft alleen weg als de inhoud intussen niet veranderde. */
function kluis_bron_herwrap(string $bron, string $cursor): array {
    if ($bron === 'ondertekeningen') {
        try {
            $db = db();
            $q = $db->prepare("SELECT vertrouwelijk FROM ondertekeningen WHERE id=?");
            $q->execute([(int)$cursor]);
            $oud = (string)($q->fetchColumn() ?: '');
            if ($oud === '') return ['status' => 'al_goed', 'reden' => ''];   // intussen gewist (bijv. AVG)
            $r = kluis_herwrap($oud);
            if ($r['status'] !== 'gedaan') return $r;
            $u = $db->prepare("UPDATE ondertekeningen SET vertrouwelijk=? WHERE id=? AND vertrouwelijk=?");
            $u->execute([$r['blob'], (int)$cursor, $oud]);
            if ($u->rowCount() !== 1) return ['status' => 'mislukt', 'reden' => 'intussen gewijzigd; draai opnieuw'];
            // De bewijs-hash van de ondertekening is over de oude ciphertext berekend. Leg de overgang vast,
            // zodat die hash later nog te herleiden is (alleen hashes van ciphertext, geen inhoud).
            if (function_exists('audit_log')) audit_log('kluis_blob_herwrapt', 'ondertekening', (int)$cursor, [
                'oud_sha256' => hash('sha256', $oud), 'nieuw_sha256' => hash('sha256', $r['blob']), 'naar_versie' => kluis_kek_versie(),
            ]);
            return ['status' => 'gedaan', 'reden' => ''];
        } catch (\Throwable $e) {
            return ['status' => 'mislukt', 'reden' => 'databasefout'];
        }
    }
    $map = kluis_bron_map($bron);
    if ($map === null) return ['status' => 'mislukt', 'reden' => 'map niet beschikbaar'];
    $naam = basename($cursor);
    $r = kluis_bestand_herwrappen($map . '/' . $naam);
    if ($bron === 'documenten' && $r['status'] === 'gedaan' && function_exists('db')) {
        try {
            $db = db();
            $db->prepare("UPDATE sollicitant_documenten SET kluis_versie=? WHERE opslagnaam=?")->execute([kluis_kek_versie(), $naam]);
            // Is het document net vervangen of vernietigd, dan hoort het bestand weg te zijn: niet door ons terugzetten.
            $q = $db->prepare("SELECT status FROM sollicitant_documenten WHERE opslagnaam=?");
            $q->execute([$naam]);
            $st = $q->fetchColumn();
            if ($st !== false && !in_array($st, ['ingediend', 'verwerkt'], true)) @unlink($map . '/' . $naam);
        } catch (\Throwable $e) { error_log('kluis_bron_herwrap documenten: ' . $e->getMessage()); }
    }
    return ['status' => $r['status'], 'reden' => $r['reden']];
}

/**
 * Stand van de rotatie: per bron hoeveel blobs op de huidige versie staan en hoeveel nog niet.
 * Retour: ['versie', 'oude_versie', 'configfout', 'oud' (totaal nog te doen), 'zonder_sleutel', 'ongeldig',
 *          'bronnen' => [bron => ['titel', 'totaal', 'huidig', 'oud', 'zonder_sleutel', 'ongeldig']]].
 *   oud            = andere versie, sleutel bekend: kan herwrapt worden
 *   zonder_sleutel = andere versie waarvoor geen sleutel in config staat: onleesbaar tot die terug is
 *   ongeldig       = geen kluis-blob (beschadigd)
 */
function kluis_rotatie_status(): array {
    $huidig = kluis_kek_versie(); $oudev = kluis_oude_versie();
    $st = ['versie' => $huidig, 'oude_versie' => $oudev, 'configfout' => kluis_rotatie_configfout(),
           'oud' => 0, 'zonder_sleutel' => 0, 'ongeldig' => 0, 'bronnen' => []];
    foreach (kluis_bronnen() as $bron => $titel) {
        $b = ['titel' => $titel, 'totaal' => 0, 'huidig' => 0, 'oud' => 0, 'zonder_sleutel' => 0, 'ongeldig' => 0];
        foreach (kluis_bron_volgende($bron, '', PHP_INT_MAX) as $item) {
            $v = kluis_kop_versie($item[1]);
            $b['totaal']++;
            if ($v === null)        $b['ongeldig']++;
            elseif ($v === $huidig) $b['huidig']++;
            elseif ($v === $oudev)  $b['oud']++;
            else                    $b['zonder_sleutel']++;
        }
        foreach (['oud', 'zonder_sleutel', 'ongeldig'] as $k) $st[$k] += $b[$k];
        $st['bronnen'][$bron] = $b;
    }
    return $st;
}

/**
 * Eén batch van de rotatie: herwrapt tot $max blobs (of tot $max_sec seconden om zijn) en geeft de
 * nieuwe stand terug. Hervatbaar: geef de teruggegeven stand bij de volgende aanroep weer mee.
 * Een mislukte blob stopt de rest niet; hij komt in 'fouten' (bron + id/bestandsnaam, nooit inhoud).
 *
 * Stand: ['bron' => string, 'na' => string, 'gedaan' => int, 'al_goed' => int, 'mislukt' => int,
 *         'fouten' => [string], 'klaar' => bool]. Tellingen lopen op over de batches heen.
 */
function kluis_herversleutel_batch(array $stand = [], int $max = 200, float $max_sec = 20.0): array {
    $bronnen = array_keys(kluis_bronnen());
    $s = [
        'bron'    => (string)($stand['bron'] ?? ''), 'na' => (string)($stand['na'] ?? ''),
        'gedaan'  => (int)($stand['gedaan'] ?? 0), 'al_goed' => (int)($stand['al_goed'] ?? 0), 'mislukt' => (int)($stand['mislukt'] ?? 0),
        'fouten'  => array_slice(array_map('strval', (array)($stand['fouten'] ?? [])), 0, 50), 'klaar' => false,
    ];
    $i = array_search($s['bron'], $bronnen, true);
    if ($i === false) { $i = 0; $s['na'] = ''; }
    $deze = ['gedaan' => 0, 'al_goed' => 0, 'mislukt' => 0];
    $start = microtime(true); $verwerkt = 0;
    while ($i < count($bronnen) && $verwerkt < $max && microtime(true) - $start < $max_sec) {
        $bron  = $bronnen[$i];
        $items = kluis_bron_volgende($bron, $s['na'], $max - $verwerkt);
        if (!$items) { $i++; $s['na'] = ''; continue; }
        foreach ($items as $item) {
            $s['na'] = $item[0]; $verwerkt++;
            $r = kluis_kop_versie($item[1]) === kluis_kek_versie() ? ['status' => 'al_goed', 'reden' => ''] : kluis_bron_herwrap($bron, $item[0]);
            $deze[$r['status']] = ($deze[$r['status']] ?? 0) + 1;
            if ($r['status'] === 'mislukt' && count($s['fouten']) < 50) $s['fouten'][] = $bron . ' ' . $item[0] . ': ' . $r['reden'];
            if (microtime(true) - $start >= $max_sec) break;
        }
    }
    $s['bron'] = $bronnen[$i] ?? '';
    $s['klaar'] = $i >= count($bronnen);
    foreach ($deze as $k => $n) $s[$k] += $n;
    if (($deze['gedaan'] + $deze['mislukt']) > 0 && function_exists('audit_log')) {
        audit_log('kluis_herversleuteld', 'systeem', null, $deze + ['naar_versie' => kluis_kek_versie(), 'klaar' => $s['klaar']]);
    }
    return $s;
}

/** Gemaskeerde weergave, bijv. BSN "*****123" of IBAN "NL** **** **** **12 34". */
function kluis_maskeer(string $waarde, int $zichtbaar = 3): string {
    $w = preg_replace('/\s+/', '', $waarde);
    $n = strlen($w);
    if ($n <= $zichtbaar) return str_repeat('*', $n);
    return str_repeat('*', $n - $zichtbaar) . substr($w, -$zichtbaar);
}

} // function_exists

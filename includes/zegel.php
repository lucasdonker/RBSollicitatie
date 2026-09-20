<?php
/**
 * Paal 13 — Documentzegel: PDF's digitaal ondertekenen (PAdES / PKCS#7 detached) met een eigen X.509-certificaat.
 *
 * Elke verzegelde PDF krijgt een handtekeningveld waarmee Acrobat/Reader kan vaststellen dat er ná het
 * verzegelen geen byte is veranderd. Het certificaat is een eigen "Reddingsbrigade Paal 13 – Documentzegel"
 * (zelf uitgegeven, 10 jaar; Reader meldt "geldigheid onbekend" maar wél of het document intact is).
 * Later een AATL-certificaat (Sectigo/GlobalSign) plaatsen = zelfde code, ander certificaat.
 *
 * Sleutelbeheer: het certificaat (publiek) staat als cert.pem in de zegelmap; de privésleutel staat
 * versleuteld in de kluis (key.enc). Beide worden bij het eerste gebruik automatisch aangemaakt.
 *
 * PDF-motor: TCPDF 6.x (includes/lib/tcpdf, LGPL) + FPDI (includes/lib/fpdi, MIT) voor het importeren van
 * bestaande PDF's (het loonheffingsformulier). zegel_pdf_nieuw() geeft een klaargezette instantie.
 *
 * Beperking: een RFC 3161-tijdstempel van een externe TSA ondersteunt TCPDF niet; het tijdstip komt
 * uit de handtekening zelf + het gebeurtenissenlog (certificaat van ondertekening).
 */

if (!function_exists('zegel_status')) {

/** Laadt TCPDF + FPDI (één keer). Retourneert false als de bibliotheken ontbreken. */
function zegel_lib_laden(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    $tcpdf = __DIR__ . '/lib/tcpdf/tcpdf.php';
    $fpdi  = __DIR__ . '/lib/fpdi/autoload.php';
    if (!is_file($tcpdf) || !is_file($fpdi)) return $ok = false;
    if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
        // Eigen configuratie: geen voorbeeldmappen, cache in de systeemtemp, geen header/footer-defaults.
        define('K_TCPDF_EXTERNAL_CONFIG', true);
        define('K_PATH_MAIN', __DIR__ . '/lib/tcpdf/');
        define('K_PATH_URL', '');
        define('K_PATH_FONTS', K_PATH_MAIN . 'fonts/');
        define('K_PATH_CACHE', rtrim(sys_get_temp_dir(), '/\\') . '/');
        define('K_PATH_URL_CACHE', K_PATH_CACHE);
        define('K_PATH_IMAGES', K_PATH_MAIN);
        define('K_BLANK_IMAGE', K_PATH_MAIN . '_blank.png');
        define('PDF_PAGE_FORMAT', 'A4');
        define('PDF_PAGE_ORIENTATION', 'P');
        define('PDF_CREATOR', 'Paal 13 sollicitatiesysteem');
        define('PDF_AUTHOR', 'Reddingsbrigade Paal 13');
        define('PDF_HEADER_TITLE', '');
        define('PDF_HEADER_STRING', '');
        define('PDF_HEADER_LOGO', '');
        define('PDF_HEADER_LOGO_WIDTH', 0);
        define('PDF_UNIT', 'mm');
        define('PDF_MARGIN_HEADER', 0);
        define('PDF_MARGIN_FOOTER', 0);
        define('PDF_MARGIN_TOP', 18);
        define('PDF_MARGIN_BOTTOM', 18);
        define('PDF_MARGIN_LEFT', 20);
        define('PDF_MARGIN_RIGHT', 20);
        define('PDF_FONT_NAME_MAIN', 'helvetica');
        define('PDF_FONT_SIZE_MAIN', 10);
        define('PDF_FONT_NAME_DATA', 'helvetica');
        define('PDF_FONT_SIZE_DATA', 8);
        define('PDF_FONT_MONOSPACED', 'courier');
        define('PDF_IMAGE_SCALE_RATIO', 1.25);
        define('HEAD_MAGNIFICATION', 1.1);
        define('K_CELL_HEIGHT_RATIO', 1.25);
        define('K_TITLE_MAGNIFICATION', 1.3);
        define('K_SMALL_RATIO', 2 / 3);
        define('K_THAI_TOPCHARS', false);
        define('K_TCPDF_CALLS_IN_HTML', false);
        define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
        define('K_TIMEZONE', 'Europe/Amsterdam');
    }
    try {
        require_once $tcpdf;
        require_once $fpdi;
    } catch (\Throwable $e) { error_log('zegel_lib_laden: ' . $e->getMessage()); return $ok = false; }
    return $ok = class_exists('TCPDF') && class_exists('\\setasign\\Fpdi\\Tcpdf\\Fpdi');
}

/** Map voor certificaat + versleutelde sleutel (naast de documentenmap, buiten de webroot als die dat is). */
function zegel_map(): ?string {
    if (defined('ZEGEL_MAP') && ZEGEL_MAP !== '' && !str_contains((string)ZEGEL_MAP, '{{')) $dir = rtrim((string)ZEGEL_MAP, '/\\');
    elseif (function_exists('documenten_map') && ($dm = documenten_map()) !== null) $dir = $dm . '/zegel';
    else $dir = dirname(__DIR__) . '/uploads/zegel';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (!is_dir($dir) || !is_writable($dir)) return null;
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    return $dir;
}

/** Onderwerp van het certificaat (instelbaar via config). */
function zegel_dn(): array {
    return [
        'countryName'            => 'NL',
        'organizationName'       => defined('ZEGEL_ORGANISATIE') ? ZEGEL_ORGANISATIE : 'Reddingsbrigade Paal 13',
        'commonName'             => defined('ZEGEL_NAAM') ? ZEGEL_NAAM : 'Reddingsbrigade Paal 13 - Documentzegel',
        'emailAddress'           => function_exists('bestuur_email') ? bestuur_email() : (defined('MAIL_BESTUUR') ? MAIL_BESTUUR : 'bestuur@paal13.com'),
    ];
}

/**
 * openssl-configuratie voor het genereren van sleutels/certificaten. Op Linux (Cloud86) vindt openssl
 * zijn openssl.cnf zelf; op Windows (portable PHP) niet — dan via OPENSSL_CONF, ZEGEL_OPENSSL_CNF of
 * de extras/ssl-map van PHP. Retourneert ['config' => pad] of [].
 */
function zegel_openssl_config(): array {
    foreach ([defined('ZEGEL_OPENSSL_CNF') ? ZEGEL_OPENSSL_CNF : '', (string)getenv('OPENSSL_CONF'),
              dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf', dirname(PHP_BINARY) . '/openssl.cnf'] as $p) {
        if ($p !== '' && is_file($p)) return ['config' => $p];
    }
    return [];
}

/**
 * Zorgt dat er een certificaat + sleutel is. Maakt ze aan bij het eerste gebruik (RSA 3072, SHA-256, 10 jaar).
 * Retour: ['ok'=>bool, 'reden'=>string, 'aangemaakt'=>bool].
 */
function zegel_verzekeren(): array {
    if (!function_exists('openssl_pkey_new')) return ['ok' => false, 'reden' => 'openssl-extensie ontbreekt', 'aangemaakt' => false];
    if (!function_exists('kluis_beschikbaar') || !kluis_beschikbaar()) return ['ok' => false, 'reden' => 'kluis niet beschikbaar (de privésleutel wordt daarin bewaard)', 'aangemaakt' => false];
    $dir = zegel_map();
    if ($dir === null) return ['ok' => false, 'reden' => 'zegelmap niet schrijfbaar', 'aangemaakt' => false];
    if (is_file($dir . '/cert.pem') && is_file($dir . '/key.enc')) return ['ok' => true, 'reden' => '', 'aangemaakt' => false];
    try {
        $cfg = zegel_openssl_config();
        $sleutel = openssl_pkey_new($cfg + ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 3072, 'digest_alg' => 'sha256']);
        if ($sleutel === false) throw new \RuntimeException((openssl_error_string() ?: 'sleutel genereren mislukt') . ($cfg ? '' : ' (geen openssl.cnf gevonden; zet OPENSSL_CONF of ZEGEL_OPENSSL_CNF)'));
        $csr = openssl_csr_new(zegel_dn(), $sleutel, $cfg + ['digest_alg' => 'sha256']);
        if ($csr === false) throw new \RuntimeException('csr mislukt: ' . (openssl_error_string() ?: ''));
        $cert = openssl_csr_sign($csr, null, $sleutel, 3650, $cfg + ['digest_alg' => 'sha256'], time());
        if ($cert === false) throw new \RuntimeException('zelfondertekenen mislukt');
        $cert_pem = ''; $key_pem = '';
        openssl_x509_export($cert, $cert_pem);
        openssl_pkey_export($sleutel, $key_pem, null, $cfg);
        if ($cert_pem === '' || $key_pem === '') throw new \RuntimeException('export mislukt');
        if (!kluis_bestand_schrijven($key_pem, $dir . '/key.enc', 'zegel|key')) throw new \RuntimeException('sleutel versleuteld opslaan mislukt');
        kluis_wis($key_pem);
        if (@file_put_contents($dir . '/cert.pem', $cert_pem, LOCK_EX) === false) { @unlink($dir . '/key.enc'); throw new \RuntimeException('certificaat opslaan mislukt'); }
        @chmod($dir . '/cert.pem', 0600);
        if (function_exists('audit_log')) audit_log('zegel_certificaat_aangemaakt', 'systeem', null, ['vingerafdruk' => zegel_vingerafdruk($cert_pem)]);
        return ['ok' => true, 'reden' => '', 'aangemaakt' => true];
    } catch (\Throwable $e) {
        error_log('zegel_verzekeren: ' . $e->getMessage());
        return ['ok' => false, 'reden' => $e->getMessage(), 'aangemaakt' => false];
    }
}

function zegel_certificaat(): ?string {
    $dir = zegel_map();
    return ($dir !== null && is_file($dir . '/cert.pem')) ? ((string)@file_get_contents($dir . '/cert.pem') ?: null) : null;
}

/** Privésleutel (PEM) uit de kluis — alleen in het geheugen, direct na gebruik wissen. */
function zegel_sleutel(): ?string {
    $dir = zegel_map();
    if ($dir === null || !is_file($dir . '/key.enc') || !function_exists('kluis_bestand_lezen')) return null;
    return kluis_bestand_lezen($dir . '/key.enc', 'zegel|key');
}

/** SHA-256-vingerafdruk van het certificaat (hex, gegroepeerd). */
function zegel_vingerafdruk(?string $cert_pem = null): string {
    $cert_pem = $cert_pem ?? zegel_certificaat();
    if (!$cert_pem) return '';
    $fp = openssl_x509_fingerprint($cert_pem, 'sha256');
    return $fp ? strtoupper(implode(':', str_split($fp, 2))) : '';
}

/** Certificaatgegevens voor de health-check en het certificaat van ondertekening. */
function zegel_info(): array {
    $pem = zegel_certificaat();
    if (!$pem) return [];
    $p = openssl_x509_parse($pem);
    if (!$p) return [];
    return [
        'naam'          => (string)($p['subject']['CN'] ?? ''),
        'organisatie'   => (string)($p['subject']['O'] ?? ''),
        'geldig_van'    => date('Y-m-d', (int)$p['validFrom_time_t']),
        'geldig_tot'    => date('Y-m-d', (int)$p['validTo_time_t']),
        'serienummer'   => (string)($p['serialNumberHex'] ?? $p['serialNumber'] ?? ''),
        'vingerafdruk'  => zegel_vingerafdruk($pem),
        'zelf_uitgegeven' => ($p['issuer'] ?? []) == ($p['subject'] ?? []),
    ];
}

/** Status voor de health-check: ['ok'=>bool, 'reden'=>string, 'info'=>array]. Maakt het certificaat aan als het ontbreekt. */
function zegel_status(): array {
    if (!zegel_lib_laden()) return ['ok' => false, 'reden' => 'TCPDF/FPDI ontbreken in includes/lib.', 'info' => []];
    $v = zegel_verzekeren();
    if (!$v['ok']) return ['ok' => false, 'reden' => $v['reden'], 'info' => []];
    $info = zegel_info();
    if (!$info) return ['ok' => false, 'reden' => 'Certificaat onleesbaar.', 'info' => []];
    $sleutel = zegel_sleutel();
    if ($sleutel === null) return ['ok' => false, 'reden' => 'Privésleutel niet te ontsleutelen (kluis-sleutel gewijzigd?).', 'info' => $info];
    $pk = openssl_pkey_get_private($sleutel);
    kluis_wis($sleutel);
    if ($pk === false) return ['ok' => false, 'reden' => 'Privésleutel ongeldig.', 'info' => $info];
    if (strtotime($info['geldig_tot']) < time()) return ['ok' => false, 'reden' => 'Certificaat is verlopen op ' . $info['geldig_tot'] . '.', 'info' => $info];
    return ['ok' => true, 'reden' => '', 'info' => $info];
}

/**
 * Nieuwe PDF-instantie (TCPDF + FPDI), zonder header/footer, met de huisstijl-instellingen.
 * @return \setasign\Fpdi\Tcpdf\Fpdi
 */
function zegel_pdf_nieuw(string $titel = '') {
    if (!zegel_lib_laden()) throw new \RuntimeException('PDF-bibliotheek niet beschikbaar');
    $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Paal 13 sollicitatiesysteem');
    $pdf->SetAuthor('Reddingsbrigade Paal 13');
    if ($titel !== '') $pdf->SetTitle($titel);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 18, 20);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->setFontSubsetting(false);
    $pdf->SetFont('helvetica', '', 10);
    return $pdf;
}

/**
 * Zegel op de PDF zetten: certificaat + sleutel uit de kluis, handtekeningveld zichtbaar op de opgegeven plek.
 * $info: Name/Location/Reason/ContactInfo. $veld: ['x','y','w','h','pagina'] in mm, of null voor onzichtbaar.
 * Aanroepen ná het opbouwen van alle pagina's en vóór zegel_bytes().
 */
function zegel_toepassen($pdf, array $info, ?array $veld = null): bool {
    $cert = zegel_certificaat(); $sleutel = zegel_sleutel();
    if (!$cert || $sleutel === null) return false;
    $pdf->setSignature($cert, $sleutel, '', '', 2, [
        'Name'        => $info['Name'] ?? 'Reddingsbrigade Paal 13',
        'Location'    => $info['Location'] ?? 'Callantsoog',
        'Reason'      => $info['Reason'] ?? 'Digitaal ondertekend via het sollicitatiesysteem van Reddingsbrigade Paal 13',
        'ContactInfo' => $info['ContactInfo'] ?? (function_exists('bestuur_email') ? bestuur_email() : ''),
    ]);
    if ($veld) $pdf->setSignatureAppearance((float)$veld['x'], (float)$veld['y'], (float)$veld['w'], (float)$veld['h'], (int)($veld['pagina'] ?? -1));
    // TCPDF houdt de sleutel als string vast tot Output(); daarna wissen we onze kopie.
    kluis_wis($sleutel);
    return true;
}

/** De PDF als bytes (in het geheugen; niets op schijf behalve TCPDF's tijdelijke bestanden tijdens het tekenen). */
function zegel_bytes($pdf, string $naam = 'document.pdf'): string {
    return (string)$pdf->Output($naam, 'S');
}

/** Bevat deze PDF een digitale handtekening? (structurele controle; de echte verificatie doet de PDF-lezer) */
function zegel_pdf_is_verzegeld(string $bytes): bool {
    return str_contains($bytes, '/Type /Sig') && str_contains($bytes, '/ByteRange') && preg_match('#/Contents\s*<[0-9A-Fa-f]{64,}#', $bytes) === 1;
}

} // function_exists

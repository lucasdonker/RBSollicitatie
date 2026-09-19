<?php
/**
 * Paal 13 — Gedeelde upload-validatie (bijlagenbibliotheek, documenten van sollicitanten, foto's).
 *
 * Eén plek voor: upload-fout, grootte, extensie-whitelist, magic-bytes/mime-controle en een veilige
 * bestandsnaam. Geeft alleen een oordeel; het opslaan doet de aanroeper.
 */

if (!function_exists('upload_valideer')) {

/** Leesbare tekst bij een PHP-uploadfout. */
function upload_fouttekst(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'het bestand is te groot voor de server',
        UPLOAD_ERR_PARTIAL => 'het bestand is maar gedeeltelijk ontvangen; probeer het opnieuw',
        UPLOAD_ERR_NO_FILE => 'geen bestand gekozen',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'de server kon het bestand niet bewaren',
        default => 'uploaden mislukt',
    };
}

/** "10M"/"2G"-notatie van php.ini naar bytes. */
function upload_ini_bytes(string $v): int {
    $v = trim($v); if ($v === '' || $v === '-1') return PHP_INT_MAX;
    $n = (int)$v; $e = strtolower(substr($v, -1));
    return $e === 'g' ? $n * 1073741824 : ($e === 'm' ? $n * 1048576 : ($e === 'k' ? $n * 1024 : $n));
}

/** Het serverplafond voor één upload (upload_max_filesize / post_max_size). */
function upload_server_max_bytes(): int {
    return (int)min(upload_ini_bytes((string)ini_get('upload_max_filesize')), upload_ini_bytes((string)ini_get('post_max_size')));
}

/**
 * Is deze POST door post_max_size weggegooid?
 * PHP levert dan een lege $_POST én lege $_FILES, terwijl er wel een body was. Zonder deze
 * controle loopt zo'n te groot bestand stuk op csrf_check() — de beheerder krijgt dan een
 * onbegrijpelijke beveiligingsmelding in plaats van "het bestand is te groot".
 * Aanroepen vóór csrf_check(), alleen op schermen met een upload.
 */
function upload_post_te_groot(): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return false;
    $lengte = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($lengte <= 0 || !empty($_POST) || !empty($_FILES)) return false;
    $max = upload_ini_bytes((string)ini_get('post_max_size'));
    return $max > 0 && $lengte > $max;
}

/**
 * Valideer een $_FILES-element.
 * $typen: extensie => mime (bijv. ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg']).
 * Retour: ['ok'=>bool, 'fout'=>?string, 'ext'=>string, 'mime'=>string, 'naam'=>string (veilig), 'grootte'=>int].
 */
function upload_valideer(array $upload, array $typen, int $max_bytes): array {
    $uit = ['ok' => false, 'fout' => null, 'ext' => '', 'mime' => '', 'naam' => '', 'grootte' => 0];
    $code = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($code !== UPLOAD_ERR_OK || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
        $uit['fout'] = upload_fouttekst($code); return $uit;
    }
    $grootte = (int)($upload['size'] ?? 0);
    $max = min($max_bytes, upload_server_max_bytes());
    if ($grootte <= 0) { $uit['fout'] = 'het bestand is leeg'; return $uit; }
    if ($grootte > $max) { $uit['fout'] = 'het bestand is groter dan ' . round($max / 1048576) . ' MB'; return $uit; }

    $naam = (string)($upload['name'] ?? 'bestand');
    $ext  = strtolower(pathinfo($naam, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') $ext = 'jpg';
    if (!isset($typen[$ext]) && !($ext === 'jpg' && isset($typen['jpeg']))) {
        $uit['fout'] = 'bestandstype niet toegestaan (' . implode(', ', array_unique(array_keys($typen))) . ')'; return $uit;
    }
    $verwacht = $typen[$ext] ?? $typen['jpeg'];

    // Inhoud controleren (magic bytes) i.p.v. alleen de extensie
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = @finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) { $mime = (string)(@finfo_file($fi, $upload['tmp_name']) ?: ''); @finfo_close($fi); }
    } elseif (function_exists('mime_content_type')) {
        $mime = (string)(@mime_content_type($upload['tmp_name']) ?: '');
    }
    if ($mime === '') {
        // Geen fileinfo: minimale eigen controle op de eerste bytes
        $kop = (string)@file_get_contents($upload['tmp_name'], false, null, 0, 12);
        $mime = match (true) {
            str_starts_with($kop, "\xFF\xD8\xFF")            => 'image/jpeg',
            str_starts_with($kop, "\x89PNG\r\n\x1A\n")        => 'image/png',
            str_starts_with($kop, '%PDF')                     => 'application/pdf',
            substr($kop, 0, 4) === 'RIFF' && substr($kop, 8, 4) === 'WEBP' => 'image/webp',
            str_starts_with($kop, "PK\x03\x04")               => 'application/zip',
            default => 'application/octet-stream',
        };
    }
    $office = in_array($ext, ['docx', 'xlsx'], true);
    if ($mime !== $verwacht && !($office && in_array($mime, ['application/zip', 'application/octet-stream'], true))) {
        $uit['fout'] = 'de inhoud van het bestand past niet bij het type'; return $uit;
    }

    $veilig = preg_replace('/[^A-Za-z0-9._-]+/', '_', $naam);
    $veilig = substr(trim((string)$veilig, '_') ?: ('bestand.' . $ext), 0, 200);

    return ['ok' => true, 'fout' => null, 'ext' => $ext, 'mime' => $verwacht, 'naam' => $veilig, 'grootte' => $grootte];
}

} // function_exists

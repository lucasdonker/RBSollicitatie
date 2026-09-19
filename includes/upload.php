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
 * Hoeveel bestanden neemt PHP in één POST aan (max_file_uploads)?
 * Kiest iemand er meer, dan gooit PHP de bestanden daarboven weg zonder foutcode: ze
 * komen simpelweg niet in $_FILES voor. Een scherm met een meervoudige upload moet dus
 * zelf niet meer beloven dan dit.
 */
function upload_max_bestanden(): int {
    $n = (int)ini_get('max_file_uploads');
    return $n > 0 ? $n : 20;
}

/**
 * Eén of meer $_FILES-elementen uit elkaar halen.
 *
 * PHP levert bij <input name="foto"> een plat array en bij <input name="foto[]" multiple>
 * een array van kolommen (name[], type[], tmp_name[], …). Deze functie maakt daar altijd
 * een lijst van losse upload-arrays van, zodat de aanroeper maar één vorm kent.
 * Lege plekken (niets gekozen) vallen weg.
 */
function upload_bestanden(array $veld): array {
    if (!isset($veld['name'])) return [];
    if (!is_array($veld['name'])) {
        return (int)($veld['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE && empty($veld['tmp_name']) ? [] : [$veld];
    }
    $uit = [];
    foreach (array_keys($veld['name']) as $i) {
        if ((int)($veld['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $uit[] = [
            'name'     => (string)($veld['name'][$i] ?? ''),
            'type'     => (string)($veld['type'][$i] ?? ''),
            'tmp_name' => (string)($veld['tmp_name'][$i] ?? ''),
            'error'    => (int)($veld['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int)($veld['size'][$i] ?? 0),
        ];
    }
    return $uit;
}

/**
 * Schrijfwijzen van hetzelfde type gelijktrekken. Servers (en oude browsers) noemen een
 * JPEG ook wel image/jpg of image/pjpeg; zonder deze vertaling zou de vergelijking
 * hieronder een volstrekt geldig bestand weigeren.
 */
function upload_mime_normaliseer(string $mime): string {
    $delen = explode(';', $mime);
    $m = strtolower(trim($delen[0]));
    return match ($m) {
        'image/jpg', 'image/pjpeg', 'image/x-jpeg', 'image/jpe' => 'image/jpeg',
        'image/x-png', 'image/x-citrix-png'                     => 'image/png',
        'image/x-webp', 'application/x-webp', 'image/webp2'     => 'image/webp',
        'image/x-gif'                                           => 'image/gif',
        default => $m,
    };
}

/**
 * Welk type zit er écht in dit bestand? Eerst fileinfo, daarna getimagesize() (die leest
 * een afbeelding echt uit, ook als libmagic hem niet kent), en pas als laatste de eerste
 * bytes zelf. Lege string = niet vast te stellen.
 */
function upload_mime_bepaal(string $pad): string {
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = @finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) { $mime = (string)(@finfo_file($fi, $pad) ?: ''); @finfo_close($fi); }
    } elseif (function_exists('mime_content_type')) {
        $mime = (string)(@mime_content_type($pad) ?: '');
    }
    $mime = upload_mime_normaliseer($mime);

    if ($mime === '' || $mime === 'application/octet-stream' || str_starts_with($mime, 'text/')) {
        $info = @getimagesize($pad);
        if (is_array($info) && !empty($info['mime'])) return upload_mime_normaliseer((string)$info['mime']);
    }
    if ($mime !== '') return $mime;

    $kop = (string)@file_get_contents($pad, false, null, 0, 12);
    return match (true) {
        str_starts_with($kop, "\xFF\xD8\xFF")                          => 'image/jpeg',
        str_starts_with($kop, "\x89PNG\r\n\x1A\n")                      => 'image/png',
        str_starts_with($kop, 'GIF87a') || str_starts_with($kop, 'GIF89a') => 'image/gif',
        substr($kop, 0, 4) === 'RIFF' && substr($kop, 8, 4) === 'WEBP'  => 'image/webp',
        str_starts_with($kop, '%PDF')                                   => 'application/pdf',
        str_starts_with($kop, "PK\x03\x04")                             => 'application/zip',
        default => '',
    };
}

/**
 * Valideer een $_FILES-element.
 * $typen: extensie => mime (bijv. ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg']).
 * Retour: ['ok'=>bool, 'fout'=>?string, 'ext'=>string, 'mime'=>string, 'naam'=>string (veilig), 'grootte'=>int].
 *
 * De inhoud bepaalt of een bestand door mag, de extensie bepaalt hoe het gaat heten. Een PNG
 * die "foto.jpg" heet is dus geen fout maar een verkeerd etiket: zolang het gevonden type in
 * de whitelist staat, gaat hij door en houdt hij de naam die de gebruiker gaf — daar verwijst
 * zijn ontwerp of zijn administratie immers naar. `mime` vertelt wél wat er écht in zit.
 * Alleen een type dat helemaal niet in de whitelist staat, valt af.
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

    // De extensie blijft zoals de gebruiker hem aanleverde (alleen kleine letters). Alleen als
    // de whitelist de ene schrijfwijze van jpeg niet kent, pakken we de andere.
    $naam = (string)($upload['name'] ?? 'bestand');
    $ext  = strtolower(pathinfo($naam, PATHINFO_EXTENSION));
    if (!isset($typen[$ext]) && $ext === 'jpeg' && isset($typen['jpg']))  $ext = 'jpg';
    if (!isset($typen[$ext]) && $ext === 'jpg'  && isset($typen['jpeg'])) $ext = 'jpeg';
    if (!isset($typen[$ext])) {
        $uit['fout'] = 'bestandstype niet toegestaan (' . implode(', ', array_unique(array_keys($typen))) . ')'; return $uit;
    }
    $verwacht = $typen[$ext];

    // Inhoud controleren i.p.v. alleen de extensie
    $mime   = upload_mime_bepaal((string)$upload['tmp_name']);
    $office = in_array($ext, ['docx', 'xlsx'], true);

    if ($mime !== $verwacht) {
        if ($office && in_array($mime, ['application/zip', 'application/octet-stream', ''], true)) {
            $mime = $verwacht;                       // docx/xlsx zijn zip-bestanden
        } elseif (in_array($mime, $typen, true)) {
            // Ander type dan de extensie belooft, maar wel een toegestaan type: doorlaten en
            // de naam laten staan. Alleen $mime vertelt voortaan wat erin zit.
        } else {
            $uit['fout'] = 'de inhoud van het bestand past niet bij het type (herkend als '
                         . ($mime !== '' ? $mime : 'onbekend') . ', verwacht ' . $verwacht . ')';
            return $uit;
        }
    }

    $basis  = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($naam, PATHINFO_FILENAME));
    $basis  = substr(trim((string)$basis, '_.'), 0, 190);
    $veilig = ($basis !== '' ? $basis : 'bestand') . '.' . $ext;

    return ['ok' => true, 'fout' => null, 'ext' => $ext, 'mime' => $mime, 'naam' => $veilig, 'grootte' => $grootte];
}

} // function_exists

<?php
/**
 * Paal 13 — Adresvalidatie via PDOK (Kadaster).
 * Drie-stappen-fallback voor maximale coverage.
 *
 * Alleen voor ingelogde beheerders: zonder login was dit een open doorgeefluik naar
 * PDOK. Het is een opzoeking (GET, verandert niets), dus geen CSRF; wel een
 * snelheidsgrens per beheerder. Het portaal heeft een eigen endpoint
 * (portaal/api_adres.php).
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_login();
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!rate_limit('admin_adres', 'bhd' . (int)($_SESSION['bhd_id'] ?? 0) . ':' . rate_identiteit(), 30, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'fout' => 'Te veel opzoekingen achter elkaar. Probeer het over een minuut opnieuw.']);
    exit;
}

$pc_raw = strtoupper(preg_replace('/\s+/', '', $_GET['postcode'] ?? ''));
$hn_raw = trim($_GET['huisnummer'] ?? '');

if (!preg_match('/^[0-9]{4}[A-Z]{2}$/', $pc_raw) || $hn_raw === '') {
    echo json_encode(['ok' => false, 'fout' => 'Geldige postcode + huisnummer vereist']);
    exit;
}

// Split huisnummer in cijfer + toevoeging
if (preg_match('/^(\d+)[\s\-]?(.*)$/', $hn_raw, $m)) {
    $hn_nr  = $m[1];
    $hn_toe = strtoupper(preg_replace('/[\s\-]/', '', $m[2]));
} else {
    $hn_nr  = preg_replace('/[^0-9]/', '', $hn_raw);
    $hn_toe = '';
}
if (!$hn_nr) {
    echo json_encode(['ok' => false, 'fout' => 'Huisnummer ontbreekt']);
    exit;
}

if (!function_exists('curl_init')) {
    echo json_encode(['ok' => false, 'fout' => 'cURL niet beschikbaar']);
    exit;
}

function pdok_call(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Paal13-Sollicitatie/1.0',
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 200) return null;
    $data = json_decode($resp, true);
    return $data['response']['docs'] ?? [];
}

$base = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free?';
$fl   = 'fl=' . urlencode('straatnaam,woonplaatsnaam,huisnummer,huisnummertoevoeging,huisletter,postcode,weergavenaam,type');

// STAP 1: exact met toevoeging
if ($hn_toe) {
    $q = urlencode("postcode:$pc_raw AND huisnummer:$hn_nr AND (huisletter:$hn_toe OR huisnummertoevoeging:$hn_toe)");
    $docs = pdok_call("{$base}q=$q&$fl&rows=5&fq=type:adres");
    foreach ($docs ?? [] as $d) {
        echo json_encode([
            'ok'=>true,'straat'=>$d['straatnaam']??'','woonplaats'=>$d['woonplaatsnaam']??'',
            'postcode'=>$d['postcode']??$pc_raw,'huisnummer'=>$hn_nr.$hn_toe,'bron'=>'exact_toevoeging',
        ]);
        exit;
    }
}

// STAP 2: postcode + huisnummer (meest werkende)
$q = urlencode("postcode:$pc_raw AND huisnummer:$hn_nr");
$docs = pdok_call("{$base}q=$q&$fl&rows=5&fq=type:adres");
if (!empty($docs)) {
    $doc = $docs[0];
    if ($hn_toe) {
        foreach ($docs as $d) {
            $dt = strtoupper(($d['huisletter'] ?? '') . ($d['huisnummertoevoeging'] ?? ''));
            if ($dt === $hn_toe) { $doc = $d; break; }
        }
    }
    echo json_encode([
        'ok'=>true,'straat'=>$doc['straatnaam']??'','woonplaats'=>$doc['woonplaatsnaam']??'',
        'postcode'=>$doc['postcode']??$pc_raw,'huisnummer'=>$hn_nr.$hn_toe,'bron'=>'pc_hn',
    ]);
    exit;
}

// STAP 3: alleen postcode (geeft straat + woonplaats terug)
$q = urlencode("postcode:$pc_raw");
$docs = pdok_call("{$base}q=$q&$fl&rows=1&fq=type:postcode");
if (empty($docs)) $docs = pdok_call("{$base}q=$q&$fl&rows=1");

if (!empty($docs)) {
    $doc = $docs[0];
    echo json_encode([
        'ok'=>true,'straat'=>$doc['straatnaam']??'','woonplaats'=>$doc['woonplaatsnaam']??'',
        'postcode'=>$pc_raw,'huisnummer'=>$hn_nr.$hn_toe,'bron'=>'alleen_postcode',
        'waarschuwing'=>'Huisnummer niet exact gevonden — controleer of het klopt.',
    ]);
    exit;
}

echo json_encode(['ok'=>false,'fout'=>'Geen adres gevonden. Controleer postcode of vul handmatig in.']);

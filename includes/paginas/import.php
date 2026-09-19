<?php
/**
 * Pagina's — import van een los HTML-bestand (bijvoorbeeld een ontwerp uit Claude Design).
 *
 * Het bestand wordt NIET opgeschoond: animaties, tabs en tellers moeten blijven werken.
 * In plaats daarvan krijgt de beheerder een rapport van wat er in het bestand zit, en moet
 * hij dat bevestigen voordat de versie live kan. De Content-Security-Policy op de publieke
 * pagina houdt externe bronnen standaard tegen; per host wordt bewust een uitzondering
 * gemaakt (zie kern.php → csp_extra en render.php → pag_csp_regel).
 */

if (!function_exists('pag_upload_valideer')) {

/**
 * Een geüpload .html-bestand controleren en inlezen.
 * Bewust niet via upload_valideer(): HTML heeft geen magic bytes, waardoor finfo er
 * text/html, text/plain of zelfs text/x-php van maakt en de strenge mime-vergelijking
 * daar legitieme ontwerpen zou weigeren.
 * Retour: ['ok'=>bool, 'html'=>string, 'naam'=>string, 'fout'=>string]
 */
function pag_upload_valideer(array $upload): array {
    $mis = ['ok' => false, 'html' => '', 'naam' => '', 'fout' => ''];

    $code = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($code !== UPLOAD_ERR_OK || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
        $mis['fout'] = 'Uploaden mislukt: ' . upload_fouttekst($code) . '.';
        return $mis;
    }
    $grootte = (int)($upload['size'] ?? 0);
    $max = min(pag_max_html_bytes(), upload_server_max_bytes());
    if ($grootte <= 0)   { $mis['fout'] = 'Het bestand is leeg.'; return $mis; }
    if ($grootte > $max) { $mis['fout'] = 'Het bestand is groter dan ' . round($max / 1024) . ' KB.'; return $mis; }

    $naam = (string)($upload['name'] ?? 'ontwerp.html');
    $ext  = strtolower(pathinfo($naam, PATHINFO_EXTENSION));
    if (!in_array($ext, ['html', 'htm'], true)) {
        $mis['fout'] = 'Kies een .html-bestand. Een ontwerp bestaat uit één HTML-bestand; afbeeldingen upload je er los bij.';
        return $mis;
    }

    $html = (string)@file_get_contents($upload['tmp_name']);
    if ($html === '') { $mis['fout'] = 'Het bestand kon niet worden gelezen.'; return $mis; }

    // Byte order mark weghalen en de tekst controleren: alles wordt als UTF-8 geserveerd.
    if (str_starts_with($html, "\xEF\xBB\xBF")) $html = substr($html, 3);
    if (!mb_check_encoding($html, 'UTF-8')) {
        $omgezet = @mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
        if ($omgezet === false || !mb_check_encoding($omgezet, 'UTF-8')) {
            $mis['fout'] = 'Het bestand staat niet in UTF-8. Sla het ontwerp opnieuw op als UTF-8.';
            return $mis;
        }
        $html = $omgezet;
    }

    // Harde grens: PHP-code in een geüpload bestand accepteren we nooit.
    if (preg_match('/<\?php|<\?=|<\?\s|<%/i', $html)) {
        $mis['fout'] = 'Het bestand bevat servercode (<?php of <%). Dat is niet toegestaan in een ontwerp.';
        return $mis;
    }

    return ['ok' => true, 'html' => $html, 'naam' => substr(preg_replace('/[^A-Za-z0-9._-]+/', '_', $naam), 0, 200), 'fout' => ''];
}

/** Hostnamen van bekende trackers; die verdienen altijd een waarschuwing. */
function pag_tracker_patronen(): array {
    return ['google-analytics', 'googletagmanager', 'doubleclick', 'connect.facebook', 'facebook.net',
            'hotjar', 'clarity.ms', 'segment.com', 'segment.io', 'matomo', 'piwik',
            'analytics.tiktok', 'snap.licdn.com', 'sentry.io', 'mixpanel', 'amplitude'];
}

/**
 * Lees het ontwerp na en meld wat erin zit. Bewust met reguliere expressies en niet met een
 * DOM-parser: we schonen toch niets op, dus een grove maar volledige lijst is nuttiger dan
 * een nette boom — en het scheelt een afhankelijkheid van libxml.
 *
 * Retour: ['bevindingen'=>[['niveau','titel','detail']], 'hosts'=>[...], 'telling'=>[niveau=>n]]
 */
function pag_scan_html(string $html): array {
    $b = [];
    $hosts = [];

    $extern = function (string $url): ?string {
        if (!preg_match('~^(?:https?:)?//([^/"\'\s]+)~i', trim($url), $m)) return null;
        $host = strtolower($m[1]);
        $eigen = defined('APP_URL') ? parse_url(APP_URL, PHP_URL_HOST) : '';
        return ($eigen && strcasecmp($host, (string)$eigen) === 0) ? null : $host;
    };

    // ── Hoog: externe scripts, frames en formulieren ──
    if (preg_match_all('/<script[^>]+src\s*=\s*["\']?([^"\'>\s]+)/i', $html, $m)) {
        foreach ($m[1] as $url) {
            $host = $extern($url);
            if ($host === null) continue;
            $hosts[] = $host;
            $b[] = ['niveau' => 'hoog', 'titel' => 'Extern script', 'detail' => $host . ' — deze code draait mee op de pagina en kan door de eigenaar van die server gewijzigd worden.'];
        }
    }
    if (preg_match_all('/<(iframe|embed|object)[^>]+(?:src|data)\s*=\s*["\']?([^"\'>\s]+)/i', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $set) {
            $host = $extern($set[2]);
            if ($host === null) continue;
            $hosts[] = $host;
            $b[] = ['niveau' => 'hoog', 'titel' => 'Externe inhoud (' . strtolower($set[1]) . ')', 'detail' => $host];
        }
    }
    if (preg_match_all('/<form[^>]+action\s*=\s*["\']?([^"\'>\s]+)/i', $html, $m)) {
        foreach ($m[1] as $url) {
            $host = $extern($url);
            if ($host === null) continue;
            $hosts[] = $host;
            $b[] = ['niveau' => 'hoog', 'titel' => 'Formulier naar een externe website', 'detail' => $host . ' — ingevulde gegevens gaan dan naar die partij, niet naar Paal 13.'];
        }
    }

    // ── Hoog: trackers ──
    foreach (pag_tracker_patronen() as $p) {
        if (stripos($html, $p) !== false) {
            $b[] = ['niveau' => 'hoog', 'titel' => 'Mogelijke tracker', 'detail' => $p . ' — volgt bezoekers. Dat vraagt om toestemming vooraf (cookiebanner) en een vermelding in de privacyverklaring.'];
        }
    }

    // ── Midden: inline script, externe stijlen, netwerkverkeer, opslag ──
    if (preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $html, $m)) {
        $bytes = array_sum(array_map('strlen', $m[1]));
        if ($bytes > 0) {
            $b[] = ['niveau' => 'midden', 'titel' => 'Scripts in het bestand zelf', 'detail' => count($m[1]) . ' stuk(s), samen ' . round($bytes / 1024, 1) . ' KB. Deze code draait op de pagina; hij is niet gecontroleerd.'];
        }
    }
    if (preg_match_all('/<link[^>]+rel\s*=\s*["\']?stylesheet["\']?[^>]*href\s*=\s*["\']?([^"\'>\s]+)|@import\s+(?:url\()?["\']([^"\')]+)/i', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $set) {
            $host = $extern($set[1] ?: ($set[2] ?? ''));
            if ($host === null) continue;
            $hosts[] = $host;
            $niveau = preg_match('/fonts\.(googleapis|gstatic)\.com/i', $host) ? 'midden' : 'midden';
            $extra  = preg_match('/fonts\.(googleapis|gstatic)\.com/i', $host) ? ' — Google Fonts stuurt het IP-adres van de bezoeker naar de VS. Zet het lettertype liever in het bestand zelf.' : '';
            $b[] = ['niveau' => $niveau, 'titel' => 'Externe stijl of lettertype', 'detail' => $host . $extra];
        }
    }
    if (preg_match('/\bfetch\s*\(|XMLHttpRequest|sendBeacon|new\s+WebSocket/i', $html)) {
        $b[] = ['niveau' => 'midden', 'titel' => 'De pagina verstuurt gegevens', 'detail' => 'Er staat code in die zelf netwerkverkeer opzet (fetch/XHR/WebSocket). Dit wordt geblokkeerd tenzij je de host hieronder toestaat.'];
    }
    if (preg_match('/localStorage|sessionStorage|document\.cookie/i', $html)) {
        $b[] = ['niveau' => 'midden', 'titel' => 'De pagina slaat iets op bij de bezoeker', 'detail' => 'Cookies of browseropslag. Voor niet-noodzakelijke opslag is toestemming vooraf nodig.'];
    }

    // ── Laag ──
    if (preg_match_all('/<img[^>]+src\s*=\s*["\']?([^"\'>\s]+)/i', $html, $m)) {
        $ext_img = [];
        foreach ($m[1] as $url) { $host = $extern($url); if ($host !== null) $ext_img[$host] = true; }
        foreach (array_keys($ext_img) as $host) {
            $hosts[] = $host;
            $b[] = ['niveau' => 'laag', 'titel' => 'Afbeelding van een andere website', 'detail' => $host . ' — upload hem liever bij de pagina, dan blijft hij werken.'];
        }
    }
    if (preg_match_all('/\son[a-z]+\s*=\s*["\']/i', $html, $m)) {
        $b[] = ['niveau' => 'laag', 'titel' => 'Klik- en muisacties in de opmaak', 'detail' => count($m[0]) . ' stuk(s) (onclick en dergelijke).'];
    }
    if (stripos($html, 'javascript:') !== false) {
        $b[] = ['niveau' => 'laag', 'titel' => 'Link met javascript:', 'detail' => 'Zulke links werken niet met toetsenbordbediening en worden door de beveiliging geblokkeerd.'];
    }

    // ── Informatief: opbouw en toegankelijkheid ──
    if (!str_contains($html, '[[formulier]]')) {
        $b[] = ['niveau' => 'info', 'titel' => 'Geen aanmeldformulier op de pagina', 'detail' => 'Zet [[formulier]] in het ontwerp op de plek waar het formulier moet komen. Zonder die markering staat er geen formulier op de pagina.'];
    }
    if (!preg_match('/<html[^>]+lang\s*=/i', $html)) {
        $b[] = ['niveau' => 'info', 'titel' => 'Taal niet vastgelegd', 'detail' => 'Zet lang="nl" op de html-tag, zodat voorleessoftware de juiste uitspraak gebruikt.'];
    }
    if (!preg_match('/<title[^>]*>\s*\S/i', $html)) {
        $b[] = ['niveau' => 'info', 'titel' => 'Geen paginatitel', 'detail' => 'Zonder <title> ziet een bezoeker geen naam in het tabblad en in zoekresultaten.'];
    }
    if (!preg_match('/<h1[\s>]/i', $html)) {
        $b[] = ['niveau' => 'info', 'titel' => 'Geen hoofdkop (h1)', 'detail' => 'Eén duidelijke h1 helpt bezoekers die met voorleessoftware navigeren.'];
    }
    $zonder_alt = 0;
    if (preg_match_all('/<img\b[^>]*>/i', $html, $m)) {
        foreach ($m[0] as $tag) { if (!preg_match('/\balt\s*=/i', $tag)) $zonder_alt++; }
    }
    if ($zonder_alt > 0) {
        $b[] = ['niveau' => 'info', 'titel' => $zonder_alt . ' afbeelding(en) zonder alt-tekst', 'detail' => 'Voeg alt="" toe bij sierafbeeldingen en een korte omschrijving bij inhoudelijke afbeeldingen.'];
    }

    $telling = ['hoog' => 0, 'midden' => 0, 'laag' => 0, 'info' => 0];
    foreach ($b as $bev) { $telling[$bev['niveau']] = ($telling[$bev['niveau']] ?? 0) + 1; }

    // Meest urgent bovenaan
    $rang = ['hoog' => 0, 'midden' => 1, 'laag' => 2, 'info' => 3];
    usort($b, fn($x, $y) => ($rang[$x['niveau']] ?? 9) <=> ($rang[$y['niveau']] ?? 9));

    return ['bevindingen' => $b, 'hosts' => array_values(array_unique($hosts)), 'telling' => $telling];
}

/** Woord bij een niveau (nooit alleen kleur gebruiken). */
function pag_scan_niveau_label(string $niveau): string {
    return ['hoog' => 'Let op', 'midden' => 'Aandacht', 'laag' => 'Klein punt', 'info' => 'Tip'][$niveau] ?? $niveau;
}

/**
 * Upload verwerken: valideren, scannen en als nieuwe (nog inactieve) versie wegschrijven.
 * Retour: ['ok'=>bool, 'versie_id'=>int, 'scan'=>array, 'fout'=>string]
 */
function pag_import_toepassen(array $pagina, array $upload): array {
    $v = pag_upload_valideer($upload);
    if (!$v['ok']) return ['ok' => false, 'versie_id' => 0, 'scan' => [], 'fout' => $v['fout']];

    $scan = pag_scan_html($v['html']);
    $res = pag_versie_opslaan($pagina, $v['html'], ['bron' => 'upload', 'bestandsnaam' => $v['naam'], 'scan' => $scan]);
    if (!$res['ok']) return ['ok' => false, 'versie_id' => 0, 'scan' => $scan, 'fout' => $res['fout']];

    return ['ok' => true, 'versie_id' => $res['versie_id'], 'scan' => $scan, 'fout' => ''];
}

/** Scanrapport bevestigen en de versie in één keer live zetten. */
function pag_scan_bevestig(int $pagina_id, int $versie_id): bool {
    $v = pag_versie_rij($versie_id);
    if (!$v || (int)$v['pagina_id'] !== $pagina_id) return false;
    try {
        $bhd = function_exists('huidige_beheerder') ? (int)(huidige_beheerder()['id'] ?? 0) : 0;
        db()->prepare("UPDATE pagina_versies SET scan_bevestigd_op=NOW(), scan_bevestigd_door=? WHERE id=?")
            ->execute([$bhd ?: null, $versie_id]);
        $scan = json_decode((string)$v['scan_json'], true) ?: [];
        if (function_exists('audit_log')) {
            audit_log('scan_bevestigd', 'pagina', $pagina_id, [
                'versie' => (int)$v['versie'],
                'hoog'   => (int)($scan['telling']['hoog'] ?? 0),
                'midden' => (int)($scan['telling']['midden'] ?? 0),
            ]);
        }
        return pag_versie_activeer($pagina_id, $versie_id);
    } catch (\Throwable $e) { error_log('pag_scan_bevestig: ' . $e->getMessage()); return false; }
}

/** Het scanrapport van een versie uitlezen. */
function pag_scan_van_versie(?array $versie): array {
    if (!$versie || empty($versie['scan_json'])) return ['bevindingen' => [], 'hosts' => [], 'telling' => []];
    $s = json_decode((string)$versie['scan_json'], true);
    return is_array($s) ? $s + ['bevindingen' => [], 'hosts' => [], 'telling' => []] : ['bevindingen' => [], 'hosts' => [], 'telling' => []];
}

}

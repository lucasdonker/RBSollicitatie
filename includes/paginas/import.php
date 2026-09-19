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
    $max = pag_max_html_bytes(); // houdt al rekening met het serverplafond
    if ($grootte <= 0)   { $mis['fout'] = 'Het bestand is leeg.'; return $mis; }
    if ($grootte > $max) { $mis['fout'] = 'Het bestand is ' . pag_bytes_tekst($grootte) . '; het maximum is ' . pag_bytes_tekst($max) . '.'; return $mis; }

    // Het nalezen kost ongeveer 4x de bestandsgrootte aan werkgeheugen. Past dat niet,
    // dan valt PHP halverwege stil met een witte pagina; liever nu een duidelijke melding.
    if (!pag_geheugen_genoeg($grootte)) {
        $mis['fout'] = 'Het bestand is ' . pag_bytes_tekst($grootte) . ' en dat past niet in het werkgeheugen van de server (memory_limit staat op '
                     . (string)ini_get('memory_limit') . '). Zet memory_limit in Plesk op minstens ' . pag_bytes_tekst($grootte * 4 + 33554432)
                     . ', of maak het ontwerp kleiner door de ingesloten afbeeldingen los te uploaden.';
        return $mis;
    }

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

/**
 * Zoeken in een ontwerp, met vangnet.
 *
 * PCRE stopt bij een te grote of te ingewikkelde tekst na pcre.backtrack_limit stappen
 * (standaard 1 miljoen) en geeft dan false terug — geen exception, geen waarschuwing.
 * Zonder deze wikkel zou zo'n bevinding stil uit het rapport verdwijnen en zou de
 * beheerder een onvolledig rapport voor een schoon ontwerp aanzien. $onleesbaar wordt
 * dan gezet, zodat het rapport dat eerlijk meldt.
 */
function pag_zoek_alles(string $patroon, string $html, ?array &$treffers, int $vlaggen, bool &$onleesbaar): int {
    $n = @preg_match_all($patroon, $html, $treffers, $vlaggen);
    if ($n === false) { $treffers = []; $onleesbaar = true; return 0; }
    return (int)$n;
}

/** Idem, voor een enkele controle: staat dit erin, ja of nee? */
function pag_zoek(string $patroon, string $html, bool &$onleesbaar): bool {
    $n = @preg_match($patroon, $html);
    if ($n === false) { $onleesbaar = true; return false; }
    return $n === 1;
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
 * Let op bij het uitbreiden: PCRE geeft bij een groot bestand géén foutmelding maar false
 * (pcre.backtrack_limit, standaard 1 miljoen stappen). Een bevinding zou dan stil uit het
 * rapport verdwijnen en de beheerder zou een onveilig ontwerp als schoon bevestigen. Zoek
 * daarom altijd via pag_zoek_alles()/pag_zoek() hieronder, en vermijd een lui `.*?` met /s
 * over de hele tekst — gebruik een vorm die niet terugkrabbelt, zoals (?:[^<]++|<(?!\/x))*+.
 *
 * Retour: ['bevindingen'=>[['niveau','titel','detail']], 'hosts'=>[...], 'telling'=>[niveau=>n]]
 */
function pag_scan_html(string $html): array {
    $b = [];
    $hosts = [];
    $onleesbaar = false;   // is een van de zoekopdrachten vastgelopen?

    $extern = function (string $url): ?string {
        if (!preg_match('~^(?:https?:)?//([^/"\'\s]+)~i', trim($url), $m)) return null;
        $host = strtolower($m[1]);
        $eigen = defined('APP_URL') ? parse_url(APP_URL, PHP_URL_HOST) : '';
        return ($eigen && strcasecmp($host, (string)$eigen) === 0) ? null : $host;
    };

    // ── Hoog: externe scripts, frames en formulieren ──
    if (pag_zoek_alles('/<script[^>]+src\s*=\s*["\']?([^"\'>\s]+)/i', $html, $m, 0, $onleesbaar)) {
        foreach ($m[1] as $url) {
            $host = $extern($url);
            if ($host === null) continue;
            $hosts[] = $host;
            $b[] = ['niveau' => 'hoog', 'titel' => 'Extern script', 'detail' => $host . ' — deze code draait mee op de pagina en kan door de eigenaar van die server gewijzigd worden.'];
        }
    }
    if (pag_zoek_alles('/<(iframe|embed|object)[^>]+(?:src|data)\s*=\s*["\']?([^"\'>\s]+)/i', $html, $m, PREG_SET_ORDER, $onleesbaar)) {
        foreach ($m as $set) {
            $host = $extern($set[2]);
            if ($host === null) continue;
            $hosts[] = $host;
            $b[] = ['niveau' => 'hoog', 'titel' => 'Externe inhoud (' . strtolower($set[1]) . ')', 'detail' => $host];
        }
    }
    if (pag_zoek_alles('/<form[^>]+action\s*=\s*["\']?([^"\'>\s]+)/i', $html, $m, 0, $onleesbaar)) {
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
    // De inhoud wordt gepakt met (?:[^<]++|<(?!\/script))*+ in plaats van een lui `.*?`:
    // dat laatste liep bij een script van meer dan ~1 MB vast op de backtrack-limiet,
    // waarna deze bevinding stil uit het rapport verdween.
    if (pag_zoek_alles('/<script(?![^>]*\bsrc\b)[^>]*>((?:[^<]++|<(?!\/script))*+)<\/script>/i', $html, $m, 0, $onleesbaar)) {
        $bytes = array_sum(array_map('strlen', $m[1]));
        if ($bytes > 0) {
            $b[] = ['niveau' => 'midden', 'titel' => 'Scripts in het bestand zelf', 'detail' => count($m[1]) . ' stuk(s), samen ' . round($bytes / 1024, 1) . ' KB. Deze code draait op de pagina; hij is niet gecontroleerd.'];
        }
    }
    if (pag_zoek_alles('/<link[^>]+rel\s*=\s*["\']?stylesheet["\']?[^>]*href\s*=\s*["\']?([^"\'>\s]+)|@import\s+(?:url\()?["\']([^"\')]+)/i', $html, $m, PREG_SET_ORDER, $onleesbaar)) {
        foreach ($m as $set) {
            $host = $extern($set[1] ?: ($set[2] ?? ''));
            if ($host === null) continue;
            $hosts[] = $host;
            $niveau = preg_match('/fonts\.(googleapis|gstatic)\.com/i', $host) ? 'midden' : 'midden';
            $extra  = preg_match('/fonts\.(googleapis|gstatic)\.com/i', $host) ? ' — Google Fonts stuurt het IP-adres van de bezoeker naar de VS. Zet het lettertype liever in het bestand zelf.' : '';
            $b[] = ['niveau' => $niveau, 'titel' => 'Externe stijl of lettertype', 'detail' => $host . $extra];
        }
    }
    if (pag_zoek('/\bfetch\s*\(|XMLHttpRequest|sendBeacon|new\s+WebSocket/i', $html, $onleesbaar)) {
        $b[] = ['niveau' => 'midden', 'titel' => 'De pagina verstuurt gegevens', 'detail' => 'Er staat code in die zelf netwerkverkeer opzet (fetch/XHR/WebSocket). Dit wordt geblokkeerd tenzij je de host hieronder toestaat.'];
    }
    if (pag_zoek('/localStorage|sessionStorage|document\.cookie/i', $html, $onleesbaar)) {
        $b[] = ['niveau' => 'midden', 'titel' => 'De pagina slaat iets op bij de bezoeker', 'detail' => 'Cookies of browseropslag. Voor niet-noodzakelijke opslag is toestemming vooraf nodig.'];
    }

    // ── Laag ──
    if (pag_zoek_alles('/<img[^>]+src\s*=\s*["\']?([^"\'>\s]+)/i', $html, $m, 0, $onleesbaar)) {
        $ext_img = [];
        foreach ($m[1] as $url) { $host = $extern($url); if ($host !== null) $ext_img[$host] = true; }
        foreach (array_keys($ext_img) as $host) {
            $hosts[] = $host;
            $b[] = ['niveau' => 'laag', 'titel' => 'Afbeelding van een andere website', 'detail' => $host . ' — upload hem liever bij de pagina, dan blijft hij werken.'];
        }
    }
    if (pag_zoek_alles('/\son[a-z]+\s*=\s*["\']/i', $html, $m, 0, $onleesbaar)) {
        $b[] = ['niveau' => 'laag', 'titel' => 'Klik- en muisacties in de opmaak', 'detail' => count($m[0]) . ' stuk(s) (onclick en dergelijke).'];
    }
    if (stripos($html, 'javascript:') !== false) {
        $b[] = ['niveau' => 'laag', 'titel' => 'Link met javascript:', 'detail' => 'Zulke links werken niet met toetsenbordbediening en worden door de beveiliging geblokkeerd.'];
    }

    // ── Informatief: opbouw en toegankelijkheid ──
    if (!str_contains($html, '[[formulier]]')) {
        $b[] = ['niveau' => 'info', 'titel' => 'Geen aanmeldformulier op de pagina', 'detail' => 'Zet [[formulier]] in het ontwerp op de plek waar het formulier moet komen. Zonder die markering staat er geen formulier op de pagina.'];
    }
    if (!pag_zoek('/<html[^>]+lang\s*=/i', $html, $onleesbaar)) {
        $b[] = ['niveau' => 'info', 'titel' => 'Taal niet vastgelegd', 'detail' => 'Zet lang="nl" op de html-tag, zodat voorleessoftware de juiste uitspraak gebruikt.'];
    }
    if (!pag_zoek('/<title[^>]*>\s*\S/i', $html, $onleesbaar)) {
        $b[] = ['niveau' => 'info', 'titel' => 'Geen paginatitel', 'detail' => 'Zonder <title> ziet een bezoeker geen naam in het tabblad en in zoekresultaten.'];
    }
    if (!pag_zoek('/<h1[\s>]/i', $html, $onleesbaar)) {
        $b[] = ['niveau' => 'info', 'titel' => 'Geen hoofdkop (h1)', 'detail' => 'Eén duidelijke h1 helpt bezoekers die met voorleessoftware navigeren.'];
    }
    $zonder_alt = 0;
    if (pag_zoek_alles('/<img\b[^>]*>/i', $html, $m, 0, $onleesbaar)) {
        foreach ($m[0] as $tag) { if (!preg_match('/\balt\s*=/i', $tag)) $zonder_alt++; }
    }
    if ($zonder_alt > 0) {
        $b[] = ['niveau' => 'info', 'titel' => $zonder_alt . ' afbeelding(en) zonder alt-tekst', 'detail' => 'Voeg alt="" toe bij sierafbeeldingen en een korte omschrijving bij inhoudelijke afbeeldingen.'];
    }

    // Is er onderweg iets vastgelopen, dan is het rapport niet compleet. Dat moet de
    // beheerder weten vóór hij bevestigt — stilte zou hier voor "schoon" doorgaan.
    if ($onleesbaar) {
        $b[] = ['niveau' => 'hoog', 'titel' => 'Het ontwerp kon niet helemaal worden nagelezen',
                'detail' => 'Een deel van het bestand was te groot of te ingewikkeld om te doorzoeken, waardoor dit rapport onvolledig is. Maak het ontwerp kleiner (haal ingesloten afbeeldingen eruit en upload ze los) of laat iemand het bestand nakijken voordat je het live zet.'];
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
 * Ingesloten afbeeldingen losmaken.
 *
 * Een ontwerp uit een ontwerptool heeft zijn foto's vaak als `data:image/...;base64,…`
 * in de HTML staan. Dat werkt wel (de CSP staat `img-src data:` toe), maar het maakt het
 * bestand tien keer zo groot en de bezoeker moet alles binnenhalen vóór hij iets ziet.
 * Hier halen we elke data-URI eruit, zetten hem in de assets-map en laten alleen de
 * bestandsnaam staan. Dat werkt overal waar zo'n URI kan staan — img src, srcset,
 * background-image in CSS — omdat we de URI zelf vervangen en niet een heel tag.
 *
 * Dit is de enige plek waar een geüpload ontwerp wél wordt aangepast; daarom staat het
 * achter een keuze van de beheerder en meldt het rapport precies wat er is gebeurd.
 * Lukt iets niet (onbekend type, te groot, schrijffout), dan blijft die ene data-URI
 * gewoon staan: het ontwerp blijft altijd werken.
 *
 * Retour: ['html'=>string, 'bestanden'=>[naam,…], 'overgeslagen'=>int,
 *          'bytes_voor'=>int, 'bytes_na'=>int]
 */
function pag_ontvlecht_afbeeldingen(string $html, string $slug, int $max_aantal = 100): array {
    $uit = ['html' => $html, 'bestanden' => [], 'overgeslagen' => 0,
            'bytes_voor' => strlen($html), 'bytes_na' => strlen($html)];

    $gezien = [];   // sha256 => bestandsnaam, zodat dezelfde foto niet tien keer op schijf komt
    $bestanden = [];
    $over = 0;

    // Possessief (++) zodat PCRE niet terugkrabbelt: een base64-blok is zo een paar MB.
    // Witruimte hoort bij de match omdat base64 over meerdere regels verdeeld kan zijn.
    $nieuw = @preg_replace_callback(
        '~data:image/[a-z0-9.+-]{1,20};base64,([A-Za-z0-9+/=\s]++)~i',
        function (array $m) use (&$gezien, &$bestanden, &$over, $slug, $max_aantal): string {
            // Eerste lezing: alle witruimte eruit (base64 verdeeld over meerdere regels).
            $staart = '';
            $ruw = base64_decode((string)preg_replace('/\s+/', '', $m[1]), true);

            // Mislukt dat, dan zit er waarschijnlijk een srcset-descriptor aan vast:
            // `…base64,XXXX 1x, …`. Alleen het deel vóór de eerste witruimte is dan de
            // afbeelding; de rest geven we onveranderd terug.
            if ($ruw === false && preg_match('~^([A-Za-z0-9+/=]++)(\s[\s\S]*)$~', $m[1], $mm)) {
                $ruw = base64_decode($mm[1], true);
                $staart = $mm[2];
            }
            if ($ruw === false || $ruw === '') { $over++; return $m[0]; }

            $sleutel = hash('sha256', $ruw);
            if (isset($gezien[$sleutel])) return $gezien[$sleutel] . $staart;   // zelfde foto, zelfde bestand
            if (count($bestanden) >= $max_aantal) { $over++; return $m[0]; }

            $res = pag_asset_uit_bytes($slug, $ruw, 'ontwerp-' . (count($bestanden) + 1));
            if (!$res['ok']) { $over++; return $m[0]; }

            $gezien[$sleutel] = $res['naam'];
            $bestanden[] = $res['naam'];
            return $res['naam'] . $staart;
        },
        $html
    );

    // Loopt de vervanging vast, dan houden we het ontwerp zoals het was.
    if ($nieuw === null) { $uit['overgeslagen'] = 1; return $uit; }

    $uit['html'] = $nieuw;
    $uit['bytes_na'] = strlen($nieuw);
    $uit['bestanden'] = $bestanden;
    $uit['overgeslagen'] = $over;
    return $uit;
}

/**
 * Upload verwerken: valideren, eventueel ingesloten afbeeldingen losmaken, scannen en
 * als nieuwe (nog inactieve) versie wegschrijven.
 * $opties: ['ontvlecht' => bool]
 * Retour: ['ok'=>bool, 'versie_id'=>int, 'scan'=>array, 'ontvlecht'=>?array, 'fout'=>string]
 */
function pag_import_toepassen(array $pagina, array $upload, array $opties = []): array {
    $v = pag_upload_valideer($upload);
    if (!$v['ok']) return ['ok' => false, 'versie_id' => 0, 'scan' => [], 'ontvlecht' => null, 'fout' => $v['fout']];

    $html = $v['html'];
    $ontvlecht = null;
    if (!empty($opties['ontvlecht'])) {
        $ontvlecht = pag_ontvlecht_afbeeldingen($html, (string)$pagina['slug']);
        $html = $ontvlecht['html'];
    }

    $scan = pag_scan_html($html);
    if ($ontvlecht && $ontvlecht['bestanden']) {
        array_unshift($scan['bevindingen'], [
            'niveau' => 'info',
            'titel'  => count($ontvlecht['bestanden']) . ' ingesloten afbeelding(en) losgemaakt',
            'detail' => 'Het ontwerp ging van ' . pag_bytes_tekst($ontvlecht['bytes_voor']) . ' naar '
                      . pag_bytes_tekst($ontvlecht['bytes_na']) . '. De afbeeldingen staan nu los bij de pagina ('
                      . implode(', ', array_slice($ontvlecht['bestanden'], 0, 10))
                      . (count($ontvlecht['bestanden']) > 10 ? ', …' : '') . ') en worden door de browser apart bewaard.'
                      . ($ontvlecht['overgeslagen'] > 0 ? ' ' . $ontvlecht['overgeslagen'] . ' afbeelding(en) bleven in het bestand staan, bijvoorbeeld omdat het geen jpg, png, gif of webp was.' : ''),
        ]);
        $scan['telling']['info'] = ($scan['telling']['info'] ?? 0) + 1;
    }

    $res = pag_versie_opslaan($pagina, $html, ['bron' => 'upload', 'bestandsnaam' => $v['naam'], 'scan' => $scan]);
    if (!$res['ok']) return ['ok' => false, 'versie_id' => 0, 'scan' => $scan, 'ontvlecht' => $ontvlecht, 'fout' => $res['fout']];

    return ['ok' => true, 'versie_id' => $res['versie_id'], 'scan' => $scan, 'ontvlecht' => $ontvlecht, 'fout' => ''];
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

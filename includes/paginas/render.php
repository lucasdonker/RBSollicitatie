<?php
/**
 * Pagina's — publieke weergave.
 *
 * Het geüploade ontwerp gaat ongewijzigd de deur uit: we schonen niets op, zodat animaties
 * en interactie blijven werken. De begrenzing zit in de Content-Security-Policy: externe
 * bronnen zijn standaard geblokkeerd en worden per pagina bewust toegestaan.
 *
 * Wij voegen maar twee dingen toe aan het ontwerp:
 *  - in de <head>: een <base> zodat relatieve verwijzingen naar afbeeldingen kloppen,
 *    de opmaak van het formulier en (als de pagina nog niet openbaar is) een robots-regel
 *  - in de <body>: het formulier op de plek van [[formulier]], en een voorbeeldbalk voor
 *    beheerders bij een pagina die nog niet gepubliceerd is
 */

// Wervingsmarkeringen, vacature en campagnes (werving.php) horen bij de weergave; hier geladen
// zodat de moduleloader (includes/paginas.php) niet hoeft te veranderen.
require_once __DIR__ . '/werving.php';

if (!function_exists('pag_render')) {

function pag_publieke_url(array $pagina): string {
    return (defined('APP_URL') ? APP_URL : '') . '/p/' . $pagina['slug'];
}

/**
 * Mag Google deze pagina indexeren? Alleen gepubliceerd, met het vinkje "Mag gevonden worden via
 * Google", en nooit op de testomgeving (anders concurreert test met productie in de zoekresultaten).
 */
function pag_indexeerbaar(array $pagina): bool {
    return ($pagina['status'] ?? '') === 'gepubliceerd' && !empty($pagina['indexeerbaar'])
        && !(function_exists('is_test_omgeving') && is_test_omgeving());
}

/**
 * De Content-Security-Policy voor één pagina.
 *
 * 'unsafe-inline' is onvermijdelijk: een geüpload ontwerp heeft zijn opmaak en scripts in
 * het bestand zelf staan, en we schonen bewust niets op.
 *
 * connect-src is de belangrijkste regel en géén detail: die voorkomt dat script uit een
 * ontwerp op de achtergrond verzoeken doet naar /admin/ of /portaal/ met de cookie van een
 * beheerder die de pagina toevallig bekijkt.
 *
 * Er staat precies één adres van onszelf in: /track.php, waar het aanmeldformulier zijn
 * anonieme stap-tellingen naartoe stuurt. Let op het verschil met 'self': dat zou de héle
 * site openzetten, terwijl dit één pad is dat niets anders doet dan een teller ophogen.
 * Zet hier dus nooit 'self' neer — dan is het slot weg.
 */
function pag_csp_regel(array $pagina): string {
    $hosts = [];
    foreach (preg_split('/\s+/', (string)($pagina['csp_extra'] ?? '')) as $host) {
        $host = trim($host);
        if ($host !== '' && preg_match('/^[a-z0-9]([a-z0-9.\-]*[a-z0-9])?$/', $host)) $hosts[] = 'https://' . $host;
    }
    $extra = $hosts ? ' ' . implode(' ', array_unique($hosts)) : '';

    // Alleen dit ene pad, en alleen als tracking überhaupt aanstaat.
    $track = function_exists('aanmeld_track_url') ? aanmeld_track_url() : '';
    $connect = trim(($track !== '' ? $track : '') . $extra);
    if ($connect === '') $connect = "'none'";

    return implode('; ', [
        "default-src 'none'",
        "img-src 'self' data:" . $extra,
        "style-src 'self' 'unsafe-inline'" . $extra,
        "script-src 'self' 'unsafe-inline'" . $extra,
        "font-src 'self' data:" . $extra,
        "connect-src " . $connect,
        "frame-src " . ($hosts ? trim($extra) : "'none'"),
        "form-action 'self'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
    ]);
}

/** Alle beveiligings- en cache-headers voor een publieke pagina. */
function pag_headers_zet(array $pagina, array $opties = []): void {
    if (headers_sent()) return;
    $gepubliceerd = ($pagina['status'] ?? '') === 'gepubliceerd';
    $vers = !empty($opties['geen_cache']) || !$gepubliceerd;

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Security-Policy: ' . pag_csp_regel($pagina));
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), interest-cohort=()');

    if (!pag_indexeerbaar($pagina)) {
        header('X-Robots-Tag: noindex, nofollow');
    }
    header($vers ? 'Cache-Control: no-store, must-revalidate' : 'Cache-Control: public, max-age=300');
}

/** Cache-sleutel op basis van de wijzigingsdatum van een bestand. */
function pag_asset_versie(string $relatief): string {
    $pad = dirname(__DIR__, 2) . '/' . ltrim($relatief, '/');
    $t = @filemtime($pad);
    return $t ? (string)$t : '1';
}

/**
 * Markeringen in het ontwerp vervangen.
 * Onbekende markeringen laten we staan: dan ziet de beheerder zijn typefout op de pagina,
 * in plaats van dat er stilletjes niets gebeurt.
 *
 * Seizoensgegevens komen uit seizoen_voor_werving() (niet actief_seizoen(), zie werving.php);
 * de datum-, loon- en vacaturemarkeringen staan in pag_werving_markeringen().
 * $versie: de getoonde versie (voor datePosted in de vacature).
 */
function pag_placeholders(string $html, array $pagina, string $formulier_html, ?array $versie = null): string {
    $seizoen = function_exists('seizoen_voor_werving') ? seizoen_voor_werving() : null;
    $bron = (string)($pagina['bron_code'] ?: $pagina['slug']);

    return strtr($html, pag_werving_markeringen($html, $pagina, $versie) + [
        '[[formulier]]'   => $formulier_html,
        '[[seizoen]]'     => h((string)($seizoen['naam'] ?? '')),
        '[[aanmeld_url]]' => h(pag_publieke_url($pagina) . '#aanmelden'),
        '[[bron]]'        => h($bron),
        // Het volledige adres van de pagina, voor og:url, og:image en structured data. Relatieve
        // adressen werken daar niet (en <base> geldt niet voor crawlers van sociale media).
        '[[pagina_url]]'  => h(pag_publieke_url($pagina)),
    ]);
}

/** Onze eigen toevoegingen in de <head>. Een ontwerp zonder <head> krijgt een wrapper (zie pag_render). */
function pag_injecteer_head(string $html, array $pagina, bool $met_formulier): string {
    $basis = (defined('APP_URL') ? APP_URL : '') . '/p/' . $pagina['slug'] . '/';
    $blok  = "\n<base href=\"" . h($basis) . "\">\n";

    if (!pag_indexeerbaar($pagina)) {
        $blok .= "<meta name=\"robots\" content=\"noindex,nofollow\">\n";
    } elseif (!preg_match('/<link\b[^>]*rel=["\']?canonical/i', $html)) {
        // Eén adres per pagina, ook als er ?bron=… of ?ok=1 achter staat (anders dubbele inhoud).
        $blok .= '<link rel="canonical" href="' . h(pag_publieke_url($pagina)) . "\">\n";
    }
    if ($met_formulier) {
        $css = (defined('APP_URL') ? APP_URL : '') . '/assets/css/aanmeldformulier.css?v=' . pag_asset_versie('assets/css/aanmeldformulier.css');
        $blok .= '<link rel="stylesheet" href="' . h($css) . "\">\n";
    }

    if (preg_match('/<head\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($html, 0, $pos) . $blok . substr($html, $pos);
    }
    // Geen <head>: vóór de eerste tag in het document zetten is het beste wat we kunnen doen.
    return $blok . $html;
}

/** Het script voor het aanmeldformulier, vlak voor </body>. */
function pag_injecteer_script(string $html): string {
    $src = (defined('APP_URL') ? APP_URL : '') . '/assets/js/aanmeldformulier.js?v=' . pag_asset_versie('assets/js/aanmeldformulier.js');
    $tag = '<script src="' . h($src) . "\" defer></script>\n";
    $pos = stripos($html, '</body>');
    return $pos === false ? $html . $tag : substr($html, 0, $pos) . $tag . substr($html, $pos);
}

/** Balk boven een pagina die nog niet openbaar is. Alleen zichtbaar voor beheerders. */
function pag_voorbeeldbalk(array $pagina, ?array $versie = null): string {
    $statussen = pag_statussen();
    $wat = $versie && $versie['scan_bevestigd_op'] === null
        ? 'Voorbeeld van versie ' . (int)$versie['versie'] . ' — nog niet bevestigd en niet live.'
        : 'Voorbeeld — deze pagina staat op "' . ($statussen[$pagina['status']] ?? $pagina['status']) . '" en is niet openbaar.';

    return '<div role="status" style="position:sticky;top:0;z-index:99999;background:#054d8a;color:#fff;'
         . 'padding:9px 16px;font:14px/1.4 system-ui,sans-serif;text-align:center">'
         . h($wat) . ' Bezoeken tellen niet mee in de statistiek.</div>';
}

/**
 * De complete pagina opbouwen.
 * $opties: ['versie'=>?array, 'is_beheer'=>bool, 'formulier'=>string]
 */
function pag_render(array $pagina, string $ontwerp, array $opties = []): string {
    $formulier = (string)($opties['formulier'] ?? '');
    $met_formulier = $formulier !== '' && str_contains($ontwerp, '[[formulier]]');

    $html = pag_placeholders($ontwerp, $pagina, $formulier, $opties['versie'] ?? null);

    // Een fragment zonder eigen documentstructuur krijgt een minimale, geldige wrapper.
    if (!preg_match('/<html\b/i', $html)) {
        $html = "<!DOCTYPE html>\n<html lang=\"nl\">\n<head>\n<meta charset=\"utf-8\">\n"
              . "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n"
              . '<title>' . h((string)$pagina['titel']) . "</title>\n</head>\n<body>\n" . $html . "\n</body>\n</html>";
    }

    $html = pag_injecteer_head($html, $pagina, $met_formulier);
    if ($met_formulier) $html = pag_injecteer_script($html);

    if (!empty($opties['is_beheer']) && ($pagina['status'] ?? '') !== 'gepubliceerd') {
        $balk = pag_voorbeeldbalk($pagina, $opties['versie'] ?? null);
        $pos = stripos($html, '<body');
        if ($pos !== false) {
            $eind = strpos($html, '>', $pos);
            if ($eind !== false) $html = substr($html, 0, $eind + 1) . "\n" . $balk . substr($html, $eind + 1);
        } else {
            $html = $balk . $html;
        }
    }
    return $html;
}

}

<?php
/**
 * Pagina's — verwijzingen naar afbeeldingen rechtzetten.
 *
 * Een ontwerp uit een ontwerptool verwijst naar zijn eigen mappenstructuur:
 * `<img src="assets/Frame 12.png">`, `url(images/duinen.jpg)`. Bij ons staan de
 * afbeeldingen van een pagina plat in één map (uploads/paginas/<slug>/assets/) en krijgen
 * ze bij het uploaden een veilige naam (`frame-12.png`). Zonder deze stap wijst het
 * ontwerp dus naar een adres dat niet bestaat en blijven de foto's leeg.
 *
 * Wat hier gebeurt: elke relatieve verwijzing naar een jpg/png/gif/webp wordt herleid tot
 * de bestandsnaam zoals die bij ons op schijf staat. Staat het bestand er al, dan nemen we
 * die naam letterlijk over (ook als de extensie bij het uploaden is gecorrigeerd, zie
 * upload_valideer). Staat het er nog niet, dan zetten we de naam die het bestand kríjgt
 * zodra hij geüpload wordt — en meldt het rapport welke afbeeldingen nog ontbreken.
 *
 * Alles wat geen relatieve afbeelding is, blijft onaangeroerd: externe URL's, data-URI's,
 * ankers, markeringen als [[formulier]] en gewone links.
 */

if (!function_exists('pag_asset_sleutel')) {

/** Extensies die we als afbeelding herkennen; gelijk aan wat pag_asset_typen() toestaat. */
function pag_verwijzing_extensies(): array { return ['jpg', 'jpeg', 'png', 'gif', 'webp']; }

/**
 * De sleutel waarmee een verwijzing en een bestand elkaar vinden: de naam zonder extensie,
 * door dezelfde molen als bij het uploaden. "assets/Frame 12.png" en "frame-12.jpg" komen
 * zo allebei op `frame-12` uit.
 */
function pag_asset_sleutel(string $naam): string {
    return pag_slug_maak(pathinfo($naam, PATHINFO_FILENAME));
}

/** Wat er werkelijk in de assets-map staat: sleutel => bestandsnaam. */
function pag_asset_index(string $slug): array {
    $uit = [];
    foreach (pag_asset_lijst($slug) as $a) {
        $sleutel = pag_asset_sleutel((string)$a['naam']);
        if ($sleutel !== '' && !isset($uit[$sleutel])) $uit[$sleutel] = (string)$a['naam'];
    }
    return $uit;
}

/**
 * Hoort deze URL uit een ontwerp bij een afbeelding die bij ons hoort te staan?
 * Retour: ['sleutel'=>string, 'ext'=>string] of null (met rust laten).
 */
function pag_verwijzing_ontleed(string $url): ?array {
    $u = trim($url);
    if ($u === '' || $u[0] === '#') return null;
    if (str_starts_with($u, '[[')) return null;                       // onze eigen markeringen
    if (str_starts_with($u, '//')) return null;                       // //cdn.example.com/…
    if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $u)) return null;       // http:, data:, mailto:

    $delen = explode('?', $u);   $pad = $delen[0];
    $delen = explode('#', $pad); $pad = $delen[0];
    $pad = rawurldecode(str_replace('\\', '/', $pad));
    $bestand = basename($pad);
    if ($bestand === '' || $bestand === '.' || $bestand === '..') return null;

    $ext = strtolower(pathinfo($bestand, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') $ext = 'jpg';
    if (!in_array($ext, pag_verwijzing_extensies(), true)) return null;

    $sleutel = pag_asset_sleutel($bestand);
    if ($sleutel === '') return null;
    return ['sleutel' => $sleutel, 'ext' => $ext];
}

/**
 * Eén URL vertalen naar de bestandsnaam bij deze pagina.
 * Retour: de nieuwe naam, of null als er niets te doen valt.
 */
function pag_verwijzing_vertaal(string $url, array $index, array &$ontbreekt): ?string {
    $d = pag_verwijzing_ontleed($url);
    if ($d === null) return null;

    $naam = $index[$d['sleutel']] ?? ($d['sleutel'] . '.' . $d['ext']);
    if (!isset($index[$d['sleutel']]) && !in_array($naam, $ontbreekt, true)) $ontbreekt[] = $naam;
    return $naam === $url ? null : $naam;
}

/**
 * Alle verwijzingen in het ontwerp rechtzetten.
 * Retour: ['html'=>string, 'aangepast'=>[oud => nieuw], 'ontbreekt'=>[naam,…]]
 */
function pag_verwijzingen_herschrijf(string $html, string $slug): array {
    $uit = ['html' => $html, 'aangepast' => [], 'ontbreekt' => []];
    $index = pag_asset_index($slug);
    $aangepast = [];
    $ontbreekt = [];

    $vertaal = function (string $url) use ($index, &$aangepast, &$ontbreekt): ?string {
        $nieuw = pag_verwijzing_vertaal($url, $index, $ontbreekt);
        if ($nieuw !== null) $aangepast[trim($url)] = $nieuw;
        return $nieuw;
    };

    // 1. src / href / poster / data-src — met aanhalingstekens, want zo levert elke
    //    ontwerptool het aan. href alleen als het naar een afbeelding wijst (favicon).
    $stap = @preg_replace_callback(
        '~\b(src|href|poster|data-src)(\s*=\s*)(["\'])([^"\']*)\3~i',
        function (array $m) use ($vertaal): string {
            $nieuw = $vertaal($m[4]);
            return $nieuw === null ? $m[0] : $m[1] . $m[2] . $m[3] . $nieuw . $m[3];
        },
        $uit['html']
    );
    if ($stap !== null) $uit['html'] = $stap;

    // 2. srcset: een lijst van "adres descriptor", gescheiden door komma's.
    $stap = @preg_replace_callback(
        '~\bsrcset(\s*=\s*)(["\'])([^"\']*)\2~i',
        function (array $m) use ($vertaal): string {
            $items = [];
            foreach (explode(',', $m[3]) as $item) {
                $stukken = preg_split('/\s+/', trim($item), 2);
                $adres = $stukken[0] ?? '';
                $staart = isset($stukken[1]) ? ' ' . $stukken[1] : '';
                if ($adres === '') continue;
                $nieuw = $vertaal($adres);
                $items[] = ($nieuw ?? $adres) . $staart;
            }
            return $items ? 'srcset' . $m[1] . $m[2] . implode(', ', $items) . $m[2] : $m[0];
        },
        $uit['html']
    );
    if ($stap !== null) $uit['html'] = $stap;

    // 3. url(…) in CSS: background-image en dergelijke, in <style> én in style="…".
    $stap = @preg_replace_callback(
        '~\burl\(\s*(["\']?)([^"\')]*)\1\s*\)~i',
        function (array $m) use ($vertaal): string {
            $nieuw = $vertaal($m[2]);
            return $nieuw === null ? $m[0] : 'url(' . $m[1] . $nieuw . $m[1] . ')';
        },
        $uit['html']
    );
    if ($stap !== null) $uit['html'] = $stap;

    $uit['aangepast'] = $aangepast;
    $uit['ontbreekt'] = $ontbreekt;
    return $uit;
}

}

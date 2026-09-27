<?php
/**
 * /sitemap.xml — de wervingspagina's die Google mag vinden (gepubliceerd + "Mag gevonden worden
 * via Google"), zodat ze ook zonder inkomende links worden ontdekt. Zelfde regel als de
 * robots-meta op de pagina zelf: pag_indexeerbaar(). Op de testomgeving altijd leeg.
 * Geen sessie, geen persoonsgegevens.
 */
require_once __DIR__ . '/includes/functions.php';

$urls = [];
if (function_exists('module_aan') && module_aan('paginas') && function_exists('pag_alle')) {
    foreach (pag_alle() as $p) {
        if (!pag_indexeerbaar($p) || empty($p['actieve_versie_id'])) continue;
        $urls[] = ['loc' => pag_publieke_url($p), 'lastmod' => substr((string)($p['gewijzigd_op'] ?: $p['aangemaakt_op']), 0, 10)];
    }
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>'
       . ($u['lastmod'] !== '' ? '<lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1, 'UTF-8') . '</lastmod>' : '')
       . "</url>\n";
}
echo "</urlset>\n";

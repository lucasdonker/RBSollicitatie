<?php
/**
 * /robots.txt — dynamisch, zodat het domein klopt en de testomgeving nooit wordt geïndexeerd.
 * Alleen de wervingspagina's (/p/) en het aanmeldformulier zijn bedoeld voor zoekmachines;
 * beheer, portaal, API en downloads niet. (Welke wervingspagina's echt geïndexeerd worden,
 * bepaalt het vinkje per pagina: noindex op de pagina zelf.)
 */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

if (function_exists('is_test_omgeving') && is_test_omgeving()) {
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
$basis = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
echo "User-agent: *\n"
   . "Disallow: /admin/\n"
   . "Disallow: /portaal/\n"
   . "Disallow: /api/\n"
   . "Disallow: /cron/\n"
   . "Disallow: /d/\n"
   . "Disallow: /uploads/\n"
   . "Allow: /p/\n"
   . "Allow: /aanmelden.php\n"
   . "\n"
   . "Sitemap: " . $basis . "/sitemap.xml\n";

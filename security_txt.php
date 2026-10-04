<?php
/**
 * /.well-known/security.txt (RFC 9116) — waar iemand een kwetsbaarheid kan melden.
 * NIS2 art. 21(2)(e): beleid voor het omgaan met en bekendmaken van kwetsbaarheden.
 *
 * Dynamisch zodat het domein klopt en "Expires" nooit verloopt (steeds een jaar vooruit).
 * Het adres is dat voor technische meldingen (Instellingen › Mail › Foutmeldingen sturen naar):
 * daar komen ook de foutmails, dus iemand met technische kennis leest mee.
 */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');

$basis  = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
$adres  = function_exists('instelling') ? trim((string)instelling('mail.fout_email', 'website@paal13.com')) : 'website@paal13.com';
if (!filter_var($adres, FILTER_VALIDATE_EMAIL)) $adres = 'website@paal13.com';

echo "# Kwetsbaarheid gevonden in het sollicitatiesysteem van Reddingsbrigade Paal 13?\n"
   . "# Meld het vertrouwelijk op dit adres. Test niet met echte persoonsgegevens en deel niets\n"
   . "# openbaar voordat we het hebben opgelost.\n"
   . "Contact: mailto:" . $adres . "\n"
   . "Expires: " . gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 year')) . "\n"
   . "Preferred-Languages: nl, en\n"
   . "Canonical: " . $basis . "/.well-known/security.txt\n";

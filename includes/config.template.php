<?php
/**
 * Paal 13 Sollicitatiesysteem — configuratie VOORBEELD
 * ----------------------------------------------------
 * Dit bestand staat WEL in Git, als voorbeeld. Het bevat GEEN echte wachtwoorden.
 *
 * Zo gebruik je het op een nieuwe server (bijv. de testomgeving):
 *   1. Kopieer dit bestand naar  includes/config.php
 *   2. Vul de echte waarden in voor DEZE server (test of productie)
 *   3. config.php staat in .gitignore en gaat dus NIET naar Git — dat is de bedoeling
 *
 * Test en productie hebben elk hun EIGEN config.php met een EIGEN database en URL.
 */

// ── Database (elke omgeving een eigen database!) ──
define('DB_HOST',     'localhost');
define('DB_NAME',     '{{DB_NAME}}');        // bv. ..._Sollicitaties  (prod)  of  ..._Sollicitaties_test  (test)
define('DB_USER',     '{{DB_USER}}');
define('DB_PASS',     '{{DB_PASS}}');
define('DB_CHARSET',  'utf8mb4');

// ── Webadres van DEZE omgeving ──
define('APP_URL',        '{{APP_URL}}');     // bv. https://sollicitatie.paal13.com  of  https://test.sollicitatie.paal13.com

// ── E-mail ──
define('MAIL_FROM',      'no-reply@paal13.com');
define('MAIL_FROM_NAME', 'Reddingsbrigade Paal 13');
define('MAIL_BESTUUR',   '{{MAIL_BESTUUR}}');

// ── SMTP (leeg laten = PHP mail() als terugval) ──
define('SMTP_HOST',    '{{SMTP_HOST}}');
define('SMTP_PORT',    587);
define('SMTP_USER',    '{{SMTP_USER}}');
define('SMTP_PASS',    '{{SMTP_PASS}}');
define('SMTP_ENCRYPT', 'tls');

// ── Authentik SSO (leeg laten = uitgeschakeld, wachtwoord-login werkt altijd) ──
define('OIDC_CLIENT_ID',     '{{OIDC_CLIENT_ID}}');
define('OIDC_CLIENT_SECRET', '{{OIDC_CLIENT_SECRET}}');
define('OIDC_ISSUER',        '{{OIDC_ISSUER}}');
define('OIDC_SLUG',          '{{OIDC_SLUG}}');
define('OIDC_REDIRECT_URI',  '{{APP_URL}}/admin/auth_callback.php');

// ── Kluis: versleuteling van ID-kopieën, bankpas, BSN, IBAN en verzegelde PDF's ──
// Genereer per omgeving een EIGEN sleutel:  php -r "echo base64_encode(random_bytes(32));"
// Bewaar een kopie op een veilige plek buiten de server (zonder deze sleutel zijn de documenten
// onleesbaar, ook voor ons). Verlies = dataverlies; lekken = alleen samen met de database gevaarlijk.
define('KLUIS_SLEUTEL',        '{{KLUIS_SLEUTEL}}');
define('KLUIS_SLEUTEL_VERSIE', 1);
// Sleutel wisselen (Systeem › Kluissleutel wisselen): zet de huidige sleutel + versie hieronder, de nieuwe
// sleutel hierboven met versie +1, herversleutel in het beheer tot 0 over, en haal deze twee regels dan weer weg.
// define('KLUIS_SLEUTEL_OUD',        '');   // de vorige KLUIS_SLEUTEL
// define('KLUIS_SLEUTEL_OUD_VERSIE', 1);    // de vorige KLUIS_SLEUTEL_VERSIE
// Map voor de versleutelde documenten, bij voorkeur BUITEN de webroot (Plesk: /var/www/vhosts/<domein>/private/documenten).
// Leeg laten = uploads/documenten binnen de webroot (afgeschermd met .htaccess; de health-check waarschuwt).
define('DOCUMENTEN_MAP', '');
// Documentzegel (digitale handtekening op PDF's): certificaat + sleutel worden automatisch aangemaakt in
// <DOCUMENTEN_MAP>/zegel. Optioneel een eigen map, naam en organisatie voor het certificaat:
// define('ZEGEL_MAP', '');  define('ZEGEL_NAAM', 'Reddingsbrigade Paal 13 - Documentzegel');  define('ZEGEL_ORGANISATIE', 'Reddingsbrigade Paal 13');

// ── Sessie ──
define('SESSION_NAME',     'p13sess');
// De sessie leeft vast 8 uur (sessie_start() in includes/functions.php); daar is geen constante voor.

date_default_timezone_set('Europe/Amsterdam');
error_reporting(0);
ini_set('display_errors', 0);

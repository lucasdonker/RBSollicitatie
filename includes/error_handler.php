<?php
/**
 * Paal 13 — Foutafhandeling
 * Foutcodes: P13-[Module][Nummer]
 * Modules: A=Aanmelden D=Database M=Mail P=Portaal B=BLK S=Systeem L=Loon I=Instellingen
 */

define('P13_FOUT_EMAIL', 'website@paal13.com');

// Foutcode definities
const FOUTCODES = [
    // Database
    'P13-D001' => 'Database verbinding mislukt',
    'P13-D002' => 'Query uitvoering mislukt',
    'P13-D003' => 'Record niet gevonden',
    // Mail
    'P13-M001' => 'SMTP verbinding mislukt',
    'P13-M002' => 'Mail verzenden mislukt',
    'P13-M003' => 'Template niet gevonden',
    'P13-M004' => 'Melding overgeslagen: hetzelfde bericht ging vandaag al te vaak naar deze ontvanger (anti-loop)',
    'P13-M005' => 'WhatsApp tijdelijk gepauzeerd na herhaalde fouten',
    // Portaal
    'P13-P001' => 'OTP genereren mislukt',
    'P13-P002' => 'OTP verificatie mislukt',
    'P13-P003' => 'Portaal sessie verlopen',
    // Aanmelden
    'P13-A001' => 'Aanmelding opslaan mislukt',
    'P13-A002' => 'Dubbele aanmelding',
    // BLK
    'P13-B001' => 'BLK opslaan mislukt',
    'P13-B002' => 'BLK foto upload mislukt',
    // Loon
    'P13-L001' => 'Loonberekening mislukt',
    'P13-L002' => 'Loontarief niet gevonden',
    // Systeem
    'P13-S001' => 'Configuratiebestand ontbreekt',
    'P13-S002' => 'Onbevoegde toegang',
    'P13-S003' => 'Pagina niet gevonden',
    'P13-S004' => 'Upload map niet beschrijfbaar',
    'P13-S000' => 'Onverwachte fout',
    // Instellingen
    'P13-I001' => 'Instelling opslaan mislukt',
    'P13-I002' => 'AI API sleutel ontbreekt',
    'P13-I003' => 'AI analyse mislukt',
];

function p13_fout(
    string $code,
    string $detail = '',
    ?\Throwable $e = null,
    bool $fataal = false
): void {
    $bericht    = (FOUTCODES[$code] ?? 'Onbekende fout') . ($detail ? ": $detail" : '');
    $bestand    = $e ? $e->getFile() : (debug_backtrace(0, 2)[1]['file'] ?? '');
    $regel      = $e ? $e->getLine()  : (debug_backtrace(0, 2)[1]['line'] ?? 0);
    $url        = $_SERVER['REQUEST_URI'] ?? '';
    $gebruiker  = $_SESSION['bhd_email'] ?? ($_SESSION['portaal_email'] ?? 'anoniem');
    // Kort, uniek referentienummer zodat een foutmelding op het scherm terug te vinden is in het log
    $ref        = substr(bin2hex(random_bytes(4)), 0, 8);

    // Log naar database (best-effort)
    try {
        if (function_exists('db')) {
            $stmt = db()->prepare("
                INSERT INTO error_log (code, bericht, bestand, regel, gebruiker, url)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$code, "[ref $ref] $bericht", $bestand, $regel, $gebruiker, $url]);
        }
    } catch (\Exception $dbEx) {
        // DB logging mislukt — schrijf naar PHP error log
        error_log("P13 DB LOG FOUT: $code — $bericht");
    }

    // Stuur mail bij ernstige fouten (max 1x per 5 min per code)
    p13_stuur_fout_mail($code, $bericht, $bestand, $regel, $url, $gebruiker, $e);

    // PHP error log
    error_log("[$code ref:$ref] $bericht" . ($e ? " ({$e->getMessage()})" : ''));

    if ($fataal) {
        // Toon vriendelijke foutpagina
        if (!headers_sent()) {
            http_response_code(500);
        }
        $foutcode_html = htmlspecialchars($code . ' · ref ' . $ref);
        if (php_sapi_name() !== 'cli') {
            include __DIR__ . '/../500.php';
        }
        exit;
    }
}

function p13_stuur_fout_mail(
    string $code, string $bericht, string $bestand,
    int $regel, string $url, string $gebruiker, ?\Throwable $e
): void {
    // Rate limiting: niet spammen
    static $verstuurd = [];
    $sleutel = $code . '_' . floor(time() / 300); // 5 min window
    if (isset($verstuurd[$sleutel])) return;
    $verstuurd[$sleutel] = true;

    // Bepaal email adres
    $naar = P13_FOUT_EMAIL;
    try {
        if (function_exists('instelling')) {
            $naar = instelling('mail.fout_email', P13_FOUT_EMAIL);
        }
    } catch (\Exception $ignored) {}

    $site = defined('APP_URL') ? APP_URL : 'Paal 13';
    $tijd = date('d-m-Y H:i:s');

    $onderwerp = "[$code] Fout op $site";
    $body = "
<h2 style='color:#c53030;font-family:Arial'>⚠️ Foutmelding Paal 13</h2>
<table style='font-family:Arial;font-size:13px;border-collapse:collapse'>
  <tr><td style='padding:4px 12px 4px 0;color:#666;font-weight:600'>Code</td><td><code style='background:#fee2e2;padding:2px 6px;border-radius:3px'>$code</code></td></tr>
  <tr><td style='padding:4px 12px 4px 0;color:#666;font-weight:600'>Fout</td><td>" . htmlspecialchars($bericht) . "</td></tr>
  <tr><td style='padding:4px 12px 4px 0;color:#666;font-weight:600'>URL</td><td>" . htmlspecialchars($url) . "</td></tr>
  <tr><td style='padding:4px 12px 4px 0;color:#666;font-weight:600'>Bestand</td><td>" . htmlspecialchars(basename($bestand)) . " regel $regel</td></tr>
  <tr><td style='padding:4px 12px 4px 0;color:#666;font-weight:600'>Gebruiker</td><td>" . htmlspecialchars($gebruiker) . "</td></tr>
  <tr><td style='padding:4px 12px 4px 0;color:#666;font-weight:600'>Tijdstip</td><td>$tijd</td></tr>
" . ($e ? "  <tr><td style='padding:4px 12px 4px 0;color:#666;font-weight:600'>Exception</td><td><code>" . htmlspecialchars($e->getMessage()) . "</code></td></tr>" : '') . "
</table>
<p style='font-family:Arial;font-size:12px;color:#999;margin-top:16px'>
  Automatisch gegenereerd door het Paal 13 foutafhandelingssysteem.
</p>";

    try {
        if (class_exists('Mailer')) {
            Mailer::verstuur($naar, 'Beheerder', $onderwerp, $body);
        }
    } catch (\Exception $mailEx) {
        error_log("P13 FOUT MAIL MISLUKT: " . $mailEx->getMessage());
    }
}

// Global PHP error handler
function p13_php_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!($errno & error_reporting())) return false;
    if ($errno === E_NOTICE || $errno === E_DEPRECATED) return false; // Negeer notices
    p13_fout('P13-S000', "$errstr in " . basename($errfile) . ":$errline");
    return false; // Laat PHP ook zijn standaard afhandeling doen
}

// Global uncaught exception handler
function p13_exception_handler(\Throwable $e): void {
    p13_fout('P13-S000', $e->getMessage(), $e, true);
}

// Registreer handlers
if (!defined('P13_NO_ERROR_HANDLER')) {
    set_error_handler('p13_php_error_handler');
    set_exception_handler('p13_exception_handler');
}

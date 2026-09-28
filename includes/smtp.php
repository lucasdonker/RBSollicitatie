<?php
/**
 * Paal 13 SMTP Mailer
 * Puur PHP SMTP implementatie — geen composer, geen dependencies
 * Ondersteunt: plain, STARTTLS, SSL/TLS, AUTH LOGIN, AUTH PLAIN
 *
 * Twee algemene hulpjes staan hier omdat dit bestand als eerste na config.php laadt:
 *   config_ingevuld() / config_const()  — een {{PLAATSHOUDER}} uit config.template.php telt als leeg
 *   mail_omleiden()                     — testmodus (MAIL_OMLEIDEN in config.php)
 */

if (!function_exists('config_ingevuld')) {
    /** Is dit een echt ingevulde waarde? Leeg of een {{PLAATSHOUDER}} uit het sjabloon telt niet. */
    function config_ingevuld(string $waarde): bool {
        $waarde = trim($waarde);
        return $waarde !== '' && !str_contains($waarde, '{{');
    }

    /** Een constante uit config.php, of '' als hij ontbreekt of nog een plaatshouder is. */
    function config_const(string $naam): string {
        if (!defined($naam)) return '';
        $v = (string)constant($naam);   // niet trimmen: een wachtwoord mag met een spatie eindigen
        return config_ingevuld($v) ? $v : '';
    }

    /**
     * Testmodus: het adres waar álle e-mail naartoe gaat, of '' als de modus uit staat.
     * Bewust een constante in config.php en geen instelling: een teruggezette productiedatabase
     * op de testomgeving mag hem nooit uitzetten. Een ongeldig adres telt als uit.
     */
    function mail_omleiden(): string {
        $v = config_const('MAIL_OMLEIDEN');
        return ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL)) ? $v : '';
    }
}

class Mailer
{
    // ── Hoofd verzendmethode ──────────────────────────────────
    /** True als SMTP is ingesteld (via beheer óf config.php). Een plaatshouder telt niet. */
    public static function smtpActief(): bool {
        if (function_exists('instelling') && config_ingevuld(instelling('smtp.host', ''))) return true;
        return config_const('SMTP_HOST') !== '';
    }

    /**
     * Instelling uit de database (beheer), anders de constante uit config.php, anders $default.
     * Plaatshouders ({{...}}) tellen als niet ingesteld.
     */
    private static function inst(string $sleutel, string $const, string $default = ''): string {
        if (function_exists('instelling')) {
            $v = instelling($sleutel, '');
            if (config_ingevuld($v)) return $v;
        }
        $c = config_const($const);
        return $c !== '' ? $c : $default;
    }

    /**
     * Puur verzenden (SMTP of mail()), ZONDER logregel. Dit is wat de centrale meldingen-kern
     * (includes/meldingen/verzend.php) gebruikt; die logt zelf in mail_log.
     *
     * Testmodus (MAIL_OMLEIDEN): het bericht gaat naar dat adres, met de echte ontvanger vooraan
     * in het onderwerp. 'omgeleid' in het resultaat is dan het adres waar het heen ging.
     * @return array{ok:bool, fout:?string, omgeleid:?string}
     */
    public static function verzend(string $naar, string $naam, string $onderwerp, string $html_body, array $bijlagen = []): array {
        // Geen regeleinde in het adres: dat zou extra headers of SMTP-commando's kunnen injecteren.
        // (Naam en onderwerp gaan base64-gecodeerd de header in.)
        if (preg_match('/[\r\n]/', $naar)) {
            return ['ok' => false, 'fout' => 'ongeldig e-mailadres', 'omgeleid' => null];
        }
        $test = mail_omleiden();
        $omgeleid = $test !== '' ? $test : null;
        if ($omgeleid !== null && strcasecmp($test, trim($naar)) !== 0) {
            $onderwerp = '[TEST, voor ' . $naar . '] ' . $onderwerp;
            $naar = $test;
        }
        try {
            $ok = self::smtpActief()
                ? self::viaSMTP($naar, $naam, $onderwerp, $html_body, $bijlagen)
                : self::viaPhpMail($naar, $naam, $onderwerp, $html_body, $bijlagen);
            return ['ok' => (bool)$ok, 'fout' => $ok ? null : 'verzenden gaf false', 'omgeleid' => $omgeleid];
        } catch (\Throwable $e) {
            error_log('[Mailer] Fout: ' . $e->getMessage());
            return ['ok' => false, 'fout' => $e->getMessage(), 'omgeleid' => $omgeleid];
        }
    }

    /**
     * Legacy: verzenden én zelf loggen in mail_log. Nieuwe code gebruikt melding_verstuur().
     */
    public static function verstuur(
        string $naar,
        string $naam,
        string $onderwerp,
        string $html_body,
        ?int $sol_id = null,
        array $bijlagen = []
    ): bool {
        $r    = self::verzend($naar, $naam, $onderwerp, $html_body, $bijlagen);
        $ok   = $r['ok'];
        $fout = $r['fout'];

        // Mislukte mail ook naar het foutenlog schrijven, zodat de reden zichtbaar is
        // in Instellingen → Foutenlog (niet alleen in het server-errorlog). Zonder adres (AVG).
        if (!$ok && function_exists('p13_fout')) {
            $reden = $fout ?: 'onbekende reden';
            $code  = self::smtpActief() ? 'P13-M001' : 'P13-M002';
            p13_fout($code, "Mail ('{$onderwerp}') mislukt: {$reden}");
        }
        // Testmodus: de omleiding staat bij een geslaagde verzending in foutmelding (vrij tekstveld).
        if ($ok && !empty($r['omgeleid'])) $fout = 'testmodus: omgeleid naar ' . $r['omgeleid'];

        // Log in database
        try {
            db()->prepare(
                "INSERT INTO mail_log (sollicitatie_id, type, ontvanger, onderwerp, status, foutmelding)
                 VALUES (?, 'mail', ?, ?, ?, ?)"
            )->execute([$sol_id, $naar, $onderwerp, $ok ? 'verstuurd' : 'mislukt', $fout]);
        } catch (Exception $e) {
            // Mail log mislukking is niet fataal
        }

        return $ok;
    }

    /**
     * Maakt een MIME-bericht klaar voor de SMTP DATA-fase (RFC 5321 §4.5.2):
     * alle regeleindes naar CRLF, elke regel die met een punt begint krijgt er één bij,
     * en het geheel eindigt op CRLF zodat de afsluitende "." op een eigen regel staat.
     */
    private static function dataVoorbereiden(string $bericht): string {
        $bericht = str_replace(["\r\n", "\r"], "\n", $bericht);
        $bericht = str_replace("\n", "\r\n", $bericht);
        if (strncmp($bericht, '.', 1) === 0) $bericht = '.' . $bericht;
        $bericht = str_replace("\r\n.", "\r\n..", $bericht);
        if (substr($bericht, -2) !== "\r\n") $bericht .= "\r\n";
        return $bericht;
    }

    /** Geldige bijlagen (bestaande, leesbare bestanden) als ['pad','naam','mime']. */
    private static function bijlagenGeldig(array $bijlagen): array {
        $uit = [];
        foreach ($bijlagen as $b) {
            $pad = is_array($b) ? ($b['pad'] ?? '') : $b;
            if ($pad && is_file($pad) && is_readable($pad)) {
                $uit[] = [
                    'pad'  => $pad,
                    'naam' => is_array($b) ? ($b['naam'] ?? basename($pad)) : basename($pad),
                    'mime' => is_array($b) ? ($b['mime'] ?? 'application/octet-stream') : 'application/octet-stream',
                ];
            }
        }
        return $uit;
    }

    /**
     * De platte-tekstversie van een HTML-mail, voor wie geen HTML toont (en voor spamfilters).
     * Eerder was dit strip_tags van alles: de CSS uit <style> stond dan bovenaan de mail en elke
     * link verdween. Nu: kop/stijl/script weg, links als "tekst (adres)", blokken op eigen regels.
     */
    public static function tekstVersie(string $html): string {
        $t = preg_replace('#<(head|style|script|title)\b[^>]*>.*?</\1>#is', '', $html);
        $t = preg_replace('/<!--.*?-->/s', '', $t);
        $t = preg_replace_callback('#<a\b[^>]*\bhref=(["\'])(.*?)\1[^>]*>(.*?)</a>#is', function ($m) {
            $url   = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            $tekst = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES, 'UTF-8'));
            if (stripos($url, 'mailto:') === 0) $url = substr($url, 7);
            if ($tekst === '' || $tekst === $url) return $url;
            return $tekst . ' (' . $url . ')';
        }, $t);
        $t = preg_replace('#<br\s*/?>#i', "\n", $t);
        $t = preg_replace('#<(ul|ol)\b[^>]*>#i', "\n", $t);
        $t = preg_replace('#<li\b[^>]*>#i', '- ', $t);
        $t = preg_replace('#</(div|li|tr)>#i', "\n", $t);
        $t = preg_replace('#</(p|h[1-6]|ul|ol|table|blockquote)>#i', "\n\n", $t);
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
        $t = preg_replace('/[ \t\x{00A0}]+/u', ' ', $t);
        $t = preg_replace('/ *\n */', "\n", $t);
        return trim(preg_replace('/\n{3,}/', "\n\n", $t));
    }

    /**
     * De MIME-inhoud, gedeeld door SMTP en mail(): tekst + HTML (multipart/alternative), met
     * bijlagen eromheen als multipart/mixed. Zonder de adresheaders (From/To/Subject), want die
     * geeft mail() apart mee.
     * @return array{content_type:string, body:string}
     */
    public static function mimeInhoud(string $naam, string $onderwerp, string $body, array $bijlagen = []): array {
        $boundary = md5(uniqid((string)mt_rand(), true));

        $plain = self::tekstVersie($body);

        // Het inhoudsdeel (tekst + HTML) — altijd multipart/alternative
        $altBody =
            "--{$boundary}\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: quoted-printable\r\n" .
            "\r\n" .
            quoted_printable_encode($plain) . "\r\n" .
            "--{$boundary}\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: quoted-printable\r\n" .
            "\r\n" .
            quoted_printable_encode(self::bereideHtmlVoor($naam, $onderwerp, $body)) . "\r\n" .
            "--{$boundary}--\r\n";

        $geldige_bijlagen = self::bijlagenGeldig($bijlagen);
        if (!$geldige_bijlagen) {
            return ['content_type' => "multipart/alternative; boundary=\"{$boundary}\"", 'body' => $altBody];
        }

        // multipart/mixed: eerst het alternative-deel, dan de bijlagen
        $mixed = md5(uniqid((string)mt_rand(), true)) . 'mix';
        $inhoud =
            "--{$mixed}\r\n" .
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n" .
            "\r\n" .
            $altBody;
        foreach ($geldige_bijlagen as $bij) {
            $data = base64_encode((string)file_get_contents($bij['pad']));
            $data = chunk_split($data, 76, "\r\n");
            $bnaam = '=?UTF-8?B?' . base64_encode($bij['naam']) . '?=';
            $inhoud .=
                "--{$mixed}\r\n" .
                "Content-Type: {$bij['mime']}; name=\"{$bnaam}\"\r\n" .
                "Content-Transfer-Encoding: base64\r\n" .
                "Content-Disposition: attachment; filename=\"{$bnaam}\"\r\n" .
                "\r\n" .
                $data . "\r\n";
        }
        $inhoud .= "--{$mixed}--\r\n";
        return ['content_type' => "multipart/mixed; boundary=\"{$mixed}\"", 'body' => $inhoud];
    }

    /**
     * Waar "Beantwoorden" heen gaat: het contactadres voor sollicitanten (mail.contact_email,
     * sollicitatie@paal13.com), anders het bestuursadres, anders de afzender. Zonder deze header
     * kwam een antwoord op het afzenderadres uit, en dat kan een noreply-adres zijn.
     */
    private static function replyTo(string $from): string {
        foreach (['portaal_contact_email', 'bestuur_email'] as $f) {
            if (function_exists($f)) {
                $a = trim((string)$f());
                if ($a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL)) return $a;
            }
        }
        return $from;
    }

    // ── SMTP verzending ───────────────────────────────────────
    private static function viaSMTP(string $naar, string $naam, string $onderwerp, string $body, array $bijlagen = []): bool
    {
        // Instellingen: eerst uit de database (beheer), anders uit config.php als terugval.
        $host     = self::inst('smtp.host', 'SMTP_HOST');
        $port     = (int)self::inst('smtp.port', 'SMTP_PORT', '587');
        $user     = self::inst('smtp.user', 'SMTP_USER');
        $pass     = self::inst('smtp.pass', 'SMTP_PASS');
        $encrypt  = strtolower(self::inst('smtp.encrypt', 'SMTP_ENCRYPT', 'tls'));
        $from     = self::inst('smtp.from', 'MAIL_FROM', $user);
        $fromName = self::inst('smtp.from_naam', 'MAIL_FROM_NAME', 'Paal 13');
        $timeout  = 15;

        // Verbinding openen
        if ($encrypt === 'ssl') {
            $socket = @fsockopen("ssl://{$host}", $port, $errno, $errstr, $timeout);
        } else {
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        }

        if (!$socket) {
            throw new Exception("SMTP verbinding mislukt ({$host}:{$port}): {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeout);

        // Helper: lees response
        $lees = function () use ($socket): string {
            $r = '';
            while ($line = fgets($socket, 515)) {
                $r .= $line;
                if (substr($line, 3, 1) === ' ') break; // laatste regel heeft spatie na code
            }
            return $r;
        };

        // Helper: stuur commando
        $stuur = function (string $cmd) use ($socket, $lees): string {
            fwrite($socket, $cmd . "\r\n");
            return $lees();
        };

        // Welkomstbericht
        $resp = $lees();
        if (!str_starts_with($resp, '2')) {
            fclose($socket);
            throw new Exception("SMTP welkomst mislukt: {$resp}");
        }

        // EHLO
        $resp = $stuur("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!str_starts_with($resp, '2')) {
            $resp = $stuur("HELO localhost"); // fallback
        }

        // STARTTLS opwaarderen
        if ($encrypt === 'tls') {
            $resp = $stuur("STARTTLS");
            if (!str_starts_with($resp, '2')) {
                fclose($socket);
                throw new Exception("STARTTLS geweigerd: {$resp}");
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new Exception("TLS handshake mislukt");
            }
            // Opnieuw EHLO na TLS
            $resp = $stuur("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        }

        // Authenticatie
        if ($user !== '' && $pass !== '') {
            // Probeer AUTH LOGIN
            $resp = $stuur("AUTH LOGIN");
            if (str_starts_with($resp, '334')) {
                $stuur(base64_encode($user));
                $resp = $stuur(base64_encode($pass));
                if (!str_starts_with($resp, '235')) {
                    fclose($socket);
                    throw new Exception("SMTP authenticatie mislukt: {$resp}");
                }
            } else {
                // Probeer AUTH PLAIN
                $auth = base64_encode("\0{$user}\0{$pass}");
                $resp = $stuur("AUTH PLAIN {$auth}");
                if (!str_starts_with($resp, '235')) {
                    fclose($socket);
                    throw new Exception("SMTP authenticatie mislukt: {$resp}");
                }
            }
        }

        // MAIL FROM
        $resp = $stuur("MAIL FROM:<{$from}>");
        if (!str_starts_with($resp, '2')) {
            fclose($socket);
            throw new Exception("MAIL FROM geweigerd: {$resp}");
        }

        // RCPT TO
        $resp = $stuur("RCPT TO:<{$naar}>");
        if (!str_starts_with($resp, '2')) {
            fclose($socket);
            throw new Exception("RCPT TO geweigerd voor {$naar}: {$resp}");
        }

        // DATA
        $stuur("DATA");

        // Bouw MIME bericht
        $mime    = self::mimeInhoud($naam, $onderwerp, $body, $bijlagen);
        $naamEnc = '=?UTF-8?B?' . base64_encode($naam) . '?=';
        $ondEnc  = '=?UTF-8?B?' . base64_encode($onderwerp) . '?=';
        $vanEnc  = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $bericht =
            "Date: " . date('r') . "\r\n" .
            "Message-ID: <" . md5(uniqid()) . "@paal13.com>\r\n" .
            "From: {$vanEnc} <{$from}>\r\n" .
            "To: {$naamEnc} <{$naar}>\r\n" .
            "Reply-To: " . self::replyTo($from) . "\r\n" .
            "Subject: {$ondEnc}\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: {$mime['content_type']}\r\n" .
            "\r\n" .
            $mime['body'];

        // RFC 5321 §4.5.2: regels die met een punt beginnen verdubbelen, anders eet de
        // mailserver die punt op. (quoted-printable breekt lange URL's af op 75 tekens;
        // viel die afbreking net vóór een punt, dan kwam er een kapotte link aan.)
        $resp = $stuur(self::dataVoorbereiden($bericht) . '.');
        $stuur("QUIT");
        fclose($socket);

        if (!str_starts_with($resp, '2')) {
            throw new Exception("Bericht geweigerd: {$resp}");
        }

        return true;
    }

    // ── Fallback: PHP mail() ──────────────────────────────────
    /**
     * Zelfde MIME-bericht als via SMTP (tekst + HTML + bijlagen). Vroeger gingen bijlagen hier
     * stil verloren terwijl de mail als verstuurd werd gelogd (getekende PDF's, ledenservice).
     */
    private static function viaPhpMail(string $naar, string $naam, string $onderwerp, string $body, array $bijlagen = []): bool
    {
        $from     = self::inst('smtp.from', 'MAIL_FROM', 'noreply@paal13.com');
        $fromName = self::inst('smtp.from_naam', 'MAIL_FROM_NAME', 'Paal 13');
        $reply    = self::replyTo($from);
        $mime     = self::mimeInhoud($naam, $onderwerp, $body, $bijlagen);
        $headers  = implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-Type: {$mime['content_type']}",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>",
            "Reply-To: {$reply}",
        ]);
        $ondEnc = '=?UTF-8?B?' . base64_encode($onderwerp) . '?=';
        return @mail($naar, $ondEnc, $mime['body'], $headers);
    }

    // ── Bereid HTML voor: wrap alleen als nog geen volledige HTML ──
    private static function bereideHtmlVoor(string $naam, string $onderwerp, string $body): string {
        $trimmed = ltrim($body);
        // Al een volledige HTML pagina (van mail_html_wrapper) → niet opnieuw wrappen
        if (stripos($trimmed, '<!DOCTYPE') === 0 || stripos($trimmed, '<html') === 0) {
            return $body;
        }
        return self::htmlTemplate($naam, $onderwerp, $body);
    }

    // ── HTML e-mail template ──────────────────────────────────
    public static function htmlTemplate(string $naam, string $onderwerp, string $body): string
    {
        // Nieuwe centrale mail-template gebruiken als die beschikbaar is.
        // De aanhef "Hoi {naam}," wordt vooraan de body gezet (deed de oude template ook).
        if (function_exists('mail_thema_wrapper')) {
            $aanhef = $naam
                ? '<p style="margin:0 0 16px"><strong style="color:#054d8a">Hoi ' . htmlspecialchars($naam, ENT_QUOTES, 'UTF-8') . ',</strong></p>'
                : '';
            $body_verwerkt = function_exists('mail_inline_styles') ? mail_inline_styles($body) : $body;
            return mail_thema_wrapper($aanhef . $body_verwerkt, $onderwerp, $onderwerp);
        }

        return '<p>Hoi ' . htmlspecialchars($naam, ENT_QUOTES, 'UTF-8') . ',</p>' . $body;
    }

    /**
     * Probeert een testmail te versturen en geeft het resultaat terug met foutmelding.
     * In testmodus gaat ook de testmail naar het omleidadres.
     * @return array{ok:bool, melding:string}
     */
    public static function test(string $naar): array {
        if (!self::smtpActief()) {
            return ['ok' => false, 'melding' => 'Geen SMTP ingesteld. Vul eerst de servergegevens in (of het systeem gebruikt PHP mail(), wat op shared hosting vaak niet werkt).'];
        }
        $onderwerp = 'SMTP-test Paal 13';
        $test = mail_omleiden();
        if ($test !== '' && strcasecmp($test, $naar) !== 0) { $onderwerp = '[TEST, voor ' . $naar . '] ' . $onderwerp; $naar = $test; }
        try {
            $body = '<p>Dit is een testmail van het Paal 13 sollicitatiesysteem.</p><p>Als je deze ontvangt, werken je SMTP-instellingen correct.</p>';
            $html = function_exists('mail_thema_wrapper')
                ? mail_thema_wrapper($body, 'SMTP-test', 'Testmail', 'Je instellingen werken!')
                : $body;
            $ok = self::viaSMTP($naar, 'Test', $onderwerp, $html);
            return $ok
                ? ['ok' => true, 'melding' => "Testmail verstuurd naar {$naar}. Controleer je inbox (en spam)."]
                : ['ok' => false, 'melding' => 'De testmail kon niet worden verstuurd (onbekende reden).'];
        } catch (\Exception $e) {
            return ['ok' => false, 'melding' => 'Testmail mislukt: ' . $e->getMessage()];
        }
    }
}

// Losse wrapper zodat het beheer smtp_test() kan aanroepen
if (!function_exists('smtp_test')) {
    function smtp_test(string $naar): array {
        return Mailer::test($naar);
    }
}

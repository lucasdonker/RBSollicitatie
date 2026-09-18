<?php
/**
 * Paal 13 SMTP Mailer
 * Puur PHP SMTP implementatie — geen composer, geen dependencies
 * Ondersteunt: plain, STARTTLS, SSL/TLS, AUTH LOGIN, AUTH PLAIN
 */

class Mailer
{
    // ── Hoofd verzendmethode ──────────────────────────────────
    /** True als SMTP is ingesteld (via beheer óf config.php). */
    public static function smtpActief(): bool {
        if (function_exists('instelling') && instelling('smtp.host', '') !== '') return true;
        return defined('SMTP_HOST') && SMTP_HOST !== '';
    }

    /**
     * Puur verzenden (SMTP of mail()), ZONDER logregel. Dit is wat de centrale meldingen-kern
     * (includes/meldingen/verzend.php) gebruikt; die logt zelf in mail_log.
     * @return array{ok:bool, fout:?string}
     */
    public static function verzend(string $naar, string $naam, string $onderwerp, string $html_body, array $bijlagen = []): array {
        try {
            $ok = self::smtpActief()
                ? self::viaSMTP($naar, $naam, $onderwerp, $html_body, $bijlagen)
                : self::viaPhpMail($naar, $naam, $onderwerp, $html_body);
            return ['ok' => (bool)$ok, 'fout' => $ok ? null : 'verzenden gaf false'];
        } catch (\Throwable $e) {
            error_log('[Mailer] Fout: ' . $e->getMessage());
            return ['ok' => false, 'fout' => $e->getMessage()];
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

    // ── SMTP verzending ───────────────────────────────────────
    private static function viaSMTP(string $naar, string $naam, string $onderwerp, string $body, array $bijlagen = []): bool
    {
        // Instellingen: eerst uit de database (beheer), anders uit config.php als terugval.
        $ci = function(string $sleutel, string $const, string $default = ''): string {
            if (function_exists('instelling')) {
                $v = instelling($sleutel, '');
                if ($v !== '') return $v;
            }
            return defined($const) ? (string)constant($const) : $default;
        };
        $host     = $ci('smtp.host', 'SMTP_HOST');
        $port     = (int)$ci('smtp.port', 'SMTP_PORT', '587');
        $user     = $ci('smtp.user', 'SMTP_USER');
        $pass     = $ci('smtp.pass', 'SMTP_PASS');
        $encrypt  = strtolower($ci('smtp.encrypt', 'SMTP_ENCRYPT', 'tls'));
        $from     = $ci('smtp.from', 'MAIL_FROM', $user);
        $fromName = $ci('smtp.from_naam', 'MAIL_FROM_NAME', 'Paal 13');
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
        $boundary = md5(uniqid((string)mt_rand(), true));
        $datum    = date('r');
        $msgid    = '<' . md5(uniqid()) . '@paal13.com>';
        $naamEnc  = '=?UTF-8?B?' . base64_encode($naam) . '?=';
        $ondEnc   = '=?UTF-8?B?' . base64_encode($onderwerp) . '?=';
        $vanEnc   = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

        // Plain text versie (strip HTML tags)
        $plain = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $body));
        $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
        $plain = preg_replace('/\n{3,}/', "\n\n", trim($plain));

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

        // Geldige bijlagen verzamelen (bestaande, leesbare bestanden)
        $geldige_bijlagen = [];
        foreach ($bijlagen as $b) {
            $pad = is_array($b) ? ($b['pad'] ?? '') : $b;
            if ($pad && is_file($pad) && is_readable($pad)) {
                $geldige_bijlagen[] = [
                    'pad'  => $pad,
                    'naam' => is_array($b) ? ($b['naam'] ?? basename($pad)) : basename($pad),
                    'mime' => is_array($b) ? ($b['mime'] ?? 'application/octet-stream') : 'application/octet-stream',
                ];
            }
        }

        $headers =
            "Date: {$datum}\r\n" .
            "Message-ID: {$msgid}\r\n" .
            "From: {$vanEnc} <{$from}>\r\n" .
            "To: {$naamEnc} <{$naar}>\r\n" .
            "Subject: {$ondEnc}\r\n" .
            "MIME-Version: 1.0\r\n";

        if ($geldige_bijlagen) {
            // multipart/mixed: eerst het alternative-deel, dan de bijlagen
            $mixed = md5(uniqid((string)mt_rand(), true)) . 'mix';
            $bericht = $headers .
                "Content-Type: multipart/mixed; boundary=\"{$mixed}\"\r\n" .
                "\r\n" .
                "--{$mixed}\r\n" .
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n" .
                "\r\n" .
                $altBody;
            foreach ($geldige_bijlagen as $bij) {
                $data = base64_encode((string)file_get_contents($bij['pad']));
                $data = chunk_split($data, 76, "\r\n");
                $bnaam = '=?UTF-8?B?' . base64_encode($bij['naam']) . '?=';
                $bericht .=
                    "--{$mixed}\r\n" .
                    "Content-Type: {$bij['mime']}; name=\"{$bnaam}\"\r\n" .
                    "Content-Transfer-Encoding: base64\r\n" .
                    "Content-Disposition: attachment; filename=\"{$bnaam}\"\r\n" .
                    "\r\n" .
                    $data . "\r\n";
            }
            $bericht .= "--{$mixed}--\r\n.";
        } else {
            $bericht = $headers .
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n" .
                "\r\n" .
                $altBody .
                ".";
        }

        $resp = $stuur($bericht);
        $stuur("QUIT");
        fclose($socket);

        if (!str_starts_with($resp, '2')) {
            throw new Exception("Bericht geweigerd: {$resp}");
        }

        return true;
    }

    // ── Fallback: PHP mail() ──────────────────────────────────
    private static function viaPhpMail(string $naar, string $naam, string $onderwerp, string $body): bool
    {
        $from     = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@paal13.com';
        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Paal 13';
        $headers  = implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: {$fromName} <{$from}>",
            "Reply-To: " . (function_exists('bestuur_email') && bestuur_email() !== '' ? bestuur_email() : (defined('MAIL_BESTUUR') ? MAIL_BESTUUR : $from)),
        ]);
        return @mail($naar, $onderwerp, self::htmlTemplate($naam, $onderwerp, $body), $headers);
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
     * @return array{ok:bool, melding:string}
     */
    public static function test(string $naar): array {
        if (!self::smtpActief()) {
            return ['ok' => false, 'melding' => 'Geen SMTP ingesteld. Vul eerst de servergegevens in (of het systeem gebruikt PHP mail(), wat op shared hosting vaak niet werkt).'];
        }
        try {
            $body = '<p>Dit is een testmail van het Paal 13 sollicitatiesysteem.</p><p>Als je deze ontvangt, werken je SMTP-instellingen correct.</p>';
            $html = function_exists('mail_thema_wrapper')
                ? mail_thema_wrapper($body, 'SMTP-test', 'Testmail', 'Je instellingen werken!')
                : $body;
            $ok = self::viaSMTP($naar, 'Test', 'SMTP-test Paal 13', $html);
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
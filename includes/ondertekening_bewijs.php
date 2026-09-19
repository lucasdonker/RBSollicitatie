<?php
/**
 * Paal 13 — Ondertekeningen: bewijs (gebeurtenissenlog) en 2FA-code bij het tekenen.
 *
 * Elke stap van een ondertekening wordt vastgelegd in `ondertekening_gebeurtenissen`:
 *   geopend, code_verzonden, code_fout, code_ok, ondertekend, pdf_verzegeld, pdf_gedownload, ...
 * met tijdstip, IP en browser. Daaruit komt het "Certificaat van ondertekening" (extra pagina in de
 * verzegelde PDF, includes/zegel.php) en het bewijs bij een controle. Gevoelige waarden (BSN, IBAN,
 * de code zelf) komen hier nooit in. Het telefoonnummer of e-mailadres waar de code naartoe ging staat er
 * WEL volledig in (log + kolom `zekerheid_naar`): zonder dat nummer is niet te herleiden welk middel is
 * geverifieerd, en dat is precies het bewijs dat de wet vraagt. Op het scherm blijft het gemaskeerd
 * (`ondertekening_tel_masker()`).
 *
 * 2FA: voor de typen in instelling `ondertekenen.2fa_typen` (standaard loonheffing, lidmaatschap) moet de
 * ondertekenaar vlak vóór het tekenen een code invoeren die naar het eigen telefoonnummer is gestuurd
 * (SMS als dat kanaal actief is, anders WhatsApp, anders e-mail — het kanaal bepaalt het zekerheidsniveau
 * dat in het bewijs komt). De code loopt via de bestaande otp_tokens (15 minuten, 5 pogingen).
 */

if (!function_exists('ondertekening_gebeurtenis')) {

function ondertekening_bewijs_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `ondertekening_gebeurtenissen` (
            `id`               INT AUTO_INCREMENT PRIMARY KEY,
            `ondertekening_id` INT NOT NULL,
            `gebeurtenis`      VARCHAR(30) NOT NULL,
            `tijdstip`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ip`               VARCHAR(45) DEFAULT NULL,
            `browser`          VARCHAR(255) DEFAULT NULL,
            `details`          VARCHAR(500) DEFAULT NULL,
            INDEX (`ondertekening_id`), INDEX (`gebeurtenis`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if (function_exists('db_kolom_verzeker')) {
            $db = db();
            db_kolom_verzeker($db, 'ondertekeningen', 'vertrouwelijk',   'TEXT DEFAULT NULL');
            db_kolom_verzeker($db, 'ondertekeningen', 'pdf_document_id', 'INT DEFAULT NULL');
            db_kolom_verzeker($db, 'ondertekeningen', 'pdf_sha256',      'CHAR(64) DEFAULT NULL');
            db_kolom_verzeker($db, 'ondertekeningen', 'zekerheid',       'VARCHAR(20) DEFAULT NULL');
            // Het telefoonnummer of e-mailadres waar de verificatiecode naartoe ging, zoals gebruikt op het
            // moment van ondertekenen. Snapshot: wijzigt de sollicitant later zijn nummer, dan blijft het
            // bewijs herleidbaar. 255 dekt de breedste bron (personen.ouder_email); nooit korter maken,
            // MariaDB kapt stil af (zie docs/ai/harnas/schema_check.php).
            db_kolom_verzeker($db, 'ondertekeningen', 'zekerheid_naar',  'VARCHAR(255) DEFAULT NULL');
            db_kolom_verzeker($db, 'ondertekeningen', 'bewijs_hash',     'CHAR(64) DEFAULT NULL');
            db_kolom_verzeker($db, 'ondertekeningen', 'verwerkt_op',     'DATETIME DEFAULT NULL');
            db_kolom_verzeker($db, 'ondertekeningen', 'verwerkt_door',   'INT DEFAULT NULL');
        }
    } catch (\Throwable $e) { error_log('ondertekening_bewijs_auto_setup: ' . $e->getMessage()); }
}

/** Gebeurtenis vastleggen (faalt stil; mag nooit de flow breken). $details: korte, niet-gevoelige tekst of array. */
function ondertekening_gebeurtenis(int $ond_id, string $gebeurtenis, $details = null): void {
    ondertekening_bewijs_auto_setup();
    try {
        $d = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : ($details === null ? null : (string)$details);
        db()->prepare("INSERT INTO ondertekening_gebeurtenissen (ondertekening_id, gebeurtenis, ip, browser, details) VALUES (?,?,?,?,?)")
            ->execute([$ond_id, substr($gebeurtenis, 0, 30), $_SERVER['REMOTE_ADDR'] ?? null,
                       isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null, $d === null ? null : substr($d, 0, 500)]);
    } catch (\Throwable $e) { error_log('ondertekening_gebeurtenis: ' . $e->getMessage()); }
}

/** Alle gebeurtenissen van een ondertekening, oudste eerst. */
function ondertekening_gebeurtenissen(int $ond_id): array {
    ondertekening_bewijs_auto_setup();
    try { $q = db()->prepare("SELECT * FROM ondertekening_gebeurtenissen WHERE ondertekening_id=? ORDER BY id"); $q->execute([$ond_id]); return $q->fetchAll(); }
    catch (\Throwable $e) { return []; }
}

/** "Geopend" één keer per sessie per ondertekening loggen. */
function ondertekening_geopend_loggen(int $ond_id, string $rol): void {
    $k = 'ond_geopend_' . $ond_id;
    if (!empty($_SESSION[$k])) return;
    $_SESSION[$k] = time();
    ondertekening_gebeurtenis($ond_id, 'geopend', ['rol' => $rol]);
}

/**
 * Typen waarvoor een verificatiecode verplicht is (instelling, komma-gescheiden).
 * Alleen documenten met persoonsgegevens die wettelijk vastgelegd moeten worden — loonheffing en later een
 * contract. De inschrijving als lid hoort daar bewust NIET bij (besluit Lucas, 19 sep 2026): die is een
 * verenigingsformulier, geen wettelijk document, en een extra drempel zou alleen afhaken opleveren.
 */
function ondertekening_2fa_typen(): array {
    $s = function_exists('instelling') ? instelling('ondertekenen.2fa_typen', 'loonheffing') : 'loonheffing';
    return array_values(array_filter(array_map('trim', explode(',', $s))));
}
function ondertekening_2fa_nodig(string $type): bool {
    return in_array($type, ondertekening_2fa_typen(), true);
}

/** Telefoonnummer gemaskeerd voor op het scherm en in de API-respons: "06 12 34 56 78" → "06******78". */
function ondertekening_tel_masker(?string $tel): string {
    $t = preg_replace('/\D+/', '', (string)$tel);
    if (strlen($t) < 4) return '';
    return substr($t, 0, 2) . str_repeat('*', max(0, strlen($t) - 4)) . substr($t, -2);
}

/**
 * Stuur een 2FA-code naar de ondertekenaar. Kanaal: SMS → WhatsApp → e-mail (zekerheid 'sms'|'whatsapp'|'email').
 * $ontvanger: ['email', 'naam', 'telefoon']. De otp-sleutel is het e-mailadres; het type bindt de code aan deze
 * ondertekening. Retour: ['ok'=>bool, 'kanaal'=>string, 'naar'=>string (volledig, alleen voor het bewijs),
 * 'fout'=>?string].
 */
function ondertekening_code_versturen(int $ond_id, array $ontvanger): array {
    $email = (string)($ontvanger['email'] ?? '');
    $tel   = trim((string)($ontvanger['telefoon'] ?? ''));
    $naam  = (string)($ontvanger['naam'] ?? '');
    if ($email === '') return ['ok' => false, 'kanaal' => '', 'fout' => 'Geen e-mailadres bekend om een code naar te sturen.'];
    // Maximaal 5 codes per uur per ondertekening
    try {
        $q = db()->prepare("SELECT COUNT(*) FROM ondertekening_gebeurtenissen WHERE ondertekening_id=? AND gebeurtenis='code_verzonden' AND tijdstip > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $q->execute([$ond_id]);
        if ((int)$q->fetchColumn() >= 5) return ['ok' => false, 'kanaal' => '', 'fout' => 'Er zijn al te veel codes verstuurd. Probeer het over een uur opnieuw.'];
    } catch (\Throwable $e) {}

    try {
        $code = otp_genereer($email, 'ond' . $ond_id);
    } catch (\Throwable $e) {
        error_log('ondertekening_code_versturen: ' . $e->getMessage());
        return ['ok' => false, 'kanaal' => '', 'fout' => 'De code kon niet worden klaargezet (technische storing). Meld dit bij het bestuur; probeer het daarna opnieuw.'];
    }
    $tekst = "Je code om te ondertekenen bij Paal 13: $code (15 minuten geldig). Niet gevraagd? Negeer dit bericht.";
    $kanaal = 'email'; $ok = false;
    if ($tel !== '' && function_exists('sms_actief') && sms_actief()) {
        $ok = sms_verstuur($tel, $tekst); if ($ok) $kanaal = 'sms';
    }
    if (!$ok && $tel !== '' && function_exists('wa_actief') && wa_actief()) {
        $ok = wa_verstuur($tel, $tekst); if ($ok) $kanaal = 'whatsapp';
    }
    if (!$ok && function_exists('melding_verstuur')) {
        $r = melding_verstuur('portaal_code', 0, ['otp_code' => $code, 'voornaam' => $naam ?: 'daar'],
            ['ontvanger' => ['email' => $email, 'naam' => $naam, 'telefoon' => '', 'rol' => 'direct', 'kanaal_voorkeur' => 'email'], 'prioriteit' => 'direct', 'dedup_min' => 0]);
        $ok = in_array($r['status'] ?? '', ['verstuurd', 'wacht'], true); $kanaal = 'email';
    }
    if (!$ok) return ['ok' => false, 'kanaal' => '', 'fout' => 'De code kon niet verstuurd worden. Probeer het opnieuw of mail ' . (function_exists('portaal_contact_email') ? portaal_contact_email() : 'het bestuur') . '.'];
    // In het bewijs (sessie + gebeurtenis) het VOLLEDIGE nummer/adres, zodat later herleidbaar is welk nummer is
    // gebruikt. Op het scherm en in de API-respons blijft het gemaskeerd (zie api_ondertekenen_code.php).
    $naar = $kanaal === 'email' ? $email : $tel;
    $_SESSION['ond_2fa_' . $ond_id] = ['kanaal' => $kanaal, 'naar' => $naar, 'op' => time()];
    ondertekening_gebeurtenis($ond_id, 'code_verzonden', ['kanaal' => $kanaal, 'naar' => $naar]);
    return ['ok' => true, 'kanaal' => $kanaal, 'naar' => $naar, 'fout' => null];
}

/**
 * Controleer de ingevoerde code. Logt code_ok / code_fout.
 * Retour: ['kanaal' => 'sms'|'whatsapp'|'email', 'naar' => volledig nummer of e-mailadres] of null bij fout.
 * Spaties en streepjes (uit een gekopieerd sms-bericht) worden weggefilterd.
 */
function ondertekening_code_controleren(int $ond_id, string $email, string $code): ?array {
    $sessie = $_SESSION['ond_2fa_' . $ond_id] ?? null;
    $cijfers = preg_replace('/\D+/', '', $code);
    if (!$sessie || strlen($cijfers) !== 6) { ondertekening_gebeurtenis($ond_id, 'code_fout', ['reden' => $sessie ? 'vorm' : 'geen_sessie']); return null; }
    if (!otp_verifieer($email, $cijfers, 'ond' . $ond_id)) { ondertekening_gebeurtenis($ond_id, 'code_fout', ['reden' => 'onjuist_of_verlopen']); return null; }
    $naar = (string)($sessie['naar'] ?? '');
    ondertekening_gebeurtenis($ond_id, 'code_ok', ['kanaal' => $sessie['kanaal'], 'naar' => $naar]);
    unset($_SESSION['ond_2fa_' . $ond_id]);
    return ['kanaal' => (string)$sessie['kanaal'], 'naar' => $naar];
}

/** Leesbare naam van een gebeurtenis voor het certificaat en het beheer. */
function ondertekening_gebeurtenis_label(string $g): string {
    return match ($g) {
        'aangemaakt'     => 'Document klaargezet',
        'geopend'        => 'Document geopend',
        'code_verzonden' => 'Verificatiecode verstuurd',
        'code_fout'      => 'Verificatiecode onjuist',
        'code_ok'        => 'Verificatiecode geaccepteerd',
        'ondertekend'    => 'Ondertekend (akkoord + handtekening)',
        'pdf_verzegeld'  => 'PDF gegenereerd en digitaal verzegeld',
        'pdf_gedownload' => 'PDF gedownload',
        'verwerkt'       => 'Verwerkt door de penningmeester',
        default          => ucfirst(str_replace('_', ' ', $g)),
    };
}

/**
 * Leesbaar zekerheidsniveau, met het nummer of adres waar de code naartoe ging ($naar uit
 * ondertekeningen.zekerheid_naar). Zonder $naar (oudere records) valt het terug op de algemene tekst.
 */
function ondertekening_zekerheid_label(?string $z, ?string $naar = null): string {
    $waar = trim((string)$naar);
    $tel  = ($waar !== '' && function_exists('telefoon_opmaak')) ? telefoon_opmaak($waar) : $waar;
    return match ($z) {
        'sms'      => $tel !== ''  ? "SMS-code naar $tel (eigen mobiele nummer, 2FA)"      : 'SMS-code naar het eigen mobiele nummer (2FA)',
        'whatsapp' => $tel !== ''  ? "WhatsApp-code naar $tel (eigen mobiele nummer, 2FA)" : 'WhatsApp-code naar het eigen mobiele nummer (2FA)',
        'email'    => $waar !== '' ? "Code per e-mail naar $waar (het account waarmee is ingelogd)" : 'Code per e-mail (het account waarmee is ingelogd)',
        default    => 'Persoonlijke link + inlog met e-mailcode',
    };
}

/**
 * Bewijs-hash over de vastgelegde inhoud: formulierdata + versleutelde vertrouwelijke waarden +
 * handtekening-afbeelding + tijdstip. Wordt in het record en op het certificaat gezet.
 */
function ondertekening_bewijs_hash(array $rec): string {
    return hash('sha256', implode('|', [
        (string)($rec['id'] ?? ''), (string)($rec['type'] ?? ''), (string)($rec['formulier_data'] ?? ''),
        (string)($rec['vertrouwelijk'] ?? ''), (string)($rec['handtekening_beeld'] ?? ''), (string)($rec['ondertekend_op'] ?? ''),
        (string)($rec['ondertekenaar_naam'] ?? ''), (string)($rec['plaats'] ?? ''),
    ]));
}

} // function_exists

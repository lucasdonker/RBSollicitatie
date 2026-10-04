<?php
/**
 * Paal 13 — inloggen op het portaal zonder overtypwerk.
 *
 * Het probleem: een sollicitant opent het portaal drie tot zes keer in een half jaar.
 * Dat is te weinig om iets te onthouden en te vaak om elke keer zeven stappen te doen
 * (adres typen → wachten op mail → naar de mailapp → zes cijfers lezen → terug →
 * overtypen). En omdat de sessiecookie op `lifetime => 0` stond, verviel de sessie
 * zodra de browser dichtging — op een telefoon dus zo'n beetje elk bezoek.
 *
 * Twee dingen lossen dat op:
 *
 * 1. EEN INLOGLINK naast de code. Zelfde adres, zelfde geldigheidsduur, zelfde
 *    eenmaligheid — alleen zonder het typewerk. De link gaat níét rechtstreeks
 *    inloggen op een GET: mailscanners (Outlook Safe Links, virusscanners) bezoeken
 *    links vooraf, en dan is het token op vóórdat de sollicitant klikt. De link opent
 *    een pagina met één knop; die knop post. Eén klik in plaats van zes stappen.
 *
 * 2. BLIJF INGELOGD op dit apparaat, met een apart token in een eigen cookie.
 *    Selector + verifier: de selector zoekt de rij, de verifier wordt gehasht
 *    vergeleken. Wie de database steelt heeft daar niets aan, en het vergelijken is
 *    niet tijdgevoelig. Het token rouleert bij elk gebruik, zodat een gekopieerd
 *    token opvalt en maar één keer werkt.
 *
 * Wat hier NIET achter zit: gevoelige stukken. BSN, IBAN en identiteitsbewijzen zitten
 * in de kluis achter het beheer, niet in het portaal. Wat een langdurig ingelogde
 * telefoon toont zijn de eigen gegevens van de sollicitant.
 */

if (!function_exists('portaal_login_setup')) {

/* define() en geen const: const mag in PHP niet binnen een if-blok staan, en dit
   bestand zit in een function_exists-haak. */
/** Bewaartermijn van "blijf ingelogd", in dagen. */
define('PORTAAL_ONTHOUD_DAGEN', 60);
/** Naam van de cookie. Los van de sessiecookie: die mag korter leven. */
define('PORTAAL_ONTHOUD_COOKIE', 'p13_apparaat');

function portaal_login_setup(): void {
    static $gedaan = false;
    if ($gedaan || schema_actueel()) return;
    $gedaan = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `portaal_apparaten` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `email` VARCHAR(200) NOT NULL,
            `selector` CHAR(32) NOT NULL,
            `verifier_hash` CHAR(64) NOT NULL,
            `browser` VARCHAR(255) DEFAULT NULL,
            `ip` VARCHAR(45) DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `laatst_gebruikt_op` DATETIME DEFAULT NULL,
            `vervalt_op` DATETIME NOT NULL,
            UNIQUE KEY `selector` (`selector`),
            KEY `sol` (`sollicitatie_id`),
            KEY `verval` (`vervalt_op`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {
        error_log('portaal_login_setup: ' . $e->getMessage());
    }
}

/* ─────────────────────────────────────────────────────────────
   Inloglink
   ───────────────────────────────────────────────────────────── */

/**
 * Maakt een eenmalige inloglink. Gebruikt dezelfde otp_tokens-tabel als de code —
 * zelfde vervaltijd, zelfde eenmaligheid — met een eigen type, zodat een code nooit
 * als link werkt en andersom.
 *
 * @return string de volledige URL
 */
function portaal_inloglink_maken(string $email): string {
    $token = bin2hex(random_bytes(32));
    db()->prepare("DELETE FROM otp_tokens WHERE email = ? AND type = 'portaal_link'")->execute([$email]);
    db()->prepare("INSERT INTO otp_tokens (email, code_hash, type, vervalt_op)
                   VALUES (?, ?, 'portaal_link', DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
       ->execute([$email, hash('sha256', $token)]);
    return APP_URL . '/portaal/?inlog=' . $token;
}

/**
 * Hoort dit token bij een geldige, ongebruikte inloglink? Geeft het e-mailadres terug.
 *
 * $verbruik=false kijkt alleen: dat is wat de GET doet, zodat het openen van de link
 * hem nog niet opmaakt. Pas de knop (POST) verbruikt hem.
 */
function portaal_inloglink_controleren(string $token, bool $verbruik = false): ?string {
    $token = trim($token);
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    try {
        $q = db()->prepare("SELECT id, email FROM otp_tokens
                            WHERE code_hash = ? AND type = 'portaal_link'
                              AND gebruikt = 0 AND vervalt_op > NOW() LIMIT 1");
        $q->execute([hash('sha256', $token)]);
        $rij = $q->fetch();
        if (!$rij) return null;
        if ($verbruik) {
            db()->prepare("UPDATE otp_tokens SET gebruikt = 1 WHERE id = ?")->execute([$rij['id']]);
        }
        return (string)$rij['email'];
    } catch (\Throwable $e) {
        error_log('portaal_inloglink_controleren: ' . $e->getMessage());
        return null;
    }
}

/* ─────────────────────────────────────────────────────────────
   Blijf ingelogd op dit apparaat
   ───────────────────────────────────────────────────────────── */

/** Onthoudt dit apparaat. Roept dit alleen aan als de sollicitant erom vroeg. */
function portaal_onthoud_zetten(int $sol_id, string $email): void {
    portaal_login_setup();
    $selector = bin2hex(random_bytes(16));
    $verifier = bin2hex(random_bytes(32));
    try {
        db()->prepare("INSERT INTO portaal_apparaten
                       (sollicitatie_id, email, selector, verifier_hash, browser, ip, vervalt_op)
                       VALUES (?,?,?,?,?,?, DATE_ADD(NOW(), INTERVAL " . PORTAAL_ONTHOUD_DAGEN . " DAY))")
           ->execute([
               $sol_id, $email, $selector, hash('sha256', $verifier),
               mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
               (string)($_SERVER['REMOTE_ADDR'] ?? ''),
           ]);
    } catch (\Throwable $e) {
        error_log('portaal_onthoud_zetten: ' . $e->getMessage());
        return;
    }
    portaal_onthoud_cookie($selector . ':' . $verifier);
}

/** De cookie schrijven (of wissen met een lege waarde). */
function portaal_onthoud_cookie(string $waarde): void {
    if (headers_sent()) return;
    setcookie(PORTAAL_ONTHOUD_COOKIE, $waarde, [
        'expires'  => $waarde === '' ? time() - 3600 : time() + PORTAAL_ONTHOUD_DAGEN * 86400,
        'path'     => '/',
        'secure'   => function_exists('is_https') ? is_https() : true,
        'httponly' => true,
        // Lax en niet Strict: anders is de sollicitant bij het volgen van de link uit
        // zijn eigen mail alsnog uitgelogd.
        'samesite' => 'Lax',
    ]);
}

/**
 * Staat er een geldig apparaat-token? Zo ja: log in en rouleer het token.
 *
 * Rouleren is de bescherming tegen een gekopieerd token: het oude werkt daarna niet
 * meer. Wordt het toch nog een keer aangeboden, dan is dat een teken van diefstal en
 * verdwijnen alle tokens van deze sollicitant.
 *
 * @return bool of er is ingelogd
 */
function portaal_onthoud_controleren(): bool {
    $rauw = (string)($_COOKIE[PORTAAL_ONTHOUD_COOKIE] ?? '');
    if ($rauw === '' || !str_contains($rauw, ':')) return false;

    $delen = explode(':', $rauw, 2);
    $selector = $delen[0];
    $verifier = $delen[1] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $verifier)) {
        portaal_onthoud_cookie('');
        return false;
    }

    portaal_login_setup();
    try {
        $q = db()->prepare("SELECT * FROM portaal_apparaten WHERE selector = ? LIMIT 1");
        $q->execute([$selector]);
        $rij = $q->fetch();
    } catch (\Throwable $e) {
        error_log('portaal_onthoud_controleren: ' . $e->getMessage());
        return false;
    }
    if (!$rij) { portaal_onthoud_cookie(''); return false; }

    if (!hash_equals((string)$rij['verifier_hash'], hash('sha256', $verifier))) {
        /* De selector bestaat maar de verifier klopt niet: iemand gebruikt een oud of
           geraden token. Alles van deze sollicitant intrekken en de cookie wissen — dan
           moet er opnieuw worden ingelogd via de mail, en dat merkt de eigenaar. */
        error_log('portaal_onthoud: verifier klopt niet voor sollicitatie ' . (int)$rij['sollicitatie_id']);
        portaal_onthoud_intrekken_alle((int)$rij['sollicitatie_id']);
        portaal_onthoud_cookie('');
        return false;
    }
    if (strtotime((string)$rij['vervalt_op']) < time()) {
        portaal_onthoud_verwijder((int)$rij['id']);
        portaal_onthoud_cookie('');
        return false;
    }

    // Bestaat de sollicitatie nog? (verwijderd dossier, ander seizoen)
    try {
        $c = db()->prepare("SELECT 1 FROM sollicitaties WHERE id = ?");
        $c->execute([(int)$rij['sollicitatie_id']]);
        if (!$c->fetchColumn()) { portaal_onthoud_verwijder((int)$rij['id']); portaal_onthoud_cookie(''); return false; }
    } catch (\Throwable $e) { return false; }

    // Rouleren: nieuw geheim, zelfde rij, vervaldatum opnieuw vanaf nu.
    $nieuw = bin2hex(random_bytes(32));
    try {
        db()->prepare("UPDATE portaal_apparaten
                       SET verifier_hash = ?, laatst_gebruikt_op = NOW(),
                           vervalt_op = DATE_ADD(NOW(), INTERVAL " . PORTAAL_ONTHOUD_DAGEN . " DAY)
                       WHERE id = ?")
           ->execute([hash('sha256', $nieuw), (int)$rij['id']]);
    } catch (\Throwable $e) { return false; }

    portaal_onthoud_cookie($selector . ':' . $nieuw);
    login_portaal((int)$rij['sollicitatie_id'], (string)$rij['email']);
    $_SESSION['portaal_via_apparaat'] = true;
    return true;
}

function portaal_onthoud_verwijder(int $id): void {
    try { db()->prepare("DELETE FROM portaal_apparaten WHERE id = ?")->execute([$id]); }
    catch (\Throwable $e) {}
}

/**
 * Alle apparaten van één sollicitant vergeten (uitloggen-overal, diefstal, AVG): de onthouden
 * tokens én de open sessies elders (portaal_sessies_beeindigen()).
 */
function portaal_onthoud_intrekken_alle(int $sol_id): void {
    portaal_login_setup();
    try { db()->prepare("DELETE FROM portaal_apparaten WHERE sollicitatie_id = ?")->execute([$sol_id]); }
    catch (\Throwable $e) {}
    portaal_sessies_beeindigen($sol_id);
}

// ── Sessiegeneratie (2.145.0) ───────────────────────────────────────────
// sollicitaties.portaal_sessie_gen telt op bij "overal uitloggen". Elke sessie onthoudt de
// generatie van het moment van inloggen (login_portaal()); wijkt die af, dan is hij ongeldig.
// Kost één opzoeking op de primaire sleutel per verzoek (portaal_ingelogd()).

/** De huidige generatie; -1 = sollicitatie bestaat niet (meer); 0 als de kolom (nog) ontbreekt. */
function portaal_sessie_gen(int $sol_id): int {
    try {
        $q = db()->prepare("SELECT portaal_sessie_gen FROM sollicitaties WHERE id = ?");
        $q->execute([$sol_id]);
        $g = $q->fetchColumn();
        return $g === false ? -1 : (int)$g;
    } catch (\Throwable $e) { return 0; }
}

/** Hoort deze sessie nog bij de huidige generatie? Zo niet: de portaalsessie leeg maken. */
function portaal_sessie_geldig(): bool {
    $sol_id = (int)($_SESSION['portaal_sol_id'] ?? 0);
    if ($sol_id > 0 && portaal_sessie_gen($sol_id) === (int)($_SESSION['portaal_gen'] ?? 0)) return true;
    unset($_SESSION['portaal_sol_id'], $_SESSION['portaal_email'], $_SESSION['portaal_via_apparaat'], $_SESSION['portaal_gen']);
    return false;
}

/** Alle open sessies van deze sollicitant beëindigen. De eigen sessie blijft (die doet het verzoek). */
function portaal_sessies_beeindigen(int $sol_id): void {
    try { db()->prepare("UPDATE sollicitaties SET portaal_sessie_gen = portaal_sessie_gen + 1 WHERE id = ?")->execute([$sol_id]); }
    catch (\Throwable $e) { error_log('portaal_sessies_beeindigen: ' . $e->getMessage()); return; }
    if ((int)($_SESSION['portaal_sol_id'] ?? 0) === $sol_id) $_SESSION['portaal_gen'] = portaal_sessie_gen($sol_id);
}

/** Uitloggen op dit apparaat: het onthouden token en de portaalsessie. Alleen via POST (portaal/index.php). */
function portaal_uitloggen(): void {
    portaal_onthoud_intrekken_dit();
    unset($_SESSION['portaal_sol_id'], $_SESSION['portaal_email'], $_SESSION['portaal_via_apparaat'], $_SESSION['portaal_gen']);
    if (function_exists('session_regenerate_id') && session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
}

/**
 * De uitlogknop: een formulier dat post (met csrf), geen link. Een link kan een andere site of een
 * voorvertoning ongemerkt laten volgen. $tekst leeg = alleen het icoon (met aria-label).
 * De klasse staat op de knop; .pt-uitlog maakt het formulier onzichtbaar in de opmaak.
 */
function portaal_uitlog_knop(string $klasse, int $icoon = 18, string $tekst = ''): string {
    return '<form method="post" action="' . h(APP_URL . '/portaal/') . '" class="pt-uitlog">'
         . csrf_input() . '<input type="hidden" name="uitloggen" value="1">'
         . '<button type="submit" class="' . h($klasse) . '"' . ($tekst === '' ? ' aria-label="Uitloggen"' : '') . '>'
         . icoon('uitloggen', $icoon) . ($tekst !== '' ? ' ' . h($tekst) : '') . '</button></form>';
}

/** Dit ene apparaat vergeten. Hoort bij uitloggen. */
function portaal_onthoud_intrekken_dit(): void {
    $rauw = (string)($_COOKIE[PORTAAL_ONTHOUD_COOKIE] ?? '');
    if ($rauw !== '' && str_contains($rauw, ':')) {
        $selector = explode(':', $rauw, 2)[0];
        if (preg_match('/^[a-f0-9]{32}$/', $selector)) {
            portaal_login_setup();
            try { db()->prepare("DELETE FROM portaal_apparaten WHERE selector = ?")->execute([$selector]); }
            catch (\Throwable $e) {}
        }
    }
    portaal_onthoud_cookie('');
}

/** Hoeveel apparaten onthouden deze sollicitant nu? (voor Mijn gegevens) */
function portaal_onthoud_aantal(int $sol_id): int {
    portaal_login_setup();
    try {
        $q = db()->prepare("SELECT COUNT(*) FROM portaal_apparaten WHERE sollicitatie_id = ? AND vervalt_op > NOW()");
        $q->execute([$sol_id]);
        return (int)$q->fetchColumn();
    } catch (\Throwable $e) { return 0; }
}

/** Verlopen tokens opruimen. Wordt door de cron aangeroepen. */
function portaal_onthoud_opschonen(): int {
    portaal_login_setup();
    try {
        $st = db()->prepare("DELETE FROM portaal_apparaten WHERE vervalt_op < NOW()");
        $st->execute();
        return $st->rowCount();
    } catch (\Throwable $e) { return 0; }
}

} // function_exists

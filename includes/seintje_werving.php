<?php
/**
 * Paal 13 — Seintje buiten het seizoen: "laat je e-mailadres achter, dan sturen we je één
 * bericht zodra je je kunt aanmelden."
 *
 * Staat de aanmelding dicht, dan was een wervingspagina of het aanmeldformulier een dood einde.
 * Nu kan een bezoeker om één bericht vragen. Dit bestand is de logica (geen HTML); het
 * formulier staat in aanmeld_gesloten_html() (includes/aanmeldformulier.php), de pagina's in
 * /aanmelden.php (eigen formulier, met csrf) en /seintje.php (aanvraag vanaf een wervingspagina
 * of paal13.com, bevestigen, intrekken).
 *
 * Waarom een eigen tabel en niet personen.seintje_op (includes/seintje.php)?
 *   Dat seintje hoort bij iemand die al solliciteerde: de persoon bestaat, met adres,
 *   afmeld-token en dossier. Deze mensen hebben niets van dat alles. Een lege personen-rij
 *   zou in elke telling, elke lijst en elk opschoonpad meelopen als "sollicitant zonder
 *   sollicitatie". Een kleine tabel met alleen voornaam, e-mail en bron is minimale
 *   gegevensverwerking (AVG art. 5 lid 1c) en ruimt zichzelf op.
 *
 * Vier regels:
 *   1. Dubbele bevestiging (double opt-in). Iedereen kan elk adres invullen; pas na de klik
 *      in de mail staat het seintje aan (AVG art. 7, Telecommunicatiewet art. 11.7).
 *   2. Bevestigen gebeurt nooit op een GET: mailscanners openen links vooraf. De link toont
 *      een knop die post (zelfde aanpak als de inloglink in includes/portaal_login.php).
 *   3. Het token staat alleen als sha256-hash in de database.
 *   4. Het antwoord op het scherm is altijd hetzelfde: het verraadt nooit of een adres al
 *      bekend is. Dubbel aanvragen geeft geen tweede rij, hoogstens een nieuwe bevestigingsmail
 *      (maximaal drie per adres per dag).
 *
 * Opruimen: onbevestigd na seintje_werving_bevestig_dagen() dagen weg (opschoon_vervallen,
 * altijd); na het openingsbericht of na seintje_termijn_mnd() maanden weg (opschoon_run, alleen
 * met bewaar.actief).
 */

if (!function_exists('werving_seintje_auto_setup')) {

/** De tabel. Via db_failsafe() één keer per versie. */
function werving_seintje_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `werving_seintje` (
            `id`             INT AUTO_INCREMENT PRIMARY KEY,
            `voornaam`       VARCHAR(80)  NOT NULL,
            `email`          VARCHAR(190) NOT NULL,
            `token_hash`     CHAR(64)     DEFAULT NULL,
            `bron`           VARCHAR(80)  DEFAULT NULL,
            `aangevraagd_op` DATETIME     NOT NULL,
            `bevestigd_op`   DATETIME     DEFAULT NULL,
            `verstuurd_op`   DATETIME     DEFAULT NULL,
            UNIQUE KEY `uniek_email` (`email`),
            KEY `idx_token` (`token_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) { error_log('werving_seintje_auto_setup: ' . $e->getMessage()); }
}

/** Zo lang is de bevestigingslink geldig; daarna verdwijnt de aanvraag. */
function seintje_werving_bevestig_dagen(): int { return 7; }

/** Waar het anti-bot-token voor ondertekend wordt (includes/paginas/token.php). Eigen naam:
 *  een token van het aanmeldformulier werkt hier niet, en andersom. */
function seintje_werving_token_bron(): string { return 'werving-seintje'; }

function seintje_werving_nu(): string { return date('Y-m-d H:i:s'); }

function seintje_werving_hash(string $token): string { return hash('sha256', $token); }

/** De pagina achter de mail: bevestigen, en later ook intrekken. */
function seintje_werving_link(string $token): string {
    return (defined('APP_URL') ? APP_URL : '') . '/seintje.php?t=' . rawurlencode($token);
}

/** Bron (pagina/campagne) alleen voor de statistiek: kort en schoon, nooit vertrouwd. */
function seintje_werving_bron_schoon(string $bron): string {
    return substr((string)preg_replace('/[^a-z0-9_\-]/', '', strtolower($bron)), 0, 80);
}

/** Welk veld hoort bij een foutcode? '' = melding bovenaan. */
function seintje_werving_fout_veld(string $code): string {
    return ['voornaam_leeg' => 'voornaam', 'email_ongeldig' => 'email', 'geen_akkoord' => 'seintje_akkoord'][$code] ?? '';
}

/**
 * Een aanvraag verwerken. Volgorde zoals bij het aanmeldformulier:
 *   anti-bot-token (tenzij de aanroeper csrf al controleerde) → honeypot → snelheid per IP →
 *   invoer → opslaan → bevestigingsmail (met een eigen grens per adres).
 *
 * @param array $post  voornaam, email, seintje_akkoord, p13_w, p13_t, bron_code
 * @param array $ctx   'vertrouwd' => true als de aanroeper de csrf al heeft gecontroleerd
 * @return array ['ok'=>bool, 'code'=>string, 'fout'=>string]
 */
function seintje_werving_aanvragen(array $post, array $ctx = []): array {
    $weiger = fn(string $code, string $fout): array => ['ok' => false, 'code' => $code, 'fout' => $fout];

    if (empty($ctx['vertrouwd'])) {
        $t = function_exists('pag_token_check')
            ? pag_token_check((string)($post['p13_t'] ?? ''), seintje_werving_token_bron(), 2, 7200)
            : ['ok' => true, 'reden' => ''];
        if (!$t['ok']) {
            return $weiger('token', 'Controleer je gegevens en verstuur het formulier nog een keer.');
        }
    }
    if (trim((string)($post['p13_w'] ?? '')) !== '') {
        return $weiger('honeypot', 'Het formulier kon niet worden verwerkt. Ververs de pagina en probeer het opnieuw.');
    }
    if (function_exists('rate_limit') && !rate_limit('seintje_aanvraag', rate_identiteit(), 10, 3600)) {
        return $weiger('rate_limit', 'Er zijn in korte tijd te veel aanvragen vanaf dit adres gedaan. Probeer het over een uur opnieuw.');
    }

    $voornaam = trim((string)preg_replace('/\s+/u', ' ', strip_tags((string)($post['voornaam'] ?? ''))));
    $email    = strtolower(trim((string)($post['email'] ?? '')));
    if ($voornaam === '') {
        return $weiger('voornaam_leeg', 'Vul je voornaam in.');
    }
    if (mb_strlen($voornaam) > 80) $voornaam = mb_substr($voornaam, 0, 80);
    if ($email === '' || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $weiger('email_ongeldig', 'Dit lijkt geen geldig e-mailadres. Controleer of er een @ en een punt in staan.');
    }
    if (empty($post['seintje_akkoord'])) {
        return $weiger('geen_akkoord', 'Zet het vinkje: zonder jouw toestemming mogen we je geen bericht sturen.');
    }

    // Een nieuwe bevestigingsmail, maar niet onbeperkt: anders is dit formulier een manier om
    // iemand anders te bestoken. Boven de grens raken we de rij niet aan (dan blijft de link
    // uit de laatste mail geldig) en krijgt de bezoeker hetzelfde antwoord.
    if (function_exists('rate_limit') && !rate_limit('seintje_mail', hash('sha256', $email), 3, 86400)) {
        return ['ok' => true, 'code' => '', 'fout' => ''];
    }

    $bron = seintje_werving_bron_schoon((string)($post['bron_code'] ?? ''));
    $opslag = seintje_werving_opslaan($voornaam, $email, $bron);
    if ($opslag === null) {
        return $weiger('opslag', 'Het lukte even niet om je aanvraag op te slaan. Probeer het later nog eens.');
    }
    if ($opslag['token'] !== '') seintje_werving_bevestigmail($email, $voornaam, $opslag['token']);
    return ['ok' => true, 'code' => '', 'fout' => ''];
}

/**
 * Opslaan of bijwerken. Eén rij per e-mailadres.
 *   nieuw adres                   → rij + token
 *   onbevestigd (of verlopen)     → naam/bron bijwerken, nieuw token (de oude link vervalt)
 *   al verstuurd of termijn om    → opnieuw beginnen: onbevestigd, nieuw token
 *   bevestigd en nog wachtend     → niets; geen mail (het seintje staat al aan)
 *
 * @return ?array ['id'=>int, 'token'=>string] — token '' = geen mail nodig; null bij een fout
 */
function seintje_werving_opslaan(string $voornaam, string $email, string $bron): ?array {
    werving_seintje_auto_setup();
    try {
        $db = db();
        $q = $db->prepare("SELECT id, bevestigd_op, verstuurd_op FROM werving_seintje WHERE email = ?");
        $q->execute([$email]);
        $rij = $q->fetch();
        $nu = seintje_werving_nu();

        $mnd = function_exists('seintje_termijn_mnd') ? seintje_termijn_mnd() : 12;
        $grens = date('Y-m-d H:i:s', strtotime('-' . $mnd . ' months'));
        if ($rij && $rij['bevestigd_op'] !== null && $rij['verstuurd_op'] === null && (string)$rij['bevestigd_op'] >= $grens) {
            return ['id' => (int)$rij['id'], 'token' => ''];
        }

        $token = bin2hex(random_bytes(24));
        if ($rij) {
            $db->prepare("UPDATE werving_seintje SET voornaam = ?, bron = ?, token_hash = ?, aangevraagd_op = ?,
                                 bevestigd_op = NULL, verstuurd_op = NULL WHERE id = ?")
               ->execute([$voornaam, $bron !== '' ? $bron : null, seintje_werving_hash($token), $nu, (int)$rij['id']]);
            return ['id' => (int)$rij['id'], 'token' => $token];
        }
        $db->prepare("INSERT INTO werving_seintje (voornaam, email, token_hash, bron, aangevraagd_op) VALUES (?,?,?,?,?)")
           ->execute([$voornaam, $email, seintje_werving_hash($token), $bron !== '' ? $bron : null, $nu]);
        return ['id' => (int)$db->lastInsertId(), 'token' => $token];
    } catch (\Throwable $e) {
        error_log('seintje_werving_opslaan: ' . $e->getMessage());
        return null;
    }
}

/** De bevestigingsmail, ná het antwoord (de bezoeker wacht niet op de mailserver). */
function seintje_werving_bevestigmail(string $email, string $voornaam, string $token): void {
    if (!function_exists('melding_verstuur')) return;
    melding_verstuur('werving_seintje_bevestigen', 0, [
        'voornaam'       => $voornaam,
        'bevestig_url'   => seintje_werving_link($token),
        'bevestig_dagen' => (string)seintje_werving_bevestig_dagen(),
    ], [
        'ontvanger'   => ['email' => $email, 'naam' => $voornaam, 'telefoon' => '', 'rol' => 'seintje'],
        'kanaalmodus' => 'email',
        'prioriteit'  => 'na_antwoord',
        'dedup_min'   => 0,
    ]);
}

/**
 * De aanvraag achter een token, met de stand:
 *   'onbevestigd' — de knop "Bevestig" mag getoond worden
 *   'verlopen'    — onbevestigd en ouder dan seintje_werving_bevestig_dagen()
 *   'actief'      — bevestigd, wacht op de werving
 *   'verstuurd'   — het openingsbericht is al gestuurd
 * @return ?array rij + 'stand', of null als het token niet bestaat
 */
function seintje_werving_via_token(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
    werving_seintje_auto_setup();
    try {
        $q = db()->prepare("SELECT id, voornaam, email, aangevraagd_op, bevestigd_op, verstuurd_op FROM werving_seintje WHERE token_hash = ?");
        $q->execute([seintje_werving_hash($token)]);
        $rij = $q->fetch();
        if (!$rij) return null;
    } catch (\Throwable $e) { return null; }

    $grens = date('Y-m-d H:i:s', strtotime('-' . seintje_werving_bevestig_dagen() . ' days'));
    if ($rij['verstuurd_op'] !== null)          $rij['stand'] = 'verstuurd';
    elseif ($rij['bevestigd_op'] !== null)      $rij['stand'] = 'actief';
    elseif ((string)$rij['aangevraagd_op'] < $grens) $rij['stand'] = 'verlopen';
    else                                        $rij['stand'] = 'onbevestigd';
    return $rij;
}

/** Bevestigen (alleen via POST aanroepen). True als het seintje nu aan staat. */
function seintje_werving_bevestigen(string $token): bool {
    $rij = seintje_werving_via_token($token);
    if (!$rij) return false;
    if ($rij['stand'] === 'actief') return true;
    if ($rij['stand'] !== 'onbevestigd') return false;
    try {
        db()->prepare("UPDATE werving_seintje SET bevestigd_op = ? WHERE id = ? AND bevestigd_op IS NULL")
            ->execute([seintje_werving_nu(), (int)$rij['id']]);
        if (function_exists('audit_log')) audit_log('werving_seintje_bevestigd', 'werving_seintje', (int)$rij['id'], []);
        return true;
    } catch (\Throwable $e) { error_log('seintje_werving_bevestigen: ' . $e->getMessage()); return false; }
}

/** Intrekken = de rij weg (AVG art. 7 lid 3). Ook voor een onbevestigde aanvraag. */
function seintje_werving_intrekken(string $token): bool {
    $rij = seintje_werving_via_token($token);
    if (!$rij) return false;
    try {
        db()->prepare("DELETE FROM werving_seintje WHERE id = ?")->execute([(int)$rij['id']]);
        if (function_exists('audit_log')) audit_log('werving_seintje_ingetrokken', 'werving_seintje', (int)$rij['id'], []);
        return true;
    } catch (\Throwable $e) { error_log('seintje_werving_intrekken: ' . $e->getMessage()); return false; }
}

/**
 * Wat /seintje.php doet en toont, zonder HTML (de pagina tekent de 'stand').
 *
 *   POST p13_seintje      aanvraag (anti-bot-token); ok → redirect ?aangevraagd=1
 *   POST actie + t        bevestigen of intrekken; ok → redirect
 *   GET  ?t=              alleen tonen: niets verandert (mailscanners)
 *
 * @return array redirect: ?array (query voor /seintje.php) · http: int · stand: string ·
 *               rij: ?array · klaar: string · aanvraag: ['fout','code'] na een mislukte aanvraag
 * Standen: te_vaak, ingetrokken, aangevraagd, onbekend, verlopen, onbevestigd, actief,
 *          verstuurd, open, formulier
 */
function seintje_werving_scherm(string $methode, array $get, array $post, bool $open): array {
    $uit = ['redirect' => null, 'http' => 200, 'stand' => 'formulier', 'rij' => null, 'klaar' => (string)($get['klaar'] ?? ''), 'aanvraag' => null];
    $token = (string)($post['t'] ?? $get['t'] ?? '');
    $token = preg_match('/^[a-f0-9]{48}$/', $token) ? $token : '';

    if ($methode === 'POST' && isset($post['p13_seintje']) && !$open) {
        $res = seintje_werving_aanvragen($post);
        if ($res['ok']) { $uit['redirect'] = ['aangevraagd' => 1]; return $uit; }
        $uit['http'] = 422;
        $uit['aanvraag'] = ['fout' => $res['fout'], 'code' => $res['code']];
        return $uit;
    }

    if ($methode === 'POST' && isset($post['actie'])) {
        if (function_exists('rate_limit') && !rate_limit('seintje_token', rate_identiteit(), 20, 3600)) {
            $uit['http'] = 429; $uit['stand'] = 'te_vaak';
            return $uit;
        }
        if ($post['actie'] === 'bevestigen' && $token !== '' && seintje_werving_bevestigen($token)) {
            $uit['redirect'] = ['t' => $token, 'klaar' => 'bevestigd']; return $uit;
        }
        if ($post['actie'] === 'intrekken' && $token !== '' && seintje_werving_intrekken($token)) {
            $uit['redirect'] = ['klaar' => 'ingetrokken']; return $uit;
        }
        // Mislukt (verlopen, onbekend): gewoon tonen wat er bij dit token hoort.
    }

    if ($uit['klaar'] === 'ingetrokken')                 { $uit['stand'] = 'ingetrokken'; return $uit; }
    if ($methode === 'GET' && isset($get['aangevraagd'])) { $uit['stand'] = 'aangevraagd'; return $uit; }
    if ($token !== '') {
        $uit['rij'] = seintje_werving_via_token($token);
        $uit['stand'] = $uit['rij'] ? $uit['rij']['stand'] : 'onbekend';
        return $uit;
    }
    $uit['stand'] = $open ? 'open' : 'formulier';
    return $uit;
}

/**
 * Hoeveel bevestigde seintjes (van de website, buiten het seizoen aangevraagd) wachten nog op
 * het openingsbericht? Binnen seintje_termijn_mnd(). Zonder de personen-seintjes van afgewezen
 * sollicitanten: die staan in Berichten in dezelfde groep, maar hebben geen "verstuurd"-stand.
 */
function seintje_wachtend_aantal(): int {
    werving_seintje_auto_setup();
    try {
        $mnd = function_exists('seintje_termijn_mnd') ? seintje_termijn_mnd() : 12;
        $q = db()->prepare("SELECT COUNT(*) FROM werving_seintje
                             WHERE bevestigd_op IS NOT NULL AND verstuurd_op IS NULL AND bevestigd_op >= ?");
        $q->execute([date('Y-m-d H:i:s', strtotime('-' . $mnd . ' months'))]);
        return (int)$q->fetchColumn();
    } catch (\Throwable $e) { return 0; }
}

/**
 * De bevestigde, wachtende seintjes als ontvangers voor Berichten, in het formaat van
 * bh_vorige_seizoenen(). sol_id is NEGATIEF (-id): zo gaan ze mee in dezelfde ontvangerslijst
 * zonder botsing met sollicitatie-id's; seintje_werving_verstuur() handelt ze af.
 *
 * Ontdubbeld: een adres dat al in $bekende_emails staat (personen-seintje of oud-strandwacht),
 * of van iemand die zich dit seizoen al heeft aangemeld, komt er niet in.
 */
function seintje_werving_doelgroep(int $huidig_seizoen_id, array $bekende_emails = []): array {
    werving_seintje_auto_setup();
    $bekend = [];
    foreach ($bekende_emails as $e) $bekend[strtolower(trim((string)$e))] = true;
    try {
        $mnd = function_exists('seintje_termijn_mnd') ? seintje_termijn_mnd() : 12;
        $q = db()->prepare("SELECT id, voornaam, email, bron, bevestigd_op FROM werving_seintje
                             WHERE bevestigd_op IS NOT NULL AND verstuurd_op IS NULL AND bevestigd_op >= ?
                             ORDER BY voornaam, id");
        $q->execute([date('Y-m-d H:i:s', strtotime('-' . $mnd . ' months'))]);
        $rijen = $q->fetchAll();
        if ($huidig_seizoen_id > 0 && $rijen) {
            $a = db()->prepare("SELECT LOWER(p.email) AS email FROM personen p JOIN sollicitaties s ON s.persoon_id = p.id WHERE s.seizoen_id = ?");
            $a->execute([$huidig_seizoen_id]);
            foreach ($a->fetchAll() as $r) $bekend[strtolower(trim((string)$r['email']))] = true;
        }
    } catch (\Throwable $e) { error_log('seintje_werving_doelgroep: ' . $e->getMessage()); return []; }

    $uit = [];
    foreach ($rijen as $r) {
        $email = strtolower(trim((string)$r['email']));
        if (isset($bekend[$email])) continue;
        $bekend[$email] = true;
        $uit[] = [
            'sol_id' => -(int)$r['id'], 'persoon_id' => 0, 'werving_seintje_id' => (int)$r['id'],
            'voornaam' => (string)$r['voornaam'], 'tussenvoegsel' => '', 'achternaam' => '',
            'email' => $email, 'telefoon' => '', 'kanaal_voorkeur' => 'email',
            'groep' => 'seintje', 'laatste_seizoen' => null, 'bron' => (string)($r['bron'] ?? ''),
        ];
    }
    return $uit;
}

/** De voet onder het openingsbericht: waarom je dit krijgt, en de afmeldlink. */
function seintje_werving_voet_html(string $url): string {
    return '<p style="font-size:11px;color:#999;margin-top:24px;border-top:1px solid #eee;padding-top:12px">'
         . 'Je krijgt dit ene bericht omdat je om een seintje vroeg toen de aanmelding nog dicht was. '
         . 'We sturen je hierna niets meer en verwijderen je gegevens. '
         . '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="color:#999">Afmelden en mijn gegevens nu verwijderen</a>.</p>';
}

/**
 * Het openingsbericht aan één werving-seintje (vanuit Berichten). Altijd e-mail: we hebben
 * geen telefoonnummer. Krijgt een nieuw token voor de afmeldlink en wordt gemarkeerd als
 * verstuurd zodra het bericht in de wachtrij of de deur uit is; opnieuw versturen kan dan niet.
 *
 * @param array $inhoud  zoals bij 'handmatig': onderwerp, body_html, tekst_kort (+ seizoen_naam)
 * @param array $opties  override, prioriteit, referentie, bijlagen (kanaal en ontvanger zetten we zelf)
 * @return array uitslag van melding_verstuur()
 */
function seintje_werving_verstuur(int $id, array $inhoud, array $opties = []): array {
    $overgeslagen = ['status' => 'overgeslagen', 'reden' => 'niet meer actief', 'log_id' => null, 'kanaal' => null, 'items' => []];
    werving_seintje_auto_setup();
    try {
        $q = db()->prepare("SELECT id, voornaam, email FROM werving_seintje WHERE id = ? AND bevestigd_op IS NOT NULL AND verstuurd_op IS NULL");
        $q->execute([$id]);
        $rij = $q->fetch();
        if (!$rij) return $overgeslagen;

        $token = bin2hex(random_bytes(24));
        db()->prepare("UPDATE werving_seintje SET token_hash = ? WHERE id = ?")->execute([seintje_werving_hash($token), $id]);
        $url = seintje_werving_link($token);

        $naam = (string)$rij['voornaam'];
        $r = melding_verstuur('handmatig', 0, $inhoud + [
            'voornaam' => $naam, 'achternaam' => '', 'volledige_naam' => $naam, 'afmeld_url' => $url,
        ], array_merge($opties, [
            'ontvanger'     => ['email' => (string)$rij['email'], 'naam' => $naam, 'telefoon' => '', 'rol' => 'seintje'],
            'kanaalmodus'   => 'email',
            'voetnoot_html' => seintje_werving_voet_html($url),
        ]));
        if (in_array($r['status'] ?? '', ['verstuurd', 'wacht'], true)) {
            db()->prepare("UPDATE werving_seintje SET verstuurd_op = ? WHERE id = ?")->execute([seintje_werving_nu(), $id]);
        }
        return $r;
    } catch (\Throwable $e) {
        error_log('seintje_werving_verstuur: ' . $e->getMessage());
        return ['status' => 'mislukt', 'reden' => $e->getMessage(), 'log_id' => null, 'kanaal' => null, 'items' => []];
    }
}

/**
 * Opruimen.
 *   $alleen_onbevestigd = true  → alleen aanvragen die na seintje_werving_bevestig_dagen() nog
 *                                 niet bevestigd zijn. Dat is geen bewaartermijn maar een
 *                                 verlopen link, dus los van bewaar.actief (opschoon_vervallen).
 *   $alleen_onbevestigd = false → ook: het openingsbericht is verstuurd (een dag marge, zodat
 *                                 de afmeldlink de eerste dag nog iets zegt), of de toestemming
 *                                 is ouder dan seintje_termijn_mnd() (opschoon_run, bewaar.actief).
 * @return array ['onbevestigd' => n, 'afgerond' => n]
 */
function seintje_werving_opschonen(bool $alleen_onbevestigd, bool $dry_run = false): array {
    $uit = ['onbevestigd' => 0, 'afgerond' => 0];
    werving_seintje_auto_setup();
    $db = db();
    $tel_of_wis = function (string $waar, array $p) use ($db, $dry_run): int {
        if ($dry_run) { $q = $db->prepare("SELECT COUNT(*) FROM werving_seintje WHERE $waar"); $q->execute($p); return (int)$q->fetchColumn(); }
        $q = $db->prepare("DELETE FROM werving_seintje WHERE $waar"); $q->execute($p); return $q->rowCount();
    };
    try {
        $uit['onbevestigd'] = $tel_of_wis("bevestigd_op IS NULL AND aangevraagd_op < ?",
            [date('Y-m-d H:i:s', strtotime('-' . seintje_werving_bevestig_dagen() . ' days'))]);
        if (!$alleen_onbevestigd) {
            $mnd = function_exists('seintje_termijn_mnd') ? seintje_termijn_mnd() : 12;
            $uit['afgerond'] = $tel_of_wis("(verstuurd_op IS NOT NULL AND verstuurd_op < ?) OR (bevestigd_op IS NOT NULL AND bevestigd_op < ?)",
                [date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('-' . $mnd . ' months'))]);
        }
    } catch (\Throwable $e) { error_log('seintje_werving_opschonen: ' . $e->getMessage()); }
    return $uit;
}

}

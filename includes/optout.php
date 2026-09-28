<?php
/**
 * Paal 13 — Afmelden voor mailings (opt-out).
 *
 * Voor nieuwsbrieven/mailings is een afmeldmogelijkheid wettelijk verplicht (AVG/Telecomwet).
 * Transactionele mail (aanmeldbevestiging, uitnodiging) valt daar niet onder en blijft altijd
 * doorgaan — een afmelding geldt alleen voor niet-essentiële mailings.
 *
 * Werking: elke persoon heeft een afmeld-token. De afmeldlink in een mailing bevat dat token.
 * Wie afmeldt, krijgt een vlag 'mailing_afgemeld'. De mailing slaat afgemelde mensen over.
 */

if (!function_exists('optout_auto_setup')) {

function optout_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
    try {
        $db = db();
        if (function_exists('db_kolom_verzeker')) {
            db_kolom_verzeker($db, 'personen', 'afmeld_token', 'VARCHAR(64) DEFAULT NULL');
            db_kolom_verzeker($db, 'personen', 'mailing_afgemeld', 'TINYINT(1) NOT NULL DEFAULT 0');
            db_kolom_verzeker($db, 'personen', 'mailing_afgemeld_op', 'DATETIME DEFAULT NULL');
        }
    } catch (\Exception $e) { error_log('optout_auto_setup: ' . $e->getMessage()); }
}

/** Haal (of maak) het afmeld-token voor een persoon. */
function optout_token(int $persoon_id): string {
    optout_auto_setup();
    try {
        $db = db();
        $q = $db->prepare("SELECT afmeld_token FROM personen WHERE id=?");
        $q->execute([$persoon_id]);
        $token = $q->fetchColumn();
        if (!$token) {
            $token = bin2hex(random_bytes(24));
            $db->prepare("UPDATE personen SET afmeld_token=? WHERE id=?")->execute([$token, $persoon_id]);
        }
        return $token;
    } catch (\Exception $e) { return ''; }
}

/** Is deze persoon afgemeld voor mailings? */
function optout_is_afgemeld(int $persoon_id): bool {
    optout_auto_setup();
    try {
        $q = db()->prepare("SELECT mailing_afgemeld FROM personen WHERE id=?");
        $q->execute([$persoon_id]);
        return (int)$q->fetchColumn() === 1;
    } catch (\Exception $e) { return false; }
}

/**
 * Wie hoort bij dit token, zonder iets te veranderen: ['voornaam', 'afgemeld' => bool] of null.
 * Voor de GET van de afmeldpagina: mailscanners openen links vooraf.
 */
function optout_persoon(string $token): ?array {
    optout_auto_setup();
    if ($token === '') return null;
    try {
        $q = db()->prepare("SELECT voornaam, mailing_afgemeld FROM personen WHERE afmeld_token=?");
        $q->execute([$token]);
        $p = $q->fetch();
        return $p ? ['voornaam' => (string)($p['voornaam'] ?? ''), 'afgemeld' => (int)$p['mailing_afgemeld'] === 1] : null;
    } catch (\Exception $e) { return null; }
}

/** Meld iemand af op basis van het token. Retourneert de naam bij succes, anders null. */
function optout_afmelden(string $token): ?string {
    optout_auto_setup();
    if ($token === '') return null;
    try {
        $db = db();
        $q = $db->prepare("SELECT id, voornaam FROM personen WHERE afmeld_token=?");
        $q->execute([$token]);
        $p = $q->fetch();
        if (!$p) return null;
        $db->prepare("UPDATE personen SET mailing_afgemeld=1, mailing_afgemeld_op=NOW() WHERE id=?")->execute([$p['id']]);
        if (function_exists('audit_log')) audit_log('afgemeld', 'persoon', (int)$p['id'], ['kanaal' => 'mailing']);
        // Afmelden is ook de toestemming voor een seintje intrekken (AVG art. 7 lid 3).
        if (function_exists('seintje_intrekken')) seintje_intrekken((int)$p['id'], 'afmelden');
        return $p['voornaam'] ?: 'onbekend';
    } catch (\Exception $e) { return null; }
}

/** Weer aanmelden (voor het geval iemand zich bedenkt). */
function optout_heraanmelden(string $token): ?string {
    optout_auto_setup();
    if ($token === '') return null;
    try {
        $db = db();
        $q = $db->prepare("SELECT id, voornaam FROM personen WHERE afmeld_token=?");
        $q->execute([$token]);
        $p = $q->fetch();
        if (!$p) return null;
        $db->prepare("UPDATE personen SET mailing_afgemeld=0, mailing_afgemeld_op=NULL WHERE id=?")->execute([$p['id']]);
        return $p['voornaam'] ?: 'onbekend';
    } catch (\Exception $e) { return null; }
}

/** De afmeldlink voor in een mailing (HTML-footer). */
function optout_link(int $persoon_id): string {
    $token = optout_token($persoon_id);
    return APP_URL . '/afmelden.php?t=' . $token;
}

/** Kant-en-klare afmeld-footer voor onderaan een mailing. */
function optout_footer(int $persoon_id): string {
    $link = optout_link($persoon_id);
    return '<p style="font-size:11px;color:#999;margin-top:24px;border-top:1px solid #eee;padding-top:12px">'
         . 'Je ontvangt deze mail omdat je je hebt aangemeld bij Reddingsbrigade Paal 13. '
         . 'Wil je geen mailings meer ontvangen? <a href="' . htmlspecialchars($link) . '" style="color:#999">Afmelden</a>.'
         . '</p>';
}

}

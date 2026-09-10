<?php
/**
 * Paal 13 — Landingspagina's + werving-tracking.
 *
 * Twee lagen:
 *  1. Anonieme bezoekers-statistiek per landingspagina (kliks, unieke bezoekers, formulier
 *     gestart, formulier afgerond). Geen persoonsgegevens, geen cookies die individuen volgen.
 *  2. Bron-attributie: bij een aanmelding wordt de bron/landingspagina op de sollicitatie
 *     vastgelegd, zodat je later ziet waar aangenomen mensen vandaan kwamen.
 *
 * Een bron-code reist door de hele trechter mee (landingspagina → formulier → aanmelding),
 * zodat het niet uitmaakt of iemand via het platform of via de WordPress-plugin binnenkomt.
 */

if (!function_exists('landing_auto_setup')) {

function landing_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        $db = db();
        // De landingspagina's zelf
        $db->exec("CREATE TABLE IF NOT EXISTS `landingspaginas` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(80) NOT NULL UNIQUE,
            `titel` VARCHAR(200) NOT NULL,
            `doelgroep` VARCHAR(120) DEFAULT NULL,
            `html` MEDIUMTEXT DEFAULT NULL,
            `css` MEDIUMTEXT DEFAULT NULL,
            `status` ENUM('concept','alleen_admin','gepubliceerd') NOT NULL DEFAULT 'concept',
            `bron_code` VARCHAR(80) DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `gewijzigd_op` DATETIME DEFAULT NULL,
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Anonieme tracking-events (geaggregeerd te bevragen)
        $db->exec("CREATE TABLE IF NOT EXISTS `landing_events` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `landing_id` INT DEFAULT NULL,
            `bron_code` VARCHAR(80) DEFAULT NULL,
            `type` ENUM('bezoek','formulier_start','formulier_klaar') NOT NULL,
            `bezoeker_hash` CHAR(64) DEFAULT NULL,
            `moment` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_landing` (`landing_id`, `type`),
            KEY `idx_moment` (`moment`),
            KEY `idx_bezoeker` (`bezoeker_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('landing_auto_setup: ' . $e->getMessage()); }
}

/**
 * Een geanonimiseerde, dag-gebonden hash van de bezoeker, om "unieke" bezoekers te tellen
 * zonder iemand persoonlijk te volgen. Combinatie van IP + user-agent + dag + geheim, zodat
 * hij niet herleidbaar is naar een persoon en na een dag "vervalt" (andere dag = andere hash).
 */
function landing_bezoeker_hash(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $dag = date('Y-m-d');
    $geheim = defined('APP_URL') ? APP_URL : 'paal13';
    return hash('sha256', $ip . '|' . $ua . '|' . $dag . '|' . $geheim);
}

/**
 * Leg een tracking-event vast. Anoniem: alleen het type, de landingspagina/bron en een
 * dag-gebonden bezoeker-hash (voor uniek tellen).
 */
function landing_track(string $type, ?int $landing_id, ?string $bron_code = null): void {
    if (!in_array($type, ['bezoek', 'formulier_start', 'formulier_klaar'], true)) return;
    try {
        landing_auto_setup();
        db()->prepare("INSERT INTO landing_events (landing_id, bron_code, type, bezoeker_hash) VALUES (?,?,?,?)")
            ->execute([$landing_id, $bron_code, $type, landing_bezoeker_hash()]);
    } catch (\Exception $e) { error_log('landing_track: ' . $e->getMessage()); }
}

/** Haal een landingspagina op via slug. */
function landing_via_slug(string $slug): ?array {
    try {
        landing_auto_setup();
        $q = db()->prepare("SELECT * FROM landingspaginas WHERE slug = ?");
        $q->execute([$slug]);
        $r = $q->fetch();
        return $r ?: null;
    } catch (\Exception $e) { return null; }
}

/** Haal een landingspagina op via bron-code (voor attributie bij aanmelding). */
function landing_via_bron(string $bron_code): ?array {
    try {
        landing_auto_setup();
        $q = db()->prepare("SELECT * FROM landingspaginas WHERE bron_code = ? OR slug = ?");
        $q->execute([$bron_code, $bron_code]);
        $r = $q->fetch();
        return $r ?: null;
    } catch (\Exception $e) { return null; }
}

/** Alle landingspagina's (voor het beheer). */
function landing_alle(): array {
    try {
        landing_auto_setup();
        return db()->query("SELECT * FROM landingspaginas ORDER BY aangemaakt_op DESC")->fetchAll();
    } catch (\Exception $e) { return []; }
}

/**
 * Statistiek per landingspagina: bezoeken, unieke bezoekers, formulier-starts, afrondingen,
 * en de afgeleide conversie. Optioneel over een periode.
 */
function landing_stats(int $landing_id): array {
    $uit = ['bezoeken' => 0, 'uniek' => 0, 'starts' => 0, 'klaar' => 0];
    try {
        landing_auto_setup();
        $db = db();
        $q = $db->prepare("SELECT type, COUNT(*) aantal, COUNT(DISTINCT bezoeker_hash) uniek FROM landing_events WHERE landing_id = ? GROUP BY type");
        $q->execute([$landing_id]);
        foreach ($q->fetchAll() as $r) {
            if ($r['type'] === 'bezoek') { $uit['bezoeken'] = (int)$r['aantal']; $uit['uniek'] = (int)$r['uniek']; }
            if ($r['type'] === 'formulier_start') $uit['starts'] = (int)$r['aantal'];
            if ($r['type'] === 'formulier_klaar') $uit['klaar'] = (int)$r['aantal'];
        }
    } catch (\Exception $e) {}
    // Conversiepercentages (als hele getallen)
    $uit['start_pct'] = $uit['uniek'] > 0 ? (int)round($uit['starts'] / $uit['uniek'] * 100) : 0;
    $uit['klaar_pct'] = $uit['starts'] > 0 ? (int)round($uit['klaar'] / $uit['starts'] * 100) : 0;
    return $uit;
}

}

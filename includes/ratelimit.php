<?php
/**
 * Paal 13 — Gedeelde snelheidsbegrenzing.
 *
 * Eén bron van waarheid voor "max X acties per Y seconden, per sleutel". Gebruikt door de
 * publieke API (api/aanmelden.php) en door het aanmeldformulier op landingspagina's.
 *
 * Faalt bewust "open": gaat er iets mis in de limiter zelf, dan wordt de actie doorgelaten.
 * Een storing hier mag nooit legitieme aanmeldingen blokkeren.
 */

if (!function_exists('rate_limit')) {

/** Huidige bezoeker als identiteit voor de limiter (IP, of leeg bij CLI). */
function rate_identiteit(): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * Registreer een poging en zeg of die nog binnen de limiet valt.
 * Retourneert false zodra $max pogingen binnen $venster seconden zijn gedaan.
 */
function rate_limit(string $actie, string $identiteit, int $max, int $venster): bool {
    try {
        $db = db();
        $db->exec("CREATE TABLE IF NOT EXISTS `api_rate` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `sleutel` VARCHAR(160) NOT NULL,
            `moment` INT NOT NULL,
            KEY `idx_sleutel` (`sleutel`, `moment`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $sleutel = substr($actie . ':' . $identiteit, 0, 160);
        $nu = time();
        $grens = $nu - $venster;
        // Oude regels opruimen (klein)
        $db->prepare("DELETE FROM api_rate WHERE moment < ?")->execute([$grens]);
        // Tellen binnen het venster
        $q = $db->prepare("SELECT COUNT(*) FROM api_rate WHERE sleutel=? AND moment >= ?");
        $q->execute([$sleutel, $grens]);
        if ((int)$q->fetchColumn() >= $max) return false;
        $db->prepare("INSERT INTO api_rate (sleutel, moment) VALUES (?,?)")->execute([$sleutel, $nu]);
        return true;
    } catch (\Throwable $e) {
        error_log('rate_limit: ' . $e->getMessage());
        return true;  // faalt open
    }
}

}

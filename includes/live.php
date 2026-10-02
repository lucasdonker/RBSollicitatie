<?php
/**
 * Paal 13 — Live bijwerken: wie keek er, wat veranderde er? GEEN HTML.
 *
 * Elke schrijfactie die anderen op een open scherm moeten zien, verhoogt een teller per "scope"
 * (live_bump). Een open beheerscherm vraagt elke 10 seconden de tellers op van de scopes waar het
 * naar kijkt (admin/api_live.php, één PK-query) en reageert in assets/js/live.js: een blok met
 * data-live-blok wordt stil ververst, anders verschijnt "Gewijzigd door X — Vernieuwen".
 *
 * Waarom polling en geen SSE/WebSocket: op PHP-FPM bij Plesk houdt een open verbinding een worker
 * én de sessielock vast. Een korte, goedkope vraag past wel (zie ook assets/js/afspraak.js).
 *
 * Scopes (sleutel:id):
 *   sd:<seizoen>    selectiedag (inchecken, cijfers, observaties, besluiten, bellijst, vergrendelingen)
 *   tw:<seizoen>    trainingsweekend (inchecken, observaties, evaluatie, gesprekken, volgorde)
 *   sol:<id>        één sollicitatie (fase, dossier, observaties over deze persoon, feedback)
 *   loon:<seizoen>  loonplanning en beschikbaarheid (afspraak, planning, opgave)
 *   sz:<seizoen>    alles van het seizoen (lijsten, Vandaag); gaat vanzelf mee met elke sol:<id>
 *
 * Een bump faalt nooit hard: live bijwerken is gemak, geen voorwaarde voor opslaan.
 */

if (!function_exists('live_bump')) {

function live_auto_setup(): void {
    static $done = false;
    if ($done || schema_actueel()) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `live_versie` (
            `scope` VARCHAR(40) NOT NULL PRIMARY KEY,
            `versie` INT UNSIGNED NOT NULL DEFAULT 0,
            `door_id` INT DEFAULT NULL,
            `door_naam` VARCHAR(100) DEFAULT NULL,
            `op` DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) { error_log('live_auto_setup: ' . $e->getMessage()); }
}

/** Is dit een geldige scope ("sd:12")? Ook de whitelist voor wat de browser mag opvragen. */
function live_scope_geldig(string $scope): bool {
    return (bool)preg_match('/^(sd|tw|sol|loon|sz):\d{1,10}$/', $scope);
}

/** Wie doet dit: een beheerder (id + naam), anders het portaal of de tablet (alleen een naam). */
function live_wie(): array {
    if (!empty($_SESSION['bhd_id'])) return [(int)$_SESSION['bhd_id'], (string)($_SESSION['bhd_naam'] ?? 'Iemand van het bestuur')];
    if (!empty($_SESSION['portaal_sol_id'])) return [null, 'de sollicitant'];
    return [null, PHP_SAPI === 'cli' ? 'het systeem' : 'de tablet'];
}

/**
 * Er is iets veranderd. Lege of ongeldige scopes worden overgeslagen; dezelfde scope telt per
 * verzoek één keer (een opslag die drie functies raakt, geeft één melding).
 * @param string[] $scopes
 */
function live_bump(array $scopes): void {
    static $gedaan = [];
    // Een wijziging aan een persoon of evenement is ook een wijziging in het seizoen (lijsten, Vandaag).
    foreach ($scopes as $s) {
        if (!is_string($s) || !live_scope_geldig($s)) continue;
        $delen = explode(':', $s);
        if ($delen[0] === 'sol') { $sz = live_seizoen_van((int)$delen[1]); if ($sz > 0) $scopes[] = 'sz:' . $sz; }
        elseif ($delen[0] !== 'sz') $scopes[] = 'sz:' . $delen[1];
    }
    $scopes = array_values(array_unique(array_filter($scopes, fn($s) => is_string($s) && live_scope_geldig($s) && !isset($gedaan[$s]))));
    if (!$scopes) return;
    try {
        live_auto_setup();
        $wie = live_wie();
        $q = db()->prepare("INSERT INTO live_versie (scope, versie, door_id, door_naam, op) VALUES (?, 1, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE versie = versie + 1, door_id = VALUES(door_id), door_naam = VALUES(door_naam), op = VALUES(op)");
        foreach ($scopes as $s) { $q->execute([$s, $wie[0], mb_substr($wie[1], 0, 100)]); $gedaan[$s] = true; }
    } catch (\Throwable $e) { error_log('live_bump: ' . $e->getMessage()); }
}

/** De scope van een evenement: 'selectiedag' → sd:<seizoen>, 'tw' → tw:<seizoen>. */
function live_ev(string $ev, int $seizoen_id): string {
    return ($ev === 'tw' ? 'tw' : 'sd') . ':' . $seizoen_id;
}

/** Het seizoen van een sollicitatie (voor wie alleen een sol_id heeft). 0 als onbekend. */
function live_seizoen_van(int $sol_id): int {
    static $cache = [];
    if (isset($cache[$sol_id])) return $cache[$sol_id];
    try {
        $q = db()->prepare("SELECT seizoen_id FROM sollicitaties WHERE id=?");
        $q->execute([$sol_id]);
        return $cache[$sol_id] = (int)$q->fetchColumn();
    } catch (\Throwable $e) { return 0; }
}

/**
 * Loonplanning of beschikbaarheid veranderd: loon:<seizoen> (+ de persoon). Het seizoen komt uit
 * het scenario of de sollicitatie als je het niet hebt; een extra persoon (negatief id) heeft geen dossier.
 */
function live_loon(int $scenario_id = 0, int $seizoen_id = 0, int $sol_id = 0): void {
    if ($seizoen_id <= 0 && $scenario_id > 0) {
        try {
            $q = db()->prepare("SELECT seizoen_id FROM loon_scenario WHERE id=?");
            $q->execute([$scenario_id]);
            $seizoen_id = (int)$q->fetchColumn();
        } catch (\Throwable $e) {}
    }
    if ($seizoen_id <= 0 && $sol_id > 0) $seizoen_id = live_seizoen_van($sol_id);
    live_bump(array_filter([$seizoen_id > 0 ? 'loon:' . $seizoen_id : null, $sol_id > 0 ? 'sol:' . $sol_id : null]));
}

/**
 * De huidige tellers: [scope => ['v' => int, 'door_id' => ?int, 'door' => ?string, 'op' => ?string]].
 * Een scope zonder rij heeft versie 0.
 */
function live_versies(array $scopes): array {
    $scopes = array_values(array_unique(array_filter($scopes, fn($s) => is_string($s) && live_scope_geldig($s))));
    $uit = [];
    foreach ($scopes as $s) $uit[$s] = ['v' => 0, 'door_id' => null, 'door' => null, 'op' => null];
    if (!$scopes) return $uit;
    try {
        live_auto_setup();
        $q = db()->prepare("SELECT scope, versie, door_id, door_naam, op FROM live_versie WHERE scope IN (" . implode(',', array_fill(0, count($scopes), '?')) . ")");
        $q->execute($scopes);
        foreach ($q->fetchAll() as $r) {
            $uit[$r['scope']] = ['v' => (int)$r['versie'], 'door_id' => $r['door_id'] !== null ? (int)$r['door_id'] : null,
                                 'door' => $r['door_naam'], 'op' => $r['op']];
        }
    } catch (\Throwable $e) {}
    return $uit;
}

/**
 * Opruimen (kwartiertaak, includes/cron_taken.php): een teller die een week niet veranderde, is
 * nergens meer voor nodig, en bevat de naam van wie het laatst iets wijzigde. Een open tabblad
 * merkt het weghalen niet als wijziging (assets/js/live.js negeert een teller die terugvalt naar 0).
 * @return int aantal verwijderd
 */
function live_opruimen(int $dagen = 7): int {
    try {
        live_auto_setup();
        $q = db()->prepare("DELETE FROM live_versie WHERE op < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $q->execute([max(1, $dagen)]);
        return $q->rowCount();
    } catch (\Throwable $e) { return 0; }
}

/**
 * De attributen voor het element dat live meekijkt (layout_top.php zet ze op <main>): de scopes,
 * de stand van nu (zodat een wijziging tussen laden en de eerste vraag niet wegvalt) en wie je bent.
 */
function live_attr(array $scopes): string {
    $versies = live_versies($scopes);
    if (!$versies) return '';
    return ' data-live="' . h(implode(' ', array_keys($versies))) . '"'
         . ' data-live-v="' . h(json_encode(array_map(fn($r) => $r['v'], $versies))) . '"'
         . ' data-live-ik="' . (int)($_SESSION['bhd_id'] ?? 0) . '"'
         . ' data-live-url="' . h(APP_URL . '/admin/api_live.php') . '"';
}

} // function_exists

<?php
/**
 * Paal 13 — Feedback van (terugkerende) lifeguards over een sollicitant. GEEN HTML.
 *
 * Het bestuur vraagt lifeguards soms wat ze van iemand vinden. Die lifeguards zijn géén
 * beoordelaar: ze hebben geen account in het beheer en geven geen cijfers. Het bestuur legt
 * wat ze zeggen zelf vast in het dossier (besluit Lucas, 25 sep 2026), met wie het zei, op
 * welk moment en of het positief, neutraal of een aandachtspunt is.
 *
 * Vastleggen gebeurt in de module waar het bij hoort (SD-nabespreking, TW-gesprek) met bewerkrecht
 * op die module (lg_feedback_module()); in het dossier staat het alleen te lezen, per moment op
 * de tabs Selectiedag en Trainingsweekend. Beoordelaars op de dag zien het niet.
 *
 * AVG: de tekst gaat over de sollicitant en staat dus in de inzage (art. 15), zonder de naam
 * van de lifeguard (art. 15 lid 4). Hij verdwijnt met avg_sollicitatie_leegmaken().
 */

if (!function_exists('lg_feedback_auto_setup')) {

function lg_feedback_auto_setup(): void {
    static $done = false;
    if ($done || schema_actueel()) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `lifeguard_feedback` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `seizoen_id` INT DEFAULT NULL,
            `van_naam` VARCHAR(100) NOT NULL,
            `moment` VARCHAR(12) NOT NULL DEFAULT 'anders',
            `soort` VARCHAR(10) NOT NULL DEFAULT 'neutraal',
            `tekst` TEXT NOT NULL,
            `vastgelegd_door` INT DEFAULT NULL,
            `vastgelegd_door_naam` VARCHAR(100) DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('lg_feedback_auto_setup: ' . $e->getMessage()); }
}

/** Wanneer de lifeguard het zag. */
function lg_feedback_momenten(): array {
    return ['selectiedag' => 'Selectiedag', 'tw' => 'Trainingsweekend', 'anders' => 'Anders'];
}

/**
 * Welk recht hoort bij feedback van dit moment? Vastleggen en wissen gebeurt in de module zelf
 * (SD-nabespreking, TW-gesprek), dus met bewerkrecht op die module. 'anders' (oude rijen): dossierrecht.
 */
function lg_feedback_module(string $moment): string {
    return ['selectiedag' => 'selectiedag', 'tw' => 'trainingsweekend'][$moment] ?? 'sollicitanten';
}

/** De taak binnen lg_feedback_module() die het scherm ook vraagt: nabespreken, gesprekken of wijzigen. */
function lg_feedback_taak(string $moment): string {
    return ['selectiedag' => 'nabespreken', 'tw' => 'gesprekken'][$moment] ?? 'wijzigen';
}

/** Feedback over één sollicitatie, nieuwste eerst. $momenten leeg = alles. */
function lg_feedback_van(int $sol_id, array $momenten = []): array {
    lg_feedback_auto_setup();
    try {
        $sql = "SELECT * FROM lifeguard_feedback WHERE sollicitatie_id=?";
        $p = [$sol_id];
        if ($momenten) { $sql .= " AND moment IN (" . implode(',', array_fill(0, count($momenten), '?')) . ")"; $p = array_merge($p, array_values($momenten)); }
        $q = db()->prepare($sql . " ORDER BY aangemaakt_op DESC, id DESC");
        $q->execute($p);
        return $q->fetchAll();
    } catch (\Exception $e) { return []; }
}

/** Alle feedback van een seizoen in één query, per sollicitatie: [sol_id => [rij, …]]. Voor lijsten met veel kaarten. */
function lg_feedback_alle(int $seizoen_id): array {
    lg_feedback_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT * FROM lifeguard_feedback WHERE seizoen_id=? ORDER BY aangemaakt_op DESC, id DESC");
        $q->execute([$seizoen_id]);
        foreach ($q->fetchAll() as $r) $uit[(int)$r['sollicitatie_id']][] = $r;
    } catch (\Exception $e) {}
    return $uit;
}

/** Eén rij (voor de rechtencheck bij wissen), of null. */
function lg_feedback_rij(int $id): ?array {
    lg_feedback_auto_setup();
    try {
        $q = db()->prepare("SELECT * FROM lifeguard_feedback WHERE id=?");
        $q->execute([$id]);
        return $q->fetch() ?: null;
    } catch (\Exception $e) { return null; }
}

/** Vastleggen. false bij een lege naam of tekst. */
function lg_feedback_toevoegen(int $sol_id, int $seizoen_id, string $van, string $moment, string $soort, string $tekst,
                               int $door_id, string $door_naam): bool {
    $van = trim($van); $tekst = trim($tekst);
    if ($sol_id <= 0 || $van === '' || $tekst === '') return false;
    if (!isset(lg_feedback_momenten()[$moment])) $moment = 'anders';
    if (!in_array($soort, ['positief', 'neutraal', 'aandacht'], true)) $soort = 'neutraal';
    lg_feedback_auto_setup();
    try {
        db()->prepare("INSERT INTO lifeguard_feedback (sollicitatie_id, seizoen_id, van_naam, moment, soort, tekst, vastgelegd_door, vastgelegd_door_naam)
                       VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$sol_id, $seizoen_id ?: null, mb_substr($van, 0, 100), $moment, $soort, mb_substr($tekst, 0, 2000),
                      $door_id ?: null, mb_substr($door_naam, 0, 100)]);
        if (function_exists('audit_log')) audit_log('lg_feedback_vastgelegd', 'sollicitatie', $sol_id, ['moment' => $moment, 'soort' => $soort]);
        return true;
    } catch (\Exception $e) { error_log('lg_feedback_toevoegen: ' . $e->getMessage()); return false; }
}

/** Wissen: wie het vastlegde, of een admin. Retourneert het sollicitatie-id (0 = niets gewist). */
function lg_feedback_wissen(int $id, int $door_id, bool $is_admin): int {
    lg_feedback_auto_setup();
    try {
        $q = db()->prepare("SELECT sollicitatie_id, vastgelegd_door FROM lifeguard_feedback WHERE id=?");
        $q->execute([$id]);
        $r = $q->fetch();
        if (!$r || (!$is_admin && (int)$r['vastgelegd_door'] !== $door_id)) return 0;
        db()->prepare("DELETE FROM lifeguard_feedback WHERE id=?")->execute([$id]);
        if (function_exists('audit_log')) audit_log('lg_feedback_gewist', 'sollicitatie', (int)$r['sollicitatie_id'], []);
        return (int)$r['sollicitatie_id'];
    } catch (\Exception $e) { return 0; }
}

/**
 * Namen van de terugkerende lifeguards van dit seizoen, als suggestie bij "Van wie".
 * Alleen voor het bestuur (dossierrecht); vrij typen blijft mogelijk.
 */
function lg_feedback_namen(int $seizoen_id): array {
    static $cache = [];
    if (isset($cache[$seizoen_id])) return $cache[$seizoen_id];
    if ($seizoen_id <= 0 || !function_exists('authentik_lifeguard_sql')) return [];
    return $cache[$seizoen_id] = lg_feedback_namen_laden($seizoen_id);
}

function lg_feedback_namen_laden(int $seizoen_id): array {
    try {
        $q = db()->prepare("SELECT p.voornaam, p.tussenvoegsel, p.achternaam FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id
                            WHERE s.seizoen_id=? AND " . authentik_lifeguard_sql('s.id') . " = 1 ORDER BY p.voornaam, p.achternaam");
        $q->execute([$seizoen_id]);
        return array_map(fn($r) => trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam']), $q->fetchAll());
    } catch (\Exception $e) { return []; }
}

/** De rijen in de vorm van admin/_observaties.php (tekst, soort, beoordelaar, aangemaakt_op). */
function lg_feedback_als_observaties(array $rijen): array {
    $m = lg_feedback_momenten();
    // De datum hoort erbij (de partial toont alleen de tijd): "Jacob · trainingsweekend · 4 mei · via Lucas".
    return array_map(fn($r) => $r + ['beoordelaar' => $r['van_naam'] . ' · ' . mb_strtolower($m[$r['moment']] ?? $r['moment'])
                                     . (function_exists('datum_kort_nl') && !empty($r['aangemaakt_op']) ? ' · ' . datum_kort_nl($r['aangemaakt_op']) : '')
                                     . (!empty($r['vastgelegd_door_naam']) ? ' · via ' . $r['vastgelegd_door_naam'] : '')], $rijen);
}

} // function_exists

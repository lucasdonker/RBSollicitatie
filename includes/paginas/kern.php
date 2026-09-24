<?php
/**
 * Pagina's — kern: schema en CRUD. De moduledefinitie (recht, menu) staat in includes/modules/paginas.php.
 *
 * Datamodel (3 tabellen):
 *  - paginas         de pagina zelf (slug, titel, status, actieve versie, CSP-uitzonderingen)
 *  - pagina_versies  elke geüploade HTML-versie, met scanrapport (terugrollen mogelijk)
 *  - pagina_events   anonieme bezoekersstatistiek (zie statistiek.php)
 *
 * De HTML staat NIET in de database maar als bestand in uploads/paginas/<slug>/versies/.
 * Zo kun je een compleet ontwerp uploaden en blijft de database klein.
 */

if (!function_exists('pag_auto_setup')) {

function pag_statussen(): array {
    return ['concept' => 'Concept', 'alleen_admin' => 'Alleen beheerders', 'gepubliceerd' => 'Gepubliceerd'];
}

/** Tabellen aanmaken + de rechten eenmalig op bestaande rollen zetten. */
function pag_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
    try {
        $db = db();
        $db->exec("CREATE TABLE IF NOT EXISTS `paginas` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(80) NOT NULL UNIQUE,
            `titel` VARCHAR(200) NOT NULL,
            `doelgroep` VARCHAR(120) DEFAULT NULL,
            `bron_code` VARCHAR(80) DEFAULT NULL,
            `status` ENUM('concept','alleen_admin','gepubliceerd') NOT NULL DEFAULT 'concept',
            `actieve_versie_id` INT DEFAULT NULL,
            `csp_extra` TEXT DEFAULT NULL,
            `indexeerbaar` TINYINT(1) NOT NULL DEFAULT 0,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `gewijzigd_op` DATETIME DEFAULT NULL,
            `aangemaakt_door` INT DEFAULT NULL,
            `gewijzigd_door` INT DEFAULT NULL,
            KEY `idx_status` (`status`),
            KEY `idx_bron` (`bron_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `pagina_versies` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `pagina_id` INT NOT NULL,
            `versie` INT NOT NULL,
            `bestand` VARCHAR(120) NOT NULL,
            `sha256` CHAR(64) NOT NULL,
            `grootte` INT NOT NULL DEFAULT 0,
            `bron` VARCHAR(20) NOT NULL DEFAULT 'upload',
            `bestandsnaam_origineel` VARCHAR(200) DEFAULT NULL,
            `scan_json` MEDIUMTEXT DEFAULT NULL,
            `scan_bevestigd_op` DATETIME DEFAULT NULL,
            `scan_bevestigd_door` INT DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `aangemaakt_door` INT DEFAULT NULL,
            UNIQUE KEY `idx_pagina_versie` (`pagina_id`, `versie`),
            KEY `idx_pagina` (`pagina_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // De waarden van 'type' komen uit pag_event_typen(); pag_events_enum_bij() houdt de
        // ENUM daarmee gelijk op een database die al bestond.
        $typen = "'" . implode("','", pag_event_typen()) . "'";
        $db->exec("CREATE TABLE IF NOT EXISTS `pagina_events` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `pagina_id` INT DEFAULT NULL,
            `bron_code` VARCHAR(80) DEFAULT NULL,
            `type` ENUM($typen) NOT NULL,
            `detail` VARCHAR(40) DEFAULT NULL,
            `bezoeker_hash` CHAR(64) DEFAULT NULL,
            `moment` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_pagina` (`pagina_id`, `type`),
            KEY `idx_moment` (`moment`),
            KEY `idx_bezoeker` (`bezoeker_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (function_exists('db_kolom_verzeker')) db_kolom_verzeker($db, 'pagina_events', 'detail', 'VARCHAR(40) DEFAULT NULL');
        pag_events_enum_bij($db);

        pag_rechten_seed();
    } catch (\Throwable $e) { error_log('pag_auto_setup: ' . $e->getMessage()); }
}

/**
 * Eenmalig: geef bestaande rollen het recht op deze module. rechten_auto_setup() seedt
 * alleen nieuwe róllen, niet nieuwe modules — zonder dit ziet het bestuur na de update
 * het menu-item niet, terwijl ze de oude landingspagina's wél konden beheren.
 */
function pag_rechten_seed(): void {
    if (!function_exists('instelling') || instelling('paginas.rechten_geseed') === '1') return;
    try {
        $db = db();
        if (!db_tabel_bestaat($db, 'rol_rechten')) return;
        // Wie sollicitanten mocht bewerken, beheerde voorheen ook de landingspagina's.
        $db->exec("INSERT IGNORE INTO rol_rechten (rol_sleutel, module, lezen, bewerken, verwijderen)
                   SELECT rol_sleutel, 'paginas', lezen, bewerken, verwijderen
                   FROM rol_rechten WHERE module = 'sollicitanten'");
        instelling_opslaan('paginas.rechten_geseed', '1');
    } catch (\Throwable $e) { error_log('pag_rechten_seed: ' . $e->getMessage()); }
}

/** Maak een geldige slug van vrije invoer. Leeg = ongeldig. */
function pag_slug_maak(string $ruw): string {
    $s = strtolower(trim($ruw));
    $s = preg_replace('/[^a-z0-9\-]/', '-', $s);
    $s = trim(preg_replace('/-+/', '-', $s), '-');
    return substr($s, 0, 80);
}

function pag_slug_geldig(string $slug): bool {
    return $slug !== '' && (bool)preg_match('/^[a-z0-9][a-z0-9\-]{0,79}$/', $slug);
}

function pag_via_id(int $id): ?array {
    try {
        pag_auto_setup();
        $q = db()->prepare("SELECT * FROM paginas WHERE id = ?");
        $q->execute([$id]);
        return $q->fetch() ?: null;
    } catch (\Throwable $e) { return null; }
}

function pag_via_slug(string $slug): ?array {
    try {
        pag_auto_setup();
        $q = db()->prepare("SELECT * FROM paginas WHERE slug = ? LIMIT 1");
        $q->execute([$slug]);
        return $q->fetch() ?: null;
    } catch (\Throwable $e) { return null; }
}

/**
 * Pagina bij een bron-code, voor herkomst-attributie bij een aanmelding.
 * Eerst op bron_code (de expliciete koppeling), pas daarna op slug — de oude module deed
 * "bron_code = ? OR slug = ?" in één query en gaf bij een botsing een willekeurige rij.
 */
function pag_via_bron(string $bron_code): ?array {
    if ($bron_code === '') return null;
    try {
        pag_auto_setup();
        $q = db()->prepare("SELECT * FROM paginas WHERE bron_code = ? LIMIT 1");
        $q->execute([$bron_code]);
        $r = $q->fetch();
        return $r ?: pag_via_slug($bron_code);
    } catch (\Throwable $e) { return null; }
}

function pag_alle(): array {
    try {
        pag_auto_setup();
        return db()->query("SELECT * FROM paginas ORDER BY aangemaakt_op DESC")->fetchAll();
    } catch (\Throwable $e) { return []; }
}

/**
 * Pagina aanmaken of bijwerken. Bij een gewijzigde slug verhuist ook de opslagmap mee,
 * zodat relatieve verwijzingen naar afbeeldingen blijven werken.
 * Retour: ['ok'=>bool, 'id'=>int, 'fout'=>string]
 */
function pag_opslaan(array $in, int $id = 0): array {
    pag_auto_setup();
    $slug  = pag_slug_maak((string)($in['slug'] ?? ''));
    $titel = trim((string)($in['titel'] ?? ''));
    if (!pag_slug_geldig($slug)) return ['ok' => false, 'id' => $id, 'fout' => 'De URL-naam mag alleen kleine letters, cijfers en koppeltekens bevatten.'];
    if ($titel === '')           return ['ok' => false, 'id' => $id, 'fout' => 'Vul een titel in.'];

    $statussen = pag_statussen();
    $status    = isset($statussen[$in['status'] ?? '']) ? (string)$in['status'] : 'concept';
    $bron      = pag_slug_maak((string)($in['bron_code'] ?? '')) ?: $slug;
    $doelgroep = substr(trim((string)($in['doelgroep'] ?? '')), 0, 120);
    $csp       = pag_csp_lijst_opschonen((string)($in['csp_extra'] ?? ''));
    $index     = !empty($in['indexeerbaar']) ? 1 : 0;
    $bhd       = function_exists('huidige_beheerder') ? (int)(huidige_beheerder()['id'] ?? 0) : 0;

    try {
        $db = db();
        // Slug moet uniek zijn; een nette melding is duidelijker dan een SQL-fout.
        $q = $db->prepare("SELECT id FROM paginas WHERE slug = ? AND id <> ?");
        $q->execute([$slug, $id]);
        if ($q->fetch()) return ['ok' => false, 'id' => $id, 'fout' => 'Er bestaat al een pagina met deze URL-naam.'];

        if ($id > 0) {
            $oud = pag_via_id($id);
            if (!$oud) return ['ok' => false, 'id' => 0, 'fout' => 'Pagina niet gevonden.'];
            if ($oud['slug'] !== $slug && !pag_map_hernoem($oud['slug'], $slug)) {
                return ['ok' => false, 'id' => $id, 'fout' => 'De URL-naam kon niet worden gewijzigd: de opslagmap is niet te hernoemen.'];
            }
            $db->prepare("UPDATE paginas SET slug=?, titel=?, doelgroep=?, bron_code=?, status=?, csp_extra=?, indexeerbaar=?, gewijzigd_op=NOW(), gewijzigd_door=? WHERE id=?")
               ->execute([$slug, $titel, $doelgroep, $bron, $status, $csp, $index, $bhd ?: null, $id]);
            if (function_exists('audit_wijziging')) {
                audit_wijziging('pagina', $id,
                    ['slug'=>$oud['slug'],'titel'=>$oud['titel'],'status'=>$oud['status'],'bron_code'=>$oud['bron_code'],'csp_extra'=>(string)$oud['csp_extra'],'indexeerbaar'=>(int)$oud['indexeerbaar']],
                    ['slug'=>$slug,'titel'=>$titel,'status'=>$status,'bron_code'=>$bron,'csp_extra'=>$csp,'indexeerbaar'=>$index]);
            }
        } else {
            $db->prepare("INSERT INTO paginas (slug, titel, doelgroep, bron_code, status, csp_extra, indexeerbaar, aangemaakt_door) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$slug, $titel, $doelgroep, $bron, $status, $csp, $index, $bhd ?: null]);
            $id = (int)$db->lastInsertId();
            if (function_exists('audit_log')) audit_log('aangemaakt', 'pagina', $id, ['slug' => $slug]);
        }
        return ['ok' => true, 'id' => $id, 'fout' => ''];
    } catch (\Throwable $e) {
        error_log('pag_opslaan: ' . $e->getMessage());
        return ['ok' => false, 'id' => $id, 'fout' => 'Opslaan is niet gelukt. Probeer het opnieuw.'];
    }
}

/** Pagina + versies + bestanden verwijderen. De statistiek blijft (anoniem, telt mee in werving). */
function pag_verwijder(int $id): bool {
    $p = pag_via_id($id);
    if (!$p) return false;
    try {
        $db = db();
        $db->prepare("DELETE FROM pagina_versies WHERE pagina_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM paginas WHERE id = ?")->execute([$id]);
        pag_map_verwijder((string)$p['slug']);
        if (function_exists('audit_log')) audit_log('verwijderd', 'pagina', $id, ['slug' => $p['slug']]);
        return true;
    } catch (\Throwable $e) { error_log('pag_verwijder: ' . $e->getMessage()); return false; }
}

/** Externe hosts voor de CSP opschonen: één per regel, alleen https-hostnamen. */
function pag_csp_lijst_opschonen(string $ruw): string {
    $uit = [];
    foreach (preg_split('/[\r\n,\s]+/', $ruw) as $regel) {
        $regel = trim($regel);
        if ($regel === '') continue;
        // Hele URL's terugbrengen tot de host
        if (preg_match('~^https?://([^/]+)~i', $regel, $m)) $regel = $m[1];
        $regel = strtolower($regel);
        if (preg_match('/^[a-z0-9]([a-z0-9.\-]*[a-z0-9])?$/', $regel) && str_contains($regel, '.')) {
            $uit[] = $regel;
        }
    }
    return implode("\n", array_unique($uit));
}

}

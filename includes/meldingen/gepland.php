<?php
/**
 * Paal 13 — Meldingen: geplande berichten.
 *
 * Regel: "X dagen na/vóór gebeurtenis Y stuur sjabloon Z" (instelbaar in het beheer, tabel
 * geplande_berichten). Wordt meegenomen in herinnering_run() (cron + proefdraaien), met dedup
 * per regel per sollicitatie via mail_log (meldingstype 'gepland', referentie 'gepland:<id>').
 * Een venster van 7 dagen voorkomt dat een nieuw aangemaakte regel alsnog oude gevallen mailt.
 */

if (!function_exists('gepland_auto_setup')) {

function gepland_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `geplande_berichten` (
            `id`             INT AUTO_INCREMENT PRIMARY KEY,
            `naam`           VARCHAR(120) NOT NULL,
            `aan`            TINYINT(1) NOT NULL DEFAULT 1,
            `gebeurtenis`    VARCHAR(40) NOT NULL,
            `offset_dagen`   INT NOT NULL DEFAULT 0,
            `sjabloon_slug`  VARCHAR(50) NOT NULL,
            `kanaalmodus`    VARCHAR(10) NOT NULL DEFAULT 'email',
            `fases`          VARCHAR(255) DEFAULT NULL,
            `max_per_sollicitatie` TINYINT NOT NULL DEFAULT 1,
            `venster_dagen`  TINYINT NOT NULL DEFAULT 7,
            `aangemaakt_door` INT DEFAULT NULL,
            `aangemaakt_op`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `gewijzigd_op`   DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) { error_log('gepland_auto_setup: ' . $e->getMessage()); }
}

/** Gebeurtenissen waarop een regel kan aanhaken (sleutel => label). */
function gepland_gebeurtenissen(): array {
    $uit = [
        'aangemeld'                 => 'Aanmelding (datum van aanmelden)',
        'blk_ingediend'             => 'Formulier "Beter leren kennen" ingeleverd',
        'beschikbaarheid_definitief'=> 'Beschikbaarheid definitief gemaakt',
        'selectiedag'               => 'Selectiedag (datum uit het seizoen)',
        'trainingsweekend'          => 'Trainingsweekend (startdatum uit het seizoen)',
    ];
    if (function_exists('fasen')) foreach (fasen() as $f => $l) $uit['fase:' . $f] = 'Fase gewijzigd naar "' . $l . '"';
    return $uit;
}

function gepland_regels(bool $alleen_aan = false): array {
    gepland_auto_setup();
    try {
        $rijen = db()->query("SELECT * FROM geplande_berichten ORDER BY id")->fetchAll();
        return $alleen_aan ? array_values(array_filter($rijen, fn($r) => (int)$r['aan'] === 1)) : $rijen;
    } catch (\Throwable $e) { return []; }
}

function gepland_regel(int $id): ?array {
    gepland_auto_setup();
    try { $q = db()->prepare("SELECT * FROM geplande_berichten WHERE id=?"); $q->execute([$id]); return $q->fetch() ?: null; }
    catch (\Throwable $e) { return null; }
}

/** Opslaan (nieuw of bestaand). Retourneert het id, of 0 bij een fout. */
function gepland_opslaan(array $d, ?int $id = null, ?int $door = null): int {
    gepland_auto_setup();
    $fases = array_values(array_intersect((array)($d['fases'] ?? []), array_keys(function_exists('fasen') ? fasen() : [])));
    $p = [
        trim((string)($d['naam'] ?? '')) ?: 'Gepland bericht', !empty($d['aan']) ? 1 : 0,
        isset(gepland_gebeurtenissen()[$d['gebeurtenis'] ?? '']) ? $d['gebeurtenis'] : 'aangemeld',
        max(-60, min(365, (int)($d['offset_dagen'] ?? 0))), preg_replace('/[^a-z0-9_-]/i', '', (string)($d['sjabloon_slug'] ?? '')),
        in_array($d['kanaalmodus'] ?? '', ['email', 'kort', 'beide'], true) ? $d['kanaalmodus'] : 'email',
        $fases ? implode(',', $fases) : null, max(1, min(10, (int)($d['max_per_sollicitatie'] ?? 1))), max(1, min(60, (int)($d['venster_dagen'] ?? 7))),
    ];
    try {
        if ($id) {
            db()->prepare("UPDATE geplande_berichten SET naam=?, aan=?, gebeurtenis=?, offset_dagen=?, sjabloon_slug=?, kanaalmodus=?, fases=?, max_per_sollicitatie=?, venster_dagen=?, gewijzigd_op=NOW() WHERE id=?")
               ->execute(array_merge($p, [$id]));
            return $id;
        }
        db()->prepare("INSERT INTO geplande_berichten (naam, aan, gebeurtenis, offset_dagen, sjabloon_slug, kanaalmodus, fases, max_per_sollicitatie, venster_dagen, aangemaakt_door) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute(array_merge($p, [$door]));
        return (int)db()->lastInsertId();
    } catch (\Throwable $e) { error_log('gepland_opslaan: ' . $e->getMessage()); return 0; }
}

function gepland_verwijderen(int $id): bool {
    try { db()->prepare("DELETE FROM geplande_berichten WHERE id=?")->execute([$id]); return true; } catch (\Throwable $e) { return false; }
}

/**
 * Kandidaten voor een regel: sollicitaties waarvan (gebeurtenisdatum + offset) in het venster
 * [vandaag - venster, vandaag] valt. Retourneert rijen ['sol_id','naam','datum'].
 */
function gepland_kandidaten(array $regel, array $seizoen): array {
    $sid = (int)($seizoen['id'] ?? 0);
    if (!$sid) return [];
    $geb = (string)$regel['gebeurtenis'];
    $fases_filter = array_filter(array_map('trim', explode(',', (string)($regel['fases'] ?? ''))));
    $basis = "SELECT s.id AS sol_id, s.fase, s.aangemeld_op, s.gewijzigd_op, p.voornaam, p.achternaam, %s AS gebeurtenis_op
              FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id %s
              WHERE s.seizoen_id=? AND s.fase NOT IN ('afgewezen','afgehaakt','verwijderd_door_sollicitant') %s";
    $p = [$sid]; $join = ''; $extra = ''; $datum_expr = 's.aangemeld_op';
    if ($geb === 'blk_ingediend') { $join = "JOIN blk_formulieren b ON b.sollicitatie_id=s.id"; $datum_expr = 'b.definitief_op'; $extra = "AND b.definitief_op IS NOT NULL"; }
    elseif ($geb === 'beschikbaarheid_definitief') { $join = "JOIN beschikbaarheid_profiel bp ON bp.sollicitatie_id=s.id"; $datum_expr = 'bp.definitief_op'; $extra = "AND bp.definitief_op IS NOT NULL AND bp.voorlopig=0"; }
    elseif (str_starts_with($geb, 'fase:')) { $datum_expr = 's.gewijzigd_op'; $extra = "AND s.fase=?"; $p[] = substr($geb, 5); }
    elseif ($geb === 'selectiedag' || $geb === 'trainingsweekend') { $datum_expr = 'NULL'; }
    if ($fases_filter) { $extra .= " AND s.fase IN (" . implode(',', array_fill(0, count($fases_filter), '?')) . ")"; $p = array_merge($p, $fases_filter); }

    $vast = null;
    if ($geb === 'selectiedag')       $vast = $seizoen['datum_selectiedag'] ?? null;
    if ($geb === 'trainingsweekend')  $vast = $seizoen['datum_tw_start'] ?? null;
    if (($geb === 'selectiedag' || $geb === 'trainingsweekend') && !$vast) return [];

    $uit = [];
    try {
        $q = db()->prepare(sprintf($basis, $datum_expr, $join, $extra));
        $q->execute($p);
        $vandaag = strtotime('today');
        $venster = max(1, (int)($regel['venster_dagen'] ?? 7)) * 86400;
        foreach ($q->fetchAll() as $r) {
            $d = $vast ?: ($r['gebeurtenis_op'] ?? null);
            $ts = $d ? strtotime(substr((string)$d, 0, 10)) : false;
            if (!$ts) continue;
            $moment = $ts + (int)$regel['offset_dagen'] * 86400;
            if ($moment > $vandaag || $moment < $vandaag - $venster) continue;
            $uit[] = ['sol_id' => (int)$r['sol_id'], 'naam' => trim($r['voornaam'] . ' ' . $r['achternaam']), 'datum' => date('Y-m-d', $moment)];
        }
    } catch (\Throwable $e) { error_log('gepland_kandidaten: ' . $e->getMessage()); }
    return $uit;
}

/**
 * Draai de geplande regels (alle, of één op id). Zelfde uitvoerformaat als herinnering_run().
 */
function gepland_run(?int $alleen_id = null, bool $dry_run = false): array {
    $uit = [];
    $seizoen = function_exists('actief_seizoen') ? actief_seizoen() : null;
    if (!$seizoen || !function_exists('melding_verstuur')) return $uit;
    foreach (gepland_regels(false) as $regel) {
        $id = (int)$regel['id'];
        if ($alleen_id !== null && $alleen_id !== $id) continue;
        $res = ['naam' => 'Gepland: ' . $regel['naam'], 'aan' => (int)$regel['aan'] === 1, 'kandidaten' => 0, 'verstuurd' => 0, 'overgeslagen' => 0, 'lijst' => []];
        if (!$res['aan'] && $alleen_id === null) { $uit['gepland_' . $id] = $res; continue; }
        $t = function_exists('sjabloon_laden') ? sjabloon_laden((string)$regel['sjabloon_slug']) : null;
        if (!$t) { $res['fout'] = 'sjabloon niet gevonden'; $uit['gepland_' . $id] = $res; continue; }
        $ref = 'gepland:' . $id;
        $max = max(1, (int)$regel['max_per_sollicitatie']);
        foreach (gepland_kandidaten($regel, $seizoen) as $k) {
            $res['kandidaten']++;
            $stand = function_exists('herinnering_stand') ? herinnering_stand('gepland', $k['sol_id'], $ref) : ['aantal' => 0];
            if ($stand['aantal'] >= $max) { $res['overgeslagen']++; $res['lijst'][] = ['sol_id' => $k['sol_id'], 'naam' => $k['naam'], 'referentie' => $ref, 'actie' => 'max']; continue; }
            $res['lijst'][] = ['sol_id' => $k['sol_id'], 'naam' => $k['naam'] . ' (' . $k['datum'] . ')', 'referentie' => $ref, 'actie' => 'versturen'];
            if ($dry_run) { $res['verstuurd']++; continue; }
            $override = array_filter(['hero_titel' => $t['hero_titel'] ?? '', 'hero_subtitel' => $t['hero_subtitel'] ?? '', 'hero_kleur' => $t['hero_kleur'] ?? '', 'hero_image_url' => $t['hero_image_url'] ?? '', 'bijlagen_json' => $t['bijlagen_json'] ?? ''], fn($v) => $v !== '' && $v !== null);
            $r = melding_verstuur('gepland', $k['sol_id'], ['onderwerp' => (string)$t['onderwerp'], 'body_html' => (string)$t['body_html'], 'tekst_kort' => (string)($t['tekst_kort'] ?? '')],
                ['override' => $override, 'kanaalmodus' => (string)$regel['kanaalmodus'], 'prioriteit' => 'queue', 'referentie' => $ref, 'dedup_min' => 0]);
            if (in_array($r['status'], ['wacht', 'verstuurd'], true)) $res['verstuurd']++; else $res['overgeslagen']++;
        }
        $uit['gepland_' . $id] = $res;
    }
    return $uit;
}

}

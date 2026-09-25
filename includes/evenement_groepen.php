<?php
/**
 * Paal 13 — Groepen voor de selectiedag én het trainingsweekend. GEEN HTML.
 *
 * Eén laag voor beide evenementen (evenement_def(): groep_tabel + de indeling in de tabel van
 * het evenement): selectie_groep + selectie_indeling.groep_id, en tw_groep + tw_deelname.groep_id.
 * Twee momenten, twee indelingen — maar dezelfde handelingen en hetzelfde scherm
 * (admin/_groepsindeling.php).
 *
 * "Niet samen met" is bewust één set per seizoen (tabel selectie_relatie): wie op de selectiedag
 * niet bij elkaar mocht, mag dat op het weekend ook niet. Aanpassen op het ene scherm geldt
 * dus ook voor het andere.
 *
 * Het voorstel zelf is selectie_verdeel_voorstel() (includes/selectiedag.php): een pure functie,
 * voor beide evenementen dezelfde.
 */

if (!function_exists('evenement_groepen')) {

/** Groepen van een evenement in een seizoen, op nummer. */
function evenement_groepen(string $ev, int $seizoen_id): array {
    $d = evenement_def($ev);
    if (!$d || $seizoen_id <= 0) return [];
    evenement_setup($ev);
    try {
        $q = db()->prepare("SELECT * FROM `{$d['groep_tabel']}` WHERE seizoen_id=? ORDER BY nummer");
        $q->execute([$seizoen_id]);
        return $q->fetchAll();
    } catch (\Exception $e) { return []; }
}

/** Zet het aantal groepen: maakt erbij, of haalt de hoogste weg (hun leden worden "nog niet ingedeeld"). */
function evenement_groepen_zetten(string $ev, int $seizoen_id, int $aantal): void {
    $d = evenement_def($ev);
    if (!$d) return;
    $aantal   = max(1, min(20, $aantal));
    $bestaand = evenement_groepen($ev, $seizoen_id);
    $huidige  = count($bestaand);
    $db = db();
    if ($aantal > $huidige) {
        $ins = $db->prepare("INSERT IGNORE INTO `{$d['groep_tabel']}` (seizoen_id, nummer) VALUES (?,?)");
        for ($n = $huidige + 1; $n <= $aantal; $n++) $ins->execute([$seizoen_id, $n]);
    } elseif ($aantal < $huidige) {
        for ($n = $huidige; $n > $aantal; $n--) {
            $g = $bestaand[$n - 1];
            $db->prepare("UPDATE `{$d['tabel']}` SET groep_id=NULL WHERE groep_id=?")->execute([$g['id']]);
            $db->prepare("DELETE FROM `{$d['groep_tabel']}` WHERE id=?")->execute([$g['id']]);
        }
    }
}

/** Is dit een groep van dit evenement en seizoen? (Server-side, niet vertrouwen op de keuzelijst.) */
function evenement_groep_bestaat(string $ev, int $seizoen_id, int $groep_id): bool {
    return in_array($groep_id, array_map(fn($g) => (int)$g['id'], evenement_groepen($ev, $seizoen_id)), true);
}

/** Eén persoon in een groep zetten, of eruit (null). */
function evenement_indeel(string $ev, int $seizoen_id, int $sol_id, ?int $groep_id): void {
    evenement_indeling_opslaan($ev, $seizoen_id, [$sol_id => $groep_id]);
}

/** Een hele indeling opslaan: [sol_id => groep_id|null]. */
function evenement_indeling_opslaan(string $ev, int $seizoen_id, array $map): void {
    $d = evenement_def($ev);
    if (!$d) return;
    evenement_setup($ev);
    $ins = db()->prepare("INSERT INTO `{$d['tabel']}` (seizoen_id, sollicitatie_id, groep_id) VALUES (?,?,?)
                          ON DUPLICATE KEY UPDATE groep_id=VALUES(groep_id)");
    foreach ($map as $sol_id => $groep_id) {
        $ins->execute([$seizoen_id, (int)$sol_id, $groep_id !== null ? (int)$groep_id : null]);
    }
}

/**
 * Een voorstel maken en opslaan. Maakt eerst groepen als er nog geen zijn.
 * $kandidaten: rijen met sol_id, geslacht, leeftijd, is_lifeguard (selectie_kandidaten() / tw_kandidaten()).
 */
function evenement_voorstel_maken(string $ev, int $seizoen_id, array $kandidaten, int $aantal): void {
    $groepen = evenement_groepen($ev, $seizoen_id);
    if (!$groepen) { evenement_groepen_zetten($ev, $seizoen_id, max(1, $aantal)); $groepen = evenement_groepen($ev, $seizoen_id); }
    $voorstel = selectie_verdeel_voorstel($kandidaten, count($groepen), evenement_relaties($seizoen_id));
    $map = [];
    foreach ($voorstel as $sol_id => $gi) $map[$sol_id] = isset($groepen[$gi]) ? (int)$groepen[$gi]['id'] : null;
    evenement_indeling_opslaan($ev, $seizoen_id, $map);
}

/** "Niet samen met" van het seizoen, voor beide evenementen: [sol_id => [ander_id, …]]. */
function evenement_relaties(int $seizoen_id): array {
    return function_exists('selectie_relaties') ? selectie_relaties($seizoen_id) : [];
}

/** Zit iemand in dezelfde groep met een persoon met wie hij niet samen mag? [sol_id => true] */
function evenement_groep_botsingen(array $kandidaten, array $relaties): array {
    $groep = [];
    foreach ($kandidaten as $k) if (!empty($k['groep_id'])) $groep[(int)$k['sol_id']] = (int)$k['groep_id'];
    $uit = [];
    foreach ($groep as $sol => $g) {
        foreach ($relaties[$sol] ?? [] as $ander) {
            if (($groep[$ander] ?? null) === $g) { $uit[$sol] = true; break; }
        }
    }
    return $uit;
}

/** "Groep 2" of de eigen naam van de groep. */
function evenement_groep_label(array $g): string {
    return trim((string)($g['naam'] ?? '')) !== '' ? (string)$g['naam'] : 'Groep ' . (int)$g['nummer'];
}

} // function_exists

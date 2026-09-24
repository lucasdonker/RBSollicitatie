<?php
/**
 * Paal 13 — Selectiedag (fase 1: groepsindeling)
 *
 * Datamodel voor de selectiedag: groepen, koppeling van sollicitanten aan groepen,
 * en een hulpje dat een eerlijke indeling voorstelt.
 *
 * Ontwerpkeuzes (met Lucas):
 *  - Vaste groepen per seizoen (1, 2, 3, …), aantal hangt af van #sollicitanten en #begeleiders.
 *  - Eerlijk = gelijke groepsgrootte + gemengd op leeftijd en geslacht.
 *  - "Houd uit elkaar": bekende koppels (vrienden) worden zo mogelijk niet in dezelfde groep gezet.
 *  - Begeleiders worden NIET hard aan een groep gekoppeld; een beoordelaar kiest op de dag zelf
 *    zijn groep. (Zie latere fases.) Deze fase legt alleen groepen + sollicitant-indeling vast.
 *
 * De oude losse `beoordelingen`-tabel blijft ongemoeid; dit is een nieuw, apart model.
 */

function selectiedag_auto_setup(): void {
    static $done = false;
    if ($done || schema_actueel()) return;
    $done = true;
    try {
        $db = db();

        // Groepen per seizoen
        $db->exec("CREATE TABLE IF NOT EXISTS `selectie_groep` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `nummer` SMALLINT NOT NULL,
            `naam` VARCHAR(60) DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniek_nr` (`seizoen_id`,`nummer`),
            INDEX (`seizoen_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Koppeling sollicitant ↔ groep (één groep per sollicitant per seizoen)
        $db->exec("CREATE TABLE IF NOT EXISTS `selectie_indeling` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `sollicitatie_id` INT NOT NULL,
            `groep_id` INT DEFAULT NULL,
            `aanwezig` TINYINT(1) NOT NULL DEFAULT 0,
            `ingecheckt_op` DATETIME DEFAULT NULL,
            UNIQUE KEY `uniek_sol` (`seizoen_id`,`sollicitatie_id`),
            INDEX (`groep_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // "Houd uit elkaar"-relaties (vrienden die niet samen in een groep moeten)
        $db->exec("CREATE TABLE IF NOT EXISTS `selectie_relatie` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `sollicitatie_id` INT NOT NULL,
            `ander_id` INT NOT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniek_paar` (`seizoen_id`,`sollicitatie_id`,`ander_id`),
            INDEX (`seizoen_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Scores: één rij per (kandidaat, test, criterium, beoordelaar).
        // Meerdere beoordelaars naast elkaar → geen overschrijven.
        $db->exec("CREATE TABLE IF NOT EXISTS `selectie_score` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `sollicitatie_id` INT NOT NULL,
            `test` VARCHAR(20) NOT NULL,
            `criterium` VARCHAR(40) NOT NULL,
            `score` TINYINT NOT NULL,
            `beoordelaar` VARCHAR(100) NOT NULL,
            `bijgewerkt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniek` (`seizoen_id`,`sollicitatie_id`,`test`,`criterium`,`beoordelaar`),
            INDEX (`sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Observaties: vrije notities per kandidaat, door wie dan ook, op iedereen.
        $db->exec("CREATE TABLE IF NOT EXISTS `selectie_observatie` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `sollicitatie_id` INT NOT NULL,
            `tekst` TEXT NOT NULL,
            `soort` ENUM('neutraal','positief','aandacht') NOT NULL DEFAULT 'neutraal',
            `beoordelaar` VARCHAR(100) NOT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Eindbesluit per kandidaat (fase 4, maar tabel alvast klaar)
        $db->exec("CREATE TABLE IF NOT EXISTS `selectie_besluit` (
            `sollicitatie_id` INT NOT NULL PRIMARY KEY,
            `seizoen_id` INT NOT NULL,
            `besluit` ENUM('door','twijfel','niet') DEFAULT NULL,
            `notitie` VARCHAR(300) DEFAULT NULL,
            `door` VARCHAR(100) DEFAULT NULL,
            `gebeld_op` DATETIME DEFAULT NULL,
            `gebeld_door` VARCHAR(100) DEFAULT NULL,
            `gebeld_notitie` VARCHAR(300) DEFAULT NULL,
            `doorgevoerd_op` DATETIME DEFAULT NULL,
            `bijgewerkt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`seizoen_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Bestaande databases: voeg de gebeld-kolommen toe als ze nog ontbreken
        try {
            $dbn = $db->query("SELECT DATABASE()")->fetchColumn();
            $bestaand = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='selectie_besluit'");
            $bestaand->execute([$dbn]);
            $kolommen = $bestaand->fetchAll(PDO::FETCH_COLUMN);
            $toe_te_voegen = [
                'gebeld_op'      => "ADD COLUMN `gebeld_op` DATETIME DEFAULT NULL",
                'gebeld_door'    => "ADD COLUMN `gebeld_door` VARCHAR(100) DEFAULT NULL",
                'gebeld_notitie' => "ADD COLUMN `gebeld_notitie` VARCHAR(300) DEFAULT NULL",
                'doorgevoerd_op' => "ADD COLUMN `doorgevoerd_op` DATETIME DEFAULT NULL",
            ];
            foreach ($toe_te_voegen as $kol => $sql) {
                if (!in_array($kol, $kolommen, true)) {
                    try { $db->exec("ALTER TABLE `selectie_besluit` $sql"); } catch (\Exception $e) {}
                }
            }
        } catch (\Exception $e) {}
    } catch (\Exception $e) { error_log('selectiedag_auto_setup: ' . $e->getMessage()); }
}

/** De sollicitanten die voor de selectiedag in aanmerking komen. */
function selectie_kandidaten(int $seizoen_id): array {
    selectiedag_auto_setup();
    try {
        $q = db()->prepare("
            SELECT s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum, p.geslacht, p.telefoon,
                   si.groep_id, si.aanwezig,
                   blk.foto_url, blk.bijzonderheden
            FROM sollicitaties s
            JOIN personen p ON p.id = s.persoon_id
            LEFT JOIN selectie_indeling si ON si.sollicitatie_id = s.id AND si.seizoen_id = s.seizoen_id
            LEFT JOIN blk_formulieren blk ON blk.sollicitatie_id = s.id
            WHERE s.seizoen_id = ? AND s.fase IN ('uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest')
            ORDER BY p.achternaam, p.voornaam
        ");
        $q->execute([$seizoen_id]);
        return array_map(function($r) {
            $r['naam'] = trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam']);
            $r['leeftijd'] = $r['geboortedatum'] ? (int)(new DateTime($r['geboortedatum']))->diff(new DateTime())->y : null;
            return $r;
        }, $q->fetchAll());
    } catch (\Exception $e) { return []; }
}

/**
 * Hoort deze sollicitatie bij de selectiedag van dit seizoen?
 *
 * Begeleiders (alleen 'lezen') mogen inchecken en beoordelen. Dan hoort de server ook
 * te controleren dat het om een kandidaat van déze selectiedag gaat, en niet om een
 * willekeurig id uit een ander seizoen of een andere fase.
 */
function selectie_is_kandidaat(int $seizoen_id, int $sol_id): bool {
    if ($seizoen_id <= 0 || $sol_id <= 0) return false;
    try {
        $q = db()->prepare("SELECT 1 FROM sollicitaties WHERE id=? AND seizoen_id=?
                            AND fase IN ('uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest')");
        $q->execute([$sol_id, $seizoen_id]);
        return (bool)$q->fetchColumn();
    } catch (\Exception $e) { return false; }
}

/** Bestaat dit criterium in de actieve criteria? (Zie selectie_criteria().) */
function selectie_criterium_bestaat(string $test, string $criterium): bool {
    foreach (selectie_criteria()[$test]['criteria'] ?? [] as $c) {
        if (($c['sleutel'] ?? null) === $criterium) return true;
    }
    return false;
}

/** Groepen van een seizoen. */
function selectie_groepen(int $seizoen_id): array {
    selectiedag_auto_setup();
    try {
        $q = db()->prepare("SELECT * FROM selectie_groep WHERE seizoen_id=? ORDER BY nummer");
        $q->execute([$seizoen_id]);
        return $q->fetchAll();
    } catch (\Exception $e) { return []; }
}

/** Zet het aantal groepen (maakt bij / verwijdert overtollige lege groepen). */
function selectie_groepen_zetten(int $seizoen_id, int $aantal): void {
    selectiedag_auto_setup();
    $aantal = max(1, min(20, $aantal));
    $db = db();
    $bestaand = selectie_groepen($seizoen_id);
    $huidige = count($bestaand);

    if ($aantal > $huidige) {
        $ins = $db->prepare("INSERT IGNORE INTO selectie_groep (seizoen_id, nummer) VALUES (?,?)");
        for ($n = $huidige + 1; $n <= $aantal; $n++) $ins->execute([$seizoen_id, $n]);
    } elseif ($aantal < $huidige) {
        // Verwijder de hoogste groepen; koppel hun sollicitanten los
        for ($n = $huidige; $n > $aantal; $n--) {
            $g = $bestaand[$n - 1];
            $db->prepare("UPDATE selectie_indeling SET groep_id=NULL WHERE groep_id=?")->execute([$g['id']]);
            $db->prepare("DELETE FROM selectie_groep WHERE id=?")->execute([$g['id']]);
        }
    }
}

/** Eén sollicitant in een groep zetten (of eruit met groep_id=null). */
function selectie_indeel(int $seizoen_id, int $sol_id, ?int $groep_id): void {
    selectiedag_auto_setup();
    db()->prepare("
        INSERT INTO selectie_indeling (seizoen_id, sollicitatie_id, groep_id)
        VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE groep_id=VALUES(groep_id)
    ")->execute([$seizoen_id, $sol_id, $groep_id]);
}

/** "Houd uit elkaar"-relaties van een seizoen als [sol_id => [ander_id, ...]]. */
function selectie_relaties(int $seizoen_id): array {
    selectiedag_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT sollicitatie_id, ander_id FROM selectie_relatie WHERE seizoen_id=?");
        $q->execute([$seizoen_id]);
        foreach ($q->fetchAll() as $r) {
            $uit[(int)$r['sollicitatie_id']][] = (int)$r['ander_id'];
            $uit[(int)$r['ander_id']][] = (int)$r['sollicitatie_id']; // wederzijds
        }
    } catch (\Exception $e) {}
    return $uit;
}

/** Relatie toevoegen/verwijderen (wederzijds opgeslagen als één paar). */
function selectie_relatie_zet(int $seizoen_id, int $a, int $b, bool $toevoegen): void {
    selectiedag_auto_setup();
    if ($a === $b) return;
    [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
    if ($toevoegen) {
        db()->prepare("INSERT IGNORE INTO selectie_relatie (seizoen_id, sollicitatie_id, ander_id) VALUES (?,?,?)")
           ->execute([$seizoen_id, $lo, $hi]);
    } else {
        db()->prepare("DELETE FROM selectie_relatie WHERE seizoen_id=? AND sollicitatie_id=? AND ander_id=?")
           ->execute([$seizoen_id, $lo, $hi]);
    }
}

/**
 * Stel een eerlijke indeling voor.
 *
 * Doelen, in volgorde van belangrijkheid:
 *  1. "Houd uit elkaar"-paren niet in dezelfde groep (hard geprobeerd, zacht gegarandeerd)
 *  2. Groepen even groot
 *  3. Gemengd op geslacht en leeftijd (groepen onderling vergelijkbaar)
 *
 * Retourneert [sol_id => groep_index (0-based)]. Schrijft NIET naar de database —
 * de UI toont het voorstel en de gebruiker bevestigt.
 */
function selectie_verdeel_voorstel(array $kandidaten, int $aantal_groepen, array $relaties = []): array {
    $aantal_groepen = max(1, $aantal_groepen);
    if (!$kandidaten) return [];

    // Sorteer op geslacht dan leeftijd, zodat "slangsgewijs" verdelen automatisch mengt
    usort($kandidaten, function($a, $b) {
        $ga = $a['geslacht'] ?? ''; $gb = $b['geslacht'] ?? '';
        if ($ga !== $gb) return strcmp($ga, $gb);
        return ($a['leeftijd'] ?? 99) <=> ($b['leeftijd'] ?? 99);
    });

    // Slangsgewijs ronddelen: 0,1,2,2,1,0,0,1,2,... → gelijkmatige, gemengde groepen
    $groepen = array_fill(0, $aantal_groepen, []);
    $volgorde = range(0, $aantal_groepen - 1);
    $i = 0;
    foreach ($kandidaten as $k) {
        $ronde = intdiv($i, $aantal_groepen);
        $pos = $i % $aantal_groepen;
        $g = $ronde % 2 === 0 ? $volgorde[$pos] : $volgorde[$aantal_groepen - 1 - $pos];
        $groepen[$g][] = $k['sol_id'];
        $i++;
    }

    // Bouw sol_id => groep_index
    $indeling = [];
    foreach ($groepen as $gi => $ids) foreach ($ids as $id) $indeling[$id] = $gi;

    // Los "houd uit elkaar"-conflicten op met ruilen
    if ($relaties && $aantal_groepen > 1) {
        $indeling = selectie_los_conflicten($indeling, $relaties, $aantal_groepen);
    }
    return $indeling;
}

/** Probeer botsende paren uit elkaar te halen door leden te ruilen met een andere groep. */
function selectie_los_conflicten(array $indeling, array $relaties, int $aantal_groepen): array {
    $groep_leden = function($idx) use ($indeling) {
        return array_keys(array_filter($indeling, fn($g) => $g === $idx));
    };
    $conflict = function($sol, $groep_idx) use ($relaties, &$indeling) {
        foreach ($relaties[$sol] ?? [] as $ander) {
            if (($indeling[$ander] ?? -1) === $groep_idx) return true;
        }
        return false;
    };

    for ($iter = 0; $iter < 200; $iter++) {
        $verplaatst = false;
        foreach ($indeling as $sol => $gi) {
            if (!$conflict($sol, $gi)) continue;
            // Zoek een groep waar 'sol' geen conflict heeft en ruil met een willekeurig lid daar
            for ($doel = 0; $doel < $aantal_groepen; $doel++) {
                if ($doel === $gi || $conflict($sol, $doel)) continue;
                $leden_doel = $groep_leden($doel);
                // Zoek een ruilpartner in de doelgroep die in 'gi' óók geen conflict oplevert
                foreach ($leden_doel as $partner) {
                    if (!$conflict($partner, $gi)) {
                        $indeling[$sol] = $doel;
                        $indeling[$partner] = $gi;
                        $verplaatst = true;
                        break 2;
                    }
                }
            }
        }
        if (!$verplaatst) break;
    }
    return $indeling;
}

/** Sla een voorgestelde/aangepaste indeling op. $map = [sol_id => groep_id|null]. */
function selectie_indeling_opslaan(int $seizoen_id, array $map): void {
    selectiedag_auto_setup();
    $db = db();
    $ins = $db->prepare("
        INSERT INTO selectie_indeling (seizoen_id, sollicitatie_id, groep_id)
        VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE groep_id=VALUES(groep_id)
    ");
    foreach ($map as $sol_id => $groep_id) {
        $ins->execute([$seizoen_id, (int)$sol_id, $groep_id !== null ? (int)$groep_id : null]);
    }
}

/**
 * Zet aanwezigheid van één sollicitant. Retourneert de nieuwe status + groepsinfo,
 * zodat het inchecken meteen kan tonen "je zit in groep 2".
 */
function selectie_aanwezig_zetten(int $seizoen_id, int $sol_id, bool $aanwezig): array {
    selectiedag_auto_setup();
    $db = db();
    // Regel moet bestaan (indeling kan nog leeg zijn)
    $db->prepare("
        INSERT INTO selectie_indeling (seizoen_id, sollicitatie_id, aanwezig, ingecheckt_op)
        VALUES (?,?,?, " . ($aanwezig ? "NOW()" : "NULL") . ")
        ON DUPLICATE KEY UPDATE aanwezig=VALUES(aanwezig),
            ingecheckt_op=" . ($aanwezig ? "IFNULL(ingecheckt_op, NOW())" : "NULL")
    )->execute([$seizoen_id, $sol_id, $aanwezig ? 1 : 0]);

    // Fase bijwerken bij aanmelden (zoals de oude flow deed)
    if ($aanwezig) {
        $db->prepare("UPDATE sollicitaties SET fase='selectiedag_geweest'
                      WHERE id=? AND fase IN ('uitgenodigd_selectie','aangemeld_selectie')")
           ->execute([$sol_id]);
    }

    // Groepsinfo teruggeven
    $q = $db->prepare("
        SELECT g.nummer, g.naam
        FROM selectie_indeling si
        LEFT JOIN selectie_groep g ON g.id = si.groep_id
        WHERE si.seizoen_id=? AND si.sollicitatie_id=?
    ");
    $q->execute([$seizoen_id, $sol_id]);
    $r = $q->fetch() ?: [];
    return [
        'aanwezig'     => $aanwezig,
        'groep_nummer' => $r['nummer'] ?? null,
        'groep_naam'   => $r['naam'] ?? null,
    ];
}

/**
 * Zoek kandidaten op (deel van de) naam — voor de tablet-zelfservice.
 * Alleen mensen die voor de selectiedag zijn uitgenodigd.
 */
function selectie_zoek_kandidaat(int $seizoen_id, string $term): array {
    selectiedag_auto_setup();
    $term = trim($term);
    if (mb_strlen($term) < 2) return [];
    try {
        $q = db()->prepare("
            SELECT s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam,
                   si.aanwezig, g.nummer AS groep_nummer, g.naam AS groep_naam
            FROM sollicitaties s
            JOIN personen p ON p.id = s.persoon_id
            LEFT JOIN selectie_indeling si ON si.sollicitatie_id = s.id AND si.seizoen_id = s.seizoen_id
            LEFT JOIN selectie_groep g ON g.id = si.groep_id
            WHERE s.seizoen_id = ?
              AND s.fase IN ('uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest')
              AND CONCAT_WS(' ', p.voornaam, p.tussenvoegsel, p.achternaam) LIKE ?
            ORDER BY p.voornaam, p.achternaam
            LIMIT 12
        ");
        $q->execute([$seizoen_id, '%' . $term . '%']);
        return array_map(function($r) {
            $r['naam'] = trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam']);
            return $r;
        }, $q->fetchAll());
    } catch (\Exception $e) { return []; }
}

// ─────────────────────────────────────────────────────────────
//  Beoordelen (fase 3): criteria, scores, observaties
// ─────────────────────────────────────────────────────────────

/** Standaard beoordelingscriteria uit het draaiboek. Per test een lijst. */
function selectie_default_criteria(): array {
    return [
        // 'icoon' is een naam uit includes/iconen.php, geen emoji. Bestaande
        // installaties hebben hier nog een emoji in de instelling staan; het scherm
        // vangt dat op met bh_test_icoon().
        'sport' => [
            'naam' => 'Sporttest',
            'icoon' => 'bliksem',
            'criteria' => [
                ['sleutel'=>'warmup',     'label'=>'Warming-up'],
                ['sleutel'=>'hardlopen',  'label'=>'Hardlopen'],
                ['sleutel'=>'sprint',     'label'=>'Sprint'],
                ['sleutel'=>'teamspeler', 'label'=>'Teamspeler'],
                ['sleutel'=>'sociaal',    'label'=>'Sociaal'],
                ['sleutel'=>'gedrag',     'label'=>'Gedrag'],
            ],
        ],
        'zwem' => [
            'naam' => 'Zwemtest',
            'icoon' => 'golf',
            'criteria' => [
                ['sleutel'=>'schoolslag',  'label'=>'Schoolslag'],
                ['sleutel'=>'polocrawl',   'label'=>'Polocrawl'],
                ['sleutel'=>'onderwater',  'label'=>'Onderwater'],
                ['sleutel'=>'vervoeren',   'label'=>'Vervoeren'],
                ['sleutel'=>'teamspeler',  'label'=>'Teamspeler'],
                ['sleutel'=>'sociaal',     'label'=>'Sociaal'],
                ['sleutel'=>'gedrag',      'label'=>'Gedrag'],
            ],
        ],
    ];
}

/** Actieve criteria: uit instelling (JSON) of de draaiboek-default. */
function selectie_criteria(): array {
    $json = function_exists('instelling') ? instelling('selectie.criteria', '') : '';
    if ($json) {
        $d = json_decode($json, true);
        if (is_array($d) && $d) return $d;
    }
    return selectie_default_criteria();
}

function selectie_criteria_opslaan(array $criteria): void {
    if (function_exists('instelling_opslaan')) {
        instelling_opslaan('selectie.criteria', json_encode($criteria, JSON_UNESCAPED_UNICODE));
    }
}

/** Eén score opslaan (of wissen bij score 0). Beoordelaar-gebonden, dus geen overschrijven van anderen. */
function selectie_score_opslaan(int $seizoen_id, int $sol_id, string $test, string $criterium, int $score, string $beoordelaar): void {
    selectiedag_auto_setup();
    $score = max(0, min(5, $score));
    $db = db();
    if ($score === 0) {
        $db->prepare("DELETE FROM selectie_score WHERE seizoen_id=? AND sollicitatie_id=? AND test=? AND criterium=? AND beoordelaar=?")
           ->execute([$seizoen_id, $sol_id, $test, $criterium, $beoordelaar]);
        return;
    }
    $db->prepare("
        INSERT INTO selectie_score (seizoen_id, sollicitatie_id, test, criterium, score, beoordelaar)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE score=VALUES(score), bijgewerkt_op=NOW()
    ")->execute([$seizoen_id, $sol_id, $test, $criterium, $score, $beoordelaar]);
}

/**
 * Scores van één kandidaat, gegroepeerd voor weergave:
 *  [test][criterium] => ['eigen'=>score van deze beoordelaar, 'allen'=>[beoordelaar=>score], 'gem'=>gemiddelde]
 */
function selectie_scores_van(int $seizoen_id, int $sol_id, string $beoordelaar = ''): array {
    selectiedag_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT test, criterium, score, beoordelaar FROM selectie_score WHERE seizoen_id=? AND sollicitatie_id=?");
        $q->execute([$seizoen_id, $sol_id]);
        foreach ($q->fetchAll() as $r) {
            $t = $r['test']; $c = $r['criterium'];
            if (!isset($uit[$t][$c])) $uit[$t][$c] = ['eigen'=>0, 'allen'=>[], 'gem'=>0];
            $uit[$t][$c]['allen'][$r['beoordelaar']] = (int)$r['score'];
            if ($beoordelaar !== '' && $r['beoordelaar'] === $beoordelaar) $uit[$t][$c]['eigen'] = (int)$r['score'];
        }
        foreach ($uit as $t => $crits) foreach ($crits as $c => $data) {
            $vals = $data['allen'];
            $uit[$t][$c]['gem'] = $vals ? round(array_sum($vals) / count($vals), 1) : 0;
        }
    } catch (\Exception $e) {}
    return $uit;
}

/** Observatie toevoegen. */
function selectie_observatie_toevoegen(int $seizoen_id, int $sol_id, string $tekst, string $soort, string $beoordelaar): ?int {
    selectiedag_auto_setup();
    $tekst = trim($tekst);
    if ($tekst === '') return null;
    $soort = in_array($soort, ['neutraal','positief','aandacht'], true) ? $soort : 'neutraal';
    try {
        db()->prepare("INSERT INTO selectie_observatie (seizoen_id, sollicitatie_id, tekst, soort, beoordelaar) VALUES (?,?,?,?,?)")
           ->execute([$seizoen_id, $sol_id, mb_substr($tekst, 0, 2000), $soort, $beoordelaar]);
        return (int)db()->lastInsertId();
    } catch (\Exception $e) { return null; }
}

/** Observatie verwijderen (alleen eigen observatie, tenzij bestuur). */
function selectie_observatie_verwijderen(int $id, string $beoordelaar, bool $mag_alles): bool {
    selectiedag_auto_setup();
    try {
        if ($mag_alles) {
            db()->prepare("DELETE FROM selectie_observatie WHERE id=?")->execute([$id]);
        } else {
            db()->prepare("DELETE FROM selectie_observatie WHERE id=? AND beoordelaar=?")->execute([$id, $beoordelaar]);
        }
        return true;
    } catch (\Exception $e) { return false; }
}

/** Observaties van één kandidaat (nieuwste eerst). */
function selectie_observaties_van(int $seizoen_id, int $sol_id): array {
    selectiedag_auto_setup();
    try {
        $q = db()->prepare("SELECT * FROM selectie_observatie WHERE seizoen_id=? AND sollicitatie_id=? ORDER BY aangemaakt_op DESC");
        $q->execute([$seizoen_id, $sol_id]);
        return $q->fetchAll();
    } catch (\Exception $e) { return []; }
}

/**
 * Alle observaties van het seizoen, gegroepeerd per kandidaat.
 *
 * Nodig op de nabespreking van het trainingsweekend: daar wil je zien wat er op de
 * selectiedag over dezelfde persoon is gezegd, en dat is precies het moment waarop het
 * telt. Eén query in plaats van één per kandidaat.
 *
 * De twee tabellen blijven bewust gescheiden (zie includes/trainingsweekend.php) —
 * dit leest alleen mee, het schrijft niets over.
 */
function selectie_observaties_alle(int $seizoen_id): array {
    selectiedag_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT * FROM selectie_observatie WHERE seizoen_id=?
                            ORDER BY aangemaakt_op DESC, id DESC");
        $q->execute([$seizoen_id]);
        foreach ($q->fetchAll() as $o) $uit[(int)$o['sollicitatie_id']][] = $o;
    } catch (\Exception $e) {}
    return $uit;
}

/** Aantal observaties per kandidaat (voor telling in lijsten). */
function selectie_observatie_tellingen(int $seizoen_id): array {
    selectiedag_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT sollicitatie_id, COUNT(*) AS n FROM selectie_observatie WHERE seizoen_id=? GROUP BY sollicitatie_id");
        $q->execute([$seizoen_id]);
        foreach ($q->fetchAll() as $r) $uit[(int)$r['sollicitatie_id']] = (int)$r['n'];
    } catch (\Exception $e) {}
    return $uit;
}

// ─────────────────────────────────────────────────────────────
//  Nabespreking (fase 4): eindbesluit per kandidaat
// ─────────────────────────────────────────────────────────────

/** Het besluit van één kandidaat ophalen (of null). */
function selectie_besluit_van(int $seizoen_id, int $sol_id): ?array {
    selectiedag_auto_setup();
    try {
        $q = db()->prepare("SELECT * FROM selectie_besluit WHERE sollicitatie_id=? AND seizoen_id=?");
        $q->execute([$sol_id, $seizoen_id]);
        return $q->fetch() ?: null;
    } catch (\Exception $e) { return null; }
}

/** Alle besluiten van een seizoen als [sol_id => besluit-rij]. */
function selectie_besluiten(int $seizoen_id): array {
    selectiedag_auto_setup();
    $uit = [];
    try {
        $q = db()->prepare("SELECT * FROM selectie_besluit WHERE seizoen_id=?");
        $q->execute([$seizoen_id]);
        foreach ($q->fetchAll() as $r) $uit[(int)$r['sollicitatie_id']] = $r;
    } catch (\Exception $e) {}
    return $uit;
}

/**
 * Eindbesluit opslaan. 'door' | 'twijfel' | 'niet' | '' (wissen).
 * Het besluit is indicatief; de fase wordt NIET automatisch aangepast —
 * dat doet het bestuur bewust op de bellijst, met selectie_besluit_doorvoeren_een() of
 * selectie_gebelde_doorvoeren(). Alles-in-één-keer doorvoeren bestaat bewust niet meer:
 * dan ging er bericht uit vóórdat er gebeld was.
 */
function selectie_besluit_zetten(int $seizoen_id, int $sol_id, string $besluit, string $notitie, string $door): void {
    selectiedag_auto_setup();
    if ($besluit === '') {
        db()->prepare("DELETE FROM selectie_besluit WHERE sollicitatie_id=? AND seizoen_id=?")->execute([$sol_id, $seizoen_id]);
        return;
    }
    if (!in_array($besluit, ['door','twijfel','niet'], true)) return;
    db()->prepare("
        INSERT INTO selectie_besluit (sollicitatie_id, seizoen_id, besluit, notitie, door)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE besluit=VALUES(besluit), notitie=VALUES(notitie), door=VALUES(door), bijgewerkt_op=NOW()
    ")->execute([$sol_id, $seizoen_id, $besluit, mb_substr(trim($notitie), 0, 300), $door]);
}

/**
 * Markeer één kandidaat als gebeld (met optionele notitie).
 * Als $meteen_doorvoeren true is, wordt de fase meteen doorgezet.
 */
function selectie_gebeld_zetten(int $seizoen_id, int $sol_id, string $notitie, string $door, bool $meteen_doorvoeren = false): array {
    selectiedag_auto_setup();
    $db = db();
    $db->prepare("UPDATE selectie_besluit SET gebeld_op=NOW(), gebeld_door=?, gebeld_notitie=? WHERE sollicitatie_id=? AND seizoen_id=?")
       ->execute([$door, mb_substr(trim($notitie), 0, 300), $sol_id, $seizoen_id]);
    $doorgevoerd = false;
    if ($meteen_doorvoeren) {
        $doorgevoerd = selectie_besluit_doorvoeren_een($seizoen_id, $sol_id);
    }
    return ['gebeld' => true, 'doorgevoerd' => $doorgevoerd];
}

/** Verwijder de gebeld-markering (per ongeluk aangevinkt). */
function selectie_gebeld_wissen(int $seizoen_id, int $sol_id): void {
    selectiedag_auto_setup();
    db()->prepare("UPDATE selectie_besluit SET gebeld_op=NULL, gebeld_door=NULL, gebeld_notitie=NULL WHERE sollicitatie_id=? AND seizoen_id=?")
       ->execute([$sol_id, $seizoen_id]);
}

/** Voer één kandidaat door (door → uitgenodigd_tw, niet → afgewezen). Retourneert of het lukte. */
function selectie_besluit_doorvoeren_een(int $seizoen_id, int $sol_id): bool {
    selectiedag_auto_setup();
    $db = db();
    $q = $db->prepare("SELECT besluit FROM selectie_besluit WHERE sollicitatie_id=? AND seizoen_id=?");
    $q->execute([$sol_id, $seizoen_id]);
    $besluit = $q->fetchColumn();
    $naar = match ($besluit) {
        'door' => 'uitgenodigd_tw',
        'niet' => 'afgewezen',
        default => null,
    };
    if ($naar === null || !function_exists('fase_zetten')) return false;
    // Centrale fase-functie: 'door' → uitnodiging trainingsweekend; 'niet' → meldingstype 'afgewezen'
    // (staat standaard uit: dat doe je persoonlijk, telefonisch).
    $res = fase_zetten($sol_id, $naar, 'bellijst', [
        'seizoen_id' => $seizoen_id,
        'alleen_van' => ['uitgenodigd_selectie', 'aangemeld_selectie', 'selectiedag_geweest'],
    ]);
    if ($res['ok'] && !$res['ongewijzigd']) {
        $db->prepare("UPDATE selectie_besluit SET doorgevoerd_op=NOW() WHERE sollicitatie_id=? AND seizoen_id=?")
           ->execute([$sol_id, $seizoen_id]);
        return true;
    }
    return false;
}

/** Voer alle 'gebelde maar nog niet doorgevoerde' kandidaten in één keer door. */
function selectie_gebelde_doorvoeren(int $seizoen_id): int {
    selectiedag_auto_setup();
    $db = db();
    $q = $db->prepare("SELECT sollicitatie_id FROM selectie_besluit
                       WHERE seizoen_id=? AND gebeld_op IS NOT NULL AND doorgevoerd_op IS NULL
                       AND besluit IN ('door','niet')");
    $q->execute([$seizoen_id]);
    $n = 0;
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $sol_id) {
        if (selectie_besluit_doorvoeren_een($seizoen_id, (int)$sol_id)) $n++;
    }
    return $n;
}

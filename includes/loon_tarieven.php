<?php
/**
 * Paal 13 — Loontarieven per kalenderjaar (module Loonplanning). GEEN HTML.
 *
 * Waarom per jaar: het wettelijk minimumloon verandert elk jaar (en soms halverwege, vandaar juni
 * en juli–september). Met één globale tabel rekende een nieuw tarief ook alle oude seizoenen opnieuw
 * door. Nu hoort bij elk kalenderjaar een eigen pakket:
 *   loon_tarieven  (jaar, leeftijd, periode) → minimumuurloon excl. vakantietoeslag
 *   loon_jaar      (jaar) → vakantietoeslag %, werkuren per dag, vast weekbedrag postcommandant, budget
 *
 * Welk jaar bij een seizoen hoort: loon_seizoen_jaar(). Bron is de start van de werkperiode —
 * dezelfde datum waar de loonplanning zijn weken (seizoen_weken_datums) en loon_planning.jaar uit
 * haalt, en het jaar waarin het loon wordt betaald. Ontbreekt die, dan de eind-/TW-/selectiedatum,
 * dan een jaartal in de naam, dan het aanmaakjaar.
 *
 * Ontbreken de tarieven van een jaar, dan rekenen we met het dichtstbijzijnde eerdere jaar en
 * zeggen dat hardop (loon_tarieven_melding). Het budget valt bewust NIET terug: een budget van
 * vorig jaar is geen budget voor dit jaar.
 *
 * Budget per jaar en niet per seizoen: de vereniging begroot per boekjaar (= kalenderjaar) en een
 * seizoen (juni–september) valt altijd binnen één kalenderjaar. Zo staat alles wat bij "het jaar
 * 2027" hoort op één plek met één jaarkeuze in Instellingen › Lonen & budget.
 *
 * Alle berekeningen lezen via loon_tarieven_voor_seizoen() / loon_tarieven_voor_jaar() en rekenen
 * via loon_dagloon(). De functies (factor per functie) zijn niet per jaar: dat is een eigen keuze
 * van de vereniging, geen wettelijk tarief.
 */

if (!function_exists('loon_seizoen_jaar')) {

/** De twee loonperiodes binnen een seizoen. */
function loon_periodes(): array {
    return ['jun' => 'Juni', 'jul_sep' => 'Juli – september'];
}

/** Leeftijden waarvoor een tarief wordt ingevuld. Daarboven geldt het tarief van de hoogste leeftijd. */
function loon_leeftijden(): array {
    return range(15, 32);
}

/** Getal zonder overbodige nullen, Nederlandse komma: 8 → "8", 1.08 → "1,08". */
function loon_getal(float $v, int $dec = 4): string {
    $s = rtrim(rtrim(number_format($v, $dec, ',', ''), '0'), ',');
    return $s === '' || $s === '-' ? '0' : $s;
}

/** Het kalenderjaar van een seizoen (zie de uitleg bovenaan). */
function loon_seizoen_jaar(?array $seizoen): int {
    if ($seizoen) {
        foreach (['datum_werk_start', 'datum_werk_eind', 'datum_tw_start', 'datum_selectiedag'] as $k) {
            if (preg_match('/^(\d{4})-\d{2}-\d{2}/', (string)($seizoen[$k] ?? ''), $m) && (int)$m[1] > 1900) return (int)$m[1];
        }
        if (preg_match('/\b(20\d{2})\b/', (string)($seizoen['naam'] ?? ''), $m)) return (int)$m[1];
        if (preg_match('/^(\d{4})-/', (string)($seizoen['aangemaakt_op'] ?? ''), $m) && (int)$m[1] > 1900) return (int)$m[1];
    }
    return (int)date('Y');
}

/** Standaardtarieven (2026) voor een verse installatie. Per uur, excl. vakantietoeslag. */
function loon_standaard_tarieven(): array {
    return [
        'jun'     => [15=>4.41,16=>5.07,17=>5.81,18=>7.36,19=>8.83,20=>11.77,21=>14.71,22=>14.71,23=>14.71,24=>11.16,25=>11.16,26=>11.16,27=>11.16,28=>11.16,29=>11.16,30=>11.16,31=>11.16,32=>11.16],
        'jul_sep' => [15=>4.32,16=>4.97,17=>5.69,18=>7.20,19=>8.64,20=>11.52,21=>14.40,22=>14.40,23=>14.40,24=>11.16,25=>11.16,26=>11.16,27=>11.16,28=>11.16,29=>11.16,30=>11.16,31=>11.16,32=>11.16],
    ];
}

/**
 * Jaarparameters als er voor een jaar niets is vastgelegd: de oude (globale) instellingen.
 * Die worden sinds de jaartabel niet meer bewerkt; ze zijn alleen nog de terugval.
 */
function loon_jaar_standaard(): array {
    $f = fn(string $k, float $d) => function_exists('instelling_float') ? instelling_float($k, $d) : $d;
    return ['vt_percentage' => $f('loon.vt_percentage', 8), 'uren_per_dag' => $f('loon.uren_per_dag', 8),
            'pc_vast_juni'  => $f('loon.pc_vast_juni', 850), 'pc_vast_jul_sep' => $f('loon.pc_vast_jul_sep', 750)];
}

/** Grenzen per jaarparameter (ook de kolombreedte: MariaDB kapt stil af). */
function loon_jaar_velden(): array {
    return [
        'vt_percentage'   => ['min' => 0, 'max' => 50],
        'uren_per_dag'    => ['min' => 1, 'max' => 12],
        'pc_vast_juni'    => ['min' => 0, 'max' => 99999],
        'pc_vast_jul_sep' => ['min' => 0, 'max' => 99999],
        'budget'          => ['min' => 0, 'max' => 99999999],
    ];
}

/**
 * Schema: jaar op loon_tarieven, de tabel loon_jaar, en eenmalig de migratie.
 * Migratie: bestaande tarieven (zonder jaar) en de oude globale instellingen (VT, werkuren,
 * postcommandant, loon.budget_seizoen) worden die van het jaar van het huidige/nieuwste seizoen.
 */
function loon_tarieven_auto_setup(): void {
    static $done = false;
    if ($done || (function_exists('schema_actueel') && schema_actueel())) return;
    $done = true;
    try {
        $db = db();
        if (!db_tabel_bestaat($db, 'loon_tarieven')) $db->exec("CREATE TABLE IF NOT EXISTS `loon_tarieven` (
            `id`          INT          AUTO_INCREMENT PRIMARY KEY,
            `jaar`        SMALLINT     NOT NULL DEFAULT 0,
            `leeftijd`    INT          NOT NULL,
            `periode`     VARCHAR(10)  NOT NULL,
            `min_uurloon` DECIMAL(6,4) NOT NULL,
            UNIQUE KEY `jaar_leeftijd_periode` (`jaar`,`leeftijd`,`periode`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db_kolom_verzeker($db, 'loon_tarieven', 'jaar', 'SMALLINT NOT NULL DEFAULT 0');

        $jaar = loon_seizoen_jaar(function_exists('actief_seizoen') ? actief_seizoen() : null);
        // Tarieven van vóór deze versie hebben geen jaar: ze horen bij het huidige seizoen.
        try { $db->prepare("UPDATE `loon_tarieven` SET jaar = ? WHERE jaar = 0")->execute([$jaar]); }
        catch (\Exception $e) { error_log('loon_tarieven jaar zetten: ' . $e->getMessage()); }

        // Uniek per jaar. Eerst de nieuwe sleutel, dan pas de oude weg: er is altijd één unieke sleutel.
        if (!db_index_bestaat($db, 'loon_tarieven', 'jaar_leeftijd_periode')) {
            try { $db->exec("ALTER TABLE `loon_tarieven` ADD UNIQUE KEY `jaar_leeftijd_periode` (`jaar`,`leeftijd`,`periode`)"); }
            catch (\Exception $e) { error_log('loon_tarieven uniek: ' . $e->getMessage()); }
        }
        if (db_index_bestaat($db, 'loon_tarieven', 'jaar_leeftijd_periode') && db_index_bestaat($db, 'loon_tarieven', 'leeftijd_periode')) {
            // Zolang deze bestaat, kan er maar één jaar in de tabel: een nieuw jaar zou botsen.
            try { $db->exec("ALTER TABLE `loon_tarieven` DROP INDEX `leeftijd_periode`"); }
            catch (\Exception $e) { error_log('loon_tarieven drop leeftijd_periode: ' . $e->getMessage()); }
        }

        if (!db_tabel_bestaat($db, 'loon_jaar')) $db->exec("CREATE TABLE IF NOT EXISTS `loon_jaar` (
            `jaar`            SMALLINT      NOT NULL PRIMARY KEY,
            `vt_percentage`   DECIMAL(5,2)  DEFAULT NULL,
            `uren_per_dag`    DECIMAL(4,2)  DEFAULT NULL,
            `pc_vast_juni`    DECIMAL(8,2)  DEFAULT NULL,
            `pc_vast_jul_sep` DECIMAL(8,2)  DEFAULT NULL,
            `budget`          DECIMAL(10,2) DEFAULT NULL,
            `bijgewerkt_op`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Eenmalig: de oude globale instellingen worden die van dit jaar.
        if ((int)$db->query("SELECT COUNT(*) FROM `loon_jaar`")->fetchColumn() === 0) {
            $st = loon_jaar_standaard();
            $budget = function_exists('instelling_float') ? instelling_float('loon.budget_seizoen', 0) : 0.0;
            $db->prepare("INSERT INTO `loon_jaar` (jaar, vt_percentage, uren_per_dag, pc_vast_juni, pc_vast_jul_sep, budget) VALUES (?,?,?,?,?,?)")
               ->execute([$jaar, $st['vt_percentage'], $st['uren_per_dag'], $st['pc_vast_juni'], $st['pc_vast_jul_sep'], $budget > 0 ? $budget : null]);
        }

        // Verse installatie: de standaardtarieven voor dit jaar.
        if ((int)$db->query("SELECT COUNT(*) FROM `loon_tarieven`")->fetchColumn() === 0) {
            $ins = $db->prepare("INSERT INTO `loon_tarieven` (jaar, leeftijd, periode, min_uurloon) VALUES (?,?,?,?)");
            foreach (loon_standaard_tarieven() as $periode => $rij) {
                foreach ($rij as $lft => $loon) $ins->execute([$jaar, $lft, $periode, $loon]);
            }
        }
    } catch (\Exception $e) { error_log('loon_tarieven_auto_setup: ' . $e->getMessage()); }
}

/** Leeg de cache na een wijziging (zelfde verzoek). */
function loon_cache_leeg(): void {
    $GLOBALS['_p13_loon_cache'] = [];
}

/** Jaren waarvoor tarieven zijn ingevuld, oplopend. */
function loon_tarief_jaren(): array {
    loon_tarieven_auto_setup();
    if (isset($GLOBALS['_p13_loon_cache']['jaren'])) return $GLOBALS['_p13_loon_cache']['jaren'];
    try {
        $j = array_map('intval', db()->query("SELECT DISTINCT jaar FROM `loon_tarieven` WHERE jaar > 0 ORDER BY jaar")->fetchAll(\PDO::FETCH_COLUMN));
    } catch (\Exception $e) { $j = []; }
    return $GLOBALS['_p13_loon_cache']['jaren'] = $j;
}

/** De rij uit loon_jaar (of null). */
function loon_jaar_rij(int $jaar): ?array {
    try {
        $q = db()->prepare("SELECT * FROM `loon_jaar` WHERE jaar = ?");
        $q->execute([$jaar]);
        return $q->fetch() ?: null;
    } catch (\Exception $e) { return null; }
}

/**
 * Het tariefpakket voor een kalenderjaar.
 * @return array{jaar:int, bron_jaar:?int, terugval:bool, leeg:bool,
 *               tarieven:array{jun:array<int,float>, jul_sep:array<int,float>},
 *               vt_percentage:float, uren_per_dag:float, pc_vast_juni:float, pc_vast_jul_sep:float, budget:float}
 *   bron_jaar = het jaar waarvan de tarieven en parameters komen (null: er zijn nergens tarieven)
 *   budget    = alléén van het gevraagde jaar (0 = geen budget), valt nooit terug
 */
function loon_tarieven_voor_jaar(int $jaar): array {
    if (isset($GLOBALS['_p13_loon_cache']['jaar'][$jaar])) return $GLOBALS['_p13_loon_cache']['jaar'][$jaar];
    $jaren = loon_tarief_jaren();
    $bron = null;
    foreach ($jaren as $j) if ($j <= $jaar) $bron = $j;           // dichtstbijzijnde eerdere (of hetzelfde) jaar
    if ($bron === null && $jaren) $bron = $jaren[0];              // alleen latere jaren: het dichtstbijzijnde

    $tarieven = ['jun' => [], 'jul_sep' => []];
    if ($bron !== null) {
        try {
            $q = db()->prepare("SELECT leeftijd, periode, min_uurloon FROM `loon_tarieven` WHERE jaar = ? ORDER BY leeftijd");
            $q->execute([$bron]);
            foreach ($q->fetchAll() as $r) $tarieven[(string)$r['periode']][(int)$r['leeftijd']] = (float)$r['min_uurloon'];
        } catch (\Exception $e) {}
    }
    $st   = loon_jaar_standaard();
    $par  = $bron !== null ? (loon_jaar_rij($bron) ?? []) : [];
    $eigen = $bron === $jaar ? $par : (loon_jaar_rij($jaar) ?? []);
    $uit = ['jaar' => $jaar, 'bron_jaar' => $bron, 'terugval' => $bron !== null && $bron !== $jaar, 'leeg' => $bron === null,
            'tarieven' => $tarieven];
    foreach ($st as $k => $d) $uit[$k] = isset($par[$k]) && $par[$k] !== null ? (float)$par[$k] : (float)$d;
    $uit['budget'] = isset($eigen['budget']) && $eigen['budget'] !== null ? (float)$eigen['budget'] : 0.0;
    return $GLOBALS['_p13_loon_cache']['jaar'][$jaar] = $uit;
}

/** Het tariefpakket voor een seizoen (array, id, of null = het actieve seizoen). */
function loon_tarieven_voor_seizoen($seizoen = null): array {
    if (is_int($seizoen)) $seizoen = function_exists('seizoen_op_id') ? seizoen_op_id($seizoen) : null;
    elseif ($seizoen === null && function_exists('actief_seizoen')) $seizoen = actief_seizoen();
    return loon_tarieven_voor_jaar(loon_seizoen_jaar(is_array($seizoen) ? $seizoen : null));
}

/** Zin voor op het scherm en in de export als er een ander jaar wordt gebruikt; '' als alles klopt. */
function loon_tarieven_melding(array $t): string {
    if (!empty($t['leeg'])) return 'Er zijn nog geen loontarieven ingevuld; de loonkosten zijn niet te berekenen.';
    if (!empty($t['terugval'])) {
        return 'Tarieven ' . $t['jaar'] . ' nog niet ingevuld, we rekenen met ' . $t['bron_jaar'] . '.';
    }
    return '';
}

/**
 * Dagloon uit een tariefpakket: minimumuurloon (leeftijd, periode) × uren per dag × factor × (1 + VT).
 * Postcommandant: vast weekbedrag ÷ 5. Ontbreekt de leeftijd, dan die van de dichtstbijzijnde leeftijd.
 */
function loon_dagloon(array $t, int $leeftijd, string $functie_sleutel, string $periode, ?float $override = null): float {
    if ($override !== null) return $override;
    $periode = $periode === 'jun' ? 'jun' : 'jul_sep';
    if ($functie_sleutel === 'postcommandant') {
        return round(($periode === 'jun' ? $t['pc_vast_juni'] : $t['pc_vast_jul_sep']) / 5, 2);
    }
    $factor = 1.20; // standaard lifeguard
    foreach ((function_exists('functies_ophalen') ? functies_ophalen() : []) as $f) {
        if ($f['sleutel'] === $functie_sleutel) { $factor = (float)$f['factor']; break; }
    }
    $rij = $t['tarieven'][$periode] ?? [];
    if (!$rij) return 0.0;
    if (isset($rij[$leeftijd])) {
        $uurloon = $rij[$leeftijd];
    } else {
        $best = null;
        foreach ($rij as $lft => $loon) if ($best === null || abs($lft - $leeftijd) < abs($best - $leeftijd)) $best = $lft;
        $uurloon = $rij[$best];
    }
    return round($uurloon * $t['uren_per_dag'] * $factor * (1 + $t['vt_percentage'] / 100), 2);
}

/** Jaren om uit te kiezen in Instellingen: met tarieven, van een seizoen, en dit jaar. Nieuwste eerst. */
function loon_jaar_keuzes(): array {
    $j = loon_tarief_jaren();
    $j[] = (int)date('Y');
    try {
        foreach (db()->query("SELECT * FROM seizoenen")->fetchAll() as $s) $j[] = loon_seizoen_jaar($s);
    } catch (\Exception $e) {}
    $j = array_values(array_unique($j));
    rsort($j);
    return $j;
}

/** Seizoenen per jaar (voor "geldt voor seizoen …"): [jaar => [naam, …]]. */
function loon_seizoenen_per_jaar(): array {
    $uit = [];
    try {
        foreach (db()->query("SELECT * FROM seizoenen ORDER BY id")->fetchAll() as $s) $uit[loon_seizoen_jaar($s)][] = (string)$s['naam'];
    } catch (\Exception $e) {}
    return $uit;
}

/**
 * Minimumuurlonen van één jaar en periode opslaan. Lege of ongeldige waarden worden overgeslagen.
 * @return int aantal opgeslagen leeftijden
 */
function loon_tarieven_opslaan(int $jaar, string $periode, array $lonen): int {
    loon_tarieven_auto_setup();
    if ($jaar < 2000 || $jaar > 2100 || !isset(loon_periodes()[$periode])) return 0;
    $n = 0;
    try {
        $st = db()->prepare("INSERT INTO `loon_tarieven` (jaar, leeftijd, periode, min_uurloon) VALUES (?,?,?,?)
                             ON DUPLICATE KEY UPDATE min_uurloon=VALUES(min_uurloon)");
        foreach ($lonen as $lft => $loon) {
            $lft = (int)$lft;
            $v = (float)str_replace(',', '.', (string)$loon);
            if (!in_array($lft, loon_leeftijden(), true) || $v <= 0 || $v >= 100) continue;   // DECIMAL(6,4)
            $st->execute([$jaar, $lft, $periode, round($v, 4)]);
            $n++;
        }
    } catch (\Exception $e) { error_log('loon_tarieven_opslaan: ' . $e->getMessage()); }
    loon_cache_leeg();
    return $n;
}

/**
 * Jaarparameters en/of budget opslaan. Alleen de meegegeven velden; een leeg budget = geen budget.
 * @return string[] de velden die zijn opgeslagen
 */
function loon_jaar_opslaan(int $jaar, array $waarden): array {
    loon_tarieven_auto_setup();
    if ($jaar < 2000 || $jaar > 2100) return [];
    $kol = []; $par = [];
    foreach (loon_jaar_velden() as $k => $g) {
        if (!array_key_exists($k, $waarden)) continue;
        $ruw = trim((string)$waarden[$k]);
        if ($ruw === '' && $k === 'budget') { $kol[] = $k; $par[] = null; continue; }
        $v = (float)str_replace(',', '.', $ruw);
        if ($ruw === '' || $v < $g['min'] || $v > $g['max']) continue;
        $kol[] = $k; $par[] = round($v, 2);
    }
    if (!$kol) return [];
    try {
        $upd = implode(', ', array_map(fn($k) => "$k=VALUES($k)", $kol));   // $kol komt uit loon_jaar_velden()
        db()->prepare("INSERT INTO `loon_jaar` (jaar, `" . implode('`, `', $kol) . "`) VALUES (?" . str_repeat(',?', count($kol)) . ")
                       ON DUPLICATE KEY UPDATE $upd")->execute(array_merge([$jaar], $par));
    } catch (\Exception $e) { error_log('loon_jaar_opslaan: ' . $e->getMessage()); return []; }
    loon_cache_leeg();
    return $kol;
}

/**
 * Een nieuw jaar maken op basis van een ander: tarieven en parameters (niet het budget) kopiëren.
 * Doet niets als het doeljaar al tarieven heeft — dan zou je ingevulde bedragen overschrijven.
 */
function loon_jaar_kopieer(int $van, int $naar): bool {
    loon_tarieven_auto_setup();
    if ($naar < 2000 || $naar > 2100 || $van === $naar || !in_array($van, loon_tarief_jaren(), true) || in_array($naar, loon_tarief_jaren(), true)) return false;
    try {
        $db = db();
        $db->prepare("INSERT INTO `loon_tarieven` (jaar, leeftijd, periode, min_uurloon)
                      SELECT ?, leeftijd, periode, min_uurloon FROM `loon_tarieven` WHERE jaar = ?")->execute([$naar, $van]);
        $t = loon_tarieven_voor_jaar($van);
        loon_cache_leeg();
        loon_jaar_opslaan($naar, ['vt_percentage' => $t['vt_percentage'], 'uren_per_dag' => $t['uren_per_dag'],
                                  'pc_vast_juni' => $t['pc_vast_juni'], 'pc_vast_jul_sep' => $t['pc_vast_jul_sep']]);
    } catch (\Exception $e) { error_log('loon_jaar_kopieer: ' . $e->getMessage()); loon_cache_leeg(); return false; }
    loon_cache_leeg();
    return true;
}

/**
 * Dagloon voor een leeftijd, functie en periode in een kalenderjaar (null = het jaar van het actieve
 * seizoen). Per verzoek onthouden: de loonplanning vraagt dit honderden keren.
 * (Eigen guard: het renderharnas heeft een stub met dezelfde naam.)
 */
if (!function_exists('bereken_dagloon')) {
function bereken_dagloon(int $leeftijd, string $functie_sleutel, string $periode = 'jun', ?float $override = null, ?int $jaar = null): float {
    if ($override !== null) return $override;
    if ($jaar === null) $jaar = loon_seizoen_jaar(function_exists('actief_seizoen') ? actief_seizoen() : null);
    $k = $jaar . '|' . $leeftijd . '|' . $functie_sleutel . '|' . $periode;
    if (isset($GLOBALS['_p13_loon_cache']['dag'][$k])) return $GLOBALS['_p13_loon_cache']['dag'][$k];
    return $GLOBALS['_p13_loon_cache']['dag'][$k] = loon_dagloon(loon_tarieven_voor_jaar($jaar), $leeftijd, $functie_sleutel, $periode);
}
}

}

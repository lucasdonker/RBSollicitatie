<?php
/**
 * Paal 13 — Beschikbaarheid 2.0 (periode-blokken model)
 *
 * KISS voor de sollicitant:
 *   - Eén of meer PERIODE-BLOKKEN: van week X tot week Y, met regime:
 *       'vijf'    = hele weken (5 dagen)        → norm 5
 *       'weekend' = alleen weekenden (vr/za/zo) → norm 3
 *   - AFWEZIGHEID: datumperiodes (vakantie), dag-precies verwerkt
 *   - VASTE VRIJE DAG(EN): weekdagen die iemand structureel niet kan
 *       (bij weekenddag: verplichte toelichting)
 *   - VOORLOPIG-vlag: nog niet zeker; moet definitief vóór deadline (2 wk voor TW)
 *
 * Afwijkend AANTAL dagen = VERZOEK aan bestuur (beschikbaarheid_verzoek).
 * Functienamen beschikbaarheid2_van / _effectief / _opslaan blijven stabiel.
 */

function beschikbaarheid2_auto_setup(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = db();
        $db->exec("CREATE TABLE IF NOT EXISTS `beschikbaarheid_profiel` (
            `sollicitatie_id` INT PRIMARY KEY,
            `vaste_dagen_uit` VARCHAR(30) DEFAULT NULL,
            `vaste_toelichting` VARCHAR(300) DEFAULT NULL,
            `voorlopig` TINYINT(1) NOT NULL DEFAULT 0,
            `opmerking` TEXT DEFAULT NULL,
            `norm_dagen` TINYINT DEFAULT NULL,
            `norm_notitie` VARCHAR(300) DEFAULT NULL,
            `ingevuld_op` DATETIME DEFAULT NULL,
            `definitief_op` DATETIME DEFAULT NULL,
            `bijgewerkt_op` DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $dbn = $db->query("SELECT DATABASE()")->fetchColumn();
        $kolommen = [
            'vaste_dagen_uit'=>"VARCHAR(30) DEFAULT NULL", 'vaste_toelichting'=>"VARCHAR(300) DEFAULT NULL",
            'voorlopig'=>"TINYINT(1) NOT NULL DEFAULT 0", 'opmerking'=>"TEXT DEFAULT NULL",
            'norm_dagen'=>"TINYINT DEFAULT NULL", 'norm_notitie'=>"VARCHAR(300) DEFAULT NULL",
            'ingevuld_op'=>"DATETIME DEFAULT NULL", 'definitief_op'=>"DATETIME DEFAULT NULL", 'bijgewerkt_op'=>"DATETIME DEFAULT NULL",
        ];
        foreach ($kolommen as $kol => $def) {
            $c = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='beschikbaarheid_profiel' AND COLUMN_NAME=?");
            $c->execute([$dbn, $kol]);
            if (!$c->fetchColumn()) { try { $db->exec("ALTER TABLE `beschikbaarheid_profiel` ADD COLUMN `$kol` $def"); } catch (\Exception $e) {} }
        }

        $db->exec("CREATE TABLE IF NOT EXISTS `beschikbaarheid_blok` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, `sollicitatie_id` INT NOT NULL,
            `van_week` SMALLINT NOT NULL, `tot_week` SMALLINT NOT NULL,
            `regime` ENUM('vijf','weekend') NOT NULL DEFAULT 'vijf',
            `weekend_dagen` VARCHAR(20) DEFAULT NULL COMMENT 'JSON [5,6,7] welke weekenddagen bij regime weekend',
            INDEX (`sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `beschikbaarheid_afwezig` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, `sollicitatie_id` INT NOT NULL,
            `van_datum` DATE NOT NULL, `tot_datum` DATE NOT NULL, `label` VARCHAR(100) DEFAULT NULL,
            INDEX (`sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `beschikbaarheid_verzoek` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, `sollicitatie_id` INT NOT NULL,
            `van_week` SMALLINT NOT NULL, `tot_week` SMALLINT NOT NULL, `dagen_aantal` TINYINT NOT NULL,
            `dagen_pool` VARCHAR(20) DEFAULT NULL COMMENT 'JSON [1..7] beschikbare dagen waaruit planner kiest',
            `toelichting` VARCHAR(300) DEFAULT NULL,
            `status` ENUM('open','goedgekeurd','afgewezen') NOT NULL DEFAULT 'open',
            `beslist_op` DATETIME DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Bestuurs-correctie per week (herontworpen verhindering, punt 11):
        // legt vast dat iemand een week TOCH niet (of anders) kan, met reden.
        $db->exec("CREATE TABLE IF NOT EXISTS `beschikbaarheid_correctie` (
            `id` INT AUTO_INCREMENT PRIMARY KEY, `sollicitatie_id` INT NOT NULL,
            `week` SMALLINT NOT NULL,
            `soort` ENUM('niet','minder') NOT NULL DEFAULT 'niet' COMMENT 'niet=hele week vervalt, minder=aangepaste dagen',
            `dagen` VARCHAR(20) DEFAULT NULL COMMENT 'JSON [1..7] resterende dagen bij soort=minder',
            `reden` VARCHAR(300) DEFAULT NULL,
            `door` VARCHAR(100) DEFAULT NULL,
            `aangemaakt_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniek` (`sollicitatie_id`,`week`),
            INDEX (`sollicitatie_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // De 'beschikbaarheid'-tabel (compat: weken_json) stond alleen in schema.sql
        // en ontbrak daardoor op verse databases (zoals de testomgeving). Zonder deze
        // tabel faalde het opslaan van beschikbaarheid stil — nu maakt hij zichzelf aan.
        $db->exec("CREATE TABLE IF NOT EXISTS `beschikbaarheid` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL UNIQUE,
            `weken_json` JSON DEFAULT NULL,
            `uitzonderingen` TEXT DEFAULT NULL,
            `definitief_json` JSON DEFAULT NULL,
            `dagloon` DECIMAL(8,2) DEFAULT NULL,
            `functie` ENUM('aspirant','lifeguard','senior_lifeguard') DEFAULT 'aspirant',
            `ingevuld_op` DATETIME DEFAULT NULL,
            `definitief_op` DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Kolom-migraties voor bestaande installaties
        $extra_kol = [
            'beschikbaarheid_blok'    => ['weekend_dagen' => "VARCHAR(20) DEFAULT NULL"],
            'beschikbaarheid_verzoek' => ['dagen_pool' => "VARCHAR(20) DEFAULT NULL", 'beslist_op' => "DATETIME DEFAULT NULL"],
            'beschikbaarheid_profiel' => ['vaste_is_voorkeur' => "TINYINT(1) NOT NULL DEFAULT 1"],
        ];
        foreach ($extra_kol as $tabel => $kols) {
            foreach ($kols as $kol => $def) {
                $c = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
                $c->execute([$dbn, $tabel, $kol]);
                if (!$c->fetchColumn()) { try { $db->exec("ALTER TABLE `$tabel` ADD COLUMN `$kol` $def"); } catch (\Exception $e) {} }
            }
        }
    } catch (\Exception $e) { error_log('beschikbaarheid2_auto_setup: ' . $e->getMessage()); }
}

/** week => ['start'=>DateTime ma, 'eind'=>DateTime zo] voor het seizoen. */
function seizoen_weken_datums(?array $seizoen = null): array {
    if ($seizoen === null && function_exists('actief_seizoen')) $seizoen = actief_seizoen();
    $start = $seizoen['datum_werk_start'] ?? null;
    $eind  = $seizoen['datum_werk_eind'] ?? null;
    if (!$start) {
        $jaar = (int)date('Y');
        $start = (new DateTime())->setISODate($jaar, 22, 1)->format('Y-m-d');
        $eind  = (new DateTime())->setISODate($jaar, 35, 7)->format('Y-m-d');
    }
    $weken = [];
    try {
        $d = new DateTime($start); $e = new DateTime($eind);
        while ($d->format('N') != 1) $d->modify('-1 day');
        while ($d <= $e) { $wn=(int)$d->format('W'); $weken[$wn]=['start'=>clone $d,'eind'=>(clone $d)->modify('+6 days')]; $d->modify('+7 days'); }
    } catch (\Exception $e) {}
    return $weken;
}

function weken_in_periode(string $van, string $tot): array {
    $uit = [];
    try {
        $d = new DateTime($van); $eind = new DateTime($tot); $d->modify('monday this week');
        while ($d <= $eind) { $wk=(int)$d->format('W'); $uit[$wk]=['start'=>clone $d,'eind'=>(clone $d)->modify('+6 days')]; $d->modify('+7 days'); }
    } catch (\Exception $e) {}
    return $uit;
}

function beschikbaarheid2_van(int $sol_id): array {
    beschikbaarheid2_auto_setup();
    $db = db();
    $uit = ['profiel'=>null, 'blokken'=>[], 'afwezig'=>[], 'verzoeken'=>[], 'correcties'=>[]];
    try {
        $q = $db->prepare("SELECT * FROM beschikbaarheid_profiel WHERE sollicitatie_id=?"); $q->execute([$sol_id]);
        $uit['profiel'] = $q->fetch() ?: null;
        if ($uit['profiel']) $uit['profiel']['vaste_dagen_uit'] = json_decode($uit['profiel']['vaste_dagen_uit'] ?? '[]', true) ?: [];
        $q = $db->prepare("SELECT * FROM beschikbaarheid_blok WHERE sollicitatie_id=? ORDER BY van_week"); $q->execute([$sol_id]);
        $uit['blokken'] = array_map(function($b){ $b['weekend_dagen'] = json_decode($b['weekend_dagen'] ?? 'null', true); return $b; }, $q->fetchAll());
        $q = $db->prepare("SELECT * FROM beschikbaarheid_afwezig WHERE sollicitatie_id=? ORDER BY van_datum"); $q->execute([$sol_id]);
        $uit['afwezig'] = $q->fetchAll();
        $q = $db->prepare("SELECT * FROM beschikbaarheid_verzoek WHERE sollicitatie_id=? ORDER BY van_week"); $q->execute([$sol_id]);
        $uit['verzoeken'] = array_map(function($v){ $v['dagen_pool'] = json_decode($v['dagen_pool'] ?? 'null', true); return $v; }, $q->fetchAll());
        $q = $db->prepare("SELECT * FROM beschikbaarheid_correctie WHERE sollicitatie_id=? ORDER BY week"); $q->execute([$sol_id]);
        $uit['correcties'] = array_map(function($c){ $c['dagen'] = json_decode($c['dagen'] ?? 'null', true); return $c; }, $q->fetchAll());
    } catch (\Exception $e) {}
    return $uit;
}

function beschikbaarheid2_effectief(int $sol_id, ?array $seizoen = null): array {
    $data = beschikbaarheid2_van($sol_id);
    if (!$data['blokken']) return [];
    $p = $data['profiel'] ?: [];
    $weken_datums = seizoen_weken_datums($seizoen);
    // Vaste dagen zijn nu een VOORKEUR (planner beslist), niet langer een harde blokkade.
    // Ze tellen dus NIET meer af van de beschikbare dagen. We geven ze wel apart mee.
    $voorkeur_uit = array_map('intval', $p['vaste_dagen_uit'] ?? []);
    $norm_persoon = !empty($p['norm_dagen']) ? (int)$p['norm_dagen'] : 5;

    // Goedgekeurde verzoeken: week => ['aantal'=>n, 'pool'=>[..]|null]
    $verzoek_week = [];
    foreach ($data['verzoeken'] as $v) {
        if ($v['status'] === 'goedgekeurd')
            for ($w=(int)$v['van_week']; $w<=(int)$v['tot_week']; $w++)
                $verzoek_week[$w] = ['aantal'=>(int)$v['dagen_aantal'], 'pool'=>$v['dagen_pool'] ?? null];
    }

    // Bestuurs-correcties per week: week => ['soort'=>.., 'dagen'=>[..]|null, 'reden'=>..]
    $correctie_week = [];
    foreach ($data['correcties'] as $c) $correctie_week[(int)$c['week']] = $c;

    $uit = [];
    foreach ($data['blokken'] as $blok) {
        $regime = $blok['regime'] === 'weekend' ? 'weekend' : 'vijf';
        // Bij weekend-regime: gekozen weekenddagen (default vr/za/zo)
        $weekend_dagen = $regime === 'weekend'
            ? array_values(array_intersect($blok['weekend_dagen'] ?: [5,6,7], [5,6,7]))
            : null;
        if ($regime === 'weekend' && !$weekend_dagen) $weekend_dagen = [5,6,7];
        $kandidaat = $regime === 'weekend' ? $weekend_dagen : [1,2,3,4,5,6,7];
        for ($wk=(int)$blok['van_week']; $wk<=(int)$blok['tot_week']; $wk++) {
            if (!isset($weken_datums[$wk])) continue;
            if (isset($uit[$wk])) continue; // eerste blok wint bij overlap
            $range = $weken_datums[$wk];

            // Bestuurs-correctie: hele week vervalt?
            $corr = $correctie_week[$wk] ?? null;
            if ($corr && $corr['soort'] === 'niet') continue;

            $dagen = [];
            foreach ($kandidaat as $d) {
                // Afwezigheid (dag-precies) blijft harde blokkade
                $datum = (clone $range['start'])->modify('+' . ($d-1) . ' days');
                $afw = false;
                foreach ($data['afwezig'] as $a) {
                    if ($datum >= new DateTime($a['van_datum']) && $datum <= new DateTime($a['tot_datum'])) { $afw=true; break; }
                }
                if ($afw) continue;
                // Bestuurs-correctie soort=minder: alleen deze dagen resteren
                if ($corr && $corr['soort'] === 'minder' && $corr['dagen'] && !in_array($d, $corr['dagen'], true)) continue;
                $dagen[] = $d;
            }
            if (!$dagen) continue;

            // Norm bepalen
            $norm = $regime === 'weekend' ? count($dagen) : $norm_persoon;
            $pool = null;
            if (isset($verzoek_week[$wk])) {
                $norm = min($norm, $verzoek_week[$wk]['aantal']);
                $pool = $verzoek_week[$wk]['pool'];
            }
            if ($corr && $corr['soort'] === 'minder') $norm = min($norm, count($dagen));

            // Voorkeur vrije dagen die in deze week vallen (informatief voor planner)
            $voorkeur_in_week = array_values(array_intersect($voorkeur_uit, $dagen));

            $uit[$wk] = [
                'dagen'        => $dagen,
                'dagen_max'    => min($norm, count($dagen)),
                'regime'       => $regime,
                'pool'         => $pool,            // door sollicitant aangegeven beschikbare dagen bij verzoek
                'voorkeur_vrij'=> $voorkeur_in_week, // wenst deze dag(en) vrij — planner beslist
                'correctie'    => $corr ? ['soort'=>$corr['soort'], 'reden'=>$corr['reden']] : null,
                'periode'      => $range['start']->format('j M').'–'.$range['eind']->format('j M'),
                'start'        => $range['start']->format('Y-m-d'),
            ];
        }
    }
    ksort($uit);
    return $uit;
}

function beschikbaarheid2_sync_compat(int $sol_id): void {
    try {
        beschikbaarheid2_auto_setup();
        $eff = beschikbaarheid2_effectief($sol_id);
        $weken = array_keys($eff); sort($weken);
        $db = db();
        $q = $db->prepare("SELECT 1 FROM beschikbaarheid WHERE sollicitatie_id=?"); $q->execute([$sol_id]);
        if ($q->fetchColumn()) $db->prepare("UPDATE beschikbaarheid SET weken_json=?, ingevuld_op=NOW() WHERE sollicitatie_id=?")->execute([json_encode($weken), $sol_id]);
        else $db->prepare("INSERT INTO beschikbaarheid (sollicitatie_id, weken_json, ingevuld_op) VALUES (?,?,NOW())")->execute([$sol_id, json_encode($weken)]);
    } catch (\Exception $e) {
        error_log('beschikbaarheid2_sync_compat: ' . $e->getMessage());
    }
}

function beschikbaarheid2_opslaan(int $sol_id, array $input): void {
    beschikbaarheid2_auto_setup();
    $db = db();
    $vaste = array_values(array_intersect(array_map('intval', $input['vaste_dagen_uit'] ?? []), [1,2,3,4,5,6,7]));
    $voorlopig = !empty($input['voorlopig']) ? 1 : 0;

    $db->prepare("INSERT INTO beschikbaarheid_profiel (sollicitatie_id, vaste_dagen_uit, vaste_toelichting, vaste_is_voorkeur, voorlopig, opmerking, ingevuld_op, definitief_op, bijgewerkt_op)
                  VALUES (?,?,?,1,?,?,NOW(), " . ($voorlopig ? "NULL" : "NOW()") . ", NOW())
                  ON DUPLICATE KEY UPDATE vaste_dagen_uit=VALUES(vaste_dagen_uit), vaste_toelichting=VALUES(vaste_toelichting),
                    voorlopig=VALUES(voorlopig), opmerking=VALUES(opmerking), bijgewerkt_op=NOW(),
                    definitief_op=" . ($voorlopig ? "NULL" : "IFNULL(definitief_op, NOW())"))
       ->execute([$sol_id, json_encode($vaste), $input['vaste_toelichting'] ?? null, $voorlopig, $input['opmerking'] ?? null]);

    $db->prepare("DELETE FROM beschikbaarheid_blok WHERE sollicitatie_id=?")->execute([$sol_id]);
    $ins = $db->prepare("INSERT INTO beschikbaarheid_blok (sollicitatie_id, van_week, tot_week, regime, weekend_dagen) VALUES (?,?,?,?,?)");
    foreach ($input['blokken'] ?? [] as $b) {
        $vw=(int)($b['van_week']??0); $tw=(int)($b['tot_week']??0);
        if ($vw<1 || $tw<1) continue;
        if ($tw<$vw) { $t=$vw; $vw=$tw; $tw=$t; }
        $regime = ($b['regime']??'vijf')==='weekend'?'weekend':'vijf';
        $wd = null;
        if ($regime === 'weekend') {
            $wd_arr = array_values(array_intersect(array_map('intval', $b['weekend_dagen'] ?? [5,6,7]), [5,6,7]));
            if (!$wd_arr) $wd_arr = [5,6,7];
            $wd = json_encode($wd_arr);
        }
        $ins->execute([$sol_id, $vw, $tw, $regime, $wd]);
    }

    $db->prepare("DELETE FROM beschikbaarheid_afwezig WHERE sollicitatie_id=?")->execute([$sol_id]);
    $insa = $db->prepare("INSERT INTO beschikbaarheid_afwezig (sollicitatie_id, van_datum, tot_datum, label) VALUES (?,?,?,?)");
    foreach ($input['afwezig'] ?? [] as $a) {
        if (!empty($a['van']) && !empty($a['tot'])) $insa->execute([$sol_id, $a['van'], $a['tot'], mb_substr(trim($a['label']??''),0,100)?:null]);
    }

    $db->prepare("DELETE FROM beschikbaarheid_verzoek WHERE sollicitatie_id=? AND status='open'")->execute([$sol_id]);
    $insv = $db->prepare("INSERT INTO beschikbaarheid_verzoek (sollicitatie_id, van_week, tot_week, dagen_aantal, dagen_pool, toelichting, status) VALUES (?,?,?,?,?,?, 'open')");
    foreach ($input['verzoeken'] ?? [] as $v) {
        $vw=(int)($v['van_week']??0); $tw=(int)($v['tot_week']??0); $da=(int)($v['dagen']??0);
        if ($vw<1||$tw<1||$da<1||$da>6) continue;
        if ($tw<$vw) { $t=$vw; $vw=$tw; $tw=$t; }
        $pool = null;
        if (!empty($v['pool'])) {
            $pool_arr = array_values(array_intersect(array_map('intval', $v['pool']), [1,2,3,4,5,6,7]));
            if ($pool_arr) $pool = json_encode($pool_arr);
        }
        $insv->execute([$sol_id, $vw, $tw, $da, $pool, mb_substr(trim($v['toelichting']??''),0,300)?:null]);
    }

    beschikbaarheid2_sync_compat($sol_id);
}

/** Bestuurs-correctie zetten/wissen voor één week (herontworpen verhindering). */
function beschikbaarheid2_correctie_zetten(int $sol_id, int $week, ?string $soort, ?array $dagen, ?string $reden, ?string $door): void {
    beschikbaarheid2_auto_setup();
    $db = db();
    if ($soort === null || !in_array($soort, ['niet','minder'], true)) {
        // Wissen
        $db->prepare("DELETE FROM beschikbaarheid_correctie WHERE sollicitatie_id=? AND week=?")->execute([$sol_id, $week]);
    } else {
        $dagen_json = null;
        if ($soort === 'minder') {
            $d = array_values(array_intersect(array_map('intval', $dagen ?? []), [1,2,3,4,5,6,7]));
            $dagen_json = $d ? json_encode($d) : null;
        }
        $db->prepare("INSERT INTO beschikbaarheid_correctie (sollicitatie_id, week, soort, dagen, reden, door)
                      VALUES (?,?,?,?,?,?)
                      ON DUPLICATE KEY UPDATE soort=VALUES(soort), dagen=VALUES(dagen), reden=VALUES(reden), door=VALUES(door), aangemaakt_op=NOW()")
           ->execute([$sol_id, $week, $soort, $dagen_json, $reden ? mb_substr(trim($reden),0,300) : null, $door]);
    }
    beschikbaarheid2_sync_compat($sol_id);
}

function beschikbaarheid2_verzoek_beslis(int $verzoek_id, string $besluit): void {
    beschikbaarheid2_auto_setup();
    $status = $besluit === 'goedkeuren' ? 'goedgekeurd' : 'afgewezen';
    $db = db();
    $q = $db->prepare("SELECT sollicitatie_id FROM beschikbaarheid_verzoek WHERE id=?"); $q->execute([$verzoek_id]);
    $sol_id = $q->fetchColumn();
    $db->prepare("UPDATE beschikbaarheid_verzoek SET status=?, beslist_op=NOW() WHERE id=?")->execute([$status, $verzoek_id]);
    if ($sol_id) beschikbaarheid2_sync_compat((int)$sol_id);
}

/** Alle openstaande verzoeken van een seizoen (voor centraal bestuursoverzicht). */
function beschikbaarheid2_open_verzoeken(int $seizoen_id): array {
    beschikbaarheid2_auto_setup();
    try {
        $q = db()->prepare("
            SELECT bv.*, s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam
            FROM beschikbaarheid_verzoek bv
            JOIN sollicitaties s ON s.id = bv.sollicitatie_id
            JOIN personen p ON p.id = s.persoon_id
            WHERE bv.status = 'open' AND s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt')
            ORDER BY bv.aangemaakt_op
        ");
        $q->execute([$seizoen_id]);
        return array_map(function($v){ $v['dagen_pool'] = json_decode($v['dagen_pool'] ?? 'null', true); return $v; }, $q->fetchAll());
    } catch (\Exception $e) { return []; }
}

function beschikbaarheid2_norm_zetten(int $sol_id, ?int $dagen, ?string $notitie): void {
    beschikbaarheid2_auto_setup();
    $dagen = $dagen !== null ? max(1, min(6, $dagen)) : null;
    db()->prepare("INSERT INTO beschikbaarheid_profiel (sollicitatie_id, norm_dagen, norm_notitie, bijgewerkt_op)
                   VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE norm_dagen=VALUES(norm_dagen), norm_notitie=VALUES(norm_notitie), bijgewerkt_op=NOW()")
       ->execute([$sol_id, $dagen, $notitie ? mb_substr(trim($notitie),0,300) : null]);
    beschikbaarheid2_sync_compat($sol_id);
}

function beschikbaarheid2_deadline(?array $seizoen = null): ?DateTime {
    if ($seizoen === null && function_exists('actief_seizoen')) $seizoen = actief_seizoen();
    $tw = $seizoen['datum_tw_start'] ?? null;
    if (!$tw) return null;
    try { return (new DateTime($tw))->modify('-14 days'); } catch (\Exception $e) { return null; }
}

/** Sollicitant of bestuur: markeer beschikbaarheid als definitief (haalt voorlopig-vlag weg). */
function beschikbaarheid2_definitief_maken(int $sol_id): bool {
    beschikbaarheid2_auto_setup();
    try {
        $c = db()->prepare("SELECT COUNT(*) FROM beschikbaarheid_blok WHERE sollicitatie_id=?");
        $c->execute([$sol_id]);
        if (!$c->fetchColumn()) return false; // geen blokken → niets om definitief te maken
        db()->prepare("UPDATE beschikbaarheid_profiel SET voorlopig=0, definitief_op=IFNULL(definitief_op, NOW()), bijgewerkt_op=NOW() WHERE sollicitatie_id=?")
            ->execute([$sol_id]);
        return true;
    } catch (\Exception $e) { return false; }
}

function beschikbaarheid2_voorlopigen(int $seizoen_id): array {
    beschikbaarheid2_auto_setup();
    try {
        $q = db()->prepare("SELECT s.id sol_id, p.voornaam, p.tussenvoegsel, p.achternaam, p.email, bp.bijgewerkt_op
            FROM beschikbaarheid_profiel bp JOIN sollicitaties s ON s.id=bp.sollicitatie_id JOIN personen p ON p.id=s.persoon_id
            WHERE bp.voorlopig=1 AND s.seizoen_id=? AND s.fase NOT IN ('afgewezen') ORDER BY p.achternaam, p.voornaam");
        $q->execute([$seizoen_id]);
        return $q->fetchAll();
    } catch (\Exception $e) { return []; }
}

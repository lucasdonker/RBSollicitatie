<?php
/**
 * Paal 13 — Instellingen helper + auto-setup
 */

$_instellingen_cache = null;

// Auto-setup: maak alle tabellen aan als ze ontbreken.
// Draait normaal NIET (alleen als versie wijzigt of $forceer = true).
function instellingen_auto_setup(bool $forceer = false): void {
    static $gedaan = false;
    if ($gedaan && !$forceer) return;
    $gedaan = true;
    $db = db();

    // Versie-check: sla zware setup over als DB-versie al klopt
    if (!$forceer && defined('APP_VERSIE')) {
        try {
            $db_versie = $db->query("SELECT waarde FROM instellingen WHERE sleutel = 'app.db_versie' LIMIT 1")->fetchColumn();
            if ($db_versie === APP_VERSIE) return;
        } catch (\Exception $e) {
            // instellingen-tabel bestaat nog niet → volledige setup draaien
        }
    }

    // instellingen tabel
    $db->exec("CREATE TABLE IF NOT EXISTS `instellingen` (
        `sleutel`      VARCHAR(100) NOT NULL PRIMARY KEY,
        `waarde`       TEXT DEFAULT NULL,
        `beschrijving` VARCHAR(300) DEFAULT NULL,
        `type`         VARCHAR(20)  DEFAULT 'tekst',
        `bijgewerkt_op` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // functies tabel
    $db->exec("CREATE TABLE IF NOT EXISTS `functies` (
        `id`           INT          AUTO_INCREMENT PRIMARY KEY,
        `sleutel`      VARCHAR(50)  NOT NULL UNIQUE,
        `naam`         VARCHAR(100) NOT NULL,
        `factor`       DECIMAL(4,2) NOT NULL DEFAULT 1.00,
        `min_leeftijd` INT          DEFAULT NULL,
        `max_leeftijd` INT          DEFAULT NULL,
        `vast_loon`    DECIMAL(8,2) DEFAULT NULL,
        `volgorde`     INT          DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // loon_tarieven tabel
    $db->exec("CREATE TABLE IF NOT EXISTS `loon_tarieven` (
        `id`          INT          AUTO_INCREMENT PRIMARY KEY,
        `leeftijd`    INT          NOT NULL,
        `periode`     VARCHAR(10)  NOT NULL,
        `min_uurloon` DECIMAL(6,4) NOT NULL,
        UNIQUE KEY `leeftijd_periode` (`leeftijd`,`periode`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // loon_planning tabel
    $db->exec("CREATE TABLE IF NOT EXISTS `loon_planning` (
        `id`              INT          AUTO_INCREMENT PRIMARY KEY,
        `sollicitatie_id` INT          NOT NULL,
        `week_nr`         INT          NOT NULL,
        `jaar`            INT          NOT NULL DEFAULT 2026,
        `dagen`           INT          NOT NULL DEFAULT 5,
        `functie_sleutel` VARCHAR(50)  DEFAULT NULL,
        `opgenomen`       TINYINT(1)   NOT NULL DEFAULT 0,
        `loon_override`   DECIMAL(8,2) DEFAULT NULL,
        `notitie`         VARCHAR(255) DEFAULT NULL,
        `bijgewerkt_op`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `sol_week` (`sollicitatie_id`,`week_nr`,`jaar`),
        FOREIGN KEY (`sollicitatie_id`) REFERENCES `sollicitaties`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // error_log tabel
    $db->exec("CREATE TABLE IF NOT EXISTS `error_log` (
        `id`             INT          AUTO_INCREMENT PRIMARY KEY,
        `code`           VARCHAR(20)  NOT NULL,
        `bericht`        TEXT         NOT NULL,
        `bestand`        VARCHAR(300) DEFAULT NULL,
        `regel`          INT          DEFAULT NULL,
        `gebruiker`      VARCHAR(100) DEFAULT NULL,
        `url`            VARCHAR(500) DEFAULT NULL,
        `mail_verstuurd` TINYINT(1)   DEFAULT 0,
        `aangemaakt_op`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Standaard instellingen
    $defaults = [
        ['site.naam',              'Reddingsbrigade Paal 13',  'Naam van de organisatie',              'tekst'],
        ['site.logo_url',          '',                          'URL van het logo',                     'url'],
        ['site.favicon_url',       '',                          'URL van het favicon',                  'url'],
        ['mail.fout_email',        'website@paal13.com',        'E-mail voor foutmeldingen',            'email'],
        ['mail.bestuur_email',     'bestuur@paal13.com',        'E-mail bestuur',                       'email'],
        ['mail.penningmeester_email', '',                       'E-mail penningmeester (loondossiers; leeg = bestuur)', 'email'],
        ['mail.ledenservice_email',   '',                       'E-mail ledenservice (inschrijvingen lidmaatschap; leeg = bestuur)', 'email'],
        ['documenten.penningmeester_bijlagen', '0',             'Loondossier-stukken (ID, bankpas, loonheffing) als bijlage mailen', 'checkbox'],
        ['documenten.download_melding', '1',                    'Melding aan het bestuur bij elke download van een loondocument', 'checkbox'],
        ['taken.huisregels_bijlage_id', '0',                    'Bijlage (bibliotheek) met de huisregels voor het takenpakket', 'getal'],
        ['ondertekenen.2fa_typen', 'loonheffing',                'Ondertekentypen met verplichte verificatiecode (wettelijke documenten)', 'tekst'],
        ['bewaar.loondocumenten_jaar', '5',                     'Loonheffingsformulier + kopie ID bewaren (jaren na einde dienstjaar)', 'getal'],
        ['bewaar.bankpas_na_verwerking_dagen', '30',            'Kopie bankpas vernietigen na verwerking (dagen)', 'getal'],
        ['aanmeld.min_leeftijd',   '15',                        'Minimumleeftijd sollicitant',          'getal'],
        ['aanmeld.max_leeftijd',   '35',                        'Maximumleeftijd sollicitant',          'getal'],
        ['invite.modus',           'suggestie',                 'Auto-invite modus',                    'keuze'],
        ['invite.min_leeftijd',    '16',                        'Min leeftijd auto-uitnodiging',        'getal'],
        ['invite.max_leeftijd',    '30',                        'Max leeftijd auto-uitnodiging',        'getal'],
        ['loon.vt_percentage',     '8',                         'Vakantietoeslag %',                    'getal'],
        ['loon.uren_per_dag',      '8',                         'Werkuren per dag',                     'getal'],
        ['loon.pc_vast_juni',      '850',                       'Vast weekbedrag postcommandant (jun)', 'getal'],
        ['loon.pc_vast_jul_sep',   '750',                       'Vast weekbedrag postcommandant (jul)', 'getal'],
        ['ai.api_key',             '',                          'Gemini API key',                       'geheim'],
        ['portaal.welkomst_tekst', 'Welkom op het sollicitantenportaal van Paal 13.', 'Portaal tekst', 'textarea'],
        // Bewaartermijnen (AVG opslagbeperking). 0 = niet automatisch opschonen.
        ['bewaar.actief',          '0',                         'Automatisch opschonen aan/uit',        'checkbox'],
        ['bewaar.afgewezen_weken', '4',                         'Afgewezen sollicitanten anonimiseren na (weken)', 'getal'],
        ['bewaar.seintje_mnd',     '12',                        'Seintje bij volgende werving geldig (maanden)', 'getal'],
        ['bewaar.aangenomen_jaar', '2',                         'Oud-lifeguards anonimiseren na (jaren na laatste seizoen)', 'getal'],
        ['bewaar.gezondheid_mnd',  '0',                         'Gezondheidsgegevens verwijderen na (maanden, 0=einde seizoen)', 'getal'],
        ['bewaar.ai_invoer_mnd',   '3',                         'AI-invoertekst verwijderen na (maanden)', 'getal'],
        ['bewaar.onvoltooid_mnd',  '3',                         'Niet-voltooide aanmeldingen verwijderen na (maanden)', 'getal'],
        ['bewaar.maillog_mnd',     '12',                        'Mail-/actielogboek verwijderen na (maanden)', 'getal'],
    ];
    $stmt = $db->prepare("INSERT IGNORE INTO `instellingen` (sleutel,waarde,beschrijving,type) VALUES (?,?,?,?)");
    foreach ($defaults as $r) $stmt->execute($r);

    // Verouderde standaardwaarden bijwerken: alleen als de waarde nog exact de oude standaard is (dus niet
    // handmatig aangepast). Zo krijgen bestaande installaties een gewijzigd besluit mee zonder eigen keuzes
    // te overschrijven.
    $correcties = [
        // 2.91.0: de inschrijving als lid vraagt geen verificatiecode meer (alleen wettelijke documenten)
        ['ondertekenen.2fa_typen', 'loonheffing,lidmaatschap', 'loonheffing'],
    ];
    $corr = $db->prepare("UPDATE `instellingen` SET waarde=? WHERE sleutel=? AND waarde=?");
    foreach ($correcties as $c) { try { $corr->execute([$c[2], $c[0], $c[1]]); } catch (\Exception $e) {} }

    // Standaard functies
    $fstmt = $db->prepare("INSERT IGNORE INTO `functies` (sleutel,naam,factor,min_leeftijd,max_leeftijd,volgorde) VALUES (?,?,?,?,?,?)");
    foreach ([
        ['aspirant',      'Aspirant Lifeguard',  1.00, 15, 17, 1],
        ['junior',        'Junior Lifeguard',    1.10, 16, 20, 2],
        ['lifeguard',     'Lifeguard',           1.20, 16, 99, 3],
        ['senior',        'Senior Lifeguard',    1.30, 18, 99, 4],
        ['postcommandant','Postcommandant',      0.00, 18, 99, 5],
    ] as $f) $fstmt->execute($f);

    // Minimumlonen (per uur, excl VT)
    $lstmt = $db->prepare("INSERT IGNORE INTO `loon_tarieven` (leeftijd,periode,min_uurloon) VALUES (?,?,?)");
    $lonen = [
        'jun'    => [15=>4.41,16=>5.07,17=>5.81,18=>7.36,19=>8.83,20=>11.77,21=>14.71,22=>14.71,23=>14.71,24=>11.16,25=>11.16,26=>11.16,27=>11.16,28=>11.16,29=>11.16,30=>11.16,31=>11.16,32=>11.16],
        'jul_sep'=> [15=>4.32,16=>4.97,17=>5.69,18=>7.20,19=>8.64,20=>11.52,21=>14.40,22=>14.40,23=>14.40,24=>11.16,25=>11.16,26=>11.16,27=>11.16,28=>11.16,29=>11.16,30=>11.16,31=>11.16,32=>11.16],
    ];
    foreach ($lonen as $periode => $leeftijden) {
        foreach ($leeftijden as $lft => $loon) {
            $lstmt->execute([$lft, $periode, $loon]);
        }
    }

    // Markeer DB als bijgewerkt naar huidige versie
    if (defined('APP_VERSIE')) {
        try {
            $db->prepare("INSERT INTO instellingen (sleutel, waarde) VALUES ('app.db_versie', ?)
                          ON DUPLICATE KEY UPDATE waarde = ?")
               ->execute([APP_VERSIE, APP_VERSIE]);
        } catch (\Exception $e) {}
    }
}

function instellingen_laden(): array {
    global $_instellingen_cache;
    if ($_instellingen_cache !== null) return $_instellingen_cache;
    try {
        instellingen_auto_setup();
        $rows = db()->query("SELECT sleutel, waarde FROM instellingen")->fetchAll(\PDO::FETCH_KEY_PAIR);
        $_instellingen_cache = $rows ?: [];
    } catch (\Exception $e) {
        error_log("Instellingen laden mislukt: " . $e->getMessage());
        $_instellingen_cache = [];
    }
    return $_instellingen_cache;
}

function instelling(string $sleutel, string $default = ''): string {
    return instellingen_laden()[$sleutel] ?? $default;
}
function instelling_int(string $sleutel, int $default = 0): int {
    $v = instelling($sleutel);
    return ($v === null || $v === '') ? $default : (int)$v;
}
function instelling_float(string $sleutel, float $default = 0.0): float {
    $v = instelling($sleutel);
    return ($v === null || $v === '') ? $default : (float)$v;
}
function instelling_bool(string $sleutel, bool $default = false): bool {
    $v = instelling($sleutel);
    if ($v === '') return $default;
    return in_array($v, ['1','true','ja','aan'], true);
}
function instelling_opslaan(string $sleutel, string $waarde): bool {
    try {
        db()->prepare("INSERT INTO instellingen (sleutel,waarde) VALUES (?,?) ON DUPLICATE KEY UPDATE waarde=?,bijgewerkt_op=NOW()")
           ->execute([$sleutel,$waarde,$waarde]);
        global $_instellingen_cache; $_instellingen_cache = null;
        return true;
    } catch (\Exception $e) {
        error_log("Instelling opslaan mislukt '$sleutel': " . $e->getMessage());
        return false;
    }
}

function functies_ophalen(): array {
    try {
        instellingen_auto_setup();
        return db()->query("SELECT * FROM functies ORDER BY volgorde,id")->fetchAll();
    } catch (\Exception $e) {
        error_log("Functies ophalen mislukt: " . $e->getMessage());
        return [
            ['sleutel'=>'aspirant',      'naam'=>'Aspirant Lifeguard', 'factor'=>1.00,'min_leeftijd'=>15,'max_leeftijd'=>17,'vast_loon'=>null,'volgorde'=>1],
            ['sleutel'=>'junior',        'naam'=>'Junior Lifeguard',   'factor'=>1.10,'min_leeftijd'=>16,'max_leeftijd'=>20,'vast_loon'=>null,'volgorde'=>2],
            ['sleutel'=>'lifeguard',     'naam'=>'Lifeguard',          'factor'=>1.20,'min_leeftijd'=>16,'max_leeftijd'=>99,'vast_loon'=>null,'volgorde'=>3],
            ['sleutel'=>'senior',        'naam'=>'Senior Lifeguard',   'factor'=>1.30,'min_leeftijd'=>18,'max_leeftijd'=>99,'vast_loon'=>null,'volgorde'=>4],
            ['sleutel'=>'postcommandant','naam'=>'Postcommandant',     'factor'=>0.00,'min_leeftijd'=>18,'max_leeftijd'=>99,'vast_loon'=>850, 'volgorde'=>5],
        ];
    }
}

function bereken_dagloon(int $leeftijd, string $functie_sleutel, string $periode = 'jun', ?float $override = null): float {
    if ($override !== null) return $override;

    if ($functie_sleutel === 'postcommandant') {
        $k = $periode === 'jun' ? 'loon.pc_vast_juni' : 'loon.pc_vast_jul_sep';
        return round(instelling_float($k, 850) / 5, 2);
    }

    $functies = functies_ophalen();
    $factor   = 1.20; // standaard lifeguard
    foreach ($functies as $f) {
        if ($f['sleutel'] === $functie_sleutel) { $factor = (float)$f['factor']; break; }
    }

    try {
        instellingen_auto_setup();
        $stmt = db()->prepare("SELECT min_uurloon FROM loon_tarieven WHERE leeftijd=? AND periode=? LIMIT 1");
        $stmt->execute([$leeftijd, $periode]);
        $row = $stmt->fetch();
        if (!$row) {
            // Dichtstbijzijnde leeftijd
            $stmt2 = db()->prepare("SELECT min_uurloon FROM loon_tarieven WHERE periode=? ORDER BY ABS(leeftijd-?) LIMIT 1");
            $stmt2->execute([$periode, $leeftijd]);
            $row = $stmt2->fetch();
        }
    } catch (\Exception $e) { $row = false; }

    $uurloon  = $row ? (float)$row['min_uurloon'] : 11.16;
    $uren_dag = instelling_float('loon.uren_per_dag', 8);
    $vt       = 1 + (instelling_float('loon.vt_percentage', 8) / 100);

    return round($uurloon * $uren_dag * $factor * $vt, 2);
}

function week_naar_periode(int $week_nr): string {
    return ($week_nr <= 26) ? 'jun' : 'jul_sep';
}

function leeftijd_op_datum(string $geboortedatum, string $peildatum): int {
    try {
        return (int)(new \DateTime($geboortedatum))->diff(new \DateTime($peildatum))->y;
    } catch (\Exception $e) { return 0; }
}
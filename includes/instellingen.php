<?php
/**
 * Paal 13 — Instellingen helper + auto-setup
 */

// Loontarieven per kalenderjaar (tarieven, VT, werkuren, postcommandant, budget) en het dagloon.
require_once __DIR__ . '/loon_tarieven.php';

$_instellingen_cache = null;

/**
 * Welke sleutels het algemene formulier van Instellingen (actie "instellingen") per
 * tabblad mag opslaan. Dit is de whitelist: wat hier niet staat — kluis.*, db.*,
 * cron.token, api.sleutel, rechten.*, app.* — kan via dat formulier nooit worden gezet,
 * ook niet met een zelfgemaakte POST. Een nieuw veld op een tabblad = de sleutel hier
 * toevoegen, verder niets.
 *
 * @return string[]
 */
function instellingen_formulier_sleutels(string $tab): array {
    $herinneringen = [];
    if (function_exists('herinnering_regels')) {
        foreach (array_keys(herinnering_regels()) as $slug) {
            foreach (['aan', 'na', 'herhaal', 'max'] as $veld) $herinneringen[] = 'herinnering.' . $slug . '.' . $veld;
        }
        $herinneringen[] = 'herinnering.selectiedag_morgen.info';
    }
    $per_tab = [
        'algemeen'    => ['site.naam', 'site.logo_url', 'site.favicon_url', 'portaal.welkomst_tekst'],
        // Lonen & budget slaat op per kalenderjaar (includes/loon_tarieven.php) via eigen acties, niet via
        // dit formulier. De oude loon.*-sleutels zijn alleen nog de terugval voor een jaar zonder eigen waarden.
        'loon'        => [],
        // Technisch tabblad (alleen Systeem › bewerken): welke ondertekeningen een verificatiecode vragen.
        'modules'     => ['ondertekenen.2fa_typen'],
        'uitnodiging' => array_merge([
            'invite.modus', 'invite.min_leeftijd', 'invite.max_leeftijd', 'invite.delay_uur',
            'seizoen.fase', 'selectie.drempel', 'tw.bericht_aangenomen', 'tw.bericht_afgewezen', 'tw.bericht_afgewezen_niet_gekomen',
            'auto.ondertekening_sd', 'auto.kennismakingspakket',
            'herinnering.rust_dagen', 'melding.plafond_dag', 'bijlage.mail_max_mb', 'wa.max_per_uur',
        ], $herinneringen),
        'mail'        => ['mail.fout_email', 'mail.bestuur_email', 'mail.contact_email', 'mail.penningmeester_email', 'mail.ledenservice_email',
                          'documenten.penningmeester_bijlagen'],
        'ai'          => ['ai.api_key', 'ai.model', 'ai.beschikbaarheid_hulp', 'ai.auto_analyse'],
        'authentik'   => ['authentik.api_token', 'authentik.url', 'authentik.token'],
        'bewaren'     => ['bewaar.actief', 'bewaar.afgewezen_weken', 'bewaar.seintje_mnd', 'bewaar.aangenomen_jaar',
                          'bewaar.gezondheid_mnd', 'bewaar.ai_invoer_mnd', 'bewaar.onvoltooid_mnd', 'bewaar.maillog_mnd',
                          'bewaar.maillog_body_mnd', 'bewaar.bijlagen_mnd', 'bewaar.pagina_events_mnd', 'bewaar.pagina_hash_dagen',
                          'bewaar.audit_maanden', 'bewaar.bankpas_na_verwerking_dagen', 'bewaar.foutenlog_dagen',
                          'bewaar.loondocumenten_jaar', 'privacy.url',
                          'documenten.download_melding', 'paginas.tracking'],
    ];
    return $per_tab[$tab] ?? [];
}

/**
 * Ondertekentypen waarvoor je in Instellingen › Modules een verificatiecode kunt verplichten
 * (instelling ondertekenen.2fa_typen, gelezen door ondertekening_2fa_typen()). [type => label]
 */
function instellingen_2fa_keuzes(): array {
    $uit = [];
    foreach (['loonheffing', 'lidmaatschap', 'gezondheid_18plus', 'gezondheid_ouder', 'intake', 'intake_ouder'] as $t) {
        $uit[$t] = function_exists('ondertekening_label') ? ondertekening_label($t) : $t;
    }
    return $uit;
}

/**
 * Sleutels waarvan de waarde geheim is. Het scherm toont die nooit terug (alleen
 * "ingesteld"), en een leeg veld opslaan laat de huidige waarde staan.
 * api.sleutel en cron.token staan hier niet: die hebben een eigen knop om te vernieuwen.
 */
function instellingen_geheime_sleutels(): array {
    return ['ai.api_key', 'authentik.api_token', 'authentik.token', 'smtp.pass',
            'sms.http_token', 'sms.easysendsms_key', 'sms.messagebird_key', 'wa.api_sleutel'];
}

/**
 * Een geheim bijwerken: een lege nieuwe waarde verandert niets, $wissen maakt hem leeg.
 * Geeft true terug als er iets veranderde (voor het logboek — de waarde zelf komt daar nooit in).
 */
function instelling_geheim_bijwerken(string $sleutel, string $nieuw, bool $wissen = false): bool {
    if (!in_array($sleutel, instellingen_geheime_sleutels(), true)) return false;
    $nieuw = trim($nieuw);
    if ($nieuw !== '') return instelling_opslaan($sleutel, $nieuw);
    if ($wissen && instelling($sleutel, '') !== '') return instelling_opslaan($sleutel, '');
    return false;
}

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

    // loon_tarieven tabel: per kalenderjaar. Vullen, migreren en de jaartabel: loon_tarieven_auto_setup().
    $db->exec("CREATE TABLE IF NOT EXISTS `loon_tarieven` (
        `id`          INT          AUTO_INCREMENT PRIMARY KEY,
        `jaar`        SMALLINT     NOT NULL DEFAULT 0,
        `leeftijd`    INT          NOT NULL,
        `periode`     VARCHAR(10)  NOT NULL,
        `min_uurloon` DECIMAL(6,4) NOT NULL,
        UNIQUE KEY `jaar_leeftijd_periode` (`jaar`,`leeftijd`,`periode`)
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
        ['mail.contact_email',     'sollicitatie@paal13.com',   'Contactadres voor sollicitanten (portaal, vragen; leeg = bestuur)', 'email'],
        ['mail.penningmeester_email', '',                       'E-mail penningmeester (loondossiers; leeg = bestuur)', 'email'],
        ['mail.ledenservice_email',   '',                       'E-mail ledenservice (inschrijvingen lidmaatschap; leeg = bestuur)', 'email'],
        ['documenten.penningmeester_bijlagen', '0',             'Loondossier-stukken (ID, bankpas, loonheffing) als bijlage mailen', 'checkbox'],
        ['documenten.download_melding', '1',                    'Melding aan het bestuur bij elke download van een loondocument', 'checkbox'],
        ['taken.huisregels_bijlage_id', '0',                    'Bijlage (bibliotheek) met de huisregels voor het takenpakket', 'getal'],
        ['ondertekenen.2fa_typen', 'loonheffing',                'Ondertekentypen met verplichte verificatiecode (wettelijke documenten)', 'tekst'],
        ['bewaar.loondocumenten_jaar', '5',                     'Loonheffingsformulier + kopie ID bewaren (jaren na einde dienstjaar)', 'getal'],
        ['bewaar.bankpas_na_verwerking_dagen', '30',            'Kopie bankpas vernietigen na verwerking (dagen)', 'getal'],
        ['privacy.url',            '',                          'Adres van de privacyverklaring (leeg = de pagina in dit systeem)', 'url'],
        // aanmeld.min_leeftijd/max_leeftijd vervallen (2.100): nergens gebruikt; de minimumleeftijd is vast 15.
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
        ['bewaar.aangenomen_jaar', '2',                         'Oud-strandwachten anonimiseren na (jaren na laatste seizoen)', 'getal'],
        ['bewaar.gezondheid_mnd',  '0',                         'Gezondheidsgegevens verwijderen na (maanden, 0=einde seizoen)', 'getal'],
        ['bewaar.ai_invoer_mnd',   '3',                         'AI-invoertekst verwijderen na (maanden)', 'getal'],
        ['bewaar.onvoltooid_mnd',  '3',                         'Niet-voltooide aanmeldingen verwijderen na (maanden)', 'getal'],
        ['bewaar.maillog_mnd',     '12',                        'Mail-/actielogboek verwijderen na (maanden)', 'getal'],
        ['bewaar.audit_maanden',   '12',                        'Auditlog (wie deed wat) verwijderen na (maanden)', 'getal'],
        ['bewaar.foutenlog_dagen', '90',                        'Foutenlog verwijderen na (dagen)',     'getal'],
        ['paginas.tracking',       '1',                         'Bezoekersstatistiek landingspagina\'s aan/uit', 'checkbox'],
        ['seizoen.fase',           '',                          'Seizoensfase handmatig (leeg = automatisch uit de datums)', 'keuze'],
        ['selectie.drempel',       '3.5',                       'Advies-drempel selectiedag (gemiddelde score voor "Door")', 'getal'],
    ];
    $stmt = $db->prepare("INSERT IGNORE INTO `instellingen` (sleutel,waarde,beschrijving,type) VALUES (?,?,?,?)");
    foreach ($defaults as $r) $stmt->execute($r);

    // Verouderde standaardwaarden bijwerken: alleen als de waarde nog exact de oude standaard is (dus niet
    // handmatig aangepast). Zo krijgen bestaande installaties een gewijzigd besluit mee zonder eigen keuzes
    // te overschrijven.
    $correcties = [
        // 2.91.0: de inschrijving als lid vraagt geen verificatiecode meer (alleen wettelijke documenten)
        ['ondertekenen.2fa_typen', 'loonheffing,lidmaatschap', 'loonheffing'],
        // (authentik.admin_groepen vervalt sinds 2.132.0: toegang = koppeling rol → groep in Rollen & rechten.)
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

    // Minimumlonen: per jaar, gevuld door loon_tarieven_auto_setup() (includes/loon_tarieven.php).
    // Hier niet meer: zonder jaar zou elke versie-ophoging rijen met jaar 0 toevoegen.

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

/**
 * Geheime instellingen (instellingen_geheime_sleutels(): wachtwoorden en API-sleutels van koppelingen)
 * staan versleuteld in de database: "kluis:<blob>" via includes/kluis.php, gebonden aan de naam van de
 * instelling. Een databasedump of -lek alleen geeft ze dus niet prijs; daarvoor is ook KLUIS_SLEUTEL
 * uit config.php nodig (NIS2: cryptografie, geheimen niet leesbaar opgeslagen).
 * Een oude, nog leesbare waarde wordt bij de eerste keer lezen versleuteld teruggeschreven.
 * Werkt de kluis niet (geen sleutel), dan blijft alles leesbaar werken: een koppeling valt nooit stil
 * door deze laag; de kluisstatus en de health-check melden het.
 */
const INSTELLING_GEHEIM_PREFIX = 'kluis:';

function instelling_geheim_aad(string $sleutel): string { return 'instelling:' . $sleutel; }

function instelling(string $sleutel, string $default = ''): string {
    $w = instellingen_laden()[$sleutel] ?? null;
    if ($w === null) return $default;
    if ($w === '' || !in_array($sleutel, instellingen_geheime_sleutels(), true)) return $w;
    static $open = [];
    if (isset($open[$sleutel]) && $open[$sleutel][0] === $w) return $open[$sleutel][1];
    if (str_starts_with($w, INSTELLING_GEHEIM_PREFIX)) {
        $plain = function_exists('kluis_ontsleutel')
            ? kluis_ontsleutel(substr($w, strlen(INSTELLING_GEHEIM_PREFIX)), instelling_geheim_aad($sleutel)) : null;
        if ($plain === null) { error_log("instelling: geheim '$sleutel' niet te ontsleutelen (kluissleutel?)"); return $default; }
        $open[$sleutel] = [$w, $plain];
        return $plain;
    }
    // Nog leesbaar opgeslagen (van vóór 2.130.0): nu versleutelen, de waarde zelf blijft gelijk.
    if (function_exists('kluis_beschikbaar') && kluis_beschikbaar()) instelling_opslaan($sleutel, $w);
    return $w;
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
    // Geheimen versleuteld wegschrijven (zie instelling()). Lukt dat niet, dan leesbaar — met een log.
    if ($waarde !== '' && !str_starts_with($waarde, INSTELLING_GEHEIM_PREFIX)
        && in_array($sleutel, instellingen_geheime_sleutels(), true) && function_exists('kluis_versleutel')) {
        $blob = kluis_versleutel($waarde, instelling_geheim_aad($sleutel));
        if ($blob !== null) $waarde = INSTELLING_GEHEIM_PREFIX . $blob;
        else error_log("instelling_opslaan: geheim '$sleutel' onversleuteld opgeslagen (kluis niet beschikbaar)");
    }
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

// bereken_dagloon() staat in includes/loon_tarieven.php: het tarief hangt af van het kalenderjaar.

function week_naar_periode(int $week_nr): string {
    return ($week_nr <= 26) ? 'jun' : 'jul_sep';
}

function leeftijd_op_datum(string $geboortedatum, string $peildatum): int {
    try {
        return (int)(new \DateTime($geboortedatum))->diff(new \DateTime($peildatum))->y;
    } catch (\Exception $e) { return 0; }
}
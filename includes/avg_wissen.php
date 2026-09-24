<?php
/**
 * Paal 13 — AVG: gegevens van een sollicitatie wissen, een persoon anonimiseren, en inzage.
 *
 * Eén plek voor "wat verdwijnt er als iemand weg moet". Drie routes gebruiken dit:
 *   - de sollicitant zelf, via Mijn gegevens → Gegevens laten verwijderen (portaal/gegevens.php);
 *   - het bestuur, via Verwijderen in het dossier (includes/beheer/dossier.php);
 *   - het automatisch opschonen na de bewaartermijn (includes/opschonen.php).
 * Voorheen had het opschonen zijn eigen, kortere lijst: dan bleef bij een afgewezen
 * sollicitant na "anonimiseren" het BLK-formulier met adres en motivatie gewoon staan.
 *
 * Deze functies gooien bij een echte databasefout een exceptie: de aanroeper zet ze in een
 * transactie en rolt terug. Alleen een tabel die (nog) niet bestaat (SQLSTATE 42S02,
 * oudere installaties) wordt stil overgeslagen; bij kolommen die per installatie kunnen
 * ontbreken ook 42S22.
 *
 * Wat bewust blijft: de sollicitatie-rij zelf (fase, seizoen, data) voor de statistiek, en
 * het feit dát er ondertekend is (type en moment). Het loondossier (loonheffing en kopie
 * identiteitsbewijs) valt onder de fiscale bewaarplicht: met $ook_loondocs = false blijft het
 * staan, met een gemaskeerd IP-adres in het bewijsspoor. De penningmeester vernietigt het met
 * de hand (admin/documenten.php).
 *
 * Een nieuwe tabel met persoonsgegevens per sollicitatie? Zet hem in avg_sollicitatie_leegmaken()
 * én in avg_inzage(). Anders klopt het verwerkingsregister niet meer.
 */

if (!function_exists('avg_sollicitatie_leegmaken')) {

/** Voer één stap uit; alleen "tabel bestaat niet" (en desgewenst "kolom bestaat niet") mag stil voorbij. */
function avg_stap($db, string $sql, array $params, bool $kolom_mag_ontbreken = false): void {
    try { $db->prepare($sql)->execute($params); }
    catch (\PDOException $e) {
        $code = (string)($e->getCode() ?: '');
        if ($code === '42S02' || ($kolom_mag_ontbreken && $code === '42S22')) return;
        throw $e;
    }
}

/**
 * SQL-expressie die een IP-adres in kolom $kol maskeert: IPv4 houdt de eerste drie blokken
 * (1.2.3.0), IPv6 de eerste drie groepen (2001:db8:1::). Genoeg om te zien uit welk netwerk
 * er getekend is, niet genoeg om één aansluiting aan te wijzen. Idempotent.
 */
function avg_ip_masker_sql(string $kol): string {
    return "CASE WHEN $kol IS NULL THEN NULL"
         . " WHEN $kol LIKE '%:%' THEN CONCAT(SUBSTRING_INDEX($kol, ':', 3), '::')"
         . " WHEN $kol LIKE '%.%.%.%' THEN CONCAT(SUBSTRING_INDEX($kol, '.', 3), '.0')"
         . " ELSE NULL END";
}

/**
 * Alles wat bij één sollicitatie hoort en persoonlijk is: documenten in de kluis, taken,
 * vertrouwelijke ondertekengegevens en het bewijsspoor, BLK met foto en wijzigingen,
 * beschikbaarheid (alle tabellen) en de AI-hulp, notities, beoordelingen, selectiedag en
 * trainingsweekend, "blijf ingelogd", en de herleidbare velden op de sollicitatie zelf.
 *
 * $ook_loondocs = false laat het loondossier staan: de loonheffing-ondertekening (met BSN,
 * naam en adres) en de kopie van het identiteitsbewijs (wettelijke bewaarplicht, eigen
 * termijn). Het IP-adres in dat bewijsspoor wordt gemaskeerd, het e-mailadres gaat weg.
 * Zo gebruiken het opschonen van oud-lifeguards en het zelf verwijderen met een verwerkt
 * loondossier dit.
 */
function avg_sollicitatie_leegmaken($db, int $sol_id, bool $ook_loondocs = true): void {
    if (function_exists('documenten_bij_dossier_verwijderen')) documenten_bij_dossier_verwijderen($sol_id, $ook_loondocs);
    if (function_exists('foto_van_sollicitatie_verwijderen')) foto_van_sollicitatie_verwijderen($sol_id);
    $niet_loon = $ook_loondocs ? '' : " AND type <> 'loonheffing'";
    foreach ([
        "DELETE FROM sollicitant_taken WHERE sollicitatie_id = ?",
        "UPDATE ondertekeningen SET vertrouwelijk=NULL, handtekening_beeld=NULL, pdf_document_id=NULL, formulier_data=NULL WHERE sollicitatie_id = ?" . $niet_loon,
        "DELETE FROM blk_formulieren WHERE sollicitatie_id = ?",
        "DELETE FROM blk_wijzigingen WHERE sollicitatie_id = ?",
        "DELETE FROM beschikbaarheid WHERE sollicitatie_id = ?",
        "DELETE FROM beschikbaarheid_profiel WHERE sollicitatie_id = ?",
        "DELETE FROM beschikbaarheid_blok WHERE sollicitatie_id = ?",
        "DELETE FROM beschikbaarheid_afwezig WHERE sollicitatie_id = ?",
        "DELETE FROM beschikbaarheid_verzoek WHERE sollicitatie_id = ?",
        "DELETE FROM beschikbaarheid_correctie WHERE sollicitatie_id = ?",
        "DELETE FROM beschikbaarheid_dag WHERE sollicitatie_id = ?",
        "DELETE FROM ai_beschikbaarheid_log WHERE sollicitatie_id = ?",
        "DELETE FROM ai_queue WHERE sollicitatie_id = ?",
        "DELETE FROM sollicitant_notities WHERE sollicitatie_id = ?",
        "DELETE FROM beoordelingen WHERE sollicitatie_id = ?",
        "DELETE FROM selectie_indeling WHERE sollicitatie_id = ?",
        "DELETE FROM selectie_score WHERE sollicitatie_id = ?",
        "DELETE FROM selectie_observatie WHERE sollicitatie_id = ?",
        "DELETE FROM selectie_besluit WHERE sollicitatie_id = ?",
        "DELETE FROM tw_deelname WHERE sollicitatie_id = ?",
        "DELETE FROM tw_observatie WHERE sollicitatie_id = ?",
        "DELETE FROM portaal_apparaten WHERE sollicitatie_id = ?",
        // De planning blijft staan voor het loonoverzicht (na anonimiseren niet meer herleidbaar); alleen de vrije notitie gaat weg.
        "UPDATE loon_planning SET notitie = NULL WHERE sollicitatie_id = ?",
    ] as $sql) avg_stap($db, $sql, [$sol_id]);
    // "Houd uit elkaar" werkt in twee richtingen.
    avg_stap($db, "DELETE FROM selectie_relatie WHERE sollicitatie_id = ? OR ander_id = ?", [$sol_id, $sol_id]);

    // Bewijsspoor van de ondertekeningen die niet bewaard hoeven te worden: alleen type en moment blijven.
    avg_stap($db, "UPDATE ondertekeningen SET ondertekenaar_naam=NULL, ondertekenaar_email=NULL, ondertekenaar_geboortedatum=NULL,
                   ondertekenaar_geboorteplaats=NULL, ondertekend_ip=NULL, ondertekend_user_agent=NULL
                   WHERE sollicitatie_id = ?" . $niet_loon, [$sol_id], true);
    avg_stap($db, "UPDATE ondertekeningen SET zekerheid_naar=NULL WHERE sollicitatie_id = ?" . $niet_loon, [$sol_id], true);
    avg_stap($db, "UPDATE ondertekening_gebeurtenissen SET ip=NULL, browser=NULL
                   WHERE ondertekening_id IN (SELECT id FROM ondertekeningen WHERE sollicitatie_id = ?" . $niet_loon . ")", [$sol_id]);
    if (!$ook_loondocs) {
        // Loonheffing: het bewijs blijft (bewaarplicht), maar zonder volledig IP-adres en zonder contactgegevens.
        avg_stap($db, "UPDATE ondertekeningen SET ondertekend_ip = " . avg_ip_masker_sql('ondertekend_ip') . ", ondertekenaar_email = NULL
                       WHERE sollicitatie_id = ? AND type = 'loonheffing'", [$sol_id], true);
        avg_stap($db, "UPDATE ondertekeningen SET zekerheid_naar = CONCAT('***', RIGHT(zekerheid_naar, 4))
                       WHERE sollicitatie_id = ? AND type = 'loonheffing' AND zekerheid_naar IS NOT NULL AND zekerheid_naar NOT LIKE '***%'", [$sol_id], true);
        avg_stap($db, "UPDATE ondertekening_gebeurtenissen SET ip = " . avg_ip_masker_sql('ip') . "
                       WHERE ondertekening_id IN (SELECT id FROM ondertekeningen WHERE sollicitatie_id = ? AND type = 'loonheffing')", [$sol_id]);
    }

    // Herleidbaar op de sollicitatie zelf. Kolommen kunnen ontbreken (consent_auto_setup).
    avg_stap($db, "UPDATE sollicitaties SET privacy_ip=NULL, ouder_toestemming_naam=NULL WHERE id=?", [$sol_id], true);
}

/**
 * Persoonsgegevens vervangen door een neutrale waarde. Alleen kolommen die bestaan worden
 * aangeraakt (installaties verschillen). Ook het afmeld-token en een seintje verdwijnen:
 * die zijn na anonimiseren nergens meer voor.
 *
 * $behoud: kolommen die blijven zoals ze zijn. Gebruikt bij een bewaard loondossier: naam en
 * geboortedatum blijven, zodat de penningmeester het dossier terugvindt (zie avg_behoud_loondossier()).
 */
function avg_persoon_anonimiseren($db, int $persoon_id, string $voornaam, string $achternaam, string $email, array $behoud = []): void {
    $dbn = $db->query("SELECT DATABASE()")->fetchColumn();
    $q = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='personen'");
    $q->execute([$dbn]);
    $bestaand = array_column($q->fetchAll(), 'COLUMN_NAME');

    $map = [
        'voornaam' => $voornaam, 'tussenvoegsel' => null, 'achternaam' => $achternaam, 'email' => $email,
        'telefoon' => null, 'geboortedatum' => null, 'adres' => null, 'straat' => null, 'huisnummer' => null,
        'postcode' => null, 'woonplaats' => null, 'geslacht' => null,
        'ouder_naam' => null, 'ouder_email' => null, 'ouder_telefoon' => null,
        'afmeld_token' => null, 'seintje_op' => null, 'seintje_bron' => null,
    ];
    $set = []; $params = [];
    foreach ($map as $kol => $waarde) {
        if (!in_array($kol, $bestaand, true) || in_array($kol, $behoud, true)) continue;
        $set[] = "`$kol`=?"; $params[] = $waarde;
    }
    if (!$set) return;
    $params[] = $persoon_id;
    $db->prepare("UPDATE personen SET " . implode(', ', $set) . " WHERE id=?")->execute($params);
}

/**
 * Wat er van de persoon blijft bij een bewaard loondossier: de identificatie die in de
 * loonadministratie bij de opgaaf hoort (naam en geboortedatum). BSN en adres staan in de
 * loonheffing-ondertekening zelf. E-mail, telefoon, adres en ouder gaan weg.
 */
function avg_behoud_loondossier(): array {
    return ['voornaam', 'tussenvoegsel', 'achternaam', 'geboortedatum'];
}

/**
 * Na het vernietigen van een loondossier (documenten_loondossier_vernietigen): was de persoon
 * al zelf verwijderd en stond alleen het loondossier nog, dan gaat nu ook de rest weg.
 * Doet niets zolang er een sollicitatie is die niet zelf verwijderd is (die volgt het opschonen).
 * Hoort in een transactie, net als de rest.
 */
function avg_na_loondossier_vernietigd($db, int $persoon_id): bool {
    $q = $db->prepare("SELECT id, fase FROM sollicitaties WHERE persoon_id = ?");
    $q->execute([$persoon_id]);
    $sols = $q->fetchAll();
    if (!$sols) return false;
    foreach ($sols as $s) if (($s['fase'] ?? '') !== 'verwijderd_door_sollicitant') return false;
    foreach ($sols as $s) avg_sollicitatie_leegmaken($db, (int)$s['id'], true);
    $eerste = (int)$sols[0]['id'];
    avg_persoon_anonimiseren($db, $persoon_id, 'Verwijderd', '(verzoek AVG)', 'verwijderd_' . $eerste . '@anoniem.local');
    return true;
}

/**
 * Het adres en de inhoud in het berichtenlog van één sollicitatie, en de wachtrij.
 *
 * In de wachtrij (mail_queue) staan adres, telefoon en de volledige tekst. Weg gaan: alles wat
 * al verwerkt is, en wat nog wacht voor de sollicitant of de ouder zelf (die hoeft na het
 * wissen niets meer te krijgen). Een bericht aan het bestuur dat nog wacht — zoals "sollicitant
 * heeft zelf verwijderd", dat vlak hiervoor is klaargezet — blijft staan tot het verstuurd is;
 * de opschoning ruimt het na 30 dagen op.
 */
function avg_maillog_anonimiseren($db, int $sol_id, string $email): void {
    try { $db->prepare("UPDATE mail_log SET ontvanger=?, body=NULL, samenvatting=NULL WHERE sollicitatie_id = ?")->execute([$email, $sol_id]); }
    catch (\Exception $e) { try { $db->prepare("UPDATE mail_log SET ontvanger=? WHERE sollicitatie_id = ?")->execute([$email, $sol_id]); } catch (\Exception $e2) {} }
    try {
        $db->prepare("DELETE FROM mail_queue WHERE sol_id = ? AND (status IN ('klaar','fout') OR ontvanger_rol IN ('sollicitant','ouder'))")->execute([$sol_id]);
    } catch (\PDOException $e) {
        // Oudere wachtrij zonder ontvanger_rol (42S22): dan alleen wat al verwerkt is. Geen tabel (42S02): niets te doen.
        if ($e->getCode() === '42S22') avg_stap($db, "DELETE FROM mail_queue WHERE sol_id = ? AND status IN ('klaar','fout')", [$sol_id]);
        elseif ($e->getCode() !== '42S02') throw $e;
    }
}

/**
 * Inzage (AVG art. 15): alles wat over deze sollicitatie is vastgelegd, gegroepeerd met
 * Nederlandse sleutels. Dezelfde tabellen als avg_sollicitatie_leegmaken().
 *
 * Wat er bewust niet in staat: BSN, IBAN en handtekening (die staan in de verzegelde PDF's
 * onder de taken), de bestanden zelf, en de namen van beoordelaars en bestuursleden (dat zijn
 * persoonsgegevens van een ander, art. 15 lid 4): daar staat "beoordelaar".
 * Een tabel die niet bestaat geeft een lege lijst; de download mag nooit breken.
 */
function avg_inzage($db, int $sol_id): array {
    $rijen = function (string $sql) use ($db, $sol_id): array {
        try { $q = $db->prepare($sql); $q->execute([$sol_id]); return $q->fetchAll(\PDO::FETCH_ASSOC); }
        catch (\Throwable $e) { return []; }
    };
    $rij = fn(string $sql) => $rijen($sql)[0] ?? null;
    $zonder = fn(array $lijst, array $weg) => array_map(fn($r) => array_diff_key($r, array_flip($weg)), $lijst);

    $blk = $rij("SELECT * FROM blk_formulieren WHERE sollicitatie_id = ?");
    $ai = null;
    if ($blk) {
        if (!empty($blk['ai_analyse'])) $ai = ['tekst' => $blk['ai_analyse'], 'gemaakt_op' => $blk['ai_analyse_op'] ?? null,
            'uitleg' => 'Een hulpmiddel voor het bestuur, gemaakt door een AI-dienst op basis van je formulier zonder naam en contactgegevens. Een mens beslist.'];
        unset($blk['ai_analyse'], $blk['ai_analyse_op']);
    }

    $beoordelaar = fn(array $lijst) => array_map(fn($r) => ['beoordelaar' => 'beoordelaar'] + $r, $lijst);

    return [
        'sollicitatie' => $rij("SELECT s.fase, s.aangemeld_op, sz.naam AS seizoen FROM sollicitaties s LEFT JOIN seizoenen sz ON sz.id = s.seizoen_id WHERE s.id = ?"),
        'kennismakingsformulier' => $blk,
        'kennismakingsformulier_wijzigingen' => $rijen("SELECT gewijzigd_op, reden, wijzigingen_json AS wijzigingen FROM blk_wijzigingen WHERE sollicitatie_id = ? ORDER BY id"),
        'ai_analyse' => $ai,
        'beschikbaarheid' => [
            'algemeen'              => $rij("SELECT * FROM beschikbaarheid_profiel WHERE sollicitatie_id = ?"),
            'periodes'              => $zonder($rijen("SELECT * FROM beschikbaarheid_blok WHERE sollicitatie_id = ? ORDER BY van_week"), ['id', 'sollicitatie_id']),
            'afwezig'               => $zonder($rijen("SELECT * FROM beschikbaarheid_afwezig WHERE sollicitatie_id = ? ORDER BY van_datum"), ['id', 'sollicitatie_id']),
            'verzoeken_minder_dagen'=> $zonder($rijen("SELECT * FROM beschikbaarheid_verzoek WHERE sollicitatie_id = ? ORDER BY id"), ['id', 'sollicitatie_id']),
            'correcties_bestuur'    => $rijen("SELECT week, soort, dagen, reden, aangemaakt_op FROM beschikbaarheid_correctie WHERE sollicitatie_id = ? ORDER BY week"),
            'losse_dagen'           => $rijen("SELECT datum, soort, reden, bron, status FROM beschikbaarheid_dag WHERE sollicitatie_id = ? ORDER BY datum"),
            'eerdere_opslag'        => $zonder($rijen("SELECT * FROM beschikbaarheid WHERE sollicitatie_id = ?"), ['id', 'sollicitatie_id']),
            'tekst_voor_ai_hulp'    => $rijen("SELECT aangemaakt_op, invoer, status FROM ai_beschikbaarheid_log WHERE sollicitatie_id = ? AND invoer <> '' ORDER BY id"),
        ],
        'selectiedag' => [
            'indeling'    => $rij("SELECT g.nummer AS groep, i.aanwezig, i.ingecheckt_op FROM selectie_indeling i LEFT JOIN selectie_groep g ON g.id = i.groep_id WHERE i.sollicitatie_id = ?"),
            'cijfers'     => $beoordelaar($rijen("SELECT test, criterium, score, bijgewerkt_op FROM selectie_score WHERE sollicitatie_id = ? ORDER BY test, criterium")),
            'observaties' => $beoordelaar($rijen("SELECT soort, tekst, aangemaakt_op FROM selectie_observatie WHERE sollicitatie_id = ? ORDER BY id")),
            'besluit'     => $rij("SELECT besluit, notitie, gebeld_op, gebeld_notitie, bijgewerkt_op FROM selectie_besluit WHERE sollicitatie_id = ?"),
            'beoordeling_oud' => $zonder($rijen("SELECT * FROM beoordelingen WHERE sollicitatie_id = ?"), ['id', 'sollicitatie_id', 'beoordelaar']),
        ],
        'trainingsweekend' => [
            'deelname'    => $rij("SELECT aanwezig, ingecheckt_op, afgemeld_reden, besluit, besluit_notitie, besluit_op FROM tw_deelname WHERE sollicitatie_id = ?"),
            'observaties' => $beoordelaar($rijen("SELECT soort, tekst, aangemaakt_op FROM tw_observatie WHERE sollicitatie_id = ? ORDER BY id")),
        ],
        'notities_bestuur' => $rijen("SELECT tekst, aangemaakt_op FROM sollicitant_notities WHERE sollicitatie_id = ? ORDER BY id"),
        'ondertekeningen' => $rijen("SELECT type, ondertekenaar_rol AS door, ondertekend_op, plaats FROM ondertekeningen WHERE sollicitatie_id = ? ORDER BY aangemaakt_op"),
        'documenten' => $rijen("SELECT soort, geupload_op AS datum, status, verwerkt_op, vernietigd_op FROM sollicitant_documenten WHERE sollicitatie_id = ? ORDER BY id"),
        'berichten' => $rijen("SELECT verstuurd_op AS datum, onderwerp, kanaal, status FROM mail_log
                               WHERE sollicitatie_id = ? AND COALESCE(ontvanger_rol, 'sollicitant') IN ('sollicitant', 'ouder') ORDER BY verstuurd_op DESC"),
        'ingelogde_apparaten' => $rijen("SELECT browser, ip, aangemaakt_op, laatst_gebruikt_op, vervalt_op FROM portaal_apparaten WHERE sollicitatie_id = ? ORDER BY id"),
    ];
}

}

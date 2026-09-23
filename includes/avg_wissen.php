<?php
/**
 * Paal 13 — AVG: gegevens van een sollicitatie wissen en een persoon anonimiseren.
 *
 * Eén plek voor "wat verdwijnt er als iemand weg moet". Twee routes gebruiken dit:
 *   - de sollicitant zelf, via Mijn gegevens → Gegevens laten verwijderen (portaal/gegevens.php);
 *   - het automatisch opschonen na de bewaartermijn (includes/opschonen.php).
 * Voorheen had het opschonen zijn eigen, kortere lijst: dan bleef bij een afgewezen
 * sollicitant na "anonimiseren" het BLK-formulier met adres en motivatie gewoon staan.
 *
 * Deze functies gooien bij een echte databasefout een exceptie: de aanroeper zet ze in een
 * transactie en rolt terug. Alleen een tabel die (nog) niet bestaat (SQLSTATE 42S02,
 * oudere installaties) wordt stil overgeslagen.
 *
 * Wat bewust blijft: de sollicitatie-rij zelf (fase, seizoen, data) voor de statistiek, en
 * het feit dát er ondertekend is. Loondocumenten horen hier niet: die hebben een fiscale
 * bewaarplicht en de penningmeester vernietigt ze met de hand (admin/documenten.php).
 */

if (!function_exists('avg_sollicitatie_leegmaken')) {

/** Voer één stap uit; alleen "tabel bestaat niet" mag stil voorbij. */
function avg_stap($db, string $sql, array $params): void {
    try { $db->prepare($sql)->execute($params); }
    catch (\PDOException $e) { if (($e->getCode() ?: '') !== '42S02') throw $e; }
}

/**
 * Alles wat bij één sollicitatie hoort en persoonlijk is: documenten in de kluis, taken,
 * vertrouwelijke ondertekengegevens, BLK met foto, beschikbaarheid, notities, beoordelingen,
 * en de herleidbare velden op de sollicitatie zelf.
 *
 * $ook_loondocs = false laat de loonheffing en de kopie van het identiteitsbewijs staan
 * (wettelijke bewaarplicht, eigen termijn). Zo gebruikt het opschonen van oud-lifeguards dit.
 */
function avg_sollicitatie_leegmaken($db, int $sol_id, bool $ook_loondocs = true): void {
    if (function_exists('documenten_bij_dossier_verwijderen')) documenten_bij_dossier_verwijderen($sol_id, $ook_loondocs);
    if (function_exists('foto_van_sollicitatie_verwijderen')) foto_van_sollicitatie_verwijderen($sol_id);
    $ondertekend = "UPDATE ondertekeningen SET vertrouwelijk=NULL, handtekening_beeld=NULL, pdf_document_id=NULL, formulier_data=NULL WHERE sollicitatie_id = ?"
                 . ($ook_loondocs ? '' : " AND type <> 'loonheffing'");
    foreach ([
        "DELETE FROM sollicitant_taken WHERE sollicitatie_id = ?",
        $ondertekend,
        "DELETE FROM blk_formulieren WHERE sollicitatie_id = ?",
        "DELETE FROM beschikbaarheid WHERE sollicitatie_id = ?",
        "DELETE FROM sollicitant_notities WHERE sollicitatie_id = ?",
        "DELETE FROM beoordelingen WHERE sollicitatie_id = ?",
    ] as $sql) avg_stap($db, $sql, [$sol_id]);
    // Herleidbaar op de sollicitatie zelf. Kolommen kunnen ontbreken (consent_auto_setup).
    try { $db->prepare("UPDATE sollicitaties SET privacy_ip=NULL, ouder_toestemming_naam=NULL WHERE id=?")->execute([$sol_id]); }
    catch (\PDOException $e) { if (!in_array($e->getCode(), ['42S02', '42S22'], true)) throw $e; }
}

/**
 * Persoonsgegevens vervangen door een neutrale waarde. Alleen kolommen die bestaan worden
 * aangeraakt (installaties verschillen). Ook het afmeld-token en een seintje verdwijnen:
 * die zijn na anonimiseren nergens meer voor.
 */
function avg_persoon_anonimiseren($db, int $persoon_id, string $voornaam, string $achternaam, string $email): void {
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
        if (!in_array($kol, $bestaand, true)) continue;
        $set[] = "`$kol`=?"; $params[] = $waarde;
    }
    if (!$set) return;
    $params[] = $persoon_id;
    $db->prepare("UPDATE personen SET " . implode(', ', $set) . " WHERE id=?")->execute($params);
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

}

<?php
/**
 * Paal 13 — Systeemgezondheid (health check).
 *
 * Controleert de belangrijkste onderdelen van het systeem en, waar mogelijk, herstelt ze
 * automatisch. Bouwt voort op db_failsafe (database) en voegt controles toe voor de cron,
 * de mailwachtrij, de opschoning, de API-configuratie en het schrijfbaar zijn van mappen.
 *
 * Elke check geeft terug: status ('ok' | 'waarschuwing' | 'fout'), een leesbare uitleg, en
 * of er automatisch iets hersteld is. Niets hierin mag ooit een pagina breken.
 */

if (!function_exists('health_check')) {

/**
 * Voer alle checks uit. $repareer=true probeert waar mogelijk automatisch te herstellen.
 * Retourneert ['ok'=>bool, 'checks'=>[...], 'samenvatting'=>string].
 */
function health_check(bool $repareer = false): array {
    $checks = [];

    $checks['database']   = health_check_database($repareer);
    $checks['cron']       = health_check_cron();
    $checks['mailwachtrij'] = health_check_mailwachtrij();
    $checks['opschoning'] = health_check_opschoning();
    $checks['mappen']     = health_check_mappen();
    $checks['configuratie'] = health_check_configuratie();
    $checks['kluis']      = health_check_kluis();
    $checks['zegel']      = health_check_zegel();
    $checks['snelheid']   = health_check_snelheid();

    // Totaaloordeel: fout > waarschuwing > ok
    $heeft_fout = false; $heeft_waarschuwing = false;
    foreach ($checks as $c) {
        if ($c['status'] === 'fout') $heeft_fout = true;
        if ($c['status'] === 'waarschuwing') $heeft_waarschuwing = true;
    }
    $totaal = $heeft_fout ? 'fout' : ($heeft_waarschuwing ? 'waarschuwing' : 'ok');

    return [
        'ok'          => !$heeft_fout,
        'status'      => $totaal,
        'checks'      => $checks,
        'gecontroleerd_op' => date('c'),
    ];
}

/**
 * Kan de server antwoorden vóór het zware werk (mail, WhatsApp) klaar is? Alleen onder PHP-FPM
 * (of LiteSpeed). Zonder dat wacht elke bezoeker na "verstuur" op de mailserver.
 */
function health_check_snelheid(): array {
    if (PHP_SAPI === 'cli') return ['status' => 'ok', 'uitleg' => 'Niet te controleren vanaf de opdrachtregel.', 'hersteld' => false];
    if (function_exists('na_antwoord_mogelijk') && na_antwoord_mogelijk()) {
        return ['status' => 'ok', 'uitleg' => 'Berichten gaan ná het antwoord de deur uit.', 'hersteld' => false];
    }
    return ['status' => 'waarschuwing', 'uitleg' => 'PHP draait niet als FPM: na elk formulier wacht de bezoeker tot mails en WhatsApp verstuurd zijn. Zet in Plesk de PHP-handler op "FPM application served by nginx/Apache".', 'hersteld' => false];
}

/** Database: bestaan alle tabellen en kolommen? Repareert via db_failsafe. */
function health_check_database(bool $repareer): array {
    if (!function_exists('db_gezondheid')) {
        return ['status' => 'waarschuwing', 'uitleg' => 'Database-controle niet beschikbaar.', 'hersteld' => false];
    }
    $g = db_gezondheid();
    if ($g['ok']) {
        return ['status' => 'ok', 'uitleg' => 'Alle tabellen en kolommen aanwezig.', 'hersteld' => false];
    }
    // Probeer te repareren
    $hersteld = false;
    if ($repareer && function_exists('db_failsafe')) {
        // Geforceerd: de versievlag (db.failsafe_versie) slaat de run anders over.
        try { db_failsafe(true); $hersteld = true; } catch (\Throwable $e) {}
        $g = db_gezondheid();
        if ($g['ok']) {
            return ['status' => 'ok', 'uitleg' => 'Ontbrekende onderdelen automatisch aangemaakt.', 'hersteld' => true];
        }
    }
    $mist = [];
    foreach ($g['tabellen'] as $t => $bestaat) { if (!$bestaat) $mist[] = "tabel $t"; }
    $mist = array_merge($mist, $g['ontbrekende_kolommen'] ?? []);
    return [
        'status'   => 'fout',
        'uitleg'   => 'Ontbrekend: ' . implode(', ', $mist) . '. Klik op "Controleren & repareren" of open een pagina zodat het zichzelf herstelt.',
        'hersteld' => $hersteld,
    ];
}

/**
 * Cron: draait elke groep (snel/kwartier/dag) op tijd? Er is geen vangnet meer bij een
 * paginabezoek, dus een stilgevallen cron betekent: geen berichten, geen herinneringen.
 */
function health_check_cron(): array {
    if (!function_exists('cron_stand')) return ['status' => 'waarschuwing', 'uitleg' => 'Cron-module niet geladen.', 'hersteld' => false];
    $problemen = []; $status = 'ok';
    foreach (cron_stand() as $g) {
        if ($g['status'] === 'ok') continue;
        $wanneer = $g['laatste'] ? 'voor het laatst ' . date('d-m-Y H:i', strtotime($g['laatste'])) : 'nog nooit';
        $problemen[] = $g['label'] . ': ' . $wanneer . '.';
        if ($g['status'] === 'stil' || $g['status'] === 'nooit') $status = 'fout';
        elseif ($status === 'ok') $status = 'waarschuwing';
    }
    if (!$problemen) return ['status' => 'ok', 'uitleg' => 'Alle automatische taken draaien op tijd.', 'hersteld' => false];
    return ['status' => $status, 'uitleg' => implode(' ', $problemen) . ' Controleer de geplande taak in Plesk (php cron/run.php, elke minuut).', 'hersteld' => false];
}

/** Mailwachtrij: blijven er berichten hangen? */
function health_check_mailwachtrij(): array {
    try {
        $db = db();
        // Tabel bestaat mogelijk niet
        if (function_exists('db_tabel_bestaat') && !db_tabel_bestaat($db, 'mail_queue')) {
            return ['status' => 'ok', 'uitleg' => 'Geen mailwachtrij in gebruik.', 'hersteld' => false];
        }
        // Berichten die al lang wachten (meer dan 1 uur, nog niet verzonden)
        $q = $db->query("SELECT COUNT(*) FROM mail_queue WHERE status IN ('wacht','bezig') AND aangemaakt_op < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $vast = (int)$q->fetchColumn();
        if ($vast > 0) {
            return ['status' => 'waarschuwing', 'uitleg' => "$vast e-mail(s) wachten al meer dan een uur. Draait de cron en klopt de SMTP-instelling?", 'hersteld' => false];
        }
        return ['status' => 'ok', 'uitleg' => 'Mailwachtrij loopt door.', 'hersteld' => false];
    } catch (\Exception $e) {
        return ['status' => 'ok', 'uitleg' => 'Mailwachtrij niet te controleren (mogelijk niet in gebruik).', 'hersteld' => false];
    }
}

/** Opschoning: is die recent gedraaid? */
function health_check_opschoning(): array {
    // Uit = rood: dan wordt niets verwijderd, terwijl de privacyverklaring en de afwijzingsmail
    // termijnen beloven. Dat is geen technisch probleem maar een AVG-probleem.
    if (function_exists('instelling_bool') && !instelling_bool('bewaar.actief', false)) {
        return ['status' => 'fout', 'uitleg' => health_opschonen_uit_tekst(), 'hersteld' => false];
    }
    $laatste = function_exists('instelling') ? instelling('bewaar.laatste_run', '') : '';
    if ($laatste === '') {
        return ['status' => 'waarschuwing', 'uitleg' => 'De AVG-opschoning heeft nog niet gedraaid.', 'hersteld' => false];
    }
    $sinds = time() - strtotime($laatste);
    $leesbaar = date('d-m-Y', strtotime($laatste));
    // Hoort dagelijks; boven 3 dagen = aandacht
    if ($sinds > 259200) {
        return ['status' => 'waarschuwing', 'uitleg' => "Opschoning draaide voor het laatst op $leesbaar (meer dan 3 dagen geleden).", 'hersteld' => false];
    }
    return ['status' => 'ok', 'uitleg' => "Opschoning recent gedraaid ($leesbaar).", 'hersteld' => false];
}

/** De waarschuwing als automatisch opschonen uitstaat. Eén tekst voor de systeemcontrole en Vandaag. */
function health_opschonen_uit_tekst(): string {
    return 'Automatisch opschonen staat uit — er wordt niets verwijderd; de privacyverklaring belooft wel termijnen. Zet het aan bij Instellingen › Bewaren & opschonen.';
}

/** Mappen: zijn de upload/output-mappen schrijfbaar? */
function health_check_mappen(): array {
    $te_checken = [];
    if (defined('UPLOAD_PAD')) $te_checken['uploads'] = UPLOAD_PAD;
    else $te_checken['uploads'] = __DIR__ . '/../uploads';

    $problemen = [];
    foreach ($te_checken as $naam => $pad) {
        if (!is_dir($pad)) {
            // Probeer aan te maken
            @mkdir($pad, 0755, true);
        }
        if (!is_dir($pad) || !is_writable($pad)) {
            $problemen[] = $naam;
        }
    }
    if ($problemen) {
        return ['status' => 'fout', 'uitleg' => 'Niet schrijfbaar: ' . implode(', ', $problemen) . '. Controleer de maprechten.', 'hersteld' => false];
    }
    return ['status' => 'ok', 'uitleg' => 'Mappen schrijfbaar.', 'hersteld' => false];
}

/**
 * Kluis: kan het systeem gevoelige documenten (ID, bankpas, BSN) versleuteld opslaan?
 * Zonder werkende kluis weigert het portaal die uploads en ondertekeningen.
 */
function health_check_kluis(): array {
    if (!function_exists('kluis_status')) {
        return ['status' => 'waarschuwing', 'uitleg' => 'Kluis-module niet geladen.', 'hersteld' => false];
    }
    $s = kluis_status();
    if (!$s['ok']) {
        return ['status' => 'fout', 'uitleg' => 'Kluis niet beschikbaar: ' . $s['reden'] . ' Uploads van ID/bankpas en het loonheffingsformulier zijn geblokkeerd tot dit is opgelost.', 'hersteld' => false];
    }
    $uitleg = 'Kluis werkt (' . $s['algoritme'] . ').';
    if (function_exists('documenten_map')) {
        $map = documenten_map();
        if ($map === null) return ['status' => 'fout', 'uitleg' => $uitleg . ' Maar de documentenmap is niet schrijfbaar (DOCUMENTEN_MAP of uploads/documenten).', 'hersteld' => false];
    }
    // Sleutelrotatie (admin/kluis.php): staat er nog iets op een oude sleutel, of is de oude sleutel overbodig?
    $punten = [];
    $status  = 'ok';
    if (function_exists('kluis_rotatie_status')) {
        $r = kluis_rotatie_status();
        if ($r['configfout'] !== '') { $punten[] = $r['configfout']; $status = 'fout'; }
        if ($r['zonder_sleutel'] > 0) {
            $punten[] = $r['zonder_sleutel'] . ' versleutelde gegevens staan op een sleutelversie die niet in config.php staat en zijn onleesbaar. Zet die sleutel terug als KLUIS_SLEUTEL_OUD.';
            $status = 'fout';
        }
        if ($r['oud'] > 0) {
            $punten[] = $r['oud'] . ' versleutelde gegevens staan nog op de oude sleutel (versie ' . (int)$r['oude_versie'] . '). Rond het wisselen af bij Systeem › Kluissleutel wisselen.';
            if ($status === 'ok') $status = 'waarschuwing';
        } elseif ($r['oude_versie'] !== null && $r['zonder_sleutel'] === 0) {
            $punten[] = 'Niets staat meer op de oude sleutel: verwijder KLUIS_SLEUTEL_OUD en KLUIS_SLEUTEL_OUD_VERSIE uit config.php.';
            if ($status === 'ok') $status = 'waarschuwing';
        }
    }
    if (function_exists('documenten_map_in_webroot') && function_exists('documenten_map') && documenten_map_in_webroot()) {
        $punten[] = 'De documentenmap staat binnen de webroot (afgeschermd met .htaccess). Beter: DOCUMENTEN_MAP in config.php naar een map buiten httpdocs.';
        if ($status === 'ok') $status = 'waarschuwing';
    }
    if ($punten) return ['status' => $status, 'uitleg' => $uitleg . ' ' . implode(' ', $punten), 'hersteld' => false];
    return ['status' => 'ok', 'uitleg' => $uitleg . ' Documentenmap buiten de webroot.', 'hersteld' => false];
}

/** Zegel: kan het systeem PDF's digitaal verzegelen (TCPDF/FPDI + certificaat + sleutel in de kluis)? */
function health_check_zegel(): array {
    if (!function_exists('zegel_status')) {
        return ['status' => 'waarschuwing', 'uitleg' => 'Zegel-module niet geladen.', 'hersteld' => false];
    }
    $s = zegel_status();
    if (!$s['ok']) {
        return ['status' => 'fout', 'uitleg' => 'Documentzegel niet beschikbaar: ' . $s['reden'] . ' Ondertekende PDF\'s worden dan zonder digitaal zegel gemaakt.', 'hersteld' => false];
    }
    $i = $s['info'];
    $dagen = (int)floor((strtotime($i['geldig_tot']) - time()) / 86400);
    $uitleg = 'Zegelcertificaat "' . $i['naam'] . '" geldig t/m ' . $i['geldig_tot'] . ($i['zelf_uitgegeven'] ? ' (zelf uitgegeven; voor een groen vinkje in Acrobat is een AATL-certificaat nodig)' : '') . '.';
    if ($dagen < 60) return ['status' => 'waarschuwing', 'uitleg' => $uitleg . " Verloopt over $dagen dagen.", 'hersteld' => false];
    return ['status' => 'ok', 'uitleg' => $uitleg, 'hersteld' => false];
}

/** Configuratie: zijn de basiszaken ingesteld? */
function health_check_configuratie(): array {
    $ontbreekt = [];
    if (function_exists('instelling')) {
        if (instelling('cron.token', '') === '') $ontbreekt[] = 'cron-token';
        // SMTP alleen waarschuwen als er een mailwachtrij is
    }
    // Zelfde volgorde als het verzenden: de instelling mail.bestuur_email, anders MAIL_BESTUUR.
    $bestuur = function_exists('bestuur_email') ? bestuur_email()
             : (trim(function_exists('instelling') ? instelling('mail.bestuur_email', '') : '') ?: (defined('MAIL_BESTUUR') ? (string)MAIL_BESTUUR : ''));
    if ($bestuur === '') $ontbreekt[] = 'bestuur-e-mailadres';

    if ($ontbreekt) {
        return ['status' => 'waarschuwing', 'uitleg' => 'Nog niet ingesteld: ' . implode(', ', $ontbreekt) . '.', 'hersteld' => false];
    }
    return ['status' => 'ok', 'uitleg' => 'Basisconfiguratie compleet.', 'hersteld' => false];
}

}

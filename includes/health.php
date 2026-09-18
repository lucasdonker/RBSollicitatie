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
        // db_failsafe draait 1x per request; forceer een nieuwe controle door de static te omzeilen
        // is niet nodig — bij een aparte reparatie-aanroep roepen we de setups direct aan.
        try { db_failsafe(); $hersteld = true; } catch (\Throwable $e) {}
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
        'uitleg'   => 'Ontbrekend: ' . implode(', ', $mist) . '. Klik op "Repareren" of open een pagina zodat het zichzelf herstelt.',
        'hersteld' => $hersteld,
    ];
}

/** Cron: is de laatste run recent genoeg? */
function health_check_cron(): array {
    $laatste = function_exists('instelling') ? instelling('cron.laatste_run', '') : '';
    $bron    = function_exists('instelling') ? instelling('cron.laatste_bron', '') : '';
    if ($laatste === '') {
        return ['status' => 'waarschuwing', 'uitleg' => 'De cron heeft nog nooit gedraaid. Controleer de cronjob-instelling.', 'hersteld' => false];
    }
    $sinds = time() - strtotime($laatste);
    $leesbaar = date('d-m-Y H:i', strtotime($laatste));
    // De cron hoort elke 5 min te draaien; de lazy-cron vangt op tot 1u. Boven 2u = probleem.
    if ($sinds > 7200) {
        return ['status' => 'fout', 'uitleg' => "De cron draaide voor het laatst op $leesbaar (meer dan 2 uur geleden). Controleer de cronjob.", 'hersteld' => false];
    }
    if ($sinds > 1800) {
        return ['status' => 'waarschuwing', 'uitleg' => "De cron draaide voor het laatst op $leesbaar. Dat is langer dan verwacht (>30 min).", 'hersteld' => false];
    }
    return ['status' => 'ok', 'uitleg' => "Cron draait ($leesbaar, via $bron).", 'hersteld' => false];
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
        if (function_exists('documenten_map_in_webroot') && documenten_map_in_webroot()) {
            return ['status' => 'waarschuwing', 'uitleg' => $uitleg . ' De documentenmap staat binnen de webroot (afgeschermd met .htaccess). Beter: DOCUMENTEN_MAP in config.php naar een map buiten httpdocs.', 'hersteld' => false];
        }
    }
    return ['status' => 'ok', 'uitleg' => $uitleg . ' Documentenmap buiten de webroot.', 'hersteld' => false];
}

/** Configuratie: zijn de basiszaken ingesteld? */
function health_check_configuratie(): array {
    $ontbreekt = [];
    if (function_exists('instelling')) {
        if (instelling('cron.token', '') === '') $ontbreekt[] = 'cron-token';
        // SMTP alleen waarschuwen als er een mailwachtrij is
    }
    if (!defined('MAIL_BESTUUR') || !MAIL_BESTUUR) $ontbreekt[] = 'bestuur-e-mailadres';

    if ($ontbreekt) {
        return ['status' => 'waarschuwing', 'uitleg' => 'Nog niet ingesteld: ' . implode(', ', $ontbreekt) . '.', 'hersteld' => false];
    }
    return ['status' => 'ok', 'uitleg' => 'Basisconfiguratie compleet.', 'hersteld' => false];
}

}

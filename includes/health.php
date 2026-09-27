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
 *
 * Een check kan ook 'punten' hebben: losse dingen die nog ingesteld moeten worden, elk als
 * ['sleutel','tekst','url','knop','soort' => fout|waarschuwing|info]. Samen vormen die de lijst
 * "Nog in te stellen" (health_nog_in_te_stellen()) op Vandaag en bij Systeem. Geen HTML hier.
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
    $checks['berichten']  = health_check_berichten();
    $checks['inloggen']   = health_check_inloggen();
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

/**
 * Stand van de mailwachtrij, ook voor api/status.php: wachtend, vast (wacht/bezig langer dan een
 * uur) en definitief mislukt in de afgelopen 24 uur. null als de wachtrij niet te lezen is.
 * @return ?array{wacht:int, vast:int, fout_24u:int}
 */
function health_wachtrij_stand(): ?array {
    try {
        $db = db();
        if (function_exists('db_tabel_bestaat') && !db_tabel_bestaat($db, 'mail_queue')) return null;
        $r = $db->query("SELECT
                SUM(status = 'wacht') AS wacht,
                SUM(status IN ('wacht','bezig') AND aangemaakt_op < DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS vast,
                SUM(status = 'fout' AND aangemaakt_op > DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS fout_24u
            FROM mail_queue")->fetch();
        return ['wacht' => (int)($r['wacht'] ?? 0), 'vast' => (int)($r['vast'] ?? 0), 'fout_24u' => (int)($r['fout_24u'] ?? 0)];
    } catch (\Throwable $e) {
        return null;
    }
}

/** Mailwachtrij: blijven er berichten hangen, of zijn er definitief mislukt? */
function health_check_mailwachtrij(): array {
    $s = health_wachtrij_stand();
    if ($s === null) return ['status' => 'ok', 'uitleg' => 'Mailwachtrij niet te controleren (mogelijk niet in gebruik).', 'hersteld' => false];
    $punten = [];
    if ($s['vast'] > 0) $punten[] = "{$s['vast']} bericht(en) wachten al meer dan een uur. Draait de cron en klopt de SMTP-instelling?";
    // Na drie pogingen staat een bericht op 'fout' en wordt het niet meer geprobeerd: de ontvanger heeft het nooit gehad.
    if ($s['fout_24u'] > 0) $punten[] = "{$s['fout_24u']} bericht(en) definitief mislukt in de afgelopen 24 uur. De reden staat in het logboek (bron Berichten).";
    if ($punten) return ['status' => 'waarschuwing', 'uitleg' => implode(' ', $punten), 'hersteld' => false];
    return ['status' => 'ok', 'uitleg' => 'Mailwachtrij loopt door.', 'hersteld' => false];
}

/** Opschoning: is die recent gedraaid? */
function health_check_opschoning(): array {
    // Uit = rood: dan wordt niets verwijderd, terwijl de privacyverklaring en de afwijzingsmail
    // termijnen beloven. Dat is geen technisch probleem maar een AVG-probleem.
    if (function_exists('instelling_bool') && !instelling_bool('bewaar.actief', false)) {
        return ['status' => 'fout', 'uitleg' => health_opschonen_uit_tekst(), 'hersteld' => false,
                'punten' => [health_punt('opschonen', 'fout', 'Automatisch opschonen staat uit: er wordt niets verwijderd, terwijl de privacyverklaring termijnen belooft.', 'bewaren', 'Opschonen aanzetten')]];
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

/**
 * Eén punt voor de lijst "Nog in te stellen". $waar is een instellingentabblad ('mail') of een
 * pad vanaf de app-root ('/admin/seizoen.php').
 */
function health_punt(string $sleutel, string $soort, string $tekst, string $waar, string $knop): array {
    $basis = defined('APP_URL') ? APP_URL : '';
    $url = str_starts_with($waar, '/') ? $basis . $waar : $basis . '/admin/instellingen.php?tab=' . rawurlencode($waar);
    return ['sleutel' => $sleutel, 'soort' => $soort, 'tekst' => $tekst, 'url' => $url, 'knop' => $knop];
}

/** Check-uitkomst uit een lijst punten: de zwaarste soort telt; 'info' maakt de check niet oranje. */
function health_uit_punten(array $punten, string $ok_tekst): array {
    $status = 'ok';
    foreach ($punten as $p) {
        if ($p['soort'] === 'fout') $status = 'fout';
        elseif ($p['soort'] === 'waarschuwing' && $status === 'ok') $status = 'waarschuwing';
    }
    $uitleg = $punten ? implode(' ', array_column($punten, 'tekst')) : $ok_tekst;
    return ['status' => $status, 'uitleg' => $uitleg, 'hersteld' => false, 'punten' => $punten];
}

/**
 * Berichten: gaat e-mail via SMTP, en staat de testmodus (MAIL_OMLEIDEN) aan?
 * De testmodus is geen instelpunt maar een stand; hij staat daarom niet in "Nog in te stellen".
 */
function health_check_berichten(): array {
    $punten = [];
    if (class_exists('Mailer') && !Mailer::smtpActief()) {
        $punten[] = health_punt('smtp', 'waarschuwing', 'Er is geen SMTP-server ingesteld: e-mail gaat via PHP mail() en komt op gedeelde hosting vaak niet aan of in de spam.', 'smtp', 'SMTP instellen');
    }
    $uit = health_uit_punten($punten, 'E-mail gaat via de ingestelde SMTP-server.');
    $test = function_exists('mail_omleiden') ? mail_omleiden() : '';
    if ($test !== '') {
        $uit['uitleg'] = health_testmodus_tekst($test) . ($punten ? ' ' . $uit['uitleg'] : '');
        if ($uit['status'] === 'ok') $uit['status'] = 'waarschuwing';
    }
    return $uit;
}

/** De zin over de testmodus. Eén tekst voor de topbalk, de systeemcontrole en Vandaag. */
function health_testmodus_tekst(string $adres): string {
    return 'Testmodus: alle e-mail gaat naar ' . $adres . '; WhatsApp en sms worden niet verstuurd (MAIL_OMLEIDEN in config.php).';
}

/** Inloggen in het beheer: is Authentik (OIDC) ingesteld? Zonder werkt alleen de wachtwoord-noodingang. */
function health_check_inloggen(): array {
    $punten = [];
    if (function_exists('authentik_actief') && !authentik_actief()) {
        $punten[] = health_punt('oidc', 'waarschuwing', 'Inloggen via Authentik is niet ingesteld (OIDC_CLIENT_ID en OIDC_ISSUER in config.php): het beheer werkt dan alleen met de wachtwoord-noodingang.', 'authentik', 'Authentik bekijken');
    }
    return health_uit_punten($punten, 'Inloggen via Authentik is ingesteld.');
}

/**
 * Configuratie: de basiszaken die bij de eerste keer instellen horen.
 *
 * Het bestuursadres telt als ingesteld zodra het niet leeg en geen {{PLAATSHOUDER}} is. De standaard
 * bestuur@paal13.com is bewust géén waarschuwing: dat is het echte adres van de brigade.
 */
function health_check_configuratie(): array {
    $punten = [];
    $inst = fn(string $k, string $d = '') => function_exists('instelling') ? trim(instelling($k, $d)) : $d;

    if ($inst('cron.token') === '') {
        // Het tabblad maakt het token vanzelf aan zodra je het opent.
        $punten[] = health_punt('cron_token', 'waarschuwing', 'Er is geen cron-token: de taakronde via HTTP en de uptime-monitor (api/status.php) werken dan niet.', 'bewaren', 'Token aanmaken');
    }
    // Zelfde volgorde als het verzenden: de instelling mail.bestuur_email, anders MAIL_BESTUUR.
    $bestuur = function_exists('bestuur_email') ? bestuur_email() : $inst('mail.bestuur_email');
    if (!config_ingevuld($bestuur)) {
        $punten[] = health_punt('bestuur', 'waarschuwing', 'Er is geen bestuursadres: meldingen voor het bestuur worden niet verstuurd.', 'mail', 'Adres invullen');
    }
    if ($inst('mail.penningmeester_email') === '') {
        $punten[] = health_punt('penningmeester', 'waarschuwing', 'Er is geen adres voor de penningmeester: loondocumenten (IBAN, BSN) gaan nu naar het bestuursadres.', 'mail', 'Adres invullen');
    }
    if ($inst('mail.ledenservice_email') === '') {
        $punten[] = health_punt('ledenservice', 'waarschuwing', 'Er is geen adres voor de ledenservice: inschrijvingen voor het lidmaatschap (met IBAN) gaan nu naar het bestuursadres.', 'mail', 'Adres invullen');
    }
    // Huisregels horen bij het takenpakket voor aangenomen lifeguards; staat dat uit, dan maakt het niet uit.
    if ($inst('auto.kennismakingspakket', '1') === '1' && (function_exists('instelling_int') ? instelling_int('taken.huisregels_bijlage_id', 0) : 0) <= 0) {
        $punten[] = health_punt('huisregels', 'waarschuwing', 'Er is geen huisregels-PDF gekozen: aangenomen lifeguards zien bij die taak "volgt binnenkort".', 'algemeen', 'Huisregels kiezen');
    }
    // Informatief: geen fout, wel handig om te weten bij de eerste keer instellen.
    $sz = function_exists('actief_seizoen') ? actief_seizoen() : null;
    if (!$sz || ($sz['status'] ?? '') !== 'open') {
        $punten[] = health_punt('seizoen', 'info', 'Er is geen open seizoen: aanmelden staat dicht.', '/admin/seizoen.php', 'Seizoen');
    }
    return health_uit_punten($punten, 'Basisconfiguratie compleet.');
}

/**
 * De lijst "Nog in te stellen": alle punten uit de lichte checks (geen database-schema, geen kluis
 * of zegel), zwaarste eerst. Goedkoop genoeg voor Vandaag.
 * @return array<int,array{sleutel:string,soort:string,tekst:string,url:string,knop:string}>
 */
function health_nog_in_te_stellen(): array {
    $punten = [];
    foreach (['health_check_opschoning', 'health_check_berichten', 'health_check_inloggen', 'health_check_configuratie'] as $fn) {
        try { foreach ($fn()['punten'] ?? [] as $p) $punten[] = $p; } catch (\Throwable $e) {}
    }
    $gewicht = ['fout' => 0, 'waarschuwing' => 1, 'info' => 2];
    usort($punten, fn($a, $b) => ($gewicht[$a['soort']] ?? 3) <=> ($gewicht[$b['soort']] ?? 3));
    return $punten;
}

}

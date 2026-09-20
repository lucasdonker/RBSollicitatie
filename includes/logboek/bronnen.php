<?php
/**
 * Logboek — het bronregister.
 *
 * Het systeem schrijft zijn sporen naar zes verschillende tabellen. Deze module legt ze naast
 * elkaar in één tijdlijn. Elke bron vertaalt zijn eigen kolommen naar één gemeenschappelijk
 * formaat (zie log_velden()); de rest van de module kent alleen dat formaat.
 *
 * EEN BRON TOEVOEGEN: voeg één blok toe aan log_bronnen(). Meer niet — query.php en het scherm
 * passen zich vanzelf aan.
 *
 * De veld-expressies worden zowel voor de SELECT als voor de WHERE gebruikt. Een bron die een
 * veld niet kent zet er 'NULL' neer; een filter op dat veld sluit de bron dan automatisch uit.
 *
 * Leest alleen. Deze module maakt geen tabellen aan en schrijft nergens naartoe.
 */

if (!function_exists('log_velden')) {

/**
 * Wat deze module bij de kern registreert. Registry-klaar: drie haken, meer niet.
 * (Geen 'setup': het logboek leest alleen, dus db_failsafe hoeft hier niets voor te doen.)
 */
function log_module_definitie(): array {
    return [
        'sleutel' => 'logboek',
        'recht'   => ['label' => 'Logboek (alle logs)', 'groep' => 'Beheer', 'acties' => ['lezen']],
        'nav'     => ['id' => 'logboek', 'label' => 'Logboek', 'url' => '/admin/logboek.php'],
    ];
}

/**
 * Het gemeenschappelijke formaat. De volgorde is bindend: elke UNION-tak levert precies
 * deze kolommen, in deze volgorde.
 */
function log_velden(): array {
    return ['moment', 'wie_type', 'wie_id', 'wie_naam', 'actie', 'objecttype', 'object_id', 'details', 'ip', 'ernst'];
}

/** De drie ernstniveaus, oplopend. */
function log_ernsten(): array {
    return ['info' => 'Info', 'waarschuwing' => 'Aandacht', 'fout' => 'Fout'];
}

/**
 * Welke kolommen bestaat deze tabel uit? Gecached per request.
 * Retourneert een lege array als de tabel niet bestaat — zo kan de aanroeper een bron overslaan
 * in plaats van de hele UNION te laten klappen.
 */
function log_tabel_kolommen(string $tabel): array {
    static $cache = [];
    if (isset($cache[$tabel])) return $cache[$tabel];
    $kolommen = [];
    try {
        // Tabelnaam komt uit ons eigen register, nooit uit gebruikersinvoer.
        foreach (db()->query("SHOW COLUMNS FROM `$tabel`")->fetchAll() as $r) {
            $kolommen[] = $r['Field'];
        }
    } catch (\Throwable $e) {
        $kolommen = [];  // tabel bestaat nog niet (alles draait op auto-setup)
    }
    return $cache[$tabel] = $kolommen;
}

/** Bestaat deze kolom in deze tabel? */
function log_heeft_kolom(string $tabel, string $kolom): bool {
    return in_array($kolom, log_tabel_kolommen($tabel), true);
}

/**
 * Het register. Per bron:
 *   label/uitleg/icoon  weergave in het scherm
 *   tabel               de brontabel (moet bestaan, anders valt de bron stil weg)
 *   velden              SQL-expressie per veld uit log_velden(); 'NULL' = bron kent dit veld niet
 *
 * De 'velden' zijn een closure omdat sommige kolommen pas door een latere migratie zijn
 * toegevoegd (mail_log kreeg kanaal/meldingstype in 2.87). Op een database die nog niet is
 * bijgewerkt valt het veld dan terug op een literal in plaats van de query te laten falen.
 */
function log_bronnen(): array {
    return [
        'audit' => [
            'label'  => 'Audit',
            'uitleg' => 'Wie wat wanneer deed: aanmeldingen, fasewijzigingen, downloads, instellingen.',
            'icoon'  => 'schild',
            'tabel'  => 'audit_log',
            'velden' => function (string $t): array {
                return [
                    'moment'     => 'moment',
                    'wie_type'   => 'wie_type',
                    'wie_id'     => 'wie_id',
                    'wie_naam'   => 'wie_naam',
                    'actie'      => 'actie',
                    'objecttype' => 'objecttype',
                    'object_id'  => 'object_id',
                    'details'    => 'details',
                    'ip'         => 'ip',
                    'ernst'      => "'info'",
                ];
            },
        ],

        'fout' => [
            'label'  => 'Fouten',
            'uitleg' => 'Technische fouten met hun referentienummer, zoals ze ook op de foutpagina staan.',
            'icoon'  => 'waarschuwing',
            'tabel'  => 'error_log',
            'velden' => function (string $t): array {
                return [
                    'moment'     => 'aangemaakt_op',
                    'wie_type'   => "'systeem'",
                    'wie_id'     => 'NULL',
                    'wie_naam'   => 'gebruiker',
                    'actie'      => 'code',
                    'objecttype' => 'NULL',
                    'object_id'  => 'NULL',
                    // bericht · bestand:regel · url — CONCAT_WS slaat lege onderdelen over
                    'details'    => "CONCAT_WS(' \u{00b7} ', bericht, CONCAT(bestand, ':', regel), url)",
                    'ip'         => 'NULL',
                    'ernst'      => "'fout'",
                ];
            },
        ],

        'bericht' => [
            'label'  => 'Berichten',
            'uitleg' => 'Verstuurde e-mail, WhatsApp en sms. Alleen het onderwerp — nooit de inhoud van het bericht.',
            'icoon'  => 'mail',
            'tabel'  => 'mail_log',
            'velden' => function (string $t): array {
                $heeft_type   = log_heeft_kolom($t, 'meldingstype');
                $heeft_kanaal = log_heeft_kolom($t, 'kanaal');
                $heeft_fout   = log_heeft_kolom($t, 'foutmelding');
                return [
                    'moment'   => 'verstuurd_op',
                    'wie_type' => "'systeem'",
                    'wie_id'   => log_heeft_kolom($t, 'persoon_id') ? 'persoon_id' : 'NULL',
                    // Bij een bericht is de betrokken persoon de ontvanger, niet de handelende partij.
                    'wie_naam' => 'ontvanger',
                    'actie'    => $heeft_type ? "COALESCE(NULLIF(meldingstype, ''), 'bericht')" : "'bericht'",
                    // Een bericht hangt aan een sollicitatie; zonder koppeling geen objecttype.
                    'objecttype' => "IF(sollicitatie_id IS NULL, NULL, 'sollicitatie')",
                    'object_id'  => 'sollicitatie_id',
                    // BEWUST zonder body: dat is de berichtinhoud zelf en hoort niet in een overzicht.
                    'details'    => "CONCAT_WS(' \u{00b7} ', onderwerp, "
                                  . ($heeft_kanaal ? 'kanaal' : "''") . ', status'
                                  . ($heeft_fout ? ', foutmelding' : '') . ')',
                    'ip'         => 'NULL',
                    'ernst'      => "CASE status WHEN 'mislukt' THEN 'fout' WHEN 'overgeslagen' THEN 'waarschuwing' ELSE 'info' END",
                ];
            },
        ],

        'ondertekening' => [
            'label'  => 'Ondertekenen',
            'uitleg' => 'Het bewijsspoor per ondertekening: geopend, code verzonden, ondertekend, verzegeld.',
            'icoon'  => 'document',
            'tabel'  => 'ondertekening_gebeurtenissen',
            'velden' => function (string $t): array {
                return [
                    'moment'     => 'tijdstip',
                    'wie_type'   => "'systeem'",
                    'wie_id'     => 'NULL',
                    'wie_naam'   => 'NULL',
                    'actie'      => 'gebeurtenis',
                    'objecttype' => "'ondertekening'",
                    'object_id'  => 'ondertekening_id',
                    'details'    => 'details',
                    'ip'         => 'ip',
                    'ernst'      => "IF(gebeurtenis = 'code_fout', 'waarschuwing', 'info')",
                ];
            },
        ],

        'opschoning' => [
            'label'  => 'AVG-opschoning',
            'uitleg' => 'Wat de bewaartermijnen hebben opgeruimd of vernietigd.',
            'icoon'  => 'slot',
            'tabel'  => 'opschoon_log',
            'velden' => function (string $t): array {
                return [
                    'moment'     => 'uitgevoerd_op',
                    'wie_type'   => "'systeem'",
                    'wie_id'     => 'NULL',
                    'wie_naam'   => 'NULL',
                    'actie'      => 'categorie',
                    'objecttype' => 'NULL',
                    'object_id'  => 'NULL',
                    'details'    => "CONCAT_WS(' \u{00b7} ', CONCAT(aantal, ' stuks'), CONCAT('via ', bron))",
                    'ip'         => 'NULL',
                    'ernst'      => "'info'",
                ];
            },
        ],

        'login' => [
            'label'  => 'Mislukte logins',
            'uitleg' => 'Inlogpogingen die niet slaagden. Een reeks op hetzelfde adres wijst op een aanval.',
            'icoon'  => 'sleutel',
            'tabel'  => 'login_pogingen',
            'velden' => function (string $t): array {
                return [
                    'moment'     => 'moment',
                    'wie_type'   => "'onbekend'",
                    'wie_id'     => 'NULL',
                    'wie_naam'   => 'email',
                    'actie'      => "'login_mislukt'",
                    'objecttype' => 'NULL',
                    'object_id'  => 'NULL',
                    'details'    => 'NULL',
                    'ip'         => 'ip',
                    'ernst'      => "'waarschuwing'",
                ];
            },
        ],
    ];
}

/**
 * De bronnen die nu echt bruikbaar zijn: tabel bestaat en heeft kolommen.
 * @param string $alleen  beperk tot deze ene bronsleutel ('' = alle)
 */
function log_bronnen_beschikbaar(string $alleen = ''): array {
    $uit = [];
    foreach (log_bronnen() as $sleutel => $bron) {
        if ($alleen !== '' && $sleutel !== $alleen) continue;
        if (!log_tabel_kolommen($bron['tabel'])) continue;  // tabel bestaat niet → overslaan
        $uit[$sleutel] = $bron;
    }
    return $uit;
}

/** De veld-expressies van één bron, met de closure uitgevoerd. */
function log_bron_velden(array $bron): array {
    $f = $bron['velden'];
    return $f($bron['tabel']);
}

}

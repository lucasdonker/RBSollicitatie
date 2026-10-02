<?php
/**
 * Paal 13 — Productiekopie op test anonimiseren ("generale repetitie").
 *
 * Werkwijze (runbook, sectie "Generale repetitie op test"): de productiedatabase wordt op de
 * TESTomgeving geïmporteerd, en direct daarna draait dit script één keer. Het vervangt alle
 * persoonsgegevens en geheimen door nepwaarden die de vorm behouden, zodat schermen, validatie en
 * aantallen realistisch blijven zonder echte mensen. Daarna gaat de nieuwe versie op test.
 *
 * Gebruik (alleen via de opdrachtregel, op de testserver):
 *   php tools/anonimiseer_testkopie.php --ja [--email=jij@domein.nl] [--behoud-beheerder=a@x.nl,b@x.nl]
 *
 *   --ja                  verplicht: bevestigt dat deze database overschreven mag worden.
 *   --email=basis@domein  nep-adressen worden plus-adressen hierop (basis+p123@domein), zodat alle
 *                         test-mail bij jou binnenkomt en je als sollicitant kunt inloggen. Zonder deze
 *                         optie: test+p123@example.invalid (onbestelbaar).
 *   --behoud-beheerder=…  beheerders (e-mail, kommagescheiden) die naam, e-mail en Authentik-koppeling
 *                         houden, zodat je op test kunt inloggen. Wachtwoord en tweestaps gaan ook bij hen weg.
 *   --help                deze uitleg.
 *
 * Weigert: buiten de CLI, als is_test_omgeving() niet waar is, als APP_URL de productiehost is,
 * als MAIL_OMLEIDEN leeg is, en zonder --ja. Alles gebeurt in één transactie (alleen UPDATE/DELETE,
 * geen DDL); bij een fout wordt alles teruggedraaid. Twee keer draaien kan geen kwaad: de
 * instelling `anon.testkopie_op` onthoudt dat de geboortedatums al verschoven zijn.
 *
 * Bewust NIET: de kluis repareren. DOCUMENTEN_MAP en KLUIS_SLEUTEL van productie komen niet mee naar
 * test, dus documenten, foto's en verzegelde PDF's van deze records zijn op test onleesbaar. Het
 * script meldt dat alleen. db.failsafe_versie blijft staan: de nieuwe versie draait dan zijn eigen
 * schema-updates, en dat is precies wat je wilt testen. Het script opent daarom een eigen
 * databaseverbinding in plaats van db() (die zou db_failsafe() starten).
 *
 * Nieuwe tabel of kolom met persoonsgegevens? Zet hem in anon_regels(). De eindscan waarschuwt
 * voor tabellen die er niet in staan en voor tekstkolommen buiten de regels met iets dat op een
 * e-mailadres, telefoonnummer of IBAN lijkt.
 * Test: docs/ai/harnas/anonimiseer_test.php (SQLite; draai met -d extension=pdo_sqlite).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

/**
 * Wat er per tabel en kolom gebeurt. Dit is het overzicht; de regels staan hieronder uitgelegd.
 *
 * Tabel:   '*' => 'wis'     alle rijen weg (wachtrijen, tokens, sessies)
 *          '*' => 'bewaar'  niets aanpassen (geen persoonsgegevens); de eindscan kijkt wel mee
 * Kolom (lege en NULL-waarden blijven altijd leeg, zodat "is ingevuld" klopt):
 *   null            NULL                               tekst         '[geanonimiseerd]'
 *   voornaam        'Test'                             achternaam    'Persoon<id>'
 *   naam:X          'Test X<id>' (standaard Persoon)   naammap       naam van een beheerder/persoon →
 *                                                                   'Beheerder <id>' / 'Test Persoon<id>' /
 *                                                                   'Persoon <6 tekens>', overal gelijk
 *   email:code      basis+<code><id>@domein of test+<code><id>@example.invalid
 *   telefoon        0612345<3 cijfers van id>          ontvanger:code e-mail of telefoon, zelfde nepwaarde
 *                                                                   als bij de persoon als die bekend is
 *   datum_schuif    ± 180 dagen (per id vast), één keer   token        nieuw willekeurig, zelfde lengte
 *   adres/straat/huisnummer/postcode/woonplaats/plaats   vaste fictieve waarden
 *   ip              '0.0.0.0'                          json_tekst    JSON: structuur blijft, tekst die naar
 *                                                                   een persoon kan wijzen wordt vervangen
 *   url_pad         alleen het pad (querystring weg)   bestandsnaam  'document-<id>.<ext>'
 *   gebruikersnaam  'test<id>'                         overslaan     status wacht/bezig → 'overgeslagen'
 *   bhd_naam / bhd_email:code / bhd_null   als naam/email/null, behalve bij --behoud-beheerder
 *   bewaar          blijft staan (bewust; eindscan kijkt mee)
 *   eigen_stap      apart geregeld (instellingen: zie anon_instellingen())
 *
 * Volgorde telt: personen en beheerders eerst, zodat berichtenlog en ondertekeningen dezelfde
 * nepadressen krijgen.
 */
function anon_regels(): array {
    return [
        // ── Personen en beheerders eerst (vullen de adres- en naamkaart) ──
        'personen' => [
            'voornaam' => 'voornaam', 'tussenvoegsel' => 'null', 'achternaam' => 'achternaam',
            'email' => 'email:p', 'telefoon' => 'telefoon', 'geboortedatum' => 'datum_schuif',
            'adres' => 'adres', 'straat' => 'straat', 'huisnummer' => 'huisnummer', 'postcode' => 'postcode',
            'woonplaats' => 'woonplaats',
            'ouder_naam' => 'naam:Ouder', 'ouder_email' => 'email:o', 'ouder_telefoon' => 'telefoon',
            'afmeld_token' => 'null',
            'geslacht' => 'bewaar', 'seintje_bron' => 'bewaar', 'kanaal_bron' => 'bewaar',
        ],
        'beheerders' => [
            'naam' => 'bhd_naam', 'email' => 'bhd_email:b', 'authentik_uid' => 'bhd_null',
            'wachtwoord' => 'null', 'tweestaps_geheim' => 'null', 'tweestaps_herstel' => 'null',
            'tweestaps_aan_op' => 'null', 'tweestaps_stap' => 'null',
        ],
        'werving_seintje' => ['voornaam' => 'voornaam', 'email' => 'email:ws', 'token_hash' => 'null', 'bron' => 'bewaar'],

        // ── Sollicitatie en kennismaking ──
        'sollicitaties' => [
            'notities' => 'tekst', 'portaal_token' => 'token', 'privacy_ip' => 'ip',
            'ouder_toestemming_naam' => 'naam:Ouder', 'bron_label' => 'bewaar',
        ],
        'blk_formulieren' => [
            'adres' => 'adres', 'postcode' => 'postcode', 'huisnummer' => 'huisnummer', 'straat' => 'straat',
            'woonplaats' => 'woonplaats',
            'opleiding' => 'tekst', 'werkervaring' => 'tekst', 'diplomas' => 'tekst',
            'motivatie_lifeguard' => 'tekst', 'motivatie_strand' => 'tekst', 'strand_motivatie' => 'tekst',
            'beschikbaarheid_intentie' => 'tekst', 'beschikbaarheid_uitzonderingen' => 'tekst',
            'bijzonderheden' => 'tekst', 'ai_analyse' => 'tekst',
            'social_media' => 'null', 'foto_url' => 'null',
        ],
        'blk_wijzigingen'   => ['reden' => 'tekst', 'wijzigingen_json' => 'null'],
        'sollicitant_notities' => ['tekst' => 'tekst'],
        'sollicitant_taken' => ['*' => 'bewaar'],
        'intrek_verzoek'    => ['reden' => 'tekst', 'notitie' => 'tekst', 'besloten_door' => 'naammap'],
        'authentik_koppeling' => ['authentik_username' => 'gebruikersnaam', 'authentik_pk' => 'null', 'sync_melding' => 'tekst'],
        'competenties'      => ['*' => 'bewaar'],

        // ── Ondertekeningen en documenten (kluisinhoud leeg; bestanden staan niet op test) ──
        'ondertekeningen' => [
            'token' => 'token', 'ondertekenaar_naam' => 'naam:Ondertekenaar', 'ondertekenaar_email' => 'email:ond',
            'ondertekenaar_geboortedatum' => 'datum_schuif', 'ondertekenaar_geboorteplaats' => 'plaats',
            'plaats' => 'plaats', 'formulier_data' => 'null', 'handtekening_beeld' => 'null',
            'vertrouwelijk' => 'null', 'ondertekend_ip' => 'ip', 'ondertekend_user_agent' => 'null',
            'zekerheid_naar' => 'ontvanger:ond', 'ondertekenaar_relatie' => 'bewaar',
        ],
        'ondertekening_gebeurtenissen' => ['ip' => 'ip', 'browser' => 'null', 'details' => 'json_tekst'],
        'sollicitant_documenten' => ['bestandsnaam' => 'bestandsnaam', 'geupload_ip' => 'ip', 'opslagnaam' => 'bewaar'],

        // ── Beschikbaarheid, loon, AI ──
        'beschikbaarheid'           => ['uitzonderingen' => 'tekst'],
        'beschikbaarheid_profiel'   => ['vaste_toelichting' => 'tekst', 'opmerking' => 'tekst', 'norm_notitie' => 'tekst', 'concept_json' => 'null'],
        'beschikbaarheid_blok'      => ['*' => 'bewaar'],
        'beschikbaarheid_afwezig'   => ['label' => 'tekst'],
        'beschikbaarheid_verzoek'   => ['toelichting' => 'tekst'],
        'beschikbaarheid_correctie' => ['reden' => 'tekst', 'door' => 'naammap'],
        'beschikbaarheid_dag'       => ['reden' => 'tekst'],
        'ai_beschikbaarheid_log'    => ['invoer' => 'tekst', 'resultaat_json' => 'null'],
        'ai_queue'                  => ['*' => 'wis'],
        'loon_planning'             => ['notitie' => 'tekst'],
        'loon_scenario'             => ['notitie' => 'tekst', 'naam' => 'bewaar'],
        'loon_scenario_persoon'     => ['*' => 'bewaar'],
        'loon_vergrendeling'        => ['*' => 'bewaar'],
        'loon_extra_persoon'        => ['naam' => 'naam:Extra', 'geboortedatum' => 'datum_schuif', 'notitie' => 'tekst', 'functie_sleutel' => 'bewaar'],
        'loon_extra_planning'       => ['*' => 'bewaar'],
        'inzet_afspraak'            => ['notitie' => 'tekst'],
        'inzet_afspraak_week'       => ['*' => 'bewaar'],

        // ── Selectiedag en trainingsweekend ──
        'beoordelingen'       => ['aantekeningen' => 'tekst', 'beoordelaar' => 'naammap'],
        'selectie_indeling'   => ['bevestiging_reden' => 'tekst', 'afgemeld_reden' => 'tekst'],
        'selectie_score'      => ['beoordelaar' => 'naammap'],
        'selectie_observatie' => ['tekst' => 'tekst', 'beoordelaar' => 'naammap'],
        'selectie_besluit'    => ['notitie' => 'tekst', 'gebeld_notitie' => 'tekst', 'door' => 'naammap', 'gebeld_door' => 'naammap'],
        'selectie_relatie'    => ['*' => 'bewaar'],
        'selectie_groep'      => ['*' => 'bewaar'],
        'tw_deelname' => [
            'afgemeld_reden' => 'tekst', 'besluit_notitie' => 'tekst', 'bevestiging_reden' => 'tekst',
            'gesprek_bericht' => 'tekst', 'evaluatie_bericht' => 'tekst',
            'besluit_door' => 'naammap', 'evaluatie_door' => 'naammap',
        ],
        'tw_observatie'       => ['tekst' => 'tekst', 'beoordelaar' => 'naammap'],
        'tw_groep'            => ['*' => 'bewaar'],
        'lifeguard_feedback'  => ['van_naam' => 'naammap', 'tekst' => 'tekst', 'vastgelegd_door_naam' => 'naammap'],

        // ── Berichten: wachtrij leeg, log zonder inhoud ──
        'mail_queue' => ['*' => 'wis'],
        'mail_log'   => [
            'ontvanger' => 'ontvanger:ml', 'onderwerp' => 'tekst', 'samenvatting' => 'null', 'body' => 'null',
            'foutmelding' => 'tekst', 'status' => 'overslaan',
        ],
        'mail_templates'     => ['*' => 'bewaar'],
        'bijlagen'           => ['*' => 'bewaar'],
        'geplande_berichten' => ['*' => 'bewaar'],

        // ── Inloggen, sessies, tellers ──
        'otp_tokens'        => ['*' => 'wis'],
        'portaal_apparaten' => ['*' => 'wis'],
        'login_pogingen'    => ['*' => 'wis'],
        'api_rate'          => ['*' => 'wis'],
        'live_versie'       => ['*' => 'wis'],   // tellers voor live bijwerken, met de naam van wie iets wijzigde

        // ── Logboek ──
        'audit_log' => ['wie_naam' => 'naammap', 'details' => 'json_tekst', 'ip' => 'ip', 'browser' => 'null'],
        'error_log' => ['bericht' => 'tekst', 'gebruiker' => 'null', 'url' => 'url_pad'],
        'opschoon_log' => ['*' => 'bewaar'],

        // ── Wervingspagina's ──
        'paginas'        => ['*' => 'bewaar'],
        'pagina_versies' => ['*' => 'bewaar'],
        'pagina_events'  => ['bezoeker_hash' => 'null'],

        // ── Configuratie zonder persoonsgegevens ──
        'instellingen'   => ['waarde' => 'eigen_stap'],
        'seizoenen'      => ['*' => 'bewaar'],
        'rollen'         => ['*' => 'bewaar'],
        'rol_rechten'    => ['*' => 'bewaar'],
        'rol_taak'       => ['*' => 'bewaar'],   // taakrechten (2.141.0): geen persoonsgegevens
        'functies'       => ['*' => 'bewaar'],
        'loon_tarieven'  => ['*' => 'bewaar'],
        'loon_jaar'      => ['*' => 'bewaar'],
        'migraties'      => ['*' => 'bewaar'],
    ];
}

/** Regels per soort: per rij (PHP), per waarde (naamkaart) of in één UPDATE (vaste waarde). */
function anon_regelsoort(string $naam): ?string {
    $soorten = [
        'rij'    => ['voornaam', 'achternaam', 'naam', 'email', 'telefoon', 'datum_schuif', 'token', 'ontvanger',
                     'json_tekst', 'url_pad', 'bestandsnaam', 'gebruikersnaam', 'bhd_naam', 'bhd_email', 'bhd_null'],
        'waarde' => ['naammap'],
        'vast'   => ['null', 'tekst', 'ip', 'adres', 'straat', 'huisnummer', 'postcode', 'woonplaats', 'plaats', 'overslaan'],
        'niets'  => ['bewaar', 'eigen_stap'],
    ];
    foreach ($soorten as $soort => $namen) if (in_array($naam, $namen, true)) return $soort;
    return null;
}

/** De vaste nepwaarden. */
function anon_vast(string $naam): ?string {
    $w = ['tekst' => '[geanonimiseerd]', 'ip' => '0.0.0.0', 'straat' => 'Teststraat', 'huisnummer' => '1',
          'postcode' => '1234AB', 'woonplaats' => 'Testdorp', 'plaats' => 'Testdorp',
          'adres' => 'Teststraat 1, 1234AB Testdorp'];
    return $w[$naam] ?? null;
}

/** Controleert vooraf dat elke regel bestaat (een tikfout mag pas niets kapotmaken als hij er niet is). */
function anon_regels_controleren(array $regels): array {
    $fouten = [];
    foreach ($regels as $t => $kolommen) {
        foreach ($kolommen as $k => $r) {
            if ($k === '*') { if (!in_array($r, ['wis', 'bewaar'], true)) $fouten[] = "$t.*: $r"; continue; }
            $d = explode(':', (string)$r);
            if (anon_regelsoort($d[0]) === null) $fouten[] = "$t.$k: $r";
        }
    }
    return $fouten;
}

// ─────────────────────────────────────────────────────────────
//  Opties en weigeren
// ─────────────────────────────────────────────────────────────

function anon_gebruik(): string {
    return "Gebruik: php tools/anonimiseer_testkopie.php --ja [--email=jij@domein.nl] [--behoud-beheerder=a@x.nl,b@x.nl]\n"
         . "Alleen op de testomgeving, direct na het importeren van een productiekopie. Zie het runbook.";
}

function anon_opties(array $args): array {
    $o = ['ja' => false, 'email' => null, 'behoud' => [], 'help' => false, 'fout' => null];
    foreach ($args as $a) {
        $a = (string)$a;
        if ($a === '--ja') { $o['ja'] = true; continue; }
        if ($a === '--help' || $a === '-h') { $o['help'] = true; continue; }
        if (str_starts_with($a, '--email=')) {
            $e = trim(substr($a, 8));
            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) { $o['fout'] = "--email is geen geldig e-mailadres: $e"; continue; }
            $o['email'] = strtolower($e);
            continue;
        }
        if (str_starts_with($a, '--behoud-beheerder=')) {
            foreach (explode(',', substr($a, 19)) as $e) {
                $e = strtolower(trim($e));
                if ($e !== '') $o['behoud'][] = $e;
            }
            continue;
        }
        $o['fout'] = "onbekende optie: $a";
    }
    return $o;
}

/**
 * Waarom het script niet mag draaien, of null als het mag. Volgorde: omgeving eerst.
 * $is_test komt van is_test_omgeving(); de productiehost is een tweede, onafhankelijk slot.
 */
function anon_weigering(bool $is_test, string $app_url, string $mail_omleiden, array $opties): ?string {
    $host = strtolower((string)(parse_url($app_url, PHP_URL_HOST) ?: ''));
    if (!$is_test) return "dit is geen testomgeving (APP_URL = $app_url). Dit script draait alleen op test.";
    if (in_array($host, ['sollicitatie.paal13.com', 'www.sollicitatie.paal13.com'], true)) {
        return "APP_URL wijst naar de productiehost ($host).";
    }
    if (trim($mail_omleiden) === '') {
        return 'MAIL_OMLEIDEN is leeg in includes/config.php. Zet eerst de testmodus aan (runbook), dan pas een productiekopie op test.';
    }
    if (!$opties['ja']) {
        return 'geef --ja mee. Dit overschrijft alle persoonsgegevens in deze database, onherroepelijk.';
    }
    return null;
}

function anon_zeg(string $tekst = ''): void { echo $tekst, "\n"; }

function anon_pdo(): PDO {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

// ─────────────────────────────────────────────────────────────
//  Schema en scan (MariaDB). Het harnas definieert anon_schema zelf voor SQLite.
// ─────────────────────────────────────────────────────────────

if (!function_exists('anon_schema')) {
/**
 * Alle tabellen en kolommen van deze database: [tabel => [kolom => ['type' => ..., 'auto' => bool]]].
 * 'auto' = ON UPDATE CURRENT_TIMESTAMP: die kolom zetten we bij elke UPDATE op zichzelf, zodat
 * "gewijzigd op" niet voor iedereen op vandaag springt.
 */
function anon_schema(PDO $db): array {
    $q = $db->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, EXTRA FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION");
    $uit = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $uit[$r['TABLE_NAME']][$r['COLUMN_NAME']] = [
            'type' => strtolower((string)$r['DATA_TYPE']),
            'auto' => stripos((string)$r['EXTRA'], 'on update') !== false,
        ];
    }
    return $uit;
}
}

/** Patronen voor de eindscan. Gelden zowel in MariaDB (REGEXP, PCRE) als in PHP (preg). */
function anon_patronen(): array {
    return [
        'e-mail'   => '[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}',
        'telefoon' => '(\+31|0031|(^|[^0-9])0)[ -]?[1-9]([ -]?[0-9]){8}([^0-9]|$)',
        'IBAN'     => '[A-Z]{2}[0-9]{2}[A-Z]{4}[0-9]{10}',
    ];
}

/** Is dit e-mailadres onschuldig (van de vereniging, een nepadres, of een plus-adres op --email)? */
function anon_email_toegestaan(string $adres, array $ctx): bool {
    $adres = strtolower($adres);
    $d = explode('@', $adres);
    $domein = $d[1] ?? '';
    if (in_array($domein, ['paal13.com', 'example.invalid', 'example.com', 'example.org', 'example.net', 'anoniem.local'], true)) return true;
    if (str_ends_with($domein, '.invalid')) return true;
    $basis = $ctx['opties']['email'] ?? null;
    if ($basis) {
        $b = explode('@', $basis);
        if ($domein === $b[1] && ($d[0] === $b[0] || str_starts_with($d[0], $b[0] . '+'))) return true;
    }
    return false;
}

/** Telt de rijen van één kolom met iets dat op een e-mailadres, telefoonnummer of IBAN lijkt (zonder onze eigen nepwaarden). */
function anon_scan_kolom(PDO $db, string $t, string $k, array $ctx): array {
    $uit = [];
    foreach (anon_patronen() as $soort => $patroon) {
        $q = $db->prepare("SELECT `$k` AS w FROM `$t` WHERE `$k` REGEXP ? LIMIT 10000");
        $q->execute([$patroon]);
        $n = 0;
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $w) {
            if (!preg_match_all('~' . $patroon . '~', (string)$w, $m)) continue;
            foreach ($m[0] as $treffer) {
                if ($soort === 'e-mail' && anon_email_toegestaan($treffer, $ctx)) continue;
                if ($soort === 'telefoon' && preg_match('/^0612345\d{3}$/', anon_tel_norm($treffer))) continue;
                $n++;
                break;
            }
        }
        if ($n > 0) $uit[$soort] = $n;
    }
    return $uit;
}

/**
 * De eindscan: elke tekstkolom die geen anonimiseerregel heeft (ook 'bewaar' en tabellen die niet
 * in de regels staan). Geeft ['kolommen' => ["t.k" => [soort => n]], 'onbekend' => [tabellen]].
 */
function anon_scan(PDO $db, array $schema, array $regels, array $ctx): array {
    $tekst = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'json',
              'tinyblob', 'blob', 'mediumblob', 'longblob'];
    $uit = ['kolommen' => [], 'onbekend' => []];
    foreach ($schema as $t => $kolommen) {
        $r = $regels[$t] ?? null;
        if ($r === null) $uit['onbekend'][] = $t;
        if (($r['*'] ?? '') === 'wis') continue;
        foreach ($kolommen as $k => $info) {
            if (!in_array($info['type'], $tekst, true)) continue;
            if (isset($r[$k]) && $r[$k] !== 'bewaar') continue;
            $n = anon_scan_kolom($db, $t, $k, $ctx);
            if ($n) $uit['kolommen']["$t.$k"] = $n;
        }
    }
    return $uit;
}

// ─────────────────────────────────────────────────────────────
//  Nepwaarden
// ─────────────────────────────────────────────────────────────

function anon_email(string $code, $id, array $ctx): string {
    $basis = $ctx['opties']['email'] ?? null;
    if ($basis) {
        $b = explode('@', $basis);
        return $b[0] . '+' . $code . $id . '@' . $b[1];
    }
    return 'test+' . $code . $id . '@example.invalid';
}

/** Telefoonnummer terug naar 06xxxxxxxx-vorm, voor de kaart oud → nieuw. */
function anon_tel_norm(string $tel): string {
    $c = preg_replace('/\D/', '', $tel);
    if (str_starts_with($c, '0031')) $c = '0' . substr($c, 4);
    elseif (str_starts_with($c, '31') && strlen($c) === 11) $c = '0' . substr($c, 2);
    return $c;
}

function anon_tel($id): string {
    $n = is_numeric($id) ? (int)$id : crc32((string)$id);
    return '0612345' . sprintf('%03d', abs($n) % 1000);
}

/** Naam van een beheerder, persoon of beoordelaar: overal dezelfde vervanging. */
function anon_naam_map(string $w, array &$ctx): string {
    if (isset($ctx['naam_map'][$w])) return $ctx['naam_map'][$w];
    if (preg_match('/^(Beheerder \d+|Persoon [0-9a-f]{6}|Test [A-Za-z]+\d+)$/', $w)) return $w;
    if (in_array(strtolower(trim($w)), ['systeem', 'bestuur', 'cron', 'onbekend', 'sollicitant', 'portaal', 'bellijst', 'beheer'], true)) return $w;
    return $ctx['naam_map'][$w] = 'Persoon ' . substr(hash_hmac('sha256', mb_strtolower(trim($w)), $ctx['zout']), 0, 6);
}

/** Onderdelen van een JSON-sleutel die op persoonlijke inhoud wijzen. */
function anon_json_gevoelig(string $sleutel): bool {
    $delen = preg_split('/[^a-z]+/', strtolower($sleutel)) ?: [];
    $gevoelig = ['naam', 'voornaam', 'achternaam', 'email', 'mail', 'telefoon', 'tel', 'gsm', 'mobiel', 'adres',
                 'straat', 'postcode', 'woonplaats', 'ip', 'browser', 'agent', 'reden', 'tekst', 'notitie',
                 'bericht', 'ontvanger', 'naar', 'body', 'onderwerp', 'opmerking', 'toelichting', 'iban', 'bsn',
                 'geboortedatum', 'oud', 'nieuw', 'waarde', 'wie', 'van'];
    return (bool)array_intersect($delen, $gevoelig);
}

function anon_json_waarde($v, string $sleutel) {
    if (is_array($v)) {
        $uit = [];
        foreach ($v as $k => $w) $uit[$k] = anon_json_waarde($w, is_string($k) ? $k : $sleutel);
        return $uit;
    }
    if ($v === null || is_bool($v)) return $v;
    if (anon_json_gevoelig($sleutel)) return '[geanonimiseerd]';
    if (is_string($v) && !preg_match('/^[a-z][a-z0-9_]{0,39}$/', $v)) return '[geanonimiseerd]';
    return $v;
}

function anon_json_tekst(string $w): string {
    $d = json_decode($w, true);
    if (!is_array($d)) return '[geanonimiseerd]';
    return (string)json_encode(anon_json_waarde($d, ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Nieuwe waarde voor één cel van een per-rij-regel. Lege waarden blijven leeg. */
function anon_rij_waarde(string $regel, $w, array $rij, string $t, array &$ctx) {
    $d = explode(':', $regel);
    $naam = $d[0];
    $param = $d[1] ?? '';
    $id = $rij['anon_k'];
    $behouden = in_array(strtolower((string)($rij['email'] ?? '')), $ctx['behoud'], true);
    if ($naam === 'bhd_null') return $behouden ? $w : null;
    if ($w === null || $w === '') return $w;
    $w = (string)$w;

    switch ($naam) {
        case 'voornaam':   return 'Test';
        case 'achternaam': return 'Persoon' . $id;
        case 'naam':       return 'Test ' . ($param !== '' ? $param : 'Persoon') . $id;
        case 'bhd_naam':   return $behouden ? $w : 'Beheerder ' . $id;
        case 'bhd_email':
            if ($behouden) return $w;
            return anon_email_nieuw($w, $param, $id, $ctx);
        case 'email':      return anon_email_nieuw($w, $param, $id, $ctx);
        case 'telefoon':   return anon_tel_nieuw($w, $id, $ctx);
        case 'ontvanger':
            // Eerst de kaart (bekende persoon: dezelfde nepwaarde). Daarna: al een nepwaarde of een adres
            // van de vereniging laten staan, zodat een tweede run niets verandert.
            if (str_contains($w, '@')) {
                $sleutel = strtolower(trim($w));
                if (isset($ctx['email_map'][$sleutel])) return $ctx['email_map'][$sleutel];
                return anon_email_toegestaan($w, $ctx) ? $w : anon_email($param, $id, $ctx);
            }
            if (preg_match('/\d{6}/', preg_replace('/\D/', '', $w))) {
                $sleutel = anon_tel_norm($w);
                if (isset($ctx['tel_map'][$sleutel])) return $ctx['tel_map'][$sleutel];
                return preg_match('/^0612345\d{3}$/', $sleutel) ? $w : anon_tel($id);
            }
            return $w;
        case 'datum_schuif':
            if (!$ctx['schuif']) return $w;
            $dag = (hexdec(substr(hash_hmac('sha256', $t . '|' . $id, $ctx['zout']), 0, 6)) % 361) - 180;
            try { $dt = new DateTime($w); } catch (\Exception $e) { return $w; }
            $dt->modify(($dag >= 0 ? '+' : '') . $dag . ' days');
            return $dt->format(strlen($w) > 10 ? 'Y-m-d H:i:s' : 'Y-m-d');
        case 'token':
            $len = max(16, strlen($w));
            return substr(bin2hex(random_bytes(intdiv($len + 1, 2))), 0, $len);
        case 'json_tekst':     return anon_json_tekst($w);
        case 'url_pad':        $p = parse_url($w, PHP_URL_PATH); return is_string($p) ? $p : '';
        case 'bestandsnaam':
            $ext = strtolower((string)pathinfo($w, PATHINFO_EXTENSION));
            return 'document-' . $id . (preg_match('/^[a-z0-9]{1,5}$/', $ext) ? '.' . $ext : '');
        case 'gebruikersnaam': return 'test' . $id;
    }
    throw new RuntimeException("onbekende regel $regel");
}

function anon_email_nieuw(string $oud, string $code, $id, array &$ctx): string {
    $nieuw = anon_email($code, $id, $ctx);
    $sleutel = strtolower(trim($oud));
    if (!isset($ctx['email_map'][$sleutel])) $ctx['email_map'][$sleutel] = $nieuw;
    return $nieuw;
}

function anon_tel_nieuw(string $oud, $id, array &$ctx): string {
    $nieuw = anon_tel($id);
    $sleutel = anon_tel_norm($oud);
    if ($sleutel !== '' && !isset($ctx['tel_map'][$sleutel])) $ctx['tel_map'][$sleutel] = $nieuw;
    return $nieuw;
}

// ─────────────────────────────────────────────────────────────
//  Uitvoeren
// ─────────────────────────────────────────────────────────────

/** Kaarten en zout voor deze run. Namen worden vóór het wijzigen gelezen. */
function anon_context(PDO $db, array $schema, array $opties, bool $schuif): array {
    $ctx = ['opties' => $opties, 'zout' => bin2hex(random_bytes(16)), 'schuif' => $schuif,
            'email_map' => [], 'tel_map' => [], 'naam_map' => [],
            'behoud' => $opties['behoud'], 'behoud_onbekend' => []];
    if (isset($schema['personen']['voornaam'], $schema['personen']['achternaam'])) {
        $tv = isset($schema['personen']['tussenvoegsel']) ? ', tussenvoegsel' : ", '' AS tussenvoegsel";
        foreach ($db->query("SELECT id, voornaam, achternaam $tv FROM personen")->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $vol = trim(preg_replace('/\s+/', ' ', $p['voornaam'] . ' ' . ($p['tussenvoegsel'] ?? '') . ' ' . $p['achternaam']));
            if ($vol !== '') $ctx['naam_map'][$vol] = 'Test Persoon' . $p['id'];
        }
    }
    if (isset($schema['beheerders']['naam'], $schema['beheerders']['email'])) {
        $gevonden = [];
        foreach ($db->query("SELECT id, naam, email FROM beheerders")->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $mail = strtolower((string)$b['email']);
            $houd = in_array($mail, $ctx['behoud'], true);
            if ($houd) $gevonden[] = $mail;
            if ((string)$b['naam'] !== '') $ctx['naam_map'][(string)$b['naam']] = $houd ? (string)$b['naam'] : 'Beheerder ' . $b['id'];
        }
        $ctx['behoud_onbekend'] = array_values(array_diff($ctx['behoud'], $gevonden));
    }
    return $ctx;
}

function anon_sleutelkolom(array $kolommen): ?string {
    foreach (['id', 'sollicitatie_id', 'sleutel'] as $k) if (isset($kolommen[$k])) return $k;
    return null;
}

function anon_tel_rijen(PDO $db, string $t, string $waar = ''): int {
    return (int)$db->query("SELECT COUNT(*) FROM `$t`" . ($waar !== '' ? " WHERE $waar" : ''))->fetchColumn();
}

/**
 * Eén tabel volgens de regels. Geeft ['status' => ontbreekt|wis|bewaar|bewerkt, 'rijen' => n,
 * 'mist' => [kolommen die in de regels staan maar hier niet bestaan]].
 */
function anon_tabel(PDO $db, string $t, array $regels, array $schema, array &$ctx): array {
    if (!isset($schema[$t])) return ['status' => 'ontbreekt', 'rijen' => 0, 'mist' => []];
    if (isset($regels['*'])) {
        if ($regels['*'] === 'bewaar') return ['status' => 'bewaar', 'rijen' => 0, 'mist' => []];
        $n = anon_tel_rijen($db, $t);
        $db->exec("DELETE FROM `$t`");
        return ['status' => 'wis', 'rijen' => $n, 'mist' => []];
    }

    $kolommen = $schema[$t];
    $mist = [];
    $per = ['rij' => [], 'waarde' => [], 'vast' => []];
    foreach ($regels as $k => $r) {
        $d = explode(':', $r);
        $soort = anon_regelsoort($d[0]);
        if (!isset($per[$soort])) continue;          // bewaar / eigen_stap
        if (!isset($kolommen[$k])) { $mist[] = $k; continue; }
        $per[$soort][$k] = $r;
    }
    if (!$per['rij'] && !$per['waarde'] && !$per['vast'] && !$mist) return ['status' => 'bewaar', 'rijen' => 0, 'mist' => []];
    // Kolommen met ON UPDATE CURRENT_TIMESTAMP die we niet zelf zetten: laten staan.
    $auto = '';
    foreach ($kolommen as $k => $info) {
        if (!empty($info['auto']) && !isset($regels[$k])) $auto .= ", `$k` = `$k`";
    }

    $tellen = [];
    foreach (array_merge($per['rij'], $per['waarde'], $per['vast']) as $k => $r) if ($r !== 'overslaan') $tellen[] = "`$k` IS NOT NULL";
    $n = $tellen ? anon_tel_rijen($db, $t, implode(' OR ', $tellen)) : 0;

    // 1. Per rij (in PHP), in porties op de sleutel.
    if ($per['rij']) {
        $sleutel = anon_sleutelkolom($kolommen);
        if ($sleutel === null) throw new RuntimeException("$t heeft geen id-, sollicitatie_id- of sleutelkolom");
        $extra = isset($kolommen['email']) && !isset($per['rij']['email']) ? ', `email`' : '';
        $lijst = '`' . implode('`, `', array_keys($per['rij'])) . '`' . $extra;
        $laatste = null;
        do {
            if ($laatste === null) {
                $q = $db->prepare("SELECT `$sleutel` AS anon_k, $lijst FROM `$t` ORDER BY `$sleutel` LIMIT 500");
                $q->execute();
            } else {
                $q = $db->prepare("SELECT `$sleutel` AS anon_k, $lijst FROM `$t` WHERE `$sleutel` > ? ORDER BY `$sleutel` LIMIT 500");
                $q->execute([$laatste]);
            }
            $rijen = $q->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rijen as $rij) {
                $laatste = $rij['anon_k'];
                $nieuw = [];
                foreach ($per['rij'] as $k => $r) {
                    $w = anon_rij_waarde($r, $rij[$k], $rij, $t, $ctx);
                    if ($w !== $rij[$k] && !($w !== null && $rij[$k] !== null && (string)$w === (string)$rij[$k])) $nieuw[$k] = $w;
                }
                if (!$nieuw) continue;
                $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($nieuw))) . $auto;
                $params = array_values($nieuw);
                $params[] = $rij['anon_k'];
                $db->prepare("UPDATE `$t` SET $set WHERE `$sleutel` = ?")->execute($params);
            }
        } while (count($rijen) === 500);
    }

    // 2. Per waarde: namen, overal dezelfde vervanging.
    foreach ($per['waarde'] as $k => $r) {
        $waarden = $db->query("SELECT DISTINCT `$k` FROM `$t` WHERE `$k` IS NOT NULL AND `$k` <> ''")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($waarden as $w) {
            $nieuw = anon_naam_map((string)$w, $ctx);
            if ($nieuw !== (string)$w) $db->prepare("UPDATE `$t` SET `$k` = ?$auto WHERE `$k` = ?")->execute([$nieuw, $w]);
        }
    }

    // 3. Vaste waarden in één UPDATE per kolom.
    foreach ($per['vast'] as $k => $r) {
        if ($r === 'null') {
            $db->exec("UPDATE `$t` SET `$k` = NULL$auto WHERE `$k` IS NOT NULL");
        } elseif ($r === 'overslaan') {
            $db->exec("UPDATE `$t` SET `$k` = 'overgeslagen'$auto WHERE `$k` IN ('wacht', 'bezig')");
        } else {
            $v = anon_vast($r);
            $db->prepare("UPDATE `$t` SET `$k` = ?$auto WHERE `$k` IS NOT NULL AND `$k` <> '' AND `$k` <> ?")->execute([$v, $v]);
        }
    }
    return ['status' => 'bewerkt', 'rijen' => $n, 'mist' => $mist];
}

/** Sleutels in `instellingen` die leeg moeten: de bekende geheimen plus alles wat naar een geheim klinkt. */
function anon_geheime_sleutels(array $bestaand): array {
    $lijst = ['ai.api_key', 'authentik.api_token', 'authentik.token', 'smtp.pass', 'smtp.user', 'smtp.host',
              'smtp.port', 'smtp.encrypt', 'sms.http_token', 'sms.http_url', 'sms.easysendsms_key',
              'sms.messagebird_key', 'wa.api_sleutel'];
    if (function_exists('instellingen_geheime_sleutels')) $lijst = array_merge($lijst, instellingen_geheime_sleutels());
    foreach (array_keys($bestaand) as $k) {
        if (preg_match('/(^|[._])(pass|password|wachtwoord|secret|geheim|token|api_key|apikey|key|sleutel)$/i', $k)) $lijst[] = $k;
    }
    return array_values(array_diff(array_unique($lijst), array_keys(anon_nieuwe_geheimen())));
}

/** Geheimen die een nieuwe willekeurige waarde krijgen (het systeem rekent erop dat ze bestaan): sleutel => bytes. */
function anon_nieuwe_geheimen(): array {
    return ['cron.token' => 24, 'api.sleutel' => 32, 'paginas.pseudoniem_zout' => 32, 'paginas.form_geheim' => 32];
}

/**
 * Geheimen onschadelijk, WhatsApp en sms uit, adressen van de vereniging naar het testadres.
 * De rest blijft: die instellingen wil je juist testen. db.failsafe_versie en kluis.zout blijven.
 * De waarden zelf komen nooit in de uitvoer, alleen de sleutelnamen.
 */
function anon_instellingen(PDO $db, array $schema, array &$ctx): array {
    $uit = ['leeg' => [], 'nieuw' => [], 'uit' => [], 'adres' => [], 'nog_adres' => []];
    if (!isset($schema['instellingen'])) return $uit;
    $bestaand = [];
    foreach ($db->query("SELECT sleutel, waarde FROM instellingen")->fetchAll(PDO::FETCH_ASSOC) as $r) $bestaand[$r['sleutel']] = (string)$r['waarde'];
    $zet = $db->prepare("UPDATE instellingen SET waarde = ? WHERE sleutel = ?");

    foreach (anon_geheime_sleutels($bestaand) as $k) {
        if (($bestaand[$k] ?? '') === '') continue;
        $zet->execute(['', $k]);
        $bestaand[$k] = '';
        $uit['leeg'][] = $k;
    }
    foreach (anon_nieuwe_geheimen() as $k => $bytes) {
        if (($bestaand[$k] ?? '') === '') continue;
        $zet->execute([bin2hex(random_bytes($bytes)), $k]);
        $uit['nieuw'][] = $k;
    }
    foreach (['wa.actief', 'sms.actief'] as $k) {
        if (!isset($bestaand[$k]) || $bestaand[$k] === '0') continue;
        $zet->execute(['0', $k]);
        $uit['uit'][] = $k;
    }
    foreach ($bestaand as $k => $w) {
        if ($w === '' || !preg_match('/^mail\.(\w+)_email$/', $k, $m) || anon_email_toegestaan($w, $ctx)) continue;
        $nieuw = anon_email($m[1], '', $ctx);
        $zet->execute([$nieuw, $k]);
        $bestaand[$k] = $nieuw;
        $uit['adres'][] = $k;
    }
    // Wat er nog als e-mailadres in de instellingen staat (bijv. smtp.from): alleen melden.
    $p = anon_patronen();
    foreach ($bestaand as $k => $w) {
        if (!preg_match_all('~' . $p['e-mail'] . '~', $w, $m)) continue;
        foreach ($m[0] as $a) if (!anon_email_toegestaan($a, $ctx)) { $uit['nog_adres'][] = $k; break; }
    }
    return $uit;
}

/** Het hele werk, op een open verbinding. Geeft de exitcode terug. */
function anon_uitvoeren(PDO $db, array $opties, array $info): int {
    $regels = anon_regels();
    $fouten = anon_regels_controleren($regels);
    if ($fouten) { anon_zeg('Fout in de regellijst: ' . implode(', ', $fouten)); return 1; }

    $schema = anon_schema($db);
    $tel = fn(string $t) => isset($schema[$t]) ? (string)anon_tel_rijen($db, $t) : '-';
    anon_zeg('Database:     ' . ($info['db'] ?? '?') . ' op ' . ($info['host'] ?? '?'));
    anon_zeg('Nu aanwezig:  ' . $tel('personen') . ' personen, ' . $tel('sollicitaties') . ' sollicitaties, ' . $tel('beheerders') . ' beheerders');

    $marker = null;
    if (isset($schema['instellingen'])) {
        $q = $db->prepare("SELECT waarde FROM instellingen WHERE sleutel = 'anon.testkopie_op'");
        $q->execute();
        $marker = $q->fetchColumn() ?: null;
    }
    anon_zeg('Eerder geanonimiseerd: ' . ($marker ? "ja, op $marker (geboortedatums worden niet opnieuw verschoven)" : 'nee'));

    // Kluis: alleen melden, niet repareren.
    $kluis = ['docs' => 0, 'vertrouwelijk' => 0, 'foto' => 0];
    if (isset($schema['sollicitant_documenten']['status'])) $kluis['docs'] = anon_tel_rijen($db, 'sollicitant_documenten', "status <> 'vernietigd'");
    if (isset($schema['ondertekeningen']['vertrouwelijk'])) $kluis['vertrouwelijk'] = anon_tel_rijen($db, 'ondertekeningen', 'vertrouwelijk IS NOT NULL');
    if (isset($schema['blk_formulieren']['foto_url'])) $kluis['foto'] = anon_tel_rijen($db, 'blk_formulieren', "foto_url IS NOT NULL AND foto_url <> ''");

    $ctx = anon_context($db, $schema, $opties, $marker === null);
    foreach ($ctx['behoud_onbekend'] as $e) anon_zeg("Let op: --behoud-beheerder $e bestaat niet in beheerders; niemand behouden voor dit adres.");
    anon_zeg();

    $resultaat = [];
    $db->beginTransaction();
    try {
        foreach ($regels as $t => $kolommen) $resultaat[$t] = anon_tabel($db, $t, $kolommen, $schema, $ctx);
        $inst = anon_instellingen($db, $schema, $ctx);
        if (isset($schema['instellingen'])) {
            $nu = date('Y-m-d H:i:s');
            if ($marker === null) $db->prepare("INSERT INTO instellingen (sleutel, waarde) VALUES ('anon.testkopie_op', ?)")->execute([$nu]);
            else $db->prepare("UPDATE instellingen SET waarde = ? WHERE sleutel = 'anon.testkopie_op'")->execute([$nu]);
        }
        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        anon_zeg('FOUT, alles teruggedraaid: ' . $e->getMessage());
        return 1;
    }

    anon_zeg('Per tabel');
    foreach ($resultaat as $t => $r) {
        if ($r['status'] === 'ontbreekt') { anon_zeg(sprintf('  %-30s bestaat niet (overgeslagen)', $t)); continue; }
        if ($r['status'] === 'bewaar') continue;
        $tekst = $r['status'] === 'wis' ? "{$r['rijen']} rijen verwijderd" : "{$r['rijen']} rijen met gegevens bewerkt";
        if ($r['mist']) $tekst .= ' (kolommen bestaan niet: ' . implode(', ', $r['mist']) . ')';
        anon_zeg(sprintf('  %-30s %s', $t, $tekst));
    }
    anon_zeg();
    anon_zeg('Instellingen');
    anon_zeg('  leeggemaakt:  ' . ($inst['leeg'] ? implode(', ', $inst['leeg']) : '-'));
    anon_zeg('  vernieuwd:    ' . ($inst['nieuw'] ? implode(', ', $inst['nieuw']) : '-'));
    anon_zeg('  uitgezet:     ' . ($inst['uit'] ? implode(', ', $inst['uit']) : '-'));
    anon_zeg('  testadres:    ' . ($inst['adres'] ? implode(', ', $inst['adres']) : '-'));
    if ($inst['nog_adres']) anon_zeg('  nog een e-mailadres in: ' . implode(', ', $inst['nog_adres']) . ' (controleer of dat een adres van de vereniging is)');
    if (in_array('cron.token', $inst['nieuw'], true)) anon_zeg('  cron.token is nieuw: draait de cron van test via HTTP, zet dan de nieuwe regel in Plesk.');
    anon_zeg();
    anon_zeg('Kluis (bewust niet gerepareerd)');
    anon_zeg("  {$kluis['docs']} documentrecords en {$kluis['foto']} foto's verwijzen naar bestanden in DOCUMENTEN_MAP van productie;");
    anon_zeg("  die map en KLUIS_SLEUTEL komen niet mee naar test. Openen geeft op test een fout: dat is de bedoeling.");
    anon_zeg("  {$kluis['vertrouwelijk']} versleutelde BSN/IBAN-velden zijn leeggemaakt. De systeemcontrole kan de kluis rood kleuren.");
    anon_zeg();

    $scan = anon_scan($db, anon_schema($db), $regels, $ctx);
    anon_zeg('Controle (tekstkolommen zonder regel)');
    foreach ($scan['onbekend'] as $t) anon_zeg("  WAARSCHUWING: tabel $t staat niet in de regellijst van dit script. Voeg hem toe aan anon_regels().");
    foreach ($scan['kolommen'] as $plek => $n) {
        $delen = [];
        foreach ($n as $soort => $aantal) $delen[] = "$aantal rijen met een $soort";
        anon_zeg("  WAARSCHUWING: mogelijk nog persoonsgegevens in $plek: " . implode(', ', $delen));
    }
    if (!$scan['onbekend'] && !$scan['kolommen']) anon_zeg('  niets gevonden.');
    anon_zeg();
    anon_zeg('Klaar. Gooi het geëxporteerde productiebestand nu weg (laptop, downloads, server).');
    return 0;
}

/** Startpunt vanaf de opdrachtregel: opties, weigeren, verbinden, uitvoeren. */
function anon_main(array $argv): int {
    $opties = anon_opties(array_slice($argv, 1));
    if ($opties['help']) { anon_zeg(anon_gebruik()); return 0; }
    if ($opties['fout'] !== null) { anon_zeg('Fout: ' . $opties['fout']); anon_zeg(anon_gebruik()); return 2; }

    $app_url = defined('APP_URL') ? (string)APP_URL : '';
    anon_zeg('APP_URL:      ' . $app_url);
    anon_zeg('Database:     ' . (defined('DB_NAME') ? DB_NAME : '?') . ' op ' . (defined('DB_HOST') ? DB_HOST : '?'));
    $reden = anon_weigering(function_exists('is_test_omgeving') && is_test_omgeving(), $app_url,
                            defined('MAIL_OMLEIDEN') ? (string)MAIL_OMLEIDEN : '', $opties);
    if ($reden !== null) { anon_zeg('GEWEIGERD: ' . $reden); return 1; }

    try { $db = anon_pdo(); }
    catch (\Throwable $e) { anon_zeg('Geen databaseverbinding: ' . $e->getMessage()); return 1; }
    anon_zeg();
    return anon_uitvoeren($db, $opties, ['db' => DB_NAME, 'host' => DB_HOST]);
}

if (!defined('ANON_ALS_BIBLIOTHEEK')) {
    if (!is_file(__DIR__ . '/../includes/config.php')) {
        anon_zeg('GEWEIGERD: includes/config.php ontbreekt; dit script hoort op een ingerichte testserver.');
        exit(1);
    }
    require_once __DIR__ . '/../includes/functions.php';
    exit(anon_main($argv));
}

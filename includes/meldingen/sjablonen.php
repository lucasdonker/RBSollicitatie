<?php
/**
 * Paal 13 — Meldingen: sjablonen (inhoud) en rendering.
 *
 * De inhoud per meldingstype staat in `mail_templates` (slug = type). Dit bestand:
 *   - verzekert de extra kolommen (tekst_kort, categorie, samenvatting, bijlagen_json);
 *   - hernoemt oude slugs naar de nieuwe typen (eenmalig, zonder inhoud te overschrijven);
 *   - zaait ontbrekende sjablonen uit het register (includes/meldingen/registry.php);
 *   - rendert een melding tot onderwerp + volledige mail-HTML + korte tekst + samenvatting.
 *
 * Variabelen: de standaardset uit mail_variabelen() (includes/mail_render.php) aangevuld met
 * een paar korte namen die in alle sjablonen bruikbaar zijn (zie melding_vars()).
 */

if (!function_exists('sjablonen_auto_setup')) {

/** Kolommen + slug-migratie + seeds. Draait één keer per request, faalt stil. */
function sjablonen_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
    try {
        $db = db();
        if (!function_exists('db_tabel_bestaat') || !db_tabel_bestaat($db, 'mail_templates')) {
            $db->exec("CREATE TABLE IF NOT EXISTS `mail_templates` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `slug` VARCHAR(50) NOT NULL UNIQUE,
                `naam` VARCHAR(100) NOT NULL,
                `beschrijving` VARCHAR(300) DEFAULT NULL,
                `onderwerp` VARCHAR(300) NOT NULL,
                `hero_titel` VARCHAR(300) DEFAULT NULL,
                `body_html` TEXT NOT NULL,
                `gewijzigd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        if (function_exists('db_kolom_verzeker')) {
            db_kolom_verzeker($db, 'mail_templates', 'hero_titel',     'VARCHAR(300) DEFAULT NULL');
            db_kolom_verzeker($db, 'mail_templates', 'hero_subtitel',  'VARCHAR(255) DEFAULT NULL');
            db_kolom_verzeker($db, 'mail_templates', 'hero_image_url', 'VARCHAR(500) DEFAULT NULL');
            db_kolom_verzeker($db, 'mail_templates', 'hero_kleur',     "VARCHAR(20) DEFAULT NULL");
            db_kolom_verzeker($db, 'mail_templates', 'tekst_kort',     'TEXT DEFAULT NULL');
            db_kolom_verzeker($db, 'mail_templates', 'categorie',      'VARCHAR(20) DEFAULT NULL');
            db_kolom_verzeker($db, 'mail_templates', 'samenvatting',   'VARCHAR(200) DEFAULT NULL');
            db_kolom_verzeker($db, 'mail_templates', 'bijlagen_json',  'TEXT DEFAULT NULL');
        }
        sjablonen_migreer_slugs($db);
        sjablonen_seed($db);
    } catch (\Throwable $e) { error_log('sjablonen_auto_setup: ' . $e->getMessage()); }
}

/**
 * Oude slugs → nieuwe typen. Alleen hernoemen als het nieuwe slug nog niet bestaat, zodat
 * bewerkte inhoud behouden blijft. De dode 'aangenomen'-seed uit migratie 0005 wijkt voor de
 * bewerkte 'welkom_aangenomen'.
 */
function sjablonen_migreer_slugs($db): void {
    $bestaand = [];
    try { $bestaand = $db->query("SELECT slug FROM mail_templates")->fetchAll(PDO::FETCH_COLUMN); } catch (\Throwable $e) { return; }
    $heeft = fn(string $s) => in_array($s, $bestaand, true);

    if ($heeft('welkom_aangenomen') && $heeft('aangenomen')) {
        try { $db->exec("DELETE FROM mail_templates WHERE slug='aangenomen'"); } catch (\Throwable $e) {}
        $bestaand = array_values(array_diff($bestaand, ['aangenomen']));
    }
    $map = [
        'welkomstmail'             => 'aanmelding_bevestiging',
        'welkom_aangenomen'        => 'aangenomen',
        'otp_portaal'              => 'portaal_code',
        'reminder_sd_aankomst'     => 'selectiedag_nadert',
        'reminder_sd_morgen'       => 'selectiedag_morgen',
        'reminder_ondertekening'   => 'ondertekening_herinnering',
        'reminder_beschikbaarheid' => 'beschikbaarheid_voorlopig',
        'ondertekening_ouder'      => 'ondertekening_verzoek_ouder',
        'reminder_blk'             => 'herinnering_taken_open',
    ];
    $upd = $db->prepare("UPDATE mail_templates SET slug=? WHERE slug=?");
    foreach ($map as $oud => $nieuw) {
        if ($heeft($oud) && !$heeft($nieuw)) {
            try { $upd->execute([$nieuw, $oud]); $bestaand[] = $nieuw; } catch (\Throwable $e) {}
        }
    }
    // Oude variabelenaam in de inlogcode-mail ({{otp.code}}) → nieuwe; en de oude lange hero-titel
    // ("Jouw inlogcode voor het Paal 13 portaal" — mailclients maken van "Paal 13" een adreslink) → kort.
    try {
        $db->exec("UPDATE mail_templates SET body_html=REPLACE(body_html,'{{otp.code}}','{{otp_code}}') WHERE body_html LIKE '%{{otp.code}}%'");
        // De vaste afmeldzin stond t/m 2.87.7 in elke korte tekst; nu alleen bij het eerste korte bericht (code).
        $db->exec("UPDATE mail_templates SET tekst_kort=TRIM(REPLACE(tekst_kort,'Afmelden voor WhatsApp/SMS kan onder Mijn gegevens in het portaal.','')) WHERE tekst_kort LIKE '%Afmelden voor WhatsApp/SMS kan onder Mijn gegevens%'");
        // De ondertekenherinnering noemde t/m 2.125 altijd de selectiedag, ook bij loonheffing of lidmaatschap.
        $oud_zin = 'Zonder deze handtekening kan {{voornaam}} helaas niet deelnemen aan de selectiedag van Reddingsbrigade Paal 13.';
        $nw_zin  = 'We hebben deze handtekening nodig om met de sollicitatie van {{voornaam}} bij Reddingsbrigade Paal 13 verder te kunnen.';
        $db->prepare("UPDATE mail_templates SET body_html=REPLACE(body_html,?,?) WHERE slug='ondertekening_herinnering' AND body_html LIKE ?")
           ->execute([$oud_zin, $nw_zin, '%' . $oud_zin . '%']);
        if (function_exists('meldingen_register') && isset(meldingen_register()['portaal_code'])) {
            $s = meldingen_register()['portaal_code']['seed'];
            $db->prepare("UPDATE mail_templates SET hero_titel=?, hero_subtitel=? WHERE slug='portaal_code' AND (hero_titel IS NULL OR hero_titel='' OR hero_titel LIKE '%inlogcode voor%')")
               ->execute([$s['hero_titel'], $s['hero_subtitel']]);
        }
        // 'ondertekening_bevestiging' was oorspronkelijk (en t/m 2.90.9) de mail "je ouder heeft ondertekend".
        // Sinds 2.91.0 is dat gesplitst: dit sjabloon gaat naar wie ZELF ondertekent, de oudervariant heeft een
        // eigen slug. Een sjabloon dat nog over de ouder gaat of de oude {{door}}-variabele gebruikt, is dus fout
        // voor 18-plussers; dat zetten we terug naar de nieuwe standaardtekst. Een echt eigen tekst blijft staan.
        if (function_exists('meldingen_register') && isset(meldingen_register()['ondertekening_bevestiging'])) {
            $s = meldingen_register()['ondertekening_bevestiging']['seed'];
            $db->prepare("UPDATE mail_templates SET onderwerp=?, hero_titel=?, hero_subtitel=?, body_html=?, tekst_kort=?, samenvatting=?
                          WHERE slug='ondertekening_bevestiging'
                            AND (body_html LIKE '%{{door}}%' OR body_html LIKE '%ouder%' OR onderwerp LIKE '%ouder%')")
               ->execute([$s['onderwerp'], $s['hero_titel'], $s['hero_subtitel'], $s['body_html'], $s['tekst_kort'], $s['samenvatting']]);
        }
        // T/m 2.128.1: de vraag-mail noemde {{email}} (bestaat niet, werd weggehaald: "rechtstreeks op .").
        $db->exec("UPDATE mail_templates SET body_html=REPLACE(body_html,'rechtstreeks op {{email}}.','rechtstreeks op <a href=\"mailto:{{sollicitant_email}}\">{{sollicitant_email}}</a>.') WHERE slug='sollicitant_vraag' AND body_html LIKE '%{{email}}%'");
        $db->exec("UPDATE mail_templates SET body_html=REPLACE(body_html,'{{email}}','{{sollicitant_email}}') WHERE slug='sollicitant_vraag' AND body_html LIKE '%{{email}}%'");
        // T/m 2.128.1: de aangenomen-mail gebruikte {{gesprek_bericht}} niet, dus het bericht uit het
        // TW-gesprek kwam nooit aan. Alleen in de ongewijzigde standaardtekst invoegen.
        $welkom = 'We zijn blij dat je aan boord komt voor {{seizoen_naam}}.</p>';
        $db->prepare("UPDATE mail_templates SET body_html=REPLACE(body_html,?,?) WHERE slug='aangenomen' AND body_html LIKE ? AND body_html NOT LIKE '%{{gesprek_bericht}}%'")
           ->execute([$welkom, $welkom . "\n<p>{{gesprek_bericht}}</p>", '%' . $welkom . '%']);
        // 2.129.0 (besluit Lucas): sollicitanten en ouders bereiken ons via het contactadres
        // (sollicitatie@paal13.com), niet het bestuursadres. Alleen de mails aan hen.
        $aan_sollicitant = ['aanmelding_bevestiging', 'aangenomen', 'afgewezen', 'tw_niet_gekomen', 'tw_afgewezen',
                            'ondertekening_verzoek_zelf', 'intrekverzoek_niet_doorgevoerd', 'deelname_afgemeld',
                            'herinnering_selectie_aanmelden', 'ondertekening_verzoek_ouder', 'lidmaatschap_verzoek_ouder'];
        $db->exec("UPDATE mail_templates SET body_html=REPLACE(body_html,'{{bestuur_email}}','{{contact_email}}')
                   WHERE body_html LIKE '%{{bestuur_email}}%' AND slug IN ('" . implode("','", $aan_sollicitant) . "')");
        // 2.130.0: "{{tw_datum_start}} t/m {{tw_datum_eind}}" gaf "Wanneer:  t/m " zolang de datum nog niet
        // bekend was. {{tw_periode}} levert altijd een hele zin (ook één dag, of "een datum die nog volgt").
        // 2.130.0: "Mail ons gerust." zonder adres in het ouderverzoek; de kopie-mail (naar ouders én
        // 18-plussers) sprak met "je" terwijl oudermails "u" zeggen — nu zonder aanspreekvorm.
        $db->exec("UPDATE mail_templates SET body_html=REPLACE(body_html,'<p>Heeft u vragen? Mail ons gerust.</p>',
                   '<p>Heeft u vragen? Mail ons gerust via <a href=\"mailto:{{contact_email}}\">{{contact_email}}</a>.</p>')
                   WHERE slug='ondertekening_verzoek_ouder' AND body_html LIKE '%Mail ons gerust.</p>%'");
        $db->exec("UPDATE mail_templates SET hero_titel='Kopie van de ondertekening', hero_subtitel='Voor de eigen administratie',
                   body_html=REPLACE(body_html,'<p>In de bijlage vind je een kopie van het ondertekende document, zodat je kunt terugzien waar je je handtekening onder hebt gezet.</p>',
                   '<p>In de bijlage staat een kopie van het ondertekende document, om later terug te kunnen zien wat er is ondertekend.</p>')
                   WHERE slug='ondertekening_kopie' AND hero_titel='Kopie van je ondertekening'");
        foreach (['onderwerp', 'body_html', 'tekst_kort', 'samenvatting'] as $_kol) {
            $db->exec("UPDATE mail_templates SET `$_kol`=REPLACE(`$_kol`,'{{tw_datum_start}} t/m {{tw_datum_eind}}','{{tw_periode}}')
                       WHERE `$_kol` LIKE '%{{tw_datum_start}} t/m {{tw_datum_eind}}%'");
        }
        // 2.146.0: "ik kom" meldt je aan voor de selectiedag, niet meer het kennismakingsformulier.
        // Alleen de ongewijzigde standaardzinnen; een eigen tekst blijft staan.
        $_vervang = [
            ['uitnodiging_selectie', 'body_html',
             'Log in op je portaal en vul het kennismakingsformulier in. Daarmee bevestig je meteen je deelname aan de selectiedag.',
             'Log in op je portaal en laat weten of je komt. Vul daar ook het kennismakingsformulier in.'],
            ['selectie_aangemeld_bevestiging', 'body_html', 'Bedankt voor het invullen van je formulier.', 'Bedankt dat je het hebt laten weten.'],
            ['bestuur_blk_ingediend', 'body_html', 'heeft het kennismakingsformulier ingeleverd en is daarmee aangemeld voor de selectiedag.',
             'heeft het kennismakingsformulier ingeleverd.'],
            ['herinnering_selectie_aanmelden', 'body_html',
             'maar je hebt je deelname nog niet bevestigd. Dat doe je door het kennismakingsformulier in je portaal in te vullen.</p>' . "\n" . '[knop href="{{portaal_url}}"]Formulier invullen[/knop]',
             'maar je hebt nog niet laten weten of je komt. Dat doe je in je portaal; daar kun je ook doorgeven dat je niet kunt.</p>' . "\n" . '[knop href="{{portaal_url}}"]Naar mijn portaal[/knop]'],
            ['herinnering_selectie_aanmelden', 'tekst_kort', 'Bevestig je deelname via het formulier in je portaal:', 'Laat in je portaal weten of je komt:'],
        ];
        $_st = $db->prepare("UPDATE mail_templates SET body_html=REPLACE(body_html,?,?) WHERE slug=? AND body_html LIKE ?");
        $_sk = $db->prepare("UPDATE mail_templates SET tekst_kort=REPLACE(tekst_kort,?,?) WHERE slug=? AND tekst_kort LIKE ?");
        foreach ($_vervang as $_v) {
            ($_v[1] === 'tekst_kort' ? $_sk : $_st)->execute([$_v[2], $_v[3], $_v[0], '%' . $_v[2] . '%']);
        }
    } catch (\Throwable $e) {}
}

/** Zaai ontbrekende sjablonen uit het register; vul lege nieuwe kolommen bij bestaande. */
function sjablonen_seed($db): void {
    if (!function_exists('meldingen_register')) return;
    $rijen = [];
    try {
        foreach ($db->query("SELECT slug, tekst_kort, categorie, samenvatting FROM mail_templates")->fetchAll() as $r) $rijen[$r['slug']] = $r;
    } catch (\Throwable $e) { return; }

    $ins = $db->prepare("INSERT INTO mail_templates (slug, naam, beschrijving, onderwerp, hero_titel, hero_subtitel, hero_kleur, body_html, tekst_kort, categorie, samenvatting)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $upd = $db->prepare("UPDATE mail_templates SET tekst_kort=COALESCE(NULLIF(tekst_kort,''),?), categorie=?, samenvatting=COALESCE(NULLIF(samenvatting,''),?), beschrijving=COALESCE(NULLIF(beschrijving,''),?) WHERE slug=?");
    foreach (meldingen_register() as $slug => $def) {
        $s = $def['seed'];
        try {
            if (!isset($rijen[$slug])) {
                $ins->execute([$slug, $def['naam'], $def['beschrijving'] ?? '', $s['onderwerp'], $s['hero_titel'] ?? '', $s['hero_subtitel'] ?? '',
                               $s['hero_kleur'] ?? ($def['hero_kleur'] ?? '#054d8a'), $s['body_html'], $s['tekst_kort'] ?? '', $def['categorie'], $s['samenvatting'] ?? '']);
            } elseif (($rijen[$slug]['categorie'] ?? '') !== $def['categorie'] || ($rijen[$slug]['tekst_kort'] ?? '') === '' || ($rijen[$slug]['tekst_kort'] ?? null) === null) {
                $upd->execute([$s['tekst_kort'] ?? '', $def['categorie'], $s['samenvatting'] ?? '', $def['beschrijving'] ?? '', $slug]);
            }
        } catch (\Throwable $e) { error_log("sjablonen_seed $slug: " . $e->getMessage()); }
    }
}

/** Sjabloon uit de DB; valt terug op de seed uit het register. Null als het type onbekend is. */
function sjabloon_laden(string $type): ?array {
    static $cache = [];
    if (array_key_exists($type, $cache)) return $cache[$type];
    $def = function_exists('melding_definitie') ? melding_definitie($type) : null;
    $rij = null;
    try {
        sjablonen_auto_setup();
        $q = db()->prepare("SELECT * FROM mail_templates WHERE slug=?");
        $q->execute([$type]);
        $rij = $q->fetch() ?: null;
    } catch (\Throwable $e) { $rij = null; }
    if (!$rij && $def) {
        $s = $def['seed'];
        $rij = ['slug' => $type, 'naam' => $def['naam'], 'onderwerp' => $s['onderwerp'], 'hero_titel' => $s['hero_titel'] ?? '',
                'hero_subtitel' => $s['hero_subtitel'] ?? '', 'hero_kleur' => $s['hero_kleur'] ?? $def['hero_kleur'], 'hero_image_url' => null,
                'body_html' => $s['body_html'], 'tekst_kort' => $s['tekst_kort'] ?? '', 'samenvatting' => $s['samenvatting'] ?? '',
                'categorie' => $def['categorie'], 'bijlagen_json' => null];
    }
    if ($rij && $def) {
        // Lege korte tekst / samenvatting in de DB → seed gebruiken, zodat "kort" altijd iets heeft.
        if (trim((string)($rij['tekst_kort'] ?? '')) === '')   $rij['tekst_kort']   = $def['seed']['tekst_kort'] ?? '';
        if (trim((string)($rij['samenvatting'] ?? '')) === '') $rij['samenvatting'] = $def['seed']['samenvatting'] ?? '';
    }
    return $cache[$type] = $rij;
}

/**
 * Variabelen voor een sollicitatie: standaardset + korte aliassen + $extra.
 * $sol_id = 0 → alleen seizoen + algemene variabelen (voor testberichten/direct).
 */
function melding_vars(int $sol_id, array $extra = []): array {
    $sol = []; $seizoen = [];
    if ($sol_id > 0 && function_exists('portaal_sollicitant_laden')) {
        $rij = portaal_sollicitant_laden($sol_id) ?: [];
        if ($rij) {
            $sol = $rij;
            $seizoen = $rij;
            $seizoen['naam'] = $rij['seizoen_naam'] ?? ($rij['sz_naam'] ?? '');
        }
    }
    if (!$seizoen && function_exists('actief_seizoen')) $seizoen = actief_seizoen() ?: [];

    $vars = function_exists('mail_variabelen') ? mail_variabelen($sol, $seizoen) : [];
    $app = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    $vars['portaal_url']          = $app . '/portaal/';
    $vars['aanmeld_url']          = $app . '/aanmelden.php';
    $vars['beheer_url']           = $app . '/admin/sollicitanten.php' . ($sol_id ? '?panel=' . $sol_id : '');
    $vars['sollicitant_email']    = $sol['email'] ?? '';
    $vars['sollicitant_telefoon'] = $sol['telefoon'] ?? '';
    $vars['selectiedag_adres']    = $seizoen['adres_selectiedag'] ?? '';
    $vars['selectiedag_meenemen'] = $seizoen['selectiedag_meenemen'] ?? '';
    $vars['selectiedag_eindtijd'] = !empty($seizoen['selectiedag_eindtijd']) ? substr($seizoen['selectiedag_eindtijd'], 0, 5) : '';
    $vars['tw_adres']             = $seizoen['adres_tw'] ?? '';
    $vars['tw_meenemen']          = $seizoen['tw_meenemen'] ?? '';
    $vars['tw_eindtijd']          = !empty($seizoen['tw_eindtijd']) ? substr($seizoen['tw_eindtijd'], 0, 5) : '';
    $vars['bestuur_email']        = function_exists('bestuur_email') ? bestuur_email() : ($vars['bestuur_email'] ?? '');
    $vars['bestuur.email']        = $vars['bestuur_email'];
    // Het adres waar sollicitanten en ouders ons bereiken (besluit: altijd sollicitatie@paal13.com, instelbaar).
    $vars['contact_email']        = function_exists('portaal_contact_email') ? portaal_contact_email() : $vars['bestuur_email'];
    $vars['ouder_naam']           = $sol['ouder_naam'] ?? '';
    if (!empty($sol['persoon_id']) && function_exists('optout_link')) {
        try { $vars['afmeld_url'] = optout_link((int)$sol['persoon_id']); } catch (\Throwable $e) { $vars['afmeld_url'] = ''; }
    } else {
        $vars['afmeld_url'] = '';
    }
    $vars['seintje_url'] = (!empty($sol['persoon_id']) && function_exists('seintje_link')) ? seintje_link((int)$sol['persoon_id']) : '';
    foreach ($extra as $k => $v) $vars[$k] = $v;
    // Oude sjablonen gebruiken de lange namen ({{otp.code}}); nieuwe de korte ({{otp_code}}). Beide vullen.
    foreach (['otp_code' => 'otp.code', 'portaal_url' => 'portaal.url', 'bestuur_email' => 'bestuur.email', 'voornaam' => 'sollicitant.voornaam', 'achternaam' => 'sollicitant.achternaam', 'volledige_naam' => 'sollicitant.volledige_naam', 'seizoen_naam' => 'seizoen.naam'] as $kort => $lang) {
        if (isset($vars[$kort]) && $vars[$kort] !== '' && (!isset($vars[$lang]) || $vars[$lang] === '')) $vars[$lang] = $vars[$kort];
        if (isset($vars[$lang]) && $vars[$lang] !== '' && (!isset($vars[$kort]) || $vars[$kort] === '')) $vars[$kort] = $vars[$lang];
    }
    return $vars;
}

/** Platte vervanging van {{var}} zonder HTML-escaping (voor onderwerp, korte tekst, samenvatting). */
function tekst_vervang(string $tekst, array $vars): string {
    // Twee rondes: een variabele (bijv. tekst_kort van een handmatig bericht) kan zelf variabelen bevatten.
    for ($ronde = 0; $ronde < 2 && strpos($tekst, '{{') !== false; $ronde++) {
        foreach ($vars as $k => $v) {
            if (is_array($v)) continue;
            $tekst = str_replace('{{' . $k . '}}', (string)$v, $tekst);
        }
    }
    // Onbekende variabelen netjes weghalen
    return trim((string)preg_replace('/\{\{[a-z0-9_.]+\}\}/i', '', $tekst));
}

/** Korte tekst voor WhatsApp/SMS: variabelen invullen, HTML strippen, witruimte normaliseren, afkappen. */
function tekst_kort_maak(string $sjabloon, array $vars, int $max = 400): string {
    $t = tekst_vervang($sjabloon, $vars);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    $t = trim((string)preg_replace('/[ \t]+/', ' ', (string)preg_replace('/\r?\n\s*\r?\n/', "\n", $t)));
    if (function_exists('mb_strlen')) {
        if (mb_strlen($t) > $max) $t = mb_substr($t, 0, $max - 3) . '...';
    } elseif (strlen($t) > $max) {
        $t = substr($t, 0, $max - 3) . '...';
    }
    return $t;
}

/**
 * Logica ({{als …}}, {{kies …}}) in alle tekstvelden van een sjabloon toepassen, vóór de variabelen.
 * Eerst gaat de inhoud van een zelf geschreven bericht ({{body_html}}, {{onderwerp}}, {{tekst_kort}})
 * erin, zodat ook die logica mag bevatten; ingevulde gegevens van de sollicitant komen pas daarna en
 * worden dus nooit als logica gelezen. De eerste fout komt in $fout.
 */
function melding_logica(array $t, array $vars, ?int $sol_id, ?string &$fout): array {
    $inhoud = fn(string $tekst, array $namen) => strtr($tekst, array_combine(
        array_map(fn($n) => '{{' . $n . '}}', $namen), array_map(fn($n) => (string)($vars[$n] ?? '{{' . $n . '}}'), $namen)));
    $waarde = null;
    foreach (['onderwerp' => false, 'hero_titel' => false, 'hero_subtitel' => false, 'body_html' => true, 'tekst_kort' => false, 'samenvatting' => false] as $veld => $html) {
        $tekst = (string)($t[$veld] ?? '');
        if ($tekst === '' || strpos($tekst, '{{') === false) continue;
        $tekst = $inhoud($tekst, $html ? ['body_html'] : ['onderwerp', 'tekst_kort']);
        if (!sjabloon_heeft_logica($tekst)) continue;
        $waarde = $waarde ?? voorwaarde_resolver($sol_id, $vars);
        try { $t[$veld] = sjabloon_logica($tekst, $waarde, $html); }
        catch (SjabloonFout $e) { $fout = $fout ?? $e->getMessage(); $t[$veld] = ''; continue; }
        // Vangnet: een tag die niet dicht is ({{als x) zou letterlijk naar WhatsApp, sms of mail gaan.
        if (sjabloon_losse_accolade($t[$veld])) { $fout = $fout ?? 'Er staat een {{ zonder }} in ' . $veld . '.'; $t[$veld] = ''; }
    }
    return $t;
}

/**
 * Render een meldingstype met variabelen.
 * $override kan onderwerp/body_html/tekst_kort/hero_* overschrijven (handmatige berichten).
 * $sol_id voor de logica in het sjabloon: null = voorbeeldwaarden, 0 = geen sollicitant, > 0 = echt.
 * Retourneert null als het type onbekend is; 'fout' is gevuld als de logica niet klopt (dan niet versturen).
 */
function melding_render(string $type, array $vars, array $override = [], ?int $sol_id = null): ?array {
    $t = sjabloon_laden($type);
    if (!$t) return null;
    foreach ($override as $k => $v) if ($v !== null && $v !== '') $t[$k] = $v;
    $fout = null;
    if (function_exists('sjabloon_logica')) $t = melding_logica($t, $vars, $sol_id, $fout);

    // Twee keer vervangen: een variabele (bijv. body_html van een handmatig bericht) kan zelf weer
    // variabelen bevatten ({{voornaam}}).
    $onderwerp = tekst_vervang((string)$t['onderwerp'], $vars);
    $hero_t    = tekst_vervang((string)($t['hero_titel'] ?: $onderwerp), $vars);
    $hero_s    = tekst_vervang((string)($t['hero_subtitel'] ?? ''), $vars);
    $kleur     = (string)($t['hero_kleur'] ?? '');
    if ($kleur === '' || $kleur === '#1A2540') $kleur = '';
    $img       = (string)($t['hero_image_url'] ?? '');
    if ($img !== '' && function_exists('foto_url')) { try { $img = (string)foto_url($img); } catch (\Throwable $e) {} }

    $body = (string)$t['body_html'];
    $body = function_exists('mail_vervang') ? mail_vervang(mail_vervang($body, $vars), $vars) : tekst_vervang(tekst_vervang($body, $vars), $vars);
    $body = (string)preg_replace('/\{\{[a-z0-9_.]+\}\}/i', '', $body);
    // Een lege variabele in een eigen alinea (bijv. geen gespreksbericht) laat geen gat achter.
    $body = (string)preg_replace('#<p>\s*</p>\s*#i', '', $body);
    if (!empty($override['bijlagen_blok_html'])) $body .= $override['bijlagen_blok_html'];
    // Vast blok onder de tekst (bijv. de seintje-vraag); vóór de shortcodes, want het gebruikt [knop].
    if (!empty($override['blok_html'])) $body .= $override['blok_html'];
    if (function_exists('mail_shortcodes')) $body = mail_shortcodes($body);
    if (!empty($override['voetnoot_html'])) $body .= $override['voetnoot_html'];

    $html = function_exists('mail_html_wrapper')
        ? mail_html_wrapper($body, $onderwerp, $hero_t, $hero_s, $img ?: null, $kleur)
        : $body;

    // Een zelf geschreven bericht mag langer (tot 1000 tekens); een vast sjabloon blijft kort.
    $kort = tekst_kort_maak((string)($t['tekst_kort'] ?? ''), $vars, (int)((function_exists('melding_definitie') ? melding_definitie($type) : null)['kort_max'] ?? 400));
    $sam  = tekst_kort_maak((string)($t['samenvatting'] ?? ''), $vars, 300);
    if ($sam === '') $sam = tekst_kort_maak($body, $vars, 140);

    $bijlagen = [];
    if (!empty($t['bijlagen_json'])) { $j = json_decode((string)$t['bijlagen_json'], true); if (is_array($j)) $bijlagen = $j; }

    return [
        'onderwerp' => $onderwerp, 'hero_titel' => $hero_t, 'hero_subtitel' => $hero_s, 'hero_kleur' => $kleur,
        'hero_image' => $img, 'body_html' => $body, 'html' => $html, 'tekst_kort' => $kort, 'samenvatting' => $sam,
        'bijlagen' => $bijlagen, 'fout' => $fout,
    ];
}

// Opmaak van de korte tekst (WhatsApp/sms) en de lijst met variabelen: includes/berichten_opmaak.php.

}

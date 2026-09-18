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
    static $done = false; if ($done) return; $done = true;
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
    $vars['ouder_naam']           = $sol['ouder_naam'] ?? '';
    if (!empty($sol['persoon_id']) && function_exists('optout_link')) {
        try { $vars['afmeld_url'] = optout_link((int)$sol['persoon_id']); } catch (\Throwable $e) { $vars['afmeld_url'] = ''; }
    } else {
        $vars['afmeld_url'] = '';
    }
    foreach ($extra as $k => $v) $vars[$k] = $v;
    return $vars;
}

/** Platte vervanging van {{var}} zonder HTML-escaping (voor onderwerp, korte tekst, samenvatting). */
function tekst_vervang(string $tekst, array $vars): string {
    foreach ($vars as $k => $v) {
        if (is_array($v)) continue;
        $tekst = str_replace('{{' . $k . '}}', (string)$v, $tekst);
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
 * Render een meldingstype met variabelen.
 * $override kan onderwerp/body_html/tekst_kort/hero_* overschrijven (handmatige berichten).
 * Retourneert null als het type onbekend is.
 */
function melding_render(string $type, array $vars, array $override = []): ?array {
    $t = sjabloon_laden($type);
    if (!$t) return null;
    foreach ($override as $k => $v) if ($v !== null && $v !== '') $t[$k] = $v;

    $onderwerp = tekst_vervang((string)$t['onderwerp'], $vars);
    $hero_t    = tekst_vervang((string)($t['hero_titel'] ?: $onderwerp), $vars);
    $hero_s    = tekst_vervang((string)($t['hero_subtitel'] ?? ''), $vars);
    $kleur     = (string)($t['hero_kleur'] ?? '');
    if ($kleur === '' || $kleur === '#1A2540') $kleur = '';
    $img       = (string)($t['hero_image_url'] ?? '');
    if ($img !== '' && function_exists('foto_url')) { try { $img = (string)foto_url($img); } catch (\Throwable $e) {} }

    $body = (string)$t['body_html'];
    $body = function_exists('mail_vervang') ? mail_vervang($body, $vars) : tekst_vervang($body, $vars);
    $body = (string)preg_replace('/\{\{[a-z0-9_.]+\}\}/i', '', $body);
    if (function_exists('mail_shortcodes')) $body = mail_shortcodes($body);
    if (!empty($override['voetnoot_html'])) $body .= $override['voetnoot_html'];

    $html = function_exists('mail_html_wrapper')
        ? mail_html_wrapper($body, $onderwerp, $hero_t, $hero_s, $img ?: null, $kleur)
        : $body;

    $kort = tekst_kort_maak((string)($t['tekst_kort'] ?? ''), $vars);
    $sam  = tekst_kort_maak((string)($t['samenvatting'] ?? ''), $vars, 300);
    if ($sam === '') $sam = tekst_kort_maak($body, $vars, 140);

    $bijlagen = [];
    if (!empty($t['bijlagen_json'])) { $j = json_decode((string)$t['bijlagen_json'], true); if (is_array($j)) $bijlagen = $j; }

    return [
        'onderwerp' => $onderwerp, 'hero_titel' => $hero_t, 'hero_subtitel' => $hero_s, 'hero_kleur' => $kleur,
        'hero_image' => $img, 'body_html' => $body, 'html' => $html, 'tekst_kort' => $kort, 'samenvatting' => $sam,
        'bijlagen' => $bijlagen,
    ];
}

}

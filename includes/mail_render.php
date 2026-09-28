<?php
/**
 * Mail template renderer — Paal 13
 */

// Centrale mail-opmaak (het "jasje" om elke mail). Alle wrappers gebruiken dit.
if (file_exists(__DIR__ . '/mail_thema.php')) require_once __DIR__ . '/mail_thema.php';

/**
 * Een datum zoals je hem zou zeggen: "zaterdag 11 april" in plaats van "11 apr 2026".
 *
 * In een uitnodiging is de dág van de week het eerste wat iemand wil weten — dan pas
 * de datum. datum_nl() blijft wat het is (tabellen, logboek, beheer); dit is de vorm
 * voor berichten aan sollicitanten. portaal_datum_lang() doet hetzelfde in het portaal;
 * die wordt hier gebruikt als hij beschikbaar is, zodat beide dezelfde zin opleveren.
 */
function mail_datum_lang(?string $d): string {
    if (!$d) return '';
    if (function_exists('portaal_datum_lang')) {
        $lang = portaal_datum_lang($d);
        if ($lang !== '') return lcfirst($lang);
    }
    return datum_nl($d);
}

/**
 * Een gegeven van het seizoen voor in een mailzin. Ontbreekt het (null óf leeg), dan een tekst die
 * midden in een zin past ("Waar: nog niet bekend", "op een datum die nog volgt") — eerder stond daar
 * "Nader te bepalen" met hoofdletter midden in de zin, of niets ("Wanneer:  t/m ").
 */
function mail_of_onbekend($waarde, string $onbekend = 'nog niet bekend'): string {
    $w = trim((string)($waarde ?? ''));
    return $w !== '' ? $w : $onbekend;
}

/** "vrijdag 3 juli t/m zondag 5 juli", één dag, of "een datum die nog volgt". Voor {{tw_periode}}. */
function mail_tw_periode(array $seizoen): string {
    $van = mail_datum_lang($seizoen['datum_tw_start'] ?? null);
    $tot = mail_datum_lang($seizoen['datum_tw_eind'] ?? null);
    if ($van === '') return 'een datum die nog volgt';
    return ($tot === '' || $tot === $van) ? $van : $van . ' t/m ' . $tot;
}

function mail_variabelen(array $sol, array $seizoen = []): array {
    $sd_datum = !empty($seizoen['datum_selectiedag']) ? mail_datum_lang($seizoen['datum_selectiedag']) : 'een datum die nog volgt';
    $sd_tijd  = !empty($seizoen['selectiedag_verzameltijd']) ? ' om ' . substr($seizoen['selectiedag_verzameltijd'], 0, 5) : '';
    $sd_loc   = mail_of_onbekend($seizoen['locatie_selectiedag'] ?? null);

    return [
        // Lange namen (bestaand)
        'sollicitant.voornaam'        => $sol['voornaam'] ?? '',
        'sollicitant.achternaam'      => ($sol['tussenvoegsel'] ?? '' ? $sol['tussenvoegsel'] . ' ' : '') . ($sol['achternaam'] ?? ''),
        'sollicitant.volledige_naam'  => volledige_naam($sol),
        'sollicitant.email'           => $sol['email'] ?? '',
        'sollicitant.telefoon'        => $sol['telefoon'] ?? '',
        'seizoen.naam'                => $seizoen['naam'] ?? '',
        'selectiedag.datum'           => datum_nl($seizoen['datum_selectiedag'] ?? null),
        'selectiedag.locatie'         => $sd_loc,
        'selectiedag.starttijd'       => mail_of_onbekend($seizoen['selectiedag_starttijd'] ?? null),
        'selectiedag.verzameltijd'    => mail_of_onbekend($seizoen['selectiedag_verzameltijd'] ?? null),
        'selectiedag.eindtijd'        => !empty($seizoen['selectiedag_eindtijd']) ? substr($seizoen['selectiedag_eindtijd'], 0, 5) : '',
        'selectiedag.adres'           => $seizoen['adres_selectiedag'] ?? '',
        'selectiedag.meenemen'        => $seizoen['selectiedag_meenemen'] ?? '',
        'tw.datum_start'              => datum_nl($seizoen['datum_tw_start'] ?? null),
        'tw.datum_eind'               => datum_nl($seizoen['datum_tw_eind'] ?? null),
        'tw.locatie'                  => mail_of_onbekend($seizoen['locatie_tw'] ?? null),
        'tw.verzameltijd'             => mail_of_onbekend($seizoen['tw_verzameltijd'] ?? null),
        'tw.eindtijd'                 => !empty($seizoen['tw_eindtijd']) ? substr($seizoen['tw_eindtijd'], 0, 5) : '',
        'tw.adres'                    => $seizoen['adres_tw'] ?? '',
        'tw.meenemen'                 => $seizoen['tw_meenemen'] ?? '',
        'portaal.url'                 => defined('APP_URL') ? APP_URL . '/portaal/' : '',
        'bestuur.email'               => defined('MAIL_BESTUUR') ? MAIL_BESTUUR : '',
        'otp.code'                    => '',

        // Korte aliassen (gebruikt in reminder templates)
        'voornaam'                    => $sol['voornaam'] ?? '',
        'achternaam'                  => $sol['achternaam'] ?? '',
        'volledige_naam'              => volledige_naam($sol),
        'portaal_url'                 => defined('APP_URL') ? APP_URL . '/portaal/' : '',
        'seizoen_naam'                => $seizoen['naam'] ?? '',
        'selectiedag_datum'           => $sd_datum,
        'selectiedag_tijd'            => $sd_tijd,
        'selectiedag_locatie'         => $sd_loc,
        'tw_datum_start'              => mail_datum_lang($seizoen['datum_tw_start'] ?? null) ?: 'een datum die nog volgt',
        'tw_datum_eind'               => mail_datum_lang($seizoen['datum_tw_eind'] ?? null) ?: datum_nl($seizoen['datum_tw_eind'] ?? null),
        'tw_locatie'                  => mail_of_onbekend($seizoen['locatie_tw'] ?? null),
        'tw_verzameltijd'             => mail_of_onbekend($seizoen['tw_verzameltijd'] ?? null),
        'tw_periode'                  => mail_tw_periode($seizoen),
        'bestuur_email'               => defined('MAIL_BESTUUR') ? MAIL_BESTUUR : '',
    ];
}

function mail_template_render(string $slug, array $vars): ?array {
    static $setup_done = false;
    if (!$setup_done) { mail_templates_auto_setup(); $setup_done = true; }
    try {
        $stmt = db()->prepare("SELECT * FROM mail_templates WHERE slug = ?");
        $stmt->execute([$slug]);
        $tmpl = $stmt->fetch();
        if (!$tmpl) return null;
    } catch (Exception $e) { return null; }
    $onderwerp     = mail_vervang($tmpl['onderwerp'], $vars);
    $hero_titel    = !empty($tmpl['hero_titel'])    ? mail_vervang($tmpl['hero_titel'], $vars)    : $onderwerp;
    $hero_subtitel = !empty($tmpl['hero_subtitel']) ? mail_vervang($tmpl['hero_subtitel'], $vars) : '';
    $hero_image    = $tmpl['hero_image_url'] ?? null;
    $hero_kleur    = $tmpl['hero_kleur'] ?: '#1A2540';
    $body          = mail_shortcodes(mail_vervang($tmpl['body_html'], $vars));
    return [
        'onderwerp'     => $onderwerp,
        'hero_titel'    => $hero_titel,
        'hero_subtitel' => $hero_subtitel,
        'hero_image'    => $hero_image,
        'hero_kleur'    => $hero_kleur,
        'body_html'     => $body,
    ];
}

/**
 * Voegt nieuwe hero-kolommen toe aan mail_templates als ze nog niet bestaan.
 * Werkt op alle MySQL/MariaDB versies via INFORMATION_SCHEMA.
 */
function mail_templates_auto_setup(): void {
    // Sinds 2.87: kolommen, slug-migratie en seeds zitten in includes/meldingen/sjablonen.php.
    if (function_exists('sjablonen_auto_setup')) sjablonen_auto_setup();
}

function mail_vervang(string $tekst, array $vars): string {
    // Variabelen die bewust HTML bevatten (door het systeem zelf opgebouwd, geen
    // gebruikersinvoer) mogen NIET ge-escaped worden — anders zie je de rauwe HTML-code
    // in de mail. Herkenbaar aan de naam-suffix '_blok' of '_html'.
    foreach ($vars as $k => $v) {
        $is_html = (substr($k, -5) === '_blok' || substr($k, -5) === '_html');
        $waarde  = $is_html ? (string)$v : htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $tekst   = str_replace('{{' . $k . '}}', $waarde, $tekst);
    }
    return $tekst;
}

function mail_shortcodes(string $html): string {
    // [knop href="url"]Tekst[/knop]
    $html = preg_replace_callback(
        '/\[knop\s+href="([^"]+)"\](.+?)\[\/knop\]/s',
        fn($m) => '
<table width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr><td style="padding:4px 0 24px">
    <a href="' . $m[1] . '" style="display:inline-block;background:#D43D00;color:#ffffff;
       text-decoration:none;padding:14px 32px;border-radius:6px;font-weight:700;
       font-size:15px;font-family:Arial,sans-serif;line-height:1">' . $m[2] . '</a>
  </td></tr>
</table>',
        $html
    );
    // [info]...[/info]
    $html = preg_replace_callback('/\[info\](.+?)\[\/info\]/s',
        fn($m) => '
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 20px">
  <tr><td style="background:#fff5f0;border-left:4px solid #D43D00;border-radius:0 6px 6px 0;
      padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#374151;line-height:1.65">
    ' . $m[1] . '
  </td></tr>
</table>', $html);
    // [let op]...[/let op]
    $html = preg_replace_callback('/\[let op\](.+?)\[\/let op\]/s',
        fn($m) => '
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 20px">
  <tr><td style="background:#fffbeb;border-left:4px solid #f59e0b;border-radius:0 6px 6px 0;
      padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#374151;line-height:1.65">
    <strong>Let op:</strong> ' . $m[1] . '
  </td></tr>
</table>', $html);
    // [code]...[/code]
    $html = preg_replace_callback('/\[code\](.+?)\[\/code\]/s',
        fn($m) => '
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px">
  <tr><td align="center" style="background:#f8fafc;border:2px dashed #cbd5e1;border-radius:10px;
      padding:28px 20px;font-family:\'Courier New\',monospace;font-size:40px;
      font-weight:800;letter-spacing:14px;color:#002147">
    ' . trim($m[1]) . '
  </td></tr>
</table>', $html);
    return $html;
}

/**
 * Voegt inline styles toe aan body-HTML voor maximale mail-client compatibiliteit
 * (Gmail web, Outlook, Apple Mail strippen of negeren <style> blocks soms)
 */
function mail_inline_styles(string $html): string {
    $regels = [
        // Tag, inline style om toe te voegen (alleen als nog geen style="")
        '/(<p)(\s+[^>]*?)?(>)/i'       => '<p style="margin:0 0 18px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.8"$2$3',
        '/(<a)(\s+[^>]*?)?(>)/i'       => '<a style="color:#D43D00;text-decoration:none"$2$3',
        '/(<strong)(\s+[^>]*?)?(>)/i'  => '<strong style="color:#111827"$2$3',
        '/(<b)(\s+[^>]*?)?(>)/i'       => '<b style="color:#111827"$2$3',
        '/(<ul)(\s+[^>]*?)?(>)/i'      => '<ul style="margin:0 0 18px;padding-left:22px;font-family:Arial,Helvetica,sans-serif"$2$3',
        '/(<ol)(\s+[^>]*?)?(>)/i'      => '<ol style="margin:0 0 18px;padding-left:22px;font-family:Arial,Helvetica,sans-serif"$2$3',
        '/(<li)(\s+[^>]*?)?(>)/i'      => '<li style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.8;margin-bottom:4px"$2$3',
        '/(<h2)(\s+[^>]*?)?(>)/i'      => '<h2 style="margin:24px 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:20px;color:#002147;font-weight:700;line-height:1.3"$2$3',
        '/(<h3)(\s+[^>]*?)?(>)/i'      => '<h3 style="margin:20px 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:17px;color:#002147;font-weight:600;line-height:1.3"$2$3',
        '/(<blockquote)(\s+[^>]*?)?(>)/i' => '<blockquote style="margin:18px 0;padding:12px 18px;border-left:3px solid #D43D00;background:#f9fafb;color:#4b5563;font-style:italic"$2$3',
        '/(<hr)(\s+[^>]*?)?(\/?>)/i'   => '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0" $2$3',
    ];
    // Alleen toepassen op tags die nog GEEN style attribute hebben
    foreach ($regels as $pat => $rep) {
        $html = preg_replace_callback($pat, function($m) use ($rep, $pat) {
            // Skip als al een style attribute aanwezig is
            if (isset($m[2]) && stripos($m[2], 'style=') !== false) return $m[0];
            // Doe de standaard vervanging
            return preg_replace($pat, $rep, $m[0], 1);
        }, $html);
    }
    return $html;
}

/** Volledige mail-HTML rond een stuk body: het centrale thema (includes/mail_thema.php). */
function mail_html_wrapper(
    string $body_html,
    string $onderwerp,
    string $hero_titel = '',
    string $hero_subtitel = '',
    ?string $hero_image = null,
    string $hero_kleur = ''
): string {
    // '#1A2540' was de oude standaard navy; leeg laten = thema-kleur gebruiken
    return mail_thema_wrapper(mail_inline_styles($body_html), $onderwerp, $hero_titel, $hero_subtitel,
        ($hero_kleur && $hero_kleur !== '#1A2540') ? $hero_kleur : '', $hero_image ?: '');
}

/** Eenvoudige variant (eigen afbeelding als fotohero). Zelfde thema. */
function mail_simple_wrapper(string $body_html, string $onderwerp, string $custom_image = ''): string {
    return mail_thema_wrapper(mail_inline_styles($body_html), $onderwerp, $onderwerp, '', '', $custom_image);
}

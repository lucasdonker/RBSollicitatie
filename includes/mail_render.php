<?php
/**
 * Mail template renderer — Paal 13
 */

// Centrale mail-opmaak (het "jasje" om elke mail). Alle wrappers gebruiken dit.
if (file_exists(__DIR__ . '/mail_thema.php')) require_once __DIR__ . '/mail_thema.php';

function mail_variabelen(array $sol, array $seizoen = []): array {
    $sd_datum = !empty($seizoen['datum_selectiedag']) ? datum_nl($seizoen['datum_selectiedag']) : 'Nader te bepalen';
    $sd_tijd  = !empty($seizoen['selectiedag_verzameltijd']) ? ' om ' . substr($seizoen['selectiedag_verzameltijd'], 0, 5) : '';
    $sd_loc   = $seizoen['locatie_selectiedag'] ?? 'Nader te bepalen';

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
        'selectiedag.starttijd'       => $seizoen['selectiedag_starttijd'] ?? 'Nader te bepalen',
        'selectiedag.verzameltijd'    => $seizoen['selectiedag_verzameltijd'] ?? 'Nader te bepalen',
        'tw.datum_start'              => datum_nl($seizoen['datum_tw_start'] ?? null),
        'tw.datum_eind'               => datum_nl($seizoen['datum_tw_eind'] ?? null),
        'tw.locatie'                  => $seizoen['locatie_tw'] ?? 'Nader te bepalen',
        'tw.verzameltijd'             => $seizoen['tw_verzameltijd'] ?? 'Nader te bepalen',
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
        'tw_datum_start'              => datum_nl($seizoen['datum_tw_start'] ?? null),
        'tw_datum_eind'               => datum_nl($seizoen['datum_tw_eind'] ?? null),
        'tw_locatie'                  => $seizoen['locatie_tw'] ?? 'Nader te bepalen',
        'tw_verzameltijd'             => $seizoen['tw_verzameltijd'] ?? 'Nader te bepalen',
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
    try {
        $db = db();
        $dbn = $db->query("SELECT DATABASE()")->fetchColumn();
        $kolommen = [
            'hero_titel'     => "VARCHAR(255) DEFAULT NULL",
            'hero_subtitel'  => "VARCHAR(255) DEFAULT NULL",
            'hero_image_url' => "VARCHAR(500) DEFAULT NULL",
            'hero_kleur'     => "VARCHAR(20)  DEFAULT '#1A2540'",
        ];
        $kolommen_toegevoegd = false;
        foreach ($kolommen as $kol => $def) {
            $check = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='mail_templates' AND COLUMN_NAME=?");
            $check->execute([$dbn, $kol]);
            if (!$check->fetchColumn()) {
                $db->exec("ALTER TABLE `mail_templates` ADD COLUMN `$kol` $def");
                $kolommen_toegevoegd = true;
            }
        }

        // Seed default subtitels voor reminder templates als ze leeg zijn
        $seeds = [
            'reminder_blk'         => ['Vul je formulier nog snel in',  '#1A2540'],
            'reminder_sd_aankomst' => ['Selectiedag staat voor de deur', '#1A2540'],
            'reminder_sd_morgen'   => ['Tot morgen op het strand!',      '#E8420A'],
            'uitnodiging_selectie' => ['We zien je graag op de selectiedag', '#1A2540'],
            'uitnodiging_tw'       => ['Trainingsweekend staat voor de deur', '#1A2540'],
        ];
        $upd = $db->prepare("UPDATE mail_templates SET hero_subtitel=?, hero_kleur=? WHERE slug=? AND (hero_subtitel IS NULL OR hero_subtitel='')");
        foreach ($seeds as $slug => $waarden) {
            try { $upd->execute([$waarden[0], $waarden[1], $slug]); } catch (\Exception $e) {}
        }

        // Seed een volwaardige body voor de selectiedag-uitnodiging als die nog leeg is.
        // Zo bevat de mail meteen info over de dag + de melding dat er nog taken openstaan
        // om de deelname te bevestigen (het BLK-formulier invullen in het portaal).
        try {
            $chk_us = $db->prepare("SELECT body_html FROM mail_templates WHERE slug='uitnodiging_selectie'");
            $chk_us->execute();
            $rij_us = $chk_us->fetch();
            $body_leeg = !$rij_us || trim((string)($rij_us['body_html'] ?? '')) === '';
            if ($body_leeg) {
                $ubody = "<p>Hoi {{voornaam}},</p>\n"
                    . "<p>Goed nieuws — we nodigen je van harte uit voor de <strong>selectiedag</strong> van Reddingsbrigade Paal 13! Dit is jouw kans om te ontdekken hoe het is om strandwacht te zijn, en meteen onze kans om jou te leren kennen.</p>\n"
                    . "[info]<strong>De selectiedag op een rij</strong><br>\n"
                    . "📅 Wanneer: {{selectiedag_datum}}{{selectiedag_tijd}}<br>\n"
                    . "📍 Waar: {{selectiedag_locatie}}[/info]\n"
                    . "<p><strong>Bevestig je deelname</strong><br>\n"
                    . "Log in op je portaal en vul het \"Beter leren kennen\"-formulier in. Daarmee bevestig je meteen je deelname aan de selectiedag — zo weten wij dat je komt én leren we je alvast een beetje kennen. Er staan nog een of meer taken voor je klaar; je ziet ze zodra je inlogt.</p>\n"
                    . "[knop href=\"{{portaal_url}}\"]Naar mijn portaal[/knop]\n"
                    . "<p><strong>Wat kun je verwachten?</strong><br>\n"
                    . "Tijdens de selectiedag doe je een conditietest en een zwemtest, en hebben we een persoonlijk gesprek. Geen zorgen als je nog niet alles vloeiend kunt — we kijken vooral naar je inzet en enthousiasme.</p>\n"
                    . "<p><strong>Kom je met het openbaar vervoer?</strong><br>\n"
                    . "We bieden een pendeldienst aan vanaf station Beverwijk. Laat het ons even weten via sollicitatie@paal13.com of een berichtje naar 0224-581601, dan zorgen we dat je op tijd en zonder stress aankomt.</p>\n"
                    . "<p>Tot op de selectiedag!<br><strong>Team Sollicitaties</strong></p>";
                if ($rij_us) {
                    $db->prepare("UPDATE mail_templates SET body_html=? WHERE slug='uitnodiging_selectie'")->execute([$ubody]);
                } else {
                    $db->prepare("INSERT INTO mail_templates (slug, naam, beschrijving, onderwerp, body_html, hero_titel, hero_subtitel, hero_kleur)
                                  VALUES (?,?,?,?,?,?,?,?)")
                       ->execute([
                           'uitnodiging_selectie',
                           'Uitnodiging selectiedag',
                           'Wordt gestuurd als een sollicitant wordt uitgenodigd voor de selectiedag. Bevat de details en de oproep om het formulier in te vullen (= deelname bevestigen).',
                           'Je bent uitgenodigd voor de selectiedag — Paal 13',
                           $ubody,
                           'Je bent uitgenodigd!',
                           'We zien je graag op de selectiedag',
                           '#1A2540',
                       ]);
                }
            }
        } catch (\Exception $e) { error_log('seed uitnodiging_selectie: ' . $e->getMessage()); }

        // Seed de beschikbaarheid-deadline reminder als die nog niet bestaat
        $chk = $db->prepare("SELECT 1 FROM mail_templates WHERE slug='reminder_beschikbaarheid'");
        $chk->execute();
        if (!$chk->fetchColumn()) {
            $body = "<p>Hoi {{voornaam}},</p>\n"
                . "<p>Je hebt je beschikbaarheid voor het seizoen doorgegeven, maar deze staat nog op <strong>voorlopig</strong>. "
                . "We hebben je definitieve beschikbaarheid nodig om je te kunnen inplannen.</p>\n"
                . "[info]<strong>Deadline: {{deadline_datum}}</strong><br>Dat is uiterlijk 2 weken vóór het trainingsweekend.[/info]\n"
                . "<p>Het duurt maar een minuutje: open het portaal, controleer je gegevens en haal het vinkje \"voorlopig\" weg.</p>\n"
                . "[knop href=\"{{portaal_url}}\"]Beschikbaarheid definitief maken[/knop]\n"
                . "<p style=\"color:#5a6478;font-size:13px\">Klopt alles al en wil je niets wijzigen? Dan hoef je alleen het vinkje \"voorlopig\" weg te halen en op te slaan.</p>";
            try {
                $db->prepare("INSERT INTO mail_templates (slug, naam, beschrijving, onderwerp, body_html, hero_titel, hero_subtitel, hero_kleur)
                              VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([
                       'reminder_beschikbaarheid',
                       'Herinnering: beschikbaarheid definitief maken',
                       'Wordt automatisch gestuurd naar sollicitanten met voorlopige beschikbaarheid, vanaf 2 weken vóór de deadline.',
                       'Maak je beschikbaarheid definitief — Paal 13',
                       $body,
                       'Nog even je beschikbaarheid definitief maken',
                       'Bijna deadline!',
                       '#D43D00',
                   ]);
            } catch (\Exception $e) {}
        }
    } catch (\Exception $e) { error_log("mail_templates_auto_setup: " . $e->getMessage()); }
}

// Haal hero image URL op (configureerbaar via admin)
function mail_hero_image(): string {
    try {
        $s = db()->prepare("SELECT body_html FROM mail_templates WHERE slug='_hero_image'");
        $s->execute(); $r = $s->fetch();
        if ($r && !empty(trim($r['body_html']))) return trim($r['body_html']);
    } catch (Exception $e) {}
    // Default: mooie strandlifeguard foto (Unsplash, vrij te gebruiken)
    return 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1120&q=80&fm=jpg&fit=crop';
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
    ⚠️ ' . $m[1] . '
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

function mail_html_wrapper(
    string $body_html,
    string $onderwerp,
    string $hero_titel = '',
    string $hero_subtitel = '',
    ?string $hero_image = null,
    string $hero_kleur = ''
): string {
    // Nieuwe centrale template gebruiken als die beschikbaar is.
    if (function_exists('mail_thema_wrapper')) {
        return mail_thema_wrapper(
            mail_inline_styles($body_html),
            $onderwerp,
            $hero_titel,
            $hero_subtitel,
            // '#1A2540' was de oude standaard navy; leeg laten = thema-kleur gebruiken
            ($hero_kleur && $hero_kleur !== '#1A2540') ? $hero_kleur : '',
            $hero_image ?: ''
        );
    }

    // ── Terugval: oude ingebouwde opmaak (mocht het thema ontbreken) ──
    if (!$hero_titel) $hero_titel = $onderwerp;
    if (!$hero_kleur) $hero_kleur = '#1A2540';
    $body_html = mail_inline_styles($body_html);
    $logo_inst = function_exists('site_logo_url') ? site_logo_url() : null;
    $logo      = $logo_inst ?: (defined('APP_URL') ? APP_URL . '/assets/img/logo-mail.png' : '');
    $bestuur   = defined('MAIL_BESTUUR') ? MAIL_BESTUUR : '';
    $jaar      = date('Y');
    $ond       = htmlspecialchars($onderwerp, ENT_QUOTES, 'UTF-8');
    $titel_h   = htmlspecialchars($hero_titel, ENT_QUOTES, 'UTF-8');
    $sub_h     = htmlspecialchars($hero_subtitel, ENT_QUOTES, 'UTF-8');

    // Hero: navy basis met optionele achtergrond image
    $hero_bg_style = "background-color:{$hero_kleur}";
    if ($hero_image) {
        $img = htmlspecialchars($hero_image, ENT_QUOTES, 'UTF-8');
        $hero_bg_style .= ";background-image:url('{$img}');background-size:cover;background-position:center top";
    }

    return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="nl">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<title>' . $ond . '</title>
<style type="text/css">
@media only screen and (max-width:620px){
  .ec{width:100%!important;max-width:100%!important}
  .hero-text{font-size:24px!important;padding:0 12px!important}
  .bc{padding:24px 22px 28px!important}
  .pp{padding-left:22px!important;padding-right:22px!important}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;color:#1A2540">
<div style="display:none;max-height:0;overflow:hidden;font-size:1px;color:#f4f4f4">' . $ond . ' &zwnj;&zwnj;&zwnj;</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f4f4" style="background-color:#f4f4f4">
<tr><td align="center" style="padding:20px 0">

<table role="presentation" class="ec" width="600" cellpadding="0" cellspacing="0" border="0"
       style="width:600px;max-width:600px;background-color:#ffffff;border-radius:8px;border-collapse:separate"
       bgcolor="#ffffff">

  <!-- HERO -->
  <tr>
    <td valign="middle" align="center" bgcolor="' . htmlspecialchars($hero_kleur, ENT_QUOTES) . '"
        style="' . $hero_bg_style . ';padding:0;border-radius:8px 8px 0 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr><td align="center" style="padding:48px 32px 56px;' . ($hero_image ? 'background:rgba(20,30,60,0.55)' : '') . '">
          <img src="' . htmlspecialchars($logo, ENT_QUOTES) . '" alt="Paal 13" height="44"
               style="display:block;margin:0 auto 16px;border:0;outline:none;text-decoration:none;height:44px;filter:brightness(0) invert(1)">
          <h1 class="hero-text" style="margin:0;color:#ffffff;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:30px;font-weight:800;letter-spacing:-0.5px;line-height:1.2;text-align:center">
            ' . $titel_h . '
          </h1>
          ' . ($sub_h ? '<p style="margin:8px 0 0;color:rgba(255,255,255,0.9);font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;font-weight:400;line-height:1.4;text-align:center">' . $sub_h . '</p>' : '') . '
        </td></tr>
      </table>
    </td>
  </tr>

  <!-- Golfje onder hero -->
  <tr>
    <td style="padding:0;line-height:0;font-size:0">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 40" preserveAspectRatio="none" width="100%" height="40" style="display:block">
        <path d="M0,20 C100,40 200,0 300,20 C400,40 500,0 600,20 L600,40 L0,40 Z" fill="#ffffff"/>
      </svg>
    </td>
  </tr>

  <!-- BODY -->
  <tr>
    <td class="bc" bgcolor="#ffffff"
        style="background-color:#ffffff;padding:8px 36px 36px;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:15px;color:#1A2540;line-height:1.7">
      ' . $body_html . '
    </td>
  </tr>

  <!-- FOOTER binnen kaart -->
  <tr>
    <td bgcolor="#ffffff" align="center"
        style="background-color:#ffffff;padding:24px 36px 28px;border-top:1px solid #eeeeee;border-radius:0 0 8px 8px">
      <p style="margin:0 0 6px;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;color:#aaaaaa;line-height:1.5">
        Dit is een automatische mail. Voor vragen kun je mailen naar
        <a href="mailto:' . $bestuur . '" style="color:#aaaaaa;text-decoration:underline">' . $bestuur . '</a>
      </p>
      <p style="margin:0;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:11px;color:#c0c0c0;line-height:1">
        &copy; ' . $jaar . ' Reddingsbrigade Paal 13 &nbsp;&bull;&nbsp;
        <a href="https://paal13.com" style="color:#c0c0c0;text-decoration:none">paal13.com</a>
      </p>
    </td>
  </tr>

</table>

</td></tr>
</table>

</body>
</html>';
}


function mail_simple_wrapper(string $body_html, string $onderwerp, string $custom_image = ''): string {
    // Nieuwe centrale template gebruiken als die beschikbaar is.
    // Een eigen afbeelding wordt de fotohero.
    if (function_exists('mail_thema_wrapper')) {
        return mail_thema_wrapper(
            mail_inline_styles($body_html),
            $onderwerp,
            $onderwerp,
            '',
            '',
            $custom_image
        );
    }

    // ── Terugval: oude ingebouwde opmaak ──
    $logo    = defined('APP_URL') ? APP_URL . '/assets/img/logo-mail.png' : '';
    $bestuur = defined('MAIL_BESTUUR') ? MAIL_BESTUUR : '';
    $jaar    = date('Y');
    $ond     = htmlspecialchars($onderwerp, ENT_QUOTES, 'UTF-8');

    $hero_section = '';
    if ($custom_image) {
        // Met eigen afbeelding bovenaan (geen overlay, gewoon een banner)
        $hero_section = '
  <tr>
    <td style="padding:0">
      <img src="' . htmlspecialchars($custom_image, ENT_QUOTES, 'UTF-8') . '" alt="" width="560"
           style="display:block;width:100%;max-width:560px;height:auto">
    </td>
  </tr>';
    }

    return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="nl">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . $ond . '</title>
<style type="text/css">
body,#bt{margin:0;padding:0;background:#f3f4f6}
img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none}
p{margin:0 0 18px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.8}
a{color:#D43D00;text-decoration:none}
strong{color:#111827}
ul,ol{margin:0 0 18px;padding-left:22px}
li{font-family:Arial,sans-serif;font-size:15px;color:#374151;line-height:1.8;margin-bottom:4px}
h2{margin:0 0 10px;font-family:Arial,sans-serif;font-size:19px;font-weight:700;color:#111827}
h3{margin:0 0 8px;font-family:Arial,sans-serif;font-size:16px;font-weight:600;color:#111827}
</style>
</head>
<body style="margin:0;padding:0;background:#f3f4f6">
<div style="display:none;max-height:0;overflow:hidden;font-size:1px">' . $ond . ' &zwnj;&zwnj;</div>
<table id="bt" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr><td align="center" style="padding:32px 16px 48px">
<table width="560" cellpadding="0" cellspacing="0" border="0"
       style="border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08)">' . $hero_section . '
  <tr>
    <td align="center" style="background:#ffffff;padding:28px 40px 18px;border-bottom:3px solid #054d8a">
      <img src="' . $logo . '" alt="Paal 13" width="80" height="auto"
           style="display:block;margin:0 auto 14px;max-width:80px">
      <h1 style="margin:0;color:#111827;font-family:Arial,sans-serif;font-size:22px;font-weight:700;text-align:center">
        ' . $ond . '
      </h1>
      <p style="margin:6px 0 0;color:#9ca3af;font-family:Arial,sans-serif;font-size:12px;text-align:center">
        Reddingsbrigade Paal 13
      </p>
    </td>
  </tr>
  <tr>
    <td style="background:#ffffff;padding:28px 40px 36px;font-family:Arial,sans-serif;font-size:15px;color:#374151;line-height:1.8">
      ' . $body_html . '
    </td>
  </tr>
  <tr>
    <td align="center" style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:18px 40px 22px">
      <p style="margin:0 0 4px;font-family:Arial,sans-serif;font-size:12px;font-weight:600;color:#6b7280">Reddingsbrigade Paal 13</p>
      <p style="margin:0;font-family:Arial,sans-serif;font-size:11px;color:#9ca3af">
        <a href="mailto:' . $bestuur . '" style="color:#D43D00">' . $bestuur . '</a> &bull;
        <a href="https://paal13.com" style="color:#D43D00">paal13.com</a>
      </p>
      <p style="margin:8px 0 0;font-family:Arial,sans-serif;font-size:10px;color:#d1d5db">
        &copy; ' . $jaar . ' Reddingsbrigade Paal 13
      </p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>';
}

function stuur_template_mail(
    string $slug, string $naar, array $sol,
    array $seizoen = [], array $extra_vars = [], ?int $sol_id = null
): bool {
    $vars   = array_merge(mail_variabelen($sol, $seizoen), $extra_vars);
    $render = mail_template_render($slug, $vars);
    if (!$render) return false;
    $html = mail_html_wrapper(
        $render['body_html'],
        $render['onderwerp'],
        $render['hero_titel']    ?? '',
        $render['hero_subtitel'] ?? '',
        $render['hero_image']    ?? null,
        $render['hero_kleur']    ?? '#1A2540'
    );
    return Mailer::verstuur($naar, $sol['voornaam'] ?? '', $render['onderwerp'], $html, $sol_id);
}

function stuur_mail_html(string $naar, string $naam, string $onderwerp, string $html, ?int $sol_id = null, array $bijlagen = []): bool {
    return Mailer::verstuur($naar, $naam, $onderwerp, $html, $sol_id, $bijlagen);
}
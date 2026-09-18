<?php
/**
 * ════════════════════════════════════════════════════════════════════════
 *  PAAL 13 — CENTRALE MAIL-TEMPLATE
 * ════════════════════════════════════════════════════════════════════════
 *
 *  Alle uitgaande mails worden met deze ene template opgemaakt. Wil je de
 *  uitstraling van álle mails tegelijk aanpassen? Verander dan hieronder de
 *  instellingen in MAIL_THEMA. Je hoeft nergens anders iets te wijzigen.
 *
 *  De inhoud (teksten per mail) staat los hiervan, in de mailtemplates die
 *  je via het beheer kunt bewerken. Dit bestand gaat alleen over de OPMAAK:
 *  het logo, de kleuren, de hero, de footer — het "jasje" om elke mail.
 * ════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('mail_thema')) {
/**
 * De centrale opmaak-instellingen. Pas deze waarden aan om de uitstraling
 * van alle mails te veranderen. (Kan later ook uit de database komen.)
 */
function mail_thema(): array {
    static $thema = null;
    if ($thema !== null) return $thema;

    $app = defined('APP_URL') ? APP_URL : '';

    $thema = [
        // ── Merk ──────────────────────────────────────────────
        'naam'          => 'Reddingsbrigade Paal 13',
        'plaats'        => 'Callantsoog · Noord-Holland Noord',
        // Logo: MOET een PNG/JPG zijn (mailclients tonen geen SVG).
        'logo_url'      => $app . '/assets/img/logo-mail.png',
        'logo_breedte'  => 190,   // pixels

        // ── Kleuren ───────────────────────────────────────────
        'kleur_navy'    => '#054d8a',   // hoofdkleur (hero, accenten)
        'kleur_navy2'   => '#0a6ba8',   // lichtere navy (verloop in hero)
        'kleur_oranje'  => '#D43D00',   // knoppen en accenten
        'kleur_tekst'   => '#243447',   // bodytekst
        'kleur_tekst_zacht' => '#5a6b7d',
        'kleur_pagina'  => '#eef2f6',   // achtergrond rond de mail
        'kleur_kaart'   => '#ffffff',   // de mail zelf
        'kleur_footer'  => '#f7f9fb',

        // ── Hero ──────────────────────────────────────────────
        // 'kleur' = effen/verloop-hero. Zet 'foto_url' om een fotohero te gebruiken.
        'hero_stijl'    => 'kleur',     // 'kleur' of 'foto'
        'hero_foto_url' => '',          // bijv. $app.'/assets/img/mail-hero.jpg'
        'hero_badge'    => '',          // bijv. 'Selectie 2026' (leeg = geen badge)

        // ── Footer ────────────────────────────────────────────
        'footer_email'  => function_exists('bestuur_email') ? bestuur_email() : (defined('MAIL_BESTUUR') ? MAIL_BESTUUR : ''),
        'footer_website'=> 'paal13.com',
        'footer_website_url' => 'https://paal13.com',
    ];
    return $thema;
}
}

if (!function_exists('mail_thema_wrapper')) {
/**
 * Bouwt de volledige HTML-mail rond een stuk body-HTML.
 * Dit is de ENIGE plek waar de mail-opmaak wordt bepaald.
 *
 * @param string $body_html   De inhoud (al met inline-styles/shortcodes verwerkt)
 * @param string $onderwerp   Voor de <title> en preview-tekst
 * @param string $hero_titel  Grote titel in de hero (leeg = onderwerp)
 * @param string $hero_sub    Subtitel onder de hero-titel
 * @param string $hero_kleur  Overschrijf de hero-kleur (leeg = thema-navy)
 * @param string $hero_foto   Overschrijf met een fotohero (URL; leeg = thema)
 */
function mail_thema_wrapper(
    string $body_html,
    string $onderwerp,
    string $hero_titel = '',
    string $hero_sub   = '',
    string $hero_kleur = '',
    string $hero_foto  = ''
): string {
    $t = mail_thema();
    if ($hero_titel === '') $hero_titel = $onderwerp;

    $navy   = $hero_kleur ?: $t['kleur_navy'];
    $navy2  = $t['kleur_navy2'];
    $foto   = $hero_foto ?: ($t['hero_stijl'] === 'foto' ? $t['hero_foto_url'] : '');

    $ond    = htmlspecialchars($onderwerp, ENT_QUOTES, 'UTF-8');
    $titel  = htmlspecialchars($hero_titel, ENT_QUOTES, 'UTF-8');
    $sub    = htmlspecialchars($hero_sub, ENT_QUOTES, 'UTF-8');
    $logo   = htmlspecialchars($t['logo_url'], ENT_QUOTES, 'UTF-8');
    $badge  = htmlspecialchars($t['hero_badge'], ENT_QUOTES, 'UTF-8');

    // Hero-achtergrond: foto (met donkere overlay voor leesbaarheid) of kleurverloop
    if ($foto) {
        $hero_bg = "background-color:{$navy};background-image:linear-gradient(rgba(5,40,70,0.55),rgba(5,40,70,0.55)),url('" . htmlspecialchars($foto, ENT_QUOTES) . "');background-size:cover;background-position:center";
    } else {
        $hero_bg = "background-color:{$navy};background-image:linear-gradient(135deg,{$navy} 0%,{$navy2} 100%)";
    }

    $badge_html = $badge
        ? '<div style="margin:0 0 14px"><span style="display:inline-block;background:rgba(255,255,255,0.2);border-radius:100px;padding:5px 15px;color:#ffffff;font-size:12px;font-weight:600;letter-spacing:1.2px;text-transform:uppercase">' . $badge . '</span></div>'
        : '';

    $sub_html = $sub
        ? '<p style="margin:12px 0 0;color:rgba(255,255,255,0.92);font-size:16px;line-height:1.5;font-family:-apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif">' . $sub . '</p>'
        : '';

    $jaar    = date('Y');
    $email   = htmlspecialchars($t['footer_email'], ENT_QUOTES);
    $webnaam = htmlspecialchars($t['footer_website'], ENT_QUOTES);
    $webn_url= htmlspecialchars($t['footer_website_url'], ENT_QUOTES);
    $merk    = htmlspecialchars($t['naam'], ENT_QUOTES);
    $plaats  = htmlspecialchars($t['plaats'], ENT_QUOTES);
    $lw      = (int)$t['logo_breedte'];
    $ff      = "-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    return '<!DOCTYPE html>
<html lang="nl" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light only">
<title>' . $ond . '</title>
<style type="text/css">
@media only screen and (max-width:620px){
  .ec{width:100%!important;max-width:100%!important}
  .bc{padding:28px 24px 24px!important}
  .hero-pad{padding:36px 24px 40px!important}
  .hero-t{font-size:26px!important}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:' . $t['kleur_pagina'] . ';font-family:' . $ff . ';color:' . $t['kleur_tekst'] . '">
<div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:' . $t['kleur_pagina'] . '">' . $ond . ' &zwnj;&zwnj;&zwnj;&zwnj;</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . $t['kleur_pagina'] . '" style="background-color:' . $t['kleur_pagina'] . '">
<tr><td align="center" style="padding:28px 12px">

<table role="presentation" class="ec" width="600" cellpadding="0" cellspacing="0" border="0"
       style="width:600px;max-width:600px;background-color:' . $t['kleur_kaart'] . ';border-radius:14px;border-collapse:separate;overflow:hidden"
       bgcolor="' . $t['kleur_kaart'] . '">

  <!-- LOGO-BALK -->
  <tr><td align="center" bgcolor="' . $t['kleur_kaart'] . '" style="background-color:' . $t['kleur_kaart'] . ';padding:26px 32px 20px;border-bottom:1px solid ' . $t['kleur_pagina'] . '">
    <img src="' . $logo . '" alt="' . $merk . '" width="' . $lw . '" style="display:block;width:' . $lw . 'px;max-width:62%;height:auto;border:0;outline:none;text-decoration:none">
  </td></tr>

  <!-- HERO -->
  <tr><td align="center" valign="middle" bgcolor="' . $navy . '" style="' . $hero_bg . '">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
      <td class="hero-pad" align="center" style="padding:46px 40px 50px">
        ' . $badge_html . '
        <h1 class="hero-t" style="margin:0;color:#ffffff;font-family:' . $ff . ';font-size:32px;font-weight:800;letter-spacing:-0.5px;line-height:1.15;text-align:center">' . $titel . '</h1>
        ' . $sub_html . '
      </td>
    </tr></table>
  </td></tr>

  <!-- BODY -->
  <tr><td class="bc" bgcolor="' . $t['kleur_kaart'] . '" style="background-color:' . $t['kleur_kaart'] . ';padding:38px 44px 30px;font-family:' . $ff . ';font-size:16px;line-height:1.75;color:' . $t['kleur_tekst'] . '">
    ' . $body_html . '
  </td></tr>

  <!-- FOOTER -->
  <tr><td bgcolor="' . $t['kleur_footer'] . '" align="center" style="background-color:' . $t['kleur_footer'] . ';padding:26px 40px 30px;border-top:1px solid ' . $t['kleur_pagina'] . '">
    <p style="margin:0 0 8px;font-family:' . $ff . ';font-size:13px;color:' . $t['kleur_tekst_zacht'] . ';line-height:1.6">
      <strong style="color:' . $t['kleur_tekst'] . '">' . $merk . '</strong><br>' . $plaats . '
    </p>
    <p style="margin:0;font-family:' . $ff . ';font-size:12px;color:#94a3b3;line-height:1.6">
      Vragen? Mail naar <a href="mailto:' . $email . '" style="color:' . $t['kleur_navy'] . ';text-decoration:none;font-weight:600">' . $email . '</a><br>
      &copy; ' . $jaar . ' &bull; <a href="' . $webn_url . '" style="color:#94a3b3;text-decoration:underline">' . $webnaam . '</a>
    </p>
  </td></tr>

</table>

</td></tr>
</table>
</body>
</html>';
}
}

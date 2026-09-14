<?php
/**
 * Paal 13 — Notificatie-routering.
 *
 * Eén plek die bepaalt via welke kanalen (e-mail, SMS, WhatsApp) een bepaald type melding
 * wordt verstuurd. Per melding-type is dat instelbaar in het beheer. Zo kies je bijvoorbeeld:
 * "uitnodiging selectiedag" via e-mail én SMS, maar "aanmeldbevestiging" alleen e-mail.
 *
 * De e-mail zelf blijft lopen via de bestaande template-mails; deze laag voegt SMS/WA toe als
 * die voor het type zijn aangezet en de kanalen actief zijn.
 */

if (!function_exists('notificatie_typen')) {

/** De meldingstypen die via meerdere kanalen kunnen. Sleutel => leesbare naam. */
function notificatie_typen(): array {
    return [
        'portaal_code'       => 'Portaal-inlogcode',
        'aanmeldbevestiging' => 'Bevestiging van aanmelding',
        'uitnodiging_selectie' => 'Uitnodiging selectiedag',
        'herinnering_selectie' => 'Herinnering selectiedag',
        'uitnodiging_tw'     => 'Uitnodiging trainingsweekend',
        'status_update'      => 'Status-update sollicitatie',
        'taak_afgerond'      => 'Bevestiging taak afgerond',
        'ondertekening_herinnering' => 'Herinnering ondertekening',
        'algemeen'           => 'Algemene mededeling',
    ];
}

/**
 * Standaard-kanaal per meldingstype. De portaal-inlogcode staat standaard op e-mail
 * (dat is de betrouwbaarste bezorging); de rest ook, tenzij het beheer het aanpast.
 */
function notificatie_standaard(string $type): string {
    return 'email';
}

/**
 * Welke kanalen zijn ingesteld voor een meldingstype?
 * Retourneert bijv. ['email'=>true, 'sms'=>false, 'wa'=>false].
 * Standaard: alleen e-mail (veilig), tenzij anders ingesteld.
 */
function notificatie_kanalen(string $type): array {
    $default = 'email'; // veilige standaard
    $ruw = function_exists('instelling') ? instelling('notif.' . $type, $default) : $default;
    $delen = array_filter(array_map('trim', explode(',', $ruw)));
    return [
        'email' => in_array('email', $delen, true),
        'sms'   => in_array('sms', $delen, true),
        'wa'    => in_array('wa', $delen, true),
    ];
}

/**
 * Verstuur een melding via de ingestelde kanalen.
 *
 * @param string $type       een sleutel uit notificatie_typen()
 * @param array  $ontvanger  ['email'=>, 'telefoon'=>, 'naam'=>]
 * @param array  $inhoud     ['onderwerp'=>, 'email_body'=>, 'kort'=> (voor sms/wa)]
 * @param int|null $sol_id
 * @return array  welke kanalen zijn gebruikt en met welk resultaat
 */
function notificatie_verstuur(string $type, array $ontvanger, array $inhoud, ?int $sol_id = null): array {
    $kanalen = notificatie_kanalen($type);
    $resultaat = [];

    // E-mail (via de bestaande mail-laag)
    if ($kanalen['email'] && !empty($ontvanger['email']) && function_exists('stuur_mail')) {
        $ok = stuur_mail(
            $ontvanger['email'],
            $ontvanger['naam'] ?? '',
            $inhoud['onderwerp'] ?? 'Bericht van Paal 13',
            $inhoud['email_body'] ?? ($inhoud['kort'] ?? ''),
            $sol_id
        );
        $resultaat['email'] = $ok;
    }

    // Korte tekst voor SMS/WA
    $kort = $inhoud['kort'] ?? strip_tags($inhoud['email_body'] ?? '');
    $kort = trim(preg_replace('/\s+/', ' ', $kort));
    if (mb_strlen($kort) > 400) $kort = mb_substr($kort, 0, 397) . '...';

    // SMS
    if ($kanalen['sms'] && !empty($ontvanger['telefoon']) && function_exists('sms_verstuur') && sms_actief()) {
        $ok = sms_verstuur($ontvanger['telefoon'], $kort);
        $resultaat['sms'] = $ok;
        if (function_exists('audit_log')) audit_log('sms_verstuurd', 'sollicitatie', $sol_id, ['type' => $type, 'gelukt' => $ok]);
    }

    // WhatsApp
    if ($kanalen['wa'] && !empty($ontvanger['telefoon']) && function_exists('wa_verstuur') && wa_actief()) {
        $ok = wa_verstuur($ontvanger['telefoon'], $kort);
        $resultaat['wa'] = $ok;
        if (function_exists('audit_log')) audit_log('wa_verstuurd', 'sollicitatie', $sol_id, ['type' => $type, 'gelukt' => $ok]);
    }

    return $resultaat;
}

/**
 * Maak een passende korte tekst (voor SMS/WhatsApp) per meldingstype. Kort en functioneel;
 * met een link naar het portaal voor de details.
 */
function notificatie_korte_tekst(string $type, string $voornaam, array $seizoen = []): string {
    $portaal = defined('APP_URL') ? APP_URL . '/portaal/' : '';
    $sel_datum = '';
    if (!empty($seizoen['datum_selectiedag'])) {
        $ts = strtotime($seizoen['datum_selectiedag']);
        if ($ts) $sel_datum = date('d-m-Y', $ts);
    }
    switch ($type) {
        case 'uitnodiging_selectie':
            return "Hoi $voornaam, je bent uitgenodigd voor de selectiedag bij Paal 13" . ($sel_datum ? " op $sel_datum" : "") . ". Check je e-mail en portaal voor alle details: $portaal";
        case 'herinnering_selectie':
            return "Hoi $voornaam, denk je aan de selectiedag" . ($sel_datum ? " op $sel_datum" : "") . "? Details in je portaal: $portaal";
        case 'uitnodiging_tw':
            return "Hoi $voornaam, je bent uitgenodigd voor het trainingsweekend bij Paal 13. Details in je e-mail en portaal: $portaal";
        case 'status_update':
            return "Hoi $voornaam, er is een update over je sollicitatie bij Paal 13. Bekijk 'm in je portaal: $portaal";
        case 'ondertekening_herinnering':
            return "Hoi $voornaam, er staat nog een document klaar om te ondertekenen in je portaal: $portaal";
        default:
            return "Hoi $voornaam, er is een bericht voor je van Paal 13. Bekijk je portaal: $portaal";
    }
}

/**
 * Verstuur de portaal-inlogcode via de ingestelde kanalen.
 * E-mail is altijd de betrouwbare basis; SMS/WhatsApp worden toegevoegd als het beheer die
 * voor het type 'portaal_code' heeft aangezet én de kanalen actief zijn en er een nummer is.
 */
function portaal_code_versturen(string $email, string $voornaam, string $code, ?string $telefoon = null): void {
    $k = notificatie_kanalen('portaal_code');
    $mail_body = '<p>Jouw inlogcode voor het sollicitantenportaal:</p>'
        . '<div style="font-size:40px;font-weight:bold;letter-spacing:10px;color:#054d8a;text-align:center;background:#f0f4f8;border-radius:8px;padding:22px;margin:16px 0">' . htmlspecialchars($code) . '</div>'
        . '<p>Geldig 15 minuten.</p>';
    // E-mail (tenzij het beheer e-mail expliciet heeft uitgezet én een ander kanaal aan staat)
    if ($k['email'] && function_exists('stuur_mail')) {
        stuur_mail($email, $voornaam, 'Inlogcode Paal 13 portaal', $mail_body);
    }
    $kort = "Je inlogcode voor het Paal 13 portaal is: $code (geldig 15 minuten).";
    if ($k['sms'] && $telefoon && function_exists('sms_verstuur') && sms_actief()) {
        sms_verstuur($telefoon, $kort);
    }
    if ($k['wa'] && $telefoon && function_exists('wa_verstuur') && wa_actief()) {
        wa_verstuur($telefoon, $kort);
    }
    // Vangnet: als om welke reden dan ook geen enkel kanaal aanstond, tóch e-mailen.
    if (!$k['email'] && !$k['sms'] && !$k['wa'] && function_exists('stuur_mail')) {
        stuur_mail($email, $voornaam, 'Inlogcode Paal 13 portaal', $mail_body);
    }
}

}

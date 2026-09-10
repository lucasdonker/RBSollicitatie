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
        'aanmeldbevestiging' => 'Bevestiging van aanmelding',
        'uitnodiging_selectie' => 'Uitnodiging selectiedag',
        'herinnering_selectie' => 'Herinnering selectiedag',
        'uitnodiging_tw'     => 'Uitnodiging trainingsweekend',
        'status_update'      => 'Status-update sollicitatie',
        'ondertekening_herinnering' => 'Herinnering ondertekening',
        'algemeen'           => 'Algemene mededeling',
    ];
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

}

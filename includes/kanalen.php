<?php
/**
 * Paal 13 — Notificatiekanalen (SMS en WhatsApp).
 *
 * Naast e-mail kan het systeem meldingen ook via SMS en WhatsApp versturen. Elk kanaal is
 * apart in- en uitschakelbaar en instelbaar. Deze laag levert de verzendfuncties per kanaal;
 * de routering (welke melding via welk kanaal) zit in notificatie.php.
 *
 * SMS ondersteunt meerdere gateways, van goedkoop naar simpel:
 *   - 'eigen_telefoon' : je eigen Android-telefoon als gateway (bijv. via een app die een
 *                        webhook/HTTP-endpoint aanbiedt). Goedkoopst: alleen je eigen sim-bundel.
 *   - 'http_gateway'   : een generieke HTTP-gateway (VoIP-nummer of provider met simpele URL).
 *   - 'easysendsms' / 'messagebird' : klassieke providers (per bericht, pay-as-you-go).
 *
 * Alles faalt "zacht": een verzendprobleem mag nooit een pagina breken. Er wordt gelogd.
 */

if (!function_exists('sms_actief')) {

/** Is SMS ingeschakeld én geconfigureerd? */
function sms_actief(): bool {
    if (!function_exists('instelling')) return false;
    if (instelling('sms.actief', '0') !== '1') return false;
    $gw = instelling('sms.gateway', '');
    return $gw !== '';
}

/** Is WhatsApp (WhatsReach) ingeschakeld én geconfigureerd? */
function wa_actief(): bool {
    if (!function_exists('instelling')) return false;
    if (instelling('wa.actief', '0') !== '1') return false;
    return instelling('wa.api_sleutel', '') !== '';
}

/**
 * Normaliseer een telefoonnummer naar internationaal formaat (+31…).
 * Nederlandse nummers: 06… → +316…, 0031… → +31…
 */
function telefoon_normaliseer(string $tel): ?string {
    $tel = preg_replace('/[^0-9+]/', '', $tel);
    if ($tel === '') return null;
    if (strpos($tel, '+') === 0) return $tel;
    if (strpos($tel, '0031') === 0) return '+' . substr($tel, 2);
    if (strpos($tel, '31') === 0 && strlen($tel) >= 11) return '+' . $tel;
    if (strpos($tel, '0') === 0) return '+31' . substr($tel, 1);
    return null; // onherkenbaar
}

/**
 * Verstuur een SMS. Retourneert true bij (ogenschijnlijk) succes.
 * $tekst wordt kort gehouden; de aanroeper zorgt voor een zinnige, korte boodschap.
 */
function sms_verstuur(string $telefoon, string $tekst): bool {
    if (!sms_actief()) return false;
    $nummer = telefoon_normaliseer($telefoon);
    if (!$nummer) { error_log('sms_verstuur: onherkenbaar nummer'); return false; }

    $gateway = instelling('sms.gateway', '');
    try {
        switch ($gateway) {
            case 'eigen_telefoon':
            case 'http_gateway':
                return sms_via_http($nummer, $tekst);
            case 'easysendsms':
                return sms_via_easysendsms($nummer, $tekst);
            case 'messagebird':
                return sms_via_messagebird($nummer, $tekst);
            default:
                error_log('sms_verstuur: onbekende gateway ' . $gateway);
                return false;
        }
    } catch (\Throwable $e) {
        error_log('sms_verstuur (' . $gateway . '): ' . $e->getMessage());
        return false;
    }
}

/**
 * Generieke HTTP-gateway: geschikt voor een eigen Android-telefoon-app of een VoIP-nummer
 * met een simpel HTTP-endpoint. De URL kan {nummer} en {tekst} als placeholders bevatten;
 * anders worden ze als POST-parameters meegestuurd.
 */
function sms_via_http(string $nummer, string $tekst): bool {
    $url    = instelling('sms.http_url', '');
    $method = strtoupper(instelling('sms.http_method', 'POST'));
    $token  = instelling('sms.http_token', '');
    if ($url === '') return false;

    // Placeholders in de URL vervangen (voor eenvoudige gateways)
    $heeft_placeholder = strpos($url, '{nummer}') !== false || strpos($url, '{tekst}') !== false;
    $eind_url = str_replace(['{nummer}', '{tekst}'], [rawurlencode($nummer), rawurlencode($tekst)], $url);

    $ch = curl_init();
    $headers = ['Accept: application/json'];
    if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;

    if ($method === 'GET' || $heeft_placeholder) {
        curl_setopt($ch, CURLOPT_URL, $eind_url);
    } else {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['nummer' => $nummer, 'telefoon' => $nummer, 'tekst' => $tekst, 'bericht' => $tekst, 'message' => $tekst, 'to' => $nummer]));
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

/** EasySendSMS — eenvoudige pay-as-you-go provider. */
function sms_via_easysendsms(string $nummer, string $tekst): bool {
    $apikey = instelling('sms.easysendsms_key', '');
    $sender = instelling('sms.afzender', 'Paal13');
    if ($apikey === '') return false;
    $ch = curl_init('https://restapi.easysendsms.app/v1/rest/sms/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['apikey: ' . $apikey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['from' => $sender, 'to' => ltrim($nummer, '+'), 'text' => $tekst, 'type' => '0']),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

/** MessageBird/Bird — Nederlandse provider. */
function sms_via_messagebird(string $nummer, string $tekst): bool {
    $apikey = instelling('sms.messagebird_key', '');
    $sender = instelling('sms.afzender', 'Paal13');
    if ($apikey === '') return false;
    $ch = curl_init('https://rest.messagebird.com/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Authorization: AccessKey ' . $apikey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['originator' => $sender, 'recipients' => [$nummer], 'body' => $tekst]),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 201 + 1;
}

/**
 * Verstuur een WhatsApp-bericht via WhatsReach.
 *
 * LET OP: WhatsReach werkt buiten de officiële WhatsApp Business API om. Dat brengt een
 * reëel risico op blokkering van het gebruikte WhatsApp-nummer met zich mee. Dit kanaal is
 * daarom standaard uitgeschakeld en bedoeld als bewuste keuze. Zie de waarschuwing in het
 * instellingenscherm.
 */
function wa_verstuur(string $telefoon, string $tekst): bool {
    if (!wa_actief()) return false;
    $nummer = telefoon_normaliseer($telefoon);
    if (!$nummer) return false;

    $sleutel = instelling('wa.api_sleutel', '');
    $endpoint = instelling('wa.endpoint', 'https://api.whatsreach.co/v1/messages');
    try {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $sleutel, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode(['to' => ltrim($nummer, '+'), 'message' => $tekst, 'type' => 'text']),
            CURLOPT_TIMEOUT        => 12,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    } catch (\Throwable $e) {
        error_log('wa_verstuur: ' . $e->getMessage());
        return false;
    }
}

}

<?php
/**
 * Pagina's — formuliertoken.
 *
 * LET OP — dit is anti-bot, GEEN CSRF-bescherming.
 *
 * Een landingspagina start bewust geen sessie: bezoekers krijgen geen cookie en de pagina
 * is te cachen. Daarmee vervalt het gewone CSRF-token (csrf_check), dat een sessie nodig
 * heeft. Dit token vervangt dat niet: een gerichte aanvaller haalt zelf een token op.
 *
 * Dat is hier acceptabel, want er is geen sessie om te kapen en de handeling (een
 * aanmelding insturen) is publiek en anoniem. Wat het token wél doet:
 *  - eenvoudige bots weren die direct posten zonder de pagina te laden
 *  - meten hoe lang het invullen duurde (te snel = bot)
 *  - een token van pagina A onbruikbaar maken op pagina B
 *
 * Gebruik dit dus nooit op een plek waar een ingelogde gebruiker iets wijzigt: daar hoort
 * csrf_check().
 */

if (!function_exists('pag_form_geheim')) {

/**
 * Geheim voor de ondertekening. Eenmalig 32 random bytes in de instellingen (hetzelfde
 * patroon als kluis_zout). Bewust een eigen geheim: de kluissleutel mag nooit afhankelijk
 * raken van een publiek formulier.
 */
function pag_form_geheim(): string {
    static $geheim = null;
    if ($geheim !== null) return $geheim;

    $g = function_exists('instelling') ? instelling('paginas.form_geheim', '') : '';
    if ($g === '' && function_exists('instelling_opslaan')) {
        $g = bin2hex(random_bytes(32));
        try {
            instelling_opslaan('paginas.form_geheim', $g);
            if (function_exists('instellingen_cache_wissen')) instellingen_cache_wissen();
        } catch (\Throwable $e) { $g = ''; }
    }
    // Terugval als de instelling niet te schrijven is: een afgeleide van de kluissleutel.
    if ($g === '' && defined('KLUIS_SLEUTEL') && !str_contains((string)KLUIS_SLEUTEL, '{{')) {
        $g = hash_hmac('sha256', 'paal13-paginas-formulier-v1', (string)KLUIS_SLEUTEL);
    }
    $geheim = $g;
    return $geheim;
}

/** Token voor één formulierweergave: "<tijdstip>.<handtekening>". */
function pag_token_maak(string $bron_code): string {
    $ts = time();
    return $ts . '.' . pag_token_mac($ts, $bron_code);
}

function pag_token_mac(int $ts, string $bron_code): string {
    // Geen IP in de handtekening: op mobiel wisselt dat tijdens het invullen.
    return substr(hash_hmac('sha256', $ts . '|' . $bron_code . '|v1', pag_form_geheim()), 0, 32);
}

/**
 * Controleer het token. Retour: ['ok'=>bool, 'reden'=>string, 'seconden'=>int]
 * Redenen: ongeldig | te_snel | verlopen
 */
function pag_token_check(string $token, string $bron_code, int $min_sec = 4, int $max_sec = 7200): array {
    if (pag_form_geheim() === '') return ['ok' => true, 'reden' => '', 'seconden' => 0]; // faalt open
    $delen = explode('.', $token, 2);
    if (count($delen) !== 2 || !ctype_digit($delen[0])) return ['ok' => false, 'reden' => 'ongeldig', 'seconden' => 0];

    $ts = (int)$delen[0];
    if (!hash_equals(pag_token_mac($ts, $bron_code), $delen[1])) return ['ok' => false, 'reden' => 'ongeldig', 'seconden' => 0];

    $sec = time() - $ts;
    if ($sec < $min_sec) return ['ok' => false, 'reden' => 'te_snel',  'seconden' => $sec];
    if ($sec > $max_sec) return ['ok' => false, 'reden' => 'verlopen', 'seconden' => $sec];
    return ['ok' => true, 'reden' => '', 'seconden' => $sec];
}

}

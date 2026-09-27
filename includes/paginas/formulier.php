<?php
/**
 * Pagina's — het aanmeldformulier dat op de plek van [[formulier]] in het ontwerp komt.
 *
 * Bewust echte HTML in de pagina zelf en géén iframe: dan erft het formulier de opmaak van
 * het ontwerp, klopt de hoogte altijd, werkt het met toetsenbord en voorleessoftware, en
 * hoeft de bezoeker niet in een venster-in-een-venster te scrollen.
 *
 * De HTML zelf staat niet hier maar in includes/aanmeldformulier.php — hetzelfde formulier
 * als op /aanmelden.php. Dit bestand gaat alleen over wat er op een landingspagina anders is:
 * het anti-bot-token in plaats van CSRF (geen sessie), en het tellen van weigeringen.
 */

if (!function_exists('pag_formulier_html')) {

/**
 * De verwerking van een ingestuurd formulier.
 * Retour: ['ok'=>bool, 'fout'=>string, 'code'=>string] — bij ok volgt een redirect (PRG).
 * $campagne: al geschoond (pag_campagne_uit); leeg = geen campagne. Zie werving.php.
 */
function pag_formulier_verwerk(array $pagina, array $post, string $campagne = ''): array {
    $bron = (string)($pagina['bron_code'] ?: $pagina['slug']);
    $pid  = (int)$pagina['id'];

    // Tot welke stap iemand kwam; alleen voor de statistiek, nooit vertrouwd als invoer.
    $stap = (string)($post['af_stap'] ?? '');
    $stap = ctype_digit($stap) ? $stap : '';

    $weiger = function (string $reden, string $tekst) use ($pid, $bron): array {
        // Alleen het type en de bron vastleggen — geen IP, invoer of user-agent.
        pag_track('formulier_geweigerd', $pid, $bron, $reden);
        error_log('pag_formulier geweigerd (' . $reden . ') op pagina ' . $pid);
        return ['ok' => false, 'fout' => $tekst, 'code' => $reden];
    };

    // 1. Token (anti-bot, zie token.php)
    $t = pag_token_check((string)($post['p13_t'] ?? ''), $bron);
    if (!$t['ok']) {
        if ($t['reden'] === 'verlopen') {
            return $weiger('verlopen', 'Deze pagina stond te lang open. Controleer je gegevens en verstuur het formulier opnieuw.');
        }
        if ($t['reden'] === 'te_snel') {
            return $weiger('te_snel', 'Het formulier werd wel erg snel verstuurd. Probeer het nog een keer.');
        }
        return $weiger('ongeldig_token', 'Het formulier kon niet worden verwerkt. Ververs de pagina en probeer het opnieuw.');
    }

    // 2. Honeypot: een leeg veld dat mensen niet zien en bots wel invullen
    if (trim((string)($post['p13_w'] ?? '')) !== '') {
        return $weiger('honeypot', 'Het formulier kon niet worden verwerkt. Ververs de pagina en probeer het opnieuw.');
    }

    // 3. Snelheidsbegrenzing per IP
    if (function_exists('rate_limit') && !rate_limit('pag_aanmelding', rate_identiteit(), 5, 3600)) {
        return $weiger('rate_limit', 'Er zijn in korte tijd te veel aanmeldingen vanaf dit adres gedaan. Probeer het over een uur opnieuw.');
    }

    // 4. De echte aanmelding — één bron van waarheid.
    // aanmelden_kern.php hangt niet aan functions.php (alleen aanmelden.php en api/aanmelden.php
    // laadden hem), dus p.php kende sollicitatie_aanmaken() niet en liep hier stuk. Zelf laden:
    // het bestand is klein en beschermt zichzelf met function_exists.
    if (!function_exists('sollicitatie_aanmaken')) {
        $kern = dirname(__DIR__) . '/aanmelden_kern.php';
        if (is_file($kern)) require_once $kern;
    }
    if (!function_exists('sollicitatie_aanmaken')) {
        error_log('pag_formulier_verwerk: aanmelden_kern.php ontbreekt');
        return ['ok' => false, 'code' => 'kern_ontbreekt',
                'fout' => 'Aanmelden lukt op dit moment niet. Probeer het later opnieuw of mail ons.'];
    }

    // De paginabron blijft voorop staan (per-pagina-cijfers kloppen); de campagne komt erachter.
    $campagne = pag_campagne_schoon($campagne);
    $invoer = $post;
    unset($invoer['p13_campagne']);
    $invoer['bron_code']  = pag_bron_met_campagne($bron, $campagne);
    $invoer['bron_label'] = (string)$pagina['titel'] . ($campagne !== '' ? ' · ' . $campagne : '');
    $res = sollicitatie_aanmaken($invoer, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'via' => 'landingspagina']);
    if (!empty($res['ok'])) return ['ok' => true, 'fout' => '', 'code' => ''];

    // Een afgekeurde invoer is geen weigering door ons vangnet, maar wel het punt waar
    // iemand vastloopt. De foutcode zegt bij welk veld dat was.
    pag_track('formulier_geweigerd', $pid, $bron, (string)($res['code'] ?? 'invoer'));

    return ['ok' => false, 'fout' => (string)($res['fout'] ?? 'Aanmelden is niet gelukt.'), 'code' => (string)($res['code'] ?? '')];
}

/** Welk veld hoort bij een foutcode? Eén lijst, in includes/aanmeldformulier.php. */
function pag_fout_veld(string $code): string {
    return function_exists('aanmeld_fout_veld') ? aanmeld_fout_veld($code) : '';
}

/** Bericht als het seizoen dicht staat. */
function pag_gesloten_html(): string {
    return aanmeld_gesloten_html();
}

/** Bevestiging na een geslaagde aanmelding (na de redirect). */
function pag_succes_html(): string {
    return aanmeld_succes_html();
}

/**
 * Het formulier als HTML.
 * $staat: ['fout'=>string, 'code'=>string, 'waarden'=>array] — bij een fout blijven de
 * ingevulde gegevens staan en krijgt de bezoeker een melding bovenaan.
 * $campagne: uit het adres van dít verzoek (pag_campagne_uit), nooit uit een cache. Gaat mee
 * als verborgen veld p13_campagne, zodat hij de POST overleeft.
 */
function pag_formulier_html(array $pagina, array $staat = [], string $campagne = ''): string {
    $html = aanmeld_formulier_html([
        'bron_code' => (string)($pagina['bron_code'] ?: $pagina['slug']),
        'waarden'   => (array)($staat['waarden'] ?? []),
        'fout'      => (string)($staat['fout'] ?? ''),
        'code'      => (string)($staat['code'] ?? ''),
        'actie'     => '#aanmelden',
        'anti_bot'  => true,   // geen sessie op een landingspagina, dus geen csrf_check()
        'markeer'   => true,   // hidden p13_form=1, waar p.php op let
        'prefix'    => 'pf',
    ]);

    // Het gedeelde formulier (includes/aanmeldformulier.php) kent geen campagne; het veld komt
    // direct na de p13_form-markering. Geen markering (formulier dicht) = geen veld.
    $campagne = pag_campagne_schoon($campagne);
    $haak = '<input type="hidden" name="p13_form" value="1">';
    $pos = strpos($html, $haak);
    if ($campagne !== '' && $pos !== false) {
        $html = substr_replace($html, $haak . "\n        " . '<input type="hidden" name="p13_campagne" value="' . h($campagne) . '">', $pos, strlen($haak));
    }
    return $html;
}

}

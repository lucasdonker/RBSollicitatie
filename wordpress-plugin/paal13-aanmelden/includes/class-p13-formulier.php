<?php
/**
 * Het aanmeldformulier op de website: tonen en verwerken.
 *
 * De HTML komt uit het sollicitatiesysteem (/api/formulier.php) en wordt een paar minuten
 * bewaard. Deze klasse zet er per keer twee dingen in die niet gecachet mogen worden:
 * een WordPress-nonce en een tijdstempel. Daarna post het formulier naar de pagina waar
 * het staat — dus naar WordPress — en pas WordPress praat met de API.
 *
 * Waarom niet rechtstreeks vanuit de browser naar de API? Omdat daar de geheime sleutel
 * voor nodig is. Die zou dan in de broncode van de pagina staan en was daarmee geen
 * geheim meer.
 *
 * De volgorde bij het verwerken is die van het sollicitatiesysteem zelf:
 *   nonce → honeypot → te snel ingevuld → te vaak geprobeerd → de aanmelding zelf
 * Gaat er iets mis, dan wordt de pagina opnieuw getoond met de melding erboven (geen
 * redirect, zodat de invoer niet verdwijnt). Lukt het wel, dan volgt een redirect: zo
 * levert vernieuwen geen tweede aanmelding op.
 */

if (!defined('ABSPATH')) exit;

class P13_Formulier {

    /** Het resultaat van deze pagina-aanroep; gevuld door verwerken(), gelezen door toon(). */
    private static $uitkomst = null;

    public static function start(): void {
        add_action('template_redirect', [__CLASS__, 'verwerken']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets_registreren']);
    }

    // ── Opmaak en script ─────────────────────────────────────────────────

    /**
     * Wij leveren dezelfde stylesheet en hetzelfde script als het sollicitatiesysteem.
     * Staan ze niet in de plugin (iemand kopieerde de map zonder het bouwscript), dan halen
     * we ze van sollicitatie.paal13.com — dan ziet het formulier er in elk geval goed uit.
     */
    public static function assets_registreren(): void {
        $api = rtrim((string)P13_Api::instelling('api_url'), '/');

        if (!P13_Api::instelling('eigen_css')) {
            $css_lokaal = P13_PAD . 'assets/aanmeldformulier.css';
            $css = file_exists($css_lokaal)
                ? P13_URL . 'assets/aanmeldformulier.css'
                : $api . '/assets/css/aanmeldformulier.css';
            wp_register_style('paal13-aanmelden', $css, [], P13_VERSIE);
        }

        $js_lokaal = P13_PAD . 'assets/aanmeldformulier.js';
        $js = file_exists($js_lokaal)
            ? P13_URL . 'assets/aanmeldformulier.js'
            : $api . '/assets/js/aanmeldformulier.js';
        wp_register_script('paal13-aanmelden', $js, [], P13_VERSIE, true);
    }

    // ── Tonen ────────────────────────────────────────────────────────────

    /**
     * Het formulier als HTML.
     * $atts: bron, titel, knop — alle drie optioneel.
     */
    public static function toon(array $atts = []): string {
        if (!P13_Api::ingesteld()) {
            return self::beheerdersmelding(
                __('De koppeling met het sollicitatiesysteem is nog niet ingesteld. Ga naar Instellingen › Paal 13 Aanmelden.', 'paal13-aanmelden')
            );
        }

        if (!P13_Api::instelling('eigen_css')) wp_enqueue_style('paal13-aanmelden');
        wp_enqueue_script('paal13-aanmelden');

        // Deze pagina mag niet als kant-en-klare HTML worden bewaard door een cache-plugin.
        // In het formulier zit een nonce en een tijdstempel die per bezoeker verschillen;
        // uit een cache zijn die na een paar uur verlopen en weigert het formulier iedereen.
        // Alle bekende cache-plugins kijken naar deze constante.
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);

        $bron = sanitize_key($atts['bron'] ?? P13_Api::instelling('bron', 'website'));

        // Net aangemeld? Dan de bevestiging, niet nog een keer het formulier.
        $uit = self::$uitkomst;
        if (isset($_GET['p13']) && $_GET['p13'] === 'ok') {
            return self::succes();
        }

        $query = ['bron' => $bron];
        if (!empty($atts['titel'])) $query['titel'] = sanitize_text_field($atts['titel']);
        if (!empty($atts['knop']))  $query['knop']  = sanitize_text_field($atts['knop']);

        $antwoord = P13_Api::haal('formulier.php', $query);

        if (is_wp_error($antwoord) || empty($antwoord['html'])) {
            return self::noodmelding($antwoord);
        }

        $html = (string)$antwoord['html'];

        // De HTML komt uit ons eigen systeem en is daar al opgebouwd met htmlspecialchars().
        // Hier hoort dus geen tweede schoonmaakbeurt overheen: wp_kses zou de data-attributen
        // en het verborgen vangnetveld slopen waar het script en de botcontrole op rekenen.
        $html = self::velden_injecteren($html, $bron);

        if ($uit && !$uit['ok']) {
            $html = self::foutblok($uit['fout']) . $html;
        }

        return $html;
    }

    /** De bevestiging na een geslaagde aanmelding. */
    private static function succes(): string {
        $antwoord = P13_Api::haal('formulier.php', ['bron' => 'x']);
        if (!is_wp_error($antwoord) && !empty($antwoord['succes_html'])) {
            return (string)$antwoord['succes_html'];
        }
        return '<div class="af-kaart af-melding af-succes" id="aanmelden" role="status" tabindex="-1" data-formulier-succes>'
             . '<h2 class="af-titel">' . esc_html__('Je aanmelding is binnen', 'paal13-aanmelden') . '</h2>'
             . '<p>' . esc_html__('We hebben een bevestiging gestuurd naar je e-mailadres. Daarin staat een link naar je eigen portaal.', 'paal13-aanmelden') . '</p>'
             . '</div>';
    }

    /**
     * Onze eigen verborgen velden in het formulier zetten.
     * Die mogen niet uit de cache komen: een nonce is per bezoeker, en het tijdstempel
     * moet het moment zijn waarop dít formulier werd getoond.
     */
    private static function velden_injecteren(string $html, string $bron): string {
        $extra  = wp_nonce_field('p13_aanmelden', 'p13_nonce', true, false);
        $extra .= '<input type="hidden" name="p13_wp_t" value="' . esc_attr(self::tijdstempel()) . '">';
        $extra .= '<input type="hidden" name="p13_bron" value="' . esc_attr($bron) . '">';

        // Meteen ná de openingstag van het formulier, zodat de velden binnen het formulier staan.
        if (preg_match('/<form\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            return substr($html, 0, $pos) . $extra . substr($html, $pos);
        }

        P13_Api::log('formulier.php gaf HTML zonder <form>');
        return $html;
    }

    // ── Bot-afweer ───────────────────────────────────────────────────────

    /**
     * Tijdstempel met handtekening, om te zien hoe lang het invullen duurde. Een formulier
     * dat binnen vier seconden terugkomt is geen mens.
     * De handtekening gebruikt de zouten van WordPress; niemand kan er zelf een maken.
     */
    private static function tijdstempel(): string {
        $nu = time();
        return $nu . '.' . hash_hmac('sha256', (string)$nu, wp_salt('p13_aanmelden'));
    }

    /** Retour: '' als het goed is, anders de reden. */
    private static function tijdstempel_check(string $waarde): string {
        $delen = explode('.', $waarde, 2);
        if (count($delen) !== 2 || !ctype_digit($delen[0])) return 'ongeldig';
        if (!hash_equals(hash_hmac('sha256', $delen[0], wp_salt('p13_aanmelden')), $delen[1])) return 'ongeldig';

        $seconden = time() - (int)$delen[0];
        if ($seconden < 4)    return 'te_snel';
        if ($seconden > 7200) return 'verlopen';
        return '';
    }

    /** Hoeveel pogingen vanaf dit adres in het afgelopen uur? Max 5, net als elders. */
    private static function te_vaak(): bool {
        $ip = self::bezoeker_ip();
        if ($ip === '') return false;
        $sleutel = 'p13_rl_' . md5($ip);
        $aantal = (int)get_transient($sleutel);
        if ($aantal >= 5) return true;
        set_transient($sleutel, $aantal + 1, HOUR_IN_SECONDS);
        return false;
    }

    /**
     * Het adres van de bezoeker. Achter een proxy of CDN (Cloudflare) staat het echte
     * adres in een header; zonder dat zou iedereen hetzelfde adres hebben.
     */
    public static function bezoeker_ip(): string {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $sleutel) {
            if (empty($_SERVER[$sleutel])) continue;
            // X-Forwarded-For kan een rijtje zijn; de eerste is de bezoeker.
            $eerste = trim(explode(',', (string)$_SERVER[$sleutel])[0]);
            if (filter_var($eerste, FILTER_VALIDATE_IP)) return $eerste;
        }
        return '';
    }

    // ── Verwerken ────────────────────────────────────────────────────────

    public static function verwerken(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
        if (empty($_POST['p13_form'])) return;

        $mislukt = function (string $reden, string $tekst): void {
            P13_Api::log('aanmelding geweigerd (' . $reden . ')');
            self::$uitkomst = ['ok' => false, 'fout' => $tekst, 'code' => $reden];
        };

        // 1. Komt dit van ons eigen formulier?
        //
        //    LET OP — voor een bezoeker die niet is ingelogd is dit anti-bot, geen echte
        //    CSRF-bescherming: WordPress maakt voor anonieme bezoekers één nonce die voor
        //    iedereen hetzelfde is. Dat is hier goed genoeg, om dezelfde reden als bij de
        //    landingspagina's: er is geen sessie om te kapen en een aanmelding insturen is
        //    een publieke, anonieme handeling. Gebruik dit patroon nooit op een plek waar
        //    een ingelogde gebruiker iets wijzigt.
        if (!isset($_POST['p13_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['p13_nonce'])), 'p13_aanmelden')) {
            $mislukt('nonce', __('Deze pagina stond te lang open. Ververs hem en probeer het opnieuw.', 'paal13-aanmelden'));
            return;
        }

        // 2. Het veld dat mensen niet zien en bots wel invullen.
        if (trim((string)($_POST['p13_w'] ?? '')) !== '') {
            $mislukt('honeypot', __('Het formulier kon niet worden verwerkt. Ververs de pagina en probeer het opnieuw.', 'paal13-aanmelden'));
            return;
        }

        // 3. Hoe lang duurde het invullen?
        $tijd = self::tijdstempel_check(sanitize_text_field(wp_unslash($_POST['p13_wp_t'] ?? '')));
        if ($tijd === 'te_snel') {
            $mislukt('te_snel', __('Het formulier werd wel erg snel verstuurd. Probeer het nog een keer.', 'paal13-aanmelden'));
            return;
        }
        if ($tijd !== '') {
            $mislukt($tijd, __('Deze pagina stond te lang open. Controleer je gegevens en verstuur het formulier opnieuw.', 'paal13-aanmelden'));
            return;
        }

        // 4. Te vaak geprobeerd vanaf dit adres?
        if (self::te_vaak()) {
            $mislukt('te_vaak', __('Er zijn in korte tijd te veel aanmeldingen vanaf dit adres gedaan. Probeer het over een uur opnieuw.', 'paal13-aanmelden'));
            return;
        }

        // 5. De aanmelding zelf. Het adres van de bezoeker gaat mee: zonder dat legt het
        //    sollicitatiesysteem bij de privacytoestemming het adres van déze webserver
        //    vast, en telt iedere aanmelding mee in dezelfde limiet.
        $antwoord = P13_Api::stuur('aanmelden.php', self::invoer(), [
            'X-Paal13-Bezoeker' => self::bezoeker_ip(),
        ]);

        if (is_wp_error($antwoord)) {
            self::$uitkomst = ['ok' => false, 'fout' => $antwoord->get_error_message(),
                               'code' => (string)($antwoord->get_error_data()['code'] ?? '')];
            return;
        }

        // Gelukt: doorsturen, zodat vernieuwen geen tweede aanmelding oplevert.
        $doel = add_query_arg('p13', 'ok', self::huidige_url()) . '#aanmelden';
        wp_safe_redirect($doel, 303);
        exit;
    }

    /** De ingevulde velden, schoongemaakt. Alleen wat het sollicitatiesysteem kent. */
    private static function invoer(): array {
        $tekst = static function (string $naam): string {
            return isset($_POST[$naam]) ? sanitize_text_field(wp_unslash((string)$_POST[$naam])) : '';
        };

        return [
            'voornaam'               => $tekst('voornaam'),
            'tussenvoegsel'          => $tekst('tussenvoegsel'),
            'achternaam'             => $tekst('achternaam'),
            'email'                  => isset($_POST['email']) ? sanitize_email(wp_unslash((string)$_POST['email'])) : '',
            'telefoon'               => $tekst('telefoon'),
            'geboortedatum'          => $tekst('geboortedatum'),
            'geslacht'               => in_array($tekst('geslacht'), ['man', 'vrouw', 'anders', 'onbekend'], true) ? $tekst('geslacht') : 'onbekend',
            'akkoord'                => !empty($_POST['akkoord']) ? 1 : 0,
            'ouder_akkoord'          => !empty($_POST['ouder_akkoord']) ? 1 : 0,
            'ouder_toestemming_naam' => $tekst('ouder_toestemming_naam'),
            'bron_code'              => sanitize_key($tekst('p13_bron') ?: (string)P13_Api::instelling('bron', 'website')),
            'bron_label'             => get_bloginfo('name'),
        ];
    }

    /**
     * Het adres van de pagina waar het formulier staat.
     * Opgebouwd uit wat WordPress zelf weet, niet uit de door de bezoeker meegestuurde
     * host-header — anders stuur je iemand na een geslaagde aanmelding zo naar een
     * website van een ander.
     */
    private static function huidige_url(): string {
        $id = get_queried_object_id();
        if ($id) {
            $link = get_permalink($id);
            if ($link) return $link;
        }
        return home_url('/');
    }

    // ── Meldingen ────────────────────────────────────────────────────────

    private static function foutblok(string $tekst): string {
        return '<div class="af-kaart af-melding af-foutkaart" role="alert" tabindex="-1" data-formulier-fout>'
             . '<strong>' . esc_html__('Je aanmelding is nog niet verstuurd.', 'paal13-aanmelden') . '</strong>'
             . '<p>' . esc_html($tekst) . '</p>'
             . '</div>';
    }

    /** De API doet het niet. Bezoeker krijgt een uitweg, beheerder de reden. */
    private static function noodmelding($antwoord): string {
        $api = rtrim((string)P13_Api::instelling('api_url'), '/');
        if (is_wp_error($antwoord)) P13_Api::log('formulier ophalen: ' . $antwoord->get_error_message());

        $uit = '<div class="af-kaart af-melding af-gesloten" id="aanmelden" role="status">'
             . '<h2 class="af-titel">' . esc_html__('Aanmelden lukt hier even niet', 'paal13-aanmelden') . '</h2>'
             . '<p>' . sprintf(
                 /* translators: %s is een link naar het aanmeldformulier op het sollicitatiesysteem. */
                 esc_html__('Je kunt je wel aanmelden via %s.', 'paal13-aanmelden'),
                 '<a href="' . esc_url($api . '/aanmelden.php') . '">' . esc_html__('dit formulier', 'paal13-aanmelden') . '</a>'
               ) . '</p></div>';

        if (current_user_can('manage_options') && is_wp_error($antwoord)) {
            $uit .= self::beheerdersmelding($antwoord->get_error_message());
        }
        return $uit;
    }

    /** Alleen zichtbaar voor ingelogde beheerders. */
    private static function beheerdersmelding(string $tekst): string {
        if (!current_user_can('manage_options')) return '';
        return '<div class="af-kaart af-melding af-gesloten"><p><strong>'
             . esc_html__('Alleen zichtbaar voor beheerders:', 'paal13-aanmelden') . '</strong> '
             . esc_html($tekst) . '</p></div>';
    }
}

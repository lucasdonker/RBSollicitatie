<?php
/**
 * De shortcodes die je op een pagina of in een bericht zet.
 *
 *   [paal13_aanmeldformulier]                       het formulier
 *   [paal13_aanmeldformulier bron="flyer-2027"]     hetzelfde, met een eigen herkomst
 *   [paal13_seizoen veld="datum_selectiedag"]       één seizoenswaarde
 *   [paal13_open]…[/paal13_open]                    alleen tonen als de aanmelding openstaat
 *   [paal13_gesloten]…[/paal13_gesloten]            alleen tonen als hij dicht is
 *   [paal13_aanmeldknop]                            een knop naar de pagina met het formulier
 *
 * Voor wie liever niet met shortcodes werkt: {{seizoen:datum_selectiedag}} in een gewone
 * tekst doet hetzelfde als [paal13_seizoen veld="datum_selectiedag"].
 */

if (!defined('ABSPATH')) exit;

class P13_Shortcodes {

    public static function start(): void {
        add_shortcode('paal13_aanmeldformulier', [__CLASS__, 'formulier']);
        add_shortcode('paal13_aanmelden',        [__CLASS__, 'formulier']);   // kortere naam
        add_shortcode('paal13_seizoen',          [__CLASS__, 'seizoen']);
        add_shortcode('paal13_open',             [__CLASS__, 'indien_open']);
        add_shortcode('paal13_gesloten',         [__CLASS__, 'indien_gesloten']);
        add_shortcode('paal13_aanmeldknop',      [__CLASS__, 'knop']);

        // {{seizoen:veld}} en {{seizoen:veld|formaat}} in gewone tekst.
        add_filter('the_content', [__CLASS__, 'tekst_variabelen'], 9);
        add_filter('widget_text_content', [__CLASS__, 'tekst_variabelen'], 9);
    }

    /** [paal13_aanmeldformulier bron="" titel="" knop=""] */
    public static function formulier($atts): string {
        $a = shortcode_atts([
            'bron'  => '',
            'titel' => '',
            'knop'  => '',
        ], $atts, 'paal13_aanmeldformulier');

        return P13_Formulier::toon($a);
    }

    /**
     * [paal13_seizoen veld="datum_selectiedag" formaat="lang" leeg="nog niet bekend"]
     * formaat: lang | kort | dag | dagnaam | maand | jaar | ruw
     */
    public static function seizoen($atts): string {
        $a = shortcode_atts([
            'veld'    => 'naam',
            'formaat' => '',
            'leeg'    => '',
        ], $atts, 'paal13_seizoen');

        $veld = sanitize_key($a['veld']);
        if (!isset(P13_Seizoen::velden()[$veld])) {
            // Een typefout moet zichtbaar zijn voor wie de pagina maakt, niet stilletjes
            // een lege plek opleveren waar niemand het ooit vindt.
            return current_user_can('edit_pages')
                ? '<mark>' . esc_html(sprintf(
                    /* translators: %s is de naam van een onbekend seizoensveld. */
                    __('Onbekend seizoensveld: %s', 'paal13-aanmelden'), $veld)) . '</mark>'
                : esc_html($a['leeg']);
        }

        return esc_html(P13_Seizoen::waarde($veld, sanitize_key($a['formaat']), $a['leeg']));
    }

    /** [paal13_open]Meld je nu aan![/paal13_open] */
    public static function indien_open($atts, $inhoud = ''): string {
        return P13_Seizoen::open() ? do_shortcode((string)$inhoud) : '';
    }

    /** [paal13_gesloten]De inschrijving is gesloten.[/paal13_gesloten] */
    public static function indien_gesloten($atts, $inhoud = ''): string {
        return P13_Seizoen::open() ? '' : do_shortcode((string)$inhoud);
    }

    /** [paal13_aanmeldknop url="/aanmelden/" tekst="Meld je aan"] */
    public static function knop($atts): string {
        $a = shortcode_atts([
            'url'   => '',
            'tekst' => __('Meld je aan', 'paal13-aanmelden'),
            'altijd' => '0',   // 1 = ook tonen als de aanmelding dicht is
        ], $atts, 'paal13_aanmeldknop');

        if ($a['altijd'] !== '1' && !P13_Seizoen::open()) return '';

        $url = $a['url'] !== '' ? $a['url'] : rtrim((string)P13_Api::instelling('api_url'), '/') . '/aanmelden.php';

        if (!P13_Api::instelling('eigen_css')) wp_enqueue_style('paal13-aanmelden');

        return '<p class="af-knopregel"><a class="af-knop af-knop-link" href="' . esc_url($url) . '">'
             . esc_html($a['tekst']) . '</a></p>';
    }

    /**
     * {{seizoen:datum_selectiedag}} of {{seizoen:datum_selectiedag|dag}} in gewone tekst.
     * Handig in een kop of een knoptekst, waar een shortcode niet altijd werkt.
     */
    public static function tekst_variabelen(string $inhoud): string {
        if (strpos($inhoud, '{{seizoen:') === false) return $inhoud;

        return (string)preg_replace_callback(
            '/\{\{seizoen:([a-z0-9_]{1,40})(?:\|([a-z]{1,10}))?\}\}/',
            static function (array $m): string {
                $veld = $m[1];
                if (!isset(P13_Seizoen::velden()[$veld])) return $m[0];
                return esc_html(P13_Seizoen::waarde($veld, $m[2] ?? ''));
            },
            $inhoud
        );
    }
}

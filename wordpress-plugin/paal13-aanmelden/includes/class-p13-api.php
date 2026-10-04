<?php
/**
 * Praten met sollicitatie.paal13.com.
 *
 * Eén plek voor het adres, de sleutel, de cache en het afhandelen van fouten. Alle andere
 * bestanden vragen hier om gegevens en hoeven niets te weten van HTTP.
 *
 * De cache heeft twee lagen, en dat is met opzet:
 *  - een transient met een korte houdbaarheid (standaard 5 minuten) — dat is de normale weg
 *  - een option met het laatste geslaagde antwoord, zonder houdbaarheid
 * Is de API even onbereikbaar (onderhoud, netwerkstoring), dan valt de plugin terug op die
 * tweede laag. De bezoeker ziet dan iets wat een paar uur oud kan zijn in plaats van een
 * foutmelding — voor een pagina met seizoensdatums is dat altijd de betere uitkomst.
 */

if (!defined('ABSPATH')) exit;

class P13_Api {

    const OPTIE       = 'p13_instellingen';
    const CACHE_VOOR  = 'p13_cache_';
    const LAATSTE     = 'p13_laatst_goed_';

    /** De instellingen, met hun standaardwaarden. */
    public static function instellingen(): array {
        $opgeslagen = get_option(self::OPTIE, []);
        if (!is_array($opgeslagen)) $opgeslagen = [];
        return array_merge([
            'api_url'     => 'https://sollicitatie.paal13.com',
            'api_sleutel' => '',
            'cache_min'   => 5,
            'bron'        => 'website',
            'eigen_css'   => 0,   // 1 = onze stylesheet niet laden, je stylet zelf
        ], $opgeslagen);
    }

    public static function instelling(string $sleutel, $standaard = '') {
        $alles = self::instellingen();
        return $alles[$sleutel] ?? $standaard;
    }

    /** Is de plugin klaar voor gebruik? */
    public static function ingesteld(): bool {
        $i = self::instellingen();
        return $i['api_sleutel'] !== '' && $i['api_url'] !== '';
    }

    /** Het volledige adres van een endpoint. */
    private static function adres(string $pad, array $query = []): string {
        $basis = rtrim((string)self::instelling('api_url'), '/');
        $url = $basis . '/api/' . ltrim($pad, '/');
        return $query ? add_query_arg($query, $url) : $url;
    }

    /**
     * Een GET met cache.
     * Retour: het antwoord als array, of een WP_Error.
     */
    public static function haal(string $pad, array $query = [], bool $vers = false) {
        $sleutel = md5($pad . '|' . wp_json_encode($query) . '|' . self::instelling('api_url'));

        if (!$vers) {
            $uit_cache = get_transient(self::CACHE_VOOR . $sleutel);
            if (is_array($uit_cache)) return $uit_cache;
        }

        $antwoord = self::verzoek('GET', $pad, $query);

        if (is_wp_error($antwoord)) {
            // Terugvallen op het laatste antwoord dat wél goed was.
            $laatste = get_option(self::LAATSTE . $sleutel);
            if (is_array($laatste) && isset($laatste['data'])) {
                $laatste['data']['_uit_noodvoorraad'] = true;
                $laatste['data']['_opgehaald_op'] = $laatste['op'] ?? '';
                return $laatste['data'];
            }
            return $antwoord;
        }

        $minuten = max(0, (int)self::instelling('cache_min', 5));
        if ($minuten > 0) set_transient(self::CACHE_VOOR . $sleutel, $antwoord, $minuten * MINUTE_IN_SECONDS);
        update_option(self::LAATSTE . $sleutel, ['data' => $antwoord, 'op' => current_time('mysql')], false);

        return $antwoord;
    }

    /** Een POST. Nooit gecachet — dit verandert iets. */
    public static function stuur(string $pad, array $gegevens, array $headers = []) {
        return self::verzoek('POST', $pad, [], $gegevens, $headers);
    }

    /** Het echte HTTP-verzoek. */
    private static function verzoek(string $methode, string $pad, array $query = [], array $body = [], array $headers = []) {
        if (!self::ingesteld()) {
            return new WP_Error('p13_niet_ingesteld',
                __('De koppeling met het sollicitatiesysteem is nog niet ingesteld.', 'paal13-aanmelden'));
        }

        $argumenten = [
            'method'    => $methode,
            'timeout'   => $methode === 'POST' ? 15 : 8,
            'headers'   => [
                'X-Paal13-Key' => (string)self::instelling('api_sleutel'),
                'Accept'       => 'application/json',
            ],
            'user-agent' => 'Paal13-WP/' . P13_VERSIE . '; ' . home_url('/'),
        ];
        foreach ($headers as $naam => $waarde) {
            if ($waarde !== '') $argumenten['headers'][$naam] = $waarde;
        }
        if ($methode === 'POST') {
            $argumenten['headers']['Content-Type'] = 'application/json; charset=utf-8';
            $argumenten['body'] = wp_json_encode($body);
        }

        $res = wp_remote_request(self::adres($pad, $query), $argumenten);

        if (is_wp_error($res)) {
            self::log($pad . ': ' . $res->get_error_message());
            return new WP_Error('p13_onbereikbaar',
                __('Het sollicitatiesysteem is even niet bereikbaar. Probeer het zo nog eens.', 'paal13-aanmelden'));
        }

        $status = (int)wp_remote_retrieve_response_code($res);
        $data   = json_decode((string)wp_remote_retrieve_body($res), true);

        if (!is_array($data)) {
            self::log($pad . ': geen geldig antwoord (status ' . $status . ')');
            return new WP_Error('p13_kapot_antwoord',
                __('Het sollicitatiesysteem gaf een onverwacht antwoord.', 'paal13-aanmelden'));
        }

        // 401/503 betekent dat de sleutel niet klopt of nog niet is ingesteld — dat is iets
        // voor de beheerder, niet iets waar de bezoeker een technische tekst van hoort.
        if ($status === 401 || $status === 503) {
            self::log($pad . ': sleutel geweigerd of API niet ingesteld (status ' . $status . ')');
            return new WP_Error('p13_sleutel',
                __('De koppeling met het sollicitatiesysteem werkt niet. Neem contact op met het bestuur.', 'paal13-aanmelden'),
                ['status' => $status]);
        }

        if ($status >= 400 && empty($data['ok'])) {
            // Een inhoudelijke afwijzing (al aangemeld, te jong) hoort bij de aanroeper,
            // die er een nette melding van maakt. Geven we hier gewoon door.
            return new WP_Error('p13_afgewezen',
                (string)($data['fout'] ?? __('Aanmelden is niet gelukt.', 'paal13-aanmelden')),
                ['status' => $status, 'code' => (string)($data['code'] ?? '')]);
        }

        return $data;
    }

    /** Alles wat we bewaren weggooien. */
    public static function cache_leegmaken(): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_' . self::CACHE_VOOR) . '%',
            $wpdb->esc_like('_transient_timeout_' . self::CACHE_VOOR) . '%',
            $wpdb->esc_like(self::LAATSTE) . '%'
        ));
    }

    /** Fouten in het WordPress-foutenlog, nooit op het scherm. */
    public static function log(string $bericht): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Paal 13] ' . $bericht);
        }
    }
}

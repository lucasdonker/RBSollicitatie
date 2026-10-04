<?php
/**
 * De instellingenpagina in WordPress: Instellingen › Paal 13 Aanmelden.
 *
 * Drie dingen: de koppeling instellen, controleren of hij werkt, en een overzicht van alle
 * shortcodes met de waarden die er nú uit komen. Dat laatste is bewust: wie een pagina
 * maakt, wil zien wat er op de site komt te staan, niet alleen hoe de shortcode heet.
 */

if (!defined('ABSPATH')) exit;

class P13_Admin {

    public static function start(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'instellingen_registreren']);
    }

    public static function menu(): void {
        add_options_page(
            __('Paal 13 Aanmelden', 'paal13-aanmelden'),
            __('Paal 13 Aanmelden', 'paal13-aanmelden'),
            'manage_options',
            'paal13-aanmelden',
            [__CLASS__, 'pagina']
        );
    }

    public static function instellingen_registreren(): void {
        register_setting('p13_groep', P13_Api::OPTIE, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'opschonen'],
            'default'           => [],
        ]);
    }

    /** Wat er binnenkomt van het formulier, schoongemaakt. */
    public static function opschonen($invoer): array {
        $in = is_array($invoer) ? $invoer : [];
        $nu = P13_Api::instellingen();

        $url = esc_url_raw(trim((string)($in['api_url'] ?? '')));
        // Zonder https zou de sleutel leesbaar over de lijn gaan.
        if ($url !== '' && strpos($url, 'https://') !== 0) {
            add_settings_error('p13_groep', 'p13_url', __('Het adres van het sollicitatiesysteem moet met https:// beginnen.', 'paal13-aanmelden'));
            $url = $nu['api_url'];
        }

        // Een leeg sleutelveld betekent "niet wijzigen": zo hoeft niemand hem opnieuw te
        // plakken bij elke kleine aanpassing, en staat hij nooit voluit op het scherm.
        $sleutel = trim((string)($in['api_sleutel'] ?? ''));
        if ($sleutel === '') $sleutel = (string)$nu['api_sleutel'];

        $schoon = [
            'api_url'     => $url ?: $nu['api_url'],
            'api_sleutel' => sanitize_text_field($sleutel),
            'cache_min'   => max(0, min(60, (int)($in['cache_min'] ?? 5))),
            'bron'        => sanitize_key($in['bron'] ?? 'website') ?: 'website',
            'eigen_css'   => !empty($in['eigen_css']) ? 1 : 0,
        ];

        // Instellingen gewijzigd? Dan is wat we bewaard hebben niet meer te vertrouwen.
        P13_Api::cache_leegmaken();

        return $schoon;
    }

    public static function pagina(): void {
        if (!current_user_can('manage_options')) return;

        $i = P13_Api::instellingen();

        // Verbinding testen
        $test = null;
        if (isset($_GET['p13_test']) && check_admin_referer('p13_test')) {
            $test = P13_Seizoen::gegevens(true);
        }
        ?>
        <div class="wrap">
          <h1><?php esc_html_e('Paal 13 — Aanmelden', 'paal13-aanmelden'); ?></h1>
          <p class="description">
            <?php esc_html_e('Deze plugin haalt het aanmeldformulier en de seizoensgegevens op bij het sollicitatiesysteem. Je hoeft hier niets bij te werken als daar iets verandert.', 'paal13-aanmelden'); ?>
          </p>

          <?php if ($test !== null) self::testuitslag($test); ?>

          <form method="post" action="options.php">
            <?php settings_fields('p13_groep'); ?>
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><label for="p13_api_url"><?php esc_html_e('Adres sollicitatiesysteem', 'paal13-aanmelden'); ?></label></th>
                <td>
                  <input type="url" id="p13_api_url" class="regular-text"
                         name="<?php echo esc_attr(P13_Api::OPTIE); ?>[api_url]"
                         value="<?php echo esc_attr($i['api_url']); ?>" placeholder="https://sollicitatie.paal13.com">
                  <p class="description"><?php esc_html_e('Zonder /api erachter. Moet met https:// beginnen.', 'paal13-aanmelden'); ?></p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="p13_sleutel"><?php esc_html_e('API-sleutel', 'paal13-aanmelden'); ?></label></th>
                <td>
                  <input type="password" id="p13_sleutel" class="regular-text" autocomplete="new-password"
                         name="<?php echo esc_attr(P13_Api::OPTIE); ?>[api_sleutel]" value=""
                         placeholder="<?php echo $i['api_sleutel'] !== ''
                            ? esc_attr__('••••••••  (laat leeg om te behouden)', 'paal13-aanmelden')
                            : esc_attr__('plak hier de sleutel', 'paal13-aanmelden'); ?>">
                  <p class="description">
                    <?php esc_html_e('Te vinden in het beheer van het sollicitatiesysteem bij Instellingen › Website & API. Laat dit veld leeg om de huidige sleutel te behouden.', 'paal13-aanmelden'); ?>
                  </p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="p13_bron"><?php esc_html_e('Herkomst', 'paal13-aanmelden'); ?></label></th>
                <td>
                  <input type="text" id="p13_bron" class="regular-text"
                         name="<?php echo esc_attr(P13_Api::OPTIE); ?>[bron]"
                         value="<?php echo esc_attr($i['bron']); ?>">
                  <p class="description"><?php esc_html_e('Komt bij elke aanmelding te staan, zodat het bestuur ziet waar iemand vandaan kwam. Per pagina te overschrijven met bron="…" in de shortcode.', 'paal13-aanmelden'); ?></p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="p13_cache"><?php esc_html_e('Gegevens bewaren', 'paal13-aanmelden'); ?></label></th>
                <td>
                  <input type="number" id="p13_cache" class="small-text" min="0" max="60"
                         name="<?php echo esc_attr(P13_Api::OPTIE); ?>[cache_min]"
                         value="<?php echo esc_attr((string)$i['cache_min']); ?>">
                  <?php esc_html_e('minuten', 'paal13-aanmelden'); ?>
                  <p class="description"><?php esc_html_e('Hoe lang de seizoensgegevens en het formulier worden hergebruikt voordat ze opnieuw worden opgehaald. Hoger is sneller; 0 betekent elke keer opnieuw.', 'paal13-aanmelden'); ?></p>
                </td>
              </tr>
              <tr>
                <th scope="row"><?php esc_html_e('Opmaak', 'paal13-aanmelden'); ?></th>
                <td>
                  <label>
                    <input type="checkbox" name="<?php echo esc_attr(P13_Api::OPTIE); ?>[eigen_css]" value="1"
                           <?php checked($i['eigen_css'], 1); ?>>
                    <?php esc_html_e('Onze opmaak niet laden — ik style het formulier zelf', 'paal13-aanmelden'); ?>
                  </label>
                  <p class="description"><?php esc_html_e('Aanvinken zet ook de kleuren, de invoervelden en de toetsenbordranden uit. Alleen doen als het thema het formulier helemaal zelf opmaakt.', 'paal13-aanmelden'); ?></p>
                </td>
              </tr>
            </table>
            <?php submit_button(); ?>
          </form>

          <p>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=paal13-aanmelden&p13_test=1'), 'p13_test')); ?>">
              <?php esc_html_e('Verbinding testen', 'paal13-aanmelden'); ?>
            </a>
          </p>

          <hr>
          <?php self::spiekbriefje(); ?>
        </div>
        <?php
    }

    /** Wat kwam er terug bij het testen? */
    private static function testuitslag($test): void {
        if (is_wp_error($test)) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('De verbinding werkt niet:', 'paal13-aanmelden')
               . '</strong> ' . esc_html($test->get_error_message()) . '</p></div>';
            return;
        }

        $seizoen = is_array($test['seizoen'] ?? null) ? $test['seizoen'] : [];
        $open = !empty($test['aanmelden_open']);

        echo '<div class="notice notice-success"><p><strong>' . esc_html__('De verbinding werkt.', 'paal13-aanmelden') . '</strong> ';
        printf(
            /* translators: 1: naam van het seizoen, 2: open of gesloten. */
            esc_html__('Huidig seizoen: %1$s — de aanmelding is %2$s.', 'paal13-aanmelden'),
            '<em>' . esc_html((string)($seizoen['naam'] ?? '—')) . '</em>',
            $open ? esc_html__('open', 'paal13-aanmelden') : esc_html__('gesloten', 'paal13-aanmelden')
        );
        echo '</p></div>';
    }

    /** Alle shortcodes met hun huidige waarde. */
    private static function spiekbriefje(): void {
        ?>
        <h2><?php esc_html_e('Het formulier op een pagina zetten', 'paal13-aanmelden'); ?></h2>
        <p><?php esc_html_e('Maak een pagina en zet daar deze shortcode in:', 'paal13-aanmelden'); ?></p>
        <p><code>[paal13_aanmeldformulier]</code></p>
        <p class="description">
          <?php esc_html_e('Extra mogelijkheden: bron="flyer-2027" om te zien waar iemand vandaan kwam, titel="…" voor een eigen kop en knop="…" voor een eigen tekst op de verzendknop.', 'paal13-aanmelden'); ?>
        </p>

        <h2><?php esc_html_e('Tonen of verbergen', 'paal13-aanmelden'); ?></h2>
        <p>
          <code>[paal13_open]<?php esc_html_e('Deze tekst staat er alleen als je je kunt aanmelden.', 'paal13-aanmelden'); ?>[/paal13_open]</code><br>
          <code>[paal13_gesloten]<?php esc_html_e('En deze alleen als de inschrijving dicht is.', 'paal13-aanmelden'); ?>[/paal13_gesloten]</code><br>
          <code>[paal13_aanmeldknop url="/aanmelden/"]</code>
        </p>

        <h2><?php esc_html_e('Seizoensgegevens in je tekst', 'paal13-aanmelden'); ?></h2>
        <p class="description">
          <?php esc_html_e('Deze waarden komen rechtstreeks uit het sollicitatiesysteem. Verandert het bestuur daar een datum, dan verandert je website mee. Achter elke regel staat wat er nu zou komen te staan.', 'paal13-aanmelden'); ?>
        </p>
        <table class="widefat striped" style="max-width:none">
          <thead><tr>
            <th style="width:30%"><?php esc_html_e('Shortcode', 'paal13-aanmelden'); ?></th>
            <th style="width:30%"><?php esc_html_e('Wat het is', 'paal13-aanmelden'); ?></th>
            <th><?php esc_html_e('Nu', 'paal13-aanmelden'); ?></th>
          </tr></thead>
          <tbody>
          <?php foreach (P13_Seizoen::velden() as $veld => $info):
              $waarde = P13_Seizoen::waarde($veld, '', '—'); ?>
            <tr>
              <td><code>[paal13_seizoen veld="<?php echo esc_attr($veld); ?>"]</code></td>
              <td><?php echo esc_html($info[1]); ?></td>
              <td><?php echo esc_html($waarde); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <h3><?php esc_html_e('Datums anders laten zien', 'paal13-aanmelden'); ?></h3>
        <p class="description"><?php esc_html_e('Zet er formaat="…" bij. Voorbeeld met de selectiedag:', 'paal13-aanmelden'); ?></p>
        <table class="widefat striped" style="max-width:none">
          <thead><tr>
            <th style="width:30%"><?php esc_html_e('Shortcode', 'paal13-aanmelden'); ?></th>
            <th><?php esc_html_e('Nu', 'paal13-aanmelden'); ?></th>
          </tr></thead>
          <tbody>
          <?php foreach (['lang', 'kort', 'dag', 'dagnaam', 'maand', 'jaar'] as $f): ?>
            <tr>
              <td><code>[paal13_seizoen veld="datum_selectiedag" formaat="<?php echo esc_attr($f); ?>"]</code></td>
              <td><?php echo esc_html(P13_Seizoen::waarde('datum_selectiedag', $f, '—')); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <h3><?php esc_html_e('In een kop of een knoptekst', 'paal13-aanmelden'); ?></h3>
        <p class="description">
          <?php esc_html_e('Waar een shortcode niet werkt, kun je dit gebruiken in gewone tekst:', 'paal13-aanmelden'); ?>
          <code>{{seizoen:datum_selectiedag}}</code> <?php esc_html_e('of', 'paal13-aanmelden'); ?>
          <code>{{seizoen:datum_selectiedag|dag}}</code>
        </p>
        <?php
    }
}

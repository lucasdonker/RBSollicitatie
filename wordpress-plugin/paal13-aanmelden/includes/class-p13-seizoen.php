<?php
/**
 * De seizoensgegevens: ophalen, en er leesbare tekst van maken.
 *
 * Elke waarde die het sollicitatiesysteem over een seizoen kent, is hier als variabele te
 * gebruiken. Zo hoeft niemand datums met de hand op de website te zetten — het bestuur
 * verandert ze in het beheer en de website volgt vanzelf.
 *
 * Datums en tijden worden in het Nederlands opgemaakt met een eigen tabel, niet met de
 * taalinstelling van WordPress: op een site die (deels) op Engels staat, kreeg je anders
 * "11 June" op een Nederlandse pagina.
 */

if (!defined('ABSPATH')) exit;

class P13_Seizoen {

    /** Alle velden die uit /api/seizoen.php komen, met wat ze zijn en hoe ze standaard tonen. */
    public static function velden(): array {
        return [
            // sleutel                  => [soort, omschrijving]
            'naam'                     => ['tekst', 'Naam van het seizoen'],
            'status'                   => ['tekst', 'open of gesloten'],
            'aanmelden_open'           => ['ja_nee', 'Staat de aanmelding open?'],

            'datum_selectiedag'        => ['datum', 'Datum van de selectiedag'],
            'locatie_selectiedag'      => ['tekst', 'Locatie van de selectiedag'],
            'adres_selectiedag'        => ['tekst', 'Adres van de selectiedag'],
            'selectiedag_verzameltijd' => ['tijd',  'Hoe laat je er moet zijn'],
            'selectiedag_starttijd'    => ['tijd',  'Hoe laat de selectiedag begint'],
            'selectiedag_eindtijd'     => ['tijd',  'Hoe laat de selectiedag klaar is'],
            'selectiedag_meenemen'     => ['tekst', 'Wat je meeneemt naar de selectiedag'],
            'selectiedag_tijden'       => ['afgeleid', 'Verzamelen en eindtijd in één zin'],

            'datum_tw_start'           => ['datum', 'Eerste dag van het trainingsweekend'],
            'datum_tw_eind'            => ['datum', 'Laatste dag van het trainingsweekend'],
            'tw_periode'               => ['afgeleid', 'Het trainingsweekend als periode'],
            'locatie_tw'               => ['tekst', 'Locatie van het trainingsweekend'],
            'adres_tw'                 => ['tekst', 'Adres van het trainingsweekend'],
            'tw_verzameltijd'          => ['tijd',  'Hoe laat je er moet zijn'],
            'tw_begintijd'             => ['tijd',  'Hoe laat het weekend begint'],
            'tw_eindtijd'              => ['tijd',  'Hoe laat het weekend klaar is'],
            'tw_meenemen'              => ['tekst', 'Wat je meeneemt naar het trainingsweekend'],

            'datum_werk_start'         => ['datum', 'Eerste werkdag van het seizoen'],
            'datum_werk_eind'          => ['datum', 'Laatste werkdag van het seizoen'],
            'werk_periode'             => ['afgeleid', 'Het strandseizoen als periode'],
            'hoofdseizoen_start_week'  => ['getal', 'Eerste week van het hoofdseizoen'],
            'hoofdseizoen_eind_week'   => ['getal', 'Laatste week van het hoofdseizoen'],
        ];
    }

    /** De ruwe gegevens van de API. Retour: array met 'ok', 'aanmelden_open', 'seizoen'. */
    public static function gegevens(bool $vers = false) {
        return P13_Api::haal('seizoen.php', [], $vers);
    }

    /** Staat de aanmelding open? Bij twijfel (API stuk) nee: liever niets beloven. */
    public static function open(): bool {
        $d = self::gegevens();
        return !is_wp_error($d) && !empty($d['aanmelden_open']);
    }

    /**
     * Eén waarde, opgemaakt.
     * $formaat: lang | kort | dag | dagnaam | maand | jaar | tijd | ruw
     */
    public static function waarde(string $veld, string $formaat = '', string $leeg = ''): string {
        $d = self::gegevens();
        if (is_wp_error($d)) return $leeg;

        $s = isset($d['seizoen']) && is_array($d['seizoen']) ? $d['seizoen'] : [];

        // Afgeleide waarden zijn combinaties; die maken we hier.
        switch ($veld) {
            case 'aanmelden_open':
                return !empty($d['aanmelden_open'])
                    ? __('ja', 'paal13-aanmelden')
                    : __('nee', 'paal13-aanmelden');

            case 'tw_periode':
                return self::periode($s['datum_tw_start'] ?? null, $s['datum_tw_eind'] ?? null, $leeg);

            case 'werk_periode':
                return self::periode($s['datum_werk_start'] ?? null, $s['datum_werk_eind'] ?? null, $leeg);

            case 'selectiedag_tijden':
                $van = self::tijd($s['selectiedag_verzameltijd'] ?? ($s['selectiedag_starttijd'] ?? null));
                $tot = self::tijd($s['selectiedag_eindtijd'] ?? null);
                if ($van === '' ) return $leeg;
                return $tot !== '' ? $van . ' – ' . $tot : $van;
        }

        $ruw = $s[$veld] ?? null;
        if ($ruw === null || $ruw === '' ) return $leeg;

        $velden = self::velden();
        $soort = $velden[$veld][0] ?? 'tekst';
        if ($formaat === 'ruw') return (string)$ruw;

        if ($soort === 'datum') return self::datum((string)$ruw, $formaat ?: 'lang', $leeg);
        if ($soort === 'tijd')  return self::tijd((string)$ruw) ?: $leeg;

        return (string)$ruw;
    }

    // ── Opmaak ───────────────────────────────────────────────────────────

    private static function maanden(): array {
        return [1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni',
                'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
    }

    private static function maanden_kort(): array {
        return [1 => 'jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    }

    private static function dagen(): array {
        // 0 = zondag, zoals date('w')
        return ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
    }

    public static function datum(string $ruw, string $formaat = 'lang', string $leeg = ''): string {
        $t = strtotime($ruw);
        if (!$t || $ruw === '0000-00-00') return $leeg;

        $dag    = (int)date('j', $t);
        $maand  = (int)date('n', $t);
        $jaar   = date('Y', $t);
        $dagnr  = (int)date('w', $t);

        switch ($formaat) {
            case 'kort':    return $dag . ' ' . self::maanden_kort()[$maand] . ' ' . $jaar;
            case 'dag':     return self::dagen()[$dagnr] . ' ' . $dag . ' ' . self::maanden()[$maand] . ' ' . $jaar;
            case 'dagnaam': return self::dagen()[$dagnr];
            case 'maand':   return self::maanden()[$maand];
            case 'jaar':    return $jaar;
            case 'lang':
            default:        return $dag . ' ' . self::maanden()[$maand] . ' ' . $jaar;
        }
    }

    public static function tijd(?string $ruw): string {
        if (!$ruw) return '';
        if (!preg_match('/^(\d{1,2}):(\d{2})/', $ruw, $m)) return '';
        return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    }

    /**
     * Twee datums als één periode, zo kort als kan:
     *   zelfde maand  → "15 t/m 17 mei 2027"
     *   zelfde jaar   → "27 juni t/m 30 augustus 2027"
     *   anders        → "27 december 2027 t/m 3 januari 2028"
     */
    public static function periode(?string $van, ?string $tot, string $leeg = ''): string {
        $tv = $van ? strtotime($van) : 0;
        $tt = $tot ? strtotime($tot) : 0;
        if (!$tv && !$tt) return $leeg;
        if (!$tt) return self::datum((string)$van, 'lang', $leeg);
        if (!$tv) return self::datum((string)$tot, 'lang', $leeg);

        $m = self::maanden();
        $zelfde_jaar  = date('Y', $tv) === date('Y', $tt);
        $zelfde_maand = $zelfde_jaar && date('n', $tv) === date('n', $tt);

        if (date('Y-m-d', $tv) === date('Y-m-d', $tt)) return self::datum((string)$van, 'lang');
        if ($zelfde_maand) {
            return (int)date('j', $tv) . ' t/m ' . (int)date('j', $tt) . ' ' . $m[(int)date('n', $tt)] . ' ' . date('Y', $tt);
        }
        if ($zelfde_jaar) {
            return (int)date('j', $tv) . ' ' . $m[(int)date('n', $tv)] . ' t/m '
                 . (int)date('j', $tt) . ' ' . $m[(int)date('n', $tt)] . ' ' . date('Y', $tt);
        }
        return self::datum((string)$van, 'lang') . ' t/m ' . self::datum((string)$tot, 'lang');
    }
}

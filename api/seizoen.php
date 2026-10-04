<?php
/**
 * GET /api/seizoen.php
 *
 * Geeft de publieke seizoensinformatie terug voor de WordPress-plugin:
 * of er een open seizoen is, en de relevante datums (selectiedag, trainingsweekend)
 * voor gebruik in website-teksten.
 *
 * Vereist de API-sleutel in de header X-Paal13-Key (de querystring-variant is vervallen in 2.94.0).
 */
require_once __DIR__ . '/_basis.php';
api_preflight();
api_vereis_sleutel();

// De website kijkt vooruit (zie seizoen_voor_werving() in includes/functions.php).
$seizoen = seizoen_voor_werving();

if (!$seizoen) {
    api_json([
        'ok'            => true,
        'aanmelden_open'=> false,
        'status'        => 'geen_seizoen',
        'bericht'       => 'Er is op dit moment geen seizoen actief.',
        'seizoen'       => null,
    ]);
}

$open = ($seizoen['status'] ?? '') === 'open';

/**
 * Alle seizoensvelden die op de website mogen staan — de WordPress-plugin maakt hier
 * shortcodes van, dus wat hier ontbreekt kan daar niet getoond worden.
 *
 * Dit gaat over een evenement (waar, wanneer, wat meenemen), niet over personen: het is
 * precies de informatie die de brigade zelf op paal13.com wil zetten. Wat hier níét in
 * staat, staat er bewust niet in — zet er nooit iets bij dat over een sollicitant gaat.
 */
$publiek = [
    'naam'                     => $seizoen['naam'] ?? null,
    'status'                   => $seizoen['status'] ?? null,
    'aanmelden_tot'            => $seizoen['aanmelden_tot'] ?? null,

    'datum_selectiedag'        => $seizoen['datum_selectiedag'] ?? null,
    'locatie_selectiedag'      => $seizoen['locatie_selectiedag'] ?? null,
    'adres_selectiedag'        => $seizoen['adres_selectiedag'] ?? null,
    'selectiedag_verzameltijd' => $seizoen['selectiedag_verzameltijd'] ?? null,
    'selectiedag_starttijd'    => $seizoen['selectiedag_starttijd'] ?? null,
    'selectiedag_eindtijd'     => $seizoen['selectiedag_eindtijd'] ?? null,
    'selectiedag_meenemen'     => $seizoen['selectiedag_meenemen'] ?? null,

    'datum_tw_start'           => $seizoen['datum_tw_start'] ?? null,
    'datum_tw_eind'            => $seizoen['datum_tw_eind'] ?? null,
    'locatie_tw'               => $seizoen['locatie_tw'] ?? null,
    'adres_tw'                 => $seizoen['adres_tw'] ?? null,
    'tw_verzameltijd'          => $seizoen['tw_verzameltijd'] ?? null,
    'tw_begintijd'             => $seizoen['tw_begintijd'] ?? null,
    'tw_eindtijd'              => $seizoen['tw_eindtijd'] ?? null,
    'tw_meenemen'              => $seizoen['tw_meenemen'] ?? null,

    'datum_werk_start'         => $seizoen['datum_werk_start'] ?? null,
    'datum_werk_eind'          => $seizoen['datum_werk_eind'] ?? null,
    'hoofdseizoen_start_week'  => isset($seizoen['hoofdseizoen_start_week']) ? (int)$seizoen['hoofdseizoen_start_week'] : null,
    'hoofdseizoen_eind_week'   => isset($seizoen['hoofdseizoen_eind_week']) ? (int)$seizoen['hoofdseizoen_eind_week'] : null,
];

api_json([
    'ok'             => true,
    'aanmelden_open' => $open,
    'status'         => $open ? 'open' : ($seizoen['status'] ?? 'gesloten'),
    'bericht'        => $open
        ? 'De aanmelding is open.'
        : 'De aanmelding is op dit moment gesloten.',
    'seizoen'        => $publiek,
    'opgehaald_op'   => date('c'),
]);

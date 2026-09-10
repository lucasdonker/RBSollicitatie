<?php
/**
 * GET /api/seizoen.php
 *
 * Geeft de publieke seizoensinformatie terug voor de WordPress-plugin:
 * of er een open seizoen is, en de relevante datums (selectiedag, trainingsweekend)
 * voor gebruik in website-teksten.
 *
 * Vereist de API-sleutel (header X-Paal13-Key of ?key=).
 */
require_once __DIR__ . '/_basis.php';
api_preflight();
api_vereis_sleutel();

$seizoen = actief_seizoen();

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

// Alleen publiek-relevante velden teruggeven (geen interne gegevens).
$publiek = [
    'naam'                  => $seizoen['naam'] ?? null,
    'status'                => $seizoen['status'] ?? null,
    'datum_selectiedag'     => $seizoen['datum_selectiedag'] ?? null,
    'locatie_selectiedag'   => $seizoen['locatie_selectiedag'] ?? null,
    'selectiedag_verzameltijd' => $seizoen['selectiedag_verzameltijd'] ?? null,
    'selectiedag_starttijd' => $seizoen['selectiedag_starttijd'] ?? null,
    'datum_tw_start'        => $seizoen['datum_tw_start'] ?? null,
    'datum_tw_eind'         => $seizoen['datum_tw_eind'] ?? null,
    'locatie_tw'            => $seizoen['locatie_tw'] ?? null,
    'tw_verzameltijd'       => $seizoen['tw_verzameltijd'] ?? null,
    'tw_begintijd'          => $seizoen['tw_begintijd'] ?? null,
    'datum_werk_start'      => $seizoen['datum_werk_start'] ?? null,
    'datum_werk_eind'       => $seizoen['datum_werk_eind'] ?? null,
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

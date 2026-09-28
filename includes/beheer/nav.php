<?php
/**
 * Paal 13 — de beheernavigatie. GEEN HTML.
 *
 * Vier groepen met "Vandaag" los bovenaan (ontwerpfilosofie §11): dertien losse items
 * is een lijst om in te zoeken, vier groepen is een lijst om te scannen.
 *
 * Twee regels:
 *   1. Geen recht betekent dat het item wég is, niet uitgegrijsd (§10). Een groep
 *      zonder zichtbare items verdwijnt vanzelf mee.
 *   2. Iconen komen uit includes/iconen.php. Eerder droeg layout_top.php zijn eigen
 *      SVG-paden in een array, naast die 62 iconen — precies de dubbeling die
 *      CLAUDE.md punt 8 verbiedt, in het bestand dat élk beheerscherm inlaadt.
 */

if (!function_exists('bh_nav_groepen')) {

/**
 * De navigatie zoals deze gebruiker hem mag zien.
 *
 * @param array $tellers  [nav-id => int] badges, bijv. ['vandaag'=>5,'ondertekeningen'=>3]
 * @param bool  $alles    true = niet filteren op rechten (voor bh_nav_actief)
 * @return array{los:array,groepen:array}  'los' = items zonder groepskop (Vandaag)
 */
function bh_nav_groepen(array $tellers = [], bool $alles = false): array {
    $basis = APP_URL . '/admin/';

    $los = [
        ['id' => 'dashboard', 'label' => 'Vandaag', 'url' => $basis, 'icoon' => 'huis', 'module' => null],
    ];

    // De kern. Items van losse modules (selectiedag, loonplanning, …) komen uit het
    // moduleregister (includes/modules/) en schuiven op 'positie' tussen deze in.
    $items = [
        ['groep' => 'Selectie',      'positie' => 10, 'id' => 'sollicitanten',   'label' => 'Sollicitanten',   'url' => 'sollicitanten.php',   'icoon' => 'mensen',       'module' => 'sollicitanten'],
        ['groep' => 'Selectie',      'positie' => 40, 'id' => 'ondertekeningen', 'label' => 'Ondertekeningen', 'url' => 'ondertekeningen.php', 'icoon' => 'ondertekenen', 'module' => 'ondertekeningen'],
        ['groep' => 'Inzet & loon', 'positie' => 10, 'id' => 'beschikbaarheid', 'label' => 'Beschikbaarheid', 'url' => 'beschikbaarheid.php', 'icoon' => 'klok',         'module' => 'beschikbaarheid'],
        ['groep' => 'Inzet & loon', 'positie' => 40, 'id' => 'documenten',      'label' => 'Loondossiers',    'url' => 'documenten.php',      'icoon' => 'slot',         'module' => 'documenten'],
        ['groep' => 'Communicatie', 'positie' => 10, 'id' => 'berichten',       'label' => 'Berichten',       'url' => 'berichten.php',       'icoon' => 'mail',         'module' => 'mailing'],
        ['groep' => 'Systeem',      'positie' => 10, 'id' => 'seizoen',         'label' => 'Seizoen',         'url' => 'seizoen.php',         'icoon' => 'strand',       'module' => 'seizoen'],
        ['groep' => 'Systeem',      'positie' => 20, 'id' => 'instellingen',    'label' => 'Instellingen',    'url' => 'instellingen.php',    'icoon' => 'instellingen', 'module' => 'instellingen'],
        ['groep' => 'Systeem',      'positie' => 30, 'id' => 'beheerders',      'label' => 'Beheerders & rechten', 'url' => 'beheerders.php', 'icoon' => 'sleutel',      'module' => 'beheerders'],
        ['groep' => 'Systeem',      'positie' => 40, 'id' => 'logboek',         'label' => 'Logboek',         'url' => 'logboek.php',         'icoon' => 'auditlog',     'module' => 'logboek'],
    ];
    if (function_exists('modules_nav')) $items = array_merge($items, modules_nav());
    usort($items, fn($a, $b) => $a['positie'] <=> $b['positie']);

    $groepen = array_fill_keys(['Selectie', 'Inzet & loon', 'Communicatie', 'Systeem'], []);
    foreach ($items as $i) {
        $groepen[$i['groep']][] = ['id' => $i['id'], 'label' => $i['label'], 'url' => $basis . $i['url'],
                                   'icoon' => $i['icoon'], 'module' => $i['module']];
    }

    $zichtbaar = function (array $item) use ($alles): bool {
        if ($alles || $item['module'] === null) return true;
        return !function_exists('mag') || mag($item['module'], 'lezen');
    };

    $los = array_values(array_filter($los, $zichtbaar));
    foreach ($groepen as $naam => $items) {
        $items = array_values(array_filter($items, $zichtbaar));
        if (!$items) { unset($groepen[$naam]); continue; }     // lege groep verdwijnt mee
        $groepen[$naam] = $items;
    }

    // Tellers erbij
    $tel = function (array $item) use ($tellers): array {
        $item['teller'] = (int)($tellers[$item['id']] ?? 0);
        return $item;
    };
    $los = array_map($tel, $los);
    foreach ($groepen as $naam => $items) $groepen[$naam] = array_map($tel, $items);

    return ['los' => $los, 'groepen' => $groepen];
}

/**
 * Welk nav-item hoort bij deze $actieve_nav?
 *
 * Schermen zetten een vrije string en die is niet altijd een nav-id: mail_templates.php
 * hoort onder Berichten, foutenlog onder Logboek. Onbekende
 * sleutels worden gelogd — anders is een typefout onzichtbaar en licht er gewoon geen
 * enkel item op.
 */
function bh_nav_actief(string $nav): string {
    $alias = [
        'mail_templates' => 'berichten',   // Sjablonen is een tab van Berichten (2.123)
        'geplande_berichten' => 'berichten', 'mailing' => 'berichten',
        // handleiding: eigen link onderin de zijbalk (_nav.php), niet onder Instellingen
        'handleiding' => '', 'systeem' => 'instellingen', 'update' => 'instellingen',
        'foutenlog' => 'logboek', 'audit' => 'logboek',
        'rollen' => 'beheerders', 'profiel' => '', 'import' => 'sollicitanten',
        'blk' => 'sollicitanten', 'sollicitant' => 'sollicitanten',
    ] + (function_exists('modules_nav_alias') ? modules_nav_alias() : []);
    if (isset($alias[$nav])) return $alias[$nav];

    // Alle ids, óók die deze rol niet mag zien: een verborgen item is geen typefout.
    static $bekend = null;
    if ($bekend === null) {
        $bekend = ['dashboard'];
        foreach (bh_nav_groepen([], true)['groepen'] as $items) {
            foreach ($items as $i) $bekend[] = $i['id'];
        }
    }
    if ($nav === '' || in_array($nav, $bekend, true)) return $nav;

    error_log("bh_nav_actief: onbekende nav-sleutel '$nav' — geen enkel menu-item licht op.");
    return '';
}

} // function_exists

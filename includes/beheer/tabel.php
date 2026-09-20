<?php
/**
 * Paal 13 — kolomdefinitie voor de beheer-tabellen. GEEN HTML.
 *
 * Deze laag beantwoordt vier vragen over een tabel:
 *   welke kolommen mag deze rol zien · welke staan aan · hoe breed zijn ze · waarop
 *   mag gesorteerd worden.
 * Het tekenen gebeurt in admin/_tabel.php, de celinhoud in een rij-partial per scherm.
 *
 * Waarom die splitsing: de naamcel uit het ontwerp is samengesteld (avatar + naam +
 * e-mail + soms een chip). Eén tabelcomponent die ook dát zou renderen heeft een
 * "geef maar HTML"-luik nodig, en dan is de kolomdefinitie alleen nog decoratie.
 * Liever minder generiek en wel houdbaar.
 *
 * Eén kolom:
 *   'kop'       string   kolomkop
 *   'track'     string   grid-track ('2.2fr', '80px', 'minmax(180px,2fr)'); standaard '1fr'.
 *                        LET OP: géén inhoudsafhankelijke waarde (auto, min-content,
 *                        max-content, fit-content). Elke <tr> is een eigen grid, dus zo'n
 *                        track wordt per rij anders berekend en de kolommen lijnen niet
 *                        meer uit — de rij met een extra knop schuift dan zichtbaar op.
 *                        bh_kolommen() vervangt zo'n waarde en logt het.
 *   'sorteer'   ?string  SQL-veilige sleutel; null = niet sorteerbaar
 *   'uitlijn'   string   'links' (standaard) of 'rechts' (rechts = ook tabular-nums)
 *   'optioneel' bool     verschijnt in de kolommenkiezer
 *   'standaard' bool     staat aan als de gebruiker niets koos (standaard true)
 *   'mobiel'    string   'toon' (standaard) of 'verberg' — op telefoon weglaten
 *   'recht'     ?array   ['module','actie'] — zonder recht verdwijnt de kolom
 */

if (!function_exists('bh_kolommen')) {

/**
 * Normaliseert een kolomdefinitie tot wat de partial nodig heeft.
 *
 * @param string $tabel_id  sleutel voor de kolomkeuze-cookie en het DOM-id
 * @param array  $def       [sleutel => kolom]
 * @param array  $opt       'sort' => ?string, 'richting' => ?string (uit $_GET, ongevalideerd)
 * @return array{id:string,kolommen:array,tracks:string,zichtbaar:array,optioneel:array,sort:?string,richting:string}
 */
function bh_kolommen(string $tabel_id, array $def, array $opt = []): array {
    $keuze = bh_kolomkeuze($tabel_id);          // null = gebruiker koos niets

    $kolommen = [];
    foreach ($def as $sleutel => $k) {
        // Rechtenfilter: een kolom die deze rol niet mag zien bestaat niet.
        if (!empty($k['recht']) && function_exists('mag') && !mag($k['recht'][0], $k['recht'][1] ?? 'lezen')) {
            continue;
        }
        $optioneel = !empty($k['optioneel']);
        $aan = $optioneel
            ? ($keuze === null ? ($k['standaard'] ?? true) : in_array($sleutel, $keuze, true))
            : true;                             // vaste kolommen staan altijd aan
        $kolommen[$sleutel] = [
            'sleutel'   => $sleutel,
            'kop'       => $k['kop'] ?? '',
            'track'     => bh_track_veilig($k['track'] ?? '1fr', $tabel_id, $sleutel),
            'sorteer'   => $k['sorteer'] ?? null,
            'uitlijn'   => ($k['uitlijn'] ?? 'links') === 'rechts' ? 'rechts' : 'links',
            'optioneel' => $optioneel,
            'standaard' => $k['standaard'] ?? true,
            'mobiel'    => ($k['mobiel'] ?? 'toon') === 'verberg' ? 'verberg' : 'toon',
            'aan'       => $aan,
        ];
    }

    $zichtbaar = array_values(array_filter($kolommen, fn($k) => $k['aan']));
    $tracks    = implode(' ', array_column($zichtbaar, 'track'));

    // Sorteren: uitsluitend tegen de whitelist uit de definitie. Nooit $_GET in de ORDER BY.
    $toegestaan = array_filter(array_column($kolommen, 'sorteer'));
    $sort       = in_array($opt['sort'] ?? '', $toegestaan, true) ? $opt['sort'] : null;
    $richting   = strtolower($opt['richting'] ?? '') === 'asc' ? 'asc' : 'desc';

    return [
        'id'        => $tabel_id,
        'kolommen'  => $kolommen,
        'tracks'    => $tracks !== '' ? $tracks : '1fr',
        'zichtbaar' => array_column($zichtbaar, 'sleutel'),
        'optioneel' => array_values(array_filter($kolommen, fn($k) => $k['optioneel'])),
        'sort'      => $sort,
        'richting'  => $richting,
    ];
}

/**
 * Weert inhoudsafhankelijke grid-tracks.
 *
 * Elke <tr> is een eigen grid. Een track die zich naar de inhoud voegt (auto,
 * min-content, max-content, fit-content) wordt daardoor per rij anders berekend: de
 * rij met een extra knop of een langere badge krijgt andere kolombreedtes dan de rest
 * en de tabel gaat zichtbaar scheef staan. Vaste maten en fr zijn per rij wél gelijk.
 */
function bh_track_veilig(string $track, string $tabel_id, string $kolom): string {
    if (!preg_match('/\b(auto|min-content|max-content|fit-content)\b/i', $track)) return $track;
    error_log("bh_kolommen: kolom '$kolom' in tabel '$tabel_id' gebruikt een inhoudsafhankelijke "
            . "track ('$track'); vervangen door 1fr omdat de kolommen anders per rij verspringen.");
    return preg_replace('/\b(auto|min-content|max-content|fit-content)\b/i', '1fr', $track);
}

/**
 * De kolomkeuze van deze gebruiker, of null als hij niets koos.
 *
 * Bewust een cookie en geen localStorage: de server moet de keuze al kennen bij de
 * eerste render, anders staan de kolommen even verkeerd en springt de tabel. De
 * keuze vervalt per seizoen (naam bevat het seizoen-id), zodat een keuze van vier
 * maanden geleden geen onverklaarbaar scherm oplevert.
 */
function bh_kolomkeuze(string $tabel_id): ?array {
    $naam = bh_kolomcookie($tabel_id);
    if (empty($_COOKIE[$naam])) return null;
    $waarde = (string)$_COOKIE[$naam];
    if (!preg_match('/^[a-z0-9_,]{0,400}$/i', $waarde)) return null;   // rommel = geen keuze
    $lijst = array_values(array_filter(explode(',', $waarde)));
    return $lijst ?: [];                                              // lege lijst = alles uit
}

/** Cookienaam per tabel én per seizoen. */
function bh_kolomcookie(string $tabel_id): string {
    $sid = 0;
    if (function_exists('actief_seizoen')) {
        $s = actief_seizoen();
        $sid = $s ? (int)$s['id'] : 0;
    }
    return 'bh_kol_' . preg_replace('/[^a-z0-9_]/i', '', $tabel_id) . '_' . $sid;
}

/**
 * Sorteer-URL voor een kolomkop: klik op de actieve kolom draait de richting om.
 * Alle overige queryparameters blijven staan, zodat filters niet wegvallen.
 */
function bh_sorteer_url(array $tabel, string $sleutel): ?string {
    $k = $tabel['kolommen'][$sleutel] ?? null;
    if (!$k || !$k['sorteer']) return null;
    $q = $_GET;
    $q['sort'] = $k['sorteer'];
    $q['richting'] = ($tabel['sort'] === $k['sorteer'] && $tabel['richting'] === 'asc') ? 'desc' : 'asc';
    unset($q['pagina']);                        // andere sortering = terug naar pagina 1
    return '?' . http_build_query($q);
}

/** 'ascending' | 'descending' | null — voor aria-sort op de <th>. */
function bh_sorteer_stand(array $tabel, string $sleutel): ?string {
    $k = $tabel['kolommen'][$sleutel] ?? null;
    if (!$k || !$k['sorteer'] || $tabel['sort'] !== $k['sorteer']) return null;
    return $tabel['richting'] === 'asc' ? 'ascending' : 'descending';
}

/**
 * De ORDER BY-clausule, of de meegegeven standaard als er niet gesorteerd is.
 * De sleutel is al gevalideerd tegen de whitelist in bh_kolommen().
 */
function bh_order_by(array $tabel, string $standaard): string {
    if (!$tabel['sort']) return $standaard;
    return $tabel['sort'] . ' ' . ($tabel['richting'] === 'asc' ? 'ASC' : 'DESC');
}

} // function_exists

<?php
/**
 * Pagina's — wervingsgegevens: seizoensdatums en loon als markeringen, de vacature voor Google
 * (JobPosting) en campagnes (?bron=…) op dezelfde pagina. Schrijft niets weg.
 *
 * Bron van de datums: seizoen_voor_werving() — het seizoen met de aanmelding open, anders het
 * nieuwste. Níét actief_seizoen(): dat blijft bij het lopende seizoen, en op een wervingspagina
 * zijn dat oude datums. Zo veroudert een pagina nooit: de tekst komt elk jaar uit het seizoen.
 *
 * Wat hier uitkomt is HTML (al ge-escaped) voor op de plek van een markering. De markeringen zelf
 * en het vervangen staan in render.php (pag_placeholders).
 */
require_once __DIR__ . '/../opmaak.php';   // euro(), datums

if (!function_exists('pag_werving_gegevens')) {

/** De salaristabel begint bij deze leeftijd (jonger dan 16 werkt niet op de post). */
function pag_salaris_vanaf(): int {
    return 16;
}

/** Wat er staat als een gegeven nog niet is ingevuld — nooit leeg, nooit de markering zelf. */
function pag_nog_onbekend(): string {
    return 'wordt nog bekendgemaakt';
}

/** "€ 4,97" met een vaste spatie (breekt nooit af tussen teken en bedrag). */
function pag_euro(float $v): string {
    return euro($v, 2);   // includes/opmaak.php: één notatie voor het hele systeem
}

/**
 * Een datum als tekst, met de portaal-helpers (één notatie voor het hele systeem).
 * "zaterdag 11 april 2027" ($met_dag) of "11 april 2027"; zonder jaar als $met_jaar false is.
 */
function pag_datum_tekst(?string $d, bool $met_dag = true, bool $met_jaar = true): string {
    $d = substr(trim((string)$d), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d === '0000-00-00') return '';
    $t = $met_dag ? lcfirst(portaal_datum_lang($d)) : portaal_datum_kort($d);
    return $met_jaar ? $t . ' ' . substr($d, 0, 4) : $t;
}

/**
 * Een periode: "vrijdag 30 april t/m zondag 2 mei 2027", of één datum als begin en eind gelijk
 * zijn (of het eind ontbreekt). Het jaartal alleen aan het eind, behalve over de jaargrens.
 */
function pag_periode_tekst(?string $van, ?string $tot, bool $met_dag = true): string {
    $begin = pag_datum_tekst($van, $met_dag);
    if ($begin === '') return '';
    $eind = pag_datum_tekst($tot, $met_dag);
    if ($eind === '' || substr((string)$van, 0, 10) === substr((string)$tot, 0, 10)) return $begin;
    $zelfde_jaar = substr((string)$van, 0, 4) === substr((string)$tot, 0, 4);
    return ($zelfde_jaar ? pag_datum_tekst($van, $met_dag, false) : $begin) . ' t/m ' . $eind;
}

/**
 * De kolommen van de salaristabel: de diploma's/functies met een factor op het uurloon
 * (Instellingen › Functies & factoren), in hun volgorde. Functies met een vast weekbedrag
 * (postcommandant) horen er niet in: dat is geen uurloon en geen diploma waarmee je instroomt.
 * Retour: [sleutel => ['naam', 'factor', 'min', 'max']].
 */
function pag_salaris_kolommen(): array {
    $uit = [];
    foreach (function_exists('functies_ophalen') ? functies_ophalen() : [] as $f) {
        if ((float)($f['factor'] ?? 0) <= 0 || !empty($f['vast_loon'])) continue;
        $uit[(string)$f['sleutel']] = ['naam' => (string)$f['naam'], 'factor' => (float)$f['factor'],
                                       'min' => (int)($f['min_leeftijd'] ?? 0), 'max' => (int)($f['max_leeftijd'] ?? 99)];
    }
    return $uit;
}

/** Het tarief waarmee de tabel rekent: juli t/m september, het grootste deel van de werkperiode. */
function pag_salaris_periode(): string {
    return 'jul_sep';
}

/**
 * De rijen van de salaristabel: per leeftijd het uurloon per diploma, zoals de loonplanning het
 * rekent (minimum(jeugd)uurloon van die leeftijd × factor van de functie; loon_dagloon() zonder
 * uren en vakantietoeslag). Buiten de leeftijdsgrenzen van een functie: null ("—").
 * Retour: [['label' => '16 jaar', 'bedragen' => [sleutel => ?float]], …]
 *
 * De hoogste leeftijden met dezelfde bedragen worden één rij "N jaar en ouder" — boven de
 * hoogste ingevulde leeftijd geldt toch al dat tarief. Bedragen vergelijken we zoals ze op de
 * pagina staan (twee decimalen), anders splitst een verschil in de vierde decimaal een rij.
 */
function pag_salaris_rijen(array $t, ?array $kolommen = null): array {
    $kolommen ??= pag_salaris_kolommen();
    $tarief = (array)($t['tarieven'][pag_salaris_periode()] ?? []);
    $leeftijden = array_values(array_filter(array_keys($tarief), fn($l) => (int)$l >= pag_salaris_vanaf()));
    sort($leeftijden);
    if (!$leeftijden || !$kolommen) return [];

    $rijen = [];
    foreach ($leeftijden as $l) {
        $l = (int)$l;
        $bedragen = [];
        foreach ($kolommen as $k => $f) {
            $bedragen[$k] = ((float)$tarief[$l] > 0 && $l >= $f['min'] && $l <= $f['max'])
                ? round((float)$tarief[$l] * $f['factor'], 2) : null;
        }
        $rijen[] = ['leeftijd' => $l, 'bedragen' => $bedragen];
    }

    // Van boven af samenvoegen zolang de bedragen gelijk zijn aan die van de hoogste leeftijd.
    $laatste = count($rijen) - 1;
    $van = $laatste;
    while ($van > 0 && $rijen[$van - 1]['bedragen'] === $rijen[$laatste]['bedragen']) $van--;

    $uit = [];
    foreach ($rijen as $i => $r) {
        if ($i > $van) break;
        $uit[] = ['label' => $r['leeftijd'] . ($i === $van ? ' jaar en ouder' : ' jaar'), 'bedragen' => $r['bedragen']];
    }
    return $uit;
}

/** Laagste en hoogste bedrag uit de rijen: ['min' => ?float, 'max' => ?float]. */
function pag_salaris_bereik(array $rijen): array {
    $alle = [];
    foreach ($rijen as $r) foreach ($r['bedragen'] as $v) if ($v !== null) $alle[] = $v;
    return ['min' => $alle ? min($alle) : null, 'max' => $alle ? max($alle) : null];
}

/** De vaste disclaimer onder elke loonweergave: indicatief, geen rechten aan te ontlenen. */
function pag_salaris_disclaimer(): string {
    return 'Deze bedragen zijn indicatief en kunnen veranderen. Wat je precies verdient, hangt af van '
         . 'je situatie en gegevens, zoals je leeftijd, je diploma\'s en je functie, en van wijzigingen '
         . 'in het wettelijk minimumloon. Aan deze tabel kun je geen rechten ontlenen.';
}

/**
 * Loon voor de wervingspagina, per seizoen één keer per verzoek. Dezelfde tarieven als de
 * loonplanning (per kalenderjaar); de terugvalmelding hoort in het beheer, niet op de pagina.
 */
function pag_werving_loon(?array $seizoen): array {
    static $cache = [];
    $sleutel = md5((string)json_encode($seizoen));
    if (isset($cache[$sleutel])) return $cache[$sleutel];

    $leeg = ['rijen' => [], 'min' => null, 'max' => null, 'vt' => 0.0, 'jaar' => 0];
    if (!$seizoen) return $cache[$sleutel] = $leeg;
    if (!function_exists('loon_tarieven_voor_seizoen')) {
        $pad = dirname(__DIR__) . '/loon_tarieven.php';
        if (is_file($pad)) require_once $pad;
    }
    if (!function_exists('loon_tarieven_voor_seizoen')) return $cache[$sleutel] = $leeg;
    try {
        $t = loon_tarieven_voor_seizoen($seizoen);
    } catch (\Throwable $e) {
        error_log('pag_werving_loon: ' . $e->getMessage());
        return $cache[$sleutel] = $leeg;
    }
    $kolommen = pag_salaris_kolommen();
    $rijen = pag_salaris_rijen($t, $kolommen);
    $bereik = pag_salaris_bereik($rijen);
    return $cache[$sleutel] = ['rijen' => $rijen, 'kolommen' => $kolommen, 'min' => $bereik['min'], 'max' => $bereik['max'],
                               'vt' => (float)($t['vt_percentage'] ?? 0), 'jaar' => (int)($t['jaar'] ?? 0)];
}

/**
 * De salaristabel als toegankelijke HTML: een rij per leeftijd, een kolom per diploma/functie
 * (caption, th met scope), met eronder altijd de noot en de disclaimer (indicatief).
 */
function pag_salaris_html(array $loon): string {
    $disclaimer = '<p class="p13-salaris-noot p13-salaris-disclaimer">' . h(pag_salaris_disclaimer()) . '</p>';
    if (!$loon['rijen']) {
        return '<p class="p13-salaris-noot">Het uurloon per leeftijd ' . h(pag_nog_onbekend()) . '.</p>' . $disclaimer;
    }
    $cel = fn(?float $v): string => $v === null
        // Verborgen tekst voor schermlezers met een eigen inline stijl: het ontwerp (Claude Design)
        // kent onze klassen niet. Op publieke pagina's staat de CSP inline stijl toe.
        ? '<span aria-hidden="true">—</span><span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap">niet van toepassing</span>'
        : h(pag_euro($v));
    $html = '<table class="p13-salaris">'
          . '<caption>Indicatief uurloon per leeftijd en diploma' . ($loon['jaar'] ? ' (' . (int)$loon['jaar'] . ')' : '') . '</caption>'
          . '<thead><tr><th scope="col">Leeftijd</th>';
    foreach ($loon['kolommen'] as $f) $html .= '<th scope="col">' . h($f['naam']) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($loon['rijen'] as $r) {
        $html .= '<tr><th scope="row">' . h($r['label']) . '</th>';
        foreach (array_keys($loon['kolommen']) as $k) $html .= '<td>' . $cel($r['bedragen'][$k] ?? null) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $vt = function_exists('loon_getal') ? loon_getal($loon['vt'], 2) : rtrim(rtrim(number_format($loon['vt'], 2, ',', ''), '0'), ',');
    $html .= '<p class="p13-salaris-noot">Bruto per uur, exclusief ' . h($vt) . '% vakantietoeslag, voor juli en augustus. '
           . 'In juni geldt een iets ander wettelijk tarief. Een "—" betekent dat die functie bij die leeftijd niet voorkomt.</p>'
           . $disclaimer;
    return $html;
}

/**
 * De seizoensteksten (platte tekst, nog niet ge-escaped), één keer per seizoen per verzoek.
 * Leeg = nog niet ingevuld.
 */
function pag_werving_gegevens(?array $seizoen): array {
    static $cache = [];
    $sleutel = md5((string)json_encode($seizoen));
    if (isset($cache[$sleutel])) return $cache[$sleutel];
    $s = $seizoen ?? [];
    return $cache[$sleutel] = [
        'selectiedag_datum'      => pag_datum_tekst($s['datum_selectiedag'] ?? null),
        'trainingsweekend_datum' => pag_periode_tekst($s['datum_tw_start'] ?? null, $s['datum_tw_eind'] ?? null),
        'werkperiode'            => pag_periode_tekst($s['datum_werk_start'] ?? null, $s['datum_werk_eind'] ?? null, false),
        'aanmelden_tot'          => pag_datum_tekst($s['aanmelden_tot'] ?? null, false),
    ];
}

/**
 * Alle wervingsmarkeringen die in $html voorkomen, als [markering => HTML]. Alleen wat er staat
 * wordt berekend: een pagina zonder [[salaristabel]] raakt de loontabellen niet.
 */
function pag_werving_markeringen(string $html, array $pagina, ?array $versie = null): array {
    $namen = ['selectiedag_datum', 'trainingsweekend_datum', 'werkperiode', 'aanmelden_tot', 'uurloon_vanaf', 'salaristabel', 'vacature_schema'];
    $nodig = array_values(array_filter($namen, fn($n) => str_contains($html, '[[' . $n . ']]')));
    if (!$nodig) return [];

    $seizoen = function_exists('seizoen_voor_werving') ? seizoen_voor_werving() : null;
    $uit = [];
    foreach ($nodig as $n) {
        if ($n === 'salaristabel') {
            $uit['[[salaristabel]]'] = pag_salaris_html(pag_werving_loon($seizoen));
        } elseif ($n === 'uurloon_vanaf') {
            $min = pag_werving_loon($seizoen)['min'];
            // Altijd met "indicatief": een los bedrag in een hero leest anders als een belofte.
            $uit['[[uurloon_vanaf]]'] = h($min === null ? pag_nog_onbekend() : pag_euro($min) . ' (indicatief)');
        } elseif ($n === 'vacature_schema') {
            $uit['[[vacature_schema]]'] = pag_vacature_schema($pagina, $seizoen, $versie);
        } else {
            $t = pag_werving_gegevens($seizoen)[$n];
            $uit['[[' . $n . ']]'] = h($t !== '' ? $t : pag_nog_onbekend());
        }
    }
    return $uit;
}

/**
 * Tot wanneer de vacature geldt (Europe/Amsterdam): de dag "Aanmelden kan tot" tot 23:59, en
 * zonder deadline het begin van de selectiedag. Null als geen van beide is ingevuld.
 */
function pag_vacature_einde(?array $seizoen): ?\DateTimeImmutable {
    $tz = new \DateTimeZone('Europe/Amsterdam');
    foreach ([['aanmelden_tot', '23:59:00'], ['datum_selectiedag', '00:00:00']] as $k) {
        $d = substr((string)($seizoen[$k[0]] ?? ''), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d === '0000-00-00') continue;
        try { return new \DateTimeImmutable($d . ' ' . $k[1], $tz); } catch (\Throwable $e) { continue; }
    }
    return null;
}

/**
 * [[vacature_schema]]: een JobPosting voor Google (developers.google.com/search/docs/appearance/
 * structured-data/job-posting). Alleen als de pagina gepubliceerd en vindbaar is, de aanmelding
 * open staat en de einddatum niet voorbij is — anders een lege string, zodat de vacature vanzelf
 * uit Google verdwijnt. Alle informatie staat ook op de pagina zelf (eis van Google).
 *
 * JSON_HEX_TAG: een "<" in een tekst wordt <, dus niets kan het script-blok afsluiten.
 */
function pag_vacature_schema(array $pagina, ?array $seizoen, ?array $versie = null, ?\DateTimeImmutable $nu = null): string {
    if (!pag_indexeerbaar($pagina) || !$seizoen || ($seizoen['status'] ?? '') !== 'open') return '';
    $tz = new \DateTimeZone('Europe/Amsterdam');
    $nu = $nu ?? new \DateTimeImmutable('now', $tz);
    $einde = pag_vacature_einde($seizoen);
    if ($einde && $nu > $einde) return '';

    $g = pag_werving_gegevens($seizoen);
    $loon = pag_werving_loon($seizoen);

    // Beschrijving: korte HTML, alleen met wat bekend is.
    $punten = [];
    if ($g['werkperiode'] !== '')            $punten[] = 'Werkperiode: ' . $g['werkperiode'];
    if ($g['selectiedag_datum'] !== '')      $punten[] = 'Selectiedag: ' . $g['selectiedag_datum'];
    if ($g['trainingsweekend_datum'] !== '') $punten[] = 'Trainingsweekend: ' . $g['trainingsweekend_datum'];
    if ($g['aanmelden_tot'] !== '')          $punten[] = 'Aanmelden kan tot en met ' . $g['aanmelden_tot'];
    $punten[] = 'Geen ervaring nodig';
    $beschrijving = '<p>Word strandwacht bij Reddingsbrigade Paal 13 op het strand van Callantsoog.</p><ul>';
    foreach ($punten as $p) $beschrijving .= '<li>' . h($p) . '</li>';
    $beschrijving .= '</ul>';
    if ($loon['rijen'] && $loon['min'] !== null) {
        $beschrijving .= '<p>Indicatief uurloon ' . h(pag_euro($loon['min'])) . ' tot ' . h(pag_euro($loon['max']))
                       . ' bruto, afhankelijk van leeftijd en diploma, plus '
                       . h(function_exists('loon_getal') ? loon_getal($loon['vt'], 2) : (string)$loon['vt']) . '% vakantietoeslag. '
                       . h(pag_salaris_disclaimer()) . '</p>';
    }

    // Geplaatst: toen de huidige versie live ging, anders toen de pagina werd aangemaakt.
    $geplaatst = substr((string)($versie['scan_bevestigd_op'] ?? $pagina['aangemaakt_op'] ?? ''), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $geplaatst)) $geplaatst = $nu->format('Y-m-d');

    $job = [
        '@context'       => 'https://schema.org/',
        '@type'          => 'JobPosting',
        'title'          => 'Lifeguard (strandwacht)',
        'description'    => $beschrijving,
        'datePosted'     => $geplaatst,
    ];
    if ($einde) $job['validThrough'] = $einde->format('c');
    $job += [
        'employmentType'     => ['TEMPORARY'],
        'hiringOrganization' => ['@type' => 'Organization', 'name' => 'Reddingsbrigade Paal 13', 'sameAs' => 'https://paal13.com'],
        'jobLocation'        => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Callantsoog',
                                 'addressRegion' => 'Noord-Holland', 'addressCountry' => 'NL']],
        'directApply'        => true,
    ];
    if ($loon['min'] !== null) {
        $job['baseSalary'] = ['@type' => 'MonetaryAmount', 'currency' => 'EUR',
            'value' => ['@type' => 'QuantitativeValue', 'minValue' => $loon['min'], 'maxValue' => $loon['max'], 'unitText' => 'HOUR']];
    }

    $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    return $json === false ? '' : '<script type="application/ld+json">' . $json . '</script>';
}

// ── Campagnes: /p/<slug>?bron=instagram-mei ─────────────────────────────────
//
// De bron van de pagina blijft de bron (pagina-statistiek en trechter kloppen dus gewoon); de
// campagne komt erachter: sollicitaties.bron_code = "<paginabron>/<campagne>". Een paginabron is
// een slug (a-z0-9-), dus de "/" is ondubbelzinnig. pag_via_bron() knipt hem er weer af.

/** Campagnewaarde uit een adres: kleine letters, a-z 0-9 en koppeltekens, maximaal 40 tekens. */
function pag_campagne_schoon(string $ruw): string {
    $s = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($ruw)));
    return trim(substr(trim((string)$s, '-'), 0, 40), '-');
}

/**
 * De campagne van dit verzoek: het verborgen formulierveld, anders ?bron=, anders utm_campaign,
 * anders utm_source. Gelijk aan de eigen paginabron telt niet als campagne.
 */
function pag_campagne_uit(array $in, string $paginabron = ''): string {
    foreach (['p13_campagne', 'bron', 'utm_campaign', 'utm_source'] as $k) {
        if (!isset($in[$k]) || !is_string($in[$k])) continue;
        $c = pag_campagne_schoon($in[$k]);
        if ($c !== '' && $c !== $paginabron) return $c;
    }
    return '';
}

/** "<paginabron>/<campagne>", ingekort zodat het in bron_code (80 tekens) past. */
function pag_bron_met_campagne(string $bron, string $campagne): string {
    $campagne = pag_campagne_schoon($campagne);
    $ruimte = 80 - strlen($bron) - 1;
    if ($campagne === '' || $ruimte < 3) return $bron;
    $c = rtrim(substr($campagne, 0, $ruimte), '-');
    return $c === '' ? $bron : $bron . '/' . $c;
}

/** Een bron-code terug in stukken: ['bron' => …, 'campagne' => …]. */
function pag_bron_splits(string $code): array {
    $delen = explode('/', $code, 2);
    return ['bron' => $delen[0], 'campagne' => $delen[1] ?? ''];
}

/** De deellink van een pagina, met ?bron=<campagne> als er een campagne is. */
function pag_deel_url(array $pagina, string $campagne = ''): string {
    $c = pag_campagne_schoon($campagne);
    return pag_publieke_url($pagina) . ($c !== '' ? '?bron=' . rawurlencode($c) : '');
}

}

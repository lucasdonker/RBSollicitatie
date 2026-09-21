<?php
/**
 * Paal 13 — kleine UI-bouwstenen voor de beheeromgeving.
 *
 * Alles hier geeft een HTML-STRING terug en echoot nooit, zodat het in een tabelcel
 * of in een grotere string past. Blokken met een eigen indeling (tabel, filterbalk,
 * lege staat) zijn geen functie maar een partial in admin/_*.php.
 *
 * Twee regels die je niet moet versoepelen:
 *   1. Geen enkele functie neemt een `class`-parameter. Zodra een scherm er zijn eigen
 *      klasse in kan duwen, kan het afwijken en is de bouwsteen dood. Moet een scherm
 *      er anders uitzien? Verander de bouwsteen, niet het scherm.
 *   2. bh_stoplicht() en bh_weekcel() weigeren een lege tekst of een leeg getal. Dat
 *      dwingt "status nooit alleen met kleur" af in plaats van het af te spreken.
 *
 * Stijl: het ds-blok in assets/css/stijl.css en de bh-laag in assets/css/beheer.css.
 */

if (!function_exists('bh_badge')) {

/** De zeven kleurfamilies waarin de acht fases vallen (sleutel = uitvoer van fase_kleur()). */
function bh_fase_kleuren(): array {
    return ['grijs', 'blauw', 'oranje', 'teal', 'paars', 'groen', 'rood'];
}

/**
 * Eerste letter van een naam, als hoofdletter. Werkt op UTF-8 zonder mbstring —
 * de portable PHP van de dev-machine heeft die extensie niet, de server wel.
 */
function bh_eerste_letter(string $s): string {
    if (!preg_match('/\p{L}/u', $s, $m)) return '';
    return function_exists('mb_strtoupper') ? mb_strtoupper($m[0], 'UTF-8') : strtoupper($m[0]);
}

/**
 * Statusbadge in een fasekleur.
 *
 * @param string $tekst  wat er staat — verplicht, want kleur alleen is geen status
 * @param string $kleur  een van bh_fase_kleuren(); onbekend → grijs
 * @param array  $opt    stip:bool (bolletje vooraan) · titel:?string (tooltip)
 */
function bh_badge(string $tekst, string $kleur = 'grijs', array $opt = []): string {
    if (trim($tekst) === '') return '';                 // liever niets dan een kleurvlek
    if (!in_array($kleur, bh_fase_kleuren(), true)) $kleur = 'grijs';
    $titel = !empty($opt['titel']) ? ' title="' . h($opt['titel']) . '"' : '';
    $stip  = !empty($opt['stip']) ? '<span class="ds-stip"></span>' : '';
    return '<span class="ds-badge-fase ds-fase-' . $kleur . '"' . $titel . '>' . $stip . h($tekst) . '</span>';
}

/**
 * Statusbadge van een sollicitatiefase. Label en kleur komen uit één bron.
 *
 * @param array $opt  stip:bool · lengte:'vol'|'kort'|'mini' — 'kort' in een tabel of
 *                    filterchip ("Uitgenodigd TW"), 'mini' in de fase-knoppenrij
 *                    ("Uitg. TW"). De volle naam past daar niet en zou de cel breken.
 */
function bh_fase_badge(string $fase, array $opt = []): string {
    $lengte = $opt['lengte'] ?? 'vol';
    $tekst  = $lengte === 'vol' ? fase_label($fase) : fase_label_kort($fase, $lengte);
    // De volle naam blijft als tooltip beschikbaar zodra we afkorten.
    if ($lengte !== 'vol' && !isset($opt['titel']) && $tekst !== fase_label($fase)) {
        $opt['titel'] = fase_label($fase);
    }
    return bh_badge($tekst, fase_kleur($fase), $opt);
}

/**
 * Stoplicht: groen/oranje/rood mét een woord ernaast.
 * $tekst is verplicht — een kleur zonder woord is voor een kleurenblinde gebruiker niets.
 */
function bh_stoplicht(string $stand, string $tekst): string {
    if (trim($tekst) === '') return '';
    $kleur = ['goed' => 'groen', 'krap' => 'oranje', 'slecht' => 'rood'][$stand] ?? 'grijs';
    return '<span class="bh-stoplicht ds-fase-' . $kleur . '"><span class="bh-stoplicht-bol"></span>'
         . h($tekst) . '</span>';
}

/**
 * Avatar: foto als die er is, anders de initialen.
 *
 * @param array $persoon  voornaam/achternaam/foto_url (ontbrekende velden zijn geen fout)
 * @param int   $px       hoogte en breedte
 * @param array $opt      dim:bool (gedempt, bijv. bij afgewezen)
 */
function bh_avatar(array $persoon, int $px = 30, array $opt = []): string {
    $vn = trim((string)($persoon['voornaam'] ?? ''));
    $an = trim((string)($persoon['achternaam'] ?? ''));
    $init = bh_eerste_letter($vn) . bh_eerste_letter($an);
    if ($init === '') $init = '?';
    $stijl = 'width:' . $px . 'px;height:' . $px . 'px;font-size:' . max(10, (int)round($px * .37)) . 'px';
    $cls   = 'ds-avatar' . (!empty($opt['dim']) ? ' ds-avatar-dim' : '');
    $naam  = trim($vn . ' ' . $an);
    $foto  = (string)($persoon['foto_url'] ?? '');
    $inhoud = $foto !== ''
        ? '<img src="' . h(function_exists('foto_url') ? foto_url($foto) : $foto) . '" alt="">'
        : h($init);
    return '<span class="' . $cls . '" style="' . $stijl . '" aria-hidden="true" title="' . h($naam) . '">'
         . $inhoud . '</span>';
}

/** Chip voor een los kenmerk: diploma, competentie, "terugkerend". */
function bh_chip(string $tekst, string $kleur = 'grijs'): string {
    if (trim($tekst) === '') return '';
    if (!in_array($kleur, bh_fase_kleuren(), true)) $kleur = 'grijs';
    return '<span class="bh-chip ds-fase-' . $kleur . '">' . h($tekst) . '</span>';
}

/**
 * Voortgangsbalk met het getal ernaast ("5 / 6").
 * Zonder het getal is de balk alleen decoratie; daarom staat het er altijd bij.
 */
function bh_voortgang(int $gedaan, int $totaal, array $opt = []): string {
    $totaal = max(0, $totaal);
    $gedaan = max(0, min($gedaan, $totaal));
    $pct    = $totaal > 0 ? round($gedaan / $totaal * 100) : 0;
    $kleur  = $gedaan >= $totaal && $totaal > 0 ? 'groen' : 'blauw';
    $label  = $opt['label'] ?? ($gedaan . ' / ' . $totaal);
    return '<span class="bh-voortgang">'
         . '<span class="bh-voortgang-balk" role="img" aria-label="' . h($gedaan . ' van ' . $totaal) . '">'
         . '<span class="bh-voortgang-vul ds-fase-' . $kleur . '" style="width:' . $pct . '%"></span></span>'
         . '<span class="bh-voortgang-getal">' . h((string)$label) . '</span></span>';
}

/**
 * Eén cel in een weekraster (beschikbaarheid, dekking).
 *
 * @param string $tekst  wat er in de cel staat: "5", "wk", of "—" voor niets.
 *                       Verplicht: een gekleurd vlakje zonder inhoud zegt niets tegen
 *                       iemand die kleuren niet onderscheidt, en niets tegen een schermlezer.
 * @param string $soort  vol | weekend | deels | correctie | leeg  (onbekend → leeg)
 * @param array  $opt    titel:?string (tooltip, wordt ook aria-label) ·
 *                       klik:?string (JS-aanroep; maakt er een <button> van)
 */
function bh_weekcel(string $tekst, string $soort = 'leeg', array $opt = []): string {
    if (trim($tekst) === '') return '';
    if (!in_array($soort, ['vol', 'weekend', 'deels', 'correctie', 'leeg'], true)) $soort = 'leeg';
    $cls   = 'bh-weekcel bh-weekcel-' . $soort;
    $titel = !empty($opt['titel']) ? (string)$opt['titel'] : '';
    $attr  = $titel !== '' ? ' title="' . h($titel) . '" aria-label="' . h($titel) . '"' : '';
    if (empty($opt['klik'])) {
        return '<span class="' . $cls . '"' . $attr . '>' . h($tekst) . '</span>';
    }
    return '<button type="button" class="' . $cls . ' bh-weekcel-klik"' . $attr
         . ' onclick="' . h((string)$opt['klik']) . '">' . h($tekst) . '</button>';
}

/**
 * Meta-regel: losse stukjes met een punt ertussen ("19 jaar · Den Haag · 06 …").
 * Lege delen vallen weg, zodat je geen "· ·" krijgt bij een ontbrekend veld.
 */
function bh_meta(array $delen): string {
    $schoon = array_values(array_filter(array_map(fn($d) => trim((string)$d), $delen), fn($d) => $d !== ''));
    if (!$schoon) return '';
    return '<span class="bh-meta">' . implode(' <span class="bh-meta-punt">·</span> ', array_map('h', $schoon)) . '</span>';
}

/**
 * Het laatste moment uit een MAX(...)-query, of null als het niet lukt.
 *
 * Elk scherm weet zelf uit welke tabel en welke kolom zijn cijfers komen — die
 * verschillen echt (beschikbaarheid heeft ingevuld_op/definitief_op, loon_planning
 * heeft bijgewerkt_op). Daarom geeft het scherm de query, en doet deze functie alleen
 * het foutbestendige deel: een scherm mag nooit stukgaan op zijn eigen voettekst.
 */
function bh_laatst(string $sql, array $params = []): ?string {
    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        $w = $st->fetchColumn();
        return ($w === false || $w === null || $w === '') ? null : (string)$w;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * "Bijgewerkt om 14:03" onder een scherm vol cijfers.
 *
 * Bij Beschikbaarheid, Dekking en Loonplanning kijk je naar getallen zonder te weten
 * hoe vers ze zijn — en je weet dus ook niet of de wijziging die je net elders deed er
 * al in zit. Eén regel lost dat op.
 *
 * $moment is het moment waarop de gegevens zelf voor het laatst veranderden (een
 * MAX(bijgewerkt_op) uit de database), niet het moment van deze paginalading. Is dat
 * onbekend, geef dan null: dan zegt de regel alleen wanneer het scherm is opgehaald.
 */
function bh_versheid(?string $moment = null, string $wat = 'Gegevens'): string {
    $nu = 'opgehaald om ' . date('H:i');
    if ($moment === null || $moment === '') {
        return '<p class="bh-versheid">' . h($wat) . ' ' . h($nu) . '.</p>';
    }
    $ts = strtotime($moment);
    if ($ts === false) return '<p class="bh-versheid">' . h($wat) . ' ' . h($nu) . '.</p>';

    // Vandaag alleen de tijd, anders de datum erbij: "om 14:03" is nietszeggend als
    // het gisteren was.
    $zelfde_dag = date('Y-m-d', $ts) === date('Y-m-d');
    $tekst = $zelfde_dag
        ? 'bijgewerkt om ' . date('H:i', $ts)
        : 'bijgewerkt op ' . datum_nl(date('Y-m-d', $ts)) . ' om ' . date('H:i', $ts);
    return '<p class="bh-versheid">' . h($wat) . ' ' . h($tekst)
         . ' <span class="bh-meta-punt">·</span> ' . h($nu) . '.</p>';
}

} // function_exists

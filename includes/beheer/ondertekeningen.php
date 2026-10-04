<?php
/**
 * Paal 13 — Ondertekeningen-overzicht: per persoon wat er openstaat, filteren, sorteren en wie
 * al een herinnering kreeg. Geen HTML (admin/ondertekeningen.php toont het).
 *
 * Twee leeftijdsgrenzen die hier zichtbaar blijven: 18 is de ondertekengrens (daaronder tekent een
 * ouder mee), 15/16 is de aanmeld- en AVG-grens. Die twee zijn juridisch verschillend.
 */

/** Sorteringen van het overzicht: sleutel => label. */
function bh_ond_sorteringen(): array {
    return [
        'naam'      => 'Op naam',
        'open'      => 'Meeste open eerst',
        'oudst'     => 'Langst open eerst',
        'herinnerd' => 'Langst niet herinnerd',
    ];
}

/**
 * Alle ondertekeningen van het seizoen, gebundeld per persoon (je wilt weten wie er klaar is, niet
 * welk los formulier binnen is). Twee queries: de documenten en de laatste herinnering per document.
 *
 * @return array<int,array>  sol_id => naam, docs, klaar, totaal, ouder, open_ids, oudste_open, herinnerd, soorten
 */
function bh_ond_per_persoon(int $seizoen_id): array {
    if ($seizoen_id <= 0) return [];
    try {
        $q = db()->prepare("
            SELECT o.*, s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam
            FROM ondertekeningen o
            JOIN sollicitaties s ON s.id = o.sollicitatie_id
            JOIN personen p ON p.id = s.persoon_id
            WHERE s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt','verwijderd_door_sollicitant')
            ORDER BY p.achternaam, p.voornaam, o.aangemaakt_op
        ");
        $q->execute([$seizoen_id]);
        $rijen = $q->fetchAll();
    } catch (\Throwable $e) { error_log('bh_ond_per_persoon: ' . $e->getMessage()); return []; }

    $open_ids = [];
    foreach ($rijen as $o) if (empty($o['ondertekend_op'])) $open_ids[] = (int)$o['id'];
    $herinnerd = bh_ond_herinnerd($open_ids);

    $uit = [];
    foreach ($rijen as $o) {
        $sol = (int)$o['sol_id'];
        $uit[$sol] ??= [
            'naam' => trim($o['voornaam'] . ' ' . ($o['tussenvoegsel'] ? $o['tussenvoegsel'] . ' ' : '') . $o['achternaam']),
            'docs' => [], 'klaar' => 0, 'totaal' => 0, 'ouder' => false,
            'open_ids' => [], 'oudste_open' => null, 'herinnerd' => null, 'soorten' => [],
        ];
        $p = &$uit[$sol];
        $o['herinnerd'] = $herinnerd[(int)$o['id']] ?? null;
        $p['docs'][] = $o;
        $p['totaal']++;
        $p['soorten'][(string)$o['type']] = true;
        // Tekent er een ouder mee? Dan is deze sollicitant nog geen 18.
        if (str_ends_with((string)$o['type'], '_ouder') || ($o['ondertekenaar_rol'] ?? '') === 'ouder') $p['ouder'] = true;
        if (!empty($o['ondertekend_op'])) { $p['klaar']++; }
        else {
            $p['open_ids'][] = (int)$o['id'];
            $sinds = (string)($o['aangemaakt_op'] ?? '');
            if ($sinds !== '' && ($p['oudste_open'] === null || $sinds < $p['oudste_open'])) $p['oudste_open'] = $sinds;
            if ($o['herinnerd'] && ($p['herinnerd'] === null || $o['herinnerd'] > $p['herinnerd'])) $p['herinnerd'] = $o['herinnerd'];
        }
        unset($p);
    }
    return $uit;
}

/** Laatste herinnering per ondertekening (automatisch of handmatig), uit het berichtenlog: [id => datum-tijd]. */
function bh_ond_herinnerd(array $ond_ids): array {
    if (!$ond_ids) return [];
    $uit = [];
    try {
        $refs = array_map(fn($id) => 'ondertekening:' . (int)$id, $ond_ids);
        $q = db()->prepare("SELECT referentie, MAX(verstuurd_op) AS laatste FROM mail_log
                            WHERE meldingstype='ondertekening_herinnering' AND status IN ('wacht','bezig','verstuurd')
                              AND referentie IN (" . implode(',', array_fill(0, count($refs), '?')) . ")
                            GROUP BY referentie");
        $q->execute($refs);
        foreach ($q->fetchAll() as $r) {
            if (!empty($r['laatste'])) $uit[(int)substr((string)$r['referentie'], 14)] = (string)$r['laatste'];
        }
    } catch (\Throwable $e) { error_log('bh_ond_herinnerd: ' . $e->getMessage()); }
    return $uit;
}

/**
 * Filteren en sorteren.
 * @param array $f  status (alle|open|compleet) · q (naamdeel) · soort (documenttype) · ouder (''|ja|nee) · sort
 */
function bh_ond_filter(array $per_persoon, array $f): array {
    $status = (string)($f['status'] ?? 'alle');
    $q      = mb_strtolower(trim((string)($f['q'] ?? '')));
    $soort  = (string)($f['soort'] ?? '');
    $ouder  = (string)($f['ouder'] ?? '');
    $uit = array_filter($per_persoon, function ($p) use ($status, $q, $soort, $ouder) {
        $compleet = $p['klaar'] === $p['totaal'];
        if ($status === 'open' && $compleet) return false;
        if ($status === 'compleet' && !$compleet) return false;
        if ($q !== '' && strpos(mb_strtolower($p['naam']), $q) === false) return false;
        if ($soort !== '') {
            // Bij "nog open" telt alleen een document dat nog open staat.
            $docs = $status === 'open' ? array_filter($p['docs'], fn($o) => empty($o['ondertekend_op'])) : $p['docs'];
            if (!in_array($soort, array_column($docs, 'type'), true)) return false;
        }
        if ($ouder === 'ja' && !$p['ouder']) return false;
        if ($ouder === 'nee' && $p['ouder']) return false;
        return true;
    });
    $naam = fn($a, $b) => strcasecmp($a['naam'], $b['naam']);
    $cmp = [
        'open'      => fn($a, $b) => ($b['totaal'] - $b['klaar']) <=> ($a['totaal'] - $a['klaar']),
        'oudst'     => fn($a, $b) => ($a['oudste_open'] ?? '9999') <=> ($b['oudste_open'] ?? '9999'),
        // Nooit herinnerd komt eerst (''), daarna de langst geleden.
        'herinnerd' => fn($a, $b) => (($a['open_ids'] ? ($a['herinnerd'] ?? '') : '9999') <=> ($b['open_ids'] ? ($b['herinnerd'] ?? '') : '9999')),
    ][(string)($f['sort'] ?? 'naam')] ?? null;
    uasort($uit, fn($a, $b) => ($cmp ? $cmp($a, $b) : 0) ?: $naam($a, $b));
    return $uit;
}

/** De open ondertekening-ids van een lijst personen (voor "herinnering aan iedereen hierboven"). */
function bh_ond_open_ids(array $per_persoon): array {
    $ids = [];
    foreach ($per_persoon as $p) foreach ($p['open_ids'] as $id) $ids[] = $id;
    return $ids;
}

/** "5 dagen", "vandaag" — hoe lang iets al openstaat. */
function bh_ond_dagen_open(?string $sinds): string {
    if (!$sinds || !($ts = strtotime($sinds))) return '';
    $d = (int)floor((strtotime('today') - strtotime(date('Y-m-d', $ts))) / 86400);
    return $d <= 0 ? 'vandaag' : ($d === 1 ? '1 dag' : $d . ' dagen');
}

<?php
/**
 * Logboek — de tijdlijn opbouwen.
 *
 * Bouwt één UNION ALL over de beschikbare bronnen, sorteert op moment en pagineert. Doordat de
 * veld-expressies uit bronnen.php zowel in de SELECT als in de WHERE worden gebruikt, hoeft hier
 * niets per bron te worden uitgezonderd: een bron die een veld niet kent heeft daar 'NULL' staan
 * en valt bij een filter op dat veld vanzelf af.
 *
 * Alle filterwaarden gaan als PDO-parameter mee. Alleen LIMIT/OFFSET worden als int geïnterpoleerd
 * (dat kan niet als parameter) — die zijn hier altijd door (int) gehaald.
 */

if (!function_exists('log_zoek')) {

/** Lege filterset — ook het overzicht van wat een filter kan zijn. */
function log_filters_leeg(): array {
    return [
        'bron'       => '',   // bronsleutel; '' = alle
        'wie'        => '',   // naam, e-mail of id
        'actie'      => '',
        'objecttype' => '',
        'object_id'  => 0,
        'van'        => '',   // jjjj-mm-dd
        'tot'        => '',   // jjjj-mm-dd
        'ernst'      => '',   // info | waarschuwing | fout
        'zoek'       => '',   // vrije tekst over details, actie en wie
    ];
}

/** Filters uit $_GET halen en schoonmaken. */
function log_filters_uit_get(array $bron_data): array {
    $f = log_filters_leeg();
    $f['bron']       = trim((string)($bron_data['bron'] ?? ''));
    $f['wie']        = trim((string)($bron_data['wie'] ?? ''));
    $f['actie']      = trim((string)($bron_data['actie'] ?? ''));
    $f['objecttype'] = trim((string)($bron_data['object'] ?? ''));
    $f['object_id']  = (int)($bron_data['object_id'] ?? 0);
    $f['van']        = log_datum_schoon((string)($bron_data['van'] ?? ''));
    $f['tot']        = log_datum_schoon((string)($bron_data['tot'] ?? ''));
    $f['ernst']      = trim((string)($bron_data['ernst'] ?? ''));
    $f['zoek']       = trim((string)($bron_data['zoek'] ?? ''));
    if (!isset(log_bronnen()[$f['bron']])) $f['bron'] = '';
    if (!isset(log_ernsten()[$f['ernst']])) $f['ernst'] = '';
    return $f;
}

/** Alleen jjjj-mm-dd doorlaten; al het andere wordt leeg. */
function log_datum_schoon(string $d): string {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
}

/** Staat er een filter aan (bron niet meegerekend)? */
function log_filters_actief(array $f): bool {
    $leeg = log_filters_leeg();
    foreach ($f as $k => $v) {
        if ($k === 'bron') continue;
        if ($v !== $leeg[$k]) return true;
    }
    return false;
}

/**
 * Bouwt de UNION-tak voor één bron.
 * Retourneert null als de bron door de filters toch niets kan opleveren.
 *
 * @param array $args  wordt aangevuld met de parameters van deze tak (volgorde = SQL-volgorde)
 */
function log_tak(string $sleutel, array $bron, array $f, array &$args): ?string {
    $v = log_bron_velden($bron);

    // Een filter op een veld dat deze bron niet kent: tak overslaan in plaats van 'NULL = ?'
    // door de database te laten uitrekenen.
    if ($f['wie'] !== ''        && $v['wie_naam'] === 'NULL' && $v['wie_id'] === 'NULL') return null;
    if ($f['objecttype'] !== '' && $v['objecttype'] === 'NULL') return null;
    if ($f['object_id'] > 0     && $v['object_id'] === 'NULL') return null;

    $where = []; $tak_args = [];

    if ($f['wie'] !== '') {
        $stukken = [];
        if ($v['wie_naam'] !== 'NULL') { $stukken[] = $v['wie_naam'] . ' LIKE ?'; $tak_args[] = '%' . $f['wie'] . '%'; }
        // Alleen op id zoeken als er een getal is ingetypt, anders matcht '0' overal op.
        if ($v['wie_id'] !== 'NULL' && ctype_digit($f['wie'])) { $stukken[] = $v['wie_id'] . ' = ?'; $tak_args[] = (int)$f['wie']; }
        if (!$stukken) return null;
        $where[] = '(' . implode(' OR ', $stukken) . ')';
    }
    if ($f['actie'] !== '')      { $where[] = $v['actie'] . ' = ?';      $tak_args[] = $f['actie']; }
    if ($f['objecttype'] !== '') { $where[] = $v['objecttype'] . ' = ?'; $tak_args[] = $f['objecttype']; }
    if ($f['object_id'] > 0)     { $where[] = $v['object_id'] . ' = ?';  $tak_args[] = $f['object_id']; }
    if ($f['van'] !== '')        { $where[] = $v['moment'] . ' >= ?';    $tak_args[] = $f['van'] . ' 00:00:00'; }
    if ($f['tot'] !== '')        { $where[] = $v['moment'] . ' <= ?';    $tak_args[] = $f['tot'] . ' 23:59:59'; }
    if ($f['ernst'] !== '')      { $where[] = $v['ernst'] . ' = ?';      $tak_args[] = $f['ernst']; }

    if ($f['zoek'] !== '') {
        $stukken = [];
        foreach (['wie_naam', 'actie', 'details'] as $veld) {
            if ($v[$veld] === 'NULL') continue;
            $stukken[] = $v[$veld] . ' LIKE ?';
            $tak_args[] = '%' . $f['zoek'] . '%';
        }
        if (!$stukken) return null;
        $where[] = '(' . implode(' OR ', $stukken) . ')';
    }

    // Vaste kolomvolgorde. CAST op de tekstvelden: bij een UNION leidt MariaDB het resultaattype
    // af uit alle takken samen en kapt stil af als de breedtes verschillen.
    $kolommen = ["'" . $sleutel . "' AS bron"];
    foreach (log_velden() as $veld) {
        $expr = $v[$veld];
        if (!in_array($veld, ['moment', 'wie_id', 'object_id'], true)) $expr = 'CAST(' . $expr . ' AS CHAR)';
        $kolommen[] = $expr . ' AS ' . $veld;
    }

    foreach ($tak_args as $a) $args[] = $a;

    return 'SELECT ' . implode(', ', $kolommen)
         . ' FROM `' . $bron['tabel'] . '`'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
}

/**
 * Bouwt de volledige UNION voor de gevraagde filters.
 * Retourneert ['sql' => string, 'args' => array]; sql is '' als er niets te doorzoeken valt.
 */
function log_union(array $f): array {
    $args = []; $takken = [];
    foreach (log_bronnen_beschikbaar($f['bron']) as $sleutel => $bron) {
        $tak = log_tak($sleutel, $bron, $f, $args);
        if ($tak !== null) $takken[] = $tak;
    }
    return ['sql' => $takken ? implode(' UNION ALL ', $takken) : '', 'args' => $args];
}

/**
 * Eén pagina uit de tijdlijn. Nieuwste eerst.
 * @return array rijen met de velden uit log_velden(), plus 'bron'
 */
function log_zoek(array $f, int $pagina = 1, int $per_pagina = 50): array {
    $u = log_union($f);
    if ($u['sql'] === '') return [];
    $per_pagina = max(1, min(200, $per_pagina));
    $offset     = max(0, ($pagina - 1) * $per_pagina);
    try {
        $q = db()->prepare('SELECT * FROM (' . $u['sql'] . ') t ORDER BY moment DESC, bron ASC LIMIT ' . (int)$per_pagina . ' OFFSET ' . (int)$offset);
        $q->execute($u['args']);
        return $q->fetchAll();
    } catch (\Throwable $e) {
        error_log('log_zoek: ' . $e->getMessage());
        return [];
    }
}

/** Hoeveel regels voldoen aan deze filters? */
function log_tel(array $f): int {
    $u = log_union($f);
    if ($u['sql'] === '') return 0;
    try {
        $q = db()->prepare('SELECT COUNT(*) FROM (' . $u['sql'] . ') t');
        $q->execute($u['args']);
        return (int)$q->fetchColumn();
    } catch (\Throwable $e) {
        error_log('log_tel: ' . $e->getMessage());
        return 0;
    }
}

/** Aantal per bron, voor de tellers op de tabbladen. */
function log_tel_per_bron(array $f): array {
    $uit = [];
    foreach (log_bronnen_beschikbaar() as $sleutel => $_bron) {
        $eigen = $f; $eigen['bron'] = $sleutel;
        $uit[$sleutel] = log_tel($eigen);
    }
    return $uit;
}

/**
 * De acties die echt in het log voorkomen, voor de filterlijst. Uit de data, niet hardcoded:
 * een nieuwe actie in de code verschijnt zo vanzelf in het filter.
 */
function log_acties_aanwezig(string $bron = ''): array {
    $f = log_filters_leeg(); $f['bron'] = $bron;
    $u = log_union($f);
    if ($u['sql'] === '') return [];
    try {
        $q = db()->prepare('SELECT DISTINCT actie FROM (' . $u['sql'] . ') t WHERE actie IS NOT NULL AND actie <> \'\' ORDER BY actie');
        $q->execute($u['args']);
        return $q->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Throwable $e) {
        error_log('log_acties_aanwezig: ' . $e->getMessage());
        return [];
    }
}

/**
 * Alle regels voor de export: als log_zoek, maar zonder paginering en met één plafond.
 * Het plafond voorkomt dat een lege filter het geheugen opvreet.
 */
function log_zoek_alles(array $f, int $plafond = 10000): array {
    $plafond = max(1, min(50000, $plafond));
    $u = log_union($f);
    if ($u['sql'] === '') return [];
    try {
        $q = db()->prepare('SELECT * FROM (' . $u['sql'] . ') t ORDER BY moment DESC, bron ASC LIMIT ' . (int)$plafond);
        $q->execute($u['args']);
        return $q->fetchAll();
    } catch (\Throwable $e) {
        error_log('log_zoek_alles: ' . $e->getMessage());
        return [];
    }
}

}

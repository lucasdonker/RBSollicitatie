<?php
/**
 * Beheer › Logboek — export naar CSV.
 *
 * Neemt dezelfde filters als admin/logboek.php (uit $_GET) en schrijft de tijdlijn als CSV
 * met puntkomma's, zodat Excel in het Nederlands hem meteen goed opent.
 *
 * De export zelf wordt altijd vastgelegd: dit is een set persoonsgegevens die het systeem
 * verlaat. Wie hem maakte, met welke filters en hoeveel regels — dat hoort herleidbaar te zijn.
 */
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('logboek', 'lezen');

/** Meer dan dit in één bestand is geen overzicht meer, en kost te veel geheugen. */
const LOGBOEK_EXPORT_PLAFOND = 10000;

$f      = log_filters_uit_get($_GET);
$regels = log_zoek_alles($f, LOGBOEK_EXPORT_PLAFOND);
$totaal = log_tel($f);

// Vastleggen vóór de output: welke selectie is er uit het systeem gehaald?
$gebruikte_filters = array_filter($f, function ($v) { return $v !== '' && $v !== 0; });
audit_log('geexporteerd', 'logboek', null, [
    'regels'    => count($regels),
    'totaal'    => $totaal,
    'afgekapt'  => $totaal > count($regels) ? 'ja' : 'nee',
    'filters'   => $gebruikte_filters ? implode(', ', array_map(
        function ($k, $v) { return $k . '=' . $v; },
        array_keys($gebruikte_filters), $gebruikte_filters
    )) : 'geen',
]);

$bestandsnaam = 'paal13_logboek_' . ($f['bron'] !== '' ? $f['bron'] . '_' : '') . date('Y-m-d_Hi') . '.csv';
$bestandsnaam = preg_replace('/[^a-z0-9_\-\.]/i', '_', $bestandsnaam);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $bestandsnaam . '"');
header('X-Content-Type-Options: nosniff');

$bronnen = log_bronnen();
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");  // BOM: Excel toont accenten dan goed

fputcsv($out, ['Wanneer', 'Bron', 'Wie', 'Soort', 'IP', 'Actie', 'Objecttype', 'Object-id', 'Ernst', 'Details'], ';');

foreach ($regels as $r) {
    $soort = log_wie_soorten()[(string)($r['wie_type'] ?? '')] ?? null;
    fputcsv($out, [
        log_moment_tekst($r['moment'] ?? null),
        $bronnen[$r['bron']]['label'] ?? (string)$r['bron'],
        log_wie_naam($r),
        $soort ? $soort[0] : (string)($r['wie_type'] ?? ''),
        (string)($r['ip'] ?? ''),
        log_actie_label($r['actie'] ?? null),
        (string)($r['objecttype'] ?? ''),
        !empty($r['object_id']) ? (int)$r['object_id'] : '',
        log_ernsten()[(string)($r['ernst'] ?? 'info')] ?? (string)($r['ernst'] ?? ''),
        // Meerdere detailregels op één cel: nieuwe regels worden scheidingstekens
        implode(' | ', log_details_regels($r['details'] ?? null)),
    ], ';');
}

if ($totaal > count($regels)) {
    fputcsv($out, ['', '', '', '', '', '', '', '', '', 'Afgekapt op ' . LOGBOEK_EXPORT_PLAFOND
        . ' regels van ' . $totaal . '. Verfijn de filters voor de rest.'], ';');
}

fclose($out);
exit;

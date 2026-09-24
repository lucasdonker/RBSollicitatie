<?php
/**
 * Excel-export van de beschikbaarheid (opgave), per week of per dag.
 *   ?soort=week|dag  plus dezelfde filters als het scherm (q, groep, type, toon)
 *
 * Opbouw: includes/beschikbaarheid_export.php. Elke export komt in het auditlog: het bestand
 * bevat persoonsgegevens (naam, fase, wanneer iemand kan).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
require_once __DIR__ . '/../includes/beschikbaarheid_export.php';
vereis_recht('beschikbaarheid', 'lezen');

$seizoen = actief_seizoen();
if (!$seizoen) { flash('fout', 'Geen actief seizoen.'); redirect(APP_URL . '/admin/beschikbaarheid.php'); }

$soort  = ($_GET['soort'] ?? 'week') === 'dag' ? 'dag' : 'week';
$filter = bh_besch_filter($_GET);
$boek   = besch_export_boek($seizoen, bh_besch_raster($seizoen, $filter), $filter, $soort);
if (function_exists('audit_log')) audit_log('export', 'beschikbaarheid', null, ['soort' => $soort, 'personen' => $boek['personen'], 'filter' => array_filter($filter)]);
xlsx_download($boek['bladen'], $boek['bestand']);

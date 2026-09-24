<?php
/**
 * Excel-export van de loonplanning voor de loonadministratie (vervangt de oude CSV).
 *
 *   ?bron=afspraak             de vastgestelde afspraken (standaard; alleen na "definitief maken")
 *   ?bron=scenario&scenario=N  één scenario, met CONCEPT bovenaan elk tabblad
 *
 * Tabbladen en opbouw: includes/loonplan_export.php. Elke export komt in het auditlog: het
 * bestand bevat persoonsgegevens (naam, geboortedatum, inzet, loon).
 */
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('loonplanning', 'lezen');

$seizoen = actief_seizoen();
$sid     = $seizoen ? (int)$seizoen['id'] : 0;
if (!$sid) { flash('fout', 'Geen actief seizoen.'); redirect(APP_URL . '/admin/loonplanning.php'); }

$bron = ($_GET['bron'] ?? 'afspraak') === 'scenario' ? 'scenario' : 'afspraak';
$scenario_id = 0;
if ($bron === 'scenario') {
    $scenario_id = (int)($_GET['scenario'] ?? 0);
    // Alleen een scenario van dit seizoen.
    $ids = array_map('intval', array_column(loonplan_scenarios($sid), 'id'));
    if (!in_array($scenario_id, $ids, true)) $scenario_id = (int)(loonplan_actief_scenario($sid)['id'] ?? 0);
} elseif (!loonplan_definitief_op($sid)) {
    flash('fout', 'Er zijn nog geen vastgestelde afspraken. Maak de loonplanning eerst definitief, of exporteer een scenario (dat komt er als CONCEPT uit).');
    redirect(APP_URL . '/admin/loonplanning.php');
}

$boek = loonplan_export_boek($sid, $bron, $scenario_id);
if (function_exists('audit_log')) {
    audit_log('export', 'loonplanning', null, ['bron' => $bron, 'scenario' => $scenario_id ?: null, 'personen' => $boek['personen']]);
}
xlsx_download($boek['bladen'], $boek['bestand']);

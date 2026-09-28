<?php
/**
 * TW-stap Voorbereiden: wie komt er en wie mist nog beschikbaarheid.
 * Onderdeel van admin/trainingsweekend.php (die zet $sid, $kandidaten, $mag_beslissen, …).
 * De weergave is gedeeld met de selectiedag: admin/_voorbereiden.php.
 */
$_met_b    = tw_met_beschikbaarheid($sid);
$_zonder_b = count(array_filter($kandidaten, fn($k) => empty($_met_b[(int)$k['sol_id']])));

$vb_lijst        = $kandidaten;
$vb_post_url     = tw_url('voorbereiden');
$vb_filter_url   = APP_URL . '/admin/trainingsweekend.php';
$vb_verborgen    = ['stap' => 'voorbereiden'];
$vb_mag_wijzigen = $mag_beslissen;
$vb_mag_dossier  = $mag_dossier;
$vb_klaar_kop    = 'Beschikbaarheid';
$vb_klaar        = fn($k) => !empty($_met_b[(int)$k['sol_id']]) ? ['Opgegeven', 'groen'] : ['Ontbreekt', 'oranje'];
$vb_mist         = $_zonder_b > 0
    ? $_zonder_b . ' ' . ($_zonder_b === 1 ? 'persoon heeft' : 'personen hebben') . ' nog geen beschikbaarheid opgegeven — die heb je nodig voor het gesprek en de loonplanning.'
    : '';
$vb_leeg         = ['titel' => 'Nog niemand uitgenodigd', 'tekst' => 'Zodra je na de selectiedag mensen uitnodigt, staan ze hier.', 'icoon' => 'reddingsboei'];
require __DIR__ . '/../_voorbereiden.php';

<?php
/**
 * TW-stap Groepen: de groepsindeling van het weekend, precies als op de selectiedag
 * (admin/_groepsindeling.php). "Niet samen met" is dezelfde lijst als op de selectiedag.
 * Onderdeel van admin/trainingsweekend.php (die zet $sid, $kandidaten, $mag_voorbereiden).
 * De groep verschijnt bij het inchecken, op de tablet en bij beoordelen.
 */
$gi_ev = 'tw'; $gi_kandidaten = $kandidaten; $gi_groepen = evenement_groepen('tw', $sid);
$gi_relaties = evenement_relaties($sid); $gi_mag_bewerken = $mag_voorbereiden;
$gi_endpoint = tw_url('groepen');
require __DIR__ . '/../_groepsindeling.php';

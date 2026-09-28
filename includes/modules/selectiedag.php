<?php
// Module Selectiedag — logica in includes/selectiedag.php, schermen admin/selectie*.php en
// admin/selectiedag.php, tablet portaal/selectie_incheck.php. Zie includes/modules.php.
return [
    'sleutel'      => 'selectiedag',
    'label'        => 'Selectiedag',
    'omschrijving' => 'Voorbereiden (wie komt er), groepen indelen, inchecken (ook op een tablet), beoordelen, nabespreken en de bellijst.',
    'rechten'      => [
        'selectiedag' => ['label' => 'Selectiedag', 'groep' => 'Selectie', 'acties' => ['lezen', 'bewerken']],
    ],
    'nav'          => [
        ['groep' => 'Selectie', 'positie' => 20, 'id' => 'selectiedag', 'label' => 'Selectiedag',
         // ?start=1: begint op de stap die op dat moment aan de beurt is (admin/selectie_indeling.php).
         'url' => 'selectie_indeling.php?start=1', 'icoon' => 'kalender', 'module' => 'selectiedag'],
    ],
    'nav_alias'    => [
        'selectie_indeling' => 'selectiedag', 'selectie_inchecken' => 'selectiedag', 'selectie_voorbereiden' => 'selectiedag',
        'selectie_nabespreking' => 'selectiedag',
    ],
];

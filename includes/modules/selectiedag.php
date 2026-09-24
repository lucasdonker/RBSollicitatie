<?php
// Module Selectiedag — logica in includes/selectiedag.php, schermen admin/selectie*.php en
// admin/selectiedag.php, tablet portaal/selectie_incheck.php. Zie includes/modules.php.
return [
    'sleutel'      => 'selectiedag',
    'label'        => 'Selectiedag',
    'omschrijving' => 'Groepen indelen, inchecken (ook op een tablet), beoordelen, nabespreken en de bellijst.',
    'rechten'      => [
        'selectiedag' => ['label' => 'Selectiedag', 'groep' => 'Selectie', 'acties' => ['lezen', 'bewerken']],
    ],
    'nav'          => [
        ['groep' => 'Kandidaten', 'positie' => 20, 'id' => 'selectiedag', 'label' => 'Selectiedag',
         'url' => 'selectie_indeling.php', 'icoon' => 'kalender', 'module' => 'selectiedag'],
    ],
    'nav_alias'    => [
        'selectie_indeling' => 'selectiedag', 'selectie_inchecken' => 'selectiedag',
        'selectie_nabespreking' => 'selectiedag',
    ],
];

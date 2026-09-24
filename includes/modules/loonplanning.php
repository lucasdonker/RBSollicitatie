<?php
// Module Loonplanning — logica in includes/loonplan.php, scherm admin/loonplanning.php. Zie includes/modules.php.
return [
    'sleutel'      => 'loonplanning',
    'label'        => 'Loonplanning',
    'omschrijving' => 'Scenario\'s per persoon en per week, kosten tegen het budget, de vastgestelde afspraken en de export voor de loonadministratie.',
    'rechten'      => [
        'loonplanning' => ['label' => 'Loonplanning', 'groep' => 'Planning', 'acties' => ['lezen', 'bewerken']],
    ],
    'nav'          => [
        ['groep' => 'Inzet & loon', 'positie' => 30, 'id' => 'loonplanning', 'label' => 'Loonplanning',
         'url' => 'loonplanning.php', 'icoon' => 'grafiek', 'module' => 'loonplanning'],
    ],
];

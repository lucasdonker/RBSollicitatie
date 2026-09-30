<?php
// Module Loonplanning — logica in includes/loonplan.php, scherm admin/loonplanning.php. Zie includes/modules.php.
return [
    'sleutel'      => 'loonplanning',
    'label'        => 'Loonplanning',
    'omschrijving' => 'Scenario\'s per persoon en per week, kosten tegen het budget, de vastgestelde afspraken en de export voor de loonadministratie.',
    'rechten'      => [
        'loonplanning' => ['label' => 'Loonplanning', 'groep' => 'Planning', 'acties' => ['lezen', 'bewerken'],
            // Taakrechten (includes/rechten_taken.php).
            'taken' => [
                'bekijken'   => ['label' => 'Bekijken',   'basis' => 'lezen',    'uitleg' => 'De planning, kosten en exports zien.'],
                'plannen'    => ['label' => 'Plannen',    'basis' => 'bewerken', 'uitleg' => 'Weken plannen, scenario\'s maken en beheren, extra personen, en de afspraak in het TW-gesprek aanpassen.'],
                'definitief' => ['label' => 'Definitief maken', 'basis' => 'bewerken', 'uitleg' => 'Afspraken vastleggen (de sollicitant ziet ze), vastzetten en ontgrendelen na het gesprek.'],
            ]],
    ],
    'nav'          => [
        ['groep' => 'Inzet & loon', 'positie' => 30, 'id' => 'loonplanning', 'label' => 'Loonplanning',
         'url' => 'loonplanning.php', 'icoon' => 'grafiek', 'module' => 'loonplanning'],
    ],
];

<?php
// Module Trainingsweekend — logica in includes/trainingsweekend.php, scherm admin/trainingsweekend.php.
// Eigen recht sinds 2.105.0 (leende eerder 'selectiedag'). Zie includes/modules.php.
return [
    'sleutel'      => 'trainingsweekend',
    'label'        => 'Trainingsweekend',
    'omschrijving' => 'Aanwezigheid en observaties tijdens het weekend, de gesprekken met het bestuur en het besluit.',
    'rechten'      => [
        'trainingsweekend' => ['label' => 'Trainingsweekend', 'groep' => 'Selectie', 'acties' => ['lezen', 'bewerken']],
    ],
    'nav'          => [
        ['groep' => 'Kandidaten', 'positie' => 30, 'id' => 'trainingsweekend', 'label' => 'Trainingsweekend',
         'url' => 'trainingsweekend.php', 'icoon' => 'reddingsboei', 'module' => 'trainingsweekend'],
    ],
];

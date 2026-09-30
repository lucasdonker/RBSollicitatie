<?php
// Module Trainingsweekend — logica in includes/trainingsweekend.php, scherm admin/trainingsweekend.php.
// Eigen recht sinds 2.105.0 (leende eerder 'selectiedag'). Zie includes/modules.php.
return [
    'sleutel'      => 'trainingsweekend',
    'label'        => 'Trainingsweekend',
    'omschrijving' => 'Aanwezigheid en observaties tijdens het weekend, de gesprekken met het bestuur en het besluit.',
    'rechten'      => [
        'trainingsweekend' => ['label' => 'Trainingsweekend', 'groep' => 'Selectie', 'acties' => ['lezen', 'bewerken'],
            // Taakrechten (includes/rechten_taken.php); zelfde indeling als de selectiedag.
            'taken' => [
                'bekijken'     => ['label' => 'Bekijken',     'basis' => 'lezen',    'uitleg' => 'De deelnemers en de groepen zien.'],
                'inchecken'    => ['label' => 'Inchecken',    'basis' => 'lezen',    'uitleg' => 'Aanwezig of afgemeld tikken bij binnenkomst.'],
                'beoordelen'   => ['label' => 'Beoordelen',   'basis' => 'lezen',    'uitleg' => 'Observaties plaatsen tijdens het weekend (en de eigen weer weghalen).'],
                'voorbereiden' => ['label' => 'Voorbereiden', 'basis' => 'bewerken', 'uitleg' => 'Wie komt er, groepen indelen, de tabletcode en pincode, en het inchecken afsluiten.'],
                'gesprekken'   => ['label' => 'Gesprekken en besluiten', 'basis' => 'bewerken', 'uitleg' => 'Evaluatie, de gesprekken, aannemen of afwijzen, en observaties van anderen weghalen.'],
            ]],
    ],
    'nav'          => [
        ['groep' => 'Selectie', 'positie' => 30, 'id' => 'trainingsweekend', 'label' => 'Trainingsweekend',
         'url' => 'trainingsweekend.php', 'icoon' => 'reddingsboei', 'module' => 'trainingsweekend'],
    ],
];

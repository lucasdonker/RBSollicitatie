<?php
// Module Selectiedag — logica in includes/selectiedag.php, schermen admin/selectie*.php en
// admin/selectiedag.php, tablet portaal/selectie_incheck.php. Zie includes/modules.php.
return [
    'sleutel'      => 'selectiedag',
    'label'        => 'Selectiedag',
    'omschrijving' => 'Voorbereiden (wie komt er), groepen indelen, inchecken (ook op een tablet), beoordelen, nabespreken en de bellijst.',
    'rechten'      => [
        'selectiedag' => ['label' => 'Selectiedag', 'groep' => 'Selectie', 'acties' => ['lezen', 'bewerken'],
            // Taakrechten (includes/rechten_taken.php). Standaard: begeleider = de drie met basis lezen.
            'taken' => [
                'bekijken'     => ['label' => 'Bekijken',     'basis' => 'lezen',    'uitleg' => 'De groepsindeling en de kandidaten zien.'],
                'inchecken'    => ['label' => 'Inchecken',    'basis' => 'lezen',    'uitleg' => 'Aanwezig of afgemeld tikken bij binnenkomst.'],
                'beoordelen'   => ['label' => 'Beoordelen',   'basis' => 'lezen',    'uitleg' => 'Cijfers geven en observaties plaatsen (en de eigen observaties weer weghalen).'],
                'voorbereiden' => ['label' => 'Voorbereiden', 'basis' => 'bewerken', 'uitleg' => 'Wie komt er, groepen indelen, afmeldingen, de tabletcode en pincode, en het inchecken afsluiten.'],
                'nabespreken'  => ['label' => 'Nabespreken en besluiten', 'basis' => 'bewerken', 'uitleg' => 'De nabespreking, het besluit per kandidaat, de bellijst en observaties van anderen weghalen.'],
            ]],
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

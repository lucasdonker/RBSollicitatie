<?php
// Module Wervingspagina's — alles in includes/paginas/, router p.php, beheer admin/paginas*.php.
// Staat hij uit, dan geven /p/<slug> en de statistiek-ingang een 404. Zie includes/modules.php.
return [
    'sleutel'      => 'paginas',
    'label'        => "Wervingspagina's",
    'omschrijving' => 'Eigen wervingspagina\'s op /p/… met een ingebouwd aanmeldformulier en bezoekersstatistiek.',
    'rechten'      => [
        'paginas' => ['label' => "Wervingspagina's", 'groep' => 'Werving', 'acties' => ['lezen', 'bewerken', 'verwijderen']],
    ],
    'nav'          => [
        ['groep' => 'Communicatie', 'positie' => 20, 'id' => 'paginas', 'label' => "Wervingspagina's",
         'url' => 'paginas.php', 'icoon' => 'paginas', 'module' => 'paginas'],
    ],
];

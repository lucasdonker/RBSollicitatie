<?php
// Module Landingspagina's — alles in includes/paginas/, router p.php, beheer admin/paginas*.php.
// Staat hij uit, dan geven /p/<slug> en de statistiek-ingang een 404. Zie includes/modules.php.
return [
    'sleutel'      => 'paginas',
    'label'        => "Landingspagina's",
    'omschrijving' => 'Eigen landingspagina\'s op /p/… met een ingebouwd aanmeldformulier en bezoekersstatistiek.',
    'rechten'      => [
        'paginas' => ['label' => "Landingspagina's", 'groep' => 'Werving', 'acties' => ['lezen', 'bewerken', 'verwijderen']],
    ],
    'nav'          => [
        ['groep' => 'Communicatie', 'positie' => 20, 'id' => 'paginas', 'label' => "Landingspagina's",
         'url' => 'paginas.php', 'icoon' => 'paginas', 'module' => 'paginas'],
    ],
];

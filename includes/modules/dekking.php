<?php
// Module Dekking — logica in includes/bezetting.php en includes/beheer/dekking.php, scherm admin/dekking.php.
// De competenties uit Authentik (includes/competenties.php) horen bij de kern: het dossier toont ze ook.
return [
    'sleutel'      => 'dekking',
    'label'        => 'Dekking',
    'omschrijving' => 'Per week of de posten te bemannen zijn met de juiste competenties, met een bellijst.',
    'rechten'      => [
        'dekking' => ['label' => 'Dekking', 'groep' => 'Planning', 'acties' => ['lezen']],
    ],
    'nav'          => [
        ['groep' => 'Inzet & loon', 'positie' => 20, 'id' => 'dekking', 'label' => 'Dekking',
         'url' => 'dekking.php', 'icoon' => 'schild', 'module' => 'dekking'],
    ],
];

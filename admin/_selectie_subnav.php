<?php
/**
 * Partial: de stappen van de selectiedag.
 *
 * Het ontwerp toont de selectiedag als één scherm met tabbladen (Groepen · Inchecken ·
 * Beoordelen · Nabespreking · Bellijst). Achter de schermen zijn het aparte bestanden —
 * ze doen genoeg verschillends om dat te rechtvaardigen — maar voor wie ermee werkt is
 * het één scherm. Deze balk stond eerder in elk van die bestanden apart, met een eigen
 * stukje CSS erbij.
 *
 * Verwacht:
 *   $subnav_actief  string  voorbereiden|groepen|inchecken|beoordelen|nabespreking|bellijst
 *   $subnav_datum   ?string optioneel: de datum van de selectiedag, in de kop
 */
$subnav_actief = $subnav_actief ?? '';
$subnav_datum  = $subnav_datum ?? null;

// Wie alleen meedraait (begeleider, 'lezen') ziet Inchecken en Beoordelen: meer heeft hij op de
// dag niet nodig, en op een telefoon is elke extra stap ruis. Voorbereiden en Groepen blijven via
// een directe link te lezen, maar staan niet in de balk.
$_stappen = [
    'voorbereiden' => ['Voorbereiden', 'selectie_voorbereiden.php',  'selectiedag', 'bewerken'],
    'groepen'      => ['Groepen',      'selectie_indeling.php',      'selectiedag', 'bewerken'],
    'inchecken'    => ['Inchecken',    'selectie_inchecken.php',     'selectiedag', 'lezen'],
    'beoordelen'   => ['Beoordelen',   'selectiedag.php',            'selectiedag', 'lezen'],
    // Beslissen en bellen is bestuurswerk: een begeleider ziet deze twee niet.
    'nabespreking' => ['Nabespreking', 'selectie_nabespreking.php',  'selectiedag', 'bewerken'],
    'bellijst'     => ['Bellijst',     'selectie_nabespreking.php?tab=bellijst', 'selectiedag', 'bewerken'],
];
$sb_stappen = [];
foreach ($_stappen as $_k => $_s) {
    if (mag($_s[2], $_s[3])) $sb_stappen[$_k] = ['label' => $_s[0], 'url' => APP_URL . '/admin/' . $_s[1]];
}
$sb_titel = 'Selectiedag'; $sb_datum = $subnav_datum; $sb_actief = $subnav_actief;
require __DIR__ . '/_stappenbalk.php';   // zelfde kop als het trainingsweekend
$subnav_actief = ''; $subnav_datum = null;

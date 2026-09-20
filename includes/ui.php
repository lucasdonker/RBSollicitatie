<?php
/**
 * Paal 13 — Kleine, herbruikbare UI-bouwstenen (HTML-snippets die op meerdere schermen terugkomen).
 * Eén plek, zodat een aanpassing overal doorwerkt. Gedrag: assets/js/app.js. Stijl: het "UI-BOUWSTENEN"-blok
 * in assets/css/stijl.css.
 */

if (!function_exists('info_bol')) {

/**
 * Info-bolletje: een klein rond knopje met "i" dat op aantikken een korte uitleg openklapt.
 * Gebruik naast een kopje of label, bijvoorbeeld:
 *   <span class="ond-teken-titel">Jouw handtekening <?= info_bol('Waarom?', 'Korte uitleg…') ?></span>
 *
 * @param string $titel  kop van het uitlegblokje (kort)
 * @param string $tekst  de uitleg zelf; regels scheiden met "\n" wordt een nieuwe alinea
 */
function info_bol(string $titel, string $tekst): string {
    static $n = 0;
    $id = 'infobol' . (++$n);
    $alineas = '';
    foreach (preg_split('/\n+/', trim($tekst)) ?: [] as $alinea) {
        if (trim($alinea) !== '') $alineas .= '<span class="ui-info-tekst">' . h(trim($alinea)) . '</span>';
    }
    return '<span class="ui-info">'
         . '<button type="button" class="ui-info-knop" aria-expanded="false" aria-controls="' . $id . '" aria-label="Uitleg: ' . h($titel) . '">'
         . icoon('info', 15) . '</button>'
         . '<span class="ui-info-paneel" id="' . $id . '" role="note" hidden>'
         . '<span class="ui-info-titel">' . h($titel) . '</span>' . $alineas
         . '<button type="button" class="ui-info-sluit">Sluiten</button>'
         . '</span></span>';
}

} // function_exists

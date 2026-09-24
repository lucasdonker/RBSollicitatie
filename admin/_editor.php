<?php
/**
 * Paal 13 — gedeelde e-mail-editor (partial) voor het beheer.
 *
 *   <?= editor_html('body', 'body_html', $html) ?>
 *   JS: assets/js/editor.js (P13Editor). CSS: .p13-editor in stijl.css.
 *
 * @param string $id     uniek id (ook gebruikt voor P13Editor)
 * @param string $naam   name-attribuut van het verborgen textarea (de inhoud die gepost wordt)
 * @param string $html   begin-inhoud
 * @param array  $opties ['hoogte' => 300, 'placeholder' => '...']
 */
if (!function_exists('editor_html')) {
function editor_html(string $id, string $naam, string $html = '', array $opties = []): string {
    $h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $hoogte = (int)($opties['hoogte'] ?? 300);
    $knop = fn(string $attr, string $label, string $titel) => '<button type="button" class="p13-ek" ' . $attr . ' title="' . $h($titel) . '">' . $label . '</button>';
    $sep  = '<span class="p13-esep"></span>';
    return '<div class="p13-editor" id="' . $h($id) . '" data-auto="1">
  <div class="p13-etabs">
    <button type="button" class="p13-etab actief" id="' . $h($id) . '_tab_vis">Visueel</button>
    <button type="button" class="p13-etab" id="' . $h($id) . '_tab_html">HTML</button>
  </div>
  <div class="p13-etoolbar" id="' . $h($id) . '_toolbar">
    <select id="' . $h($id) . '_blok" class="p13-eblok" title="Tekststijl"><option value="">Alinea/kop</option><option value="p">Normaal</option><option value="h2">Kop</option><option value="h3">Subkop</option></select>
    ' . $sep . $knop('data-cmd="bold"', '<strong>B</strong>', 'Vet') . $knop('data-cmd="italic"', '<em>I</em>', 'Schuin') . $knop('data-cmd="underline"', '<u>U</u>', 'Onderstrepen') . '
    ' . $sep . $knop('data-cmd="insertUnorderedList"', 'Lijst', 'Opsomming') . $knop('data-cmd="insertOrderedList"', '1. Lijst', 'Nummering') . '
    ' . $sep . $knop('data-actie="link"', 'Link', 'Link invoegen') . $knop('data-cmd="unlink"', 'Link weg', 'Link verwijderen') . '
    ' . $sep . $knop('data-actie="knop"', 'Knop', 'Oranje knop (shortcode)') . $knop('data-actie="info"', 'Info-blok', 'Gekleurd info-blok') . $knop('data-actie="letop"', 'Let op', 'Waarschuwingsblok') . $knop('data-actie="afbeelding"', 'Afbeelding', 'Afbeelding via URL') . $knop('data-actie="lijn"', 'Lijn', 'Horizontale lijn') . '
    ' . $sep . $knop('data-cmd="undo"', 'Ongedaan', 'Ongedaan maken') . $knop('data-cmd="redo"', 'Opnieuw', 'Opnieuw') . $knop('data-cmd="removeFormat"', 'Opmaak wissen', 'Opmaak verwijderen') . '
  </div>
  <div class="p13-evis" id="' . $h($id) . '_vis" contenteditable="true" spellcheck="true" lang="nl" style="--h: ' . $hoogte . 'px"' . (!empty($opties['placeholder']) ? ' data-placeholder="' . $h($opties['placeholder']) . '"' : '') . '></div>
  <textarea name="' . $h($naam) . '" id="' . $h($id) . '_html" class="p13-ehtml" style="--h: ' . $hoogte . 'px; display: none">' . $h($html) . '</textarea>
</div>';
}
}

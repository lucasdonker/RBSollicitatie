<?php
/**
 * Partial: de fase-specifieke infokaart op de startpagina (ontwerp 02 + 08 "afgewezen").
 * Verwacht $sol (portaal_sollicitant_laden()). Toont niets als er voor de fase geen info is.
 *
 *  aangemeld              → info-melding "Aanmelding ontvangen" (tekst: instelling portaal.welkomst_tekst)
 *  uitgenodigd_selectie / aangemeld_selectie → navy kaart SELECTIEDAG
 *  uitgenodigd_tw         → navy kaart TRAININGSWEEKEND
 *  afgewezen              → kaart "Dit seizoen niet verder" + AVG-acties
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$_fase = $sol['fase'] ?? '';
$_kaart = null; // navy infokaart: label, datum, tijd, locatie, meenemen
if (in_array($_fase, ['uitgenodigd_selectie', 'aangemeld_selectie'], true) && !empty($sol['datum_selectiedag'])) {
    $_kaart = [
        'label'    => 'Selectiedag',
        'datum'    => portaal_datum_lang($sol['datum_selectiedag']),
        'tijd'     => portaal_tijdregel($sol['selectiedag_verzameltijd'] ?? null, $sol['selectiedag_starttijd'] ?? null, $sol['selectiedag_eindtijd'] ?? null),
        'locatie'  => trim(implode(', ', array_filter([$sol['locatie_selectiedag'] ?? '', $sol['adres_selectiedag'] ?? '']))),
        'meenemen' => trim((string)($sol['selectiedag_meenemen'] ?? '')),
    ];
} elseif ($_fase === 'uitgenodigd_tw' && !empty($sol['datum_tw_start'])) {
    $_datum = portaal_datum_lang($sol['datum_tw_start']);
    if (!empty($sol['datum_tw_eind']) && $sol['datum_tw_eind'] !== $sol['datum_tw_start']) $_datum .= ' t/m ' . lcfirst(portaal_datum_lang($sol['datum_tw_eind']));
    $_kaart = [
        'label'    => 'Trainingsweekend',
        'datum'    => $_datum,
        'tijd'     => portaal_tijdregel($sol['tw_verzameltijd'] ?? null, $sol['tw_begintijd'] ?? null, $sol['tw_eindtijd'] ?? null),
        'locatie'  => trim(implode(', ', array_filter([$sol['locatie_tw'] ?? '', $sol['adres_tw'] ?? '']))),
        'meenemen' => trim((string)($sol['tw_meenemen'] ?? '')),
    ];
}
?>
<?php if ($_kaart): ?>
<div class="pt-infokaart">
  <div class="pt-infokaart-label"><?= icoon('kalender', 18) ?><span><?= h($_kaart['label']) ?></span></div>
  <span class="pt-infokaart-datum"><?= h($_kaart['datum']) ?></span>
  <?php if ($_kaart['tijd'] !== '' || $_kaart['locatie'] !== ''): ?>
  <div class="pt-infokaart-regels">
    <?php if ($_kaart['tijd'] !== ''): ?><div class="pt-infokaart-regel"><?= icoon('klok', 17) ?><span><?= h($_kaart['tijd']) ?></span></div><?php endif; ?>
    <?php if ($_kaart['locatie'] !== ''): ?><div class="pt-infokaart-regel"><?= icoon('locatie', 17) ?><span><?= h($_kaart['locatie']) ?></span></div><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if ($_kaart['meenemen'] !== ''): ?>
  <div class="pt-infokaart-scheiding"></div>
  <span class="pt-infokaart-meenemen"><?= nl2br(h($_kaart['meenemen'])) ?></span>
  <?php endif; ?>
</div>

<?php elseif ($_fase === 'aangemeld'): ?>
<div class="pt-melding info">
  <?= icoon('info', 20) ?>
  <div class="pt-melding-tekst">
    <span class="pt-melding-titel">Aanmelding ontvangen</span>
    <span class="pt-melding-body"><?= h((function_exists('instelling') ? instelling('portaal.welkomst_tekst', '') : '') ?: 'Zodra we je uitnodigen voor de selectiedag krijg je bericht.') ?></span>
  </div>
</div>

<?php elseif ($_fase === 'afgewezen'): ?>
<div class="pt-stapel">
  <div class="pt-afgewezen">
    <div class="pt-afgewezen-kop">
      <span class="pt-taakrij-icoon error"><?= icoon('kruis-cirkel', 20) ?></span>
      <div class="pt-afgewezen-tekst">
        <span class="pt-afgewezen-titel">Dit seizoen niet verder</span>
        <p>Na de selectie hebben we besloten je dit seizoen niet aan te nemen. Dat zegt niets over volgend jaar — je bent dan opnieuw welkom.</p>
      </div>
    </div>
    <div class="pt-afgewezen-scheiding"></div>
    <p>Wil je weten waarop we hebben gelet? Mail <a href="mailto:<?= h(portaal_contact_email()) ?>"><?= h(portaal_contact_email()) ?></a> — we vertellen het graag persoonlijk.</p>
  </div>
  <div class="pt-takenlijst">
    <a href="<?= APP_URL ?>/portaal/gegevens.php#download" class="pt-taakrij">
      <span class="pt-taakrij-icoon navy"><?= icoon('download', 20) ?></span>
      <span class="pt-taakrij-tekst"><span class="pt-taakrij-titel">Mijn gegevens downloaden</span><span class="pt-taakrij-sub">Alles wat we bewaren, als bestand</span></span>
      <span class="pt-taakrij-chevron"><?= icoon('chevron', 18) ?></span>
    </a>
    <div class="pt-taakrij-scheiding"></div>
    <a href="<?= APP_URL ?>/portaal/gegevens.php#verwijderen" class="pt-taakrij">
      <span class="pt-taakrij-icoon error"><?= icoon('prullenbak', 20) ?></span>
      <span class="pt-taakrij-tekst"><span class="pt-taakrij-titel">Gegevens laten verwijderen</span><span class="pt-taakrij-sub">Je portaal wordt dan gesloten</span></span>
      <span class="pt-taakrij-chevron"><?= icoon('chevron', 18) ?></span>
    </a>
  </div>
  <span class="pt-hulp">We bewaren je gegevens maximaal 2 jaar na het seizoen.</span>
</div>
<?php endif; ?>

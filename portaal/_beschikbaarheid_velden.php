<?php
/**
 * Beschikbaarheidsformulier — structuur, exact volgens Portaal.dc sectie 4b.
 * Verwacht: $sol (sollicitant). De rijen (werkperiodes, vakanties, losse dagen, verzoeken) bouwt
 * assets/js/beschikbaarheid.js op uit $besch_data (→ BESCH_CFG.data); de veldnamen (b2_*) blijven
 * gelijk aan wat includes/beschikbaarheid_post.php verwerkt.
 * Zet: $besch_data (opgeslagen data in het gedeelde JS-formaat).
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
if (!isset($sol)) { return; }

$b2  = $b2 ?? (function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($sol['id']) : ['profiel'=>null,'blokken'=>[],'afwezig'=>[],'verzoeken'=>[]]);
$b2p = $b2['profiel'] ?? null;
$losse_dagen = function_exists('beschikbaarheid_dagen') ? beschikbaarheid_dagen($sol['id']) : [];
$dag_namen = [1=>'ma',2=>'di',3=>'wo',4=>'do',5=>'vr',6=>'za',7=>'zo'];
$ico = function($naam, $g=18) { return function_exists('icoon') ? icoon($naam, $g) : ''; };

// Opgeslagen data → gedeeld JS-formaat (zelfde vorm als het concept en het AI-voorstel)
$besch_data = [
    'periodes'  => array_values(array_map(fn($b) => ['van' => (int)$b['van_week'], 'tot' => (int)$b['tot_week'], 'regime' => $b['regime'] === 'weekend' ? 'weekend' : 'vijf', 'wkdagen' => array_values(array_map('intval', $b['weekend_dagen'] ?: []))], $b2['blokken'] ?? [])),
    'vaste'     => array_values(array_map('intval', $b2p['vaste_dagen_uit'] ?? [])),
    'vaste_toelichting' => (string)($b2p['vaste_toelichting'] ?? ''),
    'vakanties' => array_values(array_map(fn($a) => ['van' => $a['van_datum'] ?? '', 'tot' => $a['tot_datum'] ?? '', 'label' => (string)($a['label'] ?? '')], $b2['afwezig'] ?? [])),
    'losse'     => array_values(array_map(fn($l) => ['datum' => $l['datum'] ?? '', 'soort' => $l['soort'] ?? 'niet_beschikbaar', 'reden' => (string)($l['reden'] ?? '')], $losse_dagen)),
    // Afgewezen verzoeken blijven uit het formulier (wel zichtbaar op het overzicht)
    'verzoeken' => array_values(array_map(fn($v) => ['id' => (int)$v['id'], 'status' => $v['status'] ?? 'open', 'van' => (int)$v['van_week'], 'tot' => (int)$v['tot_week'], 'dagen' => (int)$v['dagen_aantal'], 'pool' => array_values(array_map('intval', $v['dagen_pool'] ?? [])), 'toelichting' => (string)($v['toelichting'] ?? '')],
                     array_filter($b2['verzoeken'] ?? [], fn($v) => ($v['status'] ?? 'open') !== 'afgewezen'))),
    'voorlopig' => !empty($b2p['voorlopig']),
];
$besch_heeft_data = !empty($besch_data['periodes']);

// Eén sectie-kop voor de vier "alleen als het van toepassing is"-secties
$sectie_kop = function(string $titel) use ($ico): string {
    return '<button type="button" class="bs-uitz-kop" aria-expanded="false" onclick="beschSectieToggle(this)">'
         . '<span class="bs-uitz-tekst"><span class="bs-uitz-titel">' . h($titel) . '</span><span class="bs-uitz-sub">Niets ingevuld</span></span>'
         . '<span class="bs-uitz-actie"><span class="bs-uitz-toevoeg-lbl">Toevoegen</span><span class="bs-uitz-chev">' . $ico('chevron', 18) . '</span></span>'
         . '</button>';
};
?>

<!-- Jouw samenvatting (navy) — bovenaan, exact volgens ontwerp -->
<div class="bs-samenvatting">
  <span class="bs-samenvatting-lbl">Jouw samenvatting</span>
  <span class="bs-samenvatting-tekst" id="beschPreview">Vul hieronder je beschikbaarheid in.</span>
</div>

<!-- Wanneer kun je werken? -->
<div class="bs-kaart">
  <div class="bs-kaart-kop">
    <span class="bs-kaart-titel">Wanneer kun je werken?</span>
    <span class="bs-kaart-sub">Kies je werkperiodes — dit is het enige dat je echt hoeft in te vullen.</span>
  </div>
  <div id="beschPeriodes"></div>
  <button type="button" class="bs-toevoeg-knop" onclick="beschPeriodeToevoegen()"><?= $ico('plus', 16) ?> Nog een periode</button>
</div>

<span class="bs-sectie-lbl">Alleen als het van toepassing is</span>

<!-- Vier ingeklapte secties met samenvattingsregel; uitklappen om te bewerken -->
<div class="bs-uitz-kaart">

  <!-- Vaste vrije dag -->
  <div class="bs-uitz-sec" data-sectie="vaste">
    <?= $sectie_kop('Vaste vrije dag') ?>
    <div class="bs-uitz-body" hidden>
      <div class="bs-dagpillen">
        <?php foreach ($dag_namen as $n => $kort): $weekend = ($n >= 6); ?>
          <label class="bs-dagpil <?= $weekend ? 'weekend' : '' ?>">
            <input type="checkbox" class="besch-vastedag" value="<?= $n ?>" <?= $weekend ? 'disabled' : '' ?> onchange="beschVasteToggle(this)">
            <?= $kort ?>
          </label>
        <?php endforeach; ?>
      </div>
      <span class="bs-uitz-uitleg">Bijvoorbeeld vanwege school of een bijbaan. Weekenden kunnen niet vast vrij.</span>
      <input type="text" name="b2_vaste_toelichting" id="beschVasteToelichting" placeholder="Toelichting (optioneel)" class="bs-invoer" maxlength="300">
    </div>
  </div>

  <div class="bs-uitz-lijn"></div>

  <!-- Vakanties (afwezig) -->
  <div class="bs-uitz-sec" data-sectie="vakanties">
    <?= $sectie_kop('Vakanties') ?>
    <div class="bs-uitz-body" hidden>
      <div id="beschAfwezig"></div>
      <button type="button" class="bs-uitz-toevoeg" onclick="beschVakantieToevoegen()">+ Nog een vakantie</button>
    </div>
  </div>

  <div class="bs-uitz-lijn"></div>

  <!-- Minder dagen werken (verzoek aan het bestuur) -->
  <div class="bs-uitz-sec" data-sectie="verzoeken">
    <?= $sectie_kop('Minder dagen werken') ?>
    <div class="bs-uitz-body" hidden>
      <span class="bs-uitz-uitleg">Standaard gaan we uit van 5 dagen per week. Minder in bepaalde weken? Geef het door als verzoek — het bestuur beoordeelt dit.</span>
      <div id="beschVerzoeken"></div>
      <button type="button" class="bs-uitz-toevoeg" onclick="beschVerzoekToevoegen()">+ Nog een verzoek</button>
    </div>
  </div>

  <div class="bs-uitz-lijn"></div>

  <!-- Losse dagen niet beschikbaar -->
  <div class="bs-uitz-sec" data-sectie="losse">
    <?= $sectie_kop('Losse dagen niet beschikbaar') ?>
    <div class="bs-uitz-body" hidden>
      <div id="beschLosseDagen"></div>
      <button type="button" class="bs-uitz-toevoeg" onclick="beschLosseDagToevoegen()">+ Nog een dag</button>
    </div>
  </div>

</div>

<label class="bs-voorlopig">
  <input type="checkbox" name="b2_voorlopig" value="1" onchange="beschHerbereken()">
  Mijn beschikbaarheid is nog voorlopig — ik werk het later bij.
</label>

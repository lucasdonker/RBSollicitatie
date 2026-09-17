<?php
/**
 * Beschikbaarheidsformulier — velden, exact volgens Portaal.dc sectie 4b.
 * Verwacht: $sol (sollicitant) en $alle_weken. Behoudt de veldnamen/id's die
 * assets/js/beschikbaarheid.js gebruikt (b2_*-velden, beschPeriodes, etc.).
 */
if (!isset($sol) || !isset($alle_weken)) { return; }

$b2  = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($sol['id']) : ['profiel'=>null,'blokken'=>[],'afwezig'=>[],'verzoeken'=>[]];
$b2p = $b2['profiel'] ?? null;
$losse_dagen = function_exists('beschikbaarheid_dagen') ? beschikbaarheid_dagen($sol['id']) : [];
$vaste_sel = array_map('intval', $b2p['vaste_dagen_uit'] ?? []);
$dag_namen = [1=>'ma',2=>'di',3=>'wo',4=>'do',5=>'vr',6=>'za',7=>'zo'];
$ico = function($naam, $g=18) { return function_exists('icoon') ? icoon($naam, $g) : ''; };
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

<!-- Uitzonderingen-kaart met sub-secties -->
<div class="bs-uitz-kaart">

  <!-- Vaste vrije dag -->
  <div class="bs-uitz-sec">
    <div class="bs-uitz-kop">
      <span class="bs-uitz-titel">Vaste vrije dag</span>
      <span class="bs-uitz-hint" id="beschVasteHint"><?= $vaste_sel ? 'Elke ' . h($dag_namen[$vaste_sel[0]] ?? '') : 'Geen' ?></span>
    </div>
    <div class="bs-dagpillen">
      <?php foreach ($dag_namen as $n => $kort): $weekend = ($n >= 6); ?>
        <label class="bs-dagpil <?= $weekend ? 'weekend' : '' ?> <?= in_array($n, $vaste_sel, true) ? 'actief' : '' ?>">
          <input type="checkbox" class="besch-vastedag" value="<?= $n ?>" <?= in_array($n, $vaste_sel, true) ? 'checked' : '' ?> <?= $weekend ? 'disabled' : '' ?> onchange="beschVasteToggle(this)">
          <?= $kort ?>
        </label>
      <?php endforeach; ?>
    </div>
    <span class="bs-uitz-uitleg">Bijvoorbeeld vanwege school of een bijbaan. Weekenden kunnen niet vast vrij.</span>
    <?php foreach ($vaste_sel as $vd): ?><input type="hidden" name="b2_vaste_uit[]" value="<?= (int)$vd ?>" class="besch-vaste-hidden"><?php endforeach; ?>
    <input type="text" name="b2_vaste_toelichting" id="beschVasteToelichting" value="<?= h($b2p['vaste_toelichting'] ?? '') ?>" placeholder="Toelichting (optioneel)" class="bs-invoer" style="margin-top:10px">
  </div>

  <div class="bs-uitz-lijn"></div>

  <!-- Uitzonderingen (afwezig) -->
  <div class="bs-uitz-sec">
    <div class="bs-uitz-kop">
      <span class="bs-uitz-titel">Uitzonderingen</span>
      <button type="button" class="bs-uitz-toevoeg" onclick="beschAfwezigToevoegen()">+ Toevoegen</button>
    </div>
    <div id="beschAfwezig">
      <?php foreach ($b2['afwezig'] as $a): ?>
      <div class="besch-rij besch-afwezig bs-item-rij">
        <div class="bs-item-tekst">
          <div class="bs-item-datums">
            <input type="date" name="b2_afw_van[]" value="<?= h($a['van_datum'] ?? '') ?>" onchange="beschHerbereken()" aria-label="Van">
            <span>t/m</span>
            <input type="date" name="b2_afw_tot[]" value="<?= h($a['tot_datum'] ?? '') ?>" onchange="beschHerbereken()" aria-label="Tot">
          </div>
          <input type="text" name="b2_afw_label[]" value="<?= h($a['label'] ?? '') ?>" placeholder="reden (bijv. vakantie)" class="bs-invoer" style="margin-top:8px" onchange="beschHerbereken()">
        </div>
        <button type="button" class="bs-item-wis" onclick="this.closest('.besch-rij').remove(); beschHerbereken()" aria-label="Verwijderen"><?= $ico('prullenbak', 16) ?></button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="bs-uitz-lijn"></div>

  <!-- Minder dagen werken -->
  <div class="bs-uitz-sec">
    <div class="bs-uitz-kop">
      <span class="bs-uitz-titel">Minder dagen werken</span>
      <button type="button" class="bs-uitz-toevoeg" onclick="beschVerzoekToevoegen()">+ Verzoek</button>
    </div>
    <span class="bs-uitz-uitleg">Standaard gaan we uit van 5 dagen per week. Minder? Geef het door als verzoek — het bestuur beoordeelt dit.</span>
    <div id="beschVerzoeken" style="margin-top:10px">
      <?php foreach ($b2['verzoeken'] as $vz): if (($vz['status'] ?? 'open')==='afgewezen') continue; ?>
      <div class="besch-rij besch-verzoek bs-item-rij">
        <div class="bs-item-tekst">
          <div class="bs-item-datums">
            <span class="bs-lbl-mini">week</span>
            <select name="b2_vz_van[]" onchange="beschHerbereken()" class="bs-mini-select"><?php foreach ($alle_weken as $w): ?><option value="<?= $w['week'] ?>" <?= (int)$w['week']===(int)($vz['van_week']??0)?'selected':'' ?>><?= $w['week'] ?></option><?php endforeach; ?></select>
            <span>t/m</span>
            <select name="b2_vz_tot[]" onchange="beschHerbereken()" class="bs-mini-select"><?php foreach ($alle_weken as $w): ?><option value="<?= $w['week'] ?>" <?= (int)$w['week']===(int)($vz['tot_week']??0)?'selected':'' ?>><?= $w['week'] ?></option><?php endforeach; ?></select>
            <span>max</span>
            <input type="number" name="b2_vz_dagen[]" min="1" max="6" value="<?= h($vz['dagen_aantal'] ?? '') ?>" class="bs-mini-getal" onchange="beschHerbereken()" aria-label="dagen">
            <span>dgn</span>
          </div>
          <input type="text" name="b2_vz_toelichting[]" value="<?= h($vz['toelichting'] ?? '') ?>" placeholder="reden (bijv. school)" class="bs-invoer" style="margin-top:8px" onchange="beschHerbereken()">
          <input type="hidden" name="b2_vz_pool[]" value="<?= h(json_encode($vz['dagen_pool'] ?? [])) ?>">
        </div>
        <button type="button" class="bs-item-wis" onclick="this.closest('.besch-rij').remove(); beschHerbereken()" aria-label="Verwijderen"><?= $ico('prullenbak', 16) ?></button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="bs-uitz-lijn"></div>

  <!-- Losse dagen niet beschikbaar -->
  <div class="bs-uitz-sec">
    <div class="bs-uitz-kop">
      <span class="bs-uitz-titel">Losse dagen niet beschikbaar</span>
      <button type="button" class="bs-uitz-toevoeg" onclick="beschLosseDagToevoegen()">+ Toevoegen</button>
    </div>
    <div id="beschLosseDagen">
      <?php foreach ($losse_dagen as $ld): ?>
      <div class="besch-rij besch-losdag bs-item-rij">
        <div class="bs-item-tekst">
          <div class="bs-item-datums">
            <input type="date" name="b2_dag_datum[]" value="<?= h($ld['datum'] ?? '') ?>" onchange="beschHerbereken()" aria-label="Datum">
            <select name="b2_dag_soort[]" onchange="beschHerbereken()" class="bs-mini-select">
              <option value="niet_beschikbaar" <?= ($ld['soort'] ?? '')==='niet_beschikbaar'?'selected':'' ?>>kan niet</option>
              <option value="voorkeur_vrij" <?= ($ld['soort'] ?? '')==='voorkeur_vrij'?'selected':'' ?>>liever vrij</option>
            </select>
          </div>
          <input type="text" name="b2_dag_reden[]" value="<?= h($ld['reden'] ?? '') ?>" placeholder="reden (optioneel)" class="bs-invoer" style="margin-top:8px" onchange="beschHerbereken()">
        </div>
        <button type="button" class="bs-item-wis" onclick="this.closest('.besch-rij').remove(); beschHerbereken()" aria-label="Verwijderen"><?= $ico('prullenbak', 16) ?></button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<label class="bs-voorlopig">
  <input type="checkbox" name="b2_voorlopig" value="1" <?= !empty($b2p['voorlopig'])?'checked':'' ?>>
  Mijn beschikbaarheid is nog voorlopig — ik werk het later bij.
</label>

<?php
/**
 * Beschikbaarheidsformulier — velden (schone opzet).
 * Verwacht: $sol (sollicitant) en $alle_weken. Zet zelf de bestaande data klaar.
 * Werkt samen met assets/js/beschikbaarheid.js en window.BESCH_CFG.
 */
if (!isset($sol) || !isset($alle_weken)) { return; }

$b2  = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($sol['id']) : ['profiel'=>null,'blokken'=>[],'afwezig'=>[],'verzoeken'=>[]];
$b2p = $b2['profiel'] ?? null;
$losse_dagen = function_exists('beschikbaarheid_dagen') ? beschikbaarheid_dagen($sol['id']) : [];
$vaste_sel = array_map('intval', $b2p['vaste_dagen_uit'] ?? []);
$dag_namen = [1=>'ma',2=>'di',3=>'wo',4=>'do',5=>'vr',6=>'za',7=>'zo'];

// Icoon-helper: als icoon() bestaat gebruiken we die, anders leeg (uniform gedrag)
$ico = function($naam, $g=18) { return function_exists('icoon') ? icoon($naam, $g) : ''; };
?>

<div class="besch-sectie-titel"><?= $ico('kalender', 18) ?> Wanneer kun je werken?</div>

<?php if (function_exists('ai_besch_actief') && ai_besch_actief()): ?>
<div class="besch-ai">
  <div class="besch-ai-kop"><?= $ico('bliksem', 16) ?> Wanneer kun je werken deze zomer?</div>
  <p class="besch-hint" style="margin:2px 0 10px">Schrijf het in je eigen woorden — wij zetten het om in een voorstel dat je daarna kunt aanpassen.</p>
  <textarea id="beschAiTekst" class="besch-ai-veld" rows="3" placeholder="Bijvoorbeeld: Ik kan van eind juni tot half augustus, behalve de eerste week van juli…"></textarea>
  <div class="besch-ai-voorbeelden">
    <span class="besch-ai-vb-lbl">Voorbeelden om te beginnen:</span>
    <button type="button" class="besch-chip" onclick="beschAiChip(this)">Alleen weekenden</button>
    <button type="button" class="besch-chip" onclick="beschAiChip(this)">Hele juli en augustus</button>
    <button type="button" class="besch-chip" onclick="beschAiChip(this)">Niet in de bouwvak</button>
  </div>
  <div class="besch-ai-actie">
    <button type="button" id="beschAiKnop" class="besch-ai-knop" onclick="beschAiVerwerk()">Maak mijn voorstel</button>
    <span id="beschAiStatus" class="besch-hint"></span>
  </div>
</div>
<div class="besch-of-scheiding"><span>of vul het zelf in</span></div>
<?php endif; ?>

<div id="beschPeriodes">
  <?php foreach ($b2['blokken'] as $blok): ?>
    <?php /* server-side gerenderde bestaande periodes; JS neemt het daarna over */ ?>
  <?php endforeach; ?>
</div>
<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="beschPeriodeToevoegen()"><?= $ico('plus', 16) ?> Nog een periode</button>
<p class="besch-hint">Standaard staat het hele seizoen ingevuld. Werk je alleen in het weekend? Kies dan "alleen weekend".</p>

<div class="besch-sectie-titel"><?= $ico('kalender', 18) ?> Uitzonderingen <span class="besch-optioneel">optioneel</span></div>

<details class="besch-uitz">
  <summary><span class="besch-uitz-ico"><?= $ico('strand', 18) ?></span> Ik ben een hele periode weg (vakantie)<span class="besch-chev"><?= $ico('chevron', 16) ?></span></summary>
  <div class="besch-uitz-body">
    <div id="beschAfwezig">
      <?php foreach ($b2['afwezig'] as $a): ?>
      <div class="besch-rij besch-afwezig">
        <div class="besch-rij-hoofd">
          <input type="date" name="b2_afw_van[]" value="<?= h($a['van_datum'] ?? '') ?>" onchange="beschHerbereken()" aria-label="Van datum">
          <span class="besch-lbl">t/m</span>
          <input type="date" name="b2_afw_tot[]" value="<?= h($a['tot_datum'] ?? '') ?>" onchange="beschHerbereken()" aria-label="Tot datum">
          <button type="button" class="besch-x" onclick="this.closest('.besch-rij').remove(); beschHerbereken()" aria-label="Verwijderen"><?= $ico('kruis', 15) ?></button>
        </div>
        <input type="text" name="b2_afw_label[]" value="<?= h($a['label'] ?? '') ?>" placeholder="reden (bijv. vakantie)" class="besch-breed" onchange="beschHerbereken()">
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="beschAfwezigToevoegen()"><?= $ico('plus', 16) ?> Periode toevoegen</button>
  </div>
</details>

<details class="besch-uitz">
  <summary><span class="besch-uitz-ico"><?= $ico('kalender', 18) ?></span> Losse dagen dat ik niet kan<span class="besch-chev"><?= $ico('chevron', 16) ?></span></summary>
  <div class="besch-uitz-body">
    <div id="beschLosseDagen">
      <?php foreach ($losse_dagen as $ld): ?>
      <div class="besch-rij besch-losdag">
        <div class="besch-rij-hoofd">
          <input type="date" name="b2_dag_datum[]" value="<?= h($ld['datum'] ?? '') ?>" onchange="beschHerbereken()" aria-label="Datum">
          <select name="b2_dag_soort[]" onchange="beschHerbereken()">
            <option value="niet_beschikbaar" <?= ($ld['soort'] ?? '')==='niet_beschikbaar'?'selected':'' ?>>kan ik niet</option>
            <option value="voorkeur_vrij" <?= ($ld['soort'] ?? '')==='voorkeur_vrij'?'selected':'' ?>>liever vrij</option>
          </select>
          <button type="button" class="besch-x" onclick="this.closest('.besch-rij').remove(); beschHerbereken()" aria-label="Verwijderen"><?= $ico('kruis', 15) ?></button>
        </div>
        <input type="text" name="b2_dag_reden[]" value="<?= h($ld['reden'] ?? '') ?>" placeholder="reden (optioneel)" class="besch-breed" onchange="beschHerbereken()">
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="beschLosseDagToevoegen()"><?= $ico('plus', 16) ?> Dag toevoegen</button>
  </div>
</details>

<details class="besch-uitz">
  <summary><span class="besch-uitz-ico"><?= $ico('ververs', 18) ?></span> Een vaste dag die ik elke week vrij wil<span class="besch-chev"><?= $ico('chevron', 16) ?></span></summary>
  <div class="besch-uitz-body">
    <div class="besch-vastedagen">
      <?php foreach ($dag_namen as $n => $kort): ?>
      <label class="besch-dagpil"><input type="checkbox" class="besch-vastedag" value="<?= $n ?>" <?= in_array($n, $vaste_sel, true)?'checked':'' ?>> <?= $kort ?></label>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="b2_vaste_uit_json" id="beschVasteJson">
    <?php foreach ($vaste_sel as $vd): ?><input type="hidden" name="b2_vaste_uit[]" value="<?= (int)$vd ?>" class="besch-vaste-hidden"><?php endforeach; ?>
    <input type="text" name="b2_vaste_toelichting" id="beschVasteToelichting" value="<?= h($b2p['vaste_toelichting'] ?? '') ?>" placeholder="toelichting (bijv. elke woensdag college)" class="besch-breed" style="margin-top:8px">
  </div>
</details>

<details class="besch-uitz">
  <summary><span class="besch-uitz-ico"><?= $ico('min', 18) ?></span> In bepaalde weken minder dagen<span class="besch-chev"><?= $ico('chevron', 16) ?></span></summary>
  <div class="besch-uitz-body">
    <p class="besch-hint">Standaard gaan we uit van 5 dagen per week. Wil je in bepaalde weken minder? Geef het door als verzoek — het bestuur beoordeelt dit.</p>
    <div id="beschVerzoeken">
      <?php foreach ($b2['verzoeken'] as $vz): if (($vz['status'] ?? 'open')==='afgewezen') continue; ?>
      <div class="besch-rij besch-verzoek">
        <div class="besch-rij-hoofd">
          <span class="besch-lbl">van</span>
          <select name="b2_vz_van[]" onchange="beschHerbereken()"><?php foreach ($alle_weken as $w): ?><option value="<?= $w['week'] ?>" <?= (int)$w['week']===(int)($vz['van_week']??0)?'selected':'' ?>>W<?= $w['week'] ?> · <?= h($w['periode'] ?? '') ?></option><?php endforeach; ?></select>
          <span class="besch-lbl">t/m</span>
          <select name="b2_vz_tot[]" onchange="beschHerbereken()"><?php foreach ($alle_weken as $w): ?><option value="<?= $w['week'] ?>" <?= (int)$w['week']===(int)($vz['tot_week']??0)?'selected':'' ?>>W<?= $w['week'] ?> · <?= h($w['periode'] ?? '') ?></option><?php endforeach; ?></select>
          <input type="number" name="b2_vz_dagen[]" min="1" max="6" value="<?= h($vz['dagen_aantal'] ?? '') ?>" placeholder="dagen" class="besch-getal" onchange="beschHerbereken()" aria-label="Aantal dagen">
          <span class="besch-lbl">dagen</span>
          <button type="button" class="besch-x" onclick="this.closest('.besch-rij').remove(); beschHerbereken()" aria-label="Verwijderen"><?= $ico('kruis', 15) ?></button>
        </div>
        <input type="text" name="b2_vz_toelichting[]" value="<?= h($vz['toelichting'] ?? '') ?>" placeholder="reden (bijv. school)" class="besch-breed" onchange="beschHerbereken()">
        <input type="hidden" name="b2_vz_pool[]" value="<?= h(json_encode($vz['dagen_pool'] ?? [])) ?>">
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="beschVerzoekToevoegen()"><?= $ico('plus', 16) ?> Verzoek toevoegen</button>
  </div>
</details>

<div class="besch-sectie-titel"><?= $ico('vink-cirkel', 18) ?> Zo begrijpen wij het</div>
<div id="beschPreview" class="besch-preview"></div>

<label class="besch-voorlopig">
  <input type="checkbox" name="b2_voorlopig" value="1" <?= !empty($b2p['voorlopig'])?'checked':'' ?>>
  Mijn beschikbaarheid is nog voorlopig — ik werk het later bij.
</label>

<?php
/**
 * Partial: de inzet van één persoon afspreken — in het TW-gesprek.
 *
 * Per week: wat iemand opgaf, en wat je nu afspreekt (plannen, dagen, betaald). Plus de functie.
 * Schrijft in het ACTIEVE scenario van de loonplanning (concept tot "Loonplanning definitief
 * maken"); ververst zich elke 15 seconden, zodat wat iemand in de loonplanning verandert hier
 * meteen te zien is. Na aannemen staat de persoon vast in álle scenario's
 * (includes/loonplan_slot.php); "Toch aanpassen" ontgrendelt met een reden.
 *
 * Verwacht:
 *   $af_sol_id  int
 *   $af_seizoen array  het actieve seizoen
 *   $af_alleen_lezen bool optioneel: alleen tonen (bijv. een collega heeft het gesprek open)
 * Toont niets als de module Loonplanning uitstaat of je die niet mag lezen; zonder bewerkrecht
 * alleen lezen. Gedrag: assets/js/afspraak.js. Opslaan: admin/api_afspraak.php.
 */
if ((function_exists('module_aan') && !module_aan('loonplanning')) || !mag('loonplanning', 'lezen')) return;
$_sid = (int)($af_seizoen['id'] ?? 0);
$_sc  = $_sid ? loonplan_actief_scenario($_sid) : null;
if (!$_sc) return;
$_m    = loonplan_analyse($_sid, (int)$_sc['id'], $af_seizoen, (int)$af_sol_id)['mensen'][(int)$af_sol_id] ?? null;
$_slot = loonplan_sloten($_sid)[(int)$af_sol_id] ?? null;
$_mag  = mag('loonplanning', 'bewerken') && empty($af_alleen_lezen);
$af_alleen_lezen = false;
$_kan  = $_mag && !$_slot;
$_wd   = seizoen_weken_datums($af_seizoen);
$_functies = functies_ophalen();
?>
<section class="bh-afspraak<?= $_slot ? ' vast' : '' ?>" data-afspraak data-sol="<?= (int)$af_sol_id ?>" data-mag="<?= $_mag ? '1' : '0' ?>"
         data-endpoint="<?= APP_URL ?>/admin/api_afspraak.php" data-csrf="<?= h(csrf_token()) ?>">
  <div class="bh-afspraak-kop">
    <h3>Inzet afspreken <span class="bh-meta">concept · scenario "<?= h((string)$_sc['naam']) ?>"</span></h3>
    <label class="bh-afspraak-functie">
      <span class="bh-veldlabel">Functie</span>
      <select class="ds-select" data-af-functie <?= $_kan ? '' : 'disabled' ?>>
        <?php foreach ($_functies as $_f): ?>
          <option value="<?= h($_f['sleutel']) ?>" <?= ($_m['functie'] ?? '') === $_f['sleutel'] ? 'selected' : '' ?>><?= h($_f['naam']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (($_m['functie_bron'] ?? '') === 'voorstel'): ?><span class="bh-meta">voorstel (nieuw = Aspirant, anders de rang uit Authentik)</span><?php endif; ?>
    </label>
  </div>

  <div class="bh-afspraak-slot" data-af-slotbalk role="status">
    <span data-af-slottekst><?php if ($_slot): ?><?= icoon('slot', 15) ?> Vastgezet na het gesprek (<?= h(date('d-m H:i', strtotime((string)$_slot['vergrendeld_op']))) ?>), in alle scenario's.<?php else: ?>Wijzigingen gaan meteen in het actieve scenario. Na aannemen staat dit vast in alle scenario's.<?php endif; ?></span>
    <?php if ($_mag): ?>
      <button type="button" class="bh-doc-actie" data-af-ontgrendel<?= $_slot ? '' : ' hidden' ?>>Toch aanpassen</button>
      <button type="button" class="bh-doc-actie" data-af-opgave<?= $_slot ? ' hidden' : '' ?>>Opgave overnemen</button>
      <button type="button" class="bh-doc-actie" data-af-vastzetten<?= $_slot ? ' hidden' : '' ?>>Nu vastzetten</button>
    <?php endif; ?>
  </div>

  <table class="bh-tabel bh-afspraak-tabel">
    <thead><tr><th scope="col">Week</th><th scope="col">Opgave</th><th scope="col">Plan</th><th scope="col">Dagen</th><th scope="col">Betaald</th></tr></thead>
    <tbody>
      <?php foreach ($_wd as $_wk => $_afd): $_w = $_m['weken'][$_wk] ?? ['opgenomen' => 0, 'dagen' => 0, 'betaald' => 1, 'max' => 0]; ?>
      <tr data-week="<?= (int)$_wk ?>" data-max="<?= (int)$_w['max'] ?>" class="<?= $_w['opgenomen'] ? 'ingepland' : '' ?>">
        <th scope="row" title="Week <?= (int)$_wk ?>, vanaf <?= h(datum_kort_nl($_afd['start'])) ?>">W<?= (int)$_wk ?> <span class="bh-meta"><?= h(datum_kort_nl($_afd['start'])) ?></span></th>
        <td><?= $_w['max'] > 0 ? (int)$_w['max'] . ' d' : '<span class="bh-meta">niet</span>' ?></td>
        <td><input type="checkbox" data-af="opgenomen" <?= $_w['opgenomen'] ? 'checked' : '' ?> <?= $_kan ? '' : 'disabled' ?> aria-label="Week <?= (int)$_wk ?> plannen"></td>
        <td><input type="number" class="ds-input" min="0" max="7" data-af="dagen" value="<?= (int)$_w['dagen'] ?>" <?= ($_kan && $_w['opgenomen']) ? '' : 'disabled' ?> aria-label="Dagen week <?= (int)$_wk ?>"></td>
        <td><input type="checkbox" data-af="betaald" <?= $_w['betaald'] ? 'checked' : '' ?> <?= ($_kan && $_w['opgenomen']) ? '' : 'disabled' ?> aria-label="Week <?= (int)$_wk ?> betaald"></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="bh-meta" data-af-totaal>
    <?= (int)($_m['totaal_dagen'] ?? 0) ?> dagen<?= !empty($_m['dagen_onbetaald']) ? ', waarvan ' . (int)$_m['dagen_onbetaald'] . ' onbetaald' : '' ?>
    · <?= h(euro((float)($_m['totaal_kosten'] ?? 0))) ?> bruto
  </p>
</section>
<script src="<?= APP_URL ?>/assets/js/afspraak.js?v=<?= @filemtime(__DIR__ . '/../assets/js/afspraak.js') ?>" defer></script>

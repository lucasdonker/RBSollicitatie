<?php
/**
 * Partial: de inzet van één persoon afspreken — in het TW-gesprek (en elders waar nodig).
 *
 * Per week: wat iemand opgaf, en wat je nu afspreekt (inplannen, dagen, betaald). Plus de
 * functie. Schrijft in het ACTIEVE scenario van de loonplanning; dat blijft concept tot het
 * bestuur na het weekend "Loonplanning definitief maken" doet. Tot dan ziet de sollicitant
 * hier niets van.
 *
 * Verwacht:
 *   $af_sol_id  int
 *   $af_seizoen array  het actieve seizoen
 * Toont niets als de module Loonplanning uitstaat of je geen bewerkrecht hebt (dan alleen lezen).
 * Gedrag: assets/js/afspraak.js. Opslaan: admin/api_afspraak.php.
 */
if ((function_exists('module_aan') && !module_aan('loonplanning')) || !mag('loonplanning', 'lezen')) return;
$_sid = (int)($af_seizoen['id'] ?? 0);
$_sc  = $_sid ? loonplan_actief_scenario($_sid) : null;
if (!$_sc) return;
$_m = loonplan_analyse($_sid, (int)$_sc['id'], $af_seizoen)['mensen'][(int)$af_sol_id] ?? null;
$_mag = mag('loonplanning', 'bewerken');
$_wd  = seizoen_weken_datums($af_seizoen);
$_functies = functies_ophalen();
?>
<section class="bh-afspraak" data-afspraak data-sol="<?= (int)$af_sol_id ?>" data-endpoint="<?= APP_URL ?>/admin/api_afspraak.php" data-csrf="<?= h(csrf_token()) ?>">
  <div class="bh-afspraak-kop">
    <h3>Inzet afspreken <span class="bh-meta">concept · scenario "<?= h((string)$_sc['naam']) ?>"</span></h3>
    <label class="bh-afspraak-functie">
      <span class="bh-veldlabel">Functie</span>
      <select class="ds-select" data-af-functie <?= $_mag ? '' : 'disabled' ?>>
        <?php foreach ($_functies as $_f): ?>
          <option value="<?= h($_f['sleutel']) ?>" <?= ($_m['functie'] ?? '') === $_f['sleutel'] ? 'selected' : '' ?>><?= h($_f['naam']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (($_m['functie_bron'] ?? '') === 'voorstel'): ?><span class="bh-meta">voorstel (nieuw = Aspirant, anders de rang uit Authentik)</span><?php endif; ?>
    </label>
  </div>

  <table class="bh-tabel bh-afspraak-tabel">
    <thead><tr><th scope="col">Week</th><th scope="col">Opgave</th><th scope="col">Plan</th><th scope="col">Dagen</th><th scope="col">Betaald</th></tr></thead>
    <tbody>
      <?php foreach ($_wd as $_wk => $_d): $_w = $_m['weken'][$_wk] ?? ['opgenomen' => 0, 'dagen' => 0, 'betaald' => 1, 'max' => 0]; ?>
      <tr data-week="<?= (int)$_wk ?>" class="<?= $_w['opgenomen'] ? 'ingepland' : '' ?>">
        <th scope="row" title="Week <?= (int)$_wk ?>, vanaf <?= h(datum_kort_nl($_d['start'])) ?>">W<?= (int)$_wk ?> <span class="bh-meta"><?= h(datum_kort_nl($_d['start'])) ?></span></th>
        <td><?= $_w['max'] > 0 ? (int)$_w['max'] . ' d' : '<span class="bh-meta">niet</span>' ?></td>
        <td><input type="checkbox" data-af="opgenomen" <?= $_w['opgenomen'] ? 'checked' : '' ?> <?= $_mag ? '' : 'disabled' ?> aria-label="Week <?= (int)$_wk ?> inplannen"></td>
        <td><input type="number" class="ds-input" min="0" max="7" data-af="dagen" value="<?= (int)$_w['dagen'] ?>" <?= ($_mag && $_w['opgenomen']) ? '' : 'disabled' ?> aria-label="Dagen week <?= (int)$_wk ?>"></td>
        <td><input type="checkbox" data-af="betaald" <?= $_w['betaald'] ? 'checked' : '' ?> <?= ($_mag && $_w['opgenomen']) ? '' : 'disabled' ?> aria-label="Week <?= (int)$_wk ?> betaald"></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="bh-meta" data-af-totaal>
    <?= (int)($_m['totaal_dagen'] ?? 0) ?> dagen<?= !empty($_m['dagen_onbetaald']) ? ', waarvan ' . (int)$_m['dagen_onbetaald'] . ' onbetaald' : '' ?>
    · € <?= number_format((float)($_m['totaal_kosten'] ?? 0), 0, ',', '.') ?> bruto
  </p>
</section>
<script src="<?= APP_URL ?>/assets/js/afspraak.js?v=<?= @filemtime(__DIR__ . '/../assets/js/afspraak.js') ?>" defer></script>

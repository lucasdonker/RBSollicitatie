<?php
/**
 * Loonplanning — filters en het raster. Per cel: groot wat je plant, klein wat iemand opgaf
 * ("kan 4"). Klik op een cel = zijpaneel voor die week; klik op een naam = de persoon.
 * Onderdeel van admin/loonplanning.php. Cellen: bh_lp_cel() in includes/beheer/loonplanning.php.
 */
$_functie_naam = array_column($functies, 'naam', 'sleutel');
$filter_verborgen = ['scenario' => $scenario_id];
$filter_velden = [
  ['soort' => 'zoek', 'naam' => 'q', 'label' => 'Zoek op naam', 'waarde' => $filter['q'], 'breed' => true],
  ['soort' => 'keuze', 'naam' => 'functie', 'label' => 'Functie', 'waarde' => $filter['functie'], 'opties' => ['' => 'Alle functies'] + $_functie_naam],
  ['soort' => 'keuze', 'naam' => 'groep', 'label' => 'Wie', 'waarde' => $filter['groep'],
   'opties' => ['' => 'Iedereen', 'proces' => 'Nog in de procedure', 'aangenomen' => 'Aangenomen']],
  ['soort' => 'keuze', 'naam' => 'toon', 'label' => 'Toon', 'waarde' => $filter['toon'],
   'opties' => ['' => 'Alles', 'let' => 'Met een afwijking', 'niet_ingepland' => 'Nog niet ingepland', 'vast' => 'Vast na het gesprek', 'open' => 'Nog niet vast']],
];
$filter_wissen = bh_lp_gefilterd($filter) ? APP_URL . '/admin/loonplanning.php?scenario=' . $scenario_id : null;
require __DIR__ . '/../_filterbalk.php';
?>
<section class="ds-kaart bh-raster bh-lp-raster" data-lp-raster data-mag="<?= $mag_bewerken ? '1' : '0' ?>" data-csrf="<?= h(csrf_token()) ?>"
         data-scenario="<?= (int)$scenario_id ?>" data-endpoint="<?= APP_URL ?>/admin/loonplanning.php"
         data-definitief="<?= $definitief_op ? '1' : '0' ?>" data-dossier="<?= APP_URL ?>/admin/sollicitant.php?tab=beschikbaarheid&amp;id=">
  <header class="bh-raster-kop">
    <h2>Planning</h2>
    <span class="bh-raster-uitleg"><?= $mag_bewerken ? 'klik een week om te plannen, een naam voor de persoon' : 'alleen lezen' ?></span>
    <span class="bh-raster-legenda">
      <span><i class="bh-legenda-blok bh-weekcel-gepland"></i>gepland</span>
      <span><i class="bh-legenda-blok bh-weekcel-onbetaald"></i>onbetaald</span>
      <span><i class="bh-legenda-blok bh-weekcel-let"></i>meer dan opgegeven</span>
      <span><i class="bh-legenda-blok bh-weekcel-open"></i>opgegeven, niet gepland ("kan 4")</span>
    </span>
  </header>
  <p class="bh-lp-smal">De planning heeft ruimte nodig: open hem op een groter scherm om te plannen.</p>

  <?php if (!$rijen): ?>
    <div class="ds-kaart-p">
      <?php $leeg_titel = 'Niemand gevonden'; $leeg_tekst = 'Met deze filters blijft er niemand over.'; $leeg_icoon = 'zoek'; require __DIR__ . '/../_leeg.php'; ?>
    </div>
  <?php else: ?>
  <div class="bh-rasterrol">
    <table class="bh-rastertabel bh-lp-tabel" style="--bh-tracks:<?= 'minmax(230px,1.6fr) 92px repeat(' . count($weken) . ',minmax(52px,1fr))' ?>">
      <caption class="sr-only">Loonplanning per persoon per week</caption>
      <thead>
        <tr>
          <th scope="col" class="bh-raster-naam">Naam (<?= count($rijen) ?><?= bh_lp_gefilterd($filter) ? ' van ' . count($analyse['mensen']) : '' ?>)</th>
          <th scope="col" class="bh-raster-wkn">Totaal</th>
          <?php foreach ($weken as $_wn => $_w): ?>
            <th scope="col" class="bh-raster-wk bh-wk-<?= h((string)$_w['weektype']) ?><?= ($dekking[$_wn]['stand'] ?? '') === 'slecht' ? ' bh-wk-laag' : '' ?>">
              <span class="bh-raster-wknr">W<?= (int)$_wn ?></span><span class="bh-raster-wkdat"><?= h($_w['kort']) ?></span>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rijen as $_sol => $_m): ?>
        <tr data-lp-rij="<?= (int)$_sol ?>">
          <th scope="row" class="bh-raster-naam bh-lp-naam">
            <button type="button" class="bh-lp-persoonknop" data-lp-persoon="<?= (int)$_sol ?>" data-naam="<?= h($_m['naam']) ?>"
                    data-functie="<?= h($_m['functie']) ?>" data-fase="<?= h(fase_label($_m['fase'])) ?>"
                    data-vast="<?= $_m['vast'] ? h(date('d-m H:i', strtotime((string)$_m['vast']['vergrendeld_op']))) : '' ?>"
                    data-afspraak="<?= $_m['afspraak'] ? ($_m['afspraak']['wijkt_af'] ? 'wijkt_af' : 'ja') : '' ?>">
              <span class="bh-lp-naamtekst"><?= h($_m['naam']) ?></span>
              <span class="bh-lp-naamsub"><?= h(implode(' · ', array_filter([$_functie_naam[$_m['functie']] ?? $_m['functie'],
                  $_m['leeftijd'] ? $_m['leeftijd'] . ' jr' : '', $_m['fase'] === 'aangenomen' ? 'aangenomen' : '']))) ?></span>
            </button>
            <?php if ($_m['vast']): ?><span class="bh-lp-slot" title="Vast na het TW-gesprek"><?= icoon('slot', 14) ?><span class="bh-vw">vast na het gesprek</span></span><?php endif; ?>
            <?php if (!empty($_m['signalen'])): ?>
              <span title="<?= h(implode("\n", $_m['signalen'])) ?>"><?= bh_chip('let op', 'oranje') ?><span class="bh-vw">: <?= h(implode(' ', $_m['signalen'])) ?></span></span>
            <?php endif; ?>
            <?php if ($_m['afspraak']): ?>
              <?= $_m['afspraak']['wijkt_af'] ? bh_chip('wijkt af', 'oranje') : bh_chip('afspraak', 'groen') ?>
            <?php endif; ?>
          </th>
          <td class="bh-raster-wkn bh-lp-tot" data-lp-tot="<?= (int)$_sol ?>">
            <strong><?= (int)$_m['totaal_dagen'] ?> d</strong>
            <span>€ <?= number_format((float)$_m['totaal_kosten'], 0, ',', '.') ?></span>
          </td>
          <?php foreach ($weken as $_wn => $_w):
            $_cw = $_m['weken'][$_wn] ?? ['opgenomen' => 0, 'dagen' => 0, 'betaald' => 1, 'max' => 0];
            $_c  = bh_lp_cel($_cw, $_m['naam'], (int)$_wn);
          ?>
          <td class="bh-raster-cel"><?= bh_weekcel($_c['tekst'], $_c['soort'], ['titel' => $_c['tip'], 'sub' => $_c['sub'], 'data' => [
                'lp-cel' => $_sol . '-' . $_wn, 'sol' => $_sol, 'week' => $_wn, 'opgen' => (int)$_cw['opgenomen'],
                'dagen' => (int)$_cw['dagen'], 'betaald' => (int)$_cw['betaald'], 'max' => (int)$_cw['max']]]) ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th scope="row" class="bh-raster-naam">Ingepland <span class="bh-raster-totsub">· weekend</span></th>
          <td class="bh-raster-wkn"></td>
          <?php foreach ($weken as $_wn => $_w): $_d = $dekking[$_wn]; ?>
            <td class="bh-raster-tot bh-num<?= $_d['stand'] === 'slecht' ? ' bh-raster-tot-laag' : '' ?>" data-lp-weektot="<?= (int)$_wn ?>"
                title="Week <?= (int)$_wn ?>: <?= $_d['aantal'] ?> ingepland, <?= $_d['weekend'] ?> in het weekend">
              <?= $_d['aantal'] ?><span class="bh-raster-totwk"> / <?= $_d['weekend'] ?></span>
            </td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <th scope="row" class="bh-raster-naam">Kosten</th>
          <td class="bh-raster-wkn"></td>
          <?php foreach ($weken as $_wn => $_w): $_k = (float)($analyse['per_week'][$_wn]['kosten'] ?? 0); ?>
            <td class="bh-raster-tot bh-lp-weekkosten" data-lp-weekkosten="<?= (int)$_wn ?>"><?= $_k > 0 ? '€ ' . number_format($_k / 1000, 1, ',', '') . 'k' : '–' ?></td>
          <?php endforeach; ?>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>
</section>

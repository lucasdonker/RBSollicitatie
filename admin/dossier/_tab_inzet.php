<?php
/**
 * Tabblad: inzet — wat iemand opgaf, wat er is afgesproken (definitief), en hoe de persoon in
 * elk loonplanning-scenario staat. De afspraak staat bovenaan: dat is wat telt voor het
 * contract, de loonadministratie en het rooster. De scenario's zijn concept.
 *
 * Verwacht: $tab_dossier  array  uitkomst van bh_dossier()
 * Alleen met leesrecht op de loonplanning (bh_dossier_tabs() toont de tab anders niet).
 */
$tab_dossier = $tab_dossier ?? null;
if (!$tab_dossier) return;

$_sol  = (int)$tab_dossier['sol']['id'];
$_szid = (int)$tab_dossier['sol']['seizoen_id'];
$_sz   = seizoen_op_id($_szid);
$_wd   = seizoen_weken_datums($_sz);
$_af   = inzet_afspraak_van($_sol);
$_scs  = loonplan_persoon_scenarios($_szid, $_sol);
$_slot = loonplan_sloten($_szid)[$_sol] ?? null;
$_opg  = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($_sol, $_sz) : [];
$_fn   = array_column(functies_ophalen(), 'naam', 'sleutel');
$_tracks = 'minmax(170px,1.2fr) repeat(' . count($_wd) . ',minmax(44px,1fr)) 70px';

/** Eén rij van het overzicht: [wk => ['dagen','betaald']] naar cellen. */
$_cellen = function (array $weken) use ($_wd): string {
    $uit = ''; $tot = 0;
    foreach ($_wd as $wk => $_) {
        $w = $weken[$wk] ?? null;
        if (!$w) { $uit .= '<td class="bh-raster-cel">' . bh_weekcel('–', 'leeg') . '</td>'; continue; }
        $tot += $w['dagen'];
        $uit .= '<td class="bh-raster-cel">' . bh_weekcel($w['dagen'] . ($w['betaald'] ? '' : ' onb.'), $w['betaald'] ? 'gepland' : 'onbetaald',
                    ['titel' => 'Week ' . $wk . ': ' . $w['dagen'] . ' dagen' . ($w['betaald'] ? '' : ', onbetaald')]) . '</td>';
    }
    return $uit . '<td class="bh-raster-tot">' . $tot . ' d</td>';
};
?>
<section class="ds-kaart bh-raster">
  <header class="bh-raster-kop">
    <h2>Inzet</h2>
    <span class="bh-raster-uitleg">
      <?php if ($_af): ?>afspraak vastgesteld op <?= h(date('d-m-Y', strtotime((string)$_af['vastgesteld_op']))) ?> · <?= h($_fn[$_af['functie_sleutel']] ?? $_af['functie_sleutel']) ?>
      <?php else: ?>nog geen vastgestelde afspraak — alles hieronder is concept<?php endif; ?>
      <?php if ($_slot): ?> · vast na het TW-gesprek (<?= h(date('d-m', strtotime((string)$_slot['vergrendeld_op']))) ?>)<?php endif; ?>
    </span>
    <?php if (mag('loonplanning', 'lezen')): ?>
      <a class="bh-kaartkop-link" href="<?= APP_URL ?>/admin/loonplanning.php?q=<?= urlencode((string)$tab_dossier['sol']['voornaam']) ?>">Naar de loonplanning</a>
    <?php endif; ?>
  </header>
  <div class="bh-rasterrol">
    <table class="bh-rastertabel" style="--bh-tracks:<?= $_tracks ?>">
      <caption class="sr-only">Inzet per week: opgave, afspraak en scenario's</caption>
      <thead><tr>
        <th scope="col" class="bh-raster-naam"></th>
        <?php foreach ($_wd as $_wk => $_w): ?><th scope="col" class="bh-raster-wk"><span class="bh-raster-wknr">W<?= (int)$_wk ?></span><span class="bh-raster-wkdat"><?= h(datum_kort_nl($_w['start'])) ?></span></th><?php endforeach; ?>
        <th scope="col" class="bh-raster-wkn">Totaal</th>
      </tr></thead>
      <tbody>
        <tr>
          <th scope="row" class="bh-raster-naam">Opgegeven</th>
          <?php $_t = 0; foreach ($_wd as $_wk => $_): $_m = (int)($_opg[$_wk]['dagen_max'] ?? 0); $_t += $_m; ?>
            <td class="bh-raster-cel"><?= bh_weekcel($_m ? (string)$_m : '–', $_m ? 'open' : 'leeg', ['titel' => 'Week ' . $_wk . ': ' . ($_m ? 'hooguit ' . $_m . ' dagen opgegeven' : 'niet opgegeven')]) ?></td>
          <?php endforeach; ?>
          <td class="bh-raster-tot"><?= $_t ?> d</td>
        </tr>
        <?php if ($_af): ?>
        <tr class="bh-inzet-afspraak">
          <th scope="row" class="bh-raster-naam"><strong>Afspraak</strong> <?= bh_chip('definitief', 'groen') ?></th>
          <?= $_cellen($_af['weken']) ?>
        </tr>
        <?php endif; ?>
        <?php foreach ($_scs as $_s): ?>
        <tr>
          <th scope="row" class="bh-raster-naam">
            <?php if ($_s['actief']): ?><?= icoon('ster', 13) ?><?php endif; ?>
            <?= h($_s['naam']) ?> <span class="bh-meta"><?= h(implode(' · ', array_filter([$_s['actief'] ? 'actief' : 'concept', $_fn[$_s['functie'] ?? ''] ?? '']))) ?></span>
          </th>
          <?= $_cellen($_s['weken']) ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="bh-raster-voet">Onbetaalde weken ("onb.") zijn afgesproken werkdagen zonder loon. De afspraak is wat de sollicitant in het
    portaal ziet en wat in de loonadministratie en het rooster komt; de scenario's zijn het puzzelwerk ervoor.</p>
</section>
<?php
$tab_dossier = null;

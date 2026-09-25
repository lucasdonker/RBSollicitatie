<?php
/**
 * Tabblad: Beschikbaarheid & inzet — per week wat iemand opgaf en wat er is afgesproken of
 * gepland, recht onder elkaar, zodat je ze in één oogopslag vergelijkt. Een week waarin meer
 * is gepland dan opgegeven valt op (rood, met uitleg).
 *
 * Opgave: bh_besch_persoon_weken() — dezelfde cellen als het raster op Beschikbaarheid; klik =
 * corrigeren (met bewerkrecht op de beschikbaarheid). Afspraak, planning en de andere scenario's
 * alleen met leesrecht op de loonplanning (daar staan loonkosten achter). Onder het raster: de
 * afspraak-editor (admin/_afspraak_editor.php) voor het actieve seizoen, ingeklapt.
 * Vervangt de losse tabs Beschikbaarheid en Inzet (2.122.0); ?tab=inzet komt hier uit.
 *
 * Verwacht: $tab_dossier  array  uitkomst van bh_dossier()
 */
$tab_dossier = $tab_dossier ?? null;
if (!$tab_dossier) return;

$_s    = $tab_dossier['sol'];
$_sol  = (int)$_s['id'];
$_szid = (int)$_s['seizoen_id'];
$_sz   = seizoen_op_id($_szid);
$_wkn  = bh_besch_weken($_sz);
$_opg  = bh_besch_persoon_weken($_sol, $_wkn, $_sz)['weken'];
$_b    = $tab_dossier['beschikbaarheid'];
$_prof = function_exists('beschikbaarheid2_van') ? (beschikbaarheid2_van($_sol)['profiel'] ?? null) : null;
$_mag_corr = mag('beschikbaarheid', 'bewerken');
$_lp   = (!function_exists('module_aan') || module_aan('loonplanning')) && mag('loonplanning', 'lezen') && function_exists('loonplan_persoon_scenarios');
$_af   = $_lp ? inzet_afspraak_van($_sol) : null;
$_scs  = $_lp ? loonplan_persoon_scenarios($_szid, $_sol) : [];
$_actief = null; $_rest = [];
foreach ($_scs as $_sc) { if ($_sc['actief']) $_actief = $_sc; else $_rest[] = $_sc; }
$_slot = $_lp ? (loonplan_sloten($_szid)[$_sol] ?? null) : null;
$_fn   = $_lp ? array_column(functies_ophalen(), 'naam', 'sleutel') : [];
$_naam = (string)$_s['voornaam'];

if (!$_opg && !$_af && !$_actief && !$_b) {
    echo '<div class="ds-kaart ds-kaart-p">';
    $leeg_titel = 'Nog geen beschikbaarheid';
    $leeg_tekst = h($_naam) . ' heeft nog niet doorgegeven welke weken beschikbaar zijn.';
    $leeg_icoon = 'kalender';
    require __DIR__ . '/../_leeg.php';
    echo '</div>';
    return;
}

/** Een rij afspraak/planning: cellen, met "meer dan opgegeven" als afwijking (soort let). */
$_rij = function (array $weken) use ($_wkn, $_opg): string {
    $uit = ''; $tot = 0;
    foreach ($_wkn as $wk => $_) {
        $w = $weken[$wk] ?? null;
        if (!$w || (int)$w['dagen'] <= 0) { $uit .= '<td class="bh-raster-cel">' . bh_weekcel('–', 'leeg') . '</td>'; continue; }
        $d = (int)$w['dagen']; $tot += $d;
        $max = (int)($_opg[$wk]['dagen'] ?? 0);
        $te_veel = $d > $max;
        $soort = $te_veel ? 'let' : (!empty($w['betaald']) ? 'gepland' : 'onbetaald');
        $titel = 'Week ' . $wk . ': ' . $d . ' dagen' . (empty($w['betaald']) ? ', onbetaald' : '')
               . ($te_veel ? ' — meer dan opgegeven (' . ($max ?: 'niets') . ')' : '');
        $uit .= '<td class="bh-raster-cel">' . bh_weekcel($d . (empty($w['betaald']) ? ' onb.' : ''), $soort, ['titel' => $titel]) . '</td>';
    }
    return $uit . '<td class="bh-raster-tot">' . $tot . ' d</td>';
};
$_tracks = 'minmax(170px,1.2fr) repeat(' . count($_wkn) . ',minmax(44px,1fr)) 70px';
$_kop = function () use ($_wkn): string {
    $h = '<thead><tr><th scope="col" class="bh-raster-naam"></th>';
    foreach ($_wkn as $wk => $w) $h .= '<th scope="col" class="bh-raster-wk"><span class="bh-raster-wknr">W' . (int)$wk . '</span><span class="bh-raster-wkdat">' . h($w['kort']) . '</span></th>';
    return $h . '<th scope="col" class="bh-raster-wkn">Totaal</th></tr></thead>';
};
?>
<section class="ds-kaart bh-raster">
  <header class="bh-raster-kop">
    <h2>Per week</h2>
    <span class="bh-raster-uitleg">
      <?php if ($_prof): ?><?= !empty($_prof['voorlopig']) ? bh_badge('Opgave voorlopig', 'oranje') : bh_badge('Opgave definitief', 'groen') ?><?php endif; ?>
      <?= h(implode(' · ', array_filter([
            $_lp ? ($_af ? 'afspraak vastgesteld ' . date('d-m-Y', strtotime((string)$_af['vastgesteld_op'])) . ', ' . ($_fn[$_af['functie_sleutel']] ?? $_af['functie_sleutel'])
                         : 'nog geen vastgestelde afspraak') : '',
            $_lp && $_actief ? 'planning = scenario "' . $_actief['naam'] . '"' : '',
            $_slot ? 'vast na het TW-gesprek' : '',
            $_mag_corr ? 'klik een week van de opgave om te corrigeren' : '',
      ]))) ?>
    </span>
    <?php if ($_lp): ?>
      <a class="bh-kaartkop-link" href="<?= APP_URL ?>/admin/loonplanning.php?<?= h(http_build_query(['q' => volledige_naam($_s), 'persoon' => $_sol])) ?>">Naar de loonplanning</a>
    <?php endif; ?>
  </header>
  <div class="bh-rasterrol">
    <table class="bh-rastertabel" style="--bh-tracks:<?= $_tracks ?>">
      <caption class="sr-only">Per week: opgave, afspraak en planning</caption>
      <?= $_kop() ?>
      <tbody>
        <tr>
          <th scope="row" class="bh-raster-naam"<?= $_mag_corr ? ' title="Klik een week om te corrigeren"' : '' ?>>Opgave</th>
          <?php $_t = 0; foreach ($_wkn as $_wk => $_): $_c = $_opg[$_wk] ?? null; $_t += (int)($_c['dagen'] ?? 0);
            $_ct = bh_besch_cel_tekst($_c); ?>
            <td class="bh-raster-cel"><?= bh_weekcel($_ct[0], $_ct[2], ['titel' => 'Week ' . $_wk . ': ' . $_ct[1],
                'klik' => $_mag_corr ? 'correctieOpen(' . $_sol . ',' . (int)$_wk . ',' . json_encode(volledige_naam($_s), JSON_HEX_QUOT | JSON_HEX_APOS) . ')' : null]) ?></td>
          <?php endforeach; ?>
          <td class="bh-raster-tot"><?= $_t ?> d</td>
        </tr>
        <?php if ($_af): ?>
        <tr class="bh-inzet-afspraak">
          <th scope="row" class="bh-raster-naam" title="Vastgestelde afspraak"><strong>Afspraak</strong></th>
          <?= $_rij($_af['weken']) ?>
        </tr>
        <?php endif; ?>
        <?php if ($_actief): ?>
        <tr>
          <th scope="row" class="bh-raster-naam" title="Actief scenario: <?= h((string)$_actief['naam']) ?> (concept)">Planning</th>
          <?= $_rij($_actief['weken']) ?>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($_rest): ?>
  <details class="bh-inzet-rest">
    <summary>Andere scenario's (<?= count($_rest) ?>)</summary>
    <div class="bh-rasterrol">
      <table class="bh-rastertabel" style="--bh-tracks:<?= $_tracks ?>">
        <caption class="sr-only">Andere scenario's</caption>
        <?= $_kop() ?>
        <tbody>
          <?php foreach ($_rest as $_sc): ?>
          <tr><th scope="row" class="bh-raster-naam"><?= h($_sc['naam']) ?> <span class="bh-meta"><?= h($_fn[$_sc['functie'] ?? ''] ?? '') ?></span></th><?= $_rij($_sc['weken']) ?></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
  <?php endif; ?>
  <div class="bh-weeklegenda bh-raster-voet">
    <span><?= bh_weekcel('5', 'vol') ?> hele week</span>
    <span><?= bh_weekcel('3', 'deels') ?> deels</span>
    <span><?= bh_weekcel('3', 'correctie') ?> gecorrigeerd</span>
    <?php if ($_lp): ?>
      <span><?= bh_weekcel('4', 'gepland') ?> gepland</span>
      <span><?= bh_weekcel('5', 'let') ?> meer dan opgegeven</span>
    <?php endif; ?>
  </div>
</section>

<?php
  // Aanpassen in het actieve scenario (alleen voor het lopende seizoen; de editor schrijft daar).
  $_actief_sz = actief_seizoen();
  if ($_lp && $_actief && $_actief_sz && (int)$_actief_sz['id'] === $_szid): ?>
  <details class="ds-kaart ds-kaart-p bh-inzet-editor">
    <summary>Inzet afspreken of aanpassen</summary>
    <?php $af_sol_id = $_sol; $af_seizoen = $_actief_sz; require __DIR__ . '/../_afspraak_editor.php'; ?>
  </details>
<?php endif; ?>

<div class="bh-dossier-kolommen">
  <div class="bh-dossier-hoofd">
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Uit het formulier</h2></div>
      <?php $_int = trim((string)($tab_dossier['blk']['beschikbaarheid_intentie'] ?? '')); ?>
      <p class="bh-dossier-tekst<?= $_int === '' ? ' zacht' : '' ?>"><?= $_int === '' ? 'Geen voorkeur opgegeven bij de aanmelding.' : nl2br(h($_int)) ?></p>
      <?php $_uitz = trim((string)($tab_dossier['blk']['beschikbaarheid_uitzonderingen'] ?? '')) ?: trim((string)($_b['uitzonderingen'] ?? '')); ?>
      <?php if ($_uitz !== ''): ?>
        <div class="bh-dossier-motivatie">
          <span class="bh-veldlabel">Uitzonderingen</span>
          <p class="bh-dossier-tekst"><?= nl2br(h($_uitz)) ?></p>
        </div>
      <?php endif; ?>
    </section>
  </div>
  <aside class="bh-dossier-zij">
    <?php $norm_sol = $_sol; $norm_profiel = $_prof; require __DIR__ . '/_norm.php'; ?>
    <?php if ($_mag_corr): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Opgave aanpassen</h2></div>
      <p class="bh-dossier-tekst">Klik een week in de rij Opgave voor een correctie. Een correctie van het bestuur overschrijft wat de
        sollicitant opgaf; de reden komt in de tijdlijn.</p>
      <a class="ds-btn ds-btn-secundair ds-btn-sm bh-knop-onder" href="<?= APP_URL ?>/admin/beschikbaarheid_bewerken.php?id=<?= $_sol ?>">Per dag of per periode bewerken</a>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php
$tab_dossier = null;

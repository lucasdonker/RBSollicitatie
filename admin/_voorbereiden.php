<?php
/**
 * Partial: stap Voorbereiden van een evenement (selectiedag én trainingsweekend).
 * Wie komt er, wat mist nog, zoeken op naam en filteren op "Komt?".
 *
 * De bevestiging komt uit drie bronnen: het portaal (de sollicitant zelf), de bellijst en
 * hier. "Kan niet" verandert de fase niet — dat beslist het bestuur op Vandaag (afmelden of
 * uitzondering).
 *
 * Verwacht:
 *   $vb_lijst       array     deelnemers (evenement_lijst() of tw_kandidaten()): sol_id, naam,
 *                             leeftijd, fase, bevestiging, bevestiging_reden, foto_url
 *   $vb_post_url    string    waar het formulier heen post (actie=bevestiging, sol_id, stand, reden)
 *   $vb_filter_url  string    waar de filterbalk heen gaat (GET)
 *   $vb_verborgen   array     optioneel: [naam => waarde] die in de filter-URL moet blijven (bijv. stap)
 *   $vb_mag_wijzigen bool     bevestiging aanpassen (bestuur)
 *   $vb_mag_dossier bool      naam linkt naar het dossier
 *   $vb_klaar_kop   string    kop van de controlekolom ("Beschikbaarheid", "Formulieren")
 *   $vb_klaar       callable  fn(array $k): array [tekst, kleur] voor die kolom
 *   $vb_mist        string    optioneel: zin over wat nog ontbreekt (leeg = niets)
 *   $vb_leeg        array     ['titel','tekst','icoon'] als er nog niemand is uitgenodigd
 */
$_standen = evenement_bevestig_standen();
$_q       = (string)($_GET['q'] ?? '');
$_fstand  = (string)($_GET['komt'] ?? '');
if ($_fstand !== '' && !isset($_standen[$_fstand])) $_fstand = '';
$_tel = ['komt' => 0, 'komt_niet' => 0, 'onbekend' => 0];
foreach ($vb_lijst as $_k) $_tel[isset($_tel[$_k['bevestiging'] ?? '']) ? $_k['bevestiging'] : 'onbekend']++;
$_rijen = evenement_lijst_filter($vb_lijst, $_q, $_fstand);
$_gefilterd = $_q !== '' || $_fstand !== '';
?>
<div class="bh-incheck">
  <section class="ds-kaart bh-dekkop">
    <div class="bh-dekkop-zin">
      <?= bh_stoplicht($_tel['onbekend'] + $_tel['komt_niet'] > 0 ? 'krap' : 'goed',
                       $_tel['komt'] . ' komen · ' . $_tel['komt_niet'] . ' kunnen niet · ' . $_tel['onbekend'] . ' nog niet bekend') ?>
    </div>
    <p class="bh-dekkop-uitleg">
      Wie nog niets liet weten, ziet in het portaal de vraag "Kom je?".
      <?= $vb_mag_wijzigen ? 'Bel je iemand, vul het dan hier in.' : 'Het bestuur werkt deze lijst bij.' ?>
      <?php if (($vb_mist ?? '') !== ''): ?><strong><?= h($vb_mist) ?></strong><?php endif; ?>
    </p>
  </section>

  <?php if (!$vb_lijst): ?>
    <div class="ds-kaart ds-kaart-p">
      <?php $leeg_titel = $vb_leeg['titel'] ?? 'Nog niemand uitgenodigd'; $leeg_tekst = $vb_leeg['tekst'] ?? '';
            $leeg_icoon = $vb_leeg['icoon'] ?? 'mensen'; require __DIR__ . '/_leeg.php'; ?>
    </div>
  <?php else: ?>
    <?php
      $filter_actie     = $vb_filter_url;
      $filter_verborgen = $vb_verborgen ?? [];
      $_opties = ['' => 'Iedereen (' . count($vb_lijst) . ')'];
      foreach ($_standen as $_s => $_l) $_opties[$_s] = $_l . ' (' . $_tel[$_s] . ')';
      $filter_velden = [
          ['soort' => 'zoek', 'naam' => 'q', 'label' => 'Zoek op naam', 'waarde' => $_q, 'breed' => true],
          ['soort' => 'keuze', 'naam' => 'komt', 'label' => 'Komt?', 'waarde' => $_fstand, 'opties' => $_opties],
      ];
      $filter_wissen = $_gefilterd ? $vb_filter_url . ($filter_verborgen ? '?' . http_build_query($filter_verborgen) : '') : null;
      require __DIR__ . '/_filterbalk.php';
    ?>

    <?php if (!$_rijen): ?>
      <div class="ds-kaart ds-kaart-p">
        <?php $leeg_titel = 'Niemand gevonden'; $leeg_tekst = 'Pas de zoekterm of het filter aan.'; $leeg_icoon = 'zoek'; require __DIR__ . '/_leeg.php'; ?>
      </div>
    <?php else: ?>
    <section class="ds-kaart">
      <table class="bh-tabel bh-voor">
        <thead><tr><th scope="col">Deelnemer</th><th scope="col">Komt?</th><th scope="col"><?= h($vb_klaar_kop) ?></th><?php if ($vb_mag_wijzigen): ?><th scope="col"><span class="bh-vw">Wijzigen</span></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($_rijen as $_k):
          $_id = (int)$_k['sol_id'];
          $_st = (string)($_k['bevestiging'] ?? 'onbekend');
          if (!isset($_standen[$_st])) $_st = 'onbekend';
          $_kl = ['komt' => 'groen', 'komt_niet' => 'rood'][$_st] ?? 'grijs';
          $_klaar = $vb_klaar($_k);
        ?>
          <tr>
            <th scope="row" class="bh-voor-wie">
              <?= bh_avatar($_k, 36) ?>
              <span>
                <?php if ($vb_mag_dossier): ?><a href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $_id ?>"><?= h((string)$_k['naam']) ?></a><?php else: ?><?= h((string)$_k['naam']) ?><?php endif; ?>
                <?= bh_meta([$_k['leeftijd'] !== null ? $_k['leeftijd'] . ' jaar' : '', fase_label((string)$_k['fase'])]) ?>
              </span>
            </th>
            <td data-label="Komt?">
              <?= bh_badge($_standen[$_st], $_kl) ?>
              <?php if (!empty($_k['bevestiging_reden'])): ?><span class="bh-meta"><?= h((string)$_k['bevestiging_reden']) ?></span><?php endif; ?>
            </td>
            <td data-label="<?= h($vb_klaar_kop) ?>"><?= bh_badge($_klaar[0], $_klaar[1]) ?></td>
            <?php if ($vb_mag_wijzigen): ?>
            <td>
              <form method="post" action="<?= h($vb_post_url) ?>" class="bh-voor-form" data-direct-opslaan>
                <?= csrf_input() ?>
                <input type="hidden" name="actie" value="bevestiging">
                <input type="hidden" name="sol_id" value="<?= $_id ?>">
                <label class="bh-vw" for="bev-<?= $_id ?>">Komt <?= h((string)$_k['naam']) ?>?</label>
                <select name="stand" id="bev-<?= $_id ?>" class="ds-select">
                  <?php // "Kan niet" vraagt eerst een reden (direct_opslaan.js wacht dan met opslaan). ?>
                  <?php foreach ($_standen as $_w => $_l): ?><option value="<?= h($_w) ?>"<?= $_w === $_st ? ' selected' : '' ?><?= $_w === 'komt_niet' ? ' data-vraagt="#red-' . $_id . '"' : '' ?>><?= h($_l) ?></option><?php endforeach; ?>
                </select>
                <label class="bh-vw" for="red-<?= $_id ?>">Opmerking bij <?= h((string)$_k['naam']) ?> (verplicht bij kan niet)</label>
                <input type="text" name="reden" id="red-<?= $_id ?>" class="ds-input" maxlength="300" placeholder="Opmerking" value="<?= h((string)($_k['bevestiging_reden'] ?? '')) ?>">
                <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm" data-direct-knop>Opslaan</button>
                <span class="bh-meta bh-direct-status" data-direct-status aria-live="polite"></span>
              </form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php endif; ?>
    <?php if ($vb_mag_wijzigen): ?>
    <p class="bh-meta">Wijzigingen worden meteen opgeslagen; een opmerking zodra je het veld verlaat.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php if ($vb_mag_wijzigen): ?>
<script src="<?= APP_URL ?>/assets/js/direct_opslaan.js?v=<?= @filemtime(__DIR__ . '/../assets/js/direct_opslaan.js') ?>" defer></script>
<?php endif; ?>
<?php
$vb_lijst = []; $vb_verborgen = []; $vb_mist = ''; $vb_leeg = []; $vb_klaar = null;

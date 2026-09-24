<?php
/**
 * TW-stap Voorbereiden: wie komt er, wie mist nog beschikbaarheid, en de tablet.
 * Onderdeel van admin/trainingsweekend.php (die zet $sid, $kandidaten, $mag_beslissen, …).
 *
 * De bevestiging komt uit drie bronnen: het portaal (de sollicitant zelf), de bellijst van
 * de selectiedag, en hier. "Kan niet" verandert de fase niet — dat beslist het bestuur op
 * Vandaag (afmelden of uitzondering).
 */
$_standen = evenement_bevestig_standen();
$_met_b   = tw_met_beschikbaarheid($sid);
$_tel     = ['komt' => 0, 'komt_niet' => 0, 'onbekend' => 0];
foreach ($kandidaten as $_k) $_tel[isset($_tel[$_k['bevestiging'] ?? '']) ? $_k['bevestiging'] : 'onbekend']++;
$_zonder_b = count(array_filter($kandidaten, fn($k) => empty($_met_b[(int)$k['sol_id']])));
$_bron     = ['portaal' => 'zelf doorgegeven', 'bellijst' => 'via de bellijst', 'beheer' => 'door het bestuur', 'uitzondering' => 'uitzondering'];
?>
<div class="bh-incheck">
  <section class="ds-kaart bh-dekkop">
    <div class="bh-dekkop-zin">
      <?= bh_stoplicht($_tel['onbekend'] + $_tel['komt_niet'] > 0 ? 'krap' : 'goed',
                       $_tel['komt'] . ' komen · ' . $_tel['komt_niet'] . ' kunnen niet · ' . $_tel['onbekend'] . ' nog niet bekend') ?>
    </div>
    <p class="bh-dekkop-uitleg">
      Wie nog niets liet weten, ziet in het portaal de vraag "Kom je?". Bel je iemand, vul het dan hier in.
      <?php if ($_zonder_b > 0): ?>
        <strong><?= $_zonder_b ?> <?= $_zonder_b === 1 ? 'persoon heeft' : 'personen hebben' ?> nog geen beschikbaarheid opgegeven</strong>
        — die heb je nodig voor het gesprek en de loonplanning.
      <?php endif; ?>
    </p>
  </section>

  <?php if ($mag_beslissen) { $tc_ev = 'tw'; $tc_mag_bewerken = true; require __DIR__ . '/../_tablet_code.php'; } ?>

  <?php if (!$kandidaten): ?>
    <div class="ds-kaart ds-kaart-p">
      <?php $leeg_titel = 'Nog niemand uitgenodigd'; $leeg_tekst = 'Zodra je na de selectiedag mensen uitnodigt, staan ze hier.'; $leeg_icoon = 'reddingsboei'; require __DIR__ . '/../_leeg.php'; ?>
    </div>
  <?php else: ?>
  <section class="ds-kaart">
    <table class="bh-tabel bh-twvoor">
      <thead><tr><th scope="col">Deelnemer</th><th scope="col">Komt?</th><th scope="col">Beschikbaarheid</th><?php if ($mag_beslissen): ?><th scope="col"><span class="bh-vw">Wijzigen</span></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($kandidaten as $_k):
        $_id = (int)$_k['sol_id'];
        $_st = (string)($_k['bevestiging'] ?? 'onbekend');
        if (!isset($_standen[$_st])) $_st = 'onbekend';
        $_kl = ['komt' => 'groen', 'komt_niet' => 'rood'][$_st] ?? 'grijs';
      ?>
        <tr>
          <th scope="row" class="bh-twvoor-wie">
            <?= bh_avatar($_k, 36) ?>
            <span>
              <?php if ($mag_dossier): ?><a href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $_id ?>"><?= h((string)$_k['naam']) ?></a><?php else: ?><?= h((string)$_k['naam']) ?><?php endif; ?>
              <?= bh_meta([$_k['leeftijd'] !== null ? $_k['leeftijd'] . ' jaar' : '', fase_label((string)$_k['fase'])]) ?>
            </span>
          </th>
          <td data-label="Komt?">
            <?= bh_badge($_standen[$_st], $_kl) ?>
            <?php if (!empty($_k['bevestiging_reden'])): ?><span class="bh-meta"><?= h((string)$_k['bevestiging_reden']) ?></span><?php endif; ?>
          </td>
          <td data-label="Beschikbaarheid"><?= !empty($_met_b[$_id]) ? bh_badge('Opgegeven', 'groen') : bh_badge('Ontbreekt', 'oranje') ?></td>
          <?php if ($mag_beslissen): ?>
          <td>
            <form method="post" class="bh-twvoor-form">
              <?= csrf_input() ?>
              <input type="hidden" name="actie" value="bevestiging">
              <input type="hidden" name="sol_id" value="<?= $_id ?>">
              <label class="bh-vw" for="bev-<?= $_id ?>">Komt <?= h((string)$_k['naam']) ?>?</label>
              <select name="stand" id="bev-<?= $_id ?>" class="ds-select">
                <?php foreach ($_standen as $_w => $_l): ?><option value="<?= h($_w) ?>"<?= $_w === $_st ? ' selected' : '' ?>><?= h($_l) ?></option><?php endforeach; ?>
              </select>
              <label class="bh-vw" for="red-<?= $_id ?>">Reden (verplicht bij kan niet)</label>
              <input type="text" name="reden" id="red-<?= $_id ?>" class="ds-input" maxlength="300" placeholder="Reden bij kan niet" value="<?= h((string)($_k['bevestiging_reden'] ?? '')) ?>">
              <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Opslaan</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>
</div>

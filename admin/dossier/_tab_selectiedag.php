<?php
/**
 * Tabblad: wat er op de selectiedag is gezien.
 *
 * Scores per criterium met het gemiddelde van alle beoordelaars, en daaronder de
 * observaties. De scores zijn een advies, niet het besluit (ontwerpfilosofie §3);
 * het besluit staat er apart bij, met wie het nam.
 *
 * Verwacht: $tab_dossier  array  uitkomst van bh_dossier()
 */
$tab_dossier = $tab_dossier ?? null;
if (!$tab_dossier) return;

$_scores = $tab_dossier['scores'] ?? [];
$_obs    = $tab_dossier['observaties'] ?? [];
$_besl   = $tab_dossier['besluit'] ?? null;
$_gem    = bh_dossier_score($tab_dossier);

if (!$_scores && !$_obs) {
    echo '<div class="ds-kaart ds-kaart-p">';
    $leeg_titel = 'Nog niet beoordeeld';
    $leeg_tekst = 'Zodra er op de selectiedag scores of observaties zijn vastgelegd, staan ze hier.';
    $leeg_icoon = 'ster';
    require __DIR__ . '/../_leeg.php';
    echo '</div>';
    return;
}
?>
<div class="bh-dossier-kolommen">
  <div class="bh-dossier-hoofd">

    <?php foreach ($_scores as $_test => $_criteria): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2><?= h(ucfirst((string)$_test)) ?></h2>
        <?php $_tgem = bh_dossier_score_per_test($tab_dossier)[$_test] ?? null; ?>
        <?php if ($_tgem !== null): ?>
          <span class="bh-meta bh-num"><?= h(number_format($_tgem, 1, ',', '')) ?> gemiddeld</span>
        <?php endif; ?>
      </div>
      <div class="bh-scorebalken" style="margin-top:0">
        <?php foreach ($_criteria as $_crit => $_d): ?>
          <div class="bh-scorebalk">
            <span class="bh-scorebalk-naam"><?= h(ucfirst(str_replace('_', ' ', (string)$_crit))) ?></span>
            <span class="bh-scorebalk-spoor">
              <span class="bh-scorebalk-vul" style="width:<?= (int)round(((float)$_d['gem']) / 5 * 100) ?>%"></span>
            </span>
            <span class="bh-scorebalk-getal bh-num"><?= h(number_format((float)$_d['gem'], 1, ',', '')) ?></span>
          </div>
          <?php if (count($_d['allen']) > 1): ?>
            <div class="bh-scorebalk-wie">
              <?php foreach ($_d['allen'] as $_door => $_sc): ?>
                <span><?= h($_door) ?> <strong class="bh-num"><?= (int)$_sc ?></strong></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>

    <?php if ($_obs): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>Observaties van die dag</h2>
        <span class="bh-meta"><?= count($_obs) ?></span>
      </div>
      <ul class="bh-observaties">
        <?php foreach ($_obs as $_o):
          $_kleur = ['positief' => 'groen', 'aandacht' => 'rood'][$_o['soort'] ?? ''] ?? 'grijs';
        ?>
          <li class="ds-fase-<?= h($_kleur) ?>">
            <span class="bh-obs-stip" title="<?= h(ucfirst((string)($_o['soort'] ?? 'neutraal'))) ?>"></span>
            <span class="bh-obs-tekst">
              <?= nl2br(h((string)$_o['tekst'])) ?>
              <span class="bh-obs-wie">
                <?= h((string)($_o['beoordelaar'] ?? '')) ?>
                <?php if (!empty($_o['aangemaakt_op'])): ?>
                  · <?= h(datum_nl(substr((string)$_o['aangemaakt_op'], 0, 10))) ?>
                <?php endif; ?>
                · <?= h(ucfirst((string)($_o['soort'] ?? 'neutraal'))) ?>
              </span>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </div>

  <aside class="bh-dossier-zij">
    <?php if ($_gem !== null): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Totaal</h2></div>
      <p class="bh-grootgetal bh-num"><?= h(number_format($_gem, 1, ',', '')) ?></p>
      <span class="bh-meta">gemiddelde over alle criteria</span>
    </section>
    <?php endif; ?>

    <?php if ($_besl && !empty($_besl['besluit'])): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Besluit</h2></div>
      <?php
        $_bk = ['door' => 'groen', 'twijfel' => 'oranje', 'niet' => 'rood'][$_besl['besluit']] ?? 'grijs';
        echo bh_badge(ucfirst((string)$_besl['besluit']), $_bk);
      ?>
      <?php if (!empty($_besl['notitie'])): ?>
        <p class="bh-dossier-tekst" style="margin-top:10px"><?= nl2br(h((string)$_besl['notitie'])) ?></p>
      <?php endif; ?>
      <span class="bh-meta" style="display:block;margin-top:8px">
        <?= !empty($_besl['door']) ? 'door ' . h((string)$_besl['door']) : '' ?>
        <?= !empty($_besl['gebeld_op']) ? ' · gebeld ' . h(datum_nl(substr((string)$_besl['gebeld_op'], 0, 10))) : '' ?>
      </span>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php
$tab_dossier = null;

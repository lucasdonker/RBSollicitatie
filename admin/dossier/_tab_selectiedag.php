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
// Feedback van lifeguards over de selectiedag: vastgelegd in de nabespreking, hier alleen lezen.
$_lgf    = lg_feedback_van((int)$tab_dossier['sol']['id'], ['selectiedag']);

if (!$_scores && !$_obs && !$_lgf) {
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

    <?php $scs_dossier = $tab_dossier; $scs_kaarten = true; require __DIR__ . '/../_sd_scores.php'; ?>

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

    <?php if ($_lgf) {
        $lgf_sol_id = (int)$tab_dossier['sol']['id']; $lgf_seizoen_id = 0; $lgf_moment = 'selectiedag';
        $lgf_mag_bewerken = false; $lgf_terug = ''; $lgf_rijen = $_lgf;
        require __DIR__ . '/../_lg_feedback.php';
    } ?>
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
        <p class="bh-dossier-tekst bh-dossier-besluittekst"><?= nl2br(h((string)$_besl['notitie'])) ?></p>
      <?php endif; ?>
      <span class="bh-meta bh-dossier-voetregel">
        <?= !empty($_besl['door']) ? 'door ' . h((string)$_besl['door']) : '' ?>
        <?= !empty($_besl['gebeld_op']) ? ' · gebeld ' . h(datum_nl(substr((string)$_besl['gebeld_op'], 0, 10))) : '' ?>
      </span>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php
$tab_dossier = null;

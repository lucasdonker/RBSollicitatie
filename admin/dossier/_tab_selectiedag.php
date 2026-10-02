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

if (!$_scores && !$_obs && !$_lgf && empty($_besl['besluit'])) {
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
      <?php $obs_lijst = $_obs; $obs_eigen = null; require __DIR__ . '/../_observaties.php'; ?>
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
      <p class="bh-grootgetal bh-num"><?= h(cijfer_nl((float)$_gem)) ?></p>
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
        <?= !empty($_besl['doorgevoerd_op']) ? ' · doorgevoerd ' . h(datum_nl(substr((string)$_besl['doorgevoerd_op'], 0, 10))) : '' ?>
      </span>
    </section>

    <?php // Wat er op de bellijst is ingevuld (selectie_gebeld_zetten): wie belde, wanneer en wat er is verteld.
      $_tw_bev = function_exists('evenement_bevestiging_van')
          ? evenement_bevestiging_van('tw', (int)$tab_dossier['sol']['seizoen_id'], (int)$tab_dossier['sol']['id']) : ['stand' => 'onbekend'];
      $_tw_bellijst = ($_tw_bev['bron'] ?? '') === 'bellijst' && $_tw_bev['stand'] !== 'onbekend';
    ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Gebeld</h2></div>
      <?php if (!empty($_besl['gebeld_op'])): ?>
        <p class="bh-meta bh-dossier-voetregel">
          <?= h(datum_nl(substr((string)$_besl['gebeld_op'], 0, 10))) ?> om <?= h(date('H:i', strtotime((string)$_besl['gebeld_op']))) ?>
          <?= !empty($_besl['gebeld_door']) ? ' door ' . h((string)$_besl['gebeld_door']) : '' ?>
        </p>
        <span class="bh-veldlabel">Wat is er verteld</span>
        <p class="bh-dossier-tekst"><?= !empty($_besl['gebeld_notitie']) ? nl2br(h((string)$_besl['gebeld_notitie'])) : '<span class="zacht">Geen aantekening gemaakt.</span>' ?></p>
        <?php if ($_tw_bellijst): ?>
          <span class="bh-veldlabel">Trainingsweekend</span>
          <p class="bh-dossier-tekst"><?= h(evenement_bevestig_standen()[$_tw_bev['stand']] ?? '') ?><?= !empty($_tw_bev['reden']) ? ' — ' . h((string)$_tw_bev['reden']) : '' ?></p>
        <?php endif; ?>
      <?php else: ?>
        <p class="bh-dossier-tekst zacht">Nog niet gebeld. Dat gaat via de bellijst van de selectiedag.</p>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php
$tab_dossier = null;

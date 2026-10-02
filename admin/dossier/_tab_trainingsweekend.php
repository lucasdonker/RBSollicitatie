<?php
/**
 * Tabblad: het trainingsweekend — wat er is gezien, en het gesprek met het bestuur.
 *
 * Links de observaties van het weekend en de feedback van lifeguards. Rechts: kwam hij/zij
 * (bevestiging en inchecken), en het besluit met de interne notitie en het bericht dat naar de
 * sollicitant ging. De afgesproken inzet staat op de tab Beschikbaarheid & inzet.
 *
 * Verwacht: $tab_dossier  array  uitkomst van bh_dossier()
 */
$tab_dossier = $tab_dossier ?? null;
if (!$tab_dossier) return;

$_tw  = $tab_dossier['tw'] ?? null;
$_obs = $tab_dossier['tw_observaties'] ?? [];
$_bev = evenement_bevestig_standen();
?>
<div class="bh-dossier-kolommen">
  <div class="bh-dossier-hoofd">
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>Observaties van het weekend</h2>
        <span class="bh-meta"><?= count($_obs) ?></span>
      </div>
      <?php if ($_obs) { $obs_lijst = $_obs; $obs_eigen = null; require __DIR__ . '/../_observaties.php'; }
            else echo '<p class="bh-meta">Niemand heeft iets opgeschreven.</p>'; ?>
    </section>

    <?php // Feedback van strandwachten over het weekend: lezen, en vastleggen met de taak Gesprekken
      // (sinds 2.152.0 ook hier en in de TW-stap Feedback, niet alleen in het gesprek).
      $_sol_id  = (int)$tab_dossier['sol']['id'];
      $_lgf     = lg_feedback_van($_sol_id, ['tw', 'anders']);
      $_lgf_mag = mag(lg_feedback_module('tw'), lg_feedback_taak('tw'));
      if ($_lgf || $_lgf_mag) {
          $lgf_sol_id = $_sol_id; $lgf_seizoen_id = (int)$tab_dossier['sol']['seizoen_id']; $lgf_moment = 'tw';
          $lgf_mag_bewerken = $_lgf_mag; $lgf_rijen = $_lgf;
          $lgf_terug = APP_URL . '/admin/sollicitant.php?id=' . $_sol_id . '&tab=trainingsweekend';
          require __DIR__ . '/../_lg_feedback.php';
      } ?>
  </div>

  <aside class="bh-dossier-zij">
    <?php if ($_tw): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Deelname</h2></div>
      <p>
        <?php $_st = (string)($_tw['bevestiging'] ?? 'onbekend'); ?>
        <?= bh_badge($_bev[$_st] ?? $_bev['onbekend'], ['komt' => 'groen', 'komt_niet' => 'rood'][$_st] ?? 'grijs') ?>
        <?php if (!empty($_tw['bevestiging_reden'])): ?><span class="bh-meta"><?= h((string)$_tw['bevestiging_reden']) ?></span><?php endif; ?>
      </p>
      <?php if (!empty($_tw['eerder_weg'])): ?><p class="bh-dossier-tekst">Moet eerder weg: <?= h(tw_eerder_weg_tekst((string)$_tw['eerder_weg'])) ?>.</p><?php endif; ?>
      <p class="bh-dossier-tekst">
        <?php if (!empty($_tw['aanwezig'])): ?>
          Ingecheckt<?= !empty($_tw['ingecheckt_op']) ? ' om ' . h(date('H:i', strtotime((string)$_tw['ingecheckt_op']))) . ' op ' . h(datum_nl(substr((string)$_tw['ingecheckt_op'], 0, 10))) : '' ?>.
        <?php elseif (!empty($_tw['afgemeld_reden'])): ?>
          Niet gekomen — <?= h((string)$_tw['afgemeld_reden']) ?>
        <?php else: ?>
          Niet ingecheckt.
        <?php endif; ?>
      </p>
    </section>

    <?php if (!empty($_tw['besluit'])): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Gesprek en besluit</h2></div>
      <?php $_bd = tw_besluit_labels()[$_tw['besluit']] ?? [(string)$_tw['besluit'], 'grijs']; ?>
      <?= bh_badge($_bd[0], $_bd[1]) ?>
      <?php if (!empty($_tw['besluit_notitie'])): ?>
        <p class="bh-dossier-tekst bh-dossier-besluittekst"><strong>Intern:</strong> <?= nl2br(h((string)$_tw['besluit_notitie'])) ?></p>
      <?php endif; ?>
      <?php if (!empty($_tw['doorgevoerd_op']) && $_tw['besluit'] !== 'twijfel'): ?>
        <p class="bh-dossier-tekst"><strong>Aan de sollicitant:</strong>
          <?= nl2br(h((string)($_tw['gesprek_bericht'] ?: tw_standaard_bericht((string)$_tw['besluit'], function_exists('tw_niet_gekomen') && tw_niet_gekomen($_tw))))) ?>
          <?php if (empty($_tw['gesprek_bericht'])): ?><span class="bh-meta">(standaardtekst)</span><?php endif; ?>
        </p>
      <?php endif; ?>
      <span class="bh-meta bh-dossier-voetregel">
        <?= !empty($_tw['besluit_door']) ? 'door ' . h((string)$_tw['besluit_door']) : '' ?>
        <?= !empty($_tw['doorgevoerd_op']) ? ' · doorgevoerd ' . h(datum_nl(substr((string)$_tw['doorgevoerd_op'], 0, 10))) : ' · nog niet doorgevoerd' ?>
      </span>
    </section>
    <?php endif; ?>
    <?php endif; ?>

    <?php // De afgesproken inzet staat op de tab Beschikbaarheid & inzet, naast de opgave. ?>
  </aside>
</div>
<?php
$tab_dossier = null;

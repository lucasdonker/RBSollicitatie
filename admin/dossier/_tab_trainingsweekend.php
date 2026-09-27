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

    <?php // Feedback van lifeguards over het weekend: vastgelegd in het TW-gesprek, hier alleen lezen.
      $_lgf = lg_feedback_van((int)$tab_dossier['sol']['id'], ['tw', 'anders']);
      if ($_lgf) {
          $lgf_sol_id = (int)$tab_dossier['sol']['id']; $lgf_seizoen_id = 0; $lgf_moment = 'tw';
          $lgf_mag_bewerken = false; $lgf_terug = ''; $lgf_rijen = $_lgf;
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
      <?= bh_badge(['aannemen' => 'Aangenomen', 'twijfel' => 'Twijfel', 'afwijzen' => 'Afgewezen'][$_tw['besluit']] ?? (string)$_tw['besluit'],
                   ['aannemen' => 'groen', 'twijfel' => 'oranje', 'afwijzen' => 'rood'][$_tw['besluit']] ?? 'grijs') ?>
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

<?php
/**
 * TW-stap Feedback: wat strandwachten het bestuur over sollicitanten vertellen, vastleggen zodra
 * je het hoort — zonder eerst een gesprek te openen (dat claimt de persoon en hoort bij het
 * beslismoment). Eén kaart per kandidaat met de feedback tot nu toe en een invoer; de terugkerende
 * strandwachten onderaan, net als bij Beoordelen. Alleen met de taak Gesprekken (bestuur):
 * beoordelaars zien feedback bewust niet. Opslaan: admin/api_lg_feedback.php (werkt zonder JS).
 * Onderdeel van admin/trainingsweekend.php.
 */
if (!$mag_beslissen) return;
$fb_q      = trim((string)($_GET['q'] ?? ''));
$fb_alle   = lg_feedback_alle($sid);
$fb_kand   = evenement_lijst_filter($kandidaten, $fb_q);
$fb_secties = evenement_beoordeel_secties($fb_kand);
$fb_aantal = array_sum(array_map('count', $fb_alle));
?>
<section class="ds-kaart bh-dekkop">
  <div class="bh-dekkop-zin">
    <?= $fb_aantal === 0 ? 'Nog geen feedback van strandwachten dit seizoen'
        : '<strong class="bh-num">' . $fb_aantal . '</strong>&nbsp;keer feedback van strandwachten dit seizoen' ?>
  </div>
  <p class="bh-dekkop-uitleg">
    Vertelt een strandwacht je iets over een sollicitant? Leg het hier meteen vast. Je ziet het terug in het
    gesprek, in de nabespreking en in het dossier. Beoordelaars zien het niet.
  </p>
</section>

<?php
  $filter_velden = [['soort' => 'zoek', 'naam' => 'q', 'label' => 'Zoek op naam', 'waarde' => $fb_q]];
  $filter_verborgen = ['stap' => 'feedback'];
  $filter_wissen = $fb_q !== '' ? tw_url('feedback') : null;
  require __DIR__ . '/../_filterbalk.php';
?>

<?php if (!$fb_kand): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php $leeg_titel = 'Niemand gevonden'; $leeg_tekst = 'Pas de zoekterm aan.'; $leeg_icoon = 'zoek'; require __DIR__ . '/../_leeg.php'; ?>
  </div>
<?php endif; ?>

<?php foreach ($fb_secties as $_sec): ?>
  <?php if ($_sec['sleutel'] === 'lifeguard'): ?>
  <details class="bh-klap ds-kaart" data-klap-id="tw-feedback-lifeguards">
    <summary>
      <span class="bh-klap-titel"><?= h((string)$_sec['titel']) ?></span>
      <span class="bh-klap-sam"><?= count($_sec['rijen']) ?> · <?= h($_sec['uitleg']) ?></span>
    </summary>
    <div class="bh-klap-inhoud bh-fb-lijst">
  <?php else: ?>
    <?php if ($_sec['titel']): ?><h3 class="bh-kandidaatlijst-kop"><?= h($_sec['titel']) ?></h3><?php endif; ?>
    <div class="bh-fb-lijst">
  <?php endif; ?>

    <?php foreach ($_sec['rijen'] as $_k): $_id = (int)$_k['sol_id']; ?>
      <section class="ds-kaart ds-kaart-p bh-fb-kaart" id="fb-<?= $_id ?>">
        <header class="bh-fb-kop">
          <?= bh_avatar($_k, 38) ?>
          <span class="bh-beoordeel-wie">
            <span class="bh-beoordeel-naam"><?= h((string)$_k['naam']) ?></span>
            <?= bh_meta([$_k['leeftijd'] ? $_k['leeftijd'] . ' jaar' : '', tw_aanwezigheid_tekst($_k)]) ?>
          </span>
        </header>
        <?php
          $lgf_sol_id = $_id; $lgf_seizoen_id = $sid; $lgf_moment = 'tw'; $lgf_mag_bewerken = true;
          $lgf_terug = tw_url('feedback', null, ['q' => $fb_q]) . '#fb-' . $_id;
          $lgf_rijen = $fb_alle[$_id] ?? []; $lgf_kaart = false; $lgf_kop = 'h3';
          require __DIR__ . '/../_lg_feedback.php';
        ?>
      </section>
    <?php endforeach; ?>

  <?php if ($_sec['sleutel'] === 'lifeguard'): ?>
    </div>
  </details>
  <?php else: ?>
    </div>
  <?php endif; ?>
<?php endforeach; ?>

<?php
/**
 * Partial: feedback van lifeguards over een sollicitant — het bestuur legt vast wat een
 * (terugkerende) lifeguard zei. Lifeguards zelf beoordelen niet. Op het tabblad Dossier (met
 * formulier) en in het TW-gesprek (alleen lezen). Logica: includes/lg_feedback.php;
 * opslaan: admin/api_lg_feedback.php. Werkt zonder JavaScript.
 *
 * Verwacht:
 *   $lgf_sol_id       int
 *   $lgf_seizoen_id   int   voor de suggesties bij "Van wie"
 *   $lgf_mag_bewerken bool  formulier en wisknop (sollicitanten › bewerken)
 *   $lgf_kop          string 'h2' | 'h3'
 */
$_lgf_rijen = lg_feedback_van($lgf_sol_id);
$_lgf_kop   = ($lgf_kop ?? 'h2') === 'h3' ? 'h3' : 'h2';
$_lgf_ik    = huidige_beheerder();
?>
<section class="ds-kaart ds-kaart-p" id="lg-feedback">
  <div class="bh-kaartkop">
    <<?= $_lgf_kop ?>>Feedback van lifeguards</<?= $_lgf_kop ?>>
    <?php if ($_lgf_rijen): ?><span class="bh-meta"><?= count($_lgf_rijen) ?></span><?php endif; ?>
  </div>
  <?php if ($_lgf_rijen):
      $obs_lijst = lg_feedback_als_observaties($_lgf_rijen); $obs_plat = true;
      $obs_eigen = $lgf_mag_bewerken ? fn($o) => ($_lgf_ik['rol'] ?? '') === 'admin' || (int)($o['vastgelegd_door'] ?? 0) === (int)$_lgf_ik['id'] : null;
      $obs_wis_form = ['url' => APP_URL . '/admin/api_lg_feedback.php', 'actie' => 'lg_feedback_wis', 'velden' => ['sol_id' => $lgf_sol_id]];
      require __DIR__ . '/_observaties.php';
  else: ?>
    <p class="bh-meta">Nog geen feedback. Vraag je een lifeguard wat hij of zij van deze sollicitant vindt, leg het dan hier vast.</p>
  <?php endif; ?>

  <?php if ($lgf_mag_bewerken): $_lgf_namen = lg_feedback_namen($lgf_seizoen_id); ?>
  <details class="bh-lgf">
    <summary>Feedback vastleggen</summary>
    <form method="post" action="<?= APP_URL ?>/admin/api_lg_feedback.php" class="bh-lgf-form">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="lg_feedback">
      <input type="hidden" name="sol_id" value="<?= (int)$lgf_sol_id ?>">
      <div class="bh-lgf-rij">
        <div class="ds-veld">
          <label class="ds-label" for="lgf-van-<?= (int)$lgf_sol_id ?>">Van wie</label>
          <input type="text" class="ds-input" id="lgf-van-<?= (int)$lgf_sol_id ?>" name="van" maxlength="100" required autocomplete="off"
                 <?= $_lgf_namen ? 'list="lgf-namen-' . (int)$lgf_sol_id . '"' : '' ?> placeholder="Naam van de lifeguard">
          <?php if ($_lgf_namen): ?>
            <datalist id="lgf-namen-<?= (int)$lgf_sol_id ?>"><?php foreach ($_lgf_namen as $_n): ?><option value="<?= h($_n) ?>"><?php endforeach; ?></datalist>
          <?php endif; ?>
        </div>
        <div class="ds-veld">
          <label class="ds-label" for="lgf-moment-<?= (int)$lgf_sol_id ?>">Wanneer gezien</label>
          <select class="ds-select" id="lgf-moment-<?= (int)$lgf_sol_id ?>" name="moment">
            <?php foreach (lg_feedback_momenten() as $_w => $_l): ?><option value="<?= h($_w) ?>"<?= $_w === 'tw' ? ' selected' : '' ?>><?= h($_l) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="ds-veld">
          <label class="ds-label" for="lgf-soort-<?= (int)$lgf_sol_id ?>">Soort</label>
          <select class="ds-select" id="lgf-soort-<?= (int)$lgf_sol_id ?>" name="soort">
            <option value="positief">Positief</option><option value="neutraal" selected>Neutraal</option><option value="aandacht">Aandachtspunt</option>
          </select>
        </div>
      </div>
      <div class="ds-veld">
        <label class="ds-label" for="lgf-tekst-<?= (int)$lgf_sol_id ?>">Wat zei hij of zij?</label>
        <textarea class="ds-textarea" id="lgf-tekst-<?= (int)$lgf_sol_id ?>" name="tekst" rows="3" maxlength="2000" required></textarea>
        <p class="ds-veld-hint">Alleen voor het bestuur. De sollicitant kan dit opvragen (AVG), zonder de naam van de lifeguard.</p>
      </div>
      <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Vastleggen</button>
    </form>
  </details>
  <?php endif; ?>
</section>
<?php
$lgf_kop = null;

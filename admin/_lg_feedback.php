<?php
/**
 * Partial: feedback van lifeguards over een sollicitant — het bestuur legt vast wat een
 * (terugkerende) lifeguard zei. Lifeguards zelf beoordelen niet. Hoort bij de modules:
 * SD-nabespreking (invoer), SD-bellijst (lezen), TW-stap Feedback en TW-gesprek (invoer), en de
 * dossiertabs Selectiedag/Trainingsweekend (invoer met dezelfde taak, anders lezen). Logica: includes/lg_feedback.php;
 * opslaan: admin/api_lg_feedback.php. Werkt zonder JavaScript.
 *
 * Verwacht:
 *   $lgf_sol_id       int
 *   $lgf_seizoen_id   int     voor de suggesties bij "Van wie"
 *   $lgf_moment       string  'selectiedag' | 'tw' — waar dit blok staat; nieuwe feedback krijgt dit moment
 *   $lgf_mag_bewerken bool    formulier en wisknop (module › bewerken)
 *   $lgf_terug        string  URL om na opslaan naar terug te gaan (binnen /admin/)
 * Optioneel:
 *   $lgf_rijen        ?array  al geladen rijen (voorkom een query per kaart); null = zelf laden
 *   $lgf_momenten     array   alleen deze momenten tonen; [] = alles
 *   $lgf_kaart        bool    true (standaard) = eigen kaart met kop; false = kaal blok in een bestaande kaart
 *   $lgf_kop          string  'h2' | 'h3'
 */
$_lgf_rijen = $lgf_rijen ?? lg_feedback_van($lgf_sol_id, $lgf_momenten ?? []);
$_lgf_kop   = ($lgf_kop ?? 'h2') === 'h3' ? 'h3' : 'h2';
$_lgf_kaart = $lgf_kaart ?? true;
$_lgf_ik    = huidige_beheerder();
$_lgf_id    = (int)$lgf_sol_id;
// Kaal blok zonder feedback (nabespreking: veel kaarten onder elkaar): alleen de uitklapper, geen kop.
$_lgf_klein = !$_lgf_kaart && !$_lgf_rijen;
?>
<<?= $_lgf_kaart ? 'section class="ds-kaart ds-kaart-p"' : 'div class="bh-lgf-blok' . ($_lgf_klein ? ' klein' : '') . '"' ?> id="lg-feedback-<?= $_lgf_id ?>">
  <?php if (!$_lgf_klein): ?>
  <div class="bh-kaartkop">
    <<?= $_lgf_kop ?>>Feedback van strandwachten</<?= $_lgf_kop ?>>
    <?php if ($_lgf_rijen): ?><span class="bh-meta"><?= count($_lgf_rijen) ?></span><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if ($_lgf_klein):
      // niets: de uitklapper hieronder zegt genoeg
  elseif ($_lgf_rijen):
      $obs_lijst = lg_feedback_als_observaties($_lgf_rijen); $obs_plat = true;
      $obs_eigen = $lgf_mag_bewerken ? fn($o) => ($_lgf_ik['rol'] ?? '') === 'admin' || (int)($o['vastgelegd_door'] ?? 0) === (int)$_lgf_ik['id'] : null;
      $obs_wis_form = ['url' => APP_URL . '/admin/api_lg_feedback.php', 'actie' => 'lg_feedback_wis', 'velden' => ['terug' => $lgf_terug]];
      require __DIR__ . '/_observaties.php';
  elseif ($lgf_mag_bewerken): ?>
    <p class="bh-meta">Nog niets. Vertelde een strandwacht je iets over <?= $lgf_moment === 'tw' ? 'het weekend' : 'de selectiedag' ?>? Leg het hier vast.</p>
  <?php else: ?>
    <p class="bh-meta">Geen feedback van strandwachten.</p>
  <?php endif; ?>

  <?php if ($lgf_mag_bewerken): $_lgf_namen = lg_feedback_namen($lgf_seizoen_id); ?>
  <details class="bh-lgf">
    <summary><?= $_lgf_klein ? 'Feedback van een strandwacht vastleggen' : 'Feedback vastleggen' ?></summary>
    <form method="post" action="<?= APP_URL ?>/admin/api_lg_feedback.php" class="bh-lgf-form">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="lg_feedback">
      <input type="hidden" name="sol_id" value="<?= $_lgf_id ?>">
      <input type="hidden" name="moment" value="<?= h($lgf_moment) ?>">
      <input type="hidden" name="terug" value="<?= h($lgf_terug) ?>">
      <div class="bh-lgf-rij">
        <div class="ds-veld">
          <label class="ds-label" for="lgf-van-<?= $_lgf_id ?>">Van wie</label>
          <input type="text" class="ds-input" id="lgf-van-<?= $_lgf_id ?>" name="van" maxlength="100" required autocomplete="off"
                 <?= $_lgf_namen ? 'list="lgf-namen"' : '' ?> placeholder="Naam van de strandwacht">
        </div>
        <div class="ds-veld">
          <label class="ds-label" for="lgf-soort-<?= $_lgf_id ?>">Soort</label>
          <select class="ds-select" id="lgf-soort-<?= $_lgf_id ?>" name="soort">
            <option value="positief">Positief</option><option value="neutraal" selected>Neutraal</option><option value="aandacht">Aandachtspunt</option>
          </select>
        </div>
      </div>
      <div class="ds-veld">
        <label class="ds-label" for="lgf-tekst-<?= $_lgf_id ?>">Wat zei hij of zij?</label>
        <textarea class="ds-textarea" id="lgf-tekst-<?= $_lgf_id ?>" name="tekst" rows="3" maxlength="2000" required></textarea>
        <p class="ds-veld-hint">Alleen voor het bestuur. De sollicitant kan dit opvragen (AVG), zonder de naam van de strandwacht.</p>
      </div>
      <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Vastleggen</button>
    </form>
  </details>
  <?php
    // Eén gedeelde lijst met suggesties per pagina (de nabespreking toont veel kaarten).
    if ($_lgf_namen && empty($GLOBALS['_lgf_datalist'])): $GLOBALS['_lgf_datalist'] = true; ?>
    <datalist id="lgf-namen"><?php foreach ($_lgf_namen as $_n): ?><option value="<?= h($_n) ?>"><?php endforeach; ?></datalist>
  <?php endif; ?>
  <?php endif; ?>
</<?= $_lgf_kaart ? 'section' : 'div' ?>>
<?php
$lgf_kop = null; $lgf_rijen = null; $lgf_momenten = null; $lgf_kaart = null;

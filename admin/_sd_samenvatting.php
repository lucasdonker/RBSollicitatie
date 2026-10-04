<?php
/**
 * Partial: wat er op de selectiedag over iemand is vastgelegd — advies, de cijfers kort (per
 * onderdeel uitklapbaar), het besluit met de notitie, en de observaties. Standaard ingeklapt:
 * ouder materiaal over dezelfde persoon, herleidbaar maar niet even zwaar als wat er nu opvalt.
 *
 * Verwacht:
 *   $sds_dossier  array  uit bh_dossier()
 *   $sds_open     bool   optioneel, standaard dicht
 *   $sds_cijfers_open bool optioneel: de cijfers per onderdeel staan open (TW-gesprek)
 *   $sds_zonder_besluit bool optioneel: geen advies, besluit en notities van het bestuur — voor
 *                 beoordelaars op het trainingsweekend (need-to-know; zij zien nooit een besluit)
 * Toont niets als de module Selectiedag uitstaat of er niets is vastgelegd.
 */
if (function_exists('module_aan') && !module_aan('selectiedag')) return;
$_zonder = !empty($sds_zonder_besluit);
$_adv   = $_zonder ? null : bh_dossier_advies($sds_dossier);
$_gem   = bh_dossier_score($sds_dossier);
$_bsl   = $_zonder ? null : ($sds_dossier['besluit'] ?? null);
$_obs   = $sds_dossier['observaties'] ?? [];
$_sc    = $sds_dossier['scores'] ?? [];
// Ook met alleen cijfers: tot 2.149.0 verdween het blok voor beoordelaars als er geen observaties waren.
if (!$_adv && !$_bsl && !$_obs && !$_sc) { $sds_open = false; $sds_zonder_besluit = false; $sds_cijfers_open = false; return; }
$_bsl_label = selectie_besluit_labels()[$_bsl['besluit'] ?? ''][0] ?? null;
// In de (ingeklapte) kop al het cijfer per test: het korte overzicht zonder uitklappen.
$_crit_def = selectie_criteria();
$_per_test = [];
foreach (bh_dossier_score_per_test(['scores' => $_sc]) as $_t => $_g) $_per_test[] = ($_crit_def[$_t]['naam'] ?? ucfirst((string)$_t)) . ' ' . cijfer_nl((float)$_g);
?>
<details class="bh-sdsamen"<?= !empty($sds_open) ? ' open' : '' ?>>
  <summary>
    Selectiedag
    <span class="bh-meta"><?= h(implode(' · ', array_filter([
        $_gem !== null ? 'gemiddeld ' . cijfer_nl((float)$_gem) : '',
        implode(' · ', $_per_test),
        $_bsl_label ? 'besluit: ' . mb_strtolower($_bsl_label) : '',
        $_obs ? count($_obs) . ' ' . (count($_obs) === 1 ? 'observatie' : 'observaties') : '',
    ]))) ?></span>
  </summary>
  <div class="bh-sdsamen-inhoud">
    <?php if ($_adv): ?>
      <p><?= bh_badge('Advies: ' . $_adv['label'], $_adv['kleur']) ?> <span class="bh-meta"><?= h($_adv['reden']) ?></span></p>
    <?php endif; ?>
    <?php if ($_sc) { $sck_scores = $_sc; $sck_open = !empty($sds_cijfers_open); require __DIR__ . '/_sd_cijfers_kort.php'; } ?>
    <?php if ($_bsl_label): ?>
      <p><strong>Besluit na de selectiedag:</strong> <?= h($_bsl_label) ?><?php
        if (!empty($_bsl['notitie'])): ?> — <?= h((string)$_bsl['notitie']) ?><?php endif; ?>
        <?php if (!empty($_bsl['gebeld_notitie'])): ?><br><span class="bh-meta">Na het bellen: <?= h((string)$_bsl['gebeld_notitie']) ?></span><?php endif; ?>
      </p>
    <?php endif; ?>
    <?php if ($_obs) { $obs_lijst = $_obs; $obs_eigen = null; require __DIR__ . '/_observaties.php'; } ?>
  </div>
</details>
<?php $sds_open = false; $sds_zonder_besluit = false; $sds_cijfers_open = false;

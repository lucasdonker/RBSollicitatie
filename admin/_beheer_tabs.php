<?php
/**
 * Partial: de twee tabbladen van "Beheerders & rechten".
 *
 * Twee schermen die bij elkaar horen: wie er binnenkomt (beheerders) en wat een rol
 * mag (rollen). Ze staan als één nav-item in de zijbalk, dus de wissel hoort hier.
 *
 * Verwacht: $bt_actief  'beheerders' | 'rollen'
 */
$bt_actief = $bt_actief ?? 'beheerders';
?>
<div class="bh-tabs" role="tablist" aria-label="Beheerders en rechten">
  <a class="bh-tab<?= $bt_actief === 'beheerders' ? ' actief' : '' ?>"
     href="<?= APP_URL ?>/admin/beheerders.php" role="tab"
     aria-selected="<?= $bt_actief === 'beheerders' ? 'true' : 'false' ?>">Beheerders</a>
  <a class="bh-tab<?= $bt_actief === 'rollen' ? ' actief' : '' ?>"
     href="<?= APP_URL ?>/admin/rollen.php" role="tab"
     aria-selected="<?= $bt_actief === 'rollen' ? 'true' : 'false' ?>">Rollen &amp; rechten</a>
</div>
<?php
$bt_actief = 'beheerders';

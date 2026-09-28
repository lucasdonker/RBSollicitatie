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
<?php // Links naar een andere pagina zijn navigatie, geen tabpaneel: nav + aria-current. ?>
<nav class="bh-tabs" aria-label="Beheerders en rechten">
  <a class="bh-tab<?= $bt_actief === 'beheerders' ? ' actief' : '' ?>"
     href="<?= APP_URL ?>/admin/beheerders.php"<?= $bt_actief === 'beheerders' ? ' aria-current="page"' : '' ?>>Beheerders</a>
  <a class="bh-tab<?= $bt_actief === 'rollen' ? ' actief' : '' ?>"
     href="<?= APP_URL ?>/admin/rollen.php"<?= $bt_actief === 'rollen' ? ' aria-current="page"' : '' ?>>Rollen &amp; rechten</a>
</nav>
<?php
$bt_actief = 'beheerders';

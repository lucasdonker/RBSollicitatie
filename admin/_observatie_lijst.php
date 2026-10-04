<?php
/**
 * Partial: de observaties van één persoon, of "Nog niets opgeschreven." Eén bron voor de eerste
 * weergave (admin/_observatie_invoer.php) en voor de HTML die het scherm na opslaan terugstuurt
 * (POST actie=observatie op admin/selectiedag.php en admin/trainingsweekend.php).
 *
 * Verwacht: $obs_lijst, $obs_eigen — zie admin/_observaties.php.
 */
if (!empty($obs_lijst)) require __DIR__ . '/_observaties.php';
else echo '<p class="bh-meta">Nog niets opgeschreven.</p>';

<?php
/**
 * Partial: één versleepbaar kaartje in de groepsindeling.
 *
 * Verwacht:
 *   $_kand      array  kandidaat uit selectie_kandidaten()
 *   $_conflict  bool   zit er iemand in dezelfde groep die uit elkaar moet?
 *   $mag_bewerken bool (van het scherm)
 *
 * De conflictmelding staat in tekst en niet alleen in kleur: wie kleurenblind is moet
 * hem ook zien (ontwerpfilosofie, toegankelijkheid).
 */
$_kand     = $_kand ?? null;
$_conflict = $_conflict ?? false;
if (!$_kand) return;
$_naam = (string)($_kand['naam'] ?? '');
?>
<div class="bh-kandkaart<?= $_conflict ? ' botst' : '' ?>"
     <?= !empty($mag_bewerken) ? 'draggable="true"' : '' ?>
     data-sol="<?= (int)$_kand['sol_id'] ?>"
     <?= !empty($mag_bewerken)
         ? 'onclick="relKies(' . (int)$_kand['sol_id'] . ', ' . htmlspecialchars(json_encode($_naam), ENT_QUOTES) . ')"'
         : '' ?>>
  <span class="bh-kandkaart-naam"><?= h($_naam) ?></span>
  <span class="bh-kandkaart-meta">
    <?= !empty($_kand['leeftijd']) ? (int)$_kand['leeftijd'] . 'j' : '' ?>
    <?= !empty($_kand['geslacht']) ? strtoupper(substr((string)$_kand['geslacht'], 0, 1)) : '' ?>
  </span>
  <?php if ($_conflict): ?>
    <span class="bh-kandkaart-botst" title="Zit bij iemand die uit elkaar moet">
      <?= icoon('waarschuwing', 13) ?>botst
    </span>
  <?php endif; ?>
</div>
<?php
$_conflict = false;

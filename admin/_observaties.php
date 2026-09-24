<?php
/**
 * Partial: een lijst observaties (selectiedag of trainingsweekend), gekleurd per soort — met
 * de soort ook als tekst, want kleur alleen zegt niets voor wie hem niet ziet.
 *
 * Verwacht:
 *   $obs_lijst  array   rijen met tekst, soort, beoordelaar, id
 *   $obs_eigen  ?callable fn(array $o): bool — geeft een wisknop (data-obs-wis) bij eigen observaties;
 *                         null = alleen lezen
 */
$_soortlabel = ['positief' => 'positief', 'aandacht' => 'aandacht', 'neutraal' => 'neutraal'];
?>
<ul class="bh-observaties">
  <?php foreach ($obs_lijst as $_o):
    $_s = (string)($_o['soort'] ?? 'neutraal');
    $_kleur = ['positief' => 'groen', 'aandacht' => 'rood'][$_s] ?? 'grijs';
  ?>
  <li class="ds-fase-<?= h($_kleur) ?>"<?= isset($_o['id']) ? ' data-obs="' . (int)$_o['id'] . '"' : '' ?>>
    <span class="bh-obs-stip"></span>
    <span class="bh-obs-tekst">
      <?= nl2br(h((string)$_o['tekst'])) ?>
      <span class="bh-obs-wie"><?= h(implode(' · ', array_filter([(string)($_o['beoordelaar'] ?? ''), $_soortlabel[$_s] ?? '']))) ?></span>
    </span>
    <?php if (!empty($obs_eigen) && $obs_eigen($_o)): ?>
      <button type="button" class="bh-obs-wis" data-obs-wis="<?= (int)$_o['id'] ?>" aria-label="Observatie verwijderen"><?= icoon('kruis', 14) ?></button>
    <?php endif; ?>
  </li>
  <?php endforeach; ?>
</ul>
<?php $obs_eigen = null;

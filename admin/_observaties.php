<?php
/**
 * Partial: een lijst observaties (selectiedag of trainingsweekend), gekleurd per soort — met
 * de soort ook als tekst, want kleur alleen zegt niets voor wie hem niet ziet.
 *
 * Beslaan de observaties meer dan één dag (het trainingsweekend), dan komen ze per dag onder
 * een inklapbare kop, nieuwste dag bovenaan en open; de eerdere dagen ingeklapt. Eén dag
 * (de selectiedag) blijft een platte lijst.
 *
 * Verwacht:
 *   $obs_lijst  array   rijen met tekst, soort, beoordelaar, id, aangemaakt_op — nieuwste eerst
 *   $obs_eigen  ?callable fn(array $o): bool — geeft een wisknop (data-obs-wis) bij eigen observaties;
 *                         null = alleen lezen
 *   $obs_wis_form ?array  ['url' => …, 'actie' => …, 'velden' => [naam => waarde]] — de wisknop wordt dan een gewoon formulier
 *                         (POST actie + id, met bevestiging) in plaats van een JavaScript-knop
 *   $obs_plat   ?bool     true = nooit per dag groeperen (bijv. feedback van lifeguards)
 */
$_soortlabel = ['positief' => 'positief', 'aandacht' => 'aandacht', 'neutraal' => 'neutraal'];
$obs_wis_form = $obs_wis_form ?? null;
$obs_plat     = $obs_plat ?? false;
$_obs_dagen  = [];
foreach ($obs_lijst as $_o) $_obs_dagen[substr((string)($_o['aangemaakt_op'] ?? ''), 0, 10)][] = $_o;
krsort($_obs_dagen, SORT_STRING);   // nieuwste dag bovenaan, welke volgorde de bron ook levert
$_obs_rij = function (array $_o) use ($_soortlabel, $obs_eigen, $obs_wis_form): void {
    $_s = (string)($_o['soort'] ?? 'neutraal');
    $_kleur = ['positief' => 'groen', 'aandacht' => 'rood'][$_s] ?? 'grijs';
    ?>
  <li class="ds-fase-<?= h($_kleur) ?>"<?= isset($_o['id']) ? ' data-obs="' . (int)$_o['id'] . '"' : '' ?>>
    <span class="bh-obs-stip"></span>
    <span class="bh-obs-tekst">
      <?= nl2br(h((string)$_o['tekst'])) ?>
      <span class="bh-obs-wie"><?= h(implode(' · ', array_filter([(string)($_o['beoordelaar'] ?? ''), $_soortlabel[$_s] ?? '',
                                                                   !empty($_o['aangemaakt_op']) ? date('H:i', strtotime((string)$_o['aangemaakt_op'])) : '']))) ?></span>
    </span>
    <?php if (!empty($obs_eigen) && $obs_eigen($_o) && $obs_wis_form): ?>
      <form method="post" action="<?= h($obs_wis_form['url']) ?>" data-bevestig="Verwijderen? Dit kun je niet terugdraaien.">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="<?= h($obs_wis_form['actie']) ?>">
        <input type="hidden" name="id" value="<?= (int)$_o['id'] ?>">
        <?php foreach ($obs_wis_form['velden'] ?? [] as $_vn => $_vw): ?><input type="hidden" name="<?= h((string)$_vn) ?>" value="<?= h((string)$_vw) ?>"><?php endforeach; ?>
        <button type="submit" class="bh-obs-wis" aria-label="Verwijderen"><?= icoon('kruis', 14) ?></button>
      </form>
    <?php elseif (!empty($obs_eigen) && $obs_eigen($_o)): ?>
      <button type="button" class="bh-obs-wis" data-obs-wis="<?= (int)$_o['id'] ?>" aria-label="Observatie verwijderen"><?= icoon('kruis', 14) ?></button>
    <?php endif; ?>
  </li>
    <?php
};
?>
<?php if ($obs_plat || count($_obs_dagen) <= 1): ?>
<ul class="bh-observaties">
  <?php foreach ($obs_lijst as $_o) $_obs_rij($_o); ?>
</ul>
<?php else: $_obs_eerste = true; ?>
<div class="bh-obsdagen">
  <?php foreach ($_obs_dagen as $_dag => $_rijen): ?>
  <details class="bh-obsdag"<?= $_obs_eerste ? ' open' : '' ?>>
    <summary><span class="bh-obsdag-titel"><?= h($_dag !== '' ? datum_dag_nl($_dag) : 'Zonder datum') ?></span>
      <span class="bh-meta"><?= count($_rijen) ?> <?= count($_rijen) === 1 ? 'observatie' : 'observaties' ?></span></summary>
    <ul class="bh-observaties">
      <?php foreach ($_rijen as $_o) $_obs_rij($_o); ?>
    </ul>
  </details>
  <?php $_obs_eerste = false; endforeach; ?>
</div>
<?php endif; ?>
<?php $obs_eigen = null; $obs_wis_form = null; $obs_plat = null; $_obs_rij = null;

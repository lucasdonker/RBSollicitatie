<?php
/**
 * Partial: donkere bulkbalk met teller.
 *
 * Verschijnt zodra er iets is aangevinkt. Elke knop die bericht verstuurt, meldt dat
 * ook — de bevestiging erna zegt naar hoeveel mensen (ontwerpfilosofie §8,
 * "onomkeerbaar vraagt bevestiging en zegt wat er uitgaat").
 *
 * Verwacht:
 *   $bulk_acties  array   elk: ['actie','label','toon'=>'primair|donker|gevaar',
 *                               'mail'=>bool, 'alleen_fases'=>?array, 'recht'=>?[mod,actie]]
 *   $bulk_voet    ?string optioneel: uitleg rechts in de balk
 *
 * De balk staat altijd in de HTML (verborgen), zodat JavaScript hem alleen hoeft te
 * tonen en niet hoeft op te bouwen. Zonder JS blijft hij verborgen; de rij-acties en
 * het dossier zijn dan de weg.
 */
$bulk_acties = $bulk_acties ?? [];
$bulk_voet   = $bulk_voet ?? null;

$_zichtbaar = [];
foreach ($bulk_acties as $_b) {
    if (!empty($_b['recht']) && function_exists('mag') && !mag($_b['recht'][0], $_b['recht'][1] ?? 'bewerken')) continue;
    $_zichtbaar[] = $_b;
}
if ($_zichtbaar):
?>
<div class="bh-bulkbalk" id="bulkbalk" hidden aria-live="polite">
  <span class="bh-bulk-teller"><span data-bulk-aantal>0</span> geselecteerd</span>
  <span class="bh-bulk-streep" aria-hidden="true"></span>
  <?php foreach ($_zichtbaar as $_b): ?>
    <button type="submit" name="bulk_actie" value="<?= h($_b['actie']) ?>"
            class="bh-bulk-knop<?= ($_b['toon'] ?? '') === 'primair' ? ' primair' : '' ?><?= ($_b['toon'] ?? '') === 'gevaar' ? ' gevaar' : '' ?>"
            <?= !empty($_b['alleen_fases']) ? 'data-fases="' . h(implode(',', $_b['alleen_fases'])) . '"' : '' ?>
            <?= !empty($_b['mail']) ? 'data-mail="1"' : '' ?>>
      <?= h($_b['label']) ?>
    </button>
  <?php endforeach; ?>
  <?php if ($bulk_voet): ?>
    <span class="bh-bulk-voet"><?= h($bulk_voet) ?></span>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php
$bulk_acties = []; $bulk_voet = null;

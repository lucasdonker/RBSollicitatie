<?php
/**
 * TW-stap Evaluatie (bestuur): na een dag beoordelen zegt het bestuur per persoon alvast
 * sowieso aannemen, twijfel of sowieso afwijzen. Geen besluit — dat valt in het gesprek.
 * Wie op twijfel staat, komt bij Beoordelen bovenaan onder "extra aandacht gevraagd", met de
 * vraag die je hier invult. Aannemen of afwijzen zien de beoordelaars nooit.
 * Onderdeel van admin/trainingsweekend.php; logica in tw_evaluatie_zetten().
 * Werkt zonder JavaScript: per persoon een eigen formulier.
 */
$_st   = tw_evaluatie_standen();
$_obs  = tw_observaties_alle($sid);
$_sd   = (module_aan('selectiedag') && function_exists('selectie_scores_alle')) ? selectie_scores_alle($sid) : [];
$_toon = (string)($_GET['toon'] ?? '');
$_tel  = ['aannemen' => 0, 'twijfel' => 0, 'afwijzen' => 0, 'geen' => 0];
foreach ($kandidaten as $_k) $_tel[isset($_st[$_k['evaluatie'] ?? '']) ? $_k['evaluatie'] : 'geen']++;
$_lijst = array_values(array_filter($kandidaten, function ($k) use ($_toon, $_st) {
    $e = isset($_st[$k['evaluatie'] ?? '']) ? $k['evaluatie'] : 'geen';
    return $_toon === '' || $_toon === $e;
}));
$_kleur = ['aannemen' => 'groen', 'twijfel' => 'oranje', 'afwijzen' => 'rood'];
?>
<section class="ds-kaart bh-dekkop">
  <div class="bh-dekkop-zin">
    <?= bh_stoplicht($_tel['geen'] > 0 ? 'krap' : 'goed',
                     $_tel['aannemen'] . ' sowieso aannemen · ' . $_tel['twijfel'] . ' twijfel · ' . $_tel['afwijzen'] . ' sowieso afwijzen · ' . $_tel['geen'] . ' nog niet') ?>
  </div>
  <p class="bh-dekkop-uitleg">
    Nog geen besluit, alleen jullie eerste indruk. Wie op <strong>twijfel</strong> staat, komt bij Beoordelen bovenaan
    met jullie vraag erbij, zodat de beoordelaars daar de rest van het weekend extra op letten. Aannemen of afwijzen
    zien zij niet.
  </p>
</section>

<?php
  $filter_verborgen = ['stap' => 'evaluatie'];
  $filter_velden = [
    ['soort' => 'keuze', 'naam' => 'toon', 'label' => 'Toon', 'waarde' => $_toon,
     'opties' => ['' => 'Iedereen (' . count($kandidaten) . ')', 'geen' => 'Nog niet geëvalueerd (' . $_tel['geen'] . ')',
                  'twijfel' => 'Twijfel (' . $_tel['twijfel'] . ')', 'aannemen' => 'Sowieso aannemen (' . $_tel['aannemen'] . ')',
                  'afwijzen' => 'Sowieso afwijzen (' . $_tel['afwijzen'] . ')']],
  ];
  $filter_wissen = $_toon !== '' ? tw_url('evaluatie') : null;
  require __DIR__ . '/../_filterbalk.php';
?>

<?php if (!$_lijst): ?>
  <div class="ds-kaart ds-kaart-p">
    <?php $leeg_titel = 'Niemand in deze selectie'; $leeg_tekst = 'Kies bij Toon een andere groep.'; $leeg_icoon = 'zoek'; require __DIR__ . '/../_leeg.php'; ?>
  </div>
<?php else: ?>
<ul class="bh-twevaluatie">
  <?php foreach ($_lijst as $_k):
    $_id  = (int)$_k['sol_id'];
    $_e   = (string)($_k['evaluatie'] ?? '');
    $_pos = count(array_filter($_obs[$_id] ?? [], fn($o) => ($o['soort'] ?? '') === 'positief'));
    $_aan = count(array_filter($_obs[$_id] ?? [], fn($o) => ($o['soort'] ?? '') === 'aandacht'));
    $_gem = function_exists('selectie_gemiddelde') ? selectie_gemiddelde($_sd[$_id] ?? []) : null;
  ?>
  <li class="ds-kaart bh-twevaluatie-rij" id="ev-<?= $_id ?>">
    <div class="bh-twevaluatie-wie">
      <?= bh_avatar($_k, 44) ?>
      <span>
        <span class="bh-twgesprek-naam"><?= h((string)$_k['naam']) ?></span>
        <?= bh_meta([!empty($_k['aanwezig']) ? 'aanwezig' : (($_k['afgemeld_reden'] ?? null) !== null ? 'niet gekomen' : 'niet ingecheckt'),
                     count($_obs[$_id] ?? []) ? $_pos . ' positief · ' . $_aan . ' aandacht' : 'nog geen observaties',
                     $_gem !== null ? 'selectiedag ' . number_format($_gem, 1, ',', '') : '',
                     !empty($_k['is_lifeguard']) ? 'terugkerende lifeguard' : '']) ?>
        <?php if ($_e !== '' && !empty($_k['evaluatie_door'])): ?>
          <span class="bh-meta"><?= h((string)$_k['evaluatie_door']) ?>, <?= h(datum_dag_nl((string)$_k['evaluatie_op'])) ?> <?= h(date('H:i', strtotime((string)$_k['evaluatie_op']))) ?></span>
        <?php endif; ?>
      </span>
    </div>

    <?php if (!empty($_k['doorgevoerd_op'])): ?>
      <p class="bh-meta">Het gesprek is al geweest; het besluit staat bij Gesprekken.</p>
    <?php else: ?>
    <form method="post" class="bh-twevaluatie-form">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="evaluatie">
      <input type="hidden" name="sol_id" value="<?= $_id ?>">
      <input type="hidden" name="toon" value="<?= h($_toon) ?>">
      <fieldset class="bh-twkeuze">
        <legend class="bh-vw">Eerste indruk van <?= h((string)$_k['naam']) ?></legend>
        <?php foreach ($_st as $_w => $_l): ?>
          <label class="bh-twoptie <?= h($_w) ?>"><input type="radio" name="evaluatie" value="<?= h($_w) ?>"<?= $_e === $_w ? ' checked' : '' ?>><span><?= h($_l) ?></span></label>
        <?php endforeach; ?>
        <label class="bh-twoptie"><input type="radio" name="evaluatie" value=""<?= $_e === '' ? ' checked' : '' ?>><span>Nog niet</span></label>
      </fieldset>
      <label class="bh-vw" for="evb-<?= $_id ?>">Vraag aan de beoordelaars, alleen bij twijfel</label>
      <input type="text" class="ds-input" id="evb-<?= $_id ?>" name="evaluatie_bericht" maxlength="500"
             placeholder="Bij twijfel: waar moeten de beoordelaars op letten?" value="<?= h((string)($_k['evaluatie_bericht'] ?? '')) ?>">
      <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Opslaan</button>
    </form>
    <?php endif; ?>
  </li>
  <?php endforeach; ?>
</ul>
<p class="bh-meta">De vraag bij twijfel zien de beoordelaars bij die persoon. Bij sowieso aannemen of afwijzen wordt hij niet bewaard.</p>
<?php endif; ?>

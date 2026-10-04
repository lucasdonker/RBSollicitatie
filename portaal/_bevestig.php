<?php
/**
 * Partial: bevestigen vóór iets wat je niet (makkelijk) terugdraait — de portaalvariant van
 * admin/_bevestig.php + data-bevestig in beheer.js. Er staat in het portaal nergens een
 * confirm(); gebruik dit blok.
 *
 * Werkt zonder JavaScript: eerst uitklappen (<details>), dan pas de knop die post. Twee
 * bewuste handelingen, native toetsenbord- en schermlezerbediening, en te screenshotten.
 * Het formulier staat los, dus de knop hoort nooit per ongeluk bij een ander formulier.
 *
 * Verwacht $bev (array):
 *   knop_open  string  tekst op het uitklapkopje, bv. "Verwijderen"            (verplicht)
 *   knop       string  tekst op de verzendknop, bv. "Ja, verwijder dit document" (verplicht)
 *   velden     array   [naam => waarde] verborgen velden; csrf komt er vanzelf bij (verplicht)
 *   tekst      string|string[]  uitleg van wat er gebeurt (één alinea per element)
 *   titel      string  optionele kop in het uitgeklapte vlak
 *   icoon      string  icoon vóór het kopje (icoon()-naam), optioneel
 *   gevaar     bool    rood vlak + rode knop (onomkeerbaar, persoonsgegevens)
 *   open       bool    meteen uitgeklapt (bv. na een fout)
 *   id         string  id op het blok (anker, bv. "verwijderen")
 *   actie      string  form action; leeg = dezelfde URL
 *   typ        string  optioneel: dit woord moet worden overgetypt (veldnaam "bevestig")
 *   fout       string  optionele foutmelding in het blok (role=alert, gekoppeld aan het typveld)
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$bev = $bev ?? null;
if (!$bev || empty($bev['knop']) || empty($bev['knop_open'])) { $bev = null; return; }
$_b_id    = (string)($bev['id'] ?? '');
$_b_basis = $_b_id !== '' ? $_b_id : 'bev' . substr(md5(json_encode($bev['velden'] ?? [])), 0, 8);
$_b_tekst = (array)($bev['tekst'] ?? []);
$_b_fout  = (string)($bev['fout'] ?? '');
$_b_typ   = (string)($bev['typ'] ?? '');
?>
<details class="pt-bevestig<?= !empty($bev['gevaar']) ? ' gevaar' : '' ?>"<?= $_b_id !== '' ? ' id="' . h($_b_id) . '"' : '' ?><?= !empty($bev['open']) || $_b_fout !== '' ? ' open' : '' ?>>
  <summary><?= !empty($bev['icoon']) ? icoon((string)$bev['icoon'], 16) : '' ?><?= h((string)$bev['knop_open']) ?></summary>
  <div class="pt-bevestig-vlak">
    <?php if (!empty($bev['titel'])): ?><p class="pt-bevestig-titel"><?= h((string)$bev['titel']) ?></p><?php endif; ?>
    <?php foreach ($_b_tekst as $_b_p): ?><p><?= h((string)$_b_p) ?></p><?php endforeach; ?>
    <?php if ($_b_fout !== ''): ?>
      <div class="ds-melding ds-melding-error" id="<?= h($_b_basis) ?>-fout" role="alert"><?= icoon('let-op', 18) ?> <div><?= h($_b_fout) ?></div></div>
    <?php endif; ?>
    <form method="POST"<?= !empty($bev['actie']) ? ' action="' . h((string)$bev['actie']) . '"' : '' ?> class="pt-bevestig-form">
      <?= csrf_input() ?>
      <?php foreach ((array)($bev['velden'] ?? []) as $_b_n => $_b_v): ?>
        <input type="hidden" name="<?= h((string)$_b_n) ?>" value="<?= h((string)$_b_v) ?>">
      <?php endforeach; ?>
      <?php if ($_b_typ !== ''): ?>
        <label for="<?= h($_b_basis) ?>-typ" class="ds-label">Typ <strong><?= h($_b_typ) ?></strong> om te bevestigen</label>
        <input type="text" id="<?= h($_b_basis) ?>-typ" name="bevestig" class="ds-input pt-bevestig-typ" autocomplete="off"
               autocapitalize="characters" spellcheck="false" required
               <?= $_b_fout !== '' ? 'aria-invalid="true" aria-describedby="' . h($_b_basis) . '-fout"' : '' ?>>
      <?php endif; ?>
      <button type="submit" class="ds-btn <?= !empty($bev['gevaar']) ? 'ds-btn-gevaar' : 'ds-btn-secundair' ?>"><?= h((string)$bev['knop']) ?></button>
    </form>
  </div>
</details>
<?php
$bev = null;

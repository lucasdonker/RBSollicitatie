<?php
/**
 * Partial: één kaartje in de groepsindeling.
 *
 * Verwacht:
 *   $_kand        array  kandidaat uit selectie_kandidaten()
 *   $_conflict    bool   zit er iemand in dezelfde groep die uit elkaar moet?
 *   $mag_bewerken bool   (van het scherm)
 *   $groepen      array  selectie_groepen() (van het scherm) — voor de keuzelijst "Naar groep"
 *
 * De conflictmelding staat in tekst en niet alleen in kleur: wie kleurenblind is moet
 * hem ook zien (ontwerpfilosofie, toegankelijkheid).
 *
 * Verplaatsen kan op twee manieren, naar hetzelfde endpoint (actie 'verplaats'):
 * slepen met de muis, en de keuzelijst "Naar groep" met een knop. Die tweede is er voor
 * toetsenbord en touch (WCAG 2.1.1 en 2.5.7): slepen alleen sluit die mensen uit. Het is
 * een gewoon formulier, dus het werkt ook zonder JavaScript. Bewust geen onchange: met de
 * pijltjestoetsen door een keuzelijst lopen zou dan al verplaatsen (WCAG 3.2.2).
 */
$_kand     = $_kand ?? null;
$_conflict = $_conflict ?? false;
if (!$_kand) return;
$_naam  = (string)($_kand['naam'] ?? '');
$_sol   = (int)$_kand['sol_id'];
$_groep = (int)($_kand['groep_id'] ?? 0);
?>
<div class="bh-kandkaart<?= $_conflict ? ' botst' : '' ?>" id="kaart-<?= $_sol ?>" tabindex="-1"
     <?= !empty($mag_bewerken) ? 'draggable="true"' : '' ?>
     data-sol="<?= $_sol ?>">
  <?php if (!empty($mag_bewerken)): ?>
    <button type="button" class="bh-kandkaart-naam bh-kandkaart-relknop"
            title="Aangeven wie niet bij <?= h($_naam) ?> in de groep mag"
            onclick="relKies(<?= $_sol ?>, <?= htmlspecialchars(json_encode($_naam), ENT_QUOTES) ?>)"><?= h($_naam) ?></button>
  <?php else: ?>
    <span class="bh-kandkaart-naam"><?= h($_naam) ?></span>
  <?php endif; ?>
  <span class="bh-kandkaart-meta">
    <?= !empty($_kand['leeftijd']) ? (int)$_kand['leeftijd'] . 'j' : '' ?>
    <?= !empty($_kand['geslacht']) ? strtoupper(substr((string)$_kand['geslacht'], 0, 1)) : '' ?>
  </span>
  <?php if (!empty($_kand['is_lifeguard'])): ?>
    <?= bh_badge('Lifeguard', 'blauw', ['titel' => 'Terugkerende lifeguard: wordt over de groepen verspreid']) ?>
  <?php endif; ?>
  <?php if ($_conflict): ?>
    <span class="bh-kandkaart-botst" title="Zit bij iemand die uit elkaar moet">
      <?= icoon('waarschuwing', 13) ?>botst
    </span>
  <?php endif; ?>
  <?php if (!empty($mag_bewerken) && !empty($groepen)): ?>
    <form method="post" class="bh-kandkaart-naar">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="verplaats">
      <input type="hidden" name="terug" value="1">
      <input type="hidden" name="sol_id" value="<?= $_sol ?>">
      <label class="bh-vw" for="naar-<?= $_sol ?>">Naar groep, voor <?= h($_naam) ?></label>
      <select class="ds-input" id="naar-<?= $_sol ?>" name="groep_id">
        <option value=""<?= $_groep === 0 ? ' selected' : '' ?>>Nog niet ingedeeld</option>
        <?php foreach ($groepen as $_ng): ?>
          <option value="<?= (int)$_ng['id'] ?>"<?= $_groep === (int)$_ng['id'] ? ' selected' : '' ?>>
            <?= h((string)($_ng['naam'] ?: 'Groep ' . $_ng['nummer'])) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Verplaats</button>
    </form>
  <?php endif; ?>
</div>
<?php
$_conflict = false;

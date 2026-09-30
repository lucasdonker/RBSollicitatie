<?php
/**
 * Partial — één instellingenveld: label (met uitleg-icoon), invoer en hint, altijd in dezelfde
 * volgorde en uitlijning. Verwacht $iv: een veld uit includes/instellingen_velden.php.
 *
 * Een vinkje verstuurt eerst een verborgen "0", zodat uitvinken ook echt opslaat.
 * 'toon_als' => [sleutel, waarde]: het veld staat alleen open bij die keuze (data-toon-als, beheer.js);
 * zonder JavaScript staat het er gewoon.
 */
$_s    = (string)$iv['sleutel'];
$_id   = 'inst-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($_s));
$_naam = 'inst[' . $_s . ']';
$_w    = (string)instelling($_s, (string)($iv['standaard'] ?? ''));
$_type = (string)($iv['type'] ?? 'text');
$_hint = (string)($iv['hint'] ?? '');
$_ph   = $iv['placeholder'] ?? '';
if (is_callable($_ph)) $_ph = $_w === '' ? (string)$_ph() : '';
$_toon = $iv['toon_als'] ?? null;
$_attr = '';
foreach (['min', 'max', 'step'] as $_a) {
    if (!isset($iv[$_a]) || $_type === 'textarea') continue;
    $_attr .= ' ' . $_a . '="' . h((string)$iv[$_a]) . '"';
}
if ($_type === 'textarea' && isset($iv['max'])) $_attr .= ' maxlength="' . (int)$iv['max'] . '"';
if ($_hint !== '') $_attr .= ' aria-describedby="' . h($_id) . '-hint"';
?>
<div class="ds-veld bh-instveld<?= !empty($iv['breed']) || $_type === 'textarea' ? ' breed' : '' ?>"
     <?php if ($_toon): ?>data-toon-als="inst[<?= h($_toon[0]) ?>]=<?= h($_toon[1]) ?>"<?= instelling($_toon[0], '') !== $_toon[1] ? ' hidden' : '' ?><?php endif; ?>>
  <?php if ($_type === 'checkbox'): ?>
    <label class="bh-inst-vink" for="<?= h($_id) ?>">
      <input type="hidden" name="<?= h($_naam) ?>" value="0">
      <input type="checkbox" id="<?= h($_id) ?>" name="<?= h($_naam) ?>" value="1"<?= $_w === '1' ? ' checked' : '' ?><?= $_attr ?>>
      <span><?= h($iv['label']) ?></span>
    </label>
  <?php else: ?>
    <label for="<?= h($_id) ?>"><?= h($iv['label']) ?><?= !empty($iv['tip']) ? ' ' . bh_tip((string)$iv['tip']) : '' ?></label>
    <?php if ($_type === 'select'):
      $_opt = $iv['opties'] ?? [];
      if (is_callable($_opt)) $_opt = $_opt(); ?>
      <select id="<?= h($_id) ?>" name="<?= h($_naam) ?>"<?= $_attr ?>>
        <?php foreach ($_opt as $_k => $_l): ?>
          <option value="<?= h((string)$_k) ?>"<?= (string)$_k === $_w ? ' selected' : '' ?>><?= h((string)$_l) ?></option>
        <?php endforeach; ?>
      </select>
    <?php elseif ($_type === 'textarea'): ?>
      <textarea id="<?= h($_id) ?>" name="<?= h($_naam) ?>" rows="<?= (int)($iv['rijen'] ?? 3) ?>"<?= $_ph !== '' ? ' placeholder="' . h($_ph) . '"' : '' ?><?= $_attr ?>><?= h($_w) ?></textarea>
    <?php else: ?>
      <input type="<?= h($_type) ?>" id="<?= h($_id) ?>" name="<?= h($_naam) ?>" value="<?= h($_w) ?>"<?= $_ph !== '' ? ' placeholder="' . h($_ph) . '"' : '' ?><?= $_attr ?>>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($_hint !== ''): ?><div class="ds-veld-hint" id="<?= h($_id) ?>-hint"><?= h($_hint) ?></div><?php endif; ?>
</div>

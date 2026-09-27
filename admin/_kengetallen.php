<?php
/**
 * Partial: de kengetallen van het seizoen bovenaan Vandaag (aanmeldingen, laatste 30 dagen,
 * terugkerend, …). Een tegel met url is een link naar de gefilterde sollicitantenlijst.
 *
 * Verwacht:
 *   $kengetallen  array  uit bh_vandaag_kengetallen()
 */
$kengetallen = $kengetallen ?? [];
if ($kengetallen):
?>
<section class="bh-kengetallen" aria-label="Kengetallen van dit seizoen">
  <?php foreach ($kengetallen as $_k): $_tag = !empty($_k['url']) ? 'a' : 'div'; ?>
    <<?= $_tag ?> class="ds-kaart bh-kengetal"<?= $_tag === 'a' ? ' href="' . h($_k['url']) . '"' : '' ?>>
      <span class="bh-kengetal-waarde bh-num"><?= (int)$_k['waarde'] ?></span>
      <span class="bh-kengetal-label"><?= h($_k['label']) ?></span>
      <span class="bh-meta"><?= h($_k['sub']) ?></span>
    </<?= $_tag ?>>
  <?php endforeach; ?>
</section>
<?php
endif;
$kengetallen = null;

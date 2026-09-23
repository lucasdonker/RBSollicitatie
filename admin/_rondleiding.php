<?php
/**
 * Partial: de gegevens voor de rondleiding + het script. Staat onderaan elk beheerscherm
 * (layout_bottom.php) en doet niets als er op dit scherm niets te tonen valt.
 *
 * De stappen gaan als JSON in een <script type="application/json">: dat voert de browser
 * niet uit, en JSON_HEX_TAG maakt dat een tekst met "</script>" het blok niet kan breken.
 *
 * Verwacht:
 *   $_rl  ?array  rl_pagina_data() — berekend in layout_top.php
 */
if (!empty($_rl)):
?>
<script type="application/json" id="rl-data"><?= json_encode($_rl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= APP_URL ?>/assets/js/rondleiding.js?v=<?= @filemtime(__DIR__ . '/../assets/js/rondleiding.js') ?>"></script>
<?php
endif;

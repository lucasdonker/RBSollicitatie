<?php
/**
 * Publieke weergave van een landingspagina: /l.php?p=<slug>
 * (met URL-rewrite bij voorkeur als /l/<slug>).
 *
 * - Rendert de eigen HTML/CSS van de pagina.
 * - Voegt het echte aanmeldformulier in op de plek van de markering [[formulier]].
 * - Trackt het bezoek (anoniem) en geeft de bron mee aan het formulier, zodat de hele
 *   trechter herleidbaar is naar deze landingspagina.
 * - Respecteert de status: concept = onzichtbaar, alleen_admin = alleen voor ingelogde
 *   beheerders, gepubliceerd = voor iedereen.
 */
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['p'] ?? '';
if ($slug === '') { http_response_code(404); exit('Pagina niet gevonden.'); }

$pagina = function_exists('landing_via_slug') ? landing_via_slug($slug) : null;
if (!$pagina) { http_response_code(404); exit('Pagina niet gevonden.'); }

// Statuscontrole
$status = $pagina['status'] ?? 'concept';
$is_admin = false;
if (function_exists('is_ingelogd')) { sessie_start(); $is_admin = is_ingelogd(); }

if ($status === 'concept' && !$is_admin) { http_response_code(404); exit('Pagina niet gevonden.'); }
if ($status === 'alleen_admin' && !$is_admin) { http_response_code(404); exit('Pagina niet gevonden.'); }

// Bron-code: die van de pagina, of anders de slug.
$bron_code = $pagina['bron_code'] ?: $pagina['slug'];

// Bezoek tracken (niet als een admin aan het previewen is)
if (!($is_admin && $status !== 'gepubliceerd')) {
    if (function_exists('landing_track')) landing_track('bezoek', (int)$pagina['id'], $bron_code);
}

// Het aanmeldformulier dat op [[formulier]] wordt geplaatst.
// Het echte formulier staat op /aanmelden.php; we laden het als iframe zodat de landingspagina
// zelf volledig vrij te vormgeven is, met de bron als parameter (voor de attributie + tracking).
$seizoen = actief_seizoen();
$open = $seizoen && ($seizoen['status'] ?? '') === 'open';

ob_start();
if ($open) {
    $form_url = APP_URL . '/aanmelden.php?bron=' . urlencode($bron_code) . '&ingebed=1';
    ?>
    <div class="paal13-formulier-embed">
      <iframe src="<?= h($form_url) ?>" style="width:100%;border:none;min-height:640px" title="Aanmeldformulier"
              onload="try{window.addEventListener('message',function(e){if(e.data&&e.data.paal13Hoogte){this.style.height=e.data.paal13Hoogte+'px'}}.bind(this))}catch(e){}"></iframe>
    </div>
    <?php
} else {
    ?>
    <div class="paal13-gesloten-melding" style="padding:20px;text-align:center;background:#fff8e5;border:1px solid #dba617;border-radius:10px">
      <strong>De aanmelding is op dit moment gesloten.</strong>
      <p style="margin:8px 0 0;font-size:14px;color:#666">Houd onze kanalen in de gaten voor de volgende ronde.</p>
    </div>
    <?php
}
$formulier_html = ob_get_clean();

// De eigen HTML van de pagina; vervang de markering door het formulier.
$body_html = (string)$pagina['html'];
if (strpos($body_html, '[[formulier]]') !== false) {
    $body_html = str_replace('[[formulier]]', $formulier_html, $body_html);
} else {
    // Geen markering → formulier onderaan toevoegen zodat het altijd bereikbaar is.
    $body_html .= $formulier_html;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($pagina['titel']) ?></title>
<?php if ($status !== 'gepubliceerd'): ?>
<meta name="robots" content="noindex,nofollow">
<?php endif; ?>
<style>
<?= $pagina['css'] // eigen CSS van de pagina — bewust rauw, dit is een beheer-tool ?>
</style>
</head>
<body>
<?php if ($is_admin && $status !== 'gepubliceerd'): ?>
  <div style="position:fixed;top:0;left:0;right:0;background:#054d8a;color:#fff;text-align:center;padding:6px;font-size:13px;z-index:9999;font-family:sans-serif">
    👁 Voorbeeld — deze pagina is <strong><?= h($status === 'concept' ? 'concept' : 'alleen voor admins zichtbaar') ?></strong> en telt niet mee in de statistiek.
  </div>
  <div style="height:32px"></div>
<?php endif; ?>

<?= $body_html ?>

</body>
</html>

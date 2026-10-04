<?php
/**
 * Portaal — Contact (vierde tab van de navigatie). Hoe je ons bereikt: mailen, bellen, of een
 * vraag stellen die met je naam en fase bij ons binnenkomt. Stond eerst onderaan Berichten,
 * en daar vond niemand hem.
 *
 * Adres: portaal_contact_email() (Instellingen › Mail › Contact voor sollicitanten);
 * telefoon: instelling org.telefoon. Het vraagblok: portaal/_vraag.php.
 */
require_once __DIR__ . '/../includes/functions.php';
$sol_id = portaal_vereis_login();
$sol = portaal_sollicitant_laden($sol_id);
if (!$sol) { unset($_SESSION['portaal_sol_id']); redirect(APP_URL . '/portaal/'); }

$vr    = portaal_vraag_verwerk($sol);
$mail  = portaal_contact_email();
$tel   = function_exists('instelling') ? trim((string)instelling('org.telefoon', '')) : '';

$portaal_titel = 'Contact';
$portaal_nav   = 'contact';
require __DIR__ . '/_kop.php';
?>
<div class="pt-stapel">
  <div class="pt-paginatitel">
    <h1>Contact</h1>
    <span>We helpen je graag</span>
  </div>

  <div class="pt-kaart pt-contact">
    <h2 class="pt-kaart-titel"><?= icoon('mail', 18) ?> Mail of bel ons</h2>
    <p class="pt-kaart-tekst">Vraag, iets veranderd, of loopt er iets niet? Laat het ons weten.</p>
    <div class="pt-contact-knoppen">
      <a class="ds-btn ds-btn-primair" href="mailto:<?= h($mail) ?>"><?= icoon('mail', 18) ?> <?= h($mail) ?></a>
      <?php if ($tel !== ''): ?>
        <a class="ds-btn ds-btn-secundair" href="tel:<?= h(preg_replace('/[^0-9+]/', '', $tel)) ?>"><?= icoon('telefoon', 18) ?> <?= h($tel) ?></a>
      <?php endif; ?>
    </div>
  </div>

  <?php
    $vraag_sol = $sol; $vraag_flash = $vr['flash']; $vraag_fout = $vr['fout'];
    require __DIR__ . '/_vraag.php';
  ?>
</div>

<?php require __DIR__ . '/_voet.php'; ?>

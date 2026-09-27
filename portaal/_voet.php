<?php
/**
 * Gedeelde portaal-voet. Sluit de pagina met de navigatiebalk (één keer, hier gedefinieerd)
 * en het gedeelde script. De actieve tab komt uit $portaal_nav.
 *
 * De navigatie verschijnt alleen als de sollicitant is ingelogd én $portaal_geen_nav niet
 * gezet is (bijv. op het inlogscherm). Het telbolletje op "Taken" toont $portaal_open_taken
 * (aantal open taken) als de pagina dat heeft gezet en het groter is dan nul.
 */
require_once __DIR__ . '/../includes/functions.php';
portaal_alleen_include(__FILE__);
$portaal_geen_nav = $portaal_geen_nav ?? false;
$_nav = $portaal_nav ?? '';
$_toon_nav = !$portaal_geen_nav && function_exists('portaal_ingelogd') && portaal_ingelogd();
$_open = isset($portaal_open_taken) ? (int)$portaal_open_taken
       : (($_toon_nav && function_exists('portaal_taken_open_aantal')) ? portaal_taken_open_aantal((int)$_SESSION['portaal_sol_id']) : 0);

/* Eén adres voor als iets niet lukt, op élke pagina. Dat ontbrak: wie vastliep op het
   ondertekenen of het formulier had nergens iets om naartoe te gaan, behalve terug.
   portaal_contact_email() is de enige bron (mail.contact_email, leeg = mail.bestuur_email). */
$_contact_mail = function_exists('portaal_contact_email') ? portaal_contact_email() : '';
$_contact_tel  = function_exists('instelling') ? trim((string)instelling('org.telefoon', '')) : '';
?>
  </main>

<?php if ($_contact_mail !== '' || $_contact_tel !== ''): ?>
  <p class="pt-hulpvoet">
    Loopt iets niet?
    <?php if ($_contact_mail !== ''): ?>
      Mail <a href="mailto:<?= h($_contact_mail) ?>"><?= h($_contact_mail) ?></a><?= $_contact_tel !== '' ? ' of bel ' : '.' ?>
    <?php endif; ?>
    <?php if ($_contact_tel !== ''): ?>
      <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $_contact_tel)) ?>"><?= h($_contact_tel) ?></a>.
    <?php endif; ?>
    <?php if (function_exists('privacy_url')): ?>
      <a href="<?= h(privacy_url()) ?>">Privacyverklaring</a>
    <?php endif; ?>
  </p>
<?php endif; ?>

<?php if ($_toon_nav): ?>
  <nav class="pt-nav" aria-label="Hoofdnavigatie">
    <div class="pt-nav-merk">
      <span class="pt-merk-logo"><?= icoon('golf', 18) ?></span>
      <div class="pt-merk-tekst">
        <span class="pt-merk-naam">Paal 13</span>
        <span class="pt-merk-sub"><?php $sz = function_exists('actief_seizoen') ? actief_seizoen() : null; echo $sz ? h($sz['naam']) : 'Sollicitatie 2026'; ?></span>
      </div>
    </div>
    <div class="pt-nav-items">
      <?php /* aria-current="page": de actieve tab alleen aan kleur herkennen gaat niet voor een
               schermlezer (WCAG 1.3.1/4.1.2). $_akt() geeft klasse + attribuut in één keer. */
        $_akt = fn(string $n): string => $_nav === $n ? 'class="pt-nav-item actief" aria-current="page"' : 'class="pt-nav-item"'; ?>
      <a href="<?= APP_URL ?>/portaal/" <?= $_akt('start') ?>>
        <?= icoon('huis', 22) ?><span>Start</span>
      </a>
      <a href="<?= APP_URL ?>/portaal/taken.php" <?= $_akt('taken') ?>>
        <span class="pt-nav-icoon-wrap"><?= icoon('lijst', 22) ?><?php if ($_open > 0): ?><span class="pt-nav-badge"><?= $_open ?><span class="sr-only"> open</span></span><?php endif; ?></span>
        <span>Taken</span>
      </a>
      <a href="<?= APP_URL ?>/portaal/gegevens.php" <?= $_akt('gegevens') ?>>
        <?= icoon('persoon', 22) ?><span>Gegevens</span>
      </a>
      <?php /* Contact als eigen tab: vragen stellen stond onderaan Berichten en werd niet gevonden. */ ?>
      <a href="<?= APP_URL ?>/portaal/contact.php" <?= $_akt('contact') ?>>
        <?= icoon('chat', 22) ?><span>Contact</span>
      </a>
    </div>
    <?php // Gebruikersblok onderin de desktop-zijbalk (ontwerp): initialen, naam, stap, uitloggen
      $_gs = function_exists('portaal_sollicitant_laden') ? portaal_sollicitant_laden((int)$_SESSION['portaal_sol_id']) : null;
      if ($_gs): $_gv = portaal_voortgang($_gs['fase'] ?? ''); ?>
    <div class="pt-nav-gebruiker">
      <span class="pt-nav-initialen"><?= h(portaal_initialen($_gs)) ?></span>
      <div class="pt-nav-gebruiker-tekst">
        <span class="pt-nav-gebruiker-naam"><?= h(portaal_naam($_gs)) ?></span>
        <span class="pt-nav-gebruiker-sub"><?= $_gv ? 'Stap ' . (int)$_gv['stap'] . ' van ' . (int)$_gv['totaal'] : h(portaal_fase_label((string)($_gs['fase'] ?? ''))) ?></span>
      </div>
      <a href="<?= APP_URL ?>/portaal/?uitloggen=1" class="pt-nav-uitloggen" aria-label="Uitloggen"><?= icoon('uitloggen', 17) ?></a>
    </div>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<?php /* actie.js hangt aan document en ziet elke submit ná de eigen controle van het
         formulier; de volgorde van de scripts maakt daarvoor niet uit. */ ?>
<script src="<?= APP_URL ?>/assets/js/actie.js?v=<?= @filemtime(__DIR__.'/../assets/js/actie.js') ?>"></script>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__.'/../assets/js/app.js') ?>"></script>
</body>
</html>

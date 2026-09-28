<?php
/**
 * Portaal — "Stuur me een seintje": /portaal/seintje.php?t=<afmeld-token>
 *
 * De link komt uit de afwijzingsmail en werkt zonder in te loggen: het token is het geheim.
 * Toestemming geven en intrekken gebeurt alleen met een POST (zie includes/seintje.php:
 * een mailscanner die de link vooraf opent, mag niet namens iemand "ja" zeggen).
 */
require_once __DIR__ . '/../includes/functions.php';

$token   = (string)($_POST['t'] ?? $_GET['t'] ?? '');
$persoon = function_exists('seintje_persoon_via_token') ? seintje_persoon_via_token($token) : null;
$klaar   = (string)($_GET['klaar'] ?? '');

if ($persoon && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (function_exists('rate_limit') && !rate_limit('seintje', rate_identiteit(), 20, 3600)) {
        http_response_code(429);
        exit('Te veel pogingen. Probeer het over een uur opnieuw.');
    }
    $aan = ($_POST['actie'] ?? '') === 'aan';
    $aan ? seintje_geven((int)$persoon['id'], 'mail') : seintje_intrekken((int)$persoon['id'], 'mail');
    redirect(APP_URL . '/portaal/seintje.php?t=' . rawurlencode($token) . '&klaar=' . ($aan ? 'aan' : 'uit'));
}

$heeft = $persoon && (string)($persoon['seintje_op'] ?? '') !== '';

$portaal_titel       = 'Seintje bij nieuwe werving';
$portaal_geen_nav    = true;
$portaal_geen_topbar = true;
require __DIR__ . '/_kop.php';
?>

  <div class="ds-login">
    <div class="ds-login-kop">
      <div class="ds-login-logo"><?= icoon('golf', 26) ?></div>
      <?php if (!$persoon): ?>
        <h1>Link niet herkend</h1>
        <?php $_contact = function_exists('portaal_contact_email') ? portaal_contact_email() : 'sollicitatie@paal13.com'; ?>
        <p>Deze link is ongeldig of je gegevens zijn al verwijderd. Wil je een seintje? Mail ons gerust op
           <a href="mailto:<?= h($_contact) ?>"><?= h($_contact) ?></a>.</p>
      <?php elseif ($heeft): ?>
        <h1><?= $klaar === 'aan' ? 'Top, we laten van ons horen' : 'Je krijgt een seintje' ?></h1>
        <p>Zodra de werving voor een nieuw seizoen opent, sturen we je een bericht. We bewaren je naam
           en contactgegevens daarvoor maximaal <?= h(seintje_termijn_tekst()) ?>.</p>
      <?php else: ?>
        <h1><?= $klaar === 'uit' ? 'Geen seintje' : 'Volgend jaar weer?' ?></h1>
        <p><?= $klaar === 'uit'
            ? 'We sturen je geen bericht bij een nieuwe werving. Je gegevens verwijderen we binnen ' . seintje_afwijs_termijn_tekst() . ' na de afwijzing.'
            : 'Wil je een bericht als de werving voor een nieuw seizoen opent? Dan bewaren we je naam en contactgegevens daarvoor maximaal ' . seintje_termijn_tekst() . '.' ?></p>
      <?php endif; ?>
    </div>

    <?php if ($persoon): ?>
    <form method="POST" class="ds-login-form">
      <?= csrf_input() ?>
      <input type="hidden" name="t" value="<?= h($token) ?>">
      <input type="hidden" name="actie" value="<?= $heeft ? 'uit' : 'aan' ?>">
      <button type="submit" class="ds-btn <?= $heeft ? 'ds-btn-secundair' : 'ds-btn-primair' ?> ds-btn-vol">
        <?= $heeft ? 'Toch geen seintje' : 'Stuur me een seintje' ?>
      </button>
    </form>
    <div class="ds-login-voet">
      Je kunt dit altijd weer veranderen via deze link.
    </div>
    <?php endif; ?>
  </div>

<?php require __DIR__ . '/_voet.php'; ?>

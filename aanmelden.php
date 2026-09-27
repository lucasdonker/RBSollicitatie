<?php
/**
 * Het eigen aanmeldformulier: https://sollicitatie.paal13.com/aanmelden.php
 *
 * Dit is de terugvaloptie naast een landingspagina en de WordPress-plugin. Het formulier
 * zelf komt uit includes/aanmeldformulier.php — hier staat alleen wat op deze pagina anders
 * is: een echte sessie (dus csrf_check) en onze eigen omlijsting eromheen.
 *
 * Met ?ingebed=1 vallen de kop en de voettekst weg, zodat de pagina in een iframe op
 * paal13.com niet twee keer een logo laat zien.
 */
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/aanmelden_kern.php';
// Dit formulier mag in een iframe op paal13.com staan (zie README); de andere pagina's niet.
security_headers(site_toegestane_origins());

$seizoen = actief_seizoen();
$open    = $seizoen && $seizoen['status'] === 'open';
$fout    = '';
$code    = '';
$succes  = isset($_GET['ok']);
$ingebed = !empty($_GET['ingebed']);

// Bron-attributie: waar komt deze bezoeker vandaan? (landingspagina/advertentie)
// Komt binnen via ?bron=... of in de POST, zodat hij door de hele flow meereist.
$bron_code = substr(trim((string)($_POST['bron_code'] ?? $_GET['bron'] ?? '')), 0, 80);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $open) {
    csrf_check();
    // Zelfde bescherming als het formulier op landingspagina's (includes/paginas/formulier.php):
    // een verborgen veld dat alleen bots invullen, en maximaal 5 aanmeldingen per uur per adres.
    if (trim((string)($_POST['p13_w'] ?? '')) !== '') {
        $fout = 'Het formulier kon niet worden verwerkt. Ververs de pagina en probeer het opnieuw.';
    } elseif (function_exists('rate_limit') && !rate_limit('aanmelding', rate_identiteit(), 5, 3600)) {
        $fout = 'Er zijn in korte tijd te veel aanmeldingen vanaf dit adres gedaan. Probeer het over een uur opnieuw.';
    }

    if ($fout === '') {
        // Bron meegeven aan de gedeelde bron van waarheid.
        $invoer = $_POST;
        if ($bron_code !== '') {
            $invoer['bron_code'] = $bron_code;
            if (function_exists('pag_via_bron')) {
                $lp = pag_via_bron($bron_code);
                if ($lp) $invoer['bron_label'] = $lp['titel'] ?? $bron_code;
            }
        }
        $resultaat = sollicitatie_aanmaken($invoer, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'via' => 'website_formulier']);
        if ($resultaat['ok']) {
            // Doorsturen na succes, anders levert vernieuwen een tweede aanmelding op.
            $q = ['ok' => 1];
            if ($bron_code !== '') $q['bron'] = $bron_code;
            if ($ingebed) $q['ingebed'] = 1;
            header('Location: ' . APP_URL . '/aanmelden.php?' . http_build_query($q), true, 303);
            exit;
        }
        $fout = $resultaat['fout'];
        $code = $resultaat['code'] ?? '';
    }
}

// Op onze eigen pagina schuift de kaart over de kopbalk heen; in een iframe niet.
$opmaak = ['extra_class' => $ingebed ? '' : 'af-zelfstandig'];

$formulier = !$open
    ? aanmeld_gesloten_html($opmaak)
    : ($succes
        ? aanmeld_succes_html($opmaak)
        : aanmeld_formulier_html($opmaak + [
            'bron_code' => $bron_code,
            'waarden'   => $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [],
            'fout'      => $fout,
            'code'      => $code,
            'csrf'      => true,
            'prefix'    => 'af',
          ]));

$v = static fn(string $p): string => APP_URL . $p . '?v=' . (@filemtime(__DIR__ . $p) ?: '1');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,follow">
<title>Aanmelden als lifeguard — Reddingsbrigade Paal 13</title>
<meta name="description" content="Meld je aan voor de selectieprocedure van Reddingsbrigade Paal 13 in Callantsoog en Groote Keeten.">
<link rel="stylesheet" href="<?= h($v('/assets/css/aanmeldformulier.css')) ?>">
</head>
<body class="af-pagina">
<?php if (!$ingebed): ?>
<header class="af-hero">
  <img src="<?=APP_URL?>/assets/img/logo.png" alt="Reddingsbrigade Paal 13" width="120" height="46">
  <h1>Word lifeguard op het strand</h1>
  <p>Reddingsbrigade Callantsoog &amp; Groote Keeten</p>
</header>
<?php endif; ?>

<main>
  <?= $formulier ?>
</main>

<?php if (!$ingebed): ?>
<?php // Eén adres voor sollicitanten (mail.contact_email, leeg = bestuursadres): portaal_contact_email(). ?>
<?php $contact = portaal_contact_email(); ?>
<footer class="af-voet">
  Vragen over je aanmelding? Mail <a href="mailto:<?= h($contact) ?>"><?= h($contact) ?></a>.
</footer>
<?php endif; ?>

<script src="<?= h($v('/assets/js/aanmeldformulier.js')) ?>" defer></script>
</body>
</html>

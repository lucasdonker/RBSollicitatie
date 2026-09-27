<?php
/**
 * Het eigen aanmeldformulier: https://sollicitatie.paal13.com/aanmelden.php
 *
 * Dit is de terugvaloptie naast een landingspagina en de WordPress-plugin. Het formulier
 * zelf komt uit includes/aanmeldformulier.php — hier staat alleen wat op deze pagina anders
 * is: een echte sessie (dus csrf_check) en onze eigen omlijsting eromheen.
 *
 * Staat de aanmelding dicht, dan staat hier het seintje-formulier (aanmeld_gesloten_html);
 * dat post naar deze pagina, met csrf, en wordt verwerkt in includes/seintje_werving.php.
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

// ── Aanmelding dicht: het seintje ────────────────────────────────────────
$seintje = ['seintje_zelf' => true];
$seintje_ok = isset($_GET['seintje']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$open && isset($_POST['p13_seintje']) && function_exists('seintje_werving_aanvragen')) {
    // csrf zoals overal op deze pagina. Een verlopen sessie geeft hier geen kale 403 (zoals
    // csrf_check), maar het formulier opnieuw met een melding: de invoer blijft staan.
    csrf_token();
    $csrf_ok = hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['_csrf'] ?? ''));
    $res = $csrf_ok
        ? seintje_werving_aanvragen($_POST, ['vertrouwd' => true])
        : ['ok' => false, 'code' => 'token', 'fout' => 'Controleer je gegevens en verstuur het formulier nog een keer.'];
    if ($res['ok']) {
        $q = ['seintje' => 1];
        if ($ingebed) $q['ingebed'] = 1;
        header('Location: ' . APP_URL . '/aanmelden.php?' . http_build_query($q) . '#aanmelden', true, 303);
        exit;
    }
    http_response_code(422);
    $seintje += ['seintje_waarden' => $_POST, 'seintje_fout' => $res['fout'], 'seintje_code' => $res['code']];
}

// Op onze eigen pagina schuift de kaart over de kopbalk heen; in een iframe niet.
$opmaak = ['extra_class' => $ingebed ? '' : 'af-zelfstandig', 'bron_code' => $bron_code];

if (!$open) {
    $formulier = $seintje_ok ? aanmeld_seintje_succes_html($opmaak) : aanmeld_gesloten_html($opmaak + $seintje);
} elseif ($succes) {
    $formulier = aanmeld_succes_html($opmaak);
} else {
    $formulier = aanmeld_formulier_html($opmaak + [
        'waarden'   => $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [],
        'fout'      => $fout,
        'code'      => $code,
        'csrf'      => true,
        'prefix'    => 'af',
    ]);
}

echo aanmeld_pagina_html($formulier, [
    'titel'        => 'Aanmelden als lifeguard — Reddingsbrigade Paal 13',
    'beschrijving' => 'Meld je aan voor de selectieprocedure van Reddingsbrigade Paal 13 in Callantsoog en Groote Keeten.',
    'ingebed'      => $ingebed,
]);

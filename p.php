<?php
/**
 * Publieke weergave van een landingspagina: /p/<slug>  (of /p.php?s=<slug> zonder rewrite).
 *
 * Belangrijk: deze pagina start géén sessie voor gewone bezoekers. Er wordt dus geen cookie
 * gezet — dat scheelt een cookiemelding, maakt de pagina cachebaar, en zorgt dat script uit
 * een geüpload ontwerp niets heeft om mee te spelen. Alleen als er al een beheerderscookie
 * meekomt, kijken we of het een beheerder is (voor de voorbeeldweergave).
 */
require_once __DIR__ . '/includes/functions.php';
if (!module_aan('paginas')) pagina_404();   // module uitgezet: de pagina's bestaan niet

$slug = pag_slug_maak((string)($_GET['s'] ?? $_GET['p'] ?? ''));
if ($slug === '') pagina_404();

$pagina = pag_via_slug($slug);
if (!$pagina) pagina_404();

// Beheerder? Alleen nagaan als er al een sessiecookie is — anders starten we er ongevraagd een.
$is_beheer = false;
$cookie_naam = defined('SESSION_NAME') ? SESSION_NAME : 'p13sess';
if (isset($_COOKIE[$cookie_naam]) && function_exists('is_ingelogd')) {
    $is_beheer = is_ingelogd() && mag('paginas', 'lezen');
}

// Zichtbaarheid
$status = (string)($pagina['status'] ?? 'concept');
if ($status !== 'gepubliceerd' && !$is_beheer) pagina_404();

// Welke versie tonen we? Beheerders mogen een specifieke versie bekijken (ook een onbevestigde).
$versie = null;
$preview_id = (int)($_GET['preview'] ?? 0);
if ($preview_id > 0 && $is_beheer) {
    $versie = pag_versie_rij($preview_id);
    if ($versie && (int)$versie['pagina_id'] !== (int)$pagina['id']) $versie = null;
}
if (!$versie && !empty($pagina['actieve_versie_id'])) {
    $versie = pag_versie_rij((int)$pagina['actieve_versie_id']);
}

$ontwerp = $versie ? pag_versie_inhoud($pagina, $versie) : null;
if ($ontwerp === null) {
    if (!$is_beheer) pagina_404();
    $ontwerp = '<h1>' . h((string)$pagina['titel']) . '</h1>'
             . '<p>Er staat nog geen ontwerp klaar voor deze pagina. Upload een HTML-bestand in het beheer.</p>';
}

$is_voorbeeld = $is_beheer && ($status !== 'gepubliceerd' || ($versie && $versie['scan_bevestigd_op'] === null));

// ── Formulier ────────────────────────────────────────────────────────────
$staat = [];
$gelukt = isset($_GET['ok']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['p13_form'])) {
    $res = pag_formulier_verwerk($pagina, $_POST);
    if ($res['ok']) {
        // Na succes doorsturen, zodat vernieuwen geen tweede aanmelding oplevert.
        header('Location: ' . pag_publieke_url($pagina) . '?ok=1#aanmelden', true, 303);
        exit;
    }
    http_response_code(422);
    $staat = ['fout' => $res['fout'], 'code' => $res['code'], 'waarden' => $_POST];
}

$formulier = $gelukt ? pag_succes_html() : pag_formulier_html($pagina, $staat);

// ── Statistiek ───────────────────────────────────────────────────────────
// Alleen echte bezoeken van een gepubliceerde pagina; een voorbeeld van een beheerder niet.
if ($status === 'gepubliceerd' && !$is_voorbeeld && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && !$gelukt) {
    pag_track('bezoek', (int)$pagina['id'], (string)($pagina['bron_code'] ?: $pagina['slug']));
}

// ── Uitsturen ────────────────────────────────────────────────────────────
pag_headers_zet($pagina, ['geen_cache' => $is_voorbeeld || $staat !== [] || $gelukt]);

echo pag_render($pagina, $ontwerp, [
    'formulier' => $formulier,
    'is_beheer' => $is_voorbeeld,
    'versie'    => $versie,
]);

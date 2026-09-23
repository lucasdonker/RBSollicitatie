<?php
/**
 * Gegevens van een sollicitant corrigeren (AVG art. 16: recht op rectificatie).
 *
 * Een gewone formulier-POST vanuit de kaart Gegevens corrigeren in het dossier
 * (admin/dossier/_gegevens.php): werkt zonder JavaScript en stuurt terug naar het dossier
 * met een melding. Validatie, uniek e-mailadres, de waarschuwingen rond de 16- en
 * 18-grens en het logboek staan in bh_gegevens_corrigeren() — hier alleen de toegang.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
vereis_recht('sollicitanten', 'bewerken');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Methode niet toegestaan'); }
csrf_check();

$sol_id = (int)($_POST['sol_id'] ?? 0);
$filter = bh_soll_filter_params(is_array($_POST['f'] ?? null) ? $_POST['f'] : []);
$terug  = fn(bool $open) => APP_URL . '/admin/sollicitant.php?' . http_build_query(
              array_merge($filter, ['id' => $sol_id], $open ? ['corrigeer' => 1] : [])) . '#gegevens';

if (!$sol_id) redirect(APP_URL . '/admin/sollicitanten.php');

// Geen massale wijzigingen via een script: ruim genoeg voor een correctieronde.
if (function_exists('rate_limit') && !rate_limit('gegevens_corrigeren', (string)($_SESSION['bhd_id'] ?? rate_identiteit()), 60, 3600)) {
    flash('fout', 'Te veel correcties achter elkaar. Probeer het over een uur opnieuw.');
    redirect($terug(true));
}

$velden = [];
foreach (array_keys(bh_gegevens_velden()) as $_k) $velden[$_k] = (string)($_POST[$_k] ?? '');
$r = bh_gegevens_corrigeren($sol_id, $velden);

if (!$r['ok']) {
    flash('fout', (string)$r['fout']);
    redirect($terug(true));
}
if (!$r['gewijzigd']) {
    flash('info', 'Er was niets veranderd.');
    redirect($terug(false));
}

$labels = bh_gegevens_velden();
$tekst  = 'Opgeslagen: ' . implode(', ', array_map(fn($k) => mb_strtolower($labels[$k]), $r['gewijzigd'])) . '.';
if ($r['waarschuwingen']) {
    flash('waarschuwing', $tekst . ' ' . implode(' ', $r['waarschuwingen']));
} else {
    flash('succes', $tekst);
}
redirect($terug(false));

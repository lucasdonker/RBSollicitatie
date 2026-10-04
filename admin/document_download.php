<?php
/**
 * Beveiligde download van een kluis-document (beheer).
 *   ?id=<document>    één bestand uit sollicitant_documenten (ID-kopie, bankpas, verzegelde PDF)
 *   ?ond=<ondertekening>  de verzegelde PDF van een ondertekening (via pdf_document_id; oudere records on-the-fly)
 *   &t=<csrf-token>   verplicht (de link staat alleen in het beheer)
 * Recht per soort (document_soorten()[soort]['recht'], actie bewerken). Alles gelogd (audit + rij), no-store,
 * limiet 30 downloads per uur per sessie, watermerk op de bankpasfoto (niet op het ID: die kopie moet
 * volledig leesbaar zijn in de loonadministratie), optioneel melding aan het bestuur.
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_login();
$bhd = huidige_beheerder();
if (!hash_equals(csrf_token(), (string)($_GET['t'] ?? ''))) { http_response_code(403); exit('Ongeldige link. Open de download vanuit het beheer.'); }

// Limiet per sessie
$uur = date('YmdH');
$_SESSION['doc_dl'] = array_intersect_key($_SESSION['doc_dl'] ?? [], [$uur => 1]);
if (($_SESSION['doc_dl'][$uur] ?? 0) >= 30) { http_response_code(429); exit('Te veel downloads in een uur. Probeer het later opnieuw.'); }

$rij = null; $ond = null;
if (!empty($_GET['ond'])) {
    $q = db()->prepare("SELECT o.*, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum, p.email FROM ondertekeningen o JOIN sollicitaties s ON s.id=o.sollicitatie_id JOIN personen p ON p.id=s.persoon_id WHERE o.id=?");
    $q->execute([(int)$_GET['ond']]);
    $ond = $q->fetch() ?: null;
    if (!$ond || empty($ond['ondertekend_op'])) { http_response_code(404); exit('Ondertekening niet gevonden.'); }
    $soort = document_soort_ondertekening((string)$ond['type']);
    if (!empty($ond['pdf_document_id'])) $rij = document_rij((int)$ond['pdf_document_id']);
} else {
    $rij = document_rij((int)($_GET['id'] ?? 0));
    if (!$rij) { http_response_code(404); exit('Document niet gevonden.'); }
    $soort = (string)$rij['soort'];
}
vereis_recht(document_recht_module($soort), 'bewerken');
// Loondossier-inhoud (ID, bankpas, BSN, IBAN): niet vanzelf voor de admin, alleen voor de penningmeester.
if (document_recht_module($soort) === 'documenten' && !mag_kluis()) {
    if (function_exists('audit_log')) audit_log('document_geweigerd', 'document', $rij ? (int)$rij['id'] : null,
        ['soort' => $soort, 'ondertekening_id' => $ond ? (int)$ond['id'] : null]);
    http_response_code(403);
    exit('Alleen de penningmeester kan de inhoud van een loondossier openen. Als beheerder zie je wel of de stukken er zijn.');
}

// Inhoud ophalen (kluis) — of, voor oudere ondertekeningen zonder opgeslagen PDF, on-the-fly genereren
$inhoud = null; $naam = ''; $mime = 'application/octet-stream';
if ($rij) {
    $inhoud = document_inhoud($rij);
    $naam = (string)$rij['bestandsnaam']; $mime = (string)$rij['mime'];
} elseif ($ond && $ond['type'] === 'loonheffing' && empty($ond['vertrouwelijk'])) {
    // Loondossier vernietigd (bewaarplicht verstreken): niet opnieuw opbouwen uit wat er nog van over is.
    http_response_code(410); exit('Dit loonheffingsformulier is vernietigd na afloop van de bewaarplicht.');
} elseif ($ond && function_exists('ondertekening_pdf_bytes')) {
    $inhoud = ondertekening_pdf_bytes($ond, ['voornaam' => $ond['voornaam'], 'tussenvoegsel' => $ond['tussenvoegsel'], 'achternaam' => $ond['achternaam'], 'geboortedatum' => $ond['geboortedatum'], 'email' => $ond['email']]);
    $naam = ondertekening_pdf_bestandsnaam((string)$ond['type']); $mime = 'application/pdf';
}
if ($inhoud === null) { http_response_code(500); exit('Het document kon niet ontsleuteld of gemaakt worden. Controleer de kluis (Systeem › Health).'); }

// Watermerk alleen op de bankpas; de kopie van het ID moet volledig leesbaar blijven (document_watermerk_soorten()).
$inhoud = document_watermerk($inhoud, $soort, $mime, (string)($bhd['naam'] ?? ''));

// Loggen
$_SESSION['doc_dl'][$uur] = ($_SESSION['doc_dl'][$uur] ?? 0) + 1;
if (function_exists('audit_log')) audit_log('document_gedownload', 'document', $rij ? (int)$rij['id'] : null, ['soort' => $soort, 'sollicitatie_id' => (int)($rij['sollicitatie_id'] ?? $ond['sollicitatie_id'] ?? 0), 'ondertekening_id' => $ond ? (int)$ond['id'] : null]);
if ($rij) { try { db()->prepare("UPDATE sollicitant_documenten SET laatst_gedownload_op=NOW(), laatst_gedownload_door=? WHERE id=?")->execute([(int)$bhd['id'], (int)$rij['id']]); } catch (\Throwable $e) {} }
if ($ond && function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis((int)$ond['id'], 'pdf_gedownload', ['door' => 'beheer:' . ($bhd['naam'] ?: $bhd['id'])]);
$soort_titel = (string)(document_soorten()[$soort]['titel'] ?? $soort); // leesbaar, niet de interne slug
if (function_exists('instelling') && instelling('documenten.download_melding', '1') === '1' && function_exists('melding_verstuur') && in_array($soort, ['id_voor', 'id_achter', 'bankpas', 'ondertekening_loonheffing'], true)) {
    try {
        melding_verstuur('handmatig', (int)($rij['sollicitatie_id'] ?? $ond['sollicitatie_id'] ?? 0),
            ['onderwerp' => 'Loondocument gedownload: ' . $soort_titel, 'body_html' => '<p>' . h($bhd['naam'] ?: 'Beheerder #' . $bhd['id']) . ' heeft op ' . date('d-m-Y H:i') . ' het document <strong>' . h($soort_titel) . '</strong> (' . h($naam) . ') gedownload uit het loondossier.</p><p>Niet verwacht? Controleer het auditlog.</p>', 'tekst_kort' => ''],
            ['ontvanger' => ['email' => bestuur_email(), 'naam' => 'Bestuur', 'telefoon' => '', 'rol' => 'direct'], 'prioriteit' => 'na_antwoord', 'dedup_min' => 0]);
    } catch (\Throwable $e) {}
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $naam ?: 'document') . '"');
header('Content-Length: ' . strlen($inhoud));
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
echo $inhoud;
if (function_exists('kluis_wis')) kluis_wis($inhoud);
exit;

<?php
/**
 * Beveiligde download van een kluis-document (beheer).
 *   ?id=<document>    één bestand uit sollicitant_documenten (ID-kopie, bankpas, pasfoto, verzegelde PDF)
 *   ?ond=<ondertekening>  de verzegelde PDF van een ondertekening (via pdf_document_id; oudere records on-the-fly)
 *   &t=<csrf-token>   verplicht (de link staat alleen in het beheer)
 * Recht per soort (document_soorten()[soort]['recht'], actie bewerken). Alles gelogd (audit + rij), no-store,
 * limiet 30 downloads per uur per sessie, watermerk op ID-/bankpasfoto's, optioneel melding aan het bestuur.
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

// Inhoud ophalen (kluis) — of, voor oudere ondertekeningen zonder opgeslagen PDF, on-the-fly genereren
$inhoud = null; $naam = ''; $mime = 'application/octet-stream';
if ($rij) {
    $inhoud = document_inhoud($rij);
    $naam = (string)$rij['bestandsnaam']; $mime = (string)$rij['mime'];
} elseif ($ond && function_exists('ondertekening_pdf_bytes')) {
    $inhoud = ondertekening_pdf_bytes($ond, ['voornaam' => $ond['voornaam'], 'tussenvoegsel' => $ond['tussenvoegsel'], 'achternaam' => $ond['achternaam'], 'geboortedatum' => $ond['geboortedatum'], 'email' => $ond['email']]);
    $naam = ondertekening_pdf_bestandsnaam((string)$ond['type']); $mime = 'application/pdf';
}
if ($inhoud === null) { http_response_code(500); exit('Het document kon niet ontsleuteld of gemaakt worden. Controleer de kluis (Systeem › Health).'); }

// Watermerk op foto's van ID/bankpas (alleen op de uitgaande kopie; het origineel blijft ongewijzigd in de kluis)
$watermerk = in_array($soort, ['id_voor', 'id_achter', 'bankpas'], true) && in_array($mime, ['image/jpeg', 'image/png'], true) && function_exists('imagecreatefromstring');
if ($watermerk) {
    try {
        $im = @imagecreatefromstring($inhoud);
        if ($im) {
            $w = imagesx($im); $h = imagesy($im);
            if ($w > 2000) { $nw = 2000; $nh = (int)round($h * 2000 / $w); $im2 = imagescale($im, $nw, $nh); if ($im2) { imagedestroy($im); $im = $im2; $w = $nw; $h = $nh; } }
            $tekst = 'Kopie voor loonadministratie Reddingsbrigade Paal 13 - ' . date('d-m-Y H:i') . ' - ' . ($bhd['naam'] ?: 'beheer');
            $kleur = imagecolorallocatealpha($im, 200, 30, 30, 60);
            $font = 5; $stap = (int)max(120, $h / 6);
            for ($y = 20; $y < $h; $y += $stap) {
                imagestring($im, $font, 12, $y, $tekst, $kleur);
                imagestring($im, $font, (int)($w / 2), $y + (int)($stap / 2), $tekst, $kleur);
            }
            ob_start();
            if ($mime === 'image/png') imagepng($im, null, 6); else imagejpeg($im, null, 90);
            $uit = ob_get_clean(); imagedestroy($im);
            if ($uit !== false && $uit !== '') $inhoud = $uit;
        }
    } catch (\Throwable $e) { error_log('watermerk: ' . $e->getMessage()); }
}

// Loggen
$_SESSION['doc_dl'][$uur] = ($_SESSION['doc_dl'][$uur] ?? 0) + 1;
if (function_exists('audit_log')) audit_log('document_gedownload', 'document', $rij ? (int)$rij['id'] : null, ['soort' => $soort, 'sollicitatie_id' => (int)($rij['sollicitatie_id'] ?? $ond['sollicitatie_id'] ?? 0), 'ondertekening_id' => $ond ? (int)$ond['id'] : null]);
if ($rij) { try { db()->prepare("UPDATE sollicitant_documenten SET laatst_gedownload_op=NOW(), laatst_gedownload_door=? WHERE id=?")->execute([(int)$bhd['id'], (int)$rij['id']]); } catch (\Throwable $e) {} }
if ($ond && function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis((int)$ond['id'], 'pdf_gedownload', ['door' => 'beheer:' . ($bhd['naam'] ?: $bhd['id'])]);
if (function_exists('instelling') && instelling('documenten.download_melding', '1') === '1' && function_exists('melding_verstuur') && in_array($soort, ['id_voor', 'id_achter', 'bankpas', 'ondertekening_loonheffing'], true)) {
    try {
        melding_verstuur('handmatig', (int)($rij['sollicitatie_id'] ?? $ond['sollicitatie_id'] ?? 0),
            ['onderwerp' => 'Loondocument gedownload: ' . $soort, 'body_html' => '<p>' . h($bhd['naam'] ?: 'Beheerder #' . $bhd['id']) . ' heeft op ' . date('d-m-Y H:i') . ' het document <strong>' . h($soort) . '</strong> (' . h($naam) . ') gedownload uit het loondossier.</p><p>Niet verwacht? Controleer het auditlog.</p>', 'tekst_kort' => ''],
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

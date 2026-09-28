<?php
/**
 * Paal 13 — Loonheffingsformulier: het officiële Belastingdienst-PDF "Model Opgaaf gegevens voor de
 * loonheffingen" (LH 008-2Z*12FOL) exact overnemen en invullen als overlay.
 *
 * Sjabloon: assets/pdf/loonheffing_lh0082z12fol_v2024.pdf — het origineel, eenmalig met pdftk "platgeslagen"
 * (geen objectstreams, zodat FPDI het kan lezen; inhoud en uiterlijk ongewijzigd). Het origineel staat ernaast
 * (…_origineel.pdf). De sha256 van het sjabloon staat hier vast én in elk ondertekend record, zodat later
 * aantoonbaar is met welk formulier is getekend.
 *
 * Posities: de /Rect van de invulvelden van het origineel (punten, oorsprong linksonder, A4 = 841.89 pt hoog),
 * eenmalig uitgelezen. Cijferblokken (BSN, datums) zijn "combs": elk cijfer in zijn eigen cel.
 */

if (!function_exists('loonheffing_pdf_bouwen')) {

function loonheffing_sjabloon_versie(): string { return 'LH 008-2Z*12FOL (v2024)'; }
function loonheffing_sjabloon_pad(): string { return dirname(__DIR__) . '/assets/pdf/loonheffing_lh0082z12fol_v2024.pdf'; }
/** Verwachte sha256 van het sjabloon (controle tegen een per ongeluk vervangen bestand). */
function loonheffing_sjabloon_sha256_verwacht(): string { return '01750674817bf672354669fa9cefd306f7c80dfdfbfae37dfc624ea47cfcc260'; }
function loonheffing_sjabloon_sha256(): string {
    static $h = null;
    return $h ??= (is_file(loonheffing_sjabloon_pad()) ? (string)hash_file('sha256', loonheffing_sjabloon_pad()) : '');
}
function loonheffing_sjabloon_ok(): bool { return loonheffing_sjabloon_sha256() === loonheffing_sjabloon_sha256_verwacht(); }

/** Rect (pt, linksonder) → ['x','y','w','h'] in mm, oorsprong linksboven. */
function loonheffing_rect_mm(float $x1, float $y1, float $x2, float $y2): array {
    $f = 25.4 / 72; $H = 841.89;
    return ['x' => $x1 * $f, 'y' => ($H - $y2) * $f, 'w' => ($x2 - $x1) * $f, 'h' => ($y2 - $y1) * $f];
}

/**
 * Alle posities per veld: pagina, rect (mm) en soort (tekst | comb (met cellen) | kruis | beeld).
 * Sleutels sluiten aan op de veldnamen van het origineel (1.0, 1.1_BSN, ... 2.0, 2.1, 2.2.date02, ...).
 */
function loonheffing_veld_posities(): array {
    $r = 'loonheffing_rect_mm';
    return [
        // Pagina 1 — vraag 1
        'naam'        => ['p' => 1, 'soort' => 'tekst'] + $r(199.4, 293.29, 563.6, 310.49),
        'bsn'         => ['p' => 1, 'soort' => 'comb', 'cellen' => 9] + $r(199.4, 269.29, 325.6, 286.49),
        'adres'       => ['p' => 1, 'soort' => 'tekst'] + $r(199.4, 245.29, 563.6, 262.49),
        'postcode'    => ['p' => 1, 'soort' => 'tekst'] + $r(199.4, 197.29, 283.6, 214.49),
        'woonplaats'  => ['p' => 1, 'soort' => 'tekst'] + $r(297.4, 197.29, 563.6, 214.49),
        'land'        => ['p' => 1, 'soort' => 'tekst'] + $r(199.4, 173.29, 563.6, 190.49),
        'regio'       => ['p' => 1, 'soort' => 'tekst'] + $r(199.4, 149.29, 563.6, 166.49),
        'geb_d'       => ['p' => 1, 'soort' => 'comb', 'cellen' => 2] + $r(199.4, 125.29, 227.6, 142.49),
        'geb_m'       => ['p' => 1, 'soort' => 'comb', 'cellen' => 2] + $r(241.4, 125.29, 269.6, 142.49),
        'geb_j'       => ['p' => 1, 'soort' => 'comb', 'cellen' => 4] + $r(283.4, 125.29, 339.6, 142.49),
        // Pagina 2 — vraag 2 en 3
        'begrip'      => ['p' => 2, 'soort' => 'kruis'] + $r(325.4, 737.29, 336.4, 748.29),
        'korting_ja'  => ['p' => 2, 'soort' => 'kruis'] + $r(325.4, 683.29, 336.4, 694.29),
        'korting_nee' => ['p' => 2, 'soort' => 'kruis'] + $r(325.4, 629.29, 336.4, 640.29),
        'vanaf_ja_d'  => ['p' => 2, 'soort' => 'comb', 'cellen' => 2] + $r(325.4, 581.29, 353.6, 598.49),
        'vanaf_ja_m'  => ['p' => 2, 'soort' => 'comb', 'cellen' => 2] + $r(367.401, 581.29, 395.601, 598.49),
        'vanaf_ja_j'  => ['p' => 2, 'soort' => 'comb', 'cellen' => 4] + $r(409.401, 581.29, 465.601, 598.49),
        'aow_ja'      => ['p' => 2, 'soort' => 'kruis'] + $r(325.4, 545.29, 336.4, 556.29),
        'aow_nee'     => ['p' => 2, 'soort' => 'kruis'] + $r(325.4, 527.29, 336.4, 538.29),
        'vanaf_nee_d' => ['p' => 2, 'soort' => 'comb', 'cellen' => 2] + $r(325.4, 461.29, 353.6, 478.49),
        'vanaf_nee_m' => ['p' => 2, 'soort' => 'comb', 'cellen' => 2] + $r(367.401, 461.29, 395.601, 478.49),
        'vanaf_nee_j' => ['p' => 2, 'soort' => 'comb', 'cellen' => 4] + $r(409.401, 461.29, 465.601, 478.49),
        'datum_d'     => ['p' => 2, 'soort' => 'comb', 'cellen' => 2] + $r(199.4, 377.29, 227.6, 394.49),
        'datum_m'     => ['p' => 2, 'soort' => 'comb', 'cellen' => 2] + $r(241.4, 377.29, 269.6, 394.49),
        'datum_j'     => ['p' => 2, 'soort' => 'comb', 'cellen' => 4] + $r(283.4, 377.29, 339.6, 394.49),
        // Handtekeningkader (geen veld in het origineel; uit de render gemeten)
        'handtekening'=> ['p' => 2, 'soort' => 'beeld', 'x' => 70.4, 'y' => 166.6, 'w' => 49.2, 'h' => 18.0],
    ];
}

/** Waarde op een positie zetten. */
function loonheffing_pdf_zet($pdf, array $pos, string $waarde): void {
    if ($waarde === '') return;
    $tx = function_exists('ondertekening_pdf_tekst') ? ondertekening_pdf_tekst($waarde) : $waarde;
    $pdf->SetTextColor(20, 20, 20);
    switch ($pos['soort']) {
        case 'tekst':
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetXY($pos['x'] + 1.2, $pos['y']);
            $pdf->Cell($pos['w'] - 2.4, $pos['h'], $tx, 0, 0, 'L');
            break;
        case 'comb':
            $pdf->SetFont('helvetica', '', 10);
            $n = max(1, (int)$pos['cellen']); $cel = $pos['w'] / $n;
            $tekens = str_split(substr(preg_replace('/\D+/', '', $tx), 0, $n));
            foreach ($tekens as $i => $c) { $pdf->SetXY($pos['x'] + $i * $cel, $pos['y']); $pdf->Cell($cel, $pos['h'], $c, 0, 0, 'C'); }
            break;
        case 'kruis':
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY($pos['x'], $pos['y']);
            $pdf->Cell($pos['w'], $pos['h'], 'X', 0, 0, 'C');
            break;
    }
}

/**
 * Het formulier opbouwen in $pdf (TCPDF+FPDI uit zegel_pdf_nieuw()): 4 pagina's van het sjabloon met de
 * overlay. BSN komt uit de kluis ($rec['vertrouwelijk']); ontbreekt die, dan blijft het BSN-vak leeg en
 * wordt dat gelogd (nooit een fout tonen in het document).
 */
function loonheffing_pdf_bouwen($pdf, array $rec, array $sol): void {
    $form = json_decode((string)($rec['formulier_data'] ?? ''), true) ?: [];
    $bsn = '';
    if (!empty($rec['vertrouwelijk']) && function_exists('kluis_ontsleutel')) {
        $j = kluis_ontsleutel((string)$rec['vertrouwelijk'], 'ondertekening|' . (int)($rec['id'] ?? 0) . '|vertrouwelijk');
        $d = $j !== null ? json_decode($j, true) : null;
        $bsn = (string)($d['bsn'] ?? '');
        if ($bsn === '') error_log('loonheffing_pdf_bouwen: BSN niet te ontsleutelen voor ondertekening ' . ($rec['id'] ?? '?'));
    }
    $datum = fn(string $ymd): array => preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m) ? ['d' => $m[3], 'm' => $m[2], 'j' => $m[1]] : ['d' => '', 'm' => '', 'j' => ''];
    $geb   = $datum((string)($form['geboortedatum'] ?? ''));
    $vanaf = $datum((string)($form['vanaf'] ?? ''));
    $ond   = $datum(!empty($rec['ondertekend_op']) ? date('Y-m-d', strtotime($rec['ondertekend_op'])) : date('Y-m-d'));
    $ja    = ($form['korting'] ?? '') === 'ja';

    $waarden = [
        'naam'       => trim((string)($form['achternaam'] ?? '') . ', ' . (string)($form['voorletters'] ?? ''), ', '),
        'bsn'        => $bsn,
        'adres'      => (string)($form['adres'] ?? ''),
        'postcode'   => (string)($form['postcode'] ?? ''),
        'woonplaats' => (string)($form['woonplaats'] ?? ''),
        'geb_d' => $geb['d'], 'geb_m' => $geb['m'], 'geb_j' => $geb['j'],
        'begrip'      => !empty($form['begrip']) ? 'X' : '',
        'korting_ja'  => $ja ? 'X' : '',
        'korting_nee' => !$ja && ($form['korting'] ?? '') === 'nee' ? 'X' : '',
        'vanaf_ja_d'  => $ja ? $vanaf['d'] : '', 'vanaf_ja_m'  => $ja ? $vanaf['m'] : '', 'vanaf_ja_j'  => $ja ? $vanaf['j'] : '',
        'vanaf_nee_d' => !$ja ? $vanaf['d'] : '', 'vanaf_nee_m' => !$ja ? $vanaf['m'] : '', 'vanaf_nee_j' => !$ja ? $vanaf['j'] : '',
        'datum_d' => $ond['d'], 'datum_m' => $ond['m'], 'datum_j' => $ond['j'],
    ];
    $posities = loonheffing_veld_posities();

    $n = $pdf->setSourceFile(loonheffing_sjabloon_pad());
    for ($p = 1; $p <= $n; $p++) {
        $tpl  = $pdf->importPage($p);
        $size = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);
        $pdf->SetMargins(0, 0, 0); $pdf->SetAutoPageBreak(false);
        foreach ($posities as $veld => $pos) {
            if ($pos['p'] !== $p || $pos['soort'] === 'beeld') continue;
            loonheffing_pdf_zet($pdf, $pos, (string)($waarden[$veld] ?? ''));
        }
        if ($p === 2 && function_exists('ondertekening_pdf_handtekening')) {
            $h = $posities['handtekening'];
            // Handtekening-PNG passend in het kader (breedte begrensd; canvas is ~2.6:1 dus hoogte past)
            ondertekening_pdf_handtekening($pdf, $rec['handtekening_beeld'] ?? null, $h['x'] + 1.5, $h['y'] + 1.2, $h['w'] - 3.0, $h['h'] - 2.4, (string)($rec['ondertekenaar_naam'] ?? ''));
        }
    }
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->SetMargins(20, 18, 20);
    if (function_exists('kluis_wis')) kluis_wis($bsn);
}

} // function_exists

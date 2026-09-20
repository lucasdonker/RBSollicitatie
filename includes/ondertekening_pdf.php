<?php
/**
 * Paal 13 — PDF van een ondertekend formulier: inhoud + "Certificaat van ondertekening" + digitaal zegel.
 *
 * Opbouw (TCPDF + FPDI via includes/zegel.php):
 *   1. de inhoud per type: standaardlayout (gezondheidsverklaring, intake) óf een eigen bouwer
 *      (loonheffing_pdf_bouwen(): het officiële Belastingdienst-PDF als onderlegger; lidmaatschap_pdf_bouwen());
 *   2. de certificaatpagina: wie, wanneer, hoe geverifieerd (2FA-kanaal), tijdlijn uit het gebeurtenissenlog,
 *      bewijs-hash en certificaatgegevens;
 *   3. het zegel: PKCS#7-handtekening met het documentzegel-certificaat, zichtbaar veld op de certificaatpagina.
 *
 * De verzegelde PDF wordt bij het tekenen éénmaal gemaakt en versleuteld bewaard (sollicitant_documenten,
 * soort ondertekening_<type>); ondertekening_pdf_genereer() geeft daarna altijd dát bestand terug, zodat
 * de handtekening geldig blijft. Voor oudere records zonder opgeslagen PDF wordt hij on-the-fly gemaakt.
 */

if (!function_exists('ondertekening_pdf_bytes')) {

/** Tekens buiten Latin-1 netjes vervangen (core-fonts). */
function ondertekening_pdf_tekst($s): string {
    $s = (string)$s;
    return str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}", "\u{2026}", "\u{2192}"], ["'", "'", '"', '"', '-', '-', '...', '->'], $s);
}

/** Naam van de sollicitant uit de $sol-rij (voornaam + (tussenvoegsel) + achternaam). */
function ondertekening_pdf_naam(array $sol): string {
    return trim(($sol['voornaam'] ?? '') . ' ' . trim(($sol['tussenvoegsel'] ?? '') . ' ' . ($sol['achternaam'] ?? '')));
}

/**
 * Volledige, verzegelde PDF als bytes. Null als de PDF-bibliotheek ontbreekt.
 * $opties: ['zegel' => bool (standaard true)].
 */
function ondertekening_pdf_bytes(array $rec, array $sol, array $opties = []): ?string {
    if (!function_exists('zegel_lib_laden') || !zegel_lib_laden()) return null;
    try {
        $type  = (string)($rec['type'] ?? '');
        $titel = function_exists('ondertekening_label') ? ondertekening_label($type) : 'Ondertekend formulier';
        $pdf   = zegel_pdf_nieuw($titel . ' - ' . ondertekening_pdf_naam($sol));

        if ($type === 'loonheffing' && function_exists('loonheffing_pdf_bouwen'))       loonheffing_pdf_bouwen($pdf, $rec, $sol);
        elseif ($type === 'lidmaatschap' && function_exists('lidmaatschap_pdf_bouwen')) lidmaatschap_pdf_bouwen($pdf, $rec, $sol);
        else                                                                            ondertekening_pdf_bouwen_standaard($pdf, $rec, $sol);

        $veld = ondertekening_pdf_certificaatpagina($pdf, $rec, $sol);

        if (($opties['zegel'] ?? true) && function_exists('zegel_status') && zegel_status()['ok']) {
            zegel_toepassen($pdf, [
                'Reason' => 'Digitaal ondertekend door ' . ondertekening_pdf_tekst($rec['ondertekenaar_naam'] ?? '') . ' via het sollicitatiesysteem van Reddingsbrigade Paal 13',
            ], $veld);
        }
        return zegel_bytes($pdf, 'document.pdf');
    } catch (\Throwable $e) {
        error_log('ondertekening_pdf_bytes: ' . $e->getMessage());
        return null;
    }
}

/**
 * Compat: pad naar een tijdelijk PDF-bestand (mails, beheer). Verzegelde PDF uit de kluis als die er is,
 * anders on-the-fly. De aanroeper verwijdert het bestand na gebruik.
 */
function ondertekening_pdf_genereer(array $rec, array $sol): ?string {
    $bytes = null;
    if (!empty($rec['pdf_document_id']) && function_exists('document_rij')) {
        $d = document_rij((int)$rec['pdf_document_id']);
        if ($d) $bytes = document_inhoud($d);
    }
    if ($bytes === null) $bytes = ondertekening_pdf_bytes($rec, $sol);
    if ($bytes === null) return null;
    $pad = sys_get_temp_dir() . '/ondertekend_' . preg_replace('/[^a-z0-9]/i', '_', (string)($rec['type'] ?? 'doc')) . '_' . ($rec['id'] ?? 'x') . '_' . bin2hex(random_bytes(4)) . '.pdf';
    return @file_put_contents($pad, $bytes) !== false ? $pad : null;
}

/**
 * Genereert de PDF en stuurt hem direct naar de browser (inline). Eén plek voor het portaal en het beheer.
 * $sol: voornaam, achternaam (met tussenvoegsel), geboortedatum.
 */
function ondertekening_pdf_stream(array $rec, array $sol, string $bestandsnaam): never {
    $pad = ondertekening_pdf_genereer($rec, $sol);
    if ($pad && is_file($pad)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $bestandsnaam) . '"');
        header('Content-Length: ' . filesize($pad));
        header('Cache-Control: private, no-store');
        readfile($pad);
        @unlink($pad);
        exit;
    }
    http_response_code(500);
    exit('Kon het PDF-document niet genereren.');
}

/** Handtekening-afbeelding (data-URL PNG) passend in een kader van $w x $h mm (verhouding behouden); retourneert of het gelukt is. */
function ondertekening_pdf_handtekening($pdf, ?string $data_url, float $x, float $y, float $w, float $h = 22): bool {
    if (!$data_url || strpos($data_url, 'data:image/png;base64,') !== 0) return false;
    $bin = base64_decode(substr($data_url, strlen('data:image/png;base64,')), true);
    if ($bin === false || $bin === '') return false;
    try { $pdf->Image('@' . $bin, $x, $y, $w, $h, 'PNG', '', '', false, 300, '', false, false, 0, true); return true; } catch (\Throwable $e) { return false; }
}

/** Standaardlayout (gezondheidsverklaring, intake): logo, titel, gegevens, vragen, ondertekening. */
function ondertekening_pdf_bouwen_standaard($pdf, array $rec, array $sol): void {
    $tx = 'ondertekening_pdf_tekst';
    $form = [];
    if (!empty($rec['formulier_data'])) $form = json_decode((string)$rec['formulier_data'], true) ?: [];
    $type = (string)($rec['type'] ?? '');
    $is_gezondheid = in_array($type, ['gezondheid_ouder', 'gezondheid_18plus'], true);
    $is_ouder      = in_array($type, ['gezondheid_ouder', 'intake_ouder'], true);
    $doc_titel = match ($type) {
        'gezondheid_ouder'  => 'Gezondheidsverklaring (t/m 17 jaar)',
        'gezondheid_18plus' => 'Gezondheidsverklaring 18+',
        'intake', 'intake_ouder' => 'Intakeformulier Selectiedag',
        default             => 'Ondertekend formulier',
    };
    $NAVY = [5, 77, 138]; $ORANJE = [212, 61, 0]; $GRIJS = [90, 100, 120];

    $pdf->AddPage();
    $logo_pad = __DIR__ . '/../assets/img/logo-mail.png';
    if (is_file($logo_pad)) { $pdf->Image($logo_pad, 20, 15, 55); $pdf->Ln(22); } else { $pdf->Ln(4); }

    $pdf->SetFont('helvetica', 'B', 16); $pdf->SetTextColor(...$NAVY);
    $pdf->Cell(0, 9, $tx($doc_titel), 0, 1, 'L');
    $pdf->SetDrawColor(...$ORANJE); $pdf->SetLineWidth(0.6);
    $y = $pdf->GetY(); $pdf->Line(20, $y, 190, $y); $pdf->Ln(6);

    $pdf->SetFont('helvetica', '', 11); $pdf->SetTextColor(40, 40, 40);
    $regel = 'Dit formulier is ondertekend voor: ' . ondertekening_pdf_naam($sol);
    if (!empty($sol['geboortedatum'])) $regel .= ' (geboren ' . datum_nl($sol['geboortedatum']) . ')';
    $pdf->MultiCell(0, 6, $tx($regel), 0, 'L'); $pdf->Ln(3);

    $kop = function (string $t) use ($pdf, $tx, $NAVY) {
        $pdf->Ln(2); $pdf->SetFont('helvetica', 'B', 12); $pdf->SetTextColor(...$NAVY);
        $pdf->Cell(0, 7, $tx($t), 0, 1, 'L'); $pdf->SetFont('helvetica', '', 10.5); $pdf->SetTextColor(40, 40, 40);
    };
    $para = function (string $t) use ($pdf, $tx) { $pdf->SetFont('helvetica', '', 10); $pdf->SetTextColor(60, 60, 60); $pdf->MultiCell(0, 5, $tx($t), 0, 'L'); $pdf->Ln(1); };
    $vinkje = function (bool $aan, string $t) use ($pdf, $tx) {
        $pdf->SetFont('helvetica', '', 10); $pdf->SetTextColor(40, 40, 40);
        $pdf->Cell(8, 5, $aan ? '[X]' : '[  ]', 0, 0, 'L'); $pdf->MultiCell(0, 5, $tx($t), 0, 'L');
    };
    $veld = function (string $label, string $waarde) use ($pdf, $tx, $GRIJS) {
        $pdf->SetFont('helvetica', 'B', 10); $pdf->SetTextColor(...$GRIJS); $pdf->Cell(45, 6, $tx($label), 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10); $pdf->SetTextColor(30, 30, 30); $pdf->MultiCell(0, 6, $tx($waarde !== '' ? $waarde : '-'), 0, 'L');
    };

    $kop('Ondertekend door');
    $veld('Rol', $is_ouder ? 'Ouder / verzorger' : 'Deelnemer zelf');
    if (!empty($form['relatie'])) $veld('Relatie', ucfirst((string)$form['relatie']));
    $veld('Naam', (string)($rec['ondertekenaar_naam'] ?? ''));
    if (!empty($form['geslacht']))       $veld('Geslacht', ucfirst((string)$form['geslacht']));
    if (!empty($rec['ondertekenaar_geboorteplaats'])) $veld('Geboorteplaats', (string)$rec['ondertekenaar_geboorteplaats']);
    if (!empty($rec['ondertekenaar_geboortedatum']))  $veld('Geboortedatum', datum_nl($rec['ondertekenaar_geboortedatum']));

    if ($is_gezondheid) {
        $kop('Verklaring');
        $para($type === 'gezondheid_ouder'
            ? 'Ondergetekende verklaart dat bovengenoemde minderjarige mag deelnemen aan de opleidingen en het examen voor zwemmend en/of varend redden en/of wedstrijden waarbij de bepalingen van het geldige Examen Reglement worden gehandhaafd.'
            : 'Ondergetekende verklaart deel te mogen nemen aan de opleidingen en het examen voor zwemmend en/of varend redden en/of wedstrijden waarbij de bepalingen van het geldige Examen Reglement worden gehandhaafd.');
        $para('Ondergetekende verklaart bekend te zijn met het feit dat het bovenstaande verhoogde eisen stelt aan de fysieke toestand van de deelnemer, en verklaart het risico van deelname te aanvaarden en de organisatie en de Koninklijke Nederlandse Bond tot het Redden van Drenkelingen te vrijwaren van aansprakelijkheid voor gebeurtenissen die voortvloeien uit de vermelde gezondheidsrisico\'s.');
        $kop('Gemelde aandoeningen');
        $a = $form['aandoeningen'] ?? [];
        $vinkje(!empty($a['diabetes']), 'Diabetes (suikerziekte) of breuken');
        $vinkje(!empty($a['hart_vaat']), 'Hart- en vaataandoeningen (benauwdheid, snelle vermoeidheid, hartkloppingen)');
        $vinkje(!empty($a['ademhaling']), 'Astma / bronchitis');
        $vinkje(!empty($a['zenuwstelsel']), 'Duizeligheid, flauwtes, wegrakingen, epilepsie');
        $vinkje(!empty($a['gehoororgaan']), 'Middenoorontstekingen, loop-oor, gat in trommelvlies');
        if (!empty($a['overig'])) { $pdf->Ln(1); $veld('Overig / medicatie', (string)$a['overig']); }
    } else {
        $kop('1. Gezondheid & veiligheid');
        $keuze = (string)($form['gezondheid_keuze'] ?? '');
        $vinkje($keuze === 'geen', 'Geen aandachtspunten om te melden.');
        $vinkje($keuze === 'ja', 'Ja, aandachtspunten: ' . (string)($form['gezondheid_toelichting'] ?? ''));
        $kop('2. Foto\'s beleid');
        $vinkje(!empty($form['foto_toestemming']), 'Toestemming voor gebruik van foto\'s zoals omschreven (intern, promotie, sociale media).');
        $kop('3. Verklaring & aansprakelijkheid');
        $v = $form['verklaring'] ?? [];
        $vinkje(!empty($v['naar_waarheid']), 'Formulier naar waarheid ingevuld.');
        $vinkje(!empty($v['goede_gezondheid']), 'In goede gezondheid om deel te nemen aan sportieve activiteiten.');
        $vinkje(!empty($v['eigen_risico']), 'Deelname op eigen risico; organisatie niet aansprakelijk voor letsel uit verzwegen of onbekende omstandigheden.');
        if (!empty($v['leeftijd_16'])) $vinkje(true, '16 jaar of ouder en bevoegd om zelfstandig te ondertekenen.');
    }

    ondertekening_pdf_ondertekenblok($pdf, $rec);
}

/** Het blok "Digitale ondertekening" (naam, handtekening, plaats, datum) onderaan een standaard-PDF. */
function ondertekening_pdf_ondertekenblok($pdf, array $rec): void {
    $tx = 'ondertekening_pdf_tekst'; $NAVY = [5, 77, 138]; $GRIJS = [90, 100, 120];
    $pdf->Ln(4); $pdf->SetDrawColor(200, 200, 200); $pdf->SetLineWidth(0.2);
    $y = $pdf->GetY(); $pdf->Line(20, $y, 190, $y); $pdf->Ln(4);
    $pdf->SetFont('helvetica', 'B', 12); $pdf->SetTextColor(...$NAVY); $pdf->Cell(0, 7, 'Digitale ondertekening', 0, 1, 'L');
    $veld = function (string $label, string $waarde) use ($pdf, $tx, $GRIJS) {
        $pdf->SetFont('helvetica', 'B', 10); $pdf->SetTextColor(...$GRIJS); $pdf->Cell(45, 6, $tx($label), 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10); $pdf->SetTextColor(30, 30, 30); $pdf->MultiCell(0, 6, $tx($waarde !== '' ? $waarde : '-'), 0, 'L');
    };
    $veld('Naam (getypt)', (string)($rec['ondertekenaar_naam'] ?? ''));
    $pdf->SetFont('helvetica', '', 9); $pdf->SetTextColor(...$GRIJS); $pdf->Cell(45, 6, 'Handtekening', 0, 0);
    $y_hdt = $pdf->GetY();
    if (ondertekening_pdf_handtekening($pdf, $rec['handtekening_beeld'] ?? null, 65, $y_hdt - 1, 60)) $pdf->Ln(24); else $pdf->Ln(6);
    $veld('Plaats', (string)($rec['plaats'] ?? ''));
    if (!empty($rec['ondertekend_op'])) $veld('Datum & tijd', date('d-m-Y H:i', strtotime($rec['ondertekend_op'])));
    if (!empty($rec['geldig_tot']))     $veld('Geldig tot', datum_nl($rec['geldig_tot']));
}

/**
 * De certificaatpagina: wie, wanneer, hoe geverifieerd, tijdlijn, bewijs-hash, certificaat.
 * Retourneert de plek (mm) voor het zichtbare handtekeningveld op deze pagina.
 */
function ondertekening_pdf_certificaatpagina($pdf, array $rec, array $sol): array {
    $tx = 'ondertekening_pdf_tekst'; $NAVY = [5, 77, 138]; $ORANJE = [212, 61, 0]; $GRIJS = [90, 100, 120];
    $pdf->AddPage('P', 'A4');
    $pdf->SetMargins(20, 18, 20);
    $pdf->SetFont('helvetica', 'B', 16); $pdf->SetTextColor(...$NAVY);
    $pdf->Cell(0, 9, 'Certificaat van ondertekening', 0, 1, 'L');
    $pdf->SetDrawColor(...$ORANJE); $pdf->SetLineWidth(0.6);
    $y = $pdf->GetY(); $pdf->Line(20, $y, 190, $y); $pdf->Ln(4);
    $pdf->SetFont('helvetica', '', 9.5); $pdf->SetTextColor(60, 60, 60);
    $pdf->MultiCell(0, 4.8, $tx('Dit blad hoort bij het voorgaande document en legt vast wie het heeft ondertekend, hoe de ondertekenaar is geverifieerd en dat het document sindsdien niet is gewijzigd. Het hele bestand (document + dit blad) is digitaal verzegeld door Reddingsbrigade Paal 13.'), 0, 'L');
    $pdf->Ln(3);

    $kop = function (string $t) use ($pdf, $NAVY) { $pdf->Ln(1.5); $pdf->SetFont('helvetica', 'B', 11); $pdf->SetTextColor(...$NAVY); $pdf->Cell(0, 6.5, $t, 0, 1); };
    $veld = function (string $label, string $waarde) use ($pdf, $tx, $GRIJS) {
        $pdf->SetFont('helvetica', 'B', 9.5); $pdf->SetTextColor(...$GRIJS); $pdf->Cell(48, 5.5, $tx($label), 0, 0);
        $pdf->SetFont('helvetica', '', 9.5); $pdf->SetTextColor(30, 30, 30); $pdf->MultiCell(0, 5.5, $tx($waarde !== '' ? $waarde : '-'), 0, 'L');
    };
    $type  = (string)($rec['type'] ?? '');
    $label = function_exists('ondertekening_label') ? ondertekening_label($type) : $type;
    $token = (string)($rec['token'] ?? '');

    $kop('Document');
    $veld('Formulier', $label);
    $veld('Voor', ondertekening_pdf_naam($sol) . (!empty($sol['geboortedatum']) ? ' (geboren ' . datum_nl($sol['geboortedatum']) . ')' : ''));
    $veld('Documentkenmerk', 'OND-' . (int)($rec['id'] ?? 0) . ($token !== '' ? ' / ' . strtoupper(substr($token, 0, 8)) : ''));
    if (!empty($rec['formulier_data'])) {
        $fd = json_decode((string)$rec['formulier_data'], true) ?: [];
        if (!empty($fd['sjabloon_versie'])) $veld('Sjabloon', (string)$fd['sjabloon_versie'] . (!empty($fd['sjabloon_sha256']) ? ' (sha256 ' . substr((string)$fd['sjabloon_sha256'], 0, 16) . '...)' : ''));
    }

    $kop('Ondertekenaar');
    $veld('Naam', (string)($rec['ondertekenaar_naam'] ?? ''));
    $veld('Rol', ($rec['ondertekenaar_rol'] ?? '') === 'ouder' ? 'Ouder / verzorger' : 'Sollicitant (werknemer) zelf');
    $veld('E-mailadres', (string)($rec['ondertekenaar_email'] ?: ($sol['email'] ?? '')));
    $veld('Ondertekend op', !empty($rec['ondertekend_op']) ? date('d-m-Y H:i:s', strtotime($rec['ondertekend_op'])) . ' (Europe/Amsterdam)' : '-');
    $veld('Plaats (opgegeven)', (string)($rec['plaats'] ?? ''));
    $veld('IP-adres', (string)($rec['ondertekend_ip'] ?? ''));
    $veld('Browser', (string)($rec['ondertekend_user_agent'] ?? ''));
    $veld('Verificatie', function_exists('ondertekening_zekerheid_label') ? ondertekening_zekerheid_label($rec['zekerheid'] ?? null, $rec['zekerheid_naar'] ?? null) : (string)($rec['zekerheid'] ?? ''));

    $kop('Tijdlijn');
    $gebeurtenissen = function_exists('ondertekening_gebeurtenissen') && !empty($rec['id']) ? ondertekening_gebeurtenissen((int)$rec['id']) : [];
    if (!$gebeurtenissen) {
        $pdf->SetFont('helvetica', '', 9.5); $pdf->SetTextColor(60, 60, 60); $pdf->MultiCell(0, 5.5, 'Geen gebeurtenissen vastgelegd (document van vóór het gebeurtenissenlog).', 0, 'L');
    }
    foreach ($gebeurtenissen as $g) {
        $det = '';
        $d = json_decode((string)($g['details'] ?? ''), true);
        if (is_array($d)) {
            $delen = [];
            foreach ($d as $k => $v) {
                if (!in_array($k, ['kanaal', 'naar', 'reden', 'rol', 'door'], true) || $v === '' || $v === null) continue;
                $delen[] = $k . ': ' . ($k === 'naar' && function_exists('telefoon_opmaak') ? telefoon_opmaak((string)$v) : $v);
            }
            $det = $delen ? ' (' . implode(', ', $delen) . ')' : '';
        }
        $pdf->SetFont('helvetica', '', 9); $pdf->SetTextColor(...$GRIJS); $pdf->Cell(36, 5, date('d-m-Y H:i:s', strtotime($g['tijdstip'])), 0, 0);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->MultiCell(0, 5, $tx((function_exists('ondertekening_gebeurtenis_label') ? ondertekening_gebeurtenis_label((string)$g['gebeurtenis']) : $g['gebeurtenis']) . $det . (!empty($g['ip']) ? ' - ' . $g['ip'] : '')), 0, 'L');
    }

    $kop('Controle');
    $hash = (string)($rec['bewijs_hash'] ?? (function_exists('ondertekening_bewijs_hash') ? ondertekening_bewijs_hash($rec) : ''));
    if ($hash !== '') $veld('Bewijs-hash (SHA-256)', chunk_split($hash, 32, ' '));
    $info = function_exists('zegel_info') ? zegel_info() : [];
    if ($info) {
        $veld('Zegelcertificaat', $info['naam'] . ' (' . $info['organisatie'] . '), geldig ' . $info['geldig_van'] . ' t/m ' . $info['geldig_tot']);
        $veld('Vingerafdruk', chunk_split(str_replace(':', '', $info['vingerafdruk']), 32, ' '));
    }
    $pdf->Ln(1);
    $pdf->SetFont('helvetica', '', 9); $pdf->SetTextColor(60, 60, 60);
    $pdf->MultiCell(0, 4.6, $tx('Controleren: open dit bestand in Adobe Acrobat Reader en kies het handtekeningpaneel. Staat daar "Document is niet gewijzigd sinds de ondertekening", dan is de inhoud intact. De melding "geldigheid van de ondertekenaar onbekend" betekent alleen dat het certificaat van Paal 13 niet in de vertrouwenslijst van Adobe staat.'), 0, 'L');

    // Zichtbaar handtekeningveld (het zegel) rechtsonder op deze pagina
    $veld_pos = ['x' => 120, 'y' => 250, 'w' => 70, 'h' => 26, 'pagina' => $pdf->getPage()];
    $pdf->SetDrawColor(...$NAVY); $pdf->SetLineWidth(0.3);
    $pdf->Rect($veld_pos['x'], $veld_pos['y'], $veld_pos['w'], $veld_pos['h']);
    $pdf->SetXY($veld_pos['x'] + 2, $veld_pos['y'] + 2);
    $pdf->SetFont('helvetica', 'B', 8.5); $pdf->SetTextColor(...$NAVY); $pdf->Cell($veld_pos['w'] - 4, 4.5, 'Digitaal verzegeld', 0, 2);
    $pdf->SetFont('helvetica', '', 7.5); $pdf->SetTextColor(...$GRIJS);
    $pdf->MultiCell($veld_pos['w'] - 4, 3.6, $tx('Reddingsbrigade Paal 13 - Documentzegel' . (!empty($rec['ondertekend_op']) ? "\n" . date('d-m-Y H:i', strtotime($rec['ondertekend_op'])) : '') . "\nZie het handtekeningpaneel"), 0, 'L');
    return $veld_pos;
}

} // function_exists

<?php
/**
 * Paal 13 — PDF-generatie voor ondertekende formulieren.
 * Genereert een nette PDF (met logo) van een ondertekende gezondheidsverklaring
 * of intakeformulier, zodat de ondertekenaar kan terugzien waar hij/zij voor tekende.
 *
 * Gebruikt FPDF (public domain, dependency-vrij) uit includes/lib/fpdf.php.
 */

if (!function_exists('ondertekening_pdf_genereer')) {

/**
 * Genereert de PDF voor een ondertekening-record.
 * @param array $rec  Rij uit de ondertekeningen-tabel (met formulier_data als JSON).
 * @param array $sol  Sollicitant-gegevens (voornaam, achternaam, geboortedatum).
 * @return string|null  Pad naar het PDF-bestand, of null bij fout.
 */
function ondertekening_pdf_genereer(array $rec, array $sol): ?string {
    try {
        require_once __DIR__ . '/lib/fpdf.php';

        $form = [];
        if (!empty($rec['formulier_data'])) {
            $form = json_decode($rec['formulier_data'], true) ?: [];
        }

        // Helper: tekst veilig naar Latin-1 (FPDF core-fonts zijn Latin-1)
        $tx = function ($s): string {
            $s = (string)$s;
            // Veelvoorkomende tekens netjes vervangen
            $s = str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}", "\u{2026}"],
                             ["'", "'", '"', '"', '-', '-', '...'], $s);
            $conv = @iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
            return $conv !== false ? $conv : $s;
        };

        $is_gezondheid = in_array($rec['type'], ['gezondheid_ouder', 'gezondheid_18plus'], true);
        $is_ouder      = in_array($rec['type'], ['gezondheid_ouder', 'intake_ouder'], true);

        $doc_titel = match ($rec['type']) {
            'gezondheid_ouder'  => 'Gezondheidsverklaring (t/m 17 jaar)',
            'gezondheid_18plus' => 'Gezondheidsverklaring 18+',
            'intake'            => 'Intakeformulier Selectiedag',
            'intake_ouder'      => 'Intakeformulier Selectiedag',
            default             => 'Ondertekend formulier',
        };

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(20, 18, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $NAVY = [5, 77, 138];
        $ORANJE = [212, 61, 0];
        $GRIJS = [90, 100, 120];

        // Logo (indien aanwezig)
        $logo_pad = __DIR__ . '/../assets/img/logo-mail.png';
        if (is_file($logo_pad)) {
            // Breedte 55mm, gecentreerd bovenaan
            $pdf->Image($logo_pad, 20, 15, 55);
            $pdf->Ln(22);
        } else {
            $pdf->Ln(4);
        }

        // Titel
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(...$NAVY);
        $pdf->Cell(0, 9, $tx($doc_titel), 0, 1, 'L');
        $pdf->SetDrawColor(...$ORANJE);
        $pdf->SetLineWidth(0.6);
        $y = $pdf->GetY();
        $pdf->Line(20, $y, 190, $y);
        $pdf->Ln(6);

        // Voor wie
        $naam_sol = trim(($sol['voornaam'] ?? '') . ' ' . ($sol['achternaam'] ?? ''));
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(40, 40, 40);
        $regel = 'Dit formulier is ondertekend voor: ' . $naam_sol;
        if (!empty($sol['geboortedatum'])) {
            $regel .= ' (geboren ' . datum_nl($sol['geboortedatum']) . ')';
        }
        $pdf->MultiCell(0, 6, $tx($regel));
        $pdf->Ln(3);

        // Blok-helper: kop
        $kop = function (string $t) use ($pdf, $tx, $NAVY) {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetTextColor(...$NAVY);
            $pdf->Cell(0, 7, $tx($t), 0, 1, 'L');
            $pdf->SetFont('Arial', '', 10.5);
            $pdf->SetTextColor(40, 40, 40);
        };
        $para = function (string $t) use ($pdf, $tx) {
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->MultiCell(0, 5, $tx($t));
            $pdf->Ln(1);
        };
        $vinkje = function (bool $aan, string $t) use ($pdf, $tx) {
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetTextColor(40, 40, 40);
            $mark = $aan ? '[X]' : '[  ]';
            $pdf->Cell(8, 5, $mark, 0, 0, 'L');
            $pdf->MultiCell(0, 5, $tx($t));
        };
        $veld = function (string $label, string $waarde) use ($pdf, $tx, $GRIJS) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(...$GRIJS);
            $pdf->Cell(45, 6, $tx($label), 0, 0, 'L');
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->MultiCell(0, 6, $tx($waarde !== '' ? $waarde : '—'));
        };

        // Ondertekenaar-gegevens
        $kop('Ondertekend door');
        $rol_txt = $is_ouder ? 'Ouder / verzorger' : 'Deelnemer zelf';
        $veld('Rol', $rol_txt);
        if (!empty($form['relatie'])) $veld('Relatie', ucfirst($form['relatie']));
        $veld('Naam', $rec['ondertekenaar_naam'] ?? '');
        if (!empty($form['geslacht']))       $veld('Geslacht', ucfirst($form['geslacht']));
        if (!empty($rec['ondertekenaar_geboorteplaats'])) $veld('Geboorteplaats', $rec['ondertekenaar_geboorteplaats']);
        if (!empty($rec['ondertekenaar_geboortedatum']))  $veld('Geboortedatum', datum_nl($rec['ondertekenaar_geboortedatum']));

        if ($is_gezondheid) {
            // Verklaring
            $kop('Verklaring');
            if ($rec['type'] === 'gezondheid_ouder') {
                $para('Ondergetekende verklaart dat bovengenoemde minderjarige mag deelnemen aan de opleidingen en het examen voor zwemmend en/of varend redden en/of wedstrijden waarbij de bepalingen van het geldige Examen Reglement worden gehandhaafd.');
            } else {
                $para('Ondergetekende verklaart deel te mogen nemen aan de opleidingen en het examen voor zwemmend en/of varend redden en/of wedstrijden waarbij de bepalingen van het geldige Examen Reglement worden gehandhaafd.');
            }
            $para('Ondergetekende verklaart bekend te zijn met het feit dat het bovenstaande verhoogde eisen stelt aan de fysieke toestand van de deelnemer, en verklaart het risico van deelname te aanvaarden en de organisatie en de Koninklijke Nederlandse Bond tot het Redden van Drenkelingen te vrijwaren van aansprakelijkheid voor gebeurtenissen die voortvloeien uit de vermelde gezondheidsrisico\'s.');

            // Aandoeningen
            $kop('Gemelde aandoeningen');
            $a = $form['aandoeningen'] ?? [];
            $vinkje(!empty($a['diabetes']), 'Diabetes (suikerziekte) of breuken');
            $vinkje(!empty($a['hart_vaat']), 'Hart- en vaataandoeningen (benauwdheid, snelle vermoeidheid, hartkloppingen)');
            $vinkje(!empty($a['ademhaling']), 'Astma / bronchitis');
            $vinkje(!empty($a['zenuwstelsel']), 'Duizeligheid, flauwtes, wegrakingen, epilepsie');
            $vinkje(!empty($a['gehoororgaan']), 'Middenoorontstekingen, loop-oor, gat in trommelvlies');
            if (!empty($a['overig'])) {
                $pdf->Ln(1);
                $veld('Overig / medicatie', $a['overig']);
            }
        } else {
            // Intake
            $kop('1. Gezondheid & veiligheid');
            $keuze = $form['gezondheid_keuze'] ?? '';
            $vinkje($keuze === 'geen', 'Geen aandachtspunten om te melden.');
            $vinkje($keuze === 'ja', 'Ja, aandachtspunten: ' . ($form['gezondheid_toelichting'] ?? ''));

            $kop('2. Foto\'s beleid');
            $vinkje(!empty($form['foto_toestemming']), 'Toestemming voor gebruik van foto\'s zoals omschreven (intern, promotie, sociale media).');

            $kop('3. Verklaring & aansprakelijkheid');
            $v = $form['verklaring'] ?? [];
            $vinkje(!empty($v['naar_waarheid']), 'Formulier naar waarheid ingevuld.');
            $vinkje(!empty($v['goede_gezondheid']), 'In goede gezondheid om deel te nemen aan sportieve activiteiten.');
            $vinkje(!empty($v['eigen_risico']), 'Deelname op eigen risico; organisatie niet aansprakelijk voor letsel uit verzwegen of onbekende omstandigheden.');
            if (!empty($v['leeftijd_16'])) {
                $vinkje(true, '16 jaar of ouder en bevoegd om zelfstandig te ondertekenen.');
            }
        }

        // Ondertekening-blok
        $pdf->Ln(4);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.2);
        $y = $pdf->GetY();
        $pdf->Line(20, $y, 190, $y);
        $pdf->Ln(4);

        $kop('Digitale ondertekening');
        $veld('Naam (getypt)', $rec['ondertekenaar_naam'] ?? '');

        // Handtekening-afbeelding (indien getekend). Base64 PNG data-URL → tijdelijk bestand → Image.
        $hdt_tmp = null;
        if (!empty($rec['handtekening_beeld']) && strpos($rec['handtekening_beeld'], 'data:image/png;base64,') === 0) {
            $data = base64_decode(substr($rec['handtekening_beeld'], strlen('data:image/png;base64,')), true);
            if ($data !== false && strlen($data) > 0) {
                $hdt_tmp = sys_get_temp_dir() . '/hdt_' . ($rec['id'] ?? 'x') . '_' . uniqid() . '.png';
                if (@file_put_contents($hdt_tmp, $data) !== false) {
                    $pdf->Ln(2);
                    $pdf->SetFont('Arial', '', 9);
                    $pdf->SetTextColor(...$GRIJS);
                    $pdf->Cell(38, 6, $tx('Handtekening'), 0, 0);
                    $y_hdt = $pdf->GetY();
                    // Plaats de handtekening (max 60mm breed, 22mm hoog) rechts van het label
                    try { $pdf->Image($hdt_tmp, 58, $y_hdt - 1, 60, 0, 'PNG'); } catch (\Throwable $e) {}
                    $pdf->Ln(24);
                }
            }
        }

        $veld('Plaats', $rec['plaats'] ?? '');
        if (!empty($rec['ondertekend_op'])) $veld('Datum & tijd', date('d-m-Y H:i', strtotime($rec['ondertekend_op'])));
        if (!empty($rec['geldig_tot']))     $veld('Geldig tot', datum_nl($rec['geldig_tot']));
        if (!empty($rec['ondertekend_ip'])) $veld('IP-adres', $rec['ondertekend_ip']);

        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'I', 8.5);
        $pdf->SetTextColor(...$GRIJS);
        $pdf->MultiCell(0, 4, $tx('Dit document is digitaal ondertekend via het sollicitatiesysteem van Reddingsbrigade Paal 13. De ondertekening is vastgelegd met tijdstip en IP-adres.'));

        // Opslaan naar tijdelijk bestand
        $bestandsnaam = 'ondertekend_' . preg_replace('/[^a-z0-9]/i', '_', $rec['type']) . '_' . ($rec['id'] ?? 'x') . '.pdf';
        $pad = sys_get_temp_dir() . '/' . $bestandsnaam;
        $pdf->Output('F', $pad);
        if ($hdt_tmp && is_file($hdt_tmp)) @unlink($hdt_tmp);
        return is_file($pad) ? $pad : null;

    } catch (\Throwable $e) {
        error_log('ondertekening_pdf_genereer: ' . $e->getMessage());
        return null;
    }
}

/**
 * Genereert de PDF en stuurt hem direct naar de browser (inline). Eén plek voor het portaal
 * (kopie voor de ondertekenaar) en het beheer (bekijken/printen). Stopt de request; bij een
 * fout een nette 500-tekst. $sol: voornaam, achternaam (met tussenvoegsel), geboortedatum.
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

}

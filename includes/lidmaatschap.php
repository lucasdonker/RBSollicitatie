<?php
/**
 * Paal 13 — Lidmaatschap van de vereniging (ondertekeningstype 'lidmaatschap').
 *
 * Dezelfde velden als het inschrijfformulier op paal13.com/vereniging/lid-worden, maar vooringevuld uit het
 * dossier en ondertekend in het portaal (verzegelde PDF; een verificatiecode vraagt standaard alleen de loonheffing, zie
 * instelling ondertekenen.2fa_typen). Na het tekenen gaat de inschrijving
 * als e-mail met de PDF naar de ledenservice (instelling mail.ledenservice_email). IBAN + tenaamstelling
 * staan versleuteld in de kluis (ondertekeningen.vertrouwelijk), niet in formulier_data.
 * Later: dezelfde data naar de Paal 13-hub (lidmaatschap_na_ondertekening() is de plek voor die hook).
 */

if (!function_exists('lidmaatschap_diplomas')) {

/** Diploma's/licenties zoals op het inschrijfformulier: groep => [sleutel => label]. */
function lidmaatschap_diplomas(): array {
    return [
        'Zwemdiploma\'s' => ['zwem_a' => 'A', 'zwem_b' => 'B', 'zwem_c' => 'C'],
        'Rijbewijzen'    => ['rijbewijs_b' => 'B', 'rijbewijs_be' => 'BE', 'rijbewijs_t' => 'T'],
        'EHBO'           => ['ehbo' => 'EHBO', 'ehbk' => 'EHBK'],
        'Lifeguard'      => ['jr_lifeguard' => 'Jr. Lifeguard', 'lifeguard' => 'Lifeguard', 'sr_lifeguard' => 'Sr. Lifeguard'],
        'Maritiem'       => ['marifoon_basis' => 'Marifoon Basis', 'marcom_a' => 'Marcom A', 'marcom_b' => 'Marcom B', 'vaarbewijs_1' => 'Vaarbewijs Deel 1', 'vaarbewijs_2' => 'Vaarbewijs Deel 2'],
    ];
}

/** Alle diplomasleutels plat. */
function lidmaatschap_diploma_sleutels(): array {
    $uit = [];
    foreach (lidmaatschap_diplomas() as $groep) foreach ($groep as $k => $l) $uit[$k] = $l;
    return $uit;
}

/** Diploma's voorvinken uit de vrije BLK-tekst ("Zwemdiploma A, B, C, EHBO, Junior Lifeguard"). */
function lidmaatschap_diplomas_uit_tekst(string $tekst): array {
    $t = strtolower($tekst); $uit = [];
    if ($t === '') return $uit;
    $patronen = [
        'zwem_a' => '/zwem[^,;]*\ba\b|\ba\b[ ,\/]+b[ ,\/]+c|diploma a/', 'zwem_b' => '/zwem[^,;]*\bb\b|\ba\b[ ,\/]+b\b|diploma b/', 'zwem_c' => '/zwem[^,;]*\bc\b|\bb\b[ ,\/]+c\b|diploma c/',
        'rijbewijs_b' => '/rijbewijs\s*\(?b\b|rijbewijs b/', 'rijbewijs_be' => '/\bbe\b.*rijbewijs|rijbewijs[^,;]*\bbe\b/', 'rijbewijs_t' => '/rijbewijs[^,;]*\bt\b|trekker/',
        'ehbo' => '/\behbo\b/', 'ehbk' => '/\behbk\b|eerste hulp aan kinderen/',
        'jr_lifeguard' => '/junior lifeguard|jr\.? ?lifeguard/', 'lifeguard' => '/(?<!junior )(?<!jr\. )(?<!jr )(?<!senior )(?<!sr\. )(?<!sr )\blifeguard\b/', 'sr_lifeguard' => '/senior lifeguard|sr\.? ?lifeguard/',
        'marifoon_basis' => '/marifoon/', 'marcom_a' => '/marcom\s*a\b/', 'marcom_b' => '/marcom\s*b\b/',
        'vaarbewijs_1' => '/vaarbewijs(?![^,;]*\b(2|ii|deel 2)\b)/', 'vaarbewijs_2' => '/vaarbewijs[^,;]*\b(2|ii)\b/',
    ];
    foreach ($patronen as $k => $re) { if (@preg_match($re, $t)) $uit[] = $k; }
    return $uit;
}

/** Vooringevulde waarden (lid_*) uit persoon, BLK-adres, oudergegevens en eerdere ondertekening. */
function lidmaatschap_prefill(array $sol, array $blk, array $dossier): array {
    $sol_id = (int)($dossier['id'] ?? $sol['id'] ?? 0);
    $geboorteplaats = '';
    try {
        $q = db()->prepare("SELECT ondertekenaar_geboorteplaats FROM ondertekeningen WHERE sollicitatie_id=? AND ondertekenaar_rol='sollicitant' AND ondertekenaar_geboorteplaats IS NOT NULL AND ondertekenaar_geboorteplaats<>'' ORDER BY ondertekend_op DESC LIMIT 1");
        $q->execute([$sol_id]);
        $geboorteplaats = (string)($q->fetchColumn() ?: '');
    } catch (\Throwable $e) {}
    $ouder = function_exists('portaal_ouder_gegevens') ? portaal_ouder_gegevens($sol_id) : [];
    $ouder_naam = trim((string)($ouder['ouder_naam'] ?? ''));
    $ouder_voor = $ouder_naam; $ouder_achter = '';
    if ($ouder_naam !== '' && str_contains($ouder_naam, ' ')) { $delen = explode(' ', $ouder_naam, 2); $ouder_voor = $delen[0]; $ouder_achter = $delen[1]; }
    return [
        'lid_voornaam'       => (string)($sol['voornaam'] ?? ''),
        'lid_tussenvoegsel'  => (string)($sol['tussenvoegsel'] ?? ''),
        'lid_achternaam'     => (string)($sol['achternaam'] ?? ''),
        'lid_geboortedatum'  => (string)($sol['geboortedatum'] ?? ''),
        'lid_geboorteplaats' => $geboorteplaats,
        'lid_straat'         => (string)($blk['straat'] ?? ''),
        'lid_huisnummer'     => (string)($blk['huisnummer'] ?? ''),
        'lid_postcode'       => (string)($blk['postcode'] ?? ''),
        'lid_woonplaats'     => (string)($blk['woonplaats'] ?? ''),
        'lid_telefoon'       => (string)($dossier['telefoon'] ?? $sol['telefoon'] ?? ''),
        'lid_email'          => (string)($sol['email'] ?? ''),
        'lid_ouder_voornaam' => $ouder_voor,
        'lid_ouder_achternaam' => $ouder_achter,
        'lid_ouder_email'    => (string)($ouder['ouder_email'] ?? ''),
        'lid_ouder_telefoon' => (string)($ouder['ouder_telefoon'] ?? ''),
        'lid_soort'          => 'lid',
        'lid_diplomas'       => lidmaatschap_diplomas_uit_tekst((string)($blk['diplomas'] ?? '')),
    ];
}

/**
 * POST valideren. Retour ['form' => [...], 'vertrouwelijk' => ['iban'=>..,'tenaamstelling'=>..] | []].
 * Zet $fout bij een probleem.
 */
function lidmaatschap_post_valideren(array $post, bool $kluis_ok, string &$fout, ?int $leeftijd = null): array {
    $t = fn(string $k): string => trim((string)($post[$k] ?? ''));
    $form = [
        'voornaam' => $t('lid_voornaam'), 'tussenvoegsel' => $t('lid_tussenvoegsel'), 'achternaam' => $t('lid_achternaam'),
        'geslacht' => in_array($post['lid_geslacht'] ?? '', ['man', 'vrouw', 'geen'], true) ? $post['lid_geslacht'] : '',
        'geboortedatum' => $t('lid_geboortedatum'), 'geboorteplaats' => $t('lid_geboorteplaats'),
        'straat' => $t('lid_straat'), 'huisnummer' => $t('lid_huisnummer'), 'postcode' => postcode_opmaak($t('lid_postcode')) ?: '', 'woonplaats' => $t('lid_woonplaats'),
        'telefoon' => $t('lid_telefoon'), 'email' => $t('lid_email'),
        'ouder' => ['voornaam' => $t('lid_ouder_voornaam'), 'achternaam' => $t('lid_ouder_achternaam'), 'email' => $t('lid_ouder_email'), 'telefoon' => $t('lid_ouder_telefoon')],
        'soort' => in_array($post['lid_soort'] ?? '', ['lid', 'donateur'], true) ? $post['lid_soort'] : '',
        'diplomas' => array_values(array_intersect(array_map('strval', (array)($post['lid_diplomas'] ?? [])), array_keys(lidmaatschap_diploma_sleutels()))),
        'betaling' => in_array($post['lid_betaling'] ?? '', ['overschrijving', 'incasso'], true) ? $post['lid_betaling'] : '',
        'machtiging' => !empty($post['lid_machtiging']),
        'akkoord_opslag' => !empty($post['lid_akkoord_opslag']), 'akkoord_juist' => !empty($post['lid_akkoord_juist']),
    ];
    $minderjarig = $leeftijd !== null && $leeftijd < 18;
    $iban = strtoupper(preg_replace('/\s+/', '', $t('lid_iban'))); $tnv = $t('lid_tenaamstelling');
    if ($form['voornaam'] === '' || $form['achternaam'] === '') $fout = 'Vul je voornaam en achternaam in.';
    elseif ($form['geslacht'] === '') $fout = 'Kies je geslacht (of "wil ik niet specificeren").';
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['geboortedatum']) || $form['geboorteplaats'] === '') $fout = 'Vul je geboortedatum en geboorteplaats in.';
    elseif ($form['straat'] === '' || $form['huisnummer'] === '' || $form['postcode'] === '' || $form['woonplaats'] === '') $fout = 'Vul je straat, huisnummer, postcode (1234 AB) en woonplaats in.';
    elseif (preg_match_all('/\d/', $form['telefoon']) < 8) $fout = 'Vul een geldig telefoonnummer in.';
    elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $fout = 'Vul een geldig e-mailadres in.';
    elseif ($minderjarig && ($form['ouder']['voornaam'] === '' || $form['ouder']['achternaam'] === '' || !filter_var($form['ouder']['email'], FILTER_VALIDATE_EMAIL))) $fout = 'Vul de naam en het e-mailadres van je ouder of verzorger in.';
    elseif ($form['soort'] === '') $fout = 'Kies of je lid of donateur wilt worden.';
    elseif ($form['betaling'] === '') $fout = 'Kies hoe je wilt betalen (overschrijving of automatische incasso).';
    elseif ($form['betaling'] === 'incasso' && !$kluis_ok) $fout = 'De beveiligde opslag is tijdelijk niet beschikbaar; kies "overschrijving" of probeer het later opnieuw.';
    elseif ($form['betaling'] === 'incasso' && !iban_geldig($iban)) $fout = 'Het IBAN klopt niet. Controleer het rekeningnummer.';
    elseif ($form['betaling'] === 'incasso' && $tnv === '') $fout = 'Vul in op wiens naam de rekening staat.';
    elseif ($form['betaling'] === 'incasso' && !$form['machtiging']) $fout = 'Vink de machtiging voor automatische incasso aan.';
    elseif (!$form['akkoord_opslag'] || !$form['akkoord_juist']) $fout = 'Vink beide akkoordverklaringen aan.';
    $vertrouwelijk = ($fout === '' && $form['betaling'] === 'incasso') ? ['iban' => $iban, 'tenaamstelling' => $tnv] : [];
    return ['form' => $form, 'vertrouwelijk' => $vertrouwelijk];
}

/** De SEPA-machtigingstekst (letterlijk zoals op het inschrijfformulier). */
function lidmaatschap_machtigingstekst(): string {
    return 'Hierbij machtig ik Reddingsbrigade Paal 13 tot schriftelijke wederopzegging om jaarlijks het bedrag van de verenigingscontributie, zwemgelden, opleidingskosten en eventuele overige verenigingskosten van mijn rekening af te schrijven. Minimaal 14 dagen van te voren wordt er een factuur over het te betalen bedrag aan mij verzonden. Het terugboekingsrecht is 56 dagen na incasso.';
}

/** IBAN + tenaamstelling uit de kluis (alleen voor de PDF en de ledenservice-mail). */
function lidmaatschap_vertrouwelijk(array $rec): array {
    if (empty($rec['vertrouwelijk']) || !function_exists('kluis_ontsleutel')) return [];
    $j = kluis_ontsleutel((string)$rec['vertrouwelijk'], 'ondertekening|' . (int)($rec['id'] ?? 0) . '|vertrouwelijk');
    $d = $j !== null ? json_decode($j, true) : null;
    return is_array($d) ? $d : [];
}

/** PDF-inhoud (huisstijl): alle inschrijfgegevens + machtiging + ondertekenblok. */
function lidmaatschap_pdf_bouwen($pdf, array $rec, array $sol): void {
    $tx = 'ondertekening_pdf_tekst'; $NAVY = [5, 77, 138]; $ORANJE = [212, 61, 0]; $GRIJS = [90, 100, 120];
    $form = json_decode((string)($rec['formulier_data'] ?? ''), true) ?: [];
    $geheim = lidmaatschap_vertrouwelijk($rec);
    $pdf->AddPage();
    $logo = __DIR__ . '/../assets/img/logo-mail.png';
    if (is_file($logo)) { $pdf->Image($logo, 20, 15, 55); $pdf->Ln(22); } else { $pdf->Ln(4); }
    $pdf->SetFont('helvetica', 'B', 16); $pdf->SetTextColor(...$NAVY);
    $pdf->Cell(0, 9, 'Inschrijving als ' . (($form['soort'] ?? '') === 'donateur' ? 'donateur' : 'lid') . ' van Reddingsbrigade Paal 13', 0, 1);
    $pdf->SetDrawColor(...$ORANJE); $pdf->SetLineWidth(0.6); $y = $pdf->GetY(); $pdf->Line(20, $y, 190, $y); $pdf->Ln(6);
    $kop = function (string $t) use ($pdf, $NAVY) { $pdf->Ln(2); $pdf->SetFont('helvetica', 'B', 12); $pdf->SetTextColor(...$NAVY); $pdf->Cell(0, 7, $t, 0, 1); };
    $veld = function (string $label, string $waarde) use ($pdf, $tx, $GRIJS) {
        $pdf->SetFont('helvetica', 'B', 10); $pdf->SetTextColor(...$GRIJS); $pdf->Cell(45, 6, $tx($label), 0, 0);
        $pdf->SetFont('helvetica', '', 10); $pdf->SetTextColor(30, 30, 30); $pdf->MultiCell(0, 6, $tx($waarde !== '' ? $waarde : '-'), 0, 'L');
    };
    $kop('Persoonlijke gegevens');
    $veld('Naam', trim(($form['voornaam'] ?? '') . ' ' . trim(($form['tussenvoegsel'] ?? '') . ' ' . ($form['achternaam'] ?? ''))));
    $veld('Geslacht', match ($form['geslacht'] ?? '') { 'man' => 'Man', 'vrouw' => 'Vrouw', 'geen' => 'Wil ik niet specificeren', default => '' });
    $veld('Geboortedatum', !empty($form['geboortedatum']) ? datum_nl($form['geboortedatum']) : '');
    $veld('Geboorteplaats', (string)($form['geboorteplaats'] ?? ''));
    $veld('Adres', trim(($form['straat'] ?? '') . ' ' . ($form['huisnummer'] ?? '')) . ', ' . ($form['postcode'] ?? '') . ' ' . ($form['woonplaats'] ?? ''));
    $veld('Telefoon', (string)($form['telefoon'] ?? ''));
    $veld('E-mail', (string)($form['email'] ?? ''));
    $o = $form['ouder'] ?? [];
    if (!empty($o['voornaam']) || !empty($o['email'])) {
        $kop('Ouder / verzorger');
        $veld('Naam', trim(($o['voornaam'] ?? '') . ' ' . ($o['achternaam'] ?? '')));
        $veld('E-mail', (string)($o['email'] ?? '')); $veld('Telefoon', (string)($o['telefoon'] ?? ''));
    }
    $kop('Diploma\'s en licenties');
    $sleutels = lidmaatschap_diploma_sleutels(); $dip = [];
    foreach ((array)($form['diplomas'] ?? []) as $k) if (isset($sleutels[$k])) $dip[] = $sleutels[$k];
    $pdf->SetFont('helvetica', '', 10); $pdf->SetTextColor(30, 30, 30); $pdf->MultiCell(0, 6, $tx($dip ? implode(', ', $dip) : 'Geen'), 0, 'L');
    $kop('Betaling');
    $veld('Wijze', ($form['betaling'] ?? '') === 'incasso' ? 'Automatische incasso' : 'Overschrijving');
    if (($form['betaling'] ?? '') === 'incasso') {
        $veld('IBAN', !empty($geheim['iban']) ? iban_opmaak((string)$geheim['iban']) : '(niet beschikbaar)');
        $veld('Ten name van', (string)($geheim['tenaamstelling'] ?? ''));
        $pdf->Ln(1); $pdf->SetFont('helvetica', 'I', 9.5); $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell(0, 5, $tx('Machtiging: ' . lidmaatschap_machtigingstekst()), 0, 'L');
    }
    $kop('Verklaringen');
    $pdf->SetFont('helvetica', '', 10); $pdf->SetTextColor(30, 30, 30);
    $pdf->MultiCell(0, 5.5, '[X] Ik ga ermee akkoord dat de vereniging mijn ingediende informatie opslaat.', 0, 'L');
    $pdf->MultiCell(0, 5.5, '[X] Ik verklaar dat de bovenstaande gegevens volledig zijn en kloppen.', 0, 'L');
    if (function_exists('ondertekening_pdf_ondertekenblok')) ondertekening_pdf_ondertekenblok($pdf, $rec);
}

/** E-mailadres van de ledenservice (instelling; terugval bestuur). */
function lidmaatschap_email(): string {
    $e = function_exists('instelling') ? trim(instelling('mail.ledenservice_email', '')) : '';
    return $e !== '' ? $e : (function_exists('bestuur_email') ? bestuur_email() : '');
}

/**
 * Na het ondertekenen: inschrijving naar de ledenservice mailen (verzegelde PDF als bijlage, gegevens in de
 * mail). Hier komt later ook de hub-koppeling. Faalt stil (gelogd); de ondertekening zelf is al rond.
 */
function lidmaatschap_na_ondertekening(array $rec, array $sol, ?string $pdf_bytes): void {
    if (!function_exists('melding_verstuur')) return;
    $email = lidmaatschap_email();
    if ($email === '') { error_log('lidmaatschap: geen ledenservice-adres'); return; }
    $form = json_decode((string)($rec['formulier_data'] ?? ''), true) ?: [];
    $geheim = lidmaatschap_vertrouwelijk($rec);
    $sleutels = lidmaatschap_diploma_sleutels(); $dip = [];
    foreach ((array)($form['diplomas'] ?? []) as $k) if (isset($sleutels[$k])) $dip[] = $sleutels[$k];
    $rij = fn(string $l, string $w): string => '<tr><td style="padding:4px 10px 4px 0;color:#5a6478;white-space:nowrap">' . h($l) . '</td><td style="padding:4px 0">' . h($w !== '' ? $w : '-') . '</td></tr>';
    $o = $form['ouder'] ?? [];
    $pad = null; $bijlagen = [];
    if ($pdf_bytes !== null) {
        $pad = sys_get_temp_dir() . '/lid_' . (int)$rec['id'] . '_' . bin2hex(random_bytes(4)) . '.pdf';
        if (@file_put_contents($pad, $pdf_bytes) !== false) $bijlagen = [['pad' => $pad, 'naam' => 'Inschrijving_' . preg_replace('/[^A-Za-z0-9]+/', '_', ($form['voornaam'] ?? '') . '_' . ($form['achternaam'] ?? '')) . '_Paal13.pdf', 'mime' => 'application/pdf']];
        else $pad = null;
    }
    /* Het volledige IBAN staat in de verzegelde PDF (bijlage). In de mailtekst, die ook in de
       wachtrij en mailboxen blijft staan, alleen de laatste vier tekens. Zonder bijlage (PDF
       mislukt) blijft het volledig staan, anders kan de ledenservice niet incasseren. */
    $iban = !empty($geheim['iban']) ? iban_opmaak((string)$geheim['iban']) : '';
    $iban_mail = $iban === '' ? '-' : ($bijlagen ? 'eindigt op ' . substr(preg_replace('/\s+/', '', $iban), -4) . ' (volledig in de bijlage)' : $iban);
    $html = '<table cellpadding="0" cellspacing="0" style="font-size:14px">'
        . $rij('Naam', trim(($form['voornaam'] ?? '') . ' ' . trim(($form['tussenvoegsel'] ?? '') . ' ' . ($form['achternaam'] ?? ''))))
        . $rij('Geslacht', match ($form['geslacht'] ?? '') { 'man' => 'Man', 'vrouw' => 'Vrouw', 'geen' => 'Wil ik niet specificeren', default => '' })
        . $rij('Geboren', (!empty($form['geboortedatum']) ? datum_nl($form['geboortedatum']) : '') . ' te ' . ($form['geboorteplaats'] ?? ''))
        . $rij('Adres', trim(($form['straat'] ?? '') . ' ' . ($form['huisnummer'] ?? '')) . ', ' . ($form['postcode'] ?? '') . ' ' . ($form['woonplaats'] ?? ''))
        . $rij('Telefoon', (string)($form['telefoon'] ?? '')) . $rij('E-mail', (string)($form['email'] ?? ''))
        . (!empty($o['voornaam']) ? $rij('Ouder/verzorger', trim(($o['voornaam'] ?? '') . ' ' . ($o['achternaam'] ?? '')) . ' · ' . ($o['email'] ?? '') . ' · ' . ($o['telefoon'] ?? '')) : '')
        . $rij('Wil worden', ($form['soort'] ?? '') === 'donateur' ? 'Donateur' : 'Lid')
        . $rij('Diploma\'s', $dip ? implode(', ', $dip) : 'Geen')
        . $rij('Betaling', ($form['betaling'] ?? '') === 'incasso' ? 'Automatische incasso (machtiging ondertekend)' : 'Overschrijving')
        . (($form['betaling'] ?? '') === 'incasso' ? $rij('IBAN', $iban_mail) . $rij('Ten name van', (string)($geheim['tenaamstelling'] ?? '')) : '')
        . '</table>';
    try {
        melding_verstuur('ledenservice_inschrijving', (int)$rec['sollicitatie_id'], ['gegevens_html' => $html, 'lid_soort' => ($form['soort'] ?? '') === 'donateur' ? 'donateur' : 'lid'],
            ['ontvanger' => ['email' => $email, 'naam' => 'Ledenservice', 'telefoon' => '', 'rol' => 'direct'], 'prioriteit' => 'na_antwoord', 'dedup_min' => 0,
             'referentie' => 'lidmaatschap:' . (int)$rec['id'], 'bijlagen' => $bijlagen]);
        if (function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis((int)$rec['id'], 'verstuurd_ledenservice', ['naar' => $email]);
    } catch (\Throwable $e) { error_log('lidmaatschap_na_ondertekening: ' . $e->getMessage()); }
    // Verzending gebeurt ná het antwoord (shutdown). Die shutdown-functie is tijdens
    // melding_verstuur() geregistreerd, deze daarna — dus het bestand gaat pas weg als de mail weg is.
    if ($pad) register_shutdown_function(function () use ($pad) { @unlink($pad); });
}

} // function_exists

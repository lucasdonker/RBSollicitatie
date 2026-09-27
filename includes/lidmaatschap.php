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
 *
 * Onder 18 (de ondertekeningsgrens; bewust los van de aanmeld-/AVG-grens van 16) tekent de ouder/verzorger:
 * hetzelfde type 'lidmaatschap', maar met ondertekenaar_rol 'ouder' — dus dezelfde ouderroute als de
 * gezondheidsverklaring (token-link per mail, "Wacht op ouder" in het portaal, kopie-PDF naar de ouder).
 * De ouder vult de gegevens van het lid aan, geeft bij incasso de machtiging als rekeninghouder en tekent
 * als wettelijk vertegenwoordiger. lidmaatschap_ondertekenaar_verzekeren() houdt de rol per sollicitant
 * goed (ook voor documenten die al klaarstonden) en raakt een getekende inschrijving nooit aan.
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
        'lid_relatie'        => '',
        'lid_soort'          => 'lid',
        'lid_diplomas'       => lidmaatschap_diplomas_uit_tekst((string)($blk['diplomas'] ?? '')),
    ];
}

/** Relatie van de ondertekenende ouder/verzorger tot het lid: waarde => label. */
function lidmaatschap_relaties(): array {
    return ['ouder' => 'Ouder', 'verzorger' => 'Verzorger', 'voogd' => 'Voogd'];
}

/**
 * POST valideren. Retour ['form' => [...], 'vertrouwelijk' => ['iban'=>..,'tenaamstelling'=>..] | [],
 * 'veld' => naam van het veld waar de fout bij hoort ('' = geen)]. Zet $fout bij een probleem.
 * $door_ouder: de ouder/verzorger vult in en tekent (lid < 18); dan u-vorm en relatie verplicht.
 */
function lidmaatschap_post_valideren(array $post, bool $kluis_ok, string &$fout, ?int $leeftijd = null, bool $door_ouder = false): array {
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
    if ($door_ouder) {
        // Wie tekent en in welke hoedanigheid: vastgelegd in formulier_data (en de relatie ook als ondertekenaar_relatie).
        $form['relatie'] = array_key_exists($post['lid_relatie'] ?? '', lidmaatschap_relaties()) ? (string)$post['lid_relatie'] : '';
        $form['getekend_door'] = 'ouder';
    }
    $minderjarig = $door_ouder || ($leeftijd !== null && $leeftijd < 18);
    $iban = strtoupper(preg_replace('/\s+/', '', $t('lid_iban'))); $tnv = $t('lid_tenaamstelling');
    // Eén fout tegelijk, met het veld waar hij bij hoort (daar komt de melding + aria-invalid).
    $u = $door_ouder;
    $o = $form['ouder'];
    $leeg_adres = ['lid_straat' => $form['straat'], 'lid_huisnummer' => $form['huisnummer'], 'lid_postcode' => $form['postcode'], 'lid_woonplaats' => $form['woonplaats']];
    $regels = [
        ['lid_voornaam',   $form['voornaam'] === '', $u ? 'Vul de voornaam van het lid in.' : 'Vul je voornaam in.'],
        ['lid_achternaam', $form['achternaam'] === '', $u ? 'Vul de achternaam van het lid in.' : 'Vul je achternaam in.'],
        ['lid_geslacht',   $form['geslacht'] === '', $u ? 'Kies het geslacht van het lid (of "wil ik niet specificeren").' : 'Kies je geslacht (of "wil ik niet specificeren").'],
        ['lid_geboortedatum', !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['geboortedatum']), 'Vul de geboortedatum in.'],
        ['lid_geboorteplaats', $form['geboorteplaats'] === '', 'Vul de geboorteplaats in.'],
        [(string)array_search('', $leeg_adres, true), in_array('', $leeg_adres, true), 'Vul straat, huisnummer, postcode (1234 AB) en woonplaats in.'],
        ['lid_telefoon',   preg_match_all('/\d/', $form['telefoon']) < 8, 'Vul een geldig telefoonnummer in (minstens 8 cijfers).'],
        ['lid_email',      !filter_var($form['email'], FILTER_VALIDATE_EMAIL), 'Vul een geldig e-mailadres in.'],
        ['lid_ouder_voornaam',  $minderjarig && $o['voornaam'] === '', $u ? 'Vul uw voornaam in.' : 'Vul de voornaam van je ouder of verzorger in.'],
        ['lid_ouder_achternaam', $minderjarig && $o['achternaam'] === '', $u ? 'Vul uw achternaam in.' : 'Vul de achternaam van je ouder of verzorger in.'],
        ['lid_ouder_email', $minderjarig && !filter_var($o['email'], FILTER_VALIDATE_EMAIL), $u ? 'Vul een geldig e-mailadres van uzelf in.' : 'Vul een geldig e-mailadres van je ouder of verzorger in.'],
        ['lid_relatie',    $u && ($form['relatie'] ?? '') === '', 'Kies uw relatie tot het lid (ouder, verzorger of voogd).'],
        ['lid_soort',      $form['soort'] === '', $u ? 'Kies of het lid lid of donateur wordt.' : 'Kies of je lid of donateur wilt worden.'],
        ['lid_betaling',   $form['betaling'] === '', $u ? 'Kies hoe u wilt betalen (overschrijving of automatische incasso).' : 'Kies hoe je wilt betalen (overschrijving of automatische incasso).'],
        ['lid_betaling',   $form['betaling'] === 'incasso' && !$kluis_ok, 'De beveiligde opslag is tijdelijk niet beschikbaar; kies "overschrijving" of probeer het later opnieuw.'],
        ['lid_iban',       $form['betaling'] === 'incasso' && !iban_geldig($iban), 'Het IBAN klopt niet. Controleer het rekeningnummer.'],
        ['lid_tenaamstelling', $form['betaling'] === 'incasso' && $tnv === '', 'Vul in op wiens naam de rekening staat.'],
        ['lid_machtiging', $form['betaling'] === 'incasso' && !$form['machtiging'], $u ? 'Vink de machtiging aan: als rekeninghouder machtigt u de vereniging voor de automatische incasso.' : 'Vink de machtiging voor automatische incasso aan.'],
        ['lid_akkoord_opslag', !$form['akkoord_opslag'], 'Vink deze akkoordverklaring aan.'],
        ['lid_akkoord_juist',  !$form['akkoord_juist'], 'Vink deze akkoordverklaring aan.'],
    ];
    $veld = '';
    foreach ($regels as $r) { if ($r[1]) { $fout = $r[2]; $veld = $r[0]; break; } }
    $vertrouwelijk = ($fout === '' && $form['betaling'] === 'incasso') ? ['iban' => $iban, 'tenaamstelling' => $tnv] : [];
    return ['form' => $form, 'vertrouwelijk' => $vertrouwelijk, 'veld' => $veld];
}

/**
 * De SEPA-machtigingstekst. Zelf (18+): letterlijk zoals op het inschrijfformulier. Door de ouder: dezelfde
 * machtiging, maar uitdrukkelijk als rekeninghouder en voor het lidmaatschap van het kind.
 */
function lidmaatschap_machtigingstekst(bool $door_ouder = false, string $lid_naam = ''): string {
    if ($door_ouder) {
        return 'Hierbij machtig ik, als rekeninghouder, Reddingsbrigade Paal 13 tot schriftelijke wederopzegging om jaarlijks het bedrag van de verenigingscontributie, zwemgelden, opleidingskosten en eventuele overige verenigingskosten voor het lidmaatschap van '
             . ($lid_naam !== '' ? $lid_naam : 'mijn kind') . ' van deze rekening af te schrijven. Minimaal 14 dagen van tevoren wordt er een factuur over het te betalen bedrag aan mij verzonden. Het terugboekingsrecht is 56 dagen na incasso.';
    }
    return 'Hierbij machtig ik Reddingsbrigade Paal 13 tot schriftelijke wederopzegging om jaarlijks het bedrag van de verenigingscontributie, zwemgelden, opleidingskosten en eventuele overige verenigingskosten van mijn rekening af te schrijven. Minimaal 14 dagen van te voren wordt er een factuur over het te betalen bedrag aan mij verzonden. Het terugboekingsrecht is 56 dagen na incasso.';
}

/** De twee akkoordverklaringen onderaan het formulier: sleutel => tekst (je-vorm of, voor de ouder, u-vorm). */
function lidmaatschap_akkoordteksten(bool $door_ouder = false, string $lid_naam = ''): array {
    if ($door_ouder) {
        $wie = $lid_naam !== '' ? $lid_naam : 'mijn kind';
        return [
            'opslag' => 'Ik ga ermee akkoord dat de vereniging de ingediende gegevens van ' . $wie . ' en van mij opslaat.',
            'juist'  => 'Ik verklaar dat de bovenstaande gegevens volledig zijn en kloppen.',
        ];
    }
    return [
        'opslag' => 'Ik ga ermee akkoord dat de vereniging mijn ingediende informatie opslaat.',
        'juist'  => 'Ik verklaar dat de bovenstaande gegevens volledig zijn en kloppen.',
    ];
}

/** Volledige naam van het lid uit formulier_data. */
function lidmaatschap_lid_naam(array $form): string {
    return trim(($form['voornaam'] ?? '') . ' ' . trim(($form['tussenvoegsel'] ?? '') . ' ' . ($form['achternaam'] ?? '')));
}

/** Getekend door de ouder/verzorger (wettelijk vertegenwoordiger)? */
function lidmaatschap_door_ouder(array $rec): bool {
    return ($rec['ondertekenaar_rol'] ?? '') === 'ouder';
}

/** "Naam (ouder/verzorger, wettelijk vertegenwoordiger van X)" voor PDF en ledenservice-mail. */
function lidmaatschap_ondertekenaar_omschrijving(array $rec, array $form): string {
    $rel = lidmaatschap_relaties()[$form['relatie'] ?? ''] ?? 'Ouder/verzorger';
    return trim((string)($rec['ondertekenaar_naam'] ?? '')) . ' (' . strtolower($rel) . ', wettelijk vertegenwoordiger van ' . lidmaatschap_lid_naam($form) . ')';
}

/**
 * Foutmelding bij een veld (ondertekenscherm): attributen voor het invoerelement en de melding zelf.
 * Leeg als dit veld niet het foutveld is. Naar het voorbeeld van includes/aanmeldformulier.php.
 */
function ondertekening_veldfout_attr(string $veld, string $fout_veld): string {
    return $veld !== '' && $veld === $fout_veld ? ' aria-invalid="true" aria-describedby="ondFout-' . h($veld) . '"' : '';
}
function ondertekening_veldfout_html(string $veld, string $fout_veld, string $fout): string {
    if ($veld === '' || $veld !== $fout_veld || $fout === '') return '';
    return '<span class="ond-veldfout" id="ondFout-' . h($veld) . '" role="alert">' . icoon('let-op', 14) . '<span>' . h($fout) . '</span></span>';
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
    $door_ouder = lidmaatschap_door_ouder($rec);
    $lid_naam = lidmaatschap_lid_naam($form);
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
    if ($door_ouder) {
        // Meteen bovenaan: het lid is minderjarig; de ouder/verzorger heeft als wettelijk vertegenwoordiger getekend.
        $pdf->SetFont('helvetica', '', 10); $pdf->SetTextColor(30, 30, 30);
        $pdf->MultiCell(0, 5.5, $tx('Het lid is minderjarig. Deze inschrijving is ingevuld en ondertekend door ' . lidmaatschap_ondertekenaar_omschrijving($rec, $form) . '.'), 0, 'L');
        $pdf->Ln(1);
    }
    $kop($door_ouder ? 'Gegevens van het lid' : 'Persoonlijke gegevens');
    $veld('Naam', $lid_naam);
    $veld('Geslacht', match ($form['geslacht'] ?? '') { 'man' => 'Man', 'vrouw' => 'Vrouw', 'geen' => 'Wil ik niet specificeren', default => '' });
    $veld('Geboortedatum', !empty($form['geboortedatum']) ? datum_nl($form['geboortedatum']) : '');
    $veld('Geboorteplaats', (string)($form['geboorteplaats'] ?? ''));
    $veld('Adres', trim(($form['straat'] ?? '') . ' ' . ($form['huisnummer'] ?? '')) . ', ' . ($form['postcode'] ?? '') . ' ' . ($form['woonplaats'] ?? ''));
    $veld('Telefoon', (string)($form['telefoon'] ?? ''));
    $veld('E-mail', (string)($form['email'] ?? ''));
    $o = $form['ouder'] ?? [];
    if (!empty($o['voornaam']) || !empty($o['email'])) {
        $kop($door_ouder ? 'Ouder / verzorger (wettelijk vertegenwoordiger)' : 'Ouder / verzorger');
        $veld('Naam', trim(($o['voornaam'] ?? '') . ' ' . ($o['achternaam'] ?? '')));
        if (!empty($form['relatie'])) $veld('Relatie tot het lid', lidmaatschap_relaties()[$form['relatie']] ?? (string)$form['relatie']);
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
        if ($door_ouder) $veld('Machtiging gegeven door', trim((string)($rec['ondertekenaar_naam'] ?? '')) . ' (rekeninghouder, of namens het lid als wettelijk vertegenwoordiger)');
        $pdf->Ln(1); $pdf->SetFont('helvetica', 'I', 9.5); $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell(0, 5, $tx('Machtiging: ' . lidmaatschap_machtigingstekst($door_ouder, $lid_naam)), 0, 'L');
    }
    $kop('Verklaringen');
    $pdf->SetFont('helvetica', '', 10); $pdf->SetTextColor(30, 30, 30);
    if ($door_ouder && function_exists('ondertekening_akkoord_tekst')) {
        $pdf->MultiCell(0, 5.5, $tx('[X] ' . ondertekening_akkoord_tekst('lidmaatschap', true, $lid_naam)), 0, 'L');
    }
    foreach (lidmaatschap_akkoordteksten($door_ouder, $lid_naam) as $tekst) $pdf->MultiCell(0, 5.5, $tx('[X] ' . $tekst), 0, 'L');
    if (function_exists('ondertekening_pdf_ondertekenblok')) ondertekening_pdf_ondertekenblok($pdf, $rec);
    if ($door_ouder) $veld('Hoedanigheid', 'Ouder/verzorger, wettelijk vertegenwoordiger van ' . $lid_naam);
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
    $door_ouder = lidmaatschap_door_ouder($rec);
    $html = ($door_ouder
            ? '<p style="margin:0 0 12px"><strong>Minderjarig lid.</strong> Ingeschreven en ondertekend door ' . h(lidmaatschap_ondertekenaar_omschrijving($rec, $form)) . '.'
              . (($form['betaling'] ?? '') === 'incasso' ? ' De incassomachtiging is door deze ondertekenaar gegeven als rekeninghouder (tenaamstelling hieronder).' : '') . '</p>'
            : '')
        . '<table cellpadding="0" cellspacing="0" style="font-size:14px">'
        . $rij('Naam', lidmaatschap_lid_naam($form))
        . $rij('Geslacht', match ($form['geslacht'] ?? '') { 'man' => 'Man', 'vrouw' => 'Vrouw', 'geen' => 'Wil ik niet specificeren', default => '' })
        . $rij('Geboren', (!empty($form['geboortedatum']) ? datum_nl($form['geboortedatum']) : '') . ' te ' . ($form['geboorteplaats'] ?? ''))
        . $rij('Adres', trim(($form['straat'] ?? '') . ' ' . ($form['huisnummer'] ?? '')) . ', ' . ($form['postcode'] ?? '') . ' ' . ($form['woonplaats'] ?? ''))
        . $rij('Telefoon', (string)($form['telefoon'] ?? '')) . $rij('E-mail', (string)($form['email'] ?? ''))
        . (!empty($o['voornaam']) ? $rij('Ouder/verzorger', trim(($o['voornaam'] ?? '') . ' ' . ($o['achternaam'] ?? '')) . ' · ' . ($o['email'] ?? '') . ' · ' . ($o['telefoon'] ?? '')) : '')
        . $rij('Wil worden', ($form['soort'] ?? '') === 'donateur' ? 'Donateur' : 'Lid')
        . $rij('Diploma\'s', $dip ? implode(', ', $dip) : 'Geen')
        . $rij('Betaling', ($form['betaling'] ?? '') === 'incasso' ? 'Automatische incasso (machtiging ondertekend)' : 'Overschrijving')
        . (($form['betaling'] ?? '') === 'incasso' ? $rij('IBAN', $iban_mail) . $rij('Ten name van', (string)($geheim['tenaamstelling'] ?? '')) : '')
        . $rij('Ondertekend door', $door_ouder ? lidmaatschap_ondertekenaar_omschrijving($rec, $form) : trim((string)($rec['ondertekenaar_naam'] ?? '')) . ' (het lid zelf)')
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

// ── Wie tekent: sollicitant (18+) of ouder/verzorger (<18) ──────────────────────

/** Persoonsgegevens die de route bepalen: geboortedatum, e-mail, ouder_naam, ouder_email. Null als onbekend. */
function lidmaatschap_route_persoon(int $sol_id): ?array {
    if (function_exists('personen_ouder_auto_setup')) personen_ouder_auto_setup();
    $q = db()->prepare("SELECT p.geboortedatum, p.email, p.ouder_naam, p.ouder_email FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE s.id=?");
    $q->execute([$sol_id]);
    $p = $q->fetch() ?: null;
    // Het eigen adres van de sollicitant telt niet als ouderadres: dan zou de minderjarige zelf "als
    // wettelijk vertegenwoordiger" tekenen, inclusief de SEPA-machtiging. Behandel het als onbekend.
    if ($p && trim((string)$p['ouder_email']) !== '' && strcasecmp(trim((string)$p['ouder_email']), trim((string)$p['email'])) === 0) {
        $p['ouder_email'] = '';
    }
    return $p;
}

/** Moet de ouder/verzorger de inschrijving tekenen? (geboortedatum → leeftijd; grens 18) */
function lidmaatschap_ouder_nodig(array $persoon): bool {
    $lft = function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($persoon['geboortedatum'] ?? null) : null;
    return function_exists('ondertekening_ouder_tekent') ? ondertekening_ouder_tekent('lidmaatschap', $lft) : ($lft !== null && $lft < 18);
}

/**
 * Het ondertekenverzoek voor de inschrijving naar de ouder/verzorger. Gebruikt het eigen sjabloon
 * 'lidmaatschap_verzoek_ouder' als dat in het register staat; anders het algemene ouderverzoek met deze
 * tekst als override (het algemene sjabloon gaat over de selectiedag). Retour: status van melding_verstuur.
 */
function lidmaatschap_ouder_verzoek_versturen(int $sol_id, int $ond_id, string $token, string $email, string $naam): string {
    if (!function_exists('melding_verstuur') || $email === '') return 'overgeslagen';
    $label  = function_exists('ondertekening_label') ? ondertekening_label('lidmaatschap') : 'Inschrijving lidmaatschap vereniging';
    $url    = function_exists('ondertekening_url') ? ondertekening_url($token) : '';
    $links  = [$label => $url];
    $extra  = ['formulieren_blok' => function_exists('ondertekening_formulieren_blok') ? ondertekening_formulieren_blok($links) : '',
               'document_naam' => $label, 'onderteken_url' => $url, 'ouder_naam' => $naam !== '' ? $naam : 'ouder/verzorger'];
    $opties = ['referentie' => 'ondertekening:' . $ond_id, 'prioriteit' => 'na_antwoord',
               'ontvanger' => ['email' => $email, 'naam' => $naam !== '' ? $naam : 'ouder/verzorger', 'telefoon' => '', 'rol' => 'ouder']];
    $eigen = function_exists('meldingen_register') && isset(meldingen_register()['lidmaatschap_verzoek_ouder']);
    if (!$eigen) $opties['override'] = lidmaatschap_ouder_verzoek_tekst();
    try {
        $r = melding_verstuur($eigen ? 'lidmaatschap_verzoek_ouder' : 'ondertekening_verzoek_ouder', $sol_id, $extra, $opties);
        return (string)($r['status'] ?? 'mislukt');
    } catch (\Throwable $e) { error_log('lidmaatschap_ouder_verzoek_versturen: ' . $e->getMessage()); return 'mislukt'; }
}

/** Tekst van het ondertekenverzoek aan de ouder (ook de seed voor het sjabloon 'lidmaatschap_verzoek_ouder'). */
function lidmaatschap_ouder_verzoek_tekst(): array {
    return [
        'onderwerp'     => 'Handtekening nodig: inschrijving van {{voornaam}} als lid — Reddingsbrigade Paal 13',
        'hero_titel'    => 'Handtekening gevraagd',
        'hero_subtitel' => 'Lidmaatschap van {{voornaam}}',
        'body_html'     => "<p>Beste {{ouder_naam}},</p>\n<p><strong>{{voornaam}}</strong> is aangenomen als strandwacht bij Reddingsbrigade Paal 13. Strandwachten worden lid van de vereniging. Omdat {{voornaam}} nog geen 18 is, schrijft u {{voornaam}} als ouder/verzorger in en tekent u namens {{voornaam}}. De gegevens zijn grotendeels al ingevuld.</p>\n<p>Kiest u voor automatische incasso van de contributie, dan geeft u in hetzelfde formulier de machtiging als rekeninghouder. Het rekeningnummer wordt versleuteld bewaard en alleen gedeeld met de ledenservice.</p>\n{{formulieren_blok}}\n<p>Invullen en ondertekenen duurt ongeveer 5 minuten. U hoeft geen account aan te maken.</p>\n<p>Heeft u vragen? Mail ons gerust via <a href=\"mailto:{{bestuur_email}}\">{{bestuur_email}}</a>.</p>\n<p>Met vriendelijke groet,<br><strong>Reddingsbrigade Paal 13</strong><br>Callantsoog</p>",
    ];
}

/**
 * Houdt de ondertekenaar van een nog NIET getekende inschrijving goed (idempotent, ook het vangnet voor
 * documenten die al klaarstonden):
 *  - <18 en rol 'sollicitant' → omzetten naar rol 'ouder' met het ouderadres, nieuwe token (de oude link
 *    was van de sollicitant en mag niet als ouder-link werken), en de ouder mailen zodra het adres bekend is;
 *  - <18, rol 'ouder' zonder adres, en het ouderadres is intussen bekend → adres invullen, nieuwe token, mailen;
 *  - 18+ en rol 'ouder' (inmiddels 18 geworden) → terug naar zelf tekenen, nieuwe token (ouderlink vervalt).
 * Een getekende inschrijving wordt nooit aangeraakt.
 * Retour: 'geen' (nog geen inschrijving) | 'getekend' | 'ok' | 'omgezet' | 'wacht_op_ouder' (<18 zonder ouderadres) | 'fout'.
 */
function lidmaatschap_ondertekenaar_verzekeren(int $sol_id): string {
    try {
        $db = db();
        $q = $db->prepare("SELECT id, token, ondertekenaar_rol, ondertekenaar_email, ondertekend_op FROM ondertekeningen WHERE sollicitatie_id=? AND type='lidmaatschap' ORDER BY id DESC LIMIT 1");
        $q->execute([$sol_id]);
        $r = $q->fetch();
        if (!$r) return 'geen';
        if (!empty($r['ondertekend_op'])) return 'getekend';
        $p = lidmaatschap_route_persoon($sol_id);
        // Zonder (geldige) geboortedatum niets omzetten: dan weten we niet wie moet tekenen.
        if (!$p || !function_exists('leeftijd_uit_datum') || leeftijd_uit_datum($p['geboortedatum'] ?? null) === null) return 'ok';
        $is_ouder = ($r['ondertekenaar_rol'] ?? '') === 'ouder';
        $token    = bin2hex(random_bytes(24));
        $wijzig   = function (string $rol, ?string $naam, ?string $email) use ($db, $r, $token): bool {
            // Alleen als de rij nog precies zo is als gelezen (geen dubbele omzetting/mail bij gelijktijdige requests)
            $u = $db->prepare("UPDATE ondertekeningen SET ondertekenaar_rol=?, ondertekenaar_naam=?, ondertekenaar_email=?, token=? WHERE id=? AND token=? AND ondertekend_op IS NULL");
            $u->execute([$rol, $naam, $email, $token, (int)$r['id'], (string)$r['token']]);
            return $u->rowCount() > 0;
        };
        if (lidmaatschap_ouder_nodig($p)) {
            $email = trim((string)($p['ouder_email'] ?? ''));
            $naam  = trim((string)($p['ouder_naam'] ?? ''));
            // Staat er al een ouderadres op de rij, dan is dat leidend (het portaal corrigeert het via
            // portaal_ouder_lidmaatschap_versturen()); alleen een leeg adres vullen we aan uit de persoon.
            if ($is_ouder && trim((string)$r['ondertekenaar_email']) !== '') return 'ok';
            if ($is_ouder && $email === '') return 'wacht_op_ouder';
            if (!$wijzig('ouder', $naam !== '' ? $naam : null, $email !== '' ? $email : null)) return 'ok';
            if (function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis((int)$r['id'], 'ondertekenaar_gewijzigd', ['rol' => 'ouder', 'reden' => $is_ouder ? 'ouderadres aangevuld' : 'minderjarig: ouder/verzorger tekent']);
            if (function_exists('audit_log')) { try { audit_log('lidmaatschap_naar_ouder', 'sollicitatie', $sol_id, ['ondertekening' => (int)$r['id']]); } catch (\Throwable $e) {} }
            if ($email === '') return 'wacht_op_ouder';
            lidmaatschap_ouder_verzoek_versturen($sol_id, (int)$r['id'], $token, $email, $naam);
            return 'omgezet';
        }
        if (!$is_ouder) return 'ok';
        $eigen = trim((string)($p['email'] ?? ''));
        if (!$wijzig('sollicitant', null, $eigen !== '' ? $eigen : null)) return 'ok';
        if (function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis((int)$r['id'], 'ondertekenaar_gewijzigd', ['rol' => 'sollicitant', 'reden' => '18 geworden: tekent zelf']);
        return 'omgezet';
    } catch (\Throwable $e) { error_log('lidmaatschap_ondertekenaar_verzekeren: ' . $e->getMessage()); return 'fout'; }
}

/**
 * Nieuwe inschrijving voor een minderjarige: rol 'ouder', ouder mailen als het adres bekend is.
 * Zonder adres staat de rij zonder e-mail klaar; het portaal vraagt dan de oudergegevens (portaal_ouder_docs)
 * en lidmaatschap_ondertekenaar_verzekeren() mailt zodra ze er zijn. Retour: 'aangemaakt' | 'fout'.
 */
function lidmaatschap_ouder_aanmaken(int $sol_id, array $persoon): string {
    try {
        $db = db();
        $email = trim((string)($persoon['ouder_email'] ?? '')); $naam = trim((string)($persoon['ouder_naam'] ?? ''));
        $token = bin2hex(random_bytes(24));
        $db->prepare("INSERT INTO ondertekeningen (sollicitatie_id, type, token, ondertekenaar_rol, ondertekenaar_naam, ondertekenaar_email) VALUES (?,'lidmaatschap',?,'ouder',?,?)")
           ->execute([$sol_id, $token, $naam !== '' ? $naam : null, $email !== '' ? $email : null]);
        $id = (int)$db->lastInsertId();
        if (function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis($id, 'aangemaakt', ['type' => 'lidmaatschap', 'rol' => 'ouder']);
        if ($email !== '') lidmaatschap_ouder_verzoek_versturen($sol_id, $id, $token, $email, $naam);
        return 'aangemaakt';
    } catch (\Throwable $e) { error_log('lidmaatschap_ouder_aanmaken: ' . $e->getMessage()); return 'fout'; }
}

} // function_exists

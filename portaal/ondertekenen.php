<?php
/**
 * Digitale ondertekening — Portaal.dc sectie 05 (18+: zelf tekenen, in het portaal) en 09
 * (ouder/verzorger via een persoonlijke link, buiten het portaal: geen inlog, geen navigatie).
 *
 * Toegang via de unieke token in de URL. Twee routes, nooit beide:
 *   1) Sollicitant zelf (ondertekenaar_rol = sollicitant): opent vanuit zijn taken; ingelogd → met
 *      portaal-navigatie, anders (link uit de mail) alleen het document.
 *   2) Ouder/verzorger (ondertekenaar_rol = ouder): alleen de token uit de mail is nodig. Ook de inschrijving
 *      als lid (type lidmaatschap) van een sollicitant onder 18: de ouder vult in, machtigt als rekeninghouder
 *      en tekent als wettelijk vertegenwoordiger (includes/lidmaatschap.php).
 * Na ondertekenen: bevestiging + PDF-kopie (?pdf=1, alleen voor een ondertekend document).
 *
 * Veldnamen en opslag zijn ongewijzigd (formulier_data-JSON per type); alleen de presentatie is nieuw.
 * Documentteksten en -namen: includes/ondertekening_tekst.php. Velden: _ondertekenen_velden.php.
 * Canvas + validatie: assets/js/ondertekenen.js. Stijl: het "ONDERTEKENEN"-blok (.ond-*) in stijl.css.
 */
require_once __DIR__ . '/../includes/functions.php';
sessie_start();

$db = db();
// Zorg dat de ondertekeningen-tabel bestaat (op een verse database komt die anders alleen via migratie)
if (function_exists('ondertekeningen_auto_setup')) ondertekeningen_auto_setup();
$token = trim($_GET['token'] ?? '');

// Zonder token bestaat deze pagina niet. Documenten zet het systeem zelf klaar
// (ondertekeningen_auto.php); een sollicitant kan er geen starten via de URL.
if ($token === '') pagina_404();

// ── Record + sollicitant ophalen ─────────────────────────────
$q = $db->prepare("SELECT * FROM ondertekeningen WHERE token = ?");
$q->execute([$token]);
$rec = $q->fetch();
$sol = null;
if ($rec) {
    $s_q = $db->prepare("SELECT s.id, s.fase, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum, p.email, p.telefoon, p.ouder_telefoon, sz.naam AS seizoen_naam
                         FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id JOIN seizoenen sz ON sz.id = s.seizoen_id WHERE s.id = ?");
    $s_q->execute([$rec['sollicitatie_id']]);
    $sol = $s_q->fetch() ?: null;
}
$contact = portaal_contact_email();

if (!$rec || !$sol) {
    // Ongeldige of verlopen link (ontwerp 10: systeemstaat, nooit een technische code als kop)
    http_response_code(404);
    $portaal_titel     = 'Link niet gevonden';
    $portaal_geen_nav  = true;
    $portaal_merk_naam = 'Reddingsbrigade Paal 13';
    require __DIR__ . '/_kop.php';
    ?>
  <div class="pt-leeg groot ond-ongeldig">
    <span class="pt-leeg-icoon warn"><?= icoon('klok', 26) ?></span>
    <span class="pt-leeg-titel">Deze link werkt niet meer</span>
    <span class="pt-leeg-tekst">De link is ongeldig of niet meer geldig. Vraag zo nodig een nieuwe aan bij het bestuur.</span>
    <span class="pt-hulp">Lukt het niet? Mail <a href="mailto:<?= h($contact) ?>"><?= h($contact) ?></a></span>
  </div>
    <?php
    require __DIR__ . '/_voet.php';
    exit;
}

$type           = (string)$rec['type'];
$is_ouder       = $rec['ondertekenaar_rol'] === 'ouder' || ondertekening_is_ouder_type($type);
$is_gezondheid  = ondertekening_is_gezondheid($type);
$is_intake      = !$is_gezondheid;
$is_ondertekend = !empty($rec['ondertekend_op']);
$voornaam       = (string)$sol['voornaam'];
$naam_sol       = trim($voornaam . ' ' . trim(($sol['tussenvoegsel'] ?? '') . ' ' . $sol['achternaam']));
$leeftijd       = function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($sol['geboortedatum'] ?? null) : null;
$titel_kort     = ondertekening_titel_kort($type);

// ── Lidmaatschap: tekent de juiste persoon? Onder 18 de ouder/verzorger, vanaf 18 de sollicitant zelf.
//    Klopt de rol van deze (nog niet getekende) inschrijving niet (klaargezet vóór deze regel, of het lid is
//    intussen 18), dan zet het vangnet hem om — met een nieuwe link voor de juiste ondertekenaar — en legt
//    dit scherm uit wat er gebeurt. Deze link is daarna niet meer geldig.
if ($type === 'lidmaatschap' && !$is_ondertekend && $leeftijd !== null
    && function_exists('lidmaatschap_ondertekenaar_verzekeren') && ondertekening_ouder_tekent($type, $leeftijd) !== $is_ouder) {
    $_stand = lidmaatschap_ondertekenaar_verzekeren((int)$rec['sollicitatie_id']);
    $_naar_ouder = !$is_ouder;
    $portaal_titel     = $titel_kort;
    $portaal_geen_nav  = true;
    $portaal_merk_naam = 'Reddingsbrigade Paal 13';
    require __DIR__ . '/_kop.php';
    ?>
  <div class="pt-leeg groot ond-ongeldig">
    <span class="pt-leeg-icoon"><?= icoon($_naar_ouder ? 'ouders' : 'vink', 26) ?></span>
    <?php if ($_naar_ouder): ?>
      <span class="pt-leeg-titel">Dit formulier tekent je ouder of verzorger</span>
      <span class="pt-leeg-tekst">Omdat je jonger bent dan 18, schrijft je ouder of verzorger je in als lid van de vereniging en tekent namens jou. <?= $_stand === 'wacht_op_ouder'
        ? 'Vul in je portaal de gegevens van je ouder of verzorger in; daarna krijgt die een mail met een eigen link.'
        : 'Je ouder of verzorger krijgt daarvoor een mail met een eigen link. In je portaal zie je wanneer het rond is.' ?></span>
      <a href="<?= h(APP_URL . '/portaal/taken.php') ?>" class="ds-btn ds-btn-primair">Naar mijn taken</a>
    <?php else: ?>
      <span class="pt-leeg-titel">U hoeft hier niets meer te doen</span>
      <span class="pt-leeg-tekst"><?= h($voornaam) ?> is inmiddels 18 en schrijft zich daarom zelf in als lid, via het eigen portaal. Deze link is niet meer geldig.</span>
    <?php endif; ?>
    <span class="pt-hulp">Vragen? Mail <a href="mailto:<?= h($contact) ?>"><?= h($contact) ?></a></span>
  </div>
    <?php
    require __DIR__ . '/_voet.php';
    exit;
}
$doc            = ondertekening_document($type, $voornaam, $is_ouder);
$eigen_ingelogd = !$is_ouder && portaal_ingelogd() && (int)($_SESSION['portaal_sol_id'] ?? 0) === (int)$rec['sollicitatie_id'];
$basis_url      = APP_URL . '/portaal/ondertekenen.php?token=' . urlencode($token);
$twee_fa        = !$is_ondertekend && function_exists('ondertekening_2fa_nodig') && ondertekening_2fa_nodig($type);
$werknemer_type = function_exists('ondertekening_is_werknemer_type') && ondertekening_is_werknemer_type($type); // loonheffing/lidmaatschap: BSN/IBAN → kluis verplicht
$kluis_ok       = !$werknemer_type || (function_exists('kluis_beschikbaar') && kluis_beschikbaar());
$sol_pdf        = ['voornaam' => $voornaam, 'tussenvoegsel' => (string)($sol['tussenvoegsel'] ?? ''), 'achternaam' => (string)$sol['achternaam'], 'geboortedatum' => $sol['geboortedatum'] ?? '', 'email' => (string)($sol['email'] ?? '')];

// ── PDF-kopie (alleen van een ondertekend document; de token is de sleutel). Verzegelde PDF uit de kluis. ──
if (isset($_GET['pdf']) && $is_ondertekend && function_exists('ondertekening_pdf_stream')) {
    if (function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis((int)$rec['id'], 'pdf_gedownload', ['door' => $is_ouder ? 'ouder' : 'sollicitant']);
    ondertekening_pdf_stream($rec, $sol_pdf, ondertekening_pdf_bestandsnaam($type));
}
if (!$is_ondertekend && function_exists('ondertekening_geopend_loggen') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    ondertekening_geopend_loggen((int)$rec['id'], $is_ouder ? 'ouder' : 'sollicitant');
}

// ── POST: verwerken ondertekening (logica ongewijzigd) ───────
$fout = ''; $fout_veld = ''; // $fout_veld: naam van het veld waar de fout bij hoort (melding + aria-invalid daar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_ondertekend) {
    csrf_check();
    $naam_getypt  = trim($_POST['naam_getypt'] ?? '');
    $plaats       = trim($_POST['plaats'] ?? '');
    $akkoord      = !empty($_POST['akkoord']);
    $handtekening = $_POST['handtekening_beeld'] ?? '';
    // Valideer de handtekening-data-URL (PNG, redelijke grootte)
    if ($handtekening !== '' && !preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $handtekening)) {
        $handtekening = '';
    }
    if (strlen($handtekening) > 600000) $handtekening = ''; // te groot → negeren

    // 2FA: code die vlak hiervoor naar het eigen nummer is gestuurd. Zowel het kanaal als het gebruikte
    // nummer/adres komen in het bewijs, zodat later herleidbaar is welk nummer is geverifieerd.
    $zekerheid = 'link'; $zekerheid_naar = null;
    if ($twee_fa) {
        $bewijs_2fa = ondertekening_code_controleren((int)$rec['id'], $is_ouder ? (string)$rec['ondertekenaar_email'] : (string)$sol['email'], (string)($_POST['code'] ?? ''));
        if ($bewijs_2fa === null) $fout = 'De verificatiecode klopt niet of is verlopen. Vraag een nieuwe code aan en vul die in.';
        else { $zekerheid = $bewijs_2fa['kanaal']; $zekerheid_naar = $bewijs_2fa['naar'] !== '' ? $bewijs_2fa['naar'] : null; }
    }

    // Typespecifieke validatie (loonheffing: BSN 11-proef enz.; lidmaatschap: IBAN bij incasso)
    $lh = []; $lid = [];
    if ($fout === '' && $type === 'loonheffing') {
        if (!$kluis_ok) $fout = 'De beveiligde opslag is tijdelijk niet beschikbaar; je BSN kan nu niet veilig bewaard worden. Probeer het later opnieuw.';
        else {
            $lh = [
                'achternaam' => trim($_POST['lh_achternaam'] ?? ''), 'voorletters' => trim($_POST['lh_voorletters'] ?? ''),
                'adres' => trim($_POST['lh_adres'] ?? ''), 'postcode' => postcode_opmaak($_POST['lh_postcode'] ?? '') ?: '',
                'woonplaats' => trim($_POST['lh_woonplaats'] ?? ''), 'geboortedatum' => trim($_POST['lh_geboortedatum'] ?? ''),
                'korting' => in_array($_POST['lh_korting'] ?? '', ['ja', 'nee'], true) ? $_POST['lh_korting'] : '',
                'vanaf' => trim($_POST['lh_vanaf'] ?? ''), 'begrip' => !empty($_POST['lh_begrip']),
            ];
            $bsn = bsn_schoon($_POST['lh_bsn'] ?? '');
            if ($lh['achternaam'] === '' || $lh['voorletters'] === '') $fout = 'Vul je achternaam en voorletters in.';
            elseif (!bsn_geldig($bsn))                                 $fout = 'Het burgerservicenummer klopt niet (9 cijfers, 11-proef). Controleer het op je identiteitsbewijs.';
            elseif ($lh['adres'] === '' || $lh['postcode'] === '' || $lh['woonplaats'] === '') $fout = 'Vul je straat, huisnummer, postcode (1234 AB) en woonplaats in.';
            elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lh['geboortedatum'])) $fout = 'Vul je geboortedatum in.';
            elseif ($lh['korting'] === '')                             $fout = 'Kies of Paal 13 de loonheffingskorting mag toepassen.';
            elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lh['vanaf'])) $fout = 'Vul de datum in vanaf wanneer je keuze geldt.';
            elseif (!$lh['begrip'])                                    $fout = 'Vink aan dat je begrijpt dat de loonheffingskorting maar bij één werkgever mag.';
        }
    }
    if ($fout === '' && $type === 'lidmaatschap' && function_exists('lidmaatschap_post_valideren')) {
        $lid = lidmaatschap_post_valideren($_POST, $kluis_ok, $fout, $leeftijd, $is_ouder);
        $fout_veld = (string)($lid['veld'] ?? '');
    }

    if ($fout !== '') { /* type- of code-fout gaat voor */ }
    elseif (!$akkoord)                   { $fout = 'Vink het akkoord aan.'; $fout_veld = 'akkoord'; }
    elseif (strlen($naam_getypt) < 3)    { $fout = ($is_ouder ? 'Vul uw' : 'Vul je') . ' volledige naam in ter ondertekening.'; $fout_veld = 'naam_getypt'; }
    elseif (!$plaats)                    { $fout = 'Vul de plaats van ondertekening in.'; $fout_veld = 'plaats'; }
    elseif ($handtekening === '')        { $fout = ($is_ouder ? 'Zet uw' : 'Zet je') . ' handtekening in het vak.'; $fout_veld = 'handtekening'; }
    else {
        // Verzamel form-data (verschilt per type). Vertrouwelijke waarden (BSN, IBAN) gaan apart de kluis in.
        $form = []; $vertrouwelijk = null;
        $ond_geboortedatum = false; $ond_geboorteplaats = false; // false = uit $form halen (hieronder)
        if ($type === 'loonheffing') {
            $form = $lh + ['geslacht' => '', 'aow' => '', 'sjabloon_versie' => loonheffing_sjabloon_versie(), 'sjabloon_sha256' => loonheffing_sjabloon_sha256()];
            $vertrouwelijk = kluis_versleutel(json_encode(['bsn' => $bsn]), 'ondertekening|' . (int)$rec['id'] . '|vertrouwelijk');
            if ($vertrouwelijk === null) { $fout = 'Versleutelen mislukt; probeer het later opnieuw.'; }
        } elseif ($type === 'lidmaatschap') {
            $form = $lid['form'] ?? [];
            // Tekent de ouder, dan zijn geboortedatum/-plaats die van het lid, niet van de ondertekenaar
            // (die kolommen blijven dan leeg); de relatie gaat wél mee als ondertekenaar_relatie.
            if ($is_ouder) { $ond_geboortedatum = null; $ond_geboorteplaats = null; }
            if (!empty($lid['vertrouwelijk'])) {
                $vertrouwelijk = kluis_versleutel(json_encode($lid['vertrouwelijk']), 'ondertekening|' . (int)$rec['id'] . '|vertrouwelijk');
                if ($vertrouwelijk === null) { $fout = 'Versleutelen mislukt; probeer het later opnieuw.'; }
            }
        } elseif ($is_gezondheid) {
            $form['geslacht']       = $_POST['geslacht'] ?? '';
            $form['geboorteplaats'] = trim($_POST['geboorteplaats'] ?? '');
            $form['geboortedatum']  = $_POST['geboortedatum'] ?? '';
            if ($type === 'gezondheid_ouder') $form['relatie'] = $_POST['relatie'] ?? '';
            // Optionele aandoeningen (checkboxes)
            $form['aandoeningen'] = [
                'diabetes'     => !empty($_POST['a_diabetes']),
                'breuken'      => !empty($_POST['a_breuken']),
                'hart_vaat'    => !empty($_POST['a_hartvaat']),
                'ademhaling'   => !empty($_POST['a_ademhaling']),
                'zenuwstelsel' => !empty($_POST['a_zenuwstelsel']),
                'gehoororgaan' => !empty($_POST['a_gehoor']),
                'overig'       => trim($_POST['a_overig'] ?? ''),
                // Sinds 2.128.2 twee losse vragen; eerder was het één vinkje "diabetes of breuken".
                'los_breuken'  => true,
            ];
        } else {
            $form['geslacht'] = $_POST['geslacht'] ?? '';
            if ($type === 'intake_ouder') $form['relatie'] = $_POST['relatie'] ?? '';
            $form['geboortedatum'] = $_POST['geboortedatum'] ?? '';
            // 1. Gezondheid & veiligheid
            $form['gezondheid_keuze']       = $_POST['gezondheid_keuze'] ?? '';
            $form['gezondheid_toelichting'] = trim($_POST['gezondheid_toelichting'] ?? '');
            // 2. Foto's beleid (optioneel)
            $form['foto_toestemming'] = !empty($_POST['foto_toestemming']);
            // 3. Verklaring & aansprakelijkheid
            $form['verklaring'] = [
                'naar_waarheid'    => !empty($_POST['v_waarheid']),
                'goede_gezondheid' => !empty($_POST['v_gezondheid']),
                'eigen_risico'     => !empty($_POST['v_eigenrisico']),
                'leeftijd_18'      => !empty($_POST['v_leeftijd']),
            ];
        }

        if ($fout === '') {
        if (function_exists('ondertekening_bewijs_auto_setup')) ondertekening_bewijs_auto_setup();
        $db->prepare("UPDATE ondertekeningen SET
                        ondertekenaar_naam=?, ondertekenaar_geboortedatum=?, ondertekenaar_geboorteplaats=?, ondertekenaar_relatie=?,
                        formulier_data=?, vertrouwelijk=?, plaats=?, handtekening_beeld=?, zekerheid=?, zekerheid_naar=?,
                        ondertekend_op=NOW(), ondertekend_ip=?, ondertekend_user_agent=?,
                        geldig_tot=" . ($is_gezondheid ? 'DATE_ADD(CURDATE(), INTERVAL 1 YEAR)' : 'NULL') . "
                     WHERE token=?")
           ->execute([
                $naam_getypt,
                $ond_geboortedatum !== false ? $ond_geboortedatum : (($form['geboortedatum'] ?? '') !== '' ? $form['geboortedatum'] : null),
                $ond_geboorteplaats !== false ? $ond_geboorteplaats : ($form['geboorteplaats'] ?? null),
                $form['relatie'] ?? null,
                json_encode($form, JSON_UNESCAPED_UNICODE),
                $vertrouwelijk,
                $plaats,
                $handtekening,
                $zekerheid,
                $zekerheid_naar,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                $token,
           ]);
        $sol_id_o = (int)$rec['sollicitatie_id'];
        $ond_id   = (int)$rec['id'];

        // Bewijs: gebeurtenis + audit + bewijs-hash over het vastgelegde record
        $vers = $db->prepare("SELECT * FROM ondertekeningen WHERE token=?");
        $vers->execute([$token]);
        $rec_vers = $vers->fetch() ?: $rec;
        if (function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis($ond_id, 'ondertekend', ['rol' => $is_ouder ? 'ouder' : 'sollicitant', 'zekerheid' => $zekerheid] + ($zekerheid_naar !== null ? ['naar' => $zekerheid_naar] : []));
        if (function_exists('audit_log')) { try { audit_log('ondertekening_afgerond', 'ondertekening', $ond_id, ['type' => $type, 'rol' => $is_ouder ? 'ouder' : 'sollicitant', 'zekerheid' => $zekerheid, 'zekerheid_naar' => $zekerheid_naar]); } catch (\Throwable $e) {} }
        if (function_exists('ondertekening_bewijs_hash')) {
            $rec_vers['bewijs_hash'] = ondertekening_bewijs_hash($rec_vers);
            try { $db->prepare("UPDATE ondertekeningen SET bewijs_hash=? WHERE id=?")->execute([$rec_vers['bewijs_hash'], $ond_id]); } catch (\Throwable $e) {}
        }

        // Verzegelde PDF éénmaal maken (inhoud + certificaatpagina + digitaal zegel) en versleuteld bewaren
        $pdf_bytes = null;
        if (function_exists('ondertekening_pdf_bytes')) {
            try {
                $pdf_bytes = ondertekening_pdf_bytes($rec_vers, $sol_pdf);
                if ($pdf_bytes !== null && function_exists('document_opslaan_inhoud')) {
                    $opslag = document_opslaan_inhoud($sol_id_o, document_soort_ondertekening($type), $pdf_bytes, ondertekening_pdf_bestandsnaam($type), 'application/pdf', null, $_SERVER['REMOTE_ADDR'] ?? null);
                    if ($opslag['ok']) {
                        $db->prepare("UPDATE ondertekeningen SET pdf_document_id=?, pdf_sha256=? WHERE id=?")->execute([$opslag['id'], hash('sha256', $pdf_bytes), $ond_id]);
                        if (function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis($ond_id, 'pdf_verzegeld', ['sha256' => substr(hash('sha256', $pdf_bytes), 0, 16), 'zegel' => function_exists('zegel_pdf_is_verzegeld') && zegel_pdf_is_verzegeld($pdf_bytes) ? 'ja' : 'nee']);
                    } else { error_log('ondertekening PDF opslaan: ' . $opslag['fout']); }
                }
            } catch (\Throwable $e) { error_log('ondertekening PDF: ' . $e->getMessage()); }
        }

        // Meldingen via de centrale kern: bevestiging aan de sollicitant, kopie (PDF) aan de
        // ondertekenaar, en optioneel een seintje aan het bestuur. Fouten blokkeren nooit.
        if (function_exists('melding_verstuur')) {
            $doc_naam = ondertekening_label($type);
            $ref      = 'ondertekening:' . $ond_id;
            $pdf_pad  = null; $bijlagen = [];
            try {
                if ($pdf_bytes !== null) {
                    $pdf_pad = sys_get_temp_dir() . '/ond_' . $ond_id . '_' . bin2hex(random_bytes(4)) . '.pdf';
                    if (@file_put_contents($pdf_pad, $pdf_bytes) === false) $pdf_pad = null;
                } elseif (function_exists('ondertekening_pdf_genereer')) {
                    $pdf_pad = ondertekening_pdf_genereer($rec_vers, $sol_pdf);
                }
                if ($pdf_pad) $bijlagen = [['pad' => $pdf_pad, 'naam' => 'Ondertekend_' . ondertekening_pdf_bestandsnaam($type), 'mime' => 'application/pdf']];
            } catch (\Throwable $e) { error_log('ondertekening PDF: ' . $e->getMessage()); }
            try {
                // Sollicitant: bevestiging. Zelf getekend → "je hebt ondertekend" mét PDF-kopie;
                // door de ouder getekend → "je ouder heeft ondertekend" (de PDF gaat naar de ouder).
                /* Ná het antwoord versturen. Dit is het langste wachtmoment in het hele
                   portaal: je hebt net getekend en staat te kijken naar een pagina die
                   niets doet terwijl er een PDF van een paar honderd kB door de
                   mailserver gaat. De bevestiging op het scherm is niet afhankelijk van
                   de uitkomst van die mail. */
                melding_verstuur($is_ouder ? 'ondertekening_bevestiging_ouder' : 'ondertekening_bevestiging', $sol_id_o,
                    ['document_naam' => $doc_naam, 'ondertekenaar_naam' => $naam_getypt ?: (string)($rec['ondertekenaar_naam'] ?? 'je ouder/verzorger')],
                    ['referentie' => $ref, 'prioriteit' => 'na_antwoord', 'bijlagen' => $is_ouder ? [] : $bijlagen]);
                // Ouder: kopie met PDF
                if ($is_ouder && !empty($rec['ondertekenaar_email'])) {
                    melding_verstuur('ondertekening_kopie', $sol_id_o,
                        ['document_naam' => $doc_naam, 'ontvanger_naam' => $naam_getypt ?: ($rec['ondertekenaar_naam'] ?: 'ouder/verzorger')],
                        ['referentie' => $ref, 'prioriteit' => 'na_antwoord', 'bijlagen' => $bijlagen,
                         'ontvanger' => ['email' => $rec['ondertekenaar_email'], 'naam' => $naam_getypt ?: (string)$rec['ondertekenaar_naam'], 'telefoon' => '', 'rol' => 'ouder']]);
                }
                // Bestuur (standaard uit)
                melding_verstuur('bestuur_ondertekening_afgerond', $sol_id_o,
                    ['document_naam' => $doc_naam, 'ontvanger_naam' => $is_ouder ? 'ouder/verzorger' : 'de sollicitant'],
                    ['referentie' => $ref, 'prioriteit' => 'queue']);
            } catch (\Throwable $e) { error_log('ondertekening meldingen: ' . $e->getMessage()); }

            /* Het tijdelijke PDF-bestand mag pas weg als de mail het heeft opgepakt.
               De verzending gebeurt nu ná het antwoord (melding_flush, ook een
               shutdown-functie), dus een @unlink hier zou het bestand weghalen vóórdat
               de bijlage wordt gelezen — en dan komt de mail zonder PDF aan.
               Shutdown-functies draaien op volgorde van registratie en melding_flush is
               al geregistreerd, dus deze komt erna. */
            if ($pdf_pad) {
                $_pdf_weg = $pdf_pad;
                register_shutdown_function(function () use ($_pdf_weg) { @unlink($_pdf_weg); });
            }
        }
        // Lidmaatschap: inschrijving naar de ledenservice (later ook de hub)
        if ($type === 'lidmaatschap' && function_exists('lidmaatschap_na_ondertekening')) { try { lidmaatschap_na_ondertekening($rec_vers, $sol_pdf, $pdf_bytes); } catch (\Throwable $e) { error_log('lidmaatschap na ondertekening: ' . $e->getMessage()); } }
        // Dossier compleet richting de penningmeester? (loonheffing + ID + bankpas)
        if (function_exists('documenten_dossier_compleet_check')) { try { documenten_dossier_compleet_check($sol_id_o); } catch (\Throwable $e) {} }

        redirect($basis_url . '&klaar=1' . (portaal_terug_param() === 'taken' ? '&terug=taken' : ''));
        } // geen fout
    }
}

// ── Weergave ─────────────────────────────────────────────────
$na_fout = $fout !== '';
$std = [
    'naam_getypt'   => $is_ouder ? (string)($rec['ondertekenaar_naam'] ?? '') : $naam_sol,
    'geboortedatum' => $is_ouder ? '' : (string)($sol['geboortedatum'] ?? ''),
];
if ($werknemer_type) {
    // Vooringevuld uit het dossier (persoon, BLK-adres, seizoen); de sollicitant controleert en vult aan
    $dossier = function_exists('portaal_sollicitant_laden') ? (portaal_sollicitant_laden((int)$rec['sollicitatie_id']) ?: []) : [];
    $blk = function_exists('blk_van') ? (blk_van((int)$rec['sollicitatie_id']) ?: []) : [];
    if (empty($blk['straat']) && !empty($blk['adres']) && function_exists('blk_adres_splitsen')) $blk = array_merge($blk, array_filter(blk_adres_splitsen($blk['adres'])));
    $std += [
        'lh_achternaam'    => trim((($sol['tussenvoegsel'] ?? '') ? $sol['tussenvoegsel'] . ' ' : '') . $sol['achternaam']),
        'lh_voorletters'   => naam_voorletters($voornaam),
        'lh_adres'         => trim(($blk['straat'] ?? '') . ' ' . ($blk['huisnummer'] ?? '')),
        'lh_postcode'      => (string)($blk['postcode'] ?? ''),
        'lh_woonplaats'    => (string)($blk['woonplaats'] ?? ''),
        'lh_geboortedatum' => (string)($sol['geboortedatum'] ?? ''),
        'lh_vanaf'         => (string)($dossier['datum_werk_start'] ?? ''),
    ];
    if (function_exists('lidmaatschap_prefill')) $std += lidmaatschap_prefill($sol, $blk, $dossier);
}
$v   = fn(string $k): string => h((string)($na_fout ? ($_POST[$k] ?? '') : ($std[$k] ?? '')));
$aan = fn(string $k, string $waarde = '1'): string => ($na_fout && (string)($_POST[$k] ?? '') === $waarde) ? ' checked' : '';
$sel = fn(string $k, string $waarde): string => ($na_fout && (string)($_POST[$k] ?? '') === $waarde) ? ' selected' : '';
$u   = $is_ouder; // aanspreekvorm: ouder = u, sollicitant = je
$ondertekend_op = $is_ondertekend ? strtotime($rec['ondertekend_op']) : null;
$maanden = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
$ondertekend_tekst = $ondertekend_op ? 'Ondertekend ' . date('j', $ondertekend_op) . ' ' . $maanden[(int)date('n', $ondertekend_op) - 1] . ' om ' . date('H:i', $ondertekend_op) : '';

$portaal_titel = $titel_kort;
if ($eigen_ingelogd) {
    $portaal_terug = portaal_terug_url();
} else {
    $portaal_geen_nav  = true;
    $portaal_merk_naam = 'Reddingsbrigade Paal 13';
    $portaal_merk_sub  = $is_ouder ? 'Ondertekenen voor ' . $naam_sol : (string)$sol['seizoen_naam'];
}
require __DIR__ . '/_kop.php';
?>

<?php if ($is_ondertekend): ?>
  <!-- Na ondertekenen (ontwerp 09: bevestiging met kopie) -->
  <div class="ond-klaar">
    <span class="ond-klaar-icoon"><?= icoon('vink', 30) ?></span>
    <span class="ond-klaar-titel">Bedankt, ondertekend</span>
    <?php if (!empty($rec['pdf_sha256'])): ?>
      <span class="ond-klaar-zegel"><?= icoon('schild', 14) ?>Digitaal verzegeld · controlehash <?= h(substr((string)$rec['pdf_sha256'], 0, 12)) ?>…</span>
    <?php endif; ?>
    <span class="ond-klaar-tekst">
      <?php if ($is_ouder): ?>
        <?= h($voornaam) ?> ziet in het portaal dat dit rond is.<?= !empty($rec['ondertekenaar_email']) ? ' U krijgt een kopie per e-mail.' : '' ?>
      <?php else: ?>
        Dit document is rond. Je krijgt een kopie per e-mail; in je portaal staat de taak op afgerond.
      <?php endif; ?>
      <?php /* En wat er nu gebeurt — anders eindigt het hier zonder dat je weet of er
               nog iets van je wordt verwacht. */ ?>
      <?php if ($eigen_ingelogd && function_exists('portaal_hierna')):
        $_s_na = portaal_sollicitant_laden((int)$rec['sollicitatie_id']);
        if ($_s_na):
          $_o_na = function_exists('portaal_taken')
            ? count(portaal_taken_open(portaal_taken($_s_na, blk_van((int)$rec['sollicitatie_id'])))) : 0;
      ?>
        <span class="ond-klaar-hierna"><?= h(portaal_hierna($_s_na, $_o_na)) ?></span>
      <?php endif; endif; ?>
    </span>
    <a class="ond-bestand" href="<?= h($basis_url) ?>&amp;pdf=1" target="_blank" rel="noopener">
      <span class="ond-bestand-icoon"><?= icoon('document', 20) ?></span>
      <span class="ond-bestand-tekst">
        <span class="ond-bestand-naam"><?= h(ondertekening_pdf_bestandsnaam($type)) ?></span>
        <span class="ond-bestand-sub"><?= h($ondertekend_tekst) ?><?= !empty($rec['ondertekenaar_naam']) ? ' door ' . h($rec['ondertekenaar_naam']) : '' ?></span>
      </span>
      <?= icoon('download', 18) ?>
    </a>
    <?php if ($eigen_ingelogd): ?>
      <a href="<?= h(portaal_terug_url()) ?>" class="ds-btn ds-btn-primair ond-klaar-knop">Terug naar het portaal</a>
    <?php endif; ?>
    <span class="pt-hulp">Vragen? Mail <a href="mailto:<?= h($contact) ?>"><?= h($contact) ?></a></span>
  </div>

<?php else: ?>
  <form method="POST" class="ond<?= $is_ouder ? ' ouder' : '' ?>" id="ondForm" onsubmit="return ondValideer(this)" novalidate>
    <?= csrf_input() ?>
    <input type="hidden" name="terug" value="<?= h(portaal_terug_param()) ?>">

    <!-- Kop: titel + status (mobiel: witte balk onder de topbar; desktop: kaartkop met chip) -->
    <header class="ond-kop">
      <div class="ond-kop-titel">
        <span class="ond-kop-naam"><?= h($titel_kort) ?></span>
        <span class="ond-kop-sub"><?= $eigen_ingelogd ? 'Nog te ondertekenen' : h($naam_sol) . ' · ' . h($sol['seizoen_naam']) ?></span>
      </div>
      <span class="ond-chip"><span class="ond-chip-stip"></span>Nog te ondertekenen</span>
    </header>

    <div class="ond-body">
      <div class="ond-links">
        <?php if ($is_ouder): ?>
          <div class="ond-intro">
            <span class="ond-intro-titel"><?= h($titel_kort) ?></span>
            <span class="ond-intro-tekst"><?= h(ondertekening_ouder_intro($type, $voornaam, $leeftijd)) ?></span>
          </div>
        <?php endif; ?>

        <?php if ($na_fout): ?>
          <?php /* Staat de fout ook bij het veld (dat heeft role=alert), dan is dit de samenvatting zonder tweede aankondiging. */ ?>
          <div class="ds-melding ds-melding-error" id="ondFoutmelding"<?= $fout_veld === '' ? ' role="alert"' : '' ?>><?= icoon('let-op', 18) ?> <div><?= h($fout) ?></div></div>
        <?php elseif (!$kluis_ok): ?>
          <div class="ds-melding ds-melding-warn" role="alert"><?= icoon('let-op', 18) ?> <div>De beveiligde opslag is tijdelijk niet beschikbaar. Dit formulier kan nu niet ondertekend worden; probeer het later opnieuw.</div></div>
        <?php endif; ?>

        <!-- Het document (ingeklapt met vervaging; "Volledig document lezen" klapt uit) -->
        <div class="ond-doc dicht" id="ondDoc">
          <span class="ond-doc-titel"><?= h($doc['titel']) ?></span>
          <?php foreach ($doc['alineas'] as $alinea): ?>
            <span class="ond-doc-alinea"><?= h($alinea) ?></span>
          <?php endforeach; ?>
          <div class="ond-doc-vervaag"></div>
        </div>
        <span class="ond-doc-meer">Volledig document · <button type="button" id="ondDocToggle" data-open="Inklappen" data-dicht="Alles lezen">Alles lezen</button></span>

        <?php require __DIR__ . '/_ondertekenen_velden.php'; ?>
      </div>

      <div class="ond-rechts">
        <!-- Handtekening -->
        <div class="ond-kaart ond-teken">
          <label class="ds-veld ond-veld<?= $fout_veld === 'naam_getypt' ? ' fout' : '' ?>">
            <span class="ond-lbl"><?= $u ? 'Uw naam' : 'Je naam' ?> <span class="ster">*</span></span>
            <input type="text" name="naam_getypt" class="ds-input" value="<?= $v('naam_getypt') ?>" autocomplete="name" required<?= ondertekening_veldfout_attr('naam_getypt', $fout_veld) ?>>
            <span class="ds-veld-hint"><?= $u ? 'Uw volledige naam, zoals op uw identiteitsbewijs.' : 'Je volledige naam, zoals op je identiteitsbewijs.' ?></span>
            <?= ondertekening_veldfout_html('naam_getypt', $fout_veld, $fout) ?>
          </label>
          <label class="ds-veld ond-veld<?= $fout_veld === 'plaats' ? ' fout' : '' ?>">
            <span class="ond-lbl">Plaats van ondertekening <span class="ster">*</span></span>
            <input type="text" name="plaats" class="ds-input" value="<?= $v('plaats') ?>" placeholder="Bijv. Callantsoog" autocomplete="address-level2" required<?= ondertekening_veldfout_attr('plaats', $fout_veld) ?>>
            <?= ondertekening_veldfout_html('plaats', $fout_veld, $fout) ?>
          </label>
          <div class="ond-teken-kop">
            <span class="ond-teken-titel"><?= $u ? 'Uw handtekening' : 'Jouw handtekening' ?>
              <?= info_bol('Waarom een handtekening?', ($u
                ? "Voor dit document is uw ondertekening nodig. De krabbel die u zet wordt vastgelegd samen met uw naam, de plaats, het tijdstip en het IP-adres waarmee u ondertekent.\nDaarna maken we er een pdf van die digitaal wordt verzegeld. Verandert er later iets in het document, dan meldt elke pdf-lezer dat het gewijzigd is. Zo geldt dit net als een handtekening op papier."
                : "Voor dit document is je ondertekening nodig. De krabbel die je zet wordt vastgelegd samen met je naam, de plaats, het tijdstip en het IP-adres waarmee je ondertekent.\nDaarna maken we er een pdf van die digitaal wordt verzegeld. Verandert er later iets in het document, dan meldt elke pdf-lezer dat het gewijzigd is. Zo geldt dit net als een handtekening op papier.\nJe krijgt de pdf per e-mail en kunt hem altijd terugvinden bij je taken.")) ?>
            </span>
            <button type="button" class="ond-teken-wis" id="hdtWis">Wissen</button>
          </div>
          <div class="ond-vak<?= $fout_veld === 'handtekening' ? ' fout' : '' ?>" id="hdtWrap">
            <canvas id="hdtCanvas" class="ond-canvas" aria-label="Handtekeningvak"<?= ondertekening_veldfout_attr('handtekening', $fout_veld) ?>></canvas>
            <span class="ond-vak-hint" id="hdtHint">Teken met <?= $u ? 'uw' : 'je' ?> vinger</span>
          </div>
          <?= ondertekening_veldfout_html('handtekening', $fout_veld, $fout) ?>
          <input type="hidden" name="handtekening_beeld" id="hdtData" value="<?= $v('handtekening_beeld') ?>">
          <?php if ($twee_fa): $tel_masker = ondertekening_tel_masker($is_ouder ? ($sol['ouder_telefoon'] ?? '') : ($sol['telefoon'] ?? '')); require __DIR__ . '/_ondertekenen_2fa.php'; endif; ?>
          <label class="ond-check<?= $fout_veld === 'akkoord' ? ' fout' : '' ?>">
            <input type="checkbox" name="akkoord" value="1"<?= $aan('akkoord') ?> required<?= ondertekening_veldfout_attr('akkoord', $fout_veld) ?>>
            <span class="ond-check-box"><?= icoon('vink', 14) ?></span>
            <span class="ond-check-tekst"><?= h(ondertekening_akkoord_tekst($type, $u, $voornaam)) ?></span>
          </label>
          <?= ondertekening_veldfout_html('akkoord', $fout_veld, $fout) ?>
        </div>

        <?php if (!$is_ouder): ?>
          <!-- Ondertekenaar (desktop) -->
          <div class="ond-ondertekenaar">
            <span class="ond-ondertekenaar-lbl">Ondertekenaar</span>
            <span class="ond-ondertekenaar-naam"><?= h($naam_sol) ?><?= $leeftijd !== null ? ' · ' . (int)$leeftijd . ' jaar' : '' ?></span>
            <span class="ond-ondertekenaar-sub"><?= h(ondertekening_zelf_uitleg($type)) ?></span>
          </div>
        <?php endif; ?>

        <div class="ond-info<?= $is_ouder ? ' navy' : '' ?>">
          <?= icoon('slot', $is_ouder ? 18 : 20) ?>
          <span><?= $is_ouder
            ? 'Deze link is persoonlijk en vervalt na ondertekening. U hoeft geen account te maken. We bewaren datum, tijd en uw IP-adres bij de handtekening.'
            : ($werknemer_type
              ? 'Je ondertekent dit zelf. We bewaren datum, tijd, je IP-adres en de verificatiecode-stap als bewijs; de PDF wordt digitaal verzegeld.'
              : 'Je bent 18 of ouder, dus je ondertekent zelf. We bewaren datum, tijd en je IP-adres bij de handtekening.') ?></span>
        </div>

        <footer class="ond-voet">
          <button type="submit" class="ond-knop">
            <span class="btn-tekst"><?= $is_ouder ? 'Ondertekenen' : 'Ondertekenen en versturen' ?></span>
            <span class="btn-spinner" hidden>Bezig met ondertekenen…</span>
          </button>
        </footer>
      </div>
    </div>
  </form>

  <script src="<?= APP_URL ?>/assets/js/ondertekenen.js?v=<?= @filemtime(__DIR__ . '/../assets/js/ondertekenen.js') ?>"></script>
<?php endif; ?>

<?php require __DIR__ . '/_voet.php'; ?>

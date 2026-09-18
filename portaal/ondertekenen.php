<?php
/**
 * Digitale ondertekening — Portaal.dc sectie 05 (18+: zelf tekenen, in het portaal) en 09
 * (ouder/verzorger via een persoonlijke link, buiten het portaal: geen inlog, geen navigatie).
 *
 * Toegang via de unieke token in de URL. Twee routes, nooit beide:
 *   1) Sollicitant zelf (ondertekenaar_rol = sollicitant): opent vanuit zijn taken; ingelogd → met
 *      portaal-navigatie, anders (link uit de mail) alleen het document.
 *   2) Ouder/verzorger (ondertekenaar_rol = ouder): alleen de token uit de mail is nodig.
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

if ($token === '') {
    // Zonder token: alleen een ingelogde sollicitant kan zelf een nieuw document starten
    $sol_id = portaal_vereis_login();
    $type = $_GET['nieuw'] ?? '';
    if (in_array($type, ['intake', 'gezondheid_18plus'], true)) {
        $nieuw_token = bin2hex(random_bytes(24));
        $db->prepare("INSERT INTO ondertekeningen (sollicitatie_id, type, token, ondertekenaar_rol) VALUES (?,?,?,'sollicitant')")
           ->execute([$sol_id, $type, $nieuw_token]);
        redirect(APP_URL . '/portaal/ondertekenen.php?token=' . $nieuw_token);
    }
    pagina_404();
}

// ── Record + sollicitant ophalen ─────────────────────────────
$q = $db->prepare("SELECT * FROM ondertekeningen WHERE token = ?");
$q->execute([$token]);
$rec = $q->fetch();
$sol = null;
if ($rec) {
    $s_q = $db->prepare("SELECT s.id, s.fase, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum, p.email, sz.naam AS seizoen_naam
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
$doc            = ondertekening_document($type, $voornaam);
$eigen_ingelogd = !$is_ouder && portaal_ingelogd() && (int)($_SESSION['portaal_sol_id'] ?? 0) === (int)$rec['sollicitatie_id'];
$basis_url      = APP_URL . '/portaal/ondertekenen.php?token=' . urlencode($token);

// ── PDF-kopie (alleen van een ondertekend document; de token is de sleutel) ──
if (isset($_GET['pdf']) && $is_ondertekend && function_exists('ondertekening_pdf_stream')) {
    ondertekening_pdf_stream($rec, [
        'voornaam'      => $voornaam,
        'achternaam'    => trim(($sol['tussenvoegsel'] ? $sol['tussenvoegsel'] . ' ' : '') . $sol['achternaam']),
        'geboortedatum' => $sol['geboortedatum'] ?? '',
    ], ondertekening_pdf_bestandsnaam($type));
}

// ── POST: verwerken ondertekening (logica ongewijzigd) ───────
$fout = '';
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

    if (!$akkoord)                       $fout = 'Vink het akkoord aan.';
    elseif (strlen($naam_getypt) < 3)    $fout = ($is_ouder ? 'Vul uw' : 'Vul je') . ' volledige naam in ter ondertekening.';
    elseif (!$plaats)                    $fout = ($is_ouder ? 'Vul uw' : 'Vul je') . ' woonplaats in.';
    elseif ($handtekening === '')        $fout = ($is_ouder ? 'Zet uw' : 'Zet je') . ' handtekening in het vak.';
    else {
        // Verzamel form-data (verschilt per type)
        $form = [];
        if ($is_gezondheid) {
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
                'leeftijd_16'      => !empty($_POST['v_leeftijd']),
            ];
        }

        $db->prepare("UPDATE ondertekeningen SET
                        ondertekenaar_naam=?, ondertekenaar_geboortedatum=?, ondertekenaar_geboorteplaats=?, ondertekenaar_relatie=?,
                        formulier_data=?, plaats=?, handtekening_beeld=?,
                        ondertekend_op=NOW(), ondertekend_ip=?, ondertekend_user_agent=?,
                        geldig_tot=DATE_ADD(CURDATE(), INTERVAL 1 YEAR)
                     WHERE token=?")
           ->execute([
                $naam_getypt,
                ($form['geboortedatum'] ?? '') !== '' ? $form['geboortedatum'] : null,
                $form['geboorteplaats'] ?? null,
                $form['relatie'] ?? null,
                json_encode($form, JSON_UNESCAPED_UNICODE),
                $plaats,
                $handtekening,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                $token,
           ]);

        // Meldingen via de centrale kern: bevestiging aan de sollicitant, kopie (PDF) aan de
        // ondertekenaar, en optioneel een seintje aan het bestuur. Fouten blokkeren nooit.
        if (function_exists('melding_verstuur')) {
            $sol_id_o = (int)$rec['sollicitatie_id'];
            $doc_naam = ondertekening_label($type);
            $ref      = 'ondertekening:' . (int)$rec['id'];
            $pdf_pad  = null; $bijlagen = [];
            try {
                if (function_exists('ondertekening_pdf_genereer')) {
                    $vers = $db->prepare("SELECT * FROM ondertekeningen WHERE token=?");
                    $vers->execute([$token]);
                    $rec_vers = $vers->fetch() ?: $rec;
                    $pdf_pad  = ondertekening_pdf_genereer($rec_vers, $sol);
                    if ($pdf_pad) $bijlagen = [['pad' => $pdf_pad, 'naam' => 'Ondertekend_' . ondertekening_pdf_bestandsnaam($type), 'mime' => 'application/pdf']];
                }
            } catch (\Throwable $e) { error_log('ondertekening PDF: ' . $e->getMessage()); }
            try {
                // Sollicitant: bevestiging (bij zelf tekenen mét PDF-kopie)
                melding_verstuur('ondertekening_bevestiging', $sol_id_o,
                    ['document_naam' => $doc_naam, 'door' => $is_ouder ? 'je ouder/verzorger' : 'jou'],
                    ['referentie' => $ref, 'prioriteit' => 'direct', 'bijlagen' => $is_ouder ? [] : $bijlagen]);
                // Ouder: kopie met PDF
                if ($is_ouder && !empty($rec['ondertekenaar_email'])) {
                    melding_verstuur('ondertekening_kopie', $sol_id_o,
                        ['document_naam' => $doc_naam, 'ontvanger_naam' => $naam_getypt ?: ($rec['ondertekenaar_naam'] ?: 'ouder/verzorger')],
                        ['referentie' => $ref, 'prioriteit' => 'direct', 'bijlagen' => $bijlagen,
                         'ontvanger' => ['email' => $rec['ondertekenaar_email'], 'naam' => $naam_getypt ?: (string)$rec['ondertekenaar_naam'], 'telefoon' => '', 'rol' => 'ouder']]);
                }
                // Bestuur (standaard uit)
                melding_verstuur('bestuur_ondertekening_afgerond', $sol_id_o,
                    ['document_naam' => $doc_naam, 'ontvanger_naam' => $is_ouder ? 'ouder/verzorger' : 'de sollicitant'],
                    ['referentie' => $ref, 'prioriteit' => 'queue']);
            } catch (\Throwable $e) { error_log('ondertekening meldingen: ' . $e->getMessage()); }
            if ($pdf_pad) @unlink($pdf_pad);
        }

        redirect($basis_url . '&klaar=1' . (portaal_terug_param() === 'taken' ? '&terug=taken' : ''));
    }
}

// ── Weergave ─────────────────────────────────────────────────
$na_fout = $fout !== '';
$std = [
    'naam_getypt'   => $is_ouder ? (string)($rec['ondertekenaar_naam'] ?? '') : $naam_sol,
    'geboortedatum' => $is_ouder ? '' : (string)($sol['geboortedatum'] ?? ''),
];
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
    <span class="ond-klaar-tekst">
      <?php if ($is_ouder): ?>
        <?= h($voornaam) ?> ziet in het portaal dat dit rond is.<?= !empty($rec['ondertekenaar_email']) ? ' U krijgt een kopie per e-mail.' : '' ?>
      <?php else: ?>
        Dit document is rond. Je krijgt een kopie per e-mail; in je portaal staat de taak op afgerond.
      <?php endif; ?>
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
            <span class="ond-intro-tekst">Uw kind <?= h($voornaam) ?><?= $leeftijd !== null ? ' (' . (int)$leeftijd . ')' : '' ?> meldt zich aan als strandwacht bij Reddingsbrigade Paal 13. Voor deelname is uw handtekening nodig.</span>
          </div>
        <?php endif; ?>

        <?php if ($na_fout): ?>
          <div class="ds-melding ds-melding-error" id="ondFoutmelding" role="alert"><?= icoon('let-op', 18) ?> <div><?= h($fout) ?></div></div>
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
          <label class="ds-veld ond-veld">
            <span class="ond-lbl"><?= $u ? 'Uw naam' : 'Je naam' ?> <span class="ster">*</span></span>
            <input type="text" name="naam_getypt" class="ds-input" value="<?= $v('naam_getypt') ?>" autocomplete="name" required>
            <span class="ds-veld-hint"><?= $u ? 'Uw volledige naam, zoals op uw identiteitsbewijs.' : 'Je volledige naam, zoals op je identiteitsbewijs.' ?></span>
          </label>
          <label class="ds-veld ond-veld">
            <span class="ond-lbl">Plaats van ondertekening <span class="ster">*</span></span>
            <input type="text" name="plaats" class="ds-input" value="<?= $v('plaats') ?>" placeholder="Bijv. Callantsoog" autocomplete="address-level2" required>
          </label>
          <div class="ond-teken-kop">
            <span class="ond-teken-titel"><?= $u ? 'Uw handtekening' : 'Jouw handtekening' ?></span>
            <button type="button" class="ond-teken-wis" id="hdtWis">Wissen</button>
          </div>
          <div class="ond-vak" id="hdtWrap">
            <canvas id="hdtCanvas" class="ond-canvas" aria-label="Handtekeningvak"></canvas>
            <span class="ond-vak-hint" id="hdtHint">Teken met <?= $u ? 'uw' : 'je' ?> vinger</span>
          </div>
          <input type="hidden" name="handtekening_beeld" id="hdtData" value="<?= $v('handtekening_beeld') ?>">
          <label class="ond-check">
            <input type="checkbox" name="akkoord" value="1"<?= $aan('akkoord') ?> required>
            <span class="ond-check-box"><?= icoon('vink', 14) ?></span>
            <span class="ond-check-tekst"><?= $u
              ? 'Ik heb het document gelezen en geef toestemming voor deelname.'
              : 'Ik heb het document gelezen en verklaar naar waarheid te hebben ondertekend.' ?></span>
          </label>
        </div>

        <?php if (!$is_ouder): ?>
          <!-- Ondertekenaar (desktop) -->
          <div class="ond-ondertekenaar">
            <span class="ond-ondertekenaar-lbl">Ondertekenaar</span>
            <span class="ond-ondertekenaar-naam"><?= h($naam_sol) ?><?= $leeftijd !== null ? ' · ' . (int)$leeftijd . ' jaar' : '' ?></span>
            <span class="ond-ondertekenaar-sub">18 of ouder, dus je tekent zelf. Onder 18 vervalt dit scherm en tekent alleen de ouder via een eigen link.</span>
          </div>
        <?php endif; ?>

        <div class="ond-info<?= $is_ouder ? ' navy' : '' ?>">
          <?= icoon('slot', $is_ouder ? 18 : 20) ?>
          <span><?= $is_ouder
            ? 'Deze link is persoonlijk en vervalt na ondertekening. U hoeft geen account te maken. We bewaren datum, tijd en uw IP-adres bij de handtekening.'
            : 'Je bent 18 of ouder, dus je ondertekent zelf. We bewaren datum, tijd en je IP-adres bij de handtekening.' ?></span>
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

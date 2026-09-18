<?php
/**
 * Digitale ondertekening — toegankelijk via unieke token in URL.
 * Twee toegangspaden:
 *   1) Sollicitant zelf ingelogd → invite genereert token en linkt hier heen
 *   2) Ouder/verzorger via mail-link → alleen token in URL nodig
 */
require_once __DIR__ . '/../includes/functions.php';
sessie_start();

$db     = db();
// Zorg dat de ondertekeningen-tabel bestaat (op een verse database komt die anders alleen via migratie)
if (function_exists('ondertekeningen_auto_setup')) ondertekeningen_auto_setup();
$token  = trim($_GET['token'] ?? '');

if (!$token) {
    // Zonder token: alleen een ingelogde sollicitant kan zelf een nieuw document starten
    $sol_id = portaal_vereis_login();
    $type = $_GET['nieuw'] ?? '';
    if ($type && in_array($type, ['intake','gezondheid_18plus'], true)) {
        // Maak nieuw record aan met unieke token
        $nieuw_token = bin2hex(random_bytes(24));
        $db->prepare("INSERT INTO ondertekeningen (sollicitatie_id, type, token, ondertekenaar_rol) VALUES (?,?,?,'sollicitant')")
           ->execute([$sol_id, $type, $nieuw_token]);
        redirect(APP_URL . '/portaal/ondertekenen.php?token=' . $nieuw_token);
    }
}

// Ophalen record via token
$q = $db->prepare("SELECT * FROM ondertekeningen WHERE token = ?");
$q->execute([$token]);
$rec = $q->fetch();

if (!$rec) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Niet gevonden</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css"></head>
    <body style="padding:40px;text-align:center;background:var(--bg);color:var(--tekst);font-family:Inter,sans-serif">
    <h1>Link niet gevonden</h1>
    <p>Deze link is ongeldig of verlopen. Neem contact op met Paal 13.</p>
    <p><a href="https://paal13.com">paal13.com</a></p>
    </body></html><?php
    exit;
}

// Ophalen sollicitant info voor context
$s_q = $db->prepare("SELECT s.id, s.fase, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum, p.email, sz.naam AS seizoen_naam FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id JOIN seizoenen sz ON sz.id = s.seizoen_id WHERE s.id = ?");
$s_q->execute([$rec['sollicitatie_id']]);
$sol = $s_q->fetch();
if (!$sol) { http_response_code(404); exit; }

$naam_sol = trim($sol['voornaam'] . ' ' . ($sol['tussenvoegsel'] ? $sol['tussenvoegsel'] . ' ' : '') . $sol['achternaam']);
$is_ondertekend = !empty($rec['ondertekend_op']);

// ── POST: verwerken ondertekening ────────────────────────────
$fout = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_ondertekend) {
    csrf_check();
    $naam_getypt = trim($_POST['naam_getypt'] ?? '');
    $plaats      = trim($_POST['plaats'] ?? '');
    $akkoord     = !empty($_POST['akkoord']);
    $handtekening = $_POST['handtekening_beeld'] ?? '';
    // Valideer de handtekening-data-URL (PNG, redelijke grootte)
    if ($handtekening !== '' && !preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $handtekening)) {
        $handtekening = '';
    }
    if (strlen($handtekening) > 600000) $handtekening = ''; // te groot → negeren

    if (!$akkoord)                       $fout = 'Vink het akkoord aan.';
    elseif (strlen($naam_getypt) < 3)    $fout = 'Vul je volledige naam in ter ondertekening.';
    elseif (!$plaats)                    $fout = 'Vul je woonplaats in.';
    elseif ($handtekening === '')        $fout = 'Zet je handtekening in het vak.';
    else {
        // Verzamel form-data (verschilt per type)
        $form = [];
        if ($rec['type'] === 'gezondheid_ouder' || $rec['type'] === 'gezondheid_18plus') {
            $form['geslacht']       = $_POST['geslacht'] ?? '';
            $form['geboorteplaats'] = trim($_POST['geboorteplaats'] ?? '');
            $form['geboortedatum']  = $_POST['geboortedatum'] ?? '';
            if ($rec['type'] === 'gezondheid_ouder') {
                $form['relatie'] = $_POST['relatie'] ?? '';
            }
            // Optionele aandoeningen (checkboxes)
            $form['aandoeningen'] = [
                'diabetes'         => !empty($_POST['a_diabetes']),
                'breuken'          => !empty($_POST['a_breuken']),
                'hart_vaat'        => !empty($_POST['a_hartvaat']),
                'ademhaling'       => !empty($_POST['a_ademhaling']),
                'zenuwstelsel'     => !empty($_POST['a_zenuwstelsel']),
                'gehoororgaan'     => !empty($_POST['a_gehoor']),
                'overig'           => trim($_POST['a_overig'] ?? ''),
            ];
        }
        if ($rec['type'] === 'intake' || $rec['type'] === 'intake_ouder') {
            $form['geslacht']         = $_POST['geslacht'] ?? '';
            if ($rec['type'] === 'intake_ouder') {
                $form['relatie'] = $_POST['relatie'] ?? '';
            }
            // 1. Gezondheid & veiligheid
            $form['gezondheid_keuze']       = $_POST['gezondheid_keuze'] ?? '';
            $form['gezondheid_toelichting'] = trim($_POST['gezondheid_toelichting'] ?? '');
            // 2. Foto's beleid (optioneel)
            $form['foto_toestemming'] = !empty($_POST['foto_toestemming']);
            // 3. Verklaring & aansprakelijkheid
            $form['verklaring'] = [
                'naar_waarheid'  => !empty($_POST['v_waarheid']),
                'goede_gezondheid' => !empty($_POST['v_gezondheid']),
                'eigen_risico'   => !empty($_POST['v_eigenrisico']),
                'leeftijd_16'    => !empty($_POST['v_leeftijd']),
            ];
        }

        // Update record
        $db->prepare("UPDATE ondertekeningen SET
                        ondertekenaar_naam=?, ondertekenaar_geboortedatum=?, ondertekenaar_geboorteplaats=?, ondertekenaar_relatie=?,
                        formulier_data=?, plaats=?, handtekening_beeld=?,
                        ondertekend_op=NOW(), ondertekend_ip=?, ondertekend_user_agent=?,
                        geldig_tot=DATE_ADD(CURDATE(), INTERVAL 1 YEAR)
                     WHERE token=?")
           ->execute([
                $naam_getypt,
                $form['geboortedatum'] ?? null,
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
            $sol_id_o  = (int)$rec['sollicitatie_id'];
            $doc_naam  = function_exists('ondertekening_label') ? ondertekening_label($rec['type']) : 'Formulier';
            $is_ouder  = ($rec['ondertekenaar_rol'] === 'ouder');
            $ref       = 'ondertekening:' . (int)$rec['id'];
            $pdf_pad   = null; $bijlagen = [];
            try {
                if (function_exists('ondertekening_pdf_genereer')) {
                    $vers = $db->prepare("SELECT * FROM ondertekeningen WHERE token=?");
                    $vers->execute([$token]);
                    $rec_vers = $vers->fetch() ?: $rec;
                    $pdf_pad  = ondertekening_pdf_genereer($rec_vers, $sol);
                    if ($pdf_pad) $bijlagen = [['pad' => $pdf_pad, 'naam' => 'Ondertekend_' . preg_replace('/[^A-Za-z0-9]+/', '_', $doc_naam) . '_Paal13.pdf', 'mime' => 'application/pdf']];
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

        redirect(APP_URL . '/portaal/ondertekenen.php?token=' . urlencode($token) . '&klaar=1');
    }
}

$titel = match ($rec['type']) {
    'gezondheid_ouder'  => 'Gezondheidsverklaring (t/m 17 jaar)',
    'gezondheid_18plus' => 'Gezondheidsverklaring 18+',
    'intake'            => 'Intakeformulier Selectiedag',
    'intake_ouder'      => 'Intakeformulier Selectiedag (ouder)',
    default             => 'Ondertekening',
};
$logo = function_exists('site_logo_url') ? site_logo_url() : '';
?>
<!DOCTYPE html>
<html lang="nl" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($titel) ?> — Paal 13</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css">
<script>(function(){var t=localStorage.getItem('paal13-theme')||'light';document.documentElement.setAttribute('data-theme',t==='dark'?'dark':'');})();</script>
<style>
body { background:var(--bg); min-height:100vh; }
.doc-wrap { max-width:720px; margin:0 auto; padding:20px 16px 48px; }
.doc-kop { text-align:center; margin-bottom:20px; }
.doc-kop img { height:50px; margin-bottom:8px; }
.doc-kop h1 { font-size:22px; letter-spacing:.02em; margin:0 0 6px; }
.doc-kop .sub { color:var(--tekst-zacht); font-size:13px; }
.doc-card { background:var(--bg-card); border:1px solid var(--rand); border-radius:12px; padding:24px; margin-bottom:16px; box-shadow:var(--shadow); }
.verklaring-tekst { font-size:13px; line-height:1.65; color:var(--tekst-zacht); background:var(--grijs-bg); padding:16px 18px; border-radius:8px; border-left:3px solid var(--oranje); margin-bottom:16px; }
.verklaring-tekst p { margin:0 0 10px; }
.verklaring-tekst p:last-child { margin-bottom:0; }
.aandoening-lijst { display:grid; grid-template-columns:1fr; gap:8px; padding:12px 16px; background:var(--grijs-bg); border-radius:6px; font-size:13px; }
.klaar-melding { background:var(--groen-bg); color:var(--groen); padding:20px; border-radius:12px; text-align:center; }
/* Formuliervelden in de nieuwe ontwerpstijl */
.doc-card .form-groep { margin-bottom:14px; }
.doc-card .form-groep > label { display:block; font:var(--text-label); color:var(--ink); margin-bottom:6px; }
.doc-card input[type="text"], .doc-card input[type="email"], .doc-card input[type="date"],
.doc-card input[type="tel"], .doc-card select, .doc-card textarea {
  width:100%; box-sizing:border-box; height:48px; padding:0 14px;
  border:1px solid var(--border-strong); border-radius:var(--radius-md);
  font:var(--text-body); font-size:16px; background:var(--surface); color:var(--ink);
}
.doc-card textarea { height:auto; min-height:80px; padding:12px 14px; resize:vertical; }
.doc-card input:focus, .doc-card select:focus, .doc-card textarea:focus {
  outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(5,77,138,.12);
}
.doc-card input[type="checkbox"] { width:18px; height:18px; accent-color:var(--navy); vertical-align:-3px; }
.form-rij { display:flex; gap:12px; flex-wrap:wrap; }
.form-rij .form-groep { flex:1; min-width:160px; }
</style>
</head>
<body>
<div class="doc-wrap">

<div class="doc-kop">
  <?php if ($logo): ?><img src="<?= h($logo) ?>" alt="Paal 13"><?php endif; ?>
  <h1><?= h($titel) ?></h1>
  <div class="sub">Reddingsbrigade Paal 13 — <?= h($sol['seizoen_naam']) ?></div>
</div>

<?php if (isset($_GET['klaar']) || $is_ondertekend): ?>
  <div class="klaar-melding">
    <h2 style="margin:0 0 8px"><?= icoon("vink-cirkel", 20) ?> Bedankt voor het ondertekenen</h2>
    <p style="margin:0;color:inherit">
      We hebben de ondertekening ontvangen op <?= h(datum_nl(substr($rec['ondertekend_op'] ?? date('Y-m-d'), 0, 10))) ?>.
      Er is een bevestiging naar het bestuur gestuurd.
    </p>
    <?php if ($rec['ondertekenaar_rol'] === 'sollicitant'): ?>
    <p style="margin-top:16px">
      <a href="<?= APP_URL ?>/portaal/" class="ds-btn ds-btn-primair">Terug naar het portaal</a>
    </p>
    <?php else: ?>
    <p style="margin-top:14px;font-size:12px;color:var(--tekst-zacht)">
      Je kunt dit venster nu sluiten.
    </p>
    <?php endif; ?>
  </div>
<?php else: ?>

  <div class="doc-card">
    <p style="margin-bottom:16px;font-size:14px">
      Deze verklaring wordt ondertekend voor <strong><?= h($naam_sol) ?></strong>
      <?php if ($sol['geboortedatum']): ?>
        (geboren <?= h(datum_nl($sol['geboortedatum'])) ?>)
      <?php endif; ?>.
    </p>

    <?php if ($fout): ?>
      <div class="flash flash-fout" style="margin-bottom:16px"><?= h($fout) ?></div>
    <?php endif; ?>

    <form method="POST" onsubmit="return valideer(this)">
      <?= csrf_input() ?>

      <?php if ($rec['type'] === 'gezondheid_ouder'): ?>

        <h3>Gegevens ouder / verzorger</h3>
        <div class="form-rij">
          <div class="form-groep">
            <label>Relatie tot minderjarige</label>
            <select name="relatie" required>
              <option value="">— kies —</option>
              <option value="vader">Vader</option>
              <option value="moeder">Moeder</option>
              <option value="voogd">Voogd / voogdes</option>
            </select>
          </div>
          <div class="form-groep">
            <label>Geslacht</label>
            <select name="geslacht" required>
              <option value="">— kies —</option>
              <option value="man">Man</option>
              <option value="vrouw">Vrouw</option>
              <option value="anders">Anders</option>
            </select>
          </div>
        </div>
        <div class="form-groep">
          <label>Volledige naam (zoals in ID)</label>
          <input type="text" name="naam_getypt" required autocomplete="name">
        </div>
        <div class="form-rij">
          <div class="form-groep">
            <label>Geboorteplaats</label>
            <input type="text" name="geboorteplaats" required>
          </div>
          <div class="form-groep">
            <label>Geboortedatum</label>
            <input type="date" name="geboortedatum" required>
          </div>
        </div>

      <?php elseif ($rec['type'] === 'gezondheid_18plus'): ?>

        <h3>Jouw gegevens</h3>
        <div class="form-groep">
          <label>Volledige naam (zoals in ID)</label>
          <input type="text" name="naam_getypt" required autocomplete="name" value="<?= h($naam_sol) ?>">
        </div>
        <div class="form-rij">
          <div class="form-groep">
            <label>Geslacht</label>
            <select name="geslacht" required>
              <option value="">— kies —</option>
              <option value="man">Man</option>
              <option value="vrouw">Vrouw</option>
              <option value="anders">Anders</option>
            </select>
          </div>
          <div class="form-groep">
            <label>Geboorteplaats</label>
            <input type="text" name="geboorteplaats" required>
          </div>
        </div>
        <div class="form-groep">
          <label>Geboortedatum</label>
          <input type="date" name="geboortedatum" value="<?= h($sol['geboortedatum'] ?? '') ?>" required>
        </div>

      <?php elseif ($rec['type'] === 'intake' || $rec['type'] === 'intake_ouder'): ?>

        <?php if ($rec['type'] === 'intake_ouder'): ?>
          <h3>Gegevens ouder / verzorger</h3>
          <div class="form-rij">
            <div class="form-groep">
              <label>Relatie tot minderjarige</label>
              <select name="relatie" required>
                <option value="">— kies —</option>
                <option value="vader">Vader</option>
                <option value="moeder">Moeder</option>
                <option value="voogd">Voogd / voogdes</option>
              </select>
            </div>
            <div class="form-groep">
              <label>Geslacht</label>
              <select name="geslacht" required>
                <option value="">— kies —</option>
                <option value="man">Man</option>
                <option value="vrouw">Vrouw</option>
                <option value="anders">Anders</option>
              </select>
            </div>
          </div>
          <div class="form-groep">
            <label>Volledige naam (ouder/verzorger)</label>
            <input type="text" name="naam_getypt" required autocomplete="name">
          </div>
          <div class="form-groep">
            <label>Geboortedatum</label>
            <input type="date" name="geboortedatum" required>
          </div>
        <?php else: ?>
          <h3>Jouw gegevens</h3>
          <div class="form-groep">
            <label>Volledige naam</label>
            <input type="text" name="naam_getypt" required value="<?= h($naam_sol) ?>">
          </div>
          <div class="form-rij">
            <div class="form-groep">
              <label>Geslacht</label>
              <select name="geslacht" required>
                <option value="">— kies —</option>
                <option value="man">Man</option>
                <option value="vrouw">Vrouw</option>
                <option value="anders">Anders</option>
              </select>
            </div>
            <div class="form-groep">
              <label>Geboortedatum</label>
              <input type="date" name="geboortedatum" value="<?= h($sol['geboortedatum'] ?? '') ?>" required>
            </div>
          </div>
        <?php endif; ?>

      <?php endif; ?>

      <?php if ($rec['type'] === 'intake' || $rec['type'] === 'intake_ouder'): ?>

        <div style="margin-top:20px">
          <h3>1. Gezondheid &amp; veiligheid</h3>
          <div class="verklaring-tekst">
            <p>Om jouw veiligheid te kunnen waarborgen, vragen wij of er medische of fysieke aandachtspunten zijn waarmee we tijdens de selectiedag rekening moeten houden. Je bent niet verplicht iets te delen. Wat je invult wordt vertrouwelijk behandeld.</p>
          </div>
          <div class="aandoening-lijst">
            <label><input type="radio" name="gezondheid_keuze" value="geen" required> Ik heb geen aandachtspunten om te melden.</label>
            <label><input type="radio" name="gezondheid_keuze" value="ja"> Ja, namelijk:</label>
          </div>
          <div class="form-groep" style="margin-top:8px">
            <textarea name="gezondheid_toelichting" rows="3" placeholder="Vul hier je aandachtspunten in (alleen nodig als je hierboven 'Ja' koos)"></textarea>
          </div>

          <h3 style="margin-top:24px">2. Foto's beleid</h3>
          <div class="verklaring-tekst">
            <p>Tijdens deze dag worden foto's gemaakt voor interne evaluatie, promotie en sociale media. We gaan hier zorgvuldig mee om: niemand wordt ongepast of negatief in beeld gebracht, bijvoorbeeld in het zwembad.</p>
          </div>
          <div class="aandoening-lijst">
            <label><input type="checkbox" name="foto_toestemming" value="1"> Ik geef toestemming voor het gebruik van foto's zoals hierboven omschreven.</label>
          </div>

          <h3 style="margin-top:24px">3. Verklaring &amp; aansprakelijkheid</h3>
          <div class="aandoening-lijst">
            <label><input type="checkbox" name="v_waarheid" value="1" required> Ik verklaar dat ik dit formulier naar waarheid heb ingevuld.</label>
            <label><input type="checkbox" name="v_gezondheid" value="1" required> Ik verklaar dat ik in goede gezondheid verkeer om deel te nemen aan sportieve activiteiten.</label>
            <label><input type="checkbox" name="v_eigenrisico" value="1" required> Deelname is op eigen risico. De organisatie is niet aansprakelijk voor letsel dat voortkomt uit verzwegen of onbekende omstandigheden.</label>
            <?php if ($rec['type'] === 'intake'): ?>
            <label><input type="checkbox" name="v_leeftijd" value="1" required> Ik ben 16 jaar of ouder en bevoegd om dit formulier zelfstandig te ondertekenen.</label>
            <?php endif; ?>
          </div>
          <p style="font-size:12.5px;color:var(--tekst-zacht);margin-top:6px">De vinkjes met een verplichting zijn nodig om te kunnen ondertekenen. Het fotobeleid is optioneel.</p>
        </div>

      <?php endif; ?>

      <?php if ($rec['type'] === 'gezondheid_ouder' || $rec['type'] === 'gezondheid_18plus'): ?>

        <div style="margin-top:20px">
          <h3>Verklaring</h3>
          <div class="verklaring-tekst">
            <?php if ($rec['type'] === 'gezondheid_ouder'): ?>
              <p><strong>Ondergetekende verklaart:</strong> dat bovengenoemde minderjarige mag deelnemen aan de opleidingen en het examen voor zwemmend en/of varend redden en/of wedstrijden waarbij de bepalingen van het geldige Examen Reglement worden gehandhaafd.</p>
            <?php else: ?>
              <p><strong>Ondergetekende verklaart:</strong> deel te mogen nemen aan de opleidingen en het examen voor zwemmend en/of varend redden en/of wedstrijden waarbij de bepalingen van het geldige Examen Reglement worden gehandhaafd.</p>
            <?php endif; ?>
            <p>Ondergetekende verklaart bekend te zijn met het feit dat het bovenstaande verhoogde eisen stelt aan de fysieke toestand van de deelnemer en verklaart kennis te hebben genomen van de factoren die hieronder zijn opgenomen.</p>
            <p>Ondergetekende verklaart jegens de organisatie die de opleiding en het examen verzorgt, alsmede de Koninklijke Nederlandse Bond tot het Redden van Drenkelingen, het risico van deelname te aanvaarden en beide instanties uitdrukkelijk van iedere aansprakelijkheid te vrijwaren, voor zover deze aansprakelijkheid betrekking heeft op gebeurtenissen die voortvloeien uit de hieronder vermelde gezondheidsrisico's.</p>
          </div>

          <h3>Van toepassing? Vink aan wat geldt</h3>
          <div class="aandoening-lijst">
            <label><input type="checkbox" name="a_diabetes"> Diabetes (suikerziekte) of breuken</label>
            <label><input type="checkbox" name="a_hartvaat"> Hart- en vaataandoeningen (benauwdheid, snelle vermoeidheid, hartkloppingen)</label>
            <label><input type="checkbox" name="a_ademhaling"> Astma / bronchitis</label>
            <label><input type="checkbox" name="a_zenuwstelsel"> Duizeligheid, flauwtes, wegrakingen, epilepsie</label>
            <label><input type="checkbox" name="a_gehoor"> Middenoorontstekingen, loop-oor, gat in trommelvlies</label>
          </div>
          <div class="form-groep" style="margin-top:12px">
            <label>Overige aandoeningen of medicatie <span style="font-weight:400;color:var(--tekst-zacht)">(optioneel)</span></label>
            <textarea name="a_overig" rows="2" placeholder="Bijv. medicatie, allergieën, andere aandoeningen…"></textarea>
          </div>
          <div class="verklaring-tekst" style="margin-top:12px">
            <p style="font-weight:600">Vraag bij enige twijfel advies aan uw huisarts of behandelend specialist. Doe dit ook na iedere belangrijke ziekte, in het bijzonder infectieziekten en operaties.</p>
            <p style="font-size:13px;color:var(--tekst-zacht)">Deze gezondheidsverklaring is 1 jaar geldig vanaf de datum van ondertekening. Zonder ondertekening is deze verklaring niet geldig.</p>
          </div>
        </div>
      <?php endif; ?>


      <hr style="margin:24px 0;border:none;border-top:1px solid var(--rand)">

      <h3>Ondertekening</h3>
      <div class="form-rij">
        <div class="form-groep">
          <label>Plaats van ondertekening</label>
          <input type="text" name="plaats" required placeholder="Bijv. Callantsoog">
        </div>
        <div class="form-groep">
          <label>Datum</label>
          <input type="text" value="<?= h(datum_nl(date('Y-m-d'))) ?>" disabled>
        </div>
      </div>

      <div class="form-groep" style="margin-top:8px">
        <label>
          <input type="checkbox" name="akkoord" value="1" required>
          &nbsp; Ik verklaar bovenstaande naar waarheid te hebben ingevuld en onderteken hierbij digitaal.
        </label>
      </div>

      <!-- Handtekening-vak -->
      <div class="form-groep" style="margin-top:16px">
        <label>Jouw handtekening</label>
        <div class="hdt-wrap">
          <canvas id="hdtCanvas" class="hdt-canvas"></canvas>
          <div class="hdt-hint" id="hdtHint"><?= icoon('potlood', 16) ?> Teken hier met je vinger of muis</div>
          <button type="button" class="hdt-wis" id="hdtWis"><?= icoon('ververs', 15) ?> Wissen</button>
        </div>
        <input type="hidden" name="handtekening_beeld" id="hdtData">
        <div class="ds-veld-hint" style="margin-top:6px">Zet je handtekening in het vak hierboven. Dit hoort bij je getypte naam.</div>
      </div>

      <div style="background:#e8f0f8;border-left:3px solid var(--navy);padding:12px 14px;border-radius:var(--radius-sm);font-size:12px;color:var(--ink-soft);margin-top:12px;display:flex;gap:8px;align-items:flex-start">
        <span style="flex-shrink:0;margin-top:1px"><?= icoon('slot', 15) ?></span>
        <span>Bij het versturen worden je naam, handtekening, IP-adres, browsergegevens en tijdstip vastgelegd als digitale handtekening (juridisch geldig conform eIDAS voor niet-notariële zaken).</span>
      </div>

      <button type="submit" class="ds-btn ds-btn-primair ds-btn-vol" style="margin-top:16px">
        <span class="btn-tekst">Digitaal ondertekenen</span>
        <span class="btn-spinner" style="display:none">Bezig met ondertekenen…</span>
      </button>
    </form>
  </div>

<?php endif; ?>

</div>
<script>
function valideer(f) {
  // Wis eerdere foutmarkeringen
  f.querySelectorAll('.veld-fout').forEach(el => el.classList.remove('veld-fout'));
  const bestaandeMelding = document.getElementById('ondtFoutmelding');
  if (bestaandeMelding) bestaandeMelding.remove();

  const fouten = [];
  // Verplichte tekstvelden/selects met [required]
  f.querySelectorAll('[required]').forEach(veld => {
    const leeg = (veld.type === 'checkbox' || veld.type === 'radio') ? !veld.checked : !String(veld.value || '').trim();
    if (leeg) {
      fouten.push(veld);
      const doel = veld.closest('.form-groep') || veld;
      doel.classList.add('veld-fout');
    }
  });
  // Handtekening verplicht
  const hdt = document.getElementById('hdtData');
  if (hdt && !hdt.value) {
    const wrap = document.querySelector('.hdt-wrap');
    if (wrap) wrap.classList.add('veld-fout');
    fouten.push(hdt);
  }

  if (fouten.length) {
    // Toon een duidelijke melding bovenaan het formulier + scroll naar het eerste probleem
    const melding = document.createElement('div');
    melding.id = 'ondtFoutmelding';
    melding.className = 'ds-melding ds-melding-error';
    melding.style.marginBottom = '16px';
    melding.textContent = 'Er ontbreken nog een paar dingen — de rood gemarkeerde velden hieronder zijn verplicht.';
    f.prepend(melding);
    const eerste = fouten[0].closest('.form-groep') || fouten[0].closest('.hdt-wrap') || fouten[0];
    eerste.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return false;
  }

  const btn = f.querySelector('button[type="submit"]');
  if (btn) {
    btn.disabled = true;
    btn.querySelector('.btn-tekst').style.display = 'none';
    btn.querySelector('.btn-spinner').style.display = '';
  }
  return true;
}

// ── Handtekening-canvas ──
(function() {
  const canvas = document.getElementById('hdtCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const hint = document.getElementById('hdtHint');
  const dataVeld = document.getElementById('hdtData');
  let tekent = false, leeg = true, vorigeX = 0, vorigeY = 0;

  // Canvas op juiste resolutie zetten (scherp op retina)
  function pasGrootteAan() {
    const rect = canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    // Bewaar bestaande tekening
    const oud = leeg ? null : canvas.toDataURL();
    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1a2230';
    if (oud) { const img = new Image(); img.onload = () => ctx.drawImage(img, 0, 0, rect.width, rect.height); img.src = oud; }
  }
  setTimeout(pasGrootteAan, 50);
  window.addEventListener('resize', pasGrootteAan);

  function pos(e) {
    const rect = canvas.getBoundingClientRect();
    const t = e.touches ? e.touches[0] : e;
    return { x: t.clientX - rect.left, y: t.clientY - rect.top };
  }
  function start(e) {
    e.preventDefault(); tekent = true;
    const p = pos(e); vorigeX = p.x; vorigeY = p.y;
    if (leeg) { leeg = false; if (hint) hint.style.display = 'none'; }
  }
  function beweeg(e) {
    if (!tekent) return; e.preventDefault();
    const p = pos(e);
    ctx.beginPath(); ctx.moveTo(vorigeX, vorigeY); ctx.lineTo(p.x, p.y); ctx.stroke();
    vorigeX = p.x; vorigeY = p.y;
  }
  function stop() { tekent = false; if (!leeg) dataVeld.value = canvas.toDataURL('image/png'); }

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', beweeg);
  window.addEventListener('mouseup', stop);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', beweeg, { passive: false });
  canvas.addEventListener('touchend', stop);

  document.getElementById('hdtWis').addEventListener('click', function() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    leeg = true; dataVeld.value = '';
    if (hint) hint.style.display = '';
  });
})();
</script>
</body>
</html>

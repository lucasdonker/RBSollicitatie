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
    // Ingelogde sollicitant kan zelf een nieuwe start uitkiezen
    if (empty($_SESSION['portaal_sol_id'])) {
        redirect(APP_URL . '/portaal/');
    }
    // Toon overzicht van beschikbare formulieren
    $sol_id = (int)$_SESSION['portaal_sol_id'];
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

    if (!$akkoord)                       $fout = 'Vink het akkoord aan.';
    elseif (strlen($naam_getypt) < 3)    $fout = 'Vul je volledige naam in ter ondertekening.';
    elseif (!$plaats)                    $fout = 'Vul je woonplaats in.';
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
        if ($rec['type'] === 'intake') {
            $form['geslacht']         = $_POST['geslacht'] ?? '';
            $form['heeft_aandacht']   = ($_POST['aandacht'] ?? '') === 'ja';
            $form['aandacht_tekst']   = trim($_POST['aandacht_tekst'] ?? '');
            $form['foto_toestemming'] = !empty($_POST['foto_akkoord']);
        }

        // Update record
        $db->prepare("UPDATE ondertekeningen SET
                        ondertekenaar_naam=?, ondertekenaar_geboortedatum=?, ondertekenaar_geboorteplaats=?, ondertekenaar_relatie=?,
                        formulier_data=?, plaats=?,
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
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                $token,
           ]);
        // Bevestiging naar sollicitant als een OUDER heeft getekend (sluit de loop)
        if ($rec['ondertekenaar_rol'] === 'ouder' && !empty($sol['email'])) {
            try {
                $doc_naam = match($rec['type']) {
                    'gezondheid_ouder' => 'de gezondheidsverklaring',
                    'intake_ouder'     => 'het intakeformulier',
                    default            => 'een formulier',
                };
                $vars_b = [
                    'voornaam'      => $sol['voornaam'],
                    'document_naam' => $doc_naam,
                    'portaal_url'   => APP_URL . '/portaal/',
                ];
                $rendered_b = function_exists('ondertekening_mail_render') ? ondertekening_mail_render('ondertekening_bevestiging', $vars_b) : null;
                if ($rendered_b && function_exists('stuur_mail_html')) {
                    stuur_mail_html($sol['email'], $sol['voornaam'], $rendered_b['onderwerp'], $rendered_b['html'], (int)$rec['sollicitatie_id']);
                }
            } catch (\Exception $e) {}
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
    <h2 style="margin:0 0 8px">✓ Bedankt voor het ondertekenen</h2>
    <p style="margin:0;color:inherit">
      We hebben de ondertekening ontvangen op <?= h(datum_nl(substr($rec['ondertekend_op'] ?? date('Y-m-d'), 0, 10))) ?>.
      Er is een bevestiging naar het bestuur gestuurd.
    </p>
    <?php if ($rec['ondertekenaar_rol'] === 'sollicitant'): ?>
    <p style="margin-top:16px">
      <a href="<?= APP_URL ?>/portaal/" class="btn btn-primary">← Terug naar het portaal — bekijk je overige taken</a>
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

      <div style="background:var(--blauw-bg);border-left:3px solid var(--blauw);padding:12px 14px;border-radius:6px;font-size:12px;color:var(--tekst-zacht);margin-top:12px">
        📌 Bij het versturen worden je naam, IP-adres, browsergegevens en tijdstip vastgelegd als digitale handtekening (juridisch geldig conform eIDAS voor niet-notariële zaken).
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:16px">
        <span class="btn-tekst">✍️ Digitaal ondertekenen</span>
        <span class="btn-spinner" style="display:none">⏳ Bezig met ondertekenen…</span>
      </button>
    </form>
  </div>

<?php endif; ?>

</div>
<script>
function valideer(f) {
  if (!f.akkoord.checked) { alert('Vink het akkoord aan.'); return false; }
  const btn = f.querySelector('button[type="submit"]');
  if (btn) {
    btn.disabled = true;
    btn.querySelector('.btn-tekst').style.display = 'none';
    btn.querySelector('.btn-spinner').style.display = '';
  }
  return true;
}
</script>
</body>
</html>

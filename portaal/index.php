<?php
require_once __DIR__ . '/../includes/functions.php';
sessie_start();

$stap = $_GET['stap'] ?? 'login';
$fout = '';
if (portaal_ingelogd()) $stap = 'dossier';

// ── OTP/SSO stap 1: email-submit ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_submit'])) {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fout = 'Vul een geldig e-mailadres in.';
    } else {
        // Probeer Authentik SSO: als email daar bestaat, toon een tussenscherm
        // ("je hebt al een account, we sturen je door") met een uitweg naar de code.
        $authentik_user = authentik_email_bestaat($email);
        if ($authentik_user) {
            $_SESSION['otp_email'] = $email; // wordt door auth_sso.php gebruikt
            $_SESSION['doorsturen_email'] = $email;
            redirect(APP_URL . '/portaal/?stap=doorsturen');
        }

        // Fallback: OTP code
        $p = db()->prepare("SELECT p.voornaam FROM personen p JOIN sollicitaties s ON s.persoon_id=p.id WHERE p.email=? LIMIT 1");
        $p->execute([$email]); $persoon = $p->fetch();
        if ($persoon) {
            // Atomische throttle: is er afgelopen 60s al een actieve OTP gegenereerd?
            $rl = db()->prepare("SELECT 1 FROM otp_tokens WHERE email=? AND type='portaal' AND gebruikt=0 AND aangemaakt_op > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
            $rl->execute([$email]);
            $recent_otp = (bool)$rl->fetchColumn();

            if (!$recent_otp) {
                $code = otp_genereer($email, 'portaal');
                stuur_mail($email, $persoon['voornaam'], 'Inlogcode Paal 13 portaal',
                    '<p>Jouw inlogcode voor het sollicitantenportaal:</p>
                     <div style="font-size:40px;font-weight:bold;letter-spacing:10px;color:#054d8a;text-align:center;background:#f0f4f8;border-radius:8px;padding:22px;margin:16px 0">' . $code . '</div>
                     <p>Geldig 15 minuten.</p>');
            }
            // Als wel recent: bestaande code blijft geldig, geen nieuwe mail
        }
        $_SESSION['otp_email'] = $email;
        redirect(APP_URL . '/portaal/?stap=code');
    }
}

// ── Vanaf het doorstuur-scherm: toch een code i.p.v. het account ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toch_code'])) {
    csrf_check();
    $email = strtolower(trim($_SESSION['doorsturen_email'] ?? ''));
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $p = db()->prepare("SELECT p.voornaam FROM personen p JOIN sollicitaties s ON s.persoon_id=p.id WHERE p.email=? LIMIT 1");
        $p->execute([$email]); $persoon = $p->fetch();
        if ($persoon) {
            $rl = db()->prepare("SELECT 1 FROM otp_tokens WHERE email=? AND type='portaal' AND gebruikt=0 AND aangemaakt_op > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1");
            $rl->execute([$email]); $recent_otp = (bool)$rl->fetchColumn();
            if (!$recent_otp) {
                $code = otp_genereer($email, 'portaal');
                stuur_mail($email, $persoon['voornaam'], 'Inlogcode Paal 13 portaal',
                    '<p>Jouw inlogcode voor het sollicitantenportaal:</p>
                     <div style="font-size:40px;font-weight:bold;letter-spacing:10px;color:#054d8a;text-align:center;background:#f0f4f8;border-radius:8px;padding:22px;margin:16px 0">' . $code . '</div>
                     <p>Geldig 15 minuten.</p>');
            }
        }
        $_SESSION['otp_email'] = $email;
        unset($_SESSION['doorsturen_email']);
        redirect(APP_URL . '/portaal/?stap=code');
    }
    redirect(APP_URL . '/portaal/');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code_submit'])) {
    csrf_check();
    $email = $_SESSION['otp_email'] ?? '';
    $code  = preg_replace('/\D/', '', $_POST['code'] ?? '');
    if (!$email) redirect(APP_URL . '/portaal/');
    if (strlen($code) !== 6) { $fout = 'Voer een 6-cijferige code in.'; $stap = 'code'; }
    elseif (!otp_verifieer($email, $code, 'portaal')) { $fout = 'Onjuiste of verlopen code.'; $stap = 'code'; }
    else {
        $st = db()->prepare("SELECT s.id FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE p.email=? ORDER BY s.aangemeld_op DESC LIMIT 1");
        $st->execute([$email]); $sol = $st->fetch();
        if ($sol) { login_portaal((int)$sol['id'], $email); unset($_SESSION['otp_email']); redirect(APP_URL . '/portaal/'); }
        else { $fout = 'Geen sollicitatie gevonden.'; $stap = 'login'; }
    }
}

if (isset($_GET['uitloggen'])) { unset($_SESSION['portaal_sol_id'], $_SESSION['portaal_email']); redirect(APP_URL . '/portaal/'); }

// ── Dossier data ──────────────────────────────────────────────
$sol = $blk = $besch = null;
$huidig_weken = [];
$alle_weken = [];
$maanden_labels = [];

if ($stap === 'dossier') {
    $sol_id = (int)$_SESSION['portaal_sol_id'];

    // Haal seizoen op, met graceful fallback als datum_werk kolommen ontbreken
    try {
        $stmt = db()->prepare("
            SELECT s.*, p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon, p.geboortedatum,
                   sz.naam sz_naam, sz.datum_selectiedag, sz.locatie_selectiedag,
                   sz.datum_tw_start, sz.datum_tw_eind, sz.locatie_tw,
                   sz.datum_werk_start, sz.datum_werk_eind
            FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id
            JOIN seizoenen sz ON sz.id=s.seizoen_id WHERE s.id=?
        ");
        $stmt->execute([$sol_id]); $sol = $stmt->fetch();
    } catch (Exception $e) {
        $stmt = db()->prepare("
            SELECT s.*, p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon, p.geboortedatum,
                   sz.naam sz_naam, sz.datum_selectiedag, sz.locatie_selectiedag,
                   sz.datum_tw_start, sz.datum_tw_eind, sz.locatie_tw,
                   NULL as datum_werk_start, NULL as datum_werk_eind
            FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id
            JOIN seizoenen sz ON sz.id=s.seizoen_id WHERE s.id=?
        ");
        $stmt->execute([$sol_id]); $sol = $stmt->fetch();
    }
    if (!$sol) { unset($_SESSION['portaal_sol_id']); redirect(APP_URL . '/portaal/'); }

    $b = db()->prepare("SELECT * FROM blk_formulieren WHERE sollicitatie_id=?");
    $b->execute([$sol_id]); $blk = $b->fetch();

    $bd = db()->prepare("SELECT * FROM beschikbaarheid WHERE sollicitatie_id=?");
    $bd->execute([$sol_id]); $besch = $bd->fetch();
    $huidig_weken = $besch ? (json_decode($besch['weken_json'] ?? '[]', true) ?: []) : [];

    // Weken genereren (seizoen-datums of fallback zomerweken NL)
    $week_start = $sol['datum_werk_start'] ?? null;
    $week_eind  = $sol['datum_werk_eind']  ?? null;
    if (!$week_start || !$week_eind) {
        // Fallback: week 22 t/m 35 (eind mei – begin sept)
        $jaar = (int)date('Y');
        $week_start = (new DateTime())->setISODate($jaar, 22, 1)->format('Y-m-d');
        $week_eind  = (new DateTime())->setISODate($jaar, 35, 7)->format('Y-m-d');
    }
    $dag = new DateTime($week_start);
    $eind = new DateTime($week_eind);
    while ($dag->format('N') != 1) $dag->modify('+1 day');
    while ($dag <= $eind) {
        $wn = (int)$dag->format('W');
        $m  = (int)$dag->format('n');
        $alle_weken[] = ['week' => $wn, 'maand' => $m,
            'label'   => 'Week ' . $wn,
            'periode' => $dag->format('j M') . '–' . (clone $dag)->modify('+6 days')->format('j M')];
        $maanden_labels[$m] = $dag->format('F Y');
        $dag->modify('+7 days');
    }
}

// ── Veldlabels voor wijzigingslog ─────────────────────────────
$blk_veld_labels = [
    'postcode'                       => 'Postcode',
    'huisnummer'                     => 'Huisnummer',
    'straat'                         => 'Straat',
    'woonplaats'                     => 'Woonplaats',
    'adres'                          => 'Woonadres',
    'opleiding'                      => 'Opleiding',
    'werkervaring'                   => 'Werkervaring',
    'diplomas'                       => "Diploma's",
    'motivatie_lifeguard'            => 'Motivatie lifeguard',
    'motivatie_strand'               => 'Strand motivatie',
    'beschikbaarheid_intentie'       => 'Beschikbaarheid',
    'beschikbaarheid_uitzonderingen' => 'Uitzonderingen',
    'social_media'                   => 'Social media',
    'bijzonderheden'                 => 'Bijzonderheden',
];

// ── AI-beschikbaarheid: vrije tekst → formulierdata (experimenteel) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_besch_submit']) && portaal_ingelogd()) {
    header('Content-Type: application/json');
    csrf_check();
    if (!function_exists('ai_besch_actief') || !ai_besch_actief()) {
        echo json_encode(['status' => 'uit']); exit;
    }
    // Rate-limit: max 8 verzoeken per 10 minuten per sessie
    $nu = time();
    $_SESSION['ai_besch_hits'] = array_filter($_SESSION['ai_besch_hits'] ?? [], fn($t) => $t > $nu - 600);
    if (count($_SESSION['ai_besch_hits']) >= 8) {
        echo json_encode(['status' => 'fout', 'bericht' => 'Even rustig aan — probeer het over een paar minuten weer.']); exit;
    }
    $_SESSION['ai_besch_hits'][] = $nu;

    $seizoen_ai = actief_seizoen();
    $weken_geldig = array_keys(function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen_ai) : []);
    $res = ai_besch_verwerk($_POST['tekst'] ?? '', $weken_geldig, $seizoen_ai);
    // Log de interactie voor latere referentie (originele tekst + resultaat)
    if (function_exists('ai_besch_log')) {
        ai_besch_log((int)($_SESSION['portaal_sol_id'] ?? 0), $_POST['tekst'] ?? '', $res);
    }
    echo json_encode($res);
    exit;
}

// ── BLK opslaan ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ouder_submit']) && !isset($_POST['ond_id']) && portaal_ingelogd()) {
    csrf_check();
    $sol_id = (int)$_SESSION['portaal_sol_id'];
    $o_naam  = trim($_POST['ouder_naam'] ?? '');
    $o_email = trim($_POST['ouder_email'] ?? '');
    $o_tel   = trim($_POST['ouder_telefoon'] ?? '');
    // Telefoon: cijfers, spaties, +, -, () toegestaan; minstens 8 cijfers
    $tel_ok = preg_match_all('/\d/', $o_tel) >= 8;
    if ($o_naam && filter_var($o_email, FILTER_VALIDATE_EMAIL) && $tel_ok) {
        $opgeslagen = false;
        // Stap 1: gegevens opslaan (dit is de kern — moet lukken)
        try {
            if (function_exists('personen_ouder_auto_setup')) personen_ouder_auto_setup();
            // Zeker stellen dat de ouder-kolommen bestaan (auto-setup kan elders al gedraaid zijn of falen)
            try {
                $dbn = db()->query("SELECT DATABASE()")->fetchColumn();
                $kc = db()->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='personen' AND COLUMN_NAME IN ('ouder_naam','ouder_email','ouder_telefoon')");
                $kc->execute([$dbn]);
                $aanwezig = $kc->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('ouder_naam', $aanwezig))     db()->exec("ALTER TABLE `personen` ADD COLUMN `ouder_naam` VARCHAR(120) DEFAULT NULL");
                if (!in_array('ouder_email', $aanwezig))    db()->exec("ALTER TABLE `personen` ADD COLUMN `ouder_email` VARCHAR(255) DEFAULT NULL");
                if (!in_array('ouder_telefoon', $aanwezig)) db()->exec("ALTER TABLE `personen` ADD COLUMN `ouder_telefoon` VARCHAR(30) DEFAULT NULL");
            } catch (Exception $e) { error_log('ouder-kolommen aanmaken: ' . $e->getMessage()); }

            // Eerst het persoon_id ophalen, dan updaten (betrouwbaarder dan een subquery in de WHERE)
            $pid_q = db()->prepare("SELECT persoon_id FROM sollicitaties WHERE id=?");
            $pid_q->execute([$sol_id]);
            $persoon_id = (int)$pid_q->fetchColumn();
            if ($persoon_id > 0) {
                $upd = db()->prepare("UPDATE personen SET ouder_naam=?, ouder_email=?, ouder_telefoon=? WHERE id=?");
                $upd->execute([$o_naam, $o_email, $o_tel, $persoon_id]);
                $opgeslagen = true;
            } else {
                error_log('ouder_submit: geen persoon_id voor sollicitatie ' . $sol_id);
                $_SESSION['flash_portaal_fout'] = 'Er ging iets mis bij het opslaan (kon je gegevens niet koppelen). Neem contact op.';
            }
        } catch (Exception $e) {
            error_log('ouder_submit opslaan: ' . $e->getMessage());
            $_SESSION['flash_portaal_fout'] = 'Er ging iets mis bij het opslaan van de gegevens. Probeer het later opnieuw of neem contact op via website@paal13.com.';
        }
        // Stap 2: vervolgstappen — mogen falen zonder de bevestiging te blokkeren
        if ($opgeslagen) {
            $docs_ok = true;
            try { if (function_exists('ondertekeningen_klaarzetten_sd')) ondertekeningen_klaarzetten_sd($sol_id); }
            catch (Exception $e) { $docs_ok = false; error_log('ondertekeningen_klaarzetten_sd: ' . $e->getMessage()); }
            try { if (function_exists('mail_taken_klaar')) mail_taken_klaar($sol_id); }
            catch (Exception $e) { error_log('mail_taken_klaar: ' . $e->getMessage()); }
            $_SESSION['flash_portaal'] = $docs_ok
                ? 'Bedankt! Je ouder/verzorger ontvangt nu een mail om de formulieren te ondertekenen.'
                : 'De gegevens zijn opgeslagen. Het klaarzetten van de formulieren lukte nog niet — we kijken ernaar; probeer het zo nodig later opnieuw.';
        }
    } else {
        $_SESSION['flash_portaal_fout'] = 'Vul een geldige naam, e-mailadres én telefoonnummer in.';
    }
    redirect(APP_URL . '/portaal/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['taak_toggle']) && portaal_ingelogd()) {
    csrf_check();
    $taak_id = (int)($_POST['taak_id'] ?? 0);
    $sol_id  = (int)$_SESSION['portaal_sol_id'];
    try {
        $q = db()->prepare("SELECT status FROM sollicitant_taken WHERE id=? AND sollicitatie_id=?");
        $q->execute([$taak_id, $sol_id]);
        $t = $q->fetch();
        if ($t) {
            $nieuw   = $t['status'] === 'voltooid' ? 'open' : 'voltooid';
            $voltooid = $nieuw === 'voltooid' ? date('Y-m-d H:i:s') : null;
            db()->prepare("UPDATE sollicitant_taken SET status=?, voltooid_op=? WHERE id=?")
                ->execute([$nieuw, $voltooid, $taak_id]);
        }
    } catch (Exception $e) {}
    redirect(APP_URL . '/portaal/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ouder_submit']) && portaal_ingelogd()) {
    csrf_check();
    $ond_id      = (int)($_POST['ond_id'] ?? 0);
    $ouder_naam  = trim($_POST['ouder_naam'] ?? '');
    $ouder_email = trim($_POST['ouder_email'] ?? '');
    $sol_id      = (int)$_SESSION['portaal_sol_id'];
    if ($ond_id && $ouder_naam && filter_var($ouder_email, FILTER_VALIDATE_EMAIL)) {
        try {
            // Alleen eigen records zonder ondertekening bijwerken
            $upd = db()->prepare("UPDATE ondertekeningen SET ondertekenaar_naam=?, ondertekenaar_email=? WHERE id=? AND sollicitatie_id=? AND ondertekend_op IS NULL");
            $upd->execute([$ouder_naam, $ouder_email, $ond_id, $sol_id]);
            if ($upd->rowCount() > 0 && function_exists('ouder_mail_versturen')) {
                ouder_mail_versturen($ond_id);
            }
        } catch (Exception $e) {}
    }
    redirect(APP_URL . '/portaal/#taken');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blk_submit']) && $stap === 'dossier') {
    csrf_check();
    $sol_id = (int)$_SESSION['portaal_sol_id'];
    $reden  = trim($_POST['reden_wijziging'] ?? '');
    // Een "edit" is alleen een WIJZIGING ná definitieve indiening — niet de eerste
    // indiening vanuit een autosave-concept. Doordat de autosave al een blk-rij
    // aanmaakt, mag het bestaan van die rij niet als "edit" gelden (dat gaf onterecht
    // "Vul een reden in voor de wijziging"). We leiden het af uit de fase: pas vanaf
    // 'aangemeld_selectie' is het formulier eerder definitief ingediend.
    $reeds_ingediend_fases = ['aangemeld_selectie','selectiedag_geweest','uitgenodigd_tw','tw_geweest','aangenomen','afgewezen'];
    $is_edit = !empty($blk) && in_array($sol['fase'] ?? '', $reeds_ingediend_fases, true);

    // Weken zijn nu opgeslagen als JSON
    // Weken afleiden uit de periode-blokken — compat met blk.beschikbaarheid_intentie
    $weken_sel = [];
    if (!empty($_POST['b2_blok_van'])) {
        $bv = $_POST['b2_blok_van']; $bt = $_POST['b2_blok_tot'] ?? [];
        foreach ($bv as $i => $v) {
            $van = (int)$v; $tot = (int)($bt[$i] ?? 0);
            if ($van < 1 || $tot < 1) continue;
            if ($tot < $van) { $t = $van; $van = $tot; $tot = $t; }
            for ($w = $van; $w <= $tot; $w++) $weken_sel[$w] = true;
        }
        $weken_sel = array_keys($weken_sel);
    }
    sort($weken_sel);

    // Adresvelden: postcode normaliseren (1234 AB)
    $pc_raw = strtoupper(preg_replace('/\s+/', '', $_POST['postcode'] ?? ''));
    $pc     = preg_match('/^([0-9]{4})([A-Z]{2})$/', $pc_raw, $m) ? "{$m[1]} {$m[2]}" : $pc_raw;
    $straat_in = trim($_POST['straat'] ?? '');
    $hnr_in    = trim($_POST['huisnummer'] ?? '');
    $wp_in     = trim($_POST['woonplaats'] ?? '');
    // Samengesteld adres voor backwards compat
    $adres_samen = ($straat_in && $hnr_in) ? "$straat_in $hnr_in, $pc $wp_in" : '';

    $velden = [
        'postcode'                       => $pc,
        'huisnummer'                     => $hnr_in,
        'straat'                         => $straat_in,
        'woonplaats'                     => $wp_in,
        'adres'                          => $adres_samen,
        'opleiding'                      => trim($_POST['opleiding'] ?? ''),
        'werkervaring'                   => trim($_POST['werkervaring'] ?? ''),
        'diplomas'                       => trim($_POST['diplomas'] ?? ''),
        'motivatie_lifeguard'            => trim($_POST['motivatie_lifeguard'] ?? ''),
        'motivatie_strand'               => trim($_POST['motivatie_strand'] ?? ''),
        'beschikbaarheid_intentie'       => !empty($weken_sel) ? json_encode($weken_sel) : '',
        'beschikbaarheid_uitzonderingen' => trim($_POST['beschikbaarheid_uitzonderingen'] ?? ''),
        'social_media'                   => trim($_POST['social_media'] ?? ''),
        'bijzonderheden'                 => trim($_POST['bijzonderheden'] ?? ''),
    ];

    // Filter velden zodat we niet falen als migratie 0007 nog niet draaide
    try {
        $kolommen = db()->query("SHOW COLUMNS FROM blk_formulieren")->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Exception $e) { $kolommen = []; }
    $kolommen_set = array_flip($kolommen);
    $velden = array_intersect_key($velden, $kolommen_set);
    // Voeg adres-fallback toe als nieuwe kolommen ontbreken maar 'adres' wel bestaat
    if (!isset($velden['postcode']) && isset($kolommen_set['adres']) && !isset($velden['adres'])) {
        $velden['adres'] = $adres_samen;
    }

    // Bepaal of er inhoudelijk iets aan het BLK gewijzigd is (bij een edit).
    // Zo niet, dan is een reden onzinnig — de gebruiker werkte bijv. alleen
    // z'n beschikbaarheid bij, die apart wordt opgeslagen.
    $blk_changes = [];
    if ($is_edit) {
        foreach ($velden as $veld => $nieuw) {
            $oud = $blk[$veld] ?? '';
            if ($veld === 'beschikbaarheid_intentie') {
                $oud_w = json_decode($oud ?? '[]', true) ?: [];
                $nieuw_w = json_decode($nieuw ?? '[]', true) ?: [];
                if ($oud_w != $nieuw_w) $blk_changes[] = ['veld'=>$veld, 'label'=>$blk_veld_labels[$veld] ?? $veld,
                    'oud'=>'Weken: ' . implode(', ', $oud_w), 'nieuw'=>'Weken: ' . implode(', ', $nieuw_w)];
            } elseif (trim($oud ?? '') !== trim($nieuw ?? '')) {
                $blk_changes[] = ['veld'=>$veld, 'label'=>$blk_veld_labels[$veld] ?? $veld, 'oud'=>$oud, 'nieuw'=>$nieuw];
            }
        }
    }

    if (!$velden['motivatie_lifeguard']) {
        $fout = 'Vul je motivatie in (stap 3).';
    } elseif ($is_edit && $blk_changes && !$reden) {
        $fout = 'Vul een reden in voor de wijziging (stap 5).';
    } else {
        // Foto upload — relatief pad opslaan zodat APP_URL veranderingen geen issue zijn
        $foto_url = $blk['foto_url'] ?? null;
        if (!empty($_FILES['foto']['tmp_name']) && $_FILES['foto']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            // Controleer dat het bestand ECHT een afbeelding is (niet alleen de extensie vertrouwen).
            // getimagesize leest de daadwerkelijke bestandsinhoud; faalt bij niet-afbeeldingen.
            $imginfo = @getimagesize($_FILES['foto']['tmp_name']);
            $toegestaan = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
            $echt_type  = $imginfo['2'] ?? null;
            if ($imginfo !== false && isset($toegestaan[$echt_type])
                && in_array($ext, ['jpg','jpeg','png','webp'])
                && $_FILES['foto']['size'] < 5*1024*1024) {
                $dir = __DIR__ . '/../uploads/fotos/';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                // Gebruik de extensie die hoort bij het WERKELIJKE bestandstype, niet de opgegeven naam
                $veilige_ext = $toegestaan[$echt_type];
                $fn = 'sol_' . $sol_id . '_' . time() . '.' . $veilige_ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $fn)) {
                    // Sla RELATIEF pad op vanaf de root, zonder APP_URL prefix
                    $foto_url = '/uploads/fotos/' . $fn;
                }
            }
        }

        $nulls = array_map(fn($v) => $v ?: null, $velden);

        if ($is_edit) {
            // Log wijzigingen (al berekend als $blk_changes vóór de validatie)
            if (!empty($blk_changes)) {
                try {
                    db()->prepare("INSERT INTO blk_wijzigingen (sollicitatie_id, reden, wijzigingen_json) VALUES(?,?,?)")
                       ->execute([$sol_id, $reden, json_encode($blk_changes)]);
                } catch (Exception $e) { /* tabel bestaat nog niet — skip */ }
            }
        }
        // Opslaan met INSERT ... ON DUPLICATE KEY UPDATE, zodat het altijd werkt —
        // ook als er nog een oude blk-rij bestaat (bijv. na 'verwijderen' via de interface
        // dat de rij niet volledig weghaalde). Voorheen gaf dat een duplicate-fout.
        {
            $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($velden)));
            $ph   = implode(',', array_fill(0, count($velden), '?'));
            $upd  = implode(',', array_map(fn($k) => "`$k`=VALUES(`$k`)", array_keys($velden)));
            db()->prepare("INSERT INTO blk_formulieren (sollicitatie_id,$cols,foto_url) VALUES(?,$ph,?)
                           ON DUPLICATE KEY UPDATE $upd, foto_url=VALUES(foto_url), bijgewerkt_op=NOW()")
               ->execute([$sol_id, ...array_values($nulls), $foto_url]);
        }
        if (!$is_edit) {
            if ($sol['fase'] === 'uitgenodigd_selectie') {
                db()->prepare("UPDATE sollicitaties SET fase='aangemeld_selectie' WHERE id=?")->execute([$sol_id]);
                $sol['fase'] = 'aangemeld_selectie';
            }
            // Taak afgerond: bevestigingsmail
            if (function_exists('mail_taak_afgerond')) {
                try { mail_taak_afgerond($sol_id, 'Beter leren kennen-formulier'); } catch (Exception $e) {}
            }
        }
        $_SESSION['b2_toon_opgeslagen'] = true;

        // Beschikbaarheid 2.0 opslaan (periode-blokken model)
        try {
            if (function_exists('beschikbaarheid2_opslaan') && !empty($_POST['b2_blok_van'])) {
                // Blokken (met weekend-dagen bij regime weekend)
                $blokken = [];
                $bv = $_POST['b2_blok_van'] ?? []; $bt = $_POST['b2_blok_tot'] ?? []; $br = $_POST['b2_blok_regime'] ?? [];
                $bwd = $_POST['b2_blok_wkdagen'] ?? []; // per blok-index: JSON string van gekozen weekenddagen
                foreach ($bv as $i => $v) {
                    if ($v && !empty($bt[$i])) {
                        $blok = ['van_week' => (int)$v, 'tot_week' => (int)$bt[$i], 'regime' => $br[$i] ?? 'vijf'];
                        if (($br[$i] ?? '') === 'weekend' && !empty($bwd[$i])) {
                            $wd = json_decode($bwd[$i], true);
                            if (is_array($wd)) $blok['weekend_dagen'] = array_map('intval', $wd);
                        }
                        $blokken[] = $blok;
                    }
                }
                // Afwezigheid
                $afw = [];
                $av = $_POST['b2_afw_van'] ?? []; $at = $_POST['b2_afw_tot'] ?? []; $al = $_POST['b2_afw_label'] ?? [];
                foreach ($av as $i => $v) {
                    if ($v && !empty($at[$i])) $afw[] = ['van' => $v, 'tot' => $at[$i], 'label' => $al[$i] ?? ''];
                }
                // Verzoeken afwijkend aantal dagen (met optionele dagen-pool)
                $verzoeken = [];
                $vv = $_POST['b2_vz_van'] ?? []; $vt = $_POST['b2_vz_tot'] ?? []; $vd = $_POST['b2_vz_dagen'] ?? []; $vl = $_POST['b2_vz_toelichting'] ?? [];
                $vp = $_POST['b2_vz_pool'] ?? []; // per verzoek-index: JSON string van beschikbare dagen
                $vz_fouten = [];
                foreach ($vv as $i => $v) {
                    if ($v && !empty($vt[$i]) && !empty($vd[$i])) {
                        $aantal = (int)$vd[$i];
                        $verzoek = ['van_week' => (int)$v, 'tot_week' => (int)$vt[$i], 'dagen' => $aantal, 'toelichting' => $vl[$i] ?? ''];
                        $pool = [];
                        if (!empty($vp[$i])) {
                            $decoded = json_decode($vp[$i], true);
                            if (is_array($decoded)) $pool = array_map('intval', $decoded);
                        }
                        // Validatie: als er voorkeursdagen zijn opgegeven, moeten dat er minstens
                        // zoveel zijn als het gevraagde aantal dagen. Anders haal je het aantal niet.
                        if ($pool && count($pool) < $aantal) {
                            $vz_fouten[] = "Verzoek voor week {$v}: je hebt {$aantal} dagen aangevraagd maar slechts " . count($pool) . " dag(en) aangevinkt. Vink minstens {$aantal} dagen aan.";
                            continue; // dit verzoek niet opslaan
                        }
                        if ($pool) $verzoek['pool'] = $pool;
                        $verzoeken[] = $verzoek;
                    }
                }
                if ($vz_fouten) {
                    // Bewaar de fouten zodat ze getoond kunnen worden; sla de geldige verzoeken wel op
                    $_SESSION['b2_vz_waarschuwing'] = implode(' ', $vz_fouten);
                }
                beschikbaarheid2_opslaan($sol_id, [
                    'blokken'          => $blokken,
                    'afwezig'          => $afw,
                    'verzoeken'        => $verzoeken,
                    'vaste_dagen_uit'  => $_POST['b2_vaste_uit'] ?? [],
                    'vaste_toelichting'=> trim($_POST['b2_vaste_toelichting'] ?? '') ?: null,
                    'voorlopig'        => !empty($_POST['b2_voorlopig']),
                    'opmerking'        => trim($_POST['beschikbaarheid_uitzonderingen'] ?? '') ?: null,
                ]);
            }

            // Losse dagen (concrete kalenderdatums die iemand niet kan / liever vrij wil)
            if (function_exists('beschikbaarheid_dagen_opslaan')) {
                $dd = $_POST['b2_dag_datum'] ?? []; $ds = $_POST['b2_dag_soort'] ?? []; $dr = $_POST['b2_dag_reden'] ?? [];
                $losse = [];
                foreach ($dd as $i => $datum) {
                    if (trim($datum) === '') continue;
                    $losse[] = [
                        'datum' => trim($datum),
                        'soort' => $ds[$i] ?? 'niet_beschikbaar',
                        'reden' => trim($dr[$i] ?? ''),
                    ];
                }
                beschikbaarheid_dagen_opslaan($sol['id'], $losse, 'sollicitant');
            }
        } catch (Exception $e) { error_log('b2 opslaan: ' . $e->getMessage()); }

        // Bevestiging: waarschuwing als een verzoek werd geweigerd, anders gewoon opgeslagen
        if (!empty($_SESSION['b2_vz_waarschuwing'])) {
            flash('info', '⚠️ Let op: ' . $_SESSION['b2_vz_waarschuwing'] . ' Dat verzoek is niet opgeslagen; de rest wel.');
            unset($_SESSION['b2_vz_waarschuwing']);
        } elseif (!empty($_SESSION['b2_toon_opgeslagen'])) {
            flash('succes', 'Opgeslagen!');
        }
        unset($_SESSION['b2_toon_opgeslagen']);

        // AI analyse: toevoegen aan wachtrij (cron verwerkt dit)
        if (function_exists('ai_queue_toevoegen') && instelling('ai.api_key')) {
            try { ai_queue_toevoegen($sol_id, 'blk_analyse'); } catch (Exception $e) {}
        }

        redirect(APP_URL . '/portaal/');
    }
}

// ── Beschikbaarheid weken opslaan ─────────────────────────────
// BLK weken ophalen voor pre-fill in formulier
$blk_weken_sel = [];
if ($blk && !empty($blk['beschikbaarheid_intentie'])) {
    $decoded = json_decode($blk['beschikbaarheid_intentie'], true);
    if (is_array($decoded)) $blk_weken_sel = $decoded;
}

$fases_volgorde = ['aangemeld','uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest','uitgenodigd_tw','tw_geweest','aangenomen'];

// Weken-datums voor de AI-beschikbaarheid JS (weeknr → start/eind datum)
$weken_datums_ai = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen ?? null) : [];
?>
<!DOCTYPE html>
<html lang="nl" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mijn sollicitatie — Paal 13</title>
<?php $_fav = site_favicon_url(); if ($_fav): ?>
<link rel="icon" href="<?= h($_fav) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css?v=<?= @filemtime(__DIR__.'/../assets/css/stijl.css') ?>">
<meta name="robots" content="noindex">
<script>
(function(){
  var t = localStorage.getItem('paal13-theme') || 'light';
  document.documentElement.setAttribute('data-theme', t==='dark'?'dark':'');
  window.togglePortaalTheme = function() {
    var huidig = document.documentElement.getAttribute('data-theme');
    var nieuw  = huidig === 'dark' ? '' : 'dark';
    document.documentElement.setAttribute('data-theme', nieuw);
    localStorage.setItem('paal13-theme', nieuw === 'dark' ? 'dark' : 'light');
    var ico = document.getElementById('themeIcon');
    if (ico) ico.textContent = nieuw === 'dark' ? '☀️' : '🌙';
  };
  document.addEventListener('DOMContentLoaded', function() {
    var ico = document.getElementById('themeIcon');
    if (ico) ico.textContent = t === 'dark' ? '☀️' : '🌙';
  });
})();
</script>
<style>
/* Portaal: licht thema default, dark via toggle */
body { background:var(--bg, #f0f4f8); min-height:100vh; padding:20px; color:var(--tekst, #1a2332); }
.wrap { max-width:680px; margin:0 auto; }
.portaal-top { text-align:center; padding:24px 0 18px; }
.portaal-top img { height:48px; }
.portaal-top h1 { color:var(--navy); font-size:20px; margin:12px 0 3px; }
.portaal-top p { color:var(--tekst-zacht); font-size:13px; }

/* Login */
.login-portaal { background:var(--bg-card, #fff); border-radius:14px; padding:30px; max-width:400px; margin:0 auto; box-shadow:var(--shadow-md, 0 8px 40px rgba(0,0,0,.12)); border:1px solid var(--rand); }
.login-portaal h2 { font-size:16px; color:var(--navy); margin-bottom:5px; }
.login-portaal .sub { font-size:13px; color:var(--tekst-zacht); margin-bottom:18px; }
.code-inputs { display:flex; gap:8px; justify-content:center; margin:14px 0; }
.code-inputs input { width:44px; height:54px; text-align:center; font-size:22px; font-weight:700; border:2px solid var(--rand); border-radius:8px; color:var(--navy); background:var(--bg-card); }
.code-inputs input:focus { border-color:var(--oranje); outline:none; }

/* Voortgang */
.voortgang { display:flex; justify-content:center; background:var(--bg-card); border:1px solid var(--rand); border-radius:10px; padding:14px 8px; margin-bottom:16px; }

/* Dark mode overrides */
html[data-theme="dark"] body { background:#0b0e16; color:#e8edf5; }
html[data-theme="dark"] .login-portaal { background:#161a26; color:#e8edf5; border-color:#2a3142; }
html[data-theme="dark"] .login-portaal h2 { color:#e8edf5; }
html[data-theme="dark"] .portaal-top h1 { color:var(--oranje); }
html[data-theme="dark"] .portaal-top p { color:#a0aec0; }

/* Theme toggle knopje */
.theme-tgl { position:fixed; top:14px; right:14px; width:34px; height:34px; border-radius:50%; border:1px solid var(--rand); background:var(--bg-card); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:15px; transition:all .2s; z-index:100; }
.theme-tgl:hover { transform:scale(1.05); border-color:var(--oranje); }
html[data-theme="dark"] .theme-tgl { background:#161a26; color:#fff; }
.vg-stap { display:flex; flex-direction:column; align-items:center; gap:6px; flex:1; position:relative; }
.vg-stap:not(:last-child)::after { content:''; position:absolute; top:12px; left:50%; right:-50%; height:2px; background:var(--rand); z-index:0; }
.vg-stap.gedaan::after { background:var(--groen); }
.vg-bol { width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; border:2px solid var(--rand); background:var(--grijs-bg); color:var(--grijs); position:relative; z-index:1; }
.vg-stap.gedaan .vg-bol { background:var(--groen); border-color:var(--groen); color:#fff; }
.vg-stap.actief .vg-bol { background:var(--oranje); border-color:var(--oranje); color:#fff; }
.vg-label { font-size:9px; color:var(--grijs); text-align:center; max-width:60px; line-height:1.3; }
.vg-stap.gedaan .vg-label,.vg-stap.actief .vg-label { color:var(--tekst); font-weight:600; }

/* Banners */
.banner { border-radius:10px; padding:14px 16px; margin-bottom:12px; }
.banner-wacht { background:var(--grijs-bg); border:1px solid var(--rand); }
.banner-actie  { background:var(--oranje-bg); border:1px solid var(--oranje); }
.banner-ok     { background:var(--groen-bg); border:1px solid var(--groen); }
.banner-rood   { background:var(--rood-bg); border:1px solid var(--rood); }
.banner h2 { font-size:15px; font-weight:700; color:var(--tekst); margin-bottom:4px; }
.banner p { font-size:13px; color:var(--tekst-zacht); margin:0; }
.banner .detail { font-size:13px; color:var(--tekst); margin-top:7px; padding-top:7px; border-top:1px solid var(--rand-licht, rgba(0,0,0,.08)); }
.banner-actie h2 { color:var(--oranje); }
.banner-ok h2 { color:var(--groen); }
.banner-rood h2 { color:var(--rood); }

/* Multi-step formulier */
.stap-ind { display:flex; gap:6px; margin-bottom:18px; }
.si { flex:1; padding:10px 4px; text-align:center; font-size:11px; color:var(--ink-muted); background:var(--surface); cursor:pointer; border:1px solid var(--border); border-radius:var(--radius-md); transition:all .15s; user-select:none; }
.si.actief { background:var(--navy); color:#fff; font-weight:600; border-color:var(--navy); }
.si.klaar  { background:var(--ok-soft); color:var(--ok); border-color:var(--ok-soft); }
.si .snr   { display:block; font-size:15px; font-weight:700; margin-bottom:2px; }
.formulier-stap { display:none; }
.formulier-stap.zichtbaar { display:block; }
.stap-nav { display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding-top:14px; border-top:1px solid var(--rand); }
.sectie-titel { font-size:12px; font-weight:700; color:var(--navy); text-transform:uppercase; letter-spacing:.05em; border-bottom:2px solid var(--oranje); padding-bottom:5px; margin:16px 0 10px; }

/* Al ingevuld badge */
.blk-status-bar { background:linear-gradient(135deg,#e1f5ee,#d1fae5); border:1px solid #6ee7b7; border-radius:8px; padding:10px 14px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
.blk-status-bar .lbl { font-size:13px; font-weight:600; color:var(--groen); }
.blk-status-bar .sub { font-size:12px; color:var(--groen); }

/* Week selector */
.week-maand { margin-bottom:16px; }
.week-maand-titel { font-size:13px; font-weight:600; color:var(--tekst-zacht); margin-bottom:7px; display:flex; align-items:center; gap:6px; }
.week-maand-titel::after { content:''; flex:1; height:1px; background:var(--rand); }
.week-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:7px; }
.week-opt { position:relative; }
.week-opt input { position:absolute; opacity:0; width:0; height:0; }
.week-opt label { display:flex; flex-direction:column; gap:2px; padding:10px 12px; border-radius:8px; border:1.5px solid var(--rand); background:var(--grijs-bg); cursor:pointer; transition:all .15s; color:var(--tekst); }
.week-opt label:hover { border-color:var(--grijs); background:var(--bg-card); }
.week-opt label strong { font-size:12px; font-weight:600; }
.week-opt label span { font-size:11px; color:var(--tekst-zacht); }
.week-opt input:checked + label { background:rgba(255,107,53,.12); border-color:var(--oranje); color:var(--tekst); }
.week-opt input:checked + label strong { color:var(--oranje-dim); }
html[data-theme="dark"] .week-opt input:checked + label strong { color:#fdba74; }
.week-opt input:checked + label span { opacity:.85; }
.week-teller { font-size:14px; color:var(--tekst-zacht); margin-bottom:14px; }
.week-teller strong { color:var(--oranje); font-size:18px; margin-right:5px; }

/* Upload */
.upload-zone { border:2px dashed var(--rand); border-radius:8px; padding:18px; text-align:center; cursor:pointer; background:var(--bg-card); transition:border-color .15s; }
.upload-zone:hover { border-color:var(--oranje); }
.upload-zone input[type=file] { position:absolute; opacity:0; width:1px; height:1px; pointer-events:none; }

/* Reden wijziging */
.reden-box { background:#fff8f0; border:2px solid var(--oranje); border-radius:8px; padding:14px; margin-top:14px; }
.reden-box label { font-size:13px; font-weight:600; color:var(--navy); display:block; margin-bottom:6px; }
</style>
</head>
<body class="<?= ($stap === 'dossier' && portaal_ingelogd()) ? 'heeft-nav' : '' ?>">
<!-- Lichte/donkere modus toggle (klein knopje rechtsboven) -->
<button type="button" class="theme-tgl" id="themeTgl" onclick="togglePortaalTheme()" title="Wissel licht/donker">
  <span id="themeIcon">🌙</span>
</button>

<div class="wrap">
  <div class="portaal-top">
    <?php $_lgo = site_logo_url(); ?>
    <img src="<?= h($_lgo ?: APP_URL . '/assets/img/logo.png') ?>" alt="Paal 13"
         onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/logo.svg'">
    <?php if ($stap === 'dossier' && $sol): ?>
    <h1>Hoi <?= h($sol['voornaam']) ?>!</h1>
    <p>Jouw sollicitatie — <?= h($sol['sz_naam']) ?></p>
    <?php else: ?>
    <h1>Sollicitantenportaal</h1>
    <p>Reddingsbrigade Paal 13</p>
    <?php endif; ?>
  </div>

  <?php toon_flash(); ?>
  <?php if ($fout): ?><div class="flash flash-fout" style="margin-bottom:12px"><?= h($fout) ?></div><?php endif; ?>
  <?php if (!empty($_SESSION['portaal_flash'])): ?>
    <div class="flash flash-info" style="margin-bottom:12px"><?= h($_SESSION['portaal_flash']) ?></div>
    <?php unset($_SESSION['portaal_flash']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_portaal'])): ?>
    <div class="flash flash-succes" style="margin-bottom:12px"><?= h($_SESSION['flash_portaal']) ?></div>
    <?php unset($_SESSION['flash_portaal']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_portaal_fout'])): ?>
    <div class="flash flash-fout" style="margin-bottom:12px"><?= h($_SESSION['flash_portaal_fout']) ?></div>
    <?php unset($_SESSION['flash_portaal_fout']); ?>
  <?php endif; ?>

<?php if ($stap === 'login'): ?>
  <div class="ds-login">
    <div class="ds-login-kop">
      <div class="ds-login-logo"><?= icoon('golf', 26) ?></div>
      <h1>Welkom terug</h1>
      <p>Vul je e-mailadres in. <?= authentik_actief()
        ? 'Heb je al een Paal 13-account, dan sturen we je automatisch door. Anders krijg je een code van zes cijfers.'
        : 'Je krijgt een code van zes cijfers om in te loggen.' ?></p>
    </div>
    <form method="POST" class="ds-login-form" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerHTML='Versturen…';">
      <?= csrf_input() ?>
      <input type="hidden" name="email_submit" value="1">
      <div class="ds-veld">
        <label class="ds-label">E-mailadres</label>
        <input type="email" name="email" required autofocus autocomplete="email" class="ds-input"
               value="<?= h($_POST['email'] ?? '') ?>" placeholder="jou@voorbeeld.nl">
        <div class="ds-veld-hint">Waarmee je je hebt aangemeld bij Paal 13</div>
      </div>
      <button type="submit" class="ds-btn ds-btn-primair ds-btn-vol">Doorgaan</button>
    </form>
    <div class="ds-login-voet">
      Nog niet aangemeld? <a href="<?= APP_URL ?>/aanmelden.php">Meld je aan</a>
    </div>
  </div>

<?php elseif ($stap === 'code'): ?>
  <div class="ds-login">
    <div class="ds-login-kop">
      <div class="ds-login-logo"><?= icoon('mail', 26) ?></div>
      <h1>Code invoeren</h1>
      <p>We hebben zes cijfers gestuurd naar <strong><?= h($_SESSION['otp_email'] ?? '') ?></strong>. Check ook je spam-map.</p>
    </div>
    <?php if (!empty($fout)): ?>
    <div class="ds-melding ds-melding-error" style="margin-bottom:14px"><?= icoon('let-op', 18) ?> <div><strong>Code onjuist.</strong> <?= h($fout) ?></div></div>
    <?php endif; ?>
    <form method="POST" id="codeForm" class="ds-login-form">
      <?= csrf_input() ?>
      <input type="hidden" name="code_submit" value="1">
      <input type="hidden" name="code" id="codeHidden">
      <div class="ds-code-inputs">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="code-cijfer ds-code-cijfer"
               autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>">
        <?php endfor; ?>
      </div>
      <button type="submit" class="ds-btn ds-btn-primair ds-btn-vol" id="submitBtn" disabled>Inloggen</button>
    </form>
    <div class="ds-login-voet">
      Geen mail ontvangen? <button type="button" class="ds-link-knop" id="opnieuwBtn" disabled>Opnieuw sturen (<span id="teller">0:60</span>)</button>
      <br><a href="<?= APP_URL ?>/portaal/">← Ander e-mailadres</a>
    </div>
  </div>
  <form method="POST" id="opnieuwForm" style="display:none">
    <?= csrf_input() ?>
    <input type="hidden" name="email_submit" value="1">
    <input type="hidden" name="email" value="<?= h($_SESSION['otp_email'] ?? '') ?>">
  </form>
  <script>
  const inp = document.querySelectorAll('.code-cijfer'), h = document.getElementById('codeHidden'), b = document.getElementById('submitBtn');
  inp.forEach((el,i) => {
    el.addEventListener('input', () => { el.value=el.value.replace(/\D/g,'').slice(-1); if(el.value&&i<5) inp[i+1].focus(); upd(); });
    el.addEventListener('keydown', e => { if(e.key==='Backspace'&&!el.value&&i>0) inp[i-1].focus(); });
    el.addEventListener('paste', e => { e.preventDefault(); const v=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6); v.split('').forEach((c,j)=>{if(inp[j])inp[j].value=c;}); inp[Math.min(v.length,5)].focus(); upd(); });
  });
  function upd() { const c=Array.from(inp).map(i=>i.value).join(''); h.value=c; b.disabled=c.length!==6; if(c.length===6)b.focus(); }
  inp[0].focus();
  // Opnieuw-sturen teller: 60s aftellen, daarna knop actief
  (function(){
    var t = 60, el = document.getElementById('teller'), knop = document.getElementById('opnieuwBtn');
    var iv = setInterval(function(){
      t--;
      if (t <= 0) {
        clearInterval(iv);
        knop.disabled = false;
        knop.innerHTML = 'Opnieuw sturen';
        knop.addEventListener('click', function(){ document.getElementById('opnieuwForm').submit(); });
      } else {
        el.textContent = '0:' + (t < 10 ? '0' : '') + t;
      }
    }, 1000);
  })();
  </script>

<?php elseif ($stap === 'doorsturen'): ?>
  <?php $dse = $_SESSION['doorsturen_email'] ?? ''; ?>
  <div class="ds-login">
    <div class="ds-login-kop">
      <div class="ds-login-logo"><?= icoon('slot', 26) ?></div>
      <h1>Je hebt al een account</h1>
      <p>We sturen je door naar het Paal 13-account van <strong><?= h($dse) ?></strong>. Daar log je in zoals je gewend bent.</p>
    </div>
    <div class="ds-melding ds-melding-info" style="margin-bottom:16px">
      <?= icoon('klok', 18) ?> <div>Even geduld — je wordt doorgestuurd…</div>
    </div>
    <a href="<?= APP_URL ?>/portaal/auth_sso.php" class="ds-btn ds-btn-primair ds-btn-vol" id="dsNu">Nu doorgaan</a>
    <form method="POST" style="margin-top:14px">
      <?= csrf_input() ?>
      <input type="hidden" name="toch_code" value="1">
      <div class="ds-login-voet" style="margin-top:0">
        Geen account meer? <button type="submit" class="ds-link-knop">Stuur mij toch een code</button>
      </div>
    </form>
  </div>
  <script>
    setTimeout(function(){ window.location.href = document.getElementById('dsNu').href; }, 2500);
  </script>

<?php endif; ?>

<?php if ($stap === 'dossier' && $sol): ?>

  <?php
    $vnaam = $sol['voornaam'] ?? '';
    $begroetingen = [
      'aangemeld'            => 'Hoi ' . $vnaam . '|Je aanmelding is binnen. We nemen contact op zodra we je uitnodigen.',
      'uitgenodigd_selectie' => 'Je bent uitgenodigd|Er staan een paar dingen voor je klaar.',
      'aangemeld_selectie'   => 'Bijna klaar, ' . $vnaam . '|Je formulieren zijn ontvangen. Tot de selectiedag!',
      'selectiedag_geweest'  => 'Goed gedaan, ' . $vnaam . '|Je was op de selectiedag. We laten snel weten hoe het verder gaat.',
      'uitgenodigd_tw'       => 'Door naar het trainingsweekend|Er staat iets voor je klaar.',
      'tw_geweest'           => 'Bedankt, ' . $vnaam . '|Het trainingsweekend zit erop. We nemen contact op.',
      'aangenomen'           => 'Welkom bij Paal 13|Je bent aangenomen als strandwacht.',
      'afgewezen'            => 'Bedankt voor je sollicitatie|Dit seizoen gaan we niet verder, maar we waarderen je inzet.',
    ];
    $begroet = explode('|', $begroetingen[$sol['fase']] ?? ('Hoi ' . $vnaam . '|'));
  ?>
  <div class="pt-begroet">
    <h1><?= h($begroet[0]) ?></h1>
    <?php if (!empty($begroet[1])): ?><p><?= h($begroet[1]) ?></p><?php endif; ?>
  </div>

  <!-- Voortgang -->
  <?php if ($sol['fase'] !== 'afgewezen'):
    $vg_stappen = [['aangemeld','Aangemeld'],['uitgenodigd_selectie','Selectiedag'],['selectiedag_geweest','Na de selectie'],['uitgenodigd_tw','Trainingsweekend'],['aangenomen','Aangenomen']];
    $fi = array_search($sol['fase'], $fases_volgorde);
    $actief_idx = 0;
    foreach ($vg_stappen as $vi => $vs) {
      $vsi = array_search($vs[0], $fases_volgorde);
      $in_this = in_array($sol['fase'], [$vs[0], $vs[0] === 'uitgenodigd_selectie' ? 'aangemeld_selectie' : ''], true);
      if ($in_this) $actief_idx = $vi;
      elseif ($fi !== false && $vsi !== false && $vsi < $fi) $actief_idx = max($actief_idx, $vi);
    }
    $vg_totaal = count($vg_stappen);
    $vg_pct = round($actief_idx / ($vg_totaal - 1) * 100);
  ?>
  <div class="ds-kaart ds-kaart-p vg3">
    <div class="vg3-top">
      <div class="vg3-nu">
        <span class="vg3-stapnr">Stap <?= $actief_idx + 1 ?> van <?= $vg_totaal ?></span>
        <span class="vg3-naam"><?= h($vg_stappen[$actief_idx][1]) ?></span>
      </div>
      <div class="vg3-bollen">
        <?php foreach ($vg_stappen as $vi => $vs): $c = $vi < $actief_idx ? 'gedaan' : ($vi == $actief_idx ? 'nu' : ''); ?>
          <span class="vg3-bol <?= $c ?>"></span>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="vg3-balk"><div class="vg3-vul" style="width:<?= $vg_pct ?>%"></div></div>
    <?php if ($actief_idx + 1 < $vg_totaal): ?>
    <div class="vg3-volgende">Hierna: <?= h($vg_stappen[$actief_idx + 1][1]) ?></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Status banners -->
  <?php
  $banner_map=[
    'afgewezen'=>['rood','kruis-cirkel','Niet geselecteerd','Na zorgvuldige beoordeling nemen we je dit seizoen niet verder mee.'],
    'aangenomen'=>['ok','feest','Welkom bij Paal 13!','Je bent aangenomen! Je ontvangt binnenkort meer informatie.'],
    'aangemeld'=>['wacht','vink-cirkel','Aanmelding ontvangen','We bekijken alle aanmeldingen en nemen contact op als je wordt uitgenodigd.'.($sol['terugkerend']?' Welkom terug!':'')],
    'selectiedag_geweest'=>['wacht','wachten','Beslissing volgt','Je was aanwezig op de selectiedag. Het bestuur bespreekt de resultaten.'],
    'tw_geweest'=>['wacht','wachten','Na het trainingsweekend','Het bestuur bespreekt de resultaten. Je ontvangt bericht.'],
  ];
  if(isset($banner_map[$sol['fase']])):[$type,$ic,$titel,$tekst]=$banner_map[$sol['fase']]; ?>
  <div class="banner banner-<?=$type?>"><h2><?= icoon($ic, 20) ?> <?=h($titel)?></h2><p><?=h($tekst)?></p></div>
  <?php endif; ?>

  <?php
  // Taken tonen (kennismakingspakket) bij aangenomen sollicitant
  if ($sol['fase'] === 'aangenomen'):
      $tq = db()->prepare("SELECT id, taak_slug, titel, omschrijving, link_url, status, verplicht, voltooid_op FROM sollicitant_taken WHERE sollicitatie_id=? ORDER BY status DESC, verplicht DESC, id");
      try { $tq->execute([$sol['id']]); $taken = $tq->fetchAll(); } catch (Exception $e) { $taken = []; }
      if ($taken):
          $open = count(array_filter($taken, fn($t) => $t['status'] === 'open'));
  ?>
  <div class="banner banner-actie" style="margin-top:14px">

    <p style="margin:6px 0 12px">Voltooi deze stappen om je lidmaatschap af te ronden.</p>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($taken as $t):
        $done = ($t['status'] === 'voltooid');
      ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;background:var(--grijs-bg);border-radius:6px;border:1px solid var(--rand);<?= $done ? 'opacity:.6' : '' ?>">
          <form method="POST" style="margin:0;flex-shrink:0">
            <?= csrf_input() ?>
            <input type="hidden" name="taak_id" value="<?= (int)$t['id'] ?>">
            <button type="submit" name="taak_toggle" value="1" title="<?= $done ? 'Ongedaan maken' : 'Markeer als voltooid' ?>"
                    style="background:<?= $done ? 'var(--groen)' : 'transparent' ?>;color:<?= $done ? '#fff' : 'inherit' ?>;border:2px solid <?= $done ? 'var(--groen)' : 'currentColor' ?>;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:14px;padding:0;line-height:1"><?= $done ? '✓' : '' ?></button>
          </form>
          <div style="flex:1;min-width:0">
            <div style="font-weight:500;<?= $done ? 'text-decoration:line-through' : '' ?>">
              <?= h($t['titel']) ?>
              <?php if (!$t['verplicht']): ?><span style="font-size:11px;opacity:.6;font-weight:400"> (optioneel)</span><?php endif; ?>
            </div>
            <?php if ($t['omschrijving']): ?>
              <div style="font-size:12.5px;opacity:.75;margin-top:2px"><?= nl2br(h($t['omschrijving'])) ?></div>
            <?php endif; ?>
            <?php if ($t['link_url'] && !$done): ?>
              <a href="<?= h($t['link_url']) ?>" style="display:inline-block;margin-top:6px;font-size:12px;color:inherit;text-decoration:underline">Openen &rarr;</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; endif; ?>

  <?php if(in_array($sol['fase'],['uitgenodigd_selectie','aangemeld_selectie'])): ?>
  <div class="banner banner-actie">
    <h2><?= icoon('strand', 20) ?> Uitgenodigd voor de selectiedag</h2>
    <div class="info-grid" style="margin-top:12px">
      <?php if($sol['datum_selectiedag']): ?><div class="info-item"><span class="ii-ic"><?= icoon('kalender', 16) ?></span><div><span class="ii-l">Datum</span><span class="ii-v"><?=datum_nl($sol['datum_selectiedag'])?></span></div></div><?php endif; ?>
      <?php $v = $sol['selectiedag_verzameltijd'] ?? null; if($v): ?><div class="info-item"><span class="ii-ic"><?= icoon('klok', 16) ?></span><div><span class="ii-l">Verzamelen</span><span class="ii-v"><?=h($v)?> uur</span></div></div><?php endif; ?>
      <?php if($sol['locatie_selectiedag']): ?><div class="info-item"><span class="ii-ic"><?= icoon('reddingsboei', 16) ?></span><div><span class="ii-l">Locatie</span><span class="ii-v"><?=h($sol['locatie_selectiedag'])?></span></div></div><?php endif; ?>
      <?php $s = $sol['selectiedag_starttijd'] ?? null; if($s): ?><div class="info-item"><span class="ii-ic"><?= icoon('klok', 16) ?></span><div><span class="ii-l">Start</span><span class="ii-v"><?=h($s)?> uur</span></div></div><?php endif; ?>
    </div>
    <?php if($sol['fase']==='uitgenodigd_selectie'): ?>
    <div class="info-melding info-melding-actie" style="margin-top:12px"><?= icoon('info', 15) ?> Vergeet niet het formulier hieronder in te vullen voor de selectiedag.</div>
    <?php elseif($sol['fase']==='aangemeld_selectie'): ?>
    <div class="info-melding info-melding-ok" style="margin-top:12px"><?= icoon('vink', 15) ?> Jouw formulier is ontvangen. Tot de selectiedag!</div>
    <?php endif; ?>

  <?php
  // ── Universele taken-sectie: openstaande ondertekeningen + fase-taken ──
  $alle_taken = [];

  // 1) Openstaande ondertekeningen voor sollicitant zelf
  try {
      $oq = db()->prepare("SELECT id, type, token, ondertekend_op FROM ondertekeningen WHERE sollicitatie_id=? AND ondertekenaar_rol='sollicitant' ORDER BY aangemaakt_op");
      $oq->execute([$sol['id']]);
      foreach ($oq->fetchAll() as $o) {
          $lbl = match($o['type']) {
              'gezondheid_18plus' => 'Onderteken de gezondheidsverklaring',
              'intake'            => 'Vul het intakeformulier voor de selectiedag in',
              default             => 'Onderteken document',
          };
          $alle_taken[] = [
              'titel'   => $lbl,
              'done'    => !empty($o['ondertekend_op']),
              'url'     => APP_URL . '/portaal/ondertekenen.php?token=' . $o['token'],
              'urgent'  => empty($o['ondertekend_op']),
          ];
      }
  } catch (Exception $e) {}

  // 2) Ouder-ondertekeningen: zonder email → vraag sollicitant om gegevens; met email → status
  $ouder_invullen = [];  // records die nog oudergegevens nodig hebben
  $ouder_docs = [];      // voor het status-overzicht (punt 3)
  try {
      $oq2 = db()->prepare("SELECT id, type, ondertekenaar_naam, ondertekenaar_email, ondertekend_op, aangemaakt_op FROM ondertekeningen WHERE sollicitatie_id=? AND ondertekenaar_rol='ouder' ORDER BY aangemaakt_op");
      $oq2->execute([$sol['id']]);
      foreach ($oq2->fetchAll() as $o) {
          $lbl = match($o['type']) {
              'gezondheid_ouder' => 'Gezondheidsverklaring (door ouder/verzorger)',
              'intake_ouder'     => 'Intakeformulier (door ouder/verzorger)',
              default            => 'Document (ouder)',
          };
          if (empty($o['ondertekenaar_email']) && empty($o['ondertekend_op'])) {
              // Sollicitant moet ouder-gegevens invullen
              $ouder_invullen[] = ['id' => (int)$o['id'], 'label' => $lbl];
          } else {
              $is_klaar = !empty($o['ondertekend_op']);
              // Voor het status-overzicht (punt 3)
              $ouder_docs[] = [
                  'label'    => $lbl,
                  'email'    => $o['ondertekenaar_email'] ?? '',
                  'klaar'    => $is_klaar,
                  'klaar_op' => $o['ondertekend_op'] ?? null,
              ];
          }
      }
  } catch (Exception $e) {}

  if ($ouder_invullen) {
      $alle_taken[] = [
          'titel'   => 'Vul de gegevens van je ouder/verzorger in',
          'done'    => false,
          'url'     => '#oudergegevens',
          'urgent'  => true,
          'hint'    => 'Voor de gezondheidsverklaring en het intakeformulier hebben we een handtekening van je ouder/verzorger nodig.',
      ];
  }

  // 3) BLK invullen als open taak (bij uitgenodigd én aangemeld voor selectiedag)
  if (in_array($sol['fase'], ['uitgenodigd_selectie','aangemeld_selectie'])) {
      // De taak is pas 'gedaan' als het formulier DEFINITIEF is ingediend. Dat blijkt uit
      // de fase (aangemeld_selectie), niet uit het bestaan van een blk-rij — want de
      // autosave maakt al een concept-rij aan tijdens het typen. Voorheen werd de taak
      // daardoor onterecht als afgerond getoond na een tussentijdse pagina-refresh.
      $blk_klaar_taak = ($sol['fase'] === 'aangemeld_selectie');
      array_unshift($alle_taken, [
          'titel'  => 'Vul het "Beter leren kennen" formulier in',
          'done'   => $blk_klaar_taak,
          'url'    => '#blk',
          'urgent' => !$blk_klaar_taak,
      ]);
  }

  // 4) Ouder-gegevens taak voor minderjarigen bij SD-uitnodiging (vóór ouder-docs kunnen bestaan)
  $toon_ouder_form = false;
  if (in_array($sol['fase'], ['uitgenodigd_selectie','aangemeld_selectie'])) {
      $geb = $sol['geboortedatum'] ?? null;
      $lft = function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($geb)
           : ($geb ? (new DateTime($geb))->diff(new DateTime())->y : null);
      if ($lft !== null && $lft < 18) {
          $ouder_bekend = false;
          $ouder_gegevens = null;
          try {
              if (function_exists('personen_ouder_auto_setup')) personen_ouder_auto_setup();
              $pq = db()->prepare("SELECT p.ouder_naam, p.ouder_email, p.ouder_telefoon FROM personen p JOIN sollicitaties s ON s.persoon_id=p.id WHERE s.id=?");
              $pq->execute([$sol['id']]);
              $ouder_gegevens = $pq->fetch() ?: null;
              $ouder_bekend = $ouder_gegevens && !empty($ouder_gegevens['ouder_email']);
          } catch (Exception $e) {}
          if (!$ouder_bekend) {
              array_unshift($alle_taken, [
                  'titel'       => 'Vul de gegevens van je ouder/verzorger in',
                  'done'        => false,
                  'url'         => null,
                  'urgent'      => true,
                  'hint'        => 'Je ouder of verzorger moet twee formulieren ondertekenen omdat je nog geen 18 bent. Na het invullen krijgen ze automatisch een mail.',
                  'inline_form' => 'ouder',
              ]);
          } else {
              // Al ingevuld: toon als afgeronde taak, met mogelijkheid tot wijzigen
              array_unshift($alle_taken, [
                  'titel'       => 'Gegevens ouder/verzorger ingevuld',
                  'done'        => true,
                  'url'         => null,
                  'status'      => 'Ingevuld',
                  'hint'        => 'Ouder/verzorger: ' . trim(($ouder_gegevens['ouder_naam'] ?? '') . ' — ' . ($ouder_gegevens['ouder_email'] ?? '')),
                  'inline_form' => 'ouder',
                  'ouder_data'  => $ouder_gegevens,
              ]);
          }
      }
  }

  // Aftel-banner: beschikbaarheid nog voorlopig + deadline nadert
  $besch_voorlopig = false; $besch_deadline = null; $besch_dgn = null;
  if (function_exists('beschikbaarheid2_van') && function_exists('beschikbaarheid2_deadline')) {
      $_bp = beschikbaarheid2_van($sol['id'])['profiel'] ?? null;
      if ($_bp && !empty($_bp['voorlopig'])) {
          $besch_voorlopig = true;
          $besch_deadline = beschikbaarheid2_deadline($seizoen ?? null);
          if ($besch_deadline) $besch_dgn = (int)floor(($besch_deadline->getTimestamp() - strtotime('today')) / 86400);
      }
  }
  if ($besch_voorlopig):
    $_over = $besch_dgn;
    if ($_over === null)       { $_kleur='var(--oranje)'; $_bg='var(--oranje-bg)'; $_tekst='Je beschikbaarheid staat nog op <strong>voorlopig</strong>. Maak deze op tijd definitief.'; }
    elseif ($_over < 0)        { $_kleur='var(--rood)';   $_bg='var(--rood-bg)';   $_tekst='De deadline voor je beschikbaarheid is <strong>verstreken</strong>. Maak deze zo snel mogelijk definitief, anders kunnen we je niet inplannen.'; }
    elseif ($_over === 0)      { $_kleur='var(--rood)';   $_bg='var(--rood-bg)';   $_tekst='<strong>Vandaag</strong> is de deadline voor je beschikbaarheid! Maak deze nu definitief.'; }
    elseif ($_over <= 7)       { $_kleur='var(--rood)';   $_bg='var(--rood-bg)';   $_tekst='Nog <strong>' . $_over . ' ' . ($_over===1?'dag':'dagen') . '</strong> tot de deadline. Maak je beschikbaarheid definitief.'; }
    else                       { $_kleur='var(--oranje)'; $_bg='var(--oranje-bg)'; $_tekst='Nog <strong>' . $_over . ' dagen</strong> om je beschikbaarheid definitief te maken.'; }
  ?>
  <div class="card" style="margin-top:14px;border-left:4px solid <?= $_kleur ?>;background:<?= $_bg ?>">
    <div style="display:flex;align-items:center;gap:12px;padding:4px 2px">
      <span style="color:var(--warn)"><?= icoon('wachten', 24) ?></span>
      <div style="flex:1">
        <div style="font-weight:600;font-size:14px;margin-bottom:2px">Beschikbaarheid nog niet definitief</div>
        <div style="font-size:13px;color:var(--tekst-zacht);line-height:1.5"><?= $_tekst ?><?= $besch_deadline ? ' <span style="white-space:nowrap">(deadline ' . h(datum_nl($besch_deadline->format('Y-m-d'))) . ')</span>' : '' ?></div>
      </div>
      <a href="<?= APP_URL ?>/portaal/beschikbaarheid.php" class="ds-btn ds-btn-primair ds-btn-sm" style="white-space:nowrap">Bekijken</a>
    </div>
  </div>
  <?php endif; ?>

  <?php
  $open_taken = array_filter($alle_taken, fn($t) => !$t['done']);
  if ($alle_taken):
  ?>
  <div class="ds-kaart" style="margin-top:16px;padding:6px;overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 12px 10px">
      <span style="font:var(--text-subhead);color:var(--ink)"><?= $open_taken ? 'Nu te doen' : 'Jouw acties' ?></span>
      <?php if ($open_taken): ?>
        <span class="ds-badge ds-badge-accent"><?= count($open_taken) ?> open</span>
      <?php else: ?>
        <span class="ds-badge ds-badge-ok"><?= icoon('vink', 13) ?> Alles gedaan</span>
      <?php endif; ?>
    </div>
    <?php foreach ($alle_taken as $ti => $t):
        // Lijn-icoon + accentkleur per taak (ontwerp: geen emoji, geen gekleurde blokranden)
        if ($t['done'])                                     { $ico='vink-cirkel'; $icocls='ok'; }
        elseif (($t['status'] ?? '')==='Wachten op ouder')  { $ico='mail';        $icocls='navy'; }
        elseif (!empty($t['urgent']))                       { $ico='wachten';     $icocls='accent'; }
        else                                                { $ico='mail';        $icocls=''; }
        $heeft_form = (!empty($t['inline_form']) && $t['inline_form'] === 'ouder');
        $klikbaar = (!$t['done'] && !empty($t['url']) && !$heeft_form);
    ?>
        <?php if ($ti > 0): ?><div style="height:1px;background:var(--border);margin:0 12px"></div><?php endif; ?>
        <?php if ($klikbaar): ?><a href="<?= h($t['url']) ?>" style="text-decoration:none;color:inherit;display:block"><?php endif; ?>
        <div style="padding:13px 12px;display:flex;align-items:flex-start;gap:13px;<?= $t['done'] ? 'opacity:.6' : '' ?>">
          <span class="ds-taak-icoon <?= $icocls ?>" style="width:40px;height:40px;border-radius:10px;flex-shrink:0"><?= icoon($ico, 18) ?></span>
          <div style="flex:1;min-width:0">
            <div style="font:var(--text-label);font-size:15px;<?= $t['done'] ? 'text-decoration:line-through;color:var(--ink-muted)' : 'color:var(--ink)' ?>"><?= h($t['titel']) ?></div>
            <?php if (!empty($t['hint'])): ?>
              <div style="font:var(--text-meta);color:var(--ink-muted);margin-top:2px"><?= h($t['hint']) ?></div>
            <?php endif; ?>

            <?php if ($heeft_form): ?>
              <?php $od = $t['ouder_data'] ?? null; $is_ingevuld = !empty($t['done']); ?>
              <?php if ($is_ingevuld): ?>
                <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" style="margin-top:10px" onclick="var f=this.nextElementSibling; f.style.display = f.style.display==='none'?'block':'none';">Wijzigen</button>
              <?php endif; ?>
              <form method="POST" style="margin-top:10px<?= $is_ingevuld ? ';display:none' : '' ?>" onsubmit="return ouderValideer(this)">
                <?= csrf_input() ?>
                <input type="hidden" name="ouder_submit" value="1">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px" class="ouder-grid">
                  <input type="text" name="ouder_naam" placeholder="Naam ouder/verzorger" value="<?= h($od['ouder_naam'] ?? '') ?>" class="ds-input" style="height:42px">
                  <input type="email" name="ouder_email" placeholder="E-mail ouder/verzorger" value="<?= h($od['ouder_email'] ?? '') ?>" class="ds-input" style="height:42px">
                </div>
                <input type="tel" name="ouder_telefoon" placeholder="Telefoonnummer ouder/verzorger" value="<?= h($od['ouder_telefoon'] ?? '') ?>" class="ds-input" style="height:42px;margin-bottom:8px">
                <div class="ouder-fout" style="display:none;color:var(--error);font-size:12px;margin-bottom:8px"></div>
                <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">
                  <span class="btn-tekst"><?= $is_ingevuld ? 'Opnieuw versturen' : 'Versturen naar ouder' ?></span>
                  <span class="btn-spinner" style="display:none">Bezig…</span>
                </button>
              </form>
            <?php endif; ?>
          </div>
          <?php if ($klikbaar): ?><span style="color:var(--ink-muted);flex-shrink:0;align-self:center"><?= icoon('chevron', 18) ?></span><?php endif; ?>
        </div>
        <?php if ($klikbaar): ?></a><?php endif; ?>
      <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php // ── Ouder-status overzicht (punt 3): duidelijke status per document ──
  if (!empty($ouder_docs)):
      $totaal = count($ouder_docs);
      $getekend = count(array_filter($ouder_docs, fn($d) => $d['klaar']));
      $alles_klaar = $getekend === $totaal;
  ?>
  <div class="card" style="margin-top:14px">
    <div class="card-header">
      <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;width:100%;gap:10px">
        <span>Formulieren van je ouder/verzorger</span>
        <span class="badge <?= $alles_klaar ? 'badge-groen' : 'badge-blauw' ?>" style="font-size:11px"><?= $getekend ?>/<?= $totaal ?> ondertekend</span>
      </div>
    </div>
    <div class="card-body">
      <?php if ($alles_klaar): ?>
        <div style="background:var(--groen-bg);border:1px solid var(--groen);border-radius:8px;padding:12px 14px;font-size:13px;color:var(--groen)">
          Alle formulieren zijn ondertekend door je ouder/verzorger. Je hoeft verder niets te doen.
        </div>
      <?php else: ?>
        <p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 12px">
          Je ouder/verzorger heeft per e-mail een link gekregen om deze formulieren te ondertekenen. Zodra dat gebeurd is, zie je dat hier. Nog niets gezien? Een herinnering sturen helpt vaak.
        </p>
      <?php endif; ?>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($ouder_docs as $doc): ?>
          <div style="display:flex;align-items:flex-start;gap:10px;padding:11px 13px;border:1px solid var(--rand);border-radius:8px;background:var(--bg-card)">

            <div style="flex:1;min-width:0">
              <div style="font-weight:600;font-size:13px"><?= h($doc['label']) ?></div>
              <div style="font-size:11.5px;color:var(--tekst-zacht);margin-top:2px">
                <?php if ($doc['klaar']): ?>
                  Ondertekend<?= $doc['klaar_op'] ? ' op ' . datum_nl(substr($doc['klaar_op'],0,10)) : '' ?>
                <?php else: ?>
                  Verstuurd naar <?= h($doc['email']) ?> · wacht op ondertekening
                <?php endif; ?>
              </div>
            </div>
            <span class="badge <?= $doc['klaar'] ? 'badge-groen' : 'badge-blauw' ?>" style="font-size:10px;flex-shrink:0"><?= $doc['klaar'] ? 'Getekend' : 'Wacht' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  </div>
  <?php endif; ?>
  <?php if(in_array($sol['fase'],['uitgenodigd_tw','tw_geweest'])): ?>
  <div class="ds-kaart ds-kaart-navy" style="padding:16px;margin-bottom:16px">
    <div style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:16px;margin-bottom:12px"><?= icoon('reddingsboei', 20) ?> Uitgenodigd voor het trainingsweekend</div>
    <div class="info-grid">
      <?php if($sol['datum_tw_start']): ?><div class="info-item"><span class="ii-ic"><?= icoon('kalender', 16) ?></span><div><span class="ii-l">Datum</span><span class="ii-v"><?=datum_nl($sol['datum_tw_start'])?> t/m <?=datum_nl($sol['datum_tw_eind'])?></span></div></div><?php endif; ?>
      <?php $vtw = $sol['tw_verzameltijd'] ?? null; if($vtw): ?><div class="info-item"><span class="ii-ic"><?= icoon('klok', 16) ?></span><div><span class="ii-l">Verzamelen</span><span class="ii-v"><?=h($vtw)?> uur</span></div></div><?php endif; ?>
      <?php if($sol['locatie_tw']): ?><div class="info-item"><span class="ii-ic"><?= icoon('reddingsboei', 16) ?></span><div><span class="ii-l">Locatie</span><span class="ii-v"><?=h($sol['locatie_tw'])?></span></div></div><?php endif; ?>
    </div>
    <div class="info-melding info-melding-actie" style="margin-top:12px"><?= icoon('info', 15) ?> Hieronder zie je je beschikbaarheid. Klopt er iets niet meer? Geef dat daar aan.</div>
  </div>
  <?php endif; ?>

  <?php // ── BLK Formulier (multi-step) ──────────────────────
  if (in_array($sol['fase'], ['uitgenodigd_selectie','aangemeld_selectie'])): ?>
  <?php // Kaart is standaard verborgen; verschijnt als je op de taak "Openen →" klikt (#blk),
        // of direct zichtbaar als het BLK al is ingevuld (om te kunnen bekijken/bewerken). ?>
  <div class="card" id="blk" <?= $blk ? '' : 'style="display:none"' ?>>
    <div class="card-header" style="cursor:pointer" onclick="blkToggle()">
      <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;width:100%;gap:10px">
        <span style="display:flex;align-items:center;gap:8px;font:var(--text-subhead);color:var(--ink)">
          Vertel ons meer over jezelf
          <?php if ($blk): ?><span class="ds-badge ds-badge-ok" style="font-size:10px"><?= icoon('vink', 12) ?> Ingevuld</span><?php endif; ?>
        </span>
        <button type="button" id="blkToggleBtn" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="event.stopPropagation();blkToggle()"><?= $blk ? 'Bekijken' : 'Invullen' ?></button>
      </div>
    </div>
    <div class="card-body" id="blkBody" <?= $blk ? 'style="display:none"' : 'style="display:none"' ?>>
      <?php if ($blk): ?>
      <!-- Ingevuld: toon samenvatting, verberg formulier tenzij op bewerken geklikt -->
      <div style="background:var(--groen-bg);border:1px solid var(--groen);border-radius:8px;padding:14px 16px;margin-bottom:14px">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
          <div>
            <div style="font-weight:600;color:var(--ok);font-size:14px"><?= icoon("vink", 15) ?> Beter leren kennen — Afgerond</div>
            <div style="font-size:12px;color:var(--groen);margin-top:2px">
              Ingevuld op <?= datum_nl(substr($blk['ingevuld_op']??'',0,10)) ?>
              <?php if (!empty($blk_weken_sel)): ?> · <?= count($blk_weken_sel) ?> <?= count($blk_weken_sel) === 1 ? 'week' : 'weken' ?> geselecteerd<?php endif; ?>
            </div>
          </div>
          <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
                  onclick="document.getElementById('blkFormWrapper').style.display='block';this.closest('div').style.display='none'">
            <?= icoon("potlood", 15) ?> Bewerken
          </button>
        </div>
      </div>
      <div id="blkFormWrapper" style="display:none">
      <?php else: ?>
      <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:14px">Vul dit in vóór de selectiedag. Duurt circa 5 minuten.</p>
      <div id="blkFormWrapper">
      <?php endif; ?>

      <div class="stap-ind" id="stapInd">
        <div class="si actief" onclick="gaNaar(1)" id="si1"><span class="snr">1</span>Gegevens</div>
        <div class="si"        onclick="gaNaar(2)" id="si2"><span class="snr">2</span>Achtergrond</div>
        <div class="si"        onclick="gaNaar(3)" id="si3"><span class="snr">3</span>Motivatie</div>
        <div class="si"        onclick="gaNaar(4)" id="si4"><span class="snr">4</span>Beschikbaar</div>
        <div class="si"        onclick="gaNaar(5)" id="si5"><span class="snr">5</span>Overig</div>
      </div>

      <div id="blkAutosaveStatus" style="font-size:12px;text-align:right;min-height:16px;margin:-4px 0 10px;transition:opacity .4s;opacity:0.5" aria-live="polite">Je invoer wordt automatisch bewaard</div>

      <form method="POST" enctype="multipart/form-data" id="blkForm" onsubmit="return blkValideer(this)">
        <?= csrf_input() ?>
        <input type="hidden" name="blk_submit" value="1">

        <!-- Stap 1: Gegevens -->
        <div class="formulier-stap zichtbaar" id="fs1">
          <div class="sectie-titel">Jouw gegevens</div>
          <div class="form-rij">
            <div class="form-groep"><label>Naam</label><input type="text" value="<?= h(volledige_naam($sol)) ?>" disabled style="background:var(--grijs-bg);color:var(--grijs)"></div>
            <div class="form-groep"><label>E-mail</label><input type="text" value="<?= h($sol['email']) ?>" disabled style="background:var(--grijs-bg);color:var(--grijs)"></div>
          </div>
          <div class="form-rij" style="grid-template-columns:1fr 1fr">
            <div class="form-groep">
              <label>Postcode <span class="vereist">*</span></label>
              <input type="text" name="postcode" id="adr-pc" value="<?= h($blk['postcode'] ?? '') ?>"
                     placeholder="1234 AB" maxlength="7" data-verplicht="1">
            </div>
            <div class="form-groep">
              <label>Huisnummer <span class="vereist">*</span></label>
              <input type="text" name="huisnummer" id="adr-hn" value="<?= h($blk['huisnummer'] ?? '') ?>"
                     placeholder="12a" data-verplicht="1">
            </div>
          </div>
          <div class="form-rij" style="grid-template-columns:2fr 1fr">
            <div class="form-groep">
              <label>Straat</label>
              <input type="text" name="straat" id="adr-st" value="<?= h($blk['straat'] ?? '') ?>" placeholder="Wordt automatisch ingevuld">
            </div>
            <div class="form-groep">
              <label>Woonplaats</label>
              <input type="text" name="woonplaats" id="adr-wp" value="<?= h($blk['woonplaats'] ?? '') ?>" placeholder="Wordt automatisch ingevuld">
            </div>
          </div>
          <div class="form-hulp" id="adr-status" style="margin-top:-6px;margin-bottom:8px;min-height:18px"></div>
          <div class="stap-nav"><div></div><button type="button" class="ds-btn ds-btn-primair" onclick="gaNaar(2)">Volgende</button></div>
        </div>

        <!-- Stap 2: Achtergrond -->
        <div class="formulier-stap" id="fs2">
          <div class="sectie-titel">Achtergrond</div>
          <div class="form-groep"><label>Opleiding <span class="vereist">*</span></label><textarea name="opleiding" rows="2" data-verplicht="2" placeholder="Bijv: HAVO 4, HBO Verpleegkunde jaar 3..."><?= h($blk['opleiding'] ?? '') ?></textarea></div>
          <div class="form-groep"><label>Diploma's en certificaten</label><textarea name="diplomas" rows="2" placeholder="Bijv: Zwemdiploma A+B, EHBO, Junior Lifeguard..."><?= h($blk['diplomas'] ?? '') ?></textarea></div>
          <div class="form-groep"><label>Werkervaring</label><textarea name="werkervaring" rows="2" placeholder="Bijv: bijbaan, vrijwilligerswerk..."><?= h($blk['werkervaring'] ?? '') ?></textarea></div>
          <div class="stap-nav"><button type="button" class="ds-btn ds-btn-secundair" onclick="gaNaar(1)">Terug</button><button type="button" class="ds-btn ds-btn-primair" onclick="gaNaar(3)">Volgende</button></div>
        </div>

        <!-- Stap 3: Motivatie -->
        <div class="formulier-stap" id="fs3">
          <div class="sectie-titel">Motivatie</div>
          <div class="form-groep"><label>Waarom wil je lifeguard worden bij Paal 13? <span class="vereist">*</span></label><textarea name="motivatie_lifeguard" rows="4" data-verplicht="3" placeholder="Vertel ons wat je motiveert..."><?= h($blk['motivatie_lifeguard'] ?? '') ?></textarea></div>
          <div class="form-groep"><label>Wat doe je het liefst op het strand?</label><textarea name="motivatie_strand" rows="2" placeholder="Bijv: zwemmen, volleybal, mensen helpen..."><?= h($blk['motivatie_strand'] ?? '') ?></textarea></div>
          <div class="stap-nav"><button type="button" class="ds-btn ds-btn-secundair" onclick="gaNaar(2)">Terug</button><button type="button" class="ds-btn ds-btn-primair" onclick="gaNaar(4)">Volgende</button></div>
        </div>

        <!-- Stap 4: Beschikbaarheid (periode-blokken model) -->
        <div class="formulier-stap" id="fs4">
          <div class="sectie-titel">Beschikbaarheid</div>
          <?php
            $b2 = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($sol['id']) : ['profiel'=>null,'blokken'=>[],'afwezig'=>[],'verzoeken'=>[]];
            $b2p = $b2['profiel'];
            // Week-opties met datum voor de dropdowns
            $week_opties = [];
            foreach ($alle_weken as $w) $week_opties[$w['week']] = 'W' . $w['week'] . ' · ' . preg_replace('/–.*/', '', $w['periode']);
            $eerste_wk = $alle_weken[0]['week'] ?? 23;
            $laatste_wk = end($alle_weken)['week'] ?? 35;
            // Bestaande blokken, of één standaardblok (hele seizoen, hele weken)
            $bestaande_blokken = $b2['blokken'] ?: [['van_week'=>$eerste_wk,'tot_week'=>$laatste_wk,'regime'=>'vijf','weekend_dagen'=>null]];
          ?>
          <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:12px">
            Geef aan wanneer je kunt werken. De meeste mensen hebben genoeg aan één periode. Standaard is <strong>hele weken (5 dagen)</strong>.
          </p>

          <!-- Gidsje (punt 13) -->
          <details style="margin-bottom:14px;border:1px solid var(--blauw);border-radius:10px;background:var(--blauw-bg)">
            <summary style="padding:11px 16px;cursor:pointer;font-size:13px;font-weight:600;color:var(--blauw)">Hoe vul ik dit in? (voorbeeld)</summary>
            <div style="padding:4px 16px 14px;font-size:12.5px;line-height:1.7;color:var(--tekst)">
              <p style="margin:0 0 8px"><strong>Stel:</strong> je werkt de hele zomervakantie, behalve twee weken dat je zelf op vakantie bent in augustus.</p>
              <p style="margin:0 0 4px">Dan vul je in:</p>
              <ul style="margin:0 0 8px;padding-left:18px">
                <li><strong>Periode:</strong> laat de standaard "hele seizoen · hele weken" staan.</li>
                <li><strong>Afwezig:</strong> voeg je vakantie toe (bijv. 3 t/m 16 augustus).</li>
              </ul>
              <p style="margin:0;color:var(--tekst-zacht)">Klaar! Weet je iets nog niet zeker? Vink onderaan "voorlopig" aan en werk het later bij.</p>
            </div>
          </details>

          <!-- Foutmelding-blok (WCAG: tekst, niet alleen kleur) -->
          <?php
            // Detecteer per uitzondering of er al data is → dan het blok automatisch openklappen
            // (nieuw formulier = alles dicht; terugkerend = toon wat al ingevuld is).
            $losse_dagen   = function_exists('beschikbaarheid_dagen') ? beschikbaarheid_dagen($sol['id']) : [];
            $vaste_sel     = $b2p['vaste_dagen_uit'] ?? [];
            $heeft_afwezig = !empty($b2['afwezig']);
            $heeft_losse   = !empty($losse_dagen);
            $heeft_vaste   = !empty($vaste_sel);
            $heeft_verzoek = false;
            foreach ($b2['verzoeken'] as $vz) { if (($vz['status'] ?? '') === 'open' || ($vz['status'] ?? '') === 'goedgekeurd') { $heeft_verzoek = true; break; } }
          ?>

          <?php if (function_exists('ai_besch_actief') && ai_besch_actief()): ?>
          <!-- AI-hulp: de snelle route (experimenteel, uitschakelbaar via instellingen) -->
          <div style="background:linear-gradient(135deg,rgba(5,77,138,.06),rgba(212,61,0,.05));border:1px solid var(--rand);border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="font-weight:600;font-size:14px;margin-bottom:4px;color:var(--navy)">✨ Liever gewoon typen? <span style="font-size:11px;font-weight:500;color:var(--grijs);background:var(--bg);padding:2px 8px;border-radius:10px;margin-left:4px">experimenteel</span></div>
            <p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 10px">
              Schrijf in je eigen woorden wanneer je kunt, dan vult de AI de velden hieronder alvast in. <strong>Jij controleert het daarna.</strong> Bijvoorbeeld: "de hele zomer, in juni alleen weekenden, 20 juli–3 augustus op vakantie".
            </p>
            <p style="font-size:11.5px;color:var(--grijs);margin:0 0 10px;display:flex;align-items:flex-start;gap:5px">
              <span aria-hidden="true">ℹ️</span>
              <span>Je tekst wordt verwerkt door een externe AI-dienst (Google Gemini). Vul hier geen gevoelige of medische informatie in — dat kan gewoon in het formulier zelf.</span>
            </p>
            <textarea id="aiBeschTekst" rows="2" placeholder="Typ hier je beschikbaarheid…"
                      style="width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid var(--rand);border-radius:8px;font-size:14px;font-family:inherit;resize:vertical"></textarea>
            <div style="display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap">
              <button type="button" class="ds-btn ds-btn-primair ds-btn-sm" id="aiBeschKnop" onclick="aiBeschVerwerk()">
                <span class="ai-tekst">✨ Vul het formulier voor mij in</span>
                <span class="ai-spinner" style="display:none">Bezig…</span>
              </button>
              <span id="aiBeschStatus" style="font-size:12.5px;color:var(--tekst-zacht)"></span>
            </div>
            <div id="aiBeschVraag" style="display:none;margin-top:10px;padding:10px 12px;background:var(--geel-bg);border-left:3px solid var(--geel);border-radius:6px;font-size:13px"></div>
          </div>
          <div style="text-align:center;font-size:12px;color:var(--grijs);margin:2px 0 14px;text-transform:uppercase;letter-spacing:.05em">— of vul het zelf in —</div>
          <?php endif; ?>

          <!-- Foutmelding-blok (WCAG: tekst, niet alleen kleur) -->
          <div id="b2FoutMelding" role="alert" aria-live="assertive" style="display:none;margin-bottom:12px;padding:11px 14px;background:var(--rood-bg);border-left:3px solid var(--rood);border-radius:6px;font-size:13px;color:var(--rood)"></div>

          <?php
            // Melding als het bestuur dagen heeft aangepast (herleidbaar, transparant naar de sollicitant)
            $bestuur_aanpassingen = [];
            if (function_exists('beschikbaarheid_dagen')) {
                foreach (beschikbaarheid_dagen($sol['id']) as $bd) {
                    if (($bd['bron'] ?? '') === 'bestuur') $bestuur_aanpassingen[] = $bd;
                }
            }
            if ($bestuur_aanpassingen):
          ?>
          <div style="margin-bottom:14px;padding:12px 15px;background:var(--blauw-bg,#e8f0f8);border-left:3px solid var(--navy);border-radius:8px;font-size:13px">
            <strong>ℹ️ Een aantal dagen is door het bestuur aangepast.</strong>
            <div style="margin-top:6px;color:var(--tekst-zacht)">
              <?php foreach ($bestuur_aanpassingen as $ba):
                $lbl = date('d-m-Y', strtotime($ba['datum']));
                $status = ($ba['soort'] ?? '') === 'beschikbaar' ? 'beschikbaar gemaakt' : 'op niet-beschikbaar gezet';
              ?>
              <div>• <?= h($lbl) ?> — <?= h($status) ?><?= !empty($ba['reden']) ? ' (' . h($ba['reden']) . ')' : '' ?></div>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:6px;font-size:12px;color:var(--grijs)">Deze aanpassingen gelden boven je eigen invoer. Klopt er iets niet? Neem contact op met het bestuur.</div>
          </div>
          <?php endif; ?>

          <!-- STAP 1: Wanneer kun je werken? (verplicht, standaard hele seizoen) -->
          <div style="background:var(--bg-card);border:1px solid var(--rand);border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
              <span style="flex-shrink:0;width:24px;height:24px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600">1</span>
              <span style="font-weight:600;font-size:14.5px">Wanneer kun je werken?</span>
              <span style="margin-left:auto;font-size:11px;font-weight:600;padding:3px 10px;border-radius:11px;background:var(--oranje-bg);color:var(--oranje)">nodig</span>
            </div>
            <div id="b2BlokLijst">
              <?php foreach ($bestaande_blokken as $blok):
                $is_weekend = ($blok['regime'] ?? '') === 'weekend';
                $wd_sel = $is_weekend ? ($blok['weekend_dagen'] ?: [5,6,7]) : [5,6,7];
              ?>
              <div class="b2-blok-rij" style="border:1px solid var(--rand-licht);border-radius:8px;padding:10px;margin-bottom:8px">
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                  <span style="font-size:12px;color:var(--grijs)">van</span>
                  <select name="b2_blok_van[]" onchange="b2Herbereken()" aria-label="Van week" style="flex:1;min-width:110px">
                    <?php foreach ($week_opties as $wn => $lbl): ?><option value="<?= $wn ?>" <?= (int)$blok['van_week']===$wn?'selected':'' ?>><?= h($lbl) ?></option><?php endforeach; ?>
                  </select>
                  <span style="font-size:12px;color:var(--grijs)">t/m</span>
                  <select name="b2_blok_tot[]" onchange="b2Herbereken()" aria-label="Tot en met week" style="flex:1;min-width:110px">
                    <?php foreach ($week_opties as $wn => $lbl): ?><option value="<?= $wn ?>" <?= (int)$blok['tot_week']===$wn?'selected':'' ?>><?= h($lbl) ?></option><?php endforeach; ?>
                  </select>
                  <select name="b2_blok_regime[]" onchange="b2RegimeCheck(this);b2Herbereken()" aria-label="Regime" style="flex:1;min-width:130px">
                    <option value="vijf" <?= !$is_weekend?'selected':'' ?>>hele weken (5 dagen)</option>
                    <option value="weekend" <?= $is_weekend?'selected':'' ?>>alleen weekenden</option>
                  </select>
                  <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2BlokVerwijder(this)" title="Periode verwijderen" aria-label="Periode verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>
                </div>
                <div class="b2-weekend-dagen" style="<?= $is_weekend?'':'display:none;' ?>margin-top:8px;padding-top:8px;border-top:1px dashed var(--rand-licht)">
                  <span style="font-size:12px;color:var(--tekst-zacht);margin-right:6px">Welke dagen?</span>
                  <?php foreach ([5=>'vr',6=>'za',7=>'zo'] as $dn=>$dl): ?>
                  <label style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border:1px solid var(--rand);border-radius:6px;cursor:pointer;font-size:12.5px;margin-right:4px">
                    <input type="checkbox" class="b2-wkdag" value="<?= $dn ?>" <?= in_array($dn,$wd_sel)?'checked':'' ?> onchange="b2Herbereken()"> <?= $dl ?>
                  </label>
                  <?php endforeach; ?>
                  <input type="hidden" name="b2_blok_wkdagen[]" value="<?= h(json_encode($wd_sel)) ?>">
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2BlokToevoegen()">+ Nog een periode</button>
            <p style="font-size:11.5px;color:var(--grijs);margin-top:8px">Dit staat al ingevuld op het hele seizoen. Klopt dat? Dan hoef je hier niets te doen. Extra weekenden werken mag ook — kies dan "alleen weekenden".</p>
          </div>

          <!-- STAP 2: Uitzonderingen (optioneel, inklapbaar) -->
          <div style="background:var(--bg-card);border:1px solid var(--rand);border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
              <span style="flex-shrink:0;width:24px;height:24px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600">2</span>
              <span style="font-weight:600;font-size:14.5px">Zijn er uitzonderingen?</span>
              <span style="margin-left:auto;font-size:11px;font-weight:600;padding:3px 10px;border-radius:11px;background:var(--grijs-bg);color:var(--tekst-zacht)">optioneel</span>
            </div>
            <p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 12px">Open alleen wat op jou van toepassing is. Geen uitzonderingen? Dan ben je klaar — sla onderaan op.</p>

            <!-- Uitzondering: vakantie/afwezig -->
            <details class="b2-uitz" <?= $heeft_afwezig?'open':'' ?> style="border:1px solid var(--rand);border-radius:10px;margin-bottom:8px;background:var(--bg)">
              <summary style="padding:12px 14px;cursor:pointer;font-size:14px;list-style:none;display:flex;align-items:center;gap:10px">
                <span aria-hidden="true">✈️</span> Ik ben een hele periode weg (vakantie)
                <span class="b2-uitz-chev" style="margin-left:auto;color:var(--grijs)">›</span>
              </summary>
              <div style="padding:0 14px 14px">
                <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 10px">Bijvoorbeeld vakantie of kamp. Voor één losse dag gebruik je het blok hieronder.</p>
                <div id="b2AfwezigLijst">
                  <?php foreach ($b2['afwezig'] as $a): ?>
                  <div class="b2-afwezig-rij" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap">
                    <input type="date" name="b2_afw_van[]" value="<?= h($a['van_datum']) ?>" onchange="b2Herbereken()" aria-label="Afwezig van" style="flex:1;min-width:130px">
                    <span style="color:var(--grijs)">t/m</span>
                    <input type="date" name="b2_afw_tot[]" value="<?= h($a['tot_datum']) ?>" onchange="b2Herbereken()" aria-label="Afwezig tot en met" style="flex:1;min-width:130px">
                    <input type="text" name="b2_afw_label[]" value="<?= h($a['label'] ?? '') ?>" placeholder="bijv. vakantie" aria-label="Omschrijving" style="flex:1;min-width:100px">
                    <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.parentElement.remove();b2Herbereken()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>
                  </div>
                  <?php endforeach; ?>
                </div>
                <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2AfwezigToevoegen()">+ Periode toevoegen</button>
              </div>
            </details>

            <!-- Uitzondering: losse dagen -->
            <details class="b2-uitz" <?= $heeft_losse?'open':'' ?> style="border:1px solid var(--rand);border-radius:10px;margin-bottom:8px;background:var(--bg)">
              <summary style="padding:12px 14px;cursor:pointer;font-size:14px;list-style:none;display:flex;align-items:center;gap:10px">
                Losse dagen dat ik niet kan
                <span class="b2-uitz-chev" style="margin-left:auto;color:var(--grijs)">›</span>
              </summary>
              <div style="padding:0 14px 14px">
                <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 10px">Kun je een week wél werken, maar één losse dag niet (bijv. 12 augustus, of die ene woensdag)? Geef die dag hier op.</p>
                <div id="b2LosseDagLijst">
                  <?php foreach ($losse_dagen as $ld): ?>
                  <div class="b2-losdag-rij" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap">
                    <input type="date" name="b2_dag_datum[]" value="<?= h($ld['datum']) ?>" aria-label="Datum" style="flex:1;min-width:140px">
                    <select name="b2_dag_soort[]" aria-label="Soort" style="flex:1;min-width:130px">
                      <option value="niet_beschikbaar" <?= ($ld['soort']??'')==='niet_beschikbaar'?'selected':'' ?>>Kan ik niet</option>
                      <option value="voorkeur_vrij" <?= ($ld['soort']??'')==='voorkeur_vrij'?'selected':'' ?>>Liever vrij</option>
                    </select>
                    <input type="text" name="b2_dag_reden[]" value="<?= h($ld['reden'] ?? '') ?>" placeholder="reden (optioneel)" aria-label="Reden" style="flex:1;min-width:100px">
                    <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.parentElement.remove()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>
                  </div>
                  <?php endforeach; ?>
                </div>
                <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2LosseDagToevoegen()">+ Dag toevoegen</button>
              </div>
            </details>

            <!-- Uitzondering: vaste vrije dag -->
            <details class="b2-uitz" <?= $heeft_vaste?'open':'' ?> style="border:1px solid var(--rand);border-radius:10px;margin-bottom:8px;background:var(--bg)">
              <summary style="padding:12px 14px;cursor:pointer;font-size:14px;list-style:none;display:flex;align-items:center;gap:10px">
                <span aria-hidden="true">🔁</span> Een vaste dag die ik elke week vrij wil
                <span class="b2-uitz-chev" style="margin-left:auto;color:var(--grijs)">›</span>
              </summary>
              <div style="padding:0 14px 14px">
                <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 10px">
                  Heb je een terugkerende reden waardoor een vaste dag lastig is (school, sport, ander werk)? Geef het door als <strong>voorkeur</strong>.
                </p>
                <input type="checkbox" id="b2VasteAan" <?= $heeft_vaste?'checked':'' ?> onchange="b2VasteToggle()" style="display:none">
                <div id="b2VasteBlok">
                  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
                    <?php foreach ([1=>'ma',2=>'di',3=>'wo',4=>'do',5=>'vr',6=>'za',7=>'zo'] as $dn => $dl): ?>
                    <label style="display:flex;align-items:center;gap:5px;padding:8px 14px;border:1px solid var(--rand);border-radius:6px;cursor:pointer;font-size:13px;min-height:40px">
                      <input type="checkbox" name="b2_vaste_uit[]" value="<?= $dn ?>" <?= in_array($dn, $vaste_sel) ? 'checked' : '' ?>
                             onchange="b2VasteDagCheck();b2Herbereken()"> <?= $dl ?>
                    </label>
                    <?php endforeach; ?>
                  </div>
                  <div style="padding:10px 14px;background:var(--blauw-bg);border-left:3px solid var(--blauw);border-radius:6px;font-size:12.5px;color:var(--tekst);margin-bottom:8px">
                    ℹ️ <strong>Dit is een voorkeur, geen vaste afspraak.</strong> We nemen het mee, maar het betekent niet dat je die dag automatisch vrij krijgt — de planning maken we later samen. Kun je een <strong>weekenddag</strong> (vr/za/zo) écht niet? Dat zijn onze drukste dagen, dus licht dat goed toe.
                  </div>
                  <textarea name="b2_vaste_toelichting" id="b2VasteToelichting" rows="2" placeholder="Waarom zou je deze dag(en) willen vrijhouden?" aria-label="Toelichting vaste vrije dag" style="width:100%"><?= h($b2p['vaste_toelichting'] ?? '') ?></textarea>
                </div>
              </div>
            </details>

            <!-- Uitzondering: minder dagen (verzoek) -->
            <details class="b2-uitz" <?= $heeft_verzoek?'open':'' ?> style="border:1px solid var(--rand);border-radius:10px;background:var(--bg)">
              <summary style="padding:12px 14px;cursor:pointer;font-size:14px;list-style:none;display:flex;align-items:center;gap:10px">
                <span aria-hidden="true">➖</span> In bepaalde weken minder dagen
                <span class="b2-uitz-chev" style="margin-left:auto;color:var(--grijs)">›</span>
              </summary>
              <div style="padding:0 14px 14px">
                <p style="font-size:12px;color:var(--tekst-zacht);margin:0 0 10px">
                  Standaard gaan we uit van 5 dagen per week. Wil je in bepaalde weken minder? Geef het door als <strong>verzoek</strong> — het bestuur beoordeelt dit. Je kunt optioneel aangeven welke dagen je die week <strong>wél</strong> kunt.
                </p>
                <div id="b2VerzoekLijst">
                  <?php foreach ($b2['verzoeken'] as $vz): if ($vz['status']!=='open' && $vz['status']!=='goedgekeurd') continue;
                    $pool_sel = $vz['dagen_pool'] ?: [];
                    // Lege pool betekent "alle dagen" — toon dan alles aangevinkt (i.p.v. impliciet niks).
                    if (!$pool_sel) $pool_sel = [1,2,3,4,5,6,7]; ?>
                  <div class="b2-verzoek-rij" style="border:1px solid var(--rand-licht);border-radius:8px;padding:10px;margin-bottom:8px">
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                      <span style="font-size:12px;color:var(--grijs)">van</span>
                      <select name="b2_vz_van[]" aria-label="Verzoek van week" style="flex:1;min-width:105px">
                        <?php foreach ($week_opties as $wn => $lbl): ?><option value="<?= $wn ?>" <?= (int)$vz['van_week']===$wn?'selected':'' ?>><?= h($lbl) ?></option><?php endforeach; ?>
                      </select>
                      <span style="font-size:12px;color:var(--grijs)">t/m</span>
                      <select name="b2_vz_tot[]" aria-label="Verzoek tot week" style="flex:1;min-width:105px">
                        <?php foreach ($week_opties as $wn => $lbl): ?><option value="<?= $wn ?>" <?= (int)$vz['tot_week']===$wn?'selected':'' ?>><?= h($lbl) ?></option><?php endforeach; ?>
                      </select>
                      <input type="number" name="b2_vz_dagen[]" value="<?= (int)$vz['dagen_aantal'] ?>" min="1" max="6" aria-label="Aantal dagen" style="width:60px" placeholder="dagen">
                      <span style="font-size:12px;color:var(--grijs)">dagen</span>
                      <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.closest('.b2-verzoek-rij').remove()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>
                      <?php if ($vz['status']==='goedgekeurd'): ?><span style="font-size:11px;color:var(--groen)">✓ goedgekeurd</span><?php endif; ?>
                    </div>
                    <input type="text" name="b2_vz_toelichting[]" value="<?= h($vz['toelichting'] ?? '') ?>" placeholder="reden (bijv. school)" aria-label="Reden" style="width:100%;margin-top:6px">
                    <div style="margin-top:8px">
                      <span style="font-size:12px;color:var(--tekst-zacht);margin-right:6px">Welke dagen kun je die week wél? Vink de dagen uit die niet kunnen.</span>
                      <?php foreach ([1=>'ma',2=>'di',3=>'wo',4=>'do',5=>'vr',6=>'za',7=>'zo'] as $dn=>$dl): ?>
                      <label style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border:1px solid var(--rand);border-radius:5px;cursor:pointer;font-size:12px;margin:2px 2px 0 0">
                        <input type="checkbox" class="b2-vz-pooldag" value="<?= $dn ?>" <?= in_array($dn,$pool_sel)?'checked':'' ?>> <?= $dl ?>
                      </label>
                      <?php endforeach; ?>
                      <input type="hidden" name="b2_vz_pool[]" value="<?= h(json_encode($pool_sel)) ?>">
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2VerzoekToevoegen()">+ Verzoek toevoegen</button>
              </div>
            </details>
          </div>

          <!-- Overzicht: zo begrijpen wij het -->
          <div style="background:var(--groen-bg,var(--grijs-bg));border:1px solid var(--rand);border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="font-weight:600;font-size:13.5px;margin-bottom:8px">Zo begrijpen wij het <span id="b2WekenTeller" style="font-weight:400;color:var(--tekst-zacht)"></span></div>
            <div id="b2Preview" aria-live="polite" style="font-size:13px;line-height:1.7;color:var(--tekst)">
              <span style="color:var(--grijs);font-style:italic">Vul je periode in…</span>
            </div>
            <div id="b2OverlapWaarschuwing" style="display:none;margin-top:10px;padding:9px 12px;background:var(--amber-bg);border-left:3px solid var(--amber);border-radius:6px;font-size:12.5px;color:var(--amber)"></div>
          </div>

          <!-- 6. Voorlopig / definitief -->
          <div style="background:var(--oranje-bg);border:1px solid var(--oranje);border-radius:10px;padding:14px 16px;margin-bottom:12px">
            <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
              <input type="checkbox" name="b2_voorlopig" value="1" <?= !empty($b2p['voorlopig']) ? 'checked' : '' ?> style="margin-top:3px;width:18px;height:18px">
              <span style="font-size:13px;line-height:1.6">
                <strong>Mijn beschikbaarheid is nog voorlopig.</strong><br>
                <span style="color:var(--tekst-zacht)">Weet je het nog niet zeker? Geen probleem — vul in wat je nu weet en vink dit aan. Je kunt het later altijd bijwerken via je portaal. <strong>Let op:</strong> werk je het niet bij, dan gebruiken we je huidige invoer automatisch vanaf <strong>2 weken vóór het trainingsweekend</strong>.</span>
              </span>
            </label>
          </div>

          <div class="stap-nav"><button type="button" class="ds-btn ds-btn-secundair" onclick="gaNaar(3)">Terug</button><button type="button" class="ds-btn ds-btn-primair" onclick="b2Valideer()&&gaNaar(5)">Volgende</button></div>
        </div>

        <!-- Stap 5: Overig + reden + versturen -->
        <div class="formulier-stap" id="fs5">
          <div class="sectie-titel">Tot slot</div>
          <div class="form-groep"><label>Social media <span style="font-weight:400;color:var(--grijs)">(optioneel)</span></label><input type="text" name="social_media" value="<?= h($blk['social_media'] ?? '') ?>" placeholder="@jouwinstagram"></div>
          <div class="form-groep">
            <label>Foto van jezelf</label>
            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fotoInput').click()">
              <?php if ($blk['foto_url'] ?? null): ?>
              <img src="<?= h(foto_url($blk['foto_url'])) ?>" style="height:80px;width:80px;object-fit:cover;border-radius:50%;display:block;margin:0 auto 8px">
              <p style="font-size:13px;color:var(--grijs)">Klik om te wijzigen</p>
              <?php else: ?>
              <div style="font-size:28px;margin-bottom:6px">📸</div>
              <p style="font-size:13px;color:var(--grijs)">Klik om te uploaden (JPG/PNG/WebP, max 5MB)</p>
              <?php endif; ?>
              <input type="file" id="fotoInput" name="foto" accept="image/jpeg,image/png,image/webp">
            </div>
          </div>
          <div class="form-groep"><label>Bijzonderheden, medicatie of allergieën</label><textarea name="bijzonderheden" rows="2" placeholder="Iets wat we moeten weten?"><?= h($blk['bijzonderheden'] ?? '') ?></textarea></div>

          <!-- Reden voor wijziging — alleen tonen bij bewerken -->
          <?php if ($blk): ?>
          <div class="reden-box">
            <label>Waarom pas je het formulier aan? <span class="vereist">*</span></label>
            <textarea name="reden_wijziging" rows="2" data-verplicht-edit="5"
                      placeholder="Bijv: Mijn telefoonnummer is gewijzigd, fout in mijn motivatietekst..."
                      style="width:100%;margin-top:4px"></textarea>
            <div class="form-hulp" style="color:var(--amber)">We houden bij wat je aanpast zodat er geen verwarring ontstaat.</div>
          </div>
          <?php endif; ?>

          <div class="stap-nav">
            <button type="button" class="ds-btn ds-btn-secundair" onclick="gaNaar(4)">Terug</button>
            <button type="submit" class="ds-btn ds-btn-primair">
              <?= $blk ? "Bijwerken" : "Insturen" ?>
            </button>
          </div>
        </div>
      </form>
      </div><!-- /blkFormWrapper -->
    </div>
  </div>
  <?php endif; ?>

  <?php // ── Beschikbaarheid tonen (definitief uit BLK) ──────
  if (in_array($sol['fase'], ['uitgenodigd_tw','tw_geweest','aangenomen'])):
    $b2tw = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($sol['id']) : ['profiel'=>null,'blokken'=>[],'afwezig'=>[],'verzoeken'=>[]];
    $b2tw_eff = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol['id']) : [];
    $b2tw_wkd = function_exists('seizoen_weken_datums') ? seizoen_weken_datums() : [];
    $b2tw_lbl = function($wn) use ($b2tw_wkd) { $wn=(int)$wn; return isset($b2tw_wkd[$wn]) ? 'week ' . $wn . ' (' . $b2tw_wkd[$wn]['start']->format('j M') . ')' : 'week ' . $wn; };
    if ($b2tw['profiel'] && $b2tw['blokken']):
    $b2tw_voorlopig = !empty($b2tw['profiel']['voorlopig']);
  ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title"><?= icoon("kalender", 18) ?> Jouw beschikbaarheid</div>
    </div>
    <div class="card-body">
      <?php if ($b2tw_voorlopig): ?>
      <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--oranje-bg);border:1px solid var(--oranje);border-radius:8px;margin-bottom:12px">
        <span style="color:var(--ink-muted)"><?= icoon("wachten", 17) ?></span>
        <div style="font-size:12.5px;color:var(--oranje-dim)">
          <strong>Nog voorlopig.</strong> Werk het op tijd bij en zet het definitief — anders gebruiken we je huidige invoer automatisch vanaf 2 weken vóór het trainingsweekend.
        </div>
      </div>
      <?php else: ?>
      <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--groen-bg);border:1px solid var(--groen);border-radius:8px;margin-bottom:12px;font-size:12.5px;color:var(--groen)">
        ✓ Je beschikbaarheid is definitief.
      </div>
      <?php endif; ?>

      <!-- Compacte samenvatting -->
      <div style="font-size:13px;line-height:1.7;margin-bottom:14px">
        <?php foreach ($b2tw['blokken'] as $blok): ?>
        <div>• <strong><?= h($b2tw_lbl($blok['van_week'])) ?> t/m <?= h($b2tw_lbl($blok['tot_week'])) ?></strong> —
          <?php if ($blok['regime']==='weekend'):
            $wd = $blok['weekend_dagen'] ?: [5,6,7];
            echo 'alleen ' . implode('/', array_map('dag_kort', $wd));
          else: echo 'hele weken (5 dagen)'; endif; ?>
        </div>
        <?php endforeach; ?>
        <div style="color:var(--grijs);margin-top:4px"><?= count($b2tw_eff) ?> <?= count($b2tw_eff) === 1 ? 'week' : 'weken' ?> beschikbaar<?php if (!empty($b2tw['afwezig'])): ?> · <?= count($b2tw['afwezig']) ?> afwezigheidsperiode<?= count($b2tw['afwezig'])>1?'s':'' ?><?php endif; ?></div>
      </div>

      <a href="<?= APP_URL ?>/portaal/beschikbaarheid.php" class="ds-btn ds-btn-secundair ds-btn-sm">Bekijken & aanpassen →</a>
    </div>
  </div>
  <?php endif; endif; ?>

  <div style="text-align:center;margin-top:18px;padding-bottom:24px">
    <?php
      // Switch naar beheer — alleen als deze sollicitant ook beheertoegang heeft.
      $_kan_beheer = false;
      $_p_email = $_SESSION['portaal_email'] ?? '';
      if (function_exists('toegang_bepaal') && $_p_email) {
          $_t = toegang_bepaal($_p_email);
          $_kan_beheer = $_t['beheer'];
      }
      if ($_kan_beheer):
    ?>
    <a href="<?= APP_URL ?>/wissel.php?naar=beheer" style="font-size:12px;color:var(--tekst-zacht);text-decoration:none">↔ Naar beheer</a>
    &nbsp;·&nbsp;
    <?php endif; ?>
    <a href="<?= APP_URL ?>/portaal/?uitloggen=1" style="font-size:12px;color:var(--tekst-zacht);text-decoration:none">Uitloggen</a>
    &nbsp;·&nbsp;
    <a href="https://paal13.com" style="font-size:12px;color:var(--tekst-zacht);text-decoration:none">paal13.com</a>
  </div>
<?php endif; ?>
</div>

<script>
let huidig = 1;
function gaNaar(nr) {
  if (nr > huidig) {
    const el = document.getElementById('fs' + huidig);
    for (const f of el.querySelectorAll('[data-verplicht]')) {
      if (!f.value.trim()) {
        f.focus();
        f.style.borderColor='var(--rood)';
        f.setAttribute('aria-invalid','true');
        // Zichtbare tekstmelding i.p.v. alleen kleur (WCAG)
        let melding = el.querySelector('.stap-fout');
        if (!melding) {
          melding = document.createElement('div');
          melding.className = 'stap-fout';
          melding.setAttribute('role','alert');
          melding.style.cssText = 'margin-bottom:12px;padding:11px 14px;background:var(--rood-bg);border-left:3px solid var(--rood);border-radius:6px;font-size:13px;color:var(--rood)';
          el.insertBefore(melding, el.firstChild.nextSibling);
        }
        const lbl = f.closest('.form-groep')?.querySelector('label')?.textContent.replace('*','').trim() || 'een verplicht veld';
        melding.textContent = '⚠️ Vul eerst "' + lbl + '" in voordat je verdergaat.';
        melding.scrollIntoView({behavior:'smooth', block:'center'});
        setTimeout(()=>{ f.style.borderColor=''; f.removeAttribute('aria-invalid'); }, 4000);
        return;
      }
    }
    // Alles ingevuld → wis eventuele foutmelding van deze stap
    const oudeFout = el.querySelector('.stap-fout'); if (oudeFout) oudeFout.remove();
  }
  document.querySelectorAll('.formulier-stap').forEach(e=>e.classList.remove('zichtbaar'));
  document.getElementById('fs'+nr).classList.add('zichtbaar');
  document.querySelectorAll('.si').forEach((e,i)=>{
    e.classList.remove('actief','klaar');
    if(i+1<nr) e.classList.add('klaar');
    else if(i+1===nr) e.classList.add('actief');
  });
  huidig = nr;
  const top = document.querySelector('.card-body');
  if (top) window.scrollTo({top: top.offsetTop - 20, behavior:'smooth'});
}

document.getElementById('fotoInput')?.addEventListener('change', function() {
  if (!this.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    // BELANGRIJK: behoud de file input, vervang alleen de preview-elementen!
    const zone = document.getElementById('uploadZone');
    // Verwijder alle elementen behalve de input zelf
    Array.from(zone.children).forEach(el => { if (el.tagName !== 'INPUT') el.remove(); });
    // Voeg preview toe vóór de input
    const wrap = document.createElement('div');
    wrap.innerHTML = '<img src="'+e.target.result+'" style="height:90px;width:90px;object-fit:cover;border-radius:50%;display:block;margin:0 auto 8px"><p style="font-size:13px;color:var(--tekst-zacht)">✓ Foto geselecteerd — klik om te wijzigen</p>';
    zone.insertBefore(wrap, zone.querySelector('input'));
  };
  r.readAsDataURL(this.files[0]);
});

/* ── Adres validatie via PDOK ─────────────────────────────────── */
(function() {
  const pc = document.getElementById('adr-pc');
  const hn = document.getElementById('adr-hn');
  const st = document.getElementById('adr-st');
  const wp = document.getElementById('adr-wp');
  const status = document.getElementById('adr-status');
  if (!pc || !hn) return;

  let timer;
  function valideer() {
    const pcVal = pc.value.replace(/\s+/g,'').toUpperCase();
    if (!/^[0-9]{4}[A-Z]{2}$/.test(pcVal) || !hn.value.trim()) {
      status.textContent = '';
      return;
    }
    status.textContent = 'Adres opzoeken…';
    status.style.color = 'var(--grijs)';
    fetch('<?= APP_URL ?>/portaal/api_adres.php?postcode=' + encodeURIComponent(pcVal) + '&huisnummer=' + encodeURIComponent(hn.value.trim()))
      .then(r => r.json())
      .then(d => {
        if (d.ok) {
          // Alleen overschrijven als leeg of als de gebruiker niet recent heeft getypt
          if (!st.dataset.touched) st.value = d.straat;
          if (!wp.dataset.touched) wp.value = d.woonplaats;
          pc.value = d.postcode;
          status.textContent = '✓ ' + d.straat + ', ' + d.woonplaats;
          status.style.color = 'var(--groen)';
        } else {
          status.textContent = '⚠ ' + (d.fout || 'Adres niet gevonden — vul handmatig in');
          status.style.color = '#e55a25';
        }
      })
      .catch(() => {
        status.textContent = '⚠ Kon adres niet automatisch ophalen';
        status.style.color = '#e55a25';
      });
  }

  function debouncedValideer() { clearTimeout(timer); timer = setTimeout(valideer, 400); }

  pc.addEventListener('input', debouncedValideer);
  hn.addEventListener('input', debouncedValideer);
  st.addEventListener('input', () => st.dataset.touched = '1');
  wp.addEventListener('input', () => wp.dataset.touched = '1');
})();
</script>
<script>
/* ── Beschikbaarheid: periode-blokken + preview in mensentaal ── */
const B2_WEEKLABEL = <?= json_encode($week_opties ?? []) ?>;
const B2_EERSTE = <?= (int)($eerste_wk ?? 23) ?>;
const B2_LAATSTE = <?= (int)($laatste_wk ?? 35) ?>;
const B2_DAGNL = {1:'maandag',2:'dinsdag',3:'woensdag',4:'donderdag',5:'vrijdag',6:'zaterdag',7:'zondag'};

function b2WeekOpties(sel) {
  let h = '';
  for (const wn in B2_WEEKLABEL) h += '<option value="' + wn + '"' + (parseInt(wn)===sel?' selected':'') + '>' + B2_WEEKLABEL[wn] + '</option>';
  return h;
}

function b2BlokToevoegen() {
  const div = document.createElement('div');
  div.className = 'b2-blok-rij';
  div.style.cssText = 'border:1px solid var(--rand-licht);border-radius:8px;padding:10px;margin-bottom:8px';
  div.innerHTML =
    '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">' +
      '<span style="font-size:12px;color:var(--grijs)">van</span>' +
      '<select name="b2_blok_van[]" onchange="b2Herbereken()" aria-label="Van week" style="flex:1;min-width:110px">' + b2WeekOpties(B2_EERSTE) + '</select>' +
      '<span style="font-size:12px;color:var(--grijs)">t/m</span>' +
      '<select name="b2_blok_tot[]" onchange="b2Herbereken()" aria-label="Tot en met week" style="flex:1;min-width:110px">' + b2WeekOpties(B2_LAATSTE) + '</select>' +
      '<select name="b2_blok_regime[]" onchange="b2RegimeCheck(this);b2Herbereken()" aria-label="Regime" style="flex:1;min-width:130px"><option value="vijf">hele weken (5 dagen)</option><option value="weekend">alleen weekenden</option></select>' +
      '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="b2BlokVerwijder(this)" aria-label="Periode verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>' +
    '</div>' +
    '<div class="b2-weekend-dagen" style="display:none;margin-top:8px;padding-top:8px;border-top:1px dashed var(--rand-licht)">' +
      '<span style="font-size:12px;color:var(--tekst-zacht);margin-right:6px">Welke dagen?</span>' +
      [[5,'vr'],[6,'za'],[7,'zo']].map(d =>
        '<label style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border:1px solid var(--rand);border-radius:6px;cursor:pointer;font-size:12.5px;margin-right:4px"><input type="checkbox" class="b2-wkdag" value="'+d[0]+'" checked onchange="b2Herbereken()"> '+d[1]+'</label>'
      ).join('') +
      '<input type="hidden" name="b2_blok_wkdagen[]" value="[5,6,7]">' +
    '</div>';
  document.getElementById('b2BlokLijst').appendChild(div);
  b2Herbereken();
}

function b2BlokVerwijder(btn) {
  const rijen = document.querySelectorAll('#b2BlokLijst .b2-blok-rij');
  if (rijen.length <= 1) { b2Fout('Je moet minimaal één periode opgeven.'); return; }
  btn.closest('.b2-blok-rij').remove();
  b2Herbereken();
}

/* ── AI-beschikbaarheid (experimenteel) ──────────────────── */
function aiBeschVerwerk() {
  const veld = document.getElementById('aiBeschTekst');
  const tekst = (veld?.value || '').trim();
  const status = document.getElementById('aiBeschStatus');
  const vraagBox = document.getElementById('aiBeschVraag');
  const knop = document.getElementById('aiBeschKnop');
  if (!tekst) { status.textContent = 'Typ eerst iets in.'; return; }

  // Blokkeer input + knop tijdens verwerken
  const t = knop.querySelector('.ai-tekst'), s = knop.querySelector('.ai-spinner');
  const zetBezig = (bezig) => {
    knop.disabled = bezig;
    veld.readOnly = bezig;
    veld.style.opacity = bezig ? '0.6' : '';
    veld.style.cursor = bezig ? 'not-allowed' : '';
    if (t&&s){ t.style.display = bezig ? 'none' : ''; s.style.display = bezig ? '' : 'none'; }
  };
  zetBezig(true);
  status.textContent = 'De AI leest je tekst…'; vraagBox.style.display = 'none';

  const csrf = document.querySelector('[name="_csrf"]')?.value || '';
  fetch(location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ ai_besch_submit:'1', tekst, _csrf:csrf }) })
    .then(r => r.json())
    .then(d => {
      zetBezig(false);
      if (d.status === 'uit')      { status.textContent = 'De AI-hulp staat uit.'; return; }
      if (d.status === 'fout')     { status.textContent = d.bericht || 'Er ging iets mis.'; return; }
      if (d.status === 'geweigerd'){ status.textContent = ''; vraagBox.style.display=''; vraagBox.style.background='var(--rood-bg)'; vraagBox.style.borderColor='var(--rood)'; vraagBox.textContent = '⚠️ ' + (d.bericht || 'Ik kon geen beschikbaarheid herkennen.'); return; }
      if (d.status === 'vraag')    { status.textContent = ''; vraagBox.style.display=''; vraagBox.style.background='var(--geel-bg)'; vraagBox.style.borderColor='var(--geel)'; vraagBox.textContent = '❓ ' + d.vraag; return; }
      if (d.status === 'ok') {
        aiBeschVulIn(d);
        status.textContent = '✓ Ingevuld — controleer hieronder of het klopt.';
        if (d.vraag) { vraagBox.style.display=''; vraagBox.style.background='var(--geel-bg)'; vraagBox.style.borderColor='var(--geel)'; vraagBox.textContent = '❓ ' + d.vraag; }
      }
    })
    .catch(() => {
      zetBezig(false);
      status.textContent = 'Kon de AI niet bereiken. Vul het formulier handmatig in.';
    });
}

// Vul de bestaande formuliervelden met de (gevalideerde) AI-data
function aiBeschVulIn(d) {
  // Periode-blokken: bestaande rijen wissen, dan opnieuw opbouwen
  const lijst = document.getElementById('b2BlokLijst');
  lijst.innerHTML = '';
  (d.blokken || []).forEach(blok => {
    b2BlokToevoegen();
    const rij = lijst.lastElementChild;
    rij.querySelector('[name="b2_blok_van[]"]').value = blok.van_week;
    rij.querySelector('[name="b2_blok_tot[]"]').value = blok.tot_week;
    const regimeSel = rij.querySelector('[name="b2_blok_regime[]"]');
    regimeSel.value = blok.regime === 'weekend' ? 'weekend' : 'vijf';
    b2RegimeCheck(regimeSel);
    if (blok.regime === 'weekend' && Array.isArray(blok.weekend_dagen)) {
      rij.querySelectorAll('.b2-wkdag').forEach(cb => {
        cb.checked = blok.weekend_dagen.includes(parseInt(cb.value));
      });
      const hidden = rij.querySelector('[name="b2_blok_wkdagen[]"]');
      if (hidden) hidden.value = JSON.stringify(blok.weekend_dagen);
    }
  });
  if (!(d.blokken || []).length) b2BlokToevoegen(); // altijd minstens één rij

  // Afwezigheid
  if ((d.afwezig || []).length) {
    const afwLijst = document.getElementById('b2AfwezigLijst');
    if (afwLijst) {
      afwLijst.innerHTML = '';
      d.afwezig.forEach(a => {
        b2AfwezigToevoegen();
        const rij = afwLijst.lastElementChild;
        // Afwezigheid komt nu als exacte datums uit de AI (halve weken blijven behouden).
        // Terugval: als er toch nog een weeknummer komt, zetten we die om naar maandag/zondag.
        const van = a.van_datum || (a.van_week ? aiWeekNaarDatum(a.van_week, 'start') : '');
        const tot = a.tot_datum || (a.tot_week ? aiWeekNaarDatum(a.tot_week, 'eind') : '');
        if (van) rij.querySelector('[name="b2_afw_van[]"]').value = van;
        if (tot) rij.querySelector('[name="b2_afw_tot[]"]').value = tot;
        const lbl = rij.querySelector('[name="b2_afw_label[]"]');
        if (lbl && a.label) lbl.value = a.label;
      });
    }
  }

  // Losse dagen (concrete datums die iemand niet kan / liever vrij wil)
  if ((d.losse_dagen || []).length) {
    const losLijst = document.getElementById('b2LosseDagLijst');
    if (losLijst) {
      losLijst.innerHTML = '';
      d.losse_dagen.forEach(ld => {
        b2LosseDagToevoegen();
        const rij = losLijst.lastElementChild;
        if (ld.datum) rij.querySelector('[name="b2_dag_datum[]"]').value = ld.datum;
        const soortSel = rij.querySelector('[name="b2_dag_soort[]"]');
        if (soortSel && ld.soort) soortSel.value = ld.soort;
        const redenInp = rij.querySelector('[name="b2_dag_reden[]"]');
        if (redenInp && ld.reden) redenInp.value = ld.reden;
      });
    }
  }

  // Vaste vrije dag(en) — weekdagnummers die elke week vrij zijn
  if ((d.vaste_vrije_dagen || []).length) {
    // Vink de juiste weekdagen aan
    document.querySelectorAll('[name="b2_vaste_uit[]"]').forEach(cb => {
      cb.checked = d.vaste_vrije_dagen.map(String).includes(cb.value);
    });
    const toel = document.getElementById('b2VasteToelichting');
    if (toel && d.vaste_reden) toel.value = d.vaste_reden;
    const cbAan = document.getElementById('b2VasteAan');
    if (cbAan) cbAan.checked = true;
    if (typeof b2VasteToggle === 'function') b2VasteToggle();
  }

  // Verzoeken — minder dagen in bepaalde weken
  if ((d.verzoeken || []).length) {
    const vzLijst = document.getElementById('b2VerzoekLijst');
    if (vzLijst) {
      vzLijst.innerHTML = '';
      d.verzoeken.forEach(vz => {
        b2VerzoekToevoegen();
        const rij = vzLijst.lastElementChild;
        if (vz.van_week) { const s = rij.querySelector('[name="b2_vz_van[]"]'); if (s) s.value = vz.van_week; }
        if (vz.tot_week) { const s = rij.querySelector('[name="b2_vz_tot[]"]'); if (s) s.value = vz.tot_week; }
        if (vz.dagen_aantal) { const n = rij.querySelector('[name="b2_vz_dagen[]"]'); if (n) n.value = vz.dagen_aantal; }
        const toel = rij.querySelector('[name="b2_vz_toelichting[]"]');
        if (toel && vz.toelichting) toel.value = vz.toelichting;
        // Pool-dagen aanvinken
        if ((vz.pool || []).length) {
          rij.querySelectorAll('.b2-vz-pooldag').forEach(cb => {
            cb.checked = vz.pool.map(String).includes(cb.value);
          });
        }
      });
    }
  }

  // Uitzonderingen die de AI invulde meteen uitklappen, zodat de gebruiker ze ziet
  b2OpenDetailsMet('b2AfwezigLijst',   (d.afwezig || []).length);
  b2OpenDetailsMet('b2LosseDagLijst',  (d.losse_dagen || []).length);
  b2OpenDetailsMet('b2VasteBlok',      (d.vaste_vrije_dagen || []).length);
  b2OpenDetailsMet('b2VerzoekLijst',   (d.verzoeken || []).length);

  b2Herbereken();
  // Scroll naar het formulier zodat de gebruiker het resultaat ziet
  document.getElementById('b2BlokLijst')?.scrollIntoView({behavior:'smooth', block:'center'});
}

// Klap het <details>-blok open dat een bepaald element bevat (bij AI-invulling)
function b2OpenDetailsMet(elementId, conditie) {
  if (!conditie) return;
  const el = document.getElementById(elementId);
  if (!el) return;
  const details = el.closest('details.b2-uitz');
  if (details) details.open = true;
}

// Weeknummer → datum (op basis van de week-opties die al in de select staan)
const AI_WEEK_DATUMS = <?= json_encode(array_map(fn($w) => [
    'wk'    => $w,
    'start' => ($weken_datums_ai[$w]['start'] ?? null) instanceof DateTimeInterface ? $weken_datums_ai[$w]['start']->format('Y-m-d') : '',
    'eind'  => ($weken_datums_ai[$w]['eind']  ?? null) instanceof DateTimeInterface ? $weken_datums_ai[$w]['eind']->format('Y-m-d')  : '',
], array_keys($weken_datums_ai ?? []))) ?>;
function aiWeekNaarDatum(wk, welke) {
  const m = AI_WEEK_DATUMS.find(x => x.wk === parseInt(wk));
  return m ? (welke === 'start' ? m.start : m.eind) : null;
}

function b2RegimeCheck(sel) {
  const rij = sel.closest('.b2-blok-rij');
  const wk = rij.querySelector('.b2-weekend-dagen');
  if (wk) wk.style.display = sel.value === 'weekend' ? '' : 'none';
}

function b2AfwezigToevoegen() {
  const div = document.createElement('div');
  div.className = 'b2-afwezig-rij';
  div.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap';
  div.innerHTML = '<input type="date" name="b2_afw_van[]" onchange="b2Herbereken()" aria-label="Afwezig van" style="flex:1;min-width:130px">' +
    '<span style="color:var(--grijs)">t/m</span>' +
    '<input type="date" name="b2_afw_tot[]" onchange="b2Herbereken()" aria-label="Afwezig tot en met" style="flex:1;min-width:130px">' +
    '<input type="text" name="b2_afw_label[]" placeholder="bijv. vakantie" aria-label="Omschrijving" style="flex:1;min-width:100px">' +
    '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.parentElement.remove();b2Herbereken()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>';
  document.getElementById('b2AfwezigLijst').appendChild(div);
}

function b2LosseDagToevoegen() {
  const div = document.createElement('div');
  div.className = 'b2-losdag-rij';
  div.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:6px;flex-wrap:wrap';
  div.innerHTML = '<input type="date" name="b2_dag_datum[]" aria-label="Datum" style="flex:1;min-width:140px">' +
    '<select name="b2_dag_soort[]" aria-label="Soort" style="flex:1;min-width:130px"><option value="niet_beschikbaar">Kan ik niet</option><option value="voorkeur_vrij">Liever vrij</option></select>' +
    '<input type="text" name="b2_dag_reden[]" placeholder="reden (optioneel)" aria-label="Reden" style="flex:1;min-width:100px">' +
    '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.parentElement.remove()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>';
  document.getElementById('b2LosseDagLijst').appendChild(div);
}

function b2VerzoekToevoegen() {
  const div = document.createElement('div');
  div.className = 'b2-verzoek-rij';
  div.style.cssText = 'border:1px solid var(--rand-licht);border-radius:8px;padding:10px;margin-bottom:8px';
  div.innerHTML =
    '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">' +
      '<span style="font-size:12px;color:var(--grijs)">van</span>' +
      '<select name="b2_vz_van[]" aria-label="Verzoek van week" style="flex:1;min-width:105px">' + b2WeekOpties(B2_EERSTE) + '</select>' +
      '<span style="font-size:12px;color:var(--grijs)">t/m</span>' +
      '<select name="b2_vz_tot[]" aria-label="Verzoek tot week" style="flex:1;min-width:105px">' + b2WeekOpties(B2_LAATSTE) + '</select>' +
      '<input type="number" name="b2_vz_dagen[]" min="1" max="6" aria-label="Aantal dagen" style="width:60px" placeholder="dagen">' +
      '<span style="font-size:12px;color:var(--grijs)">dagen</span>' +
      '<button type="button" class="ds-btn ds-btn-secundair ds-btn-sm" onclick="this.closest(\'.b2-verzoek-rij\').remove()" aria-label="Verwijderen" style="min-width:44px;min-height:38px"><?= icoon("kruis", 15) ?></button>' +
    '</div>' +
    '<input type="text" name="b2_vz_toelichting[]" placeholder="reden (bijv. school)" aria-label="Reden" style="width:100%;margin-top:6px">' +
    '<div style="margin-top:8px">' +
      '<span style="font-size:12px;color:var(--tekst-zacht);margin-right:6px">Welke dagen kun je die week wél? Vink de dagen uit die niet kunnen.</span>' +
      [[1,'ma'],[2,'di'],[3,'wo'],[4,'do'],[5,'vr'],[6,'za'],[7,'zo']].map(d =>
        '<label style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border:1px solid var(--rand);border-radius:5px;cursor:pointer;font-size:12px;margin:2px 2px 0 0"><input type="checkbox" class="b2-vz-pooldag" value="'+d[0]+'" checked> '+d[1]+'</label>'
      ).join('') +
      '<input type="hidden" name="b2_vz_pool[]" value="[1,2,3,4,5,6,7]">' +
    '</div>';
  document.getElementById('b2VerzoekLijst').appendChild(div);
}

function b2VasteToggle() {
  // Het vaste-dag-blok zit nu in een uitklapbare <details>; die regelt zichtbaarheid.
  // Deze functie houdt alleen de checkbox-status in sync (voor bestaande aanroepen).
  const cb = document.getElementById('b2VasteAan');
  const blok = document.getElementById('b2VasteBlok');
  if (blok) blok.style.display = '';
  // Als iemand alle dagen uitvinkt, hoeft er niets gewist te worden — leeg = geen voorkeur.
}

function b2VasteDagCheck() { /* voorkeur — geen harde eis meer, preview verzorgt de rest */ }

function b2Fout(tekst) {
  const box = document.getElementById('b2FoutMelding');
  if (!box) { alert(tekst); return; }
  box.textContent = '⚠️ ' + tekst;
  box.style.display = '';
  box.scrollIntoView({behavior:'smooth', block:'center'});
}
function b2FoutWis() { const box = document.getElementById('b2FoutMelding'); if (box) box.style.display = 'none'; }

function b2Herbereken() {
  // Serialiseer weekend-dagen + verzoek-pools naar hidden inputs
  document.querySelectorAll('#b2BlokLijst .b2-blok-rij').forEach(rij => {
    const hid = rij.querySelector('[name="b2_blok_wkdagen[]"]');
    if (hid) {
      const dagen = Array.from(rij.querySelectorAll('.b2-wkdag:checked')).map(c => parseInt(c.value));
      hid.value = JSON.stringify(dagen.length ? dagen : [5,6,7]);
    }
  });
  document.querySelectorAll('#b2VerzoekLijst .b2-verzoek-rij').forEach(rij => {
    const hid = rij.querySelector('[name="b2_vz_pool[]"]');
    const aangevinkt = Array.from(rij.querySelectorAll('.b2-vz-pooldag:checked')).map(c => parseInt(c.value));
    if (hid) hid.value = JSON.stringify(aangevinkt);

    // Validatie: aantal aangevinkte dagen moet minstens gelijk zijn aan het gevraagde aantal
    const aantalInput = rij.querySelector('[name="b2_vz_dagen[]"]');
    const gevraagd = aantalInput ? parseInt(aantalInput.value) || 0 : 0;
    let waarschuwing = rij.querySelector('.b2-vz-waarschuwing');
    if (!waarschuwing) {
      waarschuwing = document.createElement('div');
      waarschuwing.className = 'b2-vz-waarschuwing';
      waarschuwing.style.cssText = 'font-size:12px;color:var(--oranje);margin-top:6px;display:none';
      rij.appendChild(waarschuwing);
    }
    if (gevraagd > 0 && aangevinkt.length < gevraagd) {
      waarschuwing.textContent = '⚠️ Je vraagt ' + gevraagd + ' dagen aan, maar hebt er ' + aangevinkt.length + ' aangevinkt. Vink minstens ' + gevraagd + ' dagen aan die je wél kunt.';
      waarschuwing.style.display = '';
    } else {
      waarschuwing.style.display = 'none';
    }
  });

  const box = document.getElementById('b2Preview');
  const teller = document.getElementById('b2WekenTeller');
  if (!box) return;

  const rijen = Array.from(document.querySelectorAll('#b2BlokLijst .b2-blok-rij'));
  const afwV = Array.from(document.querySelectorAll('[name="b2_afw_van[]"]')).map(i => i.value);
  const afwT = Array.from(document.querySelectorAll('[name="b2_afw_tot[]"]')).map(i => i.value);
  const vasteUit = Array.from(document.querySelectorAll('[name="b2_vaste_uit[]"]:checked')).map(c => parseInt(c.value));

  if (!rijen.length) { box.innerHTML = '<span style="color:var(--grijs);font-style:italic">Vul je periode in…</span>'; if (teller) teller.textContent=''; return; }

  let zinnen = [];
  let weekTeller = {}; // week => aantal blokken (voor overlap-detectie)
  let totaalWeken = new Set();
  for (const rij of rijen) {
    let van = parseInt(rij.querySelector('[name="b2_blok_van[]"]').value);
    let tot = parseInt(rij.querySelector('[name="b2_blok_tot[]"]').value);
    const regime = rij.querySelector('[name="b2_blok_regime[]"]').value;
    if (!van || !tot) continue;
    if (tot < van) { const t = van; van = tot; tot = t; }
    for (let w = van; w <= tot; w++) { totaalWeken.add(w); weekTeller[w] = (weekTeller[w]||0) + 1; }
    let regimeTekst;
    if (regime === 'weekend') {
      const dagen = Array.from(rij.querySelectorAll('.b2-wkdag:checked')).map(c => B2_DAGNL[parseInt(c.value)]);
      regimeTekst = dagen.length ? 'alleen ' + dagen.join('/') : 'alleen weekend';
    } else {
      regimeTekst = 'hele weken (5 dagen)';
    }
    zinnen.push('Van <strong>' + (B2_WEEKLABEL[van]||('W'+van)) + '</strong> tot <strong>' + (B2_WEEKLABEL[tot]||('W'+tot)) + '</strong>: ' + regimeTekst + '.');
  }

  let html = zinnen.map(z => '<div>• ' + z + '</div>').join('');
  if (afwV.some((v,i) => v && afwT[i])) {
    const stukken = [];
    afwV.forEach((v,i) => { if (v && afwT[i]) stukken.push(b2DatumNL(v) + ' t/m ' + b2DatumNL(afwT[i])); });
    html += '<div style="margin-top:6px">• Afwezig: <strong>' + stukken.join(', ') + '</strong>.</div>';
  }
  if (vasteUit.length) {
    html += '<div style="margin-top:6px">• Voorkeur vrij: <strong>' + vasteUit.map(d => B2_DAGNL[d]).join(', ') + '</strong> <span style="color:var(--grijs)">(planner beslist)</span>.</div>';
  }

  // Losse dagen dat iemand niet kan / liever vrij wil
  const losRijen = Array.from(document.querySelectorAll('#b2LosseDagLijst .b2-losdag-rij'));
  const losStukken = [];
  losRijen.forEach(rij => {
    const datum = rij.querySelector('[name="b2_dag_datum[]"]')?.value;
    if (!datum) return;
    const soort = rij.querySelector('[name="b2_dag_soort[]"]')?.value;
    const reden = rij.querySelector('[name="b2_dag_reden[]"]')?.value;
    let stuk = b2DatumNL(datum);
    if (soort === 'voorkeur_vrij') stuk += ' (liever vrij)';
    if (reden) stuk += ' — ' + reden;
    losStukken.push(stuk);
  });
  if (losStukken.length) {
    html += '<div style="margin-top:6px">• Losse dagen niet beschikbaar: <strong>' + losStukken.join(', ') + '</strong>.</div>';
  }

  // Verzoeken om minder dagen in bepaalde weken
  const vzRijen = Array.from(document.querySelectorAll('#b2VerzoekLijst .b2-verzoek-rij'));
  const vzStukken = [];
  vzRijen.forEach(rij => {
    const van = rij.querySelector('[name="b2_vz_van[]"]')?.value;
    const tot = rij.querySelector('[name="b2_vz_tot[]"]')?.value;
    const dagen = rij.querySelector('[name="b2_vz_dagen[]"]')?.value;
    if (!van || !tot || !dagen) return;
    let stuk = (B2_WEEKLABEL[van]||('W'+van)) + ' t/m ' + (B2_WEEKLABEL[tot]||('W'+tot)) + ': max ' + dagen + ' ' + (dagen==1?'dag':'dagen');
    vzStukken.push(stuk);
  });
  if (vzStukken.length) {
    html += '<div style="margin-top:6px">• Verzoek minder dagen: <strong>' + vzStukken.join('; ') + '</strong> <span style="color:var(--grijs)">(bestuur beoordeelt)</span>.</div>';
  }

  const n = totaalWeken.size;
  if (teller) teller.textContent = '— ' + n + (n === 1 ? ' week' : ' weken');
  box.innerHTML = html || '<span style="color:var(--grijs)">Geen periode ingevuld.</span>';

  // Overlap-waarschuwing (punt 1)
  const overlap = Object.keys(weekTeller).filter(w => weekTeller[w] > 1).map(w => B2_WEEKLABEL[w] || ('W'+w));
  const owBox = document.getElementById('b2OverlapWaarschuwing');
  if (owBox) {
    if (overlap.length) {
      owBox.innerHTML = '⚠️ Deze week(en) zitten in meerdere periodes: <strong>' + overlap.join(', ') + '</strong>. We gebruiken dan de <strong>eerste</strong> periode. Klopt dat?';
      owBox.style.display = '';
    } else {
      owBox.style.display = 'none';
    }
  }
}

function b2DatumNL(iso) {
  if (!iso) return '';
  const mnd = ['jan','feb','mrt','apr','mei','jun','jul','aug','sep','okt','nov','dec'];
  const p = iso.split('-');
  return parseInt(p[2]) + ' ' + mnd[parseInt(p[1])-1];
}

function b2Valideer() {
  b2FoutWis();
  // Minimaal één blok met geldige weken
  const rijen = Array.from(document.querySelectorAll('#b2BlokLijst .b2-blok-rij'));
  if (!rijen.length) { b2Fout('Geef minimaal één periode op waarin je kunt werken.'); return false; }
  // Weekend-blok moet minstens één dag hebben
  for (const rij of rijen) {
    if (rij.querySelector('[name="b2_blok_regime[]"]').value === 'weekend') {
      if (!rij.querySelectorAll('.b2-wkdag:checked').length) {
        b2Fout('Kies bij "alleen weekenden" minstens één dag (vrijdag, zaterdag of zondag).');
        return false;
      }
    }
  }
  // Afwezigheid: van mag niet na tot
  const afwV = Array.from(document.querySelectorAll('[name="b2_afw_van[]"]')).map(i => i.value);
  const afwT = Array.from(document.querySelectorAll('[name="b2_afw_tot[]"]')).map(i => i.value);
  for (let i = 0; i < afwV.length; i++) {
    if (afwV[i] && afwT[i] && afwV[i] > afwT[i]) { b2Fout('Bij een afwezigheid ligt de einddatum vóór de begindatum. Controleer je vakantieperiode.'); return false; }
    if ((afwV[i] && !afwT[i]) || (!afwV[i] && afwT[i])) { b2Fout('Vul bij een afwezigheid zowel een begin- als einddatum in.'); return false; }
  }
  // Toelichting verplicht als er een vaste voorkeur-dag is gekozen
  const heeftVaste = document.querySelectorAll('[name="b2_vaste_uit[]"]:checked').length > 0;
  if (heeftVaste) {
    const toel = (document.getElementById('b2VasteToelichting')?.value || '').trim();
    if (!toel) { b2Fout('Je hebt een vaste vrije dag als voorkeur aangegeven. Licht kort toe waarom, zodat we het goed kunnen meewegen.'); document.getElementById('b2VasteToelichting')?.focus(); return false; }
  }
  return true;
}

// Init
document.querySelectorAll('#b2BlokLijst .b2-blok-rij [name="b2_blok_regime[]"]').forEach(s => b2RegimeCheck(s));
b2Herbereken();

// Live preview bijwerken bij wijzigingen in losse dagen en verzoeken
['b2LosseDagLijst', 'b2VerzoekLijst'].forEach(id => {
  const lijst = document.getElementById(id);
  if (lijst) {
    lijst.addEventListener('input', b2Herbereken);
    lijst.addEventListener('change', b2Herbereken);
  }
});

/* ── Autosave concept (punt 14): tekstvelden tussentijds bewaren ── */
(function() {
  const form = document.getElementById('blkForm');
  if (!form) return;
  const csrf = form.querySelector('[name="_csrf"]')?.value || '';
  const tekstVelden = ['postcode','huisnummer','straat','woonplaats','opleiding','werkervaring','motivatie_lifeguard','motivatie_strand','diplomas','beschikbaarheid_uitzonderingen','social_media','bijzonderheden'];
  let timer = null, laatstGehashed = '';

  function verzamel() {
    const data = new URLSearchParams();
    data.append('_csrf', csrf);
    let iets = false;
    tekstVelden.forEach(naam => {
      const el = form.querySelector('[name="' + naam + '"]');
      if (el && el.value.trim()) { data.append(naam, el.value); iets = true; }
    });
    return iets ? data : null;
  }

  function toonStatus(tekst, kleur) {
    let ind = document.getElementById('blkAutosaveStatus');
    if (!ind) return;
    ind.textContent = tekst;
    ind.style.color = kleur || 'var(--grijs)';
    ind.style.opacity = '1';
    if (tekst.startsWith('✓')) {
      clearTimeout(ind._fade);
      ind._fade = setTimeout(() => { ind.style.opacity = '0.5'; }, 3000);
    }
  }

  function bewaar(sync) {
    const data = verzamel();
    if (!data) return;
    const hash = data.toString();
    if (hash === laatstGehashed) return; // niks veranderd
    laatstGehashed = hash;
    const url = '<?= APP_URL ?>/portaal/api_blk_concept.php';
    if (sync && navigator.sendBeacon) {
      navigator.sendBeacon(url, data);
    } else {
      toonStatus('Bezig met opslaan…', 'var(--grijs)');
      fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:data.toString() })
        .then(r => r.json())
        .then(d => { if (d.ok) toonStatus('✓ Concept opgeslagen' + (d.tijd ? ' om ' + d.tijd : ''), 'var(--groen)'); else toonStatus('Kon concept niet opslaan', 'var(--oranje)'); })
        .catch(() => toonStatus('Kon concept niet opslaan', 'var(--oranje)'));
    }
  }

  // Debounced autosave bij typen
  form.addEventListener('input', function(e) {
    if (!tekstVelden.includes(e.target.name)) return;
    clearTimeout(timer);
    timer = setTimeout(() => bewaar(false), 2500);
  });
  // Direct opslaan bij stapwissel (knoppen die gaNaar aanroepen)
  form.addEventListener('click', function(e) {
    if (e.target.closest('.stap-nav button')) setTimeout(() => bewaar(false), 50);
  });
  // Laatste redmiddel: bij verlaten van de pagina
  window.addEventListener('pagehide', () => bewaar(true));
})();

/* ── BLK kaart open/dicht ────────────────────────────────── */
function blkToggle() {
  const b = document.getElementById('blkBody');
  const btn = document.getElementById('blkToggleBtn');
  if (!b) return;
  const open = b.style.display !== 'none';
  b.style.display = open ? 'none' : '';
  if (btn) btn.textContent = open ? btn.textContent.replace('↑','↓') : btn.textContent.replace('↓','↑');
}
// Taak-link "#blk" opent de kaart en scrollt ernaartoe
if (location.hash === '#blk') {
  const k = document.getElementById('blk');
  if (k) k.style.display = '';
  const b = document.getElementById('blkBody');
  if (b) { b.style.display = ''; }
  const btn = document.getElementById('blkToggleBtn');
  if (btn) btn.textContent = btn.textContent.replace('↓','↑');
  setTimeout(() => { if (k) k.scrollIntoView({behavior:'smooth'}); }, 50);
}
document.querySelectorAll('a[href="#blk"]').forEach(a => {
  a.addEventListener('click', () => {
    const k = document.getElementById('blk');
    if (k) k.style.display = '';
    const b = document.getElementById('blkBody');
    if (b) b.style.display = '';
    const btn = document.getElementById('blkToggleBtn');
    if (btn) btn.textContent = btn.textContent.replace('↓','↑');
    setTimeout(() => { if (k) k.scrollIntoView({behavior:'smooth'}); }, 50);
  });
});

/* ── Loading states ──────────────────────────────────────── */
// Alle form-submits: knop → spinner, voorkom dubbel klikken
function portaalSubmit(form) {
  const btn = form.querySelector('button[type="submit"]');
  if (btn) {
    btn.disabled = true;
    const t = btn.querySelector('.btn-tekst'), s = btn.querySelector('.btn-spinner');
    if (t && s) { t.style.display = 'none'; s.style.display = ''; }
    else { btn.dataset.oud = btn.innerHTML; btn.innerHTML = 'Bezig…'; }
  }
  return true;
}

// Ouder-formulier: JS-validatie (velden hebben geen 'required' zodat verborgen staat de submit niet blokkeert)
function ouderValideer(form) {
  const naam = (form.querySelector('[name="ouder_naam"]')||{}).value?.trim() || '';
  const email = (form.querySelector('[name="ouder_email"]')||{}).value?.trim() || '';
  const tel = (form.querySelector('[name="ouder_telefoon"]')||{}).value?.trim() || '';
  const foutBox = form.querySelector('.ouder-fout');
  const cijfers = (tel.match(/\d/g) || []).length;
  let fout = '';
  if (!naam) fout = 'Vul de naam van je ouder/verzorger in.';
  else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) fout = 'Vul een geldig e-mailadres in.';
  else if (cijfers < 8) fout = 'Vul een geldig telefoonnummer in (minstens 8 cijfers).';
  if (fout) {
    if (foutBox) { foutBox.textContent = fout; foutBox.style.display = 'block'; foutBox.scrollIntoView({behavior:'smooth', block:'center'}); }
    else { alert(fout); }
    return false;
  }
  // Spinner (als portaalSubmit bestaat), maar laat de submit altijd doorgaan
  if (typeof portaalSubmit === 'function') { try { portaalSubmit(form); } catch(e){} }
  return true;
}

// BLK-validatie bij submit: loopt alle verplichte velden langs, navigeert naar de
// eerste onvolledige stap en toont daar de fout. Velden gebruiken data-verplicht
// i.p.v. required, zodat verborgen stappen de submit niet stil blokkeren.
function blkValideer(form) {
  for (const f of form.querySelectorAll('[data-verplicht]')) {
    if (!f.value.trim()) {
      const stap = parseInt(f.getAttribute('data-verplicht')) || 1;
      if (typeof gaNaar === 'function') gaNaar(stap);
      setTimeout(() => {
        f.focus();
        f.style.borderColor = 'var(--rood)';
        f.setAttribute('aria-invalid','true');
        const lbl = f.closest('.form-groep')?.querySelector('label')?.textContent.replace('*','').trim()
                  || f.getAttribute('placeholder') || 'een verplicht veld';
        const el = document.getElementById('fs' + stap);
        let melding = el?.querySelector('.stap-fout');
        if (el && !melding) {
          melding = document.createElement('div');
          melding.className = 'stap-fout';
          melding.setAttribute('role','alert');
          melding.style.cssText = 'margin-bottom:12px;padding:11px 14px;background:var(--rood-bg);border-left:3px solid var(--rood);border-radius:6px;font-size:13px;color:var(--rood)';
          el.insertBefore(melding, el.firstChild.nextSibling);
        }
        if (melding) { melding.textContent = '⚠️ Vul eerst "' + lbl + '" in.'; melding.scrollIntoView({behavior:'smooth', block:'center'}); }
        setTimeout(()=>{ f.style.borderColor=''; f.removeAttribute('aria-invalid'); }, 4000);
      }, 60);
      return false;
    }
  }
  const reden = form.querySelector('[data-verplicht-edit]');
  if (reden && reden.offsetParent !== null && !reden.value.trim()) {
    const stap = parseInt(reden.getAttribute('data-verplicht-edit')) || 5;
    if (typeof gaNaar === 'function') gaNaar(stap);
    setTimeout(() => { reden.focus(); reden.style.borderColor = 'var(--rood)'; }, 60);
    return false;
  }
  return portaalSubmit(form);
}
// Automatisch op alle forms zonder expliciete handler
document.querySelectorAll('form').forEach(f => {
  if (!f.hasAttribute('onsubmit')) {
    f.addEventListener('submit', () => portaalSubmit(f));
  }
});
// Links naar ondertekenen/documenten: visuele feedback
document.querySelectorAll('a.btn').forEach(a => {
  a.addEventListener('click', () => {
    if (a.href && !a.href.startsWith('#')) {
      a.style.opacity = '.6';
      a.textContent = 'Openen…';
    }
  });
});
</script>
<?php if ($stap === 'dossier' && portaal_ingelogd()):
  $_nav = ($_GET['tab'] ?? '') === 'taken' ? 'taken' : 'start';
?>
<nav class="pt-nav" aria-label="Hoofdnavigatie">
  <a href="<?= APP_URL ?>/portaal/" class="pt-nav-item <?= $_nav==='start'?'actief':'' ?>"><?= icoon('huis', 22) ?><span>Start</span></a>
  <a href="<?= APP_URL ?>/portaal/?tab=taken" class="pt-nav-item <?= $_nav==='taken'?'actief':'' ?>"><?= icoon('lijst', 22) ?><span>Taken</span></a>
  <a href="<?= APP_URL ?>/portaal/gegevens.php" class="pt-nav-item"><?= icoon('persoon', 22) ?><span>Gegevens</span></a>
</nav>
<?php endif; ?>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__."/../assets/js/app.js") ?>"></script>
</body>
</html>
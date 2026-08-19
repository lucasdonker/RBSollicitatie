<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('ondertekeningen', 'bewerken');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();

$sol_id = (int)($_POST['sol_id'] ?? 0);
$type   = $_POST['type'] ?? '';
$naar_email = trim($_POST['naar_email'] ?? '');
$naar_naam  = trim($_POST['naar_naam'] ?? '');

if (!$sol_id || !in_array($type, ['gezondheid_ouder','gezondheid_18plus','intake','intake_ouder'], true)) {
    echo json_encode(['ok'=>false,'fout'=>'Ongeldige parameters']);
    exit;
}

$db = db();
try {
    // Info ophalen
    $q = $db->prepare("SELECT s.id, p.voornaam, p.email FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE s.id=?");
    $q->execute([$sol_id]);
    $sol = $q->fetch();
    if (!$sol) throw new Exception('Sollicitant niet gevonden');

    $rol = ($type === 'gezondheid_ouder' || $type === 'intake_ouder') ? 'ouder' : 'sollicitant';
    if ($rol === 'ouder') {
        if (!$naar_email || !filter_var($naar_email, FILTER_VALIDATE_EMAIL)) throw new Exception('Geldig ouder e-mailadres vereist');
    } else {
        $naar_email = $sol['email'];
        $naar_naam  = $sol['voornaam'];
    }

    // Nieuwe token
    $token = bin2hex(random_bytes(24));
    $db->prepare("INSERT INTO ondertekeningen (sollicitatie_id, type, token, ondertekenaar_rol, ondertekenaar_email) VALUES (?,?,?,?,?)")
       ->execute([$sol_id, $type, $token, $rol, $naar_email]);

    // Mail met link
    $url = APP_URL . '/portaal/ondertekenen.php?token=' . urlencode($token);
    $type_label = match($type) {
        'gezondheid_ouder'  => 'Gezondheidsverklaring (t/m 17)',
        'gezondheid_18plus' => 'Gezondheidsverklaring 18+',
        'intake'            => 'Intakeformulier selectiedag',
        'intake_ouder'      => 'Intakeformulier selectiedag (ouder)',
    };
    $is_ouder_mail = ($rol === 'ouder');
    // Gebruik de bewerkbare template 'ondertekening_ouder'; vangnet als die ontbreekt.
    $verzoek_verstuurd = false;
    if (function_exists('ondertekening_mail_render') && function_exists('stuur_mail_html')) {
        try {
            $vars = [
                'voornaam'       => $sol['voornaam'] ?? '',
                'ontvanger_naam' => $naar_naam ?: ($is_ouder_mail ? 'ouder/verzorger' : ($sol['voornaam'] ?? '')),
                'ouder_naam'     => $naar_naam ?: 'ouder/verzorger',
                'document_naam'  => $type_label,
                'onderteken_url' => $url,
                'portaal_url'    => APP_URL . '/portaal/',
                'is_ouder'       => $is_ouder_mail ? '1' : '',
                'seizoen_naam'   => $sol['seizoen_naam'] ?? '',
            ];
            $rendered = ondertekening_mail_render('ondertekening_ouder', $vars);
            if ($rendered) {
                stuur_mail_html($naar_email, $naar_naam ?: 'Ontvanger', $rendered['onderwerp'], $rendered['html'], $sol_id);
                $verzoek_verstuurd = true;
            }
        } catch (\Exception $e) { error_log('ondertekenverzoek template: ' . $e->getMessage()); }
    }
    if (!$verzoek_verstuurd) {
        error_log('api_ondertekening: template faalde, vangnet gebruikt');
        $begroet = $naar_naam ? "Beste " . h($naar_naam) . "," : ($is_ouder_mail ? "Beste ouder/verzorger," : "Beste,");
        $vn = h($sol['voornaam']);
        $intro = $is_ouder_mail
            ? "<p><strong>$vn</strong> heeft gesolliciteerd als strandwacht bij Reddingsbrigade Paal 13 in Callantsoog. Omdat $vn nog geen 18 jaar is, hebben wij uw handtekening nodig op de <strong>$type_label</strong>.</p><p>Het ondertekenen gebeurt volledig digitaal en duurt ongeveer 3 minuten.</p>"
            : "<p>Voor je sollicitatie bij Reddingsbrigade Paal 13 vragen we je de <strong>$type_label</strong> digitaal te ondertekenen. Dit duurt ongeveer 3 minuten.</p>";
        $body = '<p>' . $begroet . '</p>' . $intro . '
<p style="text-align:center;margin:24px 0"><a href="' . h($url) . '" style="display:inline-block;background:#D43D00;color:#ffffff;padding:14px 28px;border-radius:6px;font-family:Arial,sans-serif;font-size:15px;font-weight:600;text-decoration:none">Formulier openen &rarr;</a></p>
<p style="font-size:12px;color:#8a94a3;word-break:break-all">Werkt de knop niet? Kopieer deze link: ' . h($url) . '</p>
<p>Vragen? Mail ons via <a href="mailto:' . (defined('MAIL_BESTUUR') ? MAIL_BESTUUR : 'info@paal13.com') . '">' . (defined('MAIL_BESTUUR') ? MAIL_BESTUUR : 'info@paal13.com') . '</a>.</p>
<p>Met vriendelijke groet,<br><strong>Reddingsbrigade Paal 13</strong></p>';
        $onderwerp = $is_ouder_mail ? "Handtekening nodig: $type_label voor $vn — Paal 13" : "Actie vereist: $type_label — Paal 13";
        if (function_exists('mail_simple_wrapper') && function_exists('stuur_mail_html')) {
            stuur_mail_html($naar_email, $naar_naam ?: 'Ontvanger', $onderwerp, mail_simple_wrapper($body, $onderwerp), $sol_id);
        } else {
            stuur_mail($naar_email, $naar_naam ?: 'Ontvanger', $onderwerp, $body, $sol_id);
        }
    }

    echo json_encode(['ok'=>true, 'url'=>$url, 'token'=>$token]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'fout'=>$e->getMessage()]);
}

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
    $ond_id = (int)$db->lastInsertId();

    // Verzoek via de centrale meldingen-kern (sjabloon per rol: ouder of sollicitant zelf)
    $url = ondertekening_url($token);
    $r = ondertekening_verzoek_versturen($sol_id, ['email' => $naar_email, 'naam' => $naar_naam, 'rol' => $rol],
                                         [ondertekening_label($type) => $url], 'ondertekening:' . $ond_id);
    $melding = function_exists('fase_melding_tekst') ? fase_melding_tekst(['melding' => $r]) : '';

    echo json_encode(['ok'=>true, 'url'=>$url, 'token'=>$token, 'melding'=>$melding, 'status'=>$r['status'] ?? null]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'fout'=>$e->getMessage()]);
}

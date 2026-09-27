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

if (!$sol_id || !in_array($type, ['gezondheid_ouder','gezondheid_18plus','intake','intake_ouder','lidmaatschap'], true)) {
    echo json_encode(['ok'=>false,'fout'=>'Ongeldige parameters']);
    exit;
}

$db = db();
try {
    // Info ophalen
    $q = $db->prepare("SELECT s.id, p.voornaam, p.email, p.geboortedatum FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE s.id=?");
    $q->execute([$sol_id]);
    $sol = $q->fetch();
    if (!$sol) throw new Exception('Sollicitant niet gevonden');

    // Inschrijving als lid: dezelfde route als bij de aanname (includes/lidmaatschap.php): onder 18 tekent de
    // ouder/verzorger (ouderadres uit het dossier, mail zodra het er is), anders de strandwacht zelf. Idempotent.
    if ($type === 'lidmaatschap') {
        if (!function_exists('lidmaatschap_verzekeren')) throw new Exception('Lidmaatschap is niet beschikbaar');
        $stand = lidmaatschap_verzekeren($sol_id);
        if ($stand === 'fout') throw new Exception('Klaarzetten lukte niet');
        $oq = $db->prepare("SELECT id, token, ondertekenaar_rol, ondertekenaar_email FROM ondertekeningen WHERE sollicitatie_id=? AND type='lidmaatschap' ORDER BY id DESC LIMIT 1");
        $oq->execute([$sol_id]);
        $o = $oq->fetch() ?: [];
        $uitleg = match (true) {
            $stand === 'overgeslagen' => 'Niet klaargezet: er bestaat al een Paal 13-account (vrijwel zeker al lid).',
            ($o['ondertekenaar_rol'] ?? '') === 'ouder' && empty($o['ondertekenaar_email']) => 'Klaargezet voor de ouder/verzorger; die krijgt een mail zodra het ouderadres bekend is.',
            ($o['ondertekenaar_rol'] ?? '') === 'ouder' => 'Klaargezet voor de ouder/verzorger (' . $o['ondertekenaar_email'] . ').',
            default => 'Klaargezet; de strandwacht tekent zelf in het portaal.',
        };
        echo json_encode(['ok' => $stand !== 'overgeslagen', 'url' => !empty($o['token']) ? ondertekening_url((string)$o['token']) : null,
                          'melding' => $uitleg, 'status' => $stand, 'fout' => $stand === 'overgeslagen' ? $uitleg : null]);
        exit;
    }

    $lft = function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($sol['geboortedatum'] ?? null) : null;
    $rol = (function_exists('ondertekening_ouder_tekent') ? ondertekening_ouder_tekent($type, $lft) : in_array($type, ['gezondheid_ouder', 'intake_ouder'], true))
        ? 'ouder' : 'sollicitant';
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

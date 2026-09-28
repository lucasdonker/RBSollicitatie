<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('sollicitanten', 'bewerken');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'fout' => 'Methode niet toegestaan']); exit;
}

csrf_check();

$sol_id = (int)($_POST['sol_id'] ?? 0);
$naar   = $_POST['naar'] ?? '';

if (!$sol_id || !function_exists('fase_zetten') || !isset(fasen()[$naar])) {
    echo json_encode(['ok' => false, 'fout' => 'Ongeldige invoer']); exit;
}

// "Zonder bericht": soms heb je al gebeld, of gaat het om een correctie van een fase
// die per ongeluk verkeerd stond. Tot nu toe was de enige uitweg het kanaal van dat
// meldingstype tijdelijk uitzetten bij Instellingen — dat raakte iedereen, niet alleen
// deze persoon. Het blijft in het logboek staan dát het stil ging.
$stil = !empty($_POST['stil']);

try {
    // Eén centrale fase-functie: zet de fase, zet documenten/kennismakingspakket klaar en
    // verstuurt de bijbehorende melding via de meldingen-kern.
    $res = fase_zetten($sol_id, $naar, 'paneel', ['stil' => $stil]);
    if (!$res['ok']) { echo json_encode(['ok' => false, 'fout' => $res['fout'] ?? 'Fase wijzigen mislukt']); exit; }

    /* Reden bij afwijken van de gewone volgorde (optioneel, uit fasWijzigen()). Die komt
       als notitie in het dossier en daarmee in de tijdlijn, naast de fasewissel zelf —
       zo is later terug te lezen waarom iemand terug is gezet. */
    $reden = trim((string)($_POST['reden'] ?? ''));
    if ($reden !== '' && !$res['ongewijzigd']) {
        $reden = mb_substr($reden, 0, 1000);
        $bhd = function_exists('huidige_beheerder') ? huidige_beheerder() : null;
        try {
            if (function_exists('extra_tabellen_auto_setup')) extra_tabellen_auto_setup();
            db()->prepare("INSERT INTO sollicitant_notities (sollicitatie_id, beheerder_id, tekst) VALUES (?,?,?)")
                ->execute([$sol_id, (int)($bhd['id'] ?? 0),
                           'Fase van ' . fase_label((string)$res['oud']) . ' naar ' . fase_label($naar) . ': ' . $reden]);
        } catch (\Throwable $e) { error_log('api_fase reden: ' . $e->getMessage()); }
    }

    $mail_status  = $res['melding']['status'] ?? null;
    $mail_melding = fase_melding_tekst($res) ?: null;
    if ($stil) $mail_melding = 'Fase gewijzigd zonder bericht — je stuurt zelf iets, of het is al besproken.';
    if ($res['ongewijzigd']) $mail_melding = 'Fase was al ' . fase_label($naar) . '.';

    // Bepaal de volgende snelle actie voor in de tabel-rij
    $snelle_actie = match($naar) {
        'aangemeld'          => ['label' => 'Uitnodigen selectiedag', 'bulk' => 'uitnodigen_selectie', 'kleur' => 'groen'],
        'selectiedag_geweest'=> ['label' => 'Uitnodigen weekend', 'bulk' => 'uitnodigen_tw',       'kleur' => 'primary'],
        default              => null,
    };

    /* De melding gaat mee als flash, niet als toast. Het scherm herlaadt toch (de fase
       staat op vier plekken), en een toast overleeft dat niet — vandaar dat de pagina
       eerst 900 ms stil bleef staan zodat je hem kon lezen. Als flash is hij er ná het
       herladen nog, blijft hij staan tot je hem wegklikt, en kan het herladen meteen. */
    if ($mail_melding && function_exists('flash')) {
        flash($mail_status === 'mislukt' ? 'waarschuwing' : 'succes', $mail_melding);
    }

    echo json_encode([
        'ok'           => true,
        'fase'         => $naar,
        'label'        => fase_label($naar),
        'kleur'        => fase_kleur($naar),
        'snelle_actie' => $snelle_actie,
        'mail_status'  => $mail_status,
        'mail_melding' => $mail_melding,
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'fout' => $e->getMessage()]);
}

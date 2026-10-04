<?php
/**
 * De tijdlijn van één sollicitatie, als JSON voor laadTimeline() in layout_bottom.php.
 *
 * Alles wat de sollicitant doet hoort hier te staan, niet alleen notities en mails:
 * eerder ontbraken ondertekeningen, beschikbaarheid en afgeronde taken, en was
 * "BLK ingevuld" in werkelijkheid het moment van het eerste concept.
 *
 * 'icon' is een SVG uit icoon() (door ons gemaakt, veilig als HTML); titel, inhoud en
 * door zijn platte tekst en worden in de JS ge-escaped.
 */
require_once __DIR__ . '/../includes/functions.php';
 if (function_exists('extra_tabellen_auto_setup')) extra_tabellen_auto_setup();
vereis_recht('sollicitanten', 'lezen');
header('Content-Type: application/json');

$sol_id = (int)($_GET['sol_id'] ?? 0);
if (!$sol_id) { echo json_encode(['ok'=>false,'fout'=>'sol_id vereist']); exit; }

$db = db();
$events = [];
$ev = function (string $type, ?string $tijd, string $icoon, string $titel, string $door, string $inhoud = '', array $extra = []) use (&$events) {
    if (!$tijd) return;
    $events[] = array_merge(['type' => $type, 'tijd' => $tijd, 'icon' => icoon($icoon, 13),
                             'titel' => $titel, 'door' => $door, 'inhoud' => $inhoud], $extra);
};

// 1) Notities
try {
    $q = $db->prepare("
        SELECT n.id, n.tekst, n.aangemaakt_op, n.gewijzigd_op, n.beheerder_id, b.naam AS beheerder_naam
        FROM sollicitant_notities n LEFT JOIN beheerders b ON b.id = n.beheerder_id
        WHERE n.sollicitatie_id = ?
        ORDER BY n.aangemaakt_op DESC
    ");
    $q->execute([$sol_id]);
    foreach ($q->fetchAll() as $r) {
        $ev('notitie', $r['aangemaakt_op'], 'chat', 'Notitie', $r['beheerder_naam'] ?? 'Onbekend', (string)$r['tekst'],
            ['meta_id' => (int)$r['id'], 'bewerkbaar' => true]);
    }
} catch (Exception $e) {}

// 2) Mail log
try {
    $q = $db->prepare("
        SELECT id, onderwerp, ontvanger, status, verstuurd_op, foutmelding
        FROM mail_log
        WHERE sollicitatie_id = ?
        ORDER BY verstuurd_op DESC
    ");
    $q->execute([$sol_id]);
    foreach ($q->fetchAll() as $r) {
        $ok = $r['status'] === 'verstuurd';
        $ev('mail', $r['verstuurd_op'], $ok ? 'mail' : 'waarschuwing', $ok ? 'Mail verstuurd' : 'Mail mislukt', 'Systeem',
            $r['onderwerp'] . ($r['foutmelding'] ? ' — ' . $r['foutmelding'] : ''));
    }
} catch (Exception $e) {}

// 3) Aanmelding en fasewissels (uit het auditlog; fase_zetten() logt elke wissel)
$sol = null;
try {
    $q = $db->prepare("SELECT s.aangemeld_op, s.gewijzigd_op, s.fase FROM sollicitaties s WHERE s.id = ?");
    $q->execute([$sol_id]);
    $sol = $q->fetch() ?: null;
    if ($sol) $ev('aanmelding', $sol['aangemeld_op'], 'plus', 'Aangemeld', 'Sollicitant', 'Sollicitatie ingediend');
} catch (Exception $e) {}

$fase_gelogd = false;
try {
    $q = $db->prepare("SELECT moment, wie_type, wie_naam, details FROM audit_log
                       WHERE objecttype = 'sollicitatie' AND object_id = ? AND actie = 'fase_gewijzigd'
                       ORDER BY moment DESC");
    $q->execute([$sol_id]);
    foreach ($q->fetchAll() as $r) {
        $d = json_decode((string)$r['details'], true) ?: [];
        $nieuw = (string)($d['nieuw'] ?? '');
        $door = $r['wie_naam'] ?: ($r['wie_type'] === 'sollicitant' ? 'Sollicitant' : 'Systeem');
        $ev('fase', $r['moment'], 'wissel', 'Fase: ' . fase_label($nieuw), $door,
            !empty($d['oud']) ? 'Was: ' . fase_label((string)$d['oud']) : '');
        $fase_gelogd = true;
    }
} catch (Exception $e) {}
// Dossiers van vóór het auditlog: dan alleen de huidige fase, zoals voorheen.
if (!$fase_gelogd && $sol && $sol['gewijzigd_op'] && $sol['gewijzigd_op'] !== $sol['aangemeld_op']) {
    $ev('fase', $sol['gewijzigd_op'], 'wissel', 'Fase: ' . fase_label($sol['fase']), 'Systeem/beheerder', 'Huidige status');
}

// 4) BLK: ingediend (niet het eerste concept — dat maakt de autosave al aan)
try {
    $q = $db->prepare("SELECT * FROM blk_formulieren WHERE sollicitatie_id = ?");
    $q->execute([$sol_id]);
    $blk = $q->fetch() ?: null;
    if ($blk && $sol) {
        $op = blk_ingediend_op($sol, $blk);
        $ev('blk', $op, 'document', 'Kennismakingsformulier ingediend', 'Sollicitant');
    }
} catch (Exception $e) {}

// 5) Ondertekeningen
try {
    $q = $db->prepare("SELECT type, ondertekend_op, ondertekenaar_naam, ondertekenaar_rol FROM ondertekeningen
                       WHERE sollicitatie_id = ? AND ondertekend_op IS NOT NULL");
    $q->execute([$sol_id]);
    foreach ($q->fetchAll() as $r) {
        $door = $r['ondertekenaar_naam'] ?: ($r['ondertekenaar_rol'] === 'ouder' ? 'Ouder/verzorger' : 'Sollicitant');
        $ev('ondertekening', $r['ondertekend_op'], 'ondertekenen', 'Ondertekend: ' . ondertekening_label((string)$r['type']), $door);
    }
} catch (Exception $e) {}

// 6) Beschikbaarheid
try {
    $q = $db->prepare("SELECT ingevuld_op, definitief_op FROM beschikbaarheid_profiel WHERE sollicitatie_id = ?");
    $q->execute([$sol_id]);
    if ($p = $q->fetch()) {
        $ev('beschikbaarheid', $p['ingevuld_op'], 'kalender', 'Beschikbaarheid doorgegeven', 'Sollicitant');
        $ev('beschikbaarheid', $p['definitief_op'], 'kalender', 'Beschikbaarheid definitief', 'Sollicitant');
    }
} catch (Exception $e) {}

// 7) Afgeronde taken uit het takenpakket (uploads, huisregels, account)
try {
    $q = $db->prepare("SELECT titel, voltooid_op FROM sollicitant_taken WHERE sollicitatie_id = ? AND status = 'voltooid'");
    $q->execute([$sol_id]);
    foreach ($q->fetchAll() as $r) {
        $ev('taak', $r['voltooid_op'], 'vink', 'Taak afgerond: ' . $r['titel'], 'Sollicitant');
    }
} catch (Exception $e) {}

// Sorteer op tijd (nieuwste eerst)
usort($events, fn($a,$b) => strcmp($b['tijd'] ?? '', $a['tijd'] ?? ''));

echo json_encode(['ok' => true, 'events' => $events]);

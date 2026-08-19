<?php
require_once __DIR__ . '/../includes/functions.php';
 if (function_exists('extra_tabellen_auto_setup')) extra_tabellen_auto_setup();
vereis_recht('sollicitanten', 'lezen');
header('Content-Type: application/json');

$sol_id = (int)($_GET['sol_id'] ?? 0);
if (!$sol_id) { echo json_encode(['ok'=>false,'fout'=>'sol_id vereist']); exit; }

$db = db();
$events = [];

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
        $events[] = [
            'type'      => 'notitie',
            'tijd'      => $r['aangemaakt_op'],
            'icon'      => '💬',
            'titel'     => 'Notitie',
            'door'      => $r['beheerder_naam'] ?? 'Onbekend',
            'inhoud'    => $r['tekst'],
            'meta_id'   => (int)$r['id'],
            'bewerkbaar'=> true,
        ];
    }
} catch (Exception $e) {}

// 2) Mail log
try {
    $q = $db->prepare("
        SELECT id, onderwerp, ontvanger, status, verstuurd_op, fout_melding
        FROM mail_log
        WHERE sollicitatie_id = ?
        ORDER BY verstuurd_op DESC
    ");
    $q->execute([$sol_id]);
    foreach ($q->fetchAll() as $r) {
        $ok = $r['status'] === 'verstuurd';
        $events[] = [
            'type'   => 'mail',
            'tijd'   => $r['verstuurd_op'],
            'icon'   => $ok ? '📧' : '⚠️',
            'titel'  => $ok ? 'Mail verstuurd' : 'Mail mislukt',
            'door'   => 'Systeem',
            'inhoud' => $r['onderwerp'] . ($r['fout_melding'] ? ' — ' . $r['fout_melding'] : ''),
        ];
    }
} catch (Exception $e) {}

// 3) Aanmelding & fase huidige status
try {
    $q = $db->prepare("
        SELECT s.aangemeld_op, s.gewijzigd_op, s.fase
        FROM sollicitaties s WHERE s.id = ?
    ");
    $q->execute([$sol_id]);
    if ($sol = $q->fetch()) {
        $events[] = [
            'type'   => 'aanmelding',
            'tijd'   => $sol['aangemeld_op'],
            'icon'   => '🎯',
            'titel'  => 'Aangemeld',
            'door'   => 'Sollicitant',
            'inhoud' => 'Sollicitatie ingediend',
        ];
        if ($sol['gewijzigd_op'] && $sol['gewijzigd_op'] !== $sol['aangemeld_op']) {
            $events[] = [
                'type'   => 'fase',
                'tijd'   => $sol['gewijzigd_op'],
                'icon'   => '🔄',
                'titel'  => 'Fase: ' . fase_label($sol['fase']),
                'door'   => 'Systeem/beheerder',
                'inhoud' => 'Huidige status',
            ];
        }
    }
} catch (Exception $e) {}

// 4) BLK ingevuld
try {
    $q = $db->prepare("SELECT ingevuld_op FROM blk_formulieren WHERE sollicitatie_id = ?");
    $q->execute([$sol_id]);
    if ($blk = $q->fetch()) {
        if (!empty($blk['ingevuld_op'])) {
            $events[] = [
                'type'   => 'blk',
                'tijd'   => $blk['ingevuld_op'],
                'icon'   => '📝',
                'titel'  => 'BLK formulier ingevuld',
                'door'   => 'Sollicitant',
                'inhoud' => 'Beter Leren Kennen formulier voltooid',
            ];
        }
    }
} catch (Exception $e) {}

// Sorteer op tijd (nieuwste eerst)
usort($events, fn($a,$b) => strcmp($b['tijd'] ?? '', $a['tijd'] ?? ''));

echo json_encode(['ok' => true, 'events' => $events]);

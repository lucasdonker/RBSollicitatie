<?php
/**
 * Paal 13 — BLK/dossier-export helpers.
 *
 * Verzamelt alle gegevens van een sollicitant (persoon + BLK-formulier + beschikbaarheid,
 * leesbaar uitgewerkt) voor export naar Excel/CSV en PDF. Eén bron voor beide formaten.
 */

if (!function_exists('export_dossier_van')) {

/**
 * Maak een leesbare samenvatting van de beschikbaarheid van een sollicitant.
 * Zet de werkperiodes, uitzonderingen, vaste vrije dagen en verzoeken om in gewone taal.
 */
function export_beschikbaarheid_tekst(int $sol_id): string {
    if (!function_exists('beschikbaarheid2_van')) return '';
    $b = beschikbaarheid2_van($sol_id);
    $delen = [];

    $dag_nl = [1=>'ma',2=>'di',3=>'wo',4=>'do',5=>'vr',6=>'za',7=>'zo'];

    // Werkperiodes
    foreach ($b['blokken'] as $blok) {
        $regime = ($blok['regime'] ?? 'vijf') === 'weekend' ? 'alleen weekend' : 'hele weken';
        $stuk = 'week ' . (int)$blok['van_week'] . ' t/m ' . (int)$blok['tot_week'] . ' (' . $regime;
        if (($blok['regime'] ?? '') === 'weekend' && !empty($blok['weekend_dagen']) && is_array($blok['weekend_dagen'])) {
            $dagen = array_map(fn($d) => $dag_nl[$d] ?? $d, $blok['weekend_dagen']);
            $stuk .= ': ' . implode('/', $dagen);
        }
        $stuk .= ')';
        $delen[] = $stuk;
    }
    $samenvatting = $delen ? ('Werkt: ' . implode('; ', $delen) . '.') : 'Geen werkperiode opgegeven.';

    // Vaste vrije dagen
    if (!empty($b['profiel']['vaste_dagen_uit']) && is_array($b['profiel']['vaste_dagen_uit'])) {
        $vd = array_map(fn($d) => $dag_nl[$d] ?? $d, $b['profiel']['vaste_dagen_uit']);
        $samenvatting .= ' Vast vrij: ' . implode(', ', $vd) . '.';
        if (!empty($b['profiel']['vaste_toelichting'])) $samenvatting .= ' (' . $b['profiel']['vaste_toelichting'] . ')';
    }

    // Afwezigheid
    $afw = [];
    foreach ($b['afwezig'] as $a) {
        $van = !empty($a['van_datum']) ? date('d-m', strtotime($a['van_datum'])) : '?';
        $tot = !empty($a['tot_datum']) ? date('d-m', strtotime($a['tot_datum'])) : '?';
        $lbl = !empty($a['label']) ? ' (' . $a['label'] . ')' : '';
        $afw[] = "$van t/m $tot$lbl";
    }
    if ($afw) $samenvatting .= ' Afwezig: ' . implode(', ', $afw) . '.';

    // Verzoeken minder dagen
    $vz = [];
    foreach ($b['verzoeken'] as $v) {
        $stuk = 'week ' . (int)$v['van_week'] . '-' . (int)$v['tot_week'] . ': max ' . (int)$v['dagen_aantal'] . ' dagen';
        if (!empty($v['dagen_pool']) && is_array($v['dagen_pool'])) {
            $pd = array_map(fn($d) => $dag_nl[$d] ?? $d, $v['dagen_pool']);
            $stuk .= ' (' . implode('/', $pd) . ')';
        }
        $vz[] = $stuk;
    }
    if ($vz) $samenvatting .= ' Verzoek minder dagen: ' . implode('; ', $vz) . '.';

    return $samenvatting;
}

/**
 * Verzamel het volledige dossier van een sollicitant als platte sleutel=>waarde-array,
 * geschikt voor een Excel-rij of een PDF.
 */
function export_dossier_van(int $sol_id): array {
    $db = db();
    $q = $db->prepare("SELECT s.*, p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.telefoon,
                              p.geboortedatum, p.geslacht, sz.naam AS seizoen_naam
                       FROM sollicitaties s
                       JOIN personen p ON p.id = s.persoon_id
                       LEFT JOIN seizoenen sz ON sz.id = s.seizoen_id
                       WHERE s.id = ?");
    $q->execute([$sol_id]);
    $s = $q->fetch();
    if (!$s) return [];

    // BLK-formulier
    $bq = $db->prepare("SELECT * FROM blk_formulieren WHERE sollicitatie_id = ?");
    $bq->execute([$sol_id]);
    $blk = $bq->fetch() ?: [];
    // Ingediend, niet "er staat een rij": de autosave maakt al een concept aan.
    $blk_op = function_exists('blk_ingediend_op') ? blk_ingediend_op($s, $blk ?: null) : ($blk['ingevuld_op'] ?? null);

    $naam = trim($s['voornaam'] . ' ' . ($s['tussenvoegsel'] ? $s['tussenvoegsel'] . ' ' : '') . $s['achternaam']);
    $leeftijd = (function_exists('leeftijd_uit_datum') && $s['geboortedatum']) ? leeftijd_uit_datum($s['geboortedatum']) : '';

    return [
        'Naam'            => $naam,
        'E-mail'          => $s['email'] ?? '',
        'Telefoon'        => $s['telefoon'] ?? '',
        'Geboortedatum'   => $s['geboortedatum'] ? date('d-m-Y', strtotime($s['geboortedatum'])) : '',
        'Leeftijd'        => $leeftijd !== null ? (string)$leeftijd : '',
        'Geslacht'        => $s['geslacht'] ?? '',
        'Seizoen'         => $s['seizoen_naam'] ?? '',
        'Fase'            => function_exists('fase_label') ? fase_label($s['fase'] ?? '') : ($s['fase'] ?? ''),
        'Aangemeld op'    => !empty($s['aangemeld_op']) ? date('d-m-Y', strtotime($s['aangemeld_op'])) : '',
        'Herkomst (bron)' => $s['bron_label'] ?? ($s['bron_code'] ?? ''),
        'Adres'           => $blk['adres'] ?? '',
        'Social media'    => $blk['social_media'] ?? '',
        'Opleiding'       => $blk['opleiding'] ?? '',
        'Werkervaring'    => $blk['werkervaring'] ?? '',
        'Diploma\'s'      => $blk['diplomas'] ?? '',
        'Motivatie lifeguard'  => $blk['motivatie_lifeguard'] ?? '',
        'Motivatie strand'     => $blk['motivatie_strand'] ?? '',
        'Bijzonderheden'  => $blk['bijzonderheden'] ?? '',
        'Beschikbaarheid' => export_beschikbaarheid_tekst($sol_id),
        'BLK ingediend op' => $blk_op ? date('d-m-Y H:i', strtotime($blk_op))
                                      : ($blk ? '(concept, nog niet ingediend)' : '(nog niet ingevuld)'),
        'Ondertekeningen' => export_ondertekeningen_tekst($sol_id),
        'Loondossier'     => export_loondossier_tekst($sol_id),
    ];
}

/** Ondertekende documenten als tekst ("Gezondheidsverklaring 18+: 4 mei 2026 (zelf, sms naar 06 12 34 56 78)"); BSN/IBAN nooit. */
function export_ondertekeningen_tekst(int $sol_id): string {
    try {
        $q = db()->prepare("SELECT type, ondertekend_op, ondertekenaar_rol, zekerheid, zekerheid_naar FROM ondertekeningen WHERE sollicitatie_id=? ORDER BY aangemaakt_op");
        $q->execute([$sol_id]);
        $delen = [];
        foreach ($q->fetchAll() as $o) {
            $label = function_exists('ondertekening_label') ? ondertekening_label((string)$o['type']) : $o['type'];
            $zek = '';
            if (!empty($o['zekerheid'])) {
                $zek = ', ' . $o['zekerheid'];
                if (!empty($o['zekerheid_naar'])) $zek .= ' naar ' . (function_exists('telefoon_opmaak') ? telefoon_opmaak((string)$o['zekerheid_naar']) : (string)$o['zekerheid_naar']);
            }
            $delen[] = $label . ': ' . (!empty($o['ondertekend_op']) ? date('d-m-Y', strtotime($o['ondertekend_op'])) . ' (' . ($o['ondertekenaar_rol'] === 'ouder' ? 'ouder' : 'zelf') . $zek . ')' : 'open');
        }
        return implode('; ', $delen);
    } catch (\Throwable $e) { return ''; }
}

/** Loondossier-status als tekst (alleen metadata: welke stukken, wanneer, verwerkt). */
function export_loondossier_tekst(int $sol_id): string {
    if (!function_exists('documenten_loondossier')) return '';
    $d = documenten_loondossier($sol_id);
    $delen = [];
    foreach (['id_voor' => 'ID voor', 'id_achter' => 'ID achter', 'bankpas' => 'bankpas', 'pasfoto' => 'pasfoto'] as $s => $l) {
        if (!empty($d['docs'][$s])) $delen[] = $l . ' ' . date('d-m-Y', strtotime($d['docs'][$s]['geupload_op'])) . ($d['docs'][$s]['status'] === 'verwerkt' ? ' (verwerkt)' : '');
    }
    if (!$delen && !$d['loonheffing']) return '';
    return ($d['compleet'] ? 'Compleet' : 'Onvolledig: ' . implode(', ', $d['ontbreekt'])) . ($d['verwerkt'] ? ', verwerkt' : '') . ($delen ? ' — ' . implode(', ', $delen) : '');
}

}

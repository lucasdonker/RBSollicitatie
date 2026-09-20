<?php
/**
 * Paal 13 — de filter op de sollicitantenlijst, op één plek. GEEN HTML.
 *
 * Waarom apart: de lijst en de dossierpagina moeten dezelfde selectie kennen. In het
 * dossier loop je met ← → door de lijst waar je vandaan kwam, met de filtertekst erbij
 * ("4 van 12 in Selectiedag geweest") — ontwerpfilosofie §5. Zou de dossierpagina zijn
 * eigen WHERE bouwen, dan wijken die twee bij de eerste wijziging van elkaar af.
 */

if (!function_exists('bh_soll_filter')) {

/**
 * Bouwt de WHERE-clausule uit de queryparameters.
 *
 * @param array $q    meestal $_GET
 * @param int   $sid  seizoen-id
 * @return array{where:string,params:array,actief:bool,omschrijving:string}
 */
function bh_soll_filter(array $q, int $sid): array {
    $where  = "s.seizoen_id = ?";
    $params = [$sid];
    $delen  = [];

    $fase = (string)($q['fase'] ?? 'alle');
    if ($fase !== 'alle' && $fase !== '' && isset(fasen()[$fase])) {
        $where .= " AND s.fase = ?";
        $params[] = $fase;
        $delen[] = fase_label($fase);
    }
    if ((string)($q['terug'] ?? '') === '1') {
        $where .= " AND s.terugkerend = 1";
        $delen[] = 'terugkerend';
    }
    if ((string)($q['blk'] ?? '') === 'ja') {
        $where .= " AND EXISTS (SELECT 1 FROM blk_formulieren bf WHERE bf.sollicitatie_id=s.id)";
        $delen[] = 'formulier ingevuld';
    } elseif ((string)($q['blk'] ?? '') === 'nee') {
        $where .= " AND NOT EXISTS (SELECT 1 FROM blk_formulieren bf WHERE bf.sollicitatie_id=s.id)";
        $delen[] = 'formulier open';
    }
    $comp = (string)($q['comp'] ?? '');
    if ($comp !== '') {
        $where .= " AND EXISTS (SELECT 1 FROM competenties c WHERE c.sollicitatie_id=s.id AND c.categorie=?)";
        $params[] = $comp;
        $delen[] = 'competentie';
    }
    $zoek = trim((string)($q['q'] ?? ''));
    if ($zoek !== '') {
        $where .= " AND (p.voornaam LIKE ? OR p.achternaam LIKE ? OR p.email LIKE ?)";
        $params[] = "%$zoek%"; $params[] = "%$zoek%"; $params[] = "%$zoek%";
        $delen[] = 'zoekterm "' . $zoek . '"';
    }

    return [
        'where'        => $where,
        'params'       => $params,
        'actief'       => (bool)$delen,
        'omschrijving' => $delen ? implode(' · ', $delen) : 'alle kandidaten',
    ];
}

/**
 * Waar zit deze sollicitant in de gefilterde lijst, en wie staan ernaast?
 *
 * Eén query over de hele selectie. Bij een lijst van deze omvang (tientallen tot
 * honderd) is dat goedkoper en eenvoudiger dan twee grensquery's met LIMIT.
 *
 * @return array{positie:int,totaal:int,vorige:?int,volgende:?int,omschrijving:string}
 */
function bh_soll_buren(int $sol_id, array $q, int $sid, string $order = 's.aangemeld_op DESC'): array {
    $leeg = ['positie' => 0, 'totaal' => 0, 'vorige' => null, 'volgende' => null, 'omschrijving' => ''];
    if ($sol_id <= 0 || $sid <= 0) return $leeg;

    $f = bh_soll_filter($q, $sid);
    try {
        $st = db()->prepare("SELECT s.id FROM sollicitaties s
                             JOIN personen p ON p.id = s.persoon_id
                             WHERE {$f['where']} ORDER BY $order");
        $st->execute($f['params']);
        $ids = array_map('intval', array_column($st->fetchAll(), 'id'));
    } catch (\Throwable $e) { return $leeg; }

    $i = array_search($sol_id, $ids, true);
    if ($i === false) return $leeg;

    return [
        'positie'      => $i + 1,
        'totaal'       => count($ids),
        'vorige'       => $ids[$i - 1] ?? null,
        'volgende'     => $ids[$i + 1] ?? null,
        'omschrijving' => $f['omschrijving'],
    ];
}

/** De filterparameters uit een URL, zonder de rest — om ze mee te geven aan het dossier. */
function bh_soll_filter_params(array $q): array {
    return array_filter([
        'fase'  => $q['fase']  ?? null, 'q'    => $q['q']    ?? null,
        'terug' => $q['terug'] ?? null, 'blk'  => $q['blk']  ?? null,
        'comp'  => $q['comp']  ?? null, 'sort' => $q['sort'] ?? null,
        'richting' => $q['richting'] ?? null,
    ], fn($v) => $v !== null && $v !== '');
}

} // function_exists

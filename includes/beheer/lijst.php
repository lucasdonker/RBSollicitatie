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
    // Terugkerend: 1 = eerder gesolliciteerd; lg = terugkerende lifeguard (Authentik-account);
    // sol = eerder gesolliciteerd maar geen lifeguard. Dezelfde indeling als de kengetallen op Vandaag.
    $terug = (string)($q['terug'] ?? '');
    $_lg   = function_exists('authentik_lifeguard_sql') ? authentik_lifeguard_sql('s.id') : '0';
    if ($terug === '1') {
        $where .= " AND s.terugkerend = 1";
        $delen[] = 'terugkerend';
    } elseif ($terug === 'lg') {
        $where .= " AND $_lg = 1";
        $delen[] = 'terugkerende lifeguard';
    } elseif ($terug === 'sol') {
        $where .= " AND s.terugkerend = 1 AND $_lg = 0";
        $delen[] = 'eerder gesolliciteerd';
    }
    $toon = (string)($q['toon'] ?? '');
    if ($toon === 'lopend') {
        $where .= " AND s.fase NOT IN ('aangenomen','afgewezen','afgehaakt')";
        $delen[] = 'in de procedure';
    } elseif ($toon === '30d') {
        $where .= " AND s.aangemeld_op >= ?";
        $params[] = date('Y-m-d H:i:s', strtotime('-30 days'));
        $delen[] = 'aangemeld in de laatste 30 dagen';
    }
    // Ingediend telt, niet het bestaan van een rij: de autosave maakt al een conceptrij.
    $_blk_in = "EXISTS (SELECT 1 FROM blk_formulieren bf WHERE bf.sollicitatie_id=s.id AND " . blk_ingediend_sql('bf', 's') . ")";
    if ((string)($q['blk'] ?? '') === 'ja') {
        $where .= " AND $_blk_in";
        $delen[] = 'formulier ingediend';
    } elseif ((string)($q['blk'] ?? '') === 'nee') {
        $where .= " AND NOT $_blk_in";
        $delen[] = 'formulier open';
    }
    $comp = (string)($q['comp'] ?? '');
    if ($comp !== '') {
        $where .= " AND EXISTS (SELECT 1 FROM competenties c WHERE c.sollicitatie_id=s.id AND c.categorie=?)";
        $params[] = $comp;
        $delen[] = 'competentie';
    }
    // Verzoek om in te trekken (includes/intrekken.php). Ook wie al is afgehaakt komt zo terug.
    $intrek = (string)($q['intrek'] ?? '');
    $_iv_sql = [
        'open'        => ["iv.status = 'open'", 'wil intrekken'],
        'afgehandeld' => ["iv.status IN ('doorgevoerd','afgewezen','geannuleerd')", 'intrekverzoek afgehandeld'],
        'ja'          => ["1=1", 'met intrekverzoek'],
    ][$intrek] ?? null;
    if ($_iv_sql) {
        $where .= " AND EXISTS (SELECT 1 FROM intrek_verzoek iv WHERE iv.sollicitatie_id=s.id AND {$_iv_sql[0]})";
        $delen[] = $_iv_sql[1];
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
 * De sorteerbare kolommen van de sollicitantenlijst: kolomsleutel => SQL-uitdrukking.
 * Eén bron voor de kolomdefinitie in sollicitanten.php én de ← →-navigatie in het
 * dossier. De uitdrukkingen gaan uit van de aliassen s (sollicitaties), p (personen)
 * en b (blk_formulieren).
 */
function bh_soll_sorteringen(): array {
    return [
        'naam'         => 'p.achternaam',
        'leeftijd'     => 'p.geboortedatum',
        'aangemeld'    => 's.aangemeld_op',
        'blk_ingevuld' => blk_ingediend_op_sql(),
    ];
}

/**
 * De ORDER BY van de lijst uit sort/richting in de URL, getoetst aan de whitelist.
 * Met s.id als laatste sleutel: bij gelijke waarden (dezelfde achternaam) ligt de
 * volgorde dan vast, zodat lijst en ← → dezelfde buren zien.
 */
function bh_soll_order(array $q): string {
    $sort = (string)($q['sort'] ?? '');
    $richting = strtolower((string)($q['richting'] ?? '')) === 'asc' ? 'ASC' : 'DESC';
    if (!in_array($sort, bh_soll_sorteringen(), true)) return 's.aangemeld_op DESC, s.id DESC';
    return $sort . ' ' . $richting . ', s.id ' . $richting;
}

/**
 * Waar zit deze sollicitant in de gefilterde lijst, en wie staan ernaast?
 *
 * Eén query over de hele selectie. Bij een lijst van deze omvang (tientallen tot
 * honderd) is dat goedkoper en eenvoudiger dan twee grensquery's met LIMIT.
 * De volgorde is die van de lijst (bh_soll_order), dus ook als je op naam sorteerde.
 *
 * @return array{positie:int,totaal:int,vorige:?int,volgende:?int,omschrijving:string}
 */
function bh_soll_buren(int $sol_id, array $q, int $sid): array {
    $leeg = ['positie' => 0, 'totaal' => 0, 'vorige' => null, 'volgende' => null, 'omschrijving' => ''];
    if ($sol_id <= 0 || $sid <= 0) return $leeg;

    $f = bh_soll_filter($q, $sid);
    $order = bh_soll_order($q);
    try {
        $st = db()->prepare("SELECT s.id FROM sollicitaties s
                             JOIN personen p ON p.id = s.persoon_id
                             LEFT JOIN blk_formulieren b ON b.sollicitatie_id = s.id
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
        'richting' => $q['richting'] ?? null, 'intrek' => $q['intrek'] ?? null,
        'toon'     => $q['toon']     ?? null,
    ], fn($v) => $v !== null && $v !== '');
}

} // function_exists

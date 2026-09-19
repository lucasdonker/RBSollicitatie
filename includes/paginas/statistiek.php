<?php
/**
 * Pagina's — anonieme statistiek + werving-attributie.
 *
 * Twee lagen:
 *  1. Bezoekersstatistiek per pagina (bezoeken, unieke bezoekers, formulier gestart/afgerond).
 *     Geen cookies, geen IP-opslag, geen user-agent. Alleen een dag-gebonden hash om
 *     "uniek" te kunnen tellen; die wordt na 30 dagen op NULL gezet (zie pag_events_opschonen).
 *  2. Bron-attributie: de bron-code reist mee naar de sollicitatie, zodat later zichtbaar is
 *     waar aangenomen mensen vandaan kwamen — ook als ze via de WordPress-plugin binnenkwamen.
 */

if (!function_exists('pag_track')) {

function pag_event_typen(): array {
    return ['bezoek', 'formulier_start', 'formulier_klaar', 'formulier_geweigerd'];
}

/**
 * Dag-gebonden pseudoniem van de bezoeker: IP + user-agent + dag + geheim. Niet herleidbaar
 * naar een persoon en na één dag vervallen (andere dag = andere hash).
 */
function pag_bezoeker_hash(): string {
    $geheim = defined('APP_URL') ? APP_URL : 'paal13';
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . date('Y-m-d') . '|' . $geheim);
}

/** Grove bot-herkenning, zodat crawlers de statistiek niet opblazen. */
function pag_is_bot(): bool {
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') return true;
    return (bool)preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|monitor|uptime|curl|wget|python-requests|okhttp|ahrefs|semrush/i', $ua);
}

/**
 * Hoe lang na een bezoek hetzelfde bezoek nog "hetzelfde bezoek" is (minuten).
 * Binnen dit venster telt een nieuwe paginaweergave van dezelfde bezoeker niet opnieuw mee.
 */
function pag_bezoek_venster_min(): int {
    $m = function_exists('instelling_int') ? instelling_int('paginas.bezoek_venster_min', 30) : 30;
    return max(0, min(1440, $m));
}

/**
 * Is deze bezoeker net al geteld op deze pagina?
 * Een browser vraagt een pagina om allerlei redenen opnieuw op zonder dat iemand hem echt
 * opnieuw opent: terug-knop, vernieuwen, of een tabblad dat door Edge/Chrome in slaapstand
 * is gezet en bij het terugwisselen opnieuw wordt geladen. Zonder deze controle telt dat
 * allemaal als bezoek.
 */
function pag_bezoek_recent(?int $pagina_id, string $hash): bool {
    $min = pag_bezoek_venster_min();
    if ($min <= 0) return false;
    try {
        $q = db()->prepare("SELECT 1 FROM pagina_events
                            WHERE type = 'bezoek' AND bezoeker_hash = ? AND pagina_id <=> ?
                              AND moment > DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 1");
        $q->execute([$hash, $pagina_id, $min]);
        return (bool)$q->fetchColumn();
    } catch (\Throwable $e) { error_log('pag_bezoek_recent: ' . $e->getMessage()); return false; }
}

/** Leg een gebeurtenis vast. Alleen het type, de pagina/bron en het dag-pseudoniem. */
function pag_track(string $type, ?int $pagina_id, ?string $bron_code = null): void {
    if (!in_array($type, pag_event_typen(), true)) return;
    if (function_exists('instelling_bool') && !instelling_bool('paginas.tracking', true)) return;
    if ($type === 'bezoek' && pag_is_bot()) return;
    try {
        pag_auto_setup();
        // Herhaalde weergaves binnen het venster niet nóg eens tellen.
        if ($type === 'bezoek' && pag_bezoek_recent($pagina_id, pag_bezoeker_hash())) return;
        db()->prepare("INSERT INTO pagina_events (pagina_id, bron_code, type, bezoeker_hash) VALUES (?,?,?,?)")
            ->execute([$pagina_id, $bron_code, $type, pag_bezoeker_hash()]);
    } catch (\Throwable $e) { error_log('pag_track: ' . $e->getMessage()); }
}

/**
 * Statistiek voor meerdere pagina's in één query (de oude module deed dit per rij).
 * Retour: [pagina_id => ['bezoeken','uniek','starts','klaar','geweigerd','start_pct','klaar_pct']]
 */
function pag_stats_bulk(array $pagina_ids): array {
    $uit = [];
    $ids = array_values(array_unique(array_filter(array_map('intval', $pagina_ids))));
    foreach ($ids as $id) {
        $uit[$id] = ['bezoeken'=>0,'uniek'=>0,'starts'=>0,'klaar'=>0,'geweigerd'=>0,'start_pct'=>0,'klaar_pct'=>0];
    }
    if (!$ids) return $uit;
    try {
        pag_auto_setup();
        $gaten = implode(',', array_fill(0, count($ids), '?'));
        $q = db()->prepare("SELECT pagina_id, type, COUNT(*) AS aantal, COUNT(DISTINCT bezoeker_hash) AS uniek
                            FROM pagina_events WHERE pagina_id IN ($gaten) GROUP BY pagina_id, type");
        $q->execute($ids);
        foreach ($q->fetchAll() as $r) {
            $id = (int)$r['pagina_id'];
            if (!isset($uit[$id])) continue;
            switch ($r['type']) {
                case 'bezoek':              $uit[$id]['bezoeken']  = (int)$r['aantal'];
                                            $uit[$id]['uniek']     = (int)$r['uniek']; break;
                case 'formulier_start':     $uit[$id]['starts']    = (int)$r['aantal']; break;
                case 'formulier_klaar':     $uit[$id]['klaar']     = (int)$r['aantal']; break;
                case 'formulier_geweigerd': $uit[$id]['geweigerd'] = (int)$r['aantal']; break;
            }
        }
    } catch (\Throwable $e) { error_log('pag_stats_bulk: ' . $e->getMessage()); }

    foreach ($uit as $id => $s) {
        $uit[$id]['start_pct'] = $s['uniek']  > 0 ? (int)round($s['starts'] / $s['uniek']  * 100) : 0;
        $uit[$id]['klaar_pct'] = $s['starts'] > 0 ? (int)round($s['klaar']  / $s['starts'] * 100) : 0;
    }
    return $uit;
}

/** Statistiek van één pagina. */
function pag_stats(int $pagina_id): array {
    return pag_stats_bulk([$pagina_id])[$pagina_id];
}

/**
 * Wervingsoverzicht: per bron hoeveel aanmeldingen en hoeveel daarvan aangenomen.
 * Leest de sollicitaties zelf, niet de events — een aanmelding telt pas als hij er echt is.
 */
function pag_werving_overzicht(?int $seizoen_id = null): array {
    try {
        pag_auto_setup();
        $where = "WHERE s.bron_code IS NOT NULL AND s.bron_code <> ''";
        $args = [];
        if ($seizoen_id) { $where .= " AND s.seizoen_id = ?"; $args[] = $seizoen_id; }
        $q = db()->prepare("
            SELECT COALESCE(s.bron_label, s.bron_code) AS bron,
                   s.bron_code,
                   COUNT(*) AS aanmeldingen,
                   SUM(CASE WHEN s.fase = 'aangenomen' THEN 1 ELSE 0 END) AS aangenomen
            FROM sollicitaties s
            $where
            GROUP BY s.bron_code, bron
            ORDER BY aanmeldingen DESC
        ");
        $q->execute($args);
        return $q->fetchAll();
    } catch (\Throwable $e) { return []; }
}

/**
 * AVG-bewaarbeperking. Twee stappen:
 *  - na $hash_dagen het pseudoniem wissen (tellingen blijven, herleidbaarheid verdwijnt)
 *  - na $maanden de gebeurtenis helemaal verwijderen
 * Retour: ['gewist'=>int, 'verwijderd'=>int]
 */
function pag_events_opschonen(int $maanden = 0, int $hash_dagen = 0, bool $dry_run = false): array {
    if ($maanden <= 0)    $maanden    = function_exists('instelling_int') ? instelling_int('bewaar.pagina_events_mnd', 13) : 13;
    if ($hash_dagen <= 0) $hash_dagen = function_exists('instelling_int') ? instelling_int('bewaar.pagina_hash_dagen', 30) : 30;
    $uit = ['gewist' => 0, 'verwijderd' => 0];
    try {
        pag_auto_setup();
        $db = db();
        if ($hash_dagen > 0) {
            $q = $db->prepare("SELECT COUNT(*) FROM pagina_events WHERE bezoeker_hash IS NOT NULL AND moment < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $q->execute([$hash_dagen]);
            $uit['gewist'] = (int)$q->fetchColumn();
            if (!$dry_run && $uit['gewist'] > 0) {
                $db->prepare("UPDATE pagina_events SET bezoeker_hash = NULL WHERE bezoeker_hash IS NOT NULL AND moment < DATE_SUB(NOW(), INTERVAL ? DAY)")
                   ->execute([$hash_dagen]);
            }
        }
        if ($maanden > 0) {
            $q = $db->prepare("SELECT COUNT(*) FROM pagina_events WHERE moment < DATE_SUB(NOW(), INTERVAL ? MONTH)");
            $q->execute([$maanden]);
            $uit['verwijderd'] = (int)$q->fetchColumn();
            if (!$dry_run && $uit['verwijderd'] > 0) {
                $db->prepare("DELETE FROM pagina_events WHERE moment < DATE_SUB(NOW(), INTERVAL ? MONTH)")->execute([$maanden]);
            }
        }
    } catch (\Throwable $e) { error_log('pag_events_opschonen: ' . $e->getMessage()); }
    return $uit;
}

}

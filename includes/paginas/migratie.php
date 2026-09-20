<?php
/**
 * Pagina's — eenmalige overzetting vanuit de oude landingspagina-module.
 *
 * De oude module bewaarde de HTML en de CSS los van elkaar in de database. Hier worden ze
 * samengevoegd tot één compleet HTML-document en als versie 1 weggeschreven, precies zoals
 * een geüpload ontwerp. De statistiek gaat één-op-één mee, zodat er geen historie verdwijnt.
 *
 * De oude tabellen blijven staan: pas verwijderen als op productie is vastgesteld dat alles
 * klopt. Draai eerst met $dry_run = true.
 */

if (!function_exists('pag_migratie_nodig')) {

function pag_migratie_nodig(): bool {
    if (function_exists('instelling') && instelling('paginas.migratie_gedaan') === '1') return false;
    try {
        $db = db();
        if (!function_exists('db_tabel_bestaat') || !db_tabel_bestaat($db, 'landingspaginas')) return false;
        return (int)$db->query("SELECT COUNT(*) FROM landingspaginas")->fetchColumn() > 0;
    } catch (\Throwable $e) { return false; }
}

/** Oude html + css samenvoegen tot één document, zoals een geüpload ontwerp eruit zou zien. */
function pag_migratie_document(array $oud): string {
    $titel = (string)($oud['titel'] ?? 'Landingspagina');
    $css   = trim((string)($oud['css'] ?? ''));
    $html  = (string)($oud['html'] ?? '');

    $doc  = "<!DOCTYPE html>\n<html lang=\"nl\">\n<head>\n<meta charset=\"utf-8\">\n";
    $doc .= "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n";
    $doc .= '<title>' . h($titel) . "</title>\n";
    if ($css !== '') $doc .= "<style>\n" . $css . "\n</style>\n";
    $doc .= "</head>\n<body>\n" . $html . "\n</body>\n</html>\n";
    return $doc;
}

/**
 * De overzetting zelf.
 * Retour: ['ok'=>bool, 'paginas'=>int, 'events'=>int, 'overgeslagen'=>[], 'fout'=>string]
 */
function pag_migreer_van_landing(bool $dry_run = true): array {
    $uit = ['ok' => false, 'paginas' => 0, 'events' => 0, 'overgeslagen' => [], 'fout' => ''];
    try {
        pag_auto_setup();
        $db = db();
        if (!db_tabel_bestaat($db, 'landingspaginas')) { $uit['fout'] = 'Er is geen oude landingspagina-tabel om over te zetten.'; return $uit; }

        $oude = $db->query("SELECT * FROM landingspaginas ORDER BY id")->fetchAll();
        if (!$oude) { $uit['ok'] = true; $uit['fout'] = 'Er staan geen oude pagina\'s klaar.'; return $uit; }

        // De id's overnemen kan alleen als paginas nog leeg is; dan blijft de koppeling met de
        // oude statistiek (landing_events.landing_id) één-op-één. Anders houden we een mapping bij.
        $leeg = (int)$db->query("SELECT COUNT(*) FROM paginas")->fetchColumn() === 0;
        $mapping = [];

        foreach ($oude as $o) {
            $slug = pag_slug_maak((string)$o['slug']);
            if (!pag_slug_geldig($slug)) { $uit['overgeslagen'][] = (string)$o['slug'] . ' (ongeldige URL-naam)'; continue; }

            $bestaat = pag_via_slug($slug);
            if ($bestaat) { $uit['overgeslagen'][] = $slug . ' (bestaat al)'; $mapping[(int)$o['id']] = (int)$bestaat['id']; continue; }
            if ($dry_run) { $uit['paginas']++; $mapping[(int)$o['id']] = (int)$o['id']; continue; }

            $status = in_array($o['status'] ?? '', ['concept','alleen_admin','gepubliceerd'], true) ? $o['status'] : 'concept';
            if ($leeg) {
                $db->prepare("INSERT INTO paginas (id, slug, titel, doelgroep, bron_code, status, aangemaakt_op, gewijzigd_op) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([(int)$o['id'], $slug, (string)$o['titel'], $o['doelgroep'] ?: null,
                              $o['bron_code'] ?: $slug, $status, $o['aangemaakt_op'] ?: date('Y-m-d H:i:s'), $o['gewijzigd_op'] ?: null]);
                $nieuw_id = (int)$o['id'];
            } else {
                $db->prepare("INSERT INTO paginas (slug, titel, doelgroep, bron_code, status, aangemaakt_op, gewijzigd_op) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$slug, (string)$o['titel'], $o['doelgroep'] ?: null,
                              $o['bron_code'] ?: $slug, $status, $o['aangemaakt_op'] ?: date('Y-m-d H:i:s'), $o['gewijzigd_op'] ?: null]);
                $nieuw_id = (int)$db->lastInsertId();
            }
            $mapping[(int)$o['id']] = $nieuw_id;

            // Het ontwerp als versie 1 wegschrijven.
            $doc = pag_migratie_document($o);
            $scan = pag_scan_html($doc);
            $pagina = pag_via_id($nieuw_id);
            $res = pag_versie_opslaan($pagina, $doc, ['bron' => 'migratie', 'bestandsnaam' => $slug . '.html', 'scan' => $scan]);
            if ($res['ok']) {
                // Deze inhoud stond al live; dan hoeft niemand het rapport opnieuw te bevestigen.
                $db->prepare("UPDATE pagina_versies SET scan_bevestigd_op = NOW() WHERE id = ?")->execute([$res['versie_id']]);
                $db->prepare("UPDATE paginas SET actieve_versie_id = ? WHERE id = ?")->execute([$res['versie_id'], $nieuw_id]);
            } else {
                $uit['overgeslagen'][] = $slug . ' (ontwerp niet op te slaan: ' . $res['fout'] . ')';
            }
            $uit['paginas']++;
        }

        // Statistiek meenemen. De nieuwe lijst met gebeurtenistypen is een superset van de oude.
        if (db_tabel_bestaat($db, 'landing_events') && $mapping) {
            foreach ($mapping as $oud_id => $nieuw_id) {
                $q = $db->prepare("SELECT COUNT(*) FROM landing_events WHERE landing_id = ?");
                $q->execute([$oud_id]);
                $aantal = (int)$q->fetchColumn();
                if ($aantal === 0) continue;
                $uit['events'] += $aantal;
                if ($dry_run) continue;
                $db->prepare("INSERT INTO pagina_events (pagina_id, bron_code, type, bezoeker_hash, moment)
                              SELECT ?, bron_code, type, bezoeker_hash, moment FROM landing_events WHERE landing_id = ?")
                   ->execute([$nieuw_id, $oud_id]);
            }
        }

        if (!$dry_run) {
            if (function_exists('instelling_opslaan')) instelling_opslaan('paginas.migratie_gedaan', '1');
            if (function_exists('audit_log')) audit_log('gemigreerd', 'pagina', 0, ['paginas' => $uit['paginas'], 'events' => $uit['events']]);
        }
        $uit['ok'] = true;
        return $uit;
    } catch (\Throwable $e) {
        error_log('pag_migreer_van_landing: ' . $e->getMessage());
        $uit['fout'] = 'De overzetting is afgebroken: ' . $e->getMessage();
        return $uit;
    }
}

}

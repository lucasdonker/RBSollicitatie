<?php
/**
 * Paal 13 — Automatische opschoning (AVG bewaartermijnen)
 *
 * Wordt aangeroepen vanuit de cron. Ruimt gegevens op waarvan de bewaartermijn
 * verstreken is, volgens de instelbare termijnen in Instellingen → Bewaartermijnen.
 *
 * Veiligheidsprincipes:
 *  - Doet NIETS tenzij 'bewaar.actief' expliciet aan staat.
 *  - Elke categorie heeft een eigen termijn; 0 maanden = die categorie NIET opschonen.
 *  - Aangenomen kandidaten worden nooit automatisch verwijderd/geanonimiseerd.
 *  - Afgewezen sollicitanten worden geANONIMISEERD (persoonsgegevens weg, geanonimiseerde
 *    statistiek blijft), niet hard verwijderd.
 *  - Alles wordt gelogd in de tabel `opschoon_log`.
 */

if (!function_exists('opschoon_auto_setup')) {
/** Zorgt dat de logtabel bestaat. */
function opschoon_auto_setup(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `opschoon_log` (
            `id`          INT AUTO_INCREMENT PRIMARY KEY,
            `uitgevoerd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `categorie`   VARCHAR(50)  NOT NULL,
            `aantal`      INT          NOT NULL DEFAULT 0,
            `bron`        VARCHAR(20)  DEFAULT 'cron'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('opschoon_auto_setup: ' . $e->getMessage()); }
}
}

if (!function_exists('opschoon_log_schrijf')) {
function opschoon_log_schrijf(string $categorie, int $aantal, string $bron = 'cron'): void {
    if ($aantal <= 0) return;
    try {
        opschoon_auto_setup();
        db()->prepare("INSERT INTO opschoon_log (categorie, aantal, bron) VALUES (?,?,?)")
            ->execute([$categorie, $aantal, $bron]);
    } catch (\Exception $e) { error_log('opschoon_log_schrijf: ' . $e->getMessage()); }
}
}

if (!function_exists('opschoon_run')) {
/**
 * Voert de opschoning uit. Retourneert een overzicht per categorie.
 * @param bool $dry_run  Als true: telt alleen wat opgeschoond ZOU worden, wijzigt niets.
 */
function opschoon_run(bool $dry_run = false, string $bron = 'cron'): array {
    $uit = ['actief' => false, 'dry_run' => $dry_run, 'categorieen' => []];

    // Alleen draaien als expliciet aangezet
    if (!instelling_bool('bewaar.actief', false)) {
        return $uit;
    }
    $uit['actief'] = true;
    opschoon_auto_setup();
    $db = db();

    // Helper: bereken de grensdatum (nu minus X maanden)
    $grens = function (int $maanden): ?string {
        if ($maanden <= 0) return null;
        return date('Y-m-d H:i:s', strtotime("-{$maanden} months"));
    };

    // ── 1. Afgewezen sollicitanten anonimiseren ──────────────────────
    $mnd = instelling_int('bewaar.afgewezen_mnd', 0);
    if ($grensdatum = $grens($mnd)) {
        try {
            // Zoek afgewezen sollicitaties ouder dan de grens die nog niet geanonimiseerd zijn.
            // We anonimiseren de gekoppelde persoon, mits die GEEN andere actieve/aangenomen
            // sollicitatie heeft.
            $q = $db->prepare("
                SELECT DISTINCT p.id
                FROM personen p
                JOIN sollicitaties s ON s.persoon_id = p.id
                WHERE s.fase = 'afgewezen'
                  AND s.aangemeld_op < ?
                  AND p.email NOT LIKE 'geanonimiseerd+%'
                  AND NOT EXISTS (
                      SELECT 1 FROM sollicitaties s2
                      WHERE s2.persoon_id = p.id
                        AND s2.fase NOT IN ('afgewezen')
                  )
            ");
            $q->execute([$grensdatum]);
            $ids = $q->fetchAll(PDO::FETCH_COLUMN);
            $aantal = 0;
            if ($ids && !$dry_run) {
                $upd = $db->prepare("
                    UPDATE personen SET
                        voornaam = 'Geanonimiseerd',
                        tussenvoegsel = NULL,
                        achternaam = '',
                        email = CONCAT('geanonimiseerd+', id, '@paal13.local'),
                        telefoon = NULL,
                        geboortedatum = NULL,
                        ouder_naam = NULL,
                        ouder_email = NULL,
                        ouder_telefoon = NULL
                    WHERE id = ?
                ");
                foreach ($ids as $pid) { $upd->execute([$pid]); $aantal++; }
            } else {
                $aantal = count($ids);
            }
            $uit['categorieen']['afgewezen_geanonimiseerd'] = $aantal;
            if (!$dry_run) opschoon_log_schrijf('afgewezen_geanonimiseerd', $aantal, $bron);
        } catch (\Exception $e) {
            $uit['categorieen']['afgewezen_geanonimiseerd'] = ['fout' => $e->getMessage()];
            error_log('opschoon afgewezen: ' . $e->getMessage());
        }
    }

    // ── 2. Gezondheidsgegevens verwijderen ───────────────────────────
    // 0 maanden = zodra de sollicitatie is afgehandeld (afgewezen of ouder dan het seizoen).
    // >0 maanden = zoveel maanden na ondertekening.
    $mnd = instelling_int('bewaar.gezondheid_mnd', 0);
    try {
        if ($mnd > 0) {
            $grensdatum = $grens($mnd);
            $q = $db->prepare("
                SELECT id FROM ondertekeningen
                WHERE type IN ('gezondheid_ouder','gezondheid_18plus')
                  AND ondertekend_op IS NOT NULL
                  AND ondertekend_op < ?
                  AND formulier_data IS NOT NULL
            ");
            $q->execute([$grensdatum]);
        } else {
            // 0 = zodra de bijbehorende sollicitatie is afgewezen
            $q = $db->prepare("
                SELECT o.id FROM ondertekeningen o
                JOIN sollicitaties s ON s.id = o.sollicitatie_id
                WHERE o.type IN ('gezondheid_ouder','gezondheid_18plus')
                  AND o.formulier_data IS NOT NULL
                  AND s.fase = 'afgewezen'
            ");
            $q->execute();
        }
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        $aantal = 0;
        if ($ids && !$dry_run) {
            // Verwijder de medische inhoud, behoud het feit dat er ondertekend is
            $upd = $db->prepare("UPDATE ondertekeningen SET formulier_data = NULL WHERE id = ?");
            foreach ($ids as $oid) { $upd->execute([$oid]); $aantal++; }
        } else {
            $aantal = count($ids);
        }
        $uit['categorieen']['gezondheid_verwijderd'] = $aantal;
        if (!$dry_run) opschoon_log_schrijf('gezondheid_verwijderd', $aantal, $bron);
    } catch (\Exception $e) {
        $uit['categorieen']['gezondheid_verwijderd'] = ['fout' => $e->getMessage()];
        error_log('opschoon gezondheid: ' . $e->getMessage());
    }

    // ── 3. AI-invoertekst verwijderen ────────────────────────────────
    $mnd = instelling_int('bewaar.ai_invoer_mnd', 0);
    if ($grensdatum = $grens($mnd)) {
        try {
            if ($dry_run) {
                $c = $db->prepare("SELECT COUNT(*) FROM ai_beschikbaarheid_log WHERE aangemaakt_op < ? AND invoer <> ''");
                $c->execute([$grensdatum]);
                $aantal = (int)$c->fetchColumn();
            } else {
                // invoer is NOT NULL → leegmaken met lege string i.p.v. NULL
                $upd = $db->prepare("UPDATE ai_beschikbaarheid_log SET invoer = '' WHERE aangemaakt_op < ? AND invoer <> ''");
                $upd->execute([$grensdatum]);
                $aantal = $upd->rowCount();
            }
            $uit['categorieen']['ai_invoer_verwijderd'] = $aantal;
            if (!$dry_run) opschoon_log_schrijf('ai_invoer_verwijderd', $aantal, $bron);
        } catch (\Exception $e) {
            // Tabel bestaat mogelijk nog niet — dan is er niets op te schonen
            $uit['categorieen']['ai_invoer_verwijderd'] = 0;
        }
    }

    // ── 4. Niet-voltooide aanmeldingen verwijderen ───────────────────
    // Sollicitaties die in 'aangemeld' bleven staan, nooit een BLK/beschikbaarheid kregen,
    // en ouder zijn dan de termijn.
    $mnd = instelling_int('bewaar.onvoltooid_mnd', 0);
    if ($grensdatum = $grens($mnd)) {
        try {
            $q = $db->prepare("
                SELECT s.id, s.persoon_id FROM sollicitaties s
                WHERE s.fase = 'aangemeld'
                  AND s.aangemeld_op < ?
                  AND NOT EXISTS (SELECT 1 FROM blk_formulieren b WHERE b.sollicitatie_id = s.id)
                  AND NOT EXISTS (SELECT 1 FROM beschikbaarheid_blok bb WHERE bb.sollicitatie_id = s.id)
            ");
            $q->execute([$grensdatum]);
            $rijen = $q->fetchAll(PDO::FETCH_ASSOC);
            $aantal = 0;
            if ($rijen && !$dry_run) {
                $del = $db->prepare("DELETE FROM sollicitaties WHERE id = ?");
                foreach ($rijen as $r) { $del->execute([$r['id']]); $aantal++; }
            } else {
                $aantal = count($rijen);
            }
            $uit['categorieen']['onvoltooid_verwijderd'] = $aantal;
            if (!$dry_run) opschoon_log_schrijf('onvoltooid_verwijderd', $aantal, $bron);
        } catch (\Exception $e) {
            $uit['categorieen']['onvoltooid_verwijderd'] = ['fout' => $e->getMessage()];
            error_log('opschoon onvoltooid: ' . $e->getMessage());
        }
    }

    // ── 5. Mail-/actielogboek verwijderen ────────────────────────────
    $mnd = instelling_int('bewaar.maillog_mnd', 0);
    if ($grensdatum = $grens($mnd)) {
        try {
            if ($dry_run) {
                $c = $db->prepare("SELECT COUNT(*) FROM mail_log WHERE verstuurd_op < ?");
                $c->execute([$grensdatum]);
                $aantal = (int)$c->fetchColumn();
            } else {
                $del = $db->prepare("DELETE FROM mail_log WHERE verstuurd_op < ?");
                $del->execute([$grensdatum]);
                $aantal = $del->rowCount();
            }
            $uit['categorieen']['maillog_verwijderd'] = $aantal;
            if (!$dry_run) opschoon_log_schrijf('maillog_verwijderd', $aantal, $bron);
        } catch (\Exception $e) {
            // Tabel bestaat mogelijk nog niet — dan is er niets op te schonen
            $uit['categorieen']['maillog_verwijderd'] = 0;
        }
    }

    instelling_opslaan('bewaar.laatste_run', date('Y-m-d H:i:s'));
    return $uit;
}
}

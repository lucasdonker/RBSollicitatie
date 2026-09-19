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

    // ── 5a. Inhoud van berichten (body) leegmaken — de logregel zelf blijft ──
    $mnd_body = instelling_int('bewaar.maillog_body_mnd', 3);
    if ($grensdatum = $grens($mnd_body)) {
        try {
            if ($dry_run) {
                $c = $db->prepare("SELECT COUNT(*) FROM mail_log WHERE body IS NOT NULL AND verstuurd_op < ?");
                $c->execute([$grensdatum]);
                $aantal = (int)$c->fetchColumn();
            } else {
                $upd = $db->prepare("UPDATE mail_log SET body=NULL WHERE body IS NOT NULL AND verstuurd_op < ?");
                $upd->execute([$grensdatum]);
                $aantal = $upd->rowCount();
            }
            $uit['categorieen']['berichtinhoud_geleegd'] = $aantal;
            if (!$dry_run && $aantal) opschoon_log_schrijf('berichtinhoud_geleegd', $aantal, $bron);
        } catch (\Exception $e) { $uit['categorieen']['berichtinhoud_geleegd'] = 0; }
    }

    // ── 5b. Bijlagen die nergens meer aan een sjabloon hangen en ouder zijn dan N maanden ──
    $mnd_bijl = instelling_int('bewaar.bijlagen_mnd', 12);
    if (($grensdatum = $grens($mnd_bijl)) && function_exists('bijlagen_lijst')) {
        try {
            $in_gebruik = function_exists('bijlagen_in_gebruik') ? bijlagen_in_gebruik() : [];
            $aantal = 0;
            foreach (bijlagen_lijst() as $b) {
                if (in_array((int)$b['id'], $in_gebruik, true) || $b['aangemaakt_op'] >= $grensdatum) continue;
                if ($dry_run) { $aantal++; continue; }
                if (bijlage_verwijderen((int)$b['id'])) $aantal++;
            }
            $uit['categorieen']['bijlagen_verwijderd'] = $aantal;
            if (!$dry_run && $aantal) opschoon_log_schrijf('bijlagen_verwijderd', $aantal, $bron);
        } catch (\Exception $e) { $uit['categorieen']['bijlagen_verwijderd'] = 0; }
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

    // Auditlog opschonen (bewaartermijn, default 12 maanden)
    try {
        $audit_maanden = (int)instelling('bewaar.audit_maanden', '12');
        if ($audit_maanden > 0) {
            $audit_grens = date('Y-m-d H:i:s', strtotime("-$audit_maanden months"));
            if ($dry_run) {
                $c = $db->prepare("SELECT COUNT(*) FROM audit_log WHERE moment < ?");
                $c->execute([$audit_grens]);
                $aantal = (int)$c->fetchColumn();
            } else {
                $del = $db->prepare("DELETE FROM audit_log WHERE moment < ?");
                $del->execute([$audit_grens]);
                $aantal = $del->rowCount();
            }
            $uit['categorieen']['auditlog_verwijderd'] = $aantal;
            if (!$dry_run) opschoon_log_schrijf('auditlog_verwijderd', $aantal, $bron);
        }
    } catch (\Exception $e) {
        $uit['categorieen']['auditlog_verwijderd'] = 0;
    }

    // ── 6. Loondocumenten (loonheffingsformulier + kopie ID): wettelijke bewaartermijn, jaren na einde dienstjaar ──
    // Wet LB art. 28: bewaren tot 5 jaar na het einde van het kalenderjaar waarin het dienstverband eindigde.
    // Het dienstjaar = het jaar van het seizoen (datum_werk_eind, anders het jaar van aanmelding).
    $jaar_bewaar = instelling_int('bewaar.loondocumenten_jaar', 5);
    if ($jaar_bewaar > 0 && function_exists('document_vernietigen')) {
        try {
            $grensjaar = (int)date('Y') - $jaar_bewaar; // dienstjaren t/m dit jaar mogen weg
            $q = $db->prepare("
                SELECT d.id FROM sollicitant_documenten d
                JOIN sollicitaties s ON s.id = d.sollicitatie_id
                LEFT JOIN seizoenen sz ON sz.id = s.seizoen_id
                WHERE d.status IN ('ingediend','verwerkt')
                  AND d.soort IN ('id_voor','id_achter','ondertekening_loonheffing')
                  AND YEAR(COALESCE(sz.datum_werk_eind, s.aangemeld_op)) <= ?
            ");
            $q->execute([$grensjaar]);
            $ids = $q->fetchAll(PDO::FETCH_COLUMN);
            $aantal = 0;
            foreach ($ids as $id) { if ($dry_run) { $aantal++; continue; } if (document_vernietigen((int)$id, 'bewaartermijn loondocumenten verstreken')) $aantal++; }
            if (!$dry_run && $ids) {
                // Het ondertekeningsrecord blijft (bewijs dat er getekend is), maar zonder BSN en handtekening
                $db->prepare("UPDATE ondertekeningen o JOIN sollicitaties s ON s.id=o.sollicitatie_id LEFT JOIN seizoenen sz ON sz.id=s.seizoen_id
                              SET o.vertrouwelijk=NULL, o.handtekening_beeld=NULL, o.pdf_document_id=NULL
                              WHERE o.type='loonheffing' AND YEAR(COALESCE(sz.datum_werk_eind, s.aangemeld_op)) <= ?")->execute([$grensjaar]);
            }
            $uit['categorieen']['loondocumenten_vernietigd'] = $aantal;
            if (!$dry_run && $aantal) opschoon_log_schrijf('loondocumenten_vernietigd', $aantal, $bron);
        } catch (\Exception $e) { $uit['categorieen']['loondocumenten_vernietigd'] = ['fout' => $e->getMessage()]; error_log('opschoon loondocumenten: ' . $e->getMessage()); }
    }

    // ── 7. Kopie bankpas: vernietigen X dagen nadat de penningmeester het dossier heeft verwerkt ──
    $dagen_bankpas = instelling_int('bewaar.bankpas_na_verwerking_dagen', 30);
    if ($dagen_bankpas > 0 && function_exists('document_vernietigen')) {
        try {
            $q = $db->prepare("SELECT id FROM sollicitant_documenten WHERE soort='bankpas' AND status='verwerkt' AND verwerkt_op IS NOT NULL AND verwerkt_op < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $q->execute([$dagen_bankpas]);
            $ids = $q->fetchAll(PDO::FETCH_COLUMN);
            $aantal = 0;
            foreach ($ids as $id) { if ($dry_run) { $aantal++; continue; } if (document_vernietigen((int)$id, 'bankpas na verwerking')) $aantal++; }
            $uit['categorieen']['bankpas_vernietigd'] = $aantal;
            if (!$dry_run && $aantal) opschoon_log_schrijf('bankpas_vernietigd', $aantal, $bron);
        } catch (\Exception $e) { $uit['categorieen']['bankpas_vernietigd'] = ['fout' => $e->getMessage()]; }
    }

    instelling_opslaan('bewaar.laatste_run', date('Y-m-d H:i:s'));
    return $uit;
}
}

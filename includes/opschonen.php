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
 *  - Loondocumenten (loonheffing, kopie ID) worden hier NOOIT vernietigd. De fiscale termijn
 *    loopt tot 5 jaar na het einde van het jaar waarin het dienstverband eindigde, en dat
 *    einde kent het systeem niet (een lifeguard kan na een pauze terugkomen). De
 *    penningmeester vernietigt ze handmatig (admin/documenten.php); besluit Lucas, 23 sep 2026.
 *  - Oud-lifeguards: het dossier wordt na bewaar.aangenomen_jaar leeggemaakt, de persoon pas
 *    geanonimiseerd als er geen loondocumenten meer zijn.
 *  - Afgewezen sollicitanten worden geANONIMISEERD (persoonsgegevens weg, geanonimiseerde
 *    statistiek blijft), niet hard verwijderd. Wat er dan precies verdwijnt staat op één
 *    plek, gedeeld met "gegevens laten verwijderen" in het portaal: includes/avg_wissen.php.
 *    Een seintje (includes/seintje.php) stelt dat uit tot de toestemming verloopt.
 *  - Alles wordt gelogd in de tabel `opschoon_log`.
 */

if (!function_exists('opschoon_auto_setup')) {
/** Zorgt dat de logtabel bestaat. */
function opschoon_auto_setup(): void {
    static $done = false; if ($done || schema_actueel()) return; $done = true;
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

if (!function_exists('opschoon_rijen')) {
/**
 * Tel (proefdraaien) of verwijder de rijen van één tabel die aan $waar voldoen.
 * $tabel en $waar komen alleen uit deze code, nooit uit invoer.
 */
function opschoon_rijen($db, string $tabel, string $waar, array $params, bool $dry_run): int {
    if ($dry_run) {
        $c = $db->prepare("SELECT COUNT(*) FROM `$tabel` WHERE $waar");
        $c->execute($params);
        return (int)$c->fetchColumn();
    }
    $d = $db->prepare("DELETE FROM `$tabel` WHERE $waar");
    $d->execute($params);
    return $d->rowCount();
}

/** De documentsoorten met een fiscale bewaarplicht (bewaren = 'loon' in document_soorten()). */
function opschoon_loonsoorten(): array {
    if (!function_exists('document_soorten')) return ['id_voor', 'id_achter', 'ondertekening_loonheffing'];
    $uit = [];
    foreach (document_soorten() as $soort => $def) if (($def['bewaren'] ?? '') === 'loon') $uit[] = $soort;
    return $uit ?: ['id_voor', 'id_achter', 'ondertekening_loonheffing'];
}

/**
 * Vervallen codes en tellingen weggooien: gebruikte of verlopen inlog- en verificatiecodes
 * (otp_tokens) en oude regels van de snelheidsbegrenzing. Geen dossiers en geen
 * bewaartermijn, dus los van bewaar.actief; de cron roept dit elke run aan. Er komt geen
 * regel in opschoon_log: dat zou elke vijf minuten een regel in het logboek geven.
 * Een uur marge: een code die net verliep, kan nog in een formulier op het scherm staan.
 */
function opschoon_vervallen(): array {
    $uit = ['codes' => 0, 'tellingen' => 0];
    try {
        $uit['codes'] = opschoon_rijen(db(), 'otp_tokens',
            "vervalt_op < DATE_SUB(NOW(), INTERVAL 1 HOUR) OR (gebruikt = 1 AND aangemaakt_op < DATE_SUB(NOW(), INTERVAL 1 HOUR))", [], false);
    } catch (\Throwable $e) {}
    if (function_exists('rate_limit_opruimen')) $uit['tellingen'] = rate_limit_opruimen();
    return $uit;
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

    // ── 1. Afgewezen en afgehaakte sollicitanten anonimiseren ────────
    // Termijn in weken, gerekend vanaf de laatste wijziging van de sollicitatie (bij een
    // afwijzing is dat het moment van afwijzen; zie fase_zetten). De AP gaat uit van vier
    // weken. Wie om een seintje vroeg (includes/seintje.php) blijft tot die toestemming
    // verloopt. Alleen personen bij wie élke sollicitatie zo is afgelopen: iemand die ooit
    // is aangenomen of nog loopt, valt hierbuiten.
    $weken = instelling_int('bewaar.afgewezen_weken', 4);
    if ($weken > 0) {
        try {
            if (function_exists('seintje_auto_setup')) seintje_auto_setup();
            $seintje_mnd = function_exists('seintje_termijn_mnd') ? seintje_termijn_mnd() : 12;
            $q = $db->prepare("
                SELECT p.id
                FROM personen p
                WHERE p.email NOT LIKE 'geanonimiseerd+%' AND p.email NOT LIKE '%@anoniem.local'
                  AND EXISTS (SELECT 1 FROM sollicitaties s WHERE s.persoon_id = p.id)
                  AND NOT EXISTS (
                      SELECT 1 FROM sollicitaties s2
                      WHERE s2.persoon_id = p.id
                        AND (s2.fase NOT IN ('afgewezen','afgehaakt')
                             OR COALESCE(s2.gewijzigd_op, s2.aangemeld_op) >= DATE_SUB(NOW(), INTERVAL ? WEEK))
                  )
                  AND (p.seintje_op IS NULL OR p.seintje_op < DATE_SUB(NOW(), INTERVAL ? MONTH))
            ");
            $q->execute([$weken, $seintje_mnd]);
            $ids = $q->fetchAll(PDO::FETCH_COLUMN);
            $aantal = 0;
            if ($ids && !$dry_run) {
                $sols = $db->prepare("SELECT id FROM sollicitaties WHERE persoon_id = ?");
                foreach ($ids as $pid) {
                    $pid = (int)$pid;
                    $email = 'geanonimiseerd+' . $pid . '@paal13.local';
                    // Per persoon een eigen transactie: één fout houdt de rest niet tegen.
                    $db->beginTransaction();
                    try {
                        $sols->execute([$pid]);
                        foreach ($sols->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                            avg_sollicitatie_leegmaken($db, (int)$sid);
                            avg_maillog_anonimiseren($db, (int)$sid, $email);
                        }
                        avg_persoon_anonimiseren($db, $pid, 'Geanonimiseerd', '', $email);
                        $db->commit();
                        $aantal++;
                    } catch (\Throwable $e) {
                        if ($db->inTransaction()) $db->rollBack();
                        error_log("opschoon afgewezen persoon $pid: " . $e->getMessage());
                    }
                }
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

    // ── 1b. Oud-lifeguards: dossier leegmaken, persoon anonimiseren ──
    // Zolang iemand bij ons werkt blijft alles staan; daarna nog bewaar.aangenomen_jaar
    // (standaard 2, de lijn van de AP voor een personeelsdossier), gerekend vanaf het einde
    // van het laatste seizoen. Wie een nieuwe sollicitatie heeft lopen, valt erbuiten.
    // Loonheffing en kopie ID blijven: die hebben een fiscale termijn en de penningmeester
    // vernietigt ze met de hand. Pas als ze weg zijn, wordt de persoon zelf geanonimiseerd —
    // tot die tijd staan naam en geboortedatum er nog bij, anders is het bewaarde stuk niet
    // terug te vinden. Bij het anonimiseren gaat ook de rest van de loonheffing-ondertekening
    // mee (BSN en handtekening), want het document waarvoor die bewaard werd is dan weg.
    $jaar = instelling_int('bewaar.aangenomen_jaar', 2);
    if ($jaar > 0) {
        try {
            $q = $db->prepare("
                SELECT p.id
                FROM personen p
                WHERE p.email NOT LIKE 'geanonimiseerd+%' AND p.email NOT LIKE '%@anoniem.local'
                  AND EXISTS (SELECT 1 FROM sollicitaties s WHERE s.persoon_id = p.id AND s.fase = 'aangenomen')
                  AND NOT EXISTS (
                      SELECT 1 FROM sollicitaties s2
                      LEFT JOIN seizoenen sz ON sz.id = s2.seizoen_id
                      WHERE s2.persoon_id = p.id
                        AND (s2.fase NOT IN ('aangenomen','afgewezen','afgehaakt')
                             OR COALESCE(sz.datum_werk_eind, DATE(s2.gewijzigd_op), DATE(s2.aangemeld_op))
                                >= DATE_SUB(CURDATE(), INTERVAL ? YEAR))
                  )
            ");
            $q->execute([$jaar]);
            $ids = $q->fetchAll(PDO::FETCH_COLUMN);
            $aantal = 0;
            if ($ids && !$dry_run) {
                $sols = $db->prepare("SELECT id FROM sollicitaties WHERE persoon_id = ?");
                $loonsoorten = opschoon_loonsoorten();
                $loon = $db->prepare("SELECT COUNT(*) FROM sollicitant_documenten d JOIN sollicitaties s ON s.id = d.sollicitatie_id
                                      WHERE s.persoon_id = ? AND d.status IN ('ingediend','verwerkt')
                                        AND d.soort IN (" . implode(',', array_fill(0, count($loonsoorten), '?')) . ")");
                foreach ($ids as $pid) {
                    $pid = (int)$pid;
                    $email = 'geanonimiseerd+' . $pid . '@paal13.local';
                    $db->beginTransaction();
                    try {
                        $sols->execute([$pid]);
                        $sids = array_map('intval', $sols->fetchAll(PDO::FETCH_COLUMN));
                        foreach ($sids as $sid) avg_sollicitatie_leegmaken($db, $sid, false);
                        $loon->execute(array_merge([$pid], $loonsoorten));
                        if ((int)$loon->fetchColumn() === 0) {
                            // Geen loondocumenten meer: nu mag ook het restant van de loonheffing weg.
                            foreach ($sids as $sid) { avg_sollicitatie_leegmaken($db, $sid, true); avg_maillog_anonimiseren($db, $sid, $email); }
                            avg_persoon_anonimiseren($db, $pid, 'Geanonimiseerd', '', $email);
                            $aantal++;
                        }
                        $db->commit();
                    } catch (\Throwable $e) {
                        if ($db->inTransaction()) $db->rollBack();
                        error_log("opschoon aangenomen persoon $pid: " . $e->getMessage());
                    }
                }
            } else {
                $aantal = count($ids);
            }
            $uit['categorieen']['aangenomen_geanonimiseerd'] = $aantal;
            if (!$dry_run) opschoon_log_schrijf('aangenomen_geanonimiseerd', $aantal, $bron);
        } catch (\Exception $e) {
            $uit['categorieen']['aangenomen_geanonimiseerd'] = ['fout' => $e->getMessage()];
            error_log('opschoon aangenomen: ' . $e->getMessage());
        }
    }

    // ── 2. Gezondheidsgegevens verwijderen ───────────────────────────
    // 0 maanden = na afloop van het seizoen, en meteen bij afwijzen of afhaken.
    // >0 maanden = zoveel maanden na ondertekening.
    // Het gaat om de gezondheidsverklaring én de gezondheidsvragen in de intake, in de
    // database en in de verzegelde PDF in de kluis. Het feit dát er ondertekend is blijft,
    // en bij de intake ook de rest (toestemming voor foto's, verklaringen).
    $mnd = instelling_int('bewaar.gezondheid_mnd', 0);
    try {
        $klaar = $mnd > 0
            ? "o.ondertekend_op < ?"
            : "(s.fase IN ('afgewezen','afgehaakt')
                OR (sz.datum_werk_eind IS NOT NULL AND sz.datum_werk_eind < CURDATE())
                OR (sz.datum_werk_eind IS NULL AND o.ondertekend_op < DATE_SUB(NOW(), INTERVAL 12 MONTH)))";
        $q = $db->prepare("
            SELECT o.id, o.type, o.formulier_data, o.pdf_document_id FROM ondertekeningen o
            JOIN sollicitaties s ON s.id = o.sollicitatie_id
            LEFT JOIN seizoenen sz ON sz.id = s.seizoen_id
            WHERE o.type IN ('gezondheid_ouder','gezondheid_18plus','intake','intake_ouder')
              AND o.ondertekend_op IS NOT NULL
              AND (o.formulier_data IS NOT NULL OR o.pdf_document_id IS NOT NULL)
              AND $klaar
        ");
        $q->execute($mnd > 0 ? [$grens($mnd)] : []);
        $aantal = 0;
        $upd = $db->prepare("UPDATE ondertekeningen SET formulier_data = ?, pdf_document_id = NULL WHERE id = ?");
        foreach ($q->fetchAll() as $o) {
            $data = null;
            if (str_starts_with((string)$o['type'], 'intake')) {
                $form = json_decode((string)$o['formulier_data'], true);
                $had  = is_array($form) && (array_key_exists('gezondheid_keuze', $form) || array_key_exists('gezondheid_toelichting', $form));
                if (!$had && !$o['pdf_document_id']) continue;       // al eerder opgeschoond
                if (is_array($form)) { unset($form['gezondheid_keuze'], $form['gezondheid_toelichting']); $data = json_encode($form, JSON_UNESCAPED_UNICODE); }
            }
            $aantal++;
            if ($dry_run) continue;
            if ($o['pdf_document_id'] && function_exists('document_vernietigen')) document_vernietigen((int)$o['pdf_document_id'], 'bewaartermijn gezondheidsgegevens');
            $upd->execute([$data, $o['id']]);
        }
        $uit['categorieen']['gezondheid_verwijderd'] = $aantal;
        if (!$dry_run) opschoon_log_schrijf('gezondheid_verwijderd', $aantal, $bron);
    } catch (\Exception $e) {
        $uit['categorieen']['gezondheid_verwijderd'] = ['fout' => $e->getMessage()];
        error_log('opschoon gezondheid: ' . $e->getMessage());
    }

    // ── 3. AI-invoertekst verwijderen ────────────────────────────────
    $mnd = instelling_int('bewaar.ai_invoer_mnd', 3);
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
    $mnd = instelling_int('bewaar.onvoltooid_mnd', 3);
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
    $mnd = instelling_int('bewaar.maillog_mnd', 12);
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

    // Bezoekersstatistiek van landingspagina's. Twee stappen: eerst het dag-pseudoniem wissen
    // (tellingen blijven, herleidbaarheid verdwijnt), daarna de gebeurtenis zelf verwijderen.
    if (function_exists('pag_events_opschonen')) {
        try {
            $r = pag_events_opschonen(0, 0, $dry_run);
            $uit['categorieen']['pagina_hash_gewist']     = $r['gewist'];
            $uit['categorieen']['pagina_events_verwijderd'] = $r['verwijderd'];
            if (!$dry_run) {
                if ($r['gewist'])     opschoon_log_schrijf('pagina_hash_gewist', $r['gewist'], $bron);
                if ($r['verwijderd']) opschoon_log_schrijf('pagina_events_verwijderd', $r['verwijderd'], $bron);
            }
        } catch (\Exception $e) {
            $uit['categorieen']['pagina_events_verwijderd'] = 0;
        }
    }

    // ── 6. Loondocumenten: bewust GEEN automatische stap (zie de kop van dit bestand). ──

    // ── 6a. Berichtenwachtrij: verwerkte en mislukte rijen na 30 dagen weg ──
    // Een rij bevat adres, telefoon en de volledige tekst; na verzending is hij alleen nog
    // nodig om een storing na te lopen. Het overzicht van verstuurde berichten staat in mail_log.
    try {
        $aantal = opschoon_rijen($db, 'mail_queue',
            "status IN ('klaar','fout') AND aangemaakt_op < DATE_SUB(NOW(), INTERVAL 30 DAY)", [], $dry_run);
        $uit['categorieen']['wachtrij_opgeschoond'] = $aantal;
        if (!$dry_run) opschoon_log_schrijf('wachtrij_opgeschoond', $aantal, $bron);
    } catch (\Exception $e) { $uit['categorieen']['wachtrij_opgeschoond'] = 0; }

    // ── 6b. Foutenlog: bevat e-mailadressen van gebruikers en URL's ──
    $dagen_fout = instelling_int('bewaar.foutenlog_dagen', 90);
    if ($dagen_fout > 0) {
        try {
            $aantal = opschoon_rijen($db, 'error_log', "aangemaakt_op < DATE_SUB(NOW(), INTERVAL ? DAY)", [$dagen_fout], $dry_run);
            $uit['categorieen']['foutenlog_verwijderd'] = $aantal;
            if (!$dry_run) opschoon_log_schrijf('foutenlog_verwijderd', $aantal, $bron);
        } catch (\Exception $e) { $uit['categorieen']['foutenlog_verwijderd'] = 0; }
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

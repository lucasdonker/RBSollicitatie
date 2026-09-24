<?php
/**
 * Paal 13 — Automatische ondertekeningen bij selectiedag-uitnodiging
 *
 * Flow:
 *  - 18+: gezondheid_18plus + intake klaargezet als portaal-taak (geen extra mail).
 *  - <18 MET ouder-gegevens: gezondheid_ouder + intake_ouder aangemaakt en direct
 *    naar de ouder gemaild.
 *  - <18 ZONDER ouder-gegevens: sollicitant ziet portaal-taak "vul ouder-gegevens in";
 *    na invullen draait deze functie opnieuw en gaan de ouder-mails automatisch.
 *
 * Instelbaar via instelling('auto.ondertekening_sd', '1').
 */

function personen_ouder_auto_setup(): void {
    static $done = false;
    if ($done || schema_actueel()) return;
    $done = true;
    try {
        $db = db();
        $dbn = $db->query("SELECT DATABASE()")->fetchColumn();
        foreach (['ouder_naam' => 'VARCHAR(150) DEFAULT NULL', 'ouder_email' => 'VARCHAR(200) DEFAULT NULL'] as $kol => $def) {
            $c = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='personen' AND COLUMN_NAME=?");
            $c->execute([$dbn, $kol]);
            if (!$c->fetchColumn()) $db->exec("ALTER TABLE `personen` ADD COLUMN `$kol` $def");
        }
    } catch (\Exception $e) { error_log('personen_ouder_auto_setup: ' . $e->getMessage()); }
}

// ondertekening_label() en de documentteksten staan in includes/ondertekening_tekst.php (één bron).

/** Onderteken-URL voor een token. */
function ondertekening_url(string $token): string {
    return rtrim(APP_URL, '/') . '/portaal/ondertekenen.php?token=' . urlencode($token);
}

/**
 * Het knoppenblok voor in een ondertekenverzoek: label => url. Tabel-layout voor Outlook.
 * Wordt als {{formulieren_blok}} in het sjabloon gezet (niet ge-escaped).
 */
function ondertekening_formulieren_blok(array $links): string {
    $uit = '';
    foreach ($links as $label => $url) {
        $uit .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 12px"><tr><td style="background:#f7f9fc;border:1px solid #dde5ef;border-radius:8px;padding:16px 20px">
<p style="margin:0 0 10px;font-family:Arial,sans-serif;font-size:15px;font-weight:700;color:#054d8a">' . h($label) . '</p>
<a href="' . h($url) . '" style="display:inline-block;background:#D43D00;color:#ffffff;padding:12px 24px;border-radius:6px;font-family:Arial,sans-serif;font-size:14px;font-weight:600;text-decoration:none">Openen en ondertekenen &rarr;</a>
<p style="margin:10px 0 0;font-family:Arial,sans-serif;font-size:11px;color:#8a94a3;word-break:break-all">Of kopieer: ' . h($url) . '</p>
</td></tr></table>';
    }
    return $uit;
}

/**
 * Verstuur een ondertekenverzoek voor één of meer documenten.
 * $ontvanger: ['email','naam','rol' => 'ouder'|'sollicitant']; $links: label => url.
 */
function ondertekening_verzoek_versturen(int $sol_id, array $ontvanger, array $links, string $referentie): array {
    if (!function_exists('melding_verstuur') || !$links) return ['status' => 'overgeslagen', 'reden' => 'geen_kern'];
    $labels = array_keys($links);
    $extra  = ['formulieren_blok' => ondertekening_formulieren_blok($links), 'document_naam' => implode(' en ', $labels), 'onderteken_url' => reset($links)];
    /* Ná het antwoord: dit bericht bevat alleen links, geen bijlage vanaf schijf, dus
       er is niets dat op de verzending moet wachten. Het scheelde de sollicitant een
       paar seconden staren naar een pagina die niets deed nadat hij de gegevens van
       zijn ouder had ingevuld. De aanroepers kijken naar status 'verstuurd' óf 'wacht'. */
    $opties = ['referentie' => $referentie, 'prioriteit' => 'na_antwoord'];
    if (($ontvanger['rol'] ?? 'ouder') === 'ouder') {
        $opties['ontvanger'] = ['email' => $ontvanger['email'], 'naam' => $ontvanger['naam'] ?: 'ouder/verzorger', 'telefoon' => '', 'rol' => 'ouder'];
        $extra['ouder_naam'] = $ontvanger['naam'] ?: 'ouder/verzorger';
        return melding_verstuur('ondertekening_verzoek_ouder', $sol_id, $extra, $opties);
    }
    return melding_verstuur('ondertekening_verzoek_zelf', $sol_id, $extra, $opties);
}

/**
 * Eén ondertekening van een type klaarzetten voor de sollicitant zelf (idempotent). Retour: 'aangemaakt' | 'bestond' | 'fout'.
 */
function ondertekening_zelf_verzekeren(int $sol_id, string $type): string {
    try {
        $db = db();
        $check = $db->prepare("SELECT 1 FROM ondertekeningen WHERE sollicitatie_id=? AND type=? LIMIT 1");
        $check->execute([$sol_id, $type]);
        if ($check->fetchColumn()) return 'bestond';
        $q = $db->prepare("SELECT p.email FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE s.id=?");
        $q->execute([$sol_id]);
        $email = (string)($q->fetchColumn() ?: '');
        $token = bin2hex(random_bytes(24));
        $db->prepare("INSERT INTO ondertekeningen (sollicitatie_id, type, token, ondertekenaar_rol, ondertekenaar_email) VALUES (?,?,?,'sollicitant',?)")
           ->execute([$sol_id, $type, $token, $email ?: null]);
        if (function_exists('ondertekening_gebeurtenis')) ondertekening_gebeurtenis((int)$db->lastInsertId(), 'aangemaakt', ['type' => $type]);
        return 'aangemaakt';
    } catch (\Throwable $e) { error_log("ondertekening_zelf_verzekeren($type): " . $e->getMessage()); return 'fout'; }
}

/** Aangenomen: het loonheffingsformulier (werknemer tekent altijd zelf, ook onder 18). */
function loonheffing_verzekeren(int $sol_id): string {
    return ondertekening_zelf_verzekeren($sol_id, 'loonheffing');
}

/**
 * Aangenomen: inschrijving als lid — alleen voor wie nog geen Authentik-verenigingsaccount heeft
 * (wie dat wel heeft, is vrijwel zeker al lid). Retour ook 'overgeslagen'.
 */
function lidmaatschap_verzekeren(int $sol_id): string {
    try {
        if (function_exists('authentik_actief') && authentik_actief() && function_exists('authentik_email_bestaat')) {
            $q = db()->prepare("SELECT p.email FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE s.id=?");
            $q->execute([$sol_id]);
            $email = (string)($q->fetchColumn() ?: '');
            if ($email !== '' && authentik_email_bestaat($email)) {
                if (function_exists('audit_log')) audit_log('lidmaatschap_overgeslagen', 'sollicitatie', $sol_id, ['reden' => 'Authentik-account bestaat']);
                return 'overgeslagen';
            }
        }
    } catch (\Throwable $e) { error_log('lidmaatschap_verzekeren authentik: ' . $e->getMessage()); }
    return ondertekening_zelf_verzekeren($sol_id, 'lidmaatschap');
}

function sol_is_minderjarig(array $sol): bool {
    if (empty($sol['geboortedatum'])) return false;
    if (function_exists('leeftijd_uit_datum')) {
        $leeftijd = leeftijd_uit_datum($sol['geboortedatum']);
        return $leeftijd !== null && $leeftijd < 18;
    }
    try {
        return (new DateTime($sol['geboortedatum']))->diff(new DateTime())->y < 18;
    } catch (Exception $e) { return false; }
}

/**
 * Vangnet: zit de sollicitant in een selectiedag-fase zonder dat er ooit documenten zijn klaargezet
 * (uitgenodigd vóór 2.87.0 toen dat bij automatisch/bulk uitnodigen niet gebeurde, of de instelling
 * stond toen uit), dan alsnog klaarzetten. Idempotent: één controlequery per request; daarna doet
 * ondertekeningen_klaarzetten_sd() alleen wat nog ontbreekt (18+: taken; <18: ouder mailen zodra
 * de oudergegevens er zijn).
 */
function ondertekeningen_verzekeren_sd(int $sol_id, string $fase): void {
    static $gedaan = [];
    if (isset($gedaan[$sol_id])) return;
    $gedaan[$sol_id] = true;
    if (!in_array($fase, ['uitgenodigd_selectie', 'aangemeld_selectie'], true)) return;
    try {
        $q = db()->prepare("SELECT 1 FROM ondertekeningen WHERE sollicitatie_id=? LIMIT 1");
        $q->execute([$sol_id]);
        if ($q->fetchColumn()) return;
        ondertekeningen_klaarzetten_sd($sol_id);
    } catch (\Throwable $e) { error_log('ondertekeningen_verzekeren_sd: ' . $e->getMessage()); }
}

function ondertekeningen_klaarzetten_sd(int $sol_id): array {
    $res = ['aangemaakt' => [], 'ouder_gemaild' => false, 'wacht_op_ouder' => false];
    if (function_exists('instelling') && instelling('auto.ondertekening_sd', '1') !== '1') return $res;
    try {
        personen_ouder_auto_setup();
        $db = db();
        $q = $db->prepare("SELECT s.id, p.geboortedatum, p.email, p.voornaam, p.ouder_naam, p.ouder_email FROM sollicitaties s JOIN personen p ON p.id=s.persoon_id WHERE s.id=?");
        $q->execute([$sol_id]);
        $sol = $q->fetch();
        if (!$sol) return $res;

        if (!sol_is_minderjarig($sol)) {
            foreach (['gezondheid_18plus', 'intake'] as $type) {
                $check = $db->prepare("SELECT 1 FROM ondertekeningen WHERE sollicitatie_id=? AND type=? LIMIT 1");
                $check->execute([$sol_id, $type]);
                if ($check->fetchColumn()) continue;
                $token = bin2hex(random_bytes(24));
                $db->prepare("INSERT INTO ondertekeningen (sollicitatie_id, type, token, ondertekenaar_rol, ondertekenaar_email) VALUES (?,?,?,'sollicitant',?)")
                   ->execute([$sol_id, $type, $token, $sol['email']]);
                $res['aangemaakt'][] = $type;
            }
            return $res;
        }

        // Minderjarig
        if (empty($sol['ouder_email'])) {
            $res['wacht_op_ouder'] = true;
            return $res;
        }

        // Maak beide documenten aan; verzamel links voor één overzichtelijke mail
        $nieuwe_links = []; $nieuwe_ids = [];
        foreach (['gezondheid_ouder', 'intake_ouder'] as $type) {
            $check = $db->prepare("SELECT id, ondertekenaar_email, ondertekend_op FROM ondertekeningen
                                   WHERE sollicitatie_id=? AND type=? ORDER BY id DESC LIMIT 1");
            $check->execute([$sol_id, $type]);
            $bestaand = $check->fetch();

            if ($bestaand) {
                // Staat er al een rij, maar is het adres van de ouder intussen gecorrigeerd?
                // Dan het adres bijwerken en de link opnieuw meesturen. Eerder werd hier
                // alleen `continue` gedaan: de herinnering bleef daardoor naar het oude,
                // verkeerde adres gaan en "opnieuw versturen" deed zichtbaar niets.
                if (empty($bestaand['ondertekend_op'])
                    && strcasecmp((string)$bestaand['ondertekenaar_email'], (string)$sol['ouder_email']) !== 0) {
                    $db->prepare("UPDATE ondertekeningen SET ondertekenaar_email=?, ondertekenaar_naam=? WHERE id=?")
                       ->execute([$sol['ouder_email'], $sol['ouder_naam'] ?: null, (int)$bestaand['id']]);
                    $tq = $db->prepare("SELECT token FROM ondertekeningen WHERE id=?");
                    $tq->execute([(int)$bestaand['id']]);
                    $nieuwe_ids[] = (int)$bestaand['id'];
                    $nieuwe_links[ondertekening_label($type)] = ondertekening_url((string)$tq->fetchColumn());
                }
                continue;
            }

            $token = bin2hex(random_bytes(24));
            $db->prepare("INSERT INTO ondertekeningen (sollicitatie_id, type, token, ondertekenaar_rol, ondertekenaar_naam, ondertekenaar_email) VALUES (?,?,?,'ouder',?,?)")
               ->execute([$sol_id, $type, $token, $sol['ouder_naam'] ?: null, $sol['ouder_email']]);
            $res['aangemaakt'][] = $type;
            $nieuwe_ids[] = (int)$db->lastInsertId();
            $nieuwe_links[ondertekening_label($type)] = ondertekening_url($token);
        }

        // Eén mail met beide formulieren + volledige context voor de ouder (centrale meldingen-kern)
        if ($nieuwe_links) {
            $r = ondertekening_verzoek_versturen($sol_id, ['email' => $sol['ouder_email'], 'naam' => (string)($sol['ouder_naam'] ?? ''), 'rol' => 'ouder'],
                                                 $nieuwe_links, 'ondertekening:' . implode(',', $nieuwe_ids));
            $res['ouder_gemaild'] = in_array($r['status'] ?? '', ['verstuurd', 'wacht'], true);
        }
    } catch (\Exception $e) {
        error_log('ondertekeningen_klaarzetten_sd: ' . $e->getMessage());
    }
    return $res;
}

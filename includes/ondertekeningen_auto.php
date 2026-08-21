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
    if ($done) return;
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
        $nieuwe_links = [];
        foreach (['gezondheid_ouder' => 'Gezondheidsverklaring', 'intake_ouder' => 'Intakeformulier selectiedag'] as $type => $label) {
            $check = $db->prepare("SELECT 1 FROM ondertekeningen WHERE sollicitatie_id=? AND type=? LIMIT 1");
            $check->execute([$sol_id, $type]);
            if ($check->fetchColumn()) continue;

            $token = bin2hex(random_bytes(24));
            $db->prepare("INSERT INTO ondertekeningen (sollicitatie_id, type, token, ondertekenaar_rol, ondertekenaar_naam, ondertekenaar_email) VALUES (?,?,?,'ouder',?,?)")
               ->execute([$sol_id, $type, $token, $sol['ouder_naam'] ?: null, $sol['ouder_email']]);
            $res['aangemaakt'][] = $type;
            $nieuwe_links[$label] = APP_URL . '/portaal/ondertekenen.php?token=' . urlencode($token);
        }

        // Eén mail met beide formulieren + volledige context voor de ouder
        if ($nieuwe_links && function_exists('stuur_mail_html')) {
            // Formulieren-blok (knoppen) opbouwen — tabel-layout voor Outlook
            $knoppen = '';
            foreach ($nieuwe_links as $label => $url) {
                $knoppen .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 12px"><tr><td style="background:#f7f9fc;border:1px solid #dde5ef;border-radius:8px;padding:16px 20px">
<p style="margin:0 0 10px;font-family:Arial,sans-serif;font-size:15px;font-weight:700;color:#054d8a">' . h($label) . '</p>
<a href="' . h($url) . '" style="display:inline-block;background:#D43D00;color:#ffffff;padding:12px 24px;border-radius:6px;font-family:Arial,sans-serif;font-size:14px;font-weight:600;text-decoration:none">Openen en ondertekenen &rarr;</a>
<p style="margin:10px 0 0;font-family:Arial,sans-serif;font-size:11px;color:#8a94a3;word-break:break-all">Of kopieer: ' . h($url) . '</p>
</td></tr></table>';
            }

            $seizoen_c = function_exists('actief_seizoen') ? (actief_seizoen() ?: []) : [];
            $vars = [
                'voornaam'          => $sol['voornaam'],
                'ouder_naam'        => $sol['ouder_naam'] ?: 'ouder/verzorger',
                'formulieren_blok'  => $knoppen,
                'seizoen_naam'      => $seizoen_c['naam'] ?? '',
                'portaal_url'       => APP_URL . '/portaal/',
            ];

            $rendered = function_exists('ondertekening_mail_render') ? ondertekening_mail_render('ondertekening_ouder', $vars) : null;
            if ($rendered) {
                stuur_mail_html($sol['ouder_email'], $sol['ouder_naam'] ?: 'Ouder/verzorger', $rendered['onderwerp'], $rendered['html'], $sol_id);
            } else {
                // Fallback: simpele wrapper
                $vn = h($sol['voornaam']);
                $begroet = $sol['ouder_naam'] ? "Beste " . h($sol['ouder_naam']) . "," : "Beste ouder/verzorger,";
                $body = '<p>' . $begroet . '</p><p><strong>' . $vn . '</strong> is uitgenodigd voor de selectiedag van Reddingsbrigade Paal 13. Omdat ' . $vn . ' minderjarig is vragen wij uw handtekening op onderstaande formulieren:</p>' . $knoppen . '<p>Met vriendelijke groet,<br><strong>Reddingsbrigade Paal 13</strong></p>';
                $ond = "Handtekening nodig: formulieren voor $vn — Paal 13";
                $html = function_exists('mail_simple_wrapper') ? mail_simple_wrapper($body, $ond) : $body;
                stuur_mail_html($sol['ouder_email'], $sol['ouder_naam'] ?: 'Ouder/verzorger', $ond, $html, $sol_id);
            }
            $res['ouder_gemaild'] = true;
        }
    } catch (\Exception $e) {
        error_log('ondertekeningen_klaarzetten_sd: ' . $e->getMessage());
    }
    return $res;
}

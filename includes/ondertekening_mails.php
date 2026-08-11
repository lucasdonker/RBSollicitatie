<?php
/**
 * Paal 13 — Extra mail-templates voor ondertekeningen-flow
 * Maakt de hardcoded mails bewerkbaar via Instellingen → Mail templates:
 *  - ondertekening_ouder      : eerste mail naar ouder met beide formulieren
 *  - ondertekening_bevestiging: naar sollicitant als ouder heeft getekend
 *  - reminder_ondertekening   : herinnering open document
 *
 * Variabelen: {{voornaam}} {{ouder_naam}} {{formulieren_blok}} {{document_naam}}
 *             {{onderteken_url}} {{portaal_url}} {{seizoen_naam}}
 */

function ondertekening_templates_seed(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = db();
        $bestaand = $db->query("SELECT slug FROM mail_templates WHERE slug IN ('ondertekening_ouder','ondertekening_bevestiging','reminder_ondertekening')")->fetchAll(PDO::FETCH_COLUMN);

        $ins = $db->prepare("INSERT INTO mail_templates (slug, naam, beschrijving, onderwerp, body_html, hero_titel, hero_subtitel, hero_kleur) VALUES (?,?,?,?,?,?,?,?)");

        if (!in_array('ondertekening_ouder', $bestaand, true)) {
            $ins->execute([
                'ondertekening_ouder',
                'Ondertekening — mail naar ouder',
                'Verstuurd naar ouder/verzorger van minderjarige sollicitant met de te ondertekenen formulieren. Variabelen: {{voornaam}} (sollicitant), {{ouder_naam}}, {{formulieren_blok}} (de knoppen, automatisch), {{seizoen_naam}}.',
                'Handtekening nodig: formulieren voor {{voornaam}} — Reddingsbrigade Paal 13',
                '<p>Beste {{ouder_naam}},</p>
<p><strong>{{voornaam}}</strong> heeft gesolliciteerd als strandwacht bij Reddingsbrigade Paal 13 in Callantsoog en is uitgenodigd voor onze selectiedag. Daar maken we kennis en testen we de zwemvaardigheid in het zwembad.</p>
<p>Omdat {{voornaam}} nog geen 18 jaar is, hebben wij als organisatie uw toestemming en handtekening nodig op onderstaande documenten. Het ondertekenen gebeurt volledig digitaal en duurt ongeveer 3 minuten per formulier.</p>
{{formulieren_blok}}
<p><strong>Goed om te weten:</strong> uw gegevens worden alleen gebruikt voor deze verklaringen en vertrouwelijk behandeld. De ondertekening is 1 jaar geldig. Zonder deze formulieren kan {{voornaam}} helaas niet deelnemen aan de selectiedag.</p>
<p>Heeft u vragen? Mail ons gerust.</p>
<p>Met vriendelijke groet,<br><strong>Reddingsbrigade Paal 13</strong><br>Callantsoog</p>',
                'Handtekening gevraagd',
                'Voor de selectiedag van {{voornaam}}',
                '#054d8a',
            ]);
        }

        if (!in_array('ondertekening_bevestiging', $bestaand, true)) {
            $ins->execute([
                'ondertekening_bevestiging',
                'Ondertekening — bevestiging naar sollicitant',
                'Verstuurd naar de sollicitant zodra een ouder/verzorger een document heeft ondertekend. Variabelen: {{voornaam}}, {{document_naam}}, {{portaal_url}}.',
                'Je ouder heeft {{document_naam}} ondertekend ✓ — Paal 13',
                '<p>Hoi {{voornaam}},</p>
<p>Goed nieuws! Je ouder/verzorger heeft zojuist <strong>{{document_naam}}</strong> ondertekend. ✅</p>
<p>Je hoeft hier niets meer voor te doen. In het portaal zie je de actuele status van al je taken.</p>
[knop href="{{portaal_url}}"]Naar het portaal →[/knop]
<p>Groet,<br><strong>Reddingsbrigade Paal 13</strong></p>',
                'Ondertekend ✓',
                '{{document_naam}}',
                '#1d9e75',
            ]);
        }

        if (!in_array('reminder_ondertekening', $bestaand, true)) {
            $ins->execute([
                'reminder_ondertekening',
                'Herinnering — open ondertekening',
                'Verstuurd als een document na X dagen nog niet is ondertekend (max 1x per document). Variabelen: {{ontvanger_naam}}, {{voornaam}} (sollicitant), {{document_naam}}, {{onderteken_url}}, {{is_ouder}} (1/0).',
                'Herinnering: {{document_naam}} — Paal 13',
                '<p>Beste {{ontvanger_naam}},</p>
<p>Een vriendelijke herinnering: <strong>{{document_naam}}</strong> staat nog open. Zonder deze handtekening kan {{voornaam}} helaas niet deelnemen aan de selectiedag van Reddingsbrigade Paal 13.</p>
[knop href="{{onderteken_url}}"]Nu ondertekenen →[/knop]
<p>Met vriendelijke groet,<br><strong>Reddingsbrigade Paal 13</strong></p>',
                'Herinnering',
                '{{document_naam}}',
                '#D43D00',
            ]);
        }
    } catch (\Exception $e) {
        error_log('ondertekening_templates_seed: ' . $e->getMessage());
    }
}

/**
 * Render een ondertekening-template met fallback naar hardcoded versie.
 * Retourneert ['onderwerp' => ..., 'html' => ...] of null.
 */
function ondertekening_mail_render(string $slug, array $vars): ?array {
    ondertekening_templates_seed();
    if (function_exists('mail_template_render')) {
        $r = mail_template_render($slug, $vars);
        if ($r) {
            $html = function_exists('mail_html_wrapper')
                ? mail_html_wrapper($r['body_html'], $r['onderwerp'], $r['hero_titel'] ?? '')
                : $r['body_html'];
            return ['onderwerp' => $r['onderwerp'], 'html' => $html];
        }
    }
    return null;
}

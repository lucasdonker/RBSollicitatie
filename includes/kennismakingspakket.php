<?php
/**
 * Paal 13 — Kennismakingspakket
 * Wordt aangeroepen bij fase-overgang naar 'aangenomen'.
 * Maakt portaal-taken aan en verstuurt welkomstmail.
 */

function kennismakingspakket_activeren(int $sol_id): array {
    $resultaat = ['taken_aangemaakt' => 0, 'mail_verstuurd' => false];
    if (function_exists('instelling') && instelling('auto.kennismakingspakket', '1') !== '1') return $resultaat;
    try {
        $db = db();

        // Bepaal welke taken
        $taken = [
            [
                'slug'        => 'bevestig_lidmaatschap',
                'titel'       => 'Bevestig je lidmaatschap',
                'omschrijving'=> 'Bevestig via het portaal dat je lid wilt worden van Reddingsbrigade Paal 13.',
                'link_url'    => APP_URL . '/portaal/?taak=bevestig_lidmaatschap',
                'verplicht'   => 1,
            ],
            [
                'slug'        => 'loonheffing',
                'titel'       => 'Vul loonheffingsformulier in',
                'omschrijving'=> 'Vul en onderteken het loonheffingsformulier voor de personeelsadministratie. Bekijk het contract-emailtje voor de link.',
                'link_url'    => null,
                'verplicht'   => 1,
            ],
            [
                'slug'        => 'huisregels_gelezen',
                'titel'       => 'Lees de huisregels',
                'omschrijving'=> 'Neem de huisregels van Paal 13 door. Ze staan in het welkomstpakket dat je per mail hebt ontvangen.',
                'link_url'    => null,
                'verplicht'   => 1,
            ],
            [
                'slug'        => 'authentik_activeren',
                'titel'       => 'Activeer je Paal 13 account',
                'omschrijving'=> 'Later ontvang je een uitnodiging om je permanente Paal 13 account aan te maken (Authentik). Volg dan de instructies in die mail.',
                'link_url'    => null,
                'verplicht'   => 0,
            ],
        ];

        // Insert (ignore als al bestaan)
        $stmt = $db->prepare("INSERT IGNORE INTO sollicitant_taken (sollicitatie_id, taak_slug, titel, omschrijving, link_url, verplicht) VALUES (?,?,?,?,?,?)");
        foreach ($taken as $t) {
            if ($stmt->execute([$sol_id, $t['slug'], $t['titel'], $t['omschrijving'], $t['link_url'], $t['verplicht']])) {
                if ($stmt->rowCount() > 0) $resultaat['taken_aangemaakt']++;
            }
        }

        // Verstuur welkomstmail
        if (function_exists('verstuur_fase_mail')) {
            $seizoen = actief_seizoen() ?: [];
            $status = verstuur_fase_mail($sol_id, 'welkom_aangenomen', '%welkom%', $seizoen, 60);
            $resultaat['mail_verstuurd'] = ($status === 'verstuurd');
        }

    } catch (Exception $e) {
        error_log('kennismakingspakket: ' . $e->getMessage());
    }
    return $resultaat;
}

/**
 * Seed welkomstmail-template als hij nog niet bestaat.
 */
function welkom_template_seed(): void {
    try {
        $db = db();
        $q = $db->prepare("SELECT 1 FROM mail_templates WHERE slug='welkom_aangenomen'");
        $q->execute();
        if ($q->fetchColumn()) return;

        $body = '<p>Hoi {{voornaam}},</p>
<p><strong>Van harte welkom bij Reddingsbrigade Paal 13!</strong> We zijn blij dat je aan boord komt voor het seizoen {{seizoen_naam}}.</p>
<h3>Wat gebeurt er nu?</h3>
<p>In het portaal staan een paar taken die je nog moet afronden:</p>
<ul>
  <li>Bevestig je lidmaatschap</li>
  <li>Vul het loonheffingsformulier in</li>
  <li>Neem de huisregels door</li>
  <li>Wacht op de uitnodiging voor je Paal 13 account</li>
</ul>
<p style="text-align:center;margin:24px 0">
  <a href="{{portaal_url}}" style="background:#D43D00;color:#fff;padding:14px 28px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:600">Open het portaal &rarr;</a>
</p>
<h3>Belangrijke contactpersonen</h3>
<p>Vragen? Neem contact op met het bestuur via <a href="mailto:postcallantsoog@paal13.com">postcallantsoog@paal13.com</a>.</p>
<p>Tot snel op het strand!</p>
<p><strong>Team Sollicitaties — Paal 13</strong></p>';

        $db->prepare("INSERT INTO mail_templates (slug, naam, beschrijving, onderwerp, body_html, hero_titel, hero_subtitel, hero_kleur) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([
                'welkom_aangenomen',
                'Welkomstmail (aangenomen)',
                'Automatische mail bij fase-overgang naar aangenomen',
                'Welkom bij Paal 13, {{voornaam}}!',
                $body,
                'Welkom bij Paal 13!',
                'Aangenomen voor {{seizoen_naam}}',
                '#054d8a',
           ]);
    } catch (Exception $e) {
        error_log('welkom_template_seed: ' . $e->getMessage());
    }
}

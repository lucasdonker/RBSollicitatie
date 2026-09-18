<?php
/**
 * Paal 13 — Kennismakingspakket
 * Wordt aangeroepen door fase_zetten() bij de overgang naar 'aangenomen'.
 * Maakt de portaal-taken aan; de welkomstmail ('aangenomen') verstuurt fase_zetten() zelf.
 */

function kennismakingspakket_activeren(int $sol_id): array {
    $resultaat = ['taken_aangemaakt' => 0];
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

    } catch (Exception $e) {
        error_log('kennismakingspakket: ' . $e->getMessage());
    }
    return $resultaat;
}

/** @deprecated De welkomstmail is nu het meldingstype 'aangenomen' (includes/meldingen/registry.php). */
function welkom_template_seed(): void {
    if (function_exists('sjablonen_auto_setup')) sjablonen_auto_setup();
}

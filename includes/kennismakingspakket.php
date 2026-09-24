<?php
/**
 * Paal 13 — Aangenomen-takenpakket ("kennismakingspakket").
 *
 * Wordt aangeroepen door fase_zetten() bij de overgang naar 'aangenomen' en als vangnet vanuit
 * portaal_taken() zodat bestaande dossiers dezelfde taken krijgen. Maakt/actualiseert de rijen in
 * sollicitant_taken (idempotent) en zet de ondertekeningen klaar (loonheffing, lidmaatschap).
 * De welkomstmail ('aangenomen') verstuurt fase_zetten() zelf.
 *
 * Soorten taken:
 *   upload   → portaal/document_upload.php?taak=<slug>  (klaar zodra de documenten er zijn, includes/documenten.php)
 *   link     → externe link of bijlage; de sollicitant vinkt zelf af
 *   afvinken → geen link; de sollicitant vinkt zelf af
 * Loonheffing en lidmaatschap zijn géén taakrij: dat zijn ondertekeningen (portaal_taken() punt 2).
 */

if (!function_exists('kennismakingspakket_taken_definities')) {

/** Eén bron voor de taken van het pakket: slug => rij. */
function kennismakingspakket_taken_definities(): array {
    $taken = [];
    if (function_exists('document_taken')) {
        foreach (document_taken() as $slug => $d) {
            $taken[$slug] = ['titel' => $d['titel'], 'omschrijving' => $d['sub'], 'link_url' => null, 'verplicht' => 1,
                             'soort' => 'upload', 'document_soort' => $slug, 'volgorde' => $d['volgorde']];
        }
    }
    // Huisregels: de PDF komt uit de bijlagenbibliotheek (Instellingen › Algemeen › Huisregels). Zolang er geen
    // document is gekozen, staat de taak er wel maar zonder link ("volgt binnenkort") — zodat de sollicitant weet
    // dat dit er nog aankomt en wij niet vergeten het te plaatsen.
    $huisregels = function_exists('instelling_int') ? instelling_int('taken.huisregels_bijlage_id', 0) : 0;
    $taken['huisregels'] = $huisregels > 0
        ? ['titel' => 'Huisregels lezen', 'omschrijving' => 'Open het document en vink daarna af', 'link_url' => null, 'verplicht' => 1,
           'soort' => 'link', 'document_soort' => 'bijlage:' . $huisregels, 'volgorde' => 30]
        : ['titel' => 'Huisregels lezen', 'omschrijving' => 'Het document volgt binnenkort van het bestuur', 'link_url' => null, 'verplicht' => 1,
           'soort' => 'afvinken', 'document_soort' => null, 'volgorde' => 30];
    $taken['authentik_activeren'] = ['titel' => 'Paal 13-account activeren', 'omschrijving' => 'Je krijgt later per mail een uitnodiging voor je verenigingsaccount', 'link_url' => null, 'verplicht' => 0,
                                     'soort' => 'afvinken', 'document_soort' => null, 'volgorde' => 90];
    return $taken;
}

/**
 * Taken aanmaken/bijwerken (status blijft staan), oude slugs uit eerdere versies migreren,
 * ondertekeningen klaarzetten. Idempotent; mag bij elke aanroep opnieuw.
 */
function kennismakingspakket_activeren(int $sol_id): array {
    $resultaat = ['taken_aangemaakt' => 0, 'ondertekeningen' => []];
    if (function_exists('instelling') && instelling('auto.kennismakingspakket', '1') !== '1') return $resultaat;
    try {
        $db = db();
        if (function_exists('extra_tabellen_auto_setup')) extra_tabellen_auto_setup();
        if (function_exists('db_kolom_verzeker') && !schema_actueel()) {
            db_kolom_verzeker($db, 'sollicitant_taken', 'soort', "VARCHAR(20) NOT NULL DEFAULT 'afvinken'");
            db_kolom_verzeker($db, 'sollicitant_taken', 'document_soort', 'VARCHAR(40) DEFAULT NULL');
            db_kolom_verzeker($db, 'sollicitant_taken', 'volgorde', 'INT NOT NULL DEFAULT 50');
        }

        // Oude slugs (t/m 2.89): lidmaatschap/loonheffing worden ondertekeningen, huisregels_gelezen → huisregels
        $db->prepare("DELETE FROM sollicitant_taken WHERE sollicitatie_id=? AND taak_slug IN ('bevestig_lidmaatschap','loonheffing') AND status='open'")->execute([$sol_id]);
        $db->prepare("UPDATE IGNORE sollicitant_taken SET taak_slug='huisregels' WHERE sollicitatie_id=? AND taak_slug='huisregels_gelezen'")->execute([$sol_id]);
        // Pasfoto is vervallen (besluit Lucas, 19 sep 2026): taak weg en een al geüploade foto vernietigen —
        // niemand kan hem nog inzien, dus bewaren zou strijdig zijn met dataminimalisatie.
        $db->prepare("DELETE FROM sollicitant_taken WHERE sollicitatie_id=? AND taak_slug='pasfoto'")->execute([$sol_id]);
        if (function_exists('document_vernietigen')) {
            try {
                $pf = $db->prepare("SELECT id FROM sollicitant_documenten WHERE sollicitatie_id=? AND soort='pasfoto' AND status IN ('ingediend','verwerkt')");
                $pf->execute([$sol_id]);
                foreach ($pf->fetchAll(PDO::FETCH_COLUMN) as $doc_id) document_vernietigen((int)$doc_id, 'pasfoto vervallen');
            } catch (\Throwable $e) { error_log('pasfoto opruimen: ' . $e->getMessage()); }
        }

        $stmt = $db->prepare("INSERT INTO sollicitant_taken (sollicitatie_id, taak_slug, titel, omschrijving, link_url, verplicht, soort, document_soort, volgorde)
                              VALUES (?,?,?,?,?,?,?,?,?)
                              ON DUPLICATE KEY UPDATE titel=VALUES(titel), omschrijving=VALUES(omschrijving), link_url=VALUES(link_url),
                                                      verplicht=VALUES(verplicht), soort=VALUES(soort), document_soort=VALUES(document_soort), volgorde=VALUES(volgorde)");
        $definities = kennismakingspakket_taken_definities();
        foreach ($definities as $slug => $t) {
            $stmt->execute([$sol_id, $slug, $t['titel'], $t['omschrijving'], $t['link_url'], $t['verplicht'], $t['soort'], $t['document_soort'], $t['volgorde']]);
            if ($stmt->rowCount() === 1) $resultaat['taken_aangemaakt']++; // 1 = ingevoegd, 2 = bijgewerkt, 0 = ongewijzigd
        }
        // Taken die niet meer in de definities staan en nog open zijn: opruimen (afgeronde blijven als bewijs staan).
        $slugs = array_keys($definities);
        $db->prepare("DELETE FROM sollicitant_taken WHERE sollicitatie_id=? AND status='open' AND taak_slug NOT IN (" . implode(',', array_fill(0, count($slugs), '?')) . ")")
           ->execute(array_merge([$sol_id], $slugs));
        // Upload-taken: status gelijkzetten aan de aanwezige documenten
        if (function_exists('documenten_taak_bijwerken') && function_exists('document_taken')) {
            foreach (array_keys(document_taken()) as $slug) documenten_taak_bijwerken($sol_id, $slug);
        }
        // Ondertekeningen die bij het pakket horen (latere bundels leveren deze functies)
        foreach (['loonheffing_verzekeren', 'lidmaatschap_verzekeren'] as $fn) {
            if (function_exists($fn)) { try { $resultaat['ondertekeningen'][$fn] = $fn($sol_id); } catch (\Throwable $e) { error_log("$fn: " . $e->getMessage()); } }
        }
    } catch (\Throwable $e) {
        error_log('kennismakingspakket: ' . $e->getMessage());
    }
    return $resultaat;
}

/**
 * Vangnet vanuit portaal_taken(): dossiers in de fase 'aangenomen' die het pakket nog niet (of nog in de
 * oude vorm) hebben, krijgen het alsnog. Eén controlequery per request; alleen bij afwijking wordt
 * kennismakingspakket_activeren() aangeroepen.
 */
function kennismakingspakket_verzekeren(int $sol_id, string $fase): void {
    static $gedaan = [];
    if ($fase !== 'aangenomen' || isset($gedaan[$sol_id])) return;
    $gedaan[$sol_id] = true;
    try {
        $q = db()->prepare("SELECT taak_slug FROM sollicitant_taken WHERE sollicitatie_id=?");
        $q->execute([$sol_id]);
        $aanwezig = $q->fetchAll(PDO::FETCH_COLUMN);
        $definities = kennismakingspakket_taken_definities();
        $oud  = array_intersect($aanwezig, ['bevestig_lidmaatschap', 'loonheffing', 'huisregels_gelezen', 'pasfoto']);
        $mist = array_diff(array_keys($definities), $aanwezig);
        $weg  = array_diff($aanwezig, array_keys($definities));   // vervallen taken (bijv. pasfoto)
        if ($oud || $mist || $weg) kennismakingspakket_activeren($sol_id);
    } catch (\Throwable $e) { error_log('kennismakingspakket_verzekeren: ' . $e->getMessage()); }
}

/** Zet een taak op voltooid/open (één plek; gebruikt door uploads, afvinken en ondertekeningen). */
function taak_status_zetten(int $sol_id, string $taak_slug, bool $klaar): void {
    try {
        db()->prepare("UPDATE sollicitant_taken SET status=?, voltooid_op=IF(?, COALESCE(voltooid_op, NOW()), NULL) WHERE sollicitatie_id=? AND taak_slug=?")
            ->execute([$klaar ? 'voltooid' : 'open', $klaar ? 1 : 0, $sol_id, $taak_slug]);
    } catch (\Throwable $e) { error_log('taak_status_zetten: ' . $e->getMessage()); }
}

} // function_exists

<?php
/**
 * Paal 13 — Sollicitatie intrekken: een VERZOEK, geen knop die meteen iets doet. GEEN HTML.
 *
 * De sollicitant vraagt het in het portaal (Mijn gegevens); het bestuur krijgt bericht en ziet
 * het op Vandaag, belt de persoon, en beslist: doorvoeren (fase Afgehaakt, stil) of niet
 * (de sollicitatie loopt door). Besluit Lucas, 25 sep 2026.
 *
 * Bij het verzoek kan de sollicitant aangeven op de hoogte te willen blijven voor een volgend
 * seizoen. Dat is toestemming voor een wervingsbericht (Telecommunicatiewet art. 11.7, AVG art. 7)
 * en loopt dus via het bestaande seintje (includes/seintje.php): het vakje staat NIET vooraf aan —
 * een vooraf aangevinkt vakje is geen geldige toestemming (HvJ EU, Planet49, C-673/17).
 *
 * Los hiervan blijft "Mijn account verwijderen" (AVG art. 17) gewoon werken: dat is een recht,
 * geen verzoek.
 */

if (!function_exists('intrek_auto_setup')) {

function intrek_auto_setup(): void {
    static $done = false;
    if ($done || schema_actueel()) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `intrek_verzoek` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sollicitatie_id` INT NOT NULL,
            `reden` VARCHAR(1000) DEFAULT NULL,
            `seintje` TINYINT(1) NOT NULL DEFAULT 0,
            `status` VARCHAR(12) NOT NULL DEFAULT 'open',
            `aangevraagd_op` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `besloten_op` DATETIME DEFAULT NULL,
            `besloten_door` VARCHAR(100) DEFAULT NULL,
            `notitie` VARCHAR(500) DEFAULT NULL,
            INDEX (`sollicitatie_id`),
            INDEX (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) { error_log('intrek_auto_setup: ' . $e->getMessage()); }
}

/** In welke fases kun je intrekken? Alles wat nog loopt, ook aangenomen. */
function intrek_fases(): array {
    return ['aangemeld', 'uitgenodigd_selectie', 'aangemeld_selectie', 'selectiedag_geweest',
            'uitgenodigd_tw', 'tw_geweest', 'aangenomen'];
}

/** Het openstaande verzoek van een sollicitatie, of null. */
function intrek_open_van(int $sol_id): ?array {
    intrek_auto_setup();
    try {
        $q = db()->prepare("SELECT * FROM intrek_verzoek WHERE sollicitatie_id=? AND status='open' ORDER BY id DESC LIMIT 1");
        $q->execute([$sol_id]);
        return $q->fetch() ?: null;
    } catch (\Exception $e) { return null; }
}

/**
 * Verzoek indienen vanuit het portaal. Eén open verzoek tegelijk.
 * @return string '' = gelukt, anders de reden waarom niet (voor de sollicitant)
 */
function intrek_aanvragen(int $sol_id, string $reden, bool $seintje): string {
    try {
        $q = db()->prepare("SELECT s.fase, s.persoon_id FROM sollicitaties s WHERE s.id=?");
        $q->execute([$sol_id]);
        $s = $q->fetch();
    } catch (\Exception $e) { $s = null; }
    if (!$s || !in_array((string)$s['fase'], intrek_fases(), true)) return 'Je sollicitatie loopt niet meer; er valt niets in te trekken.';
    if (intrek_open_van($sol_id)) return 'Je verzoek staat al klaar. We nemen contact met je op.';
    if (function_exists('rate_limit') && !rate_limit('intrekken', (string)$sol_id, 3, 86400)) return 'Probeer het morgen opnieuw, of mail ons op ' . (function_exists('portaal_contact_email') ? portaal_contact_email() : 'sollicitatie@paal13.com') . '.';
    $reden = mb_substr(trim($reden), 0, 1000);
    try {
        db()->prepare("INSERT INTO intrek_verzoek (sollicitatie_id, reden, seintje) VALUES (?,?,?)")
           ->execute([$sol_id, $reden !== '' ? $reden : null, $seintje ? 1 : 0]);
    } catch (\Exception $e) { error_log('intrek_aanvragen: ' . $e->getMessage()); return 'Dat lukte niet. Probeer het later opnieuw, of mail ons op ' . (function_exists('portaal_contact_email') ? portaal_contact_email() : 'sollicitatie@paal13.com') . '.'; }
    // Toestemming voor een seintje: bewust het bestaande mechanisme (vervalt na maximaal een jaar).
    if ($seintje && function_exists('seintje_geven')) seintje_geven((int)$s['persoon_id'], 'intrekken');
    if (function_exists('audit_log')) audit_log('intrekverzoek', 'sollicitatie', $sol_id, ['seintje' => $seintje]);
    if (function_exists('melding_verstuur')) {
        melding_verstuur('bestuur_intrekverzoek', $sol_id, [
            'reden' => $reden !== '' ? $reden : '(geen reden opgegeven)',
            'beheer_url' => rtrim(APP_URL, '/') . '/admin/sollicitant.php?id=' . $sol_id,
        ], ['prioriteit' => 'na_antwoord']);
    }
    return '';
}

/** De sollicitant bedenkt zich: het open verzoek vervalt. Het seintje blijft (dat trek je apart in). */
function intrek_annuleren(int $sol_id): bool {
    intrek_auto_setup();
    try {
        $q = db()->prepare("UPDATE intrek_verzoek SET status='geannuleerd', besloten_op=NOW(), besloten_door='sollicitant'
                            WHERE sollicitatie_id=? AND status='open'");
        $q->execute([$sol_id]);
        if ($q->rowCount() > 0 && function_exists('audit_log')) audit_log('intrekverzoek_geannuleerd', 'sollicitatie', $sol_id, []);
        return $q->rowCount() > 0;
    } catch (\Exception $e) { return false; }
}

/** Alle open verzoeken van een seizoen, voor Vandaag: [['id','sol_id','naam','reden','seintje','aangevraagd_op','fase']] */
function intrek_open_lijst(int $seizoen_id): array {
    intrek_auto_setup();
    try {
        $q = db()->prepare("SELECT v.id, v.reden, v.seintje, v.aangevraagd_op, s.id AS sol_id, s.fase, p.voornaam, p.tussenvoegsel, p.achternaam
                            FROM intrek_verzoek v JOIN sollicitaties s ON s.id = v.sollicitatie_id JOIN personen p ON p.id = s.persoon_id
                            WHERE v.status='open' AND s.seizoen_id=? ORDER BY v.aangevraagd_op");
        $q->execute([$seizoen_id]);
        return array_map(fn($r) => $r + ['naam' => trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam'])],
                         $q->fetchAll());
    } catch (\Exception $e) { return []; }
}

/**
 * Het bestuur beslist na het gesprek.
 *   'doorvoeren' — fase Afgehaakt (stil: jullie hebben al gebeld), verzoek 'doorgevoerd'
 *   'afwijzen'   — de sollicitatie loopt door, verzoek 'afgewezen'; de sollicitant krijgt
 *                  bericht (meldingstype intrekverzoek_niet_doorgevoerd)
 */
function intrek_besluiten(int $verzoek_id, string $keuze, string $notitie, string $door): bool {
    if (!in_array($keuze, ['doorvoeren', 'afwijzen'], true)) return false;
    intrek_auto_setup();
    try {
        $q = db()->prepare("SELECT sollicitatie_id FROM intrek_verzoek WHERE id=? AND status='open'");
        $q->execute([$verzoek_id]);
        $sol = (int)$q->fetchColumn();
        if (!$sol) return false;
        if ($keuze === 'doorvoeren') {
            $r = function_exists('fase_zetten') ? fase_zetten($sol, 'afgehaakt', 'paneel', ['alleen_van' => intrek_fases(), 'stil' => true]) : ['ok' => false];
            if (empty($r['ok'])) return false;
        }
        db()->prepare("UPDATE intrek_verzoek SET status=?, besloten_op=NOW(), besloten_door=?, notitie=? WHERE id=?")
           ->execute([$keuze === 'doorvoeren' ? 'doorgevoerd' : 'afgewezen', mb_substr($door, 0, 100),
                      mb_substr(trim($notitie), 0, 500) ?: null, $verzoek_id]);
        if (function_exists('audit_log')) audit_log('intrekverzoek_' . ($keuze === 'doorvoeren' ? 'doorgevoerd' : 'afgewezen'), 'sollicitatie', $sol, []);
        // Niet doorvoeren: de sollicitant vroeg iets en hoort wat het besluit is (anders verdwijnt
        // het verzoek stil uit Mijn gegevens). Doorvoeren blijft stil; het portaal toont dan de eindstand.
        if ($keuze === 'afwijzen' && function_exists('melding_verstuur')) {
            melding_verstuur('intrekverzoek_niet_doorgevoerd', $sol, [], ['prioriteit' => 'na_antwoord']);
        }
        return true;
    } catch (\Exception $e) { error_log('intrek_besluiten: ' . $e->getMessage()); return false; }
}

} // function_exists

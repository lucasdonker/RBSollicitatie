<?php
/**
 * Paal 13 — open formulieren bij het inchecken (selectiedag en trainingsweekend). Geen HTML.
 *
 * Wie incheckt terwijl de gezondheidsverklaring of het intakeformulier nog niet getekend is,
 * ziet dat op de tablet en krijgt meteen de link (mail + WhatsApp/sms). Tekent een ouder, dan
 * krijgt die de link opnieuw. De organisatie ziet het in de incheck-lijst: zonder getekende
 * gezondheidsverklaring gaat niemand het water in.
 *
 * Op de tablet staat alleen de naam van het formulier, nooit de inhoud (die staat voor
 * iedereen bij de ingang).
 */

if (!function_exists('evenement_open_formulieren')) {

/** De documenttypen die vóór het water getekend moeten zijn. */
function evenement_formulier_types(): array {
    return ['gezondheid_18plus', 'intake', 'gezondheid_ouder', 'intake_ouder'];
}

/**
 * Nog niet getekende formulieren van één sollicitatie.
 * @return array [ ['id','type','titel','ouder'=>bool,'token','email','naam'] ]
 */
function evenement_open_formulieren(int $sol_id): array {
    $types = evenement_formulier_types();
    try {
        $q = db()->prepare("SELECT id, type, token, ondertekenaar_rol, ondertekenaar_email, ondertekenaar_naam FROM ondertekeningen
                            WHERE sollicitatie_id=? AND ondertekend_op IS NULL
                              AND type IN (" . implode(',', array_fill(0, count($types), '?')) . ") ORDER BY id");
        $q->execute(array_merge([$sol_id], $types));
        return array_map(fn($o) => [
            'id' => (int)$o['id'], 'type' => (string)$o['type'], 'token' => (string)$o['token'],
            'titel' => ondertekening_titel_kort((string)$o['type']),
            'ouder' => $o['ondertekenaar_rol'] === 'ouder',
            'email' => (string)($o['ondertekenaar_email'] ?? ''), 'naam' => (string)($o['ondertekenaar_naam'] ?? ''),
        ], $q->fetchAll());
    } catch (\Throwable $e) { return []; }
}

/** [sol_id => [titels]] voor de incheck-lijst van het beheer. Eén query. */
function evenement_open_formulieren_seizoen(int $seizoen_id): array {
    $types = evenement_formulier_types();
    $uit = [];
    try {
        $q = db()->prepare("SELECT o.sollicitatie_id, o.type FROM ondertekeningen o JOIN sollicitaties s ON s.id = o.sollicitatie_id
                            WHERE s.seizoen_id=? AND o.ondertekend_op IS NULL
                              AND o.type IN (" . implode(',', array_fill(0, count($types), '?')) . ")");
        $q->execute(array_merge([$seizoen_id], $types));
        foreach ($q->fetchAll() as $r) {
            if (empty($r['sollicitatie_id']) || empty($r['type'])) continue;
            $uit[(int)$r['sollicitatie_id']][] = ondertekening_titel_kort((string)$r['type']);
        }
    } catch (\Throwable $e) {}
    return array_map(fn($t) => array_values(array_unique($t)), $uit);
}

/**
 * Bij het inchecken: stuur de links voor wat nog open staat en geef terug wat de tablet
 * moet zeggen. Hooguit één keer per 12 uur per document (twee keer "Dit ben ik" = één bericht).
 *
 * @return array ['open' => [titels], 'tekst' => string]   tekst leeg = alles is getekend
 */
function evenement_formulieren_melden(int $sol_id): array {
    $open = evenement_open_formulieren($sol_id);
    if (!$open) return ['open' => [], 'tekst' => ''];
    $titels = array_values(array_unique(array_column($open, 'titel')));
    $zelf = array_filter($open, fn($o) => !$o['ouder']);
    $ouder = array_filter($open, fn($o) => $o['ouder']);
    $opties = ['prioriteit' => 'na_antwoord', 'dedup_min' => 720];

    if (function_exists('melding_verstuur')) {
        // Zelf tekenen (18+): per document een bericht met de directe link, per mail én WhatsApp/sms.
        foreach ($zelf as $o) {
            melding_verstuur('ondertekening_verzoek_zelf', $sol_id,
                ['document_naam' => $o['titel'], 'onderteken_url' => ondertekening_url($o['token'])],
                $opties + ['kanaalmodus' => 'beide', 'referentie' => 'incheck_ond:' . $o['id']]);
        }
        // Ouder tekent: de link opnieuw naar de ouder (alleen per mail — die gaat naar een ander adres).
        $ouder_links = [];
        foreach ($ouder as $o) if ($o['email'] !== '') $ouder_links[$o['titel']] = ['url' => ondertekening_url($o['token']), 'o' => $o];
        foreach ($ouder_links as $titel => $l) {
            melding_verstuur('ondertekening_verzoek_ouder', $sol_id,
                ['ouder_naam' => $l['o']['naam'] ?: 'ouder/verzorger', 'document_naam' => $titel, 'onderteken_url' => $l['url'],
                 'formulieren_blok' => ondertekening_formulieren_blok([$titel => $l['url']])],
                $opties + ['referentie' => 'incheck_ond:' . $l['o']['id'],
                           'ontvanger' => ['email' => $l['o']['email'], 'naam' => $l['o']['naam'] ?: 'ouder/verzorger', 'telefoon' => '', 'rol' => 'ouder']]);
        }
        // Ouder zonder e-mailadres: de sollicitant moet eerst de gegevens van de ouder invullen.
        if (count($ouder_links) < count($ouder)) {
            melding_verstuur('herinnering_ouder_gegevens', $sol_id, [], $opties + ['kanaalmodus' => 'beide', 'referentie' => 'incheck_ouder']);
        }
    }
    if (function_exists('audit_log')) audit_log('incheck_formulieren_open', 'sollicitatie', $sol_id, ['open' => $titels]);

    $tekst = $zelf
        ? 'We hebben je de link gestuurd (mail en WhatsApp). Vul het nu in op je telefoon, vóór je het water in gaat.'
        : 'Je ouder of verzorger moet nog tekenen. We hebben de link opnieuw gestuurd; vraag of die het nu wil doen.';
    if ($ouder && count($ouder_links ?? []) < count($ouder)) {
        $tekst = 'Je ouder of verzorger moet nog tekenen, maar we hebben geen e-mailadres. Vul dat nu in je portaal in; dan krijgt die meteen de link.';
    }
    return ['open' => $titels, 'tekst' => $tekst];
}

} // function_exists

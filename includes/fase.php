<?php
/**
 * Paal 13 — Fase-overgangen op één plek.
 *
 *   fase_zetten($sol_id, $naar, $bron, $opties)
 *
 * Zet de fase van een sollicitatie en voert de bijbehorende neveneffecten uit:
 * documenten klaarzetten, kennismakingspakket, en de melding aan de sollicitant (via de
 * centrale meldingen-kern). Alle aanroepplekken (paneel, bulk, bellijst, auto-invite, BLK)
 * gebruiken deze functie, zodat dezelfde overgang altijd hetzelfde doet.
 *
 * $opties:
 *   alleen_van  => [fases]  alleen wisselen als de huidige fase hierin zit (anders 'ongewijzigd')
 *   seizoen_id  => int      extra veiligheid: alleen binnen dit seizoen
 *   stil        => bool     geen meldingen versturen (correcties)
 *   prioriteit  => 'direct' | 'na_antwoord' | 'queue'  (standaard 'direct')
 */

if (!function_exists('fase_zetten')) {

/** Alle fases (sleutel => label). Eén bron voor validatie in beheer-schermen. */
function fasen(): array {
    return [
        'aangemeld'            => 'Aangemeld',
        'uitgenodigd_selectie' => 'Uitgenodigd selectiedag',
        'aangemeld_selectie'   => 'Aangemeld selectiedag',
        'selectiedag_geweest'  => 'Selectiedag geweest',
        'uitgenodigd_tw'       => 'Uitgenodigd trainingsweekend',
        'tw_geweest'           => 'TW geweest',
        'aangenomen'           => 'Aangenomen',
        'afgewezen'            => 'Afgewezen',
    ];
}

/**
 * Welke meldingen horen bij een overgang naar deze fase?
 *
 * Eén bron, want de bevestigingsdialoog moet vóóraf kunnen zeggen of er bericht
 * uitgaat en fase_zetten() moet het daarna daadwerkelijk sturen. Zouden die twee elk
 * hun eigen lijstje hebben, dan bevestigt het bestuur iets anders dan er gebeurt — en
 * bij een afwijsronde is dat geen cosmetisch probleem.
 *
 * @return string[] sleutels uit meldingen_register()
 */
function fase_meldingstypen(string $naar): array {
    return [
        'uitgenodigd_selectie' => ['uitnodiging_selectie'],
        'aangemeld_selectie'   => ['selectie_aangemeld_bevestiging', 'bestuur_blk_ingediend'],
        'uitgenodigd_tw'       => ['uitnodiging_tw'],
        'aangenomen'           => ['aangenomen'],
        'afgewezen'            => ['afgewezen'],
    ][$naar] ?? [];
}

/**
 * Korte labels voor plekken waar de volle naam niet past: een tabelkolom, een
 * filterchip, de fase-knoppenrij in het dossier. Bewust hier en niet per scherm —
 * anders staat er op het ene scherm "Uitgenodigd TW" en op het andere "Uitg. TW".
 *
 * @param string $lengte 'kort' (tabel, chips) of 'mini' (knoppenrij dossier)
 */
function fase_label_kort(string $fase, string $lengte = 'kort'): string {
    $kort = [
        'aangemeld'            => 'Aangemeld',
        'uitgenodigd_selectie' => 'Uitgenodigd SD',
        'aangemeld_selectie'   => 'Aangemeld SD',
        'selectiedag_geweest'  => 'SD geweest',
        'uitgenodigd_tw'       => 'Uitgenodigd TW',
        'tw_geweest'           => 'TW geweest',
        'aangenomen'           => 'Aangenomen',
        'afgewezen'            => 'Afgewezen',
    ];
    $mini = [
        'uitgenodigd_selectie' => 'Uitg. SD',
        'aangemeld_selectie'   => 'Aangem. SD',
        'uitgenodigd_tw'       => 'Uitg. TW',
    ];
    if ($lengte === 'mini' && isset($mini[$fase])) return $mini[$fase];
    return $kort[$fase] ?? fase_label($fase);
}

/**
 * @return array ['ok'=>bool,'oud'=>?string,'nieuw'=>string,'ongewijzigd'=>bool,'melding'=>?array,'neveneffecten'=>array,'fout'=>?string]
 */
function fase_zetten(int $sol_id, string $naar, string $bron, array $opties = []): array {
    $uit = ['ok' => false, 'oud' => null, 'nieuw' => $naar, 'ongewijzigd' => false, 'melding' => null, 'neveneffecten' => [], 'fout' => null];
    if (!isset(fasen()[$naar])) { $uit['fout'] = 'ongeldige fase'; return $uit; }
    try {
        $db = db();
        $q = $db->prepare("SELECT fase, seizoen_id FROM sollicitaties WHERE id=?");
        $q->execute([$sol_id]);
        $rij = $q->fetch();
        if (!$rij) { $uit['fout'] = 'sollicitatie niet gevonden'; return $uit; }
        $oud = (string)$rij['fase'];
        $uit['oud'] = $oud;

        if (!empty($opties['seizoen_id']) && (int)$rij['seizoen_id'] !== (int)$opties['seizoen_id']) { $uit['fout'] = 'ander seizoen'; return $uit; }
        if (!empty($opties['alleen_van']) && !in_array($oud, (array)$opties['alleen_van'], true)) { $uit['ok'] = true; $uit['ongewijzigd'] = true; return $uit; }
        if ($oud === $naar) { $uit['ok'] = true; $uit['ongewijzigd'] = true; return $uit; }

        $db->prepare("UPDATE sollicitaties SET fase=?, gewijzigd_op=NOW() WHERE id=?")->execute([$naar, $sol_id]);
        $uit['ok'] = true;
        if (function_exists('audit_log')) {
            try { audit_log('fase_gewijzigd', 'sollicitatie', $sol_id, ['oud' => $oud, 'nieuw' => $naar, 'bron' => $bron]); } catch (\Throwable $e) {}
        }
        // De gecachte dossier-rij is nu verouderd; meldingen laden hem opnieuw.
        if (function_exists('portaal_sollicitant_cache_wissen')) portaal_sollicitant_cache_wissen($sol_id);

        $stil  = !empty($opties['stil']);
        /* Standaard ná het antwoord versturen, niet tijdens. Een fasewijziging liet de
           beheerder anders wachten op de mailserver — soms seconden, precies op het moment
           dat je door een lijst heen werkt. De inhoudelijke uitkomsten (geen adres,
           afgemeld, dubbel, plafond) worden vóór het verzenden bepaald en blijven dus
           gewoon zichtbaar; alleen een mislukte SMTP-poging lees je nu in het logboek.
           Onder CLI valt melding_verstuur() zelf terug op 'direct'. */
        $mopt  = ['prioriteit' => $opties['prioriteit'] ?? 'na_antwoord'];
        // Zet een mens de fase om (paneel, lijst, bulk, bellijst, trainingsweekend-doorvoeren),
        // dan is dat een bewuste keuze: het anti-loop-dagplafond mag zo'n bericht niet
        // tegenhouden. Automatische bronnen wel.
        if (in_array($bron, ['paneel', 'lijst', 'bulk', 'bellijst', 'trainingsweekend'], true)) $mopt['negeer_plafond'] = true;
        $stuur = function (string $type, array $extra = []) use ($sol_id, $stil, $mopt, &$uit) {
            if ($stil || !function_exists('melding_verstuur')) return;
            $r = melding_verstuur($type, $sol_id, $extra, $mopt);
            if ($uit['melding'] === null || ($r['status'] !== 'overgeslagen' && $uit['melding']['status'] === 'overgeslagen')) $uit['melding'] = $r;
        };

        // Neveneffecten die vóór de melding moeten gebeuren (de mail verwijst ernaar).
        if ($naar === 'uitgenodigd_selectie' && function_exists('ondertekeningen_klaarzetten_sd')) {
            try { $uit['neveneffecten']['ondertekeningen'] = ondertekeningen_klaarzetten_sd($sol_id); }
            catch (\Throwable $e) { error_log('fase_zetten ondertekeningen: ' . $e->getMessage()); }
        }
        if ($naar === 'aangenomen' && function_exists('kennismakingspakket_activeren')) {
            try { $uit['neveneffecten']['kennismakingspakket'] = kennismakingspakket_activeren($sol_id); }
            catch (\Throwable $e) { error_log('fase_zetten kennismakingspakket: ' . $e->getMessage()); }
        }
        // De meldingen zelf: uit fase_meldingstypen(), dezelfde bron die de
        // bevestigingsdialoog vooraf raadpleegt.
        foreach (fase_meldingstypen($naar) as $_type) $stuur($_type);
        return $uit;
    } catch (\Throwable $e) {
        error_log('fase_zetten: ' . $e->getMessage());
        $uit['fout'] = $e->getMessage();
        return $uit;
    }
}

/**
 * Korte, uniforme tekst over de melding bij een fase-overgang (voor flash/JSON in het beheer).
 * Leeg als er niets te melden is.
 */
function fase_melding_tekst(array $res): string {
    $m = $res['melding'] ?? null;
    if (!$m) return '';
    $kanaal = ['email' => 'e-mail', 'whatsapp' => 'WhatsApp', 'sms' => 'sms'][$m['kanaal'] ?? ''] ?? '';
    return match ($m['status']) {
        'verstuurd'    => 'Bericht verstuurd' . ($kanaal ? " via $kanaal" : '') . '.',
        // 'na_antwoord' gaat direct na deze pagina de deur uit (binnen een seconde);
        // 'queue' wacht op de cron. Dat is een ander bericht aan de beheerder, dus ook
        // een andere zin — anders lijkt een snelle verzending op een ophoping.
        'wacht'        => ($res['melding']['prioriteit'] ?? '') === 'na_antwoord'
            ? 'Bericht gaat nu de deur uit' . ($kanaal ? " via $kanaal" : '') . '.'
            : 'Bericht staat in de wachtrij.',
        'mislukt'      => 'Bericht mislukt — zie het berichtenlog.',
        'overgeslagen' => match ($m['reden'] ?? '') {
            'dubbel'     => 'Bericht al eerder verstuurd (geen dubbele).',
            'geen_adres' => 'Geen e-mailadres of telefoonnummer bekend.',
            'uit'        => '',
            'plafond'    => 'Bericht niet verstuurd: dit bericht ging vandaag al meerdere keren naar deze ontvanger.',
            default      => 'Bericht overgeslagen (' . ($m['reden'] ?? '?') . ').',
        },
        default => '',
    };
}

/**
 * Meerdere sollicitaties in één keer (bulkacties). Retourneert tellers.
 */
function fase_zetten_bulk(array $ids, string $naar, string $bron, array $opties = []): array {
    $t = ['gewijzigd' => 0, 'ongewijzigd' => 0, 'verstuurd' => 0, 'overgeslagen' => 0, 'mislukt' => 0, 'fout' => 0];
    if (count($ids) > 10 && !isset($opties['prioriteit'])) $opties['prioriteit'] = 'queue';
    foreach ($ids as $id) {
        $r = fase_zetten((int)$id, $naar, $bron, $opties);
        if (!$r['ok']) { $t['fout']++; continue; }
        if ($r['ongewijzigd']) { $t['ongewijzigd']++; continue; }
        $t['gewijzigd']++;
        $s = $r['melding']['status'] ?? null;
        if ($s === 'verstuurd' || $s === 'wacht') $t['verstuurd']++;
        elseif ($s === 'mislukt') $t['mislukt']++;
        elseif ($s === 'overgeslagen' && ($r['melding']['reden'] ?? '') !== 'uit') $t['overgeslagen']++;
    }
    return $t;
}

/** Flash-tekst voor een bulkresultaat. */
function fase_bulk_tekst(array $t, string $wat = 'bericht(en)'): string {
    $delen = [$t['gewijzigd'] . ' bijgewerkt'];
    if ($t['ongewijzigd']) $delen[] = $t['ongewijzigd'] . ' al in deze fase';
    if ($t['verstuurd'])   $delen[] = $t['verstuurd'] . ' ' . $wat . ' verstuurd';
    if ($t['overgeslagen'])$delen[] = $t['overgeslagen'] . ' overgeslagen (al verstuurd of geen adres)';
    if ($t['mislukt'])     $delen[] = $t['mislukt'] . ' mislukt — zie het berichtenlog';
    if ($t['fout'])        $delen[] = $t['fout'] . ' fout';
    return implode(', ', $delen) . '.';
}

}

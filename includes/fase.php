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
        $mopt  = ['prioriteit' => $opties['prioriteit'] ?? 'direct'];
        // Zet een mens de fase om (paneel, lijst, bulk, bellijst), dan is dat een bewuste keuze:
        // het anti-loop-dagplafond mag zo'n bericht niet tegenhouden. Automatische bronnen wel.
        if (in_array($bron, ['paneel', 'lijst', 'bulk', 'bellijst'], true)) $mopt['negeer_plafond'] = true;
        $stuur = function (string $type, array $extra = []) use ($sol_id, $stil, $mopt, &$uit) {
            if ($stil || !function_exists('melding_verstuur')) return;
            $r = melding_verstuur($type, $sol_id, $extra, $mopt);
            if ($uit['melding'] === null || ($r['status'] !== 'overgeslagen' && $uit['melding']['status'] === 'overgeslagen')) $uit['melding'] = $r;
        };

        switch ($naar) {
            case 'uitgenodigd_selectie':
                if (function_exists('ondertekeningen_klaarzetten_sd')) {
                    try { $uit['neveneffecten']['ondertekeningen'] = ondertekeningen_klaarzetten_sd($sol_id); }
                    catch (\Throwable $e) { error_log('fase_zetten ondertekeningen: ' . $e->getMessage()); }
                }
                $stuur('uitnodiging_selectie');
                break;
            case 'aangemeld_selectie':
                $stuur('selectie_aangemeld_bevestiging');
                $stuur('bestuur_blk_ingediend');
                break;
            case 'uitgenodigd_tw':
                $stuur('uitnodiging_tw');
                break;
            case 'aangenomen':
                if (function_exists('kennismakingspakket_activeren')) {
                    try { $uit['neveneffecten']['kennismakingspakket'] = kennismakingspakket_activeren($sol_id); }
                    catch (\Throwable $e) { error_log('fase_zetten kennismakingspakket: ' . $e->getMessage()); }
                }
                $stuur('aangenomen');
                break;
            case 'afgewezen':
                $stuur('afgewezen');
                break;
        }
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
        'wacht'        => 'Bericht staat in de wachtrij.',
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

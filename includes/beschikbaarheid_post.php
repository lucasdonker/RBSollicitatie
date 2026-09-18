<?php
/**
 * Paal 13 — Beschikbaarheid opslaan vanuit POST.
 *
 * Deze functie verwerkt de b2_*-POST-velden van het beschikbaarheidsformulier en slaat ze op via
 * beschikbaarheid2_opslaan(). Voorheen zat deze logica verweven in de BLK-verwerking; nu is het
 * één plek, zodat zowel het losse beschikbaarheidsformulier als (indien nodig) andere aanroepers
 * dezelfde, geteste logica gebruiken.
 *
 * Retourneert een array met eventuele validatiewaarschuwingen (verzoeken met te weinig dagen).
 */

if (!function_exists('beschikbaarheid_opslaan_uit_post')) {

function beschikbaarheid_opslaan_uit_post(int $sol_id, array $post): array {
    $waarschuwingen = [];
    if (!function_exists('beschikbaarheid2_opslaan')) return $waarschuwingen;
    if (empty($post['b2_blok_van'])) return $waarschuwingen;

    // Blokken (met weekend-dagen bij regime weekend)
    $blokken = [];
    $bv = $post['b2_blok_van'] ?? []; $bt = $post['b2_blok_tot'] ?? []; $br = $post['b2_blok_regime'] ?? [];
    $bwd = $post['b2_blok_wkdagen'] ?? [];
    foreach ($bv as $i => $v) {
        if ($v && !empty($bt[$i])) {
            $blok = ['van_week' => (int)$v, 'tot_week' => (int)$bt[$i], 'regime' => $br[$i] ?? 'vijf'];
            if (($br[$i] ?? '') === 'weekend' && !empty($bwd[$i])) {
                $wd = json_decode($bwd[$i], true);
                if (is_array($wd)) $blok['weekend_dagen'] = array_map('intval', $wd);
            }
            $blokken[] = $blok;
        }
    }

    // Afwezigheid
    $afw = [];
    $av = $post['b2_afw_van'] ?? []; $at = $post['b2_afw_tot'] ?? []; $al = $post['b2_afw_label'] ?? [];
    foreach ($av as $i => $v) {
        if ($v && !empty($at[$i])) $afw[] = ['van' => $v, 'tot' => $at[$i], 'label' => $al[$i] ?? ''];
    }

    // Verzoeken afwijkend aantal dagen (met optionele dagen-pool). Het id van een bestaand
    // (beslist) verzoek gaat mee, zodat een ongewijzigd goedgekeurd verzoek niet opnieuw als
    // open verzoek wordt ingediend.
    $verzoeken = [];
    $vv = $post['b2_vz_van'] ?? []; $vt = $post['b2_vz_tot'] ?? []; $vd = $post['b2_vz_dagen'] ?? []; $vl = $post['b2_vz_toelichting'] ?? [];
    $vp = $post['b2_vz_pool'] ?? []; $vi = $post['b2_vz_id'] ?? [];
    foreach ($vv as $i => $v) {
        if ($v && !empty($vt[$i]) && !empty($vd[$i])) {
            $aantal = (int)$vd[$i];
            $verzoek = ['van_week' => (int)$v, 'tot_week' => (int)$vt[$i], 'dagen' => $aantal, 'toelichting' => $vl[$i] ?? '', 'id' => (int)($vi[$i] ?? 0)];
            $pool = [];
            if (!empty($vp[$i])) {
                $decoded = json_decode($vp[$i], true);
                if (is_array($decoded)) $pool = array_map('intval', $decoded);
            }
            if ($pool && count($pool) < $aantal) {
                $waarschuwingen[] = "Verzoek voor week {$v}: je hebt {$aantal} dagen aangevraagd maar slechts " . count($pool) . " dag(en) aangevinkt. Vink minstens {$aantal} dagen aan.";
                continue;
            }
            if ($pool) $verzoek['pool'] = $pool;
            $verzoeken[] = $verzoek;
        }
    }

    beschikbaarheid2_opslaan($sol_id, [
        'blokken'          => $blokken,
        'afwezig'          => $afw,
        'verzoeken'        => $verzoeken,
        'vaste_dagen_uit'  => $post['b2_vaste_uit'] ?? [],
        'vaste_toelichting'=> trim($post['b2_vaste_toelichting'] ?? '') ?: null,
        'voorlopig'        => !empty($post['b2_voorlopig']),
        'opmerking'        => trim($post['beschikbaarheid_uitzonderingen'] ?? '') ?: null,
    ]);

    return $waarschuwingen;
}

}

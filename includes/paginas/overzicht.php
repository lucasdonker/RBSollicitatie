<?php
/**
 * Pagina's — het overzicht over alle bronnen heen (admin/paginas.php, tabblad Overzicht). GEEN HTML.
 *
 * Eén tabel voor alles waar aanmeldingen vandaan komen: elke landingspagina, het formulier op de
 * website (WordPress-plugin) en elke campagne (?bron=… of utm_campaign, als "<bron>/<campagne>"
 * in bron_code). Twee bronnen van gegevens, bewust zo:
 *   - pagina_events (anoniem, zie statistiek.php): bezoeken, gestart, per stap, verstuurd;
 *   - sollicitaties: aanmeldingen en hoeveel daarvan zijn aangenomen. Een aanmelding telt pas als
 *     hij er echt is; de events zeggen alleen hoe mensen erheen kwamen.
 * Bezoeken, niet unieke bezoekers: het dagpseudoniem wordt na 30 dagen gewist, dus over een
 * seizoen is "uniek" niet meer te tellen. Een bezoek telt één keer per half uur per bezoeker.
 */

if (!function_exists('pag_bronnen_overzicht')) {

/** De keuzes voor de periode: sleutel => label. */
function pag_periodes(): array {
    return ['seizoen' => 'Dit seizoen', '30' => 'Laatste 30 dagen', '90' => 'Laatste 90 dagen', 'alles' => 'Alles wat bewaard is'];
}

/** Vanaf wanneer events tellen (Y-m-d H:i:s), of null = alles. Dit seizoen = sinds het seizoen is aangemaakt. */
function pag_periode_van(string $periode, ?array $seizoen): ?string {
    if ($periode === '30' || $periode === '90') return date('Y-m-d H:i:s', strtotime('-' . $periode . ' days'));
    if ($periode === 'alles') return null;
    return !empty($seizoen['aangemaakt_op']) ? (string)$seizoen['aangemaakt_op'] : null;
}

/** Een lege teller voor één bron of campagne. */
function pag_overzicht_leeg(): array {
    return ['bezoeken' => 0, 'gestart' => 0, 'stappen' => [], 'verstuurd' => 0, 'aanmeldingen' => 0, 'aangenomen' => 0];
}

/**
 * Per bron (en daarbinnen per campagne) de trechter en de uitkomst.
 *
 * @param ?string $van        vanaf dit moment (events en, zonder seizoen, aanmeldingen); null = alles
 * @param ?int    $seizoen_id alleen aanmeldingen van dit seizoen (periode "Dit seizoen"); null = op datum
 * @return array [
 *   'bronnen'  => [bron => pag_overzicht_leeg() + ['bron','label','soort' ('pagina'|'extern'|'zonder'),
 *                  'pagina_id','conversie','campagnes' => [sleutel => pag_overzicht_leeg() + ['campagne','utm','conversie']]]],
 *                  één regel per campagne én UTM-combinatie (bron, medium, variant; pag_utm_velden())
 *                  gesorteerd op aanmeldingen, dan bezoeken
 *   'totaal'   => pag_overzicht_leeg() + ['conversie'],
 *   'trechter' => pag_trechter_treden() over alles ]
 * conversie = aanmeldingen per 100 bezoeken (null zonder bezoeken).
 */
function pag_bronnen_overzicht(?string $van, ?int $seizoen_id = null): array {
    $bronnen = [];
    $totaal = pag_overzicht_leeg();
    $tel = ['bezoek' => 0, 'formulier_start' => 0, 'formulier_klaar' => 0];
    $per_stap = [];

    // Titels van de pagina's, op id en op bron (bron_code of slug), zonder query per rij.
    $pag_id = []; $pag_bron = [];
    foreach ((function_exists('pag_alle') ? pag_alle() : []) as $p) {
        $pag_id[(int)$p['id']] = $p;
        $pag_bron[(string)($p['bron_code'] ?: $p['slug'])] = $p;
        $pag_bron[(string)$p['slug']] = $pag_bron[(string)$p['slug']] ?? $p;
    }
    $rij = function (string $code) use (&$bronnen, $pag_bron): array {
        $d = explode('/', $code, 2);
        $b = $d[0] !== '' ? $d[0] : '-';
        if (!isset($bronnen[$b])) {
            $p = $pag_bron[$b] ?? null;
            $bronnen[$b] = pag_overzicht_leeg() + ['bron' => $b, 'label' => $p ? (string)$p['titel'] : '', 'pagina_id' => $p ? (int)$p['id'] : null,
                                                    'soort' => $p ? 'pagina' : ($b === '-' ? 'zonder' : 'extern'), 'campagnes' => []];
        }
        return [$b, $d[1] ?? ''];
    };
    // Een campagneregel = de campagne uit bron_code plus de losse UTM-velden van die rij.
    $utm_van = function (array $r): array {
        $u = [];
        foreach (pag_utm_velden() as $k => $_l) $u[$k] = (string)($r[$k] ?? '');
        return $u;
    };
    $bij = function (string $b, string $c, string $veld, int $n, ?int $stap = null, array $utm = []) use (&$bronnen, &$totaal) {
        $doelen = [&$bronnen[$b]];
        $sleutel = ($c !== '' || array_filter($utm)) ? $c . '|' . implode('|', $utm) : '';
        if ($sleutel !== '') {
            if (!isset($bronnen[$b]['campagnes'][$sleutel])) $bronnen[$b]['campagnes'][$sleutel] = pag_overzicht_leeg() + ['campagne' => $c, 'utm' => $utm];
            $doelen[] = &$bronnen[$b]['campagnes'][$sleutel];
        }
        $doelen[] = &$totaal;
        foreach ($doelen as &$d) {
            if ($stap !== null) $d['stappen'][$stap] = ($d['stappen'][$stap] ?? 0) + $n;
            else $d[$veld] += $n;
        }
        unset($d);
    };

    try {
        pag_auto_setup();
        $uk = implode(', ', array_keys(pag_utm_velden()));
        $q = db()->prepare("SELECT bron_code, pagina_id, type, detail, $uk, COUNT(*) AS aantal FROM pagina_events
                            WHERE type IN ('bezoek','formulier_start','formulier_stap','formulier_klaar')"
                            . ($van ? " AND moment >= ?" : "") . " GROUP BY bron_code, pagina_id, type, detail, $uk");
        $q->execute($van ? [$van] : []);
        foreach ($q->fetchAll() as $r) {
            $code = (string)($r['bron_code'] ?? '');
            // Een event zonder bron maar met pagina (oude gegevens): onder die pagina.
            if ($code === '' && !empty($r['pagina_id']) && isset($pag_id[(int)$r['pagina_id']])) {
                $p = $pag_id[(int)$r['pagina_id']];
                $code = (string)($p['bron_code'] ?: $p['slug']);
            }
            $bc = $rij($code);
            $u = $utm_van($r);
            $n = (int)$r['aantal'];
            switch ($r['type']) {
                case 'bezoek':          $bij($bc[0], $bc[1], 'bezoeken', $n, null, $u);  $tel['bezoek'] += $n; break;
                case 'formulier_start': $bij($bc[0], $bc[1], 'gestart', $n, null, $u);   $tel['formulier_start'] += $n; break;
                case 'formulier_klaar': $bij($bc[0], $bc[1], 'verstuurd', $n, null, $u); $tel['formulier_klaar'] += $n; break;
                case 'formulier_stap':
                    $s = (int)$r['detail'];
                    if ($s >= 2) { $bij($bc[0], $bc[1], '', $n, $s, $u); $per_stap[$s] = ($per_stap[$s] ?? 0) + $n; }
                    break;
            }
        }

        $w = []; $args = [];
        if ($seizoen_id) { $w[] = "seizoen_id = ?"; $args[] = $seizoen_id; }
        elseif ($van)    { $w[] = "aangemeld_op >= ?"; $args[] = $van; }
        $q = db()->prepare("SELECT COALESCE(bron_code, '') AS bron_code, $uk, MAX(bron_label) AS bron_label, COUNT(*) AS aantal,
                                   SUM(CASE WHEN fase = 'aangenomen' THEN 1 ELSE 0 END) AS aangenomen
                            FROM sollicitaties" . ($w ? " WHERE " . implode(' AND ', $w) : "") . " GROUP BY COALESCE(bron_code, ''), $uk");
        $q->execute($args);
        foreach ($q->fetchAll() as $r) {
            $bc = $rij((string)$r['bron_code']);
            $u = $utm_van($r);
            $bij($bc[0], $bc[1], 'aanmeldingen', (int)$r['aantal'], null, $u);
            $bij($bc[0], $bc[1], 'aangenomen', (int)$r['aangenomen'], null, $u);
            // Label voor een externe bron: wat de plugin meestuurde (de naam van de website), zonder campagne.
            if ($bronnen[$bc[0]]['label'] === '' && !empty($r['bron_label'])) {
                $l = (string)$r['bron_label'];
                if ($bc[1] !== '' && str_ends_with($l, ' · ' . $bc[1])) $l = substr($l, 0, -strlen(' · ' . $bc[1]));
                $bronnen[$bc[0]]['label'] = $l;
            }
        }
    } catch (\Throwable $e) { error_log('pag_bronnen_overzicht: ' . $e->getMessage()); }

    $conv = fn(array $x) => $x['bezoeken'] > 0 ? (int)round($x['aanmeldingen'] / $x['bezoeken'] * 100) : null;
    foreach ($bronnen as $b => $x) {
        if ($x['label'] === '') $bronnen[$b]['label'] = ['-' => 'Zonder bron', 'website' => 'Website', 'aanmelden' => 'Aanmeldpagina'][$b] ?? $b;
        $bronnen[$b]['conversie'] = $conv($x);
        foreach ($x['campagnes'] as $c => $cx) $bronnen[$b]['campagnes'][$c]['conversie'] = $conv($cx);
        uasort($bronnen[$b]['campagnes'], fn($p, $q) => [$q['aanmeldingen'], $q['bezoeken']] <=> [$p['aanmeldingen'], $p['bezoeken']]);
    }
    uasort($bronnen, fn($p, $q) => [$q['aanmeldingen'], $q['bezoeken']] <=> [$p['aanmeldingen'], $p['bezoeken']]);
    $totaal['conversie'] = $conv($totaal);

    return ['bronnen' => $bronnen, 'totaal' => $totaal, 'trechter' => pag_trechter_treden($tel, $per_stap, count(aanmeld_stappen_labels()))];
}

/** Waar in het formulier haakt deze bron het meest af? "Bij stap 2 (Jij)", of '' (voor de tabel). */
function pag_bron_afhaak(array $x): string {
    $stappen = count(aanmeld_stappen_labels());
    $t = pag_trechter_treden(['bezoek' => $x['bezoeken'], 'formulier_start' => $x['gestart'], 'formulier_klaar' => $x['verstuurd']],
                             $x['stappen'], $stappen);
    foreach ($t as $trede) {
        if (!$trede['grootste']) continue;
        // "Stap 2 · Jij" → "Bij stap 2 (Jij)": begonnen aan die stap, niet verder gekomen.
        return 'Bij ' . lcfirst(str_replace(' · ', ' (', $trede['label'])) . ')';
    }
    return '';
}

} // function_exists

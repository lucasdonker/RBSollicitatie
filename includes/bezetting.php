<?php
/**
 * Paal 13 — Bezetting & dekking (fase 2b)
 *
 * Beantwoordt de vraag: "Kunnen we met de beschikbare mensen deze week de posten bemannen?"
 *
 * Uitgangspunten (vastgelegd met Lucas):
 *  - Beschikbaar = inzetbaar (optimistisch op weekniveau). Wie-welke-dag komt in fase 3.
 *  - Eén pool: beide posten concurreren om dezelfde mensen (een schipper kan niet ook GK zijn).
 *  - Postcommandant valt BUITEN deze berekening (bestuur regelt dat apart).
 *  - Rollen matchen op competentie-categorie + minimum-rang (uit Authentik).
 *
 * Stoplicht per post per week:
 *   groen  = alle rollen vervuld én er is reserve
 *   oranje = alle rollen vervuld, maar geen reserve (krap)
 *   rood   = minstens één rol onvervulbaar
 */

/** Standaard bezettingssjablonen (per weektype identiek; instelbaar via Instellingen). */
function bezetting_default_sjablonen(): array {
    return [
        'hoofdpost' => [
            'naam'  => 'Hoofdpost',
            'icoon' => '🏠',
            'rollen' => [
                // sleutel => [label, categorie, min_rang, aantal]
                ['sleutel'=>'chauffeur',      'label'=>'Chauffeur',       'categorie'=>'rijden',    'min_rang'=>2, 'aantal'=>1],
                ['sleutel'=>'opstapper_auto', 'label'=>'Opstapper auto',  'categorie'=>'rijden',    'min_rang'=>1, 'aantal'=>1],
                ['sleutel'=>'derde_man',      'label'=>'Derde man/zwemmer','categorie'=>'lifeguard','min_rang'=>1, 'aantal'=>1],
                ['sleutel'=>'schipper',       'label'=>'Schipper',        'categorie'=>'varen',     'min_rang'=>2, 'aantal'=>1],
                ['sleutel'=>'opstapper_boot', 'label'=>'Opstapper boot',  'categorie'=>'varen',     'min_rang'=>1, 'aantal'=>1],
            ],
        ],
        'gk' => [
            'naam'  => 'Tweede post (GK)',
            'icoon' => '🏖️',
            'rollen' => [
                ['sleutel'=>'gk_lifeguard', 'label'=>'Lifeguard GK', 'categorie'=>'lifeguard', 'min_rang'=>2, 'aantal'=>2],
            ],
            // Extra regel: nooit 2 aspiranten samen. Door min_rang=2 (Junior) is dat
            // al afgedekt, maar we bewaken het expliciet in de check.
            'regel_geen_twee_aspiranten' => true,
        ],
    ];
}

/** Actieve sjablonen: uit instelling (JSON) of default. */
function bezetting_sjablonen(): array {
    $json = function_exists('instelling') ? instelling('bezetting.sjablonen', '') : '';
    if ($json) {
        $d = json_decode($json, true);
        if (is_array($d) && $d) return $d;
    }
    return bezetting_default_sjablonen();
}

function bezetting_sjablonen_opslaan(array $sjablonen): void {
    if (function_exists('instelling_opslaan')) {
        instelling_opslaan('bezetting.sjablonen', json_encode($sjablonen));
    }
}

/**
 * Bouw de pool van inzetbare mensen per week.
 * Retourneert: week => [ ['sol_id'=>.., 'naam'=>.., 'comp'=>[categorie=>rang], 'weekend'=>bool], ... ]
 */
function bezetting_pool(int $seizoen_id, ?array $seizoen = null): array {
    $pool = [];
    try {
        $q = db()->prepare("
            SELECT s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam
            FROM sollicitaties s
            JOIN personen p ON p.id = s.persoon_id
            WHERE s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt')
            ORDER BY p.achternaam, p.voornaam
        ");
        $q->execute([$seizoen_id]);
        $mensen = $q->fetchAll();
    } catch (\Exception $e) { return []; }

    foreach ($mensen as $m) {
        $sol_id = (int)$m['sol_id'];
        $naam = trim($m['voornaam'] . ' ' . ($m['tussenvoegsel'] ? $m['tussenvoegsel'] . ' ' : '') . $m['achternaam']);

        // Competenties: categorie => hoogste rang
        $comp = [];
        if (function_exists('competenties_van')) {
            foreach (competenties_van($sol_id) as $c) {
                $cat = $c['categorie'];
                $comp[$cat] = max($comp[$cat] ?? 0, (int)$c['rang']);
            }
        }

        // Beschikbaarheid per week
        $eff = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol_id, $seizoen) : [];
        foreach ($eff as $wk => $w) {
            $pool[$wk][] = [
                'sol_id'  => $sol_id,
                'naam'    => $naam,
                'comp'    => $comp,
                'weekend' => count(array_intersect($w['dagen'], [5,6,7])) > 0,
                'dagen'   => $w['dagen'],
            ];
        }
    }
    ksort($pool);
    return $pool;
}

/**
 * Greedy toewijzing van één post voor één week.
 * Schaarste eerst: rollen met de minste kandidaten worden als eerste vervuld.
 *
 * @param array $kandidaten  lijst uit bezetting_pool()[week], minus reeds toegewezen mensen
 * @param array $post        sjabloon van één post
 * @return array ['status'=>groen|oranje|rood, 'rollen'=>[...], 'gebruikt'=>[sol_id,..], 'tekort'=>[...]]
 */
function bezetting_wijs_toe(array $kandidaten, array $post): array {
    $rollen = $post['rollen'] ?? [];

    // Bepaal per rol wie in aanmerking komt
    $geschikt = [];
    foreach ($rollen as $i => $rol) {
        $geschikt[$i] = [];
        foreach ($kandidaten as $k) {
            $rang = $k['comp'][$rol['categorie']] ?? 0;
            if ($rang >= (int)$rol['min_rang']) $geschikt[$i][] = $k['sol_id'];
        }
    }

    // Schaarste eerst: sorteer rol-indexen op aantal kandidaten (oplopend)
    $volgorde = array_keys($rollen);
    usort($volgorde, function($a, $b) use ($geschikt, $rollen) {
        // Per benodigde plek: minste keuze eerst
        $ra = count($geschikt[$a]) / max(1, (int)$rollen[$a]['aantal']);
        $rb = count($geschikt[$b]) / max(1, (int)$rollen[$b]['aantal']);
        return $ra <=> $rb;
    });

    $per_id = [];
    foreach ($kandidaten as $k) $per_id[$k['sol_id']] = $k;

    $gebruikt = [];
    $resultaat = [];
    $tekort = [];

    foreach ($volgorde as $i) {
        $rol = $rollen[$i];
        $nodig = (int)$rol['aantal'];
        $toegewezen = [];

        // Kandidaten voor deze rol die nog vrij zijn, laagste rang eerst
        // (zo bewaren we de zwaarste mensen voor rollen die dat écht nodig hebben)
        $vrij = array_values(array_filter($geschikt[$i], fn($id) => !in_array($id, $gebruikt, true)));
        usort($vrij, function($a, $b) use ($per_id, $rol) {
            $ra = $per_id[$a]['comp'][$rol['categorie']] ?? 0;
            $rb = $per_id[$b]['comp'][$rol['categorie']] ?? 0;
            return $ra <=> $rb;
        });

        foreach ($vrij as $id) {
            if (count($toegewezen) >= $nodig) break;
            $toegewezen[] = $id;
            $gebruikt[] = $id;
        }

        $vervuld = count($toegewezen) >= $nodig;
        if (!$vervuld) {
            $tekort[] = [
                'rol'      => $rol['label'],
                'nodig'    => $nodig,
                'gevonden' => count($toegewezen),
                'categorie'=> $rol['categorie'],
                'min_rang' => (int)$rol['min_rang'],
            ];
        }

        $resultaat[$rol['sleutel']] = [
            'label'    => $rol['label'],
            'nodig'    => $nodig,
            'namen'    => array_map(fn($id) => $per_id[$id]['naam'], $toegewezen),
            'ids'      => $toegewezen,
            'vervuld'  => $vervuld,
            'reserve'  => max(0, count($geschikt[$i]) - $nodig),
        ];
    }

    // Extra regel GK: nooit twee aspiranten samen (rang 1 lifeguard)
    if (!empty($post['regel_geen_twee_aspiranten'])) {
        foreach ($resultaat as $sleutel => $r) {
            $aspiranten = 0;
            foreach ($r['ids'] as $id) {
                if (($per_id[$id]['comp']['lifeguard'] ?? 0) <= 1) $aspiranten++;
            }
            if ($aspiranten >= 2) {
                $tekort[] = ['rol' => $r['label'], 'nodig' => $r['nodig'], 'gevonden' => $r['nodig'],
                             'categorie' => 'lifeguard', 'min_rang' => 2,
                             'melding' => 'Twee aspiranten samen op GK is niet toegestaan'];
            }
        }
    }

    // Status bepalen
    if ($tekort) {
        $status = 'rood';
    } else {
        // Reserve = mensen die over zijn en minstens één rol zouden kunnen vullen
        $over = array_filter($kandidaten, fn($k) => !in_array($k['sol_id'], $gebruikt, true));
        $heeft_reserve = false;
        foreach ($over as $k) {
            foreach ($rollen as $rol) {
                if (($k['comp'][$rol['categorie']] ?? 0) >= (int)$rol['min_rang']) { $heeft_reserve = true; break 2; }
            }
        }
        $status = $heeft_reserve ? 'groen' : 'oranje';
    }

    return ['status' => $status, 'rollen' => $resultaat, 'gebruikt' => $gebruikt, 'tekort' => $tekort];
}

/**
 * Volledige dekkingsanalyse per week voor alle posten.
 * Retourneert: week => ['posten'=>[postsleutel=>resultaat], 'status'=>..., 'pool_grootte'=>n]
 */
/**
 * Dekkingsanalyse op basis van een MEEGEGEVEN pool per week.
 * Zo kan het planbord rekenen met wie er daadwerkelijk is ingepland,
 * in plaats van met iedereen die beschikbaar is.
 *
 * @param array $pool  week => [ ['sol_id'=>, 'naam'=>, 'comp'=>[cat=>rang], ...], ... ]
 */
function bezetting_dekking_van_pool(array $pool): array {
    $sjablonen = bezetting_sjablonen();
    $uit = [];

    foreach ($pool as $wk => $kandidaten) {
        $posten = [];
        $gebruikt_totaal = [];

        foreach ($sjablonen as $sleutel => $post) {
            $vrij = array_values(array_filter($kandidaten, fn($k) => !in_array($k['sol_id'], $gebruikt_totaal, true)));
            $res = bezetting_wijs_toe($vrij, $post);
            $res['naam']  = $post['naam'] ?? $sleutel;
            $res['icoon'] = $post['icoon'] ?? '•';
            $posten[$sleutel] = $res;
            $gebruikt_totaal = array_merge($gebruikt_totaal, $res['gebruikt']);
        }

        $rangorde = ['groen'=>0, 'oranje'=>1, 'rood'=>2];
        $week_status = 'groen';
        foreach ($posten as $p) {
            if ($rangorde[$p['status']] > $rangorde[$week_status]) $week_status = $p['status'];
        }

        $uit[$wk] = [
            'posten'       => $posten,
            'status'       => $week_status,
            'pool_grootte' => count($kandidaten),
            'ingezet'      => count(array_unique($gebruikt_totaal)),
        ];
    }
    ksort($uit);
    return $uit;
}

/**
 * Volledige dekkingsanalyse per week op basis van BESCHIKBAARHEID
 * (iedereen die die week kan werken).
 */
function bezetting_dekking(int $seizoen_id, ?array $seizoen = null): array {
    return bezetting_dekking_van_pool(bezetting_pool($seizoen_id, $seizoen));
}

/** Competenties van één persoon als [categorie => hoogste rang]. */
function bezetting_comp_van(int $sol_id): array {
    $comp = [];
    if (function_exists('competenties_van')) {
        foreach (competenties_van($sol_id) as $c) {
            $cat = $c['categorie'];
            $comp[$cat] = max($comp[$cat] ?? 0, (int)$c['rang']);
        }
    }
    return $comp;
}

/**
 * "Waarom rood?" — wie heeft de competentie wél, maar is die week niet beschikbaar?
 * Levert een bellijstje op.
 */
function bezetting_bellijst(int $seizoen_id, int $week, string $categorie, int $min_rang, ?array $seizoen = null): array {
    $uit = [];
    try {
        $q = db()->prepare("
            SELECT s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam, p.telefoon, p.email
            FROM sollicitaties s
            JOIN personen p ON p.id = s.persoon_id
            WHERE s.seizoen_id = ? AND s.fase NOT IN ('afgewezen','afgehaakt')
        ");
        $q->execute([$seizoen_id]);
        $mensen = $q->fetchAll();
    } catch (\Exception $e) { return []; }

    foreach ($mensen as $m) {
        $sol_id = (int)$m['sol_id'];
        // Heeft de competentie?
        $rang = 0;
        if (function_exists('competenties_van')) {
            foreach (competenties_van($sol_id) as $c) {
                if ($c['categorie'] === $categorie) $rang = max($rang, (int)$c['rang']);
            }
        }
        if ($rang < $min_rang) continue;

        // Is die week NIET beschikbaar?
        $eff = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($sol_id, $seizoen) : [];
        if (isset($eff[$week])) continue; // wel beschikbaar → niet in de bellijst

        $uit[] = [
            'sol_id'   => $sol_id,
            'naam'     => trim($m['voornaam'] . ' ' . ($m['tussenvoegsel'] ? $m['tussenvoegsel'] . ' ' : '') . $m['achternaam']),
            'telefoon' => $m['telefoon'] ?? '',
            'email'    => $m['email'] ?? '',
            'rang'     => $rang,
        ];
    }
    return $uit;
}

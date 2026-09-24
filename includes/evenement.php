<?php
/**
 * Paal 13 — Wat de selectiedag en het trainingsweekend gemeen hebben. GEEN HTML.
 *
 * Twee "evenementen" met dezelfde handelingen: inchecken (tablet of beheer), inchecken
 * afsluiten, "ik kom / ik kan niet", en de vergrendeling "bezig door X" op de bellijst en bij
 * de gesprekken. Eén laag, zodat beide modules precies hetzelfde doen.
 *
 * Evenement-sleutels: 'selectiedag' en 'tw'. De tabellen blijven gescheiden
 * (selectie_indeling / tw_deelname): twee momenten, twee sets, zie includes/trainingsweekend.php.
 *
 * De fase zet alleen fase_zetten(): inchecken → selectiedag_geweest / tw_geweest (met audit),
 * ongedaan maken → stil terug (geen dubbele uitnodiging).
 */

if (!function_exists('evenement_def')) {

/** De vaste gegevens per evenement, of null bij een onbekende sleutel. */
function evenement_def(string $ev): ?array {
    return [
        'selectiedag' => [
            'label' => 'selectiedag', 'module' => 'selectiedag', 'tabel' => 'selectie_indeling',
            'kandidaat_fases' => ['uitgenodigd_selectie', 'aangemeld_selectie', 'selectiedag_geweest'],
            'voor_fases' => ['uitgenodigd_selectie', 'aangemeld_selectie'], 'na_fase' => 'selectiedag_geweest',
            'code' => 'selectie.incheck_code', 'pin' => 'selectie.afsluit_pin',
        ],
        'tw' => [
            'label' => 'trainingsweekend', 'module' => 'trainingsweekend', 'tabel' => 'tw_deelname',
            'kandidaat_fases' => ['uitgenodigd_tw', 'tw_geweest'],
            'voor_fases' => ['uitgenodigd_tw'], 'na_fase' => 'tw_geweest',
            'code' => 'tw.incheck_code', 'pin' => 'tw.afsluit_pin',
        ],
    ][$ev] ?? null;
}

/** Tabellen van het evenement bestaan (via de setup van de module). */
function evenement_setup(string $ev): void {
    if ($ev === 'tw') { if (function_exists('tw_auto_setup')) tw_auto_setup(); }
    elseif (function_exists('selectiedag_auto_setup')) selectiedag_auto_setup();
}

/** Hoort deze sollicitatie bij dit evenement van dit seizoen? (Server-side, niet vertrouwen op de knop.) */
function evenement_is_kandidaat(string $ev, int $seizoen_id, int $sol_id): bool {
    $d = evenement_def($ev);
    if (!$d || $seizoen_id <= 0 || $sol_id <= 0) return false;
    try {
        $q = db()->prepare("SELECT 1 FROM sollicitaties WHERE id=? AND seizoen_id=?
                            AND fase IN (" . implode(',', array_fill(0, count($d['kandidaat_fases']), '?')) . ")");
        $q->execute(array_merge([$sol_id, $seizoen_id], $d['kandidaat_fases']));
        return (bool)$q->fetchColumn();
    } catch (\Exception $e) { return false; }
}

function evenement_rij_verzeker(string $ev, int $seizoen_id, int $sol_id): void {
    $d = evenement_def($ev);
    if (!$d) return;
    evenement_setup($ev);
    db()->prepare("INSERT IGNORE INTO `{$d['tabel']}` (seizoen_id, sollicitatie_id) VALUES (?,?)")->execute([$seizoen_id, $sol_id]);
}

/**
 * Inchecken of ongedaan maken. Tablet en beheer gebruiken allebei deze functie.
 * Een laatkomer na het afsluiten checkt gewoon in: de afmelding ("niet verschenen") vervalt dan.
 * Retourneert ['aanwezig' => bool, 'groep_nummer' => ?int, 'groep_naam' => ?string].
 */
function evenement_inchecken(string $ev, int $seizoen_id, int $sol_id, bool $aanwezig, string $bron = 'beheer'): array {
    $d = evenement_def($ev);
    $uit = ['aanwezig' => $aanwezig, 'groep_nummer' => null, 'groep_naam' => null];
    if (!$d) return $uit;
    evenement_rij_verzeker($ev, $seizoen_id, $sol_id);
    $db = db();
    $db->prepare("UPDATE `{$d['tabel']}` SET aanwezig=?, ingecheckt_op=" . ($aanwezig ? "IFNULL(ingecheckt_op, NOW())" : "NULL") . ",
                  afgemeld_reden=NULL WHERE seizoen_id=? AND sollicitatie_id=?")
       ->execute([$aanwezig ? 1 : 0, $seizoen_id, $sol_id]);

    if (function_exists('fase_zetten')) {
        if ($aanwezig) {
            fase_zetten($sol_id, $d['na_fase'], 'inchecken', ['seizoen_id' => $seizoen_id, 'alleen_van' => $d['voor_fases']]);
        } else {
            // Terug naar de fase van vóór het inchecken, zonder bericht (de uitnodiging ging al).
            fase_zetten($sol_id, evenement_terugfase($ev, $sol_id), 'inchecken', ['seizoen_id' => $seizoen_id, 'alleen_van' => [$d['na_fase']], 'stil' => true]);
        }
    }
    if (function_exists('audit_log')) audit_log($aanwezig ? 'ingecheckt' : 'inchecken_ongedaan', 'sollicitatie', $sol_id, ['evenement' => $ev, 'via' => $bron]);

    if ($ev === 'selectiedag') {
        try {
            $q = $db->prepare("SELECT g.nummer, g.naam FROM selectie_indeling si LEFT JOIN selectie_groep g ON g.id = si.groep_id
                               WHERE si.seizoen_id=? AND si.sollicitatie_id=?");
            $q->execute([$seizoen_id, $sol_id]);
            $r = $q->fetch() ?: [];
            $uit['groep_nummer'] = isset($r['nummer']) ? (int)$r['nummer'] : null;
            $uit['groep_naam']   = $r['naam'] ?? null;
        } catch (\Exception $e) {}
    }
    return $uit;
}

/** Welke fase had iemand vóór het inchecken? Selectiedag: met ingeleverd BLK "aangemeld", anders "uitgenodigd". */
function evenement_terugfase(string $ev, int $sol_id): string {
    if ($ev === 'tw') return 'uitgenodigd_tw';
    try {
        $q = db()->prepare("SELECT definitief_op FROM blk_formulieren WHERE sollicitatie_id=?");
        $q->execute([$sol_id]);
        return $q->fetchColumn() ? 'aangemeld_selectie' : 'uitgenodigd_selectie';
    } catch (\Exception $e) { return 'uitgenodigd_selectie'; }
}

/** Niet gekomen (met reden). Aanwezig vervalt; de fase blijft staan. */
function evenement_afmelden(string $ev, int $seizoen_id, int $sol_id, string $reden): void {
    $d = evenement_def($ev);
    if (!$d) return;
    evenement_rij_verzeker($ev, $seizoen_id, $sol_id);
    db()->prepare("UPDATE `{$d['tabel']}` SET aanwezig=0, ingecheckt_op=NULL, afgemeld_reden=? WHERE seizoen_id=? AND sollicitatie_id=?")
       ->execute([mb_substr(trim($reden), 0, 200) ?: 'Niet verschenen', $seizoen_id, $sol_id]);
}

/**
 * "Inchecken afsluiten": wie dan nog niet is ingecheckt (en niet al is afgemeld) staat op
 * "Niet verschenen". Een laatkomer kan daarna nog steeds inchecken. Retourneert het aantal.
 */
function evenement_afsluiten(string $ev, int $seizoen_id, int $door): int {
    $d = evenement_def($ev);
    if (!$d) return 0;
    evenement_setup($ev);
    $db = db();
    $ph = implode(',', array_fill(0, count($d['kandidaat_fases']), '?'));
    // Iedereen een rij, zodat ook wie nooit is ingedeeld meetelt.
    $db->prepare("INSERT IGNORE INTO `{$d['tabel']}` (seizoen_id, sollicitatie_id)
                  SELECT seizoen_id, id FROM sollicitaties WHERE seizoen_id=? AND fase IN ($ph)")
       ->execute(array_merge([$seizoen_id], $d['kandidaat_fases']));
    $u = $db->prepare("UPDATE `{$d['tabel']}` t JOIN sollicitaties s ON s.id = t.sollicitatie_id
                       SET t.afgemeld_reden='Niet verschenen'
                       WHERE t.seizoen_id=? AND t.ingecheckt_op IS NULL AND t.aanwezig=0 AND t.afgemeld_reden IS NULL
                         AND s.fase IN ($ph)");
    $u->execute(array_merge([$seizoen_id], $d['kandidaat_fases']));
    $n = $u->rowCount();
    if (function_exists('instelling_opslaan')) instelling_opslaan($ev . '.afgesloten.' . $seizoen_id, date('Y-m-d H:i:s'));
    if (function_exists('audit_log')) audit_log('inchecken_afgesloten', 'seizoen', $seizoen_id, ['evenement' => $ev, 'niet_verschenen' => $n, 'door' => $door]);
    return $n;
}

/** Wanneer is het inchecken afgesloten? null = nog open. */
function evenement_afgesloten_op(string $ev, int $seizoen_id): ?string {
    $v = function_exists('instelling') ? (string)instelling($ev . '.afgesloten.' . $seizoen_id, '') : '';
    return $v !== '' ? $v : null;
}

/** Klopt de pincode om op de tablet af te sluiten? Lege instelling = afsluiten op de tablet kan niet. */
function evenement_pin_ok(string $ev, string $pin): bool {
    $d = evenement_def($ev);
    $goed = $d && function_exists('instelling') ? (string)instelling($d['pin'], '') : '';
    return $goed !== '' && $pin !== '' && hash_equals($goed, $pin);
}

/**
 * Zoeken op de tablet: minimaal 3 tekens, hooguit 3 treffers, mét miniatuur. Nooit een lijst
 * van iedereen: de tablet staat bij de ingang, voor iedereen zichtbaar.
 */
function evenement_zoek(string $ev, int $seizoen_id, string $term): array {
    $d = evenement_def($ev);
    $term = trim($term);
    if (!$d || mb_strlen($term) < 3) return [];
    evenement_setup($ev);
    $groep = $ev === 'selectiedag'
        ? ", g.nummer AS groep_nummer, g.naam AS groep_naam FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id
             LEFT JOIN selectie_indeling t ON t.sollicitatie_id = s.id AND t.seizoen_id = s.seizoen_id
             LEFT JOIN selectie_groep g ON g.id = t.groep_id"
        : ", NULL AS groep_nummer, NULL AS groep_naam FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id
             LEFT JOIN tw_deelname t ON t.sollicitatie_id = s.id AND t.seizoen_id = s.seizoen_id";
    try {
        $q = db()->prepare("SELECT s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam, t.aanwezig, blk.foto_url $groep
                            LEFT JOIN blk_formulieren blk ON blk.sollicitatie_id = s.id
                            WHERE s.seizoen_id = ? AND s.fase IN (" . implode(',', array_fill(0, count($d['kandidaat_fases']), '?')) . ")
                              AND CONCAT_WS(' ', p.voornaam, p.tussenvoegsel, p.achternaam) LIKE ?
                            ORDER BY p.voornaam, p.achternaam LIMIT 3");
        $q->execute(array_merge([$seizoen_id], $d['kandidaat_fases'], ['%' . $term . '%']));
        return array_map(function ($r) {
            return [
                'sol_id' => (int)$r['sol_id'],
                'naam' => trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam']),
                'initialen' => mb_strtoupper(mb_substr((string)$r['voornaam'], 0, 1) . mb_substr((string)$r['achternaam'], 0, 1)),
                'aanwezig' => (int)$r['aanwezig'] === 1,
                'groep_nummer' => $r['groep_nummer'] !== null ? (int)$r['groep_nummer'] : null,
                'groep_naam' => $r['groep_naam'],
                'foto' => function_exists('foto_miniatuur_data_url') ? foto_miniatuur_data_url($r['foto_url'] ?? null) : null,
            ];
        }, $q->fetchAll());
    } catch (\Exception $e) { return []; }
}

// ─────────────────────────────────────────────────────────────
//  "Ik kom / ik kan niet"
// ─────────────────────────────────────────────────────────────

function evenement_bevestig_standen(): array {
    return ['onbekend' => 'Nog niet bekend', 'komt' => 'Komt', 'komt_niet' => 'Kan niet'];
}

/**
 * Bevestiging vastleggen. Bij "kan niet" is een reden verplicht. De fase verandert niet: het
 * bestuur beslist op Vandaag (afmelden of uitzondering). Komt de afmelding uit het portaal,
 * dan krijgt het bestuur bericht.
 *
 * @param string $bron  portaal | bellijst | beheer
 * @return bool  false bij een ongeldige stand of een ontbrekende reden
 */
function evenement_bevestigen(string $ev, int $seizoen_id, int $sol_id, string $stand, string $reden, string $bron): bool {
    $d = evenement_def($ev);
    if (!$d || !isset(evenement_bevestig_standen()[$stand])) return false;
    $reden = mb_substr(trim($reden), 0, 300);
    if ($stand === 'komt_niet' && $reden === '') return false;
    evenement_rij_verzeker($ev, $seizoen_id, $sol_id);
    db()->prepare("UPDATE `{$d['tabel']}` SET bevestiging=?, bevestiging_reden=?, bevestiging_bron=?, bevestigd_op=NOW()
                   WHERE seizoen_id=? AND sollicitatie_id=?")
       ->execute([$stand, $stand === 'komt_niet' ? $reden : null, $bron, $seizoen_id, $sol_id]);
    if (function_exists('audit_log')) audit_log('deelname_bevestigd', 'sollicitatie', $sol_id, ['evenement' => $ev, 'stand' => $stand, 'bron' => $bron]);
    if ($stand === 'komt_niet' && $bron === 'portaal' && function_exists('melding_verstuur')) {
        melding_verstuur('bestuur_deelname_afgemeld', $sol_id, [
            'evenement' => $d['label'], 'reden' => $reden,
            'beheer_url' => rtrim(APP_URL, '/') . '/admin/sollicitant.php?id=' . $sol_id,
        ], ['prioriteit' => 'na_antwoord']);
    }
    return true;
}

/** De bevestiging van één persoon: ['stand','reden','bron','op'] (stand 'onbekend' als er niets is). */
function evenement_bevestiging_van(string $ev, int $seizoen_id, int $sol_id): array {
    $d = evenement_def($ev);
    $leeg = ['stand' => 'onbekend', 'reden' => null, 'bron' => null, 'op' => null, 'uitzondering' => false];
    if (!$d) return $leeg;
    try {
        $q = db()->prepare("SELECT bevestiging, bevestiging_reden, bevestiging_bron, bevestigd_op FROM `{$d['tabel']}` WHERE seizoen_id=? AND sollicitatie_id=?");
        $q->execute([$seizoen_id, $sol_id]);
        $r = $q->fetch();
        if (!$r) return $leeg;
        return ['stand' => (string)($r['bevestiging'] ?: 'onbekend'), 'reden' => $r['bevestiging_reden'],
                'bron' => $r['bevestiging_bron'], 'op' => $r['bevestigd_op'], 'uitzondering' => $r['bevestiging_bron'] === 'uitzondering'];
    } catch (\Exception $e) { return $leeg; }
}

/**
 * Wie gaf "kan niet" door en wacht op een besluit van het bestuur (afmelden of uitzondering)?
 * Voor Vandaag › actie nodig. [ ['sol_id','naam','evenement','reden'] ]
 */
function evenement_afmeldingen_open(int $seizoen_id): array {
    $uit = [];
    foreach (['selectiedag', 'tw'] as $ev) {
        $d = evenement_def($ev);
        if (function_exists('module_aan') && !module_aan($d['module'])) continue;
        try {
            evenement_setup($ev);
            $q = db()->prepare("SELECT s.id, p.voornaam, p.tussenvoegsel, p.achternaam, t.bevestiging_reden
                                FROM `{$d['tabel']}` t JOIN sollicitaties s ON s.id = t.sollicitatie_id JOIN personen p ON p.id = s.persoon_id
                                WHERE t.seizoen_id=? AND t.bevestiging='komt_niet'
                                  AND s.fase IN (" . implode(',', array_fill(0, count($d['voor_fases']), '?')) . ")");
            $q->execute(array_merge([$seizoen_id], $d['voor_fases']));
            foreach ($q->fetchAll() as $r) {
                $uit[] = ['sol_id' => (int)$r['id'], 'evenement' => $ev, 'label' => $d['label'], 'reden' => $r['bevestiging_reden'],
                          'naam' => trim($r['voornaam'] . ' ' . ($r['tussenvoegsel'] ? $r['tussenvoegsel'] . ' ' : '') . $r['achternaam'])];
            }
        } catch (\Exception $e) {}
    }
    return $uit;
}

/**
 * Het bestuur beslist over een afmelding: 'afmelden' (fase afgehaakt, via fase_zetten) of
 * 'uitzondering' (blijft in het proces; de bevestiging gaat op "komt" met bron uitzondering).
 */
function evenement_afmelding_besluiten(string $ev, int $seizoen_id, int $sol_id, string $keuze): bool {
    if (!evenement_is_kandidaat($ev, $seizoen_id, $sol_id)) return false;
    if ($keuze === 'afmelden') {
        $d = evenement_def($ev);
        $r = function_exists('fase_zetten') ? fase_zetten($sol_id, 'afgehaakt', 'paneel', ['seizoen_id' => $seizoen_id, 'alleen_van' => $d['voor_fases'], 'stil' => true]) : ['ok' => false];
        return !empty($r['ok']);
    }
    if ($keuze === 'uitzondering') {
        $d = evenement_def($ev);
        db()->prepare("UPDATE `{$d['tabel']}` SET bevestiging='komt', bevestiging_bron='uitzondering', bevestigd_op=NOW() WHERE seizoen_id=? AND sollicitatie_id=?")
           ->execute([$seizoen_id, $sol_id]);
        if (function_exists('audit_log')) audit_log('afmelding_uitzondering', 'sollicitatie', $sol_id, ['evenement' => $ev]);
        return true;
    }
    return false;
}

// ─────────────────────────────────────────────────────────────
//  Vergrendeling "bezig door X" (bellijst en gesprekken), 30 minuten
// ─────────────────────────────────────────────────────────────

/**
 * Probeer een persoon te claimen. Lukt als niemand anders hem in de laatste 30 minuten claimde
 * (of als jij het zelf al was). Atomair: één UPDATE met de voorwaarde erin, dus twee mensen
 * die tegelijk klikken kunnen niet allebei winnen.
 *
 * @param string $soort 'bel' (kolommen bel_door/bel_sinds) of 'gesprek' (gesprek_door/gesprek_sinds)
 * @return array ['ok' => bool, 'door' => ?int (wie hem heeft), 'sinds' => ?string]
 */
function evenement_claim(string $tabel, string $soort, int $seizoen_id, int $sol_id, int $beheerder_id): array {
    if (!in_array($tabel, ['selectie_besluit', 'tw_deelname'], true) || !in_array($soort, ['bel', 'gesprek'], true)) return ['ok' => false, 'door' => null, 'sinds' => null];
    $door = $soort . '_door'; $sinds = $soort . '_sinds';
    $db = db();
    $u = $db->prepare("UPDATE `$tabel` SET `$door`=?, `$sinds`=NOW()
                       WHERE seizoen_id=? AND sollicitatie_id=?
                         AND (`$door` IS NULL OR `$door`=? OR `$sinds` < NOW() - INTERVAL 30 MINUTE)");
    $u->execute([$beheerder_id, $seizoen_id, $sol_id, $beheerder_id]);
    $q = $db->prepare("SELECT `$door` AS door, `$sinds` AS sinds FROM `$tabel` WHERE seizoen_id=? AND sollicitatie_id=?");
    $q->execute([$seizoen_id, $sol_id]);
    $r = $q->fetch() ?: [];
    $wie = isset($r['door']) ? (int)$r['door'] : null;
    return ['ok' => $wie === $beheerder_id, 'door' => $wie, 'sinds' => $r['sinds'] ?? null];
}

/** Vergrendeling loslaten (klaar, of per ongeluk geclaimd). Alleen je eigen claim. */
function evenement_claim_los(string $tabel, string $soort, int $seizoen_id, int $sol_id, int $beheerder_id): void {
    if (!in_array($tabel, ['selectie_besluit', 'tw_deelname'], true) || !in_array($soort, ['bel', 'gesprek'], true)) return;
    db()->prepare("UPDATE `$tabel` SET `{$soort}_door`=NULL, `{$soort}_sinds`=NULL WHERE seizoen_id=? AND sollicitatie_id=? AND `{$soort}_door`=?")
       ->execute([$seizoen_id, $sol_id, $beheerder_id]);
}

/** Is de claim nog geldig voor iemand anders? Voor de weergave "bezig door X". */
function evenement_claim_actief(?string $door, ?string $sinds, int $ik): bool {
    return $door !== null && (int)$door !== $ik && $sinds !== null && strtotime($sinds) > time() - 1800;
}

}

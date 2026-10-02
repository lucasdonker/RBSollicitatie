<?php
/**
 * Paal 13 — alles over één sollicitant, op één plek. GEEN HTML.
 *
 * Naar het model van includes/portaal_dossier.php: de dossierpagina, de tabbladen en
 * de api_*-endpoints halen hun gegevens hier op, zodat ze niet uit elkaar kunnen lopen.
 * Eerder stonden deze queries in api_paneel.php, verweven met de opmaak.
 *
 * Per request gecachet: een dossierpagina raakt dezelfde gegevens in meerdere secties.
 */

if (!function_exists('bh_dossier')) {

/**
 * Het complete dossier van één sollicitatie, of null als hij niet bestaat.
 *
 * @return array{sol:array,blk:?array,beoordeling:?array,beschikbaarheid:?array,
 *               ondertekeningen:array,eerder:array,leeftijd:?int,leeftijd_seizoen:?int,
 *               leeftijd_melding:?string,minderjarig:bool}|null
 */
function bh_dossier(int $sol_id): ?array {
    static $cache = [];
    if (array_key_exists($sol_id, $cache)) return $cache[$sol_id];
    if ($sol_id <= 0) return $cache[$sol_id] = null;

    try {
        $db = db();
        $q = $db->prepare("
            SELECT s.*, p.voornaam, p.tussenvoegsel, p.achternaam, p.email, p.geboortedatum,
                   p.telefoon, p.geslacht, p.id AS pid,
                   COALESCE(p.kanaal_voorkeur, 'whatsapp') AS kanaal_voorkeur,
                   sz.naam AS sz_naam, sz.datum_werk_start
            FROM sollicitaties s
            JOIN personen p  ON p.id = s.persoon_id
            JOIN seizoenen sz ON sz.id = s.seizoen_id
            WHERE s.id = ?
        ");
        $q->execute([$sol_id]);
        $sol = $q->fetch();
        if (!$sol) return $cache[$sol_id] = null;

        $een = function (string $sql) use ($db, $sol_id) {
            try { $s = $db->prepare($sql); $s->execute([$sol_id]); return $s->fetch() ?: null; }
            catch (\Throwable $e) { return null; }
        };
        $alle = function (string $sql, array $p) use ($db) {
            try { $s = $db->prepare($sql); $s->execute($p); return $s->fetchAll(); }
            catch (\Throwable $e) { return []; }
        };

        $blk   = $een("SELECT * FROM blk_formulieren WHERE sollicitatie_id=?");
        $besch = $een("SELECT * FROM beschikbaarheid WHERE sollicitatie_id=?");

        // De foto staat bij het kennismakingsformulier, niet bij de persoon. Zonder deze
        // regel kreeg bh_avatar() een rij zonder `foto_url` en viel het dossier terug op
        // initialen, terwijl de sollicitantenlijst (die b.foto_url meeselecteert) de foto
        // wél toonde. Hier neerzetten en niet in elk scherm apart: alles wat met
        // $dossier['sol'] werkt heeft de foto nu vanzelf.
        $sol['foto_url'] = $blk['foto_url'] ?? null;
        // De oudergegevens staan op de persoon en kwamen hier niet mee, waardoor het
        // tabblad Ondertekeningen bij elke minderjarige "nog geen ouder bekend" zei, ook
        // als de sollicitant ze allang had ingevuld. Via dezelfde functie als het portaal,
        // die ook een database zonder de ouderkolommen overleeft.
        if (function_exists('portaal_ouder_gegevens')) $sol += portaal_ouder_gegevens($sol_id);
        // De selectiedag-gegevens komen uit includes/selectiedag.php en niet uit de
        // oude tabel `beoordelingen`: scores staan per test/criterium/beoordelaar in
        // selectie_score, met observaties en het besluit ernaast.
        $szid    = (int)$sol['seizoen_id'];
        $scores  = function_exists('selectie_scores_van')      ? selectie_scores_van($szid, $sol_id) : [];
        $observ  = function_exists('selectie_observaties_van') ? selectie_observaties_van($szid, $sol_id) : [];
        $besluit = function_exists('selectie_besluit_van')     ? selectie_besluit_van($szid, $sol_id) : null;
        // Het trainingsweekend: deelname (bevestiging, aanwezig, gesprek, besluit) en observaties.
        $tw      = function_exists('tw_deelname_van')          ? tw_deelname_van($szid, $sol_id) : null;
        $tw_obs  = function_exists('tw_observaties_van')       ? tw_observaties_van($szid, $sol_id) : [];
        $ond   = $alle("SELECT id, type, token, ondertekend_op, ondertekenaar_naam, ondertekenaar_rol,
                               aangemaakt_op
                        FROM ondertekeningen WHERE sollicitatie_id=? ORDER BY aangemaakt_op DESC", [$sol_id]);
        $eerder = $alle("SELECT s.fase, s.aangemeld_op, sz.naam AS seizoen_naam
                         FROM sollicitaties s JOIN seizoenen sz ON sz.id = s.seizoen_id
                         WHERE s.persoon_id = ? AND s.id != ? ORDER BY s.aangemeld_op DESC",
                        [$sol['pid'], $sol_id]);
    } catch (\Throwable $e) {
        return $cache[$sol_id] = null;
    }

    $lft = function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($sol['geboortedatum']) : null;
    $peil = $sol['datum_werk_start'] ?? (date('Y') . '-06-01');
    $lft_sz = ($sol['geboortedatum'] && function_exists('leeftijd_op_datum'))
        ? leeftijd_op_datum($sol['geboortedatum'], $peil) : $lft;

    return $cache[$sol_id] = [
        'sol'             => $sol,
        'blk'             => $blk,
        // null zolang het formulier een concept is (de autosave maakt de rij al aan).
        'blk_ingediend_op' => function_exists('blk_ingediend_op') ? blk_ingediend_op($sol, $blk) : ($blk['ingevuld_op'] ?? null),
        'beschikbaarheid' => $besch,
        'scores'          => $scores,
        'observaties'     => $observ,
        'besluit'         => $besluit,
        'tw'              => $tw,
        'tw_observaties'  => $tw_obs,
        'ondertekeningen' => $ond,
        'eerder'          => $eerder,
        'leeftijd'          => $lft,
        'leeftijd_seizoen'  => $lft_sz,
        'leeftijd_melding'  => bh_leeftijd_melding($sol['geboortedatum'] ?? null, $lft, $lft_sz),
        // 18 is de ondertekeningsgrens: onder de 18 tekent een ouder mee. Dat is een
        // andere grens dan de 15/16 hierboven (aanmeldleeftijd en AVG) — nooit samenvoegen.
        'minderjarig'       => $lft !== null && $lft < 18,
    ];
}

/**
 * Waarschuwing rond de 16-grens, in gewone taal.
 *
 * 16 is de minimum-aanmeldleeftijd en de AVG-grens voor gegevensverwerking. Wordt
 * iemand pas ná de start van het seizoen 16, dan moet het bestuur dat zien.
 */
function bh_leeftijd_melding(?string $geboortedatum, ?int $nu, ?int $bij_start): ?string {
    if (!$geboortedatum || $nu === null) return null;
    try {
        $geb     = new DateTime($geboortedatum);
        $zestien = (clone $geb)->modify('+16 years');
        $vandaag = new DateTime('today');
        if ($nu < 16 && $zestien > $vandaag) {
            return 'Wordt pas 16 op ' . $zestien->format('j-n-Y');
        }
        if ($nu >= 16 && $bij_start !== null && $bij_start < 16) {
            return 'Was 15 bij de start van het seizoen, inmiddels 16';
        }
    } catch (\Exception $e) {}
    return null;
}

/**
 * De documenten die bij deze sollicitant horen, met hun stand.
 *
 * Wat er staat, volgt wat includes/ondertekeningen_auto.php en kennismakingspakket.php
 * werkelijk klaarzetten — niet een vaste lijst. Eerder stond hier voor iedereen
 * loonheffing en lidmaatschap (die komen pas bij aangenomen), en bij een minderjarige
 * een "intake sollicitant" die het systeem nooit aanmaakt. Een dossier kwam daardoor
 * nooit op compleet, ook als de sollicitant alles had gedaan.
 *
 *   - alles wat er in `ondertekeningen` staat, telt mee;
 *   - vanaf de selectiedag-uitnodiging worden de selectiedag-documenten verwacht
 *     (onder de 18 tekent de ouder: gezondheid_ouder + intake_ouder);
 *   - bij aangenomen het loonheffingsformulier. Lidmaatschap alleen als het er staat:
 *     wie al een verenigingsaccount heeft, krijgt het bewust niet.
 *
 * @return array{gedaan:int,totaal:int,rijen:array}
 */
function bh_dossier_documenten(array $dossier): array {
    $stand = [];
    foreach ($dossier['ondertekeningen'] as $o) {
        $t = (string)$o['type'];
        // Meest recente wint; de query sorteert al aflopend.
        if (!isset($stand[$t])) $stand[$t] = $o;
    }

    $fase = (string)($dossier['sol']['fase'] ?? '');
    $volgorde = ['gezondheid_18plus', 'intake', 'gezondheid_ouder', 'intake_ouder', 'loonheffing', 'lidmaatschap'];
    $verwacht = [];
    $sd_fases = ['uitgenodigd_selectie', 'aangemeld_selectie', 'selectiedag_geweest', 'uitgenodigd_tw', 'tw_geweest', 'aangenomen'];
    $sd_types = ['gezondheid_18plus', 'intake', 'gezondheid_ouder', 'intake_ouder'];
    // Staat er al een selectiedag-document, dan is de keuze ouder/zelf al gemaakt:
    // niet ook de andere set verwachten (iemand kan intussen 18 zijn geworden).
    if (in_array($fase, $sd_fases, true) && !array_intersect($sd_types, array_keys($stand))) {
        $verwacht = $dossier['minderjarig'] ? ['gezondheid_ouder', 'intake_ouder'] : ['gezondheid_18plus', 'intake'];
    }
    if ($fase === 'aangenomen') $verwacht[] = 'loonheffing';

    $soorten = array_values(array_unique(array_merge(array_keys($stand), $verwacht)));
    usort($soorten, function ($a, $b) use ($volgorde) {
        $ia = array_search($a, $volgorde, true); $ib = array_search($b, $volgorde, true);
        return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
    });

    $rijen = [];
    $gedaan = 0;
    foreach ($soorten as $type) {
        $o = $stand[$type] ?? null;
        $klaar = $o && !empty($o['ondertekend_op']);
        if ($klaar) $gedaan++;
        $label = function_exists('ondertekening_label') ? ondertekening_label($type) : $type;
        $rijen[] = [
            'type' => $type, 'label' => $label, 'klaar' => $klaar,
            'rol' => $o['ondertekenaar_rol'] ?? null,
            'op' => $o['ondertekend_op'] ?? null, 'door' => $o['ondertekenaar_naam'] ?? null,
            'token' => $o['token'] ?? null, 'id' => $o['id'] ?? null,
            // Gezondheidsverklaringen bevatten medische gegevens: alleen bestuur en hoger.
            'medisch' => str_starts_with($type, 'gezondheid'),
        ];
    }
    return ['gedaan' => $gedaan, 'totaal' => count($rijen), 'rijen' => $rijen];
}

/**
 * De taken zoals de sollicitant ze in het portaal ziet — uit dezelfde bron
 * (portaal_taken), zodat beheer en portaal nooit iets anders zeggen over wat af is.
 * Eerder kende het beheer alleen de ondertekeningen; het BLK, de beschikbaarheid, de
 * oudergegevens en het hele takenpakket (uploads, huisregels) waren er onzichtbaar.
 *
 * Zonder vangnet: een dossier bekijken mag niets klaarzetten of versturen.
 *
 * @return array{gedaan:int,totaal:int,rijen:array}  rijen: titel, sub, done, wacht,
 *         urgent, token (alleen bij een open ondertekening, voor "Link kopiëren")
 */
function bh_dossier_taken(array $dossier): array {
    $leeg = ['gedaan' => 0, 'totaal' => 0, 'rijen' => []];
    if (!function_exists('portaal_taken')) return $leeg;
    $sol_id = (int)$dossier['sol']['id'];
    try {
        // Het portaal-formaat heeft o.a. de selectiedagdatum nodig voor de subregels.
        $sol = (function_exists('portaal_sollicitant_laden') ? portaal_sollicitant_laden($sol_id) : null) ?: $dossier['sol'];
        $taken = portaal_taken($sol, $dossier['blk'], false);
    } catch (\Throwable $e) { return $leeg; }

    $tokens = [];
    foreach ($dossier['ondertekeningen'] as $o) {
        if (empty($o['ondertekend_op'])) $tokens[(int)$o['id']] = (string)$o['token'];
    }

    $rijen = [];
    $gedaan = 0;
    foreach ($taken as $t) {
        if (!empty($t['done'])) $gedaan++;
        $token = null;
        if (preg_match('/^(ond|oud)_(\d+)$/', (string)$t['id'], $m)) $token = $tokens[(int)$m[2]] ?? null;
        $rijen[] = [
            'titel'  => (string)$t['titel'],
            'sub'    => (string)$t['sub'],
            'done'   => !empty($t['done']),
            'wacht'  => !empty($t['wacht']),
            'urgent' => !empty($t['urgent']),
            'token'  => $token,
        ];
    }
    return ['gedaan' => $gedaan, 'totaal' => count($rijen), 'rijen' => $rijen];
}

/**
 * Wat er nu speelt bij deze sollicitant — bovenaan het dossier, voor het dagelijks gebruik: één zin
 * over waar iemand staat, en waar het op wacht. De rest van het dossier staat ingeklapt.
 * De hoofdactie (de stap vooruit) staat in de kop (bh_fase_actie); die herhalen we hier niet.
 *
 * @param array $taken  uitkomst van bh_dossier_taken()
 * @return array{zin:string, wacht:array<int,array{tekst:string,kleur:string}>, meer:int, herinner:bool}
 */
function bh_dossier_nu(array $d, array $taken): array {
    $s    = $d['sol'];
    $id   = (int)$s['id'];
    $szid = (int)$s['seizoen_id'];
    $fase = (string)$s['fase'];
    $sz   = function_exists('actief_seizoen') ? actief_seizoen() : null;
    $sz   = ($sz && (int)$sz['id'] === $szid) ? $sz : [];
    $sd   = !empty($sz['datum_selectiedag']) ? ' van ' . datum_nl($sz['datum_selectiedag']) : '';
    $komt = function (string $ev) use ($szid, $id): string {
        if (!function_exists('evenement_bevestiging_van')) return '';
        $b = evenement_bevestiging_van($ev, $szid, $id);
        return ['komt' => ' Komt.', 'komt_niet' => ' Kan niet' . (!empty($b['reden']) ? ': ' . $b['reden'] : '') . '.'][$b['stand']]
            ?? ' Nog niet laten weten of hij of zij komt.';
    };
    $open = array_values(array_filter($taken['rijen'], fn($r) => !$r['done']));
    $sd_besluit = (string)($d['besluit']['besluit'] ?? '');
    $tw_besluit = (string)($d['tw']['besluit'] ?? '');

    $zin = match ($fase) {
        'aangemeld'            => empty($d['blk_ingediend_op'])
                                    ? 'Nieuwe aanmelding; het kennismakingsformulier is nog niet ingestuurd.'
                                    : 'Nieuwe aanmelding met ingestuurd formulier. Beoordeel en nodig uit voor de selectiedag.',
        'uitgenodigd_selectie' => 'Uitgenodigd voor de selectiedag' . $sd . '.' . $komt('selectiedag'),
        'aangemeld_selectie'   => 'Aangemeld voor de selectiedag' . $sd . ': komt.'
                                  . (empty($d['blk_ingediend_op']) ? ' Het kennismakingsformulier is nog niet ingeleverd.' : ''),
        'selectiedag_geweest'  => 'Selectiedag geweest. ' . match ($sd_besluit) {
                                    'door'    => 'Besluit: door. Bellen en uitnodigen voor het trainingsweekend gaat via de bellijst.',
                                    'twijfel' => 'Besluit: twijfel. Bellen via de bellijst; daar wordt het door of niet.',
                                    'niet'    => 'Besluit: niet door. Bellen en doorvoeren via de bellijst.',
                                    default   => 'Nog geen besluit in de nabespreking.',
                                  },
        'uitgenodigd_tw'       => 'Uitgenodigd voor het trainingsweekend.' . $komt('tw'),
        'tw_geweest'           => 'Trainingsweekend geweest. ' . ($tw_besluit === 'twijfel'
                                    ? 'Twijfel na het gesprek: nabellen en dan besluiten.'
                                    : 'Het gesprek en het besluit volgen bij Trainingsweekend › Gesprekken.'),
        'aangenomen'           => $open ? 'Aangenomen. Nog ' . count($open) . ' ' . (count($open) === 1 ? 'taak' : 'taken') . ' open.'
                                        : 'Aangenomen, en alles is rond.',
        'afgewezen'            => 'Afgewezen.',
        'afgehaakt'            => 'Afgehaakt.',
        default                => fase_label($fase) . '.',
    };

    $wacht = [];
    foreach ($open as $r) {
        $wacht[] = ['tekst' => $r['titel'] . ($r['wacht'] ? ' (wacht op ouder)' : ''), 'kleur' => $r['wacht'] ? 'paars' : 'oranje'];
    }
    if (empty($s['email']) && empty($s['telefoon'])) {
        array_unshift($wacht, ['tekst' => 'Geen e-mail en geen telefoonnummer', 'kleur' => 'rood']);
    }
    $max = 6;
    return [
        'zin'      => $zin,
        'wacht'    => array_slice($wacht, 0, $max),
        'meer'     => max(0, count($wacht) - $max),
        // Afgewezen of afgehaakt: niemand herinneren.
        'herinner' => (bool)$open && !in_array($fase, ['afgewezen', 'afgehaakt'], true),
    ];
}

/**
 * Wie mag een ondertekend document daadwerkelijk ópenen?
 *
 * Dit stond op twee plekken met twee antwoorden: het dossiertabblad liet een
 * gezondheidsverklaring alleen aan bestuur en hoger zien, het overzichtsscherm aan
 * iedereen met leesrecht op ondertekeningen. Eén regel, hier.
 *
 *   gezondheid*  medische gegevens  -> bestuur en hoger
 *   loonheffing  BSN                -> alleen wie de kluis mag openen (mag_kluis(): de penningmeester,
 *                                      niet vanzelf de admin)
 *   lidmaatschap IBAN               -> idem
 *   intake*      gewone afspraken   -> iedereen met leesrecht op ondertekeningen
 *
 * @return array [bool $mag, ?string $reden]  De reden is bedoeld om te tónen: "je mag
 *               dit niet" hoort te zeggen wie het wel mag (ontwerpfilosofie §9).
 */
function bh_mag_document(string $type): array {
    if (str_starts_with($type, 'gezondheid')) {
        // BEWUST het modulerecht en niet de rolrang. De penningmeester heeft rang 2,
        // net als het bestuur, maar rechten.php geeft hem expliciet alleen de
        // loonadministratie — met een rangvergelijking kon hij medische gegevens openen.
        // Rang is een hiërarchie; penningmeester staat náást bestuur, niet erboven.
        $mag = mag('ondertekeningen', 'lezen');
        return [$mag, $mag ? null : 'Alleen ondertekeningen-recht'];
    }
    if (in_array($type, ['loonheffing', 'lidmaatschap'], true)) {
        $mag = mag_kluis();
        return [$mag, $mag ? null : 'Alleen de penningmeester'];
    }
    $mag = mag('ondertekeningen', 'lezen');
    return [$mag, $mag ? null : 'Alleen ondertekeningen-recht'];
}

/**
 * Waar de PDF van een ondertekening te openen is. Met BSN of IBAN (loonheffing, lidmaatschap) via de
 * gelogde kluisdownload, anders via Ondertekeningen. Recht eerst checken met bh_mag_document().
 */
function bh_ondertekening_pdf_url(int $id, string $type): string {
    if (in_array($type, ['loonheffing', 'lidmaatschap'], true)) {
        return APP_URL . '/admin/document_download.php?ond=' . $id . '&t=' . urlencode(csrf_token());
    }
    return APP_URL . '/admin/ondertekeningen.php?export=' . $id;
}

/**
 * Het gemiddelde over alle criteria van alle tests, of null als er niets is beoordeeld.
 * De scores staan per test/criterium/beoordelaar; selectie_scores_van() heeft het
 * gemiddelde per criterium al berekend.
 */
function bh_dossier_score(array $dossier): ?float {
    $waarden = [];
    foreach (($dossier['scores'] ?? []) as $criteria) {
        foreach ($criteria as $c) {
            if (!empty($c['gem'])) $waarden[] = (float)$c['gem'];
        }
    }
    if (!$waarden) return null;
    return round(array_sum($waarden) / count($waarden), 1);
}

/** Het gemiddelde per test ("zwemtest 4,1 · sporttest 4,3"). */
function bh_dossier_score_per_test(array $dossier): array {
    $uit = [];
    foreach (($dossier['scores'] ?? []) as $test => $criteria) {
        $w = [];
        foreach ($criteria as $c) if (!empty($c['gem'])) $w[] = (float)$c['gem'];
        if ($w) $uit[$test] = round(array_sum($w) / count($w), 1);
    }
    return $uit;
}

/**
 * Het advies over deze kandidaat: door, twijfel of niet.
 *
 * Het systeem doet een voorstel, nooit een besluit (ontwerpfilosofie §3). Daarom geeft
 * deze functie ook de onderbouwing terug — zonder reden is een advies een orakel.
 *
 * @return array{stand:string,label:string,kleur:string,reden:string}|null
 */
function bh_dossier_advies(array $dossier): ?array {
    return bh_advies_bij_score(bh_dossier_score($dossier));
}

/** De drempel van de selectiedag (instelling selectie.drempel); onder drempel − 0,5 is het "niet door". */
function bh_advies_drempel(): float {
    return function_exists('instelling') ? (float)instelling('selectie.drempel', '3.5') : 3.5;
}

/**
 * Het advies bij een gemiddeld cijfer — één regel voor dossier, samenvatting en nabespreking.
 * @return array{stand:string,label:string,kleur:string,reden:string}|null  null zonder cijfer
 */
function bh_advies_bij_score(?float $score): ?array {
    if ($score === null) return null;

    $drempel = bh_advies_drempel();
    $twijfel = $drempel - 0.5;

    if ($score >= $drempel) {
        $stand = 'door';  $label = 'Door';    $kleur = 'groen';
        $reden = 'Score ' . cijfer_nl((float)$score) . ' ligt boven de drempel van '
               . cijfer_nl((float)$drempel) . '.';
    } elseif ($score >= $twijfel) {
        $stand = 'twijfel'; $label = 'Twijfel'; $kleur = 'oranje';
        $reden = 'Score ' . cijfer_nl((float)$score) . ' ligt net onder de drempel van '
               . cijfer_nl((float)$drempel) . '. Bespreek deze sollicitant.';
    } else {
        $stand = 'niet'; $label = 'Niet door'; $kleur = 'rood';
        $reden = 'Score ' . cijfer_nl((float)$score) . ' ligt onder de drempel van '
               . cijfer_nl((float)$drempel) . '.';
    }
    return ['stand' => $stand, 'label' => $label, 'kleur' => $kleur, 'reden' => $reden];
}

/**
 * De tabbladen van het dossier, met hun aantallen.
 * Een tab die niets te tonen heeft, verdwijnt — behalve Dossier, dat is de basis.
 */
function bh_dossier_tabs(array $dossier, string $actief = 'dossier'): array {
    $doc = bh_dossier_documenten($dossier);
    $mag_blk = !function_exists('mag') || mag('blk', 'lezen');

    $tabs = [['sleutel' => 'dossier', 'label' => 'Dossier', 'aantal' => null]];
    if ($dossier['blk'] && $mag_blk) {
        $tabs[] = ['sleutel' => 'formulier', 'label' => 'Kennismakingsformulier', 'aantal' => null];
    }
    // Beschikbaarheid & inzet (2.122.0: één tab, opgave en afspraak per week onder elkaar).
    // Ook tonen als de oude beschikbaarheid-rij ontbreekt maar er wél effectieve weken zijn
    // (beschikbaarheid2), of als er alleen een afspraak is. Altijd met het seizoen van déze sollicitatie.
    $_sz_sol = function_exists('seizoen_op_id') ? seizoen_op_id((int)$dossier['sol']['seizoen_id']) : null;
    $_heeft_besch = $dossier['beschikbaarheid']
        || (function_exists('beschikbaarheid2_effectief')
            && beschikbaarheid2_effectief((int)$dossier['sol']['id'], $_sz_sol))
        || (function_exists('inzet_afspraak_van') && (!function_exists('mag') || mag('loonplanning', 'lezen'))
            && inzet_afspraak_van((int)$dossier['sol']['id']));
    if ($_heeft_besch) {
        $tabs[] = ['sleutel' => 'beschikbaarheid', 'label' => 'Beschikbaarheid & inzet', 'aantal' => null];
    }
    // Observaties en scores alleen voor wie de module mag lezen (dataminimalisatie: de penningmeester
    // leest dossiers, maar heeft niets te maken met hoe iemand op de selectiedag of het weekend deed).
    // mag() geeft ook false als de module uit staat. De tabbladenlijst is ook het slot op ?tab=.
    $_mag_mod = fn(string $m) => function_exists('mag') ? mag($m, 'lezen') : (!function_exists('module_aan') || module_aan($m));
    // Ook met alleen een besluit (en wat er op de bellijst is verteld) hoort de tab er te staan.
    if (($dossier['scores'] || $dossier['observaties'] || !empty($dossier['besluit']['besluit'])) && $_mag_mod('selectiedag')) {
        $tabs[] = ['sleutel' => 'selectiedag', 'label' => 'Selectiedag',
                   'aantal' => $dossier['observaties'] ? count($dossier['observaties']) : null];
    }
    if ((!empty($dossier['tw']) || !empty($dossier['tw_observaties'])) && $_mag_mod('trainingsweekend')) {
        $tabs[] = ['sleutel' => 'trainingsweekend', 'label' => 'Trainingsweekend',
                   'aantal' => $dossier['tw_observaties'] ? count($dossier['tw_observaties']) : null];
    }
    if ($doc['totaal'] > 0) {
        $tabs[] = ['sleutel' => 'ondertekeningen', 'label' => 'Ondertekeningen',
                   'aantal' => $doc['gedaan'] . '/' . $doc['totaal']];
    }
    $tabs[] = ['sleutel' => 'tijdlijn', 'label' => 'Tijdlijn', 'aantal' => null];

    foreach ($tabs as &$t) $t['actief'] = $t['sleutel'] === $actief;
    unset($t);
    return $tabs;
}

/* ── Verwijderen ────────────────────────────────────────────────── */

/**
 * Over welke sollicitaties gaat een verwijderactie? Alleen deze, of (bij een
 * AVG-verwijderverzoek) alle sollicitaties van dezelfde persoon.
 *
 * @return int[]
 */
function bh_verwijder_ids(int $sol_id, string $omvang): array {
    if ($omvang !== 'persoon') return [$sol_id];
    try {
        $q = db()->prepare("SELECT s2.id FROM sollicitaties s
                            JOIN sollicitaties s2 ON s2.persoon_id = s.persoon_id
                            WHERE s.id = ? ORDER BY s2.id");
        $q->execute([$sol_id]);
        $ids = array_map('intval', array_column($q->fetchAll(), 'id'));
    } catch (\Throwable $e) { $ids = []; }
    return $ids ?: [$sol_id];
}

/** Ligt er bij een van deze sollicitaties een loondossier dat al verwerkt is (bewaarplicht)? */
function bh_loon_verwerkt(array $ids): bool {
    if (!function_exists('documenten_loondossier')) return false;
    foreach ($ids as $id) {
        try { if (!empty(documenten_loondossier((int)$id)['verwerkt'])) return true; }
        catch (\Throwable $e) {}
    }
    return false;
}

/**
 * Een dossier verwijderen. De enige route: het tussenscherm in admin/sollicitant.php en
 * admin/api_verwijder.php roepen allebei deze functie aan.
 *
 * Wat er verdwijnt komt uit includes/avg_wissen.php — dezelfde lijst als het portaal
 * ("Gegevens laten verwijderen") en het automatisch opschonen gebruiken. Eerder had deze
 * route een eigen, kortere lijst.
 *
 *   sollicitatie  leegmaken en de rij verwijderen; de persoon blijft.
 *   persoon       alle sollicitaties leegmaken en de persoon anonimiseren (AVG art. 17).
 *                 De lege sollicitaties blijven voor de telling, net als in het portaal.
 *
 * Loondossier: loonheffing en ID-kopie vallen onder de fiscale bewaarplicht zodra ze
 * verwerkt zijn (AVG art. 17 lid 3 sub b gaat dan voor). Dan gaat er alleen iets weg met
 * $ook_loondocs — de verklaring dat het dienstverband nooit is doorgegaan. Het recht
 * daarvoor controleert de aanroeper.
 *
 * @return array{ok:bool,fout:?string,tekst:string,loondossier:bool}
 */
function bh_dossier_verwijderen(int $sol_id, string $omvang, bool $ook_loondocs): array {
    $omvang = $omvang === 'persoon' ? 'persoon' : 'sollicitatie';
    $uit = ['ok' => false, 'fout' => null, 'tekst' => '', 'loondossier' => false];
    $db = db();
    try {
        $q = $db->prepare("SELECT s.persoon_id, p.voornaam, p.tussenvoegsel, p.achternaam
                           FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id WHERE s.id = ?");
        $q->execute([$sol_id]);
        $rij = $q->fetch();
    } catch (\Throwable $e) { $rij = null; }
    if (!$rij) { $uit['fout'] = 'Deze sollicitatie bestaat niet (meer).'; return $uit; }
    $naam = volledige_naam($rij);
    $pid  = (int)$rij['persoon_id'];
    $ids  = bh_verwijder_ids($sol_id, $omvang);

    if (bh_loon_verwerkt($ids) && !$ook_loondocs) {
        $uit['loondossier'] = true;
        $uit['fout'] = 'Niets verwijderd: ' . $naam . ' heeft een verwerkt loondossier (wettelijke bewaarplicht). '
                     . 'Dat kan alleen met de verklaring dat het dienstverband niet is doorgegaan.';
        return $uit;
    }

    try {
        $db->beginTransaction();
        foreach ($ids as $id) {
            // Loondocumenten gaan mee: niet verwerkt (geen bewaarplicht) of expliciet verklaard.
            avg_sollicitatie_leegmaken($db, $id, true);
            avg_maillog_anonimiseren($db, $id, 'verwijderd_' . $id . '@anoniem.local');
        }
        if ($omvang === 'persoon') {
            avg_persoon_anonimiseren($db, $pid, 'Verwijderd', '(verzoek AVG)', 'verwijderd_' . $sol_id . '@anoniem.local');
        } else {
            $db->prepare("DELETE FROM sollicitaties WHERE id = ?")->execute([$sol_id]);
        }
        $db->commit();
        if (function_exists('avg_na_commit')) avg_na_commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (function_exists('avg_na_rollback')) avg_na_rollback();
        error_log('bh_dossier_verwijderen ' . $sol_id . ': ' . $e->getMessage());
        $uit['fout'] = 'Verwijderen is niet gelukt; er is niets gewist. De fout staat in het logboek.';
        return $uit;
    }

    // Onthouden apparaten: eigen tabel, buiten de transactie (faalt stil). Anders blijft er
    // een token liggen dat naar een leeg of verdwenen dossier wijst.
    if (function_exists('portaal_onthoud_intrekken_alle')) {
        foreach ($ids as $id) portaal_onthoud_intrekken_alle($id);
    }
    foreach ($ids as $id) {
        if (function_exists('portaal_sollicitant_cache_wissen')) portaal_sollicitant_cache_wissen($id);
    }
    if (function_exists('audit_log')) {
        // Geen naam in het logboek bij een AVG-verzoek: die is juist gewist.
        audit_log($omvang === 'persoon' ? 'persoon_gewist_avg' : 'sollicitatie_verwijderd',
                  $omvang === 'persoon' ? 'persoon' : 'sollicitatie',
                  $omvang === 'persoon' ? $pid : $sol_id,
                  array_filter(['naam' => $omvang === 'persoon' ? null : $naam,
                                'sollicitaties' => count($ids), 'ook_loondocs' => $ook_loondocs]));
    }
    $uit['ok'] = true;
    $uit['tekst'] = $omvang === 'persoon'
        ? 'Alle gegevens van deze persoon zijn gewist (' . count($ids) . ' ' . (count($ids) === 1 ? 'sollicitatie' : 'sollicitaties') . ').'
        : $naam . ' is verwijderd uit dit seizoen.';
    return $uit;
}

/* ── Gegevens corrigeren (AVG art. 16) ──────────────────────────── */

/**
 * De velden die het bestuur mag corrigeren, met hun label. Eén bron voor het formulier,
 * de validatie en het logboek.
 */
function bh_gegevens_velden(): array {
    return ['voornaam' => 'Voornaam', 'tussenvoegsel' => 'Tussenvoegsel', 'achternaam' => 'Achternaam',
            'email' => 'E-mailadres', 'telefoon' => 'Telefoon', 'geboortedatum' => 'Geboortedatum'];
}

/**
 * Corrigeer naam, e-mail, telefoon of geboortedatum van de persoon achter een sollicitatie.
 *
 * De persoon is gedeeld over seizoenen: een correctie geldt dus ook voor eerdere
 * sollicitaties — dat is juist, het is dezelfde mens. E-mail is uniek per persoon.
 *
 * Geboortedatum raakt twee grenzen die BEWUST apart blijven: 16 (aanmeldleeftijd en
 * AVG-grens) en 18 (ondertekenen via een ouder). Er verandert niets automatisch aan de
 * documenten; wel een waarschuwing per grens die wordt overschreden.
 *
 * In het logboek: welke velden, en bij de naam oud → nieuw. Nooit de waarden van e-mail,
 * telefoon of geboortedatum — die zijn na de correctie nergens meer nodig.
 *
 * @return array{ok:bool,fout:?string,gewijzigd:string[],waarschuwingen:string[]}
 */
function bh_gegevens_corrigeren(int $sol_id, array $invoer): array {
    $uit = ['ok' => false, 'fout' => null, 'gewijzigd' => [], 'waarschuwingen' => []];
    $db = db();
    try {
        $q = $db->prepare("SELECT p.* FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id WHERE s.id = ?");
        $q->execute([$sol_id]);
        $oud = $q->fetch();
    } catch (\Throwable $e) { $oud = null; }
    if (!$oud) { $uit['fout'] = 'Deze sollicitatie bestaat niet (meer).'; return $uit; }

    $t = fn(string $k) => trim((string)($invoer[$k] ?? ''));
    $nieuw = [
        'voornaam'      => $t('voornaam'),
        'tussenvoegsel' => $t('tussenvoegsel') !== '' ? $t('tussenvoegsel') : null,
        'achternaam'    => $t('achternaam'),
        'email'         => mb_strtolower($t('email')),
        'telefoon'      => $t('telefoon') !== '' ? $t('telefoon') : null,
        'geboortedatum' => $t('geboortedatum') !== '' ? $t('geboortedatum') : null,
    ];

    if ($nieuw['voornaam'] === '' || $nieuw['achternaam'] === '') { $uit['fout'] = 'Voornaam en achternaam zijn verplicht.'; return $uit; }
    if (mb_strlen($nieuw['voornaam']) > 100 || mb_strlen($nieuw['achternaam']) > 100 || mb_strlen((string)$nieuw['tussenvoegsel']) > 30) {
        $uit['fout'] = 'Een naamveld is te lang.'; return $uit;
    }
    if (!filter_var($nieuw['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($nieuw['email']) > 200) {
        $uit['fout'] = 'Dit is geen geldig e-mailadres.'; return $uit;
    }
    if ($nieuw['telefoon'] !== null) {
        $cijfers = strlen(preg_replace('/\D+/', '', $nieuw['telefoon']));
        if ($cijfers < 10 || $cijfers > 15 || mb_strlen($nieuw['telefoon']) > 30 || !preg_match('/^[0-9+()\s\-]+$/', $nieuw['telefoon'])) {
            $uit['fout'] = 'Dit is geen geldig telefoonnummer (10 tot 15 cijfers).'; return $uit;
        }
    }
    if ($nieuw['geboortedatum'] !== null) {
        $dt = DateTime::createFromFormat('!Y-m-d', $nieuw['geboortedatum']);
        if (!$dt || $dt->format('Y-m-d') !== $nieuw['geboortedatum'] || $dt > new DateTime('today') || (int)$dt->format('Y') < 1900) {
            $uit['fout'] = 'Deze geboortedatum klopt niet.'; return $uit;
        }
    }

    foreach ($nieuw as $k => $v) {
        $was = $oud[$k] ?? null;
        $was = ($was === '' ? null : $was);
        if ($k === 'email') $was = mb_strtolower((string)$was);
        if ((string)$was !== (string)$v) $uit['gewijzigd'][] = $k;
    }
    if (!$uit['gewijzigd']) { $uit['ok'] = true; return $uit; }

    if (in_array('email', $uit['gewijzigd'], true)) {
        $d = $db->prepare("SELECT COUNT(*) FROM personen WHERE LOWER(email) = ? AND id <> ?");
        $d->execute([$nieuw['email'], (int)$oud['id']]);
        if ((int)$d->fetchColumn() > 0) {
            $uit['gewijzigd'] = [];
            $uit['fout'] = 'Dit e-mailadres hoort al bij een ander dossier. Een e-mailadres kan maar bij één persoon horen.';
            return $uit;
        }
    }

    $set = []; $par = [];
    foreach ($uit['gewijzigd'] as $k) { $set[] = "`$k` = ?"; $par[] = $nieuw[$k]; }
    $par[] = (int)$oud['id'];
    try {
        $db->prepare("UPDATE personen SET " . implode(', ', $set) . " WHERE id = ?")->execute($par);
    } catch (\Throwable $e) {
        error_log('bh_gegevens_corrigeren ' . $sol_id . ': ' . $e->getMessage());
        $uit['gewijzigd'] = [];
        $uit['fout'] = 'Opslaan is niet gelukt; er is niets gewijzigd.';
        return $uit;
    }
    if (function_exists('portaal_sollicitant_cache_wissen')) portaal_sollicitant_cache_wissen($sol_id);

    // Twee aparte grenzen, twee aparte waarschuwingen. Niet samenvoegen.
    if (in_array('geboortedatum', $uit['gewijzigd'], true) && function_exists('leeftijd_uit_datum')) {
        $l_oud = leeftijd_uit_datum($oud['geboortedatum'] ?? null);
        $l_new = leeftijd_uit_datum($nieuw['geboortedatum']);
        if ($l_oud !== null && $l_new !== null && ($l_oud < 18) !== ($l_new < 18)) {
            $uit['waarschuwingen'][] = $l_new < 18
                ? 'Volgens de nieuwe geboortedatum is deze persoon jonger dan 18: ondertekenen gaat voortaan via een ouder of verzorger. Documenten die al klaarstaan veranderen niet vanzelf; kijk bij Ondertekeningen of er iets opnieuw moet.'
                : 'Volgens de nieuwe geboortedatum is deze persoon 18 of ouder: voortaan tekent die zelf. Documenten die al via een ouder klaarstaan veranderen niet vanzelf; kijk bij Ondertekeningen of er iets opnieuw moet.';
        }
        if ($l_new !== null && $l_new < 16 && ($l_oud === null || $l_oud >= 16)) {
            $uit['waarschuwingen'][] = 'Volgens de nieuwe geboortedatum is deze persoon jonger dan 16: dat is onder de aanmeldleeftijd en de AVG-grens. Controleer of de aanmelding en de toestemming nog kloppen.';
        }
    }
    if (in_array('email', $uit['gewijzigd'], true)) {
        $uit['waarschuwingen'][] = 'Inloggen op het portaal gaat voortaan met het nieuwe e-mailadres.';
    }

    if (function_exists('audit_log')) {
        $details = ['velden' => $uit['gewijzigd'], 'persoon_id' => (int)$oud['id']];
        foreach (['voornaam', 'tussenvoegsel', 'achternaam'] as $k) {
            if (in_array($k, $uit['gewijzigd'], true)) $details[$k] = ['oud' => $oud[$k] ?? null, 'nieuw' => $nieuw[$k]];
        }
        audit_log('gegevens_gecorrigeerd', 'sollicitatie', $sol_id, $details);
    }
    $uit['ok'] = true;
    return $uit;
}

} // function_exists

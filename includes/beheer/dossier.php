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
        'beschikbaarheid' => $besch,
        'scores'          => $scores,
        'observaties'     => $observ,
        'besluit'         => $besluit,
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
 * Wie mag een ondertekend document daadwerkelijk ópenen?
 *
 * Dit stond op twee plekken met twee antwoorden: het dossiertabblad liet een
 * gezondheidsverklaring alleen aan bestuur en hoger zien, het overzichtsscherm aan
 * iedereen met leesrecht op ondertekeningen. Eén regel, hier.
 *
 *   gezondheid*  medische gegevens  -> bestuur en hoger
 *   loonheffing  BSN                -> alleen met het loondossier-recht (penningmeester/admin)
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
        $mag = mag('documenten', 'bewerken');
        return [$mag, $mag ? null : 'Alleen loondossier'];
    }
    return [true, null];
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
    $score = bh_dossier_score($dossier);
    if ($score === null) return null;

    $drempel = function_exists('instelling') ? (float)instelling('selectie.drempel', '3.5') : 3.5;
    $twijfel = $drempel - 0.5;

    if ($score >= $drempel) {
        $stand = 'door';  $label = 'Door';    $kleur = 'groen';
        $reden = 'Score ' . number_format($score, 1, ',', '') . ' ligt boven de drempel van '
               . number_format($drempel, 1, ',', '') . '.';
    } elseif ($score >= $twijfel) {
        $stand = 'twijfel'; $label = 'Twijfel'; $kleur = 'oranje';
        $reden = 'Score ' . number_format($score, 1, ',', '') . ' ligt net onder de drempel van '
               . number_format($drempel, 1, ',', '') . '. Bespreek deze kandidaat.';
    } else {
        $stand = 'niet'; $label = 'Niet';   $kleur = 'rood';
        $reden = 'Score ' . number_format($score, 1, ',', '') . ' ligt onder de drempel van '
               . number_format($drempel, 1, ',', '') . '.';
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
    // Ook tonen als de oude beschikbaarheid-rij ontbreekt maar er wél effectieve weken
    // zijn: die komen uit de nieuwe beschikbaarheid2-opzet. Anders is het tabblad
    // bereikbaar via de URL maar staat het niet in de rij.
    $_heeft_besch = $dossier['beschikbaarheid']
        || (function_exists('beschikbaarheid2_effectief')
            && beschikbaarheid2_effectief((int)$dossier['sol']['id']));
    if ($_heeft_besch) {
        $tabs[] = ['sleutel' => 'beschikbaarheid', 'label' => 'Beschikbaarheid', 'aantal' => null];
    }
    if ($dossier['scores'] || $dossier['observaties']) {
        $tabs[] = ['sleutel' => 'selectiedag', 'label' => 'Selectiedag',
                   'aantal' => $dossier['observaties'] ? count($dossier['observaties']) : null];
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

} // function_exists

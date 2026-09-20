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
        $beoor = $een("SELECT * FROM beoordelingen   WHERE sollicitatie_id=?");
        $besch = $een("SELECT * FROM beschikbaarheid WHERE sollicitatie_id=?");
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
        'beoordeling'     => $beoor,
        'beschikbaarheid' => $besch,
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
 * De zes documenten die een dossier compleet maken, met hun stand.
 * Welke zes het zijn hangt van de leeftijd af: onder de 18 tekent een ouder mee.
 *
 * @return array{gedaan:int,totaal:int,rijen:array}
 */
function bh_dossier_documenten(array $dossier): array {
    $soorten = $dossier['minderjarig']
        ? ['gezondheid_ouder' => 'Gezondheidsverklaring (ouder)',
           'intake'           => 'Intake sollicitant',
           'intake_ouder'     => 'Intake ouder']
        : ['gezondheid_18plus' => 'Gezondheidsverklaring 18+',
           'intake'            => 'Intake sollicitant'];
    $soorten['loonheffing']  = 'Loonheffingsformulier';
    $soorten['lidmaatschap'] = 'Inschrijving als lid';

    $stand = [];
    foreach ($dossier['ondertekeningen'] as $o) {
        $t = (string)$o['type'];
        // Meest recente wint; de query sorteert al aflopend.
        if (!isset($stand[$t])) $stand[$t] = $o;
    }

    $rijen = [];
    $gedaan = 0;
    foreach ($soorten as $type => $label) {
        $o = $stand[$type] ?? null;
        $klaar = $o && !empty($o['ondertekend_op']);
        if ($klaar) $gedaan++;
        $rijen[] = [
            'type' => $type, 'label' => $label, 'klaar' => $klaar,
            'op' => $o['ondertekend_op'] ?? null, 'door' => $o['ondertekenaar_naam'] ?? null,
            'token' => $o['token'] ?? null, 'id' => $o['id'] ?? null,
            // Gezondheidsverklaringen bevatten medische gegevens: alleen bestuur en hoger.
            'medisch' => str_starts_with($type, 'gezondheid'),
        ];
    }
    return ['gedaan' => $gedaan, 'totaal' => count($rijen), 'rijen' => $rijen];
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
    $b = $dossier['beoordeling'];
    if (!$b) return null;

    $score = isset($b['gemiddelde']) ? (float)$b['gemiddelde'] : null;
    if ($score === null || $score <= 0) return null;

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
    if ($dossier['beschikbaarheid']) {
        $tabs[] = ['sleutel' => 'beschikbaarheid', 'label' => 'Beschikbaarheid', 'aantal' => null];
    }
    if ($dossier['beoordeling']) {
        $tabs[] = ['sleutel' => 'beoordelingen', 'label' => 'Beoordelingen', 'aantal' => null];
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

<?php
/**
 * Paal 13 — Beter Leren Kennen (BLK): gedeelde helpers.
 *
 * Gebruikt door portaal/blk_invullen.php (het formulier) en portaal/index.php (de taak op de
 * startpagina), zodat "is het BLK ingeleverd?" op precies één plek bepaald wordt.
 */

if (!function_exists('blk_fases_zichtbaar')) {

/** Fases waarin de sollicitant het BLK kan invullen of bijwerken. */
function blk_fases_zichtbaar(): array {
    return ['uitgenodigd_selectie', 'aangemeld_selectie'];
}

/** Fases waarin het BLK eerder definitief is ingediend (een nieuwe indiening is dan een wijziging). */
function blk_fases_ingediend(): array {
    return ['aangemeld_selectie', 'selectiedag_geweest', 'uitgenodigd_tw', 'tw_geweest', 'aangenomen', 'afgewezen'];
}

/**
 * Is het BLK definitief ingeleverd? Niet af te leiden uit het bestaan van een rij (de autosave
 * maakt al een concept-rij aan), wel uit definitief_op of uit de fase.
 */
function blk_is_ingediend(array $sol, ?array $blk): bool {
    if (empty($blk)) return false;
    if (!empty($blk['definitief_op'])) return true;
    return in_array($sol['fase'] ?? '', blk_fases_ingediend(), true);
}

/** Leesbare labels per veld, voor het wijzigingslog. */
function blk_veld_labels(): array {
    return [
        'telefoon'                       => 'Telefoonnummer',
        'postcode'                       => 'Postcode',
        'huisnummer'                     => 'Huisnummer',
        'straat'                         => 'Straat',
        'woonplaats'                     => 'Woonplaats',
        'adres'                          => 'Woonadres',
        'opleiding'                      => 'Opleiding',
        'werkervaring'                   => 'Werkervaring',
        'diplomas'                       => "Diploma's",
        'motivatie_lifeguard'            => 'Motivatie lifeguard',
        'motivatie_strand'               => 'Strand motivatie',
        'beschikbaarheid_intentie'       => 'Beschikbaarheid',
        'beschikbaarheid_uitzonderingen' => 'Uitzonderingen',
        'social_media'                   => 'Social media',
        'bijzonderheden'                 => 'Bijzonderheden',
    ];
}

/** Haal de BLK-rij op (of null). Zorgt dat de kolom definitief_op bestaat. */
function blk_van(int $sol_id): ?array {
    try {
        if (function_exists('db_kolom_verzeker')) {
            db_kolom_verzeker(db(), 'blk_formulieren', 'definitief_op', 'DATETIME DEFAULT NULL');
        }
    } catch (\Exception $e) { /* failsafe mag stil falen */ }
    $b = db()->prepare("SELECT * FROM blk_formulieren WHERE sollicitatie_id=?");
    $b->execute([$sol_id]);
    $rij = $b->fetch();
    return $rij ?: null;
}

}

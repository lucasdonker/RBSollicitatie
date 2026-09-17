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

/**
 * Zorg dat de kolommen bestaan die het formulier opslaat (failsafe, zie migratie 0008).
 * Zonder de adreskolommen werden postcode/huisnummer stil weggefilterd bij het opslaan.
 */
function blk_kolommen_verzeker(): void {
    if (!function_exists('db_kolom_verzeker')) return;
    try {
        $db = db();
        db_kolom_verzeker($db, 'blk_formulieren', 'postcode',      'VARCHAR(10) DEFAULT NULL');
        db_kolom_verzeker($db, 'blk_formulieren', 'huisnummer',    'VARCHAR(15) DEFAULT NULL');
        db_kolom_verzeker($db, 'blk_formulieren', 'straat',        'VARCHAR(150) DEFAULT NULL');
        db_kolom_verzeker($db, 'blk_formulieren', 'woonplaats',    'VARCHAR(100) DEFAULT NULL');
        db_kolom_verzeker($db, 'blk_formulieren', 'definitief_op', 'DATETIME DEFAULT NULL');
    } catch (\Exception $e) { /* failsafe mag stil falen */ }
}

/** Haal de BLK-rij op (of null). Zorgt eerst dat alle kolommen bestaan. */
function blk_van(int $sol_id): ?array {
    blk_kolommen_verzeker();
    $b = db()->prepare("SELECT * FROM blk_formulieren WHERE sollicitatie_id=?");
    $b->execute([$sol_id]);
    $rij = $b->fetch();
    return $rij ?: null;
}

/**
 * Splits een samengesteld adres ("Duinweg 42, 1759 GA Callantsoog") in losse delen.
 * Voor oude rijen waarin alleen `adres` gevuld is. Geeft lege strings terug als het niet lukt.
 */
function blk_adres_splitsen(?string $adres): array {
    $uit = ['straat' => '', 'huisnummer' => '', 'postcode' => '', 'woonplaats' => ''];
    $adres = trim((string)$adres);
    if ($adres === '') return $uit;
    if (preg_match('/^(.*?)\s+(\d+\S*)\s*,\s*(\d{4}\s?[A-Za-z]{2})\s+(.+)$/u', $adres, $m)) {
        $pc = strtoupper(preg_replace('/\s+/', '', $m[3]));
        return ['straat' => trim($m[1]), 'huisnummer' => trim($m[2]), 'postcode' => substr($pc, 0, 4) . ' ' . substr($pc, 4), 'woonplaats' => trim($m[4])];
    }
    return $uit;
}

/** Platforms voor het social-media-blok: sleutel => naam + placeholder. */
function blk_social_platforms(): array {
    return [
        'instagram' => ['naam' => 'Instagram', 'placeholder' => '@jouwnaam'],
        'tiktok'    => ['naam' => 'TikTok',    'placeholder' => '@jouwnaam'],
        'facebook'  => ['naam' => 'Facebook',  'placeholder' => 'naam of link naar je profiel'],
    ];
}

/** Maak van een link of losse tekst een nette gebruikersnaam (@naam voor Instagram/TikTok). */
function blk_social_normaliseer(string $sleutel, string $in): string {
    $in = trim($in);
    if ($in === '') return '';
    // Link geplakt? Neem het laatste pad-deel als gebruikersnaam.
    if (preg_match('~^(?:https?://)?(?:www\.)?(?:instagram\.com|tiktok\.com|facebook\.com|fb\.com)/(@?[^/?#\s]+)~i', $in, $m)) {
        $in = $m[1];
    }
    $in = ltrim($in, '@');
    return in_array($sleutel, ['instagram', 'tiktok'], true) ? '@' . $in : $in;
}

/** Lees de social_*-velden uit een POST en normaliseer ze: sleutel => waarde (leeg = niet ingevuld). */
function blk_social_uit_post(array $post): array {
    $uit = [];
    foreach (blk_social_platforms() as $sleutel => $pf) {
        $uit[$sleutel] = blk_social_normaliseer($sleutel, (string)($post['social_' . $sleutel] ?? ''));
    }
    return $uit;
}

/** Eén leesbare tekst voor de kolom social_media: "Instagram @lars · TikTok @lars.tt · Facebook lars.vanbeek". */
function blk_social_samenstellen(array $delen): string {
    $stukken = [];
    foreach (blk_social_platforms() as $sleutel => $pf) {
        if (!empty($delen[$sleutel])) $stukken[] = $pf['naam'] . ' ' . $delen[$sleutel];
    }
    return implode(' · ', $stukken);
}

/** Omgekeerde van blk_social_samenstellen; losse oude tekst ("@lars") landt bij Instagram (met @) of Facebook. */
function blk_social_splitsen(?string $tekst): array {
    $uit = array_fill_keys(array_keys(blk_social_platforms()), '');
    $tekst = trim((string)$tekst);
    if ($tekst === '') return $uit;
    $herkend = false;
    foreach (preg_split('/\s*·\s*/u', $tekst) as $stuk) {
        foreach (blk_social_platforms() as $sleutel => $pf) {
            if (stripos($stuk, $pf['naam'] . ' ') === 0) { $uit[$sleutel] = trim(substr($stuk, strlen($pf['naam']))); $herkend = true; }
        }
    }
    if (!$herkend) {
        $uit[str_starts_with($tekst, '@') ? 'instagram' : 'facebook'] = $tekst;
    }
    return $uit;
}

}

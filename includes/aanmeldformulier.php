<?php
/**
 * Paal 13 — Het aanmeldformulier (presentatie), één bron voor alle ingangen.
 *
 * Drie plekken tonen hetzelfde formulier en mogen daarom nooit uit elkaar lopen:
 *   /aanmelden.php                      het eigen formulier (sessie + csrf)
 *   includes/paginas/formulier.php      op een landingspagina (geen sessie, wel anti-bot-token)
 *   wordpress-plugin/                   op paal13.com (eigen kopie van de HTML, zelfde namen)
 *
 * Dit bestand gaat alleen over hóe het formulier eruitziet en welke velden erin zitten.
 * De verwerking staat in includes/aanmelden_kern.php (sollicitatie_aanmaken); de veldnamen
 * hier zijn exact de sleutels die die functie verwacht. Voeg je een veld toe, dan hoort dat
 * in aanmeld_velden() én in sollicitatie_aanmaken() — nergens anders.
 *
 * Het formulier is in drie stappen verdeeld. Dat is bewust en werkt twee kanten op:
 *  - minder velden tegelijk in beeld, dus minder afhakers
 *  - per stap meetbaar waar iemand stopt (zie includes/paginas/statistiek.php)
 * Zonder JavaScript staan alle stappen gewoon onder elkaar met één verzendknop: de stappen
 * zijn een laag erbovenop, geen voorwaarde. Daarom maakt JS de navigatieknoppen, niet PHP.
 */

if (!function_exists('aanmeld_formulier_html')) {

/** De stappen, in volgorde. 'kort' is het label in de voortgangsbalk. */
function aanmeld_stappen(): array {
    return [
        1 => ['titel' => 'Hoe heet je?',        'kort' => 'Naam'],
        2 => ['titel' => 'Een paar gegevens',   'kort' => 'Jij'],
        3 => ['titel' => 'Hoe bereiken we je?', 'kort' => 'Contact'],
    ];
}

/**
 * De velden. 'naam' is de POST-sleutel en komt letterlijk zo aan in sollicitatie_aanmaken().
 * 'rij' zet velden naast elkaar; velden met dezelfde rij vormen samen één regel.
 */
function aanmeld_velden(): array {
    return [
        ['naam' => 'voornaam',      'stap' => 1, 'rij' => 1, 'type' => 'text',   'label' => 'Voornaam',
         'verplicht' => true,  'autocomplete' => 'given-name',      'breedte' => 'breed'],
        // Geen hulptekst: "(optioneel)" achter het label zegt het al, en een extra regel
        // onder alleen dit veld zet de rij visueel scheef.
        ['naam' => 'tussenvoegsel', 'stap' => 1, 'rij' => 1, 'type' => 'text',   'label' => 'Tussenvoegsel',
         'verplicht' => false, 'autocomplete' => 'additional-name', 'breedte' => 'smal'],
        ['naam' => 'achternaam',    'stap' => 1, 'rij' => 2, 'type' => 'text',   'label' => 'Achternaam',
         'verplicht' => true,  'autocomplete' => 'family-name',     'breedte' => 'vol'],

        ['naam' => 'geboortedatum', 'stap' => 2, 'rij' => 3, 'type' => 'date',   'label' => 'Geboortedatum',
         'verplicht' => true,  'autocomplete' => 'bday',            'breedte' => 'breed',
         'hulp' => 'Je moet minimaal 15 jaar zijn om je aan te melden.'],
        ['naam' => 'geslacht',      'stap' => 2, 'rij' => 3, 'type' => 'select', 'label' => 'Geslacht',
         'verplicht' => false, 'breedte' => 'breed',
         'hulp' => 'We gebruiken dit alleen voor de slaapindeling in het trainingsweekend.',
         'opties' => ['onbekend' => 'Zeg ik liever niet', 'man' => 'Man', 'vrouw' => 'Vrouw', 'anders' => 'Anders']],

        ['naam' => 'email',         'stap' => 3, 'rij' => 4, 'type' => 'email',  'label' => 'E-mailadres',
         'verplicht' => true,  'autocomplete' => 'email',           'breedte' => 'vol',
         'hulp' => 'Hierop krijg je de bevestiging en de uitnodiging voor de selectiedag.'],
        ['naam' => 'telefoon',      'stap' => 3, 'rij' => 5, 'type' => 'tel',    'label' => 'Telefoonnummer',
         'verplicht' => true,  'autocomplete' => 'tel',             'breedte' => 'vol',
         'placeholder' => '06 12345678',
         'hulp' => 'Alleen voor korte berichten over je sollicitatie.'],
    ];
}

/**
 * Welk veld hoort bij een foutcode uit sollicitatie_aanmaken()?
 * Bepaalt waar aria-invalid komt te staan en naar welke stap het formulier terugspringt.
 */
function aanmeld_fout_veld(string $code): string {
    return [
        'email_ongeldig' => 'email',
        'al_aangemeld'   => 'email',
        'dob_ongeldig'   => 'geboortedatum',
        'te_jong'        => 'geboortedatum',
        'geen_akkoord'   => 'akkoord',
        'ouder_nodig'    => 'ouder_toestemming_naam',
    ][$code] ?? '';
}

/** In welke stap staat een veld? 0 = niet gevonden. */
function aanmeld_veld_stap(string $naam): int {
    if ($naam === 'akkoord' || $naam === 'ouder_toestemming_naam' || $naam === 'ouder_akkoord') return 3;
    foreach (aanmeld_velden() as $v) {
        if ($v['naam'] === $naam) return (int)$v['stap'];
    }
    return 0;
}

/** De privacyverklaring (includes/privacy_verklaring.php). */
function aanmeld_privacy_url(): string {
    return function_exists('privacy_url') ? privacy_url() : APP_URL . '/privacy.php';
}

/** De URL van het tracking-endpoint, of '' als tracking uitstaat. */
function aanmeld_track_url(): string {
    if (function_exists('instelling_bool') && !instelling_bool('paginas.tracking', true)) return '';
    return (defined('APP_URL') ? APP_URL : '') . '/track.php';
}

/** Eén invoerveld met label, hulptekst en foutplek. */
function aanmeld_veld_html(array $veld, array $waarden, string $foutveld, string $p): string {
    $naam  = $veld['naam'];
    $id    = $p . '-' . $naam;
    $fout  = $foutveld === $naam;
    $waarde = (string)($waarden[$naam] ?? '');
    $hulp  = (string)($veld['hulp'] ?? '');
    $hulp_id = $hulp !== '' ? $id . '-hulp' : '';
    $fout_id = $id . '-fout';

    // aria-describedby wijst naar de hulptekst én naar de (voorlopig lege) foutplek, zodat
    // een schermlezer de melding meteen hoort zodra JS hem daar neerzet.
    $beschrijf = trim($hulp_id . ' ' . $fout_id);

    $attr  = ' id="' . h($id) . '" name="' . h($naam) . '"';
    $attr .= ' aria-describedby="' . h($beschrijf) . '"';
    if (!empty($veld['verplicht']))    $attr .= ' required aria-required="true"';
    if (!empty($veld['autocomplete'])) $attr .= ' autocomplete="' . h((string)$veld['autocomplete']) . '"';
    if (!empty($veld['placeholder']))  $attr .= ' placeholder="' . h((string)$veld['placeholder']) . '"';
    if ($fout)                         $attr .= ' aria-invalid="true"';
    if ($naam === 'geboortedatum')     $attr .= ' data-geboortedatum data-af-minleeftijd="15" data-af-ouderleeftijd="16"';

    $klasse = 'af-groep af-' . (string)($veld['breedte'] ?? 'vol');

    $uit  = '<div class="' . $klasse . '" data-af-veld="' . h($naam) . '">';
    $uit .= '<label for="' . h($id) . '">' . h((string)$veld['label']);
    $uit .= empty($veld['verplicht'])
        ? ' <span class="af-optioneel">(optioneel)</span>'
        : ' <span class="af-ster" aria-hidden="true">*</span>';
    $uit .= '</label>';

    if (($veld['type'] ?? '') === 'select') {
        $uit .= '<div class="af-select"><select' . $attr . '>';
        foreach ((array)($veld['opties'] ?? []) as $k => $l) {
            $sel = ((string)($waarden[$naam] ?? 'onbekend') === (string)$k) ? ' selected' : '';
            $uit .= '<option value="' . h((string)$k) . '"' . $sel . '>' . h((string)$l) . '</option>';
        }
        $uit .= '</select></div>';
    } else {
        $uit .= '<input type="' . h((string)$veld['type']) . '" value="' . h($waarde) . '"' . $attr . '>';
    }

    if ($hulp !== '') $uit .= '<p class="af-hulp" id="' . h($hulp_id) . '">' . h($hulp) . '</p>';
    $uit .= '<p class="af-veldfout" id="' . h($fout_id) . '" data-af-veldfout></p>';
    $uit .= '</div>';
    return $uit;
}

/** Het toestemmingsblok voor ouders (onder de 16 — de AVG-grens, niet de tekengrens van 18). */
function aanmeld_ouderblok_html(array $waarden, string $foutveld, string $p): string {
    $privacy = aanmeld_privacy_url();
    $inv_naam = $foutveld === 'ouder_toestemming_naam' ? ' aria-invalid="true"' : '';

    return '<div class="af-ouder" id="' . h($p) . '-ouderblok" hidden data-ouder-blok>'
         . '<h3 class="af-subkop">Toestemming van je ouder of verzorger</h3>'
         . '<p class="af-hulp">Je bent nog geen 16. Daarom hebben we toestemming van een ouder of verzorger '
         . 'nodig om je gegevens te mogen verwerken. Vul samen even dit blok in.</p>'
         . '<div class="af-groep af-vol" data-af-veld="ouder_toestemming_naam">'
         . '<label for="' . h($p) . '-oudernaam">Naam ouder of verzorger</label>'
         . '<input type="text" id="' . h($p) . '-oudernaam" name="ouder_toestemming_naam"'
         . ' value="' . h((string)($waarden['ouder_toestemming_naam'] ?? '')) . '"'
         . ' aria-describedby="' . h($p) . '-oudernaam-fout" placeholder="Voor- en achternaam"' . $inv_naam . '>'
         . '<p class="af-veldfout" id="' . h($p) . '-oudernaam-fout" data-af-veldfout></p>'
         . '</div>'
         . '<div class="af-vink" data-af-veld="ouder_akkoord">'
         . '<input type="checkbox" id="' . h($p) . '-ouderakkoord" name="ouder_akkoord" value="1"'
         . (!empty($waarden['ouder_akkoord']) ? ' checked' : '')
         . ' aria-describedby="' . h($p) . '-ouderakkoord-fout">'
         . '<label for="' . h($p) . '-ouderakkoord">Als ouder of verzorger geef ik toestemming voor het verwerken '
         . 'van de gegevens van mijn kind, volgens de <a href="' . h($privacy) . '" target="_blank" rel="noopener">privacyverklaring</a>.</label>'
         . '<p class="af-veldfout" id="' . h($p) . '-ouderakkoord-fout" data-af-veldfout></p>'
         . '</div>'
         . '</div>';
}

/**
 * Een extra klasse op de kaart, bijvoorbeeld 'af-zelfstandig' op onze eigen pagina.
 * Met een spatie ervoor, of leeg — zodat hij zo in de class-waarde past.
 */
function aanmeld_extra_class(array $opties): string {
    $extra = trim((string)($opties['extra_class'] ?? ''));
    if ($extra === '' || !preg_match('/^[a-z0-9 \-]{1,60}$/i', $extra)) return '';
    return ' ' . $extra;
}

/** Bericht als er geen open seizoen is. */
function aanmeld_gesloten_html(array $opties = []): string {
    return '<div class="af-kaart af-melding af-gesloten' . aanmeld_extra_class($opties) . '" id="aanmelden" role="status">'
         . '<h2 class="af-titel">De aanmelding is gesloten</h2>'
         . '<p>Op dit moment kun je je niet aanmelden. Zodra de inschrijving voor het volgende seizoen '
         . 'opengaat, staat dat op onze website en op social media.</p>'
         . '</div>';
}

/** Bevestiging na een geslaagde aanmelding. */
function aanmeld_succes_html(array $opties = []): string {
    return '<div class="af-kaart af-melding af-succes' . aanmeld_extra_class($opties) . '" id="aanmelden" role="status" tabindex="-1" data-formulier-succes>'
         . '<div class="af-succes-vink" aria-hidden="true">'
         . '<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.4" '
         . 'stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
         . '</div>'
         . '<h2 class="af-titel">Je aanmelding is binnen</h2>'
         . '<p>We hebben een bevestiging gestuurd naar je e-mailadres. Daarin staat een link naar je eigen '
         . 'portaal — daar volg je hoe het ervoor staat.</p>'
         . '<p class="af-klein">Geen mail gekregen? Kijk ook even bij je ongewenste post. Je krijgt bericht '
         . 'zodra je wordt uitgenodigd voor de selectiedag.</p>'
         . '</div>';
}

/**
 * Het complete formulier als HTML-string (echoot niets).
 *
 * $opties:
 *   actie        string  form action (leeg = naar dezelfde URL)
 *   bron_code    string  herkomst, reist mee naar de sollicitatie
 *   waarden      array   eerder ingevulde waarden (na een serverfout)
 *   fout         string  foutmelding bovenaan
 *   code         string  foutcode uit sollicitatie_aanmaken()
 *   csrf         bool    csrf_input() meesturen (alleen waar een sessie is)
 *   anti_bot     bool    het p13_t-token meesturen (landingspagina's)
 *   markeer      bool    hidden p13_form=1 meesturen (landingspagina's)
 *   prefix       string  id-voorvoegsel, standaard 'af'
 *   titel        string  eigen kop
 *   knop         string  tekst op de verzendknop
 */
function aanmeld_formulier_html(array $opties = []): string {
    $seizoen = function_exists('actief_seizoen') ? actief_seizoen() : null;
    if (!$seizoen || ($seizoen['status'] ?? '') !== 'open') return aanmeld_gesloten_html($opties);

    $p        = (string)($opties['prefix'] ?? 'af');
    $actie    = (string)($opties['actie'] ?? '');
    $bron     = (string)($opties['bron_code'] ?? '');
    $waarden  = (array)($opties['waarden'] ?? []);
    $fout     = (string)($opties['fout'] ?? '');
    $code     = (string)($opties['code'] ?? '');
    $knop     = (string)($opties['knop'] ?? 'Aanmelden');
    $foutveld = aanmeld_fout_veld($code);
    $startstap = $foutveld !== '' ? aanmeld_veld_stap($foutveld) : 1;
    $privacy  = aanmeld_privacy_url();
    $track    = aanmeld_track_url();

    $titel = (string)($opties['titel'] ?? '');
    if ($titel === '') {
        $naam = (string)($seizoen['naam'] ?? '');
        $titel = $naam !== '' ? 'Meld je aan voor ' . $naam : 'Meld je aan';
    }

    $stappen = aanmeld_stappen();
    $velden  = aanmeld_velden();

    ob_start();
    ?>
    <div class="af-kaart<?= aanmeld_extra_class($opties) ?>" id="aanmelden"
         data-af-kaart
         data-af-bron="<?= h($bron) ?>"
         <?= $track !== '' ? 'data-af-track="' . h($track) . '"' : '' ?>
         data-af-startstap="<?= (int)$startstap ?>">

      <div class="af-top">
        <h2 class="af-titel"><?= h($titel) ?></h2>
        <p class="af-sub">In drie korte stappen. Klaar in ongeveer een minuut.</p>
      </div>

      <?php // Voortgang: verborgen tot JS de stappen echt aanzet — anders liegt hij. ?>
      <div class="af-voortgang" hidden data-af-voortgang>
        <div class="af-balk"><span class="af-balk-vul" data-af-balkvul></span></div>
        <ol class="af-bollen">
          <?php foreach ($stappen as $nr => $s): ?>
          <li class="af-bol" data-af-bol="<?= (int)$nr ?>"><span class="af-bol-cijfer" aria-hidden="true"><?= (int)$nr ?></span><span class="af-bol-tekst"><?= h($s['kort']) ?></span></li>
          <?php endforeach; ?>
        </ol>
        <p class="af-stapteller" role="status" data-af-teller></p>
      </div>

      <?php if ($fout !== ''): ?>
      <div class="af-fout" id="<?= h($p) ?>-fout" role="alert" tabindex="-1" data-formulier-fout>
        <strong>Je aanmelding is nog niet verstuurd.</strong>
        <p><?= h($fout) ?></p>
      </div>
      <?php endif; ?>

      <form method="post"<?= $actie !== '' ? ' action="' . h($actie) . '"' : '' ?> class="af-form" novalidate data-af-form>
        <?php if (!empty($opties['csrf']) && function_exists('csrf_input')) echo csrf_input(); ?>
        <?php if (!empty($opties['anti_bot']) && function_exists('pag_token_maak')): ?>
        <input type="hidden" name="p13_t" value="<?= h(pag_token_maak($bron)) ?>">
        <?php endif; ?>
        <?php if (!empty($opties['markeer'])): ?>
        <input type="hidden" name="p13_form" value="1">
        <?php endif; ?>
        <?php if ($bron !== ''): ?>
        <input type="hidden" name="bron_code" value="<?= h($bron) ?>">
        <?php endif; ?>
        <?php // Tot welke stap iemand kwam; alleen ingevuld door JS, voor de statistiek. ?>
        <input type="hidden" name="af_stap" value="" data-af-stapveld>

        <?php // Vangnet: uit beeld, uit de tab-volgorde en verborgen voor voorleessoftware. ?>
        <div class="af-vangnet" aria-hidden="true">
          <label for="<?= h($p) ?>-website">Website (niet invullen)</label>
          <input type="text" id="<?= h($p) ?>-website" name="p13_w" tabindex="-1" autocomplete="off">
        </div>

        <?php foreach ($stappen as $nr => $stap):
            $stapvelden = array_values(array_filter($velden, fn($v) => (int)$v['stap'] === (int)$nr)); ?>
        <fieldset class="af-stap" data-af-stap="<?= (int)$nr ?>">
          <legend class="af-legend"><?= h($stap['titel']) ?></legend>

          <?php
          // Velden met dezelfde 'rij' komen naast elkaar te staan.
          $rij_nu = null;
          foreach ($stapvelden as $veld) {
              if ($rij_nu !== $veld['rij']) {
                  if ($rij_nu !== null) echo '</div>';
                  echo '<div class="af-rij">';
                  $rij_nu = $veld['rij'];
              }
              echo aanmeld_veld_html($veld, $waarden, $foutveld, $p);
          }
          if ($rij_nu !== null) echo '</div>';
          ?>

          <?php if ((int)$nr === 2) echo aanmeld_ouderblok_html($waarden, $foutveld, $p); ?>

          <?php if ((int)$nr === 3): ?>
          <div class="af-vink af-akkoord" data-af-veld="akkoord">
            <input type="checkbox" id="<?= h($p) ?>-akkoord" name="akkoord" value="1" required aria-required="true"
                   aria-describedby="<?= h($p) ?>-akkoord-fout"<?= $foutveld === 'akkoord' ? ' aria-invalid="true"' : '' ?><?= !empty($waarden['akkoord']) ? ' checked' : '' ?>>
            <label for="<?= h($p) ?>-akkoord">Ik heb de <a href="<?= h($privacy) ?>" target="_blank" rel="noopener">privacyverklaring</a> gelezen.</label>
            <p class="af-veldfout" id="<?= h($p) ?>-akkoord-fout" data-af-veldfout></p>
          </div>
          <?php endif; ?>
        </fieldset>
        <?php endforeach; ?>

        <div class="af-acties" data-af-acties>
          <button type="submit" class="af-knop" data-af-verzend><?= h($knop) ?></button>
        </div>
        <p class="af-gerust">Aanmelden verplicht je tot niets. Je kunt je later altijd terugtrekken.</p>
      </form>
    </div>
    <?php
    return (string)ob_get_clean();
}

}

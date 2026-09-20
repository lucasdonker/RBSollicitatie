<?php
/**
 * Pagina's — het aanmeldformulier dat op de plek van [[formulier]] in het ontwerp komt.
 *
 * Bewust echte HTML in de pagina zelf en géén iframe: dan erft het formulier de opmaak van
 * het ontwerp, klopt de hoogte altijd, werkt het met toetsenbord en voorleessoftware, en
 * hoeft de bezoeker niet in een venster-in-een-venster te scrollen.
 *
 * De veldnamen zijn exact die van aanmelden.php, zodat sollicitatie_aanmaken() ongewijzigd blijft.
 */

if (!function_exists('pag_formulier_html')) {

/** Welk veld hoort bij een foutcode uit sollicitatie_aanmaken()? Voor aria-invalid en de sprong-link. */
function pag_fout_veld(string $code): string {
    return [
        'email_ongeldig' => 'email',
        'al_aangemeld'   => 'email',
        'dob_ongeldig'   => 'geboortedatum',
        'te_jong'        => 'geboortedatum',
        'geen_akkoord'   => 'akkoord',
        'ouder_nodig'    => 'ouder_toestemming_naam',
    ][$code] ?? '';
}

/**
 * De verwerking van een ingestuurd formulier.
 * Retour: ['ok'=>bool, 'fout'=>string, 'code'=>string] — bij ok volgt een redirect (PRG).
 */
function pag_formulier_verwerk(array $pagina, array $post): array {
    $bron = (string)($pagina['bron_code'] ?: $pagina['slug']);
    $pid  = (int)$pagina['id'];

    $weiger = function (string $reden, string $tekst) use ($pid, $bron): array {
        // Alleen het type en de bron vastleggen — geen IP, invoer of user-agent.
        pag_track('formulier_geweigerd', $pid, $bron);
        error_log('pag_formulier geweigerd (' . $reden . ') op pagina ' . $pid);
        return ['ok' => false, 'fout' => $tekst, 'code' => $reden];
    };

    // 1. Token (anti-bot, zie token.php)
    $t = pag_token_check((string)($post['p13_t'] ?? ''), $bron);
    if (!$t['ok']) {
        if ($t['reden'] === 'verlopen') {
            return $weiger('verlopen', 'Deze pagina stond te lang open. Controleer je gegevens en verstuur het formulier opnieuw.');
        }
        if ($t['reden'] === 'te_snel') {
            return $weiger('te_snel', 'Het formulier werd wel erg snel verstuurd. Probeer het nog een keer.');
        }
        return $weiger('ongeldig_token', 'Het formulier kon niet worden verwerkt. Ververs de pagina en probeer het opnieuw.');
    }

    // 2. Honeypot: een leeg veld dat mensen niet zien en bots wel invullen
    if (trim((string)($post['p13_w'] ?? '')) !== '') {
        return $weiger('honeypot', 'Het formulier kon niet worden verwerkt. Ververs de pagina en probeer het opnieuw.');
    }

    // 3. Snelheidsbegrenzing per IP
    if (function_exists('rate_limit') && !rate_limit('pag_aanmelding', rate_identiteit(), 5, 3600)) {
        return $weiger('rate_limit', 'Er zijn in korte tijd te veel aanmeldingen vanaf dit adres gedaan. Probeer het over een uur opnieuw.');
    }

    // 4. De echte aanmelding — één bron van waarheid.
    // aanmelden_kern.php hangt niet aan functions.php (alleen aanmelden.php en api/aanmelden.php
    // laadden hem), dus p.php kende sollicitatie_aanmaken() niet en liep hier stuk. Zelf laden:
    // het bestand is klein en beschermt zichzelf met function_exists.
    if (!function_exists('sollicitatie_aanmaken')) {
        $kern = dirname(__DIR__) . '/aanmelden_kern.php';
        if (is_file($kern)) require_once $kern;
    }
    if (!function_exists('sollicitatie_aanmaken')) {
        error_log('pag_formulier_verwerk: aanmelden_kern.php ontbreekt');
        return ['ok' => false, 'code' => 'kern_ontbreekt',
                'fout' => 'Aanmelden lukt op dit moment niet. Probeer het later opnieuw of mail ons.'];
    }

    $invoer = $post;
    $invoer['bron_code']  = $bron;
    $invoer['bron_label'] = (string)$pagina['titel'];
    $res = sollicitatie_aanmaken($invoer, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'via' => 'landingspagina']);
    if (!empty($res['ok'])) return ['ok' => true, 'fout' => '', 'code' => ''];

    return ['ok' => false, 'fout' => (string)($res['fout'] ?? 'Aanmelden is niet gelukt.'), 'code' => (string)($res['code'] ?? '')];
}

/** Bericht als het seizoen dicht staat. */
function pag_gesloten_html(): string {
    return '<div class="pf-blok pf-gesloten" role="status">'
         . '<h2 class="pf-kop">De aanmelding is gesloten</h2>'
         . '<p>Op dit moment kun je je niet aanmelden. Houd onze kanalen in de gaten voor de volgende ronde.</p>'
         . '</div>';
}

/** Bevestiging na een geslaagde aanmelding (na de redirect). */
function pag_succes_html(): string {
    return '<div class="pf-blok pf-succes" id="pf-succes" role="status" tabindex="-1" data-formulier-succes>'
         . '<h2 class="pf-kop">Je aanmelding is ontvangen</h2>'
         . '<p>We hebben een bevestiging gestuurd naar je e-mailadres. Daarin staat een link naar je eigen portaal. '
         . 'Je krijgt bericht zodra je wordt uitgenodigd voor de selectiedag.</p>'
         . '<p class="pf-klein">Geen mail ontvangen? Kijk ook even in je map met ongewenste post.</p>'
         . '</div>';
}

/**
 * Het formulier als HTML.
 * $staat: ['fout'=>string, 'code'=>string, 'waarden'=>array] — bij een fout blijven de
 * ingevulde gegevens staan en krijgt de bezoeker een melding bovenaan.
 */
function pag_formulier_html(array $pagina, array $staat = []): string {
    $seizoen = function_exists('actief_seizoen') ? actief_seizoen() : null;
    if (!$seizoen || ($seizoen['status'] ?? '') !== 'open') return pag_gesloten_html();

    $fout  = (string)($staat['fout'] ?? '');
    $code  = (string)($staat['code'] ?? '');
    $w     = (array)($staat['waarden'] ?? []);
    $bron  = (string)($pagina['bron_code'] ?: $pagina['slug']);
    $veld  = pag_fout_veld($code);
    $privacy = function_exists('instelling') ? instelling('privacy.url', 'https://paal13.com/privacy-beleid/') : 'https://paal13.com/privacy-beleid/';

    $v = function (string $naam) use ($w): string { return h((string)($w[$naam] ?? '')); };
    $inv = function (string $naam) use ($veld): string { return $veld === $naam ? ' aria-invalid="true"' : ''; };

    ob_start();
    ?>
    <div class="pf-blok" id="aanmelden">
      <h2 class="pf-kop">Meld je aan<?= $seizoen['naam'] ? ' voor ' . h((string)$seizoen['naam']) : '' ?></h2>

      <?php if ($fout !== ''): ?>
      <div class="pf-fout" id="pf-fout" role="alert" tabindex="-1" data-formulier-fout>
        <strong>Je aanmelding is nog niet verstuurd.</strong>
        <p><?= h($fout) ?></p>
      </div>
      <?php endif; ?>

      <form method="post" action="#aanmelden" class="pf-form" novalidate>
        <input type="hidden" name="p13_t" value="<?= h(pag_token_maak($bron)) ?>">
        <input type="hidden" name="p13_form" value="1">
        <?php // Honeypot: uit beeld maar wél focusbaar uitgesloten, zodat voorleessoftware hem overslaat ?>
        <div class="pf-vangnet" aria-hidden="true">
          <label for="pf-website">Website (niet invullen)</label>
          <input type="text" id="pf-website" name="p13_w" tabindex="-1" autocomplete="off">
        </div>

        <div class="pf-rij">
          <div class="pf-groep">
            <label for="pf-voornaam">Voornaam <span class="pf-ster" aria-hidden="true">*</span></label>
            <input type="text" id="pf-voornaam" name="voornaam" value="<?= $v('voornaam') ?>" required aria-required="true" autocomplete="given-name">
          </div>
          <div class="pf-groep pf-smal">
            <label for="pf-tussenvoegsel">Tussenvoegsel</label>
            <input type="text" id="pf-tussenvoegsel" name="tussenvoegsel" value="<?= $v('tussenvoegsel') ?>" autocomplete="additional-name">
          </div>
        </div>

        <div class="pf-groep">
          <label for="pf-achternaam">Achternaam <span class="pf-ster" aria-hidden="true">*</span></label>
          <input type="text" id="pf-achternaam" name="achternaam" value="<?= $v('achternaam') ?>" required aria-required="true" autocomplete="family-name">
        </div>

        <div class="pf-rij">
          <div class="pf-groep">
            <label for="pf-geboortedatum">Geboortedatum <span class="pf-ster" aria-hidden="true">*</span></label>
            <input type="date" id="pf-geboortedatum" name="geboortedatum" value="<?= $v('geboortedatum') ?>" required aria-required="true"<?= $inv('geboortedatum') ?> aria-describedby="pf-dob-hulp" data-geboortedatum>
            <p class="pf-hulp" id="pf-dob-hulp">Je moet minimaal 15 jaar zijn om je aan te melden.</p>
          </div>
          <div class="pf-groep">
            <label for="pf-geslacht">Geslacht</label>
            <select id="pf-geslacht" name="geslacht">
              <?php foreach (['onbekend' => 'Zeg ik liever niet', 'man' => 'Man', 'vrouw' => 'Vrouw', 'anders' => 'Anders'] as $k => $l): ?>
              <option value="<?= $k ?>"<?= (($w['geslacht'] ?? 'onbekend') === $k ? ' selected' : '') ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="pf-groep">
          <label for="pf-email">E-mailadres <span class="pf-ster" aria-hidden="true">*</span></label>
          <input type="email" id="pf-email" name="email" value="<?= $v('email') ?>" required aria-required="true"<?= $inv('email') ?> autocomplete="email" aria-describedby="pf-email-hulp">
          <p class="pf-hulp" id="pf-email-hulp">Hierop ontvang je de bevestiging en de uitnodigingen.</p>
        </div>

        <div class="pf-groep">
          <label for="pf-telefoon">Telefoonnummer <span class="pf-ster" aria-hidden="true">*</span></label>
          <input type="tel" id="pf-telefoon" name="telefoon" value="<?= $v('telefoon') ?>" required aria-required="true" autocomplete="tel" placeholder="06 12345678">
        </div>

        <?php // Ouder-toestemming: alleen voor 15-jarigen (AVG-grens van 16 jaar). Verschijnt via JS. ?>
        <div class="pf-ouder" id="pf-ouderblok" hidden data-ouder-blok>
          <h3 class="pf-subkop">Toestemming van je ouder of verzorger</h3>
          <p class="pf-hulp">Je bent nog geen 16 jaar. Daarom hebben we toestemming van een ouder of verzorger nodig om je gegevens te mogen verwerken.</p>
          <div class="pf-groep">
            <label for="pf-oudernaam">Naam ouder of verzorger</label>
            <input type="text" id="pf-oudernaam" name="ouder_toestemming_naam" value="<?= $v('ouder_toestemming_naam') ?>"<?= $inv('ouder_toestemming_naam') ?> placeholder="Voor- en achternaam">
          </div>
          <div class="pf-vink">
            <input type="checkbox" id="pf-ouderakkoord" name="ouder_akkoord" value="1"<?= !empty($w['ouder_akkoord']) ? ' checked' : '' ?>>
            <label for="pf-ouderakkoord">Als ouder of verzorger geef ik toestemming voor het verwerken van de gegevens van mijn kind, volgens de <a href="<?= h($privacy) ?>" target="_blank" rel="noopener">privacyverklaring</a>.</label>
          </div>
        </div>

        <div class="pf-vink">
          <input type="checkbox" id="pf-akkoord" name="akkoord" value="1" required aria-required="true"<?= $inv('akkoord') ?><?= !empty($w['akkoord']) ? ' checked' : '' ?>>
          <label for="pf-akkoord">Ik ga akkoord met de <a href="<?= h($privacy) ?>" target="_blank" rel="noopener">privacyverklaring</a>.</label>
        </div>

        <button type="submit" class="pf-knop">Aanmelden</button>
      </form>
    </div>
    <?php
    return (string)ob_get_clean();
}

}

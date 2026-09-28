<?php
/**
 * Admin — Systeem & Updates
 * Toont de geïnstalleerde versie, de changelog en of alle tabellen aanwezig zijn.
 * Handig direct na een upload: draaien de automatische migraties?
 */
require_once __DIR__ . '/../includes/functions.php';
// Dit scherm draait migraties en repareert het schema, dus 'bewerken' en niet 'lezen'.
// In de standaardmatrix heeft alleen de beheerder de module 'systeem'.
vereis_recht('systeem', 'bewerken');

$pagina_titel = 'Systeem & Updates';
$actieve_nav  = 'systeem';

// Verwachte tabellen (worden automatisch aangemaakt bij gebruik)
$verwacht = [
    'Kern' => ['personen', 'sollicitaties', 'seizoenen', 'beheerders', 'instellingen'],
    'Beschikbaarheid' => ['beschikbaarheid_profiel', 'beschikbaarheid_blok', 'beschikbaarheid_afwezig',
                          'beschikbaarheid_verzoek', 'beschikbaarheid_correctie'],
    'Competenties'    => ['competenties', 'authentik_koppeling'],
    'Planning'        => ['loon_planning', 'loon_scenario'],
];

$aanwezig = [];
$db_fout = null;
try {
    $aanwezig = db()->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $db_fout = $e->getMessage();
}

// Migraties forceren / schema repareren (handig na een upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'migreer') {
    csrf_check();
    if (function_exists('db_failsafe')) {
        try { db_failsafe(true); } catch (\Throwable $e) {}
    }
    flash('succes', 'Databasecontrole uitgevoerd: ontbrekende tabellen en kolommen zijn aangemaakt waar nodig.');
    redirect(APP_URL . '/admin/systeem.php');
}

// Schema-gezondheid ophalen (voor de statusweergave)
$gezondheid = function_exists('db_gezondheid') ? db_gezondheid() : null;

// Health check: volledige systeemcontrole (met optionele reparatie)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'health_repareer') {
    csrf_check();
    if (function_exists('health_check')) health_check(true);
    flash('succes', 'Systeemcontrole uitgevoerd en waar mogelijk hersteld.');
    redirect(APP_URL . '/admin/systeem.php');
}
$health = function_exists('health_check') ? health_check(false) : null;

// Herinneringen: proefdraaien (niets versturen) of nu echt draaien
$herinnering_resultaat = null; $herinnering_echt = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['actie'] ?? '', ['herinneringen_proef', 'herinneringen_draai'], true)) {
    csrf_check();
    $herinnering_echt = ($_POST['actie'] === 'herinneringen_draai');
    if (function_exists('herinnering_run')) {
        $herinnering_resultaat = herinnering_run(null, !$herinnering_echt);
        if ($herinnering_echt && function_exists('audit_log')) audit_log('herinneringen_gedraaid', 'systeem', null, ['via' => 'beheer']);
    }
}

$changelog = function_exists('app_changelog') ? app_changelog() : [];

require __DIR__ . '/layout_top.php';
?>

<div class="ds-kaart">
  <div class="bh-kaartbody bh-sys-versiebalk">
    <div>
      <div class="bh-sys-klein">Geïnstalleerde versie</div>
      <div class="bh-sys-versie"><?= h(APP_VERSIE) ?></div>
    </div>
    <div class="bh-vulling"></div>
    <?php /* Hele scherm eist al systeem-bewerken; update.php zelf eist rol admin. */ ?>
    <a href="<?= APP_URL ?>/admin/update.php" class="ds-btn ds-btn-secundair ds-btn-sm" title="Losse SQL-migraties uit de map sql/ bekijken en uitvoeren">SQL-migraties</a>
    <form method="POST" class="bh-sys-kaal">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="migreer">
      <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm" title="Voer alle automatische database-migraties uit">Migraties uitvoeren</button>
    </form>
  </div>
</div>

<!-- Systeemgezondheid (health check) -->
<?php if ($health !== null): ?>
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Systeemgezondheid</h2></div>
  <div class="bh-kaartbody">
    <?php
      // Kleur per stand: klassen .bh-sys-status/.bh-sys-kleur + ok|waarschuwing|fout in beheer.css.
      $health_icoon = ['ok' => 'vink', 'waarschuwing' => 'waarschuwing', 'fout' => 'kruis'];
      $health_label = ['ok' => 'Alles in orde', 'waarschuwing' => 'Aandachtspunten', 'fout' => 'Er is een probleem'];
      $st = $health['status'];
    ?>
    <div class="bh-sys-status <?= h($st) ?>">
      <span class="bh-healthicoon"><?= icoon($health_icoon[$st], 18) ?></span>
      <span class="bh-sys-statustekst bh-sys-kleur <?= h($st) ?>"><strong><?= $health_label[$st] ?>.</strong></span>
    </div>

    <div class="bh-sys-checks">
      <?php
        $check_namen = [
          'database' => 'Database', 'cron' => 'Cron (automatische taken)', 'mailwachtrij' => 'Mailwachtrij',
          'opschoning' => 'AVG-opschoning', 'mappen' => 'Mappen & rechten', 'configuratie' => 'Configuratie',
          'berichten' => 'E-mail en testmodus', 'inloggen' => 'Inloggen (Authentik)',
          'kluis' => 'Kluis (versleutelde documenten)', 'zegel' => 'Documentzegel (PDF)',
          'snelheid' => 'Snel antwoorden (PHP-FPM)', 'backup' => 'Back-up vóór updates',
        ];
        foreach ($health['checks'] as $sleutel => $check):
          $ck = $check['status'];
          $punten = $check['punten'] ?? [];
      ?>
      <div class="bh-sys-check">
        <span class="bh-healthicoon bh-sys-kleur <?= h($ck) ?>"><?= icoon($health_icoon[$ck], 15) ?></span>
        <div class="bh-vulling">
          <div class="bh-sys-checknaam"><?= h($check_namen[$sleutel] ?? $sleutel) ?></div>
          <?php if ($punten): /* Per punt de tekst + een knop naar het juiste tabblad (health_punt()). */ ?>
            <ul class="bh-instellen-lijst">
              <?php foreach ($punten as $p): ?>
                <li class="bh-instellen-punt <?= h($p['soort']) ?>">
                  <span class="bh-instellen-tekst bh-sys-klein"><?= h($p['tekst']) ?></span>
                  <a class="ds-btn ds-btn-zacht ds-btn-sm" href="<?= h($p['url']) ?>"><?= h($p['knop']) ?></a>
                </li>
              <?php endforeach; ?>
            </ul>
            <?php if ($sleutel === 'berichten' && function_exists('mail_omleiden') && mail_omleiden() !== ''): ?>
              <div class="bh-sys-klein"><?= h(health_testmodus_tekst(mail_omleiden())) ?></div>
            <?php endif; ?>
          <?php else: ?>
          <div class="bh-sys-klein"><?= h($check['uitleg']) ?><?= !empty($check['hersteld']) ? ' <span class="bh-tekst-ok">(automatisch hersteld)</span>' : '' ?></div>
          <?php endif; ?>
          <?php if ($sleutel === 'kluis'): ?><a href="<?= APP_URL ?>/admin/kluis.php" class="ds-btn ds-btn-zacht ds-btn-sm"><?= icoon('sleutel', 14) ?> Kluissleutel wisselen</a><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <form method="post" class="bh-sys-repareer">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="health_repareer">
      <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Controleren &amp; repareren</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Herinneringen: proefdraaien -->
<?php if (function_exists('herinnering_run')): ?>
<div class="ds-kaart bh-sys-herinneringen" id="herinneringen">
  <div class="bh-kaartkop"><h2>Herinneringen</h2></div>
  <div class="bh-kaartbody">
    <p class="bh-sys-intro">
      Herinneringen draaien automatisch elk kwartier; laatste keer:
      <strong><?= h(instelling('cron.laatste_kwartier', '') ?: 'nog niet') ?></strong>.
      Proefdraaien toont wie een herinnering zou krijgen zonder iets te versturen.
    </p>
    <div class="bh-sys-knoprij">
      <form method="post" class="bh-sys-kaal"><?= csrf_input() ?><input type="hidden" name="actie" value="herinneringen_proef">
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Proefdraaien (niets versturen)</button></form>
      <form method="post" class="bh-sys-kaal" data-bevestig-titel="Herinneringen nu versturen?"
            data-bevestig="Iedereen die volgens de regels een herinnering hoort te krijgen, krijgt hem ook echt. Wil je eerst zien wie dat zijn, gebruik dan Proefdraaien."
            data-bevestig-knop="Ja, versturen"><?= csrf_input() ?><input type="hidden" name="actie" value="herinneringen_draai">
        <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Nu draaien</button></form>
    </div>
    <?php if ($herinnering_resultaat !== null): ?>
    <div class="bh-sys-resultaat">
      <div class="bh-sys-resultaat-kop"><?= $herinnering_echt ? 'Gedraaid' : 'Proefrun' ?> op <?= date('d-m-Y H:i') ?></div>
      <?php foreach ($herinnering_resultaat as $hs => $hr): ?>
      <div class="bh-sys-herinnering">
        <div><strong><?= h($hr['naam']) ?></strong>
          <?php if (!$hr['aan']): ?><span class="badge">uit</span>
          <?php else: ?> — <?= (int)$hr['kandidaten'] ?> kandidaat/-aten, <?= (int)$hr['verstuurd'] ?> <?= $herinnering_echt ? 'in de wachtrij' : 'zou versturen' ?>, <?= (int)$hr['overgeslagen'] ?> overgeslagen<?php endif; ?>
        </div>
        <?php if (!empty($hr['lijst'])): ?>
        <div class="bh-sys-herinnering-lijst">
          <?php foreach ($hr['lijst'] as $hl): ?>
            <span class="bh-sys-herinnering-naam"><?= h($hl['naam'] ?: ('#' . $hl['sol_id'])) ?>: <em><?= h($hl['actie']) ?></em></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Tabelstatus -->
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Database</h2></div>
  <div class="bh-kaartbody">
    <?php if ($db_fout): ?>
      <div class="bh-tekst-fout">Kan de database niet lezen: <?= h($db_fout) ?></div>
    <?php else: ?>
      <?php if ($gezondheid !== null): ?>
        <?php if ($gezondheid['ok']): ?>
          <div class="bh-sys-status ok bh-sys-status-db">
            <span class="bh-healthicoon"><?= icoon('vink', 18) ?></span>
            <span class="bh-sys-statustekst bh-sys-kleur ok"><strong>Database in orde.</strong> Alle verwachte tabellen en kolommen zijn aanwezig.</span>
          </div>
        <?php else: ?>
          <div class="bh-sys-dbmelding">
            <div class="bh-sys-dbmelding-kop">Er ontbreekt iets in de database</div>
            <?php if (!empty($gezondheid['ontbrekende_kolommen'])): ?>
              <div class="bh-sys-klein">Ontbrekende kolommen: <?= h(implode(', ', $gezondheid['ontbrekende_kolommen'])) ?></div>
            <?php endif; ?>
            <div class="bh-sys-klein bh-sys-klein-ruimte">Klik bovenaan op "Migraties uitvoeren" om dit automatisch te herstellen.</div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
      <p class="bh-sys-uitleg">
        Het systeem controleert en herstelt het databaseschema automatisch bij elk bezoek. Ontbrekende tabellen
        en kolommen worden vanzelf aangemaakt. Klopt er toch iets niet na een upload? Klik dan bovenaan op
        "Migraties uitvoeren".
      </p>
      <?php foreach ($verwacht as $groep => $tabellen): ?>
      <div class="bh-sys-tabelgroep">
        <div class="bh-sys-tabelgroep-kop"><?= h($groep) ?></div>
        <div class="bh-sys-tabellen">
          <?php foreach ($tabellen as $t): $ok = in_array($t, $aanwezig, true); ?>
          <span class="bh-sys-tabel<?= $ok ? ' ok' : '' ?>">
            <?= icoon($ok ? 'vink' : 'punt', 13) ?> <?= h($t) ?>
          </span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Logboek -->
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Logboek</h2></div>
  <div class="bh-sys-logboek">
    <p class="bh-sys-uitleg">
      Alle sporen van het systeem in één tijdlijn: acties, verstuurde berichten, technische fouten,
      het bewijsspoor van ondertekeningen, de AVG-opschoning en mislukte inlogpogingen.
    </p>
    <a href="<?= APP_URL ?>/admin/logboek.php" class="ds-btn ds-btn-secundair ds-btn-sm"><?= icoon('auditlog', 14) ?> Logboek openen</a>
  </div>
</div>

<!-- Changelog -->
<div class="ds-kaart">
  <div class="bh-kaartkop"><h2>Changelog</h2></div>
  <div class="bh-kaartbody">
    <?php if (!$changelog): ?>
      <div class="bh-sys-leeg">Geen changelog beschikbaar.</div>
    <?php else: ?>
      <?php foreach ($changelog as $versie => $c): ?>
      <details <?= $versie === APP_VERSIE ? 'open' : '' ?> class="bh-sys-versieblok">
        <summary class="bh-sys-versiekop">
          <span class="bh-sys-versienr"><?= h($versie) ?></span>
          <?php if ($versie === APP_VERSIE): ?>
            <span class="bh-sys-huidig">HUIDIG</span>
          <?php endif; ?>
          <span class="bh-sys-versietitel"><?= h($c['titel'] ?? '') ?></span>
          <span class="bh-vulling"></span>
          <span class="bh-sys-datum"><?= h($c['datum'] ?? '') ?></span>
        </summary>
        <ul class="bh-sys-punten">
          <?php foreach ($c['punten'] ?? [] as $p): ?>
          <li><?= h($p) ?></li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>

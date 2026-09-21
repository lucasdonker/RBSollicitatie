<?php
/**
 * Admin — Systeem & Updates
 * Toont de geïnstalleerde versie, de changelog en of alle tabellen aanwezig zijn.
 * Handig direct na een upload: draaien de automatische migraties?
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_rol('admin');

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

<div class="ds-kaart" style="margin-bottom:14px">
  <div class="bh-kaartbody" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div>
      <div style="font-size:12px;color:var(--tekst-zacht)">Geïnstalleerde versie</div>
      <div style="font-size:24px;font-weight:700;color:var(--navy)"><?= h(APP_VERSIE) ?></div>
    </div>
    <div style="flex:1"></div>
    <a href="<?= APP_URL ?>/diagnose.php" class="ds-btn ds-btn-secundair ds-btn-sm" target="_blank"> Diagnose draaien</a>
    <form method="POST" style="margin:0">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="migreer">
      <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm" title="Voer alle automatische database-migraties uit">Migraties uitvoeren</button>
    </form>
  </div>
</div>

<!-- Systeemgezondheid (health check) -->
<?php if ($health !== null): ?>
<div class="ds-kaart" style="margin-bottom:14px">
  <div class="bh-kaartkop"><h2>Systeemgezondheid</h2></div>
  <div class="bh-kaartbody">
    <?php
      $health_kleur = ['ok' => 'var(--groen)', 'waarschuwing' => 'var(--oranje)', 'fout' => 'var(--rood)'];
      $health_bg    = ['ok' => 'var(--groen-bg)', 'waarschuwing' => 'var(--oranje-bg)', 'fout' => 'var(--rood-bg)'];
      $health_icoon = ['ok' => 'vink', 'waarschuwing' => 'waarschuwing', 'fout' => 'kruis'];
      $health_label = ['ok' => 'Alles in orde', 'waarschuwing' => 'Aandachtspunten', 'fout' => 'Er is een probleem'];
      $st = $health['status'];
    ?>
    <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:<?= $health_bg[$st] ?>;border:1px solid <?= $health_kleur[$st] ?>;border-radius:8px;margin-bottom:14px">
      <span class="bh-healthicoon"><?= icoon($health_icoon[$st], 18) ?></span>
      <span style="font-size:13px;color:<?= $health_kleur[$st] ?>"><strong><?= $health_label[$st] ?>.</strong></span>
    </div>

    <div style="display:flex;flex-direction:column;gap:8px">
      <?php
        $check_namen = [
          'database' => 'Database', 'cron' => 'Cron (automatische taken)', 'mailwachtrij' => 'Mailwachtrij',
          'opschoning' => 'AVG-opschoning', 'mappen' => 'Mappen & rechten', 'configuratie' => 'Configuratie',
          'kluis' => 'Kluis (versleutelde documenten)', 'zegel' => 'Documentzegel (PDF)',
        ];
        foreach ($health['checks'] as $sleutel => $check):
          $ck = $check['status'];
      ?>
      <div style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--rand)">
        <span class="bh-healthicoon" style="color:<?= $health_kleur[$ck] ?>"><?= icoon($health_icoon[$ck], 15) ?></span>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:600"><?= h($check_namen[$sleutel] ?? $sleutel) ?></div>
          <div style="font-size:12px;color:var(--tekst-zacht)"><?= h($check['uitleg']) ?><?= !empty($check['hersteld']) ? ' <span style="color:var(--groen)">(automatisch hersteld)</span>' : '' ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <form method="post" style="margin-top:14px">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="health_repareer">
      <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Controleren &amp; repareren</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Herinneringen: proefdraaien -->
<?php if (function_exists('herinnering_run')): ?>
<div class="ds-kaart" style="margin-bottom:14px" id="herinneringen">
  <div class="bh-kaartkop"><h2>Herinneringen</h2></div>
  <div class="bh-kaartbody">
    <p style="font-size:13px;color:var(--tekst-zacht);margin:0 0 12px">
      Laatste automatische run: <strong><?= h(instelling('cron.laatste_run', '') ?: 'nog niet') ?></strong>
      (<?= h(instelling('cron.laatste_bron', '') ?: '-') ?>). Proefdraaien toont wie een herinnering zou krijgen zonder iets te versturen.
    </p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <form method="post" style="margin:0"><?= csrf_input() ?><input type="hidden" name="actie" value="herinneringen_proef">
        <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Proefdraaien (niets versturen)</button></form>
      <form method="post" style="margin:0" onsubmit="return confirm('Herinneringen nu echt versturen (via de wachtrij)?')"><?= csrf_input() ?><input type="hidden" name="actie" value="herinneringen_draai">
        <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Nu draaien</button></form>
    </div>
    <?php if ($herinnering_resultaat !== null): ?>
    <div style="margin-top:14px;font-size:13px">
      <div style="font-weight:600;margin-bottom:6px"><?= $herinnering_echt ? 'Gedraaid' : 'Proefrun' ?> op <?= date('d-m-Y H:i') ?></div>
      <?php foreach ($herinnering_resultaat as $hs => $hr): ?>
      <div style="padding:8px 0;border-bottom:1px solid var(--rand)">
        <div><strong><?= h($hr['naam']) ?></strong>
          <?php if (!$hr['aan']): ?><span class="badge">uit</span>
          <?php else: ?> — <?= (int)$hr['kandidaten'] ?> kandidaat/-aten, <?= (int)$hr['verstuurd'] ?> <?= $herinnering_echt ? 'in de wachtrij' : 'zou versturen' ?>, <?= (int)$hr['overgeslagen'] ?> overgeslagen<?php endif; ?>
        </div>
        <?php if (!empty($hr['lijst'])): ?>
        <div style="color:var(--tekst-zacht);margin-top:4px">
          <?php foreach ($hr['lijst'] as $hl): ?>
            <span style="display:inline-block;margin:2px 8px 2px 0"><?= h($hl['naam'] ?: ('#' . $hl['sol_id'])) ?>: <em><?= h($hl['actie']) ?></em></span>
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
<div class="ds-kaart" style="margin-bottom:14px">
  <div class="bh-kaartkop"><h2>Database</h2></div>
  <div class="bh-kaartbody">
    <?php if ($db_fout): ?>
      <div style="color:var(--rood)">Kan de database niet lezen: <?= h($db_fout) ?></div>
    <?php else: ?>
      <?php if ($gezondheid !== null): ?>
        <?php if ($gezondheid['ok']): ?>
          <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--groen-bg);border:1px solid var(--groen);border-radius:8px;margin-bottom:12px">
            <span class="bh-healthicoon"><?= icoon('vink', 18) ?></span>
            <span style="font-size:13px;color:var(--groen)"><strong>Database in orde.</strong> Alle verwachte tabellen en kolommen zijn aanwezig.</span>
          </div>
        <?php else: ?>
          <div style="padding:10px 14px;background:var(--oranje-bg);border:1px solid var(--oranje);border-radius:8px;margin-bottom:12px">
            <div style="font-size:13px;color:var(--oranje);font-weight:600;margin-bottom:4px">Er ontbreekt iets in de database</div>
            <?php if (!empty($gezondheid['ontbrekende_kolommen'])): ?>
              <div style="font-size:12px;color:var(--tekst-zacht)">Ontbrekende kolommen: <?= h(implode(', ', $gezondheid['ontbrekende_kolommen'])) ?></div>
            <?php endif; ?>
            <div style="font-size:12px;color:var(--tekst-zacht);margin-top:4px">Klik op "Databasecontrole uitvoeren" hieronder om dit automatisch te herstellen.</div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
      <p style="font-size:12.5px;color:var(--tekst-zacht);margin-bottom:12px">
        Het systeem controleert en herstelt het databaseschema automatisch bij elk bezoek. Ontbrekende tabellen
        en kolommen worden vanzelf aangemaakt. Klopt er toch iets niet na een upload? Klik dan op
        "Databasecontrole uitvoeren".
      </p>
      <?php foreach ($verwacht as $groep => $tabellen): ?>
      <div style="margin-bottom:10px">
        <div style="font-size:12px;font-weight:600;color:var(--tekst-zacht);margin-bottom:4px"><?= h($groep) ?></div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach ($tabellen as $t): $ok = in_array($t, $aanwezig, true); ?>
          <span style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;padding:4px 10px;border-radius:20px;
                       background:<?= $ok ? 'var(--groen-bg)' : 'var(--grijs-bg)' ?>;
                       color:<?= $ok ? 'var(--groen)' : 'var(--grijs)' ?>;
                       border:1px solid <?= $ok ? 'var(--groen)' : 'var(--rand)' ?>">
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
  <div style="padding:0 0 4px">
    <p style="font-size:12.5px;color:var(--tekst-zacht);margin-bottom:12px">
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
      <div style="color:var(--tekst-zacht)">Geen changelog beschikbaar.</div>
    <?php else: ?>
      <?php foreach ($changelog as $versie => $c): ?>
      <details <?= $versie === APP_VERSIE ? 'open' : '' ?> style="border-bottom:1px solid var(--rand-licht);padding:10px 0">
        <summary style="cursor:pointer;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span style="font-weight:700;color:var(--navy);font-size:14px"><?= h($versie) ?></span>
          <?php if ($versie === APP_VERSIE): ?>
            <span style="font-size:10px;font-weight:700;color:#fff;background:var(--groen);padding:2px 8px;border-radius:4px">HUIDIG</span>
          <?php endif; ?>
          <span style="font-size:13px;color:var(--tekst)"><?= h($c['titel'] ?? '') ?></span>
          <span style="flex:1"></span>
          <span style="font-size:11px;color:var(--grijs)"><?= h($c['datum'] ?? '') ?></span>
        </summary>
        <ul style="margin:8px 0 0;padding-left:20px;font-size:12.5px;line-height:1.8;color:var(--tekst-zacht)">
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

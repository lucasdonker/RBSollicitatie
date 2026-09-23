<?php
/**
 * Paal 13 — Hulp: rondleidingen en handleidingen in het beheer.
 *
 *   /admin/handleiding.php                      → overzicht: rondleidingen + handleidingen
 *   /admin/handleiding.php?doc=beheer           → een handleiding lezen, met inhoudsopgave en zoeken
 *   /admin/handleiding.php?doc=beheer&word=1    → de Word-versie downloaden (uitdelen, afdrukken)
 *
 * De map docs/ is afgeschermd (docs/.htaccess); alleen dit scherm leest eruit, achter de
 * login en via de whitelist + rechtencheck in includes/hulp.php. De HTML wordt gemaakt uit
 * de Markdown met docs/handleiding/maak_export.ps1 (pandoc) en staat in Git; hier wordt
 * niets omgezet, alleen in hoofdstukken geknipt (hulp_handleiding_delen()).
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_login();
require_once __DIR__ . '/../includes/beheer/laden.php';

$doc = (string)($_GET['doc'] ?? '');

// ── Word-versie ───────────────────────────────────────────────
if ($doc !== '' && !empty($_GET['word'])) {
    $pad = hulp_handleiding_pad($doc, 'docx');
    if ($pad === null) { pagina_404(); exit; }
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . basename($pad) . '"');
    header('Content-Length: ' . filesize($pad));
    header('X-Robots-Tag: noindex, nofollow');
    readfile($pad);
    exit;
}

// ── Eén handleiding lezen ─────────────────────────────────────
if ($doc !== '') {
    $pad = hulp_handleiding_pad($doc);
    if ($pad === null) { pagina_404(); exit; }
    $info   = hulp_handleidingen()[$doc];
    $delen  = hulp_handleiding_delen((string)file_get_contents($pad));
    $heeft_word = hulp_handleiding_pad($doc, 'docx') !== null;

    $pagina_titel = $info['titel'];
    $actieve_nav  = 'handleiding';
    $pagina_breed = true;
    require __DIR__ . '/layout_top.php';
    ?>
    <div class="bh-hb">
      <aside class="bh-hb-zij" aria-label="Inhoud van deze handleiding">
        <div class="bh-hb-zij-binnen">
          <a class="bh-hb-terug" href="<?= APP_URL ?>/admin/handleiding.php"><?= icoon('terug', 14) ?> Alle handleidingen</a>
          <label class="bh-hb-zoek">
            <span class="bh-hb-zoek-label">Zoek in deze handleiding</span>
            <span class="bh-hb-zoek-veld">
              <?= icoon('zoek', 15) ?>
              <input type="search" id="hb-zoek" autocomplete="off" placeholder="Bijv. bellijst, seintje, wachtwoord">
            </span>
          </label>
          <p class="bh-hb-zoek-uitkomst" id="hb-uitkomst" aria-live="polite"></p>
          <details class="bh-hb-inhoud" open>
            <summary>Inhoud</summary>
            <ol>
              <?php foreach ($delen['hoofdstukken'] as $hs): ?>
                <li><a href="#<?= h($hs['id']) ?>" data-hb-toc="<?= h($hs['id']) ?>"><?= h($hs['titel']) ?></a></li>
              <?php endforeach; ?>
            </ol>
          </details>
          <?php if ($heeft_word): ?>
            <a class="bh-hb-word" href="?doc=<?= h($doc) ?>&amp;word=1"><?= icoon('download', 14) ?> Word-versie (afdrukken, uitdelen)</a>
          <?php endif; ?>
        </div>
      </aside>

      <article class="bh-hb-tekst" id="hb-tekst">
        <?php if ($delen['intro'] !== ''): ?>
          <div class="bh-hb-intro"><?= $delen['intro'] /* eigen, gegenereerde HTML uit de repo */ ?></div>
        <?php endif; ?>

        <?php foreach ($delen['hoofdstukken'] as $hs):
          preg_match_all('~<h[34][^>]*\bid="([^"]+)"~', $hs['html'], $_sub);
          $schermen = function_exists('rl_schermen_bij_hoofdstuk')
              ? rl_schermen_bij_hoofdstuk($doc, array_merge([$hs['id']], $_sub[1])) : [];
        ?>
          <section class="bh-hb-hoofdstuk" id="<?= h($hs['id']) ?>" aria-labelledby="hk-<?= h($hs['id']) ?>" data-hb-hoofdstuk>
            <h2 id="hk-<?= h($hs['id']) ?>"><?= h($hs['titel']) ?></h2>
            <?php if ($schermen): ?>
              <div class="bh-hb-toon">
                <?php foreach ($schermen as $sc): ?>
                  <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($sc['url']) ?>">
                    <?= icoon('rondleiding', 14) ?> Laat zien op het scherm: <?= h($sc['titel']) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?= $hs['html'] /* eigen, gegenereerde HTML uit de repo */ ?>
          </section>
        <?php endforeach; ?>
        <p class="bh-hb-niets" id="hb-niets" hidden>Niets gevonden. Probeer een ander woord, of kijk in de <a href="#veelgestelde-vragen">veelgestelde vragen</a>.</p>
      </article>
    </div>
    <script src="<?= APP_URL ?>/assets/js/handleiding.js?v=<?= @filemtime(__DIR__ . '/../assets/js/handleiding.js') ?>"></script>
    <?php
    require __DIR__ . '/layout_bottom.php';
    exit;
}

// ── Overzicht ─────────────────────────────────────────────────
$pagina_titel = 'Hulp en handleidingen';
$actieve_nav  = 'handleiding';
$routes       = function_exists('rl_routes_zichtbaar') ? rl_routes_zichtbaar() : [];
$docs         = hulp_handleidingen_zichtbaar();
require __DIR__ . '/layout_top.php';

/* Veel gezocht: de hoofdstukken die een nieuw bestuurslid het eerst nodig heeft. Een
   link naar een hoofdstuk dat (nog) niet bestaat, landt gewoon bovenaan de handleiding. */
$snel = [
    ['Aan de slag',                   'beheer', 'aan-de-slag'],
    ['Het seizoen in stappen',        'beheer', 'het-seizoen-in-stappen'],
    ['De fases en wat er automatisch gebeurt', 'beheer', 'de-fases-en-wat-er-automatisch-gebeurt'],
    ['Veelgestelde vragen',           'beheer', 'veelgestelde-vragen'],
    ['Woordenlijst',                  'beheer', 'woordenlijst'],
];
?>
<div class="bh-hub">
  <?php if ($routes): ?>
  <section class="ds-kaart bh-hub-routes" aria-labelledby="hub-routes">
    <header class="bh-kaartkop">
      <h2 id="hub-routes"><?= icoon('rondleiding', 16) ?> Rondleidingen</h2>
    </header>
    <div class="bh-kaartbody">
      <p class="bh-hub-uitleg">Nieuw in het beheer? Een rondleiding loopt met je langs de echte schermen en wijst aan wat waar staat en wat jij er moet doen. Je kunt altijd stoppen met <kbd>Esc</kbd> en met de pijltjestoetsen door de stappen bladeren.</p>
      <ul class="bh-hub-lijst">
        <?php foreach ($routes as $r): ?>
        <li class="bh-hub-route">
          <span class="bh-hub-route-tekst">
            <strong><?= h($r['titel']) ?></strong>
            <span><?= h($r['voor']) ?></span>
            <span class="bh-hub-route-meta"><?= bh_meta([$r['schermen'] . ($r['schermen'] === 1 ? ' scherm' : ' schermen'), $r['stappen'] . ' stappen']) ?></span>
          </span>
          <a class="ds-btn ds-btn-primair ds-btn-sm" href="<?= h($r['url']) ?>">Start</a>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="bh-hub-uitleg">Alleen één scherm uitgelegd krijgen? Klik op dat scherm rechtsboven op het vraagteken en kies <strong>Rondleiding van dit scherm</strong>.</p>
    </div>
  </section>
  <?php endif; ?>

  <section class="ds-kaart bh-hub-docs" aria-labelledby="hub-docs">
    <header class="bh-kaartkop">
      <h2 id="hub-docs"><?= icoon('boek', 16) ?> Handleidingen</h2>
    </header>
    <div class="bh-kaartbody">
      <p class="bh-hub-uitleg">Het naslagwerk: per doelgroep, scherm voor scherm, met een zoekveld. Van elke handleiding is er ook een Word-versie om af te drukken of uit te delen.</p>
      <ul class="bh-hub-lijst">
        <?php foreach ($docs as $sleutel => $d): ?>
        <li class="bh-hub-doc">
          <span class="bh-hub-doc-icoon"><?= icoon('document', 18) ?></span>
          <span class="bh-hub-route-tekst">
            <a href="?doc=<?= h($sleutel) ?>"><strong><?= h($d['titel']) ?></strong></a>
            <span><?= h($d['voor']) ?></span>
          </span>
          <?php if (hulp_handleiding_pad($sleutel, 'docx') !== null): ?>
            <a class="bh-hub-word" href="?doc=<?= h($sleutel) ?>&amp;word=1" aria-label="Word-versie van <?= h($d['titel']) ?>"><?= icoon('download', 14) ?> Word</a>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="ds-kaart bh-hub-snel" aria-labelledby="hub-snel">
    <header class="bh-kaartkop">
      <h2 id="hub-snel"><?= icoon('bliksem', 16) ?> Veel gezocht</h2>
    </header>
    <div class="bh-kaartbody">
      <ul class="bh-hub-snellijst">
        <?php foreach ($snel as $s): if (!isset($docs[$s[1]])) continue; ?>
          <li><a href="?doc=<?= h($s[1]) ?>#<?= h($s[2]) ?>"><?= icoon('pijl-rechts', 14) ?> <?= h($s[0]) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

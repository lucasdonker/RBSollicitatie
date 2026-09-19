<?php
/**
 * Paal 13 — Handleidingen lezen in het beheer.
 *
 * De map docs/ is afgeschermd (docs/.htaccess), zodat het runbook en de handleidingen niet
 * openbaar op het web staan. Dit scherm serveert ze achter de beheer-login:
 *   /admin/handleiding.php            → overzicht
 *   /admin/handleiding.php?doc=beheer → de HTML-versie (met ankers, zie includes/hulp.php)
 *
 * De HTML wordt gemaakt uit de Markdown met docs/handleiding/maak_export.ps1 (pandoc) en staat
 * in Git; er wordt hier dus niets omgezet.
 */
require_once __DIR__ . '/../includes/functions.php';
vereist_login();

$map = dirname(__DIR__) . '/docs/handleiding';
$doc = (string)($_GET['doc'] ?? '');

// ── Eén handleiding tonen ─────────────────────────────────────
if ($doc !== '') {
    // Whitelist én rechtencheck zitten in includes/hulp.php; null = onbekend of niet toegestaan.
    $bestand = hulp_handleiding_bestand($doc);
    $pad     = $bestand !== null ? $map . '/' . $bestand . '.html' : null;
    if ($pad === null || !is_file($pad)) {
        pagina_404();
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    readfile($pad);
    exit;
}

// ── Overzicht ─────────────────────────────────────────────────
$pagina_titel = 'Handleidingen';
$actieve_nav  = 'handleiding';
require __DIR__ . '/layout_top.php';
?>
<div class="container">
  <div class="card">
    <div class="card-body">
      <p style="font-size:13px;color:var(--tekst-zacht);line-height:1.7;margin-bottom:16px">
        De uitleg bij het systeem. Het vraagteken rechtsboven op elk scherm opent de korte uitleg
        van dat scherm met een link naar het juiste hoofdstuk hieronder. Van elke handleiding staat
        ook een Word-versie in de map <code>docs/handleiding/word/</code> van de broncode, om uit te
        delen of af te drukken.
      </p>
      <div style="display:flex;flex-direction:column;gap:2px">
        <?php foreach (hulp_handleidingen_zichtbaar() as $sleutel => $h): ?>
        <a href="<?= APP_URL ?>/admin/handleiding.php?doc=<?= h($sleutel) ?>" target="_blank" rel="noopener"
           style="display:flex;align-items:flex-start;gap:12px;padding:12px 8px;border-bottom:1px solid var(--rand);text-decoration:none;color:inherit">
          <span style="color:var(--navy);flex-shrink:0;margin-top:2px"><?= icoon('document', 18) ?></span>
          <span>
            <span style="display:block;font-size:14px;font-weight:600;color:var(--navy)"><?= h($h['titel']) ?></span>
            <span style="display:block;font-size:12px;color:var(--tekst-zacht);margin-top:2px"><?= h($h['voor']) ?></span>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

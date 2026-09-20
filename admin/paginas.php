<?php
/**
 * Beheer — Landingspagina's: overzicht, nieuwe pagina, statistiek en werving.
 * Het bewerken zelf staat in paginas_bewerk.php.
 */
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('paginas', 'lezen');

$pagina_titel = "Landingspagina's";
$actieve_nav  = 'paginas';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $actie = (string)($_POST['actie'] ?? '');

    if ($actie === 'nieuw') {
        vereis_recht('paginas', 'bewerken');
        $res = pag_opslaan(['titel' => $_POST['titel'] ?? '', 'slug' => $_POST['slug'] ?? '', 'status' => 'concept']);
        if ($res['ok']) redirect(APP_URL . '/admin/paginas_bewerk.php?id=' . $res['id']);
        flash('fout', $res['fout']);
    } elseif ($actie === 'verwijder') {
        vereis_recht('paginas', 'verwijderen');
        $weg = pag_verwijder((int)($_POST['id'] ?? 0));
        flash($weg ? 'succes' : 'fout', $weg ? 'De pagina is verwijderd.' : 'Verwijderen is niet gelukt.');
    } elseif ($actie === 'migreer') {
        vereis_recht('paginas', 'bewerken');
        $droog = empty($_POST['echt']);
        $r = pag_migreer_van_landing($droog);
        $tekst = $droog
            ? 'Proef: ' . $r['paginas'] . " pagina('s) en " . $r['events'] . ' gebeurtenis(sen) zouden worden overgezet.'
            : $r['paginas'] . " pagina('s) en " . $r['events'] . ' gebeurtenis(sen) overgezet.';
        if ($r['overgeslagen']) $tekst .= ' Overgeslagen: ' . implode(', ', $r['overgeslagen']) . '.';
        flash($r['ok'] ? 'succes' : 'fout', $r['ok'] ? $tekst : $r['fout']);
    }
    redirect(APP_URL . '/admin/paginas.php');
}

$paginas = pag_alle();
$stats   = pag_stats_bulk(array_column($paginas, 'id'));
$werving = pag_werving_overzicht();
$mag_bewerken = mag('paginas', 'bewerken');

// Werkt de nette URL? Bij een ZIP-deploy blijft de .htaccess van de server staan en ontbreekt
// de regel; /p.php?s=... werkt dan nog wel, dus dat is geen storing maar wel goed om te weten.
$htaccess = @file_get_contents(dirname(__DIR__) . '/.htaccess');
$rewrite_ok = $htaccess !== false && str_contains($htaccess, '^p/');

require __DIR__ . '/layout_top.php';
?>
<div class="pg-wrap">
  <div class="pg-titelrij">
    <div>
      <h1 class="pg-h1">Landingspagina's</h1>
      <p class="pg-sub">Eigen wervingspagina's per doelgroep, met het aanmeldformulier erin.</p>
    </div>
    <?php if ($mag_bewerken): ?>
    <button type="button" class="ds-btn ds-btn-primair" data-open="pg-nieuw"><?= icoon('plus', 18) ?> Nieuwe pagina</button>
    <?php endif; ?>
  </div>

  <?php toon_flash(); ?>

  <?php if (!$rewrite_ok): ?>
  <div class="ds-melding ds-melding-warn" role="status">
    <div>
      <strong>De nette adressen werken mogelijk niet.</strong>
      De regel voor <code>/p/&lt;naam&gt;</code> ontbreekt in het <code>.htaccess</code>-bestand in de hoofdmap.
      Pagina's blijven bereikbaar via <code>/p.php?s=&lt;naam&gt;</code>. Voeg deze regels toe bij de andere RewriteRules:
      <pre class="pg-code">RewriteRule ^p/([a-z0-9\-]{1,80})/?$ p.php?s=$1 [L,QSA]
RewriteRule ^p/([a-z0-9\-]{1,80})/([A-Za-z0-9._\-]+)$ uploads/paginas/$1/assets/$2 [L]</pre>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($mag_bewerken && function_exists('pag_migratie_nodig') && pag_migratie_nodig()): ?>
  <div class="ds-melding ds-melding-info" role="status">
    <div>
      <strong>Er staan nog pagina's in de oude module.</strong>
      Zet ze over naar het nieuwe beheer. De statistiek gaat mee en de oude gegevens blijven staan.
      <div class="pg-knoprij">
        <form method="post"><?= csrf_input() ?><input type="hidden" name="actie" value="migreer">
          <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Eerst een proef draaien</button>
        </form>
        <form method="post"><?= csrf_input() ?><input type="hidden" name="actie" value="migreer"><input type="hidden" name="echt" value="1">
          <button type="submit" class="ds-btn ds-btn-primair ds-btn-sm">Nu overzetten</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($mag_bewerken): ?>
  <div class="pg-paneel" id="pg-nieuw" hidden>
    <form method="post" class="pg-nieuwform">
      <?= csrf_input() ?><input type="hidden" name="actie" value="nieuw">
      <div class="ds-veld">
        <label class="ds-label" for="pg-titel">Titel</label>
        <input class="ds-input" type="text" id="pg-titel" name="titel" required placeholder="Word lifeguard — zomer 2027">
      </div>
      <div class="ds-veld">
        <label class="ds-label" for="pg-slug">Adres</label>
        <input class="ds-input" type="text" id="pg-slug" name="slug" required placeholder="zomer-2027" pattern="[a-z0-9\-]+" aria-describedby="pg-slug-hint">
        <p class="ds-veld-hint" id="pg-slug-hint"><?= h(APP_URL) ?>/p/<span id="pg-slug-voor">…</span> — kleine letters, cijfers en koppeltekens.</p>
      </div>
      <div class="pg-knoprij">
        <button type="submit" class="ds-btn ds-btn-primair">Aanmaken</button>
        <button type="button" class="ds-btn ds-btn-zacht" data-sluit="pg-nieuw">Annuleren</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if (!$paginas): ?>
    <div class="ds-kaart ds-kaart-p ds-leeg">
      <p>Er is nog geen landingspagina. Maak er een aan en upload het ontwerp als HTML-bestand.</p>
    </div>
  <?php else: ?>
    <ul class="pg-lijst">
      <?php foreach ($paginas as $p): $s = $stats[(int)$p['id']]; ?>
      <li class="ds-kaart pg-rij">
        <div class="pg-rij-hoofd">
          <a class="pg-rij-titel" href="<?= APP_URL ?>/admin/paginas_bewerk.php?id=<?= (int)$p['id'] ?>"><?= h((string)$p['titel']) ?></a>
          <?php
            $badge = ['gepubliceerd' => 'ds-badge-ok', 'alleen_admin' => 'ds-badge-warn', 'concept' => 'ds-badge-grijs'][$p['status']] ?? 'ds-badge-grijs';
          ?>
          <span class="ds-badge <?= $badge ?>"><?= h(pag_statussen()[$p['status']] ?? (string)$p['status']) ?></span>
          <?php if (empty($p['actieve_versie_id'])): ?>
            <span class="ds-badge ds-badge-error">Geen ontwerp</span>
          <?php endif; ?>
        </div>
        <p class="pg-rij-meta">
          /p/<?= h((string)$p['slug']) ?><?= $p['doelgroep'] ? ' · ' . h((string)$p['doelgroep']) : '' ?>
        </p>
        <dl class="pg-cijfers">
          <div><dt>Bezoekers</dt><dd><?= (int)$s['uniek'] ?></dd></div>
          <div><dt>Bezoeken</dt><dd><?= (int)$s['bezoeken'] ?></dd></div>
          <div><dt>Aanmeldingen</dt><dd><?= (int)$s['klaar'] ?></dd></div>
          <div><dt>Omzetting</dt><dd><?= $s['uniek'] > 0 ? (int)round($s['klaar'] / $s['uniek'] * 100) . '%' : '—' ?></dd></div>
        </dl>
        <div class="pg-knoprij">
          <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= APP_URL ?>/admin/paginas_bewerk.php?id=<?= (int)$p['id'] ?>">Beheren</a>
          <?php if (!empty($p['actieve_versie_id'])): ?>
          <a class="ds-btn ds-btn-zacht ds-btn-sm" href="<?= APP_URL ?>/p/<?= h((string)$p['slug']) ?>" target="_blank" rel="noopener">Bekijken</a>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($werving): ?>
  <section class="ds-kaart ds-kaart-p pg-werving">
    <h2 class="pg-h2">Waar komen aanmeldingen vandaan?</h2>
    <p class="pg-sub">Per bron het aantal aanmeldingen en hoeveel daarvan uiteindelijk zijn aangenomen.</p>
    <div class="tabel-scroll">
      <table class="pg-tabel">
        <caption class="pg-verborgen">Aanmeldingen en aangenomen sollicitanten per bron</caption>
        <thead>
          <tr><th scope="col">Bron</th><th scope="col" class="pg-rechts">Aanmeldingen</th><th scope="col" class="pg-rechts">Aangenomen</th><th scope="col" class="pg-rechts">Aandeel</th></tr>
        </thead>
        <tbody>
          <?php foreach ($werving as $w): ?>
          <tr>
            <td><?= h((string)$w['bron']) ?></td>
            <td class="pg-rechts"><?= (int)$w['aanmeldingen'] ?></td>
            <td class="pg-rechts"><?= (int)$w['aangenomen'] ?></td>
            <td class="pg-rechts"><?= $w['aanmeldingen'] > 0 ? round($w['aangenomen'] / $w['aanmeldingen'] * 100) . '%' : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>
</div>

<script>
(function () {
  document.querySelectorAll('[data-open]').forEach(function (knop) {
    knop.addEventListener('click', function () {
      var doel = document.getElementById(knop.dataset.open);
      if (!doel) return;
      doel.hidden = false;
      var eerste = doel.querySelector('input, select, textarea');
      if (eerste) eerste.focus();
    });
  });
  document.querySelectorAll('[data-sluit]').forEach(function (knop) {
    knop.addEventListener('click', function () {
      var doel = document.getElementById(knop.dataset.sluit);
      if (doel) doel.hidden = true;
    });
  });
  var titel = document.getElementById('pg-titel');
  var slug = document.getElementById('pg-slug');
  var voor = document.getElementById('pg-slug-voor');
  function netjes(t) { return t.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); }
  if (titel && slug) {
    titel.addEventListener('input', function () {
      if (!slug.dataset.aangeraakt) { slug.value = netjes(titel.value); if (voor) voor.textContent = slug.value || '…'; }
    });
    slug.addEventListener('input', function () {
      slug.dataset.aangeraakt = '1';
      if (voor) voor.textContent = netjes(slug.value) || '…';
    });
  }
})();
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>

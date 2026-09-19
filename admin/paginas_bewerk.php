<?php
/**
 * Beheer — één landingspagina: gegevens, ontwerp uploaden, scanrapport, versies en afbeeldingen.
 */
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('paginas', 'lezen');

$id = (int)($_GET['id'] ?? 0);
$pagina = pag_via_id($id);
if (!$pagina) { flash('fout', 'Die pagina bestaat niet (meer).'); redirect(APP_URL . '/admin/paginas.php'); }

$pagina_titel = (string)$pagina['titel'];
$actieve_nav  = 'paginas';
$toon_versie  = (int)($_GET['versie'] ?? 0);   // scanrapport van een net geüploade versie

if (upload_post_te_groot()) {
    // Te groot voor post_max_size: PHP gooide de hele POST weg (ook het CSRF-token).
    flash('fout', 'Het bestand is te groot voor de server (maximaal ' . pag_bytes_tekst(upload_server_max_bytes()) . ' per upload). Verhoog upload_max_filesize en post_max_size in Plesk, of maak het bestand kleiner.');
    redirect(APP_URL . '/admin/paginas_bewerk.php?id=' . $id);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    vereis_recht('paginas', 'bewerken');
    $actie = (string)($_POST['actie'] ?? '');
    $terug = APP_URL . '/admin/paginas_bewerk.php?id=' . $id;

    if ($actie === 'gegevens') {
        $res = pag_opslaan($_POST, $id);
        flash($res['ok'] ? 'succes' : 'fout', $res['ok'] ? 'De gegevens zijn opgeslagen.' : $res['fout']);
        if ($res['ok']) { $p = pag_via_id($id); $terug = APP_URL . '/admin/paginas_bewerk.php?id=' . $id; }

    } elseif ($actie === 'upload') {
        $res = pag_import_toepassen($pagina, $_FILES['ontwerp'] ?? [], ['ontvlecht' => !empty($_POST['ontvlecht'])]);
        if ($res['ok']) {
            $los = $res['ontvlecht']['bestanden'] ?? [];
            flash('succes', 'Het ontwerp is ingelezen'
                . ($los ? ', met ' . count($los) . ' losgemaakte afbeelding(en)' : '')
                . '. Lees het rapport hieronder en bevestig om het live te zetten.');
            $terug .= '&versie=' . $res['versie_id'];
        } else {
            flash('fout', $res['fout']);
        }

    } elseif ($actie === 'bevestig') {
        $vid = (int)($_POST['versie_id'] ?? 0);
        if (empty($_POST['gelezen'])) {
            flash('fout', 'Zet een vinkje bij "Ik heb het rapport gelezen" om het ontwerp live te zetten.');
            $terug .= '&versie=' . $vid;
        } else {
            $hosts = pag_csp_lijst_opschonen(implode("\n", (array)($_POST['host'] ?? [])));
            if ($hosts !== '') {
                $huidig = pag_csp_lijst_opschonen((string)$pagina['csp_extra'] . "\n" . $hosts);
                db()->prepare("UPDATE paginas SET csp_extra = ? WHERE id = ?")->execute([$huidig, $id]);
                audit_log('csp_host_toegestaan', 'pagina', $id, ['hosts' => $hosts]);
            }
            $live = pag_scan_bevestig($id, $vid);
            flash($live ? 'succes' : 'fout', $live
                ? 'Het ontwerp staat live op ' . APP_URL . '/p/' . $pagina['slug'] . '.'
                : 'Het ontwerp kon niet live worden gezet.');
        }

    } elseif ($actie === 'terugrollen') {
        $ok = pag_versie_activeer($id, (int)($_POST['versie_id'] ?? 0));
        flash($ok ? 'succes' : 'fout', $ok ? 'Die versie staat weer live.' : 'Terugrollen is niet gelukt.');

    } elseif ($actie === 'asset') {
        $res = pag_asset_opslaan((string)$pagina['slug'], $_FILES['afbeelding'] ?? []);
        flash($res['ok'] ? 'succes' : 'fout', $res['ok'] ? 'De afbeelding staat klaar als ' . $res['naam'] : $res['fout']);

    } elseif ($actie === 'asset_weg') {
        pag_asset_verwijder((string)$pagina['slug'], (string)($_POST['naam'] ?? ''));
        flash('succes', 'De afbeelding is verwijderd.');
    }
    redirect($terug);
}

$mag_bewerken = mag('paginas', 'bewerken');
$versies = pag_versie_lijst($id);
$actief  = (int)($pagina['actieve_versie_id'] ?? 0);
$assets  = pag_asset_lijst((string)$pagina['slug']);
$stat    = pag_stats($id);

// Welk scanrapport tonen we? Dat van de net geüploade versie, of van de nieuwste onbevestigde.
$rapport_versie = null;
if ($toon_versie > 0) {
    $rapport_versie = pag_versie_rij($toon_versie);
    if ($rapport_versie && (int)$rapport_versie['pagina_id'] !== $id) $rapport_versie = null;
}
if (!$rapport_versie) {
    foreach ($versies as $v) { if ($v['scan_bevestigd_op'] === null) { $rapport_versie = $v; break; } }
}

require __DIR__ . '/layout_top.php';
?>
<div class="pg-wrap">
  <a class="pg-terug" href="<?= APP_URL ?>/admin/paginas.php"><?= icoon('terug', 16) ?> Alle landingspagina's</a>

  <div class="pg-titelrij">
    <div>
      <h1 class="pg-h1"><?= h((string)$pagina['titel']) ?></h1>
      <p class="pg-sub">
        <a href="<?= APP_URL ?>/p/<?= h((string)$pagina['slug']) ?>" target="_blank" rel="noopener"><?= h(APP_URL) ?>/p/<?= h((string)$pagina['slug']) ?></a>
      </p>
    </div>
    <?php $badge = ['gepubliceerd' => 'ds-badge-ok', 'alleen_admin' => 'ds-badge-warn', 'concept' => 'ds-badge-grijs'][$pagina['status']] ?? 'ds-badge-grijs'; ?>
    <span class="ds-badge <?= $badge ?>"><?= h(pag_statussen()[$pagina['status']] ?? '') ?></span>
  </div>

  <?php toon_flash(); ?>

  <dl class="pg-cijfers pg-cijfers-groot">
    <div><dt>Unieke bezoekers</dt><dd><?= (int)$stat['uniek'] ?></dd></div>
    <div><dt>Bezoeken</dt><dd><?= (int)$stat['bezoeken'] ?></dd></div>
    <div><dt>Aanmeldingen</dt><dd><?= (int)$stat['klaar'] ?></dd></div>
    <div><dt>Omzetting</dt><dd><?= $stat['uniek'] > 0 ? (int)round($stat['klaar'] / $stat['uniek'] * 100) . '%' : '—' ?></dd></div>
  </dl>

  <?php if ($rapport_versie && $mag_bewerken): ?>
    <?php require __DIR__ . '/_paginas_scan.php'; ?>
  <?php endif; ?>

  <div class="pg-kolommen">
    <div class="pg-kolom">

      <!-- Ontwerp uploaden -->
      <section class="ds-kaart ds-kaart-p">
        <h2 class="pg-h2">Ontwerp</h2>
        <p class="pg-sub">Upload het HTML-bestand van het ontwerp. Zet <code>[[formulier]]</code> in het ontwerp op de plek waar het aanmeldformulier moet komen.</p>

        <?php if ($mag_bewerken): ?>
        <form method="post" enctype="multipart/form-data" class="pg-uploadform">
          <?= csrf_input() ?><input type="hidden" name="actie" value="upload">
          <div class="ds-veld">
            <label class="ds-label" for="pg-ontwerp">HTML-bestand</label>
            <input class="ds-input pg-bestand" type="file" id="pg-ontwerp" name="ontwerp" accept=".html,.htm,text/html" required aria-describedby="pg-ontwerp-hint">
            <p class="ds-veld-hint" id="pg-ontwerp-hint">Eén .html-bestand, maximaal <?= h(pag_bytes_tekst(pag_max_html_bytes())) ?>. Je krijgt eerst een rapport te zien voordat er iets live gaat.</p>
          </div>

          <div class="pg-vink">
            <input type="checkbox" id="pg-ontvlecht" name="ontvlecht" value="1" checked aria-describedby="pg-ontvlecht-hint">
            <label for="pg-ontvlecht">Ingesloten afbeeldingen losmaken</label>
          </div>
          <p class="ds-veld-hint" id="pg-ontvlecht-hint">Zitten de foto's in het HTML-bestand verwerkt (dat maakt het al snel tien keer zo groot), dan worden ze eruit gehaald en als losse afbeelding bij de pagina gezet. De pagina laadt daardoor veel sneller op een telefoon. Verder blijft het ontwerp ongewijzigd, en in het rapport zie je precies wat er is losgemaakt.</p>

          <button type="submit" class="ds-btn ds-btn-primair"><?= icoon('upload', 18) ?> Inlezen</button>
        </form>
        <?php endif; ?>

        <?php if ($versies): ?>
        <h3 class="pg-h3">Versies</h3>
        <ul class="pg-versies">
          <?php foreach ($versies as $v): $scan = pag_scan_van_versie($v); ?>
          <li class="pg-versie">
            <div>
              <strong>Versie <?= (int)$v['versie'] ?></strong>
              <?php if ((int)$v['id'] === $actief): ?><span class="ds-badge ds-badge-ok">Live</span><?php endif; ?>
              <?php if ($v['scan_bevestigd_op'] === null): ?><span class="ds-badge ds-badge-warn">Niet bevestigd</span><?php endif; ?>
              <p class="pg-rij-meta">
                <?= h(datum_nl((string)$v['aangemaakt_op'])) ?> ·
                <?= $v['bron'] === 'migratie' ? 'overgezet uit de oude module' : h((string)($v['bestandsnaam_origineel'] ?? 'upload')) ?> ·
                <?= h(pag_bytes_tekst((int)$v['grootte'])) ?>
                <?php if (!empty($scan['telling']['hoog'])): ?> · <?= (int)$scan['telling']['hoog'] ?> aandachtspunt(en)<?php endif; ?>
              </p>
            </div>
            <div class="pg-knoprij">
              <a class="ds-btn ds-btn-zacht ds-btn-sm" href="<?= APP_URL ?>/p/<?= h((string)$pagina['slug']) ?>?preview=<?= (int)$v['id'] ?>" target="_blank" rel="noopener">Bekijken</a>
              <?php if ($mag_bewerken && (int)$v['id'] !== $actief && $v['scan_bevestigd_op'] !== null): ?>
              <form method="post" onsubmit="return confirm('Versie <?= (int)$v['versie'] ?> weer live zetten?')">
                <?= csrf_input() ?><input type="hidden" name="actie" value="terugrollen"><input type="hidden" name="versie_id" value="<?= (int)$v['id'] ?>">
                <button type="submit" class="ds-btn ds-btn-secundair ds-btn-sm">Terugzetten</button>
              </form>
              <?php elseif ($mag_bewerken && $v['scan_bevestigd_op'] === null): ?>
              <a class="ds-btn ds-btn-secundair ds-btn-sm" href="?id=<?= $id ?>&versie=<?= (int)$v['id'] ?>">Rapport</a>
              <?php endif; ?>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </section>

      <!-- Afbeeldingen -->
      <section class="ds-kaart ds-kaart-p">
        <h2 class="pg-h2">Afbeeldingen</h2>
        <p class="pg-sub">Verwijs er in het ontwerp naar met alleen de bestandsnaam, bijvoorbeeld <code>&lt;img src="strand.jpg"&gt;</code>.</p>

        <?php if ($mag_bewerken): ?>
        <form method="post" enctype="multipart/form-data" class="pg-uploadform">
          <?= csrf_input() ?><input type="hidden" name="actie" value="asset">
          <div class="ds-veld">
            <label class="ds-label" for="pg-afb">Afbeelding toevoegen</label>
            <input class="ds-input pg-bestand" type="file" id="pg-afb" name="afbeelding" accept="image/jpeg,image/png,image/gif,image/webp" required aria-describedby="pg-afb-hint">
            <p class="ds-veld-hint" id="pg-afb-hint">JPG, PNG, GIF of WebP, maximaal <?= h(pag_bytes_tekst(pag_max_asset_bytes())) ?>.</p>
          </div>
          <button type="submit" class="ds-btn ds-btn-secundair">Toevoegen</button>
        </form>
        <?php endif; ?>

        <?php if ($assets): ?>
        <ul class="pg-assets">
          <?php foreach ($assets as $a): ?>
          <li>
            <code><?= h($a['naam']) ?></code>
            <span class="pg-rij-meta"><?= (int)round($a['grootte'] / 1024) ?> KB</span>
            <?php if ($mag_bewerken): ?>
            <form method="post" onsubmit="return confirm('<?= h($a['naam']) ?> verwijderen?')">
              <?= csrf_input() ?><input type="hidden" name="actie" value="asset_weg"><input type="hidden" name="naam" value="<?= h($a['naam']) ?>">
              <button type="submit" class="ds-btn ds-btn-zacht ds-btn-sm">Verwijderen</button>
            </form>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="pg-rij-meta">Nog geen afbeeldingen bij deze pagina.</p>
        <?php endif; ?>
      </section>
    </div>

    <div class="pg-kolom">
      <!-- Gegevens -->
      <section class="ds-kaart ds-kaart-p">
        <h2 class="pg-h2">Gegevens</h2>
        <form method="post">
          <?= csrf_input() ?><input type="hidden" name="actie" value="gegevens">

          <div class="ds-veld">
            <label class="ds-label" for="f-titel">Titel</label>
            <input class="ds-input" type="text" id="f-titel" name="titel" value="<?= h((string)$pagina['titel']) ?>" required <?= $mag_bewerken ? '' : 'disabled' ?>>
          </div>

          <div class="ds-veld">
            <label class="ds-label" for="f-slug">Adres</label>
            <input class="ds-input" type="text" id="f-slug" name="slug" value="<?= h((string)$pagina['slug']) ?>" required pattern="[a-z0-9\-]+" aria-describedby="f-slug-hint" <?= $mag_bewerken ? '' : 'disabled' ?>>
            <p class="ds-veld-hint" id="f-slug-hint">Wijzig je dit, dan verhuizen de afbeeldingen mee — maar bestaande links en QR-codes werken niet meer.</p>
          </div>

          <div class="ds-veld">
            <label class="ds-label" for="f-doelgroep">Doelgroep</label>
            <input class="ds-input" type="text" id="f-doelgroep" name="doelgroep" value="<?= h((string)($pagina['doelgroep'] ?? '')) ?>" placeholder="bijvoorbeeld: studenten geneeskunde" <?= $mag_bewerken ? '' : 'disabled' ?>>
          </div>

          <div class="ds-veld">
            <label class="ds-label" for="f-bron">Bron-code</label>
            <input class="ds-input" type="text" id="f-bron" name="bron_code" value="<?= h((string)($pagina['bron_code'] ?? '')) ?>" aria-describedby="f-bron-hint" <?= $mag_bewerken ? '' : 'disabled' ?>>
            <p class="ds-veld-hint" id="f-bron-hint">Hiermee herken je later waar een aanmelding vandaan kwam. Laat leeg om het adres te gebruiken.</p>
          </div>

          <div class="ds-veld">
            <label class="ds-label" for="f-status">Status</label>
            <select class="ds-select" id="f-status" name="status" aria-describedby="f-status-hint" <?= $mag_bewerken ? '' : 'disabled' ?>>
              <?php foreach (pag_statussen() as $k => $l): ?>
              <option value="<?= $k ?>"<?= $pagina['status'] === $k ? ' selected' : '' ?>><?= h($l) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="ds-veld-hint" id="f-status-hint">Alleen "Gepubliceerd" is voor iedereen te zien. Bij de andere twee krijgen bezoekers een 404.</p>
          </div>

          <div class="pg-vink">
            <input type="checkbox" id="f-index" name="indexeerbaar" value="1"<?= !empty($pagina['indexeerbaar']) ? ' checked' : '' ?> <?= $mag_bewerken ? '' : 'disabled' ?>>
            <label for="f-index">Mag gevonden worden via Google</label>
          </div>

          <div class="ds-veld">
            <label class="ds-label" for="f-csp">Toegestane externe bronnen</label>
            <textarea class="ds-textarea" id="f-csp" name="csp_extra" rows="3" aria-describedby="f-csp-hint" <?= $mag_bewerken ? '' : 'disabled' ?>><?= h((string)($pagina['csp_extra'] ?? '')) ?></textarea>
            <p class="ds-veld-hint" id="f-csp-hint">Eén hostnaam per regel, bijvoorbeeld <code>cdn.tailwindcss.com</code>. Alles wat hier niet staat, wordt door de browser geblokkeerd. Voeg alleen toe wat het ontwerp echt nodig heeft.</p>
          </div>

          <?php if ($mag_bewerken): ?>
          <button type="submit" class="ds-btn ds-btn-primair">Opslaan</button>
          <?php endif; ?>
        </form>
      </section>

      <!-- Voorbeeld -->
      <?php if ($actief): ?>
      <section class="ds-kaart ds-kaart-p">
        <h2 class="pg-h2">Voorbeeld</h2>
        <iframe class="pg-preview" src="<?= APP_URL ?>/p/<?= h((string)$pagina['slug']) ?>?preview=<?= $actief ?>"
                title="Voorbeeld van <?= h((string)$pagina['titel']) ?>" sandbox="allow-scripts allow-forms" loading="lazy"></iframe>
        <p class="ds-veld-hint">Werkt het voorbeeld niet goed? <a href="<?= APP_URL ?>/p/<?= h((string)$pagina['slug']) ?>?preview=<?= $actief ?>" target="_blank" rel="noopener">Open de pagina in een nieuw tabblad</a>.</p>
      </section>
      <?php endif; ?>

      <?php if (mag('paginas', 'verwijderen')): ?>
      <section class="ds-kaart ds-kaart-p">
        <h2 class="pg-h2">Verwijderen</h2>
        <p class="pg-sub">De pagina, alle versies en de afbeeldingen worden verwijderd. De statistiek blijft anoniem bewaard.</p>
        <form method="post" action="<?= APP_URL ?>/admin/paginas.php" onsubmit="return confirm('<?= h((string)$pagina['titel']) ?> definitief verwijderen?')">
          <?= csrf_input() ?><input type="hidden" name="actie" value="verwijder"><input type="hidden" name="id" value="<?= $id ?>">
          <button type="submit" class="ds-btn ds-btn-gevaar"><?= icoon('prullenbak', 18) ?> Pagina verwijderen</button>
        </form>
      </section>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>

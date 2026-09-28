<?php
/**
 * Documenten uploaden (aangenomen-takenpakket): kopie ID (voor/achter) en kopie bankpas.
 * ?taak=id_kopie|bankpas_kopie — definities in includes/documenten.php (document_taken()).
 *
 * Per vak één klein formulier (bestand kiezen → uploaden). Bestanden gaan versleuteld de kluis in
 * (nooit onversleuteld op schijf) en zijn daarna alleen voor de bevoegde beheerder (penningmeester)
 * te openen. Zolang de penningmeester het document nog niet heeft verwerkt, kan de sollicitant het
 * vervangen of verwijderen. Zelfde bouwstenen als het ondertekenscherm (.ond-*).
 */
require_once __DIR__ . '/../includes/functions.php';

$sol_id = portaal_vereis_login();
$sol = portaal_sollicitant_laden($sol_id);
if (!$sol) { unset($_SESSION['portaal_sol_id']); redirect(APP_URL . '/portaal/'); }
if (($sol['fase'] ?? '') !== 'aangenomen') { flash('info', 'Documenten uploaden kan pas nadat je bent aangenomen.'); redirect(APP_URL . '/portaal/'); }

$taak_slug = (string)($_GET['taak'] ?? '');
$taak = document_taken()[$taak_slug] ?? null;
if (!$taak) pagina_404();
$soorten = document_soorten();
$kluis_ok = function_exists('kluis_beschikbaar') && kluis_beschikbaar();

// ── POST: uploaden of verwijderen ─────────────────────────────
$fout = ''; $succes = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $soort = (string)($_POST['soort'] ?? '');
    if (!isset($taak['soorten'][$soort])) {
        $fout = 'Onbekend documentvak.';
    } elseif (isset($_POST['verwijder'])) {
        $id = (int)($_POST['document_id'] ?? 0);
        if (document_verwijderen_door_sollicitant($id, $sol_id)) $succes = $taak['soorten'][$soort] . ' is verwijderd.';
        else $fout = 'Verwijderen lukte niet (misschien is het document al verwerkt).';
    } else {
        if (!$kluis_ok) {
            $fout = 'De beveiligde opslag is tijdelijk niet beschikbaar. Probeer het later opnieuw of mail ' . portaal_contact_email() . '.';
        } else {
            $extra = null;
            if ($soort === 'id_voor') {
                $extra = in_array($_POST['id_type'] ?? '', ['paspoort', 'id_kaart'], true) ? $_POST['id_type'] : null;
                if ($extra === null) $fout = 'Kies eerst of het een paspoort of een identiteitskaart is.';
            }
            if ($fout === '') {
                $res = document_opslaan($sol_id, $soort, $_FILES['bestand'] ?? [], $extra, $_SERVER['REMOTE_ADDR'] ?? null);
                if ($res['ok']) {
                    $succes = $taak['soorten'][$soort] . ' is veilig ontvangen.';
                    // Ook hier, niet alleen na het tekenen: de volgorde tekenen/uploaden mag niet uitmaken.
                    // De check mailt één keer per loonheffing (gebeurtenis 'penningmeester_gemeld').
                    try { documenten_dossier_compleet_check($sol_id); } catch (\Throwable $e) {}
                } else {
                    $fout = 'Uploaden lukte niet: ' . $res['fout'] . '.';
                }
            }
        }
    }
    if ($fout === '') {
        flash('succes', $succes);
        redirect(APP_URL . '/portaal/document_upload.php?taak=' . urlencode($taak_slug) . (portaal_terug_param() === 'taken' ? '&terug=taken' : ''));
    }
}
// Deze pagina toont de melding in de kaart, bij het document (niet bovenaan via _kop.php).
if ($_f = get_flash()) { if ($_f['type'] === 'succes') $succes = (string)$_f['msg']; else $fout = (string)$_f['msg']; }

// ── Weergave ──────────────────────────────────────────────────
$docs   = documenten_van($sol_id);
$klaar  = documenten_taak_klaar($sol_id, $taak_slug, $docs);
$id_type = (string)($docs['id_voor']['extra'] ?? '');
$maanden = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
$wanneer = function (?string $dt) use ($maanden): string {
    if (!$dt) return '';
    $t = strtotime($dt);
    return date('j', $t) . ' ' . $maanden[(int)date('n', $t) - 1] . ' om ' . date('H:i', $t);
};
$grootte = fn(int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' kB';
$terug_url = portaal_terug_url();

$portaal_titel = $taak['titel'];
$portaal_terug = $terug_url;
require __DIR__ . '/_kop.php';
?>

<div class="ond du">
  <header class="ond-kop">
    <div class="ond-kop-titel">
      <span class="ond-kop-naam"><?= h($taak['titel']) ?></span>
      <span class="ond-kop-sub"><?= $klaar ? 'Ontvangen' : 'Nog te doen' ?> · <?= h($taak['sub']) ?></span>
    </div>
    <span class="ond-chip<?= $klaar ? ' ok' : '' ?>"><span class="ond-chip-stip"></span><?= $klaar ? 'Ontvangen' : 'Nog te doen' ?></span>
  </header>

  <div class="ond-body">
    <div class="ond-links">
      <?php if ($fout !== ''): ?>
        <div class="ds-melding ds-melding-error" role="alert"><?= icoon('let-op', 18) ?> <div><?= h($fout) ?></div></div>
      <?php elseif ($succes !== ''): ?>
        <div class="ds-melding ds-melding-ok" role="status"><?= icoon('vink-cirkel', 18) ?> <div><?= h($succes) ?></div></div>
      <?php endif; ?>

      <div class="ond-doc">
        <span class="ond-doc-titel"><?= h($taak['titel']) ?></span>
        <span class="ond-doc-alinea"><?= h($taak['uitleg']) ?></span>
        <span class="ond-doc-alinea">Maak een scherpe foto bij goed licht, recht van boven, met alle hoeken in beeld. Een foto (jpg, png) of een pdf, maximaal 10 MB.</span>
      </div>

      <?php if (!$kluis_ok): ?>
        <div class="ds-melding ds-melding-warn"><?= icoon('let-op', 18) ?> <div>De beveiligde opslag is tijdelijk niet beschikbaar. Uploaden kan nu niet; probeer het later opnieuw.</div></div>
      <?php endif; ?>

      <?php if ($taak_slug === 'id_kopie'): ?>
        <?php /* De keuze hoort bij het voorkant-formulier (form="du-form-id_voor"), ook al staat hij
                 erboven: zo gaat hij zonder JavaScript gewoon mee met het uploaden. */ ?>
        <div class="ond-kaart" id="idType">
          <span class="ond-kaart-lbl" id="idTypeLbl"><?= icoon('id-kaart', 18) ?>Welk document?</span>
          <div class="ond-lijst" role="radiogroup" aria-labelledby="idTypeLbl">
            <label class="ond-radio"><input type="radio" name="id_type" value="paspoort" form="du-form-id_voor"<?= $id_type === 'paspoort' ? ' checked' : '' ?>><span class="ond-radio-box"></span><span class="ond-radio-tekst">Paspoort — alleen de pagina met je foto</span></label>
            <label class="ond-radio"><input type="radio" name="id_type" value="id_kaart" form="du-form-id_voor"<?= $id_type === 'id_kaart' ? ' checked' : '' ?>><span class="ond-radio-box"></span><span class="ond-radio-tekst">Identiteitskaart — voorkant én achterkant</span></label>
          </div>
          <span class="ond-kaart-tekst">Een rijbewijs is geen geldig identiteitsbewijs voor de loonadministratie.</span>
        </div>
      <?php endif; ?>

      <?php foreach ($taak['soorten'] as $soort => $label):
        $doc = $docs[$soort] ?? null;
        $verwerkt = $doc && ($doc['status'] ?? '') === 'verwerkt';
        /* Het achterkant-vak verbergt pas JavaScript (bij "paspoort"); zonder JS blijft het staan
           met de hint dat het alleen bij een identiteitskaart hoeft. */
        $alleen_idkaart = ($soort === 'id_achter' && !$doc); ?>
        <div class="ond-kaart du-vak<?= $alleen_idkaart ? ' du-alleen-idkaart' : '' ?>" data-soort="<?= h($soort) ?>">
          <span class="ond-kaart-lbl"><?= icoon($doc ? 'vink-cirkel' : 'camera', 18) ?><?= h($label) ?></span>
          <?php if ($alleen_idkaart): ?><span class="ond-kaart-tekst">Alleen nodig bij een identiteitskaart.</span><?php endif; ?>
          <?php if ($doc): ?>
            <div class="du-status">
              <span class="du-status-icoon"><?= icoon($doc['mime'] === 'application/pdf' ? 'document' : 'camera', 20) ?></span>
              <span class="du-status-tekst">
                <span class="du-status-naam"><?= h($doc['bestandsnaam']) ?></span>
                <span class="du-status-sub">Ontvangen <?= h($wanneer($doc['geupload_op'])) ?> · <?= h($grootte((int)$doc['grootte'])) ?><?= $verwerkt ? ' · verwerkt door de penningmeester' : '' ?></span>
              </span>
            </div>
            <?php if ($verwerkt): ?>
              <span class="ond-kaart-tekst">Dit document is verwerkt en kan niet meer gewijzigd worden. Klopt er iets niet? Mail <a href="mailto:<?= h(portaal_contact_email()) ?>"><?= h(portaal_contact_email()) ?></a>.</span>
            <?php endif; ?>
          <?php endif; ?>

          <?php if (!$verwerkt && $kluis_ok): ?>
            <?php /* Werkt zonder JavaScript: het bestandsveld is verplicht en de knop staat altijd aan.
                     document_upload.js voegt alleen het voorbeeld en de vooraf-controle toe. */ ?>
            <form method="POST" enctype="multipart/form-data" class="du-form" id="du-form-<?= h($soort) ?>" data-soort="<?= h($soort) ?>">
              <?= csrf_input() ?>
              <input type="hidden" name="soort" value="<?= h($soort) ?>">
              <input type="hidden" name="terug" value="<?= h(portaal_terug_param()) ?>">
              <label class="du-kies<?= $doc ? ' klein' : '' ?>">
                <input type="file" name="bestand" accept="image/jpeg,image/png,application/pdf" capture="environment" class="du-input" required>
                <span class="du-kies-icoon"><?= icoon('camera', 22) ?></span>
                <span class="du-kies-tekst"><?= $doc ? 'Andere foto kiezen (vervangt de huidige)' : 'Foto maken of bestand kiezen' ?></span>
              </label>
              <div class="du-preview" hidden>
                <img alt="" class="du-preview-img" hidden>
                <span class="du-preview-naam"></span>
              </div>
              <div class="du-acties">
                <button type="submit" class="ds-btn ds-btn-primair du-upload"><span class="btn-tekst"><?= $doc ? 'Vervangen' : 'Uploaden' ?></span><span class="btn-spinner" hidden>Bezig met versleutelen…</span></button>
              </div>
            </form>
            <?php if ($doc):
              $bev = [
                  'knop_open' => 'Verwijderen',
                  'icoon'     => 'prullenbak',
                  'tekst'     => [$label . ' wordt meteen uit de beveiligde opslag gewist. Daarna kun je een nieuw bestand uploaden.'],
                  'knop'      => 'Ja, verwijder dit bestand',
                  'velden'    => ['soort' => $soort, 'verwijder' => '1', 'document_id' => (int)$doc['id'], 'terug' => portaal_terug_param()],
              ];
              require __DIR__ . '/_bevestig.php';
            endif; ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="ond-rechts">
      <div class="ond-info">
        <?= icoon('slot', 20) ?>
        <span>Je bestanden worden direct versleuteld opgeslagen en zijn alleen te openen door de penningmeester, voor de loonadministratie. Elke inzage wordt vastgelegd.<?= $taak_slug === 'bankpas_kopie' ? ' De bankpasfoto wordt na verwerking vernietigd.' : '' ?></span>
      </div>
      <footer class="ond-voet">
        <a href="<?= h($terug_url) ?>" class="ond-knop<?= $klaar ? '' : ' secundair' ?>"><?= $klaar ? 'Klaar, terug naar het overzicht' : 'Later afmaken' ?></a>
      </footer>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/document_upload.js?v=<?= @filemtime(__DIR__ . '/../assets/js/document_upload.js') ?>"></script>
<?php require __DIR__ . '/_voet.php'; ?>

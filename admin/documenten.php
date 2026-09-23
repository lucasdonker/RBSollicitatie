<?php
/**
 * Beheer — Loondossiers: per aangenomen strandwacht het loonheffingsformulier
 * (verzegelde PDF), de kopie van het identiteitsbewijs, de bankpas, en de
 * inschrijving als lid.
 *
 * Dit is het enige scherm met een BSN en kopieën van identiteitsbewijzen erop. Dat
 * moet je zien zonder dat het alarmerend wordt (ontwerpfilosofie §9): één vast kader
 * bovenaan dat uitlegt wat de regels zijn, en verder een rustig scherm. Downloaden
 * gaat altijd via document_download.php, dat elke inzage vastlegt.
 *
 * Recht: module 'documenten' — penningmeester en beheerder. Het bestuur ziet dit niet.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/beheer/laden.php';
vereis_recht('documenten', 'lezen');

$db  = db();
$bhd = huidige_beheerder();

$mag_bewerken    = mag('documenten', 'bewerken');
$mag_verwijderen = mag('documenten', 'verwijderen');

// Wat er met de bankpasfoto gebeurt, eerlijk: automatisch alleen als opschonen aanstaat én er een termijn is.
$bp_dagen = instelling_int('bewaar.bankpas_na_verwerking_dagen', 30);
$bp_auto  = instelling_bool('bewaar.actief', false) && $bp_dagen > 0;
$bp_zin   = $bp_auto
    ? 'De bankpasfoto wordt ' . $bp_dagen . ' dagen na "verwerkt" automatisch vernietigd.'
    : 'Automatisch opschonen staat uit: vernietig de bankpasfoto na het verwerken zelf (knop Vernietigen bij de bankpas).';

// ── Acties ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $sol_id = (int)($_POST['sol_id'] ?? 0);
    $actie  = (string)($_POST['actie'] ?? '');

    if ($actie === 'loondossier_vernietigen' && $mag_bewerken && $mag_verwijderen) {
        $res = documenten_loondossier_vernietigen((int)($_POST['persoon_id'] ?? 0), (string)($bhd['naam'] ?: 'beheerder #' . $bhd['id']));
        if ($res['ok']) flash('succes', 'Het loondossier is vernietigd. De rest van het dossier staat er nog.');
        else flash('fout', 'Vernietigen lukte niet: ' . $res['fout'] . '.');
        redirect(APP_URL . '/admin/documenten.php');
    }

    if ($actie === 'verwerkt' && $sol_id > 0 && $mag_bewerken) {
        documenten_verwerkt_markeren($sol_id, (int)$bhd['id']);
        flash('succes', 'Loondossier gemarkeerd als verwerkt. ' . $bp_zin);
    } elseif ($actie === 'melding_opnieuw' && $sol_id > 0 && $mag_bewerken) {
        if (documenten_dossier_compleet_check($sol_id, true)) {
            flash('succes', 'Melding opnieuw verstuurd naar de penningmeester.');
        } else {
            flash('fout', 'De melding is niet verstuurd — het dossier is niet compleet, of de mail mislukte. Kijk in het berichtenlog.');
        }
    } elseif ($actie === 'vernietig' && $mag_verwijderen) {
        $doc_id = (int)($_POST['document_id'] ?? 0);
        $d = document_rij($doc_id);
        if ($d && $d['soort'] === 'bankpas' && document_vernietigen($doc_id, 'handmatig door ' . $bhd['naam'])) {
            flash('succes', 'De kopie van de bankpas is vernietigd.');
        } else {
            flash('fout', 'Vernietigen lukte niet. Alleen de bankpasfoto kan handmatig worden vernietigd.');
        }
    } else {
        flash('fout', 'Je rol mag deze actie niet uitvoeren.');
    }
    redirect(APP_URL . '/admin/documenten.php');
}

$pagina_titel = 'Loondossiers';
$actieve_nav  = 'documenten';

$seizoen = actief_seizoen();
$rijen   = documenten_overzicht((int)($seizoen['id'] ?? 0));
$focus   = (int)($_GET['sol'] ?? 0);
$tok     = csrf_token();
$dl      = fn(int $doc_id): string => APP_URL . '/admin/document_download.php?id=' . $doc_id . '&t=' . urlencode($tok);

$n_compleet = 0; $n_verwerkt = 0;
foreach ($rijen as $r) {
    if ($r['dossier']['compleet']) $n_compleet++;
    if ($r['dossier']['verwerkt'])  $n_verwerkt++;
}

$zegel = function_exists('zegel_status') ? zegel_status() : ['ok' => false, 'reden' => 'niet geladen'];
$kluis = function_exists('kluis_status') ? kluis_status() : ['ok' => false, 'reden' => 'niet geladen'];

/** "3 sep 14:05" — datum én tijd, want bij inzage in deze stukken telt het tijdstip. */
$wanneer = function (?string $dt): string {
    if (!$dt) return '';
    $t = strtotime($dt);
    return datum_kort_nl(date('Y-m-d', $t)) . ' ' . date('H:i', $t);
};

// ── Bevestiging als tussenscherm ─────────────────────────────
// Allebei onomkeerbaar en allebei op persoonsgegevens: geen weg te klikken dialoogje.
$bev_actie = (string)($_GET['bevestig'] ?? '');
$bev_doc   = null;
$bev_sol   = null;
if ($bev_actie === 'vernietig' && $mag_verwijderen) {
    $bev_doc = document_rij((int)($_GET['doc'] ?? 0));
    if (!$bev_doc || ($bev_doc['soort'] ?? '') !== 'bankpas') { $bev_actie = ''; $bev_doc = null; }
} elseif ($bev_actie === 'verwerkt' && $mag_bewerken) {
    $_id = (int)($_GET['sol'] ?? 0);
    foreach ($rijen as $r) if ((int)$r['sol']['id'] === $_id) { $bev_sol = $r; break; }
    if (!$bev_sol) { $bev_actie = ''; }
} elseif ($bev_actie === 'loondossier' && $mag_bewerken && $mag_verwijderen) {
    $_lijst  = documenten_te_vernietigen((int)($_GET['persoon'] ?? 0));
    $bev_sol = $_lijst[0] ?? null;
    if (!$bev_sol) { $bev_actie = ''; }
} else {
    $bev_actie = '';
}

// Kandidaten voor handmatige vernietiging (alleen wie het loondossier mag bewerken ziet de kaart)
$te_vernietigen = $mag_bewerken ? documenten_te_vernietigen() : [];

require __DIR__ . '/layout_top.php';
?>

<?php if ($bev_actie === 'vernietig'): ?>
  <?php
  $bev = [
      'titel'  => 'Kopie bankpas vernietigen?',
      'regels' => [
          'Het bestand wordt uit de kluis gewist en is daarna niet meer terug te halen.',
          'Dat het er is geweest en wie het heeft vernietigd blijft in het logboek staan.',
          'De rest van het loondossier blijft ongemoeid.',
      ],
      'bericht'      => $bp_auto
          ? 'Normaal gaat dit vanzelf: ' . $bp_dagen . ' dagen na het verwerken wordt de bankpasfoto automatisch vernietigd. Handmatig hoeft alleen als dat eerder moet.'
          : 'Automatisch opschonen staat uit, dus dit gebeurt niet vanzelf. Vernietig de bankpasfoto zodra het dossier is verwerkt.',
      'onomkeerbaar' => true,
      'knop'         => 'Ja, vernietigen',
      'toon'         => 'gevaar',
  ];
  $bev_form  = ['actie' => APP_URL . '/admin/documenten.php',
                'velden' => ['actie' => 'vernietig', 'document_id' => (int)$bev_doc['id']]];
  $bev_terug = APP_URL . '/admin/documenten.php';
  require __DIR__ . '/_bevestig.php';
  ?>
  <?php require __DIR__ . '/layout_bottom.php'; ?>
  <?php return; ?>
<?php endif; ?>

<?php if ($bev_actie === 'verwerkt'): ?>
  <?php
  $bev = [
      'titel'  => 'Loondossier van ' . $bev_sol['naam'] . ' als verwerkt markeren?',
      'regels' => [
          $bev_sol['naam'] . ' kan de documenten daarna niet meer wijzigen of opnieuw uploaden.',
          $bp_zin,
          'Het loonheffingsformulier en de ID-kopie blijven staan tot je ze zelf vernietigt; de bewaarplicht is vijf jaar na het einde van het kalenderjaar waarin het dienstverband eindigde.',
      ],
      'bericht'      => 'Doe dit pas als de gegevens echt in de loonadministratie staan.',
      'onomkeerbaar' => true,
      'knop'         => 'Ja, verwerkt',
      'toon'         => 'primair',
  ];
  $bev_form  = ['actie' => APP_URL . '/admin/documenten.php',
                'velden' => ['actie' => 'verwerkt', 'sol_id' => (int)$bev_sol['sol']['id']]];
  $bev_terug = APP_URL . '/admin/documenten.php';
  require __DIR__ . '/_bevestig.php';
  ?>
  <?php require __DIR__ . '/layout_bottom.php'; ?>
  <?php return; ?>
<?php endif; ?>

<?php if ($bev_actie === 'loondossier'): ?>
  <?php
  $bev = [
      'titel'  => 'Loondossier van ' . $bev_sol['naam'] . ' vernietigen?',
      'regels' => [
          'Onherroepelijk weg: ' . implode(', ', $bev_sol['stukken']) . '.',
          'De bestanden worden uit de kluis gewist en zijn daarna niet meer terug te halen, ook niet door een beheerder.',
          'Als bewijs blijft staan, zonder inhoud: dát er getekend en geüpload is, en in het logboek dat jij dit hebt vernietigd.',
          'De rest van het dossier (gegevens, sollicitaties, andere ondertekeningen) blijft staan.',
      ],
      'bericht'      => 'Laatste seizoen hier: ' . $bev_sol['laatste_jaar'] . '. Het systeem weet niet wanneer het dienstverband eindigde. Vernietig alleen als je in de loonadministratie hebt gecontroleerd dat de bewaarplicht echt voorbij is.',
      'onomkeerbaar' => true,
      'knop'         => 'Ja, loondossier vernietigen',
      'toon'         => 'gevaar',
  ];
  $bev_form  = ['actie' => APP_URL . '/admin/documenten.php',
                'velden' => ['actie' => 'loondossier_vernietigen', 'persoon_id' => (int)$bev_sol['persoon_id']]];
  $bev_terug = APP_URL . '/admin/documenten.php#vernietigen';
  require __DIR__ . '/_bevestig.php';
  ?>
  <?php require __DIR__ . '/layout_bottom.php'; ?>
  <?php return; ?>
<?php endif; ?>

<?php if (!$kluis['ok']): ?>
<div class="ds-melding ds-melding-error bh-blokkade">
  <?= icoon('waarschuwing', 18) ?>
  <span><strong>De kluis is niet bereikbaar.</strong> <?= h((string)$kluis['reden']) ?> —
  uploads en loonheffingsformulieren zijn zolang geblokkeerd. Los dit op voordat je verder gaat.</span>
</div>
<?php endif; ?>

<?php if (!$zegel['ok']): ?>
<div class="ds-melding ds-melding-warn bh-blokkade">
  <?= icoon('waarschuwing', 18) ?>
  <span><strong>Het documentzegel werkt niet.</strong> <?= h((string)$zegel['reden']) ?> —
  nieuwe PDF's krijgen geen digitaal zegel. Bestaande documenten blijven geldig.</span>
</div>
<?php endif; ?>

<section class="bh-avgkader">
  <?= icoon('slot', 18) ?>
  <div>
    <strong>Hier staan een BSN en kopieën van identiteitsbewijzen.</strong>
    Elke download wordt vastgelegd — wie, wat en wanneer. Op een foto (JPG of PNG) van het
    identiteitsbewijs of de bankpas komt automatisch een watermerk; op een PDF niet. Download
    alleen op een apparaat dat je vertrouwt en verwijder het bestand zodra het in de
    loonadministratie staat.
    <span class="bh-avgkader-regel"><?= h($bp_zin) ?></span>
    <span class="bh-avgkader-regel">
      Het loonheffingsformulier en de ID-kopie blijven staan tot jij ze vernietigt: de bewaarplicht
      loopt tot vijf jaar na het einde van het kalenderjaar waarin het dienstverband eindigde.
      <?php if ($te_vernietigen): ?>
        Er <?= count($te_vernietigen) === 1 ? 'komt 1 dossier' : 'komen ' . count($te_vernietigen) . ' dossiers' ?> daarvoor
        in aanmerking: zie <a href="#vernietigen">Te beoordelen voor vernietiging</a> onderaan.
      <?php else: ?>
        Komt een dossier daarvoor in aanmerking, dan verschijnt het onderaan dit scherm.
      <?php endif; ?>
    </span>
  </div>
</section>

<?php if (!$rijen): ?>
  <?php
  $leeg_titel = 'Nog geen loondossiers';
  $leeg_tekst = 'Zodra iemand op "Aangenomen" staat, zet het systeem een loondossier klaar en verschijnt het hier.';
  $leeg_icoon = 'slot';
  require __DIR__ . '/_leeg.php';
  ?>
<?php else: ?>

<div class="bh-dosstand">
  <?= bh_stoplicht($n_verwerkt === count($rijen) ? 'goed' : ($n_compleet > $n_verwerkt ? 'krap' : 'slecht'),
        $n_compleet - $n_verwerkt > 0
          ? ($n_compleet - $n_verwerkt) . ' ' . ($n_compleet - $n_verwerkt === 1 ? 'dossier wacht' : 'dossiers wachten') . ' op verwerking'
          : ($n_verwerkt === count($rijen) ? 'Alles verwerkt' : 'Geen dossier compleet')) ?>
  <span class="bh-meta">
    <?= count($rijen) ?> aangenomen · <?= $n_compleet ?> compleet · <?= $n_verwerkt ?> verwerkt
  </span>
</div>

<div class="bh-doslijst">
  <?php foreach ($rijen as $r):
    $d      = $r['dossier'];
    $sol_id = (int)$r['sol']['id'];
    $lh     = $d['loonheffing'];
    $docs   = $d['docs'];
    $open   = ($focus === $sol_id) || (!$d['verwerkt'] && $d['compleet']);
    $_toon  = $d['verwerkt'] ? 'grijs' : ($d['compleet'] ? 'groen' : 'oranje');
  ?>
  <details class="ds-kaart bh-doskaart ds-fase-<?= $_toon ?>" id="sol<?= $sol_id ?>" <?= $open ? 'open' : '' ?>>
    <summary class="bh-doskaart-kop">
      <span class="bh-doskaart-naam"><?= h((string)$r['naam']) ?></span>
      <?php if ($d['verwerkt']): ?>
        <?= bh_badge('Verwerkt ' . $wanneer($lh['verwerkt_op'] ?? null), 'grijs') ?>
      <?php elseif ($d['compleet']): ?>
        <?= bh_badge('Compleet — klaar om te verwerken', 'groen') ?>
      <?php else: ?>
        <?= bh_badge('Ontbreekt nog: ' . implode(', ', $d['ontbreekt']), 'oranje') ?>
      <?php endif; ?>
    </summary>

    <div class="bh-doskaart-body">
      <?php /* De naam zelf is hier de knop om de kaart te openen, dus de weg naar het
               dossier staat eronder. Zonder dit is dit scherm een doodlopend eind:
               je ziet dat er iets ontbreekt maar kunt niet bij de persoon. */ ?>
      <?php if (mag('sollicitanten', 'lezen')): ?>
        <a class="bh-doskaart-dossier" href="<?= APP_URL ?>/admin/sollicitant.php?id=<?= $sol_id ?>&amp;tab=ondertekeningen">
          <?= icoon('persoon', 14) ?> Naar het dossier van <?= h((string)$r['naam']) ?>
        </a>
      <?php endif; ?>
      <ul class="bh-dosstukken">
        <li>
          <span class="bh-dosstuk-wat">
            <strong>Loonheffingsformulier</strong>
            <span>Het officiële model, digitaal verzegeld</span>
          </span>
          <span class="bh-dosstuk-stand">
            <?php if ($lh && !empty($lh['ondertekend_op'])): ?>
              Ondertekend <?= h($wanneer($lh['ondertekend_op'])) ?>
              <?php if (function_exists('ondertekening_zekerheid_label')): ?>
                · <?= h((string)ondertekening_zekerheid_label($lh['zekerheid'] ?? null, $lh['zekerheid_naar'] ?? null)) ?>
              <?php endif; ?>
              <?php if (!empty($lh['pdf_sha256'])): ?>
                <span class="bh-dosstuk-hash" title="Vingerafdruk van het bestand; hiermee is te controleren dat het niet is gewijzigd">
                  sha256 <?= h(substr((string)$lh['pdf_sha256'], 0, 24)) ?>…
                </span>
              <?php endif; ?>
            <?php elseif ($lh): ?>
              <?= bh_badge('Nog niet ondertekend', 'oranje') ?>
            <?php else: ?>
              <?= bh_badge('Niet klaargezet', 'grijs') ?>
            <?php endif; ?>
          </span>
          <span class="bh-dosstuk-actie">
            <?php if ($lh && !empty($lh['ondertekend_op']) && $mag_bewerken): ?>
              <a class="ds-btn ds-btn-secundair ds-btn-sm"
                 href="<?= APP_URL ?>/admin/document_download.php?ond=<?= (int)$lh['id'] ?>&amp;t=<?= urlencode($tok) ?>">
                <?= icoon('download', 14) ?> PDF
              </a>
            <?php endif; ?>
          </span>
        </li>

        <?php foreach (['id_voor' => 'Identiteitsbewijs — voorkant',
                        'id_achter' => 'Identiteitsbewijs — achterkant',
                        'bankpas' => 'Kopie bankpas'] as $soort => $label):
          $doc = $docs[$soort] ?? null;
        ?>
        <li>
          <span class="bh-dosstuk-wat">
            <strong><?= h($label) ?></strong>
            <?php if ($soort === 'id_voor' && $doc && !empty($doc['extra'])): ?>
              <span><?= $doc['extra'] === 'paspoort' ? 'Paspoort' : 'Identiteitskaart' ?></span>
            <?php elseif ($soort === 'bankpas'): ?>
              <span><?= $bp_auto ? 'Vernietigd ' . $bp_dagen . ' dagen na verwerking' : 'Na verwerking zelf vernietigen' ?></span>
            <?php endif; ?>
          </span>
          <span class="bh-dosstuk-stand">
            <?php if ($doc): ?>
              <?= h((string)$doc['bestandsnaam']) ?> · <?= h($wanneer($doc['geupload_op'])) ?>
              · <?= round((int)$doc['grootte'] / 1048576, 1) ?> MB
              <?php if (($doc['status'] ?? '') === 'verwerkt'): ?><?= bh_chip('verwerkt') ?><?php endif; ?>
              <?php if (!empty($doc['laatst_gedownload_op'])): ?>
                <span class="bh-dosstuk-sub">Laatst ingezien <?= h($wanneer($doc['laatst_gedownload_op'])) ?></span>
              <?php endif; ?>
            <?php elseif ($soort === 'id_achter' && (($docs['id_voor']['extra'] ?? '') === 'paspoort')): ?>
              <span class="bh-meta">Niet nodig bij een paspoort</span>
            <?php else: ?>
              <?= bh_badge('Nog niet geüpload', 'oranje') ?>
            <?php endif; ?>
          </span>
          <span class="bh-dosstuk-actie">
            <?php if ($doc && mag(document_recht_module($soort), 'bewerken')): ?>
              <a class="ds-btn ds-btn-secundair ds-btn-sm" href="<?= h($dl((int)$doc['id'])) ?>">
                <?= icoon('download', 14) ?> Download
              </a>
            <?php endif; ?>
            <?php if ($doc && $soort === 'bankpas' && $mag_verwijderen): ?>
              <a class="ds-btn ds-btn-gevaar ds-btn-sm" href="?bevestig=vernietig&amp;doc=<?= (int)$doc['id'] ?>">
                <?= icoon('prullenbak', 14) ?> Vernietigen
              </a>
            <?php endif; ?>
          </span>
        </li>
        <?php endforeach; ?>

        <li>
          <span class="bh-dosstuk-wat"><strong>Lid van de vereniging</strong></span>
          <span class="bh-dosstuk-stand">
            <?php $lid = $d['lidmaatschap']; ?>
            <?php if ($lid && !empty($lid['ondertekend_op'])): ?>
              Ingeschreven <?= h($wanneer($lid['ondertekend_op'])) ?> · naar de ledenservice gemaild
            <?php elseif ($lid): ?>
              <?= bh_badge('Nog niet ingeschreven', 'oranje') ?>
            <?php else: ?>
              <span class="bh-meta">Niet van toepassing — heeft al een verenigingsaccount</span>
            <?php endif; ?>
          </span>
          <span class="bh-dosstuk-actie">
            <?php if ($lid && !empty($lid['ondertekend_op']) && $mag_bewerken): ?>
              <a class="ds-btn ds-btn-secundair ds-btn-sm"
                 href="<?= APP_URL ?>/admin/document_download.php?ond=<?= (int)$lid['id'] ?>&amp;t=<?= urlencode($tok) ?>">
                <?= icoon('download', 14) ?> PDF
              </a>
            <?php endif; ?>
          </span>
        </li>
      </ul>

      <?php if ($mag_bewerken): ?>
      <div class="bh-doskaart-voet">
        <?php if ($d['compleet'] && !$d['verwerkt']): ?>
          <a class="ds-btn ds-btn-primair ds-btn-sm" href="?bevestig=verwerkt&amp;sol=<?= $sol_id ?>">
            <?= icoon('vink', 14) ?> Verwerkt in de loonadministratie
          </a>
        <?php endif; ?>
        <?php if ($d['compleet']): ?>
          <form method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="actie" value="melding_opnieuw">
            <input type="hidden" name="sol_id" value="<?= $sol_id ?>">
            <button type="submit" class="ds-btn ds-btn-zacht ds-btn-sm">
              <?= icoon('mail', 14) ?> Melding opnieuw naar de penningmeester
            </button>
          </form>
        <?php endif; ?>

        <?php if ($lh && function_exists('ondertekening_gebeurtenissen')): $geb = ondertekening_gebeurtenissen((int)$lh['id']); ?>
          <?php if ($geb): ?>
          <details class="bh-dostijdlijn">
            <summary>Tijdlijn van de ondertekening (<?= count($geb) ?>)</summary>
            <ul>
              <?php foreach ($geb as $g):
                // Zelfde selectie als op de certificaatpagina: kanaal/nummer, reden, rol, door.
                $gd = json_decode((string)($g['details'] ?? ''), true);
                $gdel = [];
                if (is_array($gd)) foreach ($gd as $gk => $gv) {
                    if (!in_array($gk, ['kanaal', 'naar', 'reden', 'rol', 'door'], true) || $gv === '' || $gv === null) continue;
                    $gdel[] = $gk . ': ' . ($gk === 'naar' ? telefoon_opmaak((string)$gv) : $gv);
                }
              ?>
              <li>
                <?= h(date('d-m-Y H:i:s', strtotime((string)$g['tijdstip']))) ?> ·
                <?= h(ondertekening_gebeurtenis_label((string)$g['gebeurtenis'])) ?><?= $gdel ? ' (' . h(implode(', ', $gdel)) . ')' : '' ?><?= !empty($g['ip']) ? ' · ' . h((string)$g['ip']) : '' ?>
              </li>
              <?php endforeach; ?>
            </ul>
          </details>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </details>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<?php
$tv_lijst      = $te_vernietigen;
$tv_jaren      = documenten_loon_bewaarjaren();
$tv_mag_wissen = $mag_verwijderen;
require __DIR__ . '/_loon_vernietigen.php';
?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

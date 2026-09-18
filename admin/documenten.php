<?php
/**
 * Beheer › Loondossiers (penningmeester): per aangenomen strandwacht het loonheffingsformulier (verzegelde PDF),
 * kopie ID, kopie bankpas, pasfoto en de inschrijving als lid. Downloaden via document_download.php (gelogd),
 * dossier "verwerkt" markeren, bankpas vernietigen, melding aan de penningmeester opnieuw sturen, tijdlijn.
 * Recht: module 'documenten' (penningmeester + admin); bestuur ziet dit scherm niet.
 */
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('documenten', 'lezen');
$db = db();
$bhd = huidige_beheerder();
$melding = ''; $melding_type = 'succes';

// ── Acties ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $sol_id = (int)($_POST['sol_id'] ?? 0);
    $actie  = (string)($_POST['actie'] ?? '');
    if ($sol_id > 0 && $actie === 'verwerkt' && mag('documenten', 'bewerken')) {
        documenten_verwerkt_markeren($sol_id, (int)$bhd['id']);
        $melding = 'Loondossier gemarkeerd als verwerkt. De bankpasfoto wordt na de ingestelde termijn automatisch vernietigd.';
    } elseif ($sol_id > 0 && $actie === 'melding_opnieuw' && mag('documenten', 'bewerken')) {
        $melding = documenten_dossier_compleet_check($sol_id, true) ? 'Melding opnieuw verstuurd naar de penningmeester.' : 'Kon de melding niet versturen (dossier niet compleet of mail mislukt).';
        if (!str_starts_with($melding, 'Melding')) $melding_type = 'fout';
    } elseif ($actie === 'vernietig' && mag('documenten', 'verwijderen')) {
        $doc_id = (int)($_POST['document_id'] ?? 0);
        $d = document_rij($doc_id);
        if ($d && $d['soort'] === 'bankpas' && document_vernietigen($doc_id, 'handmatig door ' . $bhd['naam'])) $melding = 'Kopie bankpas vernietigd.';
        else { $melding = 'Vernietigen niet mogelijk (alleen de bankpasfoto kan handmatig vernietigd worden).'; $melding_type = 'fout'; }
    } else {
        $melding = 'Geen rechten voor deze actie.'; $melding_type = 'fout';
    }
}

$pagina_titel = 'Loondossiers';
$actieve_nav  = 'documenten';
require __DIR__ . '/layout_top.php';

$seizoen = actief_seizoen();
$rijen = documenten_overzicht((int)($seizoen['id'] ?? 0));
$focus = (int)($_GET['sol'] ?? 0);
$tok = csrf_token();
$dl = fn(int $doc_id): string => APP_URL . '/admin/document_download.php?id=' . $doc_id . '&t=' . urlencode($tok);
$mag_bewerken = mag('documenten', 'bewerken');
$mag_verwijderen = mag('documenten', 'verwijderen');
$n_compleet = 0; $n_verwerkt = 0;
foreach ($rijen as $r) { if ($r['dossier']['compleet']) $n_compleet++; if ($r['dossier']['verwerkt']) $n_verwerkt++; }
$zegel = function_exists('zegel_status') ? zegel_status() : ['ok' => false, 'reden' => 'niet geladen'];
$kluis = function_exists('kluis_status') ? kluis_status() : ['ok' => false, 'reden' => 'niet geladen'];
$maanden = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
$wanneer = function (?string $dt) use ($maanden): string { if (!$dt) return ''; $t = strtotime($dt); return date('j', $t) . ' ' . $maanden[(int)date('n', $t) - 1] . ' ' . date('H:i', $t); };
?>
<div class="container">
  <?php if ($melding !== ''): ?><div class="flash flash-<?= $melding_type ?>"><?= h($melding) ?></div><?php endif; ?>
  <?php if (!$kluis['ok']): ?><div class="flash flash-fout">Kluis niet beschikbaar: <?= h($kluis['reden']) ?> — uploads en loonheffingsformulieren zijn geblokkeerd.</div><?php endif; ?>
  <?php if (!$zegel['ok']): ?><div class="flash flash-fout">Documentzegel niet beschikbaar: <?= h($zegel['reden']) ?> — nieuwe PDF's krijgen geen digitaal zegel.</div><?php endif; ?>

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <span class="badge badge-blauw"><?= count($rijen) ?> aangenomen</span>
      <span class="badge badge-groen"><?= $n_compleet ?> compleet</span>
      <span class="badge badge-grijs"><?= $n_verwerkt ?> verwerkt</span>
    </div>
    <span class="badge badge-blauw">Seizoen <?= h($seizoen['naam'] ?? '—') ?></span>
  </div>

  <div class="notitie-box" style="margin-bottom:16px"><?= icoon('slot', 14) ?> Deze stukken bevatten BSN en kopieën van identiteitsbewijzen. Elke download wordt vastgelegd (wie, wanneer) en op ID-kopieën komt een watermerk. Download alleen op een vertrouwd apparaat, en verwijder de bestanden zodra ze in de loonadministratie staan. Bewaarplicht: 5 jaar na einde van het dienstjaar (loonheffing + ID); de bankpasfoto wordt na verwerking vernietigd.</div>

  <?php if (!$rijen): ?>
    <div class="card"><div class="card-body"><p style="color:var(--tekst-zacht);text-align:center;padding:24px 0">Nog geen aangenomen sollicitanten in dit seizoen.</p></div></div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach ($rijen as $r): $d = $r['dossier']; $sol_id = (int)$r['sol']['id']; $lh = $d['loonheffing']; $docs = $d['docs']; $open = ($focus === $sol_id) || (!$d['verwerkt'] && $d['compleet']); ?>
    <div class="card" id="sol<?= $sol_id ?>" style="border-left:4px solid <?= $d['verwerkt'] ? 'var(--grijs)' : ($d['compleet'] ? 'var(--groen)' : 'var(--oranje)') ?>">
      <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;cursor:pointer;flex-wrap:wrap" onclick="this.nextElementSibling.hidden=!this.nextElementSibling.hidden">
        <a href="<?= APP_URL ?>/admin/sollicitanten.php?zoek=<?= urlencode($r['naam']) ?>" onclick="event.stopPropagation()" style="flex:1;min-width:160px;font-weight:600;font-size:14.5px;color:var(--navy);text-decoration:none"><?= h($r['naam']) ?></a>
        <?php if ($d['verwerkt']): ?><span class="badge badge-grijs">Verwerkt <?= h($wanneer($lh['verwerkt_op'] ?? null)) ?></span>
        <?php elseif ($d['compleet']): ?><span class="badge badge-groen">Compleet</span>
        <?php else: ?><span class="badge badge-oranje">Ontbreekt: <?= h(implode(', ', $d['ontbreekt'])) ?></span><?php endif; ?>
        <span style="color:var(--grijs);font-size:12px">▾</span>
      </div>
      <div<?= $open ? '' : ' hidden' ?> style="border-top:1px solid var(--rand-licht);padding:6px 16px 14px">
        <table class="tabel" style="width:100%;font-size:13px">
          <tbody>
          <tr>
            <td style="width:220px"><strong>Loonheffingsformulier</strong><br><span style="color:var(--tekst-zacht)">Officieel model, digitaal verzegeld</span></td>
            <td><?php if ($lh && !empty($lh['ondertekend_op'])): ?>Ondertekend <?= h($wanneer($lh['ondertekend_op'])) ?> · <?= h(function_exists('ondertekening_zekerheid_label') ? ondertekening_zekerheid_label($lh['zekerheid'] ?? null) : '') ?><?php if (!empty($lh['pdf_sha256'])): ?><br><span style="color:var(--tekst-zacht);font-family:monospace;font-size:11px">sha256 <?= h(substr((string)$lh['pdf_sha256'], 0, 24)) ?>…</span><?php endif; ?>
                <?php elseif ($lh): ?><span class="badge badge-oranje">Nog niet ondertekend</span><?php else: ?><span class="badge badge-grijs">Niet klaargezet</span><?php endif; ?></td>
            <td style="text-align:right;white-space:nowrap"><?php if ($lh && !empty($lh['ondertekend_op']) && $mag_bewerken): ?><a class="btn btn-outline btn-sm" href="<?= APP_URL ?>/admin/document_download.php?ond=<?= (int)$lh['id'] ?>&amp;t=<?= urlencode($tok) ?>"><?= icoon('download', 14) ?> PDF</a><?php endif; ?></td>
          </tr>
          <?php foreach (['id_voor' => 'Identiteitsbewijs — voorkant', 'id_achter' => 'Identiteitsbewijs — achterkant', 'bankpas' => 'Kopie bankpas', 'pasfoto' => 'Pasfoto'] as $soort => $label): $doc = $docs[$soort] ?? null; ?>
          <tr>
            <td><strong><?= h($label) ?></strong><?php if ($soort === 'id_voor' && $doc && !empty($doc['extra'])): ?><br><span style="color:var(--tekst-zacht)"><?= $doc['extra'] === 'paspoort' ? 'Paspoort' : 'Identiteitskaart' ?></span><?php endif; ?></td>
            <td><?php if ($doc): ?><?= h($doc['bestandsnaam']) ?> · <?= h($wanneer($doc['geupload_op'])) ?> · <?= round((int)$doc['grootte'] / 1048576, 1) ?> MB<?= $doc['status'] === 'verwerkt' ? ' · <span class="badge badge-grijs">verwerkt</span>' : '' ?><?php if (!empty($doc['laatst_gedownload_op'])): ?><br><span style="color:var(--tekst-zacht)">Laatst gedownload <?= h($wanneer($doc['laatst_gedownload_op'])) ?></span><?php endif; ?>
                <?php elseif ($soort === 'id_achter' && (($docs['id_voor']['extra'] ?? '') === 'paspoort')): ?><span style="color:var(--tekst-zacht)">Niet nodig bij een paspoort</span>
                <?php else: ?><span class="badge badge-oranje">Nog niet geüpload</span><?php endif; ?></td>
            <td style="text-align:right;white-space:nowrap">
              <?php if ($doc && mag(document_recht_module($soort), 'bewerken')): ?><a class="btn btn-outline btn-sm" href="<?= $dl((int)$doc['id']) ?>"><?= icoon('download', 14) ?> Download</a><?php endif; ?>
              <?php if ($doc && $soort === 'bankpas' && $mag_verwijderen): ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Kopie bankpas definitief vernietigen?')"><?= csrf_input() ?><input type="hidden" name="actie" value="vernietig"><input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>"><button class="btn btn-outline btn-sm" type="submit"><?= icoon('prullenbak', 14) ?> Vernietigen</button></form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <tr>
            <td><strong>Lid van de vereniging</strong></td>
            <td><?php $lid = $d['lidmaatschap']; if ($lid && !empty($lid['ondertekend_op'])): ?>Ingeschreven <?= h($wanneer($lid['ondertekend_op'])) ?> (naar de ledenservice gemaild)<?php elseif ($lid): ?><span class="badge badge-oranje">Nog niet ingeschreven</span><?php else: ?><span style="color:var(--tekst-zacht)">Niet van toepassing (heeft al een verenigingsaccount)</span><?php endif; ?></td>
            <td style="text-align:right;white-space:nowrap"><?php if ($lid && !empty($lid['ondertekend_op']) && $mag_bewerken): ?><a class="btn btn-outline btn-sm" href="<?= APP_URL ?>/admin/document_download.php?ond=<?= (int)$lid['id'] ?>&amp;t=<?= urlencode($tok) ?>"><?= icoon('download', 14) ?> PDF</a><?php endif; ?></td>
          </tr>
          </tbody>
        </table>
        <?php if ($mag_bewerken): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center">
          <?php if ($d['compleet'] && !$d['verwerkt']): ?>
            <form method="POST" onsubmit="return confirm('Dossier als verwerkt markeren? De sollicitant kan de documenten daarna niet meer wijzigen.')"><?= csrf_input() ?><input type="hidden" name="actie" value="verwerkt"><input type="hidden" name="sol_id" value="<?= $sol_id ?>"><button class="btn btn-primary btn-sm" type="submit"><?= icoon('vink', 14) ?> Verwerkt in de loonadministratie</button></form>
          <?php endif; ?>
          <?php if ($d['compleet']): ?>
            <form method="POST"><?= csrf_input() ?><input type="hidden" name="actie" value="melding_opnieuw"><input type="hidden" name="sol_id" value="<?= $sol_id ?>"><button class="btn btn-outline btn-sm" type="submit"><?= icoon('mail', 14) ?> Melding opnieuw naar de penningmeester</button></form>
          <?php endif; ?>
          <?php if ($lh && function_exists('ondertekening_gebeurtenissen')): $geb = ondertekening_gebeurtenissen((int)$lh['id']); if ($geb): ?>
            <details style="font-size:12px;color:var(--tekst-zacht)"><summary style="cursor:pointer">Tijdlijn loonheffing (<?= count($geb) ?>)</summary>
              <ul style="margin:6px 0 0 16px;padding:0">
              <?php foreach ($geb as $g): ?><li><?= h(date('d-m-Y H:i:s', strtotime($g['tijdstip']))) ?> · <?= h(ondertekening_gebeurtenis_label((string)$g['gebeurtenis'])) ?><?= !empty($g['ip']) ? ' · ' . h($g['ip']) : '' ?></li><?php endforeach; ?>
              </ul></details>
          <?php endif; endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

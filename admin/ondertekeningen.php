<?php
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('ondertekeningen', 'lezen');
$db = db();

// ── Export één ondertekening als printbaar HTML ───────────────
if (isset($_GET['export'])) {
    $id = (int)$_GET['export'];
    $q = $db->prepare("
        SELECT o.*, s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam, p.geboortedatum, sz.naam AS seizoen_naam
        FROM ondertekeningen o
        JOIN sollicitaties s ON s.id = o.sollicitatie_id
        JOIN personen p ON p.id = s.persoon_id
        JOIN seizoenen sz ON sz.id = s.seizoen_id
        WHERE o.id = ?
    ");
    $q->execute([$id]);
    $rec = $q->fetch();
    if (!$rec) { http_response_code(404); exit('Niet gevonden'); }

    // Genereer de PDF met exact dezelfde generator als de mail naar de ouders,
    // zodat bekijken/printen in het beheer identiek is aan wat de ouder ontvangt.
    $sol_voor_pdf = [
        'voornaam'      => $rec['voornaam'] ?? '',
        'achternaam'    => trim(($rec['tussenvoegsel'] ? $rec['tussenvoegsel'] . ' ' : '') . ($rec['achternaam'] ?? '')),
        'geboortedatum' => $rec['geboortedatum'] ?? '',
    ];
    if (function_exists('ondertekening_pdf_genereer')) {
        $pdf_pad = ondertekening_pdf_genereer($rec, $sol_voor_pdf);
        if ($pdf_pad && is_file($pdf_pad)) {
            $naam_bestand = 'Ondertekend_' . preg_replace('/[^a-z0-9]/i', '_', $rec['type']) . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $naam_bestand . '"');
            header('Content-Length: ' . filesize($pdf_pad));
            readfile($pdf_pad);
            @unlink($pdf_pad);
            exit;
        }
    }
    http_response_code(500);
    exit('Kon het PDF-document niet genereren.');
}

$pagina_titel = 'Ondertekeningen';
$actieve_nav  = 'ondertekeningen';
require __DIR__ . '/layout_top.php';

// ── Overzicht ────────────────────────────────────────────────
$seizoen = actief_seizoen();
$sid = $seizoen['id'] ?? 0;

$q = $db->prepare("
    SELECT o.*, s.id AS sol_id, p.voornaam, p.tussenvoegsel, p.achternaam
    FROM ondertekeningen o
    JOIN sollicitaties s ON s.id = o.sollicitatie_id
    JOIN personen p ON p.id = s.persoon_id
    WHERE s.seizoen_id = ?
    ORDER BY p.achternaam, p.voornaam, o.aangemaakt_op
");
$q->execute([$sid]);
$rijen = $q->fetchAll();

$type_label = fn($t) => match ($t) {
    'gezondheid_ouder'  => 'Gezondheid t/m 17',
    'gezondheid_18plus' => 'Gezondheid 18+',
    'intake'            => 'Intake (sollicitant)',
    'intake_ouder'      => 'Intake (ouder)',
    default             => $t,
};

// Groeperen per persoon
$per_persoon = [];
foreach ($rijen as $o) {
    $sol = (int)$o['sol_id'];
    if (!isset($per_persoon[$sol])) {
        $per_persoon[$sol] = [
            'naam' => trim($o['voornaam'] . ' ' . ($o['tussenvoegsel'] ? $o['tussenvoegsel'] . ' ' : '') . $o['achternaam']),
            'docs' => [], 'klaar' => 0, 'totaal' => 0,
        ];
    }
    $klaar = !empty($o['ondertekend_op']);
    $per_persoon[$sol]['docs'][] = $o;
    $per_persoon[$sol]['totaal']++;
    if ($klaar) $per_persoon[$sol]['klaar']++;
}

// Tellingen voor de filterbalk
$aantal_compleet = 0; $aantal_open = 0;
foreach ($per_persoon as $p) {
    if ($p['klaar'] === $p['totaal']) $aantal_compleet++; else $aantal_open++;
}
?>

<div class="container">

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <div style="display:flex;gap:8px;flex-wrap:wrap" id="filterBalk">
      <button type="button" class="btn btn-primary btn-sm" data-filter="alle" onclick="filterOnd('alle', this)">Iedereen (<?= count($per_persoon) ?>)</button>
      <button type="button" class="btn btn-outline btn-sm" data-filter="open" onclick="filterOnd('open', this)">⏳ Nog open (<?= $aantal_open ?>)</button>
      <button type="button" class="btn btn-outline btn-sm" data-filter="compleet" onclick="filterOnd('compleet', this)">✓ Compleet (<?= $aantal_compleet ?>)</button>
    </div>
    <span class="badge badge-blauw">Seizoen <?= h($seizoen['naam'] ?? '—') ?></span>
  </div>

  <?php if (empty($per_persoon)): ?>
    <div class="card"><div class="card-body">
      <p style="color:var(--tekst-zacht);text-align:center;padding:24px 0">Nog geen ondertekeningen. Verstuur uitnodigingen via het sollicitant-paneel.</p>
    </div></div>
  <?php else: ?>

    <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach ($per_persoon as $sol => $p):
        $compleet = $p['klaar'] === $p['totaal'];
        $pct = $p['totaal'] > 0 ? round($p['klaar'] / $p['totaal'] * 100) : 0;
      ?>
      <div class="ond-kaart" data-status="<?= $compleet ? 'compleet' : 'open' ?>"
           style="background:var(--bg-card);border:1px solid var(--rand);border-left:4px solid <?= $compleet ? 'var(--groen)' : 'var(--oranje)' ?>;border-radius:10px;overflow:hidden">

        <!-- Kop: naam + voortgang -->
        <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;cursor:pointer;flex-wrap:wrap"
             onclick="this.parentElement.querySelector('.ond-docs').classList.toggle('open')">
          <div style="flex:1;min-width:160px">
            <a href="#" onclick="event.stopPropagation();openPaneel(<?= $sol ?>);return false"
               style="font-weight:600;font-size:14.5px;color:var(--navy);text-decoration:none"><?= h($p['naam']) ?></a>
          </div>

          <!-- Voortgangsbalk -->
          <div style="display:flex;align-items:center;gap:8px;min-width:160px">
            <div style="flex:1;height:7px;background:var(--grijs-bg);border-radius:4px;overflow:hidden">
              <div style="height:100%;width:<?= $pct ?>%;background:<?= $compleet ? 'var(--groen)' : 'var(--oranje)' ?>;transition:width .3s"></div>
            </div>
            <span style="font-size:12px;font-weight:600;color:<?= $compleet ? 'var(--groen)' : 'var(--tekst-zacht)' ?>;white-space:nowrap">
              <?= $p['klaar'] ?>/<?= $p['totaal'] ?>
            </span>
          </div>

          <?php if ($compleet): ?>
            <span class="badge badge-groen">✓ Compleet</span>
          <?php else: ?>
            <span class="badge badge-oranje"><?= $p['totaal'] - $p['klaar'] ?> open</span>
          <?php endif; ?>

          <span style="color:var(--grijs);font-size:12px" class="ond-chevron">▾</span>
        </div>

        <!-- Documenten (inklapbaar) -->
        <div class="ond-docs <?= $compleet ? '' : 'open' ?>" style="border-top:1px solid var(--rand-licht)">
          <?php foreach ($p['docs'] as $o):
            $klaar = !empty($o['ondertekend_op']);
          ?>
          <div style="display:flex;align-items:center;gap:12px;padding:9px 16px 9px 20px;font-size:13px;border-bottom:1px solid var(--rand-licht);flex-wrap:wrap">
            <span style="flex:1;min-width:140px">
              <?= $klaar ? '✓' : '○' ?> <?= h($type_label($o['type'])) ?>
            </span>
            <?php if ($klaar): ?>
              <span style="font-size:12px;color:var(--tekst-zacht)">
                <?= h(datum_nl(substr($o['ondertekend_op'], 0, 10))) ?>
                <?php if (!empty($o['ondertekenaar_naam'])): ?>· <?= h($o['ondertekenaar_naam']) ?><?php endif; ?>
              </span>
              <a href="?export=<?= (int)$o['id'] ?>" target="_blank" class="btn btn-outline btn-sm">📄 Bekijk</a>
            <?php else: ?>
              <span class="badge badge-oranje" style="font-size:11px">⏳ wacht</span>
              <button class="btn btn-outline btn-sm" onclick="kopieerLink('<?= APP_URL ?>/portaal/ondertekenen.php?token=<?= h($o['token']) ?>', this)">📋 Kopieer link</button>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</div>

<style>
.ond-docs { max-height:0; overflow:hidden; transition:max-height .25s ease; }
.ond-docs.open { max-height:600px; }
.ond-kaart .ond-chevron { transition:transform .2s; }
.ond-kaart:has(.ond-docs.open) .ond-chevron { transform:rotate(180deg); }
</style>

<script>
function kopieerLink(url, btn) {
  navigator.clipboard.writeText(url).then(() => {
    if (btn) { const t = btn.textContent; btn.textContent = '✓ Gekopieerd'; setTimeout(() => btn.textContent = t, 1500); }
  });
}

function filterOnd(filter, btn) {
  document.querySelectorAll('#filterBalk button').forEach(b => { b.classList.remove('btn-primary'); b.classList.add('btn-outline'); });
  btn.classList.remove('btn-outline'); btn.classList.add('btn-primary');
  document.querySelectorAll('.ond-kaart').forEach(k => {
    k.style.display = (filter === 'alle' || k.dataset.status === filter) ? '' : 'none';
  });
}
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>


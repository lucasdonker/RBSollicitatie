<?php
require_once __DIR__ . '/../includes/functions.php';
vereist_rol('bestuur');
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

    $form = json_decode($rec['formulier_data'] ?? '{}', true) ?: [];
    $naam_sol = trim($rec['voornaam'] . ' ' . ($rec['tussenvoegsel'] ? $rec['tussenvoegsel'] . ' ' : '') . $rec['achternaam']);
    $type_label = match ($rec['type']) {
        'gezondheid_ouder'  => 'Gezondheidsverklaring t/m 17 jaar',
        'gezondheid_18plus' => 'Gezondheidsverklaring 18+',
        'intake'            => 'Intakeformulier Selectiedag',
        'intake_ouder'      => 'Intakeformulier Selectiedag (ouder)',
        default             => 'Ondertekening',
    };
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html>
    <html lang="nl"><head>
    <meta charset="UTF-8">
    <title><?= h($type_label) ?> — <?= h($naam_sol) ?></title>
    <style>
    body { font-family: Arial, Helvetica, sans-serif; max-width: 780px; margin: 0 auto; padding: 40px 32px; color: #1a1a1a; line-height: 1.55; }
    h1 { font-size: 20px; letter-spacing: .03em; text-transform: uppercase; margin: 0 0 4px; color: #054d8a; }
    h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .05em; color: #054d8a; margin: 24px 0 10px; padding-bottom: 4px; border-bottom: 2px solid #054d8a; }
    .brand { text-align: center; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 2px solid #054d8a; }
    .brand .sub { color: #666; font-size: 12px; margin-top: 4px; }
    .rij { display: grid; grid-template-columns: 180px 1fr; gap: 10px 20px; font-size: 13px; margin: 6px 0; }
    .rij dt { color: #666; }
    .rij dd { margin: 0; font-weight: 500; }
    .verklaring { background: #f7f7f5; padding: 16px 20px; border-left: 4px solid #D43D00; border-radius: 4px; margin: 14px 0; font-size: 12px; line-height: 1.7; }
    .verklaring p { margin: 0 0 8px; }
    .aandoening { padding: 6px 12px; margin: 4px 0; background: #f7f7f5; border-radius: 4px; font-size: 12.5px; }
    .aandoening.ja { background: #FEF0EA; color: #8B2500; }
    .signatuur { margin-top: 40px; padding: 20px; border: 2px solid #054d8a; border-radius: 6px; background: #f7f7f5; }
    .signatuur h2 { margin-top: 0; border: none; padding: 0; }
    .signatuur .naam { font-family: 'Brush Script MT', cursive; font-size: 28px; color: #054d8a; margin: 8px 0 4px; }
    .signatuur .metadata { font-size: 11px; color: #666; margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd; font-family: monospace; }
    .voet { margin-top: 32px; text-align: center; font-size: 10px; color: #888; }
    .print-knop { position: fixed; top: 20px; right: 20px; background: #D43D00; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-family: inherit; }
    @media print { .print-knop { display: none; } body { padding: 20px; } }
    </style>
    </head><body>

    <button class="print-knop" onclick="window.print()">🖨️ Print / Opslaan als PDF</button>

    <div class="brand">
      <h1><?= h($type_label) ?></h1>
      <div class="sub">Reddingsbrigade Paal 13 — Callantsoog · Seizoen <?= h($rec['seizoen_naam']) ?></div>
    </div>

    <h2>Sollicitant</h2>
    <dl class="rij">
      <dt>Naam</dt><dd><?= h($naam_sol) ?></dd>
      <?php if ($rec['geboortedatum']): ?><dt>Geboortedatum</dt><dd><?= h(datum_nl($rec['geboortedatum'])) ?></dd><?php endif; ?>
    </dl>

    <?php if ($rec['type'] === 'gezondheid_ouder'): ?>
      <h2>Ouder / verzorger</h2>
      <dl class="rij">
        <dt>Relatie</dt><dd><?= h(ucfirst($rec['ondertekenaar_relatie'] ?? '—')) ?></dd>
        <dt>Naam</dt><dd><?= h($rec['ondertekenaar_naam']) ?></dd>
        <dt>Geboortedatum</dt><dd><?= h(datum_nl($rec['ondertekenaar_geboortedatum'] ?? '')) ?></dd>
        <dt>Geboorteplaats</dt><dd><?= h($rec['ondertekenaar_geboorteplaats'] ?? '—') ?></dd>
        <?php if (!empty($form['geslacht'])): ?><dt>Geslacht</dt><dd><?= h(ucfirst($form['geslacht'])) ?></dd><?php endif; ?>
      </dl>

    <?php elseif ($rec['type'] === 'gezondheid_18plus'): ?>
      <h2>Ondertekenaar</h2>
      <dl class="rij">
        <dt>Naam</dt><dd><?= h($rec['ondertekenaar_naam']) ?></dd>
        <?php if ($rec['ondertekenaar_geboortedatum']): ?><dt>Geboortedatum</dt><dd><?= h(datum_nl($rec['ondertekenaar_geboortedatum'])) ?></dd><?php endif; ?>
        <?php if ($rec['ondertekenaar_geboorteplaats']): ?><dt>Geboorteplaats</dt><dd><?= h($rec['ondertekenaar_geboorteplaats']) ?></dd><?php endif; ?>
        <?php if (!empty($form['geslacht'])): ?><dt>Geslacht</dt><dd><?= h(ucfirst($form['geslacht'])) ?></dd><?php endif; ?>
      </dl>
    <?php endif; ?>

    <?php if (in_array($rec['type'], ['gezondheid_ouder','gezondheid_18plus'], true)): ?>
      <h2>Verklaring</h2>
      <div class="verklaring">
        <?php if ($rec['type'] === 'gezondheid_ouder'): ?>
          <p>Ondergetekende verklaart dat bovengenoemde minderjarige mag deelnemen aan de opleidingen en het examen voor zwemmend en/of varend redden en/of wedstrijden waarbij de bepalingen van het geldige Examen Reglement worden gehandhaafd.</p>
        <?php else: ?>
          <p>Ondergetekende verklaart deel te mogen nemen aan de opleidingen en het examen voor zwemmend en/of varend redden en/of wedstrijden waarbij de bepalingen van het geldige Examen Reglement worden gehandhaafd.</p>
        <?php endif; ?>
        <p>Ondergetekende verklaart bekend te zijn met het feit dat het bovenstaande verhoogde eisen stelt aan de fysieke toestand van de deelnemer.</p>
        <p>Ondergetekende verklaart de organisatie en de KNBRD uitdrukkelijk van iedere aansprakelijkheid te vrijwaren voor gebeurtenissen die voortvloeien uit de hieronder vermelde gezondheidsrisico's.</p>
      </div>

      <h2>Gezondheidsrisico's</h2>
      <?php $a = $form['aandoeningen'] ?? []; ?>
      <div class="aandoening <?= !empty($a['diabetes']) || !empty($a['breuken']) ? 'ja' : '' ?>">Diabetes / breuken: <strong><?= !empty($a['diabetes']) || !empty($a['breuken']) ? 'JA — van toepassing' : 'nee' ?></strong></div>
      <div class="aandoening <?= !empty($a['hart_vaat']) ? 'ja' : '' ?>">Hart- en vaataandoeningen: <strong><?= !empty($a['hart_vaat']) ? 'JA — van toepassing' : 'nee' ?></strong></div>
      <div class="aandoening <?= !empty($a['ademhaling']) ? 'ja' : '' ?>">Astma / bronchitis: <strong><?= !empty($a['ademhaling']) ? 'JA — van toepassing' : 'nee' ?></strong></div>
      <div class="aandoening <?= !empty($a['zenuwstelsel']) ? 'ja' : '' ?>">Zenuwstelsel (duizeligheid, epilepsie): <strong><?= !empty($a['zenuwstelsel']) ? 'JA — van toepassing' : 'nee' ?></strong></div>
      <div class="aandoening <?= !empty($a['gehoororgaan']) ? 'ja' : '' ?>">Gehoororgaan: <strong><?= !empty($a['gehoororgaan']) ? 'JA — van toepassing' : 'nee' ?></strong></div>
      <?php if (!empty($a['overig'])): ?>
        <div class="aandoening ja"><strong>Overige aandoeningen / medicatie:</strong><br><?= nl2br(h($a['overig'])) ?></div>
      <?php endif; ?>

    <?php elseif ($rec['type'] === 'intake'): ?>
      <h2>Gezondheid &amp; veiligheid</h2>
      <?php if (empty($form['heeft_aandacht'])): ?>
        <div class="aandoening">Geen aandachtspunten gemeld.</div>
      <?php else: ?>
        <div class="aandoening ja"><strong>Aandachtspunten:</strong><br><?= nl2br(h($form['aandacht_tekst'] ?? '')) ?></div>
      <?php endif; ?>

      <h2>Foto's beleid</h2>
      <div class="aandoening <?= !empty($form['foto_toestemming']) ? '' : 'ja' ?>">
        <strong><?= !empty($form['foto_toestemming']) ? '✓ Toestemming' : '✗ Geen toestemming' ?></strong> voor gebruik van foto's tijdens de selectiedag (interne evaluatie, promotie, sociale media).
      </div>

      <h2>Verklaring &amp; aansprakelijkheid</h2>
      <div class="verklaring">
        <p>• Ik verklaar dat ik dit formulier naar waarheid heb ingevuld.</p>
        <p>• Ik verklaar dat ik in goede gezondheid verkeer om deel te nemen aan sportieve activiteiten.</p>
        <p>• Deelname is op eigen risico. De organisatie is niet aansprakelijk voor letsel dat voortkomt uit verzwegen of onbekende omstandigheden.</p>
        <p>• Ik ben 16 jaar of ouder en bevoegd om dit formulier zelfstandig te ondertekenen.</p>
      </div>
    <?php endif; ?>

    <div class="signatuur">
      <h2>Digitale ondertekening</h2>
      <div>Plaats: <strong><?= h($rec['plaats']) ?></strong> · Datum: <strong><?= h(datum_nl(substr($rec['ondertekend_op'], 0, 10))) ?></strong></div>
      <div class="naam"><?= h($rec['ondertekenaar_naam']) ?></div>
      <div style="font-size:11px;color:#666">(Digitaal ondertekend — getypte naam geldt als handtekening)</div>
      <div class="metadata">
        Tijdstip: <?= h($rec['ondertekend_op']) ?> UTC<br>
        IP-adres: <?= h($rec['ondertekend_ip']) ?><br>
        Token: <?= h($rec['token']) ?><br>
        Geldig t/m: <?= h(datum_nl($rec['geldig_tot'] ?? '')) ?><br>
        User agent: <?= h(substr($rec['ondertekend_user_agent'] ?? '', 0, 100)) ?>
      </div>
    </div>

    <div class="voet">
      Dit document is gegenereerd door het Paal 13 sollicitantensysteem — reddingsbrigade Callantsoog<br>
      Voor vragen: postcallantsoog@paal13.com · paal13.com
    </div>

    </body></html>
    <?php
    exit;
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


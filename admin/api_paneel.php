<?php
require_once __DIR__ . '/../includes/functions.php';
vereist_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); echo '<p style="padding:20px;color:#c53030">Ongeldig verzoek.</p>'; exit; }

try {
    $db   = db();
    $stmt = $db->prepare("
        SELECT s.*, p.voornaam, p.tussenvoegsel, p.achternaam,
               p.email, p.geboortedatum, p.telefoon, p.geslacht, p.id AS pid,
               sz.naam sz_naam, sz.datum_werk_start
        FROM sollicitaties s JOIN personen p ON p.id = s.persoon_id
        JOIN seizoenen sz ON sz.id = s.seizoen_id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $sol = $stmt->fetch();
    if (!$sol) { echo '<p style="padding:20px;color:#c53030">Niet gevonden.</p>'; exit; }

    $bq = $db->prepare("SELECT * FROM blk_formulieren WHERE sollicitatie_id=?"); $bq->execute([$id]); $blk = $bq->fetch();
    $cq = $db->prepare("SELECT * FROM beoordelingen WHERE sollicitatie_id=?");   $cq->execute([$id]); $beoor = $cq->fetch();
    $dq = $db->prepare("SELECT * FROM beschikbaarheid WHERE sollicitatie_id=?"); $dq->execute([$id]); $besch = $dq->fetch();
    $vq = $db->prepare("SELECT s.fase,s.aangemeld_op,sz.naam seizoen_naam FROM sollicitaties s JOIN seizoenen sz ON sz.id=s.seizoen_id WHERE s.persoon_id=? AND s.id!=? ORDER BY s.aangemeld_op DESC");
    $vq->execute([$sol['pid'], $id]); $vorige = $vq->fetchAll();
} catch (Exception $e) {
    http_response_code(500);
    echo '<p style="padding:20px;color:#c53030">Fout: ' . h($e->getMessage()) . '</p>'; exit;
}

$naam  = volledige_naam($sol);
$init  = strtoupper(substr($sol['voornaam'],0,1).substr($sol['achternaam'],0,1));
$fases = ['aangemeld','uitgenodigd_selectie','aangemeld_selectie','selectiedag_geweest','uitgenodigd_tw','tw_geweest','aangenomen'];
$fi    = array_search($sol['fase'], $fases);
$alle_fases = ['aangemeld'=>'Aangemeld','uitgenodigd_selectie'=>'Uitgenodigd SD','aangemeld_selectie'=>'Aangemeld SD','selectiedag_geweest'=>'SD geweest','uitgenodigd_tw'=>'Uitgenodigd TW','tw_geweest'=>'TW geweest','aangenomen'=>'Aangenomen','afgewezen'=>'Afgewezen'];

// Leeftijd op de HUIDIGE datum tonen (jouw keuze). Daarnaast: berekenen of/wanneer iemand
// tijdens/voor het seizoen 16 wordt, zodat het bestuur dat als melding ziet.
$lft = function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($sol['geboortedatum']) : null;
$peil = $sol['datum_werk_start'] ?? date('Y-06-01');
$lft_seizoen = ($sol['geboortedatum'] && function_exists('leeftijd_op_datum'))
    ? leeftijd_op_datum($sol['geboortedatum'], $peil) : $lft;
// Melding als de leeftijd nu en op de seizoensstart verschilt rond de 16-grens
$wordt_16_melding = '';
if ($sol['geboortedatum'] && $lft !== null) {
    try {
        $geb = new DateTime($sol['geboortedatum']);
        $zestien = (clone $geb)->modify('+16 years');
        $vandaag = new DateTime('today');
        if ($lft < 16 && $zestien > $vandaag) {
            $wordt_16_melding = 'Wordt 16 op ' . $zestien->format('d-m-Y');
        } elseif ($lft >= 16 && $lft_seizoen < 16) {
            // Nu 16, maar was op de seizoensstart nog 15
            $wordt_16_melding = 'Was 15 bij aanmelding/seizoensstart, inmiddels 16';
        }
    } catch (\Exception $e) {}
}
$aanbeveling = function_exists('invite_aanbeveling') ? invite_aanbeveling($lft_seizoen) : null;

// BLK weken
$blk_weken = [];
if ($blk && $blk['beschikbaarheid_intentie']) {
    $d = json_decode($blk['beschikbaarheid_intentie'], true);
    if (is_array($d)) $blk_weken = $d;
}
$besch_weken = $besch ? (json_decode($besch['weken_json'] ?? '[]', true) ?: []) : [];

// AI analyse
$ai_analyse = $blk ? ($blk['ai_analyse'] ?? null) : null;
?>
<style>
.ai-box{background:linear-gradient(135deg,rgba(99,102,241,.08),rgba(168,85,247,.06));border:1px solid rgba(99,102,241,.2);border-radius:8px;padding:13px 14px;font-size:13px;line-height:1.75;color:var(--tekst)}
.ai-box strong{color:var(--navy)}
[data-theme="dark"] .ai-box strong{color:#c4b5fd}
.invite-ok{background:var(--groen-bg);border:1px solid rgba(29,158,117,.3);border-radius:6px;padding:8px 11px;font-size:12px;color:#085041;margin-bottom:10px}
.invite-nok{background:var(--grijs-bg);border:1px solid var(--rand);border-radius:6px;padding:8px 11px;font-size:12px;color:var(--grijs);margin-bottom:10px}
</style>

<div class="paneel-header">
  <button class="paneel-sluiten" onclick="sluitPaneel()">✕</button>
  <div style="display:flex;align-items:center;gap:10px;margin-top:8px">
    <div class="avatar" style="width:44px;height:44px;font-size:16px;background:rgba(255,255,255,.2)"><?= h($init) ?></div>
    <div>
      <div class="paneel-naam"><?= h($naam) ?></div>
      <div class="paneel-email"><?= h($sol['email']) ?></div>
      <?php if ($sol['terugkerend']): ?><span class="badge badge-terugkerend" style="margin-top:3px">↩ Eerder gesolliciteerd</span><?php endif; ?>
    </div>
  </div>
</div>

<div class="paneel-body">

  <!-- NAW + basis -->
  <div class="info-grid">
    <div class="info-item"><label>Telefoon</label><span><?= h($sol['telefoon'] ?: '—') ?></span></div>
    <div class="info-item"><label>Leeftijd</label><span><?= $lft ?> jaar<?php if ($wordt_16_melding): ?> <span title="<?= h($wordt_16_melding) ?>" style="display:inline-block;font-size:11px;font-weight:600;color:var(--oranje);background:var(--oranje-bg);padding:1px 7px;border-radius:9px;margin-left:4px"><?= h($wordt_16_melding) ?></span><?php endif; ?></span></div>
    <div class="info-item"><label>Geboortedatum</label><span><?= datum_nl($sol['geboortedatum']) ?></span></div>
    <div class="info-item"><label>Aangemeld</label><span><?= datum_nl($sol['aangemeld_op']) ?></span></div>
    <?php // Toestemming-vastlegging (AVG) — tonen zodra beschikbaar ?>
    <?php if (!empty($sol['privacy_akkoord_op'])): ?>
    <div class="info-item" style="grid-column:1/-1">
      <label>Privacy-toestemming</label>
      <span>✓ Akkoord op <?= datum_nl(substr($sol['privacy_akkoord_op'],0,10)) ?><?= !empty($sol['privacy_versie']) ? ' · versie ' . h($sol['privacy_versie']) : '' ?><?= !empty($sol['privacy_ip']) ? ' · IP ' . h($sol['privacy_ip']) : '' ?></span>
    </div>
    <?php endif; ?>
    <?php if (!empty($sol['ouder_toestemming_op'])): ?>
    <div class="info-item" style="grid-column:1/-1">
      <label>Ouderlijke toestemming</label>
      <span>✓ Gegeven op <?= datum_nl(substr($sol['ouder_toestemming_op'],0,10)) ?><?= !empty($sol['ouder_toestemming_naam']) ? ' door ' . h($sol['ouder_toestemming_naam']) : '' ?></span>
    </div>
    <?php endif; ?>
    <?php if ($blk && $blk['adres']): ?>
    <div class="info-item" style="grid-column:1/-1"><label>Woonadres</label><span><?= h($blk['adres']) ?></span></div>
    <?php endif; ?>
    <div class="info-item" style="grid-column:1/-1">
      <label>Status</label>
      <span><span class="badge badge-<?= h(fase_kleur($sol['fase'])) ?>"><?= h(fase_label($sol['fase'])) ?></span></span>
    </div>
  </div>

  <!-- Auto-invite aanbeveling (alleen relevant voor 'aangemeld' fase) -->
  <?php if (in_array($sol['fase'], ['aangemeld']) && $aanbeveling): ?>
  <div class="<?= $aanbeveling['voldoet'] ? 'invite-ok' : 'invite-nok' ?>">
    <?= $aanbeveling['label'] ?>
    <?php if ($aanbeveling['voldoet'] && $aanbeveling['modus'] !== 'uit'): ?>
    — <strong>Uitnodigen aanbevolen</strong>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Competenties (Authentik) -->
  <?php if (function_exists('competentie_sync_klaar') && competentie_sync_klaar()):
    $comps = competenties_van($id);
    $sync_info = competentie_sync_info($id);
  ?>
  <div class="paneel-sectie">
    <h3>🎖️ Competenties
      <button type="button" class="btn btn-outline btn-sm" style="float:right;font-size:11px" onclick="competentieVerversen(<?= $id ?>)" title="Ophalen uit Authentik">🔄 Ververs</button>
    </h3>
    <div id="compChips-<?= $id ?>">
      <?php if ($comps): ?>
        <div style="display:flex;flex-wrap:wrap;gap:5px">
          <?php foreach ($comps as $c): ?>
          <span style="display:inline-flex;align-items:center;gap:4px;background:var(--blauw-bg);border:1px solid var(--blauw);color:var(--blauw);border-radius:6px;padding:3px 10px;font-size:12px;font-weight:600">
            <?= h($c['icoon']) ?> <?= h($c['groep']) ?>
          </span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="font-size:12px;color:var(--grijs)">Geen competenties gevonden in Authentik.</div>
      <?php endif; ?>
    </div>
    <div style="font-size:11px;color:var(--grijs);margin-top:6px" id="compStatus-<?= $id ?>">
      <?php if (!empty($sync_info['laatst_gesynct'])): ?>
        Laatst gesynct: <?= h(datum_nl(substr($sync_info['laatst_gesynct'],0,10))) ?> <?= h(substr($sync_info['laatst_gesynct'],11,5)) ?>
        <?php if (($sync_info['sync_status'] ?? '') === 'niet_gevonden'): ?>
          · <span style="color:var(--oranje)">geen match</span>
        <?php endif; ?>
      <?php else: ?>
        Nog niet gesynchroniseerd.
      <?php endif; ?>
    </div>
    <?php if (($sync_info['sync_status'] ?? '') === 'niet_gevonden' || empty($comps)): ?>
    <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--rand-licht)">
      <div style="font-size:11px;color:var(--tekst-zacht);margin-bottom:4px">Geen automatische match? Koppel handmatig op Authentik-gebruikersnaam:</div>
      <div style="display:flex;gap:6px">
        <input type="text" id="compUsername-<?= $id ?>" placeholder="gebruikersnaam" style="flex:1;font-size:12px;padding:5px 8px;border:1px solid var(--rand);border-radius:5px">
        <button type="button" class="btn btn-primary btn-sm" style="font-size:11px" onclick="competentieKoppelen(<?= $id ?>)">Koppel</button>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Fase wijzigen -->
  <div class="paneel-sectie">
    <h3>Fase wijzigen</h3>
    <div style="display:flex;flex-wrap:wrap;gap:5px">
      <?php foreach ($alle_fases as $f => $l): ?>
      <?php if ($f === $sol['fase']): ?>
      <span class="btn btn-sm" style="background:var(--navy);color:#fff;cursor:default">✓ <?= h($l) ?></span>
      <?php else: ?>
      <button type="button" class="btn btn-outline btn-sm"
              onclick="fasWijzigen(<?= $id ?>, '<?= $f ?>', '<?= addslashes($l) ?>')">
        <?= h($l) ?>
      </button>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Voortgang -->
  <div class="paneel-sectie">
    <h3>Voortgang</h3>
    <div class="tijdlijn">
      <?php foreach ($fases as $i => $f):
        $st = $fi === false ? 'wacht' : ($i < $fi ? 'gedaan' : ($i === $fi ? 'huidig' : 'wacht'));
        if ($sol['fase'] === 'afgewezen') $st = 'wacht';
      ?>
      <div class="tl-item">
        <div class="tl-stip <?= $st ?>"></div>
        <div class="tl-inhoud"><div class="titel" style="color:<?= $st==='wacht'?'var(--grijs)':'var(--tekst)' ?>"><?= h(fase_label($f)) ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- AI Analyse -->
  <?php if ($ai_analyse): ?>
  <div class="paneel-sectie">
    <h3>🤖 AI Analyse <span style="font-size:10px;font-weight:400;color:var(--grijs)">Geanonimiseerd · AVG-conform</span></h3>
    <div class="ai-box"><?= ai_render($ai_analyse) ?></div>
    <div style="font-size:10px;color:var(--grijs);margin-top:4px">
      Analyse op <?= datum_nl(substr($blk['ai_analyse_op'] ?? '',0,10)) ?>
      · <a href="<?= APP_URL ?>/admin/blk_detail.php?id=<?= $id ?>" target="_blank" style="color:var(--oranje)">Volledig BLK ↗</a>
    </div>
  </div>
  <?php elseif ($blk && instelling('ai.api_key')): ?>
  <div class="paneel-sectie">
    <h3>🤖 AI Analyse</h3>
    <p style="font-size:12px;color:var(--grijs);margin-bottom:8px">Nog geen analyse beschikbaar. BLK is ingevuld maar analyse is nog niet gedraaid.</p>
    <button type="button" class="btn btn-outline btn-sm"
            onclick="genereerAnalyse(<?= $id ?>)">⚡ Analyseer nu</button>
    <div id="ai-loading-<?= $id ?>" style="display:none;font-size:12px;color:var(--grijs);margin-top:6px">Analyse wordt gegenereerd...</div>
  </div>
<!-- genereerAnalyse staat in layout_bottom.php -->
  <?php endif; ?>

  <!-- Notities (bestuurs-communicatie) -->
  <div class="paneel-sectie">
    <h3>💬 Notities <span style="font-size:10px;font-weight:400;color:var(--grijs)">Zichtbaar voor bestuur</span></h3>
    <div id="notities-lijst-<?= $id ?>" style="min-height:30px">
      <div style="font-size:12px;color:var(--grijs);padding:8px 0">Laden…</div>
    </div>
    <div style="display:flex;gap:6px;margin-top:8px">
      <textarea id="nieuwe-notitie-<?= $id ?>" placeholder="Nieuwe notitie…" rows="2"
                style="flex:1;font-size:12.5px;padding:8px 10px;min-height:36px;resize:vertical"></textarea>
      <button type="button" class="btn btn-primary btn-sm" style="align-self:flex-start;white-space:nowrap"
              onclick="notitieToevoegen(<?= $id ?>)">+ Toevoegen</button>
    </div>
  </div>

  <!-- Timeline (chronologische activiteit) -->
  <div class="paneel-sectie">
    <h3>🕒 Tijdlijn</h3>
    <div id="timeline-<?= $id ?>" style="min-height:30px">
      <div style="font-size:12px;color:var(--grijs);padding:8px 0">Laden…</div>
    </div>
  </div>

  <!-- Ondertekeningen (gezondheidsverklaring + intake) -->
  <?php
    $geb = $sol['geboortedatum'] ?? null;
    $leef = function_exists('leeftijd_uit_datum') ? leeftijd_uit_datum($geb)
          : ($geb ? (new DateTime($geb))->diff(new DateTime())->y : null);
    $is_minderjarig = ($leef !== null && $leef < 18);
  ?>
  <div class="paneel-sectie">
    <h3>✍️ Ondertekening</h3>
    <?php
    try {
        $ond_q = db()->prepare("SELECT id, type, token, ondertekend_op FROM ondertekeningen WHERE sollicitatie_id=? ORDER BY aangemaakt_op DESC");
        $ond_q->execute([$id]);
        $ond_rows = $ond_q->fetchAll();
    } catch (Exception $e) { $ond_rows = []; }
    ?>
    <?php if ($ond_rows): ?>
      <div style="display:flex;flex-direction:column;gap:5px;margin-bottom:10px">
        <?php foreach ($ond_rows as $o):
          $type_label = match($o['type']) {
            'gezondheid_ouder'  => 'Gezondheid (ouder)',
            'gezondheid_18plus' => 'Gezondheid 18+',
            'intake'            => 'Intake (sollicitant)',
                    'intake_ouder'      => 'Intake (ouder)',
            default             => $o['type'],
          };
          $klaar = !empty($o['ondertekend_op']);
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:6px 10px;background:var(--grijs-bg);border-radius:6px;font-size:12px">
          <span>
            <?= $klaar ? '✅' : '⏳' ?> <?= h($type_label) ?>
            <?php if ($klaar): ?><span style="color:var(--grijs)">— <?= h(datum_nl(substr($o['ondertekend_op'],0,10))) ?></span><?php endif; ?>
          </span>
          <span style="display:flex;gap:4px">
            <?php if ($klaar): ?>
              <?php
                // Gezondheidsverklaringen bevatten medische gegevens: alleen bestuur/admin mag inzien.
                $is_medisch = in_array($o['type'], ['gezondheid_ouder','gezondheid_18plus'], true);
                if (!$is_medisch || huidige_gebruiker_rol_minstens('bestuur')):
              ?>
              <a href="<?= APP_URL ?>/admin/ondertekeningen.php?export=<?= (int)$o['id'] ?>" target="_blank" class="btn btn-outline btn-sm" style="font-size:10px;padding:2px 8px">📄 Bekijk</a>
              <?php elseif ($is_medisch): ?>
              <span style="font-size:10px;color:var(--grijs)" title="Alleen bestuur en beheerders kunnen medische gegevens inzien">🔒 bestuur</span>
              <?php endif; ?>
            <?php else: ?>
              <button class="btn btn-outline btn-sm" style="font-size:10px;padding:2px 8px" onclick="kopieerOndertekenlink('<?= APP_URL ?>/portaal/ondertekenen.php?token=<?= h($o['token']) ?>')">📋 Link</button>
            <?php endif; ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php
    // Ouder-gegevens tonen indien bekend
    $ouder_info = null;
    if ($is_minderjarig) {
        try {
            if (function_exists('personen_ouder_auto_setup')) personen_ouder_auto_setup();
            $opq = db()->prepare("SELECT p.ouder_naam, p.ouder_email, p.ouder_telefoon FROM personen p JOIN sollicitaties s ON s.persoon_id=p.id WHERE s.id=?");
            $opq->execute([$id]);
            $ouder_info = $opq->fetch() ?: null;
        } catch (Exception $e) {}
    }
    ?>
    <?php if ($is_minderjarig && empty($ouder_info['ouder_email'])): ?>
      <div style="background:var(--geel-bg);color:var(--geel);padding:8px 12px;border-radius:6px;font-size:12px;margin-bottom:8px">
        ⚠️ Minderjarig — sollicitant moet eerst <strong>ouder-gegevens invullen in het portaal</strong>. Daarna gaan de ouder-formulieren automatisch per mail. (Handmatig starten kan ook met de knoppen hieronder.)
      </div>
    <?php elseif ($is_minderjarig && !empty($ouder_info['ouder_email'])): ?>
      <div style="background:var(--blauw-bg);color:var(--blauw);padding:8px 12px;border-radius:6px;font-size:12px;margin-bottom:8px">
        👨‍👩‍👧 Ouder/verzorger: <strong><?= h($ouder_info['ouder_naam'] ?: 'Naam onbekend') ?></strong> — <?= h($ouder_info['ouder_email']) ?><?php if (!empty($ouder_info['ouder_telefoon'])): ?> — 📞 <?= h($ouder_info['ouder_telefoon']) ?><?php endif; ?>
      </div>
    <?php endif; ?>

    <div style="display:flex;flex-wrap:wrap;gap:5px">
      <?php if ($is_minderjarig): ?>
        <button class="btn btn-outline btn-sm" style="font-size:11px" onclick="startOndertekening(<?= $id ?>,'gezondheid_ouder')">+ Gezondheid (ouder)</button>
        <button class="btn btn-outline btn-sm" style="font-size:11px" onclick="startOndertekening(<?= $id ?>,'intake_ouder')">+ Intake (ouder)</button>
      <?php else: ?>
        <button class="btn btn-outline btn-sm" style="font-size:11px" onclick="startOndertekening(<?= $id ?>,'gezondheid_18plus')">+ Gezondheid 18+</button>
        <button class="btn btn-outline btn-sm" style="font-size:11px" onclick="startOndertekening(<?= $id ?>,'intake')">+ Intake selectiedag</button>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Laad notities en timeline meteen bij openen van dit paneel
    if (typeof laadNotities === 'function') laadNotities(<?= $id ?>);
    if (typeof laadTimeline === 'function') laadTimeline(<?= $id ?>);
  </script>

  <!-- BLK Formulier -->
  <div class="paneel-sectie">
    <h3>
      Beter leren kennen
      <?php if ($blk): ?>
      <span class="badge badge-groen" style="font-size:10px">✓ Ingevuld</span>
      <div style="float:right;display:flex;gap:4px">
        <a href="<?= APP_URL ?>/admin/blk_detail.php?id=<?= $id ?>" target="_blank"
           class="btn btn-outline btn-sm" style="font-size:11px">📋 Volledig</a>
        <button type="button" class="btn btn-rood btn-sm" style="font-size:11px"
                onclick="verwijderActie(<?= $id ?>,'verwijder_blk','BLK formulier verwijderen?')">🗑️</button>
      </div>
      <?php else: ?>
      <span class="badge badge-grijs" style="font-size:10px">Niet ingevuld</span>
      <?php endif; ?>
    </h3>
    <?php if ($blk): ?>
      <?php foreach (['Opleiding'=>'opleiding',"Diploma's"=>'diplomas','Werkervaring'=>'werkervaring','Motivatie'=>'motivatie_lifeguard','Bijzonderheden'=>'bijzonderheden'] as $lbl=>$veld): ?>
      <?php if (!empty($blk[$veld])): ?>
      <div style="margin-bottom:8px">
        <div style="font-size:10px;font-weight:600;color:var(--grijs);text-transform:uppercase;margin-bottom:2px"><?= $lbl ?></div>
        <div style="font-size:12px;line-height:1.6;color:var(--tekst)"><?= nl2br(h(mb_substr($blk[$veld],0,200))) ?><?= strlen($blk[$veld])>200?'…':'' ?></div>
      </div>
      <?php endif; endforeach; ?>

      <?php if ($blk['foto_url']): ?>
      <img src="<?= h($blk['foto_url']) ?>" alt="Foto" style="height:56px;width:56px;object-fit:cover;border-radius:50%;border:2px solid var(--rand);margin-top:4px">
      <?php endif; ?>
    <?php else: ?>
    <p style="font-size:12px;color:var(--grijs)">Niet ingevuld door de sollicitant.</p>
    <?php endif; ?>
  </div>

  <!-- Beschikbaarheid (BLK = definitieve bron; besch-tabel is sync daarvan) -->
  <?php $dagen_weken = !empty($besch_weken) ? $besch_weken : $blk_weken; ?>
  <?php if ($besch || !empty($dagen_weken)): ?>
  <div class="paneel-sectie">
    <h3>Beschikbaarheid
      <button type="button" class="btn btn-rood btn-sm" style="float:right;font-size:11px"
              onclick="verwijderActie(<?= $id ?>,'verwijder_beschikbaarheid','Beschikbaarheid verwijderen?')">🗑️</button>
    </h3>
    <?php
    // Beschikbaarheid 2.0 profiel (indien ingevuld)
    $b2eff = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief($id) : [];
    $b2data = function_exists('beschikbaarheid2_van') ? beschikbaarheid2_van($id) : ['profiel'=>null,'afwezig'=>[]];
    ?>
    <?php if ($b2data['profiel'] && $b2data['blokken']): ?>
      <?php
        // Week-label helper (W23 · 2 jun)
        $wk_datums = function_exists('seizoen_weken_datums') ? seizoen_weken_datums() : [];
        $wk_label = function($wn) use ($wk_datums) {
            $wn = (int)$wn;
            if (isset($wk_datums[$wn])) return 'W' . $wn . ' (' . $wk_datums[$wn]['start']->format('j M') . ')';
            return 'W' . $wn;
        };
        $voorlopig = !empty($b2data['profiel']['voorlopig']);
      ?>
      <div style="font-size:12px;margin-bottom:8px;padding:10px 12px;background:var(--blauw-bg);border-radius:6px;line-height:1.7">
        <?php if ($voorlopig): ?>
          <div style="display:inline-block;font-size:11px;font-weight:700;color:#fff;background:var(--oranje);padding:2px 8px;border-radius:4px;margin-bottom:6px">⏳ VOORLOPIG — nog niet definitief</div><br>
        <?php else: ?>
          <div style="display:inline-block;font-size:11px;font-weight:700;color:#fff;background:var(--groen);padding:2px 8px;border-radius:4px;margin-bottom:6px">✓ Definitief<?= !empty($b2data['profiel']['definitief_op']) ? ' (' . h(datum_nl($b2data['profiel']['definitief_op'])) . ')' : '' ?></div><br>
        <?php endif; ?>

        <?php foreach ($b2data['blokken'] as $blok): ?>
          📅 <strong><?= h($wk_label($blok['van_week'])) ?> t/m <?= h($wk_label($blok['tot_week'])) ?></strong>
          — <?php if ($blok['regime'] === 'weekend'):
              $wd = $blok['weekend_dagen'] ?: [5,6,7];
              echo '<span style="color:var(--oranje)">alleen ' . implode('/', array_map('dag_kort', $wd)) . '</span>';
            else: echo 'hele weken (5 dagen)'; endif; ?><br>
        <?php endforeach; ?>

        <?php if ($b2data['afwezig']): ?>
          ✈️ <strong>Afwezig:</strong> <?php echo implode(' · ', array_map(fn($a) => h(datum_nl($a['van_datum'])) . '–' . h(datum_nl($a['tot_datum'])) . ($a['label'] ? ' (' . h($a['label']) . ')' : ''), $b2data['afwezig'])); ?><br>
        <?php endif; ?>

        <?php
          $losse_dagen_p = function_exists('beschikbaarheid_dagen') ? beschikbaarheid_dagen($id) : [];
          if ($losse_dagen_p): ?>
          📅 <strong>Losse dagen:</strong>
          <?php echo implode(' · ', array_map(function($ld) {
              $lbl = h(datum_nl($ld['datum']));
              if (($ld['soort'] ?? '') === 'voorkeur_vrij') $lbl .= ' (liever vrij)';
              if (!empty($ld['reden'])) $lbl .= ' — ' . h($ld['reden']);
              return $lbl;
          }, $losse_dagen_p)); ?><br>
        <?php endif; ?>

        <?php if (!empty($b2data['profiel']['vaste_dagen_uit'])): ?>
          ⭐ <strong>Voorkeur vrij:</strong> <?= implode(', ', array_map('dag_kort', $b2data['profiel']['vaste_dagen_uit'])) ?>
          <span style="color:var(--grijs);font-size:11px">(voorkeur, planner beslist)</span>
          <?php if (!empty($b2data['profiel']['vaste_toelichting'])): ?>
            <span style="color:var(--tekst-zacht)">— <?= h($b2data['profiel']['vaste_toelichting']) ?></span>
          <?php endif; ?>
          <br>
        <?php endif; ?>

        <?php if (!empty($b2data['profiel']['opmerking'])): ?>
          💬 <span style="color:var(--tekst-zacht)"><?= h($b2data['profiel']['opmerking']) ?></span>
        <?php endif; ?>

        <div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap">
          <a href="<?= APP_URL ?>/admin/export_dagbeschikbaarheid.php?id=<?= (int)$id ?>" target="_blank"
             class="btn btn-outline btn-sm" style="font-size:11px">
            📆 Beschikbaarheid per dag bekijken (API-check)
          </a>
          <?php if (function_exists('mag') && mag('beschikbaarheid', 'bewerken')): ?>
          <a href="<?= APP_URL ?>/admin/beschikbaarheid_bewerken.php?id=<?= (int)$id ?>"
             class="btn btn-outline btn-sm" style="font-size:11px">
            ✏️ Beschikbaarheid aanpassen
          </a>
          <?php endif; ?>
        </div>
      </div>

      <?php
      // Verzoeken (afwijkend aantal dagen) — bestuur beslist
      $open_vz = array_filter($b2data['verzoeken'], fn($v) => $v['status'] === 'open');
      $besliste_vz = array_filter($b2data['verzoeken'], fn($v) => $v['status'] !== 'open');
      ?>
      <?php if ($open_vz): ?>
        <div style="margin-bottom:8px;padding:10px 12px;background:var(--oranje-bg);border:1px solid var(--oranje);border-radius:6px">
          <div style="font-size:12px;font-weight:600;color:var(--oranje);margin-bottom:6px">⚖️ Openstaand verzoek — minder dagen werken</div>
          <?php foreach ($open_vz as $vz): ?>
          <div style="font-size:12px;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--oranje-bg)">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
              <span><strong><?= h($wk_label($vz['van_week'])) ?> t/m <?= h($wk_label($vz['tot_week'])) ?></strong>: <?= (int)$vz['dagen_aantal'] ?> dagen/week</span>
              <span style="flex:1"></span>
              <button type="button" class="btn btn-primary btn-sm" style="font-size:11px" onclick="verzoekBeslis(<?= (int)$vz['id'] ?>,'goedkeuren',<?= $id ?>)">✓ Goedkeuren</button>
              <button type="button" class="btn btn-outline btn-sm" style="font-size:11px" onclick="verzoekBeslis(<?= (int)$vz['id'] ?>,'afwijzen',<?= $id ?>)">✕ Afwijzen</button>
            </div>
            <?php if (!empty($vz['dagen_pool'])): ?>
              <div style="color:var(--tekst-zacht)">Kan die week: <strong><?= implode(', ', array_map('dag_kort', $vz['dagen_pool'])) ?></strong> — kies hieruit <?= (int)$vz['dagen_aantal'] ?> dag<?= (int)$vz['dagen_aantal']===1?'':'en' ?></div>
            <?php else: ?>
              <div style="color:var(--grijs)">Geen dagen-voorkeur opgegeven — planner kiest vrij.</div>
            <?php endif; ?>
            <?php if ($vz['toelichting']): ?><div style="color:var(--tekst-zacht)">Reden: <?= h($vz['toelichting']) ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($besliste_vz): ?>
        <div style="font-size:11px;color:var(--tekst-zacht);margin-bottom:8px">
          <?php foreach ($besliste_vz as $vz): ?>
            <?= $vz['status'] === 'goedgekeurd' ? '✓' : '✕' ?> Verzoek <?= h($wk_label($vz['van_week'])) ?>–<?= h($wk_label($vz['tot_week'])) ?> (<?= (int)$vz['dagen_aantal'] ?>d): <strong><?= h($vz['status']) ?></strong><br>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php
    // Afwijkende norm (bestuursbesluit): standaard 5 dagen/week, kan per persoon anders
    $norm_dagen = $b2data['profiel']['norm_dagen'] ?? null;
    $norm_notitie = $b2data['profiel']['norm_notitie'] ?? '';
    ?>
    <div style="margin-bottom:10px;padding:8px 10px;border:1px dashed var(--rand);border-radius:6px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
        <span style="font-size:12px;color:var(--tekst-zacht)">
          Norm: <strong id="normLabel-<?= $id ?>"><?= $norm_dagen ? $norm_dagen . ' dagen/week (afwijkend)' : '5 dagen/week (standaard)' ?></strong>
        </span>
        <button type="button" class="btn btn-outline btn-sm" style="font-size:11px"
                onclick="document.getElementById('normEdit-<?= $id ?>').style.display=document.getElementById('normEdit-<?= $id ?>').style.display==='none'?'block':'none'">
          Aanpassen
        </button>
      </div>
      <div id="normEdit-<?= $id ?>" style="display:none;margin-top:8px">
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
          <select id="normDagen-<?= $id ?>" style="font-size:12px;padding:5px 8px;border:1px solid var(--rand);border-radius:5px">
            <option value="">Standaard (5)</option>
            <?php foreach ([1,2,3,4,6] as $nd): ?>
            <option value="<?= $nd ?>" <?= (int)$norm_dagen === $nd ? 'selected' : '' ?>><?= $nd ?> dagen/week</option>
            <?php endforeach; ?>
          </select>
          <input type="text" id="normNotitie-<?= $id ?>" value="<?= h($norm_notitie) ?>" placeholder="Reden (bestuursbesluit)"
                 style="flex:1;min-width:140px;font-size:12px;padding:5px 8px;border:1px solid var(--rand);border-radius:5px">
          <button type="button" class="btn btn-primary btn-sm" style="font-size:11px" onclick="normOpslaan(<?= $id ?>)">Opslaan</button>
        </div>
        <div style="font-size:11px;color:var(--grijs);margin-top:4px">Alleen instellen na een bewust bestuursbesluit. Beïnvloedt de dagen-telling in de planning.</div>
      </div>
    </div>

    <?php if ($b2data['profiel'] && $b2data['blokken']): ?>
    <?php
      // Effectieve weken uit het model + bestaande correcties
      $corr_map = [];
      foreach ($b2data['correcties'] as $c) $corr_map[(int)$c['week']] = $c;
      $eff_weken = array_keys($b2eff);
      sort($eff_weken);
    ?>
    <div style="margin-top:10px;padding:10px 12px;border:1px dashed var(--rand);border-radius:8px">
      <div style="font-size:12px;font-weight:600;margin-bottom:4px">📝 Bestuurs-correctie per week</div>
      <div style="font-size:11px;color:var(--grijs);margin-bottom:8px">
        Weet je dat iemand een week tóch niet (of anders) kan dan opgegeven? Leg het hier vast — bijvoorbeeld na een telefoontje. Dit overschrijft wat de sollicitant zelf doorgaf, met een reden erbij.
      </div>
      <div style="display:flex;flex-direction:column;gap:4px">
        <?php foreach ($eff_weken as $w): $w = (int)$w; $c = $corr_map[$w] ?? null; ?>
        <div style="display:flex;align-items:center;gap:8px;font-size:12px;flex-wrap:wrap">
          <span style="width:120px;font-weight:600;color:var(--tekst-zacht);flex-shrink:0"><?= h($wk_label($w)) ?></span>
          <?php if ($c): ?>
            <span style="flex:1;color:<?= $c['soort']==='niet'?'var(--rood)':'var(--oranje)' ?>">
              <?= $c['soort']==='niet' ? '⛔ vervalt' : '➖ alleen ' . implode('/', array_map('dag_kort', $c['dagen'] ?: [])) ?>
              <?php if ($c['reden']): ?><span style="color:var(--tekst-zacht)"> — <?= h($c['reden']) ?></span><?php endif; ?>
            </span>
            <button type="button" class="btn btn-outline btn-sm" style="font-size:10px" onclick="correctieWis(<?= $id ?>,<?= $w ?>)">↩ Herstel</button>
          <?php else: ?>
            <span style="flex:1;color:var(--groen)">✓ zoals opgegeven</span>
            <button type="button" class="btn btn-outline btn-sm" style="font-size:10px" onclick="correctieOpen(<?= $id ?>,<?= $w ?>)">Corrigeren</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($besch && $besch['uitzonderingen']): ?>
    <div class="notitie-box" style="margin-top:6px">⚠️ Vrije tekst sollicitant: <?= nl2br(h($besch['uitzonderingen'])) ?></div>
    <?php endif; ?>
    <?php if ($besch && !empty($besch['opmerkingen'])): ?>
    <div class="notitie-box" style="margin-top:4px;background:#eff8ff;border-color:#90cdf4;color:#1a56a0">💬 <?= nl2br(h($besch['opmerkingen'])) ?></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Selectiedag beoordeling -->
  <?php if ($beoor && $beoor['aanwezig'] !== null): ?>
  <div class="paneel-sectie">
    <h3>Selectiedag</h3>
    <p style="font-size:12px;margin-bottom:5px"><strong>Aanwezig:</strong> <?= $beoor['aanwezig']?'✅':'❌' ?> &nbsp; <strong>Groep:</strong> <?= h($beoor['groep']?:'—') ?></p>
    <?php foreach(['communicatie'=>'Comm.','teamwork'=>'Team','conditie'=>'Cond.','water'=>'Water','attitude'=>'Att.'] as $k=>$l): $sc=(int)($beoor["score_$k"]??0); if(!$sc) continue; ?>
    <div style="display:flex;align-items:center;gap:8px;font-size:12px;margin-bottom:2px">
      <span style="width:50px;color:var(--grijs)"><?= $l ?>:</span>
      <span style="color:var(--oranje)"><?= str_repeat('★',$sc) ?></span><span style="color:var(--rand)"><?= str_repeat('★',5-$sc) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Eerdere seizoenen -->
  <?php if (!empty($vorige)): ?>
  <div class="paneel-sectie">
    <h3>Eerdere seizoenen</h3>
    <?php foreach($vorige as $vr): ?>
    <div style="font-size:12px;padding:5px 0;border-bottom:1px solid var(--rand)">
      <strong><?= h($vr['seizoen_naam']) ?></strong> — <span class="badge badge-<?= fase_kleur($vr['fase']) ?>"><?= fase_label($vr['fase']) ?></span>
      <span style="font-size:10px;color:var(--grijs);margin-left:4px"><?= datum_nl($vr['aangemeld_op']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Acties -->
  <div class="paneel-sectie paneel-acties">
    <h3>Acties</h3>
    <?php if($sol['fase']==='aangemeld'): ?>
    <button class="btn btn-primary" onclick="uitnodigVanUitPaneel(<?= $id ?>,'uitnodigen_selectie')">📧 Uitnodigen selectiedag</button>
    <?php elseif($sol['fase']==='selectiedag_geweest'): ?>
    <button class="btn btn-groen" onclick="uitnodigVanUitPaneel(<?= $id ?>,'uitnodigen_tw')">📧 Uitnodigen trainingsweekend</button>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/admin/berichten.php?ids=<?= (int)$id ?>" class="btn btn-outline">Bericht sturen</a>
    <a href="mailto:<?= h($sol['email']) ?>" class="btn btn-outline">✉️ E-mail</a>
    <?php if($sol['telefoon']): ?><a href="tel:<?= h($sol['telefoon']) ?>" class="btn btn-outline">📞 Bellen</a><?php endif; ?>
  </div>

  <!-- Verwijderen -->
  <div class="paneel-sectie" style="border-top:2px solid #fee2e2;padding-top:14px;margin-top:6px">
    <h3 style="color:var(--rood)">⚠️ Verwijderen</h3>
    <p style="font-size:12px;color:var(--tekst-zacht);margin-bottom:8px">Verwijdert de sollicitatie voor dit seizoen. Persoonsaccount blijft bewaard.</p>
    <button type="button" class="btn btn-rood"
            onclick="verwijderActie(<?= $id ?>,'verwijder_sollicitatie','Sollicitatie van <?= addslashes(h($naam)) ?> verwijderen?\n\nIncludes BLK, beoordeling en beschikbaarheid. Kan NIET ongedaan worden.')">
      🗑️ Verwijder sollicitatie
    </button>
  </div>
</div>
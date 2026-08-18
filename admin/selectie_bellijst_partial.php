<?php
/**
 * Bellijst — onderdeel van selectie_nabespreking.php
 * Toont beoordeelde kandidaten (door/niet) die nog niet zijn doorgevoerd.
 * Per persoon: bel → markeer gebeld (met notitie) → per persoon of samen doorvoeren.
 * Verwacht in scope: $bel_kandidaten, $besluiten, $sid, $tel_gebeld, $tel_ongebeld
 */
?>
<style>
.bl-rij { display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid var(--rand); border-radius:10px; margin-bottom:8px; background:var(--bg-card); flex-wrap:wrap; }
.bl-rij.gebeld { background:var(--groen-bg); border-color:var(--groen); }
.bl-naam { font-weight:600; font-size:15px; }
.bl-besluit { font-size:12px; font-weight:600; padding:2px 9px; border-radius:12px; }
.bl-besluit.door { background:var(--groen); color:#fff; }
.bl-besluit.niet { background:var(--rood); color:#fff; }
.bl-tel { font-size:14px; color:var(--tekst); }
.bl-tel a { color:var(--tel-blauw,#0a58ca); font-weight:600; text-decoration:none; }
.bl-notitie { flex:1; min-width:200px; padding:7px 10px; border:1px solid var(--rand); border-radius:8px; font-size:13px; }
.bl-knop { padding:8px 14px; border-radius:8px; font-size:13px; font-weight:600; border:1.5px solid var(--rand); background:var(--bg-card); cursor:pointer; }
.bl-knop.groen { background:var(--groen); border-color:var(--groen); color:#fff; }
.bl-knop.grijs { background:var(--grijs-bg); }
.bl-gebeld-info { font-size:12px; color:var(--tekst-zacht); }
</style>

<?php if (!$bel_kandidaten): ?>
  <div class="card"><div class="card-body" style="text-align:center;padding:32px;color:var(--tekst-zacht)">
    Nog geen kandidaten om te bellen.<br>
    <span style="font-size:13px">Zet eerst bij <a href="?tab=nabespreken" style="color:var(--navy)">Nabespreken</a> een besluit (door of niet) per kandidaat.</span>
  </div></div>
<?php else: ?>

<div style="background:var(--blauw-bg,#eef4fb);border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:13px;color:var(--tekst)">
  Bel iedereen persoonlijk met de uitslag. Vink af wie je hebt gesproken (met een korte notitie als je wilt).
  Je kunt per persoon meteen doorvoeren, of eerst iedereen bellen en daarna in één keer alle gebelde kandidaten doorvoeren.
</div>

<!-- Gebelde-samen-doorvoeren balk -->
<?php if ($tel_gebeld): ?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;background:var(--groen-bg);border:1px solid var(--groen);border-radius:10px;margin-bottom:14px;flex-wrap:wrap">
  <div style="font-size:13px"><strong><?= $tel_gebeld ?></strong> kandidaat(en) gebeld en nog niet doorgevoerd.</div>
  <form method="POST" onsubmit="return confirm('<?= $tel_gebeld ?> gebelde kandidaten doorvoeren naar hun nieuwe fase?')">
    <?= csrf_input() ?>
    <input type="hidden" name="actie" value="gebelde_doorvoeren">
    <button type="submit" class="btn btn-primary">Alle gebelde doorvoeren →</button>
  </form>
</div>
<?php endif; ?>

<?php foreach ($bel_kandidaten as $k):
  $sol_id = (int)$k['sol_id'];
  $b = $besluiten[$sol_id] ?? [];
  $besluit = $b['besluit'] ?? '';
  $is_gebeld = !empty($b['gebeld_op']);
  $tel_nr = $k['telefoon'] ?? '';
  $naam = $k['naam'] ?? trim(($k['voornaam'] ?? '') . ' ' . ($k['achternaam'] ?? ''));
?>
<div class="bl-rij <?= $is_gebeld?'gebeld':'' ?>" id="bl-<?= $sol_id ?>">
  <div style="min-width:150px">
    <div class="bl-naam"><?= h($naam) ?></div>
    <span class="bl-besluit <?= $besluit ?>"><?= $besluit==='door'?'✓ Door':'✕ Niet' ?></span>
  </div>

  <?php if ($tel_nr): ?>
    <div class="bl-tel">📞 <a href="tel:<?= h(preg_replace('/\s+/','',$tel_nr)) ?>"><?= h($tel_nr) ?></a></div>
  <?php else: ?>
    <div class="bl-tel" style="color:var(--tekst-zacht);font-size:12.5px">geen nummer</div>
  <?php endif; ?>

  <?php if ($is_gebeld): ?>
    <div style="flex:1;min-width:180px">
      <div class="bl-gebeld-info">✓ Gebeld<?= !empty($b['gebeld_door'])?' door '.h($b['gebeld_door']):'' ?><?= !empty($b['gebeld_notitie'])?' — '.h($b['gebeld_notitie']):'' ?></div>
    </div>
    <button type="button" class="bl-knop grijs" onclick="belGebeldWissen(<?= $sol_id ?>)">Ongedaan</button>
    <button type="button" class="bl-knop groen" onclick="belDoorvoerenEen(<?= $sol_id ?>)">Doorvoeren →</button>
  <?php else: ?>
    <input type="text" class="bl-notitie" id="bl-notitie-<?= $sol_id ?>" placeholder="Notitie (optioneel), bijv. 'wil bedenktijd'">
    <button type="button" class="bl-knop" onclick="belGebeld(<?= $sol_id ?>, false)">Gebeld</button>
    <button type="button" class="bl-knop groen" onclick="belGebeld(<?= $sol_id ?>, true)">Gebeld + doorvoeren →</button>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<script>
const CSRF_BEL = '<?= csrf_token() ?>';

function belGebeld(solId, meteen) {
  const notitie = document.getElementById('bl-notitie-' + solId)?.value || '';
  fetch(location.pathname, {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ actie:'gebeld', sol_id:solId, gebeld_notitie:notitie, meteen_doorvoeren: meteen?'1':'', _csrf:CSRF_BEL })
  }).then(r => r.json()).then(() => location.reload());
}
function belGebeldWissen(solId) {
  fetch(location.pathname, {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ actie:'gebeld_wissen', sol_id:solId, _csrf:CSRF_BEL })
  }).then(r => r.json()).then(() => location.reload());
}
function belDoorvoerenEen(solId) {
  if (!confirm('Deze kandidaat doorvoeren naar de nieuwe fase?')) return;
  fetch(location.pathname, {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ actie:'doorvoeren_een', sol_id:solId, _csrf:CSRF_BEL })
  }).then(r => r.json()).then(() => location.reload());
}
</script>

<?php endif; ?>

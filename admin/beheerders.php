<?php
require_once __DIR__.'/../includes/functions.php';
$pagina_titel = 'Beheerders'; $actieve_nav = 'beheerders';
vereist_rol('admin');

$db = db();
$fout = ''; 

// ── Verwerken ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'nieuw') {
        $naam  = trim($_POST['naam']);
        $email = strtolower(trim($_POST['email']));
        $ww    = $_POST['wachtwoord'];
        $rol   = $_POST['rol'];
        if (!$naam||!$email||!$ww) { $fout='Vul alle velden in.'; }
        elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $fout='Ongeldig e-mailadres.'; }
        else {
            try {
                $hash = password_hash($ww, PASSWORD_DEFAULT);
                $db->prepare("INSERT INTO beheerders (naam,email,wachtwoord,rol) VALUES(?,?,?,?)")->execute([$naam,$email,$hash,$rol]);
                // Wie toegang krijgt tot het beheer hoort herleidbaar te zijn. Nooit het wachtwoord.
                audit_log('beheerder_aangemaakt', 'beheerder', (int)$db->lastInsertId(), ['naam' => $naam, 'email' => $email, 'rol' => $rol]);
                flash('succes', "$naam toegevoegd.");
            } catch (Exception $e) { $fout = 'E-mailadres bestaat al.'; }
        }
    }

    if ($actie === 'wachtwoord') {
        $id = (int)$_POST['bid'];
        $ww = $_POST['nieuw_wachtwoord'];
        if (strlen($ww) < 8) { $fout = 'Wachtwoord minimaal 8 tekens.'; }
        else {
            $db->prepare("UPDATE beheerders SET wachtwoord=? WHERE id=?")->execute([password_hash($ww,PASSWORD_DEFAULT),$id]);
            // Alleen dát het gebeurde, nooit het wachtwoord zelf of de hash.
            audit_log('beheerder_gewijzigd', 'beheerder', $id, ['wachtwoord' => 'opnieuw ingesteld']);
            flash('succes','Wachtwoord bijgewerkt.');
        }
    }

    if ($actie === 'rol') {
        $id = (int)$_POST['bid'];
        $nieuwe_rol = (string)$_POST['rol'];
        $vq = $db->prepare("SELECT rol FROM beheerders WHERE id=?"); $vq->execute([$id]);
        $oude_rol = (string)$vq->fetchColumn();
        $db->prepare("UPDATE beheerders SET rol=? WHERE id=?")->execute([$nieuwe_rol,$id]);
        if ($oude_rol !== $nieuwe_rol) {
            audit_log('beheerder_gewijzigd', 'beheerder', $id, ['rol' => ['oud' => $oude_rol, 'nieuw' => $nieuwe_rol]]);
        }
        flash('succes','Rol bijgewerkt.');
    }

    if ($actie === 'deactiveer') {
        $id = (int)$_POST['bid'];
        $mij = (int)huidige_beheerder()['id'];
        if ($id === $mij) { $fout='Je kunt jezelf niet deactiveren.'; }
        else {
            $db->prepare("UPDATE beheerders SET actief=0 WHERE id=?")->execute([$id]);
            audit_log('beheerder_gewijzigd', 'beheerder', $id, ['actief' => ['oud' => 'ja', 'nieuw' => 'nee']]);
            flash('succes','Account gedeactiveerd.');
        }
    }

    if ($actie === 'activeer') {
        $id = (int)$_POST['bid'];
        $db->prepare("UPDATE beheerders SET actief=1 WHERE id=?")->execute([$id]);
        audit_log('beheerder_gewijzigd', 'beheerder', $id, ['actief' => ['oud' => 'nee', 'nieuw' => 'ja']]);
        flash('succes','Account geactiveerd.');
    }

    if (!$fout) redirect(APP_URL.'/admin/beheerders.php');
}

$beheerders = $db->query("SELECT * FROM beheerders ORDER BY actief DESC, rol DESC, naam")->fetchAll();
require __DIR__.'/layout_top.php';
?>

<?php if ($fout): ?><div class="flash flash-fout"><?= h($fout) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

<!-- Beheerders lijst -->
<div class="card">
  <div class="card-header"><div class="card-title">Beheerders (<?= count($beheerders) ?>)</div></div>
  <div class="tabel-wrap">
    <table class="data-tabel">
      <thead><tr><th>Naam</th><th>Rol</th><th>Login via</th><th>Laatste login</th><th style="width:140px">Acties</th></tr></thead>
      <tbody>
      <?php foreach ($beheerders as $b): ?>
      <tr style="<?= !$b['actief']?'opacity:.55':'' ?>">
        <td>
          <div style="font-weight:600;font-size:13px">
            <?= h($b['naam']) ?>
            <?php if (!$b['actief']): ?>
            <span class="badge badge-grijs" style="font-size:9px;margin-left:6px">INACTIEF</span>
            <?php endif; ?>
          </div>
          <div style="font-size:11px;color:var(--grijs)"><?= h($b['email']) ?></div>
        </td>
        <td>
          <form method="POST" style="display:inline">
      <?= csrf_input() ?>
            <input type="hidden" name="actie" value="rol">
            <input type="hidden" name="bid" value="<?= $b['id'] ?>">
            <select name="rol" onchange="this.form.submit()" style="font-size:12px;padding:3px 6px;width:auto">
              <?php foreach (rollen_alle() as $_rol): ?>
                <option value="<?= h($_rol['sleutel']) ?>" <?= $b['rol']===$_rol['sleutel']?'selected':'' ?>><?= h($_rol['naam']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td style="font-size:12px;color:var(--grijs)">
          <?= $b['wachtwoord'] ? '🔑 Wachtwoord' : '' ?>
          <?= $b['authentik_uid'] ? '🔐 Authentik' : '' ?>
          <?= (!$b['wachtwoord'] && !$b['authentik_uid']) ? '—' : '' ?>
        </td>
        <td style="font-size:12px;color:var(--grijs)"><?= $b['laatste_login'] ? datum_nl(substr($b['laatste_login'],0,10)) : '—' ?></td>
        <td>
          <div style="display:flex;gap:4px;flex-wrap:wrap">
            <button onclick="document.getElementById('ww-<?= $b['id'] ?>').style.display=document.getElementById('ww-<?= $b['id'] ?>').style.display==='none'?'block':'none'" class="btn btn-outline btn-sm">🔑</button>
            <form method="POST" style="display:inline">
      <?= csrf_input() ?>
              <input type="hidden" name="actie" value="<?= $b['actief']?'deactiveer':'activeer' ?>">
              <input type="hidden" name="bid" value="<?= $b['id'] ?>">
              <button type="submit" class="btn btn-sm <?= $b['actief']?'btn-rood':'btn-groen' ?>" onclick="return confirm('<?= $b['actief']?'Deactiveren':'Activeren' ?>?')"><?= $b['actief']?'✕':'✓' ?></button>
            </form>
          </div>
          <!-- Wachtwoord wijzigen inline -->
          <div id="ww-<?= $b['id'] ?>" style="display:none;margin-top:8px">
            <form method="POST">
      <?= csrf_input() ?>
              <input type="hidden" name="actie" value="wachtwoord">
              <input type="hidden" name="bid" value="<?= $b['id'] ?>">
              <div style="display:flex;gap:4px">
                <input type="password" name="nieuw_wachtwoord" placeholder="Nieuw wachtwoord" minlength="8" style="font-size:12px;padding:5px 8px">
                <button type="submit" class="btn btn-primary btn-sm">Sla op</button>
              </div>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Nieuw account -->
<div>
  <div class="card">
    <div class="card-header"><div class="card-title">Nieuw account aanmaken</div></div>
    <div class="card-body">
      <form method="POST">
      <?= csrf_input() ?>
        <input type="hidden" name="actie" value="nieuw">
        <div class="form-groep"><label>Naam</label><input type="text" name="naam" required placeholder="Jan de Vries"></div>
        <div class="form-groep"><label>E-mailadres</label><input type="email" name="email" required placeholder="jan@paal13.com"></div>
        <div class="form-groep"><label>Tijdelijk wachtwoord</label><input type="password" name="wachtwoord" required minlength="8" placeholder="Minimaal 8 tekens"><div class="form-hulp">Geef dit door aan de persoon — zij kunnen het later wijzigen.</div></div>
        <div class="form-groep"><label>Rol</label>
          <select name="rol">
            <?php foreach (rollen_alle() as $_rol): ?>
              <option value="<?= h($_rol['sleutel']) ?>" <?= $_rol['sleutel']==='bestuur'?'selected':'' ?>><?= h($_rol['naam']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Account aanmaken</button>
      </form>
    </div>
  </div>

  <?php if (authentik_geconfigureerd()): ?>
  <div class="card" style="margin-top:14px;border-left:3px solid var(--groen)">
    <div class="card-body" style="padding:14px 16px">
      <div style="font-size:13px;font-weight:600;color:var(--navy);margin-bottom:4px">🔐 Authentik SSO actief</div>
      <p style="font-size:12px;color:var(--tekst-zacht)">Iemand die via Authentik inlogt krijgt automatisch een account (rol: bestuur). Geef ze admin via de tabel hierboven. Groep <code>paal13-admin</code> in Authentik → automatisch admin.</p>
    </div>
  </div>
  <?php else: ?>
  <div class="card" style="margin-top:14px;border-left:3px solid var(--grijs)">
    <div class="card-body" style="padding:14px 16px">
      <div style="font-size:13px;font-weight:600;color:var(--navy);margin-bottom:4px">Authentik SSO niet ingesteld</div>
      <p style="font-size:12px;color:var(--tekst-zacht)">Vul de OIDC-gegevens (OIDC_CLIENT_ID, OIDC_CLIENT_SECRET, OIDC_ISSUER) in <code>includes/config.php</code> in om SSO in te schakelen; zie het runbook voor systeembeheer.</p>
    </div>
  </div>
  <?php endif; ?>
</div>

</div>

<?php require __DIR__.'/layout_bottom.php'; ?>

<?php
require_once __DIR__.'/../includes/functions.php';
$pagina_titel = 'Rollen & rechten'; $actieve_nav = 'rollen';
vereist_rol('admin');

rechten_auto_setup();
$db = db();
$fout = '';

// ── Verwerken ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'rol_nieuw') {
        $naam = trim($_POST['naam'] ?? '');
        $groep = trim($_POST['authentik_groep'] ?? '');
        $rang = max(1, min(2, (int)($_POST['rang'] ?? 1))); // nieuwe rollen: rang 1 of 2 (nooit admin-rang)
        $sleutel = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $naam));
        if ($naam === '' || $sleutel === '') {
            $fout = 'Geef de rol een naam.';
        } else {
            try {
                $db->prepare("INSERT INTO rollen (sleutel, naam, authentik_groep, rang, systeemrol) VALUES (?,?,?,?,0)")
                   ->execute([$sleutel, $naam, $groep ?: null, $rang]);
                // Nieuwe rol start met alles op nee (veilig)
                foreach (rechten_modules() as $mod => $_m) {
                    $db->prepare("INSERT INTO rol_rechten (rol_sleutel, module, lezen, bewerken, verwijderen) VALUES (?,?,0,0,0)")
                       ->execute([$sleutel, $mod]);
                }
                flash('succes', "Rol \"$naam\" toegevoegd. Stel hieronder de rechten in.");
            } catch (Exception $e) {
                $fout = 'Er bestaat al een rol met (bijna) dezelfde naam.';
            }
        }
        if (!$fout) { redirect(APP_URL.'/admin/rollen.php'); }
    }

    if ($actie === 'rol_bewerken') {
        $sleutel = $_POST['sleutel'] ?? '';
        $naam = trim($_POST['naam'] ?? '');
        $groep = trim($_POST['authentik_groep'] ?? '');
        if ($sleutel && $naam) {
            // Systeemrollen: naam niet wijzigen, wel de Authentik-groep
            $is_systeem = (int)$db->query("SELECT systeemrol FROM rollen WHERE sleutel=".$db->quote($sleutel))->fetchColumn();
            if ($is_systeem) {
                $db->prepare("UPDATE rollen SET authentik_groep=? WHERE sleutel=?")->execute([$groep ?: null, $sleutel]);
            } else {
                $db->prepare("UPDATE rollen SET naam=?, authentik_groep=? WHERE sleutel=?")->execute([$naam, $groep ?: null, $sleutel]);
            }
            flash('succes', 'Rol bijgewerkt.');
        }
        redirect(APP_URL.'/admin/rollen.php');
    }

    if ($actie === 'rol_verwijderen') {
        $sleutel = $_POST['sleutel'] ?? '';
        $is_systeem = (int)$db->query("SELECT systeemrol FROM rollen WHERE sleutel=".$db->quote($sleutel))->fetchColumn();
        $q = $db->prepare("SELECT COUNT(*) FROM beheerders WHERE rol=?"); $q->execute([$sleutel]);
        $aantal_gebruikers = (int)$q->fetchColumn();
        if ($is_systeem) {
            flash('fout', 'Systeemrollen (admin, bestuur, begeleider) kunnen niet verwijderd worden.');
        } elseif ($aantal_gebruikers > 0) {
            flash('fout', "Deze rol is nog aan $aantal_gebruikers beheerder(s) gekoppeld. Wijzig die eerst.");
        } else {
            $db->prepare("DELETE FROM rol_rechten WHERE rol_sleutel=?")->execute([$sleutel]);
            $db->prepare("DELETE FROM rollen WHERE sleutel=?")->execute([$sleutel]);
            flash('succes', 'Rol verwijderd.');
        }
        redirect(APP_URL.'/admin/rollen.php');
    }

    if ($actie === 'rechten_opslaan') {
        // De hele matrix in één keer opslaan
        $matrix = $_POST['recht'] ?? []; // recht[rol_sleutel][module][actie] = 1
        $rollen = rollen_alle();
        foreach ($rollen as $rol) {
            if ($rol['sleutel'] === 'admin') continue; // admin blijft altijd alles
            foreach (rechten_modules() as $mod => $mdef) {
                $l = !empty($matrix[$rol['sleutel']][$mod]['lezen']) ? 1 : 0;
                $w = !empty($matrix[$rol['sleutel']][$mod]['bewerken']) ? 1 : 0;
                $d = !empty($matrix[$rol['sleutel']][$mod]['verwijderen']) ? 1 : 0;
                // bewerken/verwijderen impliceert lezen (consistentie)
                if ($w || $d) $l = 1;
                $db->prepare("INSERT INTO rol_rechten (rol_sleutel, module, lezen, bewerken, verwijderen) VALUES (?,?,?,?,?)
                              ON DUPLICATE KEY UPDATE lezen=VALUES(lezen), bewerken=VALUES(bewerken), verwijderen=VALUES(verwijderen)")
                   ->execute([$rol['sleutel'], $mod, $l, $w, $d]);
            }
        }
        flash('succes', 'Rechten opgeslagen.');
        redirect(APP_URL.'/admin/rollen.php');
    }
}

$rollen = rollen_alle();
$modules = rechten_modules();
$acties = rechten_acties();

// Rechten per rol vooraf laden
$rechten_per_rol = [];
foreach ($rollen as $rol) {
    $rechten_per_rol[$rol['sleutel']] = rol_rechten($rol['sleutel']);
}

// Modules groeperen voor de weergave
$modules_per_groep = [];
foreach ($modules as $mod => $mdef) {
    $modules_per_groep[$mdef['groep']][$mod] = $mdef;
}

require __DIR__ . '/layout_top.php';
?>

<style>
.rol-kaart { border:1px solid var(--rand); border-radius:12px; padding:14px 16px; margin-bottom:10px; background:var(--bg-card); }
.rol-kop { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.rol-badge { font-size:11px; font-weight:600; padding:2px 9px; border-radius:12px; background:var(--grijs-bg); color:var(--tekst-zacht); }
.rol-badge.systeem { background:var(--navy); color:#fff; }
.matrix-tabel { width:100%; border-collapse:collapse; font-size:13px; }
.matrix-tabel th, .matrix-tabel td { padding:7px 8px; text-align:center; border-bottom:1px solid var(--rand); }
.matrix-tabel th.mod, .matrix-tabel td.mod { text-align:left; font-weight:500; white-space:nowrap; }
.matrix-tabel thead th { font-size:11px; color:var(--tekst-zacht); font-weight:600; }
.matrix-groep td { background:var(--grijs-bg); font-weight:600; font-size:12px; color:var(--tekst-zacht); text-align:left; }
.matrix-tabel input[type=checkbox] { width:17px; height:17px; cursor:pointer; }
.rol-kolom { min-width:70px; }
.matrix-wrap { overflow-x:auto; }
.mini-knop { font-size:12px; padding:4px 10px; border-radius:7px; border:1px solid var(--rand); background:var(--bg-card); cursor:pointer; color:var(--tekst); }
</style>

<div style="max-width:1100px">

  <p style="font-size:13px;color:var(--tekst-zacht);margin:0 0 16px">
    Hier bepaal je per rol wie wat mag. De rechten gelden per onderdeel: <strong>lezen</strong> (bekijken),
    <strong>bewerken</strong> (aanpassen/toevoegen) en <strong>verwijderen</strong>. De rol <strong>Admin</strong>
    heeft altijd volledige toegang en kan niet worden ingeperkt — zo sluit je jezelf nooit buiten.
    Rollen koppel je aan een Authentik-groep, zodat mensen die via SSO inloggen automatisch de juiste rol krijgen.
  </p>

  <!-- Rollen beheren -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-body">
      <h2 style="margin:0 0 12px;font-size:16px">Rollen</h2>

      <?php foreach ($rollen as $rol): ?>
        <?php
          $q = $db->prepare("SELECT COUNT(*) FROM beheerders WHERE rol=?"); $q->execute([$rol['sleutel']]);
          $aantal = (int)$q->fetchColumn();
        ?>
        <div class="rol-kaart">
          <div class="rol-kop">
            <div>
              <strong style="font-size:15px"><?= h($rol['naam']) ?></strong>
              <?php if ($rol['systeemrol']): ?><span class="rol-badge systeem">systeemrol</span><?php endif; ?>
              <span class="rol-badge">rang <?= (int)$rol['rang'] ?></span>
              <span class="rol-badge"><?= $aantal ?> gebruiker<?= $aantal===1?'':'s' ?></span>
              <div style="font-size:12px;color:var(--tekst-zacht);margin-top:3px">
                Authentik-groep: <strong><?= $rol['authentik_groep'] ? h($rol['authentik_groep']) : '— niet gekoppeld —' ?></strong>
              </div>
            </div>
            <div style="display:flex;gap:6px">
              <button type="button" class="mini-knop" onclick="bewerkRol('<?= h($rol['sleutel']) ?>', '<?= h(addslashes($rol['naam'])) ?>', '<?= h(addslashes($rol['authentik_groep'] ?? '')) ?>', <?= (int)$rol['systeemrol'] ?>)">Bewerken</button>
              <?php if (!$rol['systeemrol']): ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Rol \'<?= h($rol['naam']) ?>\' verwijderen?')">
                  <?= csrf_input() ?>
                  <input type="hidden" name="actie" value="rol_verwijderen">
                  <input type="hidden" name="sleutel" value="<?= h($rol['sleutel']) ?>">
                  <button type="submit" class="mini-knop" style="color:var(--rood,#b91c1c)">Verwijderen</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- Nieuwe rol toevoegen -->
      <details style="margin-top:12px">
        <summary style="cursor:pointer;font-size:14px;font-weight:500;color:var(--navy)">+ Nieuwe rol toevoegen</summary>
        <form method="POST" style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:end">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="rol_nieuw">
          <div>
            <label style="display:block;font-size:12px;color:var(--tekst-zacht);margin-bottom:3px">Naam</label>
            <input type="text" name="naam" required placeholder="bijv. Coördinator" style="padding:7px 10px;border:1px solid var(--rand);border-radius:7px">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--tekst-zacht);margin-bottom:3px">Authentik-groep</label>
            <input type="text" name="authentik_groep" placeholder="bijv. Sol-Coordinator" style="padding:7px 10px;border:1px solid var(--rand);border-radius:7px">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:var(--tekst-zacht);margin-bottom:3px">Rang</label>
            <select name="rang" style="padding:7px 10px;border:1px solid var(--rand);border-radius:7px">
              <option value="1">1 — basis</option>
              <option value="2">2 — uitgebreid</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Rol toevoegen</button>
        </form>
        <p style="font-size:12px;color:var(--tekst-zacht);margin-top:8px">
          Een nieuwe rol begint met alle rechten uit (veilig). Zet daarna de vinkjes in de matrix hieronder.
        </p>
      </details>
    </div>
  </div>

  <!-- De rechtenmatrix -->
  <div class="card">
    <div class="card-body">
      <h2 style="margin:0 0 4px;font-size:16px">Rechtenmatrix</h2>
      <p style="font-size:12.5px;color:var(--tekst-zacht);margin:0 0 14px">
        Vink aan wat elke rol mag. "Bewerken" of "verwijderen" zet automatisch ook "lezen" aan.
        Admin staat vast op alles.
      </p>

      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="rechten_opslaan">

        <div class="matrix-wrap">
          <table class="matrix-tabel">
            <thead>
              <tr>
                <th class="mod" rowspan="2" style="vertical-align:bottom">Onderdeel</th>
                <?php foreach ($rollen as $rol): ?>
                  <th colspan="<?= count($acties) ?>" class="rol-kolom" style="border-bottom:2px solid var(--navy)">
                    <?= h($rol['naam']) ?>
                  </th>
                <?php endforeach; ?>
              </tr>
              <tr>
                <?php foreach ($rollen as $rol): ?>
                  <?php foreach ($acties as $ak => $al): ?>
                    <th style="font-size:10px"><?= h($al) ?></th>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($modules_per_groep as $groep => $mods): ?>
                <tr class="matrix-groep"><td colspan="<?= 1 + count($rollen)*count($acties) ?>"><?= h($groep) ?></td></tr>
                <?php foreach ($mods as $mod => $mdef): ?>
                  <tr>
                    <td class="mod"><?= h($mdef['label']) ?></td>
                    <?php foreach ($rollen as $rol): ?>
                      <?php
                        $is_admin = ($rol['sleutel'] === 'admin');
                        $r = $rechten_per_rol[$rol['sleutel']][$mod] ?? ['lezen'=>false,'bewerken'=>false,'verwijderen'=>false];
                      ?>
                      <?php foreach ($acties as $ak => $al): ?>
                        <td>
                          <?php if (!in_array($ak, $mdef['acties'], true)): ?>
                            <span style="color:var(--rand)">–</span>
                          <?php elseif ($is_admin): ?>
                            <input type="checkbox" checked disabled title="Admin heeft altijd volledige toegang">
                          <?php else: ?>
                            <input type="checkbox"
                                   name="recht[<?= h($rol['sleutel']) ?>][<?= h($mod) ?>][<?= h($ak) ?>]"
                                   value="1" <?= !empty($r[$ak]) ? 'checked' : '' ?>
                                   data-rol="<?= h($rol['sleutel']) ?>" data-mod="<?= h($mod) ?>" data-actie="<?= h($ak) ?>">
                          <?php endif; ?>
                        </td>
                      <?php endforeach; ?>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
          <button type="submit" class="btn btn-primary">Rechten opslaan</button>
          <span style="font-size:12px;color:var(--tekst-zacht)">Alle wijzigingen in de matrix worden in één keer opgeslagen.</span>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bewerk-rol dialoog (simpel) -->
<div id="rolBewerkModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--bg-card);border-radius:12px;padding:22px;max-width:420px;width:90%">
    <h3 style="margin:0 0 14px">Rol bewerken</h3>
    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="rol_bewerken">
      <input type="hidden" name="sleutel" id="mb_sleutel">
      <div style="margin-bottom:12px">
        <label style="display:block;font-size:12px;color:var(--tekst-zacht);margin-bottom:3px">Naam</label>
        <input type="text" name="naam" id="mb_naam" style="width:100%;padding:8px 10px;border:1px solid var(--rand);border-radius:7px">
        <span id="mb_naam_hint" style="font-size:11px;color:var(--tekst-zacht);display:none">Systeemrol — naam kan niet worden gewijzigd.</span>
      </div>
      <div style="margin-bottom:16px">
        <label style="display:block;font-size:12px;color:var(--tekst-zacht);margin-bottom:3px">Authentik-groep</label>
        <input type="text" name="authentik_groep" id="mb_groep" style="width:100%;padding:8px 10px;border:1px solid var(--rand);border-radius:7px" placeholder="bijv. Bestuur">
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="mini-knop" onclick="document.getElementById('rolBewerkModal').style.display='none'">Annuleren</button>
        <button type="submit" class="btn btn-primary">Opslaan</button>
      </div>
    </form>
  </div>
</div>

<script>
function bewerkRol(sleutel, naam, groep, isSysteem) {
  document.getElementById('mb_sleutel').value = sleutel;
  document.getElementById('mb_naam').value = naam;
  document.getElementById('mb_groep').value = groep;
  const naamVeld = document.getElementById('mb_naam');
  naamVeld.readOnly = !!isSysteem;
  document.getElementById('mb_naam_hint').style.display = isSysteem ? 'block' : 'none';
  document.getElementById('rolBewerkModal').style.display = 'flex';
}
// bewerken/verwijderen aanvinken → lezen automatisch mee aanzetten
document.querySelectorAll('.matrix-tabel input[type=checkbox]').forEach(cb => {
  cb.addEventListener('change', function() {
    if ((this.dataset.actie === 'bewerken' || this.dataset.actie === 'verwijderen') && this.checked) {
      const lees = document.querySelector('input[data-rol="'+this.dataset.rol+'"][data-mod="'+this.dataset.mod+'"][data-actie="lezen"]');
      if (lees) lees.checked = true;
    }
  });
});
</script>

<?php require __DIR__ . '/layout_bottom.php'; ?>

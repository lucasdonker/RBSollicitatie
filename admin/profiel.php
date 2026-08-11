<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Mijn profiel';
$actieve_nav  = 'profiel';
vereist_login();
$b = huidige_beheerder();
$fout = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'naam') {
        $naam = trim($_POST['naam'] ?? '');
        if ($naam) {
            db()->prepare("UPDATE beheerders SET naam=? WHERE id=?")->execute([$naam, $b['id']]);
            $_SESSION['bhd_naam'] = $naam;
            flash('succes', 'Naam bijgewerkt.');
            redirect(APP_URL . '/admin/profiel.php');
        }
    }

    if ($actie === 'wachtwoord') {
        $oud     = $_POST['oud_wachtwoord'] ?? '';
        $nieuw   = $_POST['nieuw_wachtwoord'] ?? '';
        $herhaal = $_POST['herhaal_wachtwoord'] ?? '';
        $stmt_rec = db()->prepare("SELECT * FROM beheerders WHERE id=?");
        $stmt_rec->execute([$b['id']]);
        $rec = $stmt_rec->fetch();
        if (!$rec['wachtwoord']) {
            $fout = 'Je account heeft geen wachtwoord (alleen SSO). Vraag een admin om er een in te stellen.';
        } elseif (!password_verify($oud, $rec['wachtwoord'])) {
            $fout = 'Huidig wachtwoord onjuist.';
        } elseif (strlen($nieuw) < 8) {
            $fout = 'Nieuw wachtwoord minimaal 8 tekens.';
        } elseif ($nieuw !== $herhaal) {
            $fout = 'Wachtwoorden komen niet overeen.';
        } else {
            db()->prepare("UPDATE beheerders SET wachtwoord=? WHERE id=?")->execute([password_hash($nieuw, PASSWORD_DEFAULT), $b['id']]);
            flash('succes', 'Wachtwoord gewijzigd.');
            redirect(APP_URL . '/admin/profiel.php');
        }
    }
}

$rec = db()->query("SELECT * FROM beheerders WHERE id=" . (int)$b['id'])->fetch();
require __DIR__ . '/layout_top.php';
?>

<?php if ($fout): ?><div class="flash flash-fout"><?= h($fout) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;max-width:700px;align-items:start">

<div class="card">
  <div class="card-header"><div class="card-title">Mijn gegevens</div></div>
  <div class="card-body">
    <div style="margin-bottom:14px">
      <div style="font-size:11px;color:var(--grijs);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">E-mailadres</div>
      <div style="font-size:14px;font-weight:500"><?= h($rec['email']) ?></div>
    </div>
    <div style="margin-bottom:14px">
      <div style="font-size:11px;color:var(--grijs);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Rol</div>
      <div style="font-size:14px;font-weight:500"><?= h(ucfirst($rec['rol'])) ?></div>
    </div>
    <div style="margin-bottom:14px">
      <div style="font-size:11px;color:var(--grijs);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Login via</div>
      <div style="font-size:14px"><?= $rec['authentik_uid'] ? '🔐 Authentik' : '' ?><?= $rec['wachtwoord'] ? '🔑 Wachtwoord' : '' ?></div>
    </div>
    <hr style="border:none;border-top:1px solid var(--rand);margin:14px 0">
    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="naam">
      <div class="form-groep">
        <label>Weergavenaam</label>
        <input type="text" name="naam" value="<?= h($rec['naam']) ?>" required>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Naam opslaan</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><div class="card-title">Wachtwoord wijzigen</div></div>
  <div class="card-body">
    <?php if (!$rec['wachtwoord']): ?>
    <div class="flash flash-info">Je account heeft (nog) geen wachtwoord. Vraag een admin om er een in te stellen.</div>
    <?php else: ?>
    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="actie" value="wachtwoord">
      <div class="form-groep"><label>Huidig wachtwoord</label><input type="password" name="oud_wachtwoord" required autocomplete="current-password"></div>
      <div class="form-groep"><label>Nieuw wachtwoord</label><input type="password" name="nieuw_wachtwoord" required minlength="8" autocomplete="new-password"></div>
      <div class="form-groep"><label>Herhaal nieuw wachtwoord</label><input type="password" name="herhaal_wachtwoord" required minlength="8" autocomplete="new-password"></div>
      <button type="submit" class="btn btn-primary btn-sm">Wachtwoord wijzigen</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- Voorkeuren -->
<div class="card" style="grid-column:1/-1">
  <div class="card-header"><div class="card-title">Voorkeuren</div></div>
  <div class="card-body">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <div style="font-size:14px;font-weight:500;color:var(--tekst);margin-bottom:2px">
          Donker thema
        </div>
        <div style="font-size:12px;color:var(--tekst-zacht)">
          Gebruik een donker kleurenpalet. Wordt lokaal opgeslagen per apparaat.
        </div>
      </div>
      <button type="button" id="themeSwitch" onclick="toggleTheme()"
              aria-label="Donker thema aan/uit"
              style="position:relative;width:48px;height:26px;border-radius:13px;border:1px solid var(--rand);cursor:pointer;
                     background:var(--grijs-bg);transition:background .25s;flex-shrink:0;padding:0">
        <span id="themeDot"
              style="position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;
                     background:#fff;transition:transform .25s,background .25s;display:block;box-shadow:0 1px 3px rgba(0,0,0,.2)"></span>
      </button>
    </div>
  </div>
</div>

</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>
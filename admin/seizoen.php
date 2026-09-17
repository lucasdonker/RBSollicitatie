<?php
require_once __DIR__ . '/../includes/functions.php';
$pagina_titel = 'Seizoenbeheer';
$actieve_nav  = 'seizoen';
vereis_recht('seizoen', 'bewerken');

$db  = db();
$bhd = huidige_beheerder();

// Auto-migratie: alle seizoen-kolommen (één lijst, gedeeld met het portaal: includes/portaal_dossier.php)
seizoen_kolommen_verzeker();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actie = $_POST['actie'] ?? '';

    switch ($actie) {
        case 'nieuw':
            $naam = trim($_POST['naam'] ?? '');
            if ($naam) {
                $db->prepare("INSERT INTO seizoenen (naam, status) VALUES (?, 'gesloten')")->execute([$naam]);
                flash('succes', "Seizoen '$naam' aangemaakt. Stel nu de datums in.");
            }
            break;

        case 'update':
            $sid = (int)$_POST['seizoen_id'];
            try {
                $db->prepare("UPDATE seizoenen SET naam=?, datum_selectiedag=?, locatie_selectiedag=?,
                    selectiedag_verzameltijd=?, selectiedag_starttijd=?, selectiedag_eindtijd=?,
                    adres_selectiedag=?, selectiedag_meenemen=?,
                    datum_tw_start=?, datum_tw_eind=?, locatie_tw=?, tw_verzameltijd=?, tw_begintijd=?, tw_eindtijd=?,
                    adres_tw=?, tw_meenemen=?,
                    datum_werk_start=?, datum_werk_eind=?,
                    hoofdseizoen_start_week=?, hoofdseizoen_eind_week=?
                    WHERE id=?")
                   ->execute([
                       $_POST['naam'],
                       $_POST['datum_selectiedag'] ?: null,
                       $_POST['locatie_selectiedag'] ?: null,
                       $_POST['selectiedag_verzameltijd'] ?: null,
                       $_POST['selectiedag_starttijd'] ?: null,
                       $_POST['selectiedag_eindtijd'] ?: null,
                       trim($_POST['adres_selectiedag'] ?? '') ?: null,
                       trim($_POST['selectiedag_meenemen'] ?? '') ?: null,
                       $_POST['datum_tw_start'] ?: null,
                       $_POST['datum_tw_eind'] ?: null,
                       $_POST['locatie_tw'] ?: null,
                       $_POST['tw_verzameltijd'] ?: null,
                       $_POST['tw_begintijd'] ?: null,
                       $_POST['tw_eindtijd'] ?: null,
                       trim($_POST['adres_tw'] ?? '') ?: null,
                       trim($_POST['tw_meenemen'] ?? '') ?: null,
                       $_POST['datum_werk_start'] ?: null,
                       $_POST['datum_werk_eind'] ?: null,
                       (int)($_POST['hoofdseizoen_start_week'] ?? 0) ?: null,
                       (int)($_POST['hoofdseizoen_eind_week'] ?? 0) ?: null,
                       $sid,
                   ]);
            } catch (Exception $e) {
                // Fallback: basisvelden
                $db->prepare("UPDATE seizoenen SET naam=?, datum_selectiedag=?, locatie_selectiedag=?,
                    datum_tw_start=?, datum_tw_eind=?, locatie_tw=? WHERE id=?")
                   ->execute([$_POST['naam'], $_POST['datum_selectiedag']?:null, $_POST['locatie_selectiedag']?:null,
                               $_POST['datum_tw_start']?:null, $_POST['datum_tw_eind']?:null, $_POST['locatie_tw']?:null, $sid]);
            }
            flash('succes', 'Seizoen bijgewerkt.');
            break;

        case 'open':
            $db->prepare("UPDATE seizoenen SET status='open' WHERE id=?")->execute([(int)$_POST['seizoen_id']]);
            flash('succes', '✅ Seizoen geopend — aanmeldformulier is nu actief.');
            break;

        case 'sluit':
            $db->prepare("UPDATE seizoenen SET status='gesloten' WHERE id=?")->execute([(int)$_POST['seizoen_id']]);
            flash('succes', '🔒 Seizoen gesloten.');
            break;

        case 'verwijder':
            vereist_rol('admin');
            $sid   = (int)$_POST['seizoen_id'];
            $naam  = $db->prepare("SELECT naam FROM seizoenen WHERE id=?")->execute([$sid]) ? null : null;
            $stmt  = $db->prepare("SELECT naam FROM seizoenen WHERE id=?");
            $stmt->execute([$sid]);
            $naam  = $stmt->fetchColumn();
            $tel   = $db->prepare("SELECT COUNT(*) FROM sollicitaties WHERE seizoen_id=?");
            $tel->execute([$sid]);
            $aantal = (int)$tel->fetchColumn();

            if ($aantal > 0 && !isset($_POST['bevestig_verwijder_data'])) {
                // Stuur terug met foutmelding — bevestiging vereist
                flash('fout', "Seizoen '$naam' heeft nog $aantal sollicitatie(s). Bevestig dat je de data wil verwijderen.");
                redirect(APP_URL . '/admin/seizoen.php?verwijder_bevestig=' . $sid);
            } else {
                // Verwijder alles (FK CASCADE doet de rest)
                $db->prepare("DELETE FROM sollicitaties WHERE seizoen_id=?")->execute([$sid]);
                $db->prepare("DELETE FROM seizoenen WHERE id=?")->execute([$sid]);
                flash('succes', "Seizoen '$naam' en alle bijbehorende data verwijderd.");
            }
            break;
    }

    redirect(APP_URL . '/admin/seizoen.php');
}

$seizoenen = $db->query("
    SELECT s.*, (SELECT COUNT(*) FROM sollicitaties WHERE seizoen_id=s.id) n
    FROM seizoenen s ORDER BY s.id DESC
")->fetchAll();

$huidig            = $seizoenen[0] ?? null;
$verwijder_bevestig = (int)($_GET['verwijder_bevestig'] ?? 0);
require __DIR__ . '/layout_top.php';
?>

<!-- Status banner huidig seizoen -->
<?php if ($huidig): ?>
<div class="card" style="margin-bottom:20px;border-left:5px solid <?= $huidig['status']==='open'?'var(--groen)':'var(--grijs)' ?>">
  <div class="card-body" style="padding:16px 20px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-size:18px;font-weight:700;color:var(--navy)"><?= h($huidig['naam']) ?></div>
        <div style="font-size:13px;margin-top:3px">
          <?php if ($huidig['status']==='open'): ?>
          <span style="color:var(--groen);font-weight:600">✅ Open</span> — sollicitanten kunnen zich aanmelden
          <?php else: ?>
          <span style="color:var(--grijs);font-weight:600">🔒 Gesloten</span> — aanmeldformulier is niet actief
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($huidig['status']==='open'): ?>
        <form method="POST" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="sluit">
          <input type="hidden" name="seizoen_id" value="<?= $huidig['id'] ?>">
          <button type="submit" class="btn btn-rood"
                  onclick="return confirm('Seizoen sluiten?')">🔒 Sluiten</button>
        </form>
        <?php else: ?>
        <form method="POST" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="actie" value="open">
          <input type="hidden" name="seizoen_id" value="<?= $huidig['id'] ?>">
          <button type="submit" class="btn btn-groen"
                  onclick="return confirm('Seizoen openen?')">✅ Openen</button>
        </form>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/aanmelden.php" target="_blank" class="btn btn-outline btn-sm">
          Aanmeldformulier ↗
        </a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php // Verwijder bevestiging bij seizoen met data
if ($verwijder_bevestig): $sz_bevestig = null;
foreach ($seizoenen as $sz) { if ($sz['id'] === $verwijder_bevestig) { $sz_bevestig = $sz; break; } }
if ($sz_bevestig): ?>
<div class="card" style="margin-bottom:16px;border:2px solid var(--rood)">
  <div class="card-header" style="background:#fff5f5">
    <div class="card-title" style="color:var(--rood)">⚠️ Bevestiging vereist: seizoen met data verwijderen</div>
  </div>
  <div class="card-body">
    <p style="font-size:14px;margin-bottom:12px">
      Seizoen <strong><?= h($sz_bevestig['naam']) ?></strong> heeft
      <strong><?= $sz_bevestig['n'] ?> sollicitatie(s)</strong> met alle bijbehorende data
      (BLK-formulieren, beoordelingen, beschikbaarheid). Dit wordt allemaal definitief verwijderd.
    </p>
    <div style="display:flex;gap:10px">
      <form method="POST" style="display:inline">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="verwijder">
        <input type="hidden" name="seizoen_id" value="<?= $sz_bevestig['id'] ?>">
        <input type="hidden" name="bevestig_verwijder_data" value="1">
        <button type="submit" class="btn btn-rood"
                onclick="return confirm('DEFINITIEF verwijderen inclusief alle <?= $sz_bevestig['n'] ?> sollicitaties? Dit kan NIET ongedaan worden.')">
          🗑️ Ja, verwijder alles definitief
        </button>
      </form>
      <a href="<?= APP_URL ?>/admin/seizoen.php" class="btn btn-outline">Annuleren</a>
    </div>
  </div>
</div>
<?php endif; endif; ?>

<div class="seizoen-grid-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">
<div>

  <!-- Huidig seizoen instellen -->
  <?php if ($huidig): ?>
  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><div class="card-title">⚙️ <?= h($huidig['naam']) ?> instellen</div></div>
    <div class="card-body">
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="update">
        <input type="hidden" name="seizoen_id" value="<?= $huidig['id'] ?>">

        <div class="form-groep">
          <label>Naam</label>
          <input type="text" name="naam" value="<?= h($huidig['naam']) ?>" required>
        </div>

        <div style="background:var(--blauw-bg);border:1px solid #90cdf4;border-radius:8px;padding:12px 14px;margin-bottom:14px">
          <div style="font-size:12px;font-weight:600;color:#1a56a0;margin-bottom:8px">
            📅 Werkperiode — voor automatische week-selector in het portaal
          </div>
          <div class="form-rij">
            <div class="form-groep">
              <label>Eerste werkdag</label>
              <input type="date" name="datum_werk_start" value="<?= h($huidig['datum_werk_start'] ?? '') ?>">
            </div>
            <div class="form-groep">
              <label>Laatste werkdag</label>
              <input type="date" name="datum_werk_eind" value="<?= h($huidig['datum_werk_eind'] ?? '') ?>">
            </div>
          </div>
          <div style="font-size:11px;color:#1a56a0">
            Sollicitanten zien in het portaal alle weken tussen deze datums als selecteerbare tegels.
          </div>
          <div class="form-rij" style="margin-top:10px">
            <div class="form-groep">
              <label>Hoofdseizoen vanaf week <span class="tip" data-tip="Weken vóór dit nummer tellen als voorseizoen (weekend-focus)"></span></label>
              <input type="number" name="hoofdseizoen_start_week" min="1" max="53" value="<?= h($huidig['hoofdseizoen_start_week'] ?? '') ?>" placeholder="bv. 27">
            </div>
            <div class="form-groep">
              <label>Hoofdseizoen t/m week <span class="tip" data-tip="Weken ná dit nummer tellen als naseizoen (weekend-focus)"></span></label>
              <input type="number" name="hoofdseizoen_eind_week" min="1" max="53" value="<?= h($huidig['hoofdseizoen_eind_week'] ?? '') ?>" placeholder="bv. 34">
            </div>
          </div>
          <div style="font-size:11px;color:#1a56a0">
            Voor de bezettings-planning (fase 2): voor- en naseizoen focussen op vr/za/zo, hoofdseizoen op alle dagen. Leeg = alles telt als hoofdseizoen.
          </div>
        </div>

        <div style="font-weight:600;color:var(--navy);font-size:13px;margin:14px 0 8px;border-bottom:1px solid var(--rand);padding-bottom:5px">🏖️ Selectiedag</div>
        <div class="form-rij">
          <div class="form-groep"><label>Datum</label><input type="date" name="datum_selectiedag" value="<?= h($huidig['datum_selectiedag'] ?? '') ?>"></div>
          <div class="form-groep"><label>Locatie</label><input type="text" name="locatie_selectiedag" value="<?= h($huidig['locatie_selectiedag'] ?? '') ?>" placeholder="bijv. Paal 13"></div>
        </div>
        <div class="form-rij">
          <div class="form-groep"><label>Verzameltijd</label><input type="time" name="selectiedag_verzameltijd" value="<?= h($huidig['selectiedag_verzameltijd'] ?? '') ?>"><div class="form-hulp">Wanneer moeten kandidaten aanwezig zijn</div></div>
          <div class="form-groep"><label>Starttijd programma</label><input type="time" name="selectiedag_starttijd" value="<?= h($huidig['selectiedag_starttijd'] ?? '') ?>"></div>
        </div>
        <div class="form-rij">
          <div class="form-groep"><label>Eindtijd (± )</label><input type="time" name="selectiedag_eindtijd" value="<?= h($huidig['selectiedag_eindtijd'] ?? '') ?>"><div class="form-hulp">Portaal toont "tot ± 16:00"</div></div>
          <div class="form-groep"><label>Adres</label><input type="text" name="adres_selectiedag" value="<?= h($huidig['adres_selectiedag'] ?? '') ?>" placeholder="bijv. Strandweg 13, Callantsoog"></div>
        </div>
        <div class="form-groep">
          <label>Wat meenemen</label>
          <textarea name="selectiedag_meenemen" rows="2" placeholder="bijv. Neem zwemkleding, handdoek en broodjes mee."><?= h($huidig['selectiedag_meenemen'] ?? '') ?></textarea>
          <div class="form-hulp">Staat onderaan de selectiedag-kaart in het portaal en is als {{selectiedag.meenemen}} in mails te gebruiken</div>
        </div>

        <div style="font-weight:600;color:var(--navy);font-size:13px;margin:14px 0 8px;border-bottom:1px solid var(--rand);padding-bottom:5px">🏕️ Trainingsweekend</div>
        <div class="form-rij">
          <div class="form-groep"><label>Start</label><input type="date" name="datum_tw_start" value="<?= h($huidig['datum_tw_start'] ?? '') ?>"></div>
          <div class="form-groep"><label>Eind</label><input type="date" name="datum_tw_eind" value="<?= h($huidig['datum_tw_eind'] ?? '') ?>"></div>
        </div>
        <div class="form-groep">
          <label>Locatie TW</label>
          <input type="text" name="locatie_tw" value="<?= h($huidig['locatie_tw'] ?? '') ?>" placeholder="bijv. Strandpost Callantsoog">
        </div>
        <div class="form-rij">
          <div class="form-groep"><label>Verzameltijd TW</label><input type="time" name="tw_verzameltijd" value="<?= h($huidig['tw_verzameltijd'] ?? '') ?>"></div>
          <div class="form-groep"><label>Begintijd programma TW</label><input type="time" name="tw_begintijd" value="<?= h($huidig['tw_begintijd'] ?? '') ?>"></div>
        </div>
        <div class="form-rij">
          <div class="form-groep"><label>Eindtijd TW (± )</label><input type="time" name="tw_eindtijd" value="<?= h($huidig['tw_eindtijd'] ?? '') ?>"></div>
          <div class="form-groep"><label>Adres TW</label><input type="text" name="adres_tw" value="<?= h($huidig['adres_tw'] ?? '') ?>" placeholder="bijv. Duinweg 1, Callantsoog"></div>
        </div>
        <div class="form-groep">
          <label>Wat meenemen TW</label>
          <textarea name="tw_meenemen" rows="2" placeholder="bijv. Slaapzak, matje, zwemkleding."><?= h($huidig['tw_meenemen'] ?? '') ?></textarea>
          <div class="form-hulp">Staat onderaan de trainingsweekend-kaart in het portaal en is als {{tw.meenemen}} in mails te gebruiken</div>
        </div>

        <button type="submit" class="btn btn-primary">Opslaan</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Nieuw seizoen -->
  <div class="card">
    <div class="card-header"><div class="card-title">➕ Nieuw seizoen aanmaken</div></div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--tekst-zacht);margin-bottom:12px">
        Bestaande data blijft bewaard. Terugkerende sollicitanten worden automatisch herkend.
      </p>
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="actie" value="nieuw">
        <div class="form-groep">
          <label>Naam</label>
          <input type="text" name="naam" placeholder="bijv. Zomer 2027" required>
        </div>
        <button type="submit" class="btn btn-navy">Aanmaken</button>
      </form>
    </div>
  </div>
</div>

<div>
  <!-- Uitleg -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><div class="card-title">📋 Stappenplan</div></div>
    <div class="card-body" style="font-size:13px;color:var(--tekst-zacht);line-height:1.9">
      <div><strong style="color:var(--navy)">1. Nieuw seizoen aanmaken</strong><br>Geef het een naam (bijv. Zomer 2027).</div>
      <div style="margin-top:8px"><strong style="color:var(--navy)">2. Datums instellen</strong><br>Vul selectiedag, TW en werkperiode in. Werkperiode = week-selector in portaal.</div>
      <div style="margin-top:8px"><strong style="color:var(--navy)">3. ✅ Openen</strong><br>Aanmeldformulier wordt actief, sollicitanten kunnen aanmelden.</div>
      <div style="margin-top:8px"><strong style="color:var(--navy)">4. Procedure doorlopen</strong><br>Uitnodigen, beoordelen, aannemen via het sollicitantenoverzicht.</div>
      <div style="margin-top:8px"><strong style="color:var(--navy)">5. 🔒 Sluiten</strong><br>Sluit aan het einde van het seizoen. Alle data blijft bewaard.</div>
    </div>
  </div>

  <!-- Alle seizoenen -->
  <div class="card">
    <div class="card-header"><div class="card-title">📁 Alle seizoenen</div></div>
    <?php if (empty($seizoenen)): ?>
    <div class="card-body" style="color:var(--grijs);font-size:13px">Geen seizoenen.</div>
    <?php else: ?>
    <div style="padding:0">
      <?php foreach ($seizoenen as $sz): ?>
      <div style="padding:13px 16px;border-bottom:1px solid var(--rand)">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
          <div style="min-width:0;flex:1">
            <div style="font-weight:600;font-size:13px;display:flex;flex-wrap:wrap;align-items:center;gap:5px">
              <?= h($sz['naam']) ?>
              <?php if ($sz === $huidig): ?>
              <span style="font-size:10px;background:var(--blauw-bg);color:#1a56a0;padding:1px 6px;border-radius:10px">Huidig</span>
              <?php endif; ?>
              <span class="badge <?= $sz['status']==='open'?'badge-groen':'badge-grijs' ?>">
                <?= $sz['status']==='open' ? '✅ Open' : '🔒 Gesloten' ?>
              </span>
            </div>
            <div style="font-size:11px;color:var(--grijs);margin-top:2px">
              <strong><?= $sz['n'] ?></strong> sollicitaties
              <?= ($sz['datum_selectiedag'] ?? null) ? ' · SD: '.datum_nl($sz['datum_selectiedag']) : '' ?>
              <?= ($sz['datum_werk_start'] ?? null)  ? ' · '.datum_nl($sz['datum_werk_start']).'–'.datum_nl($sz['datum_werk_eind']) : '' ?>
            </div>
          </div>

          <!-- Admin: beheer knoppen voor elk seizoen -->
          <?php if ($bhd['rol'] === 'admin'): ?>
          <div style="display:flex;gap:5px;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end">
            <?php if ($sz !== $huidig): ?>
              <?php if ($sz['status'] === 'gesloten'): ?>
              <form method="POST" style="display:inline">
                <?= csrf_input() ?>
                <input type="hidden" name="actie" value="open">
                <input type="hidden" name="seizoen_id" value="<?= $sz['id'] ?>">
                <button type="submit" class="btn btn-groen btn-sm" title="Heropenen">✅</button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline">
                <?= csrf_input() ?>
                <input type="hidden" name="actie" value="sluit">
                <input type="hidden" name="seizoen_id" value="<?= $sz['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" title="Sluiten">🔒</button>
              </form>
              <?php endif; ?>
            <?php endif; ?>

            <form method="POST" style="display:inline">
              <?= csrf_input() ?>
              <input type="hidden" name="actie" value="verwijder">
              <input type="hidden" name="seizoen_id" value="<?= $sz['id'] ?>">
              <button type="submit" class="btn btn-rood btn-sm" title="Verwijderen"
                      onclick="return confirm('Seizoen &quot;<?= addslashes(h($sz['naam'])) ?>&quot; verwijderen?\n\n<?= $sz['n'] > 0 ? "Let op: $sz[n] sollicitaties worden ook verwijderd." : "Dit seizoen heeft geen data." ?>')">
                🗑️
              </button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($bhd['rol'] !== 'admin'): ?>
    <div style="padding:10px 16px;font-size:11px;color:var(--grijs);border-top:1px solid var(--rand)">
      Alleen admins kunnen seizoenen verwijderen of heropenen.
    </div>
    <?php endif; ?>
  </div>
</div>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>
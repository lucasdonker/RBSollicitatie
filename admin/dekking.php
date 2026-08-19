<?php
/**
 * Admin — Dekking: kunnen we de posten bemannen met de beschikbare mensen?
 */
require_once __DIR__ . '/../includes/functions.php';
vereis_recht('dekking', 'lezen');

$pagina_titel = 'Dekking';
$actieve_nav  = 'dekking';

// Actief seizoen
$seizoen = function_exists('actief_seizoen') ? actief_seizoen() : null;
$sid = $seizoen ? (int)$seizoen['id'] : 0;

// Weken van het seizoen
$weken_datums = function_exists('seizoen_weken_datums') ? seizoen_weken_datums($seizoen) : [];

// Dekkingsanalyse
$dekking = ($sid && function_exists('bezetting_dekking')) ? bezetting_dekking($sid, $seizoen) : [];
$sjablonen = function_exists('bezetting_sjablonen') ? bezetting_sjablonen() : [];

// Detail-week (uitgeklapt)
$detail_wk = isset($_GET['week']) ? (int)$_GET['week'] : 0;

// Competentie-sync klaar?
$comp_klaar = function_exists('competentie_sync_klaar') ? competentie_sync_klaar() : false;

$status_kleur = [
    'groen'  => ['bg'=>'var(--groen-bg)', 'rand'=>'var(--groen)', 'tekst'=>'var(--groen)', 'icoon'=>'🟢', 'label'=>'Gedekt'],
    'oranje' => ['bg'=>'#fff7ed',         'rand'=>'#c05a1a',      'tekst'=>'#c05a1a',      'icoon'=>'🟠', 'label'=>'Krap'],
    'rood'   => ['bg'=>'var(--rood-bg)',  'rand'=>'var(--rood)',  'tekst'=>'var(--rood)',  'icoon'=>'🔴', 'label'=>'Tekort'],
];

require __DIR__ . '/layout_top.php';
?>

<style>
.dek-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:10px; }
.dek-week { border:1px solid var(--rand); border-radius:10px; padding:12px; background:var(--bg-card); cursor:pointer; transition:transform .12s, box-shadow .12s; }
.dek-week:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.08); }
.dek-week.actief { outline:2px solid var(--navy); }
.dek-wk-kop { font-weight:700; font-size:14px; margin-bottom:2px; }
.dek-wk-datum { font-size:11px; color:var(--grijs); margin-bottom:8px; }
.dek-post-rij { display:flex; align-items:center; gap:5px; font-size:11.5px; padding:2px 0; }
.dek-legenda { display:flex; gap:16px; flex-wrap:wrap; font-size:12px; color:var(--tekst-zacht); margin-bottom:14px; }
.rol-rij { display:flex; align-items:center; gap:8px; padding:7px 10px; border-radius:6px; margin-bottom:4px; font-size:12.5px; flex-wrap:wrap; }
</style>

<div style="margin-bottom:14px">
  <p style="font-size:13px;color:var(--tekst-zacht);margin:0">
    Kunnen we met de mensen die beschikbaar zijn de posten bemannen? Eén pool — een schipper kan niet tegelijk op GK staan.
    Klik op een week voor de details en het bellijstje.
    <br><span style="font-size:12px">De postcommandant valt hier buiten; die regelt het bestuur apart.</span>
  </p>
</div>

<?php if (!$comp_klaar): ?>
  <div class="card" style="margin-bottom:14px;border-left:4px solid var(--oranje)">
    <div class="card-body" style="padding:12px 16px;font-size:13px">
      ⚠️ <strong>Authentik is nog niet gekoppeld.</strong> Zonder competenties kan de dekking niet worden berekend.
      Stel dit in bij <a href="<?= APP_URL ?>/admin/instellingen.php?tab=authentik" style="color:var(--oranje)">Instellingen → Authentik &amp; competenties</a>.
    </div>
  </div>
<?php endif; ?>

<?php if (empty($dekking)): ?>
  <div class="card"><div class="card-body" style="text-align:center;color:var(--tekst-zacht);padding:40px">
    Nog geen beschikbaarheid ingevuld dit seizoen — er valt nog niets te dekken.
  </div></div>
<?php else: ?>

<div class="dek-legenda">
  <span>🟢 Gedekt (met reserve)</span>
  <span>🟠 Krap (precies genoeg, geen reserve)</span>
  <span>🔴 Tekort (rol onvervulbaar)</span>
</div>

<!-- Week-overzicht -->
<div class="dek-grid" style="margin-bottom:20px">
  <?php foreach ($dekking as $wk => $d):
    $k = $status_kleur[$d['status']];
    $datum = isset($weken_datums[$wk]) ? $weken_datums[$wk]['start']->format('j M') : '';
  ?>
  <a href="?week=<?= $wk ?>" class="dek-week <?= $detail_wk === $wk ? 'actief' : '' ?>"
     style="text-decoration:none;color:inherit;border-color:<?= $k['rand'] ?>;background:<?= $k['bg'] ?>">
    <div class="dek-wk-kop" style="color:<?= $k['tekst'] ?>"><?= $k['icoon'] ?> Week <?= $wk ?></div>
    <div class="dek-wk-datum"><?= h($datum) ?> · <?= $d['pool_grootte'] ?> beschikbaar</div>
    <?php foreach ($d['posten'] as $pk => $p):
      $pkl = $status_kleur[$p['status']];
    ?>
    <div class="dek-post-rij" style="color:<?= $pkl['tekst'] ?>">
      <span><?= $pkl['icoon'] ?></span>
      <span><?= h($p['naam']) ?></span>
    </div>
    <?php endforeach; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Detail van geselecteerde week -->
<?php if ($detail_wk && isset($dekking[$detail_wk])):
  $d = $dekking[$detail_wk];
  $datum = isset($weken_datums[$detail_wk]) ? $weken_datums[$detail_wk]['start']->format('j M') . '–' . $weken_datums[$detail_wk]['eind']->format('j M') : '';
?>
<div class="card">
  <div class="card-header" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <div class="card-title">Week <?= $detail_wk ?> <span style="font-weight:400;color:var(--tekst-zacht);font-size:13px"><?= h($datum) ?></span></div>
    <span style="flex:1"></span>
    <span style="font-size:12px;color:var(--tekst-zacht)"><?= $d['ingezet'] ?> ingezet van <?= $d['pool_grootte'] ?> beschikbaar</span>
    <a href="?" class="btn btn-outline btn-sm">✕ Sluiten</a>
  </div>
  <div class="card-body">

    <?php foreach ($d['posten'] as $pk => $p):
      $pkl = $status_kleur[$p['status']];
    ?>
    <div style="margin-bottom:18px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <span style="font-size:16px"><?= $p['icoon'] ?></span>
        <strong style="font-size:14px"><?= h($p['naam']) ?></strong>
        <span style="font-size:11px;font-weight:700;color:#fff;background:<?= $pkl['rand'] ?>;padding:2px 8px;border-radius:4px"><?= $pkl['icoon'] ?> <?= $pkl['label'] ?></span>
      </div>

      <?php foreach ($p['rollen'] as $rs => $r):
        $vervuld = $r['vervuld'];
      ?>
      <div class="rol-rij" style="background:<?= $vervuld ? 'var(--groen-bg)' : 'var(--rood-bg)' ?>;border:1px solid <?= $vervuld ? 'var(--groen)' : 'var(--rood)' ?>">
        <span style="width:16px"><?= $vervuld ? '✓' : '✕' ?></span>
        <strong style="min-width:130px"><?= h($r['label']) ?></strong>
        <?php if ($r['namen']): ?>
          <span><?= implode(', ', array_map('h', $r['namen'])) ?></span>
        <?php else: ?>
          <span style="color:var(--rood)">niemand beschikbaar</span>
        <?php endif; ?>
        <span style="flex:1"></span>
        <?php if ($vervuld && $r['reserve'] > 0): ?>
          <span style="font-size:11px;color:var(--groen)">+<?= $r['reserve'] ?> reserve</span>
        <?php elseif ($vervuld): ?>
          <span style="font-size:11px;color:#c05a1a">geen reserve</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <!-- Waarom rood? + bellijst -->
      <?php if ($p['tekort']): ?>
        <?php foreach ($p['tekort'] as $t): ?>
        <div style="margin-top:8px;padding:10px 14px;background:var(--rood-bg);border-left:3px solid var(--rood);border-radius:6px;font-size:12.5px">
          <div style="font-weight:600;color:var(--rood);margin-bottom:4px">
            Waarom tekort: <?= h($t['rol']) ?>
            <?php if (!empty($t['melding'])): ?> — <?= h($t['melding']) ?><?php endif; ?>
          </div>
          <div style="color:var(--tekst-zacht);margin-bottom:6px">
            Nodig: <?= (int)$t['nodig'] ?> · Gevonden: <?= (int)$t['gevonden'] ?> ·
            Vereist: <?= h($t['categorie']) ?> (min. rang <?= (int)$t['min_rang'] ?>)
          </div>
          <?php
            $bel = function_exists('bezetting_bellijst')
                 ? bezetting_bellijst($sid, $detail_wk, $t['categorie'], (int)$t['min_rang'], $seizoen)
                 : [];
          ?>
          <?php if ($bel): ?>
            <div style="font-weight:600;margin-bottom:4px">📞 Wel gekwalificeerd, niet beschikbaar deze week:</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
              <?php foreach ($bel as $b): ?>
              <span style="display:inline-flex;align-items:center;gap:6px;background:var(--bg-card);border:1px solid var(--rand);border-radius:20px;padding:4px 12px">
                <?= h($b['naam']) ?>
                <?php if ($b['telefoon']): ?><a href="tel:<?= h($b['telefoon']) ?>" style="color:var(--navy);text-decoration:none" title="Bellen">📞</a><?php endif; ?>
                <?php if ($b['email']): ?><a href="mailto:<?= h($b['email']) ?>" style="color:var(--navy);text-decoration:none" title="Mailen">✉️</a><?php endif; ?>
              </span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div style="color:var(--tekst-zacht)">Niemand met deze competentie gevonden — ook niet buiten de beschikbare groep.</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

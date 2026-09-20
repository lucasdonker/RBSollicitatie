<?php
/**
 * Tabblad: de beschikbaarheid van deze sollicitant.
 *
 * Drie lagen naast elkaar, want die zeggen elk iets anders: wat hij in het
 * kennismakingsformulier als voorkeur opgaf, wat hij daarna definitief indiende, en
 * wat het bestuur uiteindelijk corrigeerde. Zonder dat onderscheid lijkt een correctie
 * van het bestuur op een keuze van de sollicitant.
 *
 * Verwacht: $tab_dossier  array  uitkomst van bh_dossier()
 */
$tab_dossier = $tab_dossier ?? null;
if (!$tab_dossier) return;

$_s = $tab_dossier['sol'];
$_b = $tab_dossier['beschikbaarheid'];
$_eff = function_exists('beschikbaarheid2_effectief') ? beschikbaarheid2_effectief((int)$_s['id']) : [];
$_prof = function_exists('beschikbaarheid2_van') ? (beschikbaarheid2_van((int)$_s['id'])['profiel'] ?? null) : null;

if (!$_b && !$_eff) {
    echo '<div class="ds-kaart" style="padding:16px">';
    $leeg_titel = 'Nog geen beschikbaarheid';
    $leeg_tekst = h($_s['voornaam']) . ' heeft nog niet doorgegeven welke weken hij kan.';
    $leeg_icoon = 'kalender';
    require __DIR__ . '/../_leeg.php';
    echo '</div>';
    return;
}
?>
<div class="bh-dossier-kolommen">
  <div class="bh-dossier-hoofd">
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>Per week</h2>
        <?php if ($_prof && !empty($_prof['voorlopig'])): ?>
          <?= bh_badge('Voorlopig', 'oranje') ?>
        <?php elseif ($_prof) : ?>
          <?= bh_badge('Definitief', 'groen') ?>
        <?php endif; ?>
        <span class="bh-meta">klik een week om te corrigeren</span>
      </div>

      <?php if (!$_eff): ?>
        <p class="bh-dossier-tekst zacht">Er zijn nog geen weken ingevuld.</p>
      <?php else: ?>
        <div class="bh-weekrij">
          <?php foreach ($_eff as $_wn => $_w):
            $_dagen = (int)($_w['dagen_max'] ?? 0);
            $_corr  = !empty($_w['correctie']);
            $_kleur = $_dagen >= 5 ? 'groen' : ($_dagen > 0 ? 'oranje' : 'grijs');
            if ($_corr) $_kleur = 'blauw';
          ?>
            <button type="button" class="bh-weekcel ds-fase-<?= h($_kleur) ?>"
                    onclick="correctieOpen(<?= (int)$_s['id'] ?>, <?= (int)$_wn ?>)"
                    title="Week <?= (int)$_wn ?><?= $_corr ? ' — bestuurscorrectie' : '' ?>">
              <span class="bh-weekcel-nr">W<?= (int)$_wn ?></span>
              <span class="bh-weekcel-dagen bh-num"><?= $_dagen > 0 ? $_dagen : '—' ?></span>
            </button>
          <?php endforeach; ?>
        </div>
        <div class="bh-weeklegenda">
          <span class="ds-fase-groen"><span class="bh-legenda-blok"></span>hele week</span>
          <span class="ds-fase-oranje"><span class="bh-legenda-blok"></span>deels</span>
          <span class="ds-fase-blauw"><span class="bh-legenda-blok"></span>bestuurscorrectie</span>
          <span class="ds-fase-grijs"><span class="bh-legenda-blok"></span>niet beschikbaar</span>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($_b && !empty($_b['uitzonderingen'])): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Uitzonderingen</h2></div>
      <p class="bh-dossier-tekst"><?= nl2br(h((string)$_b['uitzonderingen'])) ?></p>
    </section>
    <?php endif; ?>
  </div>

  <aside class="bh-dossier-zij">
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Uit het formulier</h2></div>
      <?php $_int = trim((string)($tab_dossier['blk']['beschikbaarheid_intentie'] ?? '')); ?>
      <?php if ($_int === ''): ?>
        <p class="bh-dossier-tekst zacht">Geen voorkeur opgegeven bij de aanmelding.</p>
      <?php else: ?>
        <p class="bh-dossier-tekst"><?= nl2br(h($_int)) ?></p>
      <?php endif; ?>
      <?php $_uitz = trim((string)($tab_dossier['blk']['beschikbaarheid_uitzonderingen'] ?? '')); ?>
      <?php if ($_uitz !== ''): ?>
        <div class="bh-dossier-motivatie">
          <span class="bh-veldlabel">Uitzonderingen toen</span>
          <p class="bh-dossier-tekst"><?= nl2br(h($_uitz)) ?></p>
        </div>
      <?php endif; ?>
    </section>

    <?php if (mag('beschikbaarheid', 'bewerken')): ?>
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Aanpassen</h2></div>
      <p class="bh-dossier-tekst">
        Een correctie van het bestuur overschrijft wat de sollicitant opgaf. De reden
        komt in de tijdlijn te staan.
      </p>
      <a class="ds-btn ds-btn-secundair ds-btn-sm" style="margin-top:10px"
         href="<?= APP_URL ?>/admin/beschikbaarheid_bewerken.php?id=<?= (int)$_s['id'] ?>">
        Volledig bewerken
      </a>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php
$tab_dossier = null;

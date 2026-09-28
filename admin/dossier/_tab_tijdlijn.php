<?php
/**
 * Tabblad: de volledige tijdlijn van deze sollicitatie.
 *
 * Wat er gebeurde en wie het deed — dat is wat "afwijken vraagt een reden, en die is
 * terug te vinden" (ontwerpfilosofie §3) in de praktijk betekent. De inhoud komt van
 * api_timeline.php; hier staat alleen de plek waar hij landt.
 *
 * Verwacht: $tab_dossier  array  uitkomst van bh_dossier()
 */
$tab_dossier = $tab_dossier ?? null;
if (!$tab_dossier) return;
$_s = $tab_dossier['sol'];
?>
<div class="bh-dossier-kolommen">
  <div class="bh-dossier-hoofd">
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop">
        <h2>Tijdlijn</h2>
        <span class="bh-meta">nieuwste bovenaan</span>
      </div>
      <div id="timeline-<?= (int)$_s['id'] ?>">
        <?php $laden_rijen = 5; $laden_tegel = false; require __DIR__ . '/../_laden.php'; ?>
      </div>
    </section>
  </div>

  <aside class="bh-dossier-zij">
    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Notities</h2></div>
      <div id="notities-lijst-<?= (int)$_s['id'] ?>">
        <?php $laden_rijen = 2; $laden_tegel = false; require __DIR__ . '/../_laden.php'; ?>
      </div>
      <?php if (mag('sollicitanten', 'bewerken')): ?>
      <div class="bh-notitie-nieuw">
        <textarea id="nieuwe-notitie-<?= (int)$_s['id'] ?>" class="ds-textarea" rows="2" aria-label="Nieuwe notitie"
                  placeholder="Notitie plaatsen…"></textarea>
        <button type="button" class="ds-btn ds-btn-secundair ds-btn-sm"
                onclick="notitieToevoegen(<?= (int)$_s['id'] ?>)">Plaatsen</button>
      </div>
      <?php endif; ?>
    </section>

    <section class="ds-kaart ds-kaart-p">
      <div class="bh-kaartkop"><h2>Waar dit vandaan komt</h2></div>
      <p class="bh-dossier-tekst">
        Deze tijdlijn toont wat er met dit dossier is gebeurd. Wie welk document opende
        of downloadde staat in het logboek, niet hier.
      </p>
      <?php if (mag('logboek', 'lezen')): ?>
        <?php /* object + object_id, niet ?sol= — die parameter kent het logboek niet, dus
                 die knop opende het logboek ongefilterd en leek gewoon niets te doen.
                 Zowel audit_log als mail_log vertalen naar objecttype 'sollicitatie'
                 (includes/logboek/bronnen.php), dus dit vangt allebei. */ ?>
        <a class="ds-btn ds-btn-secundair ds-btn-sm bh-dossier-knop"
           href="<?= APP_URL ?>/admin/logboek.php?object=sollicitatie&amp;object_id=<?= (int)$_s['id'] ?>">
          <?= icoon('auditlog', 15) ?> Alles over deze sollicitant
        </a>
      <?php endif; ?>
    </section>
  </aside>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.laadTimeline) laadTimeline(<?= (int)$_s['id'] ?>);
    if (window.laadNotities) laadNotities(<?= (int)$_s['id'] ?>);
  });
</script>
<?php
$tab_dossier = null;

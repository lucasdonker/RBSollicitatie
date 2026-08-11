<?php
vereist_login();
$_bhd     = huidige_beheerder();
// Zorg dat mail_template kolommen bestaan + seed defaults (idempotent, één keer per request)
if (function_exists('mail_templates_auto_setup')) { mail_templates_auto_setup(); }
if (function_exists('lazy_cron_tick')) { lazy_cron_tick(); }
$_seizoen = actief_seizoen();

$_nav_items = [
    ['id'=>'dashboard',       'label'=>'Dashboard',       'url'=>'/admin/',                    'svg'=>'<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
    ['id'=>'sollicitanten',   'label'=>'Sollicitanten',   'url'=>'/admin/sollicitanten.php',   'svg'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['id'=>'selectiedag',     'label'=>'Selectiedag',     'url'=>'/admin/selectie_indeling.php', 'svg'=>'<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    ['id'=>'ondertekeningen', 'label'=>'Ondertekeningen', 'url'=>'/admin/ondertekeningen.php', 'svg'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
    ['id'=>'beschikbaarheid', 'label'=>'Beschikbaarheid', 'url'=>'/admin/beschikbaarheid.php', 'svg'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>'],
    ['id'=>'dekking',         'label'=>'Dekking',         'url'=>'/admin/dekking.php',         'svg'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
    ['id'=>'loonplanning',    'label'=>'Loonplanning',    'url'=>'/admin/loonplanning.php',    'svg'=>'<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
    [
        'id'=>'instellingen', 'label'=>'Instellingen', 'url'=>'/admin/instellingen.php',
        'svg'=>'<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>',
        'sub' => [
            ['id'=>'instellingen',    'label'=>'Algemeen',         'url'=>'/admin/instellingen.php?tab=algemeen'],
            ['id'=>'seizoen',         'label'=>'Seizoenbeheer',    'url'=>'/admin/seizoen.php'],
            ['id'=>'mailing',         'label'=>'Mailings',         'url'=>'/admin/mailing.php'],
            ['id'=>'mail_templates',  'label'=>'Mail templates',   'url'=>'/admin/mail_templates.php'],
        ],
    ],
];
if ($_bhd['rol'] === 'admin') {
    $_nav_items[] = ['id'=>'beheerders','label'=>'Beheerders','url'=>'/admin/beheerders.php','svg'=>'<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'];
    // Voeg systeem-link toe in instellingen submenu
    foreach ($_nav_items as &$_n) {
        if ($_n['id'] === 'instellingen') {
            $_n['sub'][] = ['id'=>'systeem', 'label'=>'Systeem & Updates', 'url'=>'/admin/systeem.php'];
            $_n['sub'][] = ['id'=>'deploy', 'label'=>'Deploy & Versiebeheer', 'url'=>'/admin/deploy.php'];
            break;
        }
    }
    unset($_n);
}
?>
<!DOCTYPE html>
<html lang="nl" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($pagina_titel ?? 'Beheer') ?> — Paal 13</title>
<?php $_fav = site_favicon_url(); if ($_fav): ?>
<link rel="icon" href="<?= h($_fav) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/stijl.css?v=<?= filemtime(__DIR__.'/../assets/css/stijl.css') ?>">
<meta name="robots" content="noindex">
<script>
// Pas dark mode direct toe (vóór render, geen flits)
(function(){
  const t = localStorage.getItem('paal13-theme') || 'light';
  document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : '');
})();
</script>
</head>
<body>

<!-- Mobiele topbar (alleen <900px zichtbaar) -->
<header class="mobile-topbar">
  <a href="<?= APP_URL ?>/admin/" class="mobile-topbar-logo">
    <?php if ($_logo = (function_exists('site_logo_url') ? site_logo_url() : '')): ?>
      <img src="<?= h($_logo) ?>" alt="Paal 13">
    <?php else: ?>
      <span style="color:#fff;font-weight:600;font-size:15px">Paal 13</span>
    <?php endif; ?>
  </a>
  <button type="button" class="hamburger-knop" onclick="toggleSidebar()" aria-label="Menu">☰</button>
</header>

<div class="admin-layout">

<!-- Mobiel overlay (sluit sidebar bij klik buiten) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="sluitSidebar()"></div>

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <?php $_lgo = site_logo_url(); ?>
    <img src="<?= h($_lgo ?: APP_URL . '/assets/img/logo.png') ?>" alt="Paal 13"
         onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/logo.svg'">
    <div class="sub">Sollicitatie</div>
    <?php if (is_test_omgeving()): ?>
    <div style="margin-top:8px;padding:3px 10px;background:var(--oranje);color:#fff;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:.08em;text-align:center">⚠ TESTOMGEVING</div>
    <?php endif; ?>
  </div>

  <div class="sidebar-nav">
    <div class="nav-sectie">Beheer</div>
    <?php
    $_inst_sub_ids = ['instellingen','seizoen','mailing','mail_templates'];
    $_inst_actief  = in_array($actieve_nav ?? '', $_inst_sub_ids, true);
    foreach ($_nav_items as $_item):
        $_heeft_sub = !empty($_item['sub']);
        $_is_actief = ($actieve_nav ?? '') === $_item['id'];
        $_open_sub  = $_heeft_sub && $_inst_actief;
        // Hoofd-link "Instellingen" zelf is alleen "actief" als je op de algemene instellingenpagina bent
    ?>
      <a href="<?= APP_URL . $_item['url'] ?>"
         class="nav-link <?= ($_is_actief || $_open_sub) ? 'actief' : '' ?>"
         onclick="sluitSidebar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
          <?= $_item['svg'] ?>
        </svg>
        <span><?= h($_item['label']) ?></span>
      </a>
      <?php if ($_heeft_sub && $_open_sub): ?>
      <div class="nav-sub">
        <?php foreach ($_item['sub'] as $_sub): ?>
        <a href="<?= APP_URL . $_sub['url'] ?>"
           class="nav-sublink <?= ($actieve_nav ?? '') === $_sub['id'] ? 'actief' : '' ?>"
           onclick="sluitSidebar()">
          <?= h($_sub['label']) ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="sidebar-footer">
    <a href="<?= APP_URL ?>/admin/profiel.php"
       style="text-decoration:none;color:rgba(255,255,255,.85);font-size:13px;font-weight:500;display:block;margin-bottom:2px">
      <?= h($_bhd['naam']) ?>
    </a>
    <div style="font-size:10px;color:rgba(255,255,255,.35);margin-bottom:10px">
      <?= $_bhd['via'] === 'authentik' ? '🔐 Authentik SSO' : '🔑 Wachtwoord' ?>
    </div>

    <a href="<?= APP_URL ?>/admin/uitloggen.php"
       style="color:rgba(255,255,255,.45);font-size:12px;text-decoration:none">Uitloggen</a>
  </div>
</nav>

<!-- Sidebar JS — inline zodat hamburger altijd werkt, ook bij JS-fouten verderop -->
<script>
function toggleSidebar(){
  var sb=document.getElementById('sidebar');
  var ov=document.getElementById('sidebarOverlay');
  var open=sb.classList.toggle('open');
  ov.classList.toggle('open',open);
  document.body.style.overflow=open?'hidden':'';
}
function sluitSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
  document.body.style.overflow='';
}
window.addEventListener('resize',function(){if(window.innerWidth>768)sluitSidebar();});
</script>

<!-- ── Hoofdinhoud ──────────────────────────────────────────── -->
<div class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <!-- Hamburger (alleen zichtbaar op mobiel via CSS) -->
      <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="6"  x2="21" y2="6"/>
          <line x1="3" y1="12" x2="21" y2="12"/>
          <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <span class="topbar-title"><?= h($pagina_titel ?? 'Beheer') ?></span>
    </div>

    <div class="topbar-right">
      <?php if ($_seizoen): ?>
      <span class="seizoen-badge <?= $_seizoen['status'] === 'open' ? 'open' : '' ?>">
        <?= h($_seizoen['naam']) ?> &mdash;
        <?= $_seizoen['status'] === 'open' ? '✅ Open' : '🔒 Gesloten' ?>
      </span>
      <?php endif; ?>

      <?php
      // Contextuele hulp per pagina
      $_hulp_paginas = [
          'dashboard'       => ['Dashboard', 'Hier zie je in één oogopslag hoe het seizoen loopt. Klik op een funnel-tegel om die sollicitanten te bekijken. De systeemstatus waarschuwt als mails vastlopen of ondertekeningen te lang openstaan.'],
          'sollicitanten'   => ['Sollicitanten', 'Het hart van het systeem. Klik op een naam voor het detailpaneel met alle gegevens, notities en acties. Selecteer meerdere rijen voor bulk-acties (uitnodigen, aannemen). Fase wijzigen verstuurt automatisch de bijbehorende mail.'],
          'selectiedag'     => ['Selectiedag', 'Beoordeel hier sollicitanten tijdens of na de selectiedag. Vul per persoon scores en aantekeningen in. Groepsindeling helpt bij het organiseren van de dag.'],
          'ondertekeningen' => ['Ondertekeningen', 'Overzicht van alle digitale handtekeningen (gezondheidsverklaring, intake). Bij 18+ tekent de sollicitant zelf via het portaal; bij minderjarigen tekent de ouder via een maillink. "Bekijk / Print" opent een printbare versie voor je administratie.'],
          'instellingen'    => ['Instellingen', 'Alles instelbaar: organisatie, functies, lonen, mails, automatiseringen, AI en SSO. Onder "Uitnodiging" vind je de automatiserings-toggles voor fase-wijzigingen.'],
          'mail_templates'  => ['Mail templates', 'Bewerk hier de inhoud van alle automatische mails. Gebruik variabelen zoals {{voornaam}}. Elke template kan een eigen hero-afbeelding en kleur hebben.'],
      ];
      $_hulp = $_hulp_paginas[$actieve_nav ?? ''] ?? null;
      ?>
      <?php if ($_hulp): ?>
      <button type="button" class="hulp-knop" onclick="document.getElementById('hulpPaneel').classList.toggle('open')" title="Uitleg over deze pagina" aria-label="Hulp">?</button>
      <?php endif; ?>

    </div>
  </div>

  <?php if ($_hulp): ?>
  <div id="hulpPaneel" class="hulp-paneel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <strong style="font-size:14px;color:var(--navy)">💡 <?= h($_hulp[0]) ?></strong>
      <button type="button" onclick="document.getElementById('hulpPaneel').classList.remove('open')" style="background:none;border:none;cursor:pointer;font-size:16px;color:var(--grijs)">✕</button>
    </div>
    <p style="margin:0;font-size:13px;line-height:1.7;color:var(--tekst-zacht)"><?= h($_hulp[1]) ?></p>
  </div>
  <?php endif; ?>

  <div class="page-body">
    <?php toon_flash(); ?>
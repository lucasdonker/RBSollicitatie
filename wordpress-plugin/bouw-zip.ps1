# Maakt een installeerbare ZIP van de WordPress-plugin.
#
#   .\bouw-zip.ps1
#
# Het script kopieert de stylesheet en het script van het sollicitatiesysteem in de plugin,
# zodat de website exact hetzelfde formulier laat zien als sollicitatie.paal13.com. Die twee
# bestanden staan bewust niet in Git: één bron, geen kopie die kan gaan afwijken.
#
# De ZIP komt in deze map te staan en upload je in WordPress bij
# Plugins > Nieuwe plugin > Plugin uploaden.

param(
  [string]$Uit = ''
)

$ErrorActionPreference = 'Stop'
$hier   = $PSScriptRoot
$repo   = Split-Path $hier -Parent
$plugin = Join-Path $hier 'paal13-aanmelden'

if (-not (Test-Path $plugin)) { throw "Map niet gevonden: $plugin" }

# ── Versie uit de plugin-header halen, zodat de bestandsnaam klopt ──────────
$hoofd = Get-Content (Join-Path $plugin 'paal13-aanmelden.php') -Raw
$versie = if ($hoofd -match '(?m)^\s*\*\s*Version:\s*(.+?)\s*$') { $Matches[1] } else { '0.0.0' }

# ── De gedeelde bestanden erin kopiëren ─────────────────────────────────────
$paren = @(
  @{ van = 'assets\css\aanmeldformulier.css'; naar = 'assets\aanmeldformulier.css' },
  @{ van = 'assets\js\aanmeldformulier.js';   naar = 'assets\aanmeldformulier.js' }
)
foreach ($p in $paren) {
  $bron = Join-Path $repo $p.van
  if (-not (Test-Path $bron)) { throw "Ontbreekt in het sollicitatiesysteem: $($p.van)" }
  Copy-Item $bron (Join-Path $plugin $p.naar) -Force
  Write-Output "  gekopieerd: $($p.van)"
}

# ── Controle: staat de versie van de plugin gelijk aan die van het systeem? ──
$versiePad = Join-Path $repo 'includes\versie.php'
if (Test-Path $versiePad) {
  $vb = Get-Content $versiePad -Raw
  if ($vb -match "APP_VERSIE'\s*,\s*'([^']+)'") {
    $app = $Matches[1]
    if ($app -ne $versie) {
      Write-Output "  LET OP: plugin staat op $versie, het sollicitatiesysteem op $app."
      Write-Output "          Werk 'Version:' in paal13-aanmelden.php bij als dat de bedoeling is."
    }
  }
}

# ── Inpakken ────────────────────────────────────────────────────────────────
if ($Uit -eq '') { $Uit = Join-Path $hier "paal13-aanmelden-$versie.zip" }
if (Test-Path $Uit) { Remove-Item $Uit -Force }

# LEESMIJ.txt hoort niet in de ZIP: die legt alleen uit waarom assets/ in Git leeg is.
$tijdelijk = Join-Path $env:TEMP ("p13bouw_" + [guid]::NewGuid().ToString('N').Substring(0,8))
New-Item -ItemType Directory -Force $tijdelijk | Out-Null
Copy-Item $plugin (Join-Path $tijdelijk 'paal13-aanmelden') -Recurse
Remove-Item (Join-Path $tijdelijk 'paal13-aanmelden\assets\LEESMIJ.txt') -Force -ErrorAction SilentlyContinue

Compress-Archive -Path (Join-Path $tijdelijk 'paal13-aanmelden') -DestinationPath $Uit -CompressionLevel Optimal
Remove-Item -Recurse -Force $tijdelijk

Write-Output ""
Write-Output "Klaar: $Uit"
Write-Output "Uploaden in WordPress bij Plugins > Nieuwe plugin > Plugin uploaden."

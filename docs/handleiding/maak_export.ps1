# Zet de handleidingen (Markdown) om naar HTML (voor het hulppaneel in het beheer) en Word.
#
#   .\docs\handleiding\maak_export.ps1            # alle handleidingen
#   .\docs\handleiding\maak_export.ps1 -Naam handleiding_beheer
#
# Vereist pandoc (https://pandoc.org). Zet $env:HANDLEIDING_PANDOC op het pad van pandoc.exe,
# of laat het script zoeken in PATH en in de Claude-scratchpads op deze machine.
# Uitvoer: <naam>.html naast het Markdown-bestand en word\<naam>.docx. Allebei gaan mee in Git,
# zodat het beheer en het bestuur altijd de actuele versie hebben zonder pandoc op de server.
# De HTML wordt gelezen via /admin/handleiding.php (de map docs/ is afgeschermd met docs/.htaccess).
# Draai na een tekstwijziging ook docs\ai\harnas\handleiding_check.php: die controleert of elk
# anker waar includes/hulp.php naar linkt nog bestaat.
param([string[]]$Naam = @())

$H = $PSScriptRoot
$Pandoc = $env:HANDLEIDING_PANDOC
if (-not $Pandoc -or -not (Test-Path $Pandoc)) { $Pandoc = (Get-Command pandoc -ErrorAction SilentlyContinue).Source }
if (-not $Pandoc) {
  $kandidaat = Get-ChildItem "$env:LOCALAPPDATA\Temp\claude" -Recurse -Filter pandoc.exe -ErrorAction SilentlyContinue |
    Sort-Object LastWriteTime -Descending | Select-Object -First 1
  if ($kandidaat) { $Pandoc = $kandidaat.FullName }
}
if (-not $Pandoc) { Write-Error "pandoc niet gevonden; zet HANDLEIDING_PANDOC."; exit 1 }

$titels = @{
  'handleiding_beheer'         = 'Handleiding beheer'
  'handleiding_penningmeester' = 'Handleiding penningmeester'
  'handleiding_sollicitant'    = 'Jouw sollicitatie bij Paal 13'
  'runbook_systeembeheer'      = 'Runbook systeembeheer'
  'landingspaginas'            = "Handleiding landingspagina's"
}
if (-not $Naam) { $Naam = $titels.Keys }
New-Item -ItemType Directory -Force "$H\word" | Out-Null

foreach ($n in $Naam) {
  $md = Join-Path $H "$n.md"
  if (-not (Test-Path $md)) { Write-Warning "overgeslagen: $md bestaat niet"; continue }
  $titel = $titels[$n]; if (-not $titel) { $titel = $n }
  # De H1 van het Markdown wordt de documenttitel (shift -1); elk ## wordt een hoofdstuk (h1).
  # De ankers blijven gelijk aan wat GitHub/pandoc van de koptekst maakt, dus includes/hulp.php
  # kan ernaar linken. Geen automatische inhoudsopgave: elke handleiding heeft er zelf een.
  & $Pandoc $md -f gfm -t html5 -s --embed-resources --css "$H\handleiding.css" `
    --metadata lang=nl --metadata "pagetitle=$titel" --shift-heading-level-by=-1 `
    -o (Join-Path $H "$n.html")
  & $Pandoc $md -f gfm -t docx --metadata lang=nl --shift-heading-level-by=-1 `
    -o (Join-Path $H "word\$n.docx")
  "klaar: $n.html + word\$n.docx"
}

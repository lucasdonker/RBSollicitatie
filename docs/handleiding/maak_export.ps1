# Zet de handleidingen (Markdown) om naar HTML (voor het hulppaneel in het beheer) en Word.
#
#   .\docs\handleiding\maak_export.ps1            # alle handleidingen
#   .\docs\handleiding\maak_export.ps1 -Naam handleiding_beheer
#
# Vereist pandoc (https://pandoc.org). Zet $env:HANDLEIDING_PANDOC op het pad van pandoc.exe,
# of laat het script zoeken in PATH en in de Claude-scratchpads op deze machine.
# Uitvoer: <naam>.html naast het Markdown-bestand en word\<naam>.docx. Allebei gaan mee in Git,
# zodat de site en het bestuur altijd de actuele versie hebben zonder pandoc op de server.
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
  # HTML: op zichzelf staand, eigen stijl (huisstijl), inhoudsopgave, ankers zoals GitHub/pandoc ze maakt.
  & $Pandoc $md -f gfm -t html5 -s --embed-resources --css "$H\handleiding.css" `
    --metadata "title=$titel" --metadata lang=nl --toc --toc-depth=2 --shift-heading-level-by=0 `
    -o (Join-Path $H "$n.html")
  # Word: standaard pandoc-opmaak met inhoudsopgave.
  & $Pandoc $md -f gfm -t docx --toc --toc-depth=2 --metadata "title=$titel" --metadata lang=nl `
    -o (Join-Path $H "word\$n.docx")
  "klaar: $n.html + word\$n.docx"
}

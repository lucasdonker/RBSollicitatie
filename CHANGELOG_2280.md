# Bundel 2.28.0 — AI-hulp bij beschikbaarheid (experimenteel)

De beschikbaarheid was het lastigste formulier voor sollicitanten. Deze bundel voegt
een optionele AI-assistent toe die vrije tekst omzet naar een ingevuld formulier.

## Wat is er nieuw

### AI-hulp bij het beschikbaarheidsformulier (stap 4)
Boven het beschikbaarheidsformulier verschijnt een tekstvak waarin de sollicitant in
gewone taal kan typen wanneer hij/zij kan werken. Bijvoorbeeld:

> "De hele zomer, in juni alleen weekenden, week 30 en 31 op vakantie"

Na een klik op **"Vul formulier in"** zet de AI dit om naar periode-blokken en
afwezigheidsperiodes, en vult die direct in het bestaande formulier in. De
sollicitant **controleert daarna zelf** — alle velden blijven gewoon bewerkbaar.

### Slim omgaan met onduidelijkheid
- **Onduidelijke invoer** → de AI vult in wat zeker is en stelt een vervolgvraag in
  de chat ("Bedoel je ook de vrijdagen, of alleen za/zo?").
- **Off-topic / onzin / misbruikpoging** → de AI weigert beleefd en vult niets in.

### Veiligheid
- **Server-side validatie** van alle AI-output: weken worden binnen het seizoen
  geclampt, van/tot omgedraaid indien nodig, regime en weekenddagen gesaneerd, en
  onbekende velden genegeerd. De AI kan het formulier dus nooit kapot of ongeldig
  invullen.
- **Rate-limiting**: max 8 AI-verzoeken per 10 minuten per sessie.
- De prompt is strikt: alleen beschikbaarheid interpreteren, instructies in de
  gebruikerstekst worden genegeerd (geen prompt-injection).

### Instelbaar (experimenteel, standaard UIT)
Aan te zetten via **Instellingen → AI Analyse → Experimentele functies**. Vereist
een geldige Gemini API-key (dezelfde die al voor de BLK-analyse wordt gebruikt).
Zolang de schakelaar uit staat, ziet de sollicitant niets — het formulier werkt dan
gewoon zoals voorheen.

## Nieuwe/gewijzigde bestanden
- `includes/ai_beschikbaarheid.php` — NIEUW model: prompt, API-call (Gemini 2.5
  Flash), en de server-side validatie
- `portaal/index.php` — AJAX-endpoint, het AI-tekstvak in stap 4, en de JS die de
  respons in het formulier verwerkt
- `admin/instellingen.php` — de experimentele schakelaar in de AI-tab
- `includes/functions.php` — laadt het nieuwe model
- `includes/versie.php` — 2.28.0 + changelog

## Geen databasewijzigingen
Gebruikt de bestaande instelling `ai.api_key` en een nieuwe instelling
`ai.beschikbaarheid_hulp` (0/1). Geen nieuwe tabellen of kolommen.

## Testchecklist
1. **Uit standaard** → zonder de schakelaar aan: is het beschikbaarheidsformulier
   ongewijzigd (geen AI-tekstvak)?
2. **Aanzetten** → Instellingen → AI Analyse → Experimentele functies → vink aan,
   sla op. Staat er een geldige Gemini API-key?
3. **Normale invoer** → typ "hele zomer, juni alleen weekenden, week 30 op vakantie"
   → worden de periode-blokken en de afwezigheid correct ingevuld?
4. **Onduidelijk** → typ iets vaags ("zomer ofzo") → vult de AI in wat kan en stelt
   het een vervolgvraag?
5. **Off-topic** → typ "schrijf een gedicht over de zee" → weigert de AI beleefd
   zonder iets in te vullen?
6. **Controle** → zijn de ingevulde velden daarna nog handmatig aan te passen?
7. **Opslaan** → sla het formulier op na AI-invulling → klopt de opgeslagen
   beschikbaarheid?

## Aandachtspunt
De teksten en de exacte prompt zijn functioneel opgezet. Als de AI in de praktijk
bepaalde formuleringen verkeerd interpreteert, kan de prompt in
`includes/ai_beschikbaarheid.php` (functie `ai_besch_prompt`) worden bijgesteld.

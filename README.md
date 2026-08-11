# Paal 13 Sollicitatie Systeem

## Omgevingen (test & productie via Git)

Dit systeem draait in twee omgevingen, elk met een eigen database en eigen `config.php`:

| Omgeving   | Adres                          | Branch        | Uitrol      |
|------------|--------------------------------|---------------|-------------|
| Productie  | sollicitatie.paal13.com        | `main`        | handmatig   |
| Test       | test.sollicitatie.paal13.com   | `Development` | automatisch |

Werkwijze: wijzigingen commit je op `Development` → Plesk rolt automatisch uit naar test
→ testen → merge `Development` naar `main` → in Plesk "Deploy from Repository" → productie live.

De code herkent zelf de omgeving (via `APP_URL`) en toont op test een oranje
**TESTOMGEVING**-label in het beheer, zodat je nooit per ongeluk op productie test.

**Belangrijk:** `includes/config.php` bevat wachtwoorden en staat in `.gitignore` — nooit
in Git. Ook `uploads/` en `_backups/` blijven per server (buiten Git). Een nieuwe server
opzetten: kopieer `includes/config.template.php` naar `includes/config.php` en vul de
echte waarden in, of doorloop de installatiewizard op `/setup/`.

---

## Installatie (eerste keer)

### 1. Database aanmaken
- Open phpMyAdmin op Cloud86
- Maak database aan: `paal13_sollicitatie`
- Maak gebruiker aan met alle rechten op die database

### 2. Bestanden uploaden
- Upload de gehele map naar je server, bijv. `/public_html/sollicitatie/`
- Subdomein `sollicitatie.paal13.com` → map `/public_html/sollicitatie/`
- **Controleer rechten:**
  - `includes/` → 755
  - `uploads/` → 755 (schrijfbaar voor PHP)

### 3. Logo uploaden
- Upload jouw logo als `assets/img/logo.png`
- Aanbevolen: 200×60px, transparante achtergrond, wit

### 4. Installatie wizard
- Ga naar: `https://sollicitatie.paal13.com/setup/`
- Stap 1: Database gegevens invullen → schema wordt automatisch aangemaakt
- Stap 2: E-mailinstellingen
- Stap 3: Authentik OIDC koppelen
- Stap 4: Klaar!

### 5. Authentik instellen
In Authentik (`auth.paal13.com`):
1. Applications → Providers → Nieuw → **OAuth2/OpenID Provider**
2. Redirect URI: `https://sollicitatie.paal13.com/admin/auth_callback.php`
3. Scopes: `openid profile email`
4. Noteer Client ID en Client Secret
5. Maak een Application aan met slug bijv. `paal13-sollicitatie`
6. Wijs toe aan de groepen/gebruikers die beheerder mogen zijn

### 6. Bestaande data importeren
- Ga naar: `https://sollicitatie.paal13.com/setup/import.php`
- Kies seizoen, fase en upload de CSV
- Veilig om meerdere keren te draaien (dubbelen worden overgeslagen)

---

## Updaten (nieuwe versie)

1. Download nieuwe ZIP
2. Upload alle bestanden via FTP (overschrijf alles)
   - `includes/config.php` blijft staan — wordt nooit overschreven
3. Ga naar: `https://sollicitatie.paal13.com/setup/update.php`
4. Klik "Migraties uitvoeren" als er iets pending staat
5. Klaar

---

## URL's

| Pagina | URL |
|--------|-----|
| Hoofdpagina | `/` |
| Aanmeldformulier | `/aanmelden.php` |
| Sollicitant portaal | `/portaal/?token=XXXXX` |
| Admin dashboard | `/admin/` |
| Installatie | `/setup/` |
| Updaten | `/setup/update.php` |
| Data importeren | `/setup/import.php` |

---

## Aanmeldformulier op WordPress (paal13.com)

Optie A — simpele link:
```html
<a href="https://sollicitatie.paal13.com/aanmelden.php" class="jouw-knop-class">
  Aanmelden als lifeguard
</a>
```

Optie B — iFrame (past de stijl aan):
```html
<iframe 
  src="https://sollicitatie.paal13.com/aanmelden.php"
  width="100%" 
  height="680"
  frameborder="0"
  style="border-radius:12px">
</iframe>
```

---

## Nieuwe database kolom toevoegen (ontwikkelaar)

1. Maak `sql/migratie_NNNN_omschrijving.sql` aan
2. Gebruik `ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...`
3. Upload en voer uit via `/setup/update.php`

Voorbeeld (`sql/migratie_0003_nieuw_veld.sql`):
```sql
ALTER TABLE `blk_formulieren`
  ADD COLUMN IF NOT EXISTS `nieuw_veld` TEXT DEFAULT NULL AFTER `bijzonderheden`;
```

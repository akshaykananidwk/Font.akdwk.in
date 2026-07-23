# Gujarati Font Converter — ગુજરાતી ફોન્ટ કન્વર્ટર

Pramukh-style legacy ⇄ Unicode font converter for Gujarati, Hindi, Marathi and Nepali.
Pure PHP 8.1+, MySQL/MariaDB, vanilla JS — **no Composer, no framework** — upload to any
shared/VPS host, open `/install`, and the whole site is live.

**94 registered fonts** (LMG, Shree Guj, Saral, Terafont, Akruti, Gujlys, EKLG, Bhasha Bharti,
Sulekh, ISM, Kruti Dev, Chanakya, DevLys, Shivaji, Preeti, …) · one-click GitHub auto-update ·
REST API · admin CMS · demo limits · SEO-optimised per-font landing pages.

> **Mapping data note / mapping data નોંધ:** LMG Arun ships with a complete, fully round-trip-tested
> **reference** mapping used by the test suite. The remaining 93 fonts are registered as **stubs** —
> fill their glyph tables from actual font specimens via **Admin → Mapping Editor** (or CSV import).
> Legacy glyph codes vary by font vendor/version; always verify against the real font before
> production use.

---

## 1. Installation / ઇન્સ્ટોલેશન

### Requirements
- PHP **8.1+** with `pdo_mysql`, `mbstring`, `json`, `curl`, `zip`, `openssl`, `fileinfo`
  (`intl` recommended for Unicode normalization)
- MySQL 8.0 / MariaDB 10.5+
- Apache (mod_rewrite) or Nginx + PHP-FPM

### Steps / પગલાં
1. બધી ફાઇલો server પર upload કરો (અથવા `git clone`).
2. `/config` અને `/storage` writable કરો: `chmod -R 775 config storage`
3. Browser માં `https://yoursite.com/install/` ખોલો.
4. 6-step wizard પૂરું કરો (server check → license → database → site → admin → done).
5. **`/install` folder delete કરો.** (`install.lock` રાખો — installer ને 403 થી block કરે છે.)

That's it. કોઈ manual file editing નહીં — wizard `config/config.php` જાતે લખે છે.

## 2. GitHub One-Click Auto-Update

**Setup (એક જ વાર):** Admin → **Update** → Settings:
- Repository: `username/repo`
- Branch: `main`
- Personal Access Token (private repo માટે) — DB માં **AES-256-GCM encrypted** સેવ થાય છે
- Auto-check: manual / daily / weekly

**Update process (fully automatic):**
1. Pre-flight checks (disk, permissions, lock)
2. Maintenance mode ON (visitors ને 503 + સુંદર પેજ)
3. **Auto backup** — full DB dump + files zip (verify થાય; ફેલ તો update અટકે)
4. GitHub zipball download + ZIP verify
5. Extract
6. **Protected files skip** — `config/config.php`, `.htaccess`, `storage/*`, custom mappings,
   `sitemap.xml`, `robots.txt` વગેરે ક્યારેય overwrite ન થાય (`config.php` ની
   `protected_paths` યાદી editable છે)
7. Files replace (`delete_manifest.txt` થી deletions — optional)
8. **DB migrations** — `migrations/*.sql` ક્રમમાં, run થયેલી skip, દરેક transaction માં
9. Cache clear + OPcache reset + finalize + admin ને email
10. **કોઈ પણ ભૂલ પર AUTO-ROLLBACK** — files + DB backup માંથી restore, સાઇટ 100% પહેલા જેવી

Extras: Dry Run (preview only), update history with logs, manual rollback to any backup
(Admin → Backup), last-5 backup retention, cron alert (`cron/check_update.php`).

**Migration rules:** name files `NNN_description.sql`, make them **idempotent**
(`CREATE TABLE IF NOT EXISTS`, guarded `ALTER`s), use `{{prefix}}` for the table prefix.

## 3. Adding Font Mappings / ફોન્ટ mapping ઉમેરવી

`engine/mappings/<language>/<font>.json` ( `_template.json` માંથી નકલ કરો):

```json
{
  "font_name": "My Font",
  "language": "gujarati",
  "direction": "both",
  "reorder_rules": { "matra_i_prefix": ["િ"], "reph_chars": ["ર્"], "halant": "્" },
  "mappings": {
    "2_char": { "ûÿ": "ક્ષ" },
    "1_char": { "k": "ક", "i": "િ", "©": "ર્" }
  },
  "reverse_exceptions": { "ક્ષ": "û" }
}
```

Engine rules:
- **Longest-match-first** હંમેશા — લાંબા sequences ને `4_char`/`3_char`/`2_char` groups માં મૂકો.
- Pre-base **િ** glyph ની value ફક્ત `"િ"` — engine cluster પછી આપમેળે ખસેડે છે.
- **રેફ** glyph (syllable પછી ટાઇપ થતો) ની value ફક્ત `"ર્"` — engine cluster પહેલાં ખસેડે છે.
- એક Unicode માટે અનેક legacy glyphs હોય તો `reverse_exceptions` માં canonical આપો.
- `pre_process` / `post_process` માં regex rules (`"regex": true`, `/u` આપમેળે).

Admin → Mapping Editor માં JSON editing, CSV import/export અને live test box છે.
User-custom mappings `engine/mappings/custom/` માં રાખો — updates માં protected છે.

## 4. REST API

```
POST /api/v1/convert    (X-API-Key header)
GET  /api/v1/fonts
GET  /api/v1/usage
POST /api/v1/batch      (Business plan)
```
Full docs with cURL/PHP/Python/JS examples + live console: `/api` page.
Keys generate from user dashboard (Pro/Business plan) or Admin → API Keys.

## 5. Cron Jobs

```cron
0 3 * * *  php /path/to/site/cron/daily.php          # demo cleanup, expiry emails, log trim
0 2 * * 0  php /path/to/site/cron/backup.php         # weekly DB+files backup
0 6 * * *  php /path/to/site/cron/check_update.php   # update alert email (auto-install નહીં)
```

## 6. Deployment / VPS સેટઅપ

### Apache
Included `.htaccess` handles rewriting, HTTPS redirect, security headers, caching,
compression. `AllowOverride All` જરૂરી છે.

### Nginx + PHP-FPM
```nginx
server {
    listen 443 ssl http2;
    server_name yoursite.com;
    root /var/www/font-converter;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/yoursite.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yoursite.com/privkey.pem;

    # Security headers
    add_header X-Frame-Options SAMEORIGIN always;
    add_header X-Content-Type-Options nosniff always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    add_header Strict-Transport-Security "max-age=31536000" always;

    # Block sensitive dirs
    location ~ ^/(config|core|engine|controllers|views|storage|migrations|lang|tests|cron)/ { deny all; }
    location ~ /(install\.lock|version\.json)$ { deny all; }

    # API endpoints
    location ~ ^/api/v1/(convert|fonts|usage|batch)/?$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/api/v1/convert.php;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~* \.(css|js|png|jpe?g|webp|svg|ico|woff2?)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
server { listen 80; server_name yoursite.com; return 301 https://$host$request_uri; }
```

### PHP settings (production)
```ini
display_errors = Off
log_errors = On
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 120     ; updater set_time_limit(0) જાતે કરે છે
opcache.enable = 1
```

### SSL
```bash
apt install certbot python3-certbot-apache   # અથવા -nginx
certbot --apache -d yoursite.com
```

## 7. Testing

```bash
php tests/conversion_test.php
```
DB વગર ચાલે છે — 71 assertions: સાદા શબ્દ, છોટી ઈ માત્રા reordering, જોડાક્ષર, રેફ,
ક્ષ/જ્ઞ/ત્ર/શ્ર, અનુસ્વાર, નુક્તા, અંક, વિરામચિહ્ન, મિશ્ર ટેક્સ્ટ, round-trip (બન્ને દિશા),
ખાલી input, 50k perf (<2s), emoji, HTML preserve. જુઓ `TEST_REPORT.md`.

## 8. Troubleshooting

| સમસ્યા | ઉકેલ |
|---|---|
| Installer 403 આપે | `install.lock` delete કરો (ફક્ત fresh install માટે) |
| "Mapping data … not yet available" | એ ફોન્ટ stub છે — Admin → Mapping Editor માં data ભરો |
| 500 error, blank page | `storage/logs/php_errors.log` જુઓ; `config/config.php` માં `'debug' => true` |
| Update "authentication failed" | GitHub token expire થયો — Update Settings માં નવો આપો |
| Update અટકી ગયું | `storage/update.lock` + `storage/maintenance.flag` delete કરો; Admin → Backup થી restore |
| Emails નથી જતા | Settings → Email માં SMTP ભરો, "Test Email" દબાવો; logs જુઓ |
| િ/રેફ ખોટી જગ્યાએ | Mapping માં એ glyphs ની value બરાબર `"િ"`/`"ર્"` છે કે ચકાસો |
| Slow conversion | `storage/cache` writable છે? OPcache ચાલુ કરો |

## 9. Security Highlights

PDO prepared statements only · CSRF tokens on every POST · Argon2id password hashing ·
IP rate limiting (converter 30/min, login lockout 5×15min) · honeypot ·
session httponly/secure/SameSite=Strict + regeneration · security headers + CSP ·
sensitive dirs blocked (rewrite + `.htaccess` deny) · uploads: no PHP execution ·
converted text is **never stored** (only char counts) · GitHub token encrypted (AES-256-GCM),
masked in logs · update/restore require super_admin + password re-entry.

## 10. Project Layout

```
config/     — config.php (installer-generated, never updated), constants
core/       — Database, Router, Session, Security, Auth, Cache, Logger, Mailer, Updater, …
engine/     — FontConverter + MappingLoader + mappings/<lang>/*.json
controllers/, views/, assets/ — MVC-ish front-end
admin/      — panel (dashboard, fonts, mapping editor, users, plans, API keys,
              CMS, blog, SEO, logs, backup, update)
api/v1/     — REST endpoints
install/    — 6-step wizard + schema.sql + seed.sql
migrations/ — versioned SQL (auto-run on update)
cron/       — daily, backup, check_update
storage/    — cache, logs, backups, temp, uploads (all git-ignored)
```

License: MIT-style — use freely, font copyrights belong to their owners.

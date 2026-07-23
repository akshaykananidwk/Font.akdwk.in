# Test Report — Gujarati Font Converter v1.0.0

**Date:** 2026-07-23 · **Environment:** PHP 8.4 (intl ✓), MariaDB 10.11, Linux
**How to reproduce:** `php tests/conversion_test.php` (engine) + steps in each section below.

## 1. Conversion Engine — `tests/conversion_test.php`

**Result: 70 / 70 PASS** (runs without DB; uses the LMG reference mapping)

| # | Spec test case (§4.3) | Assertions | Result |
|---|---|---|---|
| 1 | સાદો શબ્દ (કમલ) | L→U + U→L | ✓ |
| 2 | છોટી ઈ માત્રા (કિરણ) — pre-base reorder | બન્ને દિશા | ✓ |
| 3 | જોડાક્ષર (વિદ્યા, વિદ્યાર્થી) — ligature + explicit હલંત બન્ને input | 4 | ✓ |
| 4 | રેફ (કાર્ય, સૂર્ય, ધર્મ, સ્વર્ગ, કાર્યં) + રકાર (્ર) ≠ રેફ (પ્રકાશ) | 8 | ✓ |
| 5 | ક્ષ / જ્ઞ / ત્ર / શ્ર ligature glyphs + longest-match reverse | 6 | ✓ |
| 6 | અનુસ્વાર (અંક, સંસ્કૃત), ચંદ્રબિંદુ | 4 | ✓ |
| 7 | નુક્તા (ઝ઼, ફ઼) | 3 | ✓ |
| 8 | અંક ૦-૯ બન્ને દિશા | 2 | ✓ |
| 9 | વિરામચિહ્ન । ॥ , . ? ! અકબંધ | 3 | ✓ |
| 10 | મિશ્ર: English + અંક + emoji અકબંધ (U→L) | 2 | ✓ |
| 11 | **Round-trip L→U→L: 10 inputs, 100% identical** | 10 | ✓ |
| 11b | **Round-trip U→L→U: 8 inputs (કીર્તિ સહિત), 100% identical** | 8 | ✓ |
| 12 | ખાલી input → ખાલી output, error નહીં | 2 | ✓ |
| 13 | ફક્ત spaces → જેમનું તેમ | 1 | ✓ |
| 14 | 50,000 અક્ષર < 2s → **માપ્યું: 0.004s**; 10,000 < 300ms → **0.7ms**; 50k round-trip સચોટ | 4 | ✓ |
| 15 | Emoji 🙏 + Unicode | 1 | ✓ |
| 16 | HTML tags preserve (`<b>`, attribute glyphs સુરક્ષિત) | 3 | ✓ |
| 17 | Composed matras (ા+ે→ો post-process, અ+ા→આ normalize) | 3 | ✓ |
| 18 | Stub mapping → સ્પષ્ટ RuntimeException | 1 | ✓ |
| 19 | Invalid direction → InvalidArgumentException | 1 | ✓ |

**Performance vs targets:** 10k chars target <300ms → actual **0.7ms**; 50k target <2s → actual **4ms**.

Design note: reverse conversion **canonicalizes** — e.g. explicit-halant typing `qÙy` (દ+્+ય)
and ligature glyph `±` (દ્ય) both convert to the same Unicode; Unicode→legacy emits the
canonical ligature glyph, exactly as a real legacy font renders it. Round-trip on canonical
input is byte-identical.

## 2. Installer (end-to-end via HTTP)

| Step | Check | Result |
|---|---|---|
| 1 | Requirement checker (PHP, 7 extensions, writables, url_fopen) | ✓ pass/fail UI |
| 2 | License checkbox gate | ✓ |
| 3 | AJAX "Test Connection" (creates DB if missing) → schema + seed run | ✓ |
| — | **20 tables** created, utf8mb4_unicode_ci, FKs + indexes | ✓ |
| — | Seed: **94 fonts**, 4 languages, 4 plans, 4 CMS pages, 24 settings | ✓ |
| 4 | Site settings (URL auto-detect) | ✓ |
| 5 | Admin created (`super_admin`, Argon2id hash), baseline migration recorded | ✓ |
| 6 | `config/config.php` + `install.lock` written; re-visiting `/install/` → **403** | ✓ |

## 3. Public Site (live HTTP tests)

| Test | Result |
|---|---|
| Homepage 200, H1, 94-font searchable dropdown rendered | ✓ |
| AJAX convert L→U: `kml ikrN kay©` → `કમલ કિરણ કાર્ય` (1.2ms) | ✓ |
| AJAX convert U→L: `કમલ કિરણ કાર્ય` → `kml ikrN kay©` | ✓ |
| Demo counter decrements (20 → 19 → 18), `/demo-status` live | ✓ |
| 250-char input → 403 + Gujarati upgrade prompt | ✓ |
| Stub font (saral) → clear error message | ✓ |
| Wrong/missing CSRF → 403 | ✓ |
| Font SEO pages `/lmg-to-unicode-converter`, `/shree-guj-0768-...` → 200, unique H1/meta | ✓ |
| Language landing `/gujarati-font-converter` → 200 | ✓ |
| All static routes (faq, privacy, terms, pricing, contact, blog, api, login, register, guide) → 200 | ✓ |
| `/sitemap.xml` dynamic — **107 URLs** (94 fonts + 13 static) | ✓ |
| `/robots.txt` with disallows + sitemap link | ✓ |
| Unknown URL → styled 404 | ✓ |
| Paid user → `demo-status: unlimited` | ✓ |

## 4. Auth + Admin Panel

| Test | Result |
|---|---|
| Admin wrong password → "Invalid credentials" (+ lockout counter) | ✓ |
| Admin correct login → 302 dashboard; session regenerated | ✓ |
| All 13 admin pages return 200 (dashboard, fonts, mapping editor, users, plans, api-keys, pages, blog, seo, logs, settings, backup, update) | ✓ |
| backup.php / update.php gated to super_admin | ✓ |
| Unauthenticated admin URL → redirect to login | ✓ |
| User register → login → dashboard → generate API key (Pro plan) | ✓ |

## 5. REST API

| Test | Result |
|---|---|
| POST /api/v1/convert with key → converted text + usage `{calls_today, daily_limit, remaining}` | ✓ |
| GET /api/v1/fonts → 94 fonts | ✓ |
| Usage counter increments per call; daily reset by `calls_date` | ✓ |
| Invalid key → 401; missing key → 401 | ✓ |
| Conversion text **not stored** — only char_count in `conversions_log` (schema-verified) | ✓ |

## 6. Updater Machinery (unit-tested locally)

| Test | Result |
|---|---|
| Pure-PHP DB dump (no mysqldump needed) — 37KB for seeded DB | ✓ |
| Files backup zip valid, 195 files, `storage/backups` excluded (no recursion) | ✓ |
| **DB restore round-trip:** value changed → restore → original value back | ✓ |
| Protected-path matcher: 8/8 cases (config.php, uploads/, custom mappings, install.lock, robots.txt protected; engine mappings, index.php, constants.php updatable) | ✓ |
| Migration runner: new file runs + recorded; second run skips; `{{prefix}}` replaced | ✓ |
| GitHub API calls (check/download) | ⚠ **Not exercised live** — requires a configured repo + token; code paths reviewed, all failure modes throw → auto-rollback |

## 7. Static Analysis

- `php -l` on **all PHP files** (114): 0 syntax errors, PHP 8.4-clean (no deprecated functions)
- Engine tests run with `error_reporting(E_ALL)`: no warnings/notices
- All SQL via PDO prepared statements (no interpolated variables in queries)

## Known Limitations (by design/scope)

1. **93 of 94 mappings are stubs** — per project decision, glyph data is to be filled from real
   font specimens via Admin → Mapping Editor / CSV import. The engine and LMG reference prove
   the pipeline.
2. Payment gateways are structural hooks only (plans/subscriptions tables + manual admin assignment).
3. AMP layout, TinyMCE bundle and WebP asset pipeline are not included (plain fast HTML/CSS keeps
   pages lightweight instead).
4. Live GitHub update requires a real repo/token to exercise end-to-end (see §6).

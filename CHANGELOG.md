# Changelog

## [1.0.0] — 2026-07-23

### Added
- Server-side conversion engine (Legacy ⇄ Unicode) with longest-match-first replacement, િ-matra and reph reordering, NFC normalization, chunked processing and HTML-preserve mode
- LMG reference mapping (fully round-trip tested) + 93 stub mappings for Gujarati, Hindi, Marathi, Nepali fonts
- AJAX converter UI with searchable font dropdown, dark mode, copy/download/swap, keyboard shortcuts, demo counters
- 94 auto-generated font-wise SEO landing pages + language landing pages
- 6-step installation wizard with server checks and DB setup
- Admin panel: dashboard, fonts, mapping editor, users, plans, API keys, CMS pages, blog, SEO, settings, logs, backups
- GitHub one-click auto-update with backup, protected files, migrations and auto-rollback
- REST API v1: convert, fonts, usage, batch endpoints with API-key auth and daily limits
- Demo limit system (IP-based, lazy 24h reset)
- Dynamic sitemap.xml, robots.txt, JSON-LD schema, OG/Twitter meta
- File-based cache, rate limiting, CSRF protection, security headers

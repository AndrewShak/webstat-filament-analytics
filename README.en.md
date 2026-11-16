# Webstat Filament Analytics

[🇷🇺 Русский](README.md) · 🇬🇧 English

Self-hosted web analytics for Laravel 12 with deep Filament 5 and Livewire 4 integration.

The package collects visit and user-behaviour statistics on your own server. No Google Analytics, Yandex Metrika or any other external analytics service is needed. All data stays in your database.

## Features

- Visits, page views, sessions, "online now".
- **Where a visitor comes from:** IP address, country and city, device (desktop / phone / tablet), brand and model (Apple iPhone, Samsung SM-S918B…), browser, OS and version, screen size.
- Audience: new vs returning, people vs bots, age and gender (via your own data provider).
- Traffic sources: search engines, referrers, social networks, UTM campaigns, landing pages, page titles, user paths.
- Click, scroll, view and link maps.
- Form analytics: started, submitted, abandoned, drop-off per field (field values are never collected).
- Goals (4 types) and sequential funnels.
- Dashboard in **Russian and English** with an RU / EN switch.
- Redis buffering for high traffic, automatic pruning of old data.

All reports live in the Filament admin panel under **"Analytics"**.

---

## 1. Requirements

| Component | Requirement |
|---|---|
| PHP | 8.2 or higher |
| Laravel | 12.x |
| Filament | 5.x |
| Livewire | 4.x |
| Composer | 2.x |
| Database | MySQL 8.0+, MariaDB 10.2+, PostgreSQL, SQLite 3.25+ |
| Redis | optional (only for the `redis` buffer) |

The package pulls in `geoip2/geoip2` itself (country detection without external requests).

> Built for Laravel 12 and Filament 5. Other major versions are not guaranteed.

---

## 2. Installation

### Option A. Composer (published package)

```bash
cd /path/to/your-laravel-project
composer require webstat/filament-analytics
php artisan optimize:clear
```

### Option B. Local folder (path repository)

Project layout:

```text
project/
├── packages/
│   └── webstat-analytics/      ← package source
└── src/                        ← Laravel app (composer.json, artisan, app/)
```

1. In `src/composer.json`, add to the **existing** `repositories` array (create one at the top level if missing):

```json
{
    "type": "path",
    "url": "../packages/webstat-analytics",
    "options": { "symlink": true }
}
```

2. Require the package:

```bash
cd src
composer require webstat/filament-analytics:@dev -W
```

With Docker, run Composer inside the app container:

```bash
docker compose exec --user "$(id -u):$(id -g)" app composer require webstat/filament-analytics:@dev -W
```

and mount the package into the container (`docker-compose.yml`):

```yaml
volumes:
  - ./src:/var/www/html
  - ./packages/webstat-analytics:/var/www/packages/webstat-analytics:ro
```

The path `../packages/webstat-analytics` in `src/composer.json` must resolve to `/var/www/packages/webstat-analytics` inside the container.

3. Verify:

```bash
composer show webstat/filament-analytics
```

---

## 3. Laravel and Filament registration

The service provider is auto-discovered; no manual registration is needed.

The Filament plugin is registered automatically on every panel. If auto-registration is off (`filament.auto_register = false`) or you want a single panel, add it manually:

```php
use Webstat\Analytics\Filament\AnalyticsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(AnalyticsPlugin::make());
}
```

The plugin uses `discoverClusters()`: the **"Analytics"** cluster and its four tabs are discovered automatically, so you don't list pages yourself.

Afterwards:

```bash
php artisan optimize:clear
```

---

## 4. Migrations

Migrations load automatically:

```bash
php artisan migrate
```

| Table | Purpose |
|---|---|
| `analytics_sessions` | Sessions: IP, country, city, device, brand/model, OS, browser, screen, source, landing page |
| `analytics_events` | Events: page views, clicks, links, scroll, forms, goals |
| `analytics_goals` | Configured goals |

Upgrading from an earlier version: the `add_visitor_details_to_analytics_sessions` migration safely adds `ip`, `os_version`, `device_brand`, `device_model` and `screen` to the existing table. Just run `php artisan migrate`.

> Use plain `php artisan migrate`. Do not use `migrate:fresh` or `db:wipe`; they delete application data.

---

## 5. Installing the tracker

The tables alone collect nothing: the tracker must be added to your pages.

### Option A. Blade component (recommended)

In your main layout (e.g. `resources/views/layouts/app.blade.php`) before `</body>`:

```blade
    <x-analytics::tracker />
</body>
```

With a Content Security Policy:

```blade
<x-analytics::tracker nonce="{{ $nonce }}" />
```

### Option B. Automatic injection via middleware

No template changes. Off by default. In `config/analytics.php`:

```php
'inject' => ['enabled' => true, 'global' => true],
```

or `enabled = true` plus the `analytics.inject` middleware alias on selected routes. Don't enable both options at once: the tracker would be added twice (it ignores a second load, but there is no reason to).

Afterwards: `php artisan optimize:clear`.

---

## 6. Configuration

```bash
php artisan vendor:publish --tag=analytics-config
```

The file appears at `config/analytics.php`. Main options:

| Option | Purpose |
|---|---|
| `enabled` | Turn collection on/off |
| `connection` | Dedicated DB connection |
| `route.prefix` | Collect endpoint prefix (default `_wa`) |
| `allowed_hosts` | Extra hosts accepted in the Origin header |
| `buffer.driver` | `direct` or `redis` |
| `retention_days` | Data retention (395) |
| `privacy.ip` | `full` / `anonymized` / `none`: how the IP is stored |
| `privacy.ip_headers` | Headers carrying the real IP behind a proxy |
| `privacy.respect_dnt` | Honour Do Not Track and Global Privacy Control |
| `tracking.*` | Clicks, links, scroll, forms, SPA |
| `geo.driver`, `geo.database`, `geo.http.*` | Country and city detection |
| `locale`, `locales` | Dashboard language |
| `filament.auto_register`, `.panels`, `.ability` | Registration and access |

> If you published the config earlier, add the new keys (`privacy.ip`, `geo.database`, `geo.http`, `locale`) or re-publish with `--force`. Without them the package falls back to defaults.

---

## 7. IP address and country detection

### Visitor IP

Each session stores the IP in `analytics_sessions.ip`. Detection is proxy-aware: if the TCP peer is a private address (Docker network, nginx without `TrustProxies`), the IP is read from `CF-Connecting-IP`, `True-Client-IP`, `X-Real-IP`, `X-Forwarded-For`. If the peer is a public address, headers are not trusted (they can be forged).

How to store the IP (`.env`):

```env
ANALYTICS_STORE_IP=full         # full address (default)
# ANALYTICS_STORE_IP=anonymized # 203.0.113.0 / first 48 bits of IPv6
# ANALYTICS_STORE_IP=none       # not stored (country is still detected)
```

> An IP address is personal data in many jurisdictions (GDPR etc.). Use `anonymized` or `none` if needed, and disclose the data collection in your privacy policy.

### Country and city

Detection order:

1. **CDN headers:** Cloudflare, CloudFront, Vercel, Google App Engine. Free and instant.
2. **Offline `.mmdb` database.** Recommended for most sites:

```bash
php artisan analytics:geo-update                  # country + city (~130 MB, DB-IP Lite)
php artisan analytics:geo-update --type=country   # country only, small download
```

The database is stored at `storage/app/geoip/geoip-city.mmdb`. You can drop a MaxMind GeoLite2 file there instead or pass `--url=`. Refresh it monthly (e.g. via the scheduler).

3. **Online lookup (last resort, off by default):**

```env
ANALYTICS_GEO_HTTP=true
```

The IP is sent to ipwho.is (or ipapi.co via `geo.http.provider`). It runs **after** the visitor received the response and is cached per IP.

If no country could be determined, the "Audience" tab shows a hint to run `analytics:geo-update`.

---

## 8. Devices

Every session stores:

| Field | Example |
|---|---|
| `device` | `desktop`, `mobile`, `tablet`, `tv`, `bot` |
| `device_brand`, `device_model` | `Apple` · `iPhone`, `Samsung` · `SM-S918B`, `Google` · `Pixel 8` |
| `os`, `os_version` | `iOS 17`, `Android 14`, `Windows 10/11`, `macOS 10.15` |
| `browser`, `browser_version` | `Chrome 130`, `Safari 17` |
| `screen` | `390x844` |

Limitation: modern Chrome on Android hides the phone model (the User-Agent contains `K`). In that case only device type, OS and browser are stored.

Reports: "Audience" (latest visits with IP, location and device; device models; top IPs) and "Sources & Behavior" (devices, browsers, OS versions, screen sizes).

---

## 9. Dashboard language (RU / EN)

Every analytics tab has an **RU | EN** switch at the right end of the filter bar. The choice is kept in the session and applies to the whole analytics UI, including the sidebar.

Default language:

```env
ANALYTICS_LOCALE=en   # empty = app locale (APP_LOCALE) when it is ru or en
```

Supported languages: `locales` in the config. To add a language: `php artisan vendor:publish --tag=analytics-lang`, copy `lang/vendor/analytics/en` to a new folder and translate it.

---

## 10. Redis buffer (high traffic)

By default (`direct`) events are written to the database right after the response is sent. For larger sites:

```env
ANALYTICS_BUFFER=redis
ANALYTICS_REDIS_CONNECTION=default
```

Events are pushed to Redis and `analytics:flush` writes them to the database once a minute in a single transaction. The Laravel Scheduler must be running:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

or `php artisan schedule:work`. Don't enable `redis` unless Redis is configured.

---

## 11. Goals and funnels

| Type | What it measures |
|---|---|
| `path` | Visit to a URL (`*` = wildcard, e.g. `/blog/*`) |
| `event` | JS event `analytics.track('name')` or `data-wa-goal` |
| `click` | Click on an element or link (part of a selector / URL) |
| `form` | Form submit (part of the id or action) |

Create goals on the **"Goals & Conversions"** tab. To build a funnel, give several goals a "Funnel step" (1, 2, 3…).

```javascript
analytics.track('signup', 49);   // event goal + value
```

```html
<button data-wa-goal="subscribe">Subscribe</button>  <!-- click goal -->
<div data-wa-ignore>…</div>                          <!-- never tracked -->
<div data-wa-mask>…</div>                            <!-- clicks yes, text omitted -->
```

---

## 12. Privacy and security

- The IP is stored according to `privacy.ip` (see section 7).
- Form field values are never collected, only field names.
- Do Not Track and Global Privacy Control are honoured.
- First-party cookie `_wa_v`, 365 days, renewed on every visit.
- `POST /_wa/c` uses no sessions or CSRF, is rate- and size-limited (64 KB) and checks the Origin header.
- The endpoint **always** answers `204`, even when data is rejected. So `204` does not prove data was stored: check `analytics_sessions` and `analytics_events`.
- Dashboard access: `filament.ability` (Gate name) and `filament.panels` (panel ids, `['*']` = all).

These settings do not replace legal consent requirements.

---

## 13. Maintenance

```bash
php artisan analytics:prune            # delete data older than retention_days
php artisan analytics:flush            # (redis) write the buffer to the database
php artisan analytics:geo-update       # refresh the geolocation database
```

`prune` (and `flush` in redis mode) are registered in the scheduler automatically.

---

## 14. Verifying the installation

```bash
php artisan package:discover
php artisan optimize:clear
php artisan migrate
php artisan route:list --path=_wa
php artisan route:list --path=admin/analytics
```

Then:

1. Open any page of your site and find `POST /_wa/c` in DevTools → Network (response 204).
2. Check the database:

```sql
SELECT started_at, ip, country, city, device, device_brand, device_model, os, browser
FROM analytics_sessions ORDER BY id DESC LIMIT 5;
```

3. Open the admin panel → "Analytics".

### Troubleshooting

| Symptom | Cause and fix |
|---|---|
| No "Analytics" menu item | Run `php artisan optimize:clear`; check `filament.panels` and `filament.ability`; register the plugin manually |
| `/_wa/c` requests arrive but tables are empty | Migrations not run; `bots.store=false` while testing with curl; `redis` mode without `schedule:run` |
| IP is always `172.x` / `10.x` | The proxy doesn't forward the real IP: configure `TrustProxies` or `privacy.ip_headers` |
| Country is empty | `php artisan analytics:geo-update` or CDN headers |
| No phone models | Chrome hides the model (see section 8) |
| Dashboard is in the wrong language | Use the RU/EN switch or set `ANALYTICS_LOCALE` |

---

## 15. Testing the package

The package ships automated tests built on Pest + Orchestra Testbench (in-memory SQLite; your real database is never touched).

```bash
cd packages/webstat-analytics
composer install
vendor/bin/pest
```

A single file or test:

```bash
vendor/bin/pest tests/Feature/CollectTest.php
vendor/bin/pest --filter="IP"
```

What is covered:

| File | Checks |
|---|---|
| `tests/Unit/ParsersTest.php` | Browser, OS, device type, brand and model; bots; sources; LIKE patterns |
| `tests/Unit/ClientIpTest.php` | Real IP behind proxies, header-spoofing protection, IP storage modes |
| `tests/Unit/LocaleTest.php` | Language selection, ru/en key parity |
| `tests/Feature/CollectTest.php` | Ingestion, IP, country, privacy modes, bots, garbage filtering, Writer, pruning |
| `tests/Feature/ReportsTest.php` | Overview, audience, devices, maps, forms, goals, funnel, periods |
| `tests/Feature/FilamentTest.php` | Cluster + 4 pages registration, access and Gate, Russian and English UI, language switch, filters |

You can also verify a live site manually:

```bash
curl -i -X POST https://your-site.test/_wa/c \
  -H 'Content-Type: text/plain' \
  -H 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36' \
  -H 'Accept-Language: en' -H 'Sec-Fetch-Site: same-origin' \
  --data '{"vid":"bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb","sid":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","nv":1,"sw":1920,"sh":1080,"e":[{"type":"pageview","path":"/test"}]}'
```

You should get `204` and a new row in `analytics_sessions`.

---

## 16. Package layout

```text
webstat-analytics/
├── composer.json
├── README.md / README.en.md
├── config/analytics.php
├── database/migrations/
├── resources/
│   ├── js/tracker.js
│   ├── lang/{ru,en}/analytics.php
│   └── views/
├── routes/analytics.php
├── src/
│   ├── AnalyticsServiceProvider.php
│   ├── Console/        flush, prune, geo-update
│   ├── Filament/       Plugin, Cluster, Pages, Concerns
│   ├── Http/           CollectController, InjectTracker
│   ├── Ingest/         ClientIp, UserAgentParser, BotDetector, Geo/, Writer, Buffer/
│   ├── Models/Goal.php
│   ├── Reports/        AnalyticsReports, DateRange, SqlDialect
│   └── Support/        Locale, Svg, Countries
└── tests/
```

---

## License

MIT. The DB-IP Lite geolocation data is licensed under CC BY 4.0 (attribution to DB-IP.com required).

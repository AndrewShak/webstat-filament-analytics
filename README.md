# Webstat Filament Analytics

🇷🇺 Русский · [🇬🇧 English](README.en.md)

Самохостинговая веб-аналитика для Laravel 12 с глубокой интеграцией в Filament 5 и Livewire 4.

Пакет собирает статистику посещений и действий пользователей прямо на вашем сервере. Не нужны Google Analytics, Яндекс Метрика и другие внешние сервисы аналитики. Все данные лежат в вашей базе.

## Возможности

- Посещения, просмотры страниц, сессии, «сейчас на сайте».
- **Откуда пришёл посетитель:** IP-адрес, страна и город, устройство (компьютер / телефон / планшет), марка и модель (Apple iPhone, Samsung SM-S918B…), браузер, ОС и её версия, размер экрана.
- Аудитория: новые и вернувшиеся, люди и боты, возраст и пол (через ваш провайдер данных).
- Источники трафика: поисковые системы, сайты-источники, соцсети, UTM-кампании, страницы входа, заголовки страниц, пути пользователей.
- Карты кликов, прокрутки, просмотров и ссылок.
- Аналитика форм: начатые, отправленные, брошенные, отказы по полям (значения полей не собираются).
- Цели (4 типа) и последовательные воронки.
- Интерфейс на **русском и английском** с переключателем RU / EN.
- Буферизация через Redis для высокой нагрузки, автоматическая очистка старых данных.

Все отчёты находятся в админ-панели Filament, раздел **«Аналитика»**.

---

## 1. Требования

| Компонент | Требование |
|---|---|
| PHP | 8.2 или выше |
| Laravel | 12.x |
| Filament | 5.x |
| Livewire | 4.x |
| Composer | 2.x |
| База данных | MySQL 8.0+, MariaDB 10.2+, PostgreSQL, SQLite 3.25+ |
| Redis | необязательно (только для режима `redis`) |

Пакет сам подтягивает `geoip2/geoip2` (определение страны без внешних запросов).

> Пакет рассчитан на Laravel 12 и Filament 5. Другие мажорные версии не гарантируются.

---

## 2. Установка

### Вариант A. Через Composer (опубликованный пакет)

```bash
cd /path/to/your-laravel-project
composer require webstat/filament-analytics
php artisan optimize:clear
```

### Вариант B. Локальная папка (path repository)

Структура проекта:

```text
project/
├── packages/
│   └── webstat-analytics/      ← исходники пакета
└── src/                        ← Laravel-приложение (composer.json, artisan, app/)
```

1. В `src/composer.json` добавьте в **существующий** массив `repositories` (если его нет — создайте один на верхнем уровне):

```json
{
    "type": "path",
    "url": "../packages/webstat-analytics",
    "options": { "symlink": true }
}
```

2. Установите пакет:

```bash
cd src
composer require webstat/filament-analytics:@dev -W
```

В Docker запускайте Composer в контейнере приложения:

```bash
docker compose exec --user "$(id -u):$(id -g)" app composer require webstat/filament-analytics:@dev -W
```

и примонтируйте пакет в контейнер (`docker-compose.yml`):

```yaml
volumes:
  - ./src:/var/www/html
  - ./packages/webstat-analytics:/var/www/packages/webstat-analytics:ro
```

Путь `../packages/webstat-analytics` из `src/composer.json` должен указывать на `/var/www/packages/webstat-analytics` внутри контейнера.

3. Проверьте установку:

```bash
composer show webstat/filament-analytics
```

---

## 3. Регистрация в Laravel и Filament

Service Provider подключается автоматически (Laravel Package Discovery), вручную добавлять его не нужно.

Плагин Filament тоже регистрируется автоматически на всех панелях. Если автоматическая регистрация выключена (`filament.auto_register = false`) или нужна только одна панель, добавьте плагин вручную:

```php
use Webstat\Analytics\Filament\AnalyticsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(AnalyticsPlugin::make());
}
```

Плагин использует `discoverClusters()`: пункт **«Аналитика»** (кластер) и четыре вкладки находятся автоматически, перечислять страницы не нужно.

После изменений:

```bash
php artisan optimize:clear
```

---

## 4. Миграции

Миграции подключаются автоматически:

```bash
php artisan migrate
```

| Таблица | Назначение |
|---|---|
| `analytics_sessions` | Сессии: IP, страна, город, устройство, марка/модель, ОС, браузер, экран, источник, страница входа |
| `analytics_events` | События: просмотры, клики, ссылки, прокрутка, формы, цели |
| `analytics_goals` | Настроенные цели |

Обновление с прошлой версии: миграция `add_visitor_details_to_analytics_sessions` безопасно добавит колонки `ip`, `os_version`, `device_brand`, `device_model`, `screen` в уже существующую таблицу. Просто выполните `php artisan migrate`.

> Используйте обычный `php artisan migrate`. Не применяйте `migrate:fresh` и `db:wipe`, они удаляют данные приложения.

---

## 5. Подключение трекера

Сами по себе таблицы ничего не собирают: трекер нужно подключить к страницам сайта.

### Способ A. Blade-компонент (рекомендуется)

В основном layout (например, `resources/views/layouts/app.blade.php`) перед `</body>`:

```blade
    <x-analytics::tracker />
</body>
```

С Content Security Policy:

```blade
<x-analytics::tracker nonce="{{ $nonce }}" />
```

### Способ B. Автоматическое внедрение через middleware

Без правки шаблонов. По умолчанию выключено. В `config/analytics.php`:

```php
'inject' => ['enabled' => true, 'global' => true],
```

или `enabled = true` и алиас `analytics.inject` на нужных маршрутах. Не включайте оба способа одновременно: трекер подключится дважды (повторную загрузку он игнорирует, но лишнее лучше не делать).

После изменений: `php artisan optimize:clear`.

---

## 6. Конфигурация

```bash
php artisan vendor:publish --tag=analytics-config
```

Файл появится в `config/analytics.php`. Основные параметры:

| Параметр | Назначение |
|---|---|
| `enabled` | Включить / выключить сбор |
| `connection` | Отдельное подключение к БД |
| `route.prefix` | Префикс endpoint сбора (по умолчанию `_wa`) |
| `allowed_hosts` | Дополнительные домены в заголовке Origin |
| `buffer.driver` | `direct` или `redis` |
| `retention_days` | Срок хранения данных (395) |
| `privacy.ip` | `full` / `anonymized` / `none`, как хранить IP |
| `privacy.ip_headers` | Заголовки с реальным IP за прокси |
| `privacy.respect_dnt` | Уважать Do Not Track и Global Privacy Control |
| `tracking.*` | Клики, ссылки, прокрутка, формы, SPA |
| `geo.driver`, `geo.database`, `geo.http.*` | Определение страны и города |
| `locale`, `locales` | Язык интерфейса аналитики |
| `filament.auto_register`, `.panels`, `.ability` | Регистрация и доступ |

> Если вы публиковали конфиг раньше, добавьте новые ключи (`privacy.ip`, `geo.database`, `geo.http`, `locale`) или опубликуйте файл заново (`--force`). Без них пакет использует значения по умолчанию.

---

## 7. IP-адрес и определение страны

### IP посетителя

Каждая сессия сохраняет IP в `analytics_sessions.ip`. Определение учитывает прокси: если TCP-соединение пришло с приватного адреса (Docker-сеть, nginx без `TrustProxies`), IP берётся из заголовков `CF-Connecting-IP`, `True-Client-IP`, `X-Real-IP`, `X-Forwarded-For`. Если соединение пришло с публичного адреса, заголовкам не доверяют (их можно подделать).

Как хранить IP (`.env`):

```env
ANALYTICS_STORE_IP=full         # полный адрес (по умолчанию)
# ANALYTICS_STORE_IP=anonymized # 203.0.113.0 / первые 48 бит IPv6
# ANALYTICS_STORE_IP=none       # не хранить (страна всё равно определяется)
```

> IP-адрес считается персональными данными во многих странах (GDPR и др.). Если нужно, используйте `anonymized` или `none` и сообщите о сборе данных в политике конфиденциальности.

### Страна и город

Порядок определения:

1. **Заголовки CDN:** Cloudflare, CloudFront, Vercel, Google App Engine. Бесплатно и мгновенно.
2. **Офлайн-база `.mmdb`.** Рекомендуется для большинства сайтов:

```bash
php artisan analytics:geo-update              # страна + город (~130 МБ, DB-IP Lite)
php artisan analytics:geo-update --type=country   # только страна, база небольшая
```

База ставится в `storage/app/geoip/geoip-city.mmdb`. Вместо DB-IP можно положить туда MaxMind GeoLite2 или указать `--url=`. Обновляйте базу раз в месяц (например, через планировщик).

3. **Онлайн-запрос (последний вариант, выключен по умолчанию):**

```env
ANALYTICS_GEO_HTTP=true
```

IP отправляется на ipwho.is (или ipapi.co, параметр `geo.http.provider`). Запрос выполняется **после** ответа посетителю и кешируется по IP.

Если страна не определилась, в разделе «Аудитория» появится подсказка выполнить `analytics:geo-update`.

---

## 8. Устройства

Для каждой сессии сохраняется:

| Поле | Пример |
|---|---|
| `device` | `desktop`, `mobile`, `tablet`, `tv`, `bot` |
| `device_brand`, `device_model` | `Apple` · `iPhone`, `Samsung` · `SM-S918B`, `Google` · `Pixel 8` |
| `os`, `os_version` | `iOS 17`, `Android 14`, `Windows 10/11`, `macOS 10.15` |
| `browser`, `browser_version` | `Chrome 130`, `Safari 17` |
| `screen` | `390x844` |

Ограничение: современный Chrome на Android скрывает модель телефона (в User-Agent стоит `K`). Тогда сохраняется только тип устройства, ОС и браузер.

Отчёты: «Аудитория» (последние визиты с IP, местом и устройством; модели устройств; частые IP) и «Источники и поведение» (устройства, браузеры, ОС с версиями, размеры экранов).

---

## 9. Язык интерфейса (RU / EN)

В правом верхнем углу панели фильтров на каждой вкладке аналитики есть переключатель **RU | EN**. Выбор сохраняется в сессии и действует на всю аналитику, включая боковое меню.

Язык по умолчанию:

```env
ANALYTICS_LOCALE=ru   # пусто = язык приложения (APP_LOCALE), если он ru или en
```

Список языков: `locales` в конфиге. Свой язык: `php artisan vendor:publish --tag=analytics-lang`, затем скопируйте `lang/vendor/analytics/en` в новую папку и переведите.

---

## 10. Redis-буфер (высокая нагрузка)

По умолчанию (`direct`) события пишутся в БД сразу после отправки ответа посетителю. Для больших сайтов:

```env
ANALYTICS_BUFFER=redis
ANALYTICS_REDIS_CONNECTION=default
```

События складываются в Redis, а команда `analytics:flush` раз в минуту записывает их в БД одной транзакцией. Нужен работающий Laravel Scheduler:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

или `php artisan schedule:work`. Не включайте `redis`, если Redis не настроен.

---

## 11. Цели и воронки

| Тип | Что измеряет |
|---|---|
| `path` | Посещение URL (`*` = подстановка, например `/blog/*`) |
| `event` | JS-событие `analytics.track('имя')` или `data-wa-goal` |
| `click` | Клик по элементу или ссылке (часть селектора / URL) |
| `form` | Отправка формы (часть id или action) |

Цели создаются на вкладке **«Цели и конверсии»**. Чтобы построить воронку, задайте нескольким целям «Шаг воронки» (1, 2, 3…).

```javascript
analytics.track('signup', 49);   // цель-событие + значение
```

```html
<button data-wa-goal="subscribe">Подписаться</button>  <!-- цель по клику -->
<div data-wa-ignore>…</div>                            <!-- не отслеживать -->
<div data-wa-mask>…</div>                              <!-- клики да, текст не собирать -->
```

---

## 12. Приватность и безопасность

- IP хранится по правилам `privacy.ip` (см. раздел 7).
- Значения полей форм не собираются, только имена полей.
- Уважаются Do Not Track и Global Privacy Control.
- Cookie первой стороны `_wa_v`, срок 365 дней, обновляется при каждом визите.
- Endpoint `POST /_wa/c` не использует сессии и CSRF, ограничен по частоте и размеру (64 КБ), проверяет Origin.
- Endpoint **всегда** отвечает `204`, даже если данные отклонены. Поэтому `204` не доказывает, что запись произошла: смотрите таблицы `analytics_sessions` и `analytics_events`.
- Доступ к панели: `filament.ability` (имя Gate) и `filament.panels` (список панелей, `['*']` = все).

Настройки не заменяют юридические требования к согласию на обработку данных.

---

## 13. Обслуживание

```bash
php artisan analytics:prune            # удалить данные старше retention_days
php artisan analytics:flush            # (redis) записать буфер в БД
php artisan analytics:geo-update       # обновить базу геолокации
```

`prune` (и `flush` в режиме redis) регистрируются в планировщике автоматически.

---

## 14. Проверка установки

```bash
php artisan package:discover
php artisan optimize:clear
php artisan migrate
php artisan route:list --path=_wa
php artisan route:list --path=admin/analytics
```

Затем:

1. Откройте любую страницу сайта и в DevTools → Network найдите `POST /_wa/c` (ответ 204).
2. Проверьте БД:

```sql
SELECT started_at, ip, country, city, device, device_brand, device_model, os, browser
FROM analytics_sessions ORDER BY id DESC LIMIT 5;
```

3. Откройте админ-панель → «Аналитика».

### Если что-то не работает

| Симптом | Причина и решение |
|---|---|
| Нет пункта «Аналитика» | Выполните `php artisan optimize:clear`; проверьте `filament.panels` и `filament.ability`; добавьте плагин вручную |
| Запросы `/_wa/c` идут, а таблицы пусты | Не выполнены миграции; включён `bots.store=false` и тест идёт из curl; режим `redis` без `schedule:run` |
| В IP всегда `172.x` / `10.x` | Прокси не передаёт реальный IP: настройте `TrustProxies` или заголовки в `privacy.ip_headers` |
| Страна пустая | `php artisan analytics:geo-update` или CDN-заголовки |
| Нет моделей телефонов | Chrome скрывает модель (см. раздел 8) |
| Страница аналитики на английском | Переключите RU/EN или задайте `ANALYTICS_LOCALE=ru` |

---

## 15. Тестирование пакета

В пакете есть автотесты на Pest + Orchestra Testbench (SQLite в памяти, ваша рабочая БД не затрагивается).

```bash
cd packages/webstat-analytics
composer install
vendor/bin/pest
```

Один файл или тест:

```bash
vendor/bin/pest tests/Feature/CollectTest.php
vendor/bin/pest --filter="IP"
```

Что проверяется:

| Файл | Проверки |
|---|---|
| `tests/Unit/ParsersTest.php` | Браузер, ОС, тип устройства, марка и модель; боты; источники; LIKE-шаблоны |
| `tests/Unit/ClientIpTest.php` | Реальный IP за прокси, защита от подделки заголовков, режимы хранения IP |
| `tests/Unit/LocaleTest.php` | Выбор языка, совпадение ключей ru и en |
| `tests/Feature/CollectTest.php` | Приём данных, IP, страна, режимы приватности, боты, фильтрация мусора, Writer, очистка |
| `tests/Feature/ReportsTest.php` | Обзор, аудитория, устройства, карты, формы, цели, воронка, периоды |
| `tests/Feature/FilamentTest.php` | Регистрация кластера и 4 страниц, доступ и Gate, русский и английский интерфейс, переключатель языка, фильтры |

Проверить, что данные пишутся на живом сайте, можно и вручную:

```bash
    curl -i -X POST https://your-site.test/_wa/c \
      -H 'Content-Type: text/plain' \
      -H 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36' \
      -H 'Accept-Language: ru' -H 'Sec-Fetch-Site: same-origin' \
      --data '{"vid":"bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb","sid":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","nv":1,"sw":1920,"sh":1080,"e":[{"type":"pageview","path":"/test"}]}'
```

Ответ `204`, а в `analytics_sessions` должна появиться строка.

---

## 16. Структура пакета

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

## Лицензия

MIT. Данные геолокации DB-IP Lite распространяются по лицензии CC BY 4.0 (требуется указание источника DB-IP.com).

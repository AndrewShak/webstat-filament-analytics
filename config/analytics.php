<?php

declare(strict_types=1);

return [
    // Master switch: disables the collect endpoint (the dashboard keeps working).
    'enabled' => env('ANALYTICS_ENABLED', true),

    // Dedicated DB connection (null = default). Recommended for big sites.
    'connection' => env('ANALYTICS_DB_CONNECTION'),

    'route' => [
        'prefix' => env('ANALYTICS_ROUTE_PREFIX', '_wa'),
        'domain' => null,
        'rate_limit' => 240, // requests / minute / IP
    ],

    // Extra hosts allowed in the Origin header (the app host is always allowed).
    'allowed_hosts' => [],

    /*
     | Write buffer.
     |  direct : insert after the HTTP response was sent (terminating callback), zero infra.
     |  redis  : push to a Redis list, flushed by `analytics:flush` every minute in one batch.
     */
    'buffer' => [
        'driver' => env('ANALYTICS_BUFFER', 'direct'),
        'redis_connection' => env('ANALYTICS_REDIS_CONNECTION', 'default'),
        'key' => 'analytics:buffer',
        'flush_batch' => 2000,
        'max_length' => 250000, // hard cap: new payloads are dropped above this
    ],

    // Register analytics:flush / analytics:prune in the scheduler automatically.
    'schedule' => true,
    'retention_days' => 395,

    'privacy' => [
        // How the visitor IP is stored in analytics_sessions.ip:
        //   full       - complete address (default: shows exactly where visitors come from)
        //   anonymized - IPv4 last octet / IPv6 last 80 bits zeroed
        //   none       - not stored (the IP is still used transiently for country/city lookup)
        'ip' => env('ANALYTICS_STORE_IP', 'full'),
        // Headers consulted ONLY when the TCP peer is a private/reserved address
        // (i.e. the app sits behind a proxy/Docker network that Laravel does not trust).
        'ip_headers' => ['CF-Connecting-IP', 'True-Client-IP', 'X-Real-IP', 'X-Forwarded-For'],
        'respect_dnt' => true,           // honour Do-Not-Track / Global Privacy Control
        'cookie' => ['name' => '_wa_v', 'days' => 365], // first-party, renewed on every visit
        'session_minutes' => 30,
        'keep_query' => [],              // query params kept in the stored path (default: none)
    ],

    'tracking' => [
        'clicks' => true,
        'links' => true,
        'scroll' => true,
        'forms' => true,
        'spa' => true, // history API, Livewire wire:navigate, Turbo, htmx
    ],

    // Paths (without leading slash, Str::is patterns) that are never recorded.
    'ignore_paths' => ['livewire/*', 'livewire-*', 'filament/*', '_debugbar/*', '_wa/*'],

    'bots' => [
        'store' => true,     // keep bots (flagged) so the dashboard can split people / bots
        'threshold' => 50,   // score >= threshold => bot
        'patterns' => [],    // extra lowercase UA substrings => name
    ],

    'geo' => [
        // auto | headers | maxmind | null | FQCN implementing Ingest\Geo\GeoResolver
        // auto = CDN headers -> offline .mmdb database (php artisan analytics:geo-update)
        'driver' => env('ANALYTICS_GEO', 'auto'),
        // Offline database (MaxMind GeoLite2 or free DB-IP Lite in .mmdb format).
        'database' => storage_path('app/geoip/geoip-city.mmdb'),
        // Last-resort online lookup, executed AFTER the response (never blocks visitors) and cached per IP.
        // OFF by default because it sends visitor IPs to a third party.
        'http' => [
            'enabled' => env('ANALYTICS_GEO_HTTP', false),
            'provider' => 'ipwho',   // ipwho | ipapi
            'timeout' => 2,
            'cache_days' => 30,
            'max_lookups_per_batch' => 50,
        ],
    ],

    // FQCN implementing Ingest\DemographicsEnricher (age_group / gender from an external provider).
    'demographics' => ['enricher' => null],

    // Optional automatic injection of the tracker before </body> (off by default).
    'inject' => ['enabled' => false, 'global' => false],

    // Dashboard language. null = follow the app locale. Users can switch RU/EN in the dashboard.
    'locale' => env('ANALYTICS_LOCALE'),
    'locales' => ['ru', 'en'],

    'filament' => [
        'auto_register' => true,
        'panels' => ['*'],         // panel ids, or ['*']
        'ability' => null,         // Gate ability required to see the dashboard
        'navigation_group' => null,
        'navigation_sort' => 90,
        'cache_seconds' => 30,
    ],
];

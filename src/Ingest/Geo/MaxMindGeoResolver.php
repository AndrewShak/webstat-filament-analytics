<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest\Geo;

use Illuminate\Http\Request;

/**
 * Offline lookup (geoip2/geoip2) against any .mmdb City/Country database:
 * MaxMind GeoLite2 or the free DB-IP Lite (see `php artisan analytics:geo-update`).
 * Silently returns empty values when the database is missing.
 */
final class MaxMindGeoResolver implements GeoResolver
{
    private ?object $reader = null;

    public static function databasePath(): string
    {
        return (string) config('analytics.geo.database', config('analytics.geo.maxmind_database', storage_path('app/geoip/geoip-city.mmdb')));
    }

    public function resolve(Request $request, ?string $ip = null): array
    {
        $empty = ['country' => null, 'city' => null];
        $class = '\\GeoIp2\\Database\\Reader';
        $db = self::databasePath();
        $ip ??= $request->ip();
        if (! $ip || ! class_exists($class) || ! is_file($db)) {
            return $empty;
        }

        try {
            $this->reader ??= new $class($db);
            try {
                $rec = $this->reader->city($ip);
                $city = $rec->city->name;
            } catch (\Throwable) { // country-only database
                $rec = $this->reader->country($ip);
                $city = null;
            }

            return ['country' => $rec->country->isoCode, 'city' => $city ? mb_substr($city, 0, 96) : null];
        } catch (\Throwable) {
            return $empty; // address not in database
        }
    }
}

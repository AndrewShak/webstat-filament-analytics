<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** HTTPS lookup with a per-IP cache. Providers: ipwho (ipwho.is), ipapi (ipapi.co). */
final class HttpIpLookup implements IpLookup
{
    public function lookup(string $ip): array
    {
        $empty = ['country' => null, 'city' => null];
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $empty;
        }

        $days = (int) config('analytics.geo.http.cache_days', 30);

        return Cache::remember('analytics:geo:' . md5($ip), now()->addDays(max(1, $days)), function () use ($ip, $empty): array {
            try {
                $provider = (string) config('analytics.geo.http.provider', 'ipwho');
                $url = $provider === 'ipapi' ? "https://ipapi.co/{$ip}/json/" : "https://ipwho.is/{$ip}";
                $res = Http::timeout((int) config('analytics.geo.http.timeout', 2))->acceptJson()->get($url);
                if (! $res->ok() || $res->json('success') === false || $res->json('error') === true) {
                    return $empty;
                }
                $cc = $res->json('country_code');
                $city = $res->json('city');

                return [
                    'country' => is_string($cc) && preg_match('/^[A-Za-z]{2}$/', $cc) ? strtoupper($cc) : null,
                    'city' => is_string($city) && $city !== '' ? mb_substr($city, 0, 96) : null,
                ];
            } catch (\Throwable) {
                return $empty;
            }
        });
    }
}

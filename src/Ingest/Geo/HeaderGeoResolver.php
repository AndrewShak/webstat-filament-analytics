<?php

declare(strict_types=1);

namespace Webstat\Analytics\Ingest\Geo;

use Illuminate\Http\Request;

/** Reads geo headers set by CDNs / edge platforms (Cloudflare, CloudFront, Vercel, GAE, nginx geoip). */
final class HeaderGeoResolver implements GeoResolver
{
    private const COUNTRY = ['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Vercel-IP-Country', 'X-AppEngine-Country', 'X-Geo-Country'];
    private const CITY = ['CF-IPCity', 'CloudFront-Viewer-City', 'X-Vercel-IP-City', 'X-AppEngine-City', 'X-Geo-City'];

    public function resolve(Request $request, ?string $ip = null): array
    {
        $country = $this->first($request, self::COUNTRY);
        $country = ($country && preg_match('/^[A-Za-z]{2}$/', $country) && ! in_array(strtoupper($country), ['XX', 'T1', 'ZZ'], true))
            ? strtoupper($country) : null;
        $city = $this->first($request, self::CITY);

        return ['country' => $country, 'city' => $city ? mb_substr(urldecode($city), 0, 96) : null];
    }

    /** @param list<string> $names */
    private function first(Request $r, array $names): ?string
    {
        foreach ($names as $n) {
            $v = $r->headers->get($n);
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return null;
    }
}
